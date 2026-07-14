// Smart Labo Works — 問い合わせフォーム 入力値検証(サーバー側)
// クライアント側(js/pages.js)の検証は信頼せず、ここで全項目を再検証する。

const FIELD_LIMITS = {
  type: { required: false, enum: ["consult", "quote", "brochure", "demo", "other", ""] },
  company: { required: true, maxLength: 100 },
  name: { required: true, maxLength: 50 },
  email: { required: true, maxLength: 254 },
  tel: { required: false, maxLength: 20 },
  industry: {
    required: true,
    enum: ["realestate_sales", "realestate_management", "legal", "tax", "construction", "other"]
  },
  employees: { required: false, enum: ["1-10", "11-50", "51-200", "201+", ""] },
  message: { required: true, maxLength: 2000 },
  demo: { required: false, type: "boolean" },
  consent: { required: true, type: "boolean", mustBeTrue: true }
};

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function validateContactPayload(body) {
  const errors = [];
  const clean = {};

  if (!body || typeof body !== "object") {
    return { valid: false, errors: ["invalid_payload"], data: null };
  }

  Object.keys(FIELD_LIMITS).forEach(function (key) {
    const rule = FIELD_LIMITS[key];
    let value = body[key];

    if (rule.type === "boolean") {
      value = value === true || value === "true";
      if (rule.mustBeTrue && value !== true) {
        errors.push(key + "_required");
      }
      clean[key] = value;
      return;
    }

    value = typeof value === "string" ? value.trim() : "";

    if (rule.required && !value) {
      errors.push(key + "_required");
    }
    if (value && rule.maxLength && value.length > rule.maxLength) {
      errors.push(key + "_too_long");
      value = value.slice(0, rule.maxLength);
    }
    if (rule.enum && value && rule.enum.indexOf(value) === -1) {
      errors.push(key + "_invalid");
    }
    if (key === "email" && value && !EMAIL_PATTERN.test(value)) {
      errors.push("email_invalid");
    }

    clean[key] = value;
  });

  // Configurator連携(任意項目、選択内容の要約表示のみに使う。厳密なenum検証は行わない)
  clean.configuratorContext =
    typeof body.configuratorContext === "string" ? body.configuratorContext.slice(0, 500) : "";

  return { valid: errors.length === 0, errors: errors, data: clean };
}

module.exports = { validateContactPayload: validateContactPayload, FIELD_LIMITS: FIELD_LIMITS };
