# P32-3 Phase 1E — 本番公開前・最終監査（PRODUCTION RELEASE PRE-FLIGHT）

工程: **P32-3 Phase 1E（READ-ONLY監査。コード変更0・master変更0・deploy 0）**
実施日: 2026-08-23
対象: `feature/website-renewal` HEAD `3adcb3f`（1D-R1 代表PASS版）
方針: 問題は修正せず分類して報告。本書（結果doc）のみ feature branch へ commit。

---

## 1. PRE-CHECK

| 確認 | 期待 | 実測 | 判定 |
|---|---|---|---|
| branch | feature/website-renewal | 同左 | ○ |
| HEAD | 3adcb3f | 3adcb3f584e85a617899f51ba56e0f3cc621844e | ○ |
| origin/feature/website-renewal | 3adcb3f | 同左 | ○ |
| master | d3c6054 | d3c6054c99a0df7d15d6fccec08ab18495d8d463 | ○ |
| status | clean | 0行 | ○ |

## 2. feature全体差分（master..feature/website-renewal）

7 commits（9a70af2 → 3adcb3f）／17 files（+4,312 / −1,257）

| 区分 | ファイル | 状態 |
|---|---|---|
| HTML | `WEBSITE/index.html` | **変更（会社TOP刷新）** |
| CSS | `WEBSITE/assets/css/renewal.css` | **新規**（TOP専用・sl-prefix）。tokens/base/components は未変更 |
| JS | — | **変更0**（main.js / contact-form.js / apply-form.js 不変） |
| 画像 | `WEBSITE/assets/images/salon/*.webp` ×6 | **新規**（1C-2Bと同一SHA-256を確認） |
| フォーム／API | contact.html / apply.html / contact-api / signup-api | **変更0** |
| SEO／構造化データ | index.html の head・JSON-LD | **変更0**（Phase 1Aで維持。本featureで不変） |
| analytics | — | 変更0（タグなしのまま） |
| 他ページ | features / pricing / apply / company / contact / company-brain / privacy / terms / product / real-estate / 404 / robots / sitemap / CNAME | **変更0** |
| docs | `docs/website/*.md` ×8 | 新規（監査・仕様・結果） |
| PROJECT_BIBLE | `CURRENT_STATUS.md` | P32-2／1A の状態追記のみ |

不要ファイル／debugコード／テストデータ／一時ファイル: **なし**（`git status --ignored WEBSITE` 0件）。
`console.log`・`debugger`・`TODO`: WEBSITE配下 **0**。
`localhost` / `127.0.0.1`: `contact-form.js` `apply-form.js` の**ローカル確認ガード**（本番では無効化される既存設計）のみ。内部パス（`C:\Users` 等）: **0**。

## 3. 商品・料金・契約条件監査（サイト上の現在値 vs SSOT）

| ページ | 掲載 | SSOT | 整合 |
|---|---|---|---|
| index.html | Smart Labo Salon **月額14,800円（税別）／初期費用0円／1店舗単位／最低契約期間6か月**（＋meta descriptionに同値） | SALES_TERMS §2・3・6／PRICE_PLAN §1／PHASE0 §37.4 | **○** |
| index.html | 店舗HP制作 **50,000円〜（税別）**／管理・保守 **月額5,000円〜（税別）** | WEBSITE_PRODUCTION_AND_MAINTENANCE_PRICE_V1／PHASE0 §37.1 | **○** |
| index.html | Standard 19,800／Pro／先着20店舗／24か月保証／スタッフ人数 | **未掲載**（PHASE0 §37.4） | **○** |
| pricing.html / apply.html | Works 初期設定費10,000円／月額基本料金20,000円／追加アカウント3,000円（税別）・最低利用期間なし・日割なし | PHASE0 §15（PROJECT_BIBLE 14_Sales_And_Billing_Policy と一致・本featureで不変） | **○** |
| company-brain.html | 「月額2万円」誤認防止の注記（価格の再掲なし） | 同上 | ○ |
| company.html | 資本金 1,000,000円 | 会社情報 | ○ |

**矛盾・不明点: 0。**

## 4. 機能表現監査（TOPでSalonについて約束している機能）

