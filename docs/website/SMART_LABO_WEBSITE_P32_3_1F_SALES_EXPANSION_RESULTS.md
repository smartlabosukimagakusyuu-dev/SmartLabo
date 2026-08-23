# P32-3 Phase 1F — Website 営業体験拡張 ＋ RELEASE HOLD 解消 ＋ Salon LINE連携ハンドオフ 結果

工程: **P32-3 Phase 1F（ローカル実装・feature branch まで。本番反映なし／master merge なし）**
実施日: 2026-08-23
対象ブランチ: `feature/website-renewal`（開始 HEAD `e866e2c`）
関連: `SMART_LABO_WEBSITE_P32_3_1E_PREFLIGHT_AUDIT.md`（RELEASE HOLD：P1×2）／`SMART_LABO_SALON_LINE_INTEGRATION_HANDOFF.md`（本工程の成果物・Salon側次工程の設計）

> ★ローカル確認URL: `http://127.0.0.1:4789/index.html`（127.0.0.1のみ）
> ★Salon repo は READ-ONLY（HEAD `6304299`・変更0）。LINE連携は**設計ハンドオフまで**。Website に LINE の営業訴求は**追加していない**。

---

## 1. PRE-CHECK
| 確認 | 期待 | 実測 | 判定 |
|---|---|---|---|
| Website branch / HEAD / origin / master / status | feature/website-renewal / e866e2c / e866e2c / d3c6054 / clean | 同左 | ○ |
| Salon branch / HEAD / origin / status | feature/salon-foundation / 6304299 / 6304299 / clean | 同左（READ-ONLY） | ○ |

## 2. Phase 1E P1-1（`index.html#brain`）
- 他9ページのフッター「Company Brain」→ `index.html#brain` の受け皿を**Works節の直前**に追加（既存の `#capabilities` 等と同型の `<span id="brain" class="visually-hidden" aria-hidden="true">`）。
- 着地は Works 節（Company Brain は Works の機能）。`scroll-margin-top` により見出しが固定ヘッダーに隠れない。Company Brain を新商品として復活させていない。
- 全ページのアンカー再検査: 欠落 **0**。

## 3. Phase 1E P1-2（OGP刷新）
| 項目 | 内容 |
|---|---|
| 新ファイル | `WEBSITE/assets/images/ogp-top-1200x630.png`（**1200×630 PNG・207.8KB**） |
| 構成 | ロゴ＋「株式会社スマートラボ」／「AIで、仕事を楽に。顧客との関係を、もっと強く。」／ピル「Smart Labo Salon」＋「美容室向け AI顧客支援」／補足1行／URL／右に接客準備の実画面（窓枠）＋30秒メモのスマホ枠 |
| 価格 | **焼き込みなし**（価格変更耐性） |
| 生成 | Puppeteer で専用レイアウト（HTML/CSS）を描画。使用素材は正式Salon実画面2枚＋ブランドアイコンのみ。外部取得0 |
| meta | `og:image` / `twitter:image` を新ファイルへ。`og:image:width/height` 1200/630 と一致。旧 `ogp-1200x630.png` は apply.html 等が参照するため残置 |
| 確認 | 文字切れなし。縮小（SNSカード想定 600px幅）でも見出し・商品名が読める |

## 4. JSON-LD
`Organization.name` "Smart Labo Works" → **"株式会社スマートラボ"**（`alternateName: "Smart Labo"`・`legalName` 維持）、`WebSite.name` → **"Smart Labo"**（`alternateName: "株式会社スマートラボ 公式サイト"`）。JSON として再パース確認済み。

## 5. privacy / terms（最小修正・法務本文は不変）
| ファイル | 変更 |
|---|---|
| privacy.html | title／og:title／twitter:title「— Smart Labo Works」→「— 株式会社スマートラボ」。description／og／twitter description と lead の「Smart Labo Worksのホームページを通じて」→「**当社Webサイト（smartlaboworks.com）を通じて**」 |
| terms.html | title／og:title／twitter:title のみ同様に変更（本文はもともと「株式会社スマートラボが提供するホームページ・お問い合わせフォーム」） |
| 不変 | 各条文・「Smart Labo Works Lite 本体の規約・ポリシーは同サービス側」等の記述・特商法の案内・制定日 |

## 6. apply.html 375px 横overflow（11px）
- 原因: `.cform` が `display:grid` で、`.cform__actions` 内の `.btn`（`white-space:nowrap`）の最小幅（ボタン文言「申し込む（この時点では請求されません）」=370px）が暗黙の列幅を押し広げていた。
- 修正（`components.css`・5行）: `.cform { grid-template-columns: minmax(0,1fr) }` ＋ `.cform__actions .btn { white-space: normal; text-align:center; max-width:100% }`。contact.html の送信ボタン（短文）には影響なし。
- 結果: 375px で scrollWidth 386 → **375（横スクロール0）**。

