# WEB-SALES-6 — 契約の解約予約・予約取消・課金操作のレート制限

- 実施日: 2026-08-10
- 対象リポジトリ: `smartlabo-works-lite`（正式製品）
- ブランチ: `feature/web-sales-6-cancellation`
- 分岐元: `feature/web-sales-5c-customer-portal` `15335dbfc825994380b19dbd745492438d9c19fd`
- commit: `f63fc8c8f1cda0daa169f015f92b555467fc7f63`
- 種別: 販売基盤（**本番変更0・実Stripe未接続・実決済0・実Webhook0・実メール0**）

> Web完結販売に必要な「顧客自身による安全な解約」を実装する。
> 併せて、Stripeを呼ぶ4経路にサーバー側の回数制限を入れる。

---

## 0. 着手前の実測

| 項目 | 実測 |
|---|---|
| 開始branch | `feature/web-sales-5c-customer-portal` |
| local HEAD | `15335dbfc825994380b19dbd745492438d9c19fd`（origin同一） |
| working tree | clean／stash 0件 |
| `master` | `d8c15584917bf589890b6922779fc5374ccbd41e`（origin/masterと同一・既定branch） |
| 4R/5/5B/5C の4commit | すべて包含 |
| `feature/web-sales-6-cancellation` | local・remoteとも不在（新規作成） |
| テスト基準 | **1438件成功・失敗0・skip1**（想定どおり） |
| `npm run build` | 成功 |

---

## 1. 採用した解約方式

| 項目 | 採用 |
|---|---|
| 解約のタイミング | **現在の請求期間の終了時** |
| 予約後の利用 | 期間終了までは**通常どおり利用できる**（`contract_status` は `active` のまま） |
| 返金 | **行わない**（日割り返金・自動返金とも。返金APIを呼ばない） |
| 解約予定日 | **Stripeが返した値が正本**。自前で日付を計算しない |
| 予約の取消 | 期間終了前ならいつでも可能 |
| Customer Portal | **解約機能を追加しない**（Portal設定は5Cのまま） |
| `canceled` への移行 | **署名検証済みWebhookのみ** |
| 契約開始日 | 変更・上書きしない |
| 会社・利用者・業務データ | 自動削除しない |
| 再契約 | **本工程の対象外** |

---

## 2. DB migration（1列だけ追加）

`server/db/migrations/021_add_cancellation_schedule.sql`

```sql
ALTER TABLE companies ADD COLUMN cancellation_scheduled_at TEXT;
```

既存列で表現できるものは再利用した。

| 必要な情報 | 対応 |
|---|---|
| 現在の請求期間の終了日時 | **既存 `current_period_end` を再利用**（020で追加済み） |
| 解約が確定した日時 | **既存 `canceled_at` を再利用**（020で追加済み） |
| 契約の段階 | **既存 `contract_status` を再利用**。解約予約中も `active` |
| 解約予定日時 | **新規 `cancellation_scheduled_at`**（Stripeの `cancel_at` の写し） |
| 解約予約の有無 | **列を作らない**。`cancellation_scheduled_at IS NULL` で表す |
| Stripe側のsubscription status | **保存しない**（020の方針を維持） |

### `contract_end_at` を再利用しなかった理由

003で追加済みの `contract_end_at` は**運営者による会社の利用停止予定日**で、
`companies.status`（`active` / `suspend_scheduled` / `suspended`）と対で
`server/services/companyService.js` が管理している。同ファイルは
`status='active'` のとき `contract_end_at` を空へ戻す。

顧客自身の解約予約をこの列へ入れると、**運営者が会社情報を保存しただけで
顧客の解約予約が消える**。意味の違う2つの予定日を同じ列へ入れない。

### 予約の有無を真偽値で持たない理由

日付と真偽値の2か所に同じ意味を置くと、必ず食い違う。
Stripeも `cancel_at_period_end=false` のとき `cancel_at` は null であり、
1つの列で矛盾なく表せる。

---

## 3. 採用したAPI

```
POST   /api/billing/cancellation-schedule   解約を予約する
DELETE /api/billing/cancellation-schedule   解約予約を取り消す
```

既存の切り分け（`/api/contract`＝参照GET・全利用者／`/api/billing`＝Stripeへの
手続きPOST・会社管理者限定）に従い `/api/billing` へ置いた。
予約と取消は**同じ資源の作成と削除**なので、`.../cancel` と `.../uncancel` の
ような動詞違いのパスを2本作らず、1つのパスをメソッドで分けた。

### Stripeへ実際に送るキー集合

