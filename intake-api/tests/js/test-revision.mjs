/**
 * 店舗側の修正依頼表示と素材フォルダ案内（SSOT v1.5 §2.8 / §7.2 / §7.3）。
 * HP-ONBOARDING-4D-R1
 */

import { assertDeep, assertSame, assertTrue, test, watchStorage } from './bootstrap.mjs';
import { Store } from '../../public/assets/lib/store.js';
import { describeAllMissing } from '../../public/assets/lib/paths.js';

function loaded(extra = {}) {
  const store = new Store();
  store.load({
    case_number: 'HP-202608-0001',
    status: 'needs_revision',
    version: 3,
    sections: { basic: { legal_name: '架空サロン' } },
    ...extra,
  });

  return store;
}

/* ------------------------------------------------------------ 修正依頼 */

test('修正依頼: 応答の open 依頼を取り込む', () => {
  const store = loaded({
    revision_requests: [
      {
        request_number: 1,
        requested_paths: ['basic.legal_name', 'menus'],
        message: '店名と料金をご確認ください。',
        created_at: '2026-08-27T00:00:00Z',
      },
    ],
  });

  assertTrue(store.hasRevisionRequest, '依頼が取り込まれていない');
  assertSame(1, store.revisionRequests.length);
  assertSame(1, store.revisionRequests[0].request_number);
  assertSame('店名と料金をご確認ください。', store.revisionRequests[0].message);
});

test('修正依頼: 無ければ空として扱う', () => {
  assertTrue(!loaded().hasRevisionRequest, '依頼が無いのに true');
  assertDeep([], loaded().revisionRequests);
  assertTrue(!loaded({ revision_requests: null }).hasRevisionRequest, 'null で落ちている');
  assertTrue(!loaded({ revision_requests: 'x' }).hasRevisionRequest, '文字列で落ちている');
});

test('修正依頼: 対象項目を日本語のラベルへ言い換える', () => {
  const described = describeAllMissing(['basic.legal_name', 'menus', 'rights.confirmations']);

  assertSame(3, described.length);
  assertTrue(described[0].label.includes('店舗の正式名称'), described[0].label);
  assertTrue(described.every((d) => d.stepIndex >= 0), 'ステップが引けていない');

  // パスをそのまま画面へ出さない
  for (const d of described) {
    assertTrue(!d.label.includes('basic.'), 'パスが出ている: ' + d.label);
    assertTrue(!d.label.includes('rights.'), 'パスが出ている: ' + d.label);
  }
});

test('修正依頼: 知らないパスでも安全側へ倒す', () => {
  const described = describeAllMissing(['totally.unknown', 'basic.legal_name']);

  // 未知のものは一般案内になり、パスを画面へ出さない
  const unknown = described.find((d) => d.stepIndex === -1);
  assertTrue(unknown !== undefined, '未知パスが落ちている');
  assertSame('入力内容をご確認ください', unknown.label);
  assertTrue(!unknown.label.includes('totally'), 'パスがそのまま出ている');
});

test('修正依頼: サーバーの正式パスをすべて言い換えられる', async () => {
  // AnswerPaths::ALL と同じ作り方（schema.js から組み立てる）
  const { STEPS } = await import('../../public/assets/lib/schema.js');
  const all = [];
  for (const s of STEPS) {
    all.push(s.key);
    for (const f of s.fields) all.push(`${s.key}.${f.path}`);
  }

  assertSame(129, all.length, '正式パスの件数が想定と違う');

  for (const path of all) {
    const [d] = describeAllMissing([path]);
    assertTrue(d.stepIndex >= 0, `対応するステップが無い: ${path}`);
    assertTrue(d.label !== '入力内容をご確認ください', `言い換えられていない: ${path}`);
    assertTrue(!d.label.includes(path), `パスがそのまま出ている: ${path}`);
  }
});

/* ------------------------------------------------------------ Drive */

