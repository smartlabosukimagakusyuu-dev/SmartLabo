# WEB-SALES-4 — Stripe決済・Webhook・契約状態管理

- 実施日: 2026-08-09
- 対象リポジトリ: `smartlabo-works-lite`（正式製品）
- ブランチ: `feature/web-sales-4-stripe-contracts`
- 分岐元: `feature/web-sales-3-production-mail` `97ec04e3632a3fb7ae89f0ce838e7d3756546399`
- 種別: 実装工程（**テストモードのみ。実Stripeへは未接続・本番変更0**）

> ## この工程で守った一番大事なこと
>
> **契約を `active` にしてよい根拠は、署名検証済みのWebhookで支払いの成功を確認したときだけ。**
> 決済完了画面（success_url）への到達は根拠にしない。URLは誰でも開けるため、
> それを根拠にすると支払わずに契約を有効化できてしまう。

---

## 1. 料金計算

### 1-1. 正本

[PROJECT_BIBLE/14_Sales_And_Billing_Policy.md](../../PROJECT_BIBLE/14_Sales_And_Billing_Policy.md) /
[15_Stripe_Sales_Billing_Design.md](../../PROJECT_BIBLE/15_Stripe_Sales_Billing_Design.md)

| 項目 | 金額（税別） | 備考 |
|---|---|---|
| 初期設定費 | 10,000円 | 申込時に1回（one_time） |
| 基本料金 | 20,000円／月 | **管理者1名を含む** |
| 追加アカウント | 3,000円／月・1名 | 2人目以降 |

### 1-2. 計算式（推測ではなくSSOTの実測で確定）

```
追加席数   = 契約人数 − 1        （基本料金に1名含まれるため）
月額合計   = 20,000 + 3,000 × 追加席数
```

SSOTの例と一致することをテストで機械確認した。

| 契約人数 | 月額 | 内訳 |
|---|---|---|
| 1人 | 20,000円 | 基本料金のみ |
| 3人 | 26,000円 | 基本料金 ＋ 追加2名 |
| 5人 | 32,000円 | 基本料金 ＋ 追加4名 |

### 1-3. Checkoutの明細

`mode: subscription` で次を送る。追加席が0名のときは行そのものを送らない。

| Price | 数量 |
|---|---|
| `STRIPE_PRICE_BASE_MONTHLY` | 1（固定） |
| `STRIPE_PRICE_ADDITIONAL_SEAT` | 契約人数 − 1（0なら行なし） |
| `STRIPE_PRICE_SETUP` | 1 |

**金額・数量・Price IDはすべてサーバー側で決める。** ブラウザから送られてきた
`amount` / `quantity` / `priceId` / `companyId` は一切読まない（テストで改ざん耐性を確認）。

### 1-4. 請求日と日割り

SSOT 15番 3-2 のとおり `subscription_data.billing_cycle_anchor` に
**翌月1日 0:00 JST** を渡す。これにより

- 初回のInvoiceが「利用開始日〜月末」の按分（日割り）になる
- 以後は毎月1日の請求になる

日割りの金額は **Stripeのproration に委ね、自前で計算しない**（SSOT 2-3 の推奨）。
自前計算とStripeの請求額がずれる事故を構造的に防ぐため。
`monthlyQuote()` が返す `firstChargeAmount` は常に `null` にしている。

---

## 2. 状態遷移

`companies.contract_status` の1列だけで表す。

```
        （Web申込・パスワード設定）
                 │
                 ▼
        payment_required ──── 支払い成功(Webhook) ───▶ active
                 │                                      │  ▲
                 │                                      │  │ invoice.paid
                 │ subscription.deleted        支払い失敗 │  │
                 │                                      ▼  │
                 │                                   past_due
                 │                                      │
                 └──────────────▶ canceled ◀────────────┘
```

| 状態 | 意味 | 入り方 |
|---|---|---|
| `payment_required` | 支払い前 | Web申込のprovisioning（WEB-SALES-2） |
| `active` | 利用可能 | `checkout.session.completed`(paid) / `invoice.paid` |
| `past_due` | 支払い失敗 | `invoice.payment_failed` / `subscription.updated(past_due,unpaid)` |
| `canceled` | 解約済み | `subscription.deleted` / `subscription.updated(canceled)` |

