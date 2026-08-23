# P32-3 Phase 1G — Website 本番公開 結果（PRODUCTION RELEASE）

工程: **P32-3 Phase 1G（master統合＋GitHub Pages 公開＋本番Smoke Test）**
実施日: 2026-08-23（JST）
公開commit: **`502768a`**（= Phase 1F RELEASE CANDIDATE。feature/website-renewal を master へ fast-forward）
本番URL: **https://smartlaboworks.com/**

> ★今回は Website 公開のみ。Salon LINE連携・デモ動画制作・Salon本体・contact-api／signup-api・Stripe・DNS は **変更0**。

---

## 1. PRE-CHECK
| 確認 | 期待 | 実測 | 判定 |
|---|---|---|---|
| branch / HEAD / origin / master / status | feature/website-renewal / 502768a / 502768a / d3c6054 / clean | 同左（origin/master も d3c6054） | ○ |
| ff 可否 | — | master は feature の祖先 → **fast-forward 可能** | ○ |

## 2. RELEASE前最終確認（ローカル 127.0.0.1:4789・HEAD 502768a）
| 項目 | 結果 |
|---|---|
| Hero スライダー4枚／左右／ドット／5秒自動／hover停止／手動後停止 | ○（実測: 初期①→次へ②→ドット→①／5.8秒後に②） |
| reduced-motion | 自動切替なし（5.8秒後も①） |
| JS無効 fallback | ①のみ表示・操作UI非表示 |
| HP制作イメージ3種／「作って終わりじゃない。お店で更新できる。」 | HTMLに存在・描画確認 |
| Salon価格 14,800円（税別）／初期費用0円／1店舗単位／6か月 | ○ |
| HP制作価格 50,000円〜／月額5,000円〜（税別） | ○ |
| CTAリンク／#brain | 全リンク到達・アンカー欠落0・`id="brain"` あり |
| 新OGP／JSON-LD／privacy・terms／apply 375px | ○（1F と同一） |
| 禁止表現（LINE連携済み・LINE自動配信・AIがLINE送信・Messaging API・制作実績 等） | 12ページ **0件** |

## 3. 最終Responsive（ローカル・12ページ×6幅=72）
横スクロール0／console 0／失敗request 0。TOP: 文字切れ0・画像崩れ0・スライダー崩れ0・HP例崩れ0・CTA切れ0・sticky header 干渉0。

## 4. master統合
```text
git checkout master
git merge --ff-only feature/website-renewal   → d3c6054..502768a（fast-forward・merge commit なし）
git push origin master                        → d3c6054..502768a master -> master
master HEAD = origin/master = 502768ab6bf40496b85fe51a7f28ccec53232fb9
```

## 5. 本番公開
既存の正式手順＝`.github/workflows/pages.yml`（master × `WEBSITE/**` の push で自動デプロイ）のみ使用。DNS／contact-api／signup-api／Stripe／Salon本番: 変更0。
公開検知: master push 後 **約15秒**で `https://smartlaboworks.com/index.html` に新TOP（`ogp-top-1200x630.png`・`top-hero.js`・`id="brain"`・新JSON-LD）を確認。本番 index.html は working copy と**改行コード以外同一**（cmp 一致）。

