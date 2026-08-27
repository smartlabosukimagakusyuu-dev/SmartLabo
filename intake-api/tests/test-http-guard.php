<?php
/**
 * 入口検査のテスト（SSOT §4.5 / §10.2 / §10.4）
 */
declare(strict_types=1);

use SmartLabo\Intake\Config;
use SmartLabo\Intake\Support\Secret;

test('guard: GET で /session/start は 405', function (): void {
    $k   = makeKernel();
    $res = $k->app->handle(jsonPost('/session/start', ['token' => Secret::generate()], ['method' => 'GET']));
    assertSame(405, $res->status);
    assertSame('method_not_allowed', $res->body['error']);
});

test('guard: HTTPS でない要求は 403', function (): void {
    $k   = makeKernel();
    $res = $k->app->handle(jsonPost('/session/start', ['token' => Secret::generate()], ['https' => false]));
    assertSame(403, $res->status);
    assertSame('forbidden', $res->body['error']);
});

test('guard: Content-Type が application/json でなければ 400', function (): void {
    $k   = makeKernel();
    $res = $k->app->handle(jsonPost('/session/start', ['token' => Secret::generate()], [
        'content_type' => 'application/x-www-form-urlencoded',
    ]));
    assertSame(400, $res->status);
    assertSame('bad_request', $res->body['error']);
});

test('guard: Origin 不一致は 403', function (): void {
    $k = makeKernel();
    foreach (['https://evil.example', 'https://smartlaboworks.com', 'http://intake.smartlaboworks.com'] as $origin) {
        $res = $k->app->handle(jsonPost('/session/start', ['token' => Secret::generate()], ['origin' => $origin]));
        assertSame(403, $res->status, $origin . ' が拒否されない');
    }
});

test('guard: Origin も Referer も無い要求は拒否（fail closed）', function (): void {
    $k   = makeKernel();
    $res = $k->app->handle(jsonPost('/session/start', ['token' => Secret::generate()], ['no_origin' => true]));
    assertSame(403, $res->status);
});

test('guard: 1MB を超える body は 413', function (): void {
    $k    = makeKernel();
    $big  = str_repeat('a', Config::MAX_BODY_BYTES + 10);
    $res  = $k->app->handle(jsonPost('/session/start', [], ['raw_body' => $big]));
    assertSame(413, $res->status);
    assertSame('payload_too_large', $res->body['error']);
});

test('guard: 壊れた JSON は 400', function (): void {
    $k   = makeKernel();
    $res = $k->app->handle(jsonPost('/session/start', [], ['raw_body' => '{"token": ']));
    assertSame(400, $res->status);
    assertSame('bad_request', $res->body['error']);
});

test('guard: CORS ヘッダーを一切返さない', function (): void {
    $k      = makeKernel();
    $caseId = $k->cases->create('HP-2026-0200', '架空サロン');
    $token  = $k->tokens->issue($caseId);
    $res    = $k->app->handle(jsonPost('/session/start', ['token' => $token]));

    foreach (array_keys($res->headers) as $name) {
        assertTrue(
            !str_starts_with(strtolower($name), 'access-control-'),
            'CORS ヘッダー ' . $name . ' を返している'
        );
    }
});

test('guard: セキュリティヘッダーが付く（SSOT §10.4）', function (): void {
    $k   = makeKernel();
    $res = $k->app->handle(jsonPost('/session/start', ['token' => Secret::generate()]));
    $h   = $res->headers;

    assertSame('nosniff', $h['X-Content-Type-Options']);
    assertSame('DENY', $h['X-Frame-Options']);
    assertSame('no-referrer', $h['Referrer-Policy']);
    assertSame('no-store, no-cache, must-revalidate', $h['Cache-Control']);
    assertTrue(str_contains($h['Content-Security-Policy'], "default-src 'self'"), 'CSP が既定 self でない');
    assertTrue(str_contains($h['Content-Security-Policy'], "frame-ancestors 'none'"), 'frame-ancestors が none でない');
});

test('guard: 未知のパスは 404 同一応答', function (): void {
    $k   = makeKernel();
    $res = $k->app->handle(jsonGet('/unknown/path'));
    assertSame(404, $res->status);
    assertSame('unavailable', $res->body['error']);
});

test('rate limit: 無効token試行は 10分5回で 429', function (): void {
    $k = makeKernel();
    for ($i = 0; $i < 5; ++$i) {
        $res = $k->app->handle(jsonPost('/session/start', ['token' => Secret::generate()], ['ip' => '198.51.100.77']));
        assertSame(404, $res->status, ($i + 1) . '回目が404でない');
    }
    $res = $k->app->handle(jsonPost('/session/start', ['token' => Secret::generate()], ['ip' => '198.51.100.77']));
    assertSame(429, $res->status, '6回目が429でない');
    assertSame('rate_limited', $res->body['error']);
});

test('rate limit: IP が違えば独立して数える', function (): void {
    $k = makeKernel();
    for ($i = 0; $i < 5; ++$i) {
        $k->app->handle(jsonPost('/session/start', ['token' => Secret::generate()], ['ip' => '198.51.100.88']));
    }
    $res = $k->app->handle(jsonPost('/session/start', ['token' => Secret::generate()], ['ip' => '198.51.100.89']));
    assertSame(404, $res->status, '別IPが巻き添えで429になっている');
});

test('rate limit: 生IPをファイル名・内容へ保存しない', function (): void {
    $k  = makeKernel();
    $ip = '198.51.100.99';
    $k->app->handle(jsonPost('/session/start', ['token' => Secret::generate()], ['ip' => $ip]));

    $dir = $k->config->rateLimitDir;
    foreach ((array)glob($dir . '/*') as $file) {
        assertTrue(!str_contains(basename((string)$file), $ip), 'ファイル名に生IPが含まれる');
        assertTrue(!str_contains((string)file_get_contents((string)$file), $ip), '内容に生IPが含まれる');
    }
});

test('rate limit: 未定義バケットは通さない（fail closed）', function (): void {
    $k = makeKernel();
    assertSame(false, $k->rateLimiter->allow('no_such_bucket', 'x'));
});

test('config: HMAC鍵・暗号鍵が未設定なら起動しない（fail closed）', function (): void {
    foreach (['ip_hmac_key', 'enc_key'] as $key) {
        $thrown = false;
        try {
            \SmartLabo\Intake\Config::load([
                'db_path'     => ':memory:',
                'ip_hmac_key' => $key === 'ip_hmac_key' ? '' : TEST_IP_HMAC_KEY,
                'enc_key'     => $key === 'enc_key' ? '' : TEST_ENC_KEY,
            ]);
        } catch (\SmartLabo\Intake\ConfigException $e) {
            $thrown = true;
        }
        assertTrue($thrown, $key . ' 未設定でも起動してしまう');
    }
});

test('config: 許可オリジンに https 以外を設定できない', function (): void {
    $thrown = false;
    try {
        \SmartLabo\Intake\Config::load([
            'db_path'         => ':memory:',
            'ip_hmac_key'     => TEST_IP_HMAC_KEY,
            'enc_key'         => TEST_ENC_KEY,
            'allowed_origins' => ['http://intake.smartlaboworks.com'],
        ]);
    } catch (\SmartLabo\Intake\ConfigException $e) {
        $thrown = true;
    }
    assertTrue($thrown, 'http のオリジンが許可されてしまう');
});
