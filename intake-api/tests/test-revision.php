<?php
/**
 * 修正依頼・案件作成・Drive 案内（SSOT v1.5 §2.8 / §5.1 / §7.3 / §11.3）
 * HP-ONBOARDING-4D-R1
 */
declare(strict_types=1);

use SmartLabo\Intake\AnswerPaths;
use SmartLabo\Intake\Config;
use SmartLabo\Intake\Http\Request;
use SmartLabo\Intake\Migrator;
use SmartLabo\Intake\Service\CaseService;
use SmartLabo\Intake\Service\RevisionRequestService;
use SmartLabo\Intake\Support\DriveLink;

/** 架空の Drive フォルダ（実在しない） */
const FAKE_DRIVE_URL   = 'https://drive.google.com/drive/folders/FAKE-0000000000000000';
const FAKE_DRIVE_EMAIL = 'shop-owner@example.invalid';

/** 修正依頼の form POST（paths[] は同名複数値） */
function revisionPost(string $caseNumber, array $paths, string $csrf, ?string $message = null, array $opts = []): Request
{
    $body = 'csrf_token=' . rawurlencode($csrf) . '&case=' . rawurlencode($caseNumber);
    foreach ($paths as $p) {
        $body .= '&paths%5B%5D=' . rawurlencode($p);
    }
    if ($message !== null) {
        $body .= '&message=' . rawurlencode($message);
    }

    return new Request(
        method: 'POST',
        path: '/admin/revision/send',
        headers: [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Origin'       => $opts['origin'] ?? TEST_ORIGIN,
        ],
        body: $body,
        cookies: $opts['cookies'] ?? [],
        isHttps: true,
        clientIp: '203.0.113.10',
    );
}

/** その管理 session の最新 CSRF を取り直す */
function freshCsrf(object $k): string
{
    $id = (int)$k->db->pdo()->query('SELECT id FROM intake_admin_sessions ORDER BY id DESC LIMIT 1')->fetchColumn();

    return $k->adminAuth->rotateCsrf($id);
}

/* ==================================================== 正式パス一覧 */

test('paths: 画面側の定義とサーバー側の一覧が一致する', function (): void {
    // ★ここが食い違うと、画面で選べるのに保存できない項目が生まれる。
    //   AnswerPaths は schema.js から機械的に作っている。
    $schema = (string)file_get_contents(__DIR__ . '/../public/assets/lib/schema.js');

    // schema.js の STEPS から key と field.path を拾う（順序も見る）
    preg_match_all("/^  \{\s*\n    key: '([a-z_]+)'/m", $schema, $keys);
    assertSame(11, count($keys[1]), '画面側の分類が11個でない');

    $sections = [];
    foreach (AnswerPaths::ALL as $path) {
        $sections[str_contains($path, '.') ? substr($path, 0, strpos($path, '.')) : $path] = true;
    }
    assertSame($keys[1], array_keys($sections), '分類名または順序が画面側と違う');

    // 画面側の各項目が一覧に載っていること
    preg_match_all("/f\('([a-zA-Z0-9_.]+)',/", $schema, $fields);
    assertTrue(count($fields[1]) > 100, '項目が少なすぎる: ' . count($fields[1]));

    $missing = [];
    foreach ($fields[1] as $i => $fieldPath) {
        $found = false;
        foreach (AnswerPaths::ALL as $p) {
            if (str_ends_with($p, '.' . $fieldPath)) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $missing[] = $fieldPath;
        }
    }
    assertSame([], $missing, '一覧に無い項目がある: ' . implode(', ', $missing));
});

test('paths: 未知のパスを受け付けない', function (): void {
    foreach ([
        'unknown_section', 'basic.unknown_field', 'basic', // 'basic' は正当
    ] as $path) {
        $expected = $path === 'basic';
        assertSame($expected, AnswerPaths::isValid($path), $path . ' の判定が違う');
    }

    foreach (['', '../etc', 'basic.legal_name; DROP TABLE', 'BASIC.legal_name'] as $bad) {
        assertTrue(!AnswerPaths::isValid($bad), '不正なパスが通る: ' . $bad);
    }
});

test('paths: 1つでも未知なら丸ごと拒否する', function (): void {
    $ok = AnswerPaths::normalize(['basic.legal_name', 'menus']);
    assertSame(true, $ok['ok']);
    assertSame(['basic.legal_name', 'menus'], $ok['paths']);

    $ng = AnswerPaths::normalize(['basic.legal_name', 'nope']);
    assertSame(false, $ng['ok'], '一部が不正でも通ってしまう');
    assertSame('unknown_path', $ng['error']);

    // どのパスが不正だったかは返さない（内部の一覧を推測させない）
    assertTrue(!isset($ng['paths']), '不正時にパスを返している');
});

test('paths: 重複を正規化し、順序を保つ', function (): void {
    $r = AnswerPaths::normalize([
        'menus', 'basic.legal_name', 'menus', 'basic.address', 'basic.legal_name',
    ]);

    assertSame(true, $r['ok']);
    assertSame(['menus', 'basic.legal_name', 'basic.address'], $r['paths'], '重複の除去か順序が違う');
});

test('paths: 空・多すぎる指定を拒否する', function (): void {
    assertSame('empty', AnswerPaths::normalize([])['error']);
    assertSame('empty', AnswerPaths::normalize('basic')['error']);
    assertSame('empty', AnswerPaths::normalize(null)['error']);

    $tooMany = array_fill(0, count(AnswerPaths::ALL) + 1, 'basic');
    assertSame('too_many', AnswerPaths::normalize($tooMany)['error']);
});

/* ==================================================== 状態遷移（v1.5） */

