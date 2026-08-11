#!/usr/bin/env node
/**
 * 法務ページの検査（WEB-SALES-8L で全面改定）
 * =========================================================================
 * 旧版（WEB-V2-5〜WEB-V3-5A-1）は「WEBSITE/=Version1 の凍結本文と
 * website-v3/ の本文が一字も違わないこと」を検査していた。
 * WEB-SALES-8L で WEBSITE/ を master（v3系）基準で正式化し、
 * ドラフト表示・[CEO確認待ち]・GA4/GTM を撤去して本文を改定したため、
 * 旧版の前提（Version1凍結）は失効した。
 *
 * 本版は「発売前の公開条件」を機械的に検査する：
 *   1. 公開ページに未確定・ドラフトを示す文字列が無いこと
 *   2. 公開ページにアクセス解析（GTM/GA4/dataLayer）の読み込みが無いこと
 *   3. tokushoho.html / company.html の事業者情報が代表承認値と一致すること
 *   4. 法務ページ（terms/privacy/tokushoho）に制定日・最終更新日があること
 *   5. フッターから特商法表記と Lite 法務3文書へ到達できること
 *
 * 使い方
 *   node docs/reviews/tools/check-legal.js
 *   終了コード 0 = 合格 / 1 = 不合格（理由を表示）
 * =========================================================================
 */

'use strict';
const fs = require('fs');
const path = require('path');

const SITE = path.resolve(__dirname, '../../../WEBSITE');

/** 代表承認値（Lite本体 server/services/legal/legalDocuments.js の SELLER と一致させる） */
const SELLER = {
  name: '株式会社スマートラボ',
  representative: '小川 昌利',
  address: '鳥取県八頭郡八頭町下濃276番地2',
  phone: '090-4650-7417',
  hours: '平日9:00〜17:00',
};

/** 公開してはいけない未確定・ドラフト文字列 */
const FORBIDDEN = [
  '[CEO確認待ち]',
  'CEO確認待ち：',
  '専門家確認前',
  'ドラフトです',
  '選定中',
  '未決定事項',
];

/** アクセス解析の読み込み（コメントでの言及は許す。実コードのみ検出する） */
const ANALYTICS_PATTERNS = [
  /<script[^>]*googletagmanager\.com/i,
  /<iframe[^>]*googletagmanager\.com/i,
  /window\.dataLayer\s*=/,
  /dataLayer\.push\(/,
  /gtag\(/,
  /google-analytics\.com/i,
];

const LITE_LEGAL = [
  'https://lite.smartlaboworks.com/legal/terms',
  'https://lite.smartlaboworks.com/legal/privacy',
  'https://lite.smartlaboworks.com/legal/commercial-transactions',
];

const errors = [];
const stripComments = (html) => html.replace(/<!--[\s\S]*?-->/g, '');

const htmlFiles = fs.readdirSync(SITE).filter((f) => f.endsWith('.html'));

// 1・2: 全公開ページの未確定文字列・解析タグ
for (const file of htmlFiles) {
  const raw = fs.readFileSync(path.join(SITE, file), 'utf8');
  const visible = stripComments(raw);
  for (const bad of FORBIDDEN) {
    if (visible.includes(bad)) errors.push(`${file}: 未確定文字列「${bad}」が残っています`);
  }
  for (const re of ANALYTICS_PATTERNS) {
    if (re.test(visible)) errors.push(`${file}: アクセス解析の読み込み（${re}）が残っています`);
  }
}

// JSも解析コードを持たないこと
const jsDir = path.join(SITE, 'assets', 'js');
if (fs.existsSync(jsDir)) {
  for (const file of fs.readdirSync(jsDir).filter((f) => f.endsWith('.js'))) {
    const src = fs.readFileSync(path.join(jsDir, file), 'utf8')
      .replace(/\/\*[\s\S]*?\*\//g, '').replace(/^\s*\/\/.*$/gm, '');
    for (const re of [/dataLayer/, /gtag\(/, /googletagmanager/i]) {
      if (re.test(src)) errors.push(`assets/js/${file}: 解析コード（${re}）が残っています`);
    }
  }
}

// 3: 事業者情報の承認値一致
{
  const tokushoho = stripComments(fs.readFileSync(path.join(SITE, 'tokushoho.html'), 'utf8'));
  for (const [key, value] of Object.entries(SELLER)) {
    if (!tokushoho.includes(value)) errors.push(`tokushoho.html: 承認値（${key}: ${value}）が見つかりません`);
  }
  const company = stripComments(fs.readFileSync(path.join(SITE, 'company.html'), 'utf8'));
  for (const key of ['name', 'representative', 'address', 'phone']) {
    if (!company.includes(SELLER[key])) errors.push(`company.html: 承認値（${key}: ${SELLER[key]}）が見つかりません`);
  }
}

// 4: 法務ページの制定日・最終更新日
for (const file of ['terms.html', 'privacy.html', 'tokushoho.html']) {
  const html = stripComments(fs.readFileSync(path.join(SITE, file), 'utf8'));
  if (!/制定日：\d{4}-\d{2}-\d{2}/.test(html)) errors.push(`${file}: 制定日（YYYY-MM-DD）がありません`);
  if (!/最終更新日：\d{4}-\d{2}-\d{2}/.test(html)) errors.push(`${file}: 最終更新日（YYYY-MM-DD）がありません`);
}

// 5: フッター導線（index を代表として検査。tokushoho と Lite 3文書）
{
  const index = stripComments(fs.readFileSync(path.join(SITE, 'index.html'), 'utf8'));
  if (!index.includes('href="tokushoho.html"')) errors.push('index.html: フッターに特商法表記への導線がありません');
  for (const url of LITE_LEGAL) {
    if (!index.includes(url)) errors.push(`index.html: Lite法務文書への導線（${url}）がありません`);
  }
  const pricing = stripComments(fs.readFileSync(path.join(SITE, 'pricing.html'), 'utf8'));
  const apply = stripComments(fs.readFileSync(path.join(SITE, 'apply.html'), 'utf8'));
  for (const [name, html] of [['pricing.html', pricing], ['apply.html', apply]]) {
    if (!html.includes('tokushoho.html')) errors.push(`${name}: 特商法表記への導線がありません`);
    if (!LITE_LEGAL.some((u) => html.includes(u))) errors.push(`${name}: Lite法務文書への導線がありません`);
  }
}

if (errors.length) {
  console.error('[NG] 発売前の公開条件を満たしていません：');
  for (const e of errors) console.error('  - ' + e);
  process.exit(1);
}
console.log('[OK] 未確定文字列0・解析タグ0・承認値一致・日付・法務導線を確認しました');
