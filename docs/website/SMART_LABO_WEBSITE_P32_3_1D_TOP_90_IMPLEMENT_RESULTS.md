# P32-3 Phase 1D — 会社TOP 90点化 実装結果（実画面6枚を使った営業サイト化）

工程: **P32-3 Phase 1D（ローカル実装・feature branch まで。本番反映なし）**
実施日: 2026-08-23
対象ブランチ: `feature/website-renewal`
前工程: `SMART_LABO_WEBSITE_P32_3_1C2B_ASSET_FINAL_RESULTS.md`（正式6枚 A判定・配置済み）
根拠: 1C-1 撮影仕様（§3 のHP使用場所＝Phase 1B設計の対応表）／Salon営業SSOT（`SALON_SALES_TERMS_SUMMARY_V1.md`・`SALON_PRICE_PLAN_v1.md`・`SALON_FAQ_1.md` 現行条件表）

> ★変更したのは **`WEBSITE/index.html` と `WEBSITE/assets/css/renewal.css` の2ファイルのみ**。
> 共通CSS（tokens / base / components）・JS・他ページ・contact-api・本番・master は変更0。
> ★ローカル確認URL: `http://127.0.0.1:4789/index.html`（本セッションの静的配信。127.0.0.1のみ・外部公開なし）
> 　代表PCで再現する場合: `cd C:\Users\user\Desktop\SmartLabo\WEBSITE` → `python -m http.server 4789 --bind 127.0.0.1`

---

## 1. PRE-CHECK

| 確認 | 期待 | 実測 | 判定 |
|---|---|---|---|
| branch | feature/website-renewal | 同左 | ○ |
| HEAD | 865b27acef02795d8974eb1b9fe781a4bf3b0f84 | 同左 | ○ |
| origin/feature/website-renewal | 865b27a… | 同左 | ○ |
| master | d3c6054c99a0df7d15d6fccec08ab18495d8d463 | 同左 | ○ |
| status | clean | 0行 | ○ |

再読: 1C-1仕様／1C-2A／1C-2B／index.html／assets/css/renewal.css／main.js／営業条件早見表／価格SSOT／FAQ現行条件表／RENEWAL_SPEC §7・12・13・17・21／PHASE0 §14・15・37。
★「Phase 1B 90点化設計結果」は独立docとしてrepoに存在しない（1C-1仕様 §3「用途（HP上の使用場所）」の対応表と本工程の指示書が設計内容）。

## 2. before / after HEAD

- before: `865b27a`（1C-2B）
- after: 本書を含むcommit（§31）

## 3. 変更ファイル

| ファイル | 変更 |
|---|---|
| `WEBSITE/index.html` | 557行 → 691行（本文構成を再編。head／SEO meta／構造化データ／header／footer は不変） |
| `WEBSITE/assets/css/renewal.css` | 392行 → 685行（TOP専用。旧 `.sl-ui*` モック用CSSを撤去し、実画面枠・比較・循環・料金等を追加） |
| `docs/website/SMART_LABO_WEBSITE_P32_3_1D_TOP_90_IMPLEMENT_RESULTS.md` | 本書（新規） |
| tokens.css / base.css / components.css / main.js / 他ページ | **0** |

`!important` は `prefers-reduced-motion` 内の既存2件のみ。固定heightは装飾（ドット・アイコン）のみで、レイアウトの固定heightなし。

## 4. TOP構成 before / after

| # | Phase 1A（before・14節） | Phase 1D（after・16節） |
|---|---|---|
| 01 | Header | Header（不変） |
| 02 | Hero（HTML/CSSモック） | Hero（**実画面：接客準備＋30秒メモ**） |
| 03 | 価値3点（白カード3枚） | 3ステップ（01/02/03・矢印つなぎ） |
| 04 | Salon紹介（6ピル） | Salon全体ストーリー（8ピル＋↺・AIバッジ） |
| 05 | よくある課題（5項目＋LINE注記） | 課題提起（4項目・中央寄せ・BAへの橋渡し文） |
| 06 | 導入前後（2カード） | BEFORE/AFTER（2カード＋中央矢印・指定文言） |
| 07 | スマホ30秒入力（文章のみ） | 30秒メモ（**実画面**・2カラム） |
| 08 | HP制作 | **AI整理→スタッフ確認（実画面2枚の比較）** |
| 09 | 循環7箱 | **接客準備（山場・濃色・実画面大）** |
| 10 | Works（navy） | **店舗ページ／WEB予約（実画面2枚・スマホ枠）** |
| 11 | AI導入・業務自動化 | 集客から再来店まで（循環7箱＋↺戻り帯） |
| 12 | 思想 | **Salon料金／CTA（14,800円税別・0円・1店舗・6か月）** |
| 13 | 導入の流れ | 店舗HP制作（縮小） |
| 14 | 会社 | Works（短縮）＋AI導入相談（脇カード） |
| 15 | 最終CTA | 思想＋導入の流れ（1節に統合） |
| 16 | — | 会社 |
| 17 | — | 最終CTA |

