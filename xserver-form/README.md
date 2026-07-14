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

`form.smartlaboworks.com`はXServerのサーバーパネルで追加するサブドメインです。
**2026-07-14時点で作成済み・無料SSL有効化済み(CEO側インフラ準備完了)。**

## メールアドレス構成

| アドレス | 状態 | 役割 |
|---|---|---|
| `info@smartlaboworks.com` | **作成済み** | SMTP認証アカウント・管理者通知宛先・送信元アドレスのすべてに使用する |

送信元アドレスは`config.php`の`smtp_user`(認証アカウント)をそのまま使用する設計のため、
将来メールアドレスを変更する場合は`smtp_user`/`smtp_pass`/`admin_email`の値を
書き換えるだけでよい(コード変更不要)。

## XServerへの配置手順

**CEO側で完了済み**：株式会社スマートラボ設立、XServer契約、`smartlaboworks.com`設定、
`form.smartlaboworks.com`サブドメイン作成、無料SSL有効化、`info@smartlaboworks.com`作成。

1. （完了済み）`form.smartlaboworks.com`サブドメインの追加・SSL有効化
2. （完了済み）`info@smartlaboworks.com`メールアカウントの作成
3. XServerのサーバーパネルで、`form.smartlaboworks.com`のPHPバージョンを8.0以上に
   設定する(ドメインごとに選択可能)
4. `public/` の中身一式を、サブドメインのドキュメントルート
   (`.../form.smartlaboworks.com/public_html/`)へアップロードする
5. `private/config.php.example` をコピーして `private/config.php` を作成し、
   ドキュメントルートの**1つ上の階層**(`.../form.smartlaboworks.com/`直下、
   `public_html`と同じ階層)へアップロードする。**public_html配下には置かないこと。**
6. `config.php` に実際の値を設定する(SMTP接続情報・`admin_email`・CSRF署名鍵。
   値の入手方法は`config.php.example`内のコメントを参照)
7. `private/` 配下(`config.php`・SQLiteファイル・ログファイル)にPHPの書き込み権限が
   あることを確認する

CORS許可オリジンの一覧(`smartlaboworks.com`等)は秘密情報ではないため、
`config.php`ではなく`public/lib/settings.php`(Git管理対象)で管理する。
正式ドメインへの切替が完了したら、暫定URL(GitHub Pages側)の行を削除すること。

## ローカルでの動作確認

`php -S`の組み込みサーバーで一部ロジックを検証できますが、SMTP送信・reCAPTCHA通信は
実際のネットワーク環境が必要です。`private/config.php`をローカル用の値で作成し、
`php -S localhost:8080 -t public` 等で起動して確認してください。

## reCAPTCHA(開発モード/本番モード)について

キー未設定の状態(`recaptcha_enabled: false`)でも問い合わせフォーム自体は動作する
「開発モード」として実装しています。この間は honeypot・送信速度トラップ・CSRF・
レート制限の4層のみでbot対策を行います。

ホームページ正式公開直前に、以下3箇所へキーを設定すると「本番モード」に切り替わります。

1. `WEBSITE/contact.html` の `<script src="https://www.google.com/recaptcha/api.js?render=...">` のコメントを解除しサイトキーを設定
2. `WEBSITE/js/pages.js` の `RECAPTCHA_SITE_KEY` に同じサイトキーを設定
3. `private/config.php` の `recaptcha_enabled` を `true`、`recaptcha_secret` にシークレットキーを設定

Google reCAPTCHA v3ではなくEnterpriseを使う場合は、`public/lib/recaptcha.php`の
検証先だけを差し替えれば、上記の設定構造・呼び出し元は変更不要です。

## バックアップ

| 対象 | 方針 |
|---|---|
| `public/`配下のコード | Gitで管理済み(SmartLabo repo)。XServerへは常にリポジトリの内容をそのままアップロードする運用とし、XServer上で直接編集しない |
| `private/config.php` | Gitでは管理しない秘密情報。XServerへアップロード後、手元の安全な場所(パスワードマネージャー等)に控えを保管する。紛失時は`config.php.example`から再作成可能(値の再取得は必要) |
| `private/rate_limit.sqlite` | 再作成可能な一時データ(レート制限の状態のみ)。バックアップ不要。消失してもレート制限がリセットされるだけで実害はない |
| `private/contact.log` | 運用ログ。個人情報を含まないため、定期的にXServerのファイルマネージャー等でダウンロード・保管する運用を推奨(頻度は運用開始後にCEOと相談) |

## ロールバック手順

**ホームページ本体(GitHub Pages側)**：問題のあるコミットを`git revert`して`master`へpushすれば、GitHub Actionsが自動的に前の状態を再デプロイする(`.github/workflows/pages.yml`が`WEBSITE/**`の変更をトリガーに自動実行)。手動操作は不要。

**問い合わせフォーム(XServer側)**：
1. `public/`配下に問題が見つかった場合、直前に正常動作していたGitのコミット時点のファイル一式を、XServerへ再アップロードする(上書き)
2. `private/config.php`はGit管理外のため、設定変更前の値を控えておき、必要に応じて手動で戻す
3. 緊急時、`private/config.php`の`smtp_pass`を空にする、または`.htaccess`で`contact.php`へのアクセスを一時的に遮断することで、フォーム機能のみを即座に停止できる(ホームページ本体には影響しない)
