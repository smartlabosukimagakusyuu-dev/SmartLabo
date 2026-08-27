<?php
/**
 * 架空店舗による全工程通し確認（HP-ONBOARDING-4F）
 *
 *   php -c intake-api/dev/php.ini intake-api/dev/e2e-walkthrough.php
 *
 * ★**使い捨てのDBを毎回作り直して**確認する。終わったら消す。
 *   **本番・既存DB（dev/.preview/ を含む）へは一切接続しない。**
 * ★架空の店舗・架空のメール・架空のフォルダURLだけを使う。
 * ★実メール送信・Drive API・Stripe・Operations・AI Sales への接続を一切行わない。
 * ★表示される案件番号・リンクはこの端末の使い捨てDB専用。報告書へ貼らない。
 *
 * 何を確かめるか（4F §5〜§16）:
 *   A 管理者による案件開始      G reviewed と修正依頼
 *   B 店舗による初回交換        H token 再発行
 *   C 入力・途中保存            I locked
 *   D Drive 完了申告            J retention（既定フラグ false）
 *   E 確認・提出                K closed 後の全面拒否
 *   F 管理確認・export          L maintenance
 *                               M 回答の正式構造（4F-R1）
 *                               N 必須契約・Smart Labo 設定（4F-R3）
 *
 * ★J の「削除が成功すること」の確認は dev/retention-walkthrough.php が担当する
 *   （別の使い捨てDBで、override したときだけ動く）。ここでは
 *   **本番想定の既定状態（フラグ false）で削除できないこと**を確かめる。
 * ★ブラウザからしか見えない項目（location.hash / localStorage / document.cookie /
 *   console / Referer）は、このスクリプトの対象外である。実ブラウザで確認する。
 */
declare(strict_types=1);

require_once __DIR__ . '/../src/Autoload.php';

use SmartLabo\Intake\Config;
use SmartLabo\Intake\Http\Request;
use SmartLabo\Intake\Kernel;
use SmartLabo\Intake\Migrator;
use SmartLabo\Intake\Service\CaseService;
use SmartLabo\Intake\Service\RetentionService;
use SmartLabo\Intake\Support\Clock;

/* ---------------------------------------------------------------- 架空の値 */

const E2E_ORIGIN   = 'http://127.0.0.1:8788';
const E2E_ADMIN_ID = 'e2e-admin';
const E2E_ADMIN_PW = 'e2e-only-password-0123456789abcd';

/** 実在しない店舗・連絡先。example.invalid は RFC 2606 の予約ドメイン */
const E2E_SHOP     = '架空サロン ハルカゼ';
const E2E_MARKER   = 'E2EMARKER00000001';
const E2E_EMAIL    = 'materials@example.invalid';
const E2E_TEL      = '03-0000-0000';
const E2E_DRIVE    = 'https://drive.google.com/drive/folders/FAKE-E2E-0000000000';
/** 修正依頼のメッセージに入れる目印 */
const E2E_REVISION = 'REVISIONMARKER0001';

final class E2eClock extends Clock
{
    public int $offset = 0;

    public function now(): int
    {
        return time() + $this->offset;
    }

    public function advance(int $seconds): void
    {
        $this->offset += $seconds;
    }
}

/* ---------------------------------------------------------------- 進行管理 */

$phase = '';
$step  = 0;
$bad   = [];

$head = static function (string $title) use (&$phase): void {
    $phase = $title;
    echo "\n" . $title . "\n";
};
$check = static function (string $label, bool $ok) use (&$step, &$bad, &$phase): void {
    ++$step;
    if (!$ok) {
        $bad[] = $phase . ' / ' . $label;
    }
    printf("  %3d. [%s] %s\n", $step, $ok ? 'OK' : 'NG', $label);
};

/* ---------------------------------------------------------------- 使い捨て環境 */

$roots = [];

$makeEnv = static function (string $name, E2eClock $clock, bool $retention) use (&$roots): array {
    $base = __DIR__ . '/.e2e-' . getmypid() . '-' . $name;
    if (!is_dir($base)) {
        mkdir($base . '/logs', 0700, true);
    }
    $roots[] = $base;

    $config = Config::load([
        'db_path'         => $base . '/intake.sqlite',
        'ip_hmac_key'     => 'e2e-only-ip-hmac-key-0123456789abcdef',
        'enc_key'         => 'e2e-only-enc-key-0123456789abcdefghij',
        'allowed_origins' => [E2E_ORIGIN],
        'rate_limit_dir'  => $base . '/ratelimit',
        'log_path'        => $base . '/logs/intake.log',
        'require_https'   => false,
        'admin_id'        => E2E_ADMIN_ID,
        // ★hash はその場で作る。Git へ hash も入れない
        'admin_password_hash' => password_hash(
            E2E_ADMIN_PW,
            defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT
        ),
        // ★既定は false。retention を true にするのは「ローカル限定の削除検証」だけ
        'retention_actions_enabled' => $retention,
        'backup_policy_confirmed'   => $retention,
    ]);

    return [new Kernel($config, $clock), $base];
};

/* ---------------------------------------------------------------- 要求の組み立て */

$json = static function (string $method, string $path, array $body, array $cookies = [], array $opt = []): Request {
    $headers = ['Content-Type' => $opt['ctype'] ?? 'application/json'];
    if (($opt['no_origin'] ?? false) !== true) {
        $headers['Origin'] = $opt['origin'] ?? E2E_ORIGIN;
    }
    foreach ($opt['headers'] ?? [] as $k => $v) {
        $headers[$k] = $v;
    }

    return new Request(
        method: $method,
        path: $path,
        headers: $headers,
        body: (string)json_encode($body),
        cookies: $cookies,
        isHttps: false,
        clientIp: $opt['ip'] ?? '127.0.0.1',
    );
};
$shopGet = static function (string $path, array $cookies = [], array $opt = []): Request {
    $headers = ['Sec-Fetch-Site' => $opt['site'] ?? 'same-origin', 'Sec-Fetch-Mode' => 'cors'];
    if (($opt['no_origin'] ?? false) !== true) {
        $headers['Origin'] = $opt['origin'] ?? E2E_ORIGIN;
    }

    return new Request(
        method: 'GET', path: $path, headers: $headers, body: '',
        cookies: $cookies, isHttps: false, clientIp: '127.0.0.1',
    );
};
$adminGet = static function (string $path, array $cookies = [], array $query = []): Request {
    return new Request(
        method: 'GET', path: $path,
        headers: ['Origin' => E2E_ORIGIN, 'Sec-Fetch-Site' => 'same-origin'],
        body: '', cookies: $cookies, isHttps: false, clientIp: '127.0.0.1', query: $query,
    );
};
$adminPost = static function (string $path, array $fields, array $cookies = [], array $opt = []): Request {
    $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];
    if (($opt['no_origin'] ?? false) !== true) {
        $headers['Origin'] = $opt['origin'] ?? E2E_ORIGIN;
    }

    return new Request(
        method: $opt['method'] ?? 'POST', path: $path, headers: $headers,
        body: http_build_query($fields), cookies: $cookies, isHttps: false, clientIp: '127.0.0.1',
    );
};
$csrfOf = static function (?string $html): string {
    preg_match('/name="csrf_token" value="([A-Za-z0-9_-]{43})"/', (string)$html, $m);

    return (string)($m[1] ?? '');
};
$uuid4 = static function (): string {
    $b    = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
};
/** Smart Labo の制作設定（SSOT v1.9 §3.12）を埋める。書き出しの前に要る */
$setSettings = static function (Kernel $k, int $caseId): void {
    $k->answers->saveAdminSettings($caseId, [
        'web_links' => ['salon_booking_url' => null],
        'privacy'   => [
            'destination'       => '架空の送信先',
            'storage'           => '架空の保管方法',
            'external_services' => [],
            'consent_checkbox'  => true,
        ],
    ]);
};
$rows = static function (Kernel $k, string $sql, array $p = []): int {
    $stmt = $k->db->pdo()->prepare($sql);
    $stmt->execute($p);

    return (int)$stmt->fetchColumn();
};

echo "HP Intake — 架空店舗による全工程通し確認（4F）\n";
echo str_repeat('=', 66) . "\n";
echo "  架空店舗のみ／使い捨てDB／外部接続なし\n";

$clock          = new E2eClock();
[$k, $baseMain] = $makeEnv('main', $clock, false);

/* ================================================================ A */

$head('A. 管理者による案件開始（4F §5）');

