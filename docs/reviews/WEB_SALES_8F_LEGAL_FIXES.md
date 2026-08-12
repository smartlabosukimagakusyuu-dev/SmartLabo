# WEB-SALES-8F 法務・同意・消費税の監査差異是正

**実施日**：2026-08-11
**branch**：`feature/web-sales-8f-legal-fixes`（`feature/web-sales-8-legal-consent` から作成）
**対象**：WEB-SALES-8R 監査（`WEB_SALES_8R_LEGAL_CONSENT_AUDIT.md`）の重大5件・中4件・軽微7件
**本番への変更**：**0件**

---

## 1. 代表判断（本工程で正式採用）

### 消費税

- 当面は**日本国内向け**の販売のみ。消費税は**固定10％**
- Stripeの**固定Tax Rate**を使う。**`automatic_tax`（Stripe Tax）は使わない**
- Tax Rate は test / live で**別々に作成**し、環境変数 `STRIPE_TAX_RATE_ID` で受け取る
- IDの実値はコード・文書・ログ・Gitへ置かない
- 税抜価格：初期設定費 10,000円 / 基本料金 20,000円・月 / 追加アカウント 3,000円・月・1名
- 申込み前画面で**税抜額・税率・税込見込額**を明示する
- 初回日割りの確定税額はStripeが算定する
- 海外販売を行う場合は別工程で Stripe Tax 等へ移行する

### 解約期限

- 締切は「**更新日の前日 23:59:59（Asia/Tokyo）**」まで
- 更新日は Stripe の `current_period_end` を正本とする
- `current_period_end` を Asia/Tokyo の**暦日**へ変換し、その日の開始時刻を境界とする
- 画面はサーバーが返す締切をそのまま表示する

### 90日保持

- 解約後90日を経過した顧客業務データは、**当社所定の手順により削除**する
- 自動削除は実装しない。承認付きの手動運用手順を整備する
- 法令・税務・会計・不正防止・紛争対応・**同意の証拠**として必要な記録は削除対象外
- 会社行を単純に物理削除する運用にはしない

---

## 2. Stripe公式仕様の確認（実装根拠）

公式ドキュメントのみを根拠とした。要点：

| 確認事項 | 結果 |
| --- | --- |
| Tax Rate オブジェクト | `id`(`txr_`) / `percentage` / `inclusive` / `active` / `livemode` / `tax_type`(`jct` = Japanese Consumption Tax) |
| Checkoutの固定Tax Rate | `line_items[][tax_rates][]`（特定のプラン／請求書ラインへ適用）。`subscription_data[default_tax_rates][]` はサブスク全体の既定 |
| 明細レベルの優先 | サブスク項目に税率があれば、サブスクレベルの既定を上書きする |
| 継続請求への引き継ぎ | 「サブスクリプションの請求書が生成されると、税率がサブスクリプションから請求書にコピーされる」 |
| 項目あたりの上限 | サブスク項目は最大5、請求書項目は最大10 |
| `active=false` | 新しい Checkout Session には使えない（既存のサブスク・請求書では引き続き機能する） |
| 外税 | 小計に含まれず、`total = subtotal + tax` |
| 丸め | 「ラインアイテムレベル」「請求書レベル」をダッシュボードの請求書設定で選択 |
| `dynamic_tax_rates` | 非推奨。`default_tax_rates` / `tax_rates` との併用不可 |

**採用**：`line_items[][tax_rates][0]` を3明細すべてへ付ける（代表判断のとおり）。
`default_tax_rates` は使わない（指定箇所を1本化し、どの明細に何が付くかを明示するため）。

---

## 3. 変更ファイル

### 新規 6

| ファイル | 役割 |
| --- | --- |
| `server/db/migrations/025_add_legal_archive_and_tax.sql` | 法務アーカイブ／同意台帳の税・指紋列／CHECK制約 |
| `server/services/billing/cancellationDeadline.js` | 解約締切の算定（純関数・時計を注入） |
| `server/services/billing/taxRateService.js` | Tax Rate現物の検証と短時間キャッシュ |
| `server/services/legal/legalArchive.js` | 法務文書アーカイブの保証と過去版の復元 |
| `server/repositories/legalArchiveRepository.js` | アーカイブ台帳（追記のみ） |
| `deploy/nginx/legal-noindex.conf.example` | 法務ページの `X-Robots-Tag` 設定ひな形（**本番未適用**） |

