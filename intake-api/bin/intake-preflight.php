<?php
/**
 * HP Intake API — preflight 専用領域の作成・通し確認・確認・撤去
 * （HP-ONBOARDING-4H-4 / SSOT §9.10）。
 *
 *   php bin/intake-preflight.php preflight:init
 *   php bin/intake-preflight.php preflight:run
 *   php bin/intake-preflight.php preflight:status
 *   php bin/intake-preflight.php preflight:remove
 *   php bin/intake-preflight.php preflight:remove --apply --confirm="DELETE PREFLIGHT AREA"
 *   php bin/intake-preflight.php preflight:verify-empty
 *
 * ★このファイルを **public_html の中へ置かない**。`bin/` は配置対象外である。
 * ★万一 Web から読まれても実行されないよう、**CLI 以外では即座に終了**する。
 * ★preflight の位置は **APP_ROOT/preflight/ に固定**である。
 *   任意の絶対パスを argv で受け取らない。絶対パスを出力にも載せない。
 * ★正式 `intake-config.php` を **require しない**（overrides で組む）。
 * ★`init` / `run` は、正式設定または正式DBが存在する環境では
 *   **順序違反として実行しない**（SSOT §9.10-9）。
 *   `status` / `remove` / `verify-empty` はこの条件を課さない
 *   （正式設定を入れたあとでも撤去できる必要があるため）。
 * ★削除は **dry-run が既定**。実削除には `--apply` と確認文字列の完全一致が要る。
 * ★`init` が失敗しても**自動で削除しない**。
 *   撤去は status → dry-run → 代表承認 → apply の順で行う。
 * ★秘密値（鍵・管理者情報）を出力・ログへ出さない。
 *
 * 手順書: docs/website/HP_INTAKE_BACKUP_RESTORE_RUNBOOK_V1.md
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    // ★Web 経由の実行を認めない。ヘッダも本文も出さずに終える
    http_response_code(404);
    exit(1);
}

// ★作るものはすべて所有者だけのものにする
umask(0077);

require_once __DIR__ . '/../src/Autoload.php';

use SmartLabo\Intake\AppRoot;
use SmartLabo\Intake\Backup\BackupCli;
use SmartLabo\Intake\ConfigException;
use SmartLabo\Intake\Preflight\PreflightArea;
use SmartLabo\Intake\Preflight\PreflightBuilder;
use SmartLabo\Intake\Preflight\PreflightRunner;

$out = static function (string $line = ''): void {
    echo $line . "\n";
};

try {
    // ★APP_ROOT は解決して検査する。argv からは受け取らない
    $appRoot = AppRoot::require();
} catch (ConfigException $e) {
    fwrite(STDERR, "[NG] APP_ROOT を解決できません。\n");
    exit(1);
}

$builder = new PreflightBuilder($appRoot);
$area    = new PreflightArea(
    $builder->root(),
    $builder->productionPrivateRoot(),
    $builder->productionDbPath(),
);

$command = $argv[1] ?? '';
$options = BackupCli::parseOptions(array_slice($argv, 2));

if (array_key_exists('root', $options)) {
    // ★位置は固定である。任意のパスを受け取らない
    $out('[NG] --root は受け付けません。preflight の位置は APP_ROOT/preflight に固定です。');
    exit(1);
}

/** 初回導入としての順序を確かめる（init / run だけ） */
$requireInitialInstall = static function () use ($builder, $out): void {
    $result = $builder->assertInitialInstall();
    if ($result['ok'] === true) {
        return;
    }
    $out('[NG] 正式設定または正式DBが既にあるため、初回導入用の preflight は実行しません。');
    $out('  検出   : ' . (string)($result['found'] ?? '?') . '（正式領域）');
    $out('  ★SSOT §9.10-9 は「preflight 撤去 → 正式DB新規作成」の順序を求めています。');
    $out('  ★撤去だけなら status / remove / verify-empty は実行できます。');
    exit(1);
};

