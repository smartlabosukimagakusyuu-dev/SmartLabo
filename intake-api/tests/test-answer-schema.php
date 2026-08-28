<?php
/**
 * 回答 JSON の厳格 allowlist（HP-ONBOARDING-4F-R1 / SSOT v1.8 §3.0-9 / §11.3-6）
 *
 * 4F で見つかった穴:
 *   保存 API は**分類名（11種）だけ**を見ており、分類の中身のキーは素通しだった。
 *   未知キーはそのまま保存され、「検証済み JSON」にも出ていた。
 *
 * ここで固定すること:
 *   受け取るとき … 未知キーが1つでもあれば**要求全体を拒否**する（部分保存しない）
 *   出すとき     … 既存DBに未知キーが残っていても**出力しない**（落ちもしない）
 */
declare(strict_types=1);

use SmartLabo\Intake\AnswerPaths;
use SmartLabo\Intake\AnswerSchema;
use SmartLabo\Intake\Config;
use SmartLabo\Intake\Migrator;
use SmartLabo\Intake\Service\AnswerService;
use SmartLabo\Intake\Service\AnswerValidator;

/** 未知キーの目印（他に出てこない文字列） */
const UNK_MARKER = 'UNKNOWNKEYMARK0001';

/**
 * 正式構造から、**129パスすべてを埋めた**値を作る。
 * ★手で書き写さない。生成物（AnswerSchema）から作ることで、
 *   項目が増減してもこのテストが自動で追随する。
 */
function everyPathSections(): array
{
    // ★語彙が決まっている項目は、正式な値を使う（4F-R3 で語彙検査を入れたため）
    $value = static function (array $node, string $path) use (&$value): mixed {
        $allowed = AnswerSchema::ENUMS[$path] ?? null;

        return match ($node['type']) {
            'scalar'  => $allowed === null ? '架空の値' : $allowed[0],
            'bool'    => true,
            'list'    => $allowed === null ? ['架空1', '架空2'] : [$allowed[0]],
            'object'  => (static function () use ($node, $path, $value): array {
                $out = [];
                foreach ($node['fields'] as $key => $child) {
                    $out[$key] = $value($child, $path . '.' . $key);
                }

                return $out;
            })(),
            'objects' => [(static function () use ($node, $path, $value): array {
                $out = [];
                foreach ($node['item'] as $key => $child) {
                    $out[$key] = $value($child, $path . '.' . $key);
                }

                return $out;
            })()],
            default   => null,
        };
    };

    $out = [];
    foreach (AnswerSchema::STRUCTURE as $name => $node) {
        $bag = $node['type'] === 'objects' ? $node['item'] : $node['fields'];
        // ★Smart Labo 設定（§3.12）は店舗から送れない。除いて組み立てる
        $bag = array_filter(
            $bag,
            static fn (string $key): bool => !in_array($name . '.' . $key, AnswerSchema::ADMIN_PATHS, true),
            ARRAY_FILTER_USE_KEY
        );
        $row = [];
        foreach ($bag as $key => $child) {
            $row[$key] = $value($child, $name . '.' . $key);
        }
        $out[$name] = $node['type'] === 'objects' ? [$row] : $row;
    }

    return $out;
}

/** 入力中の案件と店舗 session を1つ用意する */
function schemaCase(string $number): array
{
    $k      = makeKernel();
    $caseId = $k->cases->create($number, '架空サロン ハルカゼ');
    $token  = $k->tokens->issue($caseId);
    $secret = (string)$k->app->handle(jsonPost('/session/start', ['token' => $token]))->cookies[0]['value'];

    return [$k, $caseId, ['cookies' => [Config::COOKIE_NAME => $secret]]];
}

/** 保存要求を1つ送る */
function saveSections(object $k, array $cookies, array $sections, ?int $version = null): object
{
    return $k->app->handle(jsonPost('/answers/save', [
        'version'  => $version ?? 1,
        'sections' => $sections,
    ], $cookies));
}

/** 回答行の生の JSON（絞り込み前）を読む */
function rawSection(object $k, int $caseId, string $section): array
{
    $stmt = $k->db->pdo()->prepare('SELECT ' . $section . '_json AS j FROM intake_answers WHERE intake_case_id = :i');
    $stmt->execute([':i' => $caseId]);
    $decoded = json_decode((string)$stmt->fetchColumn(), true);

    return is_array($decoded) ? $decoded : [];
}

/* ==================================================== 生成物との一致 */

