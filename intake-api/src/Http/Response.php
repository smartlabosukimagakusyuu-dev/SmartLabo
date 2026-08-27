<?php
/**
 * HP Intake API — 応答。
 *
 * SSOT §4.6 / §10.4 / §10.6:
 *  - token / session の失敗は**すべて同一文言・404**（存在有無を漏らさない）
 *  - 例外メッセージ・スタックトレース・SQL・パス・設定値を出さない
 *  - CORS ヘッダーを出さない（他オリジンから使えない構成にする）
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Http;

final class Response
{
    /** token / session / 案件状態の失敗はすべてこの1種類（SSOT §4.6） */
    public const UNAVAILABLE_MESSAGE = 'このURLは使用できません。お手数ですが、担当者までご連絡ください。';

    /** 固定文言。内部情報を含めない */
    private const MESSAGES = [
        'bad_request'        => '入力内容を確認できませんでした。',
        'forbidden'          => 'この操作は許可されていません。',
        'unavailable'        => self::UNAVAILABLE_MESSAGE,
        'method_not_allowed' => 'この操作は許可されていません。',
        'conflict'           => '他の端末で更新されています。最新の内容を読み込んでください。',
        'not_editable'       => '現在この内容は編集できません。',
        // ★提出済みであること以外を伝えない（既存の submission_id・提出日時・提出内容を出さない）
        'already_submitted'  => 'この内容はすでに提出済みです。',
        'payload_too_large'  => '送信内容が大きすぎます。',
        'rate_limited'       => '短時間に操作が集中しました。しばらくおいてからお試しください。',
        'server_error'       => 'ただいま処理できません。時間をおいてお試しください。',
    ];

    /** @var list<array{name:string,value:string,attributes:array<string,string|bool>}> */
    public array $cookies = [];

    /**
     * @param array<string,mixed> $body
     * @param array<string,string> $headers
     */
    private function __construct(
        public readonly int $status,
        public readonly array $body,
        public array $headers = [],
    ) {
    }

    /** @param array<string,mixed> $payload */
    public static function ok(array $payload = [], int $status = 200): self
    {
        return new self($status, ['ok' => true] + $payload, self::securityHeaders());
    }

    public static function error(string $code, int $status): self
    {
        return new self($status, [
            'ok'      => false,
            'error'   => $code,
            'message' => self::MESSAGES[$code] ?? self::MESSAGES['server_error'],
        ], self::securityHeaders());
    }

    /** token / session / 案件状態の失敗（SSOT §4.6） */
    public static function unavailable(): self
    {
        return self::error('unavailable', 404);
    }

    public function withCookie(string $name, string $value, int $maxAge): self
    {
        $this->cookies[] = [
            'name'       => $name,
            'value'      => $value,
            'attributes' => [
                'Path'     => '/',
                'Max-Age'  => (string)$maxAge,
                'Secure'   => true,
                'HttpOnly' => true,
                'SameSite' => 'Strict',
            ],
        ];

        return $this;
    }

    public function withClearedCookie(string $name): self
    {
        return $this->withCookie($name, '', 0);
    }

    public function json(): string
    {
        return json_encode($this->body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @return array<string,string> SSOT §10.4 */
    public static function securityHeaders(): array
    {
        return [
            'Content-Type'              => 'application/json; charset=UTF-8',
            'Content-Security-Policy'   => "default-src 'self'; script-src 'self'; style-src 'self'; "
                . "img-src 'self' data:; connect-src 'self'; form-action 'self'; "
                . "frame-ancestors 'none'; base-uri 'none'",
            'X-Content-Type-Options'    => 'nosniff',
            'X-Frame-Options'           => 'DENY',
            'Referrer-Policy'           => 'no-referrer',
            'Cache-Control'             => 'no-store, no-cache, must-revalidate',
            'Pragma'                    => 'no-cache',
            'Strict-Transport-Security' => 'max-age=31536000',
        ];
    }

    /** public/index.php 専用の送出 */
    public function emit(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value, true);
        }
        foreach ($this->cookies as $cookie) {
            setcookie($cookie['name'], $cookie['value'], [
                'expires'  => $cookie['attributes']['Max-Age'] === '0' ? 1 : time() + (int)$cookie['attributes']['Max-Age'],
                'path'     => '/',
                'secure'   => true,
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
        }
        echo $this->json();
    }
}
