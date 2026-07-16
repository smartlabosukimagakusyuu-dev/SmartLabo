# 13. Smart Labo Platform Architecture — 全体アーキテクチャ

このドキュメントは、Smart Labo Works全体のシステム構成を会社として正式に記録するものです。2026-07-16のCEO追加指示「Smart Labo Platform v1.0 全体アーキテクチャ設計」により新設しました。**銀行・パートナー・投資家・採用候補者への説明、および社内設計書として利用する正式資料**です。

> **ステータス：CEO承認済み（2026-07-16）。Version1.0正式版として採用。**

---

## コンセプト

> **「会社を動かすAI。」**

Smart Labo Worksは単なるAIチャットではなく、**企業全体を支援するAI Platform**です。これは[03_Mission.md](00_Foundation/03_Mission.md)の「社長が本来の仕事に集中できる世界をつくる」というミッション、[04_Vision.md](00_Foundation/04_Vision.md)の「日本で最も信頼される中小企業向けAI経営プラットフォームになる」というビジョン、[08_SmartLaboWorks_Concept.md](00_Foundation/08_SmartLaboWorks_Concept.md)の「業務領域ごとのAIモジュールの集合体」という設計思想を、技術アーキテクチャとして具体化したものです。

---

## Git構成（会社として正式に統一）

Smart Labo Works関連のリポジトリ／プロジェクトは、以下の3つに役割を統一します（2026-07-16 CEO決定）。

| リポジトリ | 役割 |
|---|---|
| `smartlabo-website` | ホームページ（GitHub Pages） |
| `smartlabo-works` | 製品本体（ログイン後のSaaS。CRM・Company Brain・Template Engine・営業メール／査定コメント／物件紹介文生成等） |
| `smartlabo-platform` | AI・API・認証・Company Brain等の共通基盤。Smart AI Router・Public AI Gatewayを起点に将来すべてのAI機能をここへ統合する |

---

## 全体構成（6レイヤー）

| Layer | 名称 | 役割 | 現状 |
|---|---|---|---|
| Layer1 | 利用者 | ホームページ訪問者・導入企業・管理者 | — |
| Layer2 | smartlabo-website | 公式ホームページ（`smartlaboworks.com`）。Public AI（Smart Concierge AI）とお問い合わせ（`form.smartlaboworks.com`）を持つ | Homepage: 公開済み／Public AI: Sprint1実装済み（本番未接続） |
| Layer3 | smartlabo-platform | Smart Labo Works全体のバックエンド基盤（`api.smartlaboworks.com`）。Smart AI Routerを内包 | Sprint1（Public AI Gatewayのみ）実装済み |
| Layer4 | AI Providers | OpenAI・Claude・Gemini。将来Provider追加可能 | OpenAI実装済み／Claude・Geminiは雛形 |
| Layer5 | 共通サービス | Company Brain・CRM・Meeting AI・OCR・Template Engine・Knowledge Base・AI Log・Partner Program | 現状`smartlabo-works`側に個別実装済み。統合はVersion2.0 |
| Layer6 | Database | ユーザー・Company Brain・CRM・Meeting・Knowledge・ログの永続化 | `smartlabo-works`側は一部実装済み。`smartlabo-platform`側は将来実装 |

技術的な構成図・データフロー図・AI Router構成図の詳細は、`smartlabo-platform/ARCHITECTURE.md`を正とする（本書は会社公式記録としての要約）。

---

## Public AIとCompany Brainの違い

Smart Labo Worksには、性質の異なる2つのAIが存在します。混同しないよう、会社として正式に区別します。

| | **Public AI（Smart Concierge AI）** | **Company Brain** |
|---|---|---|
| 利用者 | ホームページ訪問者（未ログイン・誰でも） | 導入企業の社員（ログイン後のみ） |
| 役割 | 営業担当AI。会社説明・製品説明・導入相談・料金案内・Partner Program・資料請求・デモ予約へ自然に繋げる相談相手 | 社内ナレッジAI。「探す時間を、考える時間へ。」（[08_SmartLaboWorks_Concept.md](00_Foundation/08_SmartLaboWorks_Concept.md)参照） |
| 参照する知識 | Public Knowledge Base（公開情報のみ：会社概要・ビジョン・製品説明・導入プラン・Partner Program・セキュリティ・導入の流れ・FAQ・お問い合わせ） | 各導入企業が登録した社内ナレッジ（企業ごとに分離） |
| Company Brainへのアクセス | **アクセスしません**（別サーバー・別知識ベースのため、構造上ありえない） | — |

Public AIは「質問に答えること」自体をゴールとせず、無理な営業をせず信頼できる相談相手として会話しながら、各種案内へ自然に繋げることを目的とします（2026-07-16 CEO指示）。

---

## 将来ロードマップ

| Version | 内容 |
|---|---|
| **Version1.0**（現在） | ホームページ・Public AI・お問い合わせ |
| **Version2.0** | Company Brain・CRM・Meeting AI・OCRを`smartlabo-platform`へ統合 |
| **Version3.0** | Partner Platform・API公開・Marketplace・他業種展開 |

このロードマップは、[04_Vision.md](00_Foundation/04_Vision.md)「時間軸のイメージ」の第一段階（特定業務特化）→第二段階（統合プラットフォーム）→第三段階（デファクトスタンダード）と対応するものであり、[10_Future_Roadmap.md](00_Foundation/10_Future_Roadmap.md)のPhase構成とも矛盾しません。

---

## セキュリティ方針

APIキーのサーバー側管理・HTTPS・Rate Limit・Bot対策・ログ・タイムアウト・Company Brainとの物理分離・Public Knowledgeとの物理分離を、全レイヤー共通の方針として維持します。詳細は`smartlabo-platform/ARCHITECTURE.md`「セキュリティ設計」、および`smartlabo-works/PUBLIC_AI_GATEWAY_DESIGN.md` §6を参照してください。

---

## 関連ドキュメント

- `smartlabo-platform/ARCHITECTURE.md` — 技術的な構成図・データフロー図・AI Router構成図の詳細（Mermaid図含む）
- `smartlabo-works/PUBLIC_AI_GATEWAY_DESIGN.md` — Public AI Gateway詳細設計（API設計・セキュリティ設計等、CEO承認済み）
- [00_Foundation/08_SmartLaboWorks_Concept.md](00_Foundation/08_SmartLaboWorks_Concept.md) — 商品コンセプト・Company Brainの位置づけ
- [00_Foundation/10_Future_Roadmap.md](00_Foundation/10_Future_Roadmap.md) — 会社全体のロードマップ

---

## 変更履歴

| バージョン | 日付 | 変更者 | 変更内容 |
|---|---|---|---|
| v1.0 | 2026-07-16 | Claude Code(CEO指示による) | 初版作成。Smart Labo Platform全体アーキテクチャを会社の公式記録として新設。Git構成の3リポジトリ統一、6レイヤー構成、Public AIとCompany Brainの違い、将来ロードマップを記録。同日CEO承認によりVersion1.0正式版として採用 |

*最終更新: 2026-07-16*
