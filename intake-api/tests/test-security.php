<?php
/**
 * セキュリティ総合検査（HP-ONBOARDING-4E）
 *
 * 既存のテストは「機能が動くこと」を確かめている。
 * こちらは「**壊れ方**」を確かめる — 認可の迂回・情報の漏えい・注入・競合。
 *
 * ★ローカル限定。外部へ接続しない。実鍵・実tokenを使わない。
 * ★大量試行を行わない（DoS を起こさない範囲）。
 */
declare(strict_types=1);

use SmartLabo\Intake\AnswerPaths;
use SmartLabo\Intake\Config;
use SmartLabo\Intake\Http\Request;
use SmartLabo\Intake\Http\Response;
use SmartLabo\Intake\Migrator;
use SmartLabo\Intake\Service\CaseService;
use SmartLabo\Intake\Support\Crypto;
use SmartLabo\Intake\Support\DriveLink;

/** 検査で使う、見分けのつく架空マーカー（実在情報ではない） */
const MARK_ANSWER   = 'ZZMARKANSWERZZ';
const MARK_MESSAGE  = 'ZZMARKMESSAGEZZ';
const MARK_SHOPNAME = 'ZZMARKSHOPZZ';

/** 任意ヘッダーで POST を組む（Guard の検査用） */
function rawPost(string $path, string $body, array $headers, array $cookies = [], bool $https = true): Request
{
    return new Request(
        method: 'POST',
        path: $path,
        headers: $headers,
        body: $body,
        cookies: $cookies,
        isHttps: $https,
    );
}

/** 任意ヘッダーで GET を組む */
function rawGet(string $path, array $headers, array $cookies = [], array $query = []): Request
{
    return new Request(
        method: 'GET',
        path: $path,
        headers: $headers,
        body: '',
        cookies: $cookies,
        isHttps: true,
        query: $query,
    );
}

/* ==================================================== endpoint と経路 */

test('経路: 未知パスは固定の応答で、endpoint の存在を漏らさない', function (): void {
    $k = adminKernel();

    // 店舗側（/admin 以外）: すべて同じ 404 / 同一文言
    $bodies = [];
    foreach ([
        '/', '/api', '/case/1', '/answers', '/.env', '/index.php~', '/config.php',
        '/intake.sqlite', '/private/intake-config.php', '/../src/Config.php',
    ] as $path) {
        $res = $k->app->handle(jsonGet($path));
        assertSame(404, $res->status, $path . ' が 404 でない');
        $bodies[] = (string)json_encode($res->body, JSON_UNESCAPED_UNICODE);
    }
    assertSame(1, count(array_unique($bodies)), '未知パスで応答が違う（存在を推測できる）');

    // 管理側の未知パスも、互いに同じ応答であること
    // （/admin 配下は「管理画面がある」こと自体が公開情報なので、店舗側と同一である必要はない）
    $adminBodies = [];
    foreach (['/admin-x', '/admin/nope', '/admin/case/1', '/admin/../src'] as $path) {
        $res = $k->app->handle(adminGet($path));
        assertSame(404, $res->status, $path . ' が 404 でない');
        $adminBodies[] = (string)$res->rawBody;
    }
    assertSame(1, count(array_unique($adminBodies)), '管理側の未知パスで応答が違う');

    // どちらも内部情報を含まない
    foreach (array_merge($bodies, $adminBodies) as $b) {
        foreach (['intake_', 'SELECT', 'C:\\', '/home/', 'Fatal', 'Warning'] as $leak) {
            assertTrue(!str_contains($b, $leak), '応答へ内部情報が出ている: ' . $leak);
        }
    }
});

test('経路: GET で状態を変える endpoint が無い', function (): void {
    $k = adminKernel();
    [$caseId, $storeCookies] = submittedCase($k, 'HP-2026-0900');
    $login = loginAdmin($k);

    $statusBefore  = (string)$k->cases->find($caseId)['status'];
    $tokensBefore  = (int)$k->db->pdo()->query('SELECT COUNT(*) FROM intake_tokens')->fetchColumn();
    $historyBefore = $k->answers->historyCount($caseId);

    // 状態を変える系の path をすべて GET で叩く
    foreach ([
        '/answers/save', '/submit', '/drive/confirm', '/session/logout',
        '/admin/status', '/admin/revision/send', '/admin/create', '/admin/reissue/send', '/admin/logout',
    ] as $path) {
        $res = $k->app->handle(rawGet($path, [
            'Origin' => TEST_ORIGIN, 'Sec-Fetch-Site' => 'same-origin',
        ], array_merge($storeCookies['cookies'], $login['cookie']), ['case' => 'HP-2026-0900', 'to' => 'reviewed']));

        assertTrue(in_array($res->status, [403, 404, 405], true), $path . ' の GET が通っている: ' . $res->status);
    }

    assertSame($statusBefore, (string)$k->cases->find($caseId)['status'], 'GET で状態が変わった');
    assertSame($tokensBefore, (int)$k->db->pdo()->query('SELECT COUNT(*) FROM intake_tokens')->fetchColumn(),
        'GET で token が増えた');
    assertSame($historyBefore, $k->answers->historyCount($caseId), 'GET で履歴が増えた');
});

/* ==================================================== 認証・認可の分離 */

test('分離: 店舗 Cookie で管理画面へ入れない', function (): void {
    $k = adminKernel();
    [, $storeCookies] = submittedCase($k, 'HP-2026-0901');
    $storeSecret = $storeCookies['cookies'][Config::COOKIE_NAME];

    foreach (['/admin/', '/admin/case', '/admin/export', '/admin/new', '/admin/reissue'] as $path) {
        $res = $k->app->handle(adminGet($path, [
            'cookies' => [Config::ADMIN_COOKIE_NAME => $storeSecret],
            'query'   => ['case' => 'HP-2026-0901'],
        ]));
        assertSame(303, $res->status, $path . ' へ店舗 Cookie で入れる');
        assertTrue(!str_contains((string)$res->rawBody, 'HP-2026-0901'), $path . ' が案件番号を漏らしている');
    }
    assertSame(null, $k->adminAuth->verify($storeSecret), '店舗 session が管理側で通る');
});

test('分離: 管理 Cookie で店舗APIを使えない', function (): void {
    $k = adminKernel();
    submittedCase($k, 'HP-2026-0902');
    $login       = loginAdmin($k);
    $adminSecret = array_values($login['cookie'])[0];

    $get = $k->app->handle(jsonGet('/case', ['cookies' => [Config::COOKIE_NAME => $adminSecret]]));
    assertSame(404, $get->status, '管理 session で /case が取れる');

    foreach (['/answers/save', '/submit', '/drive/confirm', '/session/logout'] as $path) {
        $res = $k->app->handle(jsonPost($path, [], ['cookies' => [Config::COOKIE_NAME => $adminSecret]]));
        assertSame(404, $res->status, $path . ' が管理 session で通る');
    }
});

test('分離: Cookie 名・属性・Path が用途ごとに分かれている', function (): void {
    $k = adminKernel();
    [, , $secret] = withSession('HP-2026-0903');
    assertTrue($secret !== '');

    // 名前が別
    assertTrue(Config::COOKIE_NAME !== Config::ADMIN_COOKIE_NAME, 'Cookie 名が同じ');

    // 店舗: Path=/ ／ 管理: Path=/admin
    $k2 = adminKernel();
    $caseId = $k2->cases->create('HP-2026-0904', '架空サロン');
    $token  = $k2->tokens->issue($caseId);
    $store  = $k2->app->handle(jsonPost('/session/start', ['token' => $token]))->cookies[0];
    $admin  = loginAdmin($k2)['res']->cookies[0];

    assertSame('/', $store['attributes']['Path'], '店舗 Cookie の Path が違う');
    assertSame('/admin', $admin['attributes']['Path'], '管理 Cookie の Path が違う');

    foreach ([$store, $admin] as $i => $c) {
        assertSame(true, $c['attributes']['Secure'], $i . ': Secure でない');
        assertSame(true, $c['attributes']['HttpOnly'], $i . ': HttpOnly でない');
        assertSame('Strict', $c['attributes']['SameSite'], $i . ': SameSite=Strict でない');
        // Domain を広げない（属性そのものを持たない＝ホスト限定）
        assertTrue(!isset($c['attributes']['Domain']), $i . ': Domain を設定している');
    }
});

