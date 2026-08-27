<?php
/**
 * 保存・提出のテスト（SSOT §2.3 / §2.4 / §3 / §6）
 */
declare(strict_types=1);

use SmartLabo\Intake\Config;
use SmartLabo\Intake\Service\AnswerService;

/** session まで進めた状態を作る */
function withSession(string $caseNumber, ?TestClock $clock = null): array
{
    $k      = makeKernel($clock);
    $caseId = $k->cases->create($caseNumber, '架空サロン');
    $token  = $k->tokens->issue($caseId);
    $secret = $k->app->handle(jsonPost('/session/start', ['token' => $token]))->cookies[0]['value'];

    return [$k, $caseId, $secret, $token];
}

test('save: JSON 11分類のみ許可し、未知キーは拒否', function (): void {
    [$k, , $secret] = withSession('HP-2026-0300');

    $res = $k->app->handle(jsonPost('/answers/save', [
        'version'  => 1,
        'sections' => ['basic' => ['legal_name' => '架空サロン']],
    ], ['cookies' => [Config::COOKIE_NAME => $secret]]));
    assertSame(200, $res->status, '正当な分類が保存できない');

    $res = $k->app->handle(jsonPost('/answers/save', [
        'version'  => 2,
        'sections' => ['unknown_section' => ['a' => 1]],
    ], ['cookies' => [Config::COOKIE_NAME => $secret]]));
    assertSame(400, $res->status, '未知キーが通ってしまう');
    assertSame('bad_request', $res->body['error']);
});

test('save: 楽観ロック（古い version は 409）', function (): void {
    [$k, , $secret] = withSession('HP-2026-0301');
    $cookies = ['cookies' => [Config::COOKIE_NAME => $secret]];

    $res = $k->app->handle(jsonPost('/answers/save', [
        'version' => 1, 'sections' => ['basic' => ['legal_name' => 'A']],
    ], $cookies));
    assertSame(200, $res->status);
    assertSame(2, $res->body['version']);

    // 同じ version=1 で再送（別端末が先に更新した状況）
    $res = $k->app->handle(jsonPost('/answers/save', [
        'version' => 1, 'sections' => ['basic' => ['legal_name' => 'B']],
    ], $cookies));
    assertSame(409, $res->status, '競合が検出されない');
    assertSame('conflict', $res->body['error']);
});

test('save: 配列上限を超えたら保存を拒否（切り捨てない）', function (): void {
    [$k, $caseId, $secret] = withSession('HP-2026-0302');

    $tooMany = array_fill(0, AnswerService::ARRAY_CAPS['menus'] + 1, ['name' => 'x']);
    $res     = $k->app->handle(jsonPost('/answers/save', [
        'version' => 1, 'sections' => ['menus' => $tooMany],
    ], ['cookies' => [Config::COOKIE_NAME => $secret]]));

    assertSame(400, $res->status, '上限超過が通ってしまう');
    assertSame([], $k->answers->get($caseId)['sections']['menus'], '一部でも保存されている');
});

test('save: version が整数でなければ 400', function (): void {
    [$k, , $secret] = withSession('HP-2026-0303');
    $res = $k->app->handle(jsonPost('/answers/save', [
        'version' => '1', 'sections' => ['basic' => []],
    ], ['cookies' => [Config::COOKIE_NAME => $secret]]));
    assertSame(400, $res->status);
});

test('save: rate limit は 10分60回', function (): void {
    [$k, , $secret] = withSession('HP-2026-0304');
    $cookies = ['cookies' => [Config::COOKIE_NAME => $secret]];

    $version = 1;
    for ($i = 0; $i < 60; ++$i) {
        $res = $k->app->handle(jsonPost('/answers/save', [
            'version' => $version, 'sections' => ['basic' => ['legal_name' => 'v' . $i]],
        ], $cookies));
        assertSame(200, $res->status, ($i + 1) . '回目が失敗した');
        $version = $res->body['version'];
    }
    $res = $k->app->handle(jsonPost('/answers/save', [
        'version' => $version, 'sections' => ['basic' => ['legal_name' => 'over']],
    ], $cookies));
    assertSame(429, $res->status, '61回目が429でない');
});

