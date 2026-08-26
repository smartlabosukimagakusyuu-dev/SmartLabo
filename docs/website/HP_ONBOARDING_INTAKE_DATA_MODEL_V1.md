# 店舗向けHP導入フォーム データモデル・token設計 v1

```text
STATUS      : APPROVED / IMPLEMENTATION NOT STARTED
VERSION     : v1.1（R1）
DATE        : 2026-08-26
工程        : HP-ONBOARDING-4A ／ HP-ONBOARDING-4A-R1（AI Sales 分離・Operations 境界確定）
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
| 7 | **平文 token 列を作らない**（§4） |
| 8 | **カード情報・秘密値・Stripe情報・公開承認の列を作らない**（§8） |

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

索引: `INDEX(intake_case_id, submitted_at)`

> ★不足**項目名**は intake_answers 側の判定結果として都度算出し、履歴には**件数だけ**残す。
> 履歴が回答のスナップショットにならないようにするため。

### 2.5 E. intake_audit_events（監査ログ）

**token 平文・回答本文・氏名・メール・電話・住所・Drive URL を保存しない。**

| 列 | 型 | NULL | 内容 |
|---|---|---|---|
| `id` | INTEGER PK AUTOINCREMENT | — | |
| `intake_case_id` | INTEGER REFERENCES intake_cases(id) | 可 | token 不一致時など、案件を特定できない場合は NULL |
| `event_type` | TEXT NOT NULL | 不可 | `token_issued` / `token_revoked` / `token_accepted` / `token_rejected` / `answer_saved` / `submitted` / `admin_viewed` / `export_generated` / `drive_url_set` / `answers_deleted` |
| `result_code` | TEXT NOT NULL | 不可 | `ok` / `invalid` / `expired` / `revoked` / `not_found` / `rate_limited` / `conflict` |
| `ip_hmac` | TEXT | 可 | **HMAC-SHA256(IP, ip_hash_secret) の先頭32文字**。生IPを保存しない |
| `created_at` | TEXT NOT NULL | 不可 | |

索引: `INDEX(intake_case_id, created_at)` ／ `INDEX(created_at)`

> ★`token_rejected` の `result_code` は監査目的で `invalid` / `expired` / `revoked` / `not_found` を
> 区別して**記録する**が、**利用者へ返す画面は §4.6 のとおり常に同一文言**にする。
> 記録の粒度と、外部へ見せる粒度を分ける。

### 2.6 提案：追加しないテーブル（判断の記録）

| 候補 | 判断 | 理由 |
|---|---|---|
| メニュー・スタッフ・写真の正規化テーブル | **作らない** | 案件内でしか使わず横断集計もしない。JSON で足りる（§2.3） |
| 管理画面のセッション/アカウント表 | **本工程では定義しない** | 管理画面の認証方式が未確定（§12-1）。4D で確定してから定義する |
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
| `promotion.industry` | §8 | 必須 | object | — | 混在 | 可 | — | `{}` |

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
| **Smart Labo が案件に持つ** | `case_number` / `contract_type` / `status` / Drive URL / 期限 | `intake_cases` |
| **intake に持たない** | C-01〜C-16（契約・請求・Stripe参照）／公開承認 | **Smart Labo Operations 側**（未実装の間は §1.3 の標準管理票） |

画面上でも、店舗入力欄と Smart Labo 設定欄は**同じ画面に混在させない**（4C で分ける）。

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

### 4.2 URL の形

```text
https://intake.smartlaboworks.com/f/<token>
（<token> は base64url 43文字。案件番号・店舗名をURLへ含めない）
```

- **案件番号や店舗名をURLへ入れない**（列挙・推測の手がかりを与えない）。
- token 単体から案件を解決する（`token_hash` で検索）。

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

### 4.5 検証の順序（4B で実装）

```text
1. HTTPS か（違えば 301）
2. token が 43文字の base64url か（形式不一致は即 reject）
3. rate limit（IP単位・token検証失敗は別枠で厳しく）
4. SHA-256 → token_hash 検索
5. revoked_at が NULL か
6. expires_at > 現在時刻か
7. 案件の status が操作を許すか（§5）
→ すべて満たしたときのみ last_used_at を更新して続行
```

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
| **Webサーバのアクセスログ** | URL パスに token が残る | **URL から token を除いた形へ書き換える**（後述） |
| **アプリログ** | 実装ミスで平文が出る | ログ出力関数で `/f/[A-Za-z0-9_-]{43}` を `[REDACTED]` へ置換する共通処理を必須にする |
| **外部解析・外部画像・第三者スクリプト** | Referer や計測でURLが外部へ出る | **intake の画面に外部リソースを一切読み込まない**（自ドメインのみ。CSPで強制。§10.5） |
| **エラー画面・スタックトレース** | 例外表示にURLが混ざる | 詳細を外部へ出さない。固定文言のみ（§10.6） |

**アクセスログ対策の具体（4B で実装）**
- token を**URLパスから受け取った直後**に、アプリ側で「案件を特定できない形」へ正規化した
  パス（例 `/f/-`）でのみ内部処理・ログ記録を行う。
- XServer 側のアクセスログはアプリから制御できないため、
  **token をパスではなく POST body で受け渡す2段構成**を 4B の設計候補とする。
  （`/f/<token>` はワンタイムで受け、直ちに Cookie（HttpOnly / Secure / SameSite=Lax）へ
   セッション識別子を移し、以後のURLに token を含めない）
  ★この2段構成の採否は 4B で確定する。**データモデルはどちらでも成立する**。

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
| `reviewed` | `locked` | Smart Labo |
| `locked` | `closed` | Smart Labo |
| 任意 | `closed` | Smart Labo（中止案件） |

### 5.2 状態別に許可する操作

| 状態 | 店舗：閲覧 | 店舗：入力・途中保存 | 店舗：提出 | Smart Labo：確認 | Smart Labo：書き出し | token |
|---|---|---|---|---|---|---|
| `draft` | ○ | ○ | ○ | ○ | △（暫定） | active |
| `submitted` | ○（**読み取りのみ**） | × | × | ○ | ○ | active |
| `needs_revision` | ○ | ○ | ○（再提出） | ○ | ○ | active |
| `reviewed` | ○（読み取りのみ） | × | × | ○ | ○ | active |
| `locked` | ×（URL失効） | × | × | ○ | ○ | **expired / revoked** |
| `closed` | × | × | × | ○（§9.4 の残余のみ） | × | revoked |

### 5.3 token の状態

`intake_tokens` の列から導出する（状態列を別に持たない＝二重管理を作らない）。

| token状態 | 判定 |
|---|---|
| `active` | `revoked_at IS NULL` かつ `expires_at > now` |
| `expired` | `revoked_at IS NULL` かつ `expires_at <= now` |
| `revoked` | `revoked_at IS NOT NULL` |

★利用者へは3状態を**区別せず**、常に §4.6 の同一文言を返す。

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
- 自動保存の間隔は**運用値として未確定**（§12-8）。データモデルは間隔に依存しない。

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

### 6.4 二重送信防止

- 「送信」は押下直後にボタンを無効化する（表示上の対策）。
- サーバー側は **`status` による冪等化**で守る。
  `draft` / `needs_revision` 以外からの提出要求は、状態を変えずに「提出済みです」を返す。
- 提出要求には**クライアント生成の submission_id（UUID）**を付ける。
  同一 `submission_id` の再送は `intake_submission_history` を増やさず、
  最初の結果をそのまま返す（4B で実装）。

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

> ★暗号化は「DBファイルが単体で漏れた場合に、共有先の入り口を即座に晒さない」ための
> 多層防御である。**アクセス制御の代わりではない。**

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

### 9.1 保持期間

| 対象 | 保持期間 | 起算 |
|---|---|---|
| `intake_answers`（回答本文） | **公開完了後6か月** | `intake_cases.closed_at` |
| `intake_cases`（案件メタ） | 同上（削除後は §9.4 の残余のみ） | 同上 |
| `intake_tokens` | 公開完了時または編集期限終了時に**失効**。行は §9.3 の削除時まで残す | — |
| `intake_submission_history` | 案件削除まで（件数のみで本文を持たないため） | — |
| `intake_audit_events` | **13か月**（不正アクセス調査に足る期間） | `created_at` |
| Drive の写真本体 | 案件ごとに保持期間と削除予定日を記録（§7.1-9） | 公開完了 |

### 9.2 token の失効タイミング

- 提出後の編集可能期間（7日）終了時
- `locked` へ遷移した時
- `closed` へ遷移した時
- 再発行した時（旧 token を即時失効）
- 漏えい・誤送信が判明した時（即時）

### 9.3 削除の実施

| # | 手順 |
|---|---|
| 1 | `retention_delete_due`（= `closed_at` + 6か月）に達した案件を管理画面で一覧表示する |
| 2 | 削除前に **§9.4 の継続保持対象が Smart Labo Operations 側（未実装の間は §1.3 の標準管理票）へ移されていること**を確認する |
| 3 | `intake_answers` の行を削除し、`intake_cases.deleted_at` に**削除実施日**を記録する |
| 4 | `intake_tokens` の当該案件行を削除する |
| 5 | Drive の当該フォルダを削除し、削除実施日を案件記録へ残す |
| 6 | 監査ログに `answers_deleted` / `ok` を記録する（**削除した値は書かない**） |
| 7 | 削除は**自動実行しない**。Phase 1 は人が確認してから実行する |

**削除要請時の処理**（保持期間内でも）
- 店舗または本人（スタッフ・被写体）から削除要請があった場合、
  上記 3〜6 を**保持期間を待たずに実施**する。
- 要請日・要請者の別（店舗／本人）・実施日を案件記録へ残す（**要請内容の本文は残さない**）。

### 9.4 削除後も残す情報（継続保持）

回答本文の削除後も、次は **Smart Labo Operations 側へ移して保持**する
（Operations 完成前は §1.3 の標準管理票）。**AI Sales へは保存しない。**

- 法的同意の証跡（`rights.confirmations[]` の code / agreed / agreed_at / agreed_by）
- スタッフ本人の掲載同意（S-13 / S-14）
- 被写体の掲載同意（IMG-04）
- 公開承認
- 素材権利台帳の要点（枚数・提供者区分・権利確認の有無）

> ★intake 側には `intake_cases` の識別情報（case_number / 状態 / 日付）と
> `intake_audit_events` のみが残る。**回答本文・氏名・連絡先は残さない。**

### 9.5 バックアップ

| # | 規則 |
|---|---|
| 1 | Phase 1 は**日次・手動**（自動化しない） |
| 2 | 保存先は**ドキュメントルート外**。Web から到達できる場所へ置かない |
| 3 | バックアップファイルにも `private/` と同等の権限（ディレクトリ 700 / ファイル 600）を与える |
| 4 | **アクセスできる主体は代表と制作担当のみ**。店舗・外注・第三者に渡さない |
| 5 | バックアップの保持は**直近30日分**。それ以前は削除する |
| 6 | バックアップを外部クラウド（Drive を含む）へ置かない（Drive は写真専用・§1） |

### 9.6 SQLite の取り扱い（-journal / -wal / -shm）

| # | 規則 |
|---|---|
| 1 | journal mode は **`delete`（既定）を採用する**。WAL にしない |
| 2 | 理由: 共有レンタルサーバー環境では `-wal` / `-shm` の権限・削除漏れが事故になりやすく、<br>1〜5店舗の書き込み頻度では WAL の利点が無いため |
| 3 | バックアップ時に `-journal` が存在する場合は**コピーしない**。<br>先に接続を閉じ、`-journal` が消えた状態でコピーする |
| 4 | ファイルコピーではなく **`VACUUM INTO 'backup.sqlite'`** を第一手段とする<br>（実行中でも整合したコピーを1ファイルで得られる） |
| 5 | `VACUUM INTO` が使えない環境では、`.backup` 相当の手順を用いる。<br>**稼働中の生ファイルコピーは行わない** |

### 9.7 バックアップの整合性確認

取得直後に**必ず**次を実行し、結果を記録する（値のみ・回答本文は出さない）。

```sql
PRAGMA integrity_check;    -- 期待値: ok
PRAGMA foreign_key_check;  -- 期待値: 0行
```

- どちらかが期待値でない場合、そのバックアップを**採用しない**。
  直前の正常なバックアップを保持したまま、原因を調査する。
- 確認結果（日付 / integrity_check / foreign_key_check の件数）を運用記録へ残す。

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
                         img-src 'self' data:; connect-src 'self';
                         form-action 'self'; frame-ancestors 'none'; base-uri 'none'
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Referrer-Policy: no-referrer
Cache-Control: no-store, no-cache, must-revalidate
Pragma: no-cache
Strict-Transport-Security: max-age=31536000
```

