# intake-api — 店舗向けHP導入フォーム（HP-ONBOARDING-4B 〜 -4F-R3）

```text
STATUS : ローカル実装（受付API＋店舗入力画面＋内部確認画面＋保持削除）。**本番未配置**
SSOT   : docs/website/HP_ONBOARDING_INTAKE_DATA_MODEL_V1.md **v1.9**
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
| **`intake-api/`** | **店舗向けHP導入フォームの受付・入力画面・内部確認画面**（本ディレクトリ） | ローカル実装 |

- 設定・DB・レート制限の記録は**それぞれ独立**させる。既存APIと共有しない。
- 既存APIのコードを import しない（考え方だけを踏襲する）。

流用した考え方:
- 生IPを保存せず HMAC 化する／レート制限をファイルで持つ … `contact-api/public/lib/security.php`
- `public/` と `private/` を分け、`private/` は Web から全拒否 … `contact-api`
- 外部ライブラリを使わない自前テストランナー … `signup-api/tests/`

## 2. ディレクトリ

```text
intake-api/
├── dev/                     ローカル専用（★本番へ配置しない）
│   ├── php.ini.example      php.ini のテンプレート（php.ini 実体は追跡しない）
│   ├── preview-env.php      ローカル確認の設定（使い捨て鍵・使い捨てDB）
│   ├── preview-seed.php     架空の案件・ご案内リンク・管理者を1組つくる
│   └── router.php           PHP内蔵サーバー用の振り分け（本番は .htaccess）
├── public/                  ドキュメントルートへ置く
│   ├── index.php            受付API・内部確認画面のフロントコントローラ
│   ├── start.html           ご案内リンクの入口（4C）
│   ├── form.html            入力画面（4C）
│   └── assets/              CSS / JS（外部CDNを使わない）
├── private/                 ★public_html の外へ置く（設定・DB・ログ・ratelimit）
├── src/                     アプリ本体
│   ├── Admin/               内部確認画面（4D）。HTML の組み立ては View に集約
│   ├── Http/ Service/ Support/
│   └── ...
└── tests/                   自動テスト（外部ライブラリなし）
    ├── test-*.php           サーバー側 ＋ 画面の静的な取り決め
    └── js/                  画面のふるまい（Node で実行。npm install 不要）
```

## 3. ローカルでの動かし方

```bash
# 1) php.ini を用意する（extension_dir を自分の環境の絶対パスへ）
cp intake-api/dev/php.ini.example intake-api/dev/php.ini

# 2) サーバー側のテスト
php -c intake-api/dev/php.ini intake-api/tests/run-tests.php

# 3) 画面側のテスト（Node。外部ライブラリを入れない）
node intake-api/tests/js/run-tests.mjs
```

必要な拡張: `pdo_sqlite` / `sqlite3` / `openssl` / `mbstring`
（Windows の PHP には DLL が同梱されている。php.ini で有効化するだけでよい）

★テストは `tests/.tmp/` 配下に使い捨てのDBを作る。**本番・既存DBへは接続しない。**
★テストの鍵は固定のダミーのみ。実鍵をテストへ持ち込まない。

### 画面をブラウザで確認する（4C）

```bash
# 1) 架空の案件とご案内リンクを1本つくる（--fresh で作り直す）
php -c intake-api/dev/php.ini intake-api/dev/preview-seed.php --fresh

# 2) 別のターミナルでサーバーを起動する（127.0.0.1 のみ）
php -c intake-api/dev/php.ini -S 127.0.0.1:8788 -t intake-api/public intake-api/dev/router.php
```

1) が表示するリンク（`http://127.0.0.1:8788/start#…`）をブラウザで開く。

- 使い捨てDBは `dev/.preview/`（`.gitignore` 済み）。**`tests/.tmp/` へ置かない**
  （`run-tests.php` が毎回そこを空にするため、確認中の案件ごと消えてしまう）
- 架空の店舗データのみ。**実在の店舗・個人情報を使わない**
- リンクは端末内だけのもの。**共有しない**。外部へ公開しない

