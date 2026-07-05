# CURRENT_STATUS

**PROJECT_BIBLEの現在地 — ChatGPT / Claude Code / Codex 共通の同期ポイント**

> 新しいChatGPTの会話を始める前に、必ずこのファイルを確認してください。下記の「5行サマリー」をそのままChatGPTに貼り付ければ、認識のズレなく議論を始められます。このファイルはCEOの指示により、PROJECT_BIBLEおよびGitHubの管理責任者(Claude Code)が常に最新版を維持します。

---

## 5行サマリー(ChatGPTに共有する用)

```
Project Bible Version：3.0
Brand Version：5.0
Design Bible Version：2.2
Homepage Version：0.9 beta(β版として一般公開中)
Current Task：Dashboard UI設計
```

---

## ステータス一覧

| 項目 | 値 |
|---|---|
| Project Bible Version | 3.0 |
| Brand Version | 5.0 |
| Design Bible Version | 2.2 |
| Homepage Version | **0.9 beta**(CEO指示により「共有しながら改善するための公開」を目的にβ版として一般公開。正式カラーパレット・正式ロゴ・Hero実写真・アイコンバッジスタイルを反映済み。公開前チェック〈スマホ表示/リンク切れ/CTA動作/表示崩れ/画像読み込み〉はすべて確認済み) |
| Dashboard Version | 0.0(設計プロンプト・標準レイアウト確定・実装未着手) |
| Smart Labo Works Version | 0.0(未着手) |
| Current Project | Company Setup |
| Current Task | Dashboard UI設計 |
| Next Task | Dashboard UI実装 / Homepageのフォーム機能・法務ページ整備 / 正式ロゴを他制作物(Dashboard・営業資料・名刺・パンフレット)へ展開 / 真のベクター(SVG)ロゴデータの取得 / β版公開後のフィードバック反映 |
| Last Update | 2026-07-04 |
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

**Xserverへの実デプロイは、CEOよりFTP/SFTP接続情報(ホスト名・ユーザー名・パスワード・公開ディレクトリ)を確認中のため、まだ完了していません。** 接続情報を受け取り次第、公開URLを確定し本ファイルへ追記します。

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

*最終更新: 2026-07-04*
