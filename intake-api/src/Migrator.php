<?php
/**
 * HP Intake API — schema（SSOT §2.1〜§2.6 の6テーブル）
 *
 * SQLite 3.26.0 互換サブセット（SSOT §2.0.1）:
 *   使用しない … VACUUM INTO / RETURNING / STRICT / DROP COLUMN / 生成列 /
 *                SQL側の JSON 関数
 *   スキーマ版は PRAGMA user_version で持つ（7つ目のテーブルを作らないため）
 *
 * 版:
 *   1 … 4B。SSOT v1.2 の6テーブル
 *   2 … 4B-R1。intake_submission_history.submission_id と部分一意索引（SSOT v1.3 §2.4 / §6.4）
 *
 * ★migrate() は**何度実行しても同じ結果**になる（再実行可能）。
 *   ALTER TABLE ADD COLUMN だけは IF NOT EXISTS を書けないため、
 *   PRAGMA table_info で列の有無を確かめてから実行する。
 */
declare(strict_types=1);

namespace SmartLabo\Intake;

final class Migrator
{
    public const SCHEMA_VERSION = 2;

    /**
     * 回答スキーマの版（intake_cases.schema_version / intake_answers.schema_version）。
     * ★DBスキーマ版（SCHEMA_VERSION）とは別物である。混ぜてはならない。
     *   v1.3（4B-R1）は JSON 11分類を一切変更していないため **1 のまま**。
     *   §3 のデータパス・型・上限を変えたときにだけ上げる。
     */
    public const ANSWER_SCHEMA_VERSION = 1;

    /** 回答の JSON 分類（SSOT §2.3 / §3）。この11個以外は受け付けない */
    public const ANSWER_SECTIONS = [
        'basic', 'business_hours', 'menus', 'staff', 'promotion', 'design',
        'web_links', 'contact_form', 'privacy', 'image_metadata', 'rights',
    ];

    /** 配列で保持する分類（既定値が [] になるもの） */
    public const LIST_SECTIONS = ['menus', 'staff', 'image_metadata'];

    public function __construct(private readonly Db $db)
    {
    }

    public function migrate(): void
    {
        $pdo     = $this->db->pdo();
        $current = (int)$pdo->query('PRAGMA user_version')->fetchColumn();
        if ($current >= self::SCHEMA_VERSION) {
            return;
        }

        // v1: 6テーブル。すべて IF NOT EXISTS なので既存DBでも安全に通せる
        foreach (self::statements() as $sql) {
            $pdo->exec($sql);
        }

        // v2: submission_id（SSOT v1.3 §2.4）
        $this->upgradeToV2($pdo);

        // PRAGMA は値をbindできない。埋め込むのはクラス定数であり外部入力ではない。
        $pdo->exec('PRAGMA user_version = ' . (int)self::SCHEMA_VERSION);
    }

    /**
     * v1 → v2。既存DBを壊さない追加のみ（列1つ・索引1つ）。
     * 既存行の submission_id は NULL のまま残す（SSOT v1.3 §2.4-4）。
     */
    private function upgradeToV2(\PDO $pdo): void
    {
        // ALTER TABLE ADD COLUMN に IF NOT EXISTS は無い。列の有無で判断する
        if (!self::hasColumn($pdo, 'intake_submission_history', 'submission_id')) {
            $pdo->exec(self::ADD_SUBMISSION_ID);
        }
        foreach (self::statementsV2() as $sql) {
            $pdo->exec($sql);
        }
    }

    public static function hasColumn(\PDO $pdo, string $table, string $column): bool
    {
        // PRAGMA は値をbindできない。$table は呼び出し元のリテラルのみ（外部入力を渡さない）
        $rows = $pdo->query("PRAGMA table_info('" . str_replace("'", '', $table) . "')")->fetchAll();

        return in_array($column, array_column($rows, 'name'), true);
    }

    /** v2 の列追加。NULL 許容（既存行との互換のため DEFAULT も付けない） */
    public const ADD_SUBMISSION_ID =
        'ALTER TABLE intake_submission_history ADD COLUMN submission_id TEXT';

    /**
     * v2 の索引。部分一意索引は SQLite 3.8.0 以降で使える（本番 3.26.0 で使用可）。
     * submission_id が NULL の既存行を対象から外すため WHERE 句を付ける。
     * @return list<string>
     */
    public static function statementsV2(): array
    {
        return [
            'CREATE UNIQUE INDEX IF NOT EXISTS idx_history_submission_id
                ON intake_submission_history (intake_case_id, submission_id)
             WHERE submission_id IS NOT NULL',
        ];
    }

    /** 静的チェック（SSOT §2.0.1）用に、実行しうる DDL をすべて返す */
    public static function allStatements(): array
    {
        return array_merge(self::statements(), [self::ADD_SUBMISSION_ID], self::statementsV2());
    }

