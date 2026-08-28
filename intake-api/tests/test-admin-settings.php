<?php
/**
 * 管理設定の内容条件・真偽の null 拒否（HP-ONBOARDING-4F-R4 / SSOT v1.10 §3.12）
 *
 * 4F-R3 で残っていた不整合:
 *   1. `contact_form.enabled` は null を保存でき、欠落・null・false の**3状態**が生まれた
 *   2. 管理設定5件が「キーがあれば設定済み」で、送信先も保管方法も**空のまま**
 *      「検証済み JSON」を書き出せた
 *
 * ここで固定すること:
 *   未回答は**キーが無いこと**で表す。null は保存させない。
 *   管理設定は**中身**まで見る。空でよい項目と、空では困る項目を分ける。
 */
declare(strict_types=1);

use SmartLabo\Intake\AnswerSchema;
use SmartLabo\Intake\Config;
use SmartLabo\Intake\Service\AnswerValidator;

/** 正常な管理設定5件（架空の値のみ） */
function goodSettings(array $overrides = []): array
{
    $base = [
        'salon_booking_url' => '',
        'destination'       => '架空の送信先',
        'storage'           => '架空の保管方法',
        'external_services' => [],
        'consent_checkbox'  => true,
    ];
    $v = array_merge($base, $overrides);

    return [
        'web_links' => ['salon_booking_url' => $v['salon_booking_url']],
        'privacy'   => [
            'destination'       => $v['destination'],
            'storage'           => $v['storage'],
            'external_services' => $v['external_services'],
            'consent_checkbox'  => $v['consent_checkbox'],
        ],
    ];
}

/** 提出済みの案件を1件つくる（管理設定はまだ入れない） */
function settingsCase(string $number): array
{
    $k       = adminKernel();
    $caseId  = $k->cases->create($number, '架空サロン ハルカゼ');
    $token   = $k->tokens->issue($caseId);
    $secret  = (string)$k->app->handle(jsonPost('/session/start', ['token' => $token]))->cookies[0]['value'];
    $cookies = ['cookies' => [Config::COOKIE_NAME => $secret]];

    $k->app->handle(jsonPost('/answers/save', ['version' => 1, 'sections' => completeSections()], $cookies));
    $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies));

    return [$k, $caseId, $cookies];
}

/* ==================================================== contact_form.enabled */

test('enabled: 真偽以外は1つ残らず保存を拒否する', function (): void {
    foreach ([
        ['null', null],
        ['"true"', 'true'],
        ['"false"', 'false'],
        ['""', ''],
        ['0', 0],
        ['1', 1],
        ['[]', []],
        ['{}', ['a' => 1]],
    ] as $i => [$label, $bad]) {
        $k       = adminKernel();
        $caseId  = $k->cases->create('HP-202608-960' . $i, '架空サロン');
        $token   = $k->tokens->issue($caseId);
        $secret  = (string)$k->app->handle(jsonPost('/session/start', ['token' => $token]))->cookies[0]['value'];
        $cookies = ['cookies' => [Config::COOKIE_NAME => $secret]];

        // 先に正しい内容を入れておく（部分保存が起きたら気づけるように）
        $k->app->handle(jsonPost('/answers/save', ['version' => 1, 'sections' => completeSections()], $cookies));
        $before      = $k->answers->get($caseId);
        $beforeAudit = $k->audit->countFor($caseId, 'answer_saved');

        $res = $k->app->handle(jsonPost('/answers/save', [
            'version'  => $before['version'],
            'sections' => ['contact_form' => ['enabled' => $bad, 'topics' => ['架空']]],
        ], $cookies));

        assertSame(400, $res->status, $label . ' が保存できてしまった');
        assertSame('bad_request', $res->body['error'] ?? '', $label . ' の理由が固定でない');

        $after = $k->answers->get($caseId);
        assertSame($before['version'], $after['version'], $label . ' で version が動いた');
        assertSame($before['sections'], $after['sections'], $label . ' で回答が変わった');
        assertSame($beforeAudit, $k->audit->countFor($caseId, 'answer_saved'), $label . ' で監査が増えた');
        assertTrue(!str_contains($res->json(), 'topics'), $label . ' の応答に値が出ている');
    }
});

