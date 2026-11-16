// roles-access.js — Roles, Page Access, User Overrides & Menu Management
const _raBasePath = (typeof _apiBase !== 'undefined' && _apiBase)
  ? _apiBase.replace('/api.php', '')
  : (window.location.origin + (window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')) || ''));
const raRoleAPI = (typeof _apiBase !== 'undefined' && _apiBase) ? _apiBase + '?endpoint=settings/roles' : _raBasePath + '/api.php?endpoint=settings/roles';
const raMenuAPI = (typeof _apiBase !== 'undefined' && _apiBase) ? _apiBase + '?endpoint=settings/menus' : _raBasePath + '/api.php?endpoint=settings/menus';
const raUserAPI = (typeof _apiBase !== 'undefined' && _apiBase) ? _apiBase + '?endpoint=settings/users' : _raBasePath + '/api.php?endpoint=settings/users';

let rolePage=1, roleSearch='';
function rEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

function raTab(t){
  ['roles','access','user_access','menus','indiv'].forEach(x=>{
    const panel = document.getElementById('ra-panel-'+x);
    if(panel) panel.style.display = x===t ? 'block' : 'none';
    const btn = document.getElementById('ra-tab-'+x);
    if(btn) {
      btn.style.background = x===t ? 'var(--primary-color)' : 'transparent';
      btn.style.color = x===t ? 'white' : 'var(--gray-600)';
    }
  });
  if(t==='roles') roleLoad();
  if(t==='access') accessInit();
  if(t==='user_access') userAccessInit();
  if(t==='menus') menuLoad();
  if(t==='indiv') indivInit();
}

// ==================== INDIVIDUAL ROLE MANAGEMENT ====================
const INDIV_FLAGS=['view','add','edit','delete','download'];
let indivUserId=0,indivPerms=[];

async function indivInit(){
  const sel=document.getElementById('indiv-role-select'),cur=sel.value;
  const j=await (await fetch(raRoleAPI+'&limit=100')).json();
  if(j.success) sel.innerHTML='<option value="">— Select Role —</option>'+(j.data.roles||j.data).map(r=>`<option value="${r.id}" ${r.id==cur?'selected':''}>${rEsc(r.name)}</option>`).join('');
  if(cur) indivLoad();
}

async function indivLoad(){
  const rid=document.getElementById('indiv-role-select').value,tb=document.getElementById('indiv-tbody');
  if(!rid){tb.innerHTML='<tr><td colspan="4" style="padding:40px;text-align:center;color:var(--gray-400);">Select a role above</td></tr>';return;}
  const j=await (await fetch(raMenuAPI+'&entity=role_users&role_id='+rid)).json();
  if(!j.success||!j.data.length){tb.innerHTML='<tr><td colspan="4" style="padding:40px;text-align:center;color:var(--gray-400);">No users in this role.</td></tr>';return;}
  tb.innerHTML=j.data.map(u=>{
    const perm=u.restrictions.length
      ? u.restrictions.map(p=>{const ok=INDIV_FLAGS.filter(f=>p['can_'+f]==1);
          return `<div style="margin-bottom:3px;"><strong>${rEsc(p.title)}:</strong> ${ok.length?ok.join(', '):'no access'}</div>`;}).join('')
      : '<span style="color:var(--gray-400);">Full access (no limits)</span>';
    return `<tr style="border-bottom:1px solid var(--gray-100);">
      <td style="padding:12px 16px;text-align:center;"><button onclick="indivEdit(${u.id},'${rEsc(u.name).replace(/'/g,"\\'")}')" style="background:none;border:none;color:var(--primary-color);cursor:pointer;font-size:15px;"><i class="fas fa-edit"></i></button></td>
      <td style="padding:12px 16px;font-weight:600;">${rEsc(u.name)}<div style="font-size:12px;color:var(--gray-500);font-weight:400;">${rEsc(u.username)}</div></td>
      <td style="padding:12px 16px;">${rEsc(u.role_name)}</td>
      <td style="padding:12px 16px;font-size:13px;">${perm}</td></tr>`;
  }).join('');
}

async function indivEdit(uid,name){
  indivUserId=uid;
  document.getElementById('indiv-name').textContent=name;
  document.getElementById('indiv-body').innerHTML='<p style="padding:30px;text-align:center;"><i class="fas fa-spinner fa-spin"></i></p>';
  document.getElementById('indiv-modal').style.display='flex';
  const j=await (await fetch(raMenuAPI+'&entity=user_perms&user_id='+uid)).json();
  indivPerms=j.success?j.data:[];
  const th='padding:8px 10px;text-align:center;font-size:12px;text-transform:uppercase;';
  document.getElementById('indiv-body').innerHTML=`<table style="width:100%;border-collapse:collapse;font-size:13px;">
    <thead style="position:sticky;top:0;background:var(--gray-50);"><tr><th style="${th}text-align:left;">Module</th>${INDIV_FLAGS.map(f=>`<th style="${th}">${f}</th>`).join('')}</tr></thead>
    <tbody>${indivPerms.map((p,i)=>`<tr style="border-top:1px solid var(--gray-100);">
      <td style="padding:8px 10px;">${rEsc(p.title)}<div style="font-size:11px;color:var(--gray-400);">${rEsc(p.menu_title)}</div></td>
      ${INDIV_FLAGS.map(f=>`<td style="text-align:center;"><input type="checkbox" data-i="${i}" data-f="${f}" ${p['can_'+f]==1?'checked':''}></td>`).join('')}</tr>`).join('')}</tbody></table>`;
}

async function indivSave(){
  const perms=indivPerms.map(p=>({menu_item_id:p.id}));
  document.querySelectorAll('#indiv-body input[type=checkbox]').forEach(cb=>{perms[cb.dataset.i][cb.dataset.f]=cb.checked;});
  const j=await (await fetch(raMenuAPI+'&entity=user_perms',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({user_id:indivUserId,perms})})).json();
  if(!j.success) return alert(j.message||'Save failed');
  document.getElementById('indiv-modal').style.display='none';
  indivLoad();
}

// ==================== ROLES TAB ====================
async function roleLoad(){
  const p=new URLSearchParams({page:rolePage,limit:20,search:roleSearch});
  try{
    const r=await fetch(raRoleAPI+'&'+p);const j=await r.json();if(!j.success){roleMsg('Failed');return;}
    const{roles,pagination}=j.data;
    const tb=document.getElementById('role-tbody');
    if(!roles.length){tb.innerHTML='<tr><td colspan="5" style="padding:60px;text-align:center;color:var(--gray-400);"><i class="fas fa-user-shield" style="font-size:36px;display:block;margin-bottom:8px;"></i>No roles found.</td></tr>';return;}
    tb.innerHTML=roles.map(r=>`<tr style="border-bottom:1px solid var(--gray-100);">
      <td style="padding:12px 16px;font-weight:600;">${rEsc(r.name)}</td>
      <td style="padding:12px 16px;font-family:monospace;font-size:13px;color:var(--primary-color);">${rEsc(r.slug)}</td>
      <td style="padding:12px 16px;font-size:13px;color:var(--gray-600);">${rEsc(r.description||'—')}</td>
      <td style="padding:12px 16px;text-align:center;font-weight:600;">${r.user_count||0}</td>
      <td style="padding:12px 16px;text-align:center;">
        <button onclick="roleEdit(${r.id})" style="background:none;border:none;color:var(--primary-color);cursor:pointer;font-size:15px;padding:4px;"><i class="fas fa-edit"></i></button>
        <button onclick="roleDel(${r.id},'${rEsc(r.name)}')" style="background:none;border:none;color:var(--danger-color);cursor:pointer;font-size:15px;padding:4px;"><i class="fas fa-trash"></i></button>
      </td>
    </tr>`).join('');
    const pg=document.getElementById('role-pagination');
    pg.innerHTML=pagination.total_pages>1?Array.from({length:pagination.total_pages},(_,i)=>`<button onclick="rolePage=${i+1};roleLoad()" style="padding:6px 12px;border:1px solid ${i+1===pagination.page?'var(--primary-color)':'var(--gray-200)'};background:${i+1===pagination.page?'var(--primary-color)':'white'};color:${i+1===pagination.page?'white':'var(--gray-700)'};border-radius:6px;cursor:pointer;">${i+1}</button>`).join(''):'';
  }catch(e){roleMsg('Error');}
}
function roleMsg(m){document.getElementById('role-tbody').innerHTML=`<tr><td colspan="5" style="padding:40px;text-align:center;color:var(--gray-400);">${m}</td></tr>`;}

function roleOpenModal(d=null){
  document.getElementById('role-id').value=d?d.id:'';
  document.getElementById('role-name').value=d?d.name:'';
  document.getElementById('role-slug').value=d?d.slug:'';
  document.getElementById('role-description').value=d?d.description:'';
  document.getElementById('role-modal-title').textContent=d?'Edit Role':'Add Role';
  document.getElementById('role-submit-label').textContent=d?'Update Role':'Create Role';
  document.getElementById('role-alert').style.display='none';
  document.getElementById('role-modal').style.display='flex';
}
function roleCloseModal(){document.getElementById('role-modal').style.display='none';}
async function roleEdit(id){try{const r=await fetch(raRoleAPI+'&id='+id);const j=await r.json();if(j.success)roleOpenModal(j.data);}catch(e){alert('Load failed');}}
async function roleDel(id,name){if(!confirm(`Delete role "${name}"?`))return;try{const r=await fetch(raRoleAPI+'&id='+id,{method:'DELETE'});const j=await r.json();j.success?roleLoad():alert(j.message);}catch(e){alert('Failed');}}
async function roleSubmit(e){
  e.preventDefault();const id=document.getElementById('role-id').value;
  const btn=document.getElementById('role-submit-btn');const al=document.getElementById('role-alert');al.style.display='none';btn.disabled=true;
  const body={name:document.getElementById('role-name').value.trim(),slug:document.getElementById('role-slug').value.trim(),description:document.getElementById('role-description').value.trim()};
  if(!body.name||!body.slug){al.textContent='Name and slug required';al.style.display='block';btn.disabled=false;return;}
  try{const r=await fetch(id?raRoleAPI+'&id='+id:raRoleAPI,{method:id?'PUT':'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});const j=await r.json();if(j.success){roleCloseModal();roleLoad();}else{al.textContent=j.message;al.style.display='block';}}
  catch(err){al.textContent='Failed';al.style.display='block';}finally{btn.disabled=false;}
}
const rNameEl = document.getElementById('role-name');
if(rNameEl){
  rNameEl.addEventListener('input',function(){
    if(!document.getElementById('role-id').value){
      document.getElementById('role-slug').value=this.value.toLowerCase().replace(/[^a-z0-9]+/g,'_').replace(/^_+|_+$/g,'');
    }
  });
}
let roleTimer;
const rSearchEl = document.getElementById('role-search');
if(rSearchEl){
  rSearchEl.addEventListener('input',function(){clearTimeout(roleTimer);roleTimer=setTimeout(()=>{roleSearch=this.value;rolePage=1;roleLoad();},350);});
}

// ==================== PAGE ACCESS (BY ROLE) TAB ====================
async function accessInit(){
  const currentRole=document.getElementById('access-role-select')?.value;
  try{
    const r=await fetch(raRoleAPI+'&limit=50');const j=await r.json();
    if(j.success){
      const sel = document.getElementById('access-role-select');
      if(sel) sel.innerHTML='<option value="">— Select Role —</option>'+j.data.roles.map(r=>`<option value="${r.id}" ${r.id==currentRole?'selected':''}>${rEsc(r.name)}</option>`).join('');
    }
  }catch(e){}
  if(document.getElementById('access-role-select')?.value){
    accessLoadForRole();
  }
}
async function accessLoadForRole(){
  const roleId=document.getElementById('access-role-select')?.value;
  if(!roleId){document.getElementById('access-content').innerHTML='<p style="text-align:center;color:var(--gray-400);padding:40px;"><i class="fas fa-shield-alt" style="font-size:36px;display:block;margin-bottom:10px;"></i>Select a role above to manage page access</p>';return;}
  document.getElementById('access-content').innerHTML='<p style="text-align:center;color:var(--gray-400);padding:20px;"><i class="fas fa-spinner fa-spin"></i> Loading...</p>';
  try{
    const r=await fetch(raMenuAPI+'&entity=access&role_id='+roleId);const j=await r.json();
    if(!j.success){document.getElementById('access-content').innerHTML='<p style="color:var(--danger-color);text-align:center;">Failed</p>';return;}
    const items=j.data.items;
    const grouped={};
    items.forEach(i=>{
      const key=i.menu_title;
      if(!grouped[key]) grouped[key]={section:i.section_label,items:[]};
      grouped[key].items.push(i);
    });
    let html='<div style="display:grid;gap:16px;">';
    Object.keys(grouped).forEach(menu=>{
      const g=grouped[menu];
      html+=`<div style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px;padding:14px 16px;">
        <div style="font-weight:600;font-size:14px;margin-bottom:10px;color:var(--gray-800);"><i class="fas fa-folder-open" style="color:var(--primary-color);margin-right:6px;"></i> ${rEsc(g.section||menu)} → ${rEsc(menu)}</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:8px;">`;
      g.items.forEach(item=>{
        html+=`<label style="display:flex;align-items:center;gap:8px;padding:8px 10px;background:white;border-radius:6px;border:1px solid var(--gray-200);cursor:pointer;font-size:13px;">
          <input type="checkbox" class="access-cb" value="${item.id}" ${item.has_access==1?'checked':''} style="width:16px;height:16px;">
          <i class="${rEsc(item.icon)}" style="color:var(--primary-color);width:16px;"></i>
          <span>${rEsc(item.title)}</span>
        </label>`;
      });
      html+='</div></div>';
    });
    html+='</div>';
    document.getElementById('access-content').innerHTML=html;
  }catch(e){document.getElementById('access-content').innerHTML='<p style="color:var(--danger-color);text-align:center;">Error</p>';}
}
async function accessSave(){
  const roleId=document.getElementById('access-role-select')?.value;
  if(!roleId){alert('Select a role first');return;}
  const cbs=document.querySelectorAll('.access-cb:checked');
  const ids=Array.from(cbs).map(cb=>parseInt(cb.value));
  try{
    const r=await fetch(raMenuAPI+'&entity=access',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({role_id:parseInt(roleId),menu_item_ids:ids})});
    const j=await r.json();
    if(j.success){
      const msg = document.getElementById('access-msg');
      if(msg){
        msg.textContent=`✓ Access updated — ${ids.length} pages assigned to role`;
        msg.style.display='block';
        setTimeout(()=>{msg.style.display='none';},3000);
      }
    }else alert(j.message);
  }catch(e){alert('Failed');}
}

// ==================== USER ACCESS OVERRIDES TAB ====================
let _allUsersForAccess = [];
let _currentUserAccessItems = [];

async function userAccessInit(preselectedUserId = null) {
  try {
    const r = await fetch(raUserAPI + '&limit=500&status=active');
    const j = await r.json();
    if (j.success && j.data && j.data.users) {
      _allUsersForAccess = j.data.users;
      const userSel = document.getElementById('user-access-user-select');
      if (userSel) {
        userSel.innerHTML = '<option value="">— Select User to Override Access —</option>' + _allUsersForAccess.map(u => 
          `<option value="${u.id}" ${preselectedUserId && u.id == preselectedUserId ? 'selected' : ''}>${rEsc(u.first_name + ' ' + u.last_name)} (@${rEsc(u.username)}) — Role: ${rEsc(u.role_name || 'None')}</option>`
        ).join('');
      }
    }
  } catch(e) {}

  userAccessLoadOverridesList();

  if (preselectedUserId) {
    userAccessLoadForUser(preselectedUserId);
  } else {
    const curVal = document.getElementById('user-access-user-select')?.value;
    if (curVal) userAccessLoadForUser(curVal);
  }
}

async function userAccessLoadForUser(userId = null) {
  if (!userId) userId = document.getElementById('user-access-user-select')?.value;
  if (!userId) {
    const content = document.getElementById('user-access-content');
    if (content) content.innerHTML = '<p style="text-align:center;color:var(--gray-400);padding:40px;"><i class="fas fa-user-lock" style="font-size:36px;display:block;margin-bottom:10px;"></i>Select a user above to manage their custom page access overrides.</p>';
    const info = document.getElementById('user-access-role-info');
    if (info) info.style.display = 'none';
    return;
  }

  const content = document.getElementById('user-access-content');
  if (content) content.innerHTML = '<p style="text-align:center;color:var(--gray-400);padding:30px;"><i class="fas fa-spinner fa-spin" style="font-size:24px;"></i> Loading user access details...</p>';

  try {
    const r = await fetch(raMenuAPI + '&entity=user_access&user_id=' + userId);
    const j = await r.json();
    if (!j.success) {
      if (content) content.innerHTML = `<p style="color:var(--danger-color);text-align:center;padding:20px;">${rEsc(j.message || 'Failed to load access')}</p>`;
      return;
    }

    const { user, roles, is_admin, items } = j.data;
    _currentUserAccessItems = items;

    // Show User summary banner
    const roleBadges = roles.length ? roles.map(r => `<span style="padding:3px 10px;background:#e0f2fe;color:#0369a1;border-radius:12px;font-size:12px;font-weight:600;margin-right:4px;"><i class="fas fa-shield-alt"></i> ${rEsc(r.name)}</span>`).join('') : '<span style="color:var(--gray-400);">No assigned roles</span>';
    
    const info = document.getElementById('user-access-role-info');
    if (info) {
      info.innerHTML = `
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
          <div>
            <div style="font-size:15px;font-weight:700;color:var(--gray-900);display:flex;align-items:center;gap:8px;">
              <i class="fas fa-user-circle" style="color:var(--primary-color);font-size:18px;"></i>
              ${rEsc(user.first_name + ' ' + user.last_name)} 
              <span style="font-size:13px;font-weight:normal;color:var(--gray-500);">(@${rEsc(user.username)})</span>
            </div>
            <div style="margin-top:6px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
              <span style="font-size:12px;color:var(--gray-500);">Assigned Roles:</span>
              ${roleBadges}
            </div>
          </div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button onclick="userAccessSelectAll(true)" style="padding:6px 12px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;"><i class="fas fa-check-double"></i> Grant All Pages</button>
            <button onclick="userAccessResetToRoleOnly()" style="padding:6px 12px;background:#fef3c7;color:#92400e;border:1px solid #fde68a;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;"><i class="fas fa-undo"></i> Reset to Role Only</button>
          </div>
        </div>
        <div style="margin-top:12px;padding:10px 14px;background:#f8fafc;border-left:4px solid #8b5cf6;border-radius:0 6px 6px 0;font-size:12px;color:var(--gray-600);line-height:1.5;">
          <i class="fas fa-info-circle" style="color:#8b5cf6;"></i> 
          Pages marked <strong>[Role Access]</strong> are automatically available from the user's role. 
          Check additional pages (e.g. <strong>Inventory</strong>) to grant custom access to <em>this specific user only</em> without giving access to all other ${roles.map(r=>r.name).join('/')} users.
        </div>
      `;
      info.style.display = 'block';
    }

    renderUserAccessGrid(items);

  } catch(e) {
    if (content) content.innerHTML = '<p style="color:var(--danger-color);text-align:center;">Error loading user access</p>';
  }
}

function renderUserAccessGrid(items, filter = '') {
  const container = document.getElementById('user-access-content');
  if (!container) return;
  if (!items || !items.length) {
    container.innerHTML = '<p style="text-align:center;color:var(--gray-400);padding:20px;">No menu items found in system.</p>';
    return;
  }

  const q = filter.trim().toLowerCase();
  const filtered = q ? items.filter(i => 
    (i.title||'').toLowerCase().includes(q) || 
    (i.url||'').toLowerCase().includes(q) || 
    (i.menu_title||'').toLowerCase().includes(q) ||
    (i.section_label||'').toLowerCase().includes(q)
  ) : items;

  if (!filtered.length) {
    container.innerHTML = '<p style="text-align:center;color:var(--gray-400);padding:20px;">No pages match your search filter.</p>';
    return;
  }

  const grouped = {};
  filtered.forEach(i => {
    const key = i.menu_title;
    if (!grouped[key]) grouped[key] = { section: i.section_label, items: [] };
    grouped[key].items.push(i);
  });

  let html = '<div style="display:grid;gap:16px;">';
  Object.keys(grouped).forEach(menu => {
    const g = grouped[menu];
    html += `<div style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:10px;padding:16px;">
      <div style="font-weight:700;font-size:14px;margin-bottom:12px;color:var(--gray-800);display:flex;justify-content:space-between;align-items:center;">
        <span><i class="fas fa-folder-open" style="color:var(--primary-color);margin-right:6px;"></i> ${rEsc(g.section || menu)} → ${rEsc(menu)}</span>
        <span style="font-size:11px;font-weight:normal;color:var(--gray-500);">${g.items.length} pages</span>
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));gap:10px;">`;
    
    g.items.forEach(item => {
      const isRole = item.has_role_access == 1;
      const isOverride = item.has_user_override == 1;
      const isChecked = isRole || isOverride;

      let badge = '';
      if (isRole) {
        badge = '<span style="margin-left:auto;padding:2px 6px;background:#e0f2fe;color:#0369a1;border-radius:4px;font-size:10px;font-weight:600;white-space:nowrap;"><i class="fas fa-shield-alt"></i> Role Access</span>';
      } else if (isOverride) {
        badge = '<span style="margin-left:auto;padding:2px 6px;background:#ede9fe;color:#6d28d9;border-radius:4px;font-size:10px;font-weight:700;white-space:nowrap;"><i class="fas fa-key"></i> User Override</span>';
      }

      html += `<label class="user-access-item-card" style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:white;border-radius:8px;border:1px solid ${isOverride ? '#8b5cf6' : (isRole ? '#93c5fd' : 'var(--gray-200)')};cursor:pointer;font-size:13px;transition:all .15s;">
        <input type="checkbox" class="user-access-cb" value="${item.id}" ${isChecked ? 'checked' : ''} data-role="${isRole ? 1 : 0}" onchange="userAccessCbChanged(this)" style="width:16px;height:16px;cursor:pointer;">
        <i class="${rEsc(item.icon || 'fas fa-circle')}" style="color:var(--primary-color);width:16px;font-size:14px;"></i>
        <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;">
          <div style="font-weight:600;color:var(--gray-900);">${rEsc(item.title)}</div>
          <div style="font-size:11px;color:var(--gray-500);font-family:monospace;">${rEsc(item.url)}</div>
        </div>
        ${badge}
      </label>`;
    });

    html += '</div></div>';
  });
  html += '</div>';
  container.innerHTML = html;
}

function userAccessFilterPages() {
  const q = document.getElementById('user-access-search')?.value || '';
  renderUserAccessGrid(_currentUserAccessItems, q);
}

function userAccessCbChanged(cb) {
  const card = cb.closest('.user-access-item-card');
  const isRole = parseInt(cb.dataset.role) === 1;
  if (!card) return;
  if (cb.checked) {
    card.style.borderColor = isRole ? '#93c5fd' : '#8b5cf6';
    card.style.background = isRole ? '#ffffff' : '#f5f3ff';
  } else {
    card.style.borderColor = 'var(--gray-200)';
    card.style.background = 'white';
  }
}

function userAccessSelectAll(grantAll = true) {
  document.querySelectorAll('.user-access-cb').forEach(cb => {
    cb.checked = grantAll;
    userAccessCbChanged(cb);
  });
}

function userAccessResetToRoleOnly() {
  document.querySelectorAll('.user-access-cb').forEach(cb => {
    const isRole = parseInt(cb.dataset.role) === 1;
    cb.checked = isRole;
    userAccessCbChanged(cb);
  });
}

async function userAccessSave() {
  const userId = document.getElementById('user-access-user-select')?.value;
  if (!userId) { alert('Select a user first'); return; }

  const checkedCbs = document.querySelectorAll('.user-access-cb:checked');
  const ids = Array.from(checkedCbs).map(cb => parseInt(cb.value));

  try {
    const r = await fetch(raMenuAPI + '&entity=user_access', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        user_id: parseInt(userId),
        menu_item_ids: ids
      })
    });
    const j = await r.json();
    if (j.success) {
      const msg = document.getElementById('user-access-msg');
      if (msg) {
        msg.textContent = `✓ User access overrides saved successfully! (${ids.length} total accessible pages)`;
        msg.style.display = 'block';
        setTimeout(() => { msg.style.display = 'none'; }, 4000);
      }
      userAccessLoadForUser(userId);
      userAccessLoadOverridesList();
    } else {
      alert('Save failed: ' + j.message);
    }
  } catch(e) {
    alert('Failed to save user overrides: ' + e.message);
  }
}

