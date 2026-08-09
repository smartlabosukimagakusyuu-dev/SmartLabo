# WEB-SALES-1R — 旧 smartlabo-works SALES実装 読み取り専用監査＋Lite移植可否判定

- 実施日: 2026-08-09
- 種別: **読み取り専用調査・設計工程**（実装0・移植0・製品修正0・本番変更0）
- ブランチ: `feature/web-sales-1r-legacy-reuse-audit`（`feature/web-sales-1-contract-audit` `6732b23` 基点）
- 前工程: WEB-SALES-1（`6732b23`／[WEB_SALES_1_CONTRACT_AUTOMATION_AUDIT.md](WEB_SALES_1_CONTRACT_AUTOMATION_AUDIT.md)）
- 代表承認: 旧リポジトリ `C:\Users\user\Desktop\smartlabo-works` の読み取り専用監査を本工程に限り明示承認

> ## ⚠️ 本監査の結論（先に読む）
>
> **判定: WEB-SALES-1B は Go。WEB-SALES-2 は条件付きGo（代表判断2件で解除）。**
>
> **1. 旧実装は実在した。設計・ロジックとして高い再利用価値がある。**
> SALES-2/3/3R/3S/4 の5工程が実装され、SALES関連テスト98件を含む合計1313件のテストが通っている。
>
> **2. WEB-SALES-1の重大な認識誤りを1件訂正する。**
> WEB-SALES-1では「実Stripeでの疎通確認は未実施」と報告した（PROJECT_BIBLE 15番 v4.0 の記載に基づく）。
> **これは誤りである。** その後の工程 **SALES-3S（2026-07-29）で、Stripeサンドボックスに対する実機E2E
> （テストカード決済 → Stripe CLI経由の実Webhook受信 → 署名検証 → `payment_required`→`active` → 業務機能解放 →
> 再送冪等 → 署名不正400）が完了している。** 最大の技術的不確実性は既に解消されている。
>
> **3. ただし「コードをそのままコピーする」ことはできない。**
> 旧＝`node:http` 素実装の単一ファイル `server.js`（5563行、`handleAPI` 1関数で約3700行）、
> 現行Lite＝Express 5 ＋ 17ルーター構成。認証も旧＝**インメモリMapセッション**、Lite＝**DB保存セッション**。
> 利用者台帳も旧＝`tenant_users`（＋環境変数テナントの二系統）、Lite＝`users`（単一系統）。
> **再利用の実体は「設計・ロジック・テストケースの移植」であり、ファイル単位のコピーではない。**
>
> **4. 日数の短縮は限定的。価値は短縮よりリスク低減にある。**
> 再見積りは 楽観34日／標準48日／安全63日。WEB-SALES-1の 41〜62日 に対し、標準値での短縮は数日にとどまる
> （Liteにテスト基盤が無いことと、新たに検出した欠陥S9の対応が増えるため）。
> 一方 **WEB-SALES-4（決済）は 8〜12日 → 4〜8日** と実質的に短縮し、かつ最大の未知が消える。
>
> **5. 旧実装にも重大な欠陥が4件ある。移植時に必ず直す。**
> ①provisioningにトランザクションが無い ②契約人数上限の強制がゼロ ③招待メールの送信ワーカーが存在しない
> （＝本番でセルフ申込しても管理者がログインできない）④`role` が定義だけで一切参照されず、一般利用者が
> 決済もAPIキー登録も実行できる。

---

## 1. 調査日時

2026-08-09。読み取り専用調査のみ。

---

## 2. 調査したrepo／branch／commit

### 2-1. 着手前確認（3リポジトリ・読み取りのみ）

| | SmartLabo | smartlabo-works-lite | **旧 smartlabo-works** |
|---|---|---|---|
| パス | `C:\Users\user\Desktop\SmartLabo` | `C:\Users\user\Desktop\smartlabo-works-lite` | `C:\Users\user\Desktop\smartlabo-works` |
| 着手時branch | `feature/web-sales-1-contract-audit` | `feature/release-8k-a-8l-a-deploy` | `feature/product-1-dashboard` |
| 着手時HEAD | `6732b23f94d94e709e07f723492302030cbce824` | `28e2c60bfc0f2fa2c5052cfcaa7ccd0f1181ff3a` | `721a6f2465019038795e8ab81b7a9530eb994c56` |
| origin/master(main) | `1252347b4a9e75621402caffc9bede1b8c312fd1` | `d8c15584917bf589890b6922779fc5374ccbd41e` | `main` = `1cecf17` |
| origin/website-v3 | `3ef57db99cd091776257a7cd939d342ce5a6feba` | ― | ― |
| working tree | clean | clean | **未追跡2件のみ**（後述） |
| stash | なし | なし | なし |
| worktree | 1件 | 1件 | 1件 |

**想定参考値との照合**: 指示書§3の想定値（master `1252347…`／website-v3 `3ef57db…`／WEB-SALES-1監査commit `6732b23…`／Lite origin/master `d8c1558…`）は**すべて一致**した。

### 2-2. 旧リポジトリの working tree について（停止条件の判定）

旧リポジトリの `git status --porcelain` は次の2行のみだった。

```
?? SmartLaboWorks_ProductBook_v1.0_draft.pdf
?? SmartLaboWorks_ProductBook_v1.0_draft.pptx
```

**追跡ファイルの変更（`M`）・ステージ済み変更・削除はゼロ。未追跡ファイル2件のみ。**

この2件は旧リポジトリ自身の記録に説明がある — `docs/reviews/SALES_2_SIGNUP_PROVISIONING.md` 第14節「未追跡のProductBook 2件は代表指示によりコミット対象外（`.gitignore`も追加しない）」。

指示書§15の停止条件「旧repoのworking treeがdirty」は、追跡ファイルが改変されている状態を指すものと解釈し、**既知・説明済みの未追跡ファイル2件は停止条件に該当しないと判断して調査を継続した。** これらのファイルは開いておらず、一切触っていない。

### 2-3. 調査対象commit

**`c25fff4`（SALES-4 = SALES系列の最終commit）を正本として調査した。**

---

## 3. 読み取り専用遵守記録

### 3-1. 実際に行った操作

| 操作 | 内容 |
|---|---|
| `git branch --show-current` / `rev-parse` / `status --porcelain` / `stash list` / `worktree list` / `remote -v` / `branch -a` | 状態確認のみ |
| `git log --oneline` / `git show --stat` / `git merge-base --is-ancestor` / `git rev-list --left-right --count` | 履歴の読み取りのみ |
| `git ls-tree --name-only -r <commit>` | ファイル一覧の読み取りのみ |
| **`git archive c25fff4 \| tar -x -C <scratchpad>`** | commit c25fff4 の内容を**セッション用一時ディレクトリへ読み取り専用に展開**。リポジトリの状態を一切変更しない |

### 3-2. 行っていない操作

**checkout／branch切り替え／commit／amend／push／merge／rebase／stash／ファイル変更／ファイル削除／migration実行／npm install／依存更新／サーバー起動／テスト実行／DB接続／本番接続。**

### 3-3. 変更0の証拠

`git archive` 実行の**直後**に旧リポジトリの状態を再確認し、着手前と完全に同一であることを確認した。

```
--- 旧repo状態 再確認（無変更であること） ---
feature/product-1-dashboard
721a6f2465019038795e8ab81b7a9530eb994c56
?? SmartLaboWorks_ProductBook_v1.0_draft.pdf
?? SmartLaboWorks_ProductBook_v1.0_draft.pptx
```

branch・HEAD・working tree のいずれも着手前と一致している。

### 3-4. 調査手法についての補足

コード本文の精読は、旧リポジトリではなく `git archive` で展開した**一時ディレクトリのコピーに対して**行った。これにより、調査中に誤ってファイルを変更する経路そのものを断った。調査を分担した副エージェントにも、旧リポジトリへのアクセスとgitコマンド実行を明示的に禁止している。

---

## 4. 旧実装の全体像

### 4-1. アーキテクチャ（現行Liteとまったく異なる）

| | 旧 smartlabo-works `c25fff4` | 現行 Lite `28e2c60` |
|---|---|---|
| HTTPサーバー | **`node:http` 素実装**。`server.js` **5563行**、`handleAPI` 1関数で約**3700行**。ルーティングは `if (pathname === '...' && req.method === '...')` の直列比較 | **Express 5**。`server/app.js` ＋ **17ルーター**に分割 |
| 依存パッケージ | `@napi-rs/canvas` / `pdfjs-dist` / **`stripe@22.3.2`** の3つのみ（Webフレームワークなし） | `express` / `react` / `react-dom` / `react-router-dom` / `lucide-react` / `@napi-rs/canvas` / `pdfjs-dist`（**`stripe` なし**） |
| Node要求 | `>=22.13.0` | `>=22.5` |
| DBドライバ | **`node:sqlite`（`DatabaseSync`）** | **同左** ✅ 一致 |
| フロントエンド | 素のHTML＋`public/js/`（`app.html` ほか） | **React SPA（Vite）** |

**DBドライバが一致している点は大きい。** SQLのDDL・クエリはそのまま意味を持つ。一方、HTTPレイヤとフロントエンドは互換性がまったくない。

