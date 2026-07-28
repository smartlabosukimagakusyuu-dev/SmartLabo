#!/usr/bin/env node
/**
 * 料金・共通部品の整合チェック（WEB-V2-4）
 * =========================================================================
 * 目的
 *   トップページ(index.html)と料金ページ(pricing.html)で料金が食い違う事故を防ぐ。
 *   片方だけ直して、もう片方が古いまま公開される、という失敗を機械的に止める。
 *
 * 置き場所について
 *   このスクリプトは docs/ 配下にある。GitHub Pages が配信するのは WEBSITE/ だけで、
 *   docs/ は公開対象に一切含まれない。website-v2/ に置くと本番切替時に
 *   「公開対象から外す」という手順が増えて事故のもとになるため、ここに置いている。
 *
 * 使い方
 *   node docs/reviews/tools/check-prices.js
 *   終了コード 0 = 一致 / 1 = 不一致（内容を標準出力に表示）
 *
 * 料金を変更するとき
 *   下の CANONICAL と、index.html・pricing.html の3か所すべてを更新する。
 *   1か所でも漏れるとこのスクリプトが失敗する。
 *
 * 設計方針
 *   ・HTMLの料金は実テキストのまま（JS無効でも読める／検索エンジンにも見える）。
 *   ・data-price 属性は「どこが料金か」を機械が特定するための目印にすぎず、
 *     表示には一切使っていない。表示をJSに依存させていない。
 *   ・外部ライブラリもビルド環境も追加していない（Node標準のみ）。
 * =========================================================================
 */

'use strict';
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '../../../website-v2');

/** 料金の正規値。ここが唯一の基準。 */
const CANONICAL = {
  initial:    '10,000',   // 初期費用（円・税別）
  monthly:    '20,000',   // 月額基本料金（円・税別）
  additional: '3,000',    // 追加アカウント（円／月・1人・税別）
};

/** 利用人数別の例。CANONICAL から計算し、HTMLの表記と突き合わせる。 */
const yen = (n) => n.toLocaleString('en-US');
const num = (s) => Number(String(s).replace(/,/g, ''));

const EXAMPLES = {
  ex1: yen(num(CANONICAL.monthly)),                                  // 1人
  ex3: yen(num(CANONICAL.monthly) + num(CANONICAL.additional) * 2),  // 3人
  ex5: yen(num(CANONICAL.monthly) + num(CANONICAL.additional) * 4),  // 5人
};

/** data-price="key" を持つ要素のテキストから数字部分だけを取り出す */
function extractPrices(html) {
  const found = {};
  const re = /data-price="([a-z0-9]+)"[^>]*>([\s\S]*?)</gi;
  let m;
  while ((m = re.exec(html)) !== null) {
    const value = m[2].replace(/<[^>]*>/g, '').trim();
    found[m[1]] = value;
  }
  return found;
}

