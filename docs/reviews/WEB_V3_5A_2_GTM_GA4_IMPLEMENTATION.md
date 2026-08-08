# WEB-V3-5A-2 — GTM本体実装＋GA4計測準備＋問い合わせ成功イベント実装

実施日：2026-08-08
ブランチ：`feature/web-v3-5a-analytics`
前工程：WEB-V3-5A-1（privacy.html §6 改定・check-legal.js 新基準化 = `0b3a5a8`）
前提：Search Console `smartlaboworks.com` ドメインプロパティ **所有権確認済み（2026-08-08）**

**本工程の時点で、本番・GTMコンテナはいずれも未公開。**
master へは merge/push していない。WEBSITE/** は変更していない。

---

## 1. 変更ファイル（10件）

| ファイル | 変更内容 |
|---|---|
| website-v3/index.html | GTM head snippet＋noscript＋注釈更新 |
| website-v3/features.html | 同上 |
| website-v3/pricing.html | 同上 |
| website-v3/apply.html | 同上 |
| website-v3/company.html | 同上 |
| website-v3/contact.html | 同上 |
| website-v3/privacy.html | 同上（§6の法務文言は一切変更していない） |
| website-v3/terms.html | 同上（法務文言は一切変更していない） |
| website-v3/404.html | 同上 |
| website-v3/assets/js/contact-form.js | 成功分岐へ `generate_lead` 送出を追加 |

上記以外の変更は0（`git status` 実測）。

## 2. GTM設置（対象9ページ）

- Container ID：**GTM-KKRD8BZR**（このIDのみ。ページソースから見える公開識別子であり秘密ではない）
- head snippet：`<meta name="viewport">` 直後（`<head>` のできるだけ上部）。Google公式スニペットを構造無変更で設置
- noscript snippet：`<body>` 開始直後。同じく公式のまま
- 各ページの旧「計測タグ導入位置」注釈は、実装後の事実（GTM集約・gtag.js直接実装禁止・非PII方針）へ更新

## 3. GTM非設置ページ（実測0件を確認）

| ページ | GTM | 理由 |
|---|---|---|
| website-v3/signup.html | 0件 | 決済未実装・非公開導線・**password入力あり**のため分析対象外 |
| website-v3/product.html | 0件 | 旧URL互換の静的転送ページ（→ features.html） |
| website-v3/real-estate.html | 0件 | 旧URL互換の静的転送ページ（→ index.html） |
| WEBSITE/app.html ほか WEBSITE/** | 変更0 | 本工程は本番ミラーに触れない |

`GTM-KKRD8BZR` を含むファイル総数 = 9（website-v3配下・実測）。

## 4. GA4 直接実装 0

- GA4 Measurement ID：**G-CCSLHTF6R2**（GTM内の Google タグにのみ設定。HTMLには存在しない）
- HTML/JS 内の `G-CCSLHTF6R2` … **0件**
- `googletagmanager.com/gtag/js` … **0件**（`googletagmanager.com` の参照は gtm.js / ns.html の各9件のみ）
- `gtag(` / `google-analytics.com` / `analytics.js` / Meta Pixel（`fbq(`・facebook.net） … **0件**
- 経路は Website → GTM-KKRD8BZR → Google タグ → G-CCSLHTF6R2 に一本化。二重計測なし

## 5. generate_lead 発火条件

実装位置：`website-v3/assets/js/contact-form.js`

- 送出関数 `pushLead(type)` を新設し、呼び出しは `data.result === 'ok'` 分岐の1箇所のみ
  （`setStatus('ok', …)` の直後・`form.reset()` の前）
- `window.dataLayer = window.dataLayer || [];` を送出直前に行うため、GTM未読込・ブロック環境でも送信処理は壊れない
- try/catch で計測失敗がフォーム完了体験に影響しない
- 送出値は `{ event: 'generate_lead', lead_type: <正式6種> }` の2キーのみ
- 種別はクライアント側でも allowlist（正式6種）照合し、想定外の値は**送らない**

発火しないケース（受入テストで実測）：ページ表示／validation失敗（invalid）／
honeypot・時間差（rejected）／送信間隔制限（too_many）／500系（failed）／
result欠落／result≠ok／旧type（intro等）／大文字・改変値。

## 6. lead_type（正式6種のみ・Contact API allowlist と同一）

`docs / consult / demo / contact / partner / recruit` — `payload.type` をそのまま使用。新分類は作っていない。

## 7. PII送信 0

- dataLayer へ送るのは `event` と `lead_type` の2キーのみ
- 氏名・会社名・メール・電話・本文・住所・IP・IPハッシュ・CSRFトークン・
  APIレスポンス全文・フォーム入力値全文は**一切送らない**
- `payload` をそのまま push する実装は存在しない（`dataLayer.push` はリポジトリ内1箇所のみ・実測）
- privacy.html §6 の開示（種別など非個人情報のみ計測利用）と実装が一致

## 8. テスト結果

### 受入テスト（モック。本番 Contact API へ実送信なし・製品コードへの疑似実装なし）

実際の contact-form.js を読み込み、fetch のみ差し替えて実施。

| 区分 | 内容 | 結果 |
|---|---|---|
| A | 成功応答 ×6種：generate_lead が**1回だけ**・lead_type がそのまま・キーは event/lead_type のみ | 全6種 OK |
| B | 失敗応答 ×6パターン（invalid/rejected/too_many/failed/result欠落/result=ng）：**0回** | 全6 OK |
| C | 想定外種別 ×5（intro/price/空/改変値/大文字）：**0回** | 全5 OK |
| D | dataLayer への PII 混入（会社名・氏名・メール・電話・本文・トークン等8項目） | 混入0件 |

### ブラウザ実測（ローカル配信。GTMコンテナ未公開の状態）

- gtm.js 読込成功（`google_tag_manager['GTM-KKRD8BZR']` 存在）・dataLayer に gtm.js/gtm.dom/gtm.load
- コンテナ未公開のため配信タグ0＝**GA4への計測送信は発生しない**（設計どおりの段階分離）
- ページ表示時の generate_lead = 0／console error 0／フォームUI無傷／`?type=docs` 事前選択も従来どおり

### 機械検査

- GTM設置 = 指定9ページのみ／signup・product・real-estate = 0件
- `node --check`：contact-form.js / main.js / signup.js 構文OK
- HTMLタグ対応（head/body/script/noscript/iframe/main/section/div）：9ページすべて一致
- 内部リンク：12ページ／413参照／未解決0
- 旧type残存：増加0（value="intro|price|feature|fit|other" = 0件）
- Contact API URL：`https://form.smartlaboworks.com/contact.php`・`csrf-token.php` 不変
- 秘密情報混入：0件

## 9. check-prices / check-legal

- `check-prices.js` → **[OK]**
- `check-legal.js` → **[OK]**（§6と改定日は承認済み文面と一致・他は Version1 と一致）

## 10. Git状態

- 作業ブランチ：`feature/web-v3-5a-analytics`（WEB-V3-5A-1 = `0b3a5a8` の続き）
- `origin/website-v3` = `8cf0f86`／`origin/master` = `b3d5df9`（着手時実測・変更なし）
- WEBSITE/** 変更0・master 変更0・force push/rebase なし

## 11. 未公開の確認

- 本番（https://smartlaboworks.com/）：未反映。GitHub Pages 未発火
- GTMコンテナ GTM-KKRD8BZR：**未公開**（公開は本番反映後・代表操作）
- GA4設定：変更なし／XServer・Contact API：変更なし

## 12. 残存リスク

1. **公開順序**：privacy.html §6（「利用しています」の現在形）と GTM snippet は
   **同じデプロイ**で本番へ出すこと（WEB-V3-5A-1 残存リスク(A)の方針どおり）。
   GTMコンテナ公開は本番反映後に行う（コード設置と計測開始の分離）。
2. GTMコンテナ側の設定（GA4 Google タグ／generate_lead 用トリガとイベントタグ）は
   Website コードの範囲外。公開前に GTM プレビューでの発火確認が必要。
3. GA4 拡張計測「フォームの操作」は既定で有効。公開時に扱いを決める（推奨：OFF）。
4. Cookie同意UI（同意モード）は未実装。国内向けは開示＋オプトアウト案内で運用、
   EU圏対応は別工程判断（WEB-V3-5A範囲外）。
5. check-legal.js の V1 基準が working な WEBSITE/ を参照する構造課題は継続
   （website-v3系ブランチ上では正しく機能。master上の是正は別工程）。

## 13. 次工程（WEB-V3-5A-RELEASE）Go / No-Go

**Go（代表承認待ち）**

根拠：機械検査15項目・受入テスト全項目合格。GTMは指定9ページのみ・GA4直接実装0・
PII送信0・既存フォーム機能無傷・本番/GTMとも未公開のまま停止。

公開時の必須順序：
1. website-v3 へ merge → WEBSITE/ へ反映 → master merge → 代表承認 → push（Pages公開）
2. 本番で GTM プレビュー確認（この時点でもコンテナ未公開なら計測は始まらない）
3. **GTMコンテナ公開**（代表操作）→ GA4 リアルタイムで page_view / generate_lead 確認
4. GA4 キーイベント設定・拡張計測「フォームの操作」の扱い決定