### 4-2. SALES実装の位置（重要）

**SALES実装は origin へ push されていない。ローカルbranchにのみ存在する。**

origin にあるのは `main` / `develop` / `feature/auth-foundation` / `feature/auth-hardening` / `feature/db-path` / `feature/miraie-crm-beta` のみで、**SALES系branchは1本もリモートに存在しない。**

```
main (1cecf17)
  └─ … 118 commits …
      ├─ c568886 docs(6-11B)
      ├─ 77f55e2 SALES-2: add signup provisioning, invitation foundation and payment gating
      ├─ cce02cf SALES-3: add Stripe Checkout, webhook handling and contract activation
      ├─ c25843b SALES-3R: adopt official Stripe SDK, add payment grace period and checkout screens
      ├─ 9029182 SALES-3R: verify live Stripe sandbox connectivity and fix managed payments handling
      ├─ ba71137 SALES-3S: verify payment webhook flow end-to-end and fix AI setup redirect
      ├─ c25fff4 SALES-4: add AI first-run setup wizard with encrypted key storage   ← 本監査の正本
      └─ … LIGHT-5.x … 721a6f2 PRODUCT-1（現HEAD）
```

- SALES chain は**一直線**（マージコミットなし）
- `c25fff4` は現HEAD `721a6f2` の**祖先**（＝現HEADはSALES実装を含む）
- `c25fff4` は `main` にも `develop` にも**未マージ**

**→ リスク: この実装はこのPC上のローカルbranchが唯一の写しである。**（第21節 R-1）

---

## 5. 関連branch／commit

| branch | tip | 内容 | remote |
|---|---|---|---|
| `feature/sales-2-provisioning` | `9029182` | SALES-2 → SALES-3 → SALES-3R → SALES-3R実疎通 | **なし** |
| `feature/sales-3s-webhook-e2e` | `ba71137` | ＋SALES-3S（実機E2E） | **なし** |
| `feature/sales-4-ai-setup` | `c25fff4` | ＋SALES-4（AI初期設定ウィザード） | **なし** |
| `feature/product-1-dashboard` | `721a6f2` | 現HEAD。SALES-4を含み、さらにLIGHT-5.x（プラン/フィーチャーゲート）とPRODUCT-1を積む | **なし** |

| commit | 工程 | 変更規模 |
|---|---|---|
| `77f55e2` | SALES-2 申込provisioning・招待基盤・支払いゲート | 26 files, +3168 |
| `cce02cf` | SALES-3 Stripe Checkout・Webhook・契約有効化 | 22 files, +2201 |
| `c25843b` | SALES-3R 公式SDK採用・14日猶予・決済画面 | 25 files, +1284 |
| `9029182` | SALES-3R 実Stripeサンドボックス疎通確認と修正 | 5 files, +60 |
| `ba71137` | SALES-3S 決済〜Webhook〜active 実機E2E | 8 files, +493 |
| `c25fff4` | SALES-4 AI初期設定ウィザード（暗号化キー保存） | 22 files, +2322 |

---

## 6. 関連ファイル一覧

### 6-1. サービス層 `src/services/sales/`（9本）

| ファイル | 役割 |
|---|---|
| `signupValidation.js` | 入力検証の正 |
| `pricing.js` | 料金の正・サーバー再計算 |
| `salesResponse.js` | 共通レスポンス形式 |
| `provisioningService.js` | 会社・管理者・招待の作成と招待受諾 |
| `contractState.js` | 契約7状態と遷移規則・14日猶予の判定 |
| `accessPolicy.js` | `payment_required` 中の許可リスト制御 |
| `stripeClient.js` | Stripe SDK呼び出しの集約 |
| `stripeWebhook.js` | 自前署名検証（**テスト補助・本番未使用**） |
| `billingService.js` | Webhookイベント適用・契約有効化 |

### 6-2. リポジトリ層（SALES新設5本）

`signupApplicationRepository.js` / `tenantUserRepository.js` / `invitationRepository.js` / `invitationEmailOutboxRepository.js` / `stripeEventRepository.js`
（既存 `companyRepository.js` / `auditLogRepository.js` も大幅拡張）

### 6-3. AI初期設定 `src/services/setup/`（SALES-4・4本）

`aiSetupService.js` / `openaiSetupClient.js` / `secretCrypto.js` / `setupSampleService.js` ＋ `src/repositories/companyAiSettingsRepository.js`

### 6-4. 画面（素のHTML）

`public/customer/billing-complete.html` / `billing-cancelled.html` / `ai-setup.html`

### 6-5. 文書

`docs/reviews/SALES_2_SIGNUP_PROVISIONING.md` / `SALES_3_STRIPE_CHECKOUT.md` / `SALES_3R_STRIPE_LIVE_TEST.md` / `SALES_3S_STRIPE_WEBHOOK_E2E.md` / `SALES_4_AI_SETUP.md`

### 6-6. 検証用スクリプト

`scripts/sales3s-test-server.js`（隔離DB＋テストキー以外では起動拒否）

---

## 7. migration／schema一覧

### 7-1. 旧のmigrationの仕組み

**JavaScriptの配列**（`src/services/storage/migrations.js`）。`{ version, name, up(db) }` を並べ、`db.js` のランナーが `schema_migrations` で適用済みを管理し、version昇順に未適用のみ `BEGIN`/`COMMIT` で適用する。

**現行Liteは番号付き `.sql` ファイル方式**（`server/db/migrations/001_*.sql` 〜 `016_*.sql`）。**仕組みが違う。**

### 7-2. 旧の全21 migration

| ver | name | | ver | name |
|---|---|---|---|---|
| 1 | create_companies_table | | 12 | add_customer_followup_foundation |
| 2 | create_crm_contacts_table | | 13 | create_ai_usage_events_table |
| 3 | add_crm_contacts_company_status_index | | 14 | create_ai_usage_daily_table |
| 4 | create_audit_logs_table | | 15 | create_properties_table |
| 5 | create_tasks_table | | 16 | extend_crm_contacts_for_imports |
| 6 | create_workflow_recipe_tables | | 17 | create_contact_context_notes |
| 7 | create_workflow_run_tables | | **18** | **create_sales_provisioning_tables** |
| 8 | add_workflow_columns_to_tasks | | **19** | **create_stripe_billing_tables** |
| 9 | add_metadata_to_audit_logs | | **20** | **add_payment_grace_period** |
| 10 | create_feedback_reports_table | | **21** | **create_company_ai_settings** |
| 11 | create_feedback_email_outbox_table | | | |

### 7-3. SALES関連テーブルの実カラム

**`signup_applications`**（v18）
`id` PK / `client_request_id` NOT NULL（**UNIQUE索引**） / `company_name` / `company_kana` / `postal_code` / `address` / `company_tel` / `contact_email` / `admin_name` / `admin_email` / `additional_accounts` INTEGER DEFAULT 0 / `status` CHECK IN (`application_pending`,`provisioning`,`provisioned`,`failed`) / `company_id` REFERENCES companies(id) / `failure_code` / `created_at` / `updated_at`

**`tenant_users`**（v18）
`id` PK / `company_id` NOT NULL FK / `email` / `name` / `role` CHECK IN (`admin`,`member`) / `status` CHECK IN (`invited`,`active`,`disabled`) / `password_hash`（**作成時NULL＝ログイン不可**） / `activated_at` / `created_at` / `updated_at`
**UNIQUE INDEX (company_id, email)** ← ★会社スコープ

**`invitations`**（v18）
`id` PK / `company_id` FK / `tenant_user_id` FK / **`token_hash` NOT NULL UNIQUE** / `purpose` CHECK IN (`initial_admin`,`member`) / `expires_at` / `used_at` / `revoked_at` / `created_at`

**`invitation_email_outbox`**（v18）
`id` PK / `invitation_id` UNIQUE FK / `company_id` FK / `delivery_status` CHECK IN (`pending`,`processing`,`sent`,`retry`,`failed`) / `attempt_count` / `next_attempt_at` / `sent_at` / `last_error_code` / `created_at` / `updated_at`
**※本文・件名・招待URLは保存しない**（平文トークンをDBに残さないため）

**`stripe_webhook_events`**（v19）
`id` PK / **`stripe_event_id` NOT NULL UNIQUE** / `event_type` / `company_id` FK / `event_created_at` INTEGER / `status` CHECK IN (`received`,`processed`,`skipped`,`failed`) / `error_code` / `received_at` / `processed_at`
**※payload本体は保存しない**

**`company_ai_settings`**（v21）
`api_key_encrypted` / `api_key_masked` ほか。**暗号鍵は環境変数のみ、DBに入れない**

**`companies` への追加列**
v18: `contract_status` DEFAULT 'active' / `signup_application_id` / `additional_accounts` / `provisioned_at` / `activated_at` / `terms_agreed_at` / `terms_agreed_by`
v19: `stripe_customer_id`(UNIQUE) / `stripe_subscription_id`(UNIQUE) / `stripe_checkout_session_id` / `stripe_payment_status`
v20: `payment_grace_expires_at` / `payment_suspended_at`

**`invoices_mirror` 相当のテーブルは存在しない。**

### 7-4. ★migration番号の衝突判定