/** ヘッダー・フッターが全ページで同一かを確認する（8ファイルの手作業同期を検知） */
function extractBlock(html, startTag, endTag) {
  const s = html.indexOf(startTag);
  const e = html.indexOf(endTag);
  if (s < 0 || e < 0) return null;
  return html.slice(s, e + endTag.length)
    .replace(/<!--[\s\S]*?-->/g, '')      // 注釈の有無は表示に影響しないので比較対象から外す
    // トップページ内のアンカーは、トップでは "#solution"、サブページでは
    // "index.html#solution" になるのが正しい。ここだけは差があってよいので揃えて比較する。
    .replace(/href="index\.html#/g, 'href="#')
    .replace(/\s+/g, ' ')
    .trim();
}

const errors = [];
const notes = [];

// ---------- 1. 料金の一致 ----------
const pricingHtml = fs.readFileSync(path.join(ROOT, 'pricing.html'), 'utf8');
const indexHtml   = fs.readFileSync(path.join(ROOT, 'index.html'), 'utf8');

const pricingPrices = extractPrices(pricingHtml);
const indexPrices   = extractPrices(indexHtml);

for (const [key, want] of Object.entries(CANONICAL)) {
  for (const [file, got] of [['pricing.html', pricingPrices[key]], ['index.html', indexPrices[key]]]) {
    if (got === undefined) {
      errors.push(`${file}: data-price="${key}" が見つかりません`);
    } else if (got !== want) {
      errors.push(`${file}: ${key} が不一致（正規値 ${want} / HTML ${got}）`);
    }
  }
}

// ---------- 2. 人数別の計算例 ----------
for (const [key, want] of Object.entries(EXAMPLES)) {
  const got = pricingPrices[key];
  if (got === undefined) {
    errors.push(`pricing.html: data-price="${key}" が見つかりません`);
  } else if (got !== want) {
    errors.push(`pricing.html: 計算例 ${key} が不一致（計算値 ${want} / HTML ${got}）`);
  }
}

// ---------- 3. 税別表記が料金ページにあるか ----------
if (!/税別/.test(pricingHtml)) errors.push('pricing.html: 「税別」の表記が見つかりません');
if (!/税別/.test(indexHtml))   errors.push('index.html: 「税別」の表記が見つかりません');

// ---------- 4. 未確定条件が書かれていないか ----------
const FORBIDDEN = ['最低契約期間', '無料期間', '返金', 'キャンペーン', '割引', '人気No', '最安', 'おすすめプラン'];
for (const word of FORBIDDEN) {
  if (pricingHtml.includes(word)) errors.push(`pricing.html: 掲載禁止の語「${word}」が含まれています`);
}

// ---------- 5. ヘッダー・フッターが全ページで同一か ----------
const PAGES = ['index.html', 'features.html', 'pricing.html', 'company.html',
               'contact.html', 'privacy.html', 'terms.html', '404.html'];
let refHeader = null, refFooter = null;
for (const f of PAGES) {
  const html = fs.readFileSync(path.join(ROOT, f), 'utf8');
  const header = extractBlock(html, '<header class="site-header"', '</header>');
  const footer = extractBlock(html, '<footer class="site-footer">', '</footer>');
  if (f === 'index.html') { refHeader = header; refFooter = footer; continue; }
  if (header !== refHeader) errors.push(`${f}: ヘッダーが index.html と一致しません`);
  if (footer !== refFooter) errors.push(`${f}: フッターが index.html と一致しません`);
}

// ---------- 6. パンくず: 構造化データと画面表示の一致 ----------
// 構造化データは「画面に出している内容」と一致していなければならない。
// 表示していないのに BreadcrumbList だけ出す、という状態を防ぐ。
for (const f of PAGES) {
  const html = fs.readFileSync(path.join(ROOT, f), 'utf8');
  const hasLd  = html.includes('"BreadcrumbList"');
  const hasVis = html.includes('class="breadcrumb"');
  if (hasLd !== hasVis) {
    errors.push(`${f}: パンくずの構造化データ(${hasLd})と画面表示(${hasVis})が食い違っています`);
  }
}

// ---------- 7. 禁止語の全ページ確認 ----------
// WEB-V2-5でブランドを「中小企業向けAI業務支援」へ変更したため、
// 特定業種を強く連想させる語と、社内用語・取引先名をまとめて禁止する。
const BANNED = [
  'Company OS',   // 社内用プラットフォーム名称
  'ミライエ',      // 取引先名
  '不動産', '物件', '査定', '売買', '仲介',  // 特定業種を連想させる語
];
for (const f of PAGES) {
  const html = fs.readFileSync(path.join(ROOT, f), 'utf8');
  for (const w of BANNED) {
    if (html.includes(w)) errors.push(`${f}: 禁止語「${w}」が含まれています`);
  }
}

// ---------- 8. 代表個人の紹介が復活していないか ----------
// WEB-V2-5で会社紹介は「企業紹介」へ変更した。会社概要の代表者欄は残すが、
// 経歴・メッセージ・署名は掲載しない。
const PERSONAL = ['野村ソリューションズ', 'アーネストワン', '代表メッセージ', '代表の経歴'];
for (const f of PAGES) {
  const html = fs.readFileSync(path.join(ROOT, f), 'utf8');
  for (const w of PERSONAL) {
    if (html.includes(w)) errors.push(`${f}: 個人紹介の記述「${w}」が含まれています`);
  }
}

// ---------- 9. フォーム・外部送信が入り込んでいないか ----------
// 問い合わせ方式が確定するまで、フォーム要素・送信先・外部フォームサービスは
// 一切置かない方針（WEB-V2-6）。見た目だけのダミーフォームも禁止。
const FORM_MARKERS = [
  /<form[\s>]/i, /<input[\s>/]/i, /<textarea[\s>]/i, /<select[\s>]/i,
  /type=["']submit["']/i, /formspree/i, /docs\.google\.com\/forms/i,
  /mailto:/i, /action=["'][^"']/i,
];
for (const f of PAGES) {
  const html = fs.readFileSync(path.join(ROOT, f), 'utf8')
    .replace(/<!--[\s\S]*?-->/g, '');   // 注釈内の説明文は対象外
  for (const re of FORM_MARKERS) {
    if (re.test(html)) errors.push(`${f}: フォーム/送信先らしき記述があります（${re}）`);
  }
}

// ---------- 結果 ----------
notes.push(`正規値: 初期費用 ${CANONICAL.initial}円 / 月額 ${CANONICAL.monthly}円 / 追加 ${CANONICAL.additional}円（税別）`);
notes.push(`計算例: 1人 ${EXAMPLES.ex1}円 / 3人 ${EXAMPLES.ex3}円 / 5人 ${EXAMPLES.ex5}円（月額のみ・税別）`);

notes.forEach((n) => console.log('  ' + n));

if (errors.length) {
  console.log('\n[NG] ' + errors.length + '件の不整合');
  errors.forEach((e) => console.log('  - ' + e));
  process.exit(1);
}
console.log('\n[OK] 料金・共通部品ともに整合しています');
