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
 * privacy.html の「6. Cookie・アクセス解析について」だけは扱いが違う（WEB-V3-5A-1）
 *   GA4／Google Tag Manager の利用開示へ正式に改定したため、この1節は
 *   Version1 と一致しない。ただし「検査しない」のではなく、**基準を差し替える**。
 *     ・§6      … 下の PRIVACY_SECTION6（代表承認済み文面）と完全一致すること
 *     ・改定日   … 下の PRIVACY_LEGAL_META と完全一致すること
 *     ・それ以外 … 従来どおり Version1 と完全一致すること（凍結を維持）
 *     ・terms.html … 一切変更なし。従来どおり Version1 と完全一致
 *   これにより「§6は意図した改定」「他の法務文言の事故は今までどおり検知」を
 *   両立させる。§6を検査対象から外すだけの実装にはしないこと（事故が素通りする）。
 *
 *   §6 を将来さらに改定するときは、HTMLと PRIVACY_SECTION6 の両方を更新する。
 *   片方だけ変えるとこの検査が落ちる（＝承認なしの書き換えを止められる）。
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
// WEB-V3-1: 比較対象を Version 3 のフォルダへ切り替えた(変数名は据え置き)
const V2 = path.resolve(__dirname, '../../../website-v3');

const PAGES = [
  { file: 'privacy.html', crumb: 'プライバシーポリシー' },
  { file: 'terms.html',   crumb: '利用規約' },
];

/* ------------------------------------------------------------------------
   WEB-V3-5A-1（2026-08-08・代表承認）で改定した privacy.html §6 の正本。
   HTMLから取り出した「画面に出る文字列」と1行ずつ突き合わせる。
   リンク文字列と読み上げ用の補足（visually-hidden）も画面に出る文字列として
   数えるため、ここにも含めている。
   ------------------------------------------------------------------------ */
const SECTION6_HEAD = '6. Cookie・アクセス解析について';
const SECTION6_NEXT = '7. 安全管理措置';          // §6の終端を決めるための次の見出し

const PRIVACY_SECTION6 = [
  '6. Cookie・アクセス解析について',
  '当社は、本サイトの利用状況の把握、サイトの改善およびお問い合わせ経路の分析を目的として、Google LLCが提供するGoogle Analytics 4（GA4）を利用しています。また、これらの計測タグの管理にGoogle Tag Managerを利用しています。',
  'Google Analyticsでは、Cookie等を利用して、閲覧したページ、参照元、利用端末・ブラウザに関する情報等がGoogleへ送信される場合があります。',
  '当社は、お問い合わせフォームに入力された氏名、会社名、メールアドレス、電話番号、お問い合わせ本文等を、Google Analyticsの計測イベントとして送信しません。お問い合わせ種別など、個人を直接特定しない情報のみをサイト改善のための計測に利用する場合があります。',
  'Google Analyticsによる計測を希望しない場合は、ブラウザのCookie設定またはGoogleが提供する',
  'Google Analyticsオプトアウトブラウザアドオン',
  '（外部サイト・新しいタブで開きます）',
  'をご利用いただけます。',
  'Google Analyticsにおけるデータの取扱いについては、',
  'Googleのプライバシーポリシー',
  '（外部サイト・新しいタブで開きます）',
  '等をご確認ください。',
  'なお、当社が将来、広告効果測定等のために新たな外部サービスを導入する場合は、必要に応じて本ポリシーを更新します。',
];

/** §6の改定に合わせた privacy.html の改定日表示（本文末尾の1行） */
const PRIVACY_LEGAL_META = '制定日：[CEO確認待ち]　最終更新日：2026-08-08（アクセス解析の利用開始に伴う改定）';

/** §6で参照する外部リンク。href と安全属性の有無をHTMLのまま検査する */
const SECTION6_LINKS = [
  'https://tools.google.com/dlpage/gaoptout',
  'https://policies.google.com/privacy',
];

/**
 * 見出しstartから、次の見出しendの手前までの範囲を返す。
 * 見つからない場合は null（呼び出し側でエラーにする）。
 */
