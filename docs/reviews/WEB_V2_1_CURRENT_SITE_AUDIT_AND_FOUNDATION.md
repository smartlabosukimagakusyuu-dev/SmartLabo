# WEB-V2-1 現行サイト監査・安全なローカル開発基盤の作成

Smart Labo公式サイト Version 2.0 の第1工程。現行の公開サイトを一切変更せずに、構成を調査し、v2の開発土台のみを作成した記録。

---

## 1. 実施日時

2026-07-28

---

## 2. 調査対象

| 対象 | パス / URL |
|---|---|
| 現行公式サイト(本体) | `C:\Users\user\Desktop\SmartLabo` |
| 公開ソースフォルダ | `SmartLabo/WEBSITE/` |
| 正式ロゴSSOT | `SmartLabo/DESIGN_ASSETS/01_LOGO/` |
| ロゴ同一性の照合先 | `C:\Users\user\Desktop\smartlabo-works-lite\public\brand`(**読み取りのみ。変更なし**) |
| 問い合わせフォーム(サーバー側) | `SmartLabo/xserver-form/` |

**名前だけで判断していないことの確認:** Desktop配下には `smartlabo-works`、`smartlabo-works-lite`、`smartlabo`、`smartlabo-miraie-beta` 等の類似名フォルダが複数存在する。git remote が `smartlabosukimagakusyuu-dev/SmartLabo.git` であること、`.github/workflows/pages.yml` が存在すること、`WEBSITE/CNAME` が本番ドメインを保持していることの3点をもって、`SmartLabo` を現行公開サイトの本体と特定した。

---

## 3. 現在の公開URL

| URL | 実測結果 |
|---|---|
| `https://smartlaboworks.com/` | **HTTP 200**(正常表示) |
| `https://smartlabosukimagakusyuu-dev.github.io/SmartLabo/` | **HTTP 200**。`https://smartlaboworks.com/` へリダイレクトされる |

**事前想定との差異:** 依頼時に想定されていた `github.io/SmartLabo/` は現在の正規URLではない。独自ドメイン `smartlaboworks.com` が設定済みで、github.io へのアクセスはそちらへ転送される。**v2の canonical・OGP・sitemap はすべて `https://smartlaboworks.com/` を基準にする必要がある。**

---

## 4. ローカルフォルダ

`C:\Users\user\Desktop\SmartLabo`

作業開始時点で **未コミットの変更なし(working tree clean)**。既存の変更を削除・stash・上書きした事実はない。

---

## 5. Git Remote

```
origin  https://github.com/smartlabosukimagakusyuu-dev/SmartLabo.git (fetch)
origin  https://github.com/smartlabosukimagakusyuu-dev/SmartLabo.git (push)
```

---

## 6. ブランチ

| 項目 | 値 |
|---|---|
| 作業開始時のブランチ | `master` |
| 公開元(本番)ブランチ | `master` |
| 開始時のHEAD | `10463d6` |
| 存在したブランチ | `master` のみ(`origin/master`) |
| 今回作成したブランチ | `website-v2` |

---

## 7. GitHub Pages 公開方式

**GitHub Actions によるデプロイ。** `.github/workflows/pages.yml`:

```yaml
on:
  push:
    branches: [master]
    paths:
      - "WEBSITE/**"
      - ".github/workflows/pages.yml"
  workflow_dispatch:
```

- `actions/configure-pages@v5` → `actions/upload-pages-artifact@v3`(`path: "WEBSITE"`)→ `actions/deploy-pages@v4`
- **ルート公開でも docs/ 公開でもない。** `WEBSITE/` フォルダをアーティファクトとしてアップロードする方式。
- **ビルドステップは存在しない。** `WEBSITE/` の中身がそのまま配信される。
- `concurrency: pages`(同時デプロイ抑止)、`workflow_dispatch` による手動実行が可能。