## 4. endpoint（4B の範囲）

| method | path | 内容 |
|---|---|---|
| POST | `/session/start` | `/start#<token>` から渡された token を body で受け、session Cookie へ交換 |
| GET | `/case` | 認証済み案件と回答の取得 |
| POST | `/answers/save` | 途中保存（楽観ロック） |
| POST | `/submit` | 最終提出。**`submission_id`（UUID v4）が必須**（下の §7） |
| POST | `/session/logout` | session の個別失効 |
| POST | `/drive/confirm` | 素材アップロード完了の申告（4D）。`{"confirmed": true}` のみ受理・冪等 |

`GET /case` は 4D-R1 で次を返すようになった（**認証済みの本人にだけ**）。

| キー | 内容 |
|---|---|
| `revision_requests` | **`open` の修正依頼だけ**（`request_number` / `requested_paths` / `message` / `created_at`） |
| `drive` | 素材フォルダの案内（`folder_url` / `folder_label` / `shared_email`） |

★どちらも**ログ・監査・書き出しへは出さない**。`resolved` の依頼本文は返さない。

> ★同一オリジンの **GET には `Origin` が付かない**（ブラウザの仕様）。
> さらに画面は `Referrer-Policy: no-referrer`（SSOT §10.4）なので **`Referer` も付かない**。
> そのため GET だけは `Sec-Fetch-Site: same-origin` でも受け付ける。
> **POST は従来どおり `Origin` の厳格検査だけで守る。**
>
> ★`Sec-Fetch-*` は **Forbidden request header** であり、**ブラウザ内の JavaScript からは設定できない**。
> ただし **curl 等の非ブラウザからは任意に構成できる**ので、単独では守りにならない。
> CSRF token・session・Origin 検査・`SameSite=Strict` と組み合わせた多層防御を前提とする。

## 4.1 画面（4C の範囲）

| path | 画面 |
|---|---|
| `/start#<ご案内の文字列>` | 入口。値を読んで即座に URL から消し、Cookie へ交換して `/form` へ移す |
| `/form` | 12ステップの入力・確認・提出・完了・終了 |

| ステップ | 分類 |
|---|---|
| 1〜11 | `basic` / `business_hours` / `menus` / `staff` / `promotion` / `design` /<br>`web_links` / `contact_form` / `privacy` / `image_metadata` / `rights` |
| 12 | 入力内容の確認・提出 |

- 画面文言に **`token` / `session` / `HP Intake` / `submission_id` を出さない**
- 自動保存は「**最終変更から30秒**」「**ステップ移動**」「**保存ボタン**」の3契機だけ
- 保存は**直列**。409 は上書きせず利用者に選ばせる。**429 で自動再試行しない**
- 「入力を終了する」→ `POST /session/logout` → Cookie 失効 → 終了画面（SSOT §2.6-12）

## 4.2 対応ブラウザ（SSOT v1.4 §10.9）

| # | 決まり |
|---|---|
| 1 | 対応対象は**現行の Chrome / Edge / Firefox / Safari** |
| 2 | `Sec-Fetch-Site` に対応しない古いブラウザは**サポート対象外** |
| 3 | `Referrer-Policy: no-referrer` のため、**`Referer` は同一オリジンでも送られない**。<br>「Sec-Fetch-Site 非対応でも Referer 経由で通る」とは限らない。**この前提に依存しない** |
| 4 | `GET /case` の `Sec-Fetch-Site` 受理は **4E で改めて検証**する |
| 5 | **POST の Origin 厳格検査は変更しない**（対応ブラウザの整理を理由に緩めない） |

## 4.3 内部確認画面（4D の範囲）

