#!/usr/bin/env node
/**
 * 法務ページの本文一致チェック（WEB-V2-5）
 * =========================================================================
 * 目的
 *   privacy.html / terms.html の本文は、Version1（WEBSITE/）から
 *   一字も変更せず移植することが条件になっている。
 *   レイアウト調整のついでに文言が変わってしまう事故を機械的に止める。
 *
 * 比較の考え方
 *   HTMLタグ・クラス名・空白・改行はレイアウトの一部なので比較対象から外し、
 *   「画面に出る文字列」だけを取り出して1文字単位で突き合わせる。
 *
 *   v2側はパンくず（ホーム／ページ名）が増え、v1側にはh1と同じ文字列の
 *   バッジがある。この2点だけは表示上の差分として除外する。
 *
 * 使い方
 *   node docs/reviews/tools/check-legal.js
 *   終了コード 0 = 一致 / 1 = 不一致（差分の行番号と内容を表示）
 * =========================================================================
 */

'use strict';
const fs = require('fs');
const path = require('path');

const V1 = path.resolve(__dirname, '../../../WEBSITE');
const V2 = path.resolve(__dirname, '../../../website-v2');

const PAGES = [
  { file: 'privacy.html', crumb: 'プライバシーポリシー' },
  { file: 'terms.html',   crumb: '利用規約' },
];

/** 画面に出る文字列だけを取り出す */
function visibleText(html) {
  return html
    .replace(/<script[\s\S]*?<\/script>/g, '')
    .replace(/<style[\s\S]*?<\/style>/g, '')
    .replace(/<!--[\s\S]*?-->/g, '')
    .replace(/<[^>]+>/g, '\n')
    .replace(/&nbsp;/g, ' ')
    .replace(/&amp;/g, '&')
    .split('\n').map((s) => s.trim()).filter(Boolean);
}

/** <main> の中身だけを取り出す */
function mainOf(html) {
  const m = html.match(/<main[\s\S]*?<\/main>/);
  return m ? m[0] : html;
}

const errors = [];

for (const p of PAGES) {
  const v1 = visibleText(mainOf(fs.readFileSync(path.join(V1, p.file), 'utf8')));
  const v2 = visibleText(mainOf(fs.readFileSync(path.join(V2, p.file), 'utf8')));

  // v1: h1と同じ文字列のバッジを1つだけ落とす
  const i1 = v1.indexOf(p.crumb);
  if (i1 >= 0 && v1[i1 + 1] === p.crumb) v1.splice(i1, 1);

  // v2: パンくずの「ホーム」と ページ名 を落とす
  const iHome = v2.indexOf('ホーム');
  if (iHome >= 0) v2.splice(iHome, 1);
  const iCrumb = v2.indexOf(p.crumb);
  if (iCrumb >= 0 && v2[iCrumb + 1] === p.crumb) v2.splice(iCrumb, 1);

  if (v1.length !== v2.length) {
    errors.push(`${p.file}: 行数が違います（v1 ${v1.length}行 / v2 ${v2.length}行）`);
  }
  let diff = 0;
  for (let i = 0; i < Math.max(v1.length, v2.length); i++) {
    if (v1[i] !== v2[i]) {
      diff++;
      if (diff <= 3) {
        errors.push(`${p.file}: ${i + 1}行目が不一致\n      v1: ${v1[i] ?? '(なし)'}\n      v2: ${v2[i] ?? '(なし)'}`);
      }
    }
  }
  const chars = v1.join('').length;
  console.log(`  ${p.file}: ${v1.length}行 / ${chars}文字 → ${diff === 0 ? '完全一致' : diff + '行が不一致'}`);
}

if (errors.length) {
  console.log('\n[NG] 法務ページの本文が Version1 と一致していません');
  errors.forEach((e) => console.log('  - ' + e));
  console.log('\n  法務文言はレイアウト調整で書き換えてはいけません。');
  process.exit(1);
}
console.log('\n[OK] 法務ページの本文は Version1 と完全に一致しています');
