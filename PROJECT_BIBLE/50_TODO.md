# 50. TODO — 全社レベルの未対応タスク一覧

このドキュメントは、特定のプロジェクト・フォルダに限定されない、会社全体レベルの未対応タスクを記録します。個別プロダクトのタスクは各フォルダ内で管理してください。

---

## 未対応タスク

- [ ] 会社の正式な設立日・登記情報を [00_Foundation/01_Company_Story.md](00_Foundation/01_Company_Story.md) に反映する
- [ ] 組織図の正式版を [40_Organization.md](40_Organization.md) に反映する
- [x] ~~ロゴの実データ(SVG/PNG、カラー版・白抜き版・アイコン版)を [DESIGN_ASSETS/01_LOGO/](../DESIGN_ASSETS/01_LOGO/README.md) に格納する~~(2026-07-04完了。CEOが正式ロゴを確定しAI_WORKSPACE経由で受領。Homepageヘッダー・フッター・favicon に実装済み)
- [ ] 真のベクター(SVG)ロゴデータを [DESIGN_ASSETS/01_LOGO/SVG/](../DESIGN_ASSETS/01_LOGO/README.md) に格納する(現状はPNG/WebPのみ。大判印刷等で必要な場合はデザイナーへの依頼を検討)
- [ ] アイコンセットの実SVGファイルを [DESIGN_ASSETS/Icons/](../DESIGN_ASSETS/Icons/README.md) に格納する(一覧は[DESIGN/system/design-tokens.md](../DESIGN/system/design-tokens.md)に定義済み)
- [x] ~~Homepage(`WEBSITE/`)のカラートークンを正式パレット(`#0A1B3D` / `#2563EB` 等)へ更新する~~(2026-07-03完了)
- [x] ~~Hero候補5点の完成イメージモックアップを [DESIGN_ASSETS/01_HERO/Mockups/](../DESIGN_ASSETS/01_HERO/Mockups/README.md) に格納する~~(2026-07-03完了。AI_WORKSPACE経由で受領)
- [x] ~~Hero背景**単体**の実データ(`.webp`、テキスト・ロゴが焼き込まれていないもの)5点を [DESIGN_ASSETS/01_HERO/Backgrounds/](../DESIGN_ASSETS/01_HERO/README.md) に格納する~~(2026-07-03完了。AI_WORKSPACE経由で受領)
- [x] ~~採用済みのHero背景(`hero_background_02_city_network.webp`)をHomepage([WEBSITE/index.html](../WEBSITE/index.html))に実装する~~(2026-07-03完了)
- [ ] アイコンの実SVGファイル化(現状は既存の線画SVGアイコンをバッジ風スタイルに変更する形で対応。専用アイコンセットを新規デザインする場合は別途検討)
- [x] ~~Homepageの別ロゴ案(角丸正方形+「+」マーク)を正式採用するかCEOに確認する~~(2026-07-04解決。CEOが別の六角形サーキットデザインのロゴを正式確定したため、この別ロゴ案は不採用として確定。[DESIGN_ASSETS/Icons/Reference/](../DESIGN_ASSETS/Icons/Reference/) には不採用の参考資料として残置)
- [ ] Dashboard・営業資料・名刺・パンフレット等、他の制作物にも正式ロゴ([DESIGN_ASSETS/01_LOGO/](../DESIGN_ASSETS/01_LOGO/README.md))を展開する(現時点ではHomepageのみ実装済み)
- [ ] Xserverのサーバープラン契約・DNS設定を行う(現状はドメイン`smartlaboworks.com`取得のみ)。契約完了後、正式ドメインへのデプロイに切り替える
- [x] ~~リポジトリのSettings→Pages→SourceでGitHub Actionsを選択する~~(2026-07-05完了。CEO対応。あわせてSettings→Actions→General→Workflow permissionsを「読み取りおよび書き込み権限」に変更し、デプロイ成功を確認)
- [x] ~~Homepage(β版)をGitHub Pagesで公開する~~(2026-07-05完了。公開URL: https://smartlabosukimagakusyuu-dev.github.io/SmartLabo/)
- [ ] Homepage 1.0公開後にCEO・関係者から寄せられるフィードバックを収集し、改善タスクとしてこのファイルに反映する
- [ ] 実際のお問い合わせ窓口(メールアドレス等)を確定し、CTAボタン(無料デモ/導入相談/資料ダウンロード)を接続する(2026-07-05時点、CEO確認により実在しない連絡先は捏造せず保留中)
- [ ] PROMPTS配下にClaude / Codex / ChatGPT向けの実プロンプトテンプレートを追加する
- [ ] [00_Foundation/02_Founder_Message.md](00_Foundation/02_Founder_Message.md) の本文(冒頭のCEO Message以外)を代表本人の言葉で確定させる
- [ ] [99_CEO_MEMORY/01_Founding_Story.md](99_CEO_MEMORY/01_Founding_Story.md)(創業経緯)・[03_Future_Vision.md](99_CEO_MEMORY/03_Future_Vision.md)(将来構想)・[05_Important_Conversations.md](99_CEO_MEMORY/05_Important_Conversations.md)(重要な会話)を代表が記入する(04_Brand_Philosophy.mdは記入済み)
- [ ] [00_Foundation/09_Product_History.md](00_Foundation/09_Product_History.md) にリリース済みプロダクト・機能を記録する

---

## 運用ルール

- タスクを追加する際は、担当者・期限が明確な場合は併記してください(例: `- [ ] ○○を実施する(担当: 山田、期限: 2026-08-01)`)。
- 完了したタスクはチェックを入れたまま残し、四半期ごとに `ARCHIVE/` へ移動して整理することを推奨します。
- 個別プロダクト・部門のタスクが増えてきた場合は、このファイルから分離し、該当フォルダ内に `TODO.md` を作成してください。

---

## 変更履歴

| バージョン | 日付 | 変更者 | 変更内容 |
|---|---|---|---|
| v1.0 | 2026-07-03 | Smart Labo | 初版作成 |
| v2.0 | 2026-07-03 | Smart Labo | PROJECT_BIBLE再構成に伴い旧12_TODO.mdから改番。00_Foundation・99_CEO_MEMORY関連の未記入項目を追加 |
| v2.1 | 2026-07-03 | Claude Code | 04_Brand_Philosophy.mdの記入完了を反映し、残タスクを更新 |
| v2.2 | 2026-07-03 | Claude Code(CEO提供のブランドキット参照資料による) | ロゴ・アイコンの実データ格納、Homepageのカラートークン更新タスクを追加 |
| v2.3 | 2026-07-03 | Claude Code(CEO指示による) | ロゴ・アイコンの格納先を [DESIGN_ASSETS/](../DESIGN_ASSETS/README.md) に更新 |
| v2.4 | 2026-07-03 | Claude Code(CEO指示による) | Homepageカラートークン更新タスクを完了として消し込み。Hero背景画像5点の格納・実装タスクを追加 |
| v2.5 | 2026-07-03 | Claude Code(CEO指示による) | Hero候補5点のモックアップ格納タスクを完了として消し込み。実装には背景単体データが別途必要であることを明記し、タスクを更新 |
| v2.6 | 2026-07-03 | Claude Code(CEO提供による) | Hero背景単体データの格納・Homepage実装タスクを完了として消し込み。アイコンSVG化・別ロゴ案の確認タスクを追加 |
| v2.7 | 2026-07-04 | Claude Code(CEO指示による) | **正式ロゴ確定を反映。** ロゴ格納タスクを完了として消し込み、格納先を[DESIGN_ASSETS/01_LOGO/](../DESIGN_ASSETS/01_LOGO/README.md)に更新。「別ロゴ案の確認」タスクを解決済みに変更(正式ロゴが別デザインに確定したため不採用)。真のSVGデータ取得・他制作物への展開タスクを追加 |
| v2.8 | 2026-07-04 | Claude Code(CEO指示による) | Homepageβ版公開に伴い、Xserverへの実デプロイタスク(FTP/SFTP接続情報待ち)とβ版フィードバック収集タスクを追加 |
| v2.9 | 2026-07-04 | Claude Code(CEO指示による) | Xserverがサーバー契約未完了と判明したため、デプロイタスクをXserver契約・DNS設定タスクに更新。暫定デプロイ先としてGitHub Pagesを採用し、CEOによる手動設定(Settings→Pages)タスクを追加 |
| v3.0 | 2026-07-05 | Claude Code(CEO対応による) | **Homepage(β版)のGitHub Pages公開が完了。** Pages設定・Workflow permissions設定タスクを完了として消し込み、公開URLを記録 |
| v3.1 | 2026-07-05 | Claude Code(CEO指示による) | Homepage 1.0公開に向けた最終ブラッシュアップに伴い、フィードバック収集タスクを「1.0公開後」に更新。実際のお問い合わせ窓口確定・CTA接続タスクを追加 |

*最終更新: 2026-07-05*
