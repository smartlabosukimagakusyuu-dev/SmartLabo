# 61. v1.0 Release Checklist — 正式リリース前チェックリスト

このドキュメントは、Smart Labo Worksホームページ（`WEBSITE/` + `xserver-form/`）を
正式公開する際に使用する、再利用可能なチェックリストのテンプレートです。

Release Candidate 1（コミット`76a9767`）・Release Candidate 2（コミット`b3a5e8f`、
チェックリストの初版制定）を経て、今回「v1.0 Release Checklist」として、CEO指定の
18項目に沿って全面拡充しました。**このチェックリストの全項目にチェックが入った
時点で、下部「3. 提出条件」の手順に沿って「v1.0 Release」として提出します。**
将来の大型リリース（v2.0公開等）でも、この構成をそのまま使い回してください。

---

## 0. バージョン番号・タグの方針（要CEO確認）

現在、PROJECT_BIBLEの「Homepage Version」（`CURRENT_STATUS.md`参照、現在3.1）は、
社内での変更を追跡する**内部バージョン**です。これとは別に、対外的な正式公開の
節目には、**独立した公開バージョン番号**を付けることを提案します。

| 項目 | 提案 |
|---|---|
| 公開バージョン番号 | `v1.0.0`（セマンティックバージョニング。ホームページの正式初回公開として） |
| PROJECT_BIBLE Homepage Version | 引き続き社内の変更追跡用として独立運用（`v1.0.0`公開後も3.x系のまま更新を継続） |
| Gitタグ名（案） | `homepage-v1.0.0`（`SmartLabo` repoに付与） |
| タグを打つタイミング | 本チェックリスト全項目の確認完了後、CEOの明示的な承認を得たとき |

**Gitタグ・GitHub Releaseの作成は対外的に見える操作（公開アクション）にあたるため、
このチェックリストの作成・実施だけでは行いません。**

---

## 1. チェックリスト本体

各項目の凡例：✅=ローカル環境で確認済み　⏸=本番環境（実ドメイン・SSL・SMTP・
外部サービスアカウント）が必要なため未実施　❓=CEO判断が必要な未決定事項

### □ ホームページ

- [x] 全9ページ（index/product/features/real-estate/pricing/contact/company/privacy/terms）がHTTP 200で表示される
- [x] 全ページの内部リンク・アンカーリンク・フッター/ナビのリンクが解決する（自動巡回で確認済み）
- [x] 全ページでJavaScriptのconsoleエラーが発生しない
- [ ] 本番ドメイン（`https://smartlaboworks.com`）切替後、同様にリンク切れ・consoleエラーが発生しないことを再確認

現状：✅ ローカルで確認済み　⏸ 本番ドメインでの最終確認が残る

---

### □ 問い合わせフォーム

**2026-07-16、CEO最終公開指示により方針変更：v1.0.0では本番フォームを非公開とし、`contact.html`のフォームブロックを撤去して`info@smartlaboworks.com`へのmailtoリンクに差し替えた（本番送信テストが完了していない機能を公開しない、というCEO方針に基づく）。以下のチェック項目はフォーム自体の実装状況の記録として残すが、v1.0.0の公開判定には影響しない。フォームの本番稼働はVersion1.1で対応する。**

- [x] クライアント側バリデーション（必須項目・メール形式）
- [x] honeypot・送信速度トラップが人間の通常操作を妨げない
- [x] 送信中の二重送信防止（送信ボタンdisable）
- [x] 送信成功時、専用の完了画面（受付番号表示）へ切り替わる
- [x] 送信失敗時、状況別のエラーメッセージが表示される（バリデーション/レート制限/CSRF失効/通信エラー等）
- [ ] 実際に`form.smartlaboworks.com`へ送信が届き、完了画面が表示される（→v1.1で実施）
- [ ] レート制限（1時間5回）・二重送信検知（10分）が本番のSQLite環境でも機能する（→v1.1で実施）

現状：ロジックはローカルPHP環境で全経路検証済み（23件のユニットテスト＋実HTTP統合テスト）。v1.0.0では非公開化(mailto代替)、実ドメインでの送受信確認・本番稼働はv1.1で実施

---

### □ SSL

- [ ] `https://smartlaboworks.com` が証明書エラーなく表示される（GitHub Pages側。**GitHubリポジトリのSettings→Pagesでカスタムドメイン設定・SSL自動発行の完了が前提**）
- [ ] `https://form.smartlaboworks.com` が証明書エラーなく表示される（XServer側。**CEO報告により有効化済み、実機確認は未実施**）
- [ ] `http://` アクセス時に `https://` へ自動リダイレクトされる（`xserver-form/public/.htaccess`で実装済み。GitHub Pages側は標準で自動リダイレクト）
- [ ] 混在コンテンツ（HTTP経由の画像・スクリプト等）警告が出ない