**番号そのものは衝突しない。** 旧は 18〜21、Liteは 001〜016 で、Liteの次は 017 から始まる。

**しかし内容はそのまま適用できない。** 旧migration 18 は「旧の v1 で作られた `companies`」を前提に `ALTER TABLE` する。Liteの `companies` は `002_create_auth_tables.sql` で作られ、**カラム構成が違う**。

| | 旧 `companies`（v1） | Lite `companies`（002+003） |
|---|---|---|
| 会社名の列 | `name` | **`company_name`** |
| `status` のCHECK値 | `active` / `inactive` | **`active` / `suspend_scheduled` / `suspended`** |
| 契約関連 | v18で `contract_status` ほかを追加 | `contract_started_at` / `contract_end_at` / `license_count` が既存 |

**結論: 旧migrationのSQLは「新規テーブル作成部分（`signup_applications`／`invitations`／`invitation_email_outbox`／`stripe_webhook_events`）のDDL」だけが流用可能で、`ALTER TABLE companies` 部分と `tenant_users` はLite向けに書き直しが必要。**

---

## 8. API一覧（旧・実測48ルート）

| 系統 | パス |
|---|---|
| **公開（認証不要）** | `/api/signup`, `/api/invitations/accept`, `/api/stripe/webhook` |
| 認証 | `/api/auth/login`, `/api/auth/logout`, `/api/auth/me` |
| **契約・課金** | `/api/contract/status`, `/api/contract/agree-terms`, `/api/billing/checkout` |
| **AI初期設定** | `/api/setup/status`, `/provider`, `/test`, `/basics`, `/step`, `/sample`, `/complete` |
| 業務 | CRM / 物件 / タスク / ワークフロー / フィードバック / フォローアップ / 営業活動 |
| AI | `/api/ai/*` 11本 |
| 取込 | `/api/imports/*` 6本 |

**WEB-SALES-1で設計した責務分担と、旧のAPI構成はほぼ一致している。** `/api/signup`・`/api/invitations/accept`・`/api/stripe/webhook` を公開経路とし、それ以外を契約状態ガードの配下に置く構成は、そのままLiteへ持ち込める。

---

## 9. 画面一覧（旧）

| 画面 | 状態 |
|---|---|
| `public/customer/billing-complete.html` | 決済成功画面。**ポーリング方式**（2.5秒間隔・最大60秒） |
| `public/customer/billing-cancelled.html` | キャンセル画面。データ不変・再決済導線 |
| `public/customer/ai-setup.html` | AI初期設定ウィザード（6手順・415行） |

**申込フォーム画面は旧リポジトリに存在しない。** 申込画面はWebsite側（`website-v3/signup.html`）の責務であり、旧は `POST /api/signup` の受け口のみを持つ。この責務分担はSALES-2の記録にも明記されている（「`website-v2`＝販売サイト／`smartlabo-works`＝製品バックエンド」）。

**LiteはReact SPAのため、これら3画面はそのまま使えない。UIは作り直し、状態機械とポーリング方針だけを移植する。**

---

## 10. テスト一覧

| ファイル | 件数 | 内容 |
|---|---|---|
| `test/salesProvisioning.test.js` | **31件** | 契約状態遷移・競合検出・申込冪等・招待（平文非保存/使い捨て/期限切れ/再発行）・パスワード形式・利用制御（前方一致漏れ検査を含む）・**テナント分離（別会社の同一メール可・他社パスワードでログイン不可）** |
| `test/salesSignupApi.test.js` | **16件** | 実HTTP。申込200・422理由コード・**金額改ざん耐性**・二重申込409・Origin403・招待受諾・弱パスワード・**無効トークン3種の文言統一**・利用制御402→200 |
| `test/stripeBillingApi.test.js` | **37件** | モックStripeサーバーに対する統合。Checkout・Webhook・状態遷移・**冪等/順不同**・猶予・解約・**秘密情報の非露出** |
| `test/stripeWebhookSignature.test.js` | **14件** | 署名改ざん・リプレイ（過去/未来）・複数v1署名・未知スキーム無視 |
| `test/aiSetupApi.test.js` | （SALES-4） | AI初期設定 519行 |
| **合計（SALES関連）** | **98件** | |
| **リポジトリ全体** | **1313件**（成功1310・失敗0・skip3） | `node --test` |

**★このテスト資産は本監査で最も価値の高い発見の1つである。** 仕様がテストとして固定されており、Liteへ書き直す際の受入基準としてそのまま使える。

**一方、現行Liteには単体テスト基盤が存在しない。** `package.json` に `test` スクリプトがなく、`tests/` にあるのは受入スクリプト10本（`.mjs`、`run.sh` で起動）のみ。**移植にはテスト基盤の整備が別途必要**（第20節に工数を計上した）。

---

## 11. 機能別A〜D判定

判定基準: **A**=実装済みで移植候補／**B**=一部実装済みだが修正必須／**C**=設計・文書だけ／**D**=存在しない

| # | 機能 | 判定 | 根拠 |
|---|---|---|---|
| 1 | WEB申込受付 | **A** | `POST /api/signup` → `signupValidation` → `pricing` → `provisioningService`。HTTPテスト16件 |
| 2 | サーバー側料金計算 | **A** | `pricing.js` に正規値10,000/20,000/3,000。画面申告の金額を一切読まない。改ざん耐性テストあり |
| 3 | 一時申込保存 | **A** | `signup_applications`。ただし状態遷移に楽観ロックなし（下記12-4） |
| 4 | メール認証 | **D** | **存在しない。** 旧フローは「申込直後に環境作成→招待メールで到達確認」であり、独立したメール認証工程を持たない |
| 5 | 認証トークン管理 | **A** | 招待トークンとして実装。256bit random・**SHA-256ハッシュのみ保存**・7日期限・使い捨て・再発行時に旧失効・形式事前検査 |
| 6 | 代表管理者パスワード設定 | **A** | `POST /api/invitations/accept`。パスワード規則10文字以上・3種以上・弱語/メールローカル部の混入拒否 |
| 7 | 規約同意記録 | **B** | `companies.terms_agreed_at` / `terms_agreed_by` の2列のみ。**規約versionを記録していない**（WEB-SALES-1設計の `consent_records` 相当が無い） |
| 8 | Stripe Checkout | **A** | `mode: subscription`・Price IDはサーバー側・`managed_payments:{enabled:false}`＋`payment_method_types:['card']`・冪等キー付き。**実機で `cs_test_` 発行を確認済み** |
| 9 | Webhook署名検証 | **A** | **公式SDK `Stripe.webhooks.constructEvent`**。失敗400・シークレット未設定503・生ボディ受信・例外メッセージを外に出さない。**実機で署名不正400を確認済み** |
| 10 | Webhook冪等性 | **A** | `stripe_event_id` UNIQUE で「先に記録→最初の1回だけ処理」。順不同は `hasNewerProcessedEvent` で skip。**実機で受信4回→DB2件を確認済み** |
| 11 | 会社・管理者provisioning | **B** | 実装済みだが **DBトランザクションが無い**（下記12-3）。company_id/company_code はサーバー生成・推測不能 |
| 12 | payment_required制御 | **A** | `accessPolicy.js`。**許可リスト方式・完全一致・フェイルクローズ**。402/403の使い分けも設計されている |
| 13 | 決済成功後active化 | **A** | Webhook受信のみが根拠。redirect到達では遷移しない。成功画面は読み取り専用ポーリング |
| 14 | 契約人数上限 | **D** | **強制がゼロ。** `additional_accounts` は保存・料金・Stripe数量・表示にしか使われず、実ユーザー数と突き合わせる箇所が1つも無い |
| 15 | 利用者招待 | **B** | テーブル・トークン・再発行は実装済み。ただし `member` を招待するAPI・画面が無く、**メール送信ワーカーも無い** |
| 16 | 招待の承認・期限切れ・再送・取消 | **B** | 承認・期限切れ・再発行（＝旧失効）は実装。**管理者主導の単独「取消」APIは無い** |
| 17 | 月額／初期費用／追加人数計算 | **A** | `pricing.js`。`firstCharge: null`（日割りは自前計算せずStripeに委ねる）と明示 |
| 18 | キャンペーン先着50社 | **D** | 未実装。`allow_promotion_codes: false` で明示的に無効化 |
| 19 | 決済失敗・猶予期間 | **B** | 14日猶予の開始・クリア・判定は実装済み。**猶予超過→suspended／停止30日→canceled の自動遷移ジョブが無い**（判定関数のみ） |
| 20 | 解約予約・契約終了 | **B** | Webhook受信による**受動的な状態同期のみ**。自社から解約するAPI・画面・Stripeへの解約呼び出しが無い |
| 21 | 請求情報表示 | **D** | 契約状態と猶予警告のみ。請求書一覧・金額履歴・支払方法変更・Billing Portal はいずれも無し |
| 22 | 運営者向け顧客管理 | **D** | **運営者（operator）という概念自体が存在しない。** 運用手段はSSH＋sqlite3直叩きと `.env` 編集のみ |
| 23 | provisioning失敗時の再処理 | **C** | 失敗は `signup_applications.status='failed'` ＋ `failure_code` と監査ログに記録するのみ。**再処理の仕組み・画面は無い**（`provisioning_jobs` 相当が無い） |
| 24 | 監査ログ | **B** | 書き込みは実装（FKあり・保存禁止事項をコードで明文化）。**閲覧API・画面はゼロ** |
| 25 | テナント分離 | **A** | company_id は必ず「セッション→`findCompanyByCode()`→`companies.id`」。クライアント指定の company_id は**400で拒否**。テスト2件で他社アクセス不可を検証 |
| 26 | PII・秘密値保護 | **A** | カード情報非保持／トークン平文非保存／payload非保存／APIキーはAES-256-GCM暗号化・平文フォールバック無し／ログは分類コードのみ／`sk_live_` の誤用で起動停止 |
| 27 | テスト | **A** | SALES関連98件・全体1313件。仕様が固定されている |

