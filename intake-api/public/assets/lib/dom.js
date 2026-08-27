/**
 * DOM の組み立て。
 *
 * ★利用者の入力を **HTML として解釈させない**（SSOT §10.3）。
 *   このファイルは innerHTML / outerHTML / insertAdjacentHTML / document.write を使わない。
 *   文字列は必ず textContent か、属性値としてだけ渡す。
 */

export function el(tag, props = {}, children = []) {
  const node = document.createElement(tag);

  for (const [key, value] of Object.entries(props)) {
    if (value === undefined || value === null || value === false) continue;

    if (key === 'class') {
      node.className = String(value);
    } else if (key === 'text') {
      node.textContent = String(value);
    } else if (key === 'dataset') {
      for (const [d, v] of Object.entries(value)) node.dataset[d] = String(v);
    } else if (key.startsWith('on') && typeof value === 'function') {
      node.addEventListener(key.slice(2).toLowerCase(), value);
    } else if (value === true) {
      node.setAttribute(key, '');
    } else {
      node.setAttribute(key, String(value));
    }
  }

  for (const child of Array.isArray(children) ? children : [children]) {
    if (child === null || child === undefined || child === false) continue;
    node.appendChild(typeof child === 'string' ? document.createTextNode(child) : child);
  }

  return node;
}

export function clear(node) {
  while (node.firstChild) node.removeChild(node.firstChild);
}

export function replace(node, children) {
  clear(node);
  for (const child of Array.isArray(children) ? children : [children]) {
    if (child === null || child === undefined || child === false) continue;
    node.appendChild(typeof child === 'string' ? document.createTextNode(child) : child);
  }
}

export function show(node, visible) {
  node.hidden = !visible;
}

/**
 * 外部リンクを安全に置く。
 * https のみ。それ以外は**リンクにせず、文字として**出す（SSOT §3.7 の検証）。
 */
export function safeLink(url, label) {
  const text = label === undefined ? String(url) : String(label);
  if (typeof url !== 'string' || !url.startsWith('https://')) {
    return document.createTextNode(text);
  }

  return el('a', {
    href: url,
    rel: 'noopener noreferrer',
    target: '_blank',
    text,
  });
}