// 1 未認証
$noAuth = [];
foreach (['/admin/', '/admin/case', '/admin/retention', '/admin/maintenance', '/admin/export'] as $p) {
    $res = $k->app->handle($adminGet($p, [], ['case' => 'HP-202608-0001']));
    $noAuth[] = $res->status === 303 && ($res->headers['Location'] ?? '') === '/admin/login'
        && !str_contains((string)$res->rawBody, 'HP-202608');
}
$check('未認証は全5経路でログインへ送られ、案件情報を出さない', !in_array(false, $noAuth, true));

// 2 誤った資格情報
$bad1 = $k->app->handle($adminPost('/admin/login', ['admin_id' => 'nobody', 'password' => 'wrong']));
$bad2 = $k->app->handle($adminPost('/admin/login', ['admin_id' => E2E_ADMIN_ID, 'password' => 'wrong']));
$check('存在しないIDと正しいIDで、応答が1文字も変わらない',
    $bad1->status === $bad2->status && (string)$bad1->rawBody === (string)$bad2->rawBody);
$check('失敗では管理 session を作らない',
    $bad1->cookies === [] && $bad2->cookies === []
    && $rows($k, 'SELECT COUNT(*) FROM intake_admin_sessions') === 0);

// 3 正常ログイン
$login  = $k->app->handle($adminPost('/admin/login', ['admin_id' => E2E_ADMIN_ID, 'password' => E2E_ADMIN_PW]));
$secret = (string)$login->cookies[0]['value'];
$admin  = [Config::ADMIN_COOKIE_NAME => $secret];
$attr   = $login->cookies[0]['attributes'];
$check('ログイン成功。Cookie は Secure / HttpOnly / SameSite=Strict / Path=/admin',
    $login->status === 303 && $attr['Secure'] === true && $attr['HttpOnly'] === true
    && $attr['SameSite'] === 'Strict' && $attr['Path'] === '/admin'
    && $login->cookies[0]['name'] === Config::ADMIN_COOKIE_NAME);
$check('DB には session hash だけ（平文列が無く、平文が保存されていない）',
    $rows($k, 'SELECT COUNT(*) FROM intake_admin_sessions WHERE session_hash = :h',
        [':h' => hash('sha256', $secret)]) === 1
    && !in_array('session_secret', array_column(
        $k->db->pdo()->query("PRAGMA table_info('intake_admin_sessions')")->fetchAll(), 'name'), true));

$listA = $k->app->handle($adminGet('/admin/', $admin));
$listB = $k->app->handle($adminGet('/admin/', $admin));
$check('CSRF は画面を開くたびに新しくなる',
    $csrfOf($listA->rawBody) !== '' && $csrfOf($listA->rawBody) !== $csrfOf($listB->rawBody));

// 4 案件作成
$newForm = $k->app->handle($adminGet('/admin/new', $admin));
$created = $k->app->handle($adminPost('/admin/create', [
    'csrf_token'        => $csrfOf($newForm->rawBody),
    'shop_display_name' => E2E_SHOP . ' ' . E2E_MARKER,
    'contract_type'     => 'salon',
    'drive_url'         => E2E_DRIVE,
    'drive_shared_email' => E2E_EMAIL,
], $admin));

preg_match('/(HP-\d{6}-\d{4})/', (string)$created->rawBody, $m);
$number = (string)($m[1] ?? '');
$case   = $k->cases->findByNumber($number);
$caseId = (int)($case['id'] ?? 0);

$check('案件番号はサーバー採番。店舗名を含まない',
    preg_match('/^HP-\d{6}-\d{4}$/', $number) === 1 && !str_contains($number, E2E_MARKER));
$check('初期 status=draft・回答 schema version=1・回答行が初期化される',
    (string)$case['status'] === 'draft'
    && (int)$case['schema_version'] === Migrator::ANSWER_SCHEMA_VERSION
    && $rows($k, 'SELECT COUNT(*) FROM intake_answers WHERE intake_case_id = :i', [':i' => $caseId]) === 1);

// 5 Drive
$reject = [];
foreach ([
    'http://drive.google.com/drive/folders/x',            // https でない
    'https://drive.google.com:8443/drive/folders/x',      // port
    'https://user@drive.google.com/drive/folders/x',      // userinfo
    'https://bit.ly/abc',                                 // 短縮
    'https://evil.example.invalid/drive/folders/x',       // 別 host
    'https://drive.google.com.evil.invalid/x',            // 似せた host
] as $badUrl) {
    try {
        $k->cases->setDriveFolder($caseId, $badUrl, 'x', E2E_EMAIL);
        $reject[] = false;
    } catch (\InvalidArgumentException $e) {
        $reject[] = true;
    }
}
$check('Drive URL は https の正式ホストのみ。6種の不正を拒否', !in_array(false, $reject, true));

$enc = $k->cases->find($caseId);
$check('Drive URL と共有先メールは暗号文だけを保存し、復号は認証経路からのみ',
    $enc['drive_folder_url_enc'] !== null
    && !str_contains((string)$enc['drive_folder_url_enc'], 'drive.google.com')
    && !str_contains((string)$enc['drive_shared_email_enc'], 'example.invalid')
    && $k->cases->driveFolderUrl($caseId) === E2E_DRIVE
    && $k->cases->driveSharedEmail($caseId) === E2E_EMAIL);

$listHtml = (string)$k->app->handle($adminGet('/admin/', $admin))->rawBody;
$check('一覧に店舗名も Drive 情報も出さない',
    !str_contains($listHtml, E2E_MARKER) && !str_contains($listHtml, 'drive.google.com')
    && !str_contains($listHtml, E2E_EMAIL) && str_contains($listHtml, $number));

// 6 初回 token
preg_match('#/start\#([A-Za-z0-9_-]{43})#', (string)$created->rawBody, $tm);
$token = (string)($tm[1] ?? '');
$check('ご案内リンクの平文は作成画面に1回だけ出る',
    strlen($token) === 43 && substr_count((string)$created->rawBody, $token) === 1);
$check('DB は SHA-256 hash のみ・14日・1本',
    $rows($k, 'SELECT COUNT(*) FROM intake_tokens WHERE token_hash = :h', [':h' => hash('sha256', $token)]) === 1
    && $k->tokens->activeCount($caseId) === 1
    && !in_array('token', array_column(
        $k->db->pdo()->query("PRAGMA table_info('intake_tokens')")->fetchAll(), 'name'), true));

$resend = $k->app->handle($adminPost('/admin/create', [
    'csrf_token'        => $csrfOf($newForm->rawBody),   // ★同じ CSRF を再送
    'shop_display_name' => E2E_SHOP,
    'contract_type'     => 'salon',
], $admin));
$check('同一 CSRF の再送では案件も token も増えない',
    $resend->status === 403 && $rows($k, 'SELECT COUNT(*) FROM intake_cases') === 1);

/* ================================================================ B */

$head('B. 店舗による初回交換（4F §6）');

$start   = $k->app->handle($json('POST', '/session/start', ['token' => $token]));
$sid     = (string)$start->cookies[0]['value'];
$shop    = [Config::COOKIE_NAME => $sid];
$sAttr   = $start->cookies[0]['attributes'];
$check('token を1度だけ交換して店舗 session を発行する',
    $start->status === 200 && strlen($sid) === 43);
$check('店舗 Cookie も Secure / HttpOnly / SameSite=Strict / Path=/',
    $sAttr['Secure'] === true && $sAttr['HttpOnly'] === true
    && $sAttr['SameSite'] === 'Strict' && $sAttr['Path'] === '/'
    && $start->cookies[0]['name'] === Config::COOKIE_NAME);
$check('DB は session hash のみ（平文列が無い）',
    $rows($k, 'SELECT COUNT(*) FROM intake_sessions WHERE session_hash = :h',
        [':h' => hash('sha256', $sid)]) === 1
    && !in_array('session_secret', array_column(
        $k->db->pdo()->query("PRAGMA table_info('intake_sessions')")->fetchAll(), 'name'), true));
$check('応答本文に token も session 平文も出さない',
    !str_contains($start->json(), $token) && !str_contains($start->json(), $sid));

// ★不正 token の試行は**別のIP**から行う。本流のレート制限枠を食わないため
$probeIp = ['ip' => '127.0.0.9'];
$fixed   = [];
foreach ([
    ['token' => 'not-a-token'],                        // 形式不正
    ['token' => str_repeat('z', 43)],                  // 存在しない
    ['token' => ''],                                   // 空
    [],                                                // 欠落
] as $body) {
    $res     = $k->app->handle($json('POST', '/session/start', $body, [], $probeIp));
    $fixed[] = ($res->body['message'] ?? '') === \SmartLabo\Intake\Http\Response::UNAVAILABLE_MESSAGE
        || $res->status === 400;
}
$check('無効・失効・形式不正の token はすべて固定文言', !in_array(false, $fixed, true));

