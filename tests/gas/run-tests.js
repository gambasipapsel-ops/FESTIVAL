/**
 * DEV-ONLY: unit/integration test untuk backend Apps Script (kode .gs asli + fake Google services).
 * Jalankan: node tests/gas/run-tests.js
 */
'use strict';

const path = require('path');
const { loadGas } = require('./fake-google');

const GAS_DIR = path.join(__dirname, '..', '..', 'google-apps-script');
const SECRET = 'test-secret-0123456789abcdef0123456789abcdef';

// Nilai aturan usia KHUSUS PENGUJIAN (bukan regulasi resmi).
const TEST_AGE = {
  MIN_AGE_U10: '6', MAX_AGE_U10: '10', MIN_AGE_U12: '9', MAX_AGE_U12: '12',
  AGE_REFERENCE_DATE: '2026-10-30'
};

let passed = 0;
let failed = 0;
const results = [];

function test(name, fn) {
  try {
    fn();
    passed++;
    results.push(['PASS', name]);
  } catch (e) {
    failed++;
    results.push(['FAIL', name + ' :: ' + e.message]);
  }
}
function assert(cond, msg) { if (!cond) throw new Error(msg || 'assertion failed'); }
function eq(a, b, msg) { if (a !== b) throw new Error((msg || 'not equal') + ` (got ${JSON.stringify(a)}, want ${JSON.stringify(b)})`); }

// ---- sample files (magic bytes valid) ----
const PDF = Buffer.from('%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n').toString('base64');
const JPG = Buffer.concat([Buffer.from([0xff, 0xd8, 0xff, 0xe0, 0, 16]), Buffer.from('JFIF test'), Buffer.from([0xff, 0xd9])]).toString('base64');
const PNG = Buffer.concat([Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]), Buffer.alloc(20, 1)]).toString('base64');
const EXE = Buffer.from('MZ\x90\x00 this is not an image').toString('base64');
const BIG = Buffer.concat([Buffer.from([0xff, 0xd8, 0xff]), Buffer.alloc(5 * 1024 * 1024 + 10, 0)]).toString('base64');

let tokenSeq = 0;
function newToken() {
  tokenSeq++;
  return ('tok' + tokenSeq.toString().padStart(4, '0') + 'abcdef0123456789abcdef0123456789').slice(0, 36);
}

function player(i, overrides) {
  return Object.assign({
    ref: 'p' + String(i).padStart(6, '0'),
    nama_lengkap: 'Pemain Uji ' + i,
    nik: '9101' + String(100000000000 + i).slice(-12),
    tempat_lahir: 'Merauke',
    tanggal_lahir: '2017-05-10',
    jenis_kelamin: 'L',
    posisi: 'TENGAH',
    nomor_punggung: String(((i - 1) % 99) + 1),
    nama_ayah: 'Ayah ' + i,
    nama_ibu: 'Ibu ' + i,
    nama_wali: '',
    whatsapp_wali: '0812345678' + (10 + i),
    alamat: 'Jl. Uji Coba No. ' + i
  }, overrides || {});
}

function payload(overrides, players) {
  return Object.assign({
    kategori: 'U10',
    club: {
      nama_club: 'SSB Uji ' + tokenSeq, alamat: 'Jl. Raya Mandala', kampung: 'Maro',
      distrik: 'Merauke', kabupaten: 'Merauke', provinsi: 'Papua Selatan', has_logo: false
    },
    official: { nama_manager: 'Manager Uji', nama_pelatih: 'Pelatih Uji', whatsapp: '081234567890', email: 'club@example.com' },
    players: players || [player(1), player(2)]
  }, overrides || {});
}

function env() {
  const g = loadGas(GAS_DIR);
  g.fakes.props.set('API_SECRET', SECRET);
  g.ctx.setupProject();
  Object.entries(TEST_AGE).forEach(([k, v]) => g.fakes.props.set(k, v));
  const api = (action, data, admin) => g.call({ action, secret: SECRET, data, admin });
  return Object.assign(g, { api });
}

