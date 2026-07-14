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

- **会社概要ページ**（`company.html`）：法人名・ブランド名等の確定情報を掲載。未確定の会社情報は推測せず明示
- **法務ページ**（`privacy.html`・`terms.html`）：プライバシーポリシー・利用規約のドラフト
- **Smart Labo Configurator**（`pricing.html`）：利用規模→基本プラン→利用モジュール→追加オプション→おすすめ構成確認の5Stepシミュレーター
- **問い合わせフォーム送信基盤**（`xserver-form/`）：XServer(PHP)によるSMTP送信、CSRF・レート制限・入力値検証・honeypot・送信速度トラップによる多層防御
- **送信完了画面**：フォーム送信成功時、受付番号付きの専用画面へ切り替わる
- **reCAPTCHA開発/本番モード**：キー未設定でも動作し、CEOがキーを設定すれば本番モードへ切替可能な構造
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

v1.0時点の既知の制限事項は、3つの区分で[61_Release_Checklist.md](61_Release_Checklist.md)の
「既知の制限事項」章にまとめています。

- **v1.0で対応しない項目**：会社情報の未確定（代表者・所在地等）、法務ページのドラフト状態、特定商取引法表記の要否未確定、reCAPTCHA未設定（開発モード）、Google Search Console/Analytics未導入、Company Brain・CRM等の非公開機能
- **v1.1予定項目**：CSS/JSのminify、レート制限実装方式の見直し（トラフィック次第）
- **将来構想**：AIチャットの実API接続、他業種テンプレートの実装、Pricing Manager等（詳細は[63_Post_Launch_Roadmap.md](63_Post_Launch_Roadmap.md)）

---

## 次期バージョン予定

公開後の優先順位は[63_Post_Launch_Roadmap.md](63_Post_Launch_Roadmap.md)を参照してください。
概要は以下のとおりです（着手時期・スコープは別途Task化してCEO承認後に決定）。

①AIチャットの実API接続　②Pricing Manager　③CRM連携　④Company Brain連携　⑤公開API　⑥`app.smartlaboworks.com`　⑦Customer Portal　⑧管理画面

---

## 変更履歴

| バージョン | 日付 | 変更者 | 変更内容 |
|---|---|---|---|
| v1.0 | 2026-07-14 | Claude Code(CEO指示による) | 新規作成。「Release Candidate 2 最終仕上げフェーズ」Task6の指示に基づき、homepage-v1.0.0のRelease Notesを作成。概要・追加機能・改善点・修正点・Known Issues・次期バージョン予定の6章構成。GitHub Release作成時にそのまま使用できる内容とした |

*最終更新: 2026-07-14*
