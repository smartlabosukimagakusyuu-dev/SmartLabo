<?php
/**
 * セルフ申し込みの受け口  POST /api/signup
 * ============================================================================
 * SALES-1の範囲
 *   入力を受け取り、**サーバー側で検証し、結果を返すところまで**。
 *
 *   ★このエンドポイントは何も保存しない。
 *     会社も、管理者も、パスワードも、DBにもファイルにも書き込まない。
 *     メールも送らない。Stripeも呼ばない。アカウントも作らない。
 *     これらはすべて SALES-2 以降で実装する。
 *
 * 処理順序（contact-api/public/contact.php と同じ考え方）
 *   1. POST以外を拒否
 *   2. 本文サイズの上限
 *   3. Content-Type の確認
 *   4. 設定の充足確認
 *   5. Origin / Referer が許可一覧に含まれるか
 *   6. honeypot・時間差（自動送信の疑いは理由を伝えず拒否）
 *   7. CSRFトークン
 *   8. 送信間隔制限
 *   9. 文字コード確認 → 入力検証
 *  10. 料金の再計算（画面から送られた金額は使わない）→ 200 OK
 *
 * 応答は常にJSON（lib/response.php の共通形式）。
 * 申し込み内容はログにも出さない。
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';
sls_harden_php();
require_once __DIR__ . '/lib/response.php';
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/validate.php';
require_once __DIR__ . '/lib/pricing.php';

$config = sls_config();

/** 許可オリジンに対してのみCORSヘッダーを返す（ワイルドカードは使わない） */
function sls_cors_headers(array $c): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && in_array($origin, $c['allowed_origins'], true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Max-Age: 600');
    }
}

/* ------------------------------------- 1. メソッド ------------------------------------- */

$method = $_SERVER['REQUEST_METHOD'] ?? '';

if ($method === 'OPTIONS') {
    sls_cors_headers($config);
    sls_send_headers();
    http_response_code(204);
    exit;
}

if ($method !== 'POST') {
    header('Allow: POST');
    sls_respond(405, 'rejected');
}

sls_cors_headers($config);

/* --------------------------------- 2. 本文のサイズ --------------------------------- */

$declared = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($declared > (int)$config['max_body_bytes']) {
    sls_respond(413, 'rejected');
}

/* --------------------------------- 3. Content-Type --------------------------------- */

$contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
$isJsonBody  = str_contains($contentType, 'application/json');
$isFormBody  = str_contains($contentType, 'application/x-www-form-urlencoded')
            || str_contains($contentType, 'multipart/form-data');

if (!$isJsonBody && !$isFormBody) {
    sls_respond(415, 'rejected');
}

if ($isJsonBody) {
    $raw = file_get_contents('php://input') ?: '';
    if (strlen($raw) > (int)$config['max_body_bytes']) {
        sls_respond(413, 'rejected');
    }
    $decoded = json_decode($raw, true);
    $body = is_array($decoded) ? $decoded : [];
} else {
    $body = $_POST;
}

/* --------------------- 4. 設定が未完成なら、内容を明かさず失敗を返す --------------------- */

if (sls_config_problems($config) !== []) {
    // 何が足りないかは応答へ出さない（サーバーのエラーログにのみ残す）
    error_log('[signup] configuration incomplete rid=' . sls_request_id());
    sls_respond(500, 'failed');
}

/* --------------------------------- 5. オリジン --------------------------------- */

if (!sls_origin_allowed($config)) {
    sls_respond(403, 'rejected');
}

/* --------------------------- 6. honeypot・時間差 --------------------------- */

if (sls_honeypot_triggered($body) || sls_too_fast($body, $config)) {
    sls_respond(400, 'rejected');
}

/* --------------------------------- 7. CSRF --------------------------------- */

$token = (string)($body['csrf_token'] ?? '');

if ($token !== '') {
    if (!sls_csrf_token_valid($token, $config)) {
        sls_respond(403, 'rejected');
    }
} elseif (!empty($config['require_csrf_always'])) {
    sls_respond(403, 'rejected');
}

/* ------------------------------ 8. 送信間隔制限 ------------------------------ */

if (sls_rate_limited($config)) {
    sls_respond(429, 'too_many');
}

/* --------------------------------- 9. 入力検証 --------------------------------- */

// UTF-8でない入力は、ここで分かる形で止める。
// そのまま進めると値が空として扱われ「未入力」という分かりにくいエラーになる。
if (!sls_body_is_utf8($body)) {
    sls_respond(422, 'invalid');
}

$check = sls_validate($body);
if (!$check['valid']) {
    sls_respond(422, 'invalid', $check['errors']);
}

/* ------------------------- 10. 料金の再計算 → 応答 ------------------------- */

// 画面から送られてきた金額は一切使わず、サーバー側の正規値から計算し直す。
$quote = sls_quote((int)$check['data']['additional_accounts']);

// ここで保存・メール送信・Stripe呼び出しを行わないことが SALES-1 の仕様。
// SALES-2でこの位置に「Stripe Checkout Session の作成」が入る。
sls_respond(200, 'ok', [], [
    'stage'     => 'validated',   // 検証まで完了した、という意味
    'persisted' => false,         // 保存していないことを応答でも明示する
    'quote'     => $quote,
    // 次に進むべき先。SALES-2でCheckoutのURLがここへ入る想定
    'next'      => [
        'step'      => 'payment',
        'available' => false,
        'note'      => 'お支払いのお手続きは現在準備中です。',
    ],
]);
