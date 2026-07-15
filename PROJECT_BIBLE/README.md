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
> **PROJECT_BIBLE Version: 7.6**

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
| 料金・導入プランの思想 | [12_Pricing_Philosophy.md](12_Pricing_Philosophy.md) |
| v1.0 Release Checklist(本番公開前) | [61_Release_Checklist.md](61_Release_Checklist.md) |
| CEO公開手順書(8ステップ) | [62_CEO_Publish_Guide.md](62_CEO_Publish_Guide.md) |
| 公開後ロードマップ | [63_Post_Launch_Roadmap.md](63_Post_Launch_Roadmap.md) |
| Release Notes(homepage-v1.0.0) | [64_Release_Notes_v1.0.0.md](64_Release_Notes_v1.0.0.md) |

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
├── 12_Pricing_Philosophy.md  ← 料金・導入プランの思想(利用規模×プラン×オプション)
├── 20_UI_UX_Rules.md         ← デザイン思想・UI/UXルール
├── 30_AI_Rules.md            ← AI利用ルール(Claude / Codex / ChatGPT共通)
├── 40_Organization.md        ← 組織図・役割分担
├── 50_TODO.md                ← 全社レベルの未対応タスク
├── 60_Editorial_Workflow.md  ← PROJECT_BIBLE自体の編集体制・更新フロー
├── 61_Release_Checklist.md   ← v1.0 Release Checklist(本番公開前・再利用可能テンプレート)
├── 62_CEO_Publish_Guide.md   ← CEO公開手順書(スクリーンショット不要・8ステップ)
├── 63_Post_Launch_Roadmap.md ← 公開後ロードマップ(CEO指定優先順位)
├── 64_Release_Notes_v1.0.0.md ← Release Notes(homepage-v1.0.0)
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