test('enabled: 未回答はキーが無いことで表す', function (): void {
    $k       = adminKernel();
    $caseId  = $k->cases->create('HP-202608-9610', '架空サロン');
    $token   = $k->tokens->issue($caseId);
    $secret  = (string)$k->app->handle(jsonPost('/session/start', ['token' => $token]))->cookies[0]['value'];
    $cookies = ['cookies' => [Config::COOKIE_NAME => $secret]];

    $sections = completeSections();
    unset($sections['contact_form']['enabled']);
    $k->app->handle(jsonPost('/answers/save', ['version' => 1, 'sections' => $sections], $cookies));

    assertTrue(in_array('contact_form.enabled', $k->answers->evaluate($caseId)['missing'], true),
        '欠落が不足にならない');

    foreach ([true, false] as $choice) {
        $current = $k->answers->get($caseId);
        $res     = $k->app->handle(jsonPost('/answers/save', [
            'version'  => $current['version'],
            'sections' => ['contact_form' => ['enabled' => $choice]],
        ], $cookies));

        assertSame(200, $res->status, var_export($choice, true) . ' が保存できない');
        assertSame([], $k->answers->evaluate($caseId)['missing'], var_export($choice, true) . ' で不足が残る');
    }
});

test('enabled: 真偽の型は null を通さない（型定義そのもの）', function (): void {
    assertSame(false, AnswerValidator::check(['contact_form' => ['enabled' => null]])['ok'], 'null が通る');
    assertSame(true, AnswerValidator::check(['contact_form' => ['enabled' => true]])['ok'], 'true が通らない');
    assertSame(true, AnswerValidator::check(['contact_form' => ['enabled' => false]])['ok'], 'false が通らない');
});

/* ==================================================== 管理設定の内容条件 */

test('設定: 生成物が5件の内容条件を持つ', function (): void {
    $rules = AnswerSchema::ADMIN_VALUE_RULES;

    assertSame(AnswerSchema::ADMIN_REQUIRED_FOR_EXPORT, array_keys($rules), '規則の顔ぶれが違う');
    assertSame('1', $rules['web_links.salon_booking_url']['allow_empty'], '予約URLが空を許していない');
    assertSame('1', $rules['web_links.salon_booking_url']['url'], '予約URLに https 検査が無い');
    assertSame('500', $rules['web_links.salon_booking_url']['max'], '予約URLの上限が SSOT と違う');
    assertSame('0', $rules['privacy.destination']['allow_empty'], '送信先が空を許している');
    assertSame('200', $rules['privacy.destination']['max'], '送信先の上限が SSOT と違う');
    assertSame('0', $rules['privacy.storage']['allow_empty'], '保管方法が空を許している');
    assertSame('200', $rules['privacy.storage']['max'], '保管方法の上限が SSOT と違う');
    assertSame('list', $rules['privacy.external_services']['type'], '外部サービスが配列でない');
    assertSame('1', $rules['privacy.external_services']['allow_empty'], '0件を許していない');
    assertSame('10', $rules['privacy.external_services']['cap'], '件数上限が SSOT と違う');
    assertSame('60', $rules['privacy.external_services']['item_max'], '1件の長さが SSOT と違う');
    assertSame('bool', $rules['privacy.consent_checkbox']['type'], '同意チェックが真偽でない');
});

