# DESIGN_ASSETS / 01_LOGO

**株式会社スマートラボ 正式ロゴ(唯一の公式ロゴ)**

---

## 重要 — このロゴが正式版です(2026-07-04確定)

CEOより、[AI_WORKSPACE/INBOX/LOGO/](../../AI_WORKSPACE/INBOX/README.md) 経由で提供されたロゴを、**株式会社スマートラボの正式ロゴ**として確定する指示があった。

> 「今回添付したロゴを株式会社スマートラボの正式ロゴとします。今後このロゴ以外は使用しません。」(CEO、2026-07-04)

**ロゴのデザイン・比率・形状の変更は禁止。** このフォルダに格納されたデータが、Homepage・Dashboard・営業資料・名刺・パンフレットなど、あらゆる制作物で使用する唯一の正式ロゴです。

これまで [00_Foundation/07_Brand_Identity.md](../../PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md) に記載されていた「途切れた2画のストローク(S字)」の暫定ロゴ仕様、および [Icons/Reference/](../Icons/Reference/) の別ロゴ案(角丸正方形+「+」マーク)は、**両方とも本ロゴに置き換えられ、廃止**されました。

---

## ロゴのデザイン

六角形のフレームの中に、途切れた回路(サーキット)パターンで構成された「S」字のシンボル。ノード(円)と接続線が基板のような質感を加え、右上に小さな四芒星(スパークル)が添えられている。カラーは Smart Blue のグラデーション(明るい水色〜Primary Navy)。ロゴタイプは「Smart Labo」+ サブテキスト「Works」+ タグライン「会社を動かすAI。」の3階層構成。

---

## フォルダ構成

```
01_LOGO/
├── Primary/    ← メインロゴ(カラー・白/透過背景用)。最も基本的な使用形態
├── White/      ← 白抜き版(ロゴ本体+アイコン単体)。Navy等の濃色背景で使用
├── Dark/       ← 黒背景での使用例(参考。実データはPrimaryと同じロゴを黒背景に配置したもの)
├── Icon/       ← アイコンマーク単体(六角形+S字+スパークル。カラー版・白抜き版)
├── Favicon/    ← ファビコン用(512×512)・アプリアイコン用(1024×1024、角丸背景付き)
├── SVG/        ← ベクターデータ置き場(現状は未格納。下記「既知の制約」参照)
├── PNG/        ← PNG形式の代表アセット(ロゴ本体・アイコン)
└── WebP/       ← WebP形式の代表アセット(ロゴ本体・アイコン)
```

---

## アセット一覧

| フォルダ | ファイル | 内容 |
|---|---|---|
| Primary/ | `smartlabo_logo_primary.png` / `.webp` | メインロゴ(カラー・横組み・白/透過背景) |
| White/ | `smartlabo_logo_white_lockup.png` / `.webp` | ロゴ本体の白抜き版(Navy背景用) |
| White/ | `smartlabo_logo_white_icon.png` / `.webp` | アイコンマークの白抜き版(Navy背景用) |
| Dark/ | `smartlabo_logo_dark_bg.png` / `.webp` | 黒背景上でのロゴ配置例 |
| Icon/ | `smartlabo_icon_color.png` / `.webp` | アイコンマーク単体(カラー) |
| Icon/ | `smartlabo_icon_white.png` / `.webp` | アイコンマーク単体(白抜き) |
| Favicon/ | `smartlabo_favicon_512.png` / `.webp` | ファビコン用(512×512、余白14%→10%で再構成) |
| Favicon/ | `smartlabo_app_icon_1024.png` / `.webp` | アプリアイコン用(1024×1024) |
| PNG/ | `smartlabo_logo_primary.png`、`smartlabo_icon_color.png` | 代表アセットのPNG版(参照用) |
| WebP/ | `smartlabo_logo_primary.webp`、`smartlabo_icon_color.webp` | 代表アセットのWebP版(参照用) |

---

## 由来・データソース

- **高解像度マスターデータ**(1254×1254px、アイコンマーク+「Smart Labo」ロゴタイプ、Works・タグラインなし): CEOより2026-06-29提供。[AI_WORKSPACE/COMPLETED/LOGO/logo_master_highres_20260629.png](../../AI_WORKSPACE/COMPLETED/README.md) に履歴保管。**Icon/・Favicon/ 配下のアセットは、最も解像度が高いこのマスターデータから書き出しています。**
- **ブランドシート**(1536×1024px、バリエーション・使用ルール一覧): CEOより2026-07-04提供、AI_WORKSPACE経由で正式ロゴとして確定。[AI_WORKSPACE/COMPLETED/LOGO/logo_brand_sheet_20260704.png](../../AI_WORKSPACE/COMPLETED/README.md) に履歴保管。**Primary/・White/・Dark/ 配下のアセットはこのシートから切り出しています。**

---

## 既知の制約(重要)

**現時点では、ロゴの真のベクターデータ(SVG)は提供されていません。** [SVG/](SVG/) フォルダには実データを格納していません。

- 格納しているPNG/WebPは、提供されたブランドシート(1536×1024px)およびマスターデータ(1254×1254px)から書き出したラスター画像です。Web表示(Homepage・Dashboard等の通常サイズ)では十分な解像度ですが、名刺・パンフレット等の大判印刷で使用する場合は、拡大時にジャギー(輪郭のギザギザ)が生じる可能性があります。
- ロゴの形状を維持したまま自分でSVGへトレース(ベクター化)することは、「ロゴのデザイン・比率・形状は変更禁止」というCEO指示に照らして慎重を期すべきため、**Claude Codeの判断では実施していません。**
- 大判印刷用途が発生した場合は、CEOより真のベクターデータ(Illustrator/Figma等で作成されたSVGファイル)の提供を受けるか、正式なトレース作業をデザイナーに依頼することを推奨します。

---

## 利用ルール

ロゴの使用ルール(余白規定・最小サイズ・背景色・使用禁止例・推奨用途別サイズ)は、[PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md](../../PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md) の「ロゴ使用ルール」セクションに正式制定しています。実装・制作時は必ず参照してください。

- **Homepage・Dashboard・営業資料・名刺・パンフレットなど、すべての制作物でこのロゴに統一してください。**
- ロゴの色・比率・形状を変更しないでください(引き伸ばし、色変更、回転、要素の分離等は禁止)。
- 背景色に応じて適切なバリエーション(Primary/White/Dark)を選択してください。

---

## 変更履歴

| バージョン | 日付 | 変更者 | 変更内容 |
|---|---|---|---|
| v1.0 | 2026-07-04 | Claude Code(CEO指示による) | 初版作成。CEOが正式ロゴとして確定した六角形S字シンボル(サーキットパターン)のロゴデータを、AI_WORKSPACE経由で受領・格納。Primary/White/Dark/Icon/Favicon/SVG/PNG/WebPの8フォルダ構成を制定 |

*最終更新: 2026-07-04*
