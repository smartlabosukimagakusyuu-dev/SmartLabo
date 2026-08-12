<?php
/**
 * HTTP統合テスト（SALES-1）
 * ============================================================================
 * PHPの内蔵サーバーを起動し、実際に POST /api/signup を叩いて
 * ステータスコード・共通レスポンス形式・拒否条件を確認する。
 *
 * 実行:
 *   php signup-api/tests/test-http.php
 *   終了コード 0 = 全件成功 / 1 = 失敗あり
 *
 * 一時的な設定ファイル（署名鍵入り）はテスト用の一時ディレクトリに作り、
 * 終了時に必ず削除する。リポジトリには残さない。
 * ============================================================================
 */

declare(strict_types=1);

$root    = dirname(__DIR__);                 // signup-api/
$docRoot = $root . '/public';
$tmpBase = sys_get_temp_dir() . '/sls-http-' . bin2hex(random_bytes(4));

/**
 * 空いているポートを選ぶ。
 * 固定ポートにすると、前回のテストで残ったサーバーが居座っていた場合に
 * 起動失敗へ気づけないまま待ち続けてしまう（Windowsでは php -S の子プロセスが
 * 残ることがある）。毎回OSに空きポートを選ばせて、その事故を避ける。
 */
function pickFreePort(): int
{
    $sock = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    if ($sock === false) {
        fwrite(STDERR, "空きポートを取得できませんでした: {$errstr}\n");
        exit(1);
    }
    $name = stream_socket_get_name($sock, false);
    fclose($sock);
    return (int)substr((string)$name, strrpos((string)$name, ':') + 1);
}

$port   = pickFreePort();
$origin = 'http://127.0.0.1:' . $port;

$passed = 0;
$failed = [];

function ok(string $name, bool $cond, string $detail = ''): void
{
    global $passed, $failed;
    if ($cond) { $passed++; } else { $failed[] = $name . ($detail !== '' ? "  ({$detail})" : ''); }
}

/* --------------------------------------------------------------------------
   テスト用の設定を用意する。
   signup-api/private/ には触らない（実運用の設定を壊さないため）。
   公開ディレクトリを一時フォルダへ丸ごと複製し、その1つ上に private を置く。
   -------------------------------------------------------------------------- */

function copyTree(string $src, string $dst): void
{
    @mkdir($dst, 0777, true);
    foreach (scandir($src) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') { continue; }
        $from = $src . '/' . $entry;
        $to   = $dst . '/' . $entry;
        if (is_dir($from)) { copyTree($from, $to); } else { copy($from, $to); }
    }
}

function rmTree(string $dir): void
{
    if (!is_dir($dir)) { return; }
    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') { continue; }
        $path = $dir . '/' . $entry;
        if (is_dir($path)) { rmTree($path); } else { @unlink($path); }
    }
    @rmdir($dir);
}

copyTree($docRoot, $tmpBase . '/public');
@mkdir($tmpBase . '/private', 0700, true);

file_put_contents($tmpBase . '/private/signup-config.php', <<<PHP
<?php
return [
    'allowed_origins' => ['{$origin}'],
    'csrf_secret'     => 'test-csrf-secret-not-for-production',
    'ip_hash_secret'  => 'test-ip-secret-not-for-production',
    'min_seconds_before_submit' => 3,
    'rate_limit_max'    => 100,
    'rate_limit_window' => 600,
    'max_body_bytes'    => 20000,
    'require_csrf_always' => true,
    'mode' => 'test',
];
PHP);

/* ------------------------------- サーバー起動 ------------------------------- */

// サーバーの出力はパイプではなくファイルへ逃がす。
// パイプのままにすると、こちらが読み取らない間にバッファが埋まり、
// サーバー側の書き込みがブロックしてテストが止まることがある。
$descriptors = [
    1 => ['file', $tmpBase . '/server-out.log', 'a'],
    2 => ['file', $tmpBase . '/server-err.log', 'a'],
];
// コマンドは配列で渡す。文字列で渡すとWindowsでは cmd.exe 経由で起動され、
// proc_get_status() が返すPIDが cmd.exe のものになる。cmd.exe が先に終了すると
// php -S が親を失って生き残り、ポートを掴んだままテストのたびに溜まっていく。
// 配列形式ならシェルを挟まないため、php -S 自身のPIDを取得して確実に止められる。
$server = proc_open(
    [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $tmpBase . '/public'],
    $descriptors, $pipes
);

if (!is_resource($server)) {
    fwrite(STDERR, "内蔵サーバーを起動できませんでした\n");
    rmTree($tmpBase);
    exit(1);
}

