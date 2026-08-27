/**
 * 応答に対する判断（SSOT v1.3 §5.2 / §6.4 / §6.5）。
 *
 * ここが「自動で送り直さない」「自動で上書きしない」を守っている場所である。
 */

import { assertSame, assertTrue, test } from './bootstrap.mjs';
import { OUTCOME } from '../../public/assets/lib/api.js';
import {
  SAVE_ACTION,
  SCREEN,
  SUBMIT_ACTION,
  decideSave,
  decideScreen,
  decideSubmit,
} from '../../public/assets/lib/flow.js';

const ok = (body = {}) => ({ outcome: OUTCOME.OK, status: 200, body: { ok: true, ...body } });
const failed = (status, error = '') => ({ outcome: OUTCOME.FAILED, status, error, body: { ok: false, error } });
const offline = () => ({ outcome: OUTCOME.OFFLINE, status: 0, error: 'offline', body: {} });

/* ------------------------------------------------------------ 途中保存 */

test('保存の判断: 成功', () => {
  assertSame(SAVE_ACTION.SAVED, decideSave(ok({ version: 2 })).action);
});

test('保存の判断: 409 は上書きせず、自動再試行もしない', () => {
  const d = decideSave(failed(409, 'conflict'));

  assertSame(SAVE_ACTION.CONFLICT, d.action);
  assertSame(false, d.overwriteServer, '利用者の入力でサーバーを上書きしようとしている');
  assertSame(false, d.autoRetry, '409 で自動再試行している');
});

test('保存の判断: 429 では自動再試行しない', () => {
  const d = decideSave(failed(429, 'rate_limited'));

  assertSame(SAVE_ACTION.RATE_LIMITED, d.action);
  assertSame(false, d.autoRetry, '429 で自動再試行している');
});

test('保存の判断: 通信断でも自動再試行しない', () => {
  const d = decideSave(offline());

  assertSame(SAVE_ACTION.OFFLINE, d.action);
  assertSame(false, d.autoRetry, '通信断で自動再試行している');
});

test('保存の判断: 404 は入口の案内へ戻す', () => {
  assertSame(SAVE_ACTION.EXPIRED, decideSave(failed(404, 'unavailable')).action);
});

test('保存の判断: どの失敗でも自動再試行・自動上書きをしない', () => {
  const cases = [offline(), failed(400), failed(403), failed(404), failed(409), failed(413), failed(429), failed(500)];

  for (const c of cases) {
    const d = decideSave(c);
    assertSame(false, d.autoRetry, `自動再試行している: ${c.status}`);
    assertSame(false, d.overwriteServer, `自動上書きしている: ${c.status}`);
  }
});

/* ------------------------------------------------------------ 提出 */

test('提出の判断: 成立したら値を捨てる', () => {
  const d = decideSubmit(ok({ submitted: true, already_submitted: false }));

  assertSame(SUBMIT_ACTION.SUBMITTED, d.action);
  assertSame(false, d.keepSubmissionId, '成立後も同じ値を持ち続けている');
});

test('提出の判断: 同一キーの再送で成立扱いになる場合も、値を捨てる', () => {
  const d = decideSubmit(ok({ submitted: true, already_submitted: true }));

  assertSame(SUBMIT_ACTION.SUBMITTED, d.action);
  assertSame(false, d.keepSubmissionId);
});

test('提出の判断: 必須不足のあとは新しい値にする', () => {
  const d = decideSubmit(ok({ submitted: false, missing: ['basic.legal_name'], missing_count: 1 }));

  assertSame(SUBMIT_ACTION.INCOMPLETE, d.action);
  assertSame(false, d.keepSubmissionId, '検証エラー後も同じ値を使おうとしている');
  assertSame(false, d.autoResend);
});