**集計: A=13／B=7／C=1／D=6**

---

## 12. Liteとの差分

### 12-1. 基盤の比較（★は移植判断に直結）

| 項目 | 旧 smartlabo-works | 現行 Lite | 優劣 |
|---|---|---|---|
| ★HTTP層 | `node:http` 素・単一ファイル5563行 | Express 5・17ルーター | **Lite** |
| DBドライバ | `node:sqlite` | `node:sqlite` | 同等 |
| ★migration | JS配列（version 1〜21） | 番号付き`.sql`（001〜016） | 同等（方式が違う） |
| ★セッション | **インメモリ`Map`**。再起動で全消滅・多重プロセス不可・逆引き不能 | **DB `sessions`テーブル** | **Lite（決定的）** |
| ★利用者台帳 | `tenant_users` ＋ **環境変数テナントの二系統** | `users` の単一系統 | **Lite（決定的）** |
| ★email一意制約 | **UNIQUE(company_id, email)**＝会社スコープ。`toLowerCase()`で正規化統一 | `email` **グローバルUNIQUE**。DB制約は大小文字区別ありでアプリは`lower()`（S7） | **旧**（設計として） |
| ★ロール | `admin`/`member` — **定義だけでどこからも参照されない** | `operator`/`company_admin`/`user` — ミドルウェア＋サービス層で二重に強制 | **Lite（決定的）** |
| ★運営者概念 | **無し**（SSH＋sqlite3直叩き） | `operator` ロールと `/admin/companies` あり | **Lite（決定的）** |
| パスワード | scrypt・形式 `salt:hash` | scrypt・形式 `scrypt$N$r$p$salt$hash` | Lite（パラメータを形式に含むため将来の変更に強い） |
| タイミング攻撃対策 | DUMMY_HASH＋`timingSafeEqual` | ダミーハッシュ＋`timingSafeEqual` | 同等 |
| ID生成 | `randomBytes(16).hex` ＋ `company_code = 'c'+16hex` | `co-`＋uuid8 ／ `u-`＋uuid8 | 同等 |
| ★CSRF/Origin | **Origin検証＋Content-Type必須＋SameSite=Strict** の3層 | **SameSite=Lax のみ。Origin検証もCSRFトークンも無し** | **旧** |
| ★ログイン試行制限 | **IP単位 15分/5回**（インメモリ） | **無し**（S3） | **旧** |
| 監査ログ | FKあり・保存禁止事項をコードで明文化・**閲覧なし** | FKなし・個人情報を残さない方針・**閲覧なし** | 旧（FKの点で） |
| ★テスト基盤 | `node --test` **1313件** | `npm test` スクリプト無し。受入スクリプト10本のみ | **旧（決定的）** |
| フロントエンド | 素のHTML | React SPA（Vite） | 用途次第 |

### 12-2. ★最重要の構造差: 利用者台帳の二重化リスク

旧は認証が**完全な二系統**に分かれている。

```
verifyCredentials(companyId, email, password):
  ① .env の AUTH_COMPANY_ID / AUTH_EMAIL / AUTH_PASSWORD_HASH  ← 既存テナント（ミライエ）。ここで即return
  ② tenant_users テーブル                                       ← SALES新規テナント
```

環境変数ユーザーは `tenant_users` に行を持たず、`status` チェックも通らない（無効化手段は `.env` 編集＋再起動のみ）。1プロセスにつき1社1ユーザーしか定義できない。

**現行Liteはこの分裂を持たない。`users` 単一系統で、`operator`/`company_admin`/`user` の3ロールが実際に強制されている。**

**→ 判定: `tenant_users` をLiteへ持ち込むことは禁止する。** 持ち込むと `users` と二重台帳になり、権限モデル・セッション・監査ログがすべて分裂する。SALES由来のテナント利用者も**Liteの `users` に統合する**。

ただしこれには副作用がある。旧は `UNIQUE(company_id, email)`（会社スコープ）、Liteは `email` グローバルUNIQUE。統合すると**旧が許していた「別会社で同一メール」が使えなくなる**（旧のテスト `salesProvisioning.test.js` はこれを明示的に検証している）。→ **第22節 代表判断 #2**。

### 12-3. ★旧実装の欠陥1: provisioningにトランザクションが無い

`provisioningService.js` の処理順は 申込保存 → `provisioning` → 会社作成 → 管理者作成 → 招待作成 → outbox投入 → `payment_required` → `provisioned` だが、**この一連が DBトランザクションで囲まれていない。**

同じリポジトリの他のコード（`feedbackRepository` / `taskRepository`）は `db.exec('BEGIN')` を使っているのに、**sales系だけ `BEGIN` が1箇所も無い。**

失敗すると会社レコードと管理者が中途状態で残り、申込は `failed` になるが**会社の削除・ロールバックは行われない。** 再処理の仕組みも無い（判定23=C）。

旧のテストもこの穴を認識しており、`salesProvisioning.test.js` に「provisioningService の内部呼び出しを差し替えて人工的に失敗させることはしない」と明記され、**孤児レコードの検証は未実施**である。

**→ 移植時に必ず修正する。** WEB-SALES-1設計の `provisioning_jobs` ＋ 運営者画面の再実行ボタンは、この穴を塞ぐために必要である（設計の妥当性が旧実装の欠陥によって裏付けられた）。

### 12-4. 旧実装の欠陥2〜4

| 欠陥 | 内容 | 移植時の対応 |
|---|---|---|
| **契約人数上限の強制ゼロ** | `additional_accounts` と実ユーザー数を突き合わせる箇所が皆無。`listUsersByCompany` は定義のみで呼び出し元が無い | 新規実装（Lite側S2と同一の課題） |
| **招待メールワーカー不在** | `invitation_email_outbox` に積むだけで、読み出して送信するワーカーが存在しない。一方APIレスポンスは「招待メールをお送りします」と返す | 新規実装。ただし自前SMTPクライアント `src/services/email/smtpClient.js`（net/tls のみで実装・証明書検証常時有効）は**設計として移植可能** |
| **`role` が完全に未使用** | `server.js` 5563行に `admin`/`role` の出現が0件。セッションにroleが入っていない。結果として `member` が `/api/billing/checkout`（決済）も `/api/setup/*`（APIキー登録）も実行できる | 移植せず、**Liteの3ロール権限モデルを使う** |
| 申込状態に楽観ロックが無い | `signup_applications` の更新は `WHERE id = ?` のみで旧状態を条件にしていない。逆行遷移も通る。契約状態側（`WHERE id = ? AND contract_status = ?`）との実装水準の差 | 契約状態と同じ方式に揃える |

### 12-5. 旧実装で見つかった軽微な注意点

- `STRIPE_API_BASE` によるStripe接続先の差し替えが**本番コードにも残っている**（テスト用モック向け）。移植時は本番ビルドから除外するか、環境で厳格にガードする
- 順不同判定は「同一 company × **同一 event_type**」の範囲でしか行われない
- `billing_cycle_anchor` **未設定**。「毎月1日に当月分を前払い」（14番の確定仕様）が**まだ実現していない**。請求日がCheckout作成時点になる
- `config.stripe.enabled` の条件に `webhookSecret` が含まれない。**Webhook未設定でもCheckoutを開始でき、決済後に `active` にならない状態を作れる**
- `isTrustedOrigin` が `selfOrigin` をクライアント制御可能な `Host` / `X-Forwarded-Proto` から組み立てている（Nginx側での固定に依存）
- `retrieveCheckoutSession` / `retrieveSubscription` / `verifyConnectivity` / `stripeWebhook.verifyAndParse` は**未使用のデッドコード**

---

## 13. セキュリティ評価

### 13-1. 旧実装で優れている点（そのまま思想を移植すべき）

