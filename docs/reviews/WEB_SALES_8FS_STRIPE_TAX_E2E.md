# WEB-SALES-8FS Stripe test mode 固定10％Tax Rate 実機E2E確認

- 実施日: 2026-08-11
- 対象: Smart Labo Works Lite（feature/web-sales-8fb-billing-safety = 8143d02）
- 範囲: **Stripe test mode限定**。live mode・本番Price・本番Webhook・本番VPS/DBへの接触は一切なし
- 本文書にStripe ID・Tax Rate ID・URL・秘密値・PIIは記載しない

## 1. 目的と結論

WEB-SALES-8F／8F-Bで実装した固定10％Tax Rate（外税・JCT）が、実Stripeの
①初回請求（日割り）②毎月請求 ③人数増加の日割り請求 に正しく反映されることを、
Test Clock付きの完全架空データで確認した。

**結論: 全確認項目一致。WEB-SALES-8FSはGo。**（丸め方式の厳密判定のみ「未判定」＝下記7章）

## 2. 作成した外部オブジェクト（上限内）

| 種別 | 件数 | 備考 |
|---|---|---|
| Tax Rate（test） | 1 | 残置（下記3章）|
| Test Clock | 1 | 後片付けで削除済み |
| Customer | 1 | 完全架空・example.invalid・後片付けで削除済み |
| Checkout Session | 1 | 再作成なし |
| Subscription | 1 | 後片付けで解約済み（canceled残存・削除不可仕様）|
| Invoice | 3 | Stripe自動作成（初回・月次・人数増加）。paidのため削除不可仕様・残存 |

Stripe CLIによるWebhook転送はlocalhost隔離APIのみ。既存のProduct 3件・Price 3件・
他purposeのtestデータは参照のみで不変。

## 3. Tax Rateの設定内容（IDなし）

- display_name=消費税／description=Smart Labo Works Lite test mode
- percentage=10・inclusive=false（外税）・country=JP・jurisdiction=JP・tax_type=jct
- active=true・livemode=false
- metadata: purpose=web-sales-8fs / env=test / run_id（非PII識別子）
- 作成後にAPI読み戻しで12項目を機械確認（全一致）
- **残置**: 後続testの正式なtest用Tax Rateとしてactiveのまま残した。
  無効化するかは代表判断事項（Tax Rateは削除不可のため）

## 4. 初回請求（billing_reason=subscription_create）

契約人数3名（基本1名込＋追加2席）・請求アンカー=翌月1日0:00 JST・
proration_behavior=create_prorations。日割りはStripe算定を正本とした。

| 明細 | 税別 | 税(10%) |
|---|---|---|
| 初期設定費（日割りなし） | 10,000 | 1,000 |
| 基本料金 日割り（8/11→9/1・按分64.795%） | 12,959 | 1,296 |
| 追加席×2 日割り | 3,888 | 389 |
| **合計** | **26,847** | **2,685** |

- total=29,532／amount_paid=29,532／amount_remaining=0／subtotal+tax=total 一致
- 3明細すべてに同一の固定Tax Rateが1件だけ・全明細exclusive
- Checkout画面の表示額と請求書が一致。自社の見込み表示（月額26,000+税2,600）は
  「見込み」であり、確定額はStripe請求書が正本という設計どおり
- 按分比率は暦の残期間と±1%内で整合（検算のみ・自社日割り計算なし）

## 5. 月次請求（billing_reason=subscription_cycle）

Test Clockをアンカー+5分→+2時間の最小進行で draft→確定→自動支払い。

| 明細 | 税別 | 税(10%) |
|---|---|---|
| 基本料金 | 20,000 | 2,000 |
| 追加席×2 | 6,000 | 600 |
| **合計** | **26,000** | **2,600** |

- total=28,600／amount_paid=28,600／remaining=0／subtotal+tax=total 一致
- 初期設定費の明細なし・二重Invoiceなし・Subscription明細のTax Rate維持
- contract_status=active・license_count=3・contract_started_at不変

## 6. 人数増加 3→5名（billing_reason=subscription_update）

正式API（POST /api/billing/seat-increase）経由。製品コード外側の観測で、送信は
payment_behavior=pending_if_incomplete・proration_behavior=always_invoice・
items（既存明細id+quantity）**の3種のみ**であることを確認。

| 明細（Stripe標準の按分2行構成） | 税別 | 税 |
|---|---|---|
| 未使用時間の相殺（2席・9/1以降） | -5,983 | -598 |
| 残り時間（4席・9/1以降） | +11,965 | +1,196 |
| **純額** | **5,982** | **598** |

