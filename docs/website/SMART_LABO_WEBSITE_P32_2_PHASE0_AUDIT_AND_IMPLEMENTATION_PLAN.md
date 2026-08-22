# SMART LABO WEBSITE P32-2 PHASE 0 — 現行監査と実装計画

工程: **P32-2 Phase 0（監査・実装差分確定）**
実施日: 2026-08-22
種別: **docs only / コード変更0 / 本番変更0**

> ★**2026-08-22 P32-2 Phase 0-CLOSE で代表承認済み**。本書は正式Webリポジトリ
> `C:\Users\user\Desktop\SmartLabo` の `docs/website/` へ配置され、作業ブランチ
> `feature/website-renewal` でコミットされた。**master へは変更を加えていない。**
> 監査時点（配置前）の調査結果は**一切変更していない**。代表判断は §37 に追記した。

---

## 1. 結論（先に3点）

| # | 結論 |
|---|---|
| **1** | **正式Webリポジトリは `C:\Users\user\Desktop\smartlabo-works` ではなく `C:\Users\user\Desktop\SmartLabo`**（remote `smartlabosukimagakusyuu-dev/SmartLabo`・本番ブランチ `master`）。本番 `smartlaboworks.com` は同リポジトリの `WEBSITE/` を GitHub Actions（`paths: WEBSITE/**`）で配信している。**指示のSTOP条件に該当**するため、docsの追加・commitは行わず承認を待つ |
| **2** | **GTM・GA4は現在サイトから撤去済み**（WEB-SALES-8L・2026-08-11）。`privacy.html §6` は「アクセス解析ツールを使用していません」と**明示公開中**。指示書の前提（GTM-KKRD8BZR稼働・generate_lead発火）は**現状と一致しない**。再導入は `docs/website/ANALYTICS_REINTRODUCTION.md` の条件充足＋プライバシー改定が前提＝**実装ブロッカー** |
| **3** | **HP制作の価格・提供範囲にSSOTが存在しない**（`PRICE SSOT REQUIRED`）。一方 Salon営業SSOTは「『HP制作』単独の表現はSalon標準商品では使わない／独自ドメインHPは別サービス」と定めており、**HP制作ページの商品定義は代表判断が必要** |

---

## 2. PRE-CHECK

### smartlabo-works（指示の候補）

| # | 項目 | 実測 |
|---|---|---|
| 1 | ディレクトリ | 実在 |
| 2 | git | あり |
| 3 | branch | `feature/product-1-dashboard` |
| 4 | HEAD | `721a6f2` |
| 5 | remote | `smartlabosukimagakusyuu-dev/smartlabo-works` |
| 7 | `git status --porcelain` | **2行（代表の未commit変更あり）** → 触れていない |
| 14 | Webコードの所在 | **無し**（`WEBSITE/` も `pages.yml` も存在しない。中身はAI業務支援プラットフォームの製品リポジトリ） |

### SmartLabo（実際の公式サイト）

| # | 項目 | 実測 |
|---|---|---|
| 1 | ディレクトリ | 実在 |
| 3 | branch | `master` |
| 4 | HEAD | `d3c6054c99a0df7d15d6fccec08ab18495d8d463` |
| 5 | remote | `smartlabosukimagakusyuu-dev/SmartLabo` |
| 7 | `git status --porcelain` | **0行（clean）** |
| 14 | Webコード | `WEBSITE/`（HTML 12・CSS 3・ブランド画像・robots・sitemap・CNAME） |
| 15 | 本番公開元の根拠 | `.github/workflows/pages.yml`：`branches:[master]` **かつ** `paths:["WEBSITE/**", ".github/workflows/pages.yml"]`。ビルドなしで `WEBSITE/` をそのまま配信。`WEBSITE/CNAME` = `smartlaboworks.com` |

### smartlabo-salon（READ-ONLY）

| # | 項目 | 実測 |
|---|---|---|
| 16 | branch | `feature/salon-foundation` ✔ |
| 17 | HEAD | `6304299d61c10ade7f52a9c7dfb8214bdf7d0837`（期待値一致）✔ |
| 18 | origin | 同上（一致）✔ |
| 19 | status | **0行（clean）** ✔ |
| 20 | P32-IMG-2 docs | `SALON_P32_IMG_1_VISUAL_ASSET_DESIGN.md` / `SALON_P32_IMG_2_VISUAL_ASSET_IMPORT_VERIFICATION.md` 存在 ✔ |

★Salonリポジトリは**1バイトも変更していない**。

### 仕様・素材

