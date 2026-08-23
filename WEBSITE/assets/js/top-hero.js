/* ==========================================================================
   Smart Labo — 会社TOP / top-hero.js（P32-3 Phase 1F）
   --------------------------------------------------------------------------
   担当するのは2つだけ。外部ライブラリ・ビルド・依存なし。
     1. ファーストビューの実画面スライダー（静かな切替・手動操作・キーボード・自動切替）
     2. デモ動画モーダル（ボタンが表示されている場合のみ。動画未完成時はボタン非表示のまま）

   原則
     ・JSが無くても1枚目（接客準備）は必ず表示される（CSS側で first slide を表示）。
     ・このファイルが失敗しても本文・CTAには影響しない。
     ・prefers-reduced-motion では自動切替を行わない。
     ・手動操作（矢印・ドット・キー）をしたら自動切替は止める。
     ・タブが非表示の間は自動切替を止め、戻ったら再開する（手動操作前のみ）。
   ========================================================================== */

(function () {
  'use strict';

  /* ------------------------------------------------- 1. Hero スライダー -- */

  var root = document.querySelector('[data-hero-slider]');
  if (root) {
    var slides = Array.prototype.slice.call(root.querySelectorAll('[data-slide]'));
    var dots = Array.prototype.slice.call(root.querySelectorAll('[data-slide-to]'));
    var prevBtn = root.querySelector('[data-slide-prev]');
    var nextBtn = root.querySelector('[data-slide-next]');
    var stage = root.querySelector('[data-slide-stage]');
    var INTERVAL = 5000;
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var current = 0;
    var timer = null;
    var userTookOver = false;
    var hovering = false;
    var focused = false;

    if (slides.length > 1) {
      root.classList.add('is-ready');

      var show = function (index) {
        var next = (index + slides.length) % slides.length;
        if (next === current) return;
        var from = slides[current];
        var to = slides[next];
        // 非表示スライドは hidden（display:none）にしておき、表示するときだけ外す。
        // hidden のままだと lazy 画像が読まれないので、初期表示の転送量を増やさない。
        to.hidden = false;
        // 1フレーム待ってから class を切り替え、opacity の transition を効かせる
        window.requestAnimationFrame(function () {
          window.requestAnimationFrame(function () {
            to.classList.add('is-active');
            to.setAttribute('aria-hidden', 'false');
            from.classList.remove('is-active');
            from.setAttribute('aria-hidden', 'true');
            var done = function () {
              if (!from.classList.contains('is-active')) from.hidden = true;
              from.removeEventListener('transitionend', done);
            };
            if (reduceMotion) { from.hidden = true; } else { from.addEventListener('transitionend', done); setTimeout(done, 700); }
          });
        });
        dots.forEach(function (d, i) {
          var on = i === next;
          d.setAttribute('aria-current', on ? 'true' : 'false');
          d.classList.toggle('is-active', on);
        });
        current = next;
      };

      var stop = function () { if (timer) { clearInterval(timer); timer = null; } };
      var start = function () {
        if (reduceMotion || userTookOver || hovering || focused || document.hidden) return;
        stop();
        timer = setInterval(function () { show(current + 1); }, INTERVAL);
      };
      var takeOver = function () {
        userTookOver = true;
        stop();
        // 自動切替が止まった後は、切替を読み上げてよい（自動では読み上げない）
        if (stage) stage.setAttribute('aria-live', 'polite');
      };

      if (prevBtn) prevBtn.addEventListener('click', function () { takeOver(); show(current - 1); });
      if (nextBtn) nextBtn.addEventListener('click', function () { takeOver(); show(current + 1); });
      dots.forEach(function (d) {
        d.addEventListener('click', function () {
          takeOver();
          show(Number(d.getAttribute('data-slide-to')) || 0);
        });
      });

      // キーボード：スライダー内にフォーカスがあるとき ← → で移動
      root.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowLeft') { e.preventDefault(); takeOver(); show(current - 1); }
        if (e.key === 'ArrowRight') { e.preventDefault(); takeOver(); show(current + 1); }
      });

      // hover / focus 中は自動切替を止める（読んでいる途中で切り替わらないように）
      root.addEventListener('mouseenter', function () { hovering = true; stop(); });
      root.addEventListener('mouseleave', function () { hovering = false; start(); });
      root.addEventListener('focusin', function () { focused = true; stop(); });
      root.addEventListener('focusout', function () {
        // フォーカスがスライダーの外へ出たときだけ再開
        setTimeout(function () { if (!root.contains(document.activeElement)) { focused = false; start(); } }, 0);
      });
      document.addEventListener('visibilitychange', function () { if (document.hidden) stop(); else start(); });
      window.addEventListener('pagehide', stop);

      start();
    }
  }

  /* ------------------------------------------------- 2. デモ動画モーダル -- */
  /* ボタン（[data-video-open]）は、動画が用意できるまで HTML 側で hidden のまま。
     hidden を外し、<video> の data-src に正式URLを入れた時点で有効になる。
     ここでは「存在しない動画URLを開かない」ため、src が空なら何もしない。 */

  var openBtn = document.querySelector('[data-video-open]');
  var dialog = document.getElementById('sl-video-dialog');
  if (openBtn && dialog && typeof dialog.showModal === 'function') {
    var video = dialog.querySelector('video');
    var src = video ? (video.getAttribute('data-src') || '') : '';
    if (!openBtn.hidden && src) {
      openBtn.addEventListener('click', function () {
        if (!video.getAttribute('src')) video.setAttribute('src', src);
        dialog.showModal();
        video.play && video.play().catch(function () { /* 自動再生不可でも再生ボタンで再生できる */ });
      });
      var close = function () { try { video.pause(); } catch (e) { /* noop */ } dialog.close(); };
      dialog.querySelectorAll('[data-video-close]').forEach(function (b) { b.addEventListener('click', close); });
      dialog.addEventListener('click', function (e) { if (e.target === dialog) close(); });
      dialog.addEventListener('close', function () { try { video.pause(); } catch (e) { /* noop */ } });
    }
  }
})();
