/**
 * 受付APIの呼び出し（SSOT v1.3 §4.5 / §10）。
 *
 *  - Cookie を送るため credentials: 'same-origin' を必ず付ける
 *  - CORS を使わない（同一オリジンからのみ動く）
 *  - 応答本文の内部情報を画面へ出さない。使うのは error コードと固定文言だけ
 *  - 通信できなかった場合と、サーバーが答えた場合を**必ず区別**する
 *    （区別できないと、提出の再試行で同じ submission_id を使ってよいか判断できない）
 */

export const OUTCOME = {
  OK: 'ok', // 2xx
  FAILED: 'failed', // サーバーが答えた（4xx / 5xx）
  OFFLINE: 'offline', // 届かなかった・応答が読めなかった
};

/** 画面へ出してよい固定文言。API の message をそのまま信用して出す用途にも使う */
export const FALLBACK_MESSAGE = 'ただいま処理できません。時間をおいてお試しください。';

export class ApiClient {
  #fetch;
  #base;

  constructor(opts = {}) {
    this.#fetch = opts.fetch || ((...a) => globalThis.fetch(...a));
    this.#base = opts.base || '';
  }

  get(path) {
    return this.#send('GET', path, undefined);
  }

  post(path, body) {
    return this.#send('POST', path, body === undefined ? {} : body);
  }

  async #send(method, path, body) {
    const init = {
      method,
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { Accept: 'application/json' },
    };
    if (body !== undefined) {
      init.headers['Content-Type'] = 'application/json';
      init.body = JSON.stringify(body);
    }

    let response;
    try {
      response = await this.#fetch(this.#base + path, init);
    } catch {
      // ★ネットワーク層の失敗。サーバーが受理したかどうか**わからない**
      return { outcome: OUTCOME.OFFLINE, status: 0, error: 'offline', message: '', body: {} };
    }

    let payload = {};
    try {
      payload = await response.json();
    } catch {
      payload = {};
    }
    if (payload === null || typeof payload !== 'object') {
      payload = {};
    }

    if (response.ok && payload.ok === true) {
      return { outcome: OUTCOME.OK, status: response.status, error: '', message: '', body: payload };
    }

    return {
      outcome: OUTCOME.FAILED,
      status: response.status,
      error: typeof payload.error === 'string' ? payload.error : 'server_error',
      message: typeof payload.message === 'string' && payload.message !== '' ? payload.message : FALLBACK_MESSAGE,
      retryAfter: readRetryAfter(response),
      body: payload,
    };
  }
}

/** 429 の案内に使ってよい。★自動再試行には使わない */
export function readRetryAfter(response) {
  const raw = response && response.headers && typeof response.headers.get === 'function'
    ? response.headers.get('Retry-After')
    : null;
  if (raw === null || raw === undefined) {
    return null;
  }
  const seconds = Number.parseInt(String(raw), 10);

  return Number.isFinite(seconds) && seconds >= 0 ? seconds : null;
}
