/**
 * Validation.gs
 * Validasi & sanitasi server-side. Validasi frontend BUKAN security;
 * semua aturan penting diperiksa ulang di sini.
 */

var POSITIONS_ = ['KIPER', 'BELAKANG', 'TENGAH', 'DEPAN'];
var GENDERS_ = ['L', 'P'];

function validationError_(errors) {
  return new AppError('VALIDATION_ERROR', 'Data pendaftaran belum valid. Periksa kembali isian Anda.', { errors: errors });
}

function isValidSubmissionToken_(token) {
  return typeof token === 'string' && /^[A-Za-z0-9-]{32,64}$/.test(token);
}

function isValidPlayerRef_(ref) {
  return typeof ref === 'string' && /^[a-z0-9]{6,16}$/.test(ref);
}

/**
 * Validasi seluruh payload pendaftaran.
 * @return {{kategori:string, club:Object, players:Object[], has_logo:boolean}}
 */
function validateRegistrationPayload_(p) {
  var errors = [];
  function err(field, message) { errors.push({ field: field, message: message }); }

  if (!p || typeof p !== 'object') throw validationError_([{ field: 'payload', message: 'Payload tidak valid.' }]);

  // ---- Kategori ----
  var kategori = String(p.kategori || '');
  if (APP_CONFIG.CATEGORIES.indexOf(kategori) === -1) {
    throw new AppError('INVALID_CATEGORY', 'Kategori tidak valid. Pilih U10 atau U12.');
  }
  var ageRules = getAgeRules_(kategori);
  if (!ageRules) {
    throw new AppError('CONFIG_ERROR', 'Aturan usia kategori belum ditetapkan panitia. Pendaftaran belum dapat diproses.');
  }

  // ---- Club & Official ----
  var c = p.club || {};
  var o = p.official || {};
  var club = {
    nama_club: cleanText_(c.nama_club, 100),
    alamat: cleanText_(c.alamat, 250),
    kampung: cleanText_(c.kampung, 100),
    distrik: cleanText_(c.distrik, 100),
    kabupaten: cleanText_(c.kabupaten, 100),
    provinsi: cleanText_(c.provinsi, 100),
    nama_manager: cleanText_(o.nama_manager, 100),
    nama_pelatih: cleanText_(o.nama_pelatih, 100),
    whatsapp: normalizePhone_(o.whatsapp),
    email: cleanText_(o.email, 120).toLowerCase()
  };

  if (club.nama_club.length < 3) err('club.nama_club', 'Nama club wajib diisi (minimal 3 karakter).');
  if (club.nama_manager.length < 3) err('official.nama_manager', 'Nama manager wajib diisi.');
  if (club.nama_pelatih.length < 3) err('official.nama_pelatih', 'Nama pelatih wajib diisi.');
  if (!club.whatsapp) err('official.whatsapp', 'Nomor WhatsApp tidak valid (contoh: 081234567890).');
  if (club.email && !isValidEmail_(club.email)) err('official.email', 'Format email tidak valid.');
  if (club.alamat.length < 5) err('club.alamat', 'Alamat wajib diisi.');
  if (!club.distrik) err('club.distrik', 'Distrik wajib diisi.');
  if (!club.kabupaten) err('club.kabupaten', 'Kabupaten wajib diisi.');
  if (!club.provinsi) err('club.provinsi', 'Provinsi wajib diisi.');

  // ---- Pemain ----
  var limits = getPlayerLimits_();
  var list = Array.isArray(p.players) ? p.players : [];
  if (list.length < limits.min) err('players', 'Minimal ' + limits.min + ' pemain.');
  if (list.length > limits.max) {
    throw validationError_([{ field: 'players', message: 'Maksimal ' + limits.max + ' pemain per club.' }]);
  }

  var today = todayIso_();
  var seenRefs = {}, seenNik = {}, seenNumber = {};
  var players = [];

  list.forEach(function (raw, i) {
    raw = raw || {};
    var label = 'Pemain #' + (i + 1);
    var f = 'players[' + i + '].';
    var pl = {
      ref: String(raw.ref || ''),
      nama_lengkap: cleanText_(raw.nama_lengkap, 100),
      nik: String(raw.nik || '').replace(/\s/g, ''),
      tempat_lahir: cleanText_(raw.tempat_lahir, 80),
      tanggal_lahir: String(raw.tanggal_lahir || ''),
      jenis_kelamin: String(raw.jenis_kelamin || '').toUpperCase(),
      posisi: String(raw.posisi || '').toUpperCase(),
      nomor_punggung: String(raw.nomor_punggung || '').replace(/^0+(?=\d)/, ''),
      nama_ayah: cleanText_(raw.nama_ayah, 100),
      nama_ibu: cleanText_(raw.nama_ibu, 100),
      nama_wali: cleanText_(raw.nama_wali, 100),
      whatsapp_wali: normalizePhone_(raw.whatsapp_wali),
      alamat: cleanText_(raw.alamat, 250)
    };

    if (!isValidPlayerRef_(pl.ref)) err(f + 'ref', label + ': referensi pemain tidak valid.');
    else if (seenRefs[pl.ref]) err(f + 'ref', label + ': referensi pemain duplikat.');
    seenRefs[pl.ref] = true;

    if (pl.nama_lengkap.length < 3) err(f + 'nama_lengkap', label + ': nama lengkap wajib diisi.');

    if (!/^\d{16}$/.test(pl.nik) || /^0{16}$/.test(pl.nik)) {
      err(f + 'nik', label + ': NIK harus 16 digit angka.');
    } else if (seenNik[pl.nik]) {
      err(f + 'nik', label + ': NIK sama dengan pemain lain di pendaftaran ini.');
    }
    seenNik[pl.nik] = true;

    if (!pl.tempat_lahir) err(f + 'tempat_lahir', label + ': tempat lahir wajib diisi.');

    if (!isValidIsoDate_(pl.tanggal_lahir)) {
      err(f + 'tanggal_lahir', label + ': tanggal lahir tidak valid.');
    } else if (pl.tanggal_lahir > today) {
      err(f + 'tanggal_lahir', label + ': tanggal lahir tidak boleh di masa depan.');
    } else {
      var age = ageOnDate_(pl.tanggal_lahir, ageRules.reference_date);
      if (age < ageRules.min || age > ageRules.max) {
        errors.push({
          field: f + 'tanggal_lahir',
          code: 'INVALID_AGE',
          message: label + ': usia ' + age + ' tahun (per ' + ageRules.reference_date +
            ') tidak sesuai kategori ' + APP_CONFIG.CATEGORY_LABELS[kategori] +
            ' (' + ageRules.min + '–' + ageRules.max + ' tahun).'
        });
      }
    }

    if (GENDERS_.indexOf(pl.jenis_kelamin) === -1) err(f + 'jenis_kelamin', label + ': jenis kelamin wajib dipilih.');
    if (POSITIONS_.indexOf(pl.posisi) === -1) err(f + 'posisi', label + ': posisi wajib dipilih.');

    if (!/^\d{1,2}$/.test(pl.nomor_punggung) || parseInt(pl.nomor_punggung, 10) < 1) {
      err(f + 'nomor_punggung', label + ': nomor punggung harus 1–99.');
    } else if (seenNumber[pl.nomor_punggung]) {
      err(f + 'nomor_punggung', label + ': nomor punggung ' + pl.nomor_punggung + ' sudah dipakai pemain lain.');
    }
    seenNumber[pl.nomor_punggung] = true;

    if (!pl.nama_ayah && !pl.nama_ibu && !pl.nama_wali) {
      err(f + 'nama_ayah', label + ': isi minimal salah satu nama ayah, ibu, atau wali.');
    }
    if (!pl.whatsapp_wali) err(f + 'whatsapp_wali', label + ': nomor WhatsApp orang tua/wali tidak valid.');
    if (pl.alamat.length < 5) err(f + 'alamat', label + ': alamat wajib diisi.');

    players.push(pl);
  });

  if (errors.length) {
    var onlyAge = errors.every(function (e) { return e.code === 'INVALID_AGE'; });
    var e = validationError_(errors);
    if (onlyAge) {
      e.code = 'INVALID_AGE';
      e.message = 'Usia pemain tidak sesuai kategori.';
    }
    throw e;
  }

  return { kategori: kategori, club: club, players: players, has_logo: p.club && p.club.has_logo === true };
}

