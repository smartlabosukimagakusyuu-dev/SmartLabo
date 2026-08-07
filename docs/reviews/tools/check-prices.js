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
 *   下の CANONICAL と、index.html・pricing.html・apply.html の4か所すべてを更新する。
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

// WEB-V3-1: 検査対象を Version 3 のフォルダへ切り替えた
const ROOT = path.resolve(__dirname, '../../../website-v3');

/** 料金の正規値。ここが唯一の基準。 */
const CANONICAL = {
  initial:    '10,000',   // 初期設定費（円・税抜）。WEB-V2-8で名称を「初期費用」から正式名称「初期設定費」へ統一
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
// WEB-V2-8 で apply.html(申込案内)、SALES-1 で signup.html(申込手続き)にも
// 料金を掲載したため、突き合わせ対象は4ページ。
const pricingHtml = fs.readFileSync(path.join(ROOT, 'pricing.html'), 'utf8');
const indexHtml   = fs.readFileSync(path.join(ROOT, 'index.html'), 'utf8');
const applyHtml   = fs.readFileSync(path.join(ROOT, 'apply.html'), 'utf8');
const signupHtml  = fs.readFileSync(path.join(ROOT, 'signup.html'), 'utf8');

const pricingPrices = extractPrices(pricingHtml);
const indexPrices   = extractPrices(indexHtml);
const applyPrices   = extractPrices(applyHtml);
const signupPrices  = extractPrices(signupHtml);

for (const [key, want] of Object.entries(CANONICAL)) {
  for (const [file, got] of [['pricing.html', pricingPrices[key]],
                             ['index.html', indexPrices[key]],
                             ['apply.html', applyPrices[key]],
                             ['signup.html', signupPrices[key]]]) {
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

// ---------- 3. 税別・税抜表記が料金掲載ページにあるか ----------
// 14_Sales_And_Billing_Policy.md は「税抜表示を基本とする」。既存2ページは「税別」
// 表記で公開準備済みのため、どちらの語でも合格とする(意味は同一)。
if (!/税別|税抜/.test(pricingHtml)) errors.push('pricing.html: 「税別」「税抜」の表記が見つかりません');
if (!/税別|税抜/.test(indexHtml))   errors.push('index.html: 「税別」「税抜」の表記が見つかりません');
if (!/税別|税抜/.test(applyHtml))   errors.push('apply.html: 「税別」「税抜」の表記が見つかりません');
if (!/税別|税抜/.test(signupHtml))  errors.push('signup.html: 「税別」「税抜」の表記が見つかりません');

// ---------- 4. 未確定条件が書かれていないか ----------
// WEB-V2-8で「創業記念キャンペーン」が正式採用されたため、キャンペーンの掲載は
// index.html と apply.html に限って許可する。pricing.html は通常料金の正本として
// キャンペーン・割引を書かないままとする(未確定条件の混入も引き続き禁止)。
const FORBIDDEN = ['最低契約期間', '無料期間', '返金', 'キャンペーン', '割引', '人気No', '最安', 'おすすめプラン'];
for (const word of FORBIDDEN) {
  if (pricingHtml.includes(word)) errors.push(`pricing.html: 掲載禁止の語「${word}」が含まれています`);
}
// apply.html にも、キャンペーン以外の未確定条件は書けない
for (const word of ['最低契約期間', '無料期間', '返金', '割引', '人気No', '最安', 'おすすめプラン']) {
  if (applyHtml.includes(word)) errors.push(`apply.html: 掲載禁止の語「${word}」が含まれています`);
}

// ---------- 4-2. 創業記念キャンペーンの正規表記(WEB-V2-8) ----------
// 正本: PROJECT_BIBLE/14_Sales_And_Billing_Policy.md
//   ・「基本料金1か月分無料」と書く(「初月無料」は日割りとずれるため禁止)
//   ・「先着50社」— 残数の自動減算・カウントダウンは実装しない
//   ・追加アカウント料金は無料対象に含まれない(未確定のため)
const CAMPAIGN_PAGES = ['index.html', 'apply.html'];   // キャンペーンを掲載してよいページ
const CAMPAIGN_REQUIRED = ['創業記念キャンペーン', '基本料金1か月分無料', '先着50社', 'クレジットカード'];
for (const f of CAMPAIGN_PAGES) {
  // 注釈(コメント)内の文言は画面に表示されないため、必須表記の判定から除外する
  const html = fs.readFileSync(path.join(ROOT, f), 'utf8').replace(/<!--[\s\S]*?-->/g, '');
  for (const w of CAMPAIGN_REQUIRED) {
    if (!html.includes(w)) errors.push(`${f}: キャンペーンの必須表記「${w}」が見つかりません`);
  }
}

// ---------- 5. ヘッダー・フッターが全ページで同一か ----------
// PAGES        … 公開する9ページ(sitemap・SEO・パンくずの検査対象)
// INTERNAL_PAGES … 公開導線に載せていないページ。SALES-1で追加した申込手続き画面は
//                  決済(SALES-2)が未実装で確認画面の先が無いため、noindexにし
//                  sitemap・リンクのいずれにも載せていない。ただしヘッダー/フッターの
//                  同一性・禁止語・フォームの検査は同じように受ける。
const PAGES = ['index.html', 'features.html', 'pricing.html', 'company.html',
               'contact.html', 'apply.html', 'privacy.html', 'terms.html', '404.html'];
const INTERNAL_PAGES = ['signup.html'];
const ALL_PAGES = PAGES.concat(INTERNAL_PAGES);

let refHeader = null, refFooter = null;
for (const f of ALL_PAGES) {
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
for (const f of ALL_PAGES) {
  const html = fs.readFileSync(path.join(ROOT, f), 'utf8');
  for (const w of BANNED) {
    if (html.includes(w)) errors.push(`${f}: 禁止語「${w}」が含まれています`);
  }
}

// ---------- 7-2. 販売導線で使ってはいけない表現(WEB-V2-8) ----------
// いずれも正式機能・正式運用として採用されていない。
// 「初月無料」は採用済みキャンペーンの誤認表現(正しくは「基本料金1か月分無料」)。
const SALES_BANNED = [
  '初月無料',            // 誤認表現。日割りの初月と無料になる月がずれる
  '無料トライアル',       // 未採用
  // 「資料請求」は WEB-V3-1(2026-08-07 代表指示)で正式採用。お問い合わせ
  // フォームの種別「docs」として受け付けるため、禁止語から除外した。
  'デモ予約',            // 未採用
  'すぐ契約できます',     // 未採用の断定表現
  '即時利用可能',         // 未採用の断定表現
  '今なら必ず無料',       // 誇大表現
  '受付完了',            // セルフ申し込み未実装のため、完了したかのような表示は禁止
  '申し込み可能',         // 同上
];
for (const f of ALL_PAGES) {
  // 注釈内で「この表現を使うな」と説明している箇所は表示に影響しないため対象外
  const html = fs.readFileSync(path.join(ROOT, f), 'utf8').replace(/<!--[\s\S]*?-->/g, '');
  for (const w of SALES_BANNED) {
    if (html.includes(w)) errors.push(`${f}: 販売導線の禁止表現「${w}」が含まれています`);
  }
}

// ---------- 8. 代表個人の紹介が復活していないか ----------
// WEB-V2-5で会社紹介は「企業紹介」へ変更した。会社概要の代表者欄は残すが、
// 経歴・メッセージ・署名は掲載しない。
const PERSONAL = ['野村ソリューションズ', 'アーネストワン', '代表メッセージ', '代表の経歴'];
for (const f of ALL_PAGES) {
  const html = fs.readFileSync(path.join(ROOT, f), 'utf8');
  for (const w of PERSONAL) {
    if (html.includes(w)) errors.push(`${f}: 個人紹介の記述「${w}」が含まれています`);
  }
}

// ---------- 9. フォームの扱い ----------
// WEB-V2-7で contact.html に正式フォームを実装した。
// 「フォーム禁止」から「contact.html だけ許可し、条件を満たすこと」へ方針を変更する。
// SALES-1で signup.html(申込手続き)を追加した。送信先 /api/signup は入力を
// 検証して結果を返すだけで、保存も決済も行わない(signup-api/public/signup.php)。
const FORM_PAGES = ['contact.html', 'signup.html'];        // フォームを置いてよいページ
const ALLOWED_ACTIONS = ['/api/contact.php', '/api/signup']; // 送信先として認めるパス

for (const f of ALL_PAGES) {
  const html = fs.readFileSync(path.join(ROOT, f), 'utf8');
  const stripped = html.replace(/<!--[\s\S]*?-->/g, '');   // 注釈内の説明文は対象外
  const hasForm = /<form[\s>]/i.test(stripped);

  if (!FORM_PAGES.includes(f)) {
    // 許可ページ以外にフォーム要素が入っていないこと
    for (const re of [/<form[\s>]/i, /<input[\s>/]/i, /<textarea[\s>]/i, /<select[\s>]/i]) {
      if (re.test(stripped)) errors.push(`${f}: このページにフォーム要素は置けません（${re}）`);
    }
    continue;
  }

  if (!hasForm) {
    errors.push(`${f}: 正式フォームが見当たりません`);
    continue;
  }

  // --- 送信方式 ---
  const methods = [...stripped.matchAll(/<form[^>]*\bmethod="([^"]*)"/gi)].map(m => m[1].toLowerCase());
  if (methods.some(m => m !== 'post')) {
    errors.push(`${f}: フォームの method は post のみ許可（GET送信は禁止）`);
  }
  if (methods.length === 0) {
    errors.push(`${f}: フォームに method="post" がありません`);
  }

  // --- 送信先 ---
  const actions = [...stripped.matchAll(/<form[^>]*\baction="([^"]*)"/gi)].map(m => m[1]);
  for (const a of actions) {
    if (!ALLOWED_ACTIONS.includes(a)) {
      errors.push(`${f}: 許可されていない送信先です（${a}）`);
    }
  }
  // data-endpoint も同じ範囲に収める
  for (const m of stripped.matchAll(/data-endpoint="([^"]*)"/gi)) {
    if (!ALLOWED_ACTIONS.includes(m[1])) {
      errors.push(`${f}: 許可されていない data-endpoint です（${m[1]}）`);
    }
  }
}

// ---------- 10. 外部フォームサービス・mailto・ダミー送信先 ----------
const BAD_TARGETS = [
  [/\bformspree\b/i,                 '外部フォームサービス(Formspree)'],
  [/docs\.google\.com\/forms/i,      'Googleフォーム'],
  [/\bmailto:/i,                      'mailto:'],
  [/action="https?:\/\/(?!smartlaboworks\.com)/i, '外部ドメインへの送信先'],
  [/action="#"/i,                     'ダミーの送信先(#)'],
  [/action=""/i,                      '空の送信先'],
  [/\bexample\.(com|org|net)\/(api|contact|form)/i, 'ダミーの送信先(example)'],
];
for (const f of ALL_PAGES) {
  const html = fs.readFileSync(path.join(ROOT, f), 'utf8').replace(/<!--[\s\S]*?-->/g, '');
  for (const [re, label] of BAD_TARGETS) {
    if (re.test(html)) errors.push(`${f}: ${label} は使用できません`);
  }
}

// ---------- 11. 秘密情報・受信メールアドレスの混入 ----------
// 公開HTML・CSS・JSに、受信先メールアドレスや鍵らしき文字列が出ていないこと。
const PUBLIC_FILES = [
  ...ALL_PAGES.map(f => path.join(ROOT, f)),
  ...['assets/js/main.js', 'assets/js/contact-form.js', 'assets/js/signup.js',
      'assets/css/components.css']
      .map(f => path.join(ROOT, f)),
];
const SECRET_PATTERNS = [
  [/[A-Za-z0-9._%+-]+@(gmail|googlemail)\.com/i, '受信用メールアドレス（Gmail）'],
  [/[A-Za-z0-9._%+-]+@smartlaboworks\.com/i,     '自社メールアドレス'],
  [/\bsmtp_pass\b|\bsmtp_user\b/i,             'SMTP設定'],
  [/csrf_secret|ip_hash_secret/i,                'サーバー側の署名鍵の名称'],
  [/\b[A-Fa-f0-9]{40,}\b/,                      '鍵らしい長い16進文字列'],
  [/AIza[0-9A-Za-z_-]{10,}|sk-[A-Za-z0-9]{16,}/, 'APIキーらしい文字列'],
];
for (const file of PUBLIC_FILES) {
  if (!fs.existsSync(file)) continue;
  const src = fs.readFileSync(file, 'utf8');
  for (const [re, label] of SECRET_PATTERNS) {
    if (re.test(src)) {
      errors.push(`${path.basename(file)}: 公開ファイルに${label}が含まれています`);
    }
  }
}

// ---------- 12. 内部ページが公開導線に載っていないか(SALES-1) ----------
// signup.html は決済(SALES-2)・アカウント作成(SALES-5)が未実装で、確認画面の
// 先が存在しない。公開すると行き止まりになるため、次の3点を機械的に守る。
//   ・noindex が付いていること
//   ・sitemap.xml に載っていないこと
//   ・公開ページからリンクされていないこと
// SALES-2で決済が繋がったら、この節を「公開してよい」条件へ書き換える。
const sitemapXml = fs.readFileSync(path.join(ROOT, 'sitemap.xml'), 'utf8');

for (const f of INTERNAL_PAGES) {
  const html = fs.readFileSync(path.join(ROOT, f), 'utf8');

  if (!/<meta\s+name="robots"\s+content="[^"]*noindex/i.test(html)) {
    errors.push(`${f}: 公開導線に載せていないページには noindex が必要です`);
  }
  if (sitemapXml.includes(f)) {
    errors.push(`${f}: sitemap.xml に載せてはいけません（決済未実装のため）`);
  }
  for (const p of PAGES) {
    const publicHtml = fs.readFileSync(path.join(ROOT, p), 'utf8')
      .replace(/<!--[\s\S]*?-->/g, '');   // 注釈内の説明は対象外
    if (publicHtml.includes(`href="${f}"`)) {
      errors.push(`${p}: ${f} へのリンクは、決済が実装されるまで置けません`);
    }
  }
}

// 内部ページ側の申込フォームが「契約が成立した」と誤解される状態でないこと。
// 実際の申込画面が公開できるようになるまで、準備中である旨の明示を必須にする。
{
  const html = fs.readFileSync(path.join(ROOT, 'signup.html'), 'utf8')
    .replace(/<!--[\s\S]*?-->/g, '');
  if (!html.includes('準備中')) {
    errors.push('signup.html: 決済が未実装である旨の明示（「準備中」）が必要です');
  }
  // カード情報の入力欄は、決済実装(SALES-2)まで置かない。
  // Stripe Checkout(ホスト型)を採用するため、自サイトにカード欄は最終的にも作らない。
  for (const re of [/name="card/i, /autocomplete="cc-/i, /カード番号/]) {
    if (re.test(html)) {
      errors.push(`signup.html: カード情報の入力欄は置けません（${re}）`);
    }
  }
}

// ---------- 結果 ----------
notes.push(`正規値: 初期設定費 ${CANONICAL.initial}円 / 月額 ${CANONICAL.monthly}円 / 追加 ${CANONICAL.additional}円（税抜）`);
notes.push(`計算例: 1人 ${EXAMPLES.ex1}円 / 3人 ${EXAMPLES.ex3}円 / 5人 ${EXAMPLES.ex5}円（月額のみ・税抜）`);

notes.forEach((n) => console.log('  ' + n));

if (errors.length) {
  console.log('\n[NG] ' + errors.length + '件の不整合');
  errors.forEach((e) => console.log('  - ' + e));
  process.exit(1);
}
console.log('\n[OK] 料金・共通部品ともに整合しています');
