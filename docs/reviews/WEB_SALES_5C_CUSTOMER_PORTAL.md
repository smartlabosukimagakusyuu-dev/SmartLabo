# WEB-SALES-5C — Stripe Customer Portal 基盤

- 実施日: 2026-08-10
- 対象リポジトリ: `smartlabo-works-lite`（正式製品）
- ブランチ: `feature/web-sales-5c-customer-portal`
- 分岐元: `feature/web-sales-5b-contract-gating` `99cda823ebeb2a0625063ef4c8c486f1075c11e7`
- 種別: 決済基盤（**本番変更0・実Stripe未接続・実決済0・実Webhook0・実メール0**）

> 会社管理者が、当社への依頼なしにカードの変更と請求書の確認を行える入口を作る。
> **解約・プラン変更・契約人数の変更はPortalへ渡さない。**
> 渡すと自社DBの `contract_status` / `license_count` とStripeが食い違うため。

---

## 0. 着手前の実測（前提の確認）

| 項目 | 実測 |
|---|---|
| 開始branch | `feature/web-sales-5b-contract-gating` |
| local HEAD | `99cda823ebeb2a0625063ef4c8c486f1075c11e7` |
| origin同branch | 同一SHA（local == remote） |
| working tree | clean（変更0） |
| stash | 0件 |
| `master` | `d8c15584917bf589890b6922779fc5374ccbd41e`（origin/masterと同一） |
| 既定branch | `master`（`origin/HEAD -> origin/master`） |
| WEB-SALES-4R `128dbdf` | 包含 |
| WEB-SALES-5 `6acc7ac` | 包含 |
| WEB-SALES-5B `99cda82` | 包含 |
| `feature/web-sales-5c-customer-portal` | local・remoteとも**不在**（新規作成） |

想定と相違なし。

---

## 1. テスト件数の訂正（1275 → 1277）

着手前に全15本を再実行し、**`pass=1277 fail=0 skip=1`** を実測した。

| | 件数 |
|---|---|
| 既存14本（WEB-SALES-5時点） | 1186 |
| contract-gating（WEB-SALES-5Bで追加） | 91 |
| **合計** | **1277** |

従来の「1275」は、修正前の `pass=1275 / fail=2` と修正後の `fail=0` を
混ぜて報告したことによる集計誤り。訂正先は2か所。

| 文書 | 箇所 | 対応 |
|---|---|---|
| `docs/reviews/WEB_SALES_5B_CONTRACT_GATING.md` | 8章の集計表（既存14本1184／合計1275） | 1186／1277へ訂正し、訂正の経緯を注記 |
| `PROJECT_BIBLE/CURRENT_STATUS.md` | WEB-SALES-5Bの要点行・改訂履歴v8.1 | 1277へ訂正し、v8.2で訂正した旨を明記 |

**既存テストの削除・skipの追加・条件の除外・判定の緩和は0件**（billing-uiは85件のまま）。
この訂正のために製品コードは変更していない。

---

## 2. 本工程で提供するもの／提供しないもの

| Customer Portalで**許可** | Customer Portalで**禁止** |
|---|---|
| 登録済みカードの変更（`payment_method_update`） | 解約（`subscription_cancel`） |
| 請求書履歴・領収書の確認（`invoice_history`） | プラン変更（`subscription_update`） |
| `past_due` のときの支払い方法の確認 | 契約人数（quantity）の変更 |
| | 割引コード（promotion code） |
| | 返金・再契約 |

本工程で行わないこと: **本番公開・本番Stripe設定・Stripeへの実接続**。
テストモードの実機設定と疎通は後続の **WEB-SALES-5CS** で代表確認のうえ行う。

---

## 3. 採用したAPI

```
POST /api/billing/portal-session
```

引継ぎ資料の候補は `POST /api/contract/portal-session` だったが、
**既存の命名規則を優先して `/api/billing` を採用した。**

| 既存の切り分け | 内容 |
|---|---|
| `/api/contract/*` | 自社の契約状態の**参照**（GET・全利用者） |
| `/api/billing/*` | Stripeへの**手続き**（POST・会社管理者と運営者のみ） |

Portal Sessionの作成はStripeへの手続きであり、既存の `POST /api/billing/checkout` と
権限・防御（`requireBillingAdmin`・CSRF・Origin）が完全に同じため、
`/api/billing` 側へ置くのが既存構造と一致する。

### 応答

```json
{ "ok": true, "data": { "portalUrl": "<Stripeが発行する短寿命URL>" } }
```

