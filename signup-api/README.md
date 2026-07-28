# signup-api — セルフ申し込みAPI（SALES-1）

Smart Labo Works のセルフ申し込み画面（`website-v2/signup.html`）が送信する `POST /api/signup` の実装です。

> ## ⚠️ このAPIはまだ本番へ配置していません
> SALES-1の時点では **入力を検証して結果を返すだけ** で、次のいずれも行いません。
>
> - DBへの保存（会社・管理者・パスワードのいずれも保存しない）
> - メール送信
> - Stripe決済
> - アカウントの自動作成
>
> これらは SALES-2 以降で実装します。

---

## 1. 置き場所についての注意（未確定事項）

**この実装をどのサーバー・どのリポジトリで動かすかは、まだ代表判断が済んでいません。**
[PROJECT_BIBLE/15_Stripe_Sales_Billing_Design.md](../PROJECT_BIBLE/15_Stripe_Sales_Billing_Design.md) の代表判断事項 **9-8** が該当します。

このリポジトリはGitHub Pagesで静的サイトを配信しており、サーバー側の処理を動かせません。そのため SALES-1 では、**このリポジトリで既に運用設計が済んでいる `contact-api/`（XServer上のPHP）と同じ方式で仮実装**しています。

移設のしやすさは意図的に確保してあります。

- **何も永続化していない** ため、移設時に移すデータがない
- 画面が知っているのは `/api/signup` と `/api/signup-csrf-token` という**URLだけ**（`.htaccess` で実ファイルへ割り当てているため、PHP以外へ載せ替えてもURLを変えずに済む）
- 入力規則・料金・レスポンス形式はいずれも1ファイルに集約している

Node（`smartlabo-platform`）へ移す判断になった場合も、[lib/validate.php](public/lib/validate.php) の規則と [lib/response.php](public/lib/response.php) の形式をそのまま移植すれば、画面側の変更は不要です。

---

## 2. フォルダ構成

```
signup-api/
├── public/                     ← 公開ディレクトリ（ドキュメントルート）へ置く
│   ├── .htaccess               HTTPS強制・URL割り当て・lib/への直接アクセス禁止
│   ├── signup.php              POST /api/signup
│   ├── csrf-token.php          GET  /api/signup-csrf-token
│   └── lib/
│       ├── config.php          設定の読み込み・PHPの隠蔽設定
│       ├── security.php        Origin確認・CSRF・honeypot・時間差・送信間隔制限
│       ├── validate.php        入力の検証と整形（★入力の正）
│       ├── pricing.php         料金の正規値と見積り計算（★金額の正）
│       └── response.php        API共通レスポンス
├── private/                    ← 公開ディレクトリの「外」へ置く
│   ├── .htaccess               誤配置時の保険として全拒否
│   └── signup-config.example.php   設定の雛形（実ファイルはGit管理外）
└── tests/
    ├── test-validate.php       入力検証・料金計算のユニットテスト（56件）
    └── test-http.php           実際にHTTPで叩く統合テスト（33件）
```

---

## 3. テストの実行

PHPさえあれば動きます。Composer・PHPUnitは使いません。

```bash
php signup-api/tests/test-validate.php
```

```bash
php signup-api/tests/test-http.php
```

`test-http.php` はPHPの内蔵サーバーを一時的に起動し、テスト用の設定ファイルを一時ディレクトリへ作って実行し、終了時に必ず削除します。`signup-api/private/` には触れません。

---

## 4. API仕様

### `POST /api/signup`

**リクエスト**（JSON または `application/x-www-form-urlencoded`）

| 項目 | 必須 | 制限 |
|---|---|---|
| `company_name` | ● | 100文字 |
| `company_kana` | ● | 100文字・全角カタカナ |
| `postal_code` | ● | 7桁（ハイフン有無どちらも可） |
| `address` | ● | 200文字 |
| `company_tel` | ● | 数字10〜11桁 |
| `contact_email` | ● | 254文字・メール形式 |
| `admin_name` | ● | 100文字 |
| `admin_email` | ● | 254文字・メール形式 |
| `password` | ● | 10〜128文字・4種のうち3種以上 |
| `password_confirm` | ● | `password` と一致 |
| `additional_accounts` | | 0〜999の整数（省略時0） |
| `csrf_token` | ● | `/api/signup-csrf-token` で取得 |
| `form_ts` | | 画面表示時刻（ミリ秒） |
| `website` | | honeypot。値が入っていると拒否 |

**レスポンス**（常にJSON）

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

| ステータス | `result` | 意味 |
|---|---|---|
| 200 | `ok` | 検証を通過（**保存はしていない**） |
| 400 | `rejected` | honeypot／表示直後の送信 |
| 403 | `rejected` | 許可外オリジン／CSRFトークン不正・欠落 |
| 405 | `rejected` | POST以外 |
| 413 | `rejected` | 本文が大きすぎる |
| 415 | `rejected` | 未対応のContent-Type |
| 422 | `invalid` | 入力エラー（`errors` に項目ごとの理由コード） |
| 429 | `too_many` | 送信間隔制限 |
| 500 | `failed` | 設定不足（内容は応答に出さずログへ） |

理由コード: `required` / `too_long` / `too_short` / `invalid` / `mismatch` / `weak` / `out_of_range`

**この形式はSALES-2以降で項目が増えても外側を変えずに済むようにしてあります。** 画面側は `ok` / `result` / `errors` だけを見ればよく、増える情報は `data` の中にだけ足します（決済のURLは `data.next` へ入る想定）。

---

## 5. 配置手順（実施は代表承認後・SALES-2以降）

1. `public/` の中身をドキュメントルートの `api/` へ置く
2. `private/signup-config.example.php` をコピーして `private/signup-config.php` を作り、値を入れる
   - `allowed_origins` … サイトのオリジン
   - `csrf_secret` / `ip_hash_secret` … それぞれ別の値。生成例 `php -r "echo bin2hex(random_bytes(32));"`
3. `private/` は**ドキュメントルートの外**へ置く
4. `contact-api/` と同じサーバーへ置く場合は `.htaccess` をマージする（`RewriteEngine On` は1回でよい）

**鍵・設定ファイルはGitへコミットしないこと。** `signup-api/.gitignore` で除外しています。

---

## 6. セキュリティ上の約束

- **カード情報はこのAPIで一切扱わない。** Stripe Checkout（Stripeがホストする画面）を採用するため、自社サーバーはカード番号に触れません（[15_Stripe_Sales_Billing_Design.md](../PROJECT_BIBLE/15_Stripe_Sales_Billing_Design.md) 3-3）。
- **パスワードは応答にもログにも出さない。** 検証結果の `data` にも含めていません。
- **生のIPアドレスを保存しない。** 送信間隔制限にはHMACでハッシュ化した値だけを使います。
- **金額は画面からの申告を信用しない。** 常に `lib/pricing.php` の正規値から計算し直します。
- PHPのエラーは画面へ出さず、サーバーのエラーログにのみ記録します。

---

## 7. 料金を変更するとき

次の5か所をすべて同時に更新してください。1か所でも漏れると `node docs/reviews/tools/check-prices.js` が失敗します。

1. `PROJECT_BIBLE/14_Sales_And_Billing_Policy.md`
2. `docs/reviews/tools/check-prices.js` の `CANONICAL`
3. `website-v2/index.html`
4. `website-v2/pricing.html`
5. `signup-api/public/lib/pricing.php`

（`website-v2/apply.html` と `website-v2/signup.html` の表記も検証対象です）
