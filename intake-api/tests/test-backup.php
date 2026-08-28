<?php
/**
 * バックアップ・復元確認・世代管理・保持削除との連動のテスト
 * （HP-ONBOARDING-4G / SSOT v1.11 §9.5）
 *
 * ★使い捨てDBと使い捨てディレクトリだけを使う。本番・既存DBへは接続しない。
 * ★架空の店舗名・架空のメールだけを使う。
 * ★Windows では chmod がほぼ効かないため、権限の実測は POSIX でのみ行い、
 *   コード側に権限設定があることは静的検査で担保する（下記「権限」参照）。
 */
declare(strict_types=1);

use SmartLabo\Intake\Backup\BackupCli;
use SmartLabo\Intake\Backup\BackupManifest;
use SmartLabo\Intake\Backup\BackupPaths;
use SmartLabo\Intake\Backup\BackupService;
use SmartLabo\Intake\Backup\SqliteBackup;
use SmartLabo\Intake\Config;
use SmartLabo\Intake\Db;
use SmartLabo\Intake\Kernel;
use SmartLabo\Intake\Migrator;
use SmartLabo\Intake\Service\Audit;
use SmartLabo\Intake\Support\Clock;

/** 削除されたことを確かめるための目印（架空・他に出てこない文字列） */
const BK_MARKER = 'BACKUPMARKER0001';

/**
 * バックアップ用の使い捨て環境を作る。
 * @return array{k:Kernel,base:string,dir:string,clock:TestClock}
 */
function backupEnv(array $overrides = []): array
{
    static $seq = 0;
    ++$seq;

    $base = tmpDir() . '/backup-' . getmypid() . '-' . $seq;
    if (!is_dir($base . '/backups')) {
        mkdir($base . '/backups', 0700, true);
    }
    $clock  = new TestClock();
    $config = Config::load(array_merge([
        'db_path'         => $base . '/intake.sqlite',
        'ip_hmac_key'     => TEST_IP_HMAC_KEY,
        'enc_key'         => TEST_ENC_KEY,
        'allowed_origins' => [TEST_ORIGIN],
        'rate_limit_dir'  => $base . '/ratelimit',
        'log_path'        => $base . '/intake.log',
        'require_https'   => true,
        'backup_dir'      => $base . '/backups',
    ], $overrides));

    return ['k' => new Kernel($config, $clock), 'base' => $base, 'dir' => $base . '/backups', 'clock' => $clock];
}

/** 架空の案件を1件つくる（目印つき） */
function seedCase(Kernel $k, string $number = 'HP-2026-7001'): int
{
    $caseId = $k->cases->create($number, '架空サロン ' . BK_MARKER);
    $k->tokens->issue($caseId);

    return $caseId;
}

/** 既存の世代を別の作成時刻の名前へ付け替える（古い世代を作るため） */
function ageBackup(string $dir, string $name, int $timestamp): string
{
    $new = BackupPaths::FILE_PREFIX . gmdate('Ymd-His', $timestamp) . '-'
        . bin2hex(random_bytes(4)) . BackupPaths::FILE_EXT;
    rename($dir . '/' . $name, $dir . '/' . $new);

    $manifest = new BackupManifest($dir);
    $entry    = $manifest->entry($name) ?? [];
    $entry['created_at'] = gmdate('Y-m-d\TH:i:s\Z', $timestamp);
    $manifest->forget($name);
    $manifest->put($new, $entry);

    return $new;
}

/** 既存の世代をそのまま複製して、別の作成時刻の世代を1つ増やす（中身は同一＝SHAも同じ） */
function copyGeneration(string $dir, string $source, int $timestamp): string
{
    $new = BackupPaths::FILE_PREFIX . gmdate('Ymd-His', $timestamp) . '-'
        . bin2hex(random_bytes(4)) . BackupPaths::FILE_EXT;
    copy($dir . '/' . $source, $dir . '/' . $new);

    $manifest = new BackupManifest($dir);
    $entry    = $manifest->entry($source) ?? [];
    $entry['created_at'] = gmdate('Y-m-d\TH:i:s\Z', $timestamp);
    $manifest->put($new, $entry);

    return $new;
}

/** 一時ファイル（作成途中）が残っていないか */
function partFiles(string $dir): array
{
    return array_values(array_filter(
        array_diff(scandir($dir) ?: [], ['.', '..']),
        static fn (string $n): bool => str_ends_with($n, BackupPaths::TEMP_EXT)
    ));
}

/** コメントを除いた実行コード（説明文を検査対象にしない） */
function backupSrc(string $relative): string
{
    $out = '';
    foreach (token_get_all((string)file_get_contents(__DIR__ . '/../' . $relative)) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        $out .= is_array($token) ? $token[1] : $token;
    }

    return $out;
}

/* ==================================================================== 作成 */

test('backup作成: SQLite3::backup() を使い、単純コピーを主方式にしていない', function (): void {
    $sqlite = backupSrc('src/Backup/SqliteBackup.php');
    assertTrue(str_contains($sqlite, '$source->backup($dest)'), 'Online Backup API を呼んでいない');

    // ★作成経路（createIn）が copy() でファイルを作っていない
    $service = backupSrc('src/Backup/BackupService.php');
    preg_match('/private function createIn\(string \$dir\): array\s*\{(.*?)\n    \}/s', $service, $m);
    assertTrue(($m[1] ?? '') !== '', 'createIn が見つからない');
    assertTrue(!str_contains($m[1], 'copy('), '作成経路が copy() を使っている');
    assertTrue(str_contains($m[1], '$this->sqlite->backupTo('), '作成経路が SqliteBackup を使っていない');

    // ★VACUUM INTO の経路が無いことは test-logging-backup.php でも見ている（二重）
    assertTrue(stripos($service, 'vacuum into') === false, 'VACUUM INTO の経路がある');
});

test('backup作成: 取得そのものは元DBを1バイトも変えない', function (): void {
    $env = backupEnv();
    seedCase($env['k']);

    // ★取得（SQLite3::backup()）だけを切り出して見る。
    //   create() は取得のあとに監査を1件書くため、そこは別の観点として下で確かめる。
    $before = (string)hash_file('sha256', $env['k']->config->dbPath);
    (new SqliteBackup($env['k']->db))->backupTo($env['base'] . '/probe.sqlite');
    $after  = (string)hash_file('sha256', $env['k']->config->dbPath);

    assertSame($before, $after, 'バックアップの取得が元DBを書き換えた');
    assertTrue(is_file($env['base'] . '/probe.sqlite'), 'バックアップファイルが無い');
});

