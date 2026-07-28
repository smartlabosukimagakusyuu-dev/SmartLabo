<?php
/**
 * 問い合わせ内容の通知メール。
 *
 * ・本文はプレーンテキスト。
 * ・From はこのサイトのドメインのアドレス（利用者のアドレスにはしない）。
 *   利用者のアドレスは Reply-To に入れる。
 * ・ヘッダーへ入る値からは改行を必ず取り除く（ヘッダーインジェクション対策）。
 * ・mode が 'test' のときは送信せず、成功として扱う（検証用）。
 */

declare(strict_types=1);

/** ヘッダーへ入れてよい形に整える。改行と制御文字を落とす */
function slw_header_safe(string $v): string
{
    $v = str_replace(["\r", "\n", "\0"], '', $v);
    $v = preg_replace('/[\x00-\x1F\x7F]/u', '', $v) ?? '';
    return trim($v);
}

/**
 * 日本語を含む可能性のある表示名・件名をエンコードする（RFC 2047）。
 * mbstring が無い環境でも動くよう、自前のBase64エンコードを用意している。
 * XServerでは通常有効だが、拡張の有無で落ちない作りにしておく。
 */
function slw_encode_header(string $v): string
{
    $v = slw_header_safe($v);
    if ($v === '') {
        return '';
    }
    if (function_exists('mb_encode_mimeheader')) {
        return mb_encode_mimeheader($v, 'UTF-8', 'B', "\r\n");
    }
    // ASCIIのみならそのまま使える
    if (preg_match('/^[\x20-\x7E]*$/', $v) === 1) {
        return $v;
    }
    // 1行が長くなりすぎないよう、文字境界を壊さずに区切ってから符号化する
    $chunks  = [];
    $current = '';
    foreach (preg_split('//u', $v, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
        if (strlen($current) + strlen($char) > 39) {
            $chunks[] = $current;
            $current  = '';
        }
        $current .= $char;
    }
    if ($current !== '') {
        $chunks[] = $current;
    }
    $encoded = array_map(static fn($c) => '=?UTF-8?B?' . base64_encode($c) . '?=', $chunks);
    return implode("\r\n ", $encoded);
}

/** 通知メールの本文を組み立てる */
function slw_build_body(array $d): string
{
    $lines = [
        '公式サイトのお問い合わせフォームから送信されました。',
        '',
        '受付日時       : ' . date('Y-m-d H:i:s') . ' (JST)',
        '問い合わせ種別 : ' . (SLW_TYPES[$d['type']] ?? $d['type']),
        '会社名         : ' . $d['company'],
        'お名前         : ' . $d['name'],
        'メールアドレス : ' . $d['email'],
        '電話番号       : ' . ($d['tel'] !== '' ? $d['tel'] : '（未入力）'),
        '利用予定人数   : ' . (SLW_HEADCOUNTS[$d['headcount']] ?? '未回答'),
        '',
        '--- お問い合わせ内容 ---',
        $d['message'],
        '',
        '------------------------',
        'このメールへ返信すると、送信者へ直接返信されます。',
    ];
    // 本文の改行は CRLF に揃える
    return implode("\r\n", $lines) . "\r\n";
}

/**
 * 送信する。
 * 返り値: true = 送信できた（testモードでは常にtrue） / false = 送信できなかった
 * 例外は投げない。呼び出し側は成否だけを見る。
 */
function slw_send_notification(array $d, array $c): bool
{
    // 受信先が未設定なら送れない
    if (empty($c['to_email']) || empty($c['from_email'])) {
        return false;
    }

    $to      = slw_header_safe((string)$c['to_email']);
    $from    = slw_header_safe((string)$c['from_email']);
    $subject = slw_encode_header(
        '【お問い合わせ】' . (SLW_TYPES[$d['type']] ?? 'その他') . ' / ' . $d['company']
    );

    // Reply-To には利用者のアドレスを入れる。
    // 検証済みだが、ヘッダーへ入れる直前にもう一度改行を落とす。
    $replyTo = slw_header_safe($d['email']);
    if ($replyTo === '' || !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $replyTo = $from;   // 念のため。不正なら自ドメインへ向ける
    }

    $headers = [
        'From: ' . slw_encode_header((string)$c['from_name']) . ' <' . $from . '>',
        'Reply-To: ' . $replyTo,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'X-Auto-Response-Suppress: All',
        'Auto-Submitted: auto-generated',
    ];

    $body = slw_build_body($d);

    // 検証モードでは実際に送信しない
    if (($c['mode'] ?? 'test') !== 'live') {
        return true;
    }

    // -f で envelope-from を自ドメインに固定する（迷惑メール判定を避ける）
    $ok = @mail($to, $subject, $body, implode("\r\n", $headers), '-f' . $from);
    return $ok === true;
}