test('設定: salon_booking_url は空か https だけ', function (): void {
    foreach ([
        ''                                  => true,   // 「予約URLなし」
        '   '                               => true,   // 空白だけも空として扱う
        'https://example.invalid/booking'   => true,
        'http://example.invalid/booking'    => false,
        'javascript:alert(1)'               => false,
        'data:text/html,<b>x</b>'           => false,
        '/booking'                          => false,
        'example.invalid/booking'           => false,
        'https://user:pass@example.invalid/' => false,
        'https://'                          => false,
    ] as $url => $expected) {
        $errors = AnswerValidator::adminValueErrors(goodSettings(['salon_booking_url' => $url]));
        assertSame($expected, $errors === [], '判定が違う: ' . var_export($url, true));
    }

    // 長さ超過
    $long = 'https://example.invalid/' . str_repeat('a', 500);
    assertTrue(AnswerValidator::adminValueErrors(goodSettings(['salon_booking_url' => $long])) !== [],
        '500文字超が通っている');
});

test('設定: destination と storage は空にできない', function (): void {
    foreach (['destination', 'storage'] as $key) {
        foreach ([
            '架空の値' => true,
            ''         => false,
            '   '      => false,
            "\t\n"     => false,
        ] as $value => $expected) {
            $errors = AnswerValidator::adminValueErrors(goodSettings([$key => $value]));
            assertSame($expected, $errors === [], $key . ' の判定が違う: ' . var_export($value, true));
        }

        // 型違い
        foreach ([null, 0, 1, true, false, ['a'], ['a' => 1]] as $bad) {
            $errors = AnswerValidator::adminValueErrors(goodSettings([$key => $bad]));
            assertTrue($errors !== [], $key . ' に ' . gettype($bad) . ' が通っている');
        }

        // 長さ超過（SSOT の 200 文字）
        $errors = AnswerValidator::adminValueErrors(goodSettings([$key => str_repeat('あ', 201)]));
        assertTrue($errors !== [], $key . ' の201文字が通っている');
        $ok = AnswerValidator::adminValueErrors(goodSettings([$key => str_repeat('あ', 200)]));
        assertSame([], $ok, $key . ' の200文字が通らない');
    }
});

test('設定: external_services は配列。0件が「なし」の正式な設定', function (): void {
    assertSame([], AnswerValidator::adminValueErrors(goodSettings(['external_services' => []])),
        '0件が拒否されている');
    assertSame([], AnswerValidator::adminValueErrors(goodSettings(['external_services' => ['架空サービス']])),
        '1件が拒否されている');
    assertSame([], AnswerValidator::adminValueErrors(
        goodSettings(['external_services' => array_fill(0, 10, '架空')])), '10件が拒否されている');

    foreach ([
        '上限超過'   => array_fill(0, 11, '架空'),
        '要素が空'   => ['架空', ''],
        '要素が空白' => ['   '],
        '要素が数値' => [1],
        '要素が配列' => [['a']],
        '要素が長い' => [str_repeat('あ', 61)],
        '文字列'     => '架空サービス',
        'object'     => ['a' => '架空'],
        'null'       => null,
        'bool'       => true,
    ] as $label => $bad) {
        assertTrue(AnswerValidator::adminValueErrors(goodSettings(['external_services' => $bad])) !== [],
            $label . ' が通っている');
    }

    // 60文字ちょうどは通る
    assertSame([], AnswerValidator::adminValueErrors(
        goodSettings(['external_services' => [str_repeat('あ', 60)]])), '60文字が通らない');
});

test('設定: consent_checkbox は true / false だけ', function (): void {
    foreach ([true, false] as $ok) {
        assertSame([], AnswerValidator::adminValueErrors(goodSettings(['consent_checkbox' => $ok])),
            var_export($ok, true) . ' が拒否されている');
    }
    foreach ([null, 'true', 'false', 0, 1, [], ['a' => 1]] as $bad) {
        assertTrue(AnswerValidator::adminValueErrors(goodSettings(['consent_checkbox' => $bad])) !== [],
            gettype($bad) . ' が通っている');
    }
});

