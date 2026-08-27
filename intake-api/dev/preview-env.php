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
}
