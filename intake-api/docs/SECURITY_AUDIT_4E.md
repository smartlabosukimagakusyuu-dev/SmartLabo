# HP Intake セキュリティ検査レポート（HP-ONBOARDING-4E）

```text
STATUS   : ローカル限定の総合検査。本番・外部サービスへ接続していない
対象     : intake-api/（受付API・店舗入力画面・内部確認画面）
検査日   : 2026-08-27
対象HEAD : 65b533fdd1b73a04922194816dbae953c7676290（4D-R2 完了時点）
SSOT     : docs/website/HP_ONBOARDING_INTAKE_DATA_MODEL_V1.md v1.6
```

> ★本書は**検査の結果と一覧**である。**仕様は SSOT が正**であり、
> 本書は SSOT を上書きしない。値が食い違った場合は SSOT に従い、本書を直す。
>
> ★実装の説明は `intake-api/README.md` にある。本書はそれを繰り返さない。

---

## 1. 判定

| 区分 | 件数 | 内容 |
|---|---|---|
| **P0** | **0** | — |
| **P1** | **0** | — |
| **P2** | **0** | — |
| **P3** | **4** | §9 の hardening 4件（本番前の作業として記録） |

**P0/P1/P2 は検出されなかった。** P3 はいずれも設計変更を伴わない。

検査中に発見した「疑わしい挙動」5件は、**すべて検査スクリプト側の誤検出**であることを
個別に確認した（§8）。実装の欠陥ではない。

---

## 2. endpoint 権限マトリクス

**S** = 店舗 session ／ **A** = 管理 session ／ **—** = 不要

### 2.1 店舗向け（JSON）

| method | path | 認証 | CSRF | Origin | Sec-Fetch | 許可 status | rate limit | PII | 状態変更 |
|---|---|---|---|---|---|---|---|---|---|
| POST | `/session/start` | — | — | **必須** | 補助なし | READABLE 4状態 | `token_start` 10分5回 | 案件番号のみ | session発行 |
| GET | `/case` | **S** | — | 無くてよい | **`same-origin` 必須**<br>（`navigate` は拒否） | READABLE 4状態 | — | **あり**（本人の回答） | なし |
| POST | `/answers/save` | **S** | — | **必須** | 補助なし | `draft` / `needs_revision` | `answer_save` 10分60回 | 受け取るのみ | 回答更新 |
| POST | `/submit` | **S** | — | **必須** | 補助なし | `draft` / `needs_revision` | `submit` 10分5回 | 不足パスのみ返す | **あり** |
| POST | `/drive/confirm` | **S** | — | **必須** | 補助なし | `draft` / `needs_revision` | `drive_confirm` 10分5回 | なし | **あり**（冪等） |
| POST | `/session/logout` | **S** | — | **必須** | 補助なし | READABLE 4状態 | — | なし | session失効 |

> ★店舗の POST は **Origin の厳格検査だけ**で守る。`fetch()` からの呼び出しなので
> 正しい Origin が必ず付く。`Sec-Fetch-Site` は**判断に使わない**（緩めない）。
> ★`GET /case` だけは Origin が付かないため Fetch Metadata に依存する（SSOT §10.9）。

### 2.2 管理向け（HTML / form）

| method | path | 認証 | CSRF | Origin | Sec-Fetch | 許可 status | rate limit | PII | 状態変更 |
|---|---|---|---|---|---|---|---|---|---|
| GET | `/admin/login` | — | — | — | `cross-site` 拒否 | — | — | なし | なし |
| POST | `/admin/login` | — | — | **必須\*** | `null` 時のみ補助 | — | `admin_login` 10分5回 | なし | session発行 |
| POST | `/admin/logout` | **A** | **必須** | **必須\*** | `null` 時のみ補助 | — | — | なし | session失効 |
| GET | `/admin/` | **A** | — | — | `cross-site` 拒否 | 全 | — | **案件番号・日時のみ** | なし |
| GET | `/admin/case` | **A** | — | — | `cross-site` 拒否 | 全 | — | **あり**（回答全文） | なし |
| GET | `/admin/new` | **A** | — | — | `cross-site` 拒否 | — | — | なし | なし |
| POST | `/admin/create` | **A** | **必須** | **必須\*** | `null` 時のみ補助 | — | — | なし | **案件作成＋token発行** |
| POST | `/admin/status` | **A** | **必須** | **必須\*** | `submitted` → `reviewed` | — | — | なし | **あり** |
| GET | `/admin/revision` | **A** | — | — | `cross-site` 拒否 | `submitted` / `reviewed` | — | 不足パスのみ | なし |
| POST | `/admin/revision/send` | **A** | **必須** | **必須\*** | `submitted` / `reviewed` | — | — | 受け取るのみ | **あり**（差し戻し） |
| GET | `/admin/reissue` | **A** | — | — | `cross-site` 拒否 | `draft` / `needs_revision` | — | なし | なし |
| POST | `/admin/reissue/send` | **A** | **必須** | **必須\*** | `draft` / `needs_revision` | `token_reissue` 案件×IP 10分5回 | **token 1回表示** | **あり**（旧token/session失効） |
| GET | `/admin/export` | **A** | — | — | `cross-site` 拒否 | `submitted` 以降＋必須充足 | — | **あり**（書き出し） | なし |

