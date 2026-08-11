# WEB-SALES-8F-B 契約後の税率監視・古い契約更新日の安全な再取得

**実施日**：2026-08-11
**branch**：`feature/web-sales-8fb-billing-safety`（`feature/web-sales-8f-legal-fixes` から作成）
**対象**：WEB-SALES-8F-A 独立再監査の**中程度2件のみ**
**本番への変更**：**0件**

> 料金体系・法務本文・同意画面・契約状態遷移・Stripe Tax（automatic_tax）への変更は
> 一切行っていない。新しいmigrationも追加していない。

---

## 1. 対象とした2件

### M-A 契約成立後の税率監視が無い

Checkout作成時には3明細すべてへ固定10％のTax Rateを付けているが、
**契約が成立したあと**に定期明細から税率が外れても製品側に検知経路が無かった。
税率の検査は「Checkout作成時」と「人数増加時」の2か所だけで、
人数を一度も変えない会社は検査経路を一度も通らない。
毎月の請求から消費税が静かに落ちても気づけない状態だった。

### M-B 古い更新日で解約できない

解約申請の締切は `companies.current_period_end` だけを見て判定しており、
Stripeを読み直さない。更新のWebhookを取りこぼすなどで保存値が過去のまま残ると、
契約が `active` でも 409 `CANCELLATION_DEADLINE_PASSED` になり、
利用者は設定画面から解約できなくなる。
規約が約束する「設定画面からの解約」が実行できない状態になりうる。

---

## 2. 変更ファイル（5件・製品3／テスト2）

| ファイル | 内容 |
| --- | --- |
| `server/services/billing/taxRateService.js` | 定期課金全体の税率検査（`subscriptionTaxIssueOf`）と安全コード定義を追加。既存の `subscriptionItemTaxIssueOf` を再利用 |
| `server/services/billing/billingService.js` | Webhookの前処理で税率を確認（M-A）／更新日の読み直し（M-B） |
| `server/services/billing/cancellationDeadline.js` | 読み直しが必要かの判定 `needsPeriodEndRefresh` を追加 |
| `tests/acceptance/stripe-contracts.mjs` | M-Aの受入テストと定期課金スタブ |
| `tests/acceptance/contract-cancellation.mjs` | M-Bの受入テストとスタブの拡張 |

**画面（`src/`）の変更は0件。** 契約状況APIが返す文言をそのまま表示する既存の作りで、
「期限を過ぎた」と「決済事業者の確認に失敗した」は別の文言として利用者に届く。

---

## 3. M-A の実装方式

### 3-1. 検査する場所とタイミング

`prepareSeatContext`（Webhook受け口が `applyEvent` の**前**に呼ぶ非同期の前処理）で行う。

```
署名検証（受け口）
  → prepareSeatContext            ← ここでStripeを読む（トランザクションの外）
      ・会社の照合
      ・定期課金を1回だけ取得
      ・税率の整合を判定 → taxIssue
  → applyEvent（同期・BEGIN IMMEDIATE の短いトランザクション）
      ・既存の契約処理をそのまま実行
      ・taxIssue があれば safe_error_code として記録するだけ
  → 200
```

対象イベントは `checkout.session.completed` と `invoice.paid`。

**二重GETをしない。** `invoice.paid` は人数の照合のためにすでに定期課金を読む場合があるため、
**その1回を使い回す**。人数の手続きが無い場合も税率の確認のために1回だけ読む
（人数の照合には使わないよう、`subscription` は手続きがあるときだけ渡す）。

`applyToCompany` は**同期のまま**であり、`await` を持たない
（受入テストで関数本体を切り出して確認している）。

### 3-2. 会社・Customer・Subscription の照合

既存の `resolveCompany` と同じ考え方に従う。

- metadata の `company_id` だけを根拠にしない
- 定期課金の `id` が DBの `stripe_subscription_id` と一致すること
- `customer` が DBの `stripe_customer_id` と一致すること
- Customer から会社を引き直し、別の会社を指していないこと

