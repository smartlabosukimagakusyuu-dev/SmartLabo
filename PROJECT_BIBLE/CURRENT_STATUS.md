# CURRENT_STATUS

**PROJECT_BIBLEの現在地 — ChatGPT / Claude Code / Codex 共通の同期ポイント**

> 新しいChatGPTの会話を始める前に、必ずこのファイルを確認してください。下記の「5行サマリー」をそのままChatGPTに貼り付ければ、認識のズレなく議論を始められます。このファイルはCEOの指示により、PROJECT_BIBLEおよびGitHubの管理責任者(Claude Code)が常に最新版を維持します。

---

## 5行サマリー(ChatGPTに共有する用)

```
Project Bible Version：3.4
Brand Version：5.1
Design Bible Version：2.3
Homepage Version：1.0 Release Candidate(Company Brain拡張済み。GitHub Pagesで公開中: https://smartlabosukimagakusyuu-dev.github.io/SmartLabo/)
Dashboard Version：0.5(Company OS実装済み。GitHub Pages公開中: https://smartlabosukimagakusyuu-dev.github.io/SmartLabo/app.html)
Current Task：Dashboard v0.5完成・GitHub Pages公開済み
```

---

## ステータス一覧

| 項目 | 値 |
|---|---|
| Project Bible Version | 3.4 |
| Brand Version | 5.1 |
| Design Bible Version | 2.3 |
| Homepage Version | **1.0 Release Candidate — GitHub Pagesで公開中**([https://smartlabosukimagakusyuu-dev.github.io/SmartLabo/](https://smartlabosukimagakusyuu-dev.github.io/SmartLabo/))。Company Brainセクション新設、ダッシュボードのデモ表記、CTA導線の整理、404ページ・OGP・favicon最適化まで完了した最終ブラッシュアップ版。**さらにCompany BrainをSmart Labo Works最大の差別化要素として全面拡張済み**(キャッチコピー・9項目の知識ソース・3利用シナリオ・ガラスUI・専用アイコン) |
| Dashboard Version | **0.5(Company OS実装済み。app.html としてGitHub Pages公開中。Company Brain画面・AIモーニングブリーフィング・KPI拡張・アクティビティカラーコーディング・クイックアクション7項目・公式デザイントークン適用)** |
| Smart Labo Works Version | 0.5(Dashboard v0.5と同一) |
| Current Project | Company Setup |
| Current Task | Dashboard v0.5完成・GitHub Pages公開済み |
| Next Task | Dashboard v0.6(実データ接続・顧客管理強化) / Company Brain実データ連携 / Homepageのフォーム機能・法務ページ整備 / 正式ロゴ展開 / Xserverサーバー契約完了後に正式ドメインへ切り替え |
| Last Update | 2026-07-05 |
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

- 2026-07-03、`WEBSITE/` に Homepage の初回実装(`index.html` / `css/style.css` / `js/main.js`)が完了しました。8セクション構成(Hero/社長の1日/主要機能/導入メリット/対応業種/Mission/CTA/Footer)、3CTA、レスポンシブ対応、ホバー・スクロールアニメーションを実装済みです。
- 同日、配色を正式カラーパレット(`#0A1B3D` / `#2563EB` 等)へ更新しました。
- 2026-07-04、CEOが正式ロゴ(六角形フレーム+サーキットパターンの「S」字シンボル)を確定。ヘッダー・フッターの暫定ロゴマーク、およびfaviconを正式ロゴに差し替えました。
  - **未実装:** フォーム送信の実処理(現状はCTAのため実際の送信機能なし)、プライバシーポリシー・利用規約の実ページ(`LEGAL/`未整備のためリンクはプレースホルダー)、実写真素材(現状は色ブロック+アイコンで代用。Heroのみ実写真済み)、真のベクター(SVG)ロゴデータ(現状はPNG/WebPのみ)
- `SmartLaboWorks/`・`DESIGN/` 配下は `README.md` 以外の実装ファイルが存在しないため、引き続き **0.0(未着手)** です。
- 実装が進んだら、この3項目を `0.1`、`α0.1` のように更新してください。**達成していない進捗を先取りして記載しないこと。**

**Hero背景画像について(2026-07-03完了):** CEOがAI_WORKSPACE経由で、Hero候補5点の「完成イメージのモックアップ」(ロゴ・見出し・CTAが焼き込まれた参考画像、[DESIGN_ASSETS/01_HERO/Mockups/](../DESIGN_ASSETS/01_HERO/Mockups/README.md))に続き、**テキスト等が焼き込まれていない背景単体データ5点**を提供しました。5点すべてを [DESIGN_ASSETS/01_HERO/Backgrounds/](../DESIGN_ASSETS/01_HERO/README.md) に格納し、うち都市とAIネットワークが1枚に収まった `hero_background_02_city_network.webp` をHomepage Hero([WEBSITE/index.html](../WEBSITE/index.html))に実装しました。以前CEOが選定していた「朝焼け」の候補(`01_sunrise_city`)はAIネットワークが写っていなかったため、今回は実装候補から外し、他用途向けにアーカイブしています。旧世代のモックアップ版`05_waterfront_city`にあった禁止ビジュアル(六角形)は、新しい背景単体データには含まれていないことも確認済みです。

**Homepageアイコンについて(2026-07-03):** CEOがAI_WORKSPACE経由で、Homepageの別デザイン案(アイコンのバッジスタイル・別ロゴ案・多色配色を含むモックアップ)を提供しました。このうち**アイコンのバッジスタイル**(角丸正方形の塗りバッジ+白抜きアイコン)のみをSmart Blue Family単色で採用し、「主要機能」「社長の1日」セクションのアイコンに反映しました。**多色配色**(青・水色・紫)と**別ロゴ案**(角丸正方形+「+」マーク)は、ブランドルール([00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md))との不整合、および過去の「仕様と実装の食い違い」の教訓から、CEOの明示的な承認を得るまで採用していません。参考資料は [DESIGN_ASSETS/Icons/Reference/](../DESIGN_ASSETS/Icons/Reference/) に保管しています。

**AI_WORKSPACEについて:** ChatGPT発の画像・資料・プロンプトをClaude Codeが受け取るための専用フォルダとして [AI_WORKSPACE/](../AI_WORKSPACE/README.md)(INBOX/PROCESSING/COMPLETED/ARCHIVEの4段階構成)を新設しました。今回のHero背景・アイコン参考資料の受領・実装が、実際に機能した最初の完全なサイクルです(INBOX受領→PROCESSING加工→DESIGN_ASSETS正式登録→COMPLETED履歴保存→Homepage実装)。

**正式ロゴの確定について(2026-07-04):** CEOより「今回添付したロゴを株式会社スマートラボの正式ロゴとします。今後このロゴ以外は使用しません。」との明示的な決定があり、六角形フレーム+サーキットパターンの「S」字シンボルを唯一の正式ロゴとして確定しました。以前の「途切れた2画のストローク」の暫定仕様、および[DESIGN_ASSETS/Icons/Reference/](../DESIGN_ASSETS/Icons/Reference/)の別ロゴ案(角丸+「+」マーク)は、両方とも本ロゴに置き換えられ廃止されています。[DESIGN_ASSETS/01_LOGO/](../DESIGN_ASSETS/01_LOGO/README.md)にPrimary/White/Dark/Icon/Favicon/PNG/WebPの8フォルダ構成で格納し、[00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md)に「ロゴ使用ルール」(余白規定・最小サイズ・背景色・使用禁止例・用途別推奨サイズ)を正式制定。Homepageのヘッダー・フッター・faviconに実装済みです。**真のベクター(SVG)データは未提供**のため、大判印刷等の用途では別途デザイナーへの依頼を検討してください。

**Homepage β版公開について(2026-07-04):** CEOより「ホームページをβ版として公開してください。目的は正式完成ではなく、共有しながら改善するための公開です。」との指示があり、Homepage Versionを**0.9 beta**として扱うことになりました。公開前に以下を確認済みです。

- スマホ表示(375px幅で全セクション、崩れなし)
- リンク切れ(内部アンカー4件すべて解決を確認。フッターの法務ページリンク・CTAセクション内の3ボタンは既知のプレースホルダー`#`であり、リンク切れではなく未実装機能として扱う)
- CTAボタンの動作(スクロール遷移を確認)
- 表示崩れ(デスクトップ・モバイル双方で全セクションを確認)
- 画像読み込み(Hero背景・ロゴ・favicon、すべて200 OKを確認)
- README([WEBSITE/README.md](../WEBSITE/README.md))・本ファイルの更新

**Homepageは2026-07-05、GitHub Pagesで正式に公開されました。**

**公開URL: https://smartlabosukimagakusyuu-dev.github.io/SmartLabo/**

**デプロイ先の経緯:** Xserverはドメイン(`smartlaboworks.com`)取得のみでサーバー契約が未完了と判明(2026-07-04)。CEOより代替案としてGitHubでの運用可否を確認された。「共有しながら改善する」というβ版公開の目的に合致するため、**GitHub Pages**(GitHubが提供する無料の静的サイトホスティング)を暫定的な公開先として採用した。[.github/workflows/pages.yml](../.github/workflows/pages.yml) により `WEBSITE/` 配下を自動デプロイするGitHub Actionsワークフローを構築。

**トラブルシューティング:** 初回デプロイはリポジトリのSettings→Actions→General→「Workflow permissions」が「読み取り専用」になっていたため失敗(`Deploy to GitHub Pages`ステップでエラー)。CEOに「読み取りおよび書き込み権限」への変更を依頼し、再デプロイを実施したところ成功。CSS・JS・Hero写真・ロゴ・faviconすべて200 OKで読み込まれることを確認済み。

Xserverのサーバー契約・DNS設定が完了し次第、正式ドメイン(`smartlaboworks.com`)へ切り替える。

**Homepage Version 1.0 Release Candidateへの最終ブラッシュアップ(2026-07-05):** CEOより「Version 0.9βは十分な完成度に達している。新しいデザインを作るのではなく、Version 1.0公開に向けた最終ブラッシュアップを行ってほしい」との指示があり、以下を実施した。

1. **Company Brainセクションを新設**(Priority 1・最大の差別化要素): 「主要機能」の直後に配置。タイトル「Company Brain」、サブタイトル「会社の知識を、AIへ。」、社内規程・業務マニュアル等の具体例(チップ表示)、「探す→AIへ聞く」の体験を示すチャット形式のビジュアルモックアップで構成。ナビゲーションにも項目を追加
2. **ダッシュボードのデモ表記を追加**(Priority 2): Hero内のダッシュボードモックアップに「DEMO」バッジを追加し、直下に「※ 画面はイメージです。表示データはすべてサンプルであり、実績値ではありません。」の注記を追加。実績値との誤認を防止
3. **CTA導線を整理**(Priority 3): 「資料ダウンロード」全4箇所(ヘッダー/Hero/CTA/フッター)に「準備中」タグを追加し、無料デモ・導入相談と機能的に区別。CTAセクション内の壊れたリンク(`href="#"` によるページ先頭への意図しないジャンプ)を `#cta` に修正。実在しない連絡先(メールアドレス等)は捏造せず、CEOの判断により現時点ではセクション内スクロールのままとし、「正式なお問い合わせ窓口は現在準備中です」の注記を追加
4. **ブランド品質の再確認**(Priority 4): Company Brain追加でナビゲーション項目が5つに増えたことに伴うヘッダーの折り返し崩れ(1024px前後)を発見・修正(ハンバーガーメニューへの切り替え幅を860px→1100pxに拡大)。その他、余白・タイポグラフィ・カラー・レスポンシブを再確認
5. **公開品質を確認**(Priority 5): ブランドに沿ったカスタム404ページを新設。OGP(og:title/description/image/url等)・Twitter Card・canonicalタグを追加。faviconを512×512(120KB)から64×64(3.5KB)に最適化し表示速度を改善。全リンク・全画像の読み込みを再確認(問題なし)

**Company BrainをSmart Labo Works最大の差別化要素として正式に formalize(2026-07-05):** CEOより「Company Brainは単なるマニュアル検索ではなく、Smart Labo Worksが提供する『会社の頭脳』である」との明確な指示があり、以下を実施した。

1. **PROJECT_BIBLEへの正式追加:** [00_Foundation/08_SmartLaboWorks_Concept.md](00_Foundation/08_SmartLaboWorks_Concept.md) にCompany Brainを中核基盤として追加し、一般的なマニュアル検索・チャットボットとの違いを比較表で明記。[00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md) にCompany Brain専用の機能コピー「探す時間を、考える時間へ。」を制定し、ブランド適用範囲に統一ブランド化の方針を追記
2. **マスター設計プロンプトの新設:** [PROMPTS/DESIGN/CompanyBrain.md](../PROMPTS/DESIGN/CompanyBrain.md) を新設。コピー・3つの利用シナリオ(契約更新/資格手当/有給申請)・UI方針(ガラスUI・Smart Blue)・アイコン方針(Brain/Network/Knowledge/AI)・Company Brain専用画面の構成(検索バー/AIチャット/最近閲覧した資料/人気の社内マニュアル/お気に入り)を集約し、ホームページ・Dashboard・営業資料・パンフレット・デモ画面すべてで参照する正本とした
3. **Homepage.md/Dashboard.mdの更新:** [PROMPTS/DESIGN/Homepage.md](../PROMPTS/DESIGN/Homepage.md) のCompany Brainセクション仕様をCompanyBrain.mdへの参照込みで更新。[PROMPTS/DESIGN/Dashboard.md](../PROMPTS/DESIGN/Dashboard.md) のサイドバーナビ標準レイアウトに「Company Brain」を追加(専用アイコン付き、他項目に埋没させない扱い)
4. **Roadmapの更新:** [00_Foundation/10_Future_Roadmap.md](00_Foundation/10_Future_Roadmap.md) に「Company Brain 拡張ロードマップ」を新設し、将来追加予定機能(動画検索/PDF検索/Word・Excel・PowerPoint検索/画像OCR/社内Wiki/議事録検索/音声検索/AI要約/多言語対応)を記録
5. **Homepage実装:** [WEBSITE/index.html](../WEBSITE/index.html) / [WEBSITE/css/style.css](../WEBSITE/css/style.css) のCompany Brainセクションを全面拡張。専用のBrain/Networkアイコンバッジ、キャッチコピー「探す時間を、考える時間へ。」、9項目に拡充した知識ソースのチップ表示、AIの回答に「関連資料」「次のアクション」チップを追加(AIが答えるだけでなく案内する存在であることを視覚化)、3つの利用シナリオを複数ステップの案内フローとして見せる新セクション、ガラスUI(glassmorphism: backdrop-filter blur + 半透明パネル)を採用。デスクトップ(1440px/1024px)・モバイル(375px)でプレビュー確認済み、レイアウト崩れ・コンソールエラーなし
6. **Dashboard・営業資料・パンフレット・デモ画面への展開:** この時点(v2.6作成時)ではDashboardはまだ実体が存在しなかった(Dashboard Version 0.0)ため、仕様レベル(PROMPTS/DESIGN配下)への反映にとどめていた。その後、別セッションでDashboard v0.5が実装され、Company Brain専用画面も実装済みとなった(下記v2.8参照)。営業資料・パンフレットへの展開は引き続き未着手

**新章「Development Principles」の新設(2026-07-05):** CEOより、今後のSmart Labo Works開発における基本原則を定めた新章の追加指示があり、[11_Development_Principles.md](11_Development_Principles.md) を新設した。`00_Foundation` の01〜10がすべて埋まっているため、開発関連ドキュメント(`10`〜`19`)の枠に `11` として挿入した(既存ファイルの番号は変更していない)。

主な内容:

- **開発前ルール(必須):** 新しい画面・機能・デモ・UIを作成する前に、PROJECT_BIBLE / Brand Bible([00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md)) / Design Bible([PROMPTS/DESIGN/SmartLabo_Design_Bible.md](../PROMPTS/DESIGN/SmartLabo_Design_Bible.md)) / CURRENT_STATUS / CHANGELOGの最新版を必ず確認すること
- **Smart Labo Design Principle:** 「社長の時間を増やせるか」「Company OSの思想に沿っているか」等、5項目のYES/NOチェックリスト。YESにならない機能は実装しない
- **デモ画面作成ルール:** デモ画面は単なるUIサンプルではなく、営業で見せる「製品デモ」として設計する
- **Company Brain優先:** 今後作成する画面ではCompany Brainとの連携を常に検討する
- **Dashboard設計原則:** DashboardはCRMではなく**Company OS**であり、社長が毎朝最初に開く画面として設計する
- **最重要原則:** Smart Labo Worksは「AIチャット」ではなく「会社を進化させるCompany OS」である

**「Company OS」という新しい用語について:** 本章で初めて登場した表現であり、既存の「会社を動かすAI。」(ブランドコピー)や「AI経営プラットフォーム」と矛盾するものではなく、Dashboard・Smart Labo Worksの性格を一言で表す補完的な位置づけとして扱う。[00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md) の正式なコピー階層(ブランドコピー/サービスコピー/説明コピー)自体は今回変更していない。CEOより将来的に正式なブランドコピー階層への統合指示があれば、その時点でBrand Identityを更新する。

**「CEOレビュー原則」の追加(2026-07-05):** CEOより、大きな仕様変更・ブランド変更・UI変更は実装前にレビューを受けてから開発を進めること、独自判断でブランドコンセプト・UI方針を変更しないこと、という指示があり、[11_Development_Principles.md](11_Development_Principles.md)(v1.0→v1.1)に「CEOレビュー原則」を新設した。既存の[60_Editorial_Workflow.md](60_Editorial_Workflow.md)「推測で判断しない(エスカレーションルール)」を強化する位置づけであり、両ファイルに相互参照を追加した。Smart Laboが長期的なブランド構築を重視する姿勢を明文化したもの。

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
| v1.1 | 2026-07-03 | Claude Code(CEO指示による) | Homepage初回実装完了に伴いHomepage Versionを0.0→0.1に更新。Current TaskをDashboard UI設計に更新 |
| v1.2 | 2026-07-03 | Claude Code(CEO指示による) | Hero/CTA/機能バー/レスポンシブをレビューし、Hero背景(都市シルエット・AIネットワーク)がコンテンツに隠れて視認できない不具合を修正 |
| v1.3 | 2026-07-03 | Claude Code(CEO提供のブランドキット参照資料による) | Brand Version 4.2→4.3、Design Bible Version 2.1→2.2に更新。正式カラーパレット確定に伴い、Homepage実装が旧配色のままである旨をNext Taskに追記 |
| v1.4 | 2026-07-03 | Claude Code(CEO指示による) | Project Bible Version 2.4→2.5、Brand Version 4.3→4.4に更新。[DESIGN_ASSETS/](../DESIGN_ASSETS/README.md) 新設(デザイン資産の実データ管理場所)とSmart Blue Family確定を反映 |
| v1.5 | 2026-07-03 | Claude Code(CEO指摘による) | 「ロゴもデザインも全く違う」との指摘を受け、Homepage Versionを0.1→0.2に更新。正式カラーパレットへの置換とロゴ再デザインが完了した旨を反映 |
| v1.6 | 2026-07-03 | Claude Code(CEO指示による) | [DESIGN_ASSETS/01_HERO/Backgrounds/](../DESIGN_ASSETS/01_HERO/README.md) 新設を反映。Hero背景候補5点のメタデータ整備状況とファイル提供待ちである旨をNext Task・注記に追加 |
| v1.7 | 2026-07-03 | Claude Code(CEO指示による) | Project Bible Version 2.6→2.7に更新。[AI_WORKSPACE/](../AI_WORKSPACE/README.md) 新設(ChatGPT発素材の正式な受け渡し経路)を反映し、Next TaskのHero背景画像受け取り口を`AI_WORKSPACE/INBOX/HERO/`に更新 |
| v1.8 | 2026-07-03 | Claude Code(CEO提供による) | Project Bible Version 2.7→2.8に更新。AI_WORKSPACE経由でHero候補5点のモックアップを受領・[DESIGN_ASSETS/01_HERO/Mockups/](../DESIGN_ASSETS/01_HERO/Mockups/README.md) へ格納したことを反映。実装用の背景単体データが引き続き必要である旨をNext Taskに明記 |
| v1.9 | 2026-07-03 | Claude Code(CEO指示による) | Homepage Versionを0.2→0.3に更新。Hero背景をCSS/SVGのみで強化(フルワイド都市シルエット・AIネットワークのグロー・ドットグリッド)した旨を反映。新規バイナリ資産の追加なし |
| v2.0 | 2026-07-03 | Claude Code(CEO提供による) | Project Bible Version 2.8→2.9、Homepage Version 0.3→0.4に更新。AI_WORKSPACE経由で受領したHero背景単体データ5点をDESIGN_ASSETSへ格納しHomepageへ実装。アイコンのバッジスタイルをHomepageへ反映。多色配色・別ロゴ案は不採用である旨と、CEOへの確認事項をNext Taskに明記 |
| v2.1 | 2026-07-04 | Claude Code(CEO指示による) | **正式ロゴ確定を反映。** Project Bible Version 2.9→3.0、Brand Version 4.4→5.0、Homepage Version 0.4→0.5に更新。[DESIGN_ASSETS/01_LOGO/](../DESIGN_ASSETS/01_LOGO/README.md) 新設とHomepage実装完了、以前の暫定ロゴ仕様・別ロゴ案の廃止を反映 |
| v2.2 | 2026-07-04 | Claude Code(CEO指示による) | **Homepageをβ版として公開。** Homepage Versionを0.5→0.9 betaに更新。公開前チェック(スマホ表示/リンク切れ/CTA動作/表示崩れ/画像読み込み)の実施結果を記録。Xserverへの実デプロイはFTP/SFTP接続情報確認中のため保留であることを明記 |
| v2.3 | 2026-07-04 | Claude Code(CEO指示による) | Xserverがサーバー契約未完了(ドメインのみ取得)と判明したことを受け、暫定デプロイ先として**GitHub Pages**を採用。[.github/workflows/pages.yml](../.github/workflows/pages.yml) を新設。CEOによるリポジトリSettings→Pages設定の手動対応が必要である旨を明記 |
| v2.4 | 2026-07-05 | Claude Code(CEO対応による) | **Homepageの公開完了を反映。** 公開URL(`https://smartlabosukimagakusyuu-dev.github.io/SmartLabo/`)を記録。Workflow permissions設定の不備によるデプロイ失敗とその解決経緯を記録。Last Updateを2026-07-05に更新 |
| v2.5 | 2026-07-05 | Claude Code(CEO指示による) | **Homepage Versionを0.9 beta→1.0 Release Candidateに更新。** Project Bible Version 3.0→3.1に更新。Company Brainセクション新設・ダッシュボードのデモ表記・CTA導線整理・ヘッダー折り返し崩れの修正・404ページ/OGP/favicon最適化を反映。Next Taskに「実際のお問い合わせ窓口確定後のCTA接続」を追加 |
| **v2.6** | 2026-07-05 | Claude Code(CEO指示による) | **Company BrainをSmart Labo Works最大の差別化要素として正式にformalize。** Project Bible Version 3.1→3.2、Brand Version 5.0→5.1、Design Bible Version 2.2→2.3に更新。新設の[PROMPTS/DESIGN/CompanyBrain.md](../PROMPTS/DESIGN/CompanyBrain.md)をマスター設計プロンプトとして、コピー・3利用シナリオ・UI方針・アイコン方針・専用画面構成を集約。Homepage実装を全面拡張(キャッチコピー・9項目知識ソース・次アクション案内・3シナリオ・ガラスUI)。Dashboard.mdサイドバーへの追加、Roadmapへの拡張機能追加を反映 |
| **v2.7** | 2026-07-05 | Claude Code(CEO指示による) | **新章[11_Development_Principles.md](11_Development_Principles.md)を新設。** Project Bible Version 3.2→3.3に更新。開発前ルール(5点確認必須)、Smart Labo Design Principle(5項目チェックリスト)、デモ画面作成ルール、Company Brain優先方針、Dashboard設計原則(Company OS)、最重要原則を制定。今後のDashboard UI実装・営業資料・パンフレット等、すべての新規開発はこの章を判断基準とすること |
| **v2.8** | 2026-07-05 | Claude Code(別セッションでのCEO作業をマージ) | **Dashboard Version 0.0→0.5を反映。** 別セッションで`WEBSITE/app.html`が実装され、GitHub Pagesで公開された(Company Brain専用画面・AIモーニングブリーフィング・KPI拡張・アクティビティカラーコーディング・クイックアクション7項目・公式デザイントークン適用)。Smart Labo Works Versionも0.0→0.5に更新。詳細は[CHANGELOG.md](CHANGELOG.md)「Dashboard v0.5」エントリを参照。マージ時点の確認により、`app.html`のログイン画面ロゴが正式ロゴ([00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md)の六角形+サーキット「S」)ではなく暫定的な六角形+チェックマークのSVGになっていることを検出。ブランド統一の観点で今後の確認・修正が必要(50_TODO.mdに追記) |
| v2.9 | 2026-07-05 | Claude Code(CEO指示による) | **「CEOレビュー原則」を追加。** Project Bible Version 3.3→3.4に更新。[11_Development_Principles.md](11_Development_Principles.md)(v1.0→v1.1)に、大きな仕様変更・ブランド変更・UI変更は実装前にレビューを受けること、独自判断でブランドコンセプト・UI方針を変更しないことを明文化。[60_Editorial_Workflow.md](60_Editorial_Workflow.md)(v2.2→v2.3)と相互参照を追加 |

*最終更新: 2026-07-05*
