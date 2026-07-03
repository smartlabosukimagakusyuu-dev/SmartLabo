# CHANGELOG — PROJECT_BIBLE 全体の変更履歴

`PROJECT_BIBLE` は株式会社スマートラボのOS・憲法であり、Single Source of Truth です。このファイルは、PROJECT_BIBLE 配下のドキュメント群に加えられた**重要な変更**を、日付順に一覧できるようにするためのマスター記録です。

- 各ファイル個別の細かい変更履歴は、各ファイル末尾の「変更履歴」テーブルを参照してください。
- ここには、複数ファイルにまたがる変更や、会社の意思決定として記録すべき重要な更新のみを記載します(誤字修正などの軽微な変更は対象外)。
- 新しいエントリは**先頭(最新)に追加**してください(Keep a Changelog 形式に準拠)。
- バージョニング方針は [README.md](README.md) の「Version管理」を参照してください。

---

## [Unreleased]

- (次回の変更予定があればここに記載)

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