```
cancel_at_period_end = true     （解約予約）
cancel_at_period_end = false    （予約の取消）
```

**この1つだけ。** 受入テストでキー集合の完全一致を検証している。

| 送らないもの | 理由 |
|---|---|
| 即時解約（`DELETE /v1/subscriptions/{id}`） | 期間途中で使えなくなる |
| `/v1/refunds` | 返金は行わない方針 |
| `price` / `quantity` | 契約人数・請求額が意図せず変わる |
| `proration_behavior` / `prorate` / `invoice_now` | 按分が変わる |
| `cancel_at`（日付の指定） | 期間の終わりはStripeが決める。自前計算しない |

### エラー

| 状況 | 応答 |
|---|---|
| 未認証 | 401 `UNAUTHORIZED` |
| 一般利用者 | 403 `FORBIDDEN` |
| CSRF欠落・不正 | 403 `CSRF_TOKEN_INVALID` |
| Origin欠落・不正 | 403 `ORIGIN_NOT_ALLOWED` |
| `past_due` / `payment_required` | 402（契約ガード） |
| `canceled` / 知らない状態 | 403（契約ガード） |
| 契約状態がactiveでない（サービス層の二重判定） | 409 `CONTRACT_NOT_CANCELABLE` |
| すでに予約済み | 409 `CANCELLATION_ALREADY_SCHEDULED` |
| 予約されていない（取消時） | 409 `CANCELLATION_NOT_SCHEDULED` |
| 予定日時を過ぎている（取消時） | 409 `CANCELLATION_PERIOD_ENDED` |
| 定期課金が無い・不整合 | 409 `BILLING_SUBSCRIPTION_MISSING` |
| Stripe未設定 | 503 `BILLING_NOT_CONFIGURED` |
| Stripe拒否・反映されない応答 | 502 `BILLING_UNAVAILABLE` |
| 回数制限超過 | 429 `TOO_MANY_REQUESTS`＋`Retry-After` |
| GET | 404 |

Stripeの生エラー文・request ID・Subscription IDは応答へ一切出さない。

---

## 4. 契約状態の遷移（正本はWebhook）

```
active ──[POST cancellation-schedule]──▶ active（解約予約あり）
                                          │
       ◀──[DELETE cancellation-schedule]──┘

active（解約予約あり） ──[customer.subscription.deleted]──▶ canceled
```

**APIの成功だけでは canceled にしない。**
予約中も `contract_status` は `active` のままで、業務機能はそのまま使える。

### Webhookイベント別の処理

| イベント | 処理 |
|---|---|
| `customer.subscription.updated` | `cancel_at_period_end` があれば解約予約を記録／解除。`current_period_end` も記録。**契約状態は変えない**。`status=active` を有効化の根拠にしない（`NO_PAYMENT_EVIDENCE`）。`status=past_due`/`unpaid` は past_due へ、`status=canceled` は canceled へ（従来どおり） |
| `customer.subscription.deleted` | **ここで初めて `canceled`**。`canceled_at` を初回だけ記録。`contract_started_at` は保持。業務機能が閉じる |
| `invoice.paid` | 従来どおり `payment_required`/`past_due` → `active`（回帰確認済み） |
| `invoice.payment_failed` | 従来どおり `active` → `past_due`（回帰確認済み） |
| `checkout.session.completed` | 従来どおり（変更なし） |

### 是正1件：`canceled_at` を初回のみに

```
修正前: canceled_at = COALESCE(?, canceled_at)   … 常に上書き
修正後: canceled_at = COALESCE(canceled_at, ?)   … 初回だけ記録
```

従来も `expectedFrom` の状態ガードで実質1回しか入らなかったが、
「初回のみ」を列の更新式そのもので保証する形へ直した。
`past_due` への遷移では `canceledAt` を渡さないため影響しない。

### 維持したもの

- raw body での署名検証（`express.raw`・CSRFの例外一覧を作らない構造）
- `stripe_events.stripe_event_id` のUNIQUEによる冪等性（再送は `duplicate`）
- metadataだけで会社を信用せずDBのStripe IDと突合（`COMPANY_MISMATCH`）
- 生ペイロード・署名・秘密値をDBへ保存しない
- 日時はStripeのUTC Unix timestampを ISO8601 で保存し、**表示時に日本時間へ変換**

---

## 5. 解約予定日の正本

| 段階 | 値の出どころ |
|---|---|
| 予約直後 | Stripe APIの応答（`cancel_at`、無ければ `current_period_end`） |
| その後 | `customer.subscription.updated` Webhookの値で上書き |
| 表示 | サーバーが返した ISO をそのまま `Intl.DateTimeFormat('ja-JP', { timeZone: 'Asia/Tokyo' })` で表示 |

