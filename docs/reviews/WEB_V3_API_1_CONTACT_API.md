# WEB-V3-API-1: Website V3 お問い合わせAPI（XServer）正式構築

- 実施日: 2026-08-07
- ブランチ: `feature/web-v3-api-1-contact-api`（`website-v3` @ f0f1b44 から分岐）
- 対象: `contact-api/**`・`website-v3/contact.html` のみ
- **本工程で行っていないこと**: XServer本番デプロイ／master・WEBSITE/**の変更／GitHub Pages公開／
  Lite（smartlabo-works-lite）・CRM・AIの改修

---

## 1. 着手前の現状監査

| 対象 | 現状（着手時） |
|---|---|
| contact-api/public | contact.php・csrf-token.php・.htaccess・lib/(config/security/validate/mailer) |
| contact-api/private | .htaccess・contact-config.example.php（実値はGit管理外） |
| 種別(SLW_TYPES) | intro/price/feature/fit/docs/other の6値（WEB-V2-7設計＋WEB-V3-1でdocs追加） |
| 件名 | 【お問い合わせ】種別 / 会社名 |
| 本文 | 受付日時・種別・会社名・氏名・メール・電話・人数・内容（IPハッシュなし） |
| セキュリティ | CSRF(HMAC+TTL7200s)/Origin・Referer allowlist/honeypot/時間差/レート制限(3回/600s)/文字数上限/メール検証/ヘッダーインジェクション対策/HTMLエスケープ/display_errors=0 |
| 本番配置 | **未配置**（apex=GitHub PagesでPHP不可・form.smartlaboworks.com=XServer初期ページのみ。WEB-V3-RELEASE停止報告のとおり） |

## 2. 実装内容

### 2-1. 正式6種別への統一（validate.php）

| value（?type= と同一） | ラベル（件名・本文・画面共通） |
|---|---|
| contact | お問い合わせ |
| consult | 無料相談 |
| docs | 資料請求 |
| demo | デモ依頼 |
| partner | パートナー募集 |
| recruit | 採用 |

旧値（intro/price/feature/fit/other）は廃止し、不正値として422で拒否される。
本番は未配置のため後方互換の考慮は不要（破壊的変更なし）。

### 2-2. メール正式化（mailer.php）

- 件名: `【Smart Labo】` ＋ 種別ラベル（RFC2047エンコード・ヘッダー安全化済み）
- 本文の項目順: 会社名 / お名前 / メールアドレス / 電話番号 / 問い合わせ種別 / 利用予定人数 /
  お問い合わせ内容 / 送信日時(JST) / IPハッシュ
- IPハッシュは `security.php slw_ip_key()` と同一のHMAC値（生IPは書かない・復元不能・
  同一送信元の突合にのみ使える）
- Reply-To=送信者・From=自ドメイン・Auto-Submitted付与は従来どおり

### 2-3. Website側（website-v3/contact.html）

- 種別 `<select>` を正式6種別へ差し替え（API側 SLW_TYPES と1対1、同時更新をコメントで明記）
- `?type=contact|consult|docs|demo|partner|recruit` の全6値で自動選択
  （contact-form.js の既存汎用処理: 値の実在確認つき・不正値は無視・JS無効でも手動選択可能）
- meta description を新種別に合わせて更新（3箇所）

## 3. セキュリティ要件の確認（全12項目）

CSRF / Originチェック / 種別Allowlist / 文字数制限(会社100・氏名100・メール254・電話30・
本文3000・全体20KB) / メール形式検証(filter_var＋改行拒否) / HTMLエスケープ
(htmlspecialchars ENT_QUOTES・strip_tags) / Header Injection対策(\r\n\0除去＋mimeheader) /
Rate Limit(3回/600秒・HMAC化IP・0600) / honeypot / 送信間隔制御(form_ts 3秒) /
PHP8対応(8.3.32でlint・実行) / 秘密情報ログ禁止(内容・宛先・例外詳細を応答へ出さない) — すべて維持・確認済み。

## 4. 動作確認（ローカルPHP 8.3.32・php -S・3構成・架空データのみ・mode=test=実送信なし）

機械テスト **33/33件 PASS**（スクリプトはscratchpad・リポジトリ非混入）:

- トークン発行: Originなし403 / 不正Origin403 / 許可Origin200＋トークン
- メソッド・パス: GET contact.php=405 / 存在しないパス=404
- **6種別すべて受理**(contact/consult/docs/demo/partner/recruit → 200 ok)
- **旧・不正種別すべて拒否**(intro/price/feature/fit/other/hack → 422 errors.type=invalid)
- 入力検証: 必須欠落422 / 本文3001文字422 / メール形式422 / メール内改行(CRLF)422
- bot対策: honeypot=400 / 送信間隔(form_ts直後)=400
- CSRF: 不正トークン403 / トークン無し＋許可Referer＝JS無効経路は200(HTML応答・設計どおり)
- Origin: 不許可403
- XServer想定: X-Forwarded-For付きで正常受理（先頭IPのみ採用の実装）
- 設定不足: contact.php=500 failed（内部情報なし）/ csrf-token.php=403（Originゲートが先・設計どおり）
- レート制限: 3回目まで200・4回目429
- 件名・本文プレビュー: 全6種別で `【Smart Labo】種別` と本文項目（IPハッシュ含む）を実出力で確認

既存検証: `php -l` 6ファイル全てOK / check-prices.js [OK] / check-legal.js [OK]
（契約系ページのヘッダー・フッター同一性等も引き続き合格）

## 5. XServer本番デプロイ（未実施・次工程）

配置手順は `contact-api/README.md` §2 のとおり（public→ドキュメントルート、private→1つ上、
contact-config.php作成・鍵生成・mode=test→live）。**apex（smartlaboworks.com）はGitHub Pagesで
PHP不可のため、配置先は form.smartlaboworks.com（B構成）**。B構成では公開前に
`website-v3/contact.html` の action / data-endpoint / data-token-endpoint を
`https://form.smartlaboworks.com/...` の絶対URLへ変更し、`allowed_origins` に
`https://smartlaboworks.com` を設定する（WEB-V3-RELEASE Runbookの必須項目）。

## 6. 残存事項

1. CRM自動登録は次工程（本工程はメール送信まで）
2. XServerへの配置・contact-config.php の実値投入は代表側作業（秘密値のため）
3. B構成採用時の contact.html エンドポイント絶対URL化（配置完了後の1コミット）
4. 実メール送信テスト（mode=test→live切替後、完全架空データで1回）は公開工程で実施

*作成: WEB-V3-API-1（2026-08-07）*
