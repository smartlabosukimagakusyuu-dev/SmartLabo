# P32-3 Phase 1C-2B — Salon Web素材の確定 ＋ 公開店舗ページ／WEB予約の取得

工程: **P32-3 Phase 1C-2B（正式素材6枚の確定・配置。TOP HTML/CSS実装は未着手）**
実施日: 2026-08-23
対象ブランチ: `feature/website-renewal`
前工程: `SMART_LABO_WEBSITE_P32_3_1C2A_KEY_CAPTURE_RESULTS.md`（4枚A判定）
根拠仕様: `SMART_LABO_WEBSITE_P32_3_1C1_SCREEN_CAPTURE_SPEC.md`

> ★本工程で **`WEBSITE/` に追加したのは画像6枚（`assets/images/salon/`）のみ**。HTML・CSS・JSは1バイトも変更していない。
> ★Salonリポジトリ・商談用DB・本番・deploy・master・Stripe・問い合わせ／申込送信は **すべて0**。
> ★公開店舗ページの写真は **P32-IMG-2で生成済みの架空店舗・架空人物（AI生成）7枚のみ**を使用。Webからの取得0。

---

## 1. PRE-CHECK

| 確認 | 期待 | 実測 | 判定 |
|---|---|---|---|
| branch | `feature/website-renewal` | 同左 | ○ |
| local HEAD（開始時） | `2934918` | `2934918567b2fdcbdbe81d68f1076c94f85bb47c` | ○ |
| origin/feature/website-renewal（開始時） | `fa6d843` | `fa6d8430035d95db7d4392bdc8c8680a6c98f66a` | ○ |
| master | `d3c6054` | `d3c6054c99a0df7d15d6fccec08ab18495d8d463` | ○ |
| `git status --porcelain` | 0行 | 0行 | ○ |
| `fa6d843..2934918` の差分 | 結果doc 1ファイルのみ | `A docs/website/SMART_LABO_WEBSITE_P32_3_1C2A_KEY_CAPTURE_RESULTS.md`（+502行・他0） | ○ |

## 2. `2934918` の push 結果

```text
git push origin feature/website-renewal
  fa6d843..2934918  feature/website-renewal -> feature/website-renewal
push後: HEAD = origin/feature/website-renewal = 2934918 / master = d3c6054（未変更）
```

## 3. Salon repo の状態（READ-ONLY）

```text
repo   : C:\Users\user\Desktop\smartlabo-salon
branch : feature/salon-foundation
HEAD   : 6304299d61c10ade7f52a9c7dfb8214bdf7d0837（Phase 1C-1／1C-2Aと同一）
status : 0行（作業前・作業後とも clean）
```

Salon本体（client / server / seed / docs）は **1バイトも変更していない**。

## 4. 撮影専用環境

| 項目 | 値 |
|---|---|
| DB | `salon-app/data/salon-capture-demo.sqlite`（撮影専用・Git管理外。1C-2Aで作成したもの） |
| API | `127.0.0.1:3110`（商談用 3100 とは別プロセス） |
| 画面 | `127.0.0.1:5283`（商談用 5273 とは別プロセス・`SALON_PORT=3110` へproxy） |
| AI | 起動ログ `AI provider initialized: fake` |
| mail | 起動ログ `notification worker started (provider=fake)` |
| 外部通信 | OpenAI 0 ／ Resend 0（環境変数でfakeを明示上書き。`.env` は `openai` / `resend` のため必須） |
| 本番 | 接続0 |
| 撮影 | Puppeteer（Chrome 149 headless）要素／領域キャプチャ。ブラウザUI・カーソル・通知の写り込み0 |
| 商談用プロセス | 3100 / 5273 で起動中のまま。**一切触れていない** |

### 写真7枚の投入（製品の正規経路）

P32-IMG-2 の `upload-ready/` 7枚を、**製品の設定画面（店舗情報／メニュー／スタッフの「写真を選ぶ」→「保存する」）から投入**した。
＝手動投入と同じ経路（PhotoFieldの縮小・WebP再変換・サーバー側検証を通している）。DBへ直接INSERTしていない。

| 対象 | ファイル | 結果 |
|---|---|---|
| 店舗写真（hero） | `salon-soleil-hero.webp` | 投入 |
| メニュー カット／カラー／トリートメント | `salon-soleil-menu-*.webp` | 投入（カット＋カラーは写真なし＝IMG-2の設計どおり） |
| スタッフ 佐藤／田村／高橋 | `salon-soleil-staff-*.webp` | 投入 |

