# PROJECT_BIBLE

**株式会社スマートラボのOS(Operating System)**

> ## これはドキュメントではありません。
> `PROJECT_BIBLE` は、単なる資料集ではありません。
>
> - 株式会社スマートラボの **OS(Operating System)** です。すべての判断はここを基盤に動きます。
> - 会社の **憲法** です。矛盾する運用が生まれたときは、必ずこちらが優先されます。
> - 会社の **知識ベース** です。理念からブランド、開発ルール、歴史まで、ここを読めばすべてがわかります。
> - **ChatGPT・Claude Code・Codex・将来のAI・将来の社員・外部パートナー**、全員が最初に読む設計書です。
>
> **PROJECT_BIBLE Version: 3.7**

---

## Single Source of Truth

このフォルダは、株式会社スマートラボに関する**唯一の正しい設計書(Single Source of Truth)**です。

会社の理念・ブランド・商品思想・デザイン思想・開発ルール・AIルール・歴史について、他の場所(チャット履歴、口頭の申し送り、個人のメモなど)に異なる情報がある場合、**必ずこのフォルダの内容が優先されます**。矛盾を見つけた場合は、放置せず該当ファイルを更新するか、[50_TODO.md](50_TODO.md) に記録してください。

AIが変わっても、社員が増えても、会社の思想が変わらないこと——それがこのフォルダの存在理由です。

---

## PROJECT_BIBLEを読むと何がわかるか

このフォルダを読み進めるだけで、以下すべてが理解できる状態を目指しています。

| 知りたいこと | 参照先 |
|---|---|
| 会社理念(Mission / Vision / Value) | [00_Foundation/03_Mission.md](00_Foundation/03_Mission.md)〜[05_Value.md](00_Foundation/05_Value.md) |
| ブランド | [00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md) |
| 商品思想(Smart Labo Works) | [00_Foundation/08_SmartLaboWorks_Concept.md](00_Foundation/08_SmartLaboWorks_Concept.md) |
| デザイン思想 | [20_UI_UX_Rules.md](20_UI_UX_Rules.md) |
| 開発ルール | [10_Development_Rules.md](10_Development_Rules.md) |
| AIルール | [30_AI_Rules.md](30_AI_Rules.md) |
| UIルール | [20_UI_UX_Rules.md](20_UI_UX_Rules.md) |
| 会社の歴史 | [00_Foundation/01_Company_Story.md](00_Foundation/01_Company_Story.md)、[00_Foundation/09_Product_History.md](00_Foundation/09_Product_History.md) |
| 開発原則(何を作る前に何を確認するか) | [11_Development_Principles.md](11_Development_Principles.md) |

人間の社員だけでなく、ChatGPT・Claude Code・Codex などのAIエージェントも、作業前に必ずこのフォルダを参照することを前提としています。

> **新しいChatGPTの会話を始める前は、必ず [CURRENT_STATUS.md](CURRENT_STATUS.md) を確認してください。** 現在のVersion一覧・Current Task・Next TaskをChatGPTに共有するための「5行サマリー」が用意されています。

---

## フォルダ・ファイル構成

```
PROJECT_BIBLE/
├── README.md               ← このファイル(全体の入口)
├── CURRENT_STATUS.md         ← 現在のVersion・タスク状況(ChatGPT等との同期ポイント)
├── CHANGELOG.md             ← PROJECT_BIBLE全体の変更履歴
│
├── 00_Foundation/            ← 会社の土台となる思想(公式見解)
│   ├── README.md
│   ├── 01_Company_Story.md          会社のストーリー
│   ├── 02_Founder_Message.md        創業者からのメッセージ
│   ├── 03_Mission.md                ミッション
│   ├── 04_Vision.md                 ビジョン
│   ├── 05_Value.md                  バリュー
│   ├── 06_Philosophy.md             会社を貫く哲学
│   ├── 07_Brand_Identity.md         ブランドアイデンティティ
│   ├── 08_SmartLaboWorks_Concept.md 商品コンセプト
│   ├── 09_Product_History.md        プロダクトの歴史
│   └── 10_Future_Roadmap.md         将来のロードマップ
│
├── 10_Development_Rules.md   ← 開発ルール
├── 11_Development_Principles.md ← 開発原則(開発前に何を確認・判断するか)
├── 20_UI_UX_Rules.md         ← デザイン思想・UI/UXルール
├── 30_AI_Rules.md            ← AI利用ルール(Claude / Codex / ChatGPT共通)
├── 40_Organization.md        ← 組織図・役割分担
├── 50_TODO.md                ← 全社レベルの未対応タスク
├── 60_Editorial_Workflow.md  ← PROJECT_BIBLE自体の編集体制・更新フロー
│
└── 99_CEO_MEMORY/             ← 代表個人の記憶(生の記録)
    ├── README.md
    ├── 01_Founding_Story.md         会社設立までの経緯
    ├── 02_Key_Decisions.md          重要な経営判断
    ├── 03_Future_Vision.md          将来構想
    ├── 04_Brand_Philosophy.md       ブランド思想
    └── 05_Important_Conversations.md 重要な会話
```

