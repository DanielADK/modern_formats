(function () {
  var cfg = window.MF_BULK;
  var btn = document.getElementById('mfBulkStart');
  if (!btn) return;

  var bar = document.getElementById('mfProgressBar');
  var status = document.getElementById('mfBulkStatus');
  var pending = document.getElementById('mfPending');
  var album = document.getElementById('mfAlbum');
  var total = cfg.total || 0;
  var errorCount = 0;

  function catId() { return album ? album.value : '0'; }

  function setProgress(remaining) {
    var doneCount = Math.max(0, total - remaining);
    var pct = total > 0 ? Math.round((doneCount / total) * 100) : 100;
    bar.style.width = pct + '%';
    if (pending) pending.textContent = remaining;
  }

  function ws(method, fields) {
    var body = new FormData();
    body.append('method', method);
    body.append('cat_id', catId());
    Object.keys(fields || {}).forEach(function (k) { body.append(k, fields[k]); });
    return fetch(cfg.wsUrl, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j || j.stat !== 'ok') { throw new Error('ws error'); }
        return j.result;
      });
  }

  function refreshPending() {
    ws('pwg.modernFormats.getPending', {}).then(function (res) {
      total = res.pending;
      if (pending) pending.textContent = res.pending;
      btn.disabled = !cfg.capOk || res.pending === 0;
      status.textContent = '';
    }).catch(function () {});
  }

  function step(startId, attempt) {
    attempt = attempt || 0;
    ws('pwg.modernFormats.convert', { limit: '200', pwg_token: cfg.token, start_id: String(startId || 0) })
      .then(function (res) {
        if (res.errors && res.errors.length) { errorCount += res.errors.length; }
        setProgress(res.remaining);
        if (res.next_id) {
          setTimeout(function () { step(res.next_id); }, 50);
        } else {
          status.textContent = errorCount > 0 ? cfg.i18n.doneErrors : cfg.i18n.done;
          btn.disabled = false;
        }
      })
      .catch(function () {
        // The job is resumable, so retry the same cursor on a transient failure
        // (timeout, network blip) before giving up.
        if (attempt < 5) {
          setTimeout(function () { step(startId, attempt + 1); }, 1000 * (attempt + 1));
        } else {
          // Persistent failure: a photo that always times out (poison pill).
          // Skip it server-side and continue past it.
          skipOne(startId);
        }
      });
  }

  function skipOne(startId) {
    ws('pwg.modernFormats.skip', { pwg_token: cfg.token, start_id: String(startId || 0) })
      .then(function (res) {
        if (res.skipped) { errorCount += 1; }
        setProgress(res.remaining);
        if (res.next_id) {
          setTimeout(function () { step(res.next_id); }, 50);
        } else {
          status.textContent = errorCount > 0 ? cfg.i18n.doneErrors : cfg.i18n.done;
          btn.disabled = false;
        }
      })
      .catch(function () {
        status.textContent = cfg.i18n.failed;
        btn.disabled = false;
      });
  }

  if (album) album.addEventListener('change', refreshPending);

  btn.addEventListener('click', function () {
    btn.disabled = true;
    document.getElementById('mfProgressWrap').style.display = 'block';
    bar.style.width = '0';
    errorCount = 0;
    status.textContent = cfg.i18n.running;
    step(0);
  });
})();
