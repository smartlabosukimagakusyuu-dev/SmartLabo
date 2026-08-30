<?php
/**
 * preflight 専用CLI（作成・通し確認・撤去）のテスト
 * （HP-ONBOARDING-4H-4 / SSOT §9.10）
 *
 * ★使い捨ての APP_ROOT だけを使う。本番・既存DBへは一切接続しない。
 * ★架空データ・example.invalid のみ。**実メールを1通も送らない**。
 * ★秘密値（鍵・管理者情報）が出力へ出ないことも検査する。
 */
declare(strict_types=1);

use SmartLabo\Intake\Preflight\PreflightArea;
use SmartLabo\Intake\Preflight\PreflightBuilder;
use SmartLabo\Intake\Preflight\PreflightFixture;
use SmartLabo\Intake\Preflight\PreflightRunner;

/* ==================================================== 使い捨て APP_ROOT */

/**
 * 本番配置と同じ形の使い捨て APP_ROOT を作る。
 * src/ は実体をコピーせず、`Autoload.php` の存在だけを満たせばよい
 * （検査は `src/Autoload.php` の実在を見るため）。
 */
function preflightAppRoot(string $name): string
{
    static $seq = 0;
    ++$seq;
    $base = tmpDir() . '/pf-' . getmypid() . '-' . $seq . '-' . $name;

    @mkdir($base . '/src', 0700, true);
    @mkdir($base . '/bin', 0700, true);
    @mkdir($base . '/private/logs', 0700, true);
    copy(__DIR__ . '/../src/Autoload.php', $base . '/src/Autoload.php');
    copy(__DIR__ . '/../private/.htaccess', $base . '/private/.htaccess');
    copy(__DIR__ . '/../bin/.htaccess', $base . '/bin/.htaccess');
    file_put_contents($base . '/private/logs/php-error.log', "preflight test log\n");

    return $base;
}

/** 使い捨て APP_ROOT を後始末する */
function preflightCleanup(string $base): void
{
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
}

/* ==================================================================== 正常系 */

test('preflight: init が 700 / 600 で隔離領域を作る', function (): void {
    $base    = preflightAppRoot('init');
    $builder = new PreflightBuilder($base);

    assertSame(true, $builder->assertInitialInstall()['ok'], '初回導入と判定されない');
    assertSame(true, $builder->precreateCheck()['ok'], '作成前の検査が通らない');
    assertSame(true, $builder->create()['ok'], '隔離領域を作れない');

    assertTrue(is_dir($builder->root()), '隔離領域が無い');
    assertTrue(is_file($builder->configPath()), 'preflight 設定が無い');
    foreach (PreflightArea::CHILDREN as $child) {
        assertTrue(is_dir($builder->root() . '/' . $child), $child . ' が無い');
    }
    if (DIRECTORY_SEPARATOR === '/') {
        assertSame('0700', substr(sprintf('%o', fileperms($builder->root())), -4), '領域が 700 でない');
        assertSame('0600', substr(sprintf('%o', fileperms($builder->configPath())), -4), '設定が 600 でない');
    }
    preflightCleanup($base);
});

test('preflight: 生成した鍵は2つとも 64桁で、互いに異なる', function (): void {
    $base    = preflightAppRoot('keys');
    $builder = new PreflightBuilder($base);
    $builder->create();

    $overrides = $builder->configOverrides();
    assertSame(64, strlen((string)$overrides['ip_hmac_key']), 'ip_hmac_key の長さが違う');
    assertSame(64, strlen((string)$overrides['enc_key']), 'enc_key の長さが違う');
    assertTrue($overrides['ip_hmac_key'] !== $overrides['enc_key'], '2つの鍵が同じ値になっている');
    preflightCleanup($base);
});

test('preflight: 解決した全パスが隔離領域の内側にある', function (): void {
    $base    = preflightAppRoot('inside');
    $builder = new PreflightBuilder($base);
    $builder->create();

    assertSame(true, $builder->assertInsideRoot($builder->configOverrides())['ok'],
        '隔離領域の外を指しているパスがある');
    preflightCleanup($base);
});