- **HTTPS を強制**する（HTTP は 301 で HTTPS へ）。
- **CORS ヘッダーを出さない**（他オリジンから使えない構成にする）。

### 10.5 外部リソースの禁止

- intake の画面から**外部ドメインへ一切リクエストしない**。
  外部フォント・外部CDN・外部画像・アクセス解析・第三者スクリプトを読み込まない。
- 理由: Referer・DNS・計測を通じて **token を含むURLが外部へ渡る**経路を根絶するため。
- CSP の `default-src 'self'` でこれを機械的に強制する。

### 10.6 エラー表示

- 外部（店舗）へは**固定文言のみ**。例外メッセージ・スタックトレース・
  SQL・ファイルパス・設定値を出さない。
- token 関連は §4.6 の同一文言・404 に統一する。
- 詳細はサーバーのエラーログへ**識別子のみ**を残す（本文・PIIを出さない）。

### 10.7 ログに出してはいけない情報

| 出さない | 出してよい |
|---|---|
| token 平文 | case_number |
| 回答本文（全JSON） | event_type |
| 氏名・掲載名・実名 | result_code |
| メールアドレス（公開用・内部用とも） | 発生日時 |
| 電話番号 | HMAC化IP |
| 住所 | schema_version |
| Drive フォルダURL | 件数（充足数・不足数） |
| 同意記録の内容 | HTTPステータス |

