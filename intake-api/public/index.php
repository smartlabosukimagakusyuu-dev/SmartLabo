<?php
/**
 * HP Intake API — フロントコントローラ。
 *
 * ★このファイルは **docroot に置く唯一の PHP** である。
 * ★本番配置時は SSOT §10.4.1 のとおり
 *   display_errors=Off / display_startup_errors=Off / log_errors=On /
 *   error_log を public_html の外 に設定すること（`.user.ini` で行う）。
 *
 * ── APP_ROOT の見つけ方（HP-ONBOARDING-4H-R0 / SSOT v1.12 §10.11）──
 *
 * XServer のサブドメインは `smartlaboworks.com/public_html/<sub>/` の下に作られる。
 * つまり **docroot の親は public_html** である。そこへ `src/` と `private/` を
 * 置くことはできない（公開領域にアプリ本体と秘密を置くことになる）。
 * そのため docroot と APP_ROOT が兄弟である必要をなくした。
 *
 *   1. 定数 INTAKE_APP_ROOT
 *      `.user.ini` の `auto_prepend_file` が読む**非公開 bootstrap** が定義する。
 *      **第一候補**。docroot には何も置かない。
 *   2. `__DIR__/../src`（リポジトリのままの配置。ローカル確認・簡易配置）
 *   3. docroot の祖先の `private/hp-intake/src`（1 が使えない環境の代替方式）
 *
 * ★**Web の入力から APP_ROOT を変えられない。**
 *   URL・query・POST・Cookie・ヘッダーを読まない。
 * ★docroot の中に APP_ROOT を書いたファイルを置かない。
 * ★このファイルに**絶対パスを直接書かない**。
 * ★見つけた候補が公開領域の中なら採用しない。見つからなければ **fail closed**。
 * ★絶対パスを応答へ出さない。
 */
declare(strict_types=1);

/**
 * オートローダの場所を探す。
 * ★ここは「Autoload.php にたどり着く」ためだけの最小限。
 *   本格的な検査は `AppRoot::check()`（＝`Config::load()` の中）が行う。
 */
$intakeAutoload = (static function (): ?string {
    // 公開領域を思わせる構成要素。候補がこの中にあるなら採らない
    $forbidden = ['public_html', 'public', 'htdocs', 'www', 'wwwroot', 'web'];
    $acceptable = static function (string $root) use ($forbidden): bool {
        if (!is_file($root . '/src/Autoload.php')) {
            return false;
        }
        foreach (explode('/', str_replace('\\', '/', $root)) as $segment) {
            if (in_array(strtolower($segment), $forbidden, true)) {
                return false;
            }
        }

        return true;
    };

    // 1) 非公開 bootstrap（auto_prepend_file）が定義した値
    if (defined('INTAKE_APP_ROOT')) {
        $root = (string)constant('INTAKE_APP_ROOT');
        if ($root !== '' && $acceptable($root)) {
            return $root . '/src/Autoload.php';
        }

        return null; // ★明示された値が使えないなら、既定へ落ちずに止める
    }

    // 2) リポジトリのままの配置（docroot の親が APP_ROOT）
    $sibling = dirname(__DIR__);
    if ($acceptable($sibling)) {
        return $sibling . '/src/Autoload.php';
    }

    // 3) 代替方式: docroot の祖先の `private/hp-intake/`
    //    ★絶対パスではなく**相対の約束**である
    $dir = __DIR__;
    for ($depth = 0; $depth < 6; ++$depth) {
        $parent = dirname($dir);
        if ($parent === $dir) {
            break;
        }
        $dir       = $parent;
        $candidate = $dir . '/private/hp-intake';
        if ($acceptable($candidate)) {
            return $candidate . '/src/Autoload.php';
        }
    }

    return null;
})();

if ($intakeAutoload === null) {
    // ★場所が決まらないまま動かさない。理由・パスを外へ出さない
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8', true);
    header('Cache-Control: no-store, no-cache, must-revalidate', true);
    echo json_encode([
        'ok'      => false,
        'error'   => 'server_error',
        'message' => 'ただいま処理できません。時間をおいてお試しください。',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once $intakeAutoload;

use SmartLabo\Intake\Config;
use SmartLabo\Intake\Http\Request;
use SmartLabo\Intake\Http\Response;
use SmartLabo\Intake\Kernel;

try {
    // ★APP_ROOT の本検査は Config::load() の中で行う（不正なら例外）
    $kernel = new Kernel(Config::load());
} catch (\Throwable $e) {
    // 設定不備（鍵未設定・APP_ROOT 不正など）は fail closed。内容を外部へ出さない
    http_response_code(500);
    foreach (Response::securityHeaders() as $name => $value) {
        header($name . ': ' . $value, true);
    }
    echo json_encode([
        'ok'      => false,
        'error'   => 'server_error',
        'message' => 'ただいま処理できません。時間をおいてお試しください。',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$kernel->app->handle(Request::fromGlobals())->emit();
