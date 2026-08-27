/**
 * 提出前の確認表示。
 *
 * ★入力値は必ず textContent で出す。HTML として解釈させない。
 * ★参考サイト等の URL も、https 以外は**リンクにしない**（dom.js の safeLink）。
 */

import { CONFIRMATION_ITEMS, KIND, STEPS, WEEKDAYS } from './schema.js';
import { el, safeLink } from './dom.js';
import { getAt } from './fields.js';

const EMPTY = '未入力';

/** 値を「読める文字列」へ直す。オブジェクトを JSON のまま見せない */
export function displayValue(field, value) {
  if (value === null || value === undefined || value === '') return EMPTY;

  if (field.kind === KIND.CHECKBOX) return value === true ? 'はい' : 'いいえ';

  if (field.kind === KIND.SELECT || field.kind === KIND.CHECKS) {
    const labelOf = (v) => {
      const hit = (field.options || []).find(([ov]) => String(ov) === String(v));

      return hit ? hit[1] : String(v);
    };
    if (Array.isArray(value)) return value.length === 0 ? EMPTY : value.map(labelOf).join('、');

    return labelOf(value);
  }

  if (field.kind === KIND.PARKING) {
    const types = { none: 'なし', own: '専用駐車場あり', nearby: '近隣に提携駐車場あり' };
    const t = types[value.type] || 'なし';

    return value.note ? `${t}（${value.note}）` : t;
  }

  if (field.kind === KIND.WEEKLY) {
    if (!Array.isArray(value) || value.length === 0) return EMPTY;

    return value
      .map((w) => {
        const day = WEEKDAYS[Number(w.day)] || '';

        return w.closed ? `${day}：定休` : `${day}：${w.open || '—'}〜${w.close || '—'}`;
      })
      .join('\n');
  }

  if (field.kind === KIND.CONFIRMATIONS) {
    const agreed = (Array.isArray(value) ? value : []).filter((c) => c && c.agreed === true).length;

    return `${agreed} / ${CONFIRMATION_ITEMS.length} 項目を確認済み`;
  }

  if (Array.isArray(value)) return value.length === 0 ? EMPTY : value.map((v) => String(v)).join('、');

  if (typeof value === 'object') return EMPTY;

  return String(value);
}

function valueNode(field, value) {
  const text = displayValue(field, value);

  // https のリンクだけ、開けるようにする（それ以外は文字のまま）
  if (field.https && typeof value === 'string' && value.startsWith('https://')) {
    return el('span', { class: 'review__val' }, [safeLink(value)]);
  }

  return el('span', {
    class: text === EMPTY ? 'review__val review__val--empty' : 'review__val',
    text,
  });
}

/**
 * 確認画面を組み立てる。
 * @param {(stepIndex:number)=>void} onEdit 「編集する」で戻る
 */
export function renderReview(store, onEdit) {
  const frag = document.createDocumentFragment();

  STEPS.forEach((step, index) => {
    const value = store.section(step.key);
    const body = el('div', { class: 'review__body' });

    if (step.kind === KIND.OBJECTS) {
      const items = Array.isArray(value) ? value : [];
      if (items.length === 0) {
        body.appendChild(el('p', { class: 'review__val review__val--empty', text: '登録がありません' }));
      } else {
        items.forEach((item, i) => {
          body.appendChild(el('p', { class: 'review__title', text: `${step.itemLabel} ${i + 1}` }));
          for (const field of step.fields) {
            body.appendChild(
              el('div', { class: 'review__row' }, [
                el('span', { class: 'review__key', text: field.label }),
                valueNode(field, getAt(item, field.path)),
              ]),
            );
          }
        });
      }
    } else {
      for (const field of step.fields) {
        body.appendChild(
          el('div', { class: 'review__row' }, [
            el('span', { class: 'review__key', text: field.label }),
            valueNode(field, getAt(value || {}, field.path)),
          ]),
        );
      }
    }

    frag.appendChild(
      el('section', { class: 'review__group' }, [
        el('div', { class: 'review__head' }, [
          el('span', { class: 'review__title', text: step.title }),
          el('button', {
            type: 'button',
            class: 'btn btn--outline btn--small',
            text: '編集する',
            'aria-label': `${step.title} を編集する`,
            onClick: () => onEdit(index),
          }),
        ]),
        body,
      ]),
    );
  });

  return frag;
}
