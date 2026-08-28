<?php
/**
 * 配置境界・提出通知・preflight 分離の通し確認
 * （HP-ONBOARDING-4H-R0 / SSOT v1.12 §9.10 / §9.11 / §10.11）
 *
 *   php -c intake-api/dev/php.ini intake-api/dev/boundary-walkthrough.php
 *
 * ★**使い捨ての領域を毎回作り直して**確認する。実行のたびに新しいディレクトリを作り、
 *   終わったら消す。**本番・既存DB（dev/.preview/ を含む）へは一切接続しない。**
 * ★架空の店舗・架空のメール（example.invalid）だけを使う。
 * ★**実メールを1通も送らない。** 通知は記録するだけの偽物を使う。
 * ★XServer へ接続しない。本番パスを作らない。cron を作らない。
 *
 * 何を確かめるか（22段）:
 *   XServer 相当の分離構成 → APP_ROOT の解決 → 公開領域の拒否 →
 *   fail closed → Config の分離 → 絶対パス非出力 → 誤配置防御 →
 *   提出で1通 → 再送で増えない → PII 非混入 → 失敗しても提出成功 →
 *   preflight の分離 → dry-run → 実削除 → 正式側の無傷 → 後始末
 */
declare(strict_types=1);

require_once __DIR__ . '/../src/Autoload.php';

use SmartLabo\Intake\AppRoot;
use SmartLabo\Intake\Config;
use SmartLabo\Intake\ConfigException;
use SmartLabo\Intake\Http\Request;
use SmartLabo\Intake\Kernel;
use SmartLabo\Intake\Notify\Notifier;
use SmartLabo\Intake\Notify\SubmissionNotice;
use SmartLabo\Intake\Preflight\PreflightArea;
use SmartLabo\Intake\Support\PathPolicy;

/* ---------------------------------------------------------------- 準備 */

/** 削除・混入を確かめるための目印（架空・他に出てこない文字列） */
const BW_MARKER = 'BOUNDARYWALKMARKER0001';
const BW_EMAIL  = 'materials@example.invalid';
const BW_DRIVE  = 'https://drive.google.com/drive/folders/FAKE-BOUNDARY-WALK-000';
const BW_NUMBER = 'HP-202608-0910';
const BW_ORIGIN = 'http://127.0.0.1:8788';

/** 実メールを送らない通知（記録するだけ） */
final class WalkNotifier implements Notifier
{
    /** @var list<string> */
    public array $sent = [];
    public bool $enabledFlag = true;
    public bool $failNext = false;

    public function enabled(): bool
    {
        return $this->enabledFlag;
    }

    public function notifySubmitted(SubmissionNotice $notice): bool
    {
        if ($this->failNext) {
            return false;
        }
        $this->sent[] = $notice->subject() . "\n" . $notice->body();

        return true;
    }
}

$step = 0;
$bad  = 0;
$check = static function (string $label, bool $ok) use (&$step, &$bad): void {
    ++$step;
    if (!$ok) {
        ++$bad;
    }
    printf("  %2d. [%s] %s\n", $step, $ok ? 'OK' : 'NG', $label);
};

$base = __DIR__ . '/.boundary-walkthrough-' . getmypid();
$cleanup = static function () use ($base): void {
    if (!is_dir($base)) {
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $path) {
        $path->isDir() ? @rmdir($path->getPathname()) : @unlink($path->getPathname());
    }
    @rmdir($base);
};
$cleanup();

// XServer 相当の分離構成をローカルに作る
$docroot   = $base . '/public_html/intake.smartlaboworks.com';
$appRoot   = $base . '/private/hp-intake';
$preflight = $appRoot . '/preflight';
mkdir($docroot, 0700, true);
mkdir($appRoot . '/src', 0700, true);
mkdir($appRoot . '/private/logs', 0700, true);
mkdir($preflight . '/logs', 0700, true);
mkdir($preflight . '/backups', 0700, true);
// ★APP_ROOT の判定は `src/Autoload.php` の実在を見る。本物を複製せず空で足りる
file_put_contents($appRoot . '/src/Autoload.php', "<?php\n");

echo "HP Intake — 配置境界・通知・preflight の通し確認（架空データ・使い捨て領域）\n";
echo str_repeat('-', 68) . "\n";
echo "  docroot   : " . $docroot . "\n";
echo "  APP_ROOT  : " . $appRoot . "\n";
echo "  preflight : " . $preflight . "\n\n";

/* -------------------------------------------------- 1. 分離構成 */