**2026-07-16、`nslookup`/`curl`による実測を実施した結果を追記：** `smartlaboworks.com`・`www.smartlaboworks.com`・`form.smartlaboworks.com`はいずれも同一のXserver IPアドレスを指しており、`https://smartlaboworks.com/`へアクセスすると本サイトではなくXserverの初期設置ページ(コンテンツ未アップロード時の標準ページ)が表示される状態を確認した(HTTPS自体は有効でSSL証明書エラーは出ない)。GitHub Pages側のカスタムドメイン設定・DNSのAレコード切替(4件、[62_CEO_Publish_Guide.md](62_CEO_Publish_Guide.md)②参照)がまだ実施されていないことを意味する。

現状：⏸ 本番ドメイン確定後に実施。**2026-07-16時点で未着手であることを実測で確認済み**

---

### □ メール送受信

- [ ] 管理者通知メールが`info@smartlaboworks.com`に届く
- [ ] 自動返信メールが送信者のアドレスに届く
- [ ] 件名・本文の日本語が文字化けしていない（`SmtpMailer.php`はbase64エンコードで送信）
- [ ] 迷惑メールフォルダに振り分けられていない
- [ ] `smtp_encryption`（tls/ssl）がXServerの実際のSMTP要件と一致している

現状：⏸ `config.php`本番設定・実際のSMTP接続確認が前提のため未実施

---

### □ スマホ表示

- [x] 全9ページ、375px幅で横スクロールなし
- [x] 問い合わせフォーム・Smart Labo Configurator等のインタラクティブ要素もモバイルで確認済み
- [x] honeypot・送信完了画面等の新規UIもモバイルで表示崩れなし

現状：✅ 確認済み

---

### □ PC表示

- [x] 全9ページ、デスクトップ幅（1440px基準）でレイアウト崩れなし
- [x] Hero「AI Control Center」・Smart Labo Configurator・会社概要テーブル等、主要コンポーネントの表示を確認済み

現状：✅ 確認済み

---

### □ SEO

- [x] 全ページの`title`・`meta description`設定済み
- [x] `canonical`タグ設定済み（正式ドメイン`https://smartlaboworks.com`）
- [x] 構造化データ（Organization、JSON-LD）を`index.html`に追加。未確定の会社情報は含めていない
- [ ] Google Rich Results Test等での構造化データの妥当性確認（本番URL公開後）

現状：✅ 実装完了　⏸ 外部ツールでの検証は本番URL公開後

---

### □ OGP

- [x] 全ページ（法務2ページ除く）に`og:title`/`og:description`/`og:url`/`og:image`/Twitter Card設定済み
- [x] URLはすべて正式ドメインに統一済み
- [ ] Facebook Sharing Debugger・Twitter Card Validator等で実際のシェアプレビュー表示を確認（本番URL公開後）

現状：✅ 設定完了　⏸ 実際のシェアプレビュー確認は本番公開後

---

### □ favicon

- [x] 全9ページで表示確認済み（`img/logo/favicon.png`）

現状：✅ 確認済み

---

### □ robots.txt

- [x] 新設・ローカルで200応答確認済み
- [x] `Allow: /`＋`Sitemap:`参照。`noindex`ページ（privacy/terms）はrobots.txtでの`Disallow`ではなく既存のmetaタグに一本化（クローラーがnoindex指示を認識できるようにするため、Google推奨方式）

現状：✅ 確認済み

---

### □ sitemap.xml

- [x] 新設・ローカルで200応答確認済み
- [x] index対象7ページを掲載、`noindex`の2ページ（privacy/terms）は除外
- [ ] 本番公開後、Google Search Consoleへ送信

現状：✅ ファイル作成・ローカル確認済み　⏸ Search Console送信は次項と合わせて実施

---

### □ 404ページ

- [x] 存在しないURLへアクセスすると`404.html`が正しく表示される
- [x] consoleエラーなし
- [x] `<meta name="robots" content="noindex">`設定済み（検索結果に404ページが表示されないように）

現状：✅ 確認済み

---

### □ Google Search Console登録

- [ ] `smartlaboworks.com`をGoogle Search Consoleへ登録し、所有権を確認する（DNS TXTレコード追加、またはGoogle Pagesでのメタタグ確認等）
- [ ] `sitemap.xml`を送信する
- [ ] インデックス登録状況を確認する

