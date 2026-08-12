# WEB-V3-2: Website V3 公開前 商品仕様・販売表現・導線 最終監査

- 実施日: 2026-08-07
- 対象ブランチ: `website-v3` / 着手時コミット: `931bc02`(WEB-V3-1)
- 対象: `website-v3/**`
- 製品照合先: `C:\Users\user\Desktop\smartlabo-works-lite`(読み取りのみ・本番RELEASE_COMMIT `18b15a7` 系列、ローカル先端 `36b7177`)
- 本工程で**本番公開・masterへのmerge・WEBSITE/**変更・contact-api本番デプロイは行っていない**。

---

## 1. Website V3 概要

Website V3 は Version 2(全9公開ページ+内部1ページ)をベースに、WEB-V3-1 で
新機能3つ(AI資料ボックス・社内マニュアルAI・全体検索)の掲載、トップの
コンセプト図解(#connect)、導入前後の拡充、資料請求導線を追加したもの。
素のHTML/CSS/JS・ビルドなし・外部CDNなし・計測タグなしの構成は維持。

## 2. 全ページ一覧

| ページ | 役割 | index対象 | canonical | 備考 |
|---|---|---|---|---|
| index.html | トップ | ○ | ○ | 図解・機能・料金・キャンペーン・FAQ |
| features.html | 機能詳細(11機能) | ○ | ○ | |
| pricing.html | 料金 | ○ | ○ | キャンペーン記載なし(正本) |
| apply.html | 申込案内 | ○ | ○ | キャンペーン掲載可ページ |
| company.html | 会社情報 | ○ | ○ | |
| contact.html | お問い合わせ(フォーム) | ○ | ○ | 種別に「資料請求」追加 |
| privacy.html / terms.html | 法務 | ○ | ○ | 本文はV1と完全一致(check-legal) |
| 404.html | Not Found | noindex | なし | |
| signup.html | 申込手続き(内部) | noindex | なし | 公開導線なし・「準備中」明示 |

## 3. 商品機能照合表(Website記載 × Lite実コード)

| Website V3 の記載 | Lite実装の根拠(コード) | 判定 |
|---|---|---|
| AIアシスタント(顧客を選ぶと状況を踏まえる) | `server/ai/prompts/chatPrompt.js`(顧客・記録・タスク・予定の文脈注入) | 一致 |
| 顧客・取引先管理 | `server/routes/customers.js` ほか | 一致 |
| 名刺の読み取り(入力サポート) | `server/services/businessCardImportService.js`(OCR→AI整理→**保存は利用者確認後の既存顧客登録APIのみ**・自動保存なし) | 一致 |
| 活動記録 / AIによる記録整理 | `server/routes/records.js`・`recordOrganizePrompt.js` | 一致 |
| タスク管理 / スケジュール管理 | `taskRepository.js`・`scheduleRepository.js` | 一致 |
| AI文章作成(用途別下書き・送信しない) | `documentPrompt.js`・`documentDrafts.js`・src/components/documents | 一致 |
| AI資料ボックス(保管・読み取り・整理下書き) | `docBoxService.js`・`docBoxExtractionService.js`(PDF/Word/Excel本文抽出)・`docBoxOcrService.js`(画像・スキャンPDF OCR) | 一致 |
| 対応形式 PDF/Word/Excel/JPEG/PNG/WebP/スキャンPDF | `server/utils/uploadPolicy.js` ALLOWED = pdf/docx/xlsx/jpg/jpeg/png/webp。HEICなし | 一致(HEIC非掲載を確認) |
| OCR利用量の上限あり | `config.js` DOCBOX_OCR_DAILY/MONTHLY_PAGE_LIMIT・`ocrUsageRepository.js` | 一致(WEB-V3-2で明記を追加) |
| 社内マニュアルAI(登録資料だけを根拠・参照資料表示・根拠なしは確認できない旨) | `manualAskPrompt.js`(一般知識で補わない・推測禁止・found=false定型文・referenceIds実在確認)・`companyDocsSearchService.js` | 一致 |
| 全体検索(7対象) | `searchService.js` SEARCHERS = customer/task/schedule/record/document/manual/docBoxItem | 一致 |
| 会社ごとにデータ分離・自社の情報のみ | `middleware/session.js` companyIdOf(セッション由来のみ)・search.jsコメント | 一致 |
| 利用者・権限管理 | `routes/users.js`・viewer(role/canViewAll) | 一致 |
| 外部カレンダー同期は行っていない(明記) | 該当実装なし | 一致 |

サイト未掲載だが実装済み: AI利用ログ(`aiUsageLogRepository.js`)・OCR利用管理・監査ログ。
掲載は必須ではない(過剰表現ではないため監査上は問題なし)。

## 4. 基本プラン掲載機能(11)

1アカウント / AIアシスタント / 顧客・取引先管理 / 活動記録 / タスク管理 /
スケジュール管理 / AI文章作成 / AI資料ボックス / 社内マニュアルAI / 全体検索 /
初期導入サポート
— index.html(#pricing)・pricing.html(#included)で同一の11項目であることを確認。

## 5. A / B / C 分類

- **A(本番実装済み・記載可)**: 上記11機能すべて＋名刺読み取り。§3のとおり全件コード根拠あり。
- **B(実装済みだが表現注意 → 本工程で修正済み)**:
  - 全体検索の対象列挙が実装(7対象)と不一致 → 「顧客・タスク・予定・記録・文書・社内資料」へ統一(4ファイル)
  - 社内マニュアルAIに「参照資料表示・根拠なし時の挙動」が未記載 → 追記
  - AI資料ボックスの対応形式・OCR上限が未記載 → 追記
  - 「会社の記憶になります」(学習・理解を連想) → 「どこからでも探せるようになります」へ
  - company.html「あらゆる中小企業」 → 「中小企業」へ(全業種断定の回避)
  - 「探す時間が本来の仕事に戻ります」 → 「充てられます」へ(効果断定の緩和)
- **C(未実装・将来機能)**: Company Brain高度機能(改善提案・矛盾検知・不足資料提案・
  成熟度分析・教育プラン・定期自動分析)・AI議事録・AI電話分析・外部カレンダー連携・
  社内アンケート — **Website V3 に記載0件であることをgrepで確認**(COMING SOON表記も不要)。
  現行の「社内マニュアルAI=検索して根拠つきで答える」との境界は守られている。

## 6. 料金照合

- 初期設定費 10,000円 / 月額基本 20,000円 / 追加アカウント 3,000円(すべて税別)
- 掲載4ページ(index/pricing/apply/signup)の全data-price一致・計算例(1人20,000/3人26,000/5人32,000)一致・税別表記あり
- `node docs/reviews/tools/check-prices.js` → **[OK]**(不一致0件)
- 構造化データに価格LDなし(価格の二重管理は発生しない)

## 7. CTA

主CTA「今すぐ申し込む」(apply.html)・副CTA「無料相談する」(contact.html)を全ページで統一。
第3導線として「資料請求」(contact.html?type=docs#form)。3者は文言・リンク先で明確に区別され、
混同する表現はない。

## 8. 資料請求

- 導線: ヒーロー下リンク / トップ最終CTA / 全10ページのフッター / トップFAQ(faq-q9)
- リンク先 `contact.html?type=docs#form` — リンク切れ0(機械確認)
- `?type=docs` で種別を自動選択(contact-form.js、値の実在確認つき)。JS無効でも手動選択可能
- 種別ラベル「資料請求（サービス紹介資料）」

## 9. Contact API

- `contact-api/public/lib/validate.php` の許可リストに `docs` を追加済み(WEB-V3-1、リポジトリ内のみ)
- **本番XServer側は未反映**。Websiteだけ切替すると資料請求送信がAPIで拒否される
- → §18 Runbook の**必須項目**として「contact-api の docs 対応を先行または同時反映」を記載

## 10. SEO

- title: 全10ページ重複なし(15〜35字)
- meta description: 重複なし。新機能キーワード(AI資料ボックス・社内マニュアルAI・全体検索・OCRは本文中)を自然に含む。詰め込みなし
- canonical: 公開8ページに設定。404/signup(noindex)はなし — 適切
- OGP/Twitter: 全公開ページに設定
- robots.txt / sitemap.xml: 公開8ページのみ掲載。signup・404は非掲載
- 見出し階層: h1(各ページ1つ)→h2→h3 の順序維持(新規セクションも準拠)
- 内部リンク: href/src/srcset 全件の存在確認 → 0エラー。ページ内アンカーも0エラー

## 11. モバイル

- 375 / 768 / 1024 / 1440px で横はみ出しなし(scrollWidth==innerWidth を実測)
- 図解SVG: 640px以下は figure 内の横スクロール方式(min-width:640px)
- **所見**: 横スクロール可能であることが視覚的に伝わりにくい。将来改善案として
  (a)スクロールヒントの表示、または (b)モバイル専用の縦積み図解(ノードを縦に並べる)を提案。
  ブランド変更を伴わないCSS/HTML内の変更で対応可能。本工程では未実施(勝手なレイアウト変更をしない方針)

## 12. アクセシビリティ

- 画像: 意味のある画像に具体的alt・装飾はalt=""+aria-hidden。width/height全指定(CLS防止)
- 図解SVG: role="img"+title/desc、装飾線はaria-hidden
- フォーム: 全項目label・エラーはaria-describedby/aria-invalid/role=status
- FAQ: button+aria-expanded/aria-controls、JS無効時は全開で読める
- :focus-visible 3pxリング全ページ・skip-link・キーボード操作可
- prefers-reduced-motion: 出現アニメーション・図解の点線アニメーションとも停止
- 色だけに依存する表現なし(NEWバッジは文字併記)

## 13. パフォーマンス

- 外部CDN 0 / 外部フォント 0 / 計測タグ 0(維持)
- JS 3本のみ(main/contact-form/signup)・フレームワークなし
- 画像: WebP・srcset/sizes・折りたたみ下は loading="lazy"・ヒーローは fetchpriority="high"
- 図解はインラインSVG(画像リクエスト0・テーマ色はCSS変数)
- 404アセット 0(機械確認)。※ローカル確認時の /api/csrf-token.php 404 は静的サーバーに
  PHPが無いためで、本番(XServer)には存在する既存設計

## 14. セキュリティ

- 静的サイト内の秘密情報: APIキー・パスワード・秘密鍵・内部IP・DB情報・サーバーパス・
  個人メール・テスト認証情報 → **0件**(独自grep+check-prices.jsの秘密情報検査)
- 問い合わせフォーム(contact-api、既存実装の確認):
  CSRF(HMACトークン+TTL、csrf-token.php発行) / 種別allowlist(validate.php) /
  文字数上限(会社名100・氏名100・メール254・本文3000・本文20KB) /
  メール検証(filter_var) / HTMLエスケープ(htmlspecialchars ENT_QUOTES) /
  ヘッダーインジェクション対策(\r\n\0除去+mb_encode_mimeheader) /
  bot対策(honeypot+時間差+送信間隔制限 3回/600秒+Origin/Referer確認)

## 15. 修正推奨事項(非ブロッカー・次工程以降の改善案)

1. 名刺読み取りを独立カード/機能詳細として昇格(現状は顧客管理内の1文)。
   優先6機能(AIアシスタント/CRM/名刺OCR/AI資料ボックス/社内マニュアルAI/全体検索)を
   上段に、残りを補助機能として整理するレイアウト案 — 大規模変更のため未実施
2. モバイル図解の縦型化またはスクロールヒント(§11)
3. システム画面スクリーンショット(app-*.webp)を現行Lite UIと目視照合し、
   乖離があれば撮り直し(架空データのみ使用)
4. AI利用ログ・OCR利用管理・監査ログは「管理機能」として掲載する価値あり(任意)
5. 将来、Company Brain等を予告掲載する場合は「今後提供予定」表記で新設セクションを作る
   (日付・月は書かない)

## 16. 本番公開ブロッカー

| # | 項目 | 状態 |
|---|---|---|
| 1 | contact-api本番の `docs` 種別未対応 | **ブロッカー**。Runbookで先行/同時反映すれば解消 |
| 2 | dev-banner(全10ページ) | 切替コミットで削除(Runbook手順) |
| 3 | それ以外 | なし。表現・料金・導線・SEO・A11y・セキュリティは監査合格 |

## 17. Go / No-Go

**コンテンツ・表現・導線: Go**(本監査の修正反映後、過剰表現0件・実装との不一致0件)。
**公開作業: 条件付きGo** — §16の2条件をRunbookどおり処理すること。
公開の最終判断は代表の明示承認(WEB-V3-RELEASE工程)による。

## 18. 本番公開Runbook案(WEB-V3-RELEASE、代表承認後にのみ実行)

1. 【必須・先行】XServer の contact-api を更新(validate.php の `docs` 追加を反映)。
   反映後、既存種別での送信が引き続き成功することを確認
2. `website-v3` ブランチで最終確認(全ページ・リンク・レスポンシブ・ロゴ・検証2スクリプト[OK])
3. 全ページから dev-banner の行を削除
4. `master` を最新化し `release/website-v3` ブランチを作成
5. `WEBSITE/` の中身を v3 で置き換える。**必ず残す**: `WEBSITE/CNAME`(smartlaboworks.com)・
   `WEBSITE/app.html`。v1固有URL(product.html / real-estate.html)の扱いは代表に確認
6. 差分が `WEBSITE/` 以外に無いことを確認してコミット・push(ここで初めてActionsが発火)
7. `Deploy Homepage to GitHub Pages` の成功を確認
8. https://smartlaboworks.com/ を実ブラウザで確認(HTTPS・favicon・全ページ・
   資料請求フォーム送信テスト=種別docsで1件送信し受信確認)
9. 問題発生時: `git revert <切替コミット>` で v1 へ切り戻し(README §9)。
   contact-api側の docs 追加は後方互換のため切り戻し不要

---

## 検証記録(WEB-V3-2)

- check-prices.js → [OK] / check-legal.js → [OK](修正反映後に再実行)
- 内部リンク・アンカー・アセット存在: 0エラー / title・description重複: 0件
- 秘密情報grep: 0件 / 禁止・過剰表現grep: 残存0件(否定文・注釈・法務文の適法な使用のみ)
- 全10ページ HTTP 200・consoleエラー0(V3起因のもの)
- 375/768/1024/1440px 横はみ出し0

*作成: WEB-V3-2(2026-08-07)*