`shop_media` = **7行**、公開APIで7枚すべて `200 image/webp` を確認。
途中、メニュー行の文字一致でカット＋カラーへ誤投入したが、**製品UIの［削除］で取り消してから正しく再投入**した（DB直接操作なし）。

## 5. 正式採用した画像一覧（6枚）

配置先: `WEBSITE/assets/images/salon/`

| # | ファイル | 内容 | 由来 |
|---|---|---|---|
| 1 | `salon-ui-memo-phone.webp` | 30秒メモ（375px・保存前） | 1C-2A A判定 |
| 2 | `salon-ui-ai-review.webp` | AI整理の確認（3項目・全チェック） | 1C-2A A判定 |
| 3 | `salon-ui-ai-review-unchecked.webp` | 同・3項目目を除外 | 1C-2A A判定 |
| 4 | `salon-ui-briefing.webp` | 接客準備（顧客カルテ経由・3ブロック） | 1C-2A A判定 |
| 5 | `salon-public-shop.webp` | 公開店舗ページ（ヒーロー写真〜WEBで予約するCTA） | **本工程で新規取得** |
| 6 | `salon-public-booking.webp` | WEB予約（担当・日付・時間を選んだ状態） | **本工程で新規取得** |

## 6. 各画像の原寸（PNG原本・Git管理外）

| ファイル | viewport | 切り抜き(CSS) | 2x原本 | 4x原本（WebP縮小元） |
|---|---|---|---|---|
| memo-phone | 375×812 | 347×576 | 694×1152 | 1388×2304 |
| ai-review | 1280×900 | 628×730 | 1256×1460 | 2512×2920 |
| ai-review-unchecked | 1280×900 | 628×730 | 1256×1460 | 2512×2920 |
| briefing | 1280×900 | 340×282 | 680×564 | 1360×1128 |
| public-shop | 375×812 | 375×650 | 750×1300 | 1500×2600 |
| public-booking | 375×812 | 375×740 | 750×1480 | 1500×2960 |

