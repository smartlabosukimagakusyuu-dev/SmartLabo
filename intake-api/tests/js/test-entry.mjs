/**
 * ご案内リンクからの入口（SSOT v1.3 §4.2 / §4.7）。
 *
 * 一番の関心事は「# より後ろの文字列が、どこにも残らないこと」である。
 */

import { assertSame, assertTrue, makeWindow, test, watchStorage } from './bootstrap.mjs';
import { clearFragment, isWellFormedKey, takeKeyFromFragment } from '../../public/assets/lib/entry.js';

const KEY = 'abcdefghijklmnopqrstuvwxyz0123456789ABCDEF_-'.slice(0, 43);

test('入口: 43文字を読み取れる', () => {
  const win = makeWindow(`http://127.0.0.1:8788/start#${KEY}`);
  assertSame(KEY, takeKeyFromFragment(win));
});

test('入口: 読み取った直後に URL から消える', () => {
  const win = makeWindow(`http://127.0.0.1:8788/start#${KEY}`);
  takeKeyFromFragment(win);

  assertSame('', win.location.hash, 'URL に # が残っている');
  assertSame('/start', win.location.pathname, 'path が変わっている');
  assertSame('', win.location.search, 'query へ移されている');
  assertSame(1, win.replaced.length, 'replaceState が呼ばれていない');
  assertTrue(!win.replaced[0].includes(KEY), '書き換え後の URL に値が入っている');
});

test('入口: 形式が違っても URL からは必ず消える', () => {
  for (const bad of ['short', '', 'x'.repeat(44), 'x'.repeat(42), 'has space here padding padding padding pad']) {
    const win = makeWindow(`http://127.0.0.1:8788/start#${encodeURIComponent(bad)}`);
    const got = takeKeyFromFragment(win);

    assertSame(null, got, `不正な値が通った: ${bad}`);
    assertSame('', win.location.hash, `不正な値が URL に残った: ${bad}`);
  }
});

test('入口: # が無いときは null', () => {
  const win = makeWindow('http://127.0.0.1:8788/start');
  assertSame(null, takeKeyFromFragment(win));
});

test('入口: query や path へ移し替えない', () => {
  const win = makeWindow(`http://127.0.0.1:8788/start?a=1#${KEY}`);
  takeKeyFromFragment(win);

  assertSame('?a=1', win.location.search, '元の query が壊れている');
  assertTrue(!win.replaced[0].includes(KEY), 'query へ値が移されている');
});

test('入口: 保存領域へ一切書かない', () => {
  const watcher = watchStorage();
  const win = makeWindow(`http://127.0.0.1:8788/start#${KEY}`);

  const originalLocal = globalThis.localStorage;
  const originalSession = globalThis.sessionStorage;
  Object.defineProperty(globalThis, 'localStorage', { value: watcher.localStorage, configurable: true });
  Object.defineProperty(globalThis, 'sessionStorage', { value: watcher.sessionStorage, configurable: true });

  try {
    takeKeyFromFragment(win);
    assertTrue(watcher.isClean, '保存領域へ書き込まれている');
    assertTrue(!watcher.contains(KEY), '保存領域へ値が入っている');
  } finally {
    if (originalLocal === undefined) delete globalThis.localStorage;
    else Object.defineProperty(globalThis, 'localStorage', { value: originalLocal, configurable: true });
    if (originalSession === undefined) delete globalThis.sessionStorage;
    else Object.defineProperty(globalThis, 'sessionStorage', { value: originalSession, configurable: true });
  }
});

test('入口: console へ出さない', () => {
  const win = makeWindow(`http://127.0.0.1:8788/start#${KEY}`);
  const seen = [];
  const original = { log: console.log, info: console.info, warn: console.warn, error: console.error, debug: console.debug };

  for (const name of Object.keys(original)) {
    console[name] = (...args) => seen.push(args.map(String).join(' '));
  }
  try {
    takeKeyFromFragment(win);
  } finally {
    for (const [name, fn] of Object.entries(original)) console[name] = fn;
  }

  assertSame(0, seen.length, 'console へ出力している');
});

test('入口: 形式の判定は base64url 43文字だけ', () => {
  assertTrue(isWellFormedKey(KEY));
  assertTrue(isWellFormedKey('-'.repeat(43)));
  assertTrue(isWellFormedKey('_'.repeat(43)));
  assertTrue(!isWellFormedKey('='.repeat(43)), 'パディングを許してはいけない');
  assertTrue(!isWellFormedKey('+'.repeat(43)), 'base64 の + を許してはいけない');
  assertTrue(!isWellFormedKey('/'.repeat(43)), 'base64 の / を許してはいけない');
  assertTrue(!isWellFormedKey('a'.repeat(42)));
  assertTrue(!isWellFormedKey('a'.repeat(44)));
  assertTrue(!isWellFormedKey(null));
  assertTrue(!isWellFormedKey(undefined));
  assertTrue(!isWellFormedKey(12345));
});

test('入口: history が使えない環境でも落ちない', () => {
  const win = { location: { hash: `#${KEY}`, pathname: '/start', search: '' } };
  clearFragment(win); // 例外を投げないこと
  assertTrue(true);
});