/* ==================================================== IDOR・案件分離 */

test('IDOR: 案件Aの session で案件Bへ触れない', function (): void {
    $k = adminKernel();
    [$caseA, $cookiesA] = submittedCase($k, 'HP-2026-0910');

    // 案件B（別の店舗）
    $caseB   = $k->cases->create('HP-2026-0911', '別の架空サロン');
    $tokenB  = $k->tokens->issue($caseB);
    $secretB = $k->app->handle(jsonPost('/session/start', ['token' => $tokenB]))->cookies[0]['value'];
    $k->cases->setDriveFolder($caseB, FAKE_DRIVE_URL, 'B 素材', FAKE_DRIVE_EMAIL);
    $k->app->handle(jsonPost('/answers/save', [
        'version' => 1, 'sections' => ['basic' => ['legal_name' => MARK_ANSWER]],
    ], ['cookies' => [Config::COOKIE_NAME => $secretB]]));

    // 案件Aの session で読む → 案件Aの内容しか返らない
    $res = $k->app->handle(jsonGet('/case', $cookiesA));
    assertSame(200, $res->status);
    assertSame('HP-2026-0910', $res->body['case_number'], '別案件が返っている');

    $dump = (string)json_encode($res->body, JSON_UNESCAPED_UNICODE);
    assertTrue(!str_contains($dump, MARK_ANSWER), '案件Bの回答が漏れている');
    assertTrue(!str_contains($dump, 'HP-2026-0911'), '案件Bの案件番号が漏れている');
    assertSame(null, $res->body['drive']['folder_url'], '案件Bの Drive 情報が漏れている');

    // 保存・提出も案件Aにしか効かない
    $k->app->handle(jsonPost('/answers/save', [
        'version' => $res->body['version'], 'sections' => ['basic' => ['legal_name' => 'AAA']],
    ], $cookiesA));
    assertSame(MARK_ANSWER, $k->answers->get($caseB)['sections']['basic']['legal_name'], '案件Bが書き換わった');
    assertTrue($caseA > 0);
});

test('IDOR: 管理画面の案件番号を差し替えても、他案件の操作にならない', function (): void {
    $k = adminKernel();
    [$caseA] = submittedCase($k, 'HP-2026-0912');
    [$caseB] = submittedCase($k, 'HP-2026-0913');
    $login = loginAdmin($k);

    // A の CSRF で B の状態を変える（管理者は両方見られるが、対象は body で決まる）
    $k->app->handle(adminPost('/admin/status',
        ['case' => 'HP-2026-0913', 'to' => 'reviewed', 'csrf_token' => $login['csrf']],
        ['cookies' => $login['cookie']]));

    assertSame('submitted', (string)$k->cases->find($caseA)['status'], '対象でない案件が変わった');
    assertSame('reviewed', (string)$k->cases->find($caseB)['status'], '対象案件が変わっていない');

    // 存在しない案件番号は 404（他案件へ流れない）
    $res = $k->app->handle(adminPost('/admin/status',
        ['case' => 'HP-9999-9999', 'to' => 'reviewed', 'csrf_token' => freshCsrf($k)],
        ['cookies' => $login['cookie']]));
    assertSame(404, $res->status, '存在しない案件が処理された');
});

test('IDOR: DB内部IDを URL へ入れても案件を取れない', function (): void {
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0914');
    $login = loginAdmin($k);

    // 内部 ID・連番・SQL 断片を case パラメータへ入れる
    foreach ([(string)$caseId, '1', '0', '-1', "1' OR '1'='1", '%', '_'] as $probe) {
        foreach (['/admin/case', '/admin/export', '/admin/reissue'] as $path) {
            $res = $k->app->handle(adminGet($path,
                ['cookies' => $login['cookie'], 'query' => ['case' => $probe]]));
            assertSame(404, $res->status, $path . ' が内部IDで開けた: ' . $probe);
            assertTrue(!str_contains((string)$res->rawBody, 'HP-2026-0914'), '案件番号が漏れている');
        }
    }
});

/* ==================================================== CSRF / Origin */

test('CSRF: 管理POSTの組合せマトリクス', function (): void {
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0920');
    $login = loginAdmin($k);

    $attempt = static function (array $headers, ?string $csrf) use ($k, $login): int {
        $fields = ['case' => 'HP-2026-0920', 'to' => 'reviewed'];
        if ($csrf !== null) {
            $fields['csrf_token'] = $csrf;
        }

        return $k->app->handle(rawPost('/admin/status', http_build_query($fields),
            ['Content-Type' => 'application/x-www-form-urlencoded'] + $headers,
            $login['cookie']))->status;
    };

    $origin = ['Origin' => TEST_ORIGIN];
    $evil   = ['Origin' => 'https://evil.example'];

    // 拒否されるべき組合せ
    assertSame(403, $attempt($origin, null), '正式Origin＋CSRF欠落が通る');
    assertSame(403, $attempt($origin, str_repeat('A', 43)), '正式Origin＋CSRF不一致が通る');
    assertSame(403, $attempt($evil, freshCsrf($k)), '不正Origin＋正しいCSRFが通る');
    assertSame(403, $attempt($evil + ['Sec-Fetch-Site' => 'same-origin'], freshCsrf($k)),
        '不正Origin＋Sec-Fetch same-origin が通る');

    foreach (['cross-site', 'same-site', 'none'] as $site) {
        assertSame(403, $attempt(['Origin' => 'null', 'Sec-Fetch-Site' => $site], freshCsrf($k)),
            'Origin:null＋' . $site . ' が通る');
    }
    assertSame(403, $attempt(['Origin' => 'null'], freshCsrf($k)), 'Origin:null＋Sec-Fetch欠落が通る');
    assertSame(403, $attempt([], freshCsrf($k)), 'Origin欠落＋Sec-Fetch欠落が通る');
    assertSame(403, $attempt(['Sec-Fetch-Site' => 'cross-site'], freshCsrf($k)), 'Origin欠落＋cross-site が通る');
    assertSame(403, $attempt(['Origin' => 'null', 'Sec-Fetch-Site' => 'same-origin'], null),
        'Origin:null＋same-origin＋CSRF欠落が通る');

    assertSame('submitted', (string)$k->cases->find($caseId)['status'], '拒否のはずが状態が変わった');

    // 通るべき組合せ
    assertSame(303, $attempt($origin, freshCsrf($k)), '正式Origin＋正しいCSRFが通らない');
    assertSame('reviewed', (string)$k->cases->find($caseId)['status']);
});

test('CSRF: Origin:null＋same-origin＋正しいCSRF は通る', function (): void {
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0921');
    $login = loginAdmin($k);

    $res = $k->app->handle(rawPost('/admin/status',
        http_build_query(['case' => 'HP-2026-0921', 'to' => 'reviewed', 'csrf_token' => freshCsrf($k)]),
        [
            'Content-Type'   => 'application/x-www-form-urlencoded',
            'Origin'         => 'null',
            'Sec-Fetch-Site' => 'same-origin',
        ],
        $login['cookie']));

    assertSame(303, $res->status, '同一オリジンの form 送信が通らない');
    assertSame('reviewed', (string)$k->cases->find($caseId)['status']);
});

test('CSRF: 使い回し・別session・失効sessionの token を拒否する', function (): void {
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0922');
    $login = loginAdmin($k);

    // 1. 使い回し（状態を変える操作のあと同じ CSRF）
    $csrf = freshCsrf($k);
    $k->app->handle(adminPost('/admin/create',
        ['csrf_token' => $csrf, 'shop_display_name' => '架空サロン', 'contract_type' => 'standalone'],
        ['cookies' => $login['cookie']]));
    $again = $k->app->handle(adminPost('/admin/create',
        ['csrf_token' => $csrf, 'shop_display_name' => '架空サロン2', 'contract_type' => 'standalone'],
        ['cookies' => $login['cookie']]));
    assertSame(403, $again->status, '使い回しの CSRF が通る');

    // 2. 別の管理 session の CSRF
    $other     = loginAdmin($k);
    $otherCsrf = (string)$other['csrf'];
    $res = $k->app->handle(adminPost('/admin/status',
        ['case' => 'HP-2026-0922', 'to' => 'reviewed', 'csrf_token' => $otherCsrf],
        ['cookies' => $login['cookie']]));
    assertSame(403, $res->status, '別 session の CSRF が通る');
    assertSame('submitted', (string)$k->cases->find($caseId)['status']);
});

