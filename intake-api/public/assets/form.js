/**
 * 入力画面の本体（SSOT v1.3 §6 / §7 / §10）。
 *
 * 画面の流れ:
 *   読み込み → 11ステップの入力 → 確認 → 提出 → 完了
 *
 * 守ること:
 *   - 回答本文・お名前・ご連絡先を console / localStorage / sessionStorage / URL へ出さない
 *   - 自動保存は「最終変更から30秒」「ステップ移動」「保存ボタン」の3契機だけ
 *   - 保存は直列。409 では上書きしない。429 では自動再試行しない
 *   - 提出のたびに新しい submission_id。再利用は通信障害の再試行だけ
 */

import { ApiClient, OUTCOME } from './lib/api.js';
import { AUTOSAVE_DELAY_MS, AutoSaver, SAVE_REASON } from './lib/autosave.js';
import { SAVE_ACTION, SCREEN, SUBMIT_ACTION, decideSave, decideScreen, decideSubmit } from './lib/flow.js';
import { STEPS } from './lib/schema.js';
import { Store } from './lib/store.js';
import { SubmissionAttempt } from './lib/submission.js';
import { describeAllMissing } from './lib/paths.js';
import { renderStep } from './lib/fields.js';
import { renderReview } from './lib/review.js';
import { clear, el, replace, show } from './lib/dom.js';

const UNAVAILABLE = 'このURLは使用できません。お手数ですが、担当者までご連絡ください。';
const REVIEW_INDEX = STEPS.length; // 12番目 ＝ 確認・提出

const api = new ApiClient();
const store = new Store();
const attempt = new SubmissionAttempt();

const ui = {
  solo: document.getElementById('solo'),
  soloTitle: document.getElementById('solo-title'),
  soloText: document.getElementById('solo-text'),
  soloActions: document.getElementById('solo-actions'),
  page: document.getElementById('page'),
  caseLabel: document.getElementById('case-label'),
  progressStep: document.getElementById('progress-step'),
  progressCount: document.getElementById('progress-count'),
  progressBar: document.getElementById('progress-bar'),
  progressFill: document.getElementById('progress-fill'),
  alerts: document.getElementById('alerts'),
  stepTitle: document.getElementById('step-title'),
  stepLead: document.getElementById('step-lead'),
  stepForm: document.getElementById('step-form'),
  btnPrev: document.getElementById('btn-prev'),
  btnNext: document.getElementById('btn-next'),
  btnSave: document.getElementById('btn-save'),
  btnEnd: document.getElementById('btn-end'),
  saveState: document.getElementById('savestate'),
};

let stepIndex = 0;
let lastSavedAt = null;
let submitting = false;
let finished = false;

/* ------------------------------------------------------------ 画面切り替え */

function showSolo(title, text, actions = []) {
  ui.soloTitle.textContent = title;
  ui.soloText.textContent = text;
  if (actions.length > 0) {
    replace(ui.soloActions, actions);
    show(ui.soloActions, true);
  } else {
    clear(ui.soloActions);
    show(ui.soloActions, false);
  }
  show(ui.solo, true);
  show(ui.page, false);
}

function showForm() {
  show(ui.solo, false);
  show(ui.page, true);
}

function goToStartNotice() {
  finished = true;
  showSolo('入力を再開できます', '最初にご案内したリンクを、もう一度開いてください。');
}

/* ------------------------------------------------------------ お知らせ */

function alertBox(kind, title, text, actions = []) {
  return el('div', { class: `notice notice--${kind}` }, [
    el('p', { class: 'notice__title', text: title }),
    text ? el('p', { text }) : null,
    actions.length > 0 ? el('div', { class: 'notice__actions' }, actions) : null,
  ]);
}

function setAlert(node) {
  replace(ui.alerts, node ? [node] : []);
  if (node) {
    node.scrollIntoView({ block: 'nearest' });
  }
}

function clearAlert() {
  clear(ui.alerts);
}

/* ------------------------------------------------------------ 保存の表示 */