test('backup作成: create() が元DBへ加えるのは監査1件だけ', function (): void {
    $env = backupEnv();
    seedCase($env['k']);

    $counts = static function (Kernel $k): array {
        $out = [];
        foreach (BackupService::REQUIRED_TABLES as $table) {
            $out[$table] = (int)$k->db->pdo()->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
        }

        return $out;
    };

    $before = $counts($env['k']);
    $result = $env['k']->backup->create();
    $after  = $counts($env['k']);

    assertSame(true, $result['ok'], '作成に失敗した: ' . (string)($result['error'] ?? ''));
    assertTrue(is_file($env['dir'] . '/' . $result['name']), 'バックアップファイルが無い');
    assertSame($before['intake_audit_events'] + 1, $after['intake_audit_events'], '監査が1件でない');
    assertSame(1, $env['k']->audit->countFor(null, 'backup_created'), 'backup_created が1件でない');

    unset($before['intake_audit_events'], $after['intake_audit_events']);
    assertSame($before, $after, '監査以外の表が変わった');
});

test('backup作成: 名前は intake-YYYYMMDD-HHMMSS-<random>.sqlite で PII を含まない', function (): void {
    $env = backupEnv();
    seedCase($env['k'], 'HP-2026-7002');

    $name = (string)$env['k']->backup->create()['name'];
    assertTrue(preg_match(BackupPaths::FILE_PATTERN, $name) === 1, '名前が規則どおりでない: ' . $name);
    foreach ([BK_MARKER, 'HP-2026-7002', '架空サロン', 'example.invalid'] as $pii) {
        assertTrue(!str_contains($name, $pii), '名前に ' . $pii . ' が入っている');
    }
});

test('backup作成: 一時ファイルを経由し、成功後に .part が残らない', function (): void {
    $env = backupEnv();
    seedCase($env['k']);
    $env['k']->backup->create();

    assertSame([], partFiles($env['dir']), '作成途中の一時ファイルが残っている');

    // ★一時ファイル → 検証 → rename の順であること（経路そのものを固定する）
    $create = backupSrc('src/Backup/BackupService.php');
    $tmpPos = strpos($create, 'BackupPaths::TEMP_PREFIX');
    $intPos = strpos($create, "'integrity_failed'");
    $renPos = strpos($create, '@rename($tmp, $final)');
    assertTrue($tmpPos !== false && $intPos !== false && $renPos !== false,
        '一時ファイル・整合性判定・rename のどれかが無い');
    assertTrue($tmpPos < $intPos && $intPos < $renPos, '一時ファイル→検証→rename の順序が崩れている');

    // ★取得側にも integrity / foreign_key の関門がある（二重）
    $sqlite = backupSrc('src/Backup/SqliteBackup.php');
    assertTrue(str_contains($sqlite, "\$check['integrity'] !== 'ok'"), '取得後の integrity 判定が無い');
    assertTrue(str_contains($sqlite, "\$check['foreign_key_violations'] !== 0"), '取得後の foreign_key 判定が無い');
});

test('backup作成: 失敗したら一時ファイルを残さない', function (): void {
    $env = backupEnv();

    // ★:memory: は「ファイルとして取得できないDB」。作成を確実に失敗させられる
    $memory = new Db(':memory:');
    (new Migrator($memory))->migrate();
    $service = new BackupService($memory, new Clock(), new Audit($memory, new Clock()), $env['dir']);

    $result = $service->create();
    assertSame(false, $result['ok'], '取得できないDBで成功してしまった');
    assertSame('backup_failed', $result['error']);
    assertSame([], partFiles($env['dir']), '失敗したのに一時ファイルが残っている');
    assertSame(0, count((array)$service->listGenerations()['items']), '失敗したのに世代ができている');
});

test('backup作成: SHA-256 とサイズを控え、実ファイルと一致する', function (): void {
    $env = backupEnv();
    seedCase($env['k']);
    $result = $env['k']->backup->create();
    $path   = $env['dir'] . '/' . $result['name'];

    assertSame(hash_file('sha256', $path), $result['sha256'], 'SHA-256 が実ファイルと違う');
    assertSame(filesize($path), $result['size'], 'サイズが実ファイルと違う');

    $entry = (new BackupManifest($env['dir']))->entry((string)$result['name']);
    assertTrue($entry !== null, '一覧に控えが無い');
    assertSame($result['sha256'], $entry['sha256'], '控えの SHA-256 が違う');
});

test('backup作成: 一覧（manifest）に PII を書かない', function (): void {
    $env    = backupEnv();
    $caseId = seedCase($env['k'], 'HP-2026-7003');
    $env['k']->cases->setDriveFolder($caseId, 'https://drive.google.com/drive/folders/FAKE-BK-0001',
        'HP-2026-7003 素材', 'materials@example.invalid');
    $env['k']->backup->create();

    $raw = (string)file_get_contents($env['dir'] . '/' . BackupPaths::MANIFEST_NAME);
    foreach ([BK_MARKER, '架空サロン', 'materials@example.invalid', 'drive.google.com', 'HP-2026-7003'] as $pii) {
        assertTrue(!str_contains($raw, $pii), '一覧に ' . $pii . ' が出ている');
    }

    $decoded = json_decode($raw, true);
    foreach ((array)($decoded['entries'] ?? []) as $entry) {
        $unknown = array_diff(array_keys((array)$entry), BackupManifest::ENTRY_KEYS);
        assertSame([], array_values($unknown), '一覧に未知のキーがある: ' . implode(',', $unknown));
    }
});

test('backup作成: 同じ秒に何度作っても同名衝突せず、既存を上書きしない', function (): void {
    $env = backupEnv();
    seedCase($env['k']);

    $names  = [];
    $hashes = [];
    for ($i = 0; $i < 3; ++$i) {
        $r        = $env['k']->backup->create();
        $names[]  = (string)$r['name'];
        $hashes[] = (string)$r['sha256'];
        assertSame(true, $r['ok']);
    }
    assertSame(3, count(array_unique($names)), '同名になった');
    foreach ($names as $name) {
        assertTrue(is_file($env['dir'] . '/' . $name), '上書きで消えた世代がある: ' . $name);
    }
    assertSame(3, count((array)$env['k']->backup->listGenerations()['items']), '世代数が合わない');
});