test('遷移: submitted → needs_revision', function (): void {
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0700');
    $login = loginAdmin($k);

    $res = $k->app->handle(revisionPost('HP-2026-0700', ['basic.legal_name'], (string)$login['csrf'],
        '店名の表記をご確認ください。', ['cookies' => $login['cookie']]));

    assertSame(303, $res->status, json_encode($res->headers));
    assertSame('needs_revision', (string)$k->cases->find($caseId)['status']);
});

test('遷移: reviewed → needs_revision（v1.5 で許可）', function (): void {
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0701');
    $login = loginAdmin($k);

    $k->app->handle(adminPost('/admin/status',
        ['case' => 'HP-2026-0701', 'to' => 'reviewed', 'csrf_token' => $login['csrf']],
        ['cookies' => $login['cookie']]));
    assertSame('reviewed', (string)$k->cases->find($caseId)['status']);

    $res = $k->app->handle(revisionPost('HP-2026-0701', ['menus'], freshCsrf($k),
        '料金の税込表示をご確認ください。', ['cookies' => $login['cookie']]));

    assertSame(303, $res->status);
    assertSame('needs_revision', (string)$k->cases->find($caseId)['status'], 'reviewed から戻せない');
    assertSame(1, $k->revisions->countFor($caseId, RevisionRequestService::STATUS_OPEN));
});

test('遷移: locked / closed からは修正依頼を出せない', function (): void {
    foreach (['locked', 'closed'] as $i => $finalStatus) {
        $k = adminKernel();
        $number = 'HP-2026-071' . $i;
        [$caseId] = submittedCase($k, $number);
        $login = loginAdmin($k);

        $k->cases->transitionTo($caseId, 'reviewed');
        $k->cases->transitionTo($caseId, $finalStatus === 'locked' ? 'locked' : 'closed');
        assertSame($finalStatus, (string)$k->cases->find($caseId)['status']);

        $res = $k->app->handle(revisionPost($number, ['basic.legal_name'], freshCsrf($k),
            null, ['cookies' => $login['cookie']]));

        assertSame($finalStatus, (string)$k->cases->find($caseId)['status'], $finalStatus . ' から戻せてしまう');
        assertSame(0, $k->revisions->countFor($caseId), $finalStatus . ' で依頼が作られた');
        assertTrue($res->status === 303 || $res->status === 409, '想定外の応答: ' . $res->status);
    }
});

