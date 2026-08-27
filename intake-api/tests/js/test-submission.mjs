/**
 * 提出の冪等化キー（SSOT v1.3 §6.4）。
 *
 * 4B-R1 でサーバー側は実装済み。ここで確かめるのは**画面側の生成契約**である。
 *   - 押すたびに新しい値
 *   - 同じ提出処理の通信障害による再試行だけ、同じ値
 *   - 検証エラーを直してからの送り直しは新しい値
 *   - どこにも保存・表示・出力しない
 */

import { assertSame, assertTrue, test, watchStorage } from './bootstrap.mjs';
import { SubmissionAttempt, isUuidV4, newSubmissionId } from '../../public/assets/lib/submission.js';

/** randomUUID が無い環境を作る（getRandomValues だけある） */
function cryptoWithoutRandomUUID() {
  return {
    getRandomValues(arr) {
      for (let i = 0; i < arr.length; i += 1) arr[i] = (i * 37 + 11) % 256;

      return arr;
    },
  };
}

test('提出キー: crypto.randomUUID を第一候補にする', () => {
  let called = 0;
  const c = {
    randomUUID() {
      called += 1;

      return '3f2504e0-4f89-41d3-9a0c-0305e82c3301';
    },
    getRandomValues(a) {
      throw new Error('こちらは呼ばれてはいけない');
    },
  };

  const id = newSubmissionId(c);
  assertSame(1, called, 'randomUUID が使われていない');
  assertTrue(isUuidV4(id), 'UUID v4 になっていない');
});

test('提出キー: randomUUID が無ければ Web Crypto から作る', () => {
  const id = newSubmissionId(cryptoWithoutRandomUUID());

  assertTrue(isUuidV4(id), `UUID v4 になっていない: ${id}`);
  assertSame('4', id[14], 'version が 4 でない');
  assertTrue(['8', '9', 'a', 'b'].includes(id[19]), 'variant が 10xx でない');
});

test('提出キー: 安全な乱数が無い環境では作らない', () => {
  let thrown = false;
  try {
    newSubmissionId({});
  } catch (e) {
    thrown = e.message === 'secure_random_unavailable';
  }
  assertTrue(thrown, '乱数が無くても値を作ってしまっている');
});

test('提出キー: Math.random を使わない', async () => {
  const original = Math.random;
  let used = false;
  Math.random = () => {
    used = true;

    return original();
  };
  try {
    newSubmissionId(cryptoWithoutRandomUUID());
  } finally {
    Math.random = original;
  }
  assertTrue(!used, 'Math.random が使われている');
});

test('提出キー: 毎回ちがう値になる', () => {
  const seen = new Set();
  for (let i = 0; i < 200; i += 1) seen.add(newSubmissionId());
  assertSame(200, seen.size, '値が重複している');
});

/* ------------------------------------------------------------ 再試行の制御 */

test('提出: 押すたびに新しい値になる', () => {
  const a = new SubmissionAttempt();

  a.startNew();
  const first = a.next();
  a.succeeded();

  a.startNew();
  const second = a.next();

  assertTrue(isUuidV4(first) && isUuidV4(second));
  assertTrue(first !== second, '押し直しで同じ値が使われている');
});

test('提出: 応答が届かなかったときは同じ値で再試行する', () => {
  const a = new SubmissionAttempt();

  a.startNew();
  const first = a.next();

  // 通信断。受理されたかどうか分からない
  a.keepForRetry();

  const retry = a.next();
  assertSame(first, retry, '再試行で値が変わっている（履歴が重複しうる）');
});

test('提出: 検証エラーのあとは新しい値になる', () => {
  const a = new SubmissionAttempt();

  a.startNew();
  const first = a.next();

  // サーバーが「必須項目が足りない」と答えた
  a.rejectedByValidation();

  // 利用者が直して、もう一度押した
  a.startNew();
  const second = a.next();

  assertTrue(first !== second, '検証エラー後に同じ値を送っている');
  assertTrue(!a.hasPending || second === a.next(), '保持の状態が壊れている');
});

test('提出: 成立したら値を捨てる', () => {
  const a = new SubmissionAttempt();

  a.startNew();
  a.next();
  a.succeeded();

  assertTrue(!a.hasPending, '提出後も値を持っている');
});

test('提出: すでに提出済み（409）なら値を捨てる', () => {
  const a = new SubmissionAttempt();

  a.startNew();
  a.next();
  a.alreadySubmitted();

  assertTrue(!a.hasPending, '409 のあとも値を持っている');
});

test('提出: 429 のあと押し直すと同じ値になる（サーバーは受理していない）', () => {
  const a = new SubmissionAttempt();

  a.startNew();
  const first = a.next();
  a.rateLimited();

  assertSame(first, a.next(), '429 の押し直しで値が変わっている');
});

test('提出: 再読み込み後に古い値を復元しない', () => {
  const first = (() => {
    const a = new SubmissionAttempt();
    a.startNew();
    const id = a.next();
    a.keepForRetry();

    return id;
  })();

  // ページを読み込み直した ＝ 新しいインスタンス。メモリの外に何も残していない
  const fresh = new SubmissionAttempt();
  const after = fresh.next();

  assertTrue(first !== after, '再読み込み後に古い値が復元されている');
});

test('提出: 値を保存領域へ書かない', () => {
  const watcher = watchStorage();
  const originalLocal = globalThis.localStorage;
  const originalSession = globalThis.sessionStorage;
  Object.defineProperty(globalThis, 'localStorage', { value: watcher.localStorage, configurable: true });
  Object.defineProperty(globalThis, 'sessionStorage', { value: watcher.sessionStorage, configurable: true });

  let id;
  try {
    const a = new SubmissionAttempt();
    a.startNew();
    id = a.next();
    a.keepForRetry();
    a.succeeded();
  } finally {
    if (originalLocal === undefined) delete globalThis.localStorage;
    else Object.defineProperty(globalThis, 'localStorage', { value: originalLocal, configurable: true });
    if (originalSession === undefined) delete globalThis.sessionStorage;
    else Object.defineProperty(globalThis, 'sessionStorage', { value: originalSession, configurable: true });
  }

  assertTrue(watcher.isClean, '保存領域へ書き込まれている');
  assertTrue(!watcher.contains(id), '保存領域へ提出キーが入っている');
});

test('提出: 値を console へ出さない', () => {
  const seen = [];
  const original = { log: console.log, info: console.info, warn: console.warn, error: console.error, debug: console.debug };
  for (const name of Object.keys(original)) console[name] = (...args) => seen.push(args.map(String).join(' '));

  let id;
  try {
    const a = new SubmissionAttempt();
    a.startNew();
    id = a.next();
    a.rejectedByValidation();
  } finally {
    for (const [name, fn] of Object.entries(original)) console[name] = fn;
  }

  assertSame(0, seen.length, `console へ出力している: ${seen.join(' / ')}`);
  assertTrue(isUuidV4(id));
});

test('提出: 生成関数を差し替えても契約は変わらない', () => {
  let n = 0;
  const ids = ['11111111-1111-4111-8111-111111111111', '22222222-2222-4222-8222-222222222222'];
  const a = new SubmissionAttempt(() => ids[n++]);

  a.startNew();
  assertSame(ids[0], a.next());
  assertSame(ids[0], a.next(), '同じ操作の中で値が変わっている');

  a.rejectedByValidation();
  a.startNew();
  assertSame(ids[1], a.next(), '新しい提出で値が変わっていない');
});