// 5回目までは通し、6回目で頭打ちになる（SSOT §0.2 token_start = 10分5回）
$k->app->handle($json('POST', '/session/start', ['token' => str_repeat('y', 43)], [], $probeIp));
$limited = $k->app->handle($json('POST', '/session/start', ['token' => str_repeat('y', 43)], [], $probeIp));
$check('無効 token の連打は 10分5回で頭打ちになる（429）', $limited->status === 429);

/* ================================================================ C */

$head('C. 入力・途中保存（4F §7）');

$sections = require __DIR__ . '/walkthrough-answers.php';
$sections['basic']['legal_name'] = E2E_SHOP . ' ' . E2E_MARKER;
$sections['basic']['internal_contact']['phone'] = E2E_TEL;

// 分類ごとに1回ずつ保存する（＝ステップ移動のたびに変更分類だけ送る挙動）
$version = 1;
$partial = [];
foreach (Migrator::ANSWER_SECTIONS as $i => $key) {
    $res = $k->app->handle($json('POST', '/answers/save', [
        'version'  => $version,
        'sections' => [$key => $sections[$key]],
    ], $shop));
    $partial[] = $res->status === 200 && (int)$res->body['version'] === $version + 1;
    $version   = (int)$res->body['version'];
    unset($i);
}
$check('11分類を1つずつ保存でき、version が毎回1つ進む（楽観ロック）',
    !in_array(false, $partial, true) && $version === 12);

$stale = $k->app->handle($json('POST', '/answers/save', [
    'version' => 1, 'sections' => ['basic' => ['legal_name' => '上書きされてはいけない']],
], $shop));
$check('古い version の保存は 409。自動で上書きしない',
    $stale->status === 409
    && $k->answers->get($caseId)['sections']['basic']['legal_name'] === E2E_SHOP . ' ' . E2E_MARKER);

$unknownSection = $k->app->handle($json('POST', '/answers/save', [
    'version' => $version, 'sections' => ['unknown_section' => ['a' => 1]],
], $shop));
$oversize = $k->app->handle($json('POST', '/answers/save', [
    'version' => $version, 'sections' => ['menus' => array_fill(0, 200, ['name' => 'x'])],
], $shop));
$check('未知の分類・配列上限超過は保存を拒否（切り捨てない）',
    $unknownSection->status === 400 && $oversize->status === 400
    && $k->answers->get($caseId)['sections']['basic']['legal_name'] === E2E_SHOP . ' ' . E2E_MARKER
    && count($k->answers->get($caseId)['sections']['menus']) === 1);

$reload = $k->app->handle($shopGet('/case', $shop));
$check('再読込に相当する GET /case で、保存済み回答と version が復元される',
    $reload->status === 200
    && (int)$reload->body['version'] === $version
    && $reload->body['sections']['basic']['legal_name'] === E2E_SHOP . ' ' . E2E_MARKER);

$check('必須22パスが充足している（不足0件）', $k->answers->evaluate($caseId)['missing'] === []);

$log = static fn (string $b): string => is_file($b . '/logs/intake.log')
    ? (string)file_get_contents($b . '/logs/intake.log') : '';
$check('アプリログに回答本文・氏名・電話・token・session を出さない',
    !str_contains($log($baseMain), E2E_MARKER) && !str_contains($log($baseMain), E2E_TEL)
    && !str_contains($log($baseMain), $token) && !str_contains($log($baseMain), $sid));

/* ================================================================ D */

$head('D. Drive 案内・完了申告（4F §8）');

$caseRes = $k->app->handle($shopGet('/case', $shop));
$check('認証済みの店舗にだけ Drive URL と共有先メールを返す',
    ($caseRes->body['drive']['folder_url'] ?? null) === E2E_DRIVE
    && ($caseRes->body['drive']['shared_email'] ?? null) === E2E_EMAIL);

$anon = $k->app->handle($shopGet('/case', []));
$check('未認証には案件情報も Drive 情報も返さない',
    $anon->status === 404 && !str_contains($anon->json(), 'drive.google.com'));

$noCheck = $k->app->handle($json('POST', '/drive/confirm', ['confirmed' => false], $shop));
$check('チェック無し（confirmed=false）では申告を受け付けない',
    $noCheck->status === 400 && $k->cases->find($caseId)['drive_upload_confirmed_at'] === null);

$c1 = $k->app->handle($json('POST', '/drive/confirm', ['confirmed' => true], $shop));
$at = $k->cases->find($caseId)['drive_upload_confirmed_at'];
$a1 = $k->audit->countFor($caseId, 'drive_upload_confirmed');
$c2 = $k->app->handle($json('POST', '/drive/confirm', ['confirmed' => true], $shop));
$check('申告は冪等。再送で時刻を上書きせず監査も増やさない',
    $c1->status === 200 && $c2->status === 200
    && $c1->body['newly_recorded'] === true && $c2->body['newly_recorded'] === false
    && $k->cases->find($caseId)['drive_upload_confirmed_at'] === $at
    && $k->audit->countFor($caseId, 'drive_upload_confirmed') === $a1);

/* ================================================================ E */

$head('E. 確認・提出（4F §9）');

$sub1   = $uuid4();
$submit = $k->app->handle($json('POST', '/submit', ['submission_id' => $sub1], $shop));
$check('submission_id は UUID v4 の形',
    preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $sub1) === 1);
$check('提出成功。status=submitted・履歴1件・監査1件',
    $submit->status === 200
    && (string)$k->cases->find($caseId)['status'] === 'submitted'
    && $rows($k, "SELECT COUNT(*) FROM intake_submission_history WHERE intake_case_id = :i AND event_type = 'submitted'", [':i' => $caseId]) === 1
    && $k->audit->countFor($caseId, 'submitted') === 1);

$again = $k->app->handle($json('POST', '/submit', ['submission_id' => $sub1], $shop));
$check('同一 submission_id の再送は同じ結果。履歴も監査も増えない',
    $again->status === $submit->status
    && $rows($k, "SELECT COUNT(*) FROM intake_submission_history WHERE intake_case_id = :i AND event_type = 'submitted'", [':i' => $caseId]) === 1
    && $k->audit->countFor($caseId, 'submitted') === 1);

$other = $k->app->handle($json('POST', '/submit', ['submission_id' => $uuid4()], $shop));
$check('別の submission_id での再提出は 409。副作用なし・内容を返さない',
    $other->status === 409 && ($other->body['error'] ?? '') === 'already_submitted'
    && !str_contains($other->json(), $sub1) && !str_contains($other->json(), E2E_MARKER)
    && $k->audit->countFor($caseId, 'submitted') === 1);

$check('submission_id は履歴列にだけ残り、監査・ログへ出さない',
    $rows($k, 'SELECT COUNT(*) FROM intake_submission_history WHERE submission_id = :s', [':s' => $sub1]) === 1
    && !str_contains($log($baseMain), $sub1));

/* ================================================================ F */

$head('F. 管理者による確認・書き出し（4F §10）');

$detail = $k->app->handle($adminGet('/admin/case', $admin, ['case' => $number]));
$html   = (string)$detail->rawBody;
$check('管理詳細で回答を表示できる（エスケープ済み・生タグが出ない）',
    $detail->status === 200 && str_contains($html, E2E_MARKER)
    && !preg_match('/<script[^>]*>[^<]*E2EMARKER/', $html));
$check('詳細に token / session / 内部ID / IP を出さない',
    !str_contains($html, $token) && !str_contains($html, $sid)
    && !str_contains($html, 'token_hash') && !str_contains($html, 'ip_hmac')
    && !preg_match('/"id"\s*:\s*' . $caseId . '\b/', $html));

// ★4F-R3: 制作設定が無いうちは書き出せない（代表判断 Q4）
$beforeSettings = $k->app->handle($adminGet('/admin/export', $admin, ['case' => $number]));
$check('制作設定が無いうちは書き出せない（店舗の提出は済んでいる）',
    $beforeSettings->status === 409
    && $k->export->export($caseId)['error'] === 'admin_settings_missing');

$setSettings($k, $caseId);
$check('Smart Labo の制作設定5件を入れた', $k->answers->missingAdminSettings($caseId) === []);

