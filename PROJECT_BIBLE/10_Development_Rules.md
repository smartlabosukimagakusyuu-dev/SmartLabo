# 10. Development Rules — 開発ルール

このドキュメントは、株式会社スマートラボにおけるソフトウェア開発全般の共通ルールを定義します。この内容は [00_Foundation/06_Philosophy.md](00_Foundation/06_Philosophy.md) の8原則(機能より体験・シンプル最優先・毎日使いたくなるUI・保守性・スケーラブル設計・AIファースト・コード品質・会社全体のAI最適化)を、開発の実務ルールに落とし込んだものです。個別プロダクトの詳細な技術仕様は各プロダクトフォルダ(例: [SmartLaboWorks/](../SmartLaboWorks/README.md))内のドキュメントを参照してください。

---

## 基本方針

- **機能より体験を優先する。** 新機能を作る前に「これは体験を良くするか、複雑にするだけか」を問う([00_Foundation/06_Philosophy.md](00_Foundation/06_Philosophy.md) 原則1・2)。
- **保守性を最優先する。** 短期的な速度よりも、後から人(またはAI)が読んで理解・修正できることを優先します(原則4)。
- **過剰実装をしない。** 今必要ない機能・抽象化・将来のための仕組みを先回りして作らない([00_Foundation/05_Value.md](00_Foundation/05_Value.md)「シンプルさ」参照)。
- **スケーラブルだが、今は身の丈にあった設計にする。** 将来100名規模の開発組織になっても破綻しない前提は持ちつつ、今すぐ複雑な仕組みを持ち込まない(原則5)。
- **ドキュメントとコードを乖離させない。** 仕様変更をしたら、関連ドキュメントも同じタイミングで更新する。

---

## AIファースト開発

- 新しい業務・機能を検討する際は、「まず人がやり、後でAIに置き換える」のではなく、「最初からAIが担えないか」を起点に設計する([00_Foundation/06_Philosophy.md](00_Foundation/06_Philosophy.md) 原則6)。
- Claude Code / Codex / ChatGPT などのAIエージェントを使って開発する場合も、人間の開発者と同じ品質基準を適用する。
  - AIに実装を依頼する前に、関連する PROJECT_BIBLE のドキュメントを読み込ませる
  - AIが生成したコードも、人間が書いたコードと同様にレビューする
  - AI固有のルールは [30_AI_Rules.md](30_AI_Rules.md) に従う

---

## コーディング規約の考え方

具体的な言語別スタイルガイドはプロダクトごとに定めますが、共通する原則は以下の通りです。

- 命名は意図が伝わる名前にする(省略しすぎない)
- コメントは「なぜそうしたか」が非自明なときだけ書く。「何をしているか」はコードで語る
- 一つの関数・コンポーネントは一つの責務に留める
- エラーハンドリングは「実際に起こりうるケース」にのみ実装する。起こらないケースへの過剰防御はしない

---

## Git / ブランチ運用

- `master` ブランチを正とします(将来リポジトリを再作成する場合は `main` でも構いませんが、混在させないこと)。
- 機能開発は `feature/xxx`、修正は `fix/xxx` のようなブランチ名を推奨します。
- コミットメッセージは「何を」「なぜ」変更したかがわかるように書きます。
- 機密情報(APIキー、パスワード、個人情報を含む契約書等)は絶対にコミットしません。`.gitignore` を適切に設定してください。
- 作業前後の手順(git status確認→最新版取得→PROJECT_BIBLE確認→作業→動作確認→CURRENT_STATUS更新→CHANGELOG更新→コミット→push→報告)は [60_Editorial_Workflow.md](60_Editorial_Workflow.md) の「GitHub運用フロー」を参照してください。

### コミットメッセージ規約

コミットの種類に応じて、以下のprefixを使用してください。

| Prefix | 用途 |
|---|---|
| `feat:` | 新機能 |
| `docs:` | Project Bible(ドキュメント)の更新 |
| `design:` | デザイン変更 |
| `fix:` | 不具合修正 |
| `refactor:` | リファクタリング |
| `style:` | UI/CSSの調整 |
| `chore:` | フォルダ整理・設定変更 |

---

## テスト・品質保証

- 新機能には、少なくとも主要な利用シナリオ(ゴールデンパス)の動作確認を行う
- UIに関わる変更は、実際に画面で動作確認してからリリースする
- リリース前に、既存機能へのリグレッションがないか確認する
- 「動けばいい」ではなく、コード品質そのものを開発工程の一部として扱う(原則7)

---

## 関連ドキュメント

- [00_Foundation/06_Philosophy.md](00_Foundation/06_Philosophy.md)
- [11_Development_Principles.md](11_Development_Principles.md) — 開発**前**に何を確認し、何を判断基準にするか(本ファイルは「どう開発するか」を定める)
- [20_UI_UX_Rules.md](20_UI_UX_Rules.md)
- [30_AI_Rules.md](30_AI_Rules.md)
- [60_Editorial_Workflow.md](60_Editorial_Workflow.md) — GitHub運用フロー・報告フォーマット
- [SmartLaboWorks/README.md](../SmartLaboWorks/README.md)

---

## 変更履歴

| バージョン | 日付 | 変更者 | 変更内容 |
|---|---|---|---|
| v1.0 | 2026-07-03 | Smart Labo | 初版作成 |
| v2.0 | 2026-07-03 | Smart Labo | PROJECT_BIBLE再構成に伴い旧06_Development_Rules.mdから改番。00_Foundation/06_Philosophy.mdの8原則(AIファースト等)を反映 |
| v2.1 | 2026-07-03 | Claude Code(CEO指示による) | コミットメッセージ規約(feat/docs/design/fix/refactor/style/chore)を追加。60_Editorial_Workflow.mdのGitHub運用フローへの参照を追加 |
| v2.2 | 2026-07-05 | Claude Code(CEO指示による) | 新設の[11_Development_Principles.md](11_Development_Principles.md)への参照を追加(開発着手前の確認ルール・判断基準) |

*最終更新: 2026-07-05*
