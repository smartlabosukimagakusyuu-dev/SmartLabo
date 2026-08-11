# WEB-SALES-8L 発売前法務是正・外部送信同意・Website正式化

対象製品：Smart Labo Works Lite（https://lite.smartlaboworks.com ・本番未反映）
製品branch：`feature/web-sales-8l-legal-finalization`（基点 8143d023ab664acd1b9c856c97400f694c249e1f）
文書branch：`feature/web-sales-4-docs`
実施日：2026-08-11
根拠：SMART_LABO_LEGAL_REVIEW_PACKAGE.md（発売前法務確認資料）と外部監査（ChatGPT）の指摘

**本工程では master統合・本番公開・本番設定変更を行っていない。**

---

## 1. 実施内容（製品repo）

### 1-1. 法務3文書を 1.2.0 へ改定（`server/services/legal/legalDocuments.js`）

- 制定日 2026-08-11 は維持、最終改定日 2026-08-11（作業日）
- 利用規約：全22条→**全24条**（条番号を振り直し）
  - 新設 第8条（知的財産権）… 当社側の知財帰属＋契約者データの権利は契約者側維持（第7条と接続）
  - 新設 第22条（分離可能性）
  - 第20条（本規約の変更）… 「利用者一般の利益への適合」または「目的適合・必要性・相当性等に照らした合理性」を要件とする基準へ変更
  - 第17条（免責）… 消費者契約法その他の強行法規の適用範囲を除外する留保を追加（故意・重過失の除外は維持）
  - 第18条（損害賠償の範囲および上限）… 対象を「現実に発生した通常かつ直接の損害」に整理、逸失利益等の特別損害を除外、上限は当月支払額のまま
  - 第15条2項 … データ復元を「保存状況等を確認し、技術上・運用上可能な範囲で引き継ぐことがある。復元・引継ぎを保証しない」へ変更
- プライバシーポリシー：
  - 7項を書き直し、Stripe / OpenAI / Google Cloud Vision / メール送信基盤の**送信目的・送信情報**を事業者ごとに明記
  - OpenAIについて「API経由の情報は既定ではモデル学習に使用されない（同社公表ポリシーによる）」
    「サービス提供・不正利用監視等のため同社で一定期間保持される場合がある」
    「当社DBには質問文・回答本文を保存しない」を明記。**「保持0」「即時削除」等の断定は書いていない**
  - 8項の「専門家の確認を踏まえて更新する」という未確定文を削除し、
    国外（米国等）での取扱いの可能性・委託先監督・**契約者が適法な権限を持つ情報だけを入力する責任**を明記
    （委託／外国第三者提供の法的区分は断定していない）
- 特商法表記：人数増加「決済事業者が算定する日割り料金のお支払いが確認できた時点で反映」を追記
- 新digest（currentConsentDigest）：`b2407986486b2d48f1aac027609d885302d58692e2ca9a721c8ebd4c605f1689`
  （旧1.1.0：`c63ea7e7…a781f`。**旧1.1.0の同意ではCheckoutを作れず、1.2.0再同意後のみ可**を受入テストで確認）

### 1-2. 本番の OPENAI_BASE_URL 固定（`server/config.js` / `server/index.js`）

- production では `https://api.openai.com/v1` **との完全一致のみ**許可
  （別ホスト・認証情報付きURL・http・部分一致・紛らわしいホストはすべて拒否）
- 環境変数が公式以外を指す場合は**起動を停止**（黙って差し替えない）。development/test のスタブ上書きは従来どおり

### 1-3. 外部送信同意のサーバー記録（migration 026 / 027）

- 026：`external_send_consents` 新設（company_id / user_id / feature / consent_version /
  disclosure_digest / consented_at / created_at の**必要最小限**。氏名・メール・画像・OCR本文・
  プロンプト・回答・IP・外部IDは保存しない）。UPDATE/DELETE禁止トリガーで追記専用をDB層で強制
- 027：既存 `consent_records` にも UPDATE/DELETE禁止トリガーを追加（既存行は変更しない非破壊migration）
- 説明文の正本 `externalSendDisclosures.js`（doc_box_ocr / business_card の2種、版1.0.0）
- API `GET/POST /api/external-consents/:feature`。同意なしでは
  資料ボックスOCR・名刺解析が**外部送信0件のまま409**で止まる（サーバー側強制）
- 名刺の同意ダイアログへ「Google Cloud VisionでOCR後、読み取った文字情報（氏名・会社名・
  電話番号・メールアドレス等を含む）をOpenAIへ送信して名刺項目を整理する」ことを明記。
  説明文はサーバー正本をAPIで取得して表示（localStorage保存は廃止しサーバー台帳へ一本化）

### 1-4. 契約終了日の表示

- `GET /api/contract/status` へ `canceledAt` と `dataRetentionUntil`（解約確定+90日の**目安**）を追加（canceledのみ）
- ご契約状況画面・設定画面へ「ご契約の終了日」「データ保持期限の目安」を表示。
  **90日後の自動削除を保証する文言は使っていない**（「当社所定の手順により削除」）

## 2. 実施内容（文書repo）

