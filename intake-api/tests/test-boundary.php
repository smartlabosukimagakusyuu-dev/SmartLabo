<?php
/**
 * 配置境界・提出通知・preflight 分離のテスト
 * （HP-ONBOARDING-4H-R0 / SSOT v1.12 §9.10 / §9.11 / §10.11）
 *
 * ★使い捨てディレクトリだけを使う。本番・既存DBへは接続しない。
 * ★架空データ・example.invalid のみ。**実メールを1通も送らない**
 *   （通知は FakeNotifier。`mail()` を呼ぶ経路をテストへ持ち込まない）。
 */
declare(strict_types=1);

use SmartLabo\Intake\AppRoot;
use SmartLabo\Intake\Config;
use SmartLabo\Intake\ConfigException;
use SmartLabo\Intake\Notify\NullNotifier;
use SmartLabo\Intake\Notify\ProductionMailNotifier;
use SmartLabo\Intake\Notify\SubmissionNotice;
use SmartLabo\Intake\Preflight\PreflightArea;
use SmartLabo\Intake\Support\PathPolicy;

/** 削除・混入を確かめるための目印（架空・他に出てこない文字列） */
const BD_MARKER = 'BOUNDARYMARKER0001';

/** コメントを除いた実行コード（説明文を検査対象にしない） */
function boundarySrc(string $relative): string
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

/** 使い捨ての作業ディレクトリ */
function boundaryDir(string $label): string
{
    static $seq = 0;
    ++$seq;
    $dir = tmpDir() . '/boundary-' . getmypid() . '-' . $seq . '-' . $label;
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }

    return $dir;
}

/** 本番相当の分離構成（APP_ROOT / private / preflight）を1組つくる */
function boundaryLayout(): array
{
    $base = boundaryDir('layout');
    foreach (['app/src', 'app/private', 'app/preflight'] as $sub) {
        if (!is_dir($base . '/' . $sub)) {
            mkdir($base . '/' . $sub, 0700, true);
        }
    }
    // ★APP_ROOT の判定は `src/Autoload.php` の実在を見る。本物をコピーせず空で足りる
    file_put_contents($base . '/app/src/Autoload.php', "<?php\n");

    return [
        'base'      => PathPolicy::normalize($base),
        'app'       => PathPolicy::normalize($base . '/app'),
        'private'   => PathPolicy::normalize($base . '/app/private'),
        'preflight' => PathPolicy::normalize($base . '/app/preflight'),
    ];
}

/* ============================================================ 配置境界 */

test('配置境界: APP_ROOT は Web の入力から変えられない', function (): void {
    // ★スーパーグローバルを1つも読まない（読めば経路ができる）
    foreach (['src/AppRoot.php', 'src/Support/PathPolicy.php'] as $relative) {
        $code = boundarySrc($relative);
        foreach (['$_GET', '$_POST', '$_COOKIE', '$_REQUEST', '$_SERVER', '$_FILES', '$_ENV'] as $global) {
            assertTrue(!str_contains($code, $global), $relative . ' が ' . $global . ' を読んでいる');
        }
    }
    // ★docroot 側の入口も、入力からパスを組み立てない
    $index = boundarySrc('public/index.php');
    foreach (['$_GET', '$_POST', '$_COOKIE', '$_REQUEST'] as $global) {
        assertTrue(!str_contains($index, $global), 'index.php が ' . $global . ' からパスを作っている');
    }
    // ★docroot 内へ APP_ROOT を書いたファイルを置く方式は採らない
    assertTrue(!is_file(__DIR__ . '/../public/.app-root.php'), 'docroot に .app-root.php がある');
});

test('配置境界: 未設定・相対・traversal・公開領域・ホーム直下・ルートを拒否する', function (): void {
    $cases = [
        ''                        => 'not_configured',
        '   '                     => 'not_configured',
        'app'                     => 'relative',
        './app'                   => 'relative',
        '../app'                  => 'relative',
        '/home/x/app/../etc'      => 'traversal',
        '/var/www/public_html/hp' => 'public_area',
        '/home/x/public/hp'       => 'public_area',
        '/home/x/htdocs/hp'       => 'public_area',
        '/home/someone'           => 'home_root',
        '/root'                   => 'home_root',
        '/'                       => 'too_shallow',
        '/app'                    => 'too_shallow',
        'C:/app'                  => 'too_shallow',
    ];
    foreach ($cases as $path => $expected) {
        $result = AppRoot::check($path);
        assertSame(false, $result['ok'], $path . ' が通ってしまう');
        assertSame($expected, $result['error'], $path . ' の拒否理由が違う');
    }
    assertSame('not_configured', AppRoot::check(null)['error'], 'null が通ってしまう');
});

