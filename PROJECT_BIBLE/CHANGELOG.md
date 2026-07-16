# CHANGELOG — PROJECT_BIBLE 全体の変更履歴

`PROJECT_BIBLE` は株式会社スマートラボのOS・憲法であり、Single Source of Truth です。このファイルは、PROJECT_BIBLE 配下のドキュメント群に加えられた**重要な変更**を、日付順に一覧できるようにするためのマスター記録です。

- 各ファイル個別の細かい変更履歴は、各ファイル末尾の「変更履歴」テーブルを参照してください。
- ここには、複数ファイルにまたがる変更や、会社の意思決定として記録すべき重要な更新のみを記載します(誤字修正などの軽微な変更は対象外)。
- 新しいエントリは**先頭(最新)に追加**してください(Keep a Changelog 形式に準拠)。
- バージョニング方針は [README.md](README.md) の「Version管理」を参照してください。

---

## 2026-07-16 — Smart Labo Works Homepage v1.0.0 正式公開版として確定

CEOより「銀行口座開設・融資申込・会社実態の公開」を目的とした最終公開指示があった。

> 現在の実装内容をそのまま正式公開します。追加開発は行いません。①GitHub Pagesの公開手順最終確認②smartlaboworks.comへの切替手順最終確認③DNS切替後の確認項目④コミット⑤push⑥CURRENT_STATUS更新⑦PROJECT_BIBLE更新を対応してください。公開後のVersionはHomepage v1.0.0としてください。AIチャット・問い合わせフォーム本番・Google Analytics・Search ConsoleはVersion1.1で実施します。

### 背景

前回セッションでCEOに代表者名・設立年月・事業内容を確認し、会社概要ページの主要項目が確定した。あわせて、[61_Release_Checklist.md](61_Release_Checklist.md)で「実ドメインでの送受信確認は未実施」と記録されていた問い合わせフォームについて、CEOから「本番送信テストが完了していない場合はフォームへの導線を一時的に外し、メール問い合わせへ変更する」との指示があった。今回の最終公開指示を受け、これらを反映したうえでHomepage v1.0.0として確定し、初めて`SmartLabo`リポジトリへコミット・pushした。

### 主な変更内容

- [WEBSITE/js/company-info.js](../WEBSITE/js/company-info.js)：代表者(小川昌利)・設立年月(2026年7月)・事業内容を追加(所在地・電話番号は引き続き未確定)
- [WEBSITE/contact.html](../WEBSITE/contact.html)：本番送信テスト未完了の問い合わせフォームを撤去し、`info@smartlaboworks.com`へのmailtoリンクへ差し替え(`js/pages.js`は既存のnullガードにより無変更でconsoleエラーなし動作を確認)
- [CURRENT_STATUS.md](CURRENT_STATUS.md)(v7.1→v7.2)：Homepage Versionを3.2→v1.0.0に更新
- [README.md](README.md)：PROJECT_BIBLE Version 7.6→7.7
- [61_Release_Checklist.md](61_Release_Checklist.md)(v3.0→v4.0)：問い合わせフォーム関連項目をVersion1.1対応として整理、DNS実測結果を追記
- [64_Release_Notes_v1.0.0.md](64_Release_Notes_v1.0.0.md)(v1.0→v1.1)：Homepage v1.0.0の正式公開内容を反映
- [../WEBSITE/README.md](../WEBSITE/README.md)(v1.3→v1.4)：公開状況をHomepage v1.0.0の実態に合わせて更新

### DNS実測による発見(重要)

2026-07-16、`nslookup`・`curl`により`smartlaboworks.com`の実際のDNS/HTTP応答を確認したところ、`smartlaboworks.com`・`www.smartlaboworks.com`・`form.smartlaboworks.com`のいずれも同一のXserver IPを指しており、`https://smartlaboworks.com/`は本サイトではなくXserverの初期設置ページを表示する状態だった。従来のドキュメント記載(「Xserverとの契約・ドメイン設定は完了」)は不正確であったため、実測結果に基づき修正した。GitHub Pages側のカスタムドメイン設定・DNSのAレコード切替(4件)は、[62_CEO_Publish_Guide.md](62_CEO_Publish_Guide.md)の手順どおりCEO(または担当者)による対応が引き続き必要。

### 今回実施しなかったこと

- Gitタグ`homepage-v1.0.0`・GitHub Releaseの作成(対外公開を伴う操作のため、CEOの別途明示的な承認を得てから実施する方針を維持)
- GitHub Pages側のカスタムドメイン設定・DNS切替・XServerへのフォームPHPアップロード(Claude Codeからは実行不可。GitHub管理画面・DNSレジストラへのアクセス権がないため)
- AIチャット実API接続・問い合わせフォーム本番稼働・Google Analytics・Search Console導入(いずれもCEOがVersion1.1で対応する方針を確定済み)

---

## [Unreleased]

- (次回の変更予定があればここに記載)
- `smartlabo-works`コード側でCompany OS機能(Smart Growth・人材育成・Innovation Hub等)とSmart Labo Works機能の実装分離に着手
- 真のベクター(SVG)ロゴデータを取得し [DESIGN_ASSETS/01_LOGO/SVG/](../DESIGN_ASSETS/01_LOGO/README.md) へ格納する
- 正式ロゴをDashboard・営業資料・名刺・パンフレット等、他の制作物にも展開する(現状はHomepageのみ実装)
- Xserverのサーバープラン契約・DNS設定、契約完了後の正式ドメインへのデプロイ切り替え
- 実際のお問い合わせ窓口(メールアドレス等)が確定次第、CTAボタンを接続する
- Company Brainを営業資料・パンフレットへ展開する
- Dashboard v0.6：実データ連携・顧客管理強化
- `WEBSITE/app.html`のログイン画面ロゴを正式ロゴ(六角形+サーキット「S」)へ差し替える

---

## 2026-07-10 — Smart Labo Worksの正式コードベースを`smartlabo-works`に一本化、WEBSITE/app.htmlはデモへ

CEOより以下の方針決定があった。

> 正式版は smartlabo-works(Node.js版)とします。こちらを唯一のSmart Labo Works正式コードベースとしてください。WEBSITE/app.html(GitHub Pages版)は正式製品ではありません。今後はデモサイトまたはマーケティング用プレビューとして扱ってください。PROJECT_BIBLE / CURRENT_STATUS / PRODUCT_REQUIREMENTS / PRODUCT_BOUNDARY / BUSINESS_STRATEGYも、smartlabo-worksのみを正式版として管理してください。WEBSITE側に残っているCompany OS等の古い表記はデモサイトとして整理してください。今後、Smart Labo Worksの機能追加はsmartlabo-worksのみで行います。

### 背景

前回(同日)のpush作業中、`SmartLabo`リポジトリのリモート側に、2026-07-07付けで`WEBSITE/app.html`を「Smart Labo Works v1.0 — Company OS完成」とする未マージのコミットが3件存在することが判明した。マージした結果、`smartlabo-works`(Node.js版、実API連携あり)と`WEBSITE/app.html`(GitHub Pages版、静的デモ)という**2つの異なるコードベースが同時に「Smart Labo Works v1.0」を名乗る状態**になっていたため、CEOに確認を仰いだ。CEOは`smartlabo-works`を唯一の正式コードベースとし、`WEBSITE/app.html`をデモサイト／マーケティング用プレビューへ位置づけ変更する決定を下した。

### 主な変更内容

- [11_Development_Principles.md](11_Development_Principles.md)(v1.2→v1.3)・[00_Foundation/08_SmartLaboWorks_Concept.md](00_Foundation/08_SmartLaboWorks_Concept.md)(v3.1→v3.2)に「唯一の正式コードベースは`smartlabo-works`」「`WEBSITE/app.html`はデモサイト／マーケティング用プレビュー」を明記
- [CURRENT_STATUS.md](CURRENT_STATUS.md)(v3.0.1→v3.1)を全面改訂。「Dashboard Version」「Smart Labo Works Version」の2行を「デモサイト Version(`WEBSITE/app.html`)」と「Smart Labo Works Version(本ファイルでは追跡せず、別リポジトリ`smartlabo-works`を参照)」へ分離。マージ時に残っていた「2つのv1.0が並立している」という未決定の注記を解消した
- [WEBSITE/README.md](../WEBSITE/README.md)(v1.1→v1.2)に「`app.html`の位置づけ」節を新設し、正式製品ではないことを明記
- [WEBSITE/app.html](../WEBSITE/app.html)の`<title>`と「バージョン」表示から「Smart Labo Works v1.0」という誤解を招く表記を修正し、「デモ（マーケティング用プレビュー）」である旨と正式コードベースへの案内を追記(コード内に「Company OS」という文字列自体は元々存在しなかったため、削除ではなく位置づけの明記による整理)
- 別リポジトリ`smartlabo-works`側の`PRODUCT_REQUIREMENTS.md`・`PRODUCT_BOUNDARY.md`にも同じ決定を反映。あわせて新規`BUSINESS_STRATEGY.md`(スタブ)を作成した
- [README.md](README.md)：PROJECT_BIBLE Version 3.5→3.6

### 更新したファイル

- [11_Development_Principles.md](11_Development_Principles.md)(v1.2→v1.3)
- [00_Foundation/08_SmartLaboWorks_Concept.md](00_Foundation/08_SmartLaboWorks_Concept.md)(v3.1→v3.2)
- [CURRENT_STATUS.md](CURRENT_STATUS.md)(v3.0.1→v3.1)
- [README.md](README.md)(PROJECT_BIBLE Version 3.5→3.6)
- [../WEBSITE/README.md](../WEBSITE/README.md)(v1.1→v1.2)
- [../WEBSITE/app.html](../WEBSITE/app.html)(表記修正、バージョン管理なし)

---

## 2026-07-10 — Company OS と Smart Labo Works の製品呼称・境界を正式決定

CEOより以下の方針決定があった。

> 会社名：株式会社スマートラボ／販売する製品：Smart Labo Works／社内専用システム：Company OS。Company OSは顧客へ販売しない。Smart Labo Worksは顧客向けSaaSである。「Smart Labo Group」という表現は現時点では正式名称ではなく、将来のブランド構想としてのみ記載する。「Smart Labo AI」「Smart Labo CRM」も将来の商品候補として整理し、正式製品としては扱わない。

### 背景

`smartlabo-works`リポジトリの実装調査(`PRODUCT_REQUIREMENTS.md`・`PRODUCT_BOUNDARY.md`)により、現行コードには株式会社スマートラボ自身が使う社内専用機能(Smart Growth・人材育成・Innovation Hub等)と、顧客へ販売するSaaS機能(Company Brain・AI Assistant・CRM等)が単一アプリに混在していることが判明した。あわせて、PROJECT_BIBLE内でも「Company OS」という語が、①社内専用システムを指す用法と、②Smart Labo Works自体(顧客向け製品)の設計思想を形容する用法の2通りで混在して使われていたため、CEOが正式に呼称を統一した。

### 主な変更内容

