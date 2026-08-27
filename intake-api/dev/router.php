<?php
/**
 * ローカル確認用のルーター（PHP 内蔵サーバー専用）。
 *
 *   php -c intake-api/dev/php.ini -S 127.0.0.1:8788 -t intake-api/public intake-api/dev/router.php
 *
 * ★本番では .htaccess が同じ振り分けを行う。このファイルは配置しない。
 * ★127.0.0.1 だけで待ち受ける。外部へ公開しない。
 */
declare(strict_types=1);

require_once __DIR__ . '/preview-env.php';
previewPutEnv();

$publicDir = __DIR__ . '/../public';
$path      = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

/** 画面（.htaccess の RewriteRule と同じ振り分け） */
$pages = [
    '/'      => '/start.html',
    '/start' => '/start.html',
    '/form'  => '/form.html',
];
$page = $pages[rtrim($path, '/') === '' ? '/' : rtrim($path, '/')] ?? null;

if ($page !== null) {
    header('Content-Type: text/html; charset=UTF-8');
    previewHeaders();
    readfile($publicDir . $page);

    return true;
}

// 実ファイル（CSS / JS）は内蔵サーバーへ任せる
$file = realpath($publicDir . $path);
if ($file !== false && is_file($file) && strncmp($file, (string)realpath($publicDir), strlen((string)realpath($publicDir))) === 0) {
    previewHeaders();

    return false;
}

// 残りは受付APIへ
require $publicDir . '/index.php';

return true;

/** 本番の .htaccess と同じヘッダーを、ローカルでも付ける */
function previewHeaders(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header(
        "Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; "
        . "img-src 'self' data:; font-src 'self'; connect-src 'self'; form-action 'self'; "
        . "frame-ancestors 'none'; base-uri 'none'; object-src 'none'"
    );
}
