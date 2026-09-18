(function () {
  'use strict';
  const btn = document.getElementById('copy-number');
  const num = document.getElementById('reg-number');
  if (!btn || !num) return;
  btn.addEventListener('click', async () => {
    const text = num.textContent.trim();
    try {
      await navigator.clipboard.writeText(text);
      window.Gambasi.toast('Nomor pendaftaran disalin.', 'success', 2500);
    } catch (e) {
      const range = document.createRange();
      range.selectNodeContents(num);
      const sel = window.getSelection();
      sel.removeAllRanges();
      sel.addRange(range);
      window.Gambasi.toast('Tekan lama / Ctrl+C untuk menyalin.', 'info', 3000);
    }
  });
})();