test('提出の判断: 通信断は「分からない」。同じ値で押し直せる', () => {
  const d = decideSubmit(offline());

  assertSame(SUBMIT_ACTION.UNKNOWN, d.action);
  assertSame(true, d.keepSubmissionId, '通信断で値を捨てている（再送が二重提出になる）');
  assertSame(false, d.autoResend, '自動で送り直している');
});

test('提出の判断: 429 は受理されていない。押し直しは同じ値', () => {
  const d = decideSubmit(failed(429, 'rate_limited'));

  assertSame(SUBMIT_ACTION.RATE_LIMITED, d.action);
  assertSame(true, d.keepSubmissionId);
  assertSame(false, d.autoResend, '429 で自動再送している');
});

test('提出の判断: 409（提出済み）は自動再送しない', () => {
  const d = decideSubmit(failed(409, 'already_submitted'));

  assertSame(SUBMIT_ACTION.ALREADY, d.action);
  assertSame(false, d.autoResend, '409 で自動再送している');
  assertSame(false, d.keepSubmissionId);
});

test('提出の判断: 404 は入口の案内へ戻す', () => {
  assertSame(SUBMIT_ACTION.EXPIRED, decideSubmit(failed(404, 'unavailable')).action);
});

test('提出の判断: どの応答でも自動再送しない', () => {
  const cases = [
    ok({ submitted: true }),
    ok({ submitted: false, missing: [] }),
    offline(),
    failed(400), failed(403), failed(404), failed(409), failed(429), failed(500),
  ];

  for (const c of cases) {
    assertSame(false, decideSubmit(c).autoResend, `自動再送している: ${c.status}`);
  }
});

test('提出の判断: 値を持ち続けてよいのは「受理されていない／分からない」ときだけ', () => {
  // 受理されたかもしれない・されていない → 同じ値で押し直す
  assertSame(true, decideSubmit(offline()).keepSubmissionId);
  assertSame(true, decideSubmit(failed(429)).keepSubmissionId);

  // 決着がついた → 次は新しい値
  assertSame(false, decideSubmit(ok({ submitted: true })).keepSubmissionId);
  assertSame(false, decideSubmit(ok({ submitted: false })).keepSubmissionId);
  assertSame(false, decideSubmit(failed(409)).keepSubmissionId);
  assertSame(false, decideSubmit(failed(400)).keepSubmissionId);
  assertSame(false, decideSubmit(failed(500)).keepSubmissionId);
});

/* ------------------------------------------------------------ 最初の画面 */

test('画面の判断: 入力できる状態', () => {
  assertSame(SCREEN.FORM, decideScreen('draft'));
  assertSame(SCREEN.FORM, decideScreen('needs_revision'));
});

test('画面の判断: 提出済みでは編集画面を出さない', () => {
  assertSame(SCREEN.SUBMITTED, decideScreen('submitted'));
  assertSame(SCREEN.SUBMITTED, decideScreen('reviewed'));
});

test('画面の判断: locked / closed は編集不可の案内', () => {
  assertSame(SCREEN.NOT_EDITABLE, decideScreen('locked'));
  assertSame(SCREEN.NOT_EDITABLE, decideScreen('closed'));
});

test('画面の判断: 知らない状態は編集させない（安全側へ倒す）', () => {
  for (const status of ['', 'unknown', 'published', null, undefined, 'DRAFT']) {
    assertSame(SCREEN.NOT_EDITABLE, decideScreen(status), `編集できてしまう: ${status}`);
  }
});

test('画面の判断: サーバーの6状態をすべて扱える', () => {
  // CaseService::STATUSES と同じ
  const statuses = ['draft', 'submitted', 'needs_revision', 'reviewed', 'locked', 'closed'];
  const seen = statuses.map((s) => decideScreen(s));

  assertSame(6, statuses.length);
  assertTrue(seen.every((s) => Object.values(SCREEN).includes(s)), '扱えない状態がある');
  assertSame(2, seen.filter((s) => s === SCREEN.FORM).length, '編集できる状態は draft / needs_revision だけ');
});
