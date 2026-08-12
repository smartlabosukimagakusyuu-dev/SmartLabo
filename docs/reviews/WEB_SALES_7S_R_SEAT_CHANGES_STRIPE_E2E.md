# WEB-SALES-7S-R：契約人数の変更・支払い回復 Stripeテストモード実機再試験

作成日：2026-08-11
対象製品：Smart Labo Works ライト（https://lite.smartlaboworks.com ・本番未反映）
対象コード：`smartlabo-works-lite` / `feature/web-sales-7r-payment-recovery`（HEAD d71bb84・master未merge）
実施環境：Stripe test mode ／ 隔離SQLite ／ localhost ／ 完全架空データ

---

## 1. 実施目的

WEB-SALES-7S で検出した実装差異
（`invoice.payment_failed` が人数変更台帳を終端させ、後から同じ請求書の支払いが
成功しても契約人数を反映できない）を WEB-SALES-7R で是正した。
その修正が実際のStripeイベントで機能することを、**新しい完全隔離環境で
最初から**確認するのが本工程の目的である。

前回の環境・データは一切再利用していない。

---

## 2. 判定

**Go（差異0件）。WEB-SALES-7M（master統合準備）へ進んでよい。**

前回のNo-Go要因（支払い失敗での台帳終端・支払い完了導線の不在）は、
いずれも実機で解消を確認した。

---

## 3. 開始状態

| 項目 | 実測 |
|---|---|
| branch | `feature/web-sales-7r-payment-recovery` |
| HEAD | `d71bb84a80e05a5ffd8fb787d84e3c062d75f576`（origin一致） |
| master | `5ff37763591a19f4392529777ff403343887f923` |
| working tree | clean（untracked 0・stash 0） |
| テスト | 19ファイル **2167 PASS／0 FAIL／1 skip** |
| build | `npm run build` 成功 |
| migration | 022・023 あり |

前回工程のテストデータは、今回専用metadataの件数で後片付け済みを確認した
（Test Clock 0件・前回purposeのCustomer／activeなSubscription 0件）。

---

## 4. 隔離条件（test modeのみ）

* Stripe **test mode のみ**（live mode 未使用・実カード未使用・実在個人情報未使用）
* 隔離SQLite ／ localhost のみ ／ `MAIL_TRANSPORT=capture`
* 完全架空データ（会社名・氏名・メールはすべて `example.invalid`）
* 決済戻り先は**実行プロセスの環境変数だけ**で localhost へ上書き。`.env` は変更なし
* Webhook secret は `stripe listen` で取得し起動プロセスだけに保持。終了時に破棄
* Webhook転送先は localhost の隔離APIのみ

### 作成したStripeオブジェクト

| 種別 | 件数 |
|---|---:|
| Test Clock | 1 |
| Customer | 1 |
| Subscription | 1 |
| Checkout Session | **0** |
| Portal Session | 0 |
| 新規Product / 新規Price | 0 / 0 |

すべて `livemode=false`・今回専用metadata付き。
既存のCustomer・Subscription・Product・Price には触れていない。

---

## 5. 契約人数の増加（3名 → 5名）

初期状態：契約3名（基本料金×1 ＋ 追加アカウント×2 ＝ 26,000円/月）、
利用中2名・招待中1名・休止中1名。

人数変更APIの実行は**1回のみ**。Stripeへ送られたキー集合は期待どおりで、
禁止キー（price / coupon / promotion_code / cancel_at_period_end /
billing_cycle_anchor / refund / customer / metadata）の送信は0件だった。

```text
payment_behavior   = pending_if_incomplete
proration_behavior = always_invoice
items[0]           = 追加アカウント明細（数量4）
```

日割り請求額は **6,000円**（Stripeが計算。自社では計算していない）。

### 5-1. 本人認証の前

| 項目 | 実測 |
|---|---|
| 日割り請求書 | open（未払い残 6,000円） |
| `pending_update` | あり |
| 自社DB 契約人数 | **3名のまま** |
| Stripe 確定quantity | **2のまま** |
| 追加席 | 利用不可（4人目の招待は409） |
| 台帳 status | `pending_payment` |

### 5-2. `invoice.payment_failed` 受信後（★前回のNo-Go箇所）

本人認証が必要な支払い方法では、認証が終わる前にStripeがこのイベントを送る。

