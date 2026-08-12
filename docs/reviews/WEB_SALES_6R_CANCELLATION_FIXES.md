# WEB-SALES-6R — 解約実機確認で検出した3差異の最小修正

- 実施日: 2026-08-10
- 対象リポジトリ: `smartlabo-works-lite`（正式製品）
- ブランチ: `feature/web-sales-6r-cancellation-fixes`
- 分岐元: `feature/web-sales-6-cancellation` `f63fc8c8f1cda0daa169f015f92b555467fc7f63`
- commit: `388ae670502e508e17096d2675d96a1e58f6d6e7`
- 種別: 販売基盤の是正（**本番変更0・実Stripe未接続・実決済0・実Webhook0・実メール0**）

> WEB-SALES-6S（Stripeテストモードでの解約実機確認）で見つかった3つの差異を、
> 製品コード・テスト・文書の**最小修正**で是正する。
> 新機能は追加しない。再契約・契約人数変更・即時解約・返金は対象外のまま。

---

## 0. 着手前の実測

| 項目 | 実測 |
|---|---|
| 開始branch | `feature/web-sales-6-cancellation` |
| local HEAD | `f63fc8c8f1cda0daa169f015f92b555467fc7f63`（origin同一） |
| working tree | clean／stash 0件 |
| `master` | `d8c15584917bf589890b6922779fc5374ccbd41e`（origin/masterと同一） |
| `15335db`・`f63fc8c` の包含 | 両方とも包含 |
| `feature/web-sales-6r-cancellation-fixes` | local・remoteとも不在（新規作成） |
| テスト基準 | **1645件成功・失敗0・skip1**（全17本・想定どおり） |
| `npm run build` | 成功 |

---

## 1. 検出した3差異と代表決定

| # | 実機で見つかった事象 | 代表決定 |
|---|---|---|
| 1 | `past_due`・`canceled` に「お支払いのお手続きへ進む」が表示され、押すと必ず失敗する | Checkoutは **`payment_required` だけ**。`past_due` は Customer Portal だけ、`canceled` は契約終了のご案内だけにする |
| 2 | 解約確定後も解約予約の日時が残り、「ご契約が終了しています」と「◯月◯日に解約予定です／その日までご利用いただけます」が同時に出る | 解約予約は `customer.subscription.deleted` で**消化済み**とし、`cancellation_scheduled_at` を NULL へ戻す |
| 3 | Stripe API `2026-06-24.dahlia` で一部フィールドの置き場所が変わっていた | 旧形式と現行形式の**両方を安全に読む helper へ集約**する。旧形式は壊さない |

差異1・2は**利用者に見えていた不具合**であり、差異3は将来のイベントを取りこぼす原因になる。
いずれも解約という後戻りしにくい業務に関わるため、本工程で是正した。

---

## 2. 差異1の修正 — 契約状態ごとの決済導線

### 正式な出し分け

| 契約状態 | Checkout | Customer Portal | 解約予約・予約取消 | 画面の案内 |
|---|---|---|---|---|
| `payment_required` | **あり** | なし | なし | お支払いのお手続きが必要です |
| `past_due` | **なし** | **あり** | なし | お支払いを確認できませんでした |
| `canceled` | **なし** | **なし** | なし | ご契約が終了しています（再契約ボタンなし） |
| `active` | なし | あり | 状態に応じて表示 | 通常利用 |
| 一般利用者（全状態） | なし | なし | なし | 管理者へのご案内のみ |

### 判定を3層で一致させた

| 層 | 変更 |
|---|---|
| 契約状況API `GET /api/contract/status` | 「お支払いのご案内が要る状態（`nextAction`）」と「Checkoutを開始できる状態（`canStartCheckout`）」を**分離**。以前は前者をそのまま後者に使っていた |
| 契約ガード（許可リスト） | `PAYMENT_ENTRY` を `past_due`・`canceled` から外した。`past_due` の `PORTAL_ENTRY` は維持 |
| サービス層 | `allowsCheckoutStart()` を新設し、契約状況APIと `startCheckout()` の両方が同じ判定を使う |

`nextAction` は3状態とも従来どおり返す。**案内は出し続け、押せない導線だけを消す**。

### 実測（拒否時はStripeを1回も呼ばない）

