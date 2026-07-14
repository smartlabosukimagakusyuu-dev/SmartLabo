# 61. Release Checklist — 本番公開前チェックリスト

このドキュメントは、Smart Labo Worksホームページ（`WEBSITE/` + `xserver-form/`）を
正式公開する際に使用する、再利用可能なチェックリストのテンプレートです。
今回は「Release Candidate 2」として、直近のRelease Candidate 1（コミット`76a9767`）
時点の状況を反映して記入しています。将来の大型リリース（v2.0公開等）でも、
このテンプレートをそのまま使い回してください。

---

## 0. バージョン番号・タグの方針（要CEO確認）

現在、PROJECT_BIBLEの「Homepage Version」（`CURRENT_STATUS.md`参照、現在3.1）は、
社内での変更を追跡する**内部バージョン**です。これとは別に、対外的な正式公開の
節目には、**独立した公開バージョン番号**を付けることを提案します。

| 項目 | 提案 |
|---|---|
| 公開バージョン番号 | `v1.0.0`（セマンティックバージョニング。ホームページの正式初回公開として） |
| PROJECT_BIBLE Homepage Version | 引き続き社内の変更追跡用として独立運用（`v1.0.0`公開後も3.x系のまま更新を継続） |
| Gitタグ名（案） | `homepage-v1.0.0`（`smartlabo-toeic`ではなく`SmartLabo` repoに付与） |
| タグを打つタイミング | 本番公開が完了し、実際に`smartlaboworks.com`でアクセスできることを確認した直後 |

**Gitタグ・GitHub Releaseの作成は、対外的に見える操作（公開アクション）にあたるため、
このチェックリストの実施だけでは行いません。すべての項目にチェックが入り、
CEOが「タグを作成してください」と明示的に指示した時点で実施します。**

---

## 1. チェックリスト本体

各項目について、✅=RC1時点でローカル環境にて確認済み、⏸=本番環境（実際のドメイン・
SSL・SMTP）でのみ確認可能なため未実施、の2種類で状況を記載しています。

### □ SSL

- [ ] `https://smartlaboworks.com` が正しく証明書エラーなく表示される（GitHub Pages側。**GitHubリポジトリのSettings→Pagesでカスタムドメイン設定・SSL自動発行の完了が前提**）
- [ ] `https://form.smartlaboworks.com` が正しく証明書エラーなく表示される（XServer側。**CEO報告により有効化済み、実機確認は未実施**）
- [ ] `http://` アクセス時に `https://` へ自動リダイレクトされる（`xserver-form/public/.htaccess`で実装済み。GitHub Pages側は標準で自動リダイレクト）
- [ ] 混在コンテンツ（HTTP経由の画像・スクリプト等）警告が出ない

現状：⏸ 本番ドメイン確定後に実施

---

### □ 問い合わせ（フォーム動作）

- [x] クライアント側バリデーション（必須項目・メール形式）— ローカル確認済み
- [x] honeypot・送信速度トラップが人間の通常操作を妨げない — ローカル確認済み
- [x] 送信中の二重送信防止（ボタンdisable）— ローカル確認済み
- [ ] 実際に`form.smartlaboworks.com`へ送信が届き、受付番号付きの完了画面が表示される
- [ ] サーバー側バリデーションエラー（不正な値を強制送信した場合等）が適切に表示される
- [ ] レート制限（1時間5回）・二重送信検知（10分）が本番のSQLite環境でも機能する

現状：ロジックはローカルPHP環境で全経路検証済み（22件の統合テスト含む）。⏸ 実ドメインでの送受信確認は未実施

---

### □ メール

- [ ] 管理者通知メールが`info@smartlaboworks.com`に届く
- [ ] 自動返信メールが送信者のアドレスに届く
- [ ] 件名・本文の日本語が文字化けしていない（`SmtpMailer.php`はbase64エンコードで送信）
- [ ] 迷惑メールフォルダに振り分けられていない
- [ ] `smtp_encryption`（tls/ssl）がXServerの実際のSMTP要件と一致している

現状：⏸ `config.php`本番設定・実際のSMTP接続確認が前提のため未実施

---

### □ リンク

- [x] 全9ページの内部リンク・アンカーリンクが解決する — ローカル確認済み（自動巡回）
- [x] フッター・ナビの全リンクが正しい遷移先を指す — ローカル確認済み
- [ ] 本番ドメイン切替後も同様にリンク切れがないことを再確認（相対パスのみ使用のため理論上は問題ないはずだが、実機での最終確認を推奨）

現状：✅ ローカルで確認済み、⏸ 本番ドメインでの最終確認が残る

---

### □ Console Error

- [x] 全9ページでJavaScriptエラーなし — ローカル確認済み
- [ ] 本番ドメイン（`form.smartlaboworks.com`との通信含む）でもエラーが出ないことを確認（CORS設定が実際のオリジンと一致している必要あり。`xserver-form/public/lib/settings.php`の`SLW_ALLOWED_ORIGINS`に`https://smartlaboworks.com`が含まれていることを確認済み）

現状：✅ ローカルで確認済み、⏸ 本番での最終確認が残る

---

### □ favicon

- [x] 全9ページで表示確認済み（`img/logo/favicon.png`）

現状：✅ 確認済み

---

### □ OGP