現状：❓⏸ **CEOのGoogleアカウントでの登録作業が必要（Claude Codeでは実施不可）。本番公開後に着手**

---

### □ Google Analytics登録

- [ ] **導入するかどうかをまずCEOに確認する（要CEO判断）**：現在の[privacy.html](../WEBSITE/privacy.html)は「アクセス解析ツールを導入していない」旨を明記しており、導入する場合はこの記載を先に更新する必要がある
- [ ] 導入する場合：Google Analytics 4のプロパティを作成し、トラッキングIDを取得（CEO作業）
- [ ] 導入する場合：`WEBSITE/`各ページへ計測タグを追加する実装(別タスクとして着手)
- [ ] 導入する場合：プライバシーポリシーのCookie/アクセス解析の章を更新する

現状：❓ **未着手・CEO判断待ち。現時点でホームページにアクセス解析は一切導入していない**

---

### □ PageSpeed

- [ ] 本番URL公開後、Google PageSpeed Insightsで計測
- [x] 事前所見：Webフォント不使用（システムフォントのみ、良好）、主要画像はWebP化済み（良好）、CSS/JS未minify（改善余地あり、今回は未実施）

現状：⏸ 実施は本番URL公開後（ローカル/暫定URLでの計測は参考値にとどまるため）

---

### □ GitHub Release

- [ ] タグ`homepage-v1.0.0`（0章参照）を作成し、GitHub Releaseを公開
- [ ] リリースノートに本チェックリストの結果・「更新履歴」章の内容を記載

現状：**未実施。全項目の確認完了後、CEOの明示的な指示を得てから実施します（対外公開を伴う操作のため）。**

---

### □ 更新履歴

以下は今回のリリースサイクル（「正式リリース前 最終整備」〜Release Candidate 2）で
実施した主要な変更のまとめです。詳細な日次記録は[CURRENT_STATUS.md](CURRENT_STATUS.md)を参照してください。

| 分類 | 内容 |
|---|---|
| 会社情報 | 会社概要ページ(`company.html`)新設。未確定の会社情報(代表者・所在地等)は「CEO確認待ち」と明示 |
| 法務 | プライバシーポリシー・利用規約のドラフト作成(専門家確認前提の注記付き) |
| 導入プラン | `pricing.html`を「Smart Labo Configurator」(5Stepシミュレーター)へ刷新 |
| 問い合わせフォーム | XServer(PHP)による送信基盤を新規実装(`xserver-form/`)。CSRF・レート制限・入力値検証・honeypot・reCAPTCHA(開発/本番モード切替)・送信完了画面を実装 |
| メール | `info@smartlaboworks.com`によるSMTP送信(管理者通知・自動返信)、tls/ssl両対応 |
| SEO | `robots.txt`・`sitemap.xml`新設、構造化データ(Organization)追加、全ページのURLを正式ドメインへ統一 |
| 公開準備 | `WEBSITE/CNAME`新設(GitHub Pagesカスタムドメイン設定用) |
| リリース管理 | 本チェックリスト(61_Release_Checklist.md)を新設 |

- [x] 上記の更新履歴を本チェックリストへ記載済み

---

### □ 既知の制限事項（Known Issues）

2026-07-14、CEO指示「Known Issuesを最新版へ更新し、v1.0で対応しない項目／v1.1予定項目／将来構想を整理してください」に基づき、3階層へ再整理しました。

#### v1.0で対応しない項目（公開時点で未解決のまま進める、CEO了承前提）

