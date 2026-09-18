/**
 * Config.gs
 * Konfigurasi terpusat backend GAMBASI Papua Selatan 2026.
 *
 * ID Spreadsheet, ID Folder, dan secret TIDAK di-hardcode di sini.
 * Semuanya dibaca dari Script Properties (Project Settings > Script Properties).
 *
 * Aturan usia (MIN_AGE_U10, MAX_AGE_U10, MIN_AGE_U12, MAX_AGE_U12, AGE_REFERENCE_DATE)
 * WAJIB diisi panitia melalui Script Properties atau menu Pengaturan admin.
 * Selama belum diisi, pendaftaran ditolak dengan error CONFIG_ERROR
 * (sistem tidak mengarang batas usia / tanggal cutoff).
 */

var APP_CONFIG = {
  VERSION: '1.0.0',

  // ---- EVENT MASTER DATA (RESMI - JANGAN DIUBAH TANPA INSTRUKSI) ----
  EVENT: {
    NAMA: 'GAMBASI PAPUA SELATAN',
    KEGIATAN: 'FESTIVAL SEPAK BOLA USIA DINI U-10 & U-12',
    KOMPETISI: 'PIALA DPR PAPUA SELATAN KE-2 TAHUN 2026',
    TANGGAL: '30 OKTOBER – 1 NOVEMBER 2026',
    LOKASI: 'LAPANGAN KODIM MERAUKE',
    TEMA: 'Membangun Karakter, Sportivitas, dan Kecintaan terhadap Sepak Bola Sejak Dini',
    WILAYAH: 'MERAUKE — PAPUA SELATAN'
  },

  CATEGORIES: ['U10', 'U12'],
  CATEGORY_LABELS: { U10: 'U-10', U12: 'U-12' },

  STATUSES: ['PENDING', 'REVIEW', 'REVISION', 'VERIFIED', 'REJECTED'],
  DEFAULT_STATUS: 'PENDING',
  // Status pemain yang boleh di-set admin (sesuai spesifikasi) + REVIEW/PENDING untuk reset
  PLAYER_STATUSES: ['PENDING', 'REVIEW', 'REVISION', 'VERIFIED', 'REJECTED'],

  REG_PREFIX: 'GMB-SB-2026-',
  REG_PAD: 4,
  REG_PATTERN: /^GMB-SB-2026-\d{4,6}$/,

  // Akun Google resmi pemilik penyimpanan (Google Sheets + Google Drive).
  // Apps Script WAJIB dibuat & di-deploy saat login dengan akun ini.
  // Dapat diganti lewat Script Property STORAGE_OWNER_EMAIL.
  STORAGE_OWNER_EMAIL: 'gambasipapsel@gmail.com',
  DRIVE_HOME_URL: 'https://drive.google.com/drive/home',

  SPREADSHEET_NAME: 'GAMBASI_DATABASE_2026',
  ROOT_FOLDER_NAME: 'GAMBASI PAPUA SELATAN 2026',
  CLUB_FOLDER_NAME: 'CLUB',
  LOGO_FOLDER_NAME: 'LOGO_CLUB',
  AKTE_FOLDER_NAME: 'AKTE_KELAHIRAN',
  FOTO_FOLDER_NAME: 'FOTO_FULL_BODY',

  SHEETS: {
    CLUB: 'CLUB',
    PEMAIN: 'PEMAIN',
    LOG: 'LOG',
    SUBMISSION: 'SUBMISSION'
  },

  COLUMNS: {
    CLUB: [
      'club_id', 'nomor_pendaftaran', 'kategori', 'nama_club', 'nama_manager', 'nama_pelatih',
      'whatsapp', 'email', 'alamat', 'kampung', 'distrik', 'kabupaten', 'provinsi',
      'logo_file_id', 'logo_url', 'jumlah_pemain', 'status', 'catatan_admin',
      'created_at', 'updated_at'
    ],
    PEMAIN: [
      'pemain_id', 'club_id', 'nomor_pendaftaran', 'kategori', 'nama_lengkap', 'nik',
      'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'posisi', 'nomor_punggung',
      'nama_ayah', 'nama_ibu', 'nama_wali', 'whatsapp_wali', 'alamat',
      'akte_file_id', 'akte_url', 'foto_file_id', 'foto_url',
      'status_verifikasi', 'catatan_admin', 'created_at', 'updated_at'
    ],
    // 6 kolom wajib + aktor & detail (tanpa NIK / isi dokumen)
    LOG: ['log_id', 'club_id', 'pemain_id', 'aktivitas', 'status', 'timestamp', 'aktor', 'detail'],
    // Sheet internal untuk tracking idempotensi & recovery submission
    SUBMISSION: [
      'submission_token', 'state', 'club_id', 'nomor_pendaftaran', 'folder_id',
      'payload_json', 'files_json', 'error', 'created_at', 'updated_at'
    ]
  },

  SUBMISSION_STATES: {
    UPLOADING: 'UPLOADING',
    FINALIZING: 'FINALIZING',
    FAILED: 'FAILED',
    COMPLETE: 'COMPLETE',
    EXPIRED: 'EXPIRED'
  },
  SUBMISSION_EXPIRE_HOURS: 48,

  FILES: {
    MAX_BYTES: 5 * 1024 * 1024,
    RULES: {
      akte: {
        mimes: ['application/pdf', 'image/jpeg', 'image/png'],
        exts: ['pdf', 'jpg', 'jpeg', 'png']
      },
      foto: {
        mimes: ['image/jpeg', 'image/png'],
        exts: ['jpg', 'jpeg', 'png']
      },
      logo: {
        mimes: ['image/jpeg', 'image/png'],
        exts: ['jpg', 'jpeg', 'png']
      }
    },
    BLOCKED_EXTS: ['php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar', 'js', 'mjs', 'exe',
      'bat', 'cmd', 'sh', 'com', 'msi', 'vbs', 'ps1', 'jar', 'html', 'htm', 'svg', 'scr', 'dll']
  },

  // Batas teknis untuk menjaga ukuran payload. Batas resmi jumlah pemain diatur panitia
  // melalui Script Property MIN_PLAYERS / MAX_PLAYERS.
  TECHNICAL_MAX_PLAYERS: 40,

  // Setting yang boleh diubah dari menu Pengaturan admin (SUPERADMIN).
  EDITABLE_SETTINGS: {
    REGISTRATION_OPEN: 'bool',
    MIN_AGE_U10: 'int',
    MAX_AGE_U10: 'int',
    MIN_AGE_U12: 'int',
    MAX_AGE_U12: 'int',
    AGE_REFERENCE_DATE: 'date',
    MIN_PLAYERS: 'int',
    MAX_PLAYERS: 'int'
  },

  ROLES: {
    SUPERADMIN: ['view', 'view_sensitive', 'view_documents', 'update_status', 'add_note',
      'export', 'export_sensitive', 'view_logs', 'settings'],
    VERIFIKATOR: ['view', 'view_sensitive', 'view_documents', 'update_status', 'add_note',
      'export', 'view_logs'],
    VIEWER: ['view', 'export']
  }
};

