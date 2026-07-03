# Dashboard — Smart Labo Works プロダクト画面 設計プロンプト

> 使用前に [SmartLabo_Design_Bible.md](SmartLabo_Design_Bible.md) を必ず読み込ませてください。このファイルは、そのブランド思想を「毎日使う業務ダッシュボード」という具体的な成果物に落とし込むための指示書です。対象実装は [SmartLaboWorks/](../../SmartLaboWorks/README.md)。

---

## 前提

Homepage(サイト)が「初めて訪れた社長にEnterprise AI Platformとしての信頼を感じさせる場所」だとすれば、Dashboardは「毎日その社長が実際に頼る道具」です。Homepageで示した信頼感・先進性を、実際の使用感として証明する画面である必要があります。

[PROJECT_BIBLE/00_Foundation/06_Philosophy.md](../../PROJECT_BIBLE/00_Foundation/06_Philosophy.md) の原則3「毎日使いたくなるUI」を、画面として体現してください。またDashboardは、[Homepage.md](Homepage.md) のHeroで見せる「実際のSmart Labo Works管理画面」そのものでもあるため、単体で完成度の高いスクリーンショットに耐える画面である必要があります。

---

## ダッシュボードとマーケティングページの違い

| | Homepage | Dashboard |
|---|---|---|
| 目的 | 信頼してもらう・デモ/相談へ誘導する | 使い続けてもらう |
| 情報量 | 価値中心に絞る | 必要な情報は過不足なく見せる(ただし詰め込まない) |
| 訪問頻度 | 数回 | 毎日 |
| 主役 | メッセージ・ビジュアル | データ・タスク・判断材料 |

情報量が増える分、「機能ではなく価値を伝える」原則がより重要になります。**多く見せられることと、多く見せることは違う**、という前提で設計してください。

---

## デザイン指示

- **レイアウト:** 社長が今日確認すべきこと(要対応事項・重要な変化)が、開いた瞬間に一目でわかる構成にする。
- **配色:** Navy / White / Smart Blue / Light Grayを基調とする。Palantir・Datadogを参考に、データ・グラフ表現には重厚さと洗練さを両立させる(参照: [PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md](../../PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md))。
- **データ表示:** 数値・グラフは正確さと迫力を両立させる。強調はSmart Blueの濃淡・サイズ・配置の優先順位で行う。
- **通知・アラート:** 緊急度に応じたトーンを使い分けるが、ネオン的な警告色や過剰なアニメーションは使わない。落ち着いたトーンで、しかし見逃させない設計にする。
- **ナビゲーション:** 高速な操作性を意識する(参照: [PROJECT_BIBLE/20_UI_UX_Rules.md](../../PROJECT_BIBLE/20_UI_UX_Rules.md))。
- **ボタン・操作要素:** 大きめ・美しい角丸・ホバーアニメーションで統一し、Homepageとの一貫性を保つ。
- **モーション:** 状態変化(保存完了、読み込み中など)を伝える上品な動きを使う。過剰な演出は避ける。

---

## 禁止事項(Homepageと共通)

[SmartLabo_Design_Bible.md](SmartLabo_Design_Bible.md) の「禁止事項」をそのまま適用します。特にダッシュボードでは以下に注意してください。

- 安っぽい「サイバー管制室」風の演出(過度なグロー、ネオン風配色)は行わない
- 通知バッジ・アラートの多用によって、画面全体が騒がしくならないようにする
- データ量が多くなりがちな画面だからこそ、「情報を詰め込みすぎるデザイン」の禁止事項を特に意識する

---

## UI・チェックリスト

- [ ] 開いた瞬間に「今日やるべきこと」がわかるか
- [ ] 色数はNavy / White / Smart Blue / Light Grayに収まっているか
- [ ] グラフ・数値は詰め込みすぎず、重厚さと理解しやすさを両立しているか
- [ ] 通知・アラートが騒がしくなっていないか
- [ ] ボタンはHomepageと一貫したスタイル(大きめ・角丸・ホバーアニメーション)か
- [ ] 毎日開いても疲れない、心地よい体験になっているか
- [ ] このままHomepageのHeroビジュアルとして使える完成度か

---

## 関連ドキュメント

- [SmartLabo_Design_Bible.md](SmartLabo_Design_Bible.md)
- [Homepage.md](Homepage.md)
- [PROJECT_BIBLE/00_Foundation/06_Philosophy.md](../../PROJECT_BIBLE/00_Foundation/06_Philosophy.md)
- [PROJECT_BIBLE/20_UI_UX_Rules.md](../../PROJECT_BIBLE/20_UI_UX_Rules.md)
- [SmartLaboWorks/README.md](../../SmartLaboWorks/README.md)

---

## 変更履歴

| バージョン | 日付 | 変更者 | 変更内容 |
|---|---|---|---|
| v1.0 | 2026-07-03 | Claude Code | 初版作成。Homepageとの役割の違いを明確化し、毎日使う業務画面としてのデザイン指示を制定 |
| **v2.0** | 2026-07-03 | Claude Code(CEO指示による全面ピボット) | **Enterprise AI Platform方針へ全面改訂。** 配色をNavy/White/Smart Blue/Light Grayに変更、Palantir/Datadogを参考にしたデータ表現の重厚さを追加。HomepageのHeroビジュアルとして使われる前提を明記 |

*最終更新: 2026-07-03*
