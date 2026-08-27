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

    // ------------------------------------------------------------------
    // 内部確認画面（SSOT v1.4 §10.8）。代表1名のみ
    //
    // ★ここに**平文パスワードを書かない**。password_hash() が作った hash だけを置く。
    // ★hash の作り方（Argon2id を優先する）:
    //     php -r "echo password_hash('ここに決めたパスワード', PASSWORD_ARGON2ID), PHP_EOL;"
    //   Argon2id が使えない環境では:
    //     php -r "echo password_hash('ここに決めたパスワード', PASSWORD_BCRYPT), PHP_EOL;"
    // ★下の値は**明らかなダミー**である。必ず自分で作り直すこと。
    // ★未設定・平文のままの場合、管理画面は動かない（fail closed）。
    //   店舗向けの受付APIはそのまま動く。
    // ------------------------------------------------------------------
    'admin_id' => '',

    // ダミー（この hash に対応する平文は運用に存在しない）
    'admin_password_hash' => '',

    // ------------------------------------------------------------------
    // 保持期限による削除（SSOT v1.7 §9.8）
    //
    // ★どちらも既定 false。**両方 true のときだけ**削除を実行できる。
    // ★削除は取り消せない。設定が整う前に動く経路を作らないための二重の鍵である。
    // ★`backup_policy_confirmed` は、**本番バックアップの世代・削除方針が
    //   確定してから（4G）**true にすること。古い世代が残っている間は、
    //   消したはずの回答がそこから復元できてしまう。
    // ★"false" / "0" / "off" は偽として扱われる（明示的に真と書いたときだけ真）。
    // ------------------------------------------------------------------
    'retention_actions_enabled' => false,
    'backup_policy_confirmed'   => false,
];
