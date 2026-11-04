/* Keeps the freshly built documents in this browser (IndexedDB) until the agent saves them from Review and Complete. Nothing goes to the folder before then. */
(function (root) {
  function open() {
    return new Promise(function (res, rej) {
      var r = indexedDB.open('flr-drafts', 1);
      r.onupgradeneeded = function () { r.result.createObjectStore('d'); };
      r.onsuccess = function () { res(r.result); }; r.onerror = function () { rej(r.error); };
    });
  }
  async function run(mode, fn) {
    var db = await open();
    return new Promise(function (res, rej) {
      var t = db.transaction('d', mode), st = t.objectStore('d'), q = fn(st);
      t.oncomplete = function () { res(q && q.result); }; t.onerror = function () { rej(t.error); };
    });
  }
  root.FLRDraft = {
    put: function (tx, v) { return run('readwrite', function (s) { return s.put(v, tx); }); },
    get: function (tx) { return run('readonly', function (s) { return s.get(tx); }); },
    del: function (tx) { return run('readwrite', function (s) { return s.delete(tx); }); }
  };
})(window);