| 表現 | 実態（SALES_TERMS §11） | 判定 |
|---|---|---|
| 店舗ページの制作・管理 | 提供済み | ○ |
| 24時間WEB予約受付／店舗側の予約へそのまま反映 | 提供済み（公開ページ予約＋店舗側予約） | ○ |
| 来店（受付）・顧客管理 | 提供済み | ○ |
| 30秒メモ（スマホ入力） | 提供済み | ○ |
| AI整理 → スタッフ確認（外した項目は登録されない） | 提供済み（pending→confirm） | ○ |
| 次回来店の接客準備（前回のポイント／会話／提案候補） | 提供済み | ○ |
| 再来店（循環図・「再来店候補」の件数表示は記述なし） | 提供済み範囲内 | ○ |
| AI投稿案・AIキャンペーン案（料金節の含まれる内容） | 提供済み | ○ |
| 投稿・キャンペーンの文章作成支援／配信は店舗が行う | 提供済み・未提供の区別どおり | ○ |
| 独自ドメインHP制作は別サービス（HP制作節） | SALES_TERMS §13 | ○ |

禁止表現（全12ページ・コメント／script除外）: 「24時間AI／AIが予約／使い放題／LINE自動配信／Hot Pepper／売上AI／離脱予測／完全オリジナル／AIが自動保存／完全自動／法務確認／絶対に安全／一切送／30日後／必ず30秒／Company OS／音声入力／口コミ／スタッフ個別／削減／売上が／効果が／実績」→ **TOP 0件**。
他ページの該当2件は否定文（apply.html「AIが自動で契約を確定させることはありません」／features.html「AIが顧客情報を書き換えることはありません」）で問題なし。

## 5. CTA全件監査

全12ページ・リンク327件・`.btn` 55件を機械抽出し、ローカル配信（127.0.0.1:4789）で到達確認。

| CTA（TOP） | href | 到達 |
|---|---|---|
| Salonについて相談する（Header／Hero／接客準備／料金／最終・計5） | contact.html?type=demo#form | ○（#form あり・type=demo 事前選択を確認） |
| 店舗ホームページ制作を相談する（Hero／HP制作／最終） | contact.html?type=consult#form | ○ |
| 無料相談する（Header） | contact.html?type=consult#form | ○ |
| Smart Labo Worksを見る（Hero link／Works／最終 link） | features.html | ○ |
| 料金を見る | pricing.html | ○ |
| AI活用について相談する | contact.html?type=consult#form | ○ |
| 会社情報を見る／お問い合わせ | company.html／contact.html | ○ |
| Footer（Salon／HP制作／相談／Works／料金／申込／Company Brain／会社情報／問い合わせ／資料請求／privacy／terms） | 各ページ | ○ |

**404: 0。外部リンク: 0。**
**アンカー欠落: 9件 — 全て同一原因**: 他ページ（404／apply／company-brain／company／contact／features／pricing／privacy／terms）のフッター「Company Brain」→ `index.html#brain`。**旧TOP（master）には `id="brain"` の節があったが、新TOPには無い**（Phase 1Aで落ち、1D/1D-R1でも未復旧）。クリックするとTOPの最上部へ着地する（404ではない）。→ §16 P1。

## 6. フォーム安全監査（READ-ONLY・送信0）

| 項目 | 結果 |
|---|---|
| GET表示 | contact.html 全6viewportで console error 0・失敗request 0 |
| 事前選択 | `?type=demo` で種別「デモ依頼」が選択される（JS・存在しない値は無視） |
| 入力・validation | 空→`checkValidity()=false`／不正メール→false（ブラウザ標準メッセージ）／正しい入力＋同意→true。**POST発生0** |
| 送信先 | `action`/`data-endpoint` = `https://form.smartlaboworks.com/contact.php`（method=post）、CSRFトークン `csrf-token.php`（GET）。localhostではトークン取得を行わない既存設計 |
| サーバー側（contact-api・既存） | Origin/Referer許可一覧、メソッド制限、honeypot、時間差、CSRFトークン（設定により必須化）を実装。本featureで変更0 |
| 秘密設定 | `contact-api/private/contact-config.php` は **.gitignore 対象・未追跡・履歴なし**（リポジトリに秘密値なし）。雛形 `*.example.php` のみ追跡。★監査中の端末出力に設定値が1度表示されたため、**予防的に csrf_secret のローテーションを推奨**（P2・任意。本書・commitには値を含めない） |

## 7. SEO監査（全12ページ）

| 項目 | 結果 |
|---|---|
| title／description | TOP: 「株式会社スマートラボ｜AIで店舗・企業の仕事を楽に」＋Salon中心の説明 ○。他ページは Works 製品ページとして「〜｜Smart Labo Works」 ○。privacy/terms は「— Smart Labo Works」（§16 P2） |
| canonical | 全ページ設定（product/real-estate は移転先へ canonical） ○ |
| robots | 404.html のみ noindex ○。robots.txt Allow / sitemap 参照 ○ |
| sitemap.xml | 9 URL（TOP・features・company-brain・pricing・apply・company・contact・privacy・terms）。product/real-estate（移転ページ）は非掲載 ○ |
| OGP | TOPは og:* 完備。**og:image は Works時代の画像**（「Smart Labo Works 会社を動かすAI。」の1200×630）— og:title/og:image:alt（Salon中心・「AIで、仕事を楽に。…」）と**内容が一致しない**（§16 P1）。他ページは og:image 未設定（§16 P3） |
| favicon／apple-touch-icon | 設定あり ○ |
| heading | 全ページ h1=1。TOPの h2/h3 階層 ○ |
| alt | 全ページ alt欠落 0 ○ |
| 内部リンク | 404 0／アンカー欠落 = `#brain` のみ（§5） |
| 旧Works専業時代のmeta残存 | TOPは更新済み。Works各ページのmetaは製品ページとして妥当。privacy/terms の「Smart Labo Worksのホームページ」表記は §16 P2 |