$export = $k->app->handle($adminGet('/admin/export', $admin, ['case' => $number]));
$body   = (string)$export->rawBody;
$decoded = json_decode($body, true);
$check('書き出しは attachment / no-store / nosniff / SHA-256 一致',
    $export->status === 200
    && str_starts_with((string)$export->headers['Content-Type'], 'application/json')
    && str_contains((string)$export->headers['Content-Disposition'], 'attachment; filename="')
    && str_contains((string)$export->headers['Cache-Control'], 'no-store')
    && ($export->headers['X-Content-Type-Options'] ?? '') === 'nosniff'
    && hash('sha256', $body) === ($export->headers['X-Intake-Export-Sha256'] ?? ''));
$check('書き出しに秘密値・Drive 情報・内部IDを含めない',
    is_array($decoded)
    && !str_contains($body, $token) && !str_contains($body, $sid)
    && !str_contains($body, 'drive.google.com') && !str_contains($body, E2E_EMAIL)
    && !str_contains($body, 'token_hash') && !str_contains($body, 'ip_hmac')
    && !str_contains($body, 'csrf') && !str_contains($body, '"id"'));
$check('書き出しに正式な回答は入っている（検査が空振りしていない）',
    str_contains($body, E2E_MARKER) && ($decoded['source'] ?? '') === 'hp_intake');
$check('書き出し本文をログへ出さない',
    !str_contains($log($baseMain), 'export_schema_version') && str_contains($log($baseMain), 'export_generated'));

/* ================================================================ G */

$head('G. reviewed と修正依頼（4F §11）');

$d2 = $k->app->handle($adminGet('/admin/case', $admin, ['case' => $number]));
$k->app->handle($adminPost('/admin/status', [
    'csrf_token' => $csrfOf($d2->rawBody), 'case' => $number, 'to' => 'reviewed',
], $admin));
$check('submitted → reviewed', (string)$k->cases->find($caseId)['status'] === 'reviewed');

// 未知パスを1つ混ぜたら丸ごと拒否
$mixed = $k->revisions->validate(['basic.legal_name', 'basic.not_a_real_path'], null);
$check('未知パスが1つでもあれば修正依頼を丸ごと拒否', $mixed['ok'] !== true);

$revForm = $k->app->handle($adminGet('/admin/revision', $admin, ['case' => $number]));
$revBody = http_build_query([
    'csrf_token' => $csrfOf($revForm->rawBody),
    'case'       => $number,
    'message'    => '架空の修正理由です ' . E2E_REVISION,
]) . '&paths%5B%5D=basic.legal_name&paths%5B%5D=promotion.concept';
$sendRev = $k->app->handle(new Request(
    method: 'POST', path: '/admin/revision/send',
    headers: ['Content-Type' => 'application/x-www-form-urlencoded', 'Origin' => E2E_ORIGIN],
    body: $revBody, cookies: $admin, isHttps: false, clientIp: '127.0.0.1',
));
$check('reviewed → needs_revision。状態変更と依頼作成が同時に成立',
    $sendRev->status === 303
    && (string)$k->cases->find($caseId)['status'] === 'needs_revision'
    && $rows($k, "SELECT COUNT(*) FROM intake_revision_requests WHERE intake_case_id = :i AND status = 'open'", [':i' => $caseId]) === 1);

$shopCase = $k->app->handle($shopGet('/case', $shop));
$open     = $shopCase->body['revision_requests'] ?? [];
$check('店舗には open の依頼だけを返す（対象パスとメッセージつき）',
    count($open) === 1
    && ($open[0]['message'] ?? '') === '架空の修正理由です ' . E2E_REVISION
    && in_array('basic.legal_name', $open[0]['requested_paths'] ?? [], true));

// ★保存の契約は「変更した**分類ごと**」。分類の中身は毎回まるごと送る
$fixedBasic = $k->answers->get($caseId)['sections']['basic'];
$fixedBasic['legal_name'] = E2E_SHOP . ' ' . E2E_MARKER . '（修正後）';
$k->app->handle($json('POST', '/answers/save', [
    'version'  => $k->answers->get($caseId)['version'],
    'sections' => ['basic' => $fixedBasic],
], $shop));
$resub = $k->app->handle($json('POST', '/submit', ['submission_id' => $uuid4()], $shop));
$check('店舗が修正して再提出でき、open が resolved になる',
    $resub->status === 200
    && (string)$k->cases->find($caseId)['status'] === 'submitted'
    && $rows($k, "SELECT COUNT(*) FROM intake_revision_requests WHERE intake_case_id = :i AND status = 'open'", [':i' => $caseId]) === 0
    && $rows($k, "SELECT COUNT(*) FROM intake_revision_requests WHERE intake_case_id = :i AND status = 'resolved'", [':i' => $caseId]) === 1);

$exp2 = $k->app->handle($adminGet('/admin/export', $admin, ['case' => $number]));
$check('書き出しに修正依頼のメッセージ本文を含めない',
    !str_contains((string)$exp2->rawBody, E2E_REVISION)
    && str_contains((string)$exp2->rawBody, 'revision_requests'));
$check('修正理由をログ・監査へ出さない',
    !str_contains($log($baseMain), E2E_REVISION)
    && $rows($k, 'SELECT COUNT(*) FROM intake_audit_events WHERE result_code LIKE :m',
        [':m' => '%' . E2E_REVISION . '%']) === 0);

/* ================================================================ H */

$head('H. ご案内リンクの再発行（4F §12）');

// 再発行できる状態へ戻す
$d3 = $k->app->handle($adminGet('/admin/case', $admin, ['case' => $number]));
$revBody2 = http_build_query([
    'csrf_token' => $csrfOf($d3->rawBody), 'case' => $number, 'message' => '再発行の確認用',
]) . '&paths%5B%5D=basic.legal_name';
$k->app->handle(new Request(
    method: 'POST', path: '/admin/revision/send',
    headers: ['Content-Type' => 'application/x-www-form-urlencoded', 'Origin' => E2E_ORIGIN],
    body: $revBody2, cookies: $admin, isHttps: false, clientIp: '127.0.0.1',
));

$answersBefore  = $k->answers->get($caseId);
$revisionsCount = $rows($k, 'SELECT COUNT(*) FROM intake_revision_requests WHERE intake_case_id = :i', [':i' => $caseId]);

$reForm = $k->app->handle($adminGet('/admin/reissue', $admin, ['case' => $number]));
$ngName = $k->app->handle($adminPost('/admin/reissue/send', [
    'csrf_token' => $csrfOf($reForm->rawBody), 'case' => $number, 'confirm_case' => 'HP-202608-9999',
], $admin));
$check('案件番号の再入力が一致しないと再発行しない',
    $ngName->status === 400 && $k->tokens->activeCount($caseId) === 1);

$okRe = $k->app->handle($adminPost('/admin/reissue/send', [
    'csrf_token' => $csrfOf($ngName->rawBody), 'case' => $number, 'confirm_case' => $number,
], $admin));
preg_match('#/start\#([A-Za-z0-9_-]{43})#', (string)$okRe->rawBody, $tm2);
$token2 = (string)($tm2[1] ?? '');
$check('新しいリンクを1回だけ表示し、旧 token は失効する',
    $okRe->status === 200 && strlen($token2) === 43 && $token2 !== $token
    && substr_count((string)$okRe->rawBody, $token2) === 1
    && $k->tokens->activeCount($caseId) === 1);
$check('旧リンクも旧 session も使えない',
    $k->app->handle($json('POST', '/session/start', ['token' => $token]))->status === 404
    && $k->app->handle($shopGet('/case', $shop))->status === 404);
$check('回答・version・Drive 情報・修正依頼は維持される',
    $k->answers->get($caseId) == $answersBefore
    && $k->cases->driveFolderUrl($caseId) === E2E_DRIVE
    && $rows($k, 'SELECT COUNT(*) FROM intake_revision_requests WHERE intake_case_id = :i', [':i' => $caseId]) === $revisionsCount);
$check('token_reissued 監査が1件。平文も hash もログへ出さない',
    $k->audit->countFor($caseId, 'token_reissued') === 1
    && !str_contains($log($baseMain), $token2)
    && !str_contains($log($baseMain), hash('sha256', $token2)));

$dup = $k->app->handle($adminPost('/admin/reissue/send', [
    'csrf_token' => $csrfOf($ngName->rawBody), 'case' => $number, 'confirm_case' => $number,
], $admin));
$check('二重 POST は CSRF で止まり、token が増えない',
    $dup->status === 403 && $k->tokens->activeCount($caseId) === 1);

// 新しいリンクで再開し、再提出して reviewed まで戻す
$start2 = $k->app->handle($json('POST', '/session/start', ['token' => $token2]));
$shop2  = [Config::COOKIE_NAME => (string)$start2->cookies[0]['value']];
$check('新しいリンクから入力を再開できる',
    $start2->status === 200
    && $k->app->handle($shopGet('/case', $shop2))->body['sections']['basic']['legal_name']
       === E2E_SHOP . ' ' . E2E_MARKER . '（修正後）');

