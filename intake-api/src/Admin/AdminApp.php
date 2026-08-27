<?php
/**
 * HP Intake API — 内部確認画面（SSOT v1.4 §10.8 / §11.1-7 / §11.1-8 / §11.3）。
 *
 * 経路（すべて /admin/ 配下。店舗向けとパスを分ける）:
 *   GET  /admin/login    ログイン画面
 *   POST /admin/login    ログイン
 *   POST /admin/logout   ログアウト（★GET では受けない）
 *   GET  /admin/         案件一覧
 *   GET  /admin/case     案件詳細
 *   POST /admin/status   状態変更（reviewed へ）
 *   GET  /admin/revision      修正依頼の入力（4D-R1）
 *   POST /admin/revision/send 修正依頼の確定（＋ needs_revision へ差し戻し）
 *   GET  /admin/new           新しい案件の入力（4D-R1）
 *   POST /admin/create        案件作成 ＋ ご案内リンクの発行
 *   GET  /admin/reissue       ご案内リンク再発行の確認（4D-R2）
 *   POST /admin/reissue/send  再発行の実行（旧token・店舗sessionを失効）
 *   GET  /admin/lock          入力確定（locked）の確認（4F）
 *   POST /admin/lock/send     確定の実行（token・店舗sessionを失効）
 *   GET  /admin/retention     保持期限の一覧（4F）
 *   POST /admin/retention/due 削除予定日の登録・変更
 *   GET  /admin/purge         機密情報の削除の確認（4F）
 *   POST /admin/purge/send    削除の実行（★元に戻せない）
 *   GET  /admin/maintenance   監査13か月削除・管理session清掃の件数表示（4F）
 *   POST /admin/maintenance/audit    監査の13か月削除
 *   POST /admin/maintenance/sessions 期限切れ管理sessionの削除
 *   GET  /admin/settings      制作設定（Smart Labo 入力・4F-R3）
 *   POST /admin/settings/save 制作設定の保存
 *   GET  /admin/export   検証済み JSON のダウンロード
 *
 * 守ること:
 *   - 未認証で到達できる画面を作らない。未認証時に案件の存在を漏らさない
 *   - 状態を変える操作は**すべて POST ＋ CSRF ＋ Origin 検査**
 *   - 管理者ID・パスワード・session・CSRF・回答本文をログへ出さない
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Admin;

use SmartLabo\Intake\Config;
use SmartLabo\Intake\Http\Guard;
use SmartLabo\Intake\Http\Request;
use SmartLabo\Intake\Http\Response;
use SmartLabo\Intake\AnswerPaths;
use SmartLabo\Intake\Migrator;
use SmartLabo\Intake\Service\AdminAuth;
use SmartLabo\Intake\Service\AnswerService;
use SmartLabo\Intake\Service\Audit;
use SmartLabo\Intake\Service\CaseService;
use SmartLabo\Intake\Service\ExportService;
use SmartLabo\Intake\Service\RateLimiter;
use SmartLabo\Intake\Service\RetentionService;
use SmartLabo\Intake\Service\RevisionRequestService;
use SmartLabo\Intake\Service\TokenService;
use SmartLabo\Intake\Support\Logger;

final class AdminApp
{
    /** 外部へ出す文言は固定。内部の理由を混ぜない */
    private const MSG_LOGIN_FAILED = 'IDまたはパスワードが正しくありません。';
    private const MSG_RATE_LIMITED = '短時間に操作が集中しました。しばらくおいてからお試しください。';
    private const MSG_FORBIDDEN    = 'この操作は許可されていません。';
    private const MSG_NOT_FOUND    = 'この画面は表示できません。';
    private const MSG_CONFLICT     = '別の操作で状態が変わっています。最新の内容をご確認ください。';

    /**
     * Smart Labo の制作設定を変更してよい案件状態（SSOT v1.9 §3.12）。
     * ★店舗の確認が済んでから設定する。`closed` / 削除済みは変更しない。
     */
    private const SETTABLE_STATUSES = ['reviewed', 'locked'];

    public function __construct(
        private readonly Config $config,
        private readonly Guard $guard,
        private readonly AdminAuth $auth,
        private readonly RateLimiter $rateLimiter,
        private readonly CaseService $cases,
        private readonly AnswerService $answers,
        private readonly ExportService $export,
        private readonly RevisionRequestService $revisions,
        private readonly TokenService $tokens,
        private readonly RetentionService $retention,
        private readonly Audit $audit,
        private readonly Logger $logger,
    ) {
    }

    public function handle(Request $req): Response
    {
        // 資格情報が未設定なら、管理画面そのものを存在させない（fail closed）
        if (!$this->auth->enabled()) {
            return Response::html(
                View::page('表示できません', View::notice('error', self::MSG_NOT_FOUND), false),
                404
            );
        }

        if ($this->config->requireHttps && !$req->isHttps) {
            return $this->forbidden();
        }

        return match ($req->path) {
            '/admin/login'  => $req->method === 'POST' ? $this->doLogin($req) : $this->loginPage($req),
            '/admin/logout' => $this->doLogout($req),
            '/admin/'       => $this->caseList($req),
            '/admin'        => Response::redirect('/admin/'),
            '/admin/case'   => $this->caseDetail($req),
            '/admin/status' => $this->changeStatus($req),
            '/admin/export' => $this->downloadExport($req),
            '/admin/revision'     => $this->revisionForm($req),
            '/admin/revision/send' => $this->sendRevision($req),
            '/admin/new'    => $this->newCaseForm($req),
            '/admin/create' => $this->createCase($req),
            '/admin/reissue'      => $this->reissueForm($req),
            '/admin/reissue/send' => $this->doReissue($req),
            '/admin/lock'         => $this->lockForm($req),
            '/admin/lock/send'    => $this->doLock($req),
            '/admin/retention'    => $this->retentionList($req),
            '/admin/retention/due' => $this->setRetentionDue($req),
            '/admin/purge'        => $this->purgeForm($req),
            '/admin/purge/send'   => $this->doPurge($req),
            '/admin/maintenance'  => $this->maintenance($req),
            '/admin/maintenance/audit'    => $this->purgeAudit($req),
            '/admin/maintenance/sessions' => $this->purgeAdminSessions($req),
            '/admin/settings'      => $this->settingsForm($req),
            '/admin/settings/save' => $this->saveSettings($req),
            default         => Response::html(
                View::page('表示できません', View::notice('error', self::MSG_NOT_FOUND), false),
                404
            ),
        };
    }

    /* ------------------------------------------------------------ ログイン */

    private function loginPage(Request $req, string $message = ''): Response
    {
        if ($req->method !== 'GET') {
            return $this->forbidden();
        }

        $notice = $message === '' ? '' : View::notice('error', $message);

        $body = '<section class="card card--narrow">'
            . '<h1 class="card__title">内部確認画面</h1>'
            . $notice
            . '<form method="post" action="/admin/login" autocomplete="on">'
            . '<div class="field"><label class="field__label" for="admin-id">ID</label>'
            . '<input id="admin-id" name="admin_id" type="text" autocomplete="username" required></div>'
            . '<div class="field"><label class="field__label" for="admin-pw">パスワード</label>'
            . '<input id="admin-pw" name="password" type="password" autocomplete="current-password" required></div>'
            . '<button type="submit" class="btn btn--primary">ログイン</button>'
            . '</form></section>';

        return Response::html(View::page('ログイン', $body, false), $message === '' ? 200 : 401);
    }

    private function doLogin(Request $req): Response
    {
        // ★ログインは form 送信。Origin の厳格検査は行う
        if (!$this->guard->adminPostAllowed($req)) {
            return $this->forbidden();
        }

        $form   = $req->formFields();
        $ipHmac = $this->rateLimiter->ipHmac($req->clientIp);

        $result = $this->auth->login(
            (string)($form['admin_id'] ?? ''),
            (string)($form['password'] ?? ''),
            $ipHmac
        );

        if ($result['ok'] !== true) {
            // ★理由で文言を変えない（IDの存在有無を漏らさない）。混雑だけは案内する
            $this->logger->warn('admin_login', [
                'result_code' => $result['reason'] === 'rate_limited' ? 'rate_limited' : 'invalid',
                'ip_hmac'     => $ipHmac,
                'http_status' => 401,
            ]);

            return $this->loginPage(
                new Request(method: 'GET', path: '/admin/login', isHttps: $req->isHttps),
                $result['reason'] === 'rate_limited' ? self::MSG_RATE_LIMITED : self::MSG_LOGIN_FAILED
            );
        }

        $this->logger->info('admin_login', ['result_code' => 'ok', 'ip_hmac' => $ipHmac, 'http_status' => 303]);

        return Response::redirect('/admin/')
            ->withCookie(Config::ADMIN_COOKIE_NAME, (string)$result['secret'], Config::ADMIN_SESSION_IDLE_TTL, '/admin');
    }

    private function doLogout(Request $req): Response
    {
        // ★GET では受けない（SSOT §10.8 の CSRF 規則5）
        if ($req->method !== 'POST' || !$this->guard->adminPostAllowed($req)) {
            return $this->forbidden();
        }

        $session = $this->auth->verify($req->cookie(Config::ADMIN_COOKIE_NAME));
        if ($session === null) {
            return Response::redirect('/admin/login');
        }
        if (!$this->auth->csrfMatches($session, $this->csrfFrom($req))) {
            return $this->forbidden();
        }

        $this->auth->logout((int)$session['id'], $this->rateLimiter->ipHmac($req->clientIp));

        return Response::redirect('/admin/login')
            ->withClearedCookie(Config::ADMIN_COOKIE_NAME, '/admin');
    }

    /* ------------------------------------------------------------ 一覧 */

    private function caseList(Request $req): Response
    {
        [$session, $fail] = $this->requireSession($req, 'GET');
        if ($fail !== null) {
            return $fail;
        }

        $csrf = $this->auth->rotateCsrf((int)$session['id']);
        $rows = $this->cases->listForAdmin();

        $this->audit->record(null, 'admin_viewed', 'ok');

        $items = '';
        foreach ($rows as $row) {
            $number = (string)$row['case_number'];
            $items .= '<tr>'
                . '<td><a href="/admin/case?case=' . rawurlencode($number) . '">' . View::esc($number) . '</a></td>'
                . '<td>' . View::statusLabel((string)$row['status']) . '</td>'
                . '<td>' . View::orDash($row['submitted_at']) . '</td>'
                . '<td>' . ($row['drive_upload_confirmed_at'] === null
                    ? '<span class="muted">未申告</span>'
                    : '<span class="ok">申告済み</span>') . '</td>'
                . '<td>' . View::orDash($row['updated_at']) . '</td>'
                . '<td>' . View::orDash($row['retention_delete_due']) . '</td>'
                . '</tr>';
        }

        if ($items === '') {
            $items = '<tr><td colspan="6" class="muted">案件がありません。</td></tr>';
        }

        $body = '<section class="card">'
            . '<h1 class="card__title">案件一覧</h1>'
            . '<p class="lead">回答の中身はこの画面に出しません。詳細画面でご確認ください。</p>'
            . '<div class="actions"><a class="btn btn--primary" href="/admin/new">新しいHP制作案件</a></div>'
            . '<div class="tablewrap"><table class="table">'
            . '<thead><tr><th>案件番号</th><th>状態</th><th>提出日時</th><th>素材</th><th>更新日時</th><th>削除予定日</th></tr></thead>'
            . '<tbody>' . $items . '</tbody></table></div>'
            . '</section>'
            . '<div class="endrow">'
            . View::actionForm('/admin/logout', $csrf, [], 'ログアウト', 'btn--quiet')
            . '</div>';

        return Response::html(View::page('案件一覧', $body));
    }

    /* ------------------------------------------------------------ 詳細 */

    private function caseDetail(Request $req): Response
    {
        [$session, $fail] = $this->requireSession($req, 'GET');
        if ($fail !== null) {
            return $fail;
        }

        $case = $this->cases->findByNumber((string)($req->query['case'] ?? ''));
        if ($case === null) {
            return Response::html(
                View::page('表示できません', View::notice('error', self::MSG_NOT_FOUND)),
                404
            );
        }

        $caseId = (int)$case['id'];
        $csrf   = $this->auth->rotateCsrf((int)$session['id']);
        $status = (string)$case['status'];

        $this->audit->record($caseId, 'admin_viewed', 'ok');
        $this->logger->info('admin_viewed', [
            'case_number' => (string)$case['case_number'],
            'result_code' => 'ok',
            'http_status' => 200,
        ]);

        $notice = self::detailNotice((string)($req->query['msg'] ?? ''));

        // ★保持期限で削除済みの案件は、最小メタデータだけを出す（SSOT v1.7 §9.4）。
        //   回答行そのものが無いので、評価も表示も行わない。
        if ($case['deleted_at'] !== null) {
            return Response::html(View::page('案件詳細',
                $notice
                . View::deletedCase($case)
                . '<div class="endrow">'
                . View::actionForm('/admin/logout', $csrf, [], 'ログアウト', 'btn--quiet')
                . '</div>'));
        }

        $evaluation = $this->answers->evaluate($caseId);
        $answers    = $this->answers->get($caseId);

        $body = $notice
            . $this->summarySection($case, $evaluation, $csrf, $status)
            // ★店舗回答の不足と、Smart Labo 設定の不足は**別々に**出す（4F-R3）。
            //   混ぜると「店舗へ差し戻すべきか」の判断ができなくなる。
            . View::adminSettingsStatus(
                (string)$case['case_number'],
                $this->answers->missingAdminSettings($caseId),
                in_array($status, self::SETTABLE_STATUSES, true),
            )
            . $this->revisionSection($caseId)
            . $this->missingSection($evaluation)
            . $this->answersSection($answers['sections'])
            . $this->historySection($caseId)
            . '<div class="endrow">'
            . View::actionForm('/admin/logout', $csrf, [], 'ログアウト', 'btn--quiet')
            . '</div>';

        return Response::html(View::page('案件詳細', $body));
    }

    /** 案件詳細のお知らせ。★固定文言だけ。query の値をそのまま描かない */
    private static function detailNotice(string $flash): string
    {
        return match ($flash) {
            'reviewed'    => View::notice('ok', '確認済みにしました。'),
            'revision'    => View::notice('ok', '修正依頼中にしました。店舗が再入力できます。'),
            'locked'      => View::notice('ok', '入力を確定しました。店舗のご案内リンクは使えなくなりました。'),
            'due'         => View::notice('ok', '削除予定日を登録しました。'),
            'due_past'    => View::notice('warn', '削除予定日を登録しました。'
                . '★入力された日付は過去です。この案件はすぐに削除できる状態になります。'),
            'due_invalid' => View::notice('warn', '削除予定日を登録できませんでした。'
                . 'YYYY-MM-DD の実在する日付を、確定済み以降の案件にご入力ください。'),
            'purged'      => View::notice('ok', '機密情報を削除しました。この操作は元に戻せません。'),
            'settings'    => View::notice('ok', '制作設定を保存しました。'),
            'conflict'    => View::notice('warn', self::MSG_CONFLICT),
            'invalid'     => View::notice('warn', 'その操作はこの状態では行えません。'),
            default       => '',
        };
    }

    /** @param array<string,mixed> $case */
    private function summarySection(array $case, array $evaluation, string $csrf, string $status): string
    {
        $number   = (string)$case['case_number'];
        $complete = $evaluation['missing'] === [];

        // ★SSOT §5.1 の遷移表に無い操作をボタンにしない。
        //   `submitted` からは reviewed / needs_revision の両方へ行ける。
        //   `reviewed` から needs_revision へは**戻れない**（遷移表に無い）。
        //   押しても必ず失敗するボタンを置かないこと自体が、仕様の説明になる。
        // ★v1.5: submitted / reviewed の両方から修正を依頼できる（代表判断の案B）。
        //   修正依頼は理由の入力が要るので、別画面へ送る（GET では何も変えない）。
        $actions = '';
        if ($status === 'submitted') {
            $actions .= View::actionForm(
                '/admin/status',
                $csrf,
                ['case' => $number, 'to' => 'reviewed'],
                '確認済みにする',
                'btn--primary'
            );
        }
        if (in_array($status, CaseService::REVISABLE, true)) {
            $actions .= '<a class="btn btn--outline" href="/admin/revision?case='
                . rawurlencode($number) . '">修正を依頼する</a>';
        }
        // ★ご案内リンクの再発行は draft / needs_revision だけ（SSOT v1.6 §4.4.1）。
        //   押しても必ず失敗するボタンを置かない
        if (in_array($status, CaseService::REISSUABLE, true)) {
            $actions .= '<a class="btn btn--outline" href="/admin/reissue?case='
                . rawurlencode($number) . '">店舗入力リンクを再発行</a>';
        }
        // ★確定（locked）は reviewed からのみ（SSOT v1.7 §5.1）
        if ($status === 'reviewed') {
            $actions .= '<a class="btn btn--outline" href="/admin/lock?case='
                . rawurlencode($number) . '">入力を確定する</a>';
        }
        // ★削除は「確定済み・削除予定日到来・フラグ2つとも真」のときだけ出す。
        //   条件を満たさないボタンを置かないこと自体が、運用手順の説明になる。
        if ($this->config->retentionEnabled()
            && $this->retention->canPurge($case)['ok'] === true) {
            $actions .= '<a class="btn btn--danger" href="/admin/purge?case='
                . rawurlencode($number) . '">機密情報を削除する</a>';
        }

        // 書き出しは提出済み以降かつ必須充足のときだけ出す
        $adminReady = $this->answers->missingAdminSettings((int)$case['id']) === [];
        $exportable = in_array($status, ExportService::EXPORTABLE, true) && $complete && $adminReady;
        $exportHtml = $exportable
            ? '<a class="btn btn--outline" href="/admin/export?case=' . rawurlencode($number) . '">検証済みJSONを書き出す</a>'
            : '<span class="muted">' . ($complete
                ? '制作設定が揃うまで書き出せません'
                : '店舗の必須項目が満たされるまで書き出せません') . '</span>';

        return '<section class="card">'
            . '<h1 class="card__title">' . View::esc($number) . '</h1>'
            . View::row('状態', View::statusLabel($status))
            . View::row('契約種別', View::orDash($case['contract_type']))
            . View::row('提出日時', View::orDash($case['submitted_at']))
            . View::row('素材アップロード申告', $case['drive_upload_confirmed_at'] === null
                ? '<span class="muted">未申告</span>'
                : '<span class="ok">' . View::esc($case['drive_upload_confirmed_at']) . '</span>')
            . View::row('削除予定日', View::orDash($case['retention_delete_due']))
            . View::row('必須項目', $complete
                ? '<span class="ok">すべて充足</span>'
                : '<span class="ng">' . View::esc(count($evaluation['missing'])) . ' 件不足</span>')
            . '<div class="actions">' . $actions . $exportHtml . '</div>'
            . (in_array($status, RetentionService::DUE_SETTABLE, true)
                ? View::retentionDueForm($number, $case['retention_delete_due'] === null
                    ? '' : (string)$case['retention_delete_due'], $csrf)
                : '')
            . '</section>';
    }

    /**
     * これまでの修正依頼（SSOT v1.5 §2.8）。
     * ★過去の依頼も残して見せる。消さない・上書きしない。
     */
    private function revisionSection(int $caseId): string
    {
        $rows = $this->revisions->allForCase($caseId);
        if ($rows === []) {
            return '';
        }

        $items = '';
        foreach (array_reverse($rows) as $row) {
            $decoded = json_decode((string)$row['requested_paths_json'], true);
            $paths   = '';
            foreach (is_array($decoded) ? $decoded : [] as $path) {
                $paths .= '<li>' . View::esc(View::pathDisplay((string)$path)) . '</li>';
            }

            $open = (string)$row['status'] === RevisionRequestService::STATUS_OPEN;
            $items .= '<div class="group">'
                . '<div class="group__head">'
                . '<span class="group__title">' . View::esc('第' . (int)$row['request_number'] . '回') . '</span>'
                . ($open
                    ? '<span class="badge badge--needs_revision">対応中</span>'
                    : '<span class="badge badge--reviewed">対応済み</span>')
                . '</div>'
                . View::row('依頼日時', View::orDash($row['created_at']))
                . View::row('対応日時', View::orDash($row['resolved_at']))
                . View::row('対象項目', '<ul class="list">' . $paths . '</ul>')
                . View::row('メッセージ', $row['message'] === null || (string)$row['message'] === ''
                    ? '<span class="muted">なし</span>'
                    : View::escLines((string)$row['message']))
                . '</div>';
        }

        return '<section class="card"><h2 class="card__title">修正依頼</h2>'
            . '<div class="rows">' . $items . '</div></section>';
    }

    private function missingSection(array $evaluation): string
    {
        if ($evaluation['missing'] === []) {
            return '';
        }
        $items = '';
        foreach ($evaluation['missing'] as $path) {
            $items .= '<li>' . View::esc($path) . '</li>';
        }

        return '<section class="card"><h2 class="card__title">店舗回答の不足項目</h2>'
            . '<ul class="list">' . $items . '</ul></section>';
    }

    /** @param array<string,mixed> $sections */
    private function answersSection(array $sections): string
    {
        $titles = [
            'basic' => '基本情報', 'business_hours' => '営業時間・定休日', 'menus' => 'メニュー・料金',
            'staff' => 'スタッフ', 'promotion' => 'お店の特徴', 'design' => 'デザインのご希望',
            'web_links' => 'SNS・外部リンク', 'contact_form' => 'お問い合わせフォーム',
            'privacy' => 'プライバシー', 'image_metadata' => '写真・素材', 'rights' => '権利・同意',
        ];

        $out = '';
        foreach (Migrator::ANSWER_SECTIONS as $key) {
            $out .= '<section class="card"><h2 class="card__title">' . View::esc($titles[$key] ?? $key) . '</h2>'
                . $this->renderValue($sections[$key] ?? null)
                . '</section>';
        }

        return $out;
    }

    /**
     * 回答の値を安全に描く。
     * ★どの分岐でも必ず esc() を通す。生の値を HTML へ入れない。
     */
    private function renderValue(mixed $value, int $depth = 0): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '<p class="muted">未入力</p>';
        }
        if (is_bool($value)) {
            return '<p>' . ($value ? 'はい' : 'いいえ') . '</p>';
        }
        if (is_scalar($value)) {
            $text = (string)$value;

            return strncmp($text, 'https://', 8) === 0
                ? '<p>' . View::link($text) . '</p>'
                : '<p>' . View::escLines($text) . '</p>';
        }
        if ($depth > 4) {
            return '<p class="muted">…</p>';
        }
        if (!is_array($value)) {
            return '<p class="muted">—</p>';
        }

        $rows = '';
        foreach ($value as $key => $child) {
            $label = is_int($key) ? '#' . ($key + 1) : (string)$key;
            $rows .= '<div class="row"><span class="row__key">' . View::esc($label) . '</span>'
                . '<span class="row__val">' . $this->renderValue($child, $depth + 1) . '</span></div>';
        }

        return $rows;
    }

    private function historySection(int $caseId): string
    {
        $rows = $this->answers->historyRows($caseId);

        $items = '';
        foreach ($rows as $row) {
            $items .= '<tr>'
                . '<td>' . View::esc($row['event_type']) . '</td>'
                . '<td>' . View::esc($row['submitted_at']) . '</td>'
                . '<td>' . View::esc($row['result_code']) . '</td>'
                . '<td>' . View::orDash($row['field_count']) . '</td>'
                . '<td>' . View::orDash($row['missing_count']) . '</td>'
                . '</tr>';
        }
        if ($items === '') {
            $items = '<tr><td colspan="5" class="muted">記録がありません。</td></tr>';
        }

        $auditTotal = $this->audit->countFor($caseId);

        return '<section class="card"><h2 class="card__title">提出履歴</h2>'
            . '<div class="tablewrap"><table class="table">'
            . '<thead><tr><th>種別</th><th>日時</th><th>結果</th><th>充足</th><th>不足</th></tr></thead>'
            . '<tbody>' . $items . '</tbody></table></div>'
            . '<p class="muted">監査イベント ' . View::esc($auditTotal) . ' 件（明細はこの画面に出しません）</p>'
            . '</section>';
    }

    /* ------------------------------------------------------------ 状態変更 */

    private function changeStatus(Request $req): Response
    {
        // ★POST のみ。GET では状態を変えない
        if ($req->method !== 'POST' || !$this->guard->adminPostAllowed($req)) {
            return $this->forbidden();
        }

        $session = $this->auth->verify($req->cookie(Config::ADMIN_COOKIE_NAME));
        if ($session === null) {
            return Response::redirect('/admin/login');
        }
        if (!$this->auth->csrfMatches($session, $this->csrfFrom($req))) {
            return $this->forbidden();
        }
        $this->auth->touch((int)$session['id']);

        $form   = $req->formFields();
        $number = (string)($form['case'] ?? '');
        $to     = (string)($form['to'] ?? '');

        // 独自 status を作らない。許すのはこの2つだけ
        $allowed = [
            'reviewed'       => 'reviewed',
            'needs_revision' => 'revision_requested',
        ];
        if (!isset($allowed[$to])) {
            return $this->forbidden();
        }

        $case = $this->cases->findByNumber($number);
        if ($case === null) {
            return Response::html(
                View::page('表示できません', View::notice('error', self::MSG_NOT_FOUND)),
                404
            );
        }

        $result = $this->cases->adminChangeStatus((int)$case['id'], $to, $allowed[$to]);

        $msg = match (true) {
            $result['ok'] === true && $to === 'reviewed' => 'reviewed',
            $result['ok'] === true                       => 'revision',
            ($result['error'] ?? '') === 'conflict'      => 'conflict',
            default                                      => 'invalid',
        };

        // ★理由をそのまま記録する（失敗をすべて conflict と書かない）
        $this->logger->info('case_status_changed', [
            'case_number' => (string)$case['case_number'],
            'result_code' => $result['ok'] === true ? 'ok' : (string)($result['error'] ?? 'invalid'),
            'http_status' => 303,
        ]);

        return Response::redirect('/admin/case?case=' . rawurlencode($number) . '&msg=' . $msg);
    }

    /* ------------------------------------------------------------ 修正依頼 */

    /**
     * 修正依頼の入力画面（GET）。
     * ★ここでは何も変えない。確定は POST /admin/revision/send。
     */
    private function revisionForm(Request $req): Response
    {
        [$session, $fail] = $this->requireSession($req, 'GET');
        if ($fail !== null) {
            return $fail;
        }

        $case = $this->cases->findByNumber((string)($req->query['case'] ?? ''));
        if ($case === null) {
            return $this->notFound();
        }

        $number = (string)$case['case_number'];
        $status = (string)$case['status'];
        if (!in_array($status, CaseService::REVISABLE, true)) {
            return Response::html(
                View::page('修正を依頼できません', View::notice(
                    'warn',
                    'この案件は、いまの状態では修正を依頼できません。'
                )),
                409
            );
        }

        $csrf    = $this->auth->rotateCsrf((int)$session['id']);
        $missing = $this->answers->evaluate((int)$case['id'])['missing'];

        return Response::html(View::page(
            '修正を依頼する',
            View::revisionForm($number, $csrf, $missing),
        ));
    }

    /**
     * 修正依頼を確定する（POST）。
     * ★状態変更と依頼の作成は CaseService 側で同一トランザクションにまとめる。
     */
    private function sendRevision(Request $req): Response
    {
        if ($req->method !== 'POST' || !$this->guard->adminPostAllowed($req)) {
            return $this->forbidden();
        }

        $session = $this->auth->verify($req->cookie(Config::ADMIN_COOKIE_NAME));
        if ($session === null) {
            return Response::redirect('/admin/login');
        }
        if (!$this->auth->csrfMatches($session, $this->csrfFrom($req))) {
            return $this->forbidden();
        }
        $this->auth->touch((int)$session['id']);

        $form   = $req->formFields();
        $number = (string)($form['case'] ?? '');
        $case   = $this->cases->findByNumber($number);
        if ($case === null) {
            return $this->notFound();
        }

        // ★checkbox は同名の複数値。formFields() は文字列だけを返すので、ここで生body を読む
        $paths = $this->pathsFrom($req);

        $checked = $this->revisions->validate($paths, $form['message'] ?? null);
        if ($checked['ok'] !== true) {
            $reason = (string)$checked['error'];
            $text   = match ($reason) {
                'empty'            => '修正をお願いする項目を1つ以上お選びください。',
                'message_too_long' => 'メッセージが長すぎます（1000文字まで）。',
                default            => '入力内容を確認できませんでした。',
            };

            return Response::html(
                View::page('修正を依頼する', View::notice('error', $text)
                    . View::revisionForm($number, $this->auth->rotateCsrf((int)$session['id']),
                        $this->answers->evaluate((int)$case['id'])['missing'])),
                400
            );
        }

        $result = $this->cases->requestRevision(
            (int)$case['id'],
            (array)$checked['paths'],
            $checked['message'],
            $this->revisions,
        );

        if ($result['ok'] !== true) {
            $msg = ($result['error'] ?? '') === 'conflict' ? 'conflict' : 'invalid';

            return Response::redirect('/admin/case?case=' . rawurlencode($number) . '&msg=' . $msg);
        }

        // ★本文も対象パスもログへ出さない（SSOT §2.8-4）
        $this->logger->info('case_status_changed', [
            'case_number' => $number,
            'result_code' => 'ok',
            'http_status' => 303,
        ]);

        return Response::redirect('/admin/case?case=' . rawurlencode($number) . '&msg=revision');
    }

    /**
     * `paths[]` を生 body から読む。
     * ★同名複数値のため formFields()（文字列のみ）では取れない。
     * @return list<string>
     */
    private function pathsFrom(Request $req): array
    {
        $ctype = strtolower(trim(explode(';', (string)$req->header('Content-Type'))[0]));
        if ($ctype !== 'application/x-www-form-urlencoded') {
            return [];
        }
        $parsed = [];
        parse_str($req->body, $parsed);
        $paths = $parsed['paths'] ?? [];

        $out = [];
        foreach (is_array($paths) ? $paths : [] as $p) {
            if (is_string($p)) {
                $out[] = $p;
            }
        }

        return $out;
    }

    /* ------------------------------------------------------------ 案件作成 */

    private function newCaseForm(Request $req): Response
    {
        [$session, $fail] = $this->requireSession($req, 'GET');
        if ($fail !== null) {
            return $fail;
        }

        $csrf = $this->auth->rotateCsrf((int)$session['id']);

        return Response::html(View::page('新しいHP制作案件', View::newCaseForm($csrf)));
    }

    /**
     * 案件を作り、ご案内リンクを**1回だけ**表示する。
     *
     * ★token の平文はこの応答にしか出さない。再表示の経路を作らない。
     * ★token をログ・監査・URL・Cookie へ出さない。
     */
    private function createCase(Request $req): Response
    {
        if ($req->method !== 'POST' || !$this->guard->adminPostAllowed($req)) {
            return $this->forbidden();
        }

        $session = $this->auth->verify($req->cookie(Config::ADMIN_COOKIE_NAME));
        if ($session === null) {
            return Response::redirect('/admin/login');
        }
        if (!$this->auth->csrfMatches($session, $this->csrfFrom($req))) {
            return $this->forbidden();
        }
        // ★ここで CSRF を作り直す。これが**二重送信の歯止め**になる。
        //   ブラウザの再送・戻る→再送信で同じ画面をもう一度送っても、
        //   古い token では通らないので案件が重複しない。
        $this->auth->touch((int)$session['id']);
        $this->auth->rotateCsrf((int)$session['id']);

        $form = $req->formFields();

        $created = $this->cases->createFromAdmin(
            (string)($form['shop_display_name'] ?? ''),
            (string)($form['contract_type'] ?? 'standalone'),
        );
        if ($created['ok'] !== true) {
            $text = ($created['error'] ?? '') === 'bad_name'
                ? '管理用の名前をご入力ください（100文字まで）。'
                : '案件を作成できませんでした。';

            return Response::html(
                View::page('新しいHP制作案件',
                    View::notice('error', $text)
                    . View::newCaseForm($this->auth->rotateCsrf((int)$session['id']))),
                400
            );
        }

        $caseId = (int)$created['case_id'];
        $number = (string)$created['case_number'];

        // Drive の情報は任意。入っていれば検査して保存する
        $driveError = $this->applyDrive($caseId, $number, $form);
        if ($driveError !== null) {
            return Response::html(
                View::page('新しいHP制作案件',
                    View::notice('error', $driveError)
                    . View::notice('warn', '案件 ' . View::esc($number) . ' は作成済みです。'
                        . '素材フォルダの設定は案件詳細からやり直してください。')),
                400
            );
        }

        // ご案内リンクの発行（既存の TokenService をそのまま使う）
        $token = $this->tokens->issue($caseId);

        $this->logger->info('token_issued', [
            'case_number' => $number,
            'result_code' => 'ok',
            'http_status' => 200,
        ]);

        // ★no-store。この画面を離れると復元できない
        return Response::html(View::page('案件を作成しました', View::createdCase($number, $token)));
    }

    /**
     * Drive の URL と共有先メールを保存する。
     * @param array<string,string> $form
     * @return string|null エラー文言（成功なら null）
     */
    private function applyDrive(int $caseId, string $caseNumber, array $form): ?string
    {
        $url   = trim((string)($form['drive_url'] ?? ''));
        $email = trim((string)($form['drive_shared_email'] ?? ''));

        if ($url === '' && $email === '') {
            return null;
        }
        if ($url === '' || $email === '') {
            return '素材フォルダのURLと共有先メールは、両方そろえてご入力ください。';
        }

        try {
            $this->cases->setDriveFolder(
                $caseId,
                $url,
                $caseNumber . ' 素材',
                $email,
            );
        } catch (\InvalidArgumentException $e) {
            // ★例外の内容（拒否理由）をそのまま画面へ出さない
            return 'この素材フォルダのURLまたはメールアドレスは受け付けられません。';
        }

        return null;
    }

    /* ------------------------------------------------ ご案内リンクの再発行 */

    /**
     * 再発行の確認画面（GET）。
     * ★ここでは何も変えない。実行は POST /admin/reissue/send。
     */
    private function reissueForm(Request $req, string $message = ''): Response
    {
        [$session, $fail] = $this->requireSession($req, 'GET');
        if ($fail !== null) {
            return $fail;
        }

        $case = $this->cases->findByNumber((string)($req->query['case'] ?? ''));
        if ($case === null) {
            return $this->notFound();
        }

        $status = (string)$case['status'];
        if (!in_array($status, CaseService::REISSUABLE, true)) {
            return Response::html(
                View::page('再発行できません', View::notice(
                    'warn',
                    'この案件は、いまの状態ではご案内リンクを再発行できません。'
                    . '修正が必要な場合は、先に「修正を依頼する」で差し戻してください。'
                )),
                409
            );
        }

        $csrf   = $this->auth->rotateCsrf((int)$session['id']);
        $notice = $message === '' ? '' : View::notice('error', $message);

        return Response::html(View::page(
            'ご案内リンクの再発行',
            $notice . View::reissueForm((string)$case['case_number'], $status, $csrf),
        ), $message === '' ? 200 : 400);
    }

    /**
     * 再発行を実行する（POST）。
     *
     * ★旧 token と関連する店舗 session の失効、新 token の発行は
     *   TokenService::reissue() が**同一トランザクション**で行う。
     * ★新しい平文はこの応答にしか出さない。再表示の経路を作らない。
     */
    private function doReissue(Request $req): Response
    {
        if ($req->method !== 'POST' || !$this->guard->adminPostAllowed($req)) {
            return $this->forbidden();
        }

        $session = $this->auth->verify($req->cookie(Config::ADMIN_COOKIE_NAME));
        if ($session === null) {
            return Response::redirect('/admin/login');
        }
        if (!$this->auth->csrfMatches($session, $this->csrfFrom($req))) {
            return $this->forbidden();
        }
        $this->auth->touch((int)$session['id']);
        // ★ここで作り直すことが二重送信の歯止めになる。
        //   ブラウザの再送・戻る→再送信では古い token になり通らない
        $this->auth->rotateCsrf((int)$session['id']);

        $form   = $req->formFields();
        $number = (string)($form['case'] ?? '');
        $case   = $this->cases->findByNumber($number);
        if ($case === null) {
            return $this->notFound();
        }

        $caseId = (int)$case['id'];
        $status = (string)$case['status'];

        // 誤操作防止: 案件番号の再入力が完全一致すること
        // ★入力された比較値をログへ出さない
        if (!hash_equals($number, (string)($form['confirm_case'] ?? ''))) {
            return $this->reissueForm(
                new Request(
                    method: 'GET',
                    path: '/admin/reissue',
                    headers: ['Sec-Fetch-Site' => 'same-origin'],
                    cookies: $req->cookies,
                    isHttps: $req->isHttps,
                    query: ['case' => $number],
                ),
                '案件番号が一致しません。もう一度ご確認ください。'
            );
        }

        // レート制限（案件 ＋ HMAC化IP で 10分5回）
        $ipHmac   = $this->rateLimiter->ipHmac($req->clientIp);
        $identity = $ipHmac . ':case:' . $caseId;
        if (!$this->rateLimiter->allow('token_reissue', $identity)) {
            $this->audit->record($caseId, 'token_reissued', 'rate_limited', $ipHmac);
            $this->logger->warn('token_reissued', [
                'case_number' => $number,
                'result_code' => 'rate_limited',
                'ip_hmac'     => $ipHmac,
                'http_status' => 429,
            ]);

            return Response::html(
                View::page('再発行できません', View::notice('warn', self::MSG_RATE_LIMITED)),
                429
            );
        }

        $result = $this->tokens->reissue($caseId, CaseService::REISSUABLE, $ipHmac);

        if ($result['ok'] !== true) {
            // ★理由の詳細を画面へ出さない
            return Response::html(
                View::page('再発行できません', View::notice(
                    'warn',
                    'この案件は、いまの状態ではご案内リンクを再発行できません。'
                )),
                409
            );
        }

        // ★token 平文はログへ出さない。案件番号と結果だけ
        $this->logger->info('token_reissued', [
            'case_number' => $number,
            'result_code' => 'ok',
            'ip_hmac'     => $ipHmac,
            'http_status' => 200,
        ]);

        return Response::html(View::page(
            'ご案内リンクを再発行しました',
            View::reissuedLink($number, $status, (string)$result['token']),
        ));
    }

    /* ------------------------------------------------ 入力確定（locked） */

    /**
     * 確定の確認画面（GET）。★ここでは何も変えない。
     */
    private function lockForm(Request $req, string $message = ''): Response
    {
        [$session, $fail] = $this->requireSession($req, 'GET');
        if ($fail !== null) {
            return $fail;
        }

        $case = $this->cases->findByNumber((string)($req->query['case'] ?? ''));
        if ($case === null || $case['deleted_at'] !== null) {
            return $this->notFound();
        }
        if ((string)$case['status'] !== 'reviewed') {
            return Response::html(
                View::page('確定できません', View::notice(
                    'warn',
                    'この案件は、いまの状態では確定できません。先に「確認済みにする」を行ってください。'
                )),
                409
            );
        }

        $csrf   = $this->auth->rotateCsrf((int)$session['id']);
        $notice = $message === '' ? '' : View::notice('error', $message);

        return Response::html(View::page(
            '入力を確定する',
            $notice . View::lockForm((string)$case['case_number'], $csrf),
        ), $message === '' ? 200 : 400);
    }

    /**
     * 確定を実行する（POST）。
     * ★状態変更・履歴・token 失効・店舗 session 失効は CaseService が同一トランザクションで行う。
     */
    private function doLock(Request $req): Response
    {
        [$session, $fail] = $this->requirePostSession($req);
        if ($fail !== null) {
            return $fail;
        }
        // ★CSRF を作り直す。ブラウザの再送・戻る→再送信を通さない
        $this->auth->rotateCsrf((int)$session['id']);

        $form   = $req->formFields();
        $number = (string)($form['case'] ?? '');
        $case   = $this->cases->findByNumber($number);
        if ($case === null) {
            return $this->notFound();
        }

        // 誤操作防止: 案件番号の再入力が完全一致すること
        if (!hash_equals($number, (string)($form['confirm_case'] ?? ''))) {
            return $this->lockForm(
                $this->sameOriginGet('/admin/lock', ['case' => $number], $req),
                '案件番号が一致しません。もう一度ご確認ください。'
            );
        }

        $result = $this->cases->adminLock((int)$case['id']);

        $this->logger->info('case_status_changed', [
            'case_number' => $number,
            'result_code' => $result['ok'] === true ? 'ok' : (string)($result['error'] ?? 'invalid'),
            'http_status' => 303,
        ]);

        $msg = match (true) {
            $result['ok'] === true                  => 'locked',
            ($result['error'] ?? '') === 'conflict' => 'conflict',
            default                                 => 'invalid',
        };

        return Response::redirect('/admin/case?case=' . rawurlencode($number) . '&msg=' . $msg);
    }

    /* ------------------------------------------------ 保持期限 */

    /** 保持期限の一覧（GET）。★回答本文・店舗名・Drive 情報を出さない */
    private function retentionList(Request $req): Response
    {
        [$session, $fail] = $this->requireSession($req, 'GET');
        if ($fail !== null) {
            return $fail;
        }

        $csrf = $this->auth->rotateCsrf((int)$session['id']);
        $this->audit->record(null, 'admin_viewed', 'ok');

        return Response::html(View::page('保持期限', View::retentionList(
            $this->retention->listForRetention(),
            $this->retention->today(),
            $this->config->retentionEnabled(),
            $csrf,
        )));
    }

    /**
     * 削除予定日を登録・変更する（POST）。
     *
     * ★公開日も公開承認も受け取らない。受け取るのは削除予定日1つだけ（SSOT v1.7 §9.3-1）。
     */
    private function setRetentionDue(Request $req): Response
    {
        [$session, $fail] = $this->requirePostSession($req);
        if ($fail !== null) {
            return $fail;
        }
        unset($session);

        $form   = $req->formFields();
        $number = (string)($form['case'] ?? '');
        $case   = $this->cases->findByNumber($number);
        if ($case === null) {
            return $this->notFound();
        }

        $result = $this->retention->setDeleteDue((int)$case['id'], (string)($form['due'] ?? ''));

        // ★日付そのものはログへ出さない。案件番号と結果だけ
        $this->logger->info('retention_due_set', [
            'case_number' => $number,
            'result_code' => $result['ok'] === true ? 'ok' : (string)($result['error'] ?? 'invalid'),
            'http_status' => 303,
        ]);

        $msg = match (true) {
            $result['ok'] === true && ($result['past'] ?? false) === true => 'due_past',
            $result['ok'] === true                                        => 'due',
            default                                                       => 'due_invalid',
        };

        return Response::redirect('/admin/case?case=' . rawurlencode($number) . '&msg=' . $msg);
    }

    /* ------------------------------------------------ 機密情報の削除 */

    /**
     * 削除の確認画面（GET）。★ここでは何も変えない。
     *
     * ★フラグが揃っていなければ、確認画面そのものを出さない（fail closed）。
     */
    private function purgeForm(Request $req, string $message = ''): Response
    {
        [$session, $fail] = $this->requireSession($req, 'GET');
        if ($fail !== null) {
            return $fail;
        }
        if (!$this->config->retentionEnabled()) {
            return $this->retentionDisabled();
        }

        $case = $this->cases->findByNumber((string)($req->query['case'] ?? ''));
        if ($case === null) {
            return $this->notFound();
        }

        $gate = $this->retention->canPurge($case);
        if ($gate['ok'] !== true) {
            return Response::html(
                View::page('削除できません', View::notice('warn', match ((string)$gate['error']) {
                    'already_deleted' => 'この案件の機密情報は、すでに削除済みです。',
                    'invalid_status'  => 'この案件は「確定」ではありません。先に入力を確定してください。',
                    'due_not_set'     => '削除予定日が登録されていません。先に削除予定日をご登録ください。',
                    default           => '削除予定日にまだ達していません。',
                })),
                409
            );
        }

        $csrf   = $this->auth->rotateCsrf((int)$session['id']);
        $notice = $message === '' ? '' : View::notice('error', $message);

        return Response::html(View::page('機密情報の削除', $notice . View::purgeForm(
            (string)$case['case_number'],
            (string)$case['retention_delete_due'],
            $csrf,
        )), $message === '' ? 200 : 400);
    }

    /**
     * 削除を実行する（POST）。★元に戻せない。
     *
     * 条件はすべて RetentionService 側でもう一度検査し、
     * 削除は同一トランザクションで行う（途中で落ちたら全部戻る）。
     */
    private function doPurge(Request $req): Response
    {
        [$session, $fail] = $this->requirePostSession($req);
        if ($fail !== null) {
            return $fail;
        }
        if (!$this->config->retentionEnabled()) {
            return $this->retentionDisabled();
        }
        $this->auth->rotateCsrf((int)$session['id']);

        $form   = $req->formFields();
        $number = (string)($form['case'] ?? '');
        $case   = $this->cases->findByNumber($number);
        if ($case === null) {
            return $this->notFound();
        }

        $result = $this->retention->purgeCase(
            (int)$case['id'],
            $number,
            (string)($form['confirm'] ?? ''),
        );

        if ($result['ok'] !== true) {
            // ★入力された確認文をログにも画面にも出さない
            $this->logger->warn('retention_purged', [
                'case_number' => $number,
                'result_code' => (string)($result['error'] ?? 'invalid'),
                'http_status' => 409,
            ]);

            if ((string)($result['error'] ?? '') === 'confirm_mismatch') {
                return $this->purgeForm(
                    $this->sameOriginGet('/admin/purge', ['case' => $number], $req),
                    '確認の入力が一致しません。表示されているとおりにご入力ください。'
                );
            }

            return Response::html(
                View::page('削除できません', View::notice(
                    'warn',
                    'この案件は、いまの状態では削除できません。最新の内容をご確認ください。'
                )),
                409
            );
        }

        // ★件数の内訳もログへ出さない。案件番号と結果だけ
        $this->logger->info('retention_purged', [
            'case_number' => $number,
            'result_code' => 'ok',
            'http_status' => 303,
        ]);

        return Response::redirect('/admin/case?case=' . rawurlencode($number) . '&msg=purged');
    }

    /* ------------------------------------------------ 保守（監査・管理session） */

    /** 件数だけを出す（GET）。★監査の中身も session hash も出さない */
    private function maintenance(Request $req, string $flash = ''): Response
    {
        [$session, $fail] = $this->requireSession($req, 'GET');
        if ($fail !== null) {
            return $fail;
        }

        $csrf = $this->auth->rotateCsrf((int)$session['id']);

        return Response::html(View::page('保守', View::maintenance(
            $this->retention->countAuditDue(),
            $this->retention->auditCutoff(),
            $this->retention->countAdminSessionsDue(),
            $this->config->retentionEnabled(),
            $csrf,
            $flash,
        )));
    }

    /** 監査の13か月削除（POST）。★flag が揃っていなければ実行しない */
    private function purgeAudit(Request $req): Response
    {
        [$session, $fail] = $this->requirePostSession($req);
        if ($fail !== null) {
            return $fail;
        }
        if (!$this->config->retentionEnabled()) {
            return $this->retentionDisabled();
        }
        $this->auth->rotateCsrf((int)$session['id']);

        $result = $this->retention->purgeAudit();

        $this->logger->info('audit_purged', [
            'result_code' => 'ok',
            'http_status' => 200,
        ]);

        return $this->maintenance(
            $this->sameOriginGet('/admin/maintenance', [], $req),
            '監査ログを ' . (int)$result['deleted'] . ' 件削除しました。'
        );
    }

    /**
     * 期限切れ・失効済みの管理 session を削除する（POST）。
     * ★いま有効な session は消さない（実行した本人が締め出されない）。
     */
    private function purgeAdminSessions(Request $req): Response
    {
        [$session, $fail] = $this->requirePostSession($req);
        if ($fail !== null) {
            return $fail;
        }
        $this->auth->rotateCsrf((int)$session['id']);

        $result = $this->retention->purgeAdminSessions();

        $this->logger->info('admin_sessions_purged', [
            'result_code' => 'ok',
            'http_status' => 200,
        ]);

        return $this->maintenance(
            $this->sameOriginGet('/admin/maintenance', [], $req),
            '期限切れの管理セッションを ' . (int)$result['deleted'] . ' 件削除しました。'
        );
    }

    /** 破壊的操作が無効化されているときの固定応答（SSOT v1.7 §9.8） */
    private function retentionDisabled(): Response
    {
        return Response::html(
            View::page('この操作は無効です', View::notice(
                'warn',
                '保持期限による削除は、この環境では有効になっていません。'
                . 'バックアップ方針の確定後に設定してください。'
            )),
            403
        );
    }

    /* ------------------------------------------------ Smart Labo 設定（4F-R3） */

    /**
     * 制作設定の入力画面（GET）。★ここでは何も変えない。
     *
     * SSOT v1.9 §3.12 の「Smart Labo が入力する」項目だけを扱う。
     * 店舗の回答欄と**同じ画面に混ぜない**。
     */
    private function settingsForm(Request $req, string $message = ''): Response
    {
        [$session, $fail] = $this->requireSession($req, 'GET');
        if ($fail !== null) {
            return $fail;
        }

        $case = $this->cases->findByNumber((string)($req->query['case'] ?? ''));
        if ($case === null || $case['deleted_at'] !== null) {
            return $this->notFound();
        }
        if (!in_array((string)$case['status'], self::SETTABLE_STATUSES, true)) {
            return Response::html(
                View::page('設定できません', View::notice(
                    'warn',
                    'この案件は、いまの状態では制作設定を変更できません。'
                    . '店舗の確認が済んでから設定してください。'
                )),
                409
            );
        }

        $csrf   = $this->auth->rotateCsrf((int)$session['id']);
        $notice = $message === '' ? '' : View::notice('error', $message);

        return Response::html(View::page('制作設定', $notice . View::adminSettingsForm(
            (string)$case['case_number'],
            $this->answers->adminSettings((int)$case['id']),
            $this->answers->missingAdminSettings((int)$case['id']),
            $csrf,
        )), $message === '' ? 200 : 400);
    }

    /**
     * 制作設定を保存する（POST）。
     *
     * ★店舗の回答には触れない（AnswerService 側で保存済みの値を残す）。
     * ★値をログにも監査にも出さない。残すのは案件番号と結果だけ。
     */
    private function saveSettings(Request $req): Response
    {
        [$session, $fail] = $this->requirePostSession($req);
        if ($fail !== null) {
            return $fail;
        }
        $this->auth->rotateCsrf((int)$session['id']);

        $form   = $req->formFields();
        $number = (string)($form['case'] ?? '');
        $case   = $this->cases->findByNumber($number);
        if ($case === null || $case['deleted_at'] !== null) {
            return $this->notFound();
        }
        if (!in_array((string)$case['status'], self::SETTABLE_STATUSES, true)) {
            return Response::html(
                View::page('設定できません', View::notice('warn', 'この案件は、いまの状態では制作設定を変更できません。')),
                409
            );
        }

        // 誤操作防止: 案件番号の再入力が完全一致すること
        if (!hash_equals($number, (string)($form['confirm_case'] ?? ''))) {
            return $this->settingsForm(
                $this->sameOriginGet('/admin/settings', ['case' => $number], $req),
                '案件番号が一致しません。もう一度ご確認ください。'
            );
        }

        // 同意チェックの表示は真偽。空のままは受け付けない
        $consent = (string)($form['consent_checkbox'] ?? '');
        if (!in_array($consent, ['true', 'false'], true)) {
            return $this->settingsForm(
                $this->sameOriginGet('/admin/settings', ['case' => $number], $req),
                '同意チェックの表示は「表示する」「表示しない」のどちらかをお選びください。'
            );
        }

        $services = [];
        foreach (preg_split('/\r\n|\r|\n/', (string)($form['external_services'] ?? '')) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $services[] = $line;
            }
        }

        $trimOrNull = static function (mixed $value): ?string {
            $text = trim((string)$value);

            return $text === '' ? null : $text;
        };

        $result = $this->answers->saveAdminSettings((int)$case['id'], [
            'web_links' => [
                'salon_booking_url' => $trimOrNull($form['salon_booking_url'] ?? ''),
            ],
            'privacy' => [
                'destination'       => $trimOrNull($form['destination'] ?? ''),
                'storage'           => $trimOrNull($form['storage'] ?? ''),
                'external_services' => $services,
                'consent_checkbox'  => $consent === 'true',
            ],
        ]);

        if ($result['ok'] !== true) {
            return $this->settingsForm(
                $this->sameOriginGet('/admin/settings', ['case' => $number], $req),
                '設定を保存できませんでした。入力内容をご確認ください。'
            );
        }

        // ★設定値そのものはログへ出さない
        $this->logger->info('admin_settings_saved', [
            'case_number' => $number,
            'result_code' => 'ok',
            'http_status' => 303,
        ]);

        return Response::redirect('/admin/case?case=' . rawurlencode($number) . '&msg=settings');
    }

    /* ------------------------------------------------------------ 書き出し */

    private function downloadExport(Request $req): Response
    {
        [$session, $fail] = $this->requireSession($req, 'GET');
        if ($fail !== null) {
            return $fail;
        }
        unset($session);

        $case = $this->cases->findByNumber((string)($req->query['case'] ?? ''));
        if ($case === null) {
            return Response::html(
                View::page('表示できません', View::notice('error', self::MSG_NOT_FOUND)),
                404
            );
        }

        $caseId = (int)$case['id'];
        $result = $this->export->export($caseId);

        if ($result['ok'] !== true) {
            $this->audit->record($caseId, 'export_generated', 'invalid');

            return Response::html(
                View::page('書き出せません', View::notice(
                    'warn',
                    'この案件はまだ書き出せません。提出と必須項目をご確認ください。'
                )),
                409
            );
        }

        $this->audit->record($caseId, 'export_generated', 'ok');
        // ★JSON 本文をログへ出さない。案件番号と件数だけ
        $this->logger->info('export_generated', [
            'case_number' => (string)$case['case_number'],
            'result_code' => 'ok',
            'http_status' => 200,
        ]);

        return Response::download(
            (string)$result['json'],
            (string)$result['file_name'],
            'application/json; charset=UTF-8',
            ['X-Intake-Export-Sha256' => (string)$result['sha256']]
        );
    }

    /* ------------------------------------------------------------ 補助 */

    /**
     * 認証を要求する。未認証ならログイン画面へ送る。
     * ★未認証のときに案件の有無を判定しない（存在を漏らさない）。
     * @return array{0:array<string,mixed>,1:?Response}
     */
    private function requireSession(Request $req, string $method): array
    {
        if ($req->method !== $method) {
            return [[], $this->forbidden()];
        }

        // 他サイトからの埋め込み・遷移は拒否する。
        // ★GET は状態を変えないため、ここでの主な守りは**認証**と
        //   `X-Frame-Options: DENY` / `frame-ancestors 'none'` / Cookie の SameSite=Strict である。
        //   Fetch Metadata が付く環境では、そこも合わせて塞いでおく。
        $site = $req->header('Sec-Fetch-Site');
        if ($site !== null && strtolower(trim($site)) === 'cross-site') {
            return [[], $this->forbidden()];
        }

        $session = $this->auth->verify($req->cookie(Config::ADMIN_COOKIE_NAME));
        if ($session === null) {
            return [[], Response::redirect('/admin/login')];
        }
        $this->auth->touch((int)$session['id']);

        return [$session, null];
    }

    /**
     * 状態を変える POST の共通前段（4F で追加）。
     *
     *   POST であること → Origin / Fetch Metadata → 管理 session → CSRF → touch
     *
     * ★この順序を各所で書き写すと、いつか1つ抜ける。1か所に集める。
     * ★CSRF の作り直し（＝二重送信の歯止め）は**呼び出し側**で行う。
     *   作り直すかどうかは操作ごとの判断だからである。
     *
     * @return array{0:array<string,mixed>,1:?Response}
     */
    private function requirePostSession(Request $req): array
    {
        if ($req->method !== 'POST' || !$this->guard->adminPostAllowed($req)) {
            return [[], $this->forbidden()];
        }

        $session = $this->auth->verify($req->cookie(Config::ADMIN_COOKIE_NAME));
        if ($session === null) {
            return [[], Response::redirect('/admin/login')];
        }
        if (!$this->auth->csrfMatches($session, $this->csrfFrom($req))) {
            return [[], $this->forbidden()];
        }
        $this->auth->touch((int)$session['id']);

        return [$session, null];
    }

    /**
     * POST の失敗を GET 画面として描き直すための擬似リクエスト。
     * ★Cookie と HTTPS 判定だけを引き継ぐ。body は引き継がない。
     *
     * @param array<string,string> $query
     */
    private function sameOriginGet(string $path, array $query, Request $req): Request
    {
        return new Request(
            method: 'GET',
            path: $path,
            headers: ['Sec-Fetch-Site' => 'same-origin'],
            cookies: $req->cookies,
            isHttps: $req->isHttps,
            query: $query,
        );
    }

    private function csrfFrom(Request $req): ?string
    {
        $form = $req->formFields();

        return isset($form['csrf_token']) ? (string)$form['csrf_token'] : null;
    }

    private function notFound(): Response
    {
        return Response::html(
            View::page('表示できません', View::notice('error', self::MSG_NOT_FOUND)),
            404
        );
    }

    private function forbidden(): Response
    {
        return Response::html(
            View::page('許可されていません', View::notice('error', self::MSG_FORBIDDEN), false),
            403
        );
    }
}
