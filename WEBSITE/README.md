# WEBSITE

**コーポレートサイト関連**

---

## このフォルダの目的

株式会社スマートラボのコーポレートサイトに関するソースコード・コンテンツ・運用資料を管理します。会社の「顔」となるサイトであるため、[PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md](../PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md) および [PROJECT_BIBLE/20_UI_UX_Rules.md](../PROJECT_BIBLE/20_UI_UX_Rules.md) に厳密に準拠します。

---

## `app.html`の位置づけ(2026-07-10 CEO決定)

**`app.html`はSmart Labo Worksの正式製品ではありません。** Smart Labo Worksの唯一の正式コードベースは、別リポジトリ`smartlabo-works`(Node.js版・実際のAI Provider連携あり)です。

`app.html`は今後、**デモサイト／マーケティング用プレビュー**として位置づけます。営業・採用・投資家向けの見た目のイメージを伝えるための静的デモであり、GitHub Pagesで公開されているデータ・機能はすべてサンプル(実データ・実処理なし)です。今後の機能追加・仕様変更は`smartlabo-works`側でのみ行い、`app.html`側では原則として行いません(ブランド刷新等に伴う見た目の同期を除く)。

詳細は [PROJECT_BIBLE/00_Foundation/08_SmartLaboWorks_Concept.md](../PROJECT_BIBLE/00_Foundation/08_SmartLaboWorks_Concept.md)「製品境界」節、および`smartlabo-works`リポジトリの`PRODUCT_BOUNDARY.md`を参照してください。

---

## 公開状況(2026-07-14時点)

**Homepage Version: 3.1(v1.0 Release Candidate)** — 正式リリース前 最終整備を経て、正式公開に向けた最終確認段階です。

**公開URL(正式ドメイン、設定済み): https://smartlaboworks.com/**

Xserverとの契約・`smartlaboworks.com`のドメイン設定・無料SSLの有効化・`form.smartlaboworks.com`サブドメイン(問い合わせフォーム用)の作成はすべて完了しています。GitHub Pages側のカスタムドメイン切替(Settings→Pages)はCEOの最終手順として残っています。詳細は[PROJECT_BIBLE/61_Release_Checklist.md](../PROJECT_BIBLE/61_Release_Checklist.md)を参照してください。

**実装済みの機能**：

- 問い合わせフォーム(`contact.html` → `form.smartlaboworks.com`、XServer/PHP。CSRF・レート制限・入力値検証・honeypot実装済み、reCAPTCHAは開発モード)
- プライバシーポリシー・利用規約(`privacy.html`・`terms.html`、専門家確認前提のドラフト)
- 会社概要ページ(`company.html`、未確定の会社情報は「CEO確認待ち」と明示)
- Smart Labo Configurator(`pricing.html`、導入プランの5Stepシミュレーター)
- SEO対応(`robots.txt`・`sitemap.xml`・構造化データ)

公開前チェックの最新状況は[PROJECT_BIBLE/61_Release_Checklist.md](../PROJECT_BIBLE/61_Release_Checklist.md)（v1.0 Release Checklist）を参照してください。

詳細は [PROJECT_BIBLE/CURRENT_STATUS.md](../PROJECT_BIBLE/CURRENT_STATUS.md) を参照してください。

---

## 利用ルール

- ページ追加・変更の際は、必ずブランドガイドライン(Enterprise AI Platform: Premium・Trust・Innovation・AI First)に沿っているか確認してください。特にHomepageは [PROMPTS/DESIGN/Homepage.md](../PROMPTS/DESIGN/Homepage.md) の指示に従ってください。
- 法務ページ(利用規約、プライバシーポリシー、特定商取引法に基づく表記など)を追加・変更する場合は、[LEGAL/](../LEGAL/README.md) の内容と整合性を取ってください。
- 新規ページを追加した場合は、フッター等のナビゲーションからの導線も確認してください。

---

## Version管理

- サイトの主要な変更(新ページ追加、デザインリニューアル等)は、コミット履歴からわかるようにメッセージを明確に記述してください。

---

## 更新ルール

- コンテンツの誤字脱字・軽微な修正は随時反映して構いません。
- デザインに影響する変更は、[DESIGN/](../DESIGN/README.md) のデザインシステムとの整合性を確認してから反映してください。

---

## Git運用ルール

- 本番公開に関わる変更は、可能な限りステージング環境やプレビューで確認してから `main` にマージしてください。
- 公開前のコンテンツ(未発表の新サービス情報等)を含む変更は、公開タイミングに注意してコミット・マージしてください。

---

## 変更履歴

| バージョン | 日付 | 変更者 | 変更内容 |
|---|---|---|---|
| v1.0 | 2026-07-03 | Claude Code | 初版作成 |
| v1.1 | 2026-07-04 | Claude Code(CEO指示による) | Homepage Version 0.9 betaとして公開。「公開状況」セクションを新設し、公開前チェック結果と既知の未実装事項を記録 |
| **v1.2** | 2026-07-10 | Claude Code(CEO指示による) | **`app.html`はSmart Labo Works正式製品ではなく、デモサイト／マーケティング用プレビューであることを明記。** 唯一の正式コードベースは別リポジトリ`smartlabo-works`(Node.js版)であることを新設の節に記載。`app.html`内の「バージョン」表示・`<title>`から「Smart Labo Works v1.0」という誤解を招く表記を修正(デモである旨と正式コードベースへの案内を追記) |
| **v1.3** | 2026-07-14 | Claude Code(CEO指示による) | **「公開状況」節を正式リリース前の最新状態へ更新。** Homepage Versionを0.9 beta→3.1(v1.0 Release Candidate)に更新。公開URLを暫定のGitHub Pages URLから正式ドメイン`https://smartlaboworks.com/`へ更新。Xserver契約・ドメイン設定・SSL有効化がすべて完了したことを反映し、古い「Xserverサーバー契約未完了」の記載を削除。実装済み機能一覧(問い合わせフォーム・法務ページ・会社概要・Configurator・SEO対応)を追加し、「β版として既知の未実装事項」節(フォーム未実装等、すべて解消済みのため陳腐化していた)を削除 |

*最終更新: 2026-07-14*
