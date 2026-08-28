# Smart Labo Salon — 実装ルール

`WEBSITE/salon.html` と `WEBSITE/assets/css/salon*.css` を触るときの実装側ルール。

---

## A. 素材の扱い

1. **正式画像が存在する場合、Claude が代替 SVG を勝手に作らない。**
2. **正式画像が不足している場合、「簡単な SVG で仮置き」をしない。**
   placeholder であることを画面上に明示するか、素材待ちとして報告する。
   人物・顔・情景・漫画を Claude が描いて埋めない。
3. **画像をカードへ閉じ込めない。** 角丸・影・枠線で囲うと、それだけでカードになる。
4. **画像上にリンクを焼き込まない。** CTA・リンクは必ず HTML 要素にする。
5. **画像内に含まれる文字を HTML 側で不必要に二重表示しない。**
   画像に吹き出しがあるなら、HTML は見出し＋短い補足だけにする。
6. **SEO 上重要な内容を画像だけに依存させない。**
   見出し・商品説明・料金・契約条件・CTA・FAQ・導入フロー・機能名称・
   法務上必要な注記は、原則 HTML テキストで持つ。
   画像内文字は、漫画の吹き出しや補助的な短文程度に限定する。
7. **alt は画像内全文の転記にしない。** 何が写っているかを1文で説明する。
   装飾に徹する画像は `alt=""` ＋ `aria-hidden="true"`。
8. UI アイコン（線画ピクトグラム）は inline SVG で作ってよい。
   `currentColor` を使い、外部リクエストを増やさない。
   意味は必ずテキスト側に持たせ、SVG は `aria-hidden="true"` にする。

---

## B. 画像の最適化