$check('docroot の親が public_html である（XServer 相当の配置を再現した）',
    is_dir($docroot) && basename(dirname($docroot)) === 'public_html');

/* -------------------------------------------------- 2. APP_ROOT の解決 */

$found = AppRoot::discoverFrom($docroot);
$check('docroot の祖先から APP_ROOT を見つけた（兄弟でなくても解決できる）',
    $found === PathPolicy::normalize($appRoot));

/* -------------------------------------------------- 3. 公開領域の拒否 */

$check('public_html の中を APP_ROOT にしない',
    AppRoot::check($base . '/public_html')['error'] === 'public_area');

/* -------------------------------------------------- 4. 不正値の拒否 */

$rejected = [];
foreach ([
    ''                        => 'not_configured',
    'hp-intake'               => 'relative',
    '/home/x/app/../etc'      => 'traversal',
    '/home/someone'           => 'home_root',
    '/app'                    => 'too_shallow',
    $base . '/private'        => 'no_src',
] as $value => $expected) {
    $actual = AppRoot::check($value === '' ? '' : (string)$value)['error'] ?? '';
    if ($actual !== $expected) {
        $rejected[] = $value . '=' . $actual;
    }
}
$check('未設定・相対・traversal・ホーム直下・ルート直下・src 不在を拒否した'
    . ($rejected === [] ? '' : '（差分: ' . implode(',', $rejected) . '）'), $rejected === []);

/* -------------------------------------------------- 5. fail closed */

$failedClosed = false;
try {
    Config::load([
        'app_root'    => $base . '/public_html',
        'ip_hmac_key' => 'boundary-walkthrough-ip-hmac-key-0123456789',
        'enc_key'     => 'boundary-walkthrough-enc-key-0123456789ab',
    ]);
} catch (ConfigException $e) {
    $failedClosed = true;
}
$check('不正な APP_ROOT では既定へ落ちずに起動を止めた（fail closed）', $failedClosed);

/* -------------------------------------------------- 6. Config の分離 */

$notifier = new WalkNotifier();
$config   = Config::load([
    'app_root'        => $appRoot,
    'ip_hmac_key'     => 'boundary-walkthrough-ip-hmac-key-0123456789',
    'enc_key'         => 'boundary-walkthrough-enc-key-0123456789ab',
    'allowed_origins' => [BW_ORIGIN],
    'require_https'   => false,
    'log_path'        => $appRoot . '/private/logs/intake.log',
    'preflight_root'  => $preflight,
    'notification_recipient' => 'ops@example.invalid',
    'notification_from'      => 'no-reply@example.invalid',
]);
$check('Config が APP_ROOT / src / private / データを分けて持った',
    $config->appRoot === PathPolicy::normalize($appRoot)
    && $config->srcRoot === PathPolicy::normalize($appRoot) . '/src'
    && $config->privateRoot === PathPolicy::normalize($appRoot) . '/private'
    && $config->dbPath === PathPolicy::normalize($appRoot) . '/private/intake.sqlite'
    && $config->preflightRoot === PathPolicy::normalize($preflight));

$kernel = new Kernel($config, null, $notifier);

/* -------------------------------------------------- 7. 誤配置防御 */

$guards = [];
foreach ([__DIR__ . '/../src/.htaccess', __DIR__ . '/../bin/.htaccess'] as $path) {
    if (!is_file($path) || !str_contains((string)file_get_contents($path), 'Require all denied')) {
        $guards[] = basename(dirname($path));
    }
}
$check('src/ と bin/ に Require all denied がある（誤配置時の二重防御）'
    . ($guards === [] ? '' : '（欠け: ' . implode(',', $guards) . '）'), $guards === []);

/* -------------------------------------------------- 8. CLI は Web 不可 */

$cliGuarded = true;
foreach ([__DIR__ . '/../bin/intake-backup.php', __DIR__ . '/../bin/intake-preflight.php'] as $path) {
    if (!str_contains((string)file_get_contents($path), "PHP_SAPI !== 'cli'")) {
        $cliGuarded = false;
    }
}
$check('管理CLI は Web から実行できない（PHP_SAPI が cli のときだけ動く）', $cliGuarded);

/* -------------------------------------------------- 9. 架空案件 */

$post = static function (string $path, array $body, array $cookies = []): Request {
    return new Request(
        method: 'POST',
        path: $path,
        headers: ['Content-Type' => 'application/json', 'Origin' => BW_ORIGIN],
        body: (string)json_encode($body),
        cookies: $cookies,
        isHttps: false,
        clientIp: '127.0.0.1',
    );
};

