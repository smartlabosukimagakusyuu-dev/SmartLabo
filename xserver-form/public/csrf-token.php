<?php
// GET /csrf-token.php — 問い合わせフォーム表示時にCSRFトークンを発行する

declare(strict_types=1);

require_once __DIR__ . '/lib/env.php';
require_once __DIR__ . '/lib/security.php';

$origin = $_SERVER['HTTP_ORIGIN'] ?? null;
slw_apply_cors_headers($origin);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

echo json_encode(['ok' => true, 'csrfToken' => slw_issue_csrf_token()]);
