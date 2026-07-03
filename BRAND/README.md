# BRAND

**ロゴ・名刺・カラー・デザインガイドライン**

---

## このフォルダの目的

株式会社スマートラボのブランド**仕様・定義**(カラーパレット、ガイドライン文書)を保管します。ブランドの思想的な背景は [PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md](../PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md) を参照してください。

> **ロゴファイル・アイコン等の実データは、このフォルダではなく [DESIGN_ASSETS/](../DESIGN_ASSETS/README.md) に格納します**(CEO指示、2026-07-03)。`BRAND/` は「定義・仕様」を、`DESIGN_ASSETS/` は「実データ」を管理する役割分担です。

---

## 想定する構成

```
BRAND/
├── colors/         ← カラーパレット定義(カラーコード一覧)
│   └── palette.md  ← 正式カラーパレット(8色、Hexコード)
├── typography/     ← 使用フォントの定義・ライセンス情報
├── business-card/  ← 名刺デザインデータ
└── guidelines/     ← ロゴの使用可否・禁止事項などのガイドライン文書
```

- カラーパレットは [colors/palette.md](colors/palette.md) に定義済みです。
- ロゴの構成(カラー/白抜き/アイコンの3バリエーション)は [PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md](../PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md) の「ロゴ」セクションに定義済みですが、**実データ(SVG/PNGファイル)は [DESIGN_ASSETS/Logo/](../DESIGN_ASSETS/Logo/README.md) にまだ格納されていません**([PROJECT_BIBLE/50_TODO.md](../PROJECT_BIBLE/50_TODO.md) 参照)。

---

## 利用ルール

- ロゴを使用する際は、必ず `guidelines/` 内の使用規定を確認してください(余白の取り方、禁止色、最小サイズなど)。
- カラーコードは `colors/` の定義を正としてください。プロダクト・サイトごとに独自に色を決めないでください。
- 新しいブランド素材を作成した場合は、[PROJECT_BIBLE/20_UI_UX_Rules.md](../PROJECT_BIBLE/20_UI_UX_Rules.md) の原則(Premium・Enterprise・Trust・Innovation等、Enterprise AI Platform方針)に沿っているか確認してください。

---

## Version管理

- ロゴ等の画像・デザインファイルは、変更時にファイル名へバージョンを付記することを推奨します(例: `logo_v2.svg`)。
- 旧バージョンは削除せず、`archive/` サブフォルダまたは [ARCHIVE/](../ARCHIVE/README.md) に保管してください。

---

## 更新ルール

- ブランド素材の変更(ロゴリニューアル等)は、[PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md](../PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md) の更新ルール(経営層承認)に従ってください。
- 更新後は、[WEBSITE/](../WEBSITE/README.md) や [SmartLaboWorks/](../SmartLaboWorks/README.md) など、ブランド素材を使用している箇所への反映漏れがないか確認してください。

---

## Git運用ルール

- 画像・デザインファイルはサイズが大きくなりがちなため、必要に応じて Git LFS の利用を検討してください。
- ブランドの根幹に関わる変更(ロゴ差し替え等)はPull Requestでレビューを経てからマージしてください。

---

## 変更履歴

| バージョン | 日付 | 変更者 | 変更内容 |
|---|---|---|---|
| v1.0 | 2026-07-03 | Smart Labo | 初版作成 |
| v1.1 | 2026-07-03 | Claude Code(CEO提供のブランドキット参照資料による) | [colors/palette.md](colors/palette.md) を新設し正式カラーパレットを追加。ロゴ構成の定義先を明記(実ファイルは未格納) |
| v1.2 | 2026-07-03 | Claude Code(CEO指示による) | 実データ(ロゴ等)の格納先を [DESIGN_ASSETS/](../DESIGN_ASSETS/README.md) に変更。`BRAND/` は仕様・定義のみを扱う役割に整理し、`logo/` サブフォルダの記載を削除 |

*最終更新: 2026-07-03*
