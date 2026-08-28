<?php
/**
 * 必須契約の統一（HP-ONBOARDING-4F-R3 / SSOT v1.9 §3.0.2 / §3.12）
 *
 * 4F-R2 で見つかった食い違い:
 *   SSOT §3 が必須と定めるのは39件。実装（PHP）は22件しか見ていなかった。
 *   通常画面は45件を止めていたので気づけず、**API を直接呼べば素通り**した。
 *
 * ここで固定すること:
 *   必須の一覧は**生成物ひとつ**。画面・API・管理画面・書き出しが同じ集合を見る。
 *   Smart Labo が設定する5件は、店舗から見えず・書けず・提出を妨げず、
 *   **書き出しの前にだけ**効く。
 */
declare(strict_types=1);

use SmartLabo\Intake\AnswerSchema;
use SmartLabo\Intake\Config;
use SmartLabo\Intake\Service\AnswerService;
use SmartLabo\Intake\Service\AnswerValidator;

/** 能動選択が必要な enum 7件（代表判断 Q3） */
const ACTIVE_CHOICE_ENUMS = [
    'basic.address_visibility',
    'business_hours.irregular_notice',
    'privacy.third_party',
    'privacy.marketing_use',
    'design.logo',
    'design.emphasis',
    'web_links.map_display',
];

/** 提出まで進める案件を1件つくる（制作設定はまだ入れない） */
function contractCase(string $number, array $overrides = []): array
{
    $k       = adminKernel();
    $caseId  = $k->cases->create($number, '架空サロン ハルカゼ');
    $token   = $k->tokens->issue($caseId);
    $secret  = (string)$k->app->handle(jsonPost('/session/start', ['token' => $token]))->cookies[0]['value'];
    $cookies = ['cookies' => [Config::COOKIE_NAME => $secret]];

    $sections = completeSections();
    foreach ($overrides as $path => $value) {
        [$section, $key] = explode('.', $path, 2);
        if ($value === null && str_contains($key, '.') === false) {
            unset($sections[$section][$key]);
            continue;
        }
        $sections[$section][$key] = $value;
    }

    $save = $k->app->handle(jsonPost('/answers/save', ['version' => 1, 'sections' => $sections], $cookies));

    return [$k, $caseId, $cookies, $save];
}

/* ==================================================== 構造 */

test('契約: 正式パスは134件（店舗129・管理5）', function (): void {
    assertSame(134, count(AnswerSchema::PATHS), '正式パスが134件でない');
    assertSame(129, count(AnswerSchema::STORE_PATHS), '店舗パスが129件でない');
    assertSame(5, count(AnswerSchema::ADMIN_PATHS), '管理パスが5件でない');
    assertSame(134, count(array_unique(AnswerSchema::PATHS)), '重複がある');

    // 店舗と管理は重ならない
    assertSame([], array_intersect(AnswerSchema::STORE_PATHS, AnswerSchema::ADMIN_PATHS), '重なっている');
    // 店舗 ＋ 管理 ＝ 正式パス
    assertSame(
        count(AnswerSchema::PATHS),
        count(AnswerSchema::STORE_PATHS) + count(AnswerSchema::ADMIN_PATHS),
        '店舗と管理を足しても正式パスにならない'
    );
});

test('契約: Smart Labo 設定5件が正式パスに入っている', function (): void {
    assertSame([
        'web_links.salon_booking_url',
        'privacy.destination',
        'privacy.storage',
        'privacy.external_services',
        'privacy.consent_checkbox',
    ], AnswerSchema::ADMIN_PATHS, '管理パスの顔ぶれが違う');

    foreach (AnswerSchema::ADMIN_PATHS as $path) {
        assertTrue(in_array($path, AnswerSchema::PATHS, true), '正式パスに無い: ' . $path);
        assertTrue(in_array($path, AnswerSchema::ADMIN_REQUIRED_FOR_EXPORT, true),
            '書き出し前の必須に入っていない: ' . $path);
    }
});

test('契約: promotion.industry を実装しない（Phase 2 以降）', function (): void {
    // ★代表判断 Q1。SSOT §3.5 は Phase 2 以降へ移した
    foreach (AnswerSchema::PATHS as $path) {
        assertTrue(!str_starts_with($path, 'promotion.industry'), '正式パスに industry がある: ' . $path);
    }
    assertTrue(!in_array('promotion.industry', AnswerSchema::STORE_REQUIRED_NON_EMPTY, true), '必須に入っている');

    // 画面にもサーバーにも痕跡が無い
    foreach ([
        __DIR__ . '/../public/assets/lib/schema.js',
        __DIR__ . '/../src/AnswerSchema.php',
    ] as $file) {
        assertTrue(!str_contains((string)file_get_contents($file), "industry"),
            basename($file) . ' に industry がある');
    }
});