$k->app->handle($json('POST', '/submit', ['submission_id' => $uuid4()], $shop2));
$d4 = $k->app->handle($adminGet('/admin/case', $admin, ['case' => $number]));
$k->app->handle($adminPost('/admin/status', [
    'csrf_token' => $csrfOf($d4->rawBody), 'case' => $number, 'to' => 'reviewed',
], $admin));

$refuse = [];
foreach (['submitted', 'reviewed', 'locked', 'closed', 'unknown_state'] as $st) {
    $refuse[] = !in_array($st, CaseService::REISSUABLE, true);
}
$check('submitted / reviewed / locked / closed / 未知状態では再発行しない',
    !in_array(false, $refuse, true)
    && (string)$k->cases->find($caseId)['status'] === 'reviewed');

/* ================================================================ I */

$head('I. 入力の確定（locked）（4F §13）');

$lockForm = $k->app->handle($adminGet('/admin/lock', $admin, ['case' => $number]));
$lockNg   = $k->app->handle($adminPost('/admin/lock/send', [
    'csrf_token' => $csrfOf($lockForm->rawBody), 'case' => $number, 'confirm_case' => 'HP-202608-9999',
], $admin));
$check('確認画面があり、案件番号が一致しないと確定しない',
    $lockForm->status === 200 && $lockNg->status === 400
    && (string)$k->cases->find($caseId)['status'] === 'reviewed');

$noCsrf = $k->app->handle($adminPost('/admin/lock/send', ['case' => $number, 'confirm_case' => $number], $admin));
$badOrg = $k->app->handle($adminPost('/admin/lock/send', [
    'csrf_token' => $csrfOf($lockNg->rawBody), 'case' => $number, 'confirm_case' => $number,
], $admin, ['origin' => 'https://evil.example.invalid']));
$check('CSRF 欠落・不正 Origin では確定しない',
    $noCsrf->status === 403 && $badOrg->status === 403
    && (string)$k->cases->find($caseId)['status'] === 'reviewed');

$answersPreLock = $k->answers->get($caseId);
$okLock = $k->app->handle($adminPost('/admin/lock/send', [
    'csrf_token' => $csrfOf($lockNg->rawBody), 'case' => $number, 'confirm_case' => $number,
], $admin));
$locked = $k->cases->find($caseId);
$check('確定できる。status=locked・locked_at 記録・closed にはならない',
    $okLock->status === 303 && (string)$locked['status'] === 'locked'
    && $locked['locked_at'] !== null && $locked['closed_at'] === null && $locked['deleted_at'] === null);
$check('token も店舗 session もすべて失効する',
    $k->tokens->activeCount($caseId) === 0 && $k->sessions->activeCount($caseId) === 0
    && $k->app->handle($shopGet('/case', $shop2))->status === 404);
$check('回答本文は維持される', $k->answers->get($caseId) == $answersPreLock);
$check('確定後は needs_revision へ戻せず、再発行もできない',
    $k->cases->requestRevision($caseId, ['basic.legal_name'], null, $k->revisions)['ok'] === false
    && $k->tokens->reissue($caseId, CaseService::REISSUABLE)['ok'] === false);

/* ================================================================ J */

$head('J. 保持期限（4F §14 A / B）');

$d5 = $k->app->handle($adminGet('/admin/case', $admin, ['case' => $number]));
$ngDue = $k->app->handle($adminPost('/admin/retention/due', [
    'csrf_token' => $csrfOf($d5->rawBody), 'case' => $number, 'due' => '2027-02-30',
], $admin));
$check('実在しない日付は登録しない',
    str_contains((string)$ngDue->headers['Location'], 'msg=due_invalid')
    && $k->cases->find($caseId)['retention_delete_due'] === null);

$d6 = $k->app->handle($adminGet('/admin/case', $admin, ['case' => $number]));
$due = gmdate('Y-m-d', $clock->now() + (40 * 86400));
$k->app->handle($adminPost('/admin/retention/due', [
    'csrf_token' => $csrfOf($d6->rawBody), 'case' => $number, 'due' => $due,
], $admin));
$check('削除予定日を登録できる', $k->cases->find($caseId)['retention_delete_due'] === $due);

$auditDue = $k->audit->countFor($caseId, 'retention_due_set');
$d7 = $k->app->handle($adminGet('/admin/case', $admin, ['case' => $number]));
$k->app->handle($adminPost('/admin/retention/due', [
    'csrf_token' => $csrfOf($d7->rawBody), 'case' => $number, 'due' => $due,
], $admin));
$check('同じ日付の再送は冪等（監査が増えない）',
    $k->audit->countFor($caseId, 'retention_due_set') === $auditDue);

$cols = array_column($k->db->pdo()->query("PRAGMA table_info('intake_cases')")->fetchAll(), 'name');
$publishCols = array_filter($cols, static fn (string $c): bool =>
    str_contains($c, 'publish') || str_contains($c, 'approv'));
$check('公開日・公開承認の列がそもそも無い。監査にも日付を出さない',
    $publishCols === []
    && $rows($k, 'SELECT COUNT(*) FROM intake_audit_events WHERE result_code = :d', [':d' => $due]) === 0
    && !str_contains($log($baseMain), $due));

// B. 本番想定の既定状態
$check('本番想定の既定フラグは両方 false',
    $k->config->retentionActionsEnabled === false
    && $k->config->backupPolicyConfirmed === false
    && $k->config->retentionEnabled() === false);

$retList = (string)$k->app->handle($adminGet('/admin/retention', $admin))->rawBody;
$caseNow = (string)$k->app->handle($adminGet('/admin/case', $admin, ['case' => $number]))->rawBody;
$check('既定状態では purge のボタンも導線も出さない',
    !str_contains($retList, '/admin/purge') && !str_contains($caseNow, '/admin/purge')
    && !str_contains($retList, '/admin/maintenance/audit'));

$clock->advance(41 * 86400);
$relogin = $k->app->handle($adminPost('/admin/login', ['admin_id' => E2E_ADMIN_ID, 'password' => E2E_ADMIN_PW]));
$admin   = [Config::ADMIN_COOKIE_NAME => (string)$relogin->cookies[0]['value']];

$purgeForm = $k->app->handle($adminGet('/admin/purge', $admin, ['case' => $number]));
$mForm     = $k->app->handle($adminGet('/admin/maintenance', $admin));
$purgePost = $k->app->handle($adminPost('/admin/purge/send', [
    'csrf_token' => $csrfOf($mForm->rawBody), 'case' => $number, 'confirm' => 'DELETE ' . $number,
], $admin));
$auditPost = $k->app->handle($adminPost('/admin/maintenance/audit', [
    'csrf_token' => $csrfOf($mForm->rawBody),
], $admin));
$check('期限が到来していても、既定フラグでは確認画面も実行も 403',
    $purgeForm->status === 403 && $purgePost->status === 403 && $auditPost->status === 403);
$check('既定フラグのままでは1行も消えていない',
    $rows($k, 'SELECT COUNT(*) FROM intake_answers WHERE intake_case_id = :i', [':i' => $caseId]) === 1
    && $k->cases->find($caseId)['deleted_at'] === null
    && (string)$k->cases->find($caseId)['status'] === 'locked');

/* ================================================================ L */

$head('L. 保守（4F §16）');

$mClock = new E2eClock();
[$m, $baseMaint] = $makeEnv('maint', $mClock, true);

$mCase  = $m->cases->create('HP-202608-0009', '架空サロン 保守確認');
$cutoff = (int)strtotime($m->retention->auditCutoff());
$ins    = $m->db->pdo()->prepare(
    'INSERT INTO intake_audit_events (intake_case_id, event_type, result_code, ip_hmac, created_at)
     VALUES (:id, :e, :r, :ip, :at)'
);
$older = gmdate('Y-m-d\TH:i:s\Z', $cutoff - 3600);
$newer = gmdate('Y-m-d\TH:i:s\Z', $cutoff + 3600);
$ins->execute([':id' => $mCase, ':e' => 'admin_viewed', ':r' => 'ok', ':ip' => str_repeat('a', 32), ':at' => $older]);
$ins->execute([':id' => $mCase, ':e' => 'admin_viewed', ':r' => 'ok', ':ip' => str_repeat('b', 32), ':at' => $newer]);

