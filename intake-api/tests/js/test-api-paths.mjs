/**
 * 受付APIの呼び出しと、不足パスの対応付け。
 */

import { assertSame, assertTrue, fakeFetch, test } from './bootstrap.mjs';
import { ApiClient, OUTCOME } from '../../public/assets/lib/api.js';
import { describeAllMissing, describeMissing, fieldId } from '../../public/assets/lib/paths.js';
import { STEPS } from '../../public/assets/lib/schema.js';

/* ------------------------------------------------------------ 呼び出し */

test('通信: Cookie を送る指定を必ず付ける', async () => {
  const fetchImpl = fakeFetch(async () => ({ status: 200, body: { ok: true } }));
  const api = new ApiClient({ fetch: fetchImpl });

  await api.get('/case');
  await api.post('/submit', { submission_id: 'x' });

  for (const call of fetchImpl.calls) {
    assertSame('same-origin', call.init.credentials, 'credentials が付いていない');
    assertSame('no-store', call.init.cache, 'キャッシュ禁止になっていない');
  }
});

test('通信: 通信できなかった場合と、サーバーが答えた場合を区別する', async () => {
  const offline = new ApiClient({ fetch: fakeFetch(async () => new Error('network down')) });
  const answered = new ApiClient({
    fetch: fakeFetch(async () => ({ status: 409, body: { ok: false, error: 'already_submitted', message: '固定文言' } })),
  });

  const a = await offline.post('/submit', {});
  assertSame(OUTCOME.OFFLINE, a.outcome, '通信断が区別されていない');
  assertSame(0, a.status);

  const b = await answered.post('/submit', {});
  assertSame(OUTCOME.FAILED, b.outcome);
  assertSame(409, b.status);
  assertSame('already_submitted', b.error);
  assertSame('固定文言', b.message);
});

test('通信: 応答が JSON でなくても落ちない', async () => {
  const api = new ApiClient({ fetch: fakeFetch(async () => ({ status: 500, invalidJson: true })) });

  const r = await api.get('/case');
  assertSame(OUTCOME.FAILED, r.outcome);
  assertSame('server_error', r.error);
  assertTrue(r.message.length > 0, '固定文言が出ていない');
});

test('通信: 200 でも ok:false なら成功にしない', async () => {
  const api = new ApiClient({ fetch: fakeFetch(async () => ({ status: 200, body: { ok: false, error: 'bad_request' } })) });

  const r = await api.post('/answers/save', {});
  assertSame(OUTCOME.FAILED, r.outcome, 'ok:false を成功として扱っている');
});

test('通信: Retry-After を読む（案内用。自動再試行には使わない）', async () => {
  const api = new ApiClient({
    fetch: fakeFetch(async () => ({ status: 429, headers: { 'Retry-After': '120' }, body: { ok: false, error: 'rate_limited' } })),
  });

  const r = await api.post('/submit', {});
  assertSame(120, r.retryAfter);
});

test('通信: Retry-After が無い・壊れていても落ちない', async () => {
  const none = new ApiClient({ fetch: fakeFetch(async () => ({ status: 429, body: { ok: false } })) });
  const junk = new ApiClient({ fetch: fakeFetch(async () => ({ status: 429, headers: { 'Retry-After': 'あとで' }, body: { ok: false } })) });

  assertSame(null, (await none.post('/submit', {})).retryAfter);
  assertSame(null, (await junk.post('/submit', {})).retryAfter);
});

test('通信: CORS を使う指定をしない', async () => {
  const fetchImpl = fakeFetch(async () => ({ status: 200, body: { ok: true } }));
  const api = new ApiClient({ fetch: fetchImpl });
  await api.get('/case');

  const init = fetchImpl.calls[0].init;
  assertTrue(init.mode !== 'cors', 'CORS を有効にしている');
  assertTrue(init.credentials !== 'include', '他オリジンへ Cookie を送る指定になっている');
});

/* ------------------------------------------------------------ 不足パス */

test('不足パス: 分類 + 項目を日本語のラベルへ言い換える', () => {
  const m = describeMissing('basic.legal_name');

  assertSame('basic', m.sectionKey);
  assertSame(0, m.stepIndex);
  assertSame(fieldId('basic', 'legal_name'), m.elementId);
  assertTrue(m.label.includes('店舗の正式名称'), `ラベルが引けていない: ${m.label}`);
  assertTrue(!m.label.includes('basic.legal_name'), 'パスをそのまま画面へ出している');
});