| method | path | 内容 |
|---|---|---|
| GET / POST | `/admin/login` | ログイン |
| POST | `/admin/logout` | ログアウト（★GET では受けない） |
| GET | `/admin/` | 案件一覧（**回答本文を出さない**） |
| GET | `/admin/case?case=…` | 案件詳細（11分類・不足項目・提出履歴・修正依頼） |
| POST | `/admin/status` | `reviewed` への変更 |
| GET | `/admin/revision?case=…` | 修正依頼の入力（4D-R1） |
| POST | `/admin/revision/send` | 修正依頼の確定 ＋ `needs_revision` へ差し戻し（4D-R1） |
| GET | `/admin/new` | 新しい案件の入力（4D-R1） |
| POST | `/admin/create` | 案件作成 ＋ ご案内リンクの発行（4D-R1） |
| GET | `/admin/reissue?case=…` | ご案内リンク再発行の確認（4D-R2） |
| POST | `/admin/reissue/send` | 再発行の実行（旧token・店舗sessionを失効。4D-R2） |
| GET | `/admin/export?case=…` | 検証済み JSON のダウンロード |
| GET | `/admin/lock?case=…` | 入力確定（`locked`）の確認（4F） |
| POST | `/admin/lock/send` | 確定の実行（token・店舗sessionを失効。4F） |
| GET | `/admin/retention` | 保持期限の一覧（4F） |
| POST | `/admin/retention/due` | 削除予定日の登録・変更（4F） |
| GET | `/admin/purge?case=…` | 機密情報の削除の確認（4F） |
| POST | `/admin/purge/send` | 削除の実行（**元に戻せない**。4F） |
| GET | `/admin/maintenance` | 監査13か月削除・管理session清掃の件数（4F） |
| POST | `/admin/maintenance/audit` | 監査の13か月削除（4F） |
| POST | `/admin/maintenance/sessions` | 期限切れ管理sessionの削除（4F） |
| GET | `/admin/settings?case=…` | 制作設定（Smart Labo 入力・4F-R3） |
| POST | `/admin/settings/save` | 制作設定の保存（4F-R3） |

- 代表1名のみ。資格情報は `private/intake-config.php`（**hash だけ**。§6）
- **未設定なら管理画面ごと 404**（fail closed）。店舗向けAPIはそのまま動く
- session は `intake_admin_sessions`（7表目）。**idle 30分 / 絶対 8時間**／ログイン時に再生成
- 店舗の session を管理画面へ流用しない。逆も行わない
- 状態を変える操作は**すべて POST ＋ CSRF ＋ Origin 検査**。GET で状態を変えない
- ログインは **HMAC化IP で 10分5回**。失敗の文言は常に同じ（IDの存在を漏らさない）
- 画面に JavaScript を持ち込まない（`script-src 'self'` と噛み合わせる）

### 検証済み書き出し（SSOT v1.4 §11.3）

allowlist 方式で組み立て、`Content-Disposition: attachment` で返す。
**含めないもの**: token / token_hash / session secret / session_hash / CSRF /
生IP / ip_hmac / Cookie / 暗号鍵 / password hash / rate limit /
**Drive URL** / **DBの内部ID** / Stripe / Operations / AI Sales / 内部ログ / 監査明細。

```text
X-Intake-Export-Sha256: <本文の SHA-256>
```

★これは**独自ヘッダー**である。取込側（OPS-4）の検証用に付けているが、
**このヘッダーが無くても取込が壊れない形**にしておくこと。

`reviewed_at` は `intake_cases` に列が無い。**`intake_submission_history` の
`reviewed` 行の時刻から導いている**（書き出しのためだけに列を増やさない）。

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
   `log_errors=On` / `error_log` は **public_html の外** / **`expose_php=Off`**）
   - ★`expose_php` は `PHP_INI_SYSTEM` のため、共用サーバーでは `.user.ini` から
     **効かないことがある**。`X-Powered-By` が消えたかを**実機で確認**する。
     消えない場合は `.htaccess` の `Header always unset X-Powered-By` が受け止める
4. `private/` をドキュメントルートの**1つ上**へ配置（権限 700 / 設定ファイル 600）
5. `private/intake-config.php` を作成し、**別々に生成した**鍵を設定する
   ```bash
   php -r "echo bin2hex(random_bytes(32));"   # ip_hmac_key 用
   php -r "echo bin2hex(random_bytes(32));"   # enc_key 用
   ```