> 実際のPortal URLは**文書・報告・ログのいずれにも記載しない**（短寿命だが
> 開けば当該会社の請求情報へ到達できるため）。上は形だけを示したもの。

- 返すのは**遷移に必要な短寿命URLだけ**（`data` のキーは `portalUrl` の1つのみ）
- Customer ID・Subscription ID・Portal設定ID・Price ID・秘密鍵は含めない
- `Cache-Control: no-store`

### エラー

| 状況 | 応答 |
|---|---|
| 未認証 | 401 `UNAUTHORIZED` |
| 一般利用者 | 403 `FORBIDDEN` |
| CSRFトークン不正・欠落 | 403 `CSRF_TOKEN_INVALID` |
| Origin不正・欠落 | 403 `ORIGIN_NOT_ALLOWED` |
| `payment_required` | 402 `PAYMENT_REQUIRED`（契約ガード） |
| `canceled` | 403 `CONTRACT_CANCELED`（契約ガード） |
| 知らない契約状態 | 403 `CONTRACT_INACTIVE`（契約ガード） |
| 契約状態がPortal対象外（サービス層の二重判定） | 409 `CONTRACT_NOT_PORTAL_ELIGIBLE` |
| Stripe Customer未設定 | 409 `BILLING_CUSTOMER_MISSING` |
| 定期課金が未設定・不整合 | 409 `BILLING_SUBSCRIPTION_MISSING` |
| Portalの設定が未整備 | 503 `BILLING_PORTAL_NOT_CONFIGURED` |
| Stripe拒否・一時障害・URL欠落 | 502 `BILLING_UNAVAILABLE` |
| GET | 404（ルート未定義） |

Stripeの生エラー文・request ID・ダッシュボードURL・Customer IDは
**利用者向け応答へ一切出さない**（分類コードと定型文のみ）。

> **429（レート制限）は本工程では返さない。**
> 既存のレート制限はログイン試行（`loginRateLimit`）と申込（`signupRateLimit`）の
> 2つだけで、どちらもこの経路に再利用できる形ではない。既存の
> `POST /api/billing/checkout` にも専用の制限は無い。新しい仕組みを
> 独断で作らず、既存規則どおり「認証必須＋会社管理者限定＋CSRF/Origin」で
> 到達面を絞る方針を踏襲した。→ 残存課題#1。

---

## 4. 対象となる契約状態と権限

| 契約状態 | Portal | 理由 |
|---|---|---|
| `active` | **可** | カード変更・請求書確認 |
| `past_due` | **可** | ここを閉じると支払い方法を直せず復帰できない |
| `payment_required` | 不可（Checkoutを使う） | まだ契約が始まっていない |
| `canceled` | 不可 | 過去請求書の閲覧を認めるかは別判断（残存課題#2） |
| 取得前・取得失敗・知らない値 | 不可 | active扱いにしない（WEB-SALES-5Bの方針を踏襲） |

判定は**サーバー側の1か所**（`billingService.allowsCustomerPortal`）で行い、
次の3層すべてが同じ関数を参照する。

1. `GET /api/contract/status` が返す `canOpenBillingPortal`（画面の出し分け用）
2. 契約ガードの許可リスト（`contractAccessPolicy`）
3. `startPortalSession` の入口（経路が開いていても状態で必ず止める）

| | 会社管理者・運営者 | 一般利用者 |
|---|---|---|
| Portalボタンの表示 | ○（active / past_due） | **×** |
| `POST /api/billing/portal-session` | ○ | **403** |

権限判定は既存の `canManageBilling`（`company_admin` / `operator`）をそのまま使い、
新しい意味を作っていない。運営者の権限は変更していない。

### 契約ガードの許可リストを広げた範囲

```js
[PAST_DUE]: [...ALWAYS_ALLOWED, ...PAYMENT_ENTRY, ...PORTAL_ENTRY]
```

**`past_due` にだけ**足した。`payment_required` と `canceled` には足していない。
判定はメソッドとパスの完全一致のままで、前方一致では広がらない。

---

## 5. Portal Configuration の固定（本工程の要）

**Configuration IDの明示を必須にした。**

Stripeアカウント既定の設定に任せると、Stripe管理画面で解約や数量変更を
有効にしただけで、こちらのコードを1行も変えずに利用者へ表示される。
**コードだけでは無効を保証できない**ため、引継ぎ資料の指示どおり
Configuration ID方式を採用した。