## 8. 構造化データ監査

| ページ | 型 | 内容 | 判定 |
|---|---|---|---|
| index.html | Organization／WebSite | `name: "Smart Labo Works"`・`legalName: "株式会社スマートラボ"`・url・logo（logo-primary.png 存在） | 誤情報なし。ただし**会社TOP（Salon中心）の Organization/WebSite name が "Smart Labo Works"**（旧来の表記・本featureで不変）→ §16 P2 |
| 他8ページ | BreadcrumbList | ホーム＋各ページ | ○（旧価格・旧商品・存在しないサービスなし） |

## 9. Analytics監査

- WEBSITE 全ページで GTM／gtag／GA 参照 **0**（WEB-SALES-8L で撤去済み・PHASE0 §37.1 で再導入しない方針）
- privacy.html「Cookieを使用していません／Google Analytics等のアクセス解析ツール…使用していない」と**整合**
- ローカル確認時の外部通信: **0**（72 page×viewport の巡回で BASE 以外へのリクエストなし）
- 二重設置なし／`generate_lead` 等のイベント設計は現サイトに存在しない（撤去済み）→ 壊していない

## 10. Security / Privacy監査

| 項目 | 結果 |
|---|---|
| API key／password／token／PIN／.env（WEBSITE配下） | 0 |
| 実在顧客情報・個人情報 | 0（Salon画像は架空データ SALON SOLEIL のみ。1C-2B QUALITY GATE と同一ファイル＝SHA-256一致） |
| 「※掲載画面はデモ用の架空データです。」 | Hero／30秒メモ／AI整理／接客準備／店舗ページ・WEB予約 の5箇所に存在（memo-phone近傍含む） |
| localhost依存 | JSのローカルガードのみ（本番では無効） |
| 内部ファイルパス | 0 |
| 秘密設定ファイル | 未追跡・gitignore（§6） |

## 11. Performance監査（TOP・1440・ローカル）

| 項目 | 値 |
|---|---|
| 初期転送（lazy画像除く） | 約263KB |
| Salon画像6枚合計 | 315KB（WebP q82・全て width/height 属性あり） |
| LCP | 約0.5s（ローカル）／CLS **0**／DCL 約0.45s |
| loading | Hero 2枚 eager（briefing は fetchpriority=high）、他 lazy |
| width/height 未指定 | ヘッダー／フッターのロゴ icon-color-64.webp（2.6KB・CSSで高さ固定・既存） |
| 不要な巨大画像 | TOPからの参照なし。リポジトリ内の未参照PNG（icon-color.png 353KB／app-icon-1024.png 323KB／favicon-512.png／logo-white.png）は配信対象だが参照されない（§16 P3） |
| Lighthouse | 未実施（ツール未導入）。上記実測で重大問題なし |

## 12. Responsive最終確認（12ページ × 1440/1280/1024/768/390/375 = 72）

| 結果 | 件数 |
|---|---|
| 横スクロール | **1件**: `apply.html` 375px（scrollWidth 386 / 11px超過・フォーム必須注記〜入力欄）。**master と同一＝本番で既に同じ挙動**。本featureで未変更 → §16 P2 |
| TOP（index.html） | 全6幅で横スクロール0・文字切れ0・画像崩れ0・CTA切れ0・sticky header干渉0（1D-R1で確認済み） |
| その他11ページ | 横スクロール0 |

## 13. Browser / Console（72巡回）

console error/warning **0**／404 **0**／failed request **0**／外部通信 **0**。

## 14. 法務表示監査

| 項目 | 結果 | 分類 |
|---|---|---|
| 会社情報（company.html） | 会社名 株式会社スマートラボ／代表取締役 小川 昌利／資本金／事業内容 あり。**所在地・電話は意図的に未掲載**（コメントで明記） | 情報（SALES_TERMS の【代表確認事項】本店所在地と関係。公開阻害ではない） |
| プライバシーポリシー | 制定日 2026-08-11・会社名一致・Cookie/解析「使用していない」と実態一致。適用範囲の文言が「Smart Labo Worksのホームページ」 | **公開後対応可能**（P2・法務文言は本工程で変更しない） |
| 利用規約 | 会社名一致・フォーム利用条件 | ○ |
| 問い合わせ導線 | 全ページから contact.html へ到達 | ○ |

