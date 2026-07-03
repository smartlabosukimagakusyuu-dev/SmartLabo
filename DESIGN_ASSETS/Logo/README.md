# DESIGN_ASSETS / Logo

**ロゴファイルの格納場所**

---

## このフォルダの目的

Smart Laboのロゴ実データ(SVG/PNG等)を格納します。仕様(バリエーション・使い分け)は [PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md](../../PROJECT_BIBLE/00_Foundation/07_Brand_Identity.md) の「ロゴ」セクションを参照してください。

**現時点(2026-07-03)では、実データはまだ格納されていません。** 参考画像として共有された構成を先行して仕様化した段階で、正式なロゴデータは別途用意される予定です。

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

*最終更新: 2026-07-03*
