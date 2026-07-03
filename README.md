# SmartLabo

**株式会社スマートラボ 開発基盤リポジトリ / Company Project Bible**

このリポジトリは、株式会社スマートラボ(Smart Labo Inc.)の思想・設計・ブランド・開発ルールをすべて一元管理する、**会社のOS・憲法・知識ベース**です。

ChatGPT・Claude Code・Codex・将来のAI・将来の社員・外部パートナー全員が、同じ思想・同じ設計・同じブランド基準を参照しながら仕事ができることを目的としています。

> **[PROJECT_BIBLE/](PROJECT_BIBLE/README.md) は、株式会社スマートラボに関する唯一の正しい設計書(Single Source of Truth)です。最新版は [PROJECT_BIBLE/README.md](PROJECT_BIBLE/README.md) に記載のVersionを正としてください。** 会社の理念・ルール・ブランドについて他の情報源と矛盾がある場合、常に PROJECT_BIBLE の内容が優先されます。

**Mission:** AIで、社長が本来の仕事に集中できる世界をつくる。
**Vision:** 日本で最も信頼される、中小企業向けAI経営プラットフォームになる。
**Catchphrase:** 「会社を動かすAI。」

---

## このリポジトリの目的

- 会社の理念(Mission / Vision / Value)を明文化し、誰が読んでも同じ理解に到達できるようにする
- プロダクト(Smart Labo Works ほか)の設計思想・開発ルール・UI/UXルールを一箇所に集約する
- AIツール(Claude / Codex / ChatGPT)がプロジェクトの文脈を正しく理解できるよう、プロンプトとルールを整備する
- ブランド・法務・営業・財務・議事録など、会社運営に必要な情報を体系的に保管する
- 将来100名規模の開発組織になっても破綻しない、スケーラブルなドキュメント構造を維持する

---

## フォルダ構成

```
SmartLabo/
│
├── PROJECT_BIBLE/      ← 会社の設計図(理念・ルール・ロードマップの中枢)
├── DOCUMENT/           ← 社内文書(事業・財務・営業関連書類)
│   ├── BUSINESS/       ← 事業計画・提携資料
│   ├── FINANCE/        ← 融資・補助金・資金計画
│   └── SALES/          ← 営業資料・提案書
├── DESIGN/             ← デザインシステム・素材・Figma等の設計データ
├── WEBSITE/            ← コーポレートサイト関連
├── SmartLaboWorks/     ← 主力プロダクト「Smart Labo Works」の開発
├── PROMPTS/            ← AI用プロンプト集
│   ├── CLAUDE/         ← Claude Code / Claude向けプロンプト・設定
│   ├── CODEX/          ← Codex向けプロンプト・設定
│   └── CHATGPT/        ← ChatGPT向けプロンプト・設定
├── BRAND/              ← ロゴ・名刺・カラー・デザインガイドライン
├── LEGAL/              ← 定款・契約書・利用規約・プライバシーポリシー
├── MEETING/            ← 打ち合わせ議事録
└── ARCHIVE/            ← 過去データ・廃止済み資料
```

各フォルダには、その中身の目的・利用ルール・Version管理・更新ルール・Git運用ルールを記載した `README.md` を配置しています。作業を始める前に、必ず該当フォルダの README を読んでください。

---

## はじめに読むべきドキュメント

新しくこのプロジェクトに関わる人(人間・AIエージェント問わず)は、以下の順番で読み進めてください。

1. [PROJECT_BIBLE/README.md](PROJECT_BIBLE/README.md) — 会社のOS・憲法の使い方
2. [PROJECT_BIBLE/00_Foundation/01_Company_Story.md](PROJECT_BIBLE/00_Foundation/01_Company_Story.md) — 会社のストーリー
3. [PROJECT_BIBLE/00_Foundation/02_Founder_Message.md](PROJECT_BIBLE/00_Foundation/02_Founder_Message.md) — 創業者からのメッセージ
4. [PROJECT_BIBLE/00_Foundation/03_Mission.md](PROJECT_BIBLE/00_Foundation/03_Mission.md) — ミッション
5. [PROJECT_BIBLE/00_Foundation/04_Vision.md](PROJECT_BIBLE/00_Foundation/04_Vision.md) — ビジョン
6. [PROJECT_BIBLE/00_Foundation/05_Value.md](PROJECT_BIBLE/00_Foundation/05_Value.md) — バリュー
7. [PROJECT_BIBLE/00_Foundation/06_Philosophy.md](PROJECT_BIBLE/00_Foundation/06_Philosophy.md) — 会社を貫く哲学
8. [PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md](PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md) — ブランドアイデンティティ
9. [PROJECT_BIBLE/00_Foundation/08_SmartLaboWorks_Concept.md](PROJECT_BIBLE/00_Foundation/08_SmartLaboWorks_Concept.md) — プロダクト「Smart Labo Works」のコンセプト
10. [PROJECT_BIBLE/10_Development_Rules.md](PROJECT_BIBLE/10_Development_Rules.md) — 開発ルール
11. [PROJECT_BIBLE/20_UI_UX_Rules.md](PROJECT_BIBLE/20_UI_UX_Rules.md) — UI/UXルール
12. [PROJECT_BIBLE/30_AI_Rules.md](PROJECT_BIBLE/30_AI_Rules.md) — AI利用ルール(Claude / Codex / ChatGPT共通)