> **未確認事項:** リポジトリの Settings → Pages における Source 設定(「GitHub Actions」が選択されているか)は、GitHub API での確認を試みたが `gh` が未認証のため取得できなかった。ただしワークフローが `deploy-pages` アクションを使用して稼働しており、実際に本番が配信されていることから、Source は「GitHub Actions」であると判断できる。推測で断定はせず、代表側で Settings 画面から一度確認いただきたい。

---

## 8. 現行ファイル構成

`WEBSITE/`(公開対象):

```
WEBSITE/
├── index.html            トップページ
├── product.html          製品
├── features.html         機能
├── real-estate.html      不動産会社向け
├── pricing.html          導入プラン
├── company.html          会社情報
├── contact.html          お問い合わせ
├── privacy.html          プライバシーポリシー
├── terms.html            利用規約
├── 404.html
├── app.html              デモ画面(約150KB。全ページのヘッダーCTAから参照)
├── CNAME                 smartlaboworks.com
├── robots.txt
├── sitemap.xml
├── README.md
├── css/style.css         単一CSS(約72KB)
├── js/                   chat.js / company-info.js / configurator.js /
│                         hero.js / main.js / pages.js
└── img/
    ├── hero/hero_background_02_city_network.webp
    └── logo/  favicon.png / smartlabo_icon_white.webp /
                smartlabo_logo_primary_transparent.png /
                smartlabo_logo_white_transparent.png
```

**技術構成:** 素の HTML / CSS / JavaScript。ビルドツール・フレームワーク・パッケージマネージャの使用なし。リポジトリ全体で追跡ファイル190件。

リポジトリ内のその他の主要ディレクトリ: `PROJECT_BIBLE/`(規範文書)、`DESIGN_ASSETS/`(ロゴ・素材SSOT)、`BRAND/`(カラーパレット)、`DESIGN/`(デザイントークン)、`xserver-form/`(問い合わせフォームのPHP実装)、`AI_WORKSPACE/`、`DOCUMENT/`、`LEGAL/` ほか。**これらはいずれもデプロイ対象外**(アーティファクトは `WEBSITE/` のみ)。

---

## 9. 独自ドメイン状況

| 項目 | 状況 |
|---|---|
| 独自ドメイン | **設定済み** — `smartlaboworks.com` |
| CNAMEファイル | **あり** — `WEBSITE/CNAME`(1行、内容はドメイン名のみ) |
| HTTPS | 有効(`https://` で 200 応答) |
| github.io からの転送 | 有効 |

> **重要:** `CNAME` は `WEBSITE/` 配下にあるため、**本番切替時に `WEBSITE/` の中身を入れ替えるとCNAMEが消えて独自ドメインが即座に切れる。** 切替手順に「CNAMEを必ず残す」ことを明記済み(`website-v2/README.md` 第8節)。

---

## 10. SEO状況

| 項目 | 現状 |
|---|---|
| title | 全ページに設定あり |
| meta description | 全ページに設定あり |
| canonical | 9ページに設定あり。**`404.html` と `app.html` には無し**(404はnoindex相当のため妥当) |
| OGP | og:type / site_name / title / description / url / image / locale を設定済み |
| Xカード | `summary_large_image` を設定済み |
| OGP画像 | **hero背景画像(`hero_background_02_city_network.webp`)を流用。** 1200×630のOGP専用画像ではないため、SNS上で見切れる可能性がある |
| 見出し構造 | `index.html` の `h1` は1つ(`.hero__title`)。適切 |
| 画像alt | 静的HTMLの画像はすべて alt 指定あり。`app.html` 内でJSが生成する `<img src="${src}">` 1箇所のみ alt 欠落(デモ画面内部) |
| Organization構造化データ | **あり**(`index.html`)。所在地・電話番号等の未確定項目を推測で埋めない方針が明記されており妥当 |
| WebSite構造化データ | **あり**(`index.html`) |
| SoftwareApplication / Product | **なし** |
| FAQ構造化データ | **なし**(サイト上にFAQセクションが存在しないため、現状では正しい) |
| 構造化データの配置 | `index.html` のみ。他9ページには ld+json が無い |
| robots.txt | あり。`Allow: /` + Sitemap宣言 |
| sitemap.xml | あり。7URL登録。**`privacy.html` と `terms.html` が未登録** |
| 404 | あり(`404.html`) |
| 表示速度 / 画像形式 | ヒーロー画像はWebP。ただし **ロゴが最大215KBのPNG**(`smartlabo_logo_primary_transparent.png`)で、WebP版が用意されていない |
| Core Web Vitals | **未計測** |

