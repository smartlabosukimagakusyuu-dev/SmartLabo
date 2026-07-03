# 00_Foundation

**株式会社スマートラボの土台となる思想 — Company Foundation**

---

## このフォルダの目的

`00_Foundation` は、PROJECT_BIBLE の中でも最も基礎となる「会社が何者であるか」を定義する層です。番号を `00` としているのは、他のすべてのルール(開発ルール・UI/UXルール・AIルール等)がこの層の思想から導かれる、という依存関係を表すためです。

ここを読めば、株式会社スマートラボという会社を、理念からプロダクトの構想まで一気通貫で理解できます。新しく参加する社員・パートナー・AIエージェントは、まずこのフォルダから読み始めてください。

---

## ファイル構成(読む順番)

| # | ファイル | 内容 |
|---|---|---|
| 01 | [01_Company_Story.md](01_Company_Story.md) | 会社としてのストーリー(何がきっかけで、何を目指して始まったか) |
| 02 | [02_Founder_Message.md](02_Founder_Message.md) | 創業者からのメッセージ |
| 03 | [03_Mission.md](03_Mission.md) | ミッション(なぜ存在するのか) |
| 04 | [04_Vision.md](04_Vision.md) | ビジョン(何を目指すのか) |
| 05 | [05_Value.md](05_Value.md) | バリュー(何を大切にするのか) |
| 06 | [06_Philosophy.md](06_Philosophy.md) | 会社全体を貫く開発・仕事の哲学 |
| 07 | [07_Brand_Identity.md](07_Brand_Identity.md) | ブランドアイデンティティ |
| 08 | [08_SmartLaboWorks_Concept.md](08_SmartLaboWorks_Concept.md) | 主力サービス「Smart Labo Works」のコンセプト |
| 09 | [09_Product_History.md](09_Product_History.md) | プロダクトの歴史・リリース記録 |
| 10 | [10_Future_Roadmap.md](10_Future_Roadmap.md) | 将来のロードマップ |

数字の若い順に、「なぜ会社が存在するか」→「何を目指すか」→「どう振る舞うか」→「何を作るか」→「これからどうなるか」という論理展開になっています。

---

## 利用ルール

- ここに書かれた内容は、会社のあらゆる意思決定・プロダクト開発・コミュニケーションの前提になります。矛盾する判断をする場合は、先にこのフォルダの内容を更新してください。
- `01`〜`02`(Company Story / Founder Message)は物語的な文章、`03`〜`05`(Mission / Vision / Value)は簡潔な原則、`06`〜`10`は実務に近い内容、という性質の違いを意識して読んでください。
- 個人的な創業エピソードや経営判断の生々しい記録は、ここではなく [99_CEO_MEMORY/](../99_CEO_MEMORY/README.md) に記録します。`00_Foundation` は対外的にも通用する、洗練された「公式見解」を置く場所です。

---

## Version管理

- 各ファイルは末尾に「変更履歴」テーブルを持ちます。
- `00_Foundation` 全体としての大きな改訂は、PROJECT_BIBLE全体のバージョン([README.md](../README.md) 参照)に影響します。特に `03_Mission.md` / `04_Vision.md` の変更はメジャーバージョンアップ(例: 1.x → 2.0)の対象として扱ってください。

---

## 更新ルール

- **01〜05(Company Story / Founder Message / Mission / Vision / Value)** の変更には、経営層(代表)の承認が必須です。
- **06〜08(Philosophy / Brand Identity / SmartLaboWorks Concept)** はプロダクト責任者・開発リードの承認のもとで更新してください。
- **09〜10(Product History / Future Roadmap)** は事実の追記・計画の更新として、随時更新して構いません。

---

## Git運用ルール

- 01〜05の変更はPull Requestを作成し、レビューを経てから `master` にマージしてください。
- 06〜10の更新は直接コミットで構いませんが、コミットメッセージに変更理由を明記してください。

---

*最終更新: 2026-07-03*
