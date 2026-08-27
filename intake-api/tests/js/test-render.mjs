/**
 * 入力欄の描画・XSS・アクセシビリティの基本属性。
 *
 * ★minidom は innerHTML を実装していない。
 *   画面側が innerHTML を使っていたら、これらのテストは例外で落ちる。
 */

import { assertSame, assertTrue, test } from './bootstrap.mjs';
import { Element, installDom } from './minidom.mjs';
import { CONFIRMATION_ITEMS, KIND, STEPS, STEP_BY_KEY } from '../../public/assets/lib/schema.js';
import { fieldId } from '../../public/assets/lib/paths.js';

const XSS = '<script>alert(1)</script><img src=x onerror=alert(2)>';

/** モジュールは document を参照するので、読み込み前に用意する */
async function withDom(fn) {
  const dom = installDom();
  const fields = await import('../../public/assets/lib/fields.js');
  const review = await import('../../public/assets/lib/review.js');
  const dom2 = await import('../../public/assets/lib/dom.js');

  return fn({ ...dom, fields, review, dom: dom2 });
}

function renderInto(ctx, step, value, onChange) {
  const host = ctx.document.createElement('form');
  host.appendChild(ctx.fields.renderStep(step, value, onChange || (() => {})));
  ctx.body.appendChild(host);

  return host;
}

/* ------------------------------------------------------------ XSS */

test('描画: 入力値を HTML として解釈しない', async () => {
  await withDom((ctx) => {
    const host = renderInto(ctx, STEP_BY_KEY.basic, { legal_name: XSS });
    const input = ctx.document.getElementById(fieldId('basic', 'legal_name'));

    assertTrue(input !== null, '入力欄が描かれていない');
    assertSame(XSS, input.value, '値がそのまま保持されていない');

    // 要素として解釈されていないこと
    const tags = host.walk().filter((n) => n instanceof Element).map((n) => n.tagName);
    assertTrue(!tags.includes('SCRIPT'), 'script 要素が作られている');
    assertTrue(!tags.includes('IMG'), 'img 要素が作られている');
  });
});

test('確認画面: 入力値を文字として出す（HTML にしない）', async () => {
  await withDom((ctx) => {
    const store = {
      section: (key) => (key === 'basic' ? { legal_name: XSS } : STEP_BY_KEY[key].kind === KIND.OBJECTS ? [] : {}),
    };
    const host = ctx.document.createElement('div');
    host.appendChild(ctx.review.renderReview(store, () => {}));

    const tags = host.walk().filter((n) => n instanceof Element).map((n) => n.tagName);
    assertTrue(!tags.includes('SCRIPT'), 'script 要素が作られている');
    assertTrue(!tags.includes('IMG'), 'img 要素が作られている');
    assertTrue(host.textContent.includes(XSS), '値が文字として表示されていない');
  });
});

test('確認画面: https 以外はリンクにしない', async () => {
  await withDom((ctx) => {
    const bad = ['javascript:alert(1)', 'http://example.invalid/x', 'data:text/html,<b>x</b>', 'vbscript:x'];

    for (const url of bad) {
      const node = ctx.dom.safeLink(url);
      assertTrue(!(node instanceof Element), `リンクにしてはいけない: ${url}`);
      assertSame(url, node.textContent, '文字として出ていない');
    }

    const good = ctx.dom.safeLink('https://example.invalid/x');
    assertTrue(good instanceof Element && good.tagName === 'A', 'https がリンクになっていない');
    assertSame('noopener noreferrer', good.getAttribute('rel'), 'rel が付いていない');
  });
});

/* ------------------------------------------------------------ 11分類 */

test('描画: 11分類すべてを描ける', async () => {
  await withDom((ctx) => {
    assertSame(11, STEPS.length, '分類が11個でない');

    for (const step of STEPS) {
      const value = step.kind === KIND.OBJECTS ? [] : {};
      const host = renderInto(ctx, step, value);
      assertTrue(host.childNodes.length > 0, `${step.key} が描かれていない`);
    }
  });
});

test('描画: 分類名はサーバーが受け付ける11個と一致する', () => {
  // AnswerService / Migrator::ANSWER_SECTIONS と同じ並び
  const server = [
    'basic', 'business_hours', 'menus', 'staff', 'promotion', 'design',
    'web_links', 'contact_form', 'privacy', 'image_metadata', 'rights',
  ];
  assertSame(server.join(','), STEPS.map((s) => s.key).join(','), '分類名がサーバーと違う');
});

