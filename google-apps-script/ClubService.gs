/**
 * ClubService.gs
 * Alur pendaftaran club (transaction-like) + fitur admin untuk club.
 *
 * Alur (lihat README bagian "Alur Submission"):
 *  1. submit_init      : validasi seluruh payload, cek duplikat, buat record SUBMISSION (UPLOADING)
 *                         + folder sementara. Idempotent berdasarkan submission_token.
 *  2. upload_file      : per file (akte/foto per pemain, logo opsional). Validasi MIME/ekstensi/ukuran/
 *                         magic bytes, simpan ke Drive, verifikasi, catat di SUBMISSION.files_json.
 *  3. submit_finalize  : pastikan semua dokumen wajib ada di Drive, generate nomor (LockService),
 *                         tulis PEMAIN + CLUB, verifikasi, tandai COMPLETE.
 *                         Jika gagal: rollback baris, state FAILED, TIDAK ada status sukses palsu.
 */

function parseJson_(s, fallback) {
  if (!s) return fallback;
  try { return JSON.parse(s); } catch (e) { return fallback; }
}

function normalizeClubName_(s) {
  return String(s || '').toUpperCase().replace(/[^A-Z0-9]+/g, ' ').replace(/\s+/g, ' ').trim();
}

function requiredSlots_(normalized) {
  var required = [];
  normalized.players.forEach(function (p) {
    required.push('akte:' + p.ref);
    required.push('foto:' + p.ref);
  });
  return { required: required, optional: ['logo'], all: required.concat(['logo']) };
}

function slotLabel_(slot, normalized) {
  var info = parseSlot_(slot);
  if (!info) return slot;
  if (info.docType === 'logo') return 'Logo club';
  var idx = -1;
  for (var i = 0; i < normalized.players.length; i++) {
    if (normalized.players[i].ref === info.ref) { idx = i; break; }
  }
  var who = idx >= 0 ? 'Pemain #' + (idx + 1) + ' (' + normalized.players[idx].nama_lengkap + ')' : 'Pemain';
  return who + ': ' + (info.docType === 'akte' ? 'Akte kelahiran' : 'Foto full body') + ' belum diunggah.';
}

/**
 * Cek duplikat terhadap data yang sudah tersimpan (selain club milik submission ini).
 */
function checkDuplicates_(normalized, ownClubId) {
  var S = APP_CONFIG.SHEETS;
  var clubs = readTable_(S.CLUB);
  var activeClubIds = {};
  var targetName = normalizeClubName_(normalized.club.nama_club);

  clubs.forEach(function (c) {
    if (c.club_id === ownClubId || c.status === 'REJECTED') return;
    activeClubIds[c.club_id] = true;
    if (c.kategori === normalized.kategori && normalizeClubName_(c.nama_club) === targetName) {
      throw new AppError('DUPLICATE_CLUB',
        'Club "' + normalized.club.nama_club + '" sudah terdaftar pada kategori ' +
        APP_CONFIG.CATEGORY_LABELS[normalized.kategori] + ' (' + c.nomor_pendaftaran + '). ' +
        'Gunakan menu Cek Pendaftaran atau hubungi panitia.');
    }
  });

  var registeredNik = {};
  readTable_(S.PEMAIN).forEach(function (p) {
    if (p.club_id === ownClubId || p.status_verifikasi === 'REJECTED' || !activeClubIds[p.club_id]) return;
    registeredNik[p.nik] = true;
  });

  var errors = [];
  normalized.players.forEach(function (p, i) {
    if (registeredNik[p.nik]) {
      errors.push({
        field: 'players[' + i + '].nik',
        message: 'Pemain #' + (i + 1) + ' (' + p.nama_lengkap + '): NIK sudah terdaftar pada pendaftaran lain.'
      });
    }
  });
  if (errors.length) {
    throw new AppError('DUPLICATE_NIK', 'Terdapat pemain yang sudah terdaftar.', { errors: errors });
  }
}

function completeResponse_(sub, already) {
  var club = findByField_(APP_CONFIG.SHEETS.CLUB, 'club_id', sub.club_id) || {};
  return {
    nomor_pendaftaran: sub.nomor_pendaftaran,
    nama_club: club.nama_club || '',
    kategori: club.kategori || '',
    status: club.status || APP_CONFIG.DEFAULT_STATUS,
    jumlah_pemain: parseInt(club.jumlah_pemain || '0', 10),
    already_submitted: already === true,
    state: APP_CONFIG.SUBMISSION_STATES.COMPLETE
  };
}

// ---------------------------------------------------------------------------
// 1. submit_init
// ---------------------------------------------------------------------------