### 変更 27（うちテスト9）

製品：`.env.example` / `server/app.js` / `config.js` / `consentRepository.js` /
`routes/billing.js` / `routes/contract.js` / `routes/legal.js` /
`billingService.js` / `pricing.js` / `seatChangeService.js` / `stripeClient.js` /
`consentService.js` / `legalDocuments.js` /
`FinalConfirmation.jsx` / `ContractNoticePage.jsx` / `LegalDocumentPage.jsx` /
`SettingsPage.jsx` / `legalApi.js` / `CURRENT_STATUS.md`

テスト：`lib/harness.mjs` ＋ 8ファイル

---

## 4. R1 解約期限の強制

`cancellationDeadline.js`（純関数）が締切を決める。

- `current_period_end` を **JSTの暦日**へ直し、その日の `00:00:00 JST` を境界とする
- `now < 境界` なら受付、`now >= 境界` なら拒否
- Asia/Tokyo は夏時間が無く UTC+9 固定のため、固定オフセットの算術で暦日を求める。
  月末・年末・うるう年は暦日番号の計算に含まれるため特別扱いを書かない
- 時計は引数で受け取る（テストで待たない）

`scheduleCancellation` は**Stripeを呼ぶ前**に判定する。

| 状況 | 応答 | Stripe |
| --- | --- | --- |
| 期限内 | 200（従来どおり） | 更新 |
| 期限後 | 409 `CANCELLATION_DEADLINE_PASSED` | **0回** |
| 更新日が未記録 | 409 `CANCELLATION_DEADLINE_UNKNOWN` | **0回** |

エラー文言にStripe ID・人数・PIIを含めない。
契約状況APIが `cancellationDeadlineAt`（＝前日23:59:59 JST）を返し、画面はそれを表示する
（画面で日付を計算しない）。解約予約の取消・人数削減の既存規則には触れていない。

> **運用上の注意**：`current_period_end` が未記録の会社は解約予約が409になる。
> 安全側（fail-closed）に倒した結果であり、その場合はお問い合わせでの対応となる。

---

## 5. R2 固定10％のTax Rate

### 設定と検証

- `STRIPE_TAX_RATE_ID`（`txr_` で始まる）を追加。`.env.example` にはキー名・説明・
  プレースホルダのみ（実IDなし）
- 申込み・人数増加の**直前**に現物を取得し、次を検証する
  - ID形式 / `object === 'tax_rate'` / `active === true` / `inclusive === false` /
    `percentage === 10` / `livemode` が Secret Key のモードと一致
- 1つでも合わなければ **503 `BILLING_TAX_NOT_CONFIGURED`** で止め、
  **Customer も Checkout Session も作らない**
- 成功だけを5分間キャッシュする（失敗は保持しない）
- ID・率を応答・画面・ログ・DBへ出さない
- 契約状況APIの `canStartCheckout` も税設定を条件に含める（必ず503になる導線を出さない）

### Checkout Session

3明細すべてに同じTax Rateを**1件だけ**付ける。実測した送信キー：

```
mode, customer,
line_items[0..2][price], line_items[0..2][quantity], line_items[0..2][tax_rates][0],
success_url, cancel_url,
payment_method_types[0]=card, managed_payments[enabled]=false, allow_promotion_codes=false,
subscription_data[billing_cycle_anchor], subscription_data[proration_behavior]=create_prorations,
subscription_data[metadata][company_id], metadata[company_id]
```

送っていないもの：`automatic_tax` / `dynamic_tax_rates` / `default_tax_rates` /
2件目以降の `tax_rates` / ブラウザ由来のTax Rate・税率・Price・数量。

### 継続請求・人数変更

