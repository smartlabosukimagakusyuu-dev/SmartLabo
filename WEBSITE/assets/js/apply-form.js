/* ==========================================================================
   Smart Labo Works — Website / セルフ申込フォーム（WEB-SALES-12）
   --------------------------------------------------------------------------
   考え方
     ・送信先は Lite 本体の公開申込API（POST /api/public/signup・JSON専用）。
       入力の正はサーバー側（smartlabo-works-lite/server/services/signupService.js の
       validateApplication）。ここでの確認は往復を減らすための同等品で、
       規則を勝手に増減しない。
     ・APIはJSONしか受け付けないため、JavaScriptが無効の環境では送信できない。
       その場合は <noscript> の案内（無料相談への導線）を表示する。
     ・CORSで許可されるヘッダーは Content-Type だけ（製品側 publicCors）。
       X-Requested-With 等の独自ヘッダーは付けない（付けるとpreflightで失敗する）。
       Cookieは使わないため credentials は omit。
     ・受付済み・登録済みメール・回数制限は、APIが同一の202応答を返す設計
       （存在確認をさせないため）。画面側もこれを尊重し、202はすべて
       同じ完了案内を表示する。
     ・成功の応答を受け取るまで入力内容は消さない。失敗時も消さない。
     ・自動再送はしない。二重送信は送信中フラグとボタン無効化で防ぐ。
     ・APIの内部情報（レスポンス全文・コード・スタック）を画面や
       コンソールへ出さない。

   外部ライブラリは使わない。ビルドも不要。
   ========================================================================== */

