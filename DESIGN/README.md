# DESIGN

**デザインシステム・設計データ**

---

## このフォルダの目的

Smart Labo が手がけるプロダクト・サイトに共通して使われるデザインシステム(コンポーネント、カラー、タイポグラフィ、Figma等の設計データ)を保管します。デザインの思想的な原則は [PROJECT_BIBLE/20_UI_UX_Rules.md](../PROJECT_BIBLE/20_UI_UX_Rules.md) を参照してください。

---

## 想定する構成

```
DESIGN/
├── system/        ← カラー・タイポグラフィ・スペーシングなどのトークン定義
│   └── design-tokens.md  ← カラートークン・UIコンポーネントの型・アイコンセット一覧
├── components/    ← 再利用可能なUIコンポーネントの仕様
├── figma/          ← Figma等外部ツールへのリンク・エクスポート素材
└── assets/         ← アイコン・画像素材(実SVGファイルは未格納)
```

上記は推奨構成であり、プロダクトの成長に応じて調整してください。デザイントークンとUIコンポーネントの型は [system/design-tokens.md](system/design-tokens.md) に定義済みです。

---

## 利用ルール

- 新しいUIコンポーネントを作る前に、既存のデザインシステムに同等のものがないか確認してください。
- デザイントークン(色・余白・フォントサイズ)は、[PROJECT_BIBLE/20_UI_UX_Rules.md](../PROJECT_BIBLE/20_UI_UX_Rules.md) の原則(Premium・Enterprise・Trust・Innovation等、Enterprise AI Platform方針)に沿って定義してください。
- 外部デザインツール(Figma等)を使う場合は、リンクをこのフォルダ内のMarkdownに記録してください。

---

## Version管理

- デザイントークンの変更は、影響範囲(どのプロダクト・サイトに影響するか)をコミットメッセージに明記してください。

---

## 更新ルール

- デザインシステムを変更する際は、影響を受ける [SmartLaboWorks/](../SmartLaboWorks/README.md) や [WEBSITE/](../WEBSITE/README.md) 側の実装も合わせて更新してください。
- 使われなくなったコンポーネントは削除するか、[ARCHIVE/](../ARCHIVE/README.md) に移動してください。

---

## Git運用ルール

- デザインシステムの破壊的変更(既存コンポーネントの仕様変更)は、Pull Requestでレビューを経てからマージしてください。

---

## 変更履歴

| バージョン | 日付 | 変更者 | 変更内容 |
|---|---|---|---|
| v1.0 | 2026-07-03 | Smart Labo | 初版作成 |
| v1.1 | 2026-07-03 | Claude Code(CEO提供のブランドキット参照資料による) | [system/design-tokens.md](system/design-tokens.md) を新設。カラートークン、UIコンポーネントの型、アイコンセット、ダッシュボードUI構成要素を追加 |

*最終更新: 2026-07-03*
