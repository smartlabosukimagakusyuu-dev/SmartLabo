<?php
/**
 * 本番前 hardening（HP-ONBOARDING-4F / P3-1〜P3-3）
 *
 * 4E の監査で P3 として残した3件を、**設定ファイルの形**として固定する。
 * 実機（XServer）で効くかどうかは 4H の確認事項であり、ここでは扱わない。
 */
declare(strict_types=1);

use SmartLabo\Intake\Http\Response;

function userIniText(): string
{
    return (string)file_get_contents(__DIR__ . '/../public/.user.ini');
}

function htaccessText(): string
{
    return (string)file_get_contents(__DIR__ . '/../public/.htaccess');
}

/** `.user.ini` の有効行（コメントを除く）を key => value で読む */
function userIniValues(): array
{
    $out = [];
    foreach (explode("\n", userIniText()) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, ';')) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $out[trim($parts[0])] = trim($parts[1]);
        }
    }

    return $out;
}

/** CSP 文字列 → ディレクティブ名 => 値の配列 */
function cspDirectives(string $csp): array
{
    $out = [];
    foreach (explode(';', $csp) as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        $tokens = preg_split('/\s+/', $part);
        $name   = array_shift($tokens);
        $out[$name] = $tokens;
    }
    ksort($out);

    return $out;
}

/** `dev/php.ini.example`の有効行を key => value で読む（4H-3） */
function devPhpIniValues(): array
{
    $out  = [];
    $text = (string)file_get_contents(__DIR__ . '/../dev/php.ini.example');
    foreach (explode("\n", $text) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, ';')) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $out[trim($parts[0])] = trim($parts[1]);
        }
    }

    return $out;
}

/* ==================================================== P3-1 expose_php */

test('P3-1: .user.ini が expose_php = Off を指定している', function (): void {
    $values = userIniValues();

    assertTrue(isset($values['expose_php']), '.user.ini に expose_php が無い');
    assertSame('Off', $values['expose_php'], 'expose_php が Off でない');
});

test('P3-1: 既存の PHP 設定を壊していない（SSOT §10.4.1）', function (): void {
    $values = userIniValues();

    assertSame('Off', $values['display_errors'] ?? null, 'display_errors が Off でない');
    assertSame('Off', $values['display_startup_errors'] ?? null, 'display_startup_errors が Off でない');
    assertSame('On', $values['log_errors'] ?? null, 'log_errors が On でない');
    assertSame('2M', $values['post_max_size'] ?? null, 'post_max_size が変わっている');

    // ★下の2行は SSOT §10.4.1 の必須項目ではない（§11.2-7 の上乗せ・4H-3 で是正）。
    //   `upload_max_filesize = 0` は「禁止」ではなく「上限なし」であり、
    //   実際にアップロードを止めるのは `max_file_uploads = 0` である。
    //   根拠は public/.user.ini のコメントと tests/test-security-static.php を参照。
    assertSame('1', $values['upload_max_filesize'] ?? null, 'upload_max_filesize が 1 でない');
    assertSame('0', $values['max_file_uploads'] ?? null, 'max_file_uploads が 0 でない');

    // error_log は配置時に実パスを入れる。**値をここへ書き込まない**（雛形のまま）
    assertTrue(!isset($values['error_log']), '.user.ini に error_log の実パスが書かれている');
    assertTrue(str_contains(userIniText(), 'error_log'), 'error_log の案内が消えている');
});

test('P3-1: .user.ini で効かない場合の二重の備えが .htaccess にある', function (): void {
    assertTrue(
        preg_match('/Header\s+always\s+unset\s+X-Powered-By/', htaccessText()) === 1,
        '.htaccess に X-Powered-By の除去が無い'
    );
});

test('P3-1: アプリ自身は X-Powered-By を付けない', function (): void {
    foreach (array_keys(Response::securityHeaders()) as $name) {
        assertTrue(
            strtolower($name) !== 'x-powered-by',
            'アプリが X-Powered-By を設定している'
        );
    }
    // 実応答（6種）にも出ていないこと
    $k = adminKernel();
    $k->cases->create('HP-202608-9100', '架空サロン ハードニング');

    $responses = [
        $k->app->handle(jsonPost('/session/start', ['token' => str_repeat('a', 43)])),
        $k->app->handle(jsonGet('/nowhere')),
        $k->app->handle(adminGet('/admin/login')),
        $k->app->handle(adminGet('/admin/')),
    ];
    foreach ($responses as $res) {
        foreach (array_keys($res->headers) as $name) {
            assertTrue(strtolower($name) !== 'x-powered-by', '応答に X-Powered-By がある');
        }
    }
});

/* ==================================================== P3-2 CSP */

