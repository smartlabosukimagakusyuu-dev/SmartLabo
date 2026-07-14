<?php
// Smart Labo Works — 問い合わせフォーム レート制限(SQLite3、private/配下に永続化)
// XServer等の共有サーバーの通常のPHPプロセスモデルでは、Vercel版で課題だった
// 「サーバーレスのコールドスタートでインメモリ状態がリセットされる」問題が
// 発生しないため、SQLiteでの永続化のみで十分な防御になる。

const SLW_RATE_WINDOW_SEC = 60 * 60; // 1時間
const SLW_RATE_MAX_REQUESTS = 5;
const SLW_DUPLICATE_WINDOW_SEC = 10 * 60; // 10分(二重送信防止)

function slw_rate_limiter_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $path = slw_config('rate_limit_db_path');
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE IF NOT EXISTS requests (ip TEXT NOT NULL, created_at INTEGER NOT NULL)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS submissions (ip_hash TEXT NOT NULL, content_hash TEXT NOT NULL, created_at INTEGER NOT NULL, PRIMARY KEY (ip_hash, content_hash))');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_requests_ip ON requests(ip, created_at)');

    return $pdo;
}

function slw_check_rate_limit(string $ip): array
{
    $pdo = slw_rate_limiter_db();
    $cutoff = time() - SLW_RATE_WINDOW_SEC;

    $pdo->prepare('DELETE FROM requests WHERE created_at < :cutoff')->execute(['cutoff' => $cutoff]);

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM requests WHERE ip = :ip AND created_at >= :cutoff');
    $stmt->execute(['ip' => $ip, 'cutoff' => $cutoff]);
    $count = (int) $stmt->fetchColumn();

    if ($count >= SLW_RATE_MAX_REQUESTS) {
        return ['allowed' => false, 'reason' => 'rate_limited'];
    }

    $pdo->prepare('INSERT INTO requests (ip, created_at) VALUES (:ip, :now)')
        ->execute(['ip' => $ip, 'now' => time()]);

    return ['allowed' => true];
}

function slw_check_duplicate_submission(string $ip, string $contentHash): array
{
    $pdo = slw_rate_limiter_db();
    $ipHash = hash('sha256', $ip);
    $cutoff = time() - SLW_DUPLICATE_WINDOW_SEC;

    $pdo->prepare('DELETE FROM submissions WHERE created_at < :cutoff')->execute(['cutoff' => $cutoff]);

    $stmt = $pdo->prepare('SELECT created_at FROM submissions WHERE ip_hash = :ip_hash AND content_hash = :content_hash');
    $stmt->execute(['ip_hash' => $ipHash, 'content_hash' => $contentHash]);
    $existing = $stmt->fetchColumn();

    if ($existing !== false && (time() - (int) $existing) < SLW_DUPLICATE_WINDOW_SEC) {
        return ['allowed' => false, 'reason' => 'duplicate_submission'];
    }

    $pdo->prepare('INSERT OR REPLACE INTO submissions (ip_hash, content_hash, created_at) VALUES (:ip_hash, :content_hash, :now)')
        ->execute(['ip_hash' => $ipHash, 'content_hash' => $contentHash, 'now' => time()]);

    return ['allowed' => true];
}
