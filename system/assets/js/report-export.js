// Shared report export helpers: print preview, PDF (html2pdf), Excel (SheetJS on demand), toolbar.
(function () {
  var hs = null;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function hospital() {
    if (hs) return Promise.resolve(hs);
    return fetch('api.php?endpoint=settings/hospital', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) { hs = (j && j.success && j.data) || {}; return hs; })
      .catch(function () { hs = {}; return hs; });
  }
  function head(h, title) {
    var logo = h.doc_logo || h.logo || '';
    return '<div style="display:flex;align-items:center;gap:12px;border-bottom:2px solid ' + esc(h.header_line_color || '#059669') + ';padding-bottom:8px;margin-bottom:12px;">' +
      (logo ? '<img src="' + esc(logo) + '" style="height:48px;">' : '') +
      '<div><div style="font-size:18px;font-weight:700;">' + esc(h.hospital_name || 'MedLink') + '</div>' +
      '<div style="font-size:15px;font-weight:600;">' + esc(title || '') + '</div>' +
      '<div style="font-size:11px;color:#64748b;">Printed ' + new Date().toLocaleString() + '</div></div></div>';
  }
  // Clone element, drop controls (.no-export) and inputs so the copy is print-clean
  function body(id) {
    var el = document.getElementById(id);
    if (!el) return '';
    var c = el.cloneNode(true);
    var src = el.querySelectorAll('canvas'); // canvases do not clone: swap for snapshots
    c.querySelectorAll('canvas').forEach(function (cv, i) {
      try { var im = document.createElement('img'); im.src = src[i].toDataURL(); im.style.maxWidth = '100%'; cv.replaceWith(im); } catch (e) { cv.remove(); }
    });
    c.querySelectorAll('.no-export,button,.pagination,#rpt-pagination').forEach(function (n) { n.remove(); });
    return c.innerHTML;
  }
  var CSS = 'body{font-family:Arial,sans-serif;font-size:12px;margin:16px;}table{width:100%;border-collapse:collapse;}th,td{padding:5px 7px;border:1px solid #ddd;text-align:left;}td{overflow-wrap:anywhere;word-break:break-all;}th{background:#f1f5f9;}tr{break-inside:avoid;}@page{size:A4 landscape;margin:10mm;}';

  window.rptPrintPreview = function (elementId, title, autoPrint) {
    var win = window.open('', '', 'width=1100,height=750');
    if (!win) { alert('Allow pop-ups to preview.'); return; }
    hospital().then(function (h) {
      win.document.write('<html><head><title>' + esc(title) + '</title><style>' + CSS +
        '.bar{position:sticky;top:0;background:#1e293b;padding:8px 16px;margin:-16px -16px 12px;display:flex;gap:8px;}.bar button{padding:6px 14px;border:0;border-radius:6px;cursor:pointer;font-size:13px;}@media print{.bar{display:none}}</style></head><body>' +
        '<div class="bar"><button onclick="window.print()">Print</button><button onclick="window.opener&&window.opener.rptExportPdf(' + JSON.stringify(elementId) + ',' + JSON.stringify(title) + ')">Save PDF</button><button onclick="window.close()">Close</button></div>' +
        head(h, title) + body(elementId) + '</body></html>');
      win.document.close();
      if (autoPrint) setTimeout(function () { win.print(); }, 400);
    });
  };

  // html2pdf cannot keep table rows whole across pages (margins on <tr> are ignored), so a row on a page
  // boundary gets cut in half. Measure at the real page width and insert a blank spacer row before any
  // row that would straddle a boundary, pushing it onto the next page.
  function keepRowsWhole(d, ratio) {
    var rows = Array.prototype.slice.call(d.querySelectorAll('table > tbody > tr'));
    var pageH = d.getBoundingClientRect().width * ratio, top0 = d.getBoundingClientRect().top;
    var pos = rows.map(function (tr) { var r = tr.getBoundingClientRect(); return [r.top - top0, r.height]; });
    var shift = 0;
    rows.forEach(function (tr, i) {
      var h = pos[i][1]; if (!h || h >= pageH) return;
      var top = pos[i][0] + shift, page = Math.floor(top / pageH);
      if (Math.floor((top + h - 0.5) / pageH) > page) {
        var gap = (page + 1) * pageH - top + 1;
        var sp = document.createElement('tr');
        sp.innerHTML = '<td colspan="99" style="height:' + gap + 'px;padding:0;border:0;"></td>';
        tr.parentNode.insertBefore(sp, tr);
        shift += gap;
      }
    });
  }

  window.rptExportPdf = function (elementId, title, filename) {
    if (typeof html2pdf === 'undefined') { alert('PDF library not loaded.'); return; }
    hospital().then(function (h) {
      var d = document.createElement('div');
      d.style.cssText = 'font-family:Arial,sans-serif;font-size:11px;padding:8px;background:#fff;color:#000;';
      d.innerHTML = '<style>' + CSS.replace(/body\{[^}]*\}/, '').replace(/@page\{[^}]*\}/, '') + '</style>' + head(h, title) + body(elementId);
      var wide = d.querySelectorAll('th').length > 6;
      var W = wide ? 281 : 194, H = wide ? 194 : 281; // inner page size in mm (A4 minus 8mm margins)
      d.style.cssText += ';box-sizing:border-box;width:' + W + 'mm;position:absolute;left:-10000px;top:0;';
      document.body.appendChild(d);
      try { keepRowsWhole(d, H / W); } finally { document.body.removeChild(d); d.style.position = ''; d.style.left = ''; d.style.top = ''; }
      html2pdf().set({
        margin: 8, filename: (filename || title || 'report').replace(/[^\w\-]+/g, '_') + '.pdf',
        html2canvas: { scale: 2, scrollX: 0, scrollY: 0 }, jsPDF: { unit: 'mm', format: 'a4', orientation: wide ? 'landscape' : 'portrait' },
        pagebreak: { mode: ['css', 'legacy'], avoid: 'tr' }
      }).from(d).save();
    });
  };

  var xlsxP = null;
  function loadXlsx() {
    if (window.XLSX) return Promise.resolve();
    if (!xlsxP) xlsxP = new Promise(function (ok, fail) {
      var s = document.createElement('script');
      s.src = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
      s.onload = ok; s.onerror = function () { xlsxP = null; fail(); };
      document.head.appendChild(s);
    });
    return xlsxP;
  }
  window.rptExportXlsx = function (tableId, filename) {
    var t = document.getElementById(tableId);
    if (!t) return;
    if (t.tagName !== 'TABLE') t = t.querySelector('table');
    if (!t) { alert('Nothing to export.'); return; }
    loadXlsx().then(function () {
      var wb = XLSX.utils.table_to_book(t, { raw: true });
      XLSX.writeFile(wb, (filename || 'report').replace(/[^\w\-]+/g, '_') + '.xlsx');
    }, function () { alert('Excel library could not be loaded.'); });
  };

  // opts: {elementId, tableId, title, filename}
  window.rptToolbar = function (containerId, opts) {
    var c = document.getElementById(containerId);
    if (!c) return;
    var st = 'padding:8px 12px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:13px;transition:transform .15s ease-out;';
    var fn = opts.filename || opts.title;
    c.className = (c.className + ' no-export').trim();
    c.innerHTML = '';
    function add(icon, label, fnc) {
      var b = document.createElement('button');
      b.type = 'button'; b.style.cssText = st; b.className = 'rpt-xbtn';
      b.innerHTML = '<i class="fas ' + icon + '"></i> ' + label;
      b.onclick = fnc; c.appendChild(b);
    }
    add('fa-eye', 'Preview', function () { rptPrintPreview(opts.elementId, opts.title); });
    add('fa-print', 'Print', function () { rptPrintPreview(opts.elementId, opts.title, true); });
    add('fa-file-pdf', 'PDF', function () { rptExportPdf(opts.elementId, opts.title, fn); });
    if (opts.tableId) add('fa-file-excel', 'Excel', function () { rptExportXlsx(opts.tableId, fn); });
    if (!document.getElementById('rpt-xcss')) {
      var s = document.createElement('style'); s.id = 'rpt-xcss';
      s.textContent = '.rpt-xbtn:active{transform:scale(.97)}.rpt-xbtn{margin-left:4px}@media (prefers-reduced-motion:reduce){.rpt-xbtn{transition:none!important}}';
      document.head.appendChild(s);
    }
  };
})();
