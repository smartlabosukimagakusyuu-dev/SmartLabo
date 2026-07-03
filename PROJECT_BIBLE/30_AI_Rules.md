# 30. AI Rules — Claude / Codex / ChatGPT 共通利用ルール

このドキュメントは、株式会社スマートラボのプロジェクトにおいて、Claude Code・Codex・ChatGPT、そして将来登場するAIエージェントを利用する際の共通ルールを定めます。人間の開発者・運営メンバーも、AIに指示を出す前に必ず読んでください。

---

## 基本原則

1. **AIは「会社を動かすAI」という思想を体現する存在として扱う。** [00_Foundation/08_SmartLaboWorks_Concept.md](00_Foundation/08_SmartLaboWorks_Concept.md) の思想を、社内でのAI活用そのものにも適用します。
2. **AIファーストで考える。** タスクを人がやるか迷ったら、まずAIに任せられないかを検討します([00_Foundation/06_Philosophy.md](00_Foundation/06_Philosophy.md) 原則6)。
3. **AIに文脈を渡してから指示する。** タスクを依頼する前に、関連する PROJECT_BIBLE のドキュメントをAIに読み込ませてください。
4. **コードより先にBibleを読む。** Claude Codeは、コードを書く前に必ずPROJECT_BIBLEを参照します。実装がPROJECT_BIBLEと矛盾する場合、あるいはPROJECT_BIBLEに記載のない判断が必要な場合は、実装前にCEOへ確認しPROJECT_BIBLEを更新します。詳細は [60_Editorial_Workflow.md](60_Editorial_Workflow.md) を参照してください。
5. **AIの出力は必ず人間が確認する。** コード・文章・デザイン、いずれの生成物も、公開・実装前に責任者が確認します。
6. **事実に基づかない出力を公開しない。** AIが生成した数値・引用・主張は、裏付けを確認してから採用してください([00_Foundation/05_Value.md](00_Foundation/05_Value.md)「誠実さ」)。

---

## ツール別の役割分担

| ツール | 主な用途 | 関連フォルダ |
|---|---|---|
| **Claude Code** | **Project Bible編集長。** コード実装、リファクタリング、ファイル構成の設計、CEOの承認を経た意思決定のPROJECT_BIBLEへの反映 | [PROMPTS/CLAUDE/](../PROMPTS/CLAUDE/README.md) |
| **Codex** | コード生成・自動化スクリプト・CI関連タスク | [PROMPTS/CODEX/](../PROMPTS/CODEX/README.md) |
| **ChatGPT** | **意思決定パートナー。** 経営戦略・ブランド・UI/UX・営業・商品設計・会社方針についての意思決定の材料・案をCEOに提示する。企画・文章作成・アイデア出し・資料のドラフト作成 | [PROMPTS/CHATGPT/](../PROMPTS/CHATGPT/README.md) |

厳密な排他ルールではなく、得意領域に応じた使い分けの目安です。将来新しいAIツールが登場した場合は、この表に行を追加してください。PROJECT_BIBLE自体の編集体制・更新フローの詳細は [60_Editorial_Workflow.md](60_Editorial_Workflow.md) を参照してください。

---

## ChatGPT発の素材の受け渡し(AI_WORKSPACE)

ChatGPTとの対話で作成された画像・資料・参考デザイン・プロンプトは、[DESIGN_ASSETS/](../DESIGN_ASSETS/README.md) へ直接保存せず、必ず [AI_WORKSPACE/](../AI_WORKSPACE/README.md) を経由してください。Claude Codeは新しい素材を利用する前に `AI_WORKSPACE/INBOX` を必ず確認し、正式登録後は利用素材・保存先・不要素材を報告します。詳細な運用フローは [60_Editorial_Workflow.md](60_Editorial_Workflow.md) の「AI_WORKSPACE運用フロー」を参照してください。

---

## プロンプト設計の原則

- プロンプトは [PROMPTS/](../PROMPTS/README.md) 配下にテンプレートとして保存し、再利用できるようにする
- プロジェクト全体に関わる前提条件(このPROJECT_BIBLEの内容)は、可能な限りAIの設定(例: `CLAUDE.md` 等)に含める
- 個別タスクの指示は簡潔に、しかし「なぜそれが必要か」の背景を伝える

---

## AIに任せてよいこと / 慎重に扱うこと

### 任せてよいこと
- コードの実装・リファクタリング(レビュー前提)
- ドキュメントの下書き作成
- 定型的な文章・資料のドラフト
- データ整理・要約

### 人間の最終確認が必須なこと
- 経営判断・契約に関わる内容([LEGAL/](../LEGAL/README.md) 関連)
- 顧客に直接送付する文書・提案書([DOCUMENT/SALES/](../DOCUMENT/SALES/README.md))
- Mission/Vision/Value/Brand Identityなど `00_Foundation` 配下の根幹ドキュメントの変更
- 個人情報・機密情報を扱う処理

---

## セキュリティ・情報管理

- APIキー・パスワード・顧客の個人情報をAIへのプロンプトに直接含めない
- 機密性の高い資料をAIに読み込ませる場合は、社内承認済みの環境・ツールのみを使用する
- リポジトリに機密情報をコミットしない([10_Development_Rules.md](10_Development_Rules.md) 参照)

---

## 将来Smart Labo Worksへの組み込みを見据えて

PROJECT_BIBLEは将来、Smart Labo Works 自体にAIの前提知識として組み込まれることを想定しています。そのため、以下を意識してドキュメントを維持してください。

- 一つのファイルは一つのテーマに絞る(AIが部分的に読み込んでも文脈が壊れないようにする)
- ファイル間の参照は相対パスのMarkdownリンクで明示する
- 曖昧な代名詞(「これ」「それ」)より、具体的な名詞で記述する

---

## 関連ドキュメント

- [PROMPTS/README.md](../PROMPTS/README.md)
- [10_Development_Rules.md](10_Development_Rules.md)
- [00_Foundation/06_Philosophy.md](00_Foundation/06_Philosophy.md)
- [60_Editorial_Workflow.md](60_Editorial_Workflow.md) — PROJECT_BIBLE編集体制・更新フローの詳細
- [AI_WORKSPACE/README.md](../AI_WORKSPACE/README.md) — ChatGPT発の素材受け渡しフロー

---

## 変更履歴

| バージョン | 日付 | 変更者 | 変更内容 |
|---|---|---|---|
| v1.0 | 2026-07-03 | Smart Labo | 初版作成 |
| v2.0 | 2026-07-03 | Smart Labo | PROJECT_BIBLE再構成に伴い旧08_AI_Rules.mdから改番。「Smart Labo Worksへの将来的な組み込み」を見据えた記述ルールを追加 |
| v2.1 | 2026-07-03 | Claude Code | ChatGPT/CEO/Claude Codeの三者役割体制を明記。「コードより先にBibleを読む」原則と60_Editorial_Workflow.mdへの参照を追加 |
| v2.2 | 2026-07-03 | Claude Code(CEO指示による) | [AI_WORKSPACE/](../AI_WORKSPACE/README.md) 新設に伴い、ChatGPT発素材の受け渡しはAI_WORKSPACE経由とするルールを追加 |

*最終更新: 2026-07-03*
