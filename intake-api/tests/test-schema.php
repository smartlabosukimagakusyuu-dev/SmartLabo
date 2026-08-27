<?php
/**
 * DB・schema のテスト（SSOT §2 / §2.0.1）
 */
declare(strict_types=1);

use SmartLabo\Intake\Migrator;

test('schema: SSOT の6テーブルが作られる', function (): void {
    $k    = makeKernel();
    $rows = $k->db->pdo()->query(
        "SELECT name FROM sqlite_master WHERE type = 'table' AND name LIKE 'intake_%' ORDER BY name"
    )->fetchAll();
    $names = array_column($rows, 'name');

    assertSame([
        'intake_answers', 'intake_audit_events', 'intake_cases',
        'intake_sessions', 'intake_submission_history', 'intake_tokens',
    ], $names, 'テーブル一覧が SSOT と一致しない');
});

test('schema: foreign_keys が ON', function (): void {
    $k = makeKernel();
    assertTrue($k->db->foreignKeysOn(), 'foreign_keys が有効でない');
});

test('schema: journal_mode が delete（WAL にしない）', function (): void {
    $k = makeKernel();
    assertSame('delete', $k->db->journalMode(), 'journal_mode が delete でない');
});

test('schema: busy_timeout が設定されている', function (): void {
    $k       = makeKernel();
    $timeout = (int)$k->db->pdo()->query('PRAGMA busy_timeout')->fetchColumn();
    assertTrue($timeout >= 5000, 'busy_timeout が設定されていない');
});

test('schema: token 平文列・session secret 平文列が存在しない', function (): void {
    $k = makeKernel();
    foreach (['intake_tokens' => 'token', 'intake_sessions' => 'session'] as $table => $kind) {
        $cols = array_column($k->db->pdo()->query("PRAGMA table_info('{$table}')")->fetchAll(), 'name');
        foreach ($cols as $col) {
            assertTrue(
                !in_array($col, [$kind, $kind . '_plain', $kind . '_secret', 'plaintext'], true),
                $table . ' に平文列 ' . $col . ' がある'
            );
        }
        assertTrue(in_array($kind === 'token' ? 'token_hash' : 'session_hash', $cols, true), $table . ' に hash 列が無い');
    }
});

test('schema: intake_sessions に absolute_expires_at / revoked_at がある', function (): void {
    $k    = makeKernel();
    $cols = array_column($k->db->pdo()->query("PRAGMA table_info('intake_sessions')")->fetchAll(), 'name');
    foreach (['absolute_expires_at', 'revoked_at', 'token_id', 'last_seen_at'] as $need) {
        assertTrue(in_array($need, $cols, true), 'intake_sessions に ' . $need . ' が無い');
    }
});

test('schema: intake_answers に楽観ロック用の version がある', function (): void {
    $k    = makeKernel();
    $cols = array_column($k->db->pdo()->query("PRAGMA table_info('intake_answers')")->fetchAll(), 'name');
    assertTrue(in_array('version', $cols, true), 'version 列が無い');
    foreach (Migrator::ANSWER_SECTIONS as $section) {
        assertTrue(in_array($section . '_json', $cols, true), $section . '_json 列が無い');
    }
});

test('schema: Stripe参照・カード情報・公開承認の列が存在しない（SSOT §8）', function (): void {
    $k        = makeKernel();
    $tables   = array_column(
        $k->db->pdo()->query("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'intake_%'")->fetchAll(),
        'name'
    );
    $forbidden = ['stripe', 'customer_id', 'invoice_id', 'card', 'cvv', 'security_code',
                  'password', 'api_key', 'secret_key', 'approved_at', 'publish_approved'];
    foreach ($tables as $table) {
        foreach (array_column($k->db->pdo()->query("PRAGMA table_info('{$table}')")->fetchAll(), 'name') as $col) {
            foreach ($forbidden as $bad) {
                assertTrue(!str_contains(strtolower($col), $bad), $table . '.' . $col . ' は保存禁止情報にあたる');
            }
        }
    }
});

test('schema: 3.26.0 非対応構文を DDL に含まない（SSOT §2.0.1）', function (): void {
    $sql = strtoupper(implode(' ', Migrator::allStatements()));
    foreach (['VACUUM INTO', 'RETURNING', ') STRICT', 'DROP COLUMN', 'GENERATED ALWAYS'] as $banned) {
        assertTrue(!str_contains($sql, $banned), 'DDL に ' . $banned . ' が含まれる');
    }
    foreach (['JSON_EXTRACT', 'JSON_EACH', 'JSON_SET', 'JSON_ARRAY'] as $jsonFn) {
        assertTrue(!str_contains($sql, $jsonFn), 'DDL が SQL側 JSON 関数へ依存している');
    }
});

test('schema: 案件作成で回答行が空JSONで初期化される', function (): void {
    $k      = makeKernel();
    $caseId = $k->cases->create('HP-2026-0001', '架空サロン');
    $answers = $k->answers->get($caseId);

    assertSame(1, $answers['version']);
    foreach (Migrator::ANSWER_SECTIONS as $section) {
        assertTrue(isset($answers['sections'][$section]), $section . ' が初期化されていない');
        assertSame([], $answers['sections'][$section], $section . ' の初期値が空でない');
    }
});
