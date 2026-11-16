<?php
// signature.php — "My Signature" (#34): draw once, used on certificates and other printed documents.
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/signature.php';
sig_schema($pdo);
$mySig = get_user_signature($pdo, (int)$_SESSION['user_id']);
?>
<style>
  #sigPad { touch-action: none; cursor: crosshair; width: 100%; height: auto; display: block; background: #fff; border: 1px solid var(--gray-200); border-radius: 8px; }
  .sig-btn { padding: 9px 18px; border-radius: 8px; font-weight: 600; font-size: 13px; cursor: pointer; border: 1px solid var(--gray-200); background: white; color: var(--gray-700); transition: transform .12s ease-out, background-color .15s ease-out; }
  .sig-btn:active { transform: scale(.97); }
  .sig-btn.primary { background: var(--primary-color); border-color: var(--primary-color); color: white; }
  .sig-btn:disabled { opacity: .5; cursor: not-allowed; }
  @media (prefers-reduced-motion: reduce) { .sig-btn { transition: none; } .sig-btn:active { transform: none; } }
</style>
<div style="max-width:760px;margin:0 auto;">
  <div style="margin-bottom:20px;">
    <h2 style="font-size:24px;font-weight:600;"><i class="fas fa-signature" style="color:var(--primary-color);"></i> My Signature</h2>
    <p style="color:var(--gray-500);font-size:14px;margin-top:4px;">Draw your signature once. It is printed with your name on the certificates and documents you issue.</p>
  </div>

  <div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);margin-bottom:20px;">
    <h3 style="font-size:15px;font-weight:600;margin-bottom:12px;">Draw your signature</h3>
    <canvas id="sigPad" width="900" height="300"></canvas>
    <p style="font-size:12px;color:var(--gray-400);margin:6px 0 14px;">Sign inside the box with your mouse, finger or stylus.</p>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <button class="sig-btn" type="button" onclick="sigClear()"><i class="fas fa-eraser"></i> Clear / redraw</button>
      <button class="sig-btn primary" type="button" id="sigSave" onclick="sigSave()"><i class="fas fa-save"></i> Save signature</button>
    </div>
  </div>

  <div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
    <h3 style="font-size:15px;font-weight:600;margin-bottom:12px;">Current signature</h3>
    <div id="sigCurrent">
<?php if ($mySig): ?>
      <img id="sigCurrentImg" src="signature-file.php?action=img&amp;t=<?= urlencode((string)$mySig['updated_at']) ?>" alt="Your saved signature" style="max-height:110px;border:1px dashed var(--gray-200);border-radius:6px;padding:8px;background:white;">
      <p style="font-size:12px;color:var(--gray-500);margin-top:8px;">Saved <?= htmlspecialchars((string)$mySig['updated_at']) ?></p>
      <button class="sig-btn" type="button" style="margin-top:8px;color:#b91c1c;" onclick="sigRemove()"><i class="fas fa-trash"></i> Remove</button>
<?php else: ?>
      <p style="color:var(--gray-400);font-size:13px;">No signature saved yet.</p>
<?php endif; ?>
    </div>
  </div>
</div>
<script>
(function () {
  const c = document.getElementById('sigPad'), ctx = c.getContext('2d');
  let painting = false, drawn = false;
  ctx.lineWidth = 3; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#111';
  function pos(e) {   // canvas has a fixed pixel size; scale from the on-screen box
    const r = c.getBoundingClientRect();
    return [(e.clientX - r.left) * c.width / r.width, (e.clientY - r.top) * c.height / r.height];
  }
  c.addEventListener('pointerdown', e => { painting = true; c.setPointerCapture(e.pointerId); const [x, y] = pos(e); ctx.beginPath(); ctx.moveTo(x, y); ctx.lineTo(x + .01, y); ctx.stroke(); drawn = true; });
  c.addEventListener('pointermove', e => { if (!painting) return; const [x, y] = pos(e); ctx.lineTo(x, y); ctx.stroke(); ctx.beginPath(); ctx.moveTo(x, y); });
  ['pointerup', 'pointercancel'].forEach(t => c.addEventListener(t, () => { painting = false; ctx.beginPath(); }));
  window.sigClear = () => { ctx.clearRect(0, 0, c.width, c.height); drawn = false; };
  async function post(action, body) {
    const r = await fetch('signature-file.php?action=' + action, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.medlinkCsrfToken || '' }, body: JSON.stringify(body || {}) });
    return r.json();
  }
  window.sigSave = async () => {
    if (!drawn) { alert('Draw your signature first.'); return; }
    const b = document.getElementById('sigSave'); b.disabled = true;
    try { const j = await post('save', { data_url: c.toDataURL('image/png') }); if (!j.success) throw new Error(j.message); location.reload(); }
    catch (e) { alert('Could not save: ' + e.message); b.disabled = false; }
  };
  window.sigRemove = async () => {
    if (!confirm('Remove your saved signature? Certificates already signed keep theirs.')) return;
    const j = await post('clear'); if (j.success) location.reload(); else alert(j.message);
  };
})();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
