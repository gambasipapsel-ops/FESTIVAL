/**
 * PlayerService.gs
 * Data pemain: pembentukan baris sheet dan fitur admin untuk pemain.
 */

var POSITION_LABELS_ = { KIPER: 'Kiper', BELAKANG: 'Belakang', TENGAH: 'Tengah', DEPAN: 'Depan' };

function buildPlayerRows_(normalized, clubId, nomor, files, now) {
  return normalized.players.map(function (p) {
    var akte = files['akte:' + p.ref];
    var foto = files['foto:' + p.ref];
    return {
      pemain_id: p.pemain_id,
      club_id: clubId,
      nomor_pendaftaran: nomor,
      kategori: normalized.kategori,
      nama_lengkap: p.nama_lengkap,
      nik: p.nik,
      tempat_lahir: p.tempat_lahir,
      tanggal_lahir: p.tanggal_lahir,
      jenis_kelamin: p.jenis_kelamin,
      posisi: p.posisi,
      nomor_punggung: p.nomor_punggung,
      nama_ayah: p.nama_ayah,
      nama_ibu: p.nama_ibu,
      nama_wali: p.nama_wali,
      whatsapp_wali: p.whatsapp_wali,
      alamat: p.alamat,
      akte_file_id: akte.file_id,
      akte_url: akte.url,
      foto_file_id: foto.file_id,
      foto_url: foto.url,
      status_verifikasi: APP_CONFIG.DEFAULT_STATUS,
      catatan_admin: '',
      created_at: now,
      updated_at: now
    };
  });
}

/**
 * Detail pemain untuk admin. File ID / URL Drive TIDAK pernah dikirim;
 * dokumen diakses lewat admin_get_file berdasarkan pemain_id.
 */
function playerDetail_(p, sensitive) {
  var out = {
    pemain_id: p.pemain_id,
    club_id: p.club_id,
    nomor_pendaftaran: p.nomor_pendaftaran,
    kategori: p.kategori,
    nama_lengkap: p.nama_lengkap,
    nik: sensitive ? p.nik : maskNik_(p.nik),
    nik_masked: !sensitive,
    tempat_lahir: p.tempat_lahir,
    tanggal_lahir: p.tanggal_lahir,
    jenis_kelamin: p.jenis_kelamin,
    posisi: p.posisi,
    posisi_label: POSITION_LABELS_[p.posisi] || p.posisi,
    nomor_punggung: p.nomor_punggung,
    has_akte: !!p.akte_file_id,
    has_foto: !!p.foto_file_id,
    status_verifikasi: p.status_verifikasi,
    catatan_admin: p.catatan_admin,
    created_at: p.created_at,
    updated_at: p.updated_at
  };
  if (sensitive) {
    out.nama_ayah = p.nama_ayah;
    out.nama_ibu = p.nama_ibu;
    out.nama_wali = p.nama_wali;
    out.whatsapp_wali = p.whatsapp_wali;
    out.alamat = p.alamat;
  }
  return out;
}

function adminListPlayers_(req, ctx) {
  requirePermission_(ctx, 'view');
  var d = req.data || {};
  var q = cleanText_(d.q, 80).toLowerCase();
  var kategori = String(d.kategori || '');
  var status = String(d.status || '');
  var clubId = String(d.club_id || '');
  var docs = String(d.docs || ''); // '' | 'complete' | 'missing'
  var sensitive = adminCan_(ctx, 'view_sensitive');

  var clubMap = {};
  readTable_(APP_CONFIG.SHEETS.CLUB).forEach(function (c) { clubMap[c.club_id] = c; });

  var list = readTable_(APP_CONFIG.SHEETS.PEMAIN).filter(function (p) {
    if (clubId && p.club_id !== clubId) return false;
    if (kategori && p.kategori !== kategori) return false;
    if (status && p.status_verifikasi !== status) return false;
    var complete = !!p.akte_file_id && !!p.foto_file_id;
    if (docs === 'complete' && !complete) return false;
    if (docs === 'missing' && complete) return false;
    if (q) {
      var club = clubMap[p.club_id];
      var hay = (p.nama_lengkap + ' ' + p.nomor_pendaftaran + ' ' + (club ? club.nama_club : '')).toLowerCase();
      if (hay.indexOf(q) === -1) return false;
    }
    return true;
  }).reverse();

  var page = paginate_(list, d.page, d.per_page);
  page.items = page.items.map(function (p) {
    var detail = playerDetail_(p, sensitive);
    detail.nama_club = clubMap[p.club_id] ? clubMap[p.club_id].nama_club : '';
    detail.club_status = clubMap[p.club_id] ? clubMap[p.club_id].status : '';
    return detail;
  });
  page.can_view_sensitive = sensitive;
  page.can_view_documents = adminCan_(ctx, 'view_documents');
  return ok_('OK', page);
}

function adminGetPlayer_(req, ctx) {
  requirePermission_(ctx, 'view');
  var id = String((req.data || {}).pemain_id || '');
  var p = findByField_(APP_CONFIG.SHEETS.PEMAIN, 'pemain_id', id);
  if (!p) throw new AppError('NOT_FOUND', 'Pemain tidak ditemukan.');
  var sensitive = adminCan_(ctx, 'view_sensitive');
  var club = findByField_(APP_CONFIG.SHEETS.CLUB, 'club_id', p.club_id);
  var detail = playerDetail_(p, sensitive);
  detail.nama_club = club ? club.nama_club : '';
  writeLog_('ADMIN_VIEW', { clubId: p.club_id, pemainId: p.pemain_id, actor: ctx.username, detail: 'player_detail' });
  return ok_('OK', {
    player: detail,
    can_view_sensitive: sensitive,
    can_view_documents: adminCan_(ctx, 'view_documents')
  });
}
