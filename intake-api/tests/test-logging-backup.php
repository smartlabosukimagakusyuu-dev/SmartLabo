<?php
/**
 * ログ非出力・バックアップのテスト（SSOT §9.6.1 / §9.7 / §10.7）
 */
declare(strict_types=1);

use SmartLabo\Intake\Backup\SqliteBackup;
use SmartLabo\Intake\Config;
use SmartLabo\Intake\Support\Logger;
use SmartLabo\Intake\Support\Secret;

test('log: allowlist 外のキーは出力されない', function (): void {
    $logger = new Logger(null);
    $line   = $logger->format('info', 'answer_saved', [
        'case_number' => 'HP-2026-0400',
        'shop_name'   => 'ヘアサロン ハルカゼ',
        'email'       => 'owner@example.invalid',
        'phone'       => '03-0000-0000',
        'address'     => '架空県架空市',
        'answers'     => '回答本文',
        'drive_url'   => 'https://drive.example.invalid/x',
    ]);

    assertTrue(str_contains($line, 'HP-2026-0400'), '許可キーが出ていない');
    foreach (['ハルカゼ', 'owner@example.invalid', '03-0000-0000', '架空県', '回答本文', 'drive.example.invalid'] as $pii) {
        assertTrue(!str_contains($line, $pii), 'ログに ' . $pii . ' が出ている');
    }
});

test('log: token / session secret（base64url 43文字）はマスクされる', function (): void {
    $secret = Secret::generate();
    $masked = Logger::redact('start ' . $secret . ' end');

    assertTrue(!str_contains($masked, $secret), 'secret がマスクされていない');
    assertTrue(str_contains($masked, '[REDACTED]'), 'マスク記号が無い');
});

test('log: SHA-256 hash・既知の秘密値パターンもマスクされる', function (): void {
    $masked = Logger::redact(hash('sha256', 'x') . ' sk_live_abcdef123456 Bearer abc.def-ghi');
    assertTrue(!str_contains($masked, 'sk_live_abcdef123456'), 'Stripe鍵らしき値が残る');
    assertTrue(!str_contains($masked, 'Bearer abc.def-ghi'), 'Bearer が残る');
    assertTrue(substr_count($masked, '[REDACTED]') >= 3, 'マスク件数が足りない');
});

test('log: 実際のログファイルへ token / PII が書かれない', function (): void {
    $k      = makeKernel();
    $caseId = $k->cases->create('HP-2026-0401', 'ヘアサロン ハルカゼ');
    $token  = $k->tokens->issue($caseId);
    $secret = $k->app->handle(jsonPost('/session/start', ['token' => $token]))->cookies[0]['value'];

    $k->app->handle(jsonPost('/answers/save', [
        'version'  => 1,
        'sections' => completeSections(),
    ], ['cookies' => [Config::COOKIE_NAME => $secret]]));

    $path = (string)$k->config->logPath;
    assertTrue(is_file($path), 'ログが書かれていない');
    $contents = (string)file_get_contents($path);

    foreach ([$token, $secret, 'ハルカゼ', '架空県', '03-0000-0000', 'internal@example.invalid'] as $forbidden) {
        assertTrue(!str_contains($contents, $forbidden), 'ログに ' . substr($forbidden, 0, 12) . ' が出ている');
    }
    assertTrue(str_contains($contents, 'HP-2026-0401'), '案件番号が記録されていない');
});

test('audit: token 拒否の理由は区別して記録されるが、応答は同一', function (): void {
    $k      = makeKernel();
    $caseId = $k->cases->create('HP-2026-0402', '架空サロン');
    $token  = $k->tokens->issue($caseId);
    $k->tokens->revokeAllForCase($caseId);

    $k->app->handle(jsonPost('/session/start', ['token' => $token], ['ip' => '198.51.100.30']));
    $k->app->handle(jsonPost('/session/start', ['token' => Secret::generate()], ['ip' => '198.51.100.31']));

    $rows    = $k->db->pdo()->query("SELECT result_code FROM intake_audit_events WHERE event_type = 'token_rejected'")->fetchAll();
    $reasons = array_column($rows, 'result_code');

    assertTrue(in_array('revoked', $reasons, true), 'revoked が記録されていない');
    assertTrue(in_array('not_found', $reasons, true), 'not_found が記録されていない');
});

