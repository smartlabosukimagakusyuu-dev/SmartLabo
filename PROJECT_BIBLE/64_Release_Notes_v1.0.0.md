# 64. Release Notes — homepage-v1.0.0

**Smart Labo Works Homepage v1.0.0**
公開バージョン番号・Gitタグ名の方針は[61_Release_Checklist.md](61_Release_Checklist.md)の
0章を参照してください。本ドキュメントは、CEO承認・Gitタグ作成の際に、GitHub Releaseの
リリースノートとしてそのまま使用することを想定しています。

---

## 概要

Smart Labo Works公式ホームページ（`smartlaboworks.com`）の正式初回リリースです。
「会社を動かすAI。」というブランドコンセプトのもと、会社概要・製品紹介・機能一覧・
不動産向けページ・導入プランシミュレーター・問い合わせフォームを備えた、
正式公開可能な品質のコーポレートサイトとして仕上げました。

ホームページ本体はGitHub Pages、問い合わせフォームはXServer(PHP)という役割分離構成を
採用し、それぞれの特性に合った基盤で運用します。

---

## 追加機能

- **会社概要ページ**（`company.html`）：法人名・代表者名・設立年月・事業内容等の確定情報を掲載。未確定の会社情報(所在地・電話番号等)は推測せず明示
- **法務ページ**（`privacy.html`・`terms.html`）：プライバシーポリシー・利用規約のドラフト
- **Smart Labo Configurator**（`pricing.html`）：利用規模→基本プラン→利用モジュール→追加オプション→おすすめ構成確認の5Stepシミュレーター
- **お問い合わせ**（`contact.html`）：v1.0.0時点では`info@smartlaboworks.com`へのmailtoリンクによるご案内。フォーム送信基盤（`xserver-form/`、XServer(PHP)によるSMTP送信、CSRF・レート制限・入力値検証・honeypot・送信速度トラップによる多層防御、送信完了画面、reCAPTCHA開発/本番モード切替構造）は実装済みだが、本番送信テストが未完了のためv1.0.0では非公開とし、Version1.1で有効化する
- **SEO基盤**：`robots.txt`・`sitemap.xml`・構造化データ（Organization / WebSite）
- **Smart Labo Works 詳細ページ群**：`product.html`・`features.html`・`real-estate.html`（製品紹介・機能一覧・不動産業向け訴求）

---

## 改善点

- 全ページのcanonical・OGP・Twitter CardのURLを、暫定URL（GitHub Pages）から正式ドメイン`https://smartlaboworks.com`へ統一
- ヘッダー・フッターのロゴ露出強化、ブランド一貫性の向上
- Hero「AI Control Center」のライブダッシュボード演出（アニメーション・KPI表示）
- ホームページの情報量を整理し、3秒で価値が伝わる構成へ簡素化
- meta descriptionの文字数を全ページで見直し、検索結果での表示切れを防止
- Safari向けに`-webkit-backdrop-filter`プレフィックスの付与漏れを解消

---

## 修正点

- SMTPヘッダー・コマンドインジェクション対策を多層防御として追加（メール送信処理）
- 会社情報表示（`js/company-info.js`）が暫定URL・古いインフラ状況を参照していた不具合を修正し、正式ドメイン・確定済みメールアドレスを反映
- トップページ最終CTA欄に残っていた「送信先は未接続です」という、フォーム実装前の古い案内文を削除
- 構造化データにWebSiteスキーマを追加（従来はOrganizationのみ）
- HTMLコメントのネスト（内側の`-->`が外側のコメントを早期終了させる）による意図しないテキスト漏出のバグを実装時に発見・修正

---

## Known Issues

v1.0.0時点の既知の制限事項は、3つの区分で[61_Release_Checklist.md](61_Release_Checklist.md)の
「既知の制限事項」章にまとめています。

- **v1.0.0で対応しない項目**：所在地・電話番号・資本金・代表者略歴が未確定（代表者名・設立年月・事業内容は2026-07-16にCEO確認済みで反映済み）、法務ページのドラフト状態、特定商取引法表記の要否未確定、reCAPTCHA未設定（開発モード）、Company Brain・CRM等の非公開機能、GitHub Pages側のカスタムドメイン・DNS切替未完了（2026-07-16実測で確認、CEOアクション待ち）
- **Version1.1予定項目**（2026-07-16 CEO最終公開指示で確定）：問い合わせフォームの本番稼働（現在は`info@smartlaboworks.com`へのmailtoリンクで代替）、AIチャットウィジェットの実API接続、Google Analytics導入、Google Search Console登録
- **v1.1予定項目（技術面）**：CSS/JSのminify、レート制限実装方式の見直し（トラフィック次第）
- **将来構想**：他業種テンプレートの実装、Pricing Manager等（詳細は[63_Post_Launch_Roadmap.md](63_Post_Launch_Roadmap.md)）

---

## 次期バージョン予定

公開後の優先順位は[63_Post_Launch_Roadmap.md](63_Post_Launch_Roadmap.md)を参照してください。
Version1.1では、2026-07-16のCEO最終公開指示により以下を優先対応します。

①問い合わせフォームの本番稼働　②AIチャットの実API接続　③Google Analytics導入　④Google Search Console登録

その先の優先順位（概要、着手時期・スコープは別途Task化してCEO承認後に決定）：

⑤Pricing Manager　⑥CRM連携　⑦Company Brain連携　⑧公開API　⑨`app.smartlaboworks.com`　⑩Customer Portal　⑪管理画面

---

## 変更履歴

| バージョン | 日付 | 変更者 | 変更内容 |
|---|---|---|---|
| v1.0 | 2026-07-14 | Claude Code(CEO指示による) | 新規作成。「Release Candidate 2 最終仕上げフェーズ」Task6の指示に基づき、homepage-v1.0.0のRelease Notesを作成。概要・追加機能・改善点・修正点・Known Issues・次期バージョン予定の6章構成。GitHub Release作成時にそのまま使用できる内容とした |
| v1.1 | 2026-07-16 | Claude Code(CEO最終公開指示による) | Homepage v1.0.0の正式公開確定を反映。会社概要ページの追加機能を代表者名・設立年月確定に合わせて更新。問い合わせフォームはv1.0.0では非公開(mailto代替)であることを明記し、Known Issues・次期バージョン予定にVersion1.1対応予定4項目(フォーム本番稼働・AIチャット実API接続・Google Analytics・Search Console)をCEO確定事項として追加 |

*最終更新: 2026-07-16*
