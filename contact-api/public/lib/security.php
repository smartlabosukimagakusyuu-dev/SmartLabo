<?php
/**
 * 送信を受け付けてよいかの判定。
 * Origin確認 / CSRFトークン / honeypot / 時間差 / 送信間隔制限。
 *
 * 生のIPアドレスは保存しない。レート制限にはHMACでハッシュ化した値だけを使う。
 */

declare(strict_types=1);

/** 接続元IPを取得する（保存はしない。ハッシュ化のみに使う） */
function slw_client_ip(): string
{
    // XServerはリバースプロキシ構成のため X-Forwarded-For が付く場合がある。
    // 先頭の1つだけを見る（後続は偽装され得る）。
    $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($xff !== '') {
        $first = trim(explode(',', $xff)[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) {
            return $first;
        }
    }
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
}

/** IPを鍵付きハッシュへ変換する。復元できないため保存しても個人特定に使えない */
function slw_ip_key(array $c): string
{
    return substr(hash_hmac('sha256', slw_client_ip(), (string)$c['ip_hash_secret']), 0, 32);
}

/** リクエストのオリジンが許可一覧に含まれるか */
function slw_origin_allowed(array $c): bool
{
    $allowed = $c['allowed_origins'];
    $origin  = $_SERVER['HTTP_ORIGIN'] ?? '';

    if ($origin !== '') {
        return in_array($origin, $allowed, true);
    }

    // Origin が付かない場合（同一オリジンの通常フォーム送信など）は Referer を見る
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if ($referer === '') {
        return false;   // どちらも無ければ受け付けない
    }
    $parts = parse_url($referer);
    if (!isset($parts['scheme'], $parts['host'])) {
        return false;
    }
    $refOrigin = $parts['scheme'] . '://' . $parts['host']
        . (isset($parts['port']) ? ':' . $parts['port'] : '');
    return in_array($refOrigin, $allowed, true);
}

/* ============================== CSRFトークン ============================== */
/*
 * 静的サイト（GitHub Pages / XServerの静的HTML）ではページ生成時にトークンを
 * 埋め込めないため、csrf-token.php が発行したものをJavaScriptが受け取り、
 * 送信時に添える方式にしている。
 * 形式: <発行時刻>.<ランダム>.<HMAC>  … サーバー側に状態を持たない。
 */

function slw_issue_csrf_token(array $c): string
{
    $issuedAt = time();
    $nonce    = bin2hex(random_bytes(16));
    $payload  = $issuedAt . '.' . $nonce;
    $sig      = hash_hmac('sha256', $payload, (string)$c['csrf_secret']);
    return $payload . '.' . $sig;
}

/** トークンが正しく、かつ有効期限内かを確認する */
function slw_csrf_token_valid(string $token, array $c, int $ttlSeconds = 7200): bool
{
    if ($token === '' || strlen($token) > 200) {
        return false;
    }
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return false;
    }
    [$issuedAt, $nonce, $sig] = $parts;
    if (!ctype_digit($issuedAt) || !ctype_xdigit($nonce)) {
        return false;
    }
    $expected = hash_hmac('sha256', $issuedAt . '.' . $nonce, (string)$c['csrf_secret']);
    if (!hash_equals($expected, $sig)) {
        return false;
    }
    $age = time() - (int)$issuedAt;
    // 発行より前の時刻や、期限を過ぎたものは無効
    return $age >= 0 && $age <= $ttlSeconds;
}

/* ============================== honeypot・時間差 ============================== */

/**
 * 人間なら埋めない項目に値が入っていないか。
 * 画面には表示されないため、入力があれば自動送信と判断する。
 */
function slw_honeypot_triggered(array $body): bool
{
    return isset($body['website']) && trim((string)$body['website']) !== '';
}

/**
 * フォーム表示から送信までが短すぎないか。
 * form_ts はフォーム表示時刻（ミリ秒）。JavaScriptが入れる。
 * 値が無い場合（JS無効）はこの判定を行わない。
 */
function slw_too_fast(array $body, array $c): bool
{
    $ts = $body['form_ts'] ?? '';
    if ($ts === '' || !ctype_digit((string)$ts)) {
        return false;
    }
    $elapsed = (time() * 1000 - (int)$ts) / 1000;
    if ($elapsed < 0) {
        return true;    // 未来の時刻は不正
    }
    return $elapsed < (int)$c['min_seconds_before_submit'];
}

/* ============================== 送信間隔制限 ============================== */
/*
 * 保存場所 : private/ratelimit/<ハッシュ化したIP>
 * 保存内容 : 送信時刻（UNIX秒）の一覧のみ。問い合わせ内容は保存しない。
 * 保存期間 : rate_limit_window（既定600秒）を過ぎた時刻は読み込み時に捨てる。
 *            さらに1時間以上更新の無いファイルは、その都度まとめて削除する。
 * 権限     : ディレクトリ0700・ファイル0600。private/はWebから全拒否。
 */

function slw_ratelimit_dir(): string
{
    $base = dirname(__DIR__, 2) . '/private/ratelimit';
    if (!is_dir($base)) {
        @mkdir($base, 0700, true);
    }
    return $base;
}

/** 古いファイルを掃除する（1時間以上更新が無いもの） */
function slw_ratelimit_cleanup(string $dir): void
{
    $now = time();
    foreach (glob($dir . '/*') ?: [] as $file) {
        if (is_file($file) && ($now - (int)@filemtime($file)) > 3600) {
            @unlink($file);
        }
    }
}

/**
 * 上限を超えていれば true。超えていなければ今回の送信を記録する。
 */
function slw_rate_limited(array $c): bool
{
    $dir = slw_ratelimit_dir();
    if (!is_dir($dir) || !is_writable($dir)) {
        return false;   // 記録できない環境では制限をかけない（送信は他の対策で守る）
    }
    slw_ratelimit_cleanup($dir);

    $file   = $dir . '/' . slw_ip_key($c);
    $now    = time();
    $window = (int)$c['rate_limit_window'];
    $max    = (int)$c['rate_limit_max'];

    $handle = @fopen($file, 'c+');
    if ($handle === false) {
        return false;
    }
    try {
        if (!flock($handle, LOCK_EX)) {
            return false;
        }
        $raw   = stream_get_contents($handle) ?: '';
        $times = array_filter(array_map('intval', explode(',', $raw)));
        // 期間内のものだけ残す（期間外は保存しない＝自動削除）
        $times = array_values(array_filter($times, fn($t) => ($now - $t) < $window));

        if (count($times) >= $max) {
            return true;
        }
        $times[] = $now;
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, implode(',', $times));
        fflush($handle);
        @chmod($file, 0600);
        return false;
    } finally {
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }
}