function submitInit_(req) {
  if (!isRegistrationOpen_()) {
    throw new AppError('REGISTRATION_CLOSED', 'Pendaftaran sedang ditutup oleh panitia.');
  }
  var d = req.data || {};
  var token = String(d.submission_token || '');
  if (!isValidSubmissionToken_(token)) {
    throw new AppError('INVALID_TOKEN', 'Token pendaftaran tidak valid. Muat ulang halaman.');
  }
  var normalized = validateRegistrationPayload_(d.payload);
  var ST = APP_CONFIG.SUBMISSION_STATES;
  var SH = APP_CONFIG.SHEETS.SUBMISSION;

  return withScriptLock_(function () {
    var sub = findByField_(SH, 'submission_token', token);

    if (sub && sub.state === ST.COMPLETE) {
      return ok_('Pendaftaran ini sudah tercatat.', completeResponse_(sub, true));
    }
    if (sub && sub.state === ST.EXPIRED) {
      throw new AppError('SUBMISSION_EXPIRED', 'Sesi pendaftaran sudah kedaluwarsa. Silakan mulai pendaftaran baru.');
    }

    checkDuplicates_(normalized, sub ? sub.club_id : '');

    // Pertahankan pemain_id untuk ref yang sama agar retry idempotent
    var oldPayload = sub ? parseJson_(sub.payload_json, null) : null;
    var oldIds = {};
    if (oldPayload && oldPayload.players) {
      oldPayload.players.forEach(function (p) { oldIds[p.ref] = p.pemain_id; });
    }
    normalized.players.forEach(function (p) {
      p.pemain_id = oldIds[p.ref] || newId_('PMN');
    });

    var payloadJson = JSON.stringify(normalized);
    if (payloadJson.length > 45000) {
      throw new AppError('VALIDATION_ERROR', 'Data pendaftaran terlalu besar.');
    }

    var slots = requiredSlots_(normalized);
    var now = nowIso_();
    var files = {};

    if (!sub) {
      var folder = createSubmissionFolder_(token);
      sub = {
        submission_token: token,
        state: ST.UPLOADING,
        club_id: newId_('CLB'),
        nomor_pendaftaran: '',
        folder_id: folder.getId(),
        payload_json: payloadJson,
        files_json: '{}',
        error: '',
        created_at: now,
        updated_at: now
      };
      appendObjects_(SH, [sub]);
    } else {
      files = parseJson_(sub.files_json, {});
      // Buang file untuk pemain yang sudah dihapus dari form
      Object.keys(files).forEach(function (slot) {
        if (slots.all.indexOf(slot) === -1) {
          trashFileQuietly_(files[slot].file_id);
          delete files[slot];
        }
      });
      updateRowFields_(SH, sub._row, {
        state: ST.UPLOADING,
        payload_json: payloadJson,
        files_json: JSON.stringify(files),
        error: '',
        updated_at: now
      });
    }

    return ok_('Data pendaftaran valid. Silakan lanjutkan unggah dokumen.', {
      submission_token: token,
      state: ST.UPLOADING,
      required_slots: slots.required,
      optional_slots: slots.optional,
      uploaded_slots: Object.keys(files)
    });
  }, 25000);
}

// ---------------------------------------------------------------------------
// 2. upload_file
// ---------------------------------------------------------------------------

function uploadFile_(req) {
  var d = req.data || {};
  var token = String(d.submission_token || '');
  if (!isValidSubmissionToken_(token)) {
    throw new AppError('INVALID_TOKEN', 'Token pendaftaran tidak valid.');
  }
  var slot = String(d.slot || '');
  var slotInfo = parseSlot_(slot);
  if (!slotInfo) throw new AppError('INVALID_SLOT', 'Slot dokumen tidak valid.');

  var ST = APP_CONFIG.SUBMISSION_STATES;
  var SH = APP_CONFIG.SHEETS.SUBMISSION;
  var sub = findByField_(SH, 'submission_token', token);
  if (!sub) throw new AppError('NOT_FOUND', 'Sesi pendaftaran tidak ditemukan. Silakan kirim ulang data.');
  if (sub.state === ST.COMPLETE) throw new AppError('ALREADY_SUBMITTED', 'Pendaftaran ini sudah dikirim.');
  if (sub.state !== ST.UPLOADING && sub.state !== ST.FAILED) {
    throw new AppError('INVALID_STATE', 'Sesi pendaftaran tidak dapat menerima dokumen.');
  }

  var normalized = parseJson_(sub.payload_json, null);
  if (!normalized) throw new AppError('INVALID_STATE', 'Data sesi pendaftaran tidak ditemukan.');
  if (requiredSlots_(normalized).all.indexOf(slot) === -1) {
    throw new AppError('INVALID_SLOT', 'Dokumen tidak sesuai dengan data pemain.');
  }

  var validated = validateUploadedFile_(slotInfo.docType, d.file_name, d.mime_type, d.data_base64);
  var saved = saveFileToDrive_(sub, slotInfo, validated);

  var previous = '';
  try {
    withScriptLock_(function () {
      var fresh = findByField_(SH, 'submission_token', token);
      if (!fresh || fresh.state === ST.COMPLETE) {
        throw new AppError('ALREADY_SUBMITTED', 'Pendaftaran ini sudah dikirim.');
      }
      var files = parseJson_(fresh.files_json, {});
      previous = files[slot] ? files[slot].file_id : '';
      files[slot] = {
        file_id: saved.file_id,
        url: saved.url,
        size: saved.size,
        mime: saved.mime,
        ext: saved.ext,
        uploaded_at: nowIso_()
      };
      updateRowFields_(SH, fresh._row, { files_json: JSON.stringify(files), updated_at: nowIso_() });
    }, 20000);
  } catch (e) {
    trashFileQuietly_(saved.file_id);
    throw e;
  }
  if (previous && previous !== saved.file_id) trashFileQuietly_(previous);

  var pemainId = '';
  if (slotInfo.ref) {
    normalized.players.forEach(function (p) { if (p.ref === slotInfo.ref) pemainId = p.pemain_id; });
  }
  var activity = slotInfo.docType === 'akte' ? 'UPLOAD_AKTE' : (slotInfo.docType === 'foto' ? 'UPLOAD_FOTO' : 'UPLOAD_LOGO');
  writeLog_(activity, {
    clubId: sub.club_id,
    pemainId: pemainId,
    detail: 'type=' + saved.mime + ' size=' + saved.size + (previous ? ' replaced=1' : '')
  });

  return ok_('File berhasil disimpan.', { slot: slot, size: saved.size, replaced: !!previous });
}

