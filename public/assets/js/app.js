/**
 * GAMBASI - helper bersama (toast, fetch JSON + CSRF, util) & navigasi.
 */
(function () {
  'use strict';

  const base = (document.querySelector('meta[name="app-base"]') || {}).content || '';

  function getCsrf() {
    const m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.content : '';
  }
  function setCsrf(token) {
    const m = document.querySelector('meta[name="csrf-token"]');
    if (m && token) m.content = token;
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function formatBytes(n) {
    if (n < 1024) return n + ' B';
    if (n < 1024 * 1024) return (n / 1024).toFixed(0) + ' KB';
    return (n / 1024 / 1024).toFixed(2) + ' MB';
  }

  function debounce(fn, wait) {
    let t;
    return function (...args) {
      clearTimeout(t);
      t = setTimeout(() => fn.apply(this, args), wait);
    };
  }

  // ---------------------------------------------------------------- Toast
  function toast(message, type = 'info', timeout = 5000) {
    const root = document.getElementById('toast-root');
    if (!root) return;
    const colors = {
      success: 'bg-royal-800 text-white',
      error: 'bg-rose-700 text-white',
      warning: 'bg-amber-500 text-ink-950',
      info: 'bg-ink-900 text-white'
    };
    const el = document.createElement('div');
    el.className = 'toast pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-2xl px-4 py-3 text-sm font-medium shadow-2xl ' + (colors[type] || colors.info);
    el.setAttribute('role', type === 'error' ? 'alert' : 'status');
    const text = document.createElement('p');
    text.className = 'flex-1';
    text.textContent = message;
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'rounded p-0.5 opacity-80 hover:opacity-100';
    close.setAttribute('aria-label', 'Tutup notifikasi');
    close.innerHTML = '&times;';
    close.addEventListener('click', () => el.remove());
    el.append(text, close);
    root.appendChild(el);
    if (timeout) setTimeout(() => el.remove(), timeout);
  }

  // ---------------------------------------------------------------- Fetch
  async function refreshCsrf() {
    try {
      const r = await fetch(base + '/api/csrf.php', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const j = await r.json();
      if (j && j.success && j.data && j.data.csrf_token) {
        setCsrf(j.data.csrf_token);
        return true;
      }
    } catch (e) { /* noop */ }
    return false;
  }

  async function request(url, options, timeoutMs, retryCsrf) {
    const ctrl = new AbortController();
    const timer = setTimeout(() => ctrl.abort(), timeoutMs || 60000);
    let res;
    try {
      const headers = Object.assign({ Accept: 'application/json', 'X-CSRF-Token': getCsrf() }, options.headers || {});
      res = await fetch(url, Object.assign({}, options, { headers, credentials: 'same-origin', signal: ctrl.signal }));
    } catch (e) {
      clearTimeout(timer);
      const timedOut = e && e.name === 'AbortError';
      return {
        success: false,
        network: true,
        status: 0,
        error_code: timedOut ? 'CLIENT_TIMEOUT' : 'NETWORK_ERROR',
        message: timedOut
          ? 'Waktu tunggu habis. Periksa koneksi internet lalu coba lagi.'
          : 'Koneksi internet bermasalah. Periksa koneksi lalu coba lagi.'
      };
    }
    clearTimeout(timer);
    let json = null;
    try { json = await res.json(); } catch (e) { json = null; }
    if (!json || typeof json.success !== 'boolean') {
      json = { success: false, error_code: 'BAD_RESPONSE', message: 'Respons server tidak valid (HTTP ' + res.status + ').' };
    }
    json.status = res.status;
    if (res.status === 419 && retryCsrf !== false && await refreshCsrf()) {
      return request(url, options, timeoutMs, false);
    }
    return json;
  }

  function postJson(url, body, timeoutMs) {
    return request(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body || {})
    }, timeoutMs);
  }

  function postForm(url, formData, timeoutMs) {
    return request(url, { method: 'POST', body: formData }, timeoutMs);
  }

  window.Gambasi = { toast, getCsrf, setCsrf, refreshCsrf, postJson, postForm, debounce, escapeHtml, formatBytes, base };

  // ---------------------------------------------------------------- Navigasi publik
  const toggle = document.getElementById('nav-toggle');
  const mobileNav = document.getElementById('mobile-nav');
  if (toggle && mobileNav) {
    toggle.addEventListener('click', () => {
      const open = mobileNav.classList.toggle('hidden') === false;
      toggle.setAttribute('aria-expanded', String(open));
      toggle.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');
    });
    mobileNav.addEventListener('click', (ev) => {
      if (ev.target.closest('a')) {
        mobileNav.classList.add('hidden');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
    document.addEventListener('keydown', (ev) => {
      if (ev.key === 'Escape' && !mobileNav.classList.contains('hidden')) {
        mobileNav.classList.add('hidden');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.focus();
      }
    });
  }

  const header = document.getElementById('site-header');
  if (header && header.classList.contains('header-transparent')) {
    const onScroll = () => header.classList.toggle('header-scrolled', window.scrollY > 40);
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }
})();