async function userAccessReset() {
  const userId = document.getElementById('user-access-user-select')?.value;
  if (!userId) { alert('Select a user first'); return; }
  if (!confirm('Reset this user to standard role access? All custom page overrides will be removed.')) return;

  try {
    const r = await fetch(raMenuAPI + '&entity=user_access_reset', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ user_id: parseInt(userId) })
    });
    const j = await r.json();
    if (j.success) {
      alert('✓ User access reset to standard role defaults');
      userAccessLoadForUser(userId);
      userAccessLoadOverridesList();
    } else {
      alert('Reset error: ' + j.message);
    }
  } catch(e) {
    alert('Reset failed: ' + e.message);
  }
}

async function userAccessLoadOverridesList() {
  const tbody = document.getElementById('user-overrides-list-tbody');
  if (!tbody) return;

  try {
    const r = await fetch(raMenuAPI + '&entity=users_with_overrides');
    const j = await r.json();
    if (j.success && j.data && j.data.users && j.data.users.length) {
      tbody.innerHTML = j.data.users.map(u => `
        <tr style="border-bottom:1px solid var(--gray-100);">
          <td style="padding:10px 14px;font-weight:600;color:var(--gray-900);">
            ${rEsc(u.first_name + ' ' + u.last_name)} 
            <div style="font-size:11px;color:var(--gray-500);font-family:monospace;">@${rEsc(u.username)} • ${rEsc(u.email)}</div>
          </td>
          <td style="padding:10px 14px;font-size:12px;">
            <span style="padding:2px 8px;background:#e0f2fe;color:#0369a1;border-radius:12px;font-weight:600;">${rEsc(u.role_names || 'None')}</span>
          </td>
          <td style="padding:10px 14px;text-align:center;">
            <span style="padding:3px 10px;background:#ede9fe;color:#6d28d9;border-radius:12px;font-weight:700;font-size:12px;">
              <i class="fas fa-key"></i> ${u.override_count} custom pages
            </span>
          </td>
          <td style="padding:10px 14px;font-size:12px;color:var(--gray-700);max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${rEsc(u.override_pages || '')}">
            ${rEsc(u.override_pages || '—')}
          </td>
          <td style="padding:10px 14px;text-align:center;">
            <button onclick="userAccessQuickManage(${u.id})" style="padding:4px 10px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;margin-right:6px;" title="Manage Overrides"><i class="fas fa-sliders-h"></i> Manage</button>
            <button onclick="userAccessQuickClear(${u.id}, '${rEsc(u.username)}')" style="padding:4px 8px;background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;" title="Clear Overrides"><i class="fas fa-trash-alt"></i></button>
          </td>
        </tr>
      `).join('');
    } else {
      tbody.innerHTML = '<tr><td colspan="5" style="padding:24px;text-align:center;color:var(--gray-400);font-size:13px;"><i class="fas fa-info-circle"></i> No custom user overrides active currently. All users have their default role permissions.</td></tr>';
    }
  } catch(e) {}
}

