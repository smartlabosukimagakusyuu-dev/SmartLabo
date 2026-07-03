# DESIGN_ASSETS

**Smart Laboのデザイン資産(実データ)管理場所**

---

## このフォルダの目的

`DESIGN_ASSETS` は、Smart Laboのブランド・プロダクトで使用する**デザイン資産の実データ**(画像・SVG・フォントファイル等)を格納する、唯一の正式な場所です(CEO指示、2026-07-03)。

PROJECT_BIBLEやBRAND/DESIGNフォルダが「何を、なぜ、どう使うか」という**思想・仕様**を定義するのに対し、`DESIGN_ASSETS` は「実際のファイルそのもの」を保管します。

| 階層 | 役割 | 具体例 |
|---|---|---|
| [PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md](../PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md) | ブランドの思想・原則 | 「ロゴはカラー/白抜き/アイコンの3種」 |
| [BRAND/](../BRAND/README.md)、[DESIGN/](../DESIGN/README.md) | 仕様・トークンの文書化 | `colors/palette.md`、`system/design-tokens.md` |
| **`DESIGN_ASSETS/`(このフォルダ)** | **実データそのもの** | `Logo/smartlabo-logo-color.svg` |

---

## フォルダ構成

```
DESIGN_ASSETS/
├── Logo/         ← ロゴファイル(カラー/白抜き/アイコン)
├── Background/    ← 背景画像・グラフィック(都市+AIネットワーク等)
├── Icons/         ← UIアイコン(AI分析/タスク管理/顧客管理 等)
├── Dashboard/      ← ダッシュボードUIのモックアップ・スクリーンショット
├── Buttons/       ← ボタンコンポーネントの素材
├── Cards/         ← カードコンポーネントの素材
├── Graphics/       ← 装飾グラフィック(ネットワークライン、グロー、グラデーション等)
├── Images/         ← 写真素材(オフィス・経営者・都市等)
├── Colors/        ← カラースウォッチファイル・プレビュー画像
└── Fonts/          ← 使用フォントの実データ・ライセンスファイル
```

各サブフォルダの詳細な利用ルールは、フォルダ内の `README.md` を参照してください。

---

## 現在の状態(2026-07-03時点)

**このフォルダには、まだ実データ(SVG/PNG/フォントファイル等)は格納されていません。** CEOより「今後正式なロゴデータ等を用意する」旨の指示があり、フォルダ構成のみを先行して整備した状態です。それまでの間、各アセットの**仕様**は [PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md](../PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md) と [DESIGN/system/design-tokens.md](../DESIGN/system/design-tokens.md) に記録されています。

実データが到着次第、該当サブフォルダに格納し、[50_TODO.md](../PROJECT_BIBLE/50_TODO.md) の該当タスクを消し込んでください。

---

## Design Assets運用ルール

1. **実データは必ずこのフォルダに置く。** `BRAND/` や `DESIGN/` に直接バイナリファイルを置かず、それらのフォルダからは `DESIGN_ASSETS/` 内のファイルを参照する形にしてください。
2. **ファイル名は用途がわかる名前にする。** 例: `smartlabo-logo-color.svg`、`smartlabo-logo-white.svg`、`icon-ai-analysis.svg`。バージョンを付ける場合は末尾に `_v2` のように付記してください。
3. **カラー・仕様と実データを一致させる。** 実データを追加・変更したら、対応する仕様ドキュメント([00_Foundation/07_Brand_Identity.md](../PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md)、[design-tokens.md](../DESIGN/system/design-tokens.md))に相違がないか確認してください。矛盾があれば仕様側を先に更新します([60_Editorial_Workflow.md](../PROJECT_BIBLE/60_Editorial_Workflow.md) の更新フローに従う)。
4. **禁止ビジュアルを持ち込まない。** [PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md](../PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md) の「禁止するビジュアル表現」に反する素材(安っぽいAIロボット、六角形背景等)は格納しないでください。

---

## Version管理

- 各アセットファイルは、変更のたびに上書きせず、意味のある単位で `_v2` 等のバージョンを付けて追加することを推奨します。
- 旧バージョンは削除せず、[ARCHIVE/](../ARCHIVE/README.md) へ移動してください。

---

## 更新ルール

- 新しいアセットを追加する際は、対応するサブフォルダに格納し、そのフォルダの `README.md` の記載内容(命名規則等)に従ってください。
- 既存のブランド原則(色・ロゴ・禁止事項)と矛盾するアセットは追加しないでください。矛盾する変更が必要な場合は、先にPROJECT_BIBLEを更新してください。

---

## Git運用ルール

- 画像・フォントファイルはサイズが大きくなりがちなため、必要に応じて Git LFS の利用を検討してください。
- ロゴなどブランドの根幹に関わるアセットの追加・変更は、コミットメッセージに `design:` プレフィックスを使用し、変更内容を明記してください。

---

## 変更履歴

| バージョン | 日付 | 変更者 | 変更内容 |
|---|---|---|---|
| v1.0 | 2026-07-03 | Claude Code(CEO指示による) | 初版作成。10サブフォルダ(Logo/Background/Icons/Dashboard/Buttons/Cards/Graphics/Images/Colors/Fonts)の構成とDesign Assets運用ルールを整備 |

*最終更新: 2026-07-03*
