# contact-api — お問い合わせフォームの受け口（XServer / PHP）

`website-v2/contact.html` のフォームから送信された内容を受け取り、指定のメールアドレスへ通知する。

**このディレクトリは GitHub Pages の公開対象に含まれない。** Pages が配信するのは `WEBSITE/` だけであり、
`contact-api/` は XServer へ手動で配置して運用する。

> `website-v2/` の中にPHPを置いてはいけない。GitHub Pages はPHPを実行できず、
> `.php` をそのままテキストとして配信するため、ソースが丸ごと公開されてしまう。

---

## 1. いちばん重要な前提：同一オリジンにするには移設が必要

現在の公開構成は次のようになっている（2026-07-28 実測）。

| ホスト名 | 向き先 | PHP |
|---|---|---|
| `smartlaboworks.com` | **GitHub Pages**（185.199.108–111.153 / `Server: GitHub.com`） | **実行できない** |
| `www.smartlaboworks.com` | 同上（apexへ転送） | 実行できない |
| `form.smartlaboworks.com` | **XServer**（85.131.213.188 / `Server: nginx`） | 実行できる |

`https://smartlaboworks.com/api/contact.php` を成立させるには、
**apexドメインの向き先を XServer へ移す**必要がある（＝サイト本体をGitHub Pagesから移設する）。
これは DNS・CNAME・`pages.yml` に関わる判断のため、実施していない。

そのため本実装は、**どちらの構成でも動くように**してある。

| 構成 | フォーム側の設定 | API側の設定 |
|---|---|---|
| **A. 同一オリジン**（推奨・要移設） | `action="/api/contact.php"`（現状のまま） | `allowed_origins` に `https://smartlaboworks.com` |
| **B. 別オリジン**（現構成のまま） | `action` と `data-endpoint` を `https://form.smartlaboworks.com/contact.php` へ変更 | 同上（CORSヘッダーは実装済み） |

現在は **A を既定値**にしている（代表方針が同一オリジン優先のため）。

---

## 2. 配置

```
XServer のドメイン領域
├── public_html/                 ← ドキュメントルート
│   ├── （website-v2 の中身：構成Aの場合）
│   └── api/                     ← contact-api/public/ の中身を置く
│       ├── .htaccess
│       ├── contact.php
│       ├── csrf-token.php
│       └── lib/
└── private/                     ← ドキュメントルートの「外」
    ├── .htaccess
    ├── contact-config.php       ← 実際の設定（Git管理外）
    └── ratelimit/               ← 送信間隔の記録（自動生成）
```

`lib/` の階層は `.htaccess` で直接アクセスを禁止している。
`private/` は本来Webから到達しない場所に置くが、誤配置に備えて全拒否の `.htaccess` も入れてある。

### 手順

1. XServer のサーバーパネルで、対象ドメインの **PHP 8.0 以上**を選択する
2. `contact-api/public/` の中身を `public_html/api/` へアップロードする
3. `contact-api/private/` の中身を、ドキュメントルートの1つ上へアップロードする
4. `contact-config.example.php` を `contact-config.php` としてコピーし、値を入れる
5. 鍵を生成して設定する
   ```bash
   php -r "echo bin2hex(random_bytes(32));"   # csrf_secret 用
   php -r "echo bin2hex(random_bytes(32));"   # ip_hash_secret 用
   ```
6. `mode` を `test` のままにして、フォームから送信し、成功表示が出ることを確認する
7. 問題なければ `mode` を `live` に変更し、実際にメールが届くことを確認する
8. `private/` のパーミッションを 700、`contact-config.php` を 600 にする

---

## 3. 設定と秘密情報

受信先メールアドレス・送信元アドレス・署名鍵は `private/contact-config.php` にのみ書く。
このファイルは `contact-api/.gitignore` で **Git 管理対象外**にしてある。
Git に入るのは `contact-config.example.php`（値が空の雛形）だけ。

**公開HTML・CSS・JavaScript には受信先メールアドレスを一切書かない。**
`docs/reviews/tools/check-prices.js` が、公開ファイルへのメールアドレス・鍵らしき文字列の混入を毎回検査する。

### .gitignore の影響範囲

追加したのは `contact-api/.gitignore` の1ファイルのみで、
リポジトリ直下の `.gitignore` は変更していない。除外対象は次の2つに限られる。

```
private/contact-config.php   # 実際の設定値
private/ratelimit/           # 実行時に生成される記録
```

他のディレクトリ（`WEBSITE/` `website-v2/` `xserver-form/` など）の Git 管理状態には影響しない。