| 項目 | 実測 |
|---|---|
| 台帳 status | **`pending_payment` のまま**（終端しない） |
| `payment_state` | `authentication_required`（自社定義の安全な状態コード） |
| 支払い未完了の記録 | 日時を記録 |
| 自社DB 契約人数 | 3名のまま |
| Stripe `pending_update` | 有効 |
| 同一申請の再作成 | 409（`SEAT_PAYMENT_INCOMPLETE`） |
| 日割り請求書 | 1件のまま（二重作成なし） |

前回はここで台帳が `failed` へ終端していた。**是正を実機で確認した。**

---

## 6. 支払い再開の導線（オンライン請求書ページ）

Stripeがホストするオンライン請求書ページ（`hosted_invoice_url`）へ遷移する方式。
カード情報も認証情報も自社では扱わない。

`POST /api/billing/seat-payment-url` の実測：

* 未認証 401 ／ 一般利用者 403 ／ CSRFなし 403 ／ Originなし 403
* 他社管理者は自社に手続きが無いため 409（URLは返さない）
* 応答は `paymentUrl` の1項目だけ
* `Cache-Control: no-store`
* URLはStripe公式ホストの**完全一致**のみ許可（https）
* URLはDBのどの表にも保存しない（全テーブル走査で0件）
* 3連打しても外部への請求書参照は**1回だけ**（サーバー側で単一化）
* 監査には操作の事実だけを残す（URLは残さない）

代表がこのページで3Dセキュア認証と支払いを1回だけ実施した。

---

## 7. 支払い成功後の反映

実機で観測したイベント順序は、受入テストで再現した順序と一致した。

```text
invoice.payment_failed（認証待ち）
  → 代表による本人認証・支払い
  → invoice.paid（processed）
  → customer.subscription.pending_update_applied（適用済みのため何もしない）
```

| 項目 | 実測 |
|---|---|
| 自社DB 契約人数 | **3 → 5** |
| Stripe 追加席quantity | **2 → 4** |
| `pending_update` | 解消（適用済み） |
| 反映回数 | **1回だけ**（台帳1件・applied） |
| 請求書の突合 | 台帳の請求書と支払い済み請求書が一致 |
| 契約開始日 | 不変 ／ 契約状態 active 維持 |

`invoice.paid` を明示的に**1回だけ**再送しても、契約人数は5名のまま・
台帳は1件のまま・二重反映なし（冪等性を確認）。

---

## 8. 利用者の休止・復帰（5名契約）

* 追加された2席で新規招待2名がいずれも成功（使用枠5）
* 契約枠が満杯のときの復帰は409。文言は仕様どおりで、人数・氏名・
  メールアドレスを含まない
* 利用中1名を休止 → 使用枠が1つ減る。**Stripeの数量も契約人数も変わらない**
  （休止だけで料金を自動削減しない）
* 休止した本人はログインできない（既存セッションは破棄済み）
* 空き枠ができたら**同じ利用者IDのまま**復帰でき、氏名・過去の担当情報を引き継ぐ

---

## 9. 契約人数の削減予約（5名 → 3名）

### 9-1. 使用人数を超える削減の拒否

5枠使用中・4枠使用中のいずれでも、3名への削減は409で拒否された
（サーバー側判定）。拒否されたときに台帳は残らない。
対象者を**休止**して3枠以下へ整理してから予約した（削除は使っていない）。

### 9-2. 予約の内容

Stripeへ送ったのは `proration_behavior=none` と明細の数量だけ。

| 項目 | 実測 |
|---|---|
| 返金 | **0** |
| クレジット | **0**（顧客残高は0のまま） |
| 追加請求 | **0**（新しい請求書は作られない） |
| Stripe 追加席quantity | 4 → 2 |
| 自社DB 契約人数 | **5名のまま** |
| 次回の契約人数 | 3名（変更予定日はStripeが返した期間の終わりの写し） |
| 解約予約フィールド | 触れていない |
| 月額の案内 | 32,000円 → 26,000円（日割り返金なし） |

### 9-3. 予約中の制御・取消・再作成

* 削減後の人数を超える新規招待・復帰はいずれも409
* 一般利用者の人数操作は403、他社利用者への操作は404
* 二重予約は409で、Stripeを再呼び出ししない
* 取消：Stripeの数量を5名分へ戻す。返金・追加請求は0。二重取消は409
* 取消後は現在の契約人数まで復帰・招待できる
* 更新日確定の確認のため、削減予約を**1回だけ**再作成した

---

## 10. 解約予約との競合

* 削減予約中の解約予約は既存仕様どおり許可され、送るのは
  `cancel_at_period_end` だけ。削減の数量も台帳も消えない