| 操作 | 税の扱い |
| --- | --- |
| 毎月の請求 | Checkoutで作られたサブスク項目の税率をStripeが請求書へコピーする（公式仕様） |
| 人数増加（既存明細） | **数量だけ**を送る。`tax_rates` を送らない＝税率に触れない |
| 人数増加（明細を新規作成／1名→複数名） | `tax_rates` を**明示**する。税率が無ければ明細を作らない |
| 増加前の検査 | 既存明細の税率が欠落・別率・内税・複数なら 409 `BILLING_TAX_UNEXPECTED` で**開始しない** |
| 人数削減 | `proration_behavior=none` のまま。返金・クレジット0。税に触れない |
| 解約予約 | `cancel_at_period_end` のみ。税に触れない |
| Customer Portal | Portal Configuration で機能を固定。税率は変更できない |

### 表示

最終確認画面に、税抜額・**税率10％**・月額の税額・月額税込見込額・初期設定費の税額・
初期設定費の税込額を出す。値はすべてサーバーの `quote` から取り、**画面で掛け算をしない**。
「初回は日割りのため確定額はお支払い画面で決まる」「実際の税額は請求時点の法令および
適用条件に従う」を併記。

丸めの規則は `pricing.taxAmountOf()` の1か所に集約（JPYは1円単位・四捨五入）。
**Stripe実物との一致は後続の実機確認事項**として残す。

### 同意台帳

`tax_rate_percent` / `monthly_tax_amount` / `monthly_total_including_tax` /
`setup_fee_tax_amount` / `setup_fee_total_including_tax` を追加（migration 025）。
**Tax Rate IDは保存しない。**

---

## 6. R3 法務文書アーカイブと過去版の再現

`legal_document_archive`（追記専用）を新設し、**本文そのもの**を版ごとに1回だけ保存する。

- 対象は3法務文書＋**販売条件**（`document_key='pricing'`、本文は canonical JSON）
- 販売条件のスナップショットは `setupFee` / `baseMonthly` / `additionalSeatUnit` /
  `seatsIncludedInBase` / `taxRatePercent` / `taxMode` / `pricingVersion` / `currency`
- `UNIQUE(document_key, version)` ＋ `BEFORE UPDATE/DELETE` トリガーで追記専用を強制
- 起動時（`createApp`）と同意処理の直前に `ensureLegalArchive()` を実行
  - 未登録なら追記（外部通信なし・transaction内・1件でも食い違えば1件も書かない）
  - **同じ版で本文ハッシュが違えば例外で停止**（版を上げずに本文を変えられない）
- `restoreConsentDocuments()` が、同意記録の版から本文を引き、digestを組み直して
  一致を確かめてから返す（一致しなければ「復元できた」と扱わない）

**誤記の訂正**：WEB-SALES-8 の「版とハッシュで再現」は技術的に不正確だった。
ハッシュは同一性の**検証**はできるが内容を**復元**できない。本文はアーカイブが持つ。

### 固定digest

受入テストに現行digestを**固定値**で置いた。

```
c63ea7e7e6070350efe18d3eb6778a00ba5e8594e2ee2f802d2d8f2eb83a781f
```

本文・金額・税率のいずれかを変えて版を上げなければ、この確認が落ちる。

---

## 7. R4 noindex・canonical・nginx

| 層 | 状態 |
| --- | --- |
| アプリの `meta robots`（3ページ） | **実装・実測済み**（`noindex, follow`。離脱時に除去） |
| アプリの `canonical`（3ページ） | **実装・実測済み**（URLはサーバーの `canonicalUrl` をそのまま使用。離脱時に除去） |
| `/api/legal/*` の `X-Robots-Tag` | 実装・実測済み |
| **画面URLのHTMLヘッダー** | **未実機確認**。nginx用ひな形を `deploy/nginx/legal-noindex.conf.example` に準備。**本番未適用** |

ひな形は `/legal/` 配下**だけ**を対象とし、通常ページには付けない。
robots.txt でクロール自体は遮断しない（遮断すると noindex を読めなくなる）。
Lite側に robots.txt / sitemap.xml は置かない。

> **「二重化完了」とは書かない。** 本番HTMLで `curl -sI` により実測するまでは、
> アプリの meta のみが確認済みである。

