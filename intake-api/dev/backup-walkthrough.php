<?php
/**
 * バックアップ・復元確認・世代管理・保持削除連動の通し確認
 * （HP-ONBOARDING-4G / SSOT v1.11 §9.5）
 *
 *   php -c intake-api/dev/php.ini intake-api/dev/backup-walkthrough.php
 *
 * ★**使い捨てのDBとバックアップ置き場を毎回作り直して**確認する。
 *   本番・既存DB（dev/.preview/ を含む）へは一切接続しない。
 * ★架空の店舗・架空のメール・架空のフォルダURLだけを使う。
 * ★XServer へ接続しない。本番パスを作らない。cron を作らない。
 * ★backup_policy_confirmed の本番 true 化は行わない（4H の別承認工程）。
 *
 * 何を確かめるか（28段）:
 *   架空DB → 作成 → SHA-256 → verify → restore drill → 復元DBの整合 →
 *   元DB不変 → 複数世代 → cleanup dry-run → 確認なしで0件 → 30日超 → 60世代超 →
 *   保持対象の案件 → purge前世代 → purge → purge後世代 → drill →
 *   purge前世代の削除 → 新世代にPIIなし → purge前0件 → 失敗注入で旧世代維持 →
 *   再実行で完了 → CLI が Web から動かない → フラグ false → 後始末
 */
declare(strict_types=1);

require_once __DIR__ . '/../src/Autoload.php';

use SmartLabo\Intake\Backup\BackupCli;
use SmartLabo\Intake\Backup\BackupPaths;
use SmartLabo\Intake\Backup\BackupService;
use SmartLabo\Intake\Config;
use SmartLabo\Intake\Kernel;
use SmartLabo\Intake\Support\Clock;

/* ---------------------------------------------------------------- 準備 */

/** 削除されたことを確かめるための目印（架空・他に出てこない文字列） */
const BW_MARKER = 'BACKUPWALKMARKER0001';
const BW_EMAIL  = 'materials@example.invalid';
const BW_DRIVE  = 'https://drive.google.com/drive/folders/FAKE-BACKUP-WALK-000';
const BW_NUMBER = 'HP-202608-0900';

/** 時計を自由に動かせる Clock */
final class BackupWalkClock extends Clock
{
    public int $offset = 0;

    public function now(): int
    {
        return time() + $this->offset;
    }

    public function advance(int $seconds): void
    {
        $this->offset += $seconds;
    }
}

$step = 0;
$bad  = 0;
$check = static function (string $label, bool $ok) use (&$step, &$bad): void {
    ++$step;
    if (!$ok) {
        ++$bad;
    }
    printf("  %2d. [%s] %s\n", $step, $ok ? 'OK' : 'NG', $label);
};

$base = __DIR__ . '/.backup-walkthrough-' . getmypid();
$dir  = $base . '/backups';
mkdir($dir, 0700, true);
mkdir($base . '/logs', 0700, true);

$cleanup = static function () use ($base): void {
    if (!is_dir($base)) {
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $path) {
        $path->isDir() ? @rmdir($path->getPathname()) : @unlink($path->getPathname());
    }
    @rmdir($base);
};

$clock  = new BackupWalkClock();
$config = Config::load([
    'db_path'         => $base . '/walkthrough.sqlite',
    'ip_hmac_key'     => 'backup-walkthrough-ip-hmac-key-0123456789',
    'enc_key'         => 'backup-walkthrough-enc-key-0123456789abcd',
    'allowed_origins' => ['http://127.0.0.1:8788'],
    'rate_limit_dir'  => $base . '/ratelimit',
    'log_path'        => $base . '/logs/intake.log',
    'require_https'   => false,
    'backup_dir'      => $dir,
    // ★保持削除はローカル override でだけ有効にする（本番は既定 false のまま）
    'retention_actions_enabled' => true,
    'backup_policy_confirmed'   => true,
]);

$kernel = new Kernel($config, $clock);
$backup = $kernel->backup;

echo "HP Intake — バックアップ通し確認（架空案件・使い捨てDB）\n";
echo str_repeat('-', 64) . "\n";
echo "  DB      : " . $base . "/walkthrough.sqlite\n";
echo "  backups : " . $dir . "\n\n";

/* -------------------------------------------------- 1. 架空DB作成 */

$caseId = $kernel->cases->create(BW_NUMBER, '架空サロン ' . BW_MARKER);
$kernel->cases->setDriveFolder($caseId, BW_DRIVE, BW_NUMBER . ' 素材', BW_EMAIL);
$kernel->answers->save($caseId, ['basic' => ['legal_name' => '架空サロン ' . BW_MARKER]], 1);
$check('架空の案件を1件つくった（使い捨てDB）', $kernel->cases->find($caseId) !== null);