test('schema: 生成物が 11分類・134パスで、手書きの一覧と一致する', function (): void {
    assertSame(11, count(AnswerSchema::SECTIONS), '分類が11個でない');
    // 134 = 店舗 129 ＋ Smart Labo 設定 5（4F-R3 / SSOT v1.9 §3.12）
    assertSame(134, count(AnswerSchema::PATHS), 'パスが134件でない');
    assertSame(129, count(AnswerSchema::STORE_PATHS), '店舗パスが129件でない');
    assertSame(5, count(AnswerSchema::ADMIN_PATHS), '管理パスが5件でない');
    assertSame(AnswerSchema::SECTIONS, Migrator::ANSWER_SECTIONS, '分類名または順序が DB 側と違う');
    assertSame(AnswerSchema::PATHS, AnswerPaths::ALL, 'AnswerPaths が生成物と食い違っている');
    assertSame(array_keys(AnswerSchema::STRUCTURE), AnswerSchema::SECTIONS, '構造の分類が一致しない');
});

test('schema: 必須は生成物を指すだけで、手書きの一覧を持たない', function (): void {
    // ★4F-R2 で見つかった食い違い（SSOT 39 / 実装 22）の再発を防ぐ
    assertSame(39, count(AnswerSchema::STORE_REQUIRED_NON_EMPTY), '必須が39件でない');
    assertSame(AnswerSchema::STORE_REQUIRED_NON_EMPTY, AnswerService::REQUIRED_PATHS,
        'AnswerService が生成物を指していない');

    $unknown = array_values(array_diff(AnswerService::REQUIRED_PATHS, AnswerSchema::PATHS));
    assertSame([], $unknown, '必須に正式パス以外がある: ' . implode(', ', $unknown));

    // 手書きの配列が復活していないこと
    $php = stripPhpComments((string)file_get_contents(__DIR__ . '/../src/Service/AnswerService.php'));
    assertTrue(str_contains($php, 'REQUIRED_PATHS = AnswerSchema::STORE_REQUIRED_NON_EMPTY'),
        'REQUIRED_PATHS が生成物を参照していない');
    assertTrue(preg_match("/REQUIRED_PATHS = \[/", $php) !== 1, '手書きの必須配列が残っている');
});

test('schema: schema.js の項目がすべて構造へ入っている', function (): void {
    $js = (string)file_get_contents(__DIR__ . '/../public/assets/lib/schema.js');

    preg_match_all("/^  \{\s*\n    key: '([a-z_]+)'/m", $js, $keys);
    assertSame($keys[1], AnswerSchema::SECTIONS, '分類名または順序が schema.js と違う');

    preg_match_all("/f\('([a-zA-Z0-9_.]+)',/", $js, $fields);
    assertTrue(count($fields[1]) > 100, '項目が少なすぎる');

    $missing = [];
    foreach ($fields[1] as $fieldPath) {
        $found = false;
        foreach (AnswerSchema::PATHS as $p) {
            if (str_ends_with($p, '.' . $fieldPath)) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $missing[] = $fieldPath;
        }
    }
    assertSame([], $missing, '構造に無い項目がある: ' . implode(', ', $missing));
});

test('schema: 配列要素・入れ子の許可キーが画面側と一致する', function (): void {
    $s = AnswerSchema::STRUCTURE;

    // 画面部品が形を決めているもの（fields.js の control と対）
    assertSame(['day', 'closed', 'open', 'close'],
        array_keys($s['business_hours']['fields']['weekly']['item']), '営業時間の要素キーが違う');
    assertSame(['code', 'agreed', 'agreed_at'],
        array_keys($s['rights']['fields']['confirmations']['item']), '法的確認の要素キーが違う');
    assertSame(['type', 'note'],
        array_keys($s['basic']['fields']['parking']['fields']), '駐車場のキーが違う');
    assertSame(['phone', 'email'],
        array_keys($s['basic']['fields']['internal_contact']['fields']), '連絡先のキーが違う');

    // 繰り返し分類は objects
    foreach (['menus', 'staff', 'image_metadata'] as $key) {
        assertSame('objects', $s[$key]['type'], $key . ' が繰り返しになっていない');
    }
    // 型の対応
    assertSame('bool', $s['contact_form']['fields']['enabled']['type'], 'チェックが bool でない');
    assertSame('list', $s['basic']['fields']['payment_methods']['type'], '複数入力が list でない');
    assertSame('scalar', $s['basic']['fields']['legal_name']['type'], '文字入力が scalar でない');
});