test('CSRF: 失効した管理 session の CSRF は通らない', function (): void {
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0923');
    $login = loginAdmin($k);
    $csrf  = (string)$login['csrf'];

    // ログアウトして session を失効させる
    $k->app->handle(adminPost('/admin/logout', ['csrf_token' => $csrf], ['cookies' => $login['cookie']]));

    $res = $k->app->handle(adminPost('/admin/status',
        ['case' => 'HP-2026-0923', 'to' => 'reviewed', 'csrf_token' => $csrf],
        ['cookies' => $login['cookie']]));

    assertSame(303, $res->status, '失効 session で操作できた');
    assertSame('submitted', (string)$k->cases->find($caseId)['status'], '状態が変わった');
});

test('Origin: 店舗POSTは Sec-Fetch や管理CSRF では通らない', function (): void {
    $k = adminKernel();
    [, $storeCookies] = submittedCase($k, 'HP-2026-0924');
    $login  = loginAdmin($k);
    $secret = $storeCookies['cookies'][Config::COOKIE_NAME];

    foreach (['/answers/save', '/submit', '/drive/confirm', '/session/logout'] as $path) {
        foreach ([
            ['Sec-Fetch-Site' => 'same-origin'],
            ['Origin' => 'null', 'Sec-Fetch-Site' => 'same-origin'],
            ['Origin' => 'https://evil.example'],
            [],
        ] as $headers) {
            $res = $k->app->handle(rawPost($path,
                (string)json_encode(['csrf_token' => $login['csrf']]),
                ['Content-Type' => 'application/json'] + $headers,
                [Config::COOKIE_NAME => $secret]));
            assertSame(403, $res->status, $path . ' が通った: ' . json_encode($headers));
        }
    }
});

test('Origin: GET /case は same-origin だけ', function (): void {
    [$k, , $secret] = withSession('HP-2026-0925');
    $cookies = [Config::COOKIE_NAME => $secret];

    foreach (['cross-site', 'same-site', 'none'] as $site) {
        $res = $k->app->handle(rawGet('/case', ['Sec-Fetch-Site' => $site], $cookies));
        assertSame(403, $res->status, $site . ' が通る');
    }
    // ヘッダーが何も無い（Origin も Referer も無い）→ 拒否
    assertSame(403, $k->app->handle(rawGet('/case', [], $cookies))->status, 'signal 無しが通る');

    // 画面遷移としての取得も拒否
    $nav = $k->app->handle(rawGet('/case',
        ['Sec-Fetch-Site' => 'same-origin', 'Sec-Fetch-Mode' => 'navigate'], $cookies));
    assertSame(403, $nav->status, '画面遷移としての取得が通る');

    // same-origin の fetch だけ通る
    $ok = $k->app->handle(rawGet('/case',
        ['Sec-Fetch-Site' => 'same-origin', 'Sec-Fetch-Mode' => 'cors'], $cookies));
    assertSame(200, $ok->status, '同一オリジンの取得が通らない');
});

test('Origin: CORS ヘッダーを一切返さない', function (): void {
    [$k, , $secret] = withSession('HP-2026-0926');
    $login = null;

    $responses = [
        $k->app->handle(jsonGet('/case', ['cookies' => [Config::COOKIE_NAME => $secret]])),
        $k->app->handle(jsonPost('/session/start', ['token' => 'x'])),
        $k->app->handle(jsonGet('/unknown')),
    ];
    foreach ($responses as $i => $res) {
        foreach ([
            'Access-Control-Allow-Origin', 'Access-Control-Allow-Credentials',
            'Access-Control-Allow-Methods', 'Access-Control-Allow-Headers', 'Timing-Allow-Origin',
        ] as $h) {
            assertTrue(!isset($res->headers[$h]), $i . ': ' . $h . ' を返している');
        }
    }
    assertTrue($login === null);
});

/* ==================================================== 入力検証・注入 */

test('注入: 悪意ある入力でも SQL が壊れない・混ざらない', function (): void {
    [$k, $caseId, $secret] = withSession('HP-2026-0930');
    $cookies = ['cookies' => [Config::COOKIE_NAME => $secret]];

    $payloads = [
        "'; DROP TABLE intake_answers; --",
        "' OR '1'='1",
        "1; UPDATE intake_cases SET status='closed'; --",
        "\\'; DELETE FROM intake_tokens; --",
        "%27%20OR%201%3D1",
        "0x27",
        "' UNION SELECT token_hash FROM intake_tokens --",
    ];

    $version = 1;
    foreach ($payloads as $p) {
        $res = $k->app->handle(jsonPost('/answers/save', [
            'version' => $version, 'sections' => ['basic' => ['legal_name' => $p]],
        ], $cookies));
        assertSame(200, $res->status, '保存できない: ' . $p);
        $version = $res->body['version'];

        // 値としてそのまま保存され、SQL としては解釈されない
        assertSame($p, $k->answers->get($caseId)['sections']['basic']['legal_name'], '値が変わった');
    }

    // 表が消えていない・状態も変わっていない
    assertSame(8, (int)$k->db->pdo()->query(
        "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name LIKE 'intake_%'"
    )->fetchColumn(), 'テーブルが壊れた');
    assertSame('draft', (string)$k->cases->find($caseId)['status'], '状態が変わった');
    assertSame(1, (int)$k->db->pdo()->query('SELECT COUNT(*) FROM intake_tokens')->fetchColumn(), 'token が変わった');
});

test('注入: 案件番号・パス・UUID の偽装を受け付けない', function (): void {
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0931');
    $login = loginAdmin($k);

    // 案件番号の偽装
    foreach (["HP-2026-0931' OR '1'='1", 'HP-2026-0931%', 'HP-2026-093_', '../HP-2026-0931'] as $fake) {
        $res = $k->app->handle(adminGet('/admin/case',
            ['cookies' => $login['cookie'], 'query' => ['case' => $fake]]));
        assertSame(404, $res->status, '偽装した案件番号が通る: ' . $fake);
    }

    // 修正依頼のパス偽装
    foreach ([
        'basic.legal_name; DROP TABLE x', '../basic.legal_name', 'basic.legal_name%00',
        'BASIC.LEGAL_NAME', '__proto__', 'constructor.prototype',
    ] as $fake) {
        assertTrue(!AnswerPaths::isValid($fake), '偽装したパスが通る: ' . $fake);
    }

    // UUID の偽装
    foreach ([
        "3f2504e0-4f89-41d3-9a0c-0305e82c3301' OR '1'='1",
        '3f2504e0-4f89-41d3-9a0c-0305e82c3301%00',
        '../3f2504e0-4f89-41d3-9a0c-0305e82c3301',
    ] as $fake) {
        assertTrue(!\SmartLabo\Intake\Service\AnswerService::isValidSubmissionId($fake),
            '偽装した submission_id が通る: ' . $fake);
    }
    assertSame('submitted', (string)$k->cases->find($caseId)['status']);
});

test('注入: 壊れた入力・過大入力を安全に拒否する', function (): void {
    [$k, , $secret] = withSession('HP-2026-0932');
    $cookies = [Config::COOKIE_NAME => $secret];

    // Content-Type 違い
    foreach (['text/plain', 'application/x-www-form-urlencoded', 'multipart/form-data', ''] as $ctype) {
        $res = $k->app->handle(rawPost('/answers/save', '{"version":1,"sections":{}}',
            ['Content-Type' => $ctype, 'Origin' => TEST_ORIGIN], $cookies));
        assertSame(400, $res->status, 'Content-Type ' . $ctype . ' が通る');
    }

    // 壊れた JSON
    foreach (['{', '[1,2', 'null', 'true', '"x"', '', '{"a":}'] as $bad) {
        $res = $k->app->handle(rawPost('/answers/save', $bad,
            ['Content-Type' => 'application/json', 'Origin' => TEST_ORIGIN], $cookies));
        assertSame(400, $res->status, '壊れた JSON が通る: ' . $bad);
    }

    // body 上限（1MB 超）
    $huge = str_repeat('a', Config::MAX_BODY_BYTES + 10);
    $res  = $k->app->handle(rawPost('/answers/save', $huge,
        ['Content-Type' => 'application/json', 'Origin' => TEST_ORIGIN], $cookies));
    assertSame(413, $res->status, '1MB 超が通る');

    // 深い入れ子（JSON 爆弾に近い形。値としては受け取るが壊れない）
    $deep = ['basic' => []];
    $node = &$deep['basic'];
    for ($i = 0; $i < 200; ++$i) {
        $node['x'] = [];
        $node      = &$node['x'];
    }
    unset($node);
    $res = $k->app->handle(jsonPost('/answers/save',
        ['version' => 1, 'sections' => $deep], ['cookies' => $cookies]));
    assertTrue(in_array($res->status, [200, 400, 413], true), '深い入れ子で想定外の応答: ' . $res->status);
});