| 操作 | 結果 |
|---|---|
| `past_due` の `POST /api/billing/checkout` | **402 `PAYMENT_PAST_DUE`** |
| `canceled` の `POST /api/billing/checkout` | **403 `CONTRACT_CANCELED`** |
| `canceled` の `POST /api/billing/portal-session` | **403 `CONTRACT_CANCELED`** |
| `past_due` の `POST /api/billing/portal-session` | **200**（維持） |
| `payment_required` の `POST /api/billing/checkout` | **200**（維持。カード限定・Managed Payments無効・請求アンカー・按分・追加席数量すべて不変） |
| 拒否された操作でのStripe呼び出し | **0件** |

---

## 3. 差異2の修正 — 解約確定後の予約情報の消化

### 変更内容

署名検証済みの `customer.subscription.deleted` で `canceled` へ**移せたときだけ**、
同じ会社の `cancellation_scheduled_at` を NULL へ戻す。

### 解約確定後のDBの状態

| 列 | 扱い |
|---|---|
| `contract_status` | `canceled` |
| `cancellation_scheduled_at` | **NULL**（予約は実行済みなので予定としては消す） |
| `canceled_at` | **初回のみ**設定（`COALESCE` で後続イベントが上書きしない） |
| `contract_started_at` | **不変** |
| `current_period_end` | **保持**（過去の期間情報・監査のため） |
| `stripe_customer_id` / `stripe_subscription_id` | 保持 |
| 会社データ・利用者 | **削除しない** |

### 二重更新をしない根拠

- 同じ `stripe_event_id` の再送 → イベント台帳の `claimEvent` が `duplicate` にする
- 別IDの同種イベント → すでに `canceled` のため `moveContractStatus` が `expectedFrom` に合わず何もしない。
  このとき**予約情報にも触れない**ので、他の経路が入れた記録を壊さない

### 画面側の多層防御

`contract_status` が `active` のときだけ解約ブロックを描画する。
`canceled` では、解約予定の文言・予約取消ボタン・「その日までご利用いただけます」を出さない。

万一DBに予約の残骸があっても矛盾表示にならないことを、隔離環境で
`canceled` ＋ 予約日時ありの状態を意図的に作って確認した（表示は「契約終了」のみ）。

`customer.subscription.updated` だけでは `canceled` にしない既存方針は維持している。

---

## 4. 差異3の修正 — Stripe API 新旧フィールドの互換

読み取りの規則を新設の互換helperへ集約した。規則を各所に散らすと片方だけ直して取りこぼす。

### 新旧の対応表

| 値 | 旧形式 | 現行形式（`2026-06-24.dahlia`） | 本工程の扱い |
|---|---|---|---|
| Subscription の期間終了 | `subscription.current_period_end` | `subscription.items.data[*].current_period_end` | 旧 → 現行の順に読む |
| Invoice の Subscription | `invoice.subscription` | `invoice.parent.subscription_details.subscription` | 旧 → 自身 → 現行の順に読む |
| Checkout Session の Subscription | `session.subscription`（文字列） | `session.subscription`（文字列／オブジェクト） | 両方に対応（変更なし） |
| Customer | `object.customer`（文字列／オブジェクト） | 同左 | 両方に対応（変更なし） |

### 期間終了日時の優先順位

1. `subscription.cancel_at` … **解約予約の有効日として最優先**（期間終了日と別日になりうる）
2. `subscription.current_period_end` … 旧API互換
3. `subscription.items.data[*].current_period_end` … 現行API互換

正式商品は基本料金・追加席とも月次・同一の請求アンカーのため、3の値は通常すべて同じになる。

- すべて同じ → その値を採用
- **食い違う → 最小・最大などを独断で正本にせず、`null`（安全側）に倒す**
- 欠落・空配列・不正値・範囲外の値 → 例外を投げず `null`

`null` のときは呼び出し側が既存値を保つ（`COALESCE`）。
解約予約中に日時を決められないイベントが来ても、**既存の予約を消さない**。
理由は識別子・個人情報を含まない内部コード（`PERIOD_END_MISSING` / `PERIOD_END_AMBIGUOUS`）で表す。

### Checkout Session の Subscription 取得

- `session.subscription` が文字列 → その値をDBへ記録
- `session.subscription` がオブジェクト → `id` を取り出してDBへ記録
- **欠落時は `metadata` から推測しない**。支払いの成功は反映するが Subscription ID は記録しない

Stripe Customer ID・Subscription ID を顧客向けAPIへ返さない方針は維持している。

---

