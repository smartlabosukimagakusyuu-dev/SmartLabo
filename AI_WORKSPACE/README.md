# AI_WORKSPACE

**ChatGPTからの受け渡し専用フォルダ — Design Assetsへの「入口」**

---

## このフォルダの目的

`AI_WORKSPACE` は、ChatGPTから提供される画像・資料・参考デザイン・プロンプトを、Claude Codeが安全かつ確実に受け取るための専用フォルダです(CEO指示、2026-07-03)。

これまで、CEOがChatGPT経由で作成した素材(Hero背景候補など)を [DESIGN_ASSETS/](../DESIGN_ASSETS/README.md) へ直接格納しようとした際、「チャット上の画像をファイル化する手段がない」という受け渡し経路の不備が繰り返し発生しました。`AI_WORKSPACE` は、この受け渡し経路を明文化し、**素材が「未確認の一時データ」なのか「正式なブランド資産」なのか**を常に区別できるようにするために新設されました。

**今後、ChatGPT発の素材をDesign Assetsへ直接保存することは禁止します。必ずAI_WORKSPACEを経由してください。**

---

## フォルダ構成

```
AI_WORKSPACE/
├── INBOX/          ← ChatGPTが作成した素材の受け取り口(未確認・未処理)
│   ├── HERO/
│   ├── BACKGROUND/
│   ├── DASHBOARD/
│   ├── LOGO/
│   ├── ICONS/
│   ├── INDUSTRY/
│   ├── REFERENCE/
│   └── PROMPTS/
├── PROCESSING/      ← Claude Codeが確認・作業中の素材
├── COMPLETED/       ← 作業完了・DESIGN_ASSETSへ正式登録済みの履歴
└── ARCHIVE/         ← 不採用・不要になった素材の保管(理由付き)
```

各フォルダの詳細な利用ルールは、フォルダ内の `README.md` を参照してください。

---

## 運用ルール(6ステップ)

```
① ChatGPTが素材を作成
        ↓
② INBOXへ保存
        ↓
③ Claude Codeが確認
        ↓
④ PROCESSINGへ移動
        ↓
⑤ 作業完了後、DESIGN_ASSETSへ正式登録
        ↓
⑥ COMPLETEDへ履歴保存
```

1. **① ChatGPTが素材を作成** — CEOがChatGPTとの対話で画像・資料・プロンプトを作成する。
2. **② INBOXへ保存** — 作成された素材を、用途に応じて [INBOX/](INBOX/README.md) 配下の該当サブフォルダへ保存する(保存作業自体はCEOまたはChatGPT連携により行われる)。
3. **③ Claude Codeが確認** — Claude Codeは、新しい素材を利用する前に必ず `AI_WORKSPACE/INBOX` を確認する。
4. **④ PROCESSINGへ移動** — 利用する素材を [PROCESSING/](PROCESSING/README.md) へ移動し、作業中であることを明示する。
5. **⑤ DESIGN_ASSETSへ正式登録** — 作業完了後、正式版を [DESIGN_ASSETS/](../DESIGN_ASSETS/README.md) の該当フォルダへ登録する(プロンプト・用途・バージョン等のメタデータを付与するルールは [DESIGN_ASSETS/README.md](../DESIGN_ASSETS/README.md) の「Version管理」に従う)。
6. **⑥ COMPLETEDへ履歴保存** — INBOX/PROCESSINGにあった元データは [COMPLETED/](COMPLETED/README.md) へ移動し、「いつ・何を・どこへ登録したか」の履歴として残す。不採用になった素材は [ARCHIVE/](ARCHIVE/README.md) へ移動する。

---

## Claude Codeへのルール

- **新しい素材を利用する場合、必ず `AI_WORKSPACE/INBOX` を最初に確認してください。** Design Assetsへ直接保存されている未登録の素材があった場合は、正規の経路(AI_WORKSPACE経由)でないことをCEOに確認してください。
- **作業完了後は、以下を必ず報告してください。**
  - どの素材を使用したか
  - 正式保存した場所([DESIGN_ASSETS/](../DESIGN_ASSETS/README.md) 内のパス)
  - 不要になった素材(ARCHIVEへ移動したものなど)
- この報告は、[PROJECT_BIBLE/60_Editorial_Workflow.md](../PROJECT_BIBLE/60_Editorial_Workflow.md) の「作業完了時の報告」フォーマットに追加項目として組み込まれています。

---

## 重要 — INBOXは正式データではない

**`INBOX` / `PROCESSING` 内のデータは、あくまで受け渡し中の一時データであり、Smart Laboの正式なブランド資産ではありません。**

- 正式版は必ず [DESIGN_ASSETS/](../DESIGN_ASSETS/README.md) へ登録してください。
- `WEBSITE/`・`SmartLaboWorks/` 等の実装から、`AI_WORKSPACE` 配下のファイルを直接参照しないでください(必ずDESIGN_ASSETS登録後のパスを参照する)。
- 禁止ビジュアル(六角形背景・安っぽいAIロボット等、[PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md](../PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md) 参照)に該当する素材は、DESIGN_ASSETSへ登録せず [ARCHIVE/](ARCHIVE/README.md) で理由付きで保管してください。

---

## 関連ドキュメント

- [PROJECT_BIBLE/60_Editorial_Workflow.md](../PROJECT_BIBLE/60_Editorial_Workflow.md) — AI_WORKSPACE運用フローの正式な制定箇所
- [DESIGN_ASSETS/README.md](../DESIGN_ASSETS/README.md) — 正式なブランド資産の管理場所
- [PROJECT_BIBLE/30_AI_Rules.md](../PROJECT_BIBLE/30_AI_Rules.md) — AIツール共通利用ルール

---

## 変更履歴

| バージョン | 日付 | 変更者 | 変更内容 |
|---|---|---|---|
| v1.0 | 2026-07-03 | Claude Code(CEO指示による) | 初版作成。INBOX/PROCESSING/COMPLETED/ARCHIVEの4段階構成と6ステップ運用ルールを制定 |

*最終更新: 2026-07-03*