法務API：不明キー・プロトタイプ由来キー（`__proto__` 等）は**必ず404**（L1）。
`Cache-Control: public, max-age=300, must-revalidate`（L2）。

---

## 8. R5 確認指紋（confirmationFingerprint）

契約状況APIが、画面に表示する条件全体を表す64桁の指紋を返す。

含めるもの：`companyId`（計算にのみ使用・画面へは出さない）/ `licenseCount` /
`baseMonthly` / `additionalSeatCount` / `additionalSeatUnit` / `monthlyAmount` /
`setupFeeAmount` / `taxMode` / `taxRatePercent` / `monthlyTaxAmount` /
`monthlyTotalIncludingTax` / `setupFeeTaxAmount` / `setupFeeTotalIncludingTax` /
3文書の版 / `pricingVersion` / `documentDigest`
（キー順を固定した canonical JSON の SHA-256）

- ブラウザは**計算しない**。受け取った値をそのまま同意APIへ返す
- サーバーは同意の時点で最新条件から計算し直し、不一致なら
  **409 `CONFIRMATION_OUTDATED`・DB記録0・Stripe呼び出し0**
- 画面は最新条件を取り直し、**3つのチェックをすべて解除**して
  「契約内容が更新されました。最新の内容を確認し、もう一度同意してください。」を表示
- Checkout直前にも同じ指紋を再照合する（`consentStatusOf`）
- 記録・請求に使う値は**常にサーバー算出値**

指紋は秘密値ではない。台帳へ `confirmation_fingerprint` として残す。
8F以前の記録（指紋が空）は最新の同意として扱わない（fail-closed）。

`FinalConfirmation.jsx` の `?? 3000` は削除し、サーバー値が欠けている場合は
独自表示せず取得エラーにする（L4）。

---

## 9. M1 料金版の上げ忘れ

`currentConsentDigest()` の入力を「販売条件の**版だけ**」から
「販売条件の**本文ハッシュ**（金額・税率を含む）」へ改めた。

- 固定digestテストが落ちる
- アーカイブが同version・別hashとして登録を拒否する
- 起動／同意処理が fail-closed

これにより「同意APIは200だがCheckoutは永久に409」という状態が生じなくなった
（digestが変われば新しい同意行として記録できるため）。

`consentRepository.insert` は **UNIQUE違反だけ**を吸収し、それ以外は再throwする（L6）。

---

## 10. M2 / M4 削除運用

製品コードに自動削除は追加していない。
`docs/runbooks/SMART_LABO_WORKS_LITE_DATA_DELETION.md` を新規作成した。

- 対象契約（`canceled_at` から90日）／承認／バックアップ／対象テーブル一覧
- **削除する（顧客業務データ）** と **削除しない（同意記録・法務アーカイブ・
  決済イベント・監査記録・契約請求記録）** を明確に区別
- 会社行・利用者行は**物理削除しない**。利用者のPIIは匿名化する
  （FKにより会社行の削除は必ず失敗するため）
- 削除順序（FK依存）／実行後の `integrity_check`・`foreign_key_check`／
  復元不能の確認／実施記録／中止条件
- 本番コマンドは記載せず、承認済み手順のみ。秘密値・実在会社情報は記載しない

法務本文も実態に合わせて改めた（自動削除と誤認させない）。

> 規約14条3項：「90日の経過後、契約者データは、次項に定める記録を除き、
> **当社所定の手順により削除します**。削除は当社の責任者の承認を経て実施するものであり、
> 期間の経過により自動的に削除されるものではありません。」

---

## 11. M3 DB制約 —— テーブル再構築を選んだ理由

SQLiteは既存テーブルへCHECK制約を後から追加できない。方式を比較した。

| 方式 | 利点 | 欠点 |
| --- | --- | --- |
| **テーブル再構築（採用）** | CHECKが宣言としてスキーマに残り、INSERT/UPDATEの双方に効く。同じ手順で NOT NULL の新列6本も入る | 索引・UNIQUE・FKを正確に作り直す必要がある |
| BEFORE INSERT trigger | 既存データを動かさない | 文ごとに書き分けが要り抜けを作りやすい。新列の追加は別途必要 |

