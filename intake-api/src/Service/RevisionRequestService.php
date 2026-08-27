<?php
/**
 * HP Intake API — 修正依頼（SSOT v1.5 §2.8）。
 *
 * 差し戻し（needs_revision）の理由を**構造として持つ**。
 *
 *  - 理由を回答欄（intake_answers）へ押し込まない
 *  - 監査ログへ本文を入れない
 *  - `requested_paths` は §3 の正式パス（AnswerPaths::ALL）だけ
 *  - `message` は最大1000文字。HTML として扱わない
 *  - 1案件に複数回。過去の依頼を**削除も上書きもしない**
 *  - 状態変更（reviewed/submitted → needs_revision）と**同一トランザクション**
 *  - 店舗の再提出が成功したら `open` をすべて `resolved` にする
 *
 * ★message と requested_paths をアプリログへ出さない（SSOT §10.7）。
 * ★管理者を識別する列を持たない（Phase 1 は代表1名。SSOT §2.8-10）。
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Service;

use SmartLabo\Intake\AnswerPaths;
use SmartLabo\Intake\Db;
use SmartLabo\Intake\Support\Clock;

final class RevisionRequestService
{
    /** 店舗向けメッセージの上限（SSOT §2.8-3）。超えたら**切り捨てず拒否** */
    public const MESSAGE_MAX = 1000;

    public const STATUS_OPEN     = 'open';
    public const STATUS_RESOLVED = 'resolved';

    public function __construct(
        private readonly Db $db,
        private readonly Clock $clock,
    ) {
    }

    /**
     * 入力を検査する（保存はしない）。
     *
     * @return array{ok:bool,error?:string,paths?:list<string>,message?:?string}
     */
    public function validate(mixed $paths, mixed $message): array
    {
        $normalized = AnswerPaths::normalize($paths);
        if ($normalized['ok'] !== true) {
            return ['ok' => false, 'error' => (string)$normalized['error']];
        }

        if ($message === null || $message === '') {
            return ['ok' => true, 'paths' => $normalized['paths'], 'message' => null];
        }
        if (!is_string($message)) {
            return ['ok' => false, 'error' => 'bad_message'];
        }

        // ★制御文字を落とす（TAB / LF / CR は残す。SSOT §3.0-2）
        $clean = (string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $message);
        $clean = trim($clean);

        if ($clean === '') {
            return ['ok' => true, 'paths' => $normalized['paths'], 'message' => null];
        }
        // ★文字数で数える（バイト数ではない。SSOT §3.0-3）
        if (mb_strlen($clean, 'UTF-8') > self::MESSAGE_MAX) {
            return ['ok' => false, 'error' => 'message_too_long'];
        }
        if (!mb_check_encoding($clean, 'UTF-8')) {
            return ['ok' => false, 'error' => 'bad_message'];
        }

        return ['ok' => true, 'paths' => $normalized['paths'], 'message' => $clean];
    }

    /**
     * 依頼を1件つくる。
     *
     * ★**呼び出し側が開いたトランザクションの中で呼ぶこと**（状態変更と同時に確定させる）。
     *   ここでトランザクションを開かないのはそのためである。
     *
     * @param list<string> $paths 検査済みのパス
     */
    public function insert(int $caseId, array $paths, ?string $message): int
    {
        $number = $this->nextNumber($caseId);

        $this->db->pdo()->prepare(
            'INSERT INTO intake_revision_requests
                (intake_case_id, request_number, requested_paths_json, message, status, created_at)
             VALUES (:case_id, :number, :paths, :message, :status, :now)'
        )->execute([
            ':case_id' => $caseId,
            ':number'  => $number,
            ':paths'   => (string)json_encode(array_values($paths), JSON_UNESCAPED_UNICODE),
            ':message' => $message,
            ':status'  => self::STATUS_OPEN,
            ':now'     => $this->clock->iso(),
        ]);

        return $number;
    }

    /** 案件内の次の通し番号（1から） */
    public function nextNumber(int $caseId): int
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT COALESCE(MAX(request_number), 0) + 1
               FROM intake_revision_requests WHERE intake_case_id = :id'
        );
        $stmt->execute([':id' => $caseId]);

        return (int)$stmt->fetchColumn();
    }

    /**
     * 店舗へ返す「いま対応が必要な依頼」。
     *
     * ★`open` のものだけ。`resolved` の本文を店舗へ返さない（SSOT §2.8-9）。
     * ★DBの内部IDを含めない。
     *
     * @return list<array{request_number:int,requested_paths:list<string>,message:?string,created_at:string}>
     */
    public function openForCase(int $caseId): array
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT request_number, requested_paths_json, message, created_at
               FROM intake_revision_requests
              WHERE intake_case_id = :id AND status = :open
              ORDER BY request_number ASC'
        );
        $stmt->execute([':id' => $caseId, ':open' => self::STATUS_OPEN]);

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $decoded = json_decode((string)$row['requested_paths_json'], true);
            $paths   = [];
            foreach (is_array($decoded) ? $decoded : [] as $path) {
                // 保存後に一覧が変わっていても、知らないパスを店舗へ出さない
                if (is_string($path) && AnswerPaths::isValid($path)) {
                    $paths[] = $path;
                }
            }

            $out[] = [
                'request_number'  => (int)$row['request_number'],
                'requested_paths' => $paths,
                'message'         => $row['message'] === null ? null : (string)$row['message'],
                'created_at'      => (string)$row['created_at'],
            ];
        }

        return $out;
    }

    /**
     * 店舗の再提出が成立したので、開いている依頼を閉じる（SSOT §2.8-8）。
     *
     * ★過去の依頼を消さない。`status` と `resolved_at` を書くだけ。
     * @return int 閉じた件数
     */
    public function resolveOpen(int $caseId): int
    {
        $stmt = $this->db->pdo()->prepare(
            'UPDATE intake_revision_requests
                SET status = :resolved, resolved_at = :now
              WHERE intake_case_id = :id AND status = :open'
        );
        $stmt->execute([
            ':resolved' => self::STATUS_RESOLVED,
            ':now'      => $this->clock->iso(),
            ':id'       => $caseId,
            ':open'     => self::STATUS_OPEN,
        ]);

        return $stmt->rowCount();
    }

    /**
     * 管理画面・書き出し用の一覧（本文を含む／含まないを呼び出し側が選ぶ）。
     * @return list<array<string,mixed>>
     */
    public function allForCase(int $caseId): array
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT request_number, requested_paths_json, message, status, created_at, resolved_at
               FROM intake_revision_requests
              WHERE intake_case_id = :id
              ORDER BY request_number ASC'
        );
        $stmt->execute([':id' => $caseId]);

        return $stmt->fetchAll();
    }

    public function countFor(int $caseId, ?string $status = null): int
    {
        $sql    = 'SELECT COUNT(*) FROM intake_revision_requests WHERE intake_case_id = :id';
        $params = [':id' => $caseId];
        if ($status !== null) {
            $sql .= ' AND status = :status';
            $params[':status'] = $status;
        }
        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }
}