test('schema: 生成を再実行しても差分が出ない', function (): void {
    $path   = __DIR__ . '/../src/AnswerSchema.php';
    $before = (string)file_get_contents($path);

    $script = escapeshellarg(__DIR__ . '/../dev/generate-answer-schema.mjs');
    exec('node ' . $script . ' 2>&1', $out, $code);

    $after = (string)file_get_contents($path);
    if ($after !== $before) {
        file_put_contents($path, $before); // 生成が壊れていても元へ戻す
    }

    assertSame(0, $code, '生成に失敗した: ' . implode(' / ', $out));
    assertSame($before, $after, '生成物が最新でない（node dev/generate-answer-schema.mjs を実行すること）');
});

test('schema: 生成物であることが本文に書いてある（手編集を誘わない）', function (): void {
    $php = (string)file_get_contents(__DIR__ . '/../src/AnswerSchema.php');

    assertTrue(str_contains($php, '生成物'), '生成物である旨が無い');
    assertTrue(str_contains($php, '手で書き換えない'), '手編集を禁じる記述が無い');
    assertTrue(str_contains($php, 'generate-answer-schema.mjs'), '作り直し方が書かれていない');
});

/* ==================================================== 正常保存 */

test('save: 店舗パスすべてを埋めた回答を保存できる', function (): void {
    [$k, $caseId, $cookies] = schemaCase('HP-202608-9300');

    $res = saveSections($k, $cookies, everyPathSections());

    assertSame(200, $res->status, '正式な回答が保存できない');
    assertSame(2, (int)$res->body['version'], 'version が進んでいない');

    // 129パスがすべて往復する
    $sections = $k->answers->get($caseId)['sections'];
    $missing  = [];
    foreach (AnswerSchema::STORE_PATHS as $path) {
        if (!str_contains($path, '.')) {
            continue;
        }
        [$section, $rest] = explode('.', $path, 2);
        $node = AnswerSchema::STRUCTURE[$section];
        $bag  = $node['type'] === 'objects' ? ($sections[$section][0] ?? []) : ($sections[$section] ?? []);
        $cur  = $bag;
        foreach (explode('.', $rest) as $part) {
            if (!is_array($cur) || !array_key_exists($part, $cur)) {
                $missing[] = $path;
                continue 2;
            }
            $cur = $cur[$part];
        }
    }
    assertSame([], $missing, '往復しないパスがある: ' . implode(', ', array_slice($missing, 0, 5)));
});

test('save: 11分類を1つずつ保存できる（既存の変更分類方式を壊さない）', function (): void {
    [$k, $caseId, $cookies] = schemaCase('HP-202608-9301');
    unset($caseId);

    $all     = everyPathSections();
    $version = 1;
    foreach (AnswerSchema::SECTIONS as $key) {
        $res     = saveSections($k, $cookies, [$key => $all[$key]], $version);
        assertSame(200, $res->status, $key . ' が保存できない');
        $version = (int)$res->body['version'];
    }
    assertSame(12, $version, 'version が11回進んでいない');
});

/* ==================================================== 未知キーの拒否 */

/**
 * 未知キーを混ぜた要求は「400・DB無変更・version無変更・監査無変更・
 * 応答にもログにもキー名が出ない」ことをまとめて確かめる。
 */
function assertRejected(string $number, array $sections, string $why): void
{
    [$k, $caseId, $cookies] = schemaCase($number);

    // 先に正式な内容を入れておく（部分保存が起きたら気づけるように）
    saveSections($k, $cookies, ['basic' => ['legal_name' => '架空サロン ハルカゼ']], 1);
    $before      = $k->answers->get($caseId);
    $beforeAudit = $k->audit->countFor($caseId, 'answer_saved');

    $res = saveSections($k, $cookies, $sections, (int)$before['version']);

    assertSame(400, $res->status, $why . ': 400 になっていない');
    assertSame('bad_request', $res->body['error'] ?? '', $why . ': エラーコードが固定でない');

    $after = $k->answers->get($caseId);
    assertSame($before['version'], $after['version'], $why . ': version が動いた');
    assertSame($before['sections'], $after['sections'], $why . ': 回答が変わった');
    assertSame($beforeAudit, $k->audit->countFor($caseId, 'answer_saved'), $why . ': 監査が増えた');

    // 未知キー名も値も、応答にもログにも出ない
    $body = $res->json();
    assertTrue(!str_contains($body, UNK_MARKER), $why . ': 応答に未知キーが出ている');
    $log = is_file((string)$k->config->logPath) ? (string)file_get_contents((string)$k->config->logPath) : '';
    assertTrue(!str_contains($log, UNK_MARKER), $why . ': ログに未知キーが出ている');
}

