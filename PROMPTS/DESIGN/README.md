# PROMPTS / DESIGN

**Smart Laboブランド・デザイン用プロンプト集**

---

## このフォルダの目的

Smart Laboのブランド・デザイン(サイト、プロダクトUI、資料、広告等)を、ChatGPT・Claude Code・Codex などのAIに作らせる際に、常に一貫した品質・思想で出力させるための「デザイン用プロンプト」を管理します。

Smart Laboはホームページを作る会社ではなく、ブランドを作っています。個別の画面・資料を作る前に、必ず [SmartLabo_Design_Bible.md](SmartLabo_Design_Bible.md) を読み、思想を理解してから着手してください。

---

## ファイル構成

| ファイル | 内容 |
|---|---|
| [SmartLabo_Design_Bible.md](SmartLabo_Design_Bible.md) | ブランドの完全版デザイン指針。すべてのデザインプロンプトの起点となるマスタードキュメント |
| [Homepage.md](Homepage.md) | コーポレートサイト トップページの構成・Hero文言・チェックリスト |
| [Dashboard.md](Dashboard.md) | Smart Labo Works のプロダクト画面(毎日使うダッシュボード)の設計指示 |
| [LandingPage.md](LandingPage.md) | 個別施策向けランディングページの設計指示 |

---

## 利用ルール

1. **必ずDesign Bibleから読み込ませる。** 個別ファイル(Homepage.md等)だけを渡さず、[SmartLabo_Design_Bible.md](SmartLabo_Design_Bible.md) を先に読み込ませてください。
2. **禁止事項は例外なく適用する。** 「今回だけは」という例外は認めません。安っぽいAIロボット・六角形背景・過度なサイバー演出・ネオンカラー等の禁止ビジュアルは、どんな成果物でも使用しません。
3. **新しい成果物の種類が増えたら、ファイルを追加する。** 既存ファイルに無理に詰め込まず、`PROMPTS/DESIGN/` に新規ファイルとして追加してください(例: `SNS_Post.md`, `PitchDeck.md` 等)。

---

## Version管理

- 各ファイルは末尾に「変更履歴」テーブルを持ちます。
- ブランドの根幹(Enterprise AI Platform方針、カラー、禁止事項)に関わる変更は、[PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md](../../PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md) と同期させてください。矛盾が生じた場合はPROJECT_BIBLE側を正とします。

---

## 更新ルール

- CEOの承認を得たブランド・デザイン方針の変更は、[PROJECT_BIBLE/60_Editorial_Workflow.md](../../PROJECT_BIBLE/60_Editorial_Workflow.md) の更新フローに従い、まずPROJECT_BIBLEへ反映し、その後このフォルダのプロンプト群を同期してください。
- 新しい参考ブランドや禁止事項が追加された場合は、[SmartLabo_Design_Bible.md](SmartLabo_Design_Bible.md) を更新し、影響する個別ファイルも確認してください。

---

## Git運用ルール

- 通常のプロンプト同様、直接コミットで構いません。ブランドの根幹に関わる変更は、PROJECT_BIBLE側の更新ルール(経営層承認)に準じます。

---

*最終更新: 2026-07-03*
