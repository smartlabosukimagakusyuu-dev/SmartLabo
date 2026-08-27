/**
 * 画面側テストのランナー（外部ライブラリなし）。
 *
 *   node intake-api/tests/js/run-tests.mjs
 *   終了コード 0 = 全件成功 / 1 = 失敗あり
 */

import { readdir } from 'node:fs/promises';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { dirname, join } from 'node:path';

import { Runner } from './bootstrap.mjs';

const here = dirname(fileURLToPath(import.meta.url));

const files = (await readdir(here)).filter((f) => f.startsWith('test-') && f.endsWith('.mjs')).sort();
for (const file of files) {
  await import(pathToFileURL(join(here, file)).href);
}

console.log('HP Intake 画面 — テスト');
console.log('-'.repeat(64));

for (const t of Runner.tests) {
  try {
    await t.fn();
    Runner.passed += 1;
  } catch (e) {
    Runner.failed.push(`${t.name} :: ${e && e.message ? e.message : String(e)}`);
  }
}

const total = Runner.passed + Runner.failed.length;
console.log(`  実行 ${total}件 / 成功 ${Runner.passed}件 / 失敗 ${Runner.failed.length}件`);

if (Runner.failed.length > 0) {
  console.log('\n[NG] 失敗したテスト');
  for (const name of Runner.failed) {
    console.log(`  - ${name}`);
  }
  process.exit(1);
}

console.log('\n[OK] すべて成功しました');
process.exit(0);
