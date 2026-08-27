# intake-api — 店舗向けHP導入フォーム（HP-ONBOARDING-4B 〜 -4D-R1）

```text
STATUS : ローカル実装（受付API＋店舗入力画面＋内部確認画面）。**本番未配置**
SSOT   : docs/website/HP_ONBOARDING_INTAKE_DATA_MODEL_V1.md **v1.5**
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
| GET | `/admin/export?case=…` | 検証済み JSON のダウンロード |

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
   `log_errors=On` / `error_log` は **public_html の外**）
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

### 残っている宿題（4E 以降）

| # | 事項 | いまの扱い |
|---|---|---|
| 1 | `GET /case` の `Sec-Fetch-Site` 受理 | **4E でセキュリティ再検証**（SSOT §10.9-4） |
| 2 | 管理画面からの **token 再発行** | 4D-R1 では**未実装**。初回発行のみ。<br>再発行が要る場合は `TokenService::issue()` を呼ぶ導線を 4E 以降で足す |
| 3 | `locked` / `closed` への遷移 | 画面から行わない（誤操作の影響が大きいため） |