test('契約: 必須の一覧を PHP へ手書きしていない', function (): void {
    $php = stripPhpComments((string)file_get_contents(__DIR__ . '/../src/Service/AnswerService.php'));

    assertTrue(str_contains($php, 'REQUIRED_PATHS = AnswerSchema::STORE_REQUIRED_NON_EMPTY'),
        '生成物を参照していない');
    assertTrue(preg_match('/REQUIRED_PATHS = \[/', $php) !== 1, '手書きの配列が残っている');

    // 必須らしき一覧を他所に持っていない
    foreach (srcFiles() as $path => $code) {
        if (str_ends_with($path, 'AnswerSchema.php')) {
            continue;
        }
        $body = stripPhpComments($code);
        assertTrue(!preg_match("/'basic\.legal_name',\s*'basic\.operator_name'/", $body),
            basename($path) . ' が必須一覧を書き写している');
    }
});

test('契約: 必須の種別が重なっていない', function (): void {
    $sets = [
        AnswerSchema::STORE_REQUIRED_NON_EMPTY,
        AnswerSchema::STORE_REQUIRED_KEY_ALLOW_EMPTY,
        AnswerSchema::ADMIN_REQUIRED_FOR_EXPORT,
    ];
    foreach ($sets as $i => $a) {
        foreach ($sets as $j => $b) {
            if ($i < $j) {
                assertSame([], array_values(array_intersect($a, $b)), '種別が重なっている');
            }
        }
    }
    // OPTIONAL は残り
    $required = array_merge(...$sets);
    foreach ($required as $path) {
        assertTrue(!in_array($path, AnswerSchema::OPTIONAL_PATHS, true), 'OPTIONAL と重なる: ' . $path);
    }
});

/* ==================================================== 店舗 enum 7件 */

test('enum: 画面が既定値を持たない（能動選択させる）', function (): void {
    $js = (string)file_get_contents(__DIR__ . '/../public/assets/lib/schema.js');

    // 空の選択肢を必ず置く
    $fields = (string)file_get_contents(__DIR__ . '/../public/assets/lib/fields.js');
    assertTrue(str_contains($fields, '選択してください'), '未選択の選択肢が無い');
    assertTrue(!str_contains($fields, "if (!hasBlank && !field.required)"), '必須で空選択肢を省いている');

    // 7件が語彙を持ち、既定値を持たない
    foreach (ACTIVE_CHOICE_ENUMS as $path) {
        assertTrue(isset(AnswerSchema::ENUMS[$path]), '語彙が無い: ' . $path);
        $key = substr($path, strpos($path, '.') + 1);
        preg_match("/f\('" . preg_quote($key, '/') . "', '[^']*', KIND\.SELECT, \{(.*?)\n      \}\)/s", $js, $m);
        assertTrue(isset($m[1]), '定義が見つからない: ' . $path);
        assertTrue(!str_contains($m[1], 'default:'), '既定値を持っている: ' . $path);
        assertTrue(str_contains($m[1], 'required: true'), '必須になっていない: ' . $path);
    }
});

test('enum: 1件ずつ欠けると提出できない', function (): void {
    foreach (ACTIVE_CHOICE_ENUMS as $i => $path) {
        [$k, $caseId, $cookies] = contractCase('HP-202608-94' . str_pad((string)$i, 2, '0', STR_PAD_LEFT),
            [$path => null]);

        $missing = $k->answers->evaluate($caseId)['missing'];
        assertTrue(in_array($path, $missing, true), $path . ' が不足として出ない');

        // ★API を直接呼んでも同じ。画面だけの検査にしない
        //   不足時の応答は 200 ＋ submitted:false ＋ missing（SSOT §6.4 の既存契約）
        $res = $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies));
        assertSame(false, $res->body['submitted'] ?? null, $path . ' 欠落で提出できてしまった');
        assertTrue(in_array($path, $res->body['missing'] ?? [], true), $path . ' が応答の不足に無い');
        assertSame('draft', (string)$k->cases->find($caseId)['status'], $path . ' で状態が動いた');
    }
});

