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
     * @param array<string,string> $query URL の query（★PII を入れない。SSOT §10.4）
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        array $headers = [],
        public readonly string $body = '',
        public readonly array $cookies = [],
        public readonly bool $isHttps = true,
        public readonly string $clientIp = '203.0.113.10',
        public readonly array $query = [],
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

    /**
     * `application/x-www-form-urlencoded` の body を読む（管理画面の form 用）。
     *
     * ★JSON の endpoint では使わない。form を受けるのは /admin/ 配下だけである。
     * ★Content-Type が form でなければ**何も返さない**（誤って JSON を form として読まない）。
     * @return array<string,string>
     */
    public function formFields(): array
    {
        $ctype = strtolower(trim(explode(';', (string)$this->header('Content-Type'))[0]));
        if ($ctype !== 'application/x-www-form-urlencoded') {
            return [];
        }

        $out = [];
        parse_str($this->body, $out);

        $flat = [];
        foreach ($out as $key => $value) {
            if (is_string($value)) {
                $flat[(string)$key] = $value;
            }
        }

        return $flat;
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

        $query = [];
        foreach ($_GET as $key => $value) {
            if (is_string($value)) {
                $query[(string)$key] = $value;
            }
        }

        return new self(
            method: (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            path: $path === '' ? '/' : $path,
            headers: $headers,
            body: (string)file_get_contents('php://input'),
            cookies: array_map('strval', $_COOKIE),
            isHttps: $https,
            clientIp: self::clientIpFromGlobals(),
            query: $query,
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