test('save: 未知の分類を拒否する', function (): void {
    assertRejected('HP-202608-9310', [UNK_MARKER => ['a' => 1]], '未知の分類');
});

test('save: 分類直下の未知キーを拒否する', function (): void {
    assertRejected('HP-202608-9311', ['basic' => [UNK_MARKER => '不正値']], '分類直下');
});

test('save: 入れ子の中の未知キーを拒否する', function (): void {
    assertRejected('HP-202608-9312', [
        'basic' => ['internal_contact' => ['phone' => '03-0000-0000', UNK_MARKER => '不正値']],
    ], '入れ子');
    assertRejected('HP-202608-9313', [
        'basic' => ['parking' => ['type' => 'none', UNK_MARKER => '不正値']],
    ], '駐車場の中');
});

test('save: 配列要素の中の未知キーを拒否する', function (): void {
    assertRejected('HP-202608-9314', [
        'menus' => [['name' => '架空カット', UNK_MARKER => '不正値']],
    ], 'メニューの要素');
    assertRejected('HP-202608-9315', [
        'business_hours' => ['weekly' => [['day' => 0, 'closed' => false, UNK_MARKER => 'x']]],
    ], '営業時間の要素');
    assertRejected('HP-202608-9316', [
        'rights' => ['confirmations' => [['code' => 'L-01', 'agreed' => true, UNK_MARKER => 'x']]],
    ], '法的確認の要素');
});

test('save: 正常値と未知キーが混ざっていたら、正常値も保存しない', function (): void {
    assertRejected('HP-202608-9317', [
        'basic' => ['legal_name' => '架空サロン 別名', UNK_MARKER => '不正値'],
    ], '混在');
});

test('save: 複数分類のうち1分類だけ不正でも、全体を拒否する', function (): void {
    assertRejected('HP-202608-9318', [
        'basic'        => ['legal_name' => '架空サロン 別名'],
        'promotion'    => ['concept' => '架空のコンセプト'],
        'contact_form' => ['enabled' => true, UNK_MARKER => 'x'],
    ], '3分類中1つが不正');
});

test('save: 攻撃的なキー名でも固定の拒否になる', function (): void {
    $keys = [
        "'; DROP TABLE intake_answers; --",
        '<script>alert(1)</script>',
        '__proto__',
        'constructor',
        'prototype',
        'legal_name ',
        '../../etc/passwd',
        str_repeat('a', 300),
    ];
    foreach ($keys as $i => $key) {
        [$k, $caseId, $cookies] = schemaCase('HP-202608-932' . $i);

        $res = saveSections($k, $cookies, ['basic' => [$key => '不正値']], 1);

        assertSame(400, $res->status, '通ってしまった: ' . substr($key, 0, 30));
        assertSame('bad_request', $res->body['error'] ?? '', '理由が固定でない');
        assertTrue(!str_contains($res->json(), 'DROP TABLE'), '応答にキーが出ている');
        assertTrue(!str_contains($res->json(), '<script'), '応答にキーが出ている');
        assertSame(1, (int)$k->answers->get($caseId)['version'], 'version が動いた');
    }

    // 8テーブルが健在
    [$k] = schemaCase('HP-202608-9329');
    $tables = $k->db->pdo()->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name LIKE 'intake_%'");
    assertSame(8, (int)$tables->fetchColumn(), '表が壊れている');
});

test('save: prototype pollution を狙ったキーで値が汚染されない', function (): void {
    [$k, $caseId, $cookies] = schemaCase('HP-202608-9330');

    $res = saveSections($k, $cookies, [
        'basic' => ['__proto__' => ['legal_name' => '汚染' . UNK_MARKER]],
    ], 1);

    assertSame(400, $res->status, '通ってしまった');
    $sections = $k->answers->get($caseId)['sections'];
    assertTrue(!str_contains((string)json_encode($sections), UNK_MARKER), '値が入っている');
    assertTrue(!array_key_exists('__proto__', $sections['basic']), 'キーが生えている');
});

/* ==================================================== 型の検査 */

