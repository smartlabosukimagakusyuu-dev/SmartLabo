# 63. Post-Launch Roadmap — 公開後ロードマップ

このドキュメントは、Smart Labo Worksホームページ（`WEBSITE/` + `xserver-form/`）の
正式公開後に着手する候補を、CEO指定の優先順位で整理したものです。
**いずれも現時点では未着手・未スコープであり、着手時期・実装範囲は別途Taskとして
CEO承認を得てから決定します。** 本ドキュメントは「やることが決まったリスト」ではなく
「優先順位の合意」を記録するものです。

---

## 優先順位一覧

| 優先順位 | 項目 | 現状 | 想定される内容 |
|---|---|---|---|
| ① | AIチャット | `index.html`のチャットウィジェットはデモ回答のみ（[Known Issues](61_Release_Checklist.md)参照） | ホームページのチャットを、実際のSmart AI Router(smartlabo-works)へ接続する。要：smartlabo-works側の公開API化(下記⑤と関連) |
| ② | Pricing Manager | 設計のみ完了（[12_Pricing_Philosophy.md](12_Pricing_Philosophy.md)参照）、コード未実装 | Company OS側に、営業担当が利用規模→基本プラン→利用モジュール→追加オプションを選択すると料金表・見積書・契約書・営業資料を自動生成する機能を実装。契約書自動生成は法的正確性の観点から特に慎重な設計が必要 |
| ③ | CRM | smartlabo-works内には実装済み（社内ログイン後の画面） | ホームページの問い合わせフォーム送信内容を、smartlabo-works側のCRMへ自動登録する連携。現状は手動転記が前提 |
| ④ | Company Brain | smartlabo-works内には実装済み（社内ログイン後の画面） | ホームページのチャット・FAQが、実際のCompany Brainナレッジを参照して回答する連携（①と密接に関連） |
| ⑤ | API | 未実装 | smartlabo-worksの機能（Smart AI Router・Company Brain等）を外部から呼び出せる公開APIの整備。①③④の前提となる基盤 |
| ⑥ | app.smartlaboworks.com | 未実装（現状`WEBSITE/app.html`はデモ、smartlabo-works本体は非公開） | smartlabo-works本体を`app.smartlaboworks.com`等のサブドメインで一般公開し、`WEBSITE/app.html`のデモ表示から実製品への切替を検討 |
| ⑦ | Customer Portal | 未実装 | 導入企業が自社のCompany Brain・CRM・利用状況等を確認できる顧客向けポータル |
| ⑧ | 管理画面 | 未実装 | Pricing Manager・問い合わせ管理・顧客管理等を横断的に扱う社内向け管理画面（Company OS内の新機能として検討） |

---

## 位置づけの整理

- ①〜④は相互に依存関係があります（①④は⑤の公開API化が前提、③はCRMとの連携が前提）。着手順序は優先順位どおりとは限らず、依存関係を踏まえた技術的な検討が必要です。
- ⑥〜⑧は、smartlabo-works本体を「デモ」から「本番運用できる製品」へ引き上げる、より大きな意思決定を伴います。着手時期・体制について、別途CEOとの議論が必要です。
- いずれの項目も、着手前には[11_Development_Principles.md](11_Development_Principles.md)の開発前ルール（PROJECT_BIBLE・Brand Bible・Design Bible確認、CEOレビュー原則）に従います。

---

## 変更履歴

| バージョン | 日付 | 変更者 | 変更内容 |
|---|---|---|---|
| v1.0 | 2026-07-14 | Claude Code(CEO指示による) | 新規作成。「Release Candidate 2 最終仕上げフェーズ」Task8の指示に基づき、公開後ロードマップをCEO指定の優先順位(①AIチャット②Pricing Manager③CRM④Company Brain⑤API⑥app.smartlaboworks.com⑦Customer Portal⑧管理画面)で整理。いずれも未着手・未スコープであり、着手時期・実装範囲は別途Task化してCEO承認を得る方針を明記 |

*最終更新: 2026-07-14*
