/**
 * Response.gs
 * Format respons JSON standar:
 *   { success: true,  message: '...', data: {...} }
 *   { success: false, message: '...', error_code: '...', errors?: [...] }
 * Tidak pernah mengirim stack trace, secret, spreadsheet ID, atau Drive ID.
 */

function jsonOutput_(obj) {
  return ContentService
    .createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}

function ok_(message, data) {
  return { success: true, message: message, data: data || {} };
}

function fail_(message, errorCode, errors) {
  var out = { success: false, message: message, error_code: errorCode };
  if (errors && errors.length) out.errors = errors;
  return out;
}

/** Ubah exception apa pun menjadi respons error yang aman. */
function errorToResponse_(err) {
  if (err instanceof AppError) {
    var errors = err.details && err.details.errors ? err.details.errors : null;
    return fail_(err.message, err.code, errors);
  }
  // Error tak terduga: catat detail di log eksekusi Apps Script (hanya pemilik),
  // kirim pesan generik ke client.
  try {
    console.error('Unexpected error: ' + (err && err.message ? err.message : String(err)));
  } catch (ignore) { /* noop */ }
  return fail_('Terjadi kesalahan pada server. Silakan coba lagi.', 'SERVER_ERROR');
}
