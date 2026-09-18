/**
 * LockService.gs
 * Wrapper LockService bawaan Apps Script.
 * Catatan: jangan mendeklarasikan variabel global bernama `LockService`
 * karena akan menimpa service bawaan.
 */

function withScriptLock_(fn, timeoutMs) {
  var lock = LockService.getScriptLock();
  var acquired = lock.tryLock(timeoutMs || 20000);
  if (!acquired) {
    throw new AppError('BUSY', 'Server sedang sibuk memproses pendaftaran lain. Silakan coba lagi sebentar.');
  }
  try {
    return fn();
  } finally {
    try {
      SpreadsheetApp.flush();
    } catch (ignore) { /* noop */ }
    lock.releaseLock();
  }
}

/**
 * Generate nomor pendaftaran berikutnya. WAJIB dipanggil di dalam withScriptLock_.
 * Menggunakan nilai terbesar antara counter Script Property dan data di sheet,
 * sehingga tetap unik walaupun counter pernah di-reset.
 */
function nextRegistrationNumber_() {
  var props = getProps_();
  var counter = parseInt(props.getProperty('REG_SEQ') || '0', 10) || 0;
  var maxSeen = counter;
  var prefix = APP_CONFIG.REG_PREFIX;

  function scan(list) {
    list.forEach(function (r) {
      var no = r.nomor_pendaftaran;
      if (no && no.indexOf(prefix) === 0) {
        var n = parseInt(no.substring(prefix.length), 10);
        if (n > maxSeen) maxSeen = n;
      }
    });
  }
  scan(readTable_(APP_CONFIG.SHEETS.CLUB));
  scan(readTable_(APP_CONFIG.SHEETS.SUBMISSION));

  var next = maxSeen + 1;
  props.setProperty('REG_SEQ', String(next));
  return prefix + padNumber_(next, APP_CONFIG.REG_PAD);
}