| # | 項目 | 実測 |
|---|---|---|
| 21 | 仕様書の所在 | `C:\Users\user\Downloads\SMART_LABO_WEBSITE_RENEWAL_SPEC_20260822.md`（**第三候補**。`(1)` 付きは存在せず、**競合なし**） |
| 22 | SHA-256 | `bc502609bce86a38874122020a63acc8fab015b1ae0efa39060206dadf987cf9`（23,232 B / 916行） |
| 23 | P32-IMG-2パッケージ | `SmartLabo_Artifacts\SMART_LABO_SALON_P32_IMG_2_ASSET_PACKAGE_20260822\` 実在 |
| 24 | 画像マニフェスト | `P32-IMG-2\P32-IMG-2_ASSET_MANIFEST.md`（生成手段・権利条件を記録） |
| 25 | 必須7枚 | `upload-ready\` に7枚 ✔ |

## 3. 正式なWebコードの所在（STOP事項）

```text
公式サイト   : https://smartlaboworks.com/
リポジトリ   : C:\Users\user\Desktop\SmartLabo   （remote: SmartLabo / branch: master）
公開ディレクトリ: WEBSITE/
公開の仕組み : GitHub Actions（pages.yml）／ビルドなし／paths = WEBSITE/**
ドメイン保持 : WEBSITE/CNAME（★WEBSITE/を入れ替えるとCNAMEが消えてドメインが切れる）
```

指示の「Web公式リポジトリ候補 = smartlabo-works」は**誤り**。
指示の STOP 条件（別リポジトリが正式であると判明した場合）に従い、**変更・commitはしていない**。

## 4. 本番とリポジトリの対応

`master` HEAD の **commit済みバイト列**と本番配信HTMLを照合した（5ページ）。

| ページ | live SHA-256(先頭16) | blob SHA-256(先頭16) | 判定 |
|---|---|---|---|
| index.html | `2e55e4d56d5d12f4` | `2e55e4d56d5d12f4` | **一致** |
| contact.html | `56ccfcde9401e014` | `56ccfcde9401e014` | **一致** |
| pricing.html | `f27391ec856ee0e8` | `f27391ec856ee0e8` | **一致** |
| apply.html | `c883c683f585bd0e` | `c883c683f585bd0e` | **一致** |
| company.html | `574666350924693c` | `574666350924693c` | **一致** |

★**作業コピーのファイルとは一致しない**（Windows チェックアウトが CRLF のため）。
比較は必ず `git show HEAD:WEBSITE/<file>` の**blob**で行う。

## 5. 現行技術構成

| 項目 | 実測 |
|---|---|
| 技術 | 素のHTML / CSS / JS（フレームワークなし・**buildステップなし**） |
| CSS | `assets/css/tokens.css` / `base.css` / `components.css`（3層・デザイントークンあり） |
| JS | `assets/js/contact-form.js` ほか |
| 共通ヘッダー/フッター | **各HTMLへ直書き**（インクルード機構なし＝12ファイルへ同じ変更を反映する必要がある） |
| レスポンシブ | CSSメディアクエリ |
| 画像 | `assets/brand/`（ロゴ・favicon・app icon）。`assets/images/` は `.gitkeep` のみ＝**写真素材0** |
| deploy | GitHub Pages（Actions）。**rollback = `git revert -m 1 <マージcommit>`**（過去の切替はマージcommitで実施） |
| ホスティング補助 | 問い合わせのみ外部（XServer `form.smartlaboworks.com`） |

## 6. 現行ページ・URL一覧（公開実測：全て200／存在しないURLは404）

| # | 公開URL | ファイル | 目的 | 主CTA | GTM | 問い合わせ導線 |
|---|---|---|---|---|---|---|
| 1 | `/` | `index.html` | Works TOP | 無料相談 / 申し込み | **0** | `contact.html?type=consult` |
| 2 | `/features.html` | `features.html` | Works機能 | 無料相談 | 0 | 同上 |
| 3 | `/pricing.html` | `pricing.html` | Works料金 | 申し込み | 0 | 同上 |
| 4 | `/apply.html` | `apply.html` | 申込（**セルフ申込フォーム**） | 申し込み | 0 | `lite.smartlaboworks.com/api` |
| 5 | `/company.html` | `company.html` | 会社情報 | 問い合わせ | 0 | contact |
| 6 | `/contact.html` | `contact.html` | 問い合わせ | 送信 | 0 | `form.smartlaboworks.com/contact.php` |
| 7 | `/company-brain.html` | `company-brain.html` | Company Brain SEO | 資料請求/相談 | 0 | `?type=docs` |
| 8 | `/privacy.html` | `privacy.html` | プライバシー | — | 0 | — |
| 9 | `/terms.html` | `terms.html` | 利用規約 | — | 0 | — |
| 10 | `/product.html` | `product.html` | **旧URL転送**（canonical→features） | — | 0 | — |
| 11 | `/real-estate.html` | `real-estate.html` | **旧URL転送**（canonical→/） | — | 0 | — |
| 12 | `/404.html` | `404.html` | 404 | — | 0 | — |
| — | `/robots.txt` | — | 公開・Sitemap宣言 | — | — | — |
| — | `/sitemap.xml` | — | **9URL**（10・11・404を含まない） | — | — | — |

内部リンク集中度：`index`111 / `contact`63 / `apply`27 / `privacy`24 / `company`22 / `terms`21 / `pricing`16 / `features`16 / `company-brain`2。
問い合わせ導線の実測：`?type=consult` **22箇所** / `?type=docs` **15箇所**（他4種はページから直接リンクされていない）。

## 7. ページ別SEO監査

| ページ | title | canonical | H1 | OGP | 構造化データ | 刷新後の扱い |
|---|---|---|---|---|---|---|
| `/` | Smart Labo Works — 会社を動かすAI。 | `https://smartlaboworks.com/` | 会社を動かすAI。 | og:title/desc/url/type/image/locale | **Organization + WebSite** | **改修**（会社TOPへ／Works色を薄め、Salonを主役に） |
| `/features.html` | Smart Labo Worksの機能… | 自URL | 日々の業務を、… | og一式（imageなし） | BreadcrumbList | **維持**（Works機能ページとして保護） |
| `/pricing.html` | 料金｜Smart Labo Works ライト | 自URL | 小さく始められる、… | og一式 | BreadcrumbList | **維持**（Works料金） |
| `/apply.html` | お申し込み｜Smart Labo Works | 自URL | Smart Labo Works | og一式 | — | **維持**（セルフ申込は改変しない） |
| `/company.html` | 会社情報｜株式会社スマートラボ | 自URL | AIで、… | og一式 | — | **改修**（会社・代表ページとして刷新仕様へ接続） |
| `/contact.html` | お問い合わせ — Smart Labo Works | 自URL | お問い合わせ | og一式 | — | **改修**（CTA種別の対応表に合わせて選択肢の見せ方のみ調整。**API仕様は変更しない**） |
| `/company-brain.html` | 社内資料…Company Brain | 自URL | 社内資料を探す時間を、 | og一式 | BreadcrumbList | **維持**（SEO資産。Company Brainは**Works配下の機能名**として既に公開済み） |
| `/privacy.html` | プライバシーポリシー | 自URL | プライバシーポリシー | og一式 | — | **改修必須**（解析再導入時のみ。現状は「解析なし」で正） |
| `/terms.html` | 利用規約 | 自URL | 利用規約 | og一式 | — | 維持 |
| `/product.html` | ページの移動 | **features.html** | ページの場所が変わりました | — | — | 維持（旧URL保護） |
| `/real-estate.html` | ページの移動 | **/** | 同上 | — | — | 維持（旧URL保護） |
| `/404.html` | ページが見つかりません | なし | — | — | — | 維持 |