- 現在: **Version 7.6**
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
| **v3.8** | 2026-07-10 | Claude Code(CEO承認による) | Task2完了(`smartlabo-works/REFACTOR_PLAN.md`新規作成)を反映。[CURRENT_STATUS.md](CURRENT_STATUS.md)(v3.2→v3.3)のStep進捗表とNext Taskを更新 |
| **v3.9** | 2026-07-10 | Claude Code(CEO承認による) | Task3完了(最小構成のサーバー認証実装)を反映。[CURRENT_STATUS.md](CURRENT_STATUS.md)(v3.3→v3.4)のStep進捗表とNext Taskを更新 |
| **v4.0** | 2026-07-10 | Claude Code(CEO承認による) | Task4完了(AI Routerの正式整理、Providerパターン統一)を反映。[CURRENT_STATUS.md](CURRENT_STATUS.md)(v3.4→v3.5)のStep進捗表とNext Taskを更新 |
| **v4.1** | 2026-07-10 | Claude Code(CEO承認による) | **Task5完了により「Version1.0リファクタリング」5Stepが全完了。** `app.html`・`manifest.json`から「Company OS」文言を全除去しSmart Labo Worksへ統一(コミット`b864dd4`)。[CURRENT_STATUS.md](CURRENT_STATUS.md)(v3.5→v3.6)のStep進捗表を更新し、次スコープ(REFACTOR_PLAN.md Phase1残り)はCEOへ改めて提案する運用とした |
| **v4.2** | 2026-07-11 | Claude Code(CEO承認による) | **Sprint2開始、Task6完了(企業単位データ分離)。** AIログ・認証設定(CompanyID→User構造)・Company Brain/CRM/案件/契約のlocalStorageをCompanyIDで分離。Workspace機能は今回対象外。[CURRENT_STATUS.md](CURRENT_STATUS.md)(v3.6→v3.7)に新設の「Sprint2進捗」表を追加 |
| **v4.3** | 2026-07-11 | Claude Code(CEO承認による) | **Sprint2 Task7完了(CRM・案件・契約のサーバー永続化)。** localStorageから`node:sqlite`(npm依存なし)ベースのSQLiteへ移行し、company_idで分離。REST API(GET/POST/PUT/DELETE)化。旧データの自動移行、Service Workerキャッシュ更新(v1→v2)も実施。[CURRENT_STATUS.md](CURRENT_STATUS.md)(v3.7→v3.8)にStep進捗を追加 |
| **v4.4** | 2026-07-11 | Claude Code(CEO承認による) | **Sprint2 Task8完了(Company Brainのサーバー永続化)。** Task7と同一パターンでSQLite化・REST API化・旧データ自動移行を実装。AI問い合わせ(`/api/ai/brain`)自体のロジックは変更なし。[CURRENT_STATUS.md](CURRENT_STATUS.md)(v3.8→v3.9)にStep進捗を追加 |
| **v4.5** | 2026-07-11 | Claude Code(CEO承認による) | **Sprint3開始、Task1完了(Smart AI Router基盤実装)。** 新設`src/services/router/`にAIProviderインターフェース・OpenAI/Claude/GeminiProvider・9jobType分のルーティングテーブル・簡易分類器・Router Monitorを実装。既存Router(Task4)・`/api/ai/*`は無変更。[CURRENT_STATUS.md](CURRENT_STATUS.md)(v3.9→v4.0)に新設の「Sprint3進捗」表を追加 |
| **v4.6** | 2026-07-11 | Claude Code(CEO承認による) | **Sprint3 Task2完了(Claude API正式実装)。** ClaudeProviderのsummarize(長文整理)/generate(提案書)を、routeTable.jsの実割り当てに合わせた専用プロンプトへ差し替え。ANTHROPIC_API_KEY未設定のため実際のAPI成功応答は未確認、ルーティング・エラー処理の正常動作のみ確認済み。[CURRENT_STATUS.md](CURRENT_STATUS.md)(v4.0→v4.1)にStep進捗を追加 |
| **v4.7** | 2026-07-11 | Claude Code(CEO承認による) | **Sprint3 Task3完了(Company Brain Builder)。** 誰でも約30分でCompany Brainを作成できる6Step Wizard(業種選択→会社情報→商品・サービス→業務ルール→FAQ→完成)を新設し、既存のTask8 REST API(`/api/brain`)経由で保存する構成とした。AI自動生成・OCR・Whisper・Gemini・OpenAI生成・Builder自動化は今回対象外。既存デザインを維持するため新規グローバルCSSは追加せず、既存の`.builder-*`/`.template-*`/`.progress-*`クラスを再利用。[CURRENT_STATUS.md](CURRENT_STATUS.md)(v4.1→v4.2)にStep進捗を追加 |
| **v4.8** | 2026-07-12 | Claude Code(CEO承認による) | **Sprint3 Task4 Step2.1〜2.2完了、Template Engine正式採用。** 物件紹介文・査定コメントの2テンプレートを実装し、AIテンプレート基盤を「機能追加型」ではなく「Template追加型」の正式アーキテクチャ(Template Engine)として確定。`templates/<namespace>/`(realestate/management/legal/tax/common)配下にファイルを1つ追加するだけで新テンプレートが使える構造とし、`promptManager.js`はswitch文を持たずTemplate Loaderへの窓口として動作するよう変更。AI Router・Providerは無変更(業種ロジックを一切持たない)。[CURRENT_STATUS.md](CURRENT_STATUS.md)(v4.2→v4.3)にTemplate Engine正式仕様を追加 |
| **v4.9** | 2026-07-12 | Claude Code(CEO承認による) | **「営業テンプレート」「Appointment Template」ファミリー第一弾完了。** `realestate/mail.js`(物件紹介メール、listing.jsに非依存)・`realestate/visit.js`(内覧・来店予約案内、候補日時は将来のカレンダー連携を見据え自由テキスト)を実装。いずれも「1ファイル=1用途」を維持し、将来の拡張(followup/thanks/contract等、来店予約/オンライン商談/契約日時等)は同形式のファイル追加のみで対応する設計。両テンプレートとも変更ファイルは1つのみでTemplate Engineの拡張性を実証した。[CURRENT_STATUS.md](CURRENT_STATUS.md)(v4.3→v4.4)にテンプレート詳細を追加 |
| **v5.0** | 2026-07-12 | Claude Code(CEO承認による) | **ホームページVersion2リニューアル完了。** `WEBSITE/index.html`を「会社紹介サイト」から「Smart Labo Worksを導入したくなる営業サイト」へ全面刷新。Smart AI Router図解・AI社員カード(6種、モデル名ではなく仕事内容で訴求)・画面ショーケース(横スクロール、実装画面をHTML/CSSで再現)・業種展開(不動産売買のみ「対応中」、他5業種は「今後対応予定」と正確に区別)・導入効果ストーリー・料金プラン(価格は「お問い合わせください」)の各セクションを新設。CEO指示にあった「Builder」カードは実装ルール(社内専用機能を顧客向けに非表示)と矛盾するため除外。既存ブランドカラー・正式ロゴのみ使用。[CURRENT_STATUS.md](CURRENT_STATUS.md)(v4.4→v4.5)に詳細を追加。SmartLabo repoはpush未実施 |
| **v5.1** | 2026-07-12 | Claude Code(CEO承認による) | **Smart Labo AIホームページチャットデモ実装完了。** 右下固定ボタン→チャットウィンドウで、Smart Labo Worksを無料体験できるAIデモを新設(問い合わせチャットではない)。「体験できるAI」一覧・おすすめ質問チップ・6機能(営業メール/物件紹介文/査定コメント/FAQ/会社紹介/提案書)のデモ生成・localStorageでの会話履歴保持を実装。実際のSmart AI Routerが本番未デプロイ(公開APIなし)であることを実装前にCEOへ確認し、「UI/UXのみ実装、AI生成はダミー応答で代替」の承認を得て実装(公開APIの新規デプロイはコスト・セキュリティ上未実施)。[CURRENT_STATUS.md](CURRENT_STATUS.md)(v4.5→v4.6)に詳細を追加。SmartLabo repoはpush未実施 |
| **v5.2** | 2026-07-12 | Claude Code(CEO承認による) | **ホームページ情報量整理完了。** 「装飾を増やすのではなく、3秒で価値が伝わるデザインへ」というCEO指示のもと、実装前に新構成案・削除統合対象を報告しCEO承認を得たうえで実施。セクション数11→7、背景ブロック11→5。Company BrainをHero直後へ移動、「AI社員」と「社長の1日タイムライン」を時間帯タグ付きカード6枚へ統合、画面ショーケースをDashboard・不動産AIの大型2枚構成へ刷新。「対応業種」「料金」の2セクションを削除(実質「今後対応予定」「お問い合わせください」のみの準備中表示だったため)。HeroとCTAのボタンを「デモを見る」「導入相談」の2つに全ページ統一。[CURRENT_STATUS.md](CURRENT_STATUS.md)(v4.6→v4.7)に詳細を追加。SmartLabo repoはpush未実施 |
| **v5.3** | 2026-07-12 | Claude Code(CEO承認による) | **Hero「AI Control Center」実装完了。** 実装前にHero新レイアウト・AI Control Center構成・スクロール後のセクション順・削除統合対象を報告しCEO承認を得たうえで実施。Hero右側の縮小ダッシュボードモックアップを、Smart AI Router LIVE・OpenAI/Claude/Gemini稼働状況・ルーティング例・Company Brain参照中・本日のAI処理件数・最近完了タスクを表示する半透明グラスパネルへ刷新(細い接続線と控えめなパルスのみ、新規ブランドカラーなし)。「Smart AI Router」独立セクションと「AI社員」カードセクションを削除し(Heroで役割が重複するため)、業務フロー図(Router→Company Brain→営業/契約/会議/CRM)としてCompany Brainセクションへ統合。画面ショーケースを「Company Brain→不動産AI→CRM→実際の製品画面」の順に再構成しCRMを復活。セクション数7→5。[CURRENT_STATUS.md](CURRENT_STATUS.md)(v4.7→v4.8)に詳細を追加。SmartLabo repoはpush未実施 |
| **v5.4** | 2026-07-12 | Claude Code(CEO承認による) | **Hero Experience Upgrade v3完了。** レイアウト(左コピー／右AI Control Center)は無変更のまま、AI Control Centerを静的UIから常時アニメーションする"Live Dashboard"へ進化。新規`js/hero.js`が2.6秒ごとに処理中ステータス(問い合わせ返信中…/契約レビュー中…/PDF・画像解析中…/Company Brain検索中…)をProviderノードの発光と同期してローテーションし、4.2秒ごとにCompany Brainの状態(参照中→更新中→学習完了)を巡回。Smart AI Routerは呼吸するように発光、接続ラインには小さな光が流れる演出、KPIカード2x2(本日のAI処理件数/Company Brain登録数/契約レビュー件数/問い合わせ返信数)を新設。背景にゆっくり動くglowと淡い浮遊粒子を追加。Heroコピーは「会社を動かすAI。」を主役(54px)、「Smart Labo Works」を副題(20px)に強弱反転。スクロール誘導を新設。`prefers-reduced-motion`で全アニメーション無効化。ロゴ・ブランドカラー・CTA・ナビ・レイアウトは無変更。[CURRENT_STATUS.md](CURRENT_STATUS.md)(v4.8→v4.9)に詳細を追加。SmartLabo repoはpush未実施 |
| **v5.5** | 2026-07-12 | Claude Code(CEO承認による) | **Smart Labo Works 詳細ページ構築完了。** 実装前にワイヤーフレーム・ナビゲーション構成・移動する情報・使用する実画面・表示ルール・URL構成を報告しCEO承認を得たうえで実施。トップページを「興味を持たせる」役割に限定し、`product.html`・`features.html`(10機能詳解)・`real-estate.html`(不動産売買仲介向け)・`pricing.html`(3プラン+機能比較表)・`contact.html`(送信先未接続の問い合わせフォーム)を新設。実装済み／Coming Soon／非表示(社内専用)の3区分表示ルールを全ページへ統一適用し、Company OS・Builder・Innovation Hub等のv1.0対象外機能は非表示。ナビゲーションを全ページ「製品/機能/不動産向け/料金/会社情報」に統一。既存コンポーネントを最大限再利用し新規ブランドカラーなし。[CURRENT_STATUS.md](CURRENT_STATUS.md)(v4.9→v5.0)に詳細を追加。SmartLabo repoはpush未実施 |
| **v5.6** | 2026-07-12 | Claude Code(CEO追加指示による) | **ブランド露出強化完了。** 「製品名ではなくブランドを印象付ける」というCEO指示のもと、トップページ＋詳細5ページの全6ページへ統一適用。ヘッダーロゴを32px→44px(1.375倍)、フッターロゴを28px→38pxへ拡大。`index.html`のHero背景へ820px・透明度7%の巨大透かしロゴ、詳細5ページの`page-hero`にも420px・透明度6%の透かしロゴを追加(いずれもモバイルでは非表示、横スクロールなしを確認)。Hero「AI Control Center」最下部に「Powered by Smart Labo Works」を追加。既存アセットの再利用のみで新規ブランドカラーなし。[CURRENT_STATUS.md](CURRENT_STATUS.md)(v5.0→v5.1)に詳細を追加。SmartLabo repoはpush未実施 |
| **v5.7** | 2026-07-12 | Claude Code(CEO指示による) | **新章[12_Pricing_Philosophy.md](12_Pricing_Philosophy.md)を新設。** 「Smart Labo Worksの料金体系を正式仕様として採用する」というCEO指示に基づき、①利用規模(Small/Medium/Enterprise)×②プラン(Lite/Standard/Premium)×③オプション(Company Brain構築/OCR/議事録/API/AI教育/テンプレート制作/その他)の3要素構造と、「料金ではなく導入プランとして表現する」という基本思想を正式制定。ホームページ・Product Book・Company OS・営業資料・見積書・契約書への共通適用を明記。Company OS「Pricing Manager」(利用規模→プラン→オプション選択で料金表/見積書/契約書/営業資料を自動生成)のデータモデル・画面遷移・生成対象の設計を記載したが、実装はsmartlabo-works側の別Taskとして今後CEO承認を得て着手するものと明記(契約書自動生成は法的正確性のためCEO確認必須)。具体的な金額は未確定のため記載していない。`00_Foundation`の01〜10・`10`〜`11`が既に埋まっているため、`12`として新設 |
| **v5.8** | 2026-07-13 | Claude Code(CEO承認による) | **pricing.htmlを「Smart Labo Configurator」へ変更、[12_Pricing_Philosophy.md](12_Pricing_Philosophy.md)を4層構造(v1.0→v2.0)へ改訂。** 実装前に①UIワイヤー②入力項目③データ構造④Company OSとの共通設計を報告し、CEO承認(4層構造への修正指示付き)を得たうえで実施。CEOの修正指示により、③オプションを単純な追加オプション一覧として扱う方針は不採用となり、「①利用規模×②基本プラン×③利用モジュール×④追加オプション」の4層構造が正式決定。Company Brain/CRM/AI Assistant/案件管理/契約管理/AIテンプレートの6中核機能は選択した基本プランに標準搭載される「③利用モジュール」(選択・変更不可)、Meeting/OCR/外部API連携/AI活用研修等の8項目のみを「④追加オプション」として区別。ホームページを、Step1利用規模→Step2基本プラン→Step3利用モジュール表示→Step4追加オプション→Step5おすすめ構成確認の5Stepシミュレーターへ全面刷新し、新規`WEBSITE/js/configurator.js`が選択状態(`usageScale/plan/modules/options`)を管理・`localStorage`保存。4CTA(無料相談/見積り依頼/資料請求/デモ予約)から選択内容を`contact.html`へURLクエリで自動引き継ぎ。顧客向けUIでは「Company OS」の名称を使用せず、見積書/契約書/提案書の自動生成・CRM連携・バックエンドAPIは今回スコープ外。[CURRENT_STATUS.md](CURRENT_STATUS.md)(v5.2→v5.3)に詳細を追加。SmartLabo repoはpush未実施 |

