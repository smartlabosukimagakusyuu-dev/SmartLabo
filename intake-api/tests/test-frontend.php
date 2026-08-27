<?php
/**
 * 店舗向け入力画面の静的な取り決め（HP-ONBOARDING-4C）。
 *
 * ふるまいのテストは tests/js/（Node で実行）にある。
 * こちらは「**配るファイルに入っていてはいけないもの**」を、PHP のテストから見張る。
 * Node が無い環境でも、この一線は必ず検査される。
 */
declare(strict_types=1);

use SmartLabo\Intake\Config;
use SmartLabo\Intake\ConfigException;

function publicDir(): string
{
    return __DIR__ . '/../public';
}

/** @return array<string,string> パス => 中身 */
function frontendSources(string $ext): array
{
    $out = [];
    $it  = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(publicDir(), FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === $ext) {
            $out[str_replace('\\', '/', $file->getPathname())] = (string)file_get_contents($file->getPathname());
        }
    }

    return $out;
}

/** コメントを取り除く（規約の説明文まで検査対象にしないため） */
function stripJsComments(string $code): string
{
    $noBlock = preg_replace('#/\*.*?\*/#s', '', $code);

    return (string)preg_replace('#^\s*//.*$#m', '', (string)$noBlock);
}

/* ------------------------------------------------------------ 保存領域 */

test('画面: 回答本文・token を保存領域へ書かない（SSOT §6.6）', function (): void {
    foreach (frontendSources('js') as $path => $code) {
        $body = stripJsComments($code);
        foreach (['localStorage', 'sessionStorage', 'document.cookie', 'indexedDB'] as $banned) {
            assertTrue(
                !str_contains($body, $banned),
                basename($path) . ' が ' . $banned . ' を使っている'
            );
        }
    }
});

/* ------------------------------------------------------------ HTML 解釈 */

test('画面: 利用者入力を HTML として解釈する経路を作らない（SSOT §10.3）', function (): void {
    foreach (frontendSources('js') as $path => $code) {
        $body = stripJsComments($code);
        foreach (['innerHTML', 'outerHTML', 'insertAdjacentHTML', 'document.write', 'createContextualFragment'] as $banned) {
            assertTrue(
                !str_contains($body, $banned),
                basename($path) . ' が ' . $banned . ' を使っている'
            );
        }
    }
});

test('画面: eval と同等のものを使わない', function (): void {
    foreach (frontendSources('js') as $path => $code) {
        $body = stripJsComments($code);
        foreach (['eval(', 'new Function(', 'setTimeout("', "setTimeout('"] as $banned) {
            assertTrue(
                !str_contains($body, $banned),
                basename($path) . ' が ' . $banned . ' を使っている'
            );
        }
    }
});

/* ------------------------------------------------------------ 出力 */

test('画面: console へ出力しない（回答本文・PII・提出キーを漏らさない）', function (): void {
    foreach (frontendSources('js') as $path => $code) {
        $body = stripJsComments($code);
        assertTrue(
            preg_match('/\bconsole\s*\.\s*(log|info|warn|error|debug|trace|dir|table)\s*\(/', $body) !== 1,
            basename($path) . ' が console へ出力している'
        );
    }
});

test('画面: 計測・解析を入れない', function (): void {
    foreach (array_merge(frontendSources('js'), frontendSources('html')) as $path => $code) {
        foreach (['gtag(', 'dataLayer', 'googletagmanager', 'google-analytics', 'ga("send"', 'hotjar', 'clarity.ms'] as $banned) {
            assertTrue(
                !str_contains($code, $banned),
                basename($path) . ' に計測タグらしきもの（' . $banned . '）がある'
            );
        }
    }
});

/* ------------------------------------------------------------ 外部リソース */

test('画面: 外部CDN・外部フォントを読み込まない（SSOT §10.5）', function (): void {
    foreach (frontendSources('html') as $path => $html) {
        preg_match_all('/\b(?:src|href)\s*=\s*"([^"]*)"/i', $html, $m);
        foreach ($m[1] as $url) {
            assertTrue(
                !preg_match('#^(https?:)?//#i', $url),
                basename($path) . ' が外部を参照している: ' . $url
            );
        }
    }

    foreach (frontendSources('css') as $path => $css) {
        assertTrue(!str_contains($css, '@import'), basename($path) . ' が @import を使っている');
        preg_match_all('/url\(\s*[\'"]?([^\'")]+)/i', $css, $m);
        foreach ($m[1] as $url) {
            assertTrue(
                !preg_match('#^(https?:)?//#i', $url),
                basename($path) . ' が外部の素材を参照している: ' . $url
            );
        }
    }
});

