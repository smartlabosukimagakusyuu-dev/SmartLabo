<?php
// Smart Labo Works — 問い合わせフォーム セキュリティユーティリティ
// CSRF検証(ステートレス署名トークン)・Origin検証・honeypot・送信速度トラップ
//
// 【教訓】Node.js/Vercel版の実装時、署名(16進数文字列)の長さ・文字種を
// 厳密一致させずにデコードすると、末尾に付加したゴミ文字列が黙って
// 切り捨てられ、改ざんトークンが正規トークンと誤判定される脆弱性を作り
// 込んでしまった。本実装では最初から署名を「64桁の16進文字列」と
// 厳密一致させたうえでhash_equals()による定数時間比較を行う。

const SLW_CSRF_TOKEN_MAX_AGE = 2 * 60 * 60; // 2時間(秒)
const SLW_MIN_FILL_TIME_MS = 3000; // フォーム表示から3秒未満の送信はbot疑い

function slw_csrf_sign(string $payload): string
{
    return hash_hmac('sha256', $payload, slw_config('csrf_secret'));
}

function slw_issue_csrf_token(): string
{
    $timestamp = (string) time();
    $signature = slw_csrf_sign($timestamp);
    return base64_encode($timestamp . '.' . $signature);
}

function slw_verify_csrf_token(?string $token): bool
{
    if (!$token) {
        return false;
    }
    $decoded = base64_decode($token, true);
    if ($decoded === false) {
        return false;
    }
    $parts = explode('.', $decoded);
    if (count($parts) !== 2) {
        return false;
    }
    [$timestamp, $signature] = $parts;

    if (!preg_match('/^\d+$/', $timestamp) || !preg_match('/^[0-9a-f]{64}$/i', $signature)) {
        return false;
    }

    $expected = slw_csrf_sign($timestamp);
    if (!hash_equals($expected, strtolower($signature))) {
        return false;
    }

    $age = time() - (int) $timestamp;
    return $age >= 0 && $age <= SLW_CSRF_TOKEN_MAX_AGE;
}

function slw_is_allowed_origin(?string $origin): bool
{
    if (!$origin) {
        return false;
    }
    $allowed = slw_config('allowed_origins');
    return in_array($origin, $allowed, true);
}

// honeypot: 人間には見えないフィールド。botが自動入力してくると値が入る。
function slw_is_honeypot_triggered(array $body): bool
{
    return !empty($body['website']);
}

// 送信速度トラップ: フォーム表示から極端に短時間での送信はbot疑い。
function slw_is_too_fast(array $body): bool
{
    $renderedAt = isset($body['formRenderedAt']) ? (float) $body['formRenderedAt'] : 0;
    if ($renderedAt <= 0) {
        return true; // フィールド自体がない送信も不正とみなす
    }
    $nowMs = microtime(true) * 1000;
    return ($nowMs - $renderedAt) < SLW_MIN_FILL_TIME_MS;
}

function slw_client_ip(): string
{
    // XServer(共有サーバー)の一般的な構成を想定。リバースプロキシの構成が
    // 変わる場合はここを調整すること。
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function slw_apply_cors_headers(?string $origin): void
{
    if (slw_is_allowed_origin($origin)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}
