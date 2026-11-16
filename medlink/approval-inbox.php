<?php
// approvals.php — one inbox for what the signed-in user has to approve. The admin sees everything; every other role sees the approvals that belong to it (purchases, assets, expenses, requests, stock, leave), even when the same approval also has a button in its own module.
// Data and rules: handlers/approvals/approvals.php + includes/approvals.php. The View button opens every detail before the decision.
require_once __DIR__ . '/includes/header.php';
?>
<style>
.ap-card{padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;}
.ap-card b{display:block;font-size:22px;font-weight:700;} .ap-card span{font-size:12px;color:var(--gray-500);}
.ap-box{background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;margin-bottom:18px;}
.ap-tbl{width:100%;border-collapse:collapse;font-size:13px;} .ap-tbl th{padding:10px 12px;text-align:left;background:var(--gray-50);border-bottom:2px solid var(--gray-200);white-space:nowrap;} .ap-tbl td{padding:10px 12px;border-bottom:1px solid var(--gray-100);vertical-align:top;}
.ap-btn{padding:7px 14px;border:none;border-radius:6px;font-size:13px;font-weight:500;cursor:pointer;transition:transform 140ms ease-out;white-space:nowrap;} .ap-btn:active{transform:scale(0.97);} .ap-btn:disabled{opacity:.5;cursor:not-allowed;}
.ap-pill{display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600;white-space:nowrap;}
.ap-seg{display:inline-flex;background:var(--gray-100);border-radius:8px;padding:3px;gap:2px;} .ap-seg button{padding:7px 14px;border:none;background:transparent;border-radius:6px;font-size:13px;font-weight:500;color:var(--gray-600);cursor:pointer;transition:background 140ms ease-out;} .ap-seg button.on{background:white;color:var(--gray-900);box-shadow:var(--shadow);}
.ap-ov{position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;padding:16px;}
.ap-dlg{background:white;border-radius:12px;padding:22px;width:680px;max-width:100%;max-height:90vh;overflow-y:auto;}
.ap-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px 18px;margin-bottom:14px;}
.ap-grid dt{font-size:11px;font-weight:600;color:var(--gray-500);text-transform:uppercase;letter-spacing:.03em;} .ap-grid dd{margin:2px 0 0;font-size:13px;word-break:break-word;}
.ap-sec{font-size:13px;font-weight:600;margin:14px 0 8px;padding-top:12px;border-top:1px solid var(--gray-100);}
.ap-err{display:none;margin:0 0 12px;padding:10px 14px;border-radius:8px;font-size:13px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;}
</style>

<div style="margin-bottom:18px;">
  <h2 style="font-size:24px;font-weight:600;">Approvals</h2>
  <p style="color:var(--gray-500);font-size:14px;margin-top:4px;">Everything that waits for your decision. Open a request with View to read all the details, then approve or reject it.</p>
</div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:16px;">
  <div class="ap-card"><b id="ap-s-wait" style="color:#92400e;">—</b><span>Waiting for you</span></div>
  <div class="ap-card"><b id="ap-s-urg" style="color:#b91c1c;">—</b><span>Urgent / emergency</span></div>
</div>
<div class="ap-box">
  <div style="padding:14px 16px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
    <div class="ap-seg" id="ap-seg"><button class="on" data-v="pending" onclick="apView('pending')">Pending</button><button data-v="decided" onclick="apView('decided')">Decided (30 days)</button></div>
    <select id="ap-kind" onchange="apRender()" style="margin-left:auto;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"><option value="">All types</option></select>
    <input id="ap-search" placeholder="Search subject or reference" style="width:240px;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;" oninput="apRender()">
  </div>
  <table class="ap-tbl"><thead><tr><th>Reference</th><th>Type</th><th>Subject</th><th>Details</th><th>Urgency</th><th>Requested by</th><th id="ap-th-when">Requested</th><th></th></tr></thead>
  <tbody id="ap-body"><tr><td colspan="8" style="padding:30px;text-align:center;color:var(--gray-400);">Loading…</td></tr></tbody></table>
</div>

