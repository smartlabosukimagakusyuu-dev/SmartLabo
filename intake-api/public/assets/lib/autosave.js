/**
 * 自動保存（SSOT v1.3 §6.1）。
 *
 * 契機は3つだけ:
 *   1. 最終変更から 30秒後
 *   2. ステップ移動時
 *   3. 手動保存ボタン
 *
 * 守ること:
 *   - debounce する（打鍵のたびに送らない）
 *   - 保存処理を**同時並行させない**（直列化する）
 *   - 保存中に増えた変更は、次の保存へ回す
 *   - 失敗しても**自動で再試行しない**（多重保存を防ぐ。SSOT §6.5）
 *   - 429 では自動再試行しない
 *   - 409 では利用者の入力でサーバーを上書きしない
 */

/** 最終変更から保存までの待ち時間（ミリ秒）。SSOT §12.1-8 で確定した運用値 */
export const AUTOSAVE_DELAY_MS = 30000;

export const SAVE_REASON = {
  IDLE: 'idle',
  STEP: 'step',
  MANUAL: 'manual',
};

export class AutoSaver {
  #save;
  #onState;
  #timer = null;
  #inFlight = null;
  #queuedReason = null;
  #queuedDeferred = null;
  #delay;
  #setTimeout;
  #clearTimeout;

  /**
   * @param {(reason:string)=>Promise<object>} save 実際に保存を行う関数
   * @param {object} opts onState / delay / タイマー（テストから差し替える）
   */
  constructor(save, opts = {}) {
    this.#save = save;
    this.#onState = opts.onState || (() => {});
    this.#delay = opts.delay === undefined ? AUTOSAVE_DELAY_MS : opts.delay;
    this.#setTimeout = opts.setTimeout || ((fn, ms) => setTimeout(fn, ms));
    this.#clearTimeout = opts.clearTimeout || ((id) => clearTimeout(id));
  }

  get isSaving() {
    return this.#inFlight !== null;
  }

  get isScheduled() {
    return this.#timer !== null;
  }

  /** 入力が変わった。最終変更から数え直す */
  touch() {
    this.cancelScheduled();
    this.#timer = this.#setTimeout(() => {
      this.#timer = null;
      void this.run(SAVE_REASON.IDLE);
    }, this.#delay);
  }

  cancelScheduled() {
    if (this.#timer !== null) {
      this.#clearTimeout(this.#timer);
      this.#timer = null;
    }
  }

  /**
   * 保存する。
   *
   * すでに保存中なら、**終わってから1回だけ**追いかけて保存する
   * （保存中に増えた変更を落とさないため。並行しては送らない）。
   * 保存中に何回呼ばれても、追いかけの保存は**1回にまとまる**。
   *
   * ★戻り値の約束: 待っている側は「自分の変更が反映された保存」の結果を受け取る。
   *   保存中に呼んだ側は、**追いかけの保存**が終わってから解決する。
   */
  async run(reason) {
    this.cancelScheduled();

    if (this.#inFlight !== null) {
      this.#queuedReason = reason;
      if (this.#queuedDeferred === null) {
        let resolve;
        let reject;
        const promise = new Promise((res, rej) => {
          resolve = res;
          reject = rej;
        });
        this.#queuedDeferred = { promise, resolve, reject };
      }

      return this.#queuedDeferred.promise;
    }

    this.#onState({ saving: true, reason });
    const task = (async () => {
      try {
        return await this.#save(reason);
      } finally {
        this.#inFlight = null;
        this.#onState({ saving: false, reason });
      }
    })();
    this.#inFlight = task;

    let result;
    let error = null;
    try {
      result = await task;
    } catch (e) {
      error = e;
    }

    // 待っている要求があれば、ここで1回だけ追いかける
    const waiting = this.#queuedDeferred;
    const nextReason = this.#queuedReason;
    this.#queuedDeferred = null;
    this.#queuedReason = null;
    if (waiting !== null) {
      this.run(nextReason).then(waiting.resolve, waiting.reject);
    }

    if (error !== null) {
      throw error;
    }

    return result;
  }

  /** 画面を離れる・終了する。予約を捨てる（勝手に保存しない） */
  dispose() {
    this.cancelScheduled();
    if (this.#queuedDeferred !== null) {
      this.#queuedDeferred.resolve({ ok: false, skipped: true });
      this.#queuedDeferred = null;
    }
    this.#queuedReason = null;
  }
}
