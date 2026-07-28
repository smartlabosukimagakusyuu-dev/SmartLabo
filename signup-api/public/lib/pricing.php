<?php
/**
 * 料金の正規値。
 * =============================================================================
 * ここが「サーバー側の正」。画面から送られてきた金額は一切信用せず、
 * 常にこのファイルの値から計算し直す（改ざんされた金額で契約が成立しないように）。
 *
 * 金額の出典（SSOT）:
 *   PROJECT_BIBLE/14_Sales_And_Billing_Policy.md 第3章
 *
 * 金額を変更するときは、次の5か所すべてを同時に更新すること。
 *   ① PROJECT_BIBLE/14_Sales_And_Billing_Policy.md
 *   ② docs/reviews/tools/check-prices.js の CANONICAL
 *   ③ website-v2/index.html
 *   ④ website-v2/pricing.html
 *   ⑤ 本ファイル
 * =============================================================================
 */

declare(strict_types=1);

/** 税抜の正規金額（円） */
const SLS_PRICE_INITIAL    = 10000;  // 初期設定費（1回）
const SLS_PRICE_MONTHLY    = 20000;  // 基本料金（月額・管理者1名を含む）
const SLS_PRICE_ADDITIONAL = 3000;   // 追加アカウント（月額・1名）

/** 追加アカウント数の上限（画面・サーバー共通の入力上限） */
const SLS_MAX_ADDITIONAL = 999;

/**
 * 契約内容の見積りを組み立てる。
 *
 * 注意: これは「画面に表示するための概算」であり、実際の請求額ではない。
 * 実際の初回請求は利用開始月の日割りであり、確定額は決済実装（SALES-2）で
 * Stripeの見積りAPIから取得する。ここでは月額の満額のみを返し、
 * 日割り額を自前計算して表示することは意図的に行っていない。
 * （自前計算とStripeの請求額がずれる事故を構造的に防ぐため）
 */
function sls_quote(int $additional): array
{
    if ($additional < 0) {
        $additional = 0;
    }

    $additionalTotal = SLS_PRICE_ADDITIONAL * $additional;
    $monthlyTotal    = SLS_PRICE_MONTHLY + $additionalTotal;

    return [
        'currency'          => 'JPY',
        'tax_included'      => false,          // すべて税抜
        'initial_fee'       => SLS_PRICE_INITIAL,
        'monthly_base'      => SLS_PRICE_MONTHLY,
        'additional_unit'   => SLS_PRICE_ADDITIONAL,
        'additional_count'  => $additional,
        'additional_total'  => $additionalTotal,
        'monthly_total'     => $monthlyTotal,
        'total_users'       => $additional + 1,  // 管理者1名＋追加分
        // 実際の初回請求額はここでは出さない（下記noteのとおり）
        'first_charge'      => null,
        'note'              => '表示はすべて税抜の月額です。実際の初回請求は利用開始月の日割りとなり、確定額はお申し込みの最終画面でご確認いただけます。',
    ];
}