function uploadAll(api, token, p, files) {
  for (const pl of p.players) {
    const a = api('upload_file', { submission_token: token, slot: 'akte:' + pl.ref, file_name: 'akte.pdf', mime_type: 'application/pdf', data_base64: (files && files.akte) || PDF });
    assert(a.success, 'upload akte: ' + a.message);
    const f = api('upload_file', { submission_token: token, slot: 'foto:' + pl.ref, file_name: 'foto.jpg', mime_type: 'image/jpeg', data_base64: JPG });
    assert(f.success, 'upload foto: ' + f.message);
  }
}

function register(e, p) {
  const token = newToken();
  const init = e.api('submit_init', { submission_token: token, payload: p });
  assert(init.success, 'init: ' + init.message + JSON.stringify(init.errors || ''));
  uploadAll(e.api, token, p);
  const fin = e.api('submit_finalize', { submission_token: token });
  return { token, init, fin };
}

const ADMIN = { username: 'superadmin', role: 'SUPERADMIN' };
const VERIF = { username: 'verif1', role: 'VERIFIKATOR' };
const VIEWER = { username: 'viewer1', role: 'VIEWER' };

// =========================================================================
const e = env();

test('setupProject membuat sheet & folder', () => {
  const ss = [...e.fakes.spreadsheets.values()][0];
  eq(ss.name, 'GAMBASI_DATABASE_2026');
  ['CLUB', 'PEMAIN', 'LOG', 'SUBMISSION'].forEach((n) => assert(ss.getSheetByName(n), 'sheet ' + n));
  const names = [...e.fakes.folders.values()].map((f) => f.name);
  assert(names.includes('GAMBASI PAPUA SELATAN 2026') && names.includes('CLUB') && names.includes('LOGO_CLUB'));
});

test('setupProject idempotent (tidak membuat folder duplikat)', () => {
  const before = e.fakes.folders.size;
  e.ctx.setupProject();
  eq(e.fakes.folders.size, before);
});

test('secret salah ditolak', () => {
  const r = e.call({ action: 'public_config', secret: 'wrong', data: {} });
  eq(r.success, false); eq(r.error_code, 'UNAUTHORIZED');
});

test('JSON rusak ditolak', () => {
  const r = e.call('{not json');
  eq(r.error_code, 'BAD_REQUEST');
});

test('aksi tidak dikenal ditolak', () => {
  eq(e.api('drop_table', {}).error_code, 'UNKNOWN_ACTION');
});

test('public_config mengembalikan aturan tanpa ID rahasia', () => {
  const r = e.api('public_config', {});
  assert(r.success);
  eq(r.data.age_rules.U10.max, 10);
  const s = JSON.stringify(r);
  assert(!s.includes(e.fakes.props.get('SPREADSHEET_ID')) && !s.includes(SECRET), 'bocor ID/secret');
});

let firstReg;
test('Pendaftaran U-10 lengkap berhasil + nomor GMB-SB-2026-0001', () => {
  firstReg = register(e, payload());
  assert(firstReg.fin.success, firstReg.fin.message);
  eq(firstReg.fin.data.nomor_pendaftaran, 'GMB-SB-2026-0001');
  eq(firstReg.fin.data.status, 'PENDING');
  eq(firstReg.fin.data.jumlah_pemain, 2);
});

test('Data tersimpan di CLUB & PEMAIN, NIK tersimpan sebagai teks', () => {
  const clubs = e.ctx.readTable_('CLUB');
  const players = e.ctx.readTable_('PEMAIN');
  eq(clubs.length, 1); eq(players.length, 2);
  eq(players[0].nik.length, 16);
  assert(players[0].akte_file_id && players[0].foto_file_id);
  eq(clubs[0].whatsapp, '6281234567890');
});

test('Folder club di-rename GMB-SB-2026-0001_NAMA_CLUB dengan subfolder', () => {
  const f = [...e.fakes.folders.values()].find((x) => x.name.startsWith('GMB-SB-2026-0001_'));
  assert(f, 'folder club');
  assert(f.folders.some((x) => x.name === 'AKTE_KELAHIRAN') && f.folders.some((x) => x.name === 'FOTO_FULL_BODY'));
  assert(f.folders.find((x) => x.name === 'AKTE_KELAHIRAN').files[0].name.startsWith('AKTE_GMB-SB-2026-0001_01_'));
});

test('Semua file Drive privat', () => {
  for (const f of e.fakes.files.values()) eq(f.sharing, 'PRIVATE', 'file ' + f.name);
});