## 5. Webhookの状態遷移（変更後）

| イベント | 契約状態 | 解約予約 |
|---|---|---|
| `checkout.session.completed`（支払い済） | → `active` | 変更なし |
| `invoice.paid` | → `active` | 変更なし |
| `invoice.payment_failed` | `active` → `past_due` | 変更なし |
| `customer.subscription.updated`（`cancel_at_period_end=true`） | **変更しない**（`active` のまま） | 予定日時を記録。決められないときは既存を保持 |
| `customer.subscription.updated`（`cancel_at_period_end=false`） | 変更しない | **NULL へ戻す** |
| `customer.subscription.updated`（`status=canceled`） | → `canceled` | 変更なし（既存動作） |
| `customer.subscription.deleted` | → `canceled` | **NULL へ戻す（本工程で追加）** |

`metadata` が他社を指すイベントは `COMPANY_MISMATCH` として処理しない（既存の安全策を維持）。

---

## 6. 変更ファイル

| ファイル | 内容 |
|---|---|
| `server/services/billing/stripeObject.js` | **新規**。旧／現行フィールドの互換helper |
| `server/services/billing/billingService.js` | `allowsCheckoutStart()` 新設・解約確定で予約を消化・互換helperへ集約 |
| `server/routes/contract.js` | `canStartCheckout` を `nextAction` から分離 |
| `server/services/contractAccessPolicy.js` | `past_due`・`canceled` から Checkout 経路を外す |
| `server/app.js` | 経路の説明コメントを実装に合わせた（1行） |
| `src/pages/SettingsPage.jsx` | 解約ブロックは `active` のときだけ描画 |
| `tests/acceptance/cancellation-fixes.mjs` | **新規**（146件） |
| `tests/acceptance/billing-ui.mjs` | 期待値の是正（後述） |
| `tests/acceptance/stripe-contracts.mjs` | 期待値の是正（後述） |

---

## 7. テスト

### 新規 `tests/acceptance/cancellation-fixes.mjs`（146件）

| 区分 | 件数 | 主な確認 |
|---|---|---|
| A 決済導線 | 48 | 状態別の `canStartCheckout` / `canOpenBillingPortal` / 解約可否・許可リスト・サービス層・API拒否・拒否時Stripe0件・4Rの回帰 |
| B 解約確定 | 25 | `canceled` 化・予約NULL・開始日不変・期間終了日時の保持・再送duplicate・別IDで二重更新なし・画面文言の位置 |
| C Subscription互換 | 28 | 旧／現行／複数明細一致／食い違い／`cancel_at` 最優先／不正値12種／Webhook経由の記録・既存値保護 |
| D Invoice互換 | 22 | 旧・現行×文字列・オブジェクト／両方ある場合の優先順位／欠落／metadata推測なし／`invoice.customer` の維持 |
| E Checkout Session | 12 | 文字列・オブジェクト・欠落・未払い・顧客向けAPIへID非露出 |
| F 横断 | 11 | 実外部通信0件・helperがログを書かない・即時解約／返金APIの不在・許可リストの形 |

### 既存テストの更新（削除・skip化・条件緩和は0件）

| ファイル | 更新 | 理由 |
|---|---|---|
| `billing-ui.mjs` | `past_due`・`canceled` の `canStartCheckout` 期待値を `true` → `false` | **旧仕様が誤っていたことの正式な是正**。実機で押しても必ず失敗する導線を「出るのが正しい」と固定していた。`nextAction` の期待値は変えていない |
| `stripe-contracts.mjs` | 「canceled でも再契約の導線は残す（200/409を許容）」→ 403 `CONTRACT_CANCELED` を厳密に確認 | 再契約は本工程の対象外であり、通しても失敗する。曖昧な許容をやめて1つの正解に固定した |
| `stripe-contracts.mjs` | 「past_due でも支払い更新の導線は残す（200/409を許容）」→ 402 `PAYMENT_PAST_DUE` を厳密に確認 | 支払い方法の見直しは Customer Portal で行う。Checkoutを通すと定期課金が二重になる |

いずれも**意味を保った1対1の更新**であり、確認項目の数は増減していない。

### ファイル別件数（全18本）