### なぜこの番号体系なのか

- **`00` (Foundation)** — すべての判断の土台。他のすべてのルールはここから導かれます。
- **`10`〜`60`(実務ルール)** — 開発・デザイン・AI・組織・タスク・編集体制という実務領域。10刻みにすることで、将来「15_Security_Rules.md」のようにルールが増えても、既存の番号を変更せずに挿入できます。
- **`99` (CEO Memory)** — 会社のあらゆる物語が最終的に集約される場所。番号を末尾に置くことで、「00から始まり99に積み重なっていく」という会社の成長を表しています。

---

## 運用体制 — 誰が、どうPROJECT_BIBLEを更新するか

PROJECT_BIBLEは以下の三者体制で運用されます。詳細な役割分担と更新フローは [60_Editorial_Workflow.md](60_Editorial_Workflow.md) を参照してください。

| 役割 | 担当 |
|---|---|
| 意思決定パートナー(経営戦略・ブランド・UI/UX・営業・商品設計・会社方針) | ChatGPT |
| 最終承認者 | CEO(代表) |
| Project Bible編集長(承認内容をPROJECT_BIBLEへ反映) | Claude Code |

更新は「①ChatGPTとの会話で方向性決定 → ②CEOが更新指示 → ③Claude CodeがPROJECT_BIBLEを更新 → ④Version更新 → ⑤CHANGELOG更新 → ⑥Gitへコミット」という6ステップで行われます。

---

## 利用ルール

1. **迷ったらまずここを読む。** 新しい施策・機能・デザインを検討する際は、必ず該当ドキュメントに目を通してから着手してください。
2. **矛盾する判断をしない。** ここに書かれた内容と矛盾する意思決定を行う場合は、先にこのドキュメントを更新してから実行してください。「実装が先、ドキュメントは後回し」を避けます。
3. **AIエージェントへの指示にも適用する。** ChatGPT・Claude Code・Codex にタスクを依頼する際は、関連するドキュメントを読み込ませた上で指示してください。`PROMPTS/` 内のテンプレートを活用してください。
4. **番号順は変更しない。** `00 → 10 → 20 → 30 → 40 → 50 → 60 → 99` の順序は、思想→実務ルール→編集体制→個人の記憶という依存関係を表しています。新しいドキュメントを追加する場合は、この体系に沿って番号を採番してください。
5. **唯一の正解として扱う。** 社員・AI・外部パートナーが異なる説明をしている場合でも、最終的な判断基準は常にこの PROJECT_BIBLE です。矛盾に気づいたら、口頭やチャットで済ませず、必ずここに反映してください。
6. **00_Foundation と 99_CEO_MEMORY を混同しない。** `00_Foundation` は誰が読んでも通用する公式見解、`99_CEO_MEMORY` は代表個人の生の記録です。詳細は [99_CEO_MEMORY/README.md](99_CEO_MEMORY/README.md) を参照してください。
7. **CURRENT_STATUS.mdを常に最新に保つ。** PROJECT_BIBLEを更新したら、[CURRENT_STATUS.md](CURRENT_STATUS.md) のVersion一覧・Current Task・Next Task・Last Updateも合わせて更新してください。

---

## Version管理

PROJECT_BIBLEは、以下の2階層でバージョンを管理します。

### 1. PROJECT_BIBLE 全体のバージョン

- 現在: **Version 3.7**
- 大きな構成変更(フォルダ構造の変更、Mission/Visionなど根幹の改訂)があった場合、`1.0 → 1.1 → 1.2 → 2.0` のように育てていきます。
- 全体バージョンの変更は [CHANGELOG.md](CHANGELOG.md) に必ず記録してください。
- 目安: 誤字修正や1ファイル内の軽微な追記は据え置き。1ファイルの実質的な内容変更で `+0.1`。フォルダ構成の変更や `00_Foundation` の根幹改訂で `+1.0`。