function renderSaveState(state) {
  const node = ui.saveState;
  node.className = 'savestate';

  if (state === 'saving') {
    node.textContent = '保存しています…';

    return;
  }
  if (state === 'error') {
    node.className = 'savestate savestate--error';
    node.textContent = '保存できませんでした。入力内容は画面に残っています。';

    return;
  }
  if (store.hasUnsavedChanges) {
    node.className = 'savestate savestate--dirty';
    node.textContent = '未保存の変更があります';

    return;
  }
  if (lastSavedAt) {
    node.className = 'savestate savestate--saved';
    node.textContent = `保存しました（${formatTime(lastSavedAt)}）`;

    return;
  }
  node.textContent = '入力内容は途中保存できます';
}

function formatTime(date) {
  const p = (n) => String(n).padStart(2, '0');

  return `${p(date.getHours())}:${p(date.getMinutes())}`;
}

/* ------------------------------------------------------------ 保存 */

const saver = new AutoSaver(
  async (reason) => performSave(reason),
  {
    delay: AUTOSAVE_DELAY_MS,
    onState: ({ saving }) => renderSaveState(saving ? 'saving' : null),
  },
);

/**
 * 変更された分類だけを送る（SSOT §6.1-3）。
 * ★戻り値は「保存できたか」。失敗しても入力内容は画面から消さない。
 */
async function performSave() {
  if (!store.isEditable || finished) return { ok: false, skipped: true };

  const sections = store.changedPayload();
  const keys = Object.keys(sections);
  if (keys.length === 0) return { ok: true, skipped: true };

  const result = await api.post('/answers/save', { version: store.version, sections });
  const decision = decideSave(result);

  if (decision.action === SAVE_ACTION.SAVED) {
    store.markSaved(keys, result.body.version);
    lastSavedAt = new Date();
    clearAlert();
    renderSaveState(null);

    return { ok: true };
  }

  renderSaveState('error');

  switch (decision.action) {
    case SAVE_ACTION.OFFLINE:
      setAlert(
        alertBox(
          'warn',
          '保存できませんでした',
          '接続を確認してください。入力内容は画面に残っています。',
          [retryButton()],
        ),
      );
      break;

    case SAVE_ACTION.CONFLICT:
      handleConflict();
      break;

    case SAVE_ACTION.RATE_LIMITED:
      // ★自動で送り直さない（多重保存を防ぐ）
      setAlert(alertBox('warn', '少し時間をおいてください', rateLimitText(result), [retryButton()]));
      break;

    case SAVE_ACTION.EXPIRED:
      goToStartNotice();
      break;

    default:
      setAlert(alertBox('error', '保存できませんでした', result.message, [retryButton()]));
  }

  return { ok: false };
}

function rateLimitText(result) {
  const base = '短時間に操作が集中しました。';
  if (typeof result.retryAfter === 'number' && result.retryAfter > 0) {
    return `${base}${Math.ceil(result.retryAfter / 60)}分ほどおいてから、もう一度お試しください。`;
  }

  return `${base}しばらくおいてから、もう一度お試しください。`;
}

function retryButton() {
  return el('button', {
    type: 'button',
    class: 'btn btn--outline btn--small',
    text: 'もう一度保存する',
    onClick: () => void saver.run(SAVE_REASON.MANUAL),
  });
}

/**
 * 409（別の画面で更新された）。
 * ★利用者の入力でサーバーを上書きしない。自動でマージもしない。選ばせる。
 */
function handleConflict() {
  renderSaveState('error');
  setAlert(
    alertBox(
      'warn',
      '別の画面で更新された可能性があります',
      '同じご案内リンクを別の端末やタブで開くと、この状態になります。'
        + 'どちらの内容を残すかをお選びください。入力中の内容は画面に残しています。',
      [
        el('button', {
          type: 'button',
          class: 'btn btn--primary btn--small',
          text: '保存済みの内容を読み込む',
          onClick: () => void reloadFromServer(),
        }),
        el('button', {
          type: 'button',
          class: 'btn btn--outline btn--small',
          text: 'このまま入力内容を確認する',
          onClick: () => clearAlert(),
        }),
      ],
    ),
  );
}

async function reloadFromServer() {
  const result = await api.get('/case');
  if (result.outcome !== OUTCOME.OK) {
    setAlert(alertBox('error', '読み込めませんでした', result.message || '', [retryButton()]));

    return;
  }
  store.load(result.body);
  lastSavedAt = null;
  clearAlert();
  renderStepView();
}

