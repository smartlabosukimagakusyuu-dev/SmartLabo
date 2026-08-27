<?php
/**
 * 最終提出の冪等化テスト（SSOT v1.3 §2.4 / §6.4 — HP-ONBOARDING-4B-R1）
 *
 * 守る対象は3つ。**状態** ／ **提出履歴** ／ **監査イベント**。
 * どれか1つでも重複すると、後工程（Operations・請求参照）が実態と食い違う。
 */
declare(strict_types=1);

use SmartLabo\Intake\Config;
use SmartLabo\Intake\Migrator;
use SmartLabo\Intake\Service\AnswerService;

/** 提出できる状態まで進めた案件を作る */
function submittableCase(string $caseNumber): array
{
    [$k, $caseId, $secret] = withSession($caseNumber);
    $cookies = ['cookies' => [Config::COOKIE_NAME => $secret]];
    $k->app->handle(jsonPost('/answers/save', ['version' => 1, 'sections' => completeSections()], $cookies));

    return [$k, $caseId, $cookies];
}

function auditCount(object $k, int $caseId): int
{
    $stmt = $k->db->pdo()->prepare('SELECT COUNT(*) FROM intake_audit_events WHERE intake_case_id = :id');
    $stmt->execute([':id' => $caseId]);

    return (int)$stmt->fetchColumn();
}

// ---------------------------------------------------------------- 入力検証

test('submit: submission_id が無ければ 400', function (): void {
    [$k, $caseId, $cookies] = submittableCase('HP-2026-0400');

    $res = $k->app->handle(jsonPost('/submit', [], $cookies));
    assertSame(400, $res->status, 'submission_id 無しが通ってしまう');
    assertSame('bad_request', $res->body['error']);

    // 副作用が無い（履歴も状態も動かない）
    assertSame(0, $k->answers->historyCount($caseId), '400 なのに履歴が増えている');
    assertSame('draft', (string)$k->cases->find($caseId)['status']);
});

test('submit: submission_id が UUID v4 でなければ 400', function (): void {
    [$k, $caseId, $cookies] = submittableCase('HP-2026-0401');

    $invalid = [
        'not-a-uuid',
        '',
        '00000000-0000-0000-0000-000000000000',              // version が 4 でない
        '3f2504e0-4f89-11d3-9a0c-0305e82c3301',              // v1
        '3f2504e0-4f89-41d3-ca0c-0305e82c3301',              // variant が 10xx でない
        '3f2504e0-4f89-41d3-9a0c-0305e82c33011',             // 長すぎる
        '3f2504e04f8941d39a0c0305e82c3301',                  // ハイフン無し
        '3f2504e0-4f89-41d3-9a0c-0305e82c330g',              // 16進以外
    ];
    // ★提出のレート制限（10分5回）は submission_id の検証より先に効く。
    //   検証そのものを確かめたいので、要求ごとに別の送信元にしてバケットを分ける
    $ip = 0;
    foreach ($invalid as $value) {
        $opts = $cookies + ['ip' => '198.51.100.' . ++$ip];
        $res  = $k->app->handle(jsonPost('/submit', ['submission_id' => $value], $opts));
        assertSame(400, $res->status, '不正な submission_id が通る: ' . $value);
        assertSame('bad_request', $res->body['error']);
    }

    // 型が文字列でない場合も 400
    foreach ([123, true, null, ['a'], 1.5] as $value) {
        $opts = $cookies + ['ip' => '198.51.100.' . ++$ip];
        $res  = $k->app->handle(jsonPost('/submit', ['submission_id' => $value], $opts));
        assertSame(400, $res->status, '文字列でない submission_id が通る');
    }

    assertSame(0, $k->answers->historyCount($caseId), '400 なのに履歴が増えている');
});