// ---------------------------------------------------------------------------
// 3. submit_finalize
// ---------------------------------------------------------------------------

function organizeDriveFiles_(sub, normalized, files, nomor) {
  var folder;
  try {
    folder = DriveApp.getFolderById(sub.folder_id);
  } catch (e) {
    return; // penamaan bersifat kosmetik; keberadaan file sudah diverifikasi
  }
  renameQuietly_(folder, nomor + '_' + safeName_(normalized.club.nama_club, 50));
  normalized.players.forEach(function (p, i) {
    var no = padNumber_(i + 1, 2) + '_' + safeName_(p.nama_lengkap, 40);
    ['akte', 'foto'].forEach(function (type) {
      var f = files[type + ':' + p.ref];
      if (!f) return;
      try {
        renameQuietly_(DriveApp.getFileById(f.file_id),
          (type === 'akte' ? 'AKTE_' : 'FOTO_') + nomor + '_' + no + '.' + f.ext);
      } catch (ignore) { /* noop */ }
    });
  });
  if (files.logo) {
    try {
      renameQuietly_(DriveApp.getFileById(files.logo.file_id),
        'LOGO_' + nomor + '_' + safeName_(normalized.club.nama_club, 50) + '.' + files.logo.ext);
    } catch (ignore) { /* noop */ }
  }
}