### 2. ファイル個別のバージョン

- 各ドキュメントは末尾に **「変更履歴」テーブル**(バージョン / 日付 / 変更者 / 変更内容)を持ちます。内容を変更した際は、必ずこのテーブルに1行追加してください。
- 大きな方針変更は、変更前バージョンが Git履歴として残るため、削除ではなく上書き編集で問題ありません(詳細な差分は `git log` / `git blame` で追跡可能)。

### 変更履歴テーブルの書式

```markdown
## 変更履歴

| バージョン | 日付 | 変更者 | 変更内容 |
|---|---|---|---|
| v1.0 | 2026-07-03 | Smart Labo | 初版作成 |
```

「変更者」は個人名でも役割名でも構いません。AIエージェントが編集した場合は `Claude Code` のように明記してください。

---

## 更新ルール

- **`00_Foundation`(会社の土台)** は会社の根幹です。特に `01`〜`05`(Company Story / Founder Message / Mission / Vision / Value)の変更には経営層(代表)の承認が必要です。`06`〜`10` はプロダクト責任者・開発リード承認のもとで更新してください。
- **`10`〜`50`(実務ルール)** はプロダクト責任者・開発リードの承認のもとで更新してください。`50_TODO.md` は積極的に随時更新して構いません。
- **`60_Editorial_Workflow.md`(編集体制)** の変更(役割体制・更新フローそのものの変更)はCEOの承認が必要です。Claude Codeが単独で更新フローを変更することはできません。
- **`99_CEO_MEMORY`** はCEO本人が更新することを基本とします。代筆・編集を行う場合は必ず本人の確認を得てください。
- 更新時は、影響を受ける他のドキュメント(README、PROMPTS内のAI指示文など)との整合性を必ず確認してください。
- 内容を変更したら、そのファイルの「変更履歴」テーブルに追記し、重要な変更であれば [CHANGELOG.md](CHANGELOG.md) にも記録してください。

---

## Git運用ルール

- 作業の開始から完了までの手順(git status確認 → 最新版取得 → PROJECT_BIBLE確認 → 作業 → 動作確認 → CURRENT_STATUS更新 → CHANGELOG更新 → コミット → push → 報告)は [60_Editorial_Workflow.md](60_Editorial_Workflow.md) の「GitHub運用フロー」を参照してください。
- コミットメッセージは `feat:` `docs:` `design:` `fix:` `refactor:` `style:` `chore:` のprefixを使い分け、どのドキュメントの何を変更したかを明記してください(詳細: [10_Development_Rules.md](10_Development_Rules.md) のコミットメッセージ規約)。
  - 例: `docs: PROJECT_BIBLE/00_Foundation/05_Value.mdに「誠実」を追加`
- 経営層承認が必要な変更(`00_Foundation` の01〜05)は、Pull Requestを作成しレビューを経てから `master` にマージしてください。
- それ以外の軽微な更新は直接コミットで構いません。
- コミットする際は、対象ファイルの「変更履歴」テーブル更新を含めてコミットしてください(変更内容と履歴の記録を1コミットにまとめる)。

---

## 変更履歴