function userAccessQuickManage(userId) {
  const sel = document.getElementById('user-access-user-select');
  if (sel) {
    sel.value = userId;
    userAccessLoadForUser(userId);
    window.scrollTo({ top: document.getElementById('ra-panel-user_access').offsetTop - 50, behavior: 'smooth' });
  }
}

async function userAccessQuickClear(userId, username) {
  if (!confirm(`Remove all custom access overrides for @${username}? They will revert to standard role access.`)) return;
  try {
    const r = await fetch(raMenuAPI + '&entity=user_access_reset', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ user_id: parseInt(userId) })
    });
    const j = await r.json();
    if (j.success) {
      userAccessLoadOverridesList();
      const curSel = document.getElementById('user-access-user-select')?.value;
      if (curSel == userId) userAccessLoadForUser(userId);
    }
  } catch(e) {}
}

// ==================== STANDALONE MODAL (FOR USERS SUBMODULE) ====================
async function openUserAccessModal(userId, username, roleName) {
  const oldModal = document.getElementById('user-access-modal-dialog');
  if (oldModal) oldModal.remove();

  const modal = document.createElement('div');
  modal.id = 'user-access-modal-dialog';
  modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:flex-start;justify-content:center;padding:16px;overflow-y:auto;';
  modal.innerHTML = `
    <div style="background:white;border-radius:12px;padding:24px;width:100%;max-width:820px;margin:20px auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);position:relative;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid var(--gray-200);">
        <div>
          <h3 style="font-size:17px;font-weight:700;color:var(--gray-900);display:flex;align-items:center;gap:8px;">
            <i class="fas fa-key" style="color:#8b5cf6;"></i> Custom Page Access Overrides — @${rEsc(username)}
          </h3>
          <p style="font-size:12px;color:var(--gray-500);margin-top:2px;">
            Base Role: <strong style="color:#0369a1;">${rEsc(roleName || 'None')}</strong>. Grant or customize page access for this user without changing permissions for other ${rEsc(roleName || 'users')}.
          </p>
        </div>
        <button onclick="document.getElementById('user-access-modal-dialog').remove()" style="border:none;background:none;font-size:22px;cursor:pointer;color:var(--gray-400);">&times;</button>
      </div>

      <div style="display:flex;gap:10px;align-items:center;margin-bottom:14px;flex-wrap:wrap;">
        <input type="text" id="modal-access-search" placeholder="Search pages, modules (e.g. inventory, billing)..." oninput="userAccessModalFilter(${userId})" style="flex:1;min-width:200px;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;">
        <button onclick="userAccessModalSelectAll(true)" style="padding:8px 12px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;"><i class="fas fa-check-double"></i> Select All</button>
        <button onclick="userAccessModalResetToRole()" style="padding:8px 12px;background:#fef3c7;color:#92400e;border:1px solid #fde68a;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;"><i class="fas fa-undo"></i> Reset to Role</button>
      </div>

      <div id="modal-access-content" style="max-height:55vh;overflow-y:auto;border:1px solid var(--gray-200);border-radius:8px;padding:16px;background:var(--gray-50);">
        <p style="text-align:center;color:var(--gray-400);padding:30px;"><i class="fas fa-spinner fa-spin"></i> Loading access items...</p>
      </div>

      <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-top:16px;padding-top:14px;border-top:1px solid var(--gray-200);flex-wrap:wrap;">
        <button onclick="userAccessModalClearOverrides(${userId})" style="padding:10px 16px;background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;"><i class="fas fa-trash-alt"></i> Remove All Overrides</button>
        <div style="display:flex;gap:10px;">
          <button onclick="document.getElementById('user-access-modal-dialog').remove()" style="padding:10px 18px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;font-weight:600;cursor:pointer;">Cancel</button>
          <button onclick="userAccessModalSave(${userId})" style="padding:10px 22px;background:#16a34a;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-save"></i> Save Overrides</button>
        </div>
      </div>
    </div>
  `;
  document.body.appendChild(modal);

  try {
    const r = await fetch(raMenuAPI + '&entity=user_access&user_id=' + userId);
    const j = await r.json();
    if (j.success) {
      window._modalUserAccessItems = j.data.items || [];
      renderModalUserAccessGrid(window._modalUserAccessItems);
    } else {
      document.getElementById('modal-access-content').innerHTML = `<p style="color:var(--danger-color);text-align:center;">${rEsc(j.message)}</p>`;
    }
  } catch(e) {
    document.getElementById('modal-access-content').innerHTML = '<p style="color:var(--danger-color);text-align:center;">Failed to load access</p>';
  }
}