function submitFinalize_(req) {
  var d = req.data || {};
  var token = String(d.submission_token || '');
  if (!isValidSubmissionToken_(token)) {
    throw new AppError('INVALID_TOKEN', 'Token pendaftaran tidak valid.');
  }
  var ST = APP_CONFIG.SUBMISSION_STATES;
  var SH = APP_CONFIG.SHEETS;

  return withScriptLock_(function () {
    var sub = findByField_(SH.SUBMISSION, 'submission_token', token);
    if (!sub) throw new AppError('NOT_FOUND', 'Sesi pendaftaran tidak ditemukan.');
    if (sub.state === ST.COMPLETE) {
      return ok_('Pendaftaran sudah tercatat sebelumnya.', completeResponse_(sub, true));
    }
    if (sub.state === ST.EXPIRED) {
      throw new AppError('SUBMISSION_EXPIRED', 'Sesi pendaftaran sudah kedaluwarsa. Silakan mulai pendaftaran baru.');
    }
    if (!isRegistrationOpen_()) {
      throw new AppError('REGISTRATION_CLOSED', 'Pendaftaran sedang ditutup oleh panitia.');
    }

    var normalized = parseJson_(sub.payload_json, null);
    if (!normalized) throw new AppError('INVALID_STATE', 'Data sesi pendaftaran tidak ditemukan.');
    var files = parseJson_(sub.files_json, {});
    var slots = requiredSlots_(normalized);

    // Semua dokumen wajib harus benar-benar ada di Drive
    var missing = slots.required.filter(function (s) {
      return !files[s] || !driveFileExists_(files[s].file_id);
    });
    if (missing.length) {
      throw new AppError('MISSING_FILES', 'Dokumen wajib belum lengkap.', {
        errors: missing.map(function (s) { return { field: s, message: slotLabel_(s, normalized) }; })
      });
    }
    if (files.logo && !driveFileExists_(files.logo.file_id)) delete files.logo;
    if (files.logo && !normalized.has_logo) {
      // Logo sempat diunggah lalu dihapus pengguna sebelum mengirim ulang
      trashFileQuietly_(files.logo.file_id);
      delete files.logo;
    }

    checkDuplicates_(normalized, sub.club_id);

    var nomor = sub.nomor_pendaftaran;
    if (!nomor) nomor = nextRegistrationNumber_();
    updateRowFields_(SH.SUBMISSION, sub._row, {
      nomor_pendaftaran: nomor,
      state: ST.FINALIZING,
      updated_at: nowIso_()
    });

    organizeDriveFiles_(sub, normalized, files, nomor);

    var now = nowIso_();
    var c = normalized.club;
    var clubRow = {
      club_id: sub.club_id,
      nomor_pendaftaran: nomor,
      kategori: normalized.kategori,
      nama_club: c.nama_club,
      nama_manager: c.nama_manager,
      nama_pelatih: c.nama_pelatih,
      whatsapp: c.whatsapp,
      email: c.email,
      alamat: c.alamat,
      kampung: c.kampung,
      distrik: c.distrik,
      kabupaten: c.kabupaten,
      provinsi: c.provinsi,
      logo_file_id: files.logo ? files.logo.file_id : '',
      logo_url: files.logo ? files.logo.url : '',
      jumlah_pemain: normalized.players.length,
      status: APP_CONFIG.DEFAULT_STATUS,
      catatan_admin: '',
      created_at: now,
      updated_at: now
    };
    var playerRows = buildPlayerRows_(normalized, sub.club_id, nomor, files, now);

    try {
      // Bersihkan sisa percobaan finalisasi sebelumnya (jika ada)
      deleteRowsByField_(SH.PEMAIN, 'club_id', sub.club_id);
      deleteRowsByField_(SH.CLUB, 'club_id', sub.club_id);

      appendObjects_(SH.PEMAIN, playerRows);
      appendObjects_(SH.CLUB, [clubRow]);
      SpreadsheetApp.flush();

      var savedPlayers = readTable_(SH.PEMAIN).filter(function (p) { return p.club_id === sub.club_id; });
      var savedClub = findByField_(SH.CLUB, 'club_id', sub.club_id);
      if (!savedClub || savedPlayers.length !== playerRows.length) {
        throw new AppError('SHEETS_ERROR', 'Data gagal diverifikasi setelah disimpan. Silakan kirim ulang.');
      }
    } catch (e) {
      try {
        deleteRowsByField_(SH.PEMAIN, 'club_id', sub.club_id);
        deleteRowsByField_(SH.CLUB, 'club_id', sub.club_id);
      } catch (ignore) { /* noop */ }
      var code = e instanceof AppError ? e.code : 'SHEETS_ERROR';
      try {
        updateRowFields_(SH.SUBMISSION, sub._row, { state: ST.FAILED, error: code, updated_at: nowIso_() });
      } catch (ignore) { /* noop */ }
      writeLog_('SUBMIT_FAILED', { clubId: sub.club_id, status: 'FAILED', detail: 'code=' + code });
      if (e instanceof AppError) throw e;
      throw new AppError('SHEETS_ERROR', 'Data gagal disimpan. Silakan kirim ulang.');
    }

    // Data personal tidak perlu disimpan dua kali setelah tersimpan di CLUB/PEMAIN
    updateRowFields_(SH.SUBMISSION, sub._row, {
      state: ST.COMPLETE,
      payload_json: '',
      error: '',
      updated_at: nowIso_()
    });

    var logs = [buildLog_('CREATE_CLUB', { clubId: sub.club_id, detail: nomor + ' ' + normalized.kategori })];
    playerRows.forEach(function (p) {
      logs.push(buildLog_('ADD_PLAYER', { clubId: sub.club_id, pemainId: p.pemain_id }));
    });
    logs.push(buildLog_('SUBMIT', { clubId: sub.club_id, detail: nomor + ' pemain=' + playerRows.length }));
    writeLogs_(logs);

    return ok_('Pendaftaran berhasil', {
      nomor_pendaftaran: nomor,
      nama_club: c.nama_club,
      kategori: normalized.kategori,
      status: APP_CONFIG.DEFAULT_STATUS,
      jumlah_pemain: playerRows.length,
      already_submitted: false,
      state: ST.COMPLETE
    });
  }, 30000);
}

// ---------------------------------------------------------------------------
// Publik: cek status
// ---------------------------------------------------------------------------

function checkStatus_(req) {
  var nomor = String((req.data || {}).nomor_pendaftaran || '').trim().toUpperCase();
  if (!APP_CONFIG.REG_PATTERN.test(nomor)) {
    throw new AppError('VALIDATION_ERROR', 'Format nomor pendaftaran tidak valid. Contoh: GMB-SB-2026-0001');
  }
  var club = findByField_(APP_CONFIG.SHEETS.CLUB, 'nomor_pendaftaran', nomor);
  if (!club) throw new AppError('NOT_FOUND', 'Nomor pendaftaran tidak ditemukan.');
  // HANYA data non-sensitif
  return ok_('Data ditemukan', {
    nomor_pendaftaran: club.nomor_pendaftaran,
    nama_club: club.nama_club,
    kategori: club.kategori,
    status: club.status
  });
}

function publicConfig_() {
  var rules = {};
  APP_CONFIG.CATEGORIES.forEach(function (cat) { rules[cat] = getAgeRules_(cat); });
  var limits = getPlayerLimits_();
  return ok_('OK', {
    registration_open: isRegistrationOpen_(),
    categories: APP_CONFIG.CATEGORIES,
    age_rules: rules,
    players: { min: limits.min, max: limits.max, configured: limits.configured },
    max_file_bytes: APP_CONFIG.FILES.MAX_BYTES,
    version: APP_CONFIG.VERSION
  });
}

// ---------------------------------------------------------------------------
// Admin: club
// ---------------------------------------------------------------------------

