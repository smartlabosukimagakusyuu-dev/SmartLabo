/* ==========================================================================
   Smart Labo Works — Website v2 / セルフ申し込み(SALES-1)
   --------------------------------------------------------------------------
   考え方
     ・HTMLは3ステップぶんの入力欄をすべて持っている。JavaScriptが無ければ
       全部が縦に並び、1回のPOSTで /api/signup へ送られる（内容は失われない）。
     ・このJSは「1ステップずつ表示する」「先に入力を確認する」「金額を計算して
       見せる」ための上乗せであり、無くてもフォームは機能する。
     ・入力の正はサーバー側（signup-api/public/lib/validate.php）。
       ここでの確認は往復を減らすためのもので、これだけに頼らない。
     ・金額の正もサーバー側（signup-api/public/lib/pricing.php）。
       ここでの計算は表示用で、送信もしない（サーバーが計算し直す）。

   外部ライブラリは使わない。ビルドも不要。
   ========================================================================== */

(function () {
  'use strict';

  var form = document.getElementById('signupForm');
  if (!form) return;

  var statusEl = document.getElementById('formStatus');
  var submitBtn = document.getElementById('formSubmit');
  var endpoint = form.getAttribute('data-endpoint') || form.getAttribute('action');
  var tokenEndpoint = form.getAttribute('data-token-endpoint');

  var steps = Array.prototype.slice.call(form.querySelectorAll('[data-step]'));
  var indicators = Array.prototype.slice.call(
    document.querySelectorAll('[data-step-indicator]')
  );

  /* ------------------------------------------------ 料金の正規値 --------
     出典: PROJECT_BIBLE/14_Sales_And_Billing_Policy.md
     サーバー側 signup-api/public/lib/pricing.php と同じ値。
     ここは表示のためだけに使い、金額を送信はしない。 */
  var PRICE = { monthly: 20000, additional: 3000 };

  /* ------------------------------------------------ 入力チェックの定義 -- */

  var LIMITS = {
    company_name: 100, company_kana: 100, postal_code: 8, address: 200,
    company_tel: 20, contact_email: 254,
    admin_name: 100, admin_email: 254, password: 128
  };

  var LABELS = {
    company_name: '会社名', company_kana: '会社名（カナ）', postal_code: '郵便番号',
    address: '住所', company_tel: '電話番号', contact_email: '担当者メールアドレス',
    admin_name: 'お名前', admin_email: 'メールアドレス',
    password: 'パスワード', password_confirm: 'パスワード（確認）',
    additional_accounts: '追加アカウント数'
  };

  // どのステップにどの項目があるか。「次へ」で手前のステップだけを確認するために使う。
  var FIELDS_BY_STEP = {
    1: ['company_name', 'company_kana', 'postal_code', 'address', 'company_tel', 'contact_email'],
    2: ['admin_name', 'admin_email', 'password', 'password_confirm'],
    3: ['additional_accounts']
  };

  var PASSWORD_MIN = 10;

  /** サーバーが返す理由コードを日本語へ。表現はサーバー側と揃えている。 */
  function reasonText(field, reason) {
    if (reason === 'required')  { return LABELS[field] + 'を入力してください。'; }
    if (reason === 'too_long')  { return LABELS[field] + 'は' + LIMITS[field] + '文字以内で入力してください。'; }
    if (reason === 'too_short') { return 'パスワードは' + PASSWORD_MIN + '文字以上で入力してください。'; }
    if (reason === 'mismatch')  { return 'パスワードが一致しません。'; }
    if (reason === 'weak')      { return '英大文字・英小文字・数字・記号のうち3種類以上を含む、推測されにくいパスワードを入力してください。'; }
    if (reason === 'out_of_range') { return '追加アカウント数は0〜999の範囲で入力してください。'; }
    if (reason === 'invalid') {
      if (field === 'contact_email' || field === 'admin_email') { return 'メールアドレスの形式をご確認ください。'; }
      if (field === 'company_kana') { return '会社名（カナ）は全角カタカナで入力してください。'; }
      if (field === 'postal_code')  { return '郵便番号は7桁の数字でご入力ください。例：123-4567'; }
      if (field === 'company_tel')  { return '電話番号は市外局番からの10〜11桁でご入力ください。'; }
      return LABELS[field] + 'の内容をご確認ください。';
    }
    return LABELS[field] + 'をご確認ください。';
  }

  function errorEl(field) { return form.querySelector('[data-error-for="' + field + '"]'); }
  function inputEl(field) { return form.querySelector('[name="' + field + '"]'); }

  function clearErrors(fields) {
    var list = fields || Object.keys(LABELS);
    list.forEach(function (field) {
      var msg = errorEl(field);
      var input = inputEl(field);
      if (msg) { msg.textContent = ''; msg.classList.remove('is-shown'); }
      if (input) { input.removeAttribute('aria-invalid'); }
    });
  }

  /** エラーを表示し、最初のエラー項目へフォーカスを移す */
  function showErrors(errors) {
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
    return firstField;
  }

  function val(field) {
    var el = inputEl(field);
    return el ? String(el.value || '').trim() : '';
  }

  /** 指定した項目だけを確認する。サーバー側の規則に合わせている。 */
  function validateFields(fields) {
    var errors = {};

    fields.forEach(function (f) {
      var v = val(f);

      // --- 必須 ---
      if (f !== 'additional_accounts' && v === '') { errors[f] = 'required'; return; }

      // --- 文字数 ---
      if (LIMITS[f] && v.length > LIMITS[f]) { errors[f] = 'too_long'; return; }

      // --- 個別の形式 ---
      if (f === 'company_kana' && !/^[ァ-ヶー゛゜・　\s]+$/.test(v)) {
        errors[f] = 'invalid';
      } else if (f === 'postal_code' && !/^\d{3}-?\d{4}$/.test(v)) {
        errors[f] = 'invalid';
      } else if (f === 'company_tel') {
        var digits = v.replace(/\D/g, '');
        if (!/^[0-9\-()\s]+$/.test(v) || digits.length < 10 || digits.length > 11) {
          errors[f] = 'invalid';
        }
      } else if (f === 'contact_email' || f === 'admin_email') {
        if (/[\r\n]/.test(v) || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) { errors[f] = 'invalid'; }
      } else if (f === 'password') {
        var pw = inputEl('password') ? inputEl('password').value : '';
        var problem = passwordProblem(pw, val('admin_email'));
        if (problem) { errors[f] = problem; }
      } else if (f === 'password_confirm') {
        var pw1 = inputEl('password') ? inputEl('password').value : '';
        var pw2 = inputEl('password_confirm') ? inputEl('password_confirm').value : '';
        if (pw2 === '') { errors[f] = 'required'; }
        else if (pw1 !== pw2) { errors[f] = 'mismatch'; }
      } else if (f === 'additional_accounts') {
        if (v === '') { return; }   // 空欄は0として扱う
        if (!/^\d+$/.test(v) || Number(v) < 0 || Number(v) > 999) {
          errors[f] = /^\d+$/.test(v) ? 'out_of_range' : 'invalid';
        }
      }
    });

    return errors;
  }

  /** パスワードの強度。サーバー側 sls_password_problem() と同じ基準。 */
  function passwordProblem(password, email) {
    if (!password) { return 'required'; }
    if (password.length > LIMITS.password) { return 'too_long'; }
    if (password.length < PASSWORD_MIN) { return 'too_short'; }

    var kinds = 0;
    if (/[A-Z]/.test(password)) kinds++;
    if (/[a-z]/.test(password)) kinds++;
    if (/[0-9]/.test(password)) kinds++;
    if (/[^A-Za-z0-9]/.test(password)) kinds++;
    if (kinds < 3) { return 'weak'; }

    var lower = password.toLowerCase();
    var bad = ['password', 'smartlabo', 'smartlaboworks', 'qwerty', '123456', 'admin'];
    for (var i = 0; i < bad.length; i++) {
      if (lower.indexOf(bad[i]) !== -1) { return 'weak'; }
    }
    var local = String(email || '').toLowerCase().split('@')[0];
    if (local && local.length >= 4 && lower.indexOf(local) !== -1) { return 'weak'; }

    return '';
  }

  /* ---------------------------------------------------- ステップ制御 -- */

  var current = 1;

  /**
   * ステップを切り替える。
   * moveFocus が true のときだけ見出しへフォーカスを移す。
   * 初期表示で移すと、何も操作していないのにフォーカスリングが出るため、
   * 利用者の操作で切り替わったときに限る。
   */
  function showStep(n, moveFocus) {
    current = n;
    steps.forEach(function (sec) {
      var isCurrent = sec.getAttribute('data-step') === String(n);
      sec.hidden = !isCurrent;
    });
    indicators.forEach(function (li) {
      var num = Number(li.getAttribute('data-step-indicator'));
      li.classList.toggle('is-current', num === n);
      li.classList.toggle('is-done', num < n);
      if (num === n) { li.setAttribute('aria-current', 'step'); }
      else { li.removeAttribute('aria-current'); }
    });

    if (!moveFocus) return;

    // ステップ見出しへフォーカスを移し、画面が切り替わったことを伝える
    var heading = form.querySelector('[data-step="' + n + '"] .signup-step__title');
    if (heading) {
      heading.setAttribute('tabindex', '-1');
      heading.focus();
    }
    // 見出しが画面外にならないように先頭へ戻す
    var anchor = document.getElementById('signup');
    if (anchor && anchor.scrollIntoView) { anchor.scrollIntoView({ block: 'start' }); }
  }

  form.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('[data-goto-step]') : null;
    if (!btn) return;

    var target = Number(btn.getAttribute('data-goto-step'));

    // 前へ戻るときは確認しない（入力途中でも戻れるようにする）
    if (target < current) { showStep(target, true); return; }

    // 先へ進むときは、今のステップの項目だけを確認する
    var fields = FIELDS_BY_STEP[current] || [];
    clearErrors(fields);
    var errors = validateFields(fields);
    if (Object.keys(errors).length > 0) {
      setStatus('error', '入力内容をご確認ください。');
      showErrors(errors);
      return;
    }
    setStatus('', '');
    showStep(target, true);
  });

  /* ---------------------------------------------------- 金額の表示 -- */

  function yen(n) { return n.toLocaleString('ja-JP'); }

  function updateQuote() {
    var raw = val('additional_accounts');
    var count = /^\d+$/.test(raw) ? Number(raw) : 0;
    if (count < 0) count = 0;
    if (count > 999) count = 999;

    var additionalTotal = PRICE.additional * count;
    var monthlyTotal = PRICE.monthly + additionalTotal;

    var elCount = document.getElementById('quoteCount');
    var elAdd = document.getElementById('quoteAdditionalTotal');
    var elTotal = document.getElementById('quoteMonthlyTotal');
    if (elCount) elCount.textContent = String(count);
    if (elAdd) elAdd.textContent = yen(additionalTotal);
    if (elTotal) elTotal.textContent = yen(monthlyTotal);
  }

  var additionalInput = inputEl('additional_accounts');
  if (additionalInput) {
    additionalInput.addEventListener('input', updateQuote);
    additionalInput.addEventListener('change', updateQuote);
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
    submitBtn.disabled = on;               // 二重送信を防ぐ
    submitBtn.setAttribute('aria-busy', on ? 'true' : 'false');
    submitBtn.textContent = on ? '確認しています…' : '入力内容を確認する';
  }

  /* ------------------------------------------------ 自動送信対策の下準備 -- */

  var tsField = document.getElementById('f-form-ts');
  if (tsField) tsField.value = String(Date.now());

  var csrfField = document.getElementById('f-csrf');
  if (csrfField && tokenEndpoint) {
    fetch(tokenEndpoint, { method: 'GET', credentials: 'omit' })
      .then(function (res) { return res.ok ? res.json() : null; })
      .then(function (data) { if (data && data.token) csrfField.value = data.token; })
      .catch(function () { /* 取得できなくても続行する（サーバー側が判断する） */ });
  }

  /* ---------------------------------------------------------- 送信 -- */

  form.addEventListener('submit', function (e) {
    // fetch が使えない環境では通常のPOSTに任せる
    if (typeof window.fetch !== 'function') return;

    e.preventDefault();
    if (sending) return;

    // 送信前は全ステップの項目を確認する
    var all = FIELDS_BY_STEP[1].concat(FIELDS_BY_STEP[2], FIELDS_BY_STEP[3]);
    clearErrors(all);
    var errors = validateFields(all);

    if (Object.keys(errors).length > 0) {
      // エラーのある最初のステップまで戻す（隠れた項目のエラーを見落とさせない）
      var firstBad = Object.keys(errors)[0];
      var stepOf = 3;
      [1, 2, 3].forEach(function (n) {
        if (stepOf === 3 && FIELDS_BY_STEP[n].indexOf(firstBad) !== -1) { stepOf = n; }
      });
      showStep(stepOf, true);
      setStatus('error', '入力内容をご確認ください。');
      showErrors(errors);
      return;
    }

    setSending(true);
    setStatus('busy', '入力内容を確認しています…');

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
          // 成功しても入力内容は消さない。
          // 決済(SALES-2)へ進む前の確認であり、やり直せる必要があるため。
          setStatus('ok', '入力内容を確認しました。お支払いのお手続きは現在準備中です。');
          if (statusEl && statusEl.focus) statusEl.focus();
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

  /* ------------------------------------------------------------ 初期化 -- */

  updateQuote();
  showStep(1, false);   // 初期表示ではフォーカスを動かさない
})();
