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
    // ------------------------------------------------------------------
    // 置き場所（SSOT v1.12 §10.11・4H-R0）
    //
    // ★APP_ROOT（= src / bin / private の親）は**この設定では決めない**。
    //   `.user.ini` の auto_prepend_file が読む非公開 bootstrap が定数で与えるか、
    //   代替方式（docroot の祖先の `private/hp-intake/`）で見つける。
    //   雛形: private/app-root-bootstrap.example.php
    // ★このファイル自身は **APP_ROOT/private/intake-config.php** から読まれる。
    //   位置は動かせない（読む前に決まらないため）。
    // ★`private_root` は**データの置き場所**だけを移す。既定は APP_ROOT/private。
    //   相対パス・public_html 配下・ホーム直下・ルート直下は拒否される（fail closed）。
    // ------------------------------------------------------------------
    // 'private_root' => '/絶対パス/private/hp-intake/private',

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

    // ------------------------------------------------------------------
    // バックアップの保存先（SSOT v1.11 §9.5・4G で実装）
    //
    // ★**絶対パス**で書く。public_html の外・ホーム直下でない専用ディレクトリ。
    //   ディレクトリ 700 / ファイル 600。
    // ★本番の正確な絶対パスは **4H で XServer 実機を確認してから**確定する。
    //   本番の候補は ${DOMAIN_ROOT}/private/intake/backups である。
    // ★未設定（null）ならバックアップ経路そのものが動かない（fail closed）。
    // ★相対パス・public_html 配下・ルート直下・ホーム直下は拒否される。
    // ★作成・検証・復元確認・削除は**管理CLI**から行う。cron を作らない。
    //     php bin/intake-backup.php backup:create
    //   手順は docs/website/HP_INTAKE_BACKUP_RESTORE_RUNBOOK_V1.md を参照。
    // ------------------------------------------------------------------
    'backup_dir' => __DIR__ . '/backups',

    // ------------------------------------------------------------------
    // 本番配置後の通し確認だけに使う領域（SSOT v1.12 §9.10・4H-R0）
    //
    // ★**正式DB・正式バックアップと完全に分ける。** 架空データ専用である。
    // ★正式な private_root と同一・内側・外側から包む位置は拒否される。
    // ★確認が済んだら領域ごと削除し、**そのあとで正式DBを新規作成する**。
    //     php bin/intake-preflight.php preflight:status
    //     php bin/intake-preflight.php preflight:remove
    // ★未設定なら preflight の経路そのものが動かない。
    // ------------------------------------------------------------------
    // 'preflight_root' => '/絶対パス/private/hp-intake/preflight',

    // ------------------------------------------------------------------
    // 提出通知メール（SSOT v1.12 §9.11・4H-R0）
    //
    // ★送るのは **案件番号・イベント種別・発生日時の3項目だけ**。
    //   回答本文・店舗名・氏名・メール・電話・住所・token・session・
    //   submission_id・Drive URL・修正依頼本文・IP は**一切含めない**。
    // ★宛先は **1つだけ**。カンマ・セミコロン・改行・山括弧・引用符・
    //   バックスラッシュを含む値は受け付けない（ヘッダー注入対策）。
    // ★宛先と差出人が**両方そろったときだけ**送る。片方でも欠ければ送らない。
    // ★差出人は XServer で正式に使える自社ドメインのアドレスを 4H で設定する。
    // ★下の値は**架空**（example.invalid）である。必ず実値へ差し替えること。
    // ★実送信テストは 4H で当社 info@ 宛て**1通のみ**。代表の直前承認が要る。
    // ------------------------------------------------------------------
    'notification_recipient' => 'ops@example.invalid',
    'notification_from'      => 'no-reply@example.invalid',
];
