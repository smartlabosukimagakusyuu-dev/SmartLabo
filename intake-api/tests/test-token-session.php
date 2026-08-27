<?php
/**
 * token / session のテスト（SSOT §2.2 / §2.6 / §4 / §5）
 */
declare(strict_types=1);

use SmartLabo\Intake\Config;
use SmartLabo\Intake\Http\Response;
use SmartLabo\Intake\Support\Secret;

/** session/start を1回通して Cookie 値を得る */
function startSession(\SmartLabo\Intake\Kernel $k, string $token, array $opts = []): Response
{
    return $k->app->handle(jsonPost('/session/start', ['token' => $token], $opts));
}

test('token: 生成は base64url 43文字・DBには hash のみ', function (): void {
    $k      = makeKernel();
    $caseId = $k->cases->create('HP-2026-0100', '架空サロン');
    $plain  = $k->tokens->issue($caseId);

    assertSame(43, strlen($plain), 'token 長が43文字でない');
    assertTrue(Secret::isWellFormed($plain), 'base64url でない');

    $row = $k->db->pdo()->query('SELECT * FROM intake_tokens LIMIT 1')->fetch();
    assertSame(hash('sha256', $plain), $row['token_hash'], 'hash が一致しない');
    assertTrue(!in_array($plain, array_map('strval', array_values($row)), true), '平文が保存されている');
});

test('token: 有効期限は14日', function (): void {
    $k      = makeKernel();
    $caseId = $k->cases->create('HP-2026-0101', '架空サロン');
    $k->tokens->issue($caseId);

    $expires = strtotime((string)$k->db->pdo()->query('SELECT expires_at FROM intake_tokens')->fetchColumn());
    $diff    = $expires - time();
    assertTrue(abs($diff - Config::TOKEN_TTL) < 120, '有効期限が14日でない');
});

test('token: 1案件につき有効tokenは常に1本（再発行で旧tokenを失効）', function (): void {
    $k      = makeKernel();
    $caseId = $k->cases->create('HP-2026-0102', '架空サロン');
    $old    = $k->tokens->issue($caseId);
    $new    = $k->tokens->issue($caseId);

    assertSame(1, $k->tokens->activeCount($caseId), '有効tokenが1本でない');
    assertSame(null, $k->tokens->verify($old)['row'], '旧tokenが通ってしまう');
    assertSame('revoked', $k->tokens->verify($old)['reason']);
    assertTrue($k->tokens->verify($new)['row'] !== null, '新tokenが通らない');
});

test('session: 有効tokenで session が発行される', function (): void {
    $k      = makeKernel();
    $caseId = $k->cases->create('HP-2026-0103', '架空サロン');
    $token  = $k->tokens->issue($caseId);

    $res = startSession($k, $token);
    assertSame(200, $res->status);
    assertSame(true, $res->body['ok']);
    assertSame('HP-2026-0103', $res->body['case_number']);
    assertSame(1, count($res->cookies), 'Cookie が設定されていない');
});

test('session: Cookie属性が Secure / HttpOnly / SameSite=Strict、名前に店舗名・案件番号を含まない', function (): void {
    $k      = makeKernel();
    $caseId = $k->cases->create('HP-2026-0104', '架空サロン');
    $token  = $k->tokens->issue($caseId);

    $cookie = startSession($k, $token)->cookies[0];
    assertSame(Config::COOKIE_NAME, $cookie['name']);
    assertSame(true, $cookie['attributes']['Secure']);
    assertSame(true, $cookie['attributes']['HttpOnly']);
    assertSame('Strict', $cookie['attributes']['SameSite']);
    assertTrue(!str_contains($cookie['name'], 'HP-2026'), 'Cookie名に案件番号が含まれる');
    assertTrue(!str_contains($cookie['name'], '架空'), 'Cookie名に店舗名が含まれる');
});

test('session: DBには session hash のみ（平文はCookieだけ）', function (): void {
    $k      = makeKernel();
    $caseId = $k->cases->create('HP-2026-0105', '架空サロン');
    $token  = $k->tokens->issue($caseId);
    $secret = startSession($k, $token)->cookies[0]['value'];

    $row = $k->db->pdo()->query('SELECT * FROM intake_sessions LIMIT 1')->fetch();
    assertSame(hash('sha256', $secret), $row['session_hash']);
    assertTrue(!in_array($secret, array_map('strval', array_values($row)), true), '平文が保存されている');
});

test('token: 応答本文へ token / session secret を再掲しない', function (): void {
    $k      = makeKernel();
    $caseId = $k->cases->create('HP-2026-0106', '架空サロン');
    $token  = $k->tokens->issue($caseId);

    $res    = startSession($k, $token);
    $secret = $res->cookies[0]['value'];
    $json   = $res->json();

    assertTrue(!str_contains($json, $token), '応答に token が含まれる');
    assertTrue(!str_contains($json, $secret), '応答に session secret が含まれる');
});

test('token: 不正・不存在・期限切れ・失効は同じ応答（404・同一文言）', function (): void {
    $k      = makeKernel();
    $caseId = $k->cases->create('HP-2026-0107', '架空サロン');
    $valid  = $k->tokens->issue($caseId);

    $bodies = [];

    // 形式不正
    $bodies[] = startSession($k, str_repeat('!', 43), ['ip' => '198.51.100.1'])->body;
    // 不存在（形式は正しい）
    $bodies[] = startSession($k, Secret::generate(), ['ip' => '198.51.100.2'])->body;
    // 失効
    $k->tokens->revokeAllForCase($caseId);
    $bodies[] = startSession($k, $valid, ['ip' => '198.51.100.3'])->body;

    foreach ($bodies as $b) {
        assertSame(false, $b['ok']);
        assertSame('unavailable', $b['error']);
        assertSame(Response::UNAVAILABLE_MESSAGE, $b['message']);
    }
    assertSame($bodies[0], $bodies[1], '応答が区別できてしまう');
    assertSame($bodies[1], $bodies[2], '応答が区別できてしまう');
});