| バージョン | 日付 | 変更者 | 変更内容 |
|---|---|---|---|
| v0.1 (draft) | 2026-07-03 | Smart Labo | 試作版作成(01〜12の連番フラット構成) |
| v0.2 (draft) | 2026-07-03 | Claude Code | Single Source of Truth宣言、CEO Noteの追加を試作 |
| **v1.0** | 2026-07-03 | Claude Code | **正式版として全面再設計・確定。** `00_Foundation/`(会社の土台)と `99_CEO_MEMORY/`(代表の記憶)を新設し、10/20/30/40/50番台へ実務ルールを再編。会社のOS・憲法としての位置づけを明記し、PROJECT_BIBLEをVersion 1.0として正式運用開始 |
| v1.1 | 2026-07-03 | Claude Code | [60_Editorial_Workflow.md](60_Editorial_Workflow.md) を新設。ChatGPT(意思決定パートナー)/CEO(最終承認者)/Claude Code(Project Bible編集長)の三者役割体制と、6ステップ更新フロー(方向性決定→更新指示→Bible更新→Version更新→CHANGELOG更新→コミット)を正式制定 |
| v1.2 | 2026-07-03 | Claude Code(CEO指示による) | ブランドコンセプト「Quiet Intelligence(静かな知性)」を正式制定。ポジショニング「AI会社ではなく社長の右腕」、参照企業にPorscheを追加(5社)、使用カラーをWhite/Black/Smart Blueに限定、UI/UXルールを9原則に拡充。`PROMPTS/DESIGN/` を新設し、Design Bible・Homepage・Dashboard・LandingPageの制作プロンプトを整備 |
| **v2.0** | 2026-07-03 | Claude Code(CEO指示による全面ピボット) | **ブランド方針をQuiet IntelligenceからEnterprise AI Platformへ全面ピボット。** ポジショニングを「会社を進化させる会社」に、参照企業をMicrosoft/OpenAI/AWS/Palantir/Stripe/Datadog/Snowflake/Vercelの8社に刷新。カラーにNavy/Light Grayを追加、ボタンを大きめ・ホバーアニメーションに、Homepageの構成・CTA(2→3)を変更。詳細な変更理由は [CHANGELOG.md](CHANGELOG.md) を参照 |
| v2.1 | 2026-07-03 | Claude Code(CEO本人執筆の反映) | CEO本人による [99_CEO_MEMORY/04_Brand_Philosophy.md](99_CEO_MEMORY/04_Brand_Philosophy.md) の初の実質記入を反映。「ブランドの原則」6項目(特に「高級感より品格」)を [00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md) に正式制定し、CEO Messageを [00_Foundation/02_Founder_Message.md](00_Foundation/02_Founder_Message.md) 冒頭に反映 |
| v2.2 | 2026-07-03 | Claude Code(CEO指示による) | CEOより改めてEnterprise AI Platform方針の確立指示を受領(内容はv2.0で確定済みのものと大部分が一致)。新規要素として**ブランドコピー階層**(ブランドコピー/サービスコピー/説明コピー)を正式制定し「経営を、ひとつにつなぐ。」「社長の右腕になるAI経営プラットフォーム。」を追加。ブランドキーワードと適用範囲(パンフレット・デモ画面)を拡張。詳細は [CHANGELOG.md](CHANGELOG.md) を参照 |
| **v2.3** | 2026-07-03 | Claude Code(CEO指示による) | **[CURRENT_STATUS.md](CURRENT_STATUS.md) を新設。** Claude Codeの役割を「Project Bible編集長 / Lead Software Engineer 兼 Knowledge Manager」に拡張し、GitHub運用フロー(10ステップ)・コミットメッセージ規約(feat/docs/design/fix/refactor/style/chore)・作業完了時の報告フォーマット・推測で判断しないエスカレーションルールを [60_Editorial_Workflow.md](60_Editorial_Workflow.md) に制定。ChatGPTとの新セッション開始時に共有する「5行サマリー」の仕組みを整備 |
| v2.4 | 2026-07-03 | Claude Code(CEO提供のブランドキット参照資料による) | CEOから提供されたブランドキット参照資料に基づき、**ロゴ仕様**(カラー/白抜き/アイコンの3バリエーション)・**正式カラーパレット**(8色、hexコード)・**タイポグラフィ階層**・**UIコンポーネントの型**(ボタン/入力欄/カード/ステータスバッジ)・**アイコンセット**(8種)を正式制定。「Smart Blueのみ」の原則を「Smart Blueファミリー(4階調)」に精緻化。[BRAND/colors/palette.md](../BRAND/colors/palette.md)、[DESIGN/system/design-tokens.md](../DESIGN/system/design-tokens.md) を新設し、[PROMPTS/DESIGN/Dashboard.md](../PROMPTS/DESIGN/Dashboard.md) に具体的なダッシュボード標準レイアウトを追加 |
| **v2.5** | 2026-07-03 | Claude Code(CEO確認・指示による) | **Smart Blue Familyの解釈をCEOが正式確認。** ブランドカラーはSmart Blueのみとし、Light Blue/Accent Blueは階調として運用することを確定。**[DESIGN_ASSETS/](../DESIGN_ASSETS/README.md) を新設**し、デザイン資産の実データ管理場所として位置づけ(Logo/Background/Icons/Dashboard/Buttons/Cards/Graphics/Images/Colors/Fontsの10サブフォルダ)。BRAND/DESIGNフォルダは「仕様・定義」、DESIGN_ASSETSは「実データ」という役割分担に整理し、関連ドキュメントの格納先参照をすべて更新 |
| v2.6 | 2026-07-03 | Claude Code(CEO指示による) | **DESIGN_ASSETSに「番号付きユースケースフォルダ」体系を追加**し、[DESIGN_ASSETS/01_HERO/Backgrounds/](../DESIGN_ASSETS/01_HERO/README.md) を新設。CEOより提示されたHomepage Hero背景候補5点を、画像ファイルごとにプロンプト・用途・テーマ・世界観・ステータスを記録した`.md`メタデータとペア管理する方式を正式制定(候補5点のうち1点採用・3点アーカイブ・1点は禁止ビジュアル該当で使用不可)。実画像ファイル(`.webp`)は未格納で、CEOからのファイル提供待ち |
| **v2.7** | 2026-07-03 | Claude Code(CEO指示による) | **[AI_WORKSPACE/](../AI_WORKSPACE/README.md) を新設。** ChatGPT発の素材(画像・資料・プロンプト)をClaude Codeが受け取るための正式な受け渡し経路として、INBOX/PROCESSING/COMPLETED/ARCHIVEの4段階構成を制定。DESIGN_ASSETSへの直接保存を禁止し、[60_Editorial_Workflow.md](60_Editorial_Workflow.md) に「AI_WORKSPACE運用フロー」(6ステップ)を正式追加。作業完了報告フォーマットに利用素材・保存先・不要素材の報告項目を追加 |
| v2.8 | 2026-07-03 | Claude Code(CEO提供による) | **AI_WORKSPACE運用フローの初運用。** CEOが `AI_WORKSPACE/INBOX/HERO/` にHero候補5点の完成イメージ画像(1枚の比較用合成画像)を格納。Claude Codeが確認したところ、Smart Laboロゴ・見出し・CTAボタンが焼き込まれた「完成イメージのモックアップ」であり、実装用のCSS背景画像としてそのまま使うと本物のHTMLテキストと二重表示になる問題を発見。CEOに確認のうえ、[DESIGN_ASSETS/01_HERO/Mockups/](../DESIGN_ASSETS/01_HERO/Mockups/README.md) を新設し「参考資料」として格納。実装用の背景単体データは引き続き別途依頼が必要であることを明記 |
| **v2.9** | 2026-07-03 | Claude Code(CEO提供による) | **Hero背景単体データ5点をAI_WORKSPACE経由で受領し、Homepageへ実装完了。** [DESIGN_ASSETS/01_HERO/Backgrounds/](../DESIGN_ASSETS/01_HERO/README.md) にテキスト焼き込みのない背景単体データを格納し、都市とAIネットワークが1枚に収まる`hero_background_02_city_network.webp`をHomepage Heroに採用。同時に、CEOより提供されたHomepageアイコン参考資料([DESIGN_ASSETS/Icons/Reference/](../DESIGN_ASSETS/Icons/Reference/))から「バッジスタイル」のみをSmart Blue Family単色で採用し、多色配色・別ロゴ案は明示的に不採用として記録(ブランドルールとの不整合、CEO未承認のため) |
| **v3.0** | 2026-07-04 | Claude Code(CEO指示による) | **正式ロゴが確定。** これまでの暫定ロゴ仕様(途切れた2画のストローク)・別ロゴ案(角丸+「+」マーク)を廃止し、六角形フレーム+サーキットパターンの「S」字シンボルを株式会社スマートラボの唯一の正式ロゴとして制定([00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md) v5.0)。[DESIGN_ASSETS/01_LOGO/](../DESIGN_ASSETS/01_LOGO/README.md) を新設しPrimary/White/Dark/Icon/Favicon/SVG/PNG/WebPの8フォルダで実データを格納。「ロゴ使用ルール」(余白規定・最小サイズ・背景色・使用禁止例・用途別推奨サイズ)を正式制定し、Homepageのヘッダー・フッター・faviconに実装。00_Foundationの根幹改訂のためVersionを+1.0 |
| v3.1 | 2026-07-05 | Claude Code(CEO指示による) | **Homepage Version 1.0 Release Candidateへ向けた最終ブラッシュアップを反映。** [PROMPTS/DESIGN/Homepage.md](../PROMPTS/DESIGN/Homepage.md)(v2.1→v3.0)にCompany Brainセクション(最大の差別化要素)を正式なページ構成へ追加。ダッシュボードのデモデータ表記、CTA導線の整理(資料ダウンロードの「準備中」明示、リンク切れ修正)、ヘッダーのレスポンシブ崩れ修正、404ページ・OGP・favicon最適化をチェックリストへ反映 |
| v3.2 | 2026-07-05 | Claude Code(CEO指示による) | **Company BrainをSmart Labo Works最大の差別化要素として正式formalize。** [00_Foundation/08_SmartLaboWorks_Concept.md](00_Foundation/08_SmartLaboWorks_Concept.md)(v2.0→v3.0)に中核基盤として追加、[00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md)(v5.0→v5.1)に専用機能コピーとブランド適用範囲を追記、[00_Foundation/10_Future_Roadmap.md](00_Foundation/10_Future_Roadmap.md)(v2.0→v3.0)に拡張ロードマップを追加。新設の[PROMPTS/DESIGN/CompanyBrain.md](../PROMPTS/DESIGN/CompanyBrain.md)をホームページ・Dashboard・営業資料・パンフレット・デモ画面共通のマスタープロンプトとして制定 |
| v3.3 | 2026-07-05 | Claude Code(CEO指示による) | **新章[11_Development_Principles.md](11_Development_Principles.md)を新設。** 開発前ルール(PROJECT_BIBLE/Brand Bible/Design Bible/CURRENT_STATUS/CHANGELOGの確認必須化)、Smart Labo Design Principle(5項目チェックリスト)、デモ画面作成ルール、Company Brain優先方針、Dashboard設計原則(「CRMではなくCompany OS」)、ブランド維持、Version管理徹底、最重要原則(「Smart Labo Worksは会社を進化させるCompany OS」)を制定。`00_Foundation`の01〜10がすべて埋まっているため、開発関連ドキュメント(`10`〜`19`)の枠に`11`として新設 |
| v3.4 | 2026-07-05 | Claude Code(CEO指示による) | [11_Development_Principles.md](11_Development_Principles.md)(v1.0→v1.1)に「CEOレビュー原則」を追加。大きな仕様変更・ブランド変更・UI変更は実装前に変更内容を整理しレビューを受けてから開発を進めること、独自判断でブランドコンセプト・UI方針を変更しないことを明文化。[60_Editorial_Workflow.md](60_Editorial_Workflow.md)(v2.2→v2.3)の既存エスカレーションルールとの相互参照を追加 |
| **v3.5** | 2026-07-10 | Claude Code(CEO指示による) | **Company OSとSmart Labo Worksの製品呼称・境界を正式決定。** Smart Labo Works=顧客へ販売するSaaS製品、Company OS=株式会社スマートラボの社内専用システム(非売品)と明確化。[11_Development_Principles.md](11_Development_Principles.md)(v1.1→v1.2)・[00_Foundation/08_SmartLaboWorks_Concept.md](00_Foundation/08_SmartLaboWorks_Concept.md)(v3.0→v3.1)から、Company OSをSmart Labo Works自体の形容として使っていた箇所を修正。「Smart Labo Group」は非公式な将来のブランド構想、「Smart Labo AI」「Smart Labo CRM」は将来の商品候補(正式製品ではない)と明記。詳細は`smartlabo-works/PRODUCT_BOUNDARY.md`(別リポジトリ)を参照 |
| **v3.6** | 2026-07-10 | Claude Code(CEO指示による) | **Smart Labo Worksの唯一の正式コードベースを`smartlabo-works`(Node.js版)に確定。** `WEBSITE/app.html`はSmart Labo Works正式製品ではなく「デモサイト／マーケティング用プレビュー」へ位置づけ変更。背景：マージ作業中に`WEBSITE/app.html`側で並行して「Smart Labo Works v1.0」を名乗るコミットが発覚したため、CEOが正式コードベースを一本化する決定を下した。[11_Development_Principles.md](11_Development_Principles.md)(v1.2→v1.3)・[00_Foundation/08_SmartLaboWorks_Concept.md](00_Foundation/08_SmartLaboWorks_Concept.md)(v3.1→v3.2)・[CURRENT_STATUS.md](CURRENT_STATUS.md)(v3.0.1→v3.1)・[../WEBSITE/README.md](../WEBSITE/README.md)(v1.1→v1.2)を更新 |
| **v3.7** | 2026-07-10 | Claude Code(CEO承認による) | **「Version1.0リファクタリング」5Step計画の開始を反映。** CEO承認により、新機能追加ではなくSmart Labo Worksを販売可能なSaaSへ仕上げることを目的とした5Step(Git初回コミット→REFACTOR_PLAN作成→サーバー認証→AI Router整理→Company OS表記修正)を開始。[CURRENT_STATUS.md](CURRENT_STATUS.md)(v3.1→v3.2)にStepごとの進捗表を新設し、Task1(`smartlabo-works`ソースコードのGit初回コミット)完了を反映 |

*最終更新: 2026-07-10*
