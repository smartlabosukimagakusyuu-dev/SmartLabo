/**
 * ご案内リンクからの入口（SSOT v1.3 §4.2 / §4.5-A）。
 *
 * 手順:
 *   1. # より後ろをメモリへ読み取る
 *   2. すぐ URL から消す（history.replaceState）
 *   3. 形式（43文字）を確かめる
 *   4. 受付へ送って、継続用の Cookie を受け取る
 *   5. 成功したら入力画面へ移す（履歴に残さない）
 *   6. 失敗したら、理由を区別しない固定の案内を出す（SSOT §4.6）
 *
 * ★読み取った文字列を、保存・表示・console 出力・外部送信のいずれもしない。
 */

import { ApiClient, OUTCOME } from './lib/api.js';
import { takeKeyFromFragment } from './lib/entry.js';
import { el, replace, show } from './lib/dom.js';

const UNAVAILABLE = 'このURLは使用できません。お手数ですが、担当者までご連絡ください。';

const messageNode = document.getElementById('message');
const actionsNode = document.getElementById('actions');

function say(text) {
  messageNode.textContent = text;
}

function offerRetry() {
  replace(
    actionsNode,
    el('button', {
      type: 'button',
      class: 'btn btn--outline',
      text: 'もう一度試す',
      onClick: () => window.location.reload(),
    }),
  );
  show(actionsNode, true);
}

async function main() {
  // 1〜3. 読み取り → URL から即時削除 → 形式確認
  const key = takeKeyFromFragment(window);

  if (key === null) {
    say(UNAVAILABLE);

    return;
  }

  say('確認しています。少しお待ちください。');

  // 4. 受付へ送る。★この呼び出しが、値を渡す唯一の場所
  const api = new ApiClient();
  const result = await api.post('/session/start', { token: key });

  if (result.outcome === OUTCOME.OK) {
    // 5. 入力画面へ。戻る操作でこの画面へ戻らないよう replace で移す
    window.location.replace('/form');

    return;
  }

  if (result.outcome === OUTCOME.OFFLINE) {
    say('通信できませんでした。電波の状態をご確認のうえ、もう一度お試しください。');
    offerRetry();

    return;
  }

  // 6. 理由を区別しない固定の案内（存在の有無を推測させない）
  if (result.status === 429) {
    say(result.message);
    offerRetry();

    return;
  }
  say(result.status === 404 ? UNAVAILABLE : result.message);
}

void main();