switch ($command) {

    /* ------------------------------------------------------------ init */
    case 'preflight:init':
    case 'init':
        $requireInitialInstall();

        $pre = $builder->precreateCheck();
        if ($pre['ok'] !== true) {
            $out('[NG] preflight 領域を作れません（' . (string)$pre['error'] . '）');
            $out('  ★1件も作成していません。');
            exit(1);
        }

        $made = $builder->create();
        if ($made['ok'] !== true) {
            $out('[NG] preflight 領域の作成に失敗しました（' . (string)$made['error'] . '）');
            $out('  ★**自動では削除しません。**');
            $out('  ★次の順で確認してください: preflight:status → preflight:remove（dry-run）');
            $out('    → 代表承認 → preflight:remove --apply --confirm="'
                . PreflightArea::CONFIRM_REMOVE . '"');
            exit(1);
        }

        $out('[OK] preflight 領域を作成しました（場所は <APP_ROOT>/preflight）');
        $out('  ディレクトリ : 700 ／ 設定ファイル : 600');
        $out('  子ディレクトリ: ' . implode(' / ', PreflightArea::CHILDREN));
        $out('  ★鍵は2つ別々に生成して領域内へ書きました。値は表示しません。');
        $out('  ★通知の宛先・差出人は設定していません（実メールを送りません）。');
        $out('  次: php bin/intake-preflight.php preflight:run');
        exit(0);

    /* ------------------------------------------------------------- run */
    case 'preflight:run':
    case 'run':
        $requireInitialInstall();

        $checked = $area->check();
        if ($checked['ok'] !== true) {
            $out('[NG] preflight 領域を使えません（' . (string)$checked['error'] . '）');
            $out('  先に preflight:init を実行してください。');
            exit(1);
        }

        // ★正式領域の baseline を、動かす直前に取る（決め打ちの期待値を持たない）
        $before = $builder->productionSnapshot();

        $result = (new PreflightRunner($builder))->run();

        $after      = $builder->productionSnapshot();
        $comparison = PreflightBuilder::compareSnapshots($before, $after);

        foreach ($result['steps'] as $step) {
            $out(($step['ok'] ? '  [OK] ' : '  [NG] ') . $step['label']);
        }
        $out('');
        $out('  通し確認 : ' . $result['passed'] . ' 件成功 / ' . $result['failed'] . ' 件失敗');
        if (isset($result['error'])) {
            $out('  中断理由 : ' . (string)$result['error']);
        }

        $out('');
        $out('  正式領域の不変検査');
        foreach ($comparison['notes'] as $note) {
            $out('    [記録] ' . $note);
        }
        foreach ($comparison['problems'] as $problem) {
            $out('    [NG]   ' . $problem);
        }
        if ($comparison['ok']) {
            $out('    [OK]   正式領域と配置物に変化なし'
                . '（一覧 / 配置物の SHA-256 / 出現してはならないもの）');
        }

        if ($result['ok'] !== true || $comparison['ok'] !== true) {
            $out('');
            $out('[NG] preflight は完了していません。**独断で修復・削除しないでください。**');
            exit(1);
        }

        $out('');
        $out('[OK] preflight の通し確認が完了しました。');
        $out('  次: php bin/intake-preflight.php preflight:status');
        exit(0);

    /* ---------------------------------------------------------- status */
    case 'preflight:status':
    case 'status':
        $result = $area->inventory();
        if ($result['ok'] !== true) {
            $out('[NG] preflight 領域を使えません（' . (string)$result['error'] . '）');
            exit(1);
        }
        $out('[OK] preflight 領域は使えます（場所は <APP_ROOT>/preflight）');
        $out('  ファイル       : ' . $result['files'] . ' 件');
        $out('  ディレクトリ   : ' . $result['dirs'] . ' 件');
        foreach ($result['entries'] as $name) {
            $out('    - ' . $name);
        }
        $out('  ★正式DB・正式バックアップとは別実体です。');
        exit(0);

    /* ---------------------------------------------------------- remove */
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
        $out('  次: php bin/intake-preflight.php preflight:verify-empty');
        exit(0);

    /* ---------------------------------------------------- verify-empty */
    case 'preflight:verify-empty':
    case 'verify-empty':
        $root = $builder->root();
        if (file_exists($root) || is_link($root)) {
            $inventory = $area->inventory();
            $remaining = $inventory['ok'] === true ? (int)$inventory['files'] + (int)$inventory['dirs'] : -1;
            $out('[NG] preflight 領域が残っています');
            $out('  残存   : ' . ($remaining < 0 ? '数えられません' : $remaining . ' 件'));
            $out('  ★独断で削除せず、preflight:remove の dry-run を確認してください。');
            exit(1);
        }
        $out('[OK] preflight 領域は残存0です（<APP_ROOT>/preflight は存在しません）');
        $out('  ★このあとで正式DBを新規作成してください（SSOT §9.10-9）。');
        exit(0);

    /* --------------------------------------------------------- default */
    default:
        $out('HP Intake — preflight 専用領域の管理CLI（端末からのみ）');
        $out('  ★位置は APP_ROOT/preflight に固定です（--root は受け付けません）');
        $out('');
        $out('  php bin/intake-preflight.php preflight:init');
        $out('  php bin/intake-preflight.php preflight:run');
        $out('  php bin/intake-preflight.php preflight:status');
        $out('  php bin/intake-preflight.php preflight:remove');
        $out('  php bin/intake-preflight.php preflight:remove --apply --confirm="'
            . PreflightArea::CONFIRM_REMOVE . '"');
        $out('  php bin/intake-preflight.php preflight:verify-empty');
        exit(2);
}