| 環境変数 | 用途 |
|---|---|
| `STRIPE_PORTAL_CONFIGURATION_ID` | 許可機能を固定するPortal設定ID（`bpc_`） |
| `STRIPE_PORTAL_RETURN_URL` | 戻り先（パスは `/settings` に固定） |

- `.env.example` には**項目名とコメントだけ**を追加。実値は書いていない
- 設定IDが未設定なら `isStripePortalConfigured()` が false になり、
  **画面のボタンは出ず、APIも503で受け付けない**（既定設定へ落ちない）
- 本工程ではStripeへ接続してConfigurationを作成・変更していない

### Stripeへ送る内容（実測）

```
customer      = 自社のstripe_customer_id（DBの値のみ）
configuration = STRIPE_PORTAL_CONFIGURATION_ID
return_url    = STRIPE_PORTAL_RETURN_URL
```

**送るのはこの3つだけ。** `subscription_cancel` / `subscription_update` /
`quantity` / `price` / `promotion` / `coupon` / `flow_data` は一切送らない
（テストでキー集合の完全一致を検証している）。
秘密鍵は `Authorization` ヘッダーのみで渡し、本文・URL・応答・ログへ出さない。
Portal SessionはCheckoutと違い `Idempotency-Key` を付けない
（短命なため使い回すと期限切れURLを返す。二重作成の抑止は画面側で行う）。

### return_url の検証

外部サイトへ利用者を送り出す経路を作らないため、環境変数の値を検証する。

| 値 | 判定 |
|---|---|
| `https://lite.example.com/settings` | 可 |
| `http://localhost:5173/settings` | 可（ローカル開発のみ） |
| `https://lite.example.com/` | 不可（パスが違う） |
| `https://lite.example.com/settings-evil` | 不可（前方一致で広げない） |
| `https://lite.example.com/settings?next=…` | 不可（問い合わせ文字列） |
| `https://user:pw@lite.example.com/settings` | 不可 |
| `http://evil.example.com/settings` | 不可（外部の平文http） |
| `javascript:alert(1)` / `/settings` / 空 | 不可 |

不正な値なら `isStripePortalConfigured()` が false になり、Portalは提供されない。

---

## 6. 秘密情報を返さない・出さない

| 対象 | 応答 | 画面 | console | ログ |
|---|---|---|---|---|
| Portal URL | 返す（遷移に必要） | 出さない | 出さない | 出さない |
| Stripe Customer ID | 返さない | 扱わない | 出さない | 出さない |
| Subscription ID | 返さない | 扱わない | 出さない | 出さない |
| Portal Configuration ID | 返さない | 扱わない | 出さない | 出さない |
| 秘密鍵・Webhookシークレット | 返さない | 扱わない | 出さない | 出さない |
| Price ID | 返さない | 扱わない | 出さない | 出さない |

ブラウザからは Customer ID・Configuration ID・return_url を**受け取らない**。
会社IDはセッションからのみ取得し、本文・クエリの会社IDは読まない。
サーバーログに残すのは「作成した」という事実だけ（`customer portal session created`）。

---

## 7. 画面（`/settings` の「契約・ライセンス」）

`active` / `past_due` の会社管理者・運営者にだけ表示する。

```
[支払い方法・請求書を確認する]

Stripeの安全な画面で、登録済みカードや請求書をご確認いただけます。
ご契約の解約・ご契約人数の変更は、株式会社スマートラボへお問い合わせください。
```

- 表示条件は**サーバーの `canOpenBillingPortal` だけ**。画面側で契約状態から
  条件を組み立て直していない
- 契約状態が `ready` になるまで表示しない（取得前に一瞬出ることがない）
- **画面表示ではSession を作らない**（クリック時のみ・自動リダイレクトなし）
- 処理中はボタンを無効化し、`useRef` の番人で連打を弾く
- **成功時はボタンを戻さない**（そのまま遷移する）。**失敗したときだけ**再操作可能
- ポップアップではなく `window.location.assign` による通常遷移
- 戻り先は `/settings`。戻ると契約セクションが表示される
- 既存のデザイン（`btn btn-secondary` / `billing-actions` / `settings-note`）を再利用。
  新しいデザイン体系・外部ライブラリの追加は0
- 解約ボタン・契約人数変更ボタンは作っていない
- 「Company OS」は表示していない

### 実機確認（ブラウザ・隔離環境／実Stripe未接続）

隔離DB・架空データ・外部通信を塞いだ状態で確認した。

