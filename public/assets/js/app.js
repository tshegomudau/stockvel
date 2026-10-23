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

  function initWysiwyg() {
    qsa('[data-wysiwyg]').forEach(function (ta) {
      if (ta.getAttribute('data-wysiwyg-ready') === '1') return;
      ta.setAttribute('data-wysiwyg-ready', '1');
      var wrap = doc.createElement('div');
      wrap.className = 'wysiwyg';
      if (ta.classList.contains('wysiwyg--tall') || ta.getAttribute('data-wysiwyg-size') === 'tall') {
        wrap.classList.add('wysiwyg--tall');
      }
      var toolbar = doc.createElement('div');
      toolbar.className = 'wysiwyg__toolbar';
      toolbar.setAttribute('role', 'toolbar');
      var editor = doc.createElement('div');
      editor.className = 'wysiwyg__editor';
      editor.contentEditable = 'true';
      editor.setAttribute('role', 'textbox');
      editor.setAttribute('aria-multiline', 'true');
      editor.setAttribute('spellcheck', 'true');
      if (ta.getAttribute('aria-label')) {
        editor.setAttribute('aria-label', ta.getAttribute('aria-label'));
      }
      editor.innerHTML = ta.value || '';
      ta.classList.add('is-hidden');
      ta.parentNode.insertBefore(wrap, ta);
      wrap.appendChild(toolbar);
      wrap.appendChild(editor);
      wrap.appendChild(ta);

      function sync() {
        ta.value = editor.innerHTML;
      }

      function cmd(command, value) {
        try {
          doc.execCommand(command, false, value || null);
        } catch (e) { /* ignore unsupported */ }
        editor.focus();
        sync();
      }

      function promptLink() {
        var url = window.prompt('Link URL (https:// or /path)', 'https://');
        if (!url) return;
        url = String(url).trim();
        if (!/^(https?:\/\/|mailto:|\/|#|\.\/)/i.test(url)) {
          window.alert('Only http(s), mailto, or relative links are allowed.');
          return;
        }
        cmd('createLink', url);
      }

      function addSep() {
        var sep = doc.createElement('span');
        sep.className = 'wysiwyg__sep';
        sep.setAttribute('aria-hidden', 'true');
        toolbar.appendChild(sep);
      }

      var buttons = [
        { label: 'H2', title: 'Heading', action: function () { cmd('formatBlock', 'h2'); } },
        { label: 'H3', title: 'Subheading', action: function () { cmd('formatBlock', 'h3'); } },
        { label: '¶', title: 'Paragraph', action: function () { cmd('formatBlock', 'p'); } },
        null,
        { label: 'B', title: 'Bold', action: function () { cmd('bold'); } },
        { label: 'I', title: 'Italic', action: function () { cmd('italic'); } },
        { label: 'U', title: 'Underline', action: function () { cmd('underline'); } },
        { label: 'S', title: 'Strikethrough', action: function () { cmd('strikeThrough'); } },
        null,
        { label: '•', title: 'Bullet list', action: function () { cmd('insertUnorderedList'); } },
        { label: '1.', title: 'Numbered list', action: function () { cmd('insertOrderedList'); } },
        { label: '“', title: 'Quote', action: function () { cmd('formatBlock', 'blockquote'); } },
        null,
        { label: '🔗', title: 'Insert link', action: promptLink },
        { label: '⌀', title: 'Remove link', action: function () { cmd('unlink'); } },
        { label: '—', title: 'Horizontal rule', action: function () { cmd('insertHorizontalRule'); } },
        null,
        { label: '↺', title: 'Undo', action: function () { cmd('undo'); } },
        { label: '↻', title: 'Redo', action: function () { cmd('redo'); } },
        { label: '⌫', title: 'Clear formatting', action: function () { cmd('removeFormat'); } }
      ];

      buttons.forEach(function (btnDef) {
        if (!btnDef) {
          addSep();
          return;
        }
        var btn = doc.createElement('button');
        btn.type = 'button';
        btn.className = 'wysiwyg__btn';
        btn.title = btnDef.title;
        btn.setAttribute('aria-label', btnDef.title);
        btn.textContent = btnDef.label;
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          btnDef.action();
        });
        toolbar.appendChild(btn);
      });

      // Text-only editor: block image/file drops and paste of binary content.
      editor.addEventListener('dragover', function (e) { e.preventDefault(); });
      editor.addEventListener('drop', function (e) {
        e.preventDefault();
        var text = e.dataTransfer && e.dataTransfer.getData('text/plain');
        if (text) {
          cmd('insertText', text);
        }
      });
      editor.addEventListener('paste', function (e) {
        var items = e.clipboardData && e.clipboardData.items;
        if (!items) return;
        var i;
        for (i = 0; i < items.length; i++) {
          if (items[i].type && items[i].type.indexOf('image') === 0) {
            e.preventDefault();
            return;
          }
        }
        if (e.clipboardData.files && e.clipboardData.files.length) {
          e.preventDefault();
          var plain = e.clipboardData.getData('text/plain');
          if (plain) {
            cmd('insertText', plain);
          }
        }
      });

      editor.addEventListener('input', sync);
      editor.addEventListener('blur', sync);
      var form = ta.closest('form');
      if (form) {
        form.addEventListener('submit', sync);
      }
    });
  }

  function b64urlToBuf(s) {
    s = String(s || '');
    var pad = '='.repeat((4 - (s.length % 4)) % 4);
    var b64 = (s + pad).replace(/-/g, '+').replace(/_/g, '/');
    var bin = atob(b64);
    var buf = new Uint8Array(bin.length);
    for (var i = 0; i < bin.length; i++) buf[i] = bin.charCodeAt(i);
    return buf.buffer;
  }

  function bufToB64url(buf) {
    var bytes = new Uint8Array(buf);
    var str = '';
    for (var i = 0; i < bytes.length; i++) str += String.fromCharCode(bytes[i]);
    return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  }

  function csrfToken() {
    var meta = qs('meta[name="csrf-token"]');
    if (meta && meta.content) return meta.content;
    var input = qs('input[name="_token"]');
    return input ? input.value : '';
  }

  function initPasskeyRegister() {
    var root = doc.getElementById('passkey-section');
    var btn = doc.getElementById('register-passkey-btn');
    var status = doc.getElementById('passkey-status');
    if (!root || !btn) return;

    btn.addEventListener('click', async function () {
      if (!window.PublicKeyCredential) {
        if (status) status.textContent = 'Passkeys are not supported in this browser.';
        return;
      }
      if (status) status.textContent = 'Waiting for authenticator…';
      try {
        var optRes = await fetch(root.dataset.optionsUrl, {
          credentials: 'same-origin',
          headers: { Accept: 'application/json' }
        });
        var optJson = await optRes.json();
        if (!optJson.ok) throw new Error(optJson.error || 'Could not start registration');
        var o = optJson.options;
        var publicKey = {
          challenge: b64urlToBuf(o.challenge),
          rp: o.rp,
          user: {
            id: b64urlToBuf(o.user.id),
            name: o.user.name,
            displayName: o.user.displayName
          },
          pubKeyCredParams: o.pubKeyCredParams,
          timeout: o.timeout,
          attestation: o.attestation,
          authenticatorSelection: o.authenticatorSelection,
          excludeCredentials: (o.excludeCredentials || []).map(function (c) {
            return { type: c.type, id: b64urlToBuf(c.id) };
          })
        };
        var cred = await navigator.credentials.create({ publicKey: publicKey });
        if (!cred) throw new Error('No credential returned');
        var att = cred.response;
        var publicKeyB64 = '';
        var algorithm = -7;
        if (typeof att.getPublicKey === 'function') {
          var pk = att.getPublicKey();
          if (pk) publicKeyB64 = bufToB64url(pk);
          if (typeof att.getPublicKeyAlgorithm === 'function') {
            algorithm = att.getPublicKeyAlgorithm();
          }
        }
        if (!publicKeyB64) {
          throw new Error('Browser did not expose getPublicKey(). Use a current Chrome, Edge, Safari, or Firefox.');
        }
        var labelInput = doc.getElementById('passkey_label');
        var label = (labelInput && labelInput.value) ? labelInput.value : 'Passkey';
        var body = {
          id: cred.id,
          rawId: bufToB64url(cred.rawId),
          clientDataJSON: bufToB64url(att.clientDataJSON),
          publicKey: publicKeyB64,
          algorithm: algorithm,
          label: label
        };
        var reg = await fetch(root.dataset.registerUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': root.dataset.csrf || csrfToken()
          },
          body: JSON.stringify(body)
        });
        var regJson = await reg.json();
        if (!regJson.ok) throw new Error(regJson.error || 'Registration failed');
        if (status) status.textContent = 'Passkey registered. Reloading…';
        window.location.reload();
      } catch (err) {
        if (status) {
          status.textContent = err && err.message ? err.message : 'Passkey registration failed.';
        }
      }
    });
  }

  function initPasskeyLogin() {
    var root = qs('[data-mfa-passkey]');
    var btn = doc.getElementById('mfa-passkey-btn');
    var err = doc.getElementById('mfa-passkey-error');
    if (!btn || !root) return;
    if (!window.PublicKeyCredential) {
      btn.hidden = true;
      return;
    }

    btn.addEventListener('click', async function () {
      if (err) err.hidden = true;
      try {
        var optRes = await fetch(root.dataset.optionsUrl, { credentials: 'same-origin' });
        var optJson = await optRes.json();
        if (!optJson.ok) throw new Error(optJson.error || 'Could not start passkey login');
        var o = optJson.options;
        o.challenge = b64urlToBuf(o.challenge);
        if (o.allowCredentials) {
          o.allowCredentials = o.allowCredentials.map(function (c) {
            c.id = b64urlToBuf(c.id);
            return c;
          });
        }
        var cred = await navigator.credentials.get({ publicKey: o });
        var body = {
          id: cred.id,
          rawId: bufToB64url(cred.rawId),
          type: cred.type,
          response: {
            clientDataJSON: bufToB64url(cred.response.clientDataJSON),
            authenticatorData: bufToB64url(cred.response.authenticatorData),
            signature: bufToB64url(cred.response.signature),
            userHandle: cred.response.userHandle ? bufToB64url(cred.response.userHandle) : null
          }
        };
        var verifyRes = await fetch(root.dataset.verifyUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken()
          },
          body: JSON.stringify(body)
        });
        var verifyJson = await verifyRes.json();
        if (!verifyJson.ok) throw new Error(verifyJson.error || 'Passkey failed');
        window.location.href = verifyJson.redirect || root.dataset.redirectUrl || '/';
      } catch (e) {
        if (err) {
          err.textContent = e.message || 'Passkey sign-in failed';
          err.hidden = false;
        }
      }
    });
  }

  function initErrorGoBack() {
    var btn = doc.getElementById('error-go-back');
    if (!btn) return;
    btn.addEventListener('click', function () {
      var dash = btn.dataset.dashboardUrl || '/';
      var safe = btn.dataset.safeBackUrl || '';
      if (safe && safe !== dash) {
        window.location.href = safe;
        return;
      }
      if (window.history.length > 1) {
        window.history.back();
        return;
      }
      window.location.href = dash;
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
    initWysiwyg();
    initPasskeyRegister();
    initPasskeyLogin();
    initErrorGoBack();
  });
})();