test('配置境界: src/Autoload.php が無い場所を APP_ROOT にしない', function (): void {
    $layout = boundaryLayout();
    assertSame('no_src', AppRoot::check($layout['base'])['error'], 'src の無い場所が通ってしまう');
    assertSame(true, AppRoot::check($layout['app'])['ok'], '正しい APP_ROOT が通らない');
    assertSame($layout['app'], AppRoot::check($layout['app'])['dir']);
});

test('配置境界: docroot と APP_ROOT が別階層でも解決できる（XServer 相当）', function (): void {
    // smartlaboworks.com/
    //   ├── public_html/intake.smartlaboworks.com/   ← docroot
    //   └── private/hp-intake/                        ← APP_ROOT
    $base    = boundaryDir('xserver');
    $docroot = $base . '/public_html/intake.smartlaboworks.com';
    $appRoot = $base . '/private/hp-intake';
    mkdir($docroot, 0700, true);
    mkdir($appRoot . '/src', 0700, true);
    file_put_contents($appRoot . '/src/Autoload.php', "<?php\n");

    // ★docroot の親（public_html）は APP_ROOT ではない。祖先から見つける
    $found = AppRoot::discoverFrom($docroot);
    assertSame(PathPolicy::normalize($appRoot), $found, 'XServer 相当の配置で APP_ROOT が見つからない');

    // ★docroot の親を APP_ROOT として渡したら拒否される（public_html の中）
    assertSame('public_area', AppRoot::check($base . '/public_html')['error'], 'public_html が通ってしまう');
});

test('配置境界: 公開領域の中に置かれた APP_ROOT は採用しない', function (): void {
    $base    = boundaryDir('inpublic');
    $bad     = $base . '/public_html/hp-intake';
    mkdir($bad . '/src', 0700, true);
    file_put_contents($bad . '/src/Autoload.php', "<?php\n");

    assertSame('public_area', AppRoot::check($bad)['error'], '公開領域内の APP_ROOT が通ってしまう');
    // 祖先探索でも拾わない（候補が公開領域の中にあるため）
    assertSame(null, AppRoot::discoverFrom($base . '/public_html/site'), '公開領域内の候補を拾ってしまう');
});

test('配置境界: 明示された APP_ROOT が不正なら既定へ落ちず fail closed', function (): void {
    $thrown = false;
    try {
        Config::load([
            'app_root'    => '/var/www/public_html/hp',
            'ip_hmac_key' => TEST_IP_HMAC_KEY,
            'enc_key'     => TEST_ENC_KEY,
        ]);
    } catch (ConfigException $e) {
        $thrown = true;
        assertTrue(str_contains($e->getMessage(), 'app_root'), '理由が app_root でない');
    }
    assertTrue($thrown, '不正な APP_ROOT で起動してしまう');
});

test('配置境界: Config が APP_ROOT / src / private / データを分けて持つ', function (): void {
    $layout = boundaryLayout();
    $config = Config::load([
        'app_root'    => $layout['app'],
        'ip_hmac_key' => TEST_IP_HMAC_KEY,
        'enc_key'     => TEST_ENC_KEY,
    ]);

    assertSame($layout['app'], $config->appRoot);
    assertSame($layout['app'] . '/src', $config->srcRoot);
    assertSame($layout['private'], $config->privateRoot);
    assertSame($layout['private'] . '/intake.sqlite', $config->dbPath);
    assertSame($layout['private'] . '/ratelimit', $config->rateLimitDir);
});

