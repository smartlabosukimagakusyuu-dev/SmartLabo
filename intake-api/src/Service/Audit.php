<?php
/**
 * HP Intake API — 監査ログ（SSOT §2.5）。
 *
 * ★保存してよいのは case_id / event_type / result_code / ip_hmac / created_at のみ。
 *   token平文・回答本文・氏名・メール・電話・住所・Drive URL を保存しない。
 * ★token 検証失敗の理由（invalid / expired / revoked / not_found）は
 *   監査には**区別して記録する**が、外部応答は §4.6 の同一文言に統一する。
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Service;

use SmartLabo\Intake\Db;
use SmartLabo\Intake\Support\Clock;

final class Audit
{
    public const EVENTS = [
        'token_issued', 'token_revoked', 'token_accepted', 'token_rejected',
        'session_revoked', 'answer_saved', 'submitted', 'admin_viewed',
        'export_generated', 'drive_url_set', 'answers_deleted', 'rate_limited',
        // 4D（SSOT v1.4 §2.5）
        'drive_upload_confirmed', 'case_status_changed', 'admin_login', 'admin_logout',
        // 4D-R2（SSOT v1.6 §2.5 / §4.4.1）
        'token_reissued',
    ];

    public function __construct(
        private readonly Db $db,
        private readonly Clock $clock,
    ) {
    }

    public function record(?int $caseId, string $eventType, string $resultCode, ?string $ipHmac = null): void
    {
        if (!in_array($eventType, self::EVENTS, true)) {
            $eventType = 'token_rejected'; // 未知イベント名を素通しさせない
        }

        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO intake_audit_events (intake_case_id, event_type, result_code, ip_hmac, created_at)
             VALUES (:case_id, :event_type, :result_code, :ip_hmac, :created_at)'
        );
        $stmt->execute([
            ':case_id'     => $caseId,
            ':event_type'  => $eventType,
            ':result_code' => $resultCode,
            ':ip_hmac'     => $ipHmac,
            ':created_at'  => $this->clock->iso(),
        ]);
    }

    /** 監査イベントの件数（冪等性の検証に使う） */
    public function countFor(?int $caseId, ?string $eventType = null): int
    {
        $sql    = 'SELECT COUNT(*) FROM intake_audit_events WHERE ';
        $params = [];

        if ($caseId === null) {
            $sql .= 'intake_case_id IS NULL';
        } else {
            $sql .= 'intake_case_id = :case_id';
            $params[':case_id'] = $caseId;
        }
        if ($eventType !== null) {
            $sql .= ' AND event_type = :event_type';
            $params[':event_type'] = $eventType;
        }

        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }
}
