<?php
// Smart Labo Works — 問い合わせフォーム ログ(個人情報を含めない)
// private/配下(Webから直接アクセス不可)のファイルへJSON Lines形式で追記する。

function slw_hash_ip(string $ip): string
{
    return substr(hash('sha256', $ip), 0, 16);
}

function slw_log_write(array $entry): void
{
    $path = slw_config('log_path');
    $line = json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
}

function slw_log_success(string $ip, string $receiptId, string $type): void
{
    slw_log_write([
        'level' => 'info',
        'event' => 'contact_submitted',
        'ipHash' => slw_hash_ip($ip),
        'receiptId' => $receiptId,
        'type' => $type,
        'at' => date('c'),
    ]);
}

function slw_log_failure(string $ip, string $reason, ?string $detail = null): void
{
    slw_log_write([
        'level' => 'warn',
        'event' => 'contact_rejected',
        'ipHash' => slw_hash_ip($ip),
        'reason' => $reason,
        'detail' => $detail,
        'at' => date('c'),
    ]);
}

function slw_log_error(string $ip, string $message): void
{
    slw_log_write([
        'level' => 'error',
        'event' => 'contact_error',
        'ipHash' => slw_hash_ip($ip),
        'message' => $message,
        'at' => date('c'),
    ]);
}