test('素材: 応答の案内を取り込む', () => {
  const store = loaded({
    drive: {
      folder_url: 'https://drive.google.com/drive/folders/FAKE',
      folder_label: 'HP-202608-0001 素材',
      shared_email: 'owner@example.invalid',
    },
  });

  assertSame('https://drive.google.com/drive/folders/FAKE', store.drive.folder_url);
  assertSame('owner@example.invalid', store.drive.shared_email);
});

test('素材: 案内が無くても落ちない', () => {
  assertSame(null, loaded().drive.folder_url);
  assertSame(null, loaded({ drive: null }).drive.folder_url);
  assertSame(null, loaded({ drive: 'x' }).drive.shared_email);
});

test('素材: https 以外はリンクにしない', async () => {
  const { installDom } = await import('./minidom.mjs');
  installDom();
  const { safeLink } = await import('../../public/assets/lib/dom.js');
  const { Element } = await import('./minidom.mjs');

  for (const bad of [
    'http://drive.google.com/x',
    'javascript:alert(1)',
    'data:text/html,<b>x</b>',
    'file:///etc/passwd',
  ]) {
    const node = safeLink(bad, '素材フォルダを開く');
    assertTrue(!(node instanceof Element), `リンクにしてはいけない: ${bad}`);
  }

  const good = safeLink('https://drive.google.com/drive/folders/FAKE', '素材フォルダを開く');
  assertTrue(good instanceof Element && good.tagName === 'A', 'https がリンクにならない');
  assertSame('noopener noreferrer', good.getAttribute('rel'), 'rel が付いていない');
  assertSame('_blank', good.getAttribute('target'));
});

/* ------------------------------------------------------------ 保存領域 */

test('素材・修正依頼: 保存領域へ書かない', () => {
  const watcher = watchStorage();
  const originalLocal = globalThis.localStorage;
  const originalSession = globalThis.sessionStorage;
  Object.defineProperty(globalThis, 'localStorage', { value: watcher.localStorage, configurable: true });
  Object.defineProperty(globalThis, 'sessionStorage', { value: watcher.sessionStorage, configurable: true });

  try {
    const store = loaded({
      revision_requests: [{ request_number: 1, requested_paths: ['menus'], message: 'ヒミツ', created_at: 'x' }],
      drive: {
        folder_url: 'https://drive.google.com/drive/folders/FAKE',
        folder_label: 'L',
        shared_email: 'owner@example.invalid',
      },
    });
    store.setSection('basic', { legal_name: 'A' });
    store.clear();

    assertTrue(watcher.isClean, '保存領域へ書き込まれている');
    assertTrue(!watcher.contains('ヒミツ'), '依頼本文が保存されている');
    assertTrue(!watcher.contains('owner@example.invalid'), '共有メールが保存されている');
    assertTrue(!watcher.contains('FAKE'), 'フォルダURLが保存されている');
  } finally {
    if (originalLocal === undefined) delete globalThis.localStorage;
    else Object.defineProperty(globalThis, 'localStorage', { value: originalLocal, configurable: true });
    if (originalSession === undefined) delete globalThis.sessionStorage;
    else Object.defineProperty(globalThis, 'sessionStorage', { value: originalSession, configurable: true });
  }
});

test('素材・修正依頼: 終了時に手元から捨てる', () => {
  const store = loaded({
    revision_requests: [{ request_number: 1, requested_paths: ['menus'], message: 'ヒミツ', created_at: 'x' }],
    drive: {
      folder_url: 'https://drive.google.com/drive/folders/FAKE',
      folder_label: 'L',
      shared_email: 'owner@example.invalid',
    },
  });

  store.clear();

  assertTrue(!store.hasRevisionRequest, '依頼が残っている');
  assertSame(null, store.drive.folder_url, 'フォルダURLが残っている');
  assertSame(null, store.drive.shared_email, '共有メールが残っている');
});