- [11_Development_Principles.md](11_Development_Principles.md)(v1.1→v1.2)に「製品境界の定義」を新設。Smart Labo Works=顧客へ販売するSaaS製品、Company OS=株式会社スマートラボの社内専用システム(非売品)と明記。「Smart Labo Design Principle」「デモ画面作成ルール」「Dashboard設計原則」「最重要原則」の4箇所で、Company OSをSmart Labo Works自体の形容として使っていた記述を修正し、Smart Labo Worksの説明からCompany OSという語を除去した
- [00_Foundation/08_SmartLaboWorks_Concept.md](00_Foundation/08_SmartLaboWorks_Concept.md)(v3.0→v3.1)に「製品境界」節を新設し、同様の区別を明記。関連ドキュメント欄の説明文も修正
- 「Smart Labo Group」というプロダクトファミリー名、「Smart Labo AI」「Smart Labo CRM」という商品名は、いずれも**非公式・将来のブランド構想/商品候補**であり、正式名称・正式製品ではないことを明記
- [CURRENT_STATUS.md](CURRENT_STATUS.md)を更新。5行サマリー・ステータス表・Current Task/Next Taskに今回の決定を反映
- 別リポジトリ`smartlabo-works`側の`PRODUCT_BOUNDARY.md`・`PRODUCT_REQUIREMENTS.md`も同じ前提へ統一(コミット: `docs: clarify product boundary and strategy`)

### 更新したファイル

- [11_Development_Principles.md](11_Development_Principles.md)(v1.1→v1.2)
- [00_Foundation/08_SmartLaboWorks_Concept.md](00_Foundation/08_SmartLaboWorks_Concept.md)(v3.0→v3.1)
- [CURRENT_STATUS.md](CURRENT_STATUS.md)
- [README.md](README.md)(PROJECT_BIBLE Version 3.4→3.5)

---

## 2026-07-05 — 「CEOレビュー原則」を追加

CEOより「大きな仕様変更・ブランド変更・UI変更を行う場合は、実装前に変更内容を整理し、レビューを受けてから開発を進めてください。独自判断でブランドコンセプトやUI方針を変更しないこと。Smart Laboは長期的なブランド構築を重視します。」との指示があった。

### 主な変更内容

- [11_Development_Principles.md](11_Development_Principles.md)(v1.0→v1.1)に「CEOレビュー原則」を新設。「開発前ルール(必須)」の直後に配置し、以下を明文化した。
  - 大きな仕様変更・ブランド変更・UI変更は、実装前に変更内容(何を・なぜ・どう変えるか)を整理し、レビューを受けてから開発を進めること
  - 独自判断でブランドコンセプトやUI方針を変更しないこと
  - 誤字修正や軽微なスタイル調整はこの原則の対象外とすること
  - Smart Laboは長期的なブランド構築を重視し、目先のスピードよりも一貫性を優先すること
- [60_Editorial_Workflow.md](60_Editorial_Workflow.md)(v2.2→v2.3)の「推測で判断しない(エスカレーションルール)」に、新設の「CEOレビュー原則」への相互参照を追加。既存のエスカレーションルール(PROJECT_BIBLEに記載のない重要判断はCEOへ確認する)を、より広い「大きな仕様・ブランド・UI変更」全般に対して明確化・強化する位置づけ

### 更新したファイル

- [11_Development_Principles.md](11_Development_Principles.md)(v1.0→v1.1)
- [60_Editorial_Workflow.md](60_Editorial_Workflow.md)(v2.2→v2.3)
- [README.md](README.md)(v3.3→v3.4)
- [CURRENT_STATUS.md](CURRENT_STATUS.md)(v2.8→v2.9)

**変更者:** Claude Code(Project Bible編集長)/ 指示: CEO

---

## 2026-07-05 — Dashboard v0.5: Company OS実装・GitHub Pages公開

CEOより「DashboardをCRMではなくCompany OSへ進化させる」との指示に基づき、`app.html`をDashboard v0.5として全面アップグレードし、GitHub Pagesへ公開。

### 主な変更内容
- **Company Brain専用画面を新規実装** — サイドバーに専用アイコン付きで追加。大型検索バー・AIチャット・人気の社内マニュアル6カテゴリ・最近閲覧した資料・お気に入り・AI回答パネル(回答+次にやること+関連資料)をガラスUI(glassmorphism)で実装
- **AIモーニングブリーフィング** — ダッシュボード最上部に「AIからのお知らせ」カード追加。返信漏れ・契約更新・売上目標達成率を社長が開いた瞬間に把握できる設計
- **KPI拡張** — 4枚→6枚。AI対応件数・Company Brain利用数・社員利用率を追加。売上カードを特別デザインで強調
- **アクティビティカラーコーディング** — 🟢成約 🟡予定 🔴要対応 🔵AIで視覚的に分類
- **クイックアクション7項目** — Company Brain検索・AIへ質問・契約書作成・顧客登録・広告作成・FAQ追加・社内規程編集
- **公式デザイントークン適用** — `--navy: #0A1B3D` / `--accent: #2563EB` / `--light-blue: #60A5FA` 等に統一
- `CURRENT_STATUS.md` のDashboard Versionを0.0→0.5に更新

---

## 2026-07-05 — 新章「11_Development_Principles.md」を新設

CEOより、今後のSmart Labo Works開発における基本原則を定めた新章の追加指示があった。PROJECT_BIBLEに散在していた「開発前に何を確認すべきか」「新機能をやるかどうかの判断基準」「デモ画面の位置づけ」といった暗黙のルールを、単一の章として明文化した。

### 新設ファイル

[11_Development_Principles.md](11_Development_Principles.md) — 主な内容は以下の通り。

- **開発前ルール(必須):** 新しい画面・機能・デモ・UIを作成する前に、PROJECT_BIBLE / Brand Bible / Design Bible / CURRENT_STATUS / CHANGELOGの最新版を必ず確認する。古い仕様・過去のブランドイメージを使用しない
- **Smart Labo Design Principle:** 「社長の時間を増やせるか」「Company OSの思想に沿っているか」「『会社を動かすAI。』というブランドコンセプトに一致しているか」「Smart Laboらしい高級感・シンプルさ・統一感があるか」「他社との差別化につながるか」の5項目チェックリスト。YESにならない機能は実装しない
- **デモ画面作成ルール:** デモ画面は単なるUIサンプルではなく、営業でお客様へ見せる「製品デモ」として設計する
- **Company Brain優先:** 今後作成する画面ではCompany Brainとの連携(検索/AIチャット/社内規程/業務マニュアル等)を常に検討する
- **Dashboard設計原則:** DashboardはCRMではなく**Company OS**。社長が毎朝最初に開く画面として設計する(表示すべきもの: AI提案/重要タスク/売上/契約状況/スケジュール/Company Brain/最新通知/経営状況)
- **ブランド維持:** ホームページ・Dashboard・Company Brain・営業資料・パンフレット・名刺・アプリすべてをBrand Bible・Design Bible・PROJECT_BIBLEの最新版に統一する
- **Version管理:** 仕様変更・ブランド変更・画面追加時は、CURRENT_STATUS・CHANGELOG・PROJECT_BIBLE・Roadmapを必ず更新する
- **最重要原則:** Smart Labo Worksは「AIチャット」ではなく「会社を進化させるCompany OS」である

### ファイル番号について

`00_Foundation` の01〜10がすべて既存ファイルで埋まっているため、「開発関連ドキュメント」の枠として設計されている `10`〜`19` の番号帯([README.md](README.md)「なぜこの番号体系なのか」参照)に `11` として新設した。既存ファイルの番号は変更していない。

### 「Company OS」という用語について

本章で初めて登場した表現。既存のブランドコピー階層(ブランドコピー「会社を動かすAI。」/ サービスコピー「経営を、ひとつにつなぐ。」/ 説明コピー「社長の右腕になるAI経営プラットフォーム。」、[00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md))自体は今回変更しておらず、Dashboard・Smart Labo Worksの性格を一言で表す補完的な位置づけとして扱う。正式にブランドコピー階層へ統合するかはCEOの今後の判断による。

### 更新したファイル

- 新規: [11_Development_Principles.md](11_Development_Principles.md)
- [README.md](README.md)(v3.2→v3.3) — ファイル構成・参照表・番号体系の説明を更新
- [10_Development_Rules.md](10_Development_Rules.md)(v2.1→v2.2)、[00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md)、[00_Foundation/08_SmartLaboWorks_Concept.md](00_Foundation/08_SmartLaboWorks_Concept.md)、[PROMPTS/DESIGN/Dashboard.md](../PROMPTS/DESIGN/Dashboard.md) — 関連ドキュメントへの相互参照を追加
- [CURRENT_STATUS.md](CURRENT_STATUS.md)(v2.6→v2.7)

**変更者:** Claude Code(Project Bible編集長)/ 指示: CEO

---

## 2026-07-05 — Company BrainをSmart Labo Works最大の差別化要素として正式formalize

CEOより「Company Brainは単なる『マニュアル検索』ではなく、Smart Labo Worksが提供する『会社の頭脳(Company Brain)』である」との明確な指示があり、ホームページの一セクションという扱いから、Smart Labo Works全体の中核基盤・最大の差別化要素として正式に位置づけを引き上げた。

### PROJECT_BIBLEへの反映

- [00_Foundation/08_SmartLaboWorks_Concept.md](00_Foundation/08_SmartLaboWorks_Concept.md)(v2.0→v3.0): Company Brainを中核基盤として追加。一般的なマニュアル検索・チャットボットとの違いを比較表で明記し、キャッチコピー「探す時間を、考える時間へ。」を制定
- [00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md)(v5.0→v5.1): Company Brain専用の機能コピーを追加。ブランド適用範囲に、ホームページ・Dashboard・営業資料・パンフレット・デモ画面すべてで統一ブランド化する方針を追記
- [00_Foundation/10_Future_Roadmap.md](00_Foundation/10_Future_Roadmap.md)(v2.0→v3.0): 「Company Brain 拡張ロードマップ」を新設し、動画検索/PDF検索/Word・Excel・PowerPoint検索/画像OCR/社内Wiki/議事録検索/音声検索/AI要約/多言語対応を記録

### 設計プロンプトの新設・更新

- **新設:** [PROMPTS/DESIGN/CompanyBrain.md](../PROMPTS/DESIGN/CompanyBrain.md)(v1.0) — Company Brainのコピー・3つの利用シナリオ(契約更新/資格手当/有給申請)・UI方針(ガラスUI・Smart Blue)・アイコン方針(Brain/Network/Knowledge/AI)・専用画面構成(検索バー/AIチャット/最近閲覧した資料/人気の社内マニュアル/お気に入り)を集約するマスタープロンプト。ホームページ・Dashboard・営業資料・パンフレット・デモ画面すべてで参照する正本とした
- [PROMPTS/DESIGN/Homepage.md](../PROMPTS/DESIGN/Homepage.md)(v3.0→v4.0): Company Brainセクション仕様をCompanyBrain.mdへの参照込みで全面更新
- [PROMPTS/DESIGN/Dashboard.md](../PROMPTS/DESIGN/Dashboard.md)(v2.1→v3.0): サイドバーナビ標準レイアウトに「Company Brain」を追加(専用アイコン付き、他項目に埋没させない扱い)
- [PROMPTS/DESIGN/SmartLabo_Design_Bible.md](../PROMPTS/DESIGN/SmartLabo_Design_Bible.md)(v2.2→v2.3): 関連ドキュメントに新設のCompanyBrain.mdを追加