**採用理由**：どのみち NOT NULL の列を6本足すため既存行への書き込みが必要であり、
`consent_records` は 024 で新設されたばかりで**本番未適用**、行数も小さい。
宣言的なCHECKのほうが後から読んで確実である。

`-- migration-requires: foreign_keys_off` を付け、transaction内で
新テーブル作成 → 写す → 差し替え → **索引を作り直す**。既存行は失わない。

### 実測（空DB / 024適用済みDBのコピー 両方）

| 確認 | 結果 |
| --- | --- |
| 空DBへ 001〜025 | 版数25・`integrity_check` ok・`foreign_key_check` 0件 |
| 024適用済み＋既存行 → 025 | 既存同意行が残り、元の列は不変。`companies` 行も不変。消えたテーブル0 |
| 税額の移行 | 26,000円 → 税2,600 / 税込28,600、10,000円 → 税1,000 / 税込11,000 |
| 指紋の移行 | 空文字（8F以前の行）。空の指紋は照合に通らないためCheckoutへ進めない |
| 索引・FK | `idx_consent_unique_snapshot`（UNIQUE）・`idx_consent_company_time`・FK2本を復元 |
| 025 再実行 | 版数・行数・整合性すべて不変 |

拒否を実測した値：契約人数0・負数、金額の負数、税額の負数、税率10以外、
内訳と合計の不一致、税込額の不一致、digest 64桁以外・大文字混じり、
指紋 64桁以外、版の空、日時の空、`tax_mode` の不正値、FK違反、UNIQUE重複。

アーカイブ側も CHECK ＋ UPDATE/DELETE トリガー ＋ UNIQUE(key, version) で守る。

---

## 12. 軽微7件

| ID | 対応 |
| --- | --- |
| L1 | `findLegalDocument` を `Object.hasOwn` 判定へ。`__proto__`/`constructor`/`toString`/`hasOwnProperty`/`valueOf` すべて404を実測 |
| L2 | 法務APIへ `Cache-Control: public, max-age=300, must-revalidate` |
| L3 | 法務ページへ canonical（サーバーの `canonicalUrl`。離脱時に除去） |
| L4 | `?? 3000` を削除。サーバー値が欠ければ取得エラー表示 |
| L5 | 監査ログは `created === true` のときだけ追記。再送では増えない（同意も増えない・エラーにもしない） |
| L6 | UNIQUE違反だけを吸収し、それ以外は再throw（FK違反が伝わることを実測） |
| L7 | 本文書で件数を実測値へ訂正。`console error` は**仕様応答とアプリ例外を区別**して記載（下記14節） |

---

## 13. テスト

既存テストの**削除・skip化・条件緩和は0件**。

| ファイル | pass | 8R時点 | 差 |
| --- | ---: | ---: | ---: |
| acceptance | 279 | 279 | 0 |
| admin-dashboard | 24 | 24 | 0 |
| billing-ui | 85 | 85 | 0 |
| business-card-disabled | 5 | 5 | 0 |
| business-card | 71 | 71 | 0 |
| cancellation-fixes | 146 | 146 | 0 |
| company-brain-basic | 32 | 32 | 0 |
| company-manual-search-disabled | 10 | 10 | 0 |
| company-manual-search | 167 | 167 | 0 |
| **contract-cancellation** | **236** | 206 | **+30** |
| contract-gating | 94 | 94 | 0 |
| customer-portal | 164 | 164 | 0 |
| global-search | 101 | 101 | 0 |
| **legal-consent** | **480** | 260 | **+220** |
| production-mail | 83 | 83 | 0 |
| ratelimit | 7 | 7 | 0 |
| **seat-changes** | **413** | 374 | **+39** |
| security-foundation | 71 | 71 | 0 |
| signup-provisioning | 124 | 124 | 0 |
| **stripe-contracts** | **135** | 133 | **+2** |
| **合計（20ファイル）** | **2727** | 2436 | **+291** |

