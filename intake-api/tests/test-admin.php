<?php
/**
 * 内部確認画面・Drive完了申告・検証済み書き出し（SSOT v1.4 §2.7 / §10.8 / §11.3）
 * HP-ONBOARDING-4D
 */
declare(strict_types=1);

use SmartLabo\Intake\Config;
use SmartLabo\Intake\Http\Request;
use SmartLabo\Intake\Migrator;
use SmartLabo\Intake\Service\ExportService;

/** テスト専用のダミー資格情報（★本番の値ではない） */
const TEST_ADMIN_ID       = 'preview-admin';
const TEST_ADMIN_PASSWORD = 'preview-only-password-0123456789';

function adminHash(string $password = TEST_ADMIN_PASSWORD): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

/** 管理画面を有効にした Kernel */
function adminKernel(?TestClock $clock = null, array $overrides = []): object
{
    return makeKernel($clock, array_merge([
        'admin_id'            => TEST_ADMIN_ID,
        'admin_password_hash' => adminHash(),
    ], $overrides));
}

/** 管理画面への form POST */
function adminPost(string $path, array $fields, array $opts = []): Request
{
    $headers = [
        'Content-Type' => $opts['content_type'] ?? 'application/x-www-form-urlencoded',
        'Origin'       => $opts['origin'] ?? TEST_ORIGIN,
    ];
    if (($opts['no_origin'] ?? false) === true) {
        unset($headers['Origin']);
    }

    return new Request(
        method: $opts['method'] ?? 'POST',
        path: $path,
        headers: $headers,
        body: http_build_query($fields),
        cookies: $opts['cookies'] ?? [],
        isHttps: $opts['https'] ?? true,
        clientIp: $opts['ip'] ?? '203.0.113.10',
        query: $opts['query'] ?? [],
    );
}

/** 管理画面への GET */
function adminGet(string $path, array $opts = []): Request
{
    return new Request(
        method: $opts['method'] ?? 'GET',
        path: $path,
        headers: ['Sec-Fetch-Site' => 'same-origin', 'Origin' => $opts['origin'] ?? TEST_ORIGIN],
        body: '',
        cookies: $opts['cookies'] ?? [],
        isHttps: $opts['https'] ?? true,
        clientIp: $opts['ip'] ?? '203.0.113.10',
        query: $opts['query'] ?? [],
    );
}

/** ログインして Cookie と CSRF を得る */
function loginAdmin(object $k, array $opts = []): array
{
    $res = $k->app->handle(adminPost('/admin/login', [
        'admin_id' => $opts['id'] ?? TEST_ADMIN_ID,
        'password' => $opts['password'] ?? TEST_ADMIN_PASSWORD,
    ], $opts));

    if ($res->cookies === []) {
        return ['res' => $res, 'cookie' => null, 'csrf' => null];
    }
    $secret  = (string)$res->cookies[0]['value'];
    $cookies = [Config::ADMIN_COOKIE_NAME => $secret];

    // 一覧を開くと CSRF が発行される
    $list = $k->app->handle(adminGet('/admin/', ['cookies' => $cookies]));
    preg_match('/name="csrf_token" value="([A-Za-z0-9_-]{43})"/', (string)$list->rawBody, $m);

    return ['res' => $res, 'cookie' => $cookies, 'csrf' => $m[1] ?? null, 'list' => $list];
}

/** 提出済みの案件を1件つくる */
function submittedCase(object $k, string $caseNumber): array
{
    $caseId  = $k->cases->create($caseNumber, '架空サロン ハルカゼ');
    $token   = $k->tokens->issue($caseId);
    $secret  = $k->app->handle(jsonPost('/session/start', ['token' => $token]))->cookies[0]['value'];
    $cookies = ['cookies' => [Config::COOKIE_NAME => $secret]];

    $k->app->handle(jsonPost('/answers/save', ['version' => 1, 'sections' => completeSections()], $cookies));
    $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies));

    // ★4F-R3: 書き出しには Smart Labo の制作設定も要る（SSOT v1.9 §3.12）。
    //   ゲートそのものを見るテストは、別途 makeKernel() から組み立てている。
    setAdminSettings($k, $caseId);

    return [$caseId, $cookies];
}

/* ==================================================== Drive 完了申告 */

test('drive: 完了申告を受け付け、時刻と監査を1件記録する', function (): void {
    [$k, $caseId, $secret] = withSession('HP-2026-0600');
    $cookies = ['cookies' => [Config::COOKIE_NAME => $secret]];

    assertSame(null, $k->cases->find($caseId)['drive_upload_confirmed_at'], '最初から申告済みになっている');

    $res = $k->app->handle(jsonPost('/drive/confirm', ['confirmed' => true], $cookies));

    assertSame(200, $res->status);
    assertSame(true, $res->body['confirmed']);
    assertSame(true, $res->body['newly_recorded']);
    assertTrue($k->cases->find($caseId)['drive_upload_confirmed_at'] !== null, '時刻が記録されていない');
    assertSame(1, $k->audit->countFor($caseId, 'drive_upload_confirmed'), '監査が1件でない');
});

test('drive: 同じ申告の再送は冪等（時刻も監査も増やさない）', function (): void {
    [$k, $caseId, $secret] = withSession('HP-2026-0601');
    $cookies = ['cookies' => [Config::COOKIE_NAME => $secret]];

    $k->app->handle(jsonPost('/drive/confirm', ['confirmed' => true], $cookies));
    $at = (string)$k->cases->find($caseId)['drive_upload_confirmed_at'];

    for ($i = 0; $i < 3; ++$i) {
        $res = $k->app->handle(jsonPost('/drive/confirm', ['confirmed' => true], $cookies));
        assertSame(200, $res->status, ($i + 2) . '回目が 200 でない');
        assertSame(true, $res->body['confirmed'], '再送が成功扱いでない');
        assertSame(false, $res->body['newly_recorded'], '再送で新規記録あつかいになっている');
    }

    assertSame($at, (string)$k->cases->find($caseId)['drive_upload_confirmed_at'], '時刻が上書きされている');
    assertSame(1, $k->audit->countFor($caseId, 'drive_upload_confirmed'), '監査が増えている');
});

test('drive: confirmed=true 以外は受け付けない（取消の経路を作らない）', function (): void {
    [$k, $caseId, $secret] = withSession('HP-2026-0602');
    $cookies = ['cookies' => [Config::COOKIE_NAME => $secret]];

    // ★レート制限（10分5回）は値の検証より先に効く。
    //   検証そのものを確かめたいので、要求ごとに別の送信元にしてバケットを分ける
    $ip = 0;
    foreach ([false, null, 'true', 1, [], 'yes'] as $value) {
        $opts = $cookies + ['ip' => '198.51.100.' . ++$ip];
        $res  = $k->app->handle(jsonPost('/drive/confirm', ['confirmed' => $value], $opts));
        assertSame(400, $res->status, '不正な値が通る: ' . json_encode($value));
    }
    $res = $k->app->handle(jsonPost('/drive/confirm', [], $cookies + ['ip' => '198.51.100.' . ++$ip]));
    assertSame(400, $res->status, '欠落が通る');

    assertSame(null, $k->cases->find($caseId)['drive_upload_confirmed_at'], '記録されてしまっている');
    assertSame(0, $k->audit->countFor($caseId, 'drive_upload_confirmed'));
});