test('LOG tidak memuat NIK', () => {
  const logs = e.ctx.readTable_('LOG');
  const nik = e.ctx.readTable_('PEMAIN')[0].nik;
  assert(logs.length > 0);
  assert(!JSON.stringify(logs).includes(nik), 'NIK bocor ke LOG');
  const acts = logs.map((l) => l.aktivitas);
  ['CREATE_CLUB', 'ADD_PLAYER', 'UPLOAD_AKTE', 'UPLOAD_FOTO', 'SUBMIT'].forEach((a) => assert(acts.includes(a), a));
});

test('Duplicate submission (finalize diulang) -> nomor sama, tanpa record baru', () => {
  const again = e.api('submit_finalize', { submission_token: firstReg.token });
  assert(again.success);
  eq(again.data.nomor_pendaftaran, 'GMB-SB-2026-0001');
  eq(again.data.already_submitted, true);
  eq(e.ctx.readTable_('CLUB').length, 1);
});

test('Duplicate submission (init diulang setelah selesai) -> aman', () => {
  const again = e.api('submit_init', { submission_token: firstReg.token, payload: payload() });
  assert(again.success);
  eq(again.data.already_submitted, true);
  eq(e.ctx.readTable_('CLUB').length, 1);
});

test('Upload setelah selesai ditolak', () => {
  const r = e.api('upload_file', { submission_token: firstReg.token, slot: 'akte:p000001', file_name: 'a.pdf', mime_type: 'application/pdf', data_base64: PDF });
  eq(r.error_code, 'ALREADY_SUBMITTED');
});

test('Club yang sama di kategori sama ditolak (DUPLICATE_CLUB)', () => {
  const p = payload({ club: Object.assign({}, payload().club, { nama_club: 'ssb uji 1' }) }, [player(30)]);
  // nama club pertama = "SSB Uji 1"? pakai nama persis dari data tersimpan
  p.club.nama_club = e.ctx.readTable_('CLUB')[0].nama_club.toLowerCase();
  const r = e.api('submit_init', { submission_token: newToken(), payload: p });
  eq(r.error_code, 'DUPLICATE_CLUB');
});

test('NIK yang sudah terdaftar ditolak (DUPLICATE_NIK)', () => {
  const r = e.api('submit_init', { submission_token: newToken(), payload: payload({}, [player(1, { ref: 'zz000001' })]) });
  eq(r.error_code, 'DUPLICATE_NIK');
});

test('Pendaftaran U-12 berhasil + nomor berurutan 0002', () => {
  const p = payload({ kategori: 'U12' }, [player(41, { tanggal_lahir: '2015-01-15' })]);
  const r = register(e, p);
  assert(r.fin.success, r.fin.message);
  eq(r.fin.data.nomor_pendaftaran, 'GMB-SB-2026-0002');
  eq(r.fin.data.kategori, 'U12');
});

test('Kategori tidak valid ditolak', () => {
  const r = e.api('submit_init', { submission_token: newToken(), payload: payload({ kategori: 'U14' }) });
  eq(r.error_code, 'INVALID_CATEGORY');
});

test('Usia tidak sesuai kategori ditolak (INVALID_AGE)', () => {
  const r = e.api('submit_init', { submission_token: newToken(), payload: payload({}, [player(50, { tanggal_lahir: '2013-01-01' })]) });
  eq(r.error_code, 'INVALID_AGE');
  assert(r.errors[0].message.includes('tidak sesuai kategori'));
});

test('Aturan usia belum dikonfigurasi -> CONFIG_ERROR (tidak mengarang batas)', () => {
  const saved = e.fakes.props.get('MAX_AGE_U10');
  e.fakes.props.delete('MAX_AGE_U10');
  const r = e.api('submit_init', { submission_token: newToken(), payload: payload({}, [player(51)]) });
  e.fakes.props.set('MAX_AGE_U10', saved);
  eq(r.error_code, 'CONFIG_ERROR');
});

test('NIK tidak valid ditolak', () => {
  const r = e.api('submit_init', { submission_token: newToken(), payload: payload({}, [player(52, { nik: '12345' })]) });
  eq(r.error_code, 'VALIDATION_ERROR');
  assert(r.errors.some((x) => x.field.endsWith('.nik')));
});