| 項目 | 内容 |
|---|---|
| テナント分離 | company_id はセッション由来のみ。**クライアント指定の company_id は無視ではなく400で拒否**（`PRODUCT_BOUNDARY.md` 第3章で原則化され、コードで実装されている） |
| 利用制御 | 許可リスト方式・完全一致・フェイルクローズ。「追加を忘れたら使えない側に倒れる」という理由がコメントに明記 |
| トークン | 256bit random・**SHA-256ハッシュのみ保存**・使い捨て・再発行時に旧失効・形式事前検査で総当たり負荷を下げる |
| カード情報 | 自社サーバーに到達しない（Stripeホスト型・PCI DSS SAQ-A相当） |
| 決済の根拠 | 署名検証済みWebhookのみ。**redirect到達では状態を変えない**（成功画面は読み取り専用ポーリング） |
| 秘密値 | APIキーはAES-256-GCM。**「鍵が無ければ機能ごと無効。平文フォールバックは持たない」** |
| ログ | Webhook失敗はコードのみ。例外メッセージに署名値が含まれうるため種別だけ返す |
| URL | 成功画面で `?session_id=` を `history.replaceState` で除去 |
| 起動時 fail-fast | `sk_live_` かつ `STRIPE_LIVE_ENABLED != true` なら**起動を停止**（意図しない本番決済の防止）。本番でのSecure Cookie未設定も起動拒否 |
| 監査ログ | 「パスワード・Cookie・セッショントークン・APIキー・顧客notes本文・リクエスト本文全体を書き込まない」をコードで明文化 |

### 13-2. 旧実装の弱点

| 弱点 | 内容 |
|---|---|
| セッションがインメモリ`Map` | 逆引きできず、停止・解約時に既存セッションを失効させられない。プロセス再起動で全消滅、多重プロセス化も不可 |
| `tenant_users.status` の再検査なし | ログイン時の1回だけ。`disabled` にしても既存セッションは最大8時間業務APIを使える |
| ロール未実装 | 一般利用者が決済・APIキー登録を実行できる |
| ログイン制限がIP単位のみ | 分散IPからの単一アカウント総当たりに無防備。アカウントロックアウトの概念が無い。カウンタはインメモリ |
| 監査ログが書き込み専用 | 運用者はsqlite3で直接開かない限り読めない |
| CSRFトークン無し | Origin＋Content-Type＋SameSite=Strict の多層で代替。`selfOrigin` の組み立てがヘッダー依存 |

### 13-3. モック・疑似処理・成功偽装の有無

**実装コードに偽装は無い。**

- `src/services/sales/` と関連リポジトリに `setTimeout` / `Math.random` / `mock` / `dummy` / `fake` の出現は**0件**。乱数はすべて `crypto.randomBytes` / `crypto.randomUUID`
- 決済成功を偽装するコードは無い。成功画面の `setTimeout` はポーリングと画面遷移のみで**サーバー状態を変えない**。タイムアウトしても「成功」と断定せず「確認中」を表示する
- モックStripeサーバーは**テストファイル内**にのみ存在
- 唯一の疑似要素は「未実装の明示」2点 — ①招待メールが実送信されない（キュー投入のみ）②開発環境限定で招待トークンをレスポンスに含める（本番では含めない）

**ただし①には実測上の齟齬がある。** APIレスポンスは「管理者の方へ招待メールをお送りします」と返すが、送信ワーカーが存在しないため実際には届かない。**これは「成功偽装」に近い挙動であり、移植時に必ず解消する。**

---

## 14. S1〜S8との関係

WEB-SALES-1で検出したLite側の既存欠陥に対し、旧実装に対策があるかを判定した。

| # | 欠陥（Lite） | 旧に対策 | Liteへ考え方を再利用 | コード移植 | 判定 |
|---|---|---|---|---|---|
| **S1** | 停止・解約後も既存セッションが有効 | **△ 部分的** | **可** | 不可 | 旧も**セッション失効の仕組みは無い**（むしろインメモリ`Map`で逆引き不能＝Liteより悪い）。ただし旧は**毎リクエストで契約状態と会社statusを再検査**しており（`accessPolicy` ガード＋`resolveActiveCompanyIdOrReject` 53箇所）、この「毎リクエスト再検査」パターンはLiteへそのまま移植する価値がある。**LiteはDBセッションを持つため、`sessions.destroyByUser` による即時失効という旧に無い正しい解を実装できる** |
| **S2** | 契約人数上限がサーバー強制されない | **✗ 無し** | ― | 不可 | **旧も強制ゼロ。** 完全新規実装 |
| **S3** | ログイン試行制限が無い | **○ あり** | **可** | 部分的に可 | IP単位15分/5回・X-Forwarded-For詐称対策付き。**ただし不十分**（アカウント単位が無い・インメモリのみ）。Liteでは**メール単位＋IPハッシュ単位**へ拡張し、DB永続化する |
| **S4** | `/api/usage` の管理者制限不足 | **✗ 無し（旧はより悪い）** | ― | 不可 | 旧は**ロールが完全に未使用**で管理者専用エンドポイントの概念すら無い。**Liteの3ロール権限モデルの方が優れている。移植しない** |
| **S5** | 本番bundleにモック従業員データ残存 | 該当なし | ― | ― | Lite固有（旧はReactでもViteでもない）。新規対応 |
| **S6** | 運営者画面にモック料金表 | 該当なし | ― | ― | Lite固有。新規対応 |
| **S7** | メール正規化の不一致 | **○ あり** | **可** | 不可 | 旧は `String(...).trim().toLowerCase()` で正規化を統一し、UNIQUE索引も正規化後の値に張っている。**この方式をLiteへ適用すればS7は解消する** |
| **S8** | 監査ログのFK／閲覧UI不足 | **△ 部分的** | **可** | 不可 | 旧の `audit_logs` は `company_id` にFKあり（Liteは無し）。**閲覧API・画面は旧にも無い。** ただし「保存禁止事項をコードで明文化する」という規律は移植価値がある |

### 14-1. ★本監査で新たに検出したLite側の欠陥

| # | 欠陥 | 実測 | 旧との比較 |
|---|---|---|---|
| **S9（新規）** | **Liteに状態変更リクエストのOrigin検証もCSRFトークンも無い** | Lite `server/` の検索で、CSRF対策は `SameSite=Lax` Cookie のみ（`server/middleware/session.js:11,38`）。Origin検証コードは存在しない | **旧には3層の対策がある**（Origin検証＋Content-Type必須＋SameSite=**Strict**）。WEB完結販売はログイン画面を公開することであり、この差は無視できない。**WEB-SALES-1B に追加すべき** |
| **S10（新規）** | **Liteに単体テスト基盤が無い** | `package.json` に `test` スクリプトなし。`tests/` は受入スクリプト10本のみ | 旧は `node --test` で1313件。**旧の98件のSALESテストを受入基準として活かすには、Lite側にテスト基盤の整備が必要** |

---

## 15. 環境先行方式との一致／不一致

指示書§9の正式候補フローと、旧実装の実測を突き合わせた。

| # | 指示書§9の正式候補 | 旧実装 | 一致 |
|---|---|---|---|
| 1 | WEB申込直後に `signup_applications` へ保存。まだ会社・ユーザーを作らない | `POST /api/signup` で申込保存 → **同一リクエスト内で会社・管理者を作成する** | **△ 差分あり** |
| 2 | メール認証後に本人確認済み申込へ遷移 | **メール認証工程が無い。** 到達確認は招待メールが兼ねる | **✗ 不一致** |
| 3 | provisioning: 会社・代表管理者作成、company_idサーバー生成、`contract_status=payment_required`、ログイン可・業務不可 | **完全に一致。** company_id/company_code はサーバー生成・推測不能。`accessPolicy` が許可リストで業務機能を402拒否 | **✅ 一致** |
| 4 | 代表管理者が決済登録 | `POST /api/billing/checkout` → Stripeホスト型Checkout | **✅ 一致** |
| 5 | 署名検証済みWebhookで `payment_required`→`active`、契約人数確定、自社顧客管理へ反映 | Webhookで `active` へ遷移（**redirect到達を根拠にしない**）。**契約人数の確定は無し。自社顧客管理も無し** | **△ 部分一致** |
| 6 | 代表管理者が購入人数の範囲内で利用者を招待 | 招待のテーブル・トークンはあるが、**`member` 招待のAPI・画面が無く、人数上限の強制も無い** | **✗ 不一致** |
| 7 | 招待された利用者がパスワード設定して登録 | `POST /api/invitations/accept` で実装済み（管理者向けとして） | **✅ 一致（機構は流用可）** |

### 15-1. 差分の評価

**中核（手順3〜5）は完全に一致している。** これは偶然ではなく、旧実装のコード内コメントに代表決定がそのまま記録されているためである。

> 【正式な販売フロー（2026-07-29 代表決定）】Webサイト申込 → 会社環境作成 → 管理者作成 → 招待メール → 初回ログイン → 利用規約同意 → payment_required → Stripe決済 → active → AI初期設定。**旧仕様（決済成功後に会社を作る）は廃止した。環境を先に作るのが正。**

**→ WEB-SALES-1の代表判断#2（申込フローの矛盾）について、「環境先行方式」を推奨する根拠が強化された。** 単に設計文書にそう書いてあるだけでなく、**実装され、テストされ、実Stripeサンドボックスで通しで動作確認まで済んでいる方式**である。

**主な差分は3点。**

1. **メール認証工程が無い**（手順1〜2）。旧は申込を受けた瞬間に会社を作るため、いたずら申込や打ち間違いでも会社レコードが作られる。指示書§9はメール認証を挟んでから provisioning する設計で、**こちらの方が安全である**。移植時に工程を1つ足す形で対応できる（`email_verification_tokens` は招待トークンの実装をほぼそのまま転用可能）
2. **契約人数の確定・強制が無い**（手順5〜6）。完全新規
3. **`member` 招待の導線が無い**（手順6）。テーブルとトークンは対応済みだが、API・画面・人数検査が無い

