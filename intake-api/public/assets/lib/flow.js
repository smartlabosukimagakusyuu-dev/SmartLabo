/**
 * 応答に対して「何をするか」の判断（SSOT v1.3 §6.4 / §6.5 / §5.2）。
 *
 * ★画面の描画から切り離してある。ここが本当の分岐であり、テストの対象でもある。
 * ★**自動で送り直す判断をここに置かない**。再試行は必ず利用者の操作から始める。
 */

import { OUTCOME } from './api.js';

export const SAVE_ACTION = {
  SAVED: 'saved',
  OFFLINE: 'offline',
  CONFLICT: 'conflict',
  RATE_LIMITED: 'rate_limited',
  EXPIRED: 'expired',
  ERROR: 'error',
};

export const SUBMIT_ACTION = {
  SUBMITTED: 'submitted',
  INCOMPLETE: 'incomplete',
  UNKNOWN: 'unknown', // 受理されたか分からない（通信断）
  ALREADY: 'already_submitted',
  RATE_LIMITED: 'rate_limited',
  EXPIRED: 'expired',
  ERROR: 'error',
};

export const SCREEN = {
  FORM: 'form',
  SUBMITTED: 'submitted',
  NOT_EDITABLE: 'not_editable',
};

/**
 * 途中保存の応答をどう扱うか。
 * @returns {{action:string, autoRetry:boolean, overwriteServer:boolean}}
 */
export function decideSave(result) {
  // ★どの経路でも自動再試行しない（多重保存を防ぐ。SSOT §6.5）
  const base = { autoRetry: false, overwriteServer: false };

  if (result.outcome === OUTCOME.OK) {
    return { ...base, action: SAVE_ACTION.SAVED };
  }
  if (result.outcome === OUTCOME.OFFLINE) {
    return { ...base, action: SAVE_ACTION.OFFLINE };
  }
  if (result.status === 409) {
    // ★利用者の入力でサーバーを上書きしない。自動マージもしない
    return { ...base, action: SAVE_ACTION.CONFLICT };
  }
  if (result.status === 429) {
    return { ...base, action: SAVE_ACTION.RATE_LIMITED };
  }
  if (result.status === 404) {
    return { ...base, action: SAVE_ACTION.EXPIRED };
  }

  return { ...base, action: SAVE_ACTION.ERROR };
}

/**
 * 提出の応答をどう扱うか。
 *
 * `keepSubmissionId` は「同じ値で押し直してよいか」を表す。
 *   true  … サーバーが受理したか分からない／受理していない（同じ値で再試行してよい）
 *   false … 決着がついた（次は新しい値を作る）
 *
 * @returns {{action:string, keepSubmissionId:boolean, autoResend:boolean}}
 */
export function decideSubmit(result) {
  // ★どの経路でも自動再送しない
  if (result.outcome === OUTCOME.OFFLINE) {
    // 受理されたかどうか分からない。同じ値で押し直せば二重にならない
    return { action: SUBMIT_ACTION.UNKNOWN, keepSubmissionId: true, autoResend: false };
  }

  if (result.outcome === OUTCOME.OK) {
    if (result.body && result.body.submitted === true) {
      return { action: SUBMIT_ACTION.SUBMITTED, keepSubmissionId: false, autoResend: false };
    }

    // 必須項目が足りない。直してからの送り直しは**新しい提出要求**
    return { action: SUBMIT_ACTION.INCOMPLETE, keepSubmissionId: false, autoResend: false };
  }

  if (result.status === 429) {
    // サーバーは受理していない。押し直しは同じ値でよい
    return { action: SUBMIT_ACTION.RATE_LIMITED, keepSubmissionId: true, autoResend: false };
  }
  if (result.status === 409) {
    return { action: SUBMIT_ACTION.ALREADY, keepSubmissionId: false, autoResend: false };
  }
  if (result.status === 404) {
    return { action: SUBMIT_ACTION.EXPIRED, keepSubmissionId: false, autoResend: false };
  }

  return { action: SUBMIT_ACTION.ERROR, keepSubmissionId: false, autoResend: false };
}

/** 案件の状態から、最初に見せる画面を決める（SSOT §5.2） */
export function decideScreen(status) {
  if (status === 'submitted' || status === 'reviewed') {
    return SCREEN.SUBMITTED;
  }
  if (status === 'draft' || status === 'needs_revision') {
    return SCREEN.FORM;
  }

  // locked / closed / 未知の状態はすべて編集不可（判断を漏らさない）
  return SCREEN.NOT_EDITABLE;
}
