<?php
/**
 * セキュリティ静的検査（HP-ONBOARDING-4E）
 *
 * 実行時のふるまいではなく、**ソースの形**を見張る。
 * 「いまは通っているが、次に誰かが壊しうる」場所を機械的に固定する。
 */
declare(strict_types=1);

use SmartLabo\Intake\Migrator;

/** @return array<string,string> src 配下の PHP */
function srcFiles(): array
{
    $out = [];
    $it  = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(__DIR__ . '/../src', FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $out[str_replace('\\', '/', $file->getPathname())] = (string)file_get_contents($file->getPathname());
        }
    }

    return $out;
}

/** PHP のコメントを取り除く（規約の説明文を検査対象にしない） */
function stripPhpComments(string $code): string
{
    $out = '';
    foreach (token_get_all($code) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        $out .= is_array($token) ? $token[1] : $token;
    }

    return $out;
}

/* ==================================================== SQL */

test('静的: 値を SQL 文字列へ連結していない（prepared statement のみ）', function (): void {
    foreach (srcFiles() as $path => $code) {
        $body = stripPhpComments($code);

        // query() の引数に変数が入っていない（PRAGMA の表名だけは別テストで固定する）
        preg_match_all('/->query\(([^;]*?)\);/s', $body, $m);
        foreach ($m[1] as $arg) {
            if (str_contains($arg, 'PRAGMA')) {
                continue; // Migrator::hasColumn。無害化を別テストで検査する
            }
            assertTrue(
                !str_contains($arg, '$'),
                basename($path) . ' が query() へ変数を渡している: ' . trim(substr($arg, 0, 60))
            );
        }

        // prepare() の中で `. $変数 .` を使う場合、変数は allowlist 由来でなければならない
        preg_match_all("/prepare\(\s*\n?\s*'[^']*'\s*\.\s*([a-zA-Z_\$>\-\(\)\[\]:', ]+)/", $body, $p);
        foreach ($p[1] as $expr) {
            assertTrue(
                str_contains($expr, 'implode'),
                basename($path) . ' が prepare() へ生の変数を連結している: ' . trim($expr)
            );
        }
    }
});

test('静的: PRAGMA の表名は呼び出し側のリテラルだけ', function (): void {
    $migrator = stripPhpComments((string)file_get_contents(__DIR__ . '/../src/Migrator.php'));

    // hasColumn の呼び出しはリテラルのみ
    preg_match_all("/hasColumn\(\\\$pdo,\s*'([a-z_]+)'/", $migrator, $m);
    assertTrue(count($m[1]) >= 2, 'hasColumn の呼び出しが見つからない');
    foreach ($m[1] as $table) {
        assertTrue(preg_match('/^intake_[a-z_]+$/', $table) === 1, '表名がリテラルでない: ' . $table);
    }

    // 引用符を落としてから埋め込んでいる（二重の保険）
    assertTrue(str_contains($migrator, "str_replace(\"'\", '', \$table)"), '表名の無害化が無い');
});

test('静的: ORDER BY / LIMIT を利用者入力から組み立てない', function (): void {
    foreach (srcFiles() as $path => $code) {
        $body = stripPhpComments($code);

        // ORDER BY の直後に変数が来ていない
        assertTrue(
            preg_match('/ORDER BY[^\'"]*\$/', $body) !== 1,
            basename($path) . ' が ORDER BY を変数で組み立てている'
        );
        // LIMIT は bindValue(PARAM_INT) で渡す
        preg_match_all('/LIMIT\s+\$/', $body, $m);
        assertSame(0, count($m[0]), basename($path) . ' が LIMIT を変数で組み立てている');
    }
});

/* ==================================================== 危険な関数 */

