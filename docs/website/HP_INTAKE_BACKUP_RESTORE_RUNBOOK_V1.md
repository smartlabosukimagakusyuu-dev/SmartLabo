# HP Intake — バックアップ・復元 運用手順書 v1

```text
STATUS      : APPROVED / HP-ONBOARDING-4G 実装済み（**ローカル検証のみ。本番未実施**）
VERSION     : v1.0
DATE        : 2026-08-28
工程        : HP-ONBOARDING-4G（SQLiteバックアップ・復元・世代管理・保持削除整合性）
SSOT        : docs/website/HP_ONBOARDING_INTAKE_DATA_MODEL_V1.md **v1.11** §9.5
実装         : intake-api/src/Backup/ ／ intake-api/bin/intake-backup.php
本番配置     : **未実施**。パス確定・実測・権限確認は **4H** で行う
```

> ★本書に出てくる案件番号・店舗名・メールはすべて**架空**である。
> ★本書は運用手順であり、SSOT を上書きしない。齟齬があれば SSOT が優先する。

---

## 0. この手順書が守る約束

| # | 約束 |
|---|---|
| 1 | バックアップは **`SQLite3::backup()`**（Online Backup API）で取る。**単純コピーを通常手段にしない** |
| 2 | `VACUUM INTO` は使わない（本番 SQLite 3.26.0 では存在しない構文） |
| 3 | 取得後に必ず `integrity_check` / `foreign_key_check` を通す |
| 4 | **復元できることを実際に確かめる**（restore drill）。取っただけで安心しない |
| 5 | **稼働DBへ書き戻す機能を作らない。** 本番復元は 4H 以降の**別承認**工程 |
| 6 | 保持は **30日 / 最大60世代**。自動 cron を作らない。**すべて手動** |
| 7 | 案件を保持削除したら、**その前に作られた世代を優先して消す** |
| 8 | 削除系はすべて **dry-run が既定**。実削除には `--apply` と確認文字列が要る |
| 9 | DB のトランザクションとファイル削除は**ひとつの原子操作にならない**。<br>段階・状態確認・冪等な再実行・本書の STOP 条件で安全性を担保する |

---

## 1. 前提

### 1.1 設定

`private/intake-config.php`（Git に入れない・権限 600）に次を置く。

```php
'backup_dir' => '/絶対パス/private/intake/backups',
```

| # | 条件 |
|---|---|
| 1 | **絶対パス**であること。相対パスは拒否される |
| 2 | **public_html の外**。`public_html` / `public` / `htdocs` / `www` を構成要素に含むパスは拒否される |
| 3 | ルート直下（`/backups`）・ホーム直下（`/home/xxx`）は拒否される |
| 4 | ディレクトリ **700** / ファイル **600** |
| 5 | symlink で公開領域へ逃げている場合も拒否される |
| 6 | **本番の正確な絶対パスは 4H で XServer 実機を確認してから確定する** |

### 1.2 実行場所

```bash
php -c intake-api/dev/php.ini intake-api/bin/intake-backup.php backup:list
```

| # | 条件 |
|---|---|
| 1 | **端末（CLI）からのみ**。Web から実行できない（`PHP_SAPI !== 'cli'` で即終了する） |
| 2 | `bin/` は **public_html へ配置しない** |
| 3 | 引数に鍵・パスワード・パスを渡さない。設定は設定ファイルか環境変数から読む |
| 4 | 出力に DB の中身・PII・保存先の絶対パスは出ない（保存先は `<backup_dir>` と表示される） |

---

## 2. 通常バックアップ（日次・手動）

```bash
php intake-api/bin/intake-backup.php backup:create
```

内部の順序（途中で失敗したら**世代を作らない**）:

```text
排他ロック → 一時ファイル(.part)へ SQLite3::backup()
  → integrity_check / foreign_key_check
  → SHA-256 計算 → ディスクへ同期 → 権限600
  → 同一ディレクトリ内で atomic rename → manifest.json へ控えを記録
```

**成功の確認**

- `[OK] バックアップを作成しました` が出る
- `ファイル` が `intake-YYYYMMDD-HHMMSS-<random>.sqlite` の形
- `schema` が `user_version=4 / answer=1`

**STOP 条件**

| 症状 | 意味 | やること |
|---|---|---|
| `lock_busy` | 他のバックアップ操作が動いている | 終わるのを待って再実行する。**同時に走らせない** |
| `not_configured` | `backup_dir` 未設定 | 設定してから再実行。**設定せずに先へ進まない** |
| `relative` / `public_area` / `home_root` / `too_shallow` | 置き場所が規則に反する | パスを直す。**規則を緩めない** |
| `backup_failed` / `integrity_failed` | 取得できなかった／壊れていた | 一時ファイルは自動で消えている。**既存の世代は無事**。<br>ディスク容量・権限を確認して再実行。直らなければ **STOP** して代表へ報告 |
| `name_collision` | 同名の世代がある | 1秒待って再実行する。**既存を上書きしない** |