- **Website公開正本の正式化**：masterのWEBSITE/を本branchへ取り込み、その上で
  - [CEO確認待ち]・「専門家確認前のドラフト」を全ページから削除（制定日2026-08-11で確定）
  - Website利用規約 第7条の管轄を「当社の本店所在地を管轄する地方裁判所」へ確定
  - privacy第5節「送信基盤は選定中」→ 実態（form.smartlaboworks.com＋レンタルサーバーのメール基盤）へ
  - privacy第6節 → 「アクセス解析・広告Cookieは使用していない」へ改定（GTM/GA4撤去に合わせる）
  - **tokushoho.html（特定商取引法に基づく表記）を新設**（Lite本体の表記と同一内容＋Lite法務3文書へのリンク）
  - 料金（pricing）・申込み（apply）・全ページフッターから tokushoho と Lite法務3文書
    （/legal/terms・/legal/privacy・/legal/commercial-transactions）へ到達可能に
  - company.html へ所在地・電話番号・受付時間（代表承認値）を掲載
  - **GTM/GA4/dataLayerを全ページ・全JSから撤去**（contact-form.jsのgenerate_lead送信も削除）。
    再導入条件は `docs/website/ANALYTICS_REINTRODUCTION.md` に文書化
  - **signup.html を削除**：全ページから未リンク・`action="/api/signup"` は静的ホスト上に存在しない
    経路（機能しない）・現行の申込経路（問い合わせ→個別対応）で不要のため
  - **創業記念キャンペーン（初期設定費無料・1か月無料・先着50社）と
    キャンペーンコード・紹介コードの記載を index / apply から撤去**：
    Lite本体の販売条件・特商法表記・最終確認画面は「キャンペーン：実施していません。
    無料体験の提供もありません」であり（8F代表判断）、製品実装にも割引コード欄が無い
    （`allow_promotion_codes: false`）。実施していない特典の広告は法務上の不一致のため
  - robots.txt の実態と不一致な注記を更新、sitemap.xml へ tokushoho.html を追加
  - `docs/reviews/tools/check-legal.js` を8L基準（未確定文字列0・解析タグ0・承認値一致・
    日付・法務導線）へ全面改定（旧「Version1凍結一致」検査は前提が失効したため）
- **漏えい対応runbook新規作成**：`docs/runbooks/SMART_LABO_WORKS_LITE_INCIDENT_RESPONSE.md`
  （検知／初動連絡／封じ込め／証拠保全／影響調査／PPC報告・本人通知の判断／
  外部事業者連絡／復旧／再発防止／実施記録。秘密値・本番コマンドなし）
- データ削除runbookを1.1.0へ（規約条番号の繰り下げ追従・external_send_consentsの保持を明記）

## 3. 弁護士確認の扱い（代表判断の記録）

- 法務3文書（1.2.0）・Website法務ページは、実装事実と代表判断にもとづき正式化した。
- **弁護士による確認は、会社の経営が安定した後の将来課題とする。**
- 代表は、外部監査（ChatGPT）の指摘への本工程の対応内容と、
  弁護士未確認のまま販売準備を進めることによる残存リスク
  （条項の有効性・外国第三者提供の法的区分・特商法表記の記載粒度等が
  専門家確認を経ていないこと）を了承したうえで、販売準備を進める。
- **利用者向けの文書には「弁護士未確認」「ドラフト」等の表示を行わない**
  （公開判定・リスク管理は本内部文書と CURRENT_STATUS で行う）。

## 4. 検証結果

- 全受入テスト（product repo・19スイート）：**pass 2,900 / fail 0**（詳細は完了報告）
  - 新規 `external-send-consent.mjs`（41件）：同意API・追記専用トリガー×2台帳・
    OPENAI_BASE_URL完全一致検査・canceledAt/dataRetentionUntil
  - `legal-consent.mjs`：固定digestを1.2.0へ更新、**旧1.1.0同意でCheckout不可→1.2.0再同意で可**、
    条文・記載の要点検査を1.2.0へ更新（検査は削除・skip・緩和せず、件数は増加）
  - `acceptance.mjs` 0b節・`business-card.mjs` A2節：**同意なしでは外部送信0件（Vision/OpenAIとも）**
- `npm run build` 成功
- Website：`check-legal.js`（8L版）・`check-prices.js` とも合格
  （未確定文字列0・解析タグ0・承認値一致・法務導線あり・料金整合）
- 実外部通信0・本番変更0・.env未参照・既存テストの削除/skip化/緩和0

## 5. 残存課題（次工程以降）

1. 弁護士による法務3文書・Website文書の確認（将来課題として代表了承済み。上記3章）
2. 1.2.0改定の本番反映（master統合・デプロイ・Stripe本番設定はスコープ外）
3. Websiteの公開反映（masterへの統合とGitHub Pages公開は別途CEO判断）
4. 外国第三者提供（個人情報保護法28条）の情報提供内容の最終整理（専門家確認とあわせて）
5. アクセス解析の再導入（`docs/website/ANALYTICS_REINTRODUCTION.md` の条件を満たしてから)
6. canceled後の自己再契約の画面対応（引き続きお問い合わせ運用）
7. 監査ログ・Stripeイベント台帳の保存期間の明文化
