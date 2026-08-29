# 店舗向けHP導入フォーム データモデル・token設計 v1

```text
STATUS      : APPROVED / 4H-R0 実装済み（受付API・店舗入力画面・内部確認画面・運用フロー・保持削除・バックアップ・配置境界・提出通知）
VERSION     : v1.13（R13）
DATE        : 2026-08-26（v1.0 制定）／ 2026-08-27（v1.2〜v1.10 改定）／ 2026-08-28（v1.11・v1.12 改定）／ 2026-08-29（v1.13 改定）
工程        : HP-ONBOARDING-4A ／ -4A-R1（AI Sales 分離・Operations 境界確定）
              ／ -4B-PRE（XServer 実行環境の実測）／ -4A-R2（実測反映）
              ／ -4B（受付API 実装）／ -4B-R1（提出の冪等化）
              ／ -4C（店舗入力画面）／ -4D（内部確認・書き出し）
              ／ -4D-R1（修正依頼・案件作成・Drive 案内）
              ／ -4D-R2（ご案内リンクの再発行）／ -4E（セキュリティ総合検査）
              ／ -4F-PRE（本番前hardening・保持削除ライフサイクル）
              ／ -4F（架空店舗による全工程通し確認）
              ／ -4F-R1（回答JSONの厳格allowlist・操作領域48px）
              ／ -4F-R2（必須定義の差分調査・STOP）
              ／ -4F-R3（必須契約の統一・Smart Labo管理設定5項目）
              ／ -4F-R4（null保存の拒否・管理設定の内容条件）
              ／ -4G（バックアップ・復元確認・世代管理・保持削除整合性）
              ／ -4H-PRE（本番配置前の統合状態・配置物・作業手順の確定）
              ／ -4H-R0（配置境界の可変化・提出通知・preflight 分離）
              ／ **-4H-3（本体コードの本番配置・APP_ROOT 解決経路の実機確定・本改定）**
本番環境    : XServer 共用（PHP 8.3.33 / **SQLite 3.26.0** / pdo_sqlite・OpenSSL・mbstring 有効）
              ★実測日 2026-08-27。**VACUUM INTO は使用不可**（§2.0.1・§9.6）
対象        : intake.smartlaboworks.com（店舗向けHP導入フォーム）
構成        : HP-ONBOARDING-3 の **案C**（受付を Website側へ分離）
契約後管理  : 内部専用 **Smart Labo Operations**（社内管理上の仮称・未実装）
実装予定先  : SmartLabo リポジトリ `intake-api/`（本工程では作成しない）
保存先      : XServer 上のドキュメントルート**外** `private/intake.sqlite`
上位文書    : docs/website/HP_ONBOARDING_INTAKE_FORM_SPEC_V1.md（v1.2 R2・入力項目のSSOT）
              docs/website/WEBSITE_PRODUCTION_AND_MAINTENANCE_PRICE_V1.md（VERSION 3・価格と範囲）
本書の位置  : 上記2文書の**下位文書**。入力項目・価格・提供範囲を上書きしない
```

> ★本書は**データ構造と規則のSSOT**である。コード・DB・画面は本工程では作らない。
> 上位文書と齟齬が生じた場合は上位文書が優先する。

> ★本書に登場する店舗名・メールアドレス・URL・案件番号は**すべて架空**である。
> 実在の店舗・個人・顧客情報は一切記載しない。

---

## 0. 本書が前提とする正式決定事項

| # | 決定 |
|---|---|
| 1 | 構成は HP-ONBOARDING-3 の**案C**を採用する |
| 2 | 顧客向け画面と受付APIは **intake.smartlaboworks.com** に分離する |
| 3 | 実装は SmartLabo リポジトリ内の `intake-api/` を予定する |
| 4 | 保存先は XServer 上のドキュメントルート**外** `private/intake.sqlite` |
| 5 | 契約後のHP制作管理は**内部専用「Smart Labo Operations」**が担当する（社内管理上の仮称・未実装） |
| 6 | **HP Intake と Smart Labo Operations は責任を分離する** |
| 7 | **HP Intake から他システムへ、手動・自動を問わず連携しない**（書き出しデータの取込は OPS-4 で扱う） |
| 8 | 写真本体は **Smart Labo 所有の Google Drive** で受領する |
| 9 | **1店舗1フォルダ**とし、**店舗の指定メールアドレスだけに共有**する |
| 10 | **「リンクを知っている全員」への共有は禁止** |
| 11 | Google Drive URL は Smart Labo 側で設定し、**店舗の自由入力欄を作らない** |
| 12 | **Stripe情報・カード情報・公開承認を intake DB へ保存しない** |

### 0.1 HP-ONBOARDING-4A-R1 で確定した事項

| # | 決定 |
|---|---|
| 1 | **AI Sales は HP制作・契約後導入管理に使用しない** |
| 2 | **intake から AI Sales へ、手動・自動を問わず連携しない** |
| 3 | **AI Sales に Stripe参照情報・公開承認・権利同意・HP進捗を保存しない** |
| 4 | **AI Sales リポジトリは本工程および今後のHP工程で変更しない** |
| 5 | AI Sales は**営業支援専用**として独立させる |
| 6 | AI Sales の将来の商品化は**別ロードマップ・別工程**で扱う |
| 7 | 現在の社内営業DBを**将来の商品版へ持ち込まない** |
| 8 | HP契約後管理は内部専用 **Smart Labo Operations** が担当する |
| 9 | Smart Labo Operations は**顧客向けの商品名ではなく社内管理上の仮称** |
| 10 | **HP Intake と Operations は責任を分離する** |

> ★v1.0 に存在した「AI Sales を契約後管理の保存先・連携先とする記述」は、
> 本 R1 で**すべて撤回**した。撤回後の保存先は Smart Labo Operations である。

### 0.2 HP-ONBOARDING-4B-PRE の実測にもとづき確定した事項（2026-08-27）

**本番環境の実測値（XServer サーバーパネル／代表による手動確認）**

| 項目 | 実測 | 本書への影響 |
|---|---|---|
| PHP_VERSION | **8.3.33** | 前提を満たす |
| PDO / pdo_sqlite | **true / true** | **SQLite 採用を維持**（§2） |
| SQLite3 拡張 | **true** | **バックアップAPIに使用**（§9.6） |
| **SQLite library version** | **3.26.0** | **§2.0.1 の互換サブセットを適用** |
| **VACUUM INTO** | **false**（3.27.0 未満） | **§9.6 で撤回**。`SQLite3::backup()` へ変更 |
| JSON / OpenSSL / mbstring | true / **true** / true | AES-256-GCM（§7.3）・UTF-8検証（§3.0）が成立 |
| sodium | true | **使用しない**（ローカルとの環境差を作らないため。暗号は OpenSSL に統一） |
| random_bytes | true | token / session secret（§4） |
| mail() / sendmail_path | true / 設定あり | Phase 1 通知メール（**実送信は 4H で確認**） |
| session.save_path | 設定あり | Cookie セッション |
| domain root exists / writable | **true / true** | **public_html 外に private を配置できる**（§2.0-1） |
| .htaccess / HTTPS detection | true / true | HTTPS強制・直アクセス拒否（§10.4） |
| display_errors | **ON → 2026-08-27 に OFF へ変更済み** | §10.4・§10.6 に必須要件として明記 |

**確定した運用値**

| 項目 | 確定値 |
|---|---|
| 通知メール | Phase 1 は XServer の **mail()**。内容は **案件番号・提出日時・イベント種別のみ** |
| 通知に含めない | 回答本文／店舗名／氏名／メール／電話／住所／token／session secret／Drive URL |
| 自動保存 | **最終変更から30秒後** ＋ **ステップ移動時** ＋ **手動保存ボタン** |
| rate limit（無効token試行） | **HMAC化IP単位で 10分 5回** |
| rate limit（有効案件の保存） | **token/session ＋ HMAC化IP単位で 10分 60回** |
| rate limit（最終提出） | **10分 5回** |
| CORS | **許可しない** |
| 最大 body | **1MB** |
| 画像アップロード | **受けない**（写真本体は Google Drive・§7） |
| token 初回交換 | **`/start#<token>` → POST → Cookie 方式を正式採用**（§4.2・§4.7） |

**本番環境に関する記録（2026-08-27）**

- 代表が XServer サーバーパネルから **`smartlaboworks.com` の `display_errors` を ON → OFF** へ変更した。
  `display_startup_errors` は OFF を維持。**その他の php.ini 項目は変更していない。**
  管理画面の一覧で `display_errors=OFF` を確認済み。
- 実測に用いた一時診断PHPは、代表が XServer 上で作成・保存・実行し、**結果取得後に削除済み**（残存0）。
- ★**intake サブドメインの本番配置時にも、`display_errors=Off` /
  `log_errors=On` / `error_log` を **public_html 外**へ置く設定を必須とする**（§10.4・§10.6）。

---

## 1. システム境界

各系が持つ情報と、**持ってはいけない情報**を確定する。

| 系 | 役割 | 保持してよい情報 | **保持してはいけない情報** |
|---|---|---|---|
| **HP Intake**<br>（intake.smartlaboworks.com） | 店舗が入力する画面と受付API。token でのみ到達できる | 店舗入力（§3）／メニュー・スタッフ・デザイン／写真メタ情報／素材利用確認／token の**hash**／提出・修正状態／Drive フォルダへの安全な参照（**暗号化**）／案件識別（case_number）／監査イベント | **Stripe参照情報／入金状態／契約金額／公開承認／カード情報／営業履歴**／秘密値（§8）／token 平文 |
| **Smart Labo 管理**<br>（intake-api の管理画面） | 案件作成・token 発行/失効・確認・不足連絡・データ書き出し・Drive URL 登録 | intake DB への読み書き／Drive URL の登録・表示／書き出しファイルの生成 | 店舗の Google アカウント資格情報／カード情報／店舗の各種パスワード |
| **Google Drive**<br>（Smart Labo 所有） | **写真本体のみ**を受領する場所 | 画像ファイルの実体／フォルダ構造 | 回答データ／token／契約情報／請求情報／個人情報を含む文書（写真以外を置かない） |
| **Smart Labo Operations**<br>（内部専用・**未実装**・社内管理上の仮称） | 契約後のHP制作管理。**公開承認・請求参照・同意証跡の正式記録** | 契約店舗／契約プラン／HP契約内容／見積額／**Stripe customer ID**／**Stripe invoice ID**／請求日／入金確認日／追加請求状態／着手可能日／HP制作進捗／不足項目／確認URL／修正回数／**店舗による公開承認**／公開日／保守開始日／**権利・同意証跡の要点**／保持期限・削除実施日 | **カード番号／有効期限／セキュリティコード／Stripe秘密鍵**／店舗の入力回答本体／token／営業履歴 |
| **Stripe** | 請求書・決済・入金 | 請求書／決済／入金／返金・取消／サブスクリプション／カード情報（Stripe内のみ） | intake の回答／公開承認／制作進捗 |
| **AI Sales**<br>（社内ローカル・営業支援専用） | 見込み客／営業活動／DM・営業文面／商談前の営業支援 | 上記の営業支援に関する情報のみ | **HP制作・契約後導入管理の一切**（Stripe参照情報／公開承認／権利同意／HP進捗） |

### 1.1 系の間で越えてはならない線

1. **HP Intake は他システムへ直接接続しない。** DB接続・API呼び出しのいずれも作らない。
2. **Smart Labo Operations から HP Intake を呼び出さない。**
   Operations への取り込みは、**検証済みの書き出しデータを人が取り込む**方式から始める（OPS-4）。
3. **Stripe の情報は HP Intake に一切入れない。** 参照IDも入れない（§8）。
4. **公開承認は HP Intake に持たせない。** 正式記録は **Smart Labo Operations**。
5. **Google Drive には写真以外を置かない。** 回答データの保管先にしない。
6. **AI Sales は営業支援専用であり、HP Intake および Smart Labo Operations とは連携しない。**
   AI Sales を HP制作・契約後導入管理の保存先にも連携先にもしない。
7. **AI Sales リポジトリを HP工程で変更しない。**

### 1.2 Smart Labo Operations の位置づけ

| 項目 | 内容 |
|---|---|
| 性質 | **内部専用**。顧客向けの商品名ではなく、**社内管理上の仮称** |
| 現状 | **未実装**。要件・データモデルは OPS-1 で確定する |
| 役割 | 契約後のHP制作管理。契約・請求参照・進捗・公開承認・同意証跡の**正式記録** |
| 保持しない | カード番号／有効期限／セキュリティコード／Stripe秘密鍵／店舗の入力回答本体／token |
| Intake との関係 | **責任を分離する。** Intake は「店舗が入力したもの」、Operations は「当社が管理するもの」 |

### 1.3 Operations 未実装時の Phase 1 運用

Smart Labo Operations の完成前に契約が発生した場合、
**代表が案件ごとの標準管理票で手動管理する。**

管理票が持つ項目:
契約内容／Stripe請求書参照／入金確認／着手可能日／修正回数／
公開承認／公開日／保守開始日／同意証跡の要点

| # | 遵守事項 |
|---|---|
| 1 | **この管理票を GitHub へ保存しない**（リポジトリに入れない） |
| 2 | **intake の自由記述欄へ押し込まない**（`promotion.*` や `rights.note` を代用しない） |
| 3 | **AI Sales へ入力しない** |
| 4 | 管理票は代表が管理する。Operations 完成後に、その内容を Operations へ移す |

### 1.4 AI Sales の位置づけ（境界のみ）

**AI Sales は営業支援専用であり、HP Intake および Smart Labo Operations とは連携しない。**

- 扱う範囲: 見込み客／営業活動／DM・営業文面／商談前の営業支援
- **契約成立後のHP制作管理へは連携しない**
- 本書および今後のHP工程で **AI Sales リポジトリを変更しない**

---

## 2. SQLite テーブル

### 2.0 共通規約

| # | 規約 |
|---|---|
| 1 | ファイルは `private/intake.sqlite`。**ドキュメントルート外**に置く（Webから到達不可） |
| 2 | アクセスは **PDO の prepared statement のみ**。SQL文字列へ値を連結しない |
| 3 | 日時は **ISO 8601 文字列**（`YYYY-MM-DDTHH:MM:SS+09:00`）。日付のみは `YYYY-MM-DD` |
| 4 | 真偽値は **INTEGER 0/1**（SQLite に boolean 型は無い） |
| 5 | `PRAGMA foreign_keys = ON` を接続ごとに実行する |
| 6 | 破壊的な `DROP` / 既存行への一括 `UPDATE` は運用で行わない |
| 7 | **平文 token 列を作らない**（§4）。**平文 session secret 列も作らない**（§2.6） |
| 8 | **カード情報・秘密値・Stripe情報・公開承認の列を作らない**（§8） |
| 9 | **SQLite 3.26.0 互換サブセットの範囲でのみ実装する**（§2.0.1） |
| 10 | private の実配置は **public_html の外**（domain root 直下）。実測で書込可を確認済み（§0.2） |

### 2.0.1 SQLite 3.26.0 互換サブセット（本番実測にもとづく制約）

本番の SQLite は **3.26.0**（2018-12）である。**ローカル開発環境はこれより新しい**ため、
「ローカルでは通るが本番だけ失敗する」事故が起こりうる。これを構造的に防ぐため、
**使用してよい機能を 3.26.0 の範囲へ明示的に限定する。**

**使用禁止（3.26.0 に存在しない）**

| 機能 | 必要版 | 代替 |
|---|---|---|
| **`VACUUM INTO`** | 3.27.0 | **`SQLite3::backup()`**（§9.6） |
| `RETURNING` 句 | 3.35.0 | `lastInsertId()` ／ 直後に SELECT |
| **STRICT テーブル** | 3.37.0 | 型はアプリ側で検証する（§3.0） |
| `ALTER TABLE ... DROP COLUMN` | 3.35.0 | 新テーブル作成 → コピー → 差し替え |
| 生成列（GENERATED ALWAYS AS） | 3.31.0 | アプリ側で算出 |
| `PRAGMA table_xinfo` の一部挙動 | 3.26+ 依存 | `PRAGMA table_info` を使う |

**使用してよい（3.26.0 で利用可）**

`UPSERT`（ON CONFLICT DO UPDATE, 3.24）／窓関数（3.25）／部分索引（3.8）／
`ALTER TABLE ... RENAME COLUMN`（3.25）／`PRAGMA foreign_keys`／
**`PRAGMA integrity_check`**／**`PRAGMA foreign_key_check`**（3.7.16 以前・§9.7）／
**`PRAGMA secure_delete`**（3.6.x 以降・**v1.7 で採用**・§9.3-5）

**SQL側の JSON 関数（`json_extract` 等）は使用しない**
- 本書の JSON 列は **TEXT として保存し、PHP 側でパースする**設計である（§2.3）。
- したがって JSON1 拡張が 3.26.0 に組み込まれているか否かに**依存しない**。
- 検索・集計を SQL の JSON 関数で行いたくなった場合は、**本書を改定してから**行う。

**実装時のガード（4B で実装）**

| # | 規則 |
|---|---|
| 1 | 起動時に `SELECT sqlite_version()` を取得し、**3.27.0 未満なら VACUUM INTO 系の経路へ入らない** |
| 2 | ローカル開発でも**意図的に 3.26.0 相当の制約で書く**（新機能を使わない） |
| 3 | **4E（セキュリティテスト）に「3.26.0 互換性チェック」を含める**。使用している全SQLを静的に確認する |
| 4 | 本番版数が上がった場合も、**本書を改定するまで新機能を使わない** |

### 2.1 A. intake_cases（案件）

1案件1行。店舗ごとの入り口。

| 列 | 型 | NULL | 既定 | 内容 |
|---|---|---|---|---|
| `id` | INTEGER PK AUTOINCREMENT | — | — | 内部ID。**URLへ出さない** |
| `case_number` | TEXT UNIQUE NOT NULL | 不可 | — | 例 `HP-2026-0001`。仕様書 C-02 に対応。人が読む識別子 |
| `shop_display_name` | TEXT NOT NULL | 不可 | — | 管理画面での識別用。最大100字。**公開HPの表示名とは別**（表示名は回答側 `basic.display_name`） |
| `contract_type` | TEXT NOT NULL | 不可 | `standalone` | `salon` / `standalone`（仕様書 §3 の契約区分A/B） |
| `status` | TEXT NOT NULL | 不可 | `draft` | §5 の状態。`draft` / `submitted` / `needs_revision` / `reviewed` / `locked` / `closed` |
| `current_step` | TEXT | 可 | NULL | 店舗が最後に開いていた入力ステップ（再開位置）。例 `basic` / `menus` / `photos` |
| `drive_folder_url_enc` | BLOB | 可 | NULL | Google Drive フォルダURLの**暗号文**（§7.3）。平文で保存しない |
| `drive_folder_label` | TEXT | 可 | NULL | 管理・画面表示用のフォルダ名（例「HP-2026-0001 写真」）。URLではない |
| `drive_shared_email_enc` | BLOB | 可 | NULL | **共有先メールアドレスの暗号文**（§7.3・v1.5 で追加）。<br>店舗画面の案内文「このフォルダは○○にのみ共有しています」に使う。<br>**平文で保存しない。書き出し・監査・ログへ出さない** |
| `drive_upload_confirmed_at` | TEXT | 可 | NULL | 店舗が「アップロード完了」を申告した日時 |
| `submitted_at` | TEXT | 可 | NULL | 初回提出日時 |
| `locked_at` | TEXT | 可 | NULL | 編集ロック日時 |
| `closed_at` | TEXT | 可 | NULL | 案件終了日時（保持期間の起算） |
| `expires_at` | TEXT | 可 | NULL | 案件の入力受付期限（token とは別。案件全体の締切） |
| `retention_delete_due` | TEXT | 可 | NULL | 回答削除の予定日（§9）。`closed_at + 6か月` |
| `deleted_at` | TEXT | 可 | NULL | 回答削除の**実施日**。実施後は §9.4 の残余のみ保持 |
| `schema_version` | INTEGER NOT NULL | 不可 | 1 | 案件作成時点の回答スキーマ版 |
| `created_at` | TEXT NOT NULL | 不可 | — | |
| `updated_at` | TEXT NOT NULL | 不可 | — | |

索引: `UNIQUE(case_number)` ／ `INDEX(status)` ／ `INDEX(retention_delete_due)`

> ★`drive_folder_url_enc` は §7.3 の方式で暗号化する。
> 復号できるのは Smart Labo 管理画面と店舗画面の描画時のみで、
> **ログ・通知メール・書き出しファイルへは出さない**（§10.7）。

### 2.2 B. intake_tokens（店舗専用URLの token）

| 列 | 型 | NULL | 内容 |
|---|---|---|---|
| `id` | INTEGER PK AUTOINCREMENT | — | |
| `intake_case_id` | INTEGER NOT NULL REFERENCES intake_cases(id) | 不可 | |
| `token_hash` | TEXT NOT NULL | 不可 | **SHA-256(token) の16進64文字**。★平文列は作らない |
| `expires_at` | TEXT NOT NULL | 不可 | 発行から14日（§4） |
| `revoked_at` | TEXT | 可 | 失効日時。再発行・漏えい時に即時セット |
| `last_used_at` | TEXT | 可 | 最終利用日時（**利用元IPは保存しない**。監査は §2.5 のHMACのみ） |
| `created_at` | TEXT NOT NULL | 不可 | |

索引: `UNIQUE(token_hash)` ／ `INDEX(intake_case_id)`

制約（アプリ側で保証・§4）:
- **1案件につき有効 token は常に1本**（`revoked_at IS NULL AND expires_at > now` の行が最大1）
- 再発行時は、既存の有効行へ `revoked_at` をセットしてから新規行を作る（同一トランザクション）

### 2.3 C. intake_answers（回答本体）

1案件1行。カテゴリごとに JSON 列で持つ。

| 列 | 型 | NULL | 内容 | 対応（仕様書） |
|---|---|---|---|---|
| `intake_case_id` | INTEGER PK REFERENCES intake_cases(id) | 不可 | 1案件1行 | — |
| `schema_version` | INTEGER NOT NULL | 不可 | 回答スキーマ版（既定1） | — |
| `basic_json` | TEXT NOT NULL | 不可 | 店舗基本情報 | §5 B-01〜B-19 |
| `business_hours_json` | TEXT NOT NULL | 不可 | 営業時間・定休日 | §5 B-10〜B-12 |
| `menus_json` | TEXT NOT NULL | 不可 | メニュー配列 | §6 M-01〜M-18 |
| `staff_json` | TEXT NOT NULL | 不可 | スタッフ配列 | §9 S-01〜S-14 |
| `promotion_json` | TEXT NOT NULL | 不可 | 訴求＋業種別分岐 | §7 P-01〜P-16 / §8 BT・WL・PV |
| `design_json` | TEXT NOT NULL | 不可 | デザイン希望 | §11 D-01〜D-11 |
| `web_links_json` | TEXT NOT NULL | 不可 | WEB・予約・SNS | §10 W-01〜W-15 |
| `contact_form_json` | TEXT NOT NULL | 不可 | 問い合わせフォーム設定 | §10.1.4 W-16〜W-18 |
| `privacy_json` | TEXT NOT NULL | 不可 | プライバシー設定 | §14.2 PR-01〜PR-10 |
| `image_metadata_json` | TEXT NOT NULL | 不可 | 写真メタ配列（**本体は Drive**） | §12.4 IMG-01〜IMG-10 |
| `rights_json` | TEXT NOT NULL | 不可 | 権利・同意・法的確認 | §13.1 L-01〜L-13 ほか |
| `version` | INTEGER NOT NULL | 不可 | 楽観ロック用の版番号（§6.3）。保存のたび +1 | — |
| `created_at` | TEXT NOT NULL | 不可 | | |
| `updated_at` | TEXT NOT NULL | 不可 | | |

> ★未入力でも列は `NOT NULL`。**空の JSON（`{}` / `[]`）で初期化**し、
> 「行が無い」状態を作らない（読み取り側の分岐を減らすため）。

**JSON 列を採用した理由**（過剰な正規化を避ける判断）
1. メニュー・スタッフ・写真メタは**案件内でしか参照しない**。横断検索も集計もしない。
2. 仕様書の入力項目は改定される。JSON なら `schema_version` で世代を併存できる。
3. 1〜5店舗規模で、テーブル分割による整合コストが利点を上回らない。
4. 生成側（テンプレート）へは §17 の店舗データ構造で渡すため、DB形状に依存しない。

### 2.4 D. intake_submission_history（提出履歴）

**回答本文・個人情報のコピーを保存しない。**

| 列 | 型 | NULL | 内容 |
|---|---|---|---|
| `id` | INTEGER PK AUTOINCREMENT | — | |
| `intake_case_id` | INTEGER NOT NULL REFERENCES intake_cases(id) | 不可 | |
| `event_type` | TEXT NOT NULL | 不可 | `submitted` / `revision_requested` / `resubmitted` / `reviewed` / `locked` / `closed` |
| `schema_version` | INTEGER NOT NULL | 不可 | その時点の回答スキーマ版 |
| `submitted_at` | TEXT NOT NULL | 不可 | 発生日時 |
| `result_code` | TEXT NOT NULL | 不可 | `ok` / `validation_error` / `conflict` / `expired` / `rejected` |
| `field_count` | INTEGER | 可 | 充足項目数（**値は持たない。件数のみ**） |
| `missing_count` | INTEGER | 可 | 不足項目数（**項目名も値も持たない**） |
| `submission_id` | TEXT | 可 | **提出要求の冪等化キー**（クライアント生成 UUID v4）。§6.4<br>**回答本文でも PII でもない。操作そのものの識別子である** |

索引: `INDEX(intake_case_id, submitted_at)`
／ `UNIQUE INDEX(intake_case_id, submission_id) WHERE submission_id IS NOT NULL`

> ★不足**項目名**は intake_answers 側の判定結果として都度算出し、履歴には**件数だけ**残す。
> 履歴が回答のスナップショットにならないようにするため。

**`submission_id` の規則（v1.3 で追加）**

| # | 規則 |
|---|---|
| 1 | **クライアントが生成**する。形式は **UUID v4 のみ**。サーバーは形式を検証する |
| 2 | 保存先は **`intake_submission_history.submission_id` だけ**。他の表へ複製しない |
| 3 | 一意性は **`intake_case_id` ＋ `submission_id`** の**部分一意索引**で保証する<br>（`submission_id IS NOT NULL` の行だけを対象にする。SQLite 3.26.0 で使用可） |
| 4 | **DB列は NULL 許容**にする。v1.2 以前に記録された既存行との互換のため |
| 5 | **HTTP `/submit` では必須**にする（欠落・不正形式は 400・固定文言） |
| 6 | **ログ・監査イベント・エラー本文・通知メールへ出さない**（§10.7） |
| 7 | 値の中身から店舗・利用者を識別できてはならない（**乱数由来の v4 のみ**を許す理由） |

> ★この列を追加しても、履歴が**回答のスナップショットにならない**という §2.4 の原則は変わらない。
> `submission_id` は「どの提出要求か」を表すだけであり、**何を入力したか**を一切含まない。

### 2.5 E. intake_audit_events（監査ログ）

**token 平文・回答本文・氏名・メール・電話・住所・Drive URL を保存しない。**

| 列 | 型 | NULL | 内容 |
|---|---|---|---|
| `id` | INTEGER PK AUTOINCREMENT | — | |
| `intake_case_id` | INTEGER REFERENCES intake_cases(id) | 可 | token 不一致時など、案件を特定できない場合は NULL |
| `event_type` | TEXT NOT NULL | 不可 | `token_issued` / `token_revoked` / `token_accepted` / `token_rejected` / `answer_saved` / `submitted` / `admin_viewed` / `export_generated` / `drive_url_set` / `answers_deleted`<br>／ `session_revoked` / `rate_limited`（4B で追加）<br>／ **`drive_upload_confirmed` / `case_status_changed` / `admin_login` / `admin_logout`**（v1.4 で追加）<br>／ **`token_reissued`**（v1.6 で追加。§4.4.1）<br>／ **`retention_due_set` / `retention_purged` / `audit_purged` / `admin_sessions_purged`**（v1.7 で追加。§9.3）<br>／ **`admin_settings_saved`**（v1.9 で追加。§3.12。**設定値を書かない**）<br>／ **`backup_created` / `backup_restore_drill` / `backup_cleanup` / `backup_generations_purged`**（v1.11 で追加。§9.5。**ファイル名もパスも件数も書かない**）<br>／ **`submission_notification_sent` / `submission_notification_failed`**（v1.12 で追加。§9.11。**宛先も件名も本文も submission_id も書かない**） |
| `result_code` | TEXT NOT NULL | 不可 | `ok` / `invalid` / `expired` / `revoked` / `not_found` / `rate_limited` / `conflict` |
| `ip_hmac` | TEXT | 可 | **HMAC-SHA256(IP, ip_hash_secret) の先頭32文字**。生IPを保存しない |
| `created_at` | TEXT NOT NULL | 不可 | |

索引: `INDEX(intake_case_id, created_at)` ／ `INDEX(created_at)`

> ★`token_rejected` の `result_code` は監査目的で `invalid` / `expired` / `revoked` / `not_found` を
> 区別して**記録する**が、**利用者へ返す画面は §4.6 のとおり常に同一文言**にする。
> 記録の粒度と、外部へ見せる粒度を分ける。

**v1.4 で追加した4種（4D）**

| event_type | いつ | `intake_case_id` | 備考 |
|---|---|---|---|
| `drive_upload_confirmed` | 店舗が素材のアップロード完了を申告したとき | 案件 | 同じ申告の再送では**追加しない**（冪等） |
| `case_status_changed` | 管理者が `reviewed` / `needs_revision` へ変更したとき | 案件 | `result_code` は `ok` / `conflict`。**変更後の状態名は書かない**（履歴側が持つ） |
| `admin_login` | 管理者のログイン試行 | **NULL** | `result_code` は `ok` / `invalid` / `rate_limited`。**IDもパスワードも書かない** |
| `admin_logout` | 管理者のログアウト | **NULL** | |

**v1.6 で追加した1種（4D-R2）**

| event_type | いつ | `intake_case_id` | 備考 |
|---|---|---|---|
| `token_reissued` | 管理者がご案内リンクを**再発行**したとき | 案件 | `result_code` は `ok` / `rate_limited`。<br>**token 平文も hash も書かない。** 再発行のたびに1件増える（§4.4.1） |

