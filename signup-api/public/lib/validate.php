<?php
/**
 * 申し込み入力の検証と整形。
 * =============================================================================
 * 画面側（signup.js）にも同じ制限を入れているが、**こちらを正とする**。
 * 画面の検証は往復を減らすための上乗せであり、突破された前提で書いている。
 *
 * 返す理由コードは画面側と共通:
 *   required / too_long / too_short / invalid / mismatch / weak / out_of_range
 * =============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/pricing.php';

/** 各項目の文字数上限（画面側 signup.js の LIMITS と一致させること） */
const SLS_LIMITS = [
    'company_name'  => 100,
    'company_kana'  => 100,
    'postal_code'   => 8,     // 123-4567
    'address'       => 200,
    'company_tel'   => 20,
    'contact_email' => 254,
    'admin_name'    => 100,
    'admin_email'   => 254,
    'password'      => 128,
];

/** パスワードの最低文字数（仮置き。SALES-2で最終確定する） */
const SLS_PASSWORD_MIN = 10;

/**
 * 値がUTF-8として妥当かを確認する。
 * 妥当でない文字列へ /u 付き正規表現を使うと preg_replace が NULL を返し、
 * 値が黙って空になって「未入力」と誤判定されるため、先に確認する。
 */
function sls_is_utf8(string $v): bool
{
    if (function_exists('mb_check_encoding')) {
        return mb_check_encoding($v, 'UTF-8');
    }
    return preg_match('//u', $v) === 1;
}

/** 受け取った本文の全項目がUTF-8として妥当か */
function sls_body_is_utf8(array $body): bool
{
    foreach ($body as $value) {
        if (is_string($value) && !sls_is_utf8($value)) {
            return false;
        }
    }
    return true;
}

/** 制御文字・改行を取り除く（1行の項目用） */
function sls_one_line(string $v): string
{
    $v = str_replace(["\r\n", "\r", "\n"], ' ', $v);
    $pattern = sls_is_utf8($v) ? '/[\x00-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/';
    $v = preg_replace($pattern, '', $v);
    return trim((string)$v);
}

/** HTMLタグを落として平文にする（保存・表示のどちらでも安全な形にする） */
function sls_plain(string $v): string
{
    $v = strip_tags($v);
    return html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/** 文字数（バイトではなく文字で数える。mbstringが無い環境でも動く） */
function sls_len(string $v): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($v, 'UTF-8');
    }
    $count = preg_match_all('/./us', $v);
    return $count === false ? strlen($v) : $count;
}

/**
 * 全角英数字・全角スペースを半角へ寄せる（郵便番号・電話番号・人数用）。
 *
 * mbstring があればそれを使い、無い環境でも同じ結果になるように
 * 変換表を持たせている。拡張の有無で入力の受け付け方が変わると、
 * 環境によって「同じ入力が通ったり弾かれたり」する事故になるため。
 */
function sls_to_halfwidth(string $v): string
{
    if (function_exists('mb_convert_kana')) {
        return mb_convert_kana($v, 'as', 'UTF-8');
    }

    static $map = null;
    if ($map === null) {
        // 全角スペース・全角数字・全角英字を半角へ
        $map = ['　' => ' '];
        $fullDigits = ['０', '１', '２', '３', '４', '５', '６', '７', '８', '９'];
        foreach ($fullDigits as $i => $ch) {
            $map[$ch] = (string)$i;
        }
        for ($i = 0; $i < 26; $i++) {
            $map[mb_chr_fallback(0xFF21 + $i)] = chr(ord('A') + $i);
            $map[mb_chr_fallback(0xFF41 + $i)] = chr(ord('a') + $i);
        }
    }
    return strtr($v, $map);
}

/** コードポイントからUTF-8の1文字を作る（mbstringが無い環境用） */
function mb_chr_fallback(int $cp): string
{
    return html_entity_decode('&#' . $cp . ';', ENT_QUOTES, 'UTF-8');
}

/** メールアドレスとして妥当か（改行混入も拒否する） */
function sls_email_invalid(string $raw, string $cleaned): bool
{
    if (preg_match('/[\r\n]/', $raw)) {
        return true;   // 整形で消えていても、元に改行があれば拒否
    }
    return !filter_var($cleaned, FILTER_VALIDATE_EMAIL);
}