    /** @return list<string> */
    public static function statements(): array
    {
        return [
            // ---------------- A. intake_cases（SSOT §2.1） ----------------
            'CREATE TABLE IF NOT EXISTS intake_cases (
                id                        INTEGER PRIMARY KEY AUTOINCREMENT,
                case_number               TEXT    NOT NULL UNIQUE,
                shop_display_name         TEXT    NOT NULL,
                contract_type             TEXT    NOT NULL DEFAULT "standalone",
                status                    TEXT    NOT NULL DEFAULT "draft",
                current_step              TEXT,
                drive_folder_url_enc      BLOB,
                drive_folder_label        TEXT,
                drive_upload_confirmed_at TEXT,
                submitted_at              TEXT,
                locked_at                 TEXT,
                closed_at                 TEXT,
                expires_at                TEXT,
                retention_delete_due      TEXT,
                deleted_at                TEXT,
                schema_version            INTEGER NOT NULL DEFAULT 1,
                created_at                TEXT    NOT NULL,
                updated_at                TEXT    NOT NULL
            )',
            'CREATE INDEX IF NOT EXISTS idx_cases_status    ON intake_cases(status)',
            'CREATE INDEX IF NOT EXISTS idx_cases_retention ON intake_cases(retention_delete_due)',

            // ---------------- B. intake_tokens（SSOT §2.2） ----------------
            // 平文 token 列は存在しない
            'CREATE TABLE IF NOT EXISTS intake_tokens (
                id             INTEGER PRIMARY KEY AUTOINCREMENT,
                intake_case_id INTEGER NOT NULL REFERENCES intake_cases(id),
                token_hash     TEXT    NOT NULL UNIQUE,
                expires_at     TEXT    NOT NULL,
                revoked_at     TEXT,
                last_used_at   TEXT,
                created_at     TEXT    NOT NULL
            )',
            'CREATE INDEX IF NOT EXISTS idx_tokens_case ON intake_tokens(intake_case_id)',

            // ---------------- C. intake_answers（SSOT §2.3） ----------------
            'CREATE TABLE IF NOT EXISTS intake_answers (
                intake_case_id      INTEGER PRIMARY KEY REFERENCES intake_cases(id),
                schema_version      INTEGER NOT NULL DEFAULT 1,
                basic_json          TEXT    NOT NULL,
                business_hours_json TEXT    NOT NULL,
                menus_json          TEXT    NOT NULL,
                staff_json          TEXT    NOT NULL,
                promotion_json      TEXT    NOT NULL,
                design_json         TEXT    NOT NULL,
                web_links_json      TEXT    NOT NULL,
                contact_form_json   TEXT    NOT NULL,
                privacy_json        TEXT    NOT NULL,
                image_metadata_json TEXT    NOT NULL,
                rights_json         TEXT    NOT NULL,
                version             INTEGER NOT NULL DEFAULT 1,
                created_at          TEXT    NOT NULL,
                updated_at          TEXT    NOT NULL
            )',

            // ---------------- D. intake_submission_history（SSOT §2.4） ----------------
            // 回答本文・個人情報のコピーを持たない。件数のみ
            // ★submission_id は v2（upgradeToV2）で追加する。ここは v1 の形のまま残す
            'CREATE TABLE IF NOT EXISTS intake_submission_history (
                id             INTEGER PRIMARY KEY AUTOINCREMENT,
                intake_case_id INTEGER NOT NULL REFERENCES intake_cases(id),
                event_type     TEXT    NOT NULL,
                schema_version INTEGER NOT NULL,
                submitted_at   TEXT    NOT NULL,
                result_code    TEXT    NOT NULL,
                field_count    INTEGER,
                missing_count  INTEGER
            )',
            'CREATE INDEX IF NOT EXISTS idx_history_case ON intake_submission_history(intake_case_id, submitted_at)',

            // ---------------- E. intake_audit_events（SSOT §2.5） ----------------
            // token平文・回答本文・氏名・メール・電話・住所・Drive URL を保存しない
            'CREATE TABLE IF NOT EXISTS intake_audit_events (
                id             INTEGER PRIMARY KEY AUTOINCREMENT,
                intake_case_id INTEGER REFERENCES intake_cases(id),
                event_type     TEXT    NOT NULL,
                result_code    TEXT    NOT NULL,
                ip_hmac        TEXT,
                created_at     TEXT    NOT NULL
            )',
            'CREATE INDEX IF NOT EXISTS idx_audit_case    ON intake_audit_events(intake_case_id, created_at)',
            'CREATE INDEX IF NOT EXISTS idx_audit_created ON intake_audit_events(created_at)',

            // ---------------- F. intake_sessions（SSOT §2.6） ----------------
            // 平文 session secret 列は存在しない
            'CREATE TABLE IF NOT EXISTS intake_sessions (
                id                  INTEGER PRIMARY KEY AUTOINCREMENT,
                intake_case_id      INTEGER NOT NULL REFERENCES intake_cases(id),
                token_id            INTEGER NOT NULL REFERENCES intake_tokens(id),
                session_hash        TEXT    NOT NULL UNIQUE,
                expires_at          TEXT    NOT NULL,
                absolute_expires_at TEXT    NOT NULL,
                revoked_at          TEXT,
                last_seen_at        TEXT,
                created_at          TEXT    NOT NULL
            )',
            'CREATE INDEX IF NOT EXISTS idx_sessions_case    ON intake_sessions(intake_case_id)',
            'CREATE INDEX IF NOT EXISTS idx_sessions_token   ON intake_sessions(token_id)',
            'CREATE INDEX IF NOT EXISTS idx_sessions_expires ON intake_sessions(expires_at)',
        ];
    }
}
