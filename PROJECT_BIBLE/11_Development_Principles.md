# 11. Development Principles — Smart Labo Development Principles

このドキュメントは、今後のSmart Labo Works開発における基本原則を定義します。[10_Development_Rules.md](10_Development_Rules.md) が「どう開発するか」(コーディング規約・Git運用・品質保証)を定めるのに対し、本ドキュメントは「何を作る前に何を確認し、何を判断基準にするか」という、開発に着手する**前**の思考・確認プロセスを定めます。

---

## 目的

Smart Laboブランド・世界観・仕様を統一し、どの画面・機能を開発しても一貫性を維持することを目的とします。

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

## Smart Labo Design Principle

新しい機能を追加する前に、必ず以下を確認してください。

- [ ] 社長の時間を増やせるか
- [ ] Company OSの思想に沿っているか
- [ ] 「会社を動かすAI。」というブランドコンセプトに一致しているか([00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md))
- [ ] Smart Laboらしい高級感・シンプルさ・統一感があるか
- [ ] 他社との差別化につながるか

**YESにならない機能は実装しないこと。**

---

## デモ画面作成ルール

デモ画面は単なるUIサンプルではありません。営業でお客様へ見せる**「製品デモ」**として設計してください。

デモを作成する際は、以下を意識してください。

- Company OS
- Enterprise SaaS
- 高級感
- 実際の利用イメージ

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

DashboardはCRMではありません。**Company OS**です。

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

**「会社を進化させるCompany OS」**です。

すべての画面・機能・UI・UXは、この思想を中心に設計してください。

---

## 関連ドキュメント

- [00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md)
- [00_Foundation/08_SmartLaboWorks_Concept.md](00_Foundation/08_SmartLaboWorks_Concept.md)
- [10_Development_Rules.md](10_Development_Rules.md)
- [20_UI_UX_Rules.md](20_UI_UX_Rules.md)
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

*最終更新: 2026-07-05*