test('enum: 空文字は未回答として扱う', function (): void {
    [$k, $caseId, $cookies] = contractCase('HP-202608-9420', ['basic.address_visibility' => '']);
    unset($cookies);

    assertTrue(in_array('basic.address_visibility', $k->answers->evaluate($caseId)['missing'], true),
        '空文字が回答済みになっている');
});

test('enum: 正式語彙なら通る（none / no も店舗が選べば回答）', function (): void {
    [$k, $caseId] = contractCase('HP-202608-9421', [
        'privacy.third_party'   => 'none',
        'privacy.marketing_use' => 'no',
        'design.logo'           => 'none',
        'business_hours.irregular_notice' => 'none',
    ]);

    assertSame([], $k->answers->evaluate($caseId)['missing'], 'none / no が未回答になっている');
});

test('enum: 正式語彙でない値は保存そのものを拒否する', function (): void {
    foreach ([
        'basic.address_visibility'        => 'public',
        'privacy.third_party'             => 'maybe',
        'web_links.map_display'           => 'SHOW',
        'design.emphasis'                 => 'video',
        'business_hours.irregular_notice' => 'twitter',
    ] as $path => $bad) {
        [$section, $key] = explode('.', $path, 2);
        [$k, $caseId, $cookies, $save] = contractCase(
            'HP-202608-943' . substr(md5($path), 0, 1),
            [$path => $bad]
        );
        unset($cookies);

        assertSame(400, $save->status, $path . ' に ' . $bad . ' が保存できてしまった');
        assertSame('bad_request', $save->body['error'] ?? '', '理由が固定でない');

        // 値が入っていない
        $sections = $k->answers->get($caseId)['sections'];
        assertTrue(($sections[$section][$key] ?? null) !== $bad, '不正な値が残っている');
    }
});

/* ==================================================== contact_form.enabled */

test('enabled: キーが無ければ不足（false と未回答を区別する）', function (): void {
    [$k, $caseId, $cookies] = contractCase('HP-202608-9440', ['contact_form.enabled' => null]);

    assertTrue(in_array('contact_form.enabled', $k->answers->evaluate($caseId)['missing'], true),
        '欠落が不足にならない');
    assertSame(false,
        $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies))->body['submitted'] ?? null,
        '欠落したまま提出できてしまった');
});

test('enabled: true でも false でも提出できる', function (): void {
    foreach ([true, false] as $i => $choice) {
        [$k, $caseId, $cookies] = contractCase('HP-202608-944' . ($i + 1), ['contact_form.enabled' => $choice]);

        assertSame([], $k->answers->evaluate($caseId)['missing'], var_export($choice, true) . ' が不足になっている');
        assertSame(200, $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies))->status,
            var_export($choice, true) . ' で提出できない');
        assertSame('submitted', (string)$k->cases->find($caseId)['status'], '状態が進んでいない');
    }
});

test('enabled: 真偽以外は保存を拒否する', function (): void {
    foreach ([['"false"', 'false'], ['"true"', 'true'], ['0', 0], ['1', 1]] as $i => [$label, $bad]) {
        [$k, $caseId, $cookies, $save] = contractCase(
            'HP-202608-945' . $i,
            ['contact_form.enabled' => $bad]
        );
        unset($cookies);

        assertSame(400, $save->status, $label . ' が保存できてしまった');
        assertTrue(($k->answers->get($caseId)['sections']['contact_form']['enabled'] ?? null) !== $bad,
            $label . ' が残っている');
    }
});

test('enabled: null は保存できない（4F-R4）', function (): void {
    // ★欠落・null・false の3状態を作らない。未回答は**キーが無いこと**で表す
    $k       = adminKernel();
    $caseId  = $k->cases->create('HP-202608-9459', '架空サロン ハルカゼ');
    $token   = $k->tokens->issue($caseId);
    $secret  = (string)$k->app->handle(jsonPost('/session/start', ['token' => $token]))->cookies[0]['value'];
    $cookies = ['cookies' => [Config::COOKIE_NAME => $secret]];

    $sections = completeSections();
    $sections['contact_form']['enabled'] = null;

    $res = $k->app->handle(jsonPost('/answers/save', ['version' => 1, 'sections' => $sections], $cookies));

    assertSame(400, $res->status, 'null が保存できてしまった');
    assertSame('bad_request', $res->body['error'] ?? '', '理由が固定でない');
    assertSame(1, (int)$k->answers->get($caseId)['version'], 'version が動いた');
    assertSame(0, $k->audit->countFor($caseId, 'answer_saved'), '監査が増えた');
});

