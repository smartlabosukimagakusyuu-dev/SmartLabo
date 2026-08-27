<?php
/**
 * 保持期限・確定（locked）・機密情報の削除（HP-ONBOARDING-4F / SSOT v1.7 §5.1 / §9）
 *
 * ★このファイルのテストは**すべて使い捨てDB**（tests/.tmp 配下）でだけ動く。
 *   本番・既存DBへは接続しない。使う店舗名・メール・URL・案件番号はすべて架空である。
 */
declare(strict_types=1);

use SmartLabo\Intake\Config;
use SmartLabo\Intake\Service\CaseService;
use SmartLabo\Intake\Service\ExportService;
use SmartLabo\Intake\Service\RetentionService;

/** 架空の素材フォルダ（実在しない） */
const RET_DRIVE_URL   = 'https://drive.google.com/drive/folders/FAKE-RETENTION-0000000';
const RET_DRIVE_EMAIL = 'materials@example.invalid';
/** 削除されたことを確かめるための、他に出てこない目印 */
const RET_MARKER      = 'RETENTIONMARKER0000';

/** 削除操作を有効にした Kernel（★ローカル確認専用の override） */
function retentionKernel(?TestClock $clock = null, array $overrides = []): object
{
    return adminKernel($clock, array_merge([
        'retention_actions_enabled' => true,
        'backup_policy_confirmed'   => true,
    ], $overrides));
}

/**
 * 「確定（locked）できる状態」の案件を1件つくる。
 * 回答・提出履歴・修正依頼・token・session・Drive 情報がすべて入った状態にする。
 */
function reviewedCase(object $k, string $number): array
{
    [$caseId, $shopCookies] = submittedCase($k, $number);

    $k->cases->setDriveFolder($caseId, RET_DRIVE_URL, $number . ' 素材', RET_DRIVE_EMAIL);
    $k->cases->adminChangeStatus($caseId, 'reviewed', 'reviewed');

    // 修正依頼（メッセージ本文が残る）→ 差し戻し → 再提出
    $k->cases->requestRevision($caseId, ['basic.legal_name'], '架空の修正理由 ' . RET_MARKER, $k->revisions);
    $k->app->handle(jsonPost('/submit', ['submission_id' => newSubmissionId()], $shopCookies));
    $k->cases->adminChangeStatus($caseId, 'reviewed', 'reviewed');

    return [$caseId, $shopCookies];
}

/** 確定済み＋削除予定日つきの案件 */
function lockedCase(object $k, string $number, string $due = '2026-01-15'): array
{
    [$caseId, $shopCookies] = reviewedCase($k, $number);
    $k->cases->adminLock($caseId);
    $k->retention->setDeleteDue($caseId, $due);

    return [$caseId, $shopCookies];
}

/** 表の行数（削除の確認に使う） */
function rowCount(object $k, string $table, int $caseId): int
{
    $sql = [
        'intake_answers'            => 'SELECT COUNT(*) FROM intake_answers WHERE intake_case_id = :id',
        'intake_tokens'             => 'SELECT COUNT(*) FROM intake_tokens WHERE intake_case_id = :id',
        'intake_sessions'           => 'SELECT COUNT(*) FROM intake_sessions WHERE intake_case_id = :id',
        'intake_revision_requests'  => 'SELECT COUNT(*) FROM intake_revision_requests WHERE intake_case_id = :id',
        'intake_submission_history' => 'SELECT COUNT(*) FROM intake_submission_history WHERE intake_case_id = :id',
        'intake_audit_events'       => 'SELECT COUNT(*) FROM intake_audit_events WHERE intake_case_id = :id',
    ][$table];

    $stmt = $k->db->pdo()->prepare($sql);
    $stmt->execute([':id' => $caseId]);

    return (int)$stmt->fetchColumn();
}

/* ==================================================== 確定（locked） */

test('locked: reviewed から確定できる', function (): void {
    $k = retentionKernel();
    [$caseId] = reviewedCase($k, 'HP-202608-9200');

    $result = $k->cases->adminLock($caseId);

    assertSame(true, $result['ok'], '確定できていない');
    assertSame(true, $result['changed'], 'changed が false');

    $case = $k->cases->find($caseId);
    assertSame('locked', $case['status'], '状態が locked でない');
    assertTrue($case['locked_at'] !== null, 'locked_at が記録されていない');
});

test('locked: reviewed 以外からは確定できない', function (): void {
    foreach ([
        'draft'          => static fn (object $k, string $n): int => $k->cases->create($n, '架空サロン'),
        'submitted'      => static fn (object $k, string $n): int => submittedCase($k, $n)[0],
        'needs_revision' => static function (object $k, string $n): int {
            [$id] = submittedCase($k, $n);
            $k->cases->requestRevision($id, ['basic.legal_name'], null, $k->revisions);

            return $id;
        },
        'closed'         => static function (object $k, string $n): int {
            $id = $k->cases->create($n, '架空サロン');
            $k->cases->transitionTo($id, 'closed');

            return $id;
        },
    ] as $status => $make) {
        $k      = retentionKernel();
        $caseId = $make($k, 'HP-202608-92' . substr(md5($status), 0, 2));

        $result = $k->cases->adminLock($caseId);

        assertSame(false, $result['ok'], $status . ' から確定できてしまった');
        assertSame('invalid_transition', $result['error'], $status . ' の理由が違う');
        assertSame($status, $k->cases->find($caseId)['status'], $status . ' の状態が変わっている');
    }
});

test('locked: 確定で token と店舗 session がすべて失効する', function (): void {
    $k = retentionKernel();
    [$caseId, $shopCookies] = reviewedCase($k, 'HP-202608-9201');

    assertSame(1, $k->tokens->activeCount($caseId), '確定前に有効 token が1本でない');
    assertSame(1, $k->sessions->activeCount($caseId), '確定前に有効 session が1本でない');

    $k->cases->adminLock($caseId);

    assertSame(0, $k->tokens->activeCount($caseId), 'token が失効していない');
    assertSame(0, $k->sessions->activeCount($caseId), 'session が失効していない');

    // 店舗はもう読めない（同一文言の 404）
    $res = $k->app->handle(jsonGet('/case', ['cookies' => $shopCookies['cookies']]));
    assertSame(404, $res->status, '確定後も店舗が読めている');
});

test('locked: 確定しても回答・履歴・修正依頼は消えない', function (): void {
    $k = retentionKernel();
    [$caseId] = reviewedCase($k, 'HP-202608-9202');

    $answers   = rowCount($k, 'intake_answers', $caseId);
    $revisions = rowCount($k, 'intake_revision_requests', $caseId);
    $history   = rowCount($k, 'intake_submission_history', $caseId);
    assertTrue($answers > 0 && $revisions > 0 && $history > 0, '確定前に内容が無い');

    $k->cases->adminLock($caseId);

    assertSame($answers, rowCount($k, 'intake_answers', $caseId), '回答が消えている');
    assertSame($revisions, rowCount($k, 'intake_revision_requests', $caseId), '修正依頼が消えている');
    // ★確定は履歴を1件**足す**（locked）。減らさない
    assertSame($history + 1, rowCount($k, 'intake_submission_history', $caseId), '履歴の件数が想定と違う');
});

test('locked: 冪等（2回押しても履歴も監査も増えない）', function (): void {
    $k = retentionKernel();
    [$caseId] = reviewedCase($k, 'HP-202608-9203');

    $k->cases->adminLock($caseId);
    $history = rowCount($k, 'intake_submission_history', $caseId);
    $audit   = rowCount($k, 'intake_audit_events', $caseId);

    $again = $k->cases->adminLock($caseId);

    assertSame(true, $again['ok'], '2回目が失敗している');
    assertSame(false, $again['changed'], '2回目で changed が true');
    assertSame($history, rowCount($k, 'intake_submission_history', $caseId), '履歴が増えている');
    assertSame($audit, rowCount($k, 'intake_audit_events', $caseId), '監査が増えている');
});

test('locked: 確定後は修正依頼にも再発行にも戻せない', function (): void {
    $k = retentionKernel();
    [$caseId] = reviewedCase($k, 'HP-202608-9204');
    $k->cases->adminLock($caseId);

    assertTrue(!in_array('locked', CaseService::REVISABLE, true), 'locked が REVISABLE に入っている');
    assertTrue(!in_array('locked', CaseService::REISSUABLE, true), 'locked が REISSUABLE に入っている');

    $rev = $k->cases->requestRevision($caseId, ['basic.legal_name'], null, $k->revisions);
    assertSame(false, $rev['ok'], '確定後に差し戻せてしまった');

    $re = $k->tokens->reissue($caseId, CaseService::REISSUABLE);
    assertSame(false, $re['ok'], '確定後に再発行できてしまった');
    assertSame(0, $k->tokens->activeCount($caseId), '再発行で token が復活している');
});