**参照するのはDBに控えた自社の定期課金だけ**である。
イベント本文に他社の Subscription ID が入っていても読みに行かない
（受入テストで、他社IDのパスへGETが飛ばないことを確認）。
対応が確認できないときは判定しない（記録もしない）。

### 3-3. 正常とみなす条件

- 定期課金の `livemode` が使用中のSecret Keyのモードと一致する
- 基本料金の明細が1つだけあり、設定済みTax Rateが**1件だけ**付いている
- 追加席の明細がある場合は、同じTax Rateが**1件だけ**付いている
- **契約人数1名で追加席の明細が無いことは正常**
- 税率が展開された形で返る場合は `active` / `inclusive=false` / `percentage=10` も確認する

Tax Rate の現物取得（`assertUsableTaxRate`）は**呼ばない**。
Webhookのたびに追加のGETを増やさないため、IDの一致と、
展開された税率オブジェクトの内容だけで判定する。

### 3-4. 異常時にすることと、しないこと

| する | しない |
| --- | --- |
| 安全なコードを `stripe_events.safe_error_code` へ記録 | 契約を停止する／`active` を取り消す |
| `logger.error` に**コードだけ**を残す | 業務機能を閉じる |
| 既存の契約処理（有効化・人数反映）をそのまま継続 | Tax Rateを自動で付け直す |
| Webhookへ **200** を返す | 自動返金・自動再請求 |
| | 契約人数・契約開始日の変更 |
| | Webhookの無限再送 |

安全コード（1か所に集約：`SUBSCRIPTION_TAX_ISSUE`）

```
BILLING_TAX_RATE_MISSING          明細に税率が付いていない
BILLING_TAX_RATE_MULTIPLE         想定外に複数の税率が付いている
BILLING_TAX_RATE_UNEXPECTED       別ID・別率・内税
BILLING_TAX_RATE_INACTIVE         税率が無効化されている
BILLING_TAX_LIVEMODE_UNEXPECTED   定期課金のlivemodeが実行環境と一致しない
BILLING_TAX_SUBSCRIPTION_UNEXPECTED  明細の構成が想定と違う（基本料金が無い・複数）
```

記録・ログへ出さないもの：Tax Rate ID／Price ID／Customer ID／Subscription ID／
Invoice ID／Stripe secret／Webhook secret／Checkout URL／Hosted Invoice URL／
カード情報／会社名／氏名／メールアドレス／Stripeの生エラー／生Webhook payload。
`console.log` は追加していない（既存 `logger` のみ）。

税率の異常コードは、人数反映の情報コード（`SEAT_CHANGE_APPLIED` 等）より**優先して**残す。
人数の反映結果は座席台帳から確認できるが、税率の異常はここでしか気づけないためである。

### 3-5. 重複防止

`stripe_events.stripe_event_id` の UNIQUE により、同じイベントは1回しか処理されない。
再送は `duplicate` として何もせず終わるため、**ログも記録も増えない**
（受入テストで4回連続送信し、台帳の行数が増えないことを確認）。

**新しいmigrationは不要だった。** `stripe_events` に `safe_error_code` 列が既にあり、
安全な分類コードを保存する場所として設計されているため、それを再利用した。

### 3-6. Stripe参照失敗との区別

| 状況 | 扱い |
| --- | --- |
| 税率の異常 | 安全コードを記録／契約処理は継続／**200** |
| Stripeの参照失敗 | 例外をそのまま投げ、受け口が **500**。Stripeの再送でやり直す |

参照できなかったイベントを「処理済み」で終端せず、「税率正常」とも記録しない。
これは WEB-SALES-7R で採用した
「Stripe参照はトランザクション開始前・失敗時はclaim前に止めてStripeの再送へ委ねる」
と同じ設計である。自動再試行のコードは新設していない。

---

## 4. M-B の実装方式

### 4-1. 読み直す条件

```
needsPeriodEndRefresh(current_period_end, now)
  true … 未記録 ／ 日時として解釈できない ／ 現在時刻より前
  false … それ以外（＝保存値が未来）
```