## 7. Hero slider
| 項目 | 内容 |
|---|---|
| 構成 | 4スライド（初期表示＝①接客準備）: ①接客準備 `salon-ui-briefing` ②30秒メモ `salon-ui-memo-phone`（スマホ枠）③AI整理・スタッフ確認 `salon-ui-ai-review-unchecked` ④店舗ページ／WEB予約 `salon-public-shop`＋`salon-public-booking`（スマホ枠2台） |
| ラベル | ①次回来店の接客準備／前回のポイント・前回の会話・今回の提案候補 ②接客後、その場で短く記録／30秒メモ ③AIが整理。残す内容は人が決める。／チェックを外した項目は登録されません ④店舗ページからWEB予約まで。／24時間WEB予約受付 |
| 切替 | 5秒自動・左右ボタン・ドット。hover／focus 中は停止、手動操作後は自動停止（以後手動）、タブ非表示で停止・復帰で再開、`pagehide` で停止 |
| 実装 | `assets/js/top-hero.js`（6.8KB・vanilla・defer・外部依存0）＋ renewal.css。JS無効／失敗時は①のみ表示・操作UI非表示（実測） |
| 表示上限 | ①470px ②高さ基準（≦448px） ③400px ④200px×2（1C-2B の上限内）。舞台は固定高 560px（≤960: 520／≤640: 490）で高さ変動なし |
| 画像加工 | なし（Salon実画面をそのまま。別機能に見せない） |

## 8. slider accessibility
`role="region" aria-roledescription="カルーセル" aria-label`、各スライド `aria-hidden` 切替、非表示スライドは `hidden`、ドットは `aria-current`＋`aria-label`（"1. 次回来店の接客準備" 等）、矢印ボタン `aria-label`、←→ キー対応（スライダー内フォーカス時）、`:focus-visible` リング、`prefers-reduced-motion: reduce` で自動切替なし（実測: 5.8秒後も①のまま）、手動操作後に `aria-live="polite"`（自動中は読み上げない）。

## 9. Hero performance
- 初期ロード時に取得する実画像は **①の briefing のみ**（`fetchpriority=high`）。②〜④は `hidden`＋`loading=lazy` のため**表示するまで未取得**（実測: 起動時 briefing のみ／「次へ」で memo-phone が取得）。
- 舞台固定高で **CLS 0.011**（Good 範囲）。LCP 約0.54s（ローカル）。初期転送 約241KB。
- 動画ボタンは `hidden`（CSSでも `display:none` を明示）。OGP はページ表示時に読み込まれない。