$mLogin = $m->app->handle($adminPost('/admin/login', ['admin_id' => E2E_ADMIN_ID, 'password' => E2E_ADMIN_PW]));
$mAdmin = [Config::ADMIN_COOKIE_NAME => (string)$mLogin->cookies[0]['value']];
$mPage  = $m->app->handle($adminGet('/admin/maintenance', $mAdmin));
$mHtml  = (string)$mPage->rawBody;
$check('保守画面は件数だけを出し、HMAC化IPも監査本文も出さない',
    str_contains($mHtml, '削除対象')
    && !str_contains($mHtml, str_repeat('a', 32)) && !str_contains($mHtml, str_repeat('b', 32))
    && !str_contains($mHtml, 'admin_viewed'));

$before = $rows($m, 'SELECT COUNT(*) FROM intake_audit_events WHERE intake_case_id = :i', [':i' => $mCase]);
$m->app->handle($adminPost('/admin/maintenance/audit', ['csrf_token' => $csrfOf($mHtml)], $mAdmin));
$check('13か月より前だけを削除し、13か月以内は残す',
    $rows($m, 'SELECT COUNT(*) FROM intake_audit_events WHERE created_at = :a', [':a' => $older]) === 0
    && $rows($m, 'SELECT COUNT(*) FROM intake_audit_events WHERE created_at = :a', [':a' => $newer]) === 1
    && $before > 0);
$check('削除自体の監査も、13か月後には同じ規則で対象になる',
    $rows($m, "SELECT COUNT(*) FROM intake_audit_events WHERE event_type = 'audit_purged'") === 1
    && (static function () use ($m, $mClock): bool {
        $mClock->advance(430 * 86400);
        return $m->retention->countAuditDue() >= 1;
    })());
$mClock->advance(-430 * 86400);

// 管理 session の清掃
$stale  = $m->app->handle($adminPost('/admin/login', ['admin_id' => E2E_ADMIN_ID, 'password' => E2E_ADMIN_PW]));
$staleS = (string)$stale->cookies[0]['value'];
$m->db->pdo()->prepare('UPDATE intake_admin_sessions SET expires_at = :p WHERE session_hash = :h')
    ->execute([':p' => '2026-01-01T00:00:00Z', ':h' => hash('sha256', $staleS)]);

$mPage2 = $m->app->handle($adminGet('/admin/maintenance', $mAdmin));
$sRes   = $m->app->handle($adminPost('/admin/maintenance/sessions', ['csrf_token' => $csrfOf($mPage2->rawBody)], $mAdmin));
$check('期限切れ・失効済みだけを消し、実行者の session は残す',
    $sRes->status === 200 && $m->retention->countAdminSessionsDue() === 0
    && $m->app->handle($adminGet('/admin/', $mAdmin))->status === 200);
$check('清掃で session hash を画面にもログにも出さない',
    !str_contains((string)$sRes->rawBody, $staleS)
    && !str_contains((string)$sRes->rawBody, hash('sha256', $staleS))
    && !str_contains($log($baseMaint), hash('sha256', $staleS)));

/* ================================================================ K */

$head('K. closed（削除済み）案件の全面拒否（4F §15）');

$kClock = new E2eClock();
[$c, $baseClosed] = $makeEnv('closed', $kClock, true);

// 削除まで一気に進める
$cNumber = 'HP-202608-0007';
$cId     = $c->cases->create($cNumber, E2E_SHOP . ' ' . E2E_MARKER);
$c->cases->setDriveFolder($cId, E2E_DRIVE, $cNumber . ' 素材', E2E_EMAIL);
$cToken  = $c->tokens->issue($cId);
$cStart  = $c->app->handle($json('POST', '/session/start', ['token' => $cToken]));
$cShop   = [Config::COOKIE_NAME => (string)$cStart->cookies[0]['value']];
$c->app->handle($json('POST', '/answers/save', ['version' => 1, 'sections' => $sections], $cShop));
$c->app->handle($json('POST', '/submit', ['submission_id' => $uuid4()], $cShop));
$c->cases->adminChangeStatus($cId, 'reviewed', 'reviewed');
$setSettings($c, $cId);
$c->cases->adminLock($cId);
$c->retention->setDeleteDue($cId, '2026-01-15');
$purged = $c->retention->purgeCase($cId, $cNumber, 'DELETE ' . $cNumber);
$check('削除済み案件を用意できた（status=closed / deleted_at 記録）',
    $purged['ok'] === true
    && (string)$c->cases->find($cId)['status'] === 'closed'
    && $c->cases->find($cId)['deleted_at'] !== null);

$cLogin = $c->app->handle($adminPost('/admin/login', ['admin_id' => E2E_ADMIN_ID, 'password' => E2E_ADMIN_PW]));
$cAdmin = [Config::ADMIN_COOKIE_NAME => (string)$cLogin->cookies[0]['value']];

$check('店舗 session を開始できない（旧 token は行ごと消えている）',
    $c->app->handle($json('POST', '/session/start', ['token' => $cToken]))->status === 404
    && $rows($c, 'SELECT COUNT(*) FROM intake_tokens WHERE intake_case_id = :i', [':i' => $cId]) === 0);

$storeDenied = [$c->app->handle($shopGet('/case', $cShop))->status === 404];
foreach ([
    ['/answers/save', ['version' => 1, 'sections' => []]],
    ['/submit', ['submission_id' => $uuid4()]],
    ['/drive/confirm', ['confirmed' => true]],
] as [$p, $b]) {
    $storeDenied[] = $c->app->handle($json('POST', $p, $b, $cShop))->status === 404;
}
$check('GET /case・回答保存・提出・Drive 申告をすべて拒否', !in_array(false, $storeDenied, true));

$check('token 発行・再発行を拒否（token は1本も増えない）',
    $c->tokens->reissue($cId, CaseService::REISSUABLE)['ok'] === false
    && $c->tokens->reissue($cId, CaseService::STATUSES)['ok'] === false
    && $rows($c, 'SELECT COUNT(*) FROM intake_tokens WHERE intake_case_id = :i', [':i' => $cId]) === 0);

$statusDenied = [];
foreach (['draft', 'submitted', 'needs_revision', 'reviewed', 'locked'] as $to) {
    $r = $c->cases->adminChangeStatus($cId, $to, 'reviewed');
    $statusDenied[] = $r['ok'] === false && ($r['error'] ?? '') === 'already_deleted';
}
$check('修正依頼も status 変更も拒否（理由は already_deleted）',
    !in_array(false, $statusDenied, true)
    && $c->cases->requestRevision($cId, ['basic.legal_name'], null, $c->revisions)['ok'] === false
    && $c->cases->adminLock($cId)['ok'] === false);

$check('削除予定日の変更を拒否',
    ($c->retention->setDeleteDue($cId, '2027-01-01')['error'] ?? '') === 'already_deleted');

$expRes = $c->export->export($cId);
$expHttp = $c->app->handle($adminGet('/admin/export', $cAdmin, ['case' => $cNumber]));
$check('★書き出しは status/deleted_at ゲートで拒否する（例外ではなく deleted）',
    $expRes['ok'] === false && ($expRes['error'] ?? '') === 'deleted'
    && !array_key_exists('json', $expRes)
    && $expHttp->status === 409
    && !isset($expHttp->headers['Content-Disposition'])
    && !isset($expHttp->headers['X-Intake-Export-Sha256']));

$second = $c->retention->purgeCase($cId, $cNumber, 'DELETE ' . $cNumber);
$check('二度目の削除を拒否（deleted_at を上書きせず監査も増やさない）',
    $second['ok'] === false && ($second['error'] ?? '') === 'already_deleted'
    && $c->audit->countFor($cId, 'retention_purged') === 1);

$cDetail = (string)$c->app->handle($adminGet('/admin/case', $cAdmin, ['case' => $cNumber]))->rawBody;
$links   = 0;
foreach (['/admin/revision?', '/admin/reissue?', '/admin/lock?', '/admin/purge?', '/admin/export?',
          '/admin/status', '/admin/retention/due'] as $l) {
    if (str_contains($cDetail, $l)) {
        ++$links;
    }
}
$check('管理詳細は最小メタデータだけ。操作リンク0件',
    $links === 0 && str_contains($cDetail, $cNumber)
    && !str_contains($cDetail, E2E_MARKER) && !str_contains($cDetail, E2E_EMAIL)
    && !str_contains($cDetail, 'drive.google.com'));

/* ================================================================ M */

$head('M. 回答の正式構造（4F-R1 / SSOT v1.8 §3.0.1）');

$mkClock = new E2eClock();
[$w, $baseSchema] = $makeEnv('schema', $mkClock, false);

