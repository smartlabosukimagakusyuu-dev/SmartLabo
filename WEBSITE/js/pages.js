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
  // 送信先は未設定のため、実際の送信は行わない。バリデーションと「未接続」の明示のみ。
  var form = document.getElementById("contactForm");
  if (!form) return;

  var successBox = document.getElementById("contactSuccess");

  function setError(fieldId, hasError) {
    var group = document.getElementById(fieldId).closest(".form-group");
    if (!group) return;
    group.classList.toggle("has-error", hasError);
  }

  function isValidEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
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
      var firstError = form.querySelector(".form-group.has-error");
      if (firstError) firstError.scrollIntoView({ behavior: "smooth", block: "center" });
      return;
    }

    // 送信先は未接続のため、実際には送信しない。バリデーション通過のみを明示する。
    if (successBox) {
      successBox.classList.add("is-visible");
      successBox.scrollIntoView({ behavior: "smooth", block: "center" });
    }
    form.reset();
  });
})();