/* ------------------------------------------------------------ ステップ */

function renderProgress() {
  const total = REVIEW_INDEX + 1;
  const current = stepIndex + 1;
  const title = stepIndex === REVIEW_INDEX ? '入力内容の確認・提出' : STEPS[stepIndex].title;

  ui.progressStep.textContent = title;
  ui.progressCount.textContent = `${current} / ${total} ステップ`;

  // ★幅は CSS（data-step）で決める。style 属性を書くと CSP に弾かれる
  ui.progressFill.dataset.step = String(current);
  ui.progressBar.setAttribute('aria-valuemax', String(total));
  ui.progressBar.setAttribute('aria-valuenow', String(current));
  ui.progressBar.setAttribute('aria-valuetext', `${title}（${current} / ${total}）`);
}

function renderStepView() {
  renderProgress();
  clear(ui.stepForm);

  if (stepIndex === REVIEW_INDEX) {
    renderReviewView();
  } else {
    const step = STEPS[stepIndex];
    ui.stepTitle.textContent = step.title;
    ui.stepLead.textContent = step.lead;

    ui.stepForm.appendChild(
      renderStep(step, store.section(step.key), (next) => {
        store.setSection(step.key, next);
        renderSaveState(null);
        saver.touch(); // 最終変更から30秒
      }),
    );

    if (step.key === 'image_metadata') {
      ui.stepForm.appendChild(driveGuide());
    }

    ui.btnNext.textContent = '次へ';
  }

  ui.btnPrev.disabled = stepIndex === 0;
  renderSaveState(null);
  window.scrollTo({ top: 0, behavior: 'auto' });
}

function renderReviewView() {
  ui.stepTitle.textContent = '入力内容の確認・提出';
  ui.stepLead.textContent = '内容をご確認のうえ、いちばん下のボタンから提出してください。';

  ui.stepForm.appendChild(
    renderReview(store, (index) => {
      stepIndex = index;
      renderStepView();
    }),
  );

  ui.stepForm.appendChild(
    el('div', { class: 'notice notice--info' }, [
      el('p', { class: 'notice__title', text: '提出前にご確認ください' }),
      el('p', {
        text:
          '提出すると、内容の確認を開始します。修正が必要な場合は、担当者から再入力のご案内をいたします。',
      }),
    ]),
  );

  const submitBtn = el('button', {
    type: 'button',
    class: 'btn btn--primary',
    id: 'btn-submit',
    text: 'この内容で提出する',
    onClick: () => void doSubmit(),
  });
  ui.stepForm.appendChild(el('div', { class: 'stepnav' }, [submitBtn]));

  ui.btnNext.textContent = '提出へ進む';
}

async function goToStep(next) {
  if (next < 0 || next > REVIEW_INDEX) return;

  // ステップ移動は保存の契機（SSOT §6.1-1）
  if (store.isEditable && store.hasUnsavedChanges) {
    await saver.run(SAVE_REASON.STEP);
  }
  stepIndex = next;
  renderStepView();
}

/* ------------------------------------------------------------ 素材の案内 */

/**
 * 共有フォルダの使い方の案内（SSOT §7.1）。
 *
 * ★本工程では Drive へ接続しない。フォルダのURLも受け取らない。
 *   案内できるのは「案件番号のフォルダ」と「決まった4つの入れ物」だけである。
 *   フォルダ名に店舗名・お名前・電話番号・メールアドレスを使わない。
 */