test('画面: 通信先は同一オリジンの相対パスだけ', function (): void {
    foreach (frontendSources('js') as $path => $code) {
        $body = stripJsComments($code);
        assertTrue(
            preg_match('#fetch\s*\(\s*[\'"`]https?://#i', $body) !== 1,
            basename($path) . ' が外部へ通信しようとしている'
        );
    }
});

/* ------------------------------------------------------------ HTML の作り */

test('画面: インラインスクリプトを置かない（CSP に合わせる）', function (): void {
    foreach (frontendSources('html') as $path => $html) {
        preg_match_all('#<script\b([^>]*)>(.*?)</script>#is', $html, $m, PREG_SET_ORDER);
        foreach ($m as $hit) {
            assertTrue(str_contains($hit[1], 'src='), basename($path) . ' にインラインスクリプトがある');
            assertTrue(trim($hit[2]) === '', basename($path) . ' の script に中身がある');
        }
        assertTrue(preg_match('/\son[a-z]+\s*=/i', $html) !== 1, basename($path) . ' に on... 属性がある');
    }
});

test('画面: インラインスタイルを使わない（CSP の style-src に弾かれる）', function (): void {
    foreach (frontendSources('html') as $path => $html) {
        assertTrue(
            preg_match('/\sstyle\s*=\s*"/i', $html) !== 1,
            basename($path) . ' に style 属性がある'
        );
        preg_match_all('#<style\b#i', $html, $m);
        assertTrue(count($m[0]) === 0, basename($path) . ' にインラインの style 要素がある');
    }

    foreach (frontendSources('js') as $path => $code) {
        $body = stripJsComments($code);
        assertTrue(
            preg_match('/\.style\.[A-Za-z]+\s*=/', $body) !== 1,
            basename($path) . ' が style を直接書き換えている'
        );
        assertTrue(
            !str_contains($body, 'setProperty('),
            basename($path) . ' が style.setProperty を使っている'
        );
        assertTrue(
            preg_match('/[\'"]style[\'"]\s*:/', $body) !== 1,
            basename($path) . ' が style 属性を組み立てている'
        );
    }
});

test('画面: 検索結果へ出さない指定がある（SSOT §4.8）', function (): void {
    foreach (frontendSources('html') as $path => $html) {
        assertTrue(
            preg_match('/<meta\s+name="robots"\s+content="[^"]*noindex/i', $html) === 1,
            basename($path) . ' に noindex が無い'
        );
    }
});

test('画面: JavaScript 無効時の案内があり、値を別URLへ移さない', function (): void {
    foreach (frontendSources('html') as $path => $html) {
        assertTrue(str_contains($html, '<noscript>'), basename($path) . ' に noscript が無い');
        assertTrue(
            str_contains($html, 'JavaScriptを有効にしてください'),
            basename($path) . ' の案内文が違う'
        );
        preg_match('#<noscript>(.*?)</noscript>#is', $html, $m);
        assertTrue(
            !preg_match('/<(a|form|meta)\b/i', $m[1] ?? ''),
            basename($path) . ' の noscript が別URLへ誘導している'
        );
    }
});

test('画面: 内部用語を画面へ出さない', function (): void {
    foreach (frontendSources('html') as $path => $html) {
        // タグ・属性を除いた「見える文字」だけを見る
        $visible = trim((string)preg_replace('/<[^>]*>/', ' ', $html));
        foreach (['token', 'session', 'HP Intake', 'submission_id', 'intake'] as $jargon) {
            assertTrue(
                stripos($visible, $jargon) === false,
                basename($path) . ' の画面文言に内部用語（' . $jargon . '）がある'
            );
        }
    }
});

/* ------------------------------------------------------------ 入口の扱い */

test('画面: ご案内リンクの値を query や path へ移す経路が無い', function (): void {
    $entry = (string)file_get_contents(publicDir() . '/assets/lib/entry.js');
    $body  = stripJsComments($entry);

    assertTrue(str_contains($body, 'replaceState'), 'URL から消す処理が無い');
    assertTrue(!str_contains($body, 'searchParams.set'), 'query へ移そうとしている');
    assertTrue(!str_contains($body, 'location.href ='), 'URL を直接組み立てている');
});