- total=6,580／status=paid（初回カードで自動決済・追加のカード入力不要）／remaining=0
- マイナス行は同一請求書内の按分相殺であり、返金・クレジットノートは0件
  （charges.amount_refunded合計=0・credit_notes=0を機械確認）
- Stripe追加席quantity 2→4／DB license_count 3→5（反映は1回だけ・台帳applied）
- pending_updateは支払い成功後に解消・全按分明細に所定Tax Rate1件
- 日割り税別はStripe期間比率と±1%内で整合（自社計算なし）

## 7. 丸め方式の実測（設定変更なし）

3請求書で「各明細税の丸め合計」「課税対象合算→丸め」「total_tax」を比較。

- 全請求書で **明細税の合計＝請求書の合計税額** が成立
- 人数増加請求書の +11,965×10%=1,196.5 が **1,196**（切り上げでない）となったため、
  「ラインアイテムレベル（四捨五入=half-up）」は**棄却**
- 残る候補「ラインアイテムレベル（銀行丸め=half-even）」と「請求書レベル」は、
  3請求書すべてで予測値が同値のため**厳密判定は未判定**
- 技術的影響: 単一10%税率・少数明細の現行商品構成では両方式の請求額は実質同値。
  複数税率や多明細を導入する場合は差が出るため、その前に方式を確定すべき。
  税務上どちらを採用すべきか（インボイス制度の端数処理要件との整合）は
  **税理士等への確認事項**として分離する。設定は変更していない。

## 8. Tax Rate引継ぎ・税率監視（WEB-SALES-8F-B）

- Checkout 3明細→Subscription 2定期明細→月次/日割りInvoiceまでTax Rate引継ぎを全点確認
- stripe_eventsのsafe_error_codeに BILLING_TAX_*（税率異常）は0件
- safe_error_codeにStripe ID・PIIの混入なし（機械確認）

## 9. Webhook・DB・冪等性

- 受信イベントは全て200応答（checkout.session.completed=processed、
  invoice.paid初回=ALREADY_ACTIVEでskip=契約有効化の二重防止として期待どおり）
- checkout.session.completedを実再送→200・イベント台帳の行数不変・
  contract_started_at/license_count不変（二重処理なし）
- DB最終状態: contract_status=active・license_count=5・contract_started_at=初回決済日で不変
- 二重Invoice 0・二重Webhook処理 0・返金/クレジット 0

## 10. 後片付け

1. 未払い0確認 → 2. Subscription解約 → 3. Customer削除 → 4. Test Clock削除
→ 5. Stripe CLI停止 → 6. Webhook secret破棄（プロセス環境のみ保持のため停止で消滅）
→ 7. 隔離API・画面サーバー停止 → 8. ポート解放確認 → 9. 隔離SQLite・一時スクリプト・
ログ・観測ファイルを全削除。

残存testオブジェクト（Stripe仕様上削除不可・全て livemode=false・完全架空）:
- Tax Rate 1件（意図的残置・3章）／canceled Subscription 1件／paid Invoice 3件

## 11. 安全性の確認

- 秘密値・Stripe ID・Tax Rate ID・URLの画面/ログ/報告への露出 0
- Tax Rate IDはプロセス環境のみ・.env未保存。Webhook secretも同様
- 実カード・実在個人情報の使用 0（Stripe公式テストカード4242のみ・全データ架空）
- 本番VPS・本番DB・本番Webhook・本番サイトへの接続/遷移 0
- .env・製品コード・テスト・migration・CURRENT_STATUS の変更 0
- 製品repo: branch/HEAD不変（8143d02）・commit 0・push 0・clean

## 12. 未確認事項

- 丸め方式の厳密判定（7章・未判定。判定にはStripe Dashboardの請求書設定の目視か、
  複数税率/多明細での追試が必要）
- 支払い失敗系（カード拒否・3DS要求）の実機確認は本工程の範囲外（モックテスト済み）
- 解約予約・人数削減・再契約の実機確認は範囲外
- 実Stripeの請求書PDF/領収書の表記（日本語表示・税表記）の目視確認

## 13. 判定

- **WEB-SALES-8FS: Go**（実装差異なし・全確認項目一致）
- **master統合: Go**（feature/web-sales-8fb-billing-safety=8143d02 を対象に可。
  統合作業自体は別途指示待ち）
- **本番公開: No-Go継続**（法務整備・本番Webhook登録・本番Price作成・
  live Tax Rate作成が未実施のため）

## 14. 次工程（案）

1. 代表判断: test用Tax Rateの残置/無効化・丸め方式の正式採用（税理士確認）
2. INTEGRATION-8FB（master統合）
3. 本番準備工程（live Tax Rate・本番Price・本番Webhook登録）は別途指示後
