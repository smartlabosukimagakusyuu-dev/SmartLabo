<?php
/**
 * HP Intake API — 回答 JSON の厳格検査と絞り込み（SSOT v1.8 §3.0-9 / §6.1）。
 *
 * 4F で見つかった穴をふさぐためのクラスである。
 * それまでは**分類名（11種）だけ**を見ており、分類の中身のキーは素通しだった。
 * その結果、未知キーがそのまま保存され、「検証済み JSON」にも出ていた。
 *
 * 役割は2つ。**方向が違う**ので混ぜない。
 *
 *   check()  … 受け取るとき（POST /answers/save）
 *              未知キーが1つでもあれば **要求全体を拒否**する。
 *              黙って捨てて保存しない。部分保存もしない。
 *
 *   filter() … 出すとき（GET /case・管理画面・書き出し）
 *              既存DBに未知キーが残っていても **出力しない**。
 *              未知キーがあるだけで画面を落とさない。
 *
 * ★どちらも `AnswerSchema`（schema.js からの生成物）だけを正とする。
 * ★未知キーの**名前も値も**、応答・ログ・監査へ出さない。
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Service;

use SmartLabo\Intake\AnswerSchema;

final class AnswerValidator
{
    /** 誰からの要求か。店舗と Smart Labo で書ける範囲が違う（SSOT v1.9 §3.12） */
    public const AUDIENCE_STORE = 'store';
    public const AUDIENCE_ADMIN = 'admin';

    /**
     * 入れ子の深さの上限。
     * ★正式構造の最大は「分類 → 項目 → 配列要素 → キー」の4段である。
     *   それを超える入力は、たとえ未知キーが無くても受け付けない。
     */
    public const MAX_DEPTH = 6;

    /**
     * 保存要求を検査する。
     *
     * ★見つけた未知キーの**名前を返さない**。内部の一覧を推測させないため
     *   （`AnswerPaths::normalize()` と同じ方針）。
     *
     * @param array<string,mixed> $sections
     * @return array{ok:bool,error?:string}
     */
    public static function check(array $sections, string $audience = self::AUDIENCE_STORE): array
    {
        foreach ($sections as $name => $value) {
            if (!is_string($name) || !isset(AnswerSchema::STRUCTURE[$name])) {
                return ['ok' => false, 'error' => 'unknown_section'];
            }
            if (!self::matches($value, AnswerSchema::STRUCTURE[$name], 1)) {
                return ['ok' => false, 'error' => 'unknown_path'];
            }
            // ★語彙の検査（4F-R3 §5）。正式な選択肢以外は保存させない。
            //   未入力（null / ""）は語彙を見ない。回答済みかどうかは必須側で判定する。
            if (!self::vocabularyOk($name, (array)$value)) {
                return ['ok' => false, 'error' => 'unknown_value'];
            }
            // ★書ける範囲の検査（4F-R3）。
            //   店舗は Smart Labo 設定を書けない。Smart Labo 設定の要求に
            //   店舗項目が混ざっていても拒否する。どちらも**要求全体を拒否**。
            if (!self::audienceOk($name, (array)$value, $audience)) {
                return ['ok' => false, 'error' => 'forbidden_path'];
            }
        }

        return ['ok' => true];
    }

    /**
     * 語彙が決まっている項目に、正式な値だけが入っているか。
     * ★どの値が不正だったかは返さない（内部の一覧を推測させない）。
     */
    private static function vocabularyOk(string $section, array $value): bool
    {
        foreach (AnswerSchema::ENUMS as $path => $allowed) {
            if (!str_starts_with($path, $section . '.')) {
                continue;
            }
            $key = substr($path, strlen($section) + 1);

            // 繰り返し分類（menus / image_metadata）は要素ごとに見る
            $rows = array_is_list($value) ? $value : [$value];
            foreach ($rows as $row) {
                if (!is_array($row) || !array_key_exists($key, $row)) {
                    continue;
                }
                $given = $row[$key];
                if ($given === null || $given === '') {
                    continue;  // 未入力
                }
                foreach (is_array($given) ? $given : [$given] as $one) {
                    if (!in_array($one, $allowed, true)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /** その分類の直下キーが、送り主の書ける範囲に収まっているか */
    private static function audienceOk(string $section, array $value, string $audience): bool
    {
        foreach (array_keys($value) as $key) {
            $path    = $section . '.' . $key;
            $isAdmin = in_array($path, AnswerSchema::ADMIN_PATHS, true);
            if ($audience === self::AUDIENCE_ADMIN ? !$isAdmin : $isAdmin) {
                return false;
            }
        }

        return true;
    }

    /**
     * 片方の担当ぶんだけを差し替え、もう片方はそのまま残す。
     *
     * ★店舗の保存で Smart Labo 設定が消えない。逆も同じ。
     *   店舗は分類まるごとを送ってくるため、素直に上書きすると
     *   管理設定が毎回消えてしまう（4F-R3 で作り込んだ穴を塞ぐ）。
     *
     * @param array<string,mixed> $existing 保存済みの分類
     * @param array<string,mixed> $incoming 送られてきた分類
     * @return array<string,mixed>
     */
    public static function mergeKeeping(array $existing, array $incoming, string $audience, string $section): array
    {
        $out = $incoming;
        foreach ($existing as $key => $value) {
            $path    = $section . '.' . $key;
            $isAdmin = in_array($path, AnswerSchema::ADMIN_PATHS, true);
            // 送り主が書けないキーは、保存済みの値をそのまま残す
            if ($audience === self::AUDIENCE_ADMIN ? !$isAdmin : $isAdmin) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * 送り先に応じて絞り込む。
     *
     * ★店舗へ Smart Labo 設定を返さない（§3.12）。
     *
     * @param array<string,mixed> $sections
     * @return array<string,mixed>
     */
    public static function filterForStore(array $sections): array
    {
        $out = self::filter($sections);
        foreach ($out as $name => $value) {
            if (!is_array($value)) {
                continue;
            }
            foreach (array_keys($value) as $key) {
                if (in_array($name . '.' . $key, AnswerSchema::ADMIN_PATHS, true)) {
                    unset($out[$name][$key]);
                }
            }
        }

        return $out;
    }

    /**
     * 保存済みの回答から、正式な値だけを取り出す。
     *
     * ★`check()` を通っていない古い行（4F-R1 より前に入った未知キー）を
     *   読み出すためにある。落とさず、黙って除く。
     *
     * @param array<string,mixed> $sections
     * @return array<string,mixed>
     */
    public static function filter(array $sections): array
    {
        $out = [];
        foreach (AnswerSchema::SECTIONS as $name) {
            if (!array_key_exists($name, $sections)) {
                continue;
            }
            $out[$name] = self::pick($sections[$name], AnswerSchema::STRUCTURE[$name], 1);
        }

        return $out;
    }

    /* ------------------------------------------------------------ 検査 */

    /**
     * 値が正式構造どおりかを見る。
     * @param array<string,mixed> $node
     */
    private static function matches(mixed $value, array $node, int $depth): bool
    {
        if ($depth > self::MAX_DEPTH) {
            return false;
        }

        return match ($node['type']) {
            'scalar'  => self::isScalarValue($value),
            'bool'    => is_bool($value) || $value === null,
            'list'    => self::isScalarList($value),
            'object'  => self::matchesObject($value, $node['fields'], $depth),
            'objects' => self::matchesObjects($value, $node['item'], $depth),
            default   => false,
        };
    }

    /** 文字列・数値・真偽・null。★配列やオブジェクトは通さない */
    private static function isScalarValue(mixed $value): bool
    {
        return $value === null || is_string($value) || is_int($value)
            || is_float($value) || is_bool($value);
    }

    /** scalar だけを並べた配列（連番であること） */
    private static function isScalarList(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (!is_array($value) || !array_is_list($value)) {
            return false;
        }
        foreach ($value as $item) {
            if (!self::isScalarValue($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 決まったキーだけを持つオブジェクト。
     * ★`__proto__` / `constructor` / `prototype` も「一覧に無いキー」として落ちる。
     * @param array<string,array<string,mixed>> $fields
     */
    private static function matchesObject(mixed $value, array $fields, int $depth): bool
    {
        if ($value === null) {
            return true;
        }
        // ★連番配列をオブジェクトの位置へ入れさせない（[] は空オブジェクト扱いで許す）
        if (!is_array($value) || (array_is_list($value) && $value !== [])) {
            return false;
        }
        foreach ($value as $key => $child) {
            if (!is_string($key) || !isset($fields[$key])) {
                return false;
            }
            if (!self::matches($child, $fields[$key], $depth + 1)) {
                return false;
            }
        }

        return true;
    }

    /**
     * オブジェクトを並べた配列。
     * @param array<string,array<string,mixed>> $item
     */
    private static function matchesObjects(mixed $value, array $item, int $depth): bool
    {
        if ($value === null) {
            return true;
        }
        if (!is_array($value) || !array_is_list($value)) {
            return false;
        }
        foreach ($value as $row) {
            if (!self::matchesObject($row, $item, $depth + 1)) {
                return false;
            }
            // ★要素そのものが scalar だった場合を弾く（matchesObject は null を許すため）
            if (!is_array($row)) {
                return false;
            }
        }

        return true;
    }

    /* ------------------------------------------------------------ 絞り込み */

    /** @param array<string,mixed> $node */
    private static function pick(mixed $value, array $node, int $depth): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            return null;
        }

        return match ($node['type']) {
            'scalar'  => self::isScalarValue($value) ? $value : null,
            'bool'    => is_bool($value) ? $value : null,
            'list'    => self::isScalarList($value) ? ($value ?? []) : [],
            'object'  => self::pickObject($value, $node['fields'], $depth),
            'objects' => self::pickObjects($value, $node['item'], $depth),
            default   => null,
        };
    }

    /**
     * @param array<string,array<string,mixed>> $fields
     * @return array<string,mixed>
     */
    private static function pickObject(mixed $value, array $fields, int $depth): array
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($fields as $key => $child) {
            if (array_key_exists($key, $value)) {
                $out[$key] = self::pick($value[$key], $child, $depth + 1);
            }
        }

        return $out;
    }

    /**
     * @param array<string,array<string,mixed>> $item
     * @return list<array<string,mixed>>
     */
    private static function pickObjects(mixed $value, array $item, int $depth): array
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $row) {
            if (is_array($row)) {
                $out[] = self::pickObject($row, $item, $depth + 1);
            }
        }

        return $out;
    }
}