test('配置境界: private_root / preflight_root の不正を fail closed で止める', function (): void {
    $layout = boundaryLayout();
    $base   = [
        'app_root'    => $layout['app'],
        'ip_hmac_key' => TEST_IP_HMAC_KEY,
        'enc_key'     => TEST_ENC_KEY,
    ];

    foreach ([
        ['private_root' => 'relative/path'],
        ['private_root' => '/var/www/public_html/data'],
        ['private_root' => '/home/someone'],
        ['preflight_root' => 'relative/path'],
        ['preflight_root' => '/var/www/public_html/pre'],
    ] as $bad) {
        $thrown = false;
        try {
            Config::load(array_merge($base, $bad));
        } catch (ConfigException $e) {
            $thrown = true;
        }
        assertTrue($thrown, json_encode($bad) . ' が通ってしまう');
    }

    // ★preflight が正式領域と重なる指定も止める
    foreach ([
        $layout['private'],                 // 同一
        $layout['private'] . '/inside',      // 内側
        $layout['app'],                      // 外側から包む
    ] as $overlap) {
        $thrown = false;
        try {
            Config::load(array_merge($base, ['preflight_root' => $overlap]));
        } catch (ConfigException $e) {
            $thrown = true;
        }
        assertTrue($thrown, $overlap . ' が preflight として通ってしまう');
    }
});

test('配置境界: 絶対パスを応答・ログへ出さない', function (): void {
    $k      = makeKernel();
    $caseId = $k->cases->create('HP-2026-6001', '架空サロン ' . BD_MARKER);
    $token  = $k->tokens->issue($caseId);
    $start  = $k->app->handle(jsonPost('/session/start', ['token' => $token]));

    $bodies = [$start->json()];
    $bodies[] = $k->app->handle(jsonGet('/case', ['cookies' => []]))->json();
    $bodies[] = $k->app->handle(jsonPost('/answers/save', ['version' => 1, 'sections' => []]))->json();

    $roots = [$k->config->appRoot, $k->config->privateRoot, $k->config->dbPath];
    foreach ($bodies as $body) {
        foreach ($roots as $root) {
            assertTrue(!str_contains($body, $root), '応答に絶対パスが出ている');
            assertTrue(!str_contains($body, str_replace('/', '\\', $root)), '応答に絶対パスが出ている');
        }
    }

    $log = is_file((string)$k->config->logPath) ? (string)file_get_contents((string)$k->config->logPath) : '';
    foreach ($roots as $root) {
        assertTrue(!str_contains($log, $root), 'ログに絶対パスが出ている');
    }
});

/* ======================================================== 誤配置防御 */

test('誤配置防御: src/ と bin/ に Require all denied がある', function (): void {
    foreach (['src/.htaccess', 'bin/.htaccess'] as $relative) {
        $path = __DIR__ . '/../' . $relative;
        assertTrue(is_file($path), $relative . ' が無い');

        $body = (string)file_get_contents($path);
        assertTrue(str_contains($body, 'Require all denied'), $relative . ' が全拒否になっていない');
        // ★実コード・秘密値を書かない
        assertTrue(!str_contains($body, '<?php'), $relative . ' に PHP が入っている');
        foreach (['key', 'password', 'secret', 'token'] as $risky) {
            assertTrue(stripos($body, $risky) === false, $relative . ' に ' . $risky . ' の語がある');
        }
    }
});

test('誤配置防御: CLI は Web から実行できず、公開領域に無い', function (): void {
    foreach (['bin/intake-backup.php', 'bin/intake-preflight.php'] as $relative) {
        $body = (string)file_get_contents(__DIR__ . '/../' . $relative);
        assertTrue(str_contains($body, "PHP_SAPI !== 'cli'"), $relative . ' が CLI 以外を止めていない');
    }
    assertTrue(!is_dir(__DIR__ . '/../public/bin'), '公開領域に bin がある');
    assertTrue(!is_dir(__DIR__ . '/../public/src'), '公開領域に src がある');

    // 公開領域の PHP は index.php だけ
    $php = [];
    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(__DIR__ . '/../public', FilesystemIterator::SKIP_DOTS)
    ) as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $php[] = $file->getFilename();
        }
    }
    assertSame(['index.php'], $php, '公開領域に余分な PHP がある: ' . implode(',', $php));
});

/* ========================================================== 通知メール */

/** 提出まで進めて、通知が何通出たかを見る環境 */
function notifyEnv(?FakeNotifier $notifier = null): array
{
    $fake   = $notifier ?? new FakeNotifier();
    $k      = makeKernel(null, [], $fake);
    $caseId = $k->cases->create('HP-2026-6100', '架空サロン ' . BD_MARKER);
    $token  = $k->tokens->issue($caseId);
    $secret = (string)$k->app->handle(jsonPost('/session/start', ['token' => $token]))->cookies[0]['value'];

    return [
        'k' => $k, 'fake' => $fake, 'caseId' => $caseId, 'token' => $token,
        'cookies' => [Config::COOKIE_NAME => $secret],
    ];
}

