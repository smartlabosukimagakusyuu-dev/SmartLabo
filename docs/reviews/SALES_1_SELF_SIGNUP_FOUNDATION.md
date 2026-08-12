# SALES-1 — セルフ申し込み・会社登録基盤 レビュー記録

- 実施日: 2026-07-29
- ブランチ: `website-v2`（ローカルのみ・未push・masterは無変更）
- 前工程: SALES-0 `e8b3bc8`（Stripe販売・課金設計）
- 範囲: **会社情報 → 管理者情報 → 契約内容確認 までの入力と、サーバー側検証**
- Stripe決済・DB保存・アカウント作成・メール送信は**いずれも未実装**（指示どおり）

---

## 1. 作業開始時の状態

| 項目 | 値 |
|---|---|
| ブランチ | `website-v2` ／ working tree clean |
| HEAD | `e8b3bc8` SALES-0: design Stripe sales, contract and billing specification |
| SALES-0コミット | 存在を確認（6ファイル・501行追加） |
| masterとの差分 | 121ファイル。master `10463d6` は無変更 |
| CURRENT_STATUS | v7.5（Project Bible 8.1・SALES-0反映済み） |

---

## 2. 実装場所の判断（★代表確認が必要）

SALES-0の代表判断事項 **9-8「実装をどのリポジトリ・どのサーバーへ置くか」は未決** のままです。今回はGit指示（`website-v2` ブランチに1コミット）に従う必要があったため、**このリポジトリ内に `signup-api/` として仮実装**しました。

| 採用案 | 理由 |
|---|---|
| **`signup-api/`（PHP・XServer方式）** | このリポジトリで既に運用設計が済んでいる `contact-api/` と同じ方式。`.htaccess`・`private/`・CSRF・送信間隔制限・テストの型がそのまま使え、新しい仕組みを増やさずに済む |

| 代替案 | 不採用の理由 |
|---|---|
| `smartlabo-platform`（Node）へ実装 | SALES-0で本命として推奨した先だが、**別リポジトリのため今回のGit指示（このブランチに1コミット）を満たせない** |
| `smartlabo-works` に同居 | 同上。加えて製品本体と申込基盤の境界が曖昧になる |

**移設コストは意図的に小さくしてあります。** 何も永続化していないため移すデータが無く、画面が知っているのは `/api/signup` というURLだけです（`.htaccess` で実ファイルへ割り当て）。Nodeへ移す判断になっても、検証規則とレスポンス形式を移植すれば画面側の変更は不要です。

---

## 3. 実装内容

### 新規（サーバー側）

| ファイル | 内容 |
|---|---|
| `signup-api/public/signup.php` | `POST /api/signup`。10段階の受け付け判定 → 検証 → 料金再計算 → 200 |
| `signup-api/public/csrf-token.php` | `GET /api/signup-csrf-token` |
| `signup-api/public/lib/validate.php` | **入力の正**。整形・サニタイズ・検証 |
| `signup-api/public/lib/pricing.php` | **金額の正**。正規値と見積り計算 |
| `signup-api/public/lib/response.php` | API共通レスポンス |
| `signup-api/public/lib/security.php` | Origin確認・CSRF・honeypot・時間差・送信間隔制限 |
| `signup-api/public/lib/config.php` | 設定読み込み・PHPエラーの隠蔽 |
| `signup-api/public/.htaccess` ほか | HTTPS強制・URL割り当て・`lib/` 直接アクセス禁止・`private/` 全拒否 |
| `signup-api/private/signup-config.example.php` | 設定の雛形（実ファイルは `.gitignore` で除外） |
| `signup-api/README.md` | API仕様・配置手順・移設方針 |
| `signup-api/tests/test-validate.php` | ユニットテスト 56件 |
| `signup-api/tests/test-http.php` | HTTP統合テスト 33件 |

### 新規（画面）

| ファイル | 内容 |
|---|---|
| `website-v2/signup.html` | 3ステップの申込画面。**noindex・sitemap未掲載・公開ページから未リンク** |
| `website-v2/assets/js/signup.js` | ステップ制御・入力チェック・金額計算・送信 |

### 変更

| ファイル | 内容 |
|---|---|
| `website-v2/assets/css/components.css` | `.wizard-steps` / `.quote` など2ブロックを追加（既存の `.cform` `.field` `.btn` `.card` は再利用。新しいブランドカラーは追加していない） |
| `docs/reviews/tools/check-prices.js` | signup.html を検査対象へ追加。**公開導線に載っていないこと**を機械的に検査する節を新設 |

### 公開導線に繋いでいない理由（★重要）

決済（SALES-2）とアカウント作成（SALES-5）が未実装のため、確認画面の先が存在しません。`apply.html` からリンクすると利用者を行き止まりへ誘導することになるため、次の3点を守り、`check-prices.js` で機械的に検査しています。

1. `signup.html` に `noindex, nofollow` を付ける
2. `sitemap.xml` に載せない
3. 公開9ページのいずれからもリンクしない

`apply.html` は WEB-V2-8 のまま「オンライン申し込みは現在準備中です」を表示し続けます。導線を繋ぐのは SALES-2 以降です。