test('token: 期限切れ token は拒否される', function (): void {
    $clock  = new TestClock();
    $k      = makeKernel($clock);
    $caseId = $k->cases->create('HP-2026-0108', '架空サロン');
    $token  = $k->tokens->issue($caseId);

    $clock->advance(Config::TOKEN_TTL + 60);
    assertSame('expired', $k->tokens->verify($token)['reason']);
    assertSame(404, startSession($k, $token)->status);
});

test('session: token 再発行で旧 session が失効する', function (): void {
    $k      = makeKernel();
    $caseId = $k->cases->create('HP-2026-0109', '架空サロン');
    $token  = $k->tokens->issue($caseId);
    $secret = startSession($k, $token)->cookies[0]['value'];

    assertTrue($k->sessions->verify($secret)['row'] !== null, '発行直後に無効');

    $k->tokens->issue($caseId); // 再発行
    assertSame(null, $k->sessions->verify($secret)['row'], '旧 session が生きている');
    assertSame(0, $k->sessions->activeCount($caseId), '有効 session が残っている');
});

test('session: 最終利用から24時間で期限切れ', function (): void {
    $clock  = new TestClock();
    $k      = makeKernel($clock);
    $caseId = $k->cases->create('HP-2026-0110', '架空サロン');
    $token  = $k->tokens->issue($caseId);
    $secret = startSession($k, $token)->cookies[0]['value'];

    $clock->advance(Config::SESSION_IDLE_TTL + 60);
    assertSame('expired', $k->sessions->verify($secret)['reason']);
});

test('session: 利用のたびに idle 期限が延び、絶対期限7日は延びない', function (): void {
    $clock  = new TestClock();
    $k      = makeKernel($clock);
    $caseId = $k->cases->create('HP-2026-0111', '架空サロン');
    $token  = $k->tokens->issue($caseId);
    $secret = startSession($k, $token)->cookies[0]['value'];

    // 12時間ごとに触り続けて idle 期限を延ばす（合計7日を超える）
    for ($i = 0; $i < 15; ++$i) {
        $clock->advance(12 * 3600);
        $res = $k->app->handle(jsonGet('/case', ['cookies' => [Config::COOKIE_NAME => $secret]]));
        if ($res->status !== 200) {
            break;
        }
    }

    $verified = $k->sessions->verify($secret);
    assertSame(null, $verified['row'], '絶対期限を過ぎても使えてしまう');
    assertSame('absolute_expired', $verified['reason'], '絶対期限で止まっていない');
});

test('session: locked / closed で全 session と token が失効する', function (): void {
    foreach (['locked', 'closed'] as $target) {
        $k      = makeKernel();
        $caseId = $k->cases->create('HP-2026-01' . ($target === 'locked' ? '12' : '13'), '架空サロン');
        $token  = $k->tokens->issue($caseId);
        $secret = startSession($k, $token)->cookies[0]['value'];

        if ($target === 'locked') {
            $k->cases->transitionTo($caseId, 'submitted');
            $k->cases->transitionTo($caseId, 'reviewed');
            $k->cases->transitionTo($caseId, 'locked');
        } else {
            $k->cases->transitionTo($caseId, 'closed');
        }

        assertSame(0, $k->sessions->activeCount($caseId), $target . ' で session が残っている');
        assertSame(0, $k->tokens->activeCount($caseId), $target . ' で token が残っている');

        $res = $k->app->handle(jsonGet('/case', ['cookies' => [Config::COOKIE_NAME => $secret]]));
        assertSame(404, $res->status, $target . ' 後もアクセスできてしまう');
        assertSame('unavailable', $res->body['error']);
    }
});

test('session: logout で個別に失効できる', function (): void {
    $k      = makeKernel();
    $caseId = $k->cases->create('HP-2026-0114', '架空サロン');
    $token  = $k->tokens->issue($caseId);
    $secret = startSession($k, $token)->cookies[0]['value'];

    $res = $k->app->handle(jsonPost('/session/logout', [], ['cookies' => [Config::COOKIE_NAME => $secret]]));
    assertSame(200, $res->status);
    assertSame(true, $res->body['logged_out']);
    assertSame(null, $k->sessions->verify($secret)['row'], 'logout 後も使える');

    // token 自体は生きている（再度 /start で入り直せる）
    assertTrue($k->tokens->verify($token)['row'] !== null, 'logout で token まで失効している');
});

test('session: Cookie が無い・偽の値は 404 同一応答', function (): void {
    $k = makeKernel();
    foreach ([[], [Config::COOKIE_NAME => 'x'], [Config::COOKIE_NAME => \SmartLabo\Intake\Support\Secret::generate()]] as $cookies) {
        $res = $k->app->handle(jsonGet('/case', ['cookies' => $cookies]));
        assertSame(404, $res->status);
        assertSame('unavailable', $res->body['error']);
        assertSame(Response::UNAVAILABLE_MESSAGE, $res->body['message']);
    }
});