test('locked: 画面から確定できる（確認画面 → 案件番号一致 → 実行）', function (): void {
    $k = retentionKernel();
    [$caseId] = reviewedCase($k, 'HP-202608-9205');
    $number   = 'HP-202608-9205';

    $login = loginAdmin($k);
    $form  = $k->app->handle(adminGet('/admin/lock', [
        'cookies' => $login['cookie'], 'query' => ['case' => $number],
    ]));
    assertSame(200, $form->status, '確認画面が出ない');
    assertTrue(str_contains((string)$form->rawBody, '確認のため、案件番号'), '再入力欄が無い');

    preg_match('/name="csrf_token" value="([A-Za-z0-9_-]{43})"/', (string)$form->rawBody, $m);

    // 案件番号が一致しないと実行されない
    $ng = $k->app->handle(adminPost('/admin/lock/send', [
        'csrf_token' => $m[1], 'case' => $number, 'confirm_case' => 'HP-202608-0000',
    ], ['cookies' => $login['cookie']]));
    assertSame(400, $ng->status, '不一致でも実行されている');
    assertSame('reviewed', $k->cases->find($caseId)['status'], '不一致で状態が変わった');

    // 正しい入力で実行できる
    preg_match('/name="csrf_token" value="([A-Za-z0-9_-]{43})"/', (string)$ng->rawBody, $m2);
    $ok = $k->app->handle(adminPost('/admin/lock/send', [
        'csrf_token' => $m2[1], 'case' => $number, 'confirm_case' => $number,
    ], ['cookies' => $login['cookie']]));

    assertSame(303, $ok->status, '実行できていない');
    assertSame('locked', $k->cases->find($caseId)['status'], '確定されていない');
});

test('locked: GET では確定しない', function (): void {
    $k = retentionKernel();
    [$caseId] = reviewedCase($k, 'HP-202608-9206');
    $login    = loginAdmin($k);

    $res = $k->app->handle(adminGet('/admin/lock/send', [
        'cookies' => $login['cookie'],
        'query'   => ['case' => 'HP-202608-9206', 'confirm_case' => 'HP-202608-9206'],
    ]));

    assertSame(403, $res->status, 'GET で通っている');
    assertSame('reviewed', $k->cases->find($caseId)['status'], 'GET で状態が変わった');
});

/* ==================================================== 削除予定日 */

test('due: YYYY-MM-DD の実在する日付だけを受け付ける', function (): void {
    foreach ([
        '2026-12-31' => true,
        '2027-02-28' => true,
        '2028-02-29' => true,   // うるう年
        '2027-02-29' => false,  // うるう年でない
        '2026-13-01' => false,
        '2026-00-10' => false,
        '2026-12-32' => false,
        '2026/12/31' => false,
        '20261231'   => false,
        '2026-12-1'  => false,
        '+1 month'   => false,
        'tomorrow'   => false,
        ''           => false,
        '2025-12-31' => false,  // 範囲外（打ち間違い）
        '9999-12-31' => false,
        "2026-12-31\n" => true, // 前後の空白は落とす
        '2026-12-31; DROP TABLE intake_cases' => false,
    ] as $input => $expected) {
        assertSame(
            $expected,
            RetentionService::checkDate((string)$input)['ok'],
            '判定が違う: ' . var_export($input, true)
        );
    }
});

test('due: reviewed / locked に登録でき、それ以外は拒否される', function (): void {
    $k = retentionKernel();

    [$reviewed] = reviewedCase($k, 'HP-202608-9210');
    assertSame(true, $k->retention->setDeleteDue($reviewed, '2027-03-01')['ok'], 'reviewed へ登録できない');

    [$locked] = reviewedCase($k, 'HP-202608-9211');
    $k->cases->adminLock($locked);
    assertSame(true, $k->retention->setDeleteDue($locked, '2027-03-01')['ok'], 'locked へ登録できない');

    [$submitted] = submittedCase($k, 'HP-202608-9212');
    $ng = $k->retention->setDeleteDue($submitted, '2027-03-01');
    assertSame(false, $ng['ok'], 'submitted へ登録できてしまった');
    assertSame('invalid_status', $ng['error'], '理由が違う');
    assertSame(null, $k->cases->find($submitted)['retention_delete_due'], '日付が入っている');

    $draft = $k->cases->create('HP-202608-9213', '架空サロン');
    assertSame(false, $k->retention->setDeleteDue($draft, '2027-03-01')['ok'], 'draft へ登録できてしまった');
});

test('due: 同じ日付の再送は冪等・変更は監査へ残る', function (): void {
    $k = retentionKernel();
    [$caseId] = reviewedCase($k, 'HP-202608-9214');

    $first = $k->retention->setDeleteDue($caseId, '2027-03-01');
    assertSame(true, $first['changed'], '初回で changed が false');
    assertSame(1, $k->audit->countFor($caseId, 'retention_due_set'), '監査が1件でない');

    $same = $k->retention->setDeleteDue($caseId, '2027-03-01');
    assertSame(true, $same['ok'], '再送が失敗');
    assertSame(false, $same['changed'], '再送で changed が true');
    assertSame(1, $k->audit->countFor($caseId, 'retention_due_set'), '再送で監査が増えた');

    $changed = $k->retention->setDeleteDue($caseId, '2027-04-01');
    assertSame(true, $changed['changed'], '変更で changed が false');
    assertSame(2, $k->audit->countFor($caseId, 'retention_due_set'), '変更が監査に残っていない');
    assertSame('2027-04-01', $k->cases->find($caseId)['retention_delete_due'], '日付が更新されていない');
});

test('due: 過去日は登録できるが、警告として返る', function (): void {
    $k = retentionKernel();
    [$caseId] = reviewedCase($k, 'HP-202608-9215');

    $result = $k->retention->setDeleteDue($caseId, '2026-01-01');

    assertSame(true, $result['ok'], '過去日が登録できない');
    assertSame(true, $result['past'], '過去日の警告が立っていない');
});

test('due: 公開日・公開承認を保存する経路が無い（SSOT v1.7 §4）', function (): void {
    $k = retentionKernel();

    // 案件表に公開日・公開承認の列そのものが無い
    $columns = array_column($k->db->pdo()->query("PRAGMA table_info('intake_cases')")->fetchAll(), 'name');
    foreach (['published_at', 'publish_date', 'published', 'approval', 'approved_at', 'publish_approved_at'] as $banned) {
        assertTrue(!in_array($banned, $columns, true), '公開日・公開承認の列がある: ' . $banned);
    }

    // 画面の入力欄も削除予定日だけ
    [$caseId] = reviewedCase($k, 'HP-202608-9216');
    unset($caseId);
    $login = loginAdmin($k);
    $html  = (string)$k->app->handle(adminGet('/admin/case', [
        'cookies' => $login['cookie'], 'query' => ['case' => 'HP-202608-9216'],
    ]))->rawBody;

    assertTrue(str_contains($html, 'name="due"'), '削除予定日の欄が無い');
    foreach (['公開日', '公開承認', '公開予定'] as $banned) {
        assertTrue(!str_contains($html, 'name="' . $banned), '公開情報の入力欄がある');
    }
    assertTrue(str_contains($html, '公開日はこの画面に入力しないでください'), '注意書きが無い');
});

test('due: 画面から登録でき、不正日付は登録されない', function (): void {
    $k = retentionKernel();
    [$caseId] = reviewedCase($k, 'HP-202608-9217');
    $number   = 'HP-202608-9217';
    $login    = loginAdmin($k);

    $detail = $k->app->handle(adminGet('/admin/case', [
        'cookies' => $login['cookie'], 'query' => ['case' => $number],
    ]));
    preg_match('/name="csrf_token" value="([A-Za-z0-9_-]{43})"/', (string)$detail->rawBody, $m);

    $bad = $k->app->handle(adminPost('/admin/retention/due', [
        'csrf_token' => $m[1], 'case' => $number, 'due' => '2027-02-30',
    ], ['cookies' => $login['cookie']]));
    assertSame(303, $bad->status, '応答が redirect でない');
    assertTrue(str_contains((string)$bad->headers['Location'], 'msg=due_invalid'), '失敗が伝わっていない');
    assertSame(null, $k->cases->find($caseId)['retention_delete_due'], '不正日付が保存された');

    $detail2 = $k->app->handle(adminGet('/admin/case', [
        'cookies' => $login['cookie'], 'query' => ['case' => $number],
    ]));
    preg_match('/name="csrf_token" value="([A-Za-z0-9_-]{43})"/', (string)$detail2->rawBody, $m2);

    $ok = $k->app->handle(adminPost('/admin/retention/due', [
        'csrf_token' => $m2[1], 'case' => $number, 'due' => '2027-03-01',
    ], ['cookies' => $login['cookie']]));
    assertTrue(str_contains((string)$ok->headers['Location'], 'msg=due'), '成功が伝わっていない');
    assertSame('2027-03-01', $k->cases->find($caseId)['retention_delete_due'], '保存されていない');
});

/* ==================================================== 期限区分 */