**0 FAIL / 1 skip**（skipは8R時点と同じ既存1件）。`npm run build` 成功。実外部通信0件。

### 既存テストへの変更（緩和ではない）

- **前提の追加（8ファイル）**：`STRIPE_TAX_RATE_ID`（架空ID）とTax Rateスタブ、
  同意APIの確認指紋。実際の画面と同じ手順（契約状況を取る→その指紋で同意する）を
  `harness.mjs` の `consentWithFingerprint()` に集約した
- **前提の追加（`contract-cancellation`）**：解約締切は `current_period_end` から決まるため、
  active な会社に更新日を持たせた
- **確認の置き換え（理由をテスト内コメントに記載）**
  1. 規約の「消費税等の額は」→「消費税等を申し受けます」＋**税率10％**＋税率改正時の扱い（1件→3件）
  2. 最終確認の「現在の月額合計（税別）」→「月額合計（税別）」＋税率・税額・税込の5件を追加（1件→6件）
  3. 「税別の明示」→ 税別・**税率**・確定額はStripeで決まる旨（1件→3件）
  4. 「★『自動削除』と断定していない」の語句一致 → 「自動削除を約束していない」＋
     「自動削除ではないと明示」＋「所定の手順により削除と記載」（1件→3件）
  5. `pricing.buildLineItems(n)` → `buildLineItems(n, { taxRateId })`。
     **税率を渡さないと null を返す**確認と、全明細に同一税率が1件だけ付く確認を追加（+2件）
  6. 旧版digestのテストデータを `'old-digest'` → 64桁hex（DB制約に合わせた形式変更）
- いずれも確認は**同数以上**へ増えている

### 新規の確認（主なもの）

解約期限（暦日・境界・UTC/JST・月末・年末・うるう年・Stripe0回・画面表示）／
税（設定不足・ID不正・inactive・10％以外・inclusive・livemode不一致・3明細・
`automatic_tax`なし・台帳の税額・ID非露出・画面表示・人数増減）／
アーカイブ（同version同hash再利用・同version別hash拒否・版更新で追加・過去本文取得・
digestからの復元・UPDATE/DELETE不可・固定digest）／
noindex（canonical・除去・不明キー404・prototypeキー404・Cache-Control・nginxひな形）／
古い画面（409・DB0・Stripe0・再取得・checkbox解除・他社指紋・税込み差異）／
DB制約（不正人数・負数・不正digest・不正version・不正日時・不正税率・UNIQUE・FK・整合性）／
軽微（`created=false`で監査ログ増えない・UNIQUE以外の例外伝播・unknown key 404）

---

## 14. 画面確認（PC / Mobile）

隔離DB・Stripeスタブ・外部通信遮断・完全架空データ。
**スクリーンショットは取得できていない**（ブラウザペインが非表示で描画されないため）。
以下はすべて**DOM実測値**である。

### PC 1280px

- 法務3ページ：`meta robots = noindex, follow`、`canonical` が各ページ固有
- 横スクロール0（`scrollWidth == clientWidth`）
- 44px未満の操作領域0
- `/login` へ移動すると meta も canonical も**消える**。戻ると再付与される

### Mobile 375px

- 最終確認画面：横スクロール0、はみ出し要素0
- 表示（サーバー値）：3名 / 基本20,000円 / 追加 2名×3,000円＝6,000円 /
  月額合計（税別）26,000円 / **消費税率10％** / 月額の消費税2,600円 /
  月額合計（税込見込み）28,600円 / 初期設定費10,000円 / 同消費税1,000円 / 同税込11,000円
- 同意欄3つとも未選択・ラベル高64px・申込ボタン44px・未選択時は無効
- 法務リンク3本：44px・`target="_blank"`・`rel="noreferrer noopener"`

### 古い条件からの申込み（R5の再現確認）

1. 画面が「ご契約人数 3名 / 税込28,600円」を表示
2. 画面を開いたままDB上で人数を10名へ変更
3. 3つチェックして申込ボタンを押す
4. → **409。「契約内容が更新されました」を表示。決済へ進まない**
5. → 画面が自動で最新条件へ更新（**10名 / 税込51,700円**）
6. → **3つのチェックがすべて外れ、ボタンは無効**