test('Nomor WhatsApp tidak valid ditolak', () => {
  const p = payload({}, [player(53)]);
  p.official.whatsapp = '12345';
  const r = e.api('submit_init', { submission_token: newToken(), payload: p });
  assert(r.errors.some((x) => x.field === 'official.whatsapp'));
});

test('Field wajib kosong ditolak', () => {
  const p = payload({}, [player(54, { nama_lengkap: '' })]);
  p.club.nama_club = '';
  p.club.distrik = '';
  const r = e.api('submit_init', { submission_token: newToken(), payload: p });
  eq(r.error_code, 'VALIDATION_ERROR');
  const fields = r.errors.map((x) => x.field);
  assert(fields.includes('club.nama_club') && fields.includes('club.distrik') && fields.includes('players[0].nama_lengkap'));
});

test('Tanpa pemain ditolak', () => {
  const r = e.api('submit_init', { submission_token: newToken(), payload: payload({}, []) });
  assert(r.errors.some((x) => x.field === 'players'));
});

test('Nomor punggung & NIK duplikat dalam satu club ditolak', () => {
  const r = e.api('submit_init', { submission_token: newToken(), payload: payload({}, [player(55), player(56, { nomor_punggung: '55', nik: player(55).nik })]) });
  const msgs = r.errors.map((x) => x.field);
  assert(msgs.includes('players[1].nik') && msgs.includes('players[1].nomor_punggung'));
});

test('Token submission tidak valid ditolak', () => {
  eq(e.api('submit_init', { submission_token: 'abc', payload: payload() }).error_code, 'INVALID_TOKEN');
});

test('Batas maksimal pemain (MAX_PLAYERS) ditegakkan', () => {
  e.fakes.props.set('MAX_PLAYERS', '2');
  const list = [player(60), player(61), player(62)];
  const r = e.api('submit_init', { submission_token: newToken(), payload: payload({}, list) });
  e.fakes.props.delete('MAX_PLAYERS');
  eq(r.error_code, 'VALIDATION_ERROR');
  assert(r.errors[0].message.includes('Maksimal 2'));
});

// ---- File validation ----
const fileEnv = { token: null, p: null };
test('Init untuk uji file', () => {
  fileEnv.p = payload({}, [player(70), player(71)]);
  fileEnv.token = newToken();
  const r = e.api('submit_init', { submission_token: fileEnv.token, payload: fileEnv.p });
  assert(r.success, r.message);
  eq(r.data.required_slots.length, 4);
});

function up(slot, name, mime, data) {
  return e.api('upload_file', { submission_token: fileEnv.token, slot, file_name: name, mime_type: mime, data_base64: data });
}

test('Upload akte PDF diterima', () => assert(up('akte:p000070', 'akte.pdf', 'application/pdf', PDF).success));
test('Upload akte PNG diterima', () => assert(up('akte:p000071', 'akte.png', 'image/png', PNG).success));
test('Upload foto JPG diterima', () => assert(up('foto:p000070', 'foto.JPEG', 'image/jpeg', JPG).success));
test('Upload foto PDF ditolak', () => eq(up('foto:p000071', 'foto.pdf', 'application/pdf', PDF).error_code, 'INVALID_FILE'));
test('File > 5MB ditolak', () => eq(up('foto:p000071', 'big.jpg', 'image/jpeg', BIG).error_code, 'FILE_TOO_LARGE'));
test('MIME palsu (exe berekstensi .jpg) ditolak', () => eq(up('foto:p000071', 'foto.jpg', 'image/jpeg', EXE).error_code, 'INVALID_MIME'));
test('Ekstensi tidak cocok dengan isi ditolak', () => eq(up('foto:p000071', 'foto.png', 'image/png', JPG).error_code, 'INVALID_MIME'));
test('Double extension .php.jpg ditolak', () => eq(up('foto:p000071', 'shell.php.jpg', 'image/jpeg', JPG).error_code, 'INVALID_FILE'));
test('Ekstensi .exe ditolak', () => eq(up('akte:p000071', 'x.exe', 'application/pdf', PDF).error_code, 'INVALID_FILE'));
test('Slot pemain yang tidak ada ditolak', () => eq(up('foto:zzzzzzzz', 'foto.jpg', 'image/jpeg', JPG).error_code, 'INVALID_SLOT'));

