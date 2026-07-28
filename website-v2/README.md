# website-v2/ — Smart Labo Works 公式サイト Version 2.0(開発中・未公開)

このフォルダは**公開されていません**。現行の公開サイトは `WEBSITE/` です。

- 作成: WEB-V2-1(2026-07-28)
- ブランチ: `website-v2`(本番ブランチは `master`)
- 状態: 土台のみ。トップページ本実装は WEB-V2-2 以降

---

## 1. なぜ本番に影響しないのか

公開は `.github/workflows/pages.yml` の GitHub Actions が行っている。トリガ条件は次の2つで、**両方を満たさない限りデプロイは起動しない**。

```yaml
on:
  push:
    branches: [master]
    paths:
      - "WEBSITE/**"
      - ".github/workflows/pages.yml"
```

このフォルダは二重に条件から外れている。

1. **ブランチが違う** — 作業は `website-v2` ブランチ。`master` へのpushではないためワークフローは起動しない。
2. **パスが違う** — 仮に誤って `master` へマージしても、`website-v2/**` は `WEBSITE/**` にマッチしないためデプロイは起動しない。

さらに、デプロイされる成果物は `path: "WEBSITE"` で指定されたフォルダのみ。`website-v2/` はアーティファクトに含まれない。

**触ってはいけないもの(v2作業中):**

| 対象 | 理由 |
|---|---|
| `WEBSITE/` 配下すべて | 公開中の本番ファイル |
| `.github/workflows/pages.yml` | 変更するとpath条件に関わらずデプロイが走る |
| `WEBSITE/CNAME`(`smartlaboworks.com`) | 消えると独自ドメインが即座に切れる |
| `DESIGN_ASSETS/01_LOGO/` | 正式ロゴのSSOT。読み取り専用 |

---

## 2. ローカルでの確認方法

ビルド不要の静的サイトのため、ファイルを直接開くだけでも表示できるが、
相対パス・`fetch` 等の挙動を本番に合わせるためローカルサーバー経由を推奨する。

```bash
cd C:/Users/user/Desktop/SmartLabo/website-v2 && python -m http.server 5500
```

`http://localhost:5500/` で確認する。ポートは他の開発サーバーと衝突しない値に変えてよい。

---

## 3. 技術構成

**素の HTML / CSS / JavaScript。ビルドステップなし。**

React・Next.js・Vite 等は採用しない。理由:

- 現行の公開方式は `actions/upload-pages-artifact` が `WEBSITE/` を**そのままアップロードする**構成で、ビルドステップが存在しない。フレームワークを入れると `pages.yml` の改修が必須になり、本番のデプロイ経路そのものを触ることになる。
- v1 も素の HTML/CSS/JS(`WEBSITE/js/*.js`)で構築されており、v2 で技術を変える必然性がない。
- 本サイトは静的なマーケティングサイトであり、SPA が必要な動的状態を持たない。表示速度・Core Web Vitals の観点でも、ランタイムJSが少ないほうが有利。

外部CDN(フォント・アイコン・JSライブラリ)への依存も追加しない。

---

## 4. フォルダ構成

```
website-v2/
├── index.html        トップページ(セクション骨格のみ)
├── features.html     機能
├── pricing.html      料金
├── company.html      会社情報
├── contact.html      お問い合わせ
├── privacy.html      プライバシーポリシー
├── terms.html        利用規約
├── 404.html          Not Found(noindex)
├── robots.txt
├── sitemap.xml
├── README.md
└── assets/
    ├── css/
    │   ├── tokens.css   デザイントークン(色・余白・角丸・影・タイポ・ロゴ高さ)
    │   └── base.css     reset・レイアウト・ボタン・ヘッダー・フッター・フォーカス表示
    ├── js/
    │   └── main.js      モバイルナビ開閉・現在ページ強調のみ
    ├── images/          ページ用画像(未配置)
    └── brand/           正式ロゴ(DESIGN_ASSETS/01_LOGO からのコピー)
```

---

## 5. 正式ロゴの取扱い

`assets/brand/` の11ファイルは `DESIGN_ASSETS/01_LOGO/` からの**バイト単位で同一のコピー**(MD5一致を確認済み)。ファイル名のみ用途が分かる形へ統一している。

| v2 のファイル | コピー元(SSOT) | 用途 |
|---|---|---|
| `logo-primary.png` / `.webp` | `Primary/smartlabo_logo_primary.*` | 明色背景のロゴ(ヘッダー・資料) |
| `logo-white.png` / `.webp` | `White/smartlabo_logo_white_lockup.*` | 濃色(Navy)背景のロゴ |
| `icon-color.png` / `.webp` | `Icon/smartlabo_icon_color.*` | アイコン単体(カラー) |
| `icon-white.png` / `.webp` | `Icon/smartlabo_icon_white.*` | アイコン単体(白抜き) |
| `favicon-512.png` / `.webp` | `Favicon/smartlabo_favicon_512.*` | ファビコン |
| `app-icon-1024.png` | `Favicon/smartlabo_app_icon_1024.png` | アプリアイコン |