### Homepage実装

- Company Brainセクションを全面拡張: Brain/Network/Knowledge/AIを連想する専用アイコンバッジを新設、キャッチコピー「探す時間を、考える時間へ。」を追加、知識ソースのチップ表示を6項目→9項目(社内規程/業務マニュアル/営業マニュアル/FAQ/教育資料/テンプレート/契約フロー/社内ルール/ベテラン社員のノウハウ)に拡充
- AIの回答に「関連資料」「次のアクション」を示すチップを追加し、AIが単に答えるだけでなく案内する存在であることを視覚化
- 3つの利用シナリオ(契約更新/資格手当/有給申請)を、質問→複数ステップの案内フローとして見せる新セクションを追加
- ガラスUI(glassmorphism: backdrop-filter blur + 半透明パネル + 繊細なボーダー)を採用し、Enterprise SaaSらしい高級感を演出
- デスクトップ(1440px/1024px)・モバイル(375px)でプレビュー確認済み。レイアウト崩れ・コンソールエラーなし

### 更新したファイル

- `WEBSITE/index.html`、`WEBSITE/css/style.css`
- [PROJECT_BIBLE/00_Foundation/08_SmartLaboWorks_Concept.md](00_Foundation/08_SmartLaboWorks_Concept.md)、[07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md)、[10_Future_Roadmap.md](00_Foundation/10_Future_Roadmap.md)
- 新規: [PROMPTS/DESIGN/CompanyBrain.md](../PROMPTS/DESIGN/CompanyBrain.md)
- [PROMPTS/DESIGN/Homepage.md](../PROMPTS/DESIGN/Homepage.md)、[Dashboard.md](../PROMPTS/DESIGN/Dashboard.md)、[SmartLabo_Design_Bible.md](../PROMPTS/DESIGN/SmartLabo_Design_Bible.md)、[README.md](../PROMPTS/DESIGN/README.md)
- [CURRENT_STATUS.md](CURRENT_STATUS.md)(v2.5→v2.6)
- [README.md](README.md)(v3.1→v3.2)

**変更者:** Claude Code(Lead Software Engineer / Chief Design Officer)/ 指示: CEO

---

## 2026-07-05 — Homepage v1.0 Release Candidate: 最終ブラッシュアップ

CEOより「現在のHomepageはVersion 0.9βとして十分な完成度に達している。今回の目的は新しいデザインを作ることではなく、Version 1.0公開に向けた最終ブラッシュアップを行うこと」との指示があった。「ホームページを見た企業経営者が『この会社に相談したい』『このシステムを導入してみたい』と思える品質」を目標に、5つの優先度に沿って対応した。

### Priority 1 — Company Brainセクションの新設

Smart Labo Worksの最大の差別化要素として、「主要機能」の直後に **Company Brain** セクションを新設した。タイトル「Company Brain」、サブタイトル「会社の知識を、AIへ。」、社内規程・業務マニュアル・営業マニュアル・FAQ・ノウハウの説明文、具体例のチップ表示(就業規則/資格制度/営業手順/契約更新方法/経費精算/AI利用ルール)、そして「探す→AIへ聞く」の体験を示すチャット形式のビジュアルモックアップで構成。ナビゲーションにも項目を追加した。

### Priority 2 — ダッシュボードのデモデータ表記

Hero内のダッシュボードモックアップ(対応中のタスク・本日の予定・未対応メール・今月の売上)が実績値と誤認されないよう、「DEMO」バッジと「※ 画面はイメージです。表示データはすべてサンプルであり、実績値ではありません。」の注記を追加した。

### Priority 3 — CTA導線の整理

「無料デモを見る」「導入相談をする」「資料ダウンロード」が同一の動作になっていた問題を修正した。「資料ダウンロード」(ヘッダー/Hero/CTA/フッターの計4箇所)に「準備中」タグを追加し、他の2つと機能的に区別。CTAセクション内で `href="#"` となっていたためページ先頭へ意図せずジャンプしていたバグを `#cta` に修正し、リンク切れを解消した。

**重要な判断:** CTAの実際の連絡先(メールアドレス等)は、PROJECT_BIBLE内に実在するものが確認できなかった。実在しない連絡先を捏造すると、実際の商談機会を取りこぼすリスクがあるため、CEOに確認したところ「今は連絡先なし、セクション内スクロールのままでよい」との判断を得た。CTAセクションに「正式なお問い合わせ窓口は現在準備中です」の注記を追加し、誠実さを保った。

### Priority 4 — ブランド品質の再確認

Company Brainのナビゲーション項目追加により、ヘッダーナビが5項目に増えたことで、1024px前後の画面幅でナビゲーションが2〜3行に折り返る表示崩れを発見した。ハンバーガーメニューへの切り替え幅を860px→1100pxに拡大することで解消した。あわせて余白・タイポグラフィ・カラー・ボタンサイズ・レスポンシブを再確認した。

### Priority 5 — 公開品質の確認

- ブランドに沿ったカスタム404ページ(`WEBSITE/404.html`)を新設
- OGP(og:title/description/image/url/site_name/locale)、Twitter Card、canonicalタグを追加
- favicon を512×512(120KB)から64×64(3.5KB)に最適化し、表示速度を改善
- 全内部リンク(アンカー)・全画像の読み込みを再確認(問題なし)

### 更新したファイル

- `WEBSITE/index.html`、`WEBSITE/css/style.css`、新規: `WEBSITE/404.html`
- `WEBSITE/img/logo/favicon.png`(最適化)
- [PROMPTS/DESIGN/Homepage.md](../PROMPTS/DESIGN/Homepage.md)(v2.1→v3.0) — Company Brainをページ構成・チェックリストへ反映
- [CURRENT_STATUS.md](CURRENT_STATUS.md)(v2.4→v2.5) — Homepage Version 0.9 beta→1.0 Release Candidate
- [README.md](README.md) — Version 3.0→3.1

**変更者:** Claude Code(Lead Software Engineer)/ 指示: CEO

---

## 2026-07-05 — Homepage(β版)公開完了

CEOがリポジトリのSettings→Pages設定を完了し、GitHub Pagesでの公開作業を進めた。

### 公開URL

**https://smartlabosukimagakusyuu-dev.github.io/SmartLabo/**

### 発生した問題と解決

初回・再試行を含む3回のデプロイが、いずれも「Deploy to GitHub Pages」ステップで失敗した。GitHub Actions APIでジョブのステップ単位のステータスを確認したところ、`Setup Pages`・`Upload artifact`までは成功するが、最終のデプロイ処理のみ失敗するパターンが一貫していた。ログ本文の閲覧には管理者権限のトークンが必要でClaude Codeからは参照できなかったため、症状から原因を推定した。

原因は、リポジトリの **Settings → Actions → General → Workflow permissions** が「リポジトリの内容とパッケージの読み取り権限」(読み取り専用)になっていたこと。ワークフローYAML内で `permissions: pages: write` を明示していても、リポジトリ設定側の上限が読み取り専用だと、それを超える権限は付与されない。CEOに設定変更(「読み取りおよび書き込み権限」への変更)を依頼し、再デプロイしたところ成功した。

### 公開前チェック(最終確認)

CSS・JS・Hero背景写真・ロゴアイコン・faviconのすべてについて、公開URL上で200 OKのレスポンスを確認した。

### 更新したファイル

- [CURRENT_STATUS.md](CURRENT_STATUS.md)(v2.3→v2.4) — 公開URL・トラブルシューティング経緯を記録
- `.github/workflows/pages.yml` — 再デプロイトリガー用の軽微なコメント追加

**変更者:** Claude Code(Lead Software Engineer)/ 対応: CEO

---

## 2026-07-04 — 暫定デプロイ先をGitHub Pagesに決定

CEOに確認したところ、Xserverはドメイン(`smartlaboworks.com`)取得のみで、サーバープランの契約がまだであることが判明した。「共有しながら改善する」というβ版公開の目的を踏まえ、CEOより「GitHubでは運用できないか」との提案があり、無料の静的サイトホスティングである**GitHub Pages**を暫定デプロイ先として採用した。

### 実施内容

[.github/workflows/pages.yml](../.github/workflows/pages.yml) を新設。`WEBSITE/` 配下への変更をトリガーに、GitHub Actions(`actions/upload-pages-artifact` + `actions/deploy-pages`)で自動デプロイするワークフローを構築した。`WEBSITE/` を直接デプロイ元とすることで、`/docs` 等への複製を避け、単一ソースを維持している。

### 残作業(CEO対応が必要)

Claude Codeが保有するツールにはGitHub REST API・`gh` CLIへのアクセス権がなく、リポジトリの Settings → Pages → Source を「GitHub Actions」に切り替える一度きりの操作は、**CEOご本人によるGitHub Web UIでの手動設定が必要。** 設定完了後、`master` ブランチへのpushをトリガーに自動デプロイが実行され、`https://smartlabosukimagakusyuu-dev.github.io/SmartLabo/` で公開される見込み。

Xserverのサーバー契約が完了しDNS設定が済み次第、正式ドメイン(`smartlaboworks.com`)への切り替えを行う。

### 更新したファイル

- 新規: `.github/workflows/pages.yml`
- [CURRENT_STATUS.md](CURRENT_STATUS.md)(v2.2→v2.3)
- [50_TODO.md](50_TODO.md)(v2.8→v2.9)

**変更者:** Claude Code(Lead Software Engineer)/ 指示: CEO

---

## 2026-07-04 — Homepage v0.9 beta: β版として一般公開

CEOより「ホームページをβ版として公開してください。目的は正式完成ではなく、共有しながら改善するための公開です。」との指示を受けた。Homepage Versionを **0.9 beta** として扱い、公開前チェックを実施した。

### 公開前チェック結果

| 項目 | 結果 |
|---|---|
| スマホ表示 | 375px幅で全セクションを確認。レイアウト崩れなし。モバイルナビ(ハンバーガーメニュー)の開閉も正常動作 |
| リンク切れ | 内部アンカー4件(`#service`/`#industries`/`#mission`/`#cta`)すべて解決を確認。フッターの法務ページリンク・CTAセクション内の3ボタンは、既知の未実装機能(プレースホルダー`#`)であり、リンク切れではない |
| CTAボタンの動作 | 「無料デモを見る」等のクリックで該当セクションへ正しくスクロールすることを確認 |
| 表示崩れ | デスクトップ(1440px)・モバイル(375px)双方で全8セクションをスクロール確認。崩れなし |
| 画像読み込み | Hero背景写真・ロゴアイコン・favicon、いずれも200 OKで読み込み成功を確認 |
| README/CURRENT_STATUS | [WEBSITE/README.md](../WEBSITE/README.md) に「公開状況」セクションを新設。本ファイルのHomepage Versionを更新 |