test('Finalize dengan foto belum lengkap -> MISSING_FILES (tidak sukses palsu)', () => {
  const r = e.api('submit_finalize', { submission_token: fileEnv.token });
  eq(r.success, false); eq(r.error_code, 'MISSING_FILES');
  assert(r.errors.length === 1 && r.errors[0].field === 'foto:p000071');
  eq(e.ctx.readTable_('CLUB').filter((c) => c.nama_club === fileEnv.p.club.nama_club).length, 0);
});

test('Missing akte terdeteksi saat file dihapus dari Drive', () => {
  assert(up('foto:p000071', 'foto.png', 'image/png', PNG).success);
  const sub = e.ctx.findByField_('SUBMISSION', 'submission_token', fileEnv.token);
  const files = JSON.parse(sub.files_json);
  e.fakes.files.get(files['akte:p000070'].file_id).trashed = true;
  const r = e.api('submit_finalize', { submission_token: fileEnv.token });
  eq(r.error_code, 'MISSING_FILES');
  assert(r.errors[0].message.includes('Akte'));
});

test('Replace file: file lama di-trash', () => {
  const sub1 = JSON.parse(e.ctx.findByField_('SUBMISSION', 'submission_token', fileEnv.token).files_json);
  const oldId = sub1['foto:p000070'].file_id;
  const r = up('foto:p000070', 'foto2.jpg', 'image/jpeg', JPG);
  assert(r.success && r.data.replaced);
  assert(e.fakes.files.get(oldId).trashed);
});

test('Google Drive error -> DRIVE_ERROR, bukan sukses', () => {
  e.fakes.faults.driveCreate = true;
  const r = up('akte:p000070', 'akte.pdf', 'application/pdf', PDF);
  e.fakes.faults.driveCreate = false;
  eq(r.success, false); eq(r.error_code, 'DRIVE_ERROR');
});

test('Google Sheets error saat finalize -> rollback, state FAILED, lalu retry sukses', () => {
  assert(up('akte:p000070', 'akte.pdf', 'application/pdf', PDF).success);
  const clubsBefore = e.ctx.readTable_('CLUB').length;
  const playersBefore = e.ctx.readTable_('PEMAIN').length;
  e.fakes.faults.setValuesFailSheet = 'CLUB';
  const r = e.api('submit_finalize', { submission_token: fileEnv.token });
  e.fakes.faults.setValuesFailSheet = null;
  eq(r.success, false);
  eq(e.ctx.readTable_('CLUB').length, clubsBefore, 'club rollback');
  eq(e.ctx.readTable_('PEMAIN').length, playersBefore, 'pemain rollback');
  const sub = e.ctx.findByField_('SUBMISSION', 'submission_token', fileEnv.token);
  eq(sub.state, 'FAILED');
  const nomorReserved = sub.nomor_pendaftaran;
  assert(nomorReserved, 'nomor dicadangkan');

  const retry = e.api('submit_finalize', { submission_token: fileEnv.token });
  assert(retry.success, retry.message);
  eq(retry.data.nomor_pendaftaran, nomorReserved, 'retry memakai nomor yang sama');
  eq(e.ctx.readTable_('PEMAIN').filter((p) => p.nomor_pendaftaran === nomorReserved).length, 2);
});

test('Spreadsheet tidak dapat diakses -> SHEETS_ERROR', () => {
  e.fakes.faults.sheetsOpen = true;
  const r = e.api('check_status', { nomor_pendaftaran: 'GMB-SB-2026-0001' });
  e.fakes.faults.sheetsOpen = false;
  eq(r.error_code, 'SHEETS_ERROR');
  assert(!JSON.stringify(r).includes('Simulated'), 'detail internal bocor');
});

test('Lock sibuk -> BUSY', () => {
  e.fakes.faults.lockBusy = true;
  const r = e.api('submit_finalize', { submission_token: fileEnv.token });
  e.fakes.faults.lockBusy = false;
  eq(r.error_code, 'BUSY');
});