function renderModalUserAccessGrid(items, filter = '') {
  const container = document.getElementById('modal-access-content');
  if (!container) return;
  const q = filter.trim().toLowerCase();
  const filtered = q ? items.filter(i => 
    (i.title||'').toLowerCase().includes(q) || 
    (i.url||'').toLowerCase().includes(q) || 
    (i.menu_title||'').toLowerCase().includes(q)
  ) : items;

  if (!filtered.length) {
    container.innerHTML = '<p style="text-align:center;color:var(--gray-400);padding:20px;">No matching pages found.</p>';
    return;
  }

  const grouped = {};
  filtered.forEach(i => {
    const key = i.menu_title;
    if (!grouped[key]) grouped[key] = { section: i.section_label, items: [] };
    grouped[key].items.push(i);
  });

  let html = '<div style="display:grid;gap:14px;">';
  Object.keys(grouped).forEach(menu => {
    const g = grouped[menu];
    html += `<div style="background:white;border:1px solid var(--gray-200);border-radius:8px;padding:12px 14px;">
      <div style="font-weight:700;font-size:13px;margin-bottom:10px;color:var(--gray-800);">
        <i class="fas fa-folder-open" style="color:var(--primary-color);margin-right:6px;"></i> ${rEsc(g.section || menu)} → ${rEsc(menu)}
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(240px, 1fr));gap:8px;">`;
    g.items.forEach(item => {
      const isRole = item.has_role_access == 1;
      const isOverride = item.has_user_override == 1;
      const isChecked = isRole || isOverride;

      let badge = isRole ? 
        '<span style="margin-left:auto;padding:1px 5px;background:#e0f2fe;color:#0369a1;border-radius:3px;font-size:10px;font-weight:600;"><i class="fas fa-shield-alt"></i> Role</span>' : 
        (isOverride ? '<span style="margin-left:auto;padding:1px 5px;background:#ede9fe;color:#6d28d9;border-radius:3px;font-size:10px;font-weight:700;"><i class="fas fa-key"></i> Override</span>' : '');

      html += `<label style="display:flex;align-items:center;gap:8px;padding:8px 10px;background:#f9fafb;border-radius:6px;border:1px solid ${isOverride ? '#8b5cf6' : (isRole ? '#bfdbfe' : 'var(--gray-200)')};cursor:pointer;font-size:12px;">
        <input type="checkbox" class="modal-user-access-cb" value="${item.id}" ${isChecked ? 'checked' : ''} data-role="${isRole ? 1 : 0}" style="width:15px;height:15px;">
        <i class="${rEsc(item.icon || 'fas fa-circle')}" style="color:var(--primary-color);font-size:13px;"></i>
        <span style="font-weight:500;color:var(--gray-900);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${rEsc(item.title)}</span>
        ${badge}
      </label>`;
    });
    html += '</div></div>';
  });
  html += '</div>';
  container.innerHTML = html;
}