test('描画: 入力すると、その分類の値だけが返る', async () => {
  await withDom((ctx) => {
    let received = null;
    renderInto(ctx, STEP_BY_KEY.basic, {}, (next) => { received = next; });

    const input = ctx.document.getElementById(fieldId('basic', 'legal_name'));
    input.value = '架空サロン';
    input.dispatch('input');

    assertTrue(received !== null, '変更が伝わっていない');
    assertSame('架空サロン', received.legal_name);
  });
});

test('描画: 入れ子の項目も正しい場所へ入る', async () => {
  await withDom((ctx) => {
    let received = null;
    renderInto(ctx, STEP_BY_KEY.basic, {}, (next) => { received = next; });

    const input = ctx.document.getElementById(fieldId('basic', 'internal_contact.phone'));
    input.value = '03-0000-0000';
    input.dispatch('input');

    assertSame('03-0000-0000', received.internal_contact.phone, '入れ子に入っていない');
  });
});

test('描画: 繰り返し（メニュー）を追加・削除できる', async () => {
  await withDom((ctx) => {
    let items = [];
    const host = renderInto(ctx, STEP_BY_KEY.menus, [], (next) => { items = next; });

    const addBtn = host.querySelectorAll('BUTTON').find((b) => b.textContent === 'メニューを追加');
    assertTrue(addBtn !== undefined, '追加ボタンが無い');

    addBtn.dispatch('click');
    assertSame(1, items.length, '1件追加されていない');
    assertSame(true, items[0].published, '初期値（掲載する）が入っていない');
    assertSame('fixed', items[0].price_type === undefined ? 'fixed' : items[0].price_type, '');

    const nameInput = ctx.document.getElementById(fieldId('menus', 'name', 0));
    assertTrue(nameInput !== null, '1件目の入力欄が無い');
    nameInput.value = 'カット';
    nameInput.dispatch('input');
    assertSame('カット', items[0].name);

    const delBtn = host.querySelectorAll('BUTTON').find((b) => b.textContent === '削除');
    delBtn.dispatch('click');
    assertSame(0, items.length, '削除されていない');
  });
});

test('描画: 配列の上限を超えて追加できない', async () => {
  await withDom((ctx) => {
    const step = STEP_BY_KEY.promotion;
    const field = step.fields.find((x) => x.path === 'strengths');
    assertSame(3, field.cap, '上限が SSOT と違う');

    let value = { strengths: ['a', 'b', 'c'] };
    const host = renderInto(ctx, step, value, (next) => { value = next; });

    const addBtn = host.querySelectorAll('BUTTON').find((b) => b.textContent === '強みを追加');
    assertSame(true, addBtn.disabled, '上限に達しても追加できてしまう');
  });
});

test('描画: 営業時間は必ず7曜日ぶん作る', async () => {
  await withDom((ctx) => {
    let value = null;
    renderInto(ctx, STEP_BY_KEY.business_hours, {}, (next) => { value = next; });

    const monday = ctx.document.getElementById(`${fieldId('business_hours', 'weekly')}-1-closed`);
    assertTrue(monday !== null, '月曜の定休チェックが無い');
    monday.checked = true;
    monday.dispatch('change');

    assertSame(7, value.weekly.length, '7要素になっていない');
    assertSame(true, value.weekly[1].closed, '月曜が定休になっていない');
    assertSame(null, value.weekly[1].open, '定休日に開店時刻が残っている');
  });
});

test('描画: 定休にすると時刻欄を触れなくする', async () => {
  await withDom((ctx) => {
    renderInto(ctx, STEP_BY_KEY.business_hours, {}, () => {});

    const base = fieldId('business_hours', 'weekly');
    const closed = ctx.document.getElementById(`${base}-2-closed`);
    const open = ctx.document.getElementById(`${base}-2-open`);

    assertSame(false, open.disabled, '最初から触れなくなっている');
    closed.checked = true;
    closed.dispatch('change');
    assertSame(true, open.disabled, '定休にしても時刻欄が触れる');
  });
});

