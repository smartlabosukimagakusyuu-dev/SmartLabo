/**
 * 自動保存と、変更された分類だけを送ること（SSOT v1.3 §6.1 / §6.3）。
 */

import { assertDeep, assertSame, assertTrue, fakeClock, test } from './bootstrap.mjs';
import { AUTOSAVE_DELAY_MS, AutoSaver, SAVE_REASON } from '../../public/assets/lib/autosave.js';
import { Store } from '../../public/assets/lib/store.js';

function makeSaver(save, clock, delay = AUTOSAVE_DELAY_MS) {
  return new AutoSaver(save, {
    delay,
    setTimeout: clock.setTimeout,
    clearTimeout: clock.clearTimeout,
  });
}

/* ------------------------------------------------------------ debounce */

test('自動保存: 最終変更から30秒で走る', async () => {
  const clock = fakeClock();
  let saved = 0;
  const saver = makeSaver(async () => { saved += 1; }, clock);

  saver.touch();
  clock.advance(29999);
  assertSame(0, saved, '30秒より前に保存している');

  clock.advance(1);
  await Promise.resolve();
  assertSame(1, saved, '30秒で保存していない');
});

test('自動保存: 打鍵のたびに数え直す（debounce）', async () => {
  const clock = fakeClock();
  let saved = 0;
  const saver = makeSaver(async () => { saved += 1; }, clock);

  for (let i = 0; i < 10; i += 1) {
    saver.touch();
    clock.advance(5000); // 5秒ごとに入力し続ける
  }
  assertSame(0, saved, '入力中に保存が走っている');

  clock.advance(30000);
  await Promise.resolve();
  assertSame(1, saved, '入力が止まってから1回だけ保存されるべき');
});

test('自動保存: 30秒は確定した運用値', () => {
  assertSame(30000, AUTOSAVE_DELAY_MS, '自動保存の間隔が SSOT と違う');
});

/* ------------------------------------------------------------ 直列化 */

/** 呼ばれるたびに「あとから終わらせられる保存」を作る */
function controllableSave() {
  const pending = [];
  const state = { running: 0, maxRunning: 0, done: 0, reasons: [] };

  const save = async (reason) => {
    state.running += 1;
    state.maxRunning = Math.max(state.maxRunning, state.running);
    state.reasons.push(reason);
    await new Promise((resolve) => pending.push(resolve));
    state.running -= 1;
    state.done += 1;

    return { ok: true, reason };
  };

  /** いま止まっている保存を1つ終わらせる */
  const releaseOne = async () => {
    const next = pending.shift();
    assertTrue(next !== undefined, '終わらせるべき保存が無い');
    next();
    // マイクロタスクを十分に回す
    for (let i = 0; i < 20; i += 1) await Promise.resolve();
  };

  return { save, state, releaseOne, get waiting() { return pending.length; } };
}

test('自動保存: 保存処理を同時並行させない', async () => {
  const clock = fakeClock();
  const c = controllableSave();
  const saver = makeSaver(c.save, clock);

  const first = saver.run(SAVE_REASON.MANUAL);
  await Promise.resolve();
  assertTrue(saver.isSaving, '保存中と判定されていない');

  // 保存中にもう一度要求する
  const second = saver.run(SAVE_REASON.STEP);
  await Promise.resolve();

  assertSame(1, c.state.maxRunning, '保存が同時に2本走っている');
  assertSame(1, c.waiting, '2本目が実際に開始されてしまっている');

  await c.releaseOne(); // 1本目が終わる → 追いかけが始まる
  await first;
  assertSame(1, c.state.maxRunning, '追いかけが並行して走った');

  await c.releaseOne(); // 追いかけが終わる
  await second;

  assertSame(1, c.state.maxRunning, '保存が同時に走った');
  assertSame(2, c.state.done, '追いかけの保存が実行されていない');
});

test('自動保存: 保存中の追加変更は次の保存へ回る（1回にまとまる）', async () => {
  const clock = fakeClock();
  const c = controllableSave();
  const saver = makeSaver(c.save, clock);

  const first = saver.run(SAVE_REASON.MANUAL);
  await Promise.resolve();

  // 保存中に3回要求があっても、追いかけは1回だけ
  const q1 = saver.run(SAVE_REASON.IDLE);
  const q2 = saver.run(SAVE_REASON.IDLE);
  const q3 = saver.run(SAVE_REASON.STEP);
  await Promise.resolve();

  await c.releaseOne();
  await first;
  await c.releaseOne();
  await Promise.all([q1, q2, q3]);

  assertSame(2, c.state.done, `保存回数が想定と違う: ${c.state.reasons.join(',')}`);
  assertSame(0, c.waiting, '余分な保存が残っている');
});