test('注入: 型の偽装を受け付けない', function (): void {
    [$k, , $secret] = withSession('HP-2026-0933');
    $cookies = ['cookies' => [Config::COOKIE_NAME => $secret]];

    // version が整数でない
    foreach (['1', 1.5, true, null, [], ['a' => 1]] as $v) {
        $res = $k->app->handle(jsonPost('/answers/save',
            ['version' => $v, 'sections' => ['basic' => []]], $cookies));
        assertSame(400, $res->status, 'version の型偽装が通る: ' . json_encode($v));
    }

    // sections が配列でない / 中身が配列でない
    foreach (['x', 1, true] as $v) {
        $res = $k->app->handle(jsonPost('/answers/save',
            ['version' => 1, 'sections' => $v], $cookies));
        assertSame(400, $res->status, 'sections の型偽装が通る');
    }
    $res = $k->app->handle(jsonPost('/answers/save',
        ['version' => 1, 'sections' => ['basic' => 'string']], $cookies));
    assertSame(400, $res->status, '分類の中身の型偽装が通る');

    // drive/confirm は true 以外を受けない
    foreach (['true', 1, 'yes', [], null] as $v) {
        $res = $k->app->handle(jsonPost('/drive/confirm', ['confirmed' => $v], $cookies));
        assertSame(400, $res->status, 'confirmed の型偽装が通る: ' . json_encode($v));
    }
});

test('注入: 制御文字・不正UTF-8・NULLバイトで壊れない', function (): void {
    [$k, $caseId, $secret] = withSession('HP-2026-0934');
    $cookies = ['cookies' => [Config::COOKIE_NAME => $secret]];

    // JSON は不正 UTF-8 を通さない（json_decode が失敗する）
    $res = $k->app->handle(rawPost('/answers/save',
        "{\"version\":1,\"sections\":{\"basic\":{\"legal_name\":\"\xC3\x28\"}}}",
        ['Content-Type' => 'application/json', 'Origin' => TEST_ORIGIN],
        [Config::COOKIE_NAME => $secret]));
    assertSame(400, $res->status, '不正 UTF-8 が通る');

    // CRLF / NUL を含む値（JSON エスケープ経由）
    $payload = "line1\r\nSet-Cookie: evil=1\u{0000}\u{202E}";
    $res     = $k->app->handle(jsonPost('/answers/save',
        ['version' => 1, 'sections' => ['basic' => ['legal_name' => $payload]]], $cookies));
    assertSame(200, $res->status, '保存できない');

    // 値として保存され、DBもヘッダーも壊れない
    $stored = $k->answers->get($caseId)['sections']['basic']['legal_name'];
    assertTrue(is_string($stored), '値が壊れた');
    foreach ($res->headers as $name => $value) {
        assertTrue(!str_contains((string)$value, "\r") && !str_contains((string)$value, "\n"),
            'ヘッダーへ改行が混ざった: ' . $name);
    }
});

/* ==================================================== XSS */

test('XSS: 管理画面・店舗応答・書き出しで HTML にならない', function (): void {
    $k       = adminKernel();
    $caseId  = $k->cases->create('HP-2026-0940', MARK_SHOPNAME . '<script>alert(1)</script>');
    $token   = $k->tokens->issue($caseId);
    $secret  = $k->app->handle(jsonPost('/session/start', ['token' => $token]))->cookies[0]['value'];
    $cookies = ['cookies' => [Config::COOKIE_NAME => $secret]];

    $payloads = [
        '<script>alert(1)</script>',
        '<img src=x onerror=alert(2)>',
        '<svg onload=alert(3)>',
        '<iframe src=javascript:alert(4)>',
        '"><style>@import"evil"</style>',
        "javascript:alert(5)",
        'data:text/html,<b>x</b>',
        '&lt;script&gt;alert(6)&lt;/script&gt;',
        '"><input autofocus onfocus=alert(7)>',
        '</textarea><script>alert(8)</script>',
    ];

    $sections = completeSections();
    $sections['basic']['legal_name']   = $payloads[0] . MARK_ANSWER;
    $sections['basic']['description']  = implode(' ', $payloads);
    $sections['menus'][0]['name']      = $payloads[1];
    $sections['staff']                 = [['display_name' => $payloads[2], 'published' => false]];
    $sections['web_links']['instagram'] = 'javascript:alert(9)';

    $k->app->handle(jsonPost('/answers/save', ['version' => 1, 'sections' => $sections], $cookies));
    $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies));

    $login = loginAdmin($k);
    $k->app->handle(revisionPost('HP-2026-0940', ['basic.legal_name'], (string)$login['csrf'],
        MARK_MESSAGE . '<script>alert(10)</script>', ['cookies' => $login['cookie']]));

    // 管理画面（詳細・一覧）
    // ★確かめるのは「文字列が現れないこと」ではなく「**実行できる形にならないこと**」。
    //   javascript: のように HTML メタ文字を含まない値は、文字として現れてよい（無害）。
    foreach ([
        'detail' => (string)$k->app->handle(adminGet('/admin/case',
            ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-2026-0940']]))->rawBody,
        'list'   => (string)$k->app->handle(adminGet('/admin/', ['cookies' => $login['cookie']]))->rawBody,
    ] as $name => $html) {
        // HTML メタ文字を含む payload は、生のままでは絶対に出ない
        foreach ($payloads as $p) {
            if (preg_match('/[<>"]/', $p) === 1) {
                assertTrue(!str_contains($html, $p), $name . ' に生の payload が出ている: ' . $p);
            }
        }
        // 実行可能な要素・属性が生まれていない
        assertTrue(preg_match('#<script[^>]*>[^<]*alert#i', $html) !== 1, $name . ': script が生成された');
        assertTrue(preg_match('/<(img|svg|iframe|input|style)[^>]*on[a-z]+\s*=/i', $html) !== 1,
            $name . ': イベント属性が生成された');
        assertTrue(preg_match('/(?:href|src)\s*=\s*"\s*(?:javascript|data|vbscript):/i', $html) !== 1,
            $name . ': 危険な scheme のリンクが生成された');
        // 詳細画面では、エスケープされた形で実際に表示されている（＝検査が空振りしていない）
        if ($name === 'detail') {
            assertTrue(str_contains($html, '&lt;script&gt;'), $name . ': エスケープ結果が見当たらない');
        }
    }

    // 一覧は回答本文を出さない（＝payload が届かない）ことも確認する
    $list = (string)$k->app->handle(adminGet('/admin/', ['cookies' => $login['cookie']]))->rawBody;
    assertTrue(!str_contains($list, MARK_ANSWER), '一覧へ回答本文が出ている');

    // 店舗応答は JSON（HTML ではない）
    $case = $k->app->handle(jsonGet('/case', ['cookies' => [Config::COOKIE_NAME => $secret]]));
    assertSame('application/json; charset=UTF-8', $case->headers['Content-Type'] ?? '');
});

test('XSS: 危険な scheme はリンクにならない（属性へ入らない）', function (): void {
    // View::link と dom.js の safeLink は https 以外をリンクにしない
    foreach ([
        'javascript:alert(1)', 'JavaScript:alert(1)', 'java\tscript:alert(1)',
        'data:text/html,<b>x</b>', 'vbscript:x', 'file:///etc/passwd',
        'http://evil.example/x', '//evil.example/x', ' https://evil.example',
    ] as $bad) {
        $html = \SmartLabo\Intake\Admin\View::link($bad);
        assertTrue(!str_contains($html, '<a '), 'リンクになってしまう: ' . $bad);
        assertTrue(!str_contains($html, 'href='), 'href が生まれる: ' . $bad);
    }

    // https だけがリンクになり、rel が付く
    $ok = \SmartLabo\Intake\Admin\View::link('https://drive.google.com/x');
    assertTrue(str_contains($ok, '<a href="https://drive.google.com/x"'), 'https がリンクにならない');
    assertTrue(str_contains($ok, 'rel="noopener noreferrer"'), 'rel が付いていない');
    assertTrue(str_contains($ok, 'target="_blank"'), 'target が付いていない');
});