test('Nomor unik & berurutan untuk 5 pendaftaran berikutnya', () => {
  const nums = [];
  for (let i = 0; i < 5; i++) {
    const r = register(e, payload({}, [player(100 + i)]));
    assert(r.fin.success, r.fin.message);
    nums.push(r.fin.data.nomor_pendaftaran);
  }
  eq(new Set(nums).size, 5);
  const ints = nums.map((n) => parseInt(n.slice(-4), 10));
  for (let i = 1; i < ints.length; i++) eq(ints[i], ints[i - 1] + 1, 'berurutan');
});

test('Pendaftaran ditutup -> REGISTRATION_CLOSED', () => {
  e.fakes.props.set('REGISTRATION_OPEN', 'false');
  const r = e.api('submit_init', { submission_token: newToken(), payload: payload({}, [player(120)]) });
  e.fakes.props.set('REGISTRATION_OPEN', 'true');
  eq(r.error_code, 'REGISTRATION_CLOSED');
});

// ---- Public status ----
test('Cek status publik hanya data non-sensitif', () => {
  const r = e.api('check_status', { nomor_pendaftaran: 'gmb-sb-2026-0001' });
  assert(r.success);
  eq(Object.keys(r.data).sort().join(','), 'kategori,nama_club,nomor_pendaftaran,status');
});
test('Cek status nomor tidak ada -> NOT_FOUND', () => eq(e.api('check_status', { nomor_pendaftaran: 'GMB-SB-2026-9999' }).error_code, 'NOT_FOUND'));
test('Cek status format salah -> VALIDATION_ERROR', () => eq(e.api('check_status', { nomor_pendaftaran: "1' OR 1=1" }).error_code, 'VALIDATION_ERROR'));

// ---- Admin ----
test('Aksi admin tanpa konteks admin ditolak', () => eq(e.api('admin_stats', {}).error_code, 'UNAUTHORIZED'));
test('Role tidak dikenal ditolak', () => eq(e.api('admin_stats', {}, { username: 'x', role: 'ROOT' }).error_code, 'UNAUTHORIZED'));

test('Admin stats', () => {
  const r = e.api('admin_stats', {}, ADMIN);
  assert(r.success);
  eq(r.data.total_club, e.ctx.readTable_('CLUB').length);
  eq(r.data.status.PENDING, r.data.total_club);
});

test('Admin list clubs + filter kategori/status/search', () => {
  eq(e.api('admin_list_clubs', { kategori: 'U12' }, VIEWER).data.pagination.total, 1);
  eq(e.api('admin_list_clubs', { status: 'VERIFIED' }, VIEWER).data.pagination.total, 0);
  eq(e.api('admin_list_clubs', { q: 'GMB-SB-2026-0002' }, VIEWER).data.pagination.total, 1);
  eq(e.api('admin_list_clubs', { q: 'pemain uji 41' }, VIEWER).data.pagination.total, 1);
  const s = JSON.stringify(e.api('admin_list_clubs', {}, ADMIN));
  assert(!s.includes('file_id') && !s.includes('drive.google.com'), 'Drive ID bocor');
});

let clubId;
test('Admin detail club: VIEWER melihat NIK tersamar, VERIFIKATOR lengkap', () => {
  clubId = e.ctx.readTable_('CLUB')[0].club_id;
  const v = e.api('admin_get_club', { club_id: clubId }, VIEWER);
  assert(v.data.players[0].nik.includes('****'));
  assert(v.data.players[0].nama_ayah === undefined, 'data ortu tersembunyi');
  const f = e.api('admin_get_club', { club_id: clubId }, VERIF);
  eq(f.data.players[0].nik.length, 16);
  assert(f.data.players[0].nama_ayah);
  assert(!JSON.stringify(f).includes('drive.google.com'));
});

test('VIEWER tidak boleh update status', () => {
  eq(e.api('admin_update_status', { target: 'club', id: clubId, status: 'VERIFIED' }, VIEWER).error_code, 'FORBIDDEN');
});

test('Update status club + log ADMIN_UPDATE_STATUS', () => {
  const r = e.api('admin_update_status', { target: 'club', id: clubId, status: 'REVIEW' }, VERIF);
  assert(r.success);
  eq(e.ctx.findByField_('CLUB', 'club_id', clubId).status, 'REVIEW');
  assert(e.ctx.readTable_('LOG').some((l) => l.aktivitas === 'ADMIN_UPDATE_STATUS' && l.aktor === 'verif1'));
});