**SEO上の注意**
- `sitemap.xml` は9URL。刷新で新設するURLは**公開後に追加**する（未公開URLを載せない）
- 旧URL（`product.html` / `real-estate.html`）は**canonical＋転送ページ**で保護済み。**削除しない**
- `robots.txt` は `Allow: /`。新規ページを段階公開する場合の除外方針は Phase 5 で決める

## 8. Works保護方針

| # | 監査項目 | 実測 | 方針 |
|---|---|---|---|
| 1 | WorksページURL | `/`・`/features.html`・`/pricing.html`・`/apply.html`・`/company-brain.html` | **URL維持** |
| 2 | コピー | 「会社を動かすAI。」 | **維持**（仕様書§11.2と一致） |
| 3 | 料金表示 | 初期設定費10,000円／基本料金20,000円/月／追加アカウント3,000円/月 | **維持**（§15でSSOT一致を確認） |
| 4 | 申込 | `apply.html` のセルフ申込フォーム（`lite.smartlaboworks.com/api`） | **改変しない** |
| 5 | CTA | 無料相談／資料請求／申し込み | 維持。会社TOP改修時も**Worksへの導線を残す** |
| 6 | SEO | title/canonical/OGP/Breadcrumb 整備済み | 維持 |
| 7 | 会社TOPからの導線 | 現状はTOP自体がWorks | **改修**：TOPを会社TOP化し、`Smart Labo Worksを見る` を明示導線として残す |
| 8 | Company OS表記 | **0件**（顧客向けページに混入なし） | 現状維持でよい |
| 9 | 最新SSOTとの不一致 | 検出なし | — |

**分類**：`/features.html` `/pricing.html` `/apply.html` `/company-brain.html` = **そのまま維持**。
`/`（現Works TOP）= **会社TOPへ改修**し、Worksコンテンツは各ページへ委譲。
★**WorksをSalonへ置換しない／美容室向け商品に見せない**を Phase 2 の受入条件にする。

## 9. 問い合わせ保護方針（最重要保護対象）

| # | 監査項目 | 実測 |
|---|---|---|
| 1 | `contact.html` | `action="https://form.smartlaboworks.com/contact.php"`（絶対URL） |
| 2 | API | `contact-api/public/contact.php` ＋ `csrf-token.php` ＋ `lib/validate.php` |
| 3 | 設定 | `contact-api/private/contact-config.php`（**`.gitignore` 済み**・`contact-config.example.php` のみ追跡） |
| 5 | HTTPメソッド | **POST以外を拒否**（`Allow: POST`） |
| 4/9 | Origin/CORS | `Access-Control-Allow-Origin` を許可Originにのみ返す。`OPTIONS` 対応・`Max-Age: 600` |
| 6 | type allowlist | `SLW_TYPES` によるallowlist。**未登録typeは422**。`contact.html` の option と1対1 |
| 7 | validation | 必須・形式・長さの検査（type未選択は `required`） |
| 10 | bot対策 | ハニーポット `website` ＋ `form_ts` ＋ CSRFトークン（`csrf-token.php` から取得） |
| 14 | 個人情報 | 本文にIPハッシュを付与（生IPを保存しない設計） |
| 17 | `generate_lead` | **撤去済み**（`contact-form.js` にコメントで明記） |

