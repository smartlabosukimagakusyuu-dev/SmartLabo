# CompanyBrain — Company Brain マスター設計プロンプト

> 使用前に [SmartLabo_Design_Bible.md](SmartLabo_Design_Bible.md) を必ず読み込ませてください。このファイルは、Smart Labo Works**最大の差別化要素**である「Company Brain」の位置づけ・コピー・UI・画面設計を、ホームページ・Dashboard・営業資料・パンフレット・デモ画面**すべて**で統一するための正本です。個別成果物の指示ファイル([Homepage.md](Homepage.md) / [Dashboard.md](Dashboard.md))は、Company Brainに関する部分でこのファイルを参照してください。

---

## 重要な位置づけ(最初に必ず理解すること)

**この機能は単なる「マニュアル検索」ではありません。Smart Labo Worksは『会社の頭脳(Company Brain)』を提供するサービスです。**

Company Brainは、他の業務モジュール(不動産関連業務支援、MIRAIEコンシェルジュ 等)と並列の一機能ではなく、Smart Labo Works というプラットフォーム全体を支える中核基盤です。一般的なチャットボット・FAQボット・社内Wikiの延長として扱わないでください。

### 目的

> Smart Labo Worksは単なるAIチャットではありません。会社の知識をAIが理解し、社員が必要な情報へ「聞くだけ」でたどり着けるCompany Brainを実現します。

---

## コピー(全成果物で統一)

| 要素 | コピー |
|---|---|
| タイトル | **Company Brain** |
| サブタイトル | **会社の知識を、AIへ。** |
| キャッチコピー | **探す時間を、考える時間へ。** |

### 説明文の型

社内規程 / 業務マニュアル / 営業マニュアル / FAQ / 教育資料 / テンプレート / 契約フロー / 社内ルール / ベテラン社員のノウハウ。これらをAIが理解します。社員はマニュアルを**「探す」**必要はありません。**AIへ質問するだけ**で、必要な情報・次にやるべきこと・関連資料まで案内します。

**重要:** AIは「質問に答えるだけ」の存在として描かないでください。必ず「次にやるべきこと」「関連資料」まで案内する、能動的なガイド役として表現してください。これが一般的なFAQボットとの決定的な違いです。

---

## 利用イメージ(3つの利用シナリオ)

ホームページ・営業資料・デモ画面では、以下の3シナリオを**質問→AIの案内の流れ**として視覚的に見せてください(単発の一問一答ではなく、複数ステップの案内フローであることが伝わるように)。

### シナリオ1: 契約更新
「契約更新ってどうする?」
→ 契約更新マニュアルを提示
→ 必要書類を案内
→ 次にやる作業を案内
→ テンプレートを提示

### シナリオ2: 資格手当
「ITパスポート合格したけど資格手当ある?」
→ 資格制度を検索
→ 対象条件を案内
→ 支給金額を案内
→ 申請方法を案内

### シナリオ3: 有給申請
「有給申請の方法は?」
→ 就業規則を検索
→ 申請画面へ誘導

---

## UI方針

- **トーン:** Enterprise SaaSらしい高級感。Company BrainはSmart Labo Worksの**主要機能・主役**として扱い、副次的なセクション・機能として矮小化しないこと。
- **ビジュアルスタイル:** ガラスUI(glassmorphism)。半透明パネル+背景ぼかし(backdrop-filter blur)+繊細なボーダーで、Navy背景に浮かぶ上質なガラス質感を表現する。
- **配色:** Smart Blueで統一する(参照: [PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md](../../PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md) のSmart Blue Family)。
- 禁止事項は [SmartLabo_Design_Bible.md](SmartLabo_Design_Bible.md) の「禁止事項」に準拠(安っぽいロボット・六角形・過度なサイバー演出・ネオン等)。

---

## アイコン

Brain(脳)/ Network(ネットワーク)/ Knowledge(知識)/ AI を連想させる、Company Brain専用のアイコンを用意する。既存の他機能アイコンの流用は不可。線画スタイル(stroke-based)でSmart Blue系統に統一し、他アイコンと同じ意匠言語(角丸バッジ+白抜きアイコン)に揃える。

