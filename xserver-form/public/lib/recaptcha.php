<?php
// Smart Labo Works — Google reCAPTCHA v3 検証(開発モード/本番モード切替対応)
//
// 【開発モード】config.php の recaptcha_enabled が false の間(キー未設定時のデフォルト)は
// この検証をスキップし、honeypot・送信速度トラップ・CSRF・レート制限の4層のみで
// bot対策を行う。
// 【本番モード】ホームページ正式公開直前に、Google reCAPTCHA管理画面でサイトキー・
// シークレットキーを発行し、recaptcha_enabled を true にすると検証が必須になる。
//
// 【reCAPTCHA Enterpriseへの切替】v3ではなくEnterpriseを使う場合は、この関数の
// 検証先(SLW_RECAPTCHA_VERIFY_URL・リクエスト形式)をEnterprise用のprojects.assessments
// APIへ差し替えるだけでよい。呼び出し元(contact.php)・config.phpのキー構造(
// recaptcha_enabled/recaptcha_secret)は変更不要。

const SLW_RECAPTCHA_VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';
const SLW_RECAPTCHA_SCORE_THRESHOLD = 0.5;

function slw_verify_recaptcha(?string $token, string $remoteIp): array
{
    if (!slw_config('recaptcha_enabled')) {
        return ['success' => true, 'skipped' => true];
    }

    $secret = slw_config('recaptcha_secret');
    if (!$secret) {
        return ['success' => false, 'reason' => 'recaptcha_not_configured'];
    }
    if (!$token) {
        return ['success' => false, 'reason' => 'recaptcha_token_missing'];
    }

    $ch = curl_init(SLW_RECAPTCHA_VERIFY_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $remoteIp,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
    ]);
    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['success' => false, 'reason' => 'recaptcha_request_failed', 'detail' => $curlError];
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['success'])) {
        return ['success' => false, 'reason' => 'recaptcha_rejected'];
    }
    if (isset($data['score']) && $data['score'] < SLW_RECAPTCHA_SCORE_THRESHOLD) {
        return ['success' => false, 'reason' => 'recaptcha_low_score', 'score' => $data['score']];
    }

    return ['success' => true, 'score' => $data['score'] ?? null];
}