function driveGuide() {
  const folders = [
    ['01_images', '店内・外観・施術風景などの写真'],
    ['02_logo', 'ロゴのデータ'],
    ['03_documents', 'お預かりする書類'],
    ['04_references', '参考にしたい資料'],
  ];

  return el('div', { class: 'notice notice--info' }, [
    el('p', { class: 'notice__title', text: '写真データのお預かりについて' }),
    el('p', {
      text:
        '写真そのものは、担当者からご案内する共有フォルダへお入れください。'
        + 'この画面ではファイル名と権利の確認だけをお願いしています。',
    }),
    el('p', {
      text: store.caseNumber
        ? `いちばん上のフォルダ名は「${store.caseNumber}」です。その中に次の4つが並んでいます。`
        : 'いちばん上のフォルダ名は、担当者からご案内する案件番号です。その中に次の4つが並んでいます。',
    }),
    el(
      'ul',
      { class: 'missing-list' },
      folders.map(([name, note]) => el('li', { text: `${name} … ${note}` })),
    ),
    el('p', {
      class: 'field__hint',
      text: store.driveConfirmed
        ? 'アップロード完了のご連絡を受け付けています。'
        : 'アップロードが終わりましたら、担当者へお知らせください。',
    }),
  ]);
}

/* ------------------------------------------------------------ 提出 */

async function doSubmit(isRetry = false) {
  if (submitting || finished) return;

  const button = document.getElementById('btn-submit');
  submitting = true;
  if (button) button.disabled = true; // ★二重クリック防止

  try {
    // 未保存の変更があれば、先に保存してから提出する
    if (store.isEditable && store.hasUnsavedChanges) {
      const saved = await saver.run(SAVE_REASON.MANUAL);
      if (saved && saved.ok === false) {
        setAlert(
          alertBox('warn', '先に保存が必要です', '保存できていないため、提出を中止しました。'),
        );

        return;
      }
    }

    // ★押すたびに新しい UUID v4。再試行のときだけ同じ値になる
    if (!isRetry) {
      attempt.startNew();
    }
    let submissionId;
    try {
      submissionId = attempt.next();
    } catch {
      setAlert(
        alertBox('error', '提出できません', 'ご利用の環境では提出できません。担当者までご連絡ください。'),
      );

      return;
    }

    const result = await api.post('/submit', { submission_id: submissionId });
    const decision = decideSubmit(result);

    // 同じ値で押し直してよいかは、ここだけで決める
    if (decision.keepSubmissionId) {
      attempt.keepForRetry();
    }

    switch (decision.action) {
      case SUBMIT_ACTION.SUBMITTED:
        attempt.succeeded();
        onSubmitted();

        return;

      case SUBMIT_ACTION.INCOMPLETE:
        // 必須項目が足りない。直してからの送り直しは新しい提出要求になる
        attempt.rejectedByValidation();
        showMissing(result.body.missing || []);

        return;

      case SUBMIT_ACTION.UNKNOWN:
        // 受理されたかどうか分からない。★同じ値で押し直せるよう保持済み
        setAlert(
          alertBox(
            'warn',
            '提出の結果を確認できませんでした',
            '接続を確認してから、もう一度お試しください。すでに受け付けている場合でも、二重にはなりません。',
            [submitAgainButton('もう一度提出する')],
          ),
        );

        return;

      case SUBMIT_ACTION.RATE_LIMITED:
        // ★自動では送り直さない。押し直しは利用者の操作
        setAlert(
          alertBox('warn', '少し時間をおいてください', rateLimitText(result), [
            submitAgainButton('もう一度提出する'),
          ]),
        );

        return;

      case SUBMIT_ACTION.ALREADY:
        // すでに提出済み。★自動で送り直さない
        attempt.alreadySubmitted();
        onSubmitted();

        return;

      case SUBMIT_ACTION.EXPIRED:
        goToStartNotice();

        return;

      default:
        attempt.discarded();
        setAlert(alertBox('error', '提出できませんでした', result.message));
    }
  } finally {
    submitting = false;
    const btn = document.getElementById('btn-submit');
    if (btn && !finished) btn.disabled = false;
  }
}

/** 「もう一度提出する」＝同じ提出処理の再試行（新しい値を作らない） */
function submitAgainButton(label) {
  return el('button', {
    type: 'button',
    class: 'btn btn--primary btn--small',
    text: label,
    onClick: () => void doSubmit(true),
  });
}

function showMissing(paths) {
  const described = describeAllMissing(paths);

  setAlert(
    alertBox(
      'error',
      `入力が必要な項目が ${described.length} 件あります`,
      '次の項目をご入力のうえ、もう一度提出してください。',
      [],
    ),
  );

  const list = el(
    'ul',
    { class: 'missing-list' },
    described.map((m) =>
      el('li', {}, [
        el('button', {
          type: 'button',
          class: 'btn btn--quiet btn--small',
          text: m.label,
          onClick: () => focusMissing(m),
        }),
      ]),
    ),
  );
  ui.alerts.firstChild.appendChild(list);

  // 最初の不足項目へ移動する
  if (described.length > 0) {
    focusMissing(described[0]);
  }
}