register_shutdown_function(function () use ($server, $pipes, $tmpBase) {
    // Windowsでは proc_terminate() が php.exe 本体を取りこぼすことがあるため、
    // PIDを直接止めてからハンドルを閉じる。残るとポートを掴んだままになる。
    $info = @proc_get_status($server);
    foreach ($pipes as $p) { if (is_resource($p)) { fclose($p); } }
    proc_terminate($server);
    if (is_array($info) && !empty($info['pid'])) {
        if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
            @exec('taskkill /T /F /PID ' . (int)$info['pid'] . ' 2>NUL');
        } else {
            @exec('kill -9 ' . (int)$info['pid'] . ' 2>/dev/null');
        }
    }
    proc_close($server);
    rmTree($tmpBase);
});

// 起動待ち（最大5秒）。立ち上がらなければ待ち続けずに失敗させる。
$up = false;
for ($i = 0; $i < 50; $i++) {
    $conn = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
    if ($conn) { fclose($conn); $up = true; break; }
    usleep(100000);
}
if (!$up) {
    fwrite(STDERR, "内蔵サーバーが起動しませんでした（ポート {$port}）\n");
    exit(1);
}

/* --------------------------------- 補助関数 --------------------------------- */

/** リクエストを送り [status, body(array|null), rawHeaders] を返す */
function request(string $method, string $path, array $opts = []): array
{
    global $origin;

    $headers = ['Accept: application/json', 'X-Requested-With: fetch'];
    $body = null;

    if (array_key_exists('json', $opts)) {
        $headers[] = 'Content-Type: application/json';
        $body = json_encode($opts['json'], JSON_UNESCAPED_UNICODE);
    } elseif (array_key_exists('raw', $opts)) {
        $headers[] = 'Content-Type: ' . ($opts['content_type'] ?? 'text/plain');
        $body = $opts['raw'];
    }

    if (!array_key_exists('no_origin', $opts)) {
        $headers[] = 'Origin: ' . ($opts['origin'] ?? $origin);
    }

    $ctx = stream_context_create(['http' => [
        'method'        => $method,
        'header'        => implode("\r\n", $headers),
        'content'       => $body,
        'ignore_errors' => true,
        'timeout'       => 5,
    ]]);

    $raw = @file_get_contents($origin . $path, false, $ctx);
    $status = 0;
    if (isset($http_response_header[0]) &&
        preg_match('#HTTP/\S+\s+(\d+)#', $http_response_header[0], $m)) {
        $status = (int)$m[1];
    }
    $decoded = $raw === false ? null : json_decode($raw, true);
    return [$status, is_array($decoded) ? $decoded : null, $http_response_header ?? []];
}

/** 有効なCSRFトークンを取得する */
function token(): string
{
    [$status, $body] = request('GET', '/csrf-token.php');
    return ($status === 200 && isset($body['token'])) ? (string)$body['token'] : '';
}

/** 妥当な申し込み内容 */
function payload(array $override = []): array
{
    return array_merge([
        'company_name'        => '株式会社テスト商事',
        'company_kana'        => 'カブシキガイシャテストショウジ',
        'postal_code'         => '680-0000',
        'address'             => '鳥取県鳥取市テスト町1-2-3',
        'company_tel'         => '0857-00-0000',
        'contact_email'       => 'keiri@example.co.jp',
        'admin_name'          => '山田 太郎',
        'admin_email'         => 'taro@example.co.jp',
        'password'            => 'Kx9#mQ2vRt',
        'password_confirm'    => 'Kx9#mQ2vRt',
        'additional_accounts' => '2',
        // 画面表示から3秒以上経過した扱いにする
        'form_ts'             => (string)((time() - 30) * 1000),
    ], $override);
}

/* ============================== CSRFトークン ============================== */

$csrf = token();
ok('CSRF: トークンを発行できる', $csrf !== '' && substr_count($csrf, '.') === 2);

[$status] = request('POST', '/csrf-token.php', ['json' => []]);
ok('CSRF: POSTでの取得は405', $status === 405, "status={$status}");

/* ============================== 正常系 ============================== */

[$status, $body] = request('POST', '/signup.php', ['json' => payload(['csrf_token' => $csrf])]);
ok('正常系: 200が返る', $status === 200, "status={$status}");
ok('正常系: ok が true', ($body['ok'] ?? null) === true);
ok('正常系: result が ok', ($body['result'] ?? '') === 'ok');
ok('正常系: 保存していないことを応答で明示する', ($body['data']['persisted'] ?? null) === false);
ok('正常系: stage が validated', ($body['data']['stage'] ?? '') === 'validated');
ok('正常系: 月額合計をサーバーが計算する（追加2名=26,000円）',
   ($body['data']['quote']['monthly_total'] ?? 0) === 26000);
ok('正常系: 次の工程が未提供であることを示す',
   ($body['data']['next']['available'] ?? null) === false);
ok('正常系: meta.api_version がある', ($body['meta']['api_version'] ?? '') === '1');
ok('正常系: meta.request_id がある', !empty($body['meta']['request_id']));
ok('正常系: errors は空', $body['errors'] === [] || $body['errors'] === new stdClass() || empty((array)$body['errors']));

