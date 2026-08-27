/**
 * 入力欄の描画（SSOT v1.3 §3）。
 *
 * ★利用者の入力を HTML として解釈しない。値は必ず `.value` / `.textContent` で扱う。
 * ★label と input を id / for で必ず結び付ける。
 * ★必須・内部の区別を、色だけでなく**文字**でも示す。
 */

import { CONFIRMATION_ITEMS, KIND, WEEKDAYS } from './schema.js';
import { el, replace } from './dom.js';
import { fieldId } from './paths.js';

/* ------------------------------------------------------------ 値の出し入れ */

export function getAt(obj, path) {
  let cur = obj;
  for (const part of String(path).split('.')) {
    if (cur === null || cur === undefined || typeof cur !== 'object') return undefined;
    cur = cur[part];
  }

  return cur;
}

export function setAt(obj, path, value) {
  const parts = String(path).split('.');
  let cur = obj;
  for (let i = 0; i < parts.length - 1; i += 1) {
    const p = parts[i];
    if (cur[p] === null || cur[p] === undefined || typeof cur[p] !== 'object') {
      cur[p] = {};
    }
    cur = cur[p];
  }
  cur[parts[parts.length - 1]] = value;

  return obj;
}

/** 空欄は「未入力」として保存する（SSOT §3.0-4。undefined を保存しない） */
function normalizeText(raw) {
  return raw === '' ? '' : raw;
}

/* ------------------------------------------------------------ 部品 */

function labelFor(id, field) {
  return el('label', { class: 'field__label', for: id }, [
    field.label,
    field.required ? el('span', { class: 'tag tag--required', text: '必須' }) : null,
    field.internal ? el('span', { class: 'tag tag--internal', text: '非掲載' }) : null,
  ]);
}

function wrap(id, field, control) {
  const hintId = `${id}-hint`;
  const hint = field.hint ? el('p', { class: 'field__hint', id: hintId, text: field.hint }) : null;
  if (hint && control.setAttribute) {
    control.setAttribute('aria-describedby', hintId);
  }

  return el('div', { class: 'field', dataset: { field: id } }, [
    labelFor(id, field),
    hint,
    control,
  ]);
}

/* ------------------------------------------------------------ 各種の入力欄 */

function textControl(id, field, value, onInput) {
  const type = field.inputType || 'text';
  const node = el(field.kind === KIND.TEXTAREA ? 'textarea' : 'input', {
    id,
    name: id,
    class: 'field__control',
    autocomplete: 'off',
    ...(field.kind === KIND.TEXTAREA ? { rows: field.rows || 4 } : { type }),
    ...(field.max && field.kind !== KIND.NUMBER ? { maxlength: field.max } : {}),
    ...(field.placeholder ? { placeholder: field.placeholder } : {}),
    ...(field.required ? { 'aria-required': 'true' } : {}),
  });
  node.value = value === null || value === undefined ? '' : String(value);
  node.addEventListener('input', () => onInput(normalizeText(node.value)));

  return node;
}

function numberControl(id, field, value, onInput) {
  const node = el('input', {
    id,
    name: id,
    type: 'number',
    inputmode: 'numeric',
    class: 'field__control',
    ...(field.min !== undefined ? { min: field.min } : {}),
    ...(field.max !== undefined ? { max: field.max } : {}),
    ...(field.required ? { 'aria-required': 'true' } : {}),
  });
  node.value = value === null || value === undefined ? '' : String(value);
  node.addEventListener('input', () => {
    const raw = node.value.trim();
    if (raw === '') {
      onInput(null);

      return;
    }
    const n = Number.parseInt(raw, 10);
    onInput(Number.isFinite(n) ? n : null);
  });

  return node;
}

function selectControl(id, field, value, onInput) {
  const node = el('select', {
    id,
    name: id,
    class: 'field__control',
    ...(field.required ? { 'aria-required': 'true' } : {}),
  });
  const options = field.options || [];
  const hasBlank = options.some(([v]) => v === '');
  if (!hasBlank && !field.required) {
    node.appendChild(el('option', { value: '', text: '選択しない' }));
  }
  for (const [v, text] of options) {
    node.appendChild(el('option', { value: v, text }));
  }
  node.value = value === null || value === undefined ? '' : String(value);
  node.addEventListener('change', () => onInput(node.value === '' ? null : node.value));

  return node;
}

