# WEB-SALES-7S：契約人数の増加・利用者の休止／復帰 Stripeテストモード実機確認

作成日：2026-08-10
対象製品：Smart Labo Works ライト（https://lite.smartlaboworks.com ・本番未反映）
対象コード：`smartlabo-works-lite` / `feature/web-sales-7-seat-changes`（HEAD 537f175・master未merge）
実施環境：Stripe test mode ／ 隔離SQLite ／ localhost ／ 完全架空データ

---

## 1. 判定

**No-Go。実装差異を1件検出したため、`WEB-SALES-7R` での修正が必要。**

第1部（決済前確認）と、増加リクエストの送信内容・支払い前の状態までは
すべて仕様どおりだった。差異は「支払いが遅れて成功したときの反映経路」にある。

本工程では製品コードを一切変更していない。

---

## 2. 実施範囲と実施しなかったこと

| 項目 | 実施 |
|---|---|
| 第1部：決済前確認（利用者枠・休止／復帰） | 実施（62項目 全PASS） |
| 第2部：3名→5名の増加リクエスト | 実施（1回のみ） |
| 支払い前の状態確認 | 実施 |
| 本人認証・決済の完了 | **実施しない**（理由は第5節） |
| 支払い成功後の反映確認 | 未実施 |
| 増加の支払い失敗（別環境） | 未実施 |
| 第3部：利用者の休止／復帰（実機） | 第1部で隔離環境により実施済み |
| 第4部：削減予約・取消・更新日確定 | 未実施 |
| 第5部：解約予約との競合 | 未実施 |
| 第6部：レート制限 | 未実施（第1部で権限・多重操作の一部のみ） |
| 第7部：ブラウザ確認（PC／Mobile） | 未実施 |

---

## 3. 第1部：決済前確認（62項目 全PASS・差異0）

隔離SQLite・localhostのみ・外部通信遮断（net/tls/dns/http/fetch）の環境で実測した。
**実外部通信0件**。

### 3-1. 初期状態（12項目）

契約状態active／契約人数3名／利用中2名／招待中1名／休止中1名／
解約予約なし／未解決の人数変更0件／通常月額26,000円／5名時32,000円／
契約枠API（契約3・使用3・残り0・招待中1・休止中1）

### 3-2. 利用者枠の数え方（7項目）

利用中は枠を使用する／招待中は枠を使用する／休止中は使用しない／
削除済みは使用しない／停止中（suspended）は使用する／
同じ会社内だけを数える／他社利用者は数えない

### 3-3. 権限・テナント分離（9項目）

一般利用者は休止・復帰とも403／他社利用者の操作は404（存在を知らせず対象の状態も不変）／
管理者が0人になる休止は400 `LAST_ADMIN_PROTECTED`／自分自身の休止は400／
いずれのエラーにも氏名・メールアドレスを含まない

### 3-4. 休止（14項目）

休止直後に既存セッション破棄（401・DBのセッション行も0件）／休止後はログイン不可（403）／
使用枠が3→2へ即時減少／契約人数は3のまま／Stripe呼び出し0件／
監査履歴 `USER_PAUSE`（操作者ID付き・氏名メールは保存しない）／二重の休止は409

### 3-5. 復帰（13項目）

空き枠なしの復帰は409で、文言は仕様どおり
「契約枠に空きがありません。別の利用者を休止するか、契約人数を追加してください。」
（人数・氏名・メールアドレスを含まない）／
空き枠ありでは復帰成功／新規利用者を作らない／同じ利用者IDのまま／
氏名・メール・作成日時を引き継ぐ／担当している業務データが残る／
休止日時と操作者が消える／枠を再び1つ使用／監査履歴 `USER_RESUME`／
復帰後はログイン可能／二重の復帰は409

**休止／復帰に差異はない。**

---

## 4. 第2部：契約人数の増加（3名→5名）

### 4-1. 作成したStripeオブジェクト

| 種別 | 件数 |
|---|---:|
| Test Clock | 1 |
| Customer | 1 |
| Subscription | 1 |
| Checkout Session | **0** |
| Portal Session | 0 |
| 新規Product / 新規Price | 0 / 0 |

すべて `livemode=false`・完全架空（`example.invalid`）・今回専用metadata付き。
既存のCustomer・Subscription・Product・Priceは参照も変更も削除もしていない。

初期状態（Stripe）：20,000円×1 ＋ 3,000円×2 ＝ **26,000円/月**、追加席quantity=2