---

## 11. 計測状況

| 項目 | 状況 |
|---|---|
| Google Analytics | **未導入。** 解析コードは一切埋め込まれていない |
| Cookie | **未使用。** Cookie同意バナーも未実装 |
| Search Console | **所有権確認メタタグなし。** 確認用HTMLファイルもリポジトリ内に存在しない |
| 導入位置 | `index.html` / `contact.html` の `<head>` に、導入順序(Search Console → GA4 → Cookie同意)を記したコメントブロックが用意されている |

導入時の注意として「GA4を入れる場合は `privacy.html` の『Cookie・アクセス解析について』(現在は未導入と明記)を先に更新すること」がコメント内に記載済み。この運用は妥当であり、v2でも踏襲する。

**本報告書には計測ID・認証情報を一切記載していない**(そもそもリポジトリ内に存在しない)。

---

## 12. 問い合わせ方式

**現在: フォーム停止中。メール誘導のみ。**

`WEBSITE/contact.html` にはコメントで経緯が明記されている。

> 問い合わせフォームは本番送信テスト未完了のため一時的に非表示とし、メールでの直接連絡へ誘導する(2026-07-16 CEO指示)。再有効化する際はgit履歴からフォームブロックを復元すること。

- 現在の導線: `mailto:info@smartlaboworks.com` のボタン1つ
- サーバー側実装は `xserver-form/`(PHP)に存在するが未接続。`contact.php` / CSRFトークン / レートリミッタ / reCAPTCHA v3 / SMTP送信のコードが揃っている
- reCAPTCHA v3 はサイトキー未発行のため `<script>` がコメントアウトされている

> **構造上の制約(v2で必ず解決が必要):** GitHub Pages は静的ホスティングのため **PHPを実行できない。** `xserver-form/` のPHPは GitHub Pages 上では動作しない。v2でフォームを復活させる場合、(a) Xserver等に置いた `contact.php` へのクロスオリジンPOST、(b) 外部フォームサービスの利用、のいずれかを選ぶ必要がある。方式が確定するまでフォームUIを実装しない方針を `website-v2/contact.html` に明記した。

---

## 13. 正式ロゴ確認

### 13-1. 2箇所の資産は同一か → **完全に同一**

`SmartLabo/DESIGN_ASSETS/01_LOGO/` と `smartlabo-works-lite/public/brand/` の全8ファイルについてMD5を照合した結果、**バイト単位で完全一致**。ファイル名が異なるだけの同一資産である。

| smartlabo-works-lite/public/brand | DESIGN_ASSETS/01_LOGO | MD5(先頭12桁) |
|---|---|---|
| `logo-primary.png` | `Primary/smartlabo_logo_primary.png` | `2cd3b7e10408` |
| `logo-primary.webp` | `Primary/smartlabo_logo_primary.webp` | `2c499ddbce93` |
| `logo-white.png` | `White/smartlabo_logo_white_lockup.png` | `bafa04678895` |
| `icon-color.png` | `Icon/smartlabo_icon_color.png` | `cfe18f74a1be` |
| `icon-color.webp` | `Icon/smartlabo_icon_color.webp` | `7bf40f393ea5` |
| `icon-white.png` | `Icon/smartlabo_icon_white.png` | `00f7f7db7fa3` |
| `favicon-512.png` | `Favicon/smartlabo_favicon_512.png` | `095e56f8d7ef` |
| `app-icon-1024.png` | `Favicon/smartlabo_app_icon_1024.png` | `c5d11c447464` |

`DESIGN_ASSETS/01_LOGO/` のほうが網羅的(`logo-white.webp`、`Dark/`、`favicon-512.webp` を追加で保持)。**リポジトリ内で完結する `DESIGN_ASSETS/01_LOGO/` をSSOTとして採用した。**