### 既知の未実装事項(β版として許容)

フォーム送信の実処理、プライバシーポリシー・利用規約ページ、Hero以外の実写真素材、真のベクター(SVG)ロゴデータ。いずれも「β版=共有しながら改善する」という公開目的に沿って、今回は許容している。

### Xserverへのデプロイについて

CEOよりXserverでの公開希望があったが、**FTP/SFTP接続情報(ホスト名・ユーザー名・パスワード・公開ディレクトリ)がまだ確認できていないため、実際のデプロイは未完了。** 情報を受け取り次第、デプロイを実施し公開URLを本ファイル・[CURRENT_STATUS.md](CURRENT_STATUS.md) に追記する。

### 更新したファイル

- [WEBSITE/README.md](../WEBSITE/README.md)(v1.0→v1.1) — 「公開状況」セクション新設
- [CURRENT_STATUS.md](CURRENT_STATUS.md)(v2.1→v2.2) — Homepage Version 0.5→0.9 beta

**変更者:** Claude Code(Lead Software Engineer)/ 指示: CEO

---

## 2026-07-04 — v3.0: 正式ロゴ確定・Brand Identity v5.0・Homepage v0.5

CEOより「正式ロゴを決定します。今回添付したロゴを株式会社スマートラボの正式ロゴとします。今後このロゴ以外は使用しません。」との明示的な決定があった。AI_WORKSPACE経由で2件のロゴ関連画像(2026-06-29提供の高解像度マスターデータ、2026-07-04提供のブランドシート)を確認し、正式ロゴとして確定・実装した。

### ロゴの内容

六角形フレームの中に、途切れた回路(サーキット)パターンで構成された「S」字のシンボル。ノード(円)と接続線が基板のような質感を加え、右上に四芒星(スパークル)が添えられている。カラーはSmart Blueのグラデーション。ロゴタイプは「Smart Labo」+「Works」+タグライン「会社を動かすAI。」の3階層構成。

これにより、以前 [00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md) に記載していた「途切れた2画のストローク」の暫定ロゴ仕様、および [DESIGN_ASSETS/Icons/Reference/](../DESIGN_ASSETS/Icons/Reference/) の別ロゴ案(角丸正方形+「+」マーク)は、**両方とも本ロゴに置き換えられ廃止**された。

### 実施内容

1. **[DESIGN_ASSETS/01_LOGO/](../DESIGN_ASSETS/01_LOGO/README.md) を新設**し、Primary/White/Dark/Icon/Favicon/SVG/PNG/WebPの8フォルダ構成でロゴ実データ(PNG/WebP)を格納。高解像度マスターデータからアイコンマーク・App Icon(1024×1024)・Favicon(512×512)を、ブランドシートからPrimary/White/Dark各バリエーションを切り出した
2. **[00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md) の「ロゴ」セクションを全面改訂**(v5.0)。「ロゴ使用ルール」を新設し、余白規定・最小サイズ・背景色・使用禁止例・用途別推奨サイズ(ヘッダー/フッター/Dashboard/App Icon等)を正式制定
3. **Homepageへ実装**(v0.5): ヘッダー・フッターの暫定ロゴマークSVGを正式ロゴ画像に差し替え、faviconも正式ロゴに更新
4. 旧 [DESIGN_ASSETS/Logo/](../DESIGN_ASSETS/Logo/README.md) は廃止し [01_LOGO/](../DESIGN_ASSETS/01_LOGO/README.md) へ移行

### 既知の制約

真のベクター(SVG)データは提供されておらず、格納しているPNG/WebPは提供画像からのラスター書き出しである。Web表示には十分だが、大判印刷等で使用する場合は別途ベクターデータの取得を検討する必要がある(詳細は [01_LOGO/README.md](../DESIGN_ASSETS/01_LOGO/README.md) の「既知の制約」参照)。

### 更新したファイル

- 新規: [DESIGN_ASSETS/01_LOGO/](../DESIGN_ASSETS/01_LOGO/README.md) 配下(Primary/White/Dark/Icon/Favicon/SVG/PNG/WebPの各フォルダ、PNG・WebP計18ファイル)
- [00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md)(v4.4→v5.0)
- [DESIGN_ASSETS/Logo/README.md](../DESIGN_ASSETS/Logo/README.md) — 廃止・リダイレクト化
- [DESIGN_ASSETS/README.md](../DESIGN_ASSETS/README.md)(v2.3→v2.4)
- [50_TODO.md](50_TODO.md)(v2.6→v2.7)
- [README.md](README.md) — Version 2.9→3.0
- [CURRENT_STATUS.md](CURRENT_STATUS.md)(v2.0→v2.1)
- `WEBSITE/index.html`、新規: `WEBSITE/img/logo/`

**利用した素材:** `AI_WORKSPACE/INBOX/LOGO/ChatGPT Image 2026年6月29日 10_09_13.png`(高解像度マスター)、`AI_WORKSPACE/INBOX/LOGO/ChatGPT Image 2026年7月4日 22_30_48.png`(ブランドシート)
**正式保存した場所:** [DESIGN_ASSETS/01_LOGO/](../DESIGN_ASSETS/01_LOGO/README.md)
**不要になった素材:** なし(元データは [AI_WORKSPACE/COMPLETED/LOGO/](../AI_WORKSPACE/COMPLETED/README.md) に履歴保管)

**変更者:** Claude Code(Project Bible編集長)/ 指示: CEO

---

## 2026-07-03 — Homepage v0.4: Hero背景に実写真を実装、アイコンをバッジスタイルへ刷新

CEOより「HIROとICONの素材をファイルに入れたので、ホームページ改修して」との指示を受け、AI_WORKSPACE経由で提供された2件の素材を確認・実装した。

### Hero背景(AI_WORKSPACE/INBOX/HERO/)

CEOが提供した「HERO背景素材(バリエーション)」シート(1枚の比較用画像、5つの背景候補+オーバーレイ・グラデーション素材を収録)を確認したところ、前回のモックアップとは異なり、**テキスト・ロゴが焼き込まれていない背景単体データ**だった。境界を検出して5点+補助素材6点に分割し、番号ラベルを除去したうえで格納した。

- [DESIGN_ASSETS/01_HERO/Backgrounds/](../DESIGN_ASSETS/01_HERO/README.md) に5点すべての実データ(`.webp`)を格納(**格納完了**)
- [DESIGN_ASSETS/01_HERO/Overlays/](../DESIGN_ASSETS/01_HERO/Overlays/README.md) を新設し、補助素材6点(オーバーレイ3点・グラデーション3点)を格納
- `hero_background_02_city_network.webp`(都市とAIネットワークが1枚に同居する構図)をHomepage Hero([WEBSITE/index.html](../WEBSITE/index.html))に実装。手描きのSVG都市シルエット・ネットワークは実写真に置き換え、下部260px(モバイル150〜170px)の帯状レイアウトで配置し、Navyグラデーションでテキストとの可読性を確保した
- 以前CEOが選定していた「朝焼け」候補(`01_sunrise_city`)はAIネットワークが写っていなかったため、今回は不採用としアーカイブへ変更。旧モックアップ版`05_waterfront_city`にあった禁止ビジュアル(六角形)は、新しい背景単体データには含まれていないことも確認した
- 元データ(比較用シート)は [AI_WORKSPACE/COMPLETED/HERO/](../AI_WORKSPACE/COMPLETED/README.md) に履歴保管

### Homepageアイコン(AI_WORKSPACE/INBOX/ICONS/)

CEOが提供した画像は、単体のアイコンではなく**Homepageの別デザイン案の全体モックアップ**だった。3つの要素(アイコンのバッジスタイル/多色配色/別ロゴ案)を切り分けて判断した。

- **採用:** アイコンの「バッジスタイル」(角丸正方形の塗りバッジ+白抜きアイコン)。「主要機能」「社長の1日」セクションの計10アイコンに、Smart Blue Family単色のグラデーションバッジとして反映
- **不採用:** 多色配色(青・水色・紫)。ブランドルール「Smart Blueのみ」に反するため
- **不採用・要確認:** 別ロゴ案(角丸正方形+「+」マーク)。公式ロゴ仕様と異なり、過去の「仕様と実装の食い違い」の教訓からCEO未承認では変更しない方針とした
- 参考資料は [DESIGN_ASSETS/Icons/Reference/](../DESIGN_ASSETS/Icons/Reference/) に格納。元データは [AI_WORKSPACE/COMPLETED/ICONS/](../AI_WORKSPACE/COMPLETED/README.md) に履歴保管

### 確認事項

デスクトップ(1440px)・モバイル(375px)双方でプレビュー確認。モバイルでHero写真とCTAボタンが重なり可読性が落ちる問題を発見し、ブレークポイントごとに写真の高さとHero下部余白を調整して解消した。コンソールエラーなし。

### 更新したファイル

- `WEBSITE/index.html`、`WEBSITE/css/style.css`、新規: `WEBSITE/img/hero/hero_background_02_city_network.webp`
- [DESIGN_ASSETS/01_HERO/Backgrounds/](../DESIGN_ASSETS/01_HERO/README.md) 配下5ファイル(各`.md`をv1.1→v1.2、`.webp`を新規格納)
- 新規: [DESIGN_ASSETS/01_HERO/Overlays/](../DESIGN_ASSETS/01_HERO/Overlays/README.md)
- [DESIGN_ASSETS/01_HERO/README.md](../DESIGN_ASSETS/01_HERO/README.md)(v1.1→v1.2)
- 新規: [DESIGN_ASSETS/Icons/Reference/](../DESIGN_ASSETS/Icons/Reference/)、[DESIGN_ASSETS/Icons/README.md](../DESIGN_ASSETS/Icons/README.md)(v1.0→v1.1)
- [DESIGN_ASSETS/README.md](../DESIGN_ASSETS/README.md)(v2.2→v2.3)
- [50_TODO.md](50_TODO.md)(v2.5→v2.6)
- [README.md](README.md)(PROJECT_BIBLE) — Version 2.8→2.9
- [CURRENT_STATUS.md](CURRENT_STATUS.md)(v1.9→v2.0)

**利用した素材:** `AI_WORKSPACE/INBOX/HERO/ChatGPT Image 2026年7月3日 21_59_28.png`、`AI_WORKSPACE/INBOX/ICONS/ChatGPT Image 2026年7月3日 11_17_55.png`
**正式保存した場所:** [DESIGN_ASSETS/01_HERO/Backgrounds/](../DESIGN_ASSETS/01_HERO/README.md)・[Overlays/](../DESIGN_ASSETS/01_HERO/Overlays/README.md)・[DESIGN_ASSETS/Icons/Reference/](../DESIGN_ASSETS/Icons/Reference/)
**不要になった素材:** なし(元データはAI_WORKSPACE/COMPLETED/へ履歴保管)

**変更者:** Claude Code(Lead Software Engineer)/ 提供: CEO

---

## 2026-07-03 — Homepage v0.3: Hero背景をCSS/SVGで強化