test('drive: 未認証の申告は拒否する', function (): void {
    $k      = adminKernel();
    $caseId = $k->cases->create('HP-2026-0603', '架空サロン');

    $res = $k->app->handle(jsonPost('/drive/confirm', ['confirmed' => true]));
    assertSame(404, $res->status, '未認証で通ってしまう');
    assertSame('unavailable', $res->body['error']);
    assertSame(null, $k->cases->find($caseId)['drive_upload_confirmed_at']);
});

test('drive: 提出後は申告できない', function (): void {
    $k = adminKernel();
    [$caseId, $cookies] = submittedCase($k, 'HP-2026-0604');

    $res = $k->app->handle(jsonPost('/drive/confirm', ['confirmed' => true], $cookies));
    assertSame(409, $res->status, '提出後に申告できてしまう');
    assertSame('not_editable', $res->body['error']);
    assertTrue($caseId > 0);
});

test('drive: Origin 不一致・GET は拒否する', function (): void {
    [$k, , $secret] = withSession('HP-2026-0605');
    $cookies = ['cookies' => [Config::COOKIE_NAME => $secret]];

    $bad = $k->app->handle(jsonPost('/drive/confirm', ['confirmed' => true],
        $cookies + ['origin' => 'https://evil.example']));
    assertSame(403, $bad->status, '他オリジンから通る');

    $get = $k->app->handle(jsonGet('/drive/confirm', $cookies));
    assertSame(405, $get->status, 'GET で状態を変えられる');
});

/* ==================================================== 管理: fail closed */

test('admin: 資格情報が未設定なら管理画面を動かさない', function (): void {
    $k = makeKernel(); // admin_id / admin_password_hash を渡さない

    assertTrue(!$k->config->adminEnabled(), '未設定なのに有効になっている');

    foreach (['/admin/', '/admin/login', '/admin/case', '/admin/export'] as $path) {
        $res = $k->app->handle(adminGet($path));
        assertSame(404, $res->status, $path . ' が未設定でも応答する');
    }

    $post = $k->app->handle(adminPost('/admin/login', ['admin_id' => 'x', 'password' => 'y']));
    assertSame(404, $post->status, '未設定でもログインを試せてしまう');
});

test('admin: ID だけ・hash だけの設定でも動かさない', function (): void {
    $idOnly = makeKernel(null, ['admin_id' => TEST_ADMIN_ID]);
    assertTrue(!$idOnly->config->adminEnabled(), 'hash 無しで有効になっている');

    $hashOnly = makeKernel(null, ['admin_password_hash' => adminHash()]);
    assertTrue(!$hashOnly->config->adminEnabled(), 'ID 無しで有効になっている');
});

test('admin: 平文パスワードを設定しても hash として受け付けない', function (): void {
    $k = makeKernel(null, [
        'admin_id'            => TEST_ADMIN_ID,
        'admin_password_hash' => TEST_ADMIN_PASSWORD, // ★平文を誤って置いた場合
    ]);

    assertSame(null, $k->config->adminPasswordHash, '平文が hash として通ってしまう');
    assertTrue(!$k->config->adminEnabled(), '平文設定で管理画面が動いてしまう');
});

test('admin: 設定に持つのは hash だけで、平文を復元できない', function (): void {
    $k = adminKernel();

    $hash = (string)$k->config->adminPasswordHash;
    assertTrue(!str_contains($hash, TEST_ADMIN_PASSWORD), '設定値に平文が含まれている');
    assertTrue(password_verify(TEST_ADMIN_PASSWORD, $hash), 'hash が検証できない');

    $info = password_get_info($hash);
    assertTrue(($info['algo'] ?? null) !== null, '既知のアルゴリズムでない');
});

/* ==================================================== 管理: ログイン */

test('admin: 正しい資格情報でログインできる', function (): void {
    $k     = adminKernel();
    $login = loginAdmin($k);

    assertSame(303, $login['res']->status, 'ログイン後に遷移していない');
    assertSame('/admin/', $login['res']->headers['Location'] ?? '');
    assertTrue($login['cookie'] !== null, 'Cookie が発行されていない');
    assertSame(1, $k->adminAuth->activeCount(), 'session が1件でない');
    assertSame(1, $k->audit->countFor(null, 'admin_login'), '監査が残っていない');
});

test('admin: Cookie は Secure / HttpOnly / SameSite=Strict', function (): void {
    $k     = adminKernel();
    $login = loginAdmin($k);
    $cookie = $login['res']->cookies[0];

    assertSame(true, $cookie['attributes']['Secure']);
    assertSame(true, $cookie['attributes']['HttpOnly']);
    assertSame('Strict', $cookie['attributes']['SameSite']);
    assertSame((string)Config::ADMIN_SESSION_IDLE_TTL, $cookie['attributes']['Max-Age'], 'idle 30分になっていない');

    // 名前に admin・店舗名・案件番号を含めない
    $name = (string)$cookie['name'];
    foreach (['admin', 'HP-', 'サロン', 'intake_sid'] as $banned) {
        assertTrue(stripos($name, $banned) === false, 'Cookie 名に ' . $banned . ' が含まれる');
    }
});

test('admin: ID の誤り・パスワードの誤りで文言が変わらない', function (): void {
    $k = adminKernel();

    $wrongId = $k->app->handle(adminPost('/admin/login',
        ['admin_id' => 'no-such-admin', 'password' => TEST_ADMIN_PASSWORD], ['ip' => '198.51.100.1']));
    $wrongPw = $k->app->handle(adminPost('/admin/login',
        ['admin_id' => TEST_ADMIN_ID, 'password' => 'wrong-password'], ['ip' => '198.51.100.2']));

    assertSame(401, $wrongId->status);
    assertSame(401, $wrongPw->status);
    assertSame((string)$wrongId->rawBody, (string)$wrongPw->rawBody, 'IDの存在有無で応答が違う');
    assertSame([], $wrongId->cookies, '失敗なのに Cookie が出ている');
    assertSame(0, $k->adminAuth->activeCount(), '失敗なのに session ができている');
});

test('admin: ログイン失敗の監査へ ID もパスワードも残さない', function (): void {
    $k = adminKernel();
    $k->app->handle(adminPost('/admin/login',
        ['admin_id' => TEST_ADMIN_ID, 'password' => 'wrong-password']));

    $rows = $k->db->pdo()->query('SELECT * FROM intake_audit_events')->fetchAll();
    assertTrue($rows !== [], '監査が残っていない');
    foreach ($rows as $row) {
        $dump = (string)json_encode($row, JSON_UNESCAPED_UNICODE);
        assertTrue(!str_contains($dump, TEST_ADMIN_ID), '監査へ管理者IDが残っている');
        assertTrue(!str_contains($dump, 'wrong-password'), '監査へパスワードが残っている');
        assertTrue(!str_contains($dump, TEST_ADMIN_PASSWORD), '監査へパスワードが残っている');
    }
});

