# DESIGN_ASSETS / Icons

**UIアイコンの格納場所**

---

## このフォルダの目的

Smart Labo Worksで使用する線画(アウトライン)スタイルのUIアイコンを格納します。アイコン一覧・スタイル方針は [DESIGN/system/design-tokens.md](../../DESIGN/system/design-tokens.md) を参照してください。

**現時点(2026-07-03)では実SVGファイルは未格納です。**

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

*最終更新: 2026-07-03*