test('XSS: 書き出しの Content-Disposition に注入できない', function (): void {
    $k = adminKernel();

    // ファイル名の正規化（引用符・改行・パス区切りを通さない）
    foreach ([
        "HP\"-2026\r\nSet-Cookie: a=b",
        'HP/../../etc/passwd',
        'HP-2026-0941"; filename="evil.html',
        "HP\n\nX-Evil: 1",
        '架空サロン',
    ] as $bad) {
        $name = $k->export->fileName($bad);
        assertTrue(preg_match('/^[A-Za-z0-9._-]+$/', $name) === 1, '危険な文字が残る: ' . $name);
    }

    // Response 側の正規化も同じ
    foreach (["a\"b", "a\r\nb", '../x', 'あ'] as $bad) {
        $safe = Response::safeFileName($bad);
        assertTrue(preg_match('/^[A-Za-z0-9._-]+$/', $safe) === 1, 'ヘッダーが壊れる: ' . $safe);
    }
});

/* ==================================================== 暗号化 */

test('暗号: AES-256-GCM の改ざんを検出する', function (): void {
    $crypto = new Crypto(TEST_ENC_KEY);
    $plain  = 'https://drive.google.com/drive/folders/' . MARK_ANSWER;

    $blob = $crypto->encrypt($plain);
    assertSame($plain, $crypto->decrypt($blob), '復号できない');

    // nonce（先頭12バイト）を1ビット変える
    $tampered    = $blob;
    $tampered[0] = chr(ord($tampered[0]) ^ 0x01);
    assertSame(null, $crypto->decrypt($tampered), 'nonce 改ざんを検出できない');

    // tag（12〜28バイト）を変える
    $tampered     = $blob;
    $tampered[12] = chr(ord($tampered[12]) ^ 0x01);
    assertSame(null, $crypto->decrypt($tampered), 'tag 改ざんを検出できない');

    // 暗号文本体を変える
    $tampered      = $blob;
    $last          = strlen($tampered) - 1;
    $tampered[$last] = chr(ord($tampered[$last]) ^ 0x01);
    assertSame(null, $crypto->decrypt($tampered), '本文の改ざんを検出できない');

    // 切り詰め
    assertSame(null, $crypto->decrypt(substr($blob, 0, 20)), '切り詰めを検出できない');
    assertSame(null, $crypto->decrypt(''), '空を復号している');

    // 別の鍵では復号できない
    $other = new Crypto('another-test-only-key-0123456789abcdef');
    assertSame(null, $other->decrypt($blob), '別の鍵で復号できてしまう');
});

test('暗号: nonce が毎回変わり、同じ平文でも暗号文が異なる', function (): void {
    $crypto = new Crypto(TEST_ENC_KEY);
    $plain  = 'https://drive.google.com/drive/folders/SAME';

    $seen = [];
    for ($i = 0; $i < 20; ++$i) {
        $blob   = $crypto->encrypt($plain);
        $nonce  = substr($blob, 0, 12);
        assertTrue(!isset($seen[$nonce]), 'nonce が再利用されている');
        $seen[$nonce] = true;
        assertSame($plain, $crypto->decrypt($blob), '復号できない');
    }
    assertSame(20, count($seen));
});

test('暗号: 鍵が短ければ起動しない（fail closed）', function (): void {
    foreach (['', 'short', str_repeat('a', 31)] as $weak) {
        $thrown = false;
        try {
            Config::load([
                'db_path'     => ':memory:',
                'ip_hmac_key' => TEST_IP_HMAC_KEY,
                'enc_key'     => $weak,
            ]);
        } catch (\SmartLabo\Intake\ConfigException $e) {
            $thrown = true;
        }
        assertTrue($thrown, '短い鍵で起動してしまう: ' . strlen($weak) . ' 文字');
    }
});

/* ==================================================== 状態遷移マトリクス */

test('状態: 遷移マトリクスが SSOT §5.1 と一致する', function (): void {
    // SSOT v1.6 §5.1 の表をそのまま書き下す
    $expected = [
        'draft'          => ['submitted', 'closed'],
        'submitted'      => ['needs_revision', 'reviewed', 'closed'],
        'needs_revision' => ['submitted', 'closed'],
        'reviewed'       => ['needs_revision', 'locked', 'closed'],
        'locked'         => ['closed'],
        'closed'         => [],
    ];

    foreach (CaseService::STATUSES as $from) {
        foreach (CaseService::STATUSES as $to) {
            $k      = makeKernel();
            $caseId = $k->cases->create('HP-2026-TRANS', '架空サロン');

            // from の状態を作る（許可された経路だけを通って到達する）
            $path = match ($from) {
                'draft'          => [],
                'submitted'      => ['submitted'],
                'needs_revision' => ['submitted', 'needs_revision'],
                'reviewed'       => ['submitted', 'reviewed'],
                'locked'         => ['submitted', 'reviewed', 'locked'],
                'closed'         => ['closed'],
            };
            foreach ($path as $step) {
                $k->cases->transitionTo($caseId, $step);
            }
            assertSame($from, (string)$k->cases->find($caseId)['status'], '前提の状態が作れない: ' . $from);

            $allowed = in_array($to, $expected[$from], true);
            $thrown  = false;
            try {
                $k->cases->transitionTo($caseId, $to);
            } catch (\DomainException $e) {
                $thrown = true;
            }

            assertSame(!$allowed, $thrown, $from . ' → ' . $to . ' の可否が SSOT と違う');
        }
    }
});

test('状態: 未知の状態からは何もできない', function (): void {
    $k      = makeKernel();
    $caseId = $k->cases->create('HP-2026-0950', '架空サロン');
    $k->db->pdo()->prepare('UPDATE intake_cases SET status = :s WHERE id = :id')
        ->execute([':s' => 'unknown_state', ':id' => $caseId]);

    foreach (CaseService::STATUSES as $to) {
        $thrown = false;
        try {
            $k->cases->transitionTo($caseId, $to);
        } catch (\DomainException $e) {
            $thrown = true;
        }
        assertTrue($thrown, '未知の状態から ' . $to . ' へ遷移できた');
    }

    // 管理操作もすべて拒否
    assertSame(false, $k->cases->adminChangeStatus($caseId, 'reviewed', 'reviewed')['ok']);
    assertSame(false, $k->cases->requestRevision($caseId, ['menus'], null, $k->revisions)['ok']);
    assertSame(false, $k->tokens->reissue($caseId, CaseService::REISSUABLE)['ok']);
});

/* ==================================================== 競合 */

test('競合: 失敗した操作は中間状態を残さない', function (): void {
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0960');

    $before = [
        'status'    => (string)$k->cases->find($caseId)['status'],
        'history'   => $k->answers->historyCount($caseId),
        'revisions' => $k->revisions->countFor($caseId),
        'audit'     => $k->audit->countFor($caseId, 'case_status_changed'),
    ];

    // 未知パスを含む修正依頼 → 何も起きない
    $r = $k->cases->requestRevision($caseId, ['basic.legal_name'], null, $k->revisions);
    assertSame(true, $r['ok'], '前提: 正常な依頼が通らない');

    // needs_revision になったので、もう一度 reviewed へは行けない
    $bad = $k->cases->adminChangeStatus($caseId, 'reviewed', 'reviewed');
    assertSame(false, $bad['ok'], '許可されない遷移が通った');
    assertSame('invalid_transition', $bad['error']);

    assertSame('needs_revision', (string)$k->cases->find($caseId)['status']);
    assertSame($before['history'] + 1, $k->answers->historyCount($caseId), '履歴が想定外');
    assertSame($before['revisions'] + 1, $k->revisions->countFor($caseId), '依頼が想定外');
    assertSame($before['audit'] + 1, $k->audit->countFor($caseId, 'case_status_changed'), '監査が想定外');
});