**方針：この工程および Phase 1〜5 では `contact-api` と `contact.html` の送信仕様を変更しない。**
CTA名称・導線だけを増やし、**既存6種のtypeへマッピング**する（§10）。

★秘密値は一切出力・記録していない。フォーム送信テストは**実施していない**。

## 10. CTAとcontact type対応案

### 案A：既存type内で完結（**推奨・API変更0**）

| 新CTA | type | 根拠 |
|---|---|---|
| 無料で3分デモを見る → 相談 | `demo` | 既存「デモ希望」に一致 |
| 自分の店舗で使えるか相談する | `consult` | 既存「導入相談」 |
| 店舗HP制作を相談する | `consult` | 相談として受ける。**識別は本文の自由記入で行う** |
| Smart Labo Works相談 | `consult` | 既存の主要導線（22箇所） |
| 資料請求 | `docs` | 既存（15箇所） |
| 提携・協業 | `partner` | 既存 |
| 採用 | `recruit` | 既存 |

**弱点**：Salon相談・HP制作相談・Works相談が**すべて `consult` に混ざる**（受信側で識別しづらい）。

### 案B：新typeを追加（`salon` / `website`）

- 追加箇所：`contact.html` の option、`validate.php` の `SLW_TYPES`、**本番 contact-api の再デプロイ**
- 利点：受信メールの件名・集計で導線を分離できる
- 欠点：**本番PHPの再配置が必要**（代表作業）。Phase 4 のブロッカーになる

### 折衷案C（**実装順として推奨**）

1. Phase 2〜3 は**案A**で公開（API変更0・即日運用可）
2. 受信量が増えて識別が必要になった時点で**案B**を別工程として実施

★この工程では**追加していない**。

## 11. GTM・GA4 現状（★指示書の前提と異なる）

| # | 監査項目 | 実測 |
|---|---|---|
| 1 | GTM設置ページ | **0ページ**（12HTML すべて `googletagmanager` の記述なし） |
| 2/3 | head / noscript snippet | **なし** |
| 5 | GA4直接設置 | **なし**（`gtag(` 0件・`G-CCSLHTF6R2` 0件） |
| 6 | dataLayer実装 | **なし** |
| 7/8 | `generate_lead` / `lead_type` | **撤去済み**（`contact-form.js` に撤去の記録コメント） |
| 13 | 公開状態 | 本番HTMLにも `googletagmanager` **0件**（実測） |
| — | privacy開示 | §6「**現在、Google Analytics等のアクセス解析ツール…を使用していません**」と公開中 |
| — | 再導入条件 | `docs/website/ANALYTICS_REINTRODUCTION.md`（送信項目・送信先・利用目的・保存期間・オプトアウト手段の確定＋privacy改定＋Cookie同意の要否整理） |

**結論：計測は「再設置」ではなく「再導入（法務込み）」になる。** Phase 4 の独立ブロッカーとして扱う。

## 12. 新イベント設計案（実装しない・仕様のみ）

| イベント | 発火条件 | タイミング | 許可パラメータ | 禁止パラメータ | 重複防止キー | ページ |
|---|---|---|---|---|---|---|
| `salon_demo_start` | デモ開始ボタン押下 | click | `demo_version` | 氏名・店舗名・メール・電話・自由記入 | セッション内1回（`sessionStorage`） | `/salon/demo/` |
| `salon_demo_step` | 各STEP完了 | STEP遷移時 | `step`(1-4) | 同上 | step毎に1回 | 同上 |
| `salon_demo_complete` | STEP4到達 | 到達時 | `demo_version` | 同上 | セッション内1回 | 同上 |
| `select_consultation` | 相談CTA押下 | click | `cta_location` | 同上 | 30秒デバウンス | 全ページ |
| `select_website_consultation` | HP制作相談CTA押下 | click | `cta_location` | 同上 | 30秒デバウンス | `/website/salon/` 他 |
| `form_start` | フォーム最初の入力 | 初回input | `form_id` | 入力値そのもの | フォーム毎1回 | `/contact.html` |
| `generate_lead` | **送信成功時のみ**（`result=ok`） | 成功レスポンス後 | `lead_type`（6種のみ） | PII全般 | 送信成功毎1回 | `/contact.html` |

**共通規則**：UTMはURLパラメータのまま扱い、**個人情報をUTM・イベント値へ入れない**。
`generate_lead` は**成功判定後のみ**（送信ボタン押下では発火させない）。

## 13. SEO・URL移行計画