* 解約予約中の人数の増加・削減・削減予約の取消はいずれも409で、
  その間Stripeを一度も呼ばない。`cancel_at_period_end=true` は維持される
* 解約予約を取り消しても、削減予約はそのまま残る

**触るStripeフィールドが完全に分離していることを実機で確認した。**

---

## 11. Test Clockによる次回更新日の確定

進行前に、テスト用の時計が今回専用であり、紐づく顧客が1件だけであることを確認した。
進めたのは**1回の更新だけ**（更新日直後 → 請求書確定のための短時間、いずれも同じ更新期間内）。

| 項目 | 実測 |
|---|---|
| 新しい請求書 | `billing_reason=subscription_cycle` |
| 請求額 | **26,000円（税別）** |
| 明細 | 基本料金 20,000円×1 ＋ 追加アカウント 3,000円×2 |
| 支払い | 成功（署名検証済みの支払い成功Webhookを受信） |
| 自社DB 契約人数 | **5 → 3** |
| Stripe 追加席quantity | 2（自社の契約人数と一致） |
| 台帳 | 反映済み・変更予定は解除 |
| 返金・クレジット | 0 |
| 契約開始日 | 不変 ／ 契約状態 active 維持 |
| 確定後の4人目 | 招待・復帰とも409 |

日付・金額はすべてStripeの値を正本とし、自社では計算していない。

---

## 12. 後片付け

削除は「`livemode=false`」「今回専用metadataの一致」「隔離DBが指す対象」の
3条件がすべて一致したものだけを対象に実施した（照合8項目すべて一致）。

* 未払いの請求書：0件（すべて支払い済み）
* テスト用の時計を削除 → 紐づく顧客も削除され、定期課金は解約された
* 既存のCustomer・Subscription・Product・Price は開始時と同数・無変更
  （過去工程で残っている顧客も参照・変更していない）
* ローカル：Stripe CLI停止（secret破棄）・隔離API停止・使用ポート開放・
  隔離SQLite・一時スクリプト・一時ログをすべて削除

### 12-1. Stripe仕様上、削除できずに残るもの

* 今回の定期課金オブジェクト（解約済み。Stripeは解約済みを削除できない）
* 支払い済みの請求書3件（確定済みの請求書は削除できない）

いずれも `livemode=false`・完全架空・削除済み顧客に紐づき、今回専用metadataで
特定できる。テストモードのため**実損は0円**。

---

## 13. 本番への変更

**0件。** 本番VPS接続・本番DB接続・本番migration適用・本番Webhook登録・
本番Stripe設定変更・XServer・DNS・Website変更・SMTP実送信のいずれも行っていない。
本番 `.env` の変更も0（決済戻り先は実行プロセスの環境変数だけで上書き）。
製品コードの変更も0（HEAD不変・clean）。

秘密値の露出も0（秘密鍵・Webhook secret・各種識別子・請求書ページのURLを
本文書・報告・ログ・画面のいずれにも出していない）。

---

## 14. 残存課題・申し送り

1. **`customer.subscription.pending_update_expired` は実機未確認。**
   期限切れには要求から約23時間の経過が必要で、支払い待ちの請求書を
   抱えたままテスト用の時計を進めることができないため、今回も観測していない。
   受入テストでは検証済み。「未実機確認」として申し送る。
2. 「支払い済み・期限切れではURLを返さない」は、支払い済み側のみ実機で
   間接的に確認した（反映後は手続きが閉じ導線が消える）。期限切れ側は
   受入テストでの確認にとどまる。
3. **本番のWebhookエンドポイントには、次の2イベントの受信登録が必要。**
   `customer.subscription.pending_update_applied` /
   `customer.subscription.pending_update_expired`
   （本番Webhookは未登録のため未設定）
4. **本番DBのmigration 017〜023 は未適用。** 本番反映時は023まで含めること。
   適用前にバックアップを取得する。
5. 本番公開のNo-Go要因は WEB-SALES-6M から変わらない
   （法務3点・本番Webhook未登録・本番Price未作成）。

---

## 15. WEB-SALES-7M への判定

**Go。**
実装・全テスト（19ファイル 2167 PASS／0 FAIL／1 skip）・build・
実機確認・秘密値検査・本番変更0 をすべて確認済み。
master統合の準備（release統合branchの作成とpush）へ進んでよい。
本番公開そのものは、上記14-3〜14-5の解消が前提となる。
