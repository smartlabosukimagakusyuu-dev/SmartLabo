# DESIGN_ASSETS / Icons

**UIアイコンの格納場所**

---

## このフォルダの目的

Smart Labo Worksで使用する線画(アウトライン)スタイルのUIアイコンを格納します。アイコン一覧・スタイル方針は [DESIGN/system/design-tokens.md](../../DESIGN/system/design-tokens.md) を参照してください。

**現時点(2026-07-03)では実SVGファイルは未格納ですが、アイコンの「バッジスタイル」の方向性は下記の参考資料をもとにHomepage実装へ反映済みです。**

---

## 参考資料(2026-07-03受領)

CEOがAI_WORKSPACE経由(`AI_WORKSPACE/INBOX/ICONS/`)で、Homepageの別デザイン案を示す画像を提供した。実データは [Reference/homepage_icon_mockup_20260703.png](Reference/homepage_icon_mockup_20260703.png) に保管している(元データの履歴は [AI_WORKSPACE/COMPLETED/ICONS/](../../AI_WORKSPACE/COMPLETED/README.md) を参照)。

この画像には、以下の3つの異なる要素が含まれていた。

1. **アイコンのバッジスタイル**(採用): メール・スケジュール・顧客管理・契約書・経営分析・AI経営秘書の6アイコンが、角丸正方形の塗りバッジ+白抜きアイコンで表現されていた。この「バッジ」の視覚的な力強さを、[WEBSITE/css/style.css](../../WEBSITE/css/style.css) の `.value-card__icon` / `.timeline__icon` に反映した(2026-07-03)。
2. **アイコンの配色**(**不採用**): 参考資料では青・水色・紫など複数の色相が使われていたが、[00_Foundation/07_Brand_Identity.md](../../PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md) の「ブランドカラーはSmart Blueのみ」の原則に反するため採用しなかった。実装ではSmart Blue Family(`#2563EB`〜`#1D4ED8`のグラデーション)のみを使用している。
3. **ロゴ・全体レイアウト**(**不採用・要CEO確認**): 参考資料のロゴは、角丸正方形+「+」のアイコンマークであり、現行の公式ロゴ仕様([00_Foundation/07_Brand_Identity.md](../../PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md) 記載の「途切れた2画のストローク(S字)」)とは異なる。過去に「仕様と実装の食い違い」で指摘を受けた経緯があるため、CEOの明示的な承認なしにロゴを差し替えることはしていない。ロゴを刷新する場合は、先に[00_Foundation/07_Brand_Identity.md](../../PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md) を更新してから実装に反映する。

---

## 利用ルール

- 対象アイコン: AI分析・タスク管理・顧客管理・売上管理・レポート・通知・設定・セキュリティ(今後追加され得ます)。
- スタイルは線画(アウトライン)で統一し、塗りつぶしアイコンと混在させないでください。
- ファイル名の例: `icon-ai-analysis.svg`、`icon-task-management.svg`。
- 色はSmart Blue Family([PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md](../../PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md))の範囲内で使用してください。

---

## Version管理

- アイコンセット全体を刷新する場合は、`icons_v2/` のようにサブフォルダを分けることを推奨します。

---

## 更新ルール

- 新しいアイコンを追加する際は、既存アイコンとスタイル(線の太さ・角の丸み)を揃えてください。
- 追加したアイコンは [DESIGN/system/design-tokens.md](../../DESIGN/system/design-tokens.md) のアイコン一覧にも反映してください。

---

## Git運用ルール

- SVGはテキストとして差分管理できます。まとめて追加する場合もコミットメッセージに一覧を明記してください。

---

---

## 変更履歴

| バージョン | 日付 | 変更者 | 変更内容 |
|---|---|---|---|
| v1.0 | 2026-07-03 | Smart Labo | 初版作成 |
| v1.1 | 2026-07-03 | Claude Code(CEO提供による) | AI_WORKSPACE経由で受領したHomepageアイコン参考資料を [Reference/](Reference/) へ格納。バッジスタイルを採用、多色配色とロゴは不採用である旨を明記 |

*最終更新: 2026-07-03*
