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
        if (!$this->sameOriginFetch($req) && !$this->originAllowed($req)) {
            return Response::error('forbidden', 403);
        }

        return null;
    }

    /**
     * 同一オリジンからの取得かどうかを、ブラウザが付ける Fetch Metadata で確かめる。
     *
     * ★同一オリジンの GET には **Origin が付かない**（ブラウザの仕様）。
     *   さらに本画面は `Referrer-Policy: no-referrer`（SSOT §10.4）なので Referer も付かない。
     *   そのため GET だけは、この経路でも受け付ける必要がある。
     *
     * ★`Sec-Fetch-*` は **Forbidden request header** であり、
     *   **ブラウザ内の JavaScript からは設定できない**。他サイトからの要求では
     *   `cross-site` になるため、**ブラウザ経由の CSRF に対する補助信号**として使える。
     *   ただし **curl 等の非ブラウザからは任意に構成できる**ので、単独では守りにならない。
     *   `none`（URL直打ち・ブックマーク）は受け付けない。
     *   ヘッダー自体が無い場合は false を返し、従来の Origin / Referer 検査へ委ねる。
     */
    public function sameOriginFetch(Request $req): bool
    {
        $site = $req->header('Sec-Fetch-Site');
        if ($site === null || strtolower(trim($site)) !== 'same-origin') {
            return false;
        }

        // 画面遷移ではなく、スクリプトからの取得であること
        $mode = $req->header('Sec-Fetch-Mode');
        if ($mode !== null && strtolower(trim($mode)) === 'navigate') {
            return false;
        }

        return true;
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

    /**
     * 管理画面の form 送信（POST）を受け付けてよいか。
     *
     * ★JavaScript を使わない**画面遷移としての form 送信**では、ブラウザは
     *   `Origin: null` を送る（Chrome で実測。Referrer-Policy を変えても直らない）。
     *   そのため Origin だけでは同一オリジンかどうかを判定できない。
     *
     * 判定:
     *   1. Origin が付いていて `null` でなければ → **許可一覧と厳格に照合**する
     *   2. Origin が無い／`null` のときだけ → `Sec-Fetch-Site: same-origin` を見る
     *
     * `Sec-Fetch-*` は **Forbidden request header** であり、
     * **ブラウザ内の JavaScript からは設定できない**。他サイトからの送信では `cross-site` になる。
     * ただし **curl 等の非ブラウザからは任意に構成できる**ため、これ単独では守りにならない。
     * さらに管理画面は **CSRF token（server 側 session に hash で保持）を必須**にしており、
     * この判定は多層防御の1枚である。
     *
     * ★これは**管理画面の form 専用**である。
     *   店舗向けの JSON POST（checkJsonPost）は従来どおり Origin の厳格検査だけで守る。
     *   そちらは fetch() からの呼び出しであり、正しい Origin が必ず付く。
     */
    public function adminPostAllowed(Request $req): bool
    {
        $origin = trim((string)$req->header('Origin'));

        if ($origin !== '' && strtolower($origin) !== 'null') {
            return in_array($origin, $this->config->allowedOrigins, true);
        }

        // ★form 送信は Sec-Fetch-Mode: navigate になる。
        //   sameOriginFetch() は navigate を弾くので、ここでは使えない。
        $site = $req->header('Sec-Fetch-Site');

        return $site !== null && strtolower(trim($site)) === 'same-origin';
    }
}
