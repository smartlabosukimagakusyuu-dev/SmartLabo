#!/usr/bin/env node
/**
 * 法務ページの検査（WEB-SALES-8L で新設 → WEB-SALES-8L-R で掲載範囲の検査へ改定）
 * =========================================================================
 * 代表方針（8L-R）
 *   Smart Labo Works Lite の契約に関する法務文書（利用規約・プライバシーポリシー・
 *   特定商取引法に基づく表記）と、所在地・電話番号・受付時間は、
 *   **Lite のお支払いへ進む前の最終確認画面**と**ご契約後の設定画面**で読む。
 *   公開Websiteへは本文を複製せず、直接リンクも置かない。
 *   正本は Lite 側の server/services/legal/legalDocuments.js の1か所だけ。
 *
 *   一方、公開Website自身の利用条件・お問い合わせフォームの個人情報の取扱いを定める
 *   Website独自の terms.html / privacy.html は維持する（適用対象が違う別文書）。
 *
 * 本ファイルが検査すること
 *   1. 公開ページに未確定・ドラフトを示す文字列が無いこと
 *   2. 公開ページにアクセス解析（GTM/GA4/dataLayer）の読み込みが無いこと
 *   3. company.html に所在地・電話番号・受付時間が無いこと（8L-R）
 *   4. 公開ページに Lite 法務文書への直接リンクが無いこと（8L-R）
 *   5. Website版 tokushoho.html が存在せず、sitemap にも載っていないこと（8L-R）
 *   6. pricing.html / apply.html に「最終確認画面で確認し、同意した場合のみ支払いへ進む」
 *      旨の案内があること（8L-R）
 *   7. Website独自の privacy.html / terms.html が維持され、日付を持つこと
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

/**
 * 公開Websiteへ載せない情報（8L-R）。
 * これらは Lite の特定商取引法に基づく表記に記載し、最終確認画面から読む。
 */
const NOT_ON_WEBSITE = [
  { label: '所在地', value: '鳥取県八頭郡八頭町下濃276番地2' },
  { label: '電話番号', value: '090-4650-7417' },
  { label: '受付時間', value: '平日9:00〜17:00' },
];

/** Lite法務文書への直接リンク（公開Websiteには置かない） */
const LITE_LEGAL_LINK = /lite\.smartlaboworks\.com\/legal/;

/** 最終確認画面の案内に必ず含める語（表現の揺れは許容し、要点だけを見る） */
const NOTICE_KEYWORDS = ['最終確認画面', '同意', 'お支払い'];

const errors = [];
const stripComments = (html) => html.replace(/<!--[\s\S]*?-->/g, '');

const htmlFiles = fs.readdirSync(SITE).filter((f) => f.endsWith('.html'));

// 1・2・3・4: 全公開ページ
for (const file of htmlFiles) {
  const raw = fs.readFileSync(path.join(SITE, file), 'utf8');
  const visible = stripComments(raw);

  for (const bad of FORBIDDEN) {
    if (visible.includes(bad)) errors.push(`${file}: 未確定文字列「${bad}」が残っています`);
  }
  for (const re of ANALYTICS_PATTERNS) {
    if (re.test(visible)) errors.push(`${file}: アクセス解析の読み込み（${re}）が残っています`);
  }
  // ★8L-R: Lite法務文書への直接リンクを公開Websiteへ置かない
  if (LITE_LEGAL_LINK.test(visible)) {
    errors.push(`${file}: Lite法務文書への直接リンクが残っています（8L-R: 最終確認画面から読む方針）`);
  }
  // ★8L-R: 所在地・電話番号・受付時間を一般公開ページへ載せない
  for (const item of NOT_ON_WEBSITE) {
    if (visible.includes(item.value)) {
      errors.push(`${file}: ${item.label}が公開Websiteに掲載されています（8L-R: Liteの特商法表記へ集約）`);
    }
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

// 5: Website版 tokushoho.html は存在しない（Lite側が正本。二重管理をしない）
{
  if (fs.existsSync(path.join(SITE, 'tokushoho.html'))) {
    errors.push('WEBSITE/tokushoho.html が存在します（8L-R: Lite側の表記が正本のため置かない）');
  }
  const sitemap = fs.readFileSync(path.join(SITE, 'sitemap.xml'), 'utf8');
  if (sitemap.includes('tokushoho')) errors.push('sitemap.xml に tokushoho の項目が残っています');
  const robots = fs.readFileSync(path.join(SITE, 'robots.txt'), 'utf8');
  if (robots.includes('tokushoho')) errors.push('robots.txt に tokushoho の記載が残っています');
}

// 6: 料金・申込案内に最終確認画面の案内がある
for (const file of ['pricing.html', 'apply.html']) {
  const html = stripComments(fs.readFileSync(path.join(SITE, file), 'utf8'));
  const missing = NOTICE_KEYWORDS.filter((k) => !html.includes(k));
  if (missing.length) {
    errors.push(`${file}: 最終確認画面の案内が不足しています（不足語: ${missing.join(' / ')}）`);
  }
}

// 7: Website独自の法務ページは維持し、日付を持つ
for (const file of ['terms.html', 'privacy.html']) {
  const full = path.join(SITE, file);
  if (!fs.existsSync(full)) {
    errors.push(`${file}: Website独自の法務ページが存在しません（8L-R: 維持する）`);
    continue;
  }
  const html = stripComments(fs.readFileSync(full, 'utf8'));
  if (!/制定日：\d{4}-\d{2}-\d{2}/.test(html)) errors.push(`${file}: 制定日（YYYY-MM-DD）がありません`);
  if (!/最終更新日：\d{4}-\d{2}-\d{2}/.test(html)) errors.push(`${file}: 最終更新日（YYYY-MM-DD）がありません`);
}
// お問い合わせフォームからWebsite独自プライバシーポリシーへ到達できること
{
  const contact = stripComments(fs.readFileSync(path.join(SITE, 'contact.html'), 'utf8'));
  if (!contact.includes('privacy.html')) {
    errors.push('contact.html: Website独自プライバシーポリシーへの導線がありません');
  }
}

if (errors.length) {
  console.error('[NG] 公開Websiteの掲載範囲が方針を満たしていません：');
  for (const e of errors) console.error('  - ' + e);
  process.exit(1);
}
console.log(
  '[OK] 未確定文字列0・解析タグ0・所在地/電話/受付時間の非掲載・Lite法務直リンク0・' +
    'Website版tokushoho不存在・最終確認画面の案内あり・Website独自文書の維持を確認しました',
);