**「締切を過ぎたか」とは別の判定である。**
更新日が未来でも締切は過ぎうる（更新日当日など）が、その場合は保存値が正しいので
読み直さない。ここを取り違えると、正常な会社にまで追加の外部呼び出しが発生する。

実測（受入テスト・独立プローブとも）

| 保存値 | 追加GET | 結果 |
| --- | ---: | --- |
| 未来 | **0回** | 従来どおりの判定 |
| 過去 | 1回 | Stripeの値で再判定 |
| 未記録 | 1回 | 同上 |
| 解釈不能 | 1回 | 同上 |
| 再操作（保存済み） | **0回** | 保存した値を使う |

### 4-2. 読み直し時の検証

ブラウザ入力は一切使わない。会社はセッション由来、Stripeの識別子はDBの保存値。

- `livemode` が使用中のSecret Keyのモードと一致
- 定期課金の `id` が DBの `stripe_subscription_id` と一致
- `customer` が DBの `stripe_customer_id` と一致
- Customer から引き直した会社が同じ会社
- 定期課金が `canceled` / `incomplete_expired` / 削除済みでない
- `current_period_end` が有効な日時として取り出せる
- **参照している間に契約状態・Subscription ID・解約予約が変わっていないこと**
  （変わっていれば安全に停止）

### 4-3. 結果の扱い

| 状況 | 応答 | 解約更新API | DB |
| --- | --- | ---: | --- |
| 検証成功・期限内 | 200（解約予約） | 1回 | 更新日だけ保存 |
| 検証成功・期限後 | 409 `CANCELLATION_DEADLINE_PASSED` | **0回** | 更新日だけ保存 |
| 検証失敗（不一致・canceled・日時不正） | 409 `CANCELLATION_DEADLINE_UNKNOWN` | **0回** | **上書きしない** |
| Stripe参照失敗 | 502 `BILLING_PERIOD_UNVERIFIED` | **0回** | **上書きしない** |

**参照失敗を「期限超過」と断定しない。**
502の文言は「ご契約の更新日を決済事業者へ確認できませんでした。」であり、
利用者には「期限を過ぎた」場合と区別して届く。
Stripeの生エラー・識別子は応答にもログにも出さない。自動再試行はしない。

### 4-4. DB更新と競合

- 更新するのは `current_period_end` **だけ**
  （`canceled_at` / `contract_started_at` / `license_count` /
  `cancellation_scheduled_at` / 人数削減予約には触れない）
- 対象会社だけを更新（他社データが変わらないことをテストで確認）
- 保存の直前に契約状態・Subscription ID・解約予約を再確認
- **DBトランザクション中にStripe通信をしない**（参照 → 検証 → 単一のUPDATE）
- 読み直しは1要求につき最大1回

---

## 5. テスト

| ファイル | pass | 8F時点 | 差 |
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
| **contract-cancellation** | **294** | 236 | **+58** |
| contract-gating | 94 | 94 | 0 |
| customer-portal | 164 | 164 | 0 |
| global-search | 101 | 101 | 0 |
| legal-consent | 480 | 480 | 0 |
| production-mail | 83 | 83 | 0 |
| ratelimit | 7 | 7 | 0 |
| seat-changes | 413 | 413 | 0 |
| security-foundation | 71 | 71 | 0 |
| signup-provisioning | 124 | 124 | 0 |
| **stripe-contracts** | **244** | 135 | **+109** |
| **合計（20ファイル）** | **2894** | 2727 | **+167** |

**0 FAIL / 1 skip**（skipは既存1件のみ・増減なし）。`npm run build` 成功。実外部通信0件。

### 既存テストの置き換え（3件・理由をテスト内コメントに記載）

WEB-SALES-8F では「DBの更新日が過去なら即座に期限後」という当時の仕様を前提に
確認していた。M-B でその仕様が変わったため、次のとおり置き換えた。