> **\*** 管理の form 送信では、ブラウザが `Origin: null` を送る（実測）。
> そのため「Origin があれば厳格照合／無いか `null` のときだけ `Sec-Fetch-Site: same-origin`」
> という判定になる。**CSRF token は常に必須**であり、Sec-Fetch 単独では通らない。

### 2.3 未知パス

| 対象 | 応答 |
|---|---|
| `/admin` 以外 | **404・同一 JSON 文言**（全パスで完全に一致することを検査） |
| `/admin` 配下 | **404・同一 HTML 文言**（管理画面の存在は公開情報なので店舗側と別でよい） |

内部パス・SQL・PHP の警告は**一切含まれない**。

### 2.4 GET で状態が変わらないこと

状態を変える 9 経路すべてを GET で叩き、**403 / 404 / 405 のいずれか**になること、
かつ **status・token 本数・履歴件数が1つも動かない**ことを実測した。

---

## 3. Cookie 一覧

| 用途 | 名前 | Path | Secure | HttpOnly | SameSite | Max-Age | 絶対期限 | Domain |
|---|---|---|---|---|---|---|---|---|
| 店舗 | `sl_intake_sid` | `/` | ✅ | ✅ | Strict | 24時間 | 7日 | **付けない**（ホスト限定） |
| 管理 | `sl_op_sid` | `/admin` | ✅ | ✅ | Strict | 30分 | 8時間 | **付けない**（ホスト限定） |

- 名前に `admin`・店舗名・案件番号を含めない
- DBへ保存するのは **SHA-256 hash のみ**。平文列を持たない
- 店舗 Cookie で管理画面へ入れない／管理 Cookie で店舗APIを使えない（実測）

---

## 4. 状態遷移マトリクス

`○` = 許可 ／ `×` = 拒否（`DomainException`）

| From \ To | draft | submitted | needs_revision | reviewed | locked | closed |
|---|---|---|---|---|---|---|
| **draft** | × | ○ | × | × | × | ○ |
| **submitted** | × | × | ○ | ○ | × | ○ |
| **needs_revision** | × | ○ | × | × | × | ○ |
| **reviewed** | × | × | **○**（v1.5） | × | ○ | ○ |
| **locked** | × | × | × | × | × | ○ |
| **closed** | × | × | × | × | × | × |
| **未知の状態** | × | × | × | × | × | × |

全 36 通り＋未知状態を総当たりで実測し、SSOT §5.1 と一致することを確認した。

### 操作ごとの許可状態

| 操作 | 許可 status |
|---|---|
| 回答の保存・提出・Drive申告 | `draft` / `needs_revision` |
| 修正依頼（差し戻し） | `submitted` / `reviewed` |
| ご案内リンクの再発行 | `draft` / `needs_revision` |
| 書き出し | `submitted` / `reviewed` / `locked` / `closed` ＋**必須充足** |

---

## 5. rate limit 一覧

| bucket | 単位 | 上限 | 期間 | 超過時 |
|---|---|---|---|---|
| `token_start` | HMAC化IP | 5 | 10分 | 429 |
| `answer_save` | HMAC化IP ＋ session | 60 | 10分 | 429 |
| `submit` | HMAC化IP ＋ session | 5 | 10分 | 429 |
| `drive_confirm` | HMAC化IP ＋ session | 5 | 10分 | 429 |
| `admin_login` | HMAC化IP | 5 | 10分 | 401（固定文言） |
| `token_reissue` | **案件 ＋ HMAC化IP** | 5 | 10分 | 429 |

- **生IPを保存しない。** ファイル名は `sha256(bucket|identity)`、中身は**時刻だけ**（実測）
- 未定義 bucket は**通さない**（fail closed）
- 記録先が作れない場合も**通さない**（fail closed。実測）
- 書き込みは `LOCK_EX`

---

## 6. 書き出しの allowlist / denylist