/* -------------------------------------------------- 2. backup作成 */

$first = $backup->create();
$check('バックアップを作成した（SQLite3::backup()）',
    $first['ok'] === true && preg_match(BackupPaths::FILE_PATTERN, (string)$first['name']) === 1);

/* -------------------------------------------------- 3. SHA-256 */

$firstPath = $dir . '/' . $first['name'];
$check('SHA-256 が実ファイルと一致する（控えも同じ）',
    hash_file('sha256', $firstPath) === $first['sha256']
    && is_file($dir . '/' . BackupPaths::MANIFEST_NAME));

/* -------------------------------------------------- 4. verify */

$verified = $backup->verify((string)$first['name']);
$check('検証に成功した（integrity ok / fk 0 / user_version 4 / 8表 / 回答schema 1）',
    $verified['ok'] === true
    && $verified['integrity'] === 'ok'
    && $verified['foreign_key_violations'] === 0
    && $verified['schema_version'] === 4
    && $verified['answer_schema_version'] === 1);

/* -------------------------------------------------- 5. restore drill */

$dbBefore = (string)hash_file('sha256', $config->dbPath);
$drill    = $backup->restoreDrill((string)$first['name']);
$check('復元確認に成功した（一時DBへ復元して確かめた）', $drill['ok'] === true);

/* -------------------------------------------------- 6. 復元DBの整合 */

$check('復元したDBの案件行が整合している（件数のみ確認・中身は出さない）',
    ($drill['cases'] ?? 0) === 1 && ($drill['integrity'] ?? '') === 'ok');

/* -------------------------------------------------- 7. 元DB不変 */

$check('復元確認の間、元DBを書き換えていない',
    ($drill['source_unchanged'] ?? false) === true && ($drill['temp_removed'] ?? false) === true);

/* -------------------------------------------------- 8. 複数世代 */

$clock->advance(2);
$second = $backup->create();
$clock->advance(2);
$third  = $backup->create();
$check('世代を3つに増やした（同名衝突なし・上書きなし）',
    $second['ok'] === true && $third['ok'] === true
    && count((array)$backup->listGenerations()['items']) === 3);

/* -------------------------------------------------- 9. cleanup dry-run */

$dry = $backup->cleanup();
$check('cleanup は既定で dry-run（削除0件）',
    $dry['ok'] === true && $dry['dry_run'] === true && $dry['deleted'] === 0);

/* -------------------------------------------------- 10. 確認なしで削除0 */

$noConfirm = $backup->cleanup(true, 'delete old backups');
$check('確認文字列が合わなければ削除0件',
    ($noConfirm['error'] ?? '') === 'confirm_mismatch'
    && count((array)$backup->listGenerations()['items']) === 3);

/* -------------------------------------------------- 11. 30日超の削除 */

// 世代1つを「40日前」に付け替える
$aged    = BackupPaths::FILE_PREFIX . gmdate('Ymd-His', $clock->now() - (40 * 86400))
    . '-' . bin2hex(random_bytes(4)) . BackupPaths::FILE_EXT;
rename($dir . '/' . $first['name'], $dir . '/' . $aged);
$manifest = new SmartLabo\Intake\Backup\BackupManifest($dir);
$entry    = $manifest->entry((string)$first['name']) ?? [];
$entry['created_at'] = gmdate('Y-m-d\TH:i:s\Z', $clock->now() - (40 * 86400));
$manifest->forget((string)$first['name']);
$manifest->put($aged, $entry);

$expired = $backup->cleanup(true, BackupService::CONFIRM_CLEANUP);
$check('30日を過ぎた世代だけを削除した',
    $expired['ok'] === true && $expired['deleted'] === 1
    && !is_file($dir . '/' . $aged)
    && is_file($dir . '/' . $second['name'])
    && is_file($dir . '/' . $third['name']));

/* -------------------------------------------------- 12. 60世代超の削除 */