---

## 16. 再利用可能部分

### 16-1. 【そのまま再利用可能】— **該当なし**

HTTPレイヤ・認証・フロントエンドがすべて異なるため、**ファイル単位でそのまま動くものは1つも無い。** 例外に見える `contractState.js`（外部依存が少ない純ロジック）も、Liteのモジュール規約とエラー型に合わせる必要がある。

### 16-2. 【設計・ロジックのみ再利用】— 最も価値が高い

| 対象 | 再利用する内容 |
|---|---|
| `contractState.js` | 契約7状態・`ALLOWED_TRANSITIONS` 表・`expectedFrom` による楽観ロック・`allowsBusinessFeatures`/`allowsLogin` の区別・14日猶予/30日解約の判定関数 |
| `accessPolicy.js` | **許可リスト方式・完全一致（前方一致にしない理由まで含む）・状態不明はフェイルクローズ・402（支払い必要）と403（停止/解約）の使い分け** |
| `pricing.js` | 正規値の一元管理・画面申告の金額を読まない原則・`firstCharge: null`（日割りを自前計算しない） |
| `signupValidation.js` | 全項目の検証規則・**メールヘッダー汚染防止（元入力に改行があれば無条件で不正）**・全角半角変換・理由コード体系 |
| `invitationRepository.js` | **トークン設計の全体**（256bit random／SHA-256ハッシュのみ保存／期限／使い捨て／再発行時の一括失効／形式事前検査） |
| `billingService.js` ＋ `stripeEventRepository.js` | **Webhook冪等の`claim`方式**（先に記録して最初の1回だけ処理・UNIQUE違反時は`failed`のみ`received`へ戻す）・順不同対策・イベント5種の処理方針・二重活性化の防止 |
| `stripeClient.js` | Checkout Session の組み立て・**`managed_payments:{enabled:false}`＋`payment_method_types:['card']`**（実疎通で判明した必須対応）・冪等キー・`toSafeError()` |
| `secretCrypto.js` | AES-256-GCM・`v1:iv:tag:ciphertext` 形式・**平文フォールバックを持たない**・マスク表示 |
| `src/services/email/smtpClient.js` | **外部ライブラリ非依存のSMTP実装**（net/tls・SMTPS/STARTTLS・AUTH LOGIN・証明書検証常時有効） |
| `auditLogRepository.js` | 保存禁止事項の明文化・`metadata` の1000文字上限・記録失敗で本処理を止めない |
| **テスト98件** | **受入基準としてそのまま使える。** 特に「金額改ざん耐性」「無効トークン3種の文言統一」「許可パスの前方一致漏れ検査」「古いイベントが新しい状態を上書きしない」「猶予は失敗のたびに延長されない」 |
| `PRODUCT_BOUNDARY.md` 第3章 | 「クライアント指定の company_id は**400で拒否**」という原則 |
| SALES-3S の実機E2E手順 | Stripe CLI の使い方・購読イベント・突合方法 |

### 16-3. 【部分移植可能】— 書き直しが必要

| 対象 | 書き直しの内容 |
|---|---|
| migration 18〜21 のDDL | **新規テーブル作成部分**（`signup_applications`／`invitations`／`invitation_email_outbox`／`stripe_webhook_events`／`company_ai_settings`）は流用可。`ALTER TABLE companies` はLiteのカラム構成に合わせて書き直し。JS配列 → Liteの `.sql` ファイル（017〜）へ形式変換 |
| リポジトリ5本 | Liteの規約（第1引数 `companyId`、全SQLの WHERE に必ず含める）へ書き直し |
| `provisioningService.js` | ロジックは流用。**DBトランザクションで囲む修正が必須**。Liteの `companyService.createCompany` / `userService.createUser` を内部から呼ぶ形に変更 |
| `stripeWebhook.js` | 自前署名検証は**移植しない**（公式SDKの `constructEvent` のみを使う）。テスト用の署名生成だけ流用 |
| 画面3本 | React コンポーネントへ作り直し。**ポーリング方式と「時間切れでも失敗と断定しない」方針は維持** |
| SALES-4 AI初期設定ウィザード | ロジックは参考になるが、**旧はBYOK（顧客が自社のOpenAI APIキーを登録する）方式**。Liteの正式方針は「スマートラボ管理キー」であり、**方式が異なる可能性が高い**（第22節 判断 #5） |

### 16-4. 【再利用禁止】

| 対象 | 理由 |
|---|---|
| `server.js` のルーティング | `node:http` 素実装・5563行・`handleAPI` 3700行。LiteはExpress＋ルーター分割 |
| `authService.js` | **インメモリ`Map`セッション**（再起動で消滅・逆引き不能・多重プロセス不可）＋**環境変数テナントとDBテナントの二系統**。LiteのDBセッションの方が優れている |
| **`tenant_users` テーブル** | Liteの `users` と**二重台帳になる**。権限モデル・セッション・監査ログがすべて分裂する |
| `role: admin/member` | 定義だけで一切参照されていない。Liteの3ロールが優れている |
| 環境変数テナント方式（`config.companies`） | 1プロセス1社1ユーザーの制約。Liteには不要 |
| `STRIPE_API_BASE` の本番残置 | テスト用の接続先差し替えが本番コードパスに残る |
| 未使用のデッドコード | `retrieveCheckoutSession` / `retrieveSubscription` / `verifyConnectivity` / `verifyAndParse` |

### 16-5. 【新規実装】

メール認証工程／メール送信ワーカー（招待）／**契約人数上限の強制**／**provisioningのトランザクション**／`provisioning_jobs` と再処理画面／規約versionを含む `consent_records`／猶予の自動遷移ジョブ／`billing_cycle_anchor`（毎月1日前払い）／自社から解約するAPI・画面／請求情報表示・Billing Portal／**運営者向け顧客・契約管理画面**／監査ログ閲覧UI／キャンペーン／紹介コード／`invoices_mirror`／`member` 招待の導線／Lite側テスト基盤

### 16-6. 再利用量の評価（機能単位・工数単位）

| 観点 | 評価 |
|---|---|
| **ファイル単位** | そのまま使えるファイル **0本** |
| **機能単位** | 27項目中 A=13（48%）。ただしA判定でも「設計の再利用」であって「コードの再利用」ではない |
| **工数単位** | **削減効果は約20〜25%。** 設計・エッジケース・テストケースが確定済みである一方、Liteアーキテクチャへの書き直しとテストの再構築が必要なため |
| **リスク単位** | **削減効果が最も大きいのはここ。** 実Stripe疎通・Managed Payments対応・Webhook冪等の実挙動・Price環境変数の落とし穴が**すべて実機で確認済み** |

---

## 17. 再利用禁止部分（要約）

第16-4節のとおり。**特に重要な3つを再掲する。**

1. **`tenant_users` を持ち込まない** — Liteの `users` に統合する
2. **旧の認証（インメモリセッション・環境変数テナント）を持ち込まない** — LiteのDBセッションを使う
3. **旧のロールモデルを持ち込まない** — Liteの `operator`/`company_admin`/`user` を使う

---

## 18. 新規実装部分（要約）

第16-5節のとおり。**このうち販売開始に必須なのは次の6件。**

`①契約人数上限の強制` `②provisioningのトランザクションと再処理` `③招待メール送信ワーカー` `④運営者向け顧客・契約管理画面` `⑤billing_cycle_anchor` `⑥猶予の自動遷移ジョブ`

---

## 19. migration移行案

**本工程ではmigrationを作成しない。以下は案である。**

| Lite新規 | 内容 | 旧の対応 |
|---|---|---|
| `017_create_signup_applications.sql` | `signup_applications`。旧に**メール認証用の状態を追加**（`pending_email` / `email_verified`） | 旧v18の一部（DDL流用可） |
| `018_create_email_verification_tokens.sql` | メール認証トークン。`token_hash` UNIQUE | **旧に無し**（招待トークンの設計を転用） |
| `019_create_user_invitations.sql` | 招待。**`tenant_users` ではなく Lite の `users` を参照する** | 旧v18の `invitations`（参照先を変更） |
| `020_create_invitation_email_outbox.sql` | 送信キュー。**本文・件名・URLを保存しない**方針を継承 | 旧v18（DDL流用可） |
| `021_extend_companies_contract.sql` | `companies` へ `contract_status` ほか。**Liteの既存 `status` / `license_count` / `contract_started_at` / `contract_end_at` と重複させない** | 旧v18の ALTER（**要書き直し**） |
| `022_create_subscriptions.sql` | 契約・課金。決済事業者のID列 | 旧は `companies` の列で代用。**WEB-SALES-1設計の独立テーブル案を採る** |
| `023_create_payment_events.sql` | `provider_event_id` UNIQUE＝冪等キー。**payloadを保存しない** | 旧v19の `stripe_webhook_events`（DDL流用可） |
| `024_add_payment_grace.sql` | 猶予期限・停止日時 | 旧v20（流用可） |
| `025_create_consent_records.sql` | **規約versionを含む**同意記録 | 旧は `companies` の2列のみ（**要拡張**） |
| `026_create_provisioning_jobs.sql` | 失敗検知と再処理 | **旧に無し**（新規） |
| `027_create_invoices_mirror.sql` | 請求ミラー | **旧に無し**（新規） |