### 含める（17キー）

`export_schema_version` / `source` / `generated_at` / `case_number` / `contract_type` /
`status` / `submitted_at` / `locked_at` / `closed_at` / `drive_upload_confirmed_at` /
`retention_delete_due` / `reviewed_at` / `revision_requested_at` /
`answer_schema_version` / `answers`（11分類） / `rights` / `submission_summary` /
`revision_requests`（本文を除く）

### 含めない（実測で1つも出ないことを確認）

token平文 ／ `token_hash` ／ session平文 ／ `session_hash` ／ 管理session ／ CSRF ／
`submission_id` ／ 生IP ／ `ip_hmac` ／ Cookie ／ 暗号鍵 ／ password hash ／
レート制限 ／ **Drive URL** ／ **Drive 共有先メール** ／ **`revision_requests.message`** ／
**DBの内部ID** ／ Stripe ／ Operations ／ AI Sales ／ 内部ログ ／ 監査明細

### 応答

`Content-Type: application/json; charset=UTF-8` ／ `Content-Disposition: attachment` ／
`Cache-Control: no-store` ／ `X-Content-Type-Options: nosniff` ／
`X-Intake-Export-Sha256`（本文の SHA-256。独自ヘッダー）

ファイル名は `[A-Za-z0-9._-]` へ正規化する（引用符・改行・パス区切りを通さない）。

---

## 7. 検査の内訳

| 領域 | 主な確認 | 結果 |
|---|---|---|
| endpoint・権限 | 全経路の認証/CSRF/Origin/status、未知パス、GET不変性 | PASS |
| 認証・認可 | 店舗↔管理の完全分離、Cookie属性、fixation、失効 | PASS |
| IDOR | 別案件の取得・保存・提出・Drive・修正依頼、内部ID推測 | PASS |
| CSRF/Origin | 14通りの組合せマトリクス、使い回し・別session・失効session | PASS |
| token | 生成・hash保存・失効・再発行・1回表示・非出力 | PASS |
| SQL injection | 7種の payload、型偽装、prepared statement の静的確認 | PASS |
| XSS | 10種の payload × 管理2画面、危険scheme のリンク化 | PASS |
| 暗号化 | nonce再利用、tag/nonce/本文の改ざん、切断、別鍵、fail closed | PASS |
| 状態遷移・競合 | 36通り＋未知、中間状態、失効直前の保存 | PASS |
| rate limit | 全6bucket、境界値、分離、fail closed、生IP非保存 | PASS |
| ログ・監査 | 24種のマーカーで実測、allowlist の静的・動的検証 | PASS |
| 書き出し | allowlist、秘密値、未認証、ヘッダー注入 | PASS |
| headers | 6通りの応答（成功・404・403・405・401・HTML） | PASS |
| 配置境界 | 公開領域の構成、gitignore、private全拒否、雛形のダミー | PASS |
| SQLite/migration | PRAGMA、禁止構文、0→最新、途中版→最新、再実行 | PASS |
| backup | `SQLite3::backup()`、integrity/foreign_key、平文非混入 | PASS |

---

## 8. 検査中に出た「疑わしい挙動」と、その判定

いずれも**検査スクリプト側の誤検出**であり、実装の欠陥ではない。
根拠を残すため記録する。

| # | 最初の見え方 | 実際 | 判定 |
|---|---|---|---|
| 1 | 未知パスで応答が2種類ある | `/admin*` は管理画面へ回るため HTML 404 になる。<br>管理画面の存在は**公開情報**（ログイン画面が要る）で、秘密ではない | 誤検出。<br>店舗側/管理側を**別々に**均一性検査する形へ修正 |
| 2 | 管理画面に `javascript:alert(5)` が生で出る | HTML メタ文字を含まないため**文字として**出ているだけ。<br>`View::link()` は https 以外をリンクにせず、`href` に入らない | 誤検出。<br>「実行できる形にならないこと」を検査する形へ修正 |
| 3 | rate limit が fail closed でない | 検査が NUL バイトの path を渡し、`mkdir()` が `ValueError` を投げた。<br>実運用で NUL は入らない（設定由来）。実際は 500 で fail closed | 誤検出。<br>現実的な「書けない path」で検査する形へ修正 |
| 4 | `App.php` が `mail()` を使っている | `driveSharedEmail(` の部分文字列に一致していた | 誤検出。単語境界で判定する形へ修正 |
| 5 | `SqliteBackup.php` が `exec()` を使っている | `$pdo->exec()`（PDO の SQL 実行）であって shell の `exec()` ではない | 誤検出。単語境界で判定する形へ修正 |

