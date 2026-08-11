/* ==========================================================================
   Smart Labo Works — Website v3 / お問い合わせフォーム
   --------------------------------------------------------------------------
   考え方
     ・HTMLのフォームは、JavaScriptが無くても action へ通常POSTされ、
       PHP側がHTMLで結果を返す。この JS は「その体験を良くする」だけの上乗せ。
     ・したがって、この JS が読み込まれなくてもフォームは機能する。
     ・送信成功の応答を受け取るまで、入力内容は消さない。

   外部ライブラリは使わない。ビルドも不要。
   ========================================================================== */

(function () {
  'use strict';

  var form = document.getElementById('contactForm');
  if (!form) return;

  var statusEl = document.getElementById('formStatus');
  var submitBtn = document.getElementById('formSubmit');
  var endpoint = form.getAttribute('data-endpoint') || form.getAttribute('action');
  var tokenEndpoint = form.getAttribute('data-token-endpoint');

  /* ------------------------------------------------ 自動送信対策の下準備 -- */

  // フォームを開いた時刻。極端に速い送信を弾くためにサーバーへ渡す。
  var tsField = document.getElementById('f-form-ts');
  if (tsField) tsField.value = String(Date.now());

  /**
   * ローカルでの開発表示かどうか（WEB-V3-4）。
   *
   * 送信先APIは本番では別オリジン（form.smartlaboworks.com）にあり、
   * その許可オリジンには公開サイトのURLだけを登録する。そのため
   * localhost から開くとCSRFトークン取得がCORSで必ず失敗し、
   * ブラウザのコンソールにエラーが残る（画面と操作は壊れないが紛らわしい）。
   * 開発時は取得そのものを行わないようにして、確認を静かに保つ。
   * 本番（smartlaboworks.com）での挙動は従来どおり変わらない。
   */
  var isLocalPreview = /^(localhost|127\.0\.0\.1|\[::1\])$/.test(location.hostname);

  // CSRFトークンを取得して隠し項目へ入れる。
  // 取得できなくても送信は止めない（サーバー側の設定次第で受け付けられる）。
  var csrfField = document.getElementById('f-csrf');
  if (csrfField && tokenEndpoint && !isLocalPreview) {
    fetch(tokenEndpoint, { method: 'GET', credentials: 'omit' })
      .then(function (res) { return res.ok ? res.json() : null; })
      .then(function (data) { if (data && data.token) csrfField.value = data.token; })
      .catch(function () { /* 取得できなくても続行する */ });
  }

  /* ------------------------------------ 種別の事前選択(WEB-V3-1) -- */

  // 資料請求などの導線から ?type=docs 付きで開かれた場合、種別をあらかじめ
  // 選んでおく。フォームに存在しない値は無視する。JSが動かなくても、
  // 利用者が自分で選べばよいだけなので機能は損なわれない。
  if ('URLSearchParams' in window) {
    var typeParam = new URLSearchParams(location.search).get('type');
    var typeSelect = document.getElementById('f-type');
    if (typeParam && typeSelect && /^[a-z0-9_-]+$/.test(typeParam) &&
        typeSelect.querySelector('option[value="' + typeParam + '"]')) {
      typeSelect.value = typeParam;
    }
  }

  /* ---------------------------------------------------- 入力チェック -- */

  var LIMITS = { company: 100, name: 100, email: 254, tel: 30, message: 3000 };

  var LABELS = {
    company: '会社名', name: 'お名前', email: 'メールアドレス', tel: '電話番号',
    type: 'お問い合わせ種別', headcount: '利用予定人数',
    message: 'お問い合わせ内容', privacy: 'プライバシーポリシーへの同意'
  };

  // サーバー側が返す理由コードを日本語へ。表現はサーバーと揃えている。
  function reasonText(field, reason) {
    if (reason === 'required') {
      return field === 'privacy'
        ? 'プライバシーポリシーへの同意が必要です。'
        : LABELS[field] + 'を入力してください。';
    }
    if (reason === 'too_long') {
      return LABELS[field] + 'は' + LIMITS[field].toLocaleString() + '文字以内で入力してください。';
    }
    if (reason === 'invalid') {
      return field === 'email'
        ? 'メールアドレスの形式をご確認ください。'
        : LABELS[field] + 'の内容をご確認ください。';
    }
    return LABELS[field] + 'をご確認ください。';
  }

  function errorEl(field) {
    return form.querySelector('[data-error-for="' + field + '"]');
  }

  function inputEl(field) {
    return form.querySelector('[name="' + field + '"]');
  }

  function clearErrors() {
    form.querySelectorAll('[data-error-for]').forEach(function (el) {
      el.textContent = '';
      el.classList.remove('is-shown');
    });
    form.querySelectorAll('[aria-invalid="true"]').forEach(function (el) {
      el.removeAttribute('aria-invalid');
    });
  }

  /** エラーを表示し、最初のエラー項目へフォーカスを移す */
  function showErrors(errors) {
    clearErrors();
    var firstField = null;

    Object.keys(errors).forEach(function (field) {
      var msg = errorEl(field);
      var input = inputEl(field);
      if (msg) {
        msg.textContent = reasonText(field, errors[field]);
        msg.classList.add('is-shown');
      }
      if (input) {
        // 色だけに頼らず、支援技術にも「不正」であることを伝える
        input.setAttribute('aria-invalid', 'true');
        if (!firstField) firstField = input;
      }
    });

    if (firstField) firstField.focus();
  }

  /** 画面側の検証。サーバー側を正とするが、往復を減らすために先に確認する */
  function validateLocally() {
    var errors = {};
    var data = new FormData(form);

    ['company', 'name', 'email', 'message'].forEach(function (f) {
      var v = String(data.get(f) || '').trim();
      if (v === '') errors[f] = 'required';
      else if (v.length > LIMITS[f]) errors[f] = 'too_long';
    });

    if (String(data.get('type') || '') === '') errors.type = 'required';

    var tel = String(data.get('tel') || '').trim();
    if (tel !== '' && tel.length > LIMITS.tel) errors.tel = 'too_long';

    var email = String(data.get('email') || '').trim();
    if (!errors.email && email !== '') {
      // 改行が混ざっていないか、形も見る
      if (/[\r\n]/.test(email) || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        errors.email = 'invalid';
      }
    }

    if (!data.get('privacy')) errors.privacy = 'required';

    return errors;
  }

  /* ------------------------------------------------------ 状態の表示 -- */

  function setStatus(kind, text) {
    if (!statusEl) return;
    statusEl.textContent = text;
    statusEl.classList.remove('is-ok', 'is-error', 'is-busy');
    if (kind) statusEl.classList.add('is-' + kind);
  }

  var sending = false;

  function setSending(on) {
    sending = on;
    if (!submitBtn) return;
    // 二重送信を防ぐ
    submitBtn.disabled = on;
    submitBtn.setAttribute('aria-busy', on ? 'true' : 'false');
    submitBtn.textContent = on ? '送信中…' : '送信する';
  }

  /* -------------------------------------------------- 成功時の計測 -- */

  /*
   * アクセス解析（GTM/GA4/dataLayer）は WEB-SALES-8L で撤去した。
   * 以前ここにあった generate_lead の送信（送るのは種別のみ）も削除している。
   * 再導入する場合は docs/website/ANALYTICS_REINTRODUCTION.md の条件を満たし、
   * privacy.html の開示・Cookie同意の要否整理を済ませてから行うこと。
   */

  /* ---------------------------------------------------------- 送信 -- */

  form.addEventListener('submit', function (e) {
    // fetch が使えない環境では通常のPOSTに任せる（何もしない）
    if (typeof window.fetch !== 'function') return;

    e.preventDefault();
    if (sending) return;

    var errors = validateLocally();
    if (Object.keys(errors).length > 0) {
      setStatus('error', '入力内容をご確認ください。');
      showErrors(errors);
      return;
    }

    clearErrors();
    setSending(true);
    setStatus('busy', '送信しています…');

    var payload = {};
    new FormData(form).forEach(function (v, k) { payload[k] = v; });

    fetch(endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'fetch'
      },
      credentials: 'omit',
      body: JSON.stringify(payload)
    })
      .then(function (res) {
        return res.json().catch(function () { return { result: 'failed' }; });
      })
      .then(function (data) {
        if (data.result === 'ok') {
          setStatus('ok', data.message || 'お問い合わせを受け付けました。');
          // 成功の応答を受け取ってから初めて入力欄を消す
          form.reset();
          if (tsField) tsField.value = String(Date.now());
          if (statusEl) statusEl.focus && statusEl.focus();
          return;
        }
        if (data.result === 'invalid' && data.errors) {
          setStatus('error', data.message || '入力内容をご確認ください。');
          showErrors(data.errors);
          return;
        }
        setStatus('error', data.message || '送信できませんでした。時間をおいて再度お試しください。');
      })
      .catch(function () {
        setStatus('error', '送信できませんでした。時間をおいて再度お試しください。');
      })
      .then(function () {
        setSending(false);
      });
  });
})();
