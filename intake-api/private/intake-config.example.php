<?php
/**
 * HP Intake API — 実設定の雛形。
 *
 * 使い方:
 *   このファイルを intake-config.php としてコピーし、値を入れる。
 *   ★intake-config.php は .gitignore で除外されている（Gitへ入れない）。
 *   ★配置先は public_html の外（domain root 直下）。権限は 600。
 *
 * 鍵の生成（それぞれ別の値を使う）:
 *   php -r "echo bin2hex(random_bytes(32));"   # ip_hmac_key 用
 *   php -r "echo bin2hex(random_bytes(32));"   # enc_key 用
 *
 * ★鍵が未設定・短すぎる場合、アプリは起動しない（fail closed）。
 */
declare(strict_types=1);

return [
    // SQLite の実体。public_html の外に置く
    'db_path' => __DIR__ . '/intake.sqlite',

    // 生IPをHMAC化する鍵（生IPは保存しない）
    'ip_hmac_key' => '',

    // Google Drive フォルダURLの暗号化鍵（AES-256-GCM）
    'enc_key' => '',

    // 許可オリジン。https のみ。ワイルドカードは使わない
    'allowed_origins' => ['https://intake.smartlaboworks.com'],

    // レート制限の記録先（保存するのは送信時刻のみ）
    'rate_limit_dir' => __DIR__ . '/ratelimit',

    // アプリログ。PII・token・session secret・Drive URL は出力しない
    'log_path' => __DIR__ . '/logs/intake.log',

    // HTTPS 強制。本番では必ず true
    'require_https' => true,
];