| 確認 | 結果 |
|---|---|
| `active` の会社管理者 | ボタン表示 |
| `past_due` の会社管理者 | ボタン表示（お支払い導線と併記） |
| `payment_required` の会社管理者 | **Checkoutのみ**・Portalボタンなし |
| `active` の一般利用者 | **ボタンなし**（契約セクション自体は閲覧可） |
| 画面表示だけ | Session作成 **0件** |
| 3連打 | サーバーへの要求 **1件のみ** |
| 失敗時 | 見出し＋理由を表示し、ボタンが再び押せる状態へ戻る |
| PC（1280px） | ボタン高さ44px・横スクロールなし |
| Mobile（375px） | ボタン幅309px・高さ44px・横スクロールなし |
| console | URL・Customer ID・秘密値の出力 **0件** |
| 契約ガード（`past_due`） | メニューは `/` と `/settings` のみ・業務APIは402のまま |

### 併せて是正した表示の不具合（1件）

決済導線の失敗メッセージが `StatusMessage` の**子要素**として渡されており、
`StatusMessage` は子要素を描画しないため**見出しだけが出て理由が消えていた**
（WEB-SALES-5から存在）。Portalの失敗表示も同じ枠を使うため、
`description` へ渡す形へ直した（1か所）。実機で理由の表示を確認済み。

---

## 8. `past_due` からの復帰

**本工程で作るのは「支払い方法を変更できる入口」までである。**

| 出来事 | 契約状態 |
|---|---|
| Portal Sessionの作成に成功 | **変えない** |
| Portalでカードを変更 | **変えない** |
| `/settings` へ戻ってきた | **変えない** |
| 署名検証済み `invoice.paid` を受信 | `past_due` → `active` |

`startPortalSession` は `activateContract` / `moveContractStatus` /
`updateSubscriptionInfo` のいずれも呼ばない（テストで静的に検証）。

### 既存Webhookの調査結果

既存実装（WEB-SALES-4）を確認したところ、**復帰は既に安全に実装されていた**。

```
invoice.paid → companyRepo.activateContract(...)
  WHERE contract_status IN ('payment_required', 'past_due')
  contract_started_at = COALESCE(contract_started_at, ?)  ← 初回のまま
```

- `past_due` → `active` は `invoice.paid`（および `checkout.session.completed`）でのみ発生
- `customer.subscription.updated` の `status=active` では有効化しない
  （コメントどおり「有効化の根拠は支払いの成功だけ」）
- 契約開始日は `COALESCE` で守られ上書きされない
- 同じイベントの再送は台帳の UNIQUE で1回だけ処理される

実測（隔離環境・架空イベント）:

```
past_due → invoice.paid 適用 → active（handled=true）
契約開始日 2026-06-01 のまま（上書きなし）
同じイベントを再送 → status=duplicate（何も起きない）
```

**不足は見つからなかったため、Webhookの状態遷移は変更していない。**

---

## 9. テスト

`tests/acceptance/customer-portal.mjs`（**161件**・新規）

| 区分 | 件数 | 主な確認 |
|---|---|---|
| A 認証と権限 | 8 | 未認証401・一般利用者403・管理者/運営者は成功・拒否時はStripeを呼ばない |
| B 会社の取り違え | 7 | 本文/クエリの会社ID・Customer・Configuration・return_urlを無視 |
| C CSRF・メソッド | 5 | CSRF欠落/不正・Origin欠落・GET拒否 |
| D 契約状態による可否 | 19 | 402/403/409・Customer自動作成なし・定期課金の自動修復なし |
| E 契約ガードの許可リスト | 6 | past_dueのみ許可・完全一致・既存Checkout導線の維持 |
| F Stripeへの送信内容 | 16 | 固定Configuration・固定return_url・解約/数量/Priceを送らない・鍵はヘッダーのみ |
| G 応答の内容 | 16 | portalUrlのみ・識別子と秘密値0・no-store・生エラーとrequest IDを返さない |
| H 設定不足時の扱い | 13 | 設定IDなしで利用不可・return_urlの検証11通り |
| I 契約状態APIの出し分け | 10 | 状態×権限の`canOpenBillingPortal` |
| J 画面の実装 | 29 | 表示条件・自動実行なし・連打防止・console出力0・解約/人数変更ボタンなし |
| K past_dueからの復帰 | 7 | Portalでは状態を変えない・invoice.paidでのみ復帰・冪等 |
| L 回帰 | 19 | 4R/5/5Bの設定とガード・seat制御の維持 |
| M 横断 | 6 | 実外部通信0件・.env.exampleに実値なし |

### 全体（全16本を再実行）

| | 件数 |
|---|---|
| 既存15本 | 1277 |
| customer-portal（新規） | 161 |
| **合計** | **1438件 成功・失敗0・skip1** |

