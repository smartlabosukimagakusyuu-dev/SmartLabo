/**
 * テスト用の最小 DOM（外部ライブラリを使わない方針のため自前で用意する）。
 *
 * ★意図的に **innerHTML を実装しない**。
 *   画面側のコードが innerHTML を使っていたら、ここで必ず落ちる。
 *   「HTMLとして解釈しない」ことを、規約ではなく**動作**で守らせるための仕掛けである。
 */

class ClassList {
  constructor(node) {
    this.node = node;
  }

  get set() {
    return new Set(String(this.node.className || '').split(/\s+/).filter(Boolean));
  }

  add(...names) {
    const s = this.set;
    for (const n of names) s.add(n);
    this.node.className = [...s].join(' ');
  }

  remove(...names) {
    const s = this.set;
    for (const n of names) s.delete(n);
    this.node.className = [...s].join(' ');
  }

  contains(name) {
    return this.set.has(name);
  }
}

class Node {
  constructor(type) {
    this.nodeType = type;
    this.childNodes = [];
    this.parentNode = null;
  }

  appendChild(child) {
    if (child instanceof Fragment) {
      for (const c of [...child.childNodes]) this.appendChild(c);

      return child;
    }
    if (child.parentNode) child.parentNode.removeChild(child);
    child.parentNode = this;
    this.childNodes.push(child);

    return child;
  }

  removeChild(child) {
    const i = this.childNodes.indexOf(child);
    if (i >= 0) {
      this.childNodes.splice(i, 1);
      child.parentNode = null;
    }

    return child;
  }

  get firstChild() {
    return this.childNodes[0] || null;
  }

  get textContent() {
    return this.childNodes.map((c) => c.textContent).join('');
  }

  set textContent(value) {
    this.childNodes = [];
    if (value !== '' && value !== null && value !== undefined) {
      this.appendChild(new Text(String(value)));
    }
  }

  /** 全子孫（自分を含む） */
  walk() {
    const out = [this];
    for (const c of this.childNodes) {
      if (c.walk) out.push(...c.walk());
      else out.push(c);
    }

    return out;
  }
}

class Text extends Node {
  constructor(data) {
    super(3);
    this.data = String(data);
  }

  get textContent() {
    return this.data;
  }

  set textContent(v) {
    this.data = String(v);
  }

  walk() {
    return [this];
  }
}

class Fragment extends Node {
  constructor() {
    super(11);
  }
}

class Element extends Node {
  #listeners = new Map();

  constructor(tagName) {
    super(1);
    this.tagName = String(tagName).toUpperCase();
    this.attributes = new Map();
    this.dataset = {};
    this.style = {};
    this.className = '';
    this.classList = new ClassList(this);
    this.hidden = false;
    this.disabled = false;
    this.checked = false;
    this.value = '';
  }

  // ★innerHTML を実装しない（使われたら落ちる）
  set innerHTML(_v) {
    throw new Error('innerHTML は使用禁止（利用者入力を HTML として解釈しない）');
  }

  get innerHTML() {
    throw new Error('innerHTML は使用禁止（利用者入力を HTML として解釈しない）');
  }

  insertAdjacentHTML() {
    throw new Error('insertAdjacentHTML は使用禁止');
  }

  setAttribute(name, value) {
    const key = String(name);
    this.attributes.set(key, String(value));

    // 実際のブラウザと同じく、既定の属性はプロパティへ反映させる
    if (key === 'disabled' || key === 'hidden' || key === 'checked') {
      this[key] = true;
    } else if (key === 'value') {
      this.value = String(value);
    }
  }

  getAttribute(name) {
    return this.attributes.has(String(name)) ? this.attributes.get(String(name)) : null;
  }

  hasAttribute(name) {
    return this.attributes.has(String(name));
  }

  get id() {
    return this.getAttribute('id') || '';
  }

  set id(v) {
    this.setAttribute('id', v);
  }

  addEventListener(type, fn) {
    if (!this.#listeners.has(type)) this.#listeners.set(type, []);
    this.#listeners.get(type).push(fn);
  }

  /** テストから発火させる */
  dispatch(type, event = {}) {
    for (const fn of this.#listeners.get(type) || []) fn({ type, target: this, ...event });
  }

  get listenerTypes() {
    return [...this.#listeners.keys()];
  }

  closest(selector) {
    const want = selector.startsWith('.') ? selector.slice(1) : null;
    let cur = this;
    while (cur) {
      if (want && cur.classList && cur.classList.contains(want)) return cur;
      cur = cur.parentNode;
    }

    return null;
  }

  focus() {
    document.activeElement = this;
  }

  scrollIntoView() {}

  querySelectorAll(selector) {
    const want = selector.startsWith('.') ? selector.slice(1) : null;
    const tag = want ? null : selector.toUpperCase();

    return this.walk().filter(
      (n) =>
        n instanceof Element
        && (want ? n.classList.contains(want) : n.tagName === tag),
    );
  }

  querySelector(selector) {
    return this.querySelectorAll(selector)[0] || null;
  }
}

class Document extends Node {
  constructor() {
    super(9);
    this.activeElement = null;
    this.root = new Element('body');
    this.appendChild(this.root);
  }

  createElement(tag) {
    return new Element(tag);
  }

  createTextNode(text) {
    return new Text(text);
  }

  createDocumentFragment() {
    return new Fragment();
  }

  getElementById(id) {
    return this.walk().find((n) => n instanceof Element && n.id === id) || null;
  }
}

/** テストごとに新しい document / window を用意する */
export function installDom() {
  const document = new Document();
  const window = {
    document,
    scrollTo() {},
    confirm: () => true,
    location: { pathname: '/form', search: '', hash: '', replace() {}, reload() {} },
    history: { replaceState() {} },
    addEventListener() {},
  };

  globalThis.document = document;
  globalThis.window = window;
  globalThis.Node = Node;
  globalThis.Element = Element;

  return { document, window, body: document.root };
}

export { Element, Fragment, Node, Text };
