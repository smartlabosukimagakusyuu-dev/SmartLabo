// Smart Labo Works — Hero「AI Control Center」Live Dashboard演出
// (2026-07-12 CEO承認「Hero Experience Upgrade Version3」)
//
// 静的なUIではなく「AIがリアルタイムで働いている」ことを伝えるための
// 軽量な状態ローテーションのみ。実際のAI処理とは連動していない
// （表示用サンプルである旨は hero__visual-note に明記）。

(function () {
  "use strict";

  var providers = document.querySelectorAll(".cc-providers .cc-node");
  var statusEl = document.getElementById("ccProcessingStatus");
  if (!providers.length || !statusEl) return;

  var STEPS = [
    { provider: 0, text: "問い合わせ返信中…" },
    { provider: 1, text: "契約レビュー中…" },
    { provider: 2, text: "PDF・画像解析中…" },
    { provider: -1, text: "Company Brain検索中…" },
  ];
  var stepIndex = 0;

  function tickProcessing() {
    providers.forEach(function (el) {
      el.classList.remove("is-active");
    });
    var step = STEPS[stepIndex % STEPS.length];
    if (step.provider >= 0 && providers[step.provider]) {
      providers[step.provider].classList.add("is-active");
    }
    statusEl.textContent = step.text;
    stepIndex++;
  }

  tickProcessing();
  window.setInterval(tickProcessing, 2600);

  var brainStatusEl = document.getElementById("ccBrainStatus");
  if (brainStatusEl) {
    var BRAIN_STATES = ["Company Brain 参照中", "Company Brain 更新中", "学習完了"];
    var brainIndex = 0;
    window.setInterval(function () {
      brainIndex++;
      brainStatusEl.textContent = BRAIN_STATES[brainIndex % BRAIN_STATES.length];
    }, 4200);
  }
})();