function checkboxControl(id, field, value, onInput) {
  const input = el('input', { id, name: id, type: 'checkbox' });
  input.checked = value === true;
  input.addEventListener('change', () => onInput(input.checked));

  return el('label', { class: 'checkline', for: id }, [
    input,
    el('span', { class: 'checkline__text' }, [
      field.label,
      field.internal ? el('span', { class: 'tag tag--internal', text: '非掲載' }) : null,
    ]),
  ]);
}

function checksControl(id, field, value, onInput) {
  const chosen = new Set(Array.isArray(value) ? value.map(String) : []);
  const box = el('div', { class: 'checks', id, role: 'group', 'aria-label': field.label });

  for (const [v, text] of field.options || []) {
    const optId = `${id}-${String(v).replace(/[^A-Za-z0-9_]/g, '_')}`;
    const input = el('input', { id: optId, type: 'checkbox', value: v });
    input.checked = chosen.has(String(v));
    input.addEventListener('change', () => {
      if (input.checked) {
        if (field.cap && chosen.size >= field.cap) {
          // 上限を超える選択は受け付けない（切り捨てず、選ばせない）
          input.checked = false;

          return;
        }
        chosen.add(String(v));
      } else {
        chosen.delete(String(v));
      }
      onInput([...chosen]);
    });
    box.appendChild(el('label', { class: 'checkline', for: optId }, [
      input,
      el('span', { class: 'checkline__text', text }),
    ]));
  }

  return box;
}

function listControl(id, field, value, onInput) {
  const items = Array.isArray(value) ? value.map((v) => (v === null || v === undefined ? '' : String(v))) : [];
  const box = el('div', { class: 'rows', id, role: 'group', 'aria-label': field.label });

  const emit = () => onInput(items.filter((v) => v !== ''));

  const draw = () => {
    const rows = items.map((v, i) => {
      const rowId = `${id}-${i}`;
      const input = el('input', {
        type: field.https ? 'url' : 'text',
        id: rowId,
        class: 'field__control',
        'aria-label': `${field.label} ${i + 1}件目`,
        ...(field.itemMax ? { maxlength: field.itemMax } : {}),
        ...(field.placeholder ? { placeholder: field.placeholder } : {}),
      });
      input.value = v;
      input.addEventListener('input', () => {
        items[i] = input.value;
        emit();
      });

      return el('div', { class: 'row' }, [
        input,
        el('button', {
          type: 'button',
          class: 'btn btn--outline btn--small',
          text: '削除',
          'aria-label': `${field.label} ${i + 1}件目を削除`,
          onClick: () => {
            items.splice(i, 1);
            emit();
            draw();
          },
        }),
      ]);
    });

    const canAdd = !field.cap || items.length < field.cap;
    rows.push(
      el('div', {}, [
        el('button', {
          type: 'button',
          class: 'btn btn--outline btn--small',
          text: field.addLabel || '追加',
          disabled: !canAdd,
          onClick: () => {
            items.push('');
            draw();
          },
        }),
        field.cap
          ? el('span', { class: 'field__hint', text: ` ${items.length} / ${field.cap} 件` })
          : null,
      ]),
    );
    replace(box, rows);
  };

  draw();

  return box;
}