test('設定: 不備のパスだけを返し、値は返さない', function (): void {
    $errors = AnswerValidator::adminValueErrors(goodSettings([
        'destination' => '',
        'salon_booking_url' => 'javascript:alert("SETTINGLEAK0001")',
    ]));

    assertSame(['web_links.salon_booking_url', 'privacy.destination'], $errors, 'パスの顔ぶれが違う');
    assertTrue(!str_contains((string)json_encode($errors), 'SETTINGLEAK0001'), '値が返っている');
});

/* ==================================================== 保存 */

test('保存: 5件が正しければ保存できる', function (): void {
    [$k, $caseId] = settingsCase('HP-202608-9620');

    $result = $k->answers->saveAdminSettings($caseId, goodSettings());

    assertSame(true, $result['ok'], '保存できない');
    assertSame([], $k->answers->missingAdminSettings($caseId), '不足が残っている');
});

test('保存: 1件でも不備があれば5件とも保存しない', function (): void {
    foreach ([
        ['destination' => ''],
        ['storage' => '   '],
        ['consent_checkbox' => null],
        ['salon_booking_url' => 'http://example.invalid/'],
        ['external_services' => ['']],
    ] as $i => $override) {
        [$k, $caseId] = settingsCase('HP-202608-963' . $i);

        $result = $k->answers->saveAdminSettings($caseId, goodSettings($override));

        assertSame(false, $result['ok'], var_export($override, true) . ' で保存できてしまった');
        // ★1件も書かれていない（5件とも「未設定」のまま）
        assertSame(5, count($k->answers->missingAdminSettings($caseId)),
            var_export($override, true) . ' で部分保存が起きた');
        assertSame([], $k->answers->adminSettings($caseId), '値が書かれている');
    }
});

test('保存: 管理設定の保存で店舗の回答が変わらない', function (): void {
    [$k, $caseId] = settingsCase('HP-202608-9640');
    $before = $k->answers->get($caseId)['sections'];

    $k->answers->saveAdminSettings($caseId, goodSettings(['destination' => '架空の送信先2']));
    $after = $k->answers->get($caseId)['sections'];

    assertSame($before['privacy']['purpose'], $after['privacy']['purpose'], '店舗の回答が変わった');
    assertSame($before['basic'], $after['basic'], '別分類まで変わった');
    assertSame($before['web_links']['contact_methods'], $after['web_links']['contact_methods'],
        '同じ分類の店舗項目が変わった');
});

test('保存: 店舗の復元に管理設定が出ない', function (): void {
    [$k, $caseId, $cookies] = settingsCase('HP-202608-9641');
    $k->answers->saveAdminSettings($caseId, goodSettings([
        'destination' => 'SETTINGMARK9641', 'storage' => 'SETTINGMARK9642',
    ]));

    $res = $k->app->handle(jsonGet('/case', $cookies));

    assertSame(200, $res->status, '復元できない');
    assertTrue(!str_contains($res->json(), 'SETTINGMARK964'), '管理設定が返っている');
});

