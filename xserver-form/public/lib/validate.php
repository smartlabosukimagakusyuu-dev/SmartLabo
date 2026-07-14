<?php
// Smart Labo Works — 問い合わせフォーム 入力値検証(サーバー側)
// クライアント側(js/pages.js)の検証は信頼せず、ここで全項目を再検証する。

const SLW_FIELD_RULES = [
    'type' => ['required' => false, 'enum' => ['consult', 'quote', 'brochure', 'demo', 'other', '']],
    'company' => ['required' => true, 'maxLength' => 100],
    'name' => ['required' => true, 'maxLength' => 50],
    'email' => ['required' => true, 'maxLength' => 254],
    'tel' => ['required' => false, 'maxLength' => 20],
    'industry' => [
        'required' => true,
        'enum' => ['realestate_sales', 'realestate_management', 'legal', 'tax', 'construction', 'other'],
    ],
    'employees' => ['required' => false, 'enum' => ['1-10', '11-50', '51-200', '201+', '']],
    'message' => ['required' => true, 'maxLength' => 2000],
];

function slw_validate_contact_payload($body): array
{
    $errors = [];
    $clean = [];

    if (!is_array($body)) {
        return ['valid' => false, 'errors' => ['invalid_payload'], 'data' => null];
    }

    foreach (SLW_FIELD_RULES as $key => $rule) {
        $value = isset($body[$key]) && is_string($body[$key]) ? trim($body[$key]) : '';

        if ($rule['required'] && $value === '') {
            $errors[] = $key . '_required';
        }
        if ($value !== '' && isset($rule['maxLength']) && mb_strlen($value) > $rule['maxLength']) {
            $errors[] = $key . '_too_long';
            $value = mb_substr($value, 0, $rule['maxLength']);
        }
        if (isset($rule['enum']) && $value !== '' && !in_array($value, $rule['enum'], true)) {
            $errors[] = $key . '_invalid';
        }
        if ($key === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'email_invalid';
        }

        $clean[$key] = $value;
    }

    $demo = isset($body['demo']) && ($body['demo'] === true || $body['demo'] === 'true');
    $clean['demo'] = $demo;

    $consent = isset($body['consent']) && ($body['consent'] === true || $body['consent'] === 'true');
    if (!$consent) {
        $errors[] = 'consent_required';
    }
    $clean['consent'] = $consent;

    // Configurator連携(任意項目、選択内容の要約表示にのみ使う)
    $context = isset($body['configuratorContext']) && is_string($body['configuratorContext'])
        ? mb_substr($body['configuratorContext'], 0, 500)
        : '';
    $clean['configuratorContext'] = $context;

    return ['valid' => count($errors) === 0, 'errors' => $errors, 'data' => $clean];
}