function weeklyControl(id, field, value, onInput) {
  const week = [];
  for (let d = 0; d < 7; d += 1) {
    const found = Array.isArray(value) ? value.find((x) => x && Number(x.day) === d) : null;
    week.push({
      day: d,
      closed: found ? found.closed === true : false,
      open: found && found.open ? String(found.open) : '',
      close: found && found.close ? String(found.close) : '',
    });
  }

  const emit = () =>
    onInput(
      week.map((w) => ({
        day: w.day,
        closed: w.closed,
        open: w.closed || w.open === '' ? null : w.open,
        close: w.closed || w.close === '' ? null : w.close,
      })),
    );

  const box = el('div', { class: 'weekly', id, role: 'group', 'aria-label': field.label });

  for (const w of week) {
    const closedId = `${id}-${w.day}-closed`;
    const openId = `${id}-${w.day}-open`;
    const closeId = `${id}-${w.day}-close`;

    const closedInput = el('input', { id: closedId, type: 'checkbox' });
    closedInput.checked = w.closed;

    const openInput = el('input', {
      id: openId,
      type: 'time',
      class: 'weekly__time',
      'aria-label': `${WEEKDAYS[w.day]}曜日の開店時刻`,
    });
    openInput.value = w.open;

    const closeInput = el('input', {
      id: closeId,
      type: 'time',
      class: 'weekly__time',
      'aria-label': `${WEEKDAYS[w.day]}曜日の閉店時刻`,
    });
    closeInput.value = w.close;

    const sync = () => {
      openInput.disabled = closedInput.checked;
      closeInput.disabled = closedInput.checked;
    };
    sync();

    closedInput.addEventListener('change', () => {
      w.closed = closedInput.checked;
      sync();
      emit();
    });
    openInput.addEventListener('input', () => {
      w.open = openInput.value;
      emit();
    });
    closeInput.addEventListener('input', () => {
      w.close = closeInput.value;
      emit();
    });

    box.appendChild(
      el('div', { class: 'weekly__row' }, [
        el('span', { class: 'weekly__day', text: `${WEEKDAYS[w.day]}曜` }),
        el('label', { class: 'weekly__closed', for: closedId }, [closedInput, el('span', { text: '定休' })]),
        openInput,
        el('span', { text: '〜' }),
        closeInput,
      ]),
    );
  }

  return box;
}

function confirmationsControl(id, field, value, onInput) {
  const agreed = new Map();
  for (const row of Array.isArray(value) ? value : []) {
    if (row && typeof row === 'object' && row.code) {
      agreed.set(String(row.code), {
        agreed: row.agreed === true,
        agreed_at: row.agreed_at || null,
      });
    }
  }

  const emit = () =>
    onInput(
      CONFIRMATION_ITEMS.map(([code]) => {
        const cur = agreed.get(code) || { agreed: false, agreed_at: null };

        return { code, agreed: cur.agreed, agreed_at: cur.agreed_at };
      }),
    );

  const box = el('div', { id, role: 'group', 'aria-label': field.label });

  for (const [code, text] of CONFIRMATION_ITEMS) {
    const rowId = `${id}-${code}`;
    const input = el('input', { id: rowId, type: 'checkbox' });
    input.checked = (agreed.get(code) || {}).agreed === true;
    input.addEventListener('change', () => {
      agreed.set(code, {
        agreed: input.checked,
        // 同意した時刻を記録する（証跡。SSOT §3.11）
        agreed_at: input.checked ? new Date().toISOString() : null,
      });
      emit();
    });

    box.appendChild(
      el('label', { class: 'checkline', for: rowId }, [
        input,
        el('span', { class: 'checkline__text' }, [el('span', { class: 'checkline__code', text: code }), text]),
      ]),
    );
  }

  return box;
}

function parkingControl(id, field, value, onInput) {
  const cur = value && typeof value === 'object' ? value : { type: 'none', note: '' };
  const typeId = `${id}-type`;
  const noteId = `${id}-note`;

  const select = el('select', { id: typeId, class: 'field__control', 'aria-label': '駐車場の有無' });
  for (const [v, t] of [
    ['none', 'なし'],
    ['own', '専用駐車場あり'],
    ['nearby', '近隣に提携駐車場あり'],
  ]) {
    select.appendChild(el('option', { value: v, text: t }));
  }
  select.value = cur.type || 'none';

  const note = el('input', {
    id: noteId,
    type: 'text',
    class: 'field__control',
    maxlength: 200,
    placeholder: '台数や場所の補足',
    'aria-label': '駐車場の補足',
  });
  note.value = cur.note || '';

  const emit = () => onInput({ type: select.value, note: note.value });
  select.addEventListener('change', emit);
  note.addEventListener('input', emit);

  return el('div', { id, class: 'rows' }, [select, note]);
}