test('enabled: 既存DBに null が残っていても回答済みにしない', function (): void {
    // ★4F-R4 より前に入った行の再現。読み出しでは未回答として扱い、
    //   勝手に false へ変換しない。
    $k       = adminKernel();
    $caseId  = $k->cases->create('HP-202608-9458', '架空サロン ハルカゼ');
    $token   = $k->tokens->issue($caseId);
    $secret  = (string)$k->app->handle(jsonPost('/session/start', ['token' => $token]))->cookies[0]['value'];
    $cookies = ['cookies' => [Config::COOKIE_NAME => $secret]];
    $k->app->handle(jsonPost('/answers/save', ['version' => 1, 'sections' => completeSections()], $cookies));
    // ★提出できた案件へ、あとから null が入った状態を作る
    $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies));

    // 保存 API を通さずに直接 null を入れる
    $k->db->pdo()->prepare('UPDATE intake_answers SET contact_form_json = :j WHERE intake_case_id = :i')
        ->execute([':j' => json_encode(['enabled' => null]), ':i' => $caseId]);

    assertTrue(in_array('contact_form.enabled', $k->answers->evaluate($caseId)['missing'], true),
        'null が回答済みになっている');

    // 店舗の復元は落ちない。false へ変換もしない
    $res = $k->app->handle(jsonGet('/case', $cookies));
    assertSame(200, $res->status, '復元が落ちている');
    // ★`??` は null も既定値へ倒すので array_key_exists で見る
    $section = $res->body['sections']['contact_form'] ?? [];
    assertTrue(!array_key_exists('enabled', $section) || $section['enabled'] === null,
        'false へ変換されている');

    // 書き出しは不足として拒否
    setAdminSettings($k, $caseId);
    assertSame('incomplete', $k->export->export($caseId)['error'] ?? '', '書き出せてしまった');
});

test('enabled: 画面が既定でどちらも選ばない', function (): void {
    $js = (string)file_get_contents(__DIR__ . '/../public/assets/lib/schema.js');
    preg_match("/f\('enabled',.*?\}\)/s", $js, $m);

    assertTrue(isset($m[0]), 'enabled の定義が見つからない');
    assertTrue(!str_contains($m[0], 'default:'), '既定値を持っている');
    assertTrue(str_contains($m[0], 'requiredKey: true'), 'キー必須になっていない');
    assertTrue(str_contains($m[0], 'BOOL_CHOICE'), '二択になっていない');

    $fields = (string)file_get_contents(__DIR__ . '/../public/assets/lib/fields.js');
    assertTrue(str_contains($fields, 'boolChoiceControl'), '二択の部品が無い');
});

/* ==================================================== parking */

test('parking: object が無ければ不足、空 object も不足', function (): void {
    [$k, $caseId] = contractCase('HP-202608-9460', ['basic.parking' => null]);
    assertTrue(in_array('basic.parking', $k->answers->evaluate($caseId)['missing'], true), '欠落が不足にならない');

    [$k2, $caseId2] = contractCase('HP-202608-9461', ['basic.parking' => []]);
    $missing = $k2->answers->evaluate($caseId2)['missing'];
    assertTrue(in_array('basic.parking', $missing, true), '空 object が不足にならない');
});

test('parking: type が無ければ不足。none は正式な回答', function (): void {
    [$k, $caseId] = contractCase('HP-202608-9462', ['basic.parking' => ['note' => '補足だけ']]);
    assertTrue(in_array('basic.parking.type', $k->answers->evaluate($caseId)['missing'], true),
        'type の欠落が不足にならない');

    [$k2, $caseId2] = contractCase('HP-202608-9463', ['basic.parking' => ['type' => 'none', 'note' => '']]);
    assertSame([], $k2->answers->evaluate($caseId2)['missing'], 'none / 空の note が不足になっている');
});

/* ==================================================== 配列要素の必須 */

