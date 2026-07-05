# 60. Editorial Workflow — 編集体制と更新フロー

このドキュメントは、PROJECT_BIBLE(株式会社スマートラボのOS・憲法)が、誰によって・どのような手順で更新されるかを定めます。PROJECT_BIBLEがSingle Source of Truthであり続けるためには、内容そのものだけでなく、「どう更新されるか」というプロセスも明確でなければなりません。

---

## 役割体制

| 役割 | 担当 | 責務 |
|---|---|---|
| **意思決定パートナー** | ChatGPT | 経営戦略・ブランド・UI/UX・営業・商品設計・会社方針について、CEOとの対話を通じて意思決定の材料・案を提示する |
| **最終承認者** | CEO(代表) | ChatGPTとの議論を踏まえ、実際に採用する内容を決定し、Project Bibleへの反映を指示する |
| **Project Bible編集長 / Lead Software Engineer 兼 Knowledge Manager** | Claude Code | CEOの更新指示を受け、承認された内容を正確にPROJECT_BIBLEへ反映する。関連ドキュメント・バージョン・CHANGELOGの整合性を保つ実務担当。あわせて、PROJECT_BIBLEおよびGitHubリポジトリ全体の管理責任者として、常に最新版を維持する |

ChatGPTが「何を決めるか」を担い、CEOが「それを採用するか」を決め、Claude Codeが「それをどう記録し、他の全ドキュメントと矛盾なく反映するか、そしてリポジトリ全体を常に最新に保つか」を担う、という三者の役割分担です。将来Codex等の別AIが加わった場合も、この三者体制の中に「意思決定パートナー」または「実装・編集担当」として位置づけます。

---

## 更新フロー(6ステップ)

```
① ChatGPTとの会話で方向性が決定
        ↓
② CEOが更新指示
        ↓
③ Claude CodeがPROJECT_BIBLEを更新
        ↓
④ Version更新
        ↓
⑤ CHANGELOG更新
        ↓
⑥ Gitへコミット
```

### ① ChatGPTとの会話で方向性が決定

経営戦略・ブランド・UI/UX・営業・商品設計・会社方針について、CEOがChatGPTと対話し、方向性の案を固める。

### ② CEOが更新指示

CEOが、Claude Codeに対して「何を」「どう」PROJECT_BIBLEに反映するかを指示する。指示には、対象ドキュメント(わかれば)と変更内容の要点を含めることが望ましい。

### ③ Claude CodeがPROJECT_BIBLEを更新

