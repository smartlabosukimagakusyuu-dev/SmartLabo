# DESIGN_ASSETS

**Smart Laboのデザイン資産(実データ)管理場所**

---

## このフォルダの目的

`DESIGN_ASSETS` は、Smart Laboのブランド・プロダクトで使用する**デザイン資産の実データ**(画像・SVG・フォントファイル等)を格納する、唯一の正式な場所です(CEO指示、2026-07-03)。

PROJECT_BIBLEやBRAND/DESIGNフォルダが「何を、なぜ、どう使うか」という**思想・仕様**を定義するのに対し、`DESIGN_ASSETS` は「実際のファイルそのもの」を保管します。

| 階層 | 役割 | 具体例 |
|---|---|---|
| [PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md](../PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md) | ブランドの思想・原則 | 「ロゴはカラー/白抜き/アイコンの3種」 |
| [BRAND/](../BRAND/README.md)、[DESIGN/](../DESIGN/README.md) | 仕様・トークンの文書化 | `colors/palette.md`、`system/design-tokens.md` |
| [AI_WORKSPACE/](../AI_WORKSPACE/README.md) | ChatGPT発素材の受け渡し口(未確認データ) | `INBOX/HERO/`、`PROCESSING/` |
| **`DESIGN_ASSETS/`(このフォルダ)** | **実データそのもの(正式登録済み)** | `Logo/smartlabo-logo-color.svg` |

---

## フォルダ構成

`DESIGN_ASSETS` は、2種類のフォルダ体系を併用します。

### 1. 型別フォルダ(ロゴ・アイコン等、単体で完結する素材)

```
DESIGN_ASSETS/
├── Logo/         ← ロゴファイル(カラー/白抜き/アイコン)
├── Background/    ← Hero以外の背景画像・グラフィック
├── Icons/         ← UIアイコン(AI分析/タスク管理/顧客管理 等)
├── Dashboard/      ← ダッシュボードUIのモックアップ・スクリーンショット
├── Buttons/       ← ボタンコンポーネントの素材
├── Cards/         ← カードコンポーネントの素材
├── Graphics/       ← 装飾グラフィック(ネットワークライン、グロー、グラデーション等)
├── Images/         ← 写真素材(オフィス・経営者・都市等)
├── Colors/        ← カラースウォッチファイル・プレビュー画像
└── Fonts/          ← 使用フォントの実データ・ライセンスファイル
```

### 2. 番号付きユースケースフォルダ(生成AI画像など、プロンプト・バージョン管理が必要な素材)

```
DESIGN_ASSETS/
└── 01_HERO/              ← Homepage Hero用ビジュアル一式
    └── Backgrounds/       ← Hero背景画像(候補・採用画像すべてをアーカイブ)
```

Hero・Dashboard・LP等、**複数の生成候補から選定するプロセスを伴う画像**は、`01_HERO/` のように用途ごとに番号を振ったフォルダで管理し、画像ファイルごとにプロンプト・用途・バージョン・世界観・採用状況を記録した `.md` を同名でペア管理します(例: `hero_background_01_sunrise_city.webp` + `hero_background_01_sunrise_city.md`)。新しいユースケースが生まれたら `02_DASHBOARD/` のように連番で追加してください。

各サブフォルダの詳細な利用ルールは、フォルダ内の `README.md` を参照してください。

---

## 現在の状態(2026-07-03時点)

**実データ(SVG/PNG/webp等のバイナリファイル)は、まだ1件も格納されていません。** CEOより「今後正式なロゴデータ等を用意する」旨の指示があり、フォルダ構成・メタデータのみを先行して整備した状態です。それまでの間、各アセットの**仕様**は [PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md](../PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md) や [DESIGN/system/design-tokens.md](../DESIGN/system/design-tokens.md)、[01_HERO/](01_HERO/README.md) 配下の各メタデータに記録されています。

実データが到着次第、[AI_WORKSPACE/INBOX/](../AI_WORKSPACE/INBOX/README.md) 経由で受け取り、該当フォルダに格納し、[50_TODO.md](../PROJECT_BIBLE/50_TODO.md) の該当タスクを消し込んでください。

---

## Design Assets一覧

| ユースケース/種別 | フォルダ | ステータス |
|---|---|---|
| Homepage Hero背景 | [01_HERO/](01_HERO/README.md) | 完成イメージのモックアップ5点は格納済み([Mockups/](01_HERO/Mockups/README.md))。実装用の背景単体データ(Backgrounds/、詳細は[01_HERO/README.md](01_HERO/README.md)参照)は1点採用決定・提供待ち |
| ロゴ | [Logo/](Logo/README.md) | 仕様のみ([00_Foundation/07_Brand_Identity.md](../PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md))、実ファイル未着手 |
| アイコン | [Icons/](Icons/README.md) | 仕様のみ([DESIGN/system/design-tokens.md](../DESIGN/system/design-tokens.md))、実ファイル未着手 |
| その他(Background/Dashboard/Buttons/Cards/Graphics/Images/Colors/Fonts) | 各フォルダ参照 | 未着手 |

