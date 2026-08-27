# intake-api — 店舗向けHP導入フォームの受付API（HP-ONBOARDING-4B / -4B-R1）

```text
STATUS : ローカル骨組み（本番未配置）
SSOT   : docs/website/HP_ONBOARDING_INTAKE_DATA_MODEL_V1.md **v1.3**
上位    : docs/website/HP_ONBOARDING_INTAKE_FORM_SPEC_V1.md v1.2（入力項目）
         docs/website/WEBSITE_PRODUCTION_AND_MAINTENANCE_PRICE_V1.md VERSION 3（価格・範囲）
配置予定: intake.smartlaboworks.com（**未作成**。サブドメイン・SSL は 4H で代表作業）
```

> ★このディレクトリは **GitHub Pages の公開対象に含まれない**。
> Pages が配信するのは `WEBSITE/` だけである（`.github/workflows/pages.yml`）。
> `intake-api/` は XServer へ手動で配置して運用する（4H）。

---

## 1. 責任境界（既存APIと混在させない）

| ディレクトリ | 役割 | 状態 |
|---|---|---|
| `contact-api/` | 会社サイトの問い合わせ（`form.smartlaboworks.com`） | **本番稼働中**。触らない |
| `signup-api/` | セルフ申し込みAPI | 本番未配置 |
| `xserver-form/` | 問い合わせの旧系統（SMTP・reCAPTCHA） | 本番未使用 |
| **`intake-api/`** | **店舗向けHP導入フォームの受付**（本ディレクトリ） | ローカル骨組み |

- 設定・DB・レート制限の記録は**それぞれ独立**させる。既存APIと共有しない。
- 既存APIのコードを import しない（考え方だけを踏襲する）。

流用した考え方:
- 生IPを保存せず HMAC 化する／レート制限をファイルで持つ … `contact-api/public/lib/security.php`
- `public/` と `private/` を分け、`private/` は Web から全拒否 … `contact-api`
- 外部ライブラリを使わない自前テストランナー … `signup-api/tests/`

## 2. ディレクトリ

```text
intake-api/
├── dev/php.ini.example      ローカル開発専用 php.ini のテンプレート（php.ini 実体は追跡しない）
├── public/                  ドキュメントルートへ置く（index.php / .htaccess / .user.ini）
├── private/                 ★public_html の外へ置く（設定・DB・ログ・ratelimit）
├── src/                     アプリ本体
└── tests/                   自動テスト（外部ライブラリなし）
```

## 3. ローカルでの動かし方

```bash
# 1) php.ini を用意する（extension_dir を自分の環境の絶対パスへ）
cp intake-api/dev/php.ini.example intake-api/dev/php.ini

# 2) テストを実行する
php -c intake-api/dev/php.ini intake-api/tests/run-tests.php
```

必要な拡張: `pdo_sqlite` / `sqlite3` / `openssl` / `mbstring`
（Windows の PHP には DLL が同梱されている。php.ini で有効化するだけでよい）

★テストは `tests/.tmp/` 配下に使い捨てのDBを作る。**本番・既存DBへは接続しない。**
★テストの鍵は固定のダミーのみ。実鍵をテストへ持ち込まない。

## 4. endpoint（4B の範囲）

| method | path | 内容 |
|---|---|---|
| POST | `/session/start` | `/start#<token>` から渡された token を body で受け、session Cookie へ交換 |
| GET | `/case` | 認証済み案件と回答の取得 |
| POST | `/answers/save` | 途中保存（楽観ロック） |
| POST | `/submit` | 最終提出。**`submission_id`（UUID v4）が必須**（下の §7） |
| POST | `/session/logout` | session の個別失効 |

**店舗入力画面は 4C の範囲。ここには無い。**

## 5. 守っている決まり（SSOT からの抜粋）

