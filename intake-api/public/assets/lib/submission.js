/**
 * 提出の冪等化キー（SSOT v1.3 §6.4）。
 *
 * 生成契約:
 *   1. 利用者が「提出する」を押すたびに、新しい UUID v4 を生成する
 *   2. 同じ値を送ってよいのは、**同じ提出処理の通信障害による再試行のときだけ**
 *   3. 検証エラーを直してからの送り直しは「新しい提出要求」なので、新しい値を作る
 *   4. 値を localStorage / sessionStorage / URL / DOM / console へ出さない
 *
 * ★進行中の提出は**メモリだけ**で管理する。
 *   ページを再読み込みしたら、古い値は復元しない（2 の「同じ処理」ではなくなるため）。
 */

const UUID_V4 = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;

export function isUuidV4(value) {
  return typeof value === 'string' && UUID_V4.test(value);
}

/**
 * UUID v4 を作る。crypto.randomUUID() を第一候補にし、
 * 未対応の場合だけ Web Crypto の乱数から組み立てる（Math.random は使わない）。
 */
export function newSubmissionId(cryptoObj) {
  const c = cryptoObj || (typeof globalThis !== 'undefined' ? globalThis.crypto : undefined);
  if (!c || typeof c.getRandomValues !== 'function') {
    // 安全な乱数が無い環境では作らない。呼び出し側で提出を止める
    throw new Error('secure_random_unavailable');
  }
  if (typeof c.randomUUID === 'function') {
    return c.randomUUID();
  }

  const b = new Uint8Array(16);
  c.getRandomValues(b);
  b[6] = (b[6] & 0x0f) | 0x40; // version 4
  b[8] = (b[8] & 0x3f) | 0x80; // variant 10xx

  const hex = [];
  for (let i = 0; i < 16; i += 1) hex.push(b[i].toString(16).padStart(2, '0'));
  const s = hex.join('');

  return [s.slice(0, 8), s.slice(8, 12), s.slice(12, 16), s.slice(16, 20), s.slice(20)].join('-');
}

/**
 * 進行中の提出操作を管理する。
 *
 * 「再試行してよい要求」だけを保持し、それ以外は必ず捨てる。
 * 捨てた状態で next() を呼ぶと、新しい UUID v4 になる。
 */
export class SubmissionAttempt {
  #pending = null;
  #generate;

  constructor(generate = newSubmissionId) {
    this.#generate = generate;
  }

  /** 再試行できる要求を保持しているか（テストと画面表示のためだけに公開する） */
  get hasPending() {
    return this.#pending !== null;
  }

  /**
   * 利用者が「提出する」を明示的に押した。
   * 前の要求は引き継がない ＝ 次の next() は**必ず新しい UUID v4** になる。
   * （SSOT v1.3 §6.4 の生成契約 1）
   */
  startNew() {
    this.#pending = null;
  }

  /**
   * これから送る submission_id を決める。
   * 保持している要求があれば**それを再利用**する（＝同じ提出処理の再試行）。
   */
  next() {
    if (this.#pending === null) {
      this.#pending = this.#generate();
    }

    return this.#pending;
  }

  /** 応答が届かなかった。同じ値で再試行してよい */
  keepForRetry() {
    return this.#pending;
  }

  /** 提出が成立した。もう使わない */
  succeeded() {
    this.#pending = null;
  }

  /**
   * 検証エラーだった。利用者が直してから送り直すのは**新しい提出要求**である。
   * （SSOT v1.3 §6.4 の生成契約 3）
   */
  rejectedByValidation() {
    this.#pending = null;
  }

  /** すでに提出済みだった（409）。自動再送しない。値も捨てる */
  alreadySubmitted() {
    this.#pending = null;
  }

  /** 入力の作り方が不正（400）。作り直す */
  discarded() {
    this.#pending = null;
  }

  /**
   * 短時間に集中した（429）。
   * サーバーは受理していないので、利用者が押し直したときは同じ値でよい。
   * ★自動では再送しない。押し直しは利用者の操作である。
   */
  rateLimited() {
    return this.#pending;
  }
}