$wNumber = 'HP-202608-0011';
$wId     = $w->cases->create($wNumber, E2E_SHOP . ' ' . E2E_MARKER);
$wToken  = $w->tokens->issue($wId);
$wStart  = $w->app->handle($json('POST', '/session/start', ['token' => $wToken]));
$wShop   = [Config::COOKIE_NAME => (string)$wStart->cookies[0]['value']];

// 正常な11分類を保存する
$wSave = $w->app->handle($json('POST', '/answers/save', ['version' => 1, 'sections' => $sections], $wShop));
$check('正式な11分類を保存できる', $wSave->status === 200 && (int)$wSave->body['version'] === 2);

// 未知キーを混ぜて送る
$UNK   = 'E2EUNKNOWNKEY0001';
$mixed = $sections;
$mixed['basic'][$UNK]    = '不正値' . $UNK;
$mixed['menus'][0][$UNK] = '不正値' . $UNK;
$beforeSave = $w->answers->get($wId);

$wBad      = $w->app->handle($json('POST', '/answers/save', ['version' => 2, 'sections' => $mixed], $wShop));
$afterSave = $w->answers->get($wId);

$check('未知キー混入は 400・固定エラーコードで拒否される',
    $wBad->status === 400 && ($wBad->body['error'] ?? '') === 'bad_request');
$check('未知キー名も値も応答に出ない', !str_contains($wBad->json(), $UNK));
$check('正常値も含めて1バイトも保存されない（部分保存なし・version も動かない）',
    $beforeSave['version'] === $afterSave['version']
    && $beforeSave['sections'] === $afterSave['sections']);

// 直して再保存 → 復元 → 提出
$fixedSections = $sections;
$fixedSections['basic']['legal_name'] = E2E_SHOP . ' ' . E2E_MARKER . '（修正版）';
$wFix  = $w->app->handle($json('POST', '/answers/save', ['version' => 2, 'sections' => $fixedSections], $wShop));
$wCase = $w->app->handle($shopGet('/case', $wShop));
$check('直して再保存でき、復元にも反映される',
    $wFix->status === 200
    && $wCase->body['sections']['basic']['legal_name'] === E2E_SHOP . ' ' . E2E_MARKER . '（修正版）');

$wSubmit = $w->app->handle($json('POST', '/submit', ['submission_id' => $uuid4()], $wShop));
$check('提出できる', $wSubmit->status === 200 && (string)$w->cases->find($wId)['status'] === 'submitted');
$setSettings($w, $wId);

// ★4F-R1 より前に入った未知キーの再現（保存 API を通さず直接埋め込む）
$rawJson = static function (Kernel $kern, int $id, string $col): array {
    $stmt = $kern->db->pdo()->prepare('SELECT ' . $col . '_json AS j FROM intake_answers WHERE intake_case_id = :i');
    $stmt->execute([':i' => $id]);
    $decoded = json_decode((string)$stmt->fetchColumn(), true);

    return is_array($decoded) ? $decoded : [];
};
$legacyBasic       = $rawJson($w, $wId, 'basic');
$legacyBasic[$UNK] = '不正値' . $UNK;
$legacyMenus       = $rawJson($w, $wId, 'menus');
$legacyMenus[0][$UNK] = '不正値' . $UNK;
$w->db->pdo()->prepare('UPDATE intake_answers SET basic_json = :b, menus_json = :m WHERE intake_case_id = :i')
    ->execute([
        ':b' => json_encode($legacyBasic, JSON_UNESCAPED_UNICODE),
        ':m' => json_encode($legacyMenus, JSON_UNESCAPED_UNICODE),
        ':i' => $wId,
    ]);
$check('既存DBへ未知キーを直接入れた（検査の前提が成立している）',
    array_key_exists($UNK, $rawJson($w, $wId, 'basic')));

$wLogin = $w->app->handle($adminPost('/admin/login', ['admin_id' => E2E_ADMIN_ID, 'password' => E2E_ADMIN_PW]));
$wAdmin = [Config::ADMIN_COOKIE_NAME => (string)$wLogin->cookies[0]['value']];

$restore    = $w->app->handle($shopGet('/case', $wShop));
$detailW    = $w->app->handle($adminGet('/admin/case', $wAdmin, ['case' => $wNumber]));
$exportW    = $w->app->handle($adminGet('/admin/export', $wAdmin, ['case' => $wNumber]));
$exportBody = (string)$exportW->rawBody;

$check('店舗の復元に未知キーが出ない（落ちもしない）',
    $restore->status === 200 && !str_contains($restore->json(), $UNK));
$check('管理詳細に未知キーが出ない（500 にならない）',
    $detailW->status === 200 && !str_contains((string)$detailW->rawBody, $UNK));
$check('書き出しに未知キーが出ない',
    $exportW->status === 200 && !str_contains($exportBody, $UNK));
$check('正式な値は残っている（検査が空振りでない）',
    str_contains($exportBody, E2E_MARKER)
    && $restore->body['sections']['menus'][0]['name'] === 'カット');
$check('書き出しの SHA-256 が本文と一致する',
    hash('sha256', $exportBody) === ($exportW->headers['X-Intake-Export-Sha256'] ?? ''));
$check('未知キーを自動で消したり移したりしない（DBはそのまま）',
    array_key_exists($UNK, $rawJson($w, $wId, 'basic')));
$check('未知キーの名前も値もログへ出ない',
    !str_contains($log($baseSchema), $UNK) && str_contains($log($baseSchema), $wNumber));

/* ================================================================ N */

$head('N. 必須契約と Smart Labo 設定（4F-R3 / SSOT v1.9 §3.0.2 / §3.12）');

$nClock = new E2eClock();
[$n, $baseContract] = $makeEnv('contract', $nClock, false);

$nNumber = 'HP-202608-0021';
$nId     = $n->cases->create($nNumber, E2E_SHOP . ' ' . E2E_MARKER);
$nToken  = $n->tokens->issue($nId);
$nStart  = $n->app->handle($json('POST', '/session/start', ['token' => $nToken]));
$nShop   = [Config::COOKIE_NAME => (string)$nStart->cookies[0]['value']];

// 能動選択が必要な enum 7件（代表判断 Q3）
$enums = [
    'basic.address_visibility'        => 'full',
    'business_hours.irregular_notice' => 'none',
    'privacy.third_party'             => 'none',
    'privacy.marketing_use'           => 'no',
    'design.logo'                     => 'none',
    'design.emphasis'                 => 'photo',
    'web_links.map_display'           => 'show',
];

// enum を未選択にした状態で保存する
$blank = $sections;
foreach ($enums as $path => $value) {
    [$sec, $key] = explode('.', $path, 2);
    unset($blank[$sec][$key]);
}
unset($blank['contact_form']['enabled'], $blank['basic']['parking']);

$n->app->handle($json('POST', '/answers/save', ['version' => 1, 'sections' => $blank], $nShop));
$missingBefore = $n->answers->evaluate($nId)['missing'];

$check('enum 7件・二択・駐車場を未選択のままだと、9件が不足として出る',
    count(array_intersect(array_keys($enums), $missingBefore)) === 7
    && in_array('contact_form.enabled', $missingBefore, true)
    && in_array('basic.parking', $missingBefore, true));

$blocked = $n->app->handle($json('POST', '/submit', ['submission_id' => $uuid4()], $nShop));
$check('API を直接呼んでも提出できない（画面だけの検査になっていない）',
    ($blocked->body['submitted'] ?? null) === false
    && (string)$n->cases->find($nId)['status'] === 'draft');

// 語彙外は保存そのものを拒否
$badVocab = $sections;
$badVocab['basic']['address_visibility'] = 'public';
$rejected = $n->app->handle($json('POST', '/answers/save', [
    'version' => $n->answers->get($nId)['version'], 'sections' => $badVocab,
], $nShop));
$check('正式な語彙でない値は保存そのものを拒否する', $rejected->status === 400);

// 能動的に選び、二択と駐車場も答える
$answered = $sections;
foreach ($enums as $path => $value) {
    [$sec, $key] = explode('.', $path, 2);
    $answered[$sec][$key] = $value;
}
$answered['contact_form']['enabled'] = false;          // ★「設置しない」も正式な回答
$answered['basic']['parking']        = ['type' => 'none', 'note' => ''];

$nSave = $n->app->handle($json('POST', '/answers/save', [
    'version' => $n->answers->get($nId)['version'], 'sections' => $answered,
], $nShop));
$check('7件を能動選択し、false と type=none と空の note を含めて保存できる',
    $nSave->status === 200);
$check('画面の不足も API の不足も 0 件になる',
    $n->answers->evaluate($nId)['missing'] === []
    && ($n->app->handle($shopGet('/case', $nShop))->status === 200));

