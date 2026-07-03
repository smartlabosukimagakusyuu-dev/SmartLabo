# CHANGELOG — PROJECT_BIBLE 全体の変更履歴

`PROJECT_BIBLE` は株式会社スマートラボのOS・憲法であり、Single Source of Truth です。このファイルは、PROJECT_BIBLE 配下のドキュメント群に加えられた**重要な変更**を、日付順に一覧できるようにするためのマスター記録です。

- 各ファイル個別の細かい変更履歴は、各ファイル末尾の「変更履歴」テーブルを参照してください。
- ここには、複数ファイルにまたがる変更や、会社の意思決定として記録すべき重要な更新のみを記載します(誤字修正などの軽微な変更は対象外)。
- 新しいエントリは**先頭(最新)に追加**してください(Keep a Changelog 形式に準拠)。
- バージョニング方針は [README.md](README.md) の「Version管理」を参照してください。

---

## [Unreleased]

- (次回の変更予定があればここに記載)
- 正式ロゴデータ(SVG/PNG)到着後、Homepageの暫定「S」シンボルを差し替える
- アイコンの実データ(SVG)を [DESIGN_ASSETS/Icons/](../DESIGN_ASSETS/Icons/README.md) に格納する
- Hero背景画像5点の実データ(`.webp`)を [AI_WORKSPACE/INBOX/HERO/](../AI_WORKSPACE/INBOX/README.md) 経由で受け取り、[DESIGN_ASSETS/01_HERO/Backgrounds/](../DESIGN_ASSETS/01_HERO/README.md) に格納・Homepageに実装する(CEOよりファイルパス提供待ち)

---

## 2026-07-03 — v2.7: AI_WORKSPACE新設(ChatGPT発素材の正式な受け渡し経路)

CEOより、ChatGPTから提供される画像・資料・参考デザイン・プロンプトを、Claude Codeが安全かつ確実に受け取るための専用フォルダとして `AI_WORKSPACE/` を新設する指示を受けた。背景として、Hero背景候補5点の実データ格納作業において「チャット上の画像をファイル化する手段がない」という受け渡し経路の不備が繰り返し発生していたことがある。

### 新設した構成

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

① ChatGPTが素材を作成 → ② INBOXへ保存 → ③ Claude Codeが確認 → ④ PROCESSINGへ移動 → ⑤ 作業完了後DESIGN_ASSETSへ正式登録 → ⑥ COMPLETEDへ履歴保存。

**今後、ChatGPT発の素材を [DESIGN_ASSETS/](../DESIGN_ASSETS/README.md) へ直接保存することは禁止。** Claude Codeは新しい素材を利用する前に必ず `AI_WORKSPACE/INBOX` を確認し、作業完了後は「どの素材を使用したか」「正式保存した場所」「不要になった素材」を報告する。

### 更新したファイル

- 新規: `AI_WORKSPACE/README.md`、`INBOX/README.md`(8サブフォルダ分含む)、`PROCESSING/README.md`、`COMPLETED/README.md`、`ARCHIVE/README.md`
- [60_Editorial_Workflow.md](60_Editorial_Workflow.md)(v2.1→v2.2) — 「AI_WORKSPACE運用フロー」新設、作業完了報告フォーマットに項目追加
- [DESIGN_ASSETS/README.md](../DESIGN_ASSETS/README.md)(v2.0→v2.1) — AI_WORKSPACE経由ルールを運用ルール第一項目として追加
- [30_AI_Rules.md](30_AI_Rules.md)(v2.1→v2.2)
- [README.md](README.md)(ルート) — フォルダ構成にAI_WORKSPACEを追加
- [README.md](README.md)(PROJECT_BIBLE) — Version 2.6→2.7
- [CURRENT_STATUS.md](CURRENT_STATUS.md)(v1.6→v1.7)

**変更者:** Claude Code(Project Bible編集長)/ 指示: CEO

---

## 2026-07-03 — v2.6: Hero背景画像5点をDesign Assetsとして正式管理

CEOより、Homepage Hero背景の候補として提示された5点の画像を「単なる参考画像」ではなく**正式なブランド資産**として管理するよう指示を受けた。同じ世界観を将来再現できるよう、画像ファイルごとにプロンプト・用途・バージョンをセットで保存する運用を制定した。

### 新設した「番号付きユースケースフォルダ」体系

生成AI画像のように複数候補から選定するプロセスを伴う素材は、型別フォルダ(Logo/Icons等)とは別に、用途ごとに番号を振ったフォルダ(`01_HERO/` など)で管理し、画像ファイルと同名の `.md` メタデータをペアで保存する方式を [DESIGN_ASSETS/README.md](../DESIGN_ASSETS/README.md) に正式制定した。

### Hero背景候補5点の管理状況

[DESIGN_ASSETS/01_HERO/Backgrounds/](../DESIGN_ASSETS/01_HERO/README.md) に、各候補のメタデータ(`.md`)を作成した。