## 15. 営業最終チェック（初見の美容室オーナー）

| 時間 | 理解できること |
|---|---|
| 5秒 | 「AIで、仕事を楽に。」＋「美容室からはじまる、接客業のAI支援」＋接客準備の実画面 → **美容室向けAIの会社** |
| 15秒 | 3ステップ（短く残す→AI整理・人が確認→接客準備）＋ストーリー8工程 → **Salonが何をするか** |
| 30秒 | AI整理比較（外した項目は登録されない）＋濃紺の接客準備 → **他の顧客カルテとの違い** |
| 60秒 | 店舗ページ／WEB予約の入口＋料金（14,800円税別・0円・6か月）＋「実際の画面を見ながら」 → **いくらで、どう相談するか** |
| 3分 | HP制作（別サービス・50,000円〜）／Works（法人）／導入の流れ／会社／最終CTA → 会社全体像と相談の入口 |

「AIだからすごい」ではなく「短く残す→AIが整理→人が確認→次回来店前に使える」の順で理解できる。

## 16. RELEASE GATE

| 優先 | # | 内容 | 由来 | 対応案 |
|---|---|---|---|---|
| **P1** | 1 | 他9ページのフッター「Company Brain」→ `index.html#brain` の受け皿が新TOPに無い（最上部へ着地） | **本feature（Phase 1A）で発生した回帰** | TOPの Works 節直前に `<span id="brain" class="visually-hidden" aria-hidden="true"></span>` を追加（既存の #capabilities 等と同じ型・1行）。代表承認後に実施 |
| **P1** | 2 | TOPの og:image が Works時代の画像（「Smart Labo Works 会社を動かすAI。」）で、Salon中心の og:title／alt・本文と不一致。Instagram・DM流入時の共有カードが別商品に見える | 既存資産（本featureで不変）だが、TOPの主役が変わったことで顕在化 | 新OGP（1200×630・TOPコピー）の制作と差し替え。※「公開後に差し替え」を代表が選ぶなら P2 へ降格可 |
| P2 | 3 | index.html JSON-LD の Organization/WebSite `name` が "Smart Labo Works"（legalName は正） | 既存 | "Smart Labo"／"株式会社スマートラボ" へ更新（公開後可） |
| P2 | 4 | privacy.html／terms.html の title・適用範囲が「Smart Labo Works のホームページ」表記 | 既存 | 法務文言のため代表・法務判断で更新（公開後可） |
| P2 | 5 | apply.html 375px で横スクロール 11px | 既存（本番でも同じ） | フォーム注記／入力欄の幅調整（公開後可） |
| P2 | 6 | csrf_secret の予防的ローテーション（値は未流出・監査端末に表示のみ） | 運用 | contact-api 側で設定値更新（任意） |
| P3 | 7 | 非TOPページの og:image 未設定 | 既存 | 任意 |
| P3 | 8 | 未参照の大きなPNG（icon-color.png 等）がリポジトリ内に残る | 既存 | 任意（配信対象だが参照なし） |
| 情報 | 9 | company.html に所在地・電話なし（意図的） | 既存 | 代表判断（SALES_TERMS【代表確認事項】） |

**判定: P0 = 0／P1 = 2 → RELEASE HOLD。**
P1-1 は1行の回帰修正、P1-2 はOGP画像の差し替え（または代表判断でP2降格）。この2点が片付けば **RELEASE CANDIDATE**。

## 17. 代表判断が必要な項目
1. P1-1 `#brain` 受け皿の追加（1行・TOPのみ）を承認するか
2. P1-2 OGP画像を公開前に差し替えるか、公開後対応（P2）とするか。差し替える場合、TOPのコピー「AIで、仕事を楽に。顧客との関係を、もっと強く。」で新規制作してよいか
3. P2-3／P2-4（JSON-LD name・privacy/terms表記）を公開前に含めるか
4. company.html の所在地未掲載を維持するか

## 18. git／本番
- コード変更 0／commit: 本書のみ（feature/website-renewal）／push: origin/feature/website-renewal／master 変更0／deploy 0／問い合わせ送信0／外部通信0

## 19. 変更履歴
| 版 | 日付 | 内容 |
|---|---|---|
| v1.0 | 2026-08-23 | 制定（P32-3 Phase 1E・READ-ONLY監査。RELEASE HOLD：P1×2（#brain回帰／OGP不一致）） |