## 6. 本番Smoke Test
| 項目 | 結果 |
|---|---|
| ページ HTTP | `/` `/index.html` features pricing apply company-brain company contact privacy terms product real-estate 404 robots.txt sitemap.xml → **すべて200** |
| CSS／JS | tokens/base/components/renewal.css・main.js/top-hero.js/contact-form.js → 200 |
| Salon画像6枚 | 200（`salon-ui-ai-review.webp` のみ初回 sweep で 503（text/html）= CDN伝播中の一過性。直後5回連続200・ブラウザ巡回でも失敗0） |
| HP制作例3枚 | 200 |
| OGP画像 | `ogp-top-1200x630.png` 200 image/png 212,779B（旧 `ogp-1200x630.png` も200） |
| Hero slider（本番） | is-ready／初期①のみ画像取得／次へ→②／ドット／キー／自動5秒／reduced-motion停止／no-JS fallback → **ローカルと同結果** |
| CTA遷移 | TOP main内 .btn 12件 → contact/features/pricing/company すべて200 |
| contact type事前選択 | `contact.html?type=demo#form` → 種別「デモ依頼」選択・`#form` 存在 |
| #brain | Works各ページの nav「Company Brain」→ `index.html#brain` → Works節へ着地（節上端 viewport 161px＝ヘッダー下） |
| 375px 横スクロール | 0（390も0） |
| console error／404／failed | 72 page×viewport 巡回で **0／0／0**。外部通信は contact.html の CSRFトークン取得（form.smartlaboworks.com・既存設計）のみ |
| 性能（本番・1440） | LCP 約0.54s／CLS 0.012／DCL 0.50s |
| フォーム | **実送信なし**（問い合わせ・申込ともGET表示のみ） |

## 7. OGP確認（本番HTML）
```text
og:title        株式会社スマートラボ｜AIで店舗・企業の仕事を楽に
og:description  美容室向けAI顧客支援「Smart Labo Salon」、店舗ホームページ制作、法人向け「Smart Labo Works」。…
og:image        https://smartlaboworks.com/assets/images/ogp-top-1200x630.png（200 / image/png / 1200×630）
og:image:width/height  1200 / 630
twitter:card    summary_large_image
twitter:image   https://smartlaboworks.com/assets/images/ogp-top-1200x630.png
```
SNS側キャッシュ: 新ファイル名のため通常は即時反映。旧カードが残る場合の再取得方法（**実施していない**）: Facebook シェアデバッガー（developers.facebook.com/tools/debug/）で「もう一度スクレイピング」／X は Card Validator 廃止のため投稿プレビューで確認／LINE はトーク内で新規URL共有（末尾に `?v=2` 等のクエリで再取得可）。

## 8. RELEASE判定
**P0 = 0／P1 = 0 → RELEASED**。ロールバック不要（必要時は `git revert` または master を `d3c6054` へ戻して push＝旧TOPへ復帰。WEBSITE/** 配下のみのため他システム影響なし）。

## 9. 記録（docs更新）
- `PROJECT_BIBLE/CURRENT_STATUS.md`: Homepage Version を **v2.0.0（2026-08-23 公開・会社TOP刷新／Salon中心）** に更新・Phase 1G 節を追加
- `PROJECT_BIBLE/CHANGELOG.md`: 2026-08-23 エントリ追加
- 本書（`docs/website/`）

| 記録項目 | 値 |
|---|---|
| 公開日時 | 2026-08-23（JST）・master push 直後に Pages 反映 |
| 公開commit／master HEAD／origin/master | 502768a |
| production確認 | §6（全200・console 0・404 0） |
| OGP | §7（新OGP反映） |
| responsive | §3・§6（72巡回 0件） |
| LINE | **未実装・未掲載**（設計ハンドオフのみ: `SMART_LABO_SALON_LINE_INTEGRATION_HANDOFF.md`） |
| 動画 | **未公開**（ボタン hidden・`data-src` 空・モーダルは器のみ） |
| 残課題 | P2: csrf_secret 予防ローテーション（任意）／P3: 非TOP og:image・未参照PNG整理／情報: company.html 所在地未掲載（代表判断）／デモ動画制作（P32-3-R-VIDEO）／LINE（P33-LINE → P32-3-R-LINE） |

## 10. 次工程
**P33-LINE: Smart Labo Salon LINE INTEGRATION**（Salon repo・`SMART_LABO_SALON_LINE_INTEGRATION_HANDOFF.md` §9 の工程0から）。★自動で着手しない。

## 11. 変更履歴
| 版 | 日付 | 内容 |
|---|---|---|
| v1.0 | 2026-08-23 | 制定（P32-3 Phase 1G・master ff 統合 d3c6054→502768a・GitHub Pages 公開・本番Smoke Test 全項目○・RELEASED） |
