<?php
/**
 * HP Intake API — APP_ROOT を教える非公開 bootstrap の雛形
 * （HP-ONBOARDING-4H-R0 / SSOT v1.13 §10.11）。
 *
 * ── これは何か ──
 * XServer のサブドメインは `smartlaboworks.com/public_html/<sub>/` の下に作られる。
 * つまり **docroot の親は public_html** であり、そこへ `src/` と `private/` を
 * 置くことはできない。そこで「アプリ本体がどこにあるか」だけを、
 * **公開領域の外にあるこのファイル**から教える。
 *
 * ── ★XServer 本番ではこの方式を採用していない（4H-3 で確定）──
 *   本番は `public/index.php` が docroot の祖先から
 *   `private/hp-intake/src/Autoload.php` を探す経路（祖先探索）で解決する。
 *   `.user.ini` の `auto_prepend_file` は**空のまま**とする。
 *   この雛形は **CLI・ローカル確認・他環境用**として残してある。
 *
 *   ★これは「XServer では auto_prepend_file が使えない」という意味ではない。
 *   4H-3 では、設定した**絶対パスでファイルを開けず**
 *   `Failed opening required` となった。正しいパスであれば動く可能性は否定しない。
 *
 *   ★それでも本番で使わないのは、auto_prepend_file が **require 相当**で実行され、
 *   このファイルを開けなかった時点で**主スクリプトが実行されない**ためである。
 *   実測では当該サブドメインの全 PHP 応答が **HTTP 500・本文 0 バイト**になった
 *   （display_errors = Off のため外部への漏えいは 0 件）。
 *   **パスを 1 文字誤るだけで、そのサブドメインの PHP が全滅する**ことに注意する。
 *
 * ── 使う場合の手順（他環境向け）──
 *   1. このファイルを APP_ROOT へ `app-root-bootstrap.php` としてコピーする
 *   2. 下の絶対パスを実環境の APP_ROOT へ書き換える（末尾に `/` を付けない）
 *   3. docroot の `.user.ini` の `auto_prepend_file` へ、そのファイルの絶対パスを書く
 *   4. `.user.ini` は即時反映されない（既定300秒）。反映を待って確認する
 *   5. 確認に失敗したら、まず `auto_prepend_file` を空へ戻して復旧させる
 *
 * ── 規則 ──
 * ★このファイルを **public_html の中へ置かない**。
 * ★**秘密値を書かない。** 鍵・パスワード・宛先は `intake-config.php` にある。
 * ★出力しない（`echo` も改行も書かない）。auto_prepend_file は全応答の前に走る。
 * ★Web の入力（URL・query・POST・Cookie・ヘッダー）を読まない。
 * ★**Git へ実パスを入れない。** これは雛形であり、実体は追跡外である。
 * ★APP_ROOT が公開領域の中・不在・相対なら、アプリ側が fail closed で止める。
 */
declare(strict_types=1);

if (!defined('INTAKE_APP_ROOT')) {
    // ★ここを実環境の絶対パスへ書き換える（末尾に `/` を付けない）。
    //   本番（XServer）ではこの定数経路を使わない（4H-3 で確定）。
    define('INTAKE_APP_ROOT', '/home/<account>/smartlaboworks.com/private/hp-intake');
}