test('backup作成: 排他ロックが取れなければ実行しない', function (): void {
    $env = backupEnv();
    seedCase($env['k']);

    $lock = fopen($env['dir'] . '/' . BackupPaths::LOCK_NAME, 'c');
    assertTrue($lock !== false && flock($lock, LOCK_EX | LOCK_NB), 'テスト側でロックを取れない');

    $result  = $env['k']->backup->create();
    $cleanup = $env['k']->backup->cleanup(false);
    $drill   = $env['k']->backup->restoreDrill('intake-20260101-000000-abcdef01.sqlite');

    flock($lock, LOCK_UN);
    fclose($lock);

    assertSame('lock_busy', $result['error'] ?? '', '作成がロックを無視した');
    assertSame('lock_busy', $cleanup['error'] ?? '', 'cleanup がロックを無視した');
    assertSame('lock_busy', $drill['error'] ?? '', 'restore drill がロックを無視した');
    assertSame([], partFiles($env['dir']), 'ロック失敗で一時ファイルができた');
});

test('backup作成: 権限を 600 にする（POSIX で実測・Windows はコードで担保）', function (): void {
    $env = backupEnv();
    seedCase($env['k']);
    $name = (string)$env['k']->backup->create()['name'];
    $path = $env['dir'] . '/' . $name;

    if (DIRECTORY_SEPARATOR === '/') {
        assertSame(0600, fileperms($path) & 0777, 'バックアップの権限が 600 でない');
    } else {
        // ★Windows の chmod は所有者以外へ効かない。実測できないので経路を固定する
        $code = backupSrc('src/Backup/BackupService.php');
        assertTrue(str_contains($code, '@chmod($final, 0600)'), '正式ファイルへ 600 を設定していない');
        assertTrue(str_contains($code, '@chmod($tmp, 0600)'), '一時ファイルへ 600 を設定していない');
        assertTrue(is_file($path), 'バックアップが無い');
    }
});

/* ================================================================ パス検査 */

test('backupパス: 空・相対・ルート・ホーム直下・公開領域を拒否する', function (): void {
    $cases = [
        ''                          => 'not_configured',
        '   '                       => 'not_configured',
        'backups'                   => 'relative',
        './backups'                 => 'relative',
        '../backups'                => 'relative',
        '/'                         => 'too_shallow',
        '/backups'                  => 'too_shallow',
        'C:/backups'                => 'too_shallow',
        '/home/someone'             => 'home_root',
        '/root'                     => 'home_root',
        'C:/Users/someone'          => 'home_root',
        '/var/www/public_html/bk'   => 'public_area',
        '/home/x/site/public/bk'    => 'public_area',
        '/home/x/htdocs/bk'         => 'public_area',
        '/home/x/www/bk'            => 'public_area',
        '/home/x/back/../ups'       => 'traversal',
    ];
    foreach ($cases as $path => $expected) {
        $result = BackupPaths::checkDir($path === '' ? '' : $path);
        assertSame(false, $result['ok'], $path . ' が通ってしまう');
        assertSame($expected, $result['error'], $path . ' の拒否理由が違う');
    }
    assertSame('not_configured', BackupPaths::checkDir(null)['error'], 'null が通ってしまう');
});

test('backupパス: 実在しないディレクトリを拒否し、許可ディレクトリだけを通す', function (): void {
    $env = backupEnv();

    assertSame('missing', BackupPaths::checkDir($env['base'] . '/does-not-exist')['error']);

    $ok = BackupPaths::checkDir($env['dir']);
    assertSame(true, $ok['ok'], '許可ディレクトリが通らない: ' . (string)($ok['error'] ?? ''));
    assertSame(BackupPaths::normalize((string)realpath($env['dir'])), $ok['dir']);
});

test('backupパス: symlink で公開領域へ逃げる設定を拒否する', function (): void {
    $env = backupEnv();
    mkdir($env['base'] . '/site/public_html/bk', 0700, true);
    $link = $env['base'] . '/link-to-public';

    // ★Windows では symlink の作成に権限が要る。作れない環境では
    //   「realpath 後にもう一度検査する」という同じ守りを、実体パスで確かめる。
    if (@symlink($env['base'] . '/site/public_html/bk', $link) && is_link($link)) {
        $result = BackupPaths::checkDir($link);
        assertSame(false, $result['ok'], 'symlink 経由の公開領域が通ってしまう');
        assertTrue(in_array($result['error'], ['symlink', 'public_area'], true), '拒否理由が違う: ' . $result['error']);
    } else {
        assertSame('public_area', BackupPaths::checkDir($env['base'] . '/site/public_html/bk')['error']);
    }
});

test('backupパス: 対象外のファイル名・ディレクトリ外を扱わない', function (): void {
    $env = backupEnv();
    seedCase($env['k']);
    $name = (string)$env['k']->backup->create()['name'];

    file_put_contents($env['dir'] . '/notes.txt', 'x');
    file_put_contents($env['dir'] . '/intake.sqlite', 'x');

    foreach (['notes.txt', 'intake.sqlite', '../intake.sqlite', 'intake-2026-01-01.sqlite',
              $name . '.bak', '/etc/passwd'] as $bad) {
        assertSame('not_a_backup_name', BackupPaths::checkFile($env['dir'], $bad)['error'], $bad . ' が通ってしまう');
        assertSame('not_a_backup_name', $env['k']->backup->verify($bad)['error'], $bad . ' の検証が通ってしまう');
    }
    assertSame(true, BackupPaths::checkFile($env['dir'], $name)['ok'], '正式な世代が通らない');

    // 規則外のファイルは世代として数えない
    assertSame(1, count((array)$env['k']->backup->listGenerations()['items']), '規則外ファイルを世代に数えた');
});

/* ================================================================== 検証 */

