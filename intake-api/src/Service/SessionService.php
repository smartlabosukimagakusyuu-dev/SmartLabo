<?php
/**
 * HP Intake API — session（SSOT §2.6 / §4.5-B / §4.7）。
 *
 *  - random_bytes(32) → base64url 43文字。**Cookie にだけ平文**
 *  - DBへは SHA-256 hash のみ
 *  - 最終利用から24時間（都度延長）／絶対期限は発行から7日（延長しない）
 *  - 紐づく token が失効していれば session も無効
 *    （「session があるから token 失効を無視してよい」経路を作らない）
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Service;

use SmartLabo\Intake\Config;
use SmartLabo\Intake\Db;
use SmartLabo\Intake\Support\Clock;
use SmartLabo\Intake\Support\Secret;

final class SessionService
{
    public function __construct(
        private readonly Db $db,
        private readonly Clock $clock,
        private readonly Audit $audit,
    ) {
    }

    /**
     * token 検証済みの行から session を発行する。
     * @return string 平文 session secret（Cookie 以外へ保存しない）
     */
    public function start(int $caseId, int $tokenId): string
    {
        $plain = Secret::generate();

        $this->db->pdo()->prepare(
            'INSERT INTO intake_sessions
                (intake_case_id, token_id, session_hash, expires_at, absolute_expires_at, last_seen_at, created_at)
             VALUES (:case_id, :token_id, :hash, :expires_at, :absolute_expires_at, :last_seen_at, :created_at)'
        )->execute([
            ':case_id'             => $caseId,
            ':token_id'            => $tokenId,
            ':hash'                => Secret::hash($plain),
            ':expires_at'          => $this->clock->isoAfter(Config::SESSION_IDLE_TTL),
            ':absolute_expires_at' => $this->clock->isoAfter(Config::SESSION_ABSOLUTE_TTL),
            ':last_seen_at'        => $this->clock->iso(),
            ':created_at'          => $this->clock->iso(),
        ]);

        return $plain;
    }

    /**
     * Cookie の平文 session secret を照合する。
     * @return array{row:?array<string,mixed>,reason:string}
     */
    public function verify(?string $plain): array
    {
        if ($plain === null || !Secret::isWellFormed($plain)) {
            return ['row' => null, 'reason' => 'invalid'];
        }

        $stmt = $this->db->pdo()->prepare(
            'SELECT s.*, t.revoked_at AS token_revoked_at, t.expires_at AS token_expires_at
               FROM intake_sessions s
               JOIN intake_tokens   t ON t.id = s.token_id
              WHERE s.session_hash = :hash'
        );
        $stmt->execute([':hash' => Secret::hash($plain)]);
        $row = $stmt->fetch();

        if ($row === false) {
            Secret::matches($plain, null);

            return ['row' => null, 'reason' => 'not_found'];
        }
        if (!Secret::matches($plain, (string)$row['session_hash'])) {
            return ['row' => null, 'reason' => 'invalid'];
        }
        if ($row['revoked_at'] !== null) {
            return ['row' => null, 'reason' => 'revoked'];
        }
        if (!$this->clock->isFuture((string)$row['expires_at'])) {
            return ['row' => null, 'reason' => 'expired'];
        }
        if (!$this->clock->isFuture((string)$row['absolute_expires_at'])) {
            return ['row' => null, 'reason' => 'absolute_expired'];
        }
        // 紐づく token の有効性（SSOT §2.6 の「session は token の下位」）
        if ($row['token_revoked_at'] !== null) {
            return ['row' => null, 'reason' => 'token_revoked'];
        }
        if (!$this->clock->isFuture((string)$row['token_expires_at'])) {
            return ['row' => null, 'reason' => 'token_expired'];
        }

        return ['row' => $row, 'reason' => 'ok'];
    }

    /** 最終利用から24時間へ延長する（絶対期限は延長しない） */
    public function touch(int $sessionId): void
    {
        $this->db->pdo()->prepare(
            'UPDATE intake_sessions
                SET last_seen_at = :now, expires_at = :expires_at
              WHERE id = :id'
        )->execute([
            ':now'        => $this->clock->iso(),
            ':expires_at' => $this->clock->isoAfter(Config::SESSION_IDLE_TTL),
            ':id'         => $sessionId,
        ]);
    }

    /** ログアウト・管理操作による個別失効 */
    public function revoke(int $sessionId, ?int $caseId = null): void
    {
        $this->db->pdo()
            ->prepare('UPDATE intake_sessions SET revoked_at = :now WHERE id = :id AND revoked_at IS NULL')
            ->execute([':now' => $this->clock->iso(), ':id' => $sessionId]);

        $this->audit->record($caseId, 'session_revoked', 'ok');
    }

    /** locked / closed 時の全失効 */
    public function revokeAllForCase(int $caseId): void
    {
        $this->db->pdo()->prepare(
            'UPDATE intake_sessions SET revoked_at = :now
              WHERE intake_case_id = :case_id AND revoked_at IS NULL'
        )->execute([':now' => $this->clock->iso(), ':case_id' => $caseId]);

        $this->audit->record($caseId, 'session_revoked', 'ok');
    }

    public function activeCount(int $caseId): int
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT COUNT(*) FROM intake_sessions
              WHERE intake_case_id = :case_id AND revoked_at IS NULL AND expires_at > :now'
        );
        $stmt->execute([':case_id' => $caseId, ':now' => $this->clock->iso()]);

        return (int)$stmt->fetchColumn();
    }
}