test('admin: ログインは HMAC化IP 単位で 10分5回', function (): void {
    $k = adminKernel();

    for ($i = 0; $i < 5; ++$i) {
        $res = $k->app->handle(adminPost('/admin/login',
            ['admin_id' => TEST_ADMIN_ID, 'password' => 'wrong']));
        assertSame(401, $res->status, ($i + 1) . '回目が 401 でない');
    }

    // 6回目は、正しいパスワードでも通さない
    $res = $k->app->handle(adminPost('/admin/login',
        ['admin_id' => TEST_ADMIN_ID, 'password' => TEST_ADMIN_PASSWORD]));
    assertSame(401, $res->status);
    assertSame([], $res->cookies, 'レート制限中にログインできてしまう');
    assertTrue(str_contains((string)$res->rawBody, '短時間に操作が集中'), '混雑の案内が出ていない');

    // 5回の失敗 ＋ 1回の締め出し ＝ 6件
    assertSame(6, $k->audit->countFor(null, 'admin_login'), '監査の件数が合わない');
    $limited = (int)$k->db->pdo()->query(
        "SELECT COUNT(*) FROM intake_audit_events
          WHERE event_type = 'admin_login' AND result_code = 'rate_limited'"
    )->fetchColumn();
    assertSame(1, $limited, '締め出しが監査へ残っていない');

    // 送信元が変われば通る（IP単位であることの確認）
    $other = $k->app->handle(adminPost('/admin/login',
        ['admin_id' => TEST_ADMIN_ID, 'password' => TEST_ADMIN_PASSWORD], ['ip' => '198.51.100.77']));
    assertSame(303, $other->status, '別の送信元まで巻き込んで止めている');
});

test('admin: 生IPを保存しない（監査は HMAC 化のみ）', function (): void {
    $k = adminKernel();
    $k->app->handle(adminPost('/admin/login',
        ['admin_id' => TEST_ADMIN_ID, 'password' => 'wrong'], ['ip' => '203.0.113.55']));

    $rows = $k->db->pdo()->query('SELECT ip_hmac FROM intake_audit_events')->fetchAll();
    foreach ($rows as $row) {
        assertTrue((string)$row['ip_hmac'] !== '203.0.113.55', '生IPが保存されている');
    }
    $dump = (string)file_get_contents($k->config->logPath);
    assertTrue(!str_contains($dump, '203.0.113.55'), 'ログへ生IPが出ている');
});

test('admin: ログインのたびに新しい session を作る（fixation 対策）', function (): void {
    $k = adminKernel();

    $first  = loginAdmin($k);
    $second = loginAdmin($k);

    $a = (string)$first['res']->cookies[0]['value'];
    $b = (string)$second['res']->cookies[0]['value'];

    assertTrue($a !== $b, 'ログインし直しても同じ session が使われている');
    assertTrue($k->adminAuth->verify($b) !== null, '新しい session が使えない');
});

/* ==================================================== 管理: session 期限 */

test('admin: idle 30分で切れる', function (): void {
    $clock = new TestClock();
    $k     = adminKernel($clock);
    $login = loginAdmin($k);

    $clock->advance(Config::ADMIN_SESSION_IDLE_TTL - 60);
    assertTrue($k->adminAuth->verify(array_values($login['cookie'])[0]) !== null, '30分未満で切れている');

    $clock->advance(120);
    assertSame(null, $k->adminAuth->verify(array_values($login['cookie'])[0]), '30分を過ぎても使える');
});

test('admin: 使うたびに idle が延びる', function (): void {
    $clock = new TestClock();
    $k     = adminKernel($clock);
    $login = loginAdmin($k);

    for ($i = 0; $i < 5; ++$i) {
        $clock->advance(20 * 60); // 20分ごとに操作
        $res = $k->app->handle(adminGet('/admin/', ['cookies' => $login['cookie']]));
        assertSame(200, $res->status, ($i + 1) . '回目で切れている');
    }
});

test('admin: 絶対期限 8時間は延びない', function (): void {
    $clock = new TestClock();
    $k     = adminKernel($clock);
    $login = loginAdmin($k);

    // 20分ごとに操作し続けても、8時間で必ず切れる
    for ($i = 0; $i < 30; ++$i) {
        $clock->advance(20 * 60);
        $k->app->handle(adminGet('/admin/', ['cookies' => $login['cookie']]));
    }

    $res = $k->app->handle(adminGet('/admin/', ['cookies' => $login['cookie']]));
    assertSame(303, $res->status, '8時間を過ぎても使える');
    assertSame('/admin/login', $res->headers['Location'] ?? '');
});

test('admin: ログアウトで失効し、監査に残る', function (): void {
    $k     = adminKernel();
    $login = loginAdmin($k);

    $res = $k->app->handle(adminPost('/admin/logout', ['csrf_token' => $login['csrf']],
        ['cookies' => $login['cookie']]));

    assertSame(303, $res->status);
    assertSame('/admin/login', $res->headers['Location'] ?? '');
    assertSame('0', $res->cookies[0]['attributes']['Max-Age'], 'Cookie を消していない');
    assertSame(null, $k->adminAuth->verify(array_values($login['cookie'])[0]), 'ログアウト後も使える');
    assertSame(1, $k->audit->countFor(null, 'admin_logout'), '監査が残っていない');

    // 失効後は画面が出ない
    $after = $k->app->handle(adminGet('/admin/', ['cookies' => $login['cookie']]));
    assertSame(303, $after->status, 'ログアウト後に一覧が見えてしまう');
});

test('admin: ログアウトは GET で受けない', function (): void {
    $k     = adminKernel();
    $login = loginAdmin($k);

    $res = $k->app->handle(adminGet('/admin/logout', ['cookies' => $login['cookie']]));
    assertSame(403, $res->status, 'GET でログアウトできてしまう');
    assertTrue($k->adminAuth->verify(array_values($login['cookie'])[0]) !== null, 'GET で失効している');
});

/* ==================================================== 管理: CSRF */

test('admin: CSRF が無ければ状態を変えられない', function (): void {
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0610');
    $login = loginAdmin($k);

    $res = $k->app->handle(adminPost('/admin/status',
        ['case' => 'HP-2026-0610', 'to' => 'reviewed'], ['cookies' => $login['cookie']]));

    assertSame(403, $res->status, 'CSRF 無しで通ってしまう');
    assertSame('submitted', (string)$k->cases->find($caseId)['status'], '状態が変わっている');
});

test('admin: CSRF が違えば状態を変えられない', function (): void {
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0611');
    $login = loginAdmin($k);

    $forged = str_repeat('A', 43);
    $res    = $k->app->handle(adminPost('/admin/status',
        ['case' => 'HP-2026-0611', 'to' => 'reviewed', 'csrf_token' => $forged],
        ['cookies' => $login['cookie']]));

    assertSame(403, $res->status, '偽の CSRF が通ってしまう');
    assertSame('submitted', (string)$k->cases->find($caseId)['status']);
});