test('描画: 法的確認は13件、同意した時刻も記録する', async () => {
  await withDom((ctx) => {
    assertSame(13, CONFIRMATION_ITEMS.length, '確認項目が13件でない');

    let value = null;
    renderInto(ctx, STEP_BY_KEY.rights, {}, (next) => { value = next; });

    const first = ctx.document.getElementById(`${fieldId('rights', 'confirmations')}-L-01`);
    assertTrue(first !== null, 'L-01 のチェックが無い');

    first.checked = true;
    first.dispatch('change');

    assertSame(13, value.confirmations.length, '13件ぶん返っていない');
    assertSame('L-01', value.confirmations[0].code);
    assertSame(true, value.confirmations[0].agreed);
    assertTrue(typeof value.confirmations[0].agreed_at === 'string', '同意時刻が記録されていない');
    assertSame(false, value.confirmations[1].agreed, '押していない項目まで同意になっている');
  });
});

test('描画: 雰囲気は3つまでしか選べない', async () => {
  await withDom((ctx) => {
    let value = {};
    renderInto(ctx, STEP_BY_KEY.design, {}, (next) => { value = next; });

    const base = fieldId('design', 'tone');
    const codes = STEP_BY_KEY.design.fields.find((f) => f.path === 'tone').options.map(([v]) => v);

    for (let i = 0; i < 4; i += 1) {
      const node = ctx.document.getElementById(`${base}-${codes[i].replace(/[^A-Za-z0-9_]/g, '_')}`);
      node.checked = true;
      node.dispatch('change');
    }

    assertSame(3, value.tone.length, '3つを超えて選べてしまう');
  });
});

/* ------------------------------------------------ アクセシビリティの基本 */

test('アクセシビリティ: label と入力欄が結び付いている', async () => {
  await withDom((ctx) => {
    for (const step of STEPS) {
      if (step.kind === KIND.OBJECTS) continue;
      const host = renderInto(ctx, step, {}, () => {});

      for (const label of host.querySelectorAll('LABEL')) {
        const target = label.getAttribute('for');
        assertTrue(target !== null && target !== '', `${step.key}: for の無い label がある`);
        assertTrue(
          ctx.document.getElementById(target) !== null,
          `${step.key}: label の for が指す要素が無い（${target}）`,
        );
      }
    }
  });
});

test('アクセシビリティ: 必須項目に aria-required が付く', async () => {
  await withDom((ctx) => {
    renderInto(ctx, STEP_BY_KEY.basic, {}, () => {});

    const required = ctx.document.getElementById(fieldId('basic', 'legal_name'));
    assertSame('true', required.getAttribute('aria-required'), '必須が伝わらない');

    const optional = ctx.document.getElementById(fieldId('basic', 'display_name'));
    assertSame(null, optional.getAttribute('aria-required'), '任意に必須が付いている');
  });
});

test('アクセシビリティ: 必須・非掲載を文字でも示す（色だけにしない）', async () => {
  await withDom((ctx) => {
    const host = renderInto(ctx, STEP_BY_KEY.basic, {}, () => {});
    const text = host.textContent;

    assertTrue(text.includes('必須'), '必須が文字で示されていない');
    assertTrue(text.includes('非掲載'), '内部項目が文字で示されていない');
  });
});

test('アクセシビリティ: 補足は aria-describedby で結ぶ', async () => {
  await withDom((ctx) => {
    renderInto(ctx, STEP_BY_KEY.basic, {}, () => {});

    const withHint = ctx.document.getElementById(fieldId('basic', 'display_name'));
    const described = withHint.getAttribute('aria-describedby');
    assertTrue(described !== null, '補足が結び付いていない');
    assertTrue(ctx.document.getElementById(described) !== null, '補足の要素が無い');
  });
});

test('アクセシビリティ: 繰り返しの入力欄にも読み上げ名を付ける', async () => {
  await withDom((ctx) => {
    let value = { payment_methods: ['現金'] };
    renderInto(ctx, STEP_BY_KEY.basic, value, (next) => { value = next; });

    const row = ctx.document.getElementById(`${fieldId('basic', 'payment_methods')}-0`);
    assertTrue(row !== null, '1件目の入力欄が無い');
    assertTrue(
      (row.getAttribute('aria-label') || '').includes('お支払い方法'),
      '読み上げ名が付いていない',
    );
  });
});

test('アクセシビリティ: 自動補完を切る（共有端末での漏えいを避ける）', async () => {
  await withDom((ctx) => {
    renderInto(ctx, STEP_BY_KEY.basic, {}, () => {});

    for (const path of ['legal_name', 'internal_contact.email', 'address']) {
      const node = ctx.document.getElementById(fieldId('basic', path));
      assertSame('off', node.getAttribute('autocomplete'), `${path} の自動補完が切れていない`);
    }
  });
});
