<?php
/**
 * 問い合わせフォームの受け口
 * ============================================================================
 * 処理順序
 *   1. POST以外を拒否
 *   2. 本文サイズの上限
 *   3. Content-Type の確認
 *   4. Origin / Referer が許可一覧に含まれるか
 *   5. honeypot・時間差（自動送信の疑いは静かに拒否）
 *   6. CSRFトークン
 *   7. 送信間隔制限
 *   8. 入力検証
 *   9. メール送信
 *
 * 応答は2種類。JavaScript から fetch した場合は JSON、
 * JavaScript 無効の通常フォーム送信の場合は簡単なHTMLを返す。
 *
 * 問い合わせ内容はどこにも保存しない。ログにも出さない。
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';
slw_harden_php();
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/validate.php';
require_once __DIR__ . '/lib/mailer.php';

$config = slw_config();

/* ------------------------------------------------------------------ *
 * 応答の形を決める。fetch からは JSON、通常フォーム送信には HTML。
 * ------------------------------------------------------------------ */
$wantsJson = (
    str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
    || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch'
);

/** 許可オリジンに対してのみCORSヘッダーを返す（ワイルドカードは使わない） */
function slw_cors_headers(array $c): void
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

/**
 * 応答して終了する。
 * 受信先メールアドレスや内部情報は一切含めない。
 */
function slw_respond(int $status, string $result, array $fieldErrors, bool $wantsJson): void
{
    http_response_code($status);
    header('Cache-Control: no-store');
    header('Referrer-Policy: no-referrer');
    header('X-Content-Type-Options: nosniff');

    $messages = [
        'ok'        => 'お問い合わせを受け付けました。',
        'invalid'   => '入力内容をご確認ください。',
        'failed'    => '送信できませんでした。時間をおいて再度お試しください。',
        'rejected'  => '送信できませんでした。時間をおいて再度お試しください。',
        'too_many'  => '送信が続いています。しばらく時間をおいて再度お試しください。',
    ];
    $message = $messages[$result] ?? $messages['failed'];

    if ($wantsJson) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(
            ['result' => $result, 'message' => $message, 'errors' => $fieldErrors],
            JSON_UNESCAPED_UNICODE
        );
        exit;
    }

    // JavaScript 無効時の表示。入力内容は載せない（URLにも残さない）
    header('Content-Type: text/html; charset=UTF-8');
    $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $title = $result === 'ok' ? 'お問い合わせを受け付けました' : '送信できませんでした';
    echo '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
       . '<meta name="robots" content="noindex">'
       . '<title>' . $h($title) . ' — Smart Labo Works</title>'
       . '<style>'
       . 'body{margin:0;padding:48px 24px;font-family:"Hiragino Sans","Noto Sans JP","Yu Gothic",'
       . 'system-ui,sans-serif;line-height:1.9;color:#1E293B;background:#fff}'
       . 'main{max-width:640px;margin:0 auto}'
       . 'h1{font-size:22px;margin:0 0 16px}'
       . 'p{margin:0 0 12px;color:#5C6B82}'
       . 'ul{margin:0 0 16px;padding-left:1.2em;color:#5C6B82}'
       . 'a{color:#2563EB}'
       . '</style></head><body><main>'
       . '<h1>' . $h($message) . '</h1>';
    if ($result === 'ok') {
        echo '<p>内容を確認のうえ、担当者よりご連絡いたします。</p>';
    } elseif ($fieldErrors !== []) {
        echo '<p>次の項目をご確認ください。</p><ul>';
        $labels = [
            'company' => '会社名', 'name' => 'お名前', 'email' => 'メールアドレス',
            'tel' => '電話番号', 'type' => 'お問い合わせ種別',
            'headcount' => '利用予定人数', 'message' => 'お問い合わせ内容',
            'privacy' => 'プライバシーポリシーへの同意',
        ];
        foreach ($fieldErrors as $field => $_reason) {
            echo '<li>' . $h($labels[$field] ?? $field) . '</li>';
        }
        echo '</ul>';
    }
    echo '<p><a href="/contact.html">お問い合わせページへ戻る</a></p>'
       . '</main></body></html>';
    exit;
}

/* ------------------------------------- 1. メソッド ------------------------------------- */