---

## 3. 一覧・検証

```bash
php intake-api/bin/intake-backup.php backup:list
php intake-api/bin/intake-backup.php backup:verify --name=intake-YYYYMMDD-HHMMSS-xxxxxxxx.sqlite
```

`verify` が見るもの:

| # | 項目 | 期待値 |
|---|---|---|
| 1 | SHA-256 が控えと一致 | 一致 |
| 2 | サイズが控えと一致 | 一致 |
| 3 | `PRAGMA integrity_check` | `ok` |
| 4 | `PRAGMA foreign_key_check` | 0件 |
| 5 | `PRAGMA user_version` | `4` |
| 6 | 必須8表の存在 | すべてある |
| 7 | 回答スキーマ版 | `1` 以下 |

**STOP 条件**

| 症状 | 意味 | やること |
|---|---|---|
| `sha_mismatch` | ファイルが**改ざん・破損**している | その世代を**使わない**。他の世代を検証する。**消さずに**代表へ報告 |
| `no_manifest_entry` | 控えが無く検証できない | その世代を「検証済み」と扱わない。新しい世代を作り直す |
| `not_a_database` / `integrity_failed` | 中身が壊れている | その世代を採用しない。直近の正常な世代を保持したまま原因を調べる |
| `schema_version_mismatch` / `missing_tables` / `answer_schema_mismatch` | 別系統・別版のファイル | **復元に使わない**。取り違えを疑う |

---

## 4. 復元確認（restore drill）

```bash
php intake-api/bin/intake-backup.php backup:restore-drill --name=intake-YYYYMMDD-HHMMSS-xxxxxxxx.sqlite
```

やること:

```text
verify → 使い捨ての一時ディレクトリ(.drill-*)へ復元 → SQLite として開く
  → integrity_check / foreign_key_check / user_version / 8表 / 回答スキーマ版
  → 案件行の形式（案件番号・状態・作成日時）が壊れていないこと
  → 元DBとの非PII指標（表ごとの件数）を比較して**表示するだけ**
  → 一時DB・一時ディレクトリを削除
```

| # | 確認する出力 |
|---|---|
| 1 | `一時DBは削除済み : はい` |
| 2 | `稼働DBは無変更   : はい` |
| 3 | `案件行` の件数が0でない（中身は表示しない） |
| 4 | `件数一致` は **いいえ でも異常ではない**（バックアップは「ある時点」の写しである） |

**やってはいけないこと**

- 稼働DBへ書き戻す
- 元DBを消す・退避のつもりで上書きする
- サーバーを再起動する
- 本番のDBファイルを手でコピーして差し替える

> ★**本番復元は 4H 以降の別承認工程**である。ここには経路が無い。
> 本当に復元が必要になった場合は、作業前に代表の承認を取り、別手順書を作る。

---

## 5. cleanup（30日超・60世代超）

### 5.1 まず dry-run（既定）

```bash
php intake-api/bin/intake-backup.php backup:cleanup
```

- **1件も削除しない。** 何が対象かだけを表示する
- `30日超` と `世代超過` の内訳、対象ファイル名が出る

### 5.2 中身を確認してから実行

```bash
php intake-api/bin/intake-backup.php backup:cleanup --apply --confirm="DELETE OLD BACKUPS"
```

| # | 規則 |
|---|---|
| 1 | 削除順は **①30日超 → ②残りが60世代を超えた分を古い順** |
| 2 | **稼働DBは対象にしない** |
| 3 | **バックアップディレクトリの外は対象にしない** |
| 4 | symlink はたどらない（世代として数えない） |
| 5 | 確認文字列が1文字でも違えば**削除0件**で終わる |

> ★**削除した世代からは二度と復元できない。**
> dry-run の一覧を目で確かめてから `--apply` する。

**STOP 条件**

| 症状 | やること |
|---|---|
| `confirm_mismatch` | 確認文字列を正しく入れ直す。**回避しない** |
| `lock_busy` | 他の操作の終了を待つ |
| 実行後に想定より多く消えた | **直ちに作業を止め**、`backup:create` で新しい世代を1つ作り、代表へ報告する |

---

## 6. 保持削除（retention purge）と連動した旧世代の削除

### 6.1 なぜ必要か