function clubSummary_(c) {
  return {
    club_id: c.club_id,
    nomor_pendaftaran: c.nomor_pendaftaran,
    kategori: c.kategori,
    nama_club: c.nama_club,
    nama_manager: c.nama_manager,
    nama_pelatih: c.nama_pelatih,
    whatsapp: c.whatsapp,
    email: c.email,
    distrik: c.distrik,
    kabupaten: c.kabupaten,
    jumlah_pemain: parseInt(c.jumlah_pemain || '0', 10),
    status: c.status,
    has_logo: !!c.logo_file_id,
    created_at: c.created_at,
    updated_at: c.updated_at
  };
}

function adminStats_(req, ctx) {
  requirePermission_(ctx, 'view');
  var clubs = readTable_(APP_CONFIG.SHEETS.CLUB);
  var players = readTable_(APP_CONFIG.SHEETS.PEMAIN);
  var byStatus = {};
  APP_CONFIG.STATUSES.forEach(function (s) { byStatus[s] = 0; });
  var byCategory = { U10: { clubs: 0, players: 0 }, U12: { clubs: 0, players: 0 } };
  clubs.forEach(function (c) {
    if (byStatus[c.status] !== undefined) byStatus[c.status]++;
    if (byCategory[c.kategori]) byCategory[c.kategori].clubs++;
  });
  var playerStatus = {};
  APP_CONFIG.PLAYER_STATUSES.forEach(function (s) { playerStatus[s] = 0; });
  players.forEach(function (p) {
    if (byCategory[p.kategori]) byCategory[p.kategori].players++;
    if (playerStatus[p.status_verifikasi] !== undefined) playerStatus[p.status_verifikasi]++;
  });
  var latest = clubs.slice(-5).reverse().map(clubSummary_);
  var pendingSubs = readTable_(APP_CONFIG.SHEETS.SUBMISSION).filter(function (s) {
    return s.state === 'UPLOADING' || s.state === 'FAILED' || s.state === 'FINALIZING';
  }).length;
  return ok_('OK', {
    total_club: clubs.length,
    total_pemain: players.length,
    status: byStatus,
    player_status: playerStatus,
    kategori: byCategory,
    latest: latest,
    submission_in_progress: pendingSubs
  });
}

function adminListClubs_(req, ctx) {
  requirePermission_(ctx, 'view');
  var d = req.data || {};
  var q = cleanText_(d.q, 80).toLowerCase();
  var kategori = String(d.kategori || '');
  var status = String(d.status || '');

  var clubs = readTable_(APP_CONFIG.SHEETS.CLUB);
  var matchByPlayer = {};
  if (q) {
    readTable_(APP_CONFIG.SHEETS.PEMAIN).forEach(function (p) {
      if (p.nama_lengkap.toLowerCase().indexOf(q) !== -1) matchByPlayer[p.club_id] = true;
    });
  }
  var list = clubs.filter(function (c) {
    if (kategori && c.kategori !== kategori) return false;
    if (status && c.status !== status) return false;
    if (q) {
      var hay = (c.nomor_pendaftaran + ' ' + c.nama_club).toLowerCase();
      if (hay.indexOf(q) === -1 && !matchByPlayer[c.club_id]) return false;
    }
    return true;
  }).reverse();

  var page = paginate_(list, d.page, d.per_page);
  page.items = page.items.map(clubSummary_);
  return ok_('OK', page);
}

function adminGetClub_(req, ctx) {
  requirePermission_(ctx, 'view');
  var id = String((req.data || {}).club_id || '');
  var club = findByField_(APP_CONFIG.SHEETS.CLUB, 'club_id', id);
  if (!club) throw new AppError('NOT_FOUND', 'Club tidak ditemukan.');
  var sensitive = adminCan_(ctx, 'view_sensitive');
  var players = readTable_(APP_CONFIG.SHEETS.PEMAIN)
    .filter(function (p) { return p.club_id === id; })
    .map(function (p) { return playerDetail_(p, sensitive); });

  var detail = clubSummary_(club);
  detail.alamat = club.alamat;
  detail.kampung = club.kampung;
  detail.provinsi = club.provinsi;
  detail.catatan_admin = club.catatan_admin;

  writeLog_('ADMIN_VIEW', { clubId: id, actor: ctx.username, detail: 'club_detail' });
  return ok_('OK', {
    club: detail,
    players: players,
    can_view_sensitive: sensitive,
    can_view_documents: adminCan_(ctx, 'view_documents')
  });
}

function appendNote_(existing, note, actor) {
  var stamp = Utilities.formatDate(new Date(), 'Asia/Jayapura', 'yyyy-MM-dd HH:mm');
  var line = '[' + stamp + ' ' + actor + '] ' + note;
  var combined = existing ? existing + '\n' + line : line;
  if (combined.length > 5000) combined = combined.substring(combined.length - 5000);
  return combined;
}

function locateTarget_(target, id) {
  var SH = APP_CONFIG.SHEETS;
  if (target === 'club') {
    return { sheet: SH.CLUB, row: findByField_(SH.CLUB, 'club_id', id), statusField: 'status' };
  }
  if (target === 'player') {
    return { sheet: SH.PEMAIN, row: findByField_(SH.PEMAIN, 'pemain_id', id), statusField: 'status_verifikasi' };
  }
  throw new AppError('VALIDATION_ERROR', 'Target tidak valid.');
}