// ---------------------------------------------------------------------------
// Validasi file
// ---------------------------------------------------------------------------

function detectMimeFromBytes_(bytes) {
  function b(i) { return bytes[i] & 0xFF; }
  if (bytes.length >= 5 && b(0) === 0x25 && b(1) === 0x50 && b(2) === 0x44 && b(3) === 0x46 && b(4) === 0x2D) {
    return 'application/pdf';
  }
  if (bytes.length >= 3 && b(0) === 0xFF && b(1) === 0xD8 && b(2) === 0xFF) {
    return 'image/jpeg';
  }
  if (bytes.length >= 8 && b(0) === 0x89 && b(1) === 0x50 && b(2) === 0x4E && b(3) === 0x47 &&
    b(4) === 0x0D && b(5) === 0x0A && b(6) === 0x1A && b(7) === 0x0A) {
    return 'image/png';
  }
  return '';
}

/**
 * Validasi file upload: ekstensi, MIME yang diklaim, MIME hasil deteksi isi (magic bytes), ukuran.
 * @return {{bytes:number[], mime:string, ext:string}}
 */
function validateUploadedFile_(docType, fileName, claimedMime, base64) {
  var rules = APP_CONFIG.FILES.RULES[docType];
  if (!rules) throw new AppError('INVALID_FILE', 'Jenis dokumen tidak dikenal.');

  var name = String(fileName || '');
  var parts = name.toLowerCase().split('.');
  if (parts.length < 2) throw new AppError('INVALID_FILE', 'Nama file harus memiliki ekstensi.');
  // Tolak double extension berbahaya, mis. foto.php.jpg
  for (var i = 1; i < parts.length; i++) {
    if (APP_CONFIG.FILES.BLOCKED_EXTS.indexOf(parts[i]) !== -1) {
      throw new AppError('INVALID_FILE', 'Tipe file tidak diizinkan.');
    }
  }
  var ext = parts[parts.length - 1];
  if (rules.exts.indexOf(ext) === -1) {
    throw new AppError('INVALID_FILE', 'Format file tidak diizinkan. Gunakan: ' + rules.exts.join(', ').toUpperCase() + '.');
  }

  var mime = String(claimedMime || '').toLowerCase();
  if (rules.mimes.indexOf(mime) === -1) throw new AppError('INVALID_MIME', 'Tipe file (MIME) tidak diizinkan.');

  if (typeof base64 !== 'string' || !base64.length || !/^[A-Za-z0-9+/]+={0,2}$/.test(base64)) {
    throw new AppError('INVALID_FILE', 'Data file tidak valid.');
  }
  // Perkiraan ukuran sebelum decode (hindari decode data raksasa)
  if (Math.floor(base64.length * 3 / 4) > APP_CONFIG.FILES.MAX_BYTES + 3) {
    throw new AppError('FILE_TOO_LARGE', 'Ukuran file melebihi 5 MB.');
  }

  var bytes;
  try {
    bytes = Utilities.base64Decode(base64);
  } catch (e) {
    throw new AppError('INVALID_FILE', 'Data file tidak valid.');
  }
  if (!bytes.length) throw new AppError('INVALID_FILE', 'File kosong.');
  if (bytes.length > APP_CONFIG.FILES.MAX_BYTES) throw new AppError('FILE_TOO_LARGE', 'Ukuran file melebihi 5 MB.');

  var detected = detectMimeFromBytes_(bytes);
  if (!detected || detected !== mime || rules.mimes.indexOf(detected) === -1) {
    throw new AppError('INVALID_MIME', 'Isi file tidak sesuai dengan tipenya.');
  }
  var extOk = (detected === 'application/pdf' && ext === 'pdf') ||
    (detected === 'image/jpeg' && (ext === 'jpg' || ext === 'jpeg')) ||
    (detected === 'image/png' && ext === 'png');
  if (!extOk) throw new AppError('INVALID_MIME', 'Ekstensi file tidak sesuai dengan isinya.');

  return { bytes: bytes, mime: detected, ext: ext === 'jpeg' ? 'jpg' : ext };
}