### 13-2. 現行公開サイトのロゴ → **SSOTと一致していない(発見した問題)**

`WEBSITE/img/logo/` の4ファイルのうち、SSOTと一致するのは1つだけ。

| WEBSITE/img/logo のファイル | SSOTとの一致 |
|---|---|
| `smartlabo_icon_white.webp` | **一致**(`Icon/smartlabo_icon_white.webp`) |
| `smartlabo_logo_primary_transparent.png` | **不一致。** SSOTに存在しない派生ファイル(215KB) |
| `smartlabo_logo_white_transparent.png` | **不一致。** SSOTに存在しない派生ファイル(30KB) |
| `favicon.png` | **不一致。** SSOTに存在しない派生ファイル(3.5KB) |

`_transparent` 系は背景を透過処理した加工版と推測されるが、**加工の記録がSSOT側に残っていない。** v1のヘッダー・フッターで実際に表示されているのは `smartlabo_icon_white.webp`(SSOT一致)であり、`_transparent` 系はヒーロー等で使われている(コミット `6c42937` / `5e6234c` 参照)。

v2ではこの状態を持ち込まず、**SSOTからのコピーのみを使う。**

### 13-3. v2への配置

`website-v2/assets/brand/` にSSOTから11ファイルをコピーし、**コピー後のMD5がSSOTと一致することを確認済み。** 用途が判別できる名前へ統一した(対応表は `website-v2/README.md` 第5節)。

- **SSOT(`DESIGN_ASSETS/01_LOGO/`)は移動・削除・上書きしていない。** 作業後の `git status DESIGN_ASSETS` は空(変更なし)。
- 再生成・トレース・色変更・加工・回転は一切行っていない。
- 実装ルールとして「高さのみ指定・幅は `auto`」を `tokens.css`(`--logo-h-header` / `--logo-h-footer` / `--logo-h-hero`)と `base.css`(`.logo__img { height: …; width: auto; }`)に組み込んだ。
- ブラウザ実測で縦横比の維持を確認: 原寸 531×180 → 表示 118×40(比率一致)。

### 13-4. 既知の制約

真のベクターデータ(SVG)は未提供。`DESIGN_ASSETS/01_LOGO/SVG/` は空。Web表示には十分だが、大判印刷用途が発生した場合は代表へ確認が必要(`DESIGN_ASSETS/01_LOGO/README.md` の記載を踏襲)。

---

## 14. Version 2 の開発方式

### 採用した方式

**B（専用の開発ブランチ）+ A（リポジトリ内の独立フォルダ）の併用。**

- ブランチ: `website-v2`(`master` から分岐)
- フォルダ: リポジトリルート直下の `website-v2/`(**`WEBSITE/` の外**)

### 理由

1. **本番ブランチ `master` へ直接コミットしない**という条件を満たす。
2. 同一リポジトリ内のため、SSOT(`DESIGN_ASSETS/`)・規範文書(`PROJECT_BIBLE/`)・v1の実装を参照でき、履歴も分断されない。完成時の切替が「フォルダの中身を置き換える1コミット」で済む。
3. `website-v2/` を `WEBSITE/` の外に置くことで、**ブランチとパスの二重の防御**になる(第16節)。
4. 方式C(完全に別のリポジトリ)は、ロゴSSOTと規範文書の二重管理を生み、切替時に履歴が分断される。同一リポジトリで十分な隔離が得られるため採用しなかった。

### 技術選定: 素の HTML / CSS / JavaScript(フレームワーク不採用)

React / Next.js / Vite は採用しない。理由:

- 現行の公開方式は `actions/upload-pages-artifact` が `WEBSITE/` を**そのままアップロードする**構成で、ビルドステップが存在しない。フレームワークを導入すると `pages.yml` の改修が必須になり、**本番のデプロイ経路そのものを触ることになる**(今回の最重要条件に反する)。
- v1も素のHTML/CSS/JSで構築されており、技術を変える必然性がない。
- 静的なマーケティングサイトであり、SPAが必要な動的状態を持たない。表示速度・Core Web Vitalsの観点でもランタイムJSは少ないほうが有利。