test('Status REVISION wajib catatan', () => {
  eq(e.api('admin_update_status', { target: 'club', id: clubId, status: 'REVISION' }, VERIF).error_code, 'VALIDATION_ERROR');
  const ok = e.api('admin_update_status', { target: 'club', id: clubId, status: 'REVISION', note: 'Foto pemain #2 buram' }, VERIF);
  assert(ok.success);
  assert(e.ctx.findByField_('CLUB', 'club_id', clubId).catatan_admin.includes('Foto pemain #2 buram'));
});

test('Status tidak valid ditolak', () => eq(e.api('admin_update_status', { target: 'club', id: clubId, status: 'APPROVED' }, ADMIN).error_code, 'VALIDATION_ERROR'));

test('Update status pemain VERIFIED', () => {
  const pid = e.ctx.readTable_('PEMAIN')[0].pemain_id;
  assert(e.api('admin_update_status', { target: 'player', id: pid, status: 'VERIFIED' }, VERIF).success);
  eq(e.ctx.findByField_('PEMAIN', 'pemain_id', pid).status_verifikasi, 'VERIFIED');
});

test('Tambah catatan admin', () => {
  const r = e.api('admin_add_note', { target: 'club', id: clubId, note: 'Sudah dihubungi via WA' }, ADMIN);
  assert(r.success);
  assert(e.ctx.readTable_('LOG').some((l) => l.aktivitas === 'ADMIN_ADD_NOTE'));
});

test('Admin list players + filter', () => {
  const r = e.api('admin_list_players', { kategori: 'U12' }, ADMIN);
  eq(r.data.pagination.total, 1);
  eq(e.api('admin_list_players', { status: 'VERIFIED' }, ADMIN).data.pagination.total, 1);
  eq(e.api('admin_list_players', { docs: 'missing' }, ADMIN).data.pagination.total, 0);
});

test('Admin melihat dokumen (VERIFIKATOR) + ADMIN_VIEW log', () => {
  const pid = e.ctx.readTable_('PEMAIN')[0].pemain_id;
  const r = e.api('admin_get_file', { target: 'player', id: pid, type: 'akte' }, VERIF);
  assert(r.success, r.message);
  eq(r.data.mime_type, 'application/pdf');
  assert(Buffer.from(r.data.data_base64, 'base64').toString().startsWith('%PDF'));
  assert(e.ctx.readTable_('LOG').some((l) => l.aktivitas === 'ADMIN_VIEW' && l.detail === 'doc=akte'));
});

test('VIEWER tidak boleh melihat dokumen', () => {
  const pid = e.ctx.readTable_('PEMAIN')[0].pemain_id;
  eq(e.api('admin_get_file', { target: 'player', id: pid, type: 'foto' }, VIEWER).error_code, 'FORBIDDEN');
});

test('Export CSV: non-sensitif tanpa NIK; sensitif hanya SUPERADMIN', () => {
  const r = e.api('admin_export', { dataset: 'players' }, VERIF);
  assert(r.success && !r.data.columns.includes('nik'));
  eq(e.api('admin_export', { dataset: 'players', include_sensitive: true }, VERIF).error_code, 'FORBIDDEN');
  const s = e.api('admin_export', { dataset: 'players', include_sensitive: true }, ADMIN);
  assert(s.data.columns.includes('nik'));
  assert(!JSON.stringify(s).includes('drive.google.com'));
});

test('Pengaturan: update aturan usia (SUPERADMIN) & validasi', () => {
  eq(e.api('admin_update_settings', { settings: { MAX_AGE_U10: '11' } }, VERIF).error_code, 'FORBIDDEN');
  eq(e.api('admin_update_settings', { settings: { MIN_AGE_U10: '12', MAX_AGE_U10: '10' } }, ADMIN).error_code, 'VALIDATION_ERROR');
  eq(e.api('admin_update_settings', { settings: { SPREADSHEET_ID: 'x' } }, ADMIN).error_code, 'VALIDATION_ERROR');
  const ok = e.api('admin_update_settings', { settings: { MAX_AGE_U10: '10', REGISTRATION_OPEN: 'true' } }, ADMIN);
  assert(ok.success);
  assert(!JSON.stringify(ok).includes(SECRET));
});