test('要素: 19件が生成物に載っている', function (): void {
    foreach ([
        'basic.parking.type',
        'business_hours.weekly.day', 'business_hours.weekly.closed',
        'menus.name', 'menus.price_type', 'menus.tax_type', 'menus.published',
        'menus.bookable', 'menus.first_time_only', 'menus.limited_period',
        'staff.published',
        'image_metadata.file_name', 'image_metadata.role', 'image_metadata.provider',
        'image_metadata.rights_confirmed', 'image_metadata.published', 'image_metadata.ai_generated',
        'rights.confirmations.code', 'rights.confirmations.agreed',
    ] as $path) {
        assertTrue(in_array($path, AnswerSchema::ARRAY_ELEMENT_REQUIRED, true), '要素必須に無い: ' . $path);
    }
    assertSame(19, count(AnswerSchema::ARRAY_ELEMENT_REQUIRED), '要素必須が19件でない');
});

test('要素: false を欠落として扱わない', function (): void {
    $menus = completeSections()['menus'];
    $menus[0]['published']       = false;
    $menus[0]['bookable']        = false;
    $menus[0]['first_time_only'] = false;
    $menus[0]['limited_period']  = false;

    [$k, $caseId] = contractCase('HP-202608-9470', ['menus.0' => null]);
    unset($k, $caseId);

    [$k2, $caseId2, $cookies] = contractCase('HP-202608-9471');
    $k2->app->handle(jsonPost('/answers/save', [
        'version' => $k2->answers->get($caseId2)['version'], 'sections' => ['menus' => $menus],
    ], $cookies));

    $missing = $k2->answers->evaluate($caseId2)['missing'];
    foreach (['published', 'bookable', 'first_time_only', 'limited_period'] as $key) {
        assertTrue(!in_array('menus[0].' . $key, $missing, true), 'false が欠落扱いになっている: ' . $key);
    }
});

test('要素: 要素の中のキーが欠けると不足になる', function (): void {
    $menus = completeSections()['menus'];
    unset($menus[0]['published']);

    [$k, $caseId, $cookies] = contractCase('HP-202608-9472');
    $k->app->handle(jsonPost('/answers/save', [
        'version' => $k->answers->get($caseId)['version'], 'sections' => ['menus' => $menus],
    ], $cookies));

    assertTrue(in_array('menus[0].published', $k->answers->evaluate($caseId)['missing'], true),
        '要素の欠落が不足にならない');
});

test('要素: 配列が空なら要素の条件を求めない', function (): void {
    // staff は0件でよい（分類そのものが必須ではない）
    [$k, $caseId] = contractCase('HP-202608-9473', ['staff.0' => null]);

    $missing = $k->answers->evaluate($caseId)['missing'];
    foreach ($missing as $path) {
        assertTrue(!str_starts_with($path, 'staff['), '空の配列へ要素条件を求めている: ' . $path);
    }
});

/* ==================================================== 必須契約の通し */

test('契約: すべて満たせば提出できる（画面もAPIも同じ）', function (): void {
    [$k, $caseId, $cookies] = contractCase('HP-202608-9480');

    assertSame([], $k->answers->evaluate($caseId)['missing'], '不足がある');
    assertSame(200, $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies))->status,
        '提出できない');
});

test('契約: 必須39件を1件ずつ欠落させると、すべて拒否される', function (): void {
    $i = 0;
    foreach (AnswerSchema::STORE_REQUIRED_NON_EMPTY as $path) {
        if (!str_contains($path, '.')) {
            continue;   // 分類そのもの（menus / image_metadata）は別テスト
        }
        [$section, $key] = explode('.', $path, 2);
        $sections = completeSections();
        if (str_contains($key, '.')) {
            [$outer, $inner] = explode('.', $key, 2);
            unset($sections[$section][$outer][$inner]);
        } else {
            unset($sections[$section][$key]);
        }

        $k       = adminKernel();
        $caseId  = $k->cases->create('HP-202608-95' . str_pad((string)$i, 2, '0', STR_PAD_LEFT), '架空サロン');
        $token   = $k->tokens->issue($caseId);
        $secret  = (string)$k->app->handle(jsonPost('/session/start', ['token' => $token]))->cookies[0]['value'];
        $cookies = ['cookies' => [Config::COOKIE_NAME => $secret]];
        $k->app->handle(jsonPost('/answers/save', ['version' => 1, 'sections' => $sections], $cookies));

        assertTrue(in_array($path, $k->answers->evaluate($caseId)['missing'], true),
            $path . ' の欠落が不足にならない');
        assertSame(false,
            $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies))->body['submitted'] ?? null,
            $path . ' が欠けたまま提出できてしまった');
        assertSame('draft', (string)$k->cases->find($caseId)['status'], $path . ' で状態が動いた');
        ++$i;
    }
    assertTrue($i >= 35, '検査したパスが少なすぎる: ' . $i);
});

