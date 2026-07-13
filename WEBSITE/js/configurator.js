// Smart Labo Configurator — 導入プランシミュレーター(pricing.html専用)
// 2026-07-13 CEO承認「Smart Labo Configurator」実装
//
// データモデルはPROJECT_BIBLE 12_Pricing_Philosophy.mdのUsageScale/Plan/Module/Option/
// ProposalSelectionと同一のフィールド名・status語彙('ready'|'planned'|'consult')を用いる。
// 将来Company OS「Pricing Manager」を実装する際、この構造をそのまま踏襲できることを意図している。
//
// プランごとのモジュール配分(PLAN_MODULES)は仮定義。正式な提供内容が決まり次第、
// このファイルのマスターデータのみを変更すれば全画面へ反映される。

(function () {
  "use strict";

  var root = document.getElementById("configurator");
  if (!root) return;

  var STORAGE_KEY = "slw_configurator_selection_v1";

  var USAGE_SCALES = [
    { id: "small", label: "Small", description: "小規模企業向け<br>目安：〜10名程度" },
    { id: "medium", label: "Medium", description: "中規模企業向け<br>目安：11〜50名程度" },
    { id: "enterprise", label: "Enterprise", description: "大規模企業向け<br>目安：51名以上・複数拠点" }
  ];

  // ③利用モジュール(選択したプランに標準搭載される中核機能。単体の追加オプションとしては扱わない)
  var MODULES = [
    { id: "companyBrain", label: "Company Brain", status: "ready" },
    { id: "crm", label: "CRM", status: "ready" },
    { id: "aiAssistant", label: "AI Assistant", status: "ready" },
    { id: "dealManagement", label: "案件管理", status: "ready" },
    { id: "contractManagement", label: "契約管理", status: "ready" },
    { id: "aiTemplate", label: "AIテンプレート", status: "ready" }
  ];

  // ②基本プラン(仮定義。modulesの配分・highlightsは今後マスターデータのみで変更可能)
  var PLANS = [
    {
      id: "lite",
      label: "Lite",
      price: "個別相談",
      description: "主要機能をご体験いただける入門プラン。",
      modules: ["aiAssistant", "aiTemplate"],
      highlights: []
    },
    {
      id: "standard",
      label: "Standard",
      price: "個別相談",
      description: "Smart AI Router・業種特化テンプレートを含む標準プラン。",
      modules: ["aiAssistant", "aiTemplate", "companyBrain", "crm", "dealManagement", "contractManagement"],
      highlights: ["Smart AI Router（OpenAI/Claude/Gemini）"],
      featured: true
    },
    {
      id: "premium",
      label: "Premium",
      price: "個別見積り",
      description: "専用カスタマイズ・優先サポートを含む最上位プラン。",
      modules: ["aiAssistant", "aiTemplate", "companyBrain", "crm", "dealManagement", "contractManagement"],
      highlights: ["高度なAI Router活用", "個別テンプレート対応", "優先サポート体制"]
    }
  ];

  // ④追加オプション(基本プラン・利用モジュールに含まれない個別性の高い拡張)
  var OPTIONS = [
    { id: "meeting", label: "Meeting／議事録AI", status: "planned" },
    { id: "ocr", label: "OCR", status: "planned" },
    { id: "api", label: "外部API連携", status: "consult" },
    { id: "aiTraining", label: "AI活用研修", status: "consult" },
    { id: "brainSetup", label: "Company Brain初期構築支援", status: "ready" },
    { id: "customTemplate", label: "オリジナルテンプレート制作", status: "ready" },
    { id: "dataMigration", label: "データ移行", status: "consult" },
    { id: "onboardingSupport", label: "導入・運用支援", status: "consult" }
  ];

  var STATUS_LABEL = { ready: "対応可", planned: "Coming Soon", consult: "要相談" };
  var STATUS_CLASS = { ready: "status-badge--ready", planned: "status-badge--soon", consult: "status-badge--consult" };

  function byId(list, id) {
    for (var i = 0; i < list.length; i++) { if (list[i].id === id) return list[i]; }
    return null;
  }

  // 実行時の選択状態(PROJECT_BIBLE ProposalSelectionのサブセット)
  var selection = { usageScale: null, plan: null, modules: [], options: [] };

  function loadSelection() {
    try {
      var raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return;
      var saved = JSON.parse(raw);
      if (saved && typeof saved === "object") {
        selection.usageScale = saved.usageScale || null;
        selection.plan = saved.plan || null;
        selection.options = Array.isArray(saved.options) ? saved.options : [];
        applyPlanModules();
      }
    } catch (e) { /* 破損データは無視して初期状態から開始 */ }
  }

  function saveSelection() {
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(selection)); } catch (e) { /* 保存不可な環境は無視 */ }
  }

  function applyPlanModules() {
    var plan = byId(PLANS, selection.plan);
    selection.modules = plan ? plan.modules.slice() : [];
  }

  function selectUsageScale(id) {
    selection.usageScale = id;
    saveSelection();
    render();
  }

  function selectPlan(id) {
    selection.plan = id;
    applyPlanModules();
    saveSelection();
    render();
  }

  function toggleOption(id) {
    var idx = selection.options.indexOf(id);
    if (idx === -1) selection.options.push(id); else selection.options.splice(idx, 1);
    saveSelection();
    render();
  }

  function esc(str) {
    return String(str).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }

  // ---- レンダリング ----

  function renderProgress() {
    var steps = [
      { label: "利用規模", done: !!selection.usageScale },
      { label: "基本プラン", done: !!selection.plan },
      { label: "利用モジュール", done: !!selection.plan },
      { label: "追加オプション", done: selection.options.length > 0 },
      { label: "おすすめ構成", done: !!selection.usageScale && !!selection.plan }
    ];
    var html = steps.map(function (s, i) {
      return '<div class="cf-progress__step' + (s.done ? " is-done" : "") + '">' +
        '<span class="cf-progress__num">' + (s.done ? "✓" : i + 1) + '</span>' +
        '<span class="cf-progress__label">' + esc(s.label) + '</span></div>';
    }).join('<span class="cf-progress__sep"></span>');
    document.querySelectorAll(".cf-progress").forEach(function (el) { el.innerHTML = html; });
  }

  function renderStep1() {
    var el = document.getElementById("cf-step1");
    if (!el) return;
    el.innerHTML = USAGE_SCALES.map(function (s) {
      var active = selection.usageScale === s.id;
      return '<button type="button" class="scale-card cf-selectable' + (active ? " is-selected" : "") + '" data-scale="' + s.id + '" aria-pressed="' + active + '">' +
        (active ? '<span class="cf-check" aria-hidden="true">✓</span>' : '') +
        '<p class="scale-card__name">' + esc(s.label) + '</p>' +
        '<p class="scale-card__desc">' + s.description + '</p></button>';
    }).join("");
  }

  function renderStep2() {
    var el = document.getElementById("cf-step2");
    if (!el) return;
    el.innerHTML = PLANS.map(function (p) {
      var active = selection.plan === p.id;
      return '<button type="button" class="pricing-card cf-selectable' + (p.featured ? " pricing-card--featured" : "") + (active ? " is-selected" : "") + '" data-plan="' + p.id + '" aria-pressed="' + active + '">' +
        (p.featured ? '<span class="pricing-card__badge">おすすめ</span>' : '') +
        (active ? '<span class="cf-check" aria-hidden="true">✓</span>' : '') +
        '<p class="pricing-card__plan">' + esc(p.label) + '</p>' +
        '<p class="pricing-card__price">' + esc(p.price) + '</p>' +
        '<p class="pricing-card__desc">' + esc(p.description) + '</p></button>';
    }).join("");
  }

  function renderStep3() {
    var el = document.getElementById("cf-step3");
    if (!el) return;
    if (!selection.plan) {
      el.innerHTML = '<p class="cf-placeholder">Step2で基本プランを選択すると、含まれる利用モジュールが表示されます。</p>';
      return;
    }
    var plan = byId(PLANS, selection.plan);
    var html = '<div class="option-grid">' + MODULES.map(function (m) {
      var included = plan.modules.indexOf(m.id) !== -1;
      return '<div class="option-item module-item' + (included ? " is-included" : " is-excluded") + '">' +
        '<span class="option-item__name"><span class="module-item__check" aria-hidden="true">' + (included ? "✓" : "—") + '</span>' + esc(m.label) + '</span>' +
        '<span class="status-badge ' + (included ? "status-badge--ready" : "status-badge--consult") + '">' + (included ? "含まれる" : "対象外") + '</span></div>';
    }).join("") + "</div>";
    if (plan.highlights.length) {
      html += '<ul class="cf-highlights">' + plan.highlights.map(function (h) { return "<li>" + esc(h) + "</li>"; }).join("") + "</ul>";
    }
    el.innerHTML = html;
  }

  function renderStep4() {
    var el = document.getElementById("cf-step4");
    if (!el) return;
    el.innerHTML = '<div class="option-grid">' + OPTIONS.map(function (o) {
      var checked = selection.options.indexOf(o.id) !== -1;
      return '<label class="option-item cf-option-item">' +
        '<span class="option-item__name"><input type="checkbox" class="cf-option-checkbox" data-option="' + o.id + '"' + (checked ? " checked" : "") + '>' + esc(o.label) + '</span>' +
        '<span class="status-badge ' + STATUS_CLASS[o.status] + '">' + STATUS_LABEL[o.status] + '</span></label>';
    }).join("") + "</div>";
  }

  function renderSummary() {
    var scale = byId(USAGE_SCALES, selection.usageScale);
    var plan = byId(PLANS, selection.plan);
    var moduleLabels = selection.modules.map(function (id) { var m = byId(MODULES, id); return m ? m.label : id; });
    var optionLabels = selection.options.map(function (id) { var o = byId(OPTIONS, id); return o ? o.label : id; });

    var priceNote;
    if (scale && plan) {
      priceNote = "個別お見積りが可能です。無料相談・見積り依頼からお気軽にどうぞ。";
    } else {
      priceNote = "Step1・Step2（利用規模・基本プラン）を選択すると、おすすめ構成が確定します。";
    }

    var html = '<dl class="cf-summary__list">' +
      '<dt>利用規模</dt><dd>' + (scale ? esc(scale.label) : "未選択") + '</dd>' +
      '<dt>基本プラン</dt><dd>' + (plan ? esc(plan.label) : "未選択") + '</dd>' +
      '<dt>利用モジュール</dt><dd>' + (moduleLabels.length ? moduleLabels.map(esc).join("／") : "—") + '</dd>' +
      '<dt>追加オプション</dt><dd>' + (optionLabels.length ? optionLabels.map(esc).join("／") : "未選択") + '</dd>' +
      '</dl><p class="cf-summary__price">' + esc(priceNote) + '</p>';

    document.querySelectorAll(".cf-summary__body").forEach(function (el) { el.innerHTML = html; });

    var miniEl = document.getElementById("cf-mini-status");
    if (miniEl) {
      miniEl.textContent = "利用規模：" + (scale ? scale.label : "未選択") + " ｜ 基本プラン：" + (plan ? plan.label : "未選択") + " ｜ 追加オプション：" + selection.options.length + "件選択中";
    }
  }

  function buildContactUrl(type) {
    var scale = byId(USAGE_SCALES, selection.usageScale);
    var plan = byId(PLANS, selection.plan);
    var params = new URLSearchParams();
    params.set("from", "configurator");
    params.set("type", type);
    if (scale) params.set("scale", scale.id);
    if (plan) params.set("plan", plan.id);
    if (selection.modules.length) params.set("modules", selection.modules.join(","));
    if (selection.options.length) params.set("options", selection.options.join(","));
    return "contact.html?" + params.toString();
  }

  function render() {
    renderProgress();
    renderStep1();
    renderStep2();
    renderStep3();
    renderStep4();
    renderSummary();
  }

  root.addEventListener("click", function (e) {
    var scaleBtn = e.target.closest("[data-scale]");
    if (scaleBtn) { selectUsageScale(scaleBtn.getAttribute("data-scale")); return; }

    var planBtn = e.target.closest("[data-plan]");
    if (planBtn) { selectPlan(planBtn.getAttribute("data-plan")); return; }

    var ctaBtn = e.target.closest("[data-cf-cta]");
    if (ctaBtn) { window.location.href = buildContactUrl(ctaBtn.getAttribute("data-cf-cta")); return; }
  });

  root.addEventListener("change", function (e) {
    if (e.target.classList.contains("cf-option-checkbox")) {
      toggleOption(e.target.getAttribute("data-option"));
    }
  });

  loadSelection();
  render();
})();