$nSubmit = $n->app->handle($json('POST', '/submit', ['submission_id' => $uuid4()], $nShop));
$check('提出できる', $nSubmit->status === 200
    && (string)$n->cases->find($nId)['status'] === 'submitted');

// Smart Labo 設定
$nLogin = $n->app->handle($adminPost('/admin/login', ['admin_id' => E2E_ADMIN_ID, 'password' => E2E_ADMIN_PW]));
$nAdmin = [Config::ADMIN_COOKIE_NAME => (string)$nLogin->cookies[0]['value']];

$detailN = $n->app->handle($adminGet('/admin/case', $nAdmin, ['case' => $nNumber]));
$check('管理詳細に「制作設定 5 件未設定」が出る',
    str_contains((string)$detailN->rawBody, '5 件未設定')
    && count($n->answers->missingAdminSettings($nId)) === 5);
$check('制作設定が無いうちは書き出せない（店舗の提出は済んでいる）',
    ($n->export->export($nId)['error'] ?? '') === 'admin_settings_missing');

$n->cases->adminChangeStatus($nId, 'reviewed', 'reviewed');
$formN = $n->app->handle($adminGet('/admin/settings', $nAdmin, ['case' => $nNumber]));
$saveN = $n->app->handle($adminPost('/admin/settings/save', [
    'csrf_token'        => $csrfOf($formN->rawBody),
    'case'              => $nNumber,
    'confirm_case'      => $nNumber,
    'salon_booking_url' => '',
    'destination'       => '架空の送信先 ' . E2E_MARKER,
    'storage'           => '架空の保管方法',
    'external_services' => '',
    'consent_checkbox'  => 'true',
], $nAdmin));
$check('管理者が制作設定5件を入れられる',
    $saveN->status === 303 && $n->answers->missingAdminSettings($nId) === []);

$caseN = $n->app->handle($shopGet('/case', $nShop));
$adminKeysLeaked = [];
foreach (['salon_booking_url', 'destination', 'storage', 'external_services', 'consent_checkbox'] as $key) {
    foreach (['web_links', 'privacy'] as $sec) {
        if (array_key_exists($key, $caseN->body['sections'][$sec] ?? [])) {
            $adminKeysLeaked[] = $sec . '.' . $key;
        }
    }
}
$check('店舗の復元に制作設定が出ない' . ($adminKeysLeaked === [] ? '' : '（出た: ' . implode(',', $adminKeysLeaked) . '）'),
    $adminKeysLeaked === [] && $caseN->body['sections']['privacy']['purpose'] === '架空の目的');

// ★書き込みの検査は**入力中（draft）**の案件で行う。
//   提出済み・確認済みの案件はそもそも店舗が保存できない（状態の検査が先に効く）ため、
//   「管理設定を書けないこと」を確かめたことにならない。
$wNumber2 = 'HP-202608-0022';
$wId2     = $n->cases->create($wNumber2, E2E_SHOP);
$wToken2  = $n->tokens->issue($wId2);
$wShop2   = [Config::COOKIE_NAME => (string)$n->app->handle(
    $json('POST', '/session/start', ['token' => $wToken2])
)->cookies[0]['value']];
$n->app->handle($json('POST', '/answers/save', ['version' => 1, 'sections' => $answered], $wShop2));
$setSettings($n, $wId2);

$beforeSettings = $n->answers->adminSettings($wId2);
$writeAttempt   = $n->app->handle($json('POST', '/answers/save', [
    'version'  => $n->answers->get($wId2)['version'],
    'sections' => ['privacy' => ['destination' => '店舗が書き換えた'] + $answered['privacy']],
], $wShop2));
$check('店舗の保存では制作設定を変更できない（入力中の案件で確認）',
    $writeAttempt->status === 400 && $n->answers->adminSettings($wId2) == $beforeSettings);

$storeResave = $n->app->handle($json('POST', '/answers/save', [
    'version' => $n->answers->get($wId2)['version'], 'sections' => ['privacy' => $answered['privacy']],
], $wShop2));
$check('店舗が分類をまるごと保存し直しても制作設定が消えない',
    $storeResave->status === 200 && $n->answers->missingAdminSettings($wId2) === []);

$detailN2 = $n->app->handle($adminGet('/admin/case', $nAdmin, ['case' => $nNumber]));
$exportN  = $n->app->handle($adminGet('/admin/export', $nAdmin, ['case' => $nNumber]));
$bodyN    = (string)$exportN->rawBody;
$decodedN = json_decode($bodyN, true);

$check('管理詳細の不足が 0 件になる', str_contains((string)$detailN2->rawBody, '設定済み'));
$check('書き出せる。SHA-256 が本文と一致する',
    $exportN->status === 200
    && hash('sha256', $bodyN) === ($exportN->headers['X-Intake-Export-Sha256'] ?? ''));
$check('書き出しに promotion.industry が無い', !str_contains($bodyN, 'industry'));

$outside = [];
foreach (($decodedN['answers'] ?? []) as $sec => $value) {
    foreach (is_array($value) ? $value : [] as $key => $child) {
        $keys = is_int($key) ? array_keys(is_array($child) ? $child : []) : [$key];
        foreach ($keys as $one) {
            $full = $sec . '.' . $one;
            $ok   = in_array($full, \SmartLabo\Intake\AnswerSchema::PATHS, true);
            foreach (\SmartLabo\Intake\AnswerSchema::PATHS as $candidate) {
                if (str_starts_with($candidate, $full . '.')) {
                    $ok = true;
                }
            }
            if (!$ok) {
                $outside[] = $full;
            }
        }
    }
}
$check('書き出しに134パスの外が1つも無い' . ($outside === [] ? '' : '（外: ' . implode(',', array_unique($outside)) . '）'),
    $outside === []);

$check('制作設定の値をログにも監査にも出さない',
    !str_contains($log($baseContract), '架空の送信先')
    && !str_contains($log($baseContract), '架空の保管方法')
    && str_contains($log($baseContract), 'admin_settings_saved')
    && $n->audit->countFor($nId, 'admin_settings_saved') === 1
    && (static function () use ($n): bool {
        $stmt = $n->db->pdo()->prepare('SELECT COUNT(*) FROM intake_audit_events WHERE result_code LIKE :m');
        $stmt->execute([':m' => '%架空%']);

        return (int)$stmt->fetchColumn() === 0;
    })());

/* ================================================================ 後始末 */

$head('後始末');

$dbFiles = [];
foreach ([$baseMain, $baseMaint, $baseClosed, $baseSchema, $baseContract] as $b) {
    $dbFiles[$b] = (string)file_get_contents($b . '/intake.sqlite');
}
$check('削除済みDBの生ファイルに架空 PII が残っていない',
    !str_contains($dbFiles[$baseClosed], E2E_MARKER)
    && !str_contains($dbFiles[$baseClosed], E2E_EMAIL)
    && !str_contains($dbFiles[$baseClosed], E2E_TEL)
    && str_contains($dbFiles[$baseClosed], $cNumber));

$leak = [];
foreach ([$baseMain, $baseMaint, $baseClosed, $baseSchema, $baseContract] as $b) {
    foreach ([E2E_MARKER, E2E_EMAIL, E2E_TEL, E2E_REVISION, 'drive.google.com',
              $token, $token2, $sid, $cToken, E2E_ADMIN_PW] as $needle) {
        if ($needle !== '' && str_contains($log($b), $needle)) {
            $leak[] = basename($b) . ':' . substr($needle, 0, 12);
        }
    }
}
$check('5つのログに PII・token・session・パスワードが1つも出ていない'
    . ($leak === [] ? '' : '（残: ' . implode(',', $leak) . '）'), $leak === []);
$check('ログは空ではない（検査が空振りしていない）',
    str_contains($log($baseMain), 'export_generated') && str_contains($log($baseMain), $number));

$k->db->close();
$m->db->close();
$c->db->close();
$w->db->close();
$n->db->close();
unset($k, $m, $c, $w, $n, $dbFiles, $ins, $stmt);
gc_collect_cycles();

$left = [];
foreach ($roots as $b) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($b, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $path) {
        $path->isDir() ? @rmdir($path->getPathname()) : @unlink($path->getPathname());
    }
    @rmdir($b);
    if (is_dir($b)) {
        $left[] = $b;
    }
}
$check('使い捨てDBを5つとも削除した' . ($left === [] ? '' : '（残: ' . implode(',', $left) . '）'), $left === []);

echo "\n" . str_repeat('=', 66) . "\n";
printf("  %d 項目 / NG %d 件\n", $step, count($bad));
foreach ($bad as $b) {
    echo '  - ' . $b . "\n";
}

exit($bad === [] ? 0 : 1);
