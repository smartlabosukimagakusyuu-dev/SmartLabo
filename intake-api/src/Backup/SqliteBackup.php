<?php
/**
 * HP Intake API — バックアップ（SSOT §9.6 / §9.6.1）。
 *
 * ★本番の SQLite は 3.26.0。**VACUUM INTO は使わない。呼ぶ経路をコードに残さない。**
 * ★第一手段は SQLite3::backup()（Online Backup API・SQLite 3.6.11 以降で利用可）。
 *   通常クエリは PDO だが、バックアップ時のみ SQLite3 で別接続を開く。
 * ★取得後に必ず integrity_check / foreign_key_check を行う。
 *   期待値でなければ**不完全ファイルを正式バックアップ扱いにせず削除**する。
 * ★public_html を想定した場所へ出力しない。
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Backup;

use SmartLabo\Intake\Db;

final class SqliteBackup
{
    /** 出力先として認めないパス片（公開領域への書き出しを防ぐ） */
    private const FORBIDDEN_SEGMENTS = ['public_html', '/public/', '\\public\\', 'htdocs', 'www'];

    public function __construct(private readonly Db $db)
    {
    }

    /**
     * @return array{ok:bool,path:string,integrity:string,foreign_key_violations:int}
     * @throws \RuntimeException
     */
    public function backupTo(string $destPath): array
    {
        $this->assertDestinationAllowed($destPath);

        if (!extension_loaded('sqlite3')) {
            throw new \RuntimeException('sqlite3 extension required for backup');
        }
        if ($this->db->path() === ':memory:') {
            throw new \RuntimeException('in-memory database cannot be backed up by file');
        }

        $dir = dirname($destPath);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException('backup directory is not writable');
        }
        if (is_file($destPath)) {
            @unlink($destPath);
        }

        $source = new \SQLite3($this->db->path(), SQLITE3_OPEN_READONLY);
        $source->busyTimeout(5000);
        $dest = new \SQLite3($destPath);
        $dest->busyTimeout(5000);

        try {
            // Online Backup API。稼働中でも整合したコピーを1ファイルで得られる
            $ok = $source->backup($dest);
        } finally {
            $dest->close();
            $source->close();
        }

        if ($ok !== true) {
            @unlink($destPath);
            throw new \RuntimeException('backup failed');
        }

        $check = $this->verify($destPath);
        if ($check['integrity'] !== 'ok' || $check['foreign_key_violations'] !== 0) {
            // 不完全なファイルを正式バックアップ扱いにしない
            @unlink($destPath);
            throw new \RuntimeException('backup verification failed');
        }
        @chmod($destPath, 0600);

        return ['ok' => true, 'path' => $destPath] + $check;
    }

    /** @return array{integrity:string,foreign_key_violations:int} */
    public function verify(string $path): array
    {
        $pdo = new \PDO('sqlite:' . $path, null, null, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');

        $integrity = (string)$pdo->query('PRAGMA integrity_check')->fetchColumn();
        $fk        = $pdo->query('PRAGMA foreign_key_check')->fetchAll();

        return ['integrity' => $integrity, 'foreign_key_violations' => count($fk)];
    }

    private function assertDestinationAllowed(string $destPath): void
    {
        $normalized = str_replace('\\', '/', strtolower($destPath));
        foreach (self::FORBIDDEN_SEGMENTS as $segment) {
            if (str_contains($normalized, str_replace('\\', '/', strtolower($segment)))) {
                throw new \RuntimeException('backup destination must be outside the public directory');
            }
        }
    }
}
