/* ==========================================================================
   Smart Labo Works — Website v2 / main.js
   --------------------------------------------------------------------------
   v1(WEBSITE/js/*.js)と同じく、ビルド不要の素のブラウザJSで書く。
   バンドラ・フレームワーク・外部CDNへの依存を追加しないこと。
   この段階では「モバイルナビの開閉」と「現在ページのナビ強調」だけを実装する。
   ========================================================================== */

(function () {
  'use strict';

  /* ---------------------------------------------- モバイルナビの開閉 -- */

  var toggle = document.querySelector('[data-nav-toggle]');
  var nav = document.getElementById('site-nav');

  if (toggle && nav) {
    var mq = window.matchMedia('(max-width: 860px)');

    // 860px超では常に表示、以下では既定で閉じる。
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

    // Escapeで閉じ、フォーカスをトグルへ戻す(キーボード操作の行き止まり防止)。
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
    if (normalize(a.getAttribute('href')) === here) {
      a.setAttribute('aria-current', 'page');
    }
  });
})();
