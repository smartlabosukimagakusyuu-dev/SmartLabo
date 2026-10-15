(function () {
  'use strict';

  var MEASUREMENT_ID = 'G-CCSLHTF6R2';
  var STORAGE_KEY = 'slw_analytics_consent_v1';
  var GRANTED = 'granted';
  var DENIED = 'denied';
  var state = null;
  var loaded = false;

  try { state = window.localStorage.getItem(STORAGE_KEY); } catch (e) { state = null; }

  function enableTracking() {
    window['ga-disable-' + MEASUREMENT_ID] = false;
    if (loaded) return;
    loaded = true;
    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    window.gtag('config', MEASUREMENT_ID, {
      send_page_view: true,
      allow_google_signals: false,
      allow_ad_personalization_signals: false
    });

    var script = document.createElement('script');
    script.async = true;
    script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(MEASUREMENT_ID);
    document.head.appendChild(script);
  }

  function clearAnalyticsCookies() {
    document.cookie.split(';').forEach(function (part) {
      var name = part.split('=')[0].trim();
      if (name.indexOf('_ga') !== 0) return;
      document.cookie = name + '=; Max-Age=0; Path=/; SameSite=Lax';
      document.cookie = name + '=; Max-Age=0; Path=/; Domain=.smartlaboworks.com; SameSite=Lax';
    });
  }

  function setConsent(next) {
    state = next;
    try { window.localStorage.setItem(STORAGE_KEY, next); } catch (e) { /* continue */ }
    if (next === GRANTED) {
      enableTracking();
    } else {
      window['ga-disable-' + MEASUREMENT_ID] = true;
      clearAnalyticsCookies();
    }
    var banner = document.querySelector('.slw-consent');
    if (banner) banner.remove();
  }

  function showConsent() {
    var old = document.querySelector('.slw-consent');
    if (old) old.remove();
    var banner = document.createElement('section');
    banner.className = 'slw-consent';
    banner.setAttribute('role', 'dialog');
    banner.setAttribute('aria-modal', 'false');
    banner.setAttribute('aria-labelledby', 'slw-consent-title');
    banner.innerHTML =
      '<p class="slw-consent__title" id="slw-consent-title">アクセス解析について</p>' +
      '<p class="slw-consent__text">サイト改善のため、同意時のみアクセス解析を使用します。お問い合わせ内容は送信しません。詳しくは<a href="privacy.html#analytics">プライバシーポリシー</a>をご確認ください。</p>' +
      '<div class="slw-consent__actions">' +
        '<button class="slw-consent__button slw-consent__button--accept" type="button" data-slw-consent="granted">同意する</button>' +
        '<button class="slw-consent__button slw-consent__button--reject" type="button" data-slw-consent="denied">拒否する</button>' +
      '</div>';
    banner.addEventListener('click', function (event) {
      var button = event.target.closest('[data-slw-consent]');
      if (button) setConsent(button.getAttribute('data-slw-consent'));
    });
    document.body.appendChild(banner);
  }

  function sectionName(element) {
    var section = element.closest('section, header, footer');
    if (!section) return 'page';
    return section.id || section.className.split(/\s+/)[0] || section.tagName.toLowerCase();
  }

  window.slwTrack = function (eventName, parameters) {
    if (state !== GRANTED) return;
    enableTracking();
    window.gtag('event', eventName, parameters || {});
  };

  function start() {
    if (state === GRANTED) enableTracking();
    if (state !== GRANTED && state !== DENIED) showConsent();

    document.addEventListener('click', function (event) {
      var settings = event.target.closest('[data-slw-analytics-settings]');
      if (settings) {
        event.preventDefault();
        showConsent();
        return;
      }
      var downloadLink = event.target.closest('[data-slw-download="brochure"]');
      if (downloadLink) {
        window.slwTrack('brochure_download', {
          file_name: 'smart-labo-works-brochure.pdf',
          link_url: downloadLink.getAttribute('href') || '',
          link_location: sectionName(downloadLink)
        });
        return;
      }
      var link = event.target.closest('a[href*="contact.html"]');
      if (!link) return;
      window.slwTrack('cta_click', {
        cta_text: (link.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 80),
        cta_location: sectionName(link),
        link_url: link.getAttribute('href') || 'contact.html'
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