test('submit: UUID v4 の判定は大文字も受け付け、保存は小文字へ揃える', function (): void {
    [$k, $caseId, $cookies] = submittableCase('HP-2026-0402');
    $sid = strtoupper(newSubmissionId());

    assertTrue(AnswerService::isValidSubmissionId($sid), '大文字の UUID v4 が弾かれる');

    $res = $k->app->handle(jsonPost('/submit', ['submission_id' => $sid], $cookies));
    assertSame(200, $res->status);

    $stored = (string)$k->db->pdo()->query('SELECT submission_id FROM intake_submission_history LIMIT 1')->fetchColumn();
    assertSame(strtolower($sid), $stored, '保存が小文字へ揃っていない');

    // 大文字で再送しても「同じ要求」として扱う（重複行を作らせない）
    $res = $k->app->handle(jsonPost('/submit', ['submission_id' => $sid], $cookies));
    assertSame(200, $res->status);
    assertSame(true, $res->body['already_submitted']);
    assertSame(1, $k->answers->historyCount($caseId), '大文字小文字の違いで履歴が増えている');
});

// ---------------------------------------------------------------- 初回提出

test('submit: 初回提出が成功し、submission_id が履歴へ保存される', function (): void {
    [$k, $caseId, $cookies] = submittableCase('HP-2026-0403');
    $sid = newSubmissionId();

    $res = $k->app->handle(jsonPost('/submit', ['submission_id' => $sid], $cookies));
    assertSame(200, $res->status);
    assertSame(true, $res->body['submitted']);
    assertSame(false, $res->body['already_submitted']);
    assertSame('submitted', (string)$k->cases->find($caseId)['status']);

    $rows = $k->db->pdo()->query('SELECT * FROM intake_submission_history')->fetchAll();
    assertSame(1, count($rows), '履歴が1件でない');
    assertSame($sid, (string)$rows[0]['submission_id'], 'submission_id が履歴へ保存されていない');
    assertSame('ok', (string)$rows[0]['result_code']);
});

test('submit: 検証エラーでも submission_id を記録する', function (): void {
    [$k, $caseId, $secret] = withSession('HP-2026-0404');
    $cookies = ['cookies' => [Config::COOKIE_NAME => $secret]];
    $sid     = newSubmissionId();

    $res = $k->app->handle(jsonPost('/submit', ['submission_id' => $sid], $cookies));
    assertSame(200, $res->status);
    assertSame(false, $res->body['submitted']);

    $row = $k->db->pdo()->query('SELECT * FROM intake_submission_history LIMIT 1')->fetch();
    assertSame('validation_error', (string)$row['result_code']);
    assertSame($sid, (string)$row['submission_id']);
    assertSame('draft', (string)$k->cases->find($caseId)['status'], '検証エラーで状態が動いている');
});

// ------------------------------------------------ 同一 submission_id の再送

test('submit: 同一 submission_id の再送は同じ成功結果を返す', function (): void {
    [$k, $caseId, $cookies] = submittableCase('HP-2026-0405');
    $sid = newSubmissionId();

    $first = $k->app->handle(jsonPost('/submit', ['submission_id' => $sid], $cookies));
    assertSame(200, $first->status);
    assertSame(true, $first->body['submitted']);

    $submittedAt = (string)$k->cases->find($caseId)['submitted_at'];

    for ($i = 0; $i < 3; ++$i) {
        $again = $k->app->handle(jsonPost('/submit', ['submission_id' => $sid], $cookies));
        assertSame(200, $again->status, ($i + 2) . '回目が 200 でない');
        assertSame(true, $again->body['submitted'], ($i + 2) . '回目が成功扱いでない');
        assertSame(true, $again->body['already_submitted']);
    }

    assertSame('submitted', (string)$k->cases->find($caseId)['status'], '状態が変わっている');
    assertSame($submittedAt, (string)$k->cases->find($caseId)['submitted_at'], 'submitted_at が上書きされている');
});

test('submit: 同一 submission_id の再送で履歴が増えない', function (): void {
    [$k, $caseId, $cookies] = submittableCase('HP-2026-0406');
    $sid = newSubmissionId();

    $k->app->handle(jsonPost('/submit', ['submission_id' => $sid], $cookies));
    $before = $k->answers->historyCount($caseId);

    for ($i = 0; $i < 4; ++$i) {
        $res = $k->app->handle(jsonPost('/submit', ['submission_id' => $sid], $cookies));
        // 429 で素通りしていないことを確かめる（テストが空振りしないように）
        assertSame(200, $res->status, ($i + 2) . '回目が 200 でない');
    }

    assertSame($before, $k->answers->historyCount($caseId), '再送で履歴が増えている');
    assertSame(1, $before, '初回の履歴が1件でない');
});