---

## 4. API仕様

`POST /api/signup` — 詳細は [signup-api/README.md](../../signup-api/README.md) 第4節。

**共通レスポンス形式**（今後項目が増えても外側を変えずに済む構造）

```json
{
  "ok": true,
  "result": "ok",
  "message": "入力内容を確認しました。",
  "data": {
    "stage": "validated",
    "persisted": false,
    "quote": { "monthly_total": 26000, "initial_fee": 10000, "tax_included": false },
    "next": { "step": "payment", "available": false }
  },
  "errors": {},
  "meta": { "api_version": "1", "endpoint": "signup", "request_id": "…" }
}
```

画面側は `ok` / `result` / `errors` だけを見ます。SALES-2で増える情報（Stripe Checkout のURL等）は `data.next` の中へ足すだけで済みます。`persisted: false` を応答に含め、**保存していないことをAPI自身が明示**しています。

ステータス: 200 `ok` ／ 400・403・405・413・415 `rejected` ／ 422 `invalid` ／ 429 `too_many` ／ 500 `failed`

---

## 5. バリデーション

**サーバー側を正とし、画面側は往復を減らすための同一規則の写し**です。

| 項目 | 規則 |
|---|---|
| 会社名 / 住所 / 氏名 | 必須・100〜200文字・HTMLタグ除去・改行除去 |
| 会社名（カナ） | 必須・全角カタカナと長音・空白のみ |
| 郵便番号 | 必須・7桁。ハイフン有無どちらも受け、`123-4567` へ整形 |
| 電話番号 | 必須・数字10〜11桁。ハイフン・括弧・空白は許容 |
| メールアドレス | 必須・254文字・形式確認・**改行混入を拒否**（ヘッダー汚染の防止） |
| パスワード | 必須・10〜128文字・英大/英小/数字/記号のうち**3種以上**・よくある語やメールのローカル部を含まない |
| パスワード（確認） | 一致（`hash_equals` で比較） |
| 追加アカウント数 | 0〜999の整数。全角数字も受け付ける・空欄は0 |

理由コード: `required` / `too_long` / `too_short` / `invalid` / `mismatch` / `weak` / `out_of_range`

**パスワード基準は仮置き**です（SALES-2で最終確定 → 第9節）。

### セキュリティ

- カード情報の入力欄は置いていない（Stripe Checkout採用のため、最終的にも自サイトには作らない）。`check-prices.js` で混入を検査
- パスワードは応答にもログにも出さない（検証結果の `data` にも含めない）
- 金額は画面からの申告を使わず、常にサーバー側の正規値から再計算
- 生のIPは保存せず、送信間隔制限にはHMACハッシュのみ使用
- CSRFは `require_csrf_always: true`（申込画面はJavaScript前提のため常時必須にできる）
- UTF-8として不正な入力は「未入力」と誤判定される前に422で止める
- `mbstring` の有無で入力の受け付け方が変わらないよう、全角→半角変換に自前の変換表を用意（この環境のPHPには `mbstring` が無く、実際に1件のテストが落ちて発見した）

---

## 6. テスト結果

### ユニットテスト（`php signup-api/tests/test-validate.php`）

```
実行 56件 / 成功 56件 / 失敗 0件
[OK] 入力検証・料金計算のテストはすべて成功しました
```

正常系5・必須9・形式8・文字数3・パスワード8・人数4・サニタイズ4・規約同意3・料金7 ほか。

### HTTP統合テスト（`php signup-api/tests/test-http.php`）

```
実行 33件 / 成功 33件 / 失敗 0件
[OK] HTTP統合テストはすべて成功しました
```

PHP内蔵サーバーを起動し実際に叩いて確認。正常系（200・`persisted:false`・サーバー側の金額計算）、**改ざん耐性**（画面から `monthly_total: 1` を送ってもサーバー計算が優先される）、異常系（422と理由コード）、拒否条件（GET 405・許可外オリジン403・CSRF無し403・偽造403・honeypot 400・表示直後400・Content-Type 415・Origin/Referer無し403）、応答ヘッダー、パスワード・鍵が応答に漏れないこと。

### 料金整合（`node docs/reviews/tools/check-prices.js`）

```
正規値: 初期設定費 10,000円 / 月額 20,000円 / 追加 3,000円（税抜）
[OK] 料金・共通部品ともに整合しています
```

signup.html を検査対象へ追加。noindex・sitemap未掲載・公開ページからの未リンク・カード欄不在も併せて検査。

### 法務ページ（`node docs/reviews/tools/check-legal.js`）

```
[OK] 法務ページの本文は Version1 と完全に一致しています
```

### 画面（Puppeteer 実測）

| 幅 | 横スクロール | h1 | 最小ボタン高さ |
|---|---|---|---|
| 375px | なし（0px） | 1 | 53.2px |
| 768px | なし（0px） | 1 | 53.2px |
| 1024px | なし（0px） | 1 | 53.2px |
| 1440px | なし（0px） | 1 | 53.2px |

動作確認（1440px）:

- 未入力で「次へ」→ ステップ1に留まり、6項目にエラー表示・`aria-invalid` 6件
- 会社情報を正しく入力 → ステップ2へ進み、進捗表示も2へ
- `password123` → 「3種類以上を含む…」で差し戻し（ステップ2に留まる）
- 妥当なパスワードでステップ3へ。追加4名 → 追加合計 12,000円・月額合計 32,000円（正規値と一致）
- 「戻る」で入力内容が保持される

**JavaScript無効時**: 入力欄14個がすべて表示され、3セクションが縦に並び、`action="/api/signup"` へ通常POSTされる。月額合計も初期値20,000円が読める。

**Console Error**: 全10ページで**新規のエラーは0件**。ただし `signup.html` と `contact.html` は、APIが未配置のためCSRFトークン取得が404になり**各1件**記録されます（引継ぎ文書の既知課題4と同じ現象で、API配置後は0件になります）。

> 検証中に気づいた点として、PHPの内蔵サーバーは拡張子の無いパスに `index.html` を200で返すため、`/api/signup-csrf-token` が「成功したように見えて」エラーが出ませんでした。正しく404を返すサーバー（`python -m http.server`）で測り直し、上記の1件を確認しています。

### 実装中に見つけて直した2点

1. `mbstring` が無い環境で全角数字が半角へ変換されず、ユニットテストが1件失敗 → 拡張の有無に依存しない変換表を追加
2. 1ステップ表示時に、隠れた前ステップぶんの余白と区切り線だけが残る／初期表示で見出しにフォーカスリングが出る → CSSの区切り線をJavaScript無効時限定にし、フォーカス移動は利用者操作時のみに変更

---

## 7. SSOT更新

| ファイル | 内容 |
|---|---|
| `PROJECT_BIBLE/CURRENT_STATUS.md` | SALES-1の実施内容・未実装範囲・次工程を追記 |
| 本ファイル | 工程の記録 |

`14_Sales_And_Billing_Policy.md` / `15_Stripe_Sales_Billing_Design.md` は**変更していません**。SALES-1は設計どおりに実装しただけで、方針・設計の変更が無いためです。

---

## 8. 今回実装していないもの（指示どおり）

Stripe ／ Webhook ／ DB保存 ／ 会社作成 ／ メール送信 ／ キャンペーン ／ 紹介コード ／ AI初期設定ウィザード

加えて、キャンペーンコード・紹介コードの**入力欄も置いていません**。SALES-3・SALES-4で扱う項目であり、入力できても何も起きない欄を先に見せると誤解を招くためです。

---

## 9. 代表判断が必要な事項

| # | 論点 | 第一案 |
|---|---|---|
| 1 | **実装場所**（SALES-0の9-8）。今回は `signup-api/`（PHP）へ仮実装 | SALES-2着手前に確定。`smartlabo-platform`（Node）へ移すなら今が最も安い（保存データが無いため） |
| 2 | パスワード基準（現在: 10文字以上・3種以上） | このまま採用。強化するなら12文字以上・漏洩パスワード照合の追加 |
| 3 | 利用規約・プライバシーポリシーへの同意をどの段階で取るか | 決済の直前（SALES-2）。検証コードは同意項目が送られた場合に必須化する形で先に用意済み |
| 4 | 申込途中の入力内容を保存するか（かご落ち対策） | 保存しない（個人情報を持たない方が安全）。必要なら SALES-2 で判断 |
| 5 | 管理者メールアドレスの到達確認（メール認証）の要否 | SALES-2でStripeの決済完了メールと合わせて設計 |

---

## 10. SALES-2 への引き継ぎ

1. **`signup.php` の「10. 料金の再計算 → 応答」の位置に、Stripe Checkout Session の作成が入る。** その他の受け付け判定（1〜9）はそのまま使える
2. レスポンスの `data.next` に Checkout のURLを入れ、`available: true` にする。画面側は `data.next` を見て遷移するだけでよい
3. `data.persisted` を `true` に変え、契約レコードの作成（[15_Stripe_Sales_Billing_Design.md](../../PROJECT_BIBLE/15_Stripe_Sales_Billing_Design.md) 第5章の `contracts`）を行う
4. `check-prices.js` 第12節を「公開してよい」条件へ書き換え、`signup.html` の noindex 解除・sitemap 追加・`apply.html` からのリンク接続を行う
5. `apply.html` の「翌々月以降は毎月1日」を「**翌月1日から毎月1日**」へ修正（SALES-0の代表判断 9-5）
6. `signup.html` の「お支払いのお手続きは準備中です」ブロックを削除する
7. キャンペーンコード・紹介コードの入力欄を追加（SALES-3・SALES-4）
8. **公開前に必須**: 特定商取引法に基づく表記ページ・キャンペーン規約の整備（15番7章）

---

## 11. Git

- 1コミット。push・masterへの操作なし
- 秘密情報のコミットなし（`signup-api/.gitignore` で設定ファイルと送信間隔制限の記録を除外）
- `git diff --check` クリーン

---

*作成: Claude Code / SALES-1（2026-07-29）*
