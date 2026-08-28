<?php
/**
 * HP Intake API — APP_ROOT を教える非公開 bootstrap の雛形
 * （HP-ONBOARDING-4H-R0 / SSOT v1.12 §10.11）。
 *
 * ── これは何か ──
 * XServer のサブドメインは `smartlaboworks.com/public_html/<sub>/` の下に作られる。
 * つまり **docroot の親は public_html** であり、そこへ `src/` と `private/` を
 * 置くことはできない。そこで「アプリ本体がどこにあるか」だけを、
 * **公開領域の外にあるこのファイル**から教える。
 *
 * ── 使い方（第一候補：auto_prepend_file）──
 *   1. このファイルを APP_ROOT へ `app-root-bootstrap.php` としてコピーする
 *      例: /home/<account>/smartlaboworks.com/private/hp-intake/app-root-bootstrap.php
 *   2. 下の絶対パスを実環境の APP_ROOT へ書き換える
 *   3. docroot の `.user.ini` に次を書く（値は実パスへ）
 *        auto_prepend_file = /home/<account>/.../private/hp-intake/app-root-bootstrap.php
 *   4. `.user.ini` は即時反映されない（既定300秒）。反映を待って確認する
 *
 * ── 代替方式（auto_prepend_file が使えないとき）──
 *   この bootstrap を置かなくても、`public/index.php` は docroot の祖先から
 *   `private/hp-intake/src/Autoload.php` を探す。**約束は相対の位置だけ**で、
 *   公開側へ絶対パスを書かずに済む。どちらを正式採用するかは 4H で実機確認して決める。
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
    //   4H で XServer 実機のパスを確認してから確定する。
    define('INTAKE_APP_ROOT', '/home/<account>/smartlaboworks.com/private/hp-intake');
}
