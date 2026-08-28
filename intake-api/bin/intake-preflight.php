<?php
/**
 * HP Intake API — preflight 領域の確認と削除（HP-ONBOARDING-4H-R0 / SSOT v1.12 §9.10）。
 *
 *   php -c intake-api/dev/php.ini intake-api/bin/intake-preflight.php preflight:status
 *   php -c intake-api/dev/php.ini intake-api/bin/intake-preflight.php preflight:remove
 *   php ... preflight:remove --apply --confirm="DELETE PREFLIGHT AREA"
 *
 * ★このファイルを **public_html の中へ置かない**。`bin/` は配置対象外である。
 * ★万一 Web から読まれても実行されないよう、**CLI 以外では即座に終了**する。
 * ★削除は **dry-run が既定**。実削除には `--apply` と確認文字列の完全一致が要る。
 * ★正式な private_root・稼働DBと重なる領域は、検査そのものが通らない。
 * ★絶対パス全文を出さない（`<preflight_root>` と表示する）。
 *
 * 手順書: docs/website/HP_INTAKE_BACKUP_RESTORE_RUNBOOK_V1.md
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    // ★Web 経由の実行を認めない。ヘッダも本文も出さずに終える
    http_response_code(404);
    exit(1);
}

require_once __DIR__ . '/../src/Autoload.php';

use SmartLabo\Intake\Backup\BackupCli;
use SmartLabo\Intake\Config;
use SmartLabo\Intake\ConfigException;
use SmartLabo\Intake\Preflight\PreflightArea;

try {
    $config = Config::load();
} catch (ConfigException $e) {
    fwrite(STDERR, "[NG] 設定が読めません。private/intake-config.php または環境変数を確認してください。\n");
    exit(1);
}

$area = new PreflightArea($config->preflightRoot, $config->privateRoot, $config->dbPath);

$command = $argv[1] ?? '';
$options = BackupCli::parseOptions(array_slice($argv, 2));

$out = static function (string $line): void {
    echo $line . "\n";
};

if (!$area->configured()) {
    $out('[NG] preflight_root が未設定です。設定していない環境では何もしません。');
    exit(1);
}

switch ($command) {
    case 'preflight:status':
    case 'status':
        $result = $area->inventory();
        if ($result['ok'] !== true) {
            $out('[NG] preflight 領域を使えません（' . (string)$result['error'] . '）');
            exit(1);
        }
        $out('[OK] preflight 領域は使えます（場所は <preflight_root>）');
        $out('  ファイル       : ' . $result['files'] . ' 件');
        $out('  ディレクトリ   : ' . $result['dirs'] . ' 件');
        foreach ($result['entries'] as $name) {
            $out('    - ' . $name);
        }
        $out('  ★正式DB・正式バックアップとは別実体です。');
        exit(0);

    case 'preflight:remove':
    case 'remove':
        $apply   = array_key_exists('apply', $options);
        $confirm = $options['confirm'] ?? '';

        $result = $area->remove($apply, $confirm);
        if ($result['ok'] !== true) {
            $out('[NG] 削除できません（' . (string)($result['error'] ?? 'unknown') . '）');
            $out('  ★1件も削除していません。');
            exit(1);
        }
        if ($result['dry_run']) {
            $out('[OK] preflight 領域の削除（dry-run。1件も削除していません）');
            $out('  対象   : ' . $result['remaining'] . ' 件');
            $out('  実削除するには: --apply --confirm="' . PreflightArea::CONFIRM_REMOVE . '"');
            $out('  ★削除した preflight データは復元できません（架空データのみのはずです）。');
            exit(0);
        }
        $out('[OK] preflight 領域を削除しました');
        $out('  削除   : ' . $result['removed'] . ' 件');
        $out('  残存   : ' . $result['remaining'] . ' 件');
        $out('  ★このあとで正式DBを新規作成してください（SSOT v1.12 §9.10-9）。');
        exit(0);

    default:
        $out('HP Intake — preflight 領域の管理CLI（端末からのみ）');
        $out('');
        $out('  php bin/intake-preflight.php preflight:status');
        $out('  php bin/intake-preflight.php preflight:remove');
        $out('  php bin/intake-preflight.php preflight:remove --apply --confirm="'
            . PreflightArea::CONFIRM_REMOVE . '"');
        exit(2);
}