function getProps_() {
  return PropertiesService.getScriptProperties();
}

function getSetting_(key) {
  var v = getProps_().getProperty(key);
  return v === null || v === undefined ? '' : String(v).trim();
}

function getRequiredSetting_(key) {
  var v = getSetting_(key);
  if (!v) {
    throw new AppError('CONFIG_ERROR', 'Konfigurasi server belum lengkap. Hubungi panitia.', { key: key });
  }
  return v;
}

function getEnvironment_() {
  return getSetting_('ENVIRONMENT') || 'production';
}

function getStorageOwnerEmail_() {
  return (getSetting_('STORAGE_OWNER_EMAIL') || APP_CONFIG.STORAGE_OWNER_EMAIL).toLowerCase();
}

/** Email akun yang menjalankan script (pemilik Drive/Sheets tempat data disimpan). */
function getRunningAccountEmail_() {
  try {
    return String(Session.getEffectiveUser().getEmail() || '').toLowerCase();
  } catch (e) {
    return '';
  }
}

/**
 * Status akun penyimpanan.
 * ok=false hanya jika email akun terdeteksi DAN berbeda dari akun resmi.
 */
function getStorageAccountStatus_() {
  var expected = getStorageOwnerEmail_();
  var actual = getRunningAccountEmail_();
  return {
    expected: expected,
    actual: actual,
    verified: actual !== '' && actual === expected,
    mismatch: actual !== '' && actual !== expected
  };
}

/** Tolak penyimpanan data bila script berjalan di akun Google yang salah. */
function assertStorageAccount_() {
  if (getStorageAccountStatus_().mismatch) {
    throw new AppError('CONFIG_ERROR', 'Penyimpanan belum terhubung ke akun resmi panitia. Hubungi admin.');
  }
}

function isRegistrationOpen_() {
  var v = getSetting_('REGISTRATION_OPEN').toLowerCase();
  if (v === '') return true;
  return v === 'true' || v === '1' || v === 'yes';
}

function parseIntSetting_(key) {
  var v = getSetting_(key);
  if (v === '' || !/^\d{1,3}$/.test(v)) return null;
  return parseInt(v, 10);
}

/**
 * Aturan usia per kategori. Usia dihitung dalam tahun penuh pada AGE_REFERENCE_DATE.
 * Mengembalikan null jika panitia belum mengisi konfigurasi.
 */
function getAgeRules_(category) {
  var min = parseIntSetting_('MIN_AGE_' + category);
  var max = parseIntSetting_('MAX_AGE_' + category);
  var ref = getSetting_('AGE_REFERENCE_DATE');
  if (min === null || max === null || !isValidIsoDate_(ref) || min > max) {
    return null;
  }
  return { min: min, max: max, reference_date: ref };
}

function getPlayerLimits_() {
  var min = parseIntSetting_('MIN_PLAYERS');
  var max = parseIntSetting_('MAX_PLAYERS');
  if (min === null || min < 1) min = 1;
  if (max === null || max > APP_CONFIG.TECHNICAL_MAX_PLAYERS) max = APP_CONFIG.TECHNICAL_MAX_PLAYERS;
  if (max < min) max = min;
  return {
    min: min,
    max: max,
    configured: parseIntSetting_('MAX_PLAYERS') !== null
  };
}