### 解約申請の期限

契約状況APIの実測：更新日 `2026/9/20 17:14 JST` → 締切 `2026/9/19 23:59:59 JST`
（＝更新日の前日23:59:59）。

### console

- **アプリ例外0件**
- 記録されたのは仕様どおりの応答2件のみ：
  未ログイン時の `401`、および古い条件で申し込んだときの `409`。
  いずれも設計どおりのHTTP応答であり、アプリの例外ではない
- 画面DOMに Tax Rate ID・Stripe識別子・Checkout URL・秘密鍵・指紋の露出**0件**

---

## 15. 秘密値

- `sk_live` / `sk_test` / `whsec` / `price` / `bpc` / `cus` / `sub` / `in` の実値：**0**
- **`txr_` の実値：0**（架空の `txr_FAKE0000NOTREAL0000` とプレースホルダのみ）
- Checkout URL / Hosted Invoice URL / Private Key：0
- `.env` 追跡：0（`.env.example` はキー名・説明・プレースホルダのみ）
- SQLite / ログ / 一時スクリプト / 実在申込者情報：0

---

## 16. 本番への変更

**0件。** master merge・push、本番デプロイ、本番VPS・本番DB接続、本番migration、
Stripe実通信・live mode・実決済・実Webhook、**本番Tax Rateの作成**、本番Price作成、
本番Webhook登録、本番 `.env` 変更、SMTP実送信、**nginx本番変更**、DNS・XServer変更、
GitHub Pages公開、公開Website変更のいずれも行っていない。

---

## 17. 残存課題

### 17-1. 弁護士による最終確認（未完了）

1. 特定商取引法の適用の有無（法26条1項1号の適用除外の該当性）
2. 外部AI事業者への送信の位置づけ（委託か、外国にある第三者への提供か）
3. 免責・損害賠償上限・専属的合意管轄・規約変更条項の有効性
4. 個人事業主の申込みにおける消費者性の判断
5. 消費税の表示方法（総額表示義務との関係。本サービスは事業者向けだが要確認）

### 17-2. 本番作業（未実施）

1. **本番Tax Rateの作成**（test / live の2本）。作成後、`percentage=10` / `inclusive=false` /
   `active=true` / `livemode` 一致 を実機で確認すること
2. **Stripe実物との税額一致の確認**（Invoice の `subtotal` / `tax` / `total`）。
   初回日割り・毎月請求・人数増加の日割りの3ケース
3. 請求書設定の**丸め方式**（ラインアイテムレベル／請求書レベル）の決定
4. 本番Price作成・本番Webhook登録
5. 本番migration 017〜**025** 未適用
6. **nginx設定の適用と実機確認**（`deploy/nginx/legal-noindex.conf.example`）。
   適用するまで「二重化完了」とは言えない
7. 代表による所在地・電話番号の目視照合（repo内に承認値の一次記録がないため）

### 17-3. 運用・実装（未実施）

1. 解約後90日の**自動削除は未実装**（手順書による手動運用）。
   月次で対象会社の有無を確認する運用を定めること
2. canceled後の自己再契約は未実装（お問い合わせ対応）
3. 既存active契約者への再同意導線は未実装
4. 公開Websiteの W1〜W4（`[CEO確認待ち]`・専門家確認前の表示・特商法ページ不在・
   申込導線からLite本体3文書へのリンク）は**次工程**
5. `current_period_end` が未記録の会社は解約予約が409になる（fail-closed）。
   お問い合わせでの対応が必要

---

## 18. 判定

| 対象 | 判定 |
| --- | --- |
| **WEB-SALES-8F（本工程）** | **Go**（重大5・中4・軽微7のすべてに対応。2727 PASS / 0 FAIL） |
| **WEB-SALES-8 再監査** | **Go**（独立監査を受けられる状態） |
| **master統合** | **条件付きGo**（再監査の通過を前提とする） |
| **本番公開** | **No-Go**（17-1 と 17-2 の未完了が前提条件） |
