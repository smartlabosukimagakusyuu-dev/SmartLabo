/**
 * 回答の正式構造を PHP へ機械生成する（HP-ONBOARDING-4F-R1）。
 *
 *   node intake-api/dev/generate-answer-schema.mjs
 *
 * ★同じ定義を PHP と JavaScript へ**二重に手書きしない**ための道具である。
 *   正は `public/assets/lib/schema.js`（SSOT §3 を写したもの）。
 *   ここから `src/AnswerSchema.php` を作り直す。
 *
 * ★必須の一覧もここで作る（4F-R3）。PHP へ手書きしない。
 *   種別は SSOT v1.9 §3.0.2 の5種:
 *     STORE_REQUIRED_NON_EMPTY / STORE_REQUIRED_KEY_ALLOW_EMPTY /
 *     ADMIN_REQUIRED_FOR_EXPORT / ARRAY_ELEMENT_REQUIRED / OPTIONAL
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
  [KIND.BOOL_CHOICE]: 'bool',
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

/** SSOT v1.9 §3.0.2 の必須種別 */
const storeRequiredNonEmpty = [];
const storeRequiredKeyOnly = [];
const adminRequiredForExport = [];
const arrayElementRequired = [];
const storePaths = [];
const adminPaths = [];
/** 語彙が決まっている項目（SELECT / CHECKS）。path -> 正式な値 */
const enums = {};

for (const step of STEPS) {
  paths.push(step.key);
  storePaths.push(step.key);

  // 分類そのものが1件以上必要か（menus / image_metadata の min）
  if (step.kind === KIND.OBJECTS && typeof step.min === 'number' && step.min > 0) {
    storeRequiredNonEmpty.push(step.key);
  }

  const item = {};
  for (const field of step.fields) {
    const full = `${step.key}.${field.path}`;
    paths.push(full);
    assign(item, field.path, nodeFor(field));

    // ★語彙が決まっているものは、サーバー側でも正式値だけを受け付ける（4F-R3 §5）
    if ((field.kind === KIND.SELECT || field.kind === KIND.CHECKS) && Array.isArray(field.options)) {
      enums[full] = field.options.map(([v]) => v).filter((v) => v !== '');
    }

    if (field.audience === 'admin') {
      adminPaths.push(full);
      if (field.adminRequired) {
        adminRequiredForExport.push(full);
      }
      continue;
    }
    storePaths.push(full);

    // ★繰り返し分類（menus / staff / image_metadata）の中の項目は
    //   「要素があるときに、その要素が満たすべき条件」である。
    //   分類そのものが必須かどうかとは別の話なので混ぜない。
    if (step.kind === KIND.OBJECTS) {
      if (field.required) {
        arrayElementRequired.push(full);
      }
    } else if (field.required) {
      storeRequiredNonEmpty.push(full);
    } else if (field.requiredKey) {
      storeRequiredKeyOnly.push(full);
    }

    // 配列要素・object の必須子キー（weekly / confirmations / parking）
    for (const child of field.itemRequired || []) {
      arrayElementRequired.push(`${full}.${child}`);
    }
  }

  sections[step.key] =
    step.kind === KIND.OBJECTS
      ? { type: 'objects', item }
      : { type: 'object', fields: item };
}

const optionalPaths = paths.filter(
  (x) =>
    !storeRequiredNonEmpty.includes(x) &&
    !storeRequiredKeyOnly.includes(x) &&
    !adminRequiredForExport.includes(x),
);

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

    /** 店舗が入力するパス（分類そのものを含む）。§3.12 */
    public const STORE_PATHS = ${phpList(storePaths, 4, 3)};

    /** Smart Labo が管理画面から設定するパス。★店舗へ出さない・店舗から書けない */
    public const ADMIN_PATHS = ${phpList(adminPaths, 4, 2)};

    /**
     * 店舗が**値を入れる**まで提出できない（"" / [] / null は未回答）。
     * ★既定値を自動で入れない。enum は店舗が能動的に選ぶまで未回答（代表判断 Q3）。
     */
    public const STORE_REQUIRED_NON_EMPTY = ${phpList(storeRequiredNonEmpty, 4, 2)};

    /**
     * キーの存在は必須だが、正式な空値を認める。
     * ★\`contact_form.enabled\` の \`false\` は「設置しない」という**回答**である（代表判断 Q2）。
     */
    public const STORE_REQUIRED_KEY_ALLOW_EMPTY = ${phpList(storeRequiredKeyOnly, 4, 2)};

    /**
     * 店舗の提出を妨げないが、**書き出しの前に** Smart Labo が設定する（代表判断 Q4）。
     * ★「設定した」＝キーが存在すること。該当が無い場合も明示的に記録する。
     */
    public const ADMIN_REQUIRED_FOR_EXPORT = ${phpList(adminRequiredForExport, 4, 2)};

    /**
     * 配列要素・object の中で満たすべき条件。
     * ★要素が存在するときだけ効く。配列そのものが必須かは別に決まる。
     * ★\`false\` を欠落として扱わない（\`menus[].published\` など）。
     */
    public const ARRAY_ELEMENT_REQUIRED = ${phpList(arrayElementRequired, 4, 2)};

    /** 上のどれにも入らないパス（欠落してよい） */
    public const OPTIONAL_PATHS = ${phpList(optionalPaths, 4, 3)};

    /**
     * 語彙が決まっている項目。**正式な値以外は保存できない**。
     * ★未入力（\`null\` / \`""\`）は語彙の検査をしない。回答済みかどうかは必須側で見る。
     * @var array<string,list<string>>
     */
    public const ENUMS = ${phpArray(enums, 4)};
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
    `（分類 ${STEPS.length} / パス ${paths.length} = 店舗 ${storePaths.length} + 管理 ${adminPaths.length}` +
      ` / 必須 ${storeRequiredNonEmpty.length}+${storeRequiredKeyOnly.length}` +
      ` / 管理必須 ${adminRequiredForExport.length} / 要素必須 ${arrayElementRequired.length}）\n`,
);