function userAccessModalFilter(userId) {
  const q = document.getElementById('modal-access-search')?.value || '';
  renderModalUserAccessGrid(window._modalUserAccessItems || [], q);
}

function userAccessModalSelectAll(grantAll = true) {
  document.querySelectorAll('.modal-user-access-cb').forEach(cb => { cb.checked = grantAll; });
}

function userAccessModalResetToRole() {
  document.querySelectorAll('.modal-user-access-cb').forEach(cb => {
    cb.checked = parseInt(cb.dataset.role) === 1;
  });
}

async function userAccessModalSave(userId) {
  const cbs = document.querySelectorAll('.modal-user-access-cb:checked');
  const ids = Array.from(cbs).map(cb => parseInt(cb.value));

  try {
    const r = await fetch(raMenuAPI + '&entity=user_access', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ user_id: parseInt(userId), menu_item_ids: ids })
    });
    const j = await r.json();
    if (j.success) {
      alert('✓ User access overrides saved successfully!');
      document.getElementById('user-access-modal-dialog')?.remove();
      if (typeof userLoad === 'function') userLoad();
      userAccessLoadOverridesList();
    } else {
      alert('Save failed: ' + j.message);
    }
  } catch(e) {
    alert('Save failed: ' + e.message);
  }
}

async function userAccessModalClearOverrides(userId) {
  if (!confirm('Remove all custom access overrides for this user?')) return;
  try {
    const r = await fetch(raMenuAPI + '&entity=user_access_reset', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ user_id: parseInt(userId) })
    });
    const j = await r.json();
    if (j.success) {
      alert('✓ Custom overrides removed. Reverted to standard role permissions.');
      document.getElementById('user-access-modal-dialog')?.remove();
      if (typeof userLoad === 'function') userLoad();
      userAccessLoadOverridesList();
    }
  } catch(e) {}
}

