# WEB-V2-8 — 販売モデルSSOT化・2導線設計・創業記念キャンペーン反映

- 実施日: 2026-07-28
- ブランチ: `website-v2`（ローカルのみ・未push）
- 本番影響: **なし**（`master`・`WEBSITE/`・`.github/workflows/pages.yml` は無変更）
- 販売仕様の正本: [PROJECT_BIBLE/14_Sales_And_Billing_Policy.md](../../PROJECT_BIBLE/14_Sales_And_Billing_Policy.md)（本工程で新設）

---

## 1. 作業開始時の状態

| 項目 | 値 |
|---|---|
| ブランチ | `website-v2` |
| 未コミット変更 | なし（working tree clean） |
| HEAD | `1572879` docs(website-v2): add handoff document for new sessions |
| WEB-V2-7 `b84bb2f` | 存在確認済み・**未push**（`origin/master..website-v2` に8コミット） |
| master | `10463d6`（V2作業では未変更） |
| ページ | index / features / pricing / company / contact / privacy / terms / 404 の8ページ |
| 検証スクリプト | check-prices.js・check-legal.js ともに開始時点で [OK] |

## 2. 既存構成監査

- CTA: 全ページのヘッダーに「無料相談」(btn--primary btn--sm)、ヒーロー・最終CTAは「無料相談」「無料で相談する」「料金について相談する」が混在。すべて contact.html へ接続
- 料金表示: index.html(#pricing) と pricing.html の2か所。`data-price` 属性で check-prices.js が突き合わせ
- キャンペーン表示: **なし**（check-prices.js が「キャンペーン」を禁止語として検査していた）
- SSOT: 指示書記載の `CLAUDE.md` / `PROJECT_BIBLE.md` / `CURRENT_STATUS.md` / `PRODUCT_REQUIREMENTS.md` / `PRODUCT_BOUNDARY.md` / `BUSINESS_STRATEGY.md` のうち、**このリポジトリに存在するのは `PROJECT_BIBLE/`(フォルダ) と `PROJECT_BIBLE/CURRENT_STATUS.md` のみ**。`PRODUCT_REQUIREMENTS.md`・`PRODUCT_BOUNDARY.md`・`BUSINESS_STRATEGY.md` は別リポジトリ `smartlabo-works` 側にあり、本工程のリポジトリ範囲外のため更新していない（第24節に代表判断事項として記載）
- 料金の思想は `PROJECT_BIBLE/12_Pricing_Philosophy.md`（4要素構造・金額未確定・個別相談表記）にあり、今回の確定販売モデルと**構造が一致しない**ことを確認（第17・24節）

## 3. 採用した販売導線

指示書どおり。①無料相談（相談→ヒアリング→デモ→提案→契約→利用開始）と、②今すぐ申し込む（会社情報→人数・料金確認→キャンペーンコード→紹介コード→カード→同意→契約・決済→アカウント自動作成→AI初期設定→利用開始）。CTAの役割は「今すぐ申し込む(主)／無料相談する(副)／機能を見る／料金を見る」に統一。

## 4. SSOT更新内容

| ファイル | 内容 |
|---|---|
| **PROJECT_BIBLE/14_Sales_And_Billing_Policy.md（新設・v1.0）** | 販売導線・セルフ申し込み(クレジットカードのみ)・通常料金・課金ルール・会計方針・創業記念キャンペーン・キャンペーンコード・紹介コード・AI初期設定ウィザード・人間確認原則・未実装範囲・SALES-0〜6 を正式仕様として1か所に集約（**正本**） |
| PROJECT_BIBLE/12_Pricing_Philosophy.md（v2.0→v2.1） | 冒頭に「金額・課金・キャンペーンは14番が正本」の注記のみ追加。**思想部分(4要素構造)は無変更**。4要素構造と単一プラン確定金額販売の不一致を未決事項として明記 |
| PROJECT_BIBLE/README.md | Version 7.9→8.0、目次・フォルダ構成へ14番を追加、変更履歴v8.0を追記 |
| PROJECT_BIBLE/CHANGELOG.md | v8.0エントリを先頭に追加 |
| PROJECT_BIBLE/CURRENT_STATUS.md | 「公式サイトVersion2.0リニューアル進捗」セクション新設、5行サマリー・ステータス一覧・変更履歴を更新 |

配置判断: 指示書の候補は `docs/specs/SALES_AND_BILLING_POLICY.md` だったが、このリポジトリのSSOTは番号付きの `PROJECT_BIBLE/` 章立て（12_Pricing・13_Platform…）で運用されているため、**`PROJECT_BIBLE/14_Sales_And_Billing_Policy.md` を優先**した（既存SSOT構成優先の指示に従った判断）。`docs/` はレビュー記録置き場であり規範文書の置き場ではない。

## 5. 新規・変更ページ

- **新規: `website-v2/apply.html`**（申込案内ページ。フォーム要素なし）
- 変更: index / features / pricing / company / contact / privacy / terms / 404（ヘッダー・フッター共通部品＋各ページCTA）
- 変更: `assets/css/base.css`（ヘッダーCTA 2つ化・520px短縮ラベル）、`assets/css/components.css`（apply-status / campaign / steps--flow / plan__actions / final-cta__actions の追加・調整）
- 変更: `sitemap.xml`（apply.html追加）、`website-v2/README.md`、`docs/reviews/tools/check-prices.js`

## 6. トップページの変更内容

1. ヒーローCTA: 「無料相談／デモを見る」→「**今すぐ申し込む(主)／無料相談する(副)**」。ヒーローの主メッセージ「会社を動かすAI。」「全社員に、AIという戦力を。」は**無変更**
2. 料金セクション末尾: 「料金について相談する」→「今すぐ申し込む／料金を見る」
3. **創業記念キャンペーンセクションを新設**（#campaign、料金セクション直後）: 3枚の静かな特典カード＋2CTA＋注意書き。発光・グラデーション・カウントダウンなし
4. セクション背景の交互リズム維持のため #steps/#faq/#company の subtle/white を入れ替え（コンテンツは無変更）
5. 最終CTA: 「無料で相談する」1つ→「今すぐ申し込む／無料相談する」2つ
6. 料金名称を「初期費用」→「初期設定費」へ統一（meta description含む）

## 7. apply.html の内容

指示書第4節の10項目をすべて実装。

1. h1「Smart Labo Works お申し込み」
2. リード: オンラインで申し込みから利用開始まで完結できる方法を**用意する予定**である旨
3. 申込の流れ: 指示どおりの9ステップ（会社情報→人数・料金→キャンペーンコード→紹介コード→カード→同意→アカウント作成→AI初期設定→利用開始）＋「AIが自動で契約を確定させない」注記
4. 通常料金: 初期設定費10,000円／月額20,000円(管理者1名込み)／追加3,000円・税抜明示（`data-price`でcheck-prices.jsの検査対象）
5. 課金ルール: 日割り・前払い・毎月1日自動決済・同一サイクル・カード限定・初回決済額は申込画面で表示予定
6. 創業記念キャンペーン: 初期設定費無料・基本料金1か月分無料・先着50社・カード必須・8/20開始の請求例・「募集枠または期間の終了をもって受付を終了」・詳細条件は正式申込画面またはキャンペーン規約で確認する旨
7. 2つの選択肢: 「今すぐ申し込む」(準備状況と先行案内導線)／「無料相談する」
8. **公開状態ブロック(#status)**: 「オンライン申し込みは現在準備中です。先行してのご案内をご希望の方は無料相談から」— このセクションの中身だけを差し替えれば公開状態を切り替えられる構造（HTMLコメントで明記）。「受付完了」「申し込み可能」等の表現は不使用（check-prices.jsが機械検査）
9. 関連リンク: 料金・機能・FAQ・プライバシーポリシー・利用規約・無料相談の6件
10. SEO: title・description・canonical・OGP・X Card・BreadcrumbList(構造化+画面表示)・h1は1つ・sitemap追加・ヘッダーフッター同一

## 8. CTA監査結果

| ページ | 主CTA | 副CTA・その他 |
|---|---|---|
| 全ページ ヘッダー | 今すぐ申し込む → apply.html | 無料相談する → contact.html（1080px以下は主CTAのみ・520px以下は「申し込む」短縮） |
| 全ページ フッター | ―（導入列に「お申し込み」リンク追加） | |
| index ヒーロー | 今すぐ申し込む | 無料相談する |
| index 料金 | 今すぐ申し込む | 料金を見る → pricing.html |
| index キャンペーン | 今すぐ申し込む | 無料相談する |
| index 最終CTA | 今すぐ申し込む | 無料相談する |
| features ヒーロー/最終 | 今すぐ申し込む | 無料相談する |
| pricing プラン/最終 | 今すぐ申し込む | 無料相談する／料金について相談する |
| apply | 無料相談する(準備中のため) | 料金を見る・先行案内を希望する |
| company 最終CTA | 無料相談する | （会社紹介ページのため申込を主にしない判断。表記のみ「無料で相談する」→「無料相談する」に統一） |
| contact | 送信する(フォーム) | 変更なし |
| 404 | トップページへ戻る | 変更なし |

禁止表現（無料トライアル・資料請求・デモ予約・即時利用可能等）は**新規追加なし**を目視＋check-prices.jsの機械検査で確認。

## 9. 料金・課金表示

- 4か所（14_Sales_And_Billing_Policy.md／check-prices.js CANONICAL／index／pricing／apply — スクリプト対象は後者3ページ）すべて 10,000／20,000／3,000 で一致
- 名称を「**初期設定費**」へ統一（正式決定の名称。旧「初期費用」はindex/pricingから8箇所置換）
- 課金ルール（日割り・前払い・毎月1日）は apply.html にのみ掲載。pricing.html は通常料金の正本としてキャンペーン・課金サイクルを持たせず、既存の税別表記・計算例を維持

## 10. 創業記念キャンペーン表示

- 掲載場所: index.html(#campaign) と apply.html(#campaign) の2か所のみ（check-prices.jsで掲載可能ページを制限）
- 必須表記4点（創業記念キャンペーン／基本料金1か月分無料／先着50社／クレジットカード）を機械検査
- 「初月無料」は全ページ禁止語として機械検査（コメント内は対象外）
- 残数の自動減算・カウントダウン・偽の申込件数は実装していない

## 11. キャンペーンコード・紹介コードの扱い

- apply.html の申込フロー内で**別ステップ(3と4)・別概念**として表示。「キャンペーンコードとは別の欄です」と明記
- 入力UI・DB・検証ロジックは未実装（SALES-3／SALES-4）
- 紹介コードによる割引付与は表示していない（計測目的である旨のみ）

## 12. 問い合わせフォームとの接続

- 無料相談CTAはすべて WEB-V2-7 の contact.html へ接続
- 問い合わせ種別5択は**無変更**
- URLクエリによる種別自動選択は**未実装**。理由: 現行フォームJSに種別復元処理がなく、実装するとcontact-form.jsの改修が必要になる。必要性が明確になった段階（例: apply.htmlから「先行案内」種別を立てたい場合）で追加を提案する

## 13. SEO対応

- apply.html: title「お申し込み｜Smart Labo Works」・description・canonical・OGP・X Card・BreadcrumbList
- sitemap.xml へ apply.html を追加（priority 0.8）
- robots.txt: 変更不要（Disallowなし・sitemap参照のみ）
- title重複なし・全ページdescription設定・canonical整合をPuppeteerで実測（404はnoindexのためcanonicalなし＝既存仕様）

## 14. アクセシビリティ

- 新規コンポーネントのコントラスト実測: Smart Blue on bg-subtle 4.77／text-secondary on bg-subtle 4.99／btn--primary 5.17／ghost-light on navy 16.98 — **すべてWCAG AA(4.5)以上**
- h1は全9ページで1つ、本文(main)内の見出しレベル飛びなし（フッターh4列見出しは全ページ共通の既存構造）
- alt欠落0・キーボードフォーカス既存仕様維持・reveal無効環境でも内容表示

## 15. レスポンシブ確認

- 375/768/1024/1440px の4幅で全9ページ横スクロールなし（Puppeteer実測、`scrollWidth - clientWidth ≤ 1px`）
- ヘッダー: 1081px以上は副CTA＋主CTA、1080px以下は主CTAのみ（副CTAはドロワー内「お問い合わせ」と本文CTAが代替）、520px以下は「申し込む」短縮ラベル — ロゴとCTAの重なりを実測で解消
- 申込9ステップ: デスクトップ3列×3行、860px以下は既存stepsの縦積みを継承
- キャンペーンカード: 860px以下1列、640px以下でCTA全幅（タップ領域確保）
- 検出・修正した表示問題: ①キャンペーン見出しの語中改行（見出しを短文化）②apply「2つの進め方」見出しの語中改行（`<br>`挿入）③375pxヘッダーのロゴとCTAの重なり（短縮ラベルで解消）

## 16. テスト結果

| 項目 | 結果 |
|---|---|
| check-prices.js | **[OK]** exit 0（apply.html追加・キャンペーン表記・禁止表現の新検査を含む） |
| check-legal.js | **[OK]** exit 0（privacy/terms はv1と一字一句一致のまま） |
| リンク検証 | 全9ページの内部リンク・アンカー切れ **0**（Puppeteer＋ファイル実在＋id実在検査） |
| Console Error | **0**（contact.htmlのCSRFトークン404のみ既知・API未配置の間だけ発生、handoff 13-4記載の既存事象） |
| h1 / description / title重複 | 各1・全ページ設定・重複なし |
| 横スクロール | 4幅×9ページ = 36ケースすべてなし |
| 料金誤記 | 10,000／20,000／3,000・計算例(20,000/26,000/32,000)一致 |
| git diff --check | 空白エラーなし |

## 17. 法務確認事項（本文は一切変更していない）

今回の販売モデルにより、公開前に法務判断が必要な項目。**利用規約・プライバシーポリシーの本文はv1と完全一致のまま**（check-legal.jsで担保）。

**A. 利用規約に現在存在せず、追記が必要と思われる項目**
1. 課金条件: 日割り課金・前払い・毎月1日の自動決済・初回決済(日割り+翌月分)の定義
2. 支払い方法がクレジットカードに限定されること
3. 契約成立時点（申込完了時点か決済完了時点か）と利用開始日の定義
4. 自動更新（毎月自動決済＝自動更新契約であることの明示）
5. 解約時の扱い（月途中解約の残期間・日割り返金の有無 — **返金方針自体が未確定**）
6. 追加アカウントの請求サイクル
7. キャンペーンコード・紹介コードの利用条件（不正利用時の取消権を含む）

**B. キャンペーン規約（新規作成が必要）**
8. 創業記念キャンペーンの適用条件・「基本料金1か月分無料」の正確な範囲（**追加アカウントを含むかは未確定 — 規約確定前に代表決定が必要**）
9. 先着50社の判定基準（決済完了順か申込順か）・終了時の告知方法
10. カード登録必須の明示

**C. プライバシーポリシー**
11. クレジットカード情報の取り扱い（決済代行事業者名・非保持の方針）— 決済実装工程で確定
12. 紹介コードによる流入計測（取得目的への追記）
13. 既存の未整合（v1から持ち越し・WEB_V2_7_CONTACT_FORM.md第18節）: 制定日プレースホルダ・「専門家確認前のドラフト」表記・Smart Labo Configurator記載・取得していない項目(業種・社員数)の記載・取得している項目(利用予定人数・ハッシュ化IP)の未記載

**D. 特定商取引法に基づく表記（ページ自体が未作成 — セルフ申し込み公開前に必須）**
14. 通信販売(オンライン契約)を行う場合、特商法に基づく表記ページが必要（事業者名・代表者・所在地・連絡先・価格・支払時期/方法・役務提供時期・解約条件）。所在地・電話番号は現在非掲載方針のため、**掲載義務との整合を専門家に確認する必要がある**
15. **税込総額表示**: 現在サイトは税抜のみ表示。消費者向け表示には総額表示義務(税込併記)が適用されうる。BtoB限定サービスとしての整理か、税込併記への変更かを**専門家確認のうえ決定する必要がある**（今回は既存の税抜表示方針を維持）

## 18. 今回未実装の範囲（指示書第8節どおり）

決済(Stripe等)・カード情報入力・契約処理・有料契約作成・アカウント自動発行・メール認証・AI初期設定ウィザード本体・キャンペーンDB・紹介コードDB・管理画面・残数リアルタイム表示・カウントダウン・偽の件数表示・自動返信メール・WEB-V2-7 PHPの本番配置・DNS変更・XServer移設・法務本文修正・masterへのマージ — **すべて未実施**。「先着50社」は静的表示のみで自動減算なし。

## 19. 更新ファイル一覧

```
新規
  PROJECT_BIBLE/14_Sales_And_Billing_Policy.md
  website-v2/apply.html
  docs/reviews/WEB_V2_8_SALES_MODEL_AND_CONVERSION.md
  docs/reviews/assets/web-v2-8/ (スクリーンショット11点)
変更
  PROJECT_BIBLE/12_Pricing_Philosophy.md   (注記追加のみ)
  PROJECT_BIBLE/README.md                  (Version 8.0・目次)
  PROJECT_BIBLE/CHANGELOG.md               (v8.0エントリ)
  PROJECT_BIBLE/CURRENT_STATUS.md          (WEB-V2-8進捗)
  docs/reviews/tools/check-prices.js       (apply対応・キャンペーン検査)
  website-v2/index.html                    (CTA・キャンペーン・初期設定費)
  website-v2/features.html / pricing.html / company.html / contact.html /
  website-v2/privacy.html / terms.html / 404.html  (共通部品・CTA)
  website-v2/assets/css/base.css / components.css
  website-v2/sitemap.xml
  website-v2/README.md
```

privacy.html / terms.html の変更は**ヘッダー・フッター共通部品のみ**（本文無変更、check-legal.jsで確認済み）。

## 20. Git情報

- ブランチ: `website-v2`（masterは無変更・push未実施・マージ未実施）
- コミット: `WEB-V2-8: define sales model and add dual conversion paths`（テスト完了後に1コミット）
- 秘密情報のコミットなし（check-prices.js の秘密情報検査も通過）

## 21. 残存リスク

1. 「今すぐ申し込む」導線の先が案内ページであるため、公開時に期待値との差を感じる訪問者がいる可能性（準備中である旨を最上部で明示して緩和）
2. 特商法表記・税込総額表示が未整理のまま**セルフ申し込みを公開すると法令リスク**（第17節D。SALES-0で必ず解消）
3. 12_Pricing_Philosophy.md の4要素構造と単一プラン販売の不整合が残存（注記済み・代表判断待ち）
4. `smartlabo-works` リポジトリ側の `BUSINESS_STRATEGY.md` 等が本販売モデルを反映していない（別リポジトリのため未更新）
5. WEB-V2-7以前からの持ち越し: フォーム構成決定・法務2ページ・実メール到達確認・API未配置間のCSRF 404
6. ヘッダーの btn--sm はタップ領域が約36px（44px推奨より小さい）。v1から続く既存仕様のため今回は変更せず

## 22. 次工程案

既存ロードマップ（PROJECT_BIBLE/63_Post_Launch_Roadmap.md はホームページ公開後の運用系、13_Platform_Architecture は基盤系）と衝突しないため、指示書のSALES系名称をそのまま採用することを提案する。

| 工程 | 内容 | 備考 |
|---|---|---|
| SALES-0 | 販売・契約・課金の詳細仕様確定 | **法務確認事項(第17節)の解消を含めることを推奨**（特商法表記・キャンペーン規約・利用規約改訂・端数処理・解約/返金） |
| SALES-1 | セルフ申し込み画面・会社情報登録 | apply.html #status の差し替えで接続 |
| SALES-2 | クレジットカード決済・サブスクリプション | 日割り+翌月分の初回決済・毎月1日課金 |
| SALES-3 | キャンペーンコード・キャンペーン管理 | 残数管理を含む(表示は静的→実数へ) |
| SALES-4 | 紹介コード・流入計測 | |
| SALES-5 | 契約完了・会社アカウント自動作成 | `smartlabo-works`/`smartlabo-platform` 側の作業 |
| SALES-6 | AI初期設定ウィザード | 同上 |

順番の修正提案: SALES-1〜2は`smartlabo-works`側のアカウント基盤(SALES-5相当)に依存するため、実装順は 0→5(基盤)→1→2→3→4→6 になる可能性がある。着手前にSALES-0で依存関係を確定することを推奨。

並行して残っている代表判断: 問い合わせフォーム構成(案A/案B)・法務2ページ確定は本工程と独立して引き続き必要。

## 23. スクリーンショット

`docs/reviews/assets/web-v2-8/`

| ファイル | 内容 |
|---|---|
| apply-desktop-1440-full.webp | apply.html 全景(1440px・6,840px) |
| apply-mobile-375-full.webp | apply.html 全景(375px) |
| apply-choose-desktop-1440.webp | 2つの進め方セクション |
| apply-sticky-header-check.webp | スクロール中のヘッダー表示確認 |
| index-campaign-desktop-1440.webp / -mobile-375.webp | 創業記念キャンペーンセクション |
| index-hero-cta-desktop-1440.webp | ヒーローの主副CTA |
| index-final-cta-desktop-1440.webp | 最終CTAの主副2ボタン |
| pricing-plan-cta-desktop-1440.webp | 料金カードの主副CTA |
| header-cta-desktop-1440.webp / -mobile-375.webp / -tablet-521.webp | ヘッダーCTA(3幅) |

## 24. 代表判断が必要な事項

1. **追加アカウント料金をキャンペーン無料対象に含めるか**（未確定のまま。現在は「含みません」と表示 — 14番SSOTの記載どおり）
2. **特商法表記ページと税込総額表示の扱い**（第17節D。セルフ申し込み公開前に専門家確認が必要）
3. **12_Pricing_Philosophy.md の4要素構造(Small/Medium/Enterprise × Lite/Standard/Premium)の今後**: 廃止／将来の上位プラン向けに温存／改訂
4. **`smartlabo-works` リポジトリ側SSOT**(`BUSINESS_STRATEGY.md`等)への販売モデル反映を別途指示するか
5. company.html の最終CTAを「無料相談する」のままとした判断の承認（会社紹介ページの性質上、申込を主にしていない）
6. キャンペーンの適用期間（開始日・終了日）が未指定。「期間」の表示は現在「募集枠または期間の終了をもって終了」の表現のみ
7. 問い合わせフォーム構成（案A/案B）・法務2ページ確定（WEB-V2-7からの持ち越し）

---

*作成: Claude Code / WEB-V2-8（2026-07-28）*