| URL | 分類 | 扱い |
|---|---|---|
| `/` | **現行維持・改修** | 会社TOP化。URLは変えない |
| `/features.html` | 現行維持 | Works機能 |
| `/pricing.html` | 現行維持 | Works料金 |
| `/apply.html` | 現行維持 | Works申込（改変しない） |
| `/company-brain.html` | 現行維持 | SEO資産 |
| `/company.html` | 現行維持・改修 | 会社・代表 |
| `/contact.html` | 現行維持・改修 | 共通問い合わせ |
| `/privacy.html` `/terms.html` `/404.html` | 現行維持 | — |
| `/product.html` `/real-estate.html` | 現行維持 | 旧URL転送。**削除しない** |
| `/salon/` | **新規（P0）** | Salon総合LP |
| `/salon/demo/` | **新規（P0）** | 3分デモ |
| `/website/salon/` | **新規（P0）** | 美容室HP制作 |
| `/works/` | **新規にしない** | 仕様書の `/works/` は**作らず現行URLを維持**（SEO資産保護・301不要） |
| `/automation/` `/salon/customer-record/` `/salon/repeat/` `/salon/line/` `/insights/` | P1（新規） | Phase 5以降 |
| `/nail/` `/esthetic/` `/seitai/` `/personal-gym/` | 将来 | 公開しない（誤認防止） |

**301リダイレクトは不要**（既存URLを1本も動かさないため）。
GitHub Pages は `.htaccess` を使えず、リダイレクトは**転送HTML**で行う既存方式を踏襲する。

★ディレクトリ型URL（`/salon/`）は `WEBSITE/salon/index.html` で実現できる（Pagesの標準動作）。

## 14. 商品・機能境界（Salon）

Salon側SSOT（`SALON_SALES_TERMS_SUMMARY_V1.md` §11/§12・`SALON_PRICE_PLAN_v1.md`）に従う。

| 区分 | 機能 | Webでの表現可否 |
|---|---|---|
| **提供済み** | 公開店舗ページ／24時間WEB予約受付／予約管理／来店管理／顧客管理／接客メモ（30秒メモ）／AI整理→スタッフ確認／AI顧客メモリ／来店前の接客準備／AI投稿案／AIキャンペーン案／再来店候補／メニュー・スタッフ・写真管理／経営者向け件数表示（PIN）／CSV書き出し | **現在形で書ける** |
| **デモのみ／実装済みだが販売前** | — | — |
| **未提供（書いてはいけない）** | 音声入力／LINE配信・一斉配信／メール配信／口コミ返信案／外部予約サイト自動連携／売上金額分析・AI経営分析／AI対話による予約受付／スタッフ個別ログイン（1店舗1アカウント運用） | **提供済みと書かない**。触れる場合は「今後対応予定」 |

★**仕様書§3.5 の機能一覧には未提供機能（音声入力・LINE配信・口コミ返信案・スタッフクイックモード等）が含まれる。**
Web実装時は Salon営業SSOT を上位とし、**§3.5 をそのまま機能一覧として転記しない**。

**禁止表現（Web共通）**：24時間AI受付／AIが予約を取る／AI使い放題／LINE自動配信／
Hot Pepper連携済み／売上AI分析・離脱予測・AI経営分析／HP制作（単独訴求としてSalonに含める表現）／
法務確認済み／効果数値・実績・口コミの捏造。

## 15. 価格SSOT確認結果

| 商品 | 正式価格 | SSOT | 判定 |
|---|---|---|---|
| **Salon 月額** | **14,800円（税別）**（創業メンバー） | `SALON_PRICE_PLAN_v1.md`（DECIDED）＋`SALON_SALES_TERMS_SUMMARY_V1.md` | **確定** |
| Salon 初期費用 | **0円** | 同上 | 確定 |
| Salon 最低契約期間 | **6か月**（満了後1か月自動更新） | 同上 | 確定 |
| Salon Standard | 19,800円（税別） | `SALON_PRICE_PLAN_v1.md` | 確定（**Webで併記するかは要判断**） |
| Salon Pro | 29,800〜34,800円 | 同上に「**将来決定・未確定**」と明記 | **公開しない** |
| **Works 初期設定費 / 基本料金 / 追加アカウント** | **10,000円 / 20,000円月 / 3,000円月**（税抜） | `PROJECT_BIBLE/14_Sales_And_Billing_Policy.md` | **確定**（公開中の `pricing.html` と一致） |
| **HP制作 基本料金** | — | **存在しない** | **PRICE SSOT REQUIRED** |
| **HP管理・保守費** | — | 存在しない | **PRICE SSOT REQUIRED** |
| **追加自動化・AI導入費** | — | 存在しない | **PRICE SSOT REQUIRED** |

★**未確定価格をサイトへ出さない。** HP制作ページは Phase 2 では**価格を出さず「相談」導線のみ**で実装するか、
価格SSOT確定を待つかを代表判断とする。

## 16. Salon画面素材計画

| 優先 | 画面 | 取得可否 |
|---|---|---|
| 1 | 本日の予約・AI TODAY（ホーム） | **実Salon画面から取得可能**（demo環境） |
| 2 | スマホ30秒メモ | 取得可能（375px） |
| 3 | AI整理・保存前確認 | 取得可能 |
| 4 | AI顧客メモリ（顧客カルテ） | 取得可能 |
| 5 | 接客準備（来店前AI） | 取得可能（**顧客カルテ経由**） |
| 6 | 次回来店・フォロー文章 | **一部**：AI投稿案／AIキャンペーン案は取得可。「フォロー文章」専用機能は**存在しない**→表現を実装に合わせる |
| 7 | WEB予約 | 取得可能（公開ページ） |
| 8 | 公開店舗ページ | 取得可能（写真投入済み） |