function sectionRange(lines, startText, endText) {
  const start = lines.indexOf(startText);
  if (start < 0) return null;
  const end = lines.indexOf(endText, start + 1);
  return { start, end: end < 0 ? lines.length : end };
}

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

  /* ---------- privacy.html §6 だけは「新しい基準」で検査する（WEB-V3-5A-1） ----------
     ここで v1/v2 の両方から §6 と改定日の行を取り除く。取り除く前に、
     v2側が承認済み文面と一致することを必ず確認する。確認せずに取り除くと
     「§6は何を書いても通る」状態になり、検査の意味が失われる。            */
  if (p.file === 'privacy.html') {
    // ① v2 の §6 が承認済み文面と完全一致するか
    const r2 = sectionRange(v2, SECTION6_HEAD, SECTION6_NEXT);
    if (!r2) {
      errors.push(`privacy.html: 「${SECTION6_HEAD}」の節が見つかりません`);
    } else {
      const got = v2.slice(r2.start, r2.end);
      if (got.length !== PRIVACY_SECTION6.length) {
        errors.push(`privacy.html §6: 行数が承認済み文面と違います（承認 ${PRIVACY_SECTION6.length}行 / HTML ${got.length}行）`);
      }
      for (let i = 0; i < Math.max(got.length, PRIVACY_SECTION6.length); i++) {
        if (got[i] !== PRIVACY_SECTION6[i]) {
          errors.push(`privacy.html §6: ${i + 1}行目が承認済み文面と不一致\n      承認: ${PRIVACY_SECTION6[i] ?? '(なし)'}\n      HTML: ${got[i] ?? '(なし)'}`);
          break;   // 1件示せば原因は分かるので、ここで打ち切る
        }
      }
      v2.splice(r2.start, r2.end - r2.start);   // 以降の凍結比較から外す
    }

    // ② v1（Version1）側の旧 §6 も同じ範囲で取り除く
    const r1 = sectionRange(v1, SECTION6_HEAD, SECTION6_NEXT);
    if (!r1) {
      errors.push(`privacy.html: Version1側に「${SECTION6_HEAD}」の節が見つかりません`);
    } else {
      v1.splice(r1.start, r1.end - r1.start);
    }

    // ③ 改定日表示。v2は新しい値と一致すること、v1は旧い値のまま取り除く
    const m2 = v2.findIndex((l) => l.startsWith('制定日：'));
    if (m2 < 0) {
      errors.push('privacy.html: 改定日の表示（制定日：…）が見つかりません');
    } else {
      if (v2[m2] !== PRIVACY_LEGAL_META) {
        errors.push(`privacy.html: 改定日が承認値と不一致\n      承認: ${PRIVACY_LEGAL_META}\n      HTML: ${v2[m2]}`);
      }
      v2.splice(m2, 1);
    }
    const m1 = v1.findIndex((l) => l.startsWith('制定日：'));
    if (m1 >= 0) v1.splice(m1, 1);

    // ④ 外部リンクは href と安全属性をHTMLのまま確認する
    //    （visibleText はタグを落とすため、可視テキストの比較では検出できない）
    const rawHtml = fs.readFileSync(path.join(V2, p.file), 'utf8');
    for (const href of SECTION6_LINKS) {
      const tag = rawHtml.match(new RegExp(`<a[^>]*href="${href.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}"[^>]*>`));
      if (!tag) {
        errors.push(`privacy.html §6: 外部リンク ${href} が見つかりません`);
        continue;
      }
      if (!/rel="[^"]*noopener[^"]*"/.test(tag[0]) || !/rel="[^"]*noreferrer[^"]*"/.test(tag[0])) {
        errors.push(`privacy.html §6: ${href} に rel="noopener noreferrer" がありません`);
      }
      if (!/target="_blank"/.test(tag[0])) {
        errors.push(`privacy.html §6: ${href} に target="_blank" がありません`);
      }
    }
  }

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
  const note = p.file === 'privacy.html' ? '（§6と改定日は承認済み文面で別途検査）' : '';
  console.log(`  ${p.file}: 凍結部分 ${v1.length}行 / ${chars}文字 → ${diff === 0 ? '完全一致' : diff + '行が不一致'}${note}`);
}

if (errors.length) {
  console.log('\n[NG] 法務ページの本文が基準と一致していません');
  errors.forEach((e) => console.log('  - ' + e));
  console.log('\n  法務文言はレイアウト調整で書き換えてはいけません。');
  console.log('  privacy.html §6 を意図的に改定する場合は、HTMLと本ファイルの');
  console.log('  PRIVACY_SECTION6 / PRIVACY_LEGAL_META を同時に更新すること。');
  process.exit(1);
}
console.log('\n[OK] 法務ページの本文は基準と完全に一致しています');
console.log('     （privacy.html §6 と改定日は承認済み文面と一致・他は Version1 と一致）');
