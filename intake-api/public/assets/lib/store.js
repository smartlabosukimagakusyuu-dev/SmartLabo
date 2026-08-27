/**
 * 入力内容の保持と、変更された分類の追跡（SSOT v1.3 §6.1 / §6.3）。
 *
 *  - 回答本文を localStorage / sessionStorage へ書かない（SSOT §6.6）。**メモリだけ**で持つ
 *  - 保存要求へ載せるのは「**変更された分類だけ**」
 *  - 楽観ロックの基準は version。保存成功のたびに最新値へ更新する
 */

import { SECTION_KEYS } from './schema.js';

export class Store {
  #sections = {};
  #dirty = new Set();
  #version = 1;
  #status = 'draft';
  #caseNumber = '';
  #contractType = 'standalone';
  #driveConfirmed = false;
  #revisionRequests = [];
  #drive = { folder_url: null, folder_label: null, shared_email: null };
  #listeners = new Set();

  /** GET /case の応答を取り込む（既存入力の復元） */
  load(payload) {
    this.#sections = {};
    for (const key of SECTION_KEYS) {
      const value = payload.sections ? payload.sections[key] : undefined;
      this.#sections[key] = value === undefined || value === null ? emptyFor(key) : value;
    }
    this.#version = typeof payload.version === 'number' ? payload.version : 1;
    this.#status = String(payload.status || 'draft');
    this.#caseNumber = String(payload.case_number || '');
    this.#contractType = String(payload.contract_type || 'standalone');
    this.#driveConfirmed = payload.drive_confirmed === true;
    // ★いま対応が必要な修正依頼だけ（サーバーが open のものしか返さない）
    this.#revisionRequests = Array.isArray(payload.revision_requests) ? payload.revision_requests : [];
    // ★素材フォルダの案内。**メモリだけ**で持ち、保存領域へ書かない
    this.#drive = payload.drive && typeof payload.drive === 'object'
      ? payload.drive
      : { folder_url: null, folder_label: null, shared_email: null };
    this.#dirty.clear();
    this.#emit();
  }

  get version() {
    return this.#version;
  }
  get status() {
    return this.#status;
  }
  get caseNumber() {
    return this.#caseNumber;
  }
  get contractType() {
    return this.#contractType;
  }
  get driveConfirmed() {
    return this.#driveConfirmed;
  }
  get revisionRequests() {
    return this.#revisionRequests;
  }
  get drive() {
    return this.#drive;
  }
  get hasRevisionRequest() {
    return this.#revisionRequests.length > 0;
  }
  get isEditable() {
    return this.#status === 'draft' || this.#status === 'needs_revision';
  }
  get hasUnsavedChanges() {
    return this.#dirty.size > 0;
  }
  get dirtySections() {
    return [...this.#dirty];
  }

  section(key) {
    return this.#sections[key];
  }

  /** 分類まるごとを置き換える（変更あつかい） */
  setSection(key, value) {
    if (!SECTION_KEYS.includes(key)) {
      throw new Error('unknown_section');
    }
    this.#sections[key] = value;
    this.#dirty.add(key);
    this.#emit();
  }

  /** 変更された分類だけを取り出す（保存要求の本体） */
  changedPayload() {
    const out = {};
    for (const key of this.#dirty) {
      out[key] = this.#sections[key];
    }

    return out;
  }

  /**
   * 保存が成功した。
   * ★保存要求を出した後に増えた変更を消さないよう、**送った分類だけ**をきれいにする。
   */
  markSaved(savedKeys, nextVersion) {
    for (const key of savedKeys) {
      this.#dirty.delete(key);
    }
    if (typeof nextVersion === 'number') {
      this.#version = nextVersion;
    }
    this.#emit();
  }

  /** 409 のあとに最新を読み直した場合など、version だけを合わせる */
  setVersion(next) {
    this.#version = next;
    this.#emit();
  }

  setStatus(next) {
    this.#status = next;
    this.#emit();
  }

  /** 素材アップロードの完了申告が済んだ（取消は無い） */
  markDriveConfirmed() {
    this.#driveConfirmed = true;
    this.#emit();
  }

  /** ログアウト・終了時にメモリ上の入力内容を捨てる */
  clear() {
    this.#sections = {};
    this.#dirty.clear();
    this.#version = 1;
    this.#status = 'closed';
    this.#caseNumber = '';
    // ★素材フォルダのURL・共有先メール・修正依頼も手元から捨てる
    this.#revisionRequests = [];
    this.#drive = { folder_url: null, folder_label: null, shared_email: null };
    this.#emit();
  }

  subscribe(fn) {
    this.#listeners.add(fn);

    return () => this.#listeners.delete(fn);
  }

  #emit() {
    for (const fn of this.#listeners) fn(this);
  }
}

/** 分類ごとの空値（SSOT §3.0-4） */
export function emptyFor(key) {
  return key === 'menus' || key === 'staff' || key === 'image_metadata' ? [] : {};
}