| ファイル | 件数 |
|---|---|
| acceptance | 279 |
| admin-dashboard | 24 |
| billing-ui | 85 |
| business-card | 71 |
| business-card-disabled | 5 |
| **cancellation-fixes（新規）** | **146** |
| company-brain-basic | 32 |
| company-manual-search | 167（skip1） |
| company-manual-search-disabled | 10 |
| contract-cancellation | 206 |
| contract-gating | 91 |
| customer-portal | 162 |
| global-search | 101 |
| production-mail | 83 |
| ratelimit | 7 |
| security-foundation | 71 |
| signup-provisioning | 124 |
| stripe-contracts | 127 |
| **合計** | **1791件 成功・失敗0・skip1** |

開始基準 1645 ＋ 新規 146 ＋ 既存更新による増減 0 ＝ **1791**。

`npm run build` 成功（エラー0。500kB超のチャンク警告は本変更前からの既存事象）。

### 外部通信

| 対象 | 件数 |
|---|---|
| 実Stripe通信 | **0** |
| 実決済・実Webhook・実メール | **0** |
| 本番DB接続 | **0** |

テストは `net`/`tls`/`dns`/`http(s)`/`fetch` を監視してループバック以外を遮断し、
`realNetCount() === 0` を検証している。

---

## 8. ブラウザ確認（隔離環境・完全架空データ）

隔離DB・ループバックのStripeスタブ・完全架空の会社／利用者で確認した。本番へは接続していない。

| 状態 | PC 1280px | Mobile 375px |
|---|---|---|
| `payment_required` | Checkoutのみ | Checkoutのみ |
| `past_due` | **Portalのみ・Checkoutなし** | **Portalのみ・Checkoutなし** |
| `canceled` | 契約終了のご案内のみ。ボタン0個 | 契約終了のご案内のみ。ボタン0個 |
| `active`・予約なし | Portal＋「契約を解約する」 | 同左 |
| `active`・予約あり | 解約予定日・予約取消・通常利用可 | 同左 |
| 一般利用者 | 課金操作なし（APIも403） | 課金操作なし |

| 確認項目 | 結果 |
|---|---|
| 横スクロール | なし（1280px・375pxとも `scrollWidth == clientWidth`） |
| ボタン高さ | すべて **44px** |
| 状態の表し方 | バッジに文言あり（「お支払い待ち」「お支払いの確認が必要」「契約終了」「利用中」）。色だけに頼らない |
| console error | 0（`vite` の接続ログとReact DevTools案内のみ。401/403は確認用に意図的に送った要求の記録） |
| 秘密値・Stripe ID・メールアドレス一覧の露出 | 0 |

`canceled` ＋ 解約予約の残骸という論理的にありえない状態を意図的に作っても、
画面は「契約終了」だけを出し、解約予定の文言・取消ボタンは出なかった（多層防御の確認）。

---

## 9. 本番への影響

| 対象 | 状態 |
|---|---|
| 本番サーバー・本番DB | **変更0・接続0** |
| 本番Webhook登録 | **未実施**（対象外） |
| Stripe（テスト・本番とも） | **接続なし・設定変更なし** |
| `master` / `main` / `develop` | **無変更**（`master` は `d8c1558` のまま） |
| 公開 | **なし** |
| DBスキーマ | **変更なし**（migration追加0。既存の `cancellation_scheduled_at` を使う） |

---

## 10. 残存課題

| # | 内容 |
|---|---|
| 1 | 本修正は**Stripeテストモードでの実機再確認（WEB-SALES-6RS）が必要**。差異1・2はテストモードの実イベントで初めて見つかったため、単体・受入テストだけでは十分と見なさない |
| 2 | `past_due` からの復帰導線が Customer Portal のみになった。Portal設定で支払い方法の更新が有効であることを実機で確認する必要がある |
| 3 | 再契約（`canceled` からの復活）は未実装。現在は問い合わせ案内のみ |
| 4 | 契約人数の変更は未実装（問い合わせ案内のみ） |
| 5 | Stripe APIの版が上がった際に他のフィールドが移動する可能性がある。互換helperに集約したため、変更点は1ファイルで吸収できる |
| 6 | 本番Webhookは未登録。登録は後続工程 |

---

## 11. 次工程の判定

| 工程 | 判定 |
|---|---|
| **WEB-SALES-6RS（Stripeテストモードでの解約実機再確認）** | **Go** |
| masterへのmerge | **No-Go**（6RSの実機再確認が先） |
| 本番公開・本番Webhook登録 | **No-Go** |
| 再契約・契約人数変更・即時解約・返金 | **No-Go**（対象外） |
