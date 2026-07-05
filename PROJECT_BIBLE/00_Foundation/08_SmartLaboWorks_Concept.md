# 08. SmartLaboWorks Concept — Smart Labo Works のコンセプト

## プロダクトコンセプト

> **「会社を動かすAI。」**

Smart Labo Works は、単なる業務効率化ツールではなく、社長が本来の仕事に集中できるよう、会社の日々の運営そのものを支える「AI経営プラットフォーム」です。

---

## なぜ「会社を動かすAI」なのか

一般的な業務ツールは「人の作業を助ける」ことを目的としますが、Smart Labo Works は一歩踏み込み、以下を目指します。

- 定型的な判断・作業をAIが「代行」する
- 社長は、AIが出した結果を確認し、最終的な意思決定に集中する
- 部門・業務ごとに分断されていた情報を、AIが横断的につなぐ

これは [03_Mission.md](03_Mission.md) の「社長が本来の仕事に集中できる世界をつくる」というミッションと、[04_Vision.md](04_Vision.md) の「日本で最も信頼される中小企業向けAI経営プラットフォーム」というビジョンを直接体現するものです。

---

## プロダクト構成の考え方

Smart Labo Works は単一の巨大なシステムではなく、業務領域ごとの「AIモジュール」の集合体として設計します。

- 各モジュールは独立して価値を提供できること(いきなり全部入りを目指さない)
- モジュール間はデータ・情報を横断して連携できること
- 導入企業は必要なモジュールから始められること

現在具体化している領域の例:
- 不動産関連業務支援(査定・契約書作成など)
- コンシェルジュ型AI対応(MIRAIEコンシェルジュ 等)
- **Company Brain**(会社の知識を理解するAI基盤。詳細は次章)

具体的な開発状況・技術構成は [SmartLaboWorks/README.md](../../SmartLaboWorks/README.md) を参照してください。プロダクトのリリース履歴は [09_Product_History.md](09_Product_History.md) を参照してください。

---

## Company Brain — Smart Labo Works 最大の差別化要素

> Smart Labo Works は単なるAIチャットではありません。会社の知識をAIが理解し、社員が必要な情報へ**「聞くだけ」**でたどり着ける **Company Brain** を実現します。

Company Brain は、他の業務モジュール(不動産関連業務支援、MIRAIEコンシェルジュ 等)と並列の一機能ではなく、Smart Labo Works というプラットフォーム全体を支える**中核基盤**です。一般的な「マニュアル検索」「社内Wiki」「FAQボット」とは、以下の点で明確に異なります。

| | 一般的なマニュアル検索・チャットボット | Company Brain |
|---|---|---|
| 対象 | 特定のFAQ・限定された文書のみ | 社内規程・業務マニュアル・営業マニュアル・FAQ・教育資料・テンプレート・契約フロー・社内ルール・ベテラン社員のノウハウまで、会社に存在するあらゆる知識 |
| 応答 | キーワードに一致した文書を提示するだけ | 必要な情報・次にやるべきこと・関連資料まで案内する |
| 位置づけ | 補助的なヘルプ機能 | 会社の頭脳そのもの(コアプラットフォーム) |

**キャッチコピー:** 「探す時間を、考える時間へ。」— 社員がマニュアルを「探す」時間をなくし、本来の「考える」「判断する」業務に集中できる状態をつくることが目的です。これは [03_Mission.md](03_Mission.md) の「社長が本来の仕事に集中できる世界をつくる」という思想を、社員全体に広げたものでもあります。

具体的なコピー・UI仕様・利用イメージ・画面設計は [PROMPTS/DESIGN/CompanyBrain.md](../../PROMPTS/DESIGN/CompanyBrain.md) を正とし、ホームページ・Dashboard・営業資料・パンフレット・デモ画面のすべてでこのコンセプトを統一してください。

---

## プロダクト開発時の判断基準

新機能を検討する際は、以下を自問してください。

1. これは「社長の本来の仕事」を取り戻すことに直結しているか?単なる便利機能になっていないか?
2. シンプルさを損なっていないか?([05_Value.md](05_Value.md))
3. 毎日使いたくなる体験になっているか?機能の数ではなく体験の質で勝負できているか?([06_Philosophy.md](06_Philosophy.md))
4. UIはApple / Notion / Linear / Stripeの思想に沿っているか?([20_UI_UX_Rules.md](../20_UI_UX_Rules.md))

---

## 関連ドキュメント

- [SmartLaboWorks/README.md](../../SmartLaboWorks/README.md)
- [06_Philosophy.md](06_Philosophy.md)
- [09_Product_History.md](09_Product_History.md)
- [10_Future_Roadmap.md](10_Future_Roadmap.md)
- [10_Development_Rules.md](../10_Development_Rules.md)
- [PROMPTS/DESIGN/CompanyBrain.md](../../PROMPTS/DESIGN/CompanyBrain.md) — Company Brainのコピー・UI・画面設計の詳細

---

## 変更履歴

| バージョン | 日付 | 変更者 | 変更内容 |
|---|---|---|---|
| v1.0 | 2026-07-03 | Smart Labo | 初版作成 |
| v2.0 | 2026-07-03 | Smart Labo | 00_Foundation構成への移行に伴い旧05_Product.mdから移動・改題。新Mission/Visionに合わせて全面改訂 |
| **v3.0** | 2026-07-05 | Claude Code(CEO指示による) | **「Company Brain」をSmart Labo Works最大の差別化要素・中核基盤として正式に追加。** 一般的なマニュアル検索・チャットボットとの違いを比較表で明記し、キャッチコピー「探す時間を、考える時間へ。」を制定。詳細仕様は新設の[PROMPTS/DESIGN/CompanyBrain.md](../../PROMPTS/DESIGN/CompanyBrain.md)を参照する構成にした |

*最終更新: 2026-07-05*