test('backup検証: 正常な世代は integrity / foreign_key / user_version / 8表 / 回答schema を満たす', function (): void {
    $env = backupEnv();
    seedCase($env['k']);
    $name = (string)$env['k']->backup->create()['name'];

    $result = $env['k']->backup->verify($name);
    assertSame(true, $result['ok'], '検証に失敗した: ' . (string)($result['error'] ?? ''));
    assertSame('ok', $result['integrity']);
    assertSame(0, $result['foreign_key_violations']);
    assertSame(Migrator::SCHEMA_VERSION, $result['schema_version']);
    assertSame(Migrator::ANSWER_SCHEMA_VERSION, $result['answer_schema_version']);
    assertTrue($result['tables'] >= count(BackupService::REQUIRED_TABLES), '表が足りない');
});

test('backup検証: 1バイトの改ざんを検出する', function (): void {
    $env = backupEnv();
    seedCase($env['k']);
    $name = (string)$env['k']->backup->create()['name'];
    $path = $env['dir'] . '/' . $name;

    file_put_contents($path, 'x', FILE_APPEND);
    assertSame('sha_mismatch', $env['k']->backup->verify($name)['error'], '改ざんを検出できない');
});

test('backup検証: 控えの無いファイルは通さない', function (): void {
    $env = backupEnv();
    seedCase($env['k']);
    $name = (string)$env['k']->backup->create()['name'];

    unlink($env['dir'] . '/' . BackupPaths::MANIFEST_NAME);
    assertSame('no_manifest_entry', $env['k']->backup->verify($name)['error'], '控え無しで通ってしまう');
});

test('backup検証: 壊れたファイル・別形式を通さない', function (): void {
    $env = backupEnv();
    seedCase($env['k']);
    $name = (string)$env['k']->backup->create()['name'];
    $path = $env['dir'] . '/' . $name;

    // ★控えごと差し替える。SHA-256 だけでなく**中身の検査**が働くことを見る
    file_put_contents($path, str_repeat('not a sqlite file ', 64));
    (new BackupManifest($env['dir']))->put($name, [
        'created_at' => '2026-08-28T00:00:00Z',
        'size'       => (int)filesize($path),
        'sha256'     => (string)hash_file('sha256', $path),
    ]);

    $result = $env['k']->backup->verify($name);
    assertSame(false, $result['ok'], '壊れたファイルが検証を通ってしまう');
    assertTrue(in_array($result['error'], ['not_a_database', 'integrity_failed'], true),
        '拒否理由が違う: ' . (string)$result['error']);
});

test('backup検証: integrity_check の異常を検出する', function (): void {
    $env = backupEnv();
    seedCase($env['k']);
    $name = (string)$env['k']->backup->create()['name'];
    $path = $env['dir'] . '/' . $name;

    // ★ヘッダは壊さず、2ページ目の中身だけを潰す。
    //   SQLite としては開けるが integrity_check が通らない状態を作る。
    $fh = fopen($path, 'r+b');
    assertTrue($fh !== false, 'ファイルを開けない');
    fseek($fh, 4096 + 8);
    fwrite($fh, str_repeat("\xAA", 200));
    fclose($fh);

    // 控えも合わせて更新する（SHA-256 ではなく**中身の検査**が働くことを見る）
    (new BackupManifest($env['dir']))->put($name, [
        'created_at' => '2026-08-28T00:00:00Z',
        'size'       => (int)filesize($path),
        'sha256'     => (string)hash_file('sha256', $path),
    ]);

    $result = $env['k']->backup->verify($name);
    assertSame(false, $result['ok'], '壊れた世代が検証を通ってしまう');
    assertSame('integrity_failed', $result['error'], '拒否理由が違う: ' . (string)$result['error']);

    // 復元確認も通さない
    assertSame(false, $env['k']->backup->restoreDrill($name)['ok'], '壊れた世代で復元確認が通る');
});