test('submit: 同一 submission_id の再送で監査イベントが増えない', function (): void {
    [$k, $caseId, $cookies] = submittableCase('HP-2026-0407');
    $sid = newSubmissionId();

    $k->app->handle(jsonPost('/submit', ['submission_id' => $sid], $cookies));
    $before = auditCount($k, $caseId);

    for ($i = 0; $i < 4; ++$i) {
        $res = $k->app->handle(jsonPost('/submit', ['submission_id' => $sid], $cookies));
        assertSame(200, $res->status, ($i + 2) . '回目が 200 でない');
    }

    assertSame($before, auditCount($k, $caseId), '再送で監査イベントが増えている');

    $submitted = (int)$k->db->pdo()->query(
        "SELECT COUNT(*) FROM intake_audit_events WHERE event_type = 'submitted'"
    )->fetchColumn();
    assertSame(1, $submitted, "submitted の監査イベントが1件でない");
});

test('submit: 検証エラーの再送も履歴・監査を増やさない', function (): void {
    [$k, $caseId, $secret] = withSession('HP-2026-0408');
    $cookies = ['cookies' => [Config::COOKIE_NAME => $secret]];
    $sid     = newSubmissionId();

    $first = $k->app->handle(jsonPost('/submit', ['submission_id' => $sid], $cookies));
    assertSame(false, $first->body['submitted']);

    $historyBefore = $k->answers->historyCount($caseId);
    $auditBefore   = auditCount($k, $caseId);

    $again = $k->app->handle(jsonPost('/submit', ['submission_id' => $sid], $cookies));
    assertSame(200, $again->status);
    assertSame(false, $again->body['submitted']);
    assertSame($first->body['missing_count'], $again->body['missing_count'], '不足件数が変わっている');

    assertSame($historyBefore, $k->answers->historyCount($caseId), '再送で履歴が増えている');
    assertSame($auditBefore, auditCount($k, $caseId), '再送で監査が増えている');
});

// ---------------------------------------------- 異なる submission_id の再送

test('submit: 異なる submission_id で提出済み案件へ再提出すると 409', function (): void {
    [$k, $caseId, $cookies] = submittableCase('HP-2026-0409');

    $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies));
    assertSame('submitted', (string)$k->cases->find($caseId)['status']);

    $res = $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies));
    assertSame(409, $res->status, '異なる submission_id が 409 にならない');
    assertSame('already_submitted', $res->body['error']);
});

test('submit: 409 の本文へ既存 submission_id・提出済み内容を含めない', function (): void {
    [$k, , $cookies] = submittableCase('HP-2026-0410');

    $firstSid = newSubmissionId();
    $k->app->handle(jsonPost('/submit', ['submission_id' => $firstSid], $cookies));

    $secondSid = newSubmissionId();
    $res       = $k->app->handle(jsonPost('/submit', ['submission_id' => $secondSid], $cookies));
    $dump      = (string)json_encode($res->body, JSON_UNESCAPED_UNICODE);

    assertTrue(!str_contains($dump, $firstSid), '409 本文へ既存 submission_id が出ている');
    assertTrue(!str_contains($dump, $secondSid), '409 本文へ送られた submission_id が出ている');
    foreach (['ハルカゼ', '架空県', '03-0000-0000', 'internal@example.invalid', 'submitted_at'] as $leak) {
        assertTrue(!str_contains($dump, $leak), '409 本文へ ' . $leak . ' が出ている');
    }
    // 固定文言だけを返す
    assertSame(['ok', 'error', 'message'], array_keys($res->body), '409 本文に余分なキーがある');
});

test('submit: 異なる submission_id の 409 で履歴・監査を増やさない', function (): void {
    [$k, $caseId, $cookies] = submittableCase('HP-2026-0411');

    $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies));
    $historyBefore = $k->answers->historyCount($caseId);
    $auditBefore   = auditCount($k, $caseId);

    for ($i = 0; $i < 3; ++$i) {
        $res = $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies));
        assertSame(409, $res->status);
    }

    assertSame($historyBefore, $k->answers->historyCount($caseId), '409 で履歴が増えている');
    assertSame($auditBefore, auditCount($k, $caseId), '409 で監査が増えている');
});