| 項目 | 内容 | 対応方針 |
|---|---|---|
| 会社情報の一部未確定 | 代表者(小川昌利)・設立年月(2026年7月)・事業内容は2026-07-16にCEO確認済みで反映済み。**所在地・電話番号・資本金・代表者略歴は引き続き未確定**（[company.html](../WEBSITE/company.html)に「CEO確認待ち」と明示、推測掲載はしない） | CEO確認後、`js/company-info.js`の値を更新すれば全ページへ反映 |
| 問い合わせフォーム非公開 | 2026-07-16のCEO最終公開指示により、本番送信テスト未完了のフォームを`contact.html`から撤去し`info@smartlaboworks.com`へのmailtoリンクへ差し替え(意図的な仕様、実装自体は完了済みで撤去のみ) | Version1.1で送信テスト実施後、フォームを再有効化 |
| 法務ページがドラフト | プライバシーポリシー・利用規約は専門家未確認（両ページに明記済み） | 正式公開前後に弁護士等の確認を推奨 |
| 特定商取引法表記 | 該当性の最終判断が未了（BtoB契約のため不要な可能性が高いが未確定） | 専門家確認後、要否に応じて対応 |
| reCAPTCHA未設定 | 現在は開発モード（honeypot・送信速度トラップ・CSRF・レート制限の4層のみでbot対策）。フォーム自体v1.0.0では非公開のため優先度低 | Version1.1のフォーム再有効化時に、CEOがGoogleでキーを発行し本番モードへ切替（[62_CEO_Publish_Guide.md](62_CEO_Publish_Guide.md)⑤参照） |
| Google Search Console未登録 | サイト所有権確認・sitemap送信が未実施 | Version1.1でCEOのGoogleアカウントにより登録（2026-07-16 CEO確定） |
| Google Analytics未導入 | アクセス解析なし | Version1.1で対応（2026-07-16 CEO確定。導入時はprivacy.html更新が先） |
| AIチャット実API接続 | ホームページのAIチャットウィジェットは事前定義のデモ回答のみ、実際のSmart AI Routerには未接続 | Version1.1で対応（2026-07-16 CEO確定） |
| DNS/GitHub Pages切替未完了 | 2026-07-16のDNS実測により、`smartlaboworks.com`がGitHub PagesではなくXserverの初期設置ページを指していることを確認。GitHub Pages側のカスタムドメイン設定・DNSのAレコード切替が未実施 | CEO(または担当者)が[62_CEO_Publish_Guide.md](62_CEO_Publish_Guide.md)①②を実施 |
| Company Brain・CRM等の非公開機能 | Company OS・Builder・Innovation Hub等は顧客向けページに一切表示しない仕様（恒久的な意図的仕様） | `PRODUCT_BOUNDARY.md`の分類どおり。将来的に公開する計画自体が現時点でない |

#### v1.1予定項目（次の改善サイクルで対応を見込む）

| 項目 | 内容 | 対応方針 |
|---|---|---|
| CSS/JS未minify | `css/style.css`(約72KB)・各JSファイルが未圧縮 | 正式公開後、PageSpeed実測結果を踏まえて対応 |
| レート制限の実装方式 | XServer共有サーバー上のSQLite3による実装（小規模な問い合わせフォームには十分だが、アクセス急増時は再検討が必要） | トラフィック状況を見て、必要に応じて外部ストア(Redis等)への移行を検討 |

#### 将来構想（時期未定、smartlabo-works本体のロードマップに依存）

| 項目 | 内容 | 対応方針 |
|---|---|---|
| AIチャットウィジェット | `index.html`のチャットは事前定義のデモ回答のみで、実際のSmart AI Routerには接続していない（2026-07-12実装時からの既知の制約） | 実際のAPI接続はsmartlabo-works側の本番デプロイ後に検討([63_Post_Launch_Roadmap.md](63_Post_Launch_Roadmap.md)優先度①) |
| 不動産以外の業種テンプレート | 司法書士・税理士・管理会社等のテンプレートは「Coming Soon」表示（実体未実装） | `smartlabo-works`側のロードマップに準拠、時期未定 |
| Pricing Manager・CRM・Company Brain連携等 | ホームページから見せない社内機能群 | [63_Post_Launch_Roadmap.md](63_Post_Launch_Roadmap.md)を参照(いずれもCompany OS側・smartlabo-works側の別Task) |

- [x] Known Issuesを3階層(v1.0で対応しない項目／v1.1予定項目／将来構想)へ整理し、本チェックリストへ記載済み

---

## 2. 項目別ステータスまとめ

| 状態 | 該当項目数 | 項目 |
|---|---|---|
| ✅ ローカル確認済み | 9 | ホームページ／問い合わせフォーム(ロジック)／スマホ表示／PC表示／SEO(実装)／OGP(実装)／favicon／robots.txt／sitemap.xml／404ページ／更新履歴／既知の制限事項 |
| ⏸ 本番環境が必要 | 6 | SSL／問い合わせフォーム(実送受信)／メール送受信／SEO(外部検証)／OGP(実プレビュー)／PageSpeed |
| ❓ CEO判断・作業が必要 | 3 | Google Search Console登録／Google Analytics登録／GitHub Release |

---

## 3. 提出条件

