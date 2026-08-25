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
  /**
   * 旧URLの読み替え（WEB-V3-SALON-URGENT-4-R1）。
   * 画面の種別を4件（docs / consult / contact / recruit）へ整理したため、
   * 既に外部へ共有された可能性のある旧URLが「選択なし」で着地しないようにする。
   *   ?type=demo    → consult（無料相談）  ※Salon相談の正式URLは type=consult&topic=salon
   *   ?type=partner → contact（一般お問い合わせ）
   * ★読み替え先は必ず「画面に存在するoption」。削除したoptionは復活させない
   *   （下の querySelector による存在確認でも二重に守っている）。
   * ★contact-api 側の許可値（SLW_TYPES）は変更していない。
   */
  var LEGACY_TYPES = { demo: 'consult', partner: 'contact' };

  if ('URLSearchParams' in window) {
    var typeParam = new URLSearchParams(location.search).get('type');
    var typeSelect = document.getElementById('f-type');
    if (typeParam && Object.prototype.hasOwnProperty.call(LEGACY_TYPES, typeParam)) {
      typeParam = LEGACY_TYPES[typeParam];
    }
    if (typeParam && typeSelect && /^[a-z0-9_-]+$/.test(typeParam) &&
        typeSelect.querySelector('option[value="' + typeParam + '"]')) {
      typeSelect.value = typeParam;
    }
  }

  /* ------------------------------ 相談内容の識別(WEB-V3-SALON-URGENT-2) -- */

  /**
   * どの商品についての相談かを、問い合わせ本文の先頭1行で識別できるようにする。
   *
   * 背景: サーバー側(contact-api)の検証は許可された項目だけを組み直してメールを
   * 作るため、hidden項目を足しても黙って捨てられ、件名にも本文にも現れない。
   * 種別(type)は「資料請求／無料相談／一般お問い合わせ…」という問い合わせの"種類"であって
   * 商品名ではないため、Salon相談とWorks相談が同じ「無料相談」として届いてしまう。
   *
   * そこで、API・PHP・XServerを一切変更せずに識別する方法として、
   * 本文(message)の先頭へ識別行を入れておく。利用者が消すことも書き換えることも
   * できる、あくまで下書きの補助である。
   */
  var TOPIC_LINES = {
    salon:   '【ご相談内容】Smart Labo Salon',
    website: '【ご相談内容】店舗ホームページ制作',
    works:   '【ご相談内容】Smart Labo Works'
  };

  if ('URLSearchParams' in window) {
    var topicParam = new URLSearchParams(location.search).get('topic');
    var messageEl = document.getElementById('f-message');

    // 未知のtopicは無視する（TOPIC_LINES に無いキーは何もしない）。
    // hasOwnProperty で照合し、'toString' などの継承プロパティを拾わないようにする。
    if (topicParam && messageEl &&
        Object.prototype.hasOwnProperty.call(TOPIC_LINES, topicParam)) {
      var line = TOPIC_LINES[topicParam];
      var current = messageEl.value;

      // 同じ識別行が既にあるなら二重に入れない。
      // （戻る操作やブラウザの入力復元で再実行されても増えない）
      var NL = String.fromCharCode(10);
      var already = current.split(NL).some(function (row) {
        return row.trim() === line;
      });

      if (!already) {
        // 既に入力がある場合も内容は壊さず、先頭へ差し込むだけにする。
        // textContent/value への代入のみで、innerHTML は使わない（XSSを作らない）。
        messageEl.value = (current === '') ? line + NL + NL
                                          : line + NL + NL + current;
      }
    }
  }

  /* ------------------ 遷移元商品の識別表示（WEB-V3-ROUTING-CLARITY-1） -- */

  /**
   * どの商品の相談として来たのかを、フォーム直前に控えめに示す。
   * ★topicが無い場合は何も表示しない（商品を勝手に選ばない）。
   * ★textContentへの代入のみ。innerHTMLは使わない。
   * ★送信payloadには影響しない（表示だけ）。本文の識別行は従来どおり
   *   TOPIC_LINES / composeInterests が担当する。
   */
  var TOPIC_BANNERS = {
    salon: {
      title: 'Smart Labo Salonのご相談',
      note: '予約・顧客管理・接客メモ・再来店支援など、気になる内容を選んでご相談いただけます。'
    },
    website: {
      title: '店舗ホームページ制作のご相談',
      note: '新規制作・リニューアル・更新方法などについてご相談いただけます。'
    },
    works: {
      title: 'Smart Labo Worksのご相談',
      note: '法人向けAI活用・業務支援についてご相談いただけます。'
    }
  };

  (function showTopicBanner() {
    var box = document.getElementById('topic-banner');
    if (!box || !('URLSearchParams' in window)) return;
    var t = new URLSearchParams(location.search).get('topic');
    if (!t || !Object.prototype.hasOwnProperty.call(TOPIC_BANNERS, t)) return;

    var titleEl = document.getElementById('topic-banner-title');
    var noteEl = document.getElementById('topic-banner-note');
    if (!titleEl || !noteEl) return;

    titleEl.textContent = TOPIC_BANNERS[t].title;
    noteEl.textContent = TOPIC_BANNERS[t].note;

    // hidden属性だけでは消えない/出ない場合に備え、displayも明示する
    box.hidden = false;
    box.style.display = 'block';
  })();

  /* --------------------- 機能選択fieldset（WEB-V3-SALON-URGENT-4 / R2） -- */

  /**
   * Salon相談のときだけ「気になる機能」fieldsetを表示する。
   *
   * 表示条件（R2で確定）: ?topic=salon 「かつ」種別が consult（無料相談）。
   *   ・topic だけで判定すると、Salonの相談画面から「採用について」等へ
   *     種別を変えても機能一覧が残り、Salon相談として誤って届く恐れがある。
   *   ・type だけで判定すると、店舗ホームページ制作(topic=website)や
   *     Works(topic=works)の無料相談にもSalonの機能一覧が出てしまう。
   *   両方を満たすときだけ表示する。
   *
   * 正式URL: contact.html?type=consult&topic=salon#interests
   * 旧URL   : contact.html?type=demo&topic=salon#interests
   *           （demo → consult へ読み替え済みのため、そのまま表示される）
   *
   * ★checkboxにはnameが無いので、表示/非表示に関わらずFormDataへは一切入らない。
   * ★選択状態は画面上そのまま残す（種別を戻せば元の選択で再開できる）。
   *   ただし条件を満たさない間は composeInterests が本文へ一切反映しない。
   */
  var interestsFieldset = document.getElementById('interests');

  /** URLで指定された相談内容（topic）。ページを開いた時点で固定する */
  var urlTopic = null;
  if ('URLSearchParams' in window) {
    urlTopic = new URLSearchParams(location.search).get('topic');
  }
  var isSalonTopic = urlTopic === 'salon';

  /** いま「Salonの無料相談」として扱ってよい状態か */
  function isSalonConsult() {
    if (!isSalonTopic) return false;
    var typeEl = document.getElementById('f-type');
    return !!typeEl && typeEl.value === 'consult';
  }

  /** fieldset内の選択済みラベルを、グループ名（now / future）ごとに集める */
  function collectInterests(group) {
    if (!interestsFieldset) return [];
    var box = interestsFieldset.querySelector('[data-interest-group="' + group + '"]');
    if (!box) return [];
    var out = [];
    box.querySelectorAll('input[type="checkbox"][data-interest]').forEach(function (cb) {
      // data-interest の無い/空のものは無視する（未知値を拾わない）
      var label = cb.getAttribute('data-interest');
      if (cb.checked && label) out.push(label);
    });
    return out;
  }

  var INTEREST_MARK = '【気になる機能】';
  var FUTURE_MARK = '【今後の希望】';
  var CONSULT_MARK = '【ご相談内容】';

  /**
   * Salon相談の識別ブロックを message の先頭に組み立て直す。
   *
   * ・?topic=salon のページでのみ動く。他のtopic（website / works）や
   *   topic無しのページでは何もしない（従来の挙動を変えない）。
   * ・Salon相談として成立している間だけ、
   *     【ご相談内容】Smart Labo Salon
   *     【気になる機能】…
   *     【今後の希望】…
   *   を先頭に置く。
   * ・成立していない間（採用について等へ変更した状態）は、上の3種の行を
   *   すべて取り除く。Salon相談として誤って届くことを構造的に防ぐ。
   * ・自由記述はどちらの場合も保持する。
   * ・毎回「取り除いてから組み立て直す」ため、何度実行しても重複しない。
   *
   * 種別変更時と送信直前の両方で呼ぶ。
   *   - 種別変更時にも整えるのは、fetchが使えない環境では送信ハンドラが
   *     何もせず通常POSTになるため。画面の内容＝送られる内容にしておく。
   *   - 送信直前にも呼ぶことで、最終的な選択状態が必ず反映される。
   * 結果は textarea へ書き戻し、そのあと既存の validateLocally が走るので、
   * 3000字超過は既存のエラー表示で止まる。
   */
  function composeInterests() {
    if (!isSalonTopic) return;
    var messageEl2 = document.getElementById('f-message');
    if (!messageEl2) return;

    var active = isSalonConsult();
    var nowSel = active ? collectInterests('now') : [];
    var futSel = active ? collectInterests('future') : [];

    var NL2 = String.fromCharCode(10);
    var salonLine = TOPIC_LINES.salon;

    // 先頭の識別ブロック（【…】行と空行の連なり）を読み飛ばし、本文の開始位置を探す
    var lines = messageEl2.value.split(NL2);
    var i = 0;
    while (i < lines.length) {
      var t = lines[i].trim();
      if (t === '') { i++; continue; }
      if (t === salonLine ||
          t.indexOf(INTEREST_MARK) === 0 ||
          t.indexOf(FUTURE_MARK) === 0) { i++; continue; }
      break;
    }

    // 本文（自由記述）。本文中に紛れ込んだ整形行も取り除く（重複を残さない保険）
    var body = lines.slice(i).filter(function (row) {
      var t2 = row.trim();
      return t2 !== salonLine &&
             t2.indexOf(INTEREST_MARK) !== 0 &&
             t2.indexOf(FUTURE_MARK) !== 0;
    }).join(NL2);

    var header = [];
    if (active) {
      header.push(salonLine);
      if (nowSel.length > 0) header.push(INTEREST_MARK + nowSel.join('／'));
      if (futSel.length > 0) header.push(FUTURE_MARK + futSel.join('／'));
    }

    var next;
    if (header.length === 0) {
      next = body;
    } else if (body.trim() === '') {
      next = header.join(NL2) + NL2 + NL2;
    } else {
      next = header.join(NL2) + NL2 + NL2 + body;
    }

    // 値への代入のみ（innerHTMLは使わない）
    messageEl2.value = next;
  }

  /**
   * fieldsetの表示を切り替える。
   * ★hidden属性だけでは消えない: components.css の `.field { display: grid }`（著者スタイル）が
   *   UAの `[hidden] { display: none }` より優先されるため。
   *   支援技術向けに hidden属性を、実際の描画のために inline style を、両方そろえる。
   */
  function setInterestsVisible(show) {
    if (!interestsFieldset) return;
    interestsFieldset.hidden = !show;
    interestsFieldset.style.display = show ? '' : 'none';
  }

  /** 表示状態と本文を、いまの種別に合わせてそろえる */
  function syncInterests() {
    setInterestsVisible(isSalonConsult());
    composeInterests();
  }

  if (interestsFieldset) {
    // 読み込み時は表示状態だけを整える（本文は上の topic 初期化が済ませている）
    setInterestsVisible(isSalonConsult());

    var typeSelectForInterests = document.getElementById('f-type');
    if (typeSelectForInterests) {
      // 種別を変えたら、その場で表示と本文の両方をそろえる
      typeSelectForInterests.addEventListener('change', syncInterests);
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

    // 機能選択を message へ整形してから検証する（3000字超過は既存エラーで止まる）
    composeInterests();

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
