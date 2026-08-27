/**
 * 回答の正式構造を PHP へ機械生成する（HP-ONBOARDING-4F-R1）。
 *
 *   node intake-api/dev/generate-answer-schema.mjs
 *
 * ★同じ定義を PHP と JavaScript へ**二重に手書きしない**ための道具である。
 *   正は `public/assets/lib/schema.js`（SSOT §3 を写したもの）。
 *   ここから `src/AnswerSchema.php` を作り直す。
 *
 * ★外部ライブラリを入れない。Node の標準機能だけで動く。
 * ★出力先は**生成物**である。手で書き換えない（書き換えても次の生成で消える）。
 * ★実行しても差分が出ないこと（＝生成が冪等であること）をテストで固定している。
 */
import { writeFileSync, readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

import { STEPS, KIND } from '../public/assets/lib/schema.js';

const here = dirname(fileURLToPath(import.meta.url));
const outPath = join(here, '../src/AnswerSchema.php');

/* ------------------------------------------------------------ 種類の対応 */

/**
 * 入力欄の種類 → 保存される JSON の形。
 *
 *  scalar  … 文字列・数値・真偽・null（**配列やオブジェクトは不可**）
 *  bool    … 真偽 または null
 *  list    … scalar だけを並べた配列
 *  object  … 決まったキーだけを持つオブジェクト
 *  objects … object を並べた配列
 */
const KIND_TO_TYPE = {
  [KIND.TEXT]: 'scalar',
  [KIND.TEXTAREA]: 'scalar',
  [KIND.NUMBER]: 'scalar',
  [KIND.SELECT]: 'scalar',
  [KIND.CHECKBOX]: 'bool',
  [KIND.CHECKS]: 'list',
  [KIND.LIST]: 'list',
};

/**
 * 画面部品が自分で形を決めているもの（fields.js の control と対にする）。
 * ★ここを変えるときは fields.js の該当 control も一緒に見ること。
 */
const FIXED_SHAPES = {
  [KIND.WEEKLY]: { type: 'objects', item: ['day', 'closed', 'open', 'close'] },
  [KIND.CONFIRMATIONS]: { type: 'objects', item: ['code', 'agreed', 'agreed_at'] },
  [KIND.PARKING]: { type: 'object', keys: ['type', 'note'] },
};

/* ------------------------------------------------------------ 組み立て */

/** `internal_contact.phone` のような入れ子を作る */
function assign(target, path, node) {
  const parts = path.split('.');
  let cur = target;
  for (let i = 0; i < parts.length - 1; i += 1) {
    const key = parts[i];
    if (!cur[key]) {
      cur[key] = { type: 'object', fields: {} };
    }
    cur = cur[key].fields;
  }
  cur[parts[parts.length - 1]] = node;
}

function nodeFor(field) {
  if (FIXED_SHAPES[field.kind]) {
    const shape = FIXED_SHAPES[field.kind];
    if (shape.type === 'object') {
      return { type: 'object', fields: Object.fromEntries(shape.keys.map((k) => [k, { type: 'scalar' }])) };
    }
    return { type: 'objects', item: Object.fromEntries(shape.item.map((k) => [k, { type: 'scalar' }])) };
  }
  const type = KIND_TO_TYPE[field.kind];
  if (!type) {
    throw new Error(`未対応の種類: ${field.kind}（${field.path}）`);
  }
  return { type };
}

const sections = {};
const paths = [];

for (const step of STEPS) {
  paths.push(step.key);

  const item = {};
  for (const field of step.fields) {
    paths.push(`${step.key}.${field.path}`);
    assign(item, field.path, nodeFor(field));
  }

  sections[step.key] =
    step.kind === KIND.OBJECTS
      ? { type: 'objects', item }
      : { type: 'object', fields: item };
}

/* ------------------------------------------------------------ PHP へ */

function phpString(s) {
  return `'${String(s).replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
}

function phpArray(value, indent) {
  const pad = ' '.repeat(indent);
  const inner = ' '.repeat(indent + 4);
  const entries = Object.entries(value);
  if (entries.length === 0) {
    return '[]';
  }
  const body = entries
    .map(([k, v]) => {
      const rendered = typeof v === 'object' && v !== null ? phpArray(v, indent + 4) : phpString(v);
      return `${inner}${phpString(k)} => ${rendered},`;
    })
    .join('\n');

  return `[\n${body}\n${pad}]`;
}

function phpList(items, indent, perLine = 4) {
  const inner = ' '.repeat(indent + 4);
  const lines = [];
  for (let i = 0; i < items.length; i += perLine) {
    lines.push(inner + items.slice(i, i + perLine).map(phpString).join(', ') + ',');
  }

  return `[\n${lines.join('\n')}\n${' '.repeat(indent)}]`;
}

const php = `<?php
/**
 * HP Intake API — 回答 JSON の正式構造（**生成物**）。
 *
 * ★このファイルは \`dev/generate-answer-schema.mjs\` が
 *   \`public/assets/lib/schema.js\` から機械生成している。**手で書き換えない。**
 *   §3 を変えるときは schema.js を直してから作り直す:
 *
 *     node intake-api/dev/generate-answer-schema.mjs
 *
 * ★同じ定義を PHP と JavaScript へ二重に手書きしないための仕組みである。
 *   生成し直しても差分が出ないこと（冪等であること）をテストで固定している。
 *
 * 形の種類（SSOT v1.8 §3.0-9）:
 *   scalar  … 文字列・数値・真偽・null。**配列やオブジェクトは受け付けない**
 *   bool    … 真偽 または null
 *   list    … scalar だけを並べた配列
 *   object  … 決まったキーだけを持つオブジェクト
 *   objects … object を並べた配列
 */
declare(strict_types=1);

namespace SmartLabo\\Intake;

final class AnswerSchema
{
    /** 分類名（intake_answers の JSON 列と1対1） */
    public const SECTIONS = ${phpList(STEPS.map((s) => s.key), 4, 3)};

    /** §3 の正式なデータパス（分類そのもの ＋ 分類.項目） */
    public const PATHS = ${phpList(paths, 4, 3)};

    /**
     * 分類ごとの構造。保存要求の検査と、読み出し時の絞り込みに使う。
     * @var array<string,array<string,mixed>>
     */
    public const STRUCTURE = ${phpArray(sections, 4)};
}
`;

const previous = (() => {
  try {
    return readFileSync(outPath, 'utf8');
  } catch {
    return null;
  }
})();

writeFileSync(outPath, php, 'utf8');

const changed = previous !== php;
process.stdout.write(
  `${changed ? '更新しました' : '差分はありません'}: src/AnswerSchema.php` +
    `（分類 ${STEPS.length} / パス ${paths.length}）\n`,
);