test('自動保存: 待っている側は追いかけの保存の結果を受け取る', async () => {
  const clock = fakeClock();
  const c = controllableSave();
  const saver = makeSaver(c.save, clock);

  const first = saver.run(SAVE_REASON.MANUAL);
  await Promise.resolve();
  const queued = saver.run(SAVE_REASON.STEP);

  await c.releaseOne();
  const firstResult = await first;
  await c.releaseOne();
  const queuedResult = await queued;

  assertSame(SAVE_REASON.MANUAL, firstResult.reason, '1本目の結果が違う');
  assertSame(SAVE_REASON.STEP, queuedResult.reason, '待っている側が古い結果を受け取っている');
});

test('自動保存: 保存を実行すると予約は取り消される', async () => {
  const clock = fakeClock();
  let saved = 0;
  const saver = makeSaver(async () => { saved += 1; }, clock);

  saver.touch();
  assertTrue(saver.isScheduled, '予約されていない');

  await saver.run(SAVE_REASON.MANUAL);
  assertTrue(!saver.isScheduled, '手動保存後も予約が残っている');

  clock.advance(60000);
  await Promise.resolve();
  assertSame(1, saved, '予約が残っていて二重に保存された');
});

test('自動保存: 失敗しても自動で再試行しない', async () => {
  const clock = fakeClock();
  let calls = 0;
  const saver = makeSaver(async () => {
    calls += 1;

    return { ok: false };
  }, clock);

  await saver.run(SAVE_REASON.MANUAL);
  clock.advance(300000); // 5分進める
  await Promise.resolve();

  assertSame(1, calls, '失敗後に自動で送り直している');
});

test('自動保存: 終了時に予約を捨てる（勝手に保存しない）', async () => {
  const clock = fakeClock();
  let saved = 0;
  const saver = makeSaver(async () => { saved += 1; }, clock);

  saver.touch();
  saver.dispose();
  clock.advance(60000);
  await Promise.resolve();

  assertSame(0, saved, '終了後に保存が走っている');
});

/* ------------------------------------------------------------ 変更の追跡 */

function loadedStore() {
  const store = new Store();
  store.load({
    case_number: 'HP-202608-0001',
    status: 'draft',
    version: 3,
    sections: { basic: { legal_name: '架空サロン' }, menus: [] },
  });

  return store;
}

test('保存内容: 変更された分類だけを送る', () => {
  const store = loadedStore();

  store.setSection('design', { template: 'beauty' });

  assertDeep(['design'], store.dirtySections, '変更していない分類まで送ろうとしている');
  assertDeep({ design: { template: 'beauty' } }, store.changedPayload());
});

test('保存内容: 未変更なら空（要求そのものを出さない判断ができる）', () => {
  const store = loadedStore();

  assertSame(0, Object.keys(store.changedPayload()).length);
  assertTrue(!store.hasUnsavedChanges);
});

test('保存内容: 複数の分類を変えたら、その分だけ送る', () => {
  const store = loadedStore();

  store.setSection('basic', { legal_name: 'A' });
  store.setSection('privacy', { purpose: 'B' });

  const payload = store.changedPayload();
  assertDeep(['basic', 'privacy'], Object.keys(payload).sort());
  assertTrue(payload.menus === undefined, '変更していない分類が入っている');
});

test('保存内容: 成功したら version を最新へ更新する', () => {
  const store = loadedStore();

  store.setSection('basic', { legal_name: 'A' });
  assertSame(3, store.version);

  store.markSaved(['basic'], 4);

  assertSame(4, store.version, 'version が更新されていない');
  assertTrue(!store.hasUnsavedChanges, '保存後も未保存のまま');
});

test('保存内容: 送信中に増えた変更は消さない', () => {
  const store = loadedStore();

  store.setSection('basic', { legal_name: 'A' });
  const sending = Object.keys(store.changedPayload());

  // 送信中に別の分類を変更した
  store.setSection('design', { template: 'beauty' });

  store.markSaved(sending, 4);

  assertDeep(['design'], store.dirtySections, '送っていない変更まで保存済みにしている');
  assertTrue(store.hasUnsavedChanges, '未保存の変更が消えている');
});

test('保存内容: 知らない分類名は受け付けない', () => {
  const store = loadedStore();
  let thrown = false;
  try {
    store.setSection('unknown_section', {});
  } catch {
    thrown = true;
  }
  assertTrue(thrown, '未知の分類が通ってしまう');
});

test('保存内容: 終了時にメモリ上の入力内容を捨てる', () => {
  const store = loadedStore();
  store.setSection('basic', { legal_name: '架空サロン ハルカゼ' });

  store.clear();

  assertSame(undefined, store.section('basic'), '入力内容が残っている');
  assertSame('', store.caseNumber, '案件番号が残っている');
  assertTrue(!store.hasUnsavedChanges);
});