test('通知: 初回の提出成功だけが1通になる', function (): void {
    ['k' => $k, 'fake' => $fake, 'caseId' => $caseId, 'cookies' => $cookies] = notifyEnv();

    // 保存だけでは送らない
    $k->app->handle(jsonPost('/answers/save', ['version' => 1, 'sections' => completeSections()], ['cookies' => $cookies]));
    assertSame(0, $fake->count(), '保存で通知が出た');

    // Drive 申告でも送らない
    $k->app->handle(jsonPost('/drive/confirm', ['confirmed' => true], ['cookies' => $cookies]));
    assertSame(0, $fake->count(), 'Drive 申告で通知が出た');

    $submissionId = newSubmissionId();
    $first = $k->app->handle(jsonPost('/submit', ['submission_id' => $submissionId], ['cookies' => $cookies]));
    assertSame(200, $first->status, '提出できていない');
    assertSame(1, $fake->count(), '初回提出で1通になっていない');

    // 同一 submission_id の再送では増えない
    $again = $k->app->handle(jsonPost('/submit', ['submission_id' => $submissionId], ['cookies' => $cookies]));
    assertSame(200, $again->status);
    assertSame(1, $fake->count(), '同一 submission_id の再送で通知が増えた');

    // 別 submission_id での二重提出（already_submitted）でも増えない
    $dup = $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], ['cookies' => $cookies]));
    assertSame(409, $dup->status, 'already_submitted になっていない');
    assertSame(1, $fake->count(), 'already_submitted で通知が増えた');

    // 監査は sent 1件・failed 0件
    assertSame(1, $k->audit->countFor($caseId, 'submission_notification_sent'), 'sent が1件でない');
    assertSame(0, $k->audit->countFor($caseId, 'submission_notification_failed'), 'failed が出ている');
});

test('通知: validation_error では1通も出ない', function (): void {
    ['k' => $k, 'fake' => $fake, 'caseId' => $caseId, 'cookies' => $cookies] = notifyEnv();

    // 不足のあるまま提出する
    $k->app->handle(jsonPost('/answers/save', ['version' => 1, 'sections' => ['basic' => ['legal_name' => '架空']]], ['cookies' => $cookies]));
    $res = $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], ['cookies' => $cookies]));

    assertSame(false, $res->body['submitted'] ?? null, '不足なのに提出できている');
    assertSame(0, $fake->count(), 'validation_error で通知が出た');
    assertSame(0, $k->audit->countFor($caseId, 'submission_notification_sent'), 'sent が出ている');
    assertSame(0, $k->audit->countFor($caseId, 'submission_notification_failed'), 'failed が出ている');
});

test('通知: 管理側の状態変更では1通も出ない', function (): void {
    ['k' => $k, 'fake' => $fake, 'caseId' => $caseId, 'cookies' => $cookies] = notifyEnv();
    $k->app->handle(jsonPost('/answers/save', ['version' => 1, 'sections' => completeSections()], ['cookies' => $cookies]));
    $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], ['cookies' => $cookies]));
    $before = $fake->count();

    $k->cases->transitionTo($caseId, 'reviewed');
    $k->revisions->insert($caseId, ['basic.legal_name'], '架空の修正依頼');
    $k->cases->transitionTo($caseId, 'needs_revision');

    assertSame($before, $fake->count(), '状態変更で通知が増えた');
});