test('preflight: 通し確認が本物の API 経路を通って全段成功する', function (): void {
    $base    = preflightAppRoot('run');
    $builder = new PreflightBuilder($base);
    $builder->create();

    $before = $builder->productionSnapshot();
    $result = (new PreflightRunner($builder))->run();
    $after  = $builder->productionSnapshot();

    $failed = [];
    foreach ($result['steps'] as $step) {
        if ($step['ok'] !== true) {
            $failed[] = $step['label'];
        }
    }
    assertSame([], $failed, '失敗した段がある: ' . implode(' / ', $failed));
    assertSame(true, $result['ok'], '通し確認が成功しない');
    assertTrue($result['passed'] >= 30, '確認した段が少なすぎる（' . $result['passed'] . '）');

    $diff = PreflightBuilder::compareSnapshots($before, $after);
    assertSame([], $diff['problems'], '正式領域が変化した: ' . implode(' / ', $diff['problems']));

    // 隔離領域の中にだけ DB ができている
    assertTrue(is_file($builder->root() . '/intake.sqlite'), 'preflight のDBが無い');
    assertTrue(!file_exists($builder->productionDbPath()), '正式DBが作られてしまった');
    preflightCleanup($base);
});

test('preflight: status / remove(dry-run) / remove(apply) / verify-empty が順に働く', function (): void {
    $base    = preflightAppRoot('remove');
    $builder = new PreflightBuilder($base);
    $builder->create();
    (new PreflightRunner($builder))->run();

    $area = new PreflightArea(
        $builder->root(),
        $builder->productionPrivateRoot(),
        $builder->productionDbPath()
    );

    $inventory = $area->inventory();
    assertSame(true, $inventory['ok'], 'status が使えない');
    assertTrue($inventory['files'] > 0, '中身が数えられていない');

    $dry = $area->remove(false, '');
    assertSame(true, $dry['dry_run'], 'dry-run になっていない');
    assertSame(0, $dry['removed'], 'dry-run で削除している');
    assertTrue(is_dir($builder->root()), 'dry-run で領域が消えた');

    $applied = $area->remove(true, PreflightArea::CONFIRM_REMOVE);
    assertSame(true, $applied['ok'], '削除できない');
    assertSame(0, $applied['remaining'], '残存がある');
    assertTrue(!file_exists($builder->root()), 'preflight 領域が残っている');

    // ★消えたのは preflight だけ。正式領域と配置物は残る
    assertTrue(is_file($builder->productionPrivateRoot() . '/.htaccess'), '正式 .htaccess が消えた');
    assertTrue(is_file($builder->productionPrivateRoot() . '/logs/php-error.log'), 'ログが消えた');
    assertTrue(is_file($base . '/src/Autoload.php'), '配置物が消えた');
    preflightCleanup($base);
});

/* ============================================================ 負のテスト */

test('preflight: 正式 intake-config.php があれば init / run は順序違反で止まる', function (): void {
    $base = preflightAppRoot('order-config');
    file_put_contents($base . '/private/intake-config.php', "<?php return [];\n");

    $builder = new PreflightBuilder($base);
    $result  = $builder->assertInitialInstall();
    assertSame(false, $result['ok'], '順序違反を検出しない');
    assertSame('production_already_installed', $result['error'], '理由コードが違う');
    assertSame('intake-config.php', $result['found'], '検出したものが違う');
    preflightCleanup($base);
});

test('preflight: 正式DB・journal・wal・shm・ratelimit・backups も順序違反にする', function (): void {
    foreach (PreflightBuilder::PRODUCTION_FORBIDDEN as $name) {
        $base = preflightAppRoot('order-' . str_replace(['.', '-'], '_', $name));
        $path = $base . '/private/' . $name;
        if (in_array($name, ['ratelimit', 'backups'], true)) {
            @mkdir($path, 0700);
        } else {
            file_put_contents($path, 'x');
        }
        $result = (new PreflightBuilder($base))->assertInitialInstall();
        assertSame(false, $result['ok'], $name . ' を順序違反にしていない');
        preflightCleanup($base);
    }
});

test('preflight: 既に領域があれば init は作り直さない（上書きしない）', function (): void {
    $base    = preflightAppRoot('exists');
    $builder = new PreflightBuilder($base);
    @mkdir($builder->root(), 0700);

    $pre = $builder->precreateCheck();
    assertSame(false, $pre['ok'], '既存の領域を作成対象にしてしまう');
    assertSame('already_exists', $pre['error'], '理由コードが違う');
    preflightCleanup($base);
});