**素材化の条件**：実顧客データを使わない（demo DBのみ）／撮影は別工程／
画像の上に商品コピーを焼き込まない。

## 17. P32-IMG-2素材の使い方

| 素材 | Webでの用途 |
|---|---|
| `salon-soleil-hero.webp` | **公開店舗ページのスクリーンショット内**で見える形が主。単体で会社TOPのファーストビュー主役にはしない |
| `staff` ×3 | 公開店舗ページ／Salon LPの「店舗ページはこう作れる」節 |
| `menu` ×3 | 同上 |
| `sources/`（原寸PNG） | HPの帯・Instagram用（**リポジトリへは入れない**） |

★今回、画像を**Webリポジトリへ追加していない**。Phase 2 で必要になった時点で、
**WEBSITE/assets/images/ へ最小枚数**を追加するか、スクリーンショット内に含めるかを判断する。

## 18〜22. 各ページの実装対象

| ページ | 新規/改修 | 実装対象 | 非対象 |
|---|---|---|---|
| **会社TOP `/`** | 改修 | ファーストビュー（コピー＋Salon実画面）／Salon紹介／HP制作／Works導線／思想／導入の流れ／会社／最終CTA | 価格の新規掲載（Works価格は `pricing.html` へ委譲）／未提供機能 |
| **Salon LP `/salon/`** | 新規 | 中心メッセージ／課題／30秒入力／AI整理／来店前AI／機能（**提供済みのみ**）／モード説明／料金（14,800円）／導入の流れ／FAQ／CTA | LINE配信・音声入力・口コミ返信案の現在形表現／Pro価格 |
| **3分デモ `/salon/demo/`** | 新規 | 4STEP（来店前AI→30秒メモ→AI整理→次回支援）／固定サンプルデータ／CTA | 本番データ接続／外部送信／実AI呼び出し（**静的デモを第一候補**） |
| **HP制作 `/website/salon/`** | 新規 | 提供範囲・制作フロー・Salonとの関係・相談CTA | **価格**（SSOT未確定）／実績・事例の捏造 |
| **Works** | 維持 | 既存4ページを維持。TOPからの導線のみ追加 | 文言の作り替え・URL変更・申込フォーム改変 |

## 23. 共通コンポーネント候補

`WEBSITE/` には**インクルード機構が無い**（各HTMLへ直書き）。Phase 1 で次を決める。

| 候補 | 内容 | 留意点 |
|---|---|---|
| ヘッダー／フッター | 全ページ共通のナビ（会社／Salon／HP制作／Works／問い合わせ） | **12ファイルへ手動反映**になる。ビルド導入は `pages.yml` 改修＝本番デプロイ経路を触るため**採用しない** |
| CTAブロック | 主CTA／副CTA の共通パーツ | CSSクラス化で対応 |
| デザイン変数 | `tokens.css` へ Salon補助色（Soft Ivory / Mist Greige / Soft Sage / Dusty Beige / Deep Sage）を追加 | 既存 Smart Blue 系と衝突させない |
| フォント | Noto Sans JP / Inter | 追加読み込みは**表示速度**（Phase 5）で評価 |

## 24〜26. ファイル分類

**維持（変更しない）**
`features.html` / `pricing.html` / `apply.html` / `company-brain.html` / `terms.html` /
`product.html` / `real-estate.html` / `404.html` / `robots.txt` / `CNAME` /
`contact-api/**` / `signup-api/**`

**改修**
`index.html`（会社TOP化）／`company.html`／`contact.html`（表示のみ）／
`assets/css/tokens.css`・`base.css`・`components.css`／`sitemap.xml`（**公開後**に追記）／
`privacy.html`（**解析を再導入する場合のみ**）

**新設**
`WEBSITE/salon/index.html`／`WEBSITE/salon/demo/index.html`／`WEBSITE/website/salon/index.html`／
必要に応じ `WEBSITE/assets/js/salon-demo.js`・`WEBSITE/assets/images/*`

## 27. Phase別実装計画