$source = (string)$second['name'];
for ($i = 1; $i <= 60; ++$i) {
    $name = BackupPaths::FILE_PREFIX . gmdate('Ymd-His', $clock->now() - (3600 * $i))
        . '-' . bin2hex(random_bytes(4)) . BackupPaths::FILE_EXT;
    copy($dir . '/' . $source, $dir . '/' . $name);
    $e = $manifest->entry($source) ?? [];
    $e['created_at'] = gmdate('Y-m-d\TH:i:s\Z', $clock->now() - (3600 * $i));
    $manifest->put($name, $e);
}
$beforeCount = count((array)$backup->listGenerations()['items']);
$excess      = $backup->cleanup(true, BackupService::CONFIRM_CLEANUP);
$check('60世代を超えた分を古い順に削除した（' . $beforeCount . ' → 60）',
    $excess['ok'] === true && $excess['deleted'] === ($beforeCount - 60)
    && count((array)$backup->listGenerations()['items']) === 60);

/* -------------------------------------------------- 13. 保持対象の案件 */

$kernel->db->pdo()->prepare(
    "UPDATE intake_cases SET status = 'locked', retention_delete_due = :due WHERE id = :id"
)->execute([':due' => gmdate('Y-m-d', $clock->now() - 86400), ':id' => $caseId]);
$check('架空案件を保持削除の対象にした（locked ＋ 期限到来）',
    $kernel->retention->canPurge($kernel->cases->find($caseId))['ok'] === true);

/* -------------------------------------------------- 14. purge前世代 */

$clock->advance(2);
$prePurge = $backup->create();
$check('purge 前のバックアップを作った（架空PIIを含む世代）',
    $prePurge['ok'] === true
    && str_contains((string)file_get_contents($dir . '/' . $prePurge['name']), BW_MARKER));

/* -------------------------------------------------- 15. purge */

$clock->advance(2);
$purged = $kernel->retention->purgeCase($caseId, BW_NUMBER, 'DELETE ' . BW_NUMBER);
$after  = $kernel->cases->find($caseId);
$check('稼働DBから案件を物理削除した（closed / deleted_at / 目印なし）',
    $purged['ok'] === true
    && $after['status'] === 'closed'
    && $after['deleted_at'] !== null
    && !str_contains((string)$after['shop_display_name'], BW_MARKER));

/* -------------------------------------------------- 16. 失敗注入（先に確かめる） */

$noPost = $backup->purgePrecedingGenerations(true, BackupService::CONFIRM_PURGE_PRECEDING);
$check('purge 後バックアップが無ければ、旧世代を1件も消さない',
    $noPost['ok'] === false
    && $noPost['error'] === 'post_purge_backup_missing'
    && $noPost['deleted'] === 0
    && is_file($dir . '/' . $prePurge['name']));

/* -------------------------------------------------- 17. purge後世代 */

$clock->advance(2);
$postPurge = $backup->create();
$check('purge 後のバックアップを作った', $postPurge['ok'] === true);

/* -------------------------------------------------- 18. 検証失敗で旧世代維持 */

// ★purge 後バックアップを壊して、検証が通らない状態を作る
file_put_contents($dir . '/' . $postPurge['name'], 'x', FILE_APPEND);
$broken = $backup->purgePrecedingGenerations(true, BackupService::CONFIRM_PURGE_PRECEDING);
$check('purge 後バックアップの検証に失敗したら、旧世代を維持する（＝バックアップ側未完了）',
    $broken['ok'] === false
    && $broken['error'] === 'post_purge_backup_unverified'
    && $broken['deleted'] === 0
    && is_file($dir . '/' . $prePurge['name']));

/* -------------------------------------------------- 19. 再実行で正常完了 */

unlink($dir . '/' . $postPurge['name']);
$manifest->forget((string)$postPurge['name']);
$clock->advance(2);
$retry = $backup->create();
$drill2 = $backup->restoreDrill((string)$retry['name']);
$check('作り直して復元確認を通した（runbook の再実行手順）',
    $retry['ok'] === true && $drill2['ok'] === true);

/* -------------------------------------------------- 20. dry-run */

$preDry = $backup->purgePrecedingGenerations();
$check('purge 前世代の削除も既定は dry-run（削除0件）',
    $preDry['ok'] === true && $preDry['dry_run'] === true && $preDry['deleted'] === 0
    && count($preDry['preceding']) >= 1);

/* -------------------------------------------------- 21. purge前世代の削除 */

$precedingCount = count($preDry['preceding']);
$applied        = $backup->purgePrecedingGenerations(true, BackupService::CONFIRM_PURGE_PRECEDING);
$check('purge 前の世代をすべて削除した（' . $precedingCount . ' 件）',
    $applied['ok'] === true && $applied['deleted'] === $precedingCount);

/* -------------------------------------------------- 22. purge前0件 */

$check('再走査して purge 前世代が 0 件である',
    $applied['remaining_preceding'] === 0
    && !is_file($dir . '/' . $prePurge['name'])
    && is_file($dir . '/' . $retry['name']));

