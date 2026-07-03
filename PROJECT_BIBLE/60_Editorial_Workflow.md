# 60. Editorial Workflow — 編集体制と更新フロー

このドキュメントは、PROJECT_BIBLE(株式会社スマートラボのOS・憲法)が、誰によって・どのような手順で更新されるかを定めます。PROJECT_BIBLEがSingle Source of Truthであり続けるためには、内容そのものだけでなく、「どう更新されるか」というプロセスも明確でなければなりません。

---

## 役割体制

| 役割 | 担当 | 責務 |
|---|---|---|
| **意思決定パートナー** | ChatGPT | 経営戦略・ブランド・UI/UX・営業・商品設計・会社方針について、CEOとの対話を通じて意思決定の材料・案を提示する |
| **最終承認者** | CEO(代表) | ChatGPTとの議論を踏まえ、実際に採用する内容を決定し、Project Bibleへの反映を指示する |
| **Project Bible編集長** | Claude Code | CEOの更新指示を受け、承認された内容を正確にPROJECT_BIBLEへ反映する。関連ドキュメント・バージョン・CHANGELOGの整合性を保つ実務担当 |

ChatGPTが「何を決めるか」を担い、CEOが「それを採用するか」を決め、Claude Codeが「それをどう記録し、他の全ドキュメントと矛盾なく反映するか」を担う、という三者の役割分担です。

---

## 更新フロー(6ステップ)

```
① ChatGPTとの会話で方向性が決定
        ↓
② CEOが更新指示
        ↓
③ Claude CodeがPROJECT_BIBLEを更新
        ↓
④ Version更新
        ↓
⑤ CHANGELOG更新
        ↓
⑥ Gitへコミット
```

### ① ChatGPTとの会話で方向性が決定

経営戦略・ブランド・UI/UX・営業・商品設計・会社方針について、CEOがChatGPTと対話し、方向性の案を固める。

### ② CEOが更新指示

CEOが、Claude Codeに対して「何を」「どう」PROJECT_BIBLEに反映するかを指示する。指示には、対象ドキュメント(わかれば)と変更内容の要点を含めることが望ましい。

### ③ Claude CodeがPROJECT_BIBLEを更新

Claude Codeは、指示内容を該当ファイルに反映する。このとき、単一ファイルの編集で終わらせず、以下を必ず確認する([Project Bible更新チェックリスト](#project-bible更新チェックリスト)参照)。

### ④ Version更新

変更の性質に応じて、PROJECT_BIBLE全体のバージョンおよび変更したファイル個別のバージョンをインクリメントする。基準は [README.md](README.md) の「Version管理」を参照。

### ⑤ CHANGELOG更新

[CHANGELOG.md](CHANGELOG.md) に、変更内容・変更者・バージョンを記録する。

### ⑥ Gitへコミット

変更内容がわかるコミットメッセージで `git commit` する。経営層承認が必要な変更([00_Foundation/](00_Foundation/README.md) の01〜05等)は、Pull Requestを経てからマージする。

---

## コードより先にBible — 開発時の原則

Claude CodeがSmartLabo Works等のコードを実装する際は、**着手前に必ずPROJECT_BIBLEを読む**。

- 実装しようとしている内容が、PROJECT_BIBLEに書かれた方針と矛盾していないか確認する。
- PROJECT_BIBLEに記載のない新しい判断(仕様の解釈、デザインの選択など)が必要になった場合は、実装を進める前にCEOに確認し、承認された内容を先にPROJECT_BIBLEへ反映してから実装する。
- 「実装が先、ドキュメントは後回し」は禁止する([00_Foundation/06_Philosophy.md](00_Foundation/06_Philosophy.md) の保守性重視の原則にも合致する)。

---

## Project Bible更新チェックリスト

PROJECT_BIBLEを更新する際は、対象ファイルを直すだけで終わらせず、必ず以下を確認する。

- [ ] 変更対象ファイルの内容を更新した
- [ ] 影響を受ける**関連ドキュメント**(相互参照している他ファイル)を更新した
- [ ] 該当フォルダおよび上位の **README.md** の整合性を確認し、必要なら修正した
- [ ] **リンク切れ**がないか確認した(特にファイルの移動・改名・削除を伴う変更の場合)
- [ ] フォルダ構成・番号体系の**見直しが必要かどうか**検討した(新しいテーマが増えた場合、無理に既存ファイルへ詰め込まず、新規ファイル・フォルダの追加を検討する)
- [ ] 変更したファイルの「変更履歴」テーブルに追記した
- [ ] PROJECT_BIBLE全体のVersionを適切にインクリメントした
- [ ] [CHANGELOG.md](CHANGELOG.md) に記録した
- [ ] Gitにコミットした(コミットメッセージに変更内容を明記)

---

## 関連ドキュメント

- [README.md](README.md) — Version管理の詳細基準
- [CHANGELOG.md](CHANGELOG.md)
- [30_AI_Rules.md](30_AI_Rules.md) — AIツールの役割分担・利用ルール

---

## 変更履歴

| バージョン | 日付 | 変更者 | 変更内容 |
|---|---|---|---|
| v1.0 | 2026-07-03 | Claude Code | 初版作成。ChatGPT/CEO/Claude Codeの三者役割体制と6ステップ更新フロー、Project Bible更新チェックリストを制定 |

*最終更新: 2026-07-03*
