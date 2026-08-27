<?php
/**
 * HP Intake API — DB接続。
 *
 * SSOT §2.0 / §2.0.1 / §9.6:
 *  - PDO の prepared statement のみ（値をSQL文字列へ連結しない）
 *  - PRAGMA foreign_keys = ON（接続ごと）
 *  - journal_mode = DELETE（WAL にしない）
 *  - busy_timeout を設定する
 *  - SQLite 3.26.0 互換サブセットの範囲でのみ実装する
 */
declare(strict_types=1);

namespace SmartLabo\Intake;

final class Db
{
    /** VACUUM INTO が使える最低版。本番 3.26.0 では使えない（SSOT §2.0.1） */
    public const VACUUM_INTO_MIN = '3.27.0';

    private \PDO $pdo;

    public function __construct(private readonly string $path)
    {
        if ($path !== ':memory:') {
            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0700, true);
            }
        }

        $this->pdo = new \PDO('sqlite:' . $path, null, null, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        // 接続ごとに必ず適用する
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('PRAGMA journal_mode = DELETE');
        $this->pdo->exec('PRAGMA busy_timeout = 5000');

        if ($path !== ':memory:') {
            @chmod($path, 0600);
        }
    }

    public function pdo(): \PDO
    {
        return $this->pdo;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function sqliteVersion(): string
    {
        return (string)$this->pdo->query('SELECT sqlite_version()')->fetchColumn();
    }

    /**
     * SSOT §2.0.1 の起動時ガード。
     * 判定できるようにしておくが、true でも VACUUM INTO は使わない（経路を作らない）。
     */
    public function supportsVacuumInto(): bool
    {
        return version_compare($this->sqliteVersion(), self::VACUUM_INTO_MIN, '>=');
    }

    public function foreignKeysOn(): bool
    {
        return (int)$this->pdo->query('PRAGMA foreign_keys')->fetchColumn() === 1;
    }

    public function journalMode(): string
    {
        return strtolower((string)$this->pdo->query('PRAGMA journal_mode')->fetchColumn());
    }

    /** @return array{integrity:string,foreign_key_violations:int} */
    public function integrity(): array
    {
        $integrity = (string)$this->pdo->query('PRAGMA integrity_check')->fetchColumn();
        $fk        = $this->pdo->query('PRAGMA foreign_key_check')->fetchAll();

        return ['integrity' => $integrity, 'foreign_key_violations' => count($fk)];
    }

    public function transaction(callable $fn): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $fn($this->pdo);
            $this->pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function close(): void
    {
        unset($this->pdo);
    }
}