test('静的: 危険な関数を使っていない', function (): void {
    foreach (srcFiles() as $path => $code) {
        $body = stripPhpComments($code);

        // ★単語境界で見る。$pdo->exec()（PDO の SQL 実行）を shell の exec() と混同しない
        foreach ([
            'eval', 'assert', 'system', 'exec', 'shell_exec', 'passthru', 'popen',
            'proc_open', 'pcntl_exec', 'create_function', 'unserialize', 'extract',
        ] as $banned) {
            assertTrue(
                preg_match('/(?<![a-zA-Z0-9_>])' . $banned . '\s*\(/', $body) !== 1,
                basename($path) . ' が ' . $banned . '() を使っている'
            );
        }
        // 可変変数・スーパーグローバル経由の呼び出し
        foreach (['$$', 'call_user_func_array($_', 'call_user_func($_'] as $banned) {
            assertTrue(!str_contains($body, $banned), basename($path) . ' が ' . $banned . ' を使っている');
        }

        // 外部へ出ていく関数（このアプリは外部通信しない）。
        // ★単語境界で見る。driveSharedEmail( の "mail(" のような部分一致を拾わない
        foreach (['curl_init', 'curl_exec', 'fsockopen', 'stream_socket_client', 'mail'] as $banned) {
            assertTrue(
                preg_match('/(?<![a-zA-Z0-9_>])' . $banned . '\s*\(/', $body) !== 1,
                basename($path) . ' が ' . $banned . '() を使っている'
            );
        }
        // http(s) を直接取りに行かない
        assertTrue(
            preg_match('/file_get_contents\(\s*[\'"]https?:/', $body) !== 1,
            basename($path) . ' が外部URLを取得しようとしている'
        );
    }
});

test('静的: スーパーグローバルを入口以外で読まない', function (): void {
    foreach (srcFiles() as $path => $code) {
        if (str_ends_with($path, 'Http/Request.php')) {
            continue; // 入口だけが読んでよい
        }
        $body = stripPhpComments($code);

        foreach (['$_GET', '$_POST', '$_COOKIE', '$_REQUEST', '$_FILES', '$_SERVER'] as $global) {
            assertTrue(!str_contains($body, $global),
                basename($path) . ' がスーパーグローバル ' . $global . ' を直接読んでいる');
        }
    }
});

test('静的: ファイルアップロードを一切扱わない（SSOT §11.2-7）', function (): void {
    foreach (srcFiles() as $path => $code) {
        $body = stripPhpComments($code);
        foreach (['$_FILES', 'move_uploaded_file', 'is_uploaded_file'] as $banned) {
            assertTrue(!str_contains($body, $banned), basename($path) . ' が ' . $banned . ' を使っている');
        }
    }
});

/* ==================================================== 秘密値の取り扱い */

test('静的: 秘密値をログへ渡す経路が無い', function (): void {
    foreach (srcFiles() as $path => $code) {
        $body = stripPhpComments($code);

        // logger->xxx( ... ) の引数に危険なキーが無い
        preg_match_all('/logger->(?:info|warn|error)\(([^;]*?)\);/s', $body, $m);
        foreach ($m[1] as $args) {
            foreach ([
                'token', 'secret', 'csrf', 'password', 'submission_id',
                'message', 'requested_paths', 'drive', 'email', 'admin_id',
            ] as $risky) {
                assertTrue(
                    !preg_match("/'" . $risky . "'\s*=>/", $args),
                    basename($path) . ' がログへ ' . $risky . ' を渡している'
                );
            }
        }
    }
});

test('静的: 監査へ渡すのは決められた引数だけ', function (): void {
    $audit = stripPhpComments((string)file_get_contents(__DIR__ . '/../src/Service/Audit.php'));

    // record() は case_id / event / result_code / ip_hmac の4つだけ
    assertTrue(
        preg_match('/function record\(\s*\?int \$caseId,\s*string \$eventType,\s*string \$resultCode,\s*\?string \$ipHmac/', $audit) === 1,
        'Audit::record の引数が変わっている'
    );
    // INSERT する列も4＋時刻だけ
    preg_match('/INSERT INTO intake_audit_events \(([^)]*)\)/', $audit, $m);
    $cols = array_map('trim', explode(',', $m[1] ?? ''));
    assertSame(['intake_case_id', 'event_type', 'result_code', 'ip_hmac', 'created_at'], $cols,
        '監査へ書く列が変わっている');
});

test('静的: 平文の秘密値を DB へ書く経路が無い', function (): void {
    foreach (srcFiles() as $path => $code) {
        $body = stripPhpComments($code);

        // INSERT の**列リスト**に平文の秘密値が無い（実行時の変数名は見ない）
        preg_match_all('/INSERT INTO\s+(intake_\w+)\s*\(([^)]*)\)/s', $body, $m, PREG_SET_ORDER);
        foreach ($m as $hit) {
            foreach (array_map('trim', explode(',', $hit[2])) as $col) {
                $lower = strtolower($col);
                foreach (['token', 'session', 'csrf', 'password', 'secret'] as $risky) {
                    if (!str_contains($lower, $risky)) continue;
                    $safe = str_ends_with($lower, '_hash') || str_ends_with($lower, '_enc')
                        || in_array($lower, ['token_id', 'intake_case_id'], true);
                    assertTrue($safe, basename($path) . ' が ' . $hit[1] . ' へ平文列 ' . $col . ' を書いている');
                }
            }
        }
        // UPDATE の SET 句も同様
        preg_match_all("/UPDATE\\s+(intake_\\w+)\\s+SET\\s+([^']*)/s", $body, $u, PREG_SET_ORDER);
        foreach ($u as $hit) {
            foreach (['token_plain', 'session_plain', 'csrf_plain', 'password_plain'] as $bad) {
                assertTrue(!str_contains(strtolower($hit[2]), $bad),
                    basename($path) . ' が ' . $hit[1] . ' へ ' . $bad . ' を書いている');
            }
        }
    }
});

