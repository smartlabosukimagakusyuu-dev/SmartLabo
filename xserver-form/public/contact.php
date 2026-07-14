<?php
// POST /contact.php — 問い合わせフォーム送信処理
//
// 処理順序: Origin確認 → honeypot/送信速度トラップ(静かに拒否) → CSRF検証
// → レート制限 → 入力値検証 → 二重送信検知 → reCAPTCHA検証(無効時はスキップ)
// → 管理者通知メール(必須) → 自動返信メール(ベストエフォート) → 受付番号を返す

declare(strict_types=1);

require_once __DIR__ . '/lib/env.php';
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/rateLimiter.php';
require_once __DIR__ . '/lib/validate.php';
require_once __DIR__ . '/lib/recaptcha.php';
require_once __DIR__ . '/lib/mailer.php';
require_once __DIR__ . '/lib/receiptId.php';
require_once __DIR__ . '/lib/log.php';

$origin = $_SERVER['HTTP_ORIGIN'] ?? null;
slw_apply_cors_headers($origin);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

function slw_respond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

function slw_fake_success_response(): void
{
    // honeypot/送信速度トラップ検知時は、botへ判定基準を悟らせないため
    // 正常送信と見分けのつかないレスポンスを返す(実際にはメール送信しない)。
    slw_respond(200, [
        'ok' => true,
        'receiptId' => slw_generate_receipt_id(),
        'receivedAt' => date('c'),
    ]);
}

$ip = slw_client_ip();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    slw_respond(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

if (!slw_is_allowed_origin($origin)) {
    slw_log_failure($ip, 'origin_rejected', $origin);
    slw_respond(403, ['ok' => false, 'error' => 'origin_rejected']);
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    $body = [];
}

if (slw_is_honeypot_triggered($body) || slw_is_too_fast($body)) {
    slw_log_failure($ip, 'bot_suspected');
    slw_fake_success_response();
}

if (!slw_verify_csrf_token($body['csrfToken'] ?? null)) {
    slw_log_failure($ip, 'csrf_invalid');
    slw_respond(403, ['ok' => false, 'error' => 'csrf_invalid']);
}

$rateResult = slw_check_rate_limit($ip);
if (!$rateResult['allowed']) {
    slw_log_failure($ip, 'rate_limited');
    slw_respond(429, ['ok' => false, 'error' => 'rate_limited']);
}

$validation = slw_validate_contact_payload($body);
if (!$validation['valid']) {
    slw_log_failure($ip, 'validation_error', implode(',', $validation['errors']));
    slw_respond(400, ['ok' => false, 'error' => 'validation_error', 'fields' => $validation['errors']]);
}
$data = $validation['data'];

$contentHash = hash('sha256', implode('|', [$data['company'], $data['email'], $data['message']]));
$duplicateResult = slw_check_duplicate_submission($ip, $contentHash);
if (!$duplicateResult['allowed']) {
    slw_log_failure($ip, 'duplicate_submission');
    slw_respond(429, ['ok' => false, 'error' => 'duplicate_submission']);
}

$recaptchaResult = slw_verify_recaptcha($body['recaptchaToken'] ?? null, $ip);
if (!$recaptchaResult['success']) {
    slw_log_failure($ip, 'captcha_failed', $recaptchaResult['reason'] ?? null);
    slw_respond(403, ['ok' => false, 'error' => 'captcha_failed']);
}

$receiptId = slw_generate_receipt_id();

try {
    slw_send_admin_notification($data, $receiptId);
} catch (Throwable $e) {
    slw_log_error($ip, $e->getMessage());
    slw_respond(500, ['ok' => false, 'error' => 'server_error']);
}

try {
    slw_send_auto_reply($data, $receiptId);
} catch (Throwable $e) {
    // 自動返信の失敗は致命的ではない(管理者へは既に通知済み)ため、ログのみ記録する
    slw_log_error($ip, $e->getMessage());
}

slw_log_success($ip, $receiptId, $data['type']);
slw_respond(200, ['ok' => true, 'receiptId' => $receiptId, 'receivedAt' => date('c')]);