- 既存の会社（Web申込を通っていない）は `active` のため、これまでどおり動く
- `active` へ移せるのは `payment_required` / `past_due` からだけ
- `past_due` → `active` の復帰は `invoice.paid` で行う

**内部状態は追加していない。** Stripe側の subscription status も保存していない
（自社の `contract_status` と二重の正本になり、どちらが正しいか分からなくなるため）。

---

## 3. contract_started_at（契約開始日）の確定条件

| 条件 | 扱い |
|---|---|
| Checkoutの戻り画面（success_url）に到達 | **設定しない** |
| `checkout.session.completed` で `payment_status` が paid 以外 | **設定しない** |
| 署名検証済みの支払い成功Webhook（paid / invoice.paid） | **設定する** |
| 2回目以降の支払い成功Webhook | **上書きしない** |
| 同じイベントの再送 | **上書きしない** |
| 解約（canceled） | **消さない**（履歴として残す） |

実装は SQL の `COALESCE(contract_started_at, ?)` で守っている。
既に日付が入っていれば、何度Webhookが来ても変わらない。

**再契約時の扱い**: 現在の実装では、解約後に再度支払いが成功しても
`contract_started_at` は最初の契約開始日のまま残る（`COALESCE` のため）。
「再契約時に開始日を新しくするか、初回契約日を残すか」は
**未確定の代表判断事項**（第9節 D-1）。

---

## 4. Webhookの署名検証

`server/services/billing/stripeSignature.js`

1. `Stripe-Signature` ヘッダーから `t`（時刻）と `v1`（署名）を取り出す
   - `v1` は複数あることがある（鍵の入れ替え中）。1つでも一致すれば本物
   - `v0` など知らないスキームは無視する
2. `${t}.${生の本文}` を Webhook署名シークレットで HMAC-SHA256
3. 定数時間比較（`timingSafeEqual`）で照合
4. `t` が現在時刻から `STRIPE_WEBHOOK_TOLERANCE_SEC`（既定300秒）以上離れていれば拒否

| 守っていること | 内容 |
|---|---|
| 生の本文で検証 | `express.raw` で受け、**検証が終わるまでJSONへ変換しない** |
| シークレット未設定 | 「検証成功」にしない。503で受け付けない |
| 署名なし・不正・別シークレット | 400で拒否。台帳へも残さない |
| リプレイ | 過去にも未来にも離れすぎた署名を拒否 |
| ログ | 署名値・本文を出さない。分類コードだけ |

### CSRF対策との関係

Webhookは Cookie も Origin も CSRFトークンも持たない。
そのため `app.js` でこのルートを **セッション読み込み・CSRF確認より前に登録** している。

「CSRF例外の一覧」を作らないのは意図的である。一覧を用意すると、
あとから他のAPIが同じ例外へ紛れ込む余地ができるため。
代わりに、このルート自身が署名検証を必ず行う責任を持つ。

---

## 5. 冪等性（二重処理の防止）

`stripe_events` テーブルの `stripe_event_id` に **UNIQUE制約**。

考え方は「処理してから記録する」ではなく **「記録できた側だけが処理する」**。

| 場面 | 動作 |
|---|---|
| 同じイベントの再送 | 2回目は `duplicate` として何もしない（200を返す） |
| 並行受信（同時4件） | INSERTできた1件だけが処理する |
| 順序の逆転 | 同じ会社・同じ種別で、より新しいイベントを処理済みなら `STALE_EVENT` で何もしない |
| 処理中の失敗 | `failed` として記録し、Stripeの再送でもう一度だけ担当できる |
| 状態変更 | `BEGIN IMMEDIATE` のトランザクション内で行う |

台帳に保存するのは種別・処理結果・安全な分類コードだけ。
**生ペイロード・署名・秘密値・顧客の個人情報は保存しない。**

---

## 6. テナント分離