外部CDN(フォント・アイコン・JSライブラリ)への依存も追加していない。

---

## 15. 開発用ファイル構成

```
website-v2/
├── index.html        トップページ(1〜15のセクション骨格。本実装はWEB-V2-2以降)
├── features.html     機能
├── pricing.html      料金
├── company.html      会社情報
├── contact.html      お問い合わせ
├── privacy.html      プライバシーポリシー
├── terms.html        利用規約
├── 404.html          noindex
├── robots.txt
├── sitemap.xml
├── README.md         本番切替手順・ロールバック手順・ロゴ規約・禁止事項
└── assets/
    ├── css/tokens.css   デザイントークン
    ├── css/base.css     reset・レイアウト・ボタン・ヘッダー・フッター・フォーカス
    ├── js/main.js       モバイルナビ開閉・現在ページ強調のみ
    ├── images/          (空。.gitkeepのみ)
    └── brand/           正式ロゴ11ファイル(SSOTからのコピー)
```

### デザイン基盤の内容

すべて既存資産から導出しており、**新しい色は1つも追加していない。**

| 項目 | 内容 |
|---|---|
| ブランドカラー | `BRAND/colors/palette.md` の8色を1対1で変数化。Smart Blue `#2563EB` が唯一のアクセント |
| Navy / Blue階調 | v1 `style.css` の `:root` をそのまま継承 |
| セマンティックカラー | `--bg-base` / `--text-primary` / `--focus-ring` 等。実装ではこちらを参照 |
| 余白トークン | 4px基準の `--sp-1`〜`--sp-10`。`--section-y`(96px、v1と一致)、`--gutter`(32px、v1と一致) |
| 角丸 | `--radius-sm/md/lg`(8/12/20px、v1から継承)+ `--radius-full` |
| 影 | `--shadow-sm/md/lg/blue`(Navyベースの半透明) |
| 最大コンテンツ幅 | `--content-max: 1200px`(v1と一致)、`--content-narrow: 760px`(規約系ページ用) |
| ブレークポイント | **1080 / 860 / 640 / 520 の4段に統一。** v1は 1100/1080/960/860/760/640/520 が混在していた |
| ボタン | `.btn` + `--primary` / `--secondary` / `--ghost-light`、`--lg` / `--sm` |
| 見出し | `--fs-display`(clamp 32→56px)/ `--fs-h2`(v1 `.section__title` と一致)/ h3 / h4 |
| フォーカス表示 | `:focus-visible` で3pxリング。濃色背景上ではAccent Blue `#00D4FF` に自動切替。スキップリンクも実装 |
| モーション | `prefers-reduced-motion: reduce` に対応 |

---

## 16. 本番に影響しない根拠

### 根拠1: ワークフローのトリガ条件を二重に外している

```yaml
on:
  push:
    branches: [master]        # ← 作業ブランチは website-v2。該当しない
    paths:
      - "WEBSITE/**"          # ← 作業パスは website-v2/**。マッチしない
      - ".github/workflows/pages.yml"   # ← 変更していない
```

`branches` と `paths` は **AND条件**。両方を満たさない限りデプロイは起動しない。今回はどちらも満たしていない。仮に誤って `master` へマージしても、`website-v2/**` は `WEBSITE/**` にマッチしないため、それだけではデプロイは走らない。

### 根拠2: アーティファクトに含まれない

`actions/upload-pages-artifact@v3` の `path: "WEBSITE"` により、配信されるのは `WEBSITE/` のみ。`website-v2/` はアップロード対象外。

### 根拠3: リモートへpushしていない

`website-v2` ブランチはローカルのみに存在する。GitHub上に存在しないため、Actionsが評価する機会自体がない。

### 根拠4: 実測による確認

