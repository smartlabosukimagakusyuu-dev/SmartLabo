# 11. Development Principles — Smart Labo Development Principles

このドキュメントは、今後のSmart Labo Works開発における基本原則を定義します。[10_Development_Rules.md](10_Development_Rules.md) が「どう開発するか」(コーディング規約・Git運用・品質保証)を定めるのに対し、本ドキュメントは「何を作る前に何を確認し、何を判断基準にするか」という、開発に着手する**前**の思考・確認プロセスを定めます。

---

## 目的

Smart Laboブランド・世界観・仕様を統一し、どの画面・機能を開発しても一貫性を維持することを目的とします。

---

## 製品境界の定義(2026-07-10 CEO決定)

株式会社スマートラボが開発・運用するシステムは、以下の2つに正式に区別されます。

| 名称 | 位置づけ | 販売可否 |
|---|---|---|
| **Smart Labo Works** | 顧客へ販売するSaaS製品 | 販売する |
| **Company OS** | 株式会社スマートラボの社内専用システム | **販売しない(非売品)** |

これまで本ドキュメントを含むPROJECT_BIBLE内の一部の記述で、「Company OS」という語を **Smart Labo Works自体の設計思想を表す形容(顧客向け製品の性格を説明する言葉)** として使用していた箇所があった。この用法は本決定により廃止し、以降「Company OS」は社内専用システムの固有名詞としてのみ使用する(本ドキュメント内の該当箇所は「Dashboard設計原則」「最重要原則」節で修正済み)。

「Smart Labo Group」という会社・プロダクトファミリー名は、現時点では**正式名称ではなく、将来のブランド構想としてのみ**扱う。「Smart Labo AI」「Smart Labo CRM」も同様に**将来の商品候補**であり、現時点では正式製品として扱わない。

**Smart Labo Worksの唯一の正式コードベースは`smartlabo-works`(Node.js版・別リポジトリ)である。** `WEBSITE/app.html`(このリポジトリ、GitHub Pages公開版)はSmart Labo Worksの正式製品ではなく、**デモサイト／マーケティング用プレビュー**として扱う。今後のSmart Labo Works機能追加は`smartlabo-works`側でのみ行う。

詳細な機能境界・分類は、`smartlabo-works`リポジトリの `PRODUCT_BOUNDARY.md`(Claude Code作成、2026-07-10)を参照。

---

## 開発前ルール(必須)

新しい画面・機能・デモ・UIを作成する前に、必ず以下を確認してください。

1. **PROJECT_BIBLE**([README.md](README.md))
2. **Brand Bible**([00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md))
3. **Design Bible**([PROMPTS/DESIGN/SmartLabo_Design_Bible.md](../PROMPTS/DESIGN/SmartLabo_Design_Bible.md))
4. **CURRENT_STATUS**([CURRENT_STATUS.md](CURRENT_STATUS.md))
5. **CHANGELOG**([CHANGELOG.md](CHANGELOG.md))

最新版を確認し、最新仕様・ブランド・デザインを反映した上で開発してください。**古い仕様や過去のブランドイメージを使用してはいけません。**

---

## CEOレビュー原則

大きな仕様変更・ブランド変更・UI変更を行う場合は、**実装前に変更内容を整理し、レビューを受けてから開発を進めてください。**

- **独自判断でブランドコンセプトやUI方針を変更しないこと。** PROJECT_BIBLEに明記されていない新しい解釈・方向性は、実装を進める前に必ずCEOへ確認する([60_Editorial_Workflow.md](60_Editorial_Workflow.md)「推測で判断しない(エスカレーションルール)」を参照)。
- レビューを受ける際は、変更内容(何を・なぜ・どう変えるか)を先に整理してから提示すること。「実装してから説明する」のではなく「説明してから実装する」順序を守る。
- 誤字修正や軽微なスタイル調整など、ブランドコンセプト・UI方針・仕様の根幹に関わらない変更はこの原則の対象外とする。
- **Smart Laboは長期的なブランド構築を重視します。** 目先のスピードよりも一貫性を優先すること。

---

## Smart Labo Design Principle

新しい機能を追加する前に、必ず以下を確認してください。

- [ ] 社長の時間を増やせるか
- [ ] 経営者が会社全体を一元的に把握・意思決定できる思想に沿っているか(旧称「Company OSの思想」。2026-07-10のCEO決定によりCompany OSは社内専用システムの固有名詞となったため、Smart Labo Works側の説明では使用しない)
- [ ] 「会社を動かすAI。」というブランドコンセプトに一致しているか([00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md))
- [ ] Smart Laboらしい高級感・シンプルさ・統一感があるか
- [ ] 他社との差別化につながるか

**YESにならない機能は実装しないこと。**

---

## デモ画面作成ルール

デモ画面は単なるUIサンプルではありません。営業でお客様へ見せる**「製品デモ」**として設計してください。

デモを作成する際は、以下を意識してください。

- Enterprise SaaS
- 高級感
- 実際の利用イメージ
- 経営者が信頼して使いたくなる一体感

---

## Company Brain優先

Company BrainはSmart Labo Works最大の差別化機能です([00_Foundation/08_SmartLaboWorks_Concept.md](00_Foundation/08_SmartLaboWorks_Concept.md)「Company Brain」章 / [PROMPTS/DESIGN/CompanyBrain.md](../PROMPTS/DESIGN/CompanyBrain.md))。

今後作成する画面では、Company Brainとの連携を常に検討してください。例:

- Company Brain検索
- AIチャット
- 社内規程
- 業務マニュアル
- FAQ
- テンプレート
- 教育資料
- 動画マニュアル

