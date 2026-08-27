<?php
/**
 * HP Intake API — 保持期限と削除（SSOT v1.7 §9）。
 *
 * この工程（4F）で確定した責任境界:
 *   - **公開日も公開承認も HP Intake へ保存しない**。Operations（未完成の間は §1.3 の
 *     標準管理票）の責任である。intake が持つのは `retention_delete_due` **だけ**で、
 *     そこから公開日を逆算しない。
 *   - 自動削除・cron を作らない。**管理者が明示的に実行**したときだけ消す。
 *   - Google Drive の実ファイルは intake から消さない（§9.3-5 / §7.1）。
 *
 * 破壊的操作の入り口は fail closed:
 *   `Config::retentionEnabled()`（= retention_actions_enabled ∧ backup_policy_confirmed）
 *   が真でなければ、この表示・実行の経路そのものを通さない。
 *
 * ★消した値をログ・監査・応答へ書かない。残すのは「案件番号・結果・件数」だけ。
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Service;

use SmartLabo\Intake\Db;
use SmartLabo\Intake\Support\Clock;

final class RetentionService
{
    /** 削除予定日を登録してよい案件状態（SSOT v1.7 §9.3-1） */
    public const DUE_SETTABLE = ['reviewed', 'locked'];

    /**
     * 機密情報を削除してよい案件状態（SSOT v1.7 §9.3-3）。
     * ★`locked` のみ。確定前（draft〜reviewed）の案件を消す経路を作らない。
     */
    public const PURGEABLE = ['locked'];

    /** 監査ログの保持月数（SSOT §9.1） */
    public const AUDIT_RETENTION_MONTHS = 13;

    /** 一度に消す行数の上限（長いロックを作らない・途中で止めても壊れない） */
    public const BATCH = 500;

    /** 一回の実行で回すバッチ数の上限 */
    public const MAX_BATCHES = 40;

    /**
     * 削除後の `intake_cases` で**そのまま残す**列（SSOT v1.7 §9.4）。
     * ★いずれも PII を含まない。案件番号・状態・日付・版のみ。
     */
    public const CASE_KEEP = [
        'id', 'case_number', 'contract_type', 'status',
        'drive_upload_confirmed_at', 'submitted_at', 'locked_at', 'closed_at',
        'retention_delete_due', 'deleted_at', 'schema_version', 'created_at', 'updated_at',
    ];

    /**
     * 削除時に **NULL にする**列。
     * ★Drive の暗号文は「鍵があれば戻せる参照」である。行ごと残さない。
     */
    public const CASE_NULLED = [
        'drive_folder_url_enc', 'drive_shared_email_enc', 'drive_folder_label',
        'current_step', 'expires_at',
    ];

    /**
     * NOT NULL のため NULL にできず、固定値へ置き換える列。
     * ★`shop_display_name` は店舗名（＝PII 相当）。削除後に残さない。
     */
    public const CASE_REPLACED = ['shop_display_name' => '（削除済み）'];

    /** 案件削除で**行ごと物理削除**する表（SSOT v1.7 §9.3-3） */
    public const PURGED_TABLES = [
        'intake_sessions',            // 先に消す（intake_tokens を参照しているため）
        'intake_tokens',
        'intake_answers',
        'intake_revision_requests',
        'intake_submission_history',
    ];

    public function __construct(
        private readonly Db $db,
        private readonly Clock $clock,
        private readonly Audit $audit,
    ) {
    }

    /* ==================================================== 削除予定日の登録 */

    /**
     * 入力された削除予定日を検査する。
     *
     * ★`YYYY-MM-DD` の**実在する日付**だけを通す。`strtotime()` の寛容な解釈
     *   （`+1 month` や `2026/1/1`）に頼らない。公開日・承認日は受け取らない。
     *
     * @return array{ok:bool,error?:string,date?:string}
     */
    public static function checkDate(string $input): array
    {
        $value = trim($input);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) !== 1) {
            return ['ok' => false, 'error' => 'format'];
        }
        [, $y, $mo, $d] = array_map('intval', $m);
        if (!checkdate($mo, $d, $y)) {
            return ['ok' => false, 'error' => 'not_a_date'];
        }
        // 現実的な範囲を外れた値は誤入力とみなす（西暦4桁の打ち間違いを通さない）
        if ($y < 2026 || $y > 2099) {
            return ['ok' => false, 'error' => 'out_of_range'];
        }

        return ['ok' => true, 'date' => $value];
    }

    /** サーバーの今日（UTC・`YYYY-MM-DD`） */
    public function today(): string
    {
        return substr($this->clock->iso(), 0, 10);
    }

    /**
     * 削除予定日を登録・変更する（SSOT v1.7 §9.3-1）。
     *
     * ★冪等。同じ日付の再送では監査を増やさない。
     * ★変更（別の日付への上書き）は許すが、必ず監査へ1件残す。
     * ★取り消し（NULL へ戻す）経路は作らない。
     *
     * @return array{ok:bool,error?:string,changed:bool,past?:bool}
     */
    public function setDeleteDue(int $caseId, string $input): array
    {
        $checked = self::checkDate($input);
        if ($checked['ok'] !== true) {
            return ['ok' => false, 'error' => (string)$checked['error'], 'changed' => false];
        }
        $date = (string)$checked['date'];

        $stmt = $this->db->pdo()->prepare(
            'SELECT status, retention_delete_due, deleted_at FROM intake_cases WHERE id = :id'
        );
        $stmt->execute([':id' => $caseId]);
        $case = $stmt->fetch();

        if ($case === false) {
            return ['ok' => false, 'error' => 'not_found', 'changed' => false];
        }
        if ($case['deleted_at'] !== null) {
            return ['ok' => false, 'error' => 'already_deleted', 'changed' => false];
        }
        if (!in_array((string)$case['status'], self::DUE_SETTABLE, true)) {
            return ['ok' => false, 'error' => 'invalid_status', 'changed' => false];
        }
        if ((string)($case['retention_delete_due'] ?? '') === $date) {
            // 同じ日付の再送。記録を増やさない
            return ['ok' => true, 'changed' => false, 'past' => $date < $this->today()];
        }

        $this->db->pdo()->prepare(
            'UPDATE intake_cases SET retention_delete_due = :due, updated_at = :now WHERE id = :id'
        )->execute([':due' => $date, ':now' => $this->clock->iso(), ':id' => $caseId]);

        // ★日付そのものは監査へ書かない（案件行に入っている。二重に持たない）
        $this->audit->record($caseId, 'retention_due_set', 'ok');

        return ['ok' => true, 'changed' => true, 'past' => $date < $this->today()];
    }

    /* ==================================================== 期限一覧 */

    /**
     * 期限の区分（SSOT v1.7 §9.3-1 の一覧）。
     * ★`deleted` を最優先で判定する。削除済みを「期限超過」と見せない。
     */
    public function bucketOf(?string $due, ?string $deletedAt, string $today): string
    {
        if ($deletedAt !== null && $deletedAt !== '') {
            return 'deleted';
        }
        if ($due === null || $due === '') {
            return 'unset';
        }
        if ($due < $today) {
            return 'overdue';
        }
        if ($due === $today) {
            return 'due';
        }

        return $due <= self::plusDays($today, 30) ? 'soon' : 'later';
    }

    /** `YYYY-MM-DD` に日数を足す（UTC 固定・時刻を持ち込まない） */
    public static function plusDays(string $date, int $days): string
    {
        $ts = strtotime($date . 'T00:00:00Z');

        return $ts === false ? $date : gmdate('Y-m-d', $ts + ($days * 86400));
    }

    /**
     * 保持期限の管理一覧（SSOT v1.7 §9.3-1）。
     *
     * ★回答本文・店舗名・Drive URL・共有先メールを**一切選ばない**。
     *   一覧に出せるのは案件番号・状態・日付・区分だけである。
     *
     * @return list<array<string,mixed>>
     */
    public function listForRetention(int $limit = 200): array
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT case_number, status, retention_delete_due, deleted_at
               FROM intake_cases
              ORDER BY (retention_delete_due IS NULL), retention_delete_due ASC, case_number ASC
              LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $today = $this->today();
        $rows  = [];
        foreach ($stmt->fetchAll() as $row) {
            $row['bucket'] = $this->bucketOf(
                $row['retention_delete_due'] === null ? null : (string)$row['retention_delete_due'],
                $row['deleted_at'] === null ? null : (string)$row['deleted_at'],
                $today
            );
            $rows[] = $row;
        }

        return $rows;
    }

    /* ==================================================== 機密情報の削除 */

    /**
     * 削除してよい案件かを判定する（実行前・確認画面の両方から呼ぶ）。
     *
     * ★呼び出し側の判断に頼らない。**実行時にも同じ判定をやり直す**。
     *
     * @param array<string,mixed> $case
     * @return array{ok:bool,error?:string}
     */
    public function canPurge(array $case): array
    {
        if (($case['deleted_at'] ?? null) !== null) {
            return ['ok' => false, 'error' => 'already_deleted'];
        }
        if (!in_array((string)$case['status'], self::PURGEABLE, true)) {
            return ['ok' => false, 'error' => 'invalid_status'];
        }
        $due = (string)($case['retention_delete_due'] ?? '');
        if ($due === '') {
            return ['ok' => false, 'error' => 'due_not_set'];
        }
        if ($due > $this->today()) {
            return ['ok' => false, 'error' => 'not_due'];
        }

        return ['ok' => true];
    }

    /** 確認入力の期待値。★完全一致だけを実行する */
    public static function confirmPhrase(string $caseNumber): string
    {
        return 'DELETE ' . $caseNumber;
    }

    /**
     * 機密情報を物理削除する（SSOT v1.7 §9.3）。
     *
     * すべて**同一トランザクション**。途中で落ちたら全部戻す。
     * 「回答だけ消えて token が残っている」中途半端な状態を作らない。
     *
     * @return array{ok:bool,error?:string,deleted?:array<string,int>}
     */
    public function purgeCase(int $caseId, string $caseNumber, string $confirmInput): array
    {
        // 確認文の完全一致。★入力された値をログへ出さない
        if (!hash_equals(self::confirmPhrase($caseNumber), trim($confirmInput))) {
            return ['ok' => false, 'error' => 'confirm_mismatch'];
        }

        $now = $this->clock->iso();

        $result = $this->db->transaction(function (\PDO $pdo) use ($caseId, $caseNumber, $now): array {
            // 1. 条件の再確認（判定してから実行するまでの間に変わっていたら止める）
            $stmt = $pdo->prepare(
                'SELECT case_number, status, retention_delete_due, deleted_at, closed_at
                   FROM intake_cases WHERE id = :id'
            );
            $stmt->execute([':id' => $caseId]);
            $case = $stmt->fetch();

            if ($case === false || !hash_equals((string)$case['case_number'], $caseNumber)) {
                return ['ok' => false, 'error' => 'not_found'];
            }
            $gate = $this->canPurge($case);
            if ($gate['ok'] !== true) {
                return $gate;
            }

            // 2〜6. 機密を持つ表を行ごと消す。
            // ★表名を変数にしない。**1文ずつリテラルで書く**。
            //   ここは「どの表を消すか」がそのまま安全性の中身なので、grep できる形に置く。
            //   順序は外部キーの向きに合わせる（sessions → tokens）。
            $deleted = [
                'intake_sessions'            => $this->deleteForCase($pdo,
                    'DELETE FROM intake_sessions WHERE intake_case_id = :id', $caseId),
                'intake_tokens'              => $this->deleteForCase($pdo,
                    'DELETE FROM intake_tokens WHERE intake_case_id = :id', $caseId),
                'intake_answers'             => $this->deleteForCase($pdo,
                    'DELETE FROM intake_answers WHERE intake_case_id = :id', $caseId),
                'intake_revision_requests'   => $this->deleteForCase($pdo,
                    'DELETE FROM intake_revision_requests WHERE intake_case_id = :id', $caseId),
                'intake_submission_history'  => $this->deleteForCase($pdo,
                    'DELETE FROM intake_submission_history WHERE intake_case_id = :id', $caseId),
            ];

            // 7〜11. 案件行は allowlist で作り直す（残す列・NULL にする列・置き換える列）
            $sets   = ['status = :status', 'deleted_at = :now', 'updated_at = :now'];
            $params = [':status' => 'closed', ':now' => $now, ':id' => $caseId];

            foreach (self::CASE_NULLED as $column) {
                $sets[] = $column . ' = NULL';
            }
            foreach (self::CASE_REPLACED as $column => $value) {
                $sets[]                  = $column . ' = :repl_' . $column;
                $params[':repl_' . $column] = $value;
            }
            if ($case['closed_at'] === null) {
                $sets[]            = 'closed_at = :closed_at';
                $params[':closed_at'] = $now;
            }

            $pdo->prepare('UPDATE intake_cases SET ' . implode(', ', $sets) . ' WHERE id = :id')
                ->execute($params);

            // 12. 監査は1件だけ。★消した値も件数の内訳も書かない
            $this->audit->record($caseId, 'retention_purged', 'ok');

            return ['ok' => true, 'deleted' => $deleted];
        });

        return $result;
    }

    /* ==================================================== 監査の 13か月削除 */

    /**
     * 監査ログの保持期限（サーバー時刻から13か月前）。
     *
     * ★月末の丸めは PHP の `strtotime` に従う（例: 3/31 の13か月前は 2/31 → 3/3）。
     *   保持期間を**短くする**方向へは働かない（1〜3日長く残るだけ）ので、
     *   「消しすぎる」事故にはならない。
     */
    public function auditCutoff(): string
    {
        $ts = strtotime('-' . self::AUDIT_RETENTION_MONTHS . ' months', $this->clock->now());

        return gmdate('Y-m-d\TH:i:s\Z', $ts === false ? $this->clock->now() : $ts);
    }

    /** 13か月を過ぎた監査イベントの件数（画面に出すのはこの数だけ） */
    public function countAuditDue(): int
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT COUNT(*) FROM intake_audit_events WHERE created_at < :cutoff'
        );
        $stmt->execute([':cutoff' => $this->auditCutoff()]);

        return (int)$stmt->fetchColumn();
    }

    /**
     * 13か月を過ぎた監査イベントを物理削除する（SSOT v1.7 §9.1）。
     *
     * ★HMAC化IP も同じ行にあるので同時に消える。
     * ★この削除自体も `audit_purged` として同じ表へ残す。その行も13か月後に
     *   通常どおり削除対象になる（保持が循環して永久に残ることはない）。
     *
     * @return array{deleted:int}
     */
    public function purgeAudit(): array
    {
        $cutoff  = $this->auditCutoff();
        $deleted = 0;

        for ($i = 0; $i < self::MAX_BATCHES; ++$i) {
            $stmt = $this->db->pdo()->prepare(
                'SELECT id FROM intake_audit_events WHERE created_at < :cutoff ORDER BY id LIMIT :limit'
            );
            $stmt->bindValue(':cutoff', $cutoff);
            $stmt->bindValue(':limit', self::BATCH, \PDO::PARAM_INT);
            $stmt->execute();
            $ids = array_map('intval', array_column($stmt->fetchAll(), 'id'));

            if ($ids === []) {
                break;
            }

            $del = $this->db->pdo()->prepare(
                'DELETE FROM intake_audit_events WHERE id IN ('
                . implode(', ', array_fill(0, count($ids), '?')) . ')'
            );
            $deleted += $this->runByIds($del, $ids);
        }

        if ($deleted > 0) {
            // ★件数は監査本文へ書かない（`result_code` は固定語彙のみ）
            $this->audit->record(null, 'audit_purged', 'ok');
        }

        return ['deleted' => $deleted];
    }

    /* ==================================================== 管理 session の清掃 */

    /**
     * 期限切れ・失効済みの管理 session の件数（SSOT v1.7 §2.7-8）。
     * ★条件はリテラルで書く。**いま有効なものは1件も選ばない**。
     */
    public function countAdminSessionsDue(): int
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT COUNT(*) FROM intake_admin_sessions
              WHERE revoked_at IS NOT NULL OR expires_at <= :now OR absolute_expires_at <= :now'
        );
        $stmt->execute([':now' => $this->clock->iso()]);

        return (int)$stmt->fetchColumn();
    }

    /**
     * 期限切れ・失効済みの管理 session を物理削除する。
     * ★session hash をログにも監査にも出さない。残すのは件数だけ。
     *
     * @return array{deleted:int}
     */
    public function purgeAdminSessions(): array
    {
        $now     = $this->clock->iso();
        $deleted = 0;

        for ($i = 0; $i < self::MAX_BATCHES; ++$i) {
            $stmt = $this->db->pdo()->prepare(
                'SELECT id FROM intake_admin_sessions
                  WHERE revoked_at IS NOT NULL OR expires_at <= :now OR absolute_expires_at <= :now
                  ORDER BY id LIMIT :limit'
            );
            $stmt->bindValue(':now', $now);
            $stmt->bindValue(':limit', self::BATCH, \PDO::PARAM_INT);
            $stmt->execute();
            $ids = array_map('intval', array_column($stmt->fetchAll(), 'id'));

            if ($ids === []) {
                break;
            }

            $del = $this->db->pdo()->prepare(
                'DELETE FROM intake_admin_sessions WHERE id IN ('
                . implode(', ', array_fill(0, count($ids), '?')) . ')'
            );
            $deleted += $this->runByIds($del, $ids);
        }

        if ($deleted > 0) {
            $this->audit->record(null, 'admin_sessions_purged', 'ok');
        }

        return ['deleted' => $deleted];
    }

    /* ==================================================== 補助 */

    /**
     * 案件に紐づく行を消す。
     * ★SQL は**呼び出し側のリテラル**だけ。ここへ組み立て途中の文字列を渡さない。
     */
    private function deleteForCase(\PDO $pdo, string $literalSql, int $caseId): int
    {
        $stmt = $pdo->prepare($literalSql);
        $stmt->execute([':id' => $caseId]);

        return $stmt->rowCount();
    }

    /** @param list<int> $ids */
    private function runByIds(\PDOStatement $stmt, array $ids): int
    {
        foreach ($ids as $i => $id) {
            $stmt->bindValue($i + 1, $id, \PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->rowCount();
    }
}