/* ==================================================== 出力 */

test('静的: 例外の内容を外部へ出さない', function (): void {
    foreach (srcFiles() as $path => $code) {
        $body = stripPhpComments($code);

        // getMessage() を応答・画面へ流していない
        preg_match_all('/(Response::\w+|View::\w+)\(([^;]*?)\)/s', $body, $m);
        foreach ($m[2] as $args) {
            assertTrue(!str_contains($args, 'getMessage()'),
                basename($path) . ' が例外メッセージを応答へ出している');
            assertTrue(!str_contains($args, 'getTraceAsString'),
                basename($path) . ' がスタックトレースを応答へ出している');
        }

        // echo / print / var_dump をアプリ本体で使わない（index.php の fail closed 応答は別）
        if (!str_ends_with($path, 'public/index.php')) {
            foreach (['var_dump(', 'print_r(', 'var_export('] as $banned) {
                assertTrue(!str_contains($body, $banned), basename($path) . ' が ' . $banned . ' を使っている');
            }
        }
    }
});

test('静的: 管理画面の HTML 組み立ては View に閉じている', function (): void {
    $view  = stripPhpComments((string)file_get_contents(__DIR__ . '/../src/Admin/View.php'));
    $admin = stripPhpComments((string)file_get_contents(__DIR__ . '/../src/Admin/AdminApp.php'));

    // エスケープの実装は View だけ
    assertTrue(str_contains($view, 'htmlspecialchars'), 'View にエスケープが無い');
    assertTrue(str_contains($view, 'ENT_QUOTES | ENT_SUBSTITUTE'), 'ENT_QUOTES/ENT_SUBSTITUTE を使っていない');
    assertTrue(!str_contains($admin, 'htmlspecialchars'), 'AdminApp が直接エスケープしている');

    // AdminApp が HTML へ埋めてよいのは
    //   (a) 組み立て済みの HTML 断片（View:: を通して作ったもの）
    //   (b) $this->xxx() の戻り（内部で View:: を通す）
    // のどちらかだけ。生の利用者入力を直接連結していないことを見る。
    preg_match_all("/'<[a-z][^']*'\s*\.\s*\\$([a-zA-Z_]+)(->[a-zA-Z_]+\()?/", $admin, $m, PREG_SET_ORDER);
    $safeLocals = ['csrf', 'number', 'status', 'notice', 'actions', 'items', 'paths',
                   'body', 'html', 'exportHtml', 'auditTotal', 'rows', 'out'];
    foreach ($m as $hit) {
        $var    = $hit[1];
        $isCall = isset($hit[2]) && $hit[2] !== '';
        assertTrue(
            ($var === 'this' && $isCall) || in_array($var, $safeLocals, true),
            'AdminApp が HTML へ直接埋めている変数がある: $' . $var
        );
    }

    // 利用者入力が入りうる値（form / query 由来）を直接 HTML へ連結していない
    foreach (['$form[', '$req->query['] as $rawInput) {
        assertTrue(
            preg_match('/' . preg_quote($rawInput, '/') . "[^;]{0,80}\.\s*'</", $admin) !== 1,
            'AdminApp が利用者入力を HTML へ直接連結している: ' . $rawInput
        );
    }
});

/* ==================================================== 配置・境界 */

test('静的: 公開領域の PHP はフロントコントローラだけ', function (): void {
    $publicDir = __DIR__ . '/../public';
    $php       = [];
    $it        = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($publicDir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $php[] = $file->getFilename();
        }
    }
    assertSame(['index.php'], $php, '公開領域に想定外の PHP がある: ' . implode(', ', $php));
});