test('不足パス: 入れ子の項目も引ける', () => {
  const m = describeMissing('basic.internal_contact.phone');

  assertSame(fieldId('basic', 'internal_contact.phone'), m.elementId);
  assertTrue(m.label.includes('ご連絡先の電話番号'), `ラベルが引けていない: ${m.label}`);
});

test('不足パス: 繰り返しの n 件目を指せる', () => {
  const m = describeMissing('menus[2].price_inc_tax');

  assertSame('menus', m.sectionKey);
  assertSame(fieldId('menus', 'price_inc_tax', 2), m.elementId);
  assertTrue(m.label.includes('3件目'), `件目が 1 起算になっていない: ${m.label}`);
  assertTrue(m.label.includes('税込料金'), `ラベルが引けていない: ${m.label}`);
});

test('不足パス: 分類そのもの（1件も無い）を扱える', () => {
  const m = describeMissing('menus');

  assertSame('menus', m.sectionKey);
  assertSame(null, m.elementId);
  assertTrue(m.label.includes('メニュー'), m.label);
});

test('不足パス: 合成パス（写真の枚数）を扱える', () => {
  const m = describeMissing('image_metadata.min_8');

  assertSame('image_metadata', m.sectionKey);
  assertTrue(m.label.includes('8件以上'), `枚数の案内になっていない: ${m.label}`);
  assertTrue(!m.label.includes('min_8'), '内部の書き方が画面へ出ている');
});

test('不足パス: 合成パス（13項目の確認）を扱える', () => {
  const m = describeMissing('rights.confirmations.all_agreed');

  assertSame('rights', m.sectionKey);
  assertSame(fieldId('rights', 'confirmations'), m.elementId);
  assertTrue(m.label.includes('13項目'), m.label);
});

test('不足パス: 見覚えのないパスでも、パスを画面へ出さない', () => {
  const m = describeMissing('totally.unknown.path');

  assertSame(-1, m.stepIndex);
  assertTrue(!m.label.includes('totally'), '未知のパスをそのまま出している');
});

test('不足パス: ステップ順に並べ、重複を消す', () => {
  const list = describeAllMissing([
    'rights.confirmations.all_agreed',
    'basic.legal_name',
    'design.template',
    'basic.legal_name',
  ]);

  assertSame(3, list.length, '重複が消えていない');
  for (let i = 1; i < list.length; i += 1) {
    assertTrue(list[i - 1].stepIndex <= list[i].stepIndex, 'ステップ順に並んでいない');
  }
  assertSame('basic', list[0].sectionKey, '最初の不足が先頭に来ていない');
});

test('不足パス: サーバーの必須22パスをすべて言い換えられる', () => {
  // AnswerService::REQUIRED_PATHS と同じ並び（4B 実装）
  const required = [
    'basic.legal_name', 'basic.operator_name', 'basic.postal_code', 'basic.address',
    'basic.access_text', 'basic.description', 'basic.payment_methods', 'basic.booking_methods',
    'business_hours.weekly', 'business_hours.closed_note',
    'menus', 'promotion.strengths', 'promotion.customer_profile', 'promotion.problems',
    'promotion.recommended_menus', 'promotion.concept',
    'design.template', 'design.tone', 'design.hero_message',
    'web_links.contact_methods',
    'image_metadata', 'rights.confirmations',
  ];
  assertSame(22, required.length, '必須パスの件数が想定と違う');

  for (const path of required) {
    const m = describeMissing(path);
    assertTrue(m.stepIndex >= 0, `対応するステップが無い: ${path}`);
    assertTrue(m.label !== '入力内容をご確認ください', `ラベルへ言い換えられていない: ${path}`);
    assertTrue(!m.label.includes(path), `パスがそのまま出ている: ${path}`);
  }
});

test('不足パス: 入力欄の id は分類ごとに衝突しない', () => {
  const seen = new Set();
  for (const step of STEPS) {
    for (const field of step.fields) {
      const id = fieldId(step.key, field.path);
      assertTrue(!seen.has(id), `id が重複している: ${id}`);
      seen.add(id);
    }
  }
  assertTrue(seen.size > 100, `項目数が少なすぎる: ${seen.size}`);
});