/* -------------------------------------------------- 23. 新世代にPIIなし */

$leftovers = [];
foreach ((array)$backup->listGenerations()['items'] as $item) {
    $bytes = (string)file_get_contents($dir . '/' . $item['name']);
    foreach ([BW_MARKER, BW_EMAIL, 'drive.google.com'] as $needle) {
        if (str_contains($bytes, $needle)) {
            $leftovers[] = $item['name'] . ':' . $needle;
        }
    }
}
$check('残った世代のどこにも架空PIIが無い' . ($leftovers === [] ? '' : '（残: ' . implode(',', $leftovers) . '）'),
    $leftovers === [] && count((array)$backup->listGenerations()['items']) >= 1);

/* -------------------------------------------------- 24. 冪等な再実行 */

$again = $backup->purgePrecedingGenerations(true, BackupService::CONFIRM_PURGE_PRECEDING);
$check('同じコマンドを何度実行しても壊れない（冪等）',
    $again['ok'] === true && $again['deleted'] === 0 && $again['remaining_preceding'] === 0);

/* -------------------------------------------------- 25. CLI・フラグ・後始末 */

$lines = [];
$cli   = new BackupCli($backup, static function (string $line) use (&$lines): void {
    $lines[] = $line;
});
$cli->run(['backup:list']);
$cli->run(['backup:cleanup']);
$out = implode("\n", $lines);

$entryFile = (string)file_get_contents(__DIR__ . '/../bin/intake-backup.php');
$leaked    = [];
foreach ([BW_MARKER, BW_EMAIL, 'drive.google.com', BW_NUMBER, $dir] as $needle) {
    if (str_contains($out, $needle)) {
        $leaked[] = $needle;
    }
}
$check('CLI は Web から実行できず、出力に PII・絶対パスが出ない'
    . ($leaked === [] ? '' : '（漏れ: ' . implode(',', $leaked) . '）'),
    $leaked === []
    && str_contains($entryFile, "PHP_SAPI !== 'cli'")
    && str_contains($out, '<backup_dir>')
    && str_contains($out, 'dry-run'));

// 本番の既定（override しない設定）は false のまま
$default = Config::load([
    'db_path'     => $base . '/flagcheck.sqlite',
    'ip_hmac_key' => 'backup-walkthrough-ip-hmac-key-0123456789',
    'enc_key'     => 'backup-walkthrough-enc-key-0123456789abcd',
]);
$check('backup_policy_confirmed は既定 false のまま（4G では本番 true にしない）',
    $default->backupPolicyConfirmed === false
    && $default->retentionActionsEnabled === false
    && $default->backupDir === null);

/* -------------------------------------------------- 後始末 */

// ★この確認は API を通さない（CLI とサービスだけ）。ログが空でも異常ではない
$logPath   = $base . '/logs/intake.log';
$log       = is_file($logPath) ? (string)file_get_contents($logPath) : '';
$logLeaked = [];
foreach ([BW_MARKER, BW_EMAIL, 'drive.google.com'] as $needle) {
    if (str_contains($log, $needle)) {
        $logLeaked[] = $needle;
    }
}
$check('ログに架空PIIが出ていない' . ($logLeaked === [] ? '' : '（残: ' . implode(',', $logLeaked) . '）')
    . ($log === '' ? '（ログ出力なし）' : ''), $logLeaked === []);

$partLeft = array_values(array_filter(
    array_diff(scandir($dir) ?: [], ['.', '..']),
    static fn (string $n): bool => str_ends_with($n, BackupPaths::TEMP_EXT)
        || str_starts_with($n, BackupPaths::DRILL_PREFIX)
));
$check('一時ファイル・一時復元DBが1つも残っていない'
    . ($partLeft === [] ? '' : '（残: ' . implode(',', $partLeft) . '）'), $partLeft === []);

echo "\n";
echo str_repeat('-', 64) . "\n";
printf("  %d 段 / NG %d 件\n", $step, $bad);

// ★PDOStatement が生きている間は接続が解放されない
//   （Windows ではファイルを消せない）。参照を手放してから消す。
$kernel->db->close();
unset($kernel, $backup, $manifest, $after, $verified, $drill, $drill2);
gc_collect_cycles();
$cleanup();
echo is_dir($base)
    ? "  ★使い捨てDBが残りました: " . $base . "\n"
    : "  使い捨てDB・使い捨てバックアップを削除しました。\n";

exit($bad === 0 ? 0 : 1);