---

## Dashboard設計原則

DashboardはCRMではありません。**経営者の意思決定を支える中核画面**です。(旧来「Company OS」という語で表現していたが、2026-07-10のCEO決定によりCompany OSは株式会社スマートラボの社内専用システムの固有名詞となったため、顧客へ販売するSmart Labo Worksの説明では使用しない)

社長が毎朝最初に開く画面として設計してください。

表示すべきもの:

- AI提案
- 重要タスク
- 売上
- 契約状況
- スケジュール
- Company Brain
- 最新通知
- 経営状況

詳細なレイアウト仕様は [PROMPTS/DESIGN/Dashboard.md](../PROMPTS/DESIGN/Dashboard.md) を参照してください。

---

## ブランド維持

今後作成する以下の成果物は、すべて Brand Bible・Design Bible・PROJECT_BIBLE の最新版に統一してください。

- ホームページ
- Dashboard
- Company Brain
- 営業資料
- パンフレット
- 名刺
- アプリ

---

## Version管理

仕様変更・ブランド変更・画面追加を行った場合は、以下を必ず更新してください。

- [CURRENT_STATUS.md](CURRENT_STATUS.md)
- [CHANGELOG.md](CHANGELOG.md)
- PROJECT_BIBLE(該当する各ドキュメント)
- [00_Foundation/10_Future_Roadmap.md](00_Foundation/10_Future_Roadmap.md)(Roadmap)

Version管理を徹底してください。

---

## 最重要原則

Smart Labo Worksは**「AIチャット」ではありません。**

**「会社を進化させるAI経営プラットフォーム」**です。(2026-07-10のCEO決定により、「Company OS」は株式会社スマートラボの社内専用システムの固有名詞であるため、顧客へ販売するSmart Labo Worksの説明には使用しない)

すべての画面・機能・UI・UXは、この思想を中心に設計してください。

---

## 関連ドキュメント

- [00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md)
- [00_Foundation/08_SmartLaboWorks_Concept.md](00_Foundation/08_SmartLaboWorks_Concept.md)
- [10_Development_Rules.md](10_Development_Rules.md)
- [20_UI_UX_Rules.md](20_UI_UX_Rules.md)
- [60_Editorial_Workflow.md](60_Editorial_Workflow.md) — 「推測で判断しない(エスカレーションルール)」「コードより先にBible」
- [PROMPTS/DESIGN/SmartLabo_Design_Bible.md](../PROMPTS/DESIGN/SmartLabo_Design_Bible.md)
- [PROMPTS/DESIGN/CompanyBrain.md](../PROMPTS/DESIGN/CompanyBrain.md)
- [PROMPTS/DESIGN/Dashboard.md](../PROMPTS/DESIGN/Dashboard.md)
- [CURRENT_STATUS.md](CURRENT_STATUS.md)
- [CHANGELOG.md](CHANGELOG.md)

---

## 変更履歴

| バージョン | 日付 | 変更者 | 変更内容 |
|---|---|---|---|
| v1.0 | 2026-07-05 | Claude Code(CEO指示による) | 初版作成。開発前ルール(PROJECT_BIBLE/Brand Bible/Design Bible/CURRENT_STATUS/CHANGELOGの確認必須化)、Smart Labo Design Principle(5項目チェックリスト)、デモ画面作成ルール、Company Brain優先方針、Dashboard設計原則(Company OS)、ブランド維持、Version管理、最重要原則(「AIチャット」ではなく「会社を進化させるCompany OS」)を制定 |
| v1.1 | 2026-07-05 | Claude Code(CEO指示による) | **「CEOレビュー原則」を新設。** 大きな仕様変更・ブランド変更・UI変更は、実装前に変更内容を整理しレビューを受けてから開発を進めることを明文化。独自判断でのブランドコンセプト・UI方針変更を禁止。[60_Editorial_Workflow.md](60_Editorial_Workflow.md)の既存のエスカレーションルールを強化する位置づけとして追加 |
| **v1.2** | 2026-07-10 | Claude Code(CEO指示による) | **「製品境界の定義」を新設し、Company OSとSmart Labo Worksの呼称を正式分離。** Smart Labo Works=顧客へ販売するSaaS製品、Company OS=株式会社スマートラボの社内専用システム(非売品)と明確化。従来「Company OSの思想」「DashboardはCompany OS」「Smart Labo Worksは会社を進化させるCompany OS」等、Company OSをSmart Labo Works自体の形容として使っていた3箇所(Smart Labo Design Principle／デモ画面作成ルール／Dashboard設計原則／最重要原則)を修正し、Smart Labo Worksの説明からCompany OSという語を除去した。あわせて「Smart Labo Group」は非公式な将来のブランド構想、「Smart Labo AI」「Smart Labo CRM」は将来の商品候補(正式製品ではない)と明記した。詳細な機能境界は`smartlabo-works/PRODUCT_BOUNDARY.md`を参照 |
| **v1.3** | 2026-07-10 | Claude Code(CEO指示による) | **Smart Labo Worksの唯一の正式コードベースを`smartlabo-works`(Node.js版)に確定。** `WEBSITE/app.html`はSmart Labo Works正式製品ではなく「デモサイト／マーケティング用プレビュー」として位置づけを変更したことを「製品境界の定義」に追記。背景：`WEBSITE/app.html`側で並行して「Smart Labo Works v1.0」を名乗るコミットが存在していたため、CEOが正式コードベースを一本化する決定を下した |

*最終更新: 2026-07-10*