function focusMissing(missing) {
  if (missing.stepIndex < 0) return;

  stepIndex = missing.stepIndex;
  renderStepView();

  if (!missing.elementId) return;
  const node = document.getElementById(missing.elementId);
  if (!node) return;

  const holder = node.closest ? node.closest('.field') : null;
  if (holder) holder.classList.add('field--invalid');

  if (typeof node.focus === 'function') {
    node.focus({ preventScroll: false });
  }
  node.scrollIntoView({ block: 'center' });
}

function onSubmitted() {
  finished = true;
  saver.dispose();
  store.setStatus('submitted');
  // ★API が返していない日時等を作らない
  showSolo(
    'ご提出ありがとうございました',
    '入力内容をお預かりしました。内容を確認のうえ、担当者からご連絡いたします。'
      + 'この画面は閉じていただいて構いません。',
  );
}

/* ------------------------------------------------------------ 入力を終了 */

async function doLogout() {
  const warning = store.hasUnsavedChanges
    ? '未保存の変更があります。保存せずに終了すると、その内容は失われます。終了しますか？'
    : '入力を終了します。よろしいですか？';

  if (!window.confirm(warning)) return;

  saver.dispose();
  await api.post('/session/logout', {});

  // 応答にかかわらず、手元の状態は必ず捨てる
  store.clear();
  finished = true;

  showSolo(
    '入力を終了しました',
    '入力内容は保存されています。再開する場合は、最初にご案内したリンクをもう一度開いてください。',
  );

  // 戻る操作でこの画面へ戻らないようにする
  if (window.history && typeof window.history.replaceState === 'function') {
    window.history.replaceState(null, '', '/form');
  }
}

/* ------------------------------------------------------------ 起動 */

function bind() {
  ui.btnPrev.addEventListener('click', () => void goToStep(stepIndex - 1));
  ui.btnNext.addEventListener('click', () => void goToStep(stepIndex + 1));
  ui.btnSave.addEventListener('click', () => void saver.run(SAVE_REASON.MANUAL));
  ui.btnEnd.addEventListener('click', () => void doLogout());

  // 未保存のまま離れようとしたら警告する。
  // ★ここでAPI保存を試みない（離脱時の通信は成功が保証されない）
  window.addEventListener('beforeunload', (event) => {
    if (finished || !store.hasUnsavedChanges) return;
    event.preventDefault();
    event.returnValue = '';
  });

  store.subscribe(() => renderSaveState(null));
}

async function boot() {
  const result = await api.get('/case');

  if (result.outcome === OUTCOME.OFFLINE) {
    showSolo('読み込めませんでした', '接続を確認のうえ、もう一度お試しください。', [
      el('button', {
        type: 'button',
        class: 'btn btn--outline',
        text: 'もう一度試す',
        onClick: () => window.location.reload(),
      }),
    ]);

    return;
  }

  if (result.outcome !== OUTCOME.OK) {
    if (result.status === 404) {
      showSolo('ご案内リンクをもう一度お開きください', UNAVAILABLE);

      return;
    }
    showSolo('読み込めませんでした', result.message);

    return;
  }

  store.load(result.body);

  const screen = decideScreen(store.status);

  if (screen === SCREEN.SUBMITTED) {
    finished = true;
    showSolo(
      'ご提出いただいています',
      'この案件の入力内容はお預かり済みです。修正が必要な場合は、担当者までご連絡ください。',
    );

    return;
  }

  if (screen !== SCREEN.FORM) {
    // locked / closed / 想定外の状態
    finished = true;
    showSolo('現在この内容は編集できません', 'お手数ですが、担当者までご連絡ください。');

    return;
  }

  ui.caseLabel.textContent = store.caseNumber ? `案件番号：${store.caseNumber}` : '';
  bind();
  showForm();
  renderStepView();
}

void boot();
