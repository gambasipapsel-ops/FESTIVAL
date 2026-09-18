/**
 * GAMBASI admin: sidebar, filter debounce, update status/catatan, viewer dokumen.
 */
(function () {
  'use strict';
  const G = window.Gambasi;
  if (!G) return;
  const base = G.base;

  // ---------------------------------------------------------------- sidebar
  const sidebar = document.getElementById('admin-sidebar');
  const toggle = document.getElementById('admin-menu-toggle');
  const backdrop = document.getElementById('admin-backdrop');
  function setSidebar(open) {
    if (!sidebar) return;
    sidebar.classList.toggle('-translate-x-full', !open);
    backdrop.classList.toggle('hidden', !open);
    toggle.setAttribute('aria-expanded', String(open));
  }
  if (toggle) {
    toggle.addEventListener('click', () => setSidebar(sidebar.classList.contains('-translate-x-full')));
    backdrop.addEventListener('click', () => setSidebar(false));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setSidebar(false); });
  }

  // ---------------------------------------------------------------- filter (debounce, tanpa request per keypress)
  document.querySelectorAll('form.auto-filter').forEach((form) => {
    const submit = G.debounce(() => form.requestSubmit ? form.requestSubmit() : form.submit(), 600);
    form.querySelectorAll('[data-debounce]').forEach((input) => {
      let last = input.value;
      input.addEventListener('input', () => {
        const v = input.value.trim();
        if (v === last.trim()) return;
        if (v.length === 0 || v.length >= 2) { last = input.value; submit(); }
      });
    });
    form.querySelectorAll('select').forEach((s) => s.addEventListener('change', () => form.requestSubmit ? form.requestSubmit() : form.submit()));
  });

  // ---------------------------------------------------------------- status & catatan
  async function sendAction(ctrl, body, buttons) {
    buttons.forEach((b) => { b.disabled = true; });
    const res = await G.postJson(base + '/admin/action.php', Object.assign({ target: ctrl.dataset.target, id: ctrl.dataset.id }, body), 30000);
    buttons.forEach((b) => { b.disabled = false; });
    if (res.status === 401) {
      G.toast('Sesi admin berakhir. Mengalihkan ke halaman login…', 'error');
      setTimeout(() => { window.location.href = base + '/admin/login.php'; }, 1500);
      return null;
    }
    if (!res.success) {
      G.toast(res.message || 'Gagal menyimpan.', 'error', 6000);
      return null;
    }
    return res;
  }

  document.querySelectorAll('.status-control').forEach((ctrl) => {
    const note = ctrl.querySelector('.note-input');
    const statusBtns = Array.from(ctrl.querySelectorAll('.status-btn'));
    statusBtns.forEach((btn) => {
      btn.addEventListener('click', async () => {
        const status = btn.dataset.status;
        if (btn.getAttribute('aria-pressed') === 'true' && !(note && note.value.trim())) {
          G.toast(`Status sudah ${status}.`, 'info', 2500);
          return;
        }
        if ((status === 'REVISION' || status === 'REJECTED') && note && !note.value.trim()) {
          G.toast('Tulis catatan terlebih dahulu untuk status ' + status + '.', 'warning');
          note.focus();
          return;
        }
        if (status === 'REJECTED' && !window.confirm('Tolak pendaftaran ini?')) return;
        const res = await sendAction(ctrl, { action: 'update_status', status, note: note ? note.value : '' }, statusBtns);
        if (!res) return;
        statusBtns.forEach((b) => {
          const on = b.dataset.status === status;
          b.setAttribute('aria-pressed', String(on));
          b.className = 'status-btn rounded-lg px-2.5 py-1.5 text-xs font-bold ring-1 ring-inset transition ' +
            (on ? 'bg-slate-900 text-white ring-slate-900' : 'bg-white text-slate-700 ring-slate-300 hover:bg-slate-50');
        });
        if (note) note.value = '';
        G.toast(`Status diperbarui menjadi ${status}.`, 'success', 3000);
        if (ctrl.dataset.target === 'club') setTimeout(() => window.location.reload(), 900);
      });
    });

    const noteBtn = ctrl.querySelector('.note-btn');
    if (noteBtn) {
      noteBtn.addEventListener('click', async () => {
        if (!note.value.trim()) { G.toast('Catatan kosong.', 'warning'); note.focus(); return; }
        const res = await sendAction(ctrl, { action: 'add_note', note: note.value }, [noteBtn]);
        if (!res) return;
        note.value = '';
        G.toast('Catatan tersimpan.', 'success', 2500);
        setTimeout(() => window.location.reload(), 800);
      });
    }
  });

  // ---------------------------------------------------------------- viewer dokumen
  const dialog = document.getElementById('doc-viewer');
  if (dialog) {
    const body = document.getElementById('doc-viewer-body');
    const title = document.getElementById('doc-viewer-title');
    const dl = document.getElementById('doc-viewer-download');
    let objectUrl = null;
    let opener = null;

    function cleanup() {
      if (objectUrl) URL.revokeObjectURL(objectUrl);
      objectUrl = null;
      body.innerHTML = '';
    }
    document.getElementById('doc-viewer-close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => { cleanup(); if (opener) opener.focus(); });
    dialog.addEventListener('click', (e) => { if (e.target === dialog) dialog.close(); });

    document.addEventListener('click', async (e) => {
      const btn = e.target.closest('.doc-open');
      if (!btn) return;
      opener = btn;
      cleanup();
      title.textContent = btn.dataset.title || 'Dokumen';
      dl.href = btn.dataset.download || btn.dataset.src;
      body.innerHTML = '<p class="text-sm text-slate-500">Memuat dokumen…</p>';
      if (typeof dialog.showModal === 'function') dialog.showModal(); else dialog.setAttribute('open', '');
      try {
        const r = await fetch(btn.dataset.src, { credentials: 'same-origin' });
        if (!r.ok) throw new Error(await r.text());
        const blob = await r.blob();
        objectUrl = URL.createObjectURL(blob);
        if (blob.type === 'application/pdf') {
          body.innerHTML = '';
          const frame = document.createElement('iframe');
          frame.src = objectUrl;
          frame.title = title.textContent;
          frame.className = 'h-[75vh] w-full rounded-lg bg-white';
          body.appendChild(frame);
        } else if (blob.type.startsWith('image/')) {
          body.innerHTML = '';
          const img = document.createElement('img');
          img.src = objectUrl;
          img.alt = title.textContent;
          img.className = 'max-h-[75vh] w-auto rounded-lg object-contain';
          body.appendChild(img);
        } else {
          throw new Error('Tipe dokumen tidak didukung.');
        }
      } catch (err) {
        body.innerHTML = '';
        const p = document.createElement('p');
        p.className = 'text-sm font-semibold text-rose-700';
        p.textContent = 'Dokumen gagal dimuat: ' + (err.message || '').slice(0, 200);
        body.appendChild(p);
      }
    });
  }
})();