test('競合: 提出と修正依頼が同時でも二重にならない', function (): void {
    $k = adminKernel();
    [$caseId, $storeCookies] = submittedCase($k, 'HP-2026-0961');
    $login = loginAdmin($k);

    // 差し戻し → 店舗が再提出 → もう一度差し戻し
    $k->app->handle(revisionPost('HP-2026-0961', ['menus'], (string)$login['csrf'], null,
        ['cookies' => $login['cookie']]));
    $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $storeCookies));

    // 提出済みなので、同じ submission_id でない再提出は 409
    $dup = $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $storeCookies));
    assertSame(409, $dup->status, '提出済みへの再提出が通る');

    // 依頼はすべて resolved
    assertSame(0, $k->revisions->countFor($caseId, 'open'), '依頼が開いたまま');
    assertSame('submitted', (string)$k->cases->find($caseId)['status']);
});

test('競合: token 再発行の直後は、旧 session の保存が通らない', function (): void {
    $k = adminKernel();
    $caseId  = $k->cases->create('HP-2026-0962', '架空サロン');
    $token   = $k->tokens->issue($caseId);
    $secret  = $k->app->handle(jsonPost('/session/start', ['token' => $token]))->cookies[0]['value'];
    $cookies = ['cookies' => [Config::COOKIE_NAME => $secret]];
    $login   = loginAdmin($k);

    $save = $k->app->handle(jsonPost('/answers/save', [
        'version' => 1, 'sections' => ['basic' => ['legal_name' => MARK_ANSWER]],
    ], $cookies));
    assertSame(200, $save->status);

    $k->app->handle(reissuePost('HP-2026-0962', freshCsrf($k), null, ['cookies' => $login['cookie']]));

    // 失効した session では保存できない（回答は残る）
    $after = $k->app->handle(jsonPost('/answers/save', [
        'version' => 2, 'sections' => ['basic' => ['legal_name' => 'OVERWRITTEN']],
    ], $cookies));
    assertSame(404, $after->status, '失効 session で保存できた');
    assertSame(MARK_ANSWER, $k->answers->get($caseId)['sections']['basic']['legal_name'], '回答が壊れた');
});

/* ==================================================== rate limit */

test('rate limit: 全 bucket が定義され、fail closed で動く', function (): void {
    $expected = ['token_start', 'answer_save', 'submit', 'drive_confirm', 'admin_login', 'token_reissue'];
    assertSame($expected, array_keys(Config::RATE_LIMITS), 'bucket 一覧が想定と違う');

    foreach (Config::RATE_LIMITS as $bucket => [$limit, $window]) {
        assertTrue($limit > 0 && $limit <= 100, $bucket . ': 上限が不自然');
        assertTrue($window >= 60, $bucket . ': 期間が短すぎる');
    }

    // 未定義 bucket は通さない
    $k = makeKernel();
    assertSame(false, $k->rateLimiter->allow('no_such_bucket', 'x'), '未定義 bucket が通る');

    // 記録先が作れない場合も通さない（既存の**ファイル**をディレクトリとして指す）
    $blocker = tmpDir() . '/ratelimit-blocker';
    file_put_contents($blocker, 'not a directory');
    $broken = makeKernel(null, ['rate_limit_dir' => $blocker . '/sub']);
    assertSame(false, $broken->rateLimiter->allow('submit', 'x'), '記録できないのに通る（fail open）');
});

test('rate limit: 生IPをファイル名にも中身にも残さない', function (): void {
    $k  = makeKernel();
    $ip = '203.0.113.77';

    $k->rateLimiter->allow('submit', $k->rateLimiter->ipHmac($ip));

    foreach ((array)glob($k->config->rateLimitDir . '/*') as $path) {
        assertTrue(!str_contains(basename((string)$path), $ip), 'ファイル名に生IPがある');
        assertTrue(!str_contains((string)file_get_contents((string)$path), $ip), '中身に生IPがある');
        // 中身は数字（時刻）だけ
        assertTrue(preg_match('/^[0-9\n]*$/', (string)file_get_contents((string)$path)) === 1,
            '時刻以外が記録されている');
    }
});

test('rate limit: 境界値・分離が正しい', function (): void {
    $k = makeKernel();
    [$limit] = Config::RATE_LIMITS['submit'];

    for ($i = 0; $i < $limit; ++$i) {
        assertTrue($k->rateLimiter->allow('submit', 'ident-A'), ($i + 1) . '回目が拒否された');
    }
    assertTrue(!$k->rateLimiter->allow('submit', 'ident-A'), '上限を超えて通った');

    // 別の識別子・別の bucket は巻き込まれない
    assertTrue($k->rateLimiter->allow('submit', 'ident-B'), '別の識別子が巻き込まれた');
    assertTrue($k->rateLimiter->allow('answer_save', 'ident-A'), '別の bucket が巻き込まれた');
});

/* ==================================================== ログ・監査 */

test('ログ: 秘密値・PII が一切出ない（マーカーで実測）', function (): void {
    $k       = adminKernel();
    $caseId  = $k->cases->create('HP-2026-0970', MARK_SHOPNAME);
    $token   = $k->tokens->issue($caseId);
    $secret  = $k->app->handle(jsonPost('/session/start', ['token' => $token]))->cookies[0]['value'];
    $cookies = ['cookies' => [Config::COOKIE_NAME => $secret]];
    $k->cases->setDriveFolder($caseId, FAKE_DRIVE_URL, '素材', FAKE_DRIVE_EMAIL);

    $sections = completeSections();
    $sections['basic']['legal_name'] = MARK_ANSWER;
    $sid = newSubmissionId();

    $k->app->handle(jsonPost('/answers/save', ['version' => 1, 'sections' => $sections], $cookies));
    $k->app->handle(jsonPost('/drive/confirm', ['confirmed' => true], $cookies));
    $k->app->handle(jsonPost('/submit', ['submission_id' => $sid], $cookies));
    $k->app->handle(jsonGet('/case', $cookies));

    $login = loginAdmin($k);
    $k->app->handle(revisionPost('HP-2026-0970', ['basic.legal_name'], (string)$login['csrf'],
        MARK_MESSAGE, ['cookies' => $login['cookie']]));
    $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies));
    $k->app->handle(adminGet('/admin/export',
        ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-2026-0970']]));
    $k->app->handle(reissuePost('HP-2026-0970', freshCsrf($k), null, ['cookies' => $login['cookie']]));

    $log = (string)file_get_contents($k->config->logPath);
    assertTrue(strlen($log) > 200, 'ログが空に近い（検査が空振りしている）');

    $tokenHash   = (string)$k->db->pdo()->query('SELECT token_hash FROM intake_tokens LIMIT 1')->fetchColumn();
    $sessionHash = (string)$k->db->pdo()->query('SELECT session_hash FROM intake_sessions LIMIT 1')->fetchColumn();
    $adminHash   = (string)$k->db->pdo()->query('SELECT session_hash FROM intake_admin_sessions LIMIT 1')->fetchColumn();
    $csrfHash    = (string)$k->db->pdo()->query('SELECT csrf_hash FROM intake_admin_sessions LIMIT 1')->fetchColumn();

    $forbidden = [
        'token平文' => $token,
        'token hash' => $tokenHash,
        'session平文' => $secret,
        'session hash' => $sessionHash,
        '管理session hash' => $adminHash,
        'CSRF hash' => $csrfHash,
        'CSRF平文' => (string)$login['csrf'],
        'submission_id' => $sid,
        'Drive URL' => FAKE_DRIVE_URL,
        '共有メール' => FAKE_DRIVE_EMAIL,
        '修正メッセージ' => MARK_MESSAGE,
        '回答本文' => MARK_ANSWER,
        '店舗名' => MARK_SHOPNAME,
        '氏名' => 'ハルカゼ',
        '電話' => '03-0000-0000',
        'メール' => 'internal@example.invalid',
        '住所' => '架空県架空市',
        '管理者ID' => TEST_ADMIN_ID,
        'パスワード' => TEST_ADMIN_PASSWORD,
        'password hash' => (string)$k->config->adminPasswordHash,
        '暗号鍵' => TEST_ENC_KEY,
        'HMAC鍵' => TEST_IP_HMAC_KEY,
        '生IP' => '203.0.113.10',
        '対象パス' => 'basic.legal_name',
    ];
    foreach ($forbidden as $label => $needle) {
        assertTrue($needle !== '' && !str_contains($log, $needle), 'ログへ ' . $label . ' が出ている');
    }

    // 内部パス・SQL・stack trace も出さない
    foreach (['SELECT ', 'INSERT ', 'UPDATE ', '#0 ', '.php:', 'Stack trace'] as $leak) {
        assertTrue(!str_contains($log, $leak), 'ログへ ' . $leak . ' が出ている');
    }

    // 監査も同様
    $audit = '';
    foreach ($k->db->pdo()->query('SELECT * FROM intake_audit_events')->fetchAll() as $row) {
        $audit .= (string)json_encode($row, JSON_UNESCAPED_UNICODE);
    }
    foreach ($forbidden as $label => $needle) {
        if ($label === '生IP') continue; // 監査は HMAC 化した値を持つ
        assertTrue(!str_contains($audit, $needle), '監査へ ' . $label . ' が出ている');
    }
    assertTrue(!str_contains($audit, '203.0.113.10'), '監査へ生IPが出ている');
});

