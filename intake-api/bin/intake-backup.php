<?php
/**
 * HP Intake API — バックアップ管理CLI の入口（HP-ONBOARDING-4G / SSOT v1.11 §9.5.7）。
 *
 *   php -c intake-api/dev/php.ini intake-api/bin/intake-backup.php backup:list
 *
 * ★このファイルを **public_html の中へ置かない**。`bin/` は配置対象外である。
 * ★万一 Web から読まれても実行されないよう、**CLI 以外では即座に終了**する。
 * ★引数に秘密値を渡さない。設定は環境変数か private/intake-config.php から読む。
 * ★標準出力へ DB の中身を出さない（BackupCli の責任範囲）。
 *
 * 手順書: docs/website/HP_INTAKE_BACKUP_RESTORE_RUNBOOK_V1.md
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    // ★Web 経由の実行を認めない。ヘッダも本文も出さずに終える
    http_response_code(404);
    exit(1);
}

// ★CLI の APP_ROOT は自明である（bin/ は APP_ROOT の直下に置く）。
//   Web と違い、ここを外から差し替えられる経路は無い。
require_once __DIR__ . '/../src/Autoload.php';

use SmartLabo\Intake\Backup\BackupCli;
use SmartLabo\Intake\Config;
use SmartLabo\Intake\ConfigException;
use SmartLabo\Intake\Kernel;

try {
    // ★APP_ROOT は Config::load() の中で検査する（不正なら例外・fail closed）
    $kernel = new Kernel(Config::load());
} catch (ConfigException $e) {
    // ★例外文をそのまま出さない（設定値が混ざりうる）。固定文だけを出す
    fwrite(STDERR, "[NG] 設定が読めません。private/intake-config.php または環境変数を確認してください。\n");
    exit(1);
}

exit((new BackupCli($kernel->backup))->run(array_slice($argv, 1)));