test('submit: 未入力なら不足パスを返し、値は返さない', function (): void {
    [$k, $caseId, $secret] = withSession('HP-2026-0305');

    $res = $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], ['cookies' => [Config::COOKIE_NAME => $secret]]));
    assertSame(200, $res->status);
    assertSame(false, $res->body['submitted']);
    assertTrue($res->body['missing_count'] > 0, '不足が検出されない');

    // 不足はパスのみ（値・PII を含まない）
    foreach ($res->body['missing'] as $path) {
        assertTrue(preg_match('/^[a-z_]+(\.[a-z0-9_]+|\[[0-9]+\])*$/i', (string)$path) === 1, '不足に値が混ざっている: ' . $path);
    }

    // 検証失敗も履歴に残る（件数のみ）
    assertSame(1, $k->answers->historyCount($caseId));
    $row = $k->db->pdo()->query('SELECT * FROM intake_submission_history LIMIT 1')->fetch();
    assertSame('validation_error', $row['result_code']);
    assertTrue((int)$row['missing_count'] > 0);
});

test('submit: 条件を満たせば submitted へ遷移する', function (): void {
    [$k, $caseId, $secret] = withSession('HP-2026-0306');
    $cookies = ['cookies' => [Config::COOKIE_NAME => $secret]];

    $res = $k->app->handle(jsonPost('/answers/save', [
        'version' => 1, 'sections' => completeSections(),
    ], $cookies));
    assertSame(200, $res->status, '完全な回答が保存できない: ' . json_encode($res->body, JSON_UNESCAPED_UNICODE));

    $res = $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies));
    assertSame(200, $res->status);
    assertSame(true, $res->body['submitted'], json_encode($res->body, JSON_UNESCAPED_UNICODE));
    assertSame(false, $res->body['already_submitted']);
    assertSame('submitted', (string)$k->cases->find($caseId)['status']);
});

test('submit: 二重送信は状態を変えず履歴も増やさない', function (): void {
    [$k, $caseId, $secret] = withSession('HP-2026-0307');
    $cookies = ['cookies' => [Config::COOKIE_NAME => $secret]];

    // 同じ submission_id での再送 ＝「同じ提出要求の再試行」（SSOT v1.3 §6.4）
    $sid = newSubmissionId();

    $k->app->handle(jsonPost('/answers/save', ['version' => 1, 'sections' => completeSections()], $cookies));
    $k->app->handle(jsonPost('/submit', ['submission_id' => $sid], $cookies));

    $historyBefore = $k->answers->historyCount($caseId);
    $updatedBefore = (string)$k->cases->find($caseId)['submitted_at'];

    $res = $k->app->handle(jsonPost('/submit', ['submission_id' => $sid], $cookies));
    assertSame(200, $res->status);
    assertSame(true, $res->body['already_submitted'], '二重送信が検出されない');
    assertSame($historyBefore, $k->answers->historyCount($caseId), '履歴が増えている');
    assertSame($updatedBefore, (string)$k->cases->find($caseId)['submitted_at'], 'submitted_at が上書きされている');
});

test('submit: 提出後は編集できない（not_editable）', function (): void {
    [$k, , $secret] = withSession('HP-2026-0308');
    $cookies = ['cookies' => [Config::COOKIE_NAME => $secret]];

    $k->app->handle(jsonPost('/answers/save', ['version' => 1, 'sections' => completeSections()], $cookies));
    $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies));

    $res = $k->app->handle(jsonPost('/answers/save', [
        'version' => 2, 'sections' => ['basic' => ['legal_name' => 'X']],
    ], $cookies));
    assertSame(409, $res->status);
    assertSame('not_editable', $res->body['error']);
});