旧アンカー受け皿（#capabilities #features #screens #pricing #faq）は維持。`#salon` `#website` `#works` `#steps` も維持。

## 5. Hero

- メインコピー「AIで、仕事を楽に。顧客との関係を、もっと強く。」維持。サブコピーは既存文＋「いまは美容室向けの Smart Labo Salon を中心に…」を1文追加
- 右側: 抽象モック（本日のお客様）を撤去 → **接客準備の実画面**（窓枠・「実際の画面」チップ・下に3ラベル）＋ **30秒メモのスマホ枠**を窓の縁30pxだけ重ねて配置。列幅に関係なく「前回の会話」の行末を隠さない（1440／1280／1024で確認）
- ≤640px ではスマホ枠を非表示（接客準備のみ。30秒メモは§07で大きく見せる）
- CTA: 主「Salonについて相談する」／副「店舗ホームページ制作を相談する」／Worksはテキストリンク
- 「※掲載画面はデモ用の架空データです。」をHero内（memo-phone近傍）に表示

## 6. 3ステップ

01 短く残す／02 AIが整理、スタッフが確認／03 次回来店の接客準備。白カード3枚 → 大番号＋縦罫＋矢印の軽い構成。文章量を減らした。「AI顧客カルテ」の語は使わない。

## 7. Salon全体ストーリー

店舗ページ → WEB予約 → 来店 → 接客メモ → AI整理 → スタッフ確認 → 接客準備 → 再来店 ↺（8ピル・AI工程にバッジ・末尾に↺）。CSSのみ。PC横・スマホ縦折返し。

## 8. 課題提起（Problem）

見出し維持。4項目カード（2列）。UIは出さない。BAへの橋渡し文を追加。LINE/Instagram項目と「配信は行いません」注記はここから外した（循環節に移動）。

## 9. BEFORE / AFTER

BEFORE 4項目／AFTER 5項目を指示どおりに置換。数値効果なし。「効果を数値で約束するものではありません」維持。PC中央に矢印、スマホは縦。

## 10. 30秒メモ

見出し「接客後、その場で短く残す。」左コピー／右 `salon-ui-memo-phone.webp`（スマホ枠・表示382px ≦520）。箇条書き3。「必ず30秒」等の保証表現なし。架空注記あり。旧「スタッフ人数で月額が増えない」注記はTOPから削除（指示§17）。

## 11. AI整理 → スタッフ確認

見出し「AIが整理する。残す内容は、人が決める。」`ai-review` と `ai-review-unchecked` を **左右同寸で並置**（PC 526px／680上限内・1024で438px）＋中央矢印＋各画像下に説明。下段に4ポイント（整理／比較／確認／登録）。JSなし。「AIが自動保存」「書き換える」は不使用。

## 12. 接客準備（山場）

背景 = Navy→Deep Blue のグラデーション（`--c-navy` #0A1B3D → #153E75・白文字）。見出し「次の来店前に、思い出すところから始めない。」左に3ブロックの白ピル＋説明・CTA、右に `salon-ui-briefing.webp`（発光影の窓枠・538px ≦620上限）。画像に矢印・赤枠の焼き込みなし。

## 13. 店舗ページ / WEB予約

見出し「予約の入口から、ひとつにつながる。」eyebrow「店舗ページ ・ 24時間WEB予約受付」。`public-shop`／`public-booking` をスマホ枠2台（各302px ≦520・右を44px段差）。下にピル3（店舗ページの制作・管理／24時間WEB予約受付／店舗側の予約へそのまま反映）。「AIが予約」なし。

## 14. 循環フロー

7箱（01〜07・AI工程は淡緑）＋「↺ 再来店したお客様の記録が、次の接客準備へつながります」帯。PC横7列→1080で4列→640で2列→380で1列。画像は増やしていない。

## 15. Salon料金 / CTA

Smart Labo Salon ／ 月額14,800円（税別）／初期費用0円／1店舗単位／最低契約期間6か月／含まれる内容1段落（営業条件早見表§11の範囲）／CTA「Salonについて相談する」→ `contact.html?type=demo#form`／補足「実際の画面を見ながらご説明します。」
★未掲載（指示どおり）: 先着20店舗・24か月価格保証・スタッフ人数無料・Standard/Pro。

## 16. HP制作

見出し「新しいお客様との入口も、つくる。」縮小版2カード（50,000円〜税別／月額5,000円〜税別・SSOT一致）。箇条書きを圧縮（7→4／4→3）。CTAは secondary に格下げ。

## 17. Works