// ==================== MENU MANAGEMENT TAB ====================
async function menuLoad(){
  try{
    const r=await fetch(raMenuAPI+'&entity=menus');const j=await r.json();
    if(!j.success) return;
    const menus=j.data.menus;
    const tb=document.getElementById('menu-tbody');
    if(!menus.length){tb.innerHTML='<tr><td colspan="6" style="padding:40px;text-align:center;color:var(--gray-400);">No menus</td></tr>';return;}
    tb.innerHTML=menus.map(m=>`<tr style="border-bottom:1px solid var(--gray-100);">
      <td style="padding:10px 14px;font-weight:600;">${rEsc(m.title)}</td>
      <td style="padding:10px 14px;font-size:13px;color:var(--gray-500);">${rEsc(m.section_label||'—')}</td>
      <td style="padding:10px 14px;"><i class="${rEsc(m.icon)}" style="color:var(--primary-color);"></i> ${rEsc(m.icon)}</td>
      <td style="padding:10px 14px;text-align:center;font-weight:700;">${m.item_count}</td>
      <td style="padding:10px 14px;text-align:center;"><span style="padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;background:${m.is_active==1?'var(--success-color)':'var(--danger-color)'}20;color:${m.is_active==1?'var(--success-color)':'var(--danger-color)'};">${m.is_active==1?'Active':'Inactive'}</span></td>
      <td style="padding:10px 14px;text-align:center;">
        <button onclick="menuItems(${m.id},'${rEsc(m.title)}')" style="background:none;border:none;color:#8b5cf6;cursor:pointer;font-size:14px;padding:3px;" title="Sub Items"><i class="fas fa-list"></i></button>
        <button onclick="menuEdit(${m.id})" style="background:none;border:none;color:var(--primary-color);cursor:pointer;font-size:14px;padding:3px;" title="Edit"><i class="fas fa-edit"></i></button>
        <button onclick="menuDel(${m.id},'${rEsc(m.title)}')" style="background:none;border:none;color:var(--danger-color);cursor:pointer;font-size:14px;padding:3px;" title="Delete"><i class="fas fa-trash"></i></button>
      </td>
    </tr>`).join('');
  }catch(e){}
}