test('save: 正式パスでも型が違えば拒否する', function (): void {
    foreach ([
        '文字列の位置へ配列'     => ['basic' => ['legal_name' => ['a', 'b']]],
        '文字列の位置へオブジェクト' => ['basic' => ['legal_name' => ['x' => 1]]],
        '配列の位置へ文字列'     => ['basic' => ['payment_methods' => '現金']],
        '配列の位置へオブジェクト' => ['basic' => ['payment_methods' => ['a' => 1]]],
        '配列の中へ配列'         => ['basic' => ['payment_methods' => [['現金']]]],
        'オブジェクトの位置へ文字列' => ['basic' => ['internal_contact' => '03-0000-0000']],
        'オブジェクトの位置へ配列' => ['basic' => ['internal_contact' => ['03-0000-0000']]],
        '真偽の位置へ文字列'     => ['contact_form' => ['enabled' => 'true']],
        '真偽の位置へ数値'       => ['contact_form' => ['enabled' => 1]],
        '繰り返しの位置へオブジェクト' => ['menus' => ['name' => '架空カット']],
        '繰り返しの要素へ文字列' => ['menus' => ['架空カット']],
        '繰り返しの要素へ配列'   => ['menus' => [['架空カット']]],
        '深すぎる入れ子'         => ['basic' => ['internal_contact' => ['phone' => ['a' => ['b' => ['c' => 'x']]]]]],
    ] as $why => $sections) {
        [$k, $caseId, $cookies] = schemaCase('HP-202608-93' . substr(md5($why), 0, 2));

        $res = saveSections($k, $cookies, $sections, 1);

        assertSame(400, $res->status, $why . ' が通ってしまった');
        assertSame(1, (int)$k->answers->get($caseId)['version'], $why . ' で version が動いた');
    }
});

test('save: 文字の項目は null を受け付ける（未入力の表現）', function (): void {
    [$k, $caseId, $cookies] = schemaCase('HP-202608-9340');
    unset($caseId);

    $res = saveSections($k, $cookies, [
        'basic' => ['legal_name' => null, 'public_phone' => null],
    ], 1);

    assertSame(200, $res->status, 'null が拒否されている');
});

test('save: 真偽の項目は null を受け付けない（4F-R4）', function (): void {
    // ★欠落・null・false の3状態を作らない。未回答は**キーが無いこと**で表す
    [$k, $caseId, $cookies] = schemaCase('HP-202608-9341');

    $res = saveSections($k, $cookies, ['contact_form' => ['enabled' => null]], 1);

    assertSame(400, $res->status, 'null が保存できてしまった');
    assertSame(1, (int)$k->answers->get($caseId)['version'], 'version が動いた');
});

/* ==================================================== 既存DBの未知キー */

/** 既存DBに未知キーが残っている案件を作る（4F-R1 より前に入った行の再現） */
function legacyUnknownCase(string $number): array
{
    $k      = adminKernel();
    $caseId = $k->cases->create($number, '架空サロン ハルカゼ');
    $token  = $k->tokens->issue($caseId);
    $secret = (string)$k->app->handle(jsonPost('/session/start', ['token' => $token]))->cookies[0]['value'];
    $cookies = ['cookies' => [Config::COOKIE_NAME => $secret]];

    $k->app->handle(jsonPost('/answers/save', ['version' => 1, 'sections' => completeSections()], $cookies));
    setAdminSettings($k, $caseId);

    // ★保存 API を通さずに直接埋め込む（いまの API では入らないため）
    $basic = rawSection($k, $caseId, 'basic');
    $basic[UNK_MARKER]     = '不正値' . UNK_MARKER;
    $basic['legal_name']   = '架空サロン ハルカゼ';
    $menus                 = rawSection($k, $caseId, 'menus');
    $menus[0][UNK_MARKER]  = '不正値' . UNK_MARKER;

    $k->db->pdo()->prepare(
        'UPDATE intake_answers SET basic_json = :b, menus_json = :m WHERE intake_case_id = :i'
    )->execute([
        ':b' => json_encode($basic, JSON_UNESCAPED_UNICODE),
        ':m' => json_encode($menus, JSON_UNESCAPED_UNICODE),
        ':i' => $caseId,
    ]);

    return [$k, $caseId, $cookies];
}