**禁止事項(CEO指示 2026-07-04 / `DESIGN_ASSETS/01_LOGO/README.md`):**

- 再生成・トレース・色変更・加工・回転・要素の分離
- 縦横比の変更(`width` と `height` の同時指定、`object-fit` での引き伸ばし)
- CSS の `filter` / `mix-blend-mode` による色の変更

**実装ルール:** 高さのみ指定し、幅は `auto`。`tokens.css` の `--logo-h-header` / `--logo-h-footer` / `--logo-h-hero` を使う。

```html
<img class="logo__img" src="assets/brand/logo-white.png" alt="Smart Labo Works">
```

真のベクター(SVG)データは未提供。大判印刷用途が発生した場合は代表へ確認する。

---

## 6. デザイン基盤のルール

- 色は `tokens.css` の変数のみを使う。**新しいブランドカラーを追加しない。** 必要な場合は `PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md` → `BRAND/colors/palette.md` を先に更新する。
- ブレークポイントは **1080 / 860 / 640 / 520** の4段に統一する(v1 は 1100/1080/960/860/760/640/520 が混在していた)。新しい段を増やさない。
- 最大コンテンツ幅は `--content-max: 1200px`、本文中心のページは `--content-narrow: 760px`。
- キーボードフォーカスは全ページで可視にする(`:focus-visible` の3pxリング)。**`outline: none` を単独で書かない。** v1 にはこの表示が無く、Tab操作で現在位置が分からない状態だった。

---

## 7. 掲載してはいけない内容

- 架空の導入実績・導入企業ロゴ
- 根拠のない削減時間・改善率などの数値
- 未確定の契約条件・料金
- **「Company OS」の名称**(社内用語。顧客向けサイトには出さない)
- 計測ID・APIキー・認証情報・個人情報

システム画面のスクリーンショットを載せる場合、実在企業名と衝突しない記号表記のダミーデータのみを使う(v1 の対応 `10463d6` を踏襲)。

---

## 8. 本番切替手順(代表の承認後にのみ実行)

v2 が完成し、代表の承認を得てから実行する。切替は「`WEBSITE/` の中身を v2 で置き換える1コミット」に集約する。

1. `website-v2` ブランチで最終確認する(全ページ表示・リンク切れ・レスポンシブ・ロゴ)。
2. **`index.html` 他すべてのページから `dev-banner` の行を削除する。**
3. `master` を最新化し、切替用ブランチを切る。
   ```bash
   cd C:/Users/user/Desktop/SmartLabo && git checkout master && git pull && git checkout -b release/website-v2
   ```
4. `WEBSITE/` の中身を v2 で置き換える。このとき**以下は必ず残す**。
   - `WEBSITE/CNAME`(`smartlaboworks.com`)— 消えると独自ドメインが切れる
   - `WEBSITE/app.html` — デモ画面。全ページから参照されている
   - v2 に対応ページが無い v1 のURL(`product.html` / `real-estate.html`)は、
     **削除するか残すかを代表に確認する。** 削除する場合は 301 の代替が
     GitHub Pages では設定できないため、`sitemap.xml` の更新と
     Search Console での確認が必要。
5. 差分を確認し、`WEBSITE/` 以外に変更が無いことを確かめてからコミット・push。
6. GitHub Actions の `Deploy Homepage to GitHub Pages` が成功することを確認する。
7. `https://smartlaboworks.com/` を実ブラウザで確認する(HTTPS・favicon・全ページ)。

---

## 9. ロールバック手順

デプロイ後に問題が判明した場合。

```bash
cd C:/Users/user/Desktop/SmartLabo && git checkout master && git revert --no-edit <切替コミットのSHA> && git push origin master
```

`WEBSITE/` が変更されるため Actions が再実行され、v1 の内容で再デプロイされる。所要は通常1〜2分。

`git revert` を使い、`git reset --hard` + force push は使わない。force push は公開履歴を壊し、Pages のデプロイ履歴とも不整合になる。

即時に切り戻したい場合は、GitHub の Actions 画面から**v1最終デプロイの run を Re-run** する方法もあるが、リポジトリの内容は v2 のままになるため、必ず `git revert` も併せて行う。

---

*最終更新: 2026-07-28(WEB-V2-1)*