案件を稼働DBから物理削除しても、**削除前のバックアップが残っている間は
そこから PII を復元できてしまう**。したがって保持削除は、
「DBから消す」だけでは**運用上まだ完了していない**。

### 6.2 正式手順（順序を入れ替えない）

```text
 1. retention_actions_enabled を確認する
 2. backup_policy_confirmed を確認する
 3. 対象案件・期限・標準管理票への移送を確認する
 4. purge 直前に backup:list で世代一覧を記録する
 5. 管理画面から案件を purge する（DELETE <案件番号> の完全一致）
 6. purge 後の新しいバックアップを作る（backup:create）
 7. その新しい世代の restore drill を成功させる
 8. purge 前に作られた全世代を削除する（backup:purge-preceding-generations）
 9. backup:list で再走査する
10. purge 前世代が 0 件であることを確認する
11. 完了を監査（backup_generations_purged / ok）で確認する
```

コマンド:

```bash
php intake-api/bin/intake-backup.php backup:list
php intake-api/bin/intake-backup.php backup:create
php intake-api/bin/intake-backup.php backup:restore-drill --name=<いま作った世代>
php intake-api/bin/intake-backup.php backup:purge-preceding-generations
php intake-api/bin/intake-backup.php backup:purge-preceding-generations --apply --confirm="DELETE PRE-PURGE BACKUPS"
php intake-api/bin/intake-backup.php backup:list
```

`backup:purge-preceding-generations` は、**実行前に自分でもう一度**
最新の purge 後世代を verify + restore drill する。ここが通らなければ
**古い世代を1件も消さない**。

### 6.3 STOP 条件と再実行

| 症状 | 意味 | やること |
|---|---|---|
| `no_purge_recorded` | 保持削除がまだ実行されていない | 先に 5 を済ませる。**先に古い世代を消さない** |
| `post_purge_backup_missing` | purge 後の世代が無い | `backup:create` を実行してからやり直す。**古い世代は消えていない** |
| `post_purge_backup_unverified` | purge 後の世代が検証を通らない | **保持削除は「バックアップ側未完了」**である。<br>①原因を調べる ②`backup:create` で作り直す ③drill を通す<br>④もう一度 `backup:purge-preceding-generations` を実行する。<br>**古い世代は1件も消えていない。** この状態のまま放置しない |
| `confirm_mismatch` | 確認文字列が違う | 入れ直す。削除は0件 |
| `preceding_generation_remains` | 一部が消えずに残った | **同じコマンドをもう一度実行する**（冪等である）。<br>それでも残るならファイル権限を確認し、代表へ報告する |

> ★このコマンドは**何度実行してもよい**。すでに消えている世代は数えられない。
> 途中で電源が落ちても、もう一度実行すれば残りを消せる。

### 6.4 DB と filesystem を跨ぐことについて

DB の削除（トランザクション）とバックアップファイルの削除（filesystem）は
**ひとつの原子操作にならない**。したがって次の中間状態がありうる。

| 状態 | 意味 | 復旧 |
|---|---|---|
| DB は purge 済み・purge 後世代が無い | バックアップ側未完了 | `backup:create` → drill → purge-preceding を実行する |
| DB は purge 済み・purge 後世代の検証が失敗 | バックアップ側未完了 | 作り直して drill を通してから purge-preceding |
| purge 前世代が一部だけ消えた | 途中終了 | **同じコマンドを再実行する** |

いずれも「消しすぎ」ではなく「消し残し」に倒れる設計である。
消し残しは再実行で解消できるが、消しすぎは戻せないためである。

---

## 7. 4H へ残す作業

| # | 事項 |
|---|---|
| 1 | XServer 上の**正確な絶対パス**を確定する（`${DOMAIN_ROOT}/private/intake/backups` 候補） |
| 2 | そのパスが **public_html の外**であることを実機で確認する |
| 3 | ディレクトリ 700 / ファイル 600 を実機で確認する |
| 4 | XServer 上で `SQLite3::backup()` が動くことを実測する |
| 5 | XServer 上で 作成 → 検証 → restore drill → cleanup を実測する |
| 6 | 代表が本手順書を確認する |
| 7 | 上記がすべて済んでから、`backup_policy_confirmed` の true 化を**別承認**で判断する |

> ★**4G の完了だけでは `backup_policy_confirmed` を true にしない。**
> 4G 終了時点では **false のまま**である。

---

## 8. 変更履歴

| 版 | 日付 | 変更 |
|---|---|---|
| v1.0 | 2026-08-28 | HP-ONBOARDING-4G で新規作成。SSOT v1.11 §9.5 に対応 |
