// Smart Labo Works — 受付番号発行(SLW-YYYYMMDD-XXXX形式)

const crypto = require("crypto");

function generateReceiptId(date) {
  const d = date || new Date();
  const yyyy = d.getUTCFullYear();
  const mm = String(d.getUTCMonth() + 1).padStart(2, "0");
  const dd = String(d.getUTCDate()).padStart(2, "0");
  const random = crypto.randomBytes(3).toString("hex").toUpperCase().slice(0, 4);
  return "SLW-" + yyyy + mm + dd + "-" + random;
}

module.exports = { generateReceiptId: generateReceiptId };