| 旧 | 新 | 件数 |
| --- | --- | ---: |
| 「期限後はStripeを一度も呼ばない」 | 「解約更新（POST）を呼ばない」＋「読み直しは1回だけ」＋「読み直しはGETで行う」 | 1→3 |
| 「エラーコードはCANCELLATION_DEADLINE_PASSED」 | 同左（DBもStripeも期限後の場合として維持） | 1→1 |
| 「更新日が未記録なら409で止める」「未記録でもStripeを呼ばない」 | 「未記録でもStripeの値で解約できる」＋「読み直しは1回だけ」＋「読み直した値をDBへ保存」＋「解約更新は1回だけ」 | 2→4 |

**テスト削除0・skip追加0・条件緩和0。** 確認件数はいずれも増えている。

### 新規の確認（主なもの）

- M-A：正常（checkout / invoice.paid / 1名契約）、同一イベント再送、
  基本の税率欠落・追加席の税率欠落・別税率・複数税率・内税・10％以外・inactive・
  構成不正（基本料金が無い／複数）・livemode不一致・他社Subscription ID・Stripe参照失敗。
  各異常について「契約を止めない・人数を変えない・開始日を上書きしない・
  自動修正/返金/再請求を呼ばない・Stripe ID/PII/秘密値を残さない・
  再送で記録が増えない」を確認
- M-B：保存値が未来/過去/未記録/解釈不能でのGET回数、Stripe側も期限後、
  canceled、Customer不一致、livemode不一致、更新日不正、参照失敗、
  参照失敗と期限超過の区別、他社データ不変、3連打での二重防止、
  解約予約の取消、解約予約中の人数変更拒否、未認証401・一般利用者403・
  Originなし403・CSRFなし403（いずれも読み直しを起こさない）

---

## 6. 秘密値・外部通信・本番

- `sk_live` / `whsec` / `price` / `cus` / `sub` / `txr_` の**実値：0**
  （テストの架空値 `txr_FAKE0000NOTREAL0000` / `txr_OTHER0000NOTREAL` のみ）
- `.env` 追跡0・SQLite/ログ/一時ファイルの追跡0
- 実外部通信0件（Stripeはループバックのスタブのみ）
- 一時DB・一時スクリプトはrepo外に作成し、終了時に削除
- **migration追加0。001〜025は不変。**
- 本番VPS・本番DB・本番Stripe・nginx・Website・DNS・XServer・`.env`：変更0

---

## 7. 残存課題（本工程では未着手・8Fから変更なし）

1. **Stripe test mode の Tax Rate 実作成**と、実物の請求額
   （`subtotal` / `tax` / `total`）の確認。初回日割り・毎月請求・人数増加の3ケース
2. Stripe請求書の**丸め方式**（ラインアイテムレベル／請求書レベル）の決定
3. live mode Tax Rate の作成／本番Price作成／本番Webhook登録
4. 本番migration 017〜025 の適用
5. **nginx適用と本番HTMLヘッダーの実測**（適用まで「noindex二重化完了」とは言えない）
6. 弁護士確認（特商法の適用・AI送信の位置づけ・免責/管轄/規約変更条項・
   個人事業主の消費者性・消費税の表示方法）
7. 公開Website W1〜W4 の是正
8. 解約後90日の削除の自動化（現在は承認付きの手動運用手順）
9. canceled後の自己再契約／既存active契約者への再同意導線

> M-A で検知できるのは「定期明細から税率が失われた／違う」ことである。
> **実際の請求額に消費税が正しく乗るかは、本番Stripeでの実機確認が必要**であり、
> 本工程では確認していない（スタブ確認のみ）。

---

## 8. 判定

| 対象 | 判定 |
| --- | --- |
| **WEB-SALES-8F-B（本工程）** | **Go**（M-A・M-B とも是正。2894 PASS / 0 FAIL / 1 skip） |
| **Stripe test mode 実機確認** | **Go**（次に行うべき工程。Tax Rateを作成し、実物の税額を確認する） |
| **master統合** | **条件付きGo**（8F-A再監査の指摘が解消されたため、統合の障害は無い。
本番公開がNo-Goのままであることを合意事項に明記すること） |
| **本番公開** | **No-Go 継続**（第7節の1〜7が未完了） |