| **v5.9** | 2026-07-14 | Claude Code(CEO承認による) | **正式リリース前 最終整備 Step1完了(会社概要ページ新設・会社情報表示統一)。** 実装前に会社情報の不足項目・修正対象ファイル・問い合わせフォーム現状・推奨送信基盤・セキュリティ対策一覧・法務ページの不足・Step分割案・完了条件を報告しCEO承認を得たうえで実施。新設`WEBSITE/company.html`（会社概要ページ）は代表者・設立年月・所在地等の未確定項目を推測せず「CEO確認待ち」と明示。新設`WEBSITE/js/company-info.js`が会社情報を一元管理。全ページのフッター著作権表記を正式法人名「株式会社スマートラボ」へ統一し、機能していなかったnav「会社情報」の`#mission`アンカーリンクを`company.html`へ修正。5ページにOGPタグを新規追加。`privacy.html`/`terms.html`をリンク切れ防止の暫定ページとして先行設置（本文ドラフトはStep2）。[CURRENT_STATUS.md](CURRENT_STATUS.md)(v5.3→v5.4)に詳細を追加。SmartLabo repoはpush未実施 |

| **v6.0** | 2026-07-14 | Claude Code(CEO承認による) | **正式リリース前 最終整備 Step2完了(プライバシーポリシー・利用規約ドラフト作成)。** Step1完了直後にCEOより「先にステップ2行こう」との指示があり着手。`WEBSITE/privacy.html`（10セクション）・`WEBSITE/terms.html`（8セクション）を「準備中」の暫定表示から正式ドラフトへ差し替え。両ページ冒頭に「専門家確認が必要なドラフト」である旨を明示し、特定商取引法の該当性・準拠法の管轄裁判所等の未確定事項は断定せず`[CEO確認待ち]`と表示。`contact.html`の個人情報同意チェックボックスを`privacy.html`へリンク。[CURRENT_STATUS.md](CURRENT_STATUS.md)(v5.4→v5.5)に詳細を追加。SmartLabo repoはpush未実施 |

