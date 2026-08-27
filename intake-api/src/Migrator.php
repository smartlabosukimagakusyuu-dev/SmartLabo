<?php
/**
 * HP Intake API — schema（SSOT §2.1〜§2.6 の6テーブル）
 *
 * SQLite 3.26.0 互換サブセット（SSOT §2.0.1）:
 *   使用しない … VACUUM INTO / RETURNING / STRICT / DROP COLUMN / 生成列 /
 *                SQL側の JSON 関数
 *   スキーマ版は PRAGMA user_version で持つ（7つ目のテーブルを作らないため）
 */
declare(strict_types=1);

namespace SmartLabo\Intake;

final class Migrator
{
    public const SCHEMA_VERSION = 1;

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

        foreach (self::statements() as $sql) {
            $pdo->exec($sql);
        }

        // PRAGMA は値をbindできない。埋め込むのはクラス定数であり外部入力ではない。
        $pdo->exec('PRAGMA user_version = ' . (int)self::SCHEMA_VERSION);
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
