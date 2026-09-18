/**
 * Code.gs
 * Entry point Web App GAMBASI Papua Selatan 2026.
 *
 * Semua request: POST JSON
 *   { "action": "...", "secret": "<API_SECRET>", "data": {...}, "admin": {"username","role"} }
 * Hanya server PHP yang mengetahui secret. Browser tidak pernah memanggil Web App ini langsung.
 */

var PUBLIC_ACTIONS_ = {
  public_config: function () { return publicConfig_(); },
  check_status: function (req) { return checkStatus_(req); },
  submit_init: function (req) { return submitInit_(req); },
  upload_file: function (req) { return uploadFile_(req); },
  submit_finalize: function (req) { return submitFinalize_(req); },
  admin_auth_event: function (req) { return adminAuthEvent_(req); }
};

// Dibungkus fungsi agar referensi ke file lain di-resolve saat dipanggil
// (file .gs dievaluasi berurutan).
var ADMIN_ACTIONS_ = {
  admin_stats: function (r, c) { return adminStats_(r, c); },
  admin_list_clubs: function (r, c) { return adminListClubs_(r, c); },
  admin_get_club: function (r, c) { return adminGetClub_(r, c); },
  admin_list_players: function (r, c) { return adminListPlayers_(r, c); },
  admin_get_player: function (r, c) { return adminGetPlayer_(r, c); },
  admin_update_status: function (r, c) { return adminUpdateStatus_(r, c); },
  admin_add_note: function (r, c) { return adminAddNote_(r, c); },
  admin_get_file: function (r, c) { return adminGetFile_(r, c); },
  admin_export: function (r, c) { return adminExport_(r, c); },
  admin_list_logs: function (r, c) { return adminListLogs_(r, c); },
  admin_get_settings: function (r, c) { return adminGetSettings_(r, c); },
  admin_update_settings: function (r, c) { return adminUpdateSettings_(r, c); }
};

var WRITE_ACTIONS_ = ['submit_init', 'upload_file', 'submit_finalize', 'admin_update_status', 'admin_add_note', 'admin_update_settings'];

function handleRequest_(req) {
  if (!req || typeof req !== 'object') {
    throw new AppError('BAD_REQUEST', 'Permintaan tidak valid.');
  }
  verifyApiSecret_(req);
  var action = String(req.action || '');
  if (WRITE_ACTIONS_.indexOf(action) !== -1) {
    // Data peserta hanya boleh tersimpan di Drive/Sheets akun resmi
    assertStorageAccount_();
  }
  if (PUBLIC_ACTIONS_.hasOwnProperty(action)) {
    return PUBLIC_ACTIONS_[action](req);
  }
  if (ADMIN_ACTIONS_.hasOwnProperty(action)) {
    var ctx = getAdminContext_(req);
    return ADMIN_ACTIONS_[action](req, ctx);
  }
  throw new AppError('UNKNOWN_ACTION', 'Aksi tidak dikenal.');
}

function doPost(e) {
  var result;
  try {
    var body = e && e.postData && e.postData.contents ? e.postData.contents : '';
    var req;
    try {
      req = JSON.parse(body);
    } catch (parseErr) {
      throw new AppError('BAD_REQUEST', 'Format permintaan tidak valid.');
    }
    result = handleRequest_(req);
  } catch (err) {
    result = errorToResponse_(err);
  }
  return jsonOutput_(result);
}

/** Health check tanpa data apa pun. */
function doGet() {
  return jsonOutput_(ok_('GAMBASI API aktif', { service: 'gambasi-api', version: APP_CONFIG.VERSION }));
}

// ---------------------------------------------------------------------------
// SETUP (jalankan manual SEKALI dari editor Apps Script: pilih fungsi setupProject > Run)
// ---------------------------------------------------------------------------

