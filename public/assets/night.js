// Red night-vision mode. Loaded early (not deferred) so the page never flashes
// in normal colours first. The choice stays in this browser only.
(function () {
  'use strict';
  var KEY = 'night-red', root = document.documentElement;
  function get() { try { return localStorage.getItem(KEY) === '1'; } catch (e) { return false; } }
  function set(on) {
    root.toggleAttribute('data-night-red', on);
    try { if (on) localStorage.setItem(KEY, '1'); else localStorage.removeItem(KEY); } catch (e) { /* not stored */ }
    document.querySelectorAll('[data-night-toggle]').forEach(function (b) { b.setAttribute('aria-pressed', on ? 'true' : 'false'); });
  }
  if (get()) root.setAttribute('data-night-red', '');
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-night-toggle]').forEach(function (b) {
      b.hidden = false;
      b.setAttribute('aria-pressed', get() ? 'true' : 'false');
      b.addEventListener('click', function () { set(!root.hasAttribute('data-night-red')); });
    });
  });
})();