test('legacy: 既存DBの未知キーを店舗の復元へ返さない', function (): void {
    [$k, $caseId, $cookies] = legacyUnknownCase('HP-202608-9350');

    // 前提: DB には確かに入っている（検査が空振りしていない）
    assertTrue(array_key_exists(UNK_MARKER, rawSection($k, $caseId, 'basic')), 'DB に未知キーが無い');

    $res = $k->app->handle(jsonGet('/case', $cookies));

    assertSame(200, $res->status, '復元が 500 になっている');
    assertTrue(!str_contains($res->json(), UNK_MARKER), '未知キーが返っている');
    assertSame('架空サロン ハルカゼ', $res->body['sections']['basic']['legal_name'], '正式値が消えている');
    assertSame('カット', $res->body['sections']['menus'][0]['name'], '繰り返しの正式値が消えている');
});

test('legacy: 管理詳細に未知キーを表示しない', function (): void {
    [$k, $caseId, $cookies] = legacyUnknownCase('HP-202608-9351');
    $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies));

    $login = loginAdmin($k);
    $res   = $k->app->handle(adminGet('/admin/case', [
        'cookies' => $login['cookie'], 'query' => ['case' => 'HP-202608-9351'],
    ]));

    assertSame(200, $res->status, '詳細が 500 になっている');
    $html = (string)$res->rawBody;
    assertTrue(!str_contains($html, UNK_MARKER), '未知キーが表示されている');
    assertTrue(str_contains($html, '架空サロン ハルカゼ'), '正式値が消えている');
    unset($caseId);
});

test('legacy: 書き出しに未知キーを出さない', function (): void {
    [$k, $caseId, $cookies] = legacyUnknownCase('HP-202608-9352');
    $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies));

    $result = $k->export->export($caseId);

    assertSame(true, $result['ok'], '書き出せない');
    assertTrue(!str_contains((string)$result['json'], UNK_MARKER), '未知キーが出ている');
    assertTrue(str_contains((string)$result['json'], '架空サロン ハルカゼ'), '正式値が消えている');
    assertSame(hash('sha256', (string)$result['json']), (string)$result['sha256'], 'SHA-256 が本文と合わない');

    // 分類名も正式11種だけ
    $decoded = json_decode((string)$result['json'], true);
    assertSame(AnswerSchema::SECTIONS, array_keys($decoded['answers']), '書き出しの分類が違う');
});

test('legacy: 未知キーがあっても、ログにも監査にも名前と値を出さない', function (): void {
    [$k, $caseId, $cookies] = legacyUnknownCase('HP-202608-9353');
    $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies));
    $login = loginAdmin($k);
    $k->app->handle(adminGet('/admin/case', ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-202608-9353']]));
    $k->app->handle(adminGet('/admin/export', ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-202608-9353']]));

    $log = is_file((string)$k->config->logPath) ? (string)file_get_contents((string)$k->config->logPath) : '';
    assertTrue($log !== '', 'ログが空（検査が空振り）');
    assertTrue(!str_contains($log, UNK_MARKER), 'ログに未知キーが出ている');

    $stmt = $k->db->pdo()->prepare('SELECT COUNT(*) FROM intake_audit_events WHERE result_code LIKE :m');
    $stmt->execute([':m' => '%' . UNK_MARKER . '%']);
    assertSame(0, (int)$stmt->fetchColumn(), '監査に未知キーが出ている');
});

test('legacy: 未知キーを自動で消したり移したりしない（DBは触らない）', function (): void {
    [$k, $caseId, $cookies] = legacyUnknownCase('HP-202608-9354');

    $k->app->handle(jsonGet('/case', $cookies));
    $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies));
    $k->export->export($caseId);

    // ★読むだけで消さない。清掃機能はこの工程で作らない（SSOT v1.8 §3.0-9）
    assertTrue(array_key_exists(UNK_MARKER, rawSection($k, $caseId, 'basic')), 'DB の値が書き換えられている');
});

/* ==================================================== 書き出しの allowlist */

test('export: 保存済みの値を信用せず、書き出し直前にも絞り込む', function (): void {
    // ★コメントを取り除いてから見る。説明文に書いてあるだけで
    //   「実装されている」と判定してしまわないため（4F-R1 の mutation で判明）。
    $code = stripPhpComments((string)file_get_contents(__DIR__ . '/../src/Service/ExportService.php'));

    assertTrue(str_contains($code, 'AnswerValidator::filter'), '書き出し側の絞り込みが無い');

    // 絞り込みが本文の組み立てより前にある
    $filter = strpos($code, 'AnswerValidator::filter');
    $build  = strpos($code, "\$out['answers']");
    assertTrue($filter !== false && $build !== false && $filter < $build, '絞り込みが組み立てより後ろにある');
});