test('admin: CSRF は平文で保存されず、画面ごとに作り直される', function (): void {
    $k     = adminKernel();
    $login = loginAdmin($k);

    $stored = (string)$k->db->pdo()->query('SELECT csrf_hash FROM intake_admin_sessions')->fetchColumn();
    assertTrue($stored !== $login['csrf'], 'CSRF が平文で保存されている');
    assertSame(64, strlen($stored), 'hash でない');

    // もう一度開くと別の値になる
    $again = $k->app->handle(adminGet('/admin/', ['cookies' => $login['cookie']]));
    preg_match('/name="csrf_token" value="([A-Za-z0-9_-]{43})"/', (string)$again->rawBody, $m);
    assertTrue(($m[1] ?? '') !== $login['csrf'], 'CSRF が使い回されている');
});

test('admin: CSRF を URL へ出さない', function (): void {
    $k     = adminKernel();
    $login = loginAdmin($k);
    $html  = (string)$login['list']->rawBody;

    preg_match_all('/(?:href|action)="([^"]*)"/', $html, $m);
    foreach ($m[1] as $url) {
        assertTrue(!str_contains($url, 'csrf'), 'URL へ CSRF が出ている: ' . $url);
        assertTrue(!str_contains($url, (string)$login['csrf']), 'URL へ CSRF の値が出ている');
    }
});

test('admin: Origin が違えば状態を変えられない', function (): void {
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0612');
    $login = loginAdmin($k);

    $res = $k->app->handle(adminPost('/admin/status',
        ['case' => 'HP-2026-0612', 'to' => 'reviewed', 'csrf_token' => $login['csrf']],
        ['cookies' => $login['cookie'], 'origin' => 'https://evil.example']));

    assertSame(403, $res->status, '他オリジンから通ってしまう');
    assertSame('submitted', (string)$k->cases->find($caseId)['status']);
});

/* ==================================================== 管理: 未認証 */

test('admin: 未認証では一覧も詳細も書き出しも見えない', function (): void {
    $k = adminKernel();
    submittedCase($k, 'HP-2026-0620');

    foreach (['/admin/', '/admin/case', '/admin/export'] as $path) {
        $res = $k->app->handle(adminGet($path, ['query' => ['case' => 'HP-2026-0620']]));
        assertSame(303, $res->status, $path . ' が未認証で見えてしまう');
        assertSame('/admin/login', $res->headers['Location'] ?? '', $path . ' の誘導先が違う');
        assertTrue(!str_contains((string)$res->rawBody, 'HP-2026-0620'), $path . ' が案件番号を漏らしている');
    }
});

test('admin: 未認証では案件の存在有無を漏らさない', function (): void {
    $k = adminKernel();
    submittedCase($k, 'HP-2026-0621');

    $exists  = $k->app->handle(adminGet('/admin/case', ['query' => ['case' => 'HP-2026-0621']]));
    $missing = $k->app->handle(adminGet('/admin/case', ['query' => ['case' => 'HP-9999-9999']]));

    assertSame($exists->status, $missing->status, '存在有無で応答が違う');
    assertSame((string)$exists->rawBody, (string)$missing->rawBody, '存在有無で本文が違う');
});

/* ==================================================== 管理: 一覧・詳細 */

test('admin: 一覧に案件が並び、回答本文を出さない', function (): void {
    $k = adminKernel();
    submittedCase($k, 'HP-2026-0630');
    $login = loginAdmin($k);
    $html  = (string)$login['list']->rawBody;

    assertSame(200, $login['list']->status);
    assertTrue(str_contains($html, 'HP-2026-0630'), '案件番号が出ていない');
    assertTrue(str_contains($html, '提出済み'), '状態が出ていない');

    // 一覧へ回答本文・PII を出さない
    foreach (['架空県架空市', '03-0000-0000', 'internal@example.invalid', '架空の紹介文'] as $pii) {
        assertTrue(!str_contains($html, $pii), '一覧に ' . $pii . ' が出ている');
    }
    assertSame(1, $k->audit->countFor(null, 'admin_viewed'), '閲覧が監査に残っていない');
});

