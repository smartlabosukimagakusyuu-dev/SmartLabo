# Homepage — コーポレートサイト トップページ 設計プロンプト

> 使用前に [SmartLabo_Design_Bible.md](SmartLabo_Design_Bible.md) を必ず読み込ませてください。このファイルは、そのブランド思想を「トップページ」という具体的な成果物に落とし込むための指示書です。対象実装は [WEBSITE/](../../WEBSITE/README.md)。

---

## 前提

Smart Laboのコーポレートサイトは、機能紹介の場ではありません。訪れた社長が「この会社になら任せられる」と静かに確信する場所です。Appleレベルの完成度を目指してください。

---

## ページ構成(この順序を変えない)

```
Hero
 ↓
共感(社長が抱える課題への共感)
 ↓
Smart Labo Works(プロダクト紹介)
 ↓
導入メリット
 ↓
ブランドストーリー
 ↓
会社理念
 ↓
お問い合わせ
```

---

## 各セクションの指示

### Hero

最初に伝えるのは、この一言だけ。

> **会社を動かすAI。**

その次に、ミッションを伝える。

> **AIで、社長が本来の仕事に集中できる世界をつくる。**

- ボタンは **「デモを見る」** と **「お問い合わせ」** の2つのみ。それ以外のCTAは置かない。
- 背景・ビジュアルは [SmartLabo_Design_Bible.md](SmartLabo_Design_Bible.md) の「推奨するビジュアル」(朝のオフィス、自然光、美しいPC等)に準拠。AIを記号化したビジュアル(ロボット、サイバー演出等)は禁止。
- 情報量を絞り、最初の画面(ファーストビュー)は上記2つのメッセージとボタンのみで構成する。

### 共感

社長が日々感じている「本来の仕事に集中できていない」という課題感に、押しつけがましくなく寄り添う。解決策の提示はまだしない。共感に徹する。

### Smart Labo Works

プロダクトの全体像を紹介する。機能の羅列ではなく、[PROJECT_BIBLE/00_Foundation/08_SmartLaboWorks_Concept.md](../../PROJECT_BIBLE/00_Foundation/08_SmartLaboWorks_Concept.md) のコンセプトに基づき、「何が変わるか」を中心に伝える。

### 導入メリット

「機能ではなく価値を伝える」原則に従い、時間・安心・信頼という観点でメリットを整理する。数値・実績は誠実さの原則([PROJECT_BIBLE/00_Foundation/05_Value.md](../../PROJECT_BIBLE/00_Foundation/05_Value.md))に基づき、裏付けのあるもののみ掲載する。

### ブランドストーリー

[PROJECT_BIBLE/00_Foundation/01_Company_Story.md](../../PROJECT_BIBLE/00_Foundation/01_Company_Story.md) および [02_Founder_Message.md](../../PROJECT_BIBLE/00_Foundation/02_Founder_Message.md) の内容を、Webページとして再構成する。

### 会社理念

Mission / Vision を簡潔に掲載する。[PROJECT_BIBLE/00_Foundation/03_Mission.md](../../PROJECT_BIBLE/00_Foundation/03_Mission.md) / [04_Vision.md](../../PROJECT_BIBLE/00_Foundation/04_Vision.md) を正とする。

### お問い合わせ

フォームはシンプルに。入力項目を必要最小限に絞り、送信までの摩擦をできる限り減らす。

---

## UI・ビジュアルチェックリスト

- [ ] ファーストビューはHeroの2メッセージ+2ボタンのみか
- [ ] 使用カラーはWhite / Black / Smart Blueのみか
- [ ] 禁止ビジュアル(ロボット・サイバー・ネオン・六角形・基板等)を使っていないか
- [ ] アニメーションはゆっくり・自然・静かか
- [ ] ボタンは小さめ・美しい角丸か
- [ ] 各セクションの余白は十分か
- [ ] スマートフォンでも情報が詰め込みすぎに見えないか

---

## 関連ドキュメント

- [SmartLabo_Design_Bible.md](SmartLabo_Design_Bible.md)
- [PROJECT_BIBLE/20_UI_UX_Rules.md](../../PROJECT_BIBLE/20_UI_UX_Rules.md)
- [WEBSITE/README.md](../../WEBSITE/README.md)

---

## 変更履歴

| バージョン | 日付 | 変更者 | 変更内容 |
|---|---|---|---|
| v1.0 | 2026-07-03 | Claude Code(CEO指示による) | 初版作成。Hero文言・ページ構成・UIチェックリストを制定 |

*最終更新: 2026-07-03*
