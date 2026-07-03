# 50. TODO — 全社レベルの未対応タスク一覧

このドキュメントは、特定のプロジェクト・フォルダに限定されない、会社全体レベルの未対応タスクを記録します。個別プロダクトのタスクは各フォルダ内で管理してください。

---

## 未対応タスク

- [ ] 会社の正式な設立日・登記情報を [00_Foundation/01_Company_Story.md](00_Foundation/01_Company_Story.md) に反映する
- [ ] 組織図の正式版を [40_Organization.md](40_Organization.md) に反映する
- [ ] ロゴの実データ(SVG/PNG、カラー版・白抜き版・アイコン版)を [DESIGN_ASSETS/Logo/](../DESIGN_ASSETS/Logo/README.md) に格納する(仕様は[00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md)に定義済み。CEOより正式データは別途用意予定と連絡あり)
- [ ] アイコンセットの実SVGファイルを [DESIGN_ASSETS/Icons/](../DESIGN_ASSETS/Icons/README.md) に格納する(一覧は[DESIGN/system/design-tokens.md](../DESIGN/system/design-tokens.md)に定義済み)
- [x] ~~Homepage(`WEBSITE/`)のカラートークンを正式パレット(`#0A1B3D` / `#2563EB` 等)へ更新する~~(2026-07-03完了)
- [x] ~~Hero候補5点の完成イメージモックアップを [DESIGN_ASSETS/01_HERO/Mockups/](../DESIGN_ASSETS/01_HERO/Mockups/README.md) に格納する~~(2026-07-03完了。AI_WORKSPACE経由で受領)
- [ ] Hero背景**単体**の実データ(`.webp`、テキスト・ロゴが焼き込まれていないもの)を [DESIGN_ASSETS/01_HERO/Backgrounds/](../DESIGN_ASSETS/01_HERO/README.md) に格納する(受領済みのモックアップはロゴ・見出し・CTAが焼き込まれておりそのまま使用不可。CEOより背景単体版の別途提供待ち)
- [ ] 採用済みのHero背景(`hero_background_01_sunrise_city.webp`、背景単体版)をHomepage([WEBSITE/index.html](../WEBSITE/index.html))に実装する
- [ ] PROMPTS配下にClaude / Codex / ChatGPT向けの実プロンプトテンプレートを追加する
- [ ] [00_Foundation/02_Founder_Message.md](00_Foundation/02_Founder_Message.md) の本文(冒頭のCEO Message以外)を代表本人の言葉で確定させる
- [ ] [99_CEO_MEMORY/01_Founding_Story.md](99_CEO_MEMORY/01_Founding_Story.md)(創業経緯)・[03_Future_Vision.md](99_CEO_MEMORY/03_Future_Vision.md)(将来構想)・[05_Important_Conversations.md](99_CEO_MEMORY/05_Important_Conversations.md)(重要な会話)を代表が記入する(04_Brand_Philosophy.mdは記入済み)
- [ ] [00_Foundation/09_Product_History.md](00_Foundation/09_Product_History.md) にリリース済みプロダクト・機能を記録する

---

## 運用ルール

- タスクを追加する際は、担当者・期限が明確な場合は併記してください(例: `- [ ] ○○を実施する(担当: 山田、期限: 2026-08-01)`)。
- 完了したタスクはチェックを入れたまま残し、四半期ごとに `ARCHIVE/` へ移動して整理することを推奨します。
- 個別プロダクト・部門のタスクが増えてきた場合は、このファイルから分離し、該当フォルダ内に `TODO.md` を作成してください。

---

## 変更履歴

| バージョン | 日付 | 変更者 | 変更内容 |
|---|---|---|---|
| v1.0 | 2026-07-03 | Smart Labo | 初版作成 |
| v2.0 | 2026-07-03 | Smart Labo | PROJECT_BIBLE再構成に伴い旧12_TODO.mdから改番。00_Foundation・99_CEO_MEMORY関連の未記入項目を追加 |
| v2.1 | 2026-07-03 | Claude Code | 04_Brand_Philosophy.mdの記入完了を反映し、残タスクを更新 |
| v2.2 | 2026-07-03 | Claude Code(CEO提供のブランドキット参照資料による) | ロゴ・アイコンの実データ格納、Homepageのカラートークン更新タスクを追加 |
| v2.3 | 2026-07-03 | Claude Code(CEO指示による) | ロゴ・アイコンの格納先を [DESIGN_ASSETS/](../DESIGN_ASSETS/README.md) に更新 |
| v2.4 | 2026-07-03 | Claude Code(CEO指示による) | Homepageカラートークン更新タスクを完了として消し込み。Hero背景画像5点の格納・実装タスクを追加 |
| v2.5 | 2026-07-03 | Claude Code(CEO指示による) | Hero候補5点のモックアップ格納タスクを完了として消し込み。実装には背景単体データが別途必要であることを明記し、タスクを更新 |

*最終更新: 2026-07-03*