- [x] 全ページに`og:title`/`og:description`/`og:url`/`og:image`/Twitter Card設定済み
- [x] URLはすべて正式ドメイン`https://smartlaboworks.com`に統一済み（RC1で対応）
- [ ] Facebook Sharing Debugger・Twitter Card Validator等で実際のシェアプレビュー表示を確認（本番URL公開後に実施）

現状：✅ 設定は完了、⏸ 実際のシェアプレビュー確認は本番公開後

---

### □ sitemap

- [x] `sitemap.xml`新設・ローカルで200応答確認済み
- [x] index対象7ページを掲載、`noindex`の2ページ（privacy/terms）は除外
- [ ] 本番公開後、Google Search Consoleへ`sitemap.xml`を送信

現状：✅ ファイル作成・ローカル確認済み、⏸ Search Console送信はCEOアクション

---

### □ robots

- [x] `robots.txt`新設・ローカルで200応答確認済み
- [x] `Allow: /`＋`Sitemap:`参照。`noindex`ページはrobots.txtでの`Disallow`ではなくmetaタグに一本化（Google推奨方式）

現状：✅ 確認済み

---

### □ SEO

- [x] 全ページの`title`・`meta description`設定済み（内容・文字数を確認済み）
- [x] `canonical`タグ設定済み（正式ドメイン）
- [x] 構造化データ（Organization、JSON-LD）を`index.html`に追加。未確定の会社情報は含めていない
- [ ] Google Rich Results Test等での構造化データの妥当性確認（本番URL公開後）
- [ ] Google Search Consoleへのサイト登録・所有権確認

現状：✅ 実装完了、⏸ 外部ツールでの検証・Search Console登録はCEOアクション

---

### □ モバイル

- [x] 全9ページ、375px幅で横スクロールなしを確認済み
- [x] 問い合わせフォーム・Configurator等のインタラクティブ要素もモバイルで確認済み

現状：✅ 確認済み

---

### □ PageSpeed

- [ ] 本番URL公開後、Google PageSpeed Insightsで計測
- [x] 事前所見：Webフォント不使用（システムフォントのみ、良好）、主要画像はWebP化済み（良好）、CSS/JS未minify（改善余地あり、今回は未実施）

現状：⏸ 実施は本番URL公開後（ローカル/暫定URLでの計測は参考値にとどまるため）

---

### □ GitHub Release Tag

- [ ] タグ`homepage-v1.0.0`（案）を作成し、GitHub Releaseを公開
- [ ] リリースノートに本チェックリストの結果・主要変更点を記載

現状：**未実施。全項目の確認完了後、CEOの明示的な指示を得てから実施します（対外公開を伴う操作のため）。**

---

### □ バージョン番号

- [ ] CEOによる`v1.0.0`（提案）の承認
- [ ] 承認後、`WEBSITE/`または`PROJECT_BIBLE`内の適切な箇所にバージョン番号を明記

現状：**提案のみ。0章参照。CEO承認待ち**

---

### □ 更新履歴

以下は今回のリリースサイクル（Sprint「正式リリース前 最終整備」〜Release Candidate 1）で
実施した主要な変更のまとめです。詳細な日次記録は[CURRENT_STATUS.md](CURRENT_STATUS.md)を参照してください。

| 分類 | 内容 |
|---|---|
| 会社情報 | 会社概要ページ(`company.html`)新設。未確定の会社情報(代表者・所在地等)は「CEO確認待ち」と明示 |
| 法務 | プライバシーポリシー・利用規約のドラフト作成(専門家確認前提の注記付き) |
| 導入プラン | `pricing.html`を「Smart Labo Configurator」(5Stepシミュレーター)へ刷新 |
| 問い合わせフォーム | XServer(PHP)による送信基盤を新規実装(`xserver-form/`)。CSRF・レート制限・入力値検証・honeypot・reCAPTCHA(開発/本番モード切替)を実装 |
| メール | `info@smartlaboworks.com`によるSMTP送信(管理者通知・自動返信)、tls/ssl両対応 |
| SEO | `robots.txt`・`sitemap.xml`新設、構造化データ(Organization)追加、全ページのURLを正式ドメインへ統一 |
| 公開準備 | `WEBSITE/CNAME`新設(GitHub Pagesカスタムドメイン設定用) |

- [x] 上記の更新履歴を本チェックリストへ記載済み

---

## 2. 完了条件

このチェックリストのうち✅（RC1で確認済み）の項目に加え、⏸（本番環境が必要）の
すべての項目にチェックが入った時点で「Release Candidate 2」から正式リリース
（GA / General Availability）に進めます。GitHubタグ・Releaseの作成、公開バージョン
番号の確定は、その最終段階でCEOの明示的な承認を得てから実施します。

---

## 変更履歴

| バージョン | 日付 | 変更者 | 変更内容 |
|---|---|---|---|
| v1.0 | 2026-07-14 | Claude Code(CEO指示による) | 新規作成。Release Candidate 2として、本番公開前チェックリスト(SSL/問い合わせ/メール/リンク/Console Error/favicon/OGP/sitemap/robots/SEO/モバイル/PageSpeed/GitHub Release Tag/バージョン番号/更新履歴の15項目)を制定。Release Candidate 1(コミット`76a9767`)時点の確認状況を反映し、本番環境が必要な項目は⏸として明示。バージョン番号は`v1.0.0`(ホームページ正式公開)を提案、Gitタグ・GitHub Releaseの作成はCEOの明示的な承認後に実施する方針を明記 |

*最終更新: 2026-07-14*
