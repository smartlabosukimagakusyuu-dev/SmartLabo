<?php
/**
 * ローカル確認**専用**の設定（HP-ONBOARDING-4C）。
 *
 * ★本番へ配置しない。`public/` の外にあり、Web からは決して読めない。
 * ★ここに実鍵を書かない。使い捨てのダミーだけを置く。
 * ★DB は tests/.tmp 配下の使い捨て。**本番・既存DBへ接続しない**。
 * ★127.0.0.1 だけで待ち受ける。外部へ公開しない。
 */
declare(strict_types=1);

/** ローカル確認で使うポート */
const PREVIEW_PORT = 8788;

const PREVIEW_ORIGIN = 'http://127.0.0.1:' . PREVIEW_PORT;

/** 使い捨てのダミー鍵（本番の鍵ではない。tests/bootstrap.php と同じ方針） */
const PREVIEW_IP_HMAC_KEY = 'preview-only-ip-hmac-key-0123456789abcdef';
const PREVIEW_ENC_KEY     = 'preview-only-enc-key-0123456789abcdefghij';

/**
 * ローカル確認用の管理者（SSOT v1.4 §10.8）。
 *
 * ★使い捨て。**本番の資格情報ではない**。
 * ★hash は毎回その場で作る（Git へ hash も入れないため）。
 */
const PREVIEW_ADMIN_ID       = 'preview-admin';
const PREVIEW_ADMIN_PASSWORD = 'preview-only-password-0123456789';

function previewAdminHash(): string
{
    $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;

    return password_hash(PREVIEW_ADMIN_PASSWORD, $algo);
}

/**
 * 使い捨てDBの置き場所。
 *
 * ★`tests/.tmp/` へ置いてはならない。
 *   run-tests.php は実行のたびに `tests/.tmp/` を空にするため、
 *   テストを流すと確認中の案件とセッションごと消えてしまう。
 * ★`dev/.preview/` は .gitignore 済み。公開領域（public/）の外にある。
 */
function previewBaseDir(): string
{
    $dir = __DIR__ . '/.preview';
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }

    return $dir;
}

/** Config::load() が読む環境変数を、ローカル確認用の値で埋める */
function previewPutEnv(): void
{
    $base = previewBaseDir();
    putenv('INTAKE_DB_PATH=' . $base . '/intake.sqlite');
    putenv('INTAKE_IP_HMAC_KEY=' . PREVIEW_IP_HMAC_KEY);
    putenv('INTAKE_ENC_KEY=' . PREVIEW_ENC_KEY);
    putenv('INTAKE_ALLOWED_ORIGINS=' . PREVIEW_ORIGIN);
    putenv('INTAKE_RATELIMIT_DIR=' . $base . '/ratelimit');
    putenv('INTAKE_LOG_PATH=' . $base . '/intake.log');
    // ★ローカルは http。この指定があるときだけ 127.0.0.1 の http を許す（Config 参照）
    putenv('INTAKE_REQUIRE_HTTPS=0');

    // 内部確認画面（使い捨て。hash はその場で作るので Git に残らない）
    putenv('INTAKE_ADMIN_ID=' . PREVIEW_ADMIN_ID);
    putenv('INTAKE_ADMIN_PASSWORD_HASH=' . previewAdminHash());

    // ------------------------------------------------------------------
    // 保持期限による削除（SSOT v1.7 §9.8）
    //
    // ★**ローカル確認だけ**の override である。使い捨てDB（dev/.preview/）にしか効かない。
    // ★本番では両方 false のまま。`backup_policy_confirmed` は 4G の後に代表が設定する。
    // ------------------------------------------------------------------
    putenv('INTAKE_RETENTION_ACTIONS_ENABLED=1');
    putenv('INTAKE_BACKUP_POLICY_CONFIRMED=1');
}