navy全幅 → 白背景の短い節。「Smart Labo Worksを見る」＋「料金を見る」。旧「AI導入・業務自動化」節を脇カードに統合（Company OS名称なし）。

## 18. final CTA

文言維持。「3分デモは準備中」→「実際の画面をご覧いただきながらご説明します。」に置換（未完成物の告知を消した）。

## 19. 使用画像（6枚・すべて `assets/images/salon/`）

| 画像 | 使用箇所 | PC表示幅（1440） | 上限 |
|---|---|---|---|
| salon-ui-briefing.webp | Hero／接客準備 | 450／538 | 620〜680 |
| salon-ui-memo-phone.webp | Hero（168）／30秒メモ | 382 | 520 |
| salon-ui-ai-review.webp | AI整理 | 526 | 680 |
| salon-ui-ai-review-unchecked.webp | AI整理 | 526 | 680 |
| salon-public-shop.webp | 店舗ページ/WEB予約 | 302 | 520 |
| salon-public-booking.webp | 店舗ページ/WEB予約 | 302 | 520 |

すべて `width/height` 属性付き。Hero 2枚は eager（briefing は `fetchpriority="high"`）、他は `loading="lazy"`。追加撮影0。`object-fit` 未使用（縦横比そのまま）。

## 20. alt

1C-2B 確定文言をそのまま使用（6種・人物名/店名なし・SEO語追加なし）。`document.images` で alt 欠落 0 を確認。

## 21. 架空データ注記

「※掲載画面はデモ用の架空データです。」を Hero／30秒メモ／AI整理／接客準備／店舗ページ・WEB予約 の5箇所（13px）。memo-phone 近傍（Hero・§07）は必須条件を満たす。

## 22. PC確認（127.0.0.1:4789・Chrome 149 headless）

| viewport | 横スクロール | console error/warn | 失敗request | CTAはみ出し | nav |
|---|---|---|---|---|---|
| 1440 | なし（scrollW=1440） | 0 | 0 | 0 | 横並び |
| 1280 | なし | 0 | 0 | 0 | 横並び |
| 1024 | なし | 0 | 0 | 0 | ドロワー（共通CSSの1080閾値） |
| 768 | なし | 0 | 0 | 0 | ドロワー |

ページ高: 1440で約10,900px。

## 23. 375px確認

| 項目 | 結果 |
|---|---|
| 横スクロール | なし（scrollW=375。共通ヘッダーのハンバーガーSVG pathのbboxが+5pxだが描画外・不変） |
| 390px | 同上・問題なし |
| ヘッダーCTA | 「無料相談する」「相談する」とも44px高・画面内 |
| ドロワー | トグルで `hidden=false / aria-expanded=true` → 5リンク表示 → 再押下で閉じる |
| 実画面 | briefing 341 / memo 325 / review 341×2 / shop・booking 302 |
| 順番 | 各節とも 説明 → 画像（Heroはコピー→画像） |
| 循環フロー | 2列→380で1列。崩れなし |
| ページ高 | 約16,700px |

補足: 375pxではAI整理の2枚が341px（原寸UI比54%）になり、画像内の小さい文字は読みづらい。画像下の説明文と4ポイントで補っている（§30 P1）。

## 24. accessibility

h1=1。h2は各節1つ・h3は節内。`aria-labelledby` 全節。装飾（窓バー・矢印・番号）は `aria-hidden`。3ステップ/流れ/循環は `ol`＋`aria-label`。フォーカス可能要素は既存の `.btn`/リンクのみ（既存フォーカススタイルを継承）。`prefers-reduced-motion` 尊重（既存）。スキップリンク維持。

## 25. console / network

全viewportで console error 0・warning 0、4xx/5xx 0、requestfailed 0。全asset 200（24参照）。

## 26. 禁止表現監査

レンダリング本文（HTMLコメント除外）に対し、指示§27の全語＋「削減／売上が／効果が／実績／％」を検索 → **0件**。
※ソース冒頭のHTMLコメント（「未提供機能（LINE自動配信・音声入力…）は掲載しない」という注意書き）には語が含まれるが、表示されない。

## 27. SSOT価格整合

| 掲載 | SSOT | 一致 |
|---|---|---|
| Salon 月額14,800円（税別）／初期費用0円／最低契約期間6か月／1店舗単位 | SALES_TERMS §2・3・6／PRICE_PLAN §1／PHASE0 §37.4 | ○ |
| HP制作 50,000円〜（税別）／管理・保守 月額5,000円〜（税別） | WEBSITE_PRODUCTION_AND_MAINTENANCE_PRICE_V1／PHASE0 §37.1 | ○ |
| Standard 19,800／Pro／先着20店舗／24か月保証 | 掲載しない（PHASE0 §37.4・本指示§17） | ○ |

## 28. SEO非回帰

