// GET /api/csrf-token — 問い合わせフォーム表示時にCSRFトークンを発行する

const { issueCsrfToken } = require("./_lib/security");

module.exports = async function handler(req, res) {
  if (req.method !== "GET") {
    res.status(405).json({ ok: false, error: "method_not_allowed" });
    return;
  }
  res.status(200).json({ ok: true, csrfToken: issueCsrfToken() });
};
