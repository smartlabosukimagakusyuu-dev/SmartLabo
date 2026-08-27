<?php
/**
 * HP Intake API — §3 の正式なデータパス一覧（SSOT v1.5 §2.8-1）。
 *
 * 修正依頼（intake_revision_requests.requested_paths_json）が指してよいのは、
 * **この一覧に載っているものだけ**である。未知のパスを含む要求は丸ごと拒否する。
 *
 * 形は2種類:
 *   'basic'             … 分類そのもの（その分類ぜんぶを見直してほしい）
 *   'basic.legal_name'  … 分類 + 項目
 *
 * ★この一覧は画面側の定義（public/assets/lib/schema.js）から機械的に生成している。
 *   実体は AnswerSchema::PATHS（生成物）。**手で書き換えない。**
 *   §3 を変えたときは schema.js を直してから作り直す:
 *     node intake-api/dev/generate-answer-schema.mjs
 */
declare(strict_types=1);

namespace SmartLabo\Intake;

final class AnswerPaths
{
    /**
     * §3 の正式なデータパス129件。
     *
     * ★実体は **生成物** `AnswerSchema::PATHS`（`dev/generate-answer-schema.mjs` が
     *   `public/assets/lib/schema.js` から作る）。ここへ書き写さない。
     *   v1.8（4F-R1）より前は、この定数へ一覧を貼り付けていた。
     *   同じものを2か所に置くと、いつか片方だけが古くなる。
     *
     * @var list<string>
     */
    public const ALL = AnswerSchema::PATHS;

    public static function isValid(string $path): bool
    {
        return in_array($path, self::ALL, true);
    }

    /**
     * 要求されたパスを検査して正規化する。
     *
     * ★1つでも未知のパスがあれば**丸ごと拒否**する（一部だけ受け入れない）。
     * ★重複は取り除く。順序は入力順を保つ（担当者が並べた意図を壊さない）。
     *
     * @param mixed $paths
     * @return array{ok:bool,paths?:list<string>,error?:string}
     */
    public static function normalize(mixed $paths): array
    {
        if (!is_array($paths) || $paths === []) {
            return ['ok' => false, 'error' => 'empty'];
        }
        if (count($paths) > count(self::ALL)) {
            return ['ok' => false, 'error' => 'too_many'];
        }

        $out  = [];
        $seen = [];
        foreach ($paths as $path) {
            if (!is_string($path) || !self::isValid($path)) {
                // ★どのパスが不正だったかは返さない（内部の一覧を推測させない）
                return ['ok' => false, 'error' => 'unknown_path'];
            }
            if (isset($seen[$path])) {
                continue;
            }
            $seen[$path] = true;
            $out[]       = $path;
        }

        return ['ok' => true, 'paths' => $out];
    }
}
