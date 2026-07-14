// Smart Labo Works — 問い合わせフォーム ログ(個人情報を含めない)

const crypto = require("crypto");

function hashIp(ip) {
  return crypto.createHash("sha256").update(String(ip)).digest("hex").slice(0, 16);
}

function logSuccess(ip, receiptId, type) {
  console.log(JSON.stringify({
    level: "info",
    event: "contact_submitted",
    ipHash: hashIp(ip),
    receiptId: receiptId,
    type: type,
    at: new Date().toISOString()
  }));
}

function logFailure(ip, reason, detail) {
  console.warn(JSON.stringify({
    level: "warn",
    event: "contact_rejected",
    ipHash: hashIp(ip),
    reason: reason,
    detail: detail || undefined,
    at: new Date().toISOString()
  }));
}

function logError(ip, error) {
  console.error(JSON.stringify({
    level: "error",
    event: "contact_error",
    ipHash: ip ? hashIp(ip) : undefined,
    message: error && error.message,
    at: new Date().toISOString()
  }));
}

module.exports = { logSuccess: logSuccess, logFailure: logFailure, logError: logError };
