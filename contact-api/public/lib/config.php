<?php
/**
 * 設定の読み込みと共通の初期化。
 * 設定ファイルはドキュメントルートの外（private/）にある前提。
 */

declare(strict_types=1);

/** PHPのエラーを画面へ出さない（内部情報の露出防止） */
function slw_harden_php(): void
{
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');   // サーバーのエラーログへのみ出す
    error_reporting(E_ALL);
}

/**
 * 設定を読む。
 * private/ はドキュメントルートの1つ上にある想定。見つからない場合は
 * 同階層の private/ も探す（配置ミスの救済。ただし .htaccess で全拒否している）。
 */
function slw_load_config(): array
{
    $candidates = [
        dirname(__DIR__, 2) . '/private/contact-config.php', // public_html の1つ上
        dirname(__DIR__, 1) . '/private/contact-config.php', // public_html/private（保険）
    ];

    foreach ($candidates as $path) {
        if (is_file($path)) {
            /** @var array $config */
            $config = require $path;
            return is_array($config) ? $config : [];
        }
    }
    return [];
}

/** 設定が実運用に足りているかを確認する。足りなければ理由を返す */
function slw_config_problems(array $c): array
{
    $problems = [];
    foreach (['to_email', 'from_email', 'csrf_secret', 'ip_hash_secret'] as $key) {
        if (empty($c[$key])) {
            $problems[] = $key;
        }
    }
    if (empty($c['allowed_origins']) || !is_array($c['allowed_origins'])) {
        $problems[] = 'allowed_origins';
    }
    return $problems;
}

/** 既定値を埋めた設定を返す */
function slw_config(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $c = slw_load_config();
    $cache = $c + [
        'to_email'                  => '',
        'from_email'                => '',
        'from_name'                 => 'Smart Labo Works お問い合わせ',
        'allowed_origins'           => [],
        'csrf_secret'               => '',
        'ip_hash_secret'            => '',
        'min_seconds_before_submit' => 3,
        'rate_limit_max'            => 3,
        'rate_limit_window'         => 600,
        'max_body_bytes'            => 20000,
        'mode'                      => 'test',
        'require_csrf_always'       => false,
    ];
    return $cache;
}