(function () {
  'use strict';

  var form = document.getElementById('applyForm');
  if (!form) return;

  var statusEl = document.getElementById('formStatus');
  var submitBtn = document.getElementById('formSubmit');
  var noticeEl = document.getElementById('applyNotice');
  var doneEl = document.getElementById('applyDone');

  /**
   * 送信先。本番は data-endpoint（正規の本番URLのみ）。
   * ローカル確認（localhost）では同一オリジンのスタブへ向ける。
   * 本番APIの許可Originには公開サイトだけが登録されているため、
   * localhostから本番へ送ってもCORSで読めず、確認にならないため。
   */
  var PROD_ENDPOINT = 'https://lite.smartlaboworks.com/api/public/signup';
  var isLocalPreview = /^(localhost|127\.0\.0\.1|\[::1\])$/.test(location.hostname);
  var endpoint = isLocalPreview
    ? '/api/public/signup'
    : (form.getAttribute('data-endpoint') || PROD_ENDPOINT);

  /* ------------------------------------------------ 入力チェックの定義 --
     サーバー側 validateApplication と同じ規則（キー名・上限・形式）。 */

  var LIMITS = { companyName: 100, representativeName: 50, email: 254, phone: 30 };

  var LABELS = {
    companyName: '会社名',
    representativeName: 'ご担当者名',
    email: 'メールアドレス',
    phone: '電話番号',
    licenseCount: 'ご利用予定人数',
    privacy: 'プライバシーポリシーへの同意'
  };

  // 人数上限の正本は製品側の10,000
  //（smartlabo-works-lite server/config.js SIGNUP_MAX_LICENSE_COUNT 既定値。
  //  companyService.js の MAX_LICENSE=10000 とも一致）。
  // 静的サイトからサーバー設定値は取得できないため同値をここへ持ち、
  // 変更時は製品側と本ファイルと apply.html の max 属性を必ず揃える。
  var COUNT_MAX = 10000;

  var EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  var PHONE_PATTERN = /^[0-9+\-()\s]+$/;

  function reasonText(field, reason) {
    if (reason === 'required') {
      if (field === 'privacy') return 'プライバシーポリシーへの同意が必要です。';
      return LABELS[field] + 'を入力してください。';
    }
    if (reason === 'too_long') {
      return LABELS[field] + 'は' + LIMITS[field] + '文字以内で入力してください。';
    }
    if (reason === 'count') {
      return 'ご利用予定人数は1〜' + COUNT_MAX.toLocaleString('ja-JP') + 'の整数で入力してください。';
    }
    if (reason === 'invalid') {
      if (field === 'email') return 'メールアドレスの形式をご確認ください。';
      if (field === 'phone') return '電話番号は数字・ハイフン・かっこでご入力ください。';
      return LABELS[field] + 'の内容をご確認ください。';
    }
    return LABELS[field] + 'をご確認ください。';
  }

  function errorEl(field) { return form.querySelector('[data-error-for="' + field + '"]'); }
  function inputEl(field) { return form.querySelector('[name="' + field + '"]'); }

  function clearErrors() {
    form.querySelectorAll('[data-error-for]').forEach(function (el) {
      el.textContent = '';
      el.classList.remove('is-shown');
    });
    form.querySelectorAll('[aria-invalid="true"]').forEach(function (el) {
      el.removeAttribute('aria-invalid');
    });
  }

  /** エラーを項目単位で表示し、最初のエラー項目へフォーカスを移す */
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

  function val(field) {
    var el = inputEl(field);
    return el ? String(el.value || '').trim() : '';
  }

  /** 画面側の検証。サーバー側 validateApplication と同じ規則で先に確認する */
  function validateLocally() {
    var errors = {};

    var companyName = val('companyName');
    if (companyName === '') errors.companyName = 'required';
    else if (companyName.length > LIMITS.companyName) errors.companyName = 'too_long';

    var representativeName = val('representativeName');
    if (representativeName === '') errors.representativeName = 'required';
    else if (representativeName.length > LIMITS.representativeName) errors.representativeName = 'too_long';

    var email = val('email');
    if (email === '') errors.email = 'required';
    else if (email.length > LIMITS.email) errors.email = 'too_long';
    else if (/[\r\n]/.test(email) || !EMAIL_PATTERN.test(email)) errors.email = 'invalid';

    var phone = val('phone');
    if (phone !== '') {
      if (phone.length > LIMITS.phone) errors.phone = 'too_long';
      else if (!PHONE_PATTERN.test(phone)) errors.phone = 'invalid';
    }

    var countRaw = val('licenseCount');
    var count = Number(countRaw);
    if (countRaw === '' || !/^\d+$/.test(countRaw) || !Number.isInteger(count) ||
        count < 1 || count > COUNT_MAX) {
      errors.licenseCount = 'count';
    }

    var privacyEl = inputEl('privacy');
    if (!privacyEl || !privacyEl.checked) errors.privacy = 'required';

    return errors;
  }

  /* ---------------------------------------------------- 月額の目安表示 -- */

  var PRICE = { monthly: 20000, additional: 3000 };
  var quoteLine = document.getElementById('quoteLine');
  var countInput = inputEl('licenseCount');

  function updateQuote() {
    if (!quoteLine || !countInput) return;
    var raw = String(countInput.value || '').trim();
    if (!/^\d+$/.test(raw)) { quoteLine.textContent = ''; return; }
    var n = Number(raw);
    if (n < 1 || n > COUNT_MAX) { quoteLine.textContent = ''; return; }
    var monthly = PRICE.monthly + PRICE.additional * (n - 1);
    quoteLine.textContent =
      n + '名の場合の月額は ' + monthly.toLocaleString('ja-JP') + '円（税別）です。';
  }
  if (countInput) {
    countInput.addEventListener('input', updateQuote);
    countInput.addEventListener('change', updateQuote);
  }

  /* ------------------------------------------------------ 状態の表示 -- */

  function setStatus(kind, text) {
    if (!statusEl) return;
    statusEl.textContent = text;
    statusEl.classList.remove('is-ok', 'is-error', 'is-busy');
    if (kind) statusEl.classList.add('is-' + kind);
  }

  var sending = false;
  var completed = false;

  function setSending(on) {
    sending = on;
    if (!submitBtn) return;
    // 二重送信を防ぐ
    submitBtn.disabled = on;
    submitBtn.setAttribute('aria-busy', on ? 'true' : 'false');
    submitBtn.textContent = on
      ? '送信しています…'
      : '申し込む（この時点では請求されません）';
  }

  /** 受付完了の表示。フォームを隠し、完了案内へフォーカスを移す */
  function showDone() {
    completed = true;
    form.hidden = true;
    if (noticeEl) noticeEl.hidden = true;
    if (doneEl) {
      doneEl.hidden = false;
      if (doneEl.focus) doneEl.focus();
      if (doneEl.scrollIntoView) doneEl.scrollIntoView({ block: 'start' });
    }
  }

  var GENERIC_FAIL = '送信できませんでした。時間をおいて再度お試しください。' +
    '解決しない場合は、お手数ですがお問い合わせフォームからご連絡ください。';

  // 通信の待ち時間上限。超えたら中断して上の一般文言を出す（自動再送はしない）。
  // 無期限に「送信しています…」のままにならないための守り。
  var TIMEOUT_MS = 20000;

  /* ---------------------------------------------------------- 送信 -- */

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (sending || completed) return;

    var errors = validateLocally();
    if (Object.keys(errors).length > 0) {
      setStatus('error', '入力内容をご確認ください。');
      showErrors(errors);
      return;
    }
    clearErrors();

    setSending(true);
    setStatus('busy', '送信しています…');

    // APIのキー名と完全に一致させる。余分な項目（privacy等）は送らない。
    var payload = {
      companyName: val('companyName'),
      representativeName: val('representativeName'),
      email: val('email'),
      licenseCount: Number(val('licenseCount'))
    };
    var phone = val('phone');
    if (phone !== '') payload.phone = phone;

    var controller = (typeof AbortController === 'function') ? new AbortController() : null;
    var timeoutId = controller
      ? setTimeout(function () { controller.abort(); }, TIMEOUT_MS)
      : null;

    fetch(endpoint, {
      method: 'POST',
      // 許可ヘッダーは Content-Type のみ（製品側publicCorsの設定）。他は付けない
      headers: { 'Content-Type': 'application/json' },
      credentials: 'omit',
      body: JSON.stringify(payload),
      signal: controller ? controller.signal : undefined
    })
      .then(function (res) {
        return res.json()
          .catch(function () { return null; })
          .then(function (data) { return { status: res.status, data: data }; });
      })
      .then(function (r) {
        // 受付成功（登録済み・回数制限もAPI設計上、同じ202で返る）
        if (r.status === 202 && r.data && r.data.ok === true) {
          showDone();
          return;
        }
        // APIが返す利用者向けメッセージ（入力エラー等）だけを表示する
        if (r.data && r.data.ok === false && r.data.error && r.data.error.message) {
          setStatus('error', r.data.error.message);
          return;
        }
        setStatus('error', GENERIC_FAIL);
      })
      .catch(function () {
        // 通信失敗・タイムアウト。詳細はコンソールにも出さない（自動再送もしない）
        setStatus('error', GENERIC_FAIL);
      })
      .then(function () {
        if (timeoutId) clearTimeout(timeoutId);
        setSending(false);
      });
  });

  /* ------------------------------------------------------------ 初期化 -- */

  updateQuote();
})();