CEOより「ホームページのデザイン修正」の指示を受けた。実装用の背景単体データ(写真)がまだ届いていないため、新規バイナリ資産を追加せず、既存のCSS/SVGのみでHero背景の視覚的な密度を高める改善を行った。

### 修正内容

- **都市シルエット:** 左右の2クラスターのみだった建物配置を、フルワイド(0〜1440px)にわたる連続した4層のスカイラインに拡張。奥行きを出すため4段階の紺系トーンを使い分け、高層ビルの一部に小さな「灯り」(Smart Blue Light の点)を追加
- **AIネットワーク:** ノード数を7→14に増やし、`feGaussianBlur`によるグロー効果を追加。表示範囲を55%→62%幅・260px→300px高に拡大し、より存在感のある構成に変更
- **新規追加:** 控えめなドットグリッドのテクスチャ層(`.hero__grid`)と、Smart Blueのアンビエントグロー(`.hero__glow`)を追加し、Stripe/Datadog等の参照企業に近い「上品なテック感」を演出
- 使用色はすべて既存の公式パレット(Navy / Smart Blue Family)の範囲内。禁止ビジュアル(ネオン・六角形・過度なサイバー演出)は使用していない

### 確認事項

デスクトップ(1440px)・モバイル(375px)双方でプレビュー確認し、コンソールエラーなし、テキストの可読性への影響なしを確認。[PROMPTS/DESIGN/Homepage.md](../PROMPTS/DESIGN/Homepage.md) の「Heroの背景に未来都市+控えめなAIネットワークが表現されているか」「使用カラーはNavy/White/Smart Blue/Light Grayの範囲内か」のチェック項目に適合。

### 更新したファイル

- `WEBSITE/index.html`、`WEBSITE/css/style.css`
- [CURRENT_STATUS.md](CURRENT_STATUS.md)(v1.8→v1.9) — Homepage Versionを0.2→0.3に更新

**変更者:** Claude Code(Lead Software Engineer)/ 指示: CEO

---

## 2026-07-03 — v2.8: AI_WORKSPACE初運用、Hero候補モックアップを受領

CEOが [AI_WORKSPACE/INBOX/HERO/](../AI_WORKSPACE/INBOX/README.md) に、Hero候補5点をまとめた比較用画像(`ChatGPT Image 2026年7月3日 17_03_07.png`)を格納した。AI_WORKSPACE新設(v2.7)後、初めての実運用となった。

### 確認内容と発見した問題

Claude Codeが画像を確認したところ、以前レビュー済みの5候補(朝日・都市ネットワーク・雲海都市・夜景・水辺都市)と同一の世界観だったが、**Smart Laboロゴ・見出し「会社を動かすAI。」・3つのCTAボタン・β版バッジが焼き込まれた「完成イメージのモックアップ」**であることが判明した。このままCSSの背景画像として実装すると、実際のHTML(本物のロゴ・見出し・ボタン)がこの焼き込み済みテキストの上に重なり、文字が二重に表示されてしまう。

CEOに確認を仰ぎ、「参考画像として保管し、背景単体版を別途依頼する」方針で進めることを決定した。

### 実施内容

1. 比較用合成画像を、境界を検出したうえで5枚に精密分割
2. [DESIGN_ASSETS/01_HERO/Mockups/](../DESIGN_ASSETS/01_HERO/Mockups/README.md) を新設し、参考資料として5点を格納(実装からは直接参照しないことを明記)
3. 元の合成画像は履歴として [AI_WORKSPACE/COMPLETED/HERO/](../AI_WORKSPACE/COMPLETED/README.md) へ保管
4. [DESIGN_ASSETS/01_HERO/Backgrounds/](../DESIGN_ASSETS/01_HERO/README.md) 配下の5つのメタデータファイルすべてに、モックアップへの相互参照と「実装用の背景単体データは引き続き未格納」である旨を追記
5. [50_TODO.md](50_TODO.md) のタスクを更新(モックアップ格納は完了、背景単体データの格納・実装は引き続き未対応)

### 更新したファイル

- 新規: [DESIGN_ASSETS/01_HERO/Mockups/](../DESIGN_ASSETS/01_HERO/Mockups/README.md) と画像5点
- `DESIGN_ASSETS/01_HERO/Backgrounds/hero_background_01〜05_*.md`(各v1.0→v1.1)
- [DESIGN_ASSETS/01_HERO/README.md](../DESIGN_ASSETS/01_HERO/README.md)(v1.0→v1.1)
- [DESIGN_ASSETS/README.md](../DESIGN_ASSETS/README.md)(v2.1→v2.2)
- [50_TODO.md](50_TODO.md)(v2.4→v2.5)
- [README.md](README.md)(PROJECT_BIBLE) — Version 2.7→2.8
- [CURRENT_STATUS.md](CURRENT_STATUS.md)(v1.7→v1.8)

**利用した素材:** `AI_WORKSPACE/INBOX/HERO/ChatGPT Image 2026年7月3日 17_03_07.png`(Hero候補5点の比較用合成画像)
**正式保存した場所:** [DESIGN_ASSETS/01_HERO/Mockups/](../DESIGN_ASSETS/01_HERO/Mockups/README.md)(参考資料として。実装用の背景単体データは別途必要)
**不要になった素材:** なし(元データは [AI_WORKSPACE/COMPLETED/HERO/](../AI_WORKSPACE/COMPLETED/README.md) に履歴保管)

**変更者:** Claude Code(Project Bible編集長)/ 提供: CEO

---

## 2026-07-03 — v2.7: AI_WORKSPACE新設(ChatGPT発素材の正式な受け渡し経路)

CEOより、ChatGPTから提供される画像・資料・参考デザイン・プロンプトを、Claude Codeが安全かつ確実に受け取るための専用フォルダとして `AI_WORKSPACE/` を新設する指示を受けた。背景として、Hero背景候補5点の実データ格納作業において「チャット上の画像をファイル化する手段がない」という受け渡し経路の不備が繰り返し発生していたことがある。

### 新設した構成

```
AI_WORKSPACE/
├── INBOX/          ← ChatGPTが作成した素材の受け取り口(未確認・未処理)
│   ├── HERO/ BACKGROUND/ DASHBOARD/ LOGO/
│   └── ICONS/ INDUSTRY/ REFERENCE/ PROMPTS/
├── PROCESSING/      ← Claude Codeが確認・作業中の素材
├── COMPLETED/       ← 作業完了・DESIGN_ASSETSへ正式登録済みの履歴
└── ARCHIVE/         ← 不採用・不要になった素材の保管(理由付き)
```

### 運用ルール(6ステップ)

① ChatGPTが素材を作成 → ② INBOXへ保存 → ③ Claude Codeが確認 → ④ PROCESSINGへ移動 → ⑤ 作業完了後DESIGN_ASSETSへ正式登録 → ⑥ COMPLETEDへ履歴保存。

**今後、ChatGPT発の素材を [DESIGN_ASSETS/](../DESIGN_ASSETS/README.md) へ直接保存することは禁止。** Claude Codeは新しい素材を利用する前に必ず `AI_WORKSPACE/INBOX` を確認し、作業完了後は「どの素材を使用したか」「正式保存した場所」「不要になった素材」を報告する。

### 更新したファイル

- 新規: `AI_WORKSPACE/README.md`、`INBOX/README.md`(8サブフォルダ分含む)、`PROCESSING/README.md`、`COMPLETED/README.md`、`ARCHIVE/README.md`
- [60_Editorial_Workflow.md](60_Editorial_Workflow.md)(v2.1→v2.2) — 「AI_WORKSPACE運用フロー」新設、作業完了報告フォーマットに項目追加
- [DESIGN_ASSETS/README.md](../DESIGN_ASSETS/README.md)(v2.0→v2.1) — AI_WORKSPACE経由ルールを運用ルール第一項目として追加
- [30_AI_Rules.md](30_AI_Rules.md)(v2.1→v2.2)
- [README.md](README.md)(ルート) — フォルダ構成にAI_WORKSPACEを追加
- [README.md](README.md)(PROJECT_BIBLE) — Version 2.6→2.7
- [CURRENT_STATUS.md](CURRENT_STATUS.md)(v1.6→v1.7)

**変更者:** Claude Code(Project Bible編集長)/ 指示: CEO

---

## 2026-07-03 — v2.6: Hero背景画像5点をDesign Assetsとして正式管理

CEOより、Homepage Hero背景の候補として提示された5点の画像を「単なる参考画像」ではなく**正式なブランド資産**として管理するよう指示を受けた。同じ世界観を将来再現できるよう、画像ファイルごとにプロンプト・用途・バージョンをセットで保存する運用を制定した。

### 新設した「番号付きユースケースフォルダ」体系

生成AI画像のように複数候補から選定するプロセスを伴う素材は、型別フォルダ(Logo/Icons等)とは別に、用途ごとに番号を振ったフォルダ(`01_HERO/` など)で管理し、画像ファイルと同名の `.md` メタデータをペアで保存する方式を [DESIGN_ASSETS/README.md](../DESIGN_ASSETS/README.md) に正式制定した。

### Hero背景候補5点の管理状況

[DESIGN_ASSETS/01_HERO/Backgrounds/](../DESIGN_ASSETS/01_HERO/README.md) に、各候補のメタデータ(`.md`)を作成した。

| ファイル | ステータス |
|---|---|
| `hero_background_01_sunrise_city.webp` | **採用中**(Homepage Hero、CEO選定) |
| `hero_background_02_city_network.webp` | 候補(アーカイブ) |
| `hero_background_03_cloud_city.webp` | 候補(アーカイブ) |
| `hero_background_04_night_network.webp` | 候補(アーカイブ) |
| `hero_background_05_waterfront_city.webp` | **使用不可**(禁止ビジュアル「六角形」に該当) |

### 未解決事項

チャット上で共有された画像をファイルとして保存・格納する手段が現状ないため、**5点とも実画像データ(`.webp`)はまだリポジトリに格納されていません。** メタデータのみ先行整備した状態です。CEOよりファイルパスの提供、または別の受け渡し方法の指示を受け次第、実データを格納しHomepageへ実装します。

### 更新したファイル

- 新規: [DESIGN_ASSETS/01_HERO/README.md](../DESIGN_ASSETS/01_HERO/README.md)、`Backgrounds/hero_background_01〜05_*.md`(5ファイル)
- [DESIGN_ASSETS/README.md](../DESIGN_ASSETS/README.md)(v1.0→v2.0) — 番号付きユースケースフォルダ体系とDesign Assets一覧を追加
- [50_TODO.md](50_TODO.md)(v2.3→v2.4) — Hero背景画像の格納・実装タスクを追加
- [CURRENT_STATUS.md](CURRENT_STATUS.md)(v1.5→v1.6)
- [README.md](README.md) — PROJECT_BIBLE Version 2.5→2.6

**変更者:** Claude Code(Project Bible編集長)/ 指示: CEO

---

## 2026-07-03 — Homepage v0.2: 正式カラー・ロゴへの反映漏れを修正

