# DESIGN_ASSETS / Logo

**(廃止・移行済み)ロゴファイルの格納場所**

---

## 重要 — 実データは [01_LOGO/](../01_LOGO/README.md) へ移行しました(2026-07-04)

CEOが正式ロゴを確定したことに伴い、ロゴの実データ管理は [DESIGN_ASSETS/01_LOGO/](../01_LOGO/README.md)(番号付きユースケースフォルダ)へ移行しました。**このフォルダ(`Logo/`)には実データを格納しないでください。** 新しいロゴファイルは、必ず [01_LOGO/](../01_LOGO/README.md) の該当サブフォルダ(Primary/White/Dark/Icon/Favicon/SVG/PNG/WebP)へ格納してください。

このフォルダは、旧構成からの参照が残っている場合のリダイレクト目的でのみ残しています。

---

## このフォルダの目的(旧)

Smart Laboのロゴ実データ(SVG/PNG等)を格納します。仕様(バリエーション・使い分け)は [PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md](../../PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md) の「ロゴ使用ルール」セクションを参照してください。

---

## 利用ルール

- 格納するバリエーション: メインロゴ(カラー)/メインロゴ(白抜き)/アイコン(シンボルマークのみ)。
- ファイル名の例: `smartlabo-logo-color.svg`、`smartlabo-logo-white.svg`、`smartlabo-icon.svg`。
- ベクター(SVG)を正とし、必要に応じてラスター(PNG)書き出しも格納してください。

---

## Version管理

- ロゴを刷新する場合は上書きせず、`_v2` のように新しいファイルを追加し、旧バージョンは [ARCHIVE/](../../ARCHIVE/README.md) へ移動してください。

---

## 更新ルール

- ロゴの変更は会社の根幹に関わるため、[PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md](../../PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md) の更新ルール(経営層承認)に従ってください。

---

## Git運用ルール

- SVGはテキストとして差分管理できますが、PNG等のバイナリはサイズに注意し、必要に応じてGit LFSを検討してください。

---

## 変更履歴

| バージョン | 日付 | 変更者 | 変更内容 |
|---|---|---|---|
| v1.0 | 2026-07-03 | Smart Labo | 初版作成 |
| v2.0 | 2026-07-04 | Claude Code(CEO指示による) | 正式ロゴ確定に伴い、実データ管理を [01_LOGO/](../01_LOGO/README.md) へ移行。このフォルダは廃止(リダイレクト目的のみ) |

*最終更新: 2026-07-04*
