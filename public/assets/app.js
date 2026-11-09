// City autocomplete and confirm prompts. Works without JS too (server falls back to the best match).
(function () {
  'use strict';

  // Language dropdown: switch on change, the button is only for browsers without JS.
  document.querySelectorAll('[data-lang-switch]').forEach(function (form) {
    var btn = form.querySelector('[data-lang-apply]');
    if (btn) btn.hidden = true;
    form.querySelector('select').addEventListener('change', function () { form.submit(); });
  });

  document.querySelectorAll('[data-city-picker]').forEach(function (box) {
    var input = box.querySelector('input[type=text]');
    var hidden = box.querySelector('input[type=hidden][name=city_id]');
    var list = box.querySelector('[role=listbox]');
    if (!input || !hidden || !list) return;
    var timer = null, items = [], active = -1, lastQuery = '';

    function close() {
      list.hidden = true; input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant'); active = -1;
    }
    function render() {
      list.innerHTML = '';
      items.forEach(function (c, i) {
        var li = document.createElement('li');
        li.id = list.id + '-' + i;
        li.setAttribute('role', 'option');
        li.setAttribute('aria-selected', i === active ? 'true' : 'false');
        li.textContent = c.label;
        li.addEventListener('mousedown', function (e) { e.preventDefault(); pick(i); });
        list.appendChild(li);
      });
      list.hidden = items.length === 0;
      input.setAttribute('aria-expanded', items.length ? 'true' : 'false');
      if (active >= 0) input.setAttribute('aria-activedescendant', list.id + '-' + active);
    }
    function pick(i) {
      var c = items[i]; if (!c) return;
      input.value = c.label; hidden.value = c.id; close();
      var form = input.form;
      if (form && form.getAttribute('action') === '/find') form.submit();
    }
    function search() {
      var q = input.value.trim();
      if (q.length < 2 || q === lastQuery) { if (q.length < 2) { items = []; render(); } return; }
      lastQuery = q;
      fetch('/search?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (input.value.trim() !== q) return; items = d.results || []; active = -1; render(); })
        .catch(function () { items = []; render(); });
    }

    input.addEventListener('input', function () {
      hidden.value = '';
      clearTimeout(timer); timer = setTimeout(search, 180);
    });
    input.addEventListener('keydown', function (e) {
      if (list.hidden) return;
      if (e.key === 'ArrowDown') { active = Math.min(active + 1, items.length - 1); render(); e.preventDefault(); }
      else if (e.key === 'ArrowUp') { active = Math.max(active - 1, 0); render(); e.preventDefault(); }
      else if (e.key === 'Enter' && active >= 0) { pick(active); e.preventDefault(); }
      else if (e.key === 'Escape') { close(); }
    });
    input.addEventListener('blur', function () { setTimeout(close, 120); });
  });

  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!window.confirm(f.getAttribute('data-confirm'))) e.preventDefault();
    });
  });
})();