| **v6.1** | 2026-07-14 | Claude Code(CEO承認による) | **正式リリース前 最終整備 Step3(送信基盤設計確定)・Step4(バックエンド実装)完了。** Step3で送信基盤を`smartlabo-works`と独立したVercel Serverless Functionsに決定(CEO承認)。Step4で`WEBSITE/api/`配下に問い合わせフォームのバックエンド(CSRF検証・レート制限・reCAPTCHA v3・入力値検証・メール送信)を実装。ローカルの単体テスト・統合テストでCSRFトークン検証の脆弱性(改ざんトークン末尾のゴミが16進デコード時に切り捨てられ検証を通過する不具合)を発見し修正、全テスト成功を確認。Vercelアカウント作成・reCAPTCHA登録・通知先メール確定はCEOアクション待ちのためStep5(`contact.html`実接続)は未着手。[CURRENT_STATUS.md](CURRENT_STATUS.md)(v5.5→v5.6)に詳細を追加。SmartLabo repoはpush未実施 |

| **v6.2** | 2026-07-14 | Claude Code(CEO判断による) | **問い合わせフォーム送信基盤をVercel/Node.jsからXServer(PHP)へ変更。** CEOより「現在契約済みのXServerを優先利用する構成へ変更する」との判断があり、実装前にXServer構成案・SMTP構成・フォーム送信構成・セキュリティ構成を報告しCEO承認を得て実施。ドメイン構成を`smartlaboworks.com`(apex、GitHub Pages)／`form.smartlaboworks.com`(サブドメイン、XServer)に分離。新設`xserver-form/`(GitHub Pages非公開)にPHPバックエンドを実装し、SQLiteによるレート制限永続化・依存ライブラリなしの最小SMTPクライアント・CSRF検証の脆弱性対策(旧Node.js版で発見)を織り込んだ。ローカルにPHP 8.3を導入し22件のユニットテスト＋実HTTP統合テストを実施。旧Vercel/Node.js版(`WEBSITE/api/`等)は削除。[CURRENT_STATUS.md](CURRENT_STATUS.md)(v5.6→v5.7)に詳細を追加。SmartLabo repoはpush未実施 |

