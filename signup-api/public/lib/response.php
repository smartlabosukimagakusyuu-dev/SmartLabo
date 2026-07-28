<?php
/**
 * API共通レスポンス。
 * =============================================================================
 * 目的
 *   SALES-2以降（決済・アカウント作成・キャンペーン・紹介コード）で
 *   返す内容が増えても、**外側の形を変えずに済むようにしておく**こと。
 *   画面側は常に ok / result / errors だけを見ればよく、
 *   増える情報は data の中にだけ足していく。
 *
 * 形
 *   {
 *     "ok":     true|false,
 *     "result": "ok" | "invalid" | "rejected" | "too_many" | "failed",
 *     "message": "利用者へそのまま表示できる日本語",
 *     "data":    { ... 成功時の付随情報。将来ここに項目を足す ... },
 *     "errors":  { "項目名": "理由コード" },
 *     "meta":    { "api_version": "1", "endpoint": "signup", "request_id": "..." }
 *   }
 *
 * 内部情報（設定の不足内容・例外メッセージ・受信先メールアドレス・鍵）は
 * 一切含めない。原因はサーバーのエラーログにのみ残す。
 * =============================================================================
 */

declare(strict_types=1);

const SLS_API_VERSION = '1';

/** result コードと、利用者へ表示する文言の対応 */
const SLS_MESSAGES = [
    'ok'       => '入力内容を確認しました。',
    'invalid'  => '入力内容をご確認ください。',
    'rejected' => '送信できませんでした。時間をおいて再度お試しください。',
    'too_many' => '送信が続いています。しばらく時間をおいて再度お試しください。',
    'failed'   => '送信できませんでした。時間をおいて再度お試しください。',
];

/** 追跡用のID。個人情報を含まないランダム値で、ログとの突き合わせにのみ使う */
function sls_request_id(): string
{
    static $id = null;
    if ($id === null) {
        $id = bin2hex(random_bytes(8));
    }
    return $id;
}

/** 共通の応答ヘッダー */
function sls_send_headers(): void
{
    header('Cache-Control: no-store');
    header('Referrer-Policy: no-referrer');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
}

/**
 * JSONで応答して終了する。
 *
 * @param int    $status      HTTPステータス
 * @param string $result      SLS_MESSAGES のキー
 * @param array  $fieldErrors ['項目名' => '理由コード']
 * @param array  $data        成功時の付随情報（将来ここが増える）
 */
function sls_respond(int $status, string $result, array $fieldErrors = [], array $data = []): void
{
    http_response_code($status);
    sls_send_headers();
    header('Content-Type: application/json; charset=UTF-8');

    $payload = [
        'ok'      => $result === 'ok',
        'result'  => $result,
        'message' => SLS_MESSAGES[$result] ?? SLS_MESSAGES['failed'],
        'data'    => (object)$data,
        'errors'  => (object)$fieldErrors,
        'meta'    => [
            'api_version' => SLS_API_VERSION,
            'endpoint'    => 'signup',
            'request_id'  => sls_request_id(),
        ],
    ];

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
