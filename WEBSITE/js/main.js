// Smart Labo Works — Homepage interactions
// 静かで自然な動きのみを使用(PROJECT_BIBLE/20_UI_UX_Rules.md 原則4)

(function () {
  "use strict";

  // Mobile nav toggle
  var navToggle = document.getElementById("navToggle");
  var nav = document.getElementById("nav");

  if (navToggle && nav) {
    navToggle.addEventListener("click", function () {
      var isOpen = nav.classList.toggle("is-open");
      navToggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
    });

    nav.querySelectorAll("a").forEach(function (link) {
      link.addEventListener("click", function () {
        nav.classList.remove("is-open");
        navToggle.setAttribute("aria-expanded", "false");
      });
    });
  }

  // Scroll reveal for major sections
  var revealTargets = document.querySelectorAll(
    ".timeline__item, .value-card, .stat-card, .industry-card, .mission, " +
    ".employee-card, .screen-card, .industry-card2, .pricing-card, .flow-step, .router-diagram"
  );
  revealTargets.forEach(function (el) {
    el.classList.add("reveal");
  });

  if ("IntersectionObserver" in window) {
    var observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add("is-visible");
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.15, rootMargin: "0px 0px -40px 0px" }
    );
    revealTargets.forEach(function (el) {
      observer.observe(el);
    });
  } else {
    revealTargets.forEach(function (el) {
      el.classList.add("is-visible");
    });
  }

  // 画面ショーケース: 縦スクロール(ホイール)をそのまま横スクロールへ変換する
  var showcase = document.querySelector(".screens-showcase");
  if (showcase) {
    showcase.addEventListener(
      "wheel",
      function (e) {
        if (Math.abs(e.deltaY) <= Math.abs(e.deltaX)) return;
        showcase.scrollLeft += e.deltaY;
        e.preventDefault();
      },
      { passive: false }
    );
  }
})();