| Phase | 目的 | 対象 | 依存 | テスト | 完了条件 | STOP条件 | 本番影響 | rollback | commit単位 |
|---|---|---|---|---|---|---|---|---|---|
| **1** | 情報設計・共通基盤 | `tokens.css`/`base.css`/`components.css`、ヘッダー・フッター設計、SEOメタ設計 | Phase 0承認 | 既存12ページの表示回帰・375px | 既存ページの見た目が壊れない | 既存CSSの破壊的変更が必要 | **なし**（`WEBSITE/`へpushすると即公開＝**master直pushしない**） | `git revert` | 1 |
| **2** | 会社TOP・Salon LP・HP制作 | `index.html` 改修、`salon/`・`website/salon/` 新設 | 1 | 表示・リンク・OGP・375px | 禁止表現0・未提供機能0 | 価格SSOT未確定の掲載が必要 | 同上 | 同上 | 3（ページ毎） |
| **3** | 3分デモ | `salon/demo/` | 2 | 4STEP動作・固定データ・外部送信0 | デモが実データへ接続しない | 実AI接続が必要になる | 同上 | 同上 | 1 |
| **4** | 問い合わせ・CTA・計測 | `contact.html` 表示調整、（別途）解析再導入 | 2,3 | 送信仕様の非回帰（**本番送信はしない**） | API仕様変更0 | 新type追加／解析再導入の法務未了 | contact-apiは触らない | 同上 | 1〜2 |
| **5** | SEO・アクセシビリティ・速度 | `sitemap.xml`、alt、構造化データ、画像最適化 | 2,3 | Lighthouse相当・リンク切れ0 | 公開URLのみsitemap掲載 | — | 同上 | 同上 | 1 |
| **6** | 本番切替前検証 | 全ページ最終監査 | 1-5 | 全URL 200／404正常／CNAME保持／禁止表現0 | 代表承認 | いずれか不合格 | なし | — | 0 |
| **7** | 本番切替 | `master` への反映 | 6承認 | 公開後の実測 | Actions success・全URL200・CNAME保持 | 失敗時は即revert | **あり** | `git revert -m 1 <merge>` | 1 |

**作業ブランチ方針（提案）**：Phase 1〜6 は **`master` 以外のブランチ**で行い、
`WEBSITE/` への変更が Actions を発火させない状態を保つ（過去のV2/V3と同じ考え方）。
★ただし**ブランチ作成は承認後**に行う（本工程では作っていない）。

## 28. 実装前ブロッカー

| # | ブロッカー | 影響Phase | 必要な判断・作業 |
|---|---|---|---|
| **B1** | **監査docsの配置先が未承認**（正式リポジトリが指示と異なる） | 0 | `Desktop\SmartLabo` の `docs/website/` へ置くか、別ブランチにするか |
| **B2** | **HP制作の価格・提供範囲SSOTが無い** | 2 | 価格を出さず相談導線のみで公開するか、SSOT確定を待つか |
| **B3** | **解析（GTM/GA4）は撤去済み・privacyで「使用していない」と公開中** | 4 | 再導入するか。する場合は送信項目/送信先/目的/保存期間/オプトアウトの確定＋privacy改定＋Cookie同意の要否整理 |
| **B4** | 新contact type（`salon`/`website`）を追加するか | 4 | 追加する場合は**本番contact-apiの再デプロイ（代表作業）** |
| **B5** | 仕様書§3.5の機能一覧に未提供機能が含まれる | 2 | Webでは Salon営業SSOT を上位とする（本書§14の方針で確定してよいか） |
| **B6** | Salon Standard 19,800円をWebに併記するか | 2 | 併記すると「創業メンバー14,800円」の位置づけ説明が必要 |

## 29. リスク

| # | リスク | 対策 |
|---|---|---|
| 1 | `WEBSITE/` を触ると **master push で即本番公開** | Phase 1〜6 は別ブランチ。master直pushしない |
| 2 | `WEBSITE/` の入れ替えで **CNAMEが消えドメインが切れる** | ファイル単位で追加・改修し、ディレクトリ置換をしない |
| 3 | PowerShellコピーによる **BOM混入** | 反映は `git cat-file blob` を使う（既知の教訓） |
| 4 | 未提供機能・未確定価格の掲載 | Phase 2/3 の受入条件に禁止表現の機械走査を入れる |
| 5 | 問い合わせ経路の破壊 | contact-api・contact.htmlの送信仕様を触らない |
| 6 | 共通ヘッダーの12ファイル手動反映漏れ | Phase 1 でチェックリスト化・機械走査 |

## 30. rollback方針

- 通常：`git revert <commit>`（Phase 7 のマージは `git revert -m 1 <merge commit>`）
- Pages は master の `WEBSITE/` を再デプロイするため、revert push で**旧版へ戻る**
- 切替前に `WEBSITE/` の実体退避を行う（過去は `SmartLabo_backup\WEBSITE-pre-v3-*` を作成）

## 31〜35. 本工程の変更

| 項目 | 実測 |
|---|---|
| 作成docs | 本書（**リポジトリ未配置**） |
| コード変更 | **0** |
| Salonリポジトリ変更 | **0** |
| 本番変更 | **0**（GET/HEADのみ。POST/PATCH/PUT/DELETE・フォーム送信は**していない**） |
| 画像追加 | 0 |
| 秘密値 | 出力・記録・commitとも**0** |
| commit / push | **未実施**（B1のため） |

---

## 36. 次工程の推奨

1. **B1の判断**：本書の配置先（`Desktop\SmartLabo` の `docs/website/` ／ ブランチ）
2. **B2・B5・B6の商品境界判断**
3. 判断後 **Phase 1（情報設計・共通基盤）** を別ブランチで開始
4. **B3（解析再導入）は Phase 4 の独立工程**として、法務要件から着手

---

## 37. 代表判断（2026-08-22・P32-2 Phase 0-CLOSE で追記）

> ★本節は**監査後に確定した代表判断の記録**である。§1〜§36 の調査結果は監査時点のまま変更していない。

### 37.1 承認事項

