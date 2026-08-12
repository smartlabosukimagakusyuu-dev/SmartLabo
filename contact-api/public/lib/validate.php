<?php
/**
 * 入力値の検証と整形。
 * フロント側にも同じ制限を入れているが、こちらを正とする。
 */

declare(strict_types=1);

/**
 * 画面の項目と、選択肢の対応。ラベルはメール件名・本文に使う。
 * WEB-V3-API-1(2026-08-07)で正式6種別へ統一し、WEB-V3-4でラベルを
 * 正式名称（一般お問い合わせ／パートナー・代理店相談／採用について）へそろえた。
 * 旧値（intro / price / feature / fit / other）は新規送信経路では使用しない
 * （このallowlistに無いため422で拒否される）。
 * キーは contact.html の option value および URLパラメータ ?type= と1対1。
 * 種別を増減する場合は contact.html の <select> と必ず同時に更新すること。
 */
const SLW_TYPES = [
    'contact' => '一般お問い合わせ',
    'consult' => '無料相談',
    'docs'    => '資料請求',
    'demo'    => 'デモ依頼',
    'partner' => 'パートナー・代理店相談',
    'recruit' => '採用について',
];

const SLW_HEADCOUNTS = [
    ''          => '未回答',
    '1'         => '1人',
    '2-5'       => '2〜5人',
    '6-10'      => '6〜10人',
    '11-30'     => '11〜30人',
    '31plus'    => '31人以上',
    'undecided' => '未定',
];

/**
 * 値がUTF-8として妥当かを確認する。
 * 妥当でない文字列に対して /u 付きの正規表現を使うと preg_replace が NULL を返し、
 * 値が黙って空になって「未入力」と誤判定されてしまう。それを防ぐために使う。
 */
function slw_is_utf8(string $v): bool
{
    if (function_exists('mb_check_encoding')) {
        return mb_check_encoding($v, 'UTF-8');
    }
    return preg_match('//u', $v) === 1;
}

/**
 * 受け取った本文の全項目がUTF-8として妥当か。
 * 妥当でなければ「入力内容をご確認ください」で返し、
 * 未入力と取り違えた分かりにくいエラーにしない。
 */
function slw_body_is_utf8(array $body): bool
{
    foreach ($body as $value) {
        if (is_string($value) && !slw_is_utf8($value)) {
            return false;
        }
    }
    return true;
}

/** 制御文字・改行を取り除く（1行の項目用）。メールヘッダー汚染の防止も兼ねる */
function slw_one_line(string $v): string
{
    $v = str_replace(["\r\n", "\r", "\n"], ' ', $v);
    // UTF-8として妥当なときだけ /u を使う（黙って空文字にしないため）
    $pattern = slw_is_utf8($v) ? '/[\x00-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/';
    $v = preg_replace($pattern, '', $v);
    return trim((string)$v);
}

/** 複数行の項目。改行は \n へ正規化し、制御文字は取り除く */
function slw_multi_line(string $v): string
{
    $v = str_replace(["\r\n", "\r"], "\n", $v);
    $pattern = slw_is_utf8($v)
        ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'
        : '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/';
    $v = preg_replace($pattern, '', $v);
    return trim((string)$v);
}

/** HTMLタグを落として、そのままメールへ書ける平文にする */
function slw_plain(string $v): string
{
    // タグを除去したうえで実体参照を戻す（<script>等が本文に残らない）
    $v = strip_tags($v);
    return html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * 文字数（バイトではなく文字で数える）。
 * mbstring が入っていない環境でも動くようにしている。
 * XServerでは通常有効だが、拡張の有無に動作を依存させない。
 */
function slw_len(string $v): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($v, 'UTF-8');
    }
    // UTF-8のコードポイント数を数える（PCREのuフラグは標準で使える）
    $count = preg_match_all('/./us', $v);
    return $count === false ? strlen($v) : $count;
}

/**
 * 入力を検証する。
 * 返り値: ['valid' => bool, 'errors' => ['項目名' => '理由コード'], 'data' => 整形後]
 */
function slw_validate(array $body): array
{
    $errors = [];

    $company  = slw_plain(slw_one_line((string)($body['company']  ?? '')));
    $name     = slw_plain(slw_one_line((string)($body['name']     ?? '')));
    $email    = slw_one_line((string)($body['email']    ?? ''));
    $tel      = slw_plain(slw_one_line((string)($body['tel']      ?? '')));
    $type     = slw_one_line((string)($body['type']     ?? ''));
    $headcount = slw_one_line((string)($body['headcount'] ?? ''));
    $message  = slw_plain(slw_multi_line((string)($body['message'] ?? '')));
    $privacy  = (string)($body['privacy'] ?? '');

    // --- 会社名（必須・100文字） ---
    if ($company === '')            { $errors['company'] = 'required'; }
    elseif (slw_len($company) > 100) { $errors['company'] = 'too_long'; }

    // --- お名前（必須・100文字） ---
    if ($name === '')             { $errors['name'] = 'required'; }
    elseif (slw_len($name) > 100) { $errors['name'] = 'too_long'; }

    // --- メールアドレス（必須・254文字・形式・改行不可） ---
    if ($email === '') {
        $errors['email'] = 'required';
    } elseif (slw_len($email) > 254) {
        $errors['email'] = 'too_long';
    } elseif (preg_match('/[\r\n]/', (string)($body['email'] ?? ''))) {
        // 元の入力に改行が含まれていた場合は、整形で消えていても拒否する
        $errors['email'] = 'invalid';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'invalid';
    }

    // --- 電話番号（任意・30文字） ---
    if ($tel !== '' && slw_len($tel) > 30) { $errors['tel'] = 'too_long'; }

    // --- 種別（必須・選択肢内） ---
    if ($type === '')                              { $errors['type'] = 'required'; }
    elseif (!array_key_exists($type, SLW_TYPES))   { $errors['type'] = 'invalid'; }

    // --- 利用予定人数（任意・選択肢内） ---
    if (!array_key_exists($headcount, SLW_HEADCOUNTS)) { $errors['headcount'] = 'invalid'; }

    // --- 内容（必須・3000文字） ---
    if ($message === '')              { $errors['message'] = 'required'; }
    elseif (slw_len($message) > 3000) { $errors['message'] = 'too_long'; }

    // --- プライバシーポリシー同意（必須） ---
    if ($privacy !== '1' && $privacy !== 'on' && $privacy !== 'true') {
        $errors['privacy'] = 'required';
    }

    return [
        'valid'  => count($errors) === 0,
        'errors' => $errors,
        'data'   => [
            'company'   => $company,
            'name'      => $name,
            'email'     => $email,
            'tel'       => $tel,
            'type'      => $type,
            'headcount' => $headcount,
            'message'   => $message,
        ],
    ];
}