画面でもサーバーでも、月末・うるう年などの日付境界を独自計算していない。

---

## 6. 権限

| | 会社管理者・運営者 | 一般利用者 |
|---|---|---|
| 解約予約・予約取消 | ○ | **×（サーバーで403）** |
| 画面の解約導線 | 表示する | **表示しない** |
| 契約状況の確認 | ○ | ○（自社のみ・操作は不可） |

判定はサーバーの `canScheduleCancellation` / `canCancelScheduledCancellation`
だけを見る（画面側で契約状態から条件を組み立て直さない）。
既存の `canManageBilling`（`company_admin` / `operator`）をそのまま使い、
運営者の権限は変更していない。

---

## 7. 画面状態別の表示（`/settings`「契約・ライセンス」）

新しい設定URLは増やしていない。

| 状態 | 表示 |
|---|---|
| `active`・予約なし | 「契約を解約する」→ 確認を挟んで「解約を予約する」 |
| `active`・予約あり | 「**○年○月○日 に解約予定です**」＋「解約予約を取り消す」。**解約ボタンは重複表示しない** |
| 一般利用者 | 解約の導線を出さない |
| `past_due` | Customer Portal（支払い方法の更新）は維持。解約導線は出さない |
| `payment_required` | Checkout導線を維持。解約導線は出さない |
| `canceled` | 契約終了の表示のみ。**再契約ボタンは作らない** |

### 確認画面に必ず出す内容

- 解約は、現在のご請求期間の終了時に有効になります
- 解約予定日
- それまでは、これまでどおりすべての機能をご利用いただけます
- 次回更新日以降は、業務機能をご利用いただけなくなります
- 日割りでの返金・自動的な返金は行っておりません
- この操作で、お客様のデータが削除されることはありません
- 実行ボタン「解約を予約する」／戻る「キャンセル」

### 操作中の扱い

- 処理中はボタンを無効化し、`useRef` の番人で連打を1回に抑える
- 成功後は契約状態を再取得する
- すぐ反映されない場合のみ「反映を確認しています…」を表示し、
  **最大3回・2秒間隔**で確認して打ち切る（無限ポーリングにしない）
- 画面を離れたらタイマーを止める
- 失敗時は安全な日本語メッセージのみ（Stripeの生エラー・IDは出さない）

### 是正1件：再取得で画面が作り直される問題

`ContractContext.reload()` が毎回 `loading` へ戻していたため、
`AppLayout` が「ご契約状況を確認しています…」へ切り替わって画面ごと
再生成され、**手続き直後に設定画面が初期タブ（プロフィール）へ戻り、
利用者が結果を確認できなかった**（ブラウザ実測で検出）。

取得済みのあとの再取得では直前の値を表示し続けるよう直した。
「取得できていない状態を active として扱わない」という WEB-SALES-5B の
方針は変えていない（失敗時は従来どおり契約情報を捨てて `error` にする）。

---

## 8. 課金操作のレート制限

### 方式

**既存の `login_attempts` テーブルを再利用**した（新テーブルを作らない）。
ログイン試行（WEB-SALES-1B）・Web申込（WEB-SALES-2）と同じ仕組みで、
`scope` を分けて記録する。DBへ書くため**再起動しても制限は消えない**。

| 項目 | 採用値 |
|---|---|
| 対象 | Checkout・Customer Portal・解約予約・予約取消の**4経路** |
| キー① | 会社ID（`billing_company`） |
| キー② | 会社ID＋利用者ID（`billing_actor`） |
| 回数① | 会社あたり **10回** / 時間窓 |
| 回数② | 利用者あたり **5回** / 時間窓 |
| 時間窓 | **10分** |
| 永続方式 | SQLite `login_attempts`（既存テーブル・scope分離） |
| 応答 | `429 TOO_MANY_REQUESTS` ＋ `Retry-After`（既存のログイン制限と同じ規則） |
| 環境変数 | `BILLING_RATE_LIMIT_WINDOW_MS` / `_MAX_PER_COMPANY` / `_MAX_PER_ACTOR` |

### 選んだ理由

- **IPを使わない**：認証済みの操作であり、プロキシ越しのIPは書き換えられる。
  会社IDと利用者IDはセッション由来で偽装できない
- **会社単位を入れる**：管理者が複数いても、会社としての合計呼び出しを抑える。
  他社の操作は自社の制限に影響しない（キーが会社ごとに独立）