内訳の実測:

```
acceptance 279 / admin-dashboard 24 / billing-ui 85 / business-card 71 /
business-card-disabled 5 / company-brain-basic 32 / company-manual-search 167(skip1) /
company-manual-search-disabled 10 / contract-gating 91 / customer-portal 161 /
global-search 101 / production-mail 83 / ratelimit 7 / security-foundation 71 /
signup-provisioning 124 / stripe-contracts 127
TOTAL pass=1438 fail=0 skip=1
```

**既存テストの削除・skipの追加・条件の除外・判定の緩和は0件。**
billing-uiは85件のまま、contract-gatingは91件のまま。

`npm run build` 成功（1707 modules・エラー0）。

### 外部通信

| 対象 | 件数 |
|---|---|
| 実Stripe通信 | **0** |
| Portal Sessionの実作成 | **0** |
| 実決済 | **0** |
| 実Webhook | **0** |
| 実メール送信 | **0** |
| 本番DB接続 | **0** |

自動テストは `net` / `tls` / `dns` / `http(s)` / `fetch` を監視し、
ループバック以外を遮断したうえで `realNetCount() === 0` を検証している。
ブラウザ確認も外部接続を遮断した隔離環境で行った（`.env` は読み込んでいない）。

---

## 10. 本番変更

なし。

| 対象 | 状態 |
|---|---|
| Lite `master` | `d8c1558` 無変更 |
| SmartLabo `master` | 無変更 |
| Stripe（テスト・本番とも） | 接続なし・設定変更なし |
| 本番Webhookエンドポイント | 未登録のまま（変更なし） |
| XServer / DNS / Website / 本番DB | 接続・変更なし |

---

## 11. 後続 WEB-SALES-5CS の手順（案）

1. 代表がStripeテストモードでPortal Configurationを作成する
   - `payment_method_update` 有効／`invoice_history` 有効
   - `subscription_cancel` **無効**／`subscription_update` **無効**（数量変更を含む）
   - promotion code は追加しない
2. 作成された `bpc_…` を**本番と検証それぞれの `.env`** へ設定（Gitへは入れない）
3. `STRIPE_PORTAL_RETURN_URL=https://lite.smartlaboworks.com/settings` を設定
4. テスト会社（`active`）の管理者でボタンを押し、Portalが開くことを確認
5. Portal画面に**解約・プラン変更・数量変更の項目が無い**ことを実機で確認（最重要）
6. テストカードを別のテストカードへ変更し、変更が反映されることを確認
7. 請求書履歴・領収書が表示されることを確認
8. `/settings` へ戻り、契約セクションが表示されることを確認
9. `past_due` の会社でも同じ経路が使えることを確認
10. 一般利用者にボタンが出ないこと・APIが403になることを確認
11. Stripeへ作成されたテストデータの後片付け

---

## 12. 残存課題

| # | 内容 |
|---|---|
| 1 | **Portal Sessionのレート制限が無い**（既存Checkoutも同様）。認証・権限・CSRFで到達面は絞られているが、専用の制限は未実装。導入するなら既存の`loginRateLimit`と同じDB方式を横展開する設計判断が必要 |
| 2 | `canceled` の会社が過去の請求書を閲覧できない（提供可否は別判断） |
| 3 | **契約人数の変更は未実装**（Stripeのquantity変更と日割りの扱いを含む） |
| 4 | **解約・再契約の操作は未実装** |
| 5 | 返金・割引コードは未対応 |
| 6 | 運営者向けの契約・決済状況の一覧は未実装 |
| 7 | 本番Webhookエンドポイントが未登録 |
| 8 | 法務3点（特商法表記・利用規約の契約条項・キャンペーン規約）が未整備 |
| 9 | Portal Configurationの内容がStripe側の設定に依存するため、**設定変更の検知手段が無い**（誰かが解約を有効化しても製品側は気付けない）。5CSでの実機確認と、定期的な目視確認が必要 |

---

## 13. Go / No-Go

| 対象 | 判定 |
|---|---|
| **WEB-SALES-5CS（Stripeテストモード実機確認）** | **Go** |
| 本番公開 | **No-Go**（法務3点未整備・本番Webhook未登録・解約/人数変更が未実装） |
| 契約人数変更・解約・再契約 | **未着手**（本工程の範囲外） |

---

*作成: Claude Code / WEB-SALES-5C（2026-08-10）*
*実Stripe未接続・実決済0・実Webhook0・実メール0・本番変更0。*
