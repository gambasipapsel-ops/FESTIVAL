/**
 * GAMBASI - formulir pendaftaran multi-step.
 * Validasi di sini hanya untuk kenyamanan pengguna; server (PHP + Apps Script) memvalidasi ulang.
 */
(function () {
  'use strict';

  const cfgEl = document.getElementById('reg-config');
  if (!cfgEl || !window.Gambasi) return;
  const CFG = JSON.parse(cfgEl.textContent);
  const G = window.Gambasi;
  const esc = G.escapeHtml;

  const DRAFT_KEY = 'gambasi_draft_v1';
  const DRAFT_TTL = 24 * 3600 * 1000;
  const TOTAL_STEPS = 6;
  const STEP_NAMES = ['Kategori & Data Club', 'Data Official', 'Data Pemain', 'Dokumen', 'Review', 'Kirim'];
  const ACCEPT = {
    akte: { exts: ['pdf', 'jpg', 'jpeg', 'png'], mimes: ['application/pdf', 'image/jpeg', 'image/png'], label: 'PDF, JPG, JPEG, PNG' },
    foto: { exts: ['jpg', 'jpeg', 'png'], mimes: ['image/jpeg', 'image/png'], label: 'JPG, JPEG, PNG' },
    logo: { exts: ['jpg', 'jpeg', 'png'], mimes: ['image/jpeg', 'image/png'], label: 'JPG, JPEG, PNG' }
  };
  const BLOCKED = ['php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar', 'js', 'mjs', 'exe', 'bat', 'cmd', 'sh', 'com', 'msi', 'vbs', 'ps1', 'jar', 'html', 'htm', 'svg', 'scr', 'dll'];
  const PHONE_RE = /^(\+?62|0)8\d{7,12}$/;
  const EMAIL_RE = /^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/;

  const $ = (s, r) => (r || document).querySelector(s);
  const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));

  const form = $('#reg-form');
  const playersEl = $('#players');
  const docsEl = $('#documents');
  const tpl = $('#tpl-player');

  const state = {
    step: 1,
    maxStep: 1,
    token: newToken(),
    files: {},      // slot -> File
    fileOk: {},     // slot -> true bila lolos cek magic bytes
    uploaded: {},   // slot -> fingerprint file yang sudah tersimpan di server
    previews: {},   // slot -> object URL
    submitting: false
  };

  // ================================================================ util
  function randomHex(bytes) {
    const a = new Uint8Array(bytes);
    crypto.getRandomValues(a);
    return Array.from(a, (b) => b.toString(16).padStart(2, '0')).join('');
  }
  function newToken() {
    if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
    const h = randomHex(16);
    return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
  }
  function newRef() {
    return randomHex(5).slice(0, 10);
  }
  function fingerprint(file) {
    return file ? `${file.name}|${file.size}|${file.lastModified}` : '';
  }
  function isValidDate(s) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(s)) return false;
    const [y, m, d] = s.split('-').map(Number);
    const dt = new Date(Date.UTC(y, m - 1, d));
    return dt.getUTCFullYear() === y && dt.getUTCMonth() === m - 1 && dt.getUTCDate() === d;
  }
  function ageOn(birth, ref) {
    const b = birth.split('-').map(Number);
    const r = ref.split('-').map(Number);
    let age = r[0] - b[0];
    if (r[1] < b[1] || (r[1] === b[1] && r[2] < b[2])) age--;
    return age;
  }
  function formatDateId(iso) {
    if (!isValidDate(iso)) return iso || '-';
    const bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    const [y, m, d] = iso.split('-').map(Number);
    return `${d} ${bulan[m - 1]} ${y}`;
  }
  function kategori() {
    const r = $('input[name="kategori"]:checked');
    return r ? r.value : '';
  }
  function catLabel(k) { return k === 'U10' ? 'U-10' : k === 'U12' ? 'U-12' : '-'; }

  // ================================================================ errors
  function errorEl(input) {
    const wrap = input.closest('div') || input.parentElement;
    return wrap ? wrap.querySelector('.field-error') : null;
  }
  function setError(input, message) {
    const el = errorEl(input);
    input.setAttribute('aria-invalid', 'true');
    input.classList.add('is-invalid');
    if (el) {
      if (!el.id) el.id = 'err-' + Math.random().toString(36).slice(2, 9);
      el.textContent = message;
      el.hidden = false;
      input.setAttribute('aria-describedby', el.id);
    }
  }
  function clearError(input) {
    input.removeAttribute('aria-invalid');
    input.classList.remove('is-invalid');
    const el = errorEl(input);
    if (el) { el.hidden = true; el.textContent = ''; }
  }
  function setBoxError(id, message) {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = message;
    el.hidden = !message;
  }
  function clearStepErrors(stepEl) {
    $$('.is-invalid', stepEl).forEach(clearError);
    $$('.field-error', stepEl).forEach((e) => { e.hidden = true; });
  }
  function focusFirstError(stepEl) {
    const first = $('.is-invalid, .field-error:not([hidden])', stepEl);
    if (!first) return;
    const card = first.closest('.player-card');
    if (card) expandPlayer(card, true);
    first.scrollIntoView({ behavior: 'smooth', block: 'center' });
    if (first.focus && first.matches('input,select,textarea')) first.focus({ preventScroll: true });
  }

  // ================================================================ stepper
  function goTo(step, quiet) {
    step = Math.max(1, Math.min(TOTAL_STEPS, step));
    state.step = step;
    state.maxStep = Math.max(state.maxStep, Math.min(step, 5));
    $$('.form-step').forEach((s) => { s.hidden = Number(s.dataset.step) !== step; });
    $$('#stepper .step-item').forEach((li) => {
      const n = Number(li.dataset.step);
      li.classList.toggle('is-active', n === step);
      li.classList.toggle('is-done', n < step);
      li.classList.toggle('is-reachable', n <= state.maxStep && n !== step && step !== 6);
      if (n === step) li.setAttribute('aria-current', 'step'); else li.removeAttribute('aria-current');
    });
    $('#step-progress').style.width = `${((step - 1) / (TOTAL_STEPS - 1)) * 100}%`;
    $('#step-announcer').textContent = `Langkah ${step} dari ${TOTAL_STEPS}: ${STEP_NAMES[step - 1]}`;

    $('#btn-prev').hidden = step === 1 || step === 6;
    $('#btn-next').hidden = step >= 5;
    $('#btn-submit').hidden = step !== 5;
    $('#form-actions').hidden = step === 6;

    if (step === 3 && !playersEl.children.length) addPlayer();
    if (step === 4) renderDocuments();
    if (step === 5) renderReview();
    if (quiet) return;

    const top = form.getBoundingClientRect().top + window.scrollY - 140;
    window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
    const heading = $(`.form-step[data-step="${step}"] h2`);
    if (heading) { heading.setAttribute('tabindex', '-1'); heading.focus({ preventScroll: true }); }
    saveDraftSoon();
  }

  $('#btn-next').addEventListener('click', () => {
    if (validateStep(state.step)) goTo(state.step + 1);
  });
  $('#btn-prev').addEventListener('click', () => goTo(state.step - 1));
  $('#stepper').addEventListener('click', (ev) => {
    const li = ev.target.closest('.step-item');
    if (!li || state.submitting || state.step === 6) return;
    const n = Number(li.dataset.step);
    if (n < state.step) goTo(n);
    else if (n <= state.maxStep) {
      for (let s = state.step; s < n; s++) {
        if (!validateStep(s)) { if (s !== state.step) goTo(s); return; }
      }
      goTo(n);
    }
  });

  // ================================================================ validation
  function requireText(input, label, min) {
    const v = input.value.trim();
    if (!v) { setError(input, `${label} wajib diisi.`); return false; }
    if (min && v.length < min) { setError(input, `${label} minimal ${min} karakter.`); return false; }
    return true;
  }

  function validateStep(step) {
    const stepEl = $(`.form-step[data-step="${step}"]`);
    clearStepErrors(stepEl);
    let ok = true;

    if (step === 1) {
      if (!kategori()) { setBoxError('err-kategori', 'Pilih kategori U-10 atau U-12.'); ok = false; }
      [['nama_club', 'Nama club', 3], ['alamat', 'Alamat', 5], ['distrik', 'Distrik'], ['kabupaten', 'Kabupaten'], ['provinsi', 'Provinsi']]
        .forEach(([n, l, min]) => { if (!requireText($('#club-' + n), l, min)) ok = false; });
    }

    if (step === 2) {
      if (!requireText($('#official-nama_manager'), 'Nama manager', 3)) ok = false;
      if (!requireText($('#official-nama_pelatih'), 'Nama pelatih', 3)) ok = false;
      const wa = $('#official-whatsapp');
      if (!PHONE_RE.test(wa.value.replace(/[\s\-().]/g, ''))) { setError(wa, 'Nomor WhatsApp tidak valid (contoh: 081234567890).'); ok = false; }
      const email = $('#official-email');
      if (email.value.trim() && !EMAIL_RE.test(email.value.trim())) { setError(email, 'Format email tidak valid.'); ok = false; }
    }

    if (step === 3) ok = validatePlayers();

    if (step === 4) {
      playerCards().forEach((card, i) => {
        const ref = card.dataset.ref;
        const name = card.querySelector('[data-name="nama_lengkap"]').value.trim() || `Pemain #${i + 1}`;
        ['akte', 'foto'].forEach((kind) => {
          const slot = `${kind}:${ref}`;
          if (!state.files[slot] || !state.fileOk[slot]) {
            setBoxError(`err-doc-${kind}-${ref}`, `${kind === 'akte' ? 'Akte kelahiran' : 'Foto full body'} ${name} wajib diunggah.`);
            ok = false;
          }
        });
      });
    }

    if (step === 5) {
      if (!$('#agree').checked) { setBoxError('err-agree', 'Centang pernyataan untuk melanjutkan.'); ok = false; }
    }

    if (!ok) {
      focusFirstError(stepEl);
      G.toast('Periksa kembali isian yang ditandai.', 'error');
    }
    return ok;
  }

  function validatePlayers() {
    let ok = true;
    const cards = playerCards();
    const limits = CFG.players || { min: 1, max: 40 };
    if (cards.length < limits.min) { setBoxError('err-players', `Minimal ${limits.min} pemain.`); ok = false; }
    if (cards.length > limits.max) { setBoxError('err-players', `Maksimal ${limits.max} pemain.`); ok = false; }
    const rule = (CFG.ageRules || {})[kategori()] || null;
    const seenNik = {};
    const seenNo = {};

    cards.forEach((card) => {
      const f = (n) => card.querySelector(`[data-name="${n}"]`);
      if (!requireText(f('nama_lengkap'), 'Nama lengkap', 3)) ok = false;

      const nik = f('nik');
      const nikVal = nik.value.replace(/\s/g, '');
      if (!/^\d{16}$/.test(nikVal) || /^0{16}$/.test(nikVal)) { setError(nik, 'NIK harus 16 digit angka.'); ok = false; }
      else if (seenNik[nikVal]) { setError(nik, 'NIK sama dengan pemain lain.'); ok = false; }
      seenNik[nikVal] = true;

      if (!requireText(f('tempat_lahir'), 'Tempat lahir')) ok = false;

      const tgl = f('tanggal_lahir');
      if (!isValidDate(tgl.value)) { setError(tgl, 'Tanggal lahir wajib diisi dengan benar.'); ok = false; }
      else if (tgl.value > CFG.today) { setError(tgl, 'Tanggal lahir tidak boleh di masa depan.'); ok = false; }
      else if (rule) {
        const age = ageOn(tgl.value, rule.reference_date);
        if (age < rule.min || age > rule.max) {
          setError(tgl, `Usia ${age} tahun tidak sesuai kategori ${catLabel(kategori())} (${rule.min}–${rule.max} tahun per ${formatDateId(rule.reference_date)}).`);
          ok = false;
        }
      }

      if (!f('jenis_kelamin').value) { setError(f('jenis_kelamin'), 'Pilih jenis kelamin.'); ok = false; }
      if (!f('posisi').value) { setError(f('posisi'), 'Pilih posisi.'); ok = false; }

      const no = f('nomor_punggung');
      const noVal = String(parseInt(no.value, 10));
      if (!/^\d{1,2}$/.test(no.value.trim()) || Number(no.value) < 1) { setError(no, 'Nomor punggung 1–99.'); ok = false; }
      else if (seenNo[noVal]) { setError(no, `Nomor ${noVal} sudah dipakai pemain lain.`); ok = false; }
      seenNo[noVal] = true;

      if (!f('nama_ayah').value.trim() && !f('nama_ibu').value.trim() && !f('nama_wali').value.trim()) {
        setError(f('nama_ayah'), 'Isi minimal salah satu: nama ayah, ibu, atau wali.');
        ok = false;
      }
      const waw = f('whatsapp_wali');
      if (!PHONE_RE.test(waw.value.replace(/[\s\-().]/g, ''))) { setError(waw, 'Nomor WhatsApp tidak valid.'); ok = false; }
      if (!requireText(f('alamat'), 'Alamat', 5)) ok = false;
    });
    return ok;
  }

  // ================================================================ players
  function playerCards() { return $$('.player-card', playersEl); }

  function addPlayer(data, silent) {
    const limits = CFG.players || { max: 40 };
    if (playerCards().length >= limits.max) {
      G.toast(`Maksimal ${limits.max} pemain.`, 'warning');
      return null;
    }
    const node = tpl.content.firstElementChild.cloneNode(true);
    const ref = (data && /^[a-z0-9]{6,16}$/.test(data.ref || '')) ? data.ref : newRef();
    node.dataset.ref = ref;

    const gender = node.querySelector('[data-name="jenis_kelamin"]');
    Object.entries(CFG.genders).forEach(([v, l]) => gender.add(new Option(l, v)));
    const pos = node.querySelector('[data-name="posisi"]');
    Object.entries(CFG.positions).forEach(([v, l]) => pos.add(new Option(l, v)));
    node.querySelector('[data-name="tanggal_lahir"]').max = CFG.today;

    $$('[data-name]', node).forEach((input) => {
      const id = `p-${ref}-${input.dataset.name}`;
      input.id = id;
      const label = node.querySelector(`label[data-for="${input.dataset.name}"]`);
      if (label) label.htmlFor = id;
      if (data && data[input.dataset.name] != null) input.value = data[input.dataset.name];
    });
    const body = node.querySelector('.player-body');
    body.id = `p-${ref}-body`;
    node.querySelector('.player-toggle').setAttribute('aria-controls', body.id);

    playersEl.appendChild(node);
    renumberPlayers();
    updatePlayerHeader(node);
    if (!silent) {
      node.scrollIntoView({ behavior: 'smooth', block: 'start' });
      node.querySelector('[data-name="nama_lengkap"]').focus({ preventScroll: true });
      saveDraftSoon();
    }
    return node;
  }

  function renumberPlayers() {
    const cards = playerCards();
    cards.forEach((c, i) => {
      c.querySelector('.player-no').textContent = String(i + 1);
      c.querySelector('.player-remove').setAttribute('aria-label', `Hapus pemain #${i + 1}`);
    });
    $('#player-count').textContent = String(cards.length);
    const l = CFG.players || {};
    $('#player-limit').textContent = l.configured ? `batas ${l.min}–${l.max} pemain` : `maksimal ${l.max} pemain (sesuai ketentuan panitia)`;
    $('#add-player').disabled = cards.length >= (l.max || 40);
  }

  function updatePlayerHeader(card) {
    const name = card.querySelector('[data-name="nama_lengkap"]').value.trim();
    const no = card.querySelector('[data-name="nomor_punggung"]').value.trim();
    const pos = card.querySelector('[data-name="posisi"]');
    card.querySelector('.player-title').textContent = name || 'Pemain baru';
    const parts = [];
    if (no) parts.push(`#${no}`);
    if (pos.value) parts.push(pos.options[pos.selectedIndex].text);
    const tgl = card.querySelector('[data-name="tanggal_lahir"]').value;
    const rule = (CFG.ageRules || {})[kategori()];
    const hint = card.querySelector('.age-hint');
    if (isValidDate(tgl)) {
      parts.push(formatDateId(tgl));
      hint.textContent = rule ? `Usia ${ageOn(tgl, rule.reference_date)} tahun per ${formatDateId(rule.reference_date)}.` : '';
    } else {
      hint.textContent = '';
    }
    card.querySelector('.player-sub').textContent = parts.join(' · ') || 'Lengkapi data pemain';
  }

  function expandPlayer(card, open) {
    const body = card.querySelector('.player-body');
    body.hidden = !open;
    card.querySelector('.player-toggle').setAttribute('aria-expanded', String(open));
  }

  playersEl.addEventListener('input', (ev) => {
    const card = ev.target.closest('.player-card');
    if (!card) return;
    if (ev.target.dataset.name === 'nik') ev.target.value = ev.target.value.replace(/\D/g, '').slice(0, 16);
    if (ev.target.classList.contains('is-invalid')) clearError(ev.target);
    updatePlayerHeader(card);
    saveDraftSoon();
  });
  playersEl.addEventListener('change', (ev) => {
    const card = ev.target.closest('.player-card');
    if (card) updatePlayerHeader(card);
  });
  playersEl.addEventListener('click', (ev) => {
    const card = ev.target.closest('.player-card');
    if (!card) return;
    if (ev.target.closest('.player-toggle')) {
      expandPlayer(card, card.querySelector('.player-body').hidden);
    } else if (ev.target.closest('.player-remove')) {
      const name = card.querySelector('[data-name="nama_lengkap"]').value.trim() || 'pemain ini';
      if (!window.confirm(`Hapus ${name}? Dokumen pemain ini juga akan dihapus dari formulir.`)) return;
      const ref = card.dataset.ref;
      ['akte', 'foto'].forEach((k) => removeFile(`${k}:${ref}`));
      card.remove();
      renumberPlayers();
      saveDraftSoon();
      G.toast('Pemain dihapus.', 'info', 2500);
    }
  });
  $('#add-player').addEventListener('click', () => {
    playerCards().forEach((c) => expandPlayer(c, false));
    addPlayer();
  });

  // ================================================================ files
  function extOf(name) {
    const parts = String(name).toLowerCase().split('.');
    return parts.length > 1 ? parts[parts.length - 1] : '';
  }

  async function sniffMime(file) {
    const buf = new Uint8Array(await file.slice(0, 8).arrayBuffer());
    if (buf[0] === 0x25 && buf[1] === 0x50 && buf[2] === 0x44 && buf[3] === 0x46) return 'application/pdf';
    if (buf[0] === 0xff && buf[1] === 0xd8 && buf[2] === 0xff) return 'image/jpeg';
    if (buf[0] === 0x89 && buf[1] === 0x50 && buf[2] === 0x4e && buf[3] === 0x47) return 'image/png';
    return '';
  }

  async function checkFile(file, kind) {
    const rule = ACCEPT[kind];
    const parts = String(file.name).toLowerCase().split('.');
    if (parts.length < 2) return 'Nama file harus memiliki ekstensi.';
    if (parts.slice(1).some((p) => BLOCKED.includes(p))) return 'Tipe file tidak diizinkan.';
    if (!rule.exts.includes(extOf(file.name))) return `Format tidak diizinkan. Gunakan ${rule.label}.`;
    if (file.type && !rule.mimes.includes(file.type)) return `Tipe file tidak diizinkan. Gunakan ${rule.label}.`;
    if (file.size <= 0) return 'File kosong.';
    if (file.size > CFG.maxBytes) return `Ukuran file ${G.formatBytes(file.size)} melebihi batas 5 MB.`;
    const mime = await sniffMime(file);
    if (!mime || !rule.mimes.includes(mime)) return 'Isi file tidak sesuai formatnya atau file rusak.';
    const ext = extOf(file.name);
    const match = (mime === 'application/pdf' && ext === 'pdf') || (mime === 'image/jpeg' && (ext === 'jpg' || ext === 'jpeg')) || (mime === 'image/png' && ext === 'png');
    if (!match) return 'Ekstensi file tidak sesuai dengan isinya.';
    return '';
  }

  function removeFile(slot) {
    if (state.previews[slot]) URL.revokeObjectURL(state.previews[slot]);
    delete state.previews[slot];
    delete state.files[slot];
    delete state.fileOk[slot];
    delete state.uploaded[slot];
  }

  async function setFile(slot, kind, file, box) {
    const err = await checkFile(file, kind);
    if (err) {
      renderUploadBox(box);
      setBoxError(box.dataset.errorId, err);
      G.toast(err, 'error');
      return;
    }
    removeFile(slot);
    state.files[slot] = file;
    state.fileOk[slot] = true;
    if (file.type.startsWith('image/') || /\.(jpe?g|png)$/i.test(file.name)) {
      state.previews[slot] = URL.createObjectURL(file);
    }
    renderUploadBox(box);
    updateDocProgress();
  }

  function renderUploadBox(box) {
    const slot = box.dataset.slot;
    const kind = box.dataset.kind;
    const safeId = slot.replace(':', '-');
    const inputId = `file-${safeId}`;
    const errorId = `err-doc-${safeId}`;
    box.dataset.errorId = errorId;
    const file = state.files[slot];
    const rule = ACCEPT[kind];
    const accept = rule.exts.map((e) => '.' + e).concat(rule.mimes).join(',');
    const title = kind === 'akte' ? 'Akte Kelahiran' : kind === 'foto' ? 'Foto Full Body' : 'Logo Club';
    const required = kind !== 'logo';

    let inner;
    if (!file) {
      inner = `
        <label for="${inputId}" class="dropzone">
          <svg class="h-8 w-8 text-royal-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5M4 16v3a1 1 0 001 1h14a1 1 0 001-1v-3"/></svg>
          <span class="mt-2 font-semibold text-slate-900">${esc(title)} ${required ? '<span class="req-pill">Wajib</span>' : ''}</span>
          <span class="text-xs text-slate-500">Ketuk untuk memilih · ${esc(rule.label)} · maks 5 MB</span>
        </label>`;
    } else {
      const preview = state.previews[slot]
        ? `<img src="${esc(state.previews[slot])}" alt="Pratinjau ${esc(title)}" class="h-24 w-20 shrink-0 rounded-lg bg-slate-100 object-cover ring-1 ring-slate-200 ${kind === 'foto' ? 'object-top' : ''}">`
        : `<span class="flex h-24 w-20 shrink-0 items-center justify-center rounded-lg bg-rose-50 font-display text-lg font-bold text-rose-700 ring-1 ring-rose-200">PDF</span>`;
      const uploaded = state.uploaded[slot] === fingerprint(file);
      inner = `
        <div class="flex items-center gap-4 rounded-xl bg-white p-3 ring-1 ring-royal-300">
          ${preview}
          <div class="min-w-0 flex-1">
            <p class="text-xs font-bold uppercase tracking-wide text-royal-700">${esc(title)} ${uploaded ? '· terunggah' : '· siap diunggah'}</p>
            <p class="truncate font-semibold text-slate-900" title="${esc(file.name)}">${esc(file.name)}</p>
            <p class="text-xs text-slate-500">${esc(G.formatBytes(file.size))}</p>
            <div class="mt-2 flex gap-2">
              <label for="${inputId}" class="btn-chip cursor-pointer">Ganti</label>
              <button type="button" class="btn-chip btn-chip-danger" data-remove="${esc(slot)}">Hapus</button>
            </div>
          </div>
        </div>`;
    }
    box.innerHTML = `${inner}
      <input type="file" id="${inputId}" class="sr-only" accept="${esc(accept)}" ${required ? 'aria-required="true"' : ''} aria-describedby="${errorId}">
      <p class="field-error" id="${errorId}" hidden></p>`;

    const input = box.querySelector('input[type=file]');
    input.addEventListener('change', () => {
      if (input.files && input.files[0]) setFile(slot, kind, input.files[0], box);
    });
    const rm = box.querySelector('[data-remove]');
    if (rm) rm.addEventListener('click', () => { removeFile(slot); renderUploadBox(box); updateDocProgress(); });
  }

  function wireDropzone(box) {
    ['dragenter', 'dragover'].forEach((t) => box.addEventListener(t, (ev) => { ev.preventDefault(); box.classList.add('is-drag'); }));
    ['dragleave', 'drop'].forEach((t) => box.addEventListener(t, () => box.classList.remove('is-drag')));
    box.addEventListener('drop', (ev) => {
      ev.preventDefault();
      const f = ev.dataTransfer && ev.dataTransfer.files && ev.dataTransfer.files[0];
      if (f) setFile(box.dataset.slot, box.dataset.kind, f, box);
    });
  }

  function renderDocuments() {
    const cards = playerCards();
    docsEl.innerHTML = '';
    cards.forEach((card, i) => {
      const ref = card.dataset.ref;
      const name = card.querySelector('[data-name="nama_lengkap"]').value.trim() || `Pemain #${i + 1}`;
      const no = card.querySelector('[data-name="nomor_punggung"]').value.trim();
      const wrap = document.createElement('div');
      wrap.className = 'rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200';
      wrap.innerHTML = `
        <p class="flex items-center gap-2 font-semibold text-slate-900">
          <span class="flex h-8 w-8 items-center justify-center rounded-full bg-royal-700 font-display text-white">${i + 1}</span>
          ${esc(name)} ${no ? `<span class="text-sm font-normal text-slate-500">#${esc(no)}</span>` : ''}
          <span class="doc-status ml-auto text-xs font-semibold" data-ref="${esc(ref)}"></span>
        </p>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
          <div class="upload-box" data-slot="akte:${esc(ref)}" data-kind="akte"></div>
          <div class="upload-box" data-slot="foto:${esc(ref)}" data-kind="foto"></div>
        </div>`;
      docsEl.appendChild(wrap);
    });
    $$('.upload-box', docsEl).forEach((b) => { renderUploadBox(b); wireDropzone(b); });
    updateDocProgress();
  }

  function updateDocProgress() {
    $$('.doc-status', docsEl).forEach((el) => {
      const ref = el.dataset.ref;
      const n = ['akte', 'foto'].filter((k) => state.fileOk[`${k}:${ref}`]).length;
      el.textContent = n === 2 ? '✓ Lengkap' : `${n}/2 dokumen`;
      el.className = `doc-status ml-auto text-xs font-semibold ${n === 2 ? 'text-royal-700' : 'text-amber-700'}`;
    });
  }

  // ================================================================ review
  function val(sel) { const el = $(sel); return el ? el.value.trim() : ''; }

  function renderReview() {
    const k = kategori();
    const row = (l, v) => `<div class="grid grid-cols-[minmax(6.5rem,35%)_minmax(0,1fr)] gap-3 py-2"><dt class="break-words text-sm text-slate-500">${esc(l)}</dt><dd class="break-words text-sm font-semibold text-slate-900">${esc(v || '-')}</dd></div>`;
    const section = (title, step, content) => `
      <section class="rounded-2xl ring-1 ring-slate-200">
        <header class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
          <h3 class="font-display text-lg font-bold uppercase text-slate-900">${esc(title)}</h3>
          <button type="button" class="text-sm font-semibold text-royal-700 hover:underline" data-goto="${step}">Ubah</button>
        </header>
        <div class="px-4 py-2">${content}</div>
      </section>`;

    const club = `<dl class="divide-y divide-slate-100">
      ${row('Kategori', catLabel(k))}${row('Nama Club', val('#club-nama_club'))}${row('Alamat', val('#club-alamat'))}
      ${row('Kampung/Kelurahan', val('#club-kampung'))}${row('Distrik', val('#club-distrik'))}
      ${row('Kabupaten', val('#club-kabupaten'))}${row('Provinsi', val('#club-provinsi'))}
      ${row('Logo', state.files.logo ? state.files.logo.name : 'Tidak ada')}</dl>`;
    const off = `<dl class="divide-y divide-slate-100">
      ${row('Manager', val('#official-nama_manager'))}${row('Pelatih', val('#official-nama_pelatih'))}
      ${row('WhatsApp', val('#official-whatsapp'))}${row('Email', val('#official-email'))}</dl>`;

    const rows = playerCards().map((c, i) => {
      const f = (n) => c.querySelector(`[data-name="${n}"]`);
      const pos = f('posisi');
      const ref = c.dataset.ref;
      const docs = ['akte', 'foto'].map((kd) => state.fileOk[`${kd}:${ref}`] ? '✓' : '✗').join(' / ');
      const parent = [f('nama_ayah').value, f('nama_ibu').value, f('nama_wali').value].filter((x) => x.trim()).join(', ');
      return `<tr class="align-top">
        <td class="py-2 pr-3 font-display text-lg font-bold">${i + 1}</td>
        <td class="py-2 pr-3"><p class="font-semibold text-slate-900">${esc(f('nama_lengkap').value)}</p>
          <p class="text-xs text-slate-500">NIK ${esc(f('nik').value)} · ${esc(f('tempat_lahir').value)}, ${esc(formatDateId(f('tanggal_lahir').value))}</p>
          <p class="text-xs text-slate-500">Ortu/Wali: ${esc(parent)} · WA ${esc(f('whatsapp_wali').value)}</p></td>
        <td class="py-2 pr-3 text-sm">#${esc(f('nomor_punggung').value)}<br><span class="text-slate-500">${esc(pos.value ? pos.options[pos.selectedIndex].text : '-')} · ${esc(f('jenis_kelamin').value)}</span></td>
        <td class="whitespace-nowrap py-2 text-sm" title="Akte / Foto">${docs}</td></tr>`;
    }).join('');
    const players = `<div class="overflow-x-auto"><table class="w-full text-left">
      <thead><tr class="text-xs uppercase tracking-wide text-slate-500"><th class="py-2 pr-3">#</th><th class="py-2 pr-3">Pemain</th><th class="py-2 pr-3">No/Posisi</th><th class="py-2">Akte/Foto</th></tr></thead>
      <tbody class="divide-y divide-slate-100">${rows}</tbody></table></div>`;

    $('#review').innerHTML = section('Data Club', 1, club) + section('Official', 2, off) +
      section(`Pemain (${playerCards().length})`, 3, players);
  }
  $('#review').addEventListener('click', (ev) => {
    const b = ev.target.closest('[data-goto]');
    if (b) goTo(Number(b.dataset.goto));
  });
  $('#agree').addEventListener('change', () => setBoxError('err-agree', ''));

  // ================================================================ payload
  function collectPayload() {
    const group = (g) => {
      const o = {};
      $$(`[data-group="${g}"]`).forEach((el) => { o[el.dataset.name] = el.value.trim(); });
      return o;
    };
    const club = group('club');
    club.has_logo = !!(state.files.logo && state.fileOk.logo);
    return {
      kategori: kategori(),
      club,
      official: group('official'),
      players: playerCards().map((c) => {
        const p = { ref: c.dataset.ref };
        $$('[data-name]', c).forEach((el) => { p[el.dataset.name] = el.value.trim(); });
        return p;
      })
    };
  }

  // ================================================================ draft
  const saveState = $('#save-state');
  function saveDraft() {
    if (state.submitting) return;
    try {
      const p = collectPayload();
      p.players.forEach((pl) => { delete pl.nik; });
      delete p.club.has_logo;
      localStorage.setItem(DRAFT_KEY, JSON.stringify({ v: 1, savedAt: Date.now(), token: state.token, step: Math.min(state.step, 5), maxStep: state.maxStep, data: p }));
      if (saveState) saveState.textContent = `Draft tersimpan ${new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}`;
    } catch (e) { /* storage penuh / diblokir */ }
  }
  const saveDraftSoon = G.debounce(saveDraft, 700);
  function clearDraft() { try { localStorage.removeItem(DRAFT_KEY); } catch (e) { /* noop */ } }

  function loadDraft() {
    let d;
    try { d = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null'); } catch (e) { d = null; }
    if (!d || d.v !== 1 || Date.now() - d.savedAt > DRAFT_TTL) { clearDraft(); return false; }
    const p = d.data || {};
    if (typeof d.token === 'string' && /^[A-Za-z0-9-]{32,64}$/.test(d.token)) state.token = d.token;
    if (p.kategori) { const r = $(`input[name="kategori"][value="${p.kategori}"]`); if (r) r.checked = true; }
    ['club', 'official'].forEach((g) => {
      Object.entries(p[g] || {}).forEach(([k, v]) => {
        const el = $(`[data-group="${g}"][data-name="${k}"]`);
        if (el && typeof v === 'string') el.value = v;
      });
    });
    (p.players || []).forEach((pl) => addPlayer(pl, true));
    playerCards().forEach((c) => expandPlayer(c, false));
    state.maxStep = Math.min(Number(d.maxStep) || 1, 3);
    const banner = $('#draft-banner');
    $('#draft-text').textContent = `Draft dari ${new Date(d.savedAt).toLocaleString('id-ID')} dimuat. Isi ulang NIK dan pilih ulang dokumen sebelum mengirim.`;
    banner.classList.remove('hidden');
    return true;
  }

  $('#draft-clear').addEventListener('click', () => {
    if (!window.confirm('Hapus draft dan kosongkan formulir?')) return;
    clearDraft();
    window.location.reload();
  });
  form.addEventListener('input', (ev) => {
    if (ev.target.dataset.group) {
      if (ev.target.classList.contains('is-invalid')) clearError(ev.target);
      saveDraftSoon();
    }
  });
  form.addEventListener('change', (ev) => {
    if (ev.target.name === 'kategori') {
      setBoxError('err-kategori', '');
      playerCards().forEach(updatePlayerHeader);
      saveDraftSoon();
    }
  });
  form.addEventListener('submit', (ev) => ev.preventDefault());

  // ================================================================ submit
  function setPhase(name, status) {
    const li = $(`#submit-steps [data-phase="${name}"]`);
    if (li) li.dataset.status = status;
  }
  function setProgress(pct) {
    $('#submit-progress').style.width = `${pct}%`;
    $('#submit-progressbar').setAttribute('aria-valuenow', String(Math.round(pct)));
  }

  function stepForField(field) {
    if (!field) return 5;
    if (field === 'kategori' || field.startsWith('club.')) return 1;
    if (field.startsWith('official.')) return 2;
    if (field.startsWith('players')) return 3;
    if (/^(akte|foto):/.test(field)) return 4;
    if (field === 'logo') return 1;
    return 5;
  }

  function applyServerErrors(errors) {
    const cards = playerCards();
    errors.forEach((er) => {
      const field = String(er.field || '');
      let m;
      if ((m = /^(club|official)\.(\w+)$/.exec(field))) {
        const el = document.getElementById(`${m[1]}-${m[2]}`);
        if (el) setError(el, er.message);
      } else if ((m = /^players\[(\d+)\]\.(\w+)$/.exec(field))) {
        const card = cards[Number(m[1])];
        const el = card && card.querySelector(`[data-name="${m[2]}"]`);
        if (el) { setError(el, er.message); expandPlayer(card, true); }
      } else if ((m = /^(akte|foto):([a-z0-9]+)$/.exec(field))) {
        delete state.uploaded[field];
      } else if (field === 'players') {
        setBoxError('err-players', er.message);
      } else if (field === 'kategori') {
        setBoxError('err-kategori', er.message);
      }
    });
  }

  let lastErrorStep = 5;
  function showSubmitError(res) {
    const box = $('#submit-error');
    $('#submit-error-text').textContent = res.message || 'Pendaftaran gagal dikirim.';
    const list = $('#submit-error-list');
    list.innerHTML = '';
    const errors = Array.isArray(res.errors) ? res.errors : [];
    errors.slice(0, 15).forEach((er) => {
      const li = document.createElement('li');
      li.textContent = er.message;
      list.appendChild(li);
    });
    if (errors.length > 15) {
      const li = document.createElement('li');
      li.textContent = `…dan ${errors.length - 15} kesalahan lainnya.`;
      list.appendChild(li);
    }
    applyServerErrors(errors);
    const steps = errors.map((er) => stepForField(er.field));
    lastErrorStep = steps.length ? Math.min(...steps) : (['INVALID_CATEGORY', 'DUPLICATE_CLUB'].includes(res.error_code) ? 1 : 5);
    const retryable = !errors.length || res.error_code === 'MISSING_FILES';
    $('#submit-retry').hidden = !retryable || ['REGISTRATION_CLOSED', 'CONFIG_ERROR', 'DUPLICATE_CLUB'].includes(res.error_code);
    $('#submit-back').textContent = res.error_code === 'SUBMISSION_EXPIRED' ? 'Mulai Ulang' : 'Perbaiki Data';
    box.hidden = false;
    box.dataset.code = res.error_code || '';
    box.focus && box.setAttribute('tabindex', '-1');
    box.focus();
    G.toast(res.message || 'Pendaftaran gagal dikirim.', 'error', 7000);
  }

  async function uploadSlot(slot) {
    const file = state.files[slot];
    for (let attempt = 1; attempt <= 3; attempt++) {
      const fd = new FormData();
      fd.append('submission_token', state.token);
      fd.append('slot', slot);
      fd.append('file', file, file.name);
      const res = await G.postForm(CFG.endpoints.upload, fd, 180000);
      if (res.success) return res;
      const retryable = res.network || res.status >= 500 || res.status === 429;
      if (!retryable || attempt === 3) return res;
      await new Promise((r) => setTimeout(r, 1500 * attempt));
    }
    return { success: false, message: 'Upload gagal.' };
  }

  function slotName(slot) {
    if (slot === 'logo') return 'Logo club';
    const [kind, ref] = slot.split(':');
    const cards = playerCards();
    const idx = cards.findIndex((c) => c.dataset.ref === ref);
    const name = idx >= 0 ? (cards[idx].querySelector('[data-name="nama_lengkap"]').value.trim() || `Pemain #${idx + 1}`) : 'Pemain';
    return `${kind === 'akte' ? 'Akte' : 'Foto'} ${name}`;
  }

  async function submit() {
    if (state.submitting) return;
    if (!CFG.canSubmit) {
      G.toast('Pengiriman belum dibuka oleh panitia. Data Anda tersimpan sebagai draft.', 'warning', 7000);
      return;
    }
    for (let s = 1; s <= 5; s++) {
      if (!validateStep(s)) { if (s !== state.step) { goTo(s); setTimeout(() => validateStep(s), 350); } return; }
    }
    state.submitting = true;
    saveDraft();
    goTo(6);
    $('#submit-error').hidden = true;
    ['init', 'upload', 'finalize'].forEach((p) => setPhase(p, 'pending'));
    setProgress(3);
    $('#upload-counter').textContent = '';

    try {
      setPhase('init', 'active');
      const payload = collectPayload();
      const init = await G.postJson(CFG.endpoints.init, { submission_token: state.token, payload }, 90000);
      if (!init.success) { setPhase('init', 'error'); return showSubmitError(init); }
      if (init.data.already_submitted) return finish();
      setPhase('init', 'done');
      setProgress(10);

      const serverHas = new Set(init.data.uploaded_slots || []);
      Object.keys(state.uploaded).forEach((s) => { if (!serverHas.has(s)) delete state.uploaded[s]; });
      const slots = (init.data.required_slots || []).slice();
      if (state.files.logo && state.fileOk.logo) slots.push('logo');
      const pending = slots.filter((s) => !(state.files[s] && state.uploaded[s] === fingerprint(state.files[s])));
      const missingLocal = slots.filter((s) => s !== 'logo' && !state.files[s]);
      if (missingLocal.length) {
        setPhase('upload', 'error');
        return showSubmitError({ message: 'Dokumen wajib belum lengkap.', error_code: 'MISSING_FILES', errors: missingLocal.map((s) => ({ field: s, message: `${slotName(s)} belum dipilih.` })) });
      }

      setPhase('upload', 'active');
      const total = pending.length;
      for (let i = 0; i < total; i++) {
        const slot = pending[i];
        $('#upload-counter').textContent = `(${i + 1}/${total}: ${slotName(slot)})`;
        const res = await uploadSlot(slot);
        if (!res.success) {
          setPhase('upload', 'error');
          const errs = res.errors && res.errors.length ? res.errors : [{ field: slot, message: `${slotName(slot)}: ${res.message}` }];
          return showSubmitError(Object.assign({}, res, { errors: errs, message: res.message || 'Upload dokumen gagal.' }));
        }
        state.uploaded[slot] = fingerprint(state.files[slot]);
        setProgress(10 + (80 * (i + 1)) / Math.max(total, 1));
      }
      $('#upload-counter').textContent = total ? `(${total} file)` : '(sudah terunggah)';
      setPhase('upload', 'done');
      setProgress(92);

      setPhase('finalize', 'active');
      const fin = await G.postJson(CFG.endpoints.finalize, { submission_token: state.token }, 120000);
      if (!fin.success) {
        setPhase('finalize', 'error');
        if (fin.error_code === 'MISSING_FILES') (fin.errors || []).forEach((er) => delete state.uploaded[er.field]);
        return showSubmitError(fin);
      }
      setPhase('finalize', 'done');
      finish();
    } finally {
      state.submitting = false;
    }
  }

  function finish() {
    setProgress(100);
    clearDraft();
    Object.values(state.previews).forEach((u) => URL.revokeObjectURL(u));
    state.done = true;
    window.location.href = CFG.endpoints.success;
  }

  $('#btn-submit').addEventListener('click', submit);
  $('#submit-retry').addEventListener('click', submit);
  $('#submit-back').addEventListener('click', () => {
    if ($('#submit-error').dataset.code === 'SUBMISSION_EXPIRED') {
      state.token = newToken();
      state.uploaded = {};
      saveDraft();
      goTo(5);
      return;
    }
    goTo(lastErrorStep);
    setTimeout(() => focusFirstError($(`.form-step[data-step="${lastErrorStep}"]`)), 400);
  });

  // Tombol WhatsApp admin: sertakan konteks non-sensitif (kategori, nama club, kode error)
  function withWaContext(link, extra) {
    if (!link) return;
    const base = link.href;
    link.addEventListener('click', () => {
      try {
        const u = new URL(base);
        const lines = [u.searchParams.get('text') || ''];
        const k = kategori();
        const club = val('#club-nama_club');
        if (k) lines.push(`Kategori: ${catLabel(k)}`);
        if (club) lines.push(`Club: ${club}`);
        const more = extra ? extra() : '';
        if (more) lines.push(more);
        link.href = `${u.origin}${u.pathname}?text=${encodeURIComponent(lines.filter(Boolean).join('\n'))}`;
      } catch (e) { link.href = base; }
    });
  }
  withWaContext($('#ask-admin'));
  withWaContext($('#ask-admin-error'), () => {
    const box = $('#submit-error');
    const msg = $('#submit-error-text').textContent.trim();
    return `Kendala saat mengirim: ${msg}${box.dataset.code ? ` (${box.dataset.code})` : ''}`;
  });

  window.addEventListener('beforeunload', (ev) => {
    if (state.submitting && !state.done) { ev.preventDefault(); ev.returnValue = ''; }
  });

  // ================================================================ init
  const logoBox = $('#doc-logo');
  renderUploadBox(logoBox);
  wireDropzone(logoBox);

  const hadDraft = loadDraft();
  if (!hadDraft && CFG.kategori) {
    const r = $(`input[name="kategori"][value="${CFG.kategori}"]`);
    if (r) r.checked = true;
  }
  goTo(1, true);
  if (hadDraft) G.toast('Draft sebelumnya dimuat.', 'info', 3000);
})();
