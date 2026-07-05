# WEBSITE

**コーポレートサイト関連**

---

## このフォルダの目的

株式会社スマートラボのコーポレートサイトに関するソースコード・コンテンツ・運用資料を管理します。会社の「顔」となるサイトであるため、[PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md](../PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md) および [PROJECT_BIBLE/20_UI_UX_Rules.md](../PROJECT_BIBLE/20_UI_UX_Rules.md) に厳密に準拠します。

---

## 公開状況(2026-07-05時点)

**Homepage Version: 0.9 beta** — CEO指示により、正式完成としてではなく「共有しながら改善するための公開」を目的としたβ版として公開します。

**公開URL(暫定・GitHub Pages): https://smartlabosukimagakusyuu-dev.github.io/SmartLabo/**

Xserverはドメイン(`smartlaboworks.com`)取得のみでサーバー契約が未完了のため、契約完了までの暫定公開先としてGitHub Pagesを利用しています([.github/workflows/pages.yml](../.github/workflows/pages.yml) により `master` へのpushで自動デプロイ)。Xserverの契約・DNS設定が完了次第、正式ドメインへ切り替えます。

公開前チェック(実施済み):

- [x] スマホ表示(375px幅で全セクションを確認、崩れなし)
- [x] リンク切れ(内部アンカー4件〈#service/#industries/#mission/#cta〉すべて解決を確認)
- [x] CTAボタンの動作(スクロール遷移を確認)
- [x] 表示崩れ(デスクトップ1440px・モバイル375pxで全セクションを確認)
- [x] 画像読み込み(Hero背景・ロゴ・favicon、すべて200 OKを確認)
- [x] README/CURRENT_STATUSの更新

**β版として既知の未実装事項**(公開後も改善を続ける前提):

- フォーム送信の実処理(現状はCTAボタンのみで実際の送信機能なし)
- プライバシーポリシー・利用規約ページ(`LEGAL/` 未整備のためリンクはプレースホルダー`#`)
- 実写真素材(Hero以外は色ブロック+アイコンで代用)
- 真のベクター(SVG)ロゴデータ(現状はPNG/WebPのみ)

詳細は [PROJECT_BIBLE/CURRENT_STATUS.md](../PROJECT_BIBLE/CURRENT_STATUS.md) を参照してください。

---

## 利用ルール

- ページ追加・変更の際は、必ずブランドガイドライン(Enterprise AI Platform: Premium・Trust・Innovation・AI First)に沿っているか確認してください。特にHomepageは [PROMPTS/DESIGN/Homepage.md](../PROMPTS/DESIGN/Homepage.md) の指示に従ってください。
- 法務ページ(利用規約、プライバシーポリシー、特定商取引法に基づく表記など)を追加・変更する場合は、[LEGAL/](../LEGAL/README.md) の内容と整合性を取ってください。
- 新規ページを追加した場合は、フッター等のナビゲーションからの導線も確認してください。

---

## Version管理

- サイトの主要な変更(新ページ追加、デザインリニューアル等)は、コミット履歴からわかるようにメッセージを明確に記述してください。

---

## 更新ルール

- コンテンツの誤字脱字・軽微な修正は随時反映して構いません。
- デザインに影響する変更は、[DESIGN/](../DESIGN/README.md) のデザインシステムとの整合性を確認してから反映してください。

---

## Git運用ルール

- 本番公開に関わる変更は、可能な限りステージング環境やプレビューで確認してから `main` にマージしてください。
- 公開前のコンテンツ(未発表の新サービス情報等)を含む変更は、公開タイミングに注意してコミット・マージしてください。

---

## 変更履歴

| バージョン | 日付 | 変更者 | 変更内容 |
|---|---|---|---|
| v1.0 | 2026-07-03 | Claude Code | 初版作成 |
| v1.1 | 2026-07-04 | Claude Code(CEO指示による) | Homepage Version 0.9 betaとして公開。「公開状況」セクションを新設し、公開前チェック結果と既知の未実装事項を記録 |

*最終更新: 2026-07-04*
