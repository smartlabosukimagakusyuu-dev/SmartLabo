# CURRENT_STATUS

**PROJECT_BIBLEの現在地 — ChatGPT / Claude Code / Codex 共通の同期ポイント**

> 新しいChatGPTの会話を始める前に、必ずこのファイルを確認してください。下記の「5行サマリー」をそのままChatGPTに貼り付ければ、認識のズレなく議論を始められます。このファイルはCEOの指示により、PROJECT_BIBLEおよびGitHubの管理責任者(Claude Code)が常に最新版を維持します。

---

## 5行サマリー(ChatGPTに共有する用)

```
Project Bible Version：2.3
Brand Version：4.2
Design Bible Version：2.1
Homepage Version：0.0(設計確定・実装未着手)
Current Task：Homepage実装
```

---

## ステータス一覧

| 項目 | 値 |
|---|---|
| Project Bible Version | 2.3 |
| Brand Version | 4.2 |
| Design Bible Version | 2.1 |
| Homepage Version | 0.0(設計プロンプト確定・実装未着手) |
| Dashboard Version | 0.0(設計プロンプト確定・実装未着手) |
| Smart Labo Works Version | 0.0(未着手) |
| Current Project | Company Setup |
| Current Task | Homepage実装(コーポレートサイトの実制作) |
| Next Task | Dashboard UI設計 |
| Last Update | 2026-07-03 |
| Maintainer | Masatoshi Ogawa |

---

## Versionの出典(トレーサビリティ)

推測でVersionを記載しないため、各数値の出典を明記します。数値を更新する際は、必ず出典ファイルの「変更履歴」テーブルと一致させてください。

| 項目 | 出典 |
|---|---|
| Project Bible Version | [README.md](README.md) 冒頭「PROJECT_BIBLE Version」 |
| Brand Version | [00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md) 末尾の変更履歴(最新バージョン) |
| Design Bible Version | [../PROMPTS/DESIGN/SmartLabo_Design_Bible.md](../PROMPTS/DESIGN/SmartLabo_Design_Bible.md) 末尾の変更履歴 |
| Homepage Version | 設計: [../PROMPTS/DESIGN/Homepage.md](../PROMPTS/DESIGN/Homepage.md) / 実装: [WEBSITE/](../WEBSITE/README.md) |
| Dashboard Version | 設計: [../PROMPTS/DESIGN/Dashboard.md](../PROMPTS/DESIGN/Dashboard.md) / 実装: [SmartLaboWorks/](../SmartLaboWorks/README.md) |
| Smart Labo Works Version | 実装: [SmartLaboWorks/](../SmartLaboWorks/README.md) |

---

## Homepage / Dashboard / Smart Labo Works のVersionについて(重要な注記)

この3項目は、**設計プロンプトのドキュメントバージョンではなく、実際のコード実装の進捗**を表す別軸です。

- 2026-07-03時点、`WEBSITE/`・`SmartLaboWorks/`・`DESIGN/` 配下には `README.md` 以外の実装ファイルは存在しません。したがって正直な現在値は **0.0(未着手)** です。
- 一方で、設計プロンプト([PROMPTS/DESIGN/](../PROMPTS/DESIGN/README.md))は確定しており(Homepage.md v2.1、Dashboard.md v1.0)、実装フェーズに進める準備は整っています。
- 実装が進んだら、この3項目を `0.1`、`α0.1` のように更新してください。**達成していない進捗を先取りして記載しないこと。**

---

## ChatGPTとの同期ルール

- 新しいChatGPTの会話を始める前に、必ずこのファイルの「5行サマリー」を確認・共有してください。
- ChatGPTとの議論で、ブランド・Mission・Vision・UI・デザイン・システム構成・ロードマップなどの方針変更があった場合、CEOはClaude Codeに更新を指示してください。Claude CodeはPROJECT_BIBLEへ反映したうえで、このファイルも更新します。
- 詳細な役割体制・更新フローは [60_Editorial_Workflow.md](60_Editorial_Workflow.md) を参照してください。

---

## 更新ルール

- このファイルは**作業のたびに更新する「生きたファイル」**です。個別ファイルの「変更履歴」テーブル(追記型)とは異なり、常に最新状態に上書きしてください。過去のスナップショットはGit履歴で追跡可能です。
- GitHub運用フロー(git status確認からpushまで)は [60_Editorial_Workflow.md](60_Editorial_Workflow.md) の「GitHub運用フロー」を参照してください。
- Maintainerが変わった場合は、この項目を更新してください。

---

## 変更履歴

| バージョン | 日付 | 変更者 | 変更内容 |
|---|---|---|---|
| v1.0 | 2026-07-03 | Claude Code(CEO指示による) | 初版作成。11項目のステータス、5行サマリー、Versionの出典トレーサビリティを整備 |

*最終更新: 2026-07-03*