| ファイル | ステータス |
|---|---|
| `hero_background_01_sunrise_city.webp` | **採用中**(Homepage Hero、CEO選定) |
| `hero_background_02_city_network.webp` | 候補(アーカイブ) |
| `hero_background_03_cloud_city.webp` | 候補(アーカイブ) |
| `hero_background_04_night_network.webp` | 候補(アーカイブ) |
| `hero_background_05_waterfront_city.webp` | **使用不可**(禁止ビジュアル「六角形」に該当) |

### 未解決事項

チャット上で共有された画像をファイルとして保存・格納する手段が現状ないため、**5点とも実画像データ(`.webp`)はまだリポジトリに格納されていません。** メタデータのみ先行整備した状態です。CEOよりファイルパスの提供、または別の受け渡し方法の指示を受け次第、実データを格納しHomepageへ実装します。

### 更新したファイル

- 新規: [DESIGN_ASSETS/01_HERO/README.md](../DESIGN_ASSETS/01_HERO/README.md)、`Backgrounds/hero_background_01〜05_*.md`(5ファイル)
- [DESIGN_ASSETS/README.md](../DESIGN_ASSETS/README.md)(v1.0→v2.0) — 番号付きユースケースフォルダ体系とDesign Assets一覧を追加
- [50_TODO.md](50_TODO.md)(v2.3→v2.4) — Hero背景画像の格納・実装タスクを追加
- [CURRENT_STATUS.md](CURRENT_STATUS.md)(v1.5→v1.6)
- [README.md](README.md) — PROJECT_BIBLE Version 2.5→2.6

**変更者:** Claude Code(Project Bible編集長)/ 指示: CEO

---

## 2026-07-03 — Homepage v0.2: 正式カラー・ロゴへの反映漏れを修正

CEOより「ロゴもデザインも全く違う」との指摘を受けた。原因は、直前のブランドキット確定作業(PROJECT_BIBLE v2.4/v2.5)で仕様は更新したものの、**実装済みのHomepage(`WEBSITE/`)への反映が漏れていた**ことだった。CURRENT_STATUS.mdには「カラー更新待ち」と記録していたが、実際の色更新作業を後回しにしたまま次のタスクに進んでしまっていたため、指摘を受けて直ちに反映した。

### 修正内容

- **カラートークン:** `WEBSITE/css/style.css` の全カラー変数・ハードコードされたhexコードを、正式パレット(Primary Navy `#0A1B3D`、Smart Blue `#2563EB`、Light Blue `#60A5FA`)に置換。Hero・各セクションのグラデーション、アイコン、チェックマークもすべて対象。
- **ロゴ:** 暫定の角丸バッジ+抽象ストロークのマークを廃止し、[00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md) 記載の「途切れた2画のストローク(左上から右下へ流れる筆致)」仕様に沿った、より明確に「S」と読み取れるシンボルへ再デザイン。Navy背景(ヘッダー・フッター)のため白抜き版を使用。
- **注記:** 現在のSシンボルは、CEOから共有された参考画像をもとにClaude Codeが解釈して作成した**暫定デザイン**です。正式なロゴデータ(SVG/PNG)が用意され次第、[DESIGN_ASSETS/Logo/](../DESIGN_ASSETS/Logo/README.md) に格納し差し替えます。

### 教訓(60_Editorial_Workflowへの反映を検討)

PROJECT_BIBLE側の仕様更新と、実装(`WEBSITE/`等)への反映は別作業であり、片方だけ完了した状態を「完了」と報告しないよう注意する。CURRENT_STATUS.mdに「反映待ち」と記載した項目は、次の関連作業に着手する前に必ず解消する。

### 更新したファイル

- `WEBSITE/css/style.css`、`WEBSITE/index.html`
- [CURRENT_STATUS.md](CURRENT_STATUS.md)(v1.4→v1.5) — Homepage Versionを0.1→0.2に更新

**変更者:** Claude Code(Lead Software Engineer)/ 指摘: CEO

---

## 2026-07-03 — v2.5: Smart Blue Family確定とDESIGN_ASSETSの新設

CEOより、前回報告した2点の判断について確認・指示を受けた。

### ① Smart Blue Familyの確定

Claude Codeの解釈(Light Blue・Accent Blueを独立したブランドカラーではなくSmart Blue Familyの階調として扱う)がCEOにより確認・確定した。**ブランドカラーはSmart Blue(`#2563EB`)ただ一つ**であり、Light Blue・Accent Blueはその階調である、という位置づけをPROJECT_BIBLE・Design Bible全体で統一した。

### ② DESIGN_ASSETSフォルダの新設

現在のロゴ参考画像は仕様検討用の参考資料であり、正式なロゴデータ(SVG/PNG等)は今後別途用意されることが判明した。それまでの間、実データの置き場所を先行して整備するため、`DESIGN_ASSETS/` フォルダを新設した。

```
DESIGN_ASSETS/
├── Logo/ Background/ Icons/ Dashboard/
├── Buttons/ Cards/ Graphics/ Images/
└── Colors/ Fonts/
```

**役割分担の整理:**

