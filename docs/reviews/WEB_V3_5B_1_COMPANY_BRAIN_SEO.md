# WEB-V3-5B-1: Company Brain SEOランディングページ新設

実施日: 2026-08-08
ブランチ: `feature/web-v3-5b-company-brain-seo`（基点: origin/website-v3 = fdbec24）
状態: **本番未公開**（master未マージ・WEBSITE/** 未変更・Pages未発火）

---

## 1. 目的

デザイン追加ではなく、WEB集客経路の新設。

```
Google検索（社内資料 AI検索 等）
→ company-brain.html
→ Smart Labo Works 全体価値の理解
→ 資料請求（?type=docs）/ 無料相談（?type=consult）
→ 既存Contact API
→ generate_lead（既存実装・変更なし）
```

## 2. 狙う検索意図

- 社内資料 AI検索 / 社内マニュアル AI / 社内規程 AI検索 / 社内ナレッジ AI / 社内文書 AI検索
- 検索者の状態:「資料が見つからない」「規程の場所が分からない」「探すのに時間がかかる」「社員によって知っている情報が違う」
- キーワードの機械的な羅列はしていない。h1は用途（社内資料を探す時間を、AIとの会話に。）、
  title・meta description・本文の各セクションが上記の検索語を自然文で含む。

## 3. 新規・変更ファイル

| ファイル | 種別 | 内容 |
|---|---|---|
| `website-v3/company-brain.html` | 新規 | SEOランディング本体（下記構成） |
| `website-v3/sitemap.xml` | 変更 | 8URL → 9URL（company-brain.html を追加） |
| `website-v3/index.html` | 変更 | #brain 節末尾に内部リンク1行のみ追加 |
| `website-v3/features.html` | 変更 | Company Brain の featdetail 末尾に内部リンク1行のみ追加 |
| `docs/reviews/tools/check-prices.js` | 変更 | 検査対象に company-brain.html を追加（下記） |
| `docs/reviews/WEB_V3_5B_1_COMPANY_BRAIN_SEO.md` | 新規 | 本文書 |

既存ページの文章の書き換えは行っていない（リンク1行の追加のみ）。

## 4. ページ構成（既存デザインシステムのみで構築・新規CSS 0行）

| # | セクション | id | 再利用した既存部品 |
|---|---|---|---|
| 1 | Hero（3秒で用途理解） | page-hero | page-hero / breadcrumb / eyebrow |
| 2 | 導入前の悩み ↔ Company Brain | problem | ba（従来/導入後の対比パネル） |
| 3 | 使い方5ステップ | flow | brainflow（index #brain と同一の正式コピー） |
| 4 | 具体例（完全架空・金額は創作しない） | example | chain（あなた→Company Brain→回答） |
| 5 | 信頼性「勝手に答えない」 | trust | principle / badge-trust |
| 6 | Smart Labo Works 全体へ接続 | together | included（8機能＋features.htmlへのリンク） |
| 7 | 料金（Basicは基本料金に含む） | pricing | plan / data-price |
| 8 | CTA | cta | final-cta（inverse） |

ヘッダー・フッターは index.html と同一（check-prices の同一性検査で機械確認済み）。

## 5. SEO実装

- title: `社内資料・マニュアル・規程をAIで検索｜Company Brain — Smart Labo Works`
- meta description: 検索意図に対応した自然文（「あの資料どこだっけ？」起点）
- canonical: `https://smartlaboworks.com/company-brain.html`
- OGP / Twitter Card: 既存ページと同形式・og:image は既存の共通画像
- BreadcrumbList 構造化データ + 画面表示のパンくず（両方あり＝既存方針と整合）
- h1×1 / h2×7 の階層構造
- アクセシビリティ: skip-link / aria-label / aria-labelledby / aria-hidden(装飾SVG) / visually-hidden

## 6. Company Brain Basic 仕様との一致

掲載したのは本番実装（Basic）のみ:
質問（全利用者可）/ 登録資料を根拠に回答 / 根拠がなければ推測しない /
参照資料（references=実際に使用した根拠）表示 /
「次に確認すると良い資料」最大3件（related=DB実在候補からサーバー側で生成。AIが資料名を作らない旨を明記）/
登録は管理者 / PDF・Word・Excel・画像 / 会社ごとのテナント分離。

使い方5ステップは index.html #brain（WEB-V3-3で承認済みの本番コピー）と同一文言。

## 7. Plus混入 0

改善提案・古い規程検知・重複検知・不足資料提案・規程比較・成熟度分析・
教育AI・自動マニュアル作成・経営分析・自動学習 —— **可視コンテンツへの出現 0件**（実測）。
冒頭のHTMLガード注釈（「Plus候補のため書かない」）にのみ列挙（index.html等と同じ再発防止パターン）。
架空の導入実績・顧客数・削減率・レビュー: 0件（実測）。

## 8. 料金整合

- 正規値のみ掲載: 初期設定費10,000円 / 月額20,000円 / 追加3,000円（税別）
- 「Company Brainだけで月額2万円」と誤認させない構成:
  見出し自体を「Company Brain Basicは、基本料金に含まれます。」とし、
  リード文とplan__noteで全機能が基本料金に含まれることを明示
- data-price属性を付与し、`check-prices.js` の突き合わせ対象を4→5ページへ拡張
  （料金改定時に本ページだけ古い金額のまま残る事故を機械的に防止）
- キャンペーンは掲載しない（掲載許可ページは index / apply のみのため）

## 9. CTA

資料請求（優先・primary）→ `contact.html?type=docs#form` ×3箇所
無料相談 → `contact.html?type=consult#form` ×3箇所
既存Contact API・正式6種のまま。新しい問い合わせ方式・フォーム要素は追加していない
（`<form>`/`<input>`等 0件を実測。check-pricesのフォーム禁止検査対象にも組み込み済み）。

## 10. GTM / GA4 / PII

- GTM: 既存公式snippet（GTM-KKRD8BZR）を head + noscript で完全再利用。
  設置ページ総数 9→10（実測で10ファイルのみ・二重設置なし）
- GA4直接実装（gtag.js / G-CCSLHTF6R2）: 0件（経路はGTM経由のみ・二重計測なし）
- 新規dataLayerイベント: 0（閲覧用の独自イベントは追加していない）
- dataLayer.push はリポジトリ内で従来どおり contact-form.js の1箇所のみ
- PII送出コード: 追加0。privacy §6（種別など非個人情報のみ計測に利用）と矛盾なし

## 11. テスト結果（すべて実測）

| 検査 | 結果 |
|---|---|
| check-prices（10ページ検査へ拡張後） | [OK] exit=0 |
| check-legal | [OK] exit=0 |
| 内部リンク・アセット | 13ページ / 455参照 / 未解決0 |
| sitemap | 9URL（転送ページ・signup・404の非掲載を維持） |
| robots | Allow:/ ・Sitemap行のみ（変更なし・整合） |
| dev-banner | 0件 |
| console error | 0件（company-brain / index / features） |
| desktop表示 | title/canonical/h1/OGP/パンくず/価格/CTA/画像欠落0 を確認 |
| mobile 375px | 横スクロール0・はみ出し要素0 |
| 回帰 | index・featuresのリンク各1件が機能・既存セクション無傷 |
| ヘッダー/フッター同一性 | check-prices §5でindex.htmlと一致を機械確認 |

## 12. 残存リスク

- (a) ナビゲーションの「Company Brain」は従来どおり index.html#brain を指す。
  専用ページへ変えるかは全ページ共通ヘッダーの変更になるため、今回は据え置き（別判断）。
- (b) スクリーンショット画像なし。既存アセットにCompany Brain画面のwebpが無く、
  創作を避けるためテキスト構成とした。実画面の撮影・追加は別工程で可能。
- (c) 公開後のSearch Consoleへのsitemap再送信・インデックス登録リクエストは
  本工程の範囲外（公開工程で実施）。
- (d) FAQ・構造化データ(FAQPage)は未実装。検索意図の網羅を広げる場合は
  実装済み機能の範囲でQ&Aを追加できる（別工程）。

## 13. 次工程 Go / No-Go

**Go**（WEB-V3-5B-RELEASE: website-v3統合 → WEBSITE/反映 → master公開候補 → 代表承認 → push）。
公開手順は WEB-V3-5A-RELEASE と同一方式を再利用する。
公開後: Search Console でsitemap再送信・URL検査（代表またはリリース工程で実施）。