| **v6.3** | 2026-07-14 | Claude Code(CEO承認による) | **正式リリース前 最終整備 Step5完了(問い合わせフォーム実送信対応・メールアドレス構成の正式反映)。** CEOより`form.smartlaboworks.com`専用化・DNS構成・メールアドレス構成(`info@`=代表／`contact@smartlaboworks.com`=フォーム送信用推奨／`noreply@`=将来追加可能)・`config.php`秘密情報専用化の4点承認があり、実装前に構成図・DNSレコード一覧・SMTP送受信フロー・セキュリティ対策・`config.php.sample`構成を報告しCEO承認を得て実施。`WEBSITE/CNAME`新設、`config.php.sample`を秘密情報専用に再編しCORS許可オリジンは`public/lib/settings.php`(Git管理対象)へ分離。`contact.html`にhoneypot・`js/pages.js`に実際のフォーム送信処理(エラーコード別メッセージ表示)を実装。ローカルPHP環境で23件のユニットテスト＋実HTTP統合テストを実施。XServerサブドメイン追加・DNS設定・メールアカウント作成等はCEOアクション待ちのため本番動作確認は未実施。[CURRENT_STATUS.md](CURRENT_STATUS.md)(v5.7→v5.8)に詳細を追加。SmartLabo repoはpush未実施 |

| **v6.4** | 2026-07-14 | Claude Code(CEO指示による) | **正式リリース前 最終整備 Step6(正式公開前チェックリスト)、ライブ環境不要分を完了。** 全9ページのリンク・スマホ表示・404・OGP・favicon・consoleエラーを自動巡回で確認しすべて問題なし。`git log --all`で`private/config.php`が一度もコミットされていないこと・`.gitignore`が秘密情報ファイルを正しく除外することを実機確認。`xserver-form/README.md`にバックアップ方針・ロールバック手順を追記。残るチェック項目(フォーム送信・自動返信・HTTPS[XServer側]・PageSpeed)はCEOアクション(XServerサブドメイン追加等)完了後に実施。[CURRENT_STATUS.md](CURRENT_STATUS.md)(v5.8→v5.9)に詳細を追加。SmartLabo repoはpush未実施 |