test('P3-2: API の CSP に object-src / font-src / base-uri / form-action がある', function (): void {
    $d = cspDirectives(Response::CSP);

    assertSame(["'none'"], $d['object-src'] ?? null, "object-src 'none' が無い");
    assertSame(["'self'"], $d['font-src'] ?? null, "font-src 'self' が無い");
    assertSame(["'none'"], $d['base-uri'] ?? null, "base-uri 'none' が無い");
    assertSame(["'self'"], $d['form-action'] ?? null, "form-action 'self' が無い");
    assertSame(["'none'"], $d['frame-ancestors'] ?? null, "frame-ancestors 'none' が無い");
    assertSame(["'self'"], $d['default-src'] ?? null, "default-src 'self' が無い");
});

test('P3-2: 静的側（.htaccess）と API 側の CSP が完全に一致する', function (): void {
    preg_match('/Content-Security-Policy\s+"([^"]+)"/', htaccessText(), $m);
    assertTrue(isset($m[1]), '.htaccess に CSP が無い');

    assertSame(
        cspDirectives((string)$m[1]),
        cspDirectives(Response::CSP),
        '静的側と API 側の CSP が食い違っている'
    );
});

test('P3-2: img-src に data: を許していない（この画面群は画像を使わない）', function (): void {
    $d = cspDirectives(Response::CSP);
    assertSame(["'self'"], $d['img-src'] ?? null, "img-src が 'self' だけでない");

    // 画面側にも data: URI・<img>・外部フォントが実在しないこと（許可を戻す理由が無い）
    $dir   = __DIR__ . '/../public';
    $found = [];
    $it    = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile() || !in_array($file->getExtension(), ['css', 'js', 'html'], true)) {
            continue;
        }
        $body = (string)file_get_contents($file->getPathname());
        foreach (['data:', '<img', '@font-face', '@import'] as $needle) {
            if (str_contains($body, $needle)) {
                $found[] = $file->getFilename() . ':' . $needle;
            }
        }
    }
    assertSame([], $found, '画面が data: / img / 外部フォントを使っている: ' . implode(', ', $found));
});

test('P3-2: unsafe-inline / unsafe-eval / ワイルドカードを使っていない', function (): void {
    preg_match('/Content-Security-Policy\s+"([^"]+)"/', htaccessText(), $m);

    foreach ([Response::CSP, (string)($m[1] ?? '')] as $csp) {
        foreach (["unsafe-inline", "unsafe-eval", "unsafe-hashes", ' *', "data:", "https:"] as $banned) {
            assertTrue(!str_contains($csp, $banned), 'CSP に ' . $banned . ' が入っている: ' . $csp);
        }
    }
});

test('P3-2: 実際の応答ヘッダーにも新しい CSP が載る', function (): void {
    $k = adminKernel();

    foreach ([
        $k->app->handle(jsonGet('/nowhere')),
        $k->app->handle(adminGet('/admin/login')),
        $k->app->handle(jsonPost('/submit', [], ['no_origin' => true])),
    ] as $res) {
        assertSame(Response::CSP, $res->headers['Content-Security-Policy'] ?? null, 'CSP が応答に無い');
        assertSame('nosniff', $res->headers['X-Content-Type-Options'] ?? null, 'nosniff が無い');
        assertSame('DENY', $res->headers['X-Frame-Options'] ?? null, 'X-Frame-Options が無い');
    }
});

/* ==================================================== P3-3 .htaccess */

test('P3-3: ドットファイルを拒否している', function (): void {
    assertTrue(
        preg_match('/<FilesMatch\s+"\^\\\\\."\s*>/', htaccessText()) === 1,
        '.htaccess にドットファイルの拒否が無い'
    );
});

test('P3-3: .well-known を壊していない（SSL 更新のため）', function (): void {
    $text = htaccessText();

    // ドットディレクトリの拒否に .well-known の除外がある
    assertTrue(
        str_contains($text, 'well-known'),
        '.well-known の除外が無い（証明書の自動更新が止まる）'
    );

    // 除外の形を実際に試す（Apache と同じ PCRE で判定する）
    $pattern = '#(^|/)\.(?!well-known(/|$))#';
    foreach (['/.env', '/.git/config', '/sub/.htaccess', '/.svn/entries'] as $blocked) {
        assertTrue(preg_match($pattern, $blocked) === 1, '拒否されるはずが通っている: ' . $blocked);
    }
    foreach (['/.well-known/acme-challenge/abc', '/.well-known/', '/assets/admin.css', '/start'] as $allowed) {
        assertTrue(preg_match($pattern, $allowed) !== 1, '通すはずが拒否されている: ' . $allowed);
    }
});

