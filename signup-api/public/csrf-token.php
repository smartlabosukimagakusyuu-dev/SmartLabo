<?php
/**
 * CSRFトークンの発行  GET /api/signup-csrf-token
 *
 * 静的サイトではページ生成時にトークンを埋め込めないため、
 * 画面のJavaScriptがここから取得して送信時に添える。
 * サーバー側に状態を持たない（HMAC署名付きの文字列を返すだけ）。
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';
sls_harden_php();
require_once __DIR__ . '/lib/response.php';
require_once __DIR__ . '/lib/security.php';

$config = sls_config();

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && in_array($origin, $config['allowed_origins'], true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Max-Age: 600');
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';

if ($method === 'OPTIONS') {
    sls_send_headers();
    http_response_code(204);
    exit;
}

if ($method !== 'GET') {
    header('Allow: GET');
    sls_respond(405, 'rejected');
}

// 鍵が未設定なら発行しない（空の鍵で署名すると検証が意味を失うため）
if (empty($config['csrf_secret'])) {
    error_log('[signup] csrf_secret is not configured');
    sls_respond(500, 'failed');
}

if (!sls_origin_allowed($config)) {
    sls_respond(403, 'rejected');
}

sls_send_headers();
header('Content-Type: application/json; charset=UTF-8');
echo json_encode(['token' => sls_issue_csrf_token($config)], JSON_UNESCAPED_SLASHES);