- token / session secret は `random_bytes(32)` → base64url 43文字。**DBには SHA-256 hash のみ**
- 平文 session secret は **Cookie にだけ**（Secure / HttpOnly / SameSite=Strict）
- token 失効・再発行・`locked` / `closed` で**関連 session も失効**
- token / session / 案件状態の失敗は**すべて同一文言・404**
- CORS を許可しない。Origin を厳格検査する。body 上限 1MB。画像アップロードを受けない
- 生IPを保存しない（HMAC 化のみ）。レート制限は 10分5回 / 10分60回 / 10分5回
- **SQLite 3.26.0 互換サブセット**のみ使用（VACUUM INTO / RETURNING / STRICT /
  DROP COLUMN / 生成列 / SQL側JSON関数を使わない）。
  部分一意索引は 3.8.0 以降で使えるため**使用可**
- 最終提出は **`submission_id`（UUID v4）で冪等化**する（§7）
- バックアップは `SQLite3::backup()`。取得後に `integrity_check` / `foreign_key_check`
- ログへ回答本文・氏名・メール・電話・住所・token・session secret・Drive URL を出さない

## 6. 本番配置前に必要なこと（4H）

1. `intake.smartlaboworks.com` のサブドメイン追加
2. 無料SSLの発行・有効化
3. **`.user.ini` の設定**（`display_errors=Off` / `display_startup_errors=Off` /
   `log_errors=On` / `error_log` は **public_html の外**）
4. `private/` をドキュメントルートの**1つ上**へ配置（権限 700 / 設定ファイル 600）
5. `private/intake-config.php` を作成し、**別々に生成した**鍵を設定する
   ```bash
   php -r "echo bin2hex(random_bytes(32));"   # ip_hmac_key 用
   php -r "echo bin2hex(random_bytes(32));"   # enc_key 用
   ```
6. `mail()` の実送信テスト（宛先は当社 info@ のみ）

## 7. 最終提出の冪等化（4B-R1 で実装済み）

`POST /submit` は **`submission_id`（クライアント生成 UUID v4）が必須**である。
SSOT v1.3 §6.4 のとおり、4層で二重送信を防ぐ。

```text
1. 画面      送信ボタンの無効化（4C）
2. submission_id  同じ要求の再送を、同じ結果で返す  ← 本体
3. status         別要求としての二重提出を弾く
4. DB             UNIQUE(intake_case_id, submission_id) WHERE submission_id IS NOT NULL
```

| 入力・状態 | HTTP | 副作用 |
|---|---|---|
| `submission_id` 欠落 / UUID v4 でない | **400** | なし |
| 初回・必須条件を満たす | 200 | 履歴1件＋監査1件＋`submitted` へ遷移（**同一トランザクション**） |
| 初回・必須条件を満たさない | 200（不足パスのみ） | 履歴1件（`validation_error`）。状態は変えない |
| **同一 `submission_id` の再送** | 200（初回と同じ結果） | **なし**。履歴も監査も増やさない |
| **異なる `submission_id`** で提出済み案件へ | **409** | なし。既存 `submission_id` も提出済み内容も返さない |
| 同時送信で一意制約に競合 | 200（記録済みの結果） | なし。例外を外へ出さず固定応答へ変換 |

★**4C が守る生成契約**: 「送信」を押すたびに新しい UUID v4 を作る。
同じ値を送ってよいのは**同じ要求の再試行のときだけ**。
検証エラーを直してからの送り直しは**新しい提出要求**なので、新しい値を作る。

★`submission_id` は**ログ・監査・エラー本文・通知メールへ出さない**。
ログ側は allowlist（`Logger::ALLOWED`）に入れないことで**構造的に**防いでいる。

DBは `PRAGMA user_version` で版を持つ（**v2**）。migration は**再実行可能**で、
`ALTER TABLE ADD COLUMN` は `PRAGMA table_info` で列の有無を見てから実行する。
既存行の `submission_id` は **NULL のまま**残る（部分索引の対象外）。

## 8. 4B / 4B-R1 で実装していないもの

店舗入力画面（4C）／Google Drive 実連携（命名規則の設計反映のみ・SSOT §7.1）／
通知メールの実送信／管理画面とその認証（4D）／
Smart Labo Operations との連携（OPS-4）／Stripe（一切）
