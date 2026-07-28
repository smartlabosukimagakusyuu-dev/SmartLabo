/* ==========================================================================
   Smart Labo Works — Website v2 / main.js
   --------------------------------------------------------------------------
   v1(WEBSITE/js/*.js)と同じく、ビルド不要の素のブラウザJSで書く。
   バンドラ・フレームワーク・外部CDNへの依存を追加しないこと。

   担当するのは次の4つだけ。スクロールジャックや位置の乗っ取りは行わない。
     1. モバイルナビの開閉
     2. 現在ページのナビ強調
     3. スクロール時のヘッダー境界線
     4. 出現アニメーション(フェード+わずかな上方向スライド)
   ========================================================================== */

(function () {
  'use strict';

  var NAV_BREAKPOINT = '(max-width: 1080px)';

  /* ---------------------------------------------- モバイルナビの開閉 -- */

  var toggle = document.querySelector('[data-nav-toggle]');
  var nav = document.getElementById('site-nav');

  if (toggle && nav) {
    var mq = window.matchMedia(NAV_BREAKPOINT);

    // ブレークポイント超では常に表示、以下では既定で閉じる。
    // 表示可否の最終判断はCSS側にもあるため、JSが失敗してもナビは消えない。
    var syncToViewport = function () {
      if (mq.matches) {
        nav.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
      } else {
        nav.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
      }
    };

    syncToViewport();
    mq.addEventListener('change', syncToViewport);

    toggle.addEventListener('click', function () {
      var willOpen = nav.hidden;
      nav.hidden = !willOpen;
      toggle.setAttribute('aria-expanded', String(willOpen));
    });

    // ページ内リンクを押したらドロワーを閉じる(移動先が隠れないように)
    nav.addEventListener('click', function (e) {
      if (mq.matches && e.target.closest('a')) {
        nav.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
      }
    });

    // Escapeで閉じ、フォーカスをトグルへ戻す(キーボード操作の行き止まり防止)
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && mq.matches && !nav.hidden) {
        nav.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
        toggle.focus();
      }
    });
  }

  /* -------------------------------------------- 現在ページのナビ強調 -- */

  // URLの末尾は環境によって形が変わる(GitHub Pagesは "/features.html"、
  // ローカルの静的サーバーはクリーンURLの "/features" を返すことがある)。
  // どちらでも一致するよう、拡張子を落とし、空なら "index" として比較する。
  var normalize = function (p) {
    var last = p.split('/').pop().split('?')[0].split('#')[0];
    return last.replace(/\.html$/, '') || 'index';
  };

  var here = normalize(location.pathname);

  document.querySelectorAll('#site-nav a[href]').forEach(function (a) {
    var href = a.getAttribute('href');
    // ページ内アンカー(#solution 等)は現在ページ扱いにしない
    if (href.charAt(0) === '#') return;
    if (normalize(href) === here) a.setAttribute('aria-current', 'page');
  });

  /* ------------------------------------ スクロール時のヘッダー境界線 -- */

  var header = document.getElementById('siteHeader');

  if (header) {
    var ticking = false;
    var updateHeader = function () {
      header.classList.toggle('is-scrolled', window.scrollY > 8);
      ticking = false;
    };
    updateHeader();
    window.addEventListener('scroll', function () {
      if (!ticking) { ticking = true; requestAnimationFrame(updateHeader); }
    }, { passive: true });
  }


  /* -------------------------------------------------- FAQ アコーディオン -- */
  /* 回答はHTMLに直接書かれており、初期状態では開いている。
     JSが動いたときだけ閉じる(プログレッシブエンハンスメント)。
     こうしておくとJSが失敗しても、質問と回答は必ず読める。 */

  document.querySelectorAll('[data-faq]').forEach(function (faq) {
    var buttons = faq.querySelectorAll('.faq__btn');

    buttons.forEach(function (btn, i) {
      var panel = document.getElementById(btn.getAttribute('aria-controls'));
      if (!panel) return;

      // 先頭だけ開いた状態で始める
      var open = i === 0;
      btn.setAttribute('aria-expanded', String(open));
      panel.hidden = !open;

      btn.addEventListener('click', function () {
        var willOpen = btn.getAttribute('aria-expanded') !== 'true';
        btn.setAttribute('aria-expanded', String(willOpen));
        panel.hidden = !willOpen;
      });
    });
  });

  /* ---------------------------------------------- 出現アニメーション -- */

  var revealables = document.querySelectorAll('.reveal');

  if (revealables.length) {
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var revealAll = function () {
      revealables.forEach(function (el) {
        el.style.transitionDelay = '';
        el.classList.add('is-in');
      });
    };

    // IntersectionObserver が無い環境・動きを減らす設定では、すべて即時表示にする
    if (reduceMotion || !('IntersectionObserver' in window)) {
      revealAll();
    } else {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) return;
          entry.target.classList.add('is-in');
          io.unobserve(entry.target);
        });
      }, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });

      revealables.forEach(function (el, i) {
        // 初期表示の位置にある要素はアニメーションさせず、すぐ出す。
        // 折りたたみより上の内容を遅らせると、その分だけLCP（最大の要素が
        // 描画される時刻）が後ろへずれてしまうため。
        if (el.getBoundingClientRect().top < window.innerHeight) {
          // トランジションも切る。class を足すだけだと 0 → 1 のフェードが
          // 走り、その分だけ描画完了が遅れてしまう。
          el.style.transition = 'none';
          el.classList.add('is-in');
          return;
        }
        // 同じ行のカードが揃って出るよう、ごく短い段差だけ付ける
        el.style.transitionDelay = Math.min(i % 6, 5) * 45 + 'ms';
        io.observe(el);
      });

      // 保険。タブが描画されていない等でIntersectionObserverが発火しない場合でも、
      // 本文が透明なまま残らないようにする。演出は本文の可読性より優先しない。
      setTimeout(function () {
        io.disconnect();
        revealAll();
      }, 2000);
    }
  }
})();