CEOより「ロゴもデザインも全く違う」との指摘を受けた。原因は、直前のブランドキット確定作業(PROJECT_BIBLE v2.4/v2.5)で仕様は更新したものの、**実装済みのHomepage(`WEBSITE/`)への反映が漏れていた**ことだった。CURRENT_STATUS.mdには「カラー更新待ち」と記録していたが、実際の色更新作業を後回しにしたまま次のタスクに進んでしまっていたため、指摘を受けて直ちに反映した。

### 修正内容

- **カラートークン:** `WEBSITE/css/style.css` の全カラー変数・ハードコードされたhexコードを、正式パレット(Primary Navy `#0A1B3D`、Smart Blue `#2563EB`、Light Blue `#60A5FA`)に置換。Hero・各セクションのグラデーション、アイコン、チェックマークもすべて対象。
- **ロゴ:** 暫定の角丸バッジ+抽象ストロークのマークを廃止し、[00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md) 記載の「途切れた2画のストローク(左上から右下へ流れる筆致)」仕様に沿った、より明確に「S」と読み取れるシンボルへ再デザイン。Navy背景(ヘッダー・フッター)のため白抜き版を使用。
- **注記:** 現在のSシンボルは、CEOから共有された参考画像をもとにClaude Codeが解釈して作成した**暫定デザイン**です。正式なロゴデータ(SVG/PNG)が用意され次第、[DESIGN_ASSETS/Logo/](../DESIGN_ASSETS/Logo/README.md) に格納し差し替えます。

### 教訓(60_Editorial_Workflowへの反映を検討)

PROJECT_BIBLE側の仕様更新と、実装(`WEBSITE/`等)への反映は別作業であり、片方だけ完了した状態を「完了」と報告しないよう注意する。CURRENT_STATUS.mdに「反映待ち」と記載した項目は、次の関連作業に着手する前に必ず解消する。

### 更新したファイル

- `WEBSITE/css/style.css`、`WEBSITE/index.html`
- [CURRENT_STATUS.md](CURRENT_STATUS.md)(v1.4→v1.5) — Homepage Versionを0.1→0.2に更新

**変更者:** Claude Code(Lead Software Engineer)/ 指摘: CEO

---

## 2026-07-03 — v2.5: Smart Blue Family確定とDESIGN_ASSETSの新設

CEOより、前回報告した2点の判断について確認・指示を受けた。

### ① Smart Blue Familyの確定

Claude Codeの解釈(Light Blue・Accent Blueを独立したブランドカラーではなくSmart Blue Familyの階調として扱う)がCEOにより確認・確定した。**ブランドカラーはSmart Blue(`#2563EB`)ただ一つ**であり、Light Blue・Accent Blueはその階調である、という位置づけをPROJECT_BIBLE・Design Bible全体で統一した。

### ② DESIGN_ASSETSフォルダの新設

現在のロゴ参考画像は仕様検討用の参考資料であり、正式なロゴデータ(SVG/PNG等)は今後別途用意されることが判明した。それまでの間、実データの置き場所を先行して整備するため、`DESIGN_ASSETS/` フォルダを新設した。

```
DESIGN_ASSETS/
├── Logo/ Background/ Icons/ Dashboard/
├── Buttons/ Cards/ Graphics/ Images/
└── Colors/ Fonts/
```

**役割分担の整理:**

| フォルダ | 役割 |
|---|---|
| PROJECT_BIBLE | 思想・原則("なぜ") |
| BRAND/、DESIGN/ | 仕様・トークンの文書化("何を") |
| **DESIGN_ASSETS/(新設)** | **実データそのもの("実物")** |

`BRAND/` と `DESIGN/` から、ロゴ・アイコン等の実ファイルに関する記述を [DESIGN_ASSETS/](../DESIGN_ASSETS/README.md) への参照に更新し、両フォルダは仕様・トークンの文書化に専念する役割へ整理した。

### 更新・新設したファイル

- 新規: `DESIGN_ASSETS/README.md`、および10サブフォルダそれぞれの `README.md`(Logo/Background/Icons/Dashboard/Buttons/Cards/Graphics/Images/Colors/Fonts)
- [00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md)(v4.3→v4.4) — Smart Blue Family確定を明記、実データ格納先をDESIGN_ASSETSへ変更
- [20_UI_UX_Rules.md](20_UI_UX_Rules.md)(v4.2→v4.3) — 「Design Assets運用ルール」セクションを新設
- [BRAND/README.md](../BRAND/README.md)(v1.1→v1.2)、[DESIGN/README.md](../DESIGN/README.md)(v1.1→v1.2) — 実データ格納先をDESIGN_ASSETSへ変更、役割を仕様・トークンの文書化に整理
- [DESIGN/system/design-tokens.md](../DESIGN/system/design-tokens.md)、[50_TODO.md](50_TODO.md)(v2.2→v2.3) — 格納先パスの参照を更新
- ルート [README.md](../README.md) — フォルダ構成図にDESIGN_ASSETSを追加

**変更者:** Claude Code(Project Bible編集長 / Lead Software Engineer 兼 Knowledge Manager)/ 指示: CEO

---
---

## 2026-07-03 — v2.4: ブランドキット(ロゴ・カラー・アイコン・UIコンポーネント)の正式反映

CEOより、ロゴ・背景・ダッシュボードUI・アイコン・UIコンポーネント・タイポグラフィ・カラーパレット・グラフィック要素を1枚にまとめたブランドキット参照資料が提供された。これを正式なデザインシステムとしてPROJECT_BIBLEへ反映した。

### 正式カラーパレット(8色)

| カラー | Hex |
|---|---|
| Primary Navy | `#0A1B3D` |
| Smart Blue | `#2563EB` |
| Light Blue | `#60A5FA` |
| Accent Blue | `#00D4FF` |
| White | `#FFFFFF` |
| Light Gray | `#F3F6FA` |
| Gray | `#64748B` |
| Dark Gray | `#1E293B` |

「アクセントカラーはSmart Blueのみ」という既存原則は、単一色ではなく **「Smart Blueファミリー(4階調)」** として精緻化した。あわせて、ステータスバッジ(完了=緑/進行中=青/未着手=グレー/遅延=赤)の意味色と、写真背景における自然な暖色(朝日・夕景)を、ブランドアクセントとは区別される例外として明記した。これにより、直前のHero背景修正で「UIはSmart Blueのみ、写真は暖色可」とした暫定判断が、正式にPROJECT_BIBLEへ組み込まれた形になる。

### ロゴ

カラー版・白抜き版・アイコン単体版の3バリエーションを定義。実データ(SVG/PNG)は未格納のため、[50_TODO.md](50_TODO.md) に追加。

### アイコンセット・UIコンポーネント

AI分析・タスク管理・顧客管理・売上管理・レポート・通知・設定・セキュリティの8アイコン、およびボタン(プライマリ/セカンダリ)・テキストリンク・入力フィールド(通常/フォーカス)・カード・ステータスバッジの型を定義。

### ダッシュボード標準レイアウト

サイドバーナビ(ダッシュボード/タスク管理/顧客管理/売上管理/AI分析/レポート/設定)+ 統計カード + データ可視化(折れ線グラフ+ドーナツチャート)+「AIからの提案」パネル + 「最近のアクティビティ」パネルという標準構成を [PROMPTS/DESIGN/Dashboard.md](../PROMPTS/DESIGN/Dashboard.md) に明記。現在のCurrent Task「Dashboard UI設計」に直接活用する。

### 更新・新設したファイル

- [00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md)(v4.2→v4.3) — ロゴ・カラーパレット・タイポグラフィ階層を追加
- [20_UI_UX_Rules.md](20_UI_UX_Rules.md)(v4.1→v4.2) — 配色・UIコンポーネントの型を更新
- [PROMPTS/DESIGN/SmartLabo_Design_Bible.md](../PROMPTS/DESIGN/SmartLabo_Design_Bible.md)(v2.1→v2.2) — カラー節を同期
- [PROMPTS/DESIGN/Dashboard.md](../PROMPTS/DESIGN/Dashboard.md)(v2.0→v2.1) — 標準レイアウトを追加
- 新規: `BRAND/colors/palette.md`、`DESIGN/system/design-tokens.md`
- [BRAND/README.md](../BRAND/README.md)、[DESIGN/README.md](../DESIGN/README.md) — 新規ファイルへの導線を追加

### 既知の残タスク

- Homepage(`WEBSITE/`)は暫定色(`#0A1A3C` 等)で実装済みのため、正式パレットへの色置換が必要(Next Taskに記録)
- ロゴ・アイコンの実データ(SVG/PNG)は未格納

**変更者:** Claude Code(Project Bible編集長 / Lead Software Engineer 兼 Knowledge Manager)/ 指示: CEO

---

## 2026-07-03 — Homepage Hero背景の視認性修正(WEBSITE/、Version 0.1継続)

CEOより「PROJECT_BIBLEとCURRENT_STATUS.mdを確認したうえで、Hero→CTA→機能バー→レスポンシブの優先順位でトップページ作成に入る」旨の指示を受けたが、CURRENT_STATUS.md確認の結果、Homepage v0.1として該当範囲は実装済みであることが判明。無駄な再実装を避けるため、既存実装をDesign Bible v2.1の基準でレビューし、発見した不具合を修正する方針とした(CEO承認済み)。

### レビューで発見した不具合と修正