| フォルダ | 役割 |
|---|---|
| PROJECT_BIBLE | 思想・原則("なぜ") |
| BRAND/、DESIGN/ | 仕様・トークンの文書化("何を") |
| **DESIGN_ASSETS/(新設)** | **実データそのもの("実物")** |

`BRAND/` と `DESIGN/` から、ロゴ・アイコン等の実ファイルに関する記述を [DESIGN_ASSETS/](../DESIGN_ASSETS/README.md) への参照に更新し、両フォルダは仕様・トークンの文書化に専念する役割へ整理した。

### 更新・新設したファイル

- 新規: `DESIGN_ASSETS/README.md`、および10サブフォルダそれぞれの `README.md`(Logo/Background/Icons/Dashboard/Buttons/Cards/Graphics/Images/Colors/Fonts)
- [00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md)(v4.3→v4.4) — Smart Blue Family確定を明記、実データ格納先をDESIGN_ASSETSへ変更
- [20_UI_UX_Rules.md](20_UI_UX_Rules.md)(v4.2→v4.3) — 「Design Assets運用ルール」セクションを新設
- [BRAND/README.md](../BRAND/README.md)(v1.1→v1.2)、[DESIGN/README.md](../DESIGN/README.md)(v1.1→v1.2) — 実データ格納先をDESIGN_ASSETSへ変更、役割を仕様・トークンの文書化に整理
- [DESIGN/system/design-tokens.md](../DESIGN/system/design-tokens.md)、[50_TODO.md](50_TODO.md)(v2.2→v2.3) — 格納先パスの参照を更新
- ルート [README.md](../README.md) — フォルダ構成図にDESIGN_ASSETSを追加

**変更者:** Claude Code(Project Bible編集長 / Lead Software Engineer 兼 Knowledge Manager)/ 指示: CEO

---
---

## 2026-07-03 — v2.4: ブランドキット(ロゴ・カラー・アイコン・UIコンポーネント)の正式反映

CEOより、ロゴ・背景・ダッシュボードUI・アイコン・UIコンポーネント・タイポグラフィ・カラーパレット・グラフィック要素を1枚にまとめたブランドキット参照資料が提供された。これを正式なデザインシステムとしてPROJECT_BIBLEへ反映した。

### 正式カラーパレット(8色)

| カラー | Hex |
|---|---|
| Primary Navy | `#0A1B3D` |
| Smart Blue | `#2563EB` |
| Light Blue | `#60A5FA` |
| Accent Blue | `#00D4FF` |
| White | `#FFFFFF` |
| Light Gray | `#F3F6FA` |
| Gray | `#64748B` |
| Dark Gray | `#1E293B` |

「アクセントカラーはSmart Blueのみ」という既存原則は、単一色ではなく **「Smart Blueファミリー(4階調)」** として精緻化した。あわせて、ステータスバッジ(完了=緑/進行中=青/未着手=グレー/遅延=赤)の意味色と、写真背景における自然な暖色(朝日・夕景)を、ブランドアクセントとは区別される例外として明記した。これにより、直前のHero背景修正で「UIはSmart Blueのみ、写真は暖色可」とした暫定判断が、正式にPROJECT_BIBLEへ組み込まれた形になる。

### ロゴ

カラー版・白抜き版・アイコン単体版の3バリエーションを定義。実データ(SVG/PNG)は未格納のため、[50_TODO.md](50_TODO.md) に追加。

### アイコンセット・UIコンポーネント

AI分析・タスク管理・顧客管理・売上管理・レポート・通知・設定・セキュリティの8アイコン、およびボタン(プライマリ/セカンダリ)・テキストリンク・入力フィールド(通常/フォーカス)・カード・ステータスバッジの型を定義。

### ダッシュボード標準レイアウト

サイドバーナビ(ダッシュボード/タスク管理/顧客管理/売上管理/AI分析/レポート/設定)+ 統計カード + データ可視化(折れ線グラフ+ドーナツチャート)+「AIからの提案」パネル + 「最近のアクティビティ」パネルという標準構成を [PROMPTS/DESIGN/Dashboard.md](../PROMPTS/DESIGN/Dashboard.md) に明記。現在のCurrent Task「Dashboard UI設計」に直接活用する。

### 更新・新設したファイル

- [00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md)(v4.2→v4.3) — ロゴ・カラーパレット・タイポグラフィ階層を追加
- [20_UI_UX_Rules.md](20_UI_UX_Rules.md)(v4.1→v4.2) — 配色・UIコンポーネントの型を更新
- [PROMPTS/DESIGN/SmartLabo_Design_Bible.md](../PROMPTS/DESIGN/SmartLabo_Design_Bible.md)(v2.1→v2.2) — カラー節を同期
- [PROMPTS/DESIGN/Dashboard.md](../PROMPTS/DESIGN/Dashboard.md)(v2.0→v2.1) — 標準レイアウトを追加
- 新規: `BRAND/colors/palette.md`、`DESIGN/system/design-tokens.md`
- [BRAND/README.md](../BRAND/README.md)、[DESIGN/README.md](../DESIGN/README.md) — 新規ファイルへの導線を追加

### 既知の残タスク