function adminUpdateStatus_(req, ctx) {
  requirePermission_(ctx, 'update_status');
  var d = req.data || {};
  var target = String(d.target || '');
  var id = String(d.id || '');
  var status = String(d.status || '').toUpperCase();
  var note = cleanMultiline_(d.note, 500);
  var allowed = target === 'player' ? APP_CONFIG.PLAYER_STATUSES : APP_CONFIG.STATUSES;
  if (allowed.indexOf(status) === -1) throw new AppError('VALIDATION_ERROR', 'Status tidak valid.');
  if ((status === 'REVISION' || status === 'REJECTED') && !note) {
    throw new AppError('VALIDATION_ERROR', 'Catatan wajib diisi untuk status REVISION atau REJECTED.');
  }

  return withScriptLock_(function () {
    var t = locateTarget_(target, id);
    if (!t.row) throw new AppError('NOT_FOUND', 'Data tidak ditemukan.');
    var before = t.row[t.statusField];
    var fields = { updated_at: nowIso_() };
    fields[t.statusField] = status;
    if (note) fields.catatan_admin = appendNote_(t.row.catatan_admin, status + ': ' + note, ctx.username);
    updateRowFields_(t.sheet, t.row._row, fields);

    var logs = [buildLog_('ADMIN_UPDATE_STATUS', {
      clubId: target === 'club' ? id : t.row.club_id,
      pemainId: target === 'player' ? id : '',
      actor: ctx.username,
      detail: target + ' ' + before + ' -> ' + status
    })];
    if (note) {
      logs.push(buildLog_('ADMIN_ADD_NOTE', {
        clubId: target === 'club' ? id : t.row.club_id,
        pemainId: target === 'player' ? id : '',
        actor: ctx.username,
        detail: target
      }));
    }
    writeLogs_(logs);
    return ok_('Status berhasil diperbarui.', { target: target, id: id, status: status, previous: before });
  }, 15000);
}

function adminAddNote_(req, ctx) {
  requirePermission_(ctx, 'add_note');
  var d = req.data || {};
  var target = String(d.target || '');
  var id = String(d.id || '');
  var note = cleanMultiline_(d.note, 500);
  if (!note) throw new AppError('VALIDATION_ERROR', 'Catatan tidak boleh kosong.');
  return withScriptLock_(function () {
    var t = locateTarget_(target, id);
    if (!t.row) throw new AppError('NOT_FOUND', 'Data tidak ditemukan.');
    var combined = appendNote_(t.row.catatan_admin, note, ctx.username);
    updateRowFields_(t.sheet, t.row._row, { catatan_admin: combined, updated_at: nowIso_() });
    writeLog_('ADMIN_ADD_NOTE', {
      clubId: target === 'club' ? id : t.row.club_id,
      pemainId: target === 'player' ? id : '',
      actor: ctx.username,
      detail: target
    });
    return ok_('Catatan tersimpan.', { catatan_admin: combined });
  }, 15000);
}

function adminGetFile_(req, ctx) {
  requirePermission_(ctx, 'view_documents');
  var d = req.data || {};
  var target = String(d.target || '');
  var id = String(d.id || '');
  var type = String(d.type || '');
  var fileId = '';
  var clubId = '';
  var pemainId = '';

  if (target === 'player' && (type === 'akte' || type === 'foto')) {
    var p = findByField_(APP_CONFIG.SHEETS.PEMAIN, 'pemain_id', id);
    if (!p) throw new AppError('NOT_FOUND', 'Pemain tidak ditemukan.');
    fileId = type === 'akte' ? p.akte_file_id : p.foto_file_id;
    clubId = p.club_id;
    pemainId = p.pemain_id;
  } else if (target === 'club' && type === 'logo') {
    var c = findByField_(APP_CONFIG.SHEETS.CLUB, 'club_id', id);
    if (!c) throw new AppError('NOT_FOUND', 'Club tidak ditemukan.');
    fileId = c.logo_file_id;
    clubId = c.club_id;
  } else {
    throw new AppError('VALIDATION_ERROR', 'Permintaan dokumen tidak valid.');
  }
  if (!fileId) throw new AppError('NOT_FOUND', 'Dokumen tidak tersedia.');

  var file = readFileForAdmin_(fileId);
  // Logo tidak perlu dicatat setiap kali ditampilkan; dokumen pribadi selalu dicatat.
  if (type !== 'logo') {
    writeLog_('ADMIN_VIEW', { clubId: clubId, pemainId: pemainId, actor: ctx.username, detail: 'doc=' + type });
  }
  return ok_('OK', file);
}

