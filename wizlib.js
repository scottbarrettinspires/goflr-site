/* Contract packet builder. Merges fillable PDFs with pdf-lib and keeps the form fields working. */
(function (root) {
  function splitLines(text, font, size, widths) {
    var words = String(text).split(/\s+/).filter(Boolean), lines = [], cur = '', i = 0;
    for (var w = 0; w < words.length; w++) {
      var t = cur ? cur + ' ' + words[w] : words[w];
      if (!cur || font.widthOfTextAtSize(t, size) <= (widths[Math.min(i, widths.length - 1)] - 6)) { cur = t; }
      else { lines.push(cur); i++; cur = words[w]; }
    }
    if (cur) lines.push(cur);
    return lines;
  }

  // Put clause text into the Additional_Terms_1..N lines of one contract (already loaded), without overwriting existing text.
  async function fillAdditionalTerms(PL, doc, clauses) {
    var form = doc.getForm(), font = await doc.embedFont(PL.StandardFonts.Helvetica), fields = [];
    for (var n = 1; n <= 40; n++) {
      var f; try { f = form.getTextField('Additional_Terms_' + n); } catch (e) { break; }
      var w = f.acroField.getWidgets()[0].getRectangle().width;
      fields.push({ f: f, w: w, used: !!(f.getText() || '').trim() });
    }
    var start = 0; while (start < fields.length && fields[start].used) start++;
    var free = fields.slice(start), widths = free.map(function (x) { return x.w; }), lines = [];
    clauses.forEach(function (c) { lines = lines.concat(splitLines(c, font, 10, widths.slice(lines.length))); });
    if (!free.length || lines.length > free.length) return { ok: false, needed: lines.length, available: free.length };
    lines.forEach(function (ln, i) { free[i].f.setText(ln); });
    return { ok: true, lines: lines.length };
  }

  // sources: [{bytes, isContract}], clauses: [text]. Returns merged PDFDocument.
  async function buildPacket(PL, sources, clauses) {
    var out = await PL.PDFDocument.create(), ctx = out.context, roots = new Set(), fontNames = {}, notes = [];
    for (var i = 0; i < sources.length; i++) {
      var src = await PL.PDFDocument.load(sources[i].bytes, { ignoreEncryption: true, updateMetadata: false });
      if (sources[i].isContract && clauses && clauses.length) {
        var r = await fillAdditionalTerms(PL, src, clauses);
        notes.push(r.ok ? 'Additional Terms filled (' + r.lines + ' line' + (r.lines === 1 ? '' : 's') + ').' : 'The Additional Terms lines are full (' + r.needed + ' needed, ' + r.available + ' free); add the clause by hand.');
      }
      var pages = await out.copyPages(src, src.getPageIndices());
      pages.forEach(function (p) {
        out.addPage(p);
        var an = p.node.lookupMaybe(PL.PDFName.of('Annots'), PL.PDFArray);
        if (!an) return;
        for (var j = 0; j < an.size(); j++) {
          var ref = an.get(j), d = an.lookup(j, PL.PDFDict);
          if (!d || String(d.get(PL.PDFName.of('Subtype'))) !== '/Widget') continue;
          var r2 = ref, cur = d;
          for (var g = 0; g < 10; g++) {
            var da = cur.get(PL.PDFName.of('DA')); if (da && da.decodeText) { var m = /\/(\S+)\s+[\d.]+\s+Tf/.exec(da.decodeText()); if (m) fontNames[m[1]] = 1; }
            var par = cur.get(PL.PDFName.of('Parent')); if (!par) break; r2 = par; cur = ctx.lookup(par, PL.PDFDict);
          }
          roots.add(r2);
        }
      });
    }
    var helv = ctx.register(ctx.obj({ Type: 'Font', Subtype: 'Type1', BaseFont: 'Helvetica', Encoding: 'WinAnsiEncoding' }));
    var fonts = { Helv: helv }; Object.keys(fontNames).forEach(function (n) { fonts[n] = helv; });
    var acro = ctx.obj({ Fields: ctx.obj(Array.from(roots)), DR: ctx.obj({ Font: ctx.obj(fonts) }), DA: PL.PDFString.of('/Helv 0 Tf 0 g') });
    out.catalog.set(PL.PDFName.of('AcroForm'), ctx.register(acro));
    // Give every text field a visible appearance (copies of a shared field otherwise look blank until clicked).
    try {
      var of = out.getForm(), ofont = await out.embedFont(PL.StandardFonts.Helvetica);
      of.getFields().forEach(function (f) { if (f instanceof PL.PDFTextField) { try { f.updateAppearances(ofont); } catch (e) {} } });
    } catch (e) { notes.push('Note: field appearances could not be refreshed.'); }
    out.__notes = notes;
    return out;
  }

  root.FLRWiz = { buildPacket: buildPacket, splitLines: splitLines };
  if (typeof module !== 'undefined') module.exports = root.FLRWiz;
})(typeof window !== 'undefined' ? window : globalThis);
