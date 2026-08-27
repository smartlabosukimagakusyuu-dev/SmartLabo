<?php
/**
 * HP Intake API — 管理画面の認証（SSOT v1.4 §2.7 / §10.8）。
 *
 *  - 代表1名。アカウント表を作らない。資格情報は private/intake-config.php
 *  - パスワードは password_hash() の hash のみを持つ。平文を保存しない
 *  - **未設定なら動かさない（fail closed）**
 *  - session secret / CSRF token は random_bytes(32) → base64url 43文字。DBへは hash のみ
 *  - idle 30分 / 絶対 8時間。ログイン成功時に必ず新しい session を作る（fixation 対策）
 *  - 店舗の session（SessionService）を流用しない。逆も行わない
 *
 * ★管理者ID・パスワード・session secret・CSRF token をログへ出さない（SSOT §10.7）。
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Service;

use SmartLabo\Intake\Config;
use SmartLabo\Intake\Db;
use SmartLabo\Intake\Support\Clock;
use SmartLabo\Intake\Support\Secret;

final class AdminAuth
{
    /**
     * ID が一致しなかったときに照合する使い捨ての hash。
     * ★存在しないIDでも password_verify() を必ず1回通し、応答時間の差を抑える
     *   （SSOT §10.8 のログイン防御3）。この hash に一致する平文は運用に存在しない。
     */
    private const DUMMY_HASH = '$2y$10$usesomesillystringforeseeeaHUHVlBLYNGRTe7jrDkyy1Zsq5x1u';

    public function __construct(
        private readonly Config $config,
        private readonly Db $db,
        private readonly Clock $clock,
        private readonly Audit $audit,
        private readonly RateLimiter $rateLimiter,
    ) {
    }

    public function enabled(): bool
    {
        return $this->config->adminEnabled();
    }

    /**
     * ログインする。
     *
     * ★成否にかかわらず**同じ固定文言**を返す（IDの存在有無を漏らさない）。
     * @return array{ok:bool,secret?:string,csrf?:string,reason:string}
     */
    public function login(string $id, string $password, string $ipHmac): array
    {
        // 資格情報が未設定なら、そもそも通さない
        if (!$this->enabled()) {
            $this->audit->record(null, 'admin_login', 'invalid', $ipHmac);

            return ['ok' => false, 'reason' => 'disabled'];
        }

        // HMAC化IP単位で 10分5回
        if (!$this->rateLimiter->allow('admin_login', $ipHmac)) {
            $this->audit->record(null, 'admin_login', 'rate_limited', $ipHmac);

            return ['ok' => false, 'reason' => 'rate_limited'];
        }

        // ★IDが違っても password_verify() を1回通す（時間差を作らない）
        $idMatches = hash_equals((string)$this->config->adminId, $id);
        $target    = $idMatches ? (string)$this->config->adminPasswordHash : self::DUMMY_HASH;
        $verified  = password_verify($password, $target);

        if (!$idMatches || !$verified) {
            $this->audit->record(null, 'admin_login', 'invalid', $ipHmac);

            return ['ok' => false, 'reason' => 'invalid'];
        }

        // 成功。★必ず新しい session を作る（session fixation 対策）
        $issued = $this->issueSession();
        $this->audit->record(null, 'admin_login', 'ok', $ipHmac);

        return ['ok' => true, 'secret' => $issued['secret'], 'csrf' => $issued['csrf'], 'reason' => 'ok'];
    }

    /** @return array{secret:string,csrf:string} */
    private function issueSession(): array
    {
        $secret = Secret::generate();
        $csrf   = Secret::generate();

        $this->db->pdo()->prepare(
            'INSERT INTO intake_admin_sessions
                (session_hash, csrf_hash, expires_at, absolute_expires_at, last_seen_at, created_at)
             VALUES (:hash, :csrf, :expires_at, :absolute_expires_at, :now, :now)'
        )->execute([
            ':hash'                => Secret::hash($secret),
            ':csrf'                => Secret::hash($csrf),
            ':expires_at'          => $this->clock->isoAfter(Config::ADMIN_SESSION_IDLE_TTL),
            ':absolute_expires_at' => $this->clock->isoAfter(Config::ADMIN_SESSION_ABSOLUTE_TTL),
            ':now'                 => $this->clock->iso(),
        ]);

        return ['secret' => $secret, 'csrf' => $csrf];
    }

    /**
     * Cookie の平文から管理 session を照合する。
     * @return array<string,mixed>|null
     */
    public function verify(?string $plain): ?array
    {
        if (!$this->enabled() || $plain === null || !Secret::isWellFormed($plain)) {
            return null;
        }

        $stmt = $this->db->pdo()->prepare(
            'SELECT * FROM intake_admin_sessions WHERE session_hash = :hash'
        );
        $stmt->execute([':hash' => Secret::hash($plain)]);
        $row = $stmt->fetch();

        if ($row === false) {
            Secret::matches($plain, null); // 時間差を作らない

            return null;
        }
        if (!Secret::matches($plain, (string)$row['session_hash'])) {
            return null;
        }
        if ($row['revoked_at'] !== null) {
            return null;
        }
        if (!$this->clock->isFuture((string)$row['expires_at'])) {
            return null;
        }
        if (!$this->clock->isFuture((string)$row['absolute_expires_at'])) {
            return null;
        }

        return $row;
    }

    /** 最終利用から30分へ延長する（絶対期限は延長しない） */
    public function touch(int $sessionId): void
    {
        $this->db->pdo()->prepare(
            'UPDATE intake_admin_sessions
                SET last_seen_at = :now, expires_at = :expires_at
              WHERE id = :id'
        )->execute([
            ':now'        => $this->clock->iso(),
            ':expires_at' => $this->clock->isoAfter(Config::ADMIN_SESSION_IDLE_TTL),
            ':id'         => $sessionId,
        ]);
    }

    public function logout(int $sessionId, ?string $ipHmac = null): void
    {
        $this->db->pdo()
            ->prepare('UPDATE intake_admin_sessions SET revoked_at = :now WHERE id = :id AND revoked_at IS NULL')
            ->execute([':now' => $this->clock->iso(), ':id' => $sessionId]);

        $this->audit->record(null, 'admin_logout', 'ok', $ipHmac);
    }

    /**
     * CSRF token を照合する（SSOT §10.8 の CSRF 規則2）。
     * ★hash_equals で定数時間比較する。値そのものは返さない。
     */
    public function csrfMatches(array $session, ?string $token): bool
    {
        if ($token === null || !Secret::isWellFormed($token)) {
            return false;
        }

        return hash_equals((string)$session['csrf_hash'], Secret::hash($token));
    }

    /**
     * 画面へ埋め込む CSRF token を作り直す。
     *
     * ★DBには hash しか無いため平文を復元できない。
     *   画面を出すたびに新しい値を発行し、hash を差し替える。
     */
    public function rotateCsrf(int $sessionId): string
    {
        $csrf = Secret::generate();
        $this->db->pdo()
            ->prepare('UPDATE intake_admin_sessions SET csrf_hash = :csrf WHERE id = :id')
            ->execute([':csrf' => Secret::hash($csrf), ':id' => $sessionId]);

        return $csrf;
    }

    /** 期限切れ・失効済みの行を片付ける（保持し続けない） */
    public function purgeExpired(): void
    {
        $this->db->pdo()
            ->prepare('DELETE FROM intake_admin_sessions WHERE absolute_expires_at <= :now')
            ->execute([':now' => $this->clock->iso()]);
    }

    public function activeCount(): int
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT COUNT(*) FROM intake_admin_sessions
              WHERE revoked_at IS NULL AND expires_at > :now AND absolute_expires_at > :now'
        );
        $stmt->execute([':now' => $this->clock->iso()]);

        return (int)$stmt->fetchColumn();
    }
}