test('ログ: allowlist 外のキーは構造的に落ちる', function (): void {
    $logger = new \SmartLabo\Intake\Support\Logger();

    $line = $logger->format('info', 'submitted', [
        'case_number'     => 'HP-2026-0971',
        'token'           => 'SHOULD-NOT-APPEAR-1',
        'session'         => 'SHOULD-NOT-APPEAR-2',
        'message'         => 'SHOULD-NOT-APPEAR-3',
        'requested_paths' => ['SHOULD-NOT-APPEAR-4'],
        'submission_id'   => 'SHOULD-NOT-APPEAR-5',
        'drive_url'       => 'SHOULD-NOT-APPEAR-6',
        'password'        => 'SHOULD-NOT-APPEAR-7',
        'admin_id'        => 'SHOULD-NOT-APPEAR-8',
    ]);

    for ($i = 1; $i <= 8; ++$i) {
        assertTrue(!str_contains($line, 'SHOULD-NOT-APPEAR-' . $i), 'allowlist 外のキーが出ている: ' . $i);
    }
    assertTrue(str_contains($line, 'HP-2026-0971'), '許可キーまで落ちている');

    // allowlist に危険なキーが増えていない
    foreach (['token', 'session', 'message', 'requested_paths', 'submission_id',
              'drive_url', 'password', 'admin_id', 'csrf'] as $banned) {
        assertTrue(!in_array($banned, \SmartLabo\Intake\Support\Logger::ALLOWED, true),
            'allowlist へ ' . $banned . ' が入っている');
    }
});

/* ==================================================== ヘッダー */

test('ヘッダー: どの応答でも安全ヘッダーが付く（エラーも含む）', function (): void {
    $k = adminKernel();
    [, , $secret] = withSession('HP-2026-0980');

    $responses = [
        '200 JSON'  => $k->app->handle(jsonGet('/case', ['cookies' => [Config::COOKIE_NAME => $secret]])),
        '404 JSON'  => $k->app->handle(jsonGet('/unknown')),
        '403 JSON'  => $k->app->handle(jsonPost('/submit', [], ['origin' => 'https://evil.example'])),
        '405 JSON'  => $k->app->handle(jsonGet('/submit')),
        '401 HTML'  => $k->app->handle(adminPost('/admin/login', ['admin_id' => 'x', 'password' => 'y'])),
        '200 HTML'  => $k->app->handle(adminGet('/admin/login')),
    ];

    foreach ($responses as $label => $res) {
        assertSame('nosniff', $res->headers['X-Content-Type-Options'] ?? '', $label . ': nosniff が無い');
        assertSame('DENY', $res->headers['X-Frame-Options'] ?? '', $label . ': X-Frame-Options が無い');
        assertSame('no-referrer', $res->headers['Referrer-Policy'] ?? '', $label . ': Referrer-Policy が無い');
        assertTrue(str_contains($res->headers['Cache-Control'] ?? '', 'no-store'), $label . ': no-store が無い');
        assertTrue(str_contains($res->headers['Strict-Transport-Security'] ?? '', 'max-age'), $label . ': HSTS が無い');

        $csp = $res->headers['Content-Security-Policy'] ?? '';
        assertTrue(str_contains($csp, "frame-ancestors 'none'"), $label . ': frame-ancestors が無い');
        assertTrue(str_contains($csp, "default-src 'self'"), $label . ': default-src が無い');
        assertTrue(!str_contains($csp, 'unsafe-inline'), $label . ': unsafe-inline がある');
        assertTrue(!str_contains($csp, 'unsafe-eval'), $label . ': unsafe-eval がある');
        assertTrue(!str_contains($csp, '*'), $label . ': ワイルドカードがある');
    }
});