test('遷移: 修正依頼の入力画面は locked / closed で出さない', function (): void {
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0713');
    $login = loginAdmin($k);
    $k->cases->transitionTo($caseId, 'reviewed');
    $k->cases->transitionTo($caseId, 'locked');

    $res = $k->app->handle(adminGet('/admin/revision',
        ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-2026-0713']]));
    assertSame(409, $res->status, 'locked でも入力画面が出る');

    // 詳細画面にもボタンを出さない
    $html = (string)$k->app->handle(adminGet('/admin/case',
        ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-2026-0713']]))->rawBody;
    assertTrue(!str_contains($html, '修正を依頼する'), '必ず失敗するボタンが出ている');
});

test('遷移: submitted / reviewed では修正依頼ボタンを出す', function (): void {
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0714');
    $login = loginAdmin($k);

    $html = (string)$k->app->handle(adminGet('/admin/case',
        ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-2026-0714']]))->rawBody;
    assertTrue(str_contains($html, '修正を依頼する'), 'submitted でボタンが出ていない');

    $k->cases->transitionTo($caseId, 'reviewed');
    $html = (string)$k->app->handle(adminGet('/admin/case',
        ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-2026-0714']]))->rawBody;
    assertTrue(str_contains($html, '修正を依頼する'), 'reviewed でボタンが出ていない');
});

/* ==================================================== 修正依頼の作成 */

test('依頼: 状態変更と依頼作成は同一トランザクション', function (): void {
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0720');
    $login = loginAdmin($k);

    $k->app->handle(revisionPost('HP-2026-0720', ['basic.legal_name', 'menus'], (string)$login['csrf'],
        '2点ご確認ください。', ['cookies' => $login['cookie']]));

    // 状態・依頼・履歴・監査がすべてそろっていること
    assertSame('needs_revision', (string)$k->cases->find($caseId)['status']);
    assertSame(1, $k->revisions->countFor($caseId), '依頼が作られていない');
    assertSame(1, $k->audit->countFor($caseId, 'case_status_changed'), '監査が無い');

    $event = $k->db->pdo()->query(
        "SELECT event_type FROM intake_submission_history WHERE event_type = 'revision_requested'"
    )->fetchColumn();
    assertSame('revision_requested', $event, '履歴が無い');
});

test('依頼: 失敗したら状態も依頼も動かない', function (): void {
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0721');
    $login = loginAdmin($k);

    // 未知パスを混ぜる
    $res = $k->app->handle(revisionPost('HP-2026-0721', ['basic.legal_name', 'nope'], (string)$login['csrf'],
        null, ['cookies' => $login['cookie']]));

    assertSame(400, $res->status, '未知パスが通ってしまう');
    assertSame('submitted', (string)$k->cases->find($caseId)['status'], '状態が変わった');
    assertSame(0, $k->revisions->countFor($caseId), '依頼が作られた');
    assertSame(0, $k->audit->countFor($caseId, 'case_status_changed'), '監査が増えた');
});

test('依頼: 項目を1つも選ばなければ拒否する', function (): void {
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0722');
    $login = loginAdmin($k);

    $res = $k->app->handle(revisionPost('HP-2026-0722', [], (string)$login['csrf'],
        'なんとなく直してください', ['cookies' => $login['cookie']]));

    assertSame(400, $res->status);
    assertSame('submitted', (string)$k->cases->find($caseId)['status']);
    assertSame(0, $k->revisions->countFor($caseId));
});

test('依頼: メッセージは1000文字まで（切り捨てず拒否）', function (): void {
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0723');
    $login = loginAdmin($k);

    $tooLong = str_repeat('あ', RevisionRequestService::MESSAGE_MAX + 1);
    $res = $k->app->handle(revisionPost('HP-2026-0723', ['basic.legal_name'], (string)$login['csrf'],
        $tooLong, ['cookies' => $login['cookie']]));

    assertSame(400, $res->status, '長すぎるメッセージが通る');
    assertSame(0, $k->revisions->countFor($caseId), '依頼が作られた');

    // ちょうど 1000 文字は通る
    $ok = str_repeat('あ', RevisionRequestService::MESSAGE_MAX);
    $res2 = $k->app->handle(revisionPost('HP-2026-0723', ['basic.legal_name'], freshCsrf($k),
        $ok, ['cookies' => $login['cookie']]));
    assertSame(303, $res2->status, 'ちょうど上限が通らない');

    $stored = (string)$k->db->pdo()->query('SELECT message FROM intake_revision_requests LIMIT 1')->fetchColumn();
    assertSame(RevisionRequestService::MESSAGE_MAX, mb_strlen($stored, 'UTF-8'), '切り捨てられている');
});

test('依頼: メッセージを HTML として解釈しない', function (): void {
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0724');
    $login = loginAdmin($k);

    $xss = '<script>alert(1)</script><img src=x onerror=alert(2)>"><svg onload=alert(3)>';
    $k->app->handle(revisionPost('HP-2026-0724', ['basic.legal_name'], (string)$login['csrf'],
        $xss, ['cookies' => $login['cookie']]));

    // そのまま保存され、値としては失われない
    $stored = (string)$k->db->pdo()->query('SELECT message FROM intake_revision_requests LIMIT 1')->fetchColumn();
    assertSame($xss, $stored, '値が変わっている');

    // 管理画面ではエスケープされて出る
    $html = (string)$k->app->handle(adminGet('/admin/case',
        ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-2026-0724']]))->rawBody;
    assertTrue(!str_contains($html, '<script>alert(1)</script>'), 'script がそのまま出ている');
    assertTrue(!str_contains($html, '<svg onload'), 'svg がそのまま出ている');
    assertTrue(str_contains($html, '&lt;script&gt;'), 'エスケープされて表示されていない');

    // 店舗へは JSON として返る（HTML ではない）
    $token   = $k->tokens->issue($caseId);
    $secret  = $k->app->handle(jsonPost('/session/start', ['token' => $token]))->cookies[0]['value'];
    $case    = $k->app->handle(jsonGet('/case', ['cookies' => [Config::COOKIE_NAME => $secret]]));
    assertSame($xss, $case->body['revision_requests'][0]['message'], '店舗へ値が届いていない');
});

test('依頼: 本文と対象項目をログへ出さない', function (): void {
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0725');
    $login = loginAdmin($k);

    $secretMessage = 'ヒミツの連絡事項ABCXYZ';
    $k->app->handle(revisionPost('HP-2026-0725', ['basic.internal_contact.email'], (string)$login['csrf'],
        $secretMessage, ['cookies' => $login['cookie']]));

    $log = (string)file_get_contents($k->config->logPath);
    assertTrue(str_contains($log, 'case_status_changed'), '差し戻しがログに残っていない');
    assertTrue(!str_contains($log, $secretMessage), 'ログへメッセージが出ている');
    assertTrue(!str_contains($log, 'internal_contact'), 'ログへ対象項目が出ている');

    // 監査にも本文を持たせない
    $rows = $k->db->pdo()->query('SELECT * FROM intake_audit_events')->fetchAll();
    foreach ($rows as $row) {
        $dump = (string)json_encode($row, JSON_UNESCAPED_UNICODE);
        assertTrue(!str_contains($dump, $secretMessage), '監査へメッセージが出ている');
    }
    assertTrue($caseId > 0);
});

test('依頼: 1案件に複数回持て、過去を消さない', function (): void {
    $k = adminKernel();
    [$caseId, $storeCookies] = submittedCase($k, 'HP-2026-0726');
    $login = loginAdmin($k);

    // 1回目
    $k->app->handle(revisionPost('HP-2026-0726', ['basic.legal_name'], (string)$login['csrf'],
        '1回目', ['cookies' => $login['cookie']]));
    // 店舗が再提出
    $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $storeCookies));
    // 2回目
    $k->app->handle(revisionPost('HP-2026-0726', ['menus'], freshCsrf($k),
        '2回目', ['cookies' => $login['cookie']]));

    $all = $k->revisions->allForCase($caseId);
    assertSame(2, count($all), '2件保持されていない');
    assertSame(1, (int)$all[0]['request_number']);
    assertSame(2, (int)$all[1]['request_number']);
    assertSame('1回目', (string)$all[0]['message'], '過去の依頼が上書きされている');
    assertSame(RevisionRequestService::STATUS_RESOLVED, (string)$all[0]['status']);
    assertSame(RevisionRequestService::STATUS_OPEN, (string)$all[1]['status']);
});

/* ==================================================== 店舗側の表示 */

test('店舗: open の依頼だけを受け取る', function (): void {
    $k = adminKernel();
    [$caseId, $storeCookies] = submittedCase($k, 'HP-2026-0730');
    $login = loginAdmin($k);

    $k->app->handle(revisionPost('HP-2026-0730', ['basic.legal_name'], (string)$login['csrf'],
        '店名をご確認ください。', ['cookies' => $login['cookie']]));

    $res = $k->app->handle(jsonGet('/case', $storeCookies));
    assertSame(200, $res->status);
    assertSame(1, count($res->body['revision_requests']), '依頼が届いていない');

    $req = $res->body['revision_requests'][0];
    assertSame(1, $req['request_number']);
    assertSame(['basic.legal_name'], $req['requested_paths']);
    assertSame('店名をご確認ください。', $req['message']);
    assertTrue(is_string($req['created_at']));

    // DB の内部ID・status を返さない
    assertSame(['request_number', 'requested_paths', 'message', 'created_at'], array_keys($req), '余分なキーがある');
    assertTrue($caseId > 0);
});

test('店舗: resolved の依頼は返さない', function (): void {
    $k = adminKernel();
    [$caseId, $storeCookies] = submittedCase($k, 'HP-2026-0731');
    $login = loginAdmin($k);

    $k->app->handle(revisionPost('HP-2026-0731', ['basic.legal_name'], (string)$login['csrf'],
        '過去の連絡事項ZZZ', ['cookies' => $login['cookie']]));
    $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $storeCookies));

    $res  = $k->app->handle(jsonGet('/case', $storeCookies));
    $dump = (string)json_encode($res->body, JSON_UNESCAPED_UNICODE);

    assertSame(0, count($res->body['revision_requests']), '対応済みの依頼が返っている');
    assertTrue(!str_contains($dump, '過去の連絡事項ZZZ'), '対応済みの本文が返っている');
    assertSame(1, $k->revisions->countFor($caseId, RevisionRequestService::STATUS_RESOLVED));
});

test('店舗: 再提出が成功したら依頼が閉じる', function (): void {
    $k = adminKernel();
    [$caseId, $storeCookies] = submittedCase($k, 'HP-2026-0732');
    $login = loginAdmin($k);

    $k->app->handle(revisionPost('HP-2026-0732', ['basic.legal_name'], (string)$login['csrf'],
        null, ['cookies' => $login['cookie']]));
    assertSame(1, $k->revisions->countFor($caseId, RevisionRequestService::STATUS_OPEN));

    $res = $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $storeCookies));
    assertSame(200, $res->status);
    assertSame(true, $res->body['submitted']);

    assertSame(0, $k->revisions->countFor($caseId, RevisionRequestService::STATUS_OPEN), '依頼が閉じていない');
    assertSame('submitted', (string)$k->cases->find($caseId)['status']);

    $resolvedAt = $k->db->pdo()->query('SELECT resolved_at FROM intake_revision_requests LIMIT 1')->fetchColumn();
    assertTrue(is_string($resolvedAt) && $resolvedAt !== '', '対応日時が入っていない');
});

test('店舗: 提出できなければ依頼は閉じない', function (): void {
    $k = adminKernel();
    [$caseId, $storeCookies] = submittedCase($k, 'HP-2026-0733');
    $login = loginAdmin($k);

    $k->app->handle(revisionPost('HP-2026-0733', ['basic.legal_name'], (string)$login['csrf'],
        null, ['cookies' => $login['cookie']]));

    // 必須を欠けさせてから再提出する
    $k->db->pdo()->prepare('UPDATE intake_answers SET basic_json = :e WHERE intake_case_id = :id')
        ->execute([':e' => '{}', ':id' => $caseId]);

    $res = $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $storeCookies));
    assertSame(false, $res->body['submitted'], '不足があるのに提出できた');
    assertSame(1, $k->revisions->countFor($caseId, RevisionRequestService::STATUS_OPEN), '依頼が閉じてしまった');
});

/* ==================================================== 案件作成 */

test('作成: 管理画面から案件を作れる', function (): void {
    $k     = adminKernel();
    $login = loginAdmin($k);

    $res = $k->app->handle(adminPost('/admin/create', [
        'csrf_token'        => $login['csrf'],
        'shop_display_name' => '架空サロン ハルカゼ',
        'contract_type'     => 'standalone',
    ], ['cookies' => $login['cookie']]));

    assertSame(200, $res->status, '作成できない');
    $html = (string)$res->rawBody;
    assertTrue(str_contains($html, '案件を作成しました'), '完了画面でない');
    assertTrue(str_contains($html, 'この画面を閉じると再表示できません'), '注意書きが無い');

    $row = $k->db->pdo()->query('SELECT case_number, status FROM intake_cases')->fetch();
    assertTrue($row !== false, '案件が作られていない');
    assertSame('draft', (string)$row['status']);
    assertTrue(preg_match('/^HP-\d{6}-\d{4}$/', (string)$row['case_number']) === 1,
        '案件番号の形式が違う: ' . $row['case_number']);
});

test('作成: 案件番号は一意で、店舗名を含めない', function (): void {
    $k     = adminKernel();
    $login = loginAdmin($k);

    $numbers = [];
    for ($i = 0; $i < 5; ++$i) {
        $k->app->handle(adminPost('/admin/create', [
            'csrf_token'        => freshCsrf($k),
            'shop_display_name' => '架空サロン ' . $i,
            'contract_type'     => 'standalone',
        ], ['cookies' => $login['cookie']]));
    }

    foreach ($k->db->pdo()->query('SELECT case_number FROM intake_cases')->fetchAll() as $row) {
        $numbers[] = (string)$row['case_number'];
    }

    assertSame(5, count($numbers), '5件作られていない');
    assertSame(5, count(array_unique($numbers)), '案件番号が重複している');
    foreach ($numbers as $n) {
        assertTrue(!str_contains($n, '架空'), '案件番号に店舗名が入っている: ' . $n);
        assertTrue(preg_match('/^HP-\d{6}-\d{4}$/', $n) === 1, '形式が違う: ' . $n);
    }
});

test('作成: ご案内リンクは1回だけ表示し、平文をDBへ残さない', function (): void {
    $k     = adminKernel();
    $login = loginAdmin($k);

    $res  = $k->app->handle(adminPost('/admin/create', [
        'csrf_token'        => $login['csrf'],
        'shop_display_name' => '架空サロン',
        'contract_type'     => 'standalone',
    ], ['cookies' => $login['cookie']]));
    $html = (string)$res->rawBody;

    preg_match('#/start\#([A-Za-z0-9_-]{43})#', $html, $m);
    assertTrue(isset($m[1]), 'ご案内リンクが表示されていない');
    $token = $m[1];

    // DB に平文が無い
    $dump = '';
    foreach (['intake_tokens', 'intake_cases', 'intake_audit_events'] as $table) {
        foreach ($k->db->pdo()->query('SELECT * FROM ' . $table)->fetchAll() as $row) {
            $dump .= (string)json_encode($row, JSON_UNESCAPED_UNICODE);
        }
    }
    assertTrue(!str_contains($dump, $token), 'DB に平文 token が残っている');

    // ログにも出さない
    assertTrue(!str_contains((string)file_get_contents($k->config->logPath), $token), 'ログへ token が出ている');

    // 案件詳細を開いても再表示されない
    $number = (string)$k->db->pdo()->query('SELECT case_number FROM intake_cases')->fetchColumn();
    $detail = (string)$k->app->handle(adminGet('/admin/case',
        ['cookies' => $login['cookie'], 'query' => ['case' => $number]]))->rawBody;
    assertTrue(!str_contains($detail, $token), '詳細画面で token が再表示されている');

    // 応答は no-store
    assertTrue(str_contains($res->headers['Cache-Control'] ?? '', 'no-store'), 'no-store が無い');
});

test('作成: 発行したリンクで店舗が入れる', function (): void {
    $k     = adminKernel();
    $login = loginAdmin($k);

    $html = (string)$k->app->handle(adminPost('/admin/create', [
        'csrf_token'        => $login['csrf'],
        'shop_display_name' => '架空サロン',
        'contract_type'     => 'standalone',
    ], ['cookies' => $login['cookie']]))->rawBody;

    preg_match('#/start\#([A-Za-z0-9_-]{43})#', $html, $m);
    $res = $k->app->handle(jsonPost('/session/start', ['token' => $m[1]]));

    assertSame(200, $res->status, '発行したリンクで入れない');
    assertSame(true, $res->body['editable']);
});

test('作成: CSRF が古ければ通らない（二重送信の歯止め）', function (): void {
    $k     = adminKernel();
    $login = loginAdmin($k);
    $csrf  = (string)$login['csrf'];

    $first = $k->app->handle(adminPost('/admin/create', [
        'csrf_token'        => $csrf,
        'shop_display_name' => '架空サロン',
        'contract_type'     => 'standalone',
    ], ['cookies' => $login['cookie']]));
    assertSame(200, $first->status);

    // 同じ画面をもう一度送る（＝ブラウザの再送）
    $second = $k->app->handle(adminPost('/admin/create', [
        'csrf_token'        => $csrf,
        'shop_display_name' => '架空サロン',
        'contract_type'     => 'standalone',
    ], ['cookies' => $login['cookie']]));

    assertSame(403, $second->status, '同じ CSRF で二重に作れてしまう');
    assertSame(1, (int)$k->db->pdo()->query('SELECT COUNT(*) FROM intake_cases')->fetchColumn(),
        '案件が重複して作られた');
});

test('作成: 名前が空・長すぎる場合は作らない', function (): void {
    $k     = adminKernel();
    $login = loginAdmin($k);

    foreach (['', '   ', str_repeat('あ', 101)] as $name) {
        $res = $k->app->handle(adminPost('/admin/create', [
            'csrf_token'        => freshCsrf($k),
            'shop_display_name' => $name,
            'contract_type'     => 'standalone',
        ], ['cookies' => $login['cookie']]));
        assertSame(400, $res->status, '不正な名前が通る');
    }
    assertSame(0, (int)$k->db->pdo()->query('SELECT COUNT(*) FROM intake_cases')->fetchColumn());
});

test('作成: 未認証では作れない', function (): void {
    $k = adminKernel();

    $res = $k->app->handle(adminPost('/admin/create', [
        'csrf_token'        => str_repeat('A', 43),
        'shop_display_name' => '架空サロン',
    ]));

    assertSame(303, $res->status, '未認証で作成を試せてしまう');
    assertSame(0, (int)$k->db->pdo()->query('SELECT COUNT(*) FROM intake_cases')->fetchColumn());
});

/* ==================================================== Drive */

test('drive: URL は Google Drive の正式ホストだけ受け付ける', function (): void {
    foreach (DriveLink::ALLOWED_HOSTS as $host) {
        $r = DriveLink::checkUrl('https://' . $host . '/drive/folders/abc');
        assertSame(true, $r['ok'], $host . ' が拒否される');
    }

    foreach ([
        'http://drive.google.com/x',                 // https でない
        'https://drive.google.com.evil.example/x',   // 似せたホスト
        'https://evil.example/drive.google.com',     // パスに紛れ込ませる
        'https://bit.ly/xxxx',                       // 短縮URL
        'https://user:pass@drive.google.com/x',      // userinfo
        'https://drive.google.com:8443/x',           // ポート
        'javascript:alert(1)',
        'data:text/html,<b>x</b>',
        'file:///etc/passwd',
        'vbscript:x',
        "https://drive.google.com/x\nSet-Cookie: a=b", // 改行
        'https://drive.google.com/' . str_repeat('a', 500), // 長すぎる
        '',
        '   ',
    ] as $bad) {
        $r = DriveLink::checkUrl($bad);
        assertSame(false, $r['ok'], '不正なURLが通る: ' . $bad);
    }
});

test('drive: query と fragment は保持する', function (): void {
    $url = 'https://drive.google.com/drive/folders/abc?usp=sharing#x';
    $r   = DriveLink::checkUrl($url);

    assertSame(true, $r['ok'], 'query 付きが拒否される');
    assertSame($url, $r['url'], 'query / fragment が落ちている');
});

test('drive: メールの形式を検査する', function (): void {
    assertSame(true, DriveLink::checkEmail('owner@example.invalid')['ok']);

    foreach (['', 'not-an-email', 'a@', '@b', "a@b\nc", str_repeat('a', 250) . '@example.invalid'] as $bad) {
        assertSame(false, DriveLink::checkEmail($bad)['ok'], '不正なメールが通る: ' . $bad);
    }
});

test('drive: URL とメールを暗号化して保存する', function (): void {
    $k      = adminKernel();
    $caseId = $k->cases->create('HP-2026-0740', '架空サロン');

    $k->cases->setDriveFolder($caseId, FAKE_DRIVE_URL, 'HP-2026-0740 素材', FAKE_DRIVE_EMAIL);

    $row = $k->db->pdo()->query('SELECT drive_folder_url_enc, drive_shared_email_enc FROM intake_cases')->fetch();
    $raw = (string)$row['drive_folder_url_enc'] . (string)$row['drive_shared_email_enc'];

    assertTrue(!str_contains($raw, 'FAKE-0000'), 'URL が平文で保存されている');
    assertTrue(!str_contains($raw, 'shop-owner'), 'メールが平文で保存されている');

    assertSame(FAKE_DRIVE_URL, $k->cases->driveFolderUrl($caseId), 'URL を復号できない');
    assertSame(FAKE_DRIVE_EMAIL, $k->cases->driveSharedEmail($caseId), 'メールを復号できない');
});

test('drive: 不正な URL・メールは保存しない', function (): void {
    $k      = adminKernel();
    $caseId = $k->cases->create('HP-2026-0741', '架空サロン');

    foreach ([
        ['https://evil.example/x', FAKE_DRIVE_EMAIL],
        [FAKE_DRIVE_URL, 'not-an-email'],
    ] as [$url, $email]) {
        $thrown = false;
        try {
            $k->cases->setDriveFolder($caseId, $url, 'label', $email);
        } catch (\InvalidArgumentException $e) {
            $thrown = true;
        }
        assertTrue($thrown, '不正な組み合わせが通る');
    }

    $row = $k->db->pdo()->query('SELECT drive_folder_url_enc FROM intake_cases')->fetch();
    assertSame(null, $row['drive_folder_url_enc'], '拒否したのに保存されている');
});

test('drive: 認証済みの店舗にだけ案内を返す', function (): void {
    $k      = adminKernel();
    $caseId = $k->cases->create('HP-2026-0742', '架空サロン');
    $k->cases->setDriveFolder($caseId, FAKE_DRIVE_URL, 'HP-2026-0742 素材', FAKE_DRIVE_EMAIL);

    // 未認証では 404（案内も返らない）
    $anon = $k->app->handle(jsonGet('/case'));
    assertSame(404, $anon->status);
    assertTrue(!str_contains((string)json_encode($anon->body), 'FAKE-0000'), '未認証へ URL が漏れている');

    // 認証済みなら返る
    $token  = $k->tokens->issue($caseId);
    $secret = $k->app->handle(jsonPost('/session/start', ['token' => $token]))->cookies[0]['value'];
    $res    = $k->app->handle(jsonGet('/case', ['cookies' => [Config::COOKIE_NAME => $secret]]));

    assertSame(FAKE_DRIVE_URL, $res->body['drive']['folder_url'], '本人へ URL が返らない');
    assertSame(FAKE_DRIVE_EMAIL, $res->body['drive']['shared_email'], '本人へメールが返らない');
    assertSame('HP-2026-0742 素材', $res->body['drive']['folder_label']);
});

test('drive: URL とメールをログ・監査へ出さない', function (): void {
    $k      = adminKernel();
    $caseId = $k->cases->create('HP-2026-0743', '架空サロン');
    $k->cases->setDriveFolder($caseId, FAKE_DRIVE_URL, 'HP-2026-0743 素材', FAKE_DRIVE_EMAIL);

    $token  = $k->tokens->issue($caseId);
    $secret = $k->app->handle(jsonPost('/session/start', ['token' => $token]))->cookies[0]['value'];
    $k->app->handle(jsonGet('/case', ['cookies' => [Config::COOKIE_NAME => $secret]]));

    $log = (string)file_get_contents($k->config->logPath);
    assertTrue(!str_contains($log, 'FAKE-0000'), 'ログへ URL が出ている');
    assertTrue(!str_contains($log, 'shop-owner'), 'ログへメールが出ている');

    foreach ($k->db->pdo()->query('SELECT * FROM intake_audit_events')->fetchAll() as $row) {
        $dump = (string)json_encode($row, JSON_UNESCAPED_UNICODE);
        assertTrue(!str_contains($dump, 'FAKE-0000'), '監査へ URL が出ている');
        assertTrue(!str_contains($dump, 'shop-owner'), '監査へメールが出ている');
    }
    // 設定した事実は残る
    assertSame(1, $k->audit->countFor($caseId, 'drive_url_set'));
});

test('drive: 案件一覧へ URL・メールを出さない', function (): void {
    $k      = adminKernel();
    $caseId = $k->cases->create('HP-2026-0744', '架空サロン');
    $k->cases->setDriveFolder($caseId, FAKE_DRIVE_URL, 'HP-2026-0744 素材', FAKE_DRIVE_EMAIL);

    $login = loginAdmin($k);
    $html  = (string)$login['list']->rawBody;

    assertTrue(!str_contains($html, 'FAKE-0000'), '一覧へ URL が出ている');
    assertTrue(!str_contains($html, 'shop-owner'), '一覧へメールが出ている');
});

/* ==================================================== 書き出し */

test('export: Drive URL・共有メール・依頼本文を含めない', function (): void {
    $k = adminKernel();
    [$caseId, $storeCookies] = submittedCase($k, 'HP-2026-0750');
    $k->cases->setDriveFolder($caseId, FAKE_DRIVE_URL, 'HP-2026-0750 素材', FAKE_DRIVE_EMAIL);

    $login = loginAdmin($k);
    $secretMessage = 'ヒミツの連絡事項QQQ';
    $k->app->handle(revisionPost('HP-2026-0750', ['basic.legal_name'], (string)$login['csrf'],
        $secretMessage, ['cookies' => $login['cookie']]));
    $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $storeCookies));

    $body = (string)$k->app->handle(adminGet('/admin/export',
        ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-2026-0750']]))->rawBody;

    assertTrue(!str_contains($body, 'FAKE-0000'), '書き出しへ Drive URL が出ている');
    assertTrue(!str_contains($body, 'shop-owner'), '書き出しへ共有メールが出ている');
    assertTrue(!str_contains($body, $secretMessage), '書き出しへ依頼本文が出ている');
    assertTrue(!str_contains($body, 'drive_shared_email'), '列名が出ている');

    // 経緯そのものは残る
    $json = json_decode($body, true);
    assertSame(1, count($json['revision_requests']), '差し戻しの経緯が無い');
    $r = $json['revision_requests'][0];
    assertSame(['request_number', 'requested_paths', 'status', 'created_at', 'resolved_at'],
        array_keys($r), '想定外のキーがある');
    assertSame(1, $r['request_number']);
    assertSame(['basic.legal_name'], $r['requested_paths']);
    assertSame('resolved', $r['status']);
});

/* ==================================================== Origin / Fetch Metadata */

test('guard: Origin が正式なら通す', function (): void {
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0760');
    $login = loginAdmin($k);

    $res = $k->app->handle(revisionPost('HP-2026-0760', ['basic.legal_name'], (string)$login['csrf'],
        null, ['cookies' => $login['cookie'], 'origin' => TEST_ORIGIN]));

    assertSame(303, $res->status);
    assertSame('needs_revision', (string)$k->cases->find($caseId)['status']);
});

test('guard: Origin が別オリジンなら拒否する（Sec-Fetch があっても）', function (): void {
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0761');
    $login = loginAdmin($k);

    $req = new Request(
        method: 'POST',
        path: '/admin/revision/send',
        headers: [
            'Content-Type'   => 'application/x-www-form-urlencoded',
            'Origin'         => 'https://evil.example',
            'Sec-Fetch-Site' => 'same-origin',
        ],
        body: 'csrf_token=' . rawurlencode((string)$login['csrf'])
            . '&case=HP-2026-0761&paths%5B%5D=basic.legal_name',
        cookies: $login['cookie'],
        isHttps: true,
    );

    assertSame(403, $k->app->handle($req)->status, '別オリジンが通る');
    assertSame('submitted', (string)$k->cases->find($caseId)['status']);
});

test('guard: Origin=null は Sec-Fetch-Site: same-origin のときだけ通す', function (): void {
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0762');
    $login = loginAdmin($k);

    $make = static function (array $extra) use ($login): Request {
        return new Request(
            method: 'POST',
            path: '/admin/revision/send',
            headers: ['Content-Type' => 'application/x-www-form-urlencoded'] + $extra,
            body: 'csrf_token=' . rawurlencode((string)$login['csrf'])
                . '&case=HP-2026-0762&paths%5B%5D=basic.legal_name',
            cookies: $login['cookie'],
            isHttps: true,
        );
    };

    // 拒否されるもの
    foreach ([
        ['Origin' => 'null', 'Sec-Fetch-Site' => 'cross-site'],
        ['Origin' => 'null', 'Sec-Fetch-Site' => 'same-site'],
        ['Origin' => 'null', 'Sec-Fetch-Site' => 'none'],
        ['Origin' => 'null'],                       // Sec-Fetch 欠落
        [],                                          // Origin も Sec-Fetch も無い
        ['Sec-Fetch-Site' => 'cross-site'],
    ] as $headers) {
        $res = $k->app->handle($make($headers));
        assertSame(403, $res->status, '通ってはいけない: ' . json_encode($headers));
    }
    assertSame('submitted', (string)$k->cases->find($caseId)['status'], '状態が変わった');

    // 通るもの
    $ok = $k->app->handle($make(['Origin' => 'null', 'Sec-Fetch-Site' => 'same-origin']));
    assertSame(303, $ok->status, '同一オリジンの form 送信が通らない');
    assertSame('needs_revision', (string)$k->cases->find($caseId)['status']);
});

test('guard: Origin=null でも CSRF は必須', function (): void {
    $k = adminKernel();
    [$caseId] = submittedCase($k, 'HP-2026-0763');
    $login = loginAdmin($k);

    $req = new Request(
        method: 'POST',
        path: '/admin/revision/send',
        headers: [
            'Content-Type'   => 'application/x-www-form-urlencoded',
            'Origin'         => 'null',
            'Sec-Fetch-Site' => 'same-origin',
        ],
        body: 'case=HP-2026-0763&paths%5B%5D=basic.legal_name', // csrf_token なし
        cookies: $login['cookie'],
        isHttps: true,
    );

    assertSame(403, $k->app->handle($req)->status, 'CSRF 無しで通る');
    assertSame('submitted', (string)$k->cases->find($caseId)['status']);
});

test('guard: 店舗の JSON POST は変更していない', function (): void {
    // 4C / 4D の回帰。Origin 厳格検査だけで守る
    [$k, , $secret] = withSession('HP-2026-0764');

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

        // Origin: null も通さない
        $res2 = $k->app->handle(new Request(
            method: 'POST',
            path: $path,
            headers: [
                'Content-Type'   => 'application/json',
                'Origin'         => 'null',
                'Sec-Fetch-Site' => 'same-origin',
            ],
            body: '{}',
            cookies: [Config::COOKIE_NAME => $secret],
            isHttps: true,
        ));
        assertSame(403, $res2->status, $path . ' が Origin=null で通る');
    }
});

/* ==================================================== migration */

test('migration: v4 の表と列が作られる', function (): void {
    $k = makeKernel();

    $cols = array_column($k->db->pdo()->query("PRAGMA table_info('intake_cases')")->fetchAll(), 'name');
    assertTrue(in_array('drive_shared_email_enc', $cols, true), 'drive_shared_email_enc が無い');

    $cols = array_column($k->db->pdo()->query("PRAGMA table_info('intake_revision_requests')")->fetchAll(), 'name');
    foreach (['id', 'intake_case_id', 'request_number', 'requested_paths_json',
              'message', 'status', 'created_at', 'resolved_at'] as $need) {
        assertTrue(in_array($need, $cols, true), $need . ' が無い');
    }

    // 管理者を識別する列を作らない（SSOT §2.8-10）
    foreach ($cols as $col) {
        assertTrue(!str_contains($col, 'admin'), '管理者を識別する列がある: ' . $col);
        assertTrue(!str_contains($col, 'session'), 'session の列がある: ' . $col);
    }

    assertSame(Migrator::SCHEMA_VERSION, (int)$k->db->pdo()->query('PRAGMA user_version')->fetchColumn());
    assertSame(4, Migrator::SCHEMA_VERSION, 'スキーマ版が 4 でない');
});

test('migration: 案件内で request_number が一意', function (): void {
    $k      = makeKernel();
    $caseId = $k->cases->create('HP-2026-0770', '架空サロン');

    $k->revisions->insert($caseId, ['basic.legal_name'], null);

    $thrown = false;
    try {
        $k->db->pdo()->prepare(
            'INSERT INTO intake_revision_requests
                (intake_case_id, request_number, requested_paths_json, status, created_at)
             VALUES (:id, 1, :p, :s, :n)'
        )->execute([':id' => $caseId, ':p' => '[]', ':s' => 'open', ':n' => '2026-08-27T00:00:00Z']);
    } catch (\PDOException $e) {
        $thrown = true;
    }
    assertTrue($thrown, '同じ通し番号が入ってしまう');
});

test('migration: v3 のDBへ後から適用でき、既存行を壊さない', function (): void {
    $k   = makeKernel();
    $pdo = $k->db->pdo();

    // v3 相当へ戻す
    $pdo->exec('DROP TABLE IF EXISTS intake_revision_requests');
    $pdo->exec('PRAGMA user_version = 3');

    $caseId = $k->cases->create('HP-2026-0771', '架空サロン');
    $before = (string)$pdo->query('SELECT case_number FROM intake_cases')->fetchColumn();

    (new Migrator($k->db))->migrate();

    $cols = array_column($pdo->query("PRAGMA table_info('intake_revision_requests')")->fetchAll(), 'name');
    assertTrue(in_array('request_number', $cols, true), '表が作られていない');
    assertSame($before, (string)$pdo->query('SELECT case_number FROM intake_cases')->fetchColumn(),
        '既存行が壊れている');
    assertTrue($caseId > 0);
});

test('migration: 何度実行しても同じ結果になる', function (): void {
    $k      = makeKernel();
    $pdo    = $k->db->pdo();
    $caseId = $k->cases->create('HP-2026-0772', '架空サロン');
    $k->revisions->insert($caseId, ['menus'], 'テスト');

    $schemaBefore = $pdo->query(
        "SELECT group_concat(name || '|' || COALESCE(sql, '')) FROM sqlite_master ORDER BY name"
    )->fetchColumn();

    for ($i = 0; $i < 3; ++$i) {
        $pdo->exec('PRAGMA user_version = 1');
        (new Migrator($k->db))->migrate();
        (new Migrator($k->db))->migrate();
    }

    assertSame($schemaBefore, $pdo->query(
        "SELECT group_concat(name || '|' || COALESCE(sql, '')) FROM sqlite_master ORDER BY name"
    )->fetchColumn(), 'スキーマが再実行で変化した');
    assertSame(1, $k->revisions->countFor($caseId), '再実行でデータが増減した');
});

test('migration: v4 の DDL も 3.26.0 非対応構文を含まない', function (): void {
    $sql = strtoupper(implode(' ', array_merge(
        [Migrator::ADD_DRIVE_SHARED_EMAIL],
        Migrator::statementsV4(),
    )));

    foreach (['VACUUM INTO', 'RETURNING', ') STRICT', 'DROP COLUMN', 'GENERATED ALWAYS'] as $banned) {
        assertTrue(!str_contains($sql, $banned), 'v4 の DDL に ' . $banned . ' が含まれる');
    }
    foreach (['JSON_EXTRACT', 'JSON_EACH', 'JSON_SET', 'JSON_ARRAY'] as $fn) {
        assertTrue(!str_contains($sql, $fn), 'v4 の DDL が SQL側 JSON 関数へ依存している');
    }
});
