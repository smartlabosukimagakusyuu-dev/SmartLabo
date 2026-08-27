<?php
/**
 * HP Intake API — 管理画面の HTML 組み立て（SSOT v1.4 §10.8）。
 *
 * ★利用者入力を HTML として解釈させない。値は必ず esc() を通す。
 *   このファイルの外で HTML 文字列を組み立てない。
 * ★インラインスクリプトを置かない。JavaScript を使わない画面にする
 *   （CSP `script-src 'self'` と噛み合わせるため）。
 * ★token hash / session hash / ip_hmac / 鍵 / rate limit / 内部エラーを描かない。
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Admin;

final class View
{
    /** 値のエスケープ（SSOT §3.0-6 の ESC） */
    public static function esc(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** 複数行のエスケープ（ESC+NL2BR） */
    public static function escLines(mixed $value): string
    {
        return nl2br(self::esc($value), false);
    }

    /**
     * https のリンクだけを作る。それ以外は**文字のまま**出す（SSOT §3.7 の検証）。
     */
    public static function link(?string $url, ?string $label = null): string
    {
        $text = self::esc($label ?? (string)$url);
        if (!is_string($url) || strncmp($url, 'https://', 8) !== 0) {
            return $text;
        }

        return '<a href="' . self::esc($url) . '" rel="noopener noreferrer" target="_blank">' . $text . '</a>';
    }

    /** 空欄の表示 */
    public static function orDash(mixed $value): string
    {
        return $value === null || $value === '' ? '<span class="muted">—</span>' : self::esc($value);
    }

    /**
     * ページ全体。
     * ★`$body` は呼び出し側で**すでにエスケープ済み**の HTML 断片であること。
     */
    public static function page(string $title, string $body, bool $withNav = true): string
    {
        $nav = $withNav
            ? '<nav class="nav"><a href="/admin/">案件一覧</a></nav>'
            : '';

        return '<!DOCTYPE html>'
            . '<html lang="ja"><head>'
            . '<meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta name="robots" content="noindex, nofollow, noarchive">'
            . '<meta name="referrer" content="no-referrer">'
            . '<title>' . self::esc($title) . '｜Smart Labo 内部</title>'
            . '<link rel="stylesheet" href="/assets/admin.css">'
            . '</head><body>'
            . '<header class="head"><span class="head__title">Smart Labo 内部確認</span>' . $nav . '</header>'
            . '<main class="wrap">' . $body . '</main>'
            . '</body></html>';
    }

    /** 状態を色だけでなく**文字**でも示す */
    public static function statusLabel(string $status): string
    {
        $labels = [
            'draft'          => '入力中',
            'submitted'      => '提出済み',
            'needs_revision' => '修正依頼中',
            'reviewed'       => '確認済み',
            'locked'         => '確定',
            'closed'         => '終了',
        ];

        return '<span class="badge badge--' . self::esc($status) . '">'
            . self::esc($labels[$status] ?? $status) . '</span>';
    }

    /** 状態を変える form（CSRF 必須・POST のみ） */
    public static function actionForm(string $action, string $csrf, array $fields, string $label, string $class = ''): string
    {
        $hidden = '<input type="hidden" name="csrf_token" value="' . self::esc($csrf) . '">';
        foreach ($fields as $name => $value) {
            $hidden .= '<input type="hidden" name="' . self::esc($name) . '" value="' . self::esc($value) . '">';
        }

        return '<form method="post" action="' . self::esc($action) . '" class="inline">'
            . $hidden
            . '<button type="submit" class="btn ' . self::esc($class) . '">' . self::esc($label) . '</button>'
            . '</form>';
    }

    /** お知らせ（固定文言のみ。内部エラーを流し込まない） */
    public static function notice(string $kind, string $message): string
    {
        return '<p class="notice notice--' . self::esc($kind) . '">' . self::esc($message) . '</p>';
    }

    /** 定義リストの1行 */
    public static function row(string $key, string $valueHtml): string
    {
        return '<div class="row"><span class="row__key">' . self::esc($key) . '</span>'
            . '<span class="row__val">' . $valueHtml . '</span></div>';
    }
}
