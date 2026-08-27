<?php
/**
 * HP Intake API — 案件（SSOT §2.1 / §5）。
 *
 * 状態遷移（SSOT §5.1）:
 *   draft → submitted →（needs_revision → submitted）/（reviewed）→ locked → closed
 *   任意 → closed（中止案件）
 *
 * ★locked / closed へ移した時点で token と session をすべて失効させる（SSOT §5.2）。
 * ★公開承認はこの状態遷移に含めない。正式記録は Smart Labo Operations（SSOT §5.4）。
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Service;

use SmartLabo\Intake\Db;
use SmartLabo\Intake\Migrator;
use SmartLabo\Intake\Support\Clock;
use SmartLabo\Intake\Support\Crypto;
use SmartLabo\Intake\Support\DriveLink;

final class CaseService
{
    public const STATUSES = ['draft', 'submitted', 'needs_revision', 'reviewed', 'locked', 'closed'];

    /** 店舗が入力・提出できる状態 */
    public const EDITABLE = ['draft', 'needs_revision'];

    /** 店舗が閲覧できる状態（locked / closed は token/session ごと失効させる） */
    public const READABLE = ['draft', 'submitted', 'needs_revision', 'reviewed'];

    /**
     * 許可する遷移（これ以外は行わない。SSOT §5.1）。
     *
     * ★v1.5 で `reviewed` → `needs_revision` を追加した（代表判断の案B）。
     *   確認後に不足が見つかっても戻せるようにするため。
     *   `locked` / `closed` からは戻さない。
     */
    private const TRANSITIONS = [
        'draft'          => ['submitted', 'closed'],
        'submitted'      => ['needs_revision', 'reviewed', 'closed'],
        'needs_revision' => ['submitted', 'closed'],
        'reviewed'       => ['needs_revision', 'locked', 'closed'],
        'locked'         => ['closed'],
        'closed'         => [],
    ];

    /** 修正依頼を出してよい状態（SSOT v1.5 §5.1） */
    public const REVISABLE = ['submitted', 'reviewed'];

    /**
     * ご案内リンクを再発行してよい状態（SSOT v1.6 §4.4.1）。
     *
     * ★`submitted` / `reviewed` では出さない。受付済みの案件へ新しい編集リンクを配らない。
     *   修正が必要なら、先に `needs_revision` へ差し戻してから再発行する。
     * ★`locked` / `closed` は確定済み（token ごと失効させている）。
     */
    public const REISSUABLE = ['draft', 'needs_revision'];

    public function __construct(
        private readonly Db $db,
        private readonly Clock $clock,
        private readonly Audit $audit,
        private readonly TokenService $tokens,
        private readonly SessionService $sessions,
        private readonly Crypto $crypto,
    ) {
    }

    public function create(string $caseNumber, string $shopDisplayName, string $contractType = 'standalone'): int
    {
        if (!in_array($contractType, ['salon', 'standalone'], true)) {
            throw new \InvalidArgumentException('contract_type');
        }
        $now = $this->clock->iso();

        return (int)$this->db->transaction(function (\PDO $pdo) use ($caseNumber, $shopDisplayName, $contractType, $now): int {
            $pdo->prepare(
                'INSERT INTO intake_cases
                    (case_number, shop_display_name, contract_type, status, schema_version, created_at, updated_at)
                 VALUES (:case_number, :shop, :contract_type, :status, :schema_version, :created_at, :updated_at)'
            )->execute([
                ':case_number'    => $caseNumber,
                ':shop'           => $shopDisplayName,
                ':contract_type'  => $contractType,
                ':status'         => 'draft',
                ':schema_version' => Migrator::ANSWER_SCHEMA_VERSION,
                ':created_at'     => $now,
                ':updated_at'     => $now,
            ]);
            $caseId = (int)$pdo->lastInsertId();

            // 回答行は空 JSON で初期化する（「行が無い」状態を作らない。SSOT §2.3）
            $cols   = [];
            $params = [':case_id' => $caseId, ':schema_version' => Migrator::ANSWER_SCHEMA_VERSION, ':now' => $now];
            foreach (Migrator::ANSWER_SECTIONS as $section) {
                $cols[':' . $section]                = in_array($section, Migrator::LIST_SECTIONS, true) ? '[]' : '{}';
                $params[':' . $section . '_v']       = $cols[':' . $section];
            }
            $names        = array_map(static fn (string $s): string => $s . '_json', Migrator::ANSWER_SECTIONS);
            $placeholders = array_map(static fn (string $s): string => ':' . $s . '_v', Migrator::ANSWER_SECTIONS);

            $pdo->prepare(
                'INSERT INTO intake_answers (intake_case_id, schema_version, ' . implode(', ', $names) . ', version, created_at, updated_at)
                 VALUES (:case_id, :schema_version, ' . implode(', ', $placeholders) . ', 1, :now, :now)'
            )->execute($params);

            return $caseId;
        });
    }

    /** @return array<string,mixed>|null */
    public function find(int $caseId): ?array
    {
        $stmt = $this->db->pdo()->prepare('SELECT * FROM intake_cases WHERE id = :id');
        $stmt->execute([':id' => $caseId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function transitionTo(int $caseId, string $next): void
    {
        $case = $this->find($caseId);
        if ($case === null) {
            throw new \RuntimeException('case not found');
        }
        $current = (string)$case['status'];
        if (!in_array($next, self::TRANSITIONS[$current] ?? [], true)) {
            throw new \DomainException('transition not allowed');
        }

        $now    = $this->clock->iso();
        $fields = ['status = :status', 'updated_at = :now'];
        $params = [':status' => $next, ':now' => $now, ':id' => $caseId];

        if ($next === 'submitted' && $case['submitted_at'] === null) {
            $fields[]              = 'submitted_at = :submitted_at';
            $params[':submitted_at'] = $now;
        }
        if ($next === 'locked') {
            $fields[]           = 'locked_at = :locked_at';
            $params[':locked_at'] = $now;
        }
        if ($next === 'closed') {
            $fields[]           = 'closed_at = :closed_at';
            $params[':closed_at'] = $now;
        }

        $this->db->pdo()
            ->prepare('UPDATE intake_cases SET ' . implode(', ', $fields) . ' WHERE id = :id')
            ->execute($params);

        // locked / closed では token と session をすべて失効（SSOT §5.2 / §9.2）
        if ($next === 'locked' || $next === 'closed') {
            $this->tokens->revokeAllForCase($caseId, $next);
            $this->sessions->revokeAllForCase($caseId);
        }
    }

    /**
     * Drive フォルダURLと共有先メールを暗号化して保存する（SSOT v1.5 §7.3）。
     *
     * ★受け入れ条件は DriveLink で厳しく検査する。ログ・応答・書き出しへ出さない。
     * ★共有先メールは、店舗画面の案内文「このフォルダは○○にのみ共有しています」に使う。
     */
    public function setDriveFolder(int $caseId, string $url, string $label, ?string $sharedEmail = null): void
    {
        $checked = DriveLink::checkUrl($url);
        if ($checked['ok'] !== true) {
            throw new \InvalidArgumentException('drive url rejected: ' . (string)$checked['error']);
        }

        $fields = [
            'drive_folder_url_enc = :enc',
            'drive_folder_label = :label',
            'updated_at = :now',
        ];
        $params = [
            ':enc'   => $this->crypto->encrypt((string)$checked['url']),
            ':label' => $label,
            ':now'   => $this->clock->iso(),
            ':id'    => $caseId,
        ];

        if ($sharedEmail !== null) {
            $email = DriveLink::checkEmail($sharedEmail);
            if ($email['ok'] !== true) {
                throw new \InvalidArgumentException('drive shared email rejected');
            }
            $fields[]        = 'drive_shared_email_enc = :email';
            $params[':email'] = $this->crypto->encrypt((string)$email['email']);
        }

        $this->db->pdo()
            ->prepare('UPDATE intake_cases SET ' . implode(', ', $fields) . ' WHERE id = :id')
            ->execute($params);

        // ★URL もメールも監査へ書かない。設定した事実だけを残す
        $this->audit->record($caseId, 'drive_url_set', 'ok');
    }

    public function driveFolderUrl(int $caseId): ?string
    {
        $case = $this->find($caseId);
        if ($case === null || $case['drive_folder_url_enc'] === null) {
            return null;
        }

        return $this->crypto->decrypt((string)$case['drive_folder_url_enc']);
    }

    /**
     * 共有先メールを復号する。
     * ★呼んでよいのは「管理画面の詳細表示」と「認証済み店舗の GET /case」だけ（SSOT §7.3）。
     */
    public function driveSharedEmail(int $caseId): ?string
    {
        $case = $this->find($caseId);
        if ($case === null || ($case['drive_shared_email_enc'] ?? null) === null) {
            return null;
        }

        return $this->crypto->decrypt((string)$case['drive_shared_email_enc']);
    }

    /**
     * 店舗による素材アップロード完了の申告（SSOT §11.1-9 / v1.4 §2.5）。
     *
     * ★冪等。すでに申告済みなら**時刻を上書きせず、監査も増やさない**。
     * ★取消は作らない（4D の範囲外）。
     *
     * @return bool 今回あらたに記録したら true（＝監査を1件だけ増やした）
     */
    public function confirmDriveUpload(int $caseId): bool
    {
        $stmt = $this->db->pdo()->prepare(
            'UPDATE intake_cases
                SET drive_upload_confirmed_at = :now, updated_at = :now
              WHERE id = :id AND drive_upload_confirmed_at IS NULL'
        );
        $stmt->execute([':now' => $this->clock->iso(), ':id' => $caseId]);

        if ($stmt->rowCount() === 0) {
            return false; // すでに申告済み。同じ結果を返すだけ
        }

        $this->audit->record($caseId, 'drive_upload_confirmed', 'ok');

        return true;
    }

    /**
     * 管理者による状態変更（SSOT §5.1 / v1.4 §2.5）。
     *
     * ★冪等。すでにその状態なら**何もせず成功**を返す（履歴も監査も増やさない）。
     * ★現在状態を条件に入れた UPDATE で、同時操作の取りこぼしを防ぐ。
     * ★独自の status を作らない。許可された遷移だけを通す。
     *
     * @return array{ok:bool,error?:string,changed:bool}
     */
    public function adminChangeStatus(int $caseId, string $next, string $historyEvent): array
    {
        $case = $this->find($caseId);
        if ($case === null) {
            return ['ok' => false, 'error' => 'not_found', 'changed' => false];
        }

        if (($case['deleted_at'] ?? null) !== null) {
            // ★保持期限で削除済みの案件は状態を動かさない（SSOT v1.7 §9.3）
            return ['ok' => false, 'error' => 'already_deleted', 'changed' => false];
        }

        $current = (string)$case['status'];
        if ($current === $next) {
            // 同じ操作の再送。成功扱いにして、記録は増やさない
            return ['ok' => true, 'changed' => false];
        }
        if (!in_array($next, self::TRANSITIONS[$current] ?? [], true)) {
            return ['ok' => false, 'error' => 'invalid_transition', 'changed' => false];
        }

        $now = $this->clock->iso();

        $stmt = $this->db->pdo()->prepare(
            'UPDATE intake_cases SET status = :next, updated_at = :now
              WHERE id = :id AND status = :current'
        );
        $stmt->execute([':next' => $next, ':now' => $now, ':id' => $caseId, ':current' => $current]);

        if ($stmt->rowCount() === 0) {
            // 判定してから UPDATE するまでの間に、別の操作が状態を変えた
            return ['ok' => false, 'error' => 'conflict', 'changed' => false];
        }

        $this->db->pdo()->prepare(
            'INSERT INTO intake_submission_history
                (intake_case_id, event_type, schema_version, submitted_at, result_code)
             VALUES (:id, :event, :schema_version, :now, :ok)'
        )->execute([
            ':id'             => $caseId,
            ':event'          => $historyEvent,
            ':schema_version' => Migrator::ANSWER_SCHEMA_VERSION,
            ':now'            => $now,
            ':ok'             => 'ok',
        ]);

        $this->audit->record($caseId, 'case_status_changed', 'ok');

        return ['ok' => true, 'changed' => true];
    }

    /**
     * 管理画面から `reviewed` → `locked` へ確定させる（SSOT v1.7 §5.1 / §6）。
     *
     * 意味は「**店舗入力を確定し、通常編集を終了した**」であり、削除ではない。
     * 回答・提出履歴・修正依頼・Drive 情報には一切触れない。
     *
     * ★状態変更・履歴・token 失効・session 失効を**同一トランザクション**で行う。
     *   「確定したのに古いリンクがまだ生きている」状態を1瞬も作らない。
     *   そのため TokenService / SessionService の（自前でトランザクションを開く）
     *   メソッドは使わず、ここで直接 UPDATE する。
     * ★`reviewed` からのみ。`locked` からは needs_revision へも戻さず、再発行もしない
     *   （`REVISABLE` / `REISSUABLE` のどちらにも `locked` を入れない）。
     * ★冪等。すでに `locked` なら何もせず成功を返す（履歴も監査も増やさない）。
     *
     * @return array{ok:bool,error?:string,changed:bool}
     */
    public function adminLock(int $caseId): array
    {
        $case = $this->find($caseId);
        if ($case === null) {
            return ['ok' => false, 'error' => 'not_found', 'changed' => false];
        }
        if (($case['deleted_at'] ?? null) !== null) {
            return ['ok' => false, 'error' => 'already_deleted', 'changed' => false];
        }

        $current = (string)$case['status'];
        if ($current === 'locked') {
            return ['ok' => true, 'changed' => false];
        }
        if (!in_array('locked', self::TRANSITIONS[$current] ?? [], true)) {
            return ['ok' => false, 'error' => 'invalid_transition', 'changed' => false];
        }

        $now = $this->clock->iso();

        try {
            return $this->db->transaction(function (\PDO $pdo) use ($caseId, $current, $now): array {
                $stmt = $pdo->prepare(
                    'UPDATE intake_cases SET status = :next, locked_at = :now, updated_at = :now
                      WHERE id = :id AND status = :current'
                );
                $stmt->execute([':next' => 'locked', ':now' => $now, ':id' => $caseId, ':current' => $current]);
                if ($stmt->rowCount() === 0) {
                    return ['ok' => false, 'error' => 'conflict', 'changed' => false];
                }

                // 店舗 session → token の順に失効させる（SSOT §5.2 / §9.2）
                $pdo->prepare(
                    'UPDATE intake_sessions SET revoked_at = :now
                      WHERE intake_case_id = :id AND revoked_at IS NULL'
                )->execute([':now' => $now, ':id' => $caseId]);

                $pdo->prepare(
                    'UPDATE intake_tokens SET revoked_at = :now
                      WHERE intake_case_id = :id AND revoked_at IS NULL'
                )->execute([':now' => $now, ':id' => $caseId]);

                $pdo->prepare(
                    'INSERT INTO intake_submission_history
                        (intake_case_id, event_type, schema_version, submitted_at, result_code)
                     VALUES (:id, :event, :schema_version, :now, :ok)'
                )->execute([
                    ':id'             => $caseId,
                    ':event'          => 'locked',
                    ':schema_version' => Migrator::ANSWER_SCHEMA_VERSION,
                    ':now'            => $now,
                    ':ok'             => 'ok',
                ]);

                $this->audit->record($caseId, 'case_status_changed', 'ok');
                $this->audit->record($caseId, 'token_revoked', 'locked');
                $this->audit->record($caseId, 'session_revoked', 'ok');

                return ['ok' => true, 'changed' => true];
            });
        } catch (\PDOException $e) {
            return ['ok' => false, 'error' => 'conflict', 'changed' => false];
        }
    }

    /**
     * 修正依頼を出して `needs_revision` へ差し戻す（SSOT v1.5 §2.8-7 / §5.1）。
     *
     * ★**状態変更と依頼の作成を同一トランザクション**で行う。
     *   「差し戻したのに理由が無い」「理由はあるのに差し戻っていない」を作らない。
     * ★`submitted` / `reviewed` からのみ。`locked` / `closed` からは戻さない。
     *
     * @param list<string> $paths 検査済みのパス
     * @return array{ok:bool,error?:string,request_number?:int}
     */
    public function requestRevision(
        int $caseId,
        array $paths,
        ?string $message,
        RevisionRequestService $revisions,
    ): array {
        $case = $this->find($caseId);
        if ($case === null) {
            return ['ok' => false, 'error' => 'not_found'];
        }

        if (($case['deleted_at'] ?? null) !== null) {
            return ['ok' => false, 'error' => 'already_deleted'];
        }

        $current = (string)$case['status'];
        if (!in_array($current, self::REVISABLE, true)) {
            return ['ok' => false, 'error' => 'invalid_transition'];
        }
        if (!in_array('needs_revision', self::TRANSITIONS[$current] ?? [], true)) {
            return ['ok' => false, 'error' => 'invalid_transition'];
        }

        $now = $this->clock->iso();

        try {
            return $this->db->transaction(function (\PDO $pdo) use ($caseId, $current, $now, $paths, $message, $revisions): array {
                // 現在状態を条件に入れる（判定と更新の間に変わっていたら止める）
                $stmt = $pdo->prepare(
                    'UPDATE intake_cases SET status = :next, updated_at = :now
                      WHERE id = :id AND status = :current'
                );
                $stmt->execute([
                    ':next'    => 'needs_revision',
                    ':now'     => $now,
                    ':id'      => $caseId,
                    ':current' => $current,
                ]);
                if ($stmt->rowCount() === 0) {
                    return ['ok' => false, 'error' => 'conflict'];
                }

                $number = $revisions->insert($caseId, $paths, $message);

                $pdo->prepare(
                    'INSERT INTO intake_submission_history
                        (intake_case_id, event_type, schema_version, submitted_at, result_code)
                     VALUES (:id, :event, :schema_version, :now, :ok)'
                )->execute([
                    ':id'             => $caseId,
                    ':event'          => 'revision_requested',
                    ':schema_version' => Migrator::ANSWER_SCHEMA_VERSION,
                    ':now'            => $now,
                    ':ok'             => 'ok',
                ]);

                // ★監査には本文を持たせない（SSOT §2.8-5）
                $this->audit->record($caseId, 'case_status_changed', 'ok');

                return ['ok' => true, 'request_number' => $number];
            });
        } catch (\PDOException $e) {
            return ['ok' => false, 'error' => 'conflict'];
        }
    }

    /** 管理画面の一覧（回答本文・PII を持ち出さない。SSOT §7 の一覧要件） */
    public function listForAdmin(int $limit = 200): array
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT case_number, status, created_at, submitted_at,
                    drive_upload_confirmed_at, updated_at, retention_delete_due
               FROM intake_cases
              ORDER BY (submitted_at IS NULL), submitted_at DESC, created_at DESC
              LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * 案件番号を採番する（SSOT §2.1 の形式にならう）。
     *
     * 形式: `HP-YYYYMM-NNNN`（`dev/preview-seed.php` と同じ形）。
     * ★店舗名を含めない。連番は**その年月の中**で 1 から数える。
     * ★呼び出し側は、採番と INSERT を同じトランザクションに入れること。
     */
    public function nextCaseNumber(?string $yearMonth = null): string
    {
        $ym     = $yearMonth ?? substr(str_replace('-', '', $this->clock->iso()), 0, 6);
        $prefix = 'HP-' . $ym . '-';

        $stmt = $this->db->pdo()->prepare(
            'SELECT case_number FROM intake_cases
              WHERE case_number LIKE :prefix
              ORDER BY case_number DESC LIMIT 1'
        );
        $stmt->execute([':prefix' => $prefix . '%']);
        $last = $stmt->fetchColumn();

        $next = 1;
        if ($last !== false && preg_match('/-(\d{4})$/', (string)$last, $m) === 1) {
            $next = (int)$m[1] + 1;
        }

        return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * 管理画面からの案件作成（4D-R1）。
     *
     * ★採番と INSERT を同一トランザクションで行い、番号の衝突を避ける。
     * ★契約金額・Stripe・公開承認・営業履歴は受け取らない（SSOT §8 / §11.2）。
     *
     * @return array{ok:bool,error?:string,case_id?:int,case_number?:string}
     */
    public function createFromAdmin(string $shopDisplayName, string $contractType): array
    {
        $name = trim($shopDisplayName);
        if ($name === '' || mb_strlen($name, 'UTF-8') > 100) {
            return ['ok' => false, 'error' => 'bad_name'];
        }
        if (!in_array($contractType, ['salon', 'standalone'], true)) {
            return ['ok' => false, 'error' => 'bad_contract_type'];
        }

        // ★create() が内側でトランザクションを開くため、ここでは開かない（PDO は入れ子にできない）。
        //   番号の衝突は UNIQUE(case_number) が最後に止める。負けたら採番し直す。
        for ($attempt = 0; $attempt < 3; ++$attempt) {
            $number = $this->nextCaseNumber();
            try {
                $caseId = $this->create($number, $name, $contractType);
            } catch (\PDOException $e) {
                if (self::isUniqueViolation($e)) {
                    continue;
                }
                throw $e;
            }

            return ['ok' => true, 'case_id' => $caseId, 'case_number' => $number];
        }

        return ['ok' => false, 'error' => 'conflict'];
    }

    private static function isUniqueViolation(\PDOException $e): bool
    {
        return ($e->getCode() === '23000' || ($e->errorInfo[1] ?? null) === 19)
            && stripos($e->getMessage(), 'unique') !== false;
    }

    /** @return array<string,mixed>|null */
    public function findByNumber(string $caseNumber): ?array
    {
        $stmt = $this->db->pdo()->prepare('SELECT * FROM intake_cases WHERE case_number = :n');
        $stmt->execute([':n' => $caseNumber]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }
}
