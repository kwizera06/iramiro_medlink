<?php
// includes/footer.php - Simple version
?>
        </main>

        <footer style="
            background: white;
            border-top: 1px solid var(--gray-200);
            padding: 16px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        ">
            <div style="color: var(--gray-500); font-size: 14px;">
                &copy; <?php echo date('Y'); ?> Hospital ERP System. All rights reserved.
            </div>
            <div style="display: flex; gap: 20px; align-items: center;">
                <span style="color: var(--gray-400); font-size: 13px;">
                    <i class="fas fa-clock"></i> 
                    <?php echo date('H:i:s'); ?>
                </span>
                <span style="color: var(--gray-400); font-size: 13px;">
                    <i class="fas fa-server"></i> 
                    v1.0.0
                </span>
                <a href="#" style="color: var(--gray-400); text-decoration: none; font-size: 13px;">
                    <i class="fas fa-life-ring"></i> Help
                </a>
            </div>
        </footer>
    </div>
    <?php
    global $pdo;
    require_once __DIR__ . '/page_perms.php';
    $ppRow = isset($pdo) ? pp_page_row($pdo) : null;
    if ($ppRow): ?>
    <script>
    // Per-user page limits (Settings > Roles > Individual Role Management): hide buttons the user may not use.
    // ponytail: matches buttons by icon / label / handler name; server still enforces add/edit/delete.
    (function(){
      const p = <?php echo json_encode(['view'=>(int)$ppRow['can_view'],'add'=>(int)$ppRow['can_add'],'edit'=>(int)$ppRow['can_edit'],'delete'=>(int)$ppRow['can_delete'],'download'=>(int)$ppRow['can_download']]); ?>;
      const rules = {
        edit:     /fa-(edit|pen|user-edit|pencil)|\bedit/i,
        delete:   /fa-(trash|times-circle)|delet|remov/i,
        add:      /fa-(plus|user-plus)|^\s*(\+|add|new|register|create)\b/i,
        download: /fa-(download|file-excel|file-pdf|print)|export|download|print/i,
        view:     /fa-eye\b/i
      };
      function sig(b){ return (b.innerHTML.match(/class="[^"]*"/g)||[]).join(' ') + ' ' + (b.getAttribute('title')||'') + ' ' + (b.getAttribute('onclick')||'') + ' ' + b.textContent.trim(); }
      function apply(root){
        (root.querySelectorAll ? root.querySelectorAll('button,a.btn,[role=button]') : []).forEach(b=>{
          if(b.closest('#sidebar,nav,.modal-close') || b.dataset.ppDone) return;
          b.dataset.ppDone = 1;
          const s = sig(b);
          for(const k in rules) if(!p[k] && rules[k].test(s)){ b.style.display='none'; break; }
        });
      }
      apply(document);
      new MutationObserver(m=>m.forEach(r=>r.addedNodes.forEach(n=>n.nodeType===1&&apply(n)))).observe(document.body,{childList:true,subtree:true});
    })();
    </script>
    <?php endif; ?>
    <script>
    // Phone numbers: digits only, everywhere (type=tel, or any input whose id/name says phone / contact / mobile / tel).
    // Delegated on document, so modals built later are covered too. The server also strips non-digits.
    (function(){
      const isPhone = el => el && el.tagName === 'INPUT' && (el.type === 'tel' || /phone|mobile|emergency_contact|(^|[-_])tel([-_]|$)/i.test((el.id||'') + ' ' + (el.name||'')));
      document.addEventListener('input', e => { const el = e.target; if(isPhone(el) && /\D/.test(el.value)) el.value = el.value.replace(/\D/g, ''); }, true);
      const fix = root => root.querySelectorAll && root.querySelectorAll('input').forEach(el => { if(isPhone(el)){ el.setAttribute('inputmode','numeric'); if(/^\+/.test(el.placeholder||'')) el.placeholder = '07xxxxxxxx'; } });
      fix(document);
      new MutationObserver(m => m.forEach(r => r.addedNodes.forEach(n => n.nodeType === 1 && fix(n)))).observe(document.body, {childList:true, subtree:true});
    })();
    </script>
<?php
// Beep for doctors (lab result ready) on pages that have no notification poller of their own (lab.php has one)
$__nr = array_map('strtolower', array_merge((array)($_SESSION['user_data']['role_slugs'] ?? []), [(string)($_SESSION['user_data']['role'] ?? ''), (string)($_SESSION['user_role'] ?? '')]));
$__nrole = in_array('doctor', $__nr, true) ? 'doctor' : (array_intersect(['lab', 'lab_tech', 'laboratory'], $__nr) ? 'lab' : '');
if ($__nrole): ?>
<script>
(function(role){
  if(typeof notifPoll==='function') return; // page has its own bell + sound
  const api=window.location.origin+<?php echo json_encode(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/')); ?>+'/api.php?endpoint=notifications&target_role='+role+'&is_read=0&limit=20';
  const key='medlink_beep_'+role; let ctx=null, pending=false;
  const muted=()=>localStorage.getItem('medlink_notif_muted')==='1';
  function tone(f,at,d){ const o=ctx.createOscillator(), g=ctx.createGain(); o.frequency.value=f; g.gain.value=0.35; o.connect(g); g.connect(ctx.destination); o.start(ctx.currentTime+at); g.gain.setValueAtTime(0.35,ctx.currentTime+at+d-0.05); g.gain.linearRampToValueAtTime(0,ctx.currentTime+at+d); o.stop(ctx.currentTime+at+d); }
  function beep(){ if(muted()) return; if(!ctx||ctx.state!=='running'){ pending=true; return; } pending=false; [880,1100,880,1100].forEach((f,i)=>tone(f,i*0.25,0.2)); }
  // browsers only allow sound after a click/key: unlock on the first one, then play any beep that was waiting
  ['click','keydown','touchstart'].forEach(e=>document.addEventListener(e,()=>{ ctx=ctx||new (window.AudioContext||window.webkitAudioContext)(); ctx.resume().then(()=>{ if(pending) beep(); }); },{once:true}));
  async function poll(){
    try{
      const j=await (await fetch(api)).json(); if(!j.success) return;
      const max=Math.max(0,...(j.data.notifications||[]).map(n=>+n.id));
      const last=localStorage.getItem(key);
      if(last===null){ localStorage.setItem(key,max); return; } // first run: baseline, no beep for old items
      if(max>+last){ localStorage.setItem(key,max); beep(); }
    }catch(e){}
  }
  poll(); setInterval(poll,15000);
})(<?php echo json_encode($__nrole); ?>);
</script>
<?php endif; ?>
</body>
</html>