> ★初回発行は従来どおり `token_issued`。**再発行は `token_reissued`** で区別する。
> 「何回配り直したか」が監査から読めるようにするためである。

**v1.7 で追加した4種（4F-PRE）**

| event_type | いつ | `intake_case_id` | 備考 |
|---|---|---|---|
| `retention_due_set` | 削除予定日を**登録・変更**したとき | 案件 | **日付そのものは書かない**（案件行が持つ。二重に持たない）。<br>同じ日付の再送では追加しない（冪等）。変更は毎回1件残す |
| `retention_purged` | 保持期限による**機密情報の削除**を実行したとき | 案件 | `result_code` は `ok` のみ（失敗は全ロールバックのため記録が残らない）。<br>**削除した値も、消した件数の内訳も書かない**（§9.3-3） |
| `audit_purged` | 13か月を過ぎた**監査ログを削除**したとき | **NULL** | 削除件数が0のときは記録しない。<br>★この行**自身も13か月後に削除対象**になる（保持が循環しない） |
| `admin_sessions_purged` | 期限切れの**管理 session を削除**したとき | **NULL** | 削除件数が0のときは記録しない。**session hash を書かない** |

> ★v1.6 まで語彙にあった **`answers_deleted` は、v1.7 で `retention_purged` へ統合**した。
> 語彙としては残す（過去DBの行を読めなくしないため）が、**新規には記録しない**。

**v1.11 で追加した4種（4G）**

| event_type | いつ | `intake_case_id` | 備考 |
|---|---|---|---|
| `backup_created` | バックアップの取得を試みたとき | **NULL** | `result_code` は `ok` または失敗の固定コード。<br>**ファイル名・保存先・SHA-256 を書かない**（控えは manifest 側が持つ） |
| `backup_restore_drill` | 復元確認を行ったとき | **NULL** | `result_code` は `ok` または失敗の固定コード。<br>**稼働DBへ書き戻していないことの記録**でもある |
| `backup_cleanup` | 30日超・60世代超を**実際に削除**したとき | **NULL** | dry-run では記録しない。**削除件数を書かない** |
| `backup_generations_purged` | 保持削除より前の世代を**実際に削除**したとき | **NULL** | `result_code` は `ok` / `incomplete`。<br>dry-run では記録しない。**削除件数を書かない**（§9.5.6） |

**v1.12 で追加した2種（4H-R0）**

| event_type | いつ | `intake_case_id` | 備考 |
|---|---|---|---|
| `submission_notification_sent` | 提出通知を**送れた**とき | 案件 | `result_code` は `ok`。<br>**宛先・件名・本文・submission_id を書かない** |
| `submission_notification_failed` | 提出通知を**送れなかった**とき | 案件 | `result_code` は `send_failed` / `invalid`。<br>**提出そのものは成功のまま**である（§9.11-5）。<br>通知が設定されていない環境では**どちらも記録しない** |

> ★通知の成否を `submission_id` で記録しない。冪等化キーは監査へ出さない（§10.7）。

> ★バックアップのメタデータ（作成日時・サイズ・SHA-256）は**DBへ保存しない**。
> DB ごと失った場面で読めなければ意味がないため、保存先の manifest ファイルが持つ（§9.5.2）。
> 監査に残すのは「いつ・何をして・成功したか」だけである。
> ★**新しい表を追加しない。** バックアップのために `PRAGMA user_version` は上げない。

> ★管理者の操作は**案件に紐づくものだけ** `intake_case_id` を持つ。
> ログイン・ログアウトは案件と無関係なので NULL にする。
> ★`admin_login` に**入力された管理者IDを記録しない**。存在の有無を残さないため（§10.8）。

### 2.6 F. intake_sessions（Cookie セッション）

`/start#<token>` で受け取った token を**一度だけ**検証し、以後は Cookie の
session secret で継続する（§4.2・§4.7）。**token を URL に残さないための表**である。

| 列 | 型 | NULL | 内容 |
|---|---|---|---|
| `id` | INTEGER PK AUTOINCREMENT | — | |
| `intake_case_id` | INTEGER NOT NULL REFERENCES intake_cases(id) | 不可 | |
| `token_id` | INTEGER NOT NULL REFERENCES intake_tokens(id) | 不可 | どの token から発行されたか |
| `session_hash` | TEXT NOT NULL | 不可 | **SHA-256(session secret) の16進64文字**。★平文列は作らない |
| `expires_at` | TEXT NOT NULL | 不可 | **最終利用から24時間**を保持（利用のたびに延長） |
| `absolute_expires_at` | TEXT NOT NULL | 不可 | **発行から7日**。延長しない |
| `revoked_at` | TEXT | 可 | 失効日時 |
| `last_seen_at` | TEXT | 可 | 最終利用日時（**利用元IPは保存しない**） |
| `created_at` | TEXT NOT NULL | 不可 | |

索引: `UNIQUE(session_hash)` ／ `INDEX(intake_case_id)` ／ `INDEX(token_id)` ／ `INDEX(expires_at)`

**規則**

| # | 規則 |
|---|---|
| 1 | session secret は **`random_bytes(32)`**（base64url 43文字） |
| 2 | DBには **SHA-256 hash のみ**保存する。**平文を保存する列を作らない** |
| 3 | **Cookie 以外へ平文を保存しない**（ファイル・ログ・localStorage・sessionStorage を含む） |
| 4 | **token 再発行・失効時に、その token から発行された session をすべて失効させる** |
| 5 | **`locked` / `closed` へ遷移した時にも全 session を失効させる**（§5） |
| 6 | 有効期限は**最終利用から24時間**（`expires_at` を都度延長） |
| 7 | **絶対有効期限は発行から7日**（`absolute_expires_at`。延長しない） |
| 8 | ログアウト・管理者操作で**個別に失効できる**（`revoked_at`） |
| 9 | **Cookie 名に店舗名・案件番号を含めない**（固定の一般名を使う） |
| 10 | Cookie 属性は **Secure / HttpOnly / SameSite=Strict**（§4.7） |
| 11 | 照合は `hash_equals()` 等の**定数時間比較**で行う |
| 12 | Cookie の **`Max-Age` は 24時間**とする（**v1.3 で確定事項として維持**）。<br>短縮も延長もしない。理由は下の枠を見よ |

> ★session は token の**下位**にある。token が無効なら session も無効。
> 「session があるから token 失効を無視してよい」という経路を作らない。

**Cookie 24時間の維持（v1.3 で確定）**

| # | 判断 |
|---|---|
| 1 | 店舗の入力は**数日にまたがる**（写真の準備・メニューの確認・法的確認）。<br>24時間より短くすると、入力途中で毎回 `/start#<token>` からやり直しになる |
| 2 | 24時間より長くすると、**共有端末・貸し出し端末で他人が続きを開ける**時間が延びる |
| 3 | よって **24時間を維持**し、短さの不足は「**利用者が自分で終了できること**」で補う |
| 4 | **4C は、目立つ位置に「入力を終了する」ボタンを置く**（画面下部に隠さない）。<br>押下で `POST /session/logout` を実行し、**Cookie も失効**させ、画面を終了状態へ切り替える |
| 5 | 絶対有効期限 7日（規則7）は変更しない。24時間の延長を無限に繰り返せない歯止めである |

### 2.7 G. intake_admin_sessions（管理画面のセッション・v1.4 で追加）

Smart Labo 内部の管理画面用。**§2.6 の `intake_sessions`（店舗向け）とは別の表**である。
店舗の session を管理画面へ流用しない。逆も行わない（§10.8）。

**アカウント表は作らない。** Phase 1 は代表1名のみで、資格情報は
`private/intake-config.php`（ドキュメントルート外・Git管理外）に置く（§10.8）。

| 列 | 型 | NULL | 内容 |
|---|---|---|---|
| `id` | INTEGER PK AUTOINCREMENT | — | |
| `session_hash` | TEXT NOT NULL UNIQUE | 不可 | **SHA-256(session secret) の16進64文字**。★平文列は作らない |
| `csrf_hash` | TEXT NOT NULL | 不可 | **SHA-256(CSRF token)**。★平文を保存しない |
| `expires_at` | TEXT NOT NULL | 不可 | **最終利用から30分**（利用のたびに延長） |
| `absolute_expires_at` | TEXT NOT NULL | 不可 | **発行から8時間**。延長しない |
| `revoked_at` | TEXT | 可 | ログアウト・再生成による失効 |
| `last_seen_at` | TEXT | 可 | 最終利用日時（**利用元IPは保存しない**） |
| `created_at` | TEXT NOT NULL | 不可 | |

索引: `UNIQUE(session_hash)` ／ `INDEX(expires_at)`

**規則**

| # | 規則 |
|---|---|
| 1 | session secret / CSRF token とも **`random_bytes(32)`**（base64url 43文字） |
| 2 | DBには **SHA-256 hash のみ**。平文を保存する列を作らない |
| 3 | 平文 session secret は **Cookie にだけ**（Secure / HttpOnly / SameSite=Strict） |
| 4 | Cookie 名に `admin`・店舗名・案件番号を**含めない**（固定の一般名） |
| 5 | **ログイン成功時に必ず新しい行を作る**（session fixation 対策。古い行は失効させる） |
| 6 | idle 30分・絶対 8時間。どちらか一方でも過ぎたら無効 |
| 7 | 権限は**代表1種類のみ**。ロール列を作らない（§11.2-11） |
| 8 | 「ログイン状態を保持する」機能を作らない |
| 9 | session ID / CSRF token を**ログへ出さない**（§10.7） |
| 10 | **期限切れ・失効済みの行を残し続けない**（v1.7 で追加）。保守画面から**明示操作**で物理削除する。<br>いま有効な session は1件も消さない（実行した本人が締め出されない）。<br>一度に消す件数に上限を設け、**長いロックを作らない**。<br>削除して残すのは**件数だけ**（hash をログにも監査にも出さない）。<br>★案件の保持期限（§9.1）とは**無関係**。破壊的操作のフラグ（§9.8）も要求しない |
| 11 | **自動 cron を作らない**（v1.7）。自動化するときは本書を改定してから行う |

> ★店舗向け（§2.6）と同じ設計を意図的に踏襲している。
> 「hash のみ保存」「idle と絶対の二重期限」「Cookie 属性」を**別実装で作り分けない**ため。

### 2.8 H. intake_revision_requests（修正依頼・v1.5 で追加）

差し戻し（`needs_revision`）の理由を**構造として持つ**表。

> ★これを作る理由: 理由を回答欄（`intake_answers`）へ書くと**回答本文と混ざり**、
> 書き出し・削除・保持期間の扱いが壊れる。監査ログへ書くと**本文が監査へ入る**。
> どちらも避けるため、専用の表を1つ足す（v1.4 §2.8 で「作らない」としていた判断を撤回する）。

| 列 | 型 | NULL | 内容 |
|---|---|---|---|
| `id` | INTEGER PK AUTOINCREMENT | — | ★外部・書き出しへ出さない |
| `intake_case_id` | INTEGER NOT NULL REFERENCES intake_cases(id) | 不可 | |
| `request_number` | INTEGER NOT NULL | 不可 | **案件内の通し番号**（1から）。店舗へ見せるのはこちら |
| `requested_paths_json` | TEXT NOT NULL | 不可 | **§3 の正式パスだけ**の配列。未知パスは受け付けない |
| `message` | TEXT | 可 | 店舗向けの補足。**最大1000文字**。HTML として扱わない |
| `status` | TEXT NOT NULL | 不可 | `open` / `resolved` |
| `created_at` | TEXT NOT NULL | 不可 | |
| `resolved_at` | TEXT | 可 | 店舗の再提出が成功した日時 |

索引: `INDEX(intake_case_id, status)` ／ `UNIQUE(intake_case_id, request_number)`

**規則**

| # | 規則 |
|---|---|
| 1 | `requested_paths_json` は **§3 の正式パスのみ**。未知パスを含む要求は**丸ごと拒否**する |
| 2 | 同じパスが重複したら**正規化**して1つにする（順序は入力順を保つ） |
| 3 | `message` は任意。**1000文字を超えたら拒否**（切り捨てない） |
| 4 | `message` と `requested_paths` を**アプリログへ出さない**（§10.7） |
| 5 | **監査ログに本文を持たせない**。監査は `case_status_changed` の発生だけを残す |
| 6 | 1案件に**複数回**保持できる。過去の依頼を**削除も上書きもしない** |
| 7 | `needs_revision` への状態変更と依頼の作成は**同一トランザクション**で行う |
| 8 | **店舗の再提出が成功した時点**で、その案件の `open` をすべて `resolved` にする |
| 9 | 店舗へ返してよいのは **`open` の依頼だけ**（`request_number` / `requested_paths` / `message` / `created_at`） |
| 10 | **管理者を識別する列を作らない。** Phase 1 は代表1名（§10.8）であり、<br>誰が出したかは自明。個人IDを増やさない |
| 11 | **保持期間は回答本文と同じ**（v1.7 で確定・§9.1）。案件の削除時に `open` / `resolved` を問わず<br>**行ごと物理削除**する。`message` と `requested_paths_json` を残さない。<br>★理由: この表は「どこを直してほしいか」＝**回答の内容そのもの**を指しており、<br>　回答だけ消して依頼が残れば、そこから内容が読めてしまう |

> ★`created_by_admin_session_id` のような列は**作らない**。
> 管理 session の識別子は秘密値であり、**残す理由が無い**（§2.7-9）。
> 「いつ差し戻したか」は `created_at` と監査の `case_status_changed` で追える。

### 2.9 提案：追加しないテーブル（判断の記録）

| 候補 | 判断 | 理由 |
|---|---|---|
| メニュー・スタッフ・写真の正規化テーブル | **作らない** | 案件内でしか使わず横断集計もしない。JSON で足りる（§2.3） |
| 管理画面の**アカウント表** | **作らない**（v1.4 で確定） | Phase 1 は代表1名。資格情報は `private/intake-config.php` に置く（§10.8）。<br>複数管理者は Phase 1 の範囲外（§11.2-11） |
| ~~修正依頼の理由テーブル~~ | **v1.5 で作った**（§2.8） | v1.4 の「作らない」判断を撤回。理由を回答欄へ押し込まず、<br>監査へ本文を入れないために**専用の表**を持つ |
| 管理者の個人アカウント表 | **作らない**（v1.5 で再確認） | Phase 1 は代表1名。修正依頼にも作成者列を作らない（§2.8-10） |
| 不足項目テーブル | **作らない** | 判定は回答から都度算出する。保存すると回答と二重管理になり必ず食い違う |
| Drive ファイル一覧テーブル | **作らない** | Drive API を使わない（§11）。件数・種類は `image_metadata_json` が持つ |
| Stripe 参照テーブル | **作らない** | §8 により intake は Stripe 情報を一切持たない |

---

## 3. JSON 構造

### 3.0 共通規約

| # | 規約 |
|---|---|
| 1 | 文字コードは **UTF-8**。保存前に UTF-8 妥当性を検証する |
| 2 | **制御文字を除去**する（TAB / LF / CR は残し、他の C0/C1 を除去） |
| 3 | 文字列長は**文字数**で判定する（バイト数ではない） |
| 4 | 空値は **未入力＝`null` または `""`**、未選択の配列は **`[]`**。`undefined` を保存しない |
| 5 | 「公開」＝生成HPへ出る／「内部」＝**生成へ渡さない**（HP生成器へ渡すデータから除外する） |
| 6 | HTML出力時の処理は次の3種のみ<br>**ESC**＝`htmlspecialchars(ENT_QUOTES\|ENT_SUBSTITUTE,'UTF-8')`<br>**ESC+NL2BR**＝ESC 後に改行を `<br>` へ（複数行）<br>**URL**＝`https://` で始まることを検査したうえで属性値としてESC |
| 7 | **内部項目は HP生成器へ渡さない**。渡せる／渡せないを各表の「生成」列で示す |
| 8 | 配列上限を超える入力は**保存を拒否**する（切り捨てない） |

「必須」は**提出（submitted）時点**の必須。`draft` 保存では未入力を許す（§6.1）。

### 3.0.1 保存できる回答の範囲（v1.8 で確定・4F-R1 で実装）

v1.7 まで、保存 API が見ていたのは**分類名（11種）だけ**だった。
分類の中身のキーは検査しておらず、未知キーはそのまま保存され、
**§11.3 の「検証済み JSON」にも出ていた**（4F の通し確認で発見）。
allowlist を名乗る以上、中身まで検査する。

**受け取るとき（`POST /answers/save`）**

| # | 規則 |
|---|---|
| 1 | 保存できるのは **§3 が定める11分類・129パスだけ** |
| 2 | 分類名だけでなく、**分類の内部のキーも厳格に検査**する |
| 3 | 未知キーが**1件でも**あれば、**その保存要求の全体を拒否**する（400） |
| 4 | 未知キーだけを**黙って削除して保存しない** |
| 5 | 未知キーの**名前も値も**、応答・ログ・監査へ出さない |
| 6 | 正常な値と不正な値が混ざっていても、**正常な分だけを部分保存しない** |
| 7 | **配列要素の中の未知キー**も拒否する（`menus[].` / `staff[].` / `image_metadata[].` /<br>`business_hours.weekly[].` / `rights.confirmations[].`） |
| 8 | オブジェクト階層の誤り（入れ子の位置違い）も拒否する |
| 9 | JSON の**型**が正式定義と違う場合も拒否する（下表） |
| 10 | 拒否の判定は**保存トランザクションより前**に行う。DBは1バイトも変わらない |
| 11 | `version` を進めない。監査（`answer_saved`）も増やさない |
| 12 | エラーは**固定コード・固定文言**（どのキーが悪かったかを教えない） |
| 13 | 既存の**サイズ上限・配列上限・文字数・語彙・型検査・楽観ロック**は従来どおり効かせる |
| 14 | 「変更した分類だけを送る」既存方式（§6.1）は変えない |

**型（値の形）**

| 形 | 受け付ける値 | 例 |
|---|---|---|
| `scalar` | 文字列・数値・真偽・`null`。**配列とオブジェクトは不可** | `basic.legal_name` |
| `bool` | 真偽 または `null`。**文字列 `"true"` は不可** | `contact_form.enabled` |
| `list` | `scalar` だけを並べた配列 | `basic.payment_methods` |
| `object` | 決まったキーだけを持つオブジェクト | `basic.internal_contact` / `basic.parking` |
| `objects` | `object` を並べた配列 | `menus` / `business_hours.weekly` |

> ★`__proto__` / `constructor` / `prototype` は「一覧に無いキー」として落ちる。
> 特別扱いの分岐を書かない（書けば、書き忘れた名前が残る）。

**出すとき（`GET /case`・管理画面・§11.3 の書き出し）**

| # | 規則 |
|---|---|
| 1 | 保存済みの値を**無条件に信用しない**。読み出しの時点で正式パスへ絞る |
| 2 | 既存DBに未知キーが残っていても**出力しない**（v1.8 より前に入った行のため） |
| 3 | 未知キーが**あるだけで画面や書き出しを失敗させない**。正式値だけを出す |
| 4 | 未知キーを**自動変換したり、別のフィールドへ移したりしない** |
| 5 | 未知キーの**自動清掃機能は作らない**（v1.8 の範囲外。既存行は触らない） |
| 6 | 必須が欠けている・不正な場合は、**従来どおり書き出しを拒否**する（§11.3-5） |

**正式構造の管理**

| # | 規則 |
|---|---|
| 1 | 正式パスの追加・変更は **`public/assets/lib/schema.js` を起点**に行う |
| 2 | PHP 側（`AnswerSchema`）は schema.js からの**機械生成**とする。手で書き換えない |
| 3 | 同じ定義を PHP と JavaScript へ**二重に手書きしない** |
| 4 | 一致（分類・パス・配列要素の許可キー・型）と**生成の冪等性**を自動テストで固定する |
| 5 | 新しいビルドシステムや npm 依存を増やさない（Node の標準機能だけで生成する） |

### 3.0.2 必須の種類（v1.9 で確定・4F-R3 で実装）

v1.8 まで、実装が見ていた必須は **22件**だった。
一方 §3 の各表が「必須」と記しているのは **39件**（本節の分類でいう
`STORE_REQUIRED_NON_EMPTY`）である。通常の画面は45件を止めていたため
気づけず、**API を直接呼べば17件を欠いたまま提出できた**（4F-R2 で判明）。

原因は「必須」を1種類として扱っていたことにある。実際には、
**誰が・いつまでに・何をもって満たすか**が項目ごとに違う。5種へ分ける。

| 種別 | 意味 | 満たし方 |
|---|---|---|
| `STORE_REQUIRED_NON_EMPTY` | 店舗が**値を入れる／能動的に選ぶ** | `null` / `""` / `[]` は**未回答** |
| `STORE_REQUIRED_KEY_ALLOW_EMPTY` | 店舗が**答えたことが分かる**必要がある | **キーの存在**が条件。正式な空値（`false` 等）を認める |
| `ADMIN_REQUIRED_FOR_EXPORT` | Smart Labo が**書き出し前に**設定する | 店舗の提出は妨げない。§11.3 の直前に検査する |
| `ARRAY_ELEMENT_REQUIRED` | 配列要素・object の**中で**満たす条件 | **要素があるときだけ**効く。配列自体の要否とは別 |
| `OPTIONAL` | 欠落してよい | — |
| `CONDITIONAL_REQUIRED` | §3 に条件が**明記されている**ものだけ | 条件が書かれていなければ使わない |

**空値の扱い（型ごと）**

| 型 | 未回答 | 正式な回答になりうる空 |
|---|---|---|
| string | `null` / `""` | — |
| enum | `null` / `""` / 語彙外 | **正式な語彙**（`none` / `no` も、店舗が選べば回答） |
| boolean | **キーが無いことだけ**（v1.10） | **`false`**（「しない」という回答） |
| 配列 | `[]`（§3 が1件以上を求める場合） | `[]`（§3 が許す場合） |
| object | キーが無い／`{}` | — （必須の子キーを満たすこと） |

> ★`false` を「欠落」と同じ扱いにしない。
> 「掲載しない」「予約を受けない」は**答えである**。

**真偽の項目は3状態を作らない（v1.10 で確定・4F-R4）**

v1.9 までは真偽の項目へ `null` を**保存できた**。その結果
「キーが無い」「`null`」「`false`」の**3状態**が生まれ、
v1.9 で決めた「未回答と `false` を区別する」という契約が
必要以上に複雑になっていた。状態は2つに絞る。

| 状態 | 表し方 |
|---|---|
| 未回答 | **キーが存在しない**（これだけ） |
| 掲載する・設置する | `true` |
| 掲載しない・設置しない | `false` |

| # | 規則 |
|---|---|
| 1 | 真偽の項目に **`null` を保存させない**。要求ごと **400** で拒否する |
| 2 | `""` / `"true"` / `"false"` / `0` / `1` / 配列 / object も同じく拒否 |
| 3 | 未回答は**キーを送らない**ことで表す |
| 4 | 拒否は**要求全体**。部分保存しない。`version` も監査も動かさない |
| 5 | 既存DBに `null` が残っていても**回答済みにしない**。<br>読み出しでは未回答として扱い、**自動で `false` へ変換しない** |
| 6 | 画面は「する／しない」の二択で、**既定ではどちらも選ばない**（§10.10 と同じ考え方） |

> ★`null` は文字列の項目では引き続き「未入力」を表せる（`basic.public_phone` など）。
> 2状態で足りる真偽の項目にだけ、この規則を適用する。

**能動選択が必要な enum 7件（代表判断 Q3）**

`basic.address_visibility` ／ `business_hours.irregular_notice` ／
`privacy.third_party` ／ `privacy.marketing_use` ／
`design.logo` ／ `design.emphasis` ／ `web_links.map_display`

| # | 規則 |
|---|---|
| 1 | 画面の初期状態は「選択してください」。**既定の語彙を選択済みにしない** |
| 2 | 未選択（`""`）を**正式値として保存しない** |
| 3 | 店舗が選んだ**正式な語彙**だけを回答済みとする |
| 4 | API を直接呼んでも同じ。キー欠落・空文字は未回答、語彙外は**保存そのものを拒否** |
| 5 | 語彙そのものは変えない |
| 6 | ★とくに `address_visibility` の `full`、`map_display` の `show` を**自動で選ばない**。<br>住所と地図を、本人の能動的な選択なしに公開側へ回さない |

> ★§3 の各表の「空値」欄は、**未入力をどう表すか**を示すものであり、
> 「既定値として入れてよい値」ではない。v1.8 まではこの2つを混同していた。

**必須定義の唯一の実装元**

| # | 規則 |
|---|---|
| 1 | 必須の一覧を **PHP へ手書きしない**。`schema.js` から機械生成する |
| 2 | 生成物（`AnswerSchema`）が **STORE_REQUIRED_NON_EMPTY / STORE_REQUIRED_KEY_ALLOW_EMPTY /<br>ADMIN_REQUIRED_FOR_EXPORT / ARRAY_ELEMENT_REQUIRED / OPTIONAL_PATHS / ENUMS** を持つ |
| 3 | **画面・API・管理画面・書き出しが同じ集合を見る**。別々に持たない |
| 4 | 「画面では止まるのに API では通る」状態を作らない。その逆も作らない |
| 5 | 一致と生成の冪等性を自動テストで固定する |

**`promotion.industry` は Phase 1 の対象外（代表判断 Q1）**

§3.5 の `promotion.industry`（業種別ブロック BT-01〜／WL-01〜 ほか）は、
**Phase 2 以降**とする。Phase 1 では次のとおり扱う。

| # | 扱い |
|---|---|
| 1 | 正式パス（134件）へ**含めない** |
| 2 | 店舗画面・管理画面・DB の回答・書き出しへ**追加しない** |
| 3 | Phase 1 の必須から**撤回**する |
| 4 | 「任意項目」ではない。**Phase 1 対象外**である（実装しないことを明示する） |
| 5 | 理由: 業種別14項目を足すと入力画面が大きく膨らむ。<br>Phase 1 の受付は `menus` / `staff` / `promotion` / `design` で必要情報を集められる。<br>実案件で不足を確認してから追加するほうが安全 |

### 3.1 basic_json（店舗基本情報）

パス接頭辞 `basic.`

| データパス | 仕様書 | 必須 | 型 | 最大 | 公開/内部 | 生成 | HTML出力 | 空値 |
|---|---|---|---|---|---|---|---|---|
| `basic.legal_name` | B-01 | 必須 | string | 100 | 公開 | 可 | ESC | `""` |
| `basic.display_name` | B-02 | 任意 | string | 60 | 公開 | 可 | ESC | `""` → `legal_name` を使う |
| `basic.operator_name` | B-03 | 必須 | string | 100 | 公開 | 可 | ESC | `""` |
| `basic.corporate_name` | B-04 | 条件付き | string\|null | 100 | 公開 | 可 | ESC | `null`（法人のみ必須） |
| `basic.postal_code` | B-05 | 必須 | string | 10 | 公開 | 可 | ESC | `""` |
| `basic.address` | B-06 | 必須 | string | 300 | 公開 | 可 | ESC | `""` |
| `basic.address_visibility` | PV-09 | 必須 | enum | — | 内部 | **判定に使用** | — | `full` |
| `basic.public_phone` | B-07 | 条件付き | string\|null | 20 | 公開 | 可 | ESC | `null` |
| `basic.internal_contact.phone` | B-08 | 必須 | string | 20 | **内部** | **不可** | — | `""` |
| `basic.internal_contact.email` | B-09 | 必須 | string | 254 | **内部** | **不可** | — | `""` |
| `basic.access_text` | B-13 | 必須 | string | 500 | 公開 | 可 | ESC+NL2BR | `""` |
| `basic.parking` | B-14 | 必須 | object | — | 公開 | 可 | ESC | `{type:"none",note:""}` |
| `basic.service_area` | B-15 | 任意 | string\|null | 200 | 公開 | 可 | ESC | `null` |
| `basic.description` | B-16 | 必須 | string | 2000 | 公開 | 可 | ESC+NL2BR | `""` |
| `basic.opened_year` | B-17 | 任意 | integer\|null | 4桁 | 公開 | 可 | ESC | `null` |
| `basic.payment_methods[]` | B-18 | 必須 | string[] | 各30 / **上限10** | 公開 | 可 | ESC | `[]` |
| `basic.booking_methods[]` | B-19 | 必須 | string[] | 各30 / **上限6** | 公開 | 可 | ESC | `[]` |
| `basic.booking_note` | B-19 | 任意 | string\|null | 300 | 公開 | 可 | ESC+NL2BR | `null` |

`basic.address_visibility` の語彙: `full` / `city` / `area` / `hidden`（仕様書 §8.3.2）

> ★**内部連絡先と公開連絡先の分離（重要）**
> `basic.internal_contact.*`（B-08 / B-09）は**制作中の連絡専用**であり、
> 生成HPへ渡すデータから**構造的に除外**する。
> 公開してよい連絡先は `basic.public_phone`（B-07）と `web_links.public_email`（W-11）のみ。
> 書き出し（§11「データ書き出し」）でも `internal_contact` は別ファイル・別セクションに分け、
> **テンプレートが参照できる場所へ置かない**。

### 3.2 business_hours_json（営業時間・定休日）

| データパス | 仕様書 | 必須 | 型 | 最大 | 公開/内部 | 生成 | HTML出力 | 空値 |
|---|---|---|---|---|---|---|---|---|
| `business_hours.weekly[]` | B-10 | 必須 | object[] | **固定7要素**（日〜土） | 公開 | 可 | — | 7要素で初期化 |
| `business_hours.weekly[].day` | — | 必須 | integer 0-6 | — | 公開 | 可 | — | 0=日 |
| `business_hours.weekly[].closed` | — | 必須 | boolean | — | 公開 | 可 | — | `false` |
| `business_hours.weekly[].open` | — | 条件付き | string\|null | `HH:MM` | 公開 | 可 | ESC | `null`（closed=true なら null） |
| `business_hours.weekly[].close` | — | 条件付き | string\|null | `HH:MM` | 公開 | 可 | ESC | `null` |
| `business_hours.closed_note` | B-11 | 必須 | string | 100 | 公開 | 可 | ESC | `""` |
| `business_hours.irregular_notice` | B-12 | 必須 | enum | — | 公開 | 可 | ESC | `none` |
| `business_hours.note` | — | 任意 | string\|null | 300 | 公開 | 可 | ESC+NL2BR | `null`（昼休み等） |

