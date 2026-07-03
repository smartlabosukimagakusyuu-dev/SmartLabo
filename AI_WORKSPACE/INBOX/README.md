# AI_WORKSPACE/INBOX

**ChatGPTが作成した素材の受け取り口(未確認・未処理)**

---

## 役割

ここは、ChatGPTとの対話でCEOが作成した画像・資料・参考デザイン・プロンプトを保存する「受け取り口」です。**保存されているだけの状態では、Claude Codeはまだその素材を確認・利用していません。**

Claude Codeは、新しい素材を使う作業を始める前に、必ずこのフォルダを最初に確認してください([AI_WORKSPACE/README.md](../README.md) の運用ルール参照)。

---

## サブフォルダ構成

| フォルダ | 用途 |
|---|---|
| `HERO/` | Homepage等のHeroセクション用ビジュアル候補 |
| `BACKGROUND/` | Hero以外の背景画像・グラフィック候補 |
| `DASHBOARD/` | ダッシュボードUIのモックアップ・参考画像 |
| `LOGO/` | ロゴの参考画像・候補データ |
| `ICONS/` | UIアイコンの参考画像・候補データ |
| `INDUSTRY/` | 対応業種ページ等で使う業種別ビジュアル |
| `REFERENCE/` | 上記に分類しにくい参考資料・競合分析・ブランドキット画像等 |
| `PROMPTS/` | 画像生成・文章生成等に使われたプロンプトのテキスト |

新しい用途が発生した場合は、[DESIGN_ASSETS/](../../DESIGN_ASSETS/README.md) の番号付きユースケースフォルダ(`01_HERO/` 等)と対応させながら、サブフォルダを追加してください。

---

## 命名・保存ルール

- ファイル名には、可能な範囲で用途がわかる名前を付けてください(例: `hero_sunrise_city_01.webp`)。
- 画像に対応するプロンプトや文脈情報がある場合は、同名の `.md` または `.txt` を添えることを推奨します(この情報は、後で [DESIGN_ASSETS/](../../DESIGN_ASSETS/README.md) へ正式登録する際のメタデータ作成に使われます)。
- ここに置かれた時点では「候補」であり、採用可否は未確定です。

---

## 次のステップ

Claude Codeがこのフォルダの素材を利用する場合、[PROCESSING/](../PROCESSING/README.md) へ移動してから作業してください。詳細は [AI_WORKSPACE/README.md](../README.md) を参照してください。

---

## 変更履歴

| バージョン | 日付 | 変更者 | 変更内容 |
|---|---|---|---|
| v1.0 | 2026-07-03 | Claude Code(CEO指示による) | 初版作成。8サブフォルダ(HERO/BACKGROUND/DASHBOARD/LOGO/ICONS/INDUSTRY/REFERENCE/PROMPTS)を制定 |

*最終更新: 2026-07-03*