function adminExport_(req, ctx) {
  requirePermission_(ctx, 'export');
  var d = req.data || {};
  var dataset = String(d.dataset || '');
  var sensitive = d.include_sensitive === true;
  if (sensitive) requirePermission_(ctx, 'export_sensitive');
  var kategori = String(d.kategori || '');
  var status = String(d.status || '');

  var clubs = readTable_(APP_CONFIG.SHEETS.CLUB);
  var clubMap = {};
  clubs.forEach(function (c) { clubMap[c.club_id] = c; });
  var columns, rows;

  if (dataset === 'clubs') {
    columns = ['nomor_pendaftaran', 'kategori', 'nama_club', 'nama_manager', 'nama_pelatih', 'whatsapp',
      'email', 'alamat', 'kampung', 'distrik', 'kabupaten', 'provinsi', 'jumlah_pemain', 'status',
      'catatan_admin', 'created_at', 'updated_at'];
    rows = clubs.filter(function (c) {
      return (!kategori || c.kategori === kategori) && (!status || c.status === status);
    }).map(function (c) {
      return columns.map(function (col) { return c[col]; });
    });
  } else if (dataset === 'players') {
    columns = ['nomor_pendaftaran', 'kategori', 'nama_club', 'nama_lengkap'];
    if (sensitive) columns.push('nik');
    columns = columns.concat(['tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'posisi', 'nomor_punggung']);
    if (sensitive) columns = columns.concat(['nama_ayah', 'nama_ibu', 'nama_wali', 'whatsapp_wali', 'alamat']);
    columns = columns.concat(['akte_tersedia', 'foto_tersedia', 'status_verifikasi', 'catatan_admin', 'created_at']);
    rows = readTable_(APP_CONFIG.SHEETS.PEMAIN).filter(function (p) {
      return (!kategori || p.kategori === kategori) && (!status || p.status_verifikasi === status);
    }).map(function (p) {
      return columns.map(function (col) {
        if (col === 'nama_club') return clubMap[p.club_id] ? clubMap[p.club_id].nama_club : '';
        if (col === 'akte_tersedia') return p.akte_file_id ? 'YA' : 'TIDAK';
        if (col === 'foto_tersedia') return p.foto_file_id ? 'YA' : 'TIDAK';
        return p[col];
      });
    });
  } else {
    throw new AppError('VALIDATION_ERROR', 'Dataset tidak valid.');
  }

  writeLog_('ADMIN_EXPORT', {
    actor: ctx.username,
    detail: dataset + ' rows=' + rows.length + ' sensitive=' + (sensitive ? 1 : 0)
  });
  return ok_('OK', { dataset: dataset, columns: columns, rows: rows, sensitive: sensitive });
}

// ---------------------------------------------------------------------------
// Admin: pengaturan & event auth
// ---------------------------------------------------------------------------

function adminGetSettings_(req, ctx) {
  requirePermission_(ctx, 'view');
  var values = {};
  Object.keys(APP_CONFIG.EDITABLE_SETTINGS).forEach(function (k) { values[k] = getSetting_(k); });
  var health = { spreadsheet: false, drive: false };
  try { getSpreadsheet_(); health.spreadsheet = true; } catch (e) { /* noop */ }
  try { getRootFolder_(); health.drive = true; } catch (e) { /* noop */ }
  var rules = {};
  APP_CONFIG.CATEGORIES.forEach(function (cat) { rules[cat] = getAgeRules_(cat); });
  return ok_('OK', {
    settings: values,
    types: APP_CONFIG.EDITABLE_SETTINGS,
    age_rules: rules,
    players: getPlayerLimits_(),
    registration_open: isRegistrationOpen_(),
    environment: getEnvironment_(),
    version: APP_CONFIG.VERSION,
    health: health,
    storage: (function () {
      var s = getStorageAccountStatus_();
      return {
        owner_email: s.expected,
        running_as: s.actual,
        verified: s.verified,
        mismatch: s.mismatch,
        drive_url: APP_CONFIG.DRIVE_HOME_URL,
        spreadsheet_name: APP_CONFIG.SPREADSHEET_NAME,
        root_folder_name: APP_CONFIG.ROOT_FOLDER_NAME
      };
    })(),
    can_edit: adminCan_(ctx, 'settings')
  });
}

function adminUpdateSettings_(req, ctx) {
  requirePermission_(ctx, 'settings');
  var input = (req.data || {}).settings || {};
  var types = APP_CONFIG.EDITABLE_SETTINGS;
  var updates = {};
  var errors = [];
  Object.keys(input).forEach(function (key) {
    if (!types[key]) { errors.push({ field: key, message: 'Pengaturan tidak dikenal.' }); return; }
    var v = String(input[key] === null || input[key] === undefined ? '' : input[key]).trim();
    if (v === '') { updates[key] = ''; return; }
    if (types[key] === 'bool') {
      if (v !== 'true' && v !== 'false') errors.push({ field: key, message: 'Nilai harus true/false.' });
      else updates[key] = v;
    } else if (types[key] === 'int') {
      if (!/^\d{1,3}$/.test(v)) errors.push({ field: key, message: 'Nilai harus angka 0–999.' });
      else updates[key] = String(parseInt(v, 10));
    } else if (types[key] === 'date') {
      if (!isValidIsoDate_(v)) errors.push({ field: key, message: 'Format tanggal harus YYYY-MM-DD.' });
      else updates[key] = v;
    }
  });
  if (errors.length) throw new AppError('VALIDATION_ERROR', 'Pengaturan tidak valid.', { errors: errors });

  // Validasi silang min <= max
  function effective(k) { return updates.hasOwnProperty(k) ? updates[k] : getSetting_(k); }
  ['U10', 'U12'].forEach(function (cat) {
    var mn = effective('MIN_AGE_' + cat), mx = effective('MAX_AGE_' + cat);
    if (mn !== '' && mx !== '' && parseInt(mn, 10) > parseInt(mx, 10)) {
      errors.push({ field: 'MIN_AGE_' + cat, message: 'Usia minimum ' + cat + ' melebihi maksimum.' });
    }
  });
  var pmn = effective('MIN_PLAYERS'), pmx = effective('MAX_PLAYERS');
  if (pmx !== '' && parseInt(pmx, 10) > APP_CONFIG.TECHNICAL_MAX_PLAYERS) {
    errors.push({ field: 'MAX_PLAYERS', message: 'Maksimal pemain tidak boleh melebihi ' + APP_CONFIG.TECHNICAL_MAX_PLAYERS + '.' });
  }
  if (pmn !== '' && pmx !== '' && parseInt(pmn, 10) > parseInt(pmx, 10)) {
    errors.push({ field: 'MIN_PLAYERS', message: 'Minimal pemain melebihi maksimal pemain.' });
  }
  if (errors.length) throw new AppError('VALIDATION_ERROR', 'Pengaturan tidak valid.', { errors: errors });

  var props = getProps_();
  Object.keys(updates).forEach(function (k) {
    if (updates[k] === '') props.deleteProperty(k);
    else props.setProperty(k, updates[k]);
  });
  writeLog_('ADMIN_UPDATE_SETTING', {
    actor: ctx.username,
    detail: Object.keys(updates).map(function (k) { return k + '=' + (updates[k] || '(kosong)'); }).join(', ')
  });
  return adminGetSettings_(req, ctx);
}

function adminAuthEvent_(req) {
  var d = req.data || {};
  var activity = String(d.aktivitas || '');
  if (['ADMIN_LOGIN', 'ADMIN_LOGIN_FAILED', 'ADMIN_LOGOUT'].indexOf(activity) === -1) {
    throw new AppError('VALIDATION_ERROR', 'Aktivitas tidak valid.');
  }
  writeLog_(activity, {
    actor: cleanText_(d.username, 60) || 'UNKNOWN',
    status: activity === 'ADMIN_LOGIN_FAILED' ? 'FAILED' : 'OK',
    detail: cleanText_(d.detail, 120)
  });
  return ok_('OK', {});
}

// ---------------------------------------------------------------------------
// Pemeliharaan: submission yang terbengkalai
// ---------------------------------------------------------------------------

/**
 * Jalankan via time-driven trigger (mis. setiap 6 jam).
 * Menandai submission yang tidak selesai > SUBMISSION_EXPIRE_HOURS sebagai EXPIRED
 * dan memindahkan file sementaranya ke trash Drive.
 */
function cleanupStaleSubmissions() {
  var ST = APP_CONFIG.SUBMISSION_STATES;
  var SH = APP_CONFIG.SHEETS.SUBMISSION;
  var limit = Date.now() - APP_CONFIG.SUBMISSION_EXPIRE_HOURS * 3600 * 1000;
  return withScriptLock_(function () {
    var count = 0;
    readTable_(SH).forEach(function (s) {
      if ([ST.UPLOADING, ST.FAILED, ST.FINALIZING].indexOf(s.state) === -1) return;
      var t = Date.parse(s.updated_at);
      if (isNaN(t) || t > limit) return;
      // Finalisasi yang datanya sudah tersimpan utuh (hanya gagal update state) -> selesaikan.
      var club = findByField_(APP_CONFIG.SHEETS.CLUB, 'club_id', s.club_id);
      if (club && s.nomor_pendaftaran && club.nomor_pendaftaran === s.nomor_pendaftaran) {
        var saved = readTable_(APP_CONFIG.SHEETS.PEMAIN).filter(function (p) { return p.club_id === s.club_id; });
        if (saved.length === parseInt(club.jumlah_pemain, 10)) {
          updateRowFields_(SH, s._row, { state: ST.COMPLETE, payload_json: '', error: '', updated_at: nowIso_() });
          return;
        }
      }
      var files = parseJson_(s.files_json, {});
      Object.keys(files).forEach(function (k) { trashFileQuietly_(files[k].file_id); });
      try { DriveApp.getFolderById(s.folder_id).setTrashed(true); } catch (ignore) { /* noop */ }
      deleteRowsByField_(APP_CONFIG.SHEETS.PEMAIN, 'club_id', s.club_id);
      deleteRowsByField_(APP_CONFIG.SHEETS.CLUB, 'club_id', s.club_id);
      updateRowFields_(SH, s._row, {
        state: ST.EXPIRED, payload_json: '', files_json: '{}', error: 'EXPIRED', updated_at: nowIso_()
      });
      writeLog_('SUBMISSION_EXPIRED', { clubId: s.club_id, actor: 'SYSTEM' });
      count++;
    });
    return count;
  }, 30000);
}
