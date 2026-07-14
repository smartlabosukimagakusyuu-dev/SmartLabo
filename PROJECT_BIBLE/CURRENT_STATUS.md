# CURRENT_STATUS

**PROJECT_BIBLEの現在地 — ChatGPT / Claude Code / Codex 共通の同期ポイント**

> 新しいChatGPTの会話を始める前に、必ずこのファイルを確認してください。下記の「5行サマリー」をそのままChatGPTに貼り付ければ、認識のズレなく議論を始められます。このファイルはCEOの指示により、PROJECT_BIBLEおよびGitHubの管理責任者(Claude Code)が常に最新版を維持します。

---

## 5行サマリー(ChatGPTに共有する用)

```
Project Bible Version：6.7
Brand Version：5.1
Design Bible Version：2.3
Homepage Version：3.1（Release Candidate 1。全ページのcanonical/OGPを正式ドメインsmartlaboworks.comへ切替。robots.txt/sitemap.xml/構造化データ(Organization)を新設。config.phpを必要最低限の9項目へ整理、SMTP暗号化方式(tls/ssl)を選択可能に）。GitHub Pagesで公開中: https://smartlabosukimagakusyuu-dev.github.io/SmartLabo/（GitHubリポジトリ側のカスタムドメイン設定はCEOアクション待ち）
Smart Labo Works：正式コードベースは別リポジトリ`smartlabo-works`(Node.js版)。詳細Versionは同リポジトリの`PRODUCT_REQUIREMENTS.md`を参照
Current Task：Release Candidate 2として、本番公開前チェックリスト(61_Release_Checklist.md、15項目)を新設。SSL/問い合わせ/メール/PageSpeedは本番環境が前提のため未実施、リンク/Console Error/favicon/OGP/sitemap/robots/SEO/モバイルはローカル確認済み。公開バージョン番号v1.0.0・Gitタグhomepage-v1.0.0を提案、実行はCEO承認後
```

**用語・体制の重要な変更(2026-07-10)：**
1. 「Company OS」は株式会社スマートラボの**社内専用システム**の固有名詞として正式に確定した(非売品)。これまでDashboard/Smart Labo Worksの設計思想を説明する形容として使っていた「Company OS」という語は廃止し、以降Smart Labo Works側の説明には使用しない。
2. **Smart Labo Worksの唯一の正式コードベースは別リポジトリ`smartlabo-works`(Node.js版)に確定した。** 本リポジトリ内の`WEBSITE/app.html`(GitHub Pages公開版)はSmart Labo Works正式製品ではなく、**デモサイト／マーケティング用プレビュー**として扱う。今後、本ファイルでは`WEBSITE/app.html`を「Smart Labo Works Version」として追跡しない(下記ステータス一覧を参照)。

詳細は [11_Development_Principles.md](11_Development_Principles.md)「製品境界の定義」、[00_Foundation/08_SmartLaboWorks_Concept.md](00_Foundation/08_SmartLaboWorks_Concept.md)「唯一の正式コードベース」を参照。

---

## ステータス一覧