function menuOpenModal(d=null){
  const h=`<div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;display:flex;align-items:center;justify-content:center;padding:16px;" id="menu-modal-wrap">
  <div style="background:white;border-radius:12px;padding:26px;width:100%;max-width:480px;position:relative;">
    <button onclick="document.getElementById('menu-modal-wrap').remove()" style="position:absolute;top:14px;right:14px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);"><i class="fas fa-times"></i></button>
    <h3 style="font-size:17px;font-weight:600;margin-bottom:16px;">${d?'Edit':'Add'} Menu</h3>
    <div style="display:grid;gap:12px;">
      <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Title *</label><input id="mm-title" value="${rEsc(d?.title||'')}" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
      <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Section Label</label><input id="mm-section" value="${rEsc(d?.section_label||'')}" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;" placeholder="e.g. Human Resources"></div>
      <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Icon (FontAwesome class)</label><input id="mm-icon" value="${rEsc(d?.icon||'fas fa-circle')}" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Order</label><input id="mm-order" type="number" value="${d?.menu_order||0}" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
        <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Active</label><select id="mm-active" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;"><option value="1" ${d?.is_active!=0?'selected':''}>Yes</option><option value="0" ${d?.is_active==0?'selected':''}>No</option></select></div>
      </div>
    </div>
    <div style="display:flex;gap:10px;margin-top:14px;padding-top:14px;border-top:1px solid var(--gray-200);">
      <button onclick="menuSave(${d?.id||'null'})" style="flex:1;padding:11px;background:var(--success-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-save"></i> ${d?'Update':'Create'}</button>
      <button onclick="document.getElementById('menu-modal-wrap').remove()" style="padding:11px 20px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;cursor:pointer;">Cancel</button>
    </div>
  </div></div>`;
  document.body.insertAdjacentHTML('beforeend',h);
}
async function menuEdit(id){try{const r=await fetch(raMenuAPI+'&entity=menus&id='+id);const j=await r.json();if(j.success)menuOpenModal(j.data);}catch(e){alert('Failed');}}
async function menuDel(id,name){if(!confirm(`Delete menu "${name}" and all its items?`))return;try{const r=await fetch(raMenuAPI+'&entity=menus&id='+id,{method:'DELETE'});const j=await r.json();j.success?menuLoad():alert(j.message);}catch(e){alert('Failed');}}
async function menuSave(id){
  const body={title:document.getElementById('mm-title').value.trim(),section_label:document.getElementById('mm-section').value.trim(),icon:document.getElementById('mm-icon').value.trim(),menu_order:document.getElementById('mm-order').value,is_active:document.getElementById('mm-active').value};
  try{const r=await fetch(id?raMenuAPI+'&entity=menus&id='+id:raMenuAPI+'&entity=menus',{method:id?'PUT':'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});const j=await r.json();if(j.success){document.getElementById('menu-modal-wrap').remove();menuLoad();}else alert(j.message);}catch(e){alert('Failed');}
}

// ===== SUB-ITEMS MANAGEMENT =====
async function menuItems(menuId, menuName){
  const h=`<div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1001;display:flex;align-items:flex-start;justify-content:center;padding:16px;overflow-y:auto;" id="items-modal-wrap">
  <div style="background:white;border-radius:12px;padding:26px;width:100%;max-width:700px;margin:auto;position:relative;">
    <button onclick="document.getElementById('items-modal-wrap').remove()" style="position:absolute;top:14px;right:14px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);"><i class="fas fa-times"></i></button>
    <h3 style="font-size:17px;font-weight:600;margin-bottom:16px;"><i class="fas fa-list" style="color:#8b5cf6;margin-right:8px;"></i>Sub-Items: ${rEsc(menuName)}</h3>
    <div style="display:flex;justify-content:flex-end;margin-bottom:12px;">
      <button onclick="itemOpenForm(${menuId})" style="padding:8px 14px;background:var(--success-color);color:white;border:none;border-radius:8px;font-size:13px;cursor:pointer;"><i class="fas fa-plus"></i> Add Sub-Item</button>
    </div>
    <div id="items-form-area" style="display:none;background:var(--gray-50);border-radius:8px;padding:14px;margin-bottom:14px;">
      <input type="hidden" id="item-edit-id">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <div><label style="font-size:12px;font-weight:500;">Title *</label><input id="item-title" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
        <div><label style="font-size:12px;font-weight:500;">URL *</label><input id="item-url" placeholder="hr.php?module=hr&sub=employees" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
        <div><label style="font-size:12px;font-weight:500;">Icon</label><input id="item-icon" value="fas fa-circle" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
        <div><label style="font-size:12px;font-weight:500;">Order</label><input id="item-order" type="number" value="0" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
      </div>
      <div style="display:flex;gap:8px;margin-top:10px;">
        <button onclick="itemSave(${menuId})" style="padding:8px 14px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:13px;cursor:pointer;"><i class="fas fa-save"></i> Save</button>
        <button onclick="document.getElementById('items-form-area').style.display='none'" style="padding:8px 14px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:6px;font-size:13px;cursor:pointer;">Cancel</button>
      </div>
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
      <thead><tr style="background:var(--gray-50);"><th style="padding:8px 10px;text-align:left;">Title</th><th style="padding:8px 10px;text-align:left;">URL</th><th style="padding:8px 10px;">Icon</th><th style="padding:8px 10px;">Order</th><th style="padding:8px 10px;">Actions</th></tr></thead>
      <tbody id="items-list-tbody"><tr><td colspan="5" style="padding:20px;text-align:center;color:var(--gray-400);">Loading...</td></tr></tbody>
    </table>
  </div></div>`;
  document.body.insertAdjacentHTML('beforeend',h);
  await itemsReload(menuId);
}
async function itemsReload(menuId){
  try{
    const r=await fetch(raMenuAPI+'&entity=items&menu_id='+menuId);const j=await r.json();
    const tb=document.getElementById('items-list-tbody');
    if(!j.success||!j.data.items.length){tb.innerHTML='<tr><td colspan="5" style="padding:20px;text-align:center;color:var(--gray-400);">No items. Add one above.</td></tr>';return;}
    tb.innerHTML=j.data.items.map(i=>`<tr style="border-bottom:1px solid var(--gray-100);">
      <td style="padding:8px 10px;font-weight:600;">${rEsc(i.title)}</td>
      <td style="padding:8px 10px;font-size:12px;color:var(--primary-color);font-family:monospace;">${rEsc(i.url)}</td>
      <td style="padding:8px 10px;text-align:center;"><i class="${rEsc(i.icon)}"></i></td>
      <td style="padding:8px 10px;text-align:center;">${i.item_order}</td>
      <td style="padding:8px 10px;text-align:center;">
        <button onclick="itemEditInline(${i.id},${menuId})" style="background:none;border:none;color:var(--primary-color);cursor:pointer;"><i class="fas fa-edit"></i></button>
        <button onclick="itemDel(${i.id},${menuId})" style="background:none;border:none;color:var(--danger-color);cursor:pointer;"><i class="fas fa-trash"></i></button>
      </td>
    </tr>`).join('');
  }catch(e){}
}
function itemOpenForm(menuId){
  document.getElementById('item-edit-id').value='';
  document.getElementById('item-title').value='';
  document.getElementById('item-url').value='';
  document.getElementById('item-icon').value='fas fa-circle';
  document.getElementById('item-order').value='0';
  document.getElementById('items-form-area').style.display='block';
}
async function itemEditInline(id,menuId){
  try{const r=await fetch(raMenuAPI+'&entity=items&id='+id);const j=await r.json();if(!j.success)return;
    const d=j.data;
    document.getElementById('item-edit-id').value=d.id;
    document.getElementById('item-title').value=d.title;
    document.getElementById('item-url').value=d.url;
    document.getElementById('item-icon').value=d.icon;
    document.getElementById('item-order').value=d.item_order;
    document.getElementById('items-form-area').style.display='block';
  }catch(e){}
}
async function itemSave(menuId){
  const id=document.getElementById('item-edit-id').value;
  const body={menu_id:menuId,title:document.getElementById('item-title').value.trim(),url:document.getElementById('item-url').value.trim(),icon:document.getElementById('item-icon').value.trim(),item_order:document.getElementById('item-order').value,is_active:1};
  try{const r=await fetch(id?raMenuAPI+'&entity=items&id='+id:raMenuAPI+'&entity=items',{method:id?'PUT':'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});const j=await r.json();if(j.success){document.getElementById('items-form-area').style.display='none';itemsReload(menuId);menuLoad();}else alert(j.message);}catch(e){alert('Failed');}
}
async function itemDel(id,menuId){if(!confirm('Delete this sub-item?'))return;try{const r=await fetch(raMenuAPI+'&entity=items&id='+id,{method:'DELETE'});const j=await r.json();if(j.success)itemsReload(menuId);menuLoad();}catch(e){alert('Failed');}}

// Init roles on page load if roles panel is active
if (document.getElementById('ra-panel-roles')) {
  roleLoad();
}

