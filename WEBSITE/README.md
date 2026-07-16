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

## 公開状況(2026-07-16時点)

**Homepage Version: v1.0.0（正式公開版）** — 2026-07-16、「銀行口座開設・融資申込・会社実態の公開」を目的としたCEO最終公開指示により、現状実装をそのまま正式公開版として確定しました。

**公開URL(正式ドメイン): https://smartlaboworks.com/**

**2026-07-16のDNS実測により、`smartlaboworks.com`はまだGitHub Pagesへ切り替わっておらず、Xserverの初期設置ページを指していることを確認しました。** GitHub Pages側のカスタムドメイン設定(Settings→Pages)・DNSレコードの切替(Aレコード4件・CNAME)は、引き続きCEO(または担当者)による対応が必要です。手順は[PROJECT_BIBLE/62_CEO_Publish_Guide.md](../PROJECT_BIBLE/62_CEO_Publish_Guide.md)を参照してください。

**実装済みの機能(v1.0.0)**：

- 会社概要ページ(`company.html`、代表者名・設立年月・事業内容を反映。所在地・電話番号は引き続き「CEO確認待ち」)
- プライバシーポリシー・利用規約(`privacy.html`・`terms.html`、専門家確認前提のドラフト)
- Smart Labo Configurator(`pricing.html`、導入プランの5Stepシミュレーター)
- SEO対応(`robots.txt`・`sitemap.xml`・構造化データ)

**v1.0.0では未実施、Version1.1で対応予定(2026-07-16 CEO確定)**：

- 問い合わせフォームの本番稼働(`contact.html`は本番送信テスト未完了のため、フォームを撤去し`info@smartlaboworks.com`へのmailtoリンクへ一時的に差し替え済み)
- AIチャットウィジェットの実API接続(現状はデモ回答のみ)
- Google Analytics導入
- Google Search Console登録

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
| **v1.4** | 2026-07-16 | Claude Code(CEO最終公開指示による) | **Homepage v1.0.0として正式公開版を確定。** 銀行口座開設・融資申込・会社実態の公開を目的としたCEO最終公開指示に基づき、Homepage Versionを3.1→v1.0.0に更新。代表者名・設立年月をCEOから取得し会社概要ページへ反映。本番送信テスト未完了の問い合わせフォームを撤去しmailtoリンクへ差し替え、AIチャット実API接続・問い合わせフォーム本番稼働・Google Analytics・Search ConsoleをVersion1.1へ先送りする方針を明記。DNS実測により`smartlaboworks.com`がまだGitHub Pagesを指していないことを確認し、v1.3時点の「Xserver契約・ドメイン設定は完了」という記載を「DNS切替は引き続きCEOアクション待ち」へ修正(正確な現状反映) |

*最終更新: 2026-07-16*