## 10. HP制作イメージ 3種（`WEBSITE/assets/images/website/`）
| ファイル | 内容（すべて架空） | 構成の違い | サイズ |
|---|---|---|---|
| website-sample-salon.webp | SALON SOLEIL（既存の架空店舗・P32-IMG-2のAI生成写真のみ使用） | 写真全幅ヒーロー＋メニュー3（写真付）＋スタッフ3＋予約CTA帯 | 1800×1125・90KB |
| website-sample-wellness.webp | リラクス整体院（架空・写真なし） | 2カラムヒーロー（お悩みカード）＋サービス3＋料金3＋予約CTA帯 | 1800×1125・63KB |
| website-sample-corporate.webp | 北都会計事務所（架空・写真なし） | 濃紺フルヒーロー＋サービス4＋事務所案内／表＋問い合わせCTA帯 | 1800×1125・72KB |
- 方式: HTML/CSS の完全架空モックを Puppeteer で描画（1200×750・DSF1.5・WebP q82）。Webからの写真取得0。原本HTML・PNGは `SmartLabo_Artifacts\P32-3-1F\hp-samples\` に保管。
- 表現: 「制作イメージ」「（架空の店舗・事務所）」。**「制作実績」と書いていない**。

## 11. HP制作セクション
見出し（維持）→ lead に「業種やお店の雰囲気に合わせて、つくります。」を追加 → **制作イメージ3枚（ブラウザ窓枠＋A/B/Cラベル）** → 注記「掲載している画面は制作イメージです（架空の店舗・事務所）。」→ 制作 50,000円〜（税別）／管理・保守 月額5,000円〜（税別）→ 相談CTA（secondary）。PC3列／960以下2列／640以下1列。Salonより視覚的に強くしていない（背景 greige・画像は窓枠のみ）。

## 12. 店舗自身の更新訴求
店舗ページ／WEB予約 節の末尾に `.sl-update` を追加: 「作って終わりじゃない。お店で更新できる。」＋本文（店舗情報・写真・メニュー・スタッフは管理画面から変更→公開ページへ反映／大きなデザイン変更・新ページ・特別な制作は相談）＋4項目カード。根拠: 1C-2B で設定画面から写真7枚を投入→公開ページへ反映を実証。「何でも自由にデザイン変更」「全ページ自由編集」とは書いていない。

## 13. デモ動画導線（器のみ・動画未完成）
- Hero スライダー下に `[data-video-open] hidden` の「▶ 60秒で見る Salon」ボタン（**非表示**・`display:none` 実測）。`<dialog id="sl-video-dialog">` に `<video controls playsinline preload="none" data-src="">`。
- 公開手順: `hidden` を外し `data-src` に正式URL → JS が `src` を遅延設定して `showModal()`。`data-src` が空なら**何もしない**（存在しないURLを開かない）。「準備中」ボタンは置かない。

## 14. デモ動画仕様（60〜90秒・別工程で制作）
0–6 店舗ページ「お客様の入口から。」／6–14 WEB予約「24時間WEB予約受付。」／14–24 来店・顧客確認「予約情報が、そのまま店舗へ。」／24–34 30秒メモ「接客後、覚えておきたいことを短く。」／34–47 AI整理「AIが整理。」／47–55 スタッフ確認「残す内容は、人が決める。」／55–70 接客準備「次回来店前には、前回のポイント・会話・提案候補を確認。」／70–80 店舗ページ更新「メニューや写真は、お店で更新。」／80–90 CTA「Smart Labo Salon 月額14,800円（税別）初期費用0円／実際の画面を見ながらご説明します。」
素材は隔離demoの実画面のみ。音声なしでも理解できるテロップ。**LINE画面は入れない**（実装後に追加：60秒短縮版／90秒完全版の2本候補）。

## 15. SNS転用仕様
9:16・15〜30秒の Reels／広告版へ切り出せるよう、重要情報（テロップ・画面の核心）を**画面中央付近**に置く。サムネは接客準備の実画面＋再生アイコン（CSSで付与・画像に焼き込まない）。動画ファイル制作は別工程。

## 16. LINE現状（事実）
友だち追加リンク掲載（`shop_profiles.line_add_url`）＋登録状況の**手動**記録（`customers.line_linked/line_display_name/line_linked_at`・P30-2-I）まで。配信・Messaging API・Webhook・userId は**未実装**。PRICE_PLAN では LINE配信＝Option（別料金・別設計）。→ 詳細は HANDOFF §1。

## 17. LINE完成形（第一段階）
顧客情報 → 条件抽出 → 対象顧客を確認 → AI文案 → スタッフ確認・編集 → 明示送信 → LINE到達 → 予約URL → WEB予約 → 予約反映（HANDOFF §2・§3）。AIが勝手に送らない・自動大量送信から始めない。

## 18. 条件抽出
最終来店日／来店回数／利用メニュー／担当スタッフ／未来予約の有無／LINE連携状態／顧客検索（現schemaで取得可能・事実のみ）。LTV・売上予測・離脱予測・AIスコアは**禁止のまま**（HANDOFF §4）。

## 19. LINEセキュリティ
チャネル秘密値のサーバー側暗号化保管、Webhook署名検証・冪等化、短寿命連携トークン＋スタッフ確認、送信ログ（誰が・いつ・誰に・何を）、送信上限・警告、テナント分離（HANDOFF §6）。

## 20. Privacy影響
取得情報（userId等）・外部送信先（LINEヤフー）・利用目的・顧客への説明・店舗の責任 → `SALON_PRIVACY_CONSENT_V1` `SALON_SERVICE_AGREEMENT_V1` `SALON_LEGAL_REVIEW_ITEMS_V1` の更新候補。**「法的に問題ない」と断定しない**（HANDOFF §7）。

## 21. Website上のLINE表現
**追加していない**（TOP本文・meta・altに「LINE連携／LINE送信／条件抽出してLINE」なし。HP制作節の「LINE・Instagram・外部予約サービスへの導線」は制作物の導線の意味で既存）。実装・実疎通後に P32-3-R-LINE で追加（HANDOFF §10）。

## 22. responsive（12ページ × 1440/1280/1024/768/390/375 = 72）
横スクロール **0**（apply.html 375 も解消）／TOP: 文字切れ0・画像崩れ0・CTA切れ0・sticky header 干渉0。Hero スライダーはスマホで1画面ずつ（③は330px幅、④はスマホ枠2台を左右）。HP制作例はPC3列→2列→1列。

## 23. performance
TOP 初期転送 約241KB／LCP 0.54s／CLS 0.011／追加画像: HP例3枚（計225KB・lazy）＋OGP（207.8KB・ページでは未読込）／JS +6.8KB（defer）。全画像 WebP（OGPのみPNG＝SNS互換のため）。

## 24. SEO
title／description／canonical 不変（TOP）。JSON-LD 更新（§4）。OGP 更新（§3）。privacy/terms の title を会社名へ（§5）。h1=1・alt 欠落0（TOP 16枚）・sitemap 不変。

## 25. 禁止表現
全12ページ（コメント・script除外）で「24時間AI／AIが予約／使い放題／LINE自動配信／LINE一斉配信／LINE連携／LINE送信／ホットペッパー／売上AI／離脱予測／AI経営分析／完全オリジナル／AIが自動保存／完全自動／法務確認／絶対に安全／一切送／30日後／必ず30秒／Company OS／音声入力／口コミ／スタッフ個別／削減／売上が／効果が／制作実績／実績／自由に編集／WordPress」→ **0件**。

## 26. P0 / P1 / P2 / P3（Phase 1E からの更新）
| 優先 | 項目 | 状態 |
|---|---|---|
| P1-1 | `#brain` 受け皿 | **解消** |
| P1-2 | TOP OGP | **解消**（新OGP） |
| P2-3 | JSON-LD name | **解消** |
| P2-4 | privacy/terms 表記 | **解消**（最小修正） |
| P2-5 | apply.html 375px overflow | **解消** |
| P2-6 | csrf_secret 予防ローテーション | 残（運用・任意） |
| P3 | 非TOP og:image 未設定／未参照PNG | 残（任意） |
| 情報 | company.html 所在地未掲載 | 維持（代表判断） |
| 新規 | Hero CLS 0.011（0→0.011・Good範囲） | P3（許容） |

