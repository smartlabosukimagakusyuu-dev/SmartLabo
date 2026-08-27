<?php
/**
 * ご案内リンクの再発行（SSOT v1.6 §4.4.1 / §2.5）
 * HP-ONBOARDING-4D-R2
 */
declare(strict_types=1);

use SmartLabo\Intake\Config;
use SmartLabo\Intake\Http\Request;
use SmartLabo\Intake\Service\CaseService;
use SmartLabo\Intake\Service\RevisionRequestService;

/** 再発行の form POST */
function reissuePost(string $caseNumber, string $csrf, ?string $confirm = null, array $opts = []): Request
{
    $fields = [
        'csrf_token'   => $csrf,
        'case'         => $caseNumber,
        'confirm_case' => $confirm ?? $caseNumber,
    ];

    $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];
    if (($opts['no_origin'] ?? false) !== true) {
        $headers['Origin'] = $opts['origin'] ?? TEST_ORIGIN;
    }
    foreach (($opts['headers'] ?? []) as $k => $v) {
        $headers[$k] = $v;
    }

    return new Request(
        method: $opts['method'] ?? 'POST',
        path: '/admin/reissue/send',
        headers: $headers,
        body: http_build_query($fields),
        cookies: $opts['cookies'] ?? [],
        isHttps: true,
        clientIp: $opts['ip'] ?? '203.0.113.10',
    );
}

/** 応答画面から新しいご案内リンクの token を取り出す */
function tokenFromPage(string $html): ?string
{
    return preg_match('#/start\#([A-Za-z0-9_-]{43})#', $html, $m) === 1 ? $m[1] : null;
}

/** draft の案件と、店舗の session をひとそろい作る */
function reissuableCase(object $k, string $caseNumber): array
{
    $caseId  = $k->cases->create($caseNumber, '架空サロン ハルカゼ');
    $token   = $k->tokens->issue($caseId);
    $secret  = $k->app->handle(jsonPost('/session/start', ['token' => $token]))->cookies[0]['value'];
    $cookies = ['cookies' => [Config::COOKIE_NAME => $secret]];

    return [$caseId, $token, $secret, $cookies];
}

/* ==================================================== 対象状態 */

test('再発行: draft で再発行できる', function (): void {
    $k = adminKernel();
    [$caseId] = reissuableCase($k, 'HP-2026-0800');
    $login = loginAdmin($k);

    assertSame('draft', (string)$k->cases->find($caseId)['status']);

    $res = $k->app->handle(reissuePost('HP-2026-0800', freshCsrf($k), null, ['cookies' => $login['cookie']]));

    assertSame(200, $res->status, '再発行できない');
    assertTrue(tokenFromPage((string)$res->rawBody) !== null, '新しいリンクが出ていない');
    assertSame(1, $k->audit->countFor($caseId, 'token_reissued'), '監査が1件でない');
});

test('再発行: needs_revision で再発行できる', function (): void {
    $k = adminKernel();
    [$caseId, $storeCookies] = submittedCase($k, 'HP-2026-0801');
    $login = loginAdmin($k);

    $k->app->handle(revisionPost('HP-2026-0801', ['basic.legal_name'], (string)$login['csrf'],
        null, ['cookies' => $login['cookie']]));
    assertSame('needs_revision', (string)$k->cases->find($caseId)['status']);

    $res = $k->app->handle(reissuePost('HP-2026-0801', freshCsrf($k), null, ['cookies' => $login['cookie']]));

    assertSame(200, $res->status, '差し戻し中に再発行できない');
    assertTrue(tokenFromPage((string)$res->rawBody) !== null, '新しいリンクが出ていない');
    assertTrue($storeCookies !== null);
});

test('再発行: submitted / reviewed / locked / closed では拒否する', function (): void {
    foreach ([
        ['HP-2026-0810', 'submitted'],
        ['HP-2026-0811', 'reviewed'],
        ['HP-2026-0812', 'locked'],
        ['HP-2026-0813', 'closed'],
    ] as [$number, $target]) {
        $k = adminKernel();
        [$caseId] = submittedCase($k, $number);
        $login = loginAdmin($k);

        if ($target === 'reviewed') {
            $k->cases->transitionTo($caseId, 'reviewed');
        } elseif ($target === 'locked') {
            $k->cases->transitionTo($caseId, 'reviewed');
            $k->cases->transitionTo($caseId, 'locked');
        } elseif ($target === 'closed') {
            $k->cases->transitionTo($caseId, 'closed');
        }
        assertSame($target, (string)$k->cases->find($caseId)['status'], '前提の状態が作れていない');

        $before = (int)$k->db->pdo()->query('SELECT COUNT(*) FROM intake_tokens')->fetchColumn();

        $res = $k->app->handle(reissuePost($number, freshCsrf($k), null, ['cookies' => $login['cookie']]));

        assertSame(409, $res->status, $target . ' で再発行できてしまう');
        assertTrue(tokenFromPage((string)$res->rawBody) === null, $target . ' でリンクが出ている');
        assertSame($before, (int)$k->db->pdo()->query('SELECT COUNT(*) FROM intake_tokens')->fetchColumn(),
            $target . ' で token が増えている');
        assertSame(0, $k->audit->countFor($caseId, 'token_reissued'), $target . ' で監査が増えている');
    }
});

