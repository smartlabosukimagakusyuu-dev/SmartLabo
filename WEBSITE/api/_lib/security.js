// Smart Labo Works — 問い合わせフォーム セキュリティユーティリティ
// CSRF検証(ステートレス署名トークン)・Origin検証・honeypot・送信速度トラップ

const crypto = require("crypto");

const CSRF_TOKEN_MAX_AGE_MS = 2 * 60 * 60 * 1000; // 2時間(フォーム閲覧に時間がかかるケースを考慮)
const MIN_FILL_TIME_MS = 3000; // フォーム表示から3秒未満の送信はbot疑い

function getCsrfSecret() {
  const secret = process.env.CONTACT_CSRF_SECRET;
  if (!secret) {
    throw new Error("CONTACT_CSRF_SECRET is not set");
  }
  return secret;
}

function sign(payload) {
  return crypto.createHmac("sha256", getCsrfSecret()).update(payload).digest("hex");
}

// ステートレスなCSRFトークンを発行する。サーバー側にトークンを保存しないため
// 「使い捨て(1回限り)」の強制はできないが、有効期限(2時間)とOrigin検証を
// 組み合わせることで、問い合わせフォームというリスクレベルに見合った防御とする。
function issueCsrfToken() {
  const timestamp = Date.now().toString();
  const signature = sign(timestamp);
  return Buffer.from(timestamp + "." + signature).toString("base64");
}

// 署名は常にHMAC-SHA256の16進数64文字。長さ・文字種を厳密一致させないと、
// Buffer.from(x, 'hex')が不正な末尾を黙って切り捨てる挙動を悪用され、
// 末尾にゴミを付加しただけの改ざんトークンが正規トークンと同一視されてしまう。
const HEX64_PATTERN = /^[0-9a-f]{64}$/i;
const TIMESTAMP_PATTERN = /^\d+$/;

function verifyCsrfToken(token) {
  if (!token || typeof token !== "string") return false;
  let decoded;
  try {
    decoded = Buffer.from(token, "base64").toString("utf8");
  } catch (e) {
    return false;
  }
  const parts = decoded.split(".");
  if (parts.length !== 2) return false;
  const timestamp = parts[0];
  const signature = parts[1];

  if (!TIMESTAMP_PATTERN.test(timestamp) || !HEX64_PATTERN.test(signature)) {
    return false;
  }

  const expected = sign(timestamp);
  const sigBuf = Buffer.from(signature, "hex");
  const expBuf = Buffer.from(expected, "hex");
  if (sigBuf.length !== expBuf.length || !crypto.timingSafeEqual(sigBuf, expBuf)) {
    return false;
  }

  const age = Date.now() - Number(timestamp);
  return age >= 0 && age <= CSRF_TOKEN_MAX_AGE_MS;
}

// 許可オリジン(本番の正式ドメイン確定後、環境変数へ追加すること)
function isAllowedOrigin(originHeader) {
  const allowed = (process.env.CONTACT_ALLOWED_ORIGINS || "")
    .split(",")
    .map(function (s) { return s.trim(); })
    .filter(Boolean);
  if (allowed.length === 0) return false;
  return allowed.indexOf(originHeader) !== -1;
}

// honeypot: 人間には見えないフィールド。botが自動入力してくると値が入る。
function isHoneypotTriggered(body) {
  return !!(body && body.website);
}

// 送信速度トラップ: フォーム表示から極端に短時間での送信はbot疑い。
function isTooFast(body) {
  const renderedAt = Number(body && body.formRenderedAt);
  if (!renderedAt) return true; // フィールド自体がない送信も不正とみなす
  return Date.now() - renderedAt < MIN_FILL_TIME_MS;
}

module.exports = {
  issueCsrfToken: issueCsrfToken,
  verifyCsrfToken: verifyCsrfToken,
  isAllowedOrigin: isAllowedOrigin,
  isHoneypotTriggered: isHoneypotTriggered,
  isTooFast: isTooFast
};
