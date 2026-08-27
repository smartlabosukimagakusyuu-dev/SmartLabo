/**
 * サーバーが返す「不足パス」を、画面の入力欄へ対応付ける。
 *
 * ★サーバー（AnswerService::evaluate）が返すのは**パスだけ**であり、値は返らない。
 *   画面側でも、パスを表示するのではなく**その欄の日本語のラベル**へ言い換える。
 *
 * 返りうる形:
 *   basic.legal_name                     … 分類 + 項目
 *   business_hours.weekly                … 分類 + 項目
 *   menus                                … 分類そのもの（1件も無い）
 *   menus[0].price_inc_tax               … 繰り返しの n 件目 + 項目
 *   image_metadata.min_8                 … 合成パス（枚数が足りない）
 *   rights.confirmations.all_agreed      … 合成パス（13件すべてに至っていない）
 */

import { MIN_IMAGES, STEP_BY_KEY, STEPS } from './schema.js';

/** 入力欄の DOM id を組み立てる（画面と対応付けの両方から使う） */
export function fieldId(sectionKey, fieldPath, index) {
  const safe = String(fieldPath).replace(/[^A-Za-z0-9_]/g, '_');

  return index === undefined || index === null
    ? `f-${sectionKey}-${safe}`
    : `f-${sectionKey}-${index}-${safe}`;
}

/**
 * 不足パス1件を、画面で使える形へ変換する。
 * @returns {{sectionKey:string, stepIndex:number, elementId:string|null, label:string}}
 */
export function describeMissing(path) {
  const raw = String(path);

  // 1. 「分類そのもの」（menus / image_metadata）
  const step = STEP_BY_KEY[raw];
  if (step) {
    return {
      sectionKey: raw,
      stepIndex: indexOfStep(raw),
      elementId: null,
      label: `${step.title}（1件以上のご登録が必要です）`,
    };
  }

  // 2. 合成パス
  if (raw === `image_metadata.min_${MIN_IMAGES}`) {
    return {
      sectionKey: 'image_metadata',
      stepIndex: indexOfStep('image_metadata'),
      elementId: null,
      label: `写真・素材の情報（権利の確認が済んだ写真が${MIN_IMAGES}件以上必要です）`,
    };
  }
  if (raw === 'rights.confirmations.all_agreed') {
    return {
      sectionKey: 'rights',
      stepIndex: indexOfStep('rights'),
      elementId: fieldId('rights', 'confirmations'),
      label: '素材の使用・権利の確認（13項目すべてのご確認が必要です）',
    };
  }

  // 3. 繰り返しの n 件目 … menus[0].price_inc_tax
  const indexed = raw.match(/^([a-z_]+)\[(\d+)\]\.(.+)$/);
  if (indexed) {
    const [, sectionKey, idx, fieldPath] = indexed;
    const n = Number.parseInt(idx, 10);
    const target = STEP_BY_KEY[sectionKey];
    const field = target ? target.fields.find((x) => x.path === fieldPath) : null;

    return {
      sectionKey,
      stepIndex: indexOfStep(sectionKey),
      elementId: fieldId(sectionKey, fieldPath, n),
      label: target
        ? `${target.title} ${n + 1}件目の「${field ? field.label : fieldPath}」`
        : raw,
    };
  }

  // 4. 分類 + 項目 … basic.legal_name / basic.internal_contact.phone
  const dot = raw.indexOf('.');
  if (dot > 0) {
    const sectionKey = raw.slice(0, dot);
    const fieldPath = raw.slice(dot + 1);
    const target = STEP_BY_KEY[sectionKey];
    const field = target ? target.fields.find((x) => x.path === fieldPath) : null;
    if (target) {
      return {
        sectionKey,
        stepIndex: indexOfStep(sectionKey),
        elementId: fieldId(sectionKey, fieldPath),
        label: `${target.title}の「${field ? field.label : fieldPath}」`,
      };
    }
  }

  // 5. 見覚えのないパス。パスをそのまま出さず、一般的な案内にする
  return { sectionKey: '', stepIndex: -1, elementId: null, label: '入力内容をご確認ください' };
}

export function indexOfStep(sectionKey) {
  return STEPS.findIndex((s) => s.key === sectionKey);
}

/** 不足パスの一覧を、ステップ順に並べ替えて説明へ変換する */
export function describeAllMissing(paths) {
  const seen = new Set();
  const out = [];
  for (const p of Array.isArray(paths) ? paths : []) {
    const described = describeMissing(p);
    const key = `${described.stepIndex}:${described.elementId || ''}:${described.label}`;
    if (seen.has(key)) continue;
    seen.add(key);
    out.push(described);
  }
  out.sort((a, b) => a.stepIndex - b.stepIndex);

  return out;
}