test('契約: 不足の応答にパス以外を出さない', function (): void {
    [$k, $caseId, $cookies] = contractCase('HP-202608-9490', ['basic.legal_name' => null]);
    unset($caseId);

    $res  = $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies));
    $body = $res->json();

    assertSame(false, $res->body['submitted'] ?? null, '拒否されていない');
    assertTrue(str_contains($body, 'basic.legal_name'), '不足パスが返らない');
    foreach (['ハルカゼ', '03-0000-0000', 'internal@example.invalid', '架空県'] as $pii) {
        assertTrue(!str_contains($body, $pii), '応答に回答内容が出ている: ' . $pii);
    }
});

/* ==================================================== Smart Labo 設定 */

test('設定: 店舗の復元に出ない', function (): void {
    [$k, $caseId, $cookies] = contractCase('HP-202608-9500');
    setAdminSettings($k, $caseId);

    $res = $k->app->handle(jsonGet('/case', $cookies));

    assertSame(200, $res->status, '復元できない');
    foreach (AnswerSchema::ADMIN_PATHS as $path) {
        [$section, $key] = explode('.', $path, 2);
        assertTrue(!array_key_exists($key, $res->body['sections'][$section] ?? []),
            '店舗へ管理設定が返っている: ' . $path);
    }
    assertSame('架空の目的', $res->body['sections']['privacy']['purpose'], '店舗の回答まで消えている');
});

test('設定: 店舗の保存では書けない', function (): void {
    [$k, $caseId, $cookies] = contractCase('HP-202608-9501');
    setAdminSettings($k, $caseId);
    $before = $k->answers->adminSettings($caseId);

    $res = $k->app->handle(jsonPost('/answers/save', [
        'version'  => $k->answers->get($caseId)['version'],
        'sections' => ['privacy' => ['destination' => '書き換え', 'purpose' => '架空の目的']],
    ], $cookies));

    assertSame(400, $res->status, '店舗から管理設定を書けてしまった');
    assertSame($before, $k->answers->adminSettings($caseId), '管理設定が変わった');
});

test('設定: 店舗の保存で管理設定が消えない', function (): void {
    [$k, $caseId, $cookies] = contractCase('HP-202608-9502');
    setAdminSettings($k, $caseId);

    // 店舗が privacy 分類をまるごと送り直す
    $res = $k->app->handle(jsonPost('/answers/save', [
        'version'  => $k->answers->get($caseId)['version'],
        'sections' => ['privacy' => completeSections()['privacy']],
    ], $cookies));

    assertSame(200, $res->status, '店舗の保存が通らない');
    assertSame([], $k->answers->missingAdminSettings($caseId), '管理設定が消えている');
});

test('設定: 管理設定の保存で店舗の回答が消えない', function (): void {
    [$k, $caseId] = contractCase('HP-202608-9503');
    $before = $k->answers->get($caseId)['sections'];

    setAdminSettings($k, $caseId);
    $after = $k->answers->get($caseId)['sections'];

    foreach (AnswerSchema::STORE_PATHS as $path) {
        if (!str_contains($path, '.')) {
            continue;
        }
        [$section, $key] = explode('.', $path, 2);
        if (str_contains($key, '.')) {
            continue;
        }
        assertSame(
            $before[$section][$key] ?? null,
            $after[$section][$key] ?? null,
            '店舗の回答が変わった: ' . $path
        );
    }
});

test('設定: 管理設定の要求に店舗項目を混ぜられない', function (): void {
    [$k, $caseId] = contractCase('HP-202608-9504');

    $result = $k->answers->saveAdminSettings($caseId, [
        'privacy' => ['destination' => '架空', 'purpose' => '書き換え'],
    ]);

    assertSame(false, $result['ok'], '店舗項目が混ざっても通った');
    assertSame('架空の目的', $k->answers->get($caseId)['sections']['privacy']['purpose'], '店舗の回答が変わった');
});

