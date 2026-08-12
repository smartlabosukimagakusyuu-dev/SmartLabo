<?php
/**
 * 入力検証のユニットテスト（SALES-1）
 * ============================================================================
 * 実行:
 *   php signup-api/tests/test-validate.php
 *   終了コード 0 = 全件成功 / 1 = 失敗あり
 *
 * 外部ライブラリ（PHPUnit等）は使わない。Composerも導入しない。
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../public/lib/validate.php';
require_once __DIR__ . '/../public/lib/pricing.php';

$passed = 0;
$failed = [];

function ok(string $name, bool $cond): void
{
    global $passed, $failed;
    if ($cond) { $passed++; } else { $failed[] = $name; }
}

/** 全項目が妥当な入力（各テストでここから1項目だけ差し替える） */
function base(array $override = []): array
{
    return array_merge([
        'company_name'        => '株式会社テスト商事',
        'company_kana'        => 'カブシキガイシャテストショウジ',
        'postal_code'         => '680-0000',
        'address'             => '鳥取県鳥取市テスト町1-2-3 テストビル4F',
        'company_tel'         => '0857-00-0000',
        'contact_email'       => 'keiri@example.co.jp',
        'admin_name'          => '山田 太郎',
        'admin_email'         => 'taro@example.co.jp',
        'password'            => 'Kx9#mQ2vRt',
        'password_confirm'    => 'Kx9#mQ2vRt',
        'additional_accounts' => '2',
    ], $override);
}

/* ============================== 正常系 ============================== */

$r = sls_validate(base());
ok('正常系: valid になる', $r['valid'] === true);
ok('正常系: errors が空', $r['errors'] === []);
ok('正常系: 追加人数が整数になる', $r['data']['additional_accounts'] === 2);
ok('正常系: data にパスワードを含めない', !array_key_exists('password', $r['data']));
ok('正常系: 郵便番号が 123-4567 形式へ整う', $r['data']['postal_code'] === '680-0000');

$r = sls_validate(base(['postal_code' => '6800000']));
ok('正常系: ハイフン無しの郵便番号を整形する', $r['valid'] && $r['data']['postal_code'] === '680-0000');

$r = sls_validate(base(['company_tel' => '09012345678']));
ok('正常系: 携帯番号(11桁)を受け付ける', $r['valid'] === true);

$r = sls_validate(base(['additional_accounts' => '0']));
ok('正常系: 追加0名を受け付ける', $r['valid'] && $r['data']['additional_accounts'] === 0);

$r = sls_validate(base(['additional_accounts' => '']));
ok('正常系: 追加人数が空欄なら0として扱う', $r['valid'] && $r['data']['additional_accounts'] === 0);

/* ============================== 必須項目 ============================== */

foreach ([
    'company_name', 'company_kana', 'postal_code', 'address',
    'company_tel', 'contact_email', 'admin_name', 'admin_email',
] as $field) {
    $r = sls_validate(base([$field => '']));
    ok("必須: {$field} 未入力で required", ($r['errors'][$field] ?? '') === 'required');
}

$r = sls_validate(base(['password' => '', 'password_confirm' => '']));
ok('必須: password 未入力で required', ($r['errors']['password'] ?? '') === 'required');

/* ============================== 形式 ============================== */

$r = sls_validate(base(['company_kana' => '株式会社テスト']));
ok('形式: カナ欄に漢字が入ると invalid', ($r['errors']['company_kana'] ?? '') === 'invalid');

$r = sls_validate(base(['company_kana' => 'かぶしきがいしゃ']));
ok('形式: カナ欄にひらがなが入ると invalid', ($r['errors']['company_kana'] ?? '') === 'invalid');

$r = sls_validate(base(['postal_code' => '68-00000']));
ok('形式: 郵便番号の桁数違いが invalid', ($r['errors']['postal_code'] ?? '') === 'invalid');

$r = sls_validate(base(['postal_code' => 'ABC-DEFG']));
ok('形式: 郵便番号に英字が入ると invalid', ($r['errors']['postal_code'] ?? '') === 'invalid');

$r = sls_validate(base(['company_tel' => '123']));
ok('形式: 電話番号が短すぎると invalid', ($r['errors']['company_tel'] ?? '') === 'invalid');

$r = sls_validate(base(['company_tel' => '0857-00-0000-0000']));
ok('形式: 電話番号が長すぎると invalid', ($r['errors']['company_tel'] ?? '') === 'invalid');

$r = sls_validate(base(['contact_email' => 'not-an-email']));
ok('形式: メール形式違いが invalid', ($r['errors']['contact_email'] ?? '') === 'invalid');

$r = sls_validate(base(['admin_email' => "taro@example.co.jp\r\nBcc: evil@example.com"]));
ok('形式: メールに改行が混ざると invalid（ヘッダー汚染の防止）',
   ($r['errors']['admin_email'] ?? '') === 'invalid');

/* ============================== 文字数 ============================== */

$r = sls_validate(base(['company_name' => str_repeat('あ', 101)]));
ok('文字数: 会社名101文字で too_long', ($r['errors']['company_name'] ?? '') === 'too_long');

$r = sls_validate(base(['company_name' => str_repeat('あ', 100)]));
ok('文字数: 会社名100文字は通る', !isset($r['errors']['company_name']));