test('画面: 提出キーを URL・保存領域へ出す経路が無い', function (): void {
    $form = stripJsComments((string)file_get_contents(publicDir() . '/assets/form.js'));

    assertTrue(str_contains($form, 'submission_id'), '提出キーを送っていない');
    assertTrue(
        preg_match('/submission_id[^\n]*(localStorage|sessionStorage|searchParams|textContent)/', $form) !== 1,
        '提出キーを保存・表示している'
    );
});

/* ------------------------------------------------------------ 画面と API */

test('画面: 4B の endpoint だけを呼ぶ', function (): void {
    $called = [];
    foreach (frontendSources('js') as $code) {
        preg_match_all('#\.(?:get|post)\(\s*[\'"](/[a-z/]+)[\'"]#', stripJsComments($code), $m);
        foreach ($m[1] as $path) {
            $called[$path] = true;
        }
    }

    $allowed = ['/session/start', '/session/logout', '/case', '/answers/save', '/submit'];
    foreach (array_keys($called) as $path) {
        assertTrue(in_array($path, $allowed, true), '未実装の endpoint を呼んでいる: ' . $path);
    }

    // 「入力を終了する」が logout を呼ぶこと（SSOT v1.3 §2.6-12）
    assertTrue(isset($called['/session/logout']), '入力を終了する導線が logout を呼んでいない');
    assertTrue(isset($called['/submit']), '提出の呼び出しが無い');
});

test('画面: 画像アップロードの入力欄を作らない（SSOT §11.2）', function (): void {
    foreach (array_merge(frontendSources('js'), frontendSources('html')) as $path => $code) {
        assertTrue(
            preg_match('/type:\s*[\'"]file[\'"]|type="file"/i', $code) !== 1,
            basename($path) . ' にファイル入力欄がある'
        );
        assertTrue(!str_contains($code, 'FormData('), basename($path) . ' がファイル送信を組み立てている');
    }
});

test('画面: Drive のフォルダ名に店舗名・氏名・連絡先を使わない（SSOT §7.1）', function (): void {
    $form = (string)file_get_contents(publicDir() . '/assets/form.js');

    foreach (['01_images', '02_logo', '03_documents', '04_references'] as $folder) {
        assertTrue(str_contains($form, $folder), '固定サブフォルダ ' . $folder . ' の案内が無い');
    }
    // 案件番号だけを使う（店舗名・氏名を組み込まない）
    assertTrue(
        preg_match('/caseNumber/', $form) === 1,
        'フォルダ名の案内が案件番号を使っていない'
    );
    assertTrue(
        preg_match('/(shop_display_name|legal_name|operator_name|internal_contact)[^\n]*フォルダ/u', $form) !== 1,
        'フォルダ名に店舗名・氏名・連絡先を使っている'
    );
});

/* ------------------------------------------------------------ ヘッダー */

test('画面: .htaccess が必要なヘッダーを付ける（SSOT §10.4）', function (): void {
    $htaccess = (string)file_get_contents(publicDir() . '/.htaccess');

    foreach ([
        "frame-ancestors 'none'",
        "default-src 'self'",
        "script-src 'self'",
        "object-src 'none'",
        'X-Content-Type-Options',
        'Referrer-Policy',
        'no-store',
        'Strict-Transport-Security',
        'noindex',
    ] as $needle) {
        assertTrue(str_contains($htaccess, $needle), '.htaccess に ' . $needle . ' が無い');
    }

    // CORS を開けていない
    assertTrue(
        !str_contains($htaccess, 'Access-Control-Allow-Origin'),
        '.htaccess が CORS を許可している'
    );
    // 画面のパスがフロントコントローラより先に振り分けられている
    assertTrue(
        strpos($htaccess, 'start.html') < strpos($htaccess, 'index.php [L]'),
        '画面の振り分けがフロントコントローラより後ろにある'
    );
});

