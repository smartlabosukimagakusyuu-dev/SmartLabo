// Smart Labo Works — 会社情報の単一データソース(2026-07-14 CEO承認「正式リリース前 最終整備」)
//
// 会社概要ページ・フッターなど、複数ページにまたがる会社情報表示は
// このファイルの値のみを編集すれば全ページへ反映される。
// 未確定の項目はnullのままとし、絶対に推測で埋めない(PENDING表示)。

(function () {
  "use strict";

  var COMPANY_INFO = {
    legalName: "株式会社スマートラボ",
    brandName: "Smart Labo",
    serviceName: "Smart Labo Works",
    representative: null,
    founded: null,
    address: null,
    tel: null,
    email: null,
    url: "https://smartlabosukimagakusyuu-dev.github.io/SmartLabo/",
    urlNote: "暫定URL(GitHub Pages)。正式ドメイン smartlaboworks.com は取得済みだが、Xserverのサーバー契約完了待ち",
    business: null
  };

  var PENDING_LABEL = "CEO確認待ち";

  function displayValue(value) {
    return value === null || value === "" ? PENDING_LABEL : value;
  }

  function renderCompanyInfo() {
    document.querySelectorAll("[data-company]").forEach(function (el) {
      var key = el.getAttribute("data-company");
      var value = COMPANY_INFO[key];
      var display = displayValue(value);
      el.textContent = display;
      el.classList.toggle("company-info__pending", value === null || value === "");
    });
  }

  window.SMART_LABO_COMPANY_INFO = COMPANY_INFO;
  document.addEventListener("DOMContentLoaded", renderCompanyInfo);
})();
