<?php
/**
 * HP Intake API — リクエストの入口検査（SSOT §4.5 / §10.2 / §10.4）。
 *
 * 判定順序は SSOT §4.5-A に一致させる:
 *   1 POST確認 → 2 HTTPS確認 → 3 Content-Type確認 → 4 Origin厳格検査
 *   → 5 body上限1MB → 6 JSON構文確認
 *
 * ★CORS ヘッダーを出さない。許可オリジン以外は**使えない**構成にする。
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Http;

use SmartLabo\Intake\Config;

final class Guard
{
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * @return array{0:?Response,1:array<string,mixed>} 失敗なら [Response, []]、成功なら [null, decodedJson]
     */
    public function checkJsonPost(Request $req): array
    {
        // 1. POST 確認
        if ($req->method !== 'POST') {
            return [Response::error('method_not_allowed', 405), []];
        }
        // 2. HTTPS 確認
        if ($this->config->requireHttps && !$req->isHttps) {
            return [Response::error('forbidden', 403), []];
        }
        // 3. Content-Type 確認
        $ctype = strtolower(trim(explode(';', (string)$req->header('Content-Type'))[0]));
        if ($ctype !== 'application/json') {
            return [Response::error('bad_request', 400), []];
        }
        // 4. Origin 厳格検査
        if (!$this->originAllowed($req)) {
            return [Response::error('forbidden', 403), []];
        }
        // 5. body 上限
        if ($req->bodyBytes() > Config::MAX_BODY_BYTES) {
            return [Response::error('payload_too_large', 413), []];
        }
        // 6. JSON 構文
        $decoded = json_decode($req->body, true);
        if (!is_array($decoded)) {
            return [Response::error('bad_request', 400), []];
        }

        return [null, $decoded];
    }

    /** GET 系（認証済み案件取得）の入口検査 */
    public function checkGet(Request $req): ?Response
    {
        if ($req->method !== 'GET') {
            return Response::error('method_not_allowed', 405);
        }
        if ($this->config->requireHttps && !$req->isHttps) {
            return Response::error('forbidden', 403);
        }
        if (!$this->originAllowed($req)) {
            return Response::error('forbidden', 403);
        }

        return null;
    }

    /**
     * Origin / Referer の厳格検査。
     * ★Origin が無い要求は許可しない（fail closed）。ブラウザ以外からの利用も想定しない。
     */
    public function originAllowed(Request $req): bool
    {
        $origin = (string)$req->header('Origin');
        if ($origin !== '') {
            return in_array($origin, $this->config->allowedOrigins, true);
        }

        $referer = (string)$req->header('Referer');
        if ($referer === '') {
            return false;
        }
        $scheme = parse_url($referer, PHP_URL_SCHEME);
        $host   = parse_url($referer, PHP_URL_HOST);
        if (!is_string($scheme) || !is_string($host)) {
            return false;
        }

        return in_array($scheme . '://' . $host, $this->config->allowedOrigins, true);
    }
}