### 4-2. Stripeへ実際に送信したキー集合（実測）

人数変更APIの実行は**1回のみ**。自動再試行0・再送0・2件目の請求作成0。

```text
GET  /subscriptions/<id>   keys=[]
POST /subscriptions/<id>   keys=[
       payment_behavior=pending_if_incomplete,
       proration_behavior=always_invoice,
       items[0][id],
       items[0][quantity]=4 ]
```

期待値と完全一致。送信禁止キーはすべて不送信を実測した
（`price` / `coupon` / `promotion_code` / `cancel_at_period_end` /
`billing_cycle_anchor` / `refund` / `customer` / `metadata` /
ブラウザ指定のStripe ID＝いずれも0件）。

API応答は `applied=false`・3→5・追加2席・26,000→32,000円で、
応答本文にStripe識別子を含まないことを実測した。

### 4-3. 支払い前の状態（仕様どおり・差異なし）

| 項目 | 実測 | 期待 |
|---|---|---|
| 自社DB `license_count` | 3 | 3のまま |
| Stripe 確定quantity | 2 | 旧数量のまま |
| Stripe `pending_update` | あり（保留中の数量=4） | あり |
| 変更台帳 | `increase` / `pending_payment` / 3→5 | pending |
| 追加席の利用 | 4人目の招待は409 `SEAT_LIMIT_REACHED` | 利用不可 |

日割り請求額（Stripeが計算）：**6,000円**
内訳 −6,000円（未使用分の戻し）＋12,000円（新数量ぶん）。請求書は `open`（未払い）。

**自社では日割り計算を行っていない**ことを、送信キーと応答から確認した。

---

## 5. 検出した実装差異（No-Go要因）

### 5-1. 現象

増加リクエストの直後、Stripeが自動で次の順にイベントを送ってきた。

```text
1. customer.subscription.updated   → skipped（人数を動かさない・正しい）
2. invoice.payment_failed          → processed
```

本人認証が必要な支払い方法では、認証が終わるまで支払いが完了しないため、
Stripeはこの時点で `invoice.payment_failed` を送る。
製品はこれを支払い失敗として扱い、変更台帳を `failed`（決着済み）へ移して手続きを閉じた。

一方、**Stripe側の手続きはまだ生きている**。

* `pending_update` … あり（期限は要求から約23時間後）
* 日割り請求書 … `open`（未払い・認証を終えれば支払える）

### 5-2. 何が問題か

この状態でお客様が本人認証を完了して6,000円を支払うと、

* Stripe側 … 支払い成立、追加席quantityが 2→4 へ確定
* 自社側 … `invoice.paid` を受け取っても契約人数を反映**できない**

反映できない理由は、支払い成功の突き合わせが
「台帳が `pending_payment` であること」を条件にしているため。

```text
findPendingByInvoice() … WHERE ... AND status = 'pending_payment'
findPendingIncrease()  … WHERE ... AND status = 'pending_payment'
                         （pending_update_applied の予備経路も同条件）
```

台帳は既に `failed` なのでどちらも該当なしとなり、`license_count` は3のまま。
結果として「6,000円を支払い、Stripeは5名分なのに、製品上は3名のまま使えない」
という食い違いが残る。

この結論は、実測した台帳の状態（`failed`）と製品コードの突き合わせ条件から導いた。
**実際の支払いは実行していない**（実行するとお客様に不利益な状態を作るため）。

### 5-3. 本人認証に固有の問題ではない

`invoice.payment_failed` は、カードの一時的な拒否・残高不足でも届く。
その後にお客様が同じ請求書を支払った場合も、まったく同じ理由で反映されない。
本人認証が必要な支払い方法は、この経路を再現しやすくしただけである。

### 5-4. なぜ既存テストで見つからなかったか

受入テスト（`seat-changes.mjs` 302件）のStripeスタブは、
「支払い失敗」と「支払い成功」を別々のシナリオとして送っており、
実機のような「失敗 → あとで同じ請求書が成功」という順序を再現していない。
実際のStripeは、認証待ち・再試行のたびにこの順序を作る。

WEB-SALES-6S で3差異が見つかったのと同じ性質の、**実機でしか出ない差異**である。

### 5-5. 併せて判明した不足

