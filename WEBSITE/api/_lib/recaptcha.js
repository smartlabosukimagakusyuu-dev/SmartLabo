// Smart Labo Works — Google reCAPTCHA v3 検証
// CEOによるreCAPTCHA管理画面でのサイト登録・シークレットキー発行が前提。

const RECAPTCHA_VERIFY_URL = "https://www.google.com/recaptcha/api/siteverify";
const SCORE_THRESHOLD = 0.5;

async function verifyRecaptcha(token, remoteIp) {
  const secret = process.env.RECAPTCHA_SECRET_KEY;
  if (!secret) {
    // シークレットキー未設定の開発環境向けフォールバック。
    // 本番運用時はRECAPTCHA_SECRET_KEYを必ず設定すること。
    return { success: false, reason: "recaptcha_not_configured" };
  }
  if (!token) {
    return { success: false, reason: "recaptcha_token_missing" };
  }

  const params = new URLSearchParams();
  params.set("secret", secret);
  params.set("response", token);
  if (remoteIp) params.set("remoteip", remoteIp);

  const res = await fetch(RECAPTCHA_VERIFY_URL, {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: params.toString()
  });
  const data = await res.json();

  if (!data.success) {
    return { success: false, reason: "recaptcha_rejected", errorCodes: data["error-codes"] };
  }
  if (typeof data.score === "number" && data.score < SCORE_THRESHOLD) {
    return { success: false, reason: "recaptcha_low_score", score: data.score };
  }
  return { success: true, score: data.score };
}

module.exports = { verifyRecaptcha: verifyRecaptcha, SCORE_THRESHOLD: SCORE_THRESHOLD };
