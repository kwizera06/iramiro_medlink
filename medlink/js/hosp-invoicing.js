// Hospitalization invoicing (Consultation > Invoicing > Hospitalization).
// window.hospInvoicing.render(el, rows, {admin, esc}) draws the table with Paid / Partial / Unpaid filters and returns the unpaid count.
window.hospInvoicing = (function () {
  const COL = {paid: ['#d1fae5', '#065f46', 'Paid'], covered: ['#d1fae5', '#065f46', 'Paid'], partial: ['#fef3c7', '#92400e', 'Partial'], approved: ['#dbeafe', '#1e40af', 'Approved'], unpaid: ['#fee2e2', '#b91c1c', 'Unpaid']};
  const group = s => (s === 'covered' ? 'paid' : s === 'approved' ? 'unpaid' : s);
  const filters = {};   // remembered per container so a background refresh keeps the chosen filter

  function render(el, rows, o) {
    const esc = o.esc, f = filters[el.id] || 'all';
    const cnt = k => rows.filter(r => k === 'all' || group(r.pay_status) === k).length;
    const chip = (k, l) => '<button type="button" data-f="' + k + '" style="padding:4px 12px;border-radius:14px;border:1px solid ' + (f === k ? 'var(--primary-color)' : 'var(--gray-200)') + ';background:' +
      (f === k ? 'var(--primary-color)' : 'white') + ';color:' + (f === k ? 'white' : 'var(--gray-600)') + ';font-size:12px;font-weight:600;cursor:pointer;margin-right:6px;">' + l + ' (' + cnt(k) + ')</button>';
    const shown = rows.filter(r => f === 'all' || group(r.pay_status) === f);
    el.innerHTML = '<div style="margin-bottom:12px;">' + chip('all', 'All') + chip('paid', 'Paid') + chip('partial', 'Partial') + chip('unpaid', 'Unpaid') + '</div>' +
      (!shown.length ? '<div class="ct-empty">' + (rows.length ? 'No ' + f + ' hospitalization invoices.' : 'No hospitalization invoices for this patient.') + '</div>' :
        '<table><thead><tr><th>Date</th><th>Invoice</th><th>Admission</th><th>Items</th>' + (o.admin ? '<th>Total</th><th>Balance</th>' : '') + '<th>Status</th></tr></thead><tbody>' +
        shown.map(r => {
          const c = COL[r.pay_status] || ['#f3f4f6', '#555', r.pay_status];
          return '<tr><td>' + esc(String(r.created_at || '').substring(0, 16)) + '</td><td><b>' + esc(r.invoice_no) + '</b></td><td>' + esc(r.visit_no) +
            (r.ward ? '<div style="font-size:11px;color:var(--gray-500);">' + esc(r.ward) + '</div>' : '') +
            '<div style="font-size:11px;color:var(--gray-500);">' + esc(String(r.admitted_at || '').substring(0, 10)) + ' → ' + (Number(r.currently_admitted) ? '<b style="color:#b45309;">Currently admitted</b>' : esc(String(r.discharged_at || '—').substring(0, 10))) + '</div></td><td>' +
            (r.items || []).map(x => esc(x.description) + (x.quantity > 1 ? ' ×' + esc(x.quantity) : '')).join('<br>') + '</td>' +
            (o.admin ? '<td>' + Number(r.total).toLocaleString() + '</td><td>' + Number(r.balance).toLocaleString() + '</td>' : '') +
            '<td><span style="padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;background:' + c[0] + ';color:' + c[1] + ';">' + c[2] + '</span></td></tr>';
        }).join('') + '</tbody></table>');
    el.querySelectorAll('button[data-f]').forEach(b => b.onclick = () => { filters[el.id] = b.dataset.f; render(el, rows, o); });
    return rows.filter(r => !['paid', 'covered'].includes(r.pay_status)).length;
  }
  return {render};
})();
