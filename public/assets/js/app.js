/**
 * stockvel UI — progressive enhancement, no frameworks.
 */
(function () {
  'use strict';

  var doc = document;

  function qs(sel, root) { return (root || doc).querySelector(sel); }
  function qsa(sel, root) { return Array.prototype.slice.call((root || doc).querySelectorAll(sel)); }

  function initDrawer() {
    var toggle = qs('[data-drawer-toggle]');
    var drawer = doc.getElementById('app-drawer');
    if (!toggle || !drawer) return;
    var panel = qs('.drawer-panel', drawer);
    drawer.removeAttribute('hidden');

    function open() {
      drawer.classList.add('is-open');
      toggle.setAttribute('aria-expanded', 'true');
      doc.body.style.overflow = 'hidden';
      if (panel) {
        var focusable = qs('a, button', panel);
        if (focusable) focusable.focus();
      }
    }
    function close() {
      drawer.classList.remove('is-open');
      toggle.setAttribute('aria-expanded', 'false');
      doc.body.style.overflow = '';
      toggle.focus();
    }

    toggle.addEventListener('click', function () {
      drawer.classList.contains('is-open') ? close() : open();
    });
    drawer.addEventListener('click', function (e) {
      if (e.target === drawer) close();
    });
    qsa('a', panel || drawer).forEach(function (link) {
      link.addEventListener('click', close);
    });
    doc.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && drawer.classList.contains('is-open')) close();
    });
  }

  function initDropdowns() {
    qsa('[data-dropdown]').forEach(function (wrap) {
      var btn = qs('[data-dropdown-toggle]', wrap);
      if (!btn) return;
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var open = wrap.classList.toggle('is-open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        qsa('[data-dropdown].is-open').forEach(function (other) {
          if (other !== wrap) {
            other.classList.remove('is-open');
            var ob = qs('[data-dropdown-toggle]', other);
            if (ob) ob.setAttribute('aria-expanded', 'false');
          }
        });
      });
    });
    doc.addEventListener('click', function () {
      qsa('[data-dropdown].is-open').forEach(function (wrap) {
        wrap.classList.remove('is-open');
        var btn = qs('[data-dropdown-toggle]', wrap);
        if (btn) btn.setAttribute('aria-expanded', 'false');
      });
    });
  }

  function initModals() {
    qsa('[data-modal-open]').forEach(function (trigger) {
      trigger.addEventListener('click', function (e) {
        e.preventDefault();
        var id = trigger.getAttribute('data-modal-open');
        var modal = id ? doc.getElementById(id) : null;
        if (!modal) return;
        modal.classList.add('is-open');
        modal.removeAttribute('hidden');
      });
    });
    qsa('[data-modal-close]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var backdrop = btn.closest('.modal-backdrop');
        if (backdrop) {
          backdrop.classList.remove('is-open');
          backdrop.setAttribute('hidden', 'hidden');
        }
      });
    });
  }

  function initConfirm() {
    var pending = null;
    var backdrop = doc.getElementById('confirm-modal');
    var messageEl = doc.getElementById('confirm-message');
    var accept = doc.getElementById('confirm-accept');

    qsa('[data-confirm]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        var msg = el.getAttribute('data-confirm') || 'Are you sure?';
        if (!backdrop || !accept) {
          if (!window.confirm(msg)) e.preventDefault();
          return;
        }
        e.preventDefault();
        pending = el;
        if (messageEl) messageEl.textContent = msg;
        backdrop.classList.add('is-open');
        backdrop.removeAttribute('hidden');
      });
    });

    if (accept) {
      accept.addEventListener('click', function () {
        if (!pending) return;
        var el = pending;
        pending = null;
        if (backdrop) {
          backdrop.classList.remove('is-open');
          backdrop.setAttribute('hidden', 'hidden');
        }
        if (el.tagName === 'A') {
          window.location.href = el.href;
        } else if (el.form) {
          el.form.requestSubmit ? el.form.requestSubmit(el) : el.form.submit();
        } else {
          el.click();
        }
      });
    }
  }

  function initToasts() {
    var stack = qs('[data-toast-stack]');
    if (!stack) return;
    qsa('.alert[role="alert"]').forEach(function (alert, index) {
      if (index > 2) return;
      var toast = doc.createElement('div');
      toast.className = 'toast';
      if (alert.classList.contains('alert-success')) toast.classList.add('toast-success');
      if (alert.classList.contains('alert-danger')) toast.classList.add('toast-danger');
      toast.textContent = alert.textContent.trim();
      stack.appendChild(toast);
      setTimeout(function () { toast.remove(); }, 5000);
    });
  }

  function initPasswordToggle() {
    qsa('[data-password-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-password-toggle');
        var input = id ? doc.getElementById(id) : null;
        if (!input) return;
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.textContent = show ? 'Hide' : 'Show';
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      });
    });
  }

  function initFormLoading() {
    qsa('form[method="post"], form[method="POST"]').forEach(function (form) {
      form.addEventListener('submit', function () {
        qsa('button[type="submit"], input[type="submit"]', form).forEach(function (btn) {
          btn.classList.add('is-loading');
          btn.disabled = true;
          if (btn.tagName === 'BUTTON' && !btn.dataset.originalText) {
            btn.dataset.originalText = btn.textContent;
            btn.textContent = 'Please wait…';
          }
        });
      });
    });
  }

  function initTableFilter() {
    qsa('[data-table-filter]').forEach(function (input) {
      var target = input.getAttribute('data-table-filter');
      var table = target ? doc.getElementById(target) : null;
      if (!table) return;
      input.addEventListener('input', function () {
        var q = input.value.toLowerCase();
        qsa('tbody tr', table).forEach(function (row) {
          row.style.display = row.textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
        });
      });
    });
  }

  function initTabs() {
    qsa('[data-tabs]').forEach(function (root) {
      var tabs = qsa('[role="tab"]', root);
      tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
          var panelId = tab.getAttribute('aria-controls');
          tabs.forEach(function (t) {
            t.classList.remove('is-active');
            t.setAttribute('aria-selected', 'false');
          });
          tab.classList.add('is-active');
          tab.setAttribute('aria-selected', 'true');
          qsa('[role="tabpanel"]', root).forEach(function (panel) {
            panel.hidden = panel.id !== panelId;
          });
        });
      });
    });
  }

  window.stockvelCharts = {
    bar: function (container, labels, values, color) {
      if (!container) return;
      var max = Math.max.apply(null, values.concat([1]));
      var w = 480, h = 220, pad = 28;
      var barW = (w - pad * 2) / Math.max(values.length, 1) * 0.65;
      var paths = values.map(function (v, i) {
        var x = pad + i * ((w - pad * 2) / Math.max(values.length, 1));
        var bh = ((h - pad * 2) * v) / max;
        var y = h - pad - bh;
        return '<rect x="' + x + '" y="' + y + '" width="' + barW + '" height="' + bh + '" rx="4" fill="' + (color || '#14532d') + '"></rect>' +
          '<text x="' + (x + barW / 2) + '" y="' + (h - 8) + '" text-anchor="middle" font-size="10" fill="#647067">' + labels[i] + '</text>';
      }).join('');
      container.innerHTML = '<svg viewBox="0 0 ' + w + ' ' + h + '" role="img" aria-label="Bar chart">' + paths + '</svg>';
    }
  };

  function initCharts() {
    qsa('[data-chart]').forEach(function (el) {
      try {
        var labels = JSON.parse(el.getAttribute('data-labels') || '[]');
        var values = JSON.parse(el.getAttribute('data-values') || '[]');
        window.stockvelCharts.bar(el, labels, values, el.getAttribute('data-color') || '#14532d');
      } catch (err) { /* keep empty state */ }
    });
  }

  doc.addEventListener('DOMContentLoaded', function () {
    initDrawer();
    initDropdowns();
    initModals();
    initConfirm();
    initToasts();
    initPasswordToggle();
    initFormLoading();
    initTableFilter();
    initTabs();
    initCharts();
  });
})();
