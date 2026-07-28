<?php
/**
 * CSRFトークンの発行。
 * 静的HTMLではページ生成時にトークンを埋め込めないため、
 * 画面側のJavaScriptがこのエンドポイントから受け取って送信時に添える。
 * サーバー側に状態は持たない（HMAC署名＋有効期限で検証する）。
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';
slw_harden_php();
require_once __DIR__ . '/lib/security.php';

$config = slw_config();

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && in_array($origin, $config['allowed_origins'], true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    echo json_encode(['result' => 'rejected'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 発行元も許可オリジンに限る
if (!slw_origin_allowed($config)) {
    http_response_code(403);
    echo json_encode(['result' => 'rejected'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($config['csrf_secret'])) {
    error_log('[csrf-token] configuration incomplete');
    http_response_code(500);
    echo json_encode(['result' => 'failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['token' => slw_issue_csrf_token($config)], JSON_UNESCAPED_UNICODE);
