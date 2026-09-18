/**
 * FileService.gs
 * Penyimpanan dokumen ke Google Drive (PRIVAT).
 *
 * Struktur:
 * GAMBASI PAPUA SELATAN 2026/
 *   CLUB/
 *     GMB-SB-2026-0001_NAMA_CLUB/
 *       AKTE_KELAHIRAN/
 *       FOTO_FULL_BODY/
 *   LOGO_CLUB/
 *
 * File TIDAK pernah dibagikan "Anyone with the link". Akses dokumen untuk admin
 * dilakukan melalui aksi admin_get_file (diproxy oleh PHP setelah login).
 */

function getRootFolder_() {
  var id = getRequiredSetting_('ROOT_FOLDER_ID');
  try {
    return DriveApp.getFolderById(id);
  } catch (e) {
    throw new AppError('DRIVE_ERROR', 'Penyimpanan dokumen tidak dapat diakses. Silakan coba lagi nanti.');
  }
}

/** Ambil folder anak berdasarkan nama; buat jika belum ada (tidak membuat duplikat). */
function getOrCreateFolder_(parent, name) {
  var it = parent.getFoldersByName(name);
  if (it.hasNext()) return it.next();
  var folder = parent.createFolder(name);
  makePrivate_(folder);
  return folder;
}

function makePrivate_(item) {
  try {
    item.setSharing(DriveApp.Access.PRIVATE, DriveApp.Permission.NONE);
  } catch (e) {
    // Beberapa domain Workspace membatasi setSharing; item tetap mewarisi akses folder induk (privat).
  }
}

function getFolderByIdSafe_(id) {
  try {
    return DriveApp.getFolderById(id);
  } catch (e) {
    throw new AppError('DRIVE_ERROR', 'Folder dokumen tidak ditemukan. Silakan ulangi pendaftaran.');
  }
}

/** Folder sementara club selama proses upload (di-rename saat finalisasi). */
function createSubmissionFolder_(token) {
  var clubRoot = getOrCreateFolder_(getRootFolder_(), APP_CONFIG.CLUB_FOLDER_NAME);
  var folder = getOrCreateFolder_(clubRoot, '_PROSES_' + token.substring(0, 12).toUpperCase());
  getOrCreateFolder_(folder, APP_CONFIG.AKTE_FOLDER_NAME);
  getOrCreateFolder_(folder, APP_CONFIG.FOTO_FOLDER_NAME);
  return folder;
}

function parseSlot_(slot) {
  var s = String(slot || '');
  if (s === 'logo') return { docType: 'logo', ref: '' };
  var m = /^(akte|foto):([a-z0-9]{6,16})$/.exec(s);
  if (!m) return null;
  return { docType: m[1], ref: m[2] };
}

/**
 * Simpan file ke Drive dan pastikan benar-benar tersimpan.
 * @return {{file_id:string, url:string, size:number, mime:string, ext:string}}
 */
function saveFileToDrive_(submission, slotInfo, validated) {
  var folder;
  try {
    var clubFolder = getFolderByIdSafe_(submission.folder_id);
    if (slotInfo.docType === 'akte') folder = getOrCreateFolder_(clubFolder, APP_CONFIG.AKTE_FOLDER_NAME);
    else if (slotInfo.docType === 'foto') folder = getOrCreateFolder_(clubFolder, APP_CONFIG.FOTO_FOLDER_NAME);
    else folder = getOrCreateFolder_(getRootFolder_(), APP_CONFIG.LOGO_FOLDER_NAME);
  } catch (e) {
    if (e instanceof AppError) throw e;
    throw new AppError('DRIVE_ERROR', 'Gagal menyiapkan folder dokumen. Silakan coba lagi.');
  }

  var baseName = slotInfo.docType === 'logo'
    ? 'LOGO_PROSES_' + submission.club_id
    : slotInfo.docType.toUpperCase() + '_PROSES_' + slotInfo.ref.toUpperCase();
  var fileName = baseName + '.' + validated.ext;

  var file;
  try {
    var blob = Utilities.newBlob(validated.bytes, validated.mime, fileName);
    file = folder.createFile(blob);
    makePrivate_(file);
  } catch (e) {
    throw new AppError('DRIVE_ERROR', 'Gagal menyimpan file ke penyimpanan. Silakan coba lagi.');
  }

  // Verifikasi file benar-benar tersimpan
  var id = file.getId();
  var saved;
  try {
    saved = DriveApp.getFileById(id);
  } catch (e) {
    throw new AppError('DRIVE_ERROR', 'File gagal diverifikasi di penyimpanan. Silakan unggah ulang.');
  }
  if (!id || saved.getSize() !== validated.bytes.length) {
    try { saved.setTrashed(true); } catch (ignore) { /* noop */ }
    throw new AppError('DRIVE_ERROR', 'File gagal diverifikasi di penyimpanan. Silakan unggah ulang.');
  }

  return { file_id: id, url: saved.getUrl(), size: validated.bytes.length, mime: validated.mime, ext: validated.ext };
}

function trashFileQuietly_(fileId) {
  if (!fileId) return;
  try { DriveApp.getFileById(fileId).setTrashed(true); } catch (ignore) { /* noop */ }
}

/** true jika file ada dan tidak di-trash. */
function driveFileExists_(fileId) {
  try {
    var f = DriveApp.getFileById(fileId);
    return !f.isTrashed();
  } catch (e) {
    return false;
  }
}

function renameQuietly_(item, name) {
  try { item.setName(name); } catch (ignore) { /* noop */ }
}

/** Baca file untuk admin (akses terkontrol, file tetap privat). */
function readFileForAdmin_(fileId) {
  var file;
  try {
    file = DriveApp.getFileById(fileId);
  } catch (e) {
    throw new AppError('NOT_FOUND', 'Dokumen tidak ditemukan.');
  }
  if (file.isTrashed()) throw new AppError('NOT_FOUND', 'Dokumen tidak ditemukan.');
  var blob = file.getBlob();
  var mime = blob.getContentType();
  if (['application/pdf', 'image/jpeg', 'image/png'].indexOf(mime) === -1) {
    throw new AppError('INVALID_FILE', 'Tipe dokumen tidak didukung.');
  }
  return {
    file_name: file.getName(),
    mime_type: mime,
    size: file.getSize(),
    data_base64: Utilities.base64Encode(blob.getBytes())
  };
}