test('静的: dev / private のファイルが公開領域から参照されない', function (): void {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(__DIR__ . '/../public', FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if (!$file->isFile()) continue;
        // .htaccess は「拒否する」ために名前を書く。保護なので対象外
        if ($file->getFilename() === '.htaccess') continue;

        $code = (string)file_get_contents($file->getPathname());

        foreach (['preview-env', 'preview-seed', 'router.php', 'intake-config', '.preview'] as $banned) {
            assertTrue(!str_contains($code, $banned),
                $file->getFilename() . ' が ' . $banned . ' を参照している');
        }
    }

    // .htaccess は「参照」ではなく「拒否」として書いていること
    $htaccess = (string)file_get_contents(__DIR__ . '/../public/.htaccess');
    assertTrue(
        preg_match('/FilesMatch[^>]*intake-config[^>]*>\s*Require all denied/s', $htaccess) === 1,
        '.htaccess が設定ファイルを拒否していない'
    );
});

test('静的: .htaccess がディレクトリ一覧と設定ファイルを止めている', function (): void {
    $htaccess = (string)file_get_contents(__DIR__ . '/../public/.htaccess');

    assertTrue(str_contains($htaccess, 'Options -Indexes'), 'ディレクトリ一覧を止めていない');
    assertTrue(str_contains($htaccess, 'Require all denied'), '設定ファイルを止めていない');
    assertTrue(str_contains($htaccess, 'RewriteCond %{HTTPS} off'), 'HTTPS 強制が無い');
    assertTrue(!str_contains($htaccess, 'Access-Control-Allow-Origin'), 'CORS を開けている');

    // 静的ファイルにも安全ヘッダーが付く
    foreach (['X-Content-Type-Options', 'X-Frame-Options', 'Referrer-Policy',
              'Cache-Control', 'Content-Security-Policy', 'X-Robots-Tag'] as $h) {
        assertTrue(str_contains($htaccess, $h), '.htaccess に ' . $h . ' が無い');
    }
});

test('静的: .user.ini が本番の必須設定を持つ（SSOT §10.4.1）', function (): void {
    $ini = (string)file_get_contents(__DIR__ . '/../public/.user.ini');

    assertTrue(preg_match('/^\s*display_errors\s*=\s*Off/mi', $ini) === 1, 'display_errors が Off でない');
    assertTrue(preg_match('/^\s*display_startup_errors\s*=\s*Off/mi', $ini) === 1, 'display_startup_errors が Off でない');
    assertTrue(preg_match('/^\s*log_errors\s*=\s*On/mi', $ini) === 1, 'log_errors が On でない');
    assertTrue(preg_match('/^\s*upload_max_filesize\s*=\s*0/mi', $ini) === 1, 'アップロードが無効化されていない');
});

/* ==================================================== schema */

test('静的: DDL に平文の秘密値を持つ列が無い', function (): void {
    $sql = implode(' ', Migrator::allStatements());

    // 列名として token / session / csrf / password が出るのは hash / enc / id のときだけ
    preg_match_all('/^\s*([a-z_]+)\s+(TEXT|BLOB|INTEGER)/mi', $sql, $m);
    foreach ($m[1] as $col) {
        $lower = strtolower($col);
        foreach (['token', 'session', 'csrf', 'password', 'secret'] as $risky) {
            if (!str_contains($lower, $risky)) continue;
            $safe = str_ends_with($lower, '_hash') || str_ends_with($lower, '_enc')
                || in_array($lower, ['token_id', 'intake_case_id'], true);
            assertTrue($safe, 'DDL に平文らしき列がある: ' . $col);
        }
    }

    // Drive の URL とメールは必ず暗号文列
    assertTrue(str_contains($sql, 'drive_folder_url_enc'), 'Drive URL の暗号文列が無い');
    assertTrue(str_contains($sql, 'drive_shared_email_enc'), '共有メールの暗号文列が無い');
    assertTrue(!preg_match('/drive_folder_url\s+TEXT/', $sql), 'Drive URL の平文列がある');
    assertTrue(!preg_match('/drive_shared_email\s+TEXT/', $sql), '共有メールの平文列がある');
});

test('静的: SSOT の版とコードの版が揃っている', function (): void {
    $ssot   = (string)file_get_contents(__DIR__ . '/../../docs/website/HP_ONBOARDING_INTAKE_DATA_MODEL_V1.md');
    $readme = (string)file_get_contents(__DIR__ . '/../README.md');

    preg_match('/VERSION\s+:\s+v(\d+\.\d+)/', $ssot, $m);
    $version = $m[1] ?? '';
    assertSame('1.9', $version, 'SSOT の版が想定と違う');
    assertTrue(str_contains($readme, 'v1.9'), 'README が SSOT の版を指していない');

    // 8表・スキーマ版4
    assertSame(4, Migrator::SCHEMA_VERSION, 'スキーマ版が想定と違う');
});