6. **管理者の資格情報を設定する**（SSOT v1.4 §10.8）
   ```bash
   php -r "echo password_hash('ここに決めたパスワード', PASSWORD_ARGON2ID), PHP_EOL;"
   ```
   - `admin_id` と `admin_password_hash` を `private/intake-config.php` へ置く
   - **平文パスワードを書かない。** 平文を置いた場合は hash として受け付けず、
     管理画面は動かない（fail closed）
   - **実パスワード・実 hash を Git へ入れない**
7. `mail()` の実送信テスト（宛先は当社 info@ のみ）
8. **保持期限による削除は、既定のまま（無効）で配置する**（SSOT v1.7 §9.8）
   - `retention_actions_enabled` と `backup_policy_confirmed` は**既定 false**
   - **`backup_policy_confirmed` を 4G より前に true にしない。**
     バックアップの世代・削除方針が決まる前に削除すると、
     古い世代から**消したはずの回答が復元できてしまう**

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

## 8. 4B / 4B-R1 / 4C / 4D で実装していないもの

案件作成・ご案内リンク発行の画面（管理画面からは行えない。CLI と `dev/preview-seed.php` のみ）／
`locked` / `closed` への遷移（画面から行わない）／
Google Drive 実連携（**案内文だけ**。API へ接続しない・SSOT §7.1）／
画像ファイルの受け取り（intake は本体を持たない）／通知メールの実送信／
Smart Labo Operations との連携（OPS-4。**書き出しファイルの受け渡しまで**）／
Stripe（一切）／本番配置・サブドメイン・SSL（4H）

### 修正依頼（4D-R1・SSOT v1.5 §2.8）

差し戻しの理由は **`intake_revision_requests`（8表目）**が持つ。回答欄へ押し込まない。

| # | 決まり |
|---|---|
| 1 | 対象項目は **§3 の正式パス129件**（`src/AnswerPaths.php`）だけ。未知パスを含む要求は**丸ごと拒否** |
| 2 | `AnswerPaths.php` は `public/assets/lib/schema.js` から**機械的に生成**した。**手で書き換えない** |
| 3 | メッセージは**1000文字まで**（切り捨てず拒否）。**ログ・監査・書き出しへ出さない** |
| 4 | 状態変更と依頼の作成は**同一トランザクション** |
| 5 | 店舗の**再提出が成功したら** `open` を `resolved` にする。過去の依頼は消さない |
| 6 | 店舗へ返すのは `open` のものだけ |

### 案件作成とご案内リンク（4D-R1）

管理画面の「新しいHP制作案件」から作る。**CLI は運用にしない**。

- 案件番号は**サーバーが採番**する（`HP-YYYYMM-NNNN`）。店舗名を含めない
- token は既存の `TokenService`（`random_bytes(32)` / DBには hash のみ）
- **平文は作成直後の画面に1回だけ**。再表示の経路を作らない
- 二重送信は **CSRF token の作り直し**で止める（同じ画面を再送しても通らない）
- 紛失時は**発行し直す**（旧 token と関連 session は失効する）

### ご案内リンクの再発行（4D-R2・SSOT v1.6 §4.4.1）

平文は発行直後に1回しか出ないため、**受け取れなかったときの回復手段は再発行だけ**である。
「以前の token を復元する」機能は作らない（復元できる＝どこかに平文が残っている、ということ）。

| 状態 | 再発行 |
|---|---|
| `draft` / `needs_revision` | **可** |
| `submitted` / `reviewed` | **不可**。修正が要るなら**先に「修正を依頼する」で差し戻す** |
| `locked` / `closed` / 未知 | **不可**（fail closed） |

実行は `TokenService::reissue()` が**同一トランザクション**で行う。

1. 状態をトランザクションの中で再確認
2. 有効な token をすべて失効
3. その token から出た**店舗 session をすべて失効**
4. `random_bytes(32)` で新 token
5. DBへは **SHA-256 hash のみ**（期限は発行から14日）
6. 監査 `token_reissued` を1件（初回の `token_issued` と区別する）