test('再発行: 未知の状態では拒否する（fail closed）', function (): void {
    $k = adminKernel();
    [$caseId] = reissuableCase($k, 'HP-2026-0814');
    $login = loginAdmin($k);

    // DB を直接いじって、想定外の状態を作る
    $k->db->pdo()->prepare('UPDATE intake_cases SET status = :s WHERE id = :id')
        ->execute([':s' => 'unknown_state', ':id' => $caseId]);

    $res = $k->app->handle(reissuePost('HP-2026-0814', freshCsrf($k), null, ['cookies' => $login['cookie']]));

    assertSame(409, $res->status, '未知の状態で再発行できてしまう');
    assertSame(0, $k->audit->countFor($caseId, 'token_reissued'));

    // 許可する状態はこの2つだけ
    assertSame(['draft', 'needs_revision'], CaseService::REISSUABLE);
});

test('再発行: 画面のボタンは draft / needs_revision だけに出す', function (): void {
    $k = adminKernel();
    [$caseId] = reissuableCase($k, 'HP-2026-0815');
    $login = loginAdmin($k);

    $html = (string)$k->app->handle(adminGet('/admin/case',
        ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-2026-0815']]))->rawBody;
    assertTrue(str_contains($html, '店舗入力リンクを再発行'), 'draft でボタンが出ていない');

    // 提出させてから見ると出ない
    $token   = $k->tokens->issue($caseId);
    $secret  = $k->app->handle(jsonPost('/session/start', ['token' => $token]))->cookies[0]['value'];
    $cookies = ['cookies' => [Config::COOKIE_NAME => $secret]];
    $k->app->handle(jsonPost('/answers/save', ['version' => 1, 'sections' => completeSections()], $cookies));
    $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies));

    $html = (string)$k->app->handle(adminGet('/admin/case',
        ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-2026-0815']]))->rawBody;
    assertTrue(!str_contains($html, '店舗入力リンクを再発行'), 'submitted でボタンが出ている');
});

test('再発行: 確認画面も submitted 以降では出さない', function (): void {
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0816');
    $login = loginAdmin($k);

    $res = $k->app->handle(adminGet('/admin/reissue',
        ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-2026-0816']]));

    assertSame(409, $res->status, 'submitted でも確認画面が出る');
    assertTrue($caseId > 0);
});

/* ==================================================== 確認画面 */

test('確認画面: 何が起きるかを先に見せる', function (): void {
    $k = adminKernel();
    reissuableCase($k, 'HP-2026-0820');
    $login = loginAdmin($k);

    $html = (string)$k->app->handle(adminGet('/admin/reissue',
        ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-2026-0820']]))->rawBody;

    assertTrue(str_contains($html, 'HP-2026-0820'), '案件番号が出ていない');
    assertTrue(str_contains($html, '入力中'), '現在の状態が出ていない');
    assertTrue(str_contains($html, 'これまでのご案内リンクは使えなくなります'), '旧リンク失効の説明が無い');
    assertTrue(str_contains($html, 'その場で使えなくなります'), '店舗 session 失効の説明が無い');
    assertTrue(str_contains($html, '入力済みの内容は消えません'), '回答が残る説明が無い');
    assertTrue(str_contains($html, '次の画面で1回だけ'), '1回だけ表示の説明が無い');

    // 案件番号の再入力欄。autocomplete を切る
    assertTrue(str_contains($html, 'name="confirm_case"'), '再入力欄が無い');
    assertTrue(preg_match('/id="confirm-case"[^>]*autocomplete="off"/', $html) === 1, 'autocomplete が切れていない');

    // ★確認画面には token を出さない
    assertTrue(tokenFromPage($html) === null, '確認画面にリンクが出ている');
});

test('確認画面: 案件番号が一致しなければ実行しない', function (): void {
    $k = adminKernel();
    [$caseId, $oldToken] = reissuableCase($k, 'HP-2026-0821');
    $login = loginAdmin($k);

    foreach (['HP-2026-0822', 'hp-2026-0821', ' HP-2026-0821', ''] as $wrong) {
        $res = $k->app->handle(reissuePost('HP-2026-0821', freshCsrf($k), $wrong,
            ['cookies' => $login['cookie']]));

        assertSame(400, $res->status, '不一致が通ってしまう: ' . $wrong);
        assertTrue(tokenFromPage((string)$res->rawBody) === null, 'リンクが出ている');
    }

    // 旧 token はまだ生きている
    assertSame(0, $k->audit->countFor($caseId, 'token_reissued'), '監査が増えている');
    assertTrue($k->tokens->verify($oldToken)['row'] !== null, '旧 token が失効している');
});

test('確認画面: 入力された比較値をログへ出さない', function (): void {
    $k = adminKernel();
    reissuableCase($k, 'HP-2026-0823');
    $login = loginAdmin($k);

    $k->app->handle(reissuePost('HP-2026-0823', freshCsrf($k), 'MISTYPED-VALUE-XYZ',
        ['cookies' => $login['cookie']]));

    $log = (string)file_get_contents($k->config->logPath);
    assertTrue(!str_contains($log, 'MISTYPED-VALUE-XYZ'), 'ログへ入力値が出ている');
});

/* ==================================================== 失効 */

test('再発行: 旧 token が失効する', function (): void {
    $k = adminKernel();
    [$caseId, $oldToken] = reissuableCase($k, 'HP-2026-0830');
    $login = loginAdmin($k);

    assertTrue($k->tokens->verify($oldToken)['row'] !== null, '前提: 旧 token が有効でない');

    $res      = $k->app->handle(reissuePost('HP-2026-0830', freshCsrf($k), null, ['cookies' => $login['cookie']]));
    $newToken = tokenFromPage((string)$res->rawBody);

    assertSame(null, $k->tokens->verify($oldToken)['row'], '旧 token が使えてしまう');
    assertSame('revoked', $k->tokens->verify($oldToken)['reason']);
    assertTrue($k->tokens->verify((string)$newToken)['row'] !== null, '新 token が使えない');
    assertSame(1, $k->tokens->activeCount($caseId), '有効 token が1本でない');
});

test('再発行: 旧リンクからは入り直せない', function (): void {
    $k = adminKernel();
    reissuableCase($k, 'HP-2026-0831');
    $login = loginAdmin($k);
    [, $oldToken] = [null, $k->db->pdo()->query('SELECT 1')->fetchColumn()];

    // 旧 token を取り直す（作成時のものを使う）
    $k2 = adminKernel();
    [$caseId2, $old2] = reissuableCase($k2, 'HP-2026-0832');
    $login2 = loginAdmin($k2);

    $k2->app->handle(reissuePost('HP-2026-0832', freshCsrf($k2), null, ['cookies' => $login2['cookie']]));

    $res = $k2->app->handle(jsonPost('/session/start', ['token' => $old2]));
    assertSame(404, $res->status, '旧リンクで入り直せてしまう');
    assertSame('unavailable', $res->body['error']);
    assertTrue($caseId2 > 0 && $oldToken !== null && $login !== null);
});

test('再発行: 関連する店舗 session がすべて失効する', function (): void {
    $k = adminKernel();
    [$caseId, $oldToken, $secret] = reissuableCase($k, 'HP-2026-0833');
    $login = loginAdmin($k);

    // 同じリンクを2端末で開いた状態を作る
    $secret2 = $k->app->handle(jsonPost('/session/start', ['token' => $oldToken]))->cookies[0]['value'];
    assertSame(2, $k->sessions->activeCount($caseId), '前提: session が2本でない');

    $k->app->handle(reissuePost('HP-2026-0833', freshCsrf($k), null, ['cookies' => $login['cookie']]));

    assertSame(0, $k->sessions->activeCount($caseId), '店舗 session が残っている');

    foreach ([$secret, $secret2] as $i => $s) {
        $res = $k->app->handle(jsonGet('/case', ['cookies' => [Config::COOKIE_NAME => $s]]));
        assertSame(404, $res->status, ($i + 1) . '本目の session がまだ使える');
    }
});

/* ==================================================== 新 token の扱い */

test('再発行: DBへは hash のみ。平文を保存しない', function (): void {
    $k = adminKernel();
    reissuableCase($k, 'HP-2026-0840');
    $login = loginAdmin($k);

    $res      = $k->app->handle(reissuePost('HP-2026-0840', freshCsrf($k), null, ['cookies' => $login['cookie']]));
    $newToken = (string)tokenFromPage((string)$res->rawBody);

    $dump = '';
    foreach (['intake_tokens', 'intake_cases', 'intake_sessions', 'intake_audit_events', 'intake_admin_sessions'] as $t) {
        foreach ($k->db->pdo()->query('SELECT * FROM ' . $t)->fetchAll() as $row) {
            $dump .= (string)json_encode($row, JSON_UNESCAPED_UNICODE);
        }
    }
    assertTrue(!str_contains($dump, $newToken), 'DB に平文 token が残っている');

    // hash は入っている
    $hash = hash('sha256', $newToken);
    assertTrue(str_contains($dump, $hash), 'hash が保存されていない');

    // 平文列を作っていない
    $cols = array_column($k->db->pdo()->query("PRAGMA table_info('intake_tokens')")->fetchAll(), 'name');
    foreach ($cols as $col) {
        assertTrue(!in_array($col, ['token', 'token_plain', 'plaintext', 'secret'], true), '平文列がある: ' . $col);
    }
});

test('再発行: token をログ・監査へ出さない', function (): void {
    $k = adminKernel();
    [$caseId] = reissuableCase($k, 'HP-2026-0841');
    $login = loginAdmin($k);

    $res      = $k->app->handle(reissuePost('HP-2026-0841', freshCsrf($k), null, ['cookies' => $login['cookie']]));
    $newToken = (string)tokenFromPage((string)$res->rawBody);

    $log = (string)file_get_contents($k->config->logPath);
    assertTrue(str_contains($log, 'token_reissued'), '再発行がログに残っていない');
    assertTrue(!str_contains($log, $newToken), 'ログへ token が出ている');
    assertTrue(!str_contains($log, hash('sha256', $newToken)), 'ログへ hash が出ている');

    foreach ($k->db->pdo()->query('SELECT * FROM intake_audit_events')->fetchAll() as $row) {
        $dump = (string)json_encode($row, JSON_UNESCAPED_UNICODE);
        assertTrue(!str_contains($dump, $newToken), '監査へ token が出ている');
        assertTrue(!str_contains($dump, hash('sha256', $newToken)), '監査へ hash が出ている');
    }
    assertSame(1, $k->audit->countFor($caseId, 'token_reissued'), '監査が1件でない');
});

test('再発行: 成功画面以外に token を出さない', function (): void {
    $k = adminKernel();
    reissuableCase($k, 'HP-2026-0842');
    $login = loginAdmin($k);

    $res      = $k->app->handle(reissuePost('HP-2026-0842', freshCsrf($k), null, ['cookies' => $login['cookie']]));
    $newToken = (string)tokenFromPage((string)$res->rawBody);

    // 詳細・一覧・確認画面・書き出しのどこにも出さない
    $pages = [
        'detail'  => (string)$k->app->handle(adminGet('/admin/case',
            ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-2026-0842']]))->rawBody,
        'list'    => (string)$k->app->handle(adminGet('/admin/', ['cookies' => $login['cookie']]))->rawBody,
        'reissue' => (string)$k->app->handle(adminGet('/admin/reissue',
            ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-2026-0842']]))->rawBody,
    ];
    foreach ($pages as $name => $html) {
        assertTrue(!str_contains($html, $newToken), $name . ' に token が出ている');
    }
});

test('再発行: token を URL・Cookie・HTMLコメントへ出さない', function (): void {
    $k = adminKernel();
    reissuableCase($k, 'HP-2026-0843');
    $login = loginAdmin($k);

    $res      = $k->app->handle(reissuePost('HP-2026-0843', freshCsrf($k), null, ['cookies' => $login['cookie']]));
    $html     = (string)$res->rawBody;
    $newToken = (string)tokenFromPage($html);

    // href / action / Location に出さない
    preg_match_all('/(?:href|action)="([^"]*)"/', $html, $m);
    foreach ($m[1] as $url) {
        assertTrue(!str_contains($url, $newToken), 'URL へ token が出ている: ' . $url);
    }
    assertTrue(!isset($res->headers['Location']), '成功応答が遷移になっている');

    // Cookie へ入れない
    foreach ($res->cookies as $cookie) {
        assertTrue(!str_contains((string)$cookie['value'], $newToken), 'Cookie へ token が出ている');
    }

    // HTML コメントへ出さない
    preg_match_all('/<!--(.*?)-->/s', $html, $c);
    foreach ($c[1] as $comment) {
        assertTrue(!str_contains($comment, $newToken), 'コメントへ token が出ている');
    }

    // token が現れるのは input の value 1箇所だけ
    assertSame(1, substr_count($html, $newToken), 'token が複数箇所に出ている');
    assertTrue(preg_match('/<input[^>]*id="issued-link"[^>]*value="[^"]*' . preg_quote($newToken, '/') . '/', $html) === 1,
        'token が想定の入力欄に無い');
});

test('再発行: 成功応答は no-store / noindex / CSP', function (): void {
    $k = adminKernel();
    reissuableCase($k, 'HP-2026-0844');
    $login = loginAdmin($k);

    $res = $k->app->handle(reissuePost('HP-2026-0844', freshCsrf($k), null, ['cookies' => $login['cookie']]));

    assertTrue(str_contains($res->headers['Cache-Control'] ?? '', 'no-store'), 'no-store が無い');
    assertTrue(str_contains($res->headers['X-Robots-Tag'] ?? '', 'noindex'), 'noindex が無い');
    assertTrue(str_contains($res->headers['Content-Security-Policy'] ?? '', "frame-ancestors 'none'"), 'CSP が無い');
    assertSame('nosniff', $res->headers['X-Content-Type-Options'] ?? '');
    assertTrue(str_contains((string)$res->rawBody, 'この画面を閉じると再表示できません'), '注意書きが無い');

    // 外部リソース・script を持ち込まない
    $html = (string)$res->rawBody;
    assertTrue(preg_match('#<script#i', $html) !== 1, 'script がある');
    assertTrue(preg_match('#(?:src|href)="(?:https?:)?//#i', $html) !== 1, '外部を参照している');
});

/* ==================================================== 二重送信 */

test('再発行: 同じ CSRF での再送では再々発行しない', function (): void {
    $k = adminKernel();
    [$caseId] = reissuableCase($k, 'HP-2026-0850');
    $login = loginAdmin($k);

    $csrf  = freshCsrf($k);
    $first = $k->app->handle(reissuePost('HP-2026-0850', $csrf, null, ['cookies' => $login['cookie']]));
    assertSame(200, $first->status);

    $tokensAfterFirst = (int)$k->db->pdo()->query('SELECT COUNT(*) FROM intake_tokens')->fetchColumn();

    // ブラウザの再送（同じ CSRF）
    $second = $k->app->handle(reissuePost('HP-2026-0850', $csrf, null, ['cookies' => $login['cookie']]));

    assertSame(403, $second->status, '同じ CSRF で再発行できてしまう');
    assertTrue(tokenFromPage((string)$second->rawBody) === null, '再送でリンクが出ている');
    assertSame($tokensAfterFirst, (int)$k->db->pdo()->query('SELECT COUNT(*) FROM intake_tokens')->fetchColumn(),
        '再送で token が増えている');
    assertSame(1, $k->audit->countFor($caseId, 'token_reissued'), '再送で監査が増えている');
});

/* ==================================================== 認証・CSRF・Origin */

test('再発行: 未認証では実行できない', function (): void {
    $k = adminKernel();
    [$caseId, $oldToken] = reissuableCase($k, 'HP-2026-0860');

    $res = $k->app->handle(reissuePost('HP-2026-0860', str_repeat('A', 43)));

    assertSame(303, $res->status, '未認証で実行できてしまう');
    assertTrue($k->tokens->verify($oldToken)['row'] !== null, '旧 token が失効した');
    assertSame(0, $k->audit->countFor($caseId, 'token_reissued'));

    // 確認画面も見えない
    $form = $k->app->handle(adminGet('/admin/reissue', ['query' => ['case' => 'HP-2026-0860']]));
    assertSame(303, $form->status, '未認証で確認画面が見える');
    assertTrue(!str_contains((string)$form->rawBody, 'HP-2026-0860'), '案件番号を漏らしている');
});

test('再発行: CSRF 欠落・不一致では実行できない', function (): void {
    $k = adminKernel();
    [$caseId, $oldToken] = reissuableCase($k, 'HP-2026-0861');
    $login = loginAdmin($k);

    // 欠落
    $missing = new Request(
        method: 'POST',
        path: '/admin/reissue/send',
        headers: ['Content-Type' => 'application/x-www-form-urlencoded', 'Origin' => TEST_ORIGIN],
        body: http_build_query(['case' => 'HP-2026-0861', 'confirm_case' => 'HP-2026-0861']),
        cookies: $login['cookie'],
        isHttps: true,
    );
    assertSame(403, $k->app->handle($missing)->status, 'CSRF 無しで通る');

    // 不一致
    $forged = $k->app->handle(reissuePost('HP-2026-0861', str_repeat('B', 43), null,
        ['cookies' => $login['cookie']]));
    assertSame(403, $forged->status, '偽の CSRF が通る');

    assertTrue($k->tokens->verify($oldToken)['row'] !== null, '旧 token が失効した');
    assertSame(0, $k->audit->countFor($caseId, 'token_reissued'));
});

test('再発行: Origin が別オリジンなら拒否する', function (): void {
    $k = adminKernel();
    [$caseId, $oldToken] = reissuableCase($k, 'HP-2026-0862');
    $login = loginAdmin($k);

    $res = $k->app->handle(reissuePost('HP-2026-0862', freshCsrf($k), null,
        ['cookies' => $login['cookie'], 'origin' => 'https://evil.example',
         'headers' => ['Sec-Fetch-Site' => 'same-origin']]));

    assertSame(403, $res->status, '別オリジンが通る');
    assertTrue($k->tokens->verify($oldToken)['row'] !== null, '旧 token が失効した');
    assertSame(0, $k->audit->countFor($caseId, 'token_reissued'));
});

test('再発行: Origin=null は Sec-Fetch-Site: same-origin のときだけ通す', function (): void {
    $k = adminKernel();
    [$caseId, $oldToken] = reissuableCase($k, 'HP-2026-0863');
    $login = loginAdmin($k);

    foreach ([
        ['Origin' => 'null', 'Sec-Fetch-Site' => 'cross-site'],
        ['Origin' => 'null', 'Sec-Fetch-Site' => 'none'],
        ['Origin' => 'null'],
        [],
    ] as $headers) {
        $res = $k->app->handle(reissuePost('HP-2026-0863', freshCsrf($k), null, [
            'cookies'   => $login['cookie'],
            'no_origin' => true,
            'headers'   => $headers,
        ]));
        assertSame(403, $res->status, '通ってはいけない: ' . json_encode($headers));
    }
    assertTrue($k->tokens->verify($oldToken)['row'] !== null, '旧 token が失効した');
    assertSame(0, $k->audit->countFor($caseId, 'token_reissued'));

    // 同一オリジンの form 送信だけ通る
    $ok = $k->app->handle(reissuePost('HP-2026-0863', freshCsrf($k), null, [
        'cookies'   => $login['cookie'],
        'no_origin' => true,
        'headers'   => ['Origin' => 'null', 'Sec-Fetch-Site' => 'same-origin'],
    ]));
    assertSame(200, $ok->status, '同一オリジンの form 送信が通らない');
    assertSame(1, $k->audit->countFor($caseId, 'token_reissued'));
});

test('再発行: GET では実行できない', function (): void {
    $k = adminKernel();
    [$caseId, $oldToken] = reissuableCase($k, 'HP-2026-0864');
    $login = loginAdmin($k);

    $res = $k->app->handle(reissuePost('HP-2026-0864', freshCsrf($k), null,
        ['cookies' => $login['cookie'], 'method' => 'GET']));

    assertSame(403, $res->status, 'GET で実行できてしまう');
    assertTrue($k->tokens->verify($oldToken)['row'] !== null, '旧 token が失効した');
    assertSame(0, $k->audit->countFor($caseId, 'token_reissued'));
});

/* ==================================================== rate limit */

test('再発行: 案件 ＋ HMAC化IP で 10分5回', function (): void {
    $k = adminKernel();
    [$caseId] = reissuableCase($k, 'HP-2026-0870');
    $login = loginAdmin($k);

    for ($i = 0; $i < 5; ++$i) {
        $res = $k->app->handle(reissuePost('HP-2026-0870', freshCsrf($k), null, ['cookies' => $login['cookie']]));
        assertSame(200, $res->status, ($i + 1) . '回目が失敗した');
    }

    $res = $k->app->handle(reissuePost('HP-2026-0870', freshCsrf($k), null, ['cookies' => $login['cookie']]));
    assertSame(429, $res->status, '6回目が 429 でない');
    assertTrue(tokenFromPage((string)$res->rawBody) === null, '締め出し中にリンクが出ている');

    // 成功5件 ＋ 締め出し1件 ＝ 6件（result_code で区別する）
    assertSame(6, $k->audit->countFor($caseId, 'token_reissued'), '監査の総数が合わない');

    $counts = [];
    foreach ($k->db->pdo()->query(
        "SELECT result_code, COUNT(*) c FROM intake_audit_events
          WHERE event_type = 'token_reissued' GROUP BY result_code"
    )->fetchAll() as $row) {
        $counts[(string)$row['result_code']] = (int)$row['c'];
    }
    assertSame(5, $counts['ok'] ?? 0, '成功件数が合わない');
    assertSame(1, $counts['rate_limited'] ?? 0, '締め出しが監査へ残っていない');

    // 締め出されても、有効な token は1本のまま
    assertSame(1, $k->tokens->activeCount($caseId), '有効 token が1本でない');
});

test('再発行: レート制限は案件ごとに分かれる', function (): void {
    $k = adminKernel();
    reissuableCase($k, 'HP-2026-0871');
    [$otherId] = reissuableCase($k, 'HP-2026-0872');
    $login = loginAdmin($k);

    for ($i = 0; $i < 5; ++$i) {
        $k->app->handle(reissuePost('HP-2026-0871', freshCsrf($k), null, ['cookies' => $login['cookie']]));
    }
    assertSame(429, $k->app->handle(reissuePost('HP-2026-0871', freshCsrf($k), null,
        ['cookies' => $login['cookie']]))->status, '前提: 締め出されていない');

    // 別案件は巻き込まれない
    $res = $k->app->handle(reissuePost('HP-2026-0872', freshCsrf($k), null, ['cookies' => $login['cookie']]));
    assertSame(200, $res->status, '別案件まで止めている');
    assertSame(1, $k->audit->countFor($otherId, 'token_reissued'));
});

/* ==================================================== 回答データの維持 */

test('再発行: 入力済みの内容・version・履歴・修正依頼・Drive を消さない', function (): void {
    $k = adminKernel();
    [$caseId, $storeCookies] = submittedCase($k, 'HP-2026-0880');
    $k->cases->setDriveFolder($caseId, FAKE_DRIVE_URL, 'HP-2026-0880 素材', FAKE_DRIVE_EMAIL);

    $login = loginAdmin($k);
    $k->app->handle(revisionPost('HP-2026-0880', ['basic.legal_name'], (string)$login['csrf'],
        '店名をご確認ください。', ['cookies' => $login['cookie']]));

    $before = [
        'answers'   => $k->answers->get($caseId),
        'history'   => $k->answers->historyCount($caseId),
        'revisions' => $k->revisions->countFor($caseId, RevisionRequestService::STATUS_OPEN),
        'drive_url' => $k->cases->driveFolderUrl($caseId),
        'drive_mail' => $k->cases->driveSharedEmail($caseId),
        'confirmed' => $k->cases->find($caseId)['drive_upload_confirmed_at'],
    ];

    $res      = $k->app->handle(reissuePost('HP-2026-0880', freshCsrf($k), null, ['cookies' => $login['cookie']]));
    $newToken = (string)tokenFromPage((string)$res->rawBody);

    $after = [
        'answers'   => $k->answers->get($caseId),
        'history'   => $k->answers->historyCount($caseId),
        'revisions' => $k->revisions->countFor($caseId, RevisionRequestService::STATUS_OPEN),
        'drive_url' => $k->cases->driveFolderUrl($caseId),
        'drive_mail' => $k->cases->driveSharedEmail($caseId),
        'confirmed' => $k->cases->find($caseId)['drive_upload_confirmed_at'],
    ];

    assertSame($before['answers']['version'], $after['answers']['version'], 'version が変わった');
    assertSame(
        json_encode($before['answers']['sections'], JSON_UNESCAPED_UNICODE),
        json_encode($after['answers']['sections'], JSON_UNESCAPED_UNICODE),
        '回答が変わった'
    );
    assertSame($before['history'], $after['history'], '提出履歴が変わった');
    assertSame($before['revisions'], $after['revisions'], '修正依頼が変わった');
    assertSame($before['drive_url'], $after['drive_url'], 'Drive URL が変わった');
    assertSame($before['drive_mail'], $after['drive_mail'], '共有メールが変わった');
    assertSame($before['confirmed'], $after['confirmed'], '素材申告が変わった');
    assertSame('needs_revision', (string)$k->cases->find($caseId)['status'], '状態が変わった');
    assertTrue($storeCookies !== null && $newToken !== '');
});

test('再発行: 新しいリンクから続きを入力できる', function (): void {
    $k = adminKernel();
    [$caseId] = reissuableCase($k, 'HP-2026-0881');
    $login = loginAdmin($k);

    // 途中まで入力しておく
    $token   = $k->tokens->issue($caseId);
    $secret  = $k->app->handle(jsonPost('/session/start', ['token' => $token]))->cookies[0]['value'];
    $cookies = ['cookies' => [Config::COOKIE_NAME => $secret]];
    $k->app->handle(jsonPost('/answers/save', [
        'version' => 1, 'sections' => ['basic' => ['legal_name' => '架空サロン ハルカゼ']],
    ], $cookies));

    $res      = $k->app->handle(reissuePost('HP-2026-0881', freshCsrf($k), null, ['cookies' => $login['cookie']]));
    $newToken = (string)tokenFromPage((string)$res->rawBody);

    // 新リンクで入り直す
    $newSecret = $k->app->handle(jsonPost('/session/start', ['token' => $newToken]))->cookies[0]['value'];
    $case      = $k->app->handle(jsonGet('/case', ['cookies' => [Config::COOKIE_NAME => $newSecret]]));

    assertSame(200, $case->status, '新リンクで入れない');
    assertSame('架空サロン ハルカゼ', $case->body['sections']['basic']['legal_name'], '入力内容が消えている');
    assertSame(2, $case->body['version'], 'version が戻っている');
});

/* ==================================================== 通信断からの復旧 */

test('再発行: 応答を受け取れなくても、もう一度再発行できる', function (): void {
    $k = adminKernel();
    [$caseId, $oldToken] = reissuableCase($k, 'HP-2026-0890');
    $login = loginAdmin($k);

    // 1回目（応答が届かなかったと想定して、token を控えない）
    $first     = $k->app->handle(reissuePost('HP-2026-0890', freshCsrf($k), null, ['cookies' => $login['cookie']]));
    $lostToken = (string)tokenFromPage((string)$first->rawBody);

    // 旧 token はすでに無効
    assertSame(null, $k->tokens->verify($oldToken)['row'], '旧 token がまだ使える');

    // 2回目。もう一度出せる
    $second   = $k->app->handle(reissuePost('HP-2026-0890', freshCsrf($k), null, ['cookies' => $login['cookie']]));
    $newToken = (string)tokenFromPage((string)$second->rawBody);

    assertSame(200, $second->status, '2回目が実行できない');
    assertTrue($newToken !== '' && $newToken !== $lostToken, '同じ token が出ている');

    // 直前の token も失効している
    assertSame(null, $k->tokens->verify($lostToken)['row'], '直前の token がまだ使える');
    assertTrue($k->tokens->verify($newToken)['row'] !== null, '新しい token が使えない');
    assertSame(1, $k->tokens->activeCount($caseId), '有効 token が1本でない');

    // 監査はそのたびに増える
    assertSame(2, $k->audit->countFor($caseId, 'token_reissued'), '監査が2件でない');
});

/* ==================================================== 回帰 */

test('再発行: 店舗の JSON POST Guard は変更していない', function (): void {
    [$k, , $secret] = withSession('HP-2026-0895');

    foreach (['/answers/save', '/submit', '/session/logout', '/drive/confirm'] as $path) {
        $res = $k->app->handle(new Request(
            method: 'POST',
            path: $path,
            headers: ['Content-Type' => 'application/json', 'Sec-Fetch-Site' => 'same-origin'],
            body: '{}',
            cookies: [Config::COOKIE_NAME => $secret],
            isHttps: true,
        ));
        assertSame(403, $res->status, $path . ' が Origin 無しで通る');
    }
});

test('再発行: 初回発行と再発行を監査で区別できる', function (): void {
    $k = adminKernel();
    [$caseId] = reissuableCase($k, 'HP-2026-0896');
    $login = loginAdmin($k);

    $issuedBefore = $k->audit->countFor($caseId, 'token_issued');
    assertTrue($issuedBefore >= 1, '初回発行が監査に無い');

    $k->app->handle(reissuePost('HP-2026-0896', freshCsrf($k), null, ['cookies' => $login['cookie']]));

    assertSame($issuedBefore, $k->audit->countFor($caseId, 'token_issued'), 'token_issued が増えている');
    assertSame(1, $k->audit->countFor($caseId, 'token_reissued'), 'token_reissued が1件でない');
});

test('再発行: SQLite 3.26.0 互換のまま（スキーマ変更なし）', function (): void {
    $k = makeKernel();

    // 4D-R2 は DB スキーマを変えていない
    assertSame(4, \SmartLabo\Intake\Migrator::SCHEMA_VERSION, 'スキーマ版が変わっている');
    assertSame(4, (int)$k->db->pdo()->query('PRAGMA user_version')->fetchColumn());

    $tables = array_column($k->db->pdo()->query(
        "SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'intake_%' ORDER BY name"
    )->fetchAll(), 'name');
    assertSame(8, count($tables), 'テーブル数が変わっている');
});
