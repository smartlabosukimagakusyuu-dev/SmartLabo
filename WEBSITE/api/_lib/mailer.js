// Smart Labo Works — 問い合わせメール送信(nodemailer/SMTP)
//
// 送信元・送信先・SMTP接続情報はすべて環境変数で管理し、コードに直書きしない。
// CONTACT_NOTIFY_TO / CONTACT_FROM_EMAIL は2026-07-14時点で未確定(CEO確認待ち)
// のため、.env側で未設定の場合は明確なエラーとして扱う(推測で代替しない)。

const nodemailer = require("nodemailer");

function escapeHtml(str) {
  return String(str).replace(/[&<>"']/g, function (c) {
    return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
  });
}

const TYPE_LABEL = {
  consult: "無料相談",
  quote: "見積り依頼",
  brochure: "資料請求",
  demo: "デモ予約",
  other: "その他",
  "": "未選択"
};

function getTransport() {
  const host = process.env.SMTP_HOST;
  const port = Number(process.env.SMTP_PORT || 587);
  const user = process.env.SMTP_USER;
  const pass = process.env.SMTP_PASS;
  if (!host || !user || !pass) {
    throw new Error("SMTP settings are not fully configured (SMTP_HOST/SMTP_USER/SMTP_PASS)");
  }
  return nodemailer.createTransport({
    host: host,
    port: port,
    secure: port === 465,
    auth: { user: user, pass: pass }
  });
}

function requireEnv(name) {
  const value = process.env[name];
  if (!value) throw new Error(name + " is not set");
  return value;
}

async function sendAdminNotification(data, receiptId) {
  const transport = getTransport();
  const to = requireEnv("CONTACT_NOTIFY_TO");
  const from = requireEnv("CONTACT_FROM_EMAIL");

  const rows = [
    ["受付番号", receiptId],
    ["ご相談種別", TYPE_LABEL[data.type] || data.type],
    ["会社名", data.company],
    ["氏名", data.name],
    ["メールアドレス", data.email],
    ["電話番号", data.tel || "(未入力)"],
    ["業種", data.industry],
    ["社員数", data.employees || "(未入力)"],
    ["無料デモ希望", data.demo ? "希望する" : "希望しない"],
    ["Configurator選択内容", data.configuratorContext || "(なし)"]
  ];

  const htmlRows = rows
    .map(function (r) { return "<tr><th style=\"text-align:left;padding:4px 12px 4px 0\">" + escapeHtml(r[0]) + "</th><td>" + escapeHtml(r[1]) + "</td></tr>"; })
    .join("");

  await transport.sendMail({
    from: from,
    to: to,
    subject: "[Smart Labo Works] 新しいお問い合わせ（" + (TYPE_LABEL[data.type] || "お問い合わせ") + "）",
    html:
      "<table>" + htmlRows + "</table>" +
      "<p>相談内容:</p><p style=\"white-space:pre-wrap\">" + escapeHtml(data.message) + "</p>"
  });
}

async function sendAutoReply(data, receiptId) {
  const transport = getTransport();
  const from = requireEnv("CONTACT_FROM_EMAIL");

  await transport.sendMail({
    from: from,
    to: data.email,
    subject: "【Smart Labo Works】お問い合わせを受け付けました（受付番号: " + receiptId + "）",
    text:
      data.name + " 様\n\n" +
      "この度はSmart Labo Worksへお問い合わせいただき、誠にありがとうございます。\n" +
      "以下の内容で受け付けました。担当者より改めてご連絡いたします。\n\n" +
      "受付番号: " + receiptId + "\n" +
      "ご相談種別: " + (TYPE_LABEL[data.type] || data.type) + "\n\n" +
      "ご相談内容:\n" + data.message + "\n\n" +
      "--\n株式会社スマートラボ\nSmart Labo Works"
  });
}

module.exports = { sendAdminNotification: sendAdminNotification, sendAutoReply: sendAutoReply };
