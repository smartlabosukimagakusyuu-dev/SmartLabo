# SALES-0 — 販売・契約・課金仕様（Stripe対応）正式設計 レビュー記録

- 実施日: 2026-07-28
- ブランチ: `website-v2`（ローカルのみ・未push・masterは無変更）
- 種別: **設計・SSOT更新のみ**（コード・マイグレーション・Stripeダッシュボード操作なし）
- 設計の正本: [PROJECT_BIBLE/15_Stripe_Sales_Billing_Design.md](../../PROJECT_BIBLE/15_Stripe_Sales_Billing_Design.md)

本ファイルは工程の記録である。設計内容の詳細はすべて15番を正とし、ここには重複記載しない。

---

## 1. 作業開始時の状態

| 項目 | 値 |
|---|---|
| ブランチ | `website-v2` ／ working tree clean |
| HEAD | `060727b` WEB-V2-8: define sales model and add dual conversion paths |
| WEB-V2-8 | 確認済み（14_Sales_And_Billing_Policy.md v1.0 存在） |
| masterとの差分 | 119 files（website-v2配下＋PROJECT_BIBLE＋docs）。master `10463d6` 無変更 |
| CURRENT_STATUS | v7.4（WEB-V2-8反映済み・Project Bible 8.0） |

## 2. 成果物

| ファイル | 内容 |
|---|---|
| **PROJECT_BIBLE/15_Stripe_Sales_Billing_Design.md（新設 v1.0）** | Stripe設計・契約状態・DB設計・解約仕様・Webhook設計・法務追加確認・SALES-1前提・代表判断12項目 |
| PROJECT_BIBLE/14_Sales_And_Billing_Policy.md（v1.0→v2.0） | SALES-0確定2点の反映（初回決済=日割りのみ／キャンペーン無料対象=基本料金のみ） |
| PROJECT_BIBLE/README.md（8.0→8.1）・CHANGELOG.md・CURRENT_STATUS.md（v7.5） | 索引・履歴・現在地の同期 |

## 3. 設計の要点（詳細は15番）

1. **Stripe設計（15番3章）**: Product3種／Price3種（税抜・改定は新Price作成）／1社=1Customer=1Subscription／`billing_cycle_anchor`=翌月1日JSTで「利用開始日の日割り→毎月1日前払い」を構造的に実現。申込はStripe Checkout（ホスト型・カード情報非保持=PCI DSS SAQ-A相当）
2. **キャンペーン**: Coupon（100% off・`applies_to`基本料金Productのみ→「追加ユーザー対象外」をStripeの仕組みで強制）＋Promotion Code（`max_redemptions: 50`→先着50社をStripe側で強制）。「利用開始翌月の基本料金だけ無料」はSubscription Schedule 3フェーズ案を推奨（代替: Webhook後付けCoupon案。比較表あり）
3. **契約状態（15番4章）**: draft／pending_payment／active／past_due／suspended／cancel_scheduled／canceled の7状態＋mermaid遷移図。状態はStripeイベント駆動で同期
4. **DB設計（15番5章）**: contracts／campaigns／campaign_codes／referral_codes／stripe_webhook_events（冪等）／contract_events（監査）／invoices_mirror。カード情報は自社DBに一切保存しない。金額・残枠の正は常にStripe
5. **Webhook（15番10章）**: 指示の5イベント＋checkout.session.expired・charge.refunded・charge.dispute.created・payment_method.attached を追加提案（invoice.upcomingは任意）。署名検証・event_id冪等・順序非保証前提
6. **解約・失敗（15番6章・第一案）**: 当月末終了・日割り返金なし／Smart Retries→猶予14日(past_due)→停止(suspended)→30日で自動解約
7. **法務（15番7章）**: WEB-V2-8の15項目に加えStripe固有16項目（契約成立時点の定義・特商法の支払方法/時期・カード非保持のプライバシー記載・キャンペーン規約の無料範囲/先着判定・インボイスT番号）。**本文の変更は行っていない**

## 4. 代表判断が必要な事項

15番9章に12項目を一覧化（各項目に第一案を明記）。特にSALES-1着手前に必要なもの:

- 9-1 日割り計算方式（Stripe proration か 自前日割り計算か）
- 9-2 消費税計算（手動Tax Rate 10% か Stripe Tax か）
- 9-8 実装リポジトリ（`smartlabo-platform` 推奨 か `smartlabo-works` か）
- 9-9/9-10 解約タイミング・返金・猶予/停止/自動解約の日数
- 9-11 キャンペーン実装方式（Schedule 3フェーズ推奨 か Webhook後付けか）

そのほか: 9-3 月途中の人数変更／9-4 「追加ユーザー」と「追加アカウント」の表記統一／9-5 apply.html文言の軽微修正時期／9-6 先着50社の判定基準（決済成功順を推奨）／9-7 インボイス登録有無／9-12 請求書提供方法（Billing Portal推奨）

## 5. SALES-1 実装前提条件（15番8章）

①本設計の代表承認 ②上記判断事項の決定 ③特商法表記・キャンペーン規約の整備方針（**公開はこれらが揃うまで不可**）④Stripeアカウント開設・本人確認（代表作業）⑤実装先リポジトリ確定 ⑥テストはStripeテストモード＋Stripe CLI Webhook再送を必須

## 6. Git

- 1コミット（設計・SSOTのみ）。push・masterへの操作なし。website-v2/ のWeb表示ファイルは無変更

---

*作成: Claude Code / SALES-0（2026-07-28）*
