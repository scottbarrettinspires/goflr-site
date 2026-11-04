/* Review and complete: shows a packet in the browser, lets the agent fill its form fields, and saves the filled PDF. */
(function (root) {
  var PL = root.PDFLib;
  function el(t, c, p) { var e = document.createElement(t); if (c) e.className = c; if (p) p.appendChild(e); return e; }

  async function start(cfg) {
    var PDFJS = root.pdfjsLib;
    var saveBtn2 = document.getElementById('esave2'), pagesEl = document.getElementById('pages'), status = document.getElementById('estatus'), saveBtn = document.getElementById('esave'), dl = document.getElementById('edl'), res = document.getElementById('eres');
    PDFJS.GlobalWorkerOptions.workerSrc = cfg.worker;
    status.textContent = 'Loading the packet...';
    var orig, draft = null;
    if (cfg.draft) {
      draft = await root.FLRDraft.get(cfg.tx);
      if (!draft || !draft.packet) throw new Error('This draft is no longer in this browser. Please build the packet again in the wizard.');
      orig = new Uint8Array(draft.packet);
    } else {
      var resp = await fetch(cfg.pdfUrl, { credentials: 'same-origin' });
      if (!resp.ok) throw new Error('Could not load the packet');
      orig = new Uint8Array(await resp.arrayBuffer());
    }
    var doc = await PDFJS.getDocument({ data: orig.slice() }).promise;
    var values = {}, dirty = {}, groups = {}; // groups[name] = [{el, kind, on}]
    function setAll(name, v, except) {
      (groups[name] || []).forEach(function (g) {
        if (g.el === except) return;
        if (g.kind === 'text') g.el.value = v;
        else if (g.kind === 'check') g.el.checked = !!v;
        else if (g.kind === 'radio') g.el.checked = (v === g.on);
      });
    }
    var wraps = [];
    for (var n = 1; n <= doc.numPages; n++) {
      var page = await doc.getPage(n), vp = page.getViewport({ scale: 1 }), cv = page.getViewport({ scale: 2 });
      var wrap = el('div', 'epage', pagesEl); wrap.style.aspectRatio = vp.width + ' / ' + vp.height; wrap.dataset.w = vp.width;
      var canvas = el('canvas', '', wrap); canvas.width = cv.width; canvas.height = cv.height;
      await page.render({ canvasContext: canvas.getContext('2d'), viewport: cv, annotationMode: PDFJS.AnnotationMode ? PDFJS.AnnotationMode.ENABLE_FORMS : 2 }).promise;
      var anns = await page.getAnnotations({ intent: 'display' });
      anns.forEach(function (a) {
        if (a.subtype !== 'Widget' || !a.fieldName || a.readOnly) return;
        var vb = vp.viewBox || [0, 0, vp.width, vp.height], r = (vp.rotation === 0 || vp.rotation === undefined) ? [a.rect[0] - vb[0], vp.height - (a.rect[1] - vb[1]), a.rect[2] - vb[0], vp.height - (a.rect[3] - vb[1])] : vp.convertToViewportRectangle(a.rect);
        var x = Math.min(r[0], r[2]), y = Math.min(r[1], r[3]), w = Math.abs(r[2] - r[0]), h = Math.abs(r[3] - r[1]);
        var box = el('div', 'efield', wrap);
        box.style.left = (x / vp.width * 100) + '%'; box.style.top = (y / vp.height * 100) + '%'; box.style.width = (w / vp.width * 100) + '%'; box.style.height = (h / vp.height * 100) + '%';
        var name = a.fieldName, inp;
        if (a.fieldType === 'Tx') {
          var multi = !!a.multiLine || h > 26;
          inp = el(multi ? 'textarea' : 'input', 'etx', box); if (!multi) inp.type = 'text';
          inp.value = Array.isArray(a.fieldValue) ? a.fieldValue.join(', ') : (a.fieldValue || '');
          inp.style.fontSize = 'calc(var(--s,1) * ' + Math.max(6, Math.min(11, h * 0.72)) + 'px)';
          if (a.textAlignment === 1) inp.style.textAlign = 'center'; else if (a.textAlignment === 2) inp.style.textAlign = 'right';
          if (a.maxLen) inp.maxLength = a.maxLen;
          if (!(name in values)) values[name] = inp.value;
          if (inp.value && !values[name]) values[name] = inp.value;
          (groups[name] = groups[name] || []).push({ el: inp, kind: 'text' });
          inp.addEventListener('input', function () { values[name] = inp.value; dirty[name] = 1; setAll(name, inp.value, inp); });
        } else if (a.fieldType === 'Btn' && a.checkBox) {
          inp = el('input', 'ecb', box); inp.type = 'checkbox';
          inp.checked = a.fieldValue && a.fieldValue !== 'Off' && (a.fieldValue === a.exportValue || a.fieldValue === a.buttonValue || a.fieldValue === 'Yes' || a.fieldValue === 'On' || a.fieldValue === true);
          if (!(name in values)) values[name] = inp.checked;
          (groups[name] = groups[name] || []).push({ el: inp, kind: 'check' });
          inp.addEventListener('change', function () { values[name] = inp.checked; dirty[name] = 1; setAll(name, inp.checked, inp); });
        } else if (a.fieldType === 'Btn' && a.radioButton) {
          inp = el('input', 'ecb', box); inp.type = 'radio'; inp.name = 'r_' + name;
          var on = a.buttonValue; inp.checked = (a.fieldValue === on);
          if (inp.checked) values[name] = on;
          (groups[name] = groups[name] || []).push({ el: inp, kind: 'radio', on: on });
          inp.addEventListener('change', function () { values[name] = on; dirty[name] = 1; });
          inp.addEventListener('click', function () { // click the selected one again to clear it
            if (inp.dataset.was === '1') { inp.checked = false; inp.dataset.was = ''; values[name] = ''; dirty[name] = 1; } else { (groups[name] || []).forEach(function (g) { g.el.dataset.was = ''; }); inp.dataset.was = '1'; }
          });
          if (inp.checked) inp.dataset.was = '1';
        } else if (a.fieldType === 'Ch') {
          inp = el('select', 'etx', box);
          (a.options || []).forEach(function (o) { var op = el('option', '', inp); op.value = o.exportValue; op.textContent = o.displayValue; });
          inp.value = Array.isArray(a.fieldValue) ? a.fieldValue[0] : (a.fieldValue || '');
          if (!(name in values)) values[name] = inp.value;
          (groups[name] = groups[name] || []).push({ el: inp, kind: 'text' });
          inp.addEventListener('change', function () { values[name] = inp.value; dirty[name] = 1; setAll(name, inp.value, inp); });
        }
      });
      wraps.push(wrap);
    }
    function scale() { wraps.forEach(function (w) { w.style.setProperty('--s', (w.clientWidth / Number(w.dataset.w))); }); }
    scale(); root.addEventListener('resize', scale);
    status.textContent = 'Click a field to fill it. Fields with the same name (like Buyer and Seller) update on every form.';
    saveBtn.disabled = false; if (saveBtn2) saveBtn2.disabled = false;

    async function build() {
      var pdf = await PL.PDFDocument.load(orig, { ignoreEncryption: true, updateMetadata: false });
      var form = pdf.getForm(), font = await pdf.embedFont(PL.StandardFonts.Helvetica);
      Object.keys(dirty).forEach(function (name) {
        var v = values[name], f; try { f = form.getField(name); } catch (e) { return; }
        try {
          if (f instanceof PL.PDFTextField) { f.setText(String(v || '')); }
          else if (f instanceof PL.PDFCheckBox) { if (v) f.check(); else f.uncheck(); }
          else if (f instanceof PL.PDFRadioGroup) { if (v) f.select(String(v)); else f.clear(); }
          else if (f instanceof PL.PDFDropdown) { if (v) f.select(String(v)); }
        } catch (e) {}
      });
      form.getFields().forEach(function (f) { if (f instanceof PL.PDFTextField && (f.getText() || '').length) { try { f.updateAppearances(font); } catch (e) {} } });
      return await pdf.save();
    }
    var lastBytes = null;
    var onSave = async function () {
      var btns = [saveBtn, saveBtn2].filter(Boolean);
      saveBtn.disabled = true; if (saveBtn2) saveBtn2.disabled = true; res.hidden = true; status.textContent = 'Preparing...'; saveBtn.dataset.t = saveBtn.textContent; btns.forEach(function (b) { b.innerHTML = '<span class="bspin"></span>Preparing...'; });
      try {
        await new Promise(function (r) { requestAnimationFrame(function () { setTimeout(r, 50); }); }); // let the spinner paint before the heavy work starts
        var bytes = await build(); lastBytes = bytes;
        if (draft) {
          // Nothing is saved to the folder yet: keep the filled documents in this browser and offer download or email-for-signing.
          draft.completed = bytes; await root.FLRDraft.put(cfg.tx, draft);
          res.innerHTML = '';
          var pd = el('p', '', res);
          var dlb = el('button', 'btn', pd); dlb.type = 'button'; dlb.textContent = 'Download Documents';
          dlb.addEventListener('click', function () {
            function dl1(u8, name) { var a1 = document.createElement('a'); a1.href = URL.createObjectURL(new Blob([u8], { type: 'application/pdf' })); a1.download = name; document.body.appendChild(a1); a1.click(); a1.remove(); }
            dl1(bytes, 'Contract Packet.pdf'); if (draft.disc) { setTimeout(function () { dl1(new Uint8Array(draft.disc), 'Buyer Disclosures.pdf'); }, 600); }
          });
          var em = el('a', 'btn ghost', pd); em.href = 'sign_packet.php?tx=' + encodeURIComponent(cfg.tx) + '&draft=1'; em.textContent = 'Email To Buyers For Signing'; em.style.marginLeft = '8px';
          em.addEventListener('click', function () { em.innerHTML = '<span class="bspin"></span>Opening...'; em.style.pointerEvents = 'none'; });
          res.hidden = false; status.hidden = true; document.querySelectorAll('#esave, #esave2').forEach(function (b) { b.closest('p').hidden = true; });
          var bot = document.getElementById('esave2'); if (bot) { bot.closest('.ebar').hidden = true; }
          try { res.scrollIntoView({ behavior: 'smooth', block: 'start' }); } catch (e6) {}
          return;
        }
        var bin = ''; for (var o = 0; o < bytes.length; o += 32768) { bin += String.fromCharCode.apply(null, bytes.subarray(o, o + 32768)); }
        var r = await fetch(cfg.saveUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ csrf: cfg.csrf, tx: cfg.tx, kind: cfg.kind || 'packet', b64: btoa(bin), property: String(values['Property_Address'] || '').trim() }) });
        var j = await r.json();
        var url = URL.createObjectURL(new Blob([bytes], { type: 'application/pdf' }));
        res.innerHTML = '';
        var p = el('p', j.ok ? '' : 'sigerr', res);
        p.innerHTML = j.ok ? '<strong>Saved</strong> in ' + esc(j.folder) + ' as <a href="' + esc(j.url) + '" target="_blank" rel="noopener">' + esc(j.name) + '</a>.' : esc(j.msg || 'Could not save.') + ' You can still download it.';
        var p2 = el('p', '', res); var a = el('a', 'btn', p2); a.href = url; a.download = 'Contract Packet - completed.pdf'; a.textContent = 'Download';
        if (j.ok) { var b = el('a', 'btn ghost', p2); b.href = 'sign_packet.php?tx=' + encodeURIComponent(cfg.tx); b.textContent = 'Send for signature'; b.style.marginLeft = '8px'; }
        res.hidden = false; status.textContent = j.ok ? 'Saved.' : 'Not saved.';
      } catch (e) { status.textContent = 'Something went wrong: ' + e.message; }
      if (!(draft && draft.completed)) { btns.forEach(function (b) { b.disabled = false; b.textContent = saveBtn.dataset.t || 'Continue For Signing'; }); }
      try { res.scrollIntoView({ behavior: 'smooth', block: 'start' }); } catch (e5) {} // the result is at the top of the page
    };
    saveBtn.addEventListener('click', onSave); if (saveBtn2) saveBtn2.addEventListener('click', onSave);
    function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    root.__editorBuild = build;
  }
  root.FLREditor = { start: start };
})(window);