| 確認項目 | 結果 |
|---|---|
| `https://smartlaboworks.com/` | 作業後も **HTTP 200**(正常) |
| `https://smartlabosukimagakusyuu-dev.github.io/SmartLabo/` | 作業後も **HTTP 200**(独自ドメインへ転送) |
| `git status WEBSITE`(作業後) | 変更なし |
| `git status DESIGN_ASSETS`(作業後) | 変更なし |
| `git status .github`(作業後) | 変更なし |
| `master` ブランチ | `10463d6` のまま。コミット追加なし |
| GitHub Pages 設定 | 変更していない |
| CNAME | 変更していない |
| `smartlabo-works-lite` リポジトリ | 読み取りのみ。変更なし |

### 根拠5: v1とファイルが混ざっていない

v2のファイルはすべて `website-v2/` 配下に閉じている。v1(`WEBSITE/`)への追加・上書き・削除は一切行っていない。

---

## 17. 本番切替方法

**代表の承認後にのみ実行する。** 詳細手順は `website-v2/README.md` 第8節。

1. `website-v2` ブランチで最終確認(全ページ・リンク・レスポンシブ・ロゴ)。
2. **全ページから `dev-banner` の行を削除する。**
3. `master` から切替用ブランチ `release/website-v2` を作成。
4. `WEBSITE/` の中身を `website-v2/` の内容で置き換える。このとき以下を必ず残す。
   - **`WEBSITE/CNAME`** — 消えると独自ドメインが即座に切れる
   - **`WEBSITE/app.html`** — デモ画面。全ページのCTAから参照されている
   - v2に対応ページが無いURL(`product.html` / `real-estate.html`)の扱いは**代表に確認する**。GitHub Pagesは301リダイレクトを設定できないため、削除する場合は `sitemap.xml` 更新とSearch Consoleでの確認が必要。
5. 差分が `WEBSITE/` 以外に及んでいないことを確認してコミット・push。
6. Actions の `Deploy Homepage to GitHub Pages` の成功を確認。
7. `https://smartlaboworks.com/` を実ブラウザで確認(HTTPS・favicon・全ページ)。

---

## 18. ロールバック方法

### 切替前(現在)

`website-v2` ブランチを削除するだけで作業前の状態に戻る。本番には最初から一切影響していない。

```bash
cd C:/Users/user/Desktop/SmartLabo && git checkout master && git branch -D website-v2
```

### 切替後

```bash
cd C:/Users/user/Desktop/SmartLabo && git checkout master && git revert --no-edit <切替コミットのSHA> && git push origin master
```

`WEBSITE/` が変更されるためActionsが再実行され、v1の内容で再デプロイされる。所要は通常1〜2分。

`git reset --hard` + force push は使わない。公開履歴を壊し、Pagesのデプロイ履歴とも不整合になる。

---

## 19. 更新ファイル

すべて `website-v2` ブランチ上。`master` への変更は無い。

**新規作成(26ファイル):**

| 種別 | ファイル |
|---|---|
| ページ | `website-v2/index.html`、`features.html`、`pricing.html`、`company.html`、`contact.html`、`privacy.html`、`terms.html`、`404.html` |
| CSS | `website-v2/assets/css/tokens.css`、`base.css` |
| JS | `website-v2/assets/js/main.js` |
| ロゴ | `website-v2/assets/brand/` 11ファイル(SSOTからのコピー) |
| その他 | `website-v2/robots.txt`、`sitemap.xml`、`README.md`、`assets/images/.gitkeep` |
| 文書 | `docs/reviews/WEB_V2_1_CURRENT_SITE_AUDIT_AND_FOUNDATION.md`(本書) |

**変更・削除したファイル: なし。**

**リポジトリ外の変更(参考):** ローカル確認用に `C:\Users\user\Desktop\TOEICアプリ\.claude\launch.json` へプレビュー設定 `smartlabo-website-v2`(port 3008)を1件追加した。SmartLaboリポジトリ外のローカル設定であり、公開物には含まれない。