原本保存先: `C:\Users\user\Desktop\SmartLabo_Artifacts\P32-3-1C\`
（`4x\` `public-2x\` `public-4x\` `webp-final\` ＋ 参考 `salon-public-shop-menu-ref` / `salon-public-booking-full-ref`）

### 新規2枚の撮影条件

**salon-public-shop**
- `/s/salon-soleil-demo` を375px幅で表示。**lazy画像を含む全画像のロード完了を待ってから**撮影
- 範囲: ページ最上部 → ［WEBで予約する］ボタン下端 +28px（ヒーロー写真・店名・アクセス・紹介文・CTA）
- 縦長ページ全体は詰め込まない（MENU/STAFFは参考画像として別保存）

**salon-public-booking**
- ［WEBで予約する］→ メニュー「カット」→ 担当「佐藤 花子」→ 日付「8/24（翌日）」→ 時間「14:00」を選択した状態
- 範囲: 「2 担当を選ぶ」見出し上16px → 時間グリッド下端 +20px
- ★お名前・電話・メール・ご要望は **すべて空欄のまま**（機械確認 `inputs empty: true`）。**予約は送信していない**（予約件数 14 → 14）

## 7. WebP寸法（正式6枚・すべて4x原本からの縮小。拡大0）

| ファイル | WebP | 想定表示幅 | 実密度 |
|---|---|---|---|
| salon-ui-memo-phone.webp | 1040×1726 | 520px | 2.00x |
| salon-ui-ai-review.webp | 1360×1581 | 680px | 2.00x |
| salon-ui-ai-review-unchecked.webp | 1360×1581 | 680px | 2.00x |
| salon-ui-briefing.webp | 1360×1128 | 680px（620〜680で使用） | 2.00x |
| salon-public-shop.webp | 1040×1803 | 520px（スマホ枠） | 2.00x |
| salon-public-booking.webp | 1040×2052 | 520px（スマホ枠） | 2.00x |

品質: **WebP q82**。想定表示幅での1倍描画を目視確認し、文字の滲みが無いため品質を上げる必要はなかった。

## 8. 各容量 ／ 9. 合計容量

| ファイル | 容量 | SHA-256（先頭16） |
|---|---|---|
| salon-ui-memo-phone.webp | 45,032 B（44.0KB） | `df4fcfe534da08c7` |
| salon-ui-ai-review.webp | 55,546 B（54.2KB） | `b155678db906dfa5` |
| salon-ui-ai-review-unchecked.webp | 55,340 B（54.0KB） | `e4ba664a389a84dc` |
| salon-ui-briefing.webp | 52,632 B（51.4KB） | `f4108de4b640be16` |
| salon-public-shop.webp | 71,466 B（69.8KB） | `3e814438221abb84` |
| salon-public-booking.webp | 42,968 B（42.0KB） | `bab69c7628a17a70` |
| **合計** | **322,984 B（315.4KB）** | |

magic bytes: 6枚とも `RIFF…WEBP`。1C-1仕様の合計上限 1.4MB に対し **315KB**。

## 10. QUALITY GATE（A/B/C）

| # | ファイル | 判定 | 根拠 |
|---|---|---|---|
| 1 | memo-phone | **A** | 1C-2Aと同一。保存前の状態・補足文まで可読。他UIの写り込み0 |
| 2 | ai-review | **A** | 同上。更新候補「以前／今回」まで写り、人が選ぶ設計が1枚で伝わる |
| 3 | ai-review-unchecked | **A** | #2と同寸。差分がチェック1つだけで一目で分かる |
| 4 | briefing | **A** | 3ブロック全部。顧客カルテ経由（前回の会話あり） |
| 5 | public-shop | **A** | §11 |
| 6 | public-booking | **A** | §12 |

**C判定は0枚。STOP条件に該当しない。**

## 11. 公開店舗ページの評価

| 観点 | 評価 |
|---|---|
| 何の画面か直感的に分かるか | ヒーロー写真の上に店名、直下に紹介文と大きな［WEBで予約する］。**「店舗ページまで作られる」が一目で分かる** |
| 写真素材込みで魅力的か | Warm Modern（白・ベージュ・木・自然光）の店内写真が全幅。**写真なし版（1C-1時点の評価B）から明確に向上** |
| 文字可読性（表示520px） | 店名・アクセス・紹介文・CTAすべて可読 |
| 高解像度でのボケ | 4x原本→1040px。2倍密度。ボケなし |
| 不自然な写り込み | ページ最上部からの領域切り抜きのため、隣接要素の半端な写り込みなし |
| 注意点 | 紹介文に「（このページは営業デモ用の架空店舗です）」、アクセスに「（デモ表示）」が写る。**架空であることの明示になるため、むしろ肯定的**。HP側で隠す加工はしない |

## 12. WEB予約画面の評価

| 観点 | 評価 |
|---|---|
| 「予約が簡単そう」か | 番号付き3ステップ（担当→日付→時間）、日付チップと時間チップ、選択済み（8/24・14:00）が濃色で明快。**簡単そうに見える** |
| 「24時間WEB予約受付」の実画面証拠として | 日付・時間をお客様が自分で選ぶ画面＝WEB予約。**「AIが予約を取る」と誤認させる要素は画面内に無い**（AIの文言・アイコンなし） |
| メニュー・CTAが画面外 | 「1 メニューを選ぶ」「予約内容を確認する」は範囲外。**全ステップ版は参考画像 `salon-public-booking-full-ref`（375×1243 css）として別保存**。Phase 1Dで「長い1枚」が必要なら差し替え可 |
| 個人情報 | 入力欄は範囲外かつ空欄。予約未送信 |
| 日付チップの右端が途中で切れる | スマホの横スクロールUIそのもの。実画面らしさとして許容（加工しない） |

## 13. PII検査（正式6枚すべて）

| 禁止項目 | 1 | 2 | 3 | 4 | 5 | 6 |
|---|---|---|---|---|---|---|
| 実在人物名 | − | − | − | − | − | − |
| 実在電話番号 | − | − | − | − | − | − |
| 実在メール | − | − | − | − | − | − |
| password / PIN | − | − | − | − | − | − |
| API key / credential | − | − | − | − | − | − |
| 内部ID | − | − | − | − | − | − |
| 本番URL | − | − | − | − | − | − |
| ブラウザ保存情報・URL・タブ | − | − | − | − | − | − |
| LUMINA HAIR（旧demo） | − | − | − | − | − | − |
| 未提供機能（LINE配信・音声入力・AI対話予約 等） | − | − | − | − | − | − |
| 金額・売上分析値 | − | − | − | − | − | − |

（− ＝ 写っていない）

写っている固有名詞（すべて架空・許可範囲）

| 画像 | 固有名詞 |
|---|---|
| memo-phone | 十和田 圭 様（承認済み架空顧客）／佐藤 花子（架空スタッフ）／カット |
| ai-review / -unchecked / briefing | 人物名なし |
| public-shop | SALON SOLEIL／「地下鉄 大通駅から徒歩5分（デモ表示）」／紹介文（架空店舗と明記） |
| public-booking | 佐藤 花子（架空スタッフ）／8/23〜8/27／10:00〜19:00 |

★public-booking のメニュー価格（¥5,500等）は範囲外。public-shop も価格は範囲外。

### Website実装時の必須注記（代表承認済み・Phase 1Dの受入条件）

```text
※掲載画面はデモ用の架空データです。
```

memo-phone（架空顧客名あり）の近傍には**必ず**表示する。6枚をまとめて掲載する場合はセクション単位で1回以上。

## 14. 禁止表現検査（`SALON_SALES_TERMS_SUMMARY_V1.md` §18 と照合）

| 観点 | 結果 |
|---|---|
| 画像内に「AIが予約」「AI受付」「24時間AI」等の文言 | **なし** |
| 画像内に「AIが自動保存／自動記録」の文言 | **なし**（「確認して登録した内容だけが顧客カルテに残ります」「確認済みの記録のみ使用・保存されません」と**逆の主張**が写っている） |
| 画像内に「使い放題」「売上分析」「離脱予測」 | なし |
| 画像内に価格 | なし（booking の価格行は範囲外） |
| 未提供機能（LINE配信・外部予約自動連携・AI対話予約） | なし |
| alt案（§15）に禁止表現 | なし（下記） |

## 15. alt 確定案（6枚）

| ファイル | alt |
|---|---|
| salon-ui-memo-phone.webp | 接客後にスマートフォンから短いメモを入力するSmart Labo Salonの接客メモ画面 |
| salon-ui-ai-review.webp | 接客メモをAIが整理し、スタッフが内容を確認して登録する項目を選ぶ画面 |
| salon-ui-ai-review-unchecked.webp | AIが整理した項目のうち、登録しない項目のチェックを外している画面 |
| salon-ui-briefing.webp | 確認済みの顧客記録から、前回のポイント・前回の会話・今回の提案候補をまとめた接客準備の画面 |
| salon-public-shop.webp | スマートフォンで表示した店舗ページ。店内写真と店舗名、WEB予約のボタンが並ぶ |
| salon-public-booking.webp | 店舗ページからのWEB予約画面。担当・日付・時間を選ぶ途中の状態 |

★人物名・店名を含めない。「AIが自動保存」「AIが予約」を含めない。SEO語の詰め込みなし。

## 16. demo DB reset 結果

撮影専用DB `salon-capture-demo.sqlite` を削除 → `seedDemo.js` で再seed。

| 確認 | reset前（撮影後） | reset後 | 期待 | 判定 |
|---|---|---|---|---|
| tenants | 1 | 1 | 1 | ○ |
| visits / visit_notes | 10 / 10 | 10 / 10 | 10 / 10 | ○ |
| ai_extractions | 1 | 1 | 1 | ○ |
| customer_memories | 4 | 4 | 4 | ○ |
| reservations | 14 | 14 | 14 | ○（撮影で予約は送信していない） |
| customers | 7 | 7 | 7 | ○ |
| shop_media | 7（撮影用に投入） | **0** | 0 | ○（写真はseedに含まれない。仕様どおり） |
| 本日の主役予約 | — | 十和田 圭 `reserved` | あり | ○ |
| 十和田 Memory | — | 4件 | 4件 | ○ |
| integrity_check | — | ok | ok | ○ |
| foreign_key_check | — | 0 | 0 | ○ |

商談用DBへの影響

| ファイル | 作業前 | 作業後 |
|---|---|---|
| salon-demo.sqlite | 2026-08-23 07:56:12 | **07:56:12（変更なし）** |
| salon-demo.sqlite-shm | 07:56:29 | **07:56:29（変更なし）** |
| salon-demo.sqlite-wal | 07:59:26 | **07:59:26（変更なし）** |

★補足: 撮影用に投入した写真の実ファイル7個が `salon-app/data/media/`（Git管理外）に残る（撮影用tenantの孤児ファイル・約0.5MB）。製品動作・商談・Gitに影響なし。不要なら削除可。
★撮影用DBのパスワードは本セッション内の一時ファイルのみ。docs・ログ・画像に出していない。

## 17. Website 変更ファイル

| 対象 | 変更 |
|---|---|
| `WEBSITE/assets/images/salon/*.webp` | **6ファイル新規**（§5） |
| `WEBSITE/index.html` | 0 |
| `WEBSITE/assets/css/*.css`（renewal.css 含む） | 0 |
| `WEBSITE/` のJS・その他 | 0 |
| `docs/website/SMART_LABO_WEBSITE_P32_3_1C2B_ASSET_FINAL_RESULTS.md` | 本書（新規） |

※指示書の「`WEBSITE/renewal.css`」は実際には **`WEBSITE/assets/css/renewal.css`**（index.html 53行目でlink）。参照のみ・未変更。

## 18. Salon 変更0の証明

```text
git -C C:\Users\user\Desktop\smartlabo-salon status --porcelain   → 0行
git -C C:\Users\user\Desktop\smartlabo-salon rev-parse HEAD        → 6304299（不変）
```

`data/` と `.env` は `.gitignore` 対象。撮影用DBと孤児mediaファイルは `data/` 配下のみ。

## 19. commit ／ 20. push ／ 21. git status ／ 22. master

本書末尾「23. 終了時のGit状態」に実測値を記録。

- commit 対象: `WEBSITE/assets/images/salon/*.webp`（6）＋ 本書（1）のみ
- push 先: `origin/feature/website-renewal` のみ
- master: **push・merge・commit いずれも0**

## 23. 本番変更0

本番VPS接続0 ／ deploy 0 ／ DNS 0 ／ Stripe 0 ／ 問い合わせ送信0 ／ 申込送信0 ／ 外部AI・メール通信0 ／ 本番DB 0。

## 24. Phase 1D へ進めるか／ストーリー成立の評価

### 6枚で「集客 → WEB予約 → 接客 → 記録 → AI整理 → スタッフ確認 → 次回来店」が成立するか

| 段階 | 画像 | 成立 | 厳しめの所見 |
|---|---|---|---|
| 集客（店舗を知る・店舗ページ） | public-shop | **○** | 写真・店名・CTAで「店舗ページまで作られる」が伝わる |
| WEB予約 | public-booking | **○** | 担当・日付・時間を選ぶ画面。24時間WEB予約の実画面 |
| 接客→記録 | memo-phone | **○** | 受付済み（本日の施術）＋3行メモ＋保存前 |
| AI整理 | ai-review | **○** | 3項目に分類。更新候補の「以前／今回」まで |
| スタッフ確認 | ai-review-unchecked | **○** | 人が外す＝AIが勝手に登録しない |
| 次回来店 | briefing | **○** | 前回のポイント／前回の会話／今回の提案候補 |

**結論: 6枚で一連のストーリーは成立する。**

ただし、厳しく見て **Phase 1D の実装で埋めるべき2つの"継ぎ目"** がある。

1. **「WEB予約 → 来店」の継ぎ目**: booking の次が memo-phone（受付済み状態）なので、「予約したお客様が来店し受付された」ことは画像ではなく**文章・図解で補う**必要がある。
   必要になった場合のみ `home-today`（本日の予約一覧）を追加取得する（今回は撮らない方針どおり）。
2. **「接客準備 → 次の予約」の循環**: briefing の後に「また予約が入る」を示す画像は無い。Webサイト上は矢印・文言で **循環** を表現する。

### 進行可否

**Phase 1D（TOP実装）へ進める状態。** 素材・alt・注記条件・容量すべて確定済み。
ただし指示どおり、**本工程では実装に着手せずここでSTOP**する。

Phase 1D 着手時の前提（本書から引き継ぐ条件）
- 画像は `WEBSITE/assets/images/salon/` の6枚のみ使用（原本・参考画像は使わない）
- 「※掲載画面はデモ用の架空データです。」を必ず表示（memo-phone近傍は必須）
- alt は §15 の文言
- 表示幅は memo-phone/public 2枚 ≦520px、ai-review 2枚 ≦680px、briefing 620〜680px（拡大表示しない）
- 「AIが予約」「AIが自動保存」「24時間AI受付」等の文言を使わない（§14）
- 追加撮影（home / step / karte-memory / karte-history）は**実装で本当に必要になった場合のみ**

---

## 25. 終了時のGit状態（実測）

```text
branch : feature/website-renewal
commit : 本書＋ WEBSITE/assets/images/salon/*.webp（6）の1commit（= 本書を含むHEAD）
push   : origin/feature/website-renewal へpush（HEAD = origin）
status : 0行（clean）
master : d3c6054（未変更・push/merge 0）
```
★HEADハッシュは `git log -1 --oneline docs/website/SMART_LABO_WEBSITE_P32_3_1C2B_ASSET_FINAL_RESULTS.md` で確認できる（本書自身のcommitのため本文には固定値を書かない）。

## 26. 変更履歴

| 版 | 日付 | 内容 |
|---|---|---|
| v1.0 | 2026-08-23 | 制定（P32-3 Phase 1C-2B・正式6枚を確定し `WEBSITE/assets/images/salon/` へ配置。公開店舗ページ／WEB予約を新規取得。TOP実装は未着手） |
