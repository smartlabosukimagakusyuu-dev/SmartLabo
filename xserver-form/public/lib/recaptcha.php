<?php
// Smart Labo Works — Google reCAPTCHA v3 検証
//
// ホームページ正式公開直前まで config.php の recaptcha_enabled は false のまま
// 運用する(CEO指示)。false の間は honeypot・送信速度トラップ・CSRF・レート制限の
// 4層のみでbot対策を行う。true に切り替えた時点でこの検証が必須になる。

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