---

## 運用の基本方針

### Markdownを基本とする

すべてのドキュメントは Markdown(`.md`)形式で記述します。Word や Google ドキュメントなど外部形式に依存しないことで、Git管理・差分確認・AIによる読み込みを容易にします。

### Version管理

- すべての変更は Git でバージョン管理します。
- 重要な意思決定を伴う変更(理念・ルールの変更など)は、コミットメッセージに変更理由を明記してください。
- PROJECT_BIBLE配下の各ドキュメントは、末尾に「変更履歴」テーブル(バージョン/日付/変更者/変更内容)を持ちます。変更時は必ず追記してください。
- PROJECT_BIBLE全体としての重要な変更は [PROJECT_BIBLE/CHANGELOG.md](PROJECT_BIBLE/CHANGELOG.md) に集約して記録します。

### 更新ルール

- ドキュメントの内容に変更が生じた場合は、関連する README・上位ドキュメントとの整合性を必ず確認してください。
- 大きな方針転換(Mission / Vision / Value の変更など)は、経営層の承認を得てから更新してください。
- 誤字脱字・軽微な修正は誰でも直接更新して構いません。

### Git運用ルール

- ブランチは `main` を正としてください。
- 機能追加・大きな編集は `feature/xxx` のようなブランチを切ってから `main` にマージすることを推奨します。
- コミットメッセージは日本語・英語どちらでも構いませんが、変更内容が一目でわかるようにしてください(例: `feat: ミッションステートメントを更新`)。
- 機密情報(契約書の個人情報、APIキー、パスワードなど)はリポジトリに含めないでください。`LEGAL/` に契約書を保管する場合は、公開範囲に注意してください。

---

## Smart Labo Works について

株式会社スマートラボの主力プロダクトです。開発思想の軸は次の一言に集約されます。

> **「会社を動かすAI。」**

詳細は [PROJECT_BIBLE/00_Foundation/08_SmartLaboWorks_Concept.md](PROJECT_BIBLE/00_Foundation/08_SmartLaboWorks_Concept.md) および [SmartLaboWorks/README.md](SmartLaboWorks/README.md) を参照してください。

---

## デザイン思想: Enterprise AI Platform

Smart Laboは「AIを売る会社」ではなく「会社を進化させる会社」です。目指すのは世界トップクラスのBtoB SaaSブランドと肩を並べる完成度で、以下のブランドを思想的な参照点とします(コピーではなく思想の継承)。

- **Microsoft** — エンタープライズが安心して任せられる信頼感
- **OpenAI** — 先進性を静かな自信として見せるトーン
- **AWS** — スケールと堅牢さを感じさせるインフラ的な安心感
- **Palantir** — 重厚で洗練されたデータ表現
- **Stripe** — ドキュメントとタイポグラフィの美しさ
- **Datadog** — リアルタイムデータを上品かつ迫力を持って見せる表現
- **Snowflake** — クリーンなブルー基調のエンタープライズサイト
- **Vercel** — 削ぎ落とされたタイポグラフィと上品なグラデーション

共通するキーワードは「Premium・Enterprise・Professional・Minimal・Modern・Trust・Innovation・AI First」です。使用カラーはNavy・White・Smart Blue・Light Gray(アクセントはSmart Blueのみ)。詳細は [PROJECT_BIBLE/20_UI_UX_Rules.md](PROJECT_BIBLE/20_UI_UX_Rules.md) を参照してください。

---

## お問い合わせ・管理者

このリポジトリの管理・更新方針に関する質問は、経営層または PROJECT_BIBLE 管理者に確認してください。

---

*最終更新: 2026-07-03*
