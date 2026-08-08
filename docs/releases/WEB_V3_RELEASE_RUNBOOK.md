# Website V3 本番公開 Runbook（WEB-V3-RELEASE 用）

作成：WEB-V3-3（2026-08-07）
対象：`website-v3` ブランチの `website-v3/`（WEB-V3-3時点のHEAD）
公開先：https://smartlaboworks.com/ （GitHub Pages・独自ドメイン）
**本Runbookは手順書であり、WEB-V3-3では一切実行していない。実行は代表の明示承認後。**

---

## 前提（実測済みの公開の仕組み）

- 公開は `.github/workflows/pages.yml`。トリガは **`branches:[master]` かつ `paths:["WEBSITE/**", ".github/workflows/pages.yml"]` のAND**。
  ビルドステップなし（`WEBSITE/` をそのまま配信）
- 独自ドメインは **`WEBSITE/CNAME`（smartlaboworks.com）** が保持している。**消すとドメインが即切れる**
- `WEBSITE/app.html` はデモ画面で、v1の全ページから参照されている

## 1. 公開前バックアップ

- Gitで戻せることを確認（`git log --oneline -3 master`／公開直前のmaster SHAを記録）
- 現行 `WEBSITE/` をローカルへ退避（例 `backup/WEBSITE-pre-v3-<YYYYMMDD-HHMMSS>/`）
- 退避物に `CNAME`・`app.html`・`css/`・`js/`・`img/`・`robots.txt`・`sitemap.xml` が含まれることを確認

## 2. WEBSITE/CNAME の保持確認（最重要）

```bash
git show master:WEBSITE/CNAME    # smartlaboworks.com であることを確認
```
切替後にも同じ内容で存在することを再確認する（消失＝No-Go・即ロールバック）。

## 3. website-v3 → WEBSITE/ への安全な反映

1. `master` を最新化し、切替専用ブランチを作る
   ```bash
   git checkout master && git pull && git checkout -b release/website-v3
   ```
2. `WEBSITE/` の中身を `website-v3/` の内容で置き換える。**必ず残すもの**
   - `WEBSITE/CNAME`（先に退避 → 置換後に復元して内容を確認）
   - `WEBSITE/app.html`（デモ画面）
3. v1固有URL（`product.html` / `real-estate.html`）は **WEB-V3-4で転送ページを用意済み**。
   website-v3 の同名ファイル（canonical＋meta refresh＋location.replace の静的転送）を
   そのまま `WEBSITE/` へ含めること。転送先は product→features.html、
   real-estate→index.html。sitemap.xml には載せない（正規URLは転送先）。
   公開後は Search Console で旧URLの扱いを確認する
4. `website-v3/README.md` は公開物ではないため `WEBSITE/` へ入れない

## 4. dev-banner の削除

全10ページの先頭にある開発中バナーを削除する。
```bash
grep -rn 'dev-banner' WEBSITE/ | wc -l    # 0 になること
```

## 5. Contact API の本番配置（必須・Website公開より先または同時）

### 5-0. 正式問い合わせ種別（WEB-V3-4で確定。Website と API で完全一致）

| value | ラベル |
|---|---|
| docs | 資料請求 |
| consult | 無料相談 |
| demo | デモ依頼 |
| contact | 一般お問い合わせ |
| partner | パートナー・代理店相談 |
| recruit | 採用について |

旧値（intro / price / feature / fit / other）は allowlist に無く、送信されても422で拒否される。
**Website の `<option>` と `contact-api/public/lib/validate.php` の `SLW_TYPES` は
必ず同時に更新する**（片方だけ変えると該当種別の送信がすべて失敗する）。

### 5-1. 構成（B構成・WEB-V3-4実測）

| 項目 | 値 |
|---|---|
| 公開サイト | `https://smartlaboworks.com` … GitHub Pages（**PHP実行不可**） |
| Contact API | `https://form.smartlaboworks.com` … XServer（85.131.213.188・nginx） |
| SSL | CN=form.smartlaboworks.com・2026-10-12まで有効・**HTTPS必須** |
| Website側の送信先 | `action` / `data-endpoint` = `https://form.smartlaboworks.com/contact.php`／`data-token-endpoint` = `https://form.smartlaboworks.com/csrf-token.php`（すでにwebsite-v3へ設定済み） |
| 現在の配置状況 | `/contact.php`・`/csrf-token.php` ともに **404 ＝ 未配置**（要作業） |

### 5-2. 配置手順（代表：XServerサーバーパネル／FTP）

1. サーバーパネルで `form.smartlaboworks.com` の **PHP 8.0以上** を選択
2. `contact-api/public/` の中身を form サブドメインのドキュメントルートへアップロード
   （`.htaccess`／`contact.php`／`csrf-token.php`／`lib/` 一式）
3. `contact-api/private/` の中身をドキュメントルートの **1つ上** へアップロード
4. `contact-config.example.php` を `contact-config.php` としてコピーし、値を設定
   - `to_email`（受信先）／`from_email`（自ドメインのアドレス）
   - `csrf_secret` と `ip_hash_secret` を個別に生成
     `php -r "echo bin2hex(random_bytes(32));"`
   - **`allowed_origins` に `https://smartlaboworks.com` のみを追加**（CORSの許可元。
     `*` は使わない。www を使う場合のみ `https://www.smartlaboworks.com` も追加）
   - `mode` は最初 `test` のまま