| # | 守っていること |
|---|---|
| 1 | **回答・`version`・提出履歴・修正依頼・Drive 情報を消さない** |
| 2 | 実行前に**案件番号の再入力**を求め、`hash_equals()` で**完全一致**のときだけ実行 |
| 3 | 新しい平文は成功画面の `input` **1箇所だけ**。URL・Cookie・HTMLコメント・ログ・監査・DBへ出さない |
| 4 | 二重送信は **CSRF token の作り直し**で阻止（ブラウザ再送は 403、token は増えない） |
| 5 | **案件 ＋ HMAC化IP で 10分5回**。6回目は 429（自動再試行しない） |
| 6 | 通信断で応答を受け取れなくても、**もう一度再発行できる**（直前の token も失効し、監査が1件増える） |

### セキュリティ検査（4E）

総合検査の結果・endpoint 権限マトリクス・状態遷移マトリクス・Cookie 一覧・
rate limit 一覧・書き出しの allowlist / denylist・本番前の残存課題は
**`intake-api/docs/SECURITY_AUDIT_4E.md`** にまとめている。

- **P0 / P1 / P2 は 0件。** P3（本番前の hardening）が4件
- 検査で追加した自動テスト: `tests/test-security.php` ／ `tests/test-security-static.php`
- 仕様は SSOT が正。検査レポートは SSOT を上書きしない

### 回答の正式構造と必須（4F-R1 / 4F-R3・SSOT v1.9 §3.0.1 / §3.0.2）

**正は `public/assets/lib/schema.js`。** PHP 側は生成物である。

**正式パスは 134 件**（店舗 129 ＋ Smart Labo 設定 5）。
`promotion.industry` は **Phase 1 の対象外**なので含めない（SSOT §3.5）。

**必須は1種類ではない**（SSOT §3.0.2）。

| 種別 | 誰が・いつまでに | 満たし方 | 件数 |
|---|---|---|---|
| `STORE_REQUIRED_NON_EMPTY` | 店舗が提出するまでに | 値を入れる／能動的に選ぶ | 39 |
| `STORE_REQUIRED_KEY_ALLOW_EMPTY` | 店舗が提出するまでに | **キーがあればよい**（`false` も回答） | 1 |
| `ADMIN_REQUIRED_FOR_EXPORT` | Smart Labo が書き出しまでに | キーがあればよい（該当無しも記録する） | 5 |
| `ARRAY_ELEMENT_REQUIRED` | 要素があるときだけ | 要素の中で満たす | 19 |
| `OPTIONAL` | — | 欠落してよい | 89 |

- **`false` は「欠落」ではない。**「掲載しない」「予約を受けない」は答えである
- **`null` / `""` / 語彙外は未回答。** enum は店舗が選ぶまで未回答（`none` / `no` も、選べば回答）
- **`address_visibility` の `full` と `map_display` の `show` を自動で選ばない。**
  住所と地図を、本人の能動的な選択なしに公開側へ回さない
- 語彙が決まっている項目は、**語彙外の値を保存そのものから拒否**する

**画面・API・管理画面・書き出しが同じ集合を見る。**
v1.8 まではサーバーが22件しか見ておらず、画面（45件）とずれていた。
通常操作では画面が止めるが、**API を直接呼べば素通り**した（4F-R2 で判明）。

```bash
node intake-api/dev/generate-answer-schema.mjs
```

- §3 を変えるときは **schema.js を直してから作り直す**
- `src/AnswerSchema.php` は**生成物**。手で書き換えない（次の生成で消える）
- `AnswerPaths::ALL` は `AnswerSchema::PATHS` を指すだけ。一覧を書き写さない
- 一致（11分類・129パス・配列要素の許可キー・型）と**生成の冪等性**は
  `tests/test-answer-schema.php` が固定している
- 外部ライブラリを増やさない（Node の標準機能だけで生成する）

