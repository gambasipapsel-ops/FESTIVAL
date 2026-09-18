/**
 * DEV-ONLY: HTTP server yang menjalankan kode .gs ASLI di atas fake Google services,
 * agar lapisan PHP bisa diuji end-to-end tanpa deploy ke Google.
 *
 *   node tests/gas/server.js 8790
 *
 * Endpoint kontrol (hanya untuk test): POST /__test/fault, POST /__test/props
 */
'use strict';

const http = require('http');
const path = require('path');
const { loadGas } = require('./fake-google');

const port = parseInt(process.argv[2] || '8790', 10);
const secret = process.env.GAS_TEST_SECRET || 'test-secret-0123456789abcdef0123456789abcdef';

const g = loadGas(path.join(__dirname, '..', '..', 'google-apps-script'));
g.fakes.props.set('API_SECRET', secret);
g.ctx.setupProject();
// Nilai KHUSUS PENGUJIAN (bukan regulasi resmi)
Object.entries({
  MIN_AGE_U10: '6', MAX_AGE_U10: '10', MIN_AGE_U12: '9', MAX_AGE_U12: '12',
  AGE_REFERENCE_DATE: '2026-10-30', ENVIRONMENT: 'test'
}).forEach(([k, v]) => g.fakes.props.set(k, v));

function readBody(req) {
  return new Promise((resolve) => {
    const chunks = [];
    req.on('data', (c) => chunks.push(c));
    req.on('end', () => resolve(Buffer.concat(chunks).toString('utf8')));
  });
}

http.createServer(async (req, res) => {
  const body = await readBody(req);
  const send = (obj) => {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(typeof obj === 'string' ? obj : JSON.stringify(obj));
  };
  if (req.url === '/__test/fault') {
    Object.assign(g.fakes.faults, JSON.parse(body || '{}'));
    return send({ ok: true, faults: g.fakes.faults });
  }
  if (req.url === '/__test/props') {
    const p = JSON.parse(body || '{}');
    Object.entries(p).forEach(([k, v]) => (v === null ? g.fakes.props.delete(k) : g.fakes.props.set(k, String(v))));
    return send({ ok: true });
  }
  if (req.url === '/__test/slow') {
    return setTimeout(() => send({ success: true, message: 'late', data: {} }), 10000);
  }
  if (req.url === '/__test/html') {
    res.writeHead(200, { 'Content-Type': 'text/html' });
    return res.end('<html>Google login page</html>');
  }
  if (req.method === 'GET') {
    return send(g.ctx.doGet().getContent());
  }
  try {
    send(g.call(body));
  } catch (e) {
    res.writeHead(500);
    res.end('internal');
  }
}).listen(port, '127.0.0.1', () => {
  console.log(`Fake GAS server listening on http://127.0.0.1:${port}`);
});