| 項目 | ルール |
|---|---|
| 形式 | **WebP**。品質は既存SSOT基準の **q82** を基本、漫画・線画は q85〜88 |
| OGP のみ | **PNG**（既存の `ogp-*.png` に合わせる。クローラ互換） |
| 保存先 | LP アート＝`WEBSITE/assets/images/salon/lp/`、実画面＝`WEBSITE/assets/images/salon/` |
| 命名 | `salon-lp-*`（LPアート）／`salon-ui-*`（管理画面）／`salon-public-*`（公開ページ） |
| 原寸PNG | **リポジトリへ入れない。** `Desktop\SmartLabo_Artifacts\` 側へ保管する |
| `width`/`height` | **全画像に必須**（CLS 対策） |
| `loading="lazy"` | HERO 以外は付ける。`decoding="async"` も併用 |
| `fetchpriority="high"` | **HERO 画像のみ** |
| `<link rel="preload">` | **HERO 画像のみ。** 他画像には付けない（帯域を奪い逆効果） |
| `object-fit: cover` | **人物・漫画には使わない。** 顔・吹き出し・重要UIが切れる。実画面スクショに限定する |
| 拡大 | 原寸以上へ過剰に拡大しない。`max-width` で上限を切る |
| 容量 | LP アートは1点 150KB 以内を目安。ページ全体の画像は 1.0〜1.4MB を上限の目安 |

### アルファチャンネルの注意（実測済み）

透過 PNG を WebP 化するとき、アルファを可逆のまま持つと**ファイルが約4倍に膨らむ**。
フェードした縁のような滑らかなアルファは非可逆で問題ないため、
`alpha_quality`（PIL なら `alpha_quality=70`）を指定する。

実測例：1536×1024 の人物ビジュアル
- 可逆アルファ … 196KB
- `alpha_quality=70` … **136KB**（見た目の差なし）
- アルファ破棄 … 48KB（ただし背景グラデーションに継ぎ目が出るため不採用）

---

## C. CSS の境界

11. **Salon 専用 CSS は Salon namespace を維持する。**
    - 全セレクタを `.salon-theme` の子孫に限定する
    - 新規クラスは `.sa-*` 名前空間
    - `salon.html` の `<body class="sl-page salon-theme">` にだけ適用される
12. **共有トークンを Salon 都合で変更しない。**
    - `assets/css/tokens.css` の `:root`（Smart Blue を含む）は触らない
    - Salon の値は `salon-tokens.css` に `--sa-*` として `.salon-theme` スコープで定義する
13. `base.css` / `components.css` / `renewal.css` / `main.js` を変更しない。
    必要な上書きは `.salon-theme` 配下で行う。
14. 新しい CSS ファイルを増やさない（`salon-tokens.css` と `salon.css` の2本で完結させる）。
15. ブレークポイントは既存の4段（1080 / 860 / 640 / 520）だけを使う。
16. 外部ライブラリ・CDN・ビルドを追加しない。素の HTML/CSS/JS で書く。
17. JS は `main.js` の既存機能（ドロワー／current強調／スクロール境界線／
    FAQアコーディオン／reveal）を流用する。Salon 用の JS を足さない。

### 共用ルールの誤削除に注意

`.sa-bubble` の基底定義は「攻める集客」でも使う。
あるセクションで使わなくなっても、他セクションでの使用を確認せずに削除しない。

---

## D. アクセシビリティ

18. 見出し階層はレベルを飛ばさない（h1 → h2 → h3）。
19. 本文 16px・注記 14px を下限。**14px 未満の文字を作らない**。
20. タップ領域は **44px 以上**。インラインリンクは `inline-flex` ＋ `min-height` で確保する。
21. コントラストは **WCAG AA**（本文 4.5:1 / 大文字 3:1）を満たす。
    グラデーション背景の上では、最も濃い側の色で計算する。
22. 装飾 SVG は `aria-hidden="true"`。意味を持つ SVG は `role="img"` ＋ `aria-label`。
23. フォーカスリングを消さない。
24. `prefers-reduced-motion: reduce` に配慮する。
25. JS が動かなくても内容が読める（FAQ は全問開いた状態で読める）。

---

## E. 確認手順（実装後は必ず実施）

26. **375 / 768 / 1080 / 1440** の4幅で確認する。
27. 確認項目：
    - 横スクロール **0**（`documentElement.scrollWidth > innerWidth` で判定）
    - はみ出し要素 **0**
    - 人物の顔・吹き出し・重要UIの切れ **0**
    - 画像の縦横比が維持されている
    - 14px 未満の文字 **0**
    - タップ 44px 未満 **0**（スマホ・タブレット）
    - コントラスト AA 未達 **0**
    - 画像リンク切れ **0** ／ alt 欠落 **0**
    - 壊れたアンカー **0** ／ 空リンク・ダミーリンク **0**
    - スマホで画像内文字が読める
28. **横スクロールは禁止**（CSS で横スクロールさせる逃げ方をしない）。
29. 触っていないセクションに差分が出ていないことを `git diff` で確認する。
30. `node docs/reviews/tools/check-legal.js` を実行して合格を確認する。

### lazy 画像の検証時の注意

`loading="lazy"` の画像はビューポート外だと `naturalWidth` が 0 のままになる。
検証時は対象セクションへスクロールし、必要なら一時的に `loading='eager'` にしてから測る。

---

## F. 掲載内容の境界（デザインより優先）

**上位SSOT**：`smartlabo-salon` リポジトリの
`docs/salon/contracts/SALON_SALES_TERMS_SUMMARY_V1.md`（営業SSOT）と
`docs/salon/SALON_PRICE_PLAN_v1.md`（価格SSOT）。
HP制作価格は `docs/website/WEBSITE_PRODUCTION_AND_MAINTENANCE_PRICE_V1.md`。

### 書かない機能（営業SSOT §12「含まれない機能」）

- AIチャットボット／AI対話による予約受付／AI電話受付
- 売上金額のAI分析／離脱予測／AI経営分析／多店舗比較（すべて Pro 領域）
- メール配信（Option・別料金）
- LINE自動配信／LINE一斉配信／AIが自動で送る／開封率・反応率の分析
- 外部予約サイト（Hot Pepper 等）との自動連携
- 「HP制作」を Salon 標準商品として表現すること（独自ドメインHPは別サービス）

### 書かない表現（営業SSOT §18）

- 導入店舗数・お客様の声・導入実績・効果数値・売上向上率（実績がないため）
- 「売上が◯円増える」「リピート率が◯%上がる」等の効果保証
- 架空の顧客レビュー・架空の評価スコア（★4.8 等）

### 画像内文字にも同じ基準が及ぶ

**画像に焼き込まれた文字も掲載内容である。**
納品素材に上記の禁止表現が含まれていたら、実装前に指摘する。
画像内文字は後から直せないため、**発注段階で潰すのが最も安い**。

代表判断で演出的コピーとして許可された場合は、
その旨をコミットメッセージと HTML コメントに記録し、
**HTML 本文には効果保証表現を書かない**（本文では厳守する）。

### LINE の表現

LINE 連携は標準機能だが、**実LINEへの疎通は準備中**。
「すでに実際のLINEへ送れている」とは書かない。
「導入時に当社と利用開始確認を行います」を添える。
LINE公式アカウントの契約料金・通数料金は**店舗負担**であることを明記する。

---

## G. Git 運用

31. Salon 刷新は `feature/salon-web-redesign-v1` の worktree で作業する。
    元 worktree（`C:\Users\user\Desktop\SmartLabo`）には触れない。
32. `reset` / `revert` / `stash` / `restore` / `clean` / 巻き戻し目的の `checkout` は使わない。
33. `master` への merge・`push`・deploy は、明示的な承認があるまで行わない。
34. 工程ごとに単独コミットにする。素材追加・レイアウト変更・SSOT更新を1コミットに混ぜない。
35. `master` へ push すると `.github/workflows/pages.yml` により
    **`WEBSITE/` が即座に本番公開される**。ステージング環境は存在しない。
