/**
 * ご案内リンクからの入口（SSOT v1.3 §4.2 / §4.7）。
 *
 * `/start#<43文字>` の **# より後ろ**を一度だけ読み、すぐ URL から消す。
 *
 * 絶対に守ること:
 *   - localStorage / sessionStorage / Cookie へ書かない
 *   - query や path へ移さない
 *   - console へ出さない
 *   - 画面（DOM）へ出さない
 *   - エラー監視・外部へ送らない
 *   - 変数として持つのは「送信するまでの一瞬」だけ
 */

/** ご案内リンクに載る文字列の形（base64url 43文字） */
const LINK_KEY = /^[A-Za-z0-9_-]{43}$/;

export function isWellFormedKey(value) {
  return typeof value === 'string' && LINK_KEY.test(value);
}

/**
 * fragment を読み取り、**同時に URL から消す**。
 *
 * 消してから検証する。形式が違っても URL に残さないため。
 *
 * @param {{location:{hash:string,pathname:string,search:string}, history:{replaceState:Function}}} win
 * @returns {string|null} 形式の正しい文字列。無ければ null
 */
export function takeKeyFromFragment(win) {
  const hash = String((win.location && win.location.hash) || '');
  const raw = hash.startsWith('#') ? hash.slice(1) : hash;

  // 1. まず URL から消す（履歴にも残さない）
  clearFragment(win);

  // 2. そのあとで形式を見る
  return isWellFormedKey(raw) ? raw : null;
}

/** URL の # より後ろを消す。query や path へは移さない */
export function clearFragment(win) {
  if (!win.history || typeof win.history.replaceState !== 'function') {
    return;
  }
  const path = String((win.location && win.location.pathname) || '/');
  const search = String((win.location && win.location.search) || '');
  win.history.replaceState(null, '', path + search);
  if (win.location && typeof win.location.hash === 'string') {
    // jsdom 等、replaceState が hash を更新しない環境でも見た目を合わせる
    try {
      win.location.hash = '';
    } catch {
      /* 読み取り専用の環境では何もしない */
    }
  }
}