- **4経路をまとめて1つの枠**にする：経路ごとに別枠だと、Checkoutを上限まで
  叩いたあとPortalも上限まで叩けてしまい、Stripeへの合計回数を抑えられない
- **権限確認の後に置く**：先に置くと、一般利用者が拒否されるだけの要求で
  会社の枠を使い切り、管理者が本当の手続きをできなくなる（妨害できてしまう）
- **成功・失敗の両方を数える**：片方だけ数えると、もう一方を無限に繰り返せる
- **生の識別子を保存しない**：`hashAttemptKey`（HMAC-SHA256）で変換した値のみ。
  ハッシュ値が64桁の16進であること、会社ID・利用者IDが含まれないことを検証済み
- **時計を注入できる**：`check()` / `record()` が `now` を受け取る。
  受入テストは待たずに「時間窓を過ぎたあと」を確認する（sleepしない）

閾値は「通常の1回操作を妨げない」値。実測では、解約予約→取消→再予約と
続けても上限に達しない。

---

## 9. テスト

`tests/acceptance/contract-cancellation.mjs`（**206件**・新規）

| 区分 | 件数 | 主な確認 |
|---|---|---|
| A 認証と権限 | 10 | 未認証401・一般利用者403・運営者可・応答に識別子なし |
| B 会社の取り違え・改ざん | 10 | 他社ID/Subscription/Customer/数量/Price/按分の改ざんを無視 |
| C CSRF・Origin・メソッド | 7 | 予約・取消の双方で403・GET不可・拒否時Stripe0件 |
| D 解約予約 | 28 | activeのみ・cancel_at_period_endだけ送る・即時解約/返金なし・二重予約409・開始日/人数/データ不変 |
| E 予約取消 | 22 | 予約中のみ・期間終了後409・新規Subscription作成なし・反映されない応答は502 |
| F Webhook | 25 | updatedで予約記録のみ・deletedで初めてcanceled・canceled_at初回のみ・再送duplicate・他社更新不可・生ペイロード非保存・invoice.paid/past_due回帰 |
| G レート制限 | 27 | 4経路すべて対象・閾値内成功・超過429・Retry-After・会社ごと独立・超過時Stripe0件・一般利用者の連打で妨害不可・時計注入で窓明け確認・生ID非保存 |
| H 画面 | 36 | 確認必須・必須表示6項目・状態別表示・連打1要求・反映確認の上限・秘密値非表示・再契約ボタンなし |
| I 回帰 | 26 | 4Rのカード限定/Managed Payments/アンカー/按分・5CのPortal禁止機能・5Bの契約ガード・seat制御 |
| J 横断 | 9 | 実外部通信0件・即時解約/返金APIの実装なし・migrationは1列のみ |

### 既存テストの更新（`customer-portal.mjs`）

**削除・skip追加・条件緩和は0件。** 意味を保った1対1の更新を3か所行った。

| 更新 | 理由 |
|---|---|
| 区分ごとに課金レート制限の記録を消す処理を追加 | 本ファイルは1人の管理者で課金APIを数十回叩くため、新設の回数制限が先に効いて、本来確かめたい応答（502・409）を観測できなくなった。制限そのものは新規ファイルで検証している |
| 「★解約のボタンを作っていない」→「★解約はStripe Portalに持たせない（自社APIで行う）」＋「★Portalの補足文で解約を案内しない」 | 5Cの趣旨は「Stripe Portalに解約を持たせない」こと。WEB-SALES-6は解約をPortalの**外**の自社画面として実装したため、確認対象を趣旨どおりに置き換えた（Portal設定側で解約が無効であることは区分F/Hで引き続き確認） |
| 「解約・人数変更は問い合わせへ案内する」→「人数変更は問い合わせへ案内する」 | 解約は自社画面で扱えるようになったため、案内文から解約を外した。対象外である人数変更の案内は維持 |

この更新で customer-portal は 161件 → **162件**（+1）になった。

### ファイル別件数（全17本）

| ファイル | 件数 |
|---|---|
| acceptance | 279 |
| admin-dashboard | 24 |
| billing-ui | 85 |
| business-card | 71 |
| business-card-disabled | 5 |
| company-brain-basic | 32 |
| company-manual-search | 167（skip1） |
| company-manual-search-disabled | 10 |
| **contract-cancellation（新規）** | **206** |
| contract-gating | 91 |
| customer-portal | 162 |
| global-search | 101 |
| production-mail | 83 |
| ratelimit | 7 |
| security-foundation | 71 |
| signup-provisioning | 124 |
| stripe-contracts | 127 |
| **合計** | **1645件 成功・失敗0・skip1** |