test('audit: 監査ログに token 平文・PII が入らない', function (): void {
    $k      = makeKernel();
    $caseId = $k->cases->create('HP-2026-0403', 'ヘアサロン ハルカゼ');
    $token  = $k->tokens->issue($caseId);
    $k->app->handle(jsonPost('/session/start', ['token' => $token]));

    $rows = $k->db->pdo()->query('SELECT * FROM intake_audit_events')->fetchAll();
    $dump = json_encode($rows, JSON_UNESCAPED_UNICODE);

    assertTrue(!str_contains((string)$dump, $token), '監査に token 平文がある');
    assertTrue(!str_contains((string)$dump, 'ハルカゼ'), '監査に店舗名がある');
});

test('backup: SQLite3::backup() で取得し、integrity/foreign_key を検証する', function (): void {
    $k      = makeKernel();
    $caseId = $k->cases->create('HP-2026-0404', '架空サロン');
    $k->tokens->issue($caseId);

    $backup = new SqliteBackup($k->db);
    $dest   = dirname($k->config->dbPath) . '/backup/intake-backup.sqlite';
    $result = $backup->backupTo($dest);

    assertSame(true, $result['ok']);
    assertSame('ok', $result['integrity']);
    assertSame(0, $result['foreign_key_violations']);
    assertTrue(is_file($dest), 'バックアップファイルが無い');

    // 中身が復元できること
    $pdo   = new PDO('sqlite:' . $dest);
    $count = (int)$pdo->query('SELECT COUNT(*) FROM intake_cases')->fetchColumn();
    assertSame(1, $count, 'バックアップに案件が入っていない');
});

test('backup: public_html を想定した場所へは出力しない', function (): void {
    $k      = makeKernel();
    $backup = new SqliteBackup($k->db);

    foreach (['/var/www/public_html/b.sqlite', 'C:/site/public/b.sqlite', '/home/x/htdocs/b.sqlite'] as $bad) {
        $thrown = false;
        try {
            $backup->backupTo($bad);
        } catch (\RuntimeException $e) {
            $thrown = true;
        }
        assertTrue($thrown, $bad . ' への出力が拒否されない');
    }
});

test('backup: 破損した取得結果を正式バックアップ扱いしない', function (): void {
    $k      = makeKernel();
    $backup = new SqliteBackup($k->db);
    $dest   = dirname($k->config->dbPath) . '/backup/ok.sqlite';
    $backup->backupTo($dest);

    // 破損させて検証にかける
    file_put_contents($dest, 'this is not a sqlite file');
    $thrown = false;
    try {
        $backup->verify($dest);
    } catch (\Throwable $e) {
        $thrown = true;
    }
    assertTrue($thrown, '破損ファイルが検証を通ってしまう');
});

/**
 * コメントと空白を除いた「実際に実行されるコード」を返す。
 * SSOT の撤回説明（コメント）を誤検知しないよう、トークナイザで落とす。
 */
function executableCode(string $path): string
{
    $out = '';
    foreach (token_get_all((string)file_get_contents($path)) as $token) {
        if (is_array($token)) {
            if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $out .= $token[1];
            continue;
        }
        $out .= $token;
    }

    return $out;
}

/** @return list<SplFileInfo> */
function srcPhpFiles(): array
{
    $files = [];
    $it    = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(dirname(__DIR__) . '/src', FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file;
        }
    }

    return $files;
}

test('backup: VACUUM INTO を使う経路がコードに存在しない（SSOT §2.0.1）', function (): void {
    $found = [];
    foreach (srcPhpFiles() as $file) {
        if (stripos(executableCode($file->getPathname()), 'vacuum into') !== false) {
            $found[] = $file->getFilename();
        }
    }
    assertSame([], $found, 'VACUUM INTO の実行経路がある: ' . implode(' / ', $found));
});

test('backup: SQLite 3.26.0 非対応構文をコードで使っていない', function (): void {
    $found = [];
    foreach (srcPhpFiles() as $file) {
        $code = strtoupper(executableCode($file->getPathname()));
        foreach ([' RETURNING ', 'JSON_EXTRACT(', 'JSON_EACH(', 'JSON_SET(', 'DROP COLUMN', 'GENERATED ALWAYS'] as $banned) {
            if (str_contains($code, $banned)) {
                $found[] = $file->getFilename() . ':' . trim($banned);
            }
        }
    }
    assertSame([], $found, '3.26.0 非対応構文がある: ' . implode(' / ', $found));
});
