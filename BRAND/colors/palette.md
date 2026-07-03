# Smart Labo カラーパレット

出典: CEOより提供されたブランドキット参照資料(2026-07-03)。正式な定義は [PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md](../../PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md) の「使用カラー」であり、矛盾があればそちらを正としてください。

## パレット

| カラー | Hex | RGB | 役割 |
|---|---|---|---|
| Primary Navy | `#0A1B3D` | 10, 27, 61 | 基調色。背景・重要セクション |
| Smart Blue | `#2563EB` | 37, 99, 235 | メインアクセント。CTA・主要な強調要素 |
| Light Blue | `#60A5FA` | 96, 165, 250 | ホバー・サブアクセント・チャート補助色 |
| Accent Blue | `#00D4FF` | 0, 212, 255 | グロー効果・データ可視化のハイライト(限定使用) |
| White | `#FFFFFF` | 255, 255, 255 | ベース |
| Light Gray | `#F3F6FA` | 243, 246, 250 | セクション区切り・補助背景 |
| Gray | `#64748B` | 100, 116, 139 | 非強調テキスト・アイコン |
| Dark Gray | `#1E293B` | 30, 41, 59 | White背景上の本文テキスト |

## 例外(意味色)

ステータスバッジに限り、以下の慣用色を使用してよい(ブランドアクセントではなく機能的なシグナルのため)。

| 状態 | 色の系統 |
|---|---|
| 完了 | 緑 |
| 進行中 | 青(Smart Blue) |
| 未着手 | グレー |
| 遅延 | 赤 |

## 使い方

- CSS変数・デザイントークンとして実装する場合は、この表のHexコードをそのまま使用してください。
- 新しい色を追加する場合は、必ず [PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md](../../PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md) を先に更新し、この表を同期させてください([PROJECT_BIBLE/60_Editorial_Workflow.md](../../PROJECT_BIBLE/60_Editorial_Workflow.md) の更新フローに従う)。

---

*最終更新: 2026-07-03*