$r = sls_validate(base(['address' => str_repeat('あ', 201)]));
ok('文字数: 住所201文字で too_long', ($r['errors']['address'] ?? '') === 'too_long');

/* ============================== パスワード ============================== */

$r = sls_validate(base(['password' => 'Kx9#mQ2v', 'password_confirm' => 'Kx9#mQ2v']));
ok('パスワード: 9文字で too_short', ($r['errors']['password'] ?? '') === 'too_short');

$r = sls_validate(base(['password' => 'abcdefghijkl', 'password_confirm' => 'abcdefghijkl']));
ok('パスワード: 小文字だけで weak', ($r['errors']['password'] ?? '') === 'weak');

$r = sls_validate(base(['password' => 'abcdefgh1234', 'password_confirm' => 'abcdefgh1234']));
ok('パスワード: 2種類だけで weak', ($r['errors']['password'] ?? '') === 'weak');

$r = sls_validate(base(['password' => 'Password123!', 'password_confirm' => 'Password123!']));
ok('パスワード: よくある語を含むと weak', ($r['errors']['password'] ?? '') === 'weak');

$r = sls_validate(base([
    'admin_email'      => 'yamada@example.co.jp',
    'password'         => 'Yamada#2026x',
    'password_confirm' => 'Yamada#2026x',
]));
ok('パスワード: メールのローカル部を含むと weak', ($r['errors']['password'] ?? '') === 'weak');

$r = sls_validate(base(['password_confirm' => 'Kx9#mQ2vRtX']));
ok('パスワード: 確認用が違うと mismatch', ($r['errors']['password_confirm'] ?? '') === 'mismatch');

$r = sls_validate(base(['password_confirm' => '']));
ok('パスワード: 確認用が空で required', ($r['errors']['password_confirm'] ?? '') === 'required');

$long = str_repeat('Aa1#', 40);   // 160文字
$r = sls_validate(base(['password' => $long, 'password_confirm' => $long]));
ok('パスワード: 長すぎると too_long', ($r['errors']['password'] ?? '') === 'too_long');

/* ============================== 追加アカウント数 ============================== */

$r = sls_validate(base(['additional_accounts' => '-1']));
ok('人数: 負の値を弾く', isset($r['errors']['additional_accounts']));

$r = sls_validate(base(['additional_accounts' => '1000']));
ok('人数: 上限超過で out_of_range', ($r['errors']['additional_accounts'] ?? '') === 'out_of_range');

$r = sls_validate(base(['additional_accounts' => '３']));   // 全角
ok('人数: 全角数字を半角として受け付ける', $r['valid'] && $r['data']['additional_accounts'] === 3);

$r = sls_validate(base(['additional_accounts' => 'abc']));
ok('人数: 数字以外を弾く', isset($r['errors']['additional_accounts']));

/* ============================== サニタイズ ============================== */

$r = sls_validate(base(['company_name' => '<script>alert(1)</script>株式会社テスト']));
ok('サニタイズ: HTMLタグを除去する',
   !str_contains($r['data']['company_name'], '<script>')
   && str_contains($r['data']['company_name'], '株式会社テスト'));

$r = sls_validate(base(['admin_name' => "山田\r\n太郎"]));
ok('サニタイズ: 氏名の改行を除去する', !preg_match('/[\r\n]/', $r['data']['admin_name']));

ok('サニタイズ: 不正なUTF-8を検出する', sls_body_is_utf8(['x' => "\xC3\x28"]) === false);
ok('サニタイズ: 正しいUTF-8は通す', sls_body_is_utf8(['x' => 'あいうえお']) === true);

/* ============================== 規約同意 ============================== */

$r = sls_validate(base(['agree_terms' => '']));
ok('同意: 項目が送られて未チェックなら required', ($r['errors']['agree_terms'] ?? '') === 'required');

$r = sls_validate(base(['agree_terms' => '1']));
ok('同意: チェック済みなら通る', $r['valid'] === true);

$r = sls_validate(base());
ok('同意: 項目自体が無ければ検査しない（SALES-1では未設置）', $r['valid'] === true);

/* ============================== 料金計算 ============================== */

$q = sls_quote(0);
ok('料金: 追加0名の月額は20,000円', $q['monthly_total'] === 20000);
ok('料金: 追加0名でも利用者は1名', $q['total_users'] === 1);

$q = sls_quote(2);
ok('料金: 追加2名の月額は26,000円', $q['monthly_total'] === 26000);

$q = sls_quote(4);
ok('料金: 追加4名の月額は32,000円', $q['monthly_total'] === 32000);

$q = sls_quote(-5);
ok('料金: 負の人数は0として扱う', $q['monthly_total'] === 20000);

$q = sls_quote(3);
ok('料金: 初期設定費は10,000円', $q['initial_fee'] === 10000);
ok('料金: 税抜であることを明示する', $q['tax_included'] === false);
ok('料金: 初回請求額は自前計算しない（nullのまま）', $q['first_charge'] === null);

/* ============================== 結果 ============================== */

$total = $passed + count($failed);
echo "  実行 {$total}件 / 成功 {$passed}件 / 失敗 " . count($failed) . "件\n";

if ($failed !== []) {
    echo "\n[NG] 失敗したテスト\n";
    foreach ($failed as $name) { echo "  - {$name}\n"; }
    exit(1);
}
echo "\n[OK] 入力検証・料金計算のテストはすべて成功しました\n";