test('P3-3: DB・ログ・バックアップ・秘密鍵の拡張子を拒否している', function (): void {
    preg_match_all('/<FilesMatch\s+"([^"]+)"\s*>/', htaccessText(), $m);
    $patterns = $m[1];
    assertTrue($patterns !== [], '.htaccess に FilesMatch が無い');

    $mustBlock = [
        'intake.sqlite', 'intake.sqlite3', 'data.db', 'dump.sql', 'php-error.log',
        'index.php.bak', 'config.backup', 'index.php.old', 'index.php.orig',
        'notes.save', '.index.php.swp', 'index.php~', 'server.pem', 'server.key',
        'cert.crt', 'store.p12', 'settings.ini', 'app.conf', 'stack.yml', 'x.yaml',
        '.env', '.git', '.htaccess', '.user.ini',
        'composer.json', 'composer.lock', 'package.json',
        'intake-config.php', 'intake-config.example.php',
    ];
    foreach ($mustBlock as $name) {
        $blocked = false;
        foreach ($patterns as $p) {
            if (preg_match('/' . str_replace('/', '\/', $p) . '/', $name) === 1) {
                $blocked = true;
                break;
            }
        }
        assertTrue($blocked, '拒否されていない: ' . $name);
    }
});

test('P3-3: 配っているファイルを誤って拒否していない', function (): void {
    preg_match_all('/<FilesMatch\s+"([^"]+)"\s*>/', htaccessText(), $m);

    // 実際に public/ 配下で配っているもの（.htaccess / .user.ini は意図的に拒否する）
    $mustServe = ['index.php', 'start.html', 'form.html', 'admin.css', 'intake.css', 'form.js', 'schema.js'];
    foreach ($mustServe as $name) {
        foreach ($m[1] as $p) {
            assertTrue(
                preg_match('/' . str_replace('/', '\/', $p) . '/', $name) !== 1,
                '配布物が拒否されている: ' . $name . '（規則 ' . $p . '）'
            );
        }
    }
});

test('P3-3: 既存の規則（HTTPS強制・Options -Indexes・フロントコントローラ）が残っている', function (): void {
    $text = htaccessText();

    assertTrue(str_contains($text, 'Options -Indexes'), 'ディレクトリ一覧の拒否が消えている');
    assertTrue(str_contains($text, 'RewriteCond %{HTTPS} off'), 'HTTPS 強制が消えている');
    assertTrue(str_contains($text, 'RewriteRule ^ index.php [L]'), 'フロントコントローラが消えている');
    assertTrue(str_contains($text, '^start/?$'), '/start の配信規則が消えている');
    assertTrue(str_contains($text, '^form/?$'), '/form の配信規則が消えている');
});

test('P3-3: 拒否規則の順序が正しい（ドット拒否がフロントコントローラより前）', function (): void {
    $text = htaccessText();

    $deny  = strpos($text, 'well-known');
    $front = strpos($text, 'RewriteRule ^ index.php [L]');

    assertTrue($deny !== false && $front !== false, '規則が見つからない');
    assertTrue($deny < $front, 'ドット拒否がフロントコントローラより後ろにある（先に index.php へ吸われる）');
});

test('P3-3: 公開領域に、自分で拒否しているファイルが実在しない', function (): void {
    // .htaccess / .user.ini は「置くが読ませない」ので除く
    $allowedDotFiles = ['.htaccess', '.user.ini'];

    $dir   = __DIR__ . '/../public';
    $found = [];
    $it    = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $name = $file->getFilename();
        if (str_starts_with($name, '.') && !in_array($name, $allowedDotFiles, true)) {
            $found[] = $name;
        }
        if (preg_match('/\.(sqlite|sqlite3|db|sql|log|bak|backup|old|orig|save|swp|env|pem|key)$/', $name) === 1) {
            $found[] = $name;
        }
        if (str_ends_with($name, '~')) {
            $found[] = $name;
        }
    }
    assertSame([], $found, '公開領域に置いてはいけないファイルがある: ' . implode(', ', $found));
});

/* ==================================================== 4H-3 上限設定の一致 */

/*
 * 本番の `public/.user.ini` とローカル雛形 `dev/php.ini.example` で
 * body 上限とアップロード抑止の値がずれると、
 * ローカルで再現できない差が生まれる。両方を同じ値で固定する。
 */
test('4H-3: public/.user.ini と dev/php.ini.example の上限設定が一致する', function (): void {
    $user = userIniValues();
    $dev  = devPhpIniValues();

    foreach (['post_max_size', 'upload_max_filesize', 'max_file_uploads'] as $key) {
        assertTrue(isset($user[$key]), 'public/.user.ini に ' . $key . ' が無い');
        assertTrue(isset($dev[$key]), 'dev/php.ini.example に ' . $key . ' が無い');
        assertSame($user[$key], $dev[$key], $key . ' が .user.ini と php.ini.example でずれている');
    }
});
