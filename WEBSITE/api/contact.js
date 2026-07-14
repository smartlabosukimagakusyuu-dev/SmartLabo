// POST /api/contact — 問い合わせフォーム送信処理
//
// 処理順序: メソッド確認 → Origin確認 → honeypot/送信速度トラップ(静かに拒否)
// → CSRF検証 → レート制限/二重送信検知 → reCAPTCHA検証 → 入力値検証
// → 管理者通知メール(必須) → 自動返信メール(ベストエフォート) → 受付番号を返す

const { validateContactPayload } = require("./_lib/validate");
const { verifyCsrfToken, isAllowedOrigin, isHoneypotTriggered, isTooFast } = require("./_lib/security");
const { checkRateLimit, checkDuplicateSubmission, getClientIp } = require("./_lib/rateLimiter");
const { verifyRecaptcha } = require("./_lib/recaptcha");
const { sendAdminNotification, sendAutoReply } = require("./_lib/mailer");
const { generateReceiptId } = require("./_lib/receiptId");
const { logSuccess, logFailure, logError } = require("./_lib/log");
const crypto = require("crypto");

function contentHashOf(data) {
  return crypto
    .createHash("sha256")
    .update([data.company, data.email, data.message].join("|"))
    .digest("hex");
}

function fakeSuccessResponse(res) {
  // honeypot/timing trap検知時は、botへ判定基準を悟らせないため
  // 正常送信と見分けのつかないレスポンスを返す(実際にはメール送信しない)。
  res.status(200).json({ ok: true, receiptId: generateReceiptId(), receivedAt: new Date().toISOString() });
}

module.exports = async function handler(req, res) {
  const ip = getClientIp(req);

  if (req.method !== "POST") {
    res.status(405).json({ ok: false, error: "method_not_allowed" });
    return;
  }

  const origin = req.headers.origin;
  if (!isAllowedOrigin(origin)) {
    logFailure(ip, "origin_rejected", origin);
    res.status(403).json({ ok: false, error: "origin_rejected" });
    return;
  }

  const body = req.body || {};

  if (isHoneypotTriggered(body) || isTooFast(body)) {
    logFailure(ip, "bot_suspected");
    fakeSuccessResponse(res);
    return;
  }

  if (!verifyCsrfToken(body.csrfToken)) {
    logFailure(ip, "csrf_invalid");
    res.status(403).json({ ok: false, error: "csrf_invalid" });
    return;
  }

  const rateResult = checkRateLimit(ip);
  if (!rateResult.allowed) {
    logFailure(ip, "rate_limited");
    res.status(429).json({ ok: false, error: "rate_limited" });
    return;
  }

  const validation = validateContactPayload(body);
  if (!validation.valid) {
    logFailure(ip, "validation_error", validation.errors.join(","));
    res.status(400).json({ ok: false, error: "validation_error", fields: validation.errors });
    return;
  }
  const data = validation.data;

  const duplicateResult = checkDuplicateSubmission(ip, contentHashOf(data));
  if (!duplicateResult.allowed) {
    logFailure(ip, "duplicate_submission");
    res.status(429).json({ ok: false, error: "duplicate_submission" });
    return;
  }

  const recaptchaResult = await verifyRecaptcha(body.recaptchaToken, ip);
  if (!recaptchaResult.success) {
    logFailure(ip, "captcha_failed", recaptchaResult.reason);
    res.status(403).json({ ok: false, error: "captcha_failed" });
    return;
  }

  const receiptId = generateReceiptId();

  try {
    await sendAdminNotification(data, receiptId);
  } catch (err) {
    logError(ip, err);
    res.status(500).json({ ok: false, error: "server_error" });
    return;
  }

  try {
    await sendAutoReply(data, receiptId);
  } catch (err) {
    // 自動返信の失敗は致命的ではない(管理者へは既に通知済み)ため、ログのみ記録し成功として返す
    logError(ip, err);
  }

  logSuccess(ip, receiptId, data.type);
  res.status(200).json({ ok: true, receiptId: receiptId, receivedAt: new Date().toISOString() });
};
