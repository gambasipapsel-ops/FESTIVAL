/**
 * Auth.gs
 * Otorisasi request.
 *
 * - Semua request wajib membawa API secret yang sama dengan Script Property API_SECRET.
 *   Secret hanya disimpan di server PHP (file .env), tidak pernah di browser.
 * - Aksi admin wajib membawa konteks admin {username, role} yang dikirim PHP
 *   setelah admin login (session + password hash di sisi PHP).
 *   Role diperiksa ulang di sini (defense in depth).
 */

function constantTimeEquals_(a, b) {
  a = String(a || '');
  b = String(b || '');
  var da = Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256, a, Utilities.Charset.UTF_8);
  var db = Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256, b, Utilities.Charset.UTF_8);
  var diff = 0;
  for (var i = 0; i < da.length; i++) diff |= (da[i] ^ db[i]);
  return diff === 0 && a.length > 0 && b.length > 0;
}

function verifyApiSecret_(req) {
  var expected = getSetting_('API_SECRET');
  if (!expected || expected.length < 32) {
    throw new AppError('CONFIG_ERROR', 'Konfigurasi server belum lengkap. Hubungi panitia.');
  }
  if (!constantTimeEquals_(req.secret, expected)) {
    throw new AppError('UNAUTHORIZED', 'Akses ditolak.');
  }
}

function getAdminContext_(req) {
  var admin = req.admin || {};
  var username = cleanText_(admin.username, 60);
  var role = String(admin.role || '').toUpperCase();
  if (!username || !APP_CONFIG.ROLES[role]) {
    throw new AppError('UNAUTHORIZED', 'Sesi admin tidak valid.');
  }
  return { username: username, role: role };
}

function adminCan_(ctx, permission) {
  var perms = APP_CONFIG.ROLES[ctx.role] || [];
  return perms.indexOf(permission) !== -1;
}

function requirePermission_(ctx, permission) {
  if (!adminCan_(ctx, permission)) {
    throw new AppError('FORBIDDEN', 'Anda tidak memiliki izin untuk aksi ini.');
  }
}
