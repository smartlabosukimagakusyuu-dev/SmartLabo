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
3. v1固有URL（`product.html` / `real-estate.html`）を削除するか残すかは**代表に確認**。
   削除する場合はGitHub PagesでURL転送ができないため、`sitemap.xml` の更新と
   Search Consoleでの確認が必要
4. `website-v3/README.md` は公開物ではないため `WEBSITE/` へ入れない

## 4. dev-banner の削除

全10ページの先頭にある開発中バナーを削除する。
```bash
grep -rn 'dev-banner' WEBSITE/ | wc -l    # 0 になること
```

## 5. contact-api の docs 種別反映（必須・先行または同時）

- Websiteの資料請求は `contact.html?type=docs#form` → フォーム種別 `docs` を送信する
- **XServer本番のAPIが `docs` を許可していないと、資料請求だけが弾かれる**
- 反映対象：`contact-api/public/lib/validate.php` の `SLW_TYPES`
- **注意（WEB-V3-3時点の実測）**：`docs` を含む種別更新は
  `feature/web-v3-api-1-contact-api`（WEB-V3-API-1・6種別＝contact/consult/docs/demo/partner/recruit）
  にあり、`website-v3` には入っていない。公開前に**どちらの種別体系で公開するかを確定**し、
  Website側の `<option>` とAPI側の allowlist を必ず一致させること
- あわせて `contact-api` 本体がXServerへ未配置である（WEB-V3-RELEASE 停止報告の実測）。
  配置手順は `contact-api/README.md` §2。apexはGitHub PagesのためPHP不可 →
  **B構成（form.smartlaboworks.com）**で配置し、`contact.html` の
  `action` / `data-endpoint` / `data-token-endpoint` を絶対URLへ変更、
  `allowed_origins` に `https://smartlaboworks.com` を設定する

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

## 11. 資料請求フォームの実送信確認

- `https://smartlaboworks.com/contact.html?type=docs#form` を開く
- 種別が「資料請求」で自動選択されることを確認
- **完全架空の情報のみ**で1回送信し、成功応答と受信を確認
- 既存の他種別（お問い合わせ／無料相談）でも1回確認
- 失敗する場合は §5 のAPI反映漏れを疑う

## 12. モバイル確認

375 / 768 / 1024 / 1440px で
- 横スクロール0（ページ全体）
- 375pxではコンセプト図解が**縦型カード**に切り替わること
- 主要機能カード・これまで→導入後・料金・CTAの見切れ0
- ヘッダーのドロワー開閉・フォーム操作

## 13. ロールバック

```bash
git checkout master
git revert --no-edit <切替コミットのSHA>
git push origin master
```
- `WEBSITE/` が変更されるためActionsが再実行され、v1の内容で再デプロイされる（通常1〜2分）
- `git reset --hard` + force push は使わない（公開履歴とPagesのデプロイ履歴が壊れる）
- 復旧後：トップ200・CNAME保持・主要ページ200を確認
- contact-api側の `docs` 追加は後方互換のため戻す必要はない

## Go / No-Go 判定（公開作業）

CNAME保持・dev-banner 0・contact-api の種別整合・検証2本[OK]・
`WEBSITE/`以外の差分0・Actions成功・公開8ページ200・404正常・
資料請求の実送信成功・モバイル横スクロール0 —— すべて満たしたときのみGo。
1件でも欠ける場合は §13 でロールバックする。