// 画面から改ざんした金額を送っても、サーバー側の計算が優先される
[$status, $body] = request('POST', '/signup.php', [
    'json' => payload(['csrf_token' => $csrf, 'monthly_total' => '1', 'additional_accounts' => '0']),
]);
ok('改ざん: 送られた金額を使わずサーバーが計算し直す',
   $status === 200 && ($body['data']['quote']['monthly_total'] ?? 0) === 20000);

/* ============================== 異常系: 入力 ============================== */

[$status, $body] = request('POST', '/signup.php', [
    'json' => payload(['csrf_token' => $csrf, 'company_name' => '']),
]);
ok('異常系: 必須未入力は422', $status === 422, "status={$status}");
ok('異常系: result が invalid', ($body['result'] ?? '') === 'invalid');
ok('異常系: 該当項目の理由コードが返る', ($body['errors']['company_name'] ?? '') === 'required');
ok('異常系: ok が false', ($body['ok'] ?? null) === false);

[$status, $body] = request('POST', '/signup.php', [
    'json' => payload(['csrf_token' => $csrf, 'admin_email' => 'bad-email']),
]);
ok('異常系: メール形式違いは422+invalid',
   $status === 422 && ($body['errors']['admin_email'] ?? '') === 'invalid');

[$status, $body] = request('POST', '/signup.php', [
    'json' => payload(['csrf_token' => $csrf, 'password' => 'short1!', 'password_confirm' => 'short1!']),
]);
ok('異常系: 短いパスワードは too_short', ($body['errors']['password'] ?? '') === 'too_short');

[$status, $body] = request('POST', '/signup.php', [
    'json' => payload(['csrf_token' => $csrf, 'password_confirm' => 'Different#1x']),
]);
ok('異常系: パスワード不一致は mismatch',
   ($body['errors']['password_confirm'] ?? '') === 'mismatch');

/* ============================== 異常系: 受け付け条件 ============================== */

[$status] = request('GET', '/signup.php');
ok('拒否: GETは405', $status === 405, "status={$status}");

[$status] = request('POST', '/signup.php', ['json' => payload(['csrf_token' => $csrf]), 'origin' => 'https://evil.example.com']);
ok('拒否: 許可外オリジンは403', $status === 403, "status={$status}");

[$status] = request('POST', '/signup.php', ['json' => payload()]);
ok('拒否: CSRFトークン無しは403（require_csrf_always=true）', $status === 403, "status={$status}");

[$status] = request('POST', '/signup.php', ['json' => payload(['csrf_token' => 'aaa.bbb.ccc'])]);
ok('拒否: 偽造トークンは403', $status === 403, "status={$status}");

[$status] = request('POST', '/signup.php', [
    'json' => payload(['csrf_token' => $csrf, 'website' => 'https://spam.example.com']),
]);
ok('拒否: honeypotに入力があると400', $status === 400, "status={$status}");

[$status] = request('POST', '/signup.php', [
    'json' => payload(['csrf_token' => $csrf, 'form_ts' => (string)(time() * 1000)]),
]);
ok('拒否: 表示直後の送信は400', $status === 400, "status={$status}");

[$status] = request('POST', '/signup.php', ['raw' => 'x=1', 'content_type' => 'text/plain']);
ok('拒否: 未対応のContent-Typeは415', $status === 415, "status={$status}");

[$status] = request('POST', '/signup.php', ['json' => payload(['csrf_token' => $csrf]), 'no_origin' => true]);
ok('拒否: OriginもRefererも無い場合は403', $status === 403, "status={$status}");

/* ============================== 応答ヘッダー ============================== */

[$status, $body, $headers] = request('POST', '/signup.php', ['json' => payload(['csrf_token' => $csrf])]);
$joined = strtolower(implode("\n", $headers));
ok('ヘッダー: Cache-Control: no-store', str_contains($joined, 'cache-control: no-store'));
ok('ヘッダー: X-Content-Type-Options: nosniff', str_contains($joined, 'x-content-type-options: nosniff'));
ok('ヘッダー: JSONで返す', str_contains($joined, 'application/json'));

/* ============================== 情報漏えい ============================== */

[$status, $body] = request('POST', '/signup.php', ['json' => payload(['csrf_token' => $csrf])]);
$rawJson = json_encode($body, JSON_UNESCAPED_UNICODE);
ok('漏えい: 応答にパスワードを含めない', !str_contains((string)$rawJson, 'Kx9#mQ2vRt'));
ok('漏えい: 応答に署名鍵を含めない', !str_contains((string)$rawJson, 'test-csrf-secret'));

/* ============================== 結果 ============================== */

$total = $passed + count($failed);
echo "  実行 {$total}件 / 成功 {$passed}件 / 失敗 " . count($failed) . "件\n";

if ($failed !== []) {
    echo "\n[NG] 失敗したテスト\n";
    foreach ($failed as $name) { echo "  - {$name}\n"; }
    exit(1);
}
echo "\n[OK] HTTP統合テストはすべて成功しました\n";
