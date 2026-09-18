/** Countdown menuju tanggal resmi festival (30 Oktober 2026). */
(function () {
  'use strict';
  const el = document.getElementById('countdown');
  if (!el) return;
  const target = new Date(el.dataset.target).getTime();
  const units = {
    hari: el.querySelector('[data-unit="hari"]'),
    jam: el.querySelector('[data-unit="jam"]'),
    menit: el.querySelector('[data-unit="menit"]'),
    detik: el.querySelector('[data-unit="detik"]')
  };
  const pad = (n) => String(n).padStart(2, '0');
  let timer = null;

  function tick() {
    let diff = Math.max(0, target - Date.now());
    const d = Math.floor(diff / 86400000); diff -= d * 86400000;
    const h = Math.floor(diff / 3600000); diff -= h * 3600000;
    const m = Math.floor(diff / 60000); diff -= m * 60000;
    const s = Math.floor(diff / 1000);
    units.hari.textContent = String(d);
    units.jam.textContent = pad(h);
    units.menit.textContent = pad(m);
    units.detik.textContent = pad(s);
    if (target - Date.now() <= 0 && timer) clearInterval(timer);
  }
  tick();
  timer = setInterval(tick, 1000);
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) { clearInterval(timer); } else { tick(); timer = setInterval(tick, 1000); }
  });
})();