test('Admin logs', () => {
  const r = e.api('admin_list_logs', { aktivitas: 'SUBMIT' }, ADMIN);
  assert(r.success && r.data.items.length >= 1);
  eq(e.api('admin_list_logs', {}, VIEWER).error_code, 'FORBIDDEN');
});

test('Auth event ADMIN_LOGIN dicatat', () => {
  assert(e.api('admin_auth_event', { aktivitas: 'ADMIN_LOGIN', username: 'superadmin' }).success);
  eq(e.api('admin_auth_event', { aktivitas: 'DROP' }).error_code, 'VALIDATION_ERROR');
});

test('Cleanup submission terbengkalai -> EXPIRED & file di-trash', () => {
  const token = newToken();
  const p = payload({}, [player(200)]);
  assert(e.api('submit_init', { submission_token: token, payload: p }).success);
  assert(e.api('upload_file', { submission_token: token, slot: 'akte:p000200', file_name: 'a.pdf', mime_type: 'application/pdf', data_base64: PDF }).success);
  const sub = e.ctx.findByField_('SUBMISSION', 'submission_token', token);
  e.ctx.updateRowFields_('SUBMISSION', sub._row, { updated_at: '2020-01-01T00:00:00.000Z' });
  const n = e.ctx.cleanupStaleSubmissions();
  assert(n >= 1);
  const after = e.ctx.findByField_('SUBMISSION', 'submission_token', token);
  eq(after.state, 'EXPIRED');
  eq(after.payload_json, '');
  eq(e.api('submit_finalize', { submission_token: token }).error_code, 'SUBMISSION_EXPIRED');
});

test('Payload COMPLETE dikosongkan dari SUBMISSION (tidak ada duplikasi data pribadi)', () => {
  const done = e.ctx.readTable_('SUBMISSION').filter((s) => s.state === 'COMPLETE');
  assert(done.length > 0 && done.every((s) => s.payload_json === ''));
});

test('Akun penyimpanan resmi gambasipapsel@gmail.com terverifikasi di pengaturan', () => {
  const r = e.api('admin_get_settings', {}, ADMIN);
  eq(r.data.storage.owner_email, 'gambasipapsel@gmail.com');
  eq(r.data.storage.verified, true);
  eq(r.data.storage.drive_url, 'https://drive.google.com/drive/home');
  assert(!JSON.stringify(r).includes(e.fakes.props.get('ROOT_FOLDER_ID')), 'folder ID bocor');
});

test('Akun Google salah -> pendaftaran & perubahan data diblokir', () => {
  e.fakes.faults.accountEmail = 'orang.lain@gmail.com';
  const init = e.api('submit_init', { submission_token: newToken(), payload: payload({}, [player(400)]) });
  const upd = e.api('admin_update_status', { target: 'club', id: clubId, status: 'REVIEW' }, ADMIN);
  const st = e.api('admin_get_settings', {}, ADMIN);
  const pub = e.api('check_status', { nomor_pendaftaran: 'GMB-SB-2026-0001' });
  let setupErr = '';
  try { e.ctx.setupProject(); } catch (err) { setupErr = err.message; }
  e.fakes.faults.accountEmail = 'gambasipapsel@gmail.com';
  eq(init.error_code, 'CONFIG_ERROR');
  eq(upd.error_code, 'CONFIG_ERROR');
  eq(st.data.storage.mismatch, true);
  assert(pub.success, 'baca status publik tetap jalan');
  assert(setupErr.includes('gambasipapsel@gmail.com'), 'setupProject menolak akun salah');
});

test('Formula injection dinetralkan', () => {
  const p = payload({}, [player(300)]);
  p.club.nama_club = '=HYPERLINK("http://evil")';
  const r = register(e, p);
  assert(r.fin.success, r.fin.message);
  const ss = [...e.fakes.spreadsheets.values()][0];
  const raw = ss.getSheetByName('CLUB').rows.find((row) => String(row[3]).includes('HYPERLINK'));
  assert(String(raw[3]).startsWith("'"), 'harus diawali apostrof');
});

// =========================================================================
for (const [s, n] of results) console.log(`${s}  ${n}`);
console.log(`\nGAS tests: ${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