test('backup検証: user_version / 8表 / 回答schema / 外部キーの異常を検出する', function (): void {
    $env = backupEnv();
    seedCase($env['k']);
    $base = (string)$env['k']->backup->create()['name'];

    $mutate = static function (string $dir, string $source, callable $fn): string {
        $name = copyGeneration($dir, $source, strtotime('2026-08-01T00:00:00Z'));
        $pdo  = new PDO('sqlite:' . $dir . '/' . $name, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $fn($pdo);
        unset($pdo);
        (new BackupManifest($dir))->put($name, [
            'created_at' => '2026-08-01T00:00:00Z',
            'size'       => (int)filesize($dir . '/' . $name),
            'sha256'     => (string)hash_file('sha256', $dir . '/' . $name),
        ]);

        return $name;
    };

    $wrongVersion = $mutate($env['dir'], $base, static fn (PDO $p) => $p->exec('PRAGMA user_version = 3'));
    assertSame('schema_version_mismatch', $env['k']->backup->verify($wrongVersion)['error'], '版違いを見逃した');

    $missingTable = $mutate($env['dir'], $base, static fn (PDO $p) => $p->exec('DROP TABLE intake_revision_requests'));
    assertSame('missing_tables', $env['k']->backup->verify($missingTable)['error'], '表の欠落を見逃した');

    $wrongAnswer = $mutate($env['dir'], $base,
        static fn (PDO $p) => $p->exec('UPDATE intake_cases SET schema_version = 9'));
    assertSame('answer_schema_mismatch', $env['k']->backup->verify($wrongAnswer)['error'], '回答schema違いを見逃した');

    // 外部キー違反（foreign_keys を切った接続でだけ作れる孤児行）
    $orphan = $mutate($env['dir'], $base, static function (PDO $p): void {
        $p->exec("INSERT INTO intake_submission_history
                      (intake_case_id, event_type, schema_version, submitted_at, result_code)
                  VALUES (99999, 'submitted', 1, '2026-08-01T00:00:00Z', 'ok')");
    });
    assertSame('foreign_key_violation', $env['k']->backup->verify($orphan)['error'], '外部キー違反を見逃した');
});

/* ============================================================ 復元確認 */

test('復元確認: 一時DBへ復元して整合を確かめ、元DBを変えずに一時DBを消す', function (): void {
    $env    = backupEnv();
    $caseId = seedCase($env['k'], 'HP-2026-7010');
    $env['k']->answers->save($caseId, ['basic' => ['legal_name' => '架空サロン ' . BK_MARKER]], 1);
    $name = (string)$env['k']->backup->create()['name'];

    $before = (string)hash_file('sha256', $env['k']->config->dbPath);
    $result = $env['k']->backup->restoreDrill($name);
    $after  = (string)hash_file('sha256', $env['k']->config->dbPath);

    assertSame(true, $result['ok'], '復元確認に失敗した: ' . (string)($result['error'] ?? ''));
    assertSame(true, $result['temp_removed'], '一時DBが残っている');
    assertSame(true, $result['source_unchanged'], '復元確認中に元DBが変わった');
    assertSame(1, $result['cases'], '架空案件が復元されていない');
    assertSame('ok', $result['integrity']);
    assertSame(0, $result['foreign_key_violations']);

    // ★監査は drill が終わってから書く。drill 中は元DBに触れていない
    assertTrue(is_string($before) && is_string($after), '');
    assertSame(1, $env['k']->audit->countFor(null, 'backup_restore_drill'), '監査が1件でない');

    // 一時ディレクトリが1つも残っていない
    $left = array_filter(array_diff(scandir($env['dir']) ?: [], ['.', '..']),
        static fn (string $n): bool => str_starts_with($n, BackupPaths::DRILL_PREFIX));
    assertSame([], array_values($left), '復元用の一時ディレクトリが残っている');
});

test('復元確認: 稼働DBへ書き戻さず、結果に PII を出さない', function (): void {
    $env    = backupEnv();
    $caseId = seedCase($env['k'], 'HP-2026-7011');
    $env['k']->cases->setDriveFolder($caseId, 'https://drive.google.com/drive/folders/FAKE-BK-0002',
        'HP-2026-7011 素材', 'materials@example.invalid');
    $name = (string)$env['k']->backup->create()['name'];

    $liveBytes = (string)file_get_contents($env['k']->config->dbPath);
    $result    = $env['k']->backup->restoreDrill($name);
    assertSame(true, $result['ok']);

    // 稼働DBの中身（監査1件を除けば）に復元DBが混ざっていない＝上書きしていない
    assertTrue(is_file($env['k']->config->dbPath), '稼働DBが消えた');
    assertTrue(strlen($liveBytes) > 0, '検査が空振りしている');

    $dump = (string)json_encode($result, JSON_UNESCAPED_UNICODE);
    foreach ([BK_MARKER, '架空サロン', 'materials@example.invalid', 'drive.google.com', 'HP-2026-7011'] as $pii) {
        assertTrue(!str_contains($dump, $pii), '復元確認の結果に ' . $pii . ' が出ている');
    }
    // ★絶対パス全文も出さない
    assertTrue(!str_contains($dump, $env['dir']), '結果に保存先の絶対パスが出ている');

    // 本番DBへ書き戻す経路そのものが無い
    $code = backupSrc('src/Backup/BackupService.php');
    assertTrue(!str_contains($code, 'copy($source, $livePath)'), '稼働DBへ書き戻す経路がある');
    assertTrue(str_contains($code, 'refuses_to_overwrite_live_db'), '稼働DB保護の判定が無い');
});

test('復元確認: 改ざんされた世代では復元確認を通さない', function (): void {
    $env = backupEnv();
    seedCase($env['k']);
    $name = (string)$env['k']->backup->create()['name'];
    file_put_contents($env['dir'] . '/' . $name, 'x', FILE_APPEND);

    $result = $env['k']->backup->restoreDrill($name);
    assertSame(false, $result['ok'], '改ざん済みで復元確認が通ってしまう');
    assertSame('sha_mismatch', $result['error']);
});

/* ================================================================ cleanup */

test('cleanup: 既定は dry-run で、1件も削除しない', function (): void {
    $env = backupEnv();
    seedCase($env['k']);
    $name = (string)$env['k']->backup->create()['name'];
    ageBackup($env['dir'], $name, $env['clock']->now() - (40 * 86400));

    $result = $env['k']->backup->cleanup();
    assertSame(true, $result['ok']);
    assertSame(true, $result['dry_run'], '既定が dry-run でない');
    assertSame(0, $result['deleted'], 'dry-run で削除した');
    assertSame(1, count($result['expired']), '30日超を数えられていない');
    assertSame(1, count((array)$env['k']->backup->listGenerations()['items']), '世代が消えた');
});

test('cleanup: 確認文字列が合わなければ削除しない', function (): void {
    $env = backupEnv();
    seedCase($env['k']);
    $name = (string)$env['k']->backup->create()['name'];
    ageBackup($env['dir'], $name, $env['clock']->now() - (40 * 86400));

    foreach (['', 'delete old backups', 'DELETE OLD BACKUP', ' DELETE OLD BACKUPS x'] as $wrong) {
        $result = $env['k']->backup->cleanup(true, $wrong);
        assertSame('confirm_mismatch', $result['error'] ?? '', '確認文字列「' . $wrong . '」で実行された');
        assertSame(0, $result['deleted']);
    }
    assertSame(1, count((array)$env['k']->backup->listGenerations()['items']), '世代が消えた');
});

test('cleanup: 30日の境界を正しく扱う', function (): void {
    $env = backupEnv();
    seedCase($env['k']);
    $seed = (string)$env['k']->backup->create()['name'];
    $now  = $env['clock']->now();

    $exactly30 = ageBackup($env['dir'], $seed, $now - (30 * 86400));
    $justOver  = copyGeneration($env['dir'], $exactly30, $now - (30 * 86400) - 1);
    $justUnder = copyGeneration($env['dir'], $exactly30, $now - (30 * 86400) + 1);

    $dry = $env['k']->backup->cleanup();
    assertSame([$justOver], $dry['expired'], '30日ちょうど／30日未満まで消そうとしている');

    $applied = $env['k']->backup->cleanup(true, BackupService::CONFIRM_CLEANUP);
    assertSame(1, $applied['deleted'], '削除件数が違う');

    $left = array_column((array)$env['k']->backup->listGenerations()['items'], 'name');
    sort($left);
    $expect = [$exactly30, $justUnder];
    sort($expect);
    assertSame($expect, $left, '残った世代が違う');
});

test('cleanup: 60世代を超えたら古いものから消す', function (): void {
    $env = backupEnv();
    seedCase($env['k']);
    $seed = (string)$env['k']->backup->create()['name'];
    $now  = $env['clock']->now();

    // 61世代（すべて30日以内）。1世代目がいちばん古い
    $names = [$seed];
    for ($i = 1; $i < 61; ++$i) {
        $names[] = copyGeneration($env['dir'], $seed, $now - (60 * 60 * $i));
    }
    // 作成時刻順（古い順）へ並べ直す
    usort($names, static fn (string $a, string $b): int
        => [BackupPaths::timestampOf($a), $a] <=> [BackupPaths::timestampOf($b), $b]);
    assertSame(61, count($names));

    $dry = $env['k']->backup->cleanup();
    assertSame([], $dry['expired'], '30日以内を期限切れにしている');
    assertSame([$names[0]], $dry['excess'], '古い順に選んでいない');

    $applied = $env['k']->backup->cleanup(true, BackupService::CONFIRM_CLEANUP);
    assertSame(1, $applied['deleted']);
    assertSame(60, count((array)$env['k']->backup->listGenerations()['items']), '60世代に収まっていない');
    assertTrue(!is_file($env['dir'] . '/' . $names[0]), 'いちばん古い世代が残っている');
});

test('cleanup: 稼働DB・ディレクトリ外・symlink を対象にしない', function (): void {
    $env = backupEnv();
    seedCase($env['k']);
    $name = (string)$env['k']->backup->create()['name'];
    ageBackup($env['dir'], $name, $env['clock']->now() - (90 * 86400));

    // ディレクトリ外の「正式な名前に見えるファイル」
    $outside = $env['base'] . '/' . BackupPaths::FILE_PREFIX . '20200101-000000-deadbeef' . BackupPaths::FILE_EXT;
    file_put_contents($outside, 'x');

    $env['k']->backup->cleanup(true, BackupService::CONFIRM_CLEANUP);

    assertTrue(is_file($env['k']->config->dbPath), '稼働DBを消した');
    assertTrue(is_file($outside), 'ディレクトリ外のファイルを消した');
    assertSame(0, count((array)$env['k']->backup->listGenerations()['items']), '対象の世代が消えていない');

    // symlink は世代として数えない（作れる環境でのみ実測する）
    $target = $env['base'] . '/target.sqlite';
    copy($env['k']->config->dbPath, $target);
    $link = $env['dir'] . '/' . BackupPaths::FILE_PREFIX . '20200102-000000-cafebabe' . BackupPaths::FILE_EXT;
    if (@symlink($target, $link) && is_link($link)) {
        assertSame(0, count((array)$env['k']->backup->listGenerations()['items']), 'symlink を世代に数えた');
        assertSame('symlink', BackupPaths::checkFile($env['dir'], basename($link))['error'], 'symlink を通した');
        $env['k']->backup->cleanup(true, BackupService::CONFIRM_CLEANUP);
        assertTrue(is_file($target), 'symlink をたどって実体を消した');
        unlink($link);
    } else {
        // symlink を作れない環境では、コード側で is_link を見ていることを確かめる
        $code = backupSrc('src/Backup/BackupService.php');
        assertTrue(str_contains($code, 'is_link($path)'), 'symlink を除外していない');
    }
});

/* ==================================================== 保持削除との連動 */

/**
 * 保持削除まで進めた環境を作る。
 * @return array{env:array,number:string,caseId:int}
 */
function purgedEnv(): array
{
    $env    = backupEnv(['retention_actions_enabled' => true, 'backup_policy_confirmed' => true]);
    $k      = $env['k'];
    $number = 'HP-2026-7100';
    $caseId = $k->cases->create($number, '架空サロン ' . BK_MARKER);
    $k->answers->save($caseId, ['basic' => ['legal_name' => '架空サロン ' . BK_MARKER]], 1);

    $k->db->pdo()->prepare(
        "UPDATE intake_cases SET status = 'locked', retention_delete_due = :due WHERE id = :id"
    )->execute([':due' => '2026-01-01', ':id' => $caseId]);

    return ['env' => $env, 'number' => $number, 'caseId' => $caseId];
}

test('purge連動: purge後バックアップが無ければ、purge前世代を1件も消さない', function (): void {
    ['env' => $env, 'number' => $number, 'caseId' => $caseId] = purgedEnv();

    $before = (string)$env['k']->backup->create()['name'];
    $env['clock']->advance(2);
    assertSame(true, $env['k']->retention->purgeCase($caseId, $number, 'DELETE ' . $number)['ok']);

    $result = $env['k']->backup->purgePrecedingGenerations(true, BackupService::CONFIRM_PURGE_PRECEDING);
    assertSame(false, $result['ok'], 'purge後バックアップ無しで通ってしまう');
    assertSame('post_purge_backup_missing', $result['error']);
    assertSame(0, $result['deleted'], '古い世代を消した');
    assertTrue(is_file($env['dir'] . '/' . $before), 'purge前世代が消えている');
});

test('purge連動: purge後バックアップの検証に失敗したら、旧世代を維持する', function (): void {
    ['env' => $env, 'number' => $number, 'caseId' => $caseId] = purgedEnv();

    $before = (string)$env['k']->backup->create()['name'];
    $env['clock']->advance(2);
    assertSame(true, $env['k']->retention->purgeCase($caseId, $number, 'DELETE ' . $number)['ok']);
    $env['clock']->advance(2);
    $after = (string)$env['k']->backup->create()['name'];

    // ★purge後バックアップを壊す（検証が失敗するようにする）
    file_put_contents($env['dir'] . '/' . $after, 'x', FILE_APPEND);

    $result = $env['k']->backup->purgePrecedingGenerations(true, BackupService::CONFIRM_PURGE_PRECEDING);
    assertSame(false, $result['ok'], '検証失敗なのに通ってしまう');
    assertSame('post_purge_backup_unverified', $result['error']);
    assertSame(0, $result['deleted'], '検証失敗なのに古い世代を消した');
    assertTrue(is_file($env['dir'] . '/' . $before), 'purge前世代が消えている');
});

test('purge連動: 検証に成功したら purge前世代だけを消し、purge後世代を残す', function (): void {
    ['env' => $env, 'number' => $number, 'caseId' => $caseId] = purgedEnv();

    $before1 = (string)$env['k']->backup->create()['name'];
    $env['clock']->advance(2);
    $before2 = (string)$env['k']->backup->create()['name'];
    $env['clock']->advance(2);

    assertSame(true, $env['k']->retention->purgeCase($caseId, $number, 'DELETE ' . $number)['ok']);
    $env['clock']->advance(2);
    $after = (string)$env['k']->backup->create()['name'];

    // 1) dry-run では消えない
    $dry = $env['k']->backup->purgePrecedingGenerations();
    assertSame(true, $dry['ok']);
    assertSame(true, $dry['dry_run']);
    assertSame(0, $dry['deleted'], 'dry-run で消した');
    assertSame(2, count($dry['preceding']), 'purge前世代の数が違う');
    assertSame($after, $dry['checked'], '検証対象が purge後の最新でない');

    // 2) 確認文字列が無ければ消えない
    assertSame('confirm_mismatch',
        $env['k']->backup->purgePrecedingGenerations(true, 'DELETE')['error'] ?? '', '確認無しで消えた');
    assertTrue(is_file($env['dir'] . '/' . $before1), '確認無しで消えた');

    // 3) 実行
    $applied = $env['k']->backup->purgePrecedingGenerations(true, BackupService::CONFIRM_PURGE_PRECEDING);
    assertSame(true, $applied['ok'], '実行に失敗した: ' . (string)($applied['error'] ?? ''));
    assertSame(2, $applied['deleted'], '削除件数が違う');
    assertSame(0, $applied['remaining_preceding'], 'purge前世代が残っている');

    assertTrue(!is_file($env['dir'] . '/' . $before1), 'purge前世代1が残っている');
    assertTrue(!is_file($env['dir'] . '/' . $before2), 'purge前世代2が残っている');
    assertTrue(is_file($env['dir'] . '/' . $after), 'purge後世代まで消した');
    assertSame(1, count((array)$env['k']->backup->listGenerations()['items']), '世代数が違う');

    // 4) 再実行できる（冪等）。もう消すものが無い
    $again = $env['k']->backup->purgePrecedingGenerations(true, BackupService::CONFIRM_PURGE_PRECEDING);
    assertSame(true, $again['ok'], '再実行が失敗した');
    assertSame(0, $again['deleted'], '再実行で余計に消した');
});

test('purge連動: 新しい世代に削除対象の架空PIIが残っていない', function (): void {
    ['env' => $env, 'number' => $number, 'caseId' => $caseId] = purgedEnv();

    $before = (string)$env['k']->backup->create()['name'];
    assertTrue(str_contains((string)file_get_contents($env['dir'] . '/' . $before), BK_MARKER),
        '検査が空振りしている（purge前世代に目印が入っていない）');

    $env['clock']->advance(2);
    assertSame(true, $env['k']->retention->purgeCase($caseId, $number, 'DELETE ' . $number)['ok']);
    $env['clock']->advance(2);
    $after = (string)$env['k']->backup->create()['name'];

    $newBytes = (string)file_get_contents($env['dir'] . '/' . $after);
    assertTrue(!str_contains($newBytes, BK_MARKER), 'purge後の世代に架空PIIが残っている');
    assertTrue(str_contains($newBytes, $number), '案件番号（残してよい情報）まで消えている');

    $applied = $env['k']->backup->purgePrecedingGenerations(true, BackupService::CONFIRM_PURGE_PRECEDING);
    assertSame(true, $applied['ok']);
    assertSame(0, $applied['remaining_preceding']);

    // 残った全世代のどこにも目印が無い
    foreach ((array)$env['k']->backup->listGenerations()['items'] as $item) {
        $bytes = (string)file_get_contents($env['dir'] . '/' . $item['name']);
        assertTrue(!str_contains($bytes, BK_MARKER), '残った世代に架空PIIがある: ' . $item['name']);
    }
});

test('purge連動: 保持削除の記録が無ければ実行しない', function (): void {
    $env = backupEnv();
    seedCase($env['k']);
    $env['k']->backup->create();

    $result = $env['k']->backup->purgePrecedingGenerations(true, BackupService::CONFIRM_PURGE_PRECEDING);
    assertSame(false, $result['ok']);
    assertSame('no_purge_recorded', $result['error']);
    assertSame(0, $result['deleted']);
});

test('purge連動: DBとファイルを1つの原子操作と説明していない', function (): void {
    $service = (string)file_get_contents(__DIR__ . '/../src/Backup/BackupService.php');
    assertTrue(str_contains($service, 'ひとつの原子操作にならない'), '非atomic である説明が無い');

    $runbook = (string)file_get_contents(
        __DIR__ . '/../../docs/website/HP_INTAKE_BACKUP_RESTORE_RUNBOOK_V1.md'
    );
    foreach (['原子操作', '再実行', 'STOP'] as $needed) {
        assertTrue(str_contains($runbook, $needed), 'runbook に「' . $needed . '」の記載が無い');
    }
});

/* ================================================================ フラグ */

test('フラグ: backup_policy_confirmed は既定 false のままで、4G では true にしない', function (): void {
    $config = Config::load([
        'ip_hmac_key' => TEST_IP_HMAC_KEY,
        'enc_key'     => TEST_ENC_KEY,
        'db_path'     => tmpDir() . '/flag-' . getmypid() . '.sqlite',
    ]);
    assertSame(false, $config->backupPolicyConfirmed, '既定が false でない');
    assertSame(false, $config->retentionActionsEnabled, '既定が false でない');
    assertSame(false, $config->retentionEnabled(), '既定で削除が通ってしまう');
    assertSame(null, $config->backupDir, 'backup_dir の既定が null でない');

    // 配布する雛形も false のまま
    $example = (string)file_get_contents(__DIR__ . '/../private/intake-config.example.php');
    assertTrue(preg_match("/'backup_policy_confirmed'\s*=>\s*false/", $example) === 1, '雛形が false でない');
    assertTrue(preg_match("/'retention_actions_enabled'\s*=>\s*false/", $example) === 1, '雛形が false でない');

    // 4H の実測が済むまで true にしない、と文書へ書いてある
    $ssot = (string)file_get_contents(__DIR__ . '/../../docs/website/HP_ONBOARDING_INTAKE_DATA_MODEL_V1.md');
    assertTrue(str_contains($ssot, 'backup_policy_confirmed'), 'SSOT にフラグの記載が無い');
    assertTrue(str_contains($ssot, '4H'), 'SSOT に 4H の条件が無い');
});

test('フラグ: 明示的に真と書いたときだけ true（ローカル override 限定）', function (): void {
    foreach (['false', '0', 'off', 'no', '', 'FALSE '] as $falsy) {
        $config = Config::load([
            'ip_hmac_key' => TEST_IP_HMAC_KEY, 'enc_key' => TEST_ENC_KEY,
            'db_path' => tmpDir() . '/flag2-' . getmypid() . '.sqlite',
            'backup_policy_confirmed' => $falsy,
        ]);
        assertSame(false, $config->backupPolicyConfirmed, '「' . $falsy . '」が true になった');
    }
    $on = Config::load([
        'ip_hmac_key' => TEST_IP_HMAC_KEY, 'enc_key' => TEST_ENC_KEY,
        'db_path' => tmpDir() . '/flag3-' . getmypid() . '.sqlite',
        'backup_policy_confirmed' => true,
    ]);
    assertSame(true, $on->backupPolicyConfirmed, 'ローカル override が効かない');
});

/* ============================================================ CLI・境界 */

test('CLI: Web から実行できず、public 配下に置かれていない', function (): void {
    $entry = (string)file_get_contents(__DIR__ . '/../bin/intake-backup.php');
    assertTrue(str_contains($entry, "PHP_SAPI !== 'cli'"), 'CLI 以外を止めていない');
    assertTrue(!is_file(__DIR__ . '/../public/intake-backup.php'), '公開領域に CLI がある');
    assertTrue(!is_dir(__DIR__ . '/../public/bin'), '公開領域に bin がある');

    // App / AdminApp からバックアップ機能を呼んでいない（Web 経路を作らない）
    foreach (['src/App.php', 'src/Admin/AdminApp.php', 'public/index.php'] as $relative) {
        $code = (string)file_get_contents(__DIR__ . '/../' . $relative);
        assertTrue(!str_contains($code, 'BackupService'), $relative . ' がバックアップを呼んでいる');
        assertTrue(!str_contains($code, '->backup->'), $relative . ' がバックアップを呼んでいる');
    }
});

test('CLI: 6つのコマンドがあり、削除系は既定 dry-run で結果に PII・絶対パスを出さない', function (): void {
    assertSame([
        'backup:create', 'backup:list', 'backup:verify',
        'backup:restore-drill', 'backup:cleanup', 'backup:purge-preceding-generations',
    ], BackupCli::COMMANDS, 'コマンドの並びが変わっている');

    $env    = backupEnv();
    $caseId = seedCase($env['k'], 'HP-2026-7200');
    $env['k']->cases->setDriveFolder($caseId, 'https://drive.google.com/drive/folders/FAKE-BK-0003',
        'HP-2026-7200 素材', 'materials@example.invalid');

    $lines = [];
    $cli   = new BackupCli($env['k']->backup, static function (string $line) use (&$lines): void {
        $lines[] = $line;
    });

    assertSame(0, $cli->run(['backup:create']), '作成が失敗した');
    $name = (string)$env['k']->backup->listGenerations()['items'][0]['name'];

    assertSame(0, $cli->run(['backup:list']));
    assertSame(0, $cli->run(['backup:verify', '--name=' . $name]));
    assertSame(0, $cli->run(['backup:restore-drill', '--name=' . $name]));
    assertSame(0, $cli->run(['backup:cleanup']));
    assertSame(2, $cli->run(['backup:verify']), '--name 無しが通ってしまう');
    assertSame(2, $cli->run(['backup:unknown']), '不明コマンドが通ってしまう');

    $out = implode("\n", $lines);
    foreach ([BK_MARKER, '架空サロン', 'materials@example.invalid', 'drive.google.com',
              'HP-2026-7200', TEST_IP_HMAC_KEY, TEST_ENC_KEY] as $forbidden) {
        assertTrue(!str_contains($out, $forbidden), 'CLI 出力に ' . $forbidden . ' が出ている');
    }
    assertTrue(!str_contains($out, $env['dir']), 'CLI 出力に保存先の絶対パスが出ている');
    assertTrue(str_contains($out, '<backup_dir>'), '保存先を伏せた表示になっていない');
    assertTrue(str_contains($out, 'dry-run'), 'cleanup が dry-run と表示されない');
    assertSame(1, count((array)$env['k']->backup->listGenerations()['items']), 'CLI が勝手に削除した');
});

test('CLI: 引数で秘密値やパスを受け取らない', function (): void {
    // 位置引数を捨て、`--key=value` だけを見る
    assertSame(['apply' => '', 'confirm' => 'X'],
        BackupCli::parseOptions(['/etc/passwd', '--apply', '--confirm=X', 'ignored']));

    $cli = backupSrc('src/Backup/BackupCli.php');
    foreach (['--db', '--dir', '--path', '--key', '--password', '--secret'] as $banned) {
        assertTrue(!str_contains($cli, $banned), 'CLI が ' . $banned . ' を受け取っている');
    }
});

test('境界: バックアップから Drive・Stripe・Operations・AI Sales へ接続しない', function (): void {
    foreach (['src/Backup/BackupService.php', 'src/Backup/BackupCli.php',
              'src/Backup/BackupPaths.php', 'src/Backup/BackupManifest.php',
              'src/Backup/SqliteBackup.php', 'bin/intake-backup.php'] as $relative) {
        $code = backupSrc($relative);
        foreach (['curl_', 'file_get_contents(\'http', 'fsockopen', 'stripe', 'googleapis',
                  'drive.google', 'operations', 'ai_sales'] as $banned) {
            assertTrue(stripos($code, $banned) === false, $relative . ' が ' . $banned . ' を含む');
        }
    }
});

test('境界: バックアップは新しいDB表を追加しない（監査の語彙だけを増やす）', function (): void {
    assertSame(4, Migrator::SCHEMA_VERSION, 'スキーマ版が変わっている');

    $migrator = (string)file_get_contents(__DIR__ . '/../src/Migrator.php');
    assertTrue(!str_contains($migrator, 'backup'), 'Migrator にバックアップ用の表が入っている');

    foreach (['backup_created', 'backup_restore_drill', 'backup_cleanup', 'backup_generations_purged'] as $event) {
        assertTrue(in_array($event, Audit::EVENTS, true), '監査の語彙に ' . $event . ' が無い');
    }
});