test('submit: 案件が違えば同じ submission_id を使える', function (): void {
    [$k, $caseIdA, $cookiesA] = submittableCase('HP-2026-0412');
    $sid = newSubmissionId();

    $k->app->handle(jsonPost('/submit', ['submission_id' => $sid], $cookiesA));

    // 同じ Kernel（＝同じDB）に別案件を作り、同じ submission_id で提出する
    $caseIdB = $k->cases->create('HP-2026-0413', '架空サロン');
    $tokenB  = $k->tokens->issue($caseIdB);
    $secretB = $k->app->handle(jsonPost('/session/start', ['token' => $tokenB]))->cookies[0]['value'];
    $cookiesB = ['cookies' => [Config::COOKIE_NAME => $secretB]];
    $k->app->handle(jsonPost('/answers/save', ['version' => 1, 'sections' => completeSections()], $cookiesB));

    $res = $k->app->handle(jsonPost('/submit', ['submission_id' => $sid], $cookiesB));
    assertSame(200, $res->status, '別案件で同じ submission_id が使えない');
    assertSame(true, $res->body['submitted']);
    assertSame(false, $res->body['already_submitted'], '別案件の提出が再送と誤判定されている');
    assertSame('submitted', (string)$k->cases->find($caseIdB)['status']);
});

// ------------------------------------------------------ DB の最後の防御線

test('submit: 一意制約が同一案件＋submission_id の重複を止める', function (): void {
    [$k, $caseId, $cookies] = submittableCase('HP-2026-0414');
    $sid = newSubmissionId();

    $k->app->handle(jsonPost('/submit', ['submission_id' => $sid], $cookies));

    // アプリの判定を迂回して、同じ組み合わせを直接入れようとする（競合相当）
    $thrown = false;
    try {
        $k->answers->recordHistory($caseId, 'submitted', 1, 'ok', 0, 0, $sid);
    } catch (\PDOException $e) {
        $thrown = true;
    }
    assertTrue($thrown, '一意制約が効いていない');
    assertSame(1, $k->answers->historyCount($caseId), '重複履歴が入っている');
});

test('submit: 競合例外は外へ漏れず、固定応答へ変換される', function (): void {
    [$k, $caseId, $cookies] = submittableCase('HP-2026-0415');
    $sid = newSubmissionId();

    // 応答が消えた直後に別プロセスが先に記録した、という状況を作る
    $k->answers->recordHistory($caseId, 'submitted', 1, 'ok', 0, 0, $sid);
    $before = $k->answers->historyCount($caseId);

    $res = $k->app->handle(jsonPost('/submit', ['submission_id' => $sid], $cookies));
    assertSame(200, $res->status, '競合が 500 になっている');
    assertSame(true, $res->body['already_submitted'], '先に記録された結果を返していない');
    assertSame($before, $k->answers->historyCount($caseId), '競合後に履歴が増えている');
    assertTrue(!str_contains((string)json_encode($res->body), 'SQLSTATE'), '例外の内容が漏れている');
});

test('submit: submission_id は NULL の行を複数持てる（既存行との互換）', function (): void {
    $k      = makeKernel();
    $caseId = $k->cases->create('HP-2026-0416', '架空サロン');

    // v1.2 以前に記録された行を模す（submission_id を渡さない）
    $k->answers->recordHistory($caseId, 'submitted', 1, 'ok', 10, 0);
    $k->answers->recordHistory($caseId, 'submitted', 1, 'validation_error', 5, 3);

    assertSame(2, $k->answers->historyCount($caseId), 'NULL 行が一意制約に引っかかっている');
    $rows = $k->db->pdo()->query('SELECT submission_id FROM intake_submission_history')->fetchAll();
    foreach ($rows as $row) {
        assertSame(null, $row['submission_id'], '既存行の submission_id が NULL でない');
    }
});

// ---------------------------------------------------------------- migration

