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
     * JSON 以外（HTML・書き出しファイル）を返すときの本文。
     * ★null のときだけ $body が JSON として使われる。
     */
    public ?string $rawBody = null;

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

    /**
     * 管理画面の HTML（SSOT §10.4 / §10.8）。
     * ★キャッシュさせない・検索結果へ出さない・外部から埋め込ませない。
     *
     * ★Referrer-Policy は `no-referrer` のまま（SSOT §10.4）。
     *   form 送信の `Origin` が `null` になるのは**ブラウザの画面遷移の仕様**であり、
     *   Referrer-Policy を緩めても直らない（4D で実測）。
     *   その判定は Guard::adminPostAllowed() が Fetch Metadata で行う。
     */
    public static function html(string $markup, int $status = 200): self
    {
        $res = new self($status, [], self::securityHeaders());
        $res->rawBody = $markup;
        $res->headers['Content-Type'] = 'text/html; charset=UTF-8';
        $res->headers['X-Robots-Tag'] = 'noindex, nofollow, noarchive';

        return $res;
    }

    /** 別のURLへ送る（POST 後は必ずこれで GET へ戻す） */
    public static function redirect(string $location, int $status = 303): self
    {
        $res = new self($status, [], self::securityHeaders());
        $res->rawBody = '';
        // ★外部サイトへ飛ばさない。相対パスだけを許す
        $res->headers['Location'] = str_starts_with($location, '/') && !str_starts_with($location, '//')
            ? $location
            : '/admin/';
        $res->headers['X-Robots-Tag'] = 'noindex, nofollow, noarchive';

        return $res;
    }

    /**
     * 書き出しファイル（SSOT §11.3）。
     * ★ブラウザに表示させず、必ずダウンロードさせる。
     */
    public static function download(string $content, string $fileName, string $contentType, array $extra = []): self
    {
        $res = new self(200, [], self::securityHeaders());
        $res->rawBody = $content;
        $res->headers['Content-Type']        = $contentType;
        $res->headers['Content-Disposition'] = 'attachment; filename="' . self::safeFileName($fileName) . '"';
        $res->headers['Content-Length']      = (string)strlen($content);
        $res->headers['X-Robots-Tag']        = 'noindex, nofollow, noarchive';
        foreach ($extra as $name => $value) {
            $res->headers[$name] = $value;
        }

        return $res;
    }

    /**
     * ファイル名を安全な形へ正規化する（SSOT §11.3-2）。
     * ★引用符・改行・パス区切り・非ASCII を通さない（ヘッダー分割と誤解釈を防ぐ）。
     */
    public static function safeFileName(string $name): string
    {
        $clean = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?? '';
        $clean = trim($clean, '._-');

        return $clean === '' ? 'export.json' : substr($clean, 0, 100);
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

    public function withCookie(string $name, string $value, int $maxAge, string $path = '/'): self
    {
        $this->cookies[] = [
            'name'       => $name,
            'value'      => $value,
            'attributes' => [
                'Path'     => $path,
                'Max-Age'  => (string)$maxAge,
                'Secure'   => true,
                'HttpOnly' => true,
                'SameSite' => 'Strict',
            ],
        ];

        return $this;
    }

    public function withClearedCookie(string $name, string $path = '/'): self
    {
        return $this->withCookie($name, '', 0, $path);
    }

    public function json(): string
    {
        return json_encode($this->body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * SSOT §10.4。
     *
     * ★CSP は**静的側（public/.htaccess）と同一の文字列**にそろえる（4F / P3-2）。
     *   API と画面で守りが違うと、どちらが本当の方針なのか読めなくなる。
     *   - `object-src 'none'` … プラグイン埋め込みを作らせない
     *   - `font-src 'self'`   … 外部フォントを取りに行かせない（既定は default-src だが明示する）
     *   - `img-src 'self'`    … **data: を許さない**。この画面群は画像もアイコンも使わない
     *   `unsafe-inline` / `unsafe-eval` / ワイルドカードは入れない。
     */
    public const CSP = "default-src 'self'; script-src 'self'; style-src 'self'; "
        . "img-src 'self'; font-src 'self'; connect-src 'self'; form-action 'self'; "
        . "frame-ancestors 'none'; base-uri 'none'; object-src 'none'";

    /** @return array<string,string> SSOT §10.4 */
    public static function securityHeaders(): array
    {
        return [
            'Content-Type'              => 'application/json; charset=UTF-8',
            'Content-Security-Policy'   => self::CSP,
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
                'path'     => (string)$cookie['attributes']['Path'],
                'secure'   => true,
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
        }
        echo $this->rawBody ?? $this->json();
    }
}