| **v6.5** | 2026-07-14 | Claude Code(CEO指示による) | **公開前最終フェーズ Step5(問い合わせフォーム完成)完了。** CEOより会社設立・XServer契約・ドメイン設定・`info@smartlaboworks.com`作成・`form.smartlaboworks.com`サブドメイン作成(SSL反映待ち)というインフラ整備状況の共有と、問い合わせフォーム完成の実装指示があった。`config.php.example`命名へ復元し、実際に作成済みの`info@smartlaboworks.com`を当面の送信用メールとする構成へ更新(`contact@`/`noreply@`への将来切替構造は維持)。送信成功時にフォームが専用の完了画面へ切り替わる実装を追加。PHP8.0以上で動作する設計であることを確認。[CURRENT_STATUS.md](CURRENT_STATUS.md)(v5.9→v6.0)に詳細を追加。SmartLabo repoはpush未実施 |

| **v6.6** | 2026-07-14 | Claude Code(CEO指示による) | **正式リリース前 最終フェーズ Release Candidate 1完了。** CEO側インフラ整備完了(会社設立・XServer契約・ドメイン設定・`form.smartlaboworks.com`サブドメイン作成・無料SSL有効化・`info@smartlaboworks.com`作成)を受け、`config.php.example`を必要最低限9項目へ整理(SMTP暗号化方式`smtp_encryption`含む、送信元は認証アカウントをそのまま使用)。全9ページのcanonical/OGPを正式ドメイン`smartlaboworks.com`へ切替。新設`robots.txt`/`sitemap.xml`/構造化データ(Organization、未確定情報は含めない)。ローカルPHP環境で23件のユニットテスト＋新config構成でのHTTP統合テスト(tls/ssl)、ブラウザでSEOメタ情報・404・全リンク・モバイル・consoleエラーなしを確認。[CURRENT_STATUS.md](CURRENT_STATUS.md)(v6.0→v6.1)に詳細を追加。SmartLabo repoはpush未実施 |

| **v6.7** | 2026-07-14 | Claude Code(CEO指示による) | **新章[61_Release_Checklist.md](61_Release_Checklist.md)を新設。** 「Release Candidate 2として本番公開前チェックリストを作成してください」というCEO指示に基づき、SSL/問い合わせ/メール/リンク/Console Error/favicon/OGP/sitemap/robots/SEO/モバイル/PageSpeed/GitHub Release Tag/バージョン番号/更新履歴の15項目からなる再利用可能なチェックリストテンプレートを制定。Release Candidate 1時点での確認状況(ローカル確認済み✅／本番環境が必要⏸)を反映。公開バージョン番号として`v1.0.0`、Gitタグ名として`homepage-v1.0.0`を提案し、いずれも実行はCEOの明示的な承認後とすることを明記。`60`番台(編集体制・運用フロー)の直後に`61`として追加 |

| **v6.8** | 2026-07-14 | Claude Code(CEO指示による) | **[61_Release_Checklist.md](61_Release_Checklist.md)を「v1.0 Release Checklist」として全面拡充。** 「Release Candidate 2完了後、正式リリース前のv1.0 Release Checklistを作成してください」というCEO指示に基づき、CEO指定の18項目(ホームページ/問い合わせフォーム/SSL/メール送受信/スマホ表示/PC表示/SEO/OGP/favicon/robots.txt/sitemap.xml/404ページ/Google Search Console登録/Google Analytics登録/PageSpeed/GitHub Release/更新履歴/既知の制限事項)へ再編。新規追加：PC表示・404ページ・Google Search Console登録(CEOのGoogleアカウント作業が必要)・Google Analytics登録(現時点で未導入、導入する場合はprivacy.htmlの更新が先に必要)・既知の制限事項(10項目)。全項目チェック完了後に「v1.0 Release」として提出する運用を明記 |