> **文書の配置について:** SmartLaboリポジトリに `docs/` は存在しなかったため、依頼どおり `docs/reviews/` を新規作成した。ただし当リポジトリの文書は `PROJECT_BIBLE/` に集約する運用(`PROJECT_BIBLE/60_Editorial_Workflow.md`)であり、**本書を `PROJECT_BIBLE/` 配下へ移すか、`docs/reviews/` を新しい置き場として正式化するかは代表の判断を仰ぎたい。**

---

## 20. Git情報

| 項目 | 値 |
|---|---|
| リポジトリ | `smartlabosukimagakusyuu-dev/SmartLabo` |
| 分岐元 | `master` @ `10463d6` |
| 作業ブランチ | `website-v2` |
| コミット | `WEB-V2-1: prepare website v2 development foundation` |
| リモートへのpush | **未実施** |
| `master` への変更 | **なし** |

**pushしていない理由:** 依頼の「Remoteへのpushが安全か不明な場合はpushせず、代表の承認を待つ」に従った。加えて、`gh` が未認証のためリモート側の保護設定・Pages設定を事前に確認できず、安全性を自分で確かめられない状態にある。`website-v2` ブランチのpush自体はワークフローのトリガ条件(`branches: [master]`)を満たさないため理論上は安全だが、**代表の承認を待つ。**

---

## 21. 残課題

### 現行サイトについて発見した課題(v2で解決すべきもの)

| # | 内容 | 影響 |
|---|---|---|
| 1 | **公開サイトのロゴがSSOTと一致していない。** `WEBSITE/img/logo/` の3ファイル(`_transparent` 系・`favicon.png`)がSSOTに存在しない派生物で、加工記録も残っていない | 中 |
| 2 | **キーボードフォーカスの可視表示が無い。** v1の `style.css` に `:focus-visible` のアウトライン指定が無く、Tab操作で現在位置が分からない | 中(アクセシビリティ) |
| 3 | **OGP画像が専用画像でない。** 1200×630でないhero背景を流用しており、SNS上で見切れる可能性 | 中 |
| 4 | **問い合わせフォームが停止中。** GitHub Pagesでは `xserver-form/` のPHPを実行できないため、方式の決定が必要 | 高 |
| 5 | **計測が一切無い。** GA4未導入・Search Console未登録のため、公開後の効果測定ができない | 高(要代表判断) |
| 6 | `sitemap.xml` に `privacy.html` / `terms.html` が未登録 | 低 |
| 7 | 構造化データが `index.html` のみ。他ページにパンくず等が無い | 低 |
| 8 | ロゴPNGが最大215KBでWebP版が未用意。表示速度に不利 | 低 |
| 9 | ブレークポイントが7種混在(1100/1080/960/860/760/640/520) | 低(v2では4段に統一済み) |
| 10 | Core Web Vitals 未計測 | 低 |

### v2側の未決事項(代表の判断が必要)

| # | 内容 |
|---|---|
| A | `product.html` / `real-estate.html` をv2で維持するか廃止するか。廃止する場合、GitHub Pagesは301を設定できないためSEO上の影響を要確認 |
| B | 問い合わせ方式(外部フォームサービス / Xserverへのクロスオリジンpost / メール継続) |
| C | GA4・Search Console を導入するか。導入する場合、`privacy.html` の更新とCookie同意バナーが前提 |
| D | `company.html` に所在地・電話番号を掲載するか(v1では2026-07-16に削除している) |
| E | 本書の置き場所(`docs/reviews/` か `PROJECT_BIBLE/` か) |
| F | `website-v2` ブランチをリモートへpushしてよいか |

---

## 22. WEB-V2-1 合否

**合格。**

