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
            ? '<nav class="nav"><a href="/admin/">案件一覧</a>'
            . '<a href="/admin/retention">保持期限</a>'
            . '<a href="/admin/maintenance">保守</a></nav>'
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

    /* ------------------------------------------------ 制作設定（4F-R3） */

    /**
     * Smart Labo が入力する項目（SSOT v1.9 §3.12）。
     *
     * ★店舗画面には出さない。店舗の回答欄と同じ画面に混ぜない。
     * ★該当が無い場合も「空のまま保存」して**設定した事実**を残す。
     *   書き出しの前に「まだ設定していない」と区別するためである。
     *
     * @param array<string,mixed> $current
     * @param list<string> $missing
     */
    public static function adminSettingsForm(
        string $caseNumber,
        array $current,
        array $missing,
        string $csrf,
    ): string {
        $services = $current['privacy']['external_services'] ?? [];
        $consent  = $current['privacy']['consent_checkbox'] ?? null;

        $state = $missing === []
            ? self::notice('ok', '制作設定は設定済みです。')
            : self::notice('warn', '未設定の項目が ' . count($missing) . ' 件あります。'
                . '設定するまで検証済みJSONを書き出せません。');

        // ★型に合った入力欄を使う。必須・任意も**文字で**書く（4F-R4）
        $text = static function (
            string $name,
            string $label,
            mixed $value,
            int $max,
            string $hint,
            bool $required = true,
            string $type = 'text',
        ): string {
            return '<div class="field">'
                . '<label class="field__label" for="s-' . self::esc($name) . '">' . self::esc($label)
                . ($required ? '<span class="req">必須</span>' : '<span class="muted">空欄可</span>')
                . '</label>'
                . '<input id="s-' . self::esc($name) . '" name="' . self::esc($name) . '" '
                . 'type="' . self::esc($type) . '" '
                . ($required ? 'aria-required="true" ' : '')
                . 'maxlength="' . self::esc($max) . '" value="' . self::esc($value ?? '') . '">'
                . '<p class="lead">' . self::esc($hint) . '</p></div>';
        };

        return '<section class="card">'
            . '<h1 class="card__title">制作設定 — ' . self::esc($caseNumber) . '</h1>'
            . '<p class="lead">ここは <strong>Smart Labo が入力する欄</strong>です。'
            . '店舗の入力画面には出ません。店舗から変更することもできません。</p>'
            . $state
            . '<form method="post" action="/admin/settings/save" autocomplete="off">'
            . '<input type="hidden" name="csrf_token" value="' . self::esc($csrf) . '">'
            . '<input type="hidden" name="case" value="' . self::esc($caseNumber) . '">'
            . $text('salon_booking_url', 'サロン共通の予約URL（W-05）',
                $current['web_links']['salon_booking_url'] ?? null, 500,
                '該当が無い場合は空のままにしてください。入れる場合は https から始まるURLだけです。',
                false, 'url')
            . $text('destination', '情報の送信先（PR-03）',
                $current['privacy']['destination'] ?? null, 200,
                'プライバシーポリシーに載る送信先です。空欄にはできません。')
            . $text('storage', '情報の保管方法（PR-04）',
                $current['privacy']['storage'] ?? null, 200,
                'プライバシーポリシーに載る保管方法です。空欄にはできません。')
            . '<div class="field">'
            . '<label class="field__label" for="s-services">利用する外部サービス（PR-07）</label>'
            . '<textarea id="s-services" name="external_services" rows="4">'
            . self::esc(is_array($services) ? implode("\n", array_map('strval', $services)) : '')
            . '</textarea>'
            . '<p class="lead">1行に1つ。無い場合は空のままにしてください'
            . '（0件が「外部サービスなし」の正式な設定です。10件まで・各60文字まで）。</p></div>'
            . '<div class="field">'
            . '<label class="field__label" for="s-consent">同意チェックの表示（PR-09）'
            . '<span class="req">必須</span></label>'
            . '<select id="s-consent" name="consent_checkbox" aria-required="true">'
            . '<option value=""' . ($consent === null ? ' selected' : '') . '>選択してください</option>'
            . '<option value="true"' . ($consent === true ? ' selected' : '') . '>表示する</option>'
            . '<option value="false"' . ($consent === false ? ' selected' : '') . '>表示しない</option>'
            . '</select>'
            . '<p class="lead">お問い合わせフォームに同意チェックを置くかどうかです。'
            . '「表示しない」も正式な設定です。</p></div>'
            . '<div class="field">'
            . '<label class="field__label" for="s-confirm">確認のため、案件番号をご入力ください</label>'
            . '<input id="s-confirm" name="confirm_case" type="text" autocomplete="off" '
            . 'spellcheck="false" required placeholder="' . self::esc($caseNumber) . '">'
            . '</div>'
            . '<div class="actions">'
            . '<button type="submit" class="btn btn--primary">制作設定を保存する</button>'
            . '<a class="btn btn--outline" href="/admin/case?case=' . rawurlencode($caseNumber) . '">やめる</a>'
            . '</div></form></section>';
    }

    /**
     * 案件詳細に出す「制作設定の状況」。
     * ★値そのものは出さない。設定済みかどうかだけを示す。
     *
     * @param list<string> $missing
     */
    public static function adminSettingsStatus(string $caseNumber, array $missing, bool $settable): string
    {
        $body = $missing === []
            ? '<span class="ok">設定済み</span>'
            : '<span class="ng">' . self::esc(count($missing)) . ' 件未設定</span>';

        $action = $settable
            ? '<a class="btn btn--outline" href="/admin/settings?case=' . rawurlencode($caseNumber) . '">制作設定を開く</a>'
            : '<span class="muted">この状態では設定できません（確認済み・確定のときに設定します）</span>';

        return '<section class="card"><h2 class="card__title">制作設定（Smart Labo 入力）</h2>'
            . '<p class="lead">店舗の入力とは別に、当社が設定する項目です。'
            . '<strong>設定が揃うまで検証済みJSONを書き出せません。</strong></p>'
            . self::row('状況', $body)
            . self::row('未設定', $missing === []
                ? '<span class="muted">なし</span>'
                : '<ul class="list"><li>' . implode('</li><li>', array_map(
                    static fn (string $p): string => self::esc(self::pathDisplay($p)), $missing)) . '</li></ul>')
            . '<div class="actions">' . $action . '</div></section>';
    }

    /* ------------------------------------------------ 入力確定（4F） */

    /**
     * 確定（`reviewed` → `locked`）の確認画面（SSOT v1.7 §5.1）。
     * ★何が起きるかを先に全部見せる。押してから気づく画面にしない。
     */
    public static function lockForm(string $caseNumber, string $csrf): string
    {
        return '<section class="card">'
            . '<h1 class="card__title">入力を確定する</h1>'
            . self::row('案件番号', self::esc($caseNumber))
            . '<div class="notice notice--warn">'
            . '<p class="notice__title">確定すると、次のことが起こります</p>'
            . '<ul class="list">'
            . '<li><strong>店舗のご案内リンクが使えなくなります。</strong>'
            . 'いま開いている入力画面も、その場で使えなくなります。</li>'
            . '<li><strong>修正依頼へ戻せなくなります。</strong>'
            . 'ご案内リンクの再発行もできなくなります。</li>'
            . '<li><strong>入力済みの内容は消えません。</strong>'
            . '確定は「入力を締め切る」という意味であり、削除ではありません。</li>'
            . '<li>確定後に削除予定日を登録すると、期限到来後に機密情報を削除できるようになります。</li>'
            . '</ul></div>'
            . '<form method="post" action="/admin/lock/send" autocomplete="off">'
            . '<input type="hidden" name="csrf_token" value="' . self::esc($csrf) . '">'
            . '<input type="hidden" name="case" value="' . self::esc($caseNumber) . '">'
            . '<div class="field">'
            . '<label class="field__label" for="confirm-case">確認のため、案件番号をご入力ください</label>'
            . '<input id="confirm-case" name="confirm_case" type="text" autocomplete="off" '
            . 'spellcheck="false" required placeholder="' . self::esc($caseNumber) . '">'
            . '</div>'
            . '<div class="actions">'
            . '<button type="submit" class="btn btn--primary">入力を確定する</button>'
            . '<a class="btn btn--outline" href="/admin/case?case=' . rawurlencode($caseNumber) . '">やめる</a>'
            . '</div></form></section>';
    }

    /* ------------------------------------------------ 保持期限（4F） */

    /**
     * 削除予定日の登録（SSOT v1.7 §9.3-1）。
     *
     * ★受け取るのは削除予定日**だけ**。公開日・公開承認・契約情報の欄を作らない。
     *   それらは Smart Labo Operations（未完成の間は標準管理票）の責任である。
     */
    public static function retentionDueForm(string $caseNumber, string $current, string $csrf): string
    {
        return '<form method="post" action="/admin/retention/due" class="subform" autocomplete="off">'
            . '<input type="hidden" name="csrf_token" value="' . self::esc($csrf) . '">'
            . '<input type="hidden" name="case" value="' . self::esc($caseNumber) . '">'
            . '<div class="field">'
            . '<label class="field__label" for="due">削除予定日（YYYY-MM-DD）</label>'
            . '<input id="due" name="due" type="date" required value="' . self::esc($current) . '">'
            . '<p class="lead">公開完了から6か月後の日付を、標準管理票または Operations 側で計算して'
            . 'ここへ転記してください。<strong>公開日はこの画面に入力しないでください。</strong></p>'
            . '</div>'
            . '<div class="actions">'
            . '<button type="submit" class="btn btn--outline">削除予定日を登録する</button>'
            . '</div></form>';
    }

    /**
     * 保持期限の一覧（SSOT v1.7 §9.3-1）。
     * ★案件番号・状態・日付・区分だけ。回答本文・店舗名・Drive 情報を出さない。
     *
     * @param list<array<string,mixed>> $rows
     */
    public static function retentionList(array $rows, string $today, bool $enabled, string $csrf): string
    {
        $counts = ['overdue' => 0, 'due' => 0, 'soon' => 0, 'later' => 0, 'unset' => 0, 'deleted' => 0];
        $items  = '';

        foreach ($rows as $row) {
            $bucket = (string)$row['bucket'];
            $counts[$bucket] = ($counts[$bucket] ?? 0) + 1;
            $number = (string)$row['case_number'];

            $items .= '<tr>'
                . '<td><a href="/admin/case?case=' . rawurlencode($number) . '">' . self::esc($number) . '</a></td>'
                . '<td>' . self::statusLabel((string)$row['status']) . '</td>'
                . '<td>' . self::orDash($row['retention_delete_due']) . '</td>'
                . '<td>' . self::orDash($row['deleted_at']) . '</td>'
                . '<td>' . self::bucketLabel($bucket) . '</td>'
                . '</tr>';
        }
        if ($items === '') {
            $items = '<tr><td colspan="5" class="muted">案件がありません。</td></tr>';
        }

        $summary = '';
        foreach (['overdue', 'due', 'soon', 'later', 'unset', 'deleted'] as $key) {
            $summary .= self::row(self::bucketName($key), self::esc((int)($counts[$key] ?? 0)) . ' 件');
        }

        $state = $enabled
            ? '<p class="notice notice--warn">削除操作が<strong>有効</strong>になっています。'
            . '期限到来した「確定」案件は、案件詳細から機密情報を削除できます。</p>'
            : '<p class="notice notice--ok">削除操作は<strong>無効</strong>です。'
            . 'バックアップの世代・削除方針が確定するまで、この環境では削除を実行できません。</p>';

        return '<section class="card">'
            . '<h1 class="card__title">保持期限</h1>'
            . '<p class="lead">HP Intake が持つのは<strong>削除予定日だけ</strong>です。'
            . '公開日と公開承認は保存しません（Smart Labo Operations の責任範囲です）。</p>'
            . $state
            . self::row('サーバーの日付', self::esc($today))
            . $summary
            . '<div class="tablewrap"><table class="table">'
            . '<thead><tr><th>案件番号</th><th>状態</th><th>削除予定日</th><th>削除実施日</th><th>区分</th></tr></thead>'
            . '<tbody>' . $items . '</tbody></table></div>'
            . '<div class="actions"><a class="btn btn--outline" href="/admin/maintenance">保守（監査・セッション）へ</a></div>'
            . '</section>'
            . '<div class="endrow">'
            . self::actionForm('/admin/logout', $csrf, [], 'ログアウト', 'btn--quiet')
            . '</div>';
    }

    private static function bucketName(string $bucket): string
    {
        return [
            'overdue' => '期限超過',
            'due'     => '期限到来',
            'soon'    => '30日以内',
            'later'   => '期限より前',
            'unset'   => '削除予定日 未設定',
            'deleted' => '削除済み',
        ][$bucket] ?? $bucket;
    }

    /** 区分は色だけでなく**文字**でも示す */
    private static function bucketLabel(string $bucket): string
    {
        return '<span class="badge badge--' . self::esc($bucket) . '">'
            . self::esc(self::bucketName($bucket)) . '</span>';
    }

    /* ------------------------------------------------ 機密情報の削除（4F） */

    /**
     * 削除の確認画面（SSOT v1.7 §9.3）。
     *
     * ★元に戻せないこと・Drive は別作業であること・バックアップにも期限があること・
     *   継続保持は先に移しておくことを、実行前に全部書く。
     * ★誤操作を防ぐため `DELETE <案件番号>` の完全一致入力を求める。
     */
    public static function purgeForm(string $caseNumber, string $due, string $csrf): string
    {
        $phrase = \SmartLabo\Intake\Service\RetentionService::confirmPhrase($caseNumber);

        return '<section class="card">'
            . '<h1 class="card__title">機密情報の削除</h1>'
            . self::row('案件番号', self::esc($caseNumber))
            . self::row('削除予定日', self::esc($due))
            . '<div class="notice notice--error">'
            . '<p class="notice__title">実行すると、元に戻せません</p>'
            . '<ul class="list">'
            . '<li>店舗の<strong>回答本文</strong>を物理削除します。</li>'
            . '<li><strong>修正依頼</strong>（メッセージ・対象項目）を物理削除します。</li>'
            . '<li><strong>提出履歴</strong>を物理削除します。</li>'
            . '<li><strong>token と店舗セッション</strong>を物理削除します。</li>'
            . '<li><strong>素材フォルダのURLと共有先メール</strong>（暗号文）を削除します。</li>'
            . '<li>案件には<strong>案件番号・状態・日付だけ</strong>が残ります。</li>'
            . '</ul></div>'
            . '<div class="notice notice--warn">'
            . '<p class="notice__title">実行の前にご確認ください</p>'
            . '<ul class="list">'
            . '<li><strong>継続保持する情報</strong>（法的同意の証跡・掲載同意・公開承認・素材権利台帳の要点）が、'
            . 'Smart Labo Operations または標準管理票へ<strong>移してあること</strong>。</li>'
            . '<li><strong>Google Drive の実ファイルはここでは消えません。</strong>'
            . 'Drive 側の削除は別作業です。実施日は標準管理票へ残してください。</li>'
            . '<li><strong>バックアップにも保持期限があります。</strong>'
            . '古い世代が残っている間は、そこから復元できてしまいます。</li>'
            . '</ul></div>'
            . '<form method="post" action="/admin/purge/send" autocomplete="off">'
            . '<input type="hidden" name="csrf_token" value="' . self::esc($csrf) . '">'
            . '<input type="hidden" name="case" value="' . self::esc($caseNumber) . '">'
            . '<div class="field">'
            . '<label class="field__label" for="confirm">確認のため、次のとおり入力してください</label>'
            . '<p class="lead"><code>' . self::esc($phrase) . '</code></p>'
            . '<input id="confirm" name="confirm" type="text" autocomplete="off" '
            . 'spellcheck="false" required>'
            . '</div>'
            . '<div class="actions">'
            . '<button type="submit" class="btn btn--danger">機密情報を削除する</button>'
            . '<a class="btn btn--outline" href="/admin/case?case=' . rawurlencode($caseNumber) . '">やめる</a>'
            . '</div></form></section>';
    }

    /**
     * 削除済み案件の詳細（SSOT v1.7 §9.4）。
     * ★残っている最小メタデータだけ。回答・Drive・店舗名の欄そのものを作らない。
     *
     * @param array<string,mixed> $case
     */
    public static function deletedCase(array $case): string
    {
        return '<section class="card">'
            . '<h1 class="card__title">' . self::esc($case['case_number']) . '</h1>'
            . '<p class="notice notice--ok">この案件の機密情報は削除済みです。'
            . '残っているのは案件番号・状態・日付だけです。</p>'
            . self::row('状態', self::statusLabel((string)$case['status']))
            . self::row('契約種別', self::orDash($case['contract_type']))
            . self::row('提出日時', self::orDash($case['submitted_at']))
            . self::row('確定日時', self::orDash($case['locked_at']))
            . self::row('終了日時', self::orDash($case['closed_at']))
            . self::row('削除予定日', self::orDash($case['retention_delete_due']))
            . self::row('削除実施日', self::orDash($case['deleted_at']))
            . '<p class="lead">Google Drive の実ファイルと、継続保持する情報の所在は'
            . 'Smart Labo Operations または標準管理票でご確認ください。</p>'
            . '<div class="actions"><a class="btn btn--outline" href="/admin/retention">保持期限一覧へ</a></div>'
            . '</section>';
    }

    /* ------------------------------------------------ 保守（4F） */

    /**
     * 監査の13か月削除と、管理セッションの清掃（SSOT v1.7 §9.1 / §2.7-8）。
     * ★出すのは**件数だけ**。監査の中身も session hash も HMAC 化IP も描かない。
     */
    public static function maintenance(
        int $auditDue,
        string $cutoff,
        int $sessionsDue,
        bool $enabled,
        string $csrf,
        string $flash = '',
    ): string {
        $notice = $flash === '' ? '' : self::notice('ok', $flash);

        $auditAction = $enabled
            ? self::actionForm('/admin/maintenance/audit', $csrf, [],
                '13か月を過ぎた監査ログを削除する', 'btn--danger')
            : '<p class="muted">削除操作が無効のため実行できません。</p>';

        return $notice
            . '<section class="card">'
            . '<h1 class="card__title">保守</h1>'
            . '<p class="lead">自動実行はしません。実行するときは、この画面から明示的に行います。</p>'
            . '</section>'
            . '<section class="card">'
            . '<h2 class="card__title">監査ログ（13か月保持）</h2>'
            . self::row('保持の境目', self::esc($cutoff))
            . self::row('削除対象', self::esc($auditDue) . ' 件')
            . '<p class="lead">監査ログは本文も個人情報も持ちません（案件・イベント種別・結果・'
            . 'HMAC化したIP・日時のみ）。削除するとHMAC化IPも同時に消えます。</p>'
            . '<p class="lead">この削除自体も監査へ1件残りますが、その行も13か月後に'
            . '同じ規則で削除対象になります（保持が終わらなくなることはありません）。</p>'
            . '<div class="actions">' . $auditAction . '</div>'
            . '</section>'
            . '<section class="card">'
            . '<h2 class="card__title">管理セッション</h2>'
            . self::row('期限切れ・失効済み', self::esc($sessionsDue) . ' 件')
            . '<p class="lead">いま有効なセッションは削除しません。'
            . 'この操作でログアウトさせられることはありません。</p>'
            . '<div class="actions">'
            . self::actionForm('/admin/maintenance/sessions', $csrf, [],
                '期限切れの管理セッションを削除する', 'btn--outline')
            . '</div>'
            . '</section>'
            . '<div class="endrow">'
            . self::actionForm('/admin/logout', $csrf, [], 'ログアウト', 'btn--quiet')
            . '</div>';
    }

    /* ------------------------------------------------ ご案内リンクの再発行 */

    /**
     * 再発行の確認画面（SSOT v1.6 §4.4.1）。
     *
     * ★何が起きるかを先に全部見せる。押してから気づく画面にしない。
     * ★誤操作を防ぐため、案件番号の再入力を求める（完全一致のみ実行）。
     */
    public static function reissueForm(string $caseNumber, string $status, string $csrf): string
    {
        return '<section class="card">'
            . '<h1 class="card__title">ご案内リンクの再発行</h1>'
            . self::row('案件番号', self::esc($caseNumber))
            . self::row('現在の状態', self::statusLabel($status))
            . '<div class="notice notice--warn">'
            . '<p class="notice__title">実行すると、次のことが起こります</p>'
            . '<ul class="list">'
            . '<li><strong>これまでのご案内リンクは使えなくなります。</strong></li>'
            . '<li>店舗がいま開いている入力画面も、その場で使えなくなります。</li>'
            . '<li><strong>入力済みの内容は消えません。</strong>'
            . '新しいリンクから、そのまま続きを入力できます。</li>'
            . '<li>新しいリンクは<strong>次の画面で1回だけ</strong>表示されます。</li>'
            . '</ul></div>'
            . '<form method="post" action="/admin/reissue/send" autocomplete="off">'
            . '<input type="hidden" name="csrf_token" value="' . self::esc($csrf) . '">'
            . '<input type="hidden" name="case" value="' . self::esc($caseNumber) . '">'
            . '<div class="field">'
            . '<label class="field__label" for="confirm-case">確認のため、案件番号をご入力ください</label>'
            . '<input id="confirm-case" name="confirm_case" type="text" autocomplete="off" '
            . 'spellcheck="false" required placeholder="' . self::esc($caseNumber) . '">'
            . '</div>'
            . '<div class="actions">'
            . '<button type="submit" class="btn btn--primary">リンクを再発行する</button>'
            . '<a class="btn btn--outline" href="/admin/case?case=' . rawurlencode($caseNumber) . '">やめる</a>'
            . '</div></form></section>';
    }

    /**
     * 再発行直後の1回だけ、新しいご案内リンクを見せる。
     * ★この画面を離れると再表示できない（SSOT v1.6 §4.4.1-2）。
     */
    public static function reissuedLink(string $caseNumber, string $status, string $token): string
    {
        $url = 'https://intake.smartlaboworks.com/start#' . $token;

        return '<section class="card">'
            . '<h1 class="card__title">ご案内リンクを再発行しました</h1>'
            . self::row('案件番号', self::esc($caseNumber))
            . self::row('現在の状態', self::statusLabel($status))
            . '<div class="notice notice--warn">'
            . '<p class="notice__title">この画面を閉じると再表示できません</p>'
            . '<p>下のリンクを店舗へお伝えください。'
            . '控えを取り忘れた場合は、もう一度発行し直してください'
            . '（そのときも、いま出ているリンクは使えなくなります）。</p>'
            . '</div>'
            . '<div class="field"><label class="field__label" for="issued-link">新しいご案内リンク</label>'
            . '<input id="issued-link" type="text" readonly value="' . self::esc($url) . '"></div>'
            . '<p class="lead">これまでのリンクと、店舗が開いていた入力画面は使えなくなりました。'
            . '入力済みの内容はそのまま残っています。</p>'
            . '<div class="actions">'
            . '<a class="btn btn--primary" href="/admin/case?case=' . rawurlencode($caseNumber) . '">案件詳細へ</a>'
            . '<a class="btn btn--outline" href="/admin/">案件一覧へ</a>'
            . '</div></section>';
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