test('migration: submission_id 列と部分一意索引が作られる', function (): void {
    $k    = makeKernel();
    $cols = array_column(
        $k->db->pdo()->query("PRAGMA table_info('intake_submission_history')")->fetchAll(),
        'name'
    );
    assertTrue(in_array('submission_id', $cols, true), 'submission_id 列が無い');

    $index = $k->db->pdo()->query(
        "SELECT sql FROM sqlite_master
          WHERE type = 'index' AND name = 'idx_history_submission_id'"
    )->fetchColumn();
    assertTrue($index !== false, '部分一意索引が無い');
    assertTrue(str_contains(strtoupper((string)$index), 'UNIQUE'), '索引が UNIQUE でない');
    assertTrue(str_contains(strtoupper((string)$index), 'WHERE'), '索引が部分索引でない');

    assertSame(2, (int)$k->db->pdo()->query('PRAGMA user_version')->fetchColumn(), 'user_version が 2 でない');
});

test('migration: v1 のDBへ後から適用でき、既存行を壊さない', function (): void {
    $k = makeKernel();

    // v1 相当のDBを作り直す（submission_id 列が無い状態）
    $pdo = $k->db->pdo();
    $pdo->exec('DROP INDEX IF EXISTS idx_history_submission_id');
    $pdo->exec('DROP TABLE IF EXISTS intake_submission_history');
    foreach (Migrator::statements() as $sql) {
        $pdo->exec($sql);
    }
    $pdo->exec('PRAGMA user_version = 1');

    $caseId = $k->cases->create('HP-2026-0417', '架空サロン');
    $pdo->prepare(
        'INSERT INTO intake_submission_history
            (intake_case_id, event_type, schema_version, submitted_at, result_code, field_count, missing_count)
         VALUES (:id, :e, 1, :t, :r, 3, 0)'
    )->execute([':id' => $caseId, ':e' => 'submitted', ':t' => '2026-08-27T00:00:00+00:00', ':r' => 'ok']);

    $cols = array_column($pdo->query("PRAGMA table_info('intake_submission_history')")->fetchAll(), 'name');
    assertTrue(!in_array('submission_id', $cols, true), 'v1 相当のDBを作れていない');

    // ここで v2 へ上げる
    (new Migrator($k->db))->migrate();

    $cols = array_column($pdo->query("PRAGMA table_info('intake_submission_history')")->fetchAll(), 'name');
    assertTrue(in_array('submission_id', $cols, true), '列が追加されていない');

    $row = $pdo->query('SELECT * FROM intake_submission_history LIMIT 1')->fetch();
    assertSame(null, $row['submission_id'], '既存行が NULL でない');
    assertSame('ok', (string)$row['result_code'], '既存行が壊れている');
    assertSame(3, (int)$row['field_count'], '既存行が壊れている');
});

test('migration: 何度実行しても同じ結果になる（再実行可能）', function (): void {
    $k      = makeKernel();
    $pdo    = $k->db->pdo();
    $caseId = $k->cases->create('HP-2026-0418', '架空サロン');
    $sid    = newSubmissionId();
    $k->answers->recordHistory($caseId, 'submitted', 1, 'ok', 3, 0, $sid);

    $schemaBefore = $pdo->query(
        "SELECT group_concat(name || '|' || COALESCE(sql, '')) FROM sqlite_master ORDER BY name"
    )->fetchColumn();

    for ($i = 0; $i < 3; ++$i) {
        // user_version を戻して、上げ直しの経路も通す
        $pdo->exec('PRAGMA user_version = 1');
        (new Migrator($k->db))->migrate();
        (new Migrator($k->db))->migrate();
    }

    assertSame($schemaBefore, $pdo->query(
        "SELECT group_concat(name || '|' || COALESCE(sql, '')) FROM sqlite_master ORDER BY name"
    )->fetchColumn(), 'スキーマが再実行で変化した');

    assertSame(1, $k->answers->historyCount($caseId), '再実行でデータが増減した');
    assertSame($sid, (string)$pdo->query('SELECT submission_id FROM intake_submission_history')->fetchColumn());
    assertSame(2, (int)$pdo->query('PRAGMA user_version')->fetchColumn());
});