---

## 4. 送信方式

```
contact.html
   ├─ JavaScript あり → fetch で JSON を POST → JSON で応答
   └─ JavaScript なし → 通常の form POST      → HTML で応答
```

**JavaScript が無くても送信できる。** `<form method="post" action="...">` をそのまま使い、
JavaScript は「その体験を良くするだけ」の上乗せにしてある。
JS が読み込めなくても入力内容が失われることはない。

PHP 側は `Accept` ヘッダーと `X-Requested-With` を見て応答形式を切り替える。

---

## 5. CSRFの扱い（設計判断）

このエンドポイントには**ログインもセッションも無い**。偽装リクエストで起こりうるのは
「問い合わせメールが1通余分に届く」ことであり、利用者になりすまして操作されるものではない。
したがって、ここでのCSRF対策は**自動送信を増やしにくくするための多層防御の一つ**として扱う。

- トークンは `csrf-token.php` が発行する。HMAC署名＋有効期限（2時間）で、サーバー側に状態を持たない。
- 静的HTMLではページ生成時にトークンを埋め込めないため、JavaScript が取得して隠し項目へ入れる。
- **JavaScript 無効時はトークンが無い。** その場合は
  「Origin/Referer が許可一覧と一致」「honeypot 未入力」「時間差」「送信間隔制限」を
  すべて満たしたときにのみ受け付ける。
- トークンを必須にしたい場合は、設定の `require_csrf_always` を `true` にする。
  ただし **JavaScript 無効の環境からは送信できなくなる**。

---

## 6. 送信間隔制限（レート制限）

| 項目 | 内容 |
|---|---|
| 保存場所 | `private/ratelimit/<ハッシュ化したIP>` |
| 保存内容 | **送信時刻（UNIX秒）の一覧のみ。** 問い合わせ内容は保存しない |
| IPの扱い | **生のIPは保存しない。** `ip_hash_secret` を鍵にしたHMACの先頭32文字だけを使う（復元できない） |
| 保存期間 | 既定10分（`rate_limit_window`）。期間を過ぎた時刻は読み込み時に捨てる |
| 自動削除 | 1時間以上更新の無いファイルは、リクエストのたびにまとめて削除する |
| 権限 | ディレクトリ 0700 / ファイル 0600。`private/` はWebから全拒否 |
| 上限 | 既定10分あたり3件（`rate_limit_max`） |

ディレクトリが作れない・書き込めない場合は制限をかけずに通す（他の対策で守る）。

---

## 7. 個人情報の扱い

- **データベースにもファイルにも保存しない。** 指定メールアドレスへ送るだけ。
- メール送信に失敗した場合も、**問い合わせ内容をログへ出さない**。
  サーバーのエラーログに残るのは `[contact] mail send failed` のような固定文言のみ。
- 応答には受信先メールアドレスを含めない。
- 入力内容やエラー内容をURLへ付けない（POSTのみ）。

---

## 8. メール

- 本文はプレーンテキスト。
- `From` は**このサイトのドメインのアドレス**（`from_email`）。利用者のアドレスは使わない。
  利用者のアドレスを From にすると、なりすまし扱いされ SPF/DKIM で落ちる。
- 利用者のアドレスは `Reply-To` に入れる。入れる直前にも改行を取り除き、形式を確認する。
- ヘッダーへ入るすべての値から CR/LF を除去する（ヘッダーインジェクション対策）。
- 件名・表示名は RFC 2047 で符号化する。`mbstring` が無い環境でも動くよう自前の実装を用意している。
- **自動返信は実装していない。** 到達性・なりすまし・文面管理・二重送信の検討が必要なため、
  必要になった時点で別途判断する。

---

## 9. 動作確認

`mode` を `test` にすると、**メールを送らずに**成功・失敗の流れだけを再現できる。
実在の宛先へ誤って送ってしまうことがない。

ローカルでの確認例（PHP標準のサーバーを使う）:

```bash
cd contact-api/public && php -S 127.0.0.1:3010
```

同一オリジン構成を再現する場合は、静的サイトを配信しつつ `/api/` をPHPへ中継する。

---

## 10. 迷惑メール対策を強化する場合

現在は honeypot・時間差・送信間隔制限・Origin確認・CSRF で対応している。
実際に迷惑メールが発生した場合は、`contact.php` の CSRF 検証の直後へ
Turnstile 等の検証を1ブロック追加できる構造にしてある（既存の判定順序を変えずに差し込める）。

---

*最終更新: 2026-07-28 / WEB-V2-7*