---

## 展開先ごとの仕様

### ホームページ

セクション構成・コピー・利用イメージの反映方法は [Homepage.md](Homepage.md) の「Company Brain」章を参照。

### Dashboard 左メニュー

サイドバーナビゲーションに「Company Brain」を追加する。詳細は [Dashboard.md](Dashboard.md) を参照。

### Company Brain画面(専用画面、新規)

Dashboardからサイドバーの「Company Brain」を選択した際に開く専用画面。以下の要素で構成する。

- **検索バー** — 画面上部に大きく配置。「質問する、検索する」の両方の使い方ができることが伝わる見た目にする
- **AIチャット** — 検索バーから続けて会話できるチャット形式のインターフェース
- **最近閲覧した資料** — 直近で参照したマニュアル・資料の一覧
- **人気の社内マニュアル** — 以下のカテゴリ別に整理して表示
  - 社内規程
  - 営業マニュアル
  - 教育コンテンツ
  - 動画マニュアル
  - テンプレート
  - FAQ
- **お気に入り** — 社員が個別にブックマークした資料

この画面は現時点では設計仕様のみであり、実装は行っていません(Dashboard Version 0.0、[PROJECT_BIBLE/CURRENT_STATUS.md](../../PROJECT_BIBLE/CURRENT_STATUS.md) を参照)。

---

## 将来ロードマップ

検索対象・機能の拡張計画は [PROJECT_BIBLE/00_Foundation/10_Future_Roadmap.md](../../PROJECT_BIBLE/00_Foundation/10_Future_Roadmap.md) の「Company Brain 拡張ロードマップ」を参照(動画検索・PDF検索・Word/Excel/PowerPoint検索・画像OCR・社内Wiki・議事録検索・音声検索・AI要約・多言語対応)。

---

## UI・チェックリスト

- [ ] Company Brainが副次的な一機能ではなく、Smart Labo Worksの主要機能として扱われているか
- [ ] タイトル・サブタイトル・キャッチコピーが指定通りか
- [ ] 知識ソースの説明が9項目(社内規程/業務マニュアル/営業マニュアル/FAQ/教育資料/テンプレート/契約フロー/社内ルール/ベテラン社員のノウハウ)を反映しているか
- [ ] AIが「答えるだけ」でなく「次にやるべきこと・関連資料」まで案内する存在として描かれているか
- [ ] 3つの利用シナリオ(契約更新/資格手当/有給申請)が複数ステップの案内フローとして見せられているか
- [ ] ガラスUI(glassmorphism)で高級感のあるビジュアルになっているか
- [ ] Smart Blueで統一されているか
- [ ] Brain/Network/Knowledge/AIを連想する専用アイコンが使われているか(既存アイコンの流用でないか)
- [ ] ホームページ・Dashboard・営業資料・パンフレット・デモ画面で、コンセプト・コピーが統一されているか

---

## 関連ドキュメント

- [SmartLabo_Design_Bible.md](SmartLabo_Design_Bible.md)
- [Homepage.md](Homepage.md)
- [Dashboard.md](Dashboard.md)
- [PROJECT_BIBLE/00_Foundation/08_SmartLaboWorks_Concept.md](../../PROJECT_BIBLE/00_Foundation/08_SmartLaboWorks_Concept.md)
- [PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md](../../PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md)
- [PROJECT_BIBLE/00_Foundation/10_Future_Roadmap.md](../../PROJECT_BIBLE/00_Foundation/10_Future_Roadmap.md)

---

## 変更履歴

| バージョン | 日付 | 変更者 | 変更内容 |
|---|---|---|---|
| v1.0 | 2026-07-05 | Claude Code(CEO指示による) | 初版作成。Company BrainをSmart Labo Works最大の差別化要素として正式に位置づけ、コピー・3つの利用シナリオ・UI方針(ガラスUI/Smart Blue)・アイコン方針・Company Brain専用画面の構成・将来ロードマップへの参照をマスタープロンプトとして整理 |

*最終更新: 2026-07-05*
