<?php
/**
 * HP Intake API — APP_ROOT（アプリ本体の置き場所）の解決と検査
 * （HP-ONBOARDING-4H-R0 / SSOT v1.12 §10.11）。
 *
 * 4H-PRE で判明した問題:
 *   これまで `public/index.php` は `__DIR__ . '/../src/Autoload.php'` を読み、
 *   設定も `src/../private/` から読んでいた。つまり **docroot の親が APP_ROOT**
 *   であることを強制していた。
 *   XServer のサブドメインは `smartlaboworks.com/public_html/<sub>/` の下に作られる。
 *   この形だと docroot の親は `public_html/` であり、そこへ `src/` と `private/`
 *   を置くことになる。**公開領域にアプリ本体と秘密を置く配置**になってしまう。
 *
 * v1.12 で確定した方針:
 *   docroot と APP_ROOT が**兄弟である必要をなくす**。
 *   APP_ROOT は次の順で解決し、**どの経路でも同じ検査**を通す。
 *
 *     1. 定数 INTAKE_APP_ROOT（`.user.ini` の auto_prepend_file が読む
 *        **非公開 bootstrap** が定義する。第一候補）
 *     2. 環境変数 INTAKE_APP_ROOT（CLI・ローカル確認）
 *     3. 既定（このファイルから見た1つ上＝リポジトリ配置。ローカルとテスト）
 *
 * ★**Web の入力から APP_ROOT を変えられない。**
 *   URL・query・POST・Cookie・ヘッダーを読まない。
 *   このファイルはスーパーグローバルへ一切触れない。
 * ★docroot の中に APP_ROOT を書いたファイルを置かない（`.app-root.php` 方式は不採用）。
 * ★public 側のコードへ絶対パスを直接書かない。
 * ★未設定・不正・不在・公開領域内は **fail closed**（例外。既定へ黙って落ちない）。
 * ★解決した絶対パスを応答・ログ・HTML・JSON へ出さない。
 */
declare(strict_types=1);

namespace SmartLabo\Intake;

use SmartLabo\Intake\Support\PathPolicy;

final class AppRoot
{
    /** 非公開 bootstrap が定義する定数名／環境変数名（同じ名前で揃える） */
    public const NAME = 'INTAKE_APP_ROOT';

    /**
     * auto_prepend_file が使えない環境の代替方式で探す相対位置。
     *
     * docroot の祖先 A に対して `A/private/hp-intake/src/Autoload.php` を探す。
     * ★**絶対パスではなく相対の約束**である。public 側へ実パスを書かずに済む。
     */
    public const DISCOVERY_RELATIVE = 'private/hp-intake';

    /** 祖先をさかのぼる上限。無制限に上がってルートまで探しに行かない */
    public const DISCOVERY_MAX_DEPTH = 6;

    /**
     * 明示的に設定された値（定数 → 環境変数）。未設定なら null。
     * ★ここで既定へ落とさない。「明示された値が不正」と「未設定」を区別するため。
     */
    public static function configured(): ?string
    {
        if (defined(self::NAME)) {
            $value = constant(self::NAME);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }
        $env = getenv(self::NAME);

        return is_string($env) && trim($env) !== '' ? trim($env) : null;
    }

    /**
     * 既定の APP_ROOT（このファイルの1つ上＝`intake-api/`）。
     * ★リポジトリのままの配置・ローカル確認・テストで使う。
     *   本番でこの既定が公開領域を指す場合は `check()` が落とす。
     */
    public static function defaultRoot(): string
    {
        return PathPolicy::normalize(dirname(__DIR__));
    }

    /**
     * APP_ROOT として受け付けてよいかを検査する。
     *
     * 通す条件:
     *   1. PathPolicy の共通規則（絶対・`..` なし・公開領域でない・
     *      ホーム直下でない・ルート直下でない・実在・symlink でない・
     *      realpath 解決後も同じ）
     *   2. `<dir>/src/Autoload.php` が実在する（アプリ本体がそこにある）
     *
     * ★書き込み可であることは要求しない（`src/` は読み取りだけで足りる）。
     *
     * @return array{ok:bool,error?:string,dir?:string}
     */
    public static function check(?string $raw): array
    {
        $checked = PathPolicy::checkDir($raw, requireWritable: false);
        if ($checked['ok'] !== true) {
            return $checked;
        }
        $dir = (string)$checked['dir'];

        if (!is_file($dir . '/src/Autoload.php')) {
            return ['ok' => false, 'error' => 'no_src'];
        }

        return ['ok' => true, 'dir' => $dir];
    }

    /**
     * 解決して検査まで済ませた APP_ROOT を返す。
     *
     * ★**明示された値が不正なら、既定へ落とさずに例外**にする（fail closed）。
     *   「設定を間違えたまま、たまたま動いてしまう」経路を作らない。
     * ★例外の文言に**パスを含めない**（外へ出るため）。理由コードだけを載せる。
     *
     * @param string|null $override テスト・CLI から直接与える値
     * @throws ConfigException
     */
    public static function require(?string $override = null): string
    {
        $explicit = $override !== null && trim($override) !== '' ? trim($override) : self::configured();
        $candidate = $explicit ?? self::defaultRoot();

        $checked = self::check($candidate);
        if ($checked['ok'] !== true) {
            // ★ここで既定へ落ちない。設定ミスは必ず止める
            throw new ConfigException('app_root is not usable: ' . (string)$checked['error']);
        }

        return (string)$checked['dir'];
    }

    /**
     * docroot の祖先から**非公開の APP_ROOT** を探す（auto_prepend_file の代替方式）。
     *
     * `A/private/hp-intake/src/Autoload.php` が見つかった最初の A を採る。
     * ★見つかった候補も `check()` を通す。公開領域の下にあれば採用しない。
     * ★docroot 自身は候補にしない（1つ上から探す）。
     *
     * @return string|null 検査を通った APP_ROOT。見つからなければ null
     */
    public static function discoverFrom(string $docroot): ?string
    {
        $dir = PathPolicy::normalize($docroot);

        for ($depth = 0; $depth < self::DISCOVERY_MAX_DEPTH; ++$depth) {
            $parent = PathPolicy::normalize(dirname($dir));
            if ($parent === $dir) {
                break; // ルートへ到達した
            }
            $dir = $parent;

            $candidate = $dir . '/' . self::DISCOVERY_RELATIVE;
            if (!is_file($candidate . '/src/Autoload.php')) {
                continue;
            }
            $checked = self::check($candidate);
            if ($checked['ok'] === true) {
                return (string)$checked['dir'];
            }
        }

        return null;
    }
}