test('preflight: 設定ファイルが既にあれば作成は失敗し、上書きしない', function (): void {
    $base    = preflightAppRoot('config-exists');
    $builder = new PreflightBuilder($base);
    @mkdir($builder->root(), 0700);
    foreach (PreflightArea::CHILDREN as $child) {
        @mkdir($builder->root() . '/' . $child, 0700);
    }
    file_put_contents($builder->configPath(), "<?php return ['keep' => true];\n");
    $before = (string)file_get_contents($builder->configPath());

    // ★precreateCheck が already_exists で止めるため、create は中身へ進まない
    assertSame(false, $builder->create()['ok'], '既存領域へ作成してしまう');
    assertSame($before, (string)file_get_contents($builder->configPath()), '既存の設定を上書きした');
    preflightCleanup($base);
});

test('preflight: APP_ROOT が存在しなければ、深い階層を勝手に作らない', function (): void {
    $base = preflightAppRoot('parent');
    // ★存在しない APP_ROOT を指す。最寄りの既存祖先は $base であり APP_ROOT ではない
    $pre = (new PreflightBuilder($base . '/not-created-yet'))->precreateCheck();
    assertSame(false, $pre['ok'], '存在しない APP_ROOT の下へ作ろうとしてしまう');
    assertSame('parent_is_not_app_root', $pre['error'], '理由コードが違う');
    preflightCleanup($base);
});

test('preflight: 公開領域を含む APP_ROOT では作らない', function (): void {
    $base = tmpDir() . '/pf-public-' . getmypid();
    @mkdir($base . '/public_html/site', 0700, true);

    $pre = (new PreflightBuilder($base . '/public_html/site'))->precreateCheck();
    assertSame(false, $pre['ok'], '公開領域を通してしまう');
    assertSame('public_area', $pre['error'], '理由コードが違う');
    preflightCleanup($base);
});

test('preflight: 相対パス・ホーム直下・ルート直下は作らない', function (): void {
    foreach (['relative/path' => 'relative', '/' => 'too_shallow'] as $raw => $expected) {
        $pre = (new PreflightBuilder($raw))->precreateCheck();
        assertSame(false, $pre['ok'], $raw . ' を通してしまう');
    }
});

test('preflight: 正式領域と重なる位置は PreflightArea が拒否する', function (): void {
    $base    = preflightAppRoot('overlap');
    $builder = new PreflightBuilder($base);

    $private = $builder->productionPrivateRoot();
    $cases   = [
        $private                 => 'is_production_root',
        $private . '/logs'       => 'inside_production_root',
        $base . '/private/..'    => null,   // `..` は字句検査で落ちる
    ];
    foreach ($cases as $root => $expected) {
        $area   = new PreflightArea($root, $private, $builder->productionDbPath());
        $result = $area->check();
        assertSame(false, $result['ok'], $root . ' を通してしまう');
        if ($expected !== null) {
            assertSame($expected, $result['error'], '理由コードが違う');
        }
    }
    preflightCleanup($base);
});

test('preflight: 確認文字列が一致しなければ1件も削除しない', function (): void {
    $base    = preflightAppRoot('confirm');
    $builder = new PreflightBuilder($base);
    $builder->create();

    $area   = new PreflightArea($builder->root(), $builder->productionPrivateRoot(), $builder->productionDbPath());
    $before = count($area->inventory()['entries']);

    // ★前後の空白は trim される仕様なので、本当に一致しない文字列だけを試す
    foreach (['', 'delete preflight area', 'DELETE PREFLIGHT', 'DELETE PREFLIGHT AREAS'] as $confirm) {
        $result = $area->remove(true, $confirm);
        assertSame(false, $result['ok'], '確認文字列 "' . $confirm . '" で削除できてしまう');
        assertSame(0, $result['removed'], '1件でも削除している');
        assertTrue(is_dir($builder->root()), '領域が消えている');
    }
    assertSame($before, count($area->inventory()['entries']), '中身が減っている');
    preflightCleanup($base);
});

/* ==================================================== 秘密値・メールの検査 */

test('preflight: 出力に鍵も管理者情報も現れない', function (): void {
    $base    = preflightAppRoot('secrets');
    $builder = new PreflightBuilder($base);
    $builder->create();

    $overrides = $builder->configOverrides();
    $result    = (new PreflightRunner($builder))->run();

    $text = json_encode($result, JSON_UNESCAPED_UNICODE) . implode(' ', array_column($result['steps'], 'label'));
    assertTrue(!str_contains($text, (string)$overrides['ip_hmac_key']), '出力に ip_hmac_key が出ている');
    assertTrue(!str_contains($text, (string)$overrides['enc_key']), '出力に enc_key が出ている');
    assertTrue(!str_contains($text, PreflightRunner::ADMIN_ID_PREFIX), '出力に管理者IDが出ている');
    assertTrue(!str_contains($text, '$argon2'), '出力に password hash が出ている');
    preflightCleanup($base);
});