| # | 事項 | 決定 |
|---|---|---|
| 1 | 正式Webリポジトリ | **`Desktop\SmartLabo`**（承認） |
| 2 | 監査記録の配置 | 同リポジトリ **`docs/website/`** |
| 3 | 作業ブランチ | **`feature/website-renewal`**（master直コミット禁止） |
| 4 | 店舗ホームページ制作 価格 | **50,000円〜（税別）** |
| 5 | ホームページ管理・保守 価格 | **月額5,000円〜（税別）** |
| 6 | 問い合わせtype | **既存typeのみ使用・API変更0**（デモ=`demo`／Salon相談・HP制作相談・Works相談=`consult`／資料請求=`docs`） |
| 7 | GTM・GA4 | **今回は再導入しない**。`privacy.html` の「現在使用していない」表示を維持。再導入は Phase 4 から分離した**独立した将来工程** |
| 8 | Salon機能の掲載 | **Salonリポジトリの最新営業・商品SSOTを上位**とする。Web刷新構想書の機能候補をそのまま掲載しない。未提供機能を現在形で書かない |
| 9 | Salon料金 | 14,800円（税別）／初期費用0円／最低利用期間6か月を維持 |
| 10 | Works | 正式商品として維持。現行URL・価格・申込機能を保護し、Salonへ置換しない |

### 37.2 ブロッカーの状態

| # | 監査時点 | 現在 | 内容 |
|---|---|---|---|
| **B1** | 監査docsの配置先が未承認 | **解消** | 正式リポジトリ＝SmartLabo。ブランチ `feature/website-renewal` を使用 |
| **B2** | HP制作の価格SSOTが無い | **解消** | `docs/website/WEBSITE_PRODUCTION_AND_MAINTENANCE_PRICE_V1.md`（STATUS: APPROVED / VERSION 1）を制定 |
| **B3** | 解析が撤去済み・privacyで「使用していない」と公開中 | **方針確定** | **今回は再導入しない**。将来の独立工程で法務・プライバシー・Cookie・同意要件を確認してから判断 |
| **B4** | contact type追加の可否 | **方針確定** | **追加しない**。当面は既存6typeのみ。API変更0 |
| **B5** | 仕様書§3.5に未提供機能が含まれる | **解消** | Salon最新営業・商品SSOTを上位とする（§14の方針で確定） |
| **B6** | Salon Standard 19,800円の掲載可否 | **継続確認項目** | 機能差・販売条件・名称が最新SSOTで**完全に確定している場合のみ掲載候補**。現時点では**Standard掲載判断待ち＝公開しない** |

### 37.3 B6 の現況（本工程で確認した事実）

`SALON_PRICE_PLAN_v1.md`（DECIDED）の記載は次のとおり。

```text
創業メンバー 14,800円（税別）  Standard相当の機能を、先行導入店舗向けの価格で提供
Standard     19,800円（税別）  標準プラン
Pro          29,800〜34,800円  将来決定（未確定）
```

一方、営業SSOT `SALON_SALES_TERMS_SUMMARY_V1.md` は**単一プラン（14,800円）を前提**に
条件・含まれる機能・禁止表現を定めており、**Standard固有の販売条件
（最低契約期間・価格保証・含まれる機能の差分）は記載されていない**。

→ **「創業メンバー」と「Standard」の機能差が明文化されていない**ため、
**Standard 19,800円はWebへ掲載しない**（Standard掲載判断待ち）。
掲載する場合は、Salon側で機能差・販売条件・名称を確定した版を先に用意する。

### 37.4 価格掲載の適用ルール（Phase 2 以降）

| 商品 | Webへの掲載 |
|---|---|
| Salon 月額 | **14,800円（税別）・初期費用0円・最低利用期間6か月** を掲載してよい |
| Salon Standard / Pro | **掲載しない** |
| 店舗ホームページ制作 | **50,000円〜（税別）**（必ず「〜」と「（税別）」を付ける） |
| ホームページ管理・保守 | **月額5,000円〜（税別）**（同上） |
| Works | 現行 `pricing.html` の表記を維持（初期設定費10,000円／基本20,000円月／追加3,000円月・税抜） |
| 追加自動化・AI導入費 | **SSOT未確定のため掲載しない**（相談導線のみ） |

### 37.5 本工程で配置したファイル

| ファイル | 内容 |
|---|---|
| `docs/website/SMART_LABO_WEBSITE_P32_2_PHASE0_AUDIT_AND_IMPLEMENTATION_PLAN.md` | 本書（監査＋代表判断） |
| `docs/website/SMART_LABO_WEBSITE_RENEWAL_SPEC_20260822.md` | Web刷新統合仕様書（原本とSHA-256一致） |
| `docs/website/WEBSITE_PRODUCTION_AND_MAINTENANCE_PRICE_V1.md` | HP制作・管理の価格SSOT（新規制定） |

★`WEBSITE/` ・ `contact-api/` ・ `signup-api/` ・ `.github/workflows/` は**変更していない**。
`docs/**` の変更は `pages.yml` の `paths`（`WEBSITE/**` と `.github/workflows/pages.yml`）に
含まれないため、**GitHub Pages のデプロイは発火しない**。