**受け取るとき（`POST /answers/save`）**

未知キーが**1件でも**あれば、**その要求の全体を 400 で拒否**する。

- 分類名だけでなく、**分類の中身のキー**も見る（入れ子・配列要素の中まで）
- 未知キーだけを黙って捨てて保存しない
- 正常な値が混ざっていても、**正常な分だけの部分保存をしない**
- 型（`scalar` / `bool` / `list` / `object` / `objects`）が違えば拒否
- 判定は**保存トランザクションより前**。DBも `version` も監査も動かない
- 未知キーの**名前も値も**、応答・ログ・監査へ出さない
- `__proto__` / `constructor` / `prototype` は「一覧に無いキー」として落ちる
  （特別扱いの分岐を書かない。書けば、書き忘れた名前が残る）

### Smart Labo 設定（4F-R3・SSOT v1.9 §3.12）

`web_links.salon_booking_url` ／ `privacy.destination` ／ `privacy.storage` ／
`privacy.external_services` ／ `privacy.consent_checkbox` の5件。

- **店舗の入力項目ではない。** 店舗画面に欄を作らず、`GET /case` でも返さない
- **店舗の `POST /answers/save` から変更できない**（混ざっていたら要求ごと拒否）
- 逆に、店舗が分類をまるごと保存しても**この5件は消えない**
- 設定は管理画面 `/admin/settings` から。**認証・CSRF・Origin・案件番号の再入力**が要る
- 設定できるのは **`reviewed` / `locked`**。`closed`・削除済みは変更しない
- **店舗の提出は妨げない。** 効くのは**検証済みJSONの書き出し直前**だけ
- 「設定した」＝**キーがあること**。該当が無い場合も空のまま保存して記録を残す
- **値をログにも監査にも出さない**（監査は `admin_settings_saved` / `ok` だけ）
- 保存先は既存の `intake_answers` の JSON 内。**新しい表も列も作らない**

**出すとき（`GET /case`・管理画面・書き出し）**

4F-R1 より前に入った未知キーが DB に残っていても、**出さない**。

- `AnswerService::get()` で絞るので、店舗の復元・管理詳細・書き出しに一度に効く
- 書き出しは**外へ出る唯一の口**なので、`ExportService` でも**もう一度**絞る
- 未知キーが**あるだけで画面や書き出しを失敗させない**。正式値だけを出す
- **自動清掃はしない。** 既存行は読むだけで、書き換えない

### 保持期限と削除（4F-PRE・SSOT v1.7 §9）

**誰が何を持つか**

- 公開日も公開承認も **HP Intake へ保存しない**。Operations（未完成の間は標準管理票）の責任
- Intake が持つのは **`retention_delete_due` の1列だけ**。人が**手動で登録**する
- **そこから公開日を逆算しない**
- **Google Drive の実ファイル削除は Intake の責任外**。Drive API へ接続しない

**削除の流れ**

`reviewed` → 確定（`locked`）→ 削除予定日の登録 → 期限到来 →
確認画面 → `DELETE <案件番号>` の完全一致入力 → 実行

- **自動実行しない。** cron・起動時処理・バッチを作らない
- 実行には **`retention_actions_enabled` と `backup_policy_confirmed` が両方 true**
  （**既定はどちらも false**。片方でも欠ければボタンも出さない）
- 5表（店舗session / token / 回答 / 修正依頼 / 提出履歴）を**行ごと物理削除**
- Drive URL と共有先メールの**暗号文を NULL 化**（復号できる参照を残さない）
- 案件行に残るのは**案件番号・状態・日付だけ**（SSOT §9.4-2 の allowlist）
- すべて**同一トランザクション**。途中で失敗したら**全部戻る**
- **`PRAGMA secure_delete = ON`**。DELETE だけでは内容が DB ファイルのページ上に残る
- 削除済み案件は **書き出し・token 発行・再発行・session 発行・状態変更をすべて拒否**

**通し確認（使い捨てDBだけで動く）**

