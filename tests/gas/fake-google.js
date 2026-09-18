/**
 * DEV-ONLY test harness.
 * Implementasi tiruan (in-memory) dari service Google Apps Script yang dipakai backend,
 * sehingga kode .gs ASLI dapat dijalankan & diuji secara offline.
 * Tidak dipakai di runtime / produksi.
 */
'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');
const crypto = require('crypto');

function toSigned(buf) {
  return Array.from(buf, (x) => (x > 127 ? x - 256 : x));
}
function toBuffer(bytes) {
  return Buffer.from(bytes.map((x) => x & 0xff));
}

function createGoogleFakes() {
  const faults = {
    sheetsOpen: false,        // SpreadsheetApp.openById gagal
    setValuesFailSheet: null, // nama sheet yang gagal ditulis
    driveCreate: false,       // folder.createFile gagal
    driveRoot: false,         // DriveApp.getFolderById gagal
    lockBusy: false,          // tryLock gagal
    accountEmail: 'gambasipapsel@gmail.com' // akun yang menjalankan script
  };
  let idSeq = 1;
  const nextId = (p) => `${p}_${(idSeq++).toString(36)}_${crypto.randomBytes(4).toString('hex')}`;

  // ------------------------------------------------------------------ Sheets
  class FakeRange {
    constructor(sheet, r, c, nr, nc) { Object.assign(this, { sheet, r, c, nr: nr || 1, nc: nc || 1 }); }
    _cell(r, c) { const row = this.sheet.rows[r - 1]; return row ? row[c - 1] : undefined; }
    getValues() {
      const out = [];
      for (let i = 0; i < this.nr; i++) {
        const row = [];
        for (let j = 0; j < this.nc; j++) {
          let v = this._cell(this.r + i, this.c + j);
          if (typeof v === 'string' && v.startsWith("'")) v = v.slice(1);
          row.push(v === undefined ? '' : v);
        }
        out.push(row);
      }
      return out;
    }
    getDisplayValues() { return this.getValues().map((r) => r.map((v) => String(v))); }
    setValues(values) {
      if (faults.setValuesFailSheet === this.sheet.name) throw new Error('Simulated Sheets write failure');
      if (values.length !== this.nr || values[0].length !== this.nc) throw new Error('Range dimension mismatch');
      for (let i = 0; i < this.nr; i++) {
        for (let j = 0; j < this.nc; j++) this.sheet._set(this.r + i, this.c + j, values[i][j]);
      }
      return this;
    }
    setValue(v) {
      if (faults.setValuesFailSheet === this.sheet.name) throw new Error('Simulated Sheets write failure');
      this.sheet._set(this.r, this.c, v); return this;
    }
    setFontWeight() { return this; }
    setNumberFormat() { return this; }
  }

  class FakeSheet {
    constructor(name) { this.name = name; this.rows = []; }
    getName() { return this.name; }
    _set(r, c, v) {
      while (this.rows.length < r) this.rows.push([]);
      const row = this.rows[r - 1];
      while (row.length < c) row.push('');
      row[c - 1] = v;
    }
    getLastRow() {
      for (let i = this.rows.length - 1; i >= 0; i--) {
        if (this.rows[i].some((v) => v !== '' && v !== undefined)) return i + 1;
      }
      return 0;
    }
    getRange(r, c, nr, nc) { return new FakeRange(this, r, c, nr, nc); }
    deleteRow(r) { this.rows.splice(r - 1, 1); }
    setFrozenRows() {}
  }

  class FakeSpreadsheet {
    constructor(name) { this.id = nextId('ss'); this.name = name; this.sheets = [new FakeSheet('Sheet1')]; }
    getId() { return this.id; }
    getName() { return this.name; }
    getSheetByName(n) { return this.sheets.find((s) => s.name === n) || null; }
    insertSheet(n) { const s = new FakeSheet(n); this.sheets.push(s); return s; }
    getSheets() { return this.sheets.slice(); }
    deleteSheet(s) { this.sheets = this.sheets.filter((x) => x !== s); }
  }

  const spreadsheets = new Map();
  const SpreadsheetApp = {
    create(name) { const ss = new FakeSpreadsheet(name); spreadsheets.set(ss.id, ss); return ss; },
    openById(id) {
      if (faults.sheetsOpen) throw new Error('Simulated Sheets outage');
      const ss = spreadsheets.get(id);
      if (!ss) throw new Error('Not found');
      return ss;
    },
    flush() {}
  };

  // ------------------------------------------------------------------ Drive
  const files = new Map();
  const folders = new Map();

  function iterator(list) {
    let i = 0;
    return { hasNext: () => i < list.length, next: () => list[i++] };
  }

  class FakeFile {
    constructor(blob, parent) {
      this.id = nextId('file'); this.name = blob.name; this.bytes = blob.bytes.slice();
      this.mime = blob.mime; this.trashed = false; this.parent = parent; this.sharing = 'PRIVATE';
      files.set(this.id, this);
    }
    getId() { return this.id; }
    getName() { return this.name; }
    setName(n) { this.name = n; return this; }
    getSize() { return this.bytes.length; }
    getUrl() { return `https://drive.google.com/file/d/${this.id}/view`; }
    isTrashed() { return this.trashed; }
    setTrashed(t) { this.trashed = !!t; return this; }
    setSharing(access) { this.sharing = access; return this; }
    getBlob() {
      const self = this;
      return { getBytes: () => self.bytes.slice(), getContentType: () => self.mime, getName: () => self.name };
    }
  }

  class FakeFolder {
    constructor(name, parent) {
      this.id = nextId('folder'); this.name = name; this.parent = parent || null;
      this.folders = []; this.files = []; this.trashed = false; this.sharing = 'PRIVATE';
      folders.set(this.id, this);
    }
    getId() { return this.id; }
    getName() { return this.name; }
    setName(n) { this.name = n; return this; }
    setSharing(access) { this.sharing = access; return this; }
    setTrashed(t) { this.trashed = !!t; return this; }
    getFoldersByName(n) { return iterator(this.folders.filter((f) => f.name === n && !f.trashed)); }
    createFolder(n) { const f = new FakeFolder(n, this); this.folders.push(f); return f; }
    createFile(blob) {
      if (faults.driveCreate) throw new Error('Simulated Drive failure');
      const f = new FakeFile(blob, this); this.files.push(f); return f;
    }
  }

  const rootFolders = [];
  const DriveApp = {
    Access: { PRIVATE: 'PRIVATE', ANYONE_WITH_LINK: 'ANYONE_WITH_LINK' },
    Permission: { NONE: 'NONE', VIEW: 'VIEW' },
    getFolderById(id) {
      if (faults.driveRoot) throw new Error('Simulated Drive outage');
      const f = folders.get(id);
      if (!f || f.trashed) throw new Error('Folder not found');
      return f;
    },
    getFileById(id) {
      const f = files.get(id);
      if (!f) throw new Error('File not found');
      return f;
    },
    createFolder(n) { const f = new FakeFolder(n, null); rootFolders.push(f); return f; },
    getFoldersByName(n) { return iterator(rootFolders.filter((f) => f.name === n)); }
  };

  // ------------------------------------------------------------------ Utilities & others
  const Utilities = {
    DigestAlgorithm: { SHA_256: 'sha256' },
    Charset: { UTF_8: 'utf8' },
    getUuid: () => crypto.randomUUID(),
    base64Decode(s) {
      if (!/^[A-Za-z0-9+/]*={0,2}$/.test(s)) throw new Error('Invalid base64');
      return toSigned(Buffer.from(s, 'base64'));
    },
    base64Encode: (bytes) => toBuffer(bytes).toString('base64'),
    newBlob: (bytes, mime, name) => ({ bytes, mime, name }),
    computeDigest: (alg, str) => toSigned(crypto.createHash(alg).update(String(str), 'utf8').digest()),
    formatDate(date, tz, fmt) {
      const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: tz, year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', hour12: false
      }).formatToParts(date).reduce((a, p) => { a[p.type] = p.value; return a; }, {});
      return fmt.replace('yyyy', parts.year).replace('MM', parts.month).replace('dd', parts.day)
        .replace('HH', parts.hour === '24' ? '00' : parts.hour).replace('mm', parts.minute);
    }
  };

  const props = new Map();
  const PropertiesService = {
    getScriptProperties: () => ({
      getProperty: (k) => (props.has(k) ? props.get(k) : null),
      setProperty: (k, v) => { props.set(k, String(v)); },
      deleteProperty: (k) => { props.delete(k); }
    })
  };

  const LockService = {
    getScriptLock: () => ({ tryLock: () => !faults.lockBusy, releaseLock: () => {} })
  };

  const ContentService = {
    MimeType: { JSON: 'application/json' },
    createTextOutput(s) {
      return { _s: s, setMimeType() { return this; }, getContent() { return this._s; } };
    }
  };

  const Session = {
    getEffectiveUser: () => ({ getEmail: () => faults.accountEmail })
  };

  const logs = [];
  const Logger = { log: (m) => logs.push(String(m)) };
  const quietConsole = { log: () => {}, error: (m) => logs.push('ERR ' + m), warn: () => {} };

  return {
    faults, props, files, folders, spreadsheets, logs,
    globals: { SpreadsheetApp, DriveApp, Utilities, PropertiesService, LockService, ContentService, Session, Logger, console: quietConsole }
  };
}

/** Muat semua file .gs ke sebuah VM context. Code.gs dimuat PERTAMA (seperti urutan editor). */
function loadGas(gasDir) {
  const fakes = createGoogleFakes();
  const ctx = vm.createContext(Object.assign({}, fakes.globals));
  const all = fs.readdirSync(gasDir).filter((f) => f.endsWith('.gs'));
  const ordered = ['Code.gs'].concat(all.filter((f) => f !== 'Code.gs').sort());
  for (const f of ordered) {
    vm.runInContext(fs.readFileSync(path.join(gasDir, f), 'utf8'), ctx, { filename: f });
  }
  const call = (body) => {
    // Setiap request Apps Script dimulai dengan global state baru
    ctx.SPREADSHEET_CACHE_ = null;
    const out = ctx.doPost({ postData: { contents: typeof body === 'string' ? body : JSON.stringify(body) } });
    return JSON.parse(out.getContent());
  };
  return { ctx, fakes, call };
}

module.exports = { loadGas, createGoogleFakes };