`irregular_notice` の語彙: `instagram` / `line` / `phone` / `none`
検証: `closed=false` のとき `open` < `close` を必須とする。

### 3.3 menus_json（メニュー・料金）

| データパス | 仕様書 | 必須 | 型 | 最大 | 公開/内部 | 生成 | HTML出力 | 空値 |
|---|---|---|---|---|---|---|---|---|
| `menus[]` | §6 | 必須（1件以上） | object[] | **上限60** | — | — | — | `[]` |
| `menus[].name` | M-01 | 必須 | string | 100 | 公開 | 可 | ESC | `""` |
| `menus[].category` | M-02 | 任意 | string\|null | 50 | 公開 | 可 | ESC | `null` |
| `menus[].price_type` | M-03 | 必須 | enum | — | 公開 | 可 | — | `fixed` |
| `menus[].price_inc_tax` | M-04 | 条件付き | integer\|null | 0〜999,999,999 | 公開 | 可 | 数値整形 | `null` |
| `menus[].price_ex_tax` | M-05 | 任意 | integer\|null | 同上 | 公開 | 可 | 数値整形 | `null` |
| `menus[].tax_type` | M-06 | 必須 | enum | — | **内部** | **判定に使用** | — | `unknown` |
| `menus[].duration_minutes` | M-07 | 任意 | integer\|null | 1〜9999 | 公開 | 可 | 数値整形 | `null` |
| `menus[].description` | M-08 | 任意 | string\|null | 500 | 公開 | 可 | ESC+NL2BR | `null` |
| `menus[].note` | M-09 | 任意 | string\|null | 300 | 公開 | 可 | ESC+NL2BR | `null` |
| `menus[].target` | M-10 | 任意 | string\|null | 100 | 公開 | 可 | ESC | `null` |
| `menus[].published` | M-11 | 必須 | boolean | — | 内部 | 表示制御 | — | `true` |
| `menus[].bookable` | M-12 | 必須 | boolean | — | 内部 | 表示制御 | — | `true` |
| `menus[].display_order` | M-13 | 任意 | integer | 0〜9999 | 内部 | 並び | — | 入力順 |
| `menus[].first_time_only` | M-14 | 必須 | boolean | — | 公開 | 可 | — | `false` |
| `menus[].limited_period` | M-15 | 必須 | boolean | — | 公開 | 可 | — | `false` |
| `menus[].period_start` | M-16 | 条件付き | string\|null | `YYYY-MM-DD` | 公開 | 可 | ESC | `null` |
| `menus[].period_end` | M-17 | 条件付き | string\|null | `YYYY-MM-DD` | 公開 | 可 | ESC | `null` |
| `menus[].cancel_policy` | M-18 | 任意 | string\|null | 500 | 公開 | 可 | ESC+NL2BR | `null` |

`price_type` の語彙: `fixed` / `from`（○円〜）/ `quote`（都度見積り）/ `undecided` / `free`
検証（仕様書 §6.1〜§6.2 に一致させる）:
- `price_type ∈ {fixed, from, free}` のとき `price_inc_tax` **必須**
- `tax_type = unknown` の行は**生成対象から除外**する（掲載しない）
- `price_ex_tax` のみで `price_inc_tax` が無い行は**生成対象から除外**する
- `limited_period = true` のとき `period_start` / `period_end` 必須、`start <= end`

### 3.4 staff_json（スタッフ）

| データパス | 仕様書 | 必須 | 型 | 最大 | 公開/内部 | 生成 | HTML出力 | 空値 |
|---|---|---|---|---|---|---|---|---|
| `staff[]` | §9 | 任意 | object[] | **上限30** | — | — | — | `[]` |
| `staff[].display_name` | S-01 | 条件付き | string\|null | 40 | 公開 | 可 | ESC | `null`（published=true で必須） |
| `staff[].real_name` | S-02 | 任意 | string\|null | 40 | **内部** | **不可** | — | `null` |
| `staff[].role` | S-03 | 任意 | string\|null | 40 | 公開 | 可 | ESC | `null` |
| `staff[].career` | S-04 | 任意 | string\|null | 500 | 公開 | 可 | ESC+NL2BR | `null` |
| `staff[].qualifications` | S-05 | 任意 | string\|null | 200 | 公開 | 可 | ESC+NL2BR | `null` |
| `staff[].specialty` | S-06 | 任意 | string\|null | 100 | 公開 | 可 | ESC | `null` |
| `staff[].menu_names[]` | S-07 | 任意 | string[] | 各100 / **上限20** | 公開 | 可 | ESC | `[]` |
| `staff[].bio` | S-08 | 任意 | string\|null | 500 | 公開 | 可 | ESC+NL2BR | `null` |
| `staff[].photo_ref` | S-09 | 条件付き | string\|null | 120 | 内部 | 参照解決に使用 | — | `null`（Drive上のファイル名） |
| `staff[].nominatable` | S-10 | 任意 | boolean | — | 内部 | 表示制御 | — | `false` |
| `staff[].published` | S-11 | 必須 | boolean | — | 内部 | 表示制御 | — | **`false`** |
| `staff[].display_order` | S-12 | 任意 | integer | 0〜9999 | 内部 | 並び | — | 入力順 |
| `staff[].consent_agreed` | S-13 | 条件付き | boolean | — | **内部** | **不可** | — | `false` |
| `staff[].consent_date` | S-14 | 条件付き | string\|null | `YYYY-MM-DD` | **内部** | **不可** | — | `null` |

検証（仕様書 §9.1 に一致させる）:
- `published` の**初期値は false**。明示的に true にしない限り生成対象にしない
- `published = true` のとき `consent_agreed = true` かつ `consent_date` 必須。
  満たさない行は **display_name / photo / career / qualifications を生成へ渡さない**
- `real_name` / `consent_*` は**書き出しの内部セクションにのみ含める**

### 3.5 promotion_json（訴求・業種別）

| データパス | 仕様書 | 必須 | 型 | 最大 | 公開/内部 | 生成 | HTML出力 | 空値 |
|---|---|---|---|---|---|---|---|---|
| `promotion.strengths[]` | P-01 | 必須（1以上） | string[] | 各60 / **上限3** | 公開 | 可 | ESC | `[]` |
| `promotion.customer_profile` | P-02 | 必須 | string | 300 | **内部** | **不可** | — | `""` |
| `promotion.problems` | P-03 | 必須 | string | 500 | 公開 | 可 | ESC+NL2BR | `""` |
| `promotion.recommended_menus[]` | P-04 | 必須（1以上） | string[] | 各100 / **上限3** | 公開 | 可 | ESC | `[]` |
| `promotion.difference` | P-05 | 任意 | string\|null | 500 | 公開 | 可 | ESC+NL2BR | `null` |
| `promotion.concept` | P-06 | 必須 | string | 500 | 公開 | 可 | ESC+NL2BR | `""` |
| `promotion.owner_message` | P-07 | 条件付き | string\|null | 1000 | 公開 | 可 | ESC+NL2BR | `null`（Private で必須） |
| `promotion.founding_story` | P-08 | 任意 | string\|null | 1000 | 公開 | 可 | ESC+NL2BR | `null` |
| `promotion.service_values` | P-09 | 任意 | string\|null | 500 | 公開 | 可 | ESC+NL2BR | `null` |
| `promotion.exclusions` | P-10 | 必須 | string | 500 | **内部** | **不可** | — | `""`（「なし」と明記） |
| `promotion.forbidden_expressions` | P-11 | 必須 | string | 300 | **内部** | **不可** | — | `""` |
| `promotion.competitors` | P-12 | 任意 | string\|null | 300 | **内部** | **不可** | — | `null` |
| `promotion.achievements` | P-13 | 任意 | string\|null | 500 | 公開 | 条件付き | ESC+NL2BR | `null` |
| `promotion.achievements_evidence` | P-14 | 条件付き | string\|null | 500 | **内部** | **不可** | — | `null` |
| `promotion.testimonials[]` | P-15 | 任意 | string[] | 各300 / **上限3** | 公開 | 条件付き | ESC+NL2BR | `[]` |
| `promotion.testimonials_permitted` | P-16 | 条件付き | boolean | — | **内部** | **不可** | — | `false` |
| `promotion.testimonials_permitted_date` | P-16 | 条件付き | string\|null | `YYYY-MM-DD` | **内部** | **不可** | — | `null` |
| ~~`promotion.industry`~~ | §8 | **Phase 2 以降**（v1.9） | object | — | 混在 | — | — | — |

> ★**v1.9（代表判断 Q1）: `promotion.industry` は Phase 1 の対象外**である。
> 正式パス134件に含めず、画面・DB・書き出しへも追加しない（§3.0.2）。
> 下表は Phase 2 以降の参考として残す。

`promotion.industry` は `design.template` に応じて1系統だけを持つ（他系統のキーを作らない）。

