# WEB-V3-4：問い合わせ種別統一 ＋ Contact API 本番配置準備

実施日：2026-08-07
対象：`website-v3` ブランチ（着手時 HEAD = 6bdcf10＝WEB-V3-3）
**Website本体の本番公開なし・masterへのmergeなし・WEBSITE/**変更なし・GitHub Pages操作なし**

---

## 1. 旧type調査（着手前の実測）

`website-v3/**` を全文検索し、問い合わせ種別として使われている値を確認した。

| 対象 | 実測 |
|---|---|
| contact.html の `<option value>` | intro / price / feature / fit / **docs** / other（旧6種・要修正） |
| URLパラメータ `?type=` | `?type=docs` のみ13件（他の種別導線なし） |
| contact-api（website-v3側） | 旧6種のまま（WEB-V3-API-1の正式6種は別ブランチに存在） |

説明文中の通常単語（「機能について」等の見出し文言）は種別値ではないため対象外として扱った。

## 2. 正式6種別（統一後）

| value | ラベル（Website／メール共通） | 用途 |
|---|---|---|
| docs | 資料請求 | サービス紹介資料の請求 |
| consult | 無料相談 | 導入相談・料金・自社に合うか |
| demo | デモ依頼 | 画面を見ながらの説明 |
| contact | 一般お問い合わせ | 上記以外 |
| partner | パートナー・代理店相談 | 協業 |
| recruit | 採用について | 採用 |

**Website の `<option>` 表示順は指示どおり docs → consult → demo → contact → partner → recruit。**
API側 `SLW_TYPES`（`contact-api/public/lib/validate.php`）のキーと**完全一致**。
旧値（intro / price / feature / fit / other）は allowlist に無いため新規送信では422で拒否される。
過去の文書・履歴は削除していない。

## 3. Website V3 の修正

- `contact.html`
  - 種別を正式6種へ差し替え（順序・ラベル・valueを指定どおり）
  - 「ご相談いただける内容」の6カードを種別と同じ並び・同じ呼び方へ変更し、
    各カードから `?type=…#form` で該当種別を自動選択
  - 送信先を **B構成の絶対URL** へ変更
    （action / data-endpoint = `https://form.smartlaboworks.com/contact.php`、
    data-token-endpoint = `https://form.smartlaboworks.com/csrf-token.php`）
- CTAのURLパラメータ統一（全10ページ）
  - 資料請求 → `?type=docs#form`（14件）
  - 無料相談・料金相談・先行案内 → `?type=consult#form`（22件）
  - デモ／一般／パートナー／採用 → contact.html 内のカードから各1件
  - ナビ・フッター・関連リンクの「お問い合わせ」は種別未指定のまま（利用者が選ぶ）
- 不正・旧typeが渡された場合：`contact-form.js` は
  「フォームに存在するoptionのみ採用」なので**未選択のまま安全に戻る**（実測確認）
- **旧type残存 0件**（value= / ?type= の文脈で機械確認）

### 旧URL互換（判断）
旧typeの変換（intro→consult 等）は**実装していない**。理由：
現行の公開サイト（v1）にはお問い合わせフォームが無く（`WEBSITE/contact.html` の
form要素 0 を実測）、`?type=` 付きURLを外部へ公開したことがない。
変換対象の利用実績が無いため、経路を増やすほうがリスクになる。
将来 `?type=` 付きURLを広告等で配布した後に変更する場合は、その時点で変換を追加する。

## 4. Contact API

`feature/web-v3-api-1-contact-api` の正式版（allowlist・件名・本文）を website-v3 へ取り込み、
ラベルのみ正式名称（一般お問い合わせ／パートナー・代理店相談／採用について）へそろえた。

### 防御の確認（ローカル PHP 8.3・隔離設定・mode=test＝実メール送信なし）
**25/25 PASS**

| 項目 | 結果 |
|---|---|
| CSRF（HMAC＋TTL 7200秒） | 不正トークン403・正常トークンで200 |
| Origin検査 | Originなし403・不許可Origin403・許可Origin200 |
| CORS | 許可オリジンにのみ `Access-Control-Allow-Origin` を返す／不許可には返さない（**ワイルドカード不使用**） |
| type allowlist | 正式6種すべて200／旧5種＋パストラバーサル文字列すべて422（`errors.type=invalid`） |
| honeypot | 400 |
| 送信間隔・時間差 | form_ts直後は400／レート制限は既存実装（3回/600秒）のまま |
| 文字数上限 | 本文3001文字で422（会社名100・氏名100・メール254・電話30・本文3000・全体20KB） |
| メール検証 | 形式不正422／メール内CRLF（ヘッダーインジェクション）422 |
| HTMLエスケープ | `<script>` を含む会社名でも200かつ本文へタグを残さない（strip_tags＋htmlspecialchars） |
| メソッド | GET は405 |
| 内部エラー非露出 | 応答は定型JSONのみ（stack・SQL・パス・鍵の混入0） |
| PHP構文 | 6ファイル `php -l` すべてOK |

既存防御を弱めた箇所は無い（allowlistの値とラベルのみ変更）。

## 5. XServer本番構成（B構成・実測）

| 項目 | 実測値 |
|---|---|
| apex `smartlaboworks.com` | 185.199.108–111.153（**GitHub Pages**・PHP実行不可） |
| API `form.smartlaboworks.com` | 85.131.213.188（**XServer**・nginx）・**HTTPS 200** |
| SSL証明書 | CN=form.smartlaboworks.com・**2026-10-12まで有効** |
| PHP配置状況 | `/csrf-token.php`・`/contact.php`・`/api/` 配下すべて **404 ＝ 未配置** |

→ **インフラ（サブドメイン・SSL）は準備済み、PHPファイルのみ未アップロード。**

### 本番配置について（本工程では未実施）
XServer共用サーバーへの配置手段は「サーバーパネル／FTP」で、当方はその認証情報を
扱わない（扱えない）ため**配置は代表作業**。配置手順・確認項目は
`docs/releases/WEB_V3_RELEASE_RUNBOOK.md` §5 に完全な形で記載した。
配置後は当方が外部から機械確認できる（csrf-token 200／不正type 422／6種別 allow 等）。
**本番メール送信試験（docsで1回・完全架空データ）は配置・設定完了後に実施する。**

## 6. Website ↔ API 接続

- 本番：`https://smartlaboworks.com` → `https://form.smartlaboworks.com`（HTTPS・
  allowed_origins に公開サイトのオリジンのみ登録）
- ローカル：APIが別オリジンかつ未配置のため、`localhost` から開いたときは
  **CSRFトークン取得を行わない**分岐を追加（`contact-form.js`）。
  UIは壊れず、送信ボタンも操作可能。**本番の挙動は変更していない**
- `contact-config.example.php` の `allowed_origins` に B構成の説明とコメント例を追記

## 7. v1固有URL（product.html / real-estate.html）

### 調査結果（実測）
| 項目 | product.html | real-estate.html |
|---|---|---|
| 現在の公開状態 | **200** | **200** |
| v1トップからのリンク | 2件 | 2件 |
| v1 sitemap.xml 掲載 | 1件 | 1件 |
| website-v3 に同名ページ | なし | なし |

公開中かつsitemap掲載済み＝検索エンジンに認識されている可能性が高い。
そのまま公開すると404になり、流入と評価を失う。

### 対応（website-v3 へ追加）
GitHub Pages はサーバー301を設定できないため、**静的な転送ページ**を追加した。
1ページで次の3つを同時に行う：`rel="canonical"`（正規URL宣言）／
`meta refresh 0`（即時転送）／`location.replace`（履歴を汚さない転送）。
すべて無効な環境でも本文のリンクから移動できる。

| 旧URL | 転送先 | 理由 |
|---|---|---|
| product.html | features.html | 製品説明は機能ページへ統合 |
| real-estate.html | index.html（トップ） | V3は業種を限定しない内容へ統一 |

転送ページは sitemap.xml へ載せない（正規URLは転送先）。禁止語（業種名等）は含めていない。
**iframeでの実測で `/product.html → /features.html`・`/real-estate.html → /index.html` を確認。**

## 8. 検証結果

| 検査 | 結果 |
|---|---|
| check-prices.js | **[OK]**（送信先の許可リストをB構成へ更新後も整合） |
| check-legal.js | **[OK]** |
| 問い合わせtype一致（Website ↔ API） | 完全一致（6種） |
| 旧type残存 | **0件** |
| 各種別の自動選択（docs/consult/demo/contact/partner/recruit） | すべて実測OK |
| 不正・旧type | 未選択へ安全に戻る（実測） |
| API 25項目（CSRF/CORS/allowlist/防御） | **25/25 PASS** |
| 秘密情報 | 0件 |
| リンク切れ・未定義type | 0件 |
| 全12ページ HTTP | すべて200（転送2ページ含む） |
| console error | 0（ローカル分岐追加後・form.smartlaboworks.comへのリクエスト0を実測） |

### 検証ツールの更新（docs配下・公開物ではない）
`check-prices.js` の送信先許可を B構成へ更新：
- `ALLOWED_ACTIONS` に `https://form.smartlaboworks.com/contact.php` ほかを追加
- 外部ドメイン検知を「smartlaboworks.com とそのサブドメイン以外を拒否」へ厳密化
  （`https://evil.example/...` は引き続き拒否）

## 9. 残存リスク

1. **contact-api が本番未配置**（唯一の公開ブロッカー）。代表による配置＋
   `contact-config.php` の値設定（受信先・鍵2つ・allowed_origins・mode=live）が必要
2. 本番での実メール送信確認が未実施（配置後に docs で1回・完全架空データ）
3. v1の `WEBSITE/sitemap.xml` に product/real-estate が残るため、公開時は
   V3の sitemap（転送ページ非掲載）へ置き換わることを確認する
4. `?type=` を広告等で配布した後に種別値を変えると流入が壊れるため、
   6種別は今後の正本として固定する

## 10. Go / No-Go

**種別統一・API準備・転送対応：Go**（Website↔API完全一致・旧type残存0・
API検証25/25・検証2本[OK]・console error 0）
**Website本番公開：条件付きGo** — 残存リスク1（contact-api配置）と2（実送信確認）を
Runbookどおり処理し、代表の明示承認を得た時点で着手可能。本工程では公開していない。