title／meta description／canonical／OG／twitter／JSON-LD は1バイトも変更なし。h1=1。節見出し階層は正しい。旧アンカーID維持。sitemap/robots 不変。

## 29. self review（Phase 1A 75点に対して）

| 項目 | 点 | 根拠 |
|---|---|---|
| Design | 88 | 背景リズム（pale→白→ivory→白→greige→白→warm→**濃紺**→pale→白→soft→greige→白→ivory→白→navy）で白一色の単調さを解消。3ステップ節は意図的に軽く、やや地味 |
| Visual storytelling | 91 | Hero→30秒メモ→AI整理(2枚)→接客準備(濃色)→店舗ページ/WEB予約→循環 の順で実画面が物語を運ぶ |
| Product clarity | 92 | 「AIが整理し、人が決める」を比較2枚で証明。接客準備3ブロックが実画面で見える |
| CTA | 88 | 主CTAは一貫。main内 .btn は12（1Aの11と同水準）。Hero／接客準備／料金／最終の4点に集約、HP制作・Worksは副 |
| Mobile | 86 | 横スクロール0・順番自然・CTA押しやすい。ただしAI整理2枚が341pxで細部が小さい、全体が約16,700pxと長い |
| Trust | 89 | 実画面＋架空データ注記＋数値効果なし＋価格と条件を明示。「準備中」告知を消した |
| Sales conversion | 88 | ①悩み→②仕組み→③実画面→④短いメモ→⑤人が決める→⑥接客準備→⑦店舗ページ・予約→⑧14,800円 の8段が1本でつながる |
| **平均** | **88.9** | **90未満**（§30へ） |

### なぜ90に届かないか（先に報告・追加改修は代表判断待ち）

1. **モバイルのAI整理比較**: 375pxで2枚とも341px幅になり、画像内の細かい文字（「以前／今回」等）は読めない。説明文で補っているが「見て分かる」には弱い。対策候補: 375pxでは2枚目を「3項目目の部分だけ」を見せる別トリム（**要・追加の切り抜き＝素材判断**）、または横スクロールの切替UI（JS）。
2. **ページ長**: 16節・PC約10,900px／スマホ約16,700px。Salon70／HP20／Works10の配分は守れているが、スマホで長い。対策候補: 3ステップ節とSalonストーリー節の統合、循環フロー節の圧縮。
3. **3ステップ節の弱さ**: 軽量にした分、Heroの直後で「視覚的な受け」が薄い。小さなアイコンか実画面の抜粋で補う余地。

## 30. 残 P0 / P1 / P2

| 優先 | 内容 |
|---|---|
| P0 | なし（掲載不可表現・価格不整合・リンク切れ・console error は0） |
| P1 | §29-1 モバイルのAI整理比較の可読性（素材追加を伴うため代表判断） |
| P1 | §29-2 ページ長の圧縮（節統合の可否） |
| P2 | 3ステップ節の視覚補強／接客準備節にPCで幅700px相当の見せ方（現在538px） |
| P2 | 本番前: OGP画像の更新要否（現行 ogp-1200x630.png のまま）／Salon LP・3分デモページの新設は別工程 |

## 31. commit ／ 32. push ／ 33. git status ／ 34. master

本書の末尾（§37）に実測を記録。commit対象: index.html／renewal.css／本書。push先: origin/feature/website-renewal のみ。master へのmerge/push 0。

## 35. 本番変更0

deploy 0／master 0／DNS 0／contact-api 0／Stripe 0／問い合わせ・申込送信 0／Salon repo 0。GitHub Pages は `master × WEBSITE/**` でのみ発火するため、feature branch への push では公開されない。

## 36. 代表へ確認してほしい点

1. **Heroの右側**: 接客準備（主）＋30秒メモ（従）の組み合わせでよいか。代替案: 接客準備のみ（スマホ枠なし）。
2. **AI整理の見せ方**: 左右同寸の並置（現状）か、メイン1枚＋部分重ね（より「演出」寄り）か。
3. **§29の3点**（モバイル比較の可読性／ページ長／3ステップの視覚補強）に追加改修を行うか。行う場合、モバイル用の追加トリムは「必要だから撮る」原則に照らして許可するか。
4. Works節を白背景・短縮にした結果、Worksの存在感は十分か（navy全幅から格下げ）。

## 37. 終了時のGit状態（実測）

```text
branch : feature/website-renewal
commit : index.html + renewal.css + 本書 の1commit
push   : origin/feature/website-renewal（HEAD = origin）
status : 0行
master : d3c6054（未変更）
```

## 38. 変更履歴

| 版 | 日付 | 内容 |
|---|---|---|
| v1.0 | 2026-08-23 | 制定（P32-3 Phase 1D・TOPを実画面6枚で再構成。ローカル検証済み・本番未反映・self review 平均88.9） |
