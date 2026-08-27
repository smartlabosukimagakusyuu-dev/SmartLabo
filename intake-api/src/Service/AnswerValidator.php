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
    public static function check(array $sections): array
    {
        foreach ($sections as $name => $value) {
            if (!is_string($name) || !isset(AnswerSchema::STRUCTURE[$name])) {
                return ['ok' => false, 'error' => 'unknown_section'];
            }
            if (!self::matches($value, AnswerSchema::STRUCTURE[$name], 1)) {
                return ['ok' => false, 'error' => 'unknown_path'];
            }
        }

        return ['ok' => true];
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