Claude Codeは、指示内容を該当ファイルに反映する。このとき、単一ファイルの編集で終わらせず、以下を必ず確認する([Project Bible更新チェックリスト](#project-bible更新チェックリスト)参照)。

### ④ Version更新

変更の性質に応じて、PROJECT_BIBLE全体のバージョンおよび変更したファイル個別のバージョンをインクリメントする。基準は [README.md](README.md) の「Version管理」を参照。あわせて [CURRENT_STATUS.md](CURRENT_STATUS.md) の該当項目(Version一覧・Current Task・Next Task・Last Update)を最新化する。

### ⑤ CHANGELOG更新

[CHANGELOG.md](CHANGELOG.md) に、変更内容・変更者・バージョンを記録する。

### ⑥ Gitへコミット

[コミットメッセージ規約](#コミットメッセージ規約)に従って `git commit` する。経営層承認が必要な変更([00_Foundation/](00_Foundation/README.md) の01〜05等)は、Pull Requestを経てからマージする。その後、[GitHub運用フロー](#github運用フロー)に従って `git push` し、[作業完了時の報告](#作業完了時の報告)を行う。

---

## GitHub運用フロー

Claude Codeは、PROJECT_BIBLEおよびGitHubリポジトリ全体の管理責任者として、以下の順序を必ず守る。

```
① git status で作業前の状態を確認
        ↓
② 最新版を取得(git fetch / git pull)
        ↓
③ PROJECT_BIBLEを確認(関連ドキュメントを読み、矛盾がないか確認)
        ↓
④ 作業(コード実装・ドキュメント更新)
        ↓
⑤ 動作確認(該当する場合。UI変更はpreviewツールで確認する)
        ↓
⑥ CURRENT_STATUS.md を更新
        ↓
⑦ CHANGELOG.md を更新(重要な変更の場合)
        ↓
⑧ コミット(コミットメッセージ規約に従う)
        ↓
⑨ GitHubへPush
        ↓
⑩ 作業完了時の報告
```

このフローは、PROJECT_BIBLE自体の編集だけでなく、Smart Labo Works・コーポレートサイトなど、このリポジトリ内で行われるすべての開発作業に適用される。

---

## AI_WORKSPACE運用フロー — ChatGPT発の素材の受け渡し

CEOがChatGPTとの対話で作成する画像・資料・参考デザイン・プロンプトを、Claude Codeが安全かつ確実に受け取るための専用フォルダとして [AI_WORKSPACE/](../AI_WORKSPACE/README.md) を設置する(CEO指示、2026-07-03)。

**ChatGPT発の素材を [DESIGN_ASSETS/](../DESIGN_ASSETS/README.md) へ直接保存することは禁止する。必ずAI_WORKSPACEを経由する。** これは、チャット上で共有された画像をファイル化する経路が曖昧だったことに起因する過去の混乱(Hero背景候補5点の実データが未格納のまま推移した事例)を防ぐための恒久ルールである。

### 構成

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

### Claude Codeへのルール

- **新しい素材を利用する場合、必ず `AI_WORKSPACE/INBOX` を最初に確認する。** DESIGN_ASSETSへ未登録データが直接置かれているなど、この経路を経ていない素材を見つけた場合は、CEOに確認する。
- **INBOX / PROCESSING 内のデータは正式データではない。** 実装(`WEBSITE/`・`SmartLaboWorks/` 等)から直接参照してはならない。正式版は必ずDESIGN_ASSETSへ登録してから参照する。
- **作業完了後は、以下を必ず報告する**(詳細は「作業完了時の報告」参照)。
  - どの素材を使用したか
  - 正式保存した場所(DESIGN_ASSETS内のパス)
  - 不要になった素材(ARCHIVEへ移動したものなど)

詳細な運用ルールは [AI_WORKSPACE/README.md](../AI_WORKSPACE/README.md) を参照。

---

## コミットメッセージ規約

コミットの種類に応じて、以下のprefixを使用する。

| Prefix | 用途 |
|---|---|
| `feat:` | 新機能 |
| `docs:` | Project Bible(ドキュメント)の更新 |
| `design:` | デザイン変更 |
| `fix:` | 不具合修正 |
| `refactor:` | リファクタリング |
| `style:` | UI/CSSの調整 |
| `chore:` | フォルダ整理・設定変更 |

例: `docs: PROJECT_BIBLE/00_Foundation/07_Brand_Identity.mdにブランドコピー階層を追加`

---

## 作業完了時の報告

Claude Codeは、作業完了のたびに以下を報告する。

- 変更したファイル
- 追加したファイル
- 削除したファイル
- PROJECT_BIBLE更新の有無
- CURRENT_STATUS.md更新の有無
- CHANGELOG更新の有無
- 現在のVersion一覧(CURRENT_STATUS.mdの5行サマリーを引用してよい)
- コミットメッセージ
- 次にやるべき作業(Next Task)
- **[AI_WORKSPACE/](../AI_WORKSPACE/README.md) の素材を利用した場合は追加で:** どの素材を使用したか / 正式保存した場所(DESIGN_ASSETS内のパス)/ 不要になった素材

---

## 推測で判断しない(エスカレーションルール)

PROJECT_BIBLEに記載されていない重要な判断(会社方針・ブランド・仕様の解釈など)が必要になった場合、Claude Codeは**推測で会社方針を変更してはならない**。以下のいずれかを行う。

1. **CEOへ確認する** — 判断が必要な論点を明確に提示し、CEOの判断を仰ぐ。
2. **ChatGPTとの協議を提案する** — 経営戦略・ブランド・UI/UX等、ChatGPTが意思決定パートナーとして扱う領域に関わる場合は、ChatGPTとの議論を経てから反映することを提案する。

この原則は、[30_AI_Rules.md](30_AI_Rules.md)の「コードより先にBibleを読む」原則、および[00_Foundation/06_Philosophy.md](00_Foundation/06_Philosophy.md)の保守性重視の原則と一貫している。あわせて[11_Development_Principles.md](11_Development_Principles.md)「CEOレビュー原則」も参照(大きな仕様変更・ブランド変更・UI変更は、実装前に変更内容を整理しレビューを受けることを明文化したもの)。

---

## コードより先にBible — 開発時の原則

Claude CodeがSmartLabo Works等のコードを実装する際は、**着手前に必ずPROJECT_BIBLEを読む**。

- 実装しようとしている内容が、PROJECT_BIBLEに書かれた方針と矛盾していないか確認する。
- PROJECT_BIBLEに記載のない新しい判断(仕様の解釈、デザインの選択など)が必要になった場合は、実装を進める前にCEOに確認し、承認された内容を先にPROJECT_BIBLEへ反映してから実装する。
- 「実装が先、ドキュメントは後回し」は禁止する([00_Foundation/06_Philosophy.md](00_Foundation/06_Philosophy.md) の保守性重視の原則にも合致する)。

---

## Project Bible更新チェックリスト

PROJECT_BIBLEを更新する際は、対象ファイルを直すだけで終わらせず、必ず以下を確認する。

- [ ] 変更対象ファイルの内容を更新した
- [ ] 影響を受ける**関連ドキュメント**(相互参照している他ファイル)を更新した
- [ ] 該当フォルダおよび上位の **README.md** の整合性を確認し、必要なら修正した
- [ ] **リンク切れ**がないか確認した(特にファイルの移動・改名・削除を伴う変更の場合)
- [ ] フォルダ構成・番号体系の**見直しが必要かどうか**検討した(新しいテーマが増えた場合、無理に既存ファイルへ詰め込まず、新規ファイル・フォルダの追加を検討する)
- [ ] 変更したファイルの「変更履歴」テーブルに追記した
- [ ] PROJECT_BIBLE全体のVersionを適切にインクリメントした
- [ ] [CHANGELOG.md](CHANGELOG.md) に記録した
- [ ] **仕様変更が既存の実装(`WEBSITE/`、`SmartLaboWorks/` 等)に影響する場合、実装への反映まで完了したか確認した。** 完了できない場合は「仕様のみ更新済み・実装は未反映」であることを [CURRENT_STATUS.md](CURRENT_STATUS.md) に明記し、Next Taskとして残す(仕様だけ更新して完了したかのように報告しない)。
- [ ] Gitにコミットした(コミットメッセージに変更内容を明記)

---

## 関連ドキュメント

- [README.md](README.md) — Version管理の詳細基準
- [CURRENT_STATUS.md](CURRENT_STATUS.md) — 現在のVersion・タスク状況(常に最新化する)
- [CHANGELOG.md](CHANGELOG.md)
- [10_Development_Rules.md](10_Development_Rules.md) — Git/ブランチ運用の詳細
- [30_AI_Rules.md](30_AI_Rules.md) — AIツールの役割分担・利用ルール
- [AI_WORKSPACE/README.md](../AI_WORKSPACE/README.md) — ChatGPT発の素材受け渡しフロー
- [DESIGN_ASSETS/README.md](../DESIGN_ASSETS/README.md) — 正式なブランド資産の管理場所

---

## 変更履歴

| バージョン | 日付 | 変更者 | 変更内容 |
|---|---|---|---|
| v1.0 | 2026-07-03 | Claude Code | 初版作成。ChatGPT/CEO/Claude Codeの三者役割体制と6ステップ更新フロー、Project Bible更新チェックリストを制定 |
| **v2.0** | 2026-07-03 | Claude Code(CEO指示による) | Claude Codeの役割を「Lead Software Engineer 兼 Knowledge Manager」に拡張。「GitHub運用フロー」(git status→pull→Bible確認→作業→動作確認→CURRENT_STATUS更新→CHANGELOG更新→コミット→push→報告の10ステップ)、「コミットメッセージ規約」(feat/docs/design/fix/refactor/style/chore)、「作業完了時の報告」フォーマット、「推測で判断しない」エスカレーションルールを新設。[CURRENT_STATUS.md](CURRENT_STATUS.md) との連携を明記 |
| v2.1 | 2026-07-03 | Claude Code(CEO指摘による) | 「仕様だけ更新して実装への反映が漏れる」不具合(ロゴ・カラーがHomepageに未反映のままCEOに報告していた事例)を受け、Project Bible更新チェックリストに「仕様変更の実装への反映確認」項目を追加 |
| v2.2 | 2026-07-03 | Claude Code(CEO指示による) | **「AI_WORKSPACE運用フロー」を新設。** [AI_WORKSPACE/](../AI_WORKSPACE/README.md)(INBOX/PROCESSING/COMPLETED/ARCHIVEの4段階構成)を正式なChatGPT発素材の受け渡し経路として制定し、DESIGN_ASSETSへの直接保存を禁止。「作業完了時の報告」に、利用した素材・正式保存先・不要素材の報告項目を追加 |
| v2.3 | 2026-07-05 | Claude Code(CEO指示による) | 新設の[11_Development_Principles.md](11_Development_Principles.md)「CEOレビュー原則」への相互参照を追加(大きな仕様変更・ブランド変更・UI変更は実装前にレビューを受けることを明文化したもの) |

*最終更新: 2026-07-05*