test('ヘッダー: 応答ヘッダーへ改行を注入できない', function (): void {
    $k = adminKernel();
    submittedCase($k, 'HP-2026-0981');
    $login = loginAdmin($k);

    $res = $k->app->handle(adminGet('/admin/export',
        ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-2026-0981']]));

    foreach ($res->headers as $name => $value) {
        assertTrue(!preg_match('/[\r\n]/', (string)$value), 'ヘッダーへ改行がある: ' . $name);
        assertTrue(!preg_match('/[\r\n]/', (string)$name), 'ヘッダー名へ改行がある');
    }
});

/* ==================================================== 書き出し */

test('export: allowlist の外側が1つも出ない（マーカーで実測）', function (): void {
    $k       = adminKernel();
    $caseId  = $k->cases->create('HP-2026-0990', MARK_SHOPNAME);
    $token   = $k->tokens->issue($caseId);
    $secret  = $k->app->handle(jsonPost('/session/start', ['token' => $token]))->cookies[0]['value'];
    $cookies = ['cookies' => [Config::COOKIE_NAME => $secret]];
    $k->cases->setDriveFolder($caseId, FAKE_DRIVE_URL, '素材', FAKE_DRIVE_EMAIL);

    $sid = newSubmissionId();
    $k->app->handle(jsonPost('/answers/save', ['version' => 1, 'sections' => completeSections()], $cookies));
    $k->app->handle(jsonPost('/submit', ['submission_id' => $sid], $cookies));
    // ★4F-R3: 書き出しには Smart Labo の制作設定も要る（SSOT v1.9 §3.12）
    setAdminSettings($k, $caseId);

    $login = loginAdmin($k);
    $k->app->handle(revisionPost('HP-2026-0990', ['basic.legal_name'], (string)$login['csrf'],
        MARK_MESSAGE, ['cookies' => $login['cookie']]));
    $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies));

    $res  = $k->app->handle(adminGet('/admin/export',
        ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-2026-0990']]));
    $body = (string)$res->rawBody;

    $tokenHash   = (string)$k->db->pdo()->query('SELECT token_hash FROM intake_tokens LIMIT 1')->fetchColumn();
    $sessionHash = (string)$k->db->pdo()->query('SELECT session_hash FROM intake_sessions LIMIT 1')->fetchColumn();

    foreach ([
        'token平文' => $token, 'token hash' => $tokenHash,
        'session平文' => $secret, 'session hash' => $sessionHash,
        'CSRF' => (string)$login['csrf'], 'submission_id' => $sid,
        'Drive URL' => FAKE_DRIVE_URL, '共有メール' => FAKE_DRIVE_EMAIL,
        '修正メッセージ' => MARK_MESSAGE, '店舗名' => MARK_SHOPNAME,
        '暗号鍵' => TEST_ENC_KEY, 'password hash' => (string)$k->config->adminPasswordHash,
    ] as $label => $needle) {
        assertTrue($needle !== '' && !str_contains($body, $needle), '書き出しへ ' . $label . ' が出ている');
    }

    // 内部ID・列名も出さない
    $json = json_decode($body, true);
    assertTrue(!array_key_exists('id', $json), '内部ID が出ている');
    foreach (['drive_folder_url_enc', 'drive_shared_email_enc', 'token_hash', 'ip_hmac', 'csrf_hash'] as $col) {
        assertTrue(!str_contains($body, $col), '列名 ' . $col . ' が出ている');
    }

    // 必要な PII（正式11分類）は入っている＝検査が空振りしていない
    assertTrue(str_contains($body, 'ハルカゼ'), '正式な回答が入っていない（検査が空振り）');
});

test('export: 認証が要る。未認証では取れない', function (): void {
    $k = adminKernel();
    submittedCase($k, 'HP-2026-0991');

    $res = $k->app->handle(adminGet('/admin/export', ['query' => ['case' => 'HP-2026-0991']]));
    assertSame(303, $res->status, '未認証で書き出せる');
    assertTrue(!str_contains((string)$res->rawBody, 'export_schema_version'), 'JSON が出ている');
});

/* ==================================================== 配置境界 */

test('配置: 公開領域へ秘密のファイルが無い', function (): void {
    $publicDir = __DIR__ . '/../public';
    $found     = [];

    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($publicDir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile()) continue;
        $name = strtolower($file->getFilename());
        $path = str_replace('\\', '/', $file->getPathname());

        foreach (['.sqlite', '.sqlite-journal', '.db', '.log', '.bak', '.env', '.pem', '.key', '~'] as $bad) {
            if (str_ends_with($name, $bad)) {
                $found[] = $path;
            }
        }
        if (str_contains($name, 'intake-config') || str_contains($name, 'preview-env')) {
            $found[] = $path;
        }
    }
    assertSame([], $found, '公開領域へ置いてはいけないファイルがある: ' . implode(', ', $found));

    // 想定どおりの構成であること
    $top = array_values(array_diff(scandir($publicDir), ['.', '..']));
    sort($top);
    assertSame(['.htaccess', '.user.ini', 'assets', 'form.html', 'index.php', 'start.html'], $top,
        '公開領域の構成が想定と違う');
});

test('配置: private / dev / tests が Git と Web から守られている', function (): void {
    $ignore = (string)file_get_contents(__DIR__ . '/../.gitignore');
    foreach (['private/intake-config.php', 'private/*.sqlite', 'dev/.preview/', 'tests/.tmp/', 'private/logs/'] as $rule) {
        assertTrue(str_contains($ignore, $rule), '.gitignore に ' . $rule . ' が無い');
    }

    // private/.htaccess が全拒否
    $deny = (string)file_get_contents(__DIR__ . '/../private/.htaccess');
    assertTrue(str_contains($deny, 'Require all denied'), 'private が全拒否になっていない');

    // 実設定・実鍵が追跡されていない
    assertTrue(!is_file(__DIR__ . '/../private/intake-config.php')
        || str_contains($ignore, 'private/intake-config.php'), '実設定が追跡され得る');

    // 雛形にはダミーしか無い
    $example = (string)file_get_contents(__DIR__ . '/../private/intake-config.example.php');
    assertTrue(str_contains($example, "'ip_hmac_key' => ''"), '雛形へ鍵が書かれている');
    assertTrue(str_contains($example, "'admin_password_hash' => ''"), '雛形へ hash が書かれている');
});

/* ==================================================== SQLite / migration */

test('SQLite: 接続ごとの PRAGMA と互換サブセット', function (): void {
    $k = makeKernel();

    assertTrue($k->db->foreignKeysOn(), 'foreign_keys が OFF');
    assertSame('delete', $k->db->journalMode(), 'journal_mode が delete でない');
    assertTrue((int)$k->db->pdo()->query('PRAGMA busy_timeout')->fetchColumn() >= 5000, 'busy_timeout が短い');

    // 実行しうる DDL すべてに禁止構文が無い
    $sql = strtoupper(implode(' ', Migrator::allStatements()));
    foreach (['VACUUM INTO', 'RETURNING', ') STRICT', 'DROP COLUMN', 'GENERATED ALWAYS',
              'JSON_EXTRACT', 'JSON_EACH', 'JSON_SET', 'JSON_ARRAY'] as $banned) {
        assertTrue(!str_contains($sql, $banned), 'DDL に ' . $banned . ' がある');
    }

    // 整合性
    $integrity = $k->db->integrity();
    assertSame('ok', $integrity['integrity'], 'integrity_check が ok でない');
    assertSame(0, $integrity['foreign_key_violations'], 'foreign_key 違反がある');
});

test('SQLite: 平文の秘密値を保存する列が無い', function (): void {
    $k = makeKernel();

    $tables = array_column($k->db->pdo()->query(
        "SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'intake_%'"
    )->fetchAll(), 'name');
    assertSame(8, count($tables), 'テーブル数が 8 でない');

    foreach ($tables as $table) {
        foreach (array_column($k->db->pdo()->query("PRAGMA table_info('{$table}')")->fetchAll(), 'name') as $col) {
            $lower = strtolower($col);
            // hash / enc で終わるものは可。それ以外で秘密を示す名前は不可
            $isProtected = str_ends_with($lower, '_hash') || str_ends_with($lower, '_enc');
            foreach (['token', 'secret', 'password', 'csrf', 'session'] as $risky) {
                if (str_contains($lower, $risky) && !$isProtected) {
                    // 例外: intake_case_id 等の無関係な語を誤検出しないよう、列名を明示で許可する
                    $allowed = ['token_id', 'intake_case_id'];
                    assertTrue(in_array($lower, $allowed, true),
                        $table . '.' . $col . ' が平文の秘密値に見える');
                }
            }
        }
    }
});

test('migration: 0 から最新まで通し、再実行しても壊れない', function (): void {
    $k   = makeKernel();
    $pdo = $k->db->pdo();

    // すべて捨てて 0 から作り直す
    foreach ($pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'intake_%'")->fetchAll() as $r) {
        $pdo->exec('DROP TABLE IF EXISTS ' . $r['name']);
    }
    $pdo->exec('PRAGMA user_version = 0');

    (new Migrator($k->db))->migrate();
    assertSame(Migrator::SCHEMA_VERSION, (int)$pdo->query('PRAGMA user_version')->fetchColumn());
    assertSame(8, (int)$pdo->query(
        "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name LIKE 'intake_%'"
    )->fetchColumn(), '8表が作られない');

    // 途中版からの適用
    foreach ([1, 2, 3] as $from) {
        $pdo->exec('PRAGMA user_version = ' . $from);
        (new Migrator($k->db))->migrate();
        assertSame(Migrator::SCHEMA_VERSION, (int)$pdo->query('PRAGMA user_version')->fetchColumn(),
            'v' . $from . ' から上げられない');
    }

    // 何度実行しても同じ
    $before = $pdo->query("SELECT group_concat(name || '|' || COALESCE(sql,'')) FROM sqlite_master ORDER BY name")->fetchColumn();
    for ($i = 0; $i < 3; ++$i) {
        (new Migrator($k->db))->migrate();
    }
    assertSame($before, $pdo->query("SELECT group_concat(name || '|' || COALESCE(sql,'')) FROM sqlite_master ORDER BY name")->fetchColumn(),
        '再実行でスキーマが変わった');
});

/* ==================================================== backup */

test('backup: 取得したファイルに平文の秘密値が無い', function (): void {
    $k       = adminKernel();
    $caseId  = $k->cases->create('HP-2026-0995', MARK_SHOPNAME);
    $token   = $k->tokens->issue($caseId);
    $secret  = $k->app->handle(jsonPost('/session/start', ['token' => $token]))->cookies[0]['value'];
    $k->cases->setDriveFolder($caseId, FAKE_DRIVE_URL, '素材', FAKE_DRIVE_EMAIL);
    $login = loginAdmin($k);

    $dir  = dirname($k->config->dbPath) . '/backup';
    @mkdir($dir, 0700, true);
    $dest = $dir . '/audit-backup.sqlite';

    $backup = new \SmartLabo\Intake\Backup\SqliteBackup($k->db);
    $result = $backup->backupTo($dest);
    assertSame(true, $result['ok'], 'バックアップが取れない: ' . json_encode($result));
    assertSame('ok', $result['integrity'], 'integrity_check が ok でない');
    assertSame(0, $result['foreign_key_violations'], 'foreign_key 違反がある');

    $raw = (string)file_get_contents($dest);
    foreach ([
        'token平文' => $token,
        'session平文' => $secret,
        'CSRF平文' => (string)$login['csrf'],
        'Drive URL' => FAKE_DRIVE_URL,
        '共有メール' => FAKE_DRIVE_EMAIL,
        '暗号鍵' => TEST_ENC_KEY,
        'パスワード' => TEST_ADMIN_PASSWORD,
    ] as $label => $needle) {
        assertTrue($needle !== '' && !str_contains($raw, $needle), 'バックアップへ ' . $label . ' が出ている');
    }

    // 案件番号は入っている（＝中身のあるバックアップである）
    assertTrue(str_contains($raw, 'HP-2026-0995'), 'バックアップが空（検査が空振り）');

    // 公開領域の外にある
    assertTrue(!str_contains(str_replace('\\', '/', $dest), '/public/'), 'バックアップが公開領域にある');
});