test('admin: 詳細で11分類と不足件数を確認できる', function (): void {
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0631');
    $login = loginAdmin($k);

    $res  = $k->app->handle(adminGet('/admin/case',
        ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-2026-0631']]));
    $html = (string)$res->rawBody;

    assertSame(200, $res->status);
    foreach (['基本情報', '営業時間・定休日', 'メニュー・料金', '権利・同意'] as $title) {
        assertTrue(str_contains($html, $title), $title . ' が出ていない');
    }
    assertTrue(str_contains($html, 'すべて充足'), '充足判定が出ていない');
    assertSame(1, $k->audit->countFor($caseId, 'admin_viewed'), '閲覧が監査に残っていない');
});

test('admin: 詳細に秘密値・内部情報を出さない', function (): void {
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0632');
    $login = loginAdmin($k);

    $html = (string)$k->app->handle(adminGet('/admin/case',
        ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-2026-0632']]))->rawBody;

    // token hash / session hash / ip_hmac / submission_id をそのまま出さない
    $tokenHash = (string)$k->db->pdo()->query('SELECT token_hash FROM intake_tokens LIMIT 1')->fetchColumn();
    $sessHash  = (string)$k->db->pdo()->query('SELECT session_hash FROM intake_sessions LIMIT 1')->fetchColumn();
    $sid       = (string)$k->db->pdo()->query('SELECT submission_id FROM intake_submission_history LIMIT 1')->fetchColumn();
    $ipHmac    = (string)$k->db->pdo()->query('SELECT ip_hmac FROM intake_audit_events WHERE ip_hmac IS NOT NULL LIMIT 1')->fetchColumn();

    assertTrue(!str_contains($html, $tokenHash), 'token hash が出ている');
    assertTrue(!str_contains($html, $sessHash), 'session hash が出ている');
    assertTrue($sid !== '' && !str_contains($html, $sid), 'submission_id が出ている');
    assertTrue($ipHmac !== '' && !str_contains($html, $ipHmac), 'ip_hmac が出ている');
    assertTrue(!str_contains($html, TEST_ENC_KEY), '暗号鍵が出ている');
    assertTrue(!str_contains($html, TEST_IP_HMAC_KEY), 'HMAC鍵が出ている');
    assertTrue(!str_contains($html, 'rate_limit'), 'レート制限の情報が出ている');
    assertTrue($caseId > 0);
});

test('admin: XSS 文字列を HTML として解釈しない', function (): void {
    $k       = adminKernel();
    $caseId  = $k->cases->create('HP-2026-0633', '架空サロン');
    $token   = $k->tokens->issue($caseId);
    $secret  = $k->app->handle(jsonPost('/session/start', ['token' => $token]))->cookies[0]['value'];
    $cookies = ['cookies' => [Config::COOKIE_NAME => $secret]];

    $xss      = '<script>alert(1)</script><img src=x onerror=alert(2)>';
    $sections = completeSections();
    $sections['basic']['legal_name'] = $xss;
    $sections['promotion']['concept'] = '"><svg onload=alert(3)>';

    $k->app->handle(jsonPost('/answers/save', ['version' => 1, 'sections' => $sections], $cookies));
    $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies));

    $login = loginAdmin($k);
    $html  = (string)$k->app->handle(adminGet('/admin/case',
        ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-2026-0633']]))->rawBody;

    assertTrue(!str_contains($html, '<script>alert(1)</script>'), 'script がそのまま出ている');
    assertTrue(!str_contains($html, '<img src=x'), 'img がそのまま出ている');
    assertTrue(!str_contains($html, '<svg onload'), 'svg がそのまま出ている');
    assertTrue(str_contains($html, '&lt;script&gt;'), 'エスケープされて表示されていない');
    assertTrue(str_contains($html, '&quot;&gt;&lt;svg'), 'エスケープされて表示されていない');
});

test('admin: 画面に外部CDN・inline script・analytics を入れない', function (): void {
    $k = adminKernel();
    submittedCase($k, 'HP-2026-0634');
    $login = loginAdmin($k);

    $pages = [
        (string)$login['list']->rawBody,
        (string)$k->app->handle(adminGet('/admin/case',
            ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-2026-0634']]))->rawBody,
        (string)$k->app->handle(adminGet('/admin/login'))->rawBody,
    ];

    foreach ($pages as $i => $html) {
        assertTrue(preg_match('#<script#i', $html) !== 1, $i . ': script 要素がある');
        assertTrue(preg_match('/\son[a-z]+\s*=/i', $html) !== 1, $i . ': on... 属性がある');
        assertTrue(preg_match('#(?:src|href)="(?:https?:)?//#i', $html) !== 1, $i . ': 外部を参照している');
        assertTrue(str_contains($html, 'noindex'), $i . ': noindex が無い');
        foreach (['gtag(', 'dataLayer', 'google-analytics'] as $banned) {
            assertTrue(!str_contains($html, $banned), $i . ': ' . $banned . ' がある');
        }
    }
});

test('admin: 応答へ no-store と nosniff を付ける', function (): void {
    $k     = adminKernel();
    $login = loginAdmin($k);

    foreach ([$login['list'], $k->app->handle(adminGet('/admin/login'))] as $res) {
        assertTrue(str_contains($res->headers['Cache-Control'] ?? '', 'no-store'), 'no-store が無い');
        assertSame('nosniff', $res->headers['X-Content-Type-Options'] ?? '');
        assertSame('DENY', $res->headers['X-Frame-Options'] ?? '');
        assertTrue(str_contains($res->headers['Content-Security-Policy'] ?? '', "frame-ancestors 'none'"), 'CSP が無い');
        assertTrue(str_contains($res->headers['X-Robots-Tag'] ?? '', 'noindex'), 'noindex ヘッダーが無い');
    }
});

test('admin: Referrer-Policy は no-referrer のまま（SSOT §10.4 を変えない）', function (): void {
    $k     = adminKernel();
    $login = loginAdmin($k);

    foreach ([$login['list'], $k->app->handle(adminGet('/admin/login'))] as $res) {
        assertSame('no-referrer', $res->headers['Referrer-Policy'] ?? '', '管理画面の方針が変わっている');
    }

    [$k2, , $secret] = withSession('HP-2026-0670');
    $store = $k2->app->handle(jsonGet('/case', ['cookies' => [Config::COOKIE_NAME => $secret]]));
    assertSame('no-referrer', $store->headers['Referrer-Policy'] ?? '', '店舗向けが変わっている');
});

test('admin: Origin が null の form 送信は Sec-Fetch-Site で判定する', function (): void {
    // ブラウザは画面遷移としての form 送信で Origin: null を送る（4D で実測）。
    // その場合だけ、偽装できない Sec-Fetch-Site を見る（SSOT v1.4 §10.8）。
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0671');
    $login = loginAdmin($k);

    $makeReq = static function (array $headers) use ($login): Request {
        return new Request(
            method: 'POST',
            path: '/admin/status',
            headers: $headers + ['Content-Type' => 'application/x-www-form-urlencoded'],
            body: http_build_query([
                'case' => 'HP-2026-0671', 'to' => 'reviewed', 'csrf_token' => $login['csrf'],
            ]),
            cookies: $login['cookie'],
            isHttps: true,
            clientIp: '203.0.113.10',
        );
    };

    // 他サイトからの送信（cross-site）は通さない
    $cross = $k->app->handle($makeReq(['Origin' => 'null', 'Sec-Fetch-Site' => 'cross-site']));
    assertSame(403, $cross->status, 'cross-site が通ってしまう');
    assertSame('submitted', (string)$k->cases->find($caseId)['status']);

    // Fetch Metadata がまったく無ければ通さない（fail closed）
    $none = $k->app->handle($makeReq(['Origin' => 'null']));
    assertSame(403, $none->status, 'Origin=null かつ signal 無しで通ってしまう');
    assertSame('submitted', (string)$k->cases->find($caseId)['status']);

    // 同一オリジンからの画面遷移だけ通す
    $same = $k->app->handle($makeReq(['Origin' => 'null', 'Sec-Fetch-Site' => 'same-origin']));
    assertSame(303, $same->status, '同一オリジンの form 送信が通らない');
    assertSame('reviewed', (string)$k->cases->find($caseId)['status']);
});

test('admin: Origin が付いていれば従来どおり厳格に照合する', function (): void {
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0672');
    $login = loginAdmin($k);

    // Sec-Fetch-Site が same-origin でも、Origin が許可外なら通さない
    $res = $k->app->handle(new Request(
        method: 'POST',
        path: '/admin/status',
        headers: [
            'Content-Type'   => 'application/x-www-form-urlencoded',
            'Origin'         => 'https://evil.example',
            'Sec-Fetch-Site' => 'same-origin',
        ],
        body: http_build_query([
            'case' => 'HP-2026-0672', 'to' => 'reviewed', 'csrf_token' => $login['csrf'],
        ]),
        cookies: $login['cookie'],
        isHttps: true,
    ));

    assertSame(403, $res->status, '許可外の Origin が通ってしまう');
    assertSame('submitted', (string)$k->cases->find($caseId)['status']);
});

test('admin: 店舗向けの JSON POST は Sec-Fetch-Site だけでは通さない', function (): void {
    // 4C の回帰。店舗側の Origin 厳格検査を緩めていないこと
    [$k, , $secret] = withSession('HP-2026-0673');

    foreach (['/answers/save', '/submit', '/session/logout', '/drive/confirm'] as $path) {
        $res = $k->app->handle(new Request(
            method: 'POST',
            path: $path,
            headers: ['Content-Type' => 'application/json', 'Sec-Fetch-Site' => 'same-origin'],
            body: '{}',
            cookies: [Config::COOKIE_NAME => $secret],
            isHttps: true,
        ));
        assertSame(403, $res->status, $path . ' が Origin 無しで通ってしまう');
    }
});

/* ==================================================== 管理: 状態遷移 */

test('admin: submitted → reviewed へ変更でき、履歴と監査に残る', function (): void {
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0640');
    $login = loginAdmin($k);

    $before = $k->answers->historyCount($caseId);

    $res = $k->app->handle(adminPost('/admin/status',
        ['case' => 'HP-2026-0640', 'to' => 'reviewed', 'csrf_token' => $login['csrf']],
        ['cookies' => $login['cookie']]));

    assertSame(303, $res->status);
    assertSame('reviewed', (string)$k->cases->find($caseId)['status'], '状態が変わっていない');
    assertSame($before + 1, $k->answers->historyCount($caseId), '履歴が増えていない');
    assertSame(1, $k->audit->countFor($caseId, 'case_status_changed'), '監査が残っていない');

    $row = $k->db->pdo()->query(
        "SELECT event_type FROM intake_submission_history WHERE event_type = 'reviewed'"
    )->fetchColumn();
    assertSame('reviewed', $row, '履歴の種別が違う');
});

test('admin: submitted → needs_revision で店舗が再提出できる', function (): void {
    $k = adminKernel();
    [$caseId, $storeCookies] = submittedCase($k, 'HP-2026-0641');
    $login = loginAdmin($k);

    $k->app->handle(adminPost('/admin/status',
        ['case' => 'HP-2026-0641', 'to' => 'needs_revision', 'csrf_token' => $login['csrf']],
        ['cookies' => $login['cookie']]));

    assertSame('needs_revision', (string)$k->cases->find($caseId)['status']);

    $row = $k->db->pdo()->query(
        "SELECT event_type FROM intake_submission_history WHERE event_type = 'revision_requested'"
    )->fetchColumn();
    assertSame('revision_requested', $row, '履歴の種別が違う');

    // 店舗が再提出できる
    $res = $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $storeCookies));
    assertSame(200, $res->status, '店舗が再提出できない');
    assertSame(true, $res->body['submitted']);
    assertSame('submitted', (string)$k->cases->find($caseId)['status']);
});

test('admin: 同じ状態への再送は冪等（履歴も監査も増やさない）', function (): void {
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0642');
    $login = loginAdmin($k);

    $fields = ['case' => 'HP-2026-0642', 'to' => 'reviewed', 'csrf_token' => $login['csrf']];
    $k->app->handle(adminPost('/admin/status', $fields, ['cookies' => $login['cookie']]));

    $history = $k->answers->historyCount($caseId);
    $audit   = $k->audit->countFor($caseId, 'case_status_changed');

    for ($i = 0; $i < 3; ++$i) {
        $csrf = $k->adminAuth->rotateCsrf(
            (int)$k->db->pdo()->query('SELECT id FROM intake_admin_sessions ORDER BY id DESC LIMIT 1')->fetchColumn()
        );
        $res = $k->app->handle(adminPost('/admin/status',
            ['case' => 'HP-2026-0642', 'to' => 'reviewed', 'csrf_token' => $csrf],
            ['cookies' => $login['cookie']]));
        assertSame(303, $res->status, ($i + 2) . '回目が失敗した');
    }

    assertSame('reviewed', (string)$k->cases->find($caseId)['status']);
    assertSame($history, $k->answers->historyCount($caseId), '再送で履歴が増えている');
    assertSame($audit, $k->audit->countFor($caseId, 'case_status_changed'), '再送で監査が増えている');
});

test('admin: 許可されていない遷移は拒否する', function (): void {
    $k      = adminKernel();
    $caseId = $k->cases->create('HP-2026-0643', '架空サロン'); // draft のまま
    $login  = loginAdmin($k);

    // draft → reviewed は許可されていない
    $res = $k->app->handle(adminPost('/admin/status',
        ['case' => 'HP-2026-0643', 'to' => 'reviewed', 'csrf_token' => $login['csrf']],
        ['cookies' => $login['cookie']]));

    assertSame(303, $res->status);
    assertTrue(str_contains($res->headers['Location'] ?? '', 'msg=invalid'), '拒否の案内になっていない');
    assertSame('draft', (string)$k->cases->find($caseId)['status'], '状態が変わってしまった');
    assertSame(0, $k->audit->countFor($caseId, 'case_status_changed'), '監査が増えている');
});

test('admin: locked / closed からは戻せない（SSOT §5.1 の遷移表どおり）', function (): void {
    // v1.5 で reviewed → needs_revision は許可された。
    // ただし locked / closed から戻す経路は**作らない**。
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0646');
    $login = loginAdmin($k);

    $k->cases->transitionTo($caseId, 'reviewed');
    $k->cases->transitionTo($caseId, 'locked');
    assertSame('locked', (string)$k->cases->find($caseId)['status']);

    $csrf = $k->adminAuth->rotateCsrf(
        (int)$k->db->pdo()->query('SELECT id FROM intake_admin_sessions ORDER BY id DESC LIMIT 1')->fetchColumn()
    );
    $res = $k->app->handle(adminPost('/admin/status',
        ['case' => 'HP-2026-0646', 'to' => 'needs_revision', 'csrf_token' => $csrf],
        ['cookies' => $login['cookie']]));

    assertTrue(str_contains($res->headers['Location'] ?? '', 'msg=invalid'), 'locked から戻せてしまう');
    assertSame('locked', (string)$k->cases->find($caseId)['status'], '状態が変わってしまった');
    assertTrue($login['csrf'] !== null);
});

test('admin: 独自の status を受け付けない', function (): void {
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0644');
    $login = loginAdmin($k);

    foreach (['locked', 'closed', 'published', 'draft', '', 'REVIEWED'] as $to) {
        $res = $k->app->handle(adminPost('/admin/status',
            ['case' => 'HP-2026-0644', 'to' => $to, 'csrf_token' => $login['csrf']],
            ['cookies' => $login['cookie']]));
        assertSame(403, $res->status, $to . ' が通ってしまう');
    }
    assertSame('submitted', (string)$k->cases->find($caseId)['status'], '状態が変わってしまった');
});

test('admin: GET では状態を変えられない', function (): void {
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0645');
    $login = loginAdmin($k);

    $res = $k->app->handle(adminGet('/admin/status', [
        'cookies' => $login['cookie'],
        'query'   => ['case' => 'HP-2026-0645', 'to' => 'reviewed', 'csrf_token' => $login['csrf']],
    ]));

    assertSame(403, $res->status, 'GET で状態を変えられる');
    assertSame('submitted', (string)$k->cases->find($caseId)['status']);
});

/* ==================================================== 書き出し */

test('export: 提出済み案件を書き出せる', function (): void {
    $k = adminKernel();
    submittedCase($k, 'HP-2026-0650');
    $login = loginAdmin($k);

    $res = $k->app->handle(adminGet('/admin/export',
        ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-2026-0650']]));

    assertSame(200, $res->status);
    assertSame('application/json; charset=UTF-8', $res->headers['Content-Type'] ?? '');
    assertTrue(str_contains($res->headers['Content-Disposition'] ?? '', 'attachment'), 'attachment でない');
    assertTrue(str_contains($res->headers['Content-Disposition'] ?? '', 'HP-2026-0650'), 'ファイル名に案件番号が無い');
    assertTrue(str_contains($res->headers['Cache-Control'] ?? '', 'no-store'), 'no-store が無い');
    assertSame('nosniff', $res->headers['X-Content-Type-Options'] ?? '');

    $json = json_decode((string)$res->rawBody, true);
    assertTrue(is_array($json), 'JSON として読めない');
    assertSame(ExportService::EXPORT_SCHEMA_VERSION, $json['export_schema_version']);
    assertSame('hp_intake', $json['source']);
    assertSame('HP-2026-0650', $json['case_number']);
    assertSame('submitted', $json['status']);
    assertSame(11, count($json['answers']), '11分類が入っていない');
    assertTrue($json['rights'] !== null, '権利・同意が入っていない');
});

test('export: SHA-256 ヘッダーが本文と一致する', function (): void {
    $k = adminKernel();
    submittedCase($k, 'HP-2026-0651');
    $login = loginAdmin($k);

    $res = $k->app->handle(adminGet('/admin/export',
        ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-2026-0651']]));

    $header = $res->headers['X-Intake-Export-Sha256'] ?? '';
    assertSame(64, strlen($header), 'SHA-256 が付いていない');
    assertSame(hash('sha256', (string)$res->rawBody), $header, '本文と一致しない');
});

test('export: 未提出の案件は書き出さない', function (): void {
    $k = adminKernel();
    $k->cases->create('HP-2026-0652', '架空サロン'); // draft
    $login = loginAdmin($k);

    $res = $k->app->handle(adminGet('/admin/export',
        ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-2026-0652']]));

    assertSame(409, $res->status, '未提出でも書き出せてしまう');
    assertTrue(!str_contains((string)$res->rawBody, 'export_schema_version'), 'JSON が出てしまっている');
});

test('export: 必須を満たしていても、未提出なら書き出さない（状態で止める）', function (): void {
    // ★「不足があるから止まった」のではなく「提出されていないから止まった」ことを確かめる。
    //   回答は完全に埋まっているが、状態は draft のままにする。
    $k       = adminKernel();
    $caseId  = $k->cases->create('HP-2026-0658', '架空サロン ハルカゼ');
    $token   = $k->tokens->issue($caseId);
    $secret  = $k->app->handle(jsonPost('/session/start', ['token' => $token]))->cookies[0]['value'];
    $cookies = ['cookies' => [Config::COOKIE_NAME => $secret]];
    $k->app->handle(jsonPost('/answers/save', ['version' => 1, 'sections' => completeSections()], $cookies));

    // 必須は満たしている（＝不足による拒否ではないこと）
    assertSame([], $k->answers->evaluate($caseId)['missing'], '前提が崩れている（不足がある）');
    assertSame('draft', (string)$k->cases->find($caseId)['status']);

    $login = loginAdmin($k);
    $res   = $k->app->handle(adminGet('/admin/export',
        ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-2026-0658']]));

    assertSame(409, $res->status, '未提出なのに書き出せてしまう');
    assertTrue(!str_contains((string)$res->rawBody, 'export_schema_version'), 'JSON が出てしまっている');

    // needs_revision（＝差し戻し中）も書き出さない
    $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies));
    $csrf = $k->adminAuth->rotateCsrf(
        (int)$k->db->pdo()->query('SELECT id FROM intake_admin_sessions ORDER BY id DESC LIMIT 1')->fetchColumn()
    );
    $k->app->handle(adminPost('/admin/status',
        ['case' => 'HP-2026-0658', 'to' => 'needs_revision', 'csrf_token' => $csrf],
        ['cookies' => $login['cookie']]));
    assertSame('needs_revision', (string)$k->cases->find($caseId)['status']);

    $res2 = $k->app->handle(adminGet('/admin/export',
        ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-2026-0658']]));
    assertSame(409, $res2->status, '差し戻し中なのに書き出せてしまう');

    // 書き出してよい状態はこの4つだけ
    assertSame(['submitted', 'reviewed', 'locked', 'closed'], ExportService::EXPORTABLE);
});

test('export: 必須が欠けた案件は書き出さない（直前に再検証する）', function (): void {
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0653');

    // 提出後に回答を空にする（DBを直接操作して、検証を迂回した状況を作る）
    $k->db->pdo()->prepare('UPDATE intake_answers SET basic_json = :empty WHERE intake_case_id = :id')
        ->execute([':empty' => '{}', ':id' => $caseId]);

    $login = loginAdmin($k);
    $res   = $k->app->handle(adminGet('/admin/export',
        ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-2026-0653']]));

    assertSame(409, $res->status, '不足があるのに書き出せてしまう');
    assertSame(1, $k->audit->countFor($caseId, 'export_generated'), '監査が残っていない');
    $code = (string)$k->db->pdo()->query(
        "SELECT result_code FROM intake_audit_events WHERE event_type = 'export_generated'"
    )->fetchColumn();
    assertSame('invalid', $code, '失敗が監査へ ok として残っている');
});

test('export: 秘密値・内部情報を含めない（allowlist）', function (): void {
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0654');

    // Drive URL を登録しておき、書き出しへ出ないことを確かめる
    $driveUrl = 'https://drive.google.com/drive/folders/FAKE-FOLDER-ID-0000000000';
    $k->cases->setDriveFolder($caseId, $driveUrl, 'HP-2026-0654 写真');

    $login = loginAdmin($k);
    $body  = (string)$k->app->handle(adminGet('/admin/export',
        ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-2026-0654']]))->rawBody;

    $tokenHash = (string)$k->db->pdo()->query('SELECT token_hash FROM intake_tokens LIMIT 1')->fetchColumn();
    $sessHash  = (string)$k->db->pdo()->query('SELECT session_hash FROM intake_sessions LIMIT 1')->fetchColumn();
    $sid       = (string)$k->db->pdo()->query('SELECT submission_id FROM intake_submission_history LIMIT 1')->fetchColumn();
    $ipHmac    = (string)$k->db->pdo()->query('SELECT ip_hmac FROM intake_audit_events WHERE ip_hmac IS NOT NULL LIMIT 1')->fetchColumn();

    foreach ([
        $tokenHash, $sessHash, $sid, $ipHmac, $driveUrl,
        TEST_ENC_KEY, TEST_IP_HMAC_KEY, TEST_ADMIN_PASSWORD,
        (string)$k->config->adminPasswordHash,
    ] as $secret) {
        assertTrue($secret !== '' && !str_contains($body, $secret), '書き出しへ秘密値が出ている');
    }

    foreach ([
        'token', 'session', 'csrf', 'ip_hmac', 'password', 'cookie',
        'stripe', 'drive_folder_url', 'rate_limit', 'ratelimit',
    ] as $banned) {
        assertTrue(stripos($body, $banned) === false, '書き出しへ ' . $banned . ' が出ている');
    }

    // DB の内部 ID を出さない
    $json = json_decode($body, true);
    assertTrue(!array_key_exists('id', $json), '内部 ID が出ている');
    assertTrue(!array_key_exists('intake_case_id', $json), '内部 ID が出ている');
});

test('export: 出すキーは決めたものだけ', function (): void {
    $k = adminKernel();
    submittedCase($k, 'HP-2026-0655');
    $login = loginAdmin($k);

    $json = json_decode((string)$k->app->handle(adminGet('/admin/export',
        ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-2026-0655']]))->rawBody, true);

    $allowed = [
        'export_schema_version', 'source', 'generated_at',
        'case_number', 'contract_type', 'status',
        'submitted_at', 'locked_at', 'closed_at',
        'drive_upload_confirmed_at', 'retention_delete_due',
        'reviewed_at', 'revision_requested_at',
        'answer_schema_version', 'answers', 'rights', 'submission_summary',
        'revision_requests',
    ];
    foreach (array_keys($json) as $key) {
        assertTrue(in_array($key, $allowed, true), '想定外のキーが出ている: ' . $key);
    }
    assertSame(Migrator::ANSWER_SECTIONS, array_keys($json['answers']), '分類名が SSOT と違う');
});

test('export: reviewed_at は履歴から導く（列を増やさない）', function (): void {
    $k = adminKernel();
    submittedCase($k, 'HP-2026-0656');
    $login = loginAdmin($k);

    $before = json_decode((string)$k->app->handle(adminGet('/admin/export',
        ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-2026-0656']]))->rawBody, true);
    assertSame(null, $before['reviewed_at'], '確認前から時刻が入っている');

    $k->app->handle(adminPost('/admin/status',
        ['case' => 'HP-2026-0656', 'to' => 'reviewed', 'csrf_token' => $login['csrf']],
        ['cookies' => $login['cookie']]));

    $after = json_decode((string)$k->app->handle(adminGet('/admin/export',
        ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-2026-0656']]))->rawBody, true);
    assertTrue(is_string($after['reviewed_at']), '確認後も時刻が入らない');
    assertSame('reviewed', $after['status']);

    // intake_cases に reviewed_at 列を足していないこと
    $cols = array_column($k->db->pdo()->query("PRAGMA table_info('intake_cases')")->fetchAll(), 'name');
    assertTrue(!in_array('reviewed_at', $cols, true), '列を増やしてしまっている');
});

test('export: ダウンロードを監査へ残し、JSON 本文をログへ出さない', function (): void {
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0657');
    $login = loginAdmin($k);

    $body = (string)$k->app->handle(adminGet('/admin/export',
        ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-2026-0657']]))->rawBody;

    assertSame(1, $k->audit->countFor($caseId, 'export_generated'), '監査が1件でない');

    $log = (string)file_get_contents($k->config->logPath);
    assertTrue(str_contains($log, 'export_generated'), '書き出しがログに残っていない');
    foreach (['架空県架空市', '03-0000-0000', 'internal@example.invalid', 'export_schema_version'] as $leak) {
        assertTrue(!str_contains($log, $leak), 'ログへ ' . $leak . ' が出ている');
    }
    assertTrue(strlen($body) > 500, '書き出しが空に近い');
});

test('export: ファイル名を安全に正規化する', function (): void {
    $k = adminKernel();

    assertSame('HP-202608-0001_intake_export.json', $k->export->fileName('HP-202608-0001'));
    // 記号だけ・空になる入力は、当たり障りのない名前へ落とす
    assertSame('case_intake_export.json', $k->export->fileName('..'));
    assertSame('case_intake_export.json', $k->export->fileName(''));

    // 引用符・改行・パス区切りを通さない
    foreach (['a"b', "a\nb", '../../etc/passwd', '架空サロン'] as $bad) {
        $name = $k->export->fileName($bad);
        assertTrue(preg_match('/^[A-Za-z0-9._-]+$/', $name) === 1, '危険な文字が残る: ' . $name);
    }
});

/* ==================================================== 既存の回帰 */

test('admin: 店舗の session を管理画面へ流用できない', function (): void {
    $k = adminKernel();
    [, $storeCookies] = submittedCase($k, 'HP-2026-0660');
    $storeSecret = $storeCookies['cookies'][Config::COOKIE_NAME];

    // 店舗の secret を管理 Cookie として送る
    $res = $k->app->handle(adminGet('/admin/', [
        'cookies' => [Config::ADMIN_COOKIE_NAME => $storeSecret],
    ]));
    assertSame(303, $res->status, '店舗の session で管理画面へ入れてしまう');
    assertSame(null, $k->adminAuth->verify($storeSecret), '店舗の session が管理側で通る');
});

test('admin: 管理 session を店舗APIへ流用できない', function (): void {
    $k = adminKernel();
    submittedCase($k, 'HP-2026-0661');
    $login = loginAdmin($k);
    $adminSecret = array_values($login['cookie'])[0];

    $res = $k->app->handle(jsonGet('/case', ['cookies' => [Config::COOKIE_NAME => $adminSecret]]));
    assertSame(404, $res->status, '管理 session で店舗APIが使えてしまう');
});

test('admin: 管理 session の平文を保存しない', function (): void {
    $k     = adminKernel();
    $login = loginAdmin($k);
    $secret = array_values($login['cookie'])[0];

    $rows = $k->db->pdo()->query('SELECT * FROM intake_admin_sessions')->fetchAll();
    foreach ($rows as $row) {
        $dump = (string)json_encode($row);
        assertTrue(!str_contains($dump, $secret), '平文 session が保存されている');
        assertTrue(!str_contains($dump, (string)$login['csrf']), '平文 CSRF が保存されている');
    }

    $cols = array_column($k->db->pdo()->query("PRAGMA table_info('intake_admin_sessions')")->fetchAll(), 'name');
    foreach ($cols as $col) {
        assertTrue(!in_array($col, ['secret', 'plain', 'token', 'password'], true), '平文列 ' . $col . ' がある');
    }
    assertTrue(in_array('session_hash', $cols, true) && in_array('csrf_hash', $cols, true), 'hash 列が無い');
});

test('admin: session ID と CSRF をログへ出さない', function (): void {
    $k     = adminKernel();
    submittedCase($k, 'HP-2026-0662');
    $login = loginAdmin($k);
    $k->app->handle(adminGet('/admin/case',
        ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-2026-0662']]));

    $log = (string)file_get_contents($k->config->logPath);
    assertTrue(!str_contains($log, array_values($login['cookie'])[0]), 'ログへ session が出ている');
    assertTrue(!str_contains($log, (string)$login['csrf']), 'ログへ CSRF が出ている');
    assertTrue(!str_contains($log, TEST_ADMIN_ID), 'ログへ管理者IDが出ている');
});

test('admin: 管理画面は HTTPS を要求する', function (): void {
    $k = adminKernel();

    $res = $k->app->handle(adminGet('/admin/', ['https' => false]));
    assertSame(403, $res->status, 'http で管理画面へ入れてしまう');
});