$caseId = $kernel->cases->create(BW_NUMBER, '架空サロン ' . BW_MARKER);
$kernel->cases->setDriveFolder($caseId, BW_DRIVE, BW_NUMBER . ' 素材', BW_EMAIL);
$token  = $kernel->tokens->issue($caseId);
$start  = $kernel->app->handle($post('/session/start', ['token' => $token]));
$cookie = [Config::COOKIE_NAME => (string)$start->cookies[0]['value']];
$check('架空の案件を作り、店舗 session を発行した', $start->status === 200);

/* -------------------------------------------------- 10. 保存では送らない */

$sections = require __DIR__ . '/walkthrough-answers.php';
$save     = $kernel->app->handle($post('/answers/save', ['version' => 1, 'sections' => $sections], $cookie));
$check('保存だけでは通知を送らない', $save->status === 200 && count($notifier->sent) === 0);

/* -------------------------------------------------- 11. 提出で1通 */

$submissionId = (function (): string {
    $b    = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
})();
$submit = $kernel->app->handle($post('/submit', ['submission_id' => $submissionId], $cookie));
$check('初回の提出成功で通知が1通だけ出た',
    $submit->status === 200 && count($notifier->sent) === 1
    && $kernel->audit->countFor($caseId, 'submission_notification_sent') === 1);

/* -------------------------------------------------- 12. 再送で増えない */

$again = $kernel->app->handle($post('/submit', ['submission_id' => $submissionId], $cookie));
$dup   = $kernel->app->handle($post('/submit', ['submission_id' => (function (): string {
    $b    = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
})()], $cookie));
$check('同一 submission_id の再送・already_submitted で通知が増えない',
    $again->status === 200 && $dup->status === 409 && count($notifier->sent) === 1);

/* -------------------------------------------------- 13. 通知の中身 */

$dump    = implode("\n", $notifier->sent);
$leaked  = [];
foreach ([BW_MARKER, '架空サロン', BW_EMAIL, 'drive.google.com', $token, $submissionId] as $needle) {
    if (str_contains($dump, $needle)) {
        $leaked[] = $needle;
    }
}
$check('通知に案件番号・イベント・日時だけが載り、PII・token・submission_id が無い'
    . ($leaked === [] ? '' : '（漏れ: ' . implode(',', $leaked) . '）'),
    $leaked === []
    && str_contains($dump, BW_NUMBER)
    && str_contains($dump, 'submitted')
    && str_contains($dump, 'UTC'));

/* -------------------------------------------------- 14. 監査の中身 */

$auditDump = (string)json_encode(
    $kernel->db->pdo()->query('SELECT * FROM intake_audit_events')->fetchAll(),
    JSON_UNESCAPED_UNICODE
);
$check('監査に宛先・件名・本文・submission_id が入っていない',
    !str_contains($auditDump, $submissionId)
    && !str_contains($auditDump, 'example.invalid')
    && !str_contains($auditDump, BW_MARKER)
    && str_contains($auditDump, 'submission_notification_sent'));

/* -------------------------------------------------- 15. 送信失敗でも提出成功 */

$notifier2 = new WalkNotifier();
$notifier2->failNext = true;
$kernel2   = new Kernel($config, null, $notifier2);
$caseId2   = $kernel2->cases->create('HP-202608-0911', '架空サロン2 ' . BW_MARKER);
$token2    = $kernel2->tokens->issue($caseId2);
$cookie2   = [Config::COOKIE_NAME => (string)$kernel2->app->handle($post('/session/start', ['token' => $token2]))->cookies[0]['value']];
$kernel2->app->handle($post('/answers/save', ['version' => 1, 'sections' => $sections], $cookie2));
$submit2   = $kernel2->app->handle($post('/submit', ['submission_id' => (function (): string {
    $b    = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
})()], $cookie2));
$check('通知の送信に失敗しても提出は成功のまま（状態も履歴も巻き戻らない）',
    $submit2->status === 200
    && ($submit2->body['submitted'] ?? null) === true
    && (string)$kernel2->cases->find($caseId2)['status'] === 'submitted'
    && $kernel2->answers->historyCount($caseId2) === 1
    && $kernel2->audit->countFor($caseId2, 'submission_notification_failed') === 1
    && $kernel2->audit->countFor($caseId2, 'submission_notification_sent') === 0);

/* -------------------------------------------------- 16. 応答に絶対パスなし */