Webhookの相手を特定するとき、**metadataの `company_id` だけを信用しない。**
metadataはStripeの管理画面から編集でき、他社のIDを書けるため。

1. `stripe_customer_id` / `stripe_subscription_id` でDBから会社を引く
2. 両方から引けたのに別の会社を指していれば処理しない（`COMPANY_MISMATCH`）
3. metadataがあれば、DBから引いた会社と一致することを確かめる
4. 引けなければ処理しない（`COMPANY_NOT_FOUND`）

Checkout作成側も、会社IDはセッション由来のみ。リクエストの会社IDは読まない。

---

## 7. 契約人数（seat）の制御

| 項目 | 扱い |
|---|---|
| 契約人数の正本 | `companies.license_count`（WEB-SALES-2で申込時に確定） |
| Stripeの数量との整合 | 追加席の quantity = `license_count − 1` |
| 管理者を人数に含めるか | **含める**（SSOT: 基本料金に管理者1名を含む） |
| `active` になる前の利用者追加 | **禁止**（409 `CONTRACT_NOT_ACTIVE`） |
| `active` 後の上限 | WEB-SALES-1B の seat 制限をそのまま使う（409 `SEAT_LIMIT_REACHED`） |
| 停止中のアカウント | 枠を使い続ける（一時停止で枠は空かない） |

**契約人数の変更（増減）は本工程では実装していない。**
Stripeの quantity 変更と日割りの扱いが必要になるため、別工程とする（第9節 D-2）。
現在は申込時の人数のまま固定される。

---

## 8. 契約状態ごとの許可リスト

方式は**許可リスト**。メソッドとパスの**完全一致**で判定する
（前方一致にすると `/api/contract` の許可が `/api/contract-secret` まで通るため）。

| 状態 | 使えるAPI | 拒否のコード |
|---|---|---|
| `active` | 制限なし | ― |
| `payment_required` | 共通＋`POST /api/billing/checkout` | 402 `PAYMENT_REQUIRED` |
| `past_due` | 共通＋`POST /api/billing/checkout` | 402 `PAYMENT_PAST_DUE` |
| `canceled` | 共通＋`POST /api/billing/checkout` | 403 `CONTRACT_CANCELED` |
| 未知の値 | 共通のみ | 403 `CONTRACT_INACTIVE`（閉じる側へ倒す） |

共通で使えるのは次だけ。ログインしたまま操作不能にならないよう、
出口と自分の状態の確認は常に残す。

- `POST /api/auth/login` / `POST /api/auth/logout`
- `GET /api/auth/me` / `PATCH /api/auth/password`
- `GET /api/me` / `GET /api/contract/status`
- `GET /api/health`

既存の認証・テナント分離・CSRF・Origin対策はいずれも弱めていない
（公開WebsiteのOriginで業務APIが通らないことも含め、テストで確認済み）。

---

## 9. test / live の分離

| 仕組み | 内容 |
|---|---|
| 起動時の判定 | `sk_live_` かつ `STRIPE_LIVE_ENABLED != true` なら**起動を停止**する |
| ログ | 鍵の種別（test / live）だけを出す。値は出さない |
| 接続先 | `https://api.stripe.com/v1` の固定値。環境変数で任意の宛先へ向ける仕組みは作らない |
| Price ID | サーバー側の許可リスト（環境変数の3つ）のみを使う |
| 設定不足 | 鍵・Price IDが1つでも欠けていれば決済画面へ進ませない（503） |

---

## 10. DB（migration 020）

### 追加した列（companies）

| 列 | 用途 |
|---|---|
| `stripe_customer_id` | 1社=1 Customer。UNIQUE索引 |
| `stripe_subscription_id` | 1社=1 Subscription。UNIQUE索引 |
| `current_period_end` | 次回の請求期間の終わり（画面案内用） |
| `canceled_at` | 解約が確定した日時 |

### 追加したテーブル（stripe_events）

`id` / `stripe_event_id`(**UNIQUE**) / `event_type` / `company_id` /
`event_created_at` / `processing_status` / `safe_error_code` / `received_at` / `processed_at`