test('migration: v2 の DDL も 3.26.0 非対応構文を含まない（SSOT §2.0.1）', function (): void {
    $sql = strtoupper(implode(' ', array_merge([Migrator::ADD_SUBMISSION_ID], Migrator::statementsV2())));
    foreach (['VACUUM INTO', 'RETURNING', ') STRICT', 'DROP COLUMN', 'GENERATED ALWAYS'] as $banned) {
        assertTrue(!str_contains($sql, $banned), 'v2 の DDL に ' . $banned . ' が含まれる');
    }
    foreach (['JSON_EXTRACT', 'JSON_EACH', 'JSON_SET', 'JSON_ARRAY'] as $jsonFn) {
        assertTrue(!str_contains($sql, $jsonFn), 'v2 の DDL が SQL側 JSON 関数へ依存している');
    }
    // 部分一意索引は 3.8.0 以降。本番 3.26.0 で使える
    assertTrue(str_contains($sql, 'WHERE SUBMISSION_ID IS NOT NULL'), '部分索引の条件が無い');

    // 実行環境が実際に部分索引を作れることも確かめる
    $k = makeKernel();
    assertTrue(version_compare($k->db->sqliteVersion(), '3.8.0', '>='), 'SQLite が古すぎる');
});

// ---------------------------------------------------- submission_id の非出力

test('submit: submission_id をログへ出さない', function (): void {
    [$k, , $cookies] = submittableCase('HP-2026-0419');
    $sid = newSubmissionId();

    $k->app->handle(jsonPost('/submit', ['submission_id' => $sid], $cookies));
    $k->app->handle(jsonPost('/submit', ['submission_id' => $sid], $cookies));
    $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies));

    $log = (string)file_get_contents($k->config->logPath);
    assertTrue($log !== '', 'ログが書かれていない（テストが素通りしている）');
    assertTrue(!str_contains($log, $sid), 'ログへ submission_id が出ている');
    assertTrue(preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}/i', $log) !== 1,
        'ログへ UUID 形式の値が出ている');
});

test('submit: ログの許可キーへ submission_id を入れない（構造的に防ぐ）', function (): void {
    assertTrue(
        !in_array('submission_id', \SmartLabo\Intake\Support\Logger::ALLOWED, true),
        'ログの許可キーへ submission_id が入っている'
    );

    // 呼び出し側が誤って渡しても、書き出す前に捨てられる
    $line = (new \SmartLabo\Intake\Support\Logger())->format('info', 'submitted', [
        'submission_id' => '3f2504e0-4f89-41d3-9a0c-0305e82c3301',
        'case_number'   => 'HP-2026-0420',
    ]);
    assertTrue(!str_contains($line, '3f2504e0'), '渡された submission_id が出力されている');
    assertTrue(str_contains($line, 'HP-2026-0420'), '許可キーまで捨てられている');
});

test('submit: submission_id をエラー本文・監査へ出さない', function (): void {
    [$k, $caseId, $cookies] = submittableCase('HP-2026-0421');
    $sid = newSubmissionId();

    // 400（不正形式）
    $res = $k->app->handle(jsonPost('/submit', ['submission_id' => 'bad-' . $sid], $cookies));
    assertSame(400, $res->status);
    assertTrue(!str_contains((string)json_encode($res->body), $sid), '400 本文へ submission_id が出ている');

    // 200（初回）／409（別キー）
    $k->app->handle(jsonPost('/submit', ['submission_id' => $sid], $cookies));
    $res = $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies));
    assertSame(409, $res->status);
    assertTrue(!str_contains((string)json_encode($res->body), $sid), '409 本文へ submission_id が出ている');

    // 監査には result_code しか入らない
    $rows = $k->db->pdo()->query('SELECT * FROM intake_audit_events')->fetchAll();
    foreach ($rows as $row) {
        $dump = (string)json_encode($row);
        assertTrue(!str_contains($dump, $sid), '監査へ submission_id が出ている');
        assertTrue(
            preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}/i', $dump) !== 1,
            '監査へ UUID 形式の値が出ている'
        );
    }
    assertTrue($caseId > 0);
});