test('通知: 中身は3項目だけで、PII・token・submission_id・Drive URL を含まない', function (): void {
    // ★ここで token を再発行しない。再発行すると既存の店舗 session が失効する（4D-R2）
    ['k' => $k, 'fake' => $fake, 'caseId' => $caseId, 'cookies' => $cookies, 'token' => $token] = notifyEnv();
    $k->cases->setDriveFolder($caseId, 'https://drive.google.com/drive/folders/FAKE-BD-0001',
        'HP-2026-6100 素材', 'materials@example.invalid');

    $k->app->handle(jsonPost('/answers/save', ['version' => 1, 'sections' => completeSections()], ['cookies' => $cookies]));
    $submissionId = newSubmissionId();
    $k->app->handle(jsonPost('/submit', ['submission_id' => $submissionId], ['cookies' => $cookies]));

    assertSame(1, $fake->count(), '通知が1通でない');
    $dump = $fake->dump();

    // 載ってよい3項目
    assertTrue(str_contains($dump, 'HP-2026-6100'), '案件番号が無い');
    assertTrue(str_contains($dump, 'submitted'), 'イベント種別が無い');
    assertTrue(preg_match('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z/', $dump) === 1, '発生日時が無い');
    assertTrue(str_contains($dump, 'UTC'), '時間帯が明示されていない');

    // 載ってはいけないもの
    foreach ([
        BD_MARKER, '架空サロン', 'ヘアサロン ハルカゼ', '架空県架空市架空町',
        '03-0000-0000', 'internal@example.invalid', 'materials@example.invalid',
        'drive.google.com', $token, $submissionId,
    ] as $forbidden) {
        assertTrue(!str_contains($dump, $forbidden), '通知に ' . substr($forbidden, 0, 16) . ' が出ている');
    }

    // ★DBの内部ID・電話番号・郵便番号・submission_id は「短い数字」なので
    //   部分一致では見分けられない。**許した2つの値を取り除いて、数字が残らないこと**を見る。
    //   ここに数字が残るなら、許していない何かが載っている。
    $sent     = $fake->sent[0];
    $stripped = str_replace([$sent['case_number'], $sent['occurred_at']], '', $dump);
    assertTrue(preg_match('/\d/', $stripped) !== 1, '通知に想定外の数字が出ている');
});

test('通知: 送信に失敗しても提出は成功のまま維持する', function (): void {
    $fake = new FakeNotifier();
    $fake->failNext = true;
    ['k' => $k, 'caseId' => $caseId, 'cookies' => $cookies] = notifyEnv($fake);

    $k->app->handle(jsonPost('/answers/save', ['version' => 1, 'sections' => completeSections()], ['cookies' => $cookies]));
    $res = $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], ['cookies' => $cookies]));

    assertSame(200, $res->status, '通知失敗で提出が失敗した');
    assertSame(true, $res->body['submitted'] ?? null, '提出が成功になっていない');
    assertSame(['ok', 'submitted', 'already_submitted'], array_keys($res->body), '応答にメールの話が出ている');
    assertSame('submitted', (string)$k->cases->find($caseId)['status'], '状態が巻き戻った');
    assertSame(1, $k->answers->historyCount($caseId), '履歴が巻き戻った');

    // 監査は failed 1件
    assertSame(0, $k->audit->countFor($caseId, 'submission_notification_sent'), 'sent が出ている');
    assertSame(1, $k->audit->countFor($caseId, 'submission_notification_failed'), 'failed が1件でない');
});

test('通知: 監査に宛先・件名・本文・submission_id を書かない', function (): void {
    ['k' => $k, 'fake' => $fake, 'cookies' => $cookies] = notifyEnv();
    $k->app->handle(jsonPost('/answers/save', ['version' => 1, 'sections' => completeSections()], ['cookies' => $cookies]));
    $submissionId = newSubmissionId();
    $k->app->handle(jsonPost('/submit', ['submission_id' => $submissionId], ['cookies' => $cookies]));

    $rows = $k->db->pdo()->query('SELECT * FROM intake_audit_events')->fetchAll();
    $dump = (string)json_encode($rows, JSON_UNESCAPED_UNICODE);

    foreach ([$submissionId, 'example.invalid', 'HP Intake', BD_MARKER, '案件番号'] as $forbidden) {
        assertTrue(!str_contains($dump, $forbidden), '監査に ' . $forbidden . ' が出ている');
    }
    assertTrue(str_contains($dump, 'submission_notification_sent'), 'sent が記録されていない');
});

test('通知: 設定が揃わなければ送らず、監査も増やさない', function (): void {
    // ★NullNotifier（既定）。enabled() が false なので通知経路そのものを通らない
    ['k' => $k, 'caseId' => $caseId, 'cookies' => $cookies] = (function (): array {
        $k      = makeKernel(null, [], new NullNotifier());
        $caseId = $k->cases->create('HP-2026-6101', '架空サロン');
        $token  = $k->tokens->issue($caseId);
        $secret = (string)$k->app->handle(jsonPost('/session/start', ['token' => $token]))->cookies[0]['value'];

        return ['k' => $k, 'caseId' => $caseId, 'cookies' => [Config::COOKIE_NAME => $secret]];
    })();

    $k->app->handle(jsonPost('/answers/save', ['version' => 1, 'sections' => completeSections()], ['cookies' => $cookies]));
    $res = $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], ['cookies' => $cookies]));

    assertSame(200, $res->status, '提出できていない');
    assertSame(0, $k->audit->countFor($caseId, 'submission_notification_sent'), 'sent が出ている');
    assertSame(0, $k->audit->countFor($caseId, 'submission_notification_failed'), 'failed が出ている');
    assertSame(false, $k->notifier->enabled(), '既定で通知が有効になっている');
});