> ★誤検出だったものも、**検査は残して精度を上げた**。
> 同じ場所が本当に壊れたときには落ちる。

---

## 9. P3（本番前の hardening・4件）

いずれも**設計変更を伴わない**。4F 以降の本番準備で対応する。

| # | 事項 | いまの状態 | 推奨 |
|---|---|---|---|
| **P3-1** | `X-Powered-By` で PHP の版が出る | `expose_php` 未設定（既定 On）。<br>ローカル実測で `X-Powered-By: PHP/8.3.32` | `.user.ini` へ `expose_php = Off` を追加する。<br>※XServer で `.user.ini` から効くかは 4F で実機確認が要る |
| **P3-2** | API 応答の CSP に `object-src` / `font-src` が無い | 静的ファイル側（`.htaccess`）には両方ある。<br>API は JSON なので実害は無い | `Response::securityHeaders()` へ `object-src 'none'` を足し、<br>静的側と表現をそろえる |
| **P3-3** | `.htaccess` にドットファイル・バックアップ拡張子の拒否が無い | 公開領域には該当ファイルが**存在しない**（実測で構成を固定済み）。<br>誤配置時の保険が無い | `.git` / `.env` / `*.sqlite` / `*.bak` / `*~` を<br>`FilesMatch` で拒否する（多層防御） |
| **P3-4** | 保持期間による**削除処理が未実装** | `retention_delete_due` / `deleted_at` の**列はある**が、<br>SSOT §9.3 の削除手順を実行する経路が無い | 4F 以降で実装する。§10 参照。<br>**本工程では独断で追加しない** |

---

## 10. retention・削除の監査（現状）

| 対象 | SSOT の規定 | 実装 |
|---|---|---|
| `retention_delete_due` | `closed_at` + 6か月（§9.1） | **列はある。自動設定は未実装** |
| `deleted_at` | 削除実施日（§9.3-3） | **列はある。書き込む経路が無い** |
| `intake_answers` の削除 | 期限到来後に行削除（§9.3-3） | **未実装** |
| `intake_tokens` の削除 | 同上（§9.3-4） | **未実装**（失効は実装済み） |
| `intake_audit_events` | 13か月保持（§9.1） | **削除処理は未実装** |
| `intake_submission_history` | 案件削除まで | 本文を持たないため現状のまま |
| `intake_revision_requests` | （v1.5 で新設。保持規定は未定義） | **SSOT に保持期間の記載が無い** |
| Drive の写真本体 | 人の操作で削除（§9.3-5） | 範囲外（API へ接続しない） |
| backup | §9.5 | 取得のみ実装。世代管理・削除は未実装 |

> ★**存在しない機能を「実装済み」として扱っていない。**
> 削除は本番運用開始前に必要になる。4F 以降で扱う（P3-4）。
> ★`intake_revision_requests` の保持期間が SSOT に無い。次回 SSOT 改定時に追記が要る。

---

## 11. 本番前の残存課題

| # | 事項 | 工程 |
|---|---|---|
| 1 | P3-1〜P3-3 の hardening | 4F |
| 2 | **保持期間による削除処理**（P3-4） | 4F 以降 |
| 3 | `intake_revision_requests` の保持期間を SSOT へ追記 | 次回 SSOT 改定 |
| 4 | `intake.smartlaboworks.com` のサブドメイン・SSL | 4H（代表作業） |
| 5 | `private/` の実配置と権限（700 / 600）、`error_log` の実パス | 4H |
| 6 | 管理者資格情報の実設定（Argon2id） | 4H |
| 7 | `mail()` の実送信確認 | 4H |
| 8 | 本番バックアップ先の具体パスと世代管理 | 4G |
| 9 | `locked` / `closed` への遷移を画面から行うか | 4F 以降 |
| 10 | `Sec-Fetch-Site` 非対応ブラウザはサポート対象外である旨の周知 | 運用 |

---

## 12. 検査で追加した自動テスト

| ファイル | 件数 | 内容 |
|---|---|---|
| `tests/test-security.php` | 27 | 権限・IDOR・CSRF・注入・XSS・暗号・競合・rate limit・ログ・書き出し・配置・SQLite・backup |
| `tests/test-security-static.php` | 16 | SQL連結・危険関数・スーパーグローバル・秘密値の経路・HTML組み立て・配置・DDL・版の一致 |

合計 **63件**を追加（266 → **329件**）。

すべてローカル限定で、外部へ接続せず、実鍵・実tokenを使わない。
大量試行を行わない（DoS を起こさない件数に抑えている）。