test('export: 読み出し側が緩んでも、書き出し側の絞り込みが止める', function (): void {
    // ★この2段は**わざと重ねてある**。片方が緩んでも、もう片方が止める。
    //   ここでは AnswerService::get() を通さず、未知キー入りの値を
    //   ExportService と同じ手順（filter → 組み立て）へ直接かけて確かめる。
    $leaky = [
        'basic' => ['legal_name' => '架空サロン ハルカゼ', UNK_MARKER => '不正値' . UNK_MARKER],
        'menus' => [['name' => '架空カット', UNK_MARKER => '不正値' . UNK_MARKER]],
        UNK_MARKER => ['a' => 1],
    ];

    $json = (string)json_encode(AnswerValidator::filter($leaky), JSON_UNESCAPED_UNICODE);

    assertTrue(!str_contains($json, UNK_MARKER), '未知キーが素通りしている');
    assertTrue(str_contains($json, '架空サロン ハルカゼ'), '正式値まで落ちている');
});

test('export: 未知分類が DB にあっても書き出しへ出ない', function (): void {
    [$k, $caseId, $cookies] = legacyUnknownCase('HP-202608-9360');
    $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies));

    $json = (string)$k->export->export($caseId)['json'];
    $out  = json_decode($json, true);

    // 分類は11種ちょうど。増えても減ってもいない
    assertSame(11, count($out['answers']), '分類の数が違う');
    foreach (array_keys($out['answers']) as $name) {
        assertTrue(in_array($name, AnswerSchema::SECTIONS, true), '未知の分類がある: ' . $name);
    }
});

test('export: 秘密値の除外は従来どおり維持されている', function (): void {
    [$k, $caseId, $cookies] = legacyUnknownCase('HP-202608-9361');
    $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies));

    $json = (string)$k->export->export($caseId)['json'];
    foreach (['token_hash', 'session_hash', 'csrf', 'ip_hmac', 'drive_folder_url',
              'drive_shared_email', 'password', 'enc_key', '"id"'] as $banned) {
        assertTrue(!str_contains($json, $banned), '書き出しに ' . $banned . ' が出ている');
    }
});

test('export: 必須が欠けていれば従来どおり拒否する（絞り込みで甘くしない）', function (): void {
    [$k, $caseId, $cookies] = schemaCase('HP-202608-9362');
    saveSections($k, $cookies, ['basic' => ['legal_name' => '架空サロン']], 1);

    // 提出せずに書き出そうとしても通らない
    assertSame('not_exportable', $k->export->export($caseId)['error'] ?? '', '未提出が書き出せる');
});

/* ==================================================== 直接の単体検査 */

test('validator: check は未知キーの名前を返さない', function (): void {
    $result = AnswerValidator::check(['basic' => [UNK_MARKER => 'x']]);

    assertSame(false, $result['ok'], '通ってしまった');
    assertTrue(!str_contains((string)json_encode($result), UNK_MARKER), 'キー名が返っている');
    assertSame('unknown_path', $result['error'], 'エラーコードが固定でない');
});

test('validator: filter は落とさず、正式な値だけを返す', function (): void {
    $filtered = AnswerValidator::filter([
        'basic'        => ['legal_name' => '架空', UNK_MARKER => 'x', 'internal_contact' => ['phone' => '03-0000-0000', UNK_MARKER => 'y']],
        'menus'        => [['name' => '架空カット', UNK_MARKER => 'z'], '文字列', ['name' => '架空カラー']],
        UNK_MARKER     => ['a' => 1],
        'contact_form' => ['enabled' => 'true'],   // 型違いは落とす
    ]);

    assertSame(['basic', 'menus', 'contact_form'], array_keys($filtered), '分類の選び方が違う');
    assertSame('架空', $filtered['basic']['legal_name'], '正式値が消えている');
    assertSame(['phone' => '03-0000-0000'], $filtered['basic']['internal_contact'], '入れ子が絞られていない');
    assertTrue(!array_key_exists(UNK_MARKER, $filtered['basic']), '未知キーが残っている');
    assertSame([['name' => '架空カット'], ['name' => '架空カラー']], $filtered['menus'], '繰り返しの絞り込みが違う');
    assertSame(['enabled' => null], $filtered['contact_form'], '型違いが残っている');
});

/* ==================================================== 操作領域 48px */

