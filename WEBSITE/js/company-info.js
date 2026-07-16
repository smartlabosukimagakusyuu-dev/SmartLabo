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
    representative: "小川昌利",
    founded: "2026年7月",
    address: null,
    tel: null,
    email: "info@smartlaboworks.com",
    url: "https://smartlaboworks.com/",
    urlNote: "正式ドメイン・無料SSLは設定済み。GitHub Pages側のカスタムドメイン切替(公開手順書 Step1参照)が完了次第、このURLで公開される",
    business: "AI業務支援プラットフォーム「Smart Labo Works」の企画・開発・提供"
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