/**
 * パスワードの強度を確認する。
 *
 * 仮置きの基準（SALES-2で最終確定する）:
 *   ・10文字以上・128文字以内
 *   ・英大文字 / 英小文字 / 数字 / 記号 のうち3種類以上
 *   ・メールアドレスのローカル部や、よくある語をそのまま含まない
 *
 * 返り値: '' なら問題なし。問題があれば理由コード。
 */
function sls_password_problem(string $password, string $email): string
{
    if ($password === '') {
        return 'required';
    }
    // バイト長も見る（bcryptは72バイトで切られるため、長すぎる入力は先に弾く）
    if (sls_len($password) > SLS_LIMITS['password'] || strlen($password) > 200) {
        return 'too_long';
    }
    if (sls_len($password) < SLS_PASSWORD_MIN) {
        return 'too_short';
    }

    $kinds = 0;
    if (preg_match('/[A-Z]/', $password))                 { $kinds++; }
    if (preg_match('/[a-z]/', $password))                 { $kinds++; }
    if (preg_match('/[0-9]/', $password))                 { $kinds++; }
    if (preg_match('/[^A-Za-z0-9]/', $password))          { $kinds++; }
    if ($kinds < 3) {
        return 'weak';
    }

    $lower = strtolower($password);

    // よくある語をそのまま含むものは拒否する
    foreach (['password', 'smartlabo', 'smartlaboworks', 'qwerty', '123456', 'admin'] as $bad) {
        if (str_contains($lower, $bad)) {
            return 'weak';
        }
    }

    // メールアドレスのローカル部（4文字以上）をそのまま含むものは拒否する
    $local = strtolower((string)strstr($email, '@', true));
    if ($local !== '' && strlen($local) >= 4 && str_contains($lower, $local)) {
        return 'weak';
    }

    return '';
}

/**
 * 申し込み入力を検証する。
 *
 * 返り値: ['valid' => bool, 'errors' => ['項目名' => '理由コード'], 'data' => 整形後]
 * data にパスワードは含めない（呼び出し側へ渡さない・ログにも出さない）。
 */
