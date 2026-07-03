# DESIGN_ASSETS / 01_HERO

**Homepage Hero用ビジュアル資産**

---

## このフォルダの目的

コーポレートサイト トップページの Hero セクション(および LP・営業資料・プレゼン等での転用)で使用するビジュアル資産を管理します。[DESIGN_ASSETS/README.md](../README.md) で説明している「番号付きユースケースフォルダ」の第一号です。

---

## 構成

```
01_HERO/
├── Backgrounds/    ← Hero背景画像(候補・採用画像すべてをアーカイブ。実装用の背景単体データ)
├── Mockups/        ← 完成イメージのモックアップ(参考資料・旧世代。ロゴ・見出し・CTAが焼き込み済みのため実装不可)
└── Overlays/       ← オーバーレイ・グラデーションの補助素材(未使用・今後の活用候補)
```

---

## Backgrounds アセット一覧

| ファイル | ステータス | 世界観 | 備考 |
|---|---|---|---|
| `hero_background_01_sunrise_city.webp` | 候補(アーカイブ) | 朝焼け・未来都市 | AIネットワークは写っていない。情緒的な訴求向け |
| `hero_background_02_city_network.webp` | **採用中**(Homepage Hero) | 昼景・未来都市・AIネットワーク | 都市とAIネットワークが1枚に同居。CEO提供データより採用(2026-07-03) |
| `hero_background_03_cloud_city.webp` | 候補(アーカイブ) | 雲海の上の高層ビル群 | 高級感・スケール感が強い。LP/プレゼン向き |
| `hero_background_04_night_network.webp` | 候補(アーカイブ) | 夜景・テクノロジー | オレンジ系の灯りも混在。機能訴求ページ向き |
| `hero_background_05_waterfront_city.webp` | 候補(アーカイブ) | 水辺の都市・ワイドビュー | 旧世代モックアップに六角形の混入があったが、本データ(背景単体)には混入なしを確認済み |

各画像の詳細(プロンプト・用途・バージョン・世界観・更新履歴)は、画像ファイルと同名の `.md` ファイルを参照してください。

> **現状の注記(2026-07-03更新):** 5点すべての**実装用背景単体データ(`.webp`)の格納が完了しました。** CEOがAI_WORKSPACE経由で提供した「HERO背景素材(バリエーション)」シートから、テキスト・ロゴが焼き込まれていない状態で切り出し、[Backgrounds/](Backgrounds/) に格納しています。このうち `hero_background_02_city_network.webp` をHomepage Hero([WEBSITE/index.html](../../WEBSITE/index.html))に実装済みです。なお、以前受領していた「完成イメージのモックアップ」([Mockups/](Mockups/README.md))は構図が異なる別カットのため、参考資料として区別して保管しています。

---

## 利用ルール

- 画像ファイルと `.md` メタデータファイルは必ずセットで管理してください(例: `hero_background_01_sunrise_city.webp` + `hero_background_01_sunrise_city.md`)。
- 新しいHero背景候補を生成した場合も、採用・不採用にかかわらずこのフォルダにアーカイブし、一覧表に追記してください(同じ世界観を再利用できるようにするため)。
- 禁止ビジュアル(六角形、安っぽいAIロボット等)に該当する画像は、参考用として保管する場合も「使用不可」であることを一覧表とメタデータに明記してください。

---

## 変更履歴

| バージョン | 日付 | 変更者 | 変更内容 |
|---|---|---|---|
| v1.0 | 2026-07-03 | Claude Code(CEO指示による) | 初版作成。5点のHero背景候補を一覧化(採用1点、候補3点、使用不可1点) |
| v1.1 | 2026-07-03 | Claude Code(CEO指示による) | [AI_WORKSPACE/](../../AI_WORKSPACE/README.md) 経由で完成イメージのモックアップ5点を受領し、[Mockups/](Mockups/README.md) を新設して格納。実装用の背景単体データは引き続き未格納であることを明記 |
| v1.2 | 2026-07-03 | Claude Code(CEO提供による) | AI_WORKSPACE経由で**実装用の背景単体データ5点(格納完了)**を受領。`hero_background_02_city_network.webp` をHomepage Heroに実装。[Overlays/](Overlays/README.md) を新設し補助素材6点を格納。`05_waterfront_city` は新データでは六角形の混入がないことを確認し「使用不可」を解除 |

*最終更新: 2026-07-03*
