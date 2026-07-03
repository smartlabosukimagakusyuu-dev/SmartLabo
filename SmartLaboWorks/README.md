# SmartLaboWorks

**主力プロダクト「Smart Labo Works」の開発**

---

## このフォルダの目的

株式会社スマートラボの主力プロダクトである Smart Labo Works の開発一式(ソースコード、技術ドキュメント、モジュールごとの仕様)を管理します。プロダクトの思想的な背景は [PROJECT_BIBLE/05_Product.md](../PROJECT_BIBLE/05_Product.md) を必ず参照してください。

---

## プロダクトコンセプト

> **「会社を動かすAI」**

Smart Labo Works は、企業の日々の業務判断・作業をAIが担う「AI社員」として機能するプロダクト群です。詳細思想は [PROJECT_BIBLE/05_Product.md](../PROJECT_BIBLE/05_Product.md) を参照してください。

---

## 想定するフォルダ構成

```
SmartLaboWorks/
├── modules/        ← 業務領域ごとのAIモジュール(例: 不動産査定、契約書作成、コンシェルジュ 等)
├── core/           ← モジュール横断で使う共通基盤・API
├── docs/           ← 技術仕様書・アーキテクチャドキュメント
└── prompts/        ← プロダクト固有のAIプロンプト(汎用プロンプトは PROMPTS/ を参照)
```

プロダクトの成長に応じて、実際のディレクトリ構成はこのフォルダ内のドキュメントで随時更新してください。

---

## 利用ルール

- 新しいモジュールを追加する際は、[PROJECT_BIBLE/05_Product.md](../PROJECT_BIBLE/05_Product.md) の「プロダクト開発時の判断基準」に照らして要否を検討してください。
- コーディング規約は [PROJECT_BIBLE/06_Development_Rules.md](../PROJECT_BIBLE/06_Development_Rules.md) に従ってください。
- UIを実装する際は [PROJECT_BIBLE/07_UI_UX_Rules.md](../PROJECT_BIBLE/07_UI_UX_Rules.md) および [DESIGN/](../DESIGN/README.md) のデザインシステムに準拠してください。

---

## Version管理

- モジュール単位でバージョンを管理することを推奨します(モノレポ構成の場合は各モジュールに `CHANGELOG.md` を持たせる等)。
- 破壊的変更(API仕様変更など)は明確にバージョンを分けてください。

---

## 更新ルール

- 技術仕様の変更は `docs/` 配下のドキュメントに反映し、コードとドキュメントの乖離を防いでください。
- 使われなくなったモジュールは削除するか [ARCHIVE/](../ARCHIVE/README.md) に移動してください。

---

## Git運用ルール

- 機能追加は `feature/xxx`、修正は `fix/xxx` ブランチで作業し、`main` へはレビューを経てマージしてください。
- リリースタグ(例: `v1.0.0`)を付けて、バージョンの区切りを明確にすることを推奨します。

---

*最終更新: 2026-07-03*
