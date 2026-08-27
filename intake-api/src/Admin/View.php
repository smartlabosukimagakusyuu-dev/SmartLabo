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

    /* ------------------------------------------------------------ 修正依頼 */

    /**
     * 修正依頼の入力画面（SSOT v1.5 §2.8）。
     *
     * ★選べるのは §3 の正式パスだけ。画面に出す一覧そのものを allowlist から作る。
     * @param list<string> $missing サーバーが判定した不足パス（初期チェック用）
     */
    public static function revisionForm(string $caseNumber, string $csrf, array $missing = []): string
    {
        $preselect = [];
        foreach ($missing as $path) {
            // menus[0].price_inc_tax → menus.price_inc_tax へ寄せる
            $normalized = (string)preg_replace('/\[\d+\]/', '', (string)$path);
            $preselect[$normalized] = true;
        }

        $groups = '';
        foreach (self::pathGroups() as $sectionKey => $paths) {
            $items = '';
            foreach ($paths as $path) {
                $id = 'p-' . preg_replace('/[^A-Za-z0-9_]/', '_', $path);
                $items .= '<label class="checkline" for="' . self::esc($id) . '">'
                    . '<input type="checkbox" id="' . self::esc($id) . '" name="paths[]"'
                    . ' value="' . self::esc($path) . '"'
                    . (isset($preselect[$path]) ? ' checked' : '') . '>'
                    . '<span class="checkline__text">' . self::esc(self::pathLabel($path)) . '</span>'
                    . '</label>';
            }
            $groups .= '<details class="group"' . (self::groupHasChecked($paths, $preselect) ? ' open' : '') . '>'
                . '<summary class="group__title">' . self::esc(self::sectionLabel($sectionKey)) . '</summary>'
                . '<div class="checks">' . $items . '</div></details>';
        }

        return '<section class="card">'
            . '<h1 class="card__title">修正を依頼する — ' . self::esc($caseNumber) . '</h1>'
            . '<p class="lead">直していただきたい項目をお選びください。'
            . '確定すると案件は「修正依頼中」になり、店舗が再入力できるようになります。</p>'
            . '<form method="post" action="/admin/revision/send">'
            . '<input type="hidden" name="csrf_token" value="' . self::esc($csrf) . '">'
            . '<input type="hidden" name="case" value="' . self::esc($caseNumber) . '">'
            . $groups
            . '<div class="field"><label class="field__label" for="rev-message">店舗へのメッセージ（任意・1000文字まで）</label>'
            . '<textarea id="rev-message" name="message" rows="5" maxlength="1000"></textarea></div>'
            . '<div class="actions">'
            . '<button type="submit" class="btn btn--primary">この内容で修正を依頼する</button>'
            . '<a class="btn btn--outline" href="/admin/case?case=' . rawurlencode($caseNumber) . '">やめる</a>'
            . '</div></form></section>';
    }

    /** @param list<string> $paths @param array<string,bool> $preselect */
    private static function groupHasChecked(array $paths, array $preselect): bool
    {
        foreach ($paths as $p) {
            if (isset($preselect[$p])) {
                return true;
            }
        }

        return false;
    }

    /**
     * 正式パスを分類ごとにまとめる。
     * @return array<string,list<string>>
     */
    public static function pathGroups(): array
    {
        $groups = [];
        foreach (\SmartLabo\Intake\AnswerPaths::ALL as $path) {
            $section = str_contains($path, '.') ? substr($path, 0, strpos($path, '.')) : $path;
            $groups[$section][] = $path;
        }

        return $groups;
    }

    private static function sectionLabel(string $key): string
    {
        $labels = [
            'basic' => '基本情報', 'business_hours' => '営業時間・定休日', 'menus' => 'メニュー・料金',
            'staff' => 'スタッフ', 'promotion' => 'お店の特徴', 'design' => 'デザインのご希望',
            'web_links' => 'SNS・外部リンク', 'contact_form' => 'お問い合わせフォーム',
            'privacy' => 'プライバシー', 'image_metadata' => '写真・素材', 'rights' => '権利・同意',
        ];

        return $labels[$key] ?? $key;
    }

    /** 画面に出す項目名。パスそのものは補助として小さく添える */
    private static function pathLabel(string $path): string
    {
        if (!str_contains($path, '.')) {
            return self::sectionLabel($path) . '（分類ぜんぶ）';
        }

        return substr($path, strpos($path, '.') + 1);
    }

    /** 分類名を添えた表示（一覧で使う） */
    public static function pathDisplay(string $path): string
    {
        if (!str_contains($path, '.')) {
            return self::sectionLabel($path) . '（分類ぜんぶ）';
        }
        $section = substr($path, 0, strpos($path, '.'));

        return self::sectionLabel($section) . ' / ' . substr($path, strpos($path, '.') + 1);
    }

    /* ------------------------------------------------------------ 案件作成 */

    public static function newCaseForm(string $csrf): string
    {
        return '<section class="card">'
            . '<h1 class="card__title">新しいHP制作案件</h1>'
            . '<p class="lead">案件番号はこちらで採番します。'
            . '素材フォルダの情報は、あとから設定することもできます。</p>'
            . '<form method="post" action="/admin/create" autocomplete="off">'
            . '<input type="hidden" name="csrf_token" value="' . self::esc($csrf) . '">'
            . '<div class="field"><label class="field__label" for="c-name">管理用の名前</label>'
            . '<input id="c-name" name="shop_display_name" type="text" maxlength="100" required>'
            . '<p class="lead">社内での見分け用です。公開ホームページの表示名とは別で構いません。</p></div>'
            . '<div class="field"><label class="field__label" for="c-type">契約区分</label>'
            . '<select id="c-type" name="contract_type">'
            . '<option value="standalone">単体</option>'
            . '<option value="salon">サロン</option>'
            . '</select></div>'
            . '<div class="field"><label class="field__label" for="c-drive">素材フォルダのURL（任意）</label>'
            . '<input id="c-drive" name="drive_url" type="url" maxlength="500" '
            . 'placeholder="https://drive.google.com/drive/folders/..."></div>'
            . '<div class="field"><label class="field__label" for="c-mail">フォルダの共有先メール（任意）</label>'
            . '<input id="c-mail" name="drive_shared_email" type="email" maxlength="254">'
            . '<p class="lead">店舗の画面に「このフォルダは○○にのみ共有しています」と表示します。</p></div>'
            . '<div class="actions">'
            . '<button type="submit" class="btn btn--primary">案件を作成してご案内リンクを発行する</button>'
            . '</div></form></section>';
    }

    /**
     * 作成直後の1回だけ、ご案内リンクを見せる。
     *
     * ★この画面を離れると再表示できない。再表示の経路を作らない（SSOT §4.1）。
     * ★リンクは input の value に置く（コピーしやすく、複製を増やさない）。
     */
    public static function createdCase(string $caseNumber, string $token): string
    {
        $url = 'https://intake.smartlaboworks.com/start#' . $token;

        return '<section class="card">'
            . '<h1 class="card__title">案件を作成しました</h1>'
            . self::row('案件番号', self::esc($caseNumber))
            . '<div class="notice notice--warn">'
            . '<p class="notice__title">この画面を閉じると再表示できません</p>'
            . '<p>下のリンクを店舗へお伝えください。'
            . '控えを取り忘れた場合は、案件詳細から発行し直してください（前のリンクは使えなくなります）。</p>'
            . '</div>'
            . '<div class="field"><label class="field__label" for="issued-link">ご案内リンク</label>'
            . '<input id="issued-link" type="text" readonly value="' . self::esc($url) . '"></div>'
            . '<div class="actions">'
            . '<a class="btn btn--primary" href="/admin/case?case=' . rawurlencode($caseNumber) . '">案件詳細へ</a>'
            . '<a class="btn btn--outline" href="/admin/">案件一覧へ</a>'
            . '</div></section>';
    }
}
