<?php
/**
 * 設定の読み込みと共通の初期化。
 * 設定ファイルはドキュメントルートの外（private/）にある前提。
 * 構成は contact-api/public/lib/config.php と揃えている。
 */

declare(strict_types=1);

/** PHPのエラーを画面へ出さない（内部情報の露出防止） */
function sls_harden_php(): void
{
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');   // サーバーのエラーログへのみ出す
    error_reporting(E_ALL);
}

/**
 * 設定を読む。private/ はドキュメントルートの1つ上にある想定。
 * 見つからない場合は同階層の private/ も探す（配置ミスの救済。
 * ただし .htaccess で全拒否している）。
 */
function sls_load_config(): array
{
    $candidates = [
        dirname(__DIR__, 2) . '/private/signup-config.php', // public_html の1つ上
        dirname(__DIR__, 1) . '/private/signup-config.php', // public_html/private（保険）
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

/**
 * 設定が実運用に足りているかを確認する。足りなければ理由（キー名）を返す。
 *
 * SALES-1では保存もメール送信も行わないため、必要なのは
 * オリジン許可・CSRF鍵・IPハッシュ鍵の3つだけ。
 * SALES-2でStripeの鍵が加わるが、それは決済実装時に追加する。
 */
function sls_config_problems(array $c): array
{
    $problems = [];
    foreach (['csrf_secret', 'ip_hash_secret'] as $key) {
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
function sls_config(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $c = sls_load_config();
    $cache = $c + [
        'allowed_origins'           => [],
        'csrf_secret'               => '',
        'ip_hash_secret'            => '',
        'min_seconds_before_submit' => 3,
        'rate_limit_max'            => 5,      // 申し込みは入力し直しがあり得るため問い合わせより緩め
        'rate_limit_window'         => 600,
        'max_body_bytes'            => 20000,
        'mode'                      => 'test',
        'require_csrf_always'       => true,   // 申込画面はJavaScript前提のため常に必須にできる
    ];
    return $cache;
}
