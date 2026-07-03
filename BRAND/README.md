# BRAND

**ロゴ・名刺・カラー・デザインガイドライン**

---

## このフォルダの目的

株式会社スマートラボのブランド資産(ロゴファイル、カラーコード、名刺デザイン、その他ブランド関連の実素材)を保管します。ブランドの思想的な背景は [PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md](../PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md) を参照してください。

---

## 想定する構成

```
BRAND/
├── logo/           ← ロゴファイル(SVG, PNG等、各種サイズ・カラーバリエーション)
├── colors/         ← カラーパレット定義(カラーコード一覧)
├── typography/     ← 使用フォントの定義・ライセンス情報
├── business-card/  ← 名刺デザインデータ
└── guidelines/     ← ロゴの使用可否・禁止事項などのガイドライン文書
```

---

## 利用ルール

- ロゴを使用する際は、必ず `guidelines/` 内の使用規定を確認してください(余白の取り方、禁止色、最小サイズなど)。
- カラーコードは `colors/` の定義を正としてください。プロダクト・サイトごとに独自に色を決めないでください。
- 新しいブランド素材を作成した場合は、[PROJECT_BIBLE/20_UI_UX_Rules.md](../PROJECT_BIBLE/20_UI_UX_Rules.md) の原則(シンプル・余白・高級感・信頼感・ミニマル)に沿っているか確認してください。

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

*最終更新: 2026-07-03*