- **Hero背景の視認性不足:** 都市シルエット・AIネットワークの背景SVGが、Heroの2カラムコンテンツ(テキスト・ダッシュボードモックアップ)とほぼ完全に重なっており、実質的に見えない状態だった。[PROMPTS/DESIGN/Homepage.md](../PROMPTS/DESIGN/Homepage.md)の「Heroの背景に未来都市+控えめなAIネットワークが表現されているか」というチェック項目を満たしていなかったため、以下を修正:
  - AIネットワークをヘッダー直下〜ダッシュボード上部の空きスペース(右上)に再配置し、コンテンツと重ならないようにした
  - 都市シルエットをHero下部の専用帯(210px)に固定し、Hero下部の余白を96px→152pxに拡大して視認できる領域を確保
  - 建物のシルエットカラーを明るめのNavy階調(#1B4488, #234E93等)に変更し、背景グラデーションとのコントラストを改善
- CTA・機能バー(主要機能セクション)・レスポンシブ(モバイル/タブレット/デスクトップ)は、既存実装がPROJECT_BIBLEの基準を満たしていることを確認(修正なし)

### 更新したファイル

- `WEBSITE/index.html`、`WEBSITE/css/style.css`
- [CURRENT_STATUS.md](CURRENT_STATUS.md)(v1.1→v1.2)

**変更者:** Claude Code(Lead Software Engineer)/ 指示: CEO

---

## 2026-07-03 — Homepage初回実装(WEBSITE/、Version 0.1)

CEOよりコーポレートサイト トップページの実制作指示を受領。添付の参考画像をレイアウトの雰囲気の参考としつつ、内容が [PROMPTS/DESIGN/Homepage.md](../PROMPTS/DESIGN/Homepage.md) と矛盾する箇所(CTA数、色数、朝日の表現色)はPROJECT_BIBLEを優先して実装した。

### 実装内容(`WEBSITE/index.html` / `css/style.css` / `js/main.js`)

- 8セクション構成: Hero → 社長の1日をAIが支える流れ → 主要機能 → 導入メリット → 対応業種 → Mission → CTA → Footer
- Hero: 左テキスト(ブランドコピー/サービスコピー/リード文)+ 右にSmart Labo Works管理画面のモックアップ、背景に控えめな都市シルエット+AIネットワーク、CTA3つ(無料デモを見る/導入相談をする/資料ダウンロード)
- 配色はNavy / White / Smart Blue / Light Grayのみを使用。「朝日」の表現は、参考画像のオレンジ系グローではなく、カラールール([00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md))を優先しSmart Blueの光として表現
- 主要機能セクションにサービスコピー「経営を、ひとつにつなぐ。」・説明コピー「社長の右腕になるAI経営プラットフォーム。」を使用し、機能→価値の変換パターンを適用
- 「対応業種」は実際の導入実績が存在しない現状(β開発中)を踏まえ、「ご利用いただいています」ではなく「ご活用を想定しています」という誠実な表現に調整([00_Foundation/05_Value.md](00_Foundation/05_Value.md)「誠実さ」原則に基づく)
- 「導入メリット」の統計は、裏付けのない具体的な数値(例: 稼働削減率)を掲載せず、24時間/365日という設計上の事実と、定性的な価値表現に留めた

### 既知の未実装事項

- フォーム送信の実処理(CTAは現状リンクのみ)
- プライバシーポリシー・利用規約の実ページ(`LEGAL/`が未整備のためリンクはプレースホルダー)
- 実写真素材(色ブロック+アイコンで代用中)

### 更新したファイル

- 新規: `WEBSITE/index.html`、`WEBSITE/css/style.css`、`WEBSITE/js/main.js`
- [CURRENT_STATUS.md](CURRENT_STATUS.md)(v1.0→v1.1) — Homepage Versionを0.0→0.1に更新、Current TaskをDashboard UI設計に更新

**変更者:** Claude Code(Lead Software Engineer)/ 指示: CEO

---

## 2026-07-03 — v2.3: CURRENT_STATUS.md新設とLead Software Engineer/Knowledge Manager体制の確立

CEOより、Claude CodeをPROJECT_BIBLEおよびGitHubリポジトリの管理責任者として位置づけ、常に最新版を維持する体制を確立するよう指示を受けた。ChatGPT・Claude Code・将来のAI(Codex等)が同じPROJECT_BIBLEを参照しながら開発を進めるための「同期の仕組み」を整備した。

### 新規追加

- **[CURRENT_STATUS.md](CURRENT_STATUS.md)** — PROJECT_BIBLE直下に新設。Project Bible Version・Brand Version・Design Bible Version・Homepage Version・Dashboard Version・Smart Labo Works Version・Current Project・Current Task・Next Task・Last Update・Maintainerの11項目を管理し、ChatGPTとの新セッション開始時に共有する「5行サマリー」を用意した。
  - Homepage/Dashboard/Smart Labo Worksの各Versionは、設計プロンプトのバージョンとは別軸の「実装進捗」であることを明記。2026-07-03時点でこれらは実装未着手のため、正直に `0.0` として記録した(架空の進捗は記載していない)。

### 役割・運用ルールの拡張

- [60_Editorial_Workflow.md](60_Editorial_Workflow.md)(v1.0→v2.0)
  - Claude Codeの役割を「Project Bible編集長」から「**Project Bible編集長 / Lead Software Engineer 兼 Knowledge Manager**」に拡張
  - **GitHub運用フロー(10ステップ)** を新設: git status確認 → 最新版取得 → PROJECT_BIBLE確認 → 作業 → 動作確認 → CURRENT_STATUS更新 → CHANGELOG更新 → コミット → push → 報告
  - **コミットメッセージ規約** を新設: `feat:` `docs:` `design:` `fix:` `refactor:` `style:` `chore:`
  - **作業完了時の報告フォーマット** を新設: 変更/追加/削除ファイル、PROJECT_BIBLE・CURRENT_STATUS・CHANGELOG更新の有無、現在のVersion一覧、コミットメッセージ、次にやるべき作業
  - **推測で判断しないエスカレーションルール** を新設: PROJECT_BIBLEに記載のない重要判断は、CEOへの確認またはChatGPTとの協議提案を必須とする
- [10_Development_Rules.md](10_Development_Rules.md)(v2.0→v2.1) — コミットメッセージ規約を反映
- [README.md](README.md)(v2.2→v2.3) — CURRENT_STATUS.mdへの導線、利用ルール7項目目(CURRENT_STATUS.mdを常に最新に保つ)、Git運用ルールの更新

**変更者:** Claude Code(Project Bible編集長 / Lead Software Engineer 兼 Knowledge Manager)/ 指示: CEO

---

## 2026-07-03 — v2.2: Enterprise AI Platform方針の再確認とブランドコピー階層の制定

CEOより改めて「Enterprise AI Platformというブランド思想へ変更する」という指示を受領した。内容を確認した結果、ポジショニング・参照8ブランド(Microsoft/OpenAI/AWS/Stripe/Palantir/Datadog/Snowflake/Vercel)・カラー・Hero構成・CTA(3つ)・UI思想など、大部分は**v2.0(2026-07-03)で既に確定・実装済み**の内容と一致していた。そのため今回は「新たな方針転換」ではなく、「既存方針の正式な再確認」として扱い、その中に含まれていた新規要素を追加で反映した。

### 新規に追加した内容

- **ブランドコピー階層の制定:** ブランドコピー「会社を動かすAI。」/ サービスコピー「経営を、ひとつにつなぐ。」/ 説明コピー「社長の右腕になるAI経営プラットフォーム。」の3階層を正式化
- **CEO判断記録:** 「AIツールを提供する会社」ではなく「会社全体をAIで進化させる企業」という方向性を、ホームページ・管理画面・営業資料・パンフレット・デモ画面・Smart Labo Worksすべての基準とすることを明記
- ブランドキーワードにGrowth(成長)・Future(未来)・AI Platform(経営)等の英語表記を追加
- ブランド適用範囲にパンフレット・デモ画面を追加
- UI原則に「余白を優先しつつEnterprise SaaSらしい力強さと両立させる」を追記

### 更新したファイル

- [00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md)(v4.1→v4.2) — ブランドコピー階層、キーワード拡張、適用範囲拡張
- [20_UI_UX_Rules.md](20_UI_UX_Rules.md)(v4.0→v4.1) — 余白と力強さの両立原則を追記
- [PROMPTS/DESIGN/SmartLabo_Design_Bible.md](../PROMPTS/DESIGN/SmartLabo_Design_Bible.md)(v2.0→v2.1) — ブランドコピー階層を追加
- [PROMPTS/DESIGN/Homepage.md](../PROMPTS/DESIGN/Homepage.md)(v2.0→v2.1) — 「主要機能」セクションでのサービスコピー/説明コピー使用指示を追加

**変更者:** Claude Code(Project Bible編集長)/ 指示: CEO

---

## 2026-07-03 — v2.1: CEO本人によるBrand Philosophyの記入と原則の正式制定

CEO本人が [99_CEO_MEMORY/04_Brand_Philosophy.md](99_CEO_MEMORY/04_Brand_Philosophy.md) に、v2.0で確定したEnterprise AI Platform方針への転換経緯と、ブランドの原則を自らの言葉で記入した。これはCEO_MEMORY配下のファイルとして初めて本人による実質的な記入が行われたケースであり、durable(長期的に判断基準となる)な原則については `00_Foundation` 側にも反映した。

### 記録された内容

- ブランド戦略転換の経緯(Quiet Intelligence → Enterprise AI Platform)を、CEO自身の思考プロセスとして記録
- **Missionは不変であることの明言:** 「AIで、社長が本来の仕事に集中できる世界をつくる。」は見せ方の変更を経ても変わらない
- **ブランドの原則(6項目):** 信頼最優先/高級感より品格/機能ではなく価値/経営者が安心できるデザイン/ホームページではなくブランドを作る/デザインだけでなく体験を設計する
- **CEO Message:** 「Smart LaboはAIを売る会社ではない。社長が本来の仕事に集中できる時間をつくる会社である。」

### 00_Foundation側への反映

- [00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md)(v4.0→v4.1) — 「ブランドの原則」6項目を正式制定。デザインキーワードのうちLuxury(高級感)より品格(Dignity)を優先する旨を明記。冒頭にCEO Messageを引用
- [00_Foundation/02_Founder_Message.md](00_Foundation/02_Founder_Message.md)(v1.0→v2.0) — 冒頭にCEO本人の実際の言葉(CEO Message)を反映。参照ブランドの記述をEnterprise AI Platform方針に合わせて更新。本文の残りは引き続き代表確認待ち
- [50_TODO.md](50_TODO.md) — 04_Brand_Philosophy.mdの記入完了を反映し、残タスク(01_Founding_Story / 03_Future_Vision / 05_Important_Conversations)を明確化

**変更者:** Claude Code(Project Bible編集長)/ 執筆: CEO本人

---

## 2026-07-03 — v2.0: ブランド方針の全面ピボット — Quiet Intelligence → Enterprise AI Platform

CEO(Chief Design Officer方針)より、v1.2で確定したばかりの「Quiet Intelligence」方針とは大きく異なる新しいブランドブリーフが示されました。Claude Code(Project Bible編集長)は、両方針の矛盾点(ポジショニング、参照ブランド、禁止/推奨ビジュアル、カラー、ボタンサイズ、CTA数、ページ構成)を洗い出してCEOに提示し、**「最新指示を優先し、Enterprise AI Platformへ全面ピボットする」**という明示的な承認を得て本改訂を実施しました。

### なぜ全面ピボットとして扱うか

矛盾が部分的ではなく、ブランドの人格そのもの(「静かに信頼を積み上げる」から「先進性とスケールを堂々と見せる」へ)に及んでいたため、部分的なマージではなく方針の置き換えとして扱っています。旧方針は以下の通り、履歴として保存されます。

### 旧方針(Quiet Intelligence, v1.2まで)の要点 [SUPERSEDED]

- ポジショニング: 「AI会社ではなく社長の右腕」
- 参照企業: Apple / Notion / Linear / Stripe / Porsche(5社)
- カラー: White / Black / Smart Blueのみ
- 禁止: 未来都市CG・AIネットワーク・六角形・過度な発光 等
- ボタン: 小さめ・控えめ
- CTA: 「デモを見る」「お問い合わせ」の2つのみ
- Homepage構成: Hero→共感→Smart Labo Works→導入メリット→ブランドストーリー→会社理念→お問い合わせ

### 新方針(Enterprise AI Platform, v2.0)の要点

- ポジショニング: 「AIを売る会社ではなく、会社を進化させる会社」
- 参照企業: Microsoft / OpenAI / AWS / Palantir / Stripe / Datadog / Snowflake / Vercel(8社、Stripeのみ共通)
- カラー: Navy / White / Smart Blue / Light Gray、グラデーションは上品に許容
- 禁止基準を「安っぽさ・過度さ」へ変更(未来都市・AIネットワーク自体は推奨ビジュアルへ)
- ボタン: 大きめ・美しい角丸・ホバーアニメーション・高級感
- CTA: 「無料デモを見る」「導入相談をする」「資料ダウンロード」の3つ、営業導線重視
- Homepage構成: Hero(左テキスト/右に管理画面表示、背景に未来都市+控えめなAIネットワーク)→社長の1日の業務をAIが支える流れ→主要機能→導入メリット→対応業種→Mission→CTA→Footer

### 更新したファイル

- [00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md) — ポジショニング・参照8社・カラー・禁止/推奨ビジュアルを全面改訂(v3.0→v4.0)
- [20_UI_UX_Rules.md](20_UI_UX_Rules.md) — デザイン原則・チェックリストを全面改訂(v3.0→v4.0)
- [PROMPTS/DESIGN/SmartLabo_Design_Bible.md](../PROMPTS/DESIGN/SmartLabo_Design_Bible.md) — マスタープロンプトを全面改訂、「機能→価値」変換パターンを追加(v1.0→v2.0)
- [PROMPTS/DESIGN/Homepage.md](../PROMPTS/DESIGN/Homepage.md) — ページ構成・Hero・CTAを全面改訂(v1.0→v2.0)
- [PROMPTS/DESIGN/Dashboard.md](../PROMPTS/DESIGN/Dashboard.md) — 配色・データ表現方針を更新(v1.0→v2.0)
- [PROMPTS/DESIGN/LandingPage.md](../PROMPTS/DESIGN/LandingPage.md) — カラー・ボタンスタイルを同期(v1.0→v2.0)
- [99_CEO_MEMORY/04_Brand_Philosophy.md](99_CEO_MEMORY/04_Brand_Philosophy.md) — 記入ガイドを新方針・方針転換の経緯記録用に更新
- ルート [README.md](../README.md)、[WEBSITE/README.md](../WEBSITE/README.md)、[BRAND/README.md](../BRAND/README.md)、[DESIGN/README.md](../DESIGN/README.md) の関連記述を同期

**変更者:** Claude Code(Project Bible編集長)/ 指示・最終承認: CEO

---

## 2026-07-03 — v1.2: ブランドデザイン「Quiet Intelligence」の制定

CEO(Chief Design Officer指示)より、Smart Laboのブランドデザイン方針が示され、PROJECT_BIBLEおよび新設の `PROMPTS/DESIGN/` に反映しました。「作るのはホームページではなくブランドである」という前提のもと、ブランドコンセプトからUI原則までを制定しています。

### ポジショニング・ブランドコンセプト

- **ポジショニング:** 「Smart LaboはAI会社ではない。社長の右腕になる会社である」
- **ブランドコンセプト:** **Quiet Intelligence(静かな知性)** — 派手ではない、説明しすぎない、余白を大切にする、長く愛されるデザイン
- 参照企業を Apple / Notion / Linear / Stripe に **Porsche** を追加した5社に拡張(コピーではなく思想の継承として扱う)

### PROJECT_BIBLE側の変更

- [00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md) にポジショニング・Quiet Intelligence・使用カラー(White/Black/Smart Blueのみ)・禁止ビジュアルの概要を追加
- [20_UI_UX_Rules.md](20_UI_UX_Rules.md) の原則を6→9項目に拡充(配色をSmart Blueのみに限定、ボタン/モーション/情報量より理解しやすさ/禁止ビジュアルを追加)、レビューチェックリストを更新
- [99_CEO_MEMORY/04_Brand_Philosophy.md](99_CEO_MEMORY/04_Brand_Philosophy.md) の記入ガイドをQuiet Intelligence・5社構成に更新

### 新規追加: PROMPTS/DESIGN/

- [PROMPTS/DESIGN/SmartLabo_Design_Bible.md](../PROMPTS/DESIGN/SmartLabo_Design_Bible.md) — ブランドの完全版デザイン指針。あらゆる制作物の起点となるマスタープロンプト(参照企業分析、デザインルール、カラー、禁止事項、推奨ビジュアル、UI思想を網羅)
- [PROMPTS/DESIGN/Homepage.md](../PROMPTS/DESIGN/Homepage.md) — コーポレートサイト トップページの構成(Hero→共感→Smart Labo Works→導入メリット→ブランドストーリー→会社理念→お問い合わせ)とHero文言を制定
- [PROMPTS/DESIGN/Dashboard.md](../PROMPTS/DESIGN/Dashboard.md) — Smart Labo Works プロダクト画面向けの設計指示
- [PROMPTS/DESIGN/LandingPage.md](../PROMPTS/DESIGN/LandingPage.md) — 個別施策向けランディングページの設計指示

### 禁止事項(全成果物共通)

AIロボット / サイバー演出 / ネオン / 未来都市CG / 青い地球 / 六角形 / 基板イラスト / 過度なエフェクト / 派手なアニメーション / 情報過多のUI

**変更者:** Claude Code(Project Bible編集長)/ 指示: CEO(Chief Design Officer方針として)

---

## 2026-07-03 — v1.1: 編集体制・更新フローの制定

PROJECT_BIBLEを継続的に正しく保つための「編集体制」を正式に制定しました。CEOより、ChatGPT・CEO・Claude Codeの三者による運用フローの指示を受け反映したものです。

- [60_Editorial_Workflow.md](60_Editorial_Workflow.md) を新設
  - 役割体制: ChatGPT(意思決定パートナー: 経営戦略・ブランド・UI/UX・営業・商品設計・会社方針)/ CEO(最終承認者)/ Claude Code(Project Bible編集長)
  - 6ステップ更新フロー: ①ChatGPTとの会話で方向性決定 → ②CEOが更新指示 → ③Claude CodeがPROJECT_BIBLEを更新 → ④Version更新 → ⑤CHANGELOG更新 → ⑥Gitへコミット
  - 「コードより先にBible」原則、Project Bible更新チェックリストを明記
- [README.md](README.md) に運用体制セクションを追加し、フォルダツリー・番号体系説明・更新ルール・変更履歴を更新
- [30_AI_Rules.md](30_AI_Rules.md) のツール別役割分担表・基本原則を更新し、60_Editorial_Workflow.mdへの参照を追加

**変更者:** Claude Code(Project Bible編集長)/ 承認: CEO

---

## 2026-07-03 — v1.0: 正式版 — 会社のOS・憲法として全面再設計

PROJECT_BIBLEを「ドキュメント集」から「会社のOS・憲法・知識ベース」として位置づけ直し、正式版 Version 1.0 として確定しました。

### 新しいフォルダ構成

```
PROJECT_BIBLE/
├── 00_Foundation/       会社の土台となる思想(公式見解)
├── 10_Development_Rules.md
├── 20_UI_UX_Rules.md
├── 30_AI_Rules.md
├── 40_Organization.md
├── 50_TODO.md
└── 99_CEO_MEMORY/        代表個人の記憶(生の記録)
```

### 旧ファイル → 新ファイルの対応表

| 旧パス(v0.x) | 新パス(v1.0) | 補足 |
|---|---|---|
| `01_Mission.md` | `00_Foundation/03_Mission.md` | Mission文を正式版に全面改訂 |
| `02_Vision.md` | `00_Foundation/04_Vision.md` | Vision文を正式版に全面改訂 |
| `03_Value.md` | `00_Foundation/05_Value.md` | 内容は継承 |
| `04_Brand.md` | `00_Foundation/07_Brand_Identity.md` | 会社名/ブランド名/キャッチコピーを追加 |
| `05_Product.md` | `00_Foundation/08_SmartLaboWorks_Concept.md` | 新Mission/Visionに合わせ改訂 |
| `06_Development_Rules.md` | `10_Development_Rules.md` | AIファースト等の原則を反映 |
| `07_UI_UX_Rules.md` | `20_UI_UX_Rules.md` | キーワードを拡張(信頼感・ミニマルを追加) |
| `08_AI_Rules.md` | `30_AI_Rules.md` | 内容は継承・一部拡充 |
| `09_Roadmap.md` | `00_Foundation/10_Future_Roadmap.md` | Product Historyとの役割分担を明記 |
| `10_Company_History.md` | `00_Foundation/01_Company_Story.md` | 物語形式の説明を追加 |
| `11_Organization.md` | `40_Organization.md` | 内容は継承 |
| `12_TODO.md` | `50_TODO.md` | 内容は継承・タスクを追加 |
| `13_CEO_NOTE.md` | `99_CEO_MEMORY/02_Key_Decisions.md` ほか4ファイルに分割 | 決定ログ / 創業経緯 / 将来構想 / ブランド思想 / 重要な会話 に再構成 |

### 新規追加ファイル

- [00_Foundation/02_Founder_Message.md](00_Foundation/02_Founder_Message.md) — 創業者からのメッセージ
- [00_Foundation/06_Philosophy.md](00_Foundation/06_Philosophy.md) — 会社を貫く8つの哲学(機能より体験・シンプル最優先・毎日使いたくなるUI・保守性・スケーラブル設計・AIファースト・コード品質・会社全体のAI最適化)
- [00_Foundation/09_Product_History.md](00_Foundation/09_Product_History.md) — プロダクトのリリース実績記録
- [99_CEO_MEMORY/01_Founding_Story.md](99_CEO_MEMORY/01_Founding_Story.md)
- [99_CEO_MEMORY/03_Future_Vision.md](99_CEO_MEMORY/03_Future_Vision.md)
- [99_CEO_MEMORY/04_Brand_Philosophy.md](99_CEO_MEMORY/04_Brand_Philosophy.md)
- [99_CEO_MEMORY/05_Important_Conversations.md](99_CEO_MEMORY/05_Important_Conversations.md)

### 公式ステートメントの正式制定

- **Mission:** 「AIで、社長が本来の仕事に集中できる世界をつくる。」
- **Vision:** 「日本で最も信頼される、中小企業向けAI経営プラットフォームになる。」
- **キャッチコピー:** 「会社を動かすAI。」

**変更者:** Claude Code(Chief System Architect)

---

## 2026-07-03 — v0.2 (draft): Single Source of Truth化の試作

- PROJECT_BIBLE を Single Source of Truth として位置づけ
- 全ファイルの末尾に「変更履歴」テーブルを追加
- CEOノート(旧13_CEO_NOTE.md)を試作

**変更者:** Claude Code

---

## 2026-07-03 — v0.1 (draft): PROJECT_BIBLE 試作版作成

- `PROJECT_BIBLE/` フォルダを新設し、01〜12の連番フラット構成で初版を作成
- あわせて `SmartLabo/` 直下のフォルダ構成一式(DOCUMENT / DESIGN / WEBSITE / SmartLaboWorks / PROMPTS / BRAND / LEGAL / MEETING / ARCHIVE)と各READMEを作成

**変更者:** Smart Labo / Claude Code

---

*このファイル自体の運用ルールは [README.md](README.md) の「Version管理」セクションを参照してください。*