function sls_validate(array $body): array
{
    $errors = [];

    /* ------------------------------- 会社情報 ------------------------------- */

    $companyName = sls_plain(sls_one_line((string)($body['company_name'] ?? '')));
    $companyKana = sls_plain(sls_one_line((string)($body['company_kana'] ?? '')));
    $postalRaw   = sls_one_line((string)($body['postal_code'] ?? ''));
    $postal      = str_replace(['ー', '−', '―', '‐'], '-', sls_to_halfwidth($postalRaw));
    $address     = sls_plain(sls_one_line((string)($body['address'] ?? '')));
    $telRaw      = sls_one_line((string)($body['company_tel'] ?? ''));
    $tel         = str_replace(['ー', '−', '―', '‐'], '-', sls_to_halfwidth($telRaw));
    $contactMail = sls_one_line((string)($body['contact_email'] ?? ''));

    // 会社名（必須）
    if ($companyName === '')                                  { $errors['company_name'] = 'required'; }
    elseif (sls_len($companyName) > SLS_LIMITS['company_name']) { $errors['company_name'] = 'too_long'; }

    // 会社名カナ（必須・全角カタカナと長音・空白のみ）
    if ($companyKana === '') {
        $errors['company_kana'] = 'required';
    } elseif (sls_len($companyKana) > SLS_LIMITS['company_kana']) {
        $errors['company_kana'] = 'too_long';
    } elseif (!preg_match('/^[ァ-ヶー゛゜・\x{3000}\s]+$/u', $companyKana)) {
        $errors['company_kana'] = 'invalid';
    }

    // 郵便番号（必須・7桁。ハイフンあり/なしの両方を受ける）
    if ($postal === '') {
        $errors['postal_code'] = 'required';
    } elseif (!preg_match('/^\d{3}-?\d{4}$/', $postal)) {
        $errors['postal_code'] = 'invalid';
    } else {
        // 表記を 123-4567 に揃える
        $digits = preg_replace('/\D/', '', $postal);
        $postal = substr((string)$digits, 0, 3) . '-' . substr((string)$digits, 3);
    }

    // 住所（必須）
    if ($address === '')                                 { $errors['address'] = 'required'; }
    elseif (sls_len($address) > SLS_LIMITS['address'])   { $errors['address'] = 'too_long'; }

    // 電話番号（必須・数字10〜11桁。ハイフン・括弧・空白は許容）
    if ($tel === '') {
        $errors['company_tel'] = 'required';
    } elseif (sls_len($tel) > SLS_LIMITS['company_tel']) {
        $errors['company_tel'] = 'too_long';
    } else {
        $telDigits = preg_replace('/\D/', '', $tel);
        $len = strlen((string)$telDigits);
        if (!preg_match('/^[0-9\-\(\)\s]+$/', $tel) || $len < 10 || $len > 11) {
            $errors['company_tel'] = 'invalid';
        }
    }

    // 担当者メールアドレス（必須）
    if ($contactMail === '') {
        $errors['contact_email'] = 'required';
    } elseif (sls_len($contactMail) > SLS_LIMITS['contact_email']) {
        $errors['contact_email'] = 'too_long';
    } elseif (sls_email_invalid((string)($body['contact_email'] ?? ''), $contactMail)) {
        $errors['contact_email'] = 'invalid';
    }

    /* ------------------------------- 管理者情報 ------------------------------- */

    $adminName  = sls_plain(sls_one_line((string)($body['admin_name']  ?? '')));
    $adminMail  = sls_one_line((string)($body['admin_email'] ?? ''));
    $password   = (string)($body['password'] ?? '');
    $passwordC  = (string)($body['password_confirm'] ?? '');

    if ($adminName === '')                                { $errors['admin_name'] = 'required'; }
    elseif (sls_len($adminName) > SLS_LIMITS['admin_name']) { $errors['admin_name'] = 'too_long'; }

    if ($adminMail === '') {
        $errors['admin_email'] = 'required';
    } elseif (sls_len($adminMail) > SLS_LIMITS['admin_email']) {
        $errors['admin_email'] = 'too_long';
    } elseif (sls_email_invalid((string)($body['admin_email'] ?? ''), $adminMail)) {
        $errors['admin_email'] = 'invalid';
    }

    $pwProblem = sls_password_problem($password, $adminMail);
    if ($pwProblem !== '') {
        $errors['password'] = $pwProblem;
    }

    // 確認用パスワード（一致確認は、パスワード自体が妥当なときだけ意味がある）
    if ($passwordC === '') {
        $errors['password_confirm'] = 'required';
    } elseif (!hash_equals($password, $passwordC)) {
        $errors['password_confirm'] = 'mismatch';
    }

    /* -------------------------------- 契約内容 -------------------------------- */

    $rawAdditional = $body['additional_accounts'] ?? '0';
    if (is_string($rawAdditional)) {
        $rawAdditional = sls_to_halfwidth(sls_one_line($rawAdditional));
        if ($rawAdditional === '') { $rawAdditional = '0'; }
    }
    $additional = 0;
    if (!is_int($rawAdditional) && !ctype_digit((string)$rawAdditional)) {
        $errors['additional_accounts'] = 'invalid';
    } else {
        $additional = (int)$rawAdditional;
        if ($additional < 0 || $additional > SLS_MAX_ADDITIONAL) {
            $errors['additional_accounts'] = 'out_of_range';
        }
    }

    /* -------------------------------- 規約同意 -------------------------------- */
    /* 画面に同意チェックがある場合のみ必須にする。SALES-1では未設置のため
       値が送られてこなくても通す（同意取得はSALES-2の決済直前で行う）。 */
    if (array_key_exists('agree_terms', $body)) {
        $agree = (string)$body['agree_terms'];
        if ($agree !== '1' && $agree !== 'on' && $agree !== 'true') {
            $errors['agree_terms'] = 'required';
        }
    }

    return [
        'valid'  => count($errors) === 0,
        'errors' => $errors,
        // パスワードは意図的に含めない
        'data'   => [
            'company_name'        => $companyName,
            'company_kana'        => $companyKana,
            'postal_code'         => $postal,
            'address'             => $address,
            'company_tel'         => $tel,
            'contact_email'       => $contactMail,
            'admin_name'          => $adminName,
            'admin_email'         => $adminMail,
            'additional_accounts' => $additional,
        ],
    ];
}
