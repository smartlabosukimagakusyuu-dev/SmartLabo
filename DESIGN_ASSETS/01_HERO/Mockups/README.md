# DESIGN_ASSETS/01_HERO/Mockups

**Hero候補5点の「完成イメージ参考資料」(実装用の背景単体画像ではない)**

---

## このフォルダの位置づけ

CEOよりAI_WORKSPACE経由で受け取った画像(`hero_candidates_composite_20260703.png`)を、[../Backgrounds/](../README.md) の5候補と対応する形で個別に切り出したものです(2026-07-03)。

**重要な注意:** これらの画像は、Smart Laboロゴ・見出し「会社を動かすAI。」・3つのCTAボタン・β版バッジが**焼き込まれた完成イメージのモックアップ**です。実際のHomepage実装では、これらの要素は本物のHTML/CSSで描画されるため、この画像をそのままCSSの背景画像として使うと、本物のテキストが焼き込み済みテキストの上に重なり、文字が二重に表示されてしまいます。

そのため、このフォルダの画像は**「目指すべき完成イメージの参考資料」**として扱い、**実装(`WEBSITE/`)からは直接参照しないでください。** 実装に使う背景単体画像(テキスト・ロゴ・ボタンが焼き込まれていないもの)は別途依頼・格納する必要があります(詳細は [../README.md](../README.md) および [50_TODO.md](../../../PROJECT_BIBLE/50_TODO.md) を参照)。

---

## ファイル一覧

| ファイル | 対応する背景候補 | ステータス |
|---|---|---|
| `hero_mockup_01_sunrise_city.png` | [hero_background_01_sunrise_city.md](../Backgrounds/hero_background_01_sunrise_city.md) | 採用中の世界観(モックアップのみ) |
| `hero_mockup_02_city_network.png` | [hero_background_02_city_network.md](../Backgrounds/hero_background_02_city_network.md) | 候補(アーカイブ) |
| `hero_mockup_03_cloud_city.png` | [hero_background_03_cloud_city.md](../Backgrounds/hero_background_03_cloud_city.md) | 候補(アーカイブ) |
| `hero_mockup_04_night_network.png` | [hero_background_04_night_network.md](../Backgrounds/hero_background_04_night_network.md) | 候補(アーカイブ) |
| `hero_mockup_05_waterfront_city.png` | [hero_background_05_waterfront_city.md](../Backgrounds/hero_background_05_waterfront_city.md) | 使用不可(六角形あり) |

---

## 由来

- 元データ: `hero_candidates_composite_20260703.png`(1〜5の候補を1枚にまとめた比較用画像。CEOがChatGPTから受け取り、[AI_WORKSPACE/INBOX/HERO/](../../../AI_WORKSPACE/INBOX/README.md) 経由で提供)
- 元データは履歴として [AI_WORKSPACE/COMPLETED/HERO/](../../../AI_WORKSPACE/COMPLETED/README.md) に保管
- Claude Codeが5分割で切り出し、このフォルダへ登録(プロンプト原文は受領していないため、メタデータの「プロンプト」欄には記録なしと明記している)

---

## 変更履歴

| バージョン | 日付 | 変更者 | 変更内容 |
|---|---|---|---|
| v1.0 | 2026-07-03 | Claude Code(CEO指示による) | 初版作成。完成イメージのモックアップ5点を参考資料として登録。実装には背景単体画像が別途必要である旨を明記 |

*最終更新: 2026-07-03*