開始基準1438 ＋ 新規206 ＋ 既存更新+1 ＝ 1645。

`npm run build` 成功（エラー0）。

### 外部通信

| 対象 | 件数 |
|---|---|
| 実Stripe通信 | **0** |
| 実決済・実Webhook・実メール | **0** |
| 本番DB接続 | **0** |

テストは `net`/`tls`/`dns`/`http(s)`/`fetch` を監視してループバック以外を遮断し、
`realNetCount() === 0` を検証している。

---

## 10. ブラウザ実測（隔離環境・実Stripe未接続）

| 確認 | 結果 |
|---|---|
| `active` 管理者 | 「契約を解約する」表示 |
| 押しただけ | 確認ダイアログ（`role="alertdialog"`）が出るだけで**契約状態は不変** |
| 必須表示6項目 | すべて表示を実測 |
| 「解約を予約する」3連打 | サーバーへの要求 **1件のみ** |
| 予約後 | 「2030年1月1日 に解約予定です」＋取消ボタン。解約ボタンは非表示 |
| 予約後の契約状態 | `active` のまま（DB実測） |
| 取消後 | 予約が消え、「契約を解約する」へ戻る |
| 手続き後のタブ | **契約タブのまま**（是正前はプロフィールへ戻っていた） |
| 一般利用者 | 解約導線なし |
| `past_due` / `payment_required` / `canceled` | 解約導線なし（Portal/Checkoutの既存導線は維持） |
| PC 1280px | 横スクロールなし |
| Mobile 375px | ボタン 309×44px・横スクロールなし |
| キーボード | ボタンにフォーカス可・ダイアログ内も操作可（44px） |
| console error | **0**（新規タブでの通常操作） |

---

## 11. 本番変更

なし。

| 対象 | 状態 |
|---|---|
| Lite `master` | `d8c1558` 無変更 |
| SmartLabo `master` | `1252347` 無変更 |
| Stripe（テスト・本番とも） | 接続なし・設定変更なし |
| 本番Webhookエンドポイント | 未登録のまま |
| XServer / DNS / Website / 本番DB | 接続・変更なし |

---

## 12. 明確な対象外（本工程で実装していない）

再契約／契約人数変更／即時解約／日割り返金／自動返金／
会社・利用者・業務データの削除／Stripe Portalへの解約機能追加／
本番用Portal Configuration作成／本番Webhook登録／live mode接続／
本番デプロイ／特商法・利用規約本文／キャンペーン／運営者向け契約一覧

---

## 13. 残存課題

| # | 内容 |
|---|---|
| 1 | **再契約が未実装**。`canceled` になった会社が自分で契約を再開する導線が無い（現状は問い合わせ） |
| 2 | **契約人数の変更が未実装**（Stripeのquantity変更と日割りの扱い） |
| 3 | 解約後のデータ保持期間・削除方針が未確定（本工程では削除しない） |
| 4 | 解約予約・取消の**利用者への確認メール**を送っていない（画面表示のみ） |
| 5 | 期間終了時の `customer.subscription.deleted` は**本番Webhook未登録のため未実機確認**。テストモードでの実機確認は後続工程 |
| 6 | 運営者向けの契約・解約状況の一覧が未実装 |
| 7 | レート制限の閾値は実運用の実績が無い。公開後に見直しが必要 |
| 8 | 本番Webhookエンドポイントが未登録 |
| 9 | 法務3点（特商法表記・利用規約の契約条項・キャンペーン規約）が未整備 |

---

## 14. Go / No-Go

| 対象 | 判定 |
|---|---|
| **WEB-SALES-6S（Stripeテストモードでの解約実機確認）** | **Go** |
| 本番公開 | **No-Go継続**（法務3点未整備・本番Webhook未登録・再契約/人数変更が未実装） |
| 再契約・契約人数変更 | **未着手**（本工程の範囲外） |

後続の実機確認で特に確かめるべき点

1. `cancel_at_period_end=true` がStripe画面に「期間終了時に解約」として出ること
2. 予約中も請求・利用が継続すること
3. 予約の取消がStripe側へ反映されること
4. 期間終了時に `customer.subscription.deleted` が届き、`canceled` へ移ること
5. その時点で業務機能が閉じ、契約開始日が保持されていること

---

*作成: Claude Code / WEB-SALES-6（2026-08-10）*
*実Stripe未接続・実決済0・実Webhook0・実メール0・本番変更0。*