<script>
const apBase=(typeof window.appBasePath!=='undefined'&&window.appBasePath!==null)?window.appBasePath:(window.location.pathname.substring(0,window.location.pathname.lastIndexOf('/'))||'');
const apUrl=q=>apBase+'/api.php?endpoint='+q;
const AP=apUrl('approvals/approvals&action=');
const apEsc=s=>String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
const apGet=async u=>{try{return await (await fetch(u)).json();}catch(e){return {success:false,message:'Network error'};}};
const apSend=async(u,m,b)=>{try{return await (await fetch(u,{method:m,headers:{'Content-Type':'application/json'},body:JSON.stringify(b)})).json();}catch(e){return {success:false,message:'Network error'};}};
const apDate=d=>d?new Date(String(d).replace(' ','T')).toLocaleString('en-GB',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}):'—';
const apUrg={routine:['#e5e7eb','#374151'],normal:['#e5e7eb','#374151'],low:['#e5e7eb','#374151'],medium:['#e5e7eb','#374151'],high:['#fef3c7','#92400e'],urgent:['#fef3c7','#92400e'],emergency:['#fee2e2','#991b1b'],critical:['#fee2e2','#991b1b']};
const apSt={draft:['#fef3c7','#92400e'],requested:['#fef3c7','#92400e'],pending:['#fef3c7','#92400e'],submitted:['#fef3c7','#92400e'],awaiting_approval:['#fef3c7','#92400e'],approved:['#d1fae5','#065f46'],completed:['#d1fae5','#065f46'],rejected:['#fee2e2','#991b1b'],cancelled:['#e5e7eb','#374151']};
const apPill=(t,m)=>{if(!t)return '';const c=m[String(t).toLowerCase()]||['#e5e7eb','#374151'];return `<span class="ap-pill" style="background:${c[0]};color:${c[1]};">${apEsc(String(t).replace(/_/g,' '))}</span>`;};
let apRows=[], apCur='pending', apCurItem=null;
function apClose(){document.querySelectorAll('.ap-ov').forEach(o=>o.remove());}
document.addEventListener('keydown',e=>{if(e.key==='Escape')apClose();});
function apDialog(html){apClose();const o=document.createElement('div');o.className='ap-ov';o.onclick=e=>{if(e.target===o)apClose();};o.innerHTML='<div class="ap-dlg">'+html+'</div>';document.body.appendChild(o);}
function apView(v){apCur=v;document.querySelectorAll('#ap-seg button').forEach(b=>b.classList.toggle('on',b.dataset.v===v));document.getElementById('ap-th-when').textContent=v==='pending'?'Requested':'Decided';apLoad();}
function apRender(){
  const q=document.getElementById('ap-search').value.trim().toLowerCase(), k=document.getElementById('ap-kind').value, pend=apCur==='pending';
  const rows=apRows.filter(r=>(!k||r.kind===k)&&(!q||(r.subject+' '+r.ref+' '+r.summary).toLowerCase().includes(q)));
  document.getElementById('ap-body').innerHTML=rows.length?rows.map(r=>`<tr>
    <td style="font-family:monospace;">${apEsc(r.ref)}</td><td>${apEsc(r.type)}</td><td style="font-weight:600;">${apEsc(r.subject)}</td><td>${apEsc(r.summary)}</td>
    <td>${apPill(r.priority,apUrg)}</td><td>${apEsc(r.requested_by||'—')}</td>
    <td style="white-space:nowrap;">${pend?apDate(r.requested_at):apDate(r.decided_at)+'<div style="font-size:11px;color:var(--gray-500);">'+apEsc(r.decided_by||'')+'</div><div>'+apPill(r.status,apSt)+'</div>'}</td>
    <td style="text-align:right;"><button class="ap-btn" style="background:var(--primary-color);color:white;" onclick="apOpen('${r.kind}',${r.id})"><i class="fas fa-eye"></i> View</button></td></tr>`).join('')
    :`<tr><td colspan="8" style="padding:50px;text-align:center;color:var(--gray-400);"><i class="fas fa-check-circle" style="font-size:30px;display:block;margin-bottom:8px;"></i>${pend?'Nothing is waiting for your approval.':'No decisions in the last 30 days.'}</td></tr>`;
  if(pend){document.getElementById('ap-s-wait').textContent=apRows.length;document.getElementById('ap-s-urg').textContent=apRows.filter(r=>['urgent','emergency','high','critical'].includes(String(r.priority).toLowerCase())).length;}
}
async function apLoad(){
  const j=await apGet(AP+'list&view='+apCur);
  if(!j.success){document.getElementById('ap-body').innerHTML=`<tr><td colspan="8" style="padding:30px;text-align:center;color:#b91c1c;">${apEsc(j.message)}</td></tr>`;return;}
  apRows=j.data.rows;
  if(apCur==='pending'&&window.apprBadgeRefresh) window.apprBadgeRefresh();
  const sel=document.getElementById('ap-kind'), cur=sel.value;
  sel.innerHTML='<option value="">All types</option>'+Object.entries(j.data.kinds).map(([k,l])=>`<option value="${k}">${apEsc(l)} (${apRows.filter(r=>r.kind===k).length})</option>`).join('');
  sel.value=cur; apRender();
}
const apDd=(k,v)=>`<div><dt>${apEsc(k)}</dt><dd>${v===null||v===undefined||v===''?'—':apEsc(v)}</dd></div>`;
async function apOpen(kind,id){
  const j=await apGet(AP+'detail&kind='+kind+'&id='+id);
  if(!j.success){alert(j.message);return;}
  const t=j.data; apCurItem=t;
  const secs=Object.entries(t.sections).map(([title,f],i)=>`<div class="ap-sec" ${i?'':'style="border:0;margin-top:0;padding-top:0;"'}>${apEsc(title)}</div><dl class="ap-grid">${Object.entries(f).map(([k,v])=>apDd(k,v)).join('')}</dl>`).join('');
  const lines=(t.lines||[]).length?`<div class="ap-sec">${apEsc(t.lines_title||'Items')}</div><ul style="margin:0 0 6px 18px;font-size:13px;line-height:1.7;">${t.lines.map(l=>`<li>${apEsc(l)}</li>`).join('')}</ul>`:'';
  apDialog(`<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:12px;">
      <div><div style="font-size:12px;color:var(--gray-500);">${apEsc(t.type)}</div><h3 style="font-size:18px;font-weight:600;">${apEsc(t.ref)} · ${apEsc(t.subject)}</h3><div style="margin-top:4px;">${apPill(t.priority,apUrg)} ${apPill(t.status,apSt)}</div></div>
      <button class="ap-btn" onclick="apClose()" style="background:white;border:1px solid var(--gray-200);">Close</button></div>
    <div id="ap-err" class="ap-err"></div>
    ${secs}${lines}
    <div class="ap-sec">Request</div>
    <dl class="ap-grid">${apDd('Requested by',t.requested_by)}${apDd('Requested at',apDate(t.requested_at))}${t.can_decide?'':apDd('Decided by',t.decided_by)+apDd('Decided at',apDate(t.decided_at))}</dl>
    ${t.can_decide?`<div class="ap-sec">Your decision</div>
      <textarea id="ap-note" rows="2" placeholder="Note (required when rejecting)" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;resize:vertical;"></textarea>
      <div style="display:flex;gap:10px;margin-top:12px;">
        <button class="ap-btn" id="ap-yes" onclick="apDecide('approve')" style="flex:1;background:#16a34a;color:white;font-weight:600;"><i class="fas fa-check"></i> Approve</button>
        <button class="ap-btn" id="ap-no" onclick="apDecide('reject')" style="flex:1;background:#dc2626;color:white;font-weight:600;"><i class="fas fa-times"></i> Reject</button></div>`:''}`);
}
// The decision goes to the module that owns the request, so its own rules (stages, ledger posting, separation of duties) still apply
async function apDecide(which){
  const t=apCurItem, d=t.decide[which], note=document.getElementById('ap-note').value.trim(), err=document.getElementById('ap-err');
  if(which==='reject'&&!note){err.textContent='Give a reason for rejecting.';err.style.display='block';return;}
  if(which==='reject'&&!confirm('Reject this request?')) return;
  const yes=document.getElementById('ap-yes'), no=document.getElementById('ap-no'); yes.disabled=no.disabled=true; err.style.display='none';
  const body=Object.assign({},d.body); if(note) body[t.decide.note_key]=note;
  const j=await apSend(apUrl(d.query),d.method,body);
  if(!j.success){err.textContent=j.message||'Failed';err.style.display='block';yes.disabled=no.disabled=false;return;}
  apClose(); apLoad();
  if(window.apprBadgeRefresh) window.apprBadgeRefresh();
}
apLoad();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