- Homepage(`WEBSITE/`)は暫定色(`#0A1A3C` 等)で実装済みのため、正式パレットへの色置換が必要(Next Taskに記録)
- ロゴ・アイコンの実データ(SVG/PNG)は未格納

**変更者:** Claude Code(Project Bible編集長 / Lead Software Engineer 兼 Knowledge Manager)/ 指示: CEO

---

## 2026-07-03 — Homepage Hero背景の視認性修正(WEBSITE/、Version 0.1継続)

CEOより「PROJECT_BIBLEとCURRENT_STATUS.mdを確認したうえで、Hero→CTA→機能バー→レスポンシブの優先順位でトップページ作成に入る」旨の指示を受けたが、CURRENT_STATUS.md確認の結果、Homepage v0.1として該当範囲は実装済みであることが判明。無駄な再実装を避けるため、既存実装をDesign Bible v2.1の基準でレビューし、発見した不具合を修正する方針とした(CEO承認済み)。

### レビューで発見した不具合と修正

- **Hero背景の視認性不足:** 都市シルエット・AIネットワークの背景SVGが、Heroの2カラムコンテンツ(テキスト・ダッシュボードモックアップ)とほぼ完全に重なっており、実質的に見えない状態だった。[PROMPTS/DESIGN/Homepage.md](../PROMPTS/DESIGN/Homepage.md)の「Heroの背景に未来都市+控えめなAIネットワークが表現されているか」というチェック項目を満たしていなかったため、以下を修正:
  - AIネットワークをヘッダー直下〜ダッシュボード上部の空きスペース(右上)に再配置し、コンテンツと重ならないようにした
  - 都市シルエットをHero下部の専用帯(210px)に固定し、Hero下部の余白を96px→152pxに拡大して視認できる領域を確保
  - 建物のシルエットカラーを明るめのNavy階調(#1B4488, #234E93等)に変更し、背景グラデーションとのコントラストを改善
- CTA・機能バー(主要機能セクション)・レスポンシブ(モバイル/タブレット/デスクトップ)は、既存実装がPROJECT_BIBLEの基準を満たしていることを確認(修正なし)

### 更新したファイル

- `WEBSITE/index.html`、`WEBSITE/css/style.css`
- [CURRENT_STATUS.md](CURRENT_STATUS.md)(v1.1→v1.2)

**変更者:** Claude Code(Lead Software Engineer)/ 指示: CEO

---

## 2026-07-03 — Homepage初回実装(WEBSITE/、Version 0.1)

CEOよりコーポレートサイト トップページの実制作指示を受領。添付の参考画像をレイアウトの雰囲気の参考としつつ、内容が [PROMPTS/DESIGN/Homepage.md](../PROMPTS/DESIGN/Homepage.md) と矛盾する箇所(CTA数、色数、朝日の表現色)はPROJECT_BIBLEを優先して実装した。

### 実装内容(`WEBSITE/index.html` / `css/style.css` / `js/main.js`)

- 8セクション構成: Hero → 社長の1日をAIが支える流れ → 主要機能 → 導入メリット → 対応業種 → Mission → CTA → Footer
- Hero: 左テキスト(ブランドコピー/サービスコピー/リード文)+ 右にSmart Labo Works管理画面のモックアップ、背景に控えめな都市シルエット+AIネットワーク、CTA3つ(無料デモを見る/導入相談をする/資料ダウンロード)
- 配色はNavy / White / Smart Blue / Light Grayのみを使用。「朝日」の表現は、参考画像のオレンジ系グローではなく、カラールール([00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md))を優先しSmart Blueの光として表現
- 主要機能セクションにサービスコピー「経営を、ひとつにつなぐ。」・説明コピー「社長の右腕になるAI経営プラットフォーム。」を使用し、機能→価値の変換パターンを適用
- 「対応業種」は実際の導入実績が存在しない現状(β開発中)を踏まえ、「ご利用いただいています」ではなく「ご活用を想定しています」という誠実な表現に調整([00_Foundation/05_Value.md](00_Foundation/05_Value.md)「誠実さ」原則に基づく)
- 「導入メリット」の統計は、裏付けのない具体的な数値(例: 稼働削減率)を掲載せず、24時間/365日という設計上の事実と、定性的な価値表現に留めた

### 既知の未実装事項

- フォーム送信の実処理(CTAは現状リンクのみ)
- プライバシーポリシー・利用規約の実ページ(`LEGAL/`が未整備のためリンクはプレースホルダー)
- 実写真素材(色ブロック+アイコンで代用中)

### 更新したファイル

- 新規: `WEBSITE/index.html`、`WEBSITE/css/style.css`、`WEBSITE/js/main.js`
- [CURRENT_STATUS.md](CURRENT_STATUS.md)(v1.0→v1.1) — Homepage Versionを0.0→0.1に更新、Current TaskをDashboard UI設計に更新

**変更者:** Claude Code(Lead Software Engineer)/ 指示: CEO

---

## 2026-07-03 — v2.3: CURRENT_STATUS.md新設とLead Software Engineer/Knowledge Manager体制の確立

CEOより、Claude CodeをPROJECT_BIBLEおよびGitHubリポジトリの管理責任者として位置づけ、常に最新版を維持する体制を確立するよう指示を受けた。ChatGPT・Claude Code・将来のAI(Codex等)が同じPROJECT_BIBLEを参照しながら開発を進めるための「同期の仕組み」を整備した。

### 新規追加

- **[CURRENT_STATUS.md](CURRENT_STATUS.md)** — PROJECT_BIBLE直下に新設。Project Bible Version・Brand Version・Design Bible Version・Homepage Version・Dashboard Version・Smart Labo Works Version・Current Project・Current Task・Next Task・Last Update・Maintainerの11項目を管理し、ChatGPTとの新セッション開始時に共有する「5行サマリー」を用意した。
  - Homepage/Dashboard/Smart Labo Worksの各Versionは、設計プロンプトのバージョンとは別軸の「実装進捗」であることを明記。2026-07-03時点でこれらは実装未着手のため、正直に `0.0` として記録した(架空の進捗は記載していない)。

### 役割・運用ルールの拡張

- [60_Editorial_Workflow.md](60_Editorial_Workflow.md)(v1.0→v2.0)
  - Claude Codeの役割を「Project Bible編集長」から「**Project Bible編集長 / Lead Software Engineer 兼 Knowledge Manager**」に拡張
  - **GitHub運用フロー(10ステップ)** を新設: git status確認 → 最新版取得 → PROJECT_BIBLE確認 → 作業 → 動作確認 → CURRENT_STATUS更新 → CHANGELOG更新 → コミット → push → 報告
  - **コミットメッセージ規約** を新設: `feat:` `docs:` `design:` `fix:` `refactor:` `style:` `chore:`
  - **作業完了時の報告フォーマット** を新設: 変更/追加/削除ファイル、PROJECT_BIBLE・CURRENT_STATUS・CHANGELOG更新の有無、現在のVersion一覧、コミットメッセージ、次にやるべき作業
  - **推測で判断しないエスカレーションルール** を新設: PROJECT_BIBLEに記載のない重要判断は、CEOへの確認またはChatGPTとの協議提案を必須とする
- [10_Development_Rules.md](10_Development_Rules.md)(v2.0→v2.1) — コミットメッセージ規約を反映
- [README.md](README.md)(v2.2→v2.3) — CURRENT_STATUS.mdへの導線、利用ルール7項目目(CURRENT_STATUS.mdを常に最新に保つ)、Git運用ルールの更新

**変更者:** Claude Code(Project Bible編集長 / Lead Software Engineer 兼 Knowledge Manager)/ 指示: CEO

---

## 2026-07-03 — v2.2: Enterprise AI Platform方針の再確認とブランドコピー階層の制定

CEOより改めて「Enterprise AI Platformというブランド思想へ変更する」という指示を受領した。内容を確認した結果、ポジショニング・参照8ブランド(Microsoft/OpenAI/AWS/Stripe/Palantir/Datadog/Snowflake/Vercel)・カラー・Hero構成・CTA(3つ)・UI思想など、大部分は**v2.0(2026-07-03)で既に確定・実装済み**の内容と一致していた。そのため今回は「新たな方針転換」ではなく、「既存方針の正式な再確認」として扱い、その中に含まれていた新規要素を追加で反映した。

### 新規に追加した内容

- **ブランドコピー階層の制定:** ブランドコピー「会社を動かすAI。」/ サービスコピー「経営を、ひとつにつなぐ。」/ 説明コピー「社長の右腕になるAI経営プラットフォーム。」の3階層を正式化
- **CEO判断記録:** 「AIツールを提供する会社」ではなく「会社全体をAIで進化させる企業」という方向性を、ホームページ・管理画面・営業資料・パンフレット・デモ画面・Smart Labo Worksすべての基準とすることを明記
- ブランドキーワードにGrowth(成長)・Future(未来)・AI Platform(経営)等の英語表記を追加
- ブランド適用範囲にパンフレット・デモ画面を追加
- UI原則に「余白を優先しつつEnterprise SaaSらしい力強さと両立させる」を追記

### 更新したファイル

- [00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md)(v4.1→v4.2) — ブランドコピー階層、キーワード拡張、適用範囲拡張
- [20_UI_UX_Rules.md](20_UI_UX_Rules.md)(v4.0→v4.1) — 余白と力強さの両立原則を追記
- [PROMPTS/DESIGN/SmartLabo_Design_Bible.md](../PROMPTS/DESIGN/SmartLabo_Design_Bible.md)(v2.0→v2.1) — ブランドコピー階層を追加
- [PROMPTS/DESIGN/Homepage.md](../PROMPTS/DESIGN/Homepage.md)(v2.0→v2.1) — 「主要機能」セクションでのサービスコピー/説明コピー使用指示を追加

**変更者:** Claude Code(Project Bible編集長)/ 指示: CEO

---

## 2026-07-03 — v2.1: CEO本人によるBrand Philosophyの記入と原則の正式制定

CEO本人が [99_CEO_MEMORY/04_Brand_Philosophy.md](99_CEO_MEMORY/04_Brand_Philosophy.md) に、v2.0で確定したEnterprise AI Platform方針への転換経緯と、ブランドの原則を自らの言葉で記入した。これはCEO_MEMORY配下のファイルとして初めて本人による実質的な記入が行われたケースであり、durable(長期的に判断基準となる)な原則については `00_Foundation` 側にも反映した。

### 記録された内容

- ブランド戦略転換の経緯(Quiet Intelligence → Enterprise AI Platform)を、CEO自身の思考プロセスとして記録
- **Missionは不変であることの明言:** 「AIで、社長が本来の仕事に集中できる世界をつくる。」は見せ方の変更を経ても変わらない
- **ブランドの原則(6項目):** 信頼最優先/高級感より品格/機能ではなく価値/経営者が安心できるデザイン/ホームページではなくブランドを作る/デザインだけでなく体験を設計する
- **CEO Message:** 「Smart LaboはAIを売る会社ではない。社長が本来の仕事に集中できる時間をつくる会社である。」

### 00_Foundation側への反映

- [00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md)(v4.0→v4.1) — 「ブランドの原則」6項目を正式制定。デザインキーワードのうちLuxury(高級感)より品格(Dignity)を優先する旨を明記。冒頭にCEO Messageを引用
- [00_Foundation/02_Founder_Message.md](00_Foundation/02_Founder_Message.md)(v1.0→v2.0) — 冒頭にCEO本人の実際の言葉(CEO Message)を反映。参照ブランドの記述をEnterprise AI Platform方針に合わせて更新。本文の残りは引き続き代表確認待ち
- [50_TODO.md](50_TODO.md) — 04_Brand_Philosophy.mdの記入完了を反映し、残タスク(01_Founding_Story / 03_Future_Vision / 05_Important_Conversations)を明確化

**変更者:** Claude Code(Project Bible編集長)/ 執筆: CEO本人

---

## 2026-07-03 — v2.0: ブランド方針の全面ピボット — Quiet Intelligence → Enterprise AI Platform

CEO(Chief Design Officer方針)より、v1.2で確定したばかりの「Quiet Intelligence」方針とは大きく異なる新しいブランドブリーフが示されました。Claude Code(Project Bible編集長)は、両方針の矛盾点(ポジショニング、参照ブランド、禁止/推奨ビジュアル、カラー、ボタンサイズ、CTA数、ページ構成)を洗い出してCEOに提示し、**「最新指示を優先し、Enterprise AI Platformへ全面ピボットする」**という明示的な承認を得て本改訂を実施しました。

### なぜ全面ピボットとして扱うか

矛盾が部分的ではなく、ブランドの人格そのもの(「静かに信頼を積み上げる」から「先進性とスケールを堂々と見せる」へ)に及んでいたため、部分的なマージではなく方針の置き換えとして扱っています。旧方針は以下の通り、履歴として保存されます。

### 旧方針(Quiet Intelligence, v1.2まで)の要点 [SUPERSEDED]

- ポジショニング: 「AI会社ではなく社長の右腕」
- 参照企業: Apple / Notion / Linear / Stripe / Porsche(5社)
- カラー: White / Black / Smart Blueのみ
- 禁止: 未来都市CG・AIネットワーク・六角形・過度な発光 等
- ボタン: 小さめ・控えめ
- CTA: 「デモを見る」「お問い合わせ」の2つのみ
- Homepage構成: Hero→共感→Smart Labo Works→導入メリット→ブランドストーリー→会社理念→お問い合わせ

### 新方針(Enterprise AI Platform, v2.0)の要点

- ポジショニング: 「AIを売る会社ではなく、会社を進化させる会社」
- 参照企業: Microsoft / OpenAI / AWS / Palantir / Stripe / Datadog / Snowflake / Vercel(8社、Stripeのみ共通)
- カラー: Navy / White / Smart Blue / Light Gray、グラデーションは上品に許容
- 禁止基準を「安っぽさ・過度さ」へ変更(未来都市・AIネットワーク自体は推奨ビジュアルへ)
- ボタン: 大きめ・美しい角丸・ホバーアニメーション・高級感
- CTA: 「無料デモを見る」「導入相談をする」「資料ダウンロード」の3つ、営業導線重視
- Homepage構成: Hero(左テキスト/右に管理画面表示、背景に未来都市+控えめなAIネットワーク)→社長の1日の業務をAIが支える流れ→主要機能→導入メリット→対応業種→Mission→CTA→Footer

### 更新したファイル

- [00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md) — ポジショニング・参照8社・カラー・禁止/推奨ビジュアルを全面改訂(v3.0→v4.0)
- [20_UI_UX_Rules.md](20_UI_UX_Rules.md) — デザイン原則・チェックリストを全面改訂(v3.0→v4.0)
- [PROMPTS/DESIGN/SmartLabo_Design_Bible.md](../PROMPTS/DESIGN/SmartLabo_Design_Bible.md) — マスタープロンプトを全面改訂、「機能→価値」変換パターンを追加(v1.0→v2.0)
- [PROMPTS/DESIGN/Homepage.md](../PROMPTS/DESIGN/Homepage.md) — ページ構成・Hero・CTAを全面改訂(v1.0→v2.0)
- [PROMPTS/DESIGN/Dashboard.md](../PROMPTS/DESIGN/Dashboard.md) — 配色・データ表現方針を更新(v1.0→v2.0)
- [PROMPTS/DESIGN/LandingPage.md](../PROMPTS/DESIGN/LandingPage.md) — カラー・ボタンスタイルを同期(v1.0→v2.0)
- [99_CEO_MEMORY/04_Brand_Philosophy.md](99_CEO_MEMORY/04_Brand_Philosophy.md) — 記入ガイドを新方針・方針転換の経緯記録用に更新
- ルート [README.md](../README.md)、[WEBSITE/README.md](../WEBSITE/README.md)、[BRAND/README.md](../BRAND/README.md)、[DESIGN/README.md](../DESIGN/README.md) の関連記述を同期

**変更者:** Claude Code(Project Bible編集長)/ 指示・最終承認: CEO

---

## 2026-07-03 — v1.2: ブランドデザイン「Quiet Intelligence」の制定

CEO(Chief Design Officer指示)より、Smart Laboのブランドデザイン方針が示され、PROJECT_BIBLEおよび新設の `PROMPTS/DESIGN/` に反映しました。「作るのはホームページではなくブランドである」という前提のもと、ブランドコンセプトからUI原則までを制定しています。

### ポジショニング・ブランドコンセプト

- **ポジショニング:** 「Smart LaboはAI会社ではない。社長の右腕になる会社である」
- **ブランドコンセプト:** **Quiet Intelligence(静かな知性)** — 派手ではない、説明しすぎない、余白を大切にする、長く愛されるデザイン
- 参照企業を Apple / Notion / Linear / Stripe に **Porsche** を追加した5社に拡張(コピーではなく思想の継承として扱う)

### PROJECT_BIBLE側の変更

- [00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md) にポジショニング・Quiet Intelligence・使用カラー(White/Black/Smart Blueのみ)・禁止ビジュアルの概要を追加
- [20_UI_UX_Rules.md](20_UI_UX_Rules.md) の原則を6→9項目に拡充(配色をSmart Blueのみに限定、ボタン/モーション/情報量より理解しやすさ/禁止ビジュアルを追加)、レビューチェックリストを更新
- [99_CEO_MEMORY/04_Brand_Philosophy.md](99_CEO_MEMORY/04_Brand_Philosophy.md) の記入ガイドをQuiet Intelligence・5社構成に更新

### 新規追加: PROMPTS/DESIGN/

- [PROMPTS/DESIGN/SmartLabo_Design_Bible.md](../PROMPTS/DESIGN/SmartLabo_Design_Bible.md) — ブランドの完全版デザイン指針。あらゆる制作物の起点となるマスタープロンプト(参照企業分析、デザインルール、カラー、禁止事項、推奨ビジュアル、UI思想を網羅)
- [PROMPTS/DESIGN/Homepage.md](../PROMPTS/DESIGN/Homepage.md) — コーポレートサイト トップページの構成(Hero→共感→Smart Labo Works→導入メリット→ブランドストーリー→会社理念→お問い合わせ)とHero文言を制定
- [PROMPTS/DESIGN/Dashboard.md](../PROMPTS/DESIGN/Dashboard.md) — Smart Labo Works プロダクト画面向けの設計指示
- [PROMPTS/DESIGN/LandingPage.md](../PROMPTS/DESIGN/LandingPage.md) — 個別施策向けランディングページの設計指示

### 禁止事項(全成果物共通)

AIロボット / サイバー演出 / ネオン / 未来都市CG / 青い地球 / 六角形 / 基板イラスト / 過度なエフェクト / 派手なアニメーション / 情報過多のUI

**変更者:** Claude Code(Project Bible編集長)/ 指示: CEO(Chief Design Officer方針として)

---

## 2026-07-03 — v1.1: 編集体制・更新フローの制定

PROJECT_BIBLEを継続的に正しく保つための「編集体制」を正式に制定しました。CEOより、ChatGPT・CEO・Claude Codeの三者による運用フローの指示を受け反映したものです。

- [60_Editorial_Workflow.md](60_Editorial_Workflow.md) を新設
  - 役割体制: ChatGPT(意思決定パートナー: 経営戦略・ブランド・UI/UX・営業・商品設計・会社方針)/ CEO(最終承認者)/ Claude Code(Project Bible編集長)
  - 6ステップ更新フロー: ①ChatGPTとの会話で方向性決定 → ②CEOが更新指示 → ③Claude CodeがPROJECT_BIBLEを更新 → ④Version更新 → ⑤CHANGELOG更新 → ⑥Gitへコミット
  - 「コードより先にBible」原則、Project Bible更新チェックリストを明記
- [README.md](README.md) に運用体制セクションを追加し、フォルダツリー・番号体系説明・更新ルール・変更履歴を更新
- [30_AI_Rules.md](30_AI_Rules.md) のツール別役割分担表・基本原則を更新し、60_Editorial_Workflow.mdへの参照を追加

**変更者:** Claude Code(Project Bible編集長)/ 承認: CEO

---

## 2026-07-03 — v1.0: 正式版 — 会社のOS・憲法として全面再設計

PROJECT_BIBLEを「ドキュメント集」から「会社のOS・憲法・知識ベース」として位置づけ直し、正式版 Version 1.0 として確定しました。

### 新しいフォルダ構成

```
PROJECT_BIBLE/
├── 00_Foundation/       会社の土台となる思想(公式見解)
├── 10_Development_Rules.md
├── 20_UI_UX_Rules.md
├── 30_AI_Rules.md
├── 40_Organization.md
├── 50_TODO.md
└── 99_CEO_MEMORY/        代表個人の記憶(生の記録)
```

### 旧ファイル → 新ファイルの対応表

| 旧パス(v0.x) | 新パス(v1.0) | 補足 |
|---|---|---|
| `01_Mission.md` | `00_Foundation/03_Mission.md` | Mission文を正式版に全面改訂 |
| `02_Vision.md` | `00_Foundation/04_Vision.md` | Vision文を正式版に全面改訂 |
| `03_Value.md` | `00_Foundation/05_Value.md` | 内容は継承 |
| `04_Brand.md` | `00_Foundation/07_Brand_Identity.md` | 会社名/ブランド名/キャッチコピーを追加 |
| `05_Product.md` | `00_Foundation/08_SmartLaboWorks_Concept.md` | 新Mission/Visionに合わせ改訂 |
| `06_Development_Rules.md` | `10_Development_Rules.md` | AIファースト等の原則を反映 |
| `07_UI_UX_Rules.md` | `20_UI_UX_Rules.md` | キーワードを拡張(信頼感・ミニマルを追加) |
| `08_AI_Rules.md` | `30_AI_Rules.md` | 内容は継承・一部拡充 |
| `09_Roadmap.md` | `00_Foundation/10_Future_Roadmap.md` | Product Historyとの役割分担を明記 |
| `10_Company_History.md` | `00_Foundation/01_Company_Story.md` | 物語形式の説明を追加 |
| `11_Organization.md` | `40_Organization.md` | 内容は継承 |
| `12_TODO.md` | `50_TODO.md` | 内容は継承・タスクを追加 |
| `13_CEO_NOTE.md` | `99_CEO_MEMORY/02_Key_Decisions.md` ほか4ファイルに分割 | 決定ログ / 創業経緯 / 将来構想 / ブランド思想 / 重要な会話 に再構成 |

### 新規追加ファイル

- [00_Foundation/02_Founder_Message.md](00_Foundation/02_Founder_Message.md) — 創業者からのメッセージ
- [00_Foundation/06_Philosophy.md](00_Foundation/06_Philosophy.md) — 会社を貫く8つの哲学(機能より体験・シンプル最優先・毎日使いたくなるUI・保守性・スケーラブル設計・AIファースト・コード品質・会社全体のAI最適化)
- [00_Foundation/09_Product_History.md](00_Foundation/09_Product_History.md) — プロダクトのリリース実績記録
- [99_CEO_MEMORY/01_Founding_Story.md](99_CEO_MEMORY/01_Founding_Story.md)
- [99_CEO_MEMORY/03_Future_Vision.md](99_CEO_MEMORY/03_Future_Vision.md)
- [99_CEO_MEMORY/04_Brand_Philosophy.md](99_CEO_MEMORY/04_Brand_Philosophy.md)
- [99_CEO_MEMORY/05_Important_Conversations.md](99_CEO_MEMORY/05_Important_Conversations.md)

### 公式ステートメントの正式制定

- **Mission:** 「AIで、社長が本来の仕事に集中できる世界をつくる。」
- **Vision:** 「日本で最も信頼される、中小企業向けAI経営プラットフォームになる。」
- **キャッチコピー:** 「会社を動かすAI。」

**変更者:** Claude Code(Chief System Architect)

---

## 2026-07-03 — v0.2 (draft): Single Source of Truth化の試作

- PROJECT_BIBLE を Single Source of Truth として位置づけ
- 全ファイルの末尾に「変更履歴」テーブルを追加
- CEOノート(旧13_CEO_NOTE.md)を試作

**変更者:** Claude Code

---

## 2026-07-03 — v0.1 (draft): PROJECT_BIBLE 試作版作成

- `PROJECT_BIBLE/` フォルダを新設し、01〜12の連番フラット構成で初版を作成
- あわせて `SmartLabo/` 直下のフォルダ構成一式(DOCUMENT / DESIGN / WEBSITE / SmartLaboWorks / PROMPTS / BRAND / LEGAL / MEETING / ARCHIVE)と各READMEを作成

**変更者:** Smart Labo / Claude Code

---

*このファイル自体の運用ルールは [README.md](README.md) の「Version管理」セクションを参照してください。*