test('submit: needs_revision へ戻すと再提出できる', function (): void {
    [$k, $caseId, $secret] = withSession('HP-2026-0309');
    $cookies = ['cookies' => [Config::COOKIE_NAME => $secret]];

    $k->app->handle(jsonPost('/answers/save', ['version' => 1, 'sections' => completeSections()], $cookies));
    $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies));

    $k->cases->transitionTo($caseId, 'needs_revision');
    $res = $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies));
    assertSame(200, $res->status);
    assertSame(true, $res->body['submitted']);
    assertSame(false, $res->body['already_submitted']);
    assertSame(2, $k->answers->historyCount($caseId), '再提出が履歴に残らない');
});

test('submit: 提出履歴は回答本文・個人情報を持たない', function (): void {
    [$k, $caseId, $secret] = withSession('HP-2026-0310');
    $cookies = ['cookies' => [Config::COOKIE_NAME => $secret]];

    $k->app->handle(jsonPost('/answers/save', ['version' => 1, 'sections' => completeSections()], $cookies));
    $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies));

    $rows = $k->db->pdo()->query('SELECT * FROM intake_submission_history')->fetchAll();
    foreach ($rows as $row) {
        $dump = json_encode($row, JSON_UNESCAPED_UNICODE);
        foreach (['ハルカゼ', '架空県', '03-0000-0000', 'internal@example.invalid'] as $pii) {
            assertTrue(!str_contains((string)$dump, $pii), '履歴に ' . $pii . ' が残っている');
        }
    }
});

test('case: 状態遷移は許可された経路のみ', function (): void {
    $k      = makeKernel();
    $caseId = $k->cases->create('HP-2026-0311', '架空サロン');

    $thrown = false;
    try {
        $k->cases->transitionTo($caseId, 'locked'); // draft → locked は不可
    } catch (\DomainException $e) {
        $thrown = true;
    }
    assertTrue($thrown, '許可されていない遷移が通る');

    $k->cases->transitionTo($caseId, 'submitted');
    $k->cases->transitionTo($caseId, 'reviewed');
    $k->cases->transitionTo($caseId, 'locked');
    assertSame('locked', (string)$k->cases->find($caseId)['status']);
});

test('case: /case は回答と version を返す', function (): void {
    [$k, , $secret] = withSession('HP-2026-0312');
    $res = $k->app->handle(jsonGet('/case', ['cookies' => [Config::COOKIE_NAME => $secret]]));

    assertSame(200, $res->status);
    assertSame('HP-2026-0312', $res->body['case_number']);
    assertSame(1, $res->body['version']);
    assertSame(true, $res->body['editable']);
    assertSame(11, count($res->body['sections']));
});

test('drive: URL は暗号化して保存し、復号できる（平文で保存しない）', function (): void {
    $k      = makeKernel();
    $caseId = $k->cases->create('HP-2026-0313', '架空サロン');
    $url    = 'https://drive.example.invalid/folders/fake-folder-id';

    $k->cases->setDriveFolder($caseId, $url, 'HP-2026-0313 写真');

    $raw = (string)$k->db->pdo()->query('SELECT drive_folder_url_enc FROM intake_cases')->fetchColumn();
    assertTrue(!str_contains($raw, 'drive.example.invalid'), '平文で保存されている');
    assertSame($url, $k->cases->driveFolderUrl($caseId), '復号できない');
});

test('drive: http の URL は受け付けない', function (): void {
    $k      = makeKernel();
    $caseId = $k->cases->create('HP-2026-0314', '架空サロン');

    $thrown = false;
    try {
        $k->cases->setDriveFolder($caseId, 'http://drive.example.invalid/x', 'label');
    } catch (\InvalidArgumentException $e) {
        $thrown = true;
    }
    assertTrue($thrown, 'http が通ってしまう');
});
