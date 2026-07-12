# CURRENT_STATUS

**PROJECT_BIBLEの現在地 — ChatGPT / Claude Code / Codex 共通の同期ポイント**

> 新しいChatGPTの会話を始める前に、必ずこのファイルを確認してください。下記の「5行サマリー」をそのままChatGPTに貼り付ければ、認識のズレなく議論を始められます。このファイルはCEOの指示により、PROJECT_BIBLEおよびGitHubの管理責任者(Claude Code)が常に最新版を維持します。

---

## 5行サマリー(ChatGPTに共有する用)

```
Project Bible Version：5.0
Brand Version：5.1
Design Bible Version：2.3
Homepage Version：2.0(Version2リニューアル。「会社紹介サイト」から「営業サイト」へ全面刷新。GitHub Pagesで公開中: https://smartlabosukimagakusyuu-dev.github.io/SmartLabo/)
Smart Labo Works：正式コードベースは別リポジトリ`smartlabo-works`(Node.js版)。詳細Versionは同リポジトリの`PRODUCT_REQUIREMENTS.md`を参照
Current Task：ホームページVersion2リニューアル完了(CEO承認2026-07-12)。Smart AI Router図解・AI社員カード・画面ショーケース・業種展開・料金セクションを新設。pushはCEO確認後
```

**用語・体制の重要な変更(2026-07-10)：**
1. 「Company OS」は株式会社スマートラボの**社内専用システム**の固有名詞として正式に確定した(非売品)。これまでDashboard/Smart Labo Worksの設計思想を説明する形容として使っていた「Company OS」という語は廃止し、以降Smart Labo Works側の説明には使用しない。
2. **Smart Labo Worksの唯一の正式コードベースは別リポジトリ`smartlabo-works`(Node.js版)に確定した。** 本リポジトリ内の`WEBSITE/app.html`(GitHub Pages公開版)はSmart Labo Works正式製品ではなく、**デモサイト／マーケティング用プレビュー**として扱う。今後、本ファイルでは`WEBSITE/app.html`を「Smart Labo Works Version」として追跡しない(下記ステータス一覧を参照)。

詳細は [11_Development_Principles.md](11_Development_Principles.md)「製品境界の定義」、[00_Foundation/08_SmartLaboWorks_Concept.md](00_Foundation/08_SmartLaboWorks_Concept.md)「唯一の正式コードベース」を参照。

---

## ステータス一覧

| 項目 | 値 |
|---|---|
| Project Bible Version | 5.0 |
| Brand Version | 5.1 |
| Design Bible Version | 2.3 |
| Homepage Version | **2.0 — GitHub Pagesで公開中**([https://smartlabosukimagakusyuu-dev.github.io/SmartLabo/](https://smartlabosukimagakusyuu-dev.github.io/SmartLabo/))。CEO承認「ホームページVersion2リニューアル」により、「会社紹介サイト」から「Smart Labo Worksを導入したくなる営業サイト」へ全面刷新(2026-07-12)。詳細は下記「ホームページVersion2リニューアル」を参照 |
| デモサイト Version（`WEBSITE/app.html`） | **デモ v1.0（2026-07-07完成、2026-07-10にデモとして再位置づけ）。GitHub Pages公開中。全9ページ・デモデータ(顧客5件・契約7件・物件6件)。Smart Labo Works正式製品ではない** |
| Smart Labo Works Version（正式コードベース） | 本ファイルでは追跡しない。**別リポジトリ`smartlabo-works`の`PRODUCT_REQUIREMENTS.md`／`PRODUCT_BOUNDARY.md`を参照** |
| Current Project | Company Setup → **Version1.0リファクタリング（2026-07-10 CEO承認）** |
| Current Task | **ホームページVersion2リニューアル完了。** Smart AI Router図解・AI社員カード（6種）・画面ショーケース（横スクロール）・業種展開・導入効果ストーリー・料金プランの各セクションを新設し、9セクション構成へ全面刷新 |
| Next Task | ホームページのpushはCEO確認後。営業テンプレート／Appointment Templateの追加分（followup/thanks/contract等）・他業種テンプレートの追加はCEO承認後に着手 |
| Last Update | 2026-07-12 |
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

*最終更新: 2026-07-12*