5. 権限：`private/` を 700・`contact-config.php` を 600
6. `mode=test` で送信し、成功表示が出ることを確認 → 問題なければ `mode=live` へ
7. 完了をお知らせいただければ、当方が外部から機械確認します
   （csrf-token 200／不正type 422／正式6種別 allow／内部情報の非露出）

### 5-3. 配置後の確認（外部から実行可能）

```bash
curl -s -o /dev/null -w '%{http_code}\n' -H 'Origin: https://smartlaboworks.com' \
  https://form.smartlaboworks.com/csrf-token.php     # 200
curl -s -o /dev/null -w '%{http_code}\n' https://form.smartlaboworks.com/csrf-token.php   # 403（Originなし）
```
CORSヘッダーが許可オリジンにのみ付くこと・`*` でないことも確認する。

## 6. 公開前差分確認

```bash
git status --short
git diff --stat master -- WEBSITE/
git diff --name-only master | grep -v '^WEBSITE/' | grep -v '^website-v3/'   # 空であること
grep -c 'smartlaboworks.com' WEBSITE/CNAME                                   # 1
node docs/reviews/tools/check-prices.js && node docs/reviews/tools/check-legal.js   # 両方[OK]
```
`WEBSITE/` 以外に変更が無いこと・CNAMEがあること・検証2本が通ることを確認。

## 7. master への統合

- `release/website-v3` をコミットし `master` へマージ（`--no-ff`）
- **この push で初めて GitHub Actions が発火する**（それまで本番は不変）

## 8. GitHub Pages の発火確認

- Actions の `Deploy Homepage to GitHub Pages` が成功することを確認（通常1〜2分）
- 失敗時はロールバック（§13）

## 9. smartlaboworks.com のHTTP確認

```bash
for p in / /features.html /pricing.html /apply.html /company.html /contact.html /privacy.html /terms.html /nonexistent; do
  echo "$p: $(curl -s -o /dev/null -w '%{http_code}' https://smartlaboworks.com$p)"
done
```
公開8ページ200・存在しないURLは404ページ・HTTPS有効・favicon表示を確認。

## 10. 全主要ページの目視確認

- トップ：Hero「会社を動かすAI。」／コンセプト図解／Company Brain Basic／
  管理者ダッシュボード／これまで→導入後／主要機能6＋その他一覧／料金／
  キャンペーン／FAQ／最終CTA（資料請求・無料相談・お申し込み）
- 機能ページ：13機能（先頭がCompany Brain Basic・管理者ダッシュボード）
- 料金ページ：初期設定費10,000円／月額20,000円／追加3,000円（税別）・含まれるもの
- 会社情報・プライバシー・利用規約・404

## 11. 問い合わせフォームの実送信確認

- `https://smartlaboworks.com/contact.html?type=docs#form` を開く
- 種別が「資料請求」で自動選択されることを確認
- **完全架空の情報のみ**で1回送信し、成功応答と受信メール
  （件名「【Smart Labo】資料請求」・本文に会社名/氏名/メール/電話/種別/内容/送信日時/IPハッシュ）を確認
- 続けて `?type=consult` `?type=demo` `?type=contact` `?type=partner` `?type=recruit` を開き、
  **種別が自動選択されること**を確認（送信は任意。少なくとも1種別で実送信できていれば経路は確認済み）
- 旧値（例 `?type=intro`）では**未選択のまま**になることを確認（安全側の挙動）
- 失敗する場合は §5 の配置・`allowed_origins`・`mode` を疑う

## 12. モバイル確認

375 / 768 / 1024 / 1440px で
- 横スクロール0（ページ全体）
- 375pxではコンセプト図解が**縦型カード**に切り替わること
- 主要機能カード・これまで→導入後・料金・CTAの見切れ0
- ヘッダーのドロワー開閉・フォーム操作

## 13. ロールバック

### Website（GitHub Pages）
```bash
git checkout master
git revert --no-edit <切替コミットのSHA>
git push origin master
```
- `WEBSITE/` が変更されるためActionsが再実行され、v1の内容で再デプロイされる（通常1〜2分）
- `git reset --hard` + force push は使わない（公開履歴とPagesのデプロイ履歴が壊れる）
- 復旧後：トップ200・**CNAME保持**・主要ページ200・旧URL（product/real-estate）の応答を確認

### Contact API（XServer）
- 配置前の内容を退避しておき、問題時はその内容へ戻す（今回は新規配置のため、
  切り戻しは「アップロードしたファイルを削除する」ことに相当）
- `mode` を `test` に戻せば、メール送信を止めたまま挙動を確認できる
- Websiteだけを先に切り戻した場合、APIは残っていても害はない（呼び出し元が無くなるだけ）
- **API側の種別allowlistは後方互換**（旧値を消しただけで新規値は増えている）ため、
  Websiteをv1へ戻してもv1にフォームは無く影響しない

## Go / No-Go 判定（公開作業）

CNAME保持・dev-banner 0・contact-api の種別整合・検証2本[OK]・
`WEBSITE/`以外の差分0・Actions成功・公開8ページ200・404正常・
資料請求の実送信成功・モバイル横スクロール0 —— すべて満たしたときのみGo。
1件でも欠ける場合は §13 でロールバックする。