### あえて追加しなかったもの（重複を作らない）

| 候補 | 理由 |
|---|---|
| `contracted_seats` | `license_count`（003で追加済み）が契約人数の正本。2か所に人数があると必ず食い違う |
| `contract_started_at` | 003で追加済み。この列だけを使う |
| `subscription_status` | 自社の `contract_status` と二重の正本になる。Stripeの状態は必要なときにStripeへ問い合わせる |

### 保存しないもの

秘密鍵 / Webhook署名シークレット / カード番号 / セキュリティコード /
Checkout URL / Webhookの生ペイロード / 不要な顧客の個人情報

---

## 11. Stripeイベントの対応一覧

| イベント | 動作 |
|---|---|
| `checkout.session.completed` | `payment_status` が paid / no_payment_required のときだけ `active` へ |
| `invoice.paid` | `active` へ（`past_due` からの復帰も）。期間終了日を更新 |
| `invoice.payment_failed` | `active` → `past_due` |
| `customer.subscription.updated` | past_due / unpaid → `past_due`、canceled → `canceled`。**active/trialing では有効化しない**（支払いの証拠がないため） |
| `customer.subscription.deleted` | `canceled` |
| 上記以外 | 受信は記録するが何もしない（`UNHANDLED_EVENT_TYPE`） |

---

## 12. 実装方針の判断（依存パッケージを増やさなかった理由）

Stripe公式SDK（`stripe` npm）は**採用しなかった**。

| 観点 | 判断 |
|---|---|
| 既存の作り | Liteは外部API（OpenAI・Google Vision）をすべて `fetch` で呼んでおり、SDKを持たない |
| 使う機能 | Customer作成とCheckout Session作成の2つだけ。どちらもフォーム形式のPOST 1本 |
| 署名検証 | Stripeが公開している仕様（HMAC-SHA256・`t.payload`・許容時間）どおりに実装し、改ざん・リプレイ・複数署名・シークレット未設定を含めてテストで確認 |
| 将来 | 公式SDKへ移行する余地は残している（`stripeClient.js` / `stripeSignature.js` の2ファイルを差し替えるだけ） |

旧 `smartlabo-works` は公式SDKを採用していたが、**コードは一切コピーしていない**
（仕様・テスト観点のみを参照した）。

---

## 13. テスト

`tests/acceptance/stripe-contracts.mjs`（**103件**）

| 区分 | 件数 | 主な確認 |
|---|---|---|
| A 料金計算 | 15 | SSOTの3例と一致・明細の数量・請求アンカー（12月→翌年1月の繰り上げ含む） |
| B Checkout作成 | 23 | 未認証／一般利用者／active会社の拒否・**金額/数量/Price ID/会社IDの改ざんが効かない**・秘密鍵をURLへ載せない |
| C 署名検証 | 9 | 署名なし／不正／別シークレット／古すぎ／未来すぎを拒否・複数v1・シークレット未設定 |
| D 支払い成功 | 11 | **支払い成功でactive**・契約開始日は初回のみ・未払いcheckoutでは有効化しない・subscription.updated(active)だけでは有効化しない |
| E 冪等性 | 9 | 再送で二重処理なし・**並行4件で処理1回**・順序逆転で古いイベントが上書きしない |
| F 失敗と解約 | 10 | past_due・復帰・canceled・**metadataだけの他社指定を拒否**・知らない顧客ID |
| G 利用制御 | 13 | 状態ごとの許可リスト・**active前の利用者追加を禁止**・seat上限 |
| H 秘密値 | 13 | 台帳/会社/応答に秘密値なし・生ペイロードなし・test判定・接続先固定 |

**外部Stripeへは接続していない**（127.0.0.1のスタブ＋自前で署名を生成）。
ネットワーク監視で実外部通信0件を確認。

### 全体

| テスト | 件数 |
|---|---|
| 既存12本 | 974 |
| stripe-contracts（新規） | 103 |
| **合計** | **1077件 成功・失敗0・skip1** |

既存テストは1件も削除・緩和していない（既存テストファイルは無改変）。