test('保存: 値をログにも監査にも出さない', function (): void {
    [$k, $caseId] = settingsCase('HP-202608-9642');
    $k->cases->adminChangeStatus($caseId, 'reviewed', 'reviewed');
    $login = loginAdmin($k);
    $form  = $k->app->handle(adminGet('/admin/settings',
        ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-202608-9642']]));
    preg_match('/name="csrf_token" value="([A-Za-z0-9_-]{43})"/', (string)$form->rawBody, $m);

    $k->app->handle(adminPost('/admin/settings/save', [
        'csrf_token' => $m[1], 'case' => 'HP-202608-9642', 'confirm_case' => 'HP-202608-9642',
        'salon_booking_url' => 'https://example.invalid/SETTINGMARK0001',
        'destination' => 'SETTINGMARK0002', 'storage' => 'SETTINGMARK0003',
        'external_services' => 'SETTINGMARK0004', 'consent_checkbox' => 'false',
    ], ['cookies' => $login['cookie']]));

    assertSame([], $k->answers->missingAdminSettings($caseId), '保存できていない');

    $log = is_file((string)$k->config->logPath) ? (string)file_get_contents((string)$k->config->logPath) : '';
    assertTrue(str_contains($log, 'admin_settings_saved'), '保存がログに残っていない');
    for ($i = 1; $i <= 4; ++$i) {
        assertTrue(!str_contains($log, 'SETTINGMARK000' . $i), 'ログに設定値が出ている');
    }

    $stmt = $k->db->pdo()->prepare('SELECT COUNT(*) FROM intake_audit_events WHERE result_code LIKE :m');
    $stmt->execute([':m' => '%SETTINGMARK%']);
    assertSame(0, (int)$stmt->fetchColumn(), '監査に設定値が出ている');
});

test('保存: 画面のエラーへ入力値を反射しない', function (): void {
    [$k, $caseId] = settingsCase('HP-202608-9643');
    $k->cases->adminChangeStatus($caseId, 'reviewed', 'reviewed');
    $login = loginAdmin($k);
    $form  = $k->app->handle(adminGet('/admin/settings',
        ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-202608-9643']]));
    preg_match('/name="csrf_token" value="([A-Za-z0-9_-]{43})"/', (string)$form->rawBody, $m);

    $res = $k->app->handle(adminPost('/admin/settings/save', [
        'csrf_token' => $m[1], 'case' => 'HP-202608-9643', 'confirm_case' => 'HP-202608-9643',
        'salon_booking_url' => 'javascript:alert("REFLECTMARK0001")',
        'destination' => '', 'storage' => '架空の保管方法',
        'external_services' => '', 'consent_checkbox' => 'true',
    ], ['cookies' => $login['cookie']]));

    assertSame(400, $res->status, '不備が通っている');
    $html = (string)$res->rawBody;
    assertTrue(!str_contains($html, 'REFLECTMARK0001'), '入力値が画面へ反射している');
    assertTrue(!str_contains($html, 'javascript:'), '危険な文字列が出ている');
    assertTrue(str_contains($html, '情報の送信先'), 'どの欄かの案内が無い');
    assertSame(5, count($k->answers->missingAdminSettings($caseId)), '部分保存が起きた');
});

test('保存: 画面が型に合った入力欄と必須表示を持つ', function (): void {
    [$k, $caseId] = settingsCase('HP-202608-9644');
    $k->cases->adminChangeStatus($caseId, 'reviewed', 'reviewed');
    $login = loginAdmin($k);
    $html  = (string)$k->app->handle(adminGet('/admin/settings',
        ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-202608-9644']]))->rawBody;

    assertTrue(str_contains($html, 'name="salon_booking_url" type="url"'), 'URL 入力になっていない');
    assertTrue(str_contains($html, 'name="destination" type="text" aria-required="true"'), '送信先が必須でない');
    assertTrue(str_contains($html, 'name="storage" type="text" aria-required="true"'), '保管方法が必須でない');
    assertTrue(str_contains($html, '<textarea id="s-services"'), '外部サービスが複数行でない');
    assertTrue(str_contains($html, '<option value="" selected>選択してください</option>'), '同意チェックが初期選択されている');
    assertTrue(substr_count($html, '必須</span>') >= 3, '必須の文字表示が足りない');
    assertTrue(str_contains($html, '空欄可'), '任意の文字表示が無い');
    unset($caseId);
});

/* ==================================================== export ゲート */

test('ゲート: 中身が満たされなければ書き出せない', function (): void {
    foreach ([
        'destination 空'    => ['destination' => ''],
        'storage 空白だけ'   => ['storage' => '  '],
        'consent が真偽でない' => ['consent_checkbox' => 'true'],
        '予約URLが http'     => ['salon_booking_url' => 'http://example.invalid/'],
        '外部サービスが不正'  => ['external_services' => ['']],
    ] as $label => $override) {
        [$k, $caseId] = settingsCase('HP-202608-965' . substr(md5($label), 0, 1));

        // ★保存 API は通さず、直接書き込んで「既に入っている不正値」を作る
        $sections = $k->answers->get($caseId)['sections'];
        $values   = goodSettings($override);
        $k->db->pdo()->prepare(
            'UPDATE intake_answers SET web_links_json = :w, privacy_json = :p WHERE intake_case_id = :i'
        )->execute([
            ':w' => json_encode(array_merge($sections['web_links'], $values['web_links']), JSON_UNESCAPED_UNICODE),
            ':p' => json_encode(array_merge($sections['privacy'], $values['privacy']), JSON_UNESCAPED_UNICODE),
            ':i' => $caseId,
        ]);

        $result = $k->export->export($caseId);
        assertSame(false, $result['ok'], $label . ' で書き出せてしまった');
        assertSame('admin_settings_missing', $result['error'], $label . ' の理由が違う');
        assertTrue(!array_key_exists('json', $result), $label . ' で本文が作られている');
    }
});

test('ゲート: 拒否のときに痕跡を残さない', function (): void {
    [$k, $caseId] = settingsCase('HP-202608-9660');
    $login  = loginAdmin($k);
    $before = $k->audit->countFor($caseId, 'export_generated');
    $tmp    = glob(sys_get_temp_dir() . '/*intake*') ?: [];

    $res = $k->app->handle(adminGet('/admin/export',
        ['cookies' => $login['cookie'], 'query' => ['case' => 'HP-202608-9660']]));

    assertSame(409, $res->status, '拒否されていない');
    assertTrue(!isset($res->headers['X-Intake-Export-Sha256']), 'SHA-256 が出ている');
    assertTrue(!isset($res->headers['Content-Disposition']), 'Content-Disposition が出ている');
    assertSame($before, $k->audit->countFor($caseId, 'export_generated'), '監査が増えている');
    assertSame($tmp, glob(sys_get_temp_dir() . '/*intake*') ?: [], '一時ファイルが作られている');
});

test('ゲート: 5件が正しければ書き出せる', function (): void {
    [$k, $caseId] = settingsCase('HP-202608-9661');
    $k->answers->saveAdminSettings($caseId, goodSettings([
        'salon_booking_url' => 'https://example.invalid/booking',
        'external_services' => ['架空アクセス解析'],
        'consent_checkbox'  => false,
    ]));

    $result = $k->export->export($caseId);

    assertSame(true, $result['ok'], '書き出せない');
    assertSame(hash('sha256', (string)$result['json']), (string)$result['sha256'], 'SHA-256 が合わない');

    $decoded = json_decode((string)$result['json'], true);
    assertSame('https://example.invalid/booking',
        $decoded['answers']['web_links']['salon_booking_url'], '予約URLが出ていない');
    assertSame(false, $decoded['answers']['privacy']['consent_checkbox'], 'false が出ていない');
    assertSame(['架空アクセス解析'], $decoded['answers']['privacy']['external_services'], '外部サービスが出ていない');
});

test('ゲート: 管理設定の不備で店舗の提出を妨げない', function (): void {
    $k       = adminKernel();
    $caseId  = $k->cases->create('HP-202608-9662', '架空サロン ハルカゼ');
    $token   = $k->tokens->issue($caseId);
    $secret  = (string)$k->app->handle(jsonPost('/session/start', ['token' => $token]))->cookies[0]['value'];
    $cookies = ['cookies' => [Config::COOKIE_NAME => $secret]];
    $k->app->handle(jsonPost('/answers/save', ['version' => 1, 'sections' => completeSections()], $cookies));

    // 管理設定は未設定・不正のいずれでも提出は通る
    assertSame(5, count($k->answers->missingAdminSettings($caseId)), '前提が違う');
    $res = $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $cookies));

    assertSame(200, $res->status, '提出できない');
    assertSame(true, $res->body['submitted'] ?? null, '提出が通っていない');
    assertSame('submitted', (string)$k->cases->find($caseId)['status'], '状態が進んでいない');
});