| テンプレート | データパス（抜粋） | 仕様書 |
|---|---|---|
| Beauty | `promotion.industry.styles[]`（各40/上限5）／`.treatments[]`（上限20）／`.products`／`.nomination`／`.style_examples[]`（各300/上限3）／`.staff_menu_map[]`（上限30） | BT-01〜BT-07 |
| Wellness | `.problems[]`（各40/上限5）／`.flow[]`（各200/**上限6**）／`.policy`（500）／`.equipment`（300）／`.contraindications`（500）／`.qualifications`（200）／`.first_visit_notes`（500） | WL-01〜WL-07 |
| Private | `.owner_name`（40）／`.career`（800）／`.qualifications`（300）／`.owner_photo_ref`（120）／`.daily_capacity`（integer）／`.appointment_only`（boolean）／`.usage_condition`（100） | PV-01〜PV-08 |

> ★`promotion.achievements` / `promotion.testimonials[]` は、
> それぞれ `achievements_evidence` / `testimonials_permitted` を満たすときだけ**生成へ渡す**。
> 満たさない場合は掲載しない（仕様書 §7.1）。**AIが実績・口コミを創作しない。**
> ★Wellness は仕様書 §8.2.1 の禁止表現検査を保存時に通す（効能断定・医療誤認）。
> ★`promotion.exclusions` / `forbidden_expressions` は内部だが、
> **DAY3 内部確認の検査入力**として書き出しの内部セクションに含める。

### 3.6 design_json（デザイン希望）

| データパス | 仕様書 | 必須 | 型 | 最大 | 公開/内部 | 生成 | HTML出力 | 空値 |
|---|---|---|---|---|---|---|---|---|
| `design.template` | D-01 / C-11 | 必須 | enum | — | 内部 | 構成決定 | — | `null` |
| `design.preferred_colors` | D-02 | 任意 | string\|null | 100 | 内部 | 配色 | — | `null` |
| `design.avoid_colors` | D-03 | 任意 | string\|null | 100 | 内部 | 配色 | — | `null` |
| `design.tone[]` | D-04 | 必須（1以上） | string[] | **上限3**（語彙固定8語） | 内部 | 配色・書体 | — | `[]` |
| `design.reference_sites[]` | D-05 | 任意 | string[] | 各500 / **上限3** | **内部** | **不可** | — | `[]` |
| `design.reference_likes` | D-06 | 条件付き | string\|null | 300 | **内部** | **不可** | — | `null` |
| `design.avoid_design` | D-07 | 任意 | string\|null | 300 | **内部** | **不可** | — | `null` |
| `design.logo` | D-08 | 必須 | enum | — | 内部 | 表示制御 | — | `none` |
| `design.font_preference` | D-09 | 任意 | enum\|null | — | 内部 | 書体 | — | `auto` |
| `design.emphasis` | D-10 | 必須 | enum | — | 内部 | 構成決定 | — | `photo` |
| `design.hero_message` | D-11 | 必須 | string | 200 | 公開 | 可 | ESC+NL2BR | `""` |

語彙: `template` = `beauty`/`wellness`/`private` ／
`tone` = 明るく清潔・落ち着き・高級感・親しみやすい・自然・シンプル・かわいい・スタイリッシュ ／
`logo` = `data`（データあり）/`image`（画像のみ）/`none` ／
`font_preference` = `mincho`/`gothic`/`auto` ／ `emphasis` = `photo`/`text`

> ★`design.reference_sites[]` は**方向性の把握のみ**に使い、模倣しない（仕様書 §11.2）。
> 生成へ渡さない。画面に表示するときも `rel="noopener noreferrer"` を付け、
> **自動でリンクを開かない・プレビューを取得しない**（外部への情報流出を防ぐ）。

### 3.7 web_links_json（WEB・予約・SNS）

| データパス | 仕様書 | 必須 | 型 | 最大 | 公開/内部 | 生成 | HTML出力 | 空値 |
|---|---|---|---|---|---|---|---|---|
| `web_links.current_site` | W-01 | 任意 | string\|null | 500 | **内部** | **不可** | — | `null` |
| `web_links.existing_domain` | W-02 | 条件付き | string\|null | 253 | 公開 | 可 | ESC | `null` |
| `web_links.desired_domain` | W-03 | 条件付き | string\|null | 253 | 内部 | 可 | ESC | `null` |
| `web_links.external_booking_url` | W-04 | 任意 | string\|null | 500 | 公開 | 可 | **URL** | `null` |
| `web_links.salon_booking_url` | W-05 | Smart Labo設定 | string\|null | 500 | 公開 | 可 | **URL** | `null` |
| `web_links.line_add_url` | W-06 | 任意 | string\|null | 500 | 公開 | 可 | **URL** | `null` |
| `web_links.instagram` | W-07 | 任意 | string\|null | 500 | 公開 | 可 | **URL** | `null` |
| `web_links.other_sns[]` | W-08 | 任意 | string[] | 各500 / **上限3** | 公開 | 可 | **URL** | `[]` |
| `web_links.google_business` | W-09 | 任意 | string\|null | 500 | 公開 | 可 | **URL** | `null` |
| `web_links.contact_methods[]` | W-10 | 必須（1以上） | string[] | **上限4** | 公開 | 可 | ESC | `[]` |
| `web_links.public_email` | W-11 | 条件付き | string\|null | 254 | 公開 | 可 | ESC | `null` |
| `web_links.map_display` | W-12 | 必須 | enum | — | 内部 | 表示制御 | — | `show` |
| `web_links.current_server` | W-13 | 条件付き | string\|null | 100 | **内部** | **不可** | — | `null` |
| `web_links.domain_registrar` | W-14 | 条件付き | string\|null | 100 | **内部** | **不可** | — | `null` |
| `web_links.existing_mail` | W-15 | 条件付き | enum\|null | — | **内部** | **不可** | — | `null` |

`contact_methods` の語彙: `phone` / `email` / `line` / `form`
`map_display` の語彙: `show` / `hide`
`existing_mail` の語彙: `yes` / `no` / `unknown`（`unknown` は `yes` として扱う。仕様書 §10.4）

検証:
- URL 型は **`https://` で始まること**を必須とする。`http:` / `javascript:` / `data:` / `vbscript:` は拒否
- `basic.address_visibility ∈ {city, area, hidden}` のとき `map_display` は **`hide` に固定**（仕様書 §8.3.2）
- `contact_methods` に `email` を含むとき `public_email` 必須

### 3.8 contact_form_json（問い合わせフォーム設定）

| データパス | 仕様書 | 必須 | 型 | 最大 | 公開/内部 | 生成 | HTML出力 | 空値 |
|---|---|---|---|---|---|---|---|---|
| `contact_form.enabled` | W-16 | 必須 | boolean | — | 内部 | 表示制御 | — | `false` |
| `contact_form.topics[]` | W-17 | 条件付き | string[] | 各40 / **上限5**（下限3） | 公開 | 可 | ESC | `[]` |
| `contact_form.internal_to` | W-18 | 条件付き | string\|null | 254 | **内部** | **不可** | — | `null` |

> ★`contact_form.internal_to`（送信先メール）は**生成物へ出さない**。
> 生成されるフォームの送信先はサーバー側設定として持ち、HTML には現れない。
> 標準フォームの来訪者入力項目（F-01〜F-06）は**intake の保存対象ではない**
> （公開後に来訪者が入力するもの）。

### 3.9 privacy_json（プライバシー設定）

| データパス | 仕様書 | 必須 | 型 | 最大 | 公開/内部 | 生成 | HTML出力 | 空値 |
|---|---|---|---|---|---|---|---|---|
| `privacy.collected_data[]` | PR-01 | 必須 | string[] | 各60 / **上限20** | 公開 | 可 | ESC | `[]` |
| `privacy.purpose` | PR-02 | 必須 | string | 500 | 公開 | 可 | ESC+NL2BR | `""` |
| `privacy.destination` | PR-03 | Smart Labo設定 | string\|null | 200 | 内部 | 可 | ESC | `null` |
| `privacy.storage` | PR-04 | Smart Labo設定 | string\|null | 200 | 公開 | 可 | ESC | `null` |
| `privacy.retention` | PR-05 | 必須 | string | 200 | 公開 | 可 | ESC | `""` |
| `privacy.third_party` | PR-06 | 必須 | enum | — | 公開 | 可 | ESC | `none` |
| `privacy.external_services[]` | PR-07 | Smart Labo設定 | string[] | 各60 / **上限10** | 公開 | 可 | ESC | `[]` |
| `privacy.contact_window` | PR-08 | 必須 | string | 200 | 公開 | 可 | ESC | `""` |
| `privacy.consent_checkbox` | PR-09 | Smart Labo設定 | boolean | — | 内部 | 表示制御 | — | `true` |
| `privacy.marketing_use` | PR-10 | 必須 | enum | — | 公開 | 可 | ESC | `no` |

`third_party` = `none` / `yes`（`yes` の場合は `purpose` へ具体を記載）
`marketing_use` = `no` / `yes`

> ★`privacy.contact_window`（PR-08）は**掲載用の窓口**であり、
> `basic.internal_contact.email`（B-09）とは別物。混同しない（仕様書 §14.2）。
> ★プライバシーポリシーは**基本テンプレート＋人の最終確認**。
> 本 JSON は差し込み値であり、**法的完全性を保証しない**（仕様書 §14.1）。

### 3.10 image_metadata_json（写真メタ／本体は Drive）

| データパス | 仕様書 | 必須 | 型 | 最大 | 公開/内部 | 生成 | HTML出力 | 空値 |
|---|---|---|---|---|---|---|---|---|
| `image_metadata[]` | §12.4 | 必須（8件以上） | object[] | **上限60** | — | — | — | `[]` |
| `image_metadata[].file_name` | — | 必須 | string | 120 | 内部 | 参照解決 | — | `""`（Drive上のファイル名） |
| `image_metadata[].role` | IMG-01 | 必須 | enum | — | 内部 | 配置判定 | — | `other` |
| `image_metadata[].provider` | IMG-02 | 必須 | enum | — | **内部** | **不可** | — | `shop` |
| `image_metadata[].rights_confirmed` | IMG-03 | 必須 | boolean | — | **内部** | **除外判定** | — | `false` |
| `image_metadata[].person_consent` | IMG-04 | 条件付き | boolean | — | **内部** | **不可** | — | `false` |
| `image_metadata[].person_consent_date` | IMG-04 | 条件付き | string\|null | `YYYY-MM-DD` | **内部** | **不可** | — | `null` |
| `image_metadata[].alt` | IMG-05 | 任意 | string\|null | 120 | 公開 | 可 | ESC | `null`（当社が作成） |
| `image_metadata[].published` | IMG-06 | 必須 | boolean | — | 内部 | 表示制御 | — | `true` |
| `image_metadata[].placement` | IMG-07 | 任意 | enum\|null | — | 内部 | 配置 | — | `auto` |
| `image_metadata[].expires_on` | IMG-08 | 任意 | string\|null | `YYYY-MM-DD` | 内部 | 表示制御 | — | `null` |
| `image_metadata[].ai_generated` | IMG-09 | 必須 | boolean | — | **内部** | **不可** | — | `false` |
| `image_metadata[].note` | IMG-10 | 任意 | string\|null | 200 | 内部 | 不可 | — | `null` |

`role` の語彙: `exterior` / `interior` / `shampoo_wait` / `style` / `staff` / `product` /
`logo` / `reception` / `treatment_room` / `treatment_scene` / `equipment` / `locker` /
`owner` / `tools` / `landmark` / `other`
`provider` の語彙: `shop` / `photographer` / `ai` / `other`
`placement` の語彙: `hero` / `section` / `auto`

検証（仕様書 §12 に一致させる）:
- `rights_confirmed = false` の要素は**生成対象から除外**する
- 人物が写る `role`（`staff` / `owner` / `treatment_scene`）は `person_consent = true` と
  `person_consent_date` を必須とする
- `provider = ai` のとき `ai_generated = true` を必須とし、**代表承認が済むまで生成へ渡さない**
- 枚数は §12.3 の業種別最低構成を満たすこと（提出時に判定）
- **画像本体・URL・Drive のファイルIDは保存しない**（保持するのは `file_name` のみ）

### 3.11 rights_json（権利・同意・法的確認）

| データパス | 仕様書 | 必須 | 型 | 最大 | 公開/内部 | 生成 | HTML出力 | 空値 |
|---|---|---|---|---|---|---|---|---|
| `rights.confirmations[]` | L-01〜L-13 | 必須（**13件すべて true**） | object[] | **固定13要素** | **内部** | **不可** | — | 13要素で初期化 |
| `rights.confirmations[].code` | — | 必須 | enum `L-01`〜`L-13` | — | 内部 | 不可 | — | — |
| `rights.confirmations[].agreed` | — | 必須 | boolean | — | 内部 | 不可 | — | `false` |
| `rights.confirmations[].agreed_at` | — | 条件付き | string\|null | ISO日時 | 内部 | 不可 | — | `null` |
| `rights.agreed_by` | — | 必須 | string | 60 | **内部** | **不可** | — | `""`（同意した担当者名） |
| `rights.note` | — | 任意 | string\|null | 500 | 内部 | 不可 | — | `null` |

> ★`rights_json` は**すべて内部**。生成HPへ1項目も渡さない。
> ★これは**同意の証跡**であり、§9.4 のとおり回答削除後も
> **Smart Labo Operations 側**へ移して継続保持する
> （Operations 完成前は §1.3 の標準管理票）。

### 3.12 Smart Labo 入力と店舗入力の分離

| 区分 | 該当 | 保存場所 |
|---|---|---|
| **店舗が入力** | §3.1〜§3.11 のうち「必須／任意／条件付き」の項目 | `intake_answers` |
| **Smart Labo が入力** | `web_links.salon_booking_url`（W-05）／`privacy.destination`（PR-03）／`privacy.storage`（PR-04）／`privacy.external_services[]`（PR-07）／`privacy.consent_checkbox`（PR-09）／`image_metadata[].alt` の補完（IMG-05） | `intake_answers`（管理画面から） |
| **正式パスの内訳**（v1.9） | 店舗 **129** ＋ Smart Labo 設定 **5** ＝ **134**。`promotion.industry` は含めない | — |
| **Smart Labo が案件に持つ** | `case_number` / `contract_type` / `status` / Drive URL / 期限 | `intake_cases` |
| **intake に持たない** | C-01〜C-16（契約・請求・Stripe参照）／公開承認 | **Smart Labo Operations 側**（未実装の間は §1.3 の標準管理票） |

画面上でも、店舗入力欄と Smart Labo 設定欄は**同じ画面に混在させない**（4C で分ける）。

**Smart Labo 設定5件の扱い（v1.9 で確定・代表判断 Q4）**

| # | 規則 |
|---|---|
| 1 | 正式パスへ**追加する**（129 → 134）。保存先は既存の `intake_answers` の JSON 内 |
| 2 | **新しい表も列も作らない。** migration 版・回答スキーマ版も変えない |
| 3 | **店舗の入力項目にしない。** 店舗画面へ欄を作らない |
| 4 | **店舗の提出条件に含めない**（`ADMIN_REQUIRED_FOR_EXPORT`。§3.0.2） |
| 5 | **`GET /case` で店舗へ返さない** |
| 6 | **`POST /answers/save` から変更できない**（店舗の要求に混ざっていたら丸ごと拒否） |
| 7 | 逆に、店舗が分類をまるごと保存しても**この5件が消えない**（保存済みの値を残す） |
| 8 | 設定できるのは**管理者だけ**。管理者認証・管理 session・CSRF・Origin 検査・**案件番号の再入力**を必須とする |
| 9 | 設定できる案件状態は **`reviewed` / `locked`**。`closed` と削除済みは変更しない |
| 10 | **検証済み書き出しの前に検証する**（§11.3）。不足なら書き出しを拒否する |
| 11 | 「設定した」＝**キーが存在し、かつ中身が下表を満たすこと**（v1.10 で厳格化）。<br>該当が無い項目も、空のまま保存して**記録を残す**（「まだ設定していない」と区別するため） |
| 12 | **値をログ・監査へ出さない**。監査は `admin_settings_saved` / `ok` の固定語彙だけ |
| 13 | Operations・AI Sales へ保存しない。HP Intake 内の制作設定として持つ |
| 14 | token / session / Drive 情報と**同じ画面に混ぜない** |
| 15 | 型・上限・語彙は §3.7（W-05）・§3.9（PR-03/04/07/09）の表に従う |

**内容条件（v1.10 で確定・4F-R4）**

v1.9 では5件とも「キーがあれば設定済み」だった。しかしそれでは
**送信先も保管方法も空のまま**「検証済み JSON」を書き出せてしまう。
空でよい項目と、空では困る項目を分ける。

| パス | 型 | 空の扱い | そのほかの条件 |
|---|---|---|---|
| `web_links.salon_booking_url` | string | **空は正式**（「予約URLなし」） | 値があるときは **`https://` のみ**。<br>userinfo・制御文字・空白を含まない。500文字まで |
| `privacy.destination` | string | **空・空白だけは不可** | 200文字まで |
| `privacy.storage` | string | **空・空白だけは不可** | 200文字まで |
| `privacy.external_services` | string[] | **`[]` は正式**（「外部サービスなし」） | 10件まで／各60文字まで。<br>要素は空・空白だけを許さない |
| `privacy.consent_checkbox` | boolean | — | **`true` / `false` のみ**。`false` は<br>「同意チェックを設置しない」という管理者の明示判断 |

| # | 規則 |
|---|---|
| 1 | **1件でも満たさなければ5件とも保存しない**（部分保存しない） |
| 2 | 満たさない**パスだけ**を管理者へ案内する。**入力された値を画面へ反射しない** |
| 3 | 型・上限は §3.7（W-05）・§3.9（PR-03/04/07/09）の表と同じ値を使う |
| 4 | 画面は型に合った入力欄を使い、必須・空欄可を**文字でも示す** |

> ★`ADMIN_REQUIRED_FOR_EXPORT` は「キーがあること」だけを見る種別ではない。
> 空でよい項目と、空では困る項目が混ざっている。判定は生成物が持つ。

> ★管理設定の不足だけを理由に、店舗を `needs_revision` へ戻さない。
> 店舗回答の不足と Smart Labo 設定の不足は、管理画面でも**別々に表示**する。

---

## 4. token

### 4.1 生成と保存

| # | 規則 |
|---|---|
| 1 | `random_bytes(32)`（暗号論的乱数）で生成する |
| 2 | **base64url エンコードで 43文字**（パディング `=` を付けない） |
| 3 | DBへは **SHA-256 ハッシュの16進64文字のみ**を保存する（`intake_tokens.token_hash`） |
| 4 | **平文 token を保存する列を作らない。** ファイル・キャッシュ・セッションにも保存しない |
| 5 | 平文 token は**発行直後の1回だけ**管理画面へ表示する。画面を閉じたら再表示できない |
| 6 | 当社側に平文の控えを残さない。紛失時は**再発行**で対応する |
| 7 | 照合は「受け取った token を SHA-256 → `token_hash` で検索」。平文比較をしない |

### 4.2 URL の形（正式採用・4B-PRE で確定）

```text
https://intake.smartlaboworks.com/start#<token>
（<token> は base64url 43文字。案件番号・店舗名をURLへ含めない）
```

**`/f/<token>` 方式は採用しない。** Webサーバーのアクセスログに token が残るため。

- **URL fragment（`#` 以降）は HTTPリクエストへ送信されない。**
  したがって nginx のアクセスログにも、プロキシにも、Referer にも token が載らない。
- **案件番号や店舗名をURLへ入れない**（列挙・推測の手がかりを与えない）。
- token 単体から案件を解決する（`token_hash` で検索）。
- 交換手順の全体は §4.7 に定める。

### 4.3 有効期限・本数

| # | 規則 |
|---|---|
| 1 | 有効期限は**発行から14日**（`expires_at`） |
| 2 | **1案件につき有効 token は常に1本** |
| 3 | 再発行時は、既存の有効行に `revoked_at` をセットしてから新規行を作る（同一トランザクション） |
| 4 | 期限延長は**行わない**。延長が必要なら再発行する |
| 5 | 提出後の編集可能期間（仕様書 §18.2 の運用に合わせ**7日**）を過ぎたら `locked`（§5）へ移し、token を失効させる |

### 4.4 失効（revoke）

- `revoked_at` を持ち、**漏えい・誤送信・担当者交代の際に即時無効化**できる。
- 案件が `closed` へ移った時点で、その案件の全 token を失効させる。
- 失効・期限切れの行は**削除しない**（監査のため残す）。§9 の削除規則に従う。

### 4.4.1 再発行（v1.6 で確定・4D-R2 で実装）

**ご案内リンクの平文は、発行直後に1回しか表示しない**（§10.8）。
通信が切れた・画面を閉じたなどで受け取れなかった場合、**復元はできない**。
そのときは**新しい token を発行する**。これが唯一の回復手段である。

> ★「以前の token を復元する」機能を作らない。
> 復元できるということは、どこかに平文が残っているということである。

**再発行してよい状態**

| 状態 | 再発行 | 理由 |
|---|---|---|
| `draft` | **可** | まだ入力中。リンクを配り直してよい |
| `needs_revision` | **可** | 差し戻し中。店舗が再入力する必要がある |
| `submitted` | **不可** | 受付済み。新しい編集リンクを出さない |
| `reviewed` | **不可** | 同上。修正が要るなら**先に `needs_revision` へ**（§5.1） |
| `locked` / `closed` | **不可** | 確定済み。§5.2 のとおり token ごと失効させている |
| 未知の状態 | **不可** | 判断を漏らさない（fail closed） |

**手順（すべて同一トランザクション）**

| # | 処理 |
|---|---|
| 1 | 案件の状態を**トランザクションの中で**もう一度確かめる |
| 2 | その案件の**有効な token をすべて失効**させる |
| 3 | その token から発行された**店舗 session をすべて失効**させる |
| 4 | `random_bytes(32)` で新しい token を作る |
| 5 | DBへは **SHA-256 hash のみ**保存する（有効期限は発行から14日。§4.3） |
| 6 | 監査へ **`token_reissued`** を1件記録する |

**必ず守ること**

| # | 規則 |
|---|---|
| 1 | **店舗の入力済み回答を消さない**（`version` / 提出履歴 / 修正依頼 / Drive 情報も維持） |
| 2 | 新しい平文リンクは**成功画面で1回だけ**。戻る・再読込で再表示しない |
| 3 | token 平文を**ログ・監査・DBの平文列・管理 session・URL・Cookie へ出さない** |
| 4 | 誤操作を防ぐため、実行前に**案件番号の再入力**を求め、**完全一致**のときだけ実行する |
| 5 | 再発行は **POST のみ**。CSRF・Origin 検査・管理 session を必須とする（§10.8） |
| 6 | **案件単位 ＋ HMAC化IP で 10分5回**のレート制限をかける（誤操作・連打を止める） |

**通信が切れた場合**

DB上は成功しているため、**旧 token はすでに無効**である。
管理画面へ戻ってもう一度再発行してよい。そのとき直前の新 token も失効し、
さらに新しい token が出る。監査は**そのたびに1件**増える。回答内容は維持される。

### 4.5 検証の順序（4B で実装）

**(A) 初回交換 `POST /session/start`（body で token を受け取る）**

```text
1. HTTPS か（違えば 301）
2. Origin を厳格検査（https://intake.smartlaboworks.com 以外は即 reject）
3. token が 43文字の base64url か（形式不一致は即 reject）
4. rate limit（HMAC化IP単位・10分5回。§0.2）
5. SHA-256 → token_hash 検索
6. revoked_at が NULL か
7. expires_at > 現在時刻か
8. 案件の status が操作を許すか（§5）
→ すべて満たしたときのみ
   ・token.last_used_at を更新
   ・session secret を発行し、SHA-256 hash を intake_sessions へ保存
   ・Cookie（Secure / HttpOnly / SameSite=Strict）を設定して /form へ
```

**(B) 2回目以降（Cookie の session secret で継続）**

```text
1. HTTPS か（違えば 301）
2. Origin / Referer を検査
3. rate limit（token/session ＋ HMAC化IP単位・10分60回。§0.2）
4. SHA-256(Cookie値) → session_hash 検索（hash_equals 等の定数時間比較）
5. revoked_at が NULL か
6. expires_at > 現在時刻 かつ absolute_expires_at > 現在時刻 か
7. **紐づく token が有効か**（revoked / expired なら session も無効・§2.6）
8. 案件の status が操作を許すか（§5）
→ すべて満たしたときのみ expires_at を延長（最終利用から24時間）して続行
```

★(A)(B) いずれの失敗も、外部へは §4.6 の**同一文言・404**で返す。

### 4.6 エラー文言（外部へ返すもの）

**token 不正・不存在・期限切れ・失効は、すべて同一の文言・同一のHTTPステータスで返す。**

```text
このURLは使用できません。
お手数ですが、担当者までご連絡ください。
```

- ステータスは **404** に統一する（403 と 404 を出し分けると存在有無が漏れる）。
- 応答時間の差で判別されないよう、検索失敗時も**同等の処理時間**を確保する。
- 監査ログ（§2.5）には `invalid` / `expired` / `revoked` / `not_found` を**区別して記録**する。

### 4.7 token が漏れる経路と対策

| 経路 | リスク | 対策 |
|---|---|---|
| **URLそのもの**（メール・LINE転送） | 受け取った人が誰でも開ける | 有効期限14日／提出後7日でロック／**即時 revoke 可能**／送付前に電話等で本人確認 |
| **Referer ヘッダー** | 外部リンクを踏むと遷移先へURLが渡る | 全ページに **`Referrer-Policy: no-referrer`**。外部リンクは `rel="noopener noreferrer"` |
| **ブラウザ履歴・共有端末** | 端末を共有していると残る | 提出後7日でロック／`Cache-Control: no-store` |
| **プロキシ・中間キャッシュ** | URL がキャッシュされる | `Cache-Control: no-store, no-cache, must-revalidate` ／ `Pragma: no-cache` ／ HTTPS 必須 |
| **Webサーバのアクセスログ** | URL パスに token が残る | **`/start#<token>` 方式を採用。fragment はリクエストへ送られないため、そもそもログに載らない**（§4.2） |
| **アプリログ** | 実装ミスで平文が出る | ログ出力関数で base64url 43文字の連続を `[REDACTED]` へ置換する共通処理を必須にする |
| **外部解析・外部画像・第三者スクリプト** | Referer や計測でURLが外部へ出る | **intake の画面に外部リソースを一切読み込まない**（自ドメインのみ。CSPで強制。§10.5） |
| **エラー画面・スタックトレース** | 例外表示にURLが混ざる | 詳細を外部へ出さない。固定文言のみ（§10.6） |

**token 初回交換方式（正式採用・4B-PRE で確定）**

XServer 側のアクセスログはアプリから制御できない。したがって
**token を URL パス・query のどこにも置かない**構成を採る。

```text
 1. 店舗へ渡すURLは  https://intake.smartlaboworks.com/start#<token>
 2. URL fragment は HTTPリクエストへ送信されない（ログ・Referer・プロキシに残らない）
 3. 同一オリジンのローカルJSが fragment を読む
 4. POST /session/start の body で token を送る
 5. Origin を厳格検査する
 6. token を hash 化して DB 照合する（§4.5-A）
 7. 有効ならランダムな session secret を発行する（random_bytes(32)）
 8. DBには session secret の SHA-256 hash だけを保存する（§2.6）
 9. Cookie へ平文 session secret を設定する
10. Cookie は Secure / HttpOnly / SameSite=Strict
11. history.replaceState で fragment を即時削除する
12. /form へ遷移する
13. 以後、店舗画面では元 token を使用しない
```

| # | 遵守事項 |
|---|---|
| 1 | **token・session secret を アプリログ・PHPエラーログ・通知メールへ出さない**（§10.7） |
| 2 | fragment は `history.replaceState` で**即時削除**する（履歴・共有端末対策） |
| 3 | Cookie は **Secure / HttpOnly / SameSite=Strict**。JS から読めない |
| 4 | **JS無効時は、秘密値をURLパスやqueryへ移さない。**<br>「このフォームを利用するにはJavaScriptを有効にしてください」と表示して終了する |
| 5 | `/start` 自体は token を持たないため、単独でアクセスされても情報を返さない |

### 4.8 noindex の位置づけ

- `noindex` / `robots.txt` 除外は**検索結果に出さないための補助**にすぎない。
- **認証の代替にしない。** URL を知る者は到達できる前提で、
  期限・失効・再発行・ロックを必ず実装する（§4.3〜§4.4）。
- 「noindex だから安全」という説明を、社内資料にも営業説明にも用いない。

---

## 5. 状態遷移

### 5.1 案件の状態（intake_cases.status）

```text
draft ──submit──> submitted ──┬── request_revision ──> needs_revision ──resubmit──> submitted
                              └── approve_review ────> reviewed ──lock──> locked ──close──> closed
                                                          │
                                                          └── request_revision ──> needs_revision
                                                              （v1.5 で追加）
```

| 状態 | 意味 |
|---|---|
| `draft` | 作成直後〜初回提出前。店舗が入力中 |
| `submitted` | 店舗が提出済み。Smart Labo が確認中 |
| `needs_revision` | 不足・修正を店舗へ差し戻した状態 |
| `reviewed` | Smart Labo の確認が完了した状態 |
| `locked` | 編集不可。制作・公開工程が進行中 |
| `closed` | 案件終了。保持期間の処理対象（§9） |

許可される遷移（これ以外は行わない）:

| From | To | 実行者 |
|---|---|---|
| `draft` | `submitted` | 店舗 |
| `submitted` | `needs_revision` | Smart Labo |
| `submitted` | `reviewed` | Smart Labo |
| `needs_revision` | `submitted` | 店舗（再提出） |
| **`reviewed`** | **`needs_revision`** | **Smart Labo（v1.5 で追加）** |
| `reviewed` | `locked` | Smart Labo |
| `locked` | `closed` | Smart Labo |
| 任意 | `closed` | Smart Labo（中止案件） |

**`reviewed` → `locked`（確定）の規則（v1.7 で確定・4F-PRE で実装）**

`locked` の意味は「**店舗入力を確定し、通常編集を終了した**」であり、**削除ではない**。

| # | 規則 |
|---|---|
| 1 | **`reviewed` からのみ**確定できる。他の状態からは行かない |
| 2 | 管理画面の **POST のみ**。CSRF・Origin 検査・管理 session を必須とする |
| 3 | 確認画面を出し、**案件番号の再入力（完全一致）**を求める |
| 4 | 実行時、その案件の **token をすべて失効**させる |
| 5 | 実行時、その案件の **店舗 session をすべて失効**させる |
| 6 | 状態変更・履歴・token 失効・session 失効を**同一トランザクション**で行う。<br>★「確定したのに古いリンクがまだ生きている」状態を1瞬も作らない |
| 7 | 監査へ残す（`case_status_changed` / `token_revoked` / `session_revoked`） |
| 8 | **冪等**。すでに `locked` なら何もせず成功（履歴も監査も増やさない） |
| 9 | **回答・提出履歴・修正依頼・Drive 情報を削除しない**（削除は §9.3 の別操作） |
| 10 | `locked` から `needs_revision` へ**戻さない**（`REVISABLE` に入れない） |
| 11 | `locked` から token を**再発行しない**（`REISSUABLE` に入れない） |

**`closed` の扱い（v1.7 で確定）**

- **`closed` への通常操作を管理画面に作らない。**
- `closed` は **§9.3 の機密情報削除が完了した時点で設定**される（`deleted_at` と同時）。
- 中止案件を `closed` にする経路は、必要になった時点で本書を改定してから作る。

**`reviewed` → `needs_revision` を許す理由（v1.5・代表判断）**

| # | 内容 |
|---|---|
| 1 | 実運用では、確認後に**写真・料金・表現・権利確認**の不足が見つかりうる |
| 2 | 戻せない設計は、**DBの直接操作や別管理**を誘発する。それを運用にしない |
| 3 | 戻せるのは **`locked` / `closed` より前**まで。確定後は戻さない |
| 4 | 差し戻したら、`reviewed` へ進むには**店舗の再提出が必要**（近道を作らない） |
| 5 | 差し戻しは必ず**修正依頼（§2.9）とともに**行い、監査へ残す |

### 5.2 状態別に許可する操作

| 状態 | 店舗：閲覧 | 店舗：入力・途中保存 | 店舗：提出 | Smart Labo：確認 | Smart Labo：書き出し | token |
|---|---|---|---|---|---|---|
| `draft` | ○ | ○ | ○ | ○ | △（暫定） | active |
| `submitted` | ○（**読み取りのみ**） | × | × | ○ | ○ | active |
| `needs_revision` | ○ | ○ | ○（再提出） | ○ | ○ | active |
| `reviewed` | ○（読み取りのみ） | × | × | ○ | ○ | active |
| `locked` | ×（URL失効） | × | × | ○ | ○ | **expired / revoked** |
| `closed` | × | × | × | ○（§9.4 の残余のみ） | × | revoked |

> ★v1.7: **削除済み**（`deleted_at` が非 NULL）の案件では、`closed` の列に関わらず
> 書き出し・token 発行・再発行・session 発行・状態変更を**すべて拒否**する（§9.3-4）。
> 管理画面の詳細も **§9.4-2 の最小メタデータだけ**を出す。

### 5.3 token の状態

`intake_tokens` の列から導出する（状態列を別に持たない＝二重管理を作らない）。

| token状態 | 判定 |
|---|---|
| `active` | `revoked_at IS NULL` かつ `expires_at > now` |
| `expired` | `revoked_at IS NULL` かつ `expires_at <= now` |
| `revoked` | `revoked_at IS NOT NULL` |

★利用者へは3状態を**区別せず**、常に §4.6 の同一文言を返す。

**session の状態も同じ方式で導出する**（§2.6）。

| session状態 | 判定 |
|---|---|
| `active` | `revoked_at IS NULL` かつ `expires_at > now` かつ `absolute_expires_at > now` <br>**かつ 紐づく token が active** |
| `expired` | 上記の期限いずれかを過ぎている |
| `revoked` | `revoked_at IS NOT NULL`、または**紐づく token が revoked** |

★**token が失効したら session も必ず失効する。**
　`locked` / `closed` への遷移時、token 再発行時、漏えい判明時は、
　当該案件の **token と session を同一トランザクションで失効**させる。

### 5.4 公開承認は含めない

- **公開承認をこの状態遷移へ含めない。**
- 公開承認の正式記録は **Smart Labo Operations**（および仕様書 §18.3 の公開条件）。
- intake の `reviewed` は「**入力内容の確認が済んだ**」という意味であり、公開の可否ではない。
- 誤解を避けるため、画面文言に「公開」「承認して公開」という語を使わない（4C/4D で徹底）。

---

## 6. 途中保存

### 6.1 自動保存と手動保存の責任範囲

| 種別 | 契機 | 保存範囲 | 検証 |
|---|---|---|---|
| **自動保存** | 入力停止後の一定時間、またはステップ移動時 | **その時点のセクションのみ**（部分更新） | 形式検証のみ（型・最大長・配列上限）。**必須チェックはしない** |
| **手動保存** | 「保存」ボタン | 表示中セクション | 同上 |
| **提出** | 「送信」ボタン | 全セクション | **必須チェックを含む全検証**（§16.1 の判定はSmart Labo側） |

- 自動保存は `draft` / `needs_revision` でのみ動作する。

**自動保存の方式（v1.3 で確定。§12-8 の未確定から外す）**

| # | 規則 |
|---|---|
| 1 | 契機は次の3つだけ … ①**最終変更から30秒後** ②**ステップ移動時** ③**手動保存ボタン** |
| 2 | 30秒は「最終変更から」数える。入力中は数え直す（**打鍵のたびに送らない**） |
| 3 | UIから送るのは「**変更された分類だけ**」（未変更の分類を送り返して上書きしない） |
| 4 | 成功したら、応答の `version` を**画面の最新値へ更新**する（次の保存の楽観ロック基準） |
| 5 | **409 のときは上書きしない**。入力内容を画面に残したまま、<br>「最新を読み込む／このまま上書き」を**利用者へ確認**させる（§6.3・§6.5） |
| 6 | 失敗しても**自動で再試行しない**（多重保存を防ぐ。§6.5） |

> ★30秒はUI側の運用値である。**データモデル・API は間隔に依存しない**（間隔を変えても DB は変わらない）。

### 6.2 同時更新防止

- 1案件に対する同時編集は想定しないが、**同じURLが2端末で開かれる**ことは起こりうる。
- 保存は `intake_answers.version` による**楽観ロック**で防ぐ。

### 6.3 楽観ロックの規則

```text
UPDATE intake_answers
   SET <対象JSON列> = :json,
       version    = version + 1,
       updated_at = :now
 WHERE intake_case_id = :id
   AND version = :client_version;
→ 影響行数 0 なら「他の端末で更新されています」（HTTP 409 / result_code=conflict）
```

- クライアントは読み込み時に受け取った `version` を保存要求へ必ず含める。
- 409 の場合、**入力内容を捨てない**。画面に残したまま「最新を読み込む／このまま上書き」を選ばせる。
- `updated_at` は表示用。**衝突判定には `version` を使う**（時刻は精度と時計ずれの問題があるため）。

### 6.4 二重送信防止（v1.3 で全面改定・4B-R1 で実装）

守る対象は3つある。**状態**・**提出履歴**・**監査イベント**。
どれか1つでも重複すると、後工程（Operations・請求参照）が実態と食い違う。

**多層で守る**

| 層 | 手段 | 防げるもの |
|---|---|---|
| 1. 画面 | 「送信」押下直後にボタンを無効化する | 利用者の連打 |
| 2. 冪等化キー | **`submission_id`（クライアント生成 UUID v4）** | **通信の再送・応答消失後の再試行・同時送信** |
| 3. 状態 | `status` による判定 | 別要求としての二重提出 |
| 4. DB | **`UNIQUE(intake_case_id, submission_id)` 部分一意索引** | 上記をすり抜けた**競合**（最後の防御線） |

> ★層2が本体である。層1・層3だけでは、**「サーバーは受理したが応答が届かなかった」**
> 場合の再送を区別できない（利用者にも画面にも、成功したかどうかが分からないため）。

**`submission_id` の生成契約（4C が守る）**

| # | 規則 |
|---|---|
| 1 | **「送信」を押すたびに新しい UUID v4 を生成**する |
| 2 | **同じ要求の再試行のときだけ、同じ値を送る**（通信断・タイムアウト後の再送） |
| 3 | 検証エラーを直してから送り直すのは「**新しい提出要求**」である。**新しい値を生成する** |
| 4 | `submission_id` を localStorage / sessionStorage へ**恒久保存しない**（§6.6） |

**`POST /submit` の挙動（正式）**

| 入力・状態 | HTTP | 応答 | 副作用 |
|---|---|---|---|
| `submission_id` **欠落** | **400** | 固定文言 | **なし**（履歴・監査を増やさない） |
| `submission_id` が **UUID v4 でない** | **400** | 固定文言 | **なし** |
| **初回**・必須条件を満たす | 200 | `submitted: true` | 履歴1件（`submission_id` 付き・`ok`）／`submitted` へ遷移／**監査1件** |
| **初回**・必須条件を満たさない | 200 | `submitted: false` ＋ **不足パスのみ** | 履歴1件（`submission_id` 付き・`validation_error`）／**状態は変えない** |
| **同一 `submission_id` の再送**（記録が `ok`） | 200 | **初回と同じ成功結果** | **なし**。状態を変えず、回答も再保存せず、**履歴も監査も増やさない** |
| **同一 `submission_id` の再送**（記録が `validation_error`） | 200 | 現時点の不足パスを再評価して返す | **なし**。**履歴も監査も増やさない** |
| **異なる `submission_id`** で `submitted` / `reviewed` の案件へ提出 | **409** | 固定文言 | **なし**。**既存の `submission_id` も提出済み内容も返さない** |
| 一意制約に競合した（同時送信） | 200 | 記録済みの結果を読み直して返す | **なし**（読めない場合のみ 409・固定文言） |

**必ず守ること**

| # | 規則 |
|---|---|
| 1 | 履歴の追加と状態遷移は**同一トランザクション**で行う。<br>「履歴はあるが状態が変わっていない」中間状態を残さない |
| 2 | 一意制約違反（競合）は**例外を外へ出さない**。§4.6 / §10.6 の固定応答へ変換する |
| 3 | 409 の本文へ、**既存の `submission_id`・提出日時・提出済みの回答**を含めない |
| 4 | `submission_id` を**ログ・監査 `result_code`・エラー本文へ出さない**（§10.7） |
| 5 | `status` による判定を**やめない**。`submission_id` は `status` を**置き換えるのではなく補強**する |

> ★4B（v1.2）では `status` だけで守っていた。これは「押し直し」は防げるが、
> **応答が消えた後の再送**を成功と区別できず、履歴・監査が重複しうる。
> v1.3 の `submission_id` はその穴を塞ぐためのものである。

### 6.5 保存失敗時の表示

| 事象 | 画面表示 | 内部 |
|---|---|---|
| ネットワーク断 | 「保存できませんでした。接続を確認してください。入力内容は画面に残っています」 | 再試行は自動で行わない（多重保存を防ぐ） |
| 409 conflict | 「他の端末で更新されています」＋選択肢 | `result_code=conflict` を監査へ |
| 検証エラー | 該当項目へインライン表示 | 値をログへ出さない |
| token 失効 | §4.6 の同一文言 | `result_code=revoked/expired` を監査へ |

**入力途中の内容を失わせないことを最優先**にする。

### 6.6 ブラウザ側の保存禁止

| # | 規則 |
|---|---|
| 1 | **`localStorage` へ回答本文を保存しない**（永続化されるため） |
| 2 | **`sessionStorage` にも機微情報を恒久保存しない。** 一時的な下書き復元に使う場合も、<br>保存してよいのは**現在編集中のセクションのみ**とし、保存成功・提出・離脱時に**必ず削除**する |
| 3 | token を localStorage / sessionStorage へ保存しない |
| 4 | 氏名・メール・電話・住所・同意記録をブラウザ保存領域へ書かない |
| 5 | オートコンプリートは、共有端末での漏えいを避けるため<br>個人情報欄で `autocomplete="off"` を指定する |

### 6.7 ページ離脱警告・復元

- 未保存の変更がある状態での離脱・再読み込みには `beforeunload` で警告する。
- **再読み込み時の復元は「サーバーの保存済み内容を再取得する」方式**とする。
  ブラウザ保存領域からの復元を主にしない（§6.6-2 の例外運用に依存しない）。
- 復元時は `version` も再取得し、楽観ロックの基準を更新する。

---

## 7. Google Drive

### 7.1 運用規則（正式）

| # | 規則 |
|---|---|
| 1 | Drive は **Smart Labo 所有**。店舗のアカウントに作らせない |
| 2 | **1店舗1フォルダ**。他店舗の素材と同じ場所に置かない |
| 3 | 共有は**店舗の指定メールアドレスだけ**（個別の閲覧者／編集者権限） |
| 4 | **「リンクを知っている全員」への共有は禁止**（設定してはならない） |
| 5 | 店舗は**画像本体を Drive へアップロード**する |
| 6 | intake が保持するのは**枚数・種類・権利確認・アップロード完了申告のみ**（§3.10） |
| 7 | **Drive URL は Smart Labo 管理画面側で登録する。店舗の自由入力欄を作らない** |
| 8 | **URL を監査ログ・通知メールへ出さない**（§10.7） |
| 9 | 公開後の**保持期間と削除予定日を案件ごとに記録**する（§9） |
| 10 | Google のパスワード・認証コード・二段階認証コードを**受け取らない**（§8） |
| 11 | Drive API を使わない（Phase 1・§11）。連携は人の操作で行う |
| 12 | **フォルダ名に店舗名・氏名・電話番号・住所・メールアドレスを含めない**（v1.3） |
| 13 | 最上位フォルダ名は**案件番号だけ**とする（v1.3） |
| 14 | 直下に**固定のサブフォルダ4つ**を作る。名前を案件ごとに変えない（v1.3） |

**フォルダ命名規則（v1.3 で確定）**

```text
HP-202608-0001/          ← 最上位。**案件番号のみ**。店舗名を入れない
├── 01_images/           写真（外観・内観・施術シーン 等）
├── 02_logo/             ロゴ・シンボル
├── 03_documents/        店舗から預かる書類
└── 04_references/       参考サイト・見本
```

| # | 理由 |
|---|---|
| 1 | Drive の**共有リンクやファイル名が第三者の目に触れても、どの店舗かが分からない** |
| 2 | 店舗名は**改称・表記ゆれ**が起きる。案件番号は変わらない |
| 3 | サブフォルダを固定にすると、**受け渡しの手順書が1つで済む**（店舗ごとに説明を変えない） |

> ★本工程（4B-R1）では **Drive へ接続しない**。命名規則を設計へ反映するだけである。
> フォルダの作成・共有は人の操作で行う（規則11）。

### 7.2 店舗画面での見せ方

- 店舗画面には **Drive フォルダへのリンクを表示してよい**。
  理由: Drive 側の権限が**指定メールアドレスに限定**されているため、
  URL を知ること自体はアクセス権にならない。
- ただし表示は次を守る。
  - リンクは `rel="noopener noreferrer"`、`Referrer-Policy: no-referrer` 配下で開く
  - 「このフォルダは○○（指定メール）にのみ共有しています」と明記する
  - **店舗がURLを編集・追加できる欄は作らない**（§7.1-7）
- 店舗が入力するのは次だけ。
  - `image_metadata[]`（枚数・種類・権利確認・人物同意・alt候補 等）
  - 「アップロード完了」チェック → `intake_cases.drive_upload_confirmed_at`

### 7.3 URL の保存方式

| # | 規則 |
|---|---|
| 1 | `intake_cases.drive_folder_url_enc` に**暗号文**として保存する |
| 2 | 方式は **AES-256-GCM**（認証付き暗号）。IV は毎回ランダム、認証タグを併せて保存 |
| 3 | 鍵は `private/intake-config.php` にのみ置く（**ドキュメントルート外・Git管理外**） |
| 4 | 復号するのは「Smart Labo 管理画面の表示時」と「店舗画面の描画時」のみ |
| 5 | **書き出しファイル・通知メール・監査ログ・エラー表示へ出さない** |
| 6 | 表示用の識別には `drive_folder_label`（フォルダ名）を使う |
| 7 | **共有先メールも同じ方式で暗号化**して `drive_shared_email_enc` へ持つ（v1.5） |

> ★暗号化は「DBファイルが単体で漏れた場合に、共有先の入り口を即座に晒さない」ための
> 多層防御である。**アクセス制御の代わりではない。**

**Drive URL の受け入れ条件（v1.5 で確定）**

管理画面から登録するときに、次をすべて満たさないものは**拒否**する。

| # | 条件 |
|---|---|
| 1 | `https://` で始まること（`http` / `javascript:` / `data:` / `file:` / `vbscript:` は拒否） |
| 2 | ホストが **Google Drive の正式ホスト**であること<br>（`drive.google.com` / `docs.google.com` / `drive.usercontent.google.com`） |
| 3 | **userinfo（`user:pass@`）を含まないこと**（表示上のホストを偽装できるため） |
| 4 | 短縮URL（`bit.ly` 等）を**受け付けない**（規則2で自動的に除外される） |
| 5 | ポート指定を含まないこと |
| 6 | 制御文字・空白・改行を含まないこと |
| 7 | **query と fragment は保持してよい**（Drive の `?usp=sharing` 等は正当）。<br>ただし**そのまま属性値としてのみ**使い、HTML へ文字列連結しない |
| 8 | 長さは 500 文字まで（§3.7 の URL 上限に合わせる） |

**共有先メールの扱い（v1.5 で確定）**

| # | 規則 |
|---|---|
| 1 | **平文で保存しない。** `drive_shared_email_enc` に AES-256-GCM で保存する |
| 2 | **書き出し（§11.3）へ含めない** |
| 3 | **監査ログ・アプリログへ出さない**（§10.7） |
| 4 | **管理画面の一覧へ出さない。** 詳細画面で必要なときだけ表示する |
| 5 | **店舗へは、session で認証された `GET /case` でだけ返してよい**。<br>本人への案内であり、§7.2 が求める文言を正確に書くために必要である |
| 6 | 回答本文（`intake_answers`）とは**別に扱う**。回答へ混ぜない |

### 7.4 障害時・例外

- Drive 側の障害・共有反映待ちの場合、**写真の後日提出を許容**する。
  その場合 `drive_upload_confirmed_at` は NULL のまま、他項目の提出を進めてよい。
- ただし**素材充足の判定（仕様書 §16.1 #12〜#14）は満たさない**ため、
  DAY2 へは進めない。例外は §16.3 の代表承認による。

---

## 8. 保存しない情報（禁止一覧）

**intake DB・intake のファイル・intake のログのいずれにも保存しない。入力欄も作らない。**

| # | 禁止する情報 | 補足 |
|---|---|---|
| 1 | **カード番号** | 決済は Stripe の画面で顧客が直接入力する |
| 2 | **有効期限** | 同上 |
| 3 | **セキュリティコード** | 同上 |
| 4 | **Stripe 秘密鍵** | intake は Stripe と一切通信しない |
| 5 | **LINE Channel Secret** | 仕様書 §10.2 |
| 6 | **LINE Access Token** | 同上 |
| 7 | **Google アカウントのパスワード** | 認証コード・二段階認証コードも含む |
| 8 | **メールパスワード** | |
| 9 | **DNS・サーバーのパスワード** | レジストラのログイン情報・SSH鍵・FTPも含む |
| 10 | **APIキー** | |
| 11 | **秘密鍵**（証明書の秘密部分を含む） | |
| 12 | **公開承認** | 正式記録は **Smart Labo Operations**（§5.4） |
| 13 | **Stripe customer ID** | 参照IDであっても intake には置かない |
| 14 | **Stripe invoice ID** | 同上 |
| 15 | **Stripe 入金状態** | 同上 |

> ★**Stripe 参照情報（customer ID / invoice ID / 請求日 / 入金確認日 / 追加請求状態）は、
> Smart Labo Operations 側でのみ保持する。**
> Operations のデータモデルは **OPS-1** で確定し、手動記録の運用は **OPS-3** で扱う。
> **AI Sales には保存しない。** Operations 完成前は §1.3 の標準管理票で代表が管理する。

**誤って秘密値が送信された場合**（仕様書 §10.3 と同じ扱い）
1. その値を他所へ転記・引用・保存しない
2. 案件記録から当該記述を削除する
3. 店舗へ連絡し、当該の資格情報を変更（reset）してもらう
4. 対応内容を1行で残す（**値そのものは書かない**）

---

## 9. 保持・削除・バックアップ

### 9.0 責任境界（v1.7 で確定・4F-PRE）

保持と削除でいちばん事故が起きやすいのは、**「いつ消してよいか」を誰が決めるか**が
曖昧なままになることである。v1.7 で次のとおり確定する。

| # | 規則 |
|---|---|
| 1 | **公開日を HP Intake へ保存しない** |
| 2 | **公開承認を HP Intake へ保存しない** |
| 3 | 公開日・公開承認は **Smart Labo Operations の責任**である（§5.4） |
| 4 | Operations 未完成の間は、**代表の標準管理票を正**とする（§1.3） |
| 5 | 「公開後6か月」の削除予定日は、**Operations または標準管理票の側で計算**する |
| 6 | HP Intake が持つのは **`retention_delete_due` の1列だけ**。人が**手動で登録**する |
| 7 | **`retention_delete_due` から公開日を逆算しない**（逆算できる書き方をしない） |
| 8 | Intake と Operations を**直接DB接続しない**。OPS-4 までは §11.3 の検証済み書き出しと手動登録を使う |
| 9 | **自動削除しない**。cron・起動時処理・バッチを作らない |
| 10 | Phase 1 の削除は**管理者による明示的な操作のみ** |
| 11 | **Google Drive の実ファイル削除は HP Intake の責任外**（§7.1）。Intake から Drive API を呼ばない |
| 12 | Drive の削除実施日は、**標準管理票または Operations 側**で管理する |
| 13 | **AI Sales へ保持情報を移さない**（§1.4） |

> ★「削除予定日だけを持つ」ことには理由がある。公開日を持てば、そこから
> 「6か月後」を Intake が計算することになり、**公開判断の責任が Intake へ滑り込む**。
> Intake は受付システムであって、公開工程の記録装置ではない。

### 9.1 保持期間（v1.7 で確定）

| 対象 | 保持期間 | 削除の仕方 |
|---|---|---|
| `intake_answers`（回答本文） | `retention_delete_due` まで | 期限到来後、**明示操作で物理削除** |
| `intake_tokens` | 案件の確定時に失効。行は削除時まで残す | 案件削除時に**物理削除** |
| `intake_sessions`（店舗） | 同上 | 案件削除時に**物理削除** |
| `intake_revision_requests` | **回答と同じ期限**（v1.7 で確定） | `open` / `resolved` を問わず案件削除時に**物理削除**。<br>`message` と `requested_paths_json` を残さない |
| `intake_submission_history` | 回答と同じ期限 | 案件削除時に**物理削除**。`submission_id` も残さない |
| Drive URL・共有先メール（暗号文） | 回答と同じ期限 | 案件削除時に **NULL 化**。**復号できる参照を残さない** |
| `intake_cases`（案件メタ） | 削除後も §9.4 の最小メタデータのみ残す | 行は消さない（allowlist で作り直す） |
| `intake_audit_events` | **13か月**（`created_at` 起算） | 期限到来後、**明示操作で物理削除**。HMAC化IP も同時に消える |
| `intake_admin_sessions` | 案件の保持期間とは**無関係** | 期限切れ・失効済みを保守操作で物理削除（§2.7-8） |
| Drive の写真本体 | 案件ごとに記録（§7.1-9） | **Intake の責任外**（§9.0-11） |

### 9.2 token の失効タイミング

- 提出後の編集可能期間（7日）終了時
- `locked` へ遷移した時
- `closed` へ遷移した時
- 再発行した時（旧 token と**関連する店舗 session** を即時失効。§4.4.1）
- 漏えい・誤送信が判明した時（即時）

### 9.3 削除の実施（v1.7 で全面改定・4F-PRE で実装）

**9.3-1 削除予定日の登録**

| # | 規則 |
|---|---|
| 1 | 登録できるのは **`reviewed` / `locked`** の案件だけ。運用としては**確定（`locked`）後に登録**する |
| 2 | 形式は **`YYYY-MM-DD`** の**実在する日付**のみ。`strtotime()` の寛容な解釈に頼らない |
| 3 | 過去日も登録できるが、**警告を出す**（即時に削除可能な状態になるため） |
| 4 | 同じ日付の再送は**冪等**（監査を増やさない）。別の日付への**変更は許すが、必ず監査へ残す** |
| 5 | **取り消し（NULL へ戻す）経路は作らない**。誤入力は「別の日付への変更」で直す |
| 6 | **公開日・公開承認・契約金額・Stripe 情報の入力欄を作らない**（§9.0） |
| 7 | POST のみ。CSRF・Origin 検査・管理 session を必須とする |
| 8 | ログへ書くのは**案件番号と結果だけ**。日付そのものを書かない |
| 9 | 管理画面に一覧を持つ。区分は **未設定 / 30日以内 / 期限到来 / 期限超過 / 削除済み** |
| 10 | 一覧に出してよいのは**案件番号・状態・日付・区分だけ**。回答本文・店舗名・Drive 情報を出さない |

**9.3-2 削除の実行条件（すべて満たすときだけ・fail closed）**

| # | 条件 |
|---|---|
| 1 | `retention_actions_enabled` が真（§9.8） |
| 2 | `backup_policy_confirmed` が真（§9.8） |
| 3 | 管理者として認証済み |
| 4 | 案件の状態が **`locked`** |
| 5 | `retention_delete_due` が登録済み |
| 6 | `retention_delete_due` ≦ サーバー日付 |
| 7 | `deleted_at` がまだ無い |
| 8 | 確認入力が **`DELETE <案件番号>`** と完全一致（前後の空白のみ落とす） |

> ★1つでも欠ければ実行しない。**「たぶん大丈夫」で通す経路を作らない。**

**9.3-3 削除の手順（同一トランザクション）**

| # | 手順 |
|---|---|
| 1 | 条件（9.3-2）を**トランザクションの中でもう一度**確認する |
| 2 | `intake_sessions` の当該案件行を削除する（`intake_tokens` を参照しているため先に消す） |
| 3 | `intake_tokens` の当該案件行を削除する |
| 4 | `intake_answers` の当該案件行を削除する |
| 5 | `intake_revision_requests` の当該案件行を削除する |
| 6 | `intake_submission_history` の当該案件行を削除する |
| 7 | `drive_folder_url_enc` / `drive_shared_email_enc` / `drive_folder_label` を NULL にする |
| 8 | `current_step` / `expires_at` を NULL にする |
| 9 | `shop_display_name` を固定文字列へ置き換える（NOT NULL のため） |
| 10 | `status` を `closed` にする（`closed_at` が空なら記録する） |
| 11 | `deleted_at` にサーバー時刻を記録する |
| 12 | 監査へ **`retention_purged` / `ok`** を1件だけ記録する（**削除した値も件数の内訳も書かない**） |

**失敗したとき**

- **全件ロールバック**する。部分削除を残さない
- 外部へは固定文言のみ。例外の内容・SQL・表名・パスを出さない
- PII をログへ出さない

**9.3-4 削除後の禁止事項**

削除済み（`deleted_at` が非 NULL）の案件では、次を**すべて拒否**する。

| # | 拒否する操作 |
|---|---|
| 1 | 検証済み JSON の書き出し（§11.3） |
| 2 | token の発行・再発行（§4.4.1） |
| 3 | 店舗 session の発行 |
| 4 | 回答の取得・保存・提出・Drive 完了申告 |
| 5 | 修正依頼 |
| 6 | 状態を戻すこと（`closed` から動かさない） |
| 7 | 削除予定日の登録・変更 |
| 8 | 二度目の削除（**安全に拒否**する。`deleted_at` を上書きしない・監査を増やさない） |

> ★管理画面の詳細は、削除済み案件では **§9.4 の最小メタデータだけ**を出す。
> 操作ボタンそのものを出さない。

**9.3-5 削除した内容をファイル上に残さない**

SQLite は既定では、削除した行の中身を**ページ上に残したまま**にする。
`DELETE` しただけでは、DBファイルを直接読むと回答本文・修正メッセージ・
暗号文が読めてしまう（**4F の実測で確認した**）。

| # | 規則 |
|---|---|
| 1 | 接続ごとに **`PRAGMA secure_delete = ON`** を設定する（SQLite 3.6.x 以降・本番 3.26.0 で利用可） |
| 2 | 「物理削除」と呼ぶ以上、ここは**必須**である。アプリ側の DELETE だけに依存しない |
| 3 | `VACUUM INTO` は使わない（§2.0.1）。`secure_delete` で足りるため VACUUM を運用に組み込まない |

**9.3-6 削除要請時の処理**（保持期間内でも）

- 店舗または本人（スタッフ・被写体）から削除要請があった場合、
  **削除予定日を要請日以前へ変更**したうえで 9.3-3 を実施する。
- 要請日・要請者の別（店舗／本人）・実施日は、**標準管理票または Operations 側**へ残す
  （**要請内容の本文は残さない**）。
- ★Intake 側に「要請理由」を書く欄を作らない。作れば、そこが新しい PII 置き場になる。

**9.3-7 自動実行しない**

- cron・起動時処理・バッチを作らない。
- 削除は**人が確認してから**、管理画面の確認 → 完全一致入力 → 実行の順で行う。
- 監査ログの13か月削除も同様に**明示操作**とする。

### 9.4 削除後も残す情報（継続保持）

**9.4-1 Operations 側（未実装の間は §1.3 の標準管理票）へ移して保持する**

回答本文の削除後も、次は移して保持する。**AI Sales へは保存しない。**

- 法的同意の証跡（`rights.confirmations[]` の code / agreed / agreed_at / agreed_by）
- スタッフ本人の掲載同意（S-13 / S-14）
- 被写体の掲載同意（IMG-04）
- 公開承認
- 素材権利台帳の要点（枚数・提供者区分・権利確認の有無）

> ★削除の**前に**移し終えていることを確認する（確認画面に明記する）。
> Operations へは接続しないため、確認は**人による申告**である。

**9.4-2 intake 側に残る最小メタデータ（allowlist）**

`intake_cases` に残してよいのは次だけである。**すべて PII を含まない。**

| 残す列 | 理由 |
|---|---|
| `id` | 主キー |
| `case_number` | 案件の識別。標準管理票との突合に要る |
| `contract_type` | `salon` / `standalone` のみ。個人を特定しない |
| `status`（= `closed`） | 終了済みであることを示す |
| `submitted_at` / `locked_at` / `closed_at` / `drive_upload_confirmed_at` | 日付のみ |
| `retention_delete_due` / `deleted_at` | 保持と削除の記録 |
| `schema_version` / `created_at` / `updated_at` | 版と時刻 |

`intake_audit_events` は残る（本文も PII も持たないため）。**13か月で削除される。**

> ★**列を追加したら、必ずこの分類に入れる。** 実装側は
> 「残す・NULL にする・置き換える」のどれにも入っていない列があれば
> テストで落ちるようにしてある（4F）。分類の漏れを人の注意力に頼らない。

### 9.5 バックアップ（v1.11 で全面改定・4G で実装）

| # | 規則 |
|---|---|
| 1 | Phase 1 は**日次・手動**（自動化しない）。**cron を作らない。ページ表示から実行しない** |
| 2 | 保存先は**ドキュメントルート外**。Web から到達できる場所へ置かない |
| 3 | バックアップファイルにも `private/` と同等の権限（ディレクトリ 700 / ファイル 600）を与える |
| 4 | **アクセスできる主体は代表と制作担当のみ**。店舗・外注・第三者に渡さない |
| 5 | バックアップの保持は**直近30日分**。それ以前は削除する（§9.5.4） |
| 6 | バックアップを外部クラウド（Drive を含む）へ置かない（Drive は写真専用・§1） |
| 7 | 取得方式は **`SQLite3::backup()`**（§9.6.1）。**DBファイルの単純コピーを通常手段にしない** |
| 8 | **取っただけで安心しない。** 復元できることを実際に確かめる（§9.5.5） |
| 9 | 保持削除を行ったら、**その前に作られた世代を優先して消す**（§9.5.6） |
| 10 | 入り口は**管理CLI**を第一手段とする（§9.5.7）。本番復元の経路は作らない |

**本番の配置候補**

```text
${DOMAIN_ROOT}/private/intake/backups
```

| # | 条件 |
|---|---|
| 1 | `public_html` の外 |
| 2 | Web から到達不可 |
| 3 | ディレクトリ **700** ／ ファイル **600** |
| 4 | **正確な絶対パスは 4H で XServer 実機を確認してから確定する** |
| 5 | 4G ではパスを設定値（`backup_dir`）として持ち、**ローカルの一時ディレクトリで規則を検証**した |

**保存先として受け付けない値（使う直前に毎回検査する）**

| 値 | 拒否理由 |
|---|---|
| 未設定・空文字 | `not_configured`（**fail closed**。設定が無ければ経路そのものを動かさない） |
| 相対パス | `relative` |
| `..` を含む | `traversal` |
| 構成要素に `public_html` / `public` / `htdocs` / `www` / `wwwroot` / `web` を含む | `public_area` |
| ホーム直下（`/home/xxx` `/root` `/Users/xxx` `C:/Users/xxx` `$HOME`） | `home_root` |
| ルート直下（`/` `/backups` `C:/backups`） | `too_shallow` |
| 実在しない | `missing` |
| ディレクトリ自体が symlink | `symlink` |
| realpath 解決後に上記のいずれかへ当たる | 解決後の理由 |

### 9.5.1 ファイル名（v1.11 で確定）

```text
intake-YYYYMMDD-HHMMSS-<random8>.sqlite
```

| # | 規則 |
|---|---|
| 1 | 拡張子は **`.sqlite` 固定**。この形に**完全一致**しないファイルは、世代として数えない・検証しない・削除しない |
| 2 | **店舗名・案件番号・メール・その他PII を含めない** |
| 3 | **token・秘密値を含めない** |
| 4 | **予測可能な連番だけにしない**（末尾に 4バイトの乱数を付ける） |
| 5 | 作成時刻は **UTC**。世代の新旧はこの名前から読む |

### 9.5.2 バックアップのメタデータ（v1.11 で確定）

| # | 規則 |
|---|---|
| 1 | **DB の中へ保存しない**（DB ごと失った場面で読めなければ意味がない） |
| 2 | JSON の sidecar を**1ファイルごとに必須にはしない** |
| 3 | 保存先に **非PII の一覧（`manifest.json`）** を1つ置く |
| 4 | 一覧に置いてよいのは **ファイル名 / 作成日時 / サイズ / SHA-256 / DBスキーマ版 / 回答スキーマ版**だけ |
| 5 | **DB の内容・案件番号・回答件数・店舗情報を含めない** |
| 6 | 一覧は allowlist で作り直す。**未知のキーを保存しない** |
| 7 | 一覧に控えの無いファイルは「**検証できない**」として扱い、検証を通さない（安全側） |

### 9.5.3 取得の手順（v1.11 で確定）

```text
排他ロック → 一時ファイルへ SQLite3::backup()
  → integrity_check / foreign_key_check
  → SHA-256 → ディスクへ同期 → 権限 600
  → 同一ディレクトリ内で atomic rename → 一覧へ控えを記録
```

| # | 規則 |
|---|---|
| 1 | 保存先は**設定の明示的な絶対パス**から取り、§9.5 の検査を**毎回**やり直す |
| 2 | symlink は拒否する（ディレクトリ・ファイルとも） |
| 3 | まず**一時ファイル**（`.tmp-*.part`）へ取得する |
| 4 | 取得後に `integrity_check` / `foreign_key_check`。期待値でなければ**正式扱いにせず削除**する |
| 5 | SHA-256 を計算して控える |
| 6 | 可能な範囲で fsync 相当を行う（PHP 8.1 未満では `fflush` まで） |
| 7 | **同一ディレクトリ内で atomic rename**（別ボリュームをまたがない） |
| 8 | 権限を **600** にする |
| 9 | **既存のバックアップを上書きしない。** 同名になるなら作らずに失敗させる |
| 10 | **排他ロック**を取る。作成・cleanup・復元確認・purge連動を同時に走らせない。<br>取れなければ待たずに固定の応答（`lock_busy`）で中止する |
| 11 | 失敗時は**一時ファイルを必ず削除**する。中途半端なファイルを世代として残さない |
| 12 | 回答本文をログ・監査・標準出力へ出さない。**保存先の絶対パス全文も出さない** |

### 9.5.4 世代・保持期間（v1.11 で確定）

| # | 規則 |
|---|---|
| 1 | 保持は**最大30日** |
| 2 | **1日1世代**を基本とする |
| 3 | 同日に複数作ってもよいが、**最大60世代** |
| 4 | 30日超・60世代超は**手動 cleanup の対象**とする |
| 5 | **自動 cron を作らない。ページ表示時の自動実行を禁止する** |
| 6 | 作成・cleanup は**管理者の明示操作または CLI** からのみ |

**cleanup の順序**

```text
1. 30日を過ぎた世代を削除する
2. 残りが60世代を超えていれば、古いものから超過分を削除する
3. 稼働DBは削除対象にしない
4. バックアップディレクトリの外は絶対に対象にしない
```

| # | 規則 |
|---|---|
| 1 | **dry-run が既定**。既定では1件も削除しない |
| 2 | 実削除には**明示フラグ**と**確認文字列の完全一致**の両方が要る |
| 3 | symlink をたどらない |
| 4 | 削除した世代からは**二度と復元できない**。実行前に対象一覧を確認する |

### 9.5.5 復元確認（restore drill・v1.11 で新設）

**本番DBへ書き戻す restore 機能を作らない。** 4G が持つのは drill だけである。

```text
1. 対象を許可ディレクトリ内から選ぶ
2. SHA-256 を検証する
3. 新しい一時DBへ復元（安全にコピー）する
4. SQLite として開く
5. PRAGMA integrity_check
6. PRAGMA foreign_key_check
7. PRAGMA user_version
8. 必須8表の存在
9. 回答スキーマ版
10. 代表的な架空案件の整合（案件番号・状態・作成日時の形式）
11. 元DBと件数などの**非PII指標**を比較する
12. 一時復元DBを削除する
```

| # | 禁止 |
|---|---|
| 1 | 稼働DBへの上書き |
| 2 | 本番 restore |
| 3 | 自動切替 |
| 4 | 元DBの削除 |
| 5 | サーバー再起動 |

> ★11 の件数が一致しなくても**異常ではない**。バックアップは「ある時点」の写しであり、
> その後の入力で件数が増えているのは正常である。**事実として報告するだけ**にする。
> ★**本番復元は 4H 以降の別承認工程**とする。

### 9.5.6 保持削除との連動（v1.11 で新設）

**案件を Intake から物理削除しても、削除前のバックアップが残っている間は
そこから PII を復元できてしまう。** したがって Phase 1 では、保持削除の実行時に
バックアップ側の処理まで行うことを**必須の運用**とする。

**正式手順**

```text
 1. retention_actions_enabled を確認する
 2. backup_policy_confirmed を確認する
 3. 対象案件・期限・標準管理票への移送を確認する
 4. purge 直前にバックアップ一覧を記録する
 5. 稼働DBから案件を purge する
 6. purge 後の新しいバックアップを作成する
 7. purge 後バックアップの restore drill を成功させる
 8. purge 前に作成された全バックアップを削除する
 9. バックアップディレクトリを再走査する
10. purge 前世代が 0 件であることを確認する
11. 完了を監査へ記録する
```

| # | 規則 |
|---|---|
| 1 | **purge 前バックアップを30日残さない。** 通常の30日保持より**優先して**削除する |
| 2 | **purge 後バックアップの検証前に、古い世代を消さない** |
| 3 | purge 後バックアップの**作成または検証に失敗した場合、古い世代を1件も消さない** |
| 4 | その場合、保持削除は「**バックアップ側未完了**」として運用上まだ完了していない |
| 5 | 再実行手順を runbook に明記する |
| 6 | 古いバックアップを削除すると**復元不能になる**ことを確認画面・CLI 出力に明記する |
| 7 | 同じ秒に作られた世代は「purge 前」に倒す（安全側の丸め） |
| 8 | この処理は**冪等**である。途中で止まっても、同じコマンドの再実行で続きから完了する |

**DB と filesystem を跨ぐことについて**

> ★アプリDBのトランザクションとファイル削除を、**「完全に1つの原子操作」と表現しない。**
> DB と filesystem を跨ぐため、完全な atomic にはならない。
> 代わりに、**段階・状態確認・冪等な再実行・失敗時 runbook** で安全性を担保する。
> 設計は「消しすぎ」ではなく「**消し残し**」へ倒す。消し残しは再実行で解消できるが、
> 消しすぎは戻せないためである。

| 中間状態 | 意味 | 復旧 |
|---|---|---|
| DB は purge 済み・purge 後世代が無い | バックアップ側未完了 | 作成 → drill → purge連動を実行する |
| DB は purge 済み・purge 後世代の検証が失敗 | バックアップ側未完了 | 作り直して drill を通してから purge連動 |
| purge 前世代が一部だけ消えた | 途中終了 | **同じコマンドを再実行する** |

> ★処理状態は**既存の監査 event / result_code** で記録する（§2.5）。
> **新しいDB表を追加しない。**

### 9.5.7 管理CLIと管理画面（v1.11 で確定）

Phase 1 は**管理CLIを正式な第一手段**とする。

| コマンド | 役割 |
|---|---|
| `backup:create` | 世代を1つ作る |
| `backup:list` | 世代の一覧 |
| `backup:verify` | SHA-256・整合性・版・8表を検証する |
| `backup:restore-drill` | 使い捨ての一時DBへ復元して確かめる |
| `backup:cleanup` | 30日超・60世代超を削除する |
| `backup:purge-preceding-generations` | 保持削除より前の世代を削除する |

| # | CLI の要件 |
|---|---|
| 1 | `public_html` の外へ置く |
| 2 | **Web 経由で実行できない**（CLI 以外の SAPI では即終了する） |
| 3 | **秘密値を引数に渡さない**（設定ファイルまたは環境変数から読む） |
| 4 | DB の内容を標準出力しない |
| 5 | 出すのは「成功/失敗」「非PIIの件数」「ファイル識別子」だけ |
| 6 | **絶対パス全文を報告へ貼らない**（`<backup_dir>` と表示する） |
| 7 | 削除系は **dry-run が既定** |
| 8 | 実削除には**明示フラグと確認文字列**が要る |
| 9 | 対象ディレクトリの外を拒否する |

**管理画面へ追加する場合の制限**

| # | 規則 |
|---|---|
| 1 | 追加してよいのは**作成・一覧・検証まで** |
| 2 | **ファイル削除・復元は CLI を優先**する |
| 3 | 管理 session ・CSRF ・Origin 検査を必須とする |
| 4 | パス全文を表示しない |
| 5 | **本番 restore ボタンを作らない** |

> ★4G では**管理画面へ追加しなかった**。Web から届く面を増やさない方が安全であり、
> Phase 1 の運用（代表1名・日次手動）は CLI で足りるためである。
> 追加が必要になった場合は、本書を改定してから作る。


### 9.6 SQLite の取り扱い（-journal / -wal / -shm）

| # | 規則 |
|---|---|
| 1 | journal mode は **`delete`（既定）を採用する**。WAL にしない |
| 2 | 理由: 共有レンタルサーバー環境では `-wal` / `-shm` の権限・削除漏れが事故になりやすく、<br>1〜5店舗の書き込み頻度では WAL の利点が無いため |
| 3 | バックアップ時に `-journal` が存在する場合は**コピーしない**。<br>先に接続を閉じ、`-journal` が消えた状態でコピーする |
| 4 | **`VACUUM INTO` は使用しない。** 本番の SQLite は **3.26.0** であり、<br>`VACUUM INTO` は **3.27.0 以降**の構文のため利用できない（§0.2・§2.0.1） |
| 5 | **稼働中の生ファイルコピーは行わない。** 取得方式は §9.6.1 に定める |

### 9.6.1 バックアップの取得方式（SQLite 3.26.0 対応）

| 優先 | 方式 | 根拠・注意 |
|---|---|---|
| **第一** | **`SQLite3::backup()`** | SQLite の **Online Backup API**（`sqlite3_backup_*`）を使う。<br>API は **SQLite 3.6.11 以降**で利用可＝**3.26.0 で動作する**。<br>PHP 側は **7.4.0 以降**で利用可＝本番 **8.3.33** で利用可。<br>**稼働中でも整合したコピーを1ファイルで取得できる**（`VACUUM INTO` の代替として等価） |
| 第二 | `BEGIN IMMEDIATE` で書込ロック取得 → ファイルコピー → `ROLLBACK` | ロック中にコピーする。§9.6-3（`-journal` があるときはコピーしない）は維持 |
| 最終 | 全行を読み出して **SQL ダンプ**を書き出す | 1〜5案件の規模では現実的。**SQLite の版数に依存しない**利点がある |

**実装上の注意（4B で実装）**

| # | 注意 |
|---|---|
| 1 | `SQLite3::backup()` は **SQLite3 クラス**の機能である。通常クエリは PDO を使うため、<br>**バックアップ時のみ別接続で SQLite3 を開く**（本番の `sqlite3` 拡張は true・§0.2） |
| 2 | 書込トランザクション中は `backup_step` が待たされる。**busy timeout を設定し、再試行**する |
| 3 | 取得先は **public_html の外**（§9.5-2） |
| 4 | 取得後に必ず §9.7 の整合性確認を行う |
| 5 | **`VACUUM INTO` を呼ぶ経路をコードに残さない**（§2.0.1 の起動時ガードで二重に防ぐ） |

### 9.7 バックアップの整合性確認

取得直後に**必ず**次を実行し、結果を記録する（値のみ・回答本文は出さない）。

```sql
PRAGMA integrity_check;    -- 期待値: ok
PRAGMA foreign_key_check;  -- 期待値: 0行
```

★どちらも **SQLite 3.26.0 で利用できる**（`foreign_key_check` は 3.7.16 で追加）。
　`VACUUM INTO` の撤回による影響はここには及ばない。

- どちらかが期待値でない場合、そのバックアップを**採用しない**。
  直前の正常なバックアップを保持したまま、原因を調査する。
- 確認結果（日付 / integrity_check / foreign_key_check の件数）を運用記録へ残す。

### 9.8 破壊的操作のフラグ（v1.7 で新設・4F-PRE で実装）

削除は**取り消せない**。設定が整う前に動いてしまう経路を作らないため、
2つのフラグが**両方とも真**のときにだけ削除を通す。

| 設定 | 既定 | 意味 |
|---|---|---|
| `retention_actions_enabled` | **false** | 保持期限による削除操作を有効にしてよいか |
| `backup_policy_confirmed` | **false** | 本番バックアップの**世代・削除方針が確定済み**か。<br>方針は v1.11（4G）で確定した。**true にしてよい条件は §9.9**（4H の実機確認まで false のまま） |

| # | 規則 |
|---|---|
| 1 | **既定は両方 false。** 設定していない環境では削除経路そのものを動かさない |
| 2 | 片方でも false なら、確認画面も実行も **403**（fail closed）。ボタンも出さない |
| 3 | 値は**明示的に真と書いたときだけ真**にする。環境変数は文字列で来るため、<br>`"false"` / `"0"` / `"off"` を `(bool)` で丸めない |
| 4 | 実値を Git へ入れない。`private/intake-config.php` または環境変数で与える |
| 5 | ローカル確認では override してよい（使い捨てDBのみ） |
| 6 | **`backup_policy_confirmed` を §9.9 の条件が揃う前に真にしない。**<br>古いバックアップが残っている間は、消したはずの回答が**そこから復元できてしまう**。<br>★方針は 4G で確定したが、**4G 終了時点では false のまま**である（§9.9-1） |
| 7 | 管理 session の清掃（§2.7-8）は破壊的でないため、このフラグを要求しない（認証は要る） |

### 9.9 `backup_policy_confirmed` を真にしてよい条件（v1.11 で新設・4G）

**4G の完了だけでは本番で true にしない。**

| # | true へできる条件（すべて満たすこと） |
|---|---|
| 1 | 4G のローカル実装と全テストが PASS していること |
| 2 | restore drill が成功していること |
| 3 | 保持削除との連動手順が成功していること |
| 4 | **4H で本番の絶対パスを確認**していること |
| 5 | そのパスが **`public_html` の外**であることを実機で確認していること |
| 6 | ディレクトリ 700 / ファイル 600 を実機で確認していること |
| 7 | **XServer 上で `SQLite3::backup()` が動くことを実測**していること |
| 8 | XServer 上で 作成・検証・削除を実測していること |
| 9 | 代表が runbook を確認していること |

| # | 規則 |
|---|---|
| 1 | **4G 終了時点では `backup_policy_confirmed` は false のまま**である |
| 2 | 4H の限定実測のあと、**別の本番実施承認**で true 化を判断する |
| 3 | ローカル確認では override してよい（使い捨てDBのみ・§9.8-5） |
| 4 | 手順書: `docs/website/HP_INTAKE_BACKUP_RESTORE_RUNBOOK_V1.md` |

### 9.10 preflight 専用環境（v1.12 で新設・4H-R0）

**本番配置後の通し確認を、正式DBで行わない。**

4H-PRE で次の問題が分かった。確認用の架空案件を正式DBへ入れると、
`retention_actions_enabled` と `backup_policy_confirmed` が false のため
**purge で消せない**。かといって確認のために正式DBを作り直す運用にすると、
「正式DBはいつでも消してよい」という誤った習慣が残る。

そこで、確認は**専用の領域**で行う。

```text
APP_ROOT/preflight/
├── intake-config.php
├── intake.sqlite
├── logs/
├── ratelimit/
└── backups/
```

| # | 規則 |
|---|---|
| 1 | `public_html` の外に置く |
| 2 | **正式DB・正式バックアップと完全に分ける**（別実体） |
| 3 | 架空データのみ。メールは `example.invalid` のみ |
| 4 | **実メールを送らない**（通知は Null / Fake。§9.11） |
| 5 | `retention_actions_enabled` / `backup_policy_confirmed` は **false** |
| 6 | 公開入口を一般公開する**前**に実施する |
| 7 | preflight 実行中は**実顧客の token を発行しない** |
| 8 | 本番設定へ切り替える前に preflight の完了を確認する |
| 9 | **preflight 領域を削除してから、正式DBを新規作成する**（順序を入れ替えない） |
| 10 | 正式な空DBバックアップは、**正式DB作成後**に取得する |
| 11 | preflight のバックアップ世代を**正式 backups へ移さない** |
| 12 | preflight の manifest を**正式側へ移さない** |
| 13 | **正式DBをテスト目的で削除・作り直す運用にしない** |

**削除の安全条件**

| # | 規則 |
|---|---|
| 1 | 削除対象を**明示パスで事前確認**する（件数と相対名のみ表示） |
| 2 | 削除前に**正式領域でないこと**を二重に確かめる<br>（正式 private_root と同一・内側・外側から包む位置・稼働DBを含むものはすべて拒否） |
| 3 | 設定の時点でも、正式領域と重なる `preflight_root` を**受け付けない**（fail closed） |
| 4 | symlink を拒否する（たどらず、リンク自体だけを外す） |
| 5 | **preflight 領域の外を再帰削除しない** |
| 6 | **dry-run が既定**。実削除には明示フラグと確認文字列の完全一致が要る |
| 7 | 削除後に**残存0**を確認する |

### 9.11 提出通知メール（v1.12 で新設・4H-R0）

§0.2 と §12.1-7 で「Phase 1 の通知は XServer の `mail()`」と確定していたが、
4H-PRE の調査で**実装が1件も無い**ことが分かった。v1.12 で実装方針を確定する。

**送信の契機**

| # | 規則 |
|---|---|
| 1 | **最終提出が初めて成功し、トランザクションが commit された後だけ**送る |
| 2 | `validation_error`（不足あり）では送らない |
| 3 | **同一 `submission_id` の再送では送らない**（冪等。§6.4） |
| 4 | `already_submitted` では送らない |
| 5 | 保存・Drive 申告・管理者の状態変更では送らない |
| 6 | 宛先と差出人が**両方そろったときだけ**送る。片方でも欠ければ送らない（fail closed） |

**本文に含めてよいもの（allowlist・3項目）**

| # | 項目 |
|---|---|
| 1 | 案件番号 |
| 2 | イベント種別（`submitted`） |
| 3 | 発生日時（**UTC**。時間帯を本文へ明記する） |

**含めてはいけないもの**

回答本文／店舗名／氏名／メールアドレス／電話番号／住所／
token／token hash／session／**`submission_id`**／Drive URL／共有先メール／
修正依頼本文／生IP／HMAC化IP／DBの内部ID／秘密値／書き出し JSON 本文。

**宛先・差出人**

| # | 規則 |
|---|---|
| 1 | 設定キーは `notification_recipient` と `notification_from` |
| 2 | Git 上の雛形は **`example.invalid`**。実値は 4H で代表が設定する |
| 3 | **1宛先のみ。** 複数宛先を受け付けない |
| 4 | 改行（CR/LF）・制御文字・空白・カンマ・セミコロン・山括弧・引用符・<br>バックスラッシュ・丸括弧・角括弧・コロンを含む値を**拒否**する（ヘッダー注入対策） |
| 5 | 差出人は XServer で正式に使える**自社ドメイン**のアドレスを 4H で設定する |
| 6 | `Reply-To` へ店舗情報を入れない（そもそも設定しない） |

**実装**

| # | 規則 |
|---|---|
| 1 | 業務コードから `mail()` を直接呼ばない。`Notifier` インターフェースで隔離する |
| 2 | `mail()` を使ってよいのは **`ProductionMailNotifier` の1ファイルだけ**。<br>静的検査が、それ以外での `mail()` 使用を失敗させる |
| 3 | テストは `FakeNotifier` を使い、**実メールを1通も送らない** |
| 4 | ヘッダーは**固定の allowlist**（`From` / `Content-Type` / `Content-Transfer-Encoding` /<br>`X-Auto-Response-Suppress` / `Auto-Submitted`）。呼び出し側から追加できない |
| 5 | 件名も**固定形式**。MIME encoded-word にして改行が混ざらないようにする |
| 6 | 本文は **text/plain・UTF-8**。HTML メールにしない |
| 7 | `mail()` の追加パラメータ（第5引数）を使わない |
| 8 | 本文・宛先・件名を**ログにも監査にも書かない** |

**失敗したとき**

| # | 規則 |
|---|---|
| 1 | **提出そのものは成功のまま維持する** |
| 2 | トランザクションを巻き戻さない |
| 3 | 店舗へメール失敗を返さない（応答の形を変えない） |
| 4 | 固定の `result_code` で監査する（§2.5） |
| 5 | 秘密値・本文・宛先を監査へ書かない |
| 6 | **自動再送しない。** 再送機能が必要なら別工程で設計する |
| 7 | 未通知の把握は、管理画面の**非PIIの最小限の表示**で検討する（v1.12 では未実装） |

---

## 10. セキュリティ

### 10.1 データアクセス

| 項目 | 規則 |
|---|---|
| SQL | **PDO の prepared statement のみ。** 値をSQL文字列へ連結しない |
| 書き込み列 | ホワイトリスト方式。定義外のキーはSQLへ渡さない |
| 外部キー | `PRAGMA foreign_keys = ON` |
| DBファイル | ドキュメントルート外・パーミッション 600 |

### 10.2 リクエスト検証

| 項目 | 規則 |
|---|---|
| CSRF | 同一オリジン＋**HMAC署名トークン**（contact-api の方式を移植）。管理画面は必須 |
| Origin / Referer | 許可オリジンは `https://intake.smartlaboworks.com` **のみ**。一致しなければ拒否 |
| honeypot | 人に見えない項目を1つ置き、入力があれば静かに拒否 |
| rate limit | IP単位。**token 検証失敗は別枠でより厳しく**。自動保存は別枠の上限を設ける |
| IP の保存 | **HMAC-SHA256(IP, ip_hash_secret) の先頭32文字のみ。** 生IPを保存しない |
| 最大 body size | JSON 1リクエストあたりの上限を設ける（運用値は §12-9 と併せて 4B で確定） |
| JSON 配列上限 | §3 の各上限を**サーバー側で検証**。超過は保存拒否（切り捨てない） |
| UTF-8 検証 | 不正なバイト列を含む要求は拒否する |
| 制御文字 | TAB / LF / CR 以外の C0/C1 を除去 |

### 10.3 出力

| 項目 | 規則 |
|---|---|
| XSS | 出力時 `htmlspecialchars(ENT_QUOTES\|ENT_SUBSTITUTE,'UTF-8')` を**必須**。生HTMLとして解釈しない |
| URL 属性 | `https://` で始まることを検査してから属性値へ。`javascript:` / `data:` / `vbscript:` を拒否 |
| 改行 | ESC 後に `<br>` へ変換（ESC+NL2BR）。ESC前に変換しない |
| 生成データ | 「内部」項目を**生成器へ渡すデータから除外**（§3.0-7・§3.1 の注記） |

### 10.4 HTTP ヘッダー

```text
Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self';
                         img-src 'self'; font-src 'self'; connect-src 'self';
                         form-action 'self'; frame-ancestors 'none'; base-uri 'none';
                         object-src 'none'
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Referrer-Policy: no-referrer
Cache-Control: no-store, no-cache, must-revalidate
Pragma: no-cache
Strict-Transport-Security: max-age=31536000
```

**CSP の規則（v1.7 で確定・4F-PRE / 4E の P3-2）**

| # | 規則 |
|---|---|
| 1 | **API（PHP）側と静的ファイル（`.htaccess`）側で、CSP を完全に同一の文字列にする**。<br>画面と API で守りが違うと、どちらが本当の方針なのか読めなくなる |
| 2 | **`object-src 'none'`** を明示する（プラグイン埋め込みの経路を作らない） |
| 3 | **`font-src 'self'`** を明示する（`default-src` で足りるが、外部フォント禁止の意思を文字にする） |
| 4 | **`img-src` に `data:` を許さない**（v1.7 で変更）。この画面群は**画像もアイコンも使わない**。<br>使う必要が生じたら、本書を改定してから許可する |
| 5 | `unsafe-inline` / `unsafe-eval` / ワイルドカードを**使わない** |
| 6 | インラインスクリプト・インラインスタイルを書かない（4C で `style="..."` を排除済み） |

- **HTTPS を強制**する（HTTP は 301 で HTTPS へ）。HTTPS 判定は `$_SERVER` の実測値による（§0.2）。
- **CORS ヘッダーを出さない**（他オリジンから使えない構成にする）。
- Cookie は **Secure / HttpOnly / SameSite=Strict**（§2.6・§4.7）。

**公開領域の誤配置対策（v1.7 で確定・4E の P3-3）**

`public/.htaccess` で次を拒否する。**公開領域に置かないことが第一**であり、これはその保険である。

| # | 拒否対象 |
|---|---|
| 1 | ドットで始まるファイル・ディレクトリ（`.git` / `.env` / `.svn` / `.htaccess` / `.user.ini` など） |
| 2 | ★ただし **`.well-known` だけは除外**する。塞ぐと **SSL 証明書の自動更新（ACME）が止まる** |
| 3 | DB・ログ・退避（`.sqlite` / `.db` / `.sql` / `.log` / `.bak` / `.backup` / `.old` / `.orig` / `.save` / `.swp` / 末尾 `~`） |
| 4 | 秘密鍵・証明書（`.pem` / `.key` / `.crt` / `.cer` / `.der` / `.csr` / `.p12` / `.pfx` / `.jks` / `.asc` / `.gpg` / `.ppk`） |
| 5 | 設定（`.ini` / `.conf` / `.cnf` / `.yml` / `.yaml` / `.toml` / `.env` / `intake-config.php` / `*.example.php`） |
| 6 | Composer 等（`composer.json` / `composer.lock` / `composer.phar` / `package.json` / `Dockerfile` / `Makefile`）<br>★Composer は導入していない。公開領域に出ること自体が誤配置である |
| 7 | ディレクトリ一覧（`Options -Indexes`） |

> ★拒否規則は**フロントコントローラへの rewrite より前**に置く。後ろだと `index.php` へ吸われる。
> ★`mod_rewrite` と `FilesMatch` の**二重**で塞ぐ（片方が無効化された環境でも残す）。

### 10.4.1 PHP 設定（本番配置の必須要件）

**intake サブドメインの本番配置時に、次を必ず設定する。**

```text
display_errors         = Off
display_startup_errors = Off
log_errors             = On
error_log              = <public_html の外のパス>
expose_php             = Off
```

| # | 規則 |
|---|---|
| 1 | **`display_errors = Off`。** ON のままだと、致命的エラーが**レスポンス本文へ出力**され、<br>絶対パス（ホスティングのアカウント名を含む）・SQL・設定値が露出する。<br>経路によっては **token / session secret がトレースに載りうる**（§10.6 と矛盾する） |
| 2 | **`display_startup_errors = Off`** も維持する |
| 3 | **`log_errors = On`** とし、`error_log` は **public_html の外**へ置く（Webから到達させない） |
| 4 | 設定経路は **`.user.ini`**（既存 form サブドメインで運用実績あり）または<br>XServer サーバーパネルの php.ini 設定 |
| 5 | `.user.ini` は即時反映されない（`user_ini.cache_ttl` 既定300秒）。**反映を待って確認する** |
| 6 | **4H の配置チェックリストに含め、確認できるまで公開しない**（§11.1） |
| 7 | **`expose_php = Off`**（v1.7 で追加・4E の P3-1）。既定 On のままだと<br>`X-Powered-By: PHP/8.3.xx` が出て、**版まで含めて外部へ知らせる**。<br>攻撃者に版固有の既知不具合を選ばせない |
| 8 | ★`expose_php` は **`PHP_INI_SYSTEM`** である。共用サーバーでは `.user.ini` から**効かないことがある**。<br>**XServer 実機での効き確認は 4H で行う**（4F-PRE では設定の追加までとする） |
| 9 | 効かない場合に備え、`.htaccess` に **`Header always unset X-Powered-By`** を併記する（二重の備え） |

> ★参考記録: `smartlaboworks.com` 側は **2026-08-27 に代表が
> `display_errors` を ON → OFF へ変更済み**（`display_startup_errors` は OFF を維持）。
> intake サブドメインは**別途、同じ設定を行う必要がある**。

### 10.5 外部リソースの禁止

- intake の画面から**外部ドメインへ一切リクエストしない**。
  外部フォント・外部CDN・外部画像・アクセス解析・第三者スクリプトを読み込まない。
- 理由: Referer・DNS・計測を通じて **token を含むURLが外部へ渡る**経路を根絶するため。
- CSP の `default-src 'self'` でこれを機械的に強制する。

### 10.6 エラー表示

- 外部（店舗）へは**固定文言のみ**。例外メッセージ・スタックトレース・
  SQL・ファイルパス・設定値を出さない。
- **`display_errors = Off` を前提とする**（§10.4.1）。
  アプリの try/catch では捕捉できない致命的エラー（parse error・メモリ超過等）が
  露出するのを、PHP 設定の側で塞ぐ。**アプリ側の対策だけに依存しない。**
- token 関連は §4.6 の同一文言・404 に統一する。
- 詳細はサーバーのエラーログへ**識別子のみ**を残す（本文・PIIを出さない）。
  エラーログ自体も **public_html の外**へ置く（§10.4.1-3）。

### 10.7 ログに出してはいけない情報

| 出さない | 出してよい |
|---|---|
| token 平文／**session secret 平文** | case_number |
| 回答本文（全JSON） | event_type |
| 氏名・掲載名・実名 | result_code |
| メールアドレス（公開用・内部用とも） | 発生日時 |
| 電話番号 | HMAC化IP |
| 住所 | schema_version |
| Drive フォルダURL | 件数（充足数・不足数） |
| 同意記録の内容 | HTTPステータス |
| **`submission_id`（v1.3）** | レート制限のバケット名 |

- ログ出力の共通関数で **base64url 43文字の連続**（token / session secret）と
  既知の秘密値パターンを `[REDACTED]` へ置換する
  （マスクは出力の直前に一箇所で行い、実装ごとに散らさない）。
- ログに出してよいキーは**許可制（allowlist）**とする。許可一覧に無いキーは、
  呼び出し側が渡しても**書き出す前に捨てる**。`submission_id` を許可一覧へ入れない
  （「マスクし忘れ」ではなく「そもそも通らない」構造にするため）。
- 通知メールにも**案件番号のみ**を書く。本文・個人情報・Drive URL を入れない。

### 10.8 管理画面（認証方式は v1.4 で確定）

- **認証必須。** 未認証で到達できる管理画面を作らない。
- 管理画面にも CSRF・レート制限・監査ログ（`admin_viewed` / `export_generated`）を適用する。
- 管理画面は店舗向けURLとパスを分ける（`/admin/` 配下）。

**資格情報（Phase 1 は代表1名）**

| # | 規則 |
|---|---|
| 1 | 管理者IDは `private/intake-config.php` に置く（**ドキュメントルート外・Git管理外**） |
| 2 | パスワードは**平文で保存しない**。`password_hash()` が作った **hash だけ**を置く |
| 3 | **Argon2id を優先**し、利用できない環境でのみ bcrypt を使う |
| 4 | 実パスワード・実 hash を**Gitへ入れない**。雛形には**明らかなダミー**だけを書く |
| 5 | **未設定なら管理画面を動かさない**（fail closed）。初期パスワードをコードへ持たない |
| 6 | 照合は `password_verify()`。**IDの存在有無で応答を変えない**（文言も時間も） |
| 7 | 外部の認証サービスへ接続しない。AI Sales / Operations の認証を流用しない |

**session（詳細は §2.7）**

- 店舗の session（§2.6）を管理画面へ流用しない。逆も行わない。
- **idle 30分／絶対 8時間**。ログイン成功時に**必ず新しい session を作る**（fixation 対策）。
- Cookie は Secure / HttpOnly / SameSite=Strict。名前に `admin`・店舗名・案件番号を含めない。

**ログイン防御**

| # | 規則 |
|---|---|
| 1 | **HMAC化IP単位で 10分5回**（生IPを保存しない。§10.2 と同じ方式） |
| 2 | 失敗時は**常に同一文言**。IDが存在するかどうかを推測させない |
| 3 | ID不一致でも `password_verify()` 相当の時間を使い、**応答時間差を抑える** |
| 4 | 監査へ `admin_login` を残す。**入力されたIDもパスワードも記録しない** |
| 5 | 入力欄は `autocomplete="username"` / `"current-password"` を指定する |

**CSRF（状態を変える操作すべて）**

| # | 規則 |
|---|---|
| 1 | `random_bytes(32)` を **server 側の session（§2.7）へ hash で保持**する |
| 2 | 画面の hidden 項目として渡し、`hash_equals()` で照合する |
| 3 | **URL（query）へ出さない。ログへ出さない** |
| 4 | Origin の厳格検査も**併用**する（どちらか一方に頼らない） |
| 5 | **GET で状態を変えない。** ログアウトも POST |
| 6 | form 送信の `Origin` が `null` になる件は、下の枠のとおり **Fetch Metadata で補う** |

**form 送信の `Origin: null`（v1.4・実測にもとづく）**

ブラウザは、**画面遷移としての form 送信**（`Sec-Fetch-Mode: navigate`）で
`Origin: null` を送る。Referrer-Policy を変えても直らない（4D で Chrome にて実測）。
そのため、JavaScript を使わない管理画面では Origin だけで同一オリジンを判定できない。

| # | 決めたこと |
|---|---|
| 1 | Origin が付いていて `null` でなければ → **許可一覧と厳格に照合**する（従来どおり） |
| 2 | Origin が無い／`null` のときだけ → **`Sec-Fetch-Site: same-origin`** を見る |
| 3 | `Sec-Fetch-*` は **Forbidden request header** であり、**ブラウザ内の JavaScript からは設定できない**。<br>他サイトからの送信では `cross-site` になるため、**ブラウザ経由の CSRF に対する補助信号**として使う |
| 4 | `cross-site` / `none` / ヘッダー欠落は**すべて拒否**する（fail closed） |
| 5 | この判定は**多層防御の1枚にすぎない**。**CSRF token は常に必須**（規則1〜3） |
| 6 | **店舗向けの JSON POST は変更しない。** `fetch()` からの呼び出しなので<br>正しい Origin が必ず付き、従来どおり Origin の厳格検査だけで守る |
| 7 | 管理画面の Cookie は `SameSite=Strict`。他サイト起点の送信には**そもそも付かない** |

> ★**「偽装不可能」とは書かない。** 正確には次のとおりである。
> - ブラウザ内の JavaScript からは設定できない（Forbidden request header）
> - **curl 等の非ブラウザからは任意に構成できる**
>
> したがって `Sec-Fetch-Site` 単独では守りにならない。
> **CSRF token（server 側 session に hash で保持）・管理 session・Origin 検査・SameSite=Strict**
> と組み合わせた多層防御を維持すること。
> `Sec-Fetch-Site` 非対応のブラウザは §10.9 のとおりサポート対象外とする。

### 10.9 対応ブラウザ（v1.4 で確定）

| # | 規則 |
|---|---|
| 1 | 対応対象は**現行の Chrome / Edge / Firefox / Safari** |
| 2 | `Sec-Fetch-Site` に対応しない古いブラウザは**サポート対象外**とする |
| 3 | 画面は `Referrer-Policy: no-referrer`（§10.4）なので、**`Referer` は同一オリジンでも送られない**。<br>「Sec-Fetch-Site 非対応でも Referer 経由で通る」とは**限らない**。この前提に依存しない |
| 4 | `GET /case` が `Sec-Fetch-Site: same-origin` を受け付ける件は、**4E で改めて検証**する |
| 5 | **POST の Origin 厳格検査は変更しない**。対応ブラウザの整理を理由に緩めない |

> ★同一オリジンの GET には Origin が付かない（ブラウザの仕様）。
> 規則3 と合わせると、GET だけは Fetch Metadata に依存せざるを得ない。
> **これは 4C で判明した実測事実であり、設計上の選択である。**

---

### 10.10 操作対象の大きさ（v1.8 で確定・4F-R1 で実装）

4F の実測で、主導線（前へ／次へ）は 48px を満たしていたが、
補助ボタン（追加・保存する・入力を終了する）が 40px だった。
店舗の担当者はスマートフォンで入力することが多く、
**押しにくさは入力の中断に直結する**。基準を1つに決める。

| # | 規則 |
|---|---|
| 1 | 主要・補助を問わず、利用者が押すボタンの**最小操作高さは 48px** |
| 2 | チェックボックス・ラジオは、**`label` を含む操作領域**で 48px を確保する |
| 3 | 操作用リンク（ボタンとして置くリンク）も 48px 相当を確保する |
| 4 | **文章中の通常リンクは対象外**。不自然に大きくしない |
| 5 | `height` で固定しない。**`min-height` ＋ padding** で確保する（文字を拡大しても切れない） |
| 6 | 複数行の文言を許容する（1行に収まらない文言を削って短くしない） |
| 7 | スマートフォン幅（320px）でも横へはみ出さない |
| 8 | 操作どうしの間隔を保ち、隣を誤って押しにくくする |
| 9 | `:focus-visible` を維持する（キーボード操作で位置が分かる） |
| 10 | 無効状態は**色だけで示さない**。`disabled` 属性と文言でも伝える |
| 11 | **管理画面も同じ基準**で点検する（店舗画面だけの規則にしない） |

### 10.11 配置境界（v1.12 で新設・4H-R0）

**docroot と APP_ROOT が兄弟である必要をなくす。**

4H-PRE で次の問題が分かった。それまでの実装は
`public/index.php` が `__DIR__/../src` を読み、設定も `src/../private` から読んでいた。
つまり **docroot の親が APP_ROOT** であることを強制していた。
XServer のサブドメインは `smartlaboworks.com/public_html/<sub>/` の下に作られるため、
この形だと `src/` と `private/` を **public_html の中**へ置くことになる。

> ★XServer の「サブドメイン設定」と「独自ドメイン設定」は別機能である。
> `intake.smartlaboworks.com` を独立ドメインとして追加できる保証は確認できていない。
> 既存の `form.smartlaboworks.com` も `smartlaboworks.com/public_html` 配下にある。
> **特殊な配置に賭けず、コード側を対応させる。**

**正式な配置**

```text
smartlaboworks.com/
├── public_html/
│   └── intake.smartlaboworks.com/   ← docroot（公開）
└── private/
    └── hp-intake/                    ← APP_ROOT（公開しない）
        ├── src/
        ├── bin/
        ├── private/
        └── preflight/
```

**APP_ROOT の与え方**

| 優先 | 方式 | 説明 |
|---|---|---|
| 第一（実装上の順序） | `.user.ini` の `auto_prepend_file` が読む**非公開 bootstrap** が定数 `INTAKE_APP_ROOT` を定義する | docroot には何も置かない。雛形は `private/app-root-bootstrap.example.php`。<br>★**XServer 本番では採用しない**（下の枠を参照） |
| 第二 | 環境変数 `INTAKE_APP_ROOT` | CLI・ローカル確認 |
| 第三 | `docroot/../src`（リポジトリのままの配置） | ローカル確認・簡易配置 |
| **本番採用** | docroot の祖先の `private/hp-intake/src` を探す | **XServer 本番はこの経路で解決する**（v1.13・4H-3 で実機確定）。<br>**APP_ROOT の解決に固定の絶対パスを使わず**、相対の約束だけで成立する。公開側に実パスを書かない |

> ★**XServer 本番の正式採用は「docroot の祖先探索」である**（HP-ONBOARDING-4H-3 で確定）。
> 実装の探索順（定数 → 環境変数 → リポジトリ配置 → 祖先探索）は**変更しない**。
> 定数・環境変数・リポジトリ配置の各経路は、CLI・ローカル確認・他環境のために残す。
> 雛形 `private/app-root-bootstrap.example.php` も削除しない。
> どの経路でも、下の検査は同じものが働く。
>
> ★これは「**XServer では `auto_prepend_file` が使えない**」という意味ではない。
> 4H-3 の実機作業では、`auto_prepend_file` へ設定した**絶対パスでファイルを開けず**、
> ログに `Failed opening required` が記録された。**正しいパスであれば動く可能性は否定しない**。
>
> ★**それでも本番では採用しない。** `auto_prepend_file` は **require 相当**で実行され、
> prepend を開けなかった時点で**主スクリプトが実行されない**。
> 実測では当該サブドメインの全 PHP 応答が **HTTP 500・本文 0 バイト**になった
> （`display_errors = Off` のため外部へは何も出ない）。
> 設定値の誤りが**単一障害点**になるため、本番運用からこの経路を外す。
>
> ★祖先探索を選ぶ理由は、**実機で成立済みであること**と、
> **APP_ROOT の解決に固定の絶対パスを使わないこと**の2点である。
> なお、`error_log` など **APP_ROOT 解決以外の設定では絶対パスを使っている**。

**規則**

| # | 規則 |
|---|---|
| 1 | **docroot の中に APP_ROOT を書いたファイルを置かない**（`.app-root.php` 方式は不採用） |
| 2 | **URL・query・POST・Cookie・ヘッダーから APP_ROOT を受け取らない。**<br>解決を担うコードはスーパーグローバルを読まない |
| 3 | **Web リクエストから APP_ROOT を変更できない** |
| 4 | docroot 内に秘密値を置かない |
| 5 | public 側のコードへ**絶対パスを直接書かない** |
| 6 | 未設定・不正・不在・`public_html` 内・相対・`..` 入り・symlink 逸脱は<br>**fail closed**（例外。既定へ黙って落ちない） |
| 7 | 絶対パスを**エラー・ログ・HTML・JSON へ出さない** |
| 8 | Windows のローカルと Linux の本番の**両方で検証できる**ようにする |

**Config が分けて持つもの**

| キー | 内容 | 既定 |
|---|---|---|
| `APP_ROOT` | アプリ本体の親 | 解決結果（設定不可） |
| `src_root` | `APP_ROOT/src` | 固定 |
| `private_root` | 設定・DB・ログ・rate limit の親 | `APP_ROOT/private` |
| `db_path` | SQLite の実体 | `private_root/intake.sqlite` |
| `log_path` | アプリログ | 未設定なら出力しない |
| `rate_limit_dir` | レート制限の記録 | `private_root/ratelimit` |
| `backup_dir` | バックアップ置き場（§9.5） | 未設定なら経路を動かさない |
| `preflight_root` | 通し確認専用（§9.10） | 未設定なら経路を動かさない |

> ★実設定ファイルは **`APP_ROOT/private/intake-config.php`** から読む。
> `private_root` は**データの置き場所**だけを移すものであり、
> 設定ファイル自身の位置は動かせない（読む前に決まらないため）。
> ★**設定値の実パスを Git へ入れない。**

**誤配置防御**

| # | 規則 |
|---|---|
| 1 | `src/` と `bin/` は本番では **public_html の外**へ置く |
| 2 | それぞれに `Require all denied` の `.htaccess` を置く（**誤配置時の二重防御**） |
| 3 | 公開領域から `src/` `bin/` へ HTTP で到達できない |
| 4 | `bin/` の CLI は **`PHP_SAPI` が `cli` のときだけ**動く |
| 5 | `.htaccess` は Apache 2.4 の書き方。**実コード・秘密値を含めない** |
| 6 | 公開領域に置く PHP は **`index.php` の1つだけ** |

---

## 11. Phase 1 の境界

### 11.1 実装する

| # | 機能 |
|---|---|
| 1 | 案件作成（case_number 採番・contract_type・初期状態） |
| 2 | token 発行・再発行・失効 |
| 3 | 店舗入力（§3 の全カテゴリ） |
| 4 | 途中保存（自動・手動／楽観ロック） |
| 5 | 提出（必須検証・二重送信防止） |
| 6 | 不足修正（`needs_revision` → 再提出） |
| 7 | Smart Labo 確認（管理画面・充足判定の表示） |
| 8 | データ書き出し（仕様書 §17 の店舗データ構造。**内部項目は別セクション**） |
| 9 | Drive 受領確認（`drive_upload_confirmed_at` の記録・枚数種類の確認） |
| 10 | **提出通知メール**（v1.12 で追加・4H-R0。案件番号・イベント種別・発生日時の3項目のみ。§9.11） |

### 11.2 実装しない

| # | 機能 | 理由 |
|---|---|---|
| 1 | Smart Labo Operations との自動同期 | Operations は未実装。取込は **OPS-4** で扱う（検証済みの書き出しデータの取込から開始） |
| 2 | Stripe API | §8。intake は Stripe と通信しない |
| 3 | webhook | 同上 |
| 4 | 自動請求 | 価格SSOT §12-6（未確定・未実装） |
| 5 | 自動入金反映 | 同上 |
| 6 | HP 自動公開 | 公開は人が承認する（仕様書 §18.3） |
| 7 | **画像アップロード** | 写真本体は Drive（正式判断8）。`$_FILES` を扱わない |
| 8 | LINE 連携 | 範囲外 |
| 9 | Google Drive API | 正式判断・§7.1-11（人の操作で行う） |
| 10 | 店舗による公開承認 | 公開承認は **Smart Labo Operations 側**（§5.4） |
| 11 | 複数 Smart Labo 管理者の権限管理 | Phase 1 は**代表1名**（§10.8）。ロールを作らない |

### 11.3 検証済み書き出し（v1.4 で確定・4D で実装／v1.8 で allowlist を徹底）

Operations（OPS-4）が**最初に取り込むのはこの形**である。API 接続はしない。
管理画面から**案件単位でダウンロード**する JSON ファイルだけを受け渡しに使う。

**含める（allowlist。ここに無いものは出さない）**

| キー | 内容 |
|---|---|
| `export_schema_version` | 書き出し形式の版（本表の版。**回答スキーマ版とは別**） |
| `source` | 固定文字列 `hp_intake` |
| `generated_at` | 書き出した日時 |
| `case_number` / `contract_type` / `status` | 案件の識別と状態 |
| `submitted_at` / `locked_at` / `closed_at` | **既存の正式な時刻列のみ**（§2.1） |
| `reviewed_at` | **列は無い。** `intake_submission_history` の `reviewed` 行の時刻から導く（下の枠） |
| `drive_upload_confirmed_at` | 素材の受領申告（§11.1-9） |
| `retention_delete_due` | 保持期限（§9.1） |
| `answer_schema_version` | 回答スキーマ版 |
| `answers` | **JSON 11分類**（§3。公開・内部の区別は取込側で行う）。<br>★v1.8: 中身も **§3 の129パスへ絞ってから**出す（§3.0.1） |
| `rights` | 権利・同意（`answers.rights` と同じ内容を、証跡として明示的に置く） |
| `submission_summary` | 提出履歴の**件数と最終結果だけ**（`submitted_at` / `result_code` / `field_count` / `missing_count`） |
| `revision_requests` | 差し戻しの経緯（v1.5）。**`request_number` / `requested_paths` / `status` /<br>`created_at` / `resolved_at` のみ。★`message` 本文と `id` を含めない** |

**書き出し直前の絞り込み（v1.8 で追加・4F-R1）**

| # | 規則 |
|---|---|
| 1 | DB の回答 JSON を**無条件に書き出さない**。読み出しでも書き出しでも、**二重に**絞る |
| 2 | 正式11分類だけを選ぶ。未知の分類は出さない |
| 3 | 正式129パスだけを選ぶ。未知キーは出さない |
| 4 | 値の**型**を確かめる（§3.0.1 の表）。合わないものは出さない |
| 5 | §8 の保存禁止情報は従来どおり除外する |
| 6 | 既存DBに未知キーがあることだけで、**管理画面や書き出しを 500 にしない**。<br>正式値だけで安全に出せるなら出す |
| 7 | 必須が不正・欠落なら、**従来どおり書き出しを拒否**する（§11.3-5。絞り込みで甘くしない） |
| 8 | **Smart Labo 設定（§3.12）が揃っていること**も検査する（v1.9）。<br>不足なら書き出しを拒否し、**不足パスだけ**を管理者へ示す。**店舗へは返さない**。<br>v1.10: **キーの存在だけでなく中身**（§3.12 の内容条件）も見る |
| 9 | **失敗した書き出しは痕跡を残さない**（v1.10）。JSON 本文・`X-Intake-Export-Sha256`・<br>`Content-Disposition`・`export_generated` 監査・一時ファイルの**いずれも作らない**。<br>「書き出した」という記録は、**実際に外へ出したときだけ**残す |

> ★保存側は「要求全体を拒否」、書き出し側は「出力しない」。
> **方向が違う**ので同じ処理にまとめない。片方が緩んでも、もう片方が止める。

**`revision_requests.message` を書き出しへ含めない理由（v1.5）**

| # | 判断 |
|---|---|
| 1 | 取込側（OPS-4）が必要とするのは「**何回・どの項目を・いつ差し戻したか**」であり、<br>店舗向けの文面そのものではない |
| 2 | `message` は自由記述であり、**担当者が書いた連絡文**が入る。<br>制作管理データとして持ち回る必要が無い |
| 3 | 含めないほうが、書き出しファイルの取り扱い（保管・受け渡し）が軽くなる |

> ★将来 OPS-4 が本文を必要とした場合は、**本書を改定してから**追加する。
> 追加するときも、ログへ出さない規則（§2.8-4）は変えない。

**含めない（禁止。1つでも出たら不具合とする）**

token 平文 ／ `token_hash` ／ session secret ／ `session_hash` ／ CSRF token ／
管理 session ／ 生IP ／ `ip_hmac` ／ Cookie ／ 暗号鍵 ／ password hash ／
レート制限の記録 ／ **Drive URL**（暗号文・平文とも） ／ **Drive 共有先メール**（v1.5）／
**DBの内部ID（`id` 列）** ／ **`revision_requests.message` 本文**（v1.5）／
Stripe 情報 ／ Operations 情報 ／ AI Sales 情報 ／ 内部ログ ／ 監査ログの明細

**要件**

| # | 規則 |
|---|---|
| 1 | UTF-8 / `JSON_UNESCAPED_UNICODE`。`Content-Type: application/json` |
| 2 | `Content-Disposition: attachment`。ファイル名は**案件番号を正規化**したもの |
| 3 | `Cache-Control: no-store` ／ `X-Content-Type-Options: nosniff` |
| 4 | **allowlist 方式**で組み立てる（DBの行をそのまま渡さない） |
| 5 | **書き出す直前にサーバー側で再検証**する。未提出・不正な案件は書き出さない |
| 6 | 一時ファイルを公開領域へ作らない（メモリ上で組み立てて直接返す） |
| 7 | ダウンロードを監査へ残す（`export_generated`）。**JSON 本文をログへ出さない** |
| 8 | 本文の SHA-256 を `X-Intake-Export-Sha256` ヘッダーで返す（取込側の検証用・独自ヘッダー） |

> ★規則8 は独自仕様である。**README に明記**し、取込側（OPS-4）が使うかどうかは
> OPS-4 で判断する。ヘッダーが無くても取込が壊れない形にしておくこと。

**`reviewed_at` について（列を増やさない判断）**

`intake_cases` には `submitted_at` / `locked_at` / `closed_at` はあるが **`reviewed_at` は無い**。
書き出しのためだけに列を足すことはせず、**`intake_submission_history` の
`event_type = 'reviewed'` の行の `submitted_at`**（＝その操作が起きた時刻）から導く。

- 履歴に `reviewed` の行が無ければ `null` を出す（推測して埋めない）
- 複数あれば**最も新しいもの**を採る（`needs_revision` を挟んで再確認した場合）
- 同じ理由で `needs_revision` の時刻も履歴側（`revision_requested`）が持つ

---

## 12. 未確定事項（運用値・後工程の判断）

**データモデル上の矛盾は残していない。以下は運用値と実装手段の未確定である。**

### 12.1 R2 / R3 / R4 / R5 / R6 / R7 / R8 / R9 / R10 で確定した事項（未確定から外したもの）

| 旧# | 事項 | 確定内容 | 反映先 |
|---|---|---|---|
| 2 | XServer の SQLite / PDO 対応 | **pdo_sqlite=true / SQLite3=true / SQLite 3.26.0 / VACUUM INTO 不可** | §0.2・§2.0.1・§9.6・§9.6.1 |
| 4 | 既存 contact.php の本番配置状況 | **配置済み**（405 / Allow: POST・Origin検査が実効） | §0.2（記録） |
| 7 | メール送信方式 | **mail() を採用**（Phase 1）。内容は案件番号・提出日時・イベント種別のみ | §0.2 |
| 8 | rate limit 値・自動保存間隔 | **確定**（無効token 10分5回／保存 10分60回／提出 10分5回／自動保存30秒） | §0.2 |
| — | token 初回交換方式 | **`/start#<token>` → POST → Cookie 方式を正式採用** | §4.2・§4.7・§2.6 |
| — | 保持・削除の責任境界（R7） | **公開日・公開承認を Intake へ保存しない。Intake は `retention_delete_due` だけを手動で持つ** | §9.0 |
| — | `intake_revision_requests` の保持期間（R7） | **回答本文と同じ**。案件削除時に `open` / `resolved` を問わず物理削除 | §2.8-11・§9.1 |
| — | `locked` の運用（R7） | **`reviewed` からのみ・確認画面＋案件番号再入力・token と店舗 session を同一トランザクションで失効** | §5.1 |
| — | `closed` の設定契機（R7） | **通常操作を作らない。機密情報の削除完了時に設定する** | §5.1・§9.3-3 |
| — | 破壊的操作のフラグ（R7） | **`retention_actions_enabled` と `backup_policy_confirmed` が両方真のときだけ削除を通す**（既定 false） | §9.8 |
| — | 削除内容のファイル残留（R7） | **`PRAGMA secure_delete = ON`** を接続ごとに設定する | §9.3-5・§2.0.1 |
| — | 回答 JSON の範囲（R8） | **11分類・129パスだけ**。分類の中身のキーも厳格に検査し、未知キーがあれば**要求全体を拒否** | §3.0.1 |
| — | 既存DBの未知キー（R8） | 読み出し・書き出しで**出力しない**。落とさない。**自動清掃はしない** | §3.0.1・§11.3 |
| — | 正式構造の生成元（R8） | **schema.js を起点**に PHP 側を機械生成する。二重に手書きしない | §3.0.1 |
| — | 操作対象の大きさ（R8） | **最小 48px**（`label` を含む領域で確保。文章中のリンクは対象外） | §10.10 |
| — | 必須の種類（R9） | **5種へ分ける**。必須を1種類として扱わない | §3.0.2 |
| — | 必須の実装元（R9） | **schema.js から生成**。PHP へ手書きしない。画面・API・管理・書き出しが同じ集合を見る | §3.0.2 |
| — | enum 7件（R9） | **能動選択必須**。既定値を自動で入れない。`full` / `show` を自動で選ばない | §3.0.2 |
| — | `contact_form.enabled`（R9） | **キー必須・`false` は正式な回答**。`"false"` / `0` / `1` / `null` は拒否 | §3.0.2 |
| — | `promotion.industry`（R9） | **Phase 1 対象外**。正式パスへ含めない（任意ではない） | §3.0.2・§3.5 |
| — | Smart Labo 設定5件（R9） | **正式パスへ追加**（129→134）。店舗から見えず書けず、**書き出し前にだけ**必須 | §3.12・§11.3-8 |
| — | 真偽の3状態（R10） | **未回答はキーが無いことだけ**。`null` は保存させない（400） | §3.0.2 |
| — | 管理設定の内容条件（R10） | 予約URLと外部サービスは**空が正式**。送信先と保管方法は**空にできない** | §3.12 |
| — | 失敗した書き出し（R10） | **痕跡を残さない**（本文・SHA-256・監査・一時ファイルのいずれも作らない） | §11.3-9 |
| — | display_errors | **本番の必須要件として明文化**（Off / log_errors On / error_log は public_html 外） | §10.4.1・§10.6 |
| **3（R3）** | Google Drive のフォルダ命名規則 | **最上位は案件番号のみ**／固定サブフォルダ4つ<br>（`01_images` / `02_logo` / `03_documents` / `04_references`）<br>店舗名・氏名・電話番号・住所・メールを**フォルダ名へ入れない** | §7.1 |
| **—（R3）** | 提出の冪等化 | **`submission_id`（UUID v4）を `/submit` の必須入力**とし、<br>`intake_submission_history` へ保存・部分一意索引で重複を防ぐ | §2.4・§6.4 |
| **—（R3）** | 自動保存の方式 | **最終変更から30秒後／ステップ移動時／手動保存ボタン**の3契機。<br>変更された分類だけを送る。409 では上書きしない | §6.1 |
| **—（R3）** | session Cookie の有効期間 | **Max-Age 24時間を維持**（確定事項）。<br>4C で「入力を終了する」ボタン＋`POST /session/logout` を必須とする | §2.6 |
| **1（R4）** | **管理画面の認証方式** | **代表1名・`private/intake-config.php` の ID ＋ `password_hash()` の hash**（Argon2id 優先）。<br>アカウント表は作らない。session は **`intake_admin_sessions`（7表目）**。<br>idle 30分／絶対8時間／ログイン時に再生成／CSRF は hash で server 保持 | §2.7・§10.8 |
| **—（R4）** | 検証済み書き出しの形 | **allowlist 方式の JSON**（`export_schema_version` / `answers` / `rights` /<br>`submission_summary` 等）。token・session・IP・鍵・Drive URL・内部IDを**含めない** | §11.3 |
| **—（R4）** | 対応ブラウザ | **現行 Chrome / Edge / Firefox / Safari**。`Sec-Fetch-Site` 非対応はサポート対象外。<br>`Referrer-Policy: no-referrer` のため「Referer で通る」前提に依存しない | §10.9 |
| **10（R5）** | **`reviewed` → `needs_revision`** | **代表判断で許可**（案B）。`locked` / `closed` より前まで戻せる。<br>戻したら `reviewed` へは**店舗の再提出を経る**。監査・履歴・修正依頼を必ず残す | §5.1 |
| **8（R5）** | **修正理由の伝え方** | **`intake_revision_requests`（8表目）を新設**。回答欄へ押し込まず、<br>監査にも本文を入れない。`open` / `resolved`・複数回保持・再提出で自動 resolved | §2.8 |
| **9（R5）** | **Drive 共有先メールの保持** | **`drive_shared_email_enc`（AES-256-GCM）**を追加。<br>店舗の §7.2 案内文を正確に書けるようにする。書き出し・監査・ログへ出さない | §2.1・§7.3 |
| **—（R5）** | **Drive URL の受け入れ条件** | https のみ／**Google Drive の正式ホストのみ**／userinfo 禁止／ポート禁止／<br>制御文字禁止／500文字まで。query・fragment は保持してよい | §7.3 |
| **—（R5）** | **案件作成・token 初回発行の経路** | **管理画面から行える**ようにした（CLI を運用にしない）。<br>token 平文は**作成直後に1回だけ表示**。再表示機能を作らない | §10.8・README |
| **—（R6）** | **ご案内リンクの再発行** | **管理画面から行える**（§4.4.1）。`draft` / `needs_revision` のみ。<br>旧 token と**関連する店舗 session を同一トランザクションで失効**。<br>新しい平文は**1回だけ表示**し、復元機能を作らない。監査は `token_reissued`。<br>**回答・version・提出履歴・修正依頼・Drive 情報は維持**する | §4.4.1・§2.5 |

### 12.2 残る未確定事項

| # | 事項 | 影響 | 確定させる工程 |
|---|---|---|---|
| ~~1~~ | ~~**管理画面の認証方式**~~ | **R4 で確定**（§10.8・§2.7・§12.1）。番号は参照の互換のため空けてある | ~~4D 着手前~~ |
| 2 | **intake.smartlaboworks.com のサブドメイン・SSL 設定** | 到達性そのもの。現状は未設定（ワイルドカードDNSで到達するのみ・証明書は `*.xserver.jp`） | 4H（代表作業） |
| ~~3~~ | ~~**Google Drive の実フォルダ命名規則**~~ | **R3 で確定**（§7.1・§12.1）。番号は参照の互換のため空けてある | ~~4C 着手前~~ |
| 4 | **本番バックアップ先の具体パス**／**APP_ROOT の解決経路** | §9.5・§10.11。世代・削除方針・命名・cleanup・復元確認は v1.11（4G）で、配置境界は v1.12（4H-R0）で確定した。<br>**`auto_prepend_file` の実機確認は v1.13（4H-3）で完了**し、本番は**祖先探索方式**を採用した（§10.11）。<br>残るのは **XServer 上の正確な絶対パス（`backup_dir`）・権限・`SQLite3::backup()` の実測**である。<br>★**§9.9 の条件が揃うまで `backup_policy_confirmed` を真にしない** | 4H（実機確認） |
| 5 | **mail() の実送信確認** | v1.12（4H-R0）で提出通知を実装した（§9.11）。<br>関数・sendmail 設定の存在までは実測済み。**実送信は未確認**。<br>★4H で**当社 info@ 宛て1通のみ**。**代表の直前承認**が要る | 4H |
| 6 | **1案件あたりの配列上限値** | §3 に**設計既定値**（menus 60 / staff 30 / images 60 等）を置いた。<br>運用実績で調整する余地を残す | 4B（既定値のまま進めてよい） |
| 7 | **ローカル開発環境の php.ini** | ローカルは `php.ini` 未読込のため pdo_sqlite / openssl / mbstring が無効。<br>DLL は同梱済みで、有効化すれば解決する | 4B 冒頭（代表判断は不要） |
| ~~8~~ | ~~**`needs_revision` の理由の伝え方**~~ | **R5 で確定**（§2.8 `intake_revision_requests`）。番号は参照の互換のため空けてある | ~~4E 以降~~ |
| ~~9~~ | ~~**店舗画面への Drive リンク表示**~~ | **R5 で確定**（§2.1 `drive_shared_email_enc`・§7.3）。番号は参照の互換のため空けてある | ~~4E 以降~~ |
| ~~10~~ | ~~**`reviewed` から `needs_revision` へ戻せない**~~ | **R5 で案B を採用し確定**（§5.1）。番号は参照の互換のため空けてある | ~~代表判断~~ |
| 11 | **`expose_php = Off` が XServer の `.user.ini` から効くか** | `PHP_INI_SYSTEM` のため共用サーバーでは効かないことがある。<br>`.htaccess` の `Header always unset X-Powered-By` を併記して備えてある（§10.4.1-8） | 4H（実機確認） |
| 12 | **中止案件を `closed` にする経路** | 現状 `closed` は削除完了時にしか設定されない。<br>途中で中止した案件の扱いは運用が固まってから決める | 4G 以降 |
| 13 | **管理 session 清掃の自動化** | Phase 1 は保守画面からの明示操作（§2.7-10）。<br>cron 化するなら本書を改定してから行う | 4G 以降 |

**`reviewed` → `needs_revision`（R5・代表判断で「案B」を採用）**

4D で提示した2案のうち、代表が**案B（遷移表へ1行追加）**を採用した。

| 項目 | 決定 |
|---|---|
| 遷移表 | `'reviewed' => ['needs_revision', 'locked', 'closed']`（§5.1） |
| 戻せる範囲 | **`locked` / `closed` より前**まで。確定後は戻さない |
| 戻したあと | `reviewed` へ進むには**店舗の再提出が必要**（近道を作らない） |
| 記録 | 監査 `case_status_changed` ＋ 履歴 `revision_requested` ＋ 修正依頼（§2.8） |
| 理由 | 確認後に写真・料金・表現・権利確認の不足が見つかりうる。<br>戻せない設計は **DB直接操作や別管理を誘発する**ため運用に適さない |

> ★`intake_submission_history` は `revision_requested` を既に語彙に持つため、
> §2.4 の表は変更していない。

> ★§12.2-6 について: 本書の上限値は**実装既定値として確定**しており、
> これに従えばモデルは成立する。Phase 1 の運用記録（仕様書 §20）を見て
> 次版で見直す、という意味での「未確定」である。
>
> ★**データモデル上の矛盾は残していない。**
> v1.1 に残っていた唯一の矛盾（`VACUUM INTO` を第一手段とする記述と
> 本番 SQLite 3.26.0 の非対応）は、本 R2 で解消した。

---

## 13. 上位文書との整合

| 観点 | 上位文書の規定 | 本書の扱い |
|---|---|---|
| 入力項目 | 仕様書 v1.2 §5〜§14（161項目） | §3 で全分類をデータパスへ対応。項目を増減しない |
| 内部連絡先の非公開 | 仕様書 §5.1（`contact.internal` を生成物へ出さない） | §3.1 で `basic.internal_contact.*` を内部固定・生成不可 |
| 秘密値を受け取らない | 仕様書 §10.2（10種） | §8 で15種へ拡張（Stripe・公開承認を追加）。緩めていない |
| 税込総額を主表示 | 仕様書 §6.1 / 価格SSOT §1 | §3.3 の検証規則（`tax_type=unknown` は生成除外） |
| スタッフ掲載の初期値 | 仕様書 §9.1（初期値＝掲載しない） | §3.4 `staff[].published` 既定 `false` |
| 写真の権利・同意 | 仕様書 §12.4〜§12.6 | §3.10・§9.4（削除後も同意証跡を継続保持） |
| ビフォーアフター | 仕様書 §8.2.2（標準では使用しない） | `image_metadata[].role` に該当区分を設けない |
| 確認用URL | 仕様書 §18.1（noindex は認証ではない） | §4.8 で同一原則。noindex を認証の代替にしない |
| 公開承認 | 仕様書 §18.3 | §5.4・§8 で intake から除外し、**Smart Labo Operations** を正式記録先とする |
| Stripe | 価格SSOT §12-5（お金は Stripe、管理は案件管理） | §1・§8 で intake から完全に分離 |
| 画像受領 | 仕様書 §12.7（クラウド共有フォルダ・1店舗1フォルダ） | §7 で Google Drive として具体化 |
| 5営業日の起算 | 仕様書 §1（着手可能日） | 本書は起算日を定義しない（仕様書に従う） |
| 確認用URLの秘匿 | 仕様書 §18.1（noindex は認証ではない） | §4.2 で **token を URL fragment に置き、リクエストへ送らない**方式を採用。<br>ログ・Referer・プロキシに残さない |
| エラー詳細の非表示 | 仕様書 §10.6 相当（詳細を外部へ出さない） | §10.4.1 で **`display_errors=Off` を本番必須要件**として明文化 |

---

## 14. 今後の工程順序（正式）

HP-ONBOARDING-4A-R1 で次の順序へ変更した。

| 工程 | 内容 | 対象 |
|---|---|---|
| **4A-R1** | AI Sales 分離・Operations 境界確定（**本工程**・docs-only） | SmartLabo repo |
| **4B** | HP Intake 受付API・token 骨組み | SmartLabo repo `intake-api/` |
| **4C** | 店舗入力画面 | 同上 |
| **4D** | HP Intake 確認画面・書き出し | 同上 |
| **4E** | セキュリティテスト | 同上 |
| **4F** | 架空店舗による通し確認 | 同上 |
| **OPS-1** | Smart Labo Operations 要件・データモデル | 別途（未確定） |
| **OPS-2** | Operations 管理画面 | 同上 |
| **OPS-3** | Stripe参照・請求状態の**手動記録** | 同上 |
| **OPS-4** | Intake から Operations への安全な取込 | 同上 |

**OPS-4 の方式（確定）**
- **直接DB接続ではなく、検証済みの書き出しデータの取込から開始する。**
- Operations から Intake の DB・API を直接読みに行かない。

**Stripe API・webhook による自動化は、さらに後工程とする。**
（価格SSOT §12-6 のとおり、現時点で未確定・未実装）

★**AI Sales はこの工程順序のどこにも登場しない。**
  AI Sales リポジトリは本工程および今後のHP工程で変更しない。

---

## 15. AI Sales の商品化境界（参考節）

本書の設計判断には影響しないが、境界を誤解しないために記録する。

| # | 記録 |
|---|---|
| 1 | 現在の AI Sales は**社内用プロトタイプ**である |
| 2 | **そのまま外部販売しない** |
| 3 | 商品化は**別工程**で扱う |
| 4 | 商品版には **認証・企業別データ分離・権限・契約・課金・セキュリティ・バックアップ**が必要 |
| 5 | **Smart Labo の実営業データを商品版へ含めない** |
| 6 | HP Intake・Smart Labo Operations とは**別商品・別データ**である |
| 7 | **本HP工程では AI Sales の商品化へ着手しない** |

---

## 16. 変更履歴

| VERSION | 日付 | 内容 |
|---|---|---|
| **v1.0** | 2026-08-26 | 制定（HP-ONBOARDING-4A）。システム境界5系／SQLite 5テーブル（intake_cases・intake_tokens・intake_answers・intake_submission_history・intake_audit_events）／JSON 11カテゴリのデータパス・型・上限・公開内部区分・HTML出力処理・空値／token 規則（random_bytes(32)・base64url 43文字・SHA-256 hash のみ保存・14日・1案件1本・失効・同一エラー文言・漏えい経路と対策）／状態遷移6状態と操作許可表／途中保存と楽観ロック／Google Drive 運用／保存禁止15種／保持削除バックアップ／セキュリティ／Phase 1 境界／未確定9件 を確定 |
| **v1.1（R1）** | 2026-08-26 | 改定（HP-ONBOARDING-4A-R1・代表承認）。**AI Sales を保存先・連携先とする記述をすべて撤回**し、契約後管理先を内部専用 **Smart Labo Operations**（社内管理上の仮称・未実装）へ置き換えた。§0.1 に R1 の正式決定10件を追加。§1 の系を **HP Intake / Smart Labo 管理 / Google Drive / Smart Labo Operations / Stripe / AI Sales** へ再定義し、AI Sales は**営業支援専用・連携しない**という境界説明のみに限定。§1.1 の越えてはならない線を7条へ。§1.2 Operations の位置づけ／§1.3 Operations 未実装時の標準管理票運用（GitHubへ保存しない・intake自由記述へ押し込まない・AI Salesへ入力しない）／§1.4 AI Sales の位置づけ を新設。§3.11・§3.12・§5.4・§8・§9.3・§9.4・§10.7・§11.2・§13 の該当記述を Operations へ是正。**§14 今後の工程順序**（4B〜4F → OPS-1〜OPS-4。OPS-4 は検証済み書き出しデータの取込から開始）と **§15 AI Sales の商品化境界**（参考節）を新設。旧 §14 変更履歴を **§16** へ繰り下げ。**intake 5テーブル・JSON 11分類・token 規則・状態遷移・途中保存・Drive 運用・保持削除・セキュリティ要件は一切変更していない。** |
| **v1.2（R2）** | 2026-08-27 | 改定（HP-ONBOARDING-4A-R2・代表承認）。**XServer 実測（-4B-PRE / 2026-08-27）を反映**。§0.2 に実測値・確定運用値・本番環境の記録を新設。**`VACUUM INTO` を撤回**し（本番 SQLite **3.26.0** のため使用不可）、**`SQLite3::backup()` を第一手段**へ変更（§9.6・§9.6.1 新設）。**§2.0.1 SQLite 3.26.0 互換サブセット**を新設（使用禁止機能・使用可機能・起動時ガード・4E での静的チェック）。**§2.6 `intake_sessions` を追加**（5表 → **6表**）。旧 §2.6 を §2.7 へ繰り下げ。**token 初回交換方式を正式採用**（`/start#<token>` → `POST /session/start` → Secure / HttpOnly / SameSite=Strict Cookie。§4.2 全面書き換え・§4.5 を (A)(B) の2段へ・§4.7 を「設計候補」から「正式採用」へ）。**§10.4.1 PHP 設定（display_errors=Off / display_startup_errors=Off / log_errors=On / error_log は public_html 外）を本番必須要件として新設**し、§10.6 へ反映。§10.7 のマスク対象へ session secret を追加。§12 を §12.1（R2 で確定：SQLite/PDO・contact.php 配置済み・mail() 採用・rate limit 値・token 交換方式・display_errors）と §12.2（残る未確定7件）へ再編。§13 へ2行追加。**JSON 11分類・§3 の全データパス・状態遷移・途中保存・Google Drive 運用・保持削除規則・保存禁止15種は一切変更していない。** |
| **v1.3（R3）** | 2026-08-27 | 改定（HP-ONBOARDING-4B-R1・代表承認）。**最終提出の冪等化を確定**した。**§2.4 へ `submission_id TEXT NULL` を追加**（クライアント生成 UUID v4・保存先はこの1列のみ・`UNIQUE(intake_case_id, submission_id) WHERE submission_id IS NOT NULL` の部分一意索引・既存行との互換のため NULL 許容・HTTP `/submit` では必須・ログ／監査／エラー本文へ出さない）。**§6.4 を全面改定**し、`status` だけの冪等化を4層（画面／`submission_id`／`status`／DB一意制約）へ拡張。`/submit` の挙動を表で確定した（欠落・不正形式=**400**／初回=履歴1件＋監査1件＋`submitted` 遷移／**同一 `submission_id` の再送=履歴も監査も増やさず同じ結果**／**異なる `submission_id` で `submitted`・`reviewed` の案件へ提出=409**・既存 `submission_id` も提出済み内容も返さない／競合は固定応答へ変換）。**履歴追加と状態遷移を同一トランザクション**とすることを明記。`submission_id` の生成契約（送信のたびに新規・再試行のときだけ同一・検証エラー修正後は新規）を 4C の義務として追加。**§2.6 へ Cookie `Max-Age` 24時間の維持を確定事項として明記**し、4C の「入力を終了する」ボタン＋`POST /session/logout` を必須化（規則12・判断根拠5点）。**§6.1 へ自動保存の方式を確定**（最終変更から30秒後／ステップ移動時／手動保存ボタン・変更分類のみ送信・成功後に `version` 更新・409 では上書きせず利用者へ確認）。これにより §6.1 の「間隔は未確定」という記述と §12.1-8 の「自動保存30秒で確定」との食い違いを解消した。**§7.1 へ Drive フォルダ命名規則を追加**（規則12〜14。最上位は**案件番号のみ**／固定サブフォルダ `01_images`・`02_logo`・`03_documents`・`04_references`／店舗名・氏名・電話番号・住所・メールをフォルダ名へ入れない）。§10.7 の「出さない」へ `submission_id` を追加し、**ログのキーを許可制（allowlist）**とする方針を明記。§12.1 へ R3 の確定4件を追加し、§12.2-3（Drive 命名規則）を確定済みへ。**JSON 11分類・§3 の全データパス・token 規則・状態遷移6状態・楽観ロック・保持削除規則・保存禁止15種・SQLite 3.26.0 互換サブセットは一切変更していない。** DB変更は `intake_submission_history` への**列1つの追加と索引1つの追加のみ**で、既存の6テーブル構成は変わらない。 |
| **v1.4（R4）** | 2026-08-27 | 改定（HP-ONBOARDING-4D・代表承認）。**管理画面の認証方式を確定**し、§12.2-1 を未確定から外した。**§10.8 を全面改定**（資格情報は `private/intake-config.php` の管理者ID＋`password_hash()` の hash のみ・**Argon2id 優先**・実値をGitへ入れない・**未設定なら fail closed**・IDの存在有無で応答も時間も変えない／session は idle 30分・絶対8時間・**ログイン成功時に再生成**／ログイン防御は **HMAC化IP で10分5回**・固定文言・`admin_login` へIDもパスワードも記録しない／CSRF は `random_bytes(32)` を **server 側 session へ hash で保持**し `hash_equals()` で照合・URLとログへ出さない・Origin 検査を併用・**GET で状態を変えない**）。**§2.7 `intake_admin_sessions` を新設**（6表 → **7表**。店舗の §2.6 とは別表・平文列を作らない・`csrf_hash` を同居させる・ロール列を作らない）。旧 §2.7 を **§2.8** へ繰り下げ、**アカウント表と修正理由テーブルを「作らない」判断**として記録。**§2.5 へ監査イベント4種を追加**（`drive_upload_confirmed` / `case_status_changed` / `admin_login` / `admin_logout`。ログイン系は `intake_case_id` を NULL とし、**入力された管理者IDを記録しない**）。**§11.3 検証済み書き出しを新設**（allowlist 方式・含める11キーと**含めない16種**を明記・`Content-Disposition: attachment`・`no-store`・書き出し直前の再検証・未提出案件を出さない・一時ファイルを公開領域へ作らない・`X-Intake-Export-Sha256` は**独自ヘッダーとして README へ明記**）。**§10.9 対応ブラウザを新設**（現行 Chrome / Edge / Firefox / Safari。**`Sec-Fetch-Site` 非対応はサポート対象外**。`Referrer-Policy: no-referrer` のため「Referer で通る」前提に依存しないことを明記し、**4C 報告の当該説明を訂正**。`GET /case` の受理は **4E で再検証**。**POST の Origin 検査は変更しない**）。§11.2-11 を「代表1名」へ具体化。§12.2 へ **-8（修正理由の伝え方）** と **-9（店舗画面への Drive リンク表示）** を新規の未確定として追加。**JSON 11分類・§3 の全データパス・token 規則・状態遷移6状態・楽観ロック・`submission_id` の冪等化・保持削除規則・保存禁止15種・SQLite 3.26.0 互換サブセットは一切変更していない。** |
| **v1.5（R5）** | 2026-08-27 | 改定（HP-ONBOARDING-4D-R1・代表承認）。**§5.1 の遷移表へ `reviewed` → `needs_revision` を追加**（4D で提示した案B を代表が採用。`locked` / `closed` より前まで戻せる／戻したら `reviewed` へは**店舗の再提出**を経る／監査・履歴・修正依頼を必ず残す／DBの直接操作を運用にしないための判断）。**§2.8 `intake_revision_requests` を新設**（7表 → **8表**。`request_number` / `requested_paths_json` / `message` / `status` / `created_at` / `resolved_at`。**§3 の正式パス129件のみ許可**・未知パスを含む要求は丸ごと拒否・重複は正規化・`message` は1000文字まで・**本文をログと監査へ出さない**・複数回保持・過去を削除も上書きもしない・**状態変更と同一トランザクション**・**店舗の再提出成功で `open` を `resolved` へ**・店舗へ返すのは `open` のみ・**管理者を識別する列を作らない**）。v1.4 §2.8 の「修正依頼の理由テーブルを作らない」判断を**撤回**し、旧 §2.8 を **§2.9** へ繰り下げ。**§2.1 へ `drive_shared_email_enc` を追加**（AES-256-GCM。§7.2 の案内文「このフォルダは○○にのみ共有しています」を正確に書くため。**平文保存禁止／書き出し・監査・ログへ出さない／一覧へ出さない／認証済み店舗の `GET /case` にだけ返してよい**）。**§7.3 へ Drive URL の受け入れ条件8項目**（https のみ・**Google Drive の正式ホストのみ**・userinfo 禁止・短縮URL拒否・ポート禁止・制御文字禁止・query/fragment は保持可・500文字まで）と**共有先メールの扱い6項目**を新設。**§10.8 の Fetch Metadata の記述を訂正**（「偽装できない」→「**Forbidden request header でありブラウザ内 JavaScript からは設定できない**が、**curl 等の非ブラウザからは任意に構成できる**」。`cross-site` / `none` / 欠落はすべて拒否。**CSRF token は常に必須**で、多層防御を維持する）。**§11.3 へ `revision_requests` を追加**（`request_number` / `requested_paths` / `status` / `created_at` / `resolved_at` のみ。**`message` 本文と `id` は含めない**。理由も明記）。除外一覧へ **Drive 共有先メール**と **`revision_requests.message`** を追加。**案件作成と token 初回発行を管理画面から行える**ようにし、CLI を運用にしない方針を確定（token 平文は作成直後に1回だけ表示・再表示機能を作らない）。§12.1 へ R5 の確定5件、§12.2-8・-9・-10 を確定済みへ。**JSON 11分類・§3 の全データパス・token 規則・6状態そのもの・楽観ロック・`submission_id` の冪等化・保持削除規則・保存禁止15種・SQLite 3.26.0 互換サブセットは変更していない。** |
| **v1.6（R6）** | 2026-08-27 | 改定（HP-ONBOARDING-4D-R2・代表承認）。**§4.4.1 ご案内リンクの再発行を新設**。平文は発行直後に1回しか出さないため、受け取れなかった場合の**唯一の回復手段が再発行**であることを明記し、**「以前の token を復元する」機能は作らない**と確定した（復元できるということは、どこかに平文が残っているということである）。再発行してよいのは **`draft` / `needs_revision` のみ**とし、`submitted` / `reviewed` / `locked` / `closed` / 未知状態は**すべて拒否**（修正が要る場合は**先に §5.1 の `needs_revision` へ**遷移させてから再発行する）。手順6段を**同一トランザクション**で行うことを明記（①状態をトランザクション内で再確認 ②有効 token をすべて失効 ③その token から出た**店舗 session をすべて失効** ④`random_bytes(32)` で新 token ⑤**SHA-256 hash のみ**保存・期限は発行から14日 ⑥監査 `token_reissued` を1件）。守ること6項目を明記（**回答・`version`・提出履歴・修正依頼・Drive 情報を消さない**／新しい平文は**成功画面で1回だけ**・戻る/再読込で再表示しない／**ログ・監査・DBの平文列・管理 session・URL・Cookie へ出さない**／実行前に**案件番号の再入力を求め完全一致のみ実行**／**POST のみ**で CSRF・Origin 検査・管理 session 必須／**案件単位＋HMAC化IP で 10分5回**）。通信断時の扱いも明記（DB上は成功しており**旧 token はすでに無効**。もう一度再発行してよく、直前の新 token も失効し監査が1件増える。回答は維持）。**§2.5 へ `token_reissued` を追加**（初回の `token_issued` と区別し、「何回配り直したか」を監査から読めるようにする。**token 平文も hash も書かない**）。§9.2 の失効タイミングへ「再発行時は**関連する店舗 session も**即時失効」を追記。§12.1 へ R6 の確定1件。**JSON 11分類・§3 の全データパス・token の生成規則（`random_bytes(32)`／base64url 43文字／hash のみ保存／14日／1案件1本）・6状態そのもの・楽観ロック・`submission_id` の冪等化・保持削除規則・保存禁止15種・SQLite 3.26.0 互換サブセットは変更していない。DBスキーマの変更も無い（8表のまま）。** |
| **v1.7（R7）** | 2026-08-27 | 改定（HP-ONBOARDING-4F-PRE・代表承認）。**保持・削除の責任境界を確定**した。**§9.0 を新設**し、13条を明記（**公開日を Intake へ保存しない**／**公開承認を Intake へ保存しない**／公開日・公開承認は **Smart Labo Operations の責任**、未完成の間は §1.3 の標準管理票を正とする／「公開後6か月」の削除予定日は **Operations または標準管理票の側で計算**する／Intake が持つのは **`retention_delete_due` の1列だけ**で**人が手動登録**する／**`retention_delete_due` から公開日を逆算しない**／Intake と Operations を**直接DB接続しない**・OPS-4 までは §11.3 の検証済み書き出しと手動登録／**自動削除しない**（cron・起動時処理・バッチを作らない）／Phase 1 は**管理者の明示操作のみ**／**Google Drive の実ファイル削除は Intake の責任外**／Drive 削除実施日は標準管理票または Operations 側で管理／**AI Sales へ保持情報を移さない**）。判断の理由も明記した（公開日を持てば「6か月後」を Intake が計算することになり、**公開判断の責任が Intake へ滑り込む**）。**§9.1 保持期間を全面改定**（回答本文・token・店舗 session・**修正依頼**・提出履歴・**Drive URL と共有先メールの暗号文**・案件メタ・監査13か月・管理 session を1表に整理）。**§2.8-11 で `intake_revision_requests` の保持期間を確定**（**回答本文と同じ**。案件削除時に `open` / `resolved` を問わず物理削除し、`message` と `requested_paths_json` を残さない。理由: この表は「どこを直してほしいか」＝**回答の内容そのもの**を指しており、回答だけ消して依頼が残れば内容が読めてしまう）。**§9.3 削除の実施を全面改定**し、7節へ分割した（**9.3-1 削除予定日の登録**＝`reviewed` / `locked` のみ・`YYYY-MM-DD` の実在日のみ・過去日は警告・同日再送は冪等・変更は監査へ・**取り消し経路を作らない**・**公開日/公開承認/契約金額/Stripe の入力欄を作らない**・POST＋CSRF＋Origin＋管理session・**日付をログへ書かない**・一覧の区分5種・**一覧に回答本文/店舗名/Drive 情報を出さない**／**9.3-2 実行条件8件**を fail closed で列挙／**9.3-3 手順12段を同一トランザクション**・失敗時は全ロールバック／**9.3-4 削除後の禁止8件**／**9.3-5 `PRAGMA secure_delete = ON`**／**9.3-6 削除要請時**は削除予定日を要請日以前へ変更して実施し、要請理由の欄を Intake へ作らない／**9.3-7 自動実行しない**）。**§9.4 を 9.4-1（Operations へ移す継続保持）と 9.4-2（intake に残る最小メタデータの allowlist）へ分割**し、残す列を1つずつ理由つきで確定した。**§9.8 破壊的操作のフラグを新設**（`retention_actions_enabled` / `backup_policy_confirmed`。**既定はどちらも false**・片方でも false なら 403 でボタンも出さない・**`(bool)` で丸めず「明示的に真」だけを真とする**・実値を Git へ入れない・**4G より前に `backup_policy_confirmed` を真にしない**）。**§5.1 へ `locked`（確定）の規則11条を新設**（`reviewed` からのみ／POST＋CSRF＋Origin＋管理session／**確認画面と案件番号の再入力**／**token と店舗 session を同一トランザクションで失効**／監査3種／冪等／**回答・履歴・修正依頼・Drive 情報を削除しない**／`needs_revision` へ戻さない／再発行しない）。あわせて **`closed` は機密情報の削除完了時に設定する**ものとし、**通常操作を管理画面に作らない**と確定した。**§5.2 へ削除済み案件の全面拒否**を追記。**§2.5 へ監査4種を追加**（`retention_due_set` / `retention_purged` / `audit_purged` / `admin_sessions_purged`。**日付も件数の内訳も値も書かない**・`audit_purged` の行**自身も13か月後に削除対象**になるため保持が循環しない）。v1.6 までの **`answers_deleted` を `retention_purged` へ統合**（語彙は残すが新規記録しない）。**§2.7-10 / -11 へ管理 session の清掃**を追加（期限切れ・失効済みのみ物理削除・**有効な session は1件も消さない**・件数上限あり・**hash をログにも監査にも出さない**・案件の保持期限とは無関係・§9.8 のフラグを要求しない・**自動 cron を作らない**）。**§10.4 の CSP を改定**（4E の P3-2。**API 側と静的側で完全に同一の文字列**にする・**`object-src 'none'` と `font-src 'self'` を明示**・**`img-src` の `data:` を撤回**〔この画面群は画像もアイコンも使わない〕・`unsafe-inline` / `unsafe-eval` / ワイルドカード禁止）。**§10.4 へ公開領域の誤配置対策**を新設（4E の P3-3。ドットファイル／DB・ログ・退避／秘密鍵・証明書／設定／Composer 関連を拒否。**`.well-known` だけは除外**〔塞ぐと SSL 証明書の自動更新が止まる〕。**拒否規則をフロントコントローラより前**に置き、`mod_rewrite` と `FilesMatch` の**二重**で塞ぐ）。**§10.4.1 へ `expose_php = Off` を追加**（4E の P3-1。`PHP_INI_SYSTEM` のため共用サーバーでは `.user.ini` から効かないことがあり、**実機確認は 4H**。`.htaccess` の `Header always unset X-Powered-By` を併記）。**§2.0.1 の「使用してよい」へ `PRAGMA secure_delete` を追加**。§12.1 へ R7 の確定6件、§12.2 へ未確定 -11（`expose_php` の実機での効き）・-12（中止案件を `closed` にする経路）・-13（管理 session 清掃の自動化）を追加し、-4 を「世代・削除方針の確定まで `backup_policy_confirmed` を真にしない」へ具体化。**JSON 11分類・§3 の全データパス129件・token の生成規則・6状態そのもの・楽観ロック・`submission_id` の冪等化・保存禁止15種・SQLite 3.26.0 互換サブセットは変更していない。DBスキーマの変更も無い（8表・`PRAGMA user_version` は 4 のまま）。** |
| **v1.8（R8）** | 2026-08-27 | 改定（HP-ONBOARDING-4F-R1・代表承認）。**回答 JSON の allowlist を分類の中身まで徹底**した。4F の通し確認で、保存 API が**分類名（11種）だけ**を見ており、分類内部のキーは素通しで保存され、**§11.3 の「検証済み JSON」にも出ていた**ことが判明したため（P3・データ整合性）。**§3.0.1 を新設**し、受け取るとき14条／型5種／出すとき6条／正式構造の管理5条を確定した。**受け取るとき**: 保存できるのは **§3 の11分類・129パスだけ**／**分類の内部のキーも厳格に検査**／未知キーが**1件でも**あれば**要求全体を拒否**（400）／未知キーだけを**黙って削除して保存しない**／未知キーの**名前も値も**応答・ログ・監査へ出さない／**正常な分だけの部分保存をしない**／**配列要素の中の未知キー**も拒否（`menus[]` / `staff[]` / `image_metadata[]` / `business_hours.weekly[]` / `rights.confirmations[]`）／オブジェクト階層の誤りも拒否／**型**が違えば拒否／判定は**保存トランザクションより前**でDBは1バイトも変わらない／`version` を進めず監査も増やさない／固定コード・固定文言／既存のサイズ上限・配列上限・文字数・語彙・楽観ロックは従来どおり／「変更した分類だけを送る」既存方式（§6.1）を変えない。**型**は `scalar`（配列・オブジェクト不可）／`bool`（文字列 `"true"` 不可）／`list`／`object`／`objects` の5種。`__proto__` / `constructor` / `prototype` は**特別扱いの分岐を書かず**、「一覧に無いキー」として落とすことを明記した（分岐を書けば、書き忘れた名前が残る）。**出すとき**: 保存済みの値を**無条件に信用しない**／既存DBに未知キーが残っていても**出力しない**／未知キーが**あるだけで画面や書き出しを失敗させない**／**自動変換も別フィールドへの移送もしない**／**自動清掃機能は作らない**（既存行は触らない）／必須の欠落・不正は**従来どおり拒否**。**正式構造の管理**: 正式パスの追加・変更は **`public/assets/lib/schema.js` を起点**とし、PHP 側は**機械生成**（手で書き換えない）、**二重に手書きしない**、一致（分類・パス・配列要素の許可キー・型）と**生成の冪等性**を自動テストで固定、**新しいビルドシステムや npm 依存を増やさない**。**§11.3 へ「書き出し直前の絞り込み」7条を追加**（読み出しと書き出しで**二重**に絞る／保存側は「要求全体を拒否」・書き出し側は「出力しない」で**方向が違うため同じ処理にまとめない**／未知キーがあるだけで 500 にしない／必須の欠落は従来どおり拒否）。**§10.10 操作対象の大きさを新設**（4F で主導線 48px・補助 40px という食い違いを実測したため。主要・補助を問わず**最小 48px**／チェックは **`label` を含む領域**で確保／操作用リンクも 48px 相当／**文章中の通常リンクは対象外**／`height` で固定せず **`min-height` ＋ padding**／複数行の文言を許容／320px 幅で横へはみ出さない／操作間隔を保つ／`:focus-visible` 維持／無効は**色だけで示さない**／**管理画面も同じ基準**）。§12.1 へ R8 の確定4件。**JSON 11分類・§3 の全データパス129件・必須22パス・token 規則・6状態・楽観ロック・`submission_id` の冪等化・保持削除規則・保存禁止15種・SQLite 3.26.0 互換サブセットは変更していない。DBスキーマ・migration 版・回答スキーマ版の変更も無い（8表・`user_version` 4・回答スキーマ 1 のまま）。** |
| **v1.9（R9）** | 2026-08-27 | 改定（HP-ONBOARDING-4F-R3・代表承認）。**必須定義を SSOT §3 へ統一**した。4F-R2 の差分調査で、§3 が必須と定める **39件**に対し実装は **22件**しか見ておらず、画面（45件）だけが厳しく、**API を直接呼べば17件を欠いたまま提出できた**ことが判明したため。原因は「必須」を1種類として扱っていたことにある。**§3.0.2 を新設**し、必須を5種へ分けた（`STORE_REQUIRED_NON_EMPTY` ／ `STORE_REQUIRED_KEY_ALLOW_EMPTY` ／ `ADMIN_REQUIRED_FOR_EXPORT` ／ `ARRAY_ELEMENT_REQUIRED` ／ `OPTIONAL`。条件付きは §3 に条件が明記されているものだけ）。**空値の扱いを型ごとに明記**（string は `null` / `""` が未回答／enum は `null` / `""` / 語彙外が未回答で、**正式な語彙は `none` / `no` でも回答**／boolean は**キー無し・`null`・`"false"`・`0`・`1` が未回答**で **`false` は回答**／配列は §3 が1件以上を求めるときだけ `[]` が未回答／object はキー無しと `{}` が未回答）。**`false` を欠落と同じ扱いにしない**ことを明記した（「掲載しない」「予約を受けない」は答えである）。**能動選択が必要な enum 7件**（`basic.address_visibility` / `business_hours.irregular_notice` / `privacy.third_party` / `privacy.marketing_use` / `design.logo` / `design.emphasis` / `web_links.map_display`）を確定し、画面の初期状態を「選択してください」とし、**既定の語彙を選択済みにしない**・未選択（`""`）を正式値として保存しない・API でも同じ・**語彙外は保存そのものを拒否**・語彙自体は変えない、を規定した。とくに **`address_visibility` の `full` と `map_display` の `show` を自動で選ばない**（住所と地図を、本人の能動的な選択なしに公開側へ回さない）。あわせて「§3 の**空値欄は未入力の表し方**であり、既定値として入れてよい値ではない」ことを明記した（v1.8 まではこの2つを混同していた）。**必須の実装元を一本化**（PHP へ手書きしない／`schema.js` から機械生成／画面・API・管理画面・書き出しが**同じ集合**を見る／「画面では止まるのに API では通る」状態を作らない／一致と生成の冪等性を自動テストで固定）。**`promotion.industry` を Phase 1 の対象外**とした（代表判断 Q1。正式パスへ含めない・画面/DB/書き出しへ追加しない・**「任意」ではなく対象外**として明示・Phase 2 以降。§3.5 の表を取り消し線つきで残し、参考記述はそのまま置く）。**§3.12 を改定し、Smart Labo 設定5件を正式パスへ追加**（`web_links.salon_booking_url` / `privacy.destination` / `privacy.storage` / `privacy.external_services` / `privacy.consent_checkbox`。**129 → 134**）。15条を規定した（既存の `intake_answers` JSON 内へ保存し**新しい表も列も作らない**／**migration 版・回答スキーマ版も変えない**／店舗の入力項目にしない／**店舗の提出条件に含めない**／**`GET /case` で店舗へ返さない**／**`POST /answers/save` から変更できない**／逆に店舗が分類をまるごと保存しても**この5件が消えない**／管理者認証・管理 session・CSRF・Origin 検査・**案件番号の再入力**を必須／設定できるのは **`reviewed` / `locked`**・`closed` と削除済みは不可／**書き出しの前に検証**し不足なら拒否／「設定した」＝**キーが存在すること**で該当が無い場合も空のまま保存して記録を残す／**値をログ・監査へ出さない**／Operations・AI Sales へ保存しない／token・session・Drive 情報と同じ画面に混ぜない／型・上限・語彙は §3.7・§3.9 の表に従う）。**管理設定の不足だけで店舗を `needs_revision` へ戻さない**こと、管理画面では**店舗回答の不足と管理設定の不足を別々に表示**することも明記した。**§11.3 へ規則8を追加**（書き出し前に Smart Labo 設定が揃っていることを検査し、不足なら拒否して**不足パスだけ**を管理者へ示す。店舗へは返さない）。**§2.5 へ監査 `admin_settings_saved` を追加**（**設定値を書かない**）。§12.1 へ R9 の確定6件。**JSON 11分類・§3 の店舗パス129件・token 規則・6状態・楽観ロック・`submission_id` の冪等化・保持削除規則・保存禁止15種・SQLite 3.26.0 互換サブセットは変更していない。DBスキーマ・migration 版・回答スキーマ版の変更も無い（8表・`PRAGMA user_version` 4・回答スキーマ 1 のまま）。** |
| **v1.10（R10）** | 2026-08-27 | 改定（HP-ONBOARDING-4F-R4・代表承認）。v1.9 に残っていた**契約上の不整合2件**を訂正した。**(1) 真偽の項目の3状態を解消**。v1.9 までは真偽へ `null` を**保存できた**ため、「キーが無い」「`null`」「`false`」の3状態が生まれ、v1.9 で決めた「未回答と `false` を区別する」という契約が必要以上に複雑になっていた。**§3.0.2 を改定**し、**未回答は「キーが存在しないこと」だけ**とした。規則6条を明記（真偽へ **`null` を保存させず要求ごと 400**／`""` / `"true"` / `"false"` / `0` / `1` / 配列 / object も同じく拒否／未回答は**キーを送らない**ことで表す／拒否は**要求全体**で部分保存せず `version` も監査も動かさない／既存DBに `null` が残っていても**回答済みにせず、自動で `false` へ変換しない**／画面は「する／しない」の二択で**既定ではどちらも選ばない**）。`null` が「未入力」を表せるのは文字列の項目だけであることも併記した。**(2) Smart Labo 設定5件の内容条件を確定**。v1.9 では5件とも「キーがあれば設定済み」だったため、**送信先も保管方法も空のまま**「検証済み JSON」を書き出せてしまっていた。**§3.12 へ内容条件の表と規則4条を追加**（`web_links.salon_booking_url` は **空が正式**〔「予約URLなし」〕で値があるときは **`https://` のみ**・userinfo/制御文字/空白を含まない・500文字まで／`privacy.destination` と `privacy.storage` は **空・空白だけを許さず** 200文字まで／`privacy.external_services` は **`[]` が正式**〔「外部サービスなし」〕で10件まで・各60文字まで・要素の空を許さない／`privacy.consent_checkbox` は **`true` / `false` のみ**で `false` は「同意チェックを設置しない」という管理者の明示判断。**1件でも満たさなければ5件とも保存しない**／満たさない**パスだけ**を案内し**入力値を画面へ反射しない**／型・上限は §3.7・§3.9 の表と同じ値／画面は型に合った入力欄を使い必須・空欄可を**文字でも示す**）。あわせて §3.12-11 の「設定した」の定義を「**キーが存在し、かつ内容条件を満たすこと**」へ厳格化した。**§11.3 へ規則9を追加**（**失敗した書き出しは痕跡を残さない**。JSON 本文・`X-Intake-Export-Sha256`・`Content-Disposition`・`export_generated` 監査・一時ファイルの**いずれも作らない**。「書き出した」という記録は**実際に外へ出したときだけ**残す）。規則8へ「キーの存在だけでなく中身も見る」を追記。§12.1 へ R10 の確定3件。**JSON 11分類・正式パス134件（店舗129＋管理5）・必須39件・token 規則・6状態・楽観ロック・`submission_id` の冪等化・保持削除規則・保存禁止15種・SQLite 3.26.0 互換サブセットは変更していない。DBスキーマ・migration 版・回答スキーマ版の変更も無い（8表・`PRAGMA user_version` 4・回答スキーマ 1 のまま）。** |
| **v1.11（R11）** | 2026-08-28 | 改定（HP-ONBOARDING-4G・代表承認）。**バックアップ・復元確認・世代管理・保持削除との整合性を確定**した。**§9.5 を全面改定**し、取得方式（`SQLite3::backup()` 第一手段・単純コピーを通常手段にしない）／本番配置候補 `${DOMAIN_ROOT}/private/intake/backups`（**正確な絶対パスは 4H で確定**）／保存先として受け付けない値（未設定・相対・`..`・公開領域・ホーム直下・ルート直下・symlink・realpath 解決後の再検査）を表で確定。**§9.5.1 ファイル名**（`intake-YYYYMMDD-HHMMSS-<random8>.sqlite`・PII と秘密値を含めない・連番だけにしない）、**§9.5.2 メタデータ**（DB内へ保存しない・非PII の `manifest.json` に ファイル名/作成日時/サイズ/SHA-256/版 のみ・控えの無いファイルは検証を通さない）、**§9.5.3 取得手順**（排他ロック → 一時ファイル → integrity_check / foreign_key_check → SHA-256 → fsync 相当 → 権限600 → **同一ディレクトリ内 atomic rename** → 控え記録。上書き禁止・失敗時に一時ファイルを残さない・絶対パス全文を出さない）、**§9.5.4 世代**（30日・最大60世代・1日1世代・自動 cron 禁止・cleanup は 30日超 → 60世代超の古い順・稼働DBとディレクトリ外を対象にしない・**dry-run 既定**・実削除に明示フラグと確認文字列）、**§9.5.5 復元確認**（本番DBへ書き戻す restore を作らない。一時DBへ復元して integrity / foreign_key / user_version / 8表 / 回答スキーマ版 / 案件行の形式 / 非PII指標比較 → 一時DB削除。件数不一致は異常ではない。**本番復元は 4H 以降の別承認**）、**§9.5.6 保持削除との連動**（purge 前世代を30日保持より優先して削除／purge 後バックアップの作成・検証に失敗したら旧世代を1件も消さない＝「バックアップ側未完了」／冪等な再実行／**DB と filesystem を跨ぐため完全な atomic ではないと明記**し、段階・状態確認・再実行・runbook で担保）、**§9.5.7 管理CLIと管理画面**（CLI 6コマンドを第一手段。public_html 外・Web 実行不可・秘密値を引数に取らない・DB内容を出さない・パス全文を出さない・dry-run 既定。管理画面は 4G では追加しない）を新設。**§2.5 へ監査4種**（`backup_created` / `backup_restore_drill` / `backup_cleanup` / `backup_generations_purged`）を追加。**新しいDB表は追加していない**（`PRAGMA user_version` は 4 のまま・JSON 11分類・回答スキーマ版 1 も不変）。**§9.9 を新設**し、`backup_policy_confirmed` を true にしてよい9条件（4H の実機確認・実測・代表確認を含む）と、**4G 終了時点では false のまま**であることを確定。別文書 `docs/website/HP_INTAKE_BACKUP_RESTORE_RUNBOOK_V1.md` v1.0 を新設。**受付API・店舗入力画面・必須契約・書き出し契約・保持削除の規則は一切変更していない。** |
| **v1.12（R12）** | 2026-08-28 | 改定（HP-ONBOARDING-4H-R0・代表承認）。4H-PRE で判明した**本番配置上の3つの問題**を、XServer へ接続する前に解消した。**(1) §10.11 配置境界を新設**し、docroot と APP_ROOT が**兄弟である必要をなくした**。XServer のサブドメインは `public_html` の下に作られるため、従来の `docroot/../src` 前提では **src と private を公開領域へ置くことになる**という問題があった。正式配置を `public_html/intake.smartlaboworks.com/`（docroot）と `private/hp-intake/`（APP_ROOT）に分離し、APP_ROOT の与え方を **auto_prepend_file の非公開 bootstrap（第一候補）／環境変数／リポジトリ配置／docroot 祖先の相対探索（代替）** の4経路に定め、**どの経路でも同じ検査**（未設定・相対・`..`・public_html 内・ホーム直下・ルート直下・symlink 逸脱・`src/Autoload.php` の実在）を通すこととした。**Web の入力から APP_ROOT を変更できない**こと（解決コードはスーパーグローバルを読まない）、**docroot 内に APP_ROOT を書いたファイルを置かない**こと、**public 側へ絶対パスを書かない**こと、**絶対パスを応答・ログ・HTML・JSON へ出さない**こと、不正なら**既定へ落とさず fail closed** とすることを明記。Config を **APP_ROOT / src_root / private_root / db_path / log_path / rate_limit_dir / backup_dir / preflight_root** へ分離し、実設定ファイルは `APP_ROOT/private/intake-config.php` から読む（`private_root` はデータの置き場所だけを移す）ことを確定。**誤配置防御**として `src/.htaccess` と `bin/.htaccess`（`Require all denied`）を必須化し、公開領域に置く PHP は `index.php` 1つだけと定めた。**(2) §9.11 提出通知メールを新設**し、§0.2・§12.1-7 で確定済みだったのに**実装が無かった**通知を実装した。送信は**最終提出が初めて成功し commit された後だけ**（validation_error・同一 submission_id の再送・already_submitted・保存・Drive 申告・状態変更では送らない）。本文の allowlist は**案件番号・イベント種別・発生日時（UTC）の3項目だけ**で、回答本文・店舗名・氏名・メール・電話・住所・token・session・**submission_id**・Drive URL・修正依頼本文・IP・内部IDを含めない。宛先は**1つだけ**で、改行・制御文字・空白・カンマ・セミコロン・山括弧・引用符・バックスラッシュ等を拒否（ヘッダー注入対策）。業務コードから `mail()` を直接呼ばず `Notifier` で隔離し、**`mail()` を使ってよいのは `ProductionMailNotifier` の1ファイルだけ**（静的検査で固定）。テストは `FakeNotifier` で**実メール0通**。ヘッダーと件名は固定 allowlist、text/plain・UTF-8、追加パラメータ不使用。**送信に失敗しても提出は成功のまま維持**し、トランザクションを巻き戻さず、店舗へ知らせず、固定 result_code で監査し、**自動再送しない**。**(3) §9.10 preflight 専用環境を新設**し、本番配置後の通し確認を**正式DBで行わない**ことを確定。`APP_ROOT/preflight/` に専用の設定・DB・logs・ratelimit・backups を持ち、正式領域と**別実体**とする。設定の時点で正式 private_root と同一・内側・外側から包む位置を**受け付けない**（fail closed）。削除は**明示パスの事前確認・正式領域でないことの二重確認・symlink 拒否・領域外の再帰削除禁止・dry-run 既定・確認文字列の完全一致・残存0の確認**を必須とし、**preflight 削除 → 正式DB新規作成 → 正式な空DBバックアップ取得**の順序を固定した。**正式DBをテスト目的で削除・作り直す運用にしない。****§2.5 へ監査2種**（`submission_notification_sent` / `submission_notification_failed`）を追加。**§11.1 へ提出通知を追加**。**新しいDB表は追加していない**（`PRAGMA user_version` は 4 のまま・JSON 11分類・回答スキーマ版 1 も不変）。**受付API・店舗入力画面・必須契約・書き出し契約・保持削除・バックアップの規則は一切変更していない。** |
| **v1.13（R13）** | 2026-08-29 | 改定（HP-ONBOARDING-4H-3・代表承認）。**§10.11 の APP_ROOT 解決経路を実機で確定**した。v1.12 は「どちらを正式採用するかは 4H で XServer 実機を確認して決める」としていたが、4H-3 の本番配置作業でこれを実施し、**XServer 本番は「docroot の祖先の `private/hp-intake/src` を探す経路」を正式採用**と定めた。`.user.ini` の `auto_prepend_file` は**空のままとする**。**実装の探索順（定数 → 環境変数 → リポジトリ配置 → 祖先探索）は変更していない**。定数・環境変数・リポジトリ配置の各経路は CLI・ローカル確認・他環境用として**実装に残し**、雛形 `private/app-root-bootstrap.example.php` も**削除していない**。**これは「XServer では `auto_prepend_file` が使えない」という判断ではない。** 実際の失敗原因は、設定した**絶対パスでファイルを開けず** `Failed opening required` となったことであり、**正しいパスであれば動く可能性は否定しない**。その上で採用しない理由を記録する。`auto_prepend_file` は **require 相当**で実行され、prepend を開けなかった時点で**主スクリプトが実行されない**。実測では当該サブドメインの全 PHP 応答が **HTTP 500・本文 0 バイト**となり（`display_errors = Off` のため外部への漏えいは 0 件）、設定値の誤りが**単一障害点**になることが確かめられた。祖先探索を選ぶ理由は、**実機で成立済みであること**と、**APP_ROOT の解決に固定の絶対パスを使わないこと**の2点である（`error_log` など **APP_ROOT 解決以外の設定では絶対パスを使っている**）。**§12.2-4 から `auto_prepend_file` の可否を完了へ更新した**。**データ構造・受付API・店舗入力画面・必須契約・書き出し契約・保持削除・バックアップの規則は一切変更していない**（`PRAGMA user_version` は 4 のまま・JSON 11分類・回答スキーマ版 1 も不変）。**実装コード・テストコードは本改定で変更していない。** |