新しいアセットを追加・整理したら、この一覧を更新してください。

---

## Design Assets運用ルール

0. **ChatGPT発の素材はAI_WORKSPACEを経由してから登録する。** CEOがChatGPTとの対話で作成した画像・資料・プロンプトを、このフォルダへ直接保存することは禁止です。必ず [AI_WORKSPACE/](../AI_WORKSPACE/README.md) のINBOXで受け取り、Claude Codeが内容を確認・作業した上で、このフォルダへ正式登録してください(詳細フローは [PROJECT_BIBLE/60_Editorial_Workflow.md](../PROJECT_BIBLE/60_Editorial_Workflow.md) の「AI_WORKSPACE運用フロー」を参照)。
1. **実データは必ずこのフォルダに置く。** `BRAND/` や `DESIGN/` に直接バイナリファイルを置かず、それらのフォルダからは `DESIGN_ASSETS/` 内のファイルを参照する形にしてください。
2. **ファイル名は用途がわかる名前にする。** 例: `smartlabo-logo-color.svg`、`smartlabo-logo-white.svg`、`icon-ai-analysis.svg`。バージョンを付ける場合は末尾に `_v2` のように付記してください。
3. **カラー・仕様と実データを一致させる。** 実データを追加・変更したら、対応する仕様ドキュメント([00_Foundation/07_Brand_Identity.md](../PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md)、[design-tokens.md](../DESIGN/system/design-tokens.md))に相違がないか確認してください。矛盾があれば仕様側を先に更新します([60_Editorial_Workflow.md](../PROJECT_BIBLE/60_Editorial_Workflow.md) の更新フローに従う)。
4. **禁止ビジュアルを持ち込まない。** [PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md](../PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md) の「禁止するビジュアル表現」に反する素材(安っぽいAIロボット、六角形背景等)は格納しないでください。

---

## Version管理

- 各アセットファイルは、変更のたびに上書きせず、意味のある単位で `_v2` 等のバージョンを付けて追加することを推奨します。
- 旧バージョンは削除せず、[ARCHIVE/](../ARCHIVE/README.md) へ移動してください。
- **生成AIで作成した画像は、単なる参考画像ではなくSmart Laboのブランド資産として扱います。** 画像ファイルには必ず同名の `.md` メタデータ(プロンプト・用途・テーマ・世界観・ステータス・更新履歴)をペアで用意し、同じ世界観を将来再現できるようにしてください(詳細は [01_HERO/README.md](01_HERO/README.md) の実例を参照)。

---

## 更新ルール

- 新しいアセットを追加する際は、対応するサブフォルダに格納し、そのフォルダの `README.md` の記載内容(命名規則等)に従ってください。
- 既存のブランド原則(色・ロゴ・禁止事項)と矛盾するアセットは追加しないでください。矛盾する変更が必要な場合は、先にPROJECT_BIBLEを更新してください。

---

## Git運用ルール

- 画像・フォントファイルはサイズが大きくなりがちなため、必要に応じて Git LFS の利用を検討してください。
- ロゴなどブランドの根幹に関わるアセットの追加・変更は、コミットメッセージに `design:` プレフィックスを使用し、変更内容を明記してください。

---

## 変更履歴

| バージョン | 日付 | 変更者 | 変更内容 |
|---|---|---|---|
| v1.0 | 2026-07-03 | Claude Code(CEO指示による) | 初版作成。10サブフォルダ(Logo/Background/Icons/Dashboard/Buttons/Cards/Graphics/Images/Colors/Fonts)の構成とDesign Assets運用ルールを整備 |
| v2.0 | 2026-07-03 | Claude Code(CEO指示による) | **番号付きユースケースフォルダ**(`01_HERO/` 等)の体系を追加。生成AI画像を「プロンプト・用途・バージョン・世界観」付きの正式ブランド資産として管理するルールを制定。Design Assets一覧セクションを新設 |
| v2.1 | 2026-07-03 | Claude Code(CEO指示による) | [AI_WORKSPACE/](../AI_WORKSPACE/README.md) 新設に伴い、**ChatGPT発の素材をこのフォルダへ直接保存することを禁止**し、AI_WORKSPACE経由での受け取りを運用ルールの第一項目として制定 |
| v2.2 | 2026-07-03 | Claude Code(CEO指示による) | AI_WORKSPACE経由でHero候補5点の完成イメージモックアップを受領し、[01_HERO/Mockups/](01_HERO/Mockups/README.md) へ格納。Design Assets一覧を更新 |

*最終更新: 2026-07-03*