---

## 14. 本番導入前の未完了事項

| # | 内容 | 影響 |
|---|---|---|
| 1 | **Stripeテストモードでの実機E2E未実施** | WEB-SALES-4S で実施する |
| 2 | **Product / Price の作成が未実施** | Price IDが未設定のため、現状は決済画面へ進めない（503） |
| 3 | **本番Webhookエンドポイント未登録** | 登録しないと本番では決済後に `active` にならない |
| 4 | **創業記念キャンペーン（先着50社）未実装** | SSOT 15番 4章の Subscription Schedule 3フェーズ案は未確定 |
| 5 | **契約人数の変更（増減）未実装** | 申込時の人数で固定。変更は別工程 |
| 6 | **決済画面（/billing/complete・/billing/cancelled）未実装** | 戻り先URLは設定できるが、画面そのものは未作成 |
| 7 | **法務3点未整備** | 特商法表記・利用規約の契約条項・キャンペーン規約 |
| 8 | **紹介コード・キャンペーンコード未実装** | SSOT 15番の別工程 |
| 9 | **請求書・領収書の提供未実装** | Stripeの機能をそのまま使うか要判断 |

---

## 15. rollback（切り戻し）の方法

| 対象 | 方法 |
|---|---|
| コード | このブランチをマージしていないため、`master` は無変更。切り戻しは不要 |
| migration 020 | 前進のみのため down は無い。切り戻す場合は、追加した列とテーブルを手動で削除する（`ALTER TABLE companies DROP COLUMN ...` / `DROP TABLE stripe_events`）。**既存の列・テーブルには一切変更を加えていないため、削除しても既存機能は動く** |
| 決済の停止 | `.env` の `STRIPE_SECRET_KEY` を空にすると、決済画面へ進めなくなる（503）。契約状態は変わらない |
| Webhookの停止 | `STRIPE_WEBHOOK_SECRET` を空にすると、Webhookは503で受け付けない。契約状態は変わらない |
| 契約状態 | `contract_status` を `active` に戻せば、その会社は通常どおり使える |

**このブランチは `master` へマージしていない。** 本番は無変更のまま。

---

## 16. 代表判断事項

| # | 事項 | 推奨 |
|---|---|---|
| D-1 | 再契約時に `contract_started_at` を新しくするか、初回契約日を残すか | **初回契約日を残す**（現在の実装）。契約履歴として意味があり、請求はStripe側の期間で決まるため |
| D-2 | 契約人数の変更（増減）をいつ実装するか | 招待機能（WEB-SALES-6）と同時が自然。月途中の増加は日割り課金・削減は翌月反映（SSOT 15番 3-2 の第一案） |
| D-3 | 創業記念キャンペーンを実装するか | SSOT 15番 4章の案A（Subscription Schedule 3フェーズ）。先着50社の管理方法も要決定 |
| D-4 | 決済画面（戻り先）のURL | `https://lite.smartlaboworks.com/billing/complete` / `/billing/cancelled` を想定。画面はWEB-SALES-4S以降で作成 |
| D-5 | Product / Price をテストモードで作成する時期 | WEB-SALES-4S の直前。SSOT 15番 3-1 の名前（`slw_lite_base_monthly_v1` 等）で作成 |

---

## 17. Go / No-Go

| 対象 | 判定 |
|---|---|
| **WEB-SALES-4S（Stripeテストモード実機E2E）** | **Go** |
| 本番公開 | **No-Go**（法務3点未整備・本番Webhook未登録・決済画面未作成） |

WEB-SALES-4S で必要になる代表作業

1. Stripeテストモードで Product / Price 3件を作成（SSOT 15番 3-1）
2. `.env` へ `STRIPE_PRICE_*` 3件を設定
3. Stripe CLI で Webhook を転送し、`STRIPE_WEBHOOK_SECRET` を設定
4. テストカードでの決済（カード情報の入力は代表が行う）

---

*作成: Claude Code / WEB-SALES-4（2026-08-09）*
*本工程では実Stripeへ接続していない。本番変更0。*