/** CSS から `セレクタ => 宣言` を粗く取り出す */
function cssRules(string $file): array
{
    $css   = (string)file_get_contents(__DIR__ . '/../public/assets/' . $file);
    $css   = (string)preg_replace('#/\*.*?\*/#s', '', $css);
    $rules = [];
    preg_match_all('/([^{}]+)\{([^{}]*)\}/s', $css, $m, PREG_SET_ORDER);
    foreach ($m as $one) {
        foreach (explode(',', trim($one[1])) as $selector) {
            $rules[trim($selector)][] = trim($one[2]);
        }
    }

    return $rules;
}

/** そのセレクタに効いている min-height（px） */
function minHeightOf(array $rules, string $selector): ?int
{
    foreach ($rules[$selector] ?? [] as $decl) {
        if (preg_match('/min-height:\s*(\d+)px/', $decl, $m) === 1) {
            return (int)$m[1];
        }
    }

    return null;
}

test('ui: 操作ボタンの最小高さが 48px（店舗・管理とも）', function (): void {
    foreach (['intake.css', 'admin.css'] as $file) {
        $rules = cssRules($file);
        assertSame(48, minHeightOf($rules, '.btn'), $file . ' の .btn が 48px でない');
    }
});

test('ui: 補助ボタンが 48px を下回らない', function (): void {
    foreach ([
        'intake.css' => ['.btn--quiet', '.btn--danger-quiet', '.btn--small'],
        'admin.css'  => ['.btn--quiet'],
    ] as $file => $selectors) {
        $rules = cssRules($file);
        foreach ($selectors as $selector) {
            $min = minHeightOf($rules, $selector);
            assertTrue(
                $min === null || $min >= 48,
                $file . ' の ' . $selector . ' が .btn の 48px を下げている: ' . var_export($min, true)
            );
        }
    }
});

test('ui: チェックの操作領域（label ごと）が 48px', function (): void {
    foreach (['intake.css', 'admin.css'] as $file) {
        $rules = cssRules($file);
        assertSame(48, minHeightOf($rules, '.checkline'), $file . ' の .checkline が 48px でない');
    }
    assertSame(48, minHeightOf(cssRules('intake.css'), '.weekly__closed'), '定休の操作領域が 48px でない');
});

test('ui: 操作対象の高さを height で固定していない（文字を拡大しても切れない）', function (): void {
    foreach (['intake.css', 'admin.css'] as $file) {
        $rules = cssRules($file);
        foreach (['.btn', '.btn--quiet', '.btn--small', '.btn--danger-quiet', '.checkline'] as $selector) {
            foreach ($rules[$selector] ?? [] as $decl) {
                assertTrue(
                    preg_match('/(?<!min-|max-|line-)height:\s*\d/', $decl) !== 1,
                    $file . ' の ' . $selector . ' が height を固定している'
                );
            }
        }
    }
});

test('ui: focus-visible と reduced-motion を維持している', function (): void {
    foreach (['intake.css', 'admin.css'] as $file) {
        $css = (string)file_get_contents(__DIR__ . '/../public/assets/' . $file);
        assertTrue(str_contains($css, ':focus-visible'), $file . ' の focus-visible が消えている');
        assertTrue(str_contains($css, 'prefers-reduced-motion'), $file . ' の reduced-motion が消えている');
    }
});

test('ui: 無効状態を色だけで示していない', function (): void {
    foreach (['intake.css', 'admin.css'] as $file) {
        $rules = cssRules($file);
        $found = false;
        foreach (array_keys($rules) as $selector) {
            if (str_contains($selector, ':disabled') || str_contains($selector, "aria-disabled")) {
                $found = true;
            }
        }
        assertTrue($found, $file . ' に無効状態の指定が無い');
    }
    // 画面側は disabled 属性そのものを使っている（支援技術へ伝わる）
    $formJs = (string)file_get_contents(__DIR__ . '/../public/assets/form.js');
    assertTrue(str_contains($formJs, 'disabled'), '画面が disabled 属性を使っていない');
});

test('ui: 文章中の通常リンクへ 48px を強制していない', function (): void {
    foreach (['intake.css', 'admin.css'] as $file) {
        $rules = cssRules($file);
        foreach (['a', 'p a', '.lead a'] as $selector) {
            assertTrue(minHeightOf($rules, $selector) === null,
                $file . ' が文章中のリンクへ min-height を付けている: ' . $selector);
        }
    }
});
