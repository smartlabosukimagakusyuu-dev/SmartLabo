<?php
// Smart Labo Works — 問い合わせメール送信
// 送信元・送信先・SMTP接続情報はすべてconfig.php(private/、秘密情報専用)で管理し、
// コードに直書きしない。未設定の場合は明確な例外を投げる(推測で代替しない)。
//
// メールアドレス構成(2026-07-14 CEO承認):
//   info@smartlaboworks.com   … 代表メール。送信処理では使用しない
//   contact@smartlaboworks.com … 問い合わせフォーム送信用(推奨構成)。
//                                  SMTP認証アカウント・管理者通知宛先・自動返信送信元に使用
//   noreply@smartlaboworks.com … 将来追加。作成後はauto_reply_fromの値のみ変更すればよい

require_once __DIR__ . '/SmtpMailer.php';

const SLW_TYPE_LABEL = [
    'consult' => '無料相談',
    'quote' => '見積り依頼',
    'brochure' => '資料請求',
    'demo' => 'デモ予約',
    'other' => 'その他',
    '' => '未選択',
];

function slw_require_config(string $key): string
{
    $value = slw_config_or_null($key);
    if (!$value) {
        throw new RuntimeException('Missing required config: ' . $key);
    }
    return $value;
}

function slw_build_mailer(): SlwSmtpMailer
{
    return new SlwSmtpMailer(
        slw_require_config('smtp_host'),
        (int) slw_config('smtp_port'),
        slw_require_config('smtp_user'),
        slw_require_config('smtp_pass')
    );
}

function slw_send_admin_notification(array $data, string $receiptId): void
{
    $mailer = slw_build_mailer();
    $to = slw_require_config('admin_notify_to');
    $from = slw_require_config('mail_from');
    $fromName = slw_config_or_null('mail_from_name') ?: 'Smart Labo Works';
    $typeLabel = SLW_TYPE_LABEL[$data['type']] ?? $data['type'];

    $lines = [
        '受付番号: ' . $receiptId,
        'ご相談種別: ' . $typeLabel,
        '会社名: ' . $data['company'],
        '氏名: ' . $data['name'],
        'メールアドレス: ' . $data['email'],
        '電話番号: ' . ($data['tel'] ?: '(未入力)'),
        '業種: ' . $data['industry'],
        '社員数: ' . ($data['employees'] ?: '(未入力)'),
        '無料デモ希望: ' . ($data['demo'] ? '希望する' : '希望しない'),
        'Configurator選択内容: ' . ($data['configuratorContext'] ?: '(なし)'),
        '',
        '相談内容:',
        $data['message'],
    ];

    $mailer->send(
        $from,
        $fromName,
        $to,
        '[Smart Labo Works] 新しいお問い合わせ（' . $typeLabel . '）',
        implode("\n", $lines)
    );
}

function slw_send_auto_reply(array $data, string $receiptId): void
{
    $mailer = slw_build_mailer();
    // 当面はcontact@smartlaboworks.com、将来noreply@smartlaboworks.comを作成した場合は
    // config.phpのauto_reply_fromのみ変更すれば切り替わる(コード変更不要)。
    $from = slw_require_config('auto_reply_from');
    $fromName = slw_config_or_null('auto_reply_from_name') ?: 'Smart Labo Works';
    $typeLabel = SLW_TYPE_LABEL[$data['type']] ?? $data['type'];

    $body =
        $data['name'] . " 様\n\n" .
        "この度はSmart Labo Worksへお問い合わせいただき、誠にありがとうございます。\n" .
        "以下の内容で受け付けました。担当者より改めてご連絡いたします。\n\n" .
        '受付番号: ' . $receiptId . "\n" .
        'ご相談種別: ' . $typeLabel . "\n\n" .
        "ご相談内容:\n" . $data['message'] . "\n\n" .
        "--\n株式会社スマートラボ\nSmart Labo Works";

    $mailer->send(
        $from,
        $fromName,
        $data['email'],
        '【Smart Labo Works】お問い合わせを受け付けました（受付番号: ' . $receiptId . '）',
        $body
    );
}
