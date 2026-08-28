---
name: salon-web-design
description: Smart Labo Salon の Web ページ（WEBSITE/salon.html・assets/css/salon*.css）を設計・実装・レビューするときの正式デザイン基準。salon.html を触る、Salon の LP を作る／直す、Salon のセクション構成・漫画・人物イラスト・実画面の配置を決める、Salon の見た目についてレビューする、といった作業のときに必ず読み込む。一般的な SaaS カード UI へ戻さないための拘束条件が入っている。
---

# Smart Labo Salon Web Design Skill

## このSkillが存在する理由

Smart Labo Salon のページでは、**参考画像や正式素材を渡しても、実装が一般的な
SaaS カード UI へ戻ってしまう**という失敗が繰り返し起きた。

具体的には次が繰り返された。

- 情報が3つあるから3列カード、4つあるから4列カードにする
- 白い角丸カード＋影を全セクションで反復する
- 人物を小さな丸アイコンにする
- 参考の漫画LPを渡されても「見出し＋カード＋説明文」に翻訳してしまう
- 素材が足りないときに、独自の低品質な人物SVGを描いて埋める

このSkillは、その再発を防ぐための**拘束条件**である。
迷ったら「カードにしない」「人物を大きく」「画像とHTMLを一体化する」を選ぶ。

---

## 基本思想

Smart Labo Salon は、一般的な SaaS テンプレートを作るプロジェクトではない。

> **Web UI の中にイラストを入れる**のではなく、
> **漫画・人物・情景・実画面・吹き出しが、ページの主要デザインそのものになる。**

目指すのは
**「サロン向け営業資料をそのまま高品質にWeb化したLP」**であって、
プロダクトサイトのテンプレートではない。

参考画像や正式素材が存在する場合、**それを最優先する**。
「参考画像に似せる」のではなく、参考画像の

- 情報密度
- 人物の大きさ
- 色
- 漫画表現
- 吹き出し
- 視線誘導
- レイアウト
- セクション同士のつながり

を **Web 上で再構築する**。

---

## 役割分担（重要）

| 担当 | 範囲 |
|---|---|
| **ChatGPT / 人間のアートディレクション** | アートディレクション、漫画素材、人物素材、情景・背景素材、完成イメージ、デザインカンプ |
| **Claude Code** | HTML / CSS / レスポンシブ / リンク / CTA / アイコン / 実画面配置 / アクセシビリティ / SEO / 画像最適化 / Git 管理 |

**Claude Code は独自のイラストレーターにならない。**
人物・漫画・主要アートは、正式素材または承認済み素材を優先する。

UI アイコン（線画のピクトグラム）は Claude Code が inline SVG で作ってよい。
**人物・顔・情景・漫画は作らない。**

---

## 代表判断で確定済み（戻さないこと）

過去に一度承認された内容でも、あとから代表判断で上書きされたものがある。
**新しい判断が勝つ。** 以下は差し戻し禁止。

| 確定日 | 内容 | 旧ルール（無効） |
|---|---|---|
| 2026-08-28 REBUILD-2 | **セクション順は MASTER COMP に従う**。`#screens`（できること）を `#benefits`（導入メリット）より前に置く | Phase 2 の順序（導入メリットが先） |
| 2026-08-28 REBUILD-2 | **最終CTA は明るいSalonトーン**（淡ピンク／白／Salon Pink） | 「最終CTA＝暖色ダーク」 |
| 2026-08-28 REBUILD-2 | 最終CTAに **QR・LINE相談・メール直書きを置かない**。正式フォームへのCTAが主役 | — |

詳細は `references/MASTER_COMP.md` §3-A2・§3-G と
`references/PAGE_STRUCTURE.md` を参照。

---

## 判断に迷ったときの順序

0. **MASTER COMP（`references/MASTER_COMP.md`）を見たか** → デザインはこれが最優先。
   ただし掲載内容は営業SSOTが優先。カンプに描かれていても未提供機能は載せない
1. **正式素材はあるか** → あればそれを主役に据える
2. **参考画像・カンプはあるか** → その情報密度とレイアウトを再構築する
3. **どちらも無いか不足か** → 勝手に埋めない。placeholder として明示し、素材待ちを報告する
4. **カードにしたくなったら** → まず「漫画・ストーリー・図解にできないか」を検討する

---

## 参照ドキュメント

作業内容に応じて必ず読むこと。

| ファイル | 読むべき場面 |
|---|---|
| **`references/MASTER_COMP.md`** | **最優先。デザインを決める前に必ず読む。** 代表承認の完成カンプ（DESIGN SSOT）の役割、継承する要素、コピー禁止の内容、カンプ→正式仕様の変換表 |
| `references/DESIGN_PRINCIPLES.md` | 配色・情報密度・人物の扱い・吹き出し・非対称レイアウト・モバイル方針を決めるとき |
| `references/PAGE_STRUCTURE.md` | セクション構成を決めるとき、各セクションの見せ方を選ぶとき |
| `references/IMPLEMENTATION_RULES.md` | 実装・画像最適化・アクセシビリティ・namespace・確認手順 |

素材計画・再構築ロードマップは
`docs/reviews/SALON_MASTER_COMP_REBUILD_PLAN.md` にある。

---

## 絶対に守る技術的境界（Salon 以外を壊さない）

- Salon 専用 CSS は `.salon-theme` スコープと `.sa-*` 名前空間を維持する
- 共有トークン（`assets/css/tokens.css`）を Salon 都合で変更しない
- `base.css` / `components.css` / `renewal.css` / `main.js` を Salon 都合で変更しない
  （必要な上書きは `.salon-theme` 配下で行う）
- `index.html` / `contact.html` / 価格SSOT / Analytics 設定を巻き込まない
- セクションの `id`（`#features` `#benefits` `#screens` `#revisit` `#manga`
  `#pricing` `#website` `#flow` `#faq` `#cta`）を変えない。外部共有済みのアンカーが壊れる
- 問い合わせ導線（`contact.html?type=consult&topic=salon#interests`）と
  送信先・CSRF 取得先を変えない

## 掲載内容の上位SSOT

デザインより**掲載内容の正確性が優先**される。機能・料金・契約条件は
`smartlabo-salon` リポジトリの営業SSOT・価格SSOTに従う。
未提供機能を現在形で書かない。効果保証・架空実績・架空数値を書かない。
詳細は `references/IMPLEMENTATION_RULES.md` の「掲載内容の境界」を参照。
