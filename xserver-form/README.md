# Smart Labo Works — 問い合わせフォーム送信基盤(XServer/PHP)

`WEBSITE/contact.html`から送信される問い合わせフォームを処理するバックエンドです。
ホームページ本体(`WEBSITE/`)はGitHub Pagesで公開しますが、このディレクトリは
**GitHub Pagesの公開対象外**（`.github/workflows/pages.yml`は`WEBSITE/`のみを
デプロイするため）であり、XServer側へ手動でアップロードして運用します。

## デプロイ構成

```
smartlaboworks.com / www.smartlaboworks.com  → GitHub Pages(ホームページ本体)
form.smartlaboworks.com                       → XServer(このディレクトリ)
```

`form.smartlaboworks.com`はXServerのサーバーパネルで追加するサブドメインです
(名称は仮案。CEOのご判断で変更可)。

## メールアドレス構成(2026-07-14 CEO承認)

| アドレス | 役割 |
|---|---|
| `info@smartlaboworks.com` | 代表メール。送信処理では使用しない |
| `contact@smartlaboworks.com` | 問い合わせフォーム送信用(推奨構成)。SMTP認証アカウント・管理者通知宛先・自動返信送信元すべてに使用 |
| `noreply@smartlaboworks.com`(将来) | 自動返信専用。作成後は`config.php`の`auto_reply_from`のみ変更すれば切替可能(コード変更不要) |

## XServerへの配置手順

1. XServerのサーバーパネルで `form.smartlaboworks.com` サブドメインを追加する
2. サブドメインに無料独自SSL(Let's Encrypt)を発行する
3. XServerのメールアカウント管理で `contact@smartlaboworks.com` を作成する
4. `public/` の中身一式を、サブドメインのドキュメントルート
   (`.../form.smartlaboworks.com/public_html/`)へアップロードする
5. `private/config.php.sample` をコピーして `private/config.php` を作成し、
   ドキュメントルートの**1つ上の階層**(`.../form.smartlaboworks.com/`直下、
   `public_html`と同じ階層)へアップロードする。**public_html配下には置かないこと。**
6. `config.php` に実際の値を設定する(SMTP接続情報・`admin_notify_to`・`mail_from`・
   `auto_reply_from`にはいずれも`contact@smartlaboworks.com`を設定するのが推奨構成。
   CSRF署名鍵など。値の入手方法は各ファイルのコメントを参照)
7. `private/` 配下(`config.php`・SQLiteファイル・ログファイル)にPHPの書き込み権限が
   あることを確認する

CORS許可オリジンの一覧(`smartlaboworks.com`等)は秘密情報ではないため、
`config.php`ではなく`public/lib/settings.php`(Git管理対象)で管理する。
正式ドメインへの切替が完了したら、暫定URL(GitHub Pages側)の行を削除すること。

## ローカルでの動作確認

`php -S`の組み込みサーバーで一部ロジックを検証できますが、SMTP送信・reCAPTCHA通信は
実際のネットワーク環境が必要です。`private/config.php`をローカル用の値で作成し、
`php -S localhost:8080 -t public` 等で起動して確認してください。

## reCAPTCHA v3について

CEO指示により、ホームページ正式公開直前まで `config.php` の `recaptcha_enabled` は
`false` のまま運用します。この間は honeypot・送信速度トラップ・CSRF・レート制限の
4層のみでbot対策を行います。正式公開直前に、Google reCAPTCHA管理画面でサイトキー・
シークレットキーを発行し、`recaptcha_enabled` を `true` に切り替えてください。
