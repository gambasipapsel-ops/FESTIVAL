/**
 * LogService.gs
 * Audit log. TIDAK menyimpan NIK, isi dokumen, atau data orang tua.
 *
 * Aktivitas: CREATE_CLUB, ADD_PLAYER, UPLOAD_AKTE, UPLOAD_FOTO, UPLOAD_LOGO, SUBMIT,
 * SUBMIT_FAILED, ADMIN_LOGIN, ADMIN_LOGIN_FAILED, ADMIN_LOGOUT, ADMIN_VIEW,
 * ADMIN_UPDATE_STATUS, ADMIN_ADD_NOTE, ADMIN_EXPORT, ADMIN_UPDATE_SETTING
 */

var LOG_ACTIVITIES_ = [
  'CREATE_CLUB', 'ADD_PLAYER', 'UPLOAD_AKTE', 'UPLOAD_FOTO', 'UPLOAD_LOGO', 'SUBMIT',
  'SUBMIT_FAILED', 'ADMIN_LOGIN', 'ADMIN_LOGIN_FAILED', 'ADMIN_LOGOUT', 'ADMIN_VIEW',
  'ADMIN_UPDATE_STATUS', 'ADMIN_ADD_NOTE', 'ADMIN_EXPORT', 'ADMIN_UPDATE_SETTING',
  'SUBMISSION_EXPIRED'
];

function buildLog_(activity, opts) {
  opts = opts || {};
  return {
    log_id: newId_('LOG'),
    club_id: opts.clubId || '',
    pemain_id: opts.pemainId || '',
    aktivitas: activity,
    status: opts.status || 'OK',
    timestamp: nowIso_(),
    aktor: opts.actor || 'PUBLIC',
    detail: cleanText_(opts.detail || '', 300)
  };
}

function writeLog_(activity, opts) {
  writeLogs_([buildLog_(activity, opts)]);
}

/** Tulis banyak log sekaligus. Kegagalan log tidak menggagalkan proses utama. */
function writeLogs_(entries) {
  try {
    appendObjects_(APP_CONFIG.SHEETS.LOG, entries);
  } catch (e) {
    try { console.error('Log write failed: ' + e.message); } catch (ignore) { /* noop */ }
  }
}

function adminListLogs_(req, ctx) {
  requirePermission_(ctx, 'view_logs');
  var d = req.data || {};
  var activity = String(d.aktivitas || '').toUpperCase();
  var q = cleanText_(d.q, 60).toLowerCase();
  var logs = readTable_(APP_CONFIG.SHEETS.LOG).filter(function (l) {
    if (activity && l.aktivitas !== activity) return false;
    if (q && (l.club_id + ' ' + l.pemain_id + ' ' + l.aktor + ' ' + l.detail).toLowerCase().indexOf(q) === -1) return false;
    return true;
  });
  logs.reverse();
  var page = paginate_(logs, d.page, d.per_page || 50);
  page.items = page.items.map(function (l) {
    return {
      log_id: l.log_id, club_id: l.club_id, pemain_id: l.pemain_id, aktivitas: l.aktivitas,
      status: l.status, timestamp: l.timestamp, aktor: l.aktor, detail: l.detail
    };
  });
  page.activities = LOG_ACTIVITIES_;
  return ok_('OK', page);
}