test('画面: CSP が外部への通信・埋め込みを許さない', function (): void {
    $htaccess = (string)file_get_contents(publicDir() . '/.htaccess');
    preg_match('/Content-Security-Policy "([^"]+)"/', $htaccess, $m);
    $csp = $m[1] ?? '';

    assertTrue($csp !== '', 'CSP が無い');
    foreach (explode(';', $csp) as $directive) {
        $directive = trim($directive);
        if ($directive === '') continue;
        assertTrue(
            !str_contains($directive, '*') && !str_contains($directive, 'http'),
            'CSP が外部を許している: ' . $directive
        );
        assertTrue(
            !str_contains($directive, 'unsafe-inline') && !str_contains($directive, 'unsafe-eval'),
            'CSP が unsafe を許している: ' . $directive
        );
    }
});

/* ------------------------------------------------ ローカル確認用の設定 */

test('設定: 許可オリジンは既定で https のみ', function (): void {
    assertTrue(Config::originAcceptable('https://intake.smartlaboworks.com', true));

    foreach ([
        'http://intake.smartlaboworks.com',
        'http://127.0.0.1:8788',
        'http://localhost:8788',
        'http://evil.example',
    ] as $origin) {
        assertTrue(!Config::originAcceptable($origin, true), '本番設定で http を許している: ' . $origin);
    }
});

test('設定: ローカル確認のときだけ自分の端末の http を許す', function (): void {
    foreach (['http://127.0.0.1:8788', 'http://localhost:8788', 'http://[::1]:8788', 'http://127.0.0.1'] as $origin) {
        assertTrue(Config::originAcceptable($origin, false), 'ローカルの http を許していない: ' . $origin);
    }

    // ローカル確認中でも、外向きの http は許さない
    foreach ([
        'http://example.invalid',
        'http://intake.smartlaboworks.com',
        'http://127.0.0.1.evil.example',
        'http://localhost.evil.example',
        'http://192.168.0.10:8788',
        'http://127.0.0.1:8788/path',
        'http://127.0.0.1:8788@evil.example',
    ] as $origin) {
        assertTrue(!Config::originAcceptable($origin, false), 'ローカル確認中に許してはいけない: ' . $origin);
    }
});

test('設定: 本番の既定は https 強制のまま', function (): void {
    $config = Config::load([
        'db_path'     => tmpDir() . '/origin-check.sqlite',
        'ip_hmac_key' => TEST_IP_HMAC_KEY,
        'enc_key'     => TEST_ENC_KEY,
    ]);

    assertTrue($config->requireHttps, '既定で HTTPS を強制していない');
    assertSame(['https://intake.smartlaboworks.com'], $config->allowedOrigins);
});

test('設定: http のオリジンは require_https を切らない限り起動しない', function (): void {
    $thrown = false;
    try {
        Config::load([
            'db_path'         => tmpDir() . '/origin-reject.sqlite',
            'ip_hmac_key'     => TEST_IP_HMAC_KEY,
            'enc_key'         => TEST_ENC_KEY,
            'allowed_origins' => ['http://127.0.0.1:8788'],
        ]);
    } catch (ConfigException $e) {
        $thrown = true;
    }
    assertTrue($thrown, 'HTTPS 強制のまま http のオリジンを受け入れている');
});

test('設定: ローカル確認用の設定は public の外にある', function (): void {
    foreach (['preview-env.php', 'router.php', 'preview-seed.php'] as $name) {
        assertTrue(is_file(__DIR__ . '/../dev/' . $name), 'dev/' . $name . ' が無い');
        assertTrue(!is_file(publicDir() . '/' . $name), $name . ' が公開領域にある');
    }
});

test('設定: ローカル確認用DBを tests/.tmp へ置かない', function (): void {
    // run-tests.php は毎回 tests/.tmp を空にする。
    // そこへ置くと、テストを流すたびに確認中の案件が消えてしまう。
    require_once __DIR__ . '/../dev/preview-env.php';

    $preview = str_replace('\\', '/', (string)realpath(previewBaseDir()));
    $testTmp = str_replace('\\', '/', (string)realpath(tmpDir()));

    assertTrue($preview !== '', 'ローカル確認用の置き場所が作れない');
    assertTrue(
        strncmp($preview, $testTmp, strlen($testTmp)) !== 0,
        'ローカル確認用DBが tests/.tmp の中にある（テスト実行で消える）: ' . $preview
    );

    // .gitignore で追跡外になっていること
    $ignore = (string)file_get_contents(__DIR__ . '/../.gitignore');
    assertTrue(str_contains($ignore, 'dev/.preview/'), 'ローカル確認用DBが Git 管理外になっていない');
});