/* ------------------------------------------------------------ 1項目 */

export function renderField(sectionKey, field, sectionValue, onChange, index) {
  const id = fieldId(sectionKey, field.path, index);
  const value = getAt(sectionValue, field.path);
  const set = (next) => onChange(field.path, next);

  switch (field.kind) {
    case KIND.CHECKBOX:
      return el('div', { class: 'field', dataset: { field: id } }, [checkboxControl(id, field, value, set)]);
    case KIND.NUMBER:
      return wrap(id, field, numberControl(id, field, value, set));
    case KIND.SELECT:
      return wrap(id, field, selectControl(id, field, value, set));
    case KIND.CHECKS:
      return wrap(id, field, checksControl(id, field, value, set));
    case KIND.LIST:
      return wrap(id, field, listControl(id, field, value, set));
    case KIND.WEEKLY:
      return wrap(id, field, weeklyControl(id, field, value, set));
    case KIND.CONFIRMATIONS:
      return wrap(id, field, confirmationsControl(id, field, value, set));
    case KIND.PARKING:
      return wrap(id, field, parkingControl(id, field, value, set));
    default:
      return wrap(id, field, textControl(id, field, value, set));
  }
}

/* ------------------------------------------------------------ 1ステップ */

/**
 * ステップまるごとを描く。
 * @param {object} step schema.js の STEPS の1件
 * @param {object|Array} value その分類の現在値
 * @param {(next:any)=>void} onChange 変更後の分類の値を丸ごと渡す
 */
export function renderStep(step, value, onChange) {
  const frag = document.createDocumentFragment();

  if (step.kind === KIND.OBJECTS) {
    const items = Array.isArray(value) ? value.map((v) => ({ ...v })) : [];
    const box = el('div', { class: 'rows' });

    const draw = () => {
      const groups = items.map((item, i) =>
        el('div', { class: 'group' }, [
          el('div', { class: 'group__head' }, [
            el('span', { class: 'group__title', text: `${step.itemLabel} ${i + 1}` }),
            el('button', {
              type: 'button',
              class: 'btn btn--outline btn--small',
              text: '削除',
              'aria-label': `${step.itemLabel} ${i + 1} を削除`,
              onClick: () => {
                items.splice(i, 1);
                onChange(items.map((x) => ({ ...x })));
                draw();
              },
            }),
          ]),
          ...step.fields.map((field) =>
            renderField(
              step.key,
              field,
              item,
              (path, next) => {
                setAt(item, path, next);
                onChange(items.map((x) => ({ ...x })));
              },
              i,
            ),
          ),
        ]),
      );

      const canAdd = !step.cap || items.length < step.cap;
      groups.push(
        el('div', {}, [
          el('button', {
            type: 'button',
            class: 'btn btn--primary btn--small',
            text: step.addLabel,
            disabled: !canAdd,
            onClick: () => {
              items.push(defaultsFor(step));
              onChange(items.map((x) => ({ ...x })));
              draw();
            },
          }),
          el('span', {
            class: 'field__hint',
            text: ` ${items.length} 件${step.cap ? ` / ${step.cap} 件まで` : ''}`,
          }),
        ]),
      );
      replace(box, groups);
    };

    draw();
    frag.appendChild(box);

    return frag;
  }

  const working = value && typeof value === 'object' && !Array.isArray(value) ? { ...value } : {};
  for (const field of step.fields) {
    frag.appendChild(
      renderField(step.key, field, working, (path, next) => {
        setAt(working, path, next);
        onChange({ ...working });
      }),
    );
  }

  return frag;
}

/** 繰り返し1件の初期値（SSOT §3 の「空値」列） */
export function defaultsFor(step) {
  const out = {};
  for (const field of step.fields) {
    if (field.default !== undefined) {
      setAt(out, field.path, field.default);
    }
  }

  return out;
}