test('bucket: 期限の区分がSSOTどおりに分かれる', function (): void {
    $k     = retentionKernel();
    $today = '2026-08-27';

    foreach ([
        [null, null, 'unset'],
        ['2026-08-26', null, 'overdue'],
        ['2026-08-27', null, 'due'],
        ['2026-08-28', null, 'soon'],
        ['2026-09-26', null, 'soon'],     // 30日ちょうど
        ['2026-09-27', null, 'later'],    // 31日目
        ['2027-01-01', null, 'later'],
        ['2026-01-01', '2026-08-01T00:00:00Z', 'deleted'],  // 削除済みが最優先
        [null, '2026-08-01T00:00:00Z', 'deleted'],
    ] as [$due, $deleted, $expected]) {
        assertSame($expected, $k->retention->bucketOf($due, $deleted, $today),
            '区分が違う: ' . var_export($due, true));
    }
});

test('bucket: 一覧に回答本文・店舗名・Drive 情報を出さない', function (): void {
    $k = retentionKernel();
    [$caseId] = lockedCase($k, 'HP-202608-9220');
    unset($caseId);

    $rows = $k->retention->listForRetention();
    assertTrue($rows !== [], '一覧が空');
    foreach ($rows as $row) {
        assertSame(
            ['case_number', 'status', 'retention_delete_due', 'deleted_at', 'bucket'],
            array_keys($row),
            '一覧が余分な列を返している'
        );
    }

    $login = loginAdmin($k);
    $html  = (string)$k->app->handle(adminGet('/admin/retention', ['cookies' => $login['cookie']]))->rawBody;

    foreach ([RET_MARKER, RET_DRIVE_EMAIL, 'drive.google.com', 'ハルカゼ', '架空県'] as $banned) {
        assertTrue(!str_contains($html, $banned), '一覧に出てはいけない値がある: ' . $banned);
    }
    assertTrue(str_contains($html, 'HP-202608-9220'), '案件番号が出ていない');
});

/* ==================================================== 削除の拒否条件 */

test('purge: retention_actions_enabled が false なら実行しない', function (): void {
    $k = retentionKernel(null, ['retention_actions_enabled' => false]);
    [$caseId] = lockedCase($k, 'HP-202608-9230');

    assertSame(false, $k->config->retentionEnabled(), 'フラグが有効になっている');

    $login = loginAdmin($k);
    $form  = $k->app->handle(adminGet('/admin/purge', [
        'cookies' => $login['cookie'], 'query' => ['case' => 'HP-202608-9230'],
    ]));
    assertSame(403, $form->status, '確認画面が出てしまった');

    $send = $k->app->handle(adminPost('/admin/purge/send', [
        'csrf_token' => $login['csrf'], 'case' => 'HP-202608-9230',
        'confirm'    => 'DELETE HP-202608-9230',
    ], ['cookies' => $login['cookie']]));
    assertSame(403, $send->status, '実行できてしまった');
    assertSame(1, rowCount($k, 'intake_answers', $caseId), '回答が消えている');
});

test('purge: backup_policy_confirmed が false なら実行しない', function (): void {
    $k = retentionKernel(null, ['backup_policy_confirmed' => false]);
    [$caseId] = lockedCase($k, 'HP-202608-9231');

    assertSame(false, $k->config->retentionEnabled(), 'フラグが有効になっている');

    $login = loginAdmin($k);
    $send  = $k->app->handle(adminPost('/admin/purge/send', [
        'csrf_token' => $login['csrf'], 'case' => 'HP-202608-9231',
        'confirm'    => 'DELETE HP-202608-9231',
    ], ['cookies' => $login['cookie']]));

    assertSame(403, $send->status, '実行できてしまった');
    assertSame(1, rowCount($k, 'intake_answers', $caseId), '回答が消えている');
});

test('purge: 既定（設定なし）では両方のフラグが false', function (): void {
    $k = adminKernel();

    assertSame(false, $k->config->retentionActionsEnabled, 'retention_actions_enabled の既定が true');
    assertSame(false, $k->config->backupPolicyConfirmed, 'backup_policy_confirmed の既定が true');
    assertSame(false, $k->config->retentionEnabled(), '既定で削除が有効になっている');
});

test('purge: フラグは「明示的に真」と書いたときだけ真になる', function (): void {
    foreach (['1' => true, 'true' => true, 'yes' => true, 'on' => true, 'TRUE' => true,
              '0' => false, 'false' => false, 'off' => false, 'no' => false,
              '' => false, 'maybe' => false, '2' => false] as $value => $expected) {
        $k = adminKernel(null, [
            'retention_actions_enabled' => (string)$value,
            'backup_policy_confirmed'   => (string)$value,
        ]);
        assertSame($expected, $k->config->retentionEnabled(),
            '"' . $value . '" の解釈が違う');
    }
});

test('purge: 状態が locked でなければ実行しない', function (): void {
    $k = retentionKernel();

    foreach (['draft', 'submitted', 'reviewed'] as $i => $status) {
        $number = 'HP-202608-924' . $i;
        if ($status === 'draft') {
            $caseId = $k->cases->create($number, '架空サロン');
        } elseif ($status === 'submitted') {
            [$caseId] = submittedCase($k, $number);
        } else {
            [$caseId] = reviewedCase($k, $number);
        }
        // 期限は満たしていても、状態が違えば通らない
        $k->db->pdo()->prepare('UPDATE intake_cases SET retention_delete_due = :d WHERE id = :id')
            ->execute([':d' => '2026-01-01', ':id' => $caseId]);

        $gate = $k->retention->canPurge($k->cases->find($caseId));
        assertSame(false, $gate['ok'], $status . ' で通ってしまった');
        assertSame('invalid_status', $gate['error'], $status . ' の理由が違う');

        $result = $k->retention->purgeCase($caseId, $number, 'DELETE ' . $number);
        assertSame(false, $result['ok'], $status . ' で削除できてしまった');
        assertSame(1, rowCount($k, 'intake_answers', $caseId), $status . ' の回答が消えている');
    }
});

test('purge: 削除予定日が未設定なら実行しない', function (): void {
    $k = retentionKernel();
    [$caseId] = reviewedCase($k, 'HP-202608-9245');
    $k->cases->adminLock($caseId);

    $gate = $k->retention->canPurge($k->cases->find($caseId));
    assertSame(false, $gate['ok'], '期限未設定で通ってしまった');
    assertSame('due_not_set', $gate['error'], '理由が違う');

    $result = $k->retention->purgeCase($caseId, 'HP-202608-9245', 'DELETE HP-202608-9245');
    assertSame(false, $result['ok'], '削除できてしまった');
});

test('purge: 期限より前は実行しない（テスト時計で期限到来させると通る）', function (): void {
    $clock = new TestClock();
    $k     = retentionKernel($clock);

    $due = gmdate('Y-m-d', $clock->now() + (40 * 86400));
    [$caseId] = lockedCase($k, 'HP-202608-9246', $due);

    $gate = $k->retention->canPurge($k->cases->find($caseId));
    assertSame(false, $gate['ok'], '期限前に通ってしまった');
    assertSame('not_due', $gate['error'], '理由が違う');
    assertSame(false, $k->retention->purgeCase($caseId, 'HP-202608-9246', 'DELETE HP-202608-9246')['ok'],
        '期限前に削除できてしまった');
    assertSame(1, rowCount($k, 'intake_answers', $caseId), '回答が消えている');

    // 時計を進めて期限到来
    $clock->advance(41 * 86400);
    assertSame(true, $k->retention->canPurge($k->cases->find($caseId))['ok'], '期限到来後も通らない');
    assertSame(true, $k->retention->purgeCase($caseId, 'HP-202608-9246', 'DELETE HP-202608-9246')['ok'],
        '期限到来後に削除できない');
});