### 19-1. 移行上の必須注意

1. **番号の衝突は無い**（Liteは017から）。ただし旧の `ALTER TABLE companies` はそのまま使えない（第7-4節）
2. **`companies.status` と `contract_status` を混ぜない。** 旧も同じ判断をしている — 「既存の status（active/inactive）は『企業レコードの有効/無効』を表す運用上の列で、契約の進行状態とは別concern」
3. **SQLiteの `ALTER TABLE ADD COLUMN` はCHECK制約を付けられない。** 旧はこの制限のためリポジトリ層で値を強制している。Liteでも同じ対応が必要
4. **Liteのmigrationは前進のみ（downが無い）。** 破壊的でないDDLに限定する
5. Liteのmigrationランナーは `-- migration-requires: foreign_keys_off` に対応している。FK追加を伴う場合に活用できる

---

## 20. 工数再見積もり

### 20-1. 前提

- 実装先は Lite `smartlabo-works-lite`（代表判断 #3 の推奨どおり）
- 再利用は「設計・ロジック・テストケースの移植」であり、コードのコピーではない
- 新たに検出した S9（Origin検証）と S10（テスト基盤）を計上する
- 1日＝実作業日

### 20-2. 再見積り

| 工程 | WEB-SALES-1 | **楽観** | **標準** | **安全** | 増減の理由 |
|---|---|---|---|---|---|
| WEB-SALES-1B（S1/S2/S3＋**S9**） | 2〜3 | 3 | 4 | 5 | S3は旧の考え方を使えるが、**S9（Origin検証）が増加**。S1はLiteのDBセッションで旧より良い解を実装できる |
| WEB-SALES-2 申込＋料金＋一時申込 | 4〜6 | 3 | 4 | 5 | `signupValidation`/`pricing`/`salesResponse` の設計移植で短縮 |
| WEB-SALES-3 メール認証＋パスワード＋規約同意 | 6〜9 | 4 | 6 | 8 | トークン設計と自前SMTP実装の移植で短縮。**ただしメール認証工程は旧に無く新規**。送信ワーカーも新規 |
| WEB-SALES-4 決済＋Webhook＋契約状態 | 8〜12 | **4** | **6** | **8** | **最大の短縮。** 実機E2E済みの知見（Managed Payments・Price環境変数・SDK版数・冪等の実挙動）が効く。ただし `billing_cycle_anchor` と猶予ジョブは新規 |
| WEB-SALES-5 provisioning＋自社顧客管理 | 5〜8 | 4 | 6 | 8 | provisioningロジックは移植可。**ただしトランザクション化・`provisioning_jobs`・運営者画面は新規** |
| WEB-SALES-6 招待＋人数制限＋利用者登録 | 5〜7 | 4 | 5 | 7 | 招待機構は移植可。**人数上限の強制は完全新規**。`users` 統合とemail一意の判断が必要 |
| WEB-SALES-7 契約管理画面＋決済失敗＋解約 | 6〜9 | 6 | 8 | 10 | **短縮ほぼ無し。** 運営者画面・監査ログ閲覧・解約API・請求表示は旧にすべて無い |
| WEB-SALES-8 初期設定＋E2E＋公開 | 5〜8 | 4 | 6 | 8 | AI初期設定は旧にあるが**BYOK方式でLiteの管理キー方針と異なる可能性**（判断 #5）。E2Eは旧のテストケースを流用 |
| **（新規）テスト基盤整備（S10）** | ― | 2 | 3 | 4 | Liteに `node --test` の基盤を用意し、旧の98テストを移植できる状態にする |
| **合計** | **41〜62** | **34** | **48** | **63** | |

### 20-3. 並行・外部要因（日数を見積もれない）

| 項目 | 状況 |
|---|---|
| **法務工程** | 特商法表記／キャンペーン規約／利用規約の契約条項。**専門家確認が必要。最大のスケジュールリスク（変わらず）** |
| **Stripe代表作業** | **大幅に軽減。** サンドボックスでのアカウント・テストキー・Price 3件・Stripe CLI は**設定済みの実績がある**（SALES-3R/3S）。残るのは**本番モードの本人確認と本番Webhookエンドポイント登録** |
| **メール送信基盤** | SMTP実装の設計は移植可。**ただし送信ドメイン・SPF/DKIM/DMARC・到達性の確立は新規**（2〜4日相当を WEB-SALES-3 に含めた） |

### 20-4. 短縮可能日数の結論

**標準値で 48日。WEB-SALES-1の 41〜62日（中央値約51日）に対し、短縮は約3日にとどまる。**

WEB-SALES-1が示した楽観値「25〜38日」には**届かない**。理由は3点。

1. 再利用の実体が「設計の移植」であり、コードは書き直しになる
2. Liteにテスト基盤が無く、その整備が追加で必要（S10）
3. 新たな欠陥S9の対応が増えた

**ただし、日数以外の価値が大きい。**

- **WEB-SALES-4（決済）は 8〜12日 → 4〜8日** と明確に短縮する
- **最大の技術的不確実性（実Stripe疎通）が既に解消されている**
- 設計上の判断（状態遷移・許可リスト・冪等方式・トークン設計）が**実装され、テストされ、実機で通っている**
- 98件のテストケースが受入基準としてそのまま使える
- **旧実装の欠陥（トランザクション欠如・人数上限ゼロ・メールワーカー不在）が事前に分かったため、同じ穴を掘らずに済む**

---

## 21. リスク

| # | リスク | 影響度 | 内容と対策 |
|---|---|---|---|
| **R-1** | **SALES実装がリモートに存在しない** | **大** | `feature/sales-*` の3本と `feature/product-1-dashboard` は**いずれも origin へ push されていない**。**このPCのローカルbranchが唯一の写しである。** 移植の参照元を失うとやり直しになる。→ 判断 #1 |
| R-2 | 法務ページの未整備 | **最大** | WEB-SALES-1から変わらず。特商法表記・キャンペーン規約・契約条項がいずれも無い |
| R-3 | 旧実装の欠陥をそのまま移植する | 大 | トランザクション欠如・人数上限ゼロ・招待メールワーカー不在・ロール未使用。**本監査で特定済みなので、移植チェックリストで機械的に防ぐ** |
| R-4 | `tenant_users` の持ち込みによる台帳二重化 | 大 | 移植時に最も起きやすい事故。**禁止事項として明記した**（第17節） |
| R-5 | 本番Webhookエンドポイントが未登録 | 大 | サンドボックスはCLI転送のみ。**本番では決済後に `active` にならない**。`config.stripe.enabled` に `webhookSecret` が含まれない設計と併せて対処が必要 |
| R-6 | `billing_cycle_anchor` 未実装 | 中 | 14番の確定仕様「毎月1日に当月分を前払い」が**まだ実現していない**。請求日がCheckout作成時点になる |
| R-7 | 支払い失敗系が実機未確認 | 中 | `invoice.payment_failed` と14日猶予は**自動テストのみ**。実機確認が必要 |
| R-8 | AI初期設定の方式不一致 | 中 | 旧はBYOK（顧客が自社APIキーを登録）。Liteの正式方針は「スマートラボ管理キー」。→ 判断 #5 |
| R-9 | email一意制約の変更判断 | 中 | 旧＝会社スコープ、Lite＝グローバル。統合方針次第で招待の挙動が変わる。→ 判断 #2 |
| R-10 | メール到達性 | 中 | SMTP実装はあるが、送信ドメイン・SPF/DKIM/DMARCの確立は新規 |
| R-11 | Liteにテスト基盤が無い | 中 | 旧の98テストを活かせない。→ S10 |
| R-12 | 旧の文書が実装に追随していない | 小 | 旧 `CLAUDE.md` のAPI一覧は9本しか載っておらず実装48ルートに対して陳腐化。`SALES_3R_STRIPE_LIVE_TEST.md` は追記前の「疎通できていない」記述が文書内に残存し自己矛盾している。**旧文書を読む際は実装を正とすること** |

---

## 22. 代表判断事項

### #1 ★最優先: 旧SALES実装の保全

**推奨: 旧リポジトリの `feature/product-1-dashboard`（SALES-4を含む最新）を origin へ push する。**

現状、SALES-2〜4の全成果はこのPCのローカルbranchにしか存在しない。ディスク障害・誤操作で失われれば、本監査で確認した資産（実装＋98テスト＋実機E2Eの知見）がすべて消える。

| 選択肢 | 影響 |
|---|---|
| **A（推奨）保全のためのpushだけを行う** | 別工程を1本立てて push のみ実施（数分）。**旧リポジトリを正式製品へ戻すことは意味しない** |
| B 何もしない | 移植完了までローカル単一障害点が残る |
| C ローカルでbundle等を作り別媒体へ退避 | pushしない方針を保ったまま保全できる |

**本工程では push を含む一切の書き込みを行っていない。** 実施には代表の明示指示が必要である。

### #2 ★利用者台帳の統合方針とメールアドレスの一意範囲

**推奨: Liteの `users` に統合し、`tenant_users` は持ち込まない。**（第12-2節）

