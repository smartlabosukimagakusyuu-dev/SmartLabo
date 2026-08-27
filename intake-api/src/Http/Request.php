<?php
/**
 * HP Intake API — リクエスト。
 * HTTP から切り離してテストできるよう、スーパーグローバルを直接参照しない。
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Http;

final class Request
{
    /** @var array<string,string> 小文字化したヘッダー名 => 値 */
    private array $headers;

    /**
     * @param array<string,string> $headers
     * @param array<string,string> $cookies
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        array $headers = [],
        public readonly string $body = '',
        public readonly array $cookies = [],
        public readonly bool $isHttps = true,
        public readonly string $clientIp = '203.0.113.10',
    ) {
        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[strtolower($name)] = $value;
        }
        $this->headers = $normalized;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function cookie(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    public function bodyBytes(): int
    {
        return strlen($this->body);
    }

    /** スーパーグローバルから組み立てる（public/index.php 専用） */
    public static function fromGlobals(): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strncmp($key, 'HTTP_', 5) === 0) {
                $headers[str_replace('_', '-', substr($key, 5))] = (string)$value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['CONTENT-TYPE'] = (string)$_SERVER['CONTENT_TYPE'];
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        $path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

        return new self(
            method: (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            path: $path === '' ? '/' : $path,
            headers: $headers,
            body: (string)file_get_contents('php://input'),
            cookies: array_map('strval', $_COOKIE),
            isHttps: $https,
            clientIp: self::clientIpFromGlobals(),
        );
    }

    /** 生IPは保存しない。HMAC化のためだけに使う（SSOT §10.2） */
    private static function clientIpFromGlobals(): string
    {
        $xff = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($xff !== '') {
            $first = trim(explode(',', $xff)[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }
        $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');

        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
    }
}