$leakedPath = [];
foreach ([$start->json(), $save->json(), $submit->json(), $submit2->json()] as $body) {
    foreach ([$config->appRoot, $config->privateRoot, $config->dbPath] as $root) {
        if (str_contains($body, $root)) {
            $leakedPath[] = 'response';
        }
    }
}
$logText = is_file((string)$config->logPath) ? (string)file_get_contents((string)$config->logPath) : '';
foreach ([$config->appRoot, $config->privateRoot, $config->dbPath] as $root) {
    if ($logText !== '' && str_contains($logText, $root)) {
        $leakedPath[] = 'log';
    }
}
$check('応答にもログにも絶対パスが出ていない'
    . ($leakedPath === [] ? '' : '（漏れ: ' . implode(',', array_unique($leakedPath)) . '）'),
    $leakedPath === []);

/* -------------------------------------------------- 17. preflight の分離 */

$area = new PreflightArea($config->preflightRoot, $config->privateRoot, $config->dbPath);
file_put_contents($preflight . '/intake.sqlite', 'preflight ' . BW_MARKER);
file_put_contents($preflight . '/intake-config.php', "<?php\nreturn [];\n");
file_put_contents($preflight . '/logs/intake.log', 'preflight ' . BW_MARKER);
file_put_contents($preflight . '/backups/intake-20260828-000000-aaaaaaaa.sqlite', 'preflight ' . BW_MARKER);

$prodDbBytes = (string)file_get_contents($config->dbPath);
$check('preflight は正式DBと別実体で、正式DBに架空の目印が入っていない',
    $area->check()['ok'] === true
    && realpath($preflight . '/intake.sqlite') !== realpath($config->dbPath)
    && !str_contains($prodDbBytes, 'preflight ' . BW_MARKER));

/* -------------------------------------------------- 18. 正式領域は拒否 */

$overlapRejected = [];
foreach ([
    $config->privateRoot            => 'is_production_root',
    $config->privateRoot . '/logs'  => 'inside_production_root',
    PathPolicy::normalize($appRoot) => 'contains_production_root',
] as $root => $expected) {
    $probe  = new PreflightArea($root, $config->privateRoot, $config->dbPath);
    $actual = $probe->check()['error'] ?? '';
    if ($actual !== $expected) {
        $overlapRejected[] = $root . '=' . $actual;
    }
}
$check('正式領域と重なる指定を preflight として受け付けない'
    . ($overlapRejected === [] ? '' : '（差分: ' . implode(',', $overlapRejected) . '）'),
    $overlapRejected === []);

/* -------------------------------------------------- 19. dry-run */

$dry = $area->remove();
$check('preflight の削除は既定が dry-run（1件も消さない）',
    $dry['ok'] === true && $dry['dry_run'] === true && $dry['removed'] === 0
    && $dry['remaining'] >= 4 && is_file($preflight . '/intake.sqlite'));

/* -------------------------------------------------- 20. 確認なしで0件 */

$noConfirm = $area->remove(true, 'delete preflight area');
$check('確認文字列が合わなければ1件も消さない',
    ($noConfirm['error'] ?? '') === 'confirm_mismatch'
    && $noConfirm['removed'] === 0
    && is_file($preflight . '/intake.sqlite'));

/* -------------------------------------------------- 21. 実削除 */

$removed = $area->remove(true, PreflightArea::CONFIRM_REMOVE);
$check('preflight 領域を削除し、残存0になった',
    $removed['ok'] === true && $removed['remaining'] === 0 && !is_dir($preflight));

/* -------------------------------------------------- 22. 正式側は無傷 */

$check('正式DB・正式領域・ログは1バイトも触れていない',
    is_file($config->dbPath)
    && (string)file_get_contents($config->dbPath) === $prodDbBytes
    && is_dir($config->privateRoot)
    && is_dir($config->privateRoot . '/logs'));

/* -------------------------------------------------- 後始末 */

echo "\n";
echo str_repeat('-', 68) . "\n";
printf("  %d 段 / NG %d 件\n", $step, $bad);
printf("  実メール送信 0 通（通知は記録するだけの偽物を使用）\n");

// ★PDOStatement が生きている間は接続が解放されない
//   （Windows ではファイルを消せない）。参照を手放してから消す。
$kernel->db->close();
$kernel2->db->close();
unset($kernel, $kernel2, $area, $config, $start, $save, $submit, $submit2, $again, $dup);
gc_collect_cycles();
$cleanup();
echo is_dir($base)
    ? "  ★使い捨て領域が残りました: " . $base . "\n"
    : "  使い捨て領域を削除しました。\n";

exit($bad === 0 ? 0 : 1);
