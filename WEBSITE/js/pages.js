// Smart Labo Works — 詳細ページ共通スクリプト(FAQアコーディオン・お問い合わせフォーム)
// (2026-07-12 CEO承認「Smart Labo Works 詳細ページ構築」)

(function () {
  "use strict";

  // FAQアコーディオン(product.html / pricing.html 等で共通利用)
  document.querySelectorAll(".faq-item__q").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var item = btn.closest(".faq-item");
      if (!item) return;
      var wasOpen = item.classList.contains("is-open");
      item.parentElement.querySelectorAll(".faq-item.is-open").forEach(function (el) {
        el.classList.remove("is-open");
      });
      if (!wasOpen) item.classList.add("is-open");
    });
  });

  // お問い合わせフォーム(contact.html)
  // 送信先: XServer上のPHPバックエンド(xserver-form/、別リポジトリ管理外の
  // form.smartlaboworks.com サブドメインへ配置)。DNS/SMTP設定完了前は
  // 通信エラーとなるため、失敗時は分かりやすいフォールバックメッセージを表示する。
  var form = document.getElementById("contactForm");
  if (!form) return;

  var CONTACT_API_BASE = "https://form.smartlaboworks.com";

  var successBox = document.getElementById("contactSuccess");
  var errorBanner = document.getElementById("contactErrorBanner");
  var submitBtn = document.getElementById("contactSubmitBtn");

  var formRenderedAt = Date.now();
  var csrfToken = null;

  function fetchCsrfToken() {
    fetch(CONTACT_API_BASE + "/csrf-token.php", { method: "GET" })
      .then(function (res) { return res.json(); })
      .then(function (data) { if (data && data.ok) csrfToken = data.csrfToken; })
      .catch(function () { /* 取得失敗時はcontact.php側でcsrf_invalidとして扱われ、送信時にエラー表示される */ });
  }
  fetchCsrfToken();

  var configuratorContext = "";

  // Smart Labo Configurator(pricing.html)からの選択内容引き継ぎ
  // クエリ例: contact.html?from=configurator&type=quote&scale=medium&plan=standard&modules=aiAssistant,crm&options=ocr
  (function prefillFromConfigurator() {
    var params = new URLSearchParams(window.location.search);
    if (params.get("from") !== "configurator") return;

    var SCALE_LABEL = { small: "Small（〜10名程度）", medium: "Medium（11〜50名程度）", enterprise: "Enterprise（51名以上・複数拠点）" };
    var PLAN_LABEL = { lite: "Lite", standard: "Standard", premium: "Premium" };
    var TYPE_LABEL = { consult: "無料相談", quote: "見積り依頼", brochure: "資料請求", demo: "デモ予約" };

    var type = params.get("type") || "";
    var scale = params.get("scale") || "";
    var plan = params.get("plan") || "";
    var modules = params.get("modules") ? params.get("modules").split(",") : [];
    var options = params.get("options") ? params.get("options").split(",") : [];

    var typeSelect = document.getElementById("cf-type");
    if (typeSelect && TYPE_LABEL[type]) typeSelect.value = type;

    var demoCheckbox = document.getElementById("cf-demo");
    if (demoCheckbox && type === "demo") demoCheckbox.checked = true;

    var lines = ["【Smart Labo Configuratorでの選択内容】"];
    if (TYPE_LABEL[type]) lines.push("ご相談種別：" + TYPE_LABEL[type]);
    lines.push("利用規模：" + (SCALE_LABEL[scale] || "未選択"));
    lines.push("基本プラン：" + (PLAN_LABEL[plan] || "未選択"));
    if (modules.length) lines.push("利用モジュール：" + modules.join("、"));
    if (options.length) lines.push("追加オプション：" + options.join("、"));

    configuratorContext = lines.join(" / ");

    var message = document.getElementById("cf-message");
    if (message) message.value = lines.join("\n");
  })();

  function setError(fieldId, hasError) {
    var el = document.getElementById(fieldId);
    var group = el && el.closest(".form-group");
    if (!group) return;
    group.classList.toggle("has-error", hasError);
  }

  function isValidEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
  }

  // サーバー側バリデーションエラーコード(例: "company_required")をフォームの項目IDへ対応付ける
  var SERVER_FIELD_TO_ID = {
    company: "cf-company",
    name: "cf-name",
    email: "cf-email",
    industry: "cf-industry",
    message: "cf-message",
    consent: "cf-consent"
  };

  var ERROR_MESSAGES = {
    validation_error: "入力内容をご確認のうえ、もう一度お試しください。",
    rate_limited: "送信回数の上限に達しました。しばらく経ってから再度お試しください。",
    duplicate_submission: "同じ内容が送信済みです。しばらく経ってから再度お試しください。",
    csrf_invalid: "ページの有効期限が切れました。画面を再読み込みしてから、もう一度お試しください。",
    captcha_failed: "送信を確認できませんでした。しばらく経ってから再度お試しください。",
    origin_rejected: "送信に失敗しました。しばらく経ってから再度お試しください。",
    server_error: "送信に失敗しました。しばらく経ってから再度お試しいただくか、デモページよりお試しください。",
    network_error: "通信エラーが発生しました。しばらく経ってから再度お試しいただくか、デモページよりお試しください。"
  };

  function showError(code, fields) {
    if (successBox) successBox.classList.remove("is-visible");
    if (errorBanner) {
      errorBanner.textContent = ERROR_MESSAGES[code] || ERROR_MESSAGES.network_error;
      errorBanner.classList.add("is-visible");
      errorBanner.scrollIntoView({ behavior: "smooth", block: "center" });
    }
    if (Array.isArray(fields)) {
      fields.forEach(function (fieldError) {
        var key = fieldError.replace(/_required$|_invalid$|_too_long$/, "");
        var id = SERVER_FIELD_TO_ID[key];
        if (id) setError(id, true);
      });
    }
    if (code === "csrf_invalid") fetchCsrfToken();
  }

  function hideError() {
    if (errorBanner) errorBanner.classList.remove("is-visible");
  }

  form.addEventListener("submit", function (e) {
    e.preventDefault();

    var company = document.getElementById("cf-company");
    var name = document.getElementById("cf-name");
    var email = document.getElementById("cf-email");
    var industry = document.getElementById("cf-industry");
    var message = document.getElementById("cf-message");
    var consent = document.getElementById("cf-consent");

    var valid = true;

    if (!company.value.trim()) { setError("cf-company", true); valid = false; } else { setError("cf-company", false); }
    if (!name.value.trim()) { setError("cf-name", true); valid = false; } else { setError("cf-name", false); }
    if (!isValidEmail(email.value.trim())) { setError("cf-email", true); valid = false; } else { setError("cf-email", false); }
    if (!industry.value) { setError("cf-industry", true); valid = false; } else { setError("cf-industry", false); }
    if (!message.value.trim()) { setError("cf-message", true); valid = false; } else { setError("cf-message", false); }
    if (!consent.checked) { setError("cf-consent", true); valid = false; } else { setError("cf-consent", false); }

    if (!valid) {
      if (successBox) successBox.classList.remove("is-visible");
      hideError();
      var firstError = form.querySelector(".form-group.has-error");
      if (firstError) firstError.scrollIntoView({ behavior: "smooth", block: "center" });
      return;
    }

    var payload = {
      csrfToken: csrfToken,
      formRenderedAt: formRenderedAt,
      website: document.getElementById("cf-website") ? document.getElementById("cf-website").value : "",
      type: document.getElementById("cf-type") ? document.getElementById("cf-type").value : "",
      company: company.value.trim(),
      name: name.value.trim(),
      email: email.value.trim(),
      tel: document.getElementById("cf-tel") ? document.getElementById("cf-tel").value.trim() : "",
      industry: industry.value,
      employees: document.getElementById("cf-employees") ? document.getElementById("cf-employees").value : "",
      message: message.value.trim(),
      demo: document.getElementById("cf-demo") ? document.getElementById("cf-demo").checked : false,
      consent: consent.checked,
      configuratorContext: configuratorContext
    };

    hideError();
    if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = "送信中…"; }

    fetch(CONTACT_API_BASE + "/contact.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    })
      .then(function (res) {
        return res.json().then(function (data) { return { status: res.status, data: data }; });
      })
      .then(function (result) {
        if (result.data && result.data.ok) {
          if (successBox) {
            successBox.textContent = "お問い合わせを受け付けました。受付番号: " + result.data.receiptId + "。担当者より改めてご連絡いたします。";
            successBox.classList.add("is-visible");
            successBox.scrollIntoView({ behavior: "smooth", block: "center" });
          }
          form.reset();
          formRenderedAt = Date.now();
          fetchCsrfToken();
        } else {
          showError(result.data && result.data.error, result.data && result.data.fields);
        }
      })
      .catch(function () {
        // XServer側のDNS/デプロイが未完了の間はここに到達する(想定内)
        showError("network_error");
      })
      .finally(function () {
        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = "送信する"; }
      });
  });
})();
