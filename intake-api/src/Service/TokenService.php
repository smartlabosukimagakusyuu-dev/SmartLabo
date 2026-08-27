<?php
/**
 * HP Intake API — token（SSOT §2.2 / §4）。
 *
 *  - random_bytes(32) → base64url 43文字。平文は発行時に1度だけ返す
 *  - DBへは SHA-256 hash のみ。**平文を保存する場所を作らない**
 *  - 有効期限14日。1案件につき有効 token は常に1本
 *  - 再発行時は、既存の有効 token と**そこから発行された session** を同一トランザクションで失効
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Service;

use SmartLabo\Intake\Config;
use SmartLabo\Intake\Db;
use SmartLabo\Intake\Support\Clock;
use SmartLabo\Intake\Support\Secret;

final class TokenService
{
    public function __construct(
        private readonly Db $db,
        private readonly Clock $clock,
        private readonly Audit $audit,
    ) {
    }

    /**
     * 新しい token を発行する。旧 token と関連 session は即時失効させる。
     * @return string 平文 token（★保存・記録してはならない。発行時の1回だけ）
     */
    public function issue(int $caseId): string
    {
        $plain = Secret::generate();
        $now   = $this->clock->iso();

        $this->db->transaction(function (\PDO $pdo) use ($caseId, $plain, $now): void {
            // 旧 token から発行された session を先に失効
            $pdo->prepare(
                'UPDATE intake_sessions SET revoked_at = :now
                  WHERE revoked_at IS NULL
                    AND token_id IN (SELECT id FROM intake_tokens
                                      WHERE intake_case_id = :case_id AND revoked_at IS NULL)'
            )->execute([':now' => $now, ':case_id' => $caseId]);

            // 旧 token を失効
            $pdo->prepare(
                'UPDATE intake_tokens SET revoked_at = :now
                  WHERE intake_case_id = :case_id AND revoked_at IS NULL'
            )->execute([':now' => $now, ':case_id' => $caseId]);

            $pdo->prepare(
                'INSERT INTO intake_tokens (intake_case_id, token_hash, expires_at, created_at)
                 VALUES (:case_id, :hash, :expires_at, :created_at)'
            )->execute([
                ':case_id'    => $caseId,
                ':hash'       => Secret::hash($plain),
                ':expires_at' => $this->clock->isoAfter(Config::TOKEN_TTL),
                ':created_at' => $now,
            ]);
        });

        $this->audit->record($caseId, 'token_issued', 'ok');

        return $plain;
    }

    /**
     * 平文 token を照合する。
     * @return array{row:?array<string,mixed>,reason:string} reason は監査用（外部へ出さない）
     */
    public function verify(string $plain): array
    {
        if (!Secret::isWellFormed($plain)) {
            return ['row' => null, 'reason' => 'invalid'];
        }

        $stmt = $this->db->pdo()->prepare(
            'SELECT * FROM intake_tokens WHERE token_hash = :hash'
        );
        $stmt->execute([':hash' => Secret::hash($plain)]);
        $row = $stmt->fetch();

        if ($row === false) {
            // 見つからない場合も同等の処理時間を使う（存在有無を時間差で漏らさない）
            Secret::matches($plain, null);

            return ['row' => null, 'reason' => 'not_found'];
        }
        if (!Secret::matches($plain, (string)$row['token_hash'])) {
            return ['row' => null, 'reason' => 'invalid'];
        }
        if ($row['revoked_at'] !== null) {
            return ['row' => null, 'reason' => 'revoked'];
        }
        if (!$this->clock->isFuture((string)$row['expires_at'])) {
            return ['row' => null, 'reason' => 'expired'];
        }

        return ['row' => $row, 'reason' => 'ok'];
    }

    public function touch(int $tokenId): void
    {
        $this->db->pdo()
            ->prepare('UPDATE intake_tokens SET last_used_at = :now WHERE id = :id')
            ->execute([':now' => $this->clock->iso(), ':id' => $tokenId]);
    }

    /** 案件の token と session をまとめて失効（locked / closed / 漏えい時） */
    public function revokeAllForCase(int $caseId, string $reason = 'ok'): void
    {
        $now = $this->clock->iso();
        $this->db->transaction(function (\PDO $pdo) use ($caseId, $now): void {
            $pdo->prepare(
                'UPDATE intake_sessions SET revoked_at = :now
                  WHERE intake_case_id = :case_id AND revoked_at IS NULL'
            )->execute([':now' => $now, ':case_id' => $caseId]);

            $pdo->prepare(
                'UPDATE intake_tokens SET revoked_at = :now
                  WHERE intake_case_id = :case_id AND revoked_at IS NULL'
            )->execute([':now' => $now, ':case_id' => $caseId]);
        });

        $this->audit->record($caseId, 'token_revoked', $reason);
    }

    /** 有効な token の本数（1案件1本であることの検証に使う） */
    public function activeCount(int $caseId): int
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT COUNT(*) FROM intake_tokens
              WHERE intake_case_id = :case_id AND revoked_at IS NULL AND expires_at > :now'
        );
        $stmt->execute([':case_id' => $caseId, ':now' => $this->clock->iso()]);

        return (int)$stmt->fetchColumn();
    }
}