| 確認項目 | 結果 |
|---|---|
| 現在の公開サイトが変化していない | **OK**(`WEBSITE/` `git status` 変更なし。`master` は `10463d6` のまま) |
| 公開URLが正常に表示される | **OK**(独自ドメイン・github.io ともHTTP 200) |
| Version 2がローカルだけで確認できる | **OK**(`http://localhost:3008` で全ページ表示を実測) |
| 現行サイトとファイルが混ざっていない | **OK**(v2は `website-v2/` 配下に閉じている) |
| 正式ロゴを使用している | **OK**(SSOTからのコピー。MD5一致を確認。縦横比維持を実測) |
| 秘密情報が含まれていない | **OK**(計測ID・APIキー・個人情報なし。本書にも未記載) |
| レスポンシブ基盤がある | **OK**(4段のブレークポイント。375px幅で横スクロール無しを実測) |
| Git差分がVersion 2関連だけ | **OK**(`website-v2/` と `docs/reviews/` のみ) |
| ロールバック可能 | **OK**(切替前はブランチ削除、切替後は `git revert`) |

### ローカル検証の実測結果

`http://localhost:3008`(`npx serve website-v2`)で確認:

- コンソールエラー **0件**、読み込み失敗画像 **0件**、リンク切れ **0件**(HTML内の全ローカル参照13件の実在を確認)
- デザイントークンの解決を確認(`--c-blue` → `#2563EB`、CTAボタンの実測背景色 `rgb(37, 99, 235)`)
- ロゴ縦横比の維持を確認(原寸 531×180 → 表示 118×40)
- `h1` はページごとに1つ
- キーボードフォーカスリング(3px)の表示を実測。濃色背景上でAccent Blue `#00D4FF` へ切替わることを確認
- スキップリンクがTab 1回目で出現することを確認
- 375×812(モバイル)で横スクロール無し、ナビのドロワー開閉と `aria-expanded` の同期、フッター1カラム化を確認
- 1280×720(デスクトップ)でナビ横並び、フッター4カラム、コンテナ1200px上限を確認

### 検証中に発見し、修正した不具合(v2側)

| 内容 | 対応 |
|---|---|
| JSがビューポート判定でナビの `hidden` を制御していたため、メディアクエリの変化を取りこぼすとデスクトップでナビが消える | 表示可否の最終判断をCSSへ移した(`@media (min-width: 861px) { .site-nav[hidden] { display: flex; } }`)。JSが失敗してもナビは消えない |
| 現在ページの `aria-current` 判定がファイル名の完全一致だったため、クリーンURL(`/features`)を返す環境で機能しなかった | 拡張子を落として比較するよう修正。`/features` でも `/features.html` でも一致することを実測 |

---

## 23. WEB-V2-2 推奨内容

**「トップページ本実装 — ヘッダー・ヒーロー・悩み・解決・業務フロー」**

### 推奨する作業範囲

`index.html` のセクション 1〜6(ヘッダー / ヒーロー / 企業の悩み / Smart Labo Worksによる解決 / 導入後の業務フロー / 導入前と導入後)を実装する。機能の羅列ではなく、**「導入すると仕事がどう変わるか」**を軸に構成する。

7〜15(主要機能・システム画面・選ばれる理由・料金・導入の流れ・FAQ・会社紹介・最終CTA)はWEB-V2-3へ。1回のステップで詰め込まず、レビュー可能な単位に分ける。

### 着手前に確定が必要なもの

- 上記21節「v2側の未決事項」の **A(product/real-estateの扱い)** — トップページのナビ構成に直結する
- ヒーローのビジュアル素材(v1のhero背景を流用するか、新規に用意するか)

### WEB-V2-2で併せて対応すべき項目

- **OGP専用画像(1200×630)の作成**(残課題#3)。正式ロゴを加工せずに配置する
- ロゴPNGのWebP併用(`<picture>` での出し分け。残課題#8)
- `privacy.html` / `terms.html` の本文をv1から移植(**法務文言は書き換えない**)

### 引き続き守る制約

- `WEBSITE/` / `.github/workflows/pages.yml` / `CNAME` / `DESIGN_ASSETS/` を変更しない
- `master` へコミットしない
- 架空の導入実績・企業ロゴ・根拠のない数値・未確定の契約条件を載せない
- 社内用語(内部プラットフォーム名称)を顧客向けページに出さない
- 計測ID・APIキー・個人情報をコミットしない
- 新しいブランドカラー・新しいブレークポイントを追加しない

---

*作成: 2026-07-28 / WEB-V2-1*