test('設定: 画面は認証・CSRF・Origin・案件番号の再入力を要求する', function (): void {
    [$k, $caseId, $cookies] = contractCase('HP-202608-9505');
    $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies));
    $k->cases->adminChangeStatus($caseId, 'reviewed', 'reviewed');
    $number = 'HP-202608-9505';
    $login  = loginAdmin($k);

    // 未認証
    assertSame(303, $k->app->handle(adminGet('/admin/settings', ['query' => ['case' => $number]]))->status,
        '未認証で開けてしまった');

    $form = $k->app->handle(adminGet('/admin/settings',
        ['cookies' => $login['cookie'], 'query' => ['case' => $number]]));
    assertSame(200, $form->status, '設定画面が出ない');
    preg_match('/name="csrf_token" value="([A-Za-z0-9_-]{43})"/', (string)$form->rawBody, $m);

    $fields = [
        'csrf_token' => $m[1], 'case' => $number, 'confirm_case' => $number,
        'destination' => '架空の送信先', 'storage' => '架空の保管方法',
        'external_services' => '', 'consent_checkbox' => 'true', 'salon_booking_url' => '',
    ];

    // CSRF 欠落 / 不正 Origin / 案件番号不一致
    assertSame(403, $k->app->handle(adminPost('/admin/settings/save',
        array_diff_key($fields, ['csrf_token' => 1]), ['cookies' => $login['cookie']]))->status, 'CSRF 無しで通った');
    assertSame(403, $k->app->handle(adminPost('/admin/settings/save', $fields,
        ['cookies' => $login['cookie'], 'origin' => 'https://evil.example.invalid']))->status, '不正 Origin で通った');

    $ng = $k->app->handle(adminPost('/admin/settings/save',
        ['confirm_case' => 'HP-202608-0000'] + $fields, ['cookies' => $login['cookie']]));
    assertSame(400, $ng->status, '案件番号が違うのに通った');
    assertSame(['web_links.salon_booking_url', 'privacy.destination', 'privacy.storage',
        'privacy.external_services', 'privacy.consent_checkbox'],
        $k->answers->missingAdminSettings($caseId), '設定されてしまった');

    // 正しい入力で保存できる
    preg_match('/name="csrf_token" value="([A-Za-z0-9_-]{43})"/', (string)$ng->rawBody, $m2);
    $ok = $k->app->handle(adminPost('/admin/settings/save',
        ['csrf_token' => $m2[1]] + $fields, ['cookies' => $login['cookie']]));
    assertSame(303, $ok->status, '保存できない');
    assertSame([], $k->answers->missingAdminSettings($caseId), '設定が入っていない');
});

test('設定: 削除済み・closed の案件では変更できない', function (): void {
    $k      = adminKernel(null, ['retention_actions_enabled' => true, 'backup_policy_confirmed' => true]);
    $caseId = $k->cases->create('HP-202608-9506', '架空サロン');
    $k->db->pdo()->prepare('UPDATE intake_cases SET status = :s, deleted_at = :d WHERE id = :i')
        ->execute([':s' => 'closed', ':d' => '2026-08-27T00:00:00Z', ':i' => $caseId]);

    $login = loginAdmin($k);
    assertSame(404, $k->app->handle(adminGet('/admin/settings',
        ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-202608-9506']]))->status,
        '削除済みで開けてしまった');
});

test('設定: 値をログにも監査にも出さない', function (): void {
    [$k, $caseId, $cookies] = contractCase('HP-202608-9507');
    $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies));
    $k->cases->adminChangeStatus($caseId, 'reviewed', 'reviewed');
    $login = loginAdmin($k);
    $form  = $k->app->handle(adminGet('/admin/settings',
        ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-202608-9507']]));
    preg_match('/name="csrf_token" value="([A-Za-z0-9_-]{43})"/', (string)$form->rawBody, $m);

    $k->app->handle(adminPost('/admin/settings/save', [
        'csrf_token' => $m[1], 'case' => 'HP-202608-9507', 'confirm_case' => 'HP-202608-9507',
        'destination' => 'SETTINGMARK0001', 'storage' => 'SETTINGMARK0002',
        'external_services' => 'SETTINGMARK0003', 'consent_checkbox' => 'false',
        'salon_booking_url' => 'https://example.invalid/SETTINGMARK0004',
    ], ['cookies' => $login['cookie']]));

    $log = is_file((string)$k->config->logPath) ? (string)file_get_contents((string)$k->config->logPath) : '';
    assertTrue(str_contains($log, 'admin_settings_saved'), '保存がログに残っていない');
    for ($i = 1; $i <= 4; ++$i) {
        assertTrue(!str_contains($log, 'SETTINGMARK000' . $i), 'ログに設定値が出ている');
    }

    $stmt = $k->db->pdo()->prepare('SELECT COUNT(*) FROM intake_audit_events WHERE result_code LIKE :m');
    $stmt->execute([':m' => '%SETTINGMARK%']);
    assertSame(0, (int)$stmt->fetchColumn(), '監査に設定値が出ている');
    assertSame(1, $k->audit->countFor($caseId, 'admin_settings_saved'), '監査が1件でない');
});

/* ==================================================== ゲート */

test('ゲート: 管理設定が無くても店舗は提出できる', function (): void {
    [$k, $caseId, $cookies] = contractCase('HP-202608-9510');

    assertSame(5, count($k->answers->missingAdminSettings($caseId)), '管理設定が入っている');
    assertSame(200, $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies))->status,
        '管理設定が無いと提出できない');
    assertSame('submitted', (string)$k->cases->find($caseId)['status'], '状態が進んでいない');
});