$method = $_SERVER['REQUEST_METHOD'] ?? '';

if ($method === 'OPTIONS') {
    slw_cors_headers($config);
    http_response_code(204);
    exit;
}

if ($method !== 'POST') {
    header('Allow: POST');
    slw_respond(405, 'rejected', [], $wantsJson);
}

slw_cors_headers($config);

/* --------------------------------- 2. 本文のサイズ --------------------------------- */

$declared = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($declared > (int)$config['max_body_bytes']) {
    slw_respond(413, 'rejected', [], $wantsJson);
}

/* --------------------------------- 3. Content-Type --------------------------------- */

$contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
$isJsonBody  = str_contains($contentType, 'application/json');
$isFormBody  = str_contains($contentType, 'application/x-www-form-urlencoded')
            || str_contains($contentType, 'multipart/form-data');

if (!$isJsonBody && !$isFormBody) {
    slw_respond(415, 'rejected', [], $wantsJson);
}

/* --------------------------------- 本文の取り出し --------------------------------- */

if ($isJsonBody) {
    $raw = file_get_contents('php://input') ?: '';
    if (strlen($raw) > (int)$config['max_body_bytes']) {
        slw_respond(413, 'rejected', [], $wantsJson);
    }
    $decoded = json_decode($raw, true);
    $body = is_array($decoded) ? $decoded : [];
} else {
    $body = $_POST;
}

/* --------------------- 設定が未完成なら、内容を明かさず失敗を返す --------------------- */

if (slw_config_problems($config) !== []) {
    // 何が足りないかは応答へ出さない（サーバーのエラーログにのみ残す）
    error_log('[contact] configuration incomplete');
    slw_respond(500, 'failed', [], $wantsJson);
}

/* --------------------------------- 4. オリジン --------------------------------- */

if (!slw_origin_allowed($config)) {
    slw_respond(403, 'rejected', [], $wantsJson);
}

/* --------------------------- 5. honeypot・時間差 --------------------------- */
/* 自動送信の疑いがある場合は、理由を伝えずに拒否する */

if (slw_honeypot_triggered($body) || slw_too_fast($body, $config)) {
    slw_respond(400, 'rejected', [], $wantsJson);
}

/* --------------------------------- 6. CSRF --------------------------------- */

$token = (string)($body['csrf_token'] ?? '');

if ($token !== '') {
    if (!slw_csrf_token_valid($token, $config)) {
        slw_respond(403, 'rejected', [], $wantsJson);
    }
} elseif (!empty($config['require_csrf_always'])) {
    // トークン必須の設定。JavaScript無効では送信できない
    slw_respond(403, 'rejected', [], $wantsJson);
}
// トークンが無く require_csrf_always が false の場合は、
// Origin/Referer一致・honeypot・時間差・送信間隔制限のみで受け付ける。

/* ------------------------------ 7. 送信間隔制限 ------------------------------ */

if (slw_rate_limited($config)) {
    slw_respond(429, 'too_many', [], $wantsJson);
}

/* --------------------------------- 8. 入力検証 --------------------------------- */

// 文字コードがUTF-8でない場合は、ここで分かる形で止める。
// そのまま進めると値が空として扱われ、「未入力」という分かりにくい
// エラーになってしまう。
if (!slw_body_is_utf8($body)) {
    slw_respond(422, 'invalid', [], $wantsJson);
}

$check = slw_validate($body);
if (!$check['valid']) {
    slw_respond(422, 'invalid', $check['errors'], $wantsJson);
}

/* --------------------------------- 9. 送信 --------------------------------- */

// 想定外の例外でも、内部情報を出さずに「送信できませんでした」を返す。
// ここで捕まえないと、PHPの致命的エラーが空の500として返り、
// 利用者には何も表示されないままになる。
try {
    $sent = slw_send_notification($check['data'], $config);
} catch (Throwable $e) {
    // 例外の内容も問い合わせ内容もレスポンスへ出さない
    error_log('[contact] mail send exception: ' . $e->getMessage());
    $sent = false;
}

if (!$sent) {
    // 失敗しても問い合わせ内容はログへ出さない
    error_log('[contact] mail send failed');
    slw_respond(502, 'failed', [], $wantsJson);
}

slw_respond(200, 'ok', [], $wantsJson);