test('purge: 確認文が完全一致しなければ実行しない', function (): void {
    $k = retentionKernel();
    [$caseId] = lockedCase($k, 'HP-202608-9247');

    // ★一致しない入力は1つも通さない（ここでは削除が起きてはならない）
    foreach ([
        '', 'DELETE', 'delete HP-202608-9247', 'DELETE  HP-202608-9247',
        'DELETE HP-202608-9248', 'HP-202608-9247', 'DELETEHP-202608-9247',
        'DELETE HP-202608-9247x', 'DELETE HP-202608-924',
    ] as $input) {
        $result = $k->retention->purgeCase($caseId, 'HP-202608-9247', $input);

        assertSame(false, $result['ok'], '通ってしまった: ' . var_export($input, true));
        assertSame('confirm_mismatch', $result['error'], '理由が違う: ' . var_export($input, true));
        assertSame(1, rowCount($k, 'intake_answers', $caseId), '回答が消えている');
    }

    // ★前後の空白だけは落として受ける（貼り付けの改行・空白で止めない）
    $ok = $k->retention->purgeCase($caseId, 'HP-202608-9247', "  DELETE HP-202608-9247
");
    assertSame(true, $ok['ok'], '前後の空白つきで通らない');
    assertSame(0, rowCount($k, 'intake_answers', $caseId), '削除されていない');
});

test('purge: 案件番号が食い違う要求は実行しない', function (): void {
    $k = retentionKernel();
    [$caseId] = lockedCase($k, 'HP-202608-9248');

    $result = $k->retention->purgeCase($caseId, 'HP-202608-0000', 'DELETE HP-202608-0000');

    assertSame(false, $result['ok'], '別の案件番号で通ってしまった');
    assertSame('not_found', $result['error'], '理由が違う');
    assertSame(1, rowCount($k, 'intake_answers', $caseId), '回答が消えている');
});

/* ==================================================== 削除の実行 */

test('purge: 期限到来の確定案件から機密情報を物理削除する', function (): void {
    $k = retentionKernel();
    [$caseId] = lockedCase($k, 'HP-202608-9250');

    // 削除前は全部そろっている
    foreach (['intake_answers', 'intake_tokens', 'intake_sessions',
              'intake_revision_requests', 'intake_submission_history'] as $table) {
        assertTrue(rowCount($k, $table, $caseId) > 0, $table . ' が削除前に空');
    }

    $result = $k->retention->purgeCase($caseId, 'HP-202608-9250', 'DELETE HP-202608-9250');
    assertSame(true, $result['ok'], '削除できていない');

    // 5つの表が行ごと消える
    foreach (RetentionService::PURGED_TABLES as $table) {
        assertSame(0, rowCount($k, $table, $caseId), $table . ' が残っている');
    }
});

test('purge: 削除後の案件行に PII も Drive 参照も残らない', function (): void {
    $k = retentionKernel();
    [$caseId] = lockedCase($k, 'HP-202608-9251');

    $k->retention->purgeCase($caseId, 'HP-202608-9251', 'DELETE HP-202608-9251');
    $case = $k->cases->find($caseId);

    assertSame(null, $case['drive_folder_url_enc'], 'Drive URL の暗号文が残っている');
    assertSame(null, $case['drive_shared_email_enc'], '共有先メールの暗号文が残っている');
    assertSame(null, $case['drive_folder_label'], 'フォルダ名が残っている');
    assertSame(null, $case['current_step'], '内部状態が残っている');
    assertSame(null, $case['expires_at'], 'expires_at が残っている');
    assertSame('（削除済み）', $case['shop_display_name'], '店舗名が残っている');

    // 復号できる参照が1つも無い
    assertSame(null, $k->cases->driveFolderUrl($caseId), 'Drive URL を復号できてしまう');
    assertSame(null, $k->cases->driveSharedEmail($caseId), '共有先メールを復号できてしまう');
});

test('purge: 削除後も案件番号・状態・日付は残る（SSOT v1.7 §9.4）', function (): void {
    $k = retentionKernel();
    [$caseId] = lockedCase($k, 'HP-202608-9252');
    $before   = $k->cases->find($caseId);

    $k->retention->purgeCase($caseId, 'HP-202608-9252', 'DELETE HP-202608-9252');
    $after = $k->cases->find($caseId);

    assertSame('HP-202608-9252', $after['case_number'], '案件番号が消えている');
    assertSame('closed', $after['status'], '状態が closed でない');
    assertSame($before['contract_type'], $after['contract_type'], '契約種別が変わっている');
    assertSame($before['submitted_at'], $after['submitted_at'], '提出日時が消えている');
    assertSame($before['locked_at'], $after['locked_at'], '確定日時が消えている');
    assertSame($before['created_at'], $after['created_at'], '作成日時が変わっている');
    assertSame($before['retention_delete_due'], $after['retention_delete_due'], '削除予定日が消えている');
    assertTrue($after['deleted_at'] !== null, '削除実施日が記録されていない');
    assertTrue($after['closed_at'] !== null, '終了日時が記録されていない');
});

test('purge: intake_cases の全列が「残す・NULLにする・置き換える」に分類されている', function (): void {
    $k       = retentionKernel();
    $columns = array_column($k->db->pdo()->query("PRAGMA table_info('intake_cases')")->fetchAll(), 'name');

    $classified = array_merge(
        RetentionService::CASE_KEEP,
        RetentionService::CASE_NULLED,
        array_keys(RetentionService::CASE_REPLACED)
    );

    // 未分類の列がない（＝列を足したら必ず判断を迫られる）
    assertSame([], array_values(array_diff($columns, $classified)),
        '分類されていない列がある: ' . implode(', ', array_diff($columns, $classified)));
    // 実在しない列を分類していない
    assertSame([], array_values(array_diff($classified, $columns)),
        '実在しない列を分類している: ' . implode(', ', array_diff($classified, $columns)));
    // 同じ列を二重に分類していない
    assertSame(count($classified), count(array_unique($classified)), '同じ列が二重に分類されている');
});

test('purge: 監査は retention_purged が1件だけ増え、値を持たない', function (): void {
    $k = retentionKernel();
    [$caseId] = lockedCase($k, 'HP-202608-9253');

    $before = $k->audit->countFor($caseId, 'retention_purged');
    $k->retention->purgeCase($caseId, 'HP-202608-9253', 'DELETE HP-202608-9253');

    assertSame($before + 1, $k->audit->countFor($caseId, 'retention_purged'), '監査が1件でない');

    // 監査行の中身に値が混ざっていない
    $stmt = $k->db->pdo()->prepare(
        'SELECT * FROM intake_audit_events WHERE intake_case_id = :id AND event_type = :e'
    );
    $stmt->execute([':id' => $caseId, ':e' => 'retention_purged']);
    foreach ($stmt->fetchAll() as $row) {
        assertSame('ok', $row['result_code'], 'result_code が固定語彙でない');
        $joined = implode('|', array_map('strval', $row));
        foreach ([RET_MARKER, RET_DRIVE_EMAIL, 'drive.google', 'ハルカゼ', 'DELETE '] as $banned) {
            assertTrue(!str_contains($joined, $banned), '監査に値が書かれている: ' . $banned);
        }
    }
});

test('purge: 削除しても監査ログそのものは消えない（13か月の対象として残る）', function (): void {
    $k = retentionKernel();
    [$caseId] = lockedCase($k, 'HP-202608-9254');

    $before = rowCount($k, 'intake_audit_events', $caseId);
    assertTrue($before > 0, '削除前に監査が無い');

    $k->retention->purgeCase($caseId, 'HP-202608-9254', 'DELETE HP-202608-9254');

    assertTrue(rowCount($k, 'intake_audit_events', $caseId) > $before, '監査が減っている');
});

test('purge: DBに架空の目印が1つも残らない', function (): void {
    $k = retentionKernel();
    [$caseId] = lockedCase($k, 'HP-202608-9255');
    unset($caseId);

    $k->retention->purgeCase(
        (int)$k->cases->findByNumber('HP-202608-9255')['id'],
        'HP-202608-9255',
        'DELETE HP-202608-9255'
    );
    $k->db->close();

    $raw = (string)file_get_contents($k->config->dbPath);
    foreach ([RET_MARKER, RET_DRIVE_EMAIL, 'ハルカゼ', '架空県架空市架空町', '03-0000-0000'] as $banned) {
        assertTrue(!str_contains($raw, $banned), 'DBファイルに残っている: ' . $banned);
    }
    assertTrue(str_contains($raw, 'HP-202608-9255'), '案件番号まで消えている（検査が空振り）');
});

test('purge: 削除した内容をDBページ上にも残さない（secure_delete）', function (): void {
    $k = retentionKernel();

    assertSame(true, $k->db->secureDelete(), 'secure_delete が有効になっていない');
    assertSame('delete', $k->db->journalMode(), 'journal_mode が変わっている');
    assertSame(true, $k->db->foreignKeysOn(), 'foreign_keys が切れている');
});

test('purge: 途中で失敗したら全部戻る（部分削除を作らない）', function (): void {
    $k = retentionKernel();
    [$caseId] = lockedCase($k, 'HP-202608-9256');

    $before = [];
    foreach (RetentionService::PURGED_TABLES as $table) {
        $before[$table] = rowCount($k, $table, $caseId);
    }

    // 最後の DELETE（提出履歴）で必ず失敗させる
    $k->db->pdo()->exec(
        'CREATE TRIGGER t_retention_fail BEFORE DELETE ON intake_submission_history
         BEGIN SELECT RAISE(ABORT, "test"); END'
    );

    $threw = false;
    try {
        $k->retention->purgeCase($caseId, 'HP-202608-9256', 'DELETE HP-202608-9256');
    } catch (\Throwable $e) {
        $threw = true;
    }
    $k->db->pdo()->exec('DROP TRIGGER t_retention_fail');

    assertSame(true, $threw, '失敗が伝わっていない');

    foreach (RetentionService::PURGED_TABLES as $table) {
        assertSame($before[$table], rowCount($k, $table, $caseId), $table . ' が部分的に消えている');
    }
    $case = $k->cases->find($caseId);
    assertSame('locked', $case['status'], '状態が変わっている');
    assertSame(null, $case['deleted_at'], '削除済みになっている');
    assertTrue($case['drive_folder_url_enc'] !== null, 'Drive URL が消えている');
    assertSame(0, $k->audit->countFor($caseId, 'retention_purged'), '監査だけ残っている');
});

test('purge: 削除中の失敗でも内部情報を漏らさない（固定の 500）', function (): void {
    $k = retentionKernel();
    [$caseId] = lockedCase($k, 'HP-202608-9257');
    unset($caseId);

    $login = loginAdmin($k);
    $form  = $k->app->handle(adminGet('/admin/purge', [
        'cookies' => $login['cookie'], 'query' => ['case' => 'HP-202608-9257'],
    ]));
    preg_match('/name="csrf_token" value="([A-Za-z0-9_-]{43})"/', (string)$form->rawBody, $m);

    $k->db->pdo()->exec(
        'CREATE TRIGGER t_retention_fail2 BEFORE DELETE ON intake_answers
         BEGIN SELECT RAISE(ABORT, "secret internal detail"); END'
    );
    $res = $k->app->handle(adminPost('/admin/purge/send', [
        'csrf_token' => $m[1], 'case' => 'HP-202608-9257', 'confirm' => 'DELETE HP-202608-9257',
    ], ['cookies' => $login['cookie']]));
    $k->db->pdo()->exec('DROP TRIGGER t_retention_fail2');

    assertSame(500, $res->status, '500 になっていない');
    $body = $res->rawBody ?? $res->json();
    foreach (['secret internal detail', 'SQLSTATE', 'intake_answers', 'RetentionService', '.php'] as $banned) {
        assertTrue(!str_contains($body, $banned), '内部情報が出ている: ' . $banned);
    }
});

/* ==================================================== 削除済み案件の扱い */

test('purge: 削除済み案件は書き出せない', function (): void {
    $k = retentionKernel();
    [$caseId] = lockedCase($k, 'HP-202608-9260');

    assertSame(true, $k->export->export($caseId)['ok'], '削除前に書き出せない');

    $k->retention->purgeCase($caseId, 'HP-202608-9260', 'DELETE HP-202608-9260');

    $result = $k->export->export($caseId);
    assertSame(false, $result['ok'], '削除後に書き出せてしまった');
    assertSame('deleted', $result['error'], '理由が違う');

    $login = loginAdmin($k);
    $res   = $k->app->handle(adminGet('/admin/export', [
        'cookies' => $login['cookie'], 'query' => ['case' => 'HP-202608-9260'],
    ]));
    assertSame(409, $res->status, '画面から書き出せてしまった');
    assertTrue(!str_contains((string)$res->rawBody, RET_MARKER), '書き出し失敗画面に値が出ている');
});

test('purge: 削除済み案件へ token を発行・再発行できない', function (): void {
    $k = retentionKernel();
    [$caseId] = lockedCase($k, 'HP-202608-9261');
    $k->retention->purgeCase($caseId, 'HP-202608-9261', 'DELETE HP-202608-9261');

    foreach ([CaseService::REISSUABLE, CaseService::STATUSES] as $allowed) {
        $result = $k->tokens->reissue($caseId, $allowed);
        assertSame(false, $result['ok'], '削除済みへ再発行できてしまった');
    }
    assertSame(0, rowCount($k, 'intake_tokens', $caseId), 'token が作られている');

    // 画面からも出せない
    $login = loginAdmin($k);
    $res   = $k->app->handle(adminGet('/admin/reissue', [
        'cookies' => $login['cookie'], 'query' => ['case' => 'HP-202608-9261'],
    ]));
    assertSame(409, $res->status, '再発行の確認画面が出てしまった');
});

test('purge: 削除済み案件の古い token / session では入れない', function (): void {
    $k = retentionKernel();
    [$caseId, $shopCookies] = lockedCase($k, 'HP-202608-9262');
    $k->retention->purgeCase($caseId, 'HP-202608-9262', 'DELETE HP-202608-9262');

    foreach (['/case'] as $path) {
        $res = $k->app->handle(jsonGet($path, ['cookies' => $shopCookies['cookies']]));
        assertSame(404, $res->status, $path . ' が通っている');
    }
    foreach (['/answers/save', '/submit', '/drive/confirm'] as $path) {
        $res = $k->app->handle(jsonPost($path, ['version' => 1, 'sections' => []], $shopCookies));
        assertSame(404, $res->status, $path . ' が通っている');
    }
    assertSame(0, rowCount($k, 'intake_answers', $caseId), '回答行が復活している');
});

test('purge: 削除済み案件は状態を戻せない', function (): void {
    $k = retentionKernel();
    [$caseId] = lockedCase($k, 'HP-202608-9263');
    $k->retention->purgeCase($caseId, 'HP-202608-9263', 'DELETE HP-202608-9263');

    foreach (['draft', 'submitted', 'needs_revision', 'reviewed', 'locked'] as $to) {
        $result = $k->cases->adminChangeStatus($caseId, $to, 'reviewed');
        assertSame(false, $result['ok'], $to . ' へ戻せてしまった');
        assertSame('already_deleted', $result['error'], $to . ' の理由が違う');
    }
    assertSame(false, $k->cases->adminLock($caseId)['ok'], '確定できてしまった');
    assertSame(false, $k->cases->requestRevision($caseId, ['basic.legal_name'], null, $k->revisions)['ok'],
        '修正依頼できてしまった');
    assertSame('closed', $k->cases->find($caseId)['status'], '状態が変わっている');
});

test('purge: 二度目の削除は安全に拒否される', function (): void {
    $k = retentionKernel();
    [$caseId] = lockedCase($k, 'HP-202608-9264');

    assertSame(true, $k->retention->purgeCase($caseId, 'HP-202608-9264', 'DELETE HP-202608-9264')['ok'],
        '1回目が失敗');
    $deletedAt = $k->cases->find($caseId)['deleted_at'];
    $audit     = $k->audit->countFor($caseId, 'retention_purged');

    $second = $k->retention->purgeCase($caseId, 'HP-202608-9264', 'DELETE HP-202608-9264');

    assertSame(false, $second['ok'], '2回目が実行されてしまった');
    assertSame('already_deleted', $second['error'], '理由が違う');
    assertSame($deletedAt, $k->cases->find($caseId)['deleted_at'], '削除実施日が上書きされた');
    assertSame($audit, $k->audit->countFor($caseId, 'retention_purged'), '監査が増えた');

    // 削除予定日も動かせない
    assertSame('already_deleted', $k->retention->setDeleteDue($caseId, '2027-01-01')['error'],
        '削除済みへ削除予定日を登録できてしまった');
});

test('purge: 削除済み案件の詳細は最小メタデータだけを出す', function (): void {
    $k = retentionKernel();
    [$caseId] = lockedCase($k, 'HP-202608-9265');
    $k->retention->purgeCase($caseId, 'HP-202608-9265', 'DELETE HP-202608-9265');

    $login = loginAdmin($k);
    $res   = $k->app->handle(adminGet('/admin/case', [
        'cookies' => $login['cookie'], 'query' => ['case' => 'HP-202608-9265'],
    ]));

    assertSame(200, $res->status, '詳細が開けない');
    $html = (string)$res->rawBody;

    assertTrue(str_contains($html, 'HP-202608-9265'), '案件番号が出ていない');
    assertTrue(str_contains($html, '削除済み'), '削除済みの説明が無い');
    foreach ([RET_MARKER, RET_DRIVE_EMAIL, 'drive.google.com', 'ハルカゼ', '架空県', '03-0000-0000'] as $banned) {
        assertTrue(!str_contains($html, $banned), '詳細に残っている: ' . $banned);
    }
    // 操作ボタンも出さない
    foreach (['/admin/revision?', '/admin/reissue?', '/admin/lock?', '/admin/purge?', '/admin/export?'] as $link) {
        assertTrue(!str_contains($html, $link), '削除済みなのに操作リンクがある: ' . $link);
    }
});

test('purge: 画面から削除でき、確認文が違えば実行されない', function (): void {
    $k = retentionKernel();
    [$caseId] = lockedCase($k, 'HP-202608-9266');
    $number   = 'HP-202608-9266';
    $login    = loginAdmin($k);

    $form = $k->app->handle(adminGet('/admin/purge', [
        'cookies' => $login['cookie'], 'query' => ['case' => $number],
    ]));
    assertSame(200, $form->status, '確認画面が出ない');
    foreach (['元に戻せません', 'Google Drive の実ファイルはここでは消えません',
              'バックアップにも保持期限があります', '継続保持する情報', 'DELETE ' . $number] as $needed) {
        assertTrue(str_contains((string)$form->rawBody, $needed), '確認画面に説明が無い: ' . $needed);
    }

    preg_match('/name="csrf_token" value="([A-Za-z0-9_-]{43})"/', (string)$form->rawBody, $m);
    $ng = $k->app->handle(adminPost('/admin/purge/send', [
        'csrf_token' => $m[1], 'case' => $number, 'confirm' => 'delete ' . $number,
    ], ['cookies' => $login['cookie']]));
    assertSame(400, $ng->status, '確認文が違うのに実行された');
    assertSame(1, rowCount($k, 'intake_answers', $caseId), '回答が消えている');

    preg_match('/name="csrf_token" value="([A-Za-z0-9_-]{43})"/', (string)$ng->rawBody, $m2);
    $ok = $k->app->handle(adminPost('/admin/purge/send', [
        'csrf_token' => $m2[1], 'case' => $number, 'confirm' => 'DELETE ' . $number,
    ], ['cookies' => $login['cookie']]));

    assertSame(303, $ok->status, '実行できていない');
    assertTrue(str_contains((string)$ok->headers['Location'], 'msg=purged'), '結果が伝わっていない');
    assertSame(0, rowCount($k, 'intake_answers', $caseId), '回答が消えていない');
});

test('purge: 削除は POST・CSRF・Origin・管理session がすべて要る', function (): void {
    $k = retentionKernel();
    [$caseId] = lockedCase($k, 'HP-202608-9267');
    $number   = 'HP-202608-9267';
    $login    = loginAdmin($k);

    $cases = [
        'GET'          => $k->app->handle(adminGet('/admin/purge/send', [
            'cookies' => $login['cookie'], 'query' => ['case' => $number, 'confirm' => 'DELETE ' . $number],
        ])),
        'CSRF欠落'      => $k->app->handle(adminPost('/admin/purge/send', [
            'case' => $number, 'confirm' => 'DELETE ' . $number,
        ], ['cookies' => $login['cookie']])),
        'CSRF不一致'    => $k->app->handle(adminPost('/admin/purge/send', [
            'csrf_token' => str_repeat('x', 43), 'case' => $number, 'confirm' => 'DELETE ' . $number,
        ], ['cookies' => $login['cookie']])),
        '不正Origin'    => $k->app->handle(adminPost('/admin/purge/send', [
            'csrf_token' => $login['csrf'], 'case' => $number, 'confirm' => 'DELETE ' . $number,
        ], ['cookies' => $login['cookie'], 'origin' => 'https://evil.example.invalid'])),
        '管理session無し' => $k->app->handle(adminPost('/admin/purge/send', [
            'csrf_token' => $login['csrf'], 'case' => $number, 'confirm' => 'DELETE ' . $number,
        ], [])),
    ];

    foreach ($cases as $label => $res) {
        assertTrue(in_array($res->status, [303, 403], true), $label . ' の応答が想定外: ' . $res->status);
        if ($res->status === 303) {
            assertSame('/admin/login', $res->headers['Location'] ?? null, $label . ' がログインへ送られていない');
        }
        assertSame(1, rowCount($k, 'intake_answers', $caseId), $label . ' で削除されてしまった');
    }
});

test('purge: 店舗の Cookie では削除経路へ入れない', function (): void {
    $k = retentionKernel();
    [$caseId, $shopCookies] = lockedCase($k, 'HP-202608-9268');

    foreach (['/admin/purge', '/admin/purge/send', '/admin/lock/send', '/admin/retention'] as $path) {
        $res = $k->app->handle(adminPost($path, [
            'case' => 'HP-202608-9268', 'confirm' => 'DELETE HP-202608-9268',
        ], ['cookies' => $shopCookies['cookies']]));

        assertTrue(in_array($res->status, [303, 403], true), $path . ' の応答が想定外');
        assertTrue(!str_contains((string)($res->rawBody ?? ''), 'HP-202608-9268'),
            $path . ' が案件番号を漏らしている');
    }
    assertSame(1, rowCount($k, 'intake_answers', $caseId), '削除されている');
});

/* ==================================================== 削除済みの書き出し拒否（4F §2） */

/**
 * 4F-PRE の mutation M9 は「削除済みでも書き出せる」改変を検出したが、
 * 落ち方が **回答行が無いことによる例外**だった。
 * つまり「状態ゲートが効いている」ことを見ていなかった。
 *
 * ここでは **回答行を残したまま** `deleted_at` だけを立てる。
 * 例外が起きる余地を消したうえで、ゲートそのものが拒否理由になることを見る。
 *
 * @return array{0:object,1:int,2:string} [kernel, caseId, caseNumber]
 */
function deletedButAnswersIntact(string $number): array
{
    $k = retentionKernel();
    [$caseId] = reviewedCase($k, $number);
    $k->cases->adminLock($caseId);

    // ★purgeCase() を通さない。回答・履歴・修正依頼は**残したまま**、
    //   案件行だけを「削除済み」の形にする。
    $k->db->pdo()->prepare(
        'UPDATE intake_cases SET status = :s, closed_at = :now, deleted_at = :now WHERE id = :id'
    )->execute([':s' => 'closed', ':now' => '2026-08-27T00:00:00Z', ':id' => $caseId]);

    return [$k, $caseId, $number];
}

test('export: 削除済み案件は「回答が無いから」ではなく状態ゲートで拒否される', function (): void {
    [$k, $caseId, $number] = deletedButAnswersIntact('HP-202608-9290');

    // 前提: 回答行は**残っている**（例外で落ちる余地が無い）
    assertSame(1, rowCount($k, 'intake_answers', $caseId), '回答行が消えている（検査が空振り）');
    assertSame([], $k->answers->evaluate($caseId)['missing'], '必須が欠けている（検査が空振り）');
    assertSame('closed', $k->cases->find($caseId)['status'], '状態が closed でない');
    assertTrue(in_array('closed', ExportService::EXPORTABLE, true),
        'closed が EXPORTABLE から外れた（この検査の前提が崩れている）');

    $before = $k->audit->countFor($caseId, 'export_generated');

    $result = $k->export->export($caseId);

    // 拒否の**理由**まで見る。not_exportable でも incomplete でもない
    assertSame(false, $result['ok'], '削除済みなのに書き出せた');
    assertSame('deleted', $result['error'], '拒否理由が状態ゲートでない');

    // 本文が1バイトも作られていない
    assertTrue(!array_key_exists('json', $result), 'JSON 本文が作られている');
    assertTrue(!array_key_exists('file_name', $result), 'ファイル名が作られている');
    assertTrue(!array_key_exists('sha256', $result), 'SHA-256 が作られている');
    assertSame(['ok', 'error'], array_keys($result), '応答に余分なキーがある');

    // 監査が増えていない
    assertSame($before, $k->audit->countFor($caseId, 'export_generated'), '監査が増えている');
    unset($number);
});

test('export: 回答行が無い（実際に削除済み）案件でも、例外ではなく同じ理由で拒否される', function (): void {
    $k = retentionKernel();
    [$caseId] = lockedCase($k, 'HP-202608-9291');
    $k->retention->purgeCase($caseId, 'HP-202608-9291', 'DELETE HP-202608-9291');

    assertSame(0, rowCount($k, 'intake_answers', $caseId), '回答行が残っている');

    $result = $k->export->export($caseId);

    assertSame(false, $result['ok'], '書き出せてしまった');
    assertSame('deleted', $result['error'], '拒否理由が違う（例外や別条件で落ちている）');
});

test('export: 状態ゲートは回答の読み出しより前にある（到達しない）', function (): void {
    $code = stripPhpComments((string)file_get_contents(__DIR__ . '/../src/Service/ExportService.php'));

    $gate = strpos($code, "'deleted'");
    assertTrue($gate !== false, '削除済みの拒否が見当たらない');

    // 回答へ触る最初の位置より前に、ゲートがあること
    foreach (['$this->answers->evaluate', '$this->answers->get', 'buildPayload'] as $touch) {
        $at = strpos($code, $touch);
        assertTrue($at !== false, $touch . ' が見当たらない');
        assertTrue($gate < $at, '削除済みの拒否が ' . $touch . ' より後ろにある');
    }
});

test('export: 削除済み案件の画面応答は固定・ファイルも作られない', function (): void {
    [$k, $caseId, $number] = deletedButAnswersIntact('HP-202608-9292');
    unset($caseId);

    $login  = loginAdmin($k);
    $before = glob(sys_get_temp_dir() . '/*intake*') ?: [];

    $res = $k->app->handle(adminGet('/admin/export', [
        'cookies' => $login['cookie'], 'query' => ['case' => $number],
    ]));

    assertSame(409, $res->status, '応答が 409 でない');
    assertSame('text/html; charset=UTF-8', $res->headers['Content-Type'] ?? null, 'HTML で返っていない');

    // 書き出しの痕跡が1つも無い
    assertTrue(!isset($res->headers['Content-Disposition']), 'Content-Disposition が出ている');
    assertTrue(!isset($res->headers['X-Intake-Export-Sha256']), 'SHA-256 ヘッダーが出ている');
    assertTrue(!isset($res->headers['Content-Length']), 'Content-Length が出ている');
    assertSame('no-store, no-cache, must-revalidate', $res->headers['Cache-Control'] ?? null, 'no-store でない');
    assertSame('nosniff', $res->headers['X-Content-Type-Options'] ?? null, 'nosniff が無い');

    // 本文に回答が1つも出ていない
    $body = (string)$res->rawBody;
    foreach ([RET_MARKER, RET_DRIVE_EMAIL, 'ハルカゼ', '架空県', 'export_schema_version', 'hp_intake'] as $banned) {
        assertTrue(!str_contains($body, $banned), '応答に書き出し内容が出ている: ' . $banned);
    }

    // 一時ファイルを作っていない
    assertSame($before, glob(sys_get_temp_dir() . '/*intake*') ?: [], '一時ファイルが作られている');
});

/* ==================================================== 監査の13か月削除 */

test('audit: 保持期間は13か月そのもの（実装の計算に頼らず確かめる）', function (): void {
    // ★4F の mutation で見つかった穴をふさぐ。
    //   境目のテストは auditCutoff() を基準に前後へ行を置くため、
    //   保持月数そのものを変えられても**気づけなかった**。
    //   ここでは「13」という値と、絶対時刻での残す／消すを直接見る。
    assertSame(13, RetentionService::AUDIT_RETENTION_MONTHS, '保持月数が SSOT §9.1 と違う');

    $k   = retentionKernel();
    $now = time();
    $ago = (int)strtotime($k->retention->auditCutoff());
    $days = (int)floor(($now - $ago) / 86400);
    // 13か月 = 395〜397日（月の長さで前後する）。ここを外れたら期間が変わっている
    assertTrue($days >= 393 && $days <= 400, '保持の境目が13か月から外れている: ' . $days . '日');

    $caseId = $k->cases->create('HP-202608-9275', '架空サロン');
    $insert = $k->db->pdo()->prepare(
        'INSERT INTO intake_audit_events (intake_case_id, event_type, result_code, ip_hmac, created_at)
         VALUES (:id, :e, :r, NULL, :at)'
    );
    // ★絶対時刻で置く。実装がどこを境目と考えていても、この2件の扱いは決まっている
    $keep = gmdate('Y-m-d\TH:i:s\Z', $now - (365 * 86400));   // 12か月前 → 残す
    $drop = gmdate('Y-m-d\TH:i:s\Z', $now - (425 * 86400));   // 14か月前 → 消す
    $insert->execute([':id' => $caseId, ':e' => 'admin_viewed', ':r' => 'ok', ':at' => $keep]);
    $insert->execute([':id' => $caseId, ':e' => 'admin_viewed', ':r' => 'ok', ':at' => $drop]);

    assertSame(1, $k->retention->countAuditDue(), '削除対象が1件でない（12か月前まで消そうとしている）');

    $k->retention->purgeAudit();

    $stmt = $k->db->pdo()->prepare(
        'SELECT COUNT(*) FROM intake_audit_events WHERE intake_case_id = :id AND created_at = :at'
    );
    $stmt->execute([':id' => $caseId, ':at' => $keep]);
    assertSame(1, (int)$stmt->fetchColumn(), '12か月前の監査まで消えている');
    $stmt->execute([':id' => $caseId, ':at' => $drop]);
    assertSame(0, (int)$stmt->fetchColumn(), '14か月前の監査が残っている');
});

test('audit: 13か月の境目で残す・消すが分かれる', function (): void {
    $clock = new TestClock();
    $k     = retentionKernel($clock);

    $caseId = $k->cases->create('HP-202608-9270', '架空サロン');
    $cutoff = (int)strtotime($k->retention->auditCutoff());

    // 境目の前後に1件ずつ差し込む。
    // ★境目ちょうどに置かない。auditCutoff() は呼ぶたびに実時刻から計算されるため、
    //   テストの実行中に1秒進むだけで判定が入れ替わってしまう（＝不安定なテストになる）。
    //   前後1時間の余裕を取り、「古い方だけが消える」ことを見る。
    $insert = $k->db->pdo()->prepare(
        'INSERT INTO intake_audit_events (intake_case_id, event_type, result_code, ip_hmac, created_at)
         VALUES (:id, :e, :r, :ip, :at)'
    );
    $older = gmdate('Y-m-d\TH:i:s\Z', $cutoff - 3600);   // 13か月＋1時間前 → 消える
    $newer = gmdate('Y-m-d\TH:i:s\Z', $cutoff + 3600);   // 13か月−1時間前 → 残る
    $insert->execute([':id' => $caseId, ':e' => 'admin_viewed', ':r' => 'ok', ':ip' => null, ':at' => $older]);
    $insert->execute([':id' => $caseId, ':e' => 'admin_viewed', ':r' => 'ok', ':ip' => null, ':at' => $newer]);

    $before = rowCount($k, 'intake_audit_events', $caseId);
    assertSame(1, $k->retention->countAuditDue(), '削除対象が1件でない');

    $result = $k->retention->purgeAudit();

    assertSame(1, $result['deleted'], '削除件数が違う');
    assertSame($before - 1, rowCount($k, 'intake_audit_events', $caseId), '案件の監査が想定と違う');
    assertSame(0, $k->retention->countAuditDue(), '対象が残っている');

    // 新しい方だけが残っている（＝境目の向きが逆になっていない）
    $stmt = $k->db->pdo()->prepare(
        'SELECT COUNT(*) FROM intake_audit_events WHERE intake_case_id = :id AND created_at = :at'
    );
    $stmt->execute([':id' => $caseId, ':at' => $newer]);
    assertSame(1, (int)$stmt->fetchColumn(), '新しい監査まで消えている');
    $stmt->execute([':id' => $caseId, ':at' => $older]);
    assertSame(0, (int)$stmt->fetchColumn(), '古い監査が残っている');
});

test('audit: 13か月より新しい監査は1件も消えない', function (): void {
    $k = retentionKernel();
    [$caseId] = lockedCase($k, 'HP-202608-9271');

    $before = rowCount($k, 'intake_audit_events', $caseId);
    assertTrue($before > 0, '監査が無い');
    assertSame(0, $k->retention->countAuditDue(), '新しい監査が対象になっている');

    $result = $k->retention->purgeAudit();

    assertSame(0, $result['deleted'], '新しい監査が消えた');
    assertSame($before, rowCount($k, 'intake_audit_events', $caseId), '件数が変わっている');
    assertSame(0, $k->audit->countFor(null, 'audit_purged'), '0件なのに監査が増えている');
});

test('audit: 削除自体の監査も13か月後には削除対象になる（保持が循環しない）', function (): void {
    $clock = new TestClock();
    $k     = retentionKernel($clock);

    $k->db->pdo()->prepare(
        'INSERT INTO intake_audit_events (intake_case_id, event_type, result_code, ip_hmac, created_at)
         VALUES (NULL, :e, :r, NULL, :at)'
    )->execute([':e' => 'admin_viewed', ':r' => 'ok', ':at' => '2020-01-01T00:00:00Z']);

    $k->retention->purgeAudit();
    assertSame(1, $k->audit->countFor(null, 'audit_purged'), '削除の監査が残っていない');

    // 14か月進めれば、その監査自身も対象になる
    $clock->advance(430 * 86400);
    assertTrue($k->retention->countAuditDue() >= 1, '削除の監査が保持対象から外れない');

    $k->retention->purgeAudit();
    // 「今回の削除」の監査だけが残る（前回の分は消えている）
    assertSame(1, $k->audit->countFor(null, 'audit_purged'), '削除の監査が積み上がっている');
});

test('audit: 保守画面は件数だけを出す（HMAC化IPも中身も出さない）', function (): void {
    $k = retentionKernel();
    [$caseId] = lockedCase($k, 'HP-202608-9272');
    unset($caseId);

    // HMAC化IP を持つ監査を作る
    $k->app->handle(adminPost('/admin/login', ['admin_id' => 'nobody', 'password' => 'wrong']));

    $stmt = $k->db->pdo()->query('SELECT ip_hmac FROM intake_audit_events WHERE ip_hmac IS NOT NULL LIMIT 1');
    $hmac = (string)$stmt->fetchColumn();
    assertTrue($hmac !== '', 'HMAC化IP が記録されていない（検査が空振り）');

    $login = loginAdmin($k);
    $html  = (string)$k->app->handle(adminGet('/admin/maintenance', ['cookies' => $login['cookie']]))->rawBody;

    assertTrue(str_contains($html, '削除対象'), '件数が出ていない');
    assertTrue(!str_contains($html, $hmac), 'HMAC化IP が画面に出ている');
    foreach ([RET_MARKER, RET_DRIVE_EMAIL, 'ハルカゼ', 'admin_viewed', 'token_issued'] as $banned) {
        assertTrue(!str_contains($html, $banned), '保守画面に出てはいけない値がある: ' . $banned);
    }
});

test('audit: 監査削除もフラグが揃っていなければ実行しない', function (): void {
    $clock = new TestClock();
    $k     = retentionKernel($clock, ['retention_actions_enabled' => false]);

    $k->db->pdo()->prepare(
        'INSERT INTO intake_audit_events (intake_case_id, event_type, result_code, ip_hmac, created_at)
         VALUES (NULL, :e, :r, NULL, :at)'
    )->execute([':e' => 'admin_viewed', ':r' => 'ok', ':at' => '2020-01-01T00:00:00Z']);

    $login = loginAdmin($k);
    $res   = $k->app->handle(adminPost('/admin/maintenance/audit', [
        'csrf_token' => $login['csrf'],
    ], ['cookies' => $login['cookie']]));

    assertSame(403, $res->status, '実行できてしまった');
    assertSame(1, $k->retention->countAuditDue(), '監査が消えている');

    // 画面にも実行ボタンを出さない
    $html = (string)$k->app->handle(adminGet('/admin/maintenance', ['cookies' => $login['cookie']]))->rawBody;
    assertTrue(!str_contains($html, '/admin/maintenance/audit'), '無効なのに実行ボタンがある');
});

/* ==================================================== 管理 session の清掃 */

test('sessions: 期限切れ・失効済みだけを消し、有効な session は残す', function (): void {
    $clock = new TestClock();
    $k     = retentionKernel($clock);

    // 3つ作る: 有効／失効済み／期限切れ
    $active  = loginAdmin($k);
    $revoked = loginAdmin($k);
    $expired = loginAdmin($k);

    $k->app->handle(adminPost('/admin/logout', ['csrf_token' => $revoked['csrf']],
        ['cookies' => $revoked['cookie']]));

    $k->db->pdo()->prepare(
        'UPDATE intake_admin_sessions SET expires_at = :past
          WHERE session_hash = :h'
    )->execute([
        ':past' => '2026-01-01T00:00:00Z',
        ':h'    => hash('sha256', (string)$expired['cookie'][Config::ADMIN_COOKIE_NAME]),
    ]);

    assertSame(2, $k->retention->countAdminSessionsDue(), '対象が2件でない');

    $result = $k->retention->purgeAdminSessions();

    assertSame(2, $result['deleted'], '削除件数が違う');
    assertSame(0, $k->retention->countAdminSessionsDue(), '対象が残っている');

    // 実行した本人は締め出されない
    $res = $k->app->handle(adminGet('/admin/', ['cookies' => $active['cookie']]));
    assertSame(200, $res->status, '有効な session まで消えている');
});

test('sessions: 清掃はログにも監査にも hash を出さない', function (): void {
    $k = retentionKernel();

    $victim = loginAdmin($k);
    $secret = (string)$victim['cookie'][Config::ADMIN_COOKIE_NAME];
    $k->app->handle(adminPost('/admin/logout', ['csrf_token' => $victim['csrf']],
        ['cookies' => $victim['cookie']]));

    $login = loginAdmin($k);
    $res   = $k->app->handle(adminPost('/admin/maintenance/sessions', [
        'csrf_token' => $login['csrf'],
    ], ['cookies' => $login['cookie']]));

    assertSame(200, $res->status, '実行できていない');
    assertTrue(str_contains((string)$res->rawBody, '削除しました'), '結果が出ていない');

    $log = is_file((string)$k->config->logPath) ? (string)file_get_contents((string)$k->config->logPath) : '';
    foreach ([$secret, hash('sha256', $secret)] as $banned) {
        assertTrue(!str_contains($log, $banned), 'ログに session の値が出ている');
        assertTrue(!str_contains((string)$res->rawBody, $banned), '画面に session の値が出ている');
    }

    $stmt = $k->db->pdo()->query("SELECT COUNT(*) FROM intake_audit_events WHERE event_type = 'admin_sessions_purged'");
    assertSame(1, (int)$stmt->fetchColumn(), '監査が1件でない');
});

test('sessions: 清掃はフラグ不要（破壊的でないため）だが認証は必要', function (): void {
    $k = retentionKernel(null, ['retention_actions_enabled' => false, 'backup_policy_confirmed' => false]);

    $res = $k->app->handle(adminPost('/admin/maintenance/sessions', ['csrf_token' => str_repeat('x', 43)]));
    assertSame(303, $res->status, '未認証で実行できてしまった');
    assertSame('/admin/login', $res->headers['Location'] ?? null, 'ログインへ送られていない');

    $login = loginAdmin($k);
    $ok    = $k->app->handle(adminPost('/admin/maintenance/sessions', [
        'csrf_token' => $login['csrf'],
    ], ['cookies' => $login['cookie']]));
    assertSame(200, $ok->status, '認証済みで実行できない');
});

/* ==================================================== ログ・スキーマ */

test('retention: ログに案件番号と結果しか出さない', function (): void {
    $k = retentionKernel();
    [$caseId] = lockedCase($k, 'HP-202608-9280');
    unset($caseId);
    $number = 'HP-202608-9280';
    $login  = loginAdmin($k);

    $detail = $k->app->handle(adminGet('/admin/case', [
        'cookies' => $login['cookie'], 'query' => ['case' => $number],
    ]));
    preg_match('/name="csrf_token" value="([A-Za-z0-9_-]{43})"/', (string)$detail->rawBody, $m);
    $k->app->handle(adminPost('/admin/retention/due', [
        'csrf_token' => $m[1], 'case' => $number, 'due' => '2026-02-02',
    ], ['cookies' => $login['cookie']]));

    $form = $k->app->handle(adminGet('/admin/purge', [
        'cookies' => $login['cookie'], 'query' => ['case' => $number],
    ]));
    preg_match('/name="csrf_token" value="([A-Za-z0-9_-]{43})"/', (string)$form->rawBody, $m2);
    $k->app->handle(adminPost('/admin/purge/send', [
        'csrf_token' => $m2[1], 'case' => $number, 'confirm' => 'DELETE ' . $number,
    ], ['cookies' => $login['cookie']]));

    $log = (string)file_get_contents((string)$k->config->logPath);

    assertTrue(str_contains($log, 'retention_purged'), '削除がログに残っていない');
    assertTrue(str_contains($log, $number), '案件番号がログに無い（検査が空振り）');
    foreach ([
        RET_MARKER, RET_DRIVE_EMAIL, 'drive.google', 'ハルカゼ', '架空県', '03-0000-0000',
        'DELETE ' . $number, '2026-02-02', 'intake_answers', 'csrf', 'session',
    ] as $banned) {
        assertTrue(!str_contains($log, $banned), 'ログに出てはいけない値がある: ' . $banned);
    }
});

test('retention: DB スキーマを変えていない（8表・user_version 4）', function (): void {
    $k = retentionKernel();

    assertSame(4, (int)$k->db->pdo()->query('PRAGMA user_version')->fetchColumn(), 'user_version が変わっている');

    $tables = array_column(
        $k->db->pdo()->query("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'intake_%' ORDER BY name")->fetchAll(),
        'name'
    );
    assertSame([
        'intake_admin_sessions', 'intake_answers', 'intake_audit_events', 'intake_cases',
        'intake_revision_requests', 'intake_sessions', 'intake_submission_history', 'intake_tokens',
    ], $tables, '表の構成が変わっている');
});

test('retention: 削除後に migration を流し直しても復活しない', function (): void {
    $k = retentionKernel();
    [$caseId] = lockedCase($k, 'HP-202608-9281');
    $k->retention->purgeCase($caseId, 'HP-202608-9281', 'DELETE HP-202608-9281');

    (new \SmartLabo\Intake\Migrator($k->db))->migrate();
    (new \SmartLabo\Intake\Migrator($k->db))->migrate();

    assertSame(0, rowCount($k, 'intake_answers', $caseId), 'migration で回答が復活した');
    assertSame('closed', $k->cases->find($caseId)['status'], 'migration で状態が変わった');
    assertSame(4, (int)$k->db->pdo()->query('PRAGMA user_version')->fetchColumn(), 'user_version が変わった');
    assertSame(['integrity' => 'ok', 'foreign_key_violations' => 0], $k->db->integrity(), 'DBが壊れている');
});

test('retention: 削除処理が SQLite 3.26.0 で使えない構文を使っていない', function (): void {
    $code = (string)file_get_contents(__DIR__ . '/../src/Service/RetentionService.php');

    foreach (['VACUUM INTO', 'RETURNING', 'STRICT', 'DROP COLUMN', 'ALTER TABLE', 'json_'] as $banned) {
        assertTrue(!str_contains($code, $banned), '使えない構文がある: ' . $banned);
    }
    // DELETE ... LIMIT は多くのビルドで無効。id を選んでから消していること
    assertTrue(preg_match('/DELETE FROM[^;\']*LIMIT/i', $code) !== 1, 'DELETE ... LIMIT を使っている');
});

test('retention: 消す表がすべてリテラルの DELETE として書かれている', function (): void {
    $code = (string)file_get_contents(__DIR__ . '/../src/Service/RetentionService.php');

    foreach (RetentionService::PURGED_TABLES as $table) {
        assertTrue(
            str_contains($code, 'DELETE FROM ' . $table . ' WHERE intake_case_id = :id'),
            $table . ' のリテラル DELETE が無い（表名を変数で組み立てていないか）'
        );
    }
    // 表名を変数で組み立てていない
    assertTrue(preg_match('/DELETE FROM \' *\. *\$/', $code) !== 1, '表名を変数で組み立てている');
});

test('retention: 自動実行（cron）の経路が無い', function (): void {
    foreach (srcFiles() as $path => $code) {
        // ★コメント（＝「cron を作らない」という規約の説明文）は検査対象にしない。
        //   見るのは**実行されるコード**だけである。
        $body = stripPhpComments($code);
        foreach (['register_shutdown_function', 'cron', 'set_time_limit', 'ignore_user_abort'] as $banned) {
            assertTrue(
                stripos($body, $banned) === false,
                basename($path) . ' に自動実行の痕跡がある: ' . $banned
            );
        }
    }
    // Kernel は起動時に削除を呼ばない
    $kernel = (string)file_get_contents(__DIR__ . '/../src/Kernel.php');
    foreach (['purgeCase', 'purgeAudit', 'purgeAdminSessions'] as $banned) {
        assertTrue(!str_contains($kernel, $banned), 'Kernel が起動時に ' . $banned . ' を呼んでいる');
    }
});