test('preflight: 実メールの経路を持たない（静的検査）', function (): void {
    foreach (glob(__DIR__ . '/../src/Preflight/*.php') ?: [] as $path) {
        $body = (string)file_get_contents($path);
        assertTrue(
            !str_contains($body, 'ProductionMailNotifier'),
            basename($path) . ' が ProductionMailNotifier を参照している'
        );
        assertTrue(
            preg_match('/(?<![a-zA-Z0-9_>])mail\s*\(/', $body) !== 1,
            basename($path) . ' が mail() を使っている'
        );
    }
    // Runner は NullNotifier を明示注入している
    $runner = (string)file_get_contents(__DIR__ . '/../src/Preflight/PreflightRunner.php');
    assertTrue(str_contains($runner, 'new NullNotifier()'), 'NullNotifier を明示注入していない');
});

test('preflight: CLI は CLI 以外の SAPI で実行できない', function (): void {
    $cli = (string)file_get_contents(__DIR__ . '/../bin/intake-preflight.php');
    assertTrue(str_contains($cli, "PHP_SAPI !== 'cli'"), 'CLI ガードが無い');
    assertTrue(str_contains($cli, 'umask(0077)'), 'umask を設定していない');
    assertTrue(!str_contains($cli, "\$options['root']"), '--root を値として使っている');
    assertTrue(str_contains($cli, '--root は受け付けません'), '--root の拒否が無い');
});

test('preflight: 架空データが dev の通し確認と同一で、実在情報を含まない', function (): void {
    $fixture = PreflightFixture::answers();
    $dev     = require __DIR__ . '/../dev/walkthrough-answers.php';
    assertSame($dev, $fixture, '架空データが dev の通し確認とずれている');

    $text = json_encode($fixture, JSON_UNESCAPED_UNICODE);
    assertTrue(!str_contains((string)$text, '@gmail.'), '実在しそうなメールを含んでいる');
    assertTrue(str_contains(PreflightFixture::DRIVE_EMAIL, 'example.invalid'), '共有先が example.invalid でない');
});

/* ==================================================== 正式領域の不変検査 */

test('preflight: 正式領域に想定外が出れば検出する', function (): void {
    $base    = preflightAppRoot('snapshot');
    $builder = new PreflightBuilder($base);

    $before = $builder->productionSnapshot();
    file_put_contents($builder->productionPrivateRoot() . '/intake.sqlite', 'x');
    $after  = $builder->productionSnapshot();

    $diff = PreflightBuilder::compareSnapshots($before, $after);
    assertSame(false, $diff['ok'], '正式DBの出現を見逃した');
    assertTrue($diff['problems'] !== [], '問題として報告していない');
    preflightCleanup($base);
});

test('preflight: 配置物の内容が変われば検出する', function (): void {
    $base    = preflightAppRoot('hashes');
    $builder = new PreflightBuilder($base);

    $before = $builder->productionSnapshot();
    file_put_contents($base . '/src/Autoload.php', "<?php\n// changed\n");
    $after  = $builder->productionSnapshot();

    $diff = PreflightBuilder::compareSnapshots($before, $after);
    assertSame(false, $diff['ok'], '配置物の変化を見逃した');
    preflightCleanup($base);
});

test('preflight: ログのサイズ変化は STOP にせず記録に留める', function (): void {
    $base    = preflightAppRoot('logsize');
    $builder = new PreflightBuilder($base);

    $before = $builder->productionSnapshot();
    file_put_contents($builder->productionPrivateRoot() . '/logs/php-error.log',
        "preflight test log\nmore\n");
    $after  = $builder->productionSnapshot();

    $diff = PreflightBuilder::compareSnapshots($before, $after);
    assertSame(true, $diff['ok'], 'ログのサイズ変化で止まってしまう');
    assertTrue($diff['notes'] !== [], 'サイズ変化を記録していない');
    preflightCleanup($base);
});

test('preflight: ログの消失は STOP にする', function (): void {
    $base    = preflightAppRoot('logmissing');
    $builder = new PreflightBuilder($base);

    $before = $builder->productionSnapshot();
    @unlink($builder->productionPrivateRoot() . '/logs/php-error.log');
    $after  = $builder->productionSnapshot();

    $diff = PreflightBuilder::compareSnapshots($before, $after);
    assertSame(false, $diff['ok'], 'ログの消失を見逃した');
    preflightCleanup($base);
});