- ログ出力の共通関数で `/f/[A-Za-z0-9_-]{43}` と既知の秘密値パターンを
  `[REDACTED]` へ置換する（マスクは出力の直前に一箇所で行い、実装ごとに散らさない）。
- 通知メールにも**案件番号のみ**を書く。本文・個人情報・Drive URL を入れない。

### 10.8 管理画面

- **認証必須。** 未認証で到達できる管理画面を作らない。
- 認証方式は未確定（§12-1）。**4D の着手前に確定する**。
- 管理画面にも CSRF・レート制限・監査ログ（`admin_viewed` / `export_generated`）を適用する。
- 管理画面は店舗向けURLとパスを分ける（`/admin/` 配下）。

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
| 11 | 複数 Smart Labo 管理者の権限管理 | 認証方式が未確定（§12-1）。Phase 1 は単一運用 |

---

## 12. 未確定事項（運用値・後工程の判断）

**データモデル上の矛盾は残していない。以下は運用値と実装手段の未確定である。**

| # | 事項 | 影響 | 確定させる工程 |
|---|---|---|---|
| 1 | **管理画面の認証方式** | §10.8。テーブル定義（セッション/アカウント）も未定義のまま | **4D 着手前** |
| 2 | **XServer の SQLite / PDO 対応の実測** | `pdo_sqlite` 拡張の有無・`VACUUM INTO` の可否 | 4B 着手前（代表のサーバーパネル確認） |
| 3 | **intake.smartlaboworks.com のサブドメイン・SSL 設定** | 到達性そのもの | 4H（代表作業） |
| 4 | **既存 contact.php の本番配置状況** | 会社サイトの問い合わせが機能しているか。intake より優先度が高い可能性 | 代表確認（本件と独立） |
| 5 | **Google Drive の実フォルダ命名規則** | `drive_folder_label` の中身 | 4C 着手前 |
| 6 | **本番バックアップ先** | §9.5 の具体（XServer 内／代表PC／その他） | 4G |
| 7 | **メール送信方式** | mail() か SMTP か（contact-api / xserver-form のどちらの方式に寄せるか） | 4B |
| 8 | **具体的な rate limit 値・自動保存間隔** | §10.2・§6.1。**データモデルは値に依存しない** | 4B |
| 9 | **1案件あたりの配列上限値** | §3 に**設計既定値**（menus 60 / staff 30 / images 60 等）を置いた。<br>運用実績で調整する余地を残す | 4B（既定値のまま進めてよい） |

> ★#9 について: 本書の上限値は**実装既定値として確定**しており、
> これに従えばモデルは成立する。Phase 1 の運用記録（仕様書 §20）を見て
> 次版で見直す、という意味での「未確定」である。

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
