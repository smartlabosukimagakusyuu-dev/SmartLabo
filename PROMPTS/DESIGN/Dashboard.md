# Dashboard — Smart Labo Works プロダクト画面 設計プロンプト

> 使用前に [SmartLabo_Design_Bible.md](SmartLabo_Design_Bible.md) を必ず読み込ませてください。このファイルは、そのブランド思想を「毎日使う業務ダッシュボード」という具体的な成果物に落とし込むための指示書です。対象実装は [SmartLaboWorks/](../../SmartLaboWorks/README.md)。

---

## 前提

Homepage(サイト)が「初めて訪れた社長を安心させる場所」だとすれば、Dashboardは「毎日その社長が実際に頼る道具」です。求められる完成度は同じQuiet Intelligenceですが、静けさに加えて**機能性・情報の見つけやすさ**が同じ重みで要求されます。

[PROJECT_BIBLE/00_Foundation/06_Philosophy.md](../../PROJECT_BIBLE/00_Foundation/06_Philosophy.md) の原則3「毎日使いたくなるUI」を、画面として体現してください。

---

## ダッシュボードとマーケティングページの違い

| | Homepage | Dashboard |
|---|---|---|
| 目的 | 信頼してもらう | 使い続けてもらう |
| 情報量 | 最小限に絞る | 必要な情報は過不足なく見せる(ただし詰め込まない) |
| 訪問頻度 | 数回 | 毎日 |
| 主役 | メッセージ・ビジュアル | データ・タスク・判断材料 |

情報量が増える分、原則5「情報量より理解しやすさ」がより重要になります。**多く見せられることと、多く見せることは違う**、という前提で設計してください。

---

## デザイン指示

- **レイアウト:** 社長が今日確認すべきこと(要対応事項・重要な変化)が、開いた瞬間に一目でわかる構成にする。
- **配色:** White / Black / Smart Blueを基調とし、グラフ等でやむを得ず色数が必要な場合も、Smart Blueの濃淡・グレースケールの範囲内で構成する(禁止事項の「派手な演出」を避ける)。
- **データ表示:** 数値・グラフは正確さと静けさを両立させる。強調は色ではなく、余白・サイズ・配置の優先順位で行う。
- **通知・アラート:** 緊急度に応じたトーンを使い分けるが、ネオン的な警告色や過剰なアニメーションは使わない。落ち着いたトーンで、しかし見逃させない設計にする。
- **ナビゲーション:** Linearを参考に、キーボード操作を含む高速な操作性を意識する(参照: [PROJECT_BIBLE/20_UI_UX_Rules.md](../../PROJECT_BIBLE/20_UI_UX_Rules.md))。
- **モーション:** 状態変化(保存完了、読み込み中など)を伝えるための最小限の動きに留める。ゆっくり・自然・静かに。

---

## 禁止事項(Homepageと共通)

[SmartLabo_Design_Bible.md](SmartLabo_Design_Bible.md) の「禁止事項」をそのまま適用します。特にダッシュボードでは以下に注意してください。

- ダッシュボード特有の「サイバー管制室」風の演出(グラフの過剰なグロー、ダークテーマでのネオン風配色など)は行わない
- 通知バッジ・アラートの多用によって、画面全体が騒がしくならないようにする

---

## UI・チェックリスト

- [ ] 開いた瞬間に「今日やるべきこと」がわかるか
- [ ] 色数はWhite / Black / Smart Blue(+必要最小限のグレースケール)に収まっているか
- [ ] グラフ・数値は詰め込みすぎず、理解しやすいか
- [ ] 通知・アラートが騒がしくなっていないか
- [ ] 毎日開いても疲れない、心地よい体験になっているか([PROJECT_BIBLE/20_UI_UX_Rules.md](../../PROJECT_BIBLE/20_UI_UX_Rules.md) レビューチェックリストと共通)

---

## 関連ドキュメント

- [SmartLabo_Design_Bible.md](SmartLabo_Design_Bible.md)
- [PROJECT_BIBLE/00_Foundation/06_Philosophy.md](../../PROJECT_BIBLE/00_Foundation/06_Philosophy.md)
- [PROJECT_BIBLE/20_UI_UX_Rules.md](../../PROJECT_BIBLE/20_UI_UX_Rules.md)
- [SmartLaboWorks/README.md](../../SmartLaboWorks/README.md)

---

## 変更履歴

| バージョン | 日付 | 変更者 | 変更内容 |
|---|---|---|---|
| v1.0 | 2026-07-03 | Claude Code | 初版作成。Homepageとの役割の違いを明確化し、毎日使う業務画面としてのデザイン指示を制定 |

*最終更新: 2026-07-03*