```bash
php -c intake-api/dev/php.ini intake-api/dev/retention-walkthrough.php
```

案件作成から削除・監査清掃・session 清掃までの20段を確かめる。
毎回新しい使い捨てDBを作り、**終わったら消す**。
`dev/.preview/` を含む**既存DBへは一切接続しない**。

### 全工程の通し確認（4F）

```bash
php -c intake-api/dev/php.ini intake-api/dev/e2e-walkthrough.php
```

架空店舗1件で、管理者の案件作成から削除済み案件の全面拒否までを
**88項目**確かめる（A 案件開始／B 初回交換／C 入力・途中保存／
D Drive 申告／E 提出／F 管理確認・書き出し／G 修正依頼・再提出／
H リンク再発行／I 確定／J 保持期限／K closed 後の拒否／L 保守）。

- 使い捨てDBを**3つ**作り、終了時にすべて消す
- 架空の店舗名・`example.invalid` のメール・架空のフォルダURLだけを使う
- 外部（XServer・Drive API・Stripe・Operations・AI Sales・実メール）へ接続しない
- **J は本番想定の既定状態（フラグ false）で削除できないこと**を確かめる。
  削除が成功することの確認は `retention-walkthrough.php` が担当する

ブラウザからしか見えない項目（`location.hash` / `localStorage` /
`document.cookie` / console / Referer / 実際の描画）は、
`dev/preview-seed.php` ＋ ローカルサーバーで**実ブラウザ**から確認する。

### 残っている宿題（4F 以降）

| # | 事項 | いまの扱い |
|---|---|---|
| ~~1~~ | ~~`GET /case` の `Sec-Fetch-Site` 受理~~ | **4E で再検証済み**（P0/P1/P2 なし） |
| ~~2~~ | ~~`locked` への遷移~~ | **4F-PRE で実装**（確認画面＋案件番号再入力。SSOT §5.1） |
| 3 | `closed` への通常遷移 | **作らない**。`closed` は削除完了時に設定される（SSOT §9.3-3） |
| 4 | 中止案件を `closed` にする経路 | 未定（SSOT §12.2-12）。運用が固まってから決める |
| 5 | 管理 session 清掃の自動化 | Phase 1 は保守画面からの明示操作（SSOT §2.7-10） |
| 6 | `expose_php` が実機で効くか | **4H で確認**。ローカルの PHP 内蔵サーバーでは<br>`.user.ini`（`PHP_INI_SYSTEM`）も `.htaccess`（Apache）も適用されないため、<br>**この2つの対策はローカルでは検証できない**（4F で実測） |
| 7 | 本番バックアップの世代・削除方針 | **4G**。確定するまで `backup_policy_confirmed` を true にしない |
| ~~8~~ | ~~分類の中の**未知キー**が書き出しへ通る~~ | **4F-R1 で是正**（SSOT v1.8 §3.0.1）。<br>保存は**要求全体を拒否**、読み出し・書き出しは**出力しない** |
| ~~9~~ | ~~補助ボタンの高さが 40px~~ | **4F-R1 で是正**（SSOT v1.8 §10.10）。店舗・管理とも 48px |
| 10 | 既存DBに残った未知キーの**清掃機能** | **作らない**（SSOT v1.9 §3.0.1）。読み出しで除くだけで、既存行は触らない。<br>必要になったら本書を改定してから作る |
| ~~11~~ | ~~必須が SSOT と実装で食い違う~~ | **4F-R3 で是正**（SSOT v1.9 §3.0.2）。<br>必須は生成物ひとつ。画面・API・管理・書き出しが同じ集合を見る |
| 12 | `promotion.industry`（業種別） | **Phase 1 対象外**（SSOT v1.9 §3.5）。Phase 2 以降で扱うかを判断する |
| 13 | `ADMIN_REQUIRED_FOR_EXPORT` の厳しさ | いまは**キーの存在**で満たす。<br>「送信先・保管方法は空にできない」等の内容条件を課すかは、<br>Operations（OPS-4）の取込要件が決まってから判断する |