test('通知: 宛先・差出人のヘッダー注入と複数宛先を拒否する', function (): void {
    $bad = [
        "ops@example.invalid\nBcc: attacker@example.invalid",
        "ops@example.invalid\r\nBcc: attacker@example.invalid",
        'ops@example.invalid,attacker@example.invalid',
        'ops@example.invalid;attacker@example.invalid',
        'Ops <ops@example.invalid>',
        'ops @example.invalid',
        'ops"@example.invalid',
        'ops' . chr(92) . '@example.invalid',
        'not-an-email',
        '',
    ];

    foreach ($bad as $address) {
        // Config は受け取らない（null＝通知しない）
        $config = Config::load([
            'ip_hmac_key' => TEST_IP_HMAC_KEY,
            'enc_key'     => TEST_ENC_KEY,
            'db_path'     => boundaryDir('addr') . '/intake.sqlite',
            'notification_recipient' => $address,
            'notification_from'      => $address,
        ]);
        assertSame(null, $config->notificationRecipient, json_encode($address) . ' が宛先として通った');
        assertSame(null, $config->notificationFrom, json_encode($address) . ' が差出人として通った');
        assertSame(false, $config->notificationEnabled(), json_encode($address) . ' で通知が有効になった');

        // 送信側でももう一度拒否する（設定を経由しない呼び出しへの備え）
        assertSame(false, ProductionMailNotifier::addressAcceptable($address),
            json_encode($address) . ' が送信側で通った');
    }

    // 正しい1宛先だけを受け付ける
    $ok = Config::load([
        'ip_hmac_key' => TEST_IP_HMAC_KEY,
        'enc_key'     => TEST_ENC_KEY,
        'db_path'     => boundaryDir('addr-ok') . '/intake.sqlite',
        'notification_recipient' => 'ops@example.invalid',
        'notification_from'      => 'no-reply@example.invalid',
    ]);
    assertSame('ops@example.invalid', $ok->notificationRecipient);
    assertSame(true, $ok->notificationEnabled());
});

test('通知: 本文を組み立てる経路が3項目しか受け取らない', function (): void {
    assertSame(null, SubmissionNotice::forSubmitted('架空サロン', '2026-08-28T00:00:00Z'), '案件番号でない値が通る');
    assertSame(null, SubmissionNotice::forSubmitted('HP-2026-6100', 'いつか'), '日時でない値が通る');
    assertSame(null, SubmissionNotice::forSubmitted("HP-2026-6100\nBcc: x", '2026-08-28T00:00:00Z'), '改行が通る');

    $notice = SubmissionNotice::forSubmitted('HP-2026-6100', '2026-08-28T00:00:00Z');
    assertTrue($notice !== null, '正しい値が通らない');
    assertSame('[HP Intake] submitted HP-2026-6100', $notice->subject());
    assertTrue(!ProductionMailNotifier::hasHeaderBreak($notice->subject()), '件名に改行が入りうる');

    // 件名は encoded-word。改行が混ざらない
    $encoded = ProductionMailNotifier::encodeSubject($notice->subject());
    assertTrue(!ProductionMailNotifier::hasHeaderBreak($encoded), 'エンコード後に改行が入る');
    assertTrue(str_starts_with($encoded, '=?UTF-8?B?'), 'MIME エンコードされていない');
});