ただしこれに伴い、**メールアドレスの一意範囲を決める必要がある。**

| 選択肢 | 影響 |
|---|---|
| **A（推奨）Liteの現行どおりグローバルUNIQUEを維持** | 変更が小さい。ただし「1メール＝1社」制約が残り、兼務・グループ会社・退職後の別社入社を扱えない。招待時に他社所属メールは拒否（理由は非開示） |
| B 旧に合わせて `(company_id, email)` の会社スコープへ変更 | 別会社で同一メールを使える。**ただしログイン時に会社を選択させる必要があり、認証の識別子設計全体に波及する大規模変更** |

**いずれを選んでも、S7（大小文字の正規化不一致）は先に解消する必要がある。** 旧の `trim().toLowerCase()` 統一方式を採用する。

### #3 申込フローの正式確定（WEB-SALES-1の判断#2）

**推奨: 環境先行方式を正式採用する。**

WEB-SALES-1の時点では「SSOTにそう書いてある」という根拠だったが、本監査により**実装され・98件のテストで固定され・実Stripeサンドボックスで通しで動作確認まで済んでいる方式**であることが確認された。推奨の確度が上がった。

**ただし指示書§9のとおり、メール認証工程を1つ挟むことを推奨する。** 旧はこれが無く、いたずら申込や打ち間違いでも会社レコードが作られる。

### #4 実装場所の確定（WEB-SALES-1の判断#3）

**推奨: Lite `smartlabo-works-lite` の `server/` に置く。**（変更なし）

本監査で根拠が補強された。旧実装は provisioning・契約状態・利用制御・Webhookをすべて製品バックエンド内に置く構成であり、これが自然である（provisioningがDB内で完結する）。

### #5 ★AI初期設定の方式（新規）

旧のSALES-4は **BYOK方式**（顧客が自社のOpenAI APIキーを登録し、AES-256-GCMで暗号化保存）である。

一方、Smart Labo Works ライトの正式AI方針は「**スマートラボ管理キー**」（企業別キーはEnterprise隔離）と記録されている。

**両者は方式が異なる。** 旧のSALES-4（`aiSetupService` / `openaiSetupClient` / `secretCrypto` / `companyAiSettings` ＋テスト519行）をそのまま移植すると、**正式方針と矛盾する製品になる。**

| 選択肢 | 影響 |
|---|---|
| **A（推奨）Liteの管理キー方針を維持し、旧のBYOK実装は移植しない** | WEB-SALES-8の初期設定ウィザードは「AIの使い方案内」に縮小できる。`secretCrypto.js` の暗号化設計だけは他用途で再利用可能 |
| B Enterprise向けにBYOKを併存させる | 旧実装が活きるが、鍵管理・課金・サポートの複雑さが増す |

### #6 招待の有効期限

旧は **7日**（`DEFAULT_TTL_MS = 7 * 24 * 60 * 60 * 1000`）。WEB-SALES-1の設計案はメール認証24時間・招待は未定。

**推奨: メール認証24時間／招待7日**（旧の実績値を採用）。

### #7 `payment_required` のまま放置された環境の扱い

旧のSALES-2記録でも代表判断事項に挙げられたまま未決。**何日で `suspended` へ落とすか。** 現在は自動遷移なし。

**推奨: 30日**（停止後30日で解約という既存ルールと揃える）。

### #8 本番Webhookエンドポイントの登録とドメイン

サンドボックスはStripe CLIの転送のみで確認している。本番では実エンドポイントの登録が必要。

**推奨: `lite.smartlaboworks.com` 配下に推測困難なパスで設置し、パスを公開資料に書かない。** 併せて `stripe.enabled` の条件に `webhookSecret` を含め、**Webhook未設定ならCheckoutを開始できない**ようにする（旧の設計上の穴を塞ぐ）。

### #9 継続中の未決事項（WEB-SALES-1から変更なし）

日割り・消費税・月途中の人数変更（15番9-1〜9-3）／契約終了後のデータ保持と削除／最低利用期間・解約締切・返金条件／キャンペーンの正式条件／インボイス登録／`website-v3` と `master` のマージ時期／税表記の統一。

---

## 23. 次工程の推奨順

| 順 | 工程 | 前提 | 備考 |
|---|---|---|---|
| **0** | **旧SALES実装の保全**（判断 #1） | 代表指示 | 数分。**移植作業の前に済ませることを強く推奨** |
| **1** | **WEB-SALES-1B**（S1/S2/S3/S9） | なし。**今すぐ着手可能** | 販売機能と独立。S9を追加した |
| 2 | WEB-SALES-2 申込＋料金＋一時申込 | 判断 #2 #3 #4 | |
| 3 | WEB-SALES-3 メール認証＋パスワード＋規約同意＋メール基盤 | ― | メール到達性の確立を含む |
| 4 | WEB-SALES-4 決済＋Webhook＋契約状態 | 判断 #8、本番キーの本人確認 | 最も短縮が効く |
| 5 | WEB-SALES-5 provisioning＋自社顧客管理 | ― | **トランザクション化を必須要件とする** |
| 6 | WEB-SALES-6 招待＋人数制限＋利用者登録 | 判断 #2 | 人数上限は完全新規 |
| 7 | WEB-SALES-7 契約管理画面＋決済失敗＋解約 | ― | 短縮ほぼ無し |
| 8 | WEB-SALES-8 初期設定＋E2E＋公開 | 判断 #5、**法務3点の完了** | |
| 並行 | 法務工程 | 専門家確認 | **最大のスケジュールリスク** |
| 並行 | テスト基盤整備（S10） | ― | WEB-SALES-2 の前に着手すると以降が楽になる |

---

## 24. Go／No-Go

### 24-1. 判定

| 対象 | 判定 | 根拠 |
|---|---|---|
| **WEB-SALES-1B** | **Go** | 販売機能と独立した既存製品の欠陥修正。判断待ちなし。**S9（Origin検証）を範囲に追加すること** |
| **WEB-SALES-2** | **条件付きGo** | WEB-SALES-1の判断#1（旧実装の扱い）は**本監査で解決した**。残るは下記2件 |
| WEB-SALES-4（決済） | 条件付き | 判断 #8 と本番モードの本人確認 |
| WEB-SALES-8／本番公開 | **No-Go** | 法務3点（特商法表記・契約条項・キャンペーン規約）が未整備 |

### 24-2. WEB-SALES-2 着手の解除条件

WEB-SALES-1が挙げた4条件のうち、**2つが本監査で解除された。**

| # | 条件 | 状態 |
|---|---|---|
| 1 | 判断事項#1（旧リポジトリの扱い） | **✅ 解決。** 実在を確認し、再利用範囲を確定した（第16節）。**別途、保全のためのpushを推奨**（判断 #1） |
| 2 | 判断事項#2（申込フローの矛盾） | **✅ 根拠が確定。** 環境先行方式が実装・テスト・実機確認済み。**代表の正式確定のみ残る**（判断 #3） |
| 3 | 判断事項#3（実装場所） | 未決。**推奨は Lite `server/`**（判断 #4） |
| 4 | WEB-SALES-1B の完了 | 未着手。**今すぐ着手可能** |

**→ 残る解除条件は「判断 #3（フロー確定）と #4（実装場所）の代表決定」＋「WEB-SALES-1Bの完了」の実質3件。** 加えて判断 #2（利用者台帳とメール一意範囲）は WEB-SALES-2 の設計に影響するため、同時に決めることを推奨する。

### 24-3. 本工程の変更範囲（実施結果）

**実施したこと**: 3リポジトリの読み取り確認／旧リポジトリの読み取り専用エクスポートと調査／本文書の作成／監査用feature branchの作成。

**実施していないこと**: 旧リポジトリ内のファイル変更・commit・amend・push・merge・rebase・checkout・stash・migration実行・npm install・依存更新・サーバー起動・テスト実行・秘密値の表示・旧コードのLiteへのコピー・本番VPS/XServer/Stripe/メールサービスへの接続・Liteでのcommit・masterへの操作・本番公開。

**本番変更 0件。Lite変更 0件。旧repo変更 0件。**

---

## 関連ドキュメント

- [WEB_SALES_1_CONTRACT_AUTOMATION_AUDIT.md](WEB_SALES_1_CONTRACT_AUTOMATION_AUDIT.md) — 前工程（本書はその判断事項#1に答えるもの）
- [PROJECT_BIBLE/14_Sales_And_Billing_Policy.md](../../PROJECT_BIBLE/14_Sales_And_Billing_Policy.md) — 販売方針の正本（v4.0）
- [PROJECT_BIBLE/15_Stripe_Sales_Billing_Design.md](../../PROJECT_BIBLE/15_Stripe_Sales_Billing_Design.md) — 実装設計の正本（v4.0）。**第0章の「実Stripe疎通は未実施」はSALES-3Sにより解消済み。本書第11節を参照**
- 旧リポジトリ `docs/reviews/SALES_2_SIGNUP_PROVISIONING.md` ほか5本 — 旧実装の一次記録（**参照のみ。旧リポジトリは凍結コード**）

---

*作成: Claude Code / WEB-SALES-1R（2026-08-09）*
*本書は読み取り専用監査と移植可否判定の記録である。実装・移植は含まない。*
