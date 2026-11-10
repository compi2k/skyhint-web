// Optional visitor statistics with the site's own Matomo server (see config 'matomo').
// No cookies. Skipped for Do Not Track, Global Privacy Control and visitors who
// switched it off on the privacy page. Only the path is sent, never the query
// string, because links from mails carry tokens there.
(function () {
  'use strict';
  var me = document.currentScript;
  var KEY = 'stats-optout';

  function optedOut() {
    try { return localStorage.getItem(KEY) === '1'; } catch (e) { return false; }
  }

  // switch on the privacy page
  document.addEventListener('DOMContentLoaded', function () {
    var box = document.querySelector('[data-stats-optout]');
    if (!box) return;
    var input = box.querySelector('input');
    var state = box.querySelector('[data-stats-state]');
    box.hidden = false;
    function show() {
      input.checked = !optedOut();
      state.textContent = input.checked ? state.dataset.on : state.dataset.off;
    }
    input.addEventListener('change', function () {
      try {
        if (input.checked) localStorage.removeItem(KEY); else localStorage.setItem(KEY, '1');
      } catch (e) { /* storage blocked: nothing is stored, nothing to switch */ }
      show();
    });
    show();
  });

  if (!me || optedOut() || navigator.doNotTrack === '1' || window.doNotTrack === '1' || navigator.globalPrivacyControl) return;

  var url = me.dataset.url;
  var _paq = window._paq = window._paq || [];
  _paq.push(['disableCookies']);
  _paq.push(['setDoNotTrack', true]);
  _paq.push(['disableBrowserFeatureDetection']);   // no screen size, plugins etc.
  _paq.push(['setCustomUrl', location.origin + location.pathname]);
  if (document.referrer.indexOf(location.origin) === 0) {
    _paq.push(['setReferrerUrl', location.origin + new URL(document.referrer).pathname]);
  }
  _paq.push(['trackPageView']);
  _paq.push(['enableLinkTracking']);
  _paq.push(['setTrackerUrl', url + 'matomo.php']);
  _paq.push(['setSiteId', me.dataset.site]);
  var s = document.createElement('script');
  s.async = true;
  s.src = url + 'matomo.js';
  document.head.appendChild(s);
})();