test('通知: mail() を使ってよいのは ProductionMailNotifier だけ', function (): void {
    $found = [];
    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(__DIR__ . '/../src', FilesystemIterator::SKIP_DOTS)
    ) as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $path = str_replace('\\', '/', $file->getPathname());
        $code = boundarySrc('src/' . substr($path, strpos($path, '/src/') + 5));
        if (preg_match('/(?<![a-zA-Z0-9_>])mail\s*\(/', $code) === 1) {
            $found[] = basename($path);
        }
    }
    assertSame(['ProductionMailNotifier.php'], $found, 'mail() の使用箇所が違う: ' . implode(',', $found));

    // dev / bin / tests からも直接は呼ばない
    foreach (['bin/intake-backup.php', 'bin/intake-preflight.php', 'public/index.php'] as $relative) {
        $code = boundarySrc($relative);
        assertTrue(preg_match('/(?<![a-zA-Z0-9_>])mail\s*\(/', $code) !== 1, $relative . ' が mail() を呼んでいる');
    }
});

/* ============================================================ preflight */

test('preflight: 正式領域と重なる指定を受け付けない', function (): void {
    $layout = boundaryLayout();
    $db     = $layout['private'] . '/intake.sqlite';
    file_put_contents($db, 'x');

    $cases = [
        $layout['private']                => 'is_production_root',
        $layout['private'] . '/inside'    => 'inside_production_root',
        $layout['app']                    => 'contains_production_root',
    ];
    foreach ($cases as $root => $expected) {
        if (!is_dir($root)) {
            mkdir($root, 0700, true);
        }
        $area = new PreflightArea($root, $layout['private'], $db);
        $result = $area->check();
        assertSame(false, $result['ok'], $root . ' が通ってしまう');
        assertSame($expected, $result['error'], $root . ' の拒否理由が違う');
    }

    // 正しい preflight は通る
    $ok = new PreflightArea($layout['preflight'], $layout['private'], $db);
    assertSame(true, $ok->check()['ok'], '正しい preflight が通らない: ' . (string)($ok->check()['error'] ?? ''));
});

test('preflight: 未設定・相対・公開領域・ホーム直下・symlink を拒否する', function (): void {
    $layout = boundaryLayout();
    $db     = $layout['private'] . '/intake.sqlite';

    foreach ([
        null                        => 'not_configured',
        ''                          => 'not_configured',
        'preflight'                 => 'relative',
        '/var/www/public_html/pre'  => 'public_area',
        '/home/someone'             => 'home_root',
        '/pre'                      => 'too_shallow',
    ] as $root => $expected) {
        $area = new PreflightArea($root === null ? null : (string)$root, $layout['private'], $db);
        assertSame($expected, $area->check()['error'], var_export($root, true) . ' の拒否理由が違う');
    }

    // symlink（作れる環境でのみ実測。作れなければコードで担保されていることを見る）
    $link = $layout['base'] . '/pre-link';
    if (@symlink($layout['preflight'], $link) && is_link($link)) {
        $area = new PreflightArea($link, $layout['private'], $db);
        assertSame('symlink', $area->check()['error'], 'symlink が通ってしまう');
        // ★Windows のディレクトリ symlink は rmdir で外す（unlink は「ディレクトリだ」と拒む）
        if (!@unlink($link)) {
            @rmdir($link);
        }
    } else {
        assertTrue(str_contains(boundarySrc('src/Support/PathPolicy.php'), 'is_link'), 'symlink を見ていない');
    }
});

test('preflight: 正式DB・正式バックアップと別実体である', function (): void {
    $layout = boundaryLayout();

    $prodDb  = $layout['private'] . '/intake.sqlite';
    $preDb   = $layout['preflight'] . '/intake.sqlite';
    mkdir($layout['preflight'] . '/backups', 0700, true);
    mkdir($layout['private'] . '/backups', 0700, true);
    file_put_contents($prodDb, 'production');
    file_put_contents($preDb, 'preflight ' . BD_MARKER);
    file_put_contents($layout['preflight'] . '/backups/intake-20260828-000000-aaaaaaaa.sqlite', 'pre ' . BD_MARKER);

    assertTrue(realpath($prodDb) !== realpath($preDb), '同じ実体を指している');
    assertTrue(!str_contains((string)file_get_contents($prodDb), BD_MARKER), '正式DBへ架空データが入っている');

    // 正式 backups へ preflight の世代・manifest が混ざっていない
    $prodBackups = array_values(array_diff(scandir($layout['private'] . '/backups') ?: [], ['.', '..']));
    assertSame([], $prodBackups, '正式バックアップに preflight の世代が入っている');
});

