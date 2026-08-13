# WEB-SALES-12 Websiteセルフ申込フォーム実装（実行前監査）

- 日付: 2026-08-13
- branch: `feature/web-sales-12-public-signup-form`（master未統合・GitHub Pages未公開）
- 製品repo: `smartlabo-works-lite` master `6077f234b5eafd096317b54c5bcfd64afe34c141`（読み取りのみ・変更なし）

## 1. 目的

公開Website `apply.html` の「オンライン申し込みは現在準備中です」を正式な申込フォームへ
差し替え、本番の `POST /api/public/signup` へ安全に接続できる状態にする。
本工程は feature branch への実装・試験・push まで。master統合と公開は代表承認後。

## 2. API仕様の抽出結果（正本: server/services/signupService.js ほか）

| 項目 | 値 |
|---|---|
| エンドポイント | `POST /api/public/signup`（`PUBLIC_CORS_PATHS`で完全一致限定） |
| 形式 | JSONのみ（`express.json` 上限256kb） |
| フィールド | `companyName` 必須≤100 / `representativeName` 必須≤50 / `email` 必須≤254・`/^[^\s@]+@[^\s@]+\.[^\s@]+$/`・改行不可 / `phone` 任意≤30・`/^[0-9+\-()\s]+$/` / `licenseCount` 必須・整数1〜`SIGNUP_MAX_LICENSE_COUNT` |
| 正常応答 | HTTP 202 `{ok:true, data:{message}}`（受付・登録済みメール・回数制限のいずれも同一応答＝存在確認をさせない設計） |
| 入力エラー | HTTP 400 `{ok:false, error:{code:'VALIDATION_ERROR', message:<利用者向け文言>}}`（フィールド別詳細は返らない） |
| Origin/CORS | `PUBLIC_SIGNUP_ORIGINS` 完全一致のみ。許可メソッド POST/OPTIONS。★許可ヘッダーは Content-Type のみ。credentialsなし |
| CSRF | 不要（未ログイン＝セッション無しはOrigin確認のみ。`middleware/csrf.js`） |
| bot対策 | API側はレート制限（IP/メール別・超過も202）。honeypotはAPIに無し（Website側で実装） |
| 冪等性 | 同一メールの認証待ち申込は再送に集約（`SIGNUP_MAX_MAIL_SENDS`=既定5で上限）。二重会社は作られない |
| 表示可 | `error.message`（利用者向け日本語）。表示不可: 内部コード詳細・スタック・レスポンス全文 |

API側の修正は不要（既存仕様のままWebsiteから接続可能）。製品repoは未変更。

## 3. 実装内容

- `WEBSITE/apply.html`: `#status` セクション（設計時からの差し替え予定地）をフォームへ差し替え。
  hero/flow/#chooseの「準備中」「予定」文言を現行化。料金・課金ルール・法務・関連ページの
  各セクションは不変。`assets/js/apply-form.js` を追加読込
- `WEBSITE/assets/js/apply-form.js`: 新規。クライアント検証はAPIと同一規則。
  fetchは `Content-Type: application/json` のみ・credentials omit・自動再送なし。
  202+`ok:true` でのみ完了案内を表示（フォームは隠すが入力は消さない）。
  失敗時は項目単位表示（クライアント検証）またはAPIの利用者向けmessage、
  通信失敗は一般文言。honeypot充填時は送信せず完了表示のみ。
  localhostでは同一オリジンのスタブへ向ける（本番Originは公開サイトのみ許可のため）
- `WEBSITE/assets/js/signup.js`: 削除（旧SALES-1のPHP API前提・Websiteでパスワードを
  収集する廃止済み設計。未参照・現行導線と矛盾するため）
- 法務方針: 所在地・電話・受付時間・Lite法務3文書の本文/直リンクはWebsiteへ戻していない
  （check-legal.js合格）。フォームは「送信では契約不成立」「最終確認画面で全文確認・
  3項目同意後にのみ決済へ」「カードはStripe画面で直接入力」を明記

## 4. 試験結果（ローカルスタブ・本番API送信0）

正常系: 202で完了案内表示・payloadキーは`{companyName,representativeName,email,licenseCount}`
（phone空欄時は省略・privacy/honeypotは送信しない）・licenseCountはnumber型・
Content-Type以外の独自ヘッダー0・完了案内へフォーカス移動。

異常系: 必須不足/不正メール/人数0・小数・10000超/101文字/privacy未同意 → いずれも
送信前に項目単位エラー（リクエスト0件）。400/403/429/500/通信失敗 → 安全な文言表示・
入力保持・各1リクエストのみ（JS自動再送0。keep-alive切断時のブラウザ標準の
トランスポート再送はサーバー側のpending集約で無害）。二重クリック → 1リクエスト。
honeypot → 送信0で完了表示。

表示・操作: 375px/1280pxとも横スクロール0・入力54px/ボタン53px/同意行44px・
正のtabindex 0・honeypotはtab到達不可・Enter送信はフォーム構造上有効
（input+type=submit。試験環境のキー合成制約により実キー再現は不可のため
submitイベント経路をrequestSubmitで検証）。console出力にPII/秘密値0。

静的検査: check-legal.js OK・check-prices.js OK（10,000/20,000/3,000円・計算例一致）・
sitemap/robots整合（URL変更なし）・GTM/GA4/dataLayer 0・秘密値/デモ個人情報0。

## 5. 現在の販売状態と初回実顧客の監視（WEB-SALES-9 段階11の確定内容）

- 技術面: lite本体（申込API〜認証〜同意〜Checkout〜Webhook〜有効化〜メール）はGo。
  本フォームのmaster統合・公開が、セルフサーブ販売開始の最後の前提条件
- 本番ベースライン（2026-08-13）: companies=1（運営者用・operator）・signup 0・
  consent 0・stripe_events 0・Stripe liveオブジェクト0
- 初回実顧客の監視: 申込検知は `sudo -u slwlite bash /tmp/ws11c.sh`（件数のみ）を
  営業開始後1日1回＋申込連絡時。決済後30分以内にStripe GET（Session/Sub/Invoice各1件・
  税/明細一致）とDB読み取り（active化1回・stripe_events重複0）・health/MainPID確認。
  二重決済疑いはInvoice件数で判定し、返金は代表承認のもとDashboardで実施
  （Stripe手数料は返金されない・返金はDBへ自動反映されない点を記録）

## 6. 未実施（禁止事項の遵守）

本番APIへの正常送信0・本番メール0・本番DB変更0・Checkout Session 0・実決済0・
Website master変更0・GitHub Pages公開0・製品repo変更0・staging/バックアップ削除0

## 7. master統合・公開の条件（Go判断は代表）

1. 本monographの試験結果の承認
2. 公開直後にWebsite実画面から不正入力による安全確認（正常な申込は作らない）を1回実施
3. 公開後、初回実顧客監視体制（上記5）の開始