上記チェックリストの✅・⏸項目すべてにチェックが入り、❓項目（Search Console登録・
Analytics導入要否の決定・GitHub Release作成）についてCEOの判断・承認を得た時点で、
このドキュメントを「v1.0 Release」として提出します。GitHubタグ・Releaseの作成、
公開バージョン番号`v1.0.0`の確定は、その最終段階でCEOの明示的な承認を得てから
実施します。

---

## 変更履歴

| バージョン | 日付 | 変更者 | 変更内容 |
|---|---|---|---|
| v1.0 | 2026-07-14 | Claude Code(CEO指示による) | 新規作成。Release Candidate 2として、本番公開前チェックリスト(SSL/問い合わせ/メール/リンク/Console Error/favicon/OGP/sitemap/robots/SEO/モバイル/PageSpeed/GitHub Release Tag/バージョン番号/更新履歴の15項目)を制定。Release Candidate 1(コミット`76a9767`)時点の確認状況を反映し、本番環境が必要な項目は⏸として明示。バージョン番号は`v1.0.0`(ホームページ正式公開)を提案、Gitタグ・GitHub Releaseの作成はCEOの明示的な承認後に実施する方針を明記 |
| **v2.0** | 2026-07-14 | Claude Code(CEO指示による) | **「v1.0 Release Checklist」として全面拡充。** 「Release Candidate 2完了後、正式リリース前のv1.0 Release Checklistを作成してください」というCEO指示に基づき、CEO指定の18項目(ホームページ/問い合わせフォーム/SSL/メール送受信/スマホ表示/PC表示/SEO/OGP/favicon/robots.txt/sitemap.xml/404ページ/Google Search Console登録/Google Analytics登録/PageSpeed/GitHub Release/更新履歴/既知の制限事項)へ再編。新規追加：「PC表示」(デスクトップ表示の明示的な確認項目)、「404ページ」(既存確認内容を独立項目化)、「Google Search Console登録」(CEOのGoogleアカウント作業が必要と明記)、「Google Analytics登録」(現時点で未導入、導入要否は要CEO判断とし、導入する場合はprivacy.htmlの更新が先に必要である旨を明記)、「既知の制限事項(Known Issues)」(10項目、意図的なスコープ外事項と今後の対応方針を整理)。「リンク」「Console Error」は「ホームページ」章へ統合。項目別ステータスまとめ(2章)・提出条件(3章)を新設し、全項目チェック完了後に「v1.0 Release」として提出する運用を明記 |
| **v3.0** | 2026-07-14 | Claude Code(CEO指示による) | **「Release Candidate 2 最終仕上げフェーズ」Task1-5・7・8を反映。** SEO最終確認(構造化データへWebSiteスキーマ追加)・公開後運用準備(GSC/GA/Cookie同意のプレースホルダーコメントを全ページへ設置、解析コードは未実装のまま)・問い合わせフォーム最終確認(SmtpMailer.phpへSMTPヘッダー・コマンドインジェクション対策を多層防御として追加)・公開前品質確認(Safari向け`-webkit-backdrop-filter`追加、company-info.js等3件の陳腐化情報を修正、コミット`32f3a28`)を実施(Task1-4)。既知の制限事項を「v1.0で対応しない項目／v1.1予定項目／将来構想」の3階層へ再整理し、Google Search Console未登録を明示項目として追加(Task5)。新規ドキュメント[62_CEO_Publish_Guide.md](62_CEO_Publish_Guide.md)(CEO向け8ステップ公開手順書、Task7)・[63_Post_Launch_Roadmap.md](63_Post_Launch_Roadmap.md)(公開後ロードマップ、Task8)・[64_Release_Notes_v1.0.0.md](64_Release_Notes_v1.0.0.md)(Release Notes、Task6)を追加し、本チェックリストから相互参照した |
| **v4.0** | 2026-07-16 | Claude Code(CEO最終公開指示による) | **Homepage v1.0.0確定に伴う更新。** 会社情報(代表者名・設立年月・事業内容)がCEO確認済みとなったことを反映。問い合わせフォームを本番送信テスト未完了のためv1.0.0では非公開化しmailtoリンクへ差し替えたことを明記し、フォーム関連項目は「v1.1で対応」へ整理。SSL章に2026-07-16実施のDNS実測結果(`smartlaboworks.com`がGitHub PagesではなくXserver初期設置ページを指している)を追記。既知の制限事項に「問い合わせフォーム非公開」「AIチャット実API接続」「DNS/GitHub Pages切替未完了」の3項目を追加し、AIチャット・フォーム本番・GA・Search ConsoleはいずれもVersion1.1で対応するとCEOが確定したことを反映 |

*最終更新: 2026-07-16*