test('preflight: 既定は dry-run で、確認文字列が無ければ1件も消さない', function (): void {
    $layout = boundaryLayout();
    $prodDb = $layout['private'] . '/intake.sqlite';
    file_put_contents($prodDb, 'production');
    mkdir($layout['preflight'] . '/logs', 0700, true);
    file_put_contents($layout['preflight'] . '/intake.sqlite', 'pre');
    file_put_contents($layout['preflight'] . '/logs/intake.log', 'pre');

    $area = new PreflightArea($layout['preflight'], $layout['private'], $prodDb);

    $dry = $area->remove();
    assertSame(true, $dry['ok']);
    assertSame(true, $dry['dry_run'], '既定が dry-run でない');
    assertSame(0, $dry['removed'], 'dry-run で消した');
    assertTrue($dry['remaining'] >= 3, '対象を数えられていない');

    foreach (['', 'delete preflight area', 'DELETE PREFLIGHT'] as $wrong) {
        $result = $area->remove(true, $wrong);
        assertSame('confirm_mismatch', $result['error'] ?? '', '確認文字列「' . $wrong . '」で実行された');
        assertSame(0, $result['removed']);
    }
    assertTrue(is_file($layout['preflight'] . '/intake.sqlite'), 'preflight が消えた');
});

test('preflight: 削除しても正式DB・正式バックアップは無傷', function (): void {
    $layout = boundaryLayout();
    $prodDb = $layout['private'] . '/intake.sqlite';
    mkdir($layout['private'] . '/backups', 0700, true);
    file_put_contents($prodDb, 'production');
    file_put_contents($layout['private'] . '/backups/intake-20260828-000000-bbbbbbbb.sqlite', 'production backup');

    mkdir($layout['preflight'] . '/backups', 0700, true);
    mkdir($layout['preflight'] . '/logs', 0700, true);
    file_put_contents($layout['preflight'] . '/intake.sqlite', 'pre ' . BD_MARKER);
    file_put_contents($layout['preflight'] . '/backups/intake-20260828-000000-cccccccc.sqlite', 'pre ' . BD_MARKER);
    file_put_contents($layout['preflight'] . '/logs/intake.log', 'pre ' . BD_MARKER);
    file_put_contents($layout['preflight'] . '/intake-config.php', "<?php\nreturn [];\n");

    $area   = new PreflightArea($layout['preflight'], $layout['private'], $prodDb);
    $before = $area->inventory();
    assertSame(true, $before['ok']);
    assertTrue($before['files'] >= 4, '事前確認で対象を数えられていない');

    $result = $area->remove(true, PreflightArea::CONFIRM_REMOVE);
    assertSame(true, $result['ok'], '削除に失敗した: ' . (string)($result['error'] ?? ''));
    assertSame(0, $result['remaining'], '残存がある');
    assertTrue(!is_dir($layout['preflight']), 'preflight ディレクトリが残っている');

    // ★正式側は1バイトも触られていない
    assertSame('production', (string)file_get_contents($prodDb), '正式DBが変わった');
    assertSame('production backup',
        (string)file_get_contents($layout['private'] . '/backups/intake-20260828-000000-bbbbbbbb.sqlite'),
        '正式バックアップが変わった');
    assertTrue(is_dir($layout['private']), '正式領域が消えた');
});

test('preflight: 領域の外にあるファイルを消さない', function (): void {
    $layout  = boundaryLayout();
    $prodDb  = $layout['private'] . '/intake.sqlite';
    file_put_contents($prodDb, 'production');

    $outside = $layout['base'] . '/outside.txt';
    file_put_contents($outside, 'keep me');
    mkdir($layout['preflight'] . '/logs', 0700, true);
    file_put_contents($layout['preflight'] . '/logs/intake.log', 'pre');

    // symlink で外を指しても、たどらずリンクだけを外す
    $link      = $layout['preflight'] . '/link.txt';
    $hasSymlink = @symlink($outside, $link) && is_link($link);

    $area   = new PreflightArea($layout['preflight'], $layout['private'], $prodDb);
    $result = $area->remove(true, PreflightArea::CONFIRM_REMOVE);

    assertSame(true, $result['ok'], '削除に失敗した');
    assertTrue(is_file($outside), '領域の外のファイルを消した');
    assertSame('keep me', (string)file_get_contents($outside), '領域の外を書き換えた');
    if ($hasSymlink) {
        assertTrue(!is_link($link), 'symlink が残っている');
    }
});