| 項目 | 値 |
|---|---|
| Project Bible Version | 6.7 |
| Brand Version | 5.1 |
| Design Bible Version | 2.3 |
| Homepage Version | 3.1 — GitHub Pagesで公開中([https://smartlabosukimagakusyuu-dev.github.io/SmartLabo/](https://smartlabosukimagakusyuu-dev.github.io/SmartLabo/))。Release Candidate 1：canonical/OGPを正式ドメインへ切替、robots.txt/sitemap.xml/構造化データを新設 |
| デモサイト Version（`WEBSITE/app.html`） | **デモ v1.0（2026-07-07完成、2026-07-10にデモとして再位置づけ）。GitHub Pages公開中。全9ページ・デモデータ(顧客5件・契約7件・物件6件)。Smart Labo Works正式製品ではない** |
| Smart Labo Works Version（正式コードベース） | 本ファイルでは追跡しない。**別リポジトリ`smartlabo-works`の`PRODUCT_REQUIREMENTS.md`／`PRODUCT_BOUNDARY.md`を参照** |
| Current Project | Company Setup → **Version1.0リファクタリング（2026-07-10 CEO承認）** |
| Current Task | **Release Candidate 2：本番公開前チェックリスト新設。** [61_Release_Checklist.md](61_Release_Checklist.md)に15項目(SSL/問い合わせ/メール/リンク/Console Error/favicon/OGP/sitemap/robots/SEO/モバイル/PageSpeed/GitHub Release Tag/バージョン番号/更新履歴)を制定。ローカル確認済み8項目、本番環境が前提の4項目(⏸)、承認待ちの2項目(タグ・バージョン)を明示 |
| Next Task | CEOによる`config.php`本番設定・`public/`一式のXServerアップロード・GitHub Pagesカスタムドメイン設定を経て、⏸項目(SSL/問い合わせ/メール/PageSpeed)の本番確認を実施。全項目確認後、CEO承認を得てGitタグ`homepage-v1.0.0`・GitHub Releaseを作成。Company OS「Pricing Manager」の実際のコード実装は別Task承認待ち |
| Last Update | 2026-07-14 |
| Maintainer | Masatoshi Ogawa |

---

## Version1.0リファクタリング進捗(2026-07-10 CEO承認、smartlabo-works限定)

CEO承認により、Smart Labo Worksを「販売可能なSaaS」に仕上げることを目的とした5Stepのリファクタリングを開始した。新機能追加・大規模変更は禁止し、1Stepごとにコミット→本ファイル更新→CEO承認を経て次Stepへ進む運用とする。

| Step | 内容 | ステータス | コミット |
|---|---|---|---|
| Task1 | `smartlabo-works`ソースコードのGit初回コミット(追加のみ) | ✅完了(2026-07-10) | `326fdc7`(TOEICアプリ repo) |
| Task2 | `REFACTOR_PLAN.md`新規作成(Phase1〜3の計画のみ) | ✅完了(2026-07-10) | `9098544`(TOEICアプリ repo) |
| Task3 | サーバー認証の最小実装(Workspace→Company→User→Role構造を見据えた設計) | ✅完了(2026-07-10) | `5adf4bb`→`653f754`→`9d7a5d9`(TOEICアプリ repo、3コミット) |
| Task4 | AI Routerの正式整理(Providerパターン統一) | ✅完了(2026-07-10) | `a76d66c`→`f81d64d`(TOEICアプリ repo、2コミット) |
| Task5 | 「Company OS」表記を販売画面(ログイン・Builder等)から除去 | ✅完了(2026-07-10) | `b864dd4`(TOEICアプリ repo) |

**5Stepすべて完了。「Version1.0リファクタリング」フェーズ1の第一弾はここで区切りとし、次のスコープ（REFACTOR_PLAN.md Phase1の残り：企業単位データ分離・CRM保存・app.html分割等）はCEOへ新規Taskとして改めて提案する。**

---

## Sprint2進捗(2026-07-11 CEO承認：複数企業で利用できるSaaSへの進化)

CEOより「Sprint2を開始する。目的はSmart Labo Worksを複数企業で利用できるSaaSへ進化させること。今回はTask6のみ実施」との承認があった。

| Step | 内容 | ステータス | コミット |
|---|---|---|---|
| Task6 | 企業単位データ分離（localStorage単一データ→CompanyID単位へ。Workspace機能は今回実装不要） | ✅完了(2026-07-11) | `d7ea0cd`→`0b8fedf`→`707ef92`(TOEICアプリ repo、3コミット) |
| Task7 | CRM・案件・契約のサーバー永続化（localStorage→SQLite。REST API化） | ✅完了(2026-07-11) | `5064513`→`afa1baf`→`41ac221`(TOEICアプリ repo、3コミット) |
| Task8 | Company Brainのサーバー永続化（localStorage→SQLite。REST API化） | ✅完了(2026-07-11) | `09a2315`→`6b5cfc5`→`75fdaf2`(TOEICアプリ repo、3コミット) |

詳細なデータ構造・影響範囲はTask6〜Task8完了報告を参照。Sprint2はTask8で一区切りとし、Sprint3へ移行した。

---

## Sprint3進捗(2026-07-11 CEO承認：Smart Labo Works独自技術「Smart AI Router」の開発)

CEOより「Sprint3 Task1: Smart AI Router基盤実装」の承認があった。目的はOpenAI/Claude/Geminiを共通インターフェースで扱い、ユーザーがAIを選択せず仕事内容から自動選択される仕組みを作ること。

| Step | 内容 | ステータス | コミット |
|---|---|---|---|
| Task1 | Smart AI Router基盤実装（AIProviderインターフェース・OpenAI/Claude/GeminiProvider・routerService・Router Monitor） | ✅完了(2026-07-11) | `77a533b`→`67f8a98`→`468c2fd`→`6c85b30`(TOEICアプリ repo、4コミット) |
| Task2 | Claude API正式実装（ClaudeProviderのsummarize/generate/analyzeを、routeTable.js実割り当てに合わせたプロンプトへ差し替え） | ✅完了(2026-07-11)※ | `9f11bab`(TOEICアプリ repo) |
| Task3 | Company Brain Builder（誰でも約30分でCompany Brainを作成できる6Step Wizard） | ✅完了(2026-07-11) | `6f31a55`(TOEICアプリ repo) |
| Task4 Step2.1 | 不動産AIテンプレート「物件紹介文」実装 | ✅完了(2026-07-12) | `9321880`(TOEICアプリ repo) |
| Task4 Step2.2 | 不動産AIテンプレート「査定コメント」実装 | ✅完了(2026-07-12) | `90773dc`(TOEICアプリ repo) |
| Task4 Template Engine正式化 | AIテンプレート基盤を「Template追加型」の正式アーキテクチャとして確定（下記「Template Engine正式仕様」参照） | ✅完了(2026-07-12・CEO承認) | `1347104`(TOEICアプリ repo) |
| Task4 営業テンプレート1 | 「物件紹介メール」実装（realestate/mail.js。営業テンプレートファミリー第一弾） | ✅完了(2026-07-12) | `8c19601`(TOEICアプリ repo) |
| Task4 Appointmentテンプレート1 | 「内覧・来店予約案内」実装（realestate/visit.js。Appointment Templateファミリー第一弾） | ✅完了(2026-07-12) | `f4e6425`(TOEICアプリ repo) |

新設の`src/services/router/`は、Task4で整理済みの既存Router（`src/services/ai/router/`、`/api/ai/*`エンドポイントを引き続き支える）とは別の並存モジュールとして実装した。既存機能への変更は一切なし。

※Task2：この環境にANTHROPIC_API_KEYが未設定のため、実際のAPI成功応答（ok:true）は確認できていない。ルーティング・エラーハンドリングの正常動作のみ確認済み。実キー設定後の疎通確認が必要。

Task3：Company Brain画面の「🧙 Builderで作成」ボタンから起動する新規Wizard。業種選択→会社情報→商品・サービス→業務ルール→FAQ・完成の6Stepで、保存はTask8で実装済みのREST API（`/api/brain`）をそのまま利用。AI自動生成・PDF/Word読込・OCR等は対象外（自由入力のみ）。既存の「Smart Builder」（企業インスタンス生成、社内専用）とは別機能。

**Task4 Template Engine正式仕様（2026-07-12 CEO承認）：**
Smart Labo Worksは「機能追加型」ではなく「Template追加型」で成長するSaaSと正式に位置づけた。今後、不動産・管理会社・司法書士・税理士・その他業種へ展開する前提で、以下を正式仕様とする。
- **Template Loader**: `smartlabo-works/src/services/ai/templates/index.js`が、`templates/<namespace>/`配下の各ファイルを自動走査してテンプレートとして登録する（`namespace`はフォルダ名から、`id`はファイルパスから自動生成）。新しいテンプレートを追加する際、`index.js`・`templateService.js`・`server.js`はいずれも変更不要（ファイルを1つ追加するだけでよい）。
- **PromptManager**: 長いswitch文を持たず、`promptManager.templates`として上記Template Loaderへの窓口となる（`require('./templates')`の再エクスポート）。
- **フォルダ構成（namespace）**: `realestate/`(不動産売買)・`management/`(不動産管理会社)・`legal/`(司法書士)・`tax/`(税理士)・`common/`(業種を問わない汎用) の5つ。今回`realestate/listing.js`(物件紹介文)・`realestate/appraisal.js`(査定コメント、Step2.2で実装・動作確認・CEO承認済みの内容をそのまま移動)の2件のみを実装し、残り19ファイルは`label`/`category`/`description`のみを持つ雛形（`status:'planned'`、一覧APIには含まれるがUIカードには表示されず、生成呼び出しは明確なエラーを返す）とした。
- **AI Router**: 変更なし。Provider選択・JobType判断は既存の`ROUTE_TABLE`機構のみで行い、Routerは特定テンプレート・業種のロジックを一切持たない（`templateService.js`は常に`feature:'assistant'`でRouterを呼び出し、テンプレート固有の`systemPrompt`のみを`options`で渡す）。
- **Provider**: 変更なし。共通Interface（chat/testConnection/isAvailable等）を維持し、Templateはどのproviderが使われるか意識しない。
- **Company Brain**: Templateから利用可能だが、Company Brain固有の取得ロジックはTemplate定義ファイル内に書かない方針を明文化（今回はどのテンプレートもCompany Brainを参照していない）。
- **API**: `GET /api/ai/templates`・`POST /api/ai/templates/generate`を正式採用。エンドポイント自体の変更なし。

**営業テンプレート／Appointment Templateファミリー（2026-07-12 CEO承認）：**
Template Engineの雛形（`realestate/mail.js`・`realestate/visit.js`）を実体化し、それぞれ独立したテンプレートファミリーの第一弾として設計した。いずれも「1ファイル=1用途」の原則を維持し、ファイル自身には他用途を判定するswitch文を書かず、将来の拡張は同系統のファイルを追加する方式とする。
- **realestate/mail.js（物件紹介メール）**: 入力は顧客名・物件名・物件紹介文(任意)・おすすめポイント・担当者名・会社名。`realestate/listing.js`には依存せず、「物件紹介文」欄はlisting.jsの出力貼り付け・手入力・空欄のいずれでも動作する単なるテキスト入力として扱う。会社名はテナントごとに異なるため入力項目とし、ハードコードしていない。今後`followup.js`（フォロー）・`visit.js`・`thanks.js`（お礼）・`contract.js`（契約案内）・`proposal.js`等を同形式のファイル追加で拡張する前提。
- **realestate/visit.js（内覧・来店予約案内）**: 入力は顧客名・物件名・候補日時(複数可・改行区切り)・集合場所・担当者・持参物(任意)・連絡先(任意)・備考(任意)。候補日時は将来のGoogle Calendar/Outlook/予約システム連携を見据え、現時点では構造化せず自由テキストとして扱う。今後、来店予約・オンライン商談・現地案内・契約日時・鍵渡し・引渡し等を同形式のファイル追加で拡張する前提。
- いずれもCompany Brain・AI Router・Provider固有ロジックはテンプレート定義ファイル内に書いていない。変更ファイルはそれぞれ1ファイルのみで、Template Loader・Service・API・UIの追加変更は不要だった（Template Engineの「ファイル追加のみで機能拡張できる」設計が実際に機能することを確認）。

詳細な機能境界・優先度は`smartlabo-works/PRODUCT_REQUIREMENTS.md`・`PRODUCT_BOUNDARY.md`・`BUSINESS_STRATEGY.md`を参照。

---

## ホームページVersion2リニューアル(2026-07-12 CEO承認、SmartLabo repo `WEBSITE/`限定)

CEOより「ホームページVersion2リニューアル」の承認があった。目的は現在のホームページを「会社紹介サイト」から「Smart Labo Worksを導入したくなる営業サイト」へ刷新すること。コミット`9a63a2b`(SmartLabo repo)。

**変更ページ**: `WEBSITE/index.html`・`WEBSITE/css/style.css`・`WEBSITE/js/main.js`（`WEBSITE/app.html`＝デモは対象外、無変更）。

**新設セクション**: ②Smart AI Router図解（質問→Router→OpenAI/Claude/Gemini→自動判断のフロー表示）、③AI社員カード6種（営業AI/契約AI/議事録AI/Company Brain/CRM AI/Meeting AI。AIモデル名ではなく仕事内容で訴求）、⑤Smart Labo Works画面ショーケース（横スクロール、実装画面をHTML/CSSで再現。写真ではなくUI表現を優先というCEO方針に沿った選択）、⑥業種展開カード（不動産売買のみ実際にTemplate Engineへ実装済みのため「対応中」、不動産管理/司法書士/税理士/建築/その他は「今後対応予定」と正確に区別。CEO指示は全業種を「今後対応予定」としていたが、既に出荷済みの機能を過小申告しないよう表示を修正）、⑦導入効果ストーリー（数値訴求から「毎日の業務→AIが支援→時間短縮→顧客対応向上→売上向上」のフロー訴求へ）、⑧料金プラン（β版/正式版/Enterprise、価格は「お問い合わせください」「個別見積り」とし具体額は記載せず）。

**既存セクションの扱い**: Company Brainセクション・「社長の1日」タイムライン・Missionは内容を維持。旧「主要機能」value-gridはAI社員カードと重複するため削除。旧5業種グリッド（不動産業/建設業/士業コンサル/製造業/サービス業）は⑥業種展開カードへ置き換え。

**実装ルール遵守**: 既存の`--navy-*`/`--smart-blue*`のみ使用し新規ブランドカラーは追加せず、ロゴは正式ロゴ(`smartlabo_icon_white.webp`)のみ使用、「Company OS」の文言なし。CEO指示のAI社員カード一覧には「Builder」が含まれていたが、実装ルール「Builderなど社内専用機能は顧客向けに表示しない」と矛盾するため、Builderをカードから除外（6種のみ採用）。

**レスポンシブ**: 既存のブレークポイント(1100px/860px/520px)体系に新規コンポーネント(Router図解・AI社員カード・画面ショーケース・業種カード・料金カード)のレスポンシブ対応を追加し、モバイル(375px)・デスクトップ(1440px)双方で表示確認済み。

ブラウザ実機確認済み（Hero〜最終CTAまで全セクションのレンダリング、Router図解のパルスアニメーション、画面ショーケースの横スクロール（ホイール操作対応）、ハンバーガーメニューの新セクション導線、コンソールエラーなし、全アセットのHTTP 200/304確認）。

---

## Smart Labo AI ホームページチャットデモ(2026-07-12 CEO承認「Smart Labo AI Demo」、SmartLabo repo `WEBSITE/`限定)

CEOより「ホームページAIチャット実装」の承認があった。目的は問い合わせチャットではなく、訪問者が3分でSmart Labo Worksを無料体験できるAIデモを設置すること。コミット`696b3ca`(SmartLabo repo)。

**変更ファイル**: `WEBSITE/index.html`（チャットウィジェットのマーカップ追加）・`WEBSITE/css/style.css`（チャットUIスタイル追加）・`WEBSITE/js/chat.js`（新規）。

**UI構成**: 右下固定の円形チャットボタン(Smart Blue)→クリックでチャットウィンドウを開閉。初回表示時に①挨拶メッセージ、②「体験できるAI」一覧(営業AI/契約AI/Company Brain/AI Router/不動産AI/司法書士AI[Coming Soon]/管理会社AI[Coming Soon])、③おすすめ質問8件をチップ形式で表示。ウィンドウ下部に常時「無料デモをご予約／β版受付中／お問い合わせ」CTAを配置。

**重要なアーキテクチャ上の制約と対応（要CEO確認）**: 実際のSmart AI Router(smartlabo-worksサーバー)は現時点で本番デプロイされておらず、一般公開されたAPIを持たない。「同じRouterを実際に利用して生成する」ことを文字通り実現するには、認証なしでアクセス可能な公開APIをどこかへ新規に本番デプロイする必要があり(レート制限・CORS・ホスティング先選定・APIコスト管理を伴う、単なるファイル追加を超えるインフラ変更)、この場でCEOに選択肢を提示したところ「今回はUI/UXのみ実装し、AI生成はダミー応答で代替する」との回答を得た。そのため`js/chat.js`はキーワード分類器によって、CEO指定の6機能(営業メール/物件紹介文/査定コメント/FAQ/会社紹介/提案書)と情報系Q&Aを、あらかじめ用意した製品トーンに合わせた高品質なデモ回答へ振り分ける構成とした。将来、smartlabo-worksが本番デプロイされ公開APIが用意された段階で、`DEMO_HANDLERS`の呼び出しを実際の`fetch()`呼び出しへ差し替えるだけで済む構造にしてある(UI側の変更は不要)。

**会話履歴**: `localStorage`(キー`slw_ai_chat_history_v1`)に保存し、リロード後も保持されることを確認済み。

**今回は実装しない**: ログイン・CRM・Meeting・Builder・Whisper・OCR・画像生成（いずれもCEO指示どおり非実装）。

**レスポンシブ**: デスクトップ(1440px)・タブレット(768px)・モバイル(375px)すべてで表示・操作を確認済み。モバイルではチャットウィンドウが画面幅いっぱいに広がる設計。

ブラウザ実機確認済み（チャット開閉、初回挨拶＋AI一覧＋おすすめ質問チップの表示、6デモ生成機能の動作、フォールバック応答からのCTA誘導、リロード後の履歴復元、コンソールエラーなし）。

---

## ホームページ情報量整理(2026-07-12 CEO承認「情報量整理・製品画面主役化」、SmartLabo repo `WEBSITE/`限定)

CEOより、装飾追加ではなく「ページを短くし、3秒で価値が伝わり、実在するSaaSとして信頼されるデザイン」への改善指示があった。実装前に新しいページ構成案・削除統合対象を報告し、CEO承認（「すすめて」）を得てから実施した。コミット`972d201`(SmartLabo repo)。

**セクション数**: 11→7。**背景ブロック**: 11回の色切替→5ブロック(Navy=Hero／Navy-deep=Company Brain+Smart AI Router／White=AI社員+画面ショーケース／Gray=導入効果+Mission／Navy-gradient=最終CTA)。

**新しいセクション順序**: ①Hero → ②Company Brain(Hero直後へ移動) → ③Smart AI Router → ④AI社員(社長の1日タイムラインを統合) → ⑤画面ショーケース(大型2枚) → ⑥導入効果+Mission(統合) → ⑦最終CTA。

**削除**: 「対応業種」セクション全体(6カード中5枚が「今後対応予定」の準備中表示のため非表示化。不動産売買が対応中である旨は画面ショーケースのキャプションへ残した)。「料金」セクション全体(3プランとも「お問い合わせください」で実質情報がなかったため削除。「料金プランは個別にご案内します」の一文のみ最終CTAへ残した)。

**統合**: 「AI社員カード」(6枚)＋「社長の1日タイムライン」(6枚、計12項目)→ AI社員カード6枚に統合し、各カードへ時間帯タグ(`employee-card__time`)を追加する形で重複解消。「導入効果ストーリー」＋「Mission」→ 1つのclosingセクションへ統合。

**画面ショーケース刷新**: 6枚の小カード横スクロール→Dashboard・不動産AI(AI Assistant)の**大型2枚**構成へ変更(Company Brainは②で既出のため除外、CRM/議事録/契約の小カードは情報量削減のため割愛)。

**Hero簡素化**: リード文を2行→1行、CTAを3つ(デモを見る／β版に申し込む／資料ダウンロード)→2つ(デモを見る／導入相談)へ整理、ステータス行を削除。この「デモを見る／導入相談」の2CTA構成はヘッダー・最終CTAにも統一適用。

**変更しない対象**: 正式ロゴ・Smart Blue・既存ブランドカラー変数は無変更。ホームページAIチャットウィジェット(Smart Labo AI)は今回のスコープ外で無変更。

ブラウザ実機確認済み（デスクトップ1440px・モバイル375pxでの2カラム/1カラム切り替え、全セクションのレンダリング、ナビ・フッターのリンク整合性、コンソールエラーなし）。

---

## Hero「AI Control Center」(2026-07-12 CEO承認、SmartLabo repo `WEBSITE/`限定)

CEOより「Heroの右側にある既存ダッシュボード表現を廃止し、通常の管理画面の縮小表示ではなくSmart Labo WorksのAI基盤を象徴する『AI Control Center』を新設せよ」との指示があった。実装前にHero新レイアウト・AI Control Center構成・スクロール後のセクション順・削除統合対象を報告し、CEO承認を得てから実施。コミット`0939f05`(SmartLabo repo)。

**AI Control Center**: Hero右側の`.device`(ノートPC風フレームに入った縮小ダッシュボード)を廃止し、枠のない半透明グラスパネルへ置換。表示要素はCEO指定の9項目すべてを含む：①Smart AI Router LIVEバッジ、②OpenAI/Claude/Geminiの稼働状況(発光ドット)、③問い合わせ返信→OpenAI・契約書レビュー→Claude・PDF/画像解析→Geminiのルーティング例3件、④Company Brain参照中インジケータ、⑤本日のAI処理件数(サンプル値)、⑥最近完了したAIタスクのミニログ、⑦Router→3 Providerを結ぶ細い接続線とノード(CSSの疑似要素によるツリー結線、緩やかなopacityパルスのみ。派手なSF表現は不使用)。Dark Navy・Smart Blueのみを使用し新規ブランドカラーは追加していない。

**削除**: 独立した「Smart AI Router」セクション(図解)、「AI社員」カードセクション(6枚)をいずれも削除。CEOの判断理由は「HeroのSmart AI Router／AI Control Centerで『複数AIが連携している』世界観をすでに表現しており、AI社員カードは役割が重複するため」。

**統合**: 削除した2セクションの内容は、Company Brainセクション内に新設した業務フロー図(Smart AI Router→Company Brain→営業／契約／会議／CRM、`router-node`/`router-arrow`のチップスタイルを再利用)へ集約。

**画面ショーケースの並び替え**: 「Company Brain(独立セクション)→不動産AI→CRM→実際の製品画面(Dashboard)」の順に再構成。前回(v4.7)の情報量整理で削減したCRMを、CEOの指示により証拠提示の一部として復活させた。

**セクション数**: 7→**5**(Hero／Company Brain／画面ショーケース／導入効果+Mission／最終CTA)。背景ブロックは5のまま維持。

ブラウザ実機確認済み（デスクトップ1440px・モバイル375pxでのAI Control Center表示、接続線・パルスアニメーションの適用確認(JS経由でanimation-name検証)、Company Brain業務フロー図の表示、画面ショーケース3枚の並び順、コンソールエラーなし）。

---

## Hero Experience Upgrade v3(2026-07-12 CEO承認、SmartLabo repo `WEBSITE/`限定)

CEOより「現在のHeroは完成度が高いが『AIが会社を動かしている』臨場感が弱い。レイアウトは変更せず、AI Control Centerを静的UIから“Live Dashboard”へ進化させよ」との追加指示があった。今回は実装前レビューの指定なし、完了後報告のみの指定のため直接実装。コミット`59d05d3`(SmartLabo repo)。

**Hero構成**: 「左：コピー／右：AI Control Center」のレイアウトそのものは無変更（CEO指示どおり）。

**AI Control Center追加演出**（新規ファイル`WEBSITE/js/hero.js`が駆動）:
- **Smart AI Router**: `cc-breathe`キーフレームでbox-shadowがゆっくり呼吸するように発光(3.6秒周期)。LIVEバッジは既存のcc-blink点滅を維持。
- **AI処理ローテーション**: 2.6秒ごとに「問い合わせ返信中…→契約レビュー中…→PDF・画像解析中…→Company Brain検索中…」の4状態を巡回し、対応するProviderノード(OpenAI/Claude/Gemini)に`.is-active`クラス(発光ハイライト)を同期して付与。静的だった3行のルーティング例リストを置き換えた。
- **接続ライン**: Router→3Providerへのライン(CSS疑似要素)を、単色rgbaから移動グラデーション(`background-position`アニメーション)へ変更し、小さな光が流れる演出を追加。
- **Company Brain状態変化**: 「Company Brain 参照中」→「更新中」→「学習完了」を4.2秒周期で独立ローテーション。
- **KPIカード(2x2)**: 本日のAI処理件数／Company Brain登録数／契約レビュー件数／問い合わせ返信数の4項目をカード形式で新設。旧来の単一stat行を置き換えた。
- **背景**: `hero__glow`に14秒周期のゆっくりとした位置・透明度ドリフトを追加。6個の淡い浮遊粒子(`hero__particles`)を新設。いずれも控えめで、既存の`hero__grid`・都市写真は無変更。
- **Heroコピー**: 「会社を動かすAI。」を主役(54px、white)、「Smart Labo Works」を副題(20px、smart-blue-light)に変更(従来は逆の強弱だった)。
- **スクロール誘導**: Hero最下部に「Company Brainを見る」+バウンドするシェブロンアイコンを新設(`#brain`へのアンカーリンク)。モバイル(520px以下)では非表示とし、限られた画面領域を圧迫しないよう配慮。
- **アクセシビリティ**: `prefers-reduced-motion: reduce`環境では全アニメーションを無効化。

**変更しないもの**（CEO指定どおり）: ロゴ、ブランドカラー、CTA、ナビゲーション、Heroレイアウト、Company Brainセクション構成。

**パフォーマンスへの影響**: 新規JS(`hero.js`)はsetIntervalを2つ(2.6秒・4.2秒間隔)使用するのみで軽量。アニメーションはbox-shadow/opacity/background-positionのみでレイアウトを再計算する類のプロパティは使用していない。新規画像・外部ライブラリの追加なし。

ブラウザ実機確認済み（処理中ローテーションとProviderノードの発光同期をJS経由で検証、Company Brain状態変化の巡回確認、Hero見出しの強弱(54px/20px)、KPIカード4枚、粒子6個・背景ドリフトアニメーションの適用確認、モバイル375pxでのスクロール誘導非表示・レイアウト崩れなし、コンソールエラーなし）。

---

## Smart Labo Works 詳細ページ構築(2026-07-12 CEO承認、SmartLabo repo `WEBSITE/`限定)

CEOより「トップページは世界観と先進性を伝える役割に限定し、詳しい製品説明は別ページへ分離せよ」との指示があった。実装前にワイヤーフレーム・ナビゲーション構成・移動する情報・使用する実画面・表示ルール・URL構成を報告し、CEO承認を得てから実施。コミット`a51b773`(SmartLabo repo)。

**新設5ページ**（いずれも`WEBSITE/`直下、ビルド工程がないためヘッダー/フッターは各ファイルへ複製）:
- `product.html` — 製品紹介。コンセプト／Smart AI Router／Company Brain／AI Assistant／CRM・案件・契約管理／AIテンプレート一覧／AI利用ログ／導入までの流れ／セキュリティ／FAQ／最終CTA
- `features.html` — 機能一覧。10機能（Smart AI Router／Company Brain／AI Assistant／CRM／案件管理／契約管理／物件紹介文／営業メール／査定コメント／AI利用ログ）を、機能名/解決する課題/できること/利用イメージ/実際の画面/関連機能/CTAの統一構成で掲載。デスクトップではsticky sidebarでアンカー移動可能
- `real-estate.html` — 不動産売買仲介会社向け専用ページ。よくある課題／不動産AIテンプレート4種／CRM・案件・契約／Company Brain(不動産FAQ)／業務フロー図／導入イメージ(1日の流れ)／β版募集CTA。不動産管理会社向けへの将来拡張は控えめな1行で記載
- `pricing.html` — Beta/Standard/Enterpriseの3プラン（既存の`.pricing-card`を再利用）、価格はすべて「個別相談」「個別見積り」表記。機能比較表(8項目×3プラン)を新設
- `contact.html` — 会社名/氏名/メールアドレス/電話番号/業種/社員数/相談内容/デモ希望/個人情報取扱同意の入力フォーム。送信先未接続のため、クライアント側バリデーションのみ実装し、フォーム上部の注記と送信成功時の両方で「実際には送信されない」旨を明示

**実装済み/Coming Soon/非表示の3区分ルール**（全ページ共通で適用）:
- 実装済み：ログイン／ダッシュボード／Company Brain／AI Assistant（問い合わせ返信ほか6種）／CRM／案件管理／契約管理／AI利用ログ／Smart AI Router／不動産AIテンプレート4種（物件紹介文・査定コメント・物件紹介メール・内覧予約案内）／Company Brain Builder
- Coming Soon：不動産管理・司法書士・税理士・建築・その他業種テンプレート／議事録AI専用画面／OCR・音声文字起こし・Gemini画像解析
- 非表示（社内専用、CEO指示どおり一切表示しない）：Company OS（名称）／Builder／Innovation Hub／アイデア提案／商品開発／Smart Growth群／Company Memory／人材育成群／ホームページ管理・営業資料・マーケティング

**Smart AI RouterのProvider表記**: OpenAIは実際に稼働・検証済み。Claude/Geminiはコード実装済みだが、この開発環境にはAPIキーが未設定のため本番接続は未検証（環境固有の事情のため、顧客向けページには反映せず「OpenAI／Claude／Geminiに対応」とのみ記載する方針をCEOに確認済み）。

**ナビゲーション**: 全ページ共通で「製品／機能／不動産向け／料金／会社情報」＋右側「デモを見る／導入相談」に統一。`index.html`のヘッダー・フッター・CTA・最終CTA注記も合わせて更新し、「導入相談」の遷移先を`#cta`アンカーから`contact.html`へ変更した。

**新規CSSコンポーネント**: `page-hero`／`status-badge`／FAQアコーディオン／`feature-block`・`feature-sidebar`／`problem-grid`／`compare-table`／`contact-form`。新規ブランドカラーは追加せず、`screen-card`／`big-screen`／`router-node`／`pricing-card`等の既存コンポーネントを最大限再利用した。新規JS`js/pages.js`がFAQ開閉とフォームバリデーションを担当。

ブラウザ実機確認済み（5ページすべてのレンダリング・コンソールエラーなし、FAQアコーディオンの開閉、features.htmlの10機能ブロックとサイドバー10項目の対応、pricing.htmlの3プラン・比較表8行、contact.htmlの未入力時6項目エラー表示→有効値入力後の送信成功表示→フォームリセット、モバイル375pxでの1カラム化、index.htmlのナビ/CTA遷移先更新）。

---

## ブランド露出強化(2026-07-12 CEO追加指示、SmartLabo repo `WEBSITE/`限定)

CEOより「製品名ではなくブランドを印象付けるため、ロゴの露出を増やす」との追加指示があった。今回は実装前レビューの指定なし、直接実装。コミット`8861c1a`(SmartLabo repo)。トップページ・詳細5ページ（product/features/real-estate/pricing/contact）の全6ページへ統一適用。

- **ヘッダーロゴ拡大**: 32px→44px(1.375倍、指定の1.3〜1.5倍の範囲内)。フッターロゴも28px→38pxで比率を揃えた。ロゴアイコンと「Smart Labo Works」ワードマーク間の余白を10px→14pxへ拡張
- **Hero巨大透かしロゴ**: `index.html`のHero背景へ、820px・透明度7%の透かしロゴを右端からはみ出す形で追加(指定の5〜8%の範囲内)。詳細5ページの`page-hero`にも420px・透明度6%の控えめな版を追加。いずれも`overflow:hidden`のコンテナ内に収め、横スクロールが発生しないことを確認。モバイル(520px以下)では非表示とし、限られた画面領域を圧迫しないよう配慮
- **Powered by表示**: Hero「AI Control Center」パネル最下部に、小さなロゴマーク付きで「Powered by Smart Labo Works」を追加
- 新規ブランドカラーの追加なし。既存アセット(`smartlabo_icon_white.webp`)を異なるスケールで再利用したのみ。派手な演出・SF的表現は使用していない

ブラウザ実機確認済み（ヘッダーロゴのサイズ(44px)・透かしロゴの透明度(7%/6%)・Powered by表示の適用をJS経由で検証、デスクトップ/モバイル双方で横スクロールが発生しないことを確認、コンソールエラーなし）。

---

## Pricing Philosophy「導入プラン」の正式制定(2026-07-12 CEO指示、SmartLabo repo `PROJECT_BIBLE/`・`WEBSITE/`、smartlabo-works repo `BUSINESS_STRATEGY.md`)

CEOより「Smart Labo Worksの料金体系を正式仕様として採用する。今後はホームページ・Product Book・Company OS・営業資料・見積書・契約書すべて共通仕様とする」との追加指示があった。実装前レビューの指定はなく、直接実装。コミット`def70d1`(SmartLabo repo、WEBSITE実装分)。

**基本思想**: 「料金」ではなく「導入プラン」として表現する。金額(円建て数値)はいずれの資料にも一切記載せず、常に「個別相談」「個別見積り」で統一する(既存の`BUSINESS_STRATEGY.md`スタブ方針「捏造しない」を踏襲)。

**3軸構造**（新設[12_Pricing_Philosophy.md](12_Pricing_Philosophy.md)、PROJECT_BIBLE Version 5.6→5.7）:
- **①利用規模**: Small(〜10名程度)／Medium(11〜50名程度)／Enterprise(51名以上・複数拠点) — 人数を厳密な線引きにはせず、非数値的な規模感として記載
- **②プラン**: Lite／Standard／Premium(旧・単一軸だったBeta/Standard/Enterpriseから改称。利用規模軸の「Enterprise」との名称衝突を避けるため)
- **③オプション**: Company Brain構築[対応可]／テンプレート制作[対応可]／API連携[要相談]／AI教育[要相談]／OCR[Coming Soon]／議事録[Coming Soon]／その他[要相談]の7項目(提供状況は`PRODUCT_BOUNDARY.md`の実装状況に準拠)

**Company OS「Pricing Manager」**: 営業担当が①利用規模→②プラン→③オプションを選択すると料金表・見積書・契約書・営業資料を自動生成する新機能として、データモデル・画面フロー・生成対象を`12_Pricing_Philosophy.md`内に設計として記載した。**契約書自動生成には法的正確性の担保が必要であり、本セッションの一貫したガバナンス方針(Company OS新機能はSprint/Task単位でCEO承認を得てから実装)に基づき、実際のコード実装は今回行わず、別Taskとして改めてスコープ提案しCEO承認を得たうえで着手する。** CEO指示の「設計としてください」は`12_Pricing_Philosophy.md`への設計記載をもって満たし、「追加し...自動生成できる」という実装部分は意図的に留保している。

**WEBSITE実装**（コミット`def70d1`）: `pricing.html`を①利用規模を選ぶ→②プランを選ぶ→③オプションを選ぶの3ステップ構成へ全面再構成。比較表の列見出しをLite/Standard/Premiumへ改称。FAQに「利用規模とプランは独立した軸で自由に組み合わせられる」旨の項目を追加。全6ページのナビゲーション「料金」を「導入プラン」へ統一(意図的に残した`pricing.html`冒頭の「「料金」ではなく「導入プラン」」という対比文のみ「料金」の語を保持)。新規CSS(`.plan-step-label`／`.scale-grid`・`.scale-card`／`.option-grid`・`.option-item`／`.status-badge--ready`・`.status-badge--consult`)はいずれも既存の`--smart-blue`等の既存ブランド変数のみを使用し新規ブランドカラーは追加していない。

**BUSINESS_STRATEGY.md更新**（別リポジトリ`smartlabo-works`、v0.1→v0.2）: 第3章「料金体系・プラン設計の正式決定」を、Pricing Philosophy策定により正式決定した旨へ更新。`app.html`内の旧`PLANS`静的データ(Starter/Pro/Enterprise、円建て数値あり)が新構造(Lite/Standard/Premium)と名称・構造とも一致しない旧デモ用データである点を明記し、今後の移行・整理が必要な未着手事項として記録した。

ブラウザ実機確認済み（`pricing.html`のデスクトップ1440px：`.scale-grid`/`.option-grid`とも3列/2列表示、モバイル375px：両グリッドとも1列へ折り畳み、比較表見出しLite/Standard/Premium表示、FAQアコーディオン(`js/pages.js`)の動作、`index.html`ナビ・CTA文言の「導入プラン」統一、コンソールエラーなし）。SmartLabo repoはpush未実施、CEO確認待ち。

---

## Smart Labo Configurator(2026-07-13 CEO追加指示、SmartLabo repo `WEBSITE/`・`PROJECT_BIBLE/`限定)

CEOより「Pricingページを価格表ではなく、企業ごとの最適な構成をシミュレーションする『Smart Labo Configurator』へ変更する」との追加指示があった。実装前に①UIワイヤー②入力項目③データ構造④Company OSとの共通設計を報告し、CEO承認（4層構造への修正指示付き）を得たうえで実施。コミット`27838a4`(SmartLabo repo、WEBSITE実装)。

**CEO承認時の重要な修正指示**: 提案時点の「③オプション9項目」を単純な追加オプション一覧として扱う方針は不採用となり、代わりに「①利用規模×②基本プラン×③利用モジュール×④追加オプション」の4層構造が正式決定された。Company Brain・CRM・AI Assistant・案件管理・契約管理・AIテンプレートの6つの中核機能は「③利用モジュール」として、選択した②基本プランに標準搭載される（顧客向けUIでは選択・変更不可のチェック済み表示）。Meeting／議事録AI・OCR・外部API連携・AI活用研修・Company Brain初期構築支援・オリジナルテンプレート制作・データ移行・導入・運用支援の8項目のみを「④追加オプション」として複数選択可能にした。

**画面構成（5Step）**: Step1利用規模を選択→Step2基本プランを選択→Step3プランに含まれる利用モジュールを表示（選択不可）→Step4追加オプションを選択（複数選択可）→Step5おすすめ構成を確認、の順に変更。各Stepの達成状況を示す進捗ステップバーと、常時更新されるミニステータス行を新設。

**プラン別モジュール配分**: CEO指示どおり、現段階では仮定義として実装（Lite=AI Assistant+AIテンプレート、Standard=Liteの全機能+Company Brain/CRM/案件管理/契約管理、Premium=Standardの全機能+highlights3項目）。`WEBSITE/js/configurator.js`のマスターデータ（`PLANS`配列の`modules`プロパティ）のみを変更すれば、Step2〜Step5の全画面表示へ反映される構造にした。

**データ構造**: `selection = { usageScale, plan, modules, options }`（`modules`と`options`を分離、CEO指示どおり）。`Module`/`Option`とも`status: 'ready'|'planned'|'consult'`の語彙を共通利用。`localStorage`（キー`slw_configurator_selection_v1`）に保存し、リロード後も選択状態を復元することを確認済み。

**Company OSとの共通設計**: フィールド名・id値・status語彙を`12_Pricing_Philosophy.md`のデータモデルと一致させ、将来のCompany OS「Pricing Manager」実装時にそのまま踏襲できる構造とした。ただし顧客向けUI（ホームページ）では「Company OS」の名称を一切使用せず、「Smart Labo Configurator」「Smart Labo Works」「利用モジュール」「追加オプション」の名称に統一した（CEO指示どおり）。

**contact.htmlへの引き継ぎ**: 4つのCTA（無料相談／見積り依頼／資料請求／デモ予約）クリック時、選択内容（相談種別・利用規模・基本プラン・利用モジュール・追加オプション）をURLクエリ経由で`contact.html`へ引き継ぎ、新設の「ご相談種別」セレクトと「相談内容」欄へ自動入力する処理を`js/pages.js`に追加した。デモ予約選択時は「無料デモを希望する」チェックボックスも自動でオンにする。

**スコープ外（CEO指示どおり）**: 見積書・契約書・提案書の自動生成、CRMへの自動登録、バックエンドAPI接続は今回実装していない。`WEBSITE/`は静的サイトのためバックエンドが存在せず、引き継ぎはURLクエリ＋`localStorage`にとどめた。

**PROJECT_BIBLE更新**: `12_Pricing_Philosophy.md`をv1.0→v2.0に改訂。③オプション(7項目)を③利用モジュール／④追加オプションへ分割し、「Smart Labo Worksは中核機能を細かく切り売りする製品ではない」という基本思想を明記。データモデルに`Module`型を追加し画面遷移を5Stepへ更新。コミット`（本docsコミットのハッシュは下記changelogで確定）`。

**検証時に判明した環境固有の事象（実装上の問題ではない）**: ローカル開発サーバー(`npx serve`)が持つ「クリーンURL」機能により、`contact.html?...`への遷移時に`serve`が`/contact`へ301リダイレクトし、その際クエリ文字列が失われる現象を確認した。これは`serve`パッケージのローカル開発専用の挙動であり、本番のGitHub Pagesでは同様のリダイレクトが発生しないため影響しない（`fetch(..., {redirect:'manual'})`によるレスポンスヘッダー検証、および拡張子なしURL`/contact?...`への遷移で自動入力ロジック自体が正しく動作することを確認済み）。

ブラウザ実機確認済み（Step1〜Step4の選択操作とチェックマーク表示、Step2でプラン変更時にStep3利用モジュール一覧が連動して切り替わること、Step5サマリーのリアルタイム更新、`localStorage`保存とリロード後の状態復元、4CTAクリックによる`contact.html`への遷移とクエリ経由の自動入力（相談種別・利用規模・基本プラン・利用モジュール・追加オプション・デモ予約チェック）、デスクトップ1440px・モバイル375pxでのレイアウト、コンソールエラーなし、`document.body.innerText`に「Company OS」が含まれないことを確認）。SmartLabo repoはpush未実施、CEO確認待ち。

---

## 正式リリース前 最終整備 — Step1: 会社概要ページ新設・会社情報表示統一(2026-07-14 CEO承認、SmartLabo repo `WEBSITE/`限定)

CEOより「正式公開前に①会社概要を正式情報へ統一する②問い合わせフォームを安全に運用できる状態にする、の2点を完成させる」との指示があった。実装前に①現在の会社情報と不足項目②修正対象ファイル③問い合わせフォームの現在構成④推奨送信基盤⑤セキュリティ対策一覧⑥法務ページの不足⑦Step分割⑧コミット単位⑨完了条件を報告し、CEO承認（「進めましょう」）を得たうえでStep1を実施。コミット`fa5c629`(SmartLabo repo)。

**調査で判明した事実**：PROJECT_BIBLE（`00_Foundation/01_Company_Story.md`等）には代表者・設立年月・所在地・電話番号・メールアドレス・事業内容の正式情報が一切記載されておらず（すべて「[記入]」プレースホルダー）、確定しているのは法人名「株式会社スマートラボ」とブランド名「Smart Labo」「Smart Labo Works」のみだった。また、全ページのフッター著作権表記が英語表記「Smart Labo Inc.」で正式法人名と不一致だったこと、ヘッダーnav「会社情報」が全ページで`index.html#mission`にリンクしていたが**index.html側に`id="mission"`を持つ要素が存在せず、リンクが機能していなかった（アンカー切れ）**ことを発見した。

**実装内容**：
- 新設`WEBSITE/company.html`（会社概要ページ）：法人名・ブランド名・主力サービス・代表者・設立・所在地・電話番号・メールアドレス・事業内容・ホームページURLを表形式で掲載。**未確定項目は推測せず「CEO確認待ち」と明示表示**（グレー・イタリック体で視覚的にも区別）。ページ下部に「推測や仮の情報は掲載していない」旨の注記を明記
- 新設`WEBSITE/js/company-info.js`：会社情報を1つのJSオブジェクト（`COMPANY_INFO`）で一元管理し、`data-company`属性を持つ要素へ自動反映する仕組み。将来正式情報が確定した際、このファイルの値を書き換えるだけで反映される設計
- 全ページ（index/product/features/real-estate/pricing/contact）のヘッダーnav「会社情報」のリンク先を、機能していなかった`index.html#mission`から`company.html`へ修正。あわせてindex.html側の対象セクションに`id="mission"`を追加し、Missionセクションへの直接アンカーも修復
- 全ページのフッター著作権表記を「© 2026 Smart Labo Inc.」→「© 2026 株式会社スマートラボ」へ統一（確定済みの正式法人名を使用、推測ではない）
- 全ページのフッターに「会社情報」列（会社概要／プライバシーポリシー／利用規約）を新設
- `product.html`／`features.html`／`real-estate.html`／`pricing.html`／`contact.html`にOGP・Twitter Cardタグを新規追加（`index.html`のみ設定済みだった状態を解消）
- 新設`WEBSITE/privacy.html`・`WEBSITE/terms.html`：本文は「準備中」の暫定表示（`<meta name="robots" content="noindex">`）。正式なドラフト作成はTask2（Step2）で実施し、今回はフッターのリンク切れを避けるための最小限のプレースホルダーとして先行設置した

**今回のスコープ外**：プライバシーポリシー・利用規約の本文ドラフト作成（Step2）、問い合わせフォームの送信基盤実装（Step3以降）、構造化データ（JSON-LD）の実装、Product Bookへの反映（Product Book自体が未作成のため）。

ブラウザ実機確認済み（`company.html`の情報テーブル10行が正しくレンダリングされ未確定項目が「CEO確認待ち」表示になること、全6ページのnav「会社情報」→`company.html`遷移、フッター著作権表記・法務リンクの統一、OGP設定（`og:title`等）が新規5ページに反映されていること、`privacy.html`／`terms.html`の200応答、モバイル375pxで会社概要テーブルに横スクロールが発生しないこと、コンソールエラーなし）。SmartLabo repoはpush未実施、CEO確認待ち。

---

## 正式リリース前 最終整備 — Step2: プライバシーポリシー・利用規約ドラフト作成(2026-07-14 CEO承認「先にステップ2行こう」、SmartLabo repo `WEBSITE/`限定)

Step1完了報告直後、CEOより「先にステップ2行こう」との指示があり、Push前にStep2（法務ページ）へ着手した。コミット`aeb495d`(SmartLabo repo)。

**実装内容**：`privacy.html`・`terms.html`を「準備中」の暫定表示から正式ドラフトへ差し替えた。

- **プライバシーポリシー**：事業者情報・取得する情報（お問い合わせフォーム項目、Configuratorの選択内容の扱い）・利用目的・第三者提供・委託・Cookie/アクセス解析（現状未導入である旨を正直に記載）・安全管理措置・開示等請求・特定商取引法表記の要否・お問い合わせ窓口の10セクション構成
- **利用規約**：適用範囲・サービス内容の変更中断・禁止事項・知的財産権・免責事項・個人情報の取り扱い・準拠法/管轄裁判所・規約の変更の8セクション構成
- 両ページとも冒頭に**「本ページは公開前のドラフトであり、断定的な法的効力を保証するものではなく、正式公開前に弁護士等の専門家による確認が必要」**という注記(`.legal-doc__notice`)を明示
- **特定商取引法の該当性は断定せず「未決定事項」として明記**（BtoB SaaS契約のため通信販売表記が不要な形態が多いと考えられるが、最終判断は専門家確認待ちと記載）
- 制定日・準拠法の管轄裁判所など、会社の正式情報確定待ちの項目は`[CEO確認待ち]`のプレースホルダーとした（Step1の会社概要ページと同じ「推測しない」方針を継続）
- `contact.html`の個人情報同意チェックボックスを`privacy.html`へリンクし、利用者が同意対象を確認できるようにした（同意必須のバリデーション自体はStep1着手前から実装済みで変更なし）
- `noindex`設定は専門家確認が完了するまで維持

ブラウザ実機確認済み（`privacy.html`10セクション・`terms.html`8セクションの表示、同意チェックボックスの`privacy.html`リンク、同意未チェック時のバリデーションエラー表示が引き続き機能すること、モバイル375pxで横スクロールが発生しないこと、コンソールエラーなし）。SmartLabo repoはpush未実施、CEO確認待ち。

---

## 正式リリース前 最終整備 — Step3・Step4: 送信基盤の設計確定・バックエンド実装(2026-07-14 CEO承認、SmartLabo repo `WEBSITE/`限定)

**Step3（設計確定のみ、コミットなし）**：送信基盤は`smartlabo-works`とは独立した軽量Node.jsサービス（Vercel Serverless Functions想定）を推奨し、CEO承認を得た。API設計（`POST /api/contact`・`GET /api/csrf-token`）、文字数上限、レート制限閾値（1時間5回、同一内容10分以内は二重送信防止）、reCAPTCHA v3（スコア閾値0.5）、CSRF・CORS方針、通知メール／自動返信の設計、受付番号形式（`SLW-YYYYMMDD-XXXX`）を確定した。

**Step4（バックエンド実装完了、コミット`b7783ab`）**：`WEBSITE/api/`配下にVercel Serverless Functions想定のバックエンドを新規実装した。`contact.js`（Origin検証→honeypot/送信速度トラップ→CSRF検証→レート制限/二重送信検知→reCAPTCHA検証→入力値検証→管理者通知メール(必須)→自動返信メール(ベストエフォート)→受付番号返却の順で処理）、`csrf-token.js`、`_lib/`配下に`validate.js`・`security.js`・`rateLimiter.js`・`recaptcha.js`・`mailer.js`・`receiptId.js`・`log.js`を分離実装。`.env.example`に必要な環境変数（SMTP設定・通知先/送信元メール・reCAPTCHAシークレット・CSRF署名鍵・許可オリジン）を列挙し、実際の値はいずれもCEO確認待ちのプレースホルダーとした。個人情報をログに残さない設計（IPはハッシュ化）、SMTP/reCAPTCHA未設定時は推測で代替せず明確なエラーで失敗する設計とした。

**ローカル検証で発見・修正したセキュリティ上の不具合**：リポジトリ外のスクリプトで19件のユニットテスト＋5件のハンドラー統合テストを実施したところ、CSRFトークンの署名検証において「改ざんトークンの末尾に付加したゴミ文字列が、16進デコード(`Buffer.from(x,'hex')`)の際に黙って切り捨てられ、正規トークンと同一の署名として検証を通過してしまう」という脆弱性を発見した。署名部分の長さ・文字種を64桁の16進文字列と厳密一致させるバリデーションを追加し、修正後は全19件のユニットテストが成功することを確認した。

**未対応（要CEOアクション、Step5着手前に必要）**：Vercelアカウント作成・環境変数設定、Google reCAPTCHA管理画面でのサイト登録・シークレットキー発行、通知先／送信元メールアドレスの確定、SMTP接続情報の確定。これらが未整備のため、実際のメール送信・reCAPTCHA通信・本番デプロイでの動作確認は行っていない。

---

## 正式リリース前 最終整備 — 送信基盤をXServer(PHP)へ変更(2026-07-14 CEO判断、SmartLabo repo `xserver-form/`新設・`WEBSITE/api/`削除)

**上記Step3・Step4のVercel/Node.js方針は、この時点でCEO判断により撤回・置き換えとなった。** CEOより「Vercelを前提に進めるのではなく、現在契約済みのXServerを優先利用する構成へ変更する。ホームページはGitHub Pages＋smartlaboworks.comで公開し、問い合わせフォームはXServer(PHP)を優先する。SMTPはinfo@smartlaboworks.comを利用予定。reCAPTCHAはホームページ正式公開直前に設定する」との指示があった。実装前にXServer構成案・SMTP構成・フォーム送信構成・セキュリティ構成の4点を報告し、CEO承認（「お願いします」）を得たうえで実施。コミット`d0402a8`(SmartLabo repo)。

**ドメイン構成**：`smartlaboworks.com`／`www.smartlaboworks.com`（apex）はGitHub Pagesへ、問い合わせフォームAPIのみ`form.smartlaboworks.com`（サブドメイン、仮案）経由でXServerへ向ける。同一apexドメインを2つのホスティング先に同時に向けられないための分離。XServerでのサブドメイン追加・DNS設定・無料独自SSL発行はCEOアクション待ち。

**実装内容**：新設`xserver-form/`（GitHub Pagesの公開対象`WEBSITE/`の**外側**に配置し、静的サイトとして誤って公開されないようにした）。`public/contact.php`・`csrf-token.php`が、Origin検証→honeypot/送信速度トラップ→CSRF検証→レート制限/二重送信検知→reCAPTCHA検証（`recaptcha_enabled=false`の間はスキップ、正式公開直前にCEOが`true`へ切替）→入力値検証→管理者通知メール（必須）→自動返信メール（ベストエフォート）→受付番号返却、の順で処理する。レート制限はSQLite3で永続化し、Vercel版で課題だった「サーバーレスのコールドスタートでインメモリ状態がリセットされる」問題を解消した。メール送信は外部ライブラリ（PHPMailer等）を同梱せず、`stream_socket_client`+STARTTLS+AUTH LOGINによる依存ライブラリなしの最小SMTPクライアント（`SmtpMailer.php`）を実装し、`info@smartlaboworks.com`でのSMTP認証送信を想定した。CSRFトークンの署名検証は、Vercel/Node.js版で発見した「改ざんトークン末尾のゴミ文字列がデコード時に切り捨てられ検証をすり抜ける」脆弱性を教訓に、最初から署名を64桁16進文字列と厳密一致させる実装とした。

**検証**：ローカル環境にPHP 8.3を新規導入し、22件のユニットテスト（validate/security/rateLimiter/receiptId）全件成功を確認。PHP組み込みサーバーによる実HTTP統合テストで、Origin拒否・honeypot・送信速度トラップ・CSRF検証・レート制限（1時間5回でブロック）・二重送信検知（10分以内の同一内容をブロック）・入力値検証エラー・管理者通知メール送信（意図的に到達不能なSMTP宛先へ接続し、正しくエラーになること・個人情報を含まないログが記録されることを確認）まで一連の流れを確認した。実際のメール送達・reCAPTCHA通信・XServer本番環境での動作確認は、CEOによるサブドメイン追加・SMTPアカウント作成・DNS設定が完了するまで実施できない。

**削除**：旧Vercel/Node.js版（`WEBSITE/api/`、`package.json`等）。CEO確認のうえ、未使用コードの二重管理を避けるため削除した。

**未対応（要CEOアクション）**：XServerサーバーパネルでの`form.smartlaboworks.com`サブドメイン追加・無料独自SSL発行、DNS設定（apex→GitHub Pages、サブドメイン→XServer）、`info@smartlaboworks.com`メールアカウントの作成とSMTP接続情報の確認、`private/config.php`への実際の値の設定。

---

## 正式リリース前 最終整備 — Step5: 問い合わせフォーム実送信対応・メールアドレス構成の正式反映(2026-07-14 CEO承認、SmartLabo repo `WEBSITE/`・`xserver-form/`)

CEOより追加承認があり、①`form.smartlaboworks.com`を問い合わせフォーム専用とする②DNS構成(apex→GitHub Pages、サブドメイン→XServer)③`info@`を代表メール、`contact@smartlaboworks.com`を問い合わせフォーム送信用の推奨構成とし将来`noreply@`も追加可能な構成にする④`config.php`はSMTP情報・各種キー・秘密情報のみを保持しGit管理対象外とする、の4点が示された。実装前に①構成図②DNSレコード一覧③SMTP送受信フロー④セキュリティ対策⑤`config.php.sample`の構成を報告し、CEO承認（「お願いします」）を得たうえでStep5を実施。コミット`fb12868`(SmartLabo repo)。

**DNSレコード**：GitHub Pages公式ドキュメントを確認のうえ、apex用A レコード4件（`185.199.108.153`等）・`www`用CNAME（`smartlabosukimagakusyuu-dev.github.io`）・`form`サブドメイン用A/CNAME（XServer側、要CEO確認）を報告。`WEBSITE/CNAME`（中身`smartlaboworks.com`）を新設し、GitHub Pages側のカスタムドメイン設定に対応。

**メールアドレス構成**：`info@smartlaboworks.com`＝代表メール（送信処理では未使用）、`contact@smartlaboworks.com`＝SMTP認証アカウント／管理者通知宛先／自動返信送信元のすべてに使用する推奨構成、`noreply@smartlaboworks.com`（将来）＝`config.php`の`auto_reply_from`の値のみ変更すれば切替可能な設計とした。

**config.php再編**：`config.php.example`→`config.php.sample`に改名し、秘密情報（SMTP接続情報・`admin_notify_to`/`mail_from`/`auto_reply_from`等のメールアドレス構成・CSRF署名鍵・reCAPTCHAシークレット）のみを保持する構成へ整理。CORS許可オリジン一覧（秘密情報でない）は新設`public/lib/settings.php`（Git管理対象）へ分離。レート制限DB・ログの保存パスは秘密情報でないためconfig.php設定項目から除去し、コード側で`private/`配下を指す固定相対パスとして算出する設計に変更した。

**ホームページ側**：`contact.html`にhoneypotフィールド（`cf-website`、視覚的に非表示）を追加。`js/pages.js`で`csrf-token.php`からのトークン取得・`contact.php`への実際の`fetch()`送信を実装し、エラーコード別（バリデーション・レート制限・二重送信・CSRF失効・reCAPTCHA失敗・ネットワークエラー等）に分かりやすい日本語メッセージを表示。送信中はボタンをdisableし二重送信を防止。

**検証**：config再編後も23件のユニットテスト全件成功、実HTTP統合テストで`contact@`ベースの新設定でも一連のフローが正しく動作することを確認。ブラウザでhoneypot非表示・クライアント側バリデーション・**`form.smartlaboworks.com`が未デプロイの現状で発生するネットワークエラー時のフォールバック表示（ボタン再有効化含む）が正しく動作すること**・モバイル375pxでの表示・コンソールエラーなしを確認。

**未対応（要CEOアクション、本番動作確認の前提）**：XServerサブドメイン追加・DNS設定・`contact@smartlaboworks.com`メールアカウント作成・SMTP接続情報確認・`private/config.php`本番設定・GitHubリポジトリのSettings→Pagesでのカスタムドメイン設定。これらが完了するまで`form.smartlaboworks.com`は名前解決できず、実際の送受信・自動返信のエンドツーエンド動作確認はできない。

---

## 正式リリース前 最終整備 — Step6: 正式公開前チェックリスト(ライブ環境不要分を実施、2026-07-14)

コミット`7ae1aa9`(SmartLabo repo)。CEOの「進めて」指示を受け、XServer/DNS/SMTPのライブ環境整備を待たずに実施できるチェック項目を先行して実施した。

| チェック項目 | 結果 |
|---|---|
| 全リンク(内部リンク・アンカー・フッター・nav) | ✅確認済み。全9ページ(index/product/features/real-estate/pricing/contact/company/privacy/terms)がHTTP 200、ページ内の内部リンクもすべて解決することをブラウザから自動巡回で確認 |
| スマホ表示(375px) | ✅確認済み。全9ページで横スクロール(オーバーフロー)が発生しないことを確認 |
| 404ページの動作 | ✅確認済み。存在しないURLで`404.html`が正しく表示される |
| OGP | ✅確認済み(会社概要含む主要7ページ)。`privacy.html`/`terms.html`は`noindex`のドラフト段階の法務ページのため意図的に未設定 |
| favicon | ✅確認済み。全9ページに設定あり |
| 未実装機能の表現 | ✅既存タスクで確認済み・変化なし(status-badgeによる実装済み/Coming Soon/要相談の3区分表示を継続維持) |
| APIキー・秘密情報の混入チェック | ✅確認済み。`private/config.php`はコミット履歴を含め一度も追跡されていないことを`git log --all`で確認。`.gitignore`が`config.php`・SQLite・ログファイルを正しく除外することを実機で確認。クライアントJS・HTMLへの秘密情報直書きなし |
| consoleエラー | ✅確認済み。全9ページでエラーなし |
| バックアップ・ロールバック手順 | ✅文書化完了。`xserver-form/README.md`に追記(GitHub Pages側はコミットrevert+自動再デプロイ、XServer側は再アップロード手順、緊急停止手順を含む) |
| フォーム送信(正常系・異常系) | ⏸**CEOアクション待ち**。`form.smartlaboworks.com`が名前解決できないため、実際の送受信は本番DNS/SMTP設定完了後に確認 |
| 自動返信メールの到達確認 | ⏸**CEOアクション待ち**。同上 |
| プライバシーポリシー同意の必須動作 | ✅クライアント側は確認済み。サーバー側は送信フローと合わせて本番確認予定 |
| HTTPS | ✅GitHub Pages側は標準HTTPS。⏸XServer側(`form.smartlaboworks.com`)は無料独自SSL発行後に確認 |
| PageSpeed(Core Web Vitals) | ⏸**未実施**。正式ドメイン公開後に測定する(ローカル/暫定URLでの計測は本番環境と条件が異なるため参考値にとどまり、正式ドメイン確定後に実施する方針) |

---

## 公開前最終フェーズ Step5仕上げ(2026-07-14 CEO指示「公開前最終フェーズ Step5開始」)

CEOより、CEO側のインフラ整備状況(株式会社スマートラボ設立・XServer契約・`smartlaboworks.com`設定・`info@smartlaboworks.com`作成・`form.smartlaboworks.com`サブドメイン作成・SSL反映待ち)の共有とともに、問い合わせフォーム完成のための最終指示(Task1〜5、完了条件①〜⑩)があった。コミット`2b17f2c`(SmartLabo repo)。

**主な調整内容**：
- `config.php.sample`を`config.php.example`へ改名(今回の指示に従った。直前のセッションで一度`.sample`へ改名する指示があったため、経緯を完了報告で開示)
- 実際に作成済みの`info@smartlaboworks.com`を当面のSMTP認証・管理者通知・自動返信アドレスとする内容へ`config.php.example`／`mailer.php`／`xserver-form/README.md`を更新(`contact@`／`noreply@`への将来切替構造は維持)
- `contact.html`／`js/pages.js`：送信成功時にフォームを非表示にし、専用の送信完了画面(受付番号・トップページへ戻るボタン)へ切り替える実装に変更(確認画面は設けない、単一ステップ送信を維持)
- `xserver-form/README.md`にCEO側完了事項を反映し、SSL反映待ちの間は`.htaccess`のHTTPS強制リダイレクトが接続断を招くリスクを明記

**検証**：設定再編後もローカルPHP環境で23件のユニットテスト全件成功、全PHPファイルの構文チェック通過。PHP 8.1以降専用構文(readonly/enum/never戻り値型等)を使用していないことを確認し、PHP 8.0以上で動作する設計であることを確認。ブラウザで送信完了画面の表示切替・クライアント側バリデーション・ネットワークエラー時のフォールバック・モバイル375pxでの表示・コンソールエラーなしを確認。

**reCAPTCHA v3の実装位置**（コミット`4686920`）：CEO指示「実装位置だけ用意し、Googleキー設定はCEO作業とする」に対応し、`contact.html`へコメントアウト済みのスクリプトタグ、`js/pages.js`へ`RECAPTCHA_SITE_KEY`(空文字)と`getRecaptchaToken()`を追加。サイトキー未設定の間は自動的にスキップされ既存の4層防御(honeypot/送信速度トラップ/CSRF/レート制限)のみで動作し、CEOがサイトキーを設定すればコード変更なしで有効化される。

---

## Release Candidate 1(2026-07-14 CEO指示「正式リリース前 最終フェーズ RC1」)

CEOより、CEO側インフラ整備完了(会社設立・XServer契約・`smartlaboworks.com`設定・`form.smartlaboworks.com`サブドメイン作成・**無料SSL有効化**・`info@smartlaboworks.com`作成)の共有とともに、Task1(問い合わせフォーム本番対応)〜Task7(公開準備)の実装指示があった。コミット`76a9767`(SmartLabo repo)。

**Task3 config.php最小化**：`admin_notify_to`/`mail_from`/`mail_from_name`/`auto_reply_from`/`auto_reply_from_name`の個別キーを廃止し、送信元アドレスは`smtp_user`(認証アカウント)をそのまま使う設計へ単純化(多くのSMTPサーバーは認証アカウントと異なるFromでの送信を拒否・スパム扱いするため、より安全な構成)。最終的な項目：`smtp_host`/`smtp_port`/`smtp_encryption`/`smtp_user`/`smtp_pass`/`admin_email`/`csrf_secret`/`recaptcha_enabled`/`recaptcha_secret`の9項目のみ。

**`smtp_encryption`対応**：`SmtpMailer.php`がSTARTTLS(`tls`、587番想定)と暗黙のTLS(`ssl`、465番想定)の両方に対応。ローカル環境で両方の接続コードパスを検証済み。

**Task2 reCAPTCHA開発/本番モード**：Step5で実装済みの仕組み(`recaptcha_enabled`トグル)を維持し、`recaptcha.php`のコメントに「reCAPTCHA Enterpriseへの切替はこの1ファイルの検証先差し替えのみで対応可能」と明記。

**Task5・Task6 SEO対応**：全9ページのcanonical/OGP/Twitter CardのURLを暫定URL(GitHub Pages)から正式ドメイン`https://smartlaboworks.com`へ切替。新設`robots.txt`(sitemap.xml参照)。`privacy.html`/`terms.html`は`robots.txt`でのDisallowではなく既存の`noindex`メタタグに一本化(クローラーがnoindex指示を認識できるようにするため、Google推奨の方式)。新設`sitemap.xml`(index可能な7ページを掲載、noindexの2ページは除外)。`index.html`へ構造化データ(Organization、JSON-LD)を追加し、未確定の会社情報(所在地・電話番号・代表者等)は含めず確定済みの項目(法人名・ブランド名・URL・ロゴ)のみ記載。

**Task7 公開準備**：内部リンクはすべて相対パスであることを再確認(既存実装のまま、ドメイン切替の影響を受けない)。

**PageSpeed改善候補（今回は未実施、所見のみ）**：Webフォント読み込みなし(システムフォントのみ使用、良好)、画像は主要なものがWebP化済み(良好)。CSS(約72KB)・JS各ファイルは未minifyであり、圧縮による転送量削減の余地がある。リスクを伴う変更のため今回は実施せず、正式公開後の改善候補として記録するにとどめた。

**検証**：ローカルPHP環境で23件のユニットテスト全件成功。新config構成での実HTTP統合テスト(tls/ssl両方の暗号化方式)で一連のフローが正しく動作することを確認。ブラウザで全9ページのタイトル/description/canonical/robots/favicon、`robots.txt`・`sitemap.xml`の200応答、JSON-LDのパース成功、404ページ、全内部リンクの解決、モバイル375pxでの横スクロールなし、consoleエラーなしを確認。

---

## Release Candidate 2 — 本番公開前チェックリスト(2026-07-14 CEO指示)

CEOより「Release Candidate 1完了後、Release Candidate 2として本番公開前チェックリストを作成してください」との指示があり、新章[61_Release_Checklist.md](61_Release_Checklist.md)を新設した。SSL/問い合わせ/メール/リンク/Console Error/favicon/OGP/sitemap/robots/SEO/モバイル/PageSpeed/GitHub Release Tag/バージョン番号/更新履歴の15項目からなる、将来のリリースでも再利用可能なチェックリストテンプレートとして制定。

Release Candidate 1時点の確認状況を反映：SSL・問い合わせ・メール・PageSpeedは本番ドメイン/SMTP環境が前提のため未実施(⏸)、リンク・Console Error・favicon・OGP・sitemap・robots・SEO・モバイルはローカル環境で確認済み(✅)。公開バージョン番号として`v1.0.0`、Gitタグ名として`homepage-v1.0.0`を提案したが、**Gitタグ・GitHub Releaseの作成は対外公開を伴う操作のため、チェックリスト作成の時点では実行せず、全項目の確認完了後にCEOの明示的な承認を得てから実施する方針を明記した。**

---

## Versionの出典(トレーサビリティ)

推測でVersionを記載しないため、各数値の出典を明記します。数値を更新する際は、必ず出典ファイルの「変更履歴」テーブルと一致させてください。

| 項目 | 出典 |
|---|---|
| Project Bible Version | [README.md](README.md) 冒頭「PROJECT_BIBLE Version」 |
| Brand Version | [00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md) 末尾の変更履歴(最新バージョン) |
| Design Bible Version | [../PROMPTS/DESIGN/SmartLabo_Design_Bible.md](../PROMPTS/DESIGN/SmartLabo_Design_Bible.md) 末尾の変更履歴 |
| Homepage Version | 設計: [../PROMPTS/DESIGN/Homepage.md](../PROMPTS/DESIGN/Homepage.md) / 実装: [WEBSITE/](../WEBSITE/README.md) |
| デモサイト Version（`WEBSITE/app.html`） | 実装: [WEBSITE/](../WEBSITE/README.md)。設計上のデザイン意図は[../PROMPTS/DESIGN/Dashboard.md](../PROMPTS/DESIGN/Dashboard.md)を参考にしているが、2026-07-10以降はSmart Labo Works本体の設計基準としては扱わない |
| Smart Labo Works Version | 別リポジトリ`smartlabo-works`(Node.js版・唯一の正式コードベース)の`PRODUCT_REQUIREMENTS.md`を参照。本リポジトリの`SmartLaboWorks/`フォルダは`README.md`のみでコード実体を持たない |

---

## Homepage / Dashboard / Smart Labo Works のVersionについて(重要な注記)

この3項目は、**設計プロンプトのドキュメントバージョンではなく、実際のコード実装の進捗**を表す別軸です。

- 2026-07-03、`WEBSITE/` に Homepage の初回実装(`index.html` / `css/style.css` / `js/main.js`)が完了しました。8セクション構成(Hero/社長の1日/主要機能/導入メリット/対応業種/Mission/CTA/Footer)、3CTA、レスポンシブ対応、ホバー・スクロールアニメーションを実装済みです。
- 同日、配色を正式カラーパレット(`#0A1B3D` / `#2563EB` 等)へ更新しました。
- 2026-07-04、CEOが正式ロゴ(六角形フレーム+サーキットパターンの「S」字シンボル)を確定。ヘッダー・フッターの暫定ロゴマーク、およびfaviconを正式ロゴに差し替えました。
  - **未実装:** フォーム送信の実処理(現状はCTAのため実際の送信機能なし)、プライバシーポリシー・利用規約の実ページ(`LEGAL/`未整備のためリンクはプレースホルダー)、実写真素材(現状は色ブロック+アイコンで代用。Heroのみ実写真済み)、真のベクター(SVG)ロゴデータ(現状はPNG/WebPのみ)
- `SmartLaboWorks/`・`DESIGN/` 配下は `README.md` 以外の実装ファイルが存在しません。**2026-07-10のCEO決定により、Smart Labo Works本体の実装はこのフォルダではなく別リポジトリ`smartlabo-works`で行うことが正式に確定した**ため、このフォルダは今後もコード実装の追加を予定しない(ドキュメント参照用フォルダとして維持)。
- 実装が進んだら、この3項目を `0.1`、`α0.1` のように更新してください。**達成していない進捗を先取りして記載しないこと。**

**Hero背景画像について(2026-07-03完了):** CEOがAI_WORKSPACE経由で、Hero候補5点の「完成イメージのモックアップ」(ロゴ・見出し・CTAが焼き込まれた参考画像、[DESIGN_ASSETS/01_HERO/Mockups/](../DESIGN_ASSETS/01_HERO/Mockups/README.md))に続き、**テキスト等が焼き込まれていない背景単体データ5点**を提供しました。5点すべてを [DESIGN_ASSETS/01_HERO/Backgrounds/](../DESIGN_ASSETS/01_HERO/README.md) に格納し、うち都市とAIネットワークが1枚に収まった `hero_background_02_city_network.webp` をHomepage Hero([WEBSITE/index.html](../WEBSITE/index.html))に実装しました。以前CEOが選定していた「朝焼け」の候補(`01_sunrise_city`)はAIネットワークが写っていなかったため、今回は実装候補から外し、他用途向けにアーカイブしています。旧世代のモックアップ版`05_waterfront_city`にあった禁止ビジュアル(六角形)は、新しい背景単体データには含まれていないことも確認済みです。

**Homepageアイコンについて(2026-07-03):** CEOがAI_WORKSPACE経由で、Homepageの別デザイン案(アイコンのバッジスタイル・別ロゴ案・多色配色を含むモックアップ)を提供しました。このうち**アイコンのバッジスタイル**(角丸正方形の塗りバッジ+白抜きアイコン)のみをSmart Blue Family単色で採用し、「主要機能」「社長の1日」セクションのアイコンに反映しました。**多色配色**(青・水色・紫)と**別ロゴ案**(角丸正方形+「+」マーク)は、ブランドルール([00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md))との不整合、および過去の「仕様と実装の食い違い」の教訓から、CEOの明示的な承認を得るまで採用していません。参考資料は [DESIGN_ASSETS/Icons/Reference/](../DESIGN_ASSETS/Icons/Reference/) に保管しています。

**AI_WORKSPACEについて:** ChatGPT発の画像・資料・プロンプトをClaude Codeが受け取るための専用フォルダとして [AI_WORKSPACE/](../AI_WORKSPACE/README.md)(INBOX/PROCESSING/COMPLETED/ARCHIVEの4段階構成)を新設しました。今回のHero背景・アイコン参考資料の受領・実装が、実際に機能した最初の完全なサイクルです(INBOX受領→PROCESSING加工→DESIGN_ASSETS正式登録→COMPLETED履歴保存→Homepage実装)。

**正式ロゴの確定について(2026-07-04):** CEOより「今回添付したロゴを株式会社スマートラボの正式ロゴとします。今後このロゴ以外は使用しません。」との明示的な決定があり、六角形フレーム+サーキットパターンの「S」字シンボルを唯一の正式ロゴとして確定しました。以前の「途切れた2画のストローク」の暫定仕様、および[DESIGN_ASSETS/Icons/Reference/](../DESIGN_ASSETS/Icons/Reference/)の別ロゴ案(角丸+「+」マーク)は、両方とも本ロゴに置き換えられ廃止されています。[DESIGN_ASSETS/01_LOGO/](../DESIGN_ASSETS/01_LOGO/README.md)にPrimary/White/Dark/Icon/Favicon/PNG/WebPの8フォルダ構成で格納し、[00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md)に「ロゴ使用ルール」(余白規定・最小サイズ・背景色・使用禁止例・用途別推奨サイズ)を正式制定。Homepageのヘッダー・フッター・faviconに実装済みです。**真のベクター(SVG)データは未提供**のため、大判印刷等の用途では別途デザイナーへの依頼を検討してください。

**Homepage β版公開について(2026-07-04):** CEOより「ホームページをβ版として公開してください。目的は正式完成ではなく、共有しながら改善するための公開です。」との指示があり、Homepage Versionを**0.9 beta**として扱うことになりました。公開前に以下を確認済みです。

- スマホ表示(375px幅で全セクション、崩れなし)
- リンク切れ(内部アンカー4件すべて解決を確認。フッターの法務ページリンク・CTAセクション内の3ボタンは既知のプレースホルダー`#`であり、リンク切れではなく未実装機能として扱う)
- CTAボタンの動作(スクロール遷移を確認)
- 表示崩れ(デスクトップ・モバイル双方で全セクションを確認)
- 画像読み込み(Hero背景・ロゴ・favicon、すべて200 OKを確認)
- README([WEBSITE/README.md](../WEBSITE/README.md))・本ファイルの更新

**Homepageは2026-07-05、GitHub Pagesで正式に公開されました。**

**公開URL: https://smartlabosukimagakusyuu-dev.github.io/SmartLabo/**

**デプロイ先の経緯:** Xserverはドメイン(`smartlaboworks.com`)取得のみでサーバー契約が未完了と判明(2026-07-04)。CEOより代替案としてGitHubでの運用可否を確認された。「共有しながら改善する」というβ版公開の目的に合致するため、**GitHub Pages**(GitHubが提供する無料の静的サイトホスティング)を暫定的な公開先として採用した。[.github/workflows/pages.yml](../.github/workflows/pages.yml) により `WEBSITE/` 配下を自動デプロイするGitHub Actionsワークフローを構築。

**トラブルシューティング:** 初回デプロイはリポジトリのSettings→Actions→General→「Workflow permissions」が「読み取り専用」になっていたため失敗(`Deploy to GitHub Pages`ステップでエラー)。CEOに「読み取りおよび書き込み権限」への変更を依頼し、再デプロイを実施したところ成功。CSS・JS・Hero写真・ロゴ・faviconすべて200 OKで読み込まれることを確認済み。

Xserverのサーバー契約・DNS設定が完了し次第、正式ドメイン(`smartlaboworks.com`)へ切り替える。

**Homepage Version 1.0 Release Candidateへの最終ブラッシュアップ(2026-07-05):** CEOより「Version 0.9βは十分な完成度に達している。新しいデザインを作るのではなく、Version 1.0公開に向けた最終ブラッシュアップを行ってほしい」との指示があり、以下を実施した。

1. **Company Brainセクションを新設**(Priority 1・最大の差別化要素): 「主要機能」の直後に配置。タイトル「Company Brain」、サブタイトル「会社の知識を、AIへ。」、社内規程・業務マニュアル等の具体例(チップ表示)、「探す→AIへ聞く」の体験を示すチャット形式のビジュアルモックアップで構成。ナビゲーションにも項目を追加
2. **ダッシュボードのデモ表記を追加**(Priority 2): Hero内のダッシュボードモックアップに「DEMO」バッジを追加し、直下に「※ 画面はイメージです。表示データはすべてサンプルであり、実績値ではありません。」の注記を追加。実績値との誤認を防止
3. **CTA導線を整理**(Priority 3): 「資料ダウンロード」全4箇所(ヘッダー/Hero/CTA/フッター)に「準備中」タグを追加し、無料デモ・導入相談と機能的に区別。CTAセクション内の壊れたリンク(`href="#"` によるページ先頭への意図しないジャンプ)を `#cta` に修正。実在しない連絡先(メールアドレス等)は捏造せず、CEOの判断により現時点ではセクション内スクロールのままとし、「正式なお問い合わせ窓口は現在準備中です」の注記を追加
4. **ブランド品質の再確認**(Priority 4): Company Brain追加でナビゲーション項目が5つに増えたことに伴うヘッダーの折り返し崩れ(1024px前後)を発見・修正(ハンバーガーメニューへの切り替え幅を860px→1100pxに拡大)。その他、余白・タイポグラフィ・カラー・レスポンシブを再確認
5. **公開品質を確認**(Priority 5): ブランドに沿ったカスタム404ページを新設。OGP(og:title/description/image/url等)・Twitter Card・canonicalタグを追加。faviconを512×512(120KB)から64×64(3.5KB)に最適化し表示速度を改善。全リンク・全画像の読み込みを再確認(問題なし)

**Company BrainをSmart Labo Works最大の差別化要素として正式に formalize(2026-07-05):** CEOより「Company Brainは単なるマニュアル検索ではなく、Smart Labo Worksが提供する『会社の頭脳』である」との明確な指示があり、以下を実施した。

1. **PROJECT_BIBLEへの正式追加:** [00_Foundation/08_SmartLaboWorks_Concept.md](00_Foundation/08_SmartLaboWorks_Concept.md) にCompany Brainを中核基盤として追加し、一般的なマニュアル検索・チャットボットとの違いを比較表で明記。[00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md) にCompany Brain専用の機能コピー「探す時間を、考える時間へ。」を制定し、ブランド適用範囲に統一ブランド化の方針を追記
2. **マスター設計プロンプトの新設:** [PROMPTS/DESIGN/CompanyBrain.md](../PROMPTS/DESIGN/CompanyBrain.md) を新設。コピー・3つの利用シナリオ(契約更新/資格手当/有給申請)・UI方針(ガラスUI・Smart Blue)・アイコン方針(Brain/Network/Knowledge/AI)・Company Brain専用画面の構成(検索バー/AIチャット/最近閲覧した資料/人気の社内マニュアル/お気に入り)を集約し、ホームページ・Dashboard・営業資料・パンフレット・デモ画面すべてで参照する正本とした
3. **Homepage.md/Dashboard.mdの更新:** [PROMPTS/DESIGN/Homepage.md](../PROMPTS/DESIGN/Homepage.md) のCompany Brainセクション仕様をCompanyBrain.mdへの参照込みで更新。[PROMPTS/DESIGN/Dashboard.md](../PROMPTS/DESIGN/Dashboard.md) のサイドバーナビ標準レイアウトに「Company Brain」を追加(専用アイコン付き、他項目に埋没させない扱い)
4. **Roadmapの更新:** [00_Foundation/10_Future_Roadmap.md](00_Foundation/10_Future_Roadmap.md) に「Company Brain 拡張ロードマップ」を新設し、将来追加予定機能(動画検索/PDF検索/Word・Excel・PowerPoint検索/画像OCR/社内Wiki/議事録検索/音声検索/AI要約/多言語対応)を記録
5. **Homepage実装:** [WEBSITE/index.html](../WEBSITE/index.html) / [WEBSITE/css/style.css](../WEBSITE/css/style.css) のCompany Brainセクションを全面拡張。専用のBrain/Networkアイコンバッジ、キャッチコピー「探す時間を、考える時間へ。」、9項目に拡充した知識ソースのチップ表示、AIの回答に「関連資料」「次のアクション」チップを追加(AIが答えるだけでなく案内する存在であることを視覚化)、3つの利用シナリオを複数ステップの案内フローとして見せる新セクション、ガラスUI(glassmorphism: backdrop-filter blur + 半透明パネル)を採用。デスクトップ(1440px/1024px)・モバイル(375px)でプレビュー確認済み、レイアウト崩れ・コンソールエラーなし
6. **Dashboard・営業資料・パンフレット・デモ画面への展開:** この時点(v2.6作成時)ではDashboardはまだ実体が存在しなかった(Dashboard Version 0.0)ため、仕様レベル(PROMPTS/DESIGN配下)への反映にとどめていた。その後、別セッションでDashboard v0.5が実装され、Company Brain専用画面も実装済みとなった(下記v2.8参照)。営業資料・パンフレットへの展開は引き続き未着手

**新章「Development Principles」の新設(2026-07-05):** CEOより、今後のSmart Labo Works開発における基本原則を定めた新章の追加指示があり、[11_Development_Principles.md](11_Development_Principles.md) を新設した。`00_Foundation` の01〜10がすべて埋まっているため、開発関連ドキュメント(`10`〜`19`)の枠に `11` として挿入した(既存ファイルの番号は変更していない)。

主な内容:

- **開発前ルール(必須):** 新しい画面・機能・デモ・UIを作成する前に、PROJECT_BIBLE / Brand Bible([00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md)) / Design Bible([PROMPTS/DESIGN/SmartLabo_Design_Bible.md](../PROMPTS/DESIGN/SmartLabo_Design_Bible.md)) / CURRENT_STATUS / CHANGELOGの最新版を必ず確認すること
- **Smart Labo Design Principle:** 「社長の時間を増やせるか」「Company OSの思想に沿っているか」等、5項目のYES/NOチェックリスト。YESにならない機能は実装しない
- **デモ画面作成ルール:** デモ画面は単なるUIサンプルではなく、営業で見せる「製品デモ」として設計する
- **Company Brain優先:** 今後作成する画面ではCompany Brainとの連携を常に検討する
- **Dashboard設計原則:** DashboardはCRMではなく**Company OS**であり、社長が毎朝最初に開く画面として設計する
- **最重要原則:** Smart Labo Worksは「AIチャット」ではなく「会社を進化させるCompany OS」である

**「Company OS」という新しい用語について:** 本章で初めて登場した表現であり、既存の「会社を動かすAI。」(ブランドコピー)や「AI経営プラットフォーム」と矛盾するものではなく、Dashboard・Smart Labo Worksの性格を一言で表す補完的な位置づけとして扱う。[00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md) の正式なコピー階層(ブランドコピー/サービスコピー/説明コピー)自体は今回変更していない。CEOより将来的に正式なブランドコピー階層への統合指示があれば、その時点でBrand Identityを更新する。

**「CEOレビュー原則」の追加(2026-07-05):** CEOより、大きな仕様変更・ブランド変更・UI変更は実装前にレビューを受けてから開発を進めること、独自判断でブランドコンセプト・UI方針を変更しないこと、という指示があり、[11_Development_Principles.md](11_Development_Principles.md)(v1.0→v1.1)に「CEOレビュー原則」を新設した。既存の[60_Editorial_Workflow.md](60_Editorial_Workflow.md)「推測で判断しない(エスカレーションルール)」を強化する位置づけであり、両ファイルに相互参照を追加した。Smart Laboが長期的なブランド構築を重視する姿勢を明文化したもの。

---

## ChatGPTとの同期ルール

- 新しいChatGPTの会話を始める前に、必ずこのファイルの「5行サマリー」を確認・共有してください。
- ChatGPTとの議論で、ブランド・Mission・Vision・UI・デザイン・システム構成・ロードマップなどの方針変更があった場合、CEOはClaude Codeに更新を指示してください。Claude CodeはPROJECT_BIBLEへ反映したうえで、このファイルも更新します。
- 詳細な役割体制・更新フローは [60_Editorial_Workflow.md](60_Editorial_Workflow.md) を参照してください。

---

## 更新ルール

- このファイルは**作業のたびに更新する「生きたファイル」**です。個別ファイルの「変更履歴」テーブル(追記型)とは異なり、常に最新状態に上書きしてください。過去のスナップショットはGit履歴で追跡可能です。
- GitHub運用フロー(git status確認からpushまで)は [60_Editorial_Workflow.md](60_Editorial_Workflow.md) の「GitHub運用フロー」を参照してください。
- Maintainerが変わった場合は、この項目を更新してください。

---

## 変更履歴

| バージョン | 日付 | 変更者 | 変更内容 |
|---|---|---|---|
| v1.0 | 2026-07-03 | Claude Code(CEO指示による) | 初版作成。11項目のステータス、5行サマリー、Versionの出典トレーサビリティを整備 |
| v1.1 | 2026-07-03 | Claude Code(CEO指示による) | Homepage初回実装完了に伴いHomepage Versionを0.0→0.1に更新。Current TaskをDashboard UI設計に更新 |
| v1.2 | 2026-07-03 | Claude Code(CEO指示による) | Hero/CTA/機能バー/レスポンシブをレビューし、Hero背景(都市シルエット・AIネットワーク)がコンテンツに隠れて視認できない不具合を修正 |
| v1.3 | 2026-07-03 | Claude Code(CEO提供のブランドキット参照資料による) | Brand Version 4.2→4.3、Design Bible Version 2.1→2.2に更新。正式カラーパレット確定に伴い、Homepage実装が旧配色のままである旨をNext Taskに追記 |
| v1.4 | 2026-07-03 | Claude Code(CEO指示による) | Project Bible Version 2.4→2.5、Brand Version 4.3→4.4に更新。[DESIGN_ASSETS/](../DESIGN_ASSETS/README.md) 新設(デザイン資産の実データ管理場所)とSmart Blue Family確定を反映 |
| v1.5 | 2026-07-03 | Claude Code(CEO指摘による) | 「ロゴもデザインも全く違う」との指摘を受け、Homepage Versionを0.1→0.2に更新。正式カラーパレットへの置換とロゴ再デザインが完了した旨を反映 |
| v1.6 | 2026-07-03 | Claude Code(CEO指示による) | [DESIGN_ASSETS/01_HERO/Backgrounds/](../DESIGN_ASSETS/01_HERO/README.md) 新設を反映。Hero背景候補5点のメタデータ整備状況とファイル提供待ちである旨をNext Task・注記に追加 |
| v1.7 | 2026-07-03 | Claude Code(CEO指示による) | Project Bible Version 2.6→2.7に更新。[AI_WORKSPACE/](../AI_WORKSPACE/README.md) 新設(ChatGPT発素材の正式な受け渡し経路)を反映し、Next TaskのHero背景画像受け取り口を`AI_WORKSPACE/INBOX/HERO/`に更新 |
| v1.8 | 2026-07-03 | Claude Code(CEO提供による) | Project Bible Version 2.7→2.8に更新。AI_WORKSPACE経由でHero候補5点のモックアップを受領・[DESIGN_ASSETS/01_HERO/Mockups/](../DESIGN_ASSETS/01_HERO/Mockups/README.md) へ格納したことを反映。実装用の背景単体データが引き続き必要である旨をNext Taskに明記 |
| v1.9 | 2026-07-03 | Claude Code(CEO指示による) | Homepage Versionを0.2→0.3に更新。Hero背景をCSS/SVGのみで強化(フルワイド都市シルエット・AIネットワークのグロー・ドットグリッド)した旨を反映。新規バイナリ資産の追加なし |
| v2.0 | 2026-07-03 | Claude Code(CEO提供による) | Project Bible Version 2.8→2.9、Homepage Version 0.3→0.4に更新。AI_WORKSPACE経由で受領したHero背景単体データ5点をDESIGN_ASSETSへ格納しHomepageへ実装。アイコンのバッジスタイルをHomepageへ反映。多色配色・別ロゴ案は不採用である旨と、CEOへの確認事項をNext Taskに明記 |
| v2.1 | 2026-07-04 | Claude Code(CEO指示による) | **正式ロゴ確定を反映。** Project Bible Version 2.9→3.0、Brand Version 4.4→5.0、Homepage Version 0.4→0.5に更新。[DESIGN_ASSETS/01_LOGO/](../DESIGN_ASSETS/01_LOGO/README.md) 新設とHomepage実装完了、以前の暫定ロゴ仕様・別ロゴ案の廃止を反映 |
| v2.2 | 2026-07-04 | Claude Code(CEO指示による) | **Homepageをβ版として公開。** Homepage Versionを0.5→0.9 betaに更新。公開前チェック(スマホ表示/リンク切れ/CTA動作/表示崩れ/画像読み込み)の実施結果を記録。Xserverへの実デプロイはFTP/SFTP接続情報確認中のため保留であることを明記 |
| v2.3 | 2026-07-04 | Claude Code(CEO指示による) | Xserverがサーバー契約未完了(ドメインのみ取得)と判明したことを受け、暫定デプロイ先として**GitHub Pages**を採用。[.github/workflows/pages.yml](../.github/workflows/pages.yml) を新設。CEOによるリポジトリSettings→Pages設定の手動対応が必要である旨を明記 |
| v2.4 | 2026-07-05 | Claude Code(CEO対応による) | **Homepageの公開完了を反映。** 公開URL(`https://smartlabosukimagakusyuu-dev.github.io/SmartLabo/`)を記録。Workflow permissions設定の不備によるデプロイ失敗とその解決経緯を記録。Last Updateを2026-07-05に更新 |
| v2.5 | 2026-07-05 | Claude Code(CEO指示による) | **Homepage Versionを0.9 beta→1.0 Release Candidateに更新。** Project Bible Version 3.0→3.1に更新。Company Brainセクション新設・ダッシュボードのデモ表記・CTA導線整理・ヘッダー折り返し崩れの修正・404ページ/OGP/favicon最適化を反映。Next Taskに「実際のお問い合わせ窓口確定後のCTA接続」を追加 |
| **v2.6** | 2026-07-05 | Claude Code(CEO指示による) | **Company BrainをSmart Labo Works最大の差別化要素として正式にformalize。** Project Bible Version 3.1→3.2、Brand Version 5.0→5.1、Design Bible Version 2.2→2.3に更新。新設の[PROMPTS/DESIGN/CompanyBrain.md](../PROMPTS/DESIGN/CompanyBrain.md)をマスター設計プロンプトとして、コピー・3利用シナリオ・UI方針・アイコン方針・専用画面構成を集約。Homepage実装を全面拡張(キャッチコピー・9項目知識ソース・次アクション案内・3シナリオ・ガラスUI)。Dashboard.mdサイドバーへの追加、Roadmapへの拡張機能追加を反映 |
| **v2.7** | 2026-07-05 | Claude Code(CEO指示による) | **新章[11_Development_Principles.md](11_Development_Principles.md)を新設。** Project Bible Version 3.2→3.3に更新。開発前ルール(5点確認必須)、Smart Labo Design Principle(5項目チェックリスト)、デモ画面作成ルール、Company Brain優先方針、Dashboard設計原則(Company OS)、最重要原則を制定。今後のDashboard UI実装・営業資料・パンフレット等、すべての新規開発はこの章を判断基準とすること |
| **v2.8** | 2026-07-05 | Claude Code(別セッションでのCEO作業をマージ) | **Dashboard Version 0.0→0.5を反映。** 別セッションで`WEBSITE/app.html`が実装され、GitHub Pagesで公開された(Company Brain専用画面・AIモーニングブリーフィング・KPI拡張・アクティビティカラーコーディング・クイックアクション7項目・公式デザイントークン適用)。Smart Labo Works Versionも0.0→0.5に更新。詳細は[CHANGELOG.md](CHANGELOG.md)「Dashboard v0.5」エントリを参照。マージ時点の確認により、`app.html`のログイン画面ロゴが正式ロゴ([00_Foundation/07_Brand_Identity.md](00_Foundation/07_Brand_Identity.md)の六角形+サーキット「S」)ではなく暫定的な六角形+チェックマークのSVGになっていることを検出。ブランド統一の観点で今後の確認・修正が必要(50_TODO.mdに追記) |
| v2.9 | 2026-07-05 | Claude Code(CEO指示による) | **「CEOレビュー原則」を追加。** Project Bible Version 3.3→3.4に更新。[11_Development_Principles.md](11_Development_Principles.md)(v1.0→v1.1)に、大きな仕様変更・ブランド変更・UI変更は実装前にレビューを受けること、独自判断でブランドコンセプト・UI方針を変更しないことを明文化。[60_Editorial_Workflow.md](60_Editorial_Workflow.md)(v2.2→v2.3)と相互参照を追加 |
| **v3.0** | 2026-07-10 | Claude Code(CEO指示による) | **Smart Labo Works(顧客向けSaaS)とCompany OS(社内専用システム、非売品)の製品境界を正式決定。** Project Bible Version 3.4→3.5に更新。[11_Development_Principles.md](11_Development_Principles.md)(v1.1→v1.2)・[00_Foundation/08_SmartLaboWorks_Concept.md](00_Foundation/08_SmartLaboWorks_Concept.md)(v3.0→v3.1)から、Company OSをSmart Labo Works自体の形容として使っていた箇所を削除・修正。「Smart Labo Group」は非公式な将来のブランド構想、「Smart Labo AI」「Smart Labo CRM」は将来の商品候補(正式製品ではない)と明記。詳細な機能境界は`smartlabo-works/PRODUCT_BOUNDARY.md`(別リポジトリ)を参照する構成にした |
| v3.0.1 | 2026-07-10 | Claude Code(マージ後の是正) | リモート側で並行していた2026-07-07付コミット(`WEBSITE/app.html`を「Smart Labo Works v1.0 — Company OS完成」とする内容)をマージした際に生じた矛盾を是正。ステータス表の該当箇所から「Company OS完成」という表現を削除し、2つの異なる「Smart Labo Works v1.0」が並立している旨を暫定的に注記した |
| **v3.1** | 2026-07-10 | Claude Code(CEO指示による) | **Smart Labo Worksの唯一の正式コードベースを`smartlabo-works`(Node.js版)に確定。** Project Bible Version 3.5→3.6に更新。`WEBSITE/app.html`はSmart Labo Works正式製品ではなく「デモサイト／マーケティング用プレビュー」へ位置づけ変更。5行サマリー・ステータス一覧・Versionの出典表を、「デモサイト Version」と「Smart Labo Works Version(別リポジトリ参照)」を分離する形に全面改訂し、v3.0.1で残っていた未決定の注記を解消した |
| **v3.2** | 2026-07-10 | Claude Code(CEO承認による) | **「Version1.0リファクタリング」5Step計画の開始を反映。** Project Bible Version 3.6→3.7に更新。CEO承認により、Smart Labo Worksを販売可能なSaaSへ仕上げる5Step(Task1:Git初回コミット→Task2:REFACTOR_PLAN作成→Task3:サーバー認証→Task4:AI Router整理→Task5:Company OS表記修正)を開始。新設の「Version1.0リファクタリング進捗」表でStepごとの状況を追跡する運用とした。Task1(`smartlabo-works`ソースコードのGit初回コミット、コミット`326fdc7`)完了を反映 |
| **v3.3** | 2026-07-10 | Claude Code(CEO承認による) | **Task2完了を反映。** Project Bible Version 3.7→3.8に更新。`smartlabo-works/REFACTOR_PLAN.md`(新規、コミット`9098544`)を作成し、Phase1(認証・企業分離・AI Router・CRM保存・app分割)、Phase2(物件紹介文・査定コメント・テンプレート・メール)、Phase3(OCR・Whisper・Gemini・Meeting AI)の各Stepに対象ファイル・変更範囲・リスク・コミット単位・完了条件を記載したことを反映。Next TaskをTask3(サーバー認証)に更新 |
| **v3.4** | 2026-07-10 | Claude Code(CEO承認による) | **Task3完了を反映。** Project Bible Version 3.8→3.9に更新。最小構成のサーバー認証を実装(`5adf4bb`→`653f754`→`9d7a5d9`、3コミット)：①`src/services/auth/authService.js`新設(scryptハッシュ検証・インメモリセッション、companyId="default"固定でWorkspace→Company→User→Role構造を見据えた設計)、②ログイン画面をサーバーAPIへ接続しHTML/JSからパスワードを完全除去、③`/api/ai/*`全エンドポイントに認証ガードを追加。ブラウザでの動作確認済み(正しいログイン→200、誤ったパスワード→401、未ログインAPI呼び出し→401、ログアウト後のセッション破棄を確認)。Next TaskをTask4(AI Router整理)に更新 |
| **v3.5** | 2026-07-10 | Claude Code(CEO承認による) | **Task4完了を反映。** Project Bible Version 3.9→4.0に更新。AI Routerを正式整理(`a76d66c`→`f81d64d`、2コミット)：①IProviderインターフェースへ`isAvailable()`を追加しopenai/claudeの両Providerへ実装、`router.js`の`isProviderAvailable()`をProvider自身への委譲に変更(Provider固有のconfig参照をRouterから排除)、②Gemini/Whisper/OCRの構造スタブを新設しPROVIDERSへ登録(実API呼び出しは実装せず、Phase3まで`isAvailable()`は常にfalse)。完了条件どおり、新Provider追加時にrouter.jsのロジック本体を書き換えずに済む構造になったことを確認。ブラウザでの動作確認済み(既存のOpenAI経由Company Brain呼び出しに影響なし、5Provider全ての登録・可用性判定が正しく動作)。Next TaskをTask5(Company OS表記修正)に更新 |
| **v3.6** | 2026-07-10 | Claude Code(CEO承認による) | **Task5完了を反映し、「Version1.0リファクタリング」5Stepが全完了。** Project Bible Version 4.0→4.1に更新。`app.html`・`manifest.json`内の「Company OS」文言13箇所すべてをSmart Labo Worksへ統一(コミット`b864dd4`)。ログイン画面・Builderのhero文言/バッジ/生成ボタン/進捗ステップ/完了画面/一覧説明・削除確認ダイアログ・DashboardのWork Flow見出し・PWA description。`generateCompanyOS()`関数も`generateWorkspace()`へリネーム(既存の`WORKSPACE_*`命名規則と統一)。ブラウザで`document.body.innerText`に「Company OS」が一切含まれないことを確認し、Builder生成フローもエンドツーエンドで正常動作を確認。ステータス一覧・5行サマリーを「5Step完了、次スコープ提案の承認待ち」に更新 |
| **v3.7** | 2026-07-11 | Claude Code(CEO承認による) | **Sprint2 Task6完了を反映(企業単位データ分離)。** Project Bible Version 4.1→4.2に更新。新設の「Sprint2進捗」表を追加。3コミット(`d7ea0cd`→`0b8fedf`→`707ef92`)：①AIログを`aiLogsByCompany`(Map)へ変更しcompanyId分離、②認証設定を`config.auth`(単一アカウント)から`config.companies`(CompanyID→User[])構造へ再設計、③クライアント側のCompany Brain/CRM/案件/契約のlocalStorageキーを`companyScopedKey()`経由でCompanyID別に分離し、ログイン時のCompanyID切替でページを再読込する仕組みを追加。Innovation Hub等の社内専用データ(INNOVATION_KEY等)は対象外とし、`PRODUCT_BOUNDARY.md`の分類との整合を確認。ブラウザで実際に2社分のダミーデータを作り、互いに影響しないことを確認(defaultの5件のCRMデータが別CompanyIDのテストデータ追加後も無傷)。Next TaskをTask7(内容未確定、CEO承認待ち)に更新 |
| **v3.8** | 2026-07-11 | Claude Code(CEO承認による) | **Sprint2 Task7完了を反映(CRM・案件・契約のサーバー永続化)。** Project Bible Version 4.2→4.3に更新。3コミット(`5064513`→`afa1baf`→`41ac221`)：①`node:sqlite`(Node.js組み込み、npm依存なし)でCRM/deals/contractsテーブルを新設、company_idインデックス付き・レコードはJSON列で保存する汎用`recordStore`、②`GET/POST/PUT/DELETE`のREST APIをcrm/deals/contractsに追加し認証ガードを`/api/*`全体(auth系除く)へ拡張、③フロントエンドをlocalStorageからAPI呼び出しへ切替え、旧localStorageデータの一度きり自動移行処理を追加。保存方式はSQLite・JSON列・PostgreSQLを比較しSQLiteを採用(理由はTask7完了報告参照)。動作確認中、Service Worker(`sw.js`)がapp.htmlを古いバージョンでキャッシュしたままになる不具合を発見しCACHE_NAMEをv2へ更新。ブラウザで旧データの自動移行・CRM/deals全CRUD操作・別CompanyIDとの分離を確認。Next TaskをTask8(Company Brainサーバー永続化、CEO承認待ち)に更新 |
| **v3.9** | 2026-07-11 | Claude Code(CEO承認による) | **Sprint2 Task8完了を反映(Company Brainのサーバー永続化)。** Project Bible Version 4.3→4.4に更新。3コミット(`09a2315`→`6b5cfc5`→`75fdaf2`)：①`brain`テーブルをTask7と同じ`recordStore`パターンで追加、②`GET/POST/PUT/DELETE /api/brain`を追加(AI問い合わせ用の`POST /api/ai/brain`とはパス・役割とも別物、正規表現の衝突なしを確認)、③フロントエンドの4箇所の書き込み処理(`saveBrainEntry`・`deleteBrain`・Innovation Hubからの`registerToBrain`・初期データへの`resetBrain`)をAPI呼び出しへ切替え、旧localStorageデータの自動移行にもbrainを追加。Company BrainのAI問い合わせ(`/api/ai/brain`)自体のロジック・プロンプトは一切変更していない(Sprint2の禁止事項どおり)。ブラウザで旧データ移行・CRUD全操作・AI問い合わせが移行後のデータを正しく参照すること・リセット機能を確認。Next TaskをTask9(内容未確定、CEO承認待ち)に更新 |
| **v4.0** | 2026-07-11 | Claude Code(CEO承認による) | **Sprint3開始、Task1完了(Smart AI Router基盤実装)。** Project Bible Version 4.4→4.5に更新。新設の「Sprint3進捗」表を追加。4コミット(`77a533b`→`67f8a98`→`468c2fd`→`6c85b30`)：①新設`src/services/router/`にAIProviderインターフェース(chat/summarize/generate/analyze/testConnection/isAvailable)・9jobType分のルーティングテーブル(Company Brain/営業メール/査定コメント→OpenAI、契約書/長文整理/提案書→Claude、PDF解析/画像解析/図面→Gemini)・キーワードベースの簡易分類器・内部ログのみのRouter Monitorを追加、②OpenAIProvider(既存実装への薄いアダプター)・ClaudeProvider(同)を追加、③GeminiProvider(新規、Gemini generateContent APIへの実接続。GEMINI_API_KEY未設定のためok:true疎通までは未確認、エラーハンドリングの正常動作は確認済み)を追加、④routerService.js(dispatch関数、①入力解析②仕事分類③AI呼び出し④レスポンス統合の一連の流れ)を追加。既存の`src/services/ai/router/`(Task4)・`server.js`・`app.html`は無変更、`/api/ai/*`エンドポイントへの影響なし。node script経由でgetRouterStatus・実OpenAI呼び出し・Claude未設定時のエラー処理・キーワード分類・モニターログ記録をすべて確認。REFACTOR_PLAN.md Step3.3(Gemini)の記述が本実装と食い違うため、両Routerが並存している旨を追記した。Next TaskをTask2(Claude API正式実装、CEO承認待ち)に更新 |
| **v4.1** | 2026-07-11 | Claude Code(CEO承認による) | **Sprint3 Task2完了(Claude API正式実装)。** Project Bible Version 4.5→4.6に更新。コミット`9f11bab`：ClaudeProviderのsummarize/generate/analyzeを、routeTable.jsの実割り当て(長文整理・提案書)に合わせた専用プロンプトへ差し替え(要点保持・決定事項の明示・「課題→解決策→期待効果」構成等)。chat()自体は呼び出し元がsystemPromptを渡す設計のため変更なし。**この環境にANTHROPIC_API_KEYが未設定のため、実際のAPI成功応答は確認できていない。** dispatch経由で3つのClaude割当jobType(契約書・長文整理・提案書)すべてが正しくルーティングされ、クラッシュせず明確なエラーで失敗すること、Router Monitorへの記録を確認済み。server.js・app.html・既存Routerへの変更なし。Next TaskをTask3(内容未確定、CEO承認待ち)に更新 |
| **v4.2** | 2026-07-11 | Claude Code(CEO承認による) | **Sprint3 Task3完了(Company Brain Builder)。** Project Bible Version 4.6→4.7に更新。コミット`6f31a55`(TOEICアプリ repo)：誰でも約30分でCompany Brainを作成できる6Step Wizardを新設(STEP1業種選択→STEP2会社情報→STEP3商品・サービス→STEP4業務ルール→STEP5 FAQ→STEP6完成)。既存のTask8 REST API(`/api/brain`)経由で保存し、AI自動生成・PDF/Word読込・OCR・Whisper・Gemini・OpenAI生成・Builder自動化は今回実装せず。UIは既存の`.builder-*`/`.template-*`/`.progress-*`等の既存CSSクラスを再利用し新規グローバルCSSは追加していない。ブラウザで6Step全体の入力・戻る/次へでの状態保持・業種選択の無効オプションガード・必須項目バリデーション・保存後に会社概要1件/商品説明1件/社内ルール1件/FAQ1件の計4件が正しくCompany Brainページに反映されること、既存の`/api/ai/brain`(AI問い合わせ)が新規保存データを正しく参照し無停止で動作することを確認。Next TaskをSprint3 Task4(不動産AIテンプレート、CEO承認待ち)に更新 |
| **v4.3** | 2026-07-12 | Claude Code(CEO承認による) | **Sprint3 Task4 Step2.1〜2.2完了、Template Engine正式採用。** Project Bible Version 4.7→4.8に更新。3コミット(`9321880`→`90773dc`→`1347104`、TOEICアプリ repo)：①Step2.1「物件紹介文」・②Step2.2「査定コメント」の2テンプレートを実装(既存AI Router・Providerを再利用、DBテーブル追加なし)、③CEO指示によりAIテンプレート基盤を「Template追加型」の正式アーキテクチャ(Template Engine)として確定。`templates/index.js`(Template Loader)が`templates/<namespace>/`(realestate/management/legal/tax/common)配下を自動走査しid/namespaceをファイルパスから自動生成、`promptManager.js`はswitch文を持たずTemplate Loaderへの窓口(`promptManager.templates`)として動作。実装済みはrealestate/listing.js・realestate/appraisal.js(Step2.2の承認済み内容をそのまま移動)の2件のみで、残り19ファイルは雛形(`status:'planned'`、UI非表示・生成は明確なエラー)。AI Router・Providerは無変更(Routerは業種ロジックを一切持たず、feature→Providerの解決のみ)。ブラウザで両テンプレートの生成・既存`/api/ai/assistant`との無停止共存・雛形テンプレートへの生成試行が正しくエラーを返すことを確認。Next Taskは他業種テンプレート追加(CEO承認待ち)に更新 |
| **v4.4** | 2026-07-12 | Claude Code(CEO承認による) | **Sprint3 Task4「営業テンプレート」「Appointment Template」ファミリー第一弾完了。** Project Bible Version 4.8→4.9に更新。2コミット(`8c19601`→`f4e6425`、TOEICアプリ repo)：①`realestate/mail.js`(物件紹介メール)を雛形から実体化。入力は顧客名・物件名・物件紹介文(任意)・おすすめポイント・担当者名・会社名。`realestate/listing.js`には依存せず「物件紹介文」欄はlisting.js出力の貼り付け・手入力・空欄いずれでも動作。会社名はテナントごとに異なるためハードコードせず入力項目化。②`realestate/visit.js`(内覧・来店予約案内、新規ファイル)を追加。入力は顧客名・物件名・候補日時(複数可・改行区切り)・集合場所・担当者・持参物/連絡先/備考(いずれも任意)。候補日時は将来のGoogle Calendar/Outlook/予約システム連携を見据え、現時点では構造化せず自由テキストとして扱う。いずれも「1ファイル=1用途」の原則を維持し、他用途を判定するswitch文を書かず、将来の拡張(followup/thanks/contract/proposal、来店予約/オンライン商談/現地案内/契約日時/鍵渡し/引渡し等)は同形式のファイル追加のみで対応する設計とした。両テンプレートとも変更ファイルは1つのみで、Template Loader・Service・API・UIの追加変更は不要だった(Template Engineの「ファイル追加のみで拡張できる」設計を実証)。ブラウザで両テンプレートの生成(候補日時が箇条書きで見やすく表示されること・返信しやすい構成・キャンセル/変更案内を含むこと等を確認)・既存機能との無停止共存を確認。Next Taskは追加のテンプレート実装(CEO承認待ち)に更新 |
| **v4.5** | 2026-07-12 | Claude Code(CEO承認による) | **ホームページVersion2リニューアル完了。** Project Bible Version 4.9→5.0に更新。コミット`9a63a2b`(SmartLabo repo)：`WEBSITE/index.html`を「会社紹介サイト」から「営業サイト」へ全面刷新し、Hero/Smart AI Router図解/AI社員カード(6種)/Company Brain/画面ショーケース(横スクロール)/業種展開/導入効果ストーリー/料金プラン/最終CTAの9セクション構成へ再編。実装画面はスクリーンショットではなくHTML/CSSでの再現とした(CEO方針「写真よりUI・アニメーション・図解」に対応)。業種展開カードは不動産売買のみ「対応中」、他5業種は「今後対応予定」と正確に区別(CEO指示は全業種同一表示だったが、既出荷機能の過小申告を避けるため表示を修正、完了報告で明記)。CEO指示のAI社員カード一覧にあった「Builder」は実装ルール「社内専用機能は顧客向けに非表示」と矛盾するため6種構成から除外。既存ブランドカラー・正式ロゴのみ使用、「Company OS」表記なし。ブラウザでデスクトップ(1440px)・モバイル(375px)双方のレンダリング、Router図解アニメーション、画面ショーケースの横スクロール、ハンバーガーメニュー、コンソールエラーなしを確認。SmartLabo repoはpush未実施、CEO確認待ち |
| **v4.6** | 2026-07-12 | Claude Code(CEO承認による) | **Smart Labo AIホームページチャットデモ実装完了。** Project Bible Version 5.0→5.1に更新。コミット`696b3ca`(SmartLabo repo)：右下固定チャットボタン→ウィンドウのUIを新設し、初回挨拶＋「体験できるAI」一覧(7種、司法書士AI/管理会社AIはComing Soon)＋おすすめ質問8件をチップ表示。CEO指定の6機能(営業メール/物件紹介文/査定コメント/FAQ/会社紹介/提案書)はキーワード分類による高品質なデモ回答、その他は情報系Q&Aまたは無料デモ予約/お問い合わせへの誘導で対応。**実装前にアーキテクチャ上の制約(実際のSmart AI RouterはsmartlaboWorksサーバー内にあり、本番デプロイ・公開APIが存在しない)をCEOへ確認し、「今回はUI/UXのみ実装し、AI生成はダミー応答で代替する」との回答を得たうえで実装した**(公開APIの新規デプロイはコスト・セキュリティ上の判断を伴うため実施していない)。会話履歴はlocalStorageで保持しリロード後も復元されることを確認。ログイン・CRM・Meeting・Builder・Whisper・OCR・画像生成は実装せず(CEO指示どおり)。デスクトップ・タブレット・モバイルで表示確認済み |
| **v4.7** | 2026-07-12 | Claude Code(CEO承認による) | **ホームページ情報量整理完了。** Project Bible Version 5.1→5.2に更新。コミット`972d201`(SmartLabo repo)：CEO指示「装飾を増やすのではなく、ページを短くし3秒で価値が伝わるデザインへ」に基づき、**実装前に新構成案・削除統合対象を報告しCEO承認を得たうえで**実施。セクション数11→7、背景ブロック11→5(Navy=Hero／Navy-deep=CompanyBrain+Router／White=AI社員+画面ショーケース／Gray=導入効果+Mission／Navy-gradient=CTA)。Company BrainをHero直後へ移動。「AI社員」と「社長の1日タイムライン」(計12項目)を、時間帯タグ付きのAI社員カード6枚へ統合し重複解消。画面ショーケースを6枚の小カード横スクロールから、Dashboard・不動産AIの大型2枚構成へ刷新。「対応業種」「料金」の2セクションを削除(実質「今後対応予定」「お問い合わせください」のみの準備中表示だったため、非表示ルールに従った)。Heroのリード文を1行に短縮、CTAを「デモを見る」「導入相談」の2つに全ページ統一。ブラウザでデスクトップ(1440px、2カラム)・モバイル(375px、1カラム)双方のレイアウト、全リンクの整合性、コンソールエラーなしを確認。SmartLabo repoはpush未実施、CEO確認待ち |
| **v4.8** | 2026-07-12 | Claude Code(CEO承認による) | **Hero「AI Control Center」実装完了。** Project Bible Version 5.2→5.3に更新。コミット`0939f05`(SmartLabo repo)：**実装前にHero新レイアウト・AI Control Center構成・スクロール後のセクション順・削除統合対象を報告しCEO承認を得たうえで**実施。Hero右側の`.device`縮小ダッシュボードモックアップを廃止し、Smart AI Router LIVEバッジ・OpenAI/Claude/Gemini稼働状況・ルーティング例3件・Company Brain参照中・本日のAI処理件数・最近完了したAIタスクを表示する半透明グラスパネル「AI Control Center」へ置換(CSS疑似要素によるツリー結線と控えめなopacityパルスのみ、新規ブランドカラーなし)。独立した「Smart AI Router」セクションと「AI社員」カードセクション(6枚)を削除し(CEO承認理由：「HeroでAIの連携をすでに表現しており役割が重複するため」)、業務フロー図(Smart AI Router→Company Brain→営業/契約/会議/CRM)としてCompany Brainセクションへ統合。画面ショーケースを「Company Brain→不動産AI→CRM→実際の製品画面(Dashboard)」の順に再構成し、前回整理で削除したCRMを証拠提示として復活。セクション数7→5。ブラウザでデスクトップ・モバイル双方のAI Control Center表示、接続線・パルスアニメーションの適用(JS経由でanimation-name検証)、業務フロー図、コンソールエラーなしを確認。SmartLabo repoはpush未実施、CEO確認待ち |
| **v4.9** | 2026-07-12 | Claude Code(CEO承認による) | **Hero Experience Upgrade v3完了。** Project Bible Version 5.3→5.4に更新。コミット`59d05d3`(SmartLabo repo)：レイアウト(左コピー／右AI Control Center)は無変更のまま、AI Control Centerを静的UIから常時アニメーションする"Live Dashboard"へ進化。新規`WEBSITE/js/hero.js`が2つのタイマーを駆動：①2.6秒ごとに「問い合わせ返信中…/契約レビュー中…/PDF・画像解析中…/Company Brain検索中…」を巡回しProviderノードの発光ハイライトと同期(旧・静的なルーティング例リストを置き換え)、②4.2秒ごとにCompany Brainの状態(参照中→更新中→学習完了)を巡回。Smart AI Routerノードはbox-shadowが呼吸するように発光(3.6秒周期)、接続ラインはbackground-positionアニメーションで小さな光が流れる演出に変更。KPIカード2x2(本日のAI処理件数/Company Brain登録数/契約レビュー件数/問い合わせ返信数)を新設し旧stat行を置換。背景に14秒周期のゆっくりとしたglowドリフトと6個の淡い浮遊粒子を追加(既存のグリッド・都市写真は無変更)。Heroコピーは「会社を動かすAI。」を主役(54px)、「Smart Labo Works」を副題(20px)に強弱を反転。Hero最下部に「Company Brainを見る」スクロール誘導(モバイルでは非表示)を新設。`prefers-reduced-motion: reduce`で全アニメーションを無効化。ロゴ・ブランドカラー・CTA・ナビゲーション・Heroレイアウト・Company Brain構成はCEO指示どおり無変更。ブラウザでJS経由の状態ローテーション・発光同期・アニメーション適用・モバイル375pxでのレイアウト、コンソールエラーなしを確認。SmartLabo repoはpush未実施、CEO確認待ち |
| **v5.0** | 2026-07-12 | Claude Code(CEO承認による) | **Smart Labo Works 詳細ページ構築完了。** Project Bible Version 5.4→5.5に更新。コミット`a51b773`(SmartLabo repo)：**実装前にワイヤーフレーム・ナビゲーション構成・移動する情報・使用する実画面・表示ルール・URL構成を報告しCEO承認を得たうえで**実施。トップページの役割を「興味を持たせる」に限定し、`product.html`(製品紹介)・`features.html`(機能一覧、10機能を統一構成で詳解、sticky sidebar)・`real-estate.html`(不動産売買仲介向け、よくある課題→AIテンプレート→CRM等→業務フロー→導入イメージ→β版募集CTA)・`pricing.html`(Beta/Standard/Enterprise、価格は個別相談、機能比較表)・`contact.html`(9項目の入力フォーム、送信先未接続を明示しクライアント側バリデーションのみ実装)を新設。実装済み／Coming Soon／非表示(社内専用)の3区分表示ルールを全ページへ統一適用し、Company OS・Builder・Innovation Hub・Smart Growth・Company Memory等のv1.0対象外機能は一切表示していない。Smart AI RouterのClaude/Gemini本番未検証という開発環境固有の事情は顧客向け表記に反映しない方針をCEOに確認済み。ナビゲーションを全ページ「製品/機能/不動産向け/料金/会社情報」に統一し、`index.html`の導入相談導線を`#cta`アンカーから`contact.html`へ変更。新規CSS(page-hero/status-badge/FAQアコーディオン/feature-block/problem-grid/compare-table/contact-form)は既存コンポーネントを最大限再利用し新規ブランドカラーなし。ブラウザで5ページのレンダリング・FAQ開閉・機能ブロックとサイドバーの対応・料金比較表・フォームバリデーション(未入力6件エラー→有効値で送信成功→リセット)・モバイル375pxでの1カラム化、コンソールエラーなしを確認。SmartLabo repoはpush未実施、CEO確認待ち |
| **v5.1** | 2026-07-12 | Claude Code(CEO追加指示による) | **ブランド露出強化完了。** Project Bible Version 5.5→5.6に更新。コミット`8861c1a`(SmartLabo repo)：「製品名ではなくブランドを印象付ける」というCEO追加指示に基づき、トップページ＋詳細5ページの全6ページへ統一適用。ヘッダーロゴを32px→44px(1.375倍)へ拡大、フッターロゴも28px→38pxへ、ロゴ・ワードマーク間の余白を10px→14pxへ拡張。`index.html`のHero背景へ820px・透明度7%の巨大透かしロゴを追加、詳細5ページの`page-hero`にも420px・透明度6%の控えめな版を追加(いずれもCEO指定の5〜8%の範囲内、モバイルでは非表示、`overflow:hidden`で横スクロールなしを確認)。Hero「AI Control Center」最下部に「Powered by Smart Labo Works」を追加。新規ブランドカラーの追加なし、既存アセットの再利用のみ。ブラウザでロゴサイズ・透かし透明度・Powered by表示・横スクロールなしをJS経由で検証、コンソールエラーなしを確認。SmartLabo repoはpush未実施、CEO確認待ち |
| **v5.2** | 2026-07-13 | Claude Code(CEO指示による) | **Pricing Philosophy「導入プラン」を正式制定。** Project Bible Version 5.6→5.7に更新。新設[12_Pricing_Philosophy.md](12_Pricing_Philosophy.md)：「料金」ではなく「導入プラン」という基本思想のもと、①利用規模(Small/Medium/Enterprise)×②プラン(Lite/Standard/Premium、旧単一軸のBeta/Standard/Enterpriseから改称)×③オプション(7項目、提供状況は`PRODUCT_BOUNDARY.md`準拠)の3軸構造を制定し、ホームページ・Product Book・Company OS・営業資料・見積書・契約書への共通適用を明記。Company OS新機能「Pricing Manager」(利用規模→プラン→オプション選択で料金表/見積書/契約書/営業資料を自動生成)はデータモデル・画面フローを設計のみ記載し、契約書自動生成の法的正確性を要するためコード実装は別Task・CEO承認待ちとして明確に留保。WEBSITE実装(コミット`def70d1`)：`pricing.html`を①利用規模→②プラン→③オプションの3ステップ構成へ全面再構成し、比較表見出しをLite/Standard/Premiumへ改称、全6ページのナビゲーション「料金」を「導入プラン」へ統一。別リポジトリ`smartlabo-works/BUSINESS_STRATEGY.md`(v0.1→v0.2)を更新し、`app.html`内の旧`PLANS`静的データが新構造と不整合である旨・今後の移行要否を明記。ブラウザでデスクトップ(3列/2列グリッド)・モバイル375px(1列折り畳み)・FAQアコーディオン・コンソールエラーなしを確認。SmartLabo repoはpush未実施、CEO確認待ち |
| **v5.3** | 2026-07-13 | Claude Code(CEO承認による) | **pricing.htmlを「Smart Labo Configurator」へ変更、Pricing Philosophyを4層構造へ改訂。** Project Bible Version 5.7→5.8に更新。**実装前に①UIワイヤー②入力項目③データ構造④Company OSとの共通設計を報告し、CEO承認(4層構造への修正指示付き)を得たうえで**実施。コミット`27838a4`(SmartLabo repo、WEBSITE実装)：CEOの修正指示により、③オプション(7〜9項目案)を単純な追加オプション一覧として扱う方針は不採用となり、「①利用規模×②基本プラン×③利用モジュール×④追加オプション」の4層構造が正式決定。Company Brain/CRM/AI Assistant/案件管理/契約管理/AIテンプレートの6中核機能は「③利用モジュール」として選択した②基本プランに標準搭載(選択・変更不可のチェック済み表示)、Meeting/OCR/外部API連携/AI活用研修/Company Brain初期構築支援/オリジナルテンプレート制作/データ移行/導入・運用支援の8項目のみを「④追加オプション」として複数選択可能にした。画面をStep1利用規模→Step2基本プラン→Step3利用モジュール表示→Step4追加オプション→Step5おすすめ構成確認の5Stepへ変更。新規`WEBSITE/js/configurator.js`が`selection={usageScale,plan,modules,options}`(modules/options分離、status語彙`ready/planned/consult`はCompany OS Pricing Managerの設計と共通)を管理し`localStorage`保存。4CTA(無料相談/見積り依頼/資料請求/デモ予約)クリック時に選択内容をURLクエリで`contact.html`へ引き継ぎ、新設の「ご相談種別」欄・相談内容欄へ自動入力する処理を`js/pages.js`に追加。顧客向けUIでは「Company OS」の名称を一切使用していない。見積書/契約書/提案書の自動生成・CRM自動登録・バックエンドAPI接続は今回実装せず(CEO指示どおりスコープ外)。[12_Pricing_Philosophy.md](12_Pricing_Philosophy.md)をv1.0→v2.0に改訂し、4層構造・「中核機能を切り売りしない」という基本思想・`Module`型を追加したデータモデル・5Step画面遷移を正式記載。ブラウザでStep間の連動(プラン変更時の利用モジュール切替)・Step5サマリーのリアルタイム更新・`localStorage`永続化・contact.htmlへの自動入力(拡張子なしURLで検証、`serve`パッケージのクリーンURLリダイレクトによるクエリ消失はローカル開発サーバー固有の事象でGitHub Pages本番には影響しないことを確認)・デスクトップ/モバイル双方のレイアウト・コンソールエラーなしを確認。SmartLabo repoはpush未実施、CEO確認待ち |
| **v5.4** | 2026-07-14 | Claude Code(CEO承認による) | **正式リリース前 最終整備 Step1完了(会社概要ページ新設・会社情報表示統一)。** Project Bible Version 5.8→5.9に更新。コミット`fa5c629`(SmartLabo repo)：**実装前に現在の会社情報と不足項目・修正対象ファイル・問い合わせフォーム現状・推奨送信基盤・セキュリティ対策一覧・法務ページの不足・Step分割・コミット単位・完了条件の9点を報告し、CEO承認(「進めましょう」)を得たうえで**Step1を実施。調査により、PROJECT_BIBLEに代表者/設立年月/所在地/電話番号/メールアドレス/事業内容の正式情報が一切記載されていないこと(確定済みは法人名「株式会社スマートラボ」のみ)、全ページのフッター著作権表記が英語表記「Smart Labo Inc.」で不一致だったこと、ヘッダーnav「会社情報」が`index.html#mission`にリンクしていたが該当要素に`id`がなくリンクが機能していなかったことを発見。新設`company.html`(会社概要ページ)は未確定項目を推測せず「CEO確認待ち」と明示表示。新設`js/company-info.js`が会社情報を1箇所で一元管理する構造とした。全ページのnav「会社情報」を`company.html`へ、フッター著作権表記を正式法人名へ統一し、`#mission`アンカーも修復。フッターに「会社情報」列を新設。5ページにOGP/Twitter Cardタグを新規追加。`privacy.html`/`terms.html`は本文ドラフトをStep2へ持ち越し、リンク切れ防止のための「準備中」暫定ページとして先行設置(noindex設定)。ブラウザで会社概要テーブルの表示・nav遷移・フッター統一・OGP反映・モバイル375pxでの横スクロールなし・コンソールエラーなしを確認。SmartLabo repoはpush未実施、CEO確認待ち |
| **v5.5** | 2026-07-14 | Claude Code(CEO承認による) | **正式リリース前 最終整備 Step2完了(プライバシーポリシー・利用規約ドラフト作成)。** Project Bible Version 5.9→6.0に更新。コミット`aeb495d`(SmartLabo repo)：Step1完了報告後、CEOより「先にステップ2行こう」との指示があり着手。`privacy.html`(10セクション：事業者情報/取得する情報/利用目的/第三者提供/委託/Cookie・アクセス解析/安全管理措置/開示等請求/特定商取引法表記の要否/お問い合わせ)・`terms.html`(8セクション：適用範囲/サービス内容の変更中断/禁止事項/知的財産権/免責事項/個人情報の取り扱い/準拠法・管轄裁判所/規約の変更)を「準備中」の暫定表示から正式ドラフトへ差し替え。両ページ冒頭に「本ページは公開前のドラフトであり専門家確認が必要」との注記を明示し、特定商取引法の該当性・準拠法の管轄裁判所・制定日など会社の正式情報確定待ちの項目は断定せず`[CEO確認待ち]`と表示（Step1と同じ「推測しない」方針を継続）。`contact.html`の個人情報同意チェックボックスを`privacy.html`へリンクし、同意対象を利用者が確認できるようにした（必須バリデーション自体は変更なし）。`noindex`は専門家確認完了まで維持。ブラウザで両ページの表示・同意チェックのリンクとバリデーション・モバイル375pxでの横スクロールなし・コンソールエラーなしを確認。SmartLabo repoはpush未実施、CEO確認待ち |
| **v5.6** | 2026-07-14 | Claude Code(CEO承認による) | **正式リリース前 最終整備 Step3(設計確定)・Step4(バックエンド実装)完了。** Project Bible Version 6.0→6.1に更新。Step3：送信基盤は`smartlabo-works`と独立したVercel Serverless Functionsを推奨しCEO承認を得た（API設計・文字数上限・レート制限閾値・reCAPTCHA v3・CSRF/CORS方針・受付番号形式`SLW-YYYYMMDD-XXXX`を確定、コミットなし）。Step4：コミット`b7783ab`(SmartLabo repo)、`WEBSITE/api/`にバックエンドを新規実装。`contact.js`(Origin検証→honeypot/送信速度トラップ→CSRF検証→レート制限/二重送信検知→reCAPTCHA検証→入力値検証→管理者通知(必須)→自動返信(ベストエフォート)→受付番号返却)・`csrf-token.js`・`_lib/`配下7ファイル(validate/security/rateLimiter/recaptcha/mailer/receiptId/log)。`.env.example`に必要な環境変数を列挙し実値はCEO確認待ち。**リポジトリ外のローカルスクリプトで19件のユニットテスト＋5件のハンドラー統合テストを実施し、CSRFトークンの署名検証において「改ざんトークン末尾のゴミ文字列が16進デコード時に黙って切り捨てられ検証を通過してしまう」脆弱性を発見。署名の長さ・文字種を64桁16進文字列と厳密一致させる修正を行い、修正後は全件成功を確認した。** Vercelアカウント作成・reCAPTCHA登録・通知先メール確定等はCEOアクション待ちのため、実際のメール送信・本番デプロイでの動作確認は未実施であることを明記。SmartLabo repoはpush未実施、CEO確認待ち |
| **v5.7** | 2026-07-14 | Claude Code(CEO判断による) | **問い合わせフォーム送信基盤をVercel/Node.jsからXServer(PHP)へ変更。** Project Bible Version 6.1→6.2に更新。コミット`d0402a8`(SmartLabo repo)：CEOより「現在契約済みのXServerを優先利用する構成へ変更する。ホームページはGitHub Pages＋smartlaboworks.comで公開し、問い合わせフォームはXServer(PHP)を優先する」との判断があり、実装前にXServer構成案・SMTP構成・フォーム送信構成・セキュリティ構成を報告しCEO承認を得たうえで実施。ドメイン構成を`smartlaboworks.com`(apex、GitHub Pages)／`form.smartlaboworks.com`(サブドメイン、XServer)に分離。新設`xserver-form/`はGitHub Pagesの公開対象`WEBSITE/`の外側に配置し誤公開を防止。`contact.php`/`csrf-token.php`がOrigin検証→honeypot/送信速度トラップ→CSRF検証→レート制限(SQLite3永続化)/二重送信検知→reCAPTCHA検証(`recaptcha_enabled=false`の間はスキップ、正式公開直前にCEOが有効化)→入力値検証→管理者通知(必須)/自動返信(ベストエフォート)→受付番号返却の順で処理。メール送信はPHPMailer等を同梱せず`stream_socket_client`による依存ライブラリなしの最小SMTPクライアントを実装し`info@smartlaboworks.com`でのSMTP認証送信を想定。CSRF検証は旧Node.js版で発見した脆弱性(署名の緩い一致判定)を教訓に最初から64桁16進厳密一致で実装。ローカルにPHP 8.3を新規導入し22件のユニットテスト全件成功、PHP組み込みサーバーによる実HTTP統合テストでOrigin拒否・honeypot・送信速度トラップ・CSRF・レート制限(1時間5回)・二重送信検知(10分)・バリデーションエラー・管理者通知メール送信(意図的に到達不能なSMTP宛でエラーとなることを確認)まで一連の流れを確認。旧Vercel/Node.js版(`WEBSITE/api/`等)は削除。XServerサブドメイン追加・DNS設定・SMTPアカウント作成はCEOアクション待ちのため本番動作確認は未実施。SmartLabo repoはpush未実施、CEO確認待ち |
| **v5.8** | 2026-07-14 | Claude Code(CEO承認による) | **正式リリース前 最終整備 Step5完了(問い合わせフォーム実送信対応・メールアドレス構成の正式反映)。** Project Bible Version 6.2→6.3に更新。コミット`fb12868`(SmartLabo repo)：CEOより①`form.smartlaboworks.com`をフォーム専用とする②DNS構成(apex→GitHub Pages、サブドメイン→XServer)③`info@`=代表メール／`contact@smartlaboworks.com`=フォーム送信用推奨構成／`noreply@`=将来追加可能④`config.php`は秘密情報専用でGit管理対象外、の4点承認があり、**実装前に構成図・DNSレコード一覧・SMTP送受信フロー・セキュリティ対策・config.php.sampleの構成を報告しCEO承認を得たうえで**実施。GitHub Pages公式ドキュメントを確認しapex用A レコード4件・`www`用CNAME・`form`サブドメイン用レコードを整理し、`WEBSITE/CNAME`(smartlaboworks.com)を新設。`config.php.example`→`config.php.sample`に改名し、SMTP接続情報・`admin_notify_to`/`mail_from`/`auto_reply_from`等のメールアドレス構成・CSRF署名鍵・reCAPTCHAシークレットのみを保持する構成へ整理。CORS許可オリジン一覧は新設`public/lib/settings.php`(Git管理対象)へ分離し、レート制限DB・ログの保存パスもconfig.phpから除去してコード側の固定相対パスへ変更。`contact.html`にhoneypotフィールド(視覚的に非表示)を追加し、`js/pages.js`で`csrf-token.php`からのトークン取得と`contact.php`への実際の`fetch()`送信を実装、エラーコード別(バリデーション/レート制限/二重送信/CSRF失効/reCAPTCHA失敗/ネットワークエラー等)に分かりやすい日本語メッセージを表示する設計とした。config再編後も23件のユニットテスト全件成功、実HTTP統合テストで`contact@`ベースの新設定でも一連のフローが正しく動作することを確認。ブラウザでhoneypot非表示・クライアント側バリデーション・**`form.smartlaboworks.com`が未デプロイの現状で発生するネットワークエラー時のフォールバック表示(ボタン再有効化含む)が正しく動作すること**・モバイル375pxでの表示・コンソールエラーなしを確認。XServerサブドメイン追加・DNS設定・`contact@`メールアカウント作成・GitHub Pagesカスタムドメイン設定はCEOアクション待ちのため、本番環境でのエンドツーエンド動作確認(実際の送受信・自動返信)は未実施であることを明記。SmartLabo repoはpush未実施、CEO確認待ち |
| **v5.9** | 2026-07-14 | Claude Code(CEO指示による) | **正式リリース前 最終整備 Step6(正式公開前チェックリスト)、ライブ環境不要分を完了。** Project Bible Version 6.3→6.4に更新。コミット`7ae1aa9`(SmartLabo repo)：CEOの「進めて」指示を受け、XServer/DNS/SMTPのライブ環境整備を待たずに実施できるチェック項目を先行実施。全9ページ(index/product/features/real-estate/pricing/contact/company/privacy/terms)についてブラウザからの自動巡回で、HTTP 200応答・内部リンクの解決・スマホ375pxでの横スクロールなし・404ページの正常動作・OGP設定(法務ページ2件は`noindex`のため意図的に未設定)・favicon設定・consoleエラーなしをすべて確認。`git log --all`で`private/config.php`が一度もコミット履歴に含まれていないこと、`.gitignore`がconfig.php/SQLite/ログファイルを実機で正しく除外することを確認し、秘密情報の混入がないことを検証した。`xserver-form/README.md`にバックアップ方針(config.php/SQLite/ログそれぞれの扱い)とロールバック手順(GitHub Pages側は`git revert`による自動再デプロイ、XServer側は再アップロード手順、緊急停止手順)を追記。残るチェック項目(フォーム送信・自動返信・XServer側HTTPS・PageSpeed)はXServerサブドメイン追加等のCEOアクション完了後に実施することを明記。SmartLabo repoはpush未実施、CEO確認待ち |
| **v6.0** | 2026-07-14 | Claude Code(CEO指示による) | **公開前最終フェーズ Step5(問い合わせフォーム完成)完了。** Project Bible Version 6.4→6.5に更新。コミット`2b17f2c`(SmartLabo repo)：CEOより会社設立・XServer契約・`smartlaboworks.com`設定・`info@smartlaboworks.com`作成・`form.smartlaboworks.com`サブドメイン作成(SSL反映待ち)というインフラ整備状況の共有とともに、Task1(問い合わせフォーム完成)〜Task5(本番切替準備)の実装指示があった。`config.php.sample`を`config.php.example`へ改名(前回セッションでの改名指示との経緯を完了報告で開示)。実際に作成済みの`info@smartlaboworks.com`を当面のSMTP認証・管理者通知・自動返信アドレスとする内容へ`config.php.example`/`mailer.php`/`xserver-form/README.md`を更新(`contact@`/`noreply@`への将来切替構造は維持)。`contact.html`/`js/pages.js`に送信完了画面(送信成功時にフォームを非表示にし受付番号とトップページへ戻るボタンを表示)を実装、確認画面は設けず単一ステップ送信を維持。`xserver-form/README.md`にCEO側完了事項を反映し、SSL反映待ちの間は`.htaccess`のHTTPS強制リダイレクトが接続断を招くリスクを明記。設定再編後もローカルPHP環境で23件のユニットテスト全件成功、全PHPファイル構文チェック通過、PHP8.1以降専用構文を使用せずPHP8.0以上で動作する設計であることを確認。ブラウザで送信完了画面の表示切替・クライアント側バリデーション・ネットワークエラー時のフォールバック・モバイル375pxでの表示・コンソールエラーなしを確認。SmartLabo repoはpush未実施、CEO確認待ち |
| **v6.1** | 2026-07-14 | Claude Code(CEO指示による) | **正式リリース前 最終フェーズ Release Candidate 1完了。** Project Bible Version 6.5→6.6に更新。コミット`76a9767`(SmartLabo repo)：CEOよりCEO側インフラ整備完了(会社設立・XServer契約・`smartlaboworks.com`設定・`form.smartlaboworks.com`サブドメイン作成・無料SSL有効化・`info@smartlaboworks.com`作成)の共有とともに、問い合わせフォーム本番対応・reCAPTCHA切替構造・config.php最小化・アップロード対象整理・公開前チェック・SEO・公開準備の指示があった。`config.php.example`を`admin_notify_to`/`mail_from`等の個別キーを廃止し、SMTP接続情報(暗号化方式`smtp_encryption`含む)・`admin_email`・`csrf_secret`・reCAPTCHAキーの必要最低限9項目へ整理(送信元アドレスは認証アカウント`smtp_user`をそのまま使用する設計へ単純化)。`SmtpMailer.php`をSTARTTLS(`tls`)と暗黙のTLS(`ssl`)の両方に対応させ、ローカル環境で両方の接続コードパスを検証。全9ページのcanonical/OGP/Twitter CardのURLを暫定URL(GitHub Pages)から正式ドメイン`https://smartlaboworks.com`へ切替。新設`robots.txt`(sitemap.xml参照)・`sitemap.xml`(index可能な7ページ、noindexの2ページは除外)。`index.html`へ構造化データ(Organization、JSON-LD)を追加し、未確定の会社情報は含めず確定済み項目のみ記載。PageSpeed改善候補(CSS/JS未minify)を所見として記録し今回は未実施と明記。ローカルPHP環境で23件のユニットテスト全件成功、新config構成での実HTTP統合テスト(tls/ssl両方)で一連のフローが正しく動作することを確認。ブラウザで全9ページのSEOメタ情報・robots.txt/sitemap.xmlの200応答・JSON-LDパース成功・404ページ・全内部リンク解決・モバイル375px・consoleエラーなしを確認。SmartLabo repoはpush未実施、CEO確認待ち |
| **v6.2** | 2026-07-14 | Claude Code(CEO指示による) | **Release Candidate 2：本番公開前チェックリスト新設。** Project Bible Version 6.6→6.7に更新。新設[61_Release_Checklist.md](61_Release_Checklist.md)：「Release Candidate 1完了後、Release Candidate 2として本番公開前チェックリストを作成してください」というCEO指示に基づき、SSL/問い合わせ/メール/リンク/Console Error/favicon/OGP/sitemap/robots/SEO/モバイル/PageSpeed/GitHub Release Tag/バージョン番号/更新履歴の15項目からなる、将来のリリースでも再利用可能なチェックリストテンプレートを制定。Release Candidate 1時点の確認状況を反映し、リンク・Console Error・favicon・OGP・sitemap・robots・SEO・モバイルの8項目はローカル環境で確認済み(✅)、SSL・問い合わせ・メール・PageSpeedの4項目は本番ドメイン/SMTP環境が前提のため未実施(⏸)と明示。公開バージョン番号として`v1.0.0`、Gitタグ名として`homepage-v1.0.0`を提案したが、**Gitタグ・GitHub Releaseの作成は対外公開を伴う操作のため実行せず、全項目確認完了後にCEOの明示的な承認を得てから実施する方針を明記した。** 更新履歴として、本リリースサイクル(会社情報/法務/導入プラン/問い合わせフォーム/メール/SEO/公開準備)の主要変更点を一覧化。SmartLabo repoはpush未実施、CEO確認待ち |

*最終更新: 2026-07-14*
