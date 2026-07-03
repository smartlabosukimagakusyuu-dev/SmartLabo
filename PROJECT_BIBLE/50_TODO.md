# 50. TODO — 全社レベルの未対応タスク一覧

このドキュメントは、特定のプロジェクト・フォルダに限定されない、会社全体レベルの未対応タスクを記録します。個別プロダクトのタスクは各フォルダ内で管理してください。

---

## 未対応タスク

- [ ] 会社の正式な設立日・登記情報を [00_Foundation/01_Company_Story.md](00_Foundation/01_Company_Story.md) に反映する
- [ ] 組織図の正式版を [40_Organization.md](40_Organization.md) に反映する
- [ ] ロゴの実データ(SVG/PNG、カラー版・白抜き版・アイコン版)を [BRAND/logo/](../BRAND/README.md) に格納する(仕様は[00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md)に定義済み)
- [ ] アイコンセットの実SVGファイルを `DESIGN/assets/icons/` に格納する(一覧は[DESIGN/system/design-tokens.md](../DESIGN/system/design-tokens.md)に定義済み)
- [ ] Homepage(`WEBSITE/`)のカラートークンを正式パレット(`#0A1B3D` / `#2563EB` 等)へ更新する
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

*最終更新: 2026-07-03*