**P0 = 0／P1 = 0。**

## 27. RELEASE GATE
**RELEASE CANDIDATE**（P0/P1 = 0）。★LINE未実装は Website に掲載していないため blocker にしない。★master merge／deploy は本工程で実行しない（代表判断後の別工程）。

## 28. files
| 区分 | ファイル |
|---|---|
| 変更 | `WEBSITE/index.html`／`WEBSITE/assets/css/renewal.css`／`WEBSITE/assets/css/components.css`（5行）／`WEBSITE/privacy.html`／`WEBSITE/terms.html` |
| 新規 | `WEBSITE/assets/js/top-hero.js`／`WEBSITE/assets/images/ogp-top-1200x630.png`／`WEBSITE/assets/images/website/website-sample-{salon,wellness,corporate}.webp`／`docs/website/SMART_LABO_WEBSITE_P32_3_1F_SALES_EXPANSION_RESULTS.md`（本書）／`docs/website/SMART_LABO_SALON_LINE_INTEGRATION_HANDOFF.md` |
| 不変 | 既存Worksページ（features/pricing/apply/company/contact/company-brain/product/real-estate/404）の内容・main.js・contact-form.js・apply-form.js・contact-api・signup-api・Salon repo・本番 |
| 原本 | `SmartLabo_Artifacts\P32-3-1F\`（HP例のHTML/PNG・OGPのHTML/PNG・節別／スライド別スクリーンショット・6幅フルページ） |

## 29〜33. commit ／ push ／ status ／ master ／ production
本書末尾（§35）に実測。master merge 0／deploy 0／本番変更0／問い合わせ・申込送信0／Salon repo 変更0。

## 34. 次工程
1. **代表判断**: RELEASE CANDIDATE の公開可否（master merge → GitHub Pages）。公開時の確認: OGPキャッシュ（各SNSのデバッガで再取得）、`#brain` 到達、動画ボタンは非表示のまま。
2. **デモ動画制作**（§14・§15）→ 完成後 `hidden` 解除・`data-src` 設定（P32-3-R-VIDEO）。
3. **P33-LINE**（Salon repo）: HANDOFF §9 の工程 0〜5 → 実疎通後に **P32-3-R-LINE** で Website へ正式表現追加。
4. 任意: csrf_secret ローテーション／非TOP og:image／未参照PNG整理。

## 35. 終了時のGit状態（実測）
```text
branch : feature/website-renewal
commit : 本書を含む1〜2commit（Website実装＋docs）
push   : origin/feature/website-renewal（HEAD = origin）
status : 0行
master : d3c6054（未変更）
```