製品には、お客様が本人認証や再支払いを完了するための導線が**存在しない**。
`hosted_invoice_url`・PaymentIntentの認証継続・`client_secret` の処理・
未払い請求書の支払い導線は、いずれも実装されていない（コード全体で該当0件）。
支払いを完了する手段が無いため、差異5-1を直したとしても、
お客様は自力で人数変更を完了できない。

---

## 6. 実損・影響

* 実損：**0円**（test modeのみ。未払いの請求書は決済していない）
* 本番への変更：**0件**（本番VPS・本番DB・本番migration・本番Webhook登録・
  本番Stripe設定・XServer・DNS・Website・SMTP実送信のいずれも実施なし）
* 製品コードの変更：**0件**（`feature/web-sales-7-seat-changes` HEAD 537f175 のまま）
* 秘密値の露出：0件（Stripe ID・Webhook secret・認証URL・請求書URL・
  PaymentIntent情報は本文書へ記録していない）
* 決済戻り先は実行プロセスの環境変数だけで localhost へ上書きし、`.env` は未変更

---

## 7. 後片付け

本文書の記録後に実施した内容は第8節に追記する。
対象は今回作成した Test Clock・Customer・Subscription と未払いの日割り請求書、
隔離SQLite・一時スクリプト・一時ログ・一時Webhook情報。
既存のCustomer・Subscription・Product・Price、および
過去工程の Portal Configuration には触れない。

---

## 8. 申し送り（WEB-SALES-7R へ）

1. **必須A**：同じ請求書について `invoice.payment_failed` の後に `invoice.paid` が
   届いた場合も、署名検証済みの成功として反映できるようにする。
   突き合わせは会社・Subscription・Invoice・台帳・変更前人数・変更後人数・
   契約状態active・Stripeの確定quantity一致・支払い済み・mode一致を
   すべて確認したうえで行い、再送では二重反映しないこと。
   `failed` を無条件に検索対象へ加えるだけの修正にしない。
2. **必須B**：`invoice.payment_failed` を即時の終端にしない。
   Stripeの `pending_update` と未払い請求書が有効な間は
   「支払い未完了・本人認証待ち」として扱い、終端は `pending_update_expired` とする。
   既存statusで安全に表現できない場合は最小限のmigrationを検討する
   （意味の異なる状態を `failed` へ押し込めない）。
3. **必須C**：未払い請求書または `pending_update` が生きている間は、
   新しい増加申請を作成させない（会社ごとに日割り請求書が複数作られないこと）。
4. **必須D**：本人認証・再支払いの正式導線を用意する。
   Stripe公式ドキュメントで確認した結果、推奨は**オンライン請求書ページ
   （`hosted_invoice_url`）へ遷移する方式**。Stripeがホストする画面で
   3DSの確認モーダルまで完結し、自前でカード情報や `client_secret` を扱わずに済む。
   Customer Portal には「未払い請求書を支払う」機能の設定項目が無いため、
   Portalだけに委ねない。
   URLを返す場合は、会社管理者・運営者だけ／自社の請求書だけ／
   Stripe公式HTTPSホストだけ／`Cache-Control: no-store`／
   ログ・監査・consoleへ出さない／期限切れは開かない／
   支払い済みなら導線を出さない、を満たすこと。
   なお請求書URLは期日から30日で期限切れになり、API取得時点から最低10日間は
   有効であることがStripeの仕様として保証されている。都度APIから取り直す設計にする。
5. **必須E**：`/settings` の契約・ライセンス画面で
   「支払い待ち」「本人認証が必要」「支払い失敗・再操作可能」「反映待ち」
   「期限切れ」「変更完了」を区別する。
   Stripe側でまだ支払えるのに「支払い失敗」と表示する矛盾を作らない。
6. **回帰テスト**：実機で観測した順序
   （増加申請 → `customer.subscription.updated` → `invoice.payment_failed`
   → 同じInvoiceの `invoice.paid` → `license_count` が1回だけ新人数へ）
   を受入テストへ追加する。別Invoice・別会社・別Subscription・quantity不一致・
   再送・`pending_update_expired` 後のpaidの扱いも併せて確認する。
   既存2095件は削除・skip化・条件緩和をしない。
7. 修正後、WEB-SALES-7S をやり直す。今回のテストデータは修正前の状態が
   混在するため再検証には流用しない。
8. WEB-SALES-4S／5CS／6S／6RS の実機review文書が存在しないことを確認した。
   本文書は、同じ欠落を繰り返さないために作成している。