function setupProject() {
  var props = getProps_();

  // 0. Pastikan dijalankan dari akun Google resmi agar Sheets & Drive tersimpan di akun tersebut
  var account = getStorageAccountStatus_();
  if (account.mismatch) {
    throw new Error('Script dijalankan dengan akun ' + account.actual + '. Silakan login sebagai ' +
      account.expected + ' lalu buat/jalankan ulang project Apps Script ini.');
  }
  if (!account.verified) {
    Logger.log('PERINGATAN: email akun tidak dapat diverifikasi. Pastikan Anda login sebagai ' + account.expected + '.');
  }

  // 1. Spreadsheet
  var ssId = props.getProperty('SPREADSHEET_ID');
  var ss;
  if (ssId) {
    ss = SpreadsheetApp.openById(ssId);
  } else {
    ss = SpreadsheetApp.create(APP_CONFIG.SPREADSHEET_NAME);
    props.setProperty('SPREADSHEET_ID', ss.getId());
  }
  SPREADSHEET_CACHE_ = ss;

  Object.keys(APP_CONFIG.SHEETS).forEach(function (key) {
    var name = APP_CONFIG.SHEETS[key];
    var cols = APP_CONFIG.COLUMNS[name];
    var sheet = ss.getSheetByName(name) || ss.insertSheet(name);
    var header = sheet.getRange(1, 1, 1, cols.length);
    header.setValues([cols]);
    header.setFontWeight('bold');
    sheet.setFrozenRows(1);
  });
  // Hapus "Sheet1" bawaan jika kosong
  var def = ss.getSheetByName('Sheet1');
  if (def && ss.getSheets().length > 1 && def.getLastRow() === 0) ss.deleteSheet(def);

  // 2. Folder Drive (privat)
  var rootId = props.getProperty('ROOT_FOLDER_ID');
  var root;
  if (rootId) {
    root = DriveApp.getFolderById(rootId);
  } else {
    var it = DriveApp.getFoldersByName(APP_CONFIG.ROOT_FOLDER_NAME);
    root = it.hasNext() ? it.next() : DriveApp.createFolder(APP_CONFIG.ROOT_FOLDER_NAME);
    props.setProperty('ROOT_FOLDER_ID', root.getId());
  }
  makePrivate_(root);
  getOrCreateFolder_(root, APP_CONFIG.CLUB_FOLDER_NAME);
  getOrCreateFolder_(root, APP_CONFIG.LOGO_FOLDER_NAME);

  // 3. Environment & secret
  if (!props.getProperty('ENVIRONMENT')) props.setProperty('ENVIRONMENT', 'production');
  if (!props.getProperty('REGISTRATION_OPEN')) props.setProperty('REGISTRATION_OPEN', 'true');
  var secret = props.getProperty('API_SECRET');
  if (!secret || secret.length < 32) {
    secret = (Utilities.getUuid() + Utilities.getUuid()).replace(/-/g, '');
    props.setProperty('API_SECRET', secret);
    Logger.log('API_SECRET baru dibuat. Salin dari Project Settings > Script Properties ke file .env (GAMBASI_API_SECRET).');
  }

  var missing = ['MIN_AGE_U10', 'MAX_AGE_U10', 'MIN_AGE_U12', 'MAX_AGE_U12', 'AGE_REFERENCE_DATE']
    .filter(function (k) { return !props.getProperty(k); });

  Logger.log('Setup selesai di akun ' + (account.actual || '(tidak terdeteksi)') + '. Spreadsheet: ' +
    ss.getName() + ', Folder Drive: ' + root.getName() + ' (' + APP_CONFIG.DRIVE_HOME_URL + ')');
  if (missing.length) {
    Logger.log('PERHATIAN: aturan usia belum diisi panitia (' + missing.join(', ') +
      '). Pendaftaran akan ditolak sampai nilai ini diisi.');
  }
}

/** Pasang trigger pembersihan submission terbengkalai (jalankan manual sekali). */
function installCleanupTrigger() {
  ScriptApp.getProjectTriggers().forEach(function (t) {
    if (t.getHandlerFunction() === 'cleanupStaleSubmissions') ScriptApp.deleteTrigger(t);
  });
  ScriptApp.newTrigger('cleanupStaleSubmissions').timeBased().everyHours(6).create();
  Logger.log('Trigger cleanupStaleSubmissions terpasang (setiap 6 jam).');
}