test('ゲート: 管理設定が無ければ書き出せない', function (): void {
    [$k, $caseId, $cookies] = contractCase('HP-202608-9511');
    $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies));

    $result = $k->export->export($caseId);
    assertSame(false, $result['ok'], '管理設定が無いのに書き出せた');
    assertSame('admin_settings_missing', $result['error'], '理由が違う');
    assertTrue(!array_key_exists('json', $result), '本文が作られている');

    setAdminSettings($k, $caseId);
    $after = $k->export->export($caseId);
    assertSame(true, $after['ok'], '設定後も書き出せない');
    assertSame(hash('sha256', (string)$after['json']), (string)$after['sha256'], 'SHA-256 が合わない');
});

test('ゲート: 管理設定の不足だけで店舗を差し戻さない', function (): void {
    [$k, $caseId, $cookies] = contractCase('HP-202608-9512');
    $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies));

    // 店舗回答は充足しているので reviewed へ進める
    $result = $k->cases->adminChangeStatus($caseId, 'reviewed', 'reviewed');
    assertSame(true, $result['ok'], '管理設定が無いと reviewed へ進めない');
    assertSame([], $k->answers->evaluate($caseId)['missing'], '店舗回答に不足がある');
});

test('ゲート: 管理画面が店舗回答と管理設定を別々に出す', function (): void {
    [$k, $caseId, $cookies] = contractCase('HP-202608-9513');
    $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies));
    $login = loginAdmin($k);

    $html = (string)$k->app->handle(adminGet('/admin/case',
        ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-202608-9513']]))->rawBody;

    assertTrue(str_contains($html, '制作設定'), '管理設定の欄が無い');
    assertTrue(str_contains($html, '5 件未設定'), '管理設定の不足が出ていない');
    assertTrue(str_contains($html, '制作設定が揃うまで書き出せません'), '書き出せない理由が出ていない');

    setAdminSettings($k, $caseId);
    $html2 = (string)$k->app->handle(adminGet('/admin/case',
        ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-202608-9513']]))->rawBody;
    assertTrue(str_contains($html2, '設定済み'), '設定後も未設定のまま');
    assertTrue(str_contains($html2, '/admin/export?case='), '書き出しボタンが出ない');
    unset($caseId);
});

test('ゲート: 書き出しは134パスの外を出さない', function (): void {
    [$k, $caseId, $cookies] = contractCase('HP-202608-9514');
    $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies));
    setAdminSettings($k, $caseId);

    $json    = (string)$k->export->export($caseId)['json'];
    $decoded = json_decode($json, true);

    assertSame(AnswerSchema::SECTIONS, array_keys($decoded['answers']), '分類が違う');
    assertTrue(!str_contains($json, 'industry'), 'industry が出ている');

    // 各分類の直下キーが正式パスに載っている
    foreach ($decoded['answers'] as $section => $value) {
        foreach (is_array($value) ? $value : [] as $key => $child) {
            if (is_int($key)) {
                foreach (is_array($child) ? array_keys($child) : [] as $itemKey) {
                    assertTrue(in_array($section . '.' . $itemKey, AnswerSchema::PATHS, true),
                        '正式パス外: ' . $section . '.' . $itemKey);
                }
                continue;
            }
            // 入れ子の途中（basic.internal_contact）は、その下に正式パスがあれば正しい
            $prefix = $section . '.' . $key;
            $ok     = in_array($prefix, AnswerSchema::PATHS, true);
            foreach (AnswerSchema::PATHS as $candidate) {
                if (str_starts_with($candidate, $prefix . '.')) {
                    $ok = true;
                    break;
                }
            }
            assertTrue($ok, '正式パス外: ' . $prefix);
        }
    }
});