| **v6.9** | 2026-07-14 | Claude Code(CEO指示による) | **「Release Candidate 2 最終仕上げフェーズ」Task1-8完了、新章62/63/64を新設。** SEO最終確認・公開後運用準備(解析コード非埋め込みのプレースホルダー整備)・問い合わせフォーム最終確認(SmtpMailer.phpへSMTPヘッダー・コマンドインジェクション対策を多層防御として追加)・公開前品質確認(Safari対応・陳腐化情報3件修正、コミット`32f3a28`)を実施。[61_Release_Checklist.md](61_Release_Checklist.md)のKnown Issuesを3階層へ再整理。新設[62_CEO_Publish_Guide.md](62_CEO_Publish_Guide.md)(スクリーンショット不要の8ステップ公開手順書)・[63_Post_Launch_Roadmap.md](63_Post_Launch_Roadmap.md)(CEO指定優先順位①〜⑧の公開後ロードマップ)・[64_Release_Notes_v1.0.0.md](64_Release_Notes_v1.0.0.md)(homepage-v1.0.0のRelease Notes)を`60`番台(編集体制・運用フロー)の直後に追加。デザイン変更・新機能追加は行わず、「Smart Labo Works Homepage v1.0 Release Candidate Final」としてCEO承認を待機する方針を[CURRENT_STATUS.md](CURRENT_STATUS.md)(v6.8→v6.9)に反映 |
| **v7.0** | 2026-07-15 | Claude Code(CEO指示による) | **銀行提出用事業計画書 Version 1.0を新規作成。** 「PROJECT_BIBLE/CURRENT_STATUS/CLAUDE.md/BUSINESS_STRATEGY/PRODUCT_REQUIREMENTS/Brand Guideline最新版を確認したうえで作成してください」というCEO指示に基づき、新設[DOCUMENT/FINANCE/](../DOCUMENT/FINANCE/README.md)へ`SmartLabo_BusinessPlan_v1.0.pptx`(PDF提出可能品質、CEO指定の13章構成)を格納。ブランドカラー・正式ロゴ・タイポグラフィをホームページ・PROJECT_BIBLEと統一。代表者名・設立年月・所在地・資本金・代表者略歴はPROJECT_BIBLE内に正式情報が一切存在しないため、推測・捏造せず「CEO確認待ち」表示のまま提出(銀行提出書類における虚偽記載リスクを避けるための対応)。売上計画(3年間)はCEO指示「現実的な数値を提案、無理な成長予測は避ける」に基づく試算、資金使途は絶対額を定めず配分比率のみを図解し、いずれも前提・仮説である旨を明記。詳細は[CURRENT_STATUS.md](CURRENT_STATUS.md)(v6.9→v7.0)を参照 |
| **v7.1** | 2026-07-15 | Claude Code(CEO追加指示による) | **銀行提出用事業計画書 Version 1.1へブラッシュアップ。** 「銀行担当者が納得できる、"なぜ達成できるのか"が分かる資料へ」というCEO追加指示に基づき、`SmartLabo_BusinessPlan_v1.1.pptx`を新設(13章→16章)。売上計画をCEO指定の新規導入目標(6/20/50社)へ再計算し「成長の根拠」「成長ストーリー」を追加。新設「持続的な成長を支える販売戦略」(10ステップの図解、銀行向け5ポイントバッジ)・「Smart Labo Partner Program」(制度目的「共に成長する。」、年間契約・初年度15%/更新10%・紹介料ではなく長期的パートナーシップとして整理)・「Smart Labo Philosophy」(CEO指定本文)の3章を新設。v1.0ファイルは比較・追跡用に同フォルダへ保持。詳細は[CURRENT_STATUS.md](CURRENT_STATUS.md)(v7.0→v7.1)を参照 |
| **v7.2** | 2026-07-15 | Claude Code(CEO追加指示による) | **経営計画書 Version 1.0(社内検討用・非公開)を新規作成。** 「銀行提出版とは別に、Partner Program・9プラン・継続課金・紹介ネットワークを考慮した実際の経営目標を作成し、費用を固定費・変動費(特にサーバー費・AI API費・Partner Program報酬を分離)へ分類してほしい」というCEO追加指示に基づき、`SmartLabo_ManagementPlan_v1.0.pptx`(全9章)を新設。9プラン価格グリッド・顧客ミックス想定ARPUから売上目標(550万円→2,900万円→7,200万円)を試算し、銀行提出版との比較を明示。固定費(人件費・基本管理費)と変動費(サーバー費・AI API費・Partner Program報酬・その他)を分離し、営業利益率を再計算(△45.5%→36.6%→46.3%、Year2で黒字化)。表紙に「CONFIDENTIAL — 社内検討用」と明記し銀行提出版との混同を防止。詳細は[CURRENT_STATUS.md](CURRENT_STATUS.md)(v7.1→v7.2)を参照 |
| **v7.3** | 2026-07-15 | Claude Code(CEO追加指示による) | **銀行提出用事業計画書 Version 1.2へ収支計画・利益計画をブラッシュアップ。** 「現実的で説得力のある利益計画へ修正し、銀行担当者が2年目黒字化を確信できる資料へ」というCEO追加指示に基づき、`SmartLabo_BusinessPlan_v1.2.pptx`(13章→19章)を新設。**6/20/50社を「累計導入社数」から「各年の新規契約数」へ再定義**(更新契約＋新規契約で累計を算出し直す重要な前提変更)、料金前提(月額15万円・初期導入費10万円)と現実的な契約時期を反映して売上高を再計算(510万円→3,050万円→9,900万円)。費用を固定費/変動費/開発費の3分類に再編し、サーバー費(XServer/バックアップ/監視、大幅下方修正)とAI API費(利用企業数比例)を分離、Partner Program報酬を顧客獲得コスト(CAC)として整理(広告経由CACとの比較を明示)。営業利益をYear2 487万円(利益率16.0%)で黒字化、Year3 4,632万円(46.8%)と算出。新設「利益が改善する理由」ページにCEO指定の3段階フロー図を実装。経営計画書Version1.0は旧解釈のまま未更新でv1.2との前提不整合が発生中、CEO確認待ち。詳細は[CURRENT_STATUS.md](CURRENT_STATUS.md)(v7.2→v7.3)を参照 |
| **v7.4** | 2026-07-15 | Claude Code(CEO追加指示による) | **銀行提出用事業計画書 Version 1.3へ収益構造・利益計画をブラッシュアップ。** 「『利益率が高い理由』ではなく『利益率が改善していく事業構造』を銀行・投資家・パートナーへ説明できる資料へ」というCEO追加指示に基づき、`SmartLabo_BusinessPlan_v1.3.pptx`(19章→21章)を新設。「受託開発ではなくSaaS・Platform Business」という収益モデルを明示的に再定義し、サーバー費(XServer・バックアップ・監視・ドメイン・メール)と営業活動費を固定費へ、AI API費のみを利用企業数比例の変動費へ再分類(v1.2から固定費/変動費の付け替え)。開発費を共通プラットフォーム開発・テンプレート整備・機能追加・保守改善への投資として再定義。営業利益はYear2 485万円(利益率15.9%、黒字化)→Year3 4,645万円(46.9%)。新設「Platform Business Model」(共通基盤7要素の図解)・「なぜ利益率が高まるのか」(固定費対売上高比率がYear1 156.9%→Year3 33.5%と急低下することを示し利益率改善の主因を数値裏付け、銀行・投資家双方への訴求点を併記)の2章を追加。「利益が改善する理由」を「利益率が改善する理由」へ改題しテンプレート活用ノードを追加。経営計画書Version1.0は旧解釈のまま未更新でv1.3との前提不整合が継続中、CEO確認待ち。詳細は[CURRENT_STATUS.md](CURRENT_STATUS.md)(v7.3→v7.4)を参照 |
| **v7.5** | 2026-07-15 | Claude Code(CEO追加指示による) | **銀行提出用事業計画書 Version 1.4へ収支計画を再修正(代表1名・自宅兼事務所の実態反映)。** 「固定費・開発費が実態より高い。代表1名・社員採用なし・自宅兼事務所・SaaS型・共通プラットフォーム・テンプレート再利用・XServer利用を前提に、実際の事業モデルに沿った収支へ」というCEO追加指示に基づき、`SmartLabo_BusinessPlan_v1.4.pptx`を新設(構成21章は変更なし、費用・利益計画を全面改訂)。費用を①固定費(運営費)②技術基盤費(サーバー・AI API)③開発費(保守・改善)④顧客獲得費(CAC)の4分類へ再編し、Year1固定費をCEO指定基準どおり50万円(人件費0円)、技術基盤費40万円、開発費80万円、顧客獲得費20万円に設定。費用合計をv1.3(1,414万円→2,565万円→5,255万円)から190万円→612万円→1,793万円へ大幅圧縮。**結果、営業利益はYear1から黒字化(320万円、利益率62.7%)→Year3 8,107万円(81.9%)という根本的に異なる姿になった。** 代表者報酬を含まない試算のため利益率が非常に高い点を「⚠️CEOへの重要な確認事項」として明記し、①このまま提出②最小限の代表者報酬を計上、の2択をCEOへ提示(本書は①の想定で作成)。各費用項目の算出根拠をCURRENT_STATUS.mdおよびスライド脚注に明記。経営計画書Version1.0は旧い費用構造のまま未更新で前提不整合がさらに拡大、CEO確認待ち。詳細は[CURRENT_STATUS.md](CURRENT_STATUS.md)(v7.4→v7.5)を参照 |
| **v7.6** | 2026-07-15 | Claude Code(CEO指示による) | **代表者報酬の扱いをCEO確定、銀行提出用事業計画書 Version 1.4を最終提出用ファイルとして確定。** v7.5で提示した「⚠️CEOへの重要な確認事項」(代表者報酬を含めず提出するか、最小限計上して調整するか)に対し、CEOより「1のままで資料出力して」との指示があり選択肢1で確定。スライド内容の変更はなく(既に該当注記を明記済みのため)、PROJECT_BIBLE上の確認事項ステータスを「判断待ち」から「確定」へ更新。実提出時は`_submitted`命名ルールを適用予定。詳細は[CURRENT_STATUS.md](CURRENT_STATUS.md)(v7.5→v7.6)を参照 |

*最終更新: 2026-07-15*
