<?php
/**
 * HP Intake API — ログ。
 *
 * SSOT §10.7「ログに出してはいけない情報」を**構造的に**守る。
 *
 *  - 出力してよいキーは ALLOWED のみ。それ以外は**書き出す前に捨てる**
 *    （呼び出し側が誤って回答本文や氏名を渡しても、ここで落ちる）
 *  - 値には必ず redact() を通す。base64url 43文字（token / session secret）と
 *    既知の秘密値パターンを [REDACTED] へ置換する
 *  - 通知メールと同じ方針：本文・PII・Drive URL を出さない
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Support;

final class Logger
{
    /** ここに無いキーは出力しない（allowlist） */
    public const ALLOWED = [
        'ts', 'level', 'event', 'case_number', 'result_code', 'ip_hmac',
        'http_status', 'schema_version', 'field_count', 'missing_count', 'bucket',
    ];

    public function __construct(private readonly ?string $path = null)
    {
    }

    /** @param array<string,mixed> $fields */
    public function info(string $event, array $fields = []): void
    {
        $this->write('info', $event, $fields);
    }

    /** @param array<string,mixed> $fields */
    public function warn(string $event, array $fields = []): void
    {
        $this->write('warn', $event, $fields);
    }

    /** @param array<string,mixed> $fields */
    public function error(string $event, array $fields = []): void
    {
        $this->write('error', $event, $fields);
    }

    /**
     * 実際に書き出される行を組み立てる（テストから検証できるよう public）。
     * @param array<string,mixed> $fields
     */
    public function format(string $level, string $event, array $fields): string
    {
        $out = [
            'ts'    => gmdate('c'),
            'level' => $level,
            'event' => self::redact($event),
        ];

        foreach ($fields as $key => $value) {
            if (!in_array($key, self::ALLOWED, true)) {
                continue; // ★allowlist 外は捨てる
            }
            if (is_int($value) || is_bool($value) || $value === null) {
                $out[$key] = $value;
                continue;
            }
            $out[$key] = self::redact((string)$value);
        }

        return json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** token / session secret / 既知の秘密値らしき文字列を落とす */
    public static function redact(string $text): string
    {
        // base64url 43文字（token / session secret）
        $text = preg_replace('/(?<![A-Za-z0-9_-])[A-Za-z0-9_-]{43}(?![A-Za-z0-9_-])/', '[REDACTED]', $text);
        // 既知の秘密値パターン
        $text = preg_replace('/\b(sk|pk|rk)_(live|test)_[A-Za-z0-9]{6,}/', '[REDACTED]', $text);
        $text = preg_replace('/\bwhsec_[A-Za-z0-9]{6,}/', '[REDACTED]', $text);
        $text = preg_replace('/\bBearer\s+[A-Za-z0-9._\-]+/', '[REDACTED]', $text);
        // SHA-256 の16進（hash をそのまま出さない）
        $text = preg_replace('/\b[0-9a-f]{64}\b/', '[REDACTED]', $text);

        return $text;
    }

    /** @param array<string,mixed> $fields */
    private function write(string $level, string $event, array $fields): void
    {
        $line = $this->format($level, $event, $fields);
        if ($this->path === null) {
            return;
        }
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        file_put_contents($this->path, $line . "\n", FILE_APPEND | LOCK_EX);
        @chmod($this->path, 0600);
    }
}
