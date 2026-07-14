// Smart Labo Works — 問い合わせフォーム レート制限
//
// 【既知の制約】サーバーレス関数(Vercel Functions等)はリクエストごとに
// 新しい実行環境が割り当てられることがあり、このインメモリ実装は
// コールドスタート時にリセットされうる(制限を回避される余地がある)。
// reCAPTCHA v3・honeypot・送信速度トラップと合わせた多層防御の一部として
// 位置づけ、トラフィックが増えた場合はUpstash Redis等の外部ストアへの
// 置き換えを推奨する(要CEO判断・新規アカウント作成)。

const WINDOW_MS = 60 * 60 * 1000; // 1時間
const MAX_REQUESTS_PER_WINDOW = 5;
const DUPLICATE_WINDOW_MS = 10 * 60 * 1000; // 10分(同一内容の重複送信=二重送信防止)

const requestLog = new Map(); // key: ip, value: timestamp[]
const recentSubmissions = new Map(); // key: ip+contentHash, value: timestamp

function pruneOld(list, windowMs) {
  const cutoff = Date.now() - windowMs;
  return list.filter(function (ts) { return ts > cutoff; });
}

function checkRateLimit(ip) {
  const key = ip || "unknown";
  const existing = pruneOld(requestLog.get(key) || [], WINDOW_MS);
  if (existing.length >= MAX_REQUESTS_PER_WINDOW) {
    return { allowed: false, reason: "rate_limited" };
  }
  existing.push(Date.now());
  requestLog.set(key, existing);
  return { allowed: true };
}

function checkDuplicateSubmission(ip, contentHash) {
  const key = (ip || "unknown") + ":" + contentHash;
  const last = recentSubmissions.get(key);
  const now = Date.now();
  if (last && now - last < DUPLICATE_WINDOW_MS) {
    return { allowed: false, reason: "duplicate_submission" };
  }
  recentSubmissions.set(key, now);
  return { allowed: true };
}

function getClientIp(req) {
  const forwarded = req.headers["x-forwarded-for"];
  if (forwarded) return forwarded.split(",")[0].trim();
  return req.socket && req.socket.remoteAddress ? req.socket.remoteAddress : "unknown";
}

module.exports = {
  checkRateLimit: checkRateLimit,
  checkDuplicateSubmission: checkDuplicateSubmission,
  getClientIp: getClientIp
};
