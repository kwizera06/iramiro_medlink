<?php
require_once __DIR__ . '/includes/header.php';
$subModule = isset($_GET['sub']) ? $_GET['sub'] : 'medical-leave';
?>
<style>
.doc-tab{padding:10px 18px;border:none;background:var(--gray-50);border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;color:var(--gray-600);}
.doc-tab.active{background:var(--primary-color);color:white;}
.doc-field{border:none;border-bottom:1px solid #333;outline:none;font-size:14px;font-family:'Times New Roman',serif;padding:2px 4px;min-width:120px;background:transparent;}
.doc-field:focus{border-bottom-color:var(--primary-color);background:#f0f7ff;}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
  <div>
    <h2 style="font-size:24px;font-weight:600;">Medical Documents</h2>
    <p style="color:var(--gray-500);font-size:14px;margin-top:4px;">Generate and print official medical documents</p>
  </div>
</div>

<!-- Document Type Tabs (one row per group) -->
<?php
$docTabs = [
  '' => ['generated' => ['fa-list', 'Generated Documents']],
  'Medical Certificates' => [
    'medical-leave' => ['fa-file-medical', 'Care of Sick Child'],
    'leave-certificate' => ['fa-notes-medical', 'Medical Leave'],
    'test-certificate' => ['fa-vial', 'Test Certificate'],
    'dietary-certificate' => ['fa-utensils', 'Dietary Restriction'],
    'fitness-certificate' => ['fa-heartbeat', 'Medical Fitness'],
    'biological-certificate' => ['fa-microscope', 'Biological Tests'],
    'hospitalization-certificate' => ['fa-procedures', 'Hospitalization'],
  ],
  // hidden: Clinical Forms (referral-form, external-transfer, echography-report, followup-sheet, dental-lab)
  //         and HR & Admin (attestation-service, attestation-service-rendu, mission-order)
];
?>
<div style="display:flex;flex-direction:column;gap:10px;margin-bottom:24px;">
  <?php foreach ($docTabs as $group => $tabs): ?>
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
    <?php if ($group): ?><span style="font-size:11px;font-weight:700;color:var(--gray-500);text-transform:uppercase;letter-spacing:0.4px;min-width:150px;"><?= $group ?></span><?php endif; ?>
    <?php foreach ($tabs as $sub => [$icon, $label]): ?>
    <button class="doc-tab <?= $subModule == $sub ? 'active' : '' ?>" onclick="location.href='medical-documents.php?module=documents&sub=<?= $sub ?>'"><i class="fas <?= $icon ?>"></i> <?= $label ?></button>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>
</div>

<script src="assets/js/qrcode.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
const _mdPublicUrl = <?php try { echo json_encode((string)($pdo->query("SELECT setting_value FROM hospital_settings WHERE setting_key='website_url'")->fetchColumn() ?: '')); } catch (Throwable $e) { echo '""'; } ?>; // Settings > Hospital > Website / System URL
const _mdBase = (typeof window.appBasePath !== 'undefined' && window.appBasePath !== null) ? window.appBasePath : (window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')) || '');
window._loggedUserName = <?php echo json_encode(trim(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? ''))); ?>;
const docSaveAPI = _mdBase + '/api.php?endpoint=documents/medical-documents';

// Strip leading "Dr." / "dr." from a name to avoid duplication when the text already says "Dr. ${name}"
function stripDr(name){ return (name||'').replace(/^\s*dr\.?\s*/i,'').trim(); }

// Active unique reference cache per document type
const _docRefs = {
  care_sick_child: null,
  leave_certificate: null,
  test_certificate: null,
  dietary_restriction: null,
  fitness_certificate: null
};

// Fetch next unique reference number from server
async function getNextDocRef(type){
  try{
    const r = await fetch(_mdBase + '/api.php?endpoint=documents/medical-documents&action=next_ref&type=' + encodeURIComponent(type));
    const j = await r.json();
    if(j.success && j.data && j.data.reference_no){
      _docRefs[type] = j.data.reference_no;
      return j.data.reference_no;
    }
  }catch(e){}

  const prefixes = {
    care_sick_child: 'CSC',
    leave_certificate: 'MLC',
    test_certificate: 'TST',
    dietary_restriction: 'DRC',
    fitness_certificate: 'MFC'
  };
  const prefix = prefixes[type] || 'DOC';
  const today = new Date().toISOString().split('T')[0].replace(/-/g,'');
  const rand = Math.floor(1000 + Math.random() * 9000);
  const ref = prefix + '-' + today + '-' + rand;
  _docRefs[type] = ref;
  return ref;
}

// Render verifiable QR Code into a container element
function renderDocQRCode(containerId, refNo, size = 68){
  const el = (typeof containerId === 'string') ? document.getElementById(containerId) : containerId;
  if(!el) return;
  el.innerHTML = '';
  if(!refNo) return;
  const verifyUrl = (_mdPublicUrl || (window.location.origin + _mdBase)) + '/verify-document.php?ref=' + encodeURIComponent(refNo);

  if(typeof QRCode !== 'undefined'){
    try{
      new QRCode(el, {
        text: verifyUrl,
        width: size,
        height: size,
        colorDark: "#000000",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.M
      });
      // Safety check: ensure canvas toDataURL is transferred to img for print/PDF export
      setTimeout(() => {
        const canvas = el.querySelector('canvas');
        let img = el.querySelector('img');
        if(canvas && (!img || !img.src || img.src.indexOf('data:image') === -1)){
          try{
            const dataUrl = canvas.toDataURL('image/png');
            if(img){
              img.src = dataUrl;
              img.style.display = 'block';
            } else {
              img = document.createElement('img');
              img.src = dataUrl;
              img.style.width = size + 'px';
              img.style.height = size + 'px';
              img.style.display = 'block';
              el.appendChild(img);
            }
            canvas.style.display = 'none';
          }catch(err){}
        }
      }, 50);
      return;
    }catch(err){
      console.warn('QRCode library error:', err);
    }
  }

  // Fallback if QRCode library is not loaded
  el.innerHTML = '<img src="https://api.qrserver.com/v1/create-qr-code/?size=' + size + 'x' + size + '&data=' + encodeURIComponent(verifyUrl) + '" style="width:' + size + 'px;height:' + size + 'px;display:block;" alt="QR Code">';
}

async function saveDocument(type, patientName, physicianName, data, customRef = null){
  try{
    const refToUse = customRef || _docRefs[type] || null;
    const r=await fetch(docSaveAPI,{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({
        document_type: type,
        patient_name: patientName,
        physician_name: physicianName,
        reference_no: refToUse,
        document_data: data
      })
    });
    const j=await r.json();
    if(j.success){
      const savedRef = j.data.reference_no;
      // Refresh reference for next document of this type
      getNextDocRef(type);
      return savedRef;
    }
  }catch(e){
    console.error('Error saving document:', e);
  }
  return null;
}
</script>
<script src="js/doc-templates.js?v=<?= filemtime(__DIR__ . '/js/doc-templates.js') ?>"></script>

<?php if ($subModule == 'generated'): ?>
<!-- ====== GENERATED DOCUMENTS LIST ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-list" style="color:var(--primary-color);"></i> Generated Documents</h3>
    <button onclick="gdLoad()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-sync-alt"></i></button>
  </div>
  <!-- Search & Filter -->
  <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
    <input id="gd-search" type="text" placeholder="Search by reference, patient, physician..." oninput="gdSearchDebounce()" style="flex:1;min-width:200px;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
    <select id="gd-type" onchange="gdLoad()" style="padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      <option value="">All Types</option>
      <option value="care_sick_child">Care of Sick Child</option>
      <option value="leave_certificate">Medical Leave</option>
      <option value="test_certificate">Test Certificate</option>
      <option value="dietary_restriction">Dietary Restriction</option>
      <option value="fitness_certificate">Medical Fitness</option>
    </select>
  </div>
  <!-- Table -->
  <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
      <thead><tr style="background:var(--gray-50);">
        <th style="padding:10px;text-align:left;">Reference</th>
        <th style="padding:10px;text-align:left;">Type</th>
        <th style="padding:10px;text-align:left;">Patient</th>
        <th style="padding:10px;text-align:left;">Physician</th>
        <th style="padding:10px;text-align:left;">Date</th>
        <th style="padding:10px;text-align:center;">Action</th>
      </tr></thead>
      <tbody id="gd-tbody"><tr><td colspan="6" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr></tbody>
    </table>
  </div>
  <div id="gd-pagination" style="display:flex;justify-content:flex-end;gap:6px;padding:12px 0;"></div>
</div>

<script>
const gdAPI = _mdBase + '/api.php?endpoint=documents/medical-documents';
let gdPage=1,gdTimer;

function gdSearchDebounce(){clearTimeout(gdTimer);gdTimer=setTimeout(gdLoad,350);}

async function gdLoad(){
  const search=document.getElementById('gd-search').value.trim();
  const type=document.getElementById('gd-type').value;
  const params=new URLSearchParams({search,type,page:gdPage,limit:20});
  const tbody=document.getElementById('gd-tbody');
  tbody.innerHTML='<tr><td colspan="6" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr>';
  try{
    const r=await fetch(gdAPI+'&'+params);const j=await r.json();
    if(!j.success){tbody.innerHTML='<tr><td colspan="6" style="padding:30px;text-align:center;color:#dc2626;">'+j.message+'</td></tr>';return;}
    const docs=j.data.documents||[];
    const typeLabels={care_sick_child:'Care of Sick Child',leave_certificate:'Medical Leave',test_certificate:'Test Certificate',dietary_restriction:'Dietary Restriction',fitness_certificate:'Medical Fitness'};
    for(const k in DT_TEMPLATES) typeLabels[k]=DT_TEMPLATES[k].label;
    const typeColors={care_sick_child:'#059669',leave_certificate:'#1e40af',test_certificate:'#7c3aed',dietary_restriction:'#d97706',fitness_certificate:'#dc2626'};
    if(!docs.length){
      tbody.innerHTML='<tr><td colspan="6" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-file-alt" style="font-size:24px;display:block;margin-bottom:8px;"></i>No documents found.</td></tr>';
    }else{
      tbody.innerHTML=docs.map(d=>`<tr style="border-bottom:1px solid var(--gray-100);">
        <td style="padding:8px 10px;font-family:monospace;font-weight:600;color:var(--primary-color);">${d.reference_no}</td>
        <td style="padding:8px 10px;"><span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;background:${typeColors[d.document_type]||'#666'}20;color:${typeColors[d.document_type]||'#666'};">${typeLabels[d.document_type]||d.document_type}</span></td>
        <td style="padding:8px 10px;font-weight:500;">${d.patient_name}</td>
        <td style="padding:8px 10px;font-size:12px;">${d.physician_name||'—'}</td>
        <td style="padding:8px 10px;font-size:12px;">${new Date(d.created_at).toLocaleDateString()}</td>
        <td style="padding:8px 10px;text-align:center;">
          <button onclick="gdView(${d.id},'${d.document_type}')" style="padding:4px 10px;background:#7c3aed;color:white;border:none;border-radius:4px;font-size:11px;cursor:pointer;font-weight:500;"><i class="fas fa-eye"></i> View</button>
          <button onclick="gdDownload(${d.id},'${d.document_type}','${d.patient_name.replace(/'/g,"\\'")}')" style="padding:4px 10px;background:#1e40af;color:white;border:none;border-radius:4px;font-size:11px;cursor:pointer;font-weight:500;"><i class="fas fa-download"></i> PDF</button>
        </td>
      </tr>`).join('');
    }
    // Pagination
    const pg=j.data.pagination;
    const pgEl=document.getElementById('gd-pagination');
    pgEl.innerHTML=pg.total_pages>1?Array.from({length:pg.total_pages},(_,i)=>`<button onclick="gdPage=${i+1};gdLoad()" style="padding:4px 10px;border:1px solid ${i+1===pg.page?'var(--primary-color)':'var(--gray-200)'};background:${i+1===pg.page?'var(--primary-color)':'white'};color:${i+1===pg.page?'white':'var(--gray-700)'};border-radius:4px;cursor:pointer;font-size:12px;">${i+1}</button>`).join(''):'';
  }catch(e){
    tbody.innerHTML='<tr><td colspan="6" style="padding:30px;text-align:center;color:#dc2626;">Error: '+e.message+'</td></tr>';
  }
}
for(const k in DT_TEMPLATES){ if(!document.querySelector('#gd-type option[value="'+k+'"]')) document.getElementById('gd-type').add(new Option(DT_TEMPLATES[k].label,k)); }
gdLoad();

async function gdView(id, type){
  try{
    const r=await fetch(gdAPI+'&id='+id);
    const j=await r.json();
    if(!j.success){alert(j.message);return;}
    const doc=j.data;
    const d=doc.document_data||{};
    // Build preview based on type
    let html=gdBuildDocument(doc,d,type);
    document.getElementById('gd-view-content').innerHTML=html;
    renderDocQRCode('gd-qr-code', doc.reference_no, 64);
    document.getElementById('gd-view-modal').style.display='flex';
  }catch(e){alert('Error: '+e.message);}
}

async function gdDownload(id, type, patientName){
  try{
    const r=await fetch(gdAPI+'&id='+id);
    const j=await r.json();
    if(!j.success){alert(j.message);return;}
    const doc=j.data;
    const d=doc.document_data||{};
    let html=gdBuildDocument(doc,d,type);
    document.getElementById('gd-view-content').innerHTML=html;
    renderDocQRCode('gd-qr-code', doc.reference_no, 64);
    document.getElementById('gd-view-modal').style.display='flex';
    // Generate PDF
    setTimeout(()=>{
      const el=document.getElementById('gd-doc-printable');
      if(!el)return;
      const opt={margin:10,filename:'COPY_'+doc.reference_no+'_'+patientName.replace(/\s+/g,'_')+'.pdf',image:{type:'jpeg',quality:0.98},html2canvas:{scale:2,useCORS:true},jsPDF:{unit:'mm',format:'a4',orientation:'portrait'}};
      html2pdf().set(opt).from(el).save();
    },300);
  }catch(e){alert('Error: '+e.message);}
}

function gdPrintCopy(){
  const content = document.getElementById('gd-view-content').innerHTML;
  const win = window.open('','_blank','width=800,height=600');
  win.document.write(`<html><head><title>Document Copy</title><style>body{font-family:'Times New Roman',serif;padding:30px;color:#000;line-height:1.6;font-size:13px;}img{max-width:100%;}@media print{body{padding:20px;}}</style></head><body><div style="position:relative;overflow:hidden;">${content}</div></body></html>`);
  win.document.close();
  win.focus();
  setTimeout(()=>{win.print();},500);
}

function gdBuildDocument(doc,d,type){
  const typeLabels={care_sick_child:'MEDICAL CERTIFICATE FOR CARE OF A SICK CHILD',leave_certificate:'MEDICAL LEAVE CERTIFICATE',test_certificate:'TEST CERTIFICATE',dietary_restriction:'DIETARY RESTRICTION CERTIFICATE',fitness_certificate:'MEDICAL FITNESS CERTIFICATE'};
  const tpl=d._tpl?DT_TEMPLATES[type]:null; // documents built from docs/*.docx templates
  const title=tpl?tpl.title:(typeLabels[type]||'MEDICAL DOCUMENT');
  const ref=doc.reference_no||'';
  const date=doc.created_at?new Date(doc.created_at).toLocaleDateString():'';

  let body='';
  if(tpl){
    const h=dtHelpers(d,tpl);
    body=tpl.body(h)+(tpl.sign?'<div style="margin-top:14px;">'+tpl.sign(h)+'</div>':'');
  } else if(type==='care_sick_child'){
    body=`<p><strong>To Whom It May Concern,</strong></p>
      <p>This is to certify that ${d.title||'Mr'}. <strong>${d.parent_name||''}</strong> is the parent and primary caregiver of <strong>${d.child_name||''}</strong>, aged <strong>${d.age||''}</strong> years, who has been under medical care for <strong>${d.condition||''}</strong>.</p>
      <p>The child requires close parental supervision and home care during the recovery period.</p>
      <p>Therefore, ${d.title||'Mr'}. <strong>${d.parent_name||''}</strong> is medically advised to take leave from <strong>${d.start||''}</strong> to <strong>${d.end||''}</strong> (a total of <strong>${d.days||''}</strong>) in order to provide the necessary care and support to the child.</p>
      <p>The employee may resume normal duties on <strong>${d.resume||''}</strong>, provided the child's condition has sufficiently improved.</p>`;
  } else if(type==='leave_certificate'){
    body=`<p>I, the undersigned, Dr. <strong>${stripDr(d.physician||'')}</strong>, a licensed physician, hereby certify that I have examined ${d.title||'Mr'}. <strong>${d.patient||''}</strong> and have determined that their medical condition requires <strong>${d.days||''}</strong> days of medical leave, from <strong>${d.start||''}</strong> to <strong>${d.end||''}</strong>.</p>
      <p>This certificate is issued at the request of the patient for all lawful purposes.</p>`;
  } else if(type==='test_certificate'){
    body=`<p>I, the undersigned, Dr. <strong>${stripDr(d.physician||'')}</strong>, hereby certify that ${d.title||'Mr'}. <strong>${d.patient||''}</strong>, born on <strong>${d.dob||''}</strong>, underwent the following test.</p>
      <p><strong>Test Performed:</strong> ${d.test||'—'}</p>
      <p><strong>Result:</strong> ${d.result||'—'}</p>
      <p>This certificate is issued at the request of the patient for all lawful purposes.</p>`;
  } else if(type==='dietary_restriction'){
    const items=(d.items||'').split('\\n').filter(s=>s.trim()).map(s=>'<li>'+s.trim()+'</li>').join('');
    body=`<p>I, the undersigned, Dr. <strong>${stripDr(d.physician||'')}</strong>, hereby certify that ${d.title||'Mr'}. <strong>${d.patient||''}</strong>, born on <strong>${d.dob||''}</strong>, has been examined and treated.</p>
      <p>Due to the patient's medical condition, they are advised to avoid consuming the following:</p>
      <ul>${items||'<li>—</li>'}</ul>
      <p>These dietary restrictions should be followed as medically recommended.</p>
      <p>This certificate is issued at the request of the patient for all lawful purposes.</p>`;
  } else if(type==='fitness_certificate'){
    const bp = d.bp ? `<strong>${d.bp}</strong> mmHg` : '—';
    const hr = d.hr ? `<strong>${d.hr}</strong> bpm` : '—';
    const resp = d.resp ? `<strong>${d.resp}</strong> /min` : '—';
    const temp = d.temp ? `<strong>${d.temp}</strong> °C` : '—';
    const spo2 = d.spo2 ? `<strong>${d.spo2}</strong> %` : '—';
    const bmi = d.bmi ? `<strong>${d.bmi}</strong> kg/m²` : '—';
    const weight = d.weight ? `<strong>${d.weight}</strong> kg` : '—';
    const height = d.height ? `<strong>${d.height}</strong> cm` : '—';
    const visitNote = d.visit_no ? `<span style="font-size:11px;color:#64748b;font-weight:normal;">(Visit: ${d.visit_no}${d.visit_date ? ' on ' + d.visit_date : ''})</span>` : '';
    const noteText = d.medical_note || 'Examinee was clinically evaluated. No evidence of communicable disease or physical impairment detected.';
    const fitStatus = d.fitness_status || 'physically and mentally fit';

    body=`<div style="margin-bottom:14px;">
        <h4 style="font-size:13px;font-weight:700;margin:0 0 6px 0;color:#1e293b;">1. IDENTIFICATION INFORMATION</h4>
        <table style="width:100%;font-size:12px;border-collapse:collapse;">
          <tr><td style="padding:3px 0;width:140px;font-weight:600;">Full Name:</td><td>${d.name||''}</td><td style="padding:3px 0;width:100px;font-weight:600;">Age / Sex:</td><td>${d.age||'—'} yrs / ${d.sex||'—'}</td></tr>
          <tr><td style="padding:3px 0;font-weight:600;">Marital Status:</td><td>${d.marital||'—'}</td><td style="padding:3px 0;font-weight:600;">Place of Birth:</td><td>${d.pob||'—'}</td></tr>
          <tr><td style="padding:3px 0;font-weight:600;">Address:</td><td colspan="3">${d.address||'—'}</td></tr>
        </table>
      </div>

      <div style="margin-bottom:14px;">
        <h4 style="font-size:13px;font-weight:700;margin:0 0 6px 0;color:#1e293b;display:flex;justify-content:space-between;">
          <span>2. VITAL SIGNS RECORDED</span> ${visitNote}
        </h4>
        <table style="width:100%;font-size:12px;border-collapse:collapse;border:1px solid #cbd5e1;">
          <tr style="background:#f8fafc;"><td style="padding:4px 8px;border:1px solid #cbd5e1;font-weight:600;">BP:</td><td style="padding:4px 8px;border:1px solid #cbd5e1;">${bp}</td><td style="padding:4px 8px;border:1px solid #cbd5e1;font-weight:600;">Heart Rate:</td><td style="padding:4px 8px;border:1px solid #cbd5e1;">${hr}</td></tr>
          <tr><td style="padding:4px 8px;border:1px solid #cbd5e1;font-weight:600;">Resp Rate:</td><td style="padding:4px 8px;border:1px solid #cbd5e1;">${resp}</td><td style="padding:4px 8px;border:1px solid #cbd5e1;font-weight:600;">Temperature:</td><td style="padding:4px 8px;border:1px solid #cbd5e1;">${temp}</td></tr>
          <tr style="background:#f8fafc;"><td style="padding:4px 8px;border:1px solid #cbd5e1;font-weight:600;">SpO2:</td><td style="padding:4px 8px;border:1px solid #cbd5e1;">${spo2}</td><td style="padding:4px 8px;border:1px solid #cbd5e1;font-weight:600;">BMI:</td><td style="padding:4px 8px;border:1px solid #cbd5e1;">${bmi}</td></tr>
          <tr><td style="padding:4px 8px;border:1px solid #cbd5e1;font-weight:600;">Weight:</td><td style="padding:4px 8px;border:1px solid #cbd5e1;">${weight}</td><td style="padding:4px 8px;border:1px solid #cbd5e1;font-weight:600;">Height:</td><td style="padding:4px 8px;border:1px solid #cbd5e1;">${height}</td></tr>
        </table>
      </div>

      <div style="margin-bottom:14px;">
        <h4 style="font-size:13px;font-weight:700;margin:0 0 6px 0;color:#1e293b;">3. CLINICAL EXAMINATION</h4>
        <table style="width:100%;font-size:12px;border-collapse:collapse;border:1px solid #cbd5e1;">
          <tr><td style="padding:4px 8px;border:1px solid #cbd5e1;font-weight:600;width:35%;">Cardio-Respiratory:</td><td style="padding:4px 8px;border:1px solid #cbd5e1;">${d.cardio||'Normal'}</td></tr>
          <tr style="background:#f8fafc;"><td style="padding:4px 8px;border:1px solid #cbd5e1;font-weight:600;">Digestive System:</td><td style="padding:4px 8px;border:1px solid #cbd5e1;">${d.digestive||'Normal'}</td></tr>
          <tr><td style="padding:4px 8px;border:1px solid #cbd5e1;font-weight:600;">Endocrine / Glandular:</td><td style="padding:4px 8px;border:1px solid #cbd5e1;">${d.endocrine||'Normal'}</td></tr>
          <tr style="background:#f8fafc;"><td style="padding:4px 8px;border:1px solid #cbd5e1;font-weight:600;">Neurological:</td><td style="padding:4px 8px;border:1px solid #cbd5e1;">${d.neuro||'Normal'}</td></tr>
          <tr><td style="padding:4px 8px;border:1px solid #cbd5e1;font-weight:600;">Musculoskeletal:</td><td style="padding:4px 8px;border:1px solid #cbd5e1;">${d.musculo||'Normal'}</td></tr>
          <tr style="background:#f8fafc;"><td style="padding:4px 8px;border:1px solid #cbd5e1;font-weight:600;">Other Findings:</td><td style="padding:4px 8px;border:1px solid #cbd5e1;">${d.other||'None'}</td></tr>
          <tr><td style="padding:4px 8px;border:1px solid #cbd5e1;font-weight:600;">Laboratory Tests:</td><td style="padding:4px 8px;border:1px solid #cbd5e1;">${d.lab||'None'}</td></tr>
        </table>
      </div>

      <div style="margin-bottom:14px;">
        <h4 style="font-size:13px;font-weight:700;margin:0 0 6px 0;color:#1e293b;">4. MEDICAL NOTE & CONDITION</h4>
        <div style="background:#f8fafc;border:1px solid #cbd5e1;border-radius:6px;padding:8px 12px;font-size:12px;color:#1e293b;line-height:1.5;">${noteText}</div>
      </div>

      <div style="margin-bottom:14px;">
        <h4 style="font-size:13px;font-weight:700;margin:0 0 6px 0;color:#1e293b;">5. CERTIFICATION</h4>
        <p style="font-size:13px;margin:0;line-height:1.6;">I, Dr. <strong>${stripDr(d.physician||'')}</strong>, hereby certify that I have examined ${d.title||'Mr/Mrs/Ms'}. <strong>${d.name||''}</strong> and confirm that, based on the examination and vitals conducted, the examinee is <strong>${fitStatus}</strong>.</p>
        <p style="font-size:12px;color:#475569;margin:6px 0 0 0;">Issued at: ${d.location||'—'} | Date: ${d.issued||'—'}</p>
      </div>`;
  }
  
  return `<div id="gd-doc-printable" style="font-family:'Times New Roman',serif;color:#000;line-height:1.7;padding:20px;position:relative;">
    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(-30deg);font-size:50px;color:rgba(200,200,200,0.2);font-weight:700;pointer-events:none;white-space:nowrap;">COPY</div>
    <div style="position:relative;z-index:1;">
      <div style="text-align:center;margin-bottom:20px;padding-bottom:8px;border-bottom:2px solid #059669;">
        <h2 style="font-size:16px;font-weight:700;margin:0;">${title}</h2>
        <p style="font-size:11px;color:#666;margin:4px 0;">Ref: ${ref} | Date: ${date}</p>
      </div>
      <div style="font-size:14px;text-align:justify;">${body}</div>
      <div style="margin-top:30px;font-size:13px;display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:12px;padding-top:14px;border-top:2px solid #059669;">
        <div>
          <p style="margin:0 0 2px 0;">${tpl&&tpl.hr?'<strong>Issued by:</strong> '+(doc.physician_name||''):'<strong>Physician:</strong> Dr. '+stripDr(doc.physician_name||'')}</p>
          <p style="margin:4px 0 0 0;font-size:10px;color:#666;">This is an official certified copy of the original medical document</p>
        </div>
        <div style="display:flex;align-items:center;gap:8px;background:#f8fafc;border:1px solid #cbd5e1;border-radius:6px;padding:6px 10px;">
          <div id="gd-qr-code" style="width:64px;height:64px;background:white;padding:2px;border:1px solid #e2e8f0;border-radius:4px;display:flex;align-items:center;justify-content:center;"></div>
          <div>
            <div style="font-size:9px;font-weight:700;color:#059669;text-transform:uppercase;letter-spacing:0.4px;">✓ Verified Copy</div>
            <div style="font-size:11px;font-weight:700;color:#0f172a;margin-top:1px;">${ref}</div>
            <div style="font-size:9px;color:#64748b;margin-top:2px;">Scan with camera<br>to verify authenticity</div>
          </div>
        </div>
      </div>
    </div>
  </div>`;
}
</script>

<!-- View Document Modal -->
<div id="gd-view-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
  <div style="background:white;border-radius:12px;width:700px;max-width:95vw;max-height:90vh;overflow-y:auto;padding:0;">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid var(--gray-200);position:sticky;top:0;background:white;z-index:1;">
      <h3 style="font-size:16px;font-weight:600;">Document Copy</h3>
      <div style="display:flex;gap:8px;align-items:center;">
        <button onclick="gdPrintCopy()" style="padding:6px 14px;background:#059669;color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-print"></i> Print</button>
        <button onclick="document.getElementById('gd-view-modal').style.display='none'" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button>
      </div>
    </div>
    <div id="gd-view-content" style="padding:20px;"></div>
  </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<?php endif; ?>

<?php if ($subModule == 'medical-leave'): ?>
<!-- ====== MEDICAL LEAVE PARENTAL ====== -->

<!-- Preview Area -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-file-medical" style="color:var(--primary-color);"></i> Medical Certificate for Care of a Sick Child</h3>
    <div style="display:flex;gap:8px;">
      <button onclick="mlOpenForm()" style="padding:8px 16px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-edit"></i> Fill Details</button>
      <button onclick="mlPrint()" style="padding:8px 16px;background:#059669;color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-print"></i> Print</button>
      <button onclick="mlDownloadPDF()" style="padding:8px 16px;background:#1e40af;color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-file-pdf"></i> PDF</button>
    </div>
  </div>
  <div id="ml-preview" style="border:1px solid var(--gray-200);border-radius:8px;padding:40px;font-family:'Times New Roman',serif;font-size:14px;line-height:1.7;min-height:600px;position:relative;overflow:hidden;background:white;max-width:800px;margin:0 auto;">
  </div>
</div>

<!-- Form Modal -->
<div id="ml-form-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
  <div style="background:white;border-radius:12px;width:560px;max-width:95vw;padding:24px;max-height:90vh;overflow-y:auto;">
    <div style="display:flex;justify-content:space-between;margin-bottom:16px;">
      <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-edit" style="color:var(--primary-color);"></i> Fill Document Details</h3>
      <button onclick="mlCloseForm()" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Title (Mr/Mrs)</label>
        <select id="ml-title" onchange="mlUpdatePreview()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
          <option value="Mr">Mr</option>
          <option value="Mrs">Mrs</option>
          <option value="Ms">Ms</option>
        </select>
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Parent/Guardian Full Name *</label>
        <div style="position:relative;">
          <input id="ml-parent-name" type="text" placeholder="Type to search patient..." oninput="mlSearchPatient()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
          <div id="ml-patient-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:white;border:1px solid var(--gray-200);border-radius:6px;max-height:150px;overflow-y:auto;z-index:100;box-shadow:0 4px 12px rgba(0,0,0,0.1);margin-top:2px;"></div>
        </div>
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Child/Patient Full Name *</label>
        <input id="ml-child-name" type="text" placeholder="e.g. ISHIMWE Kevin" oninput="mlUpdatePreview()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Child Age (years)</label>
        <input id="ml-age" type="number" placeholder="e.g. 5" min="0" oninput="mlUpdatePreview()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div style="grid-column:1/-1;">
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Medical Condition / Diagnosis *</label>
        <input id="ml-condition" type="text" placeholder="e.g. Acute Bronchitis" oninput="mlUpdatePreview()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Leave Start Date *</label>
        <input id="ml-start" type="date" onchange="mlCalcDays();mlUpdatePreview()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Leave End Date *</label>
        <input id="ml-end" type="date" onchange="mlCalcDays();mlUpdatePreview()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Total Days (auto)</label>
        <input id="ml-days" type="text" readonly style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:var(--gray-50);">
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Resume Date</label>
        <input id="ml-resume" type="date" onchange="mlUpdatePreview()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Issued Date</label>
        <input id="ml-issued" type="date" onchange="mlUpdatePreview()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Physician's Name *</label>
        <input id="ml-physician" type="text" readonly style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:var(--gray-50);cursor:not-allowed;">
      </div>
    </div>
    <button onclick="mlCloseForm()" style="width:100%;margin-top:16px;padding:12px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px;"><i class="fas fa-check"></i> Done</button>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
let mlHospSettings = {};
let mlCurrentRef = null;

async function mlInit(){
  // Set default dates
  const today = new Date().toISOString().split('T')[0];
  document.getElementById('ml-issued').value = today;
  document.getElementById('ml-start').value = today;
  
  // Fetch hospital settings
  try{
    const r = await fetch(_mdBase + '/api.php?endpoint=settings/hospital');
    const j = await r.json();
    if(j.success) mlHospSettings = j.data || {};
  }catch(e){}
  // Pre-populate physician from logged-in user (injected by PHP)
  if(window._loggedUserName){
    document.getElementById('ml-physician').value = window._loggedUserName;
  }
  mlCurrentRef = await getNextDocRef('care_sick_child');
  mlUpdatePreview();
}

let mlSearchTimer;
async function mlSearchPatient(){
  clearTimeout(mlSearchTimer);
  mlSearchTimer=setTimeout(async()=>{
    const q=document.getElementById('ml-parent-name').value.trim();
    const dd=document.getElementById('ml-patient-dropdown');
    if(q.length<2){dd.style.display='none';mlUpdatePreview();return;}
    try{
      const r=await fetch(_mdBase + '/api.php?endpoint=patients/patients&search='+encodeURIComponent(q)+'&limit=8');
      const j=await r.json();
      const patients=j.data?.patients||[];
      if(!patients.length){dd.innerHTML='<div style="padding:8px 12px;color:var(--gray-400);font-size:12px;">No patients found</div>';dd.style.display='block';mlUpdatePreview();return;}
      dd.innerHTML=patients.map(p=>`<div onclick="mlSelectPatient('${(p.first_name+' '+p.last_name).replace(/'/g,"\\'")}','${p.gender||''}')" style="padding:8px 12px;cursor:pointer;font-size:12px;border-bottom:1px solid var(--gray-100);" onmouseover="this.style.background='var(--gray-50)'" onmouseout="this.style.background=''"><strong>${p.first_name} ${p.last_name}</strong> <span style="color:var(--gray-500);">${p.patient_no}</span></div>`).join('');
      dd.style.display='block';
    }catch(e){dd.style.display='none';}
    mlUpdatePreview();
  },300);
}
function mlSelectPatient(name,gender){
  document.getElementById('ml-parent-name').value=name;
  if(gender){document.getElementById('ml-title').value=gender==='female'?'Mrs':'Mr';}
  document.getElementById('ml-patient-dropdown').style.display='none';
  mlUpdatePreview();
}

function mlOpenForm(){
  document.getElementById('ml-form-modal').style.display = 'flex';
}
function mlCloseForm(){
  document.getElementById('ml-form-modal').style.display = 'none';
}

function mlCalcDays(){
  const start = document.getElementById('ml-start').value;
  const end = document.getElementById('ml-end').value;
  if(start && end){
    const diff = Math.ceil((new Date(end) - new Date(start)) / (1000*60*60*24)) + 1;
    document.getElementById('ml-days').value = diff > 0 ? diff + ' days' : '';
    // Auto-set resume date to day after end
    if(diff > 0){
      const resume = new Date(end);
      resume.setDate(resume.getDate() + 1);
      document.getElementById('ml-resume').value = resume.toISOString().split('T')[0];
    }
  }
}

function mlFormatDate(dateStr){
  if(!dateStr) return '....................';
  const d = new Date(dateStr);
  const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  return d.getDate()+' '+months[d.getMonth()]+' '+d.getFullYear();
}

function mlUpdatePreview(){
  const hs = mlHospSettings;
  const title = document.getElementById('ml-title').value;
  const parentName = document.getElementById('ml-parent-name').value || '..................................';
  const childName = document.getElementById('ml-child-name').value || '..................................';
  const age = document.getElementById('ml-age').value || '......';
  const condition = document.getElementById('ml-condition').value || '..................................';
  const startDate = document.getElementById('ml-start').value;
  const endDate = document.getElementById('ml-end').value;
  const days = document.getElementById('ml-days').value || '......';
  const resumeDate = document.getElementById('ml-resume').value;
  const issuedDate = document.getElementById('ml-issued').value;
  const physician = document.getElementById('ml-physician').value || '..................................';
  const hospName = hs.hospital_name || 'Iramiro Clinic';
  const refNo = mlCurrentRef || '';
  
  // Document settings
  const docLogo = hs.doc_logo || hs.logo || '';
  const hLineColor = hs.header_line_color || '#059669';
  const hLineSize = hs.header_line_size || '2';
  const hTextColor = hs.header_text_color || '#1f2937';
  const hFontSize = hs.header_font_size || '11';
  const hLeftText = hs.header_left || hospName;
  const hRightText = (hs.header_right || 'Date: {date}').replace('{date}', mlFormatDate(issuedDate)).replace('{ref}', refNo || 'CSC');
  const fLineColor = hs.footer_line_color || '#059669';
  const fLineSize = hs.footer_line_size || '2';
  const fTextColor = hs.footer_text_color || '#1f2937';
  const fFontSize = hs.footer_font_size || '9';
  const fLeftText = hs.footer_left || (hs.address||'Kigali, Rwanda');
  const fRightText = hs.footer_right || (hs.phone?'Tel: '+hs.phone+' | ':'')+'Email: '+(hs.email||'');
  
  // Watermark
  const wmEnabled = hs.watermark_enabled === '1';
  const wmType = hs.watermark_type || 'text';
  const wmText = hs.watermark_text || hospName;
  const wmColor = hs.watermark_color || '#d1d5db';
  const wmSize = hs.watermark_size || '40';
  const wmOpacity = (parseInt(hs.watermark_opacity)||15)/100;
  const wmRotation = hs.watermark_rotation || '-30';
  const wmImage = hs.watermark_image || '';
  
  let wmHtml = '';
  if(wmEnabled){
    if(wmType==='image' && wmImage){
      wmHtml = `<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(${wmRotation}deg);opacity:${wmOpacity};pointer-events:none;z-index:0;"><img src="${wmImage}" style="width:150px;height:auto;"></div>`;
    } else {
      wmHtml = `<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(${wmRotation}deg);opacity:${wmOpacity};pointer-events:none;z-index:0;font-size:${wmSize}px;color:${wmColor};font-weight:700;white-space:nowrap;">${wmText}</div>`;
    }
  }

  const html = `
    ${wmHtml}
    <div style="position:relative;z-index:1;">
      <!-- Header -->
      <img src="assets/letterhead.png" style="display:block;width:100%;height:auto;">
      <div style="display:flex;justify-content:flex-end;margin:4px 0 15px;">
        <span style="font-size:${hFontSize}px;color:${hTextColor};">${hRightText}</span>
      </div>
      
      <!-- Title -->
      <div style="text-align:center;margin-bottom:15px;">
        <h2 style="font-size:15px;font-weight:700;text-decoration:underline;margin:0;">MEDICAL CERTIFICATE FOR CARE OF A SICK CHILD</h2>
        ${refNo ? `<div style="font-size:11px;color:#64748b;margin-top:4px;">Ref: <strong>${refNo}</strong></div>` : ''}
      </div>
      
      <!-- Date -->
      <p style="margin-bottom:10px;"><strong>Date:</strong> ${mlFormatDate(issuedDate)}</p>
      
      <!-- Body -->
      <p style="margin-bottom:10px;"><strong>To Whom It May Concern,</strong></p>
      
      <p style="text-align:justify;margin-bottom:10px;">This is to certify that ${title}. <strong>${parentName}</strong> is the parent and primary caregiver of <strong>${childName}</strong>, aged <strong>${age}</strong> years, who has been under my medical care for <strong>${condition}</strong>.</p>
      
      <p style="text-align:justify;margin-bottom:10px;">The child requires close parental supervision and home care during the recovery period.</p>
      
      <p style="text-align:justify;margin-bottom:10px;">Therefore, ${title}. <strong>${parentName}</strong> is medically advised to take leave from <strong>${mlFormatDate(startDate)}</strong> to <strong>${mlFormatDate(endDate)}</strong> (a total of <strong>${days}</strong>) in order to provide the necessary care and support to the child.</p>
      
      <p style="text-align:justify;margin-bottom:15px;">The employee may resume normal duties on <strong>${mlFormatDate(resumeDate)}</strong>, provided the child's condition has sufficiently improved.</p>
      
      <p style="margin-bottom:5px;"><strong>Issued on:</strong> ${mlFormatDate(issuedDate)}</p>
      <p style="margin-bottom:15px;"><strong>At:</strong> ${hospName}</p>
      
      <!-- Signature & QR Code Block -->
      <div style="margin-top:20px;display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:12px;">
        <div>
          <p style="margin-bottom:3px;"><strong>Physician's Name & Signature:</strong></p>
          <p style="margin-bottom:15px;">${physician}</p>
          <p style="margin-bottom:3px;"><strong>Stamp:</strong></p>
          <div style="width:120px;height:40px;border-bottom:1px solid #333;"></div>
          <p style="font-size:9px;color:#666;margin-top:2px;">Official stamp</p>
        </div>
        <div style="display:flex;align-items:center;gap:8px;background:#f8fafc;border:1px solid #cbd5e1;border-radius:6px;padding:6px 10px;">
          <div id="ml-qr-code" style="width:68px;height:68px;background:white;padding:2px;border:1px solid #e2e8f0;border-radius:4px;display:flex;align-items:center;justify-content:center;"></div>
          <div>
            <div style="font-size:9px;font-weight:700;color:#059669;text-transform:uppercase;letter-spacing:0.4px;">✓ Verified Document</div>
            <div style="font-size:11px;font-weight:700;color:#0f172a;margin-top:1px;">${refNo}</div>
            <div style="font-size:9px;color:#64748b;margin-top:2px;">Scan with camera<br>to verify authenticity</div>
          </div>
        </div>
      </div>
      
      <!-- Footer -->
      <div style="display:flex;justify-content:space-between;align-items:center;padding-top:6px;border-top:${fLineSize}px solid ${fLineColor};margin-top:20px;">
        <span style="font-size:${fFontSize}px;color:${fTextColor};">${fLeftText}</span>
        <span style="font-size:${fFontSize}px;color:${fTextColor};">${fRightText}</span>
      </div>
    </div>
  `;
  
  document.getElementById('ml-preview').innerHTML = html;
  renderDocQRCode('ml-qr-code', refNo, 68);
}

function mlGetPrintContent(){
  const hs = mlHospSettings;
  const preview = document.getElementById('ml-preview');
  return preview.innerHTML;
}

async function mlPrint(){
  const parentName=document.getElementById('ml-parent-name').value||'';
  const physician=document.getElementById('ml-physician').value||'';
  if(!parentName){alert('Please fill in the parent/guardian name.');return;}
  const refToSave = mlCurrentRef;
  const ref=await saveDocument('care_sick_child',parentName,physician,{
    title:document.getElementById('ml-title').value,parent_name:parentName,
    child_name:document.getElementById('ml-child-name').value,age:document.getElementById('ml-age').value,
    condition:document.getElementById('ml-condition').value,start:document.getElementById('ml-start').value,
    end:document.getElementById('ml-end').value,days:document.getElementById('ml-days').value,
    resume:document.getElementById('ml-resume').value,issued:document.getElementById('ml-issued').value,physician,
    reference_no: refToSave
  }, refToSave);
  if(ref) console.log('Saved: '+ref);
  const content = document.getElementById('ml-preview').innerHTML;
  const win = window.open('','_blank','width=800,height=600');
  win.document.write(`<html><head><title>Medical Certificate for Care of a Sick Child</title><style>
    body{font-family:'Times New Roman',serif;padding:40px;color:#000;line-height:1.6;font-size:14px;}
    img{max-width:100%;}
    @media print{body{padding:20px;}}
  </style></head><body><div style="position:relative;overflow:hidden;">${content}</div></body></html>`);
  win.document.close();
  win.focus();
  setTimeout(()=>{win.print();},500);
  mlCurrentRef = await getNextDocRef('care_sick_child');
  mlUpdatePreview();
}

async function mlDownloadPDF(){
  const parentName=document.getElementById('ml-parent-name').value||'Patient';
  const physician=document.getElementById('ml-physician').value||'';
  if(!parentName||parentName==='Patient'){alert('Please fill in the parent/guardian name.');return;}
  const refToSave = mlCurrentRef;
  const ref=await saveDocument('care_sick_child',parentName,physician,{
    title:document.getElementById('ml-title').value,parent_name:parentName,
    child_name:document.getElementById('ml-child-name').value,age:document.getElementById('ml-age').value,
    condition:document.getElementById('ml-condition').value,start:document.getElementById('ml-start').value,
    end:document.getElementById('ml-end').value,days:document.getElementById('ml-days').value,
    resume:document.getElementById('ml-resume').value,issued:document.getElementById('ml-issued').value,physician,
    reference_no: refToSave
  }, refToSave);
  if(ref) console.log('Saved: '+ref);
  const element = document.getElementById('ml-preview');
  const opt = {
    margin: 10,
    filename: (refToSave||'CSC')+'_'+parentName.replace(/\s+/g,'_')+'.pdf',
    image: {type:'jpeg',quality:0.98},
    html2canvas: {scale:2, useCORS:true},
    jsPDF: {unit:'mm',format:'a4',orientation:'portrait'}
  };
  html2pdf().set(opt).from(element).save().then(async ()=>{
    mlCurrentRef = await getNextDocRef('care_sick_child');
    mlUpdatePreview();
  });
}

mlInit();
</script>
<?php endif; ?>

<?php if ($subModule == 'leave-certificate'): ?>
<!-- ====== MEDICAL LEAVE CERTIFICATE ====== -->

<!-- Preview Area -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-notes-medical" style="color:var(--primary-color);"></i> Medical Leave Certificate</h3>
    <div style="display:flex;gap:8px;">
      <button onclick="lcOpenForm()" style="padding:8px 16px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-edit"></i> Fill Details</button>
      <button onclick="lcPrint()" style="padding:8px 16px;background:#059669;color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-print"></i> Print</button>
      <button onclick="lcDownloadPDF()" style="padding:8px 16px;background:#1e40af;color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-file-pdf"></i> PDF</button>
    </div>
  </div>
  <div id="lc-preview" style="border:1px solid var(--gray-200);border-radius:8px;padding:40px;font-family:'Times New Roman',serif;font-size:14px;line-height:1.7;min-height:500px;position:relative;overflow:hidden;background:white;max-width:800px;margin:0 auto;">
  </div>
</div>

<!-- Form Modal -->
<div id="lc-form-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
  <div style="background:white;border-radius:12px;width:500px;max-width:95vw;padding:24px;max-height:90vh;overflow-y:auto;">
    <div style="display:flex;justify-content:space-between;margin-bottom:16px;">
      <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-edit" style="color:var(--primary-color);"></i> Fill Document Details</h3>
      <button onclick="lcCloseForm()" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
      <div style="grid-column:1/-1;">
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Physician's Name *</label>
        <input id="lc-physician" type="text" readonly style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:var(--gray-50);cursor:not-allowed;">
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Title (Mr/Mrs)</label>
        <select id="lc-title" onchange="lcUpdatePreview()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
          <option value="Mr">Mr</option>
          <option value="Mrs">Mrs</option>
          <option value="Ms">Ms</option>
        </select>
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Patient Full Name *</label>
        <div style="position:relative;">
          <input id="lc-patient-search" type="text" placeholder="Type to search patient..." oninput="lcSearchPatient()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
          <input id="lc-patient" type="hidden">
          <div id="lc-patient-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:white;border:1px solid var(--gray-200);border-radius:6px;max-height:150px;overflow-y:auto;z-index:100;box-shadow:0 4px 12px rgba(0,0,0,0.1);margin-top:2px;"></div>
        </div>
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Number of Days *</label>
        <input id="lc-days" type="number" placeholder="e.g. 5" min="1" oninput="lcUpdatePreview()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Leave Start Date *</label>
        <input id="lc-start" type="date" onchange="lcUpdatePreview()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Leave End Date *</label>
        <input id="lc-end" type="date" onchange="lcUpdatePreview()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Issued Date</label>
        <input id="lc-issued" type="date" onchange="lcUpdatePreview()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div style="grid-column:1/-1;">
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Medical notes (doctor)</label>
        <textarea id="lc-notes" rows="3" oninput="lcUpdatePreview()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;resize:vertical;"></textarea>
      </div>
    </div>
    <button onclick="lcCloseForm()" style="width:100%;margin-top:16px;padding:12px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px;"><i class="fas fa-check"></i> Done</button>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
let lcHospSettings = {};
let lcCurrentRef = null;

async function lcInit(){
  const today = new Date().toISOString().split('T')[0];
  document.getElementById('lc-issued').value = today;
  document.getElementById('lc-start').value = today;
  try{
    const r = await fetch(_mdBase + '/api.php?endpoint=settings/hospital');
    const j = await r.json();
    if(j.success) lcHospSettings = j.data || {};
  }catch(e){}
  if(window._loggedUserName) document.getElementById('lc-physician').value = window._loggedUserName;
  lcCurrentRef = await getNextDocRef('leave_certificate');
  lcUpdatePreview();
  const qpid = parseInt(new URLSearchParams(location.search).get('patient_id') || '0');
  if(qpid) lcLoadClinical(qpid);
}

let lcSearchTimer;
async function lcSearchPatient(){
  clearTimeout(lcSearchTimer);
  lcSearchTimer=setTimeout(async()=>{
    const q=document.getElementById('lc-patient-search').value.trim();
    const dd=document.getElementById('lc-patient-dropdown');
    if(q.length<2){dd.style.display='none';return;}
    try{
      const r=await fetch(_mdBase + '/api.php?endpoint=patients/patients&search='+encodeURIComponent(q)+'&limit=8');
      const j=await r.json();
      const patients=j.data?.patients||[];
      if(!patients.length){dd.innerHTML='<div style="padding:8px 12px;color:var(--gray-400);font-size:12px;">No patients found</div>';dd.style.display='block';return;}
      dd.innerHTML=patients.map(p=>`<div onclick="lcSelectPatient('${(p.first_name+' '+p.last_name).replace(/'/g,"\\'")}','${p.gender||''}',${p.id})" style="padding:8px 12px;cursor:pointer;font-size:12px;border-bottom:1px solid var(--gray-100);" onmouseover="this.style.background='var(--gray-50)'" onmouseout="this.style.background=''"><strong>${p.first_name} ${p.last_name}</strong> <span style="color:var(--gray-500);">${p.patient_no}</span></div>`).join('');
      dd.style.display='block';
    }catch(e){dd.style.display='none';}
  },300);
}
let lcClinical = null;
// Name, ID, age, sex and latest vitals come from the server, not from typing
async function lcLoadClinical(pid){
  lcClinical = null;
  try{
    const j = await (await fetch(docSaveAPI+'&action=patient_clinical&patient_id='+pid)).json();
    if(j.success){
      lcClinical = j.data;
      document.getElementById('lc-patient-search').value = lcClinical.name;
      document.getElementById('lc-patient').value = lcClinical.name;
      document.getElementById('lc-title').value = String(lcClinical.sex).toLowerCase()==='female' ? 'Mrs' : 'Mr';
    }
  }catch(e){}
  lcUpdatePreview();
}
function lcSelectPatient(name,gender,pid){
  document.getElementById('lc-patient-search').value=name;
  document.getElementById('lc-patient').value=name;
  if(gender){document.getElementById('lc-title').value=gender==='female'?'Mrs':'Mr';}
  document.getElementById('lc-patient-dropdown').style.display='none';
  if(pid) lcLoadClinical(pid); else { lcClinical = null; lcUpdatePreview(); }
}

function lcOpenForm(){ document.getElementById('lc-form-modal').style.display = 'flex'; }
function lcCloseForm(){ document.getElementById('lc-form-modal').style.display = 'none'; }

function lcFormatDate(dateStr){
  if(!dateStr) return '____/____/20____';
  const d = new Date(dateStr);
  const dd = String(d.getDate()).padStart(2,'0');
  const mm = String(d.getMonth()+1).padStart(2,'0');
  const yyyy = d.getFullYear();
  return dd+'/'+mm+'/'+yyyy;
}

function lcEsc(s){return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function lcClinicalHtml(){
  const c = lcClinical, notes = document.getElementById('lc-notes').value.trim();
  if(!c && !notes) return '';
  let h = '';
  if(c){
    const v = c.vitals || {};
    const vit = [['BP',v.bp,'mmHg'],['HR',v.hr,'bpm'],['RR',v.rr,'/min'],['Temp',v.temp,'\u00b0C'],['SpO2',v.spo2,'%'],['Weight',v.weight,'kg'],['Height',v.height,'cm'],['BMI',v.bmi,'']].filter(x=>x[1]).map(x=>x[0]+': '+lcEsc(x[1])+' '+x[2]).join(' | ');
    h += '<p style="margin-bottom:6px;font-size:13px;"><strong>Patient ID:</strong> '+lcEsc(c.patient_no)+' | <strong>Age:</strong> '+lcEsc(c.age==null?'-':c.age)+' | <strong>Sex:</strong> '+lcEsc(c.sex||'-')+'</p>';
    if(vit) h += '<p style="margin-bottom:10px;font-size:13px;"><strong>Latest vitals:</strong> '+vit+'</p>';
  }
  if(notes) h += '<p style="margin-bottom:15px;font-size:13px;white-space:pre-wrap;"><strong>Medical notes:</strong> '+lcEsc(notes)+'</p>';
  return h;
}

function lcUpdatePreview(){
  const hs = lcHospSettings;
  const physician = document.getElementById('lc-physician').value || '..............................';
  const title = document.getElementById('lc-title').value;
  const patient = document.getElementById('lc-patient').value || document.getElementById('lc-patient-search').value || '..............................';
  const days = document.getElementById('lc-days').value || '.....';
  const startDate = document.getElementById('lc-start').value;
  const endDate = document.getElementById('lc-end').value;
  const issuedDate = document.getElementById('lc-issued').value;
  const hospName = hs.hospital_name || 'Hospital';
  const refNo = lcCurrentRef || '';
  
  // Document settings
  const docLogo = hs.doc_logo || hs.logo || '';
  const hLineColor = hs.header_line_color || '#059669';
  const hLineSize = hs.header_line_size || '2';
  const hTextColor = hs.header_text_color || '#1f2937';
  const hFontSize = hs.header_font_size || '11';
  const hLeftText = hs.header_left || hospName;
  const hRightText = (hs.header_right || 'Date: {date}').replace('{date}', lcFormatDate(issuedDate)).replace('{ref}', refNo || 'MLC');
  const fLineColor = hs.footer_line_color || '#059669';
  const fLineSize = hs.footer_line_size || '2';
  const fTextColor = hs.footer_text_color || '#1f2937';
  const fFontSize = hs.footer_font_size || '9';
  const fLeftText = hs.footer_left || (hs.address||'Kigali, Rwanda');
  const fRightText = hs.footer_right || (hs.phone?'Tel: '+hs.phone+' | ':'')+'Email: '+(hs.email||'');
  
  // Watermark
  const wmEnabled = hs.watermark_enabled === '1';
  const wmType = hs.watermark_type || 'text';
  const wmText = hs.watermark_text || hospName;
  const wmColor = hs.watermark_color || '#d1d5db';
  const wmSize = hs.watermark_size || '40';
  const wmOpacity = (parseInt(hs.watermark_opacity)||15)/100;
  const wmRotation = hs.watermark_rotation || '-30';
  const wmImage = hs.watermark_image || '';
  
  let wmHtml = '';
  if(wmEnabled){
    if(wmType==='image' && wmImage){
      wmHtml = `<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(${wmRotation}deg);opacity:${wmOpacity};pointer-events:none;z-index:0;"><img src="${wmImage}" style="width:150px;height:auto;"></div>`;
    } else {
      wmHtml = `<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(${wmRotation}deg);opacity:${wmOpacity};pointer-events:none;z-index:0;font-size:${wmSize}px;color:${wmColor};font-weight:700;white-space:nowrap;">${wmText}</div>`;
    }
  }

  const html = `
    ${wmHtml}
    <div style="position:relative;z-index:1;">
      <!-- Header -->
      <img src="assets/letterhead.png" style="display:block;width:100%;height:auto;">
      <div style="display:flex;justify-content:flex-end;margin:4px 0 20px;">
        <span style="font-size:${hFontSize}px;color:${hTextColor};">${hRightText}</span>
      </div>
      
      <!-- Title -->
      <div style="text-align:center;margin-bottom:20px;">
        <h2 style="font-size:16px;font-weight:700;text-decoration:underline;margin:0;">MEDICAL LEAVE CERTIFICATE</h2>
        ${refNo ? `<div style="font-size:11px;color:#64748b;margin-top:4px;">Ref: <strong>${refNo}</strong></div>` : ''}
      </div>
      
      <!-- Body -->
      <p style="text-align:justify;margin-bottom:15px;">I, the undersigned, Dr. <strong>${stripDr(physician)}</strong>, a licensed physician at <strong>${hospName}</strong>, hereby certify that ${title}. <strong>${patient}</strong> and have determined that their medical condition requires <strong>${days}</strong> days of medical leave, from <strong>${lcFormatDate(startDate)}</strong> to <strong>${lcFormatDate(endDate)}</strong>.</p>
      
      ${lcClinicalHtml()}
      <p style="text-align:justify;margin-bottom:25px;">This certificate is issued at the request of the patient for all lawful purposes.</p>
      
      <!-- Issued -->
      <p style="margin-bottom:5px;"><strong>Issued on:</strong> ${lcFormatDate(issuedDate)}</p>
      <p style="margin-bottom:20px;"><strong>At:</strong> ${hospName}</p>
      
      <!-- Signature & QR Code Block -->
      <div style="margin-top:30px;display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:12px;">
        <div>
          <p style="margin-bottom:3px;"><strong>Physician's Name & Signature:</strong></p>
          <p style="margin-bottom:20px;">Dr. ${stripDr(physician)}</p>
          <p style="margin-bottom:3px;"><strong>Stamp:</strong></p>
          <div style="width:120px;height:40px;border-bottom:1px solid #333;"></div>
          <p style="font-size:9px;color:#666;margin-top:2px;">Official stamp</p>
        </div>
        <div style="display:flex;align-items:center;gap:8px;background:#f8fafc;border:1px solid #cbd5e1;border-radius:6px;padding:6px 10px;">
          <div id="lc-qr-code" style="width:68px;height:68px;background:white;padding:2px;border:1px solid #e2e8f0;border-radius:4px;display:flex;align-items:center;justify-content:center;"></div>
          <div>
            <div style="font-size:9px;font-weight:700;color:#059669;text-transform:uppercase;letter-spacing:0.4px;">✓ Verified Document</div>
            <div style="font-size:11px;font-weight:700;color:#0f172a;margin-top:1px;">${refNo}</div>
            <div style="font-size:9px;color:#64748b;margin-top:2px;">Scan with camera<br>to verify authenticity</div>
          </div>
        </div>
      </div>
      
      <!-- Footer -->
      <div style="display:flex;justify-content:space-between;align-items:center;padding-top:6px;border-top:${fLineSize}px solid ${fLineColor};margin-top:30px;">
        <span style="font-size:${fFontSize}px;color:${fTextColor};">${fLeftText}</span>
        <span style="font-size:${fFontSize}px;color:${fTextColor};">${fRightText}</span>
      </div>
    </div>
  `;
  
  document.getElementById('lc-preview').innerHTML = html;
  renderDocQRCode('lc-qr-code', refNo, 68);
}

async function lcPrint(){
  const patient=document.getElementById('lc-patient').value||document.getElementById('lc-patient-search').value||'';
  const physician=document.getElementById('lc-physician').value||'';
  if(!patient){alert('Please fill in the patient name.');return;}
  const refToSave = lcCurrentRef;
  await saveDocument('leave_certificate',patient,physician,{
    title:document.getElementById('lc-title').value,patient,physician,
    days:document.getElementById('lc-days').value,start:document.getElementById('lc-start').value,
    end:document.getElementById('lc-end').value,issued:document.getElementById('lc-issued').value,
    clinical: lcClinical, medical_notes: document.getElementById('lc-notes').value.trim(),
    reference_no: refToSave
  }, refToSave);
  const content = document.getElementById('lc-preview').innerHTML;
  const win = window.open('','_blank','width=800,height=600');
  win.document.write(`<html><head><title>Medical Leave Certificate</title><style>
    body{font-family:'Times New Roman',serif;padding:40px;color:#000;line-height:1.7;font-size:14px;}
    img{max-width:100%;}
    @media print{body{padding:20px;}}
  </style></head><body><div style="position:relative;overflow:hidden;">${content}</div></body></html>`);
  win.document.close();
  win.focus();
  setTimeout(()=>{win.print();},500);
  lcCurrentRef = await getNextDocRef('leave_certificate');
  lcUpdatePreview();
}

async function lcDownloadPDF(){
  const patient=document.getElementById('lc-patient').value||'Patient';
  const physician=document.getElementById('lc-physician').value||'';
  if(!patient||patient==='Patient'){alert('Please fill in the patient name.');return;}
  const refToSave = lcCurrentRef;
  await saveDocument('leave_certificate',patient,physician,{
    title:document.getElementById('lc-title').value,patient,physician,
    days:document.getElementById('lc-days').value,start:document.getElementById('lc-start').value,
    end:document.getElementById('lc-end').value,issued:document.getElementById('lc-issued').value,
    clinical: lcClinical, medical_notes: document.getElementById('lc-notes').value.trim(),
    reference_no: refToSave
  }, refToSave);
  const element = document.getElementById('lc-preview');
  const opt = {
    margin: 10,
    filename: (refToSave||'MLC')+'_'+patient.replace(/\s+/g,'_')+'.pdf',
    image: {type:'jpeg',quality:0.98},
    html2canvas: {scale:2, useCORS:true},
    jsPDF: {unit:'mm',format:'a4',orientation:'portrait'}
  };
  html2pdf().set(opt).from(element).save().then(async ()=>{
    lcCurrentRef = await getNextDocRef('leave_certificate');
    lcUpdatePreview();
  });
}

lcInit();
</script>
<?php endif; ?>

<?php if ($subModule == 'test-certificate'): ?>
<!-- ====== TEST CERTIFICATE ====== -->

<!-- Step 1: Patient + Visit selector -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);margin-bottom:16px;">
  <h3 style="font-size:15px;font-weight:600;margin-bottom:16px;"><i class="fas fa-search" style="color:var(--primary-color);"></i> Step 1: Select Patient Visit</h3>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
    <div>
      <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Patient *</label>
      <div style="position:relative;">
        <input id="tc-patient-search" type="text" placeholder="Type to search patient..." oninput="tcSearchPatient()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
        <input id="tc-patient" type="hidden">
        <input id="tc-patient-id" type="hidden">
        <div id="tc-patient-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:white;border:1px solid var(--gray-200);border-radius:6px;max-height:180px;overflow-y:auto;z-index:100;box-shadow:0 4px 12px rgba(0,0,0,0.1);margin-top:2px;"></div>
      </div>
    </div>
    <div>
      <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Visit *</label>
      <select id="tc-visit" onchange="tcLoadLabTests()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;" disabled>
        <option value="">— select patient first —</option>
      </select>
    </div>
  </div>

  <!-- Step 2: Lab tests list -->
  <div id="tc-tests-section" style="display:none;margin-top:20px;">
    <h3 style="font-size:15px;font-weight:600;margin-bottom:10px;"><i class="fas fa-flask" style="color:#7c3aed;"></i> Step 2: Select Lab Tests for Certificate</h3>
    <div id="tc-tests-list" style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;"></div>
    <button onclick="tcBuildFromSelected()" id="tc-build-btn" style="margin-top:12px;padding:9px 20px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;display:none;"><i class="fas fa-file-alt"></i> Build Certificate</button>
  </div>
</div>

<!-- Preview Area -->
<div id="tc-preview-card" style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);display:none;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-vial" style="color:var(--primary-color);"></i> Test Certificate Preview</h3>
    <div style="display:flex;gap:8px;">
      <button onclick="tcOpenForm()" style="padding:8px 16px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-edit"></i> Edit Details</button>
      <button onclick="tcPrint()" style="padding:8px 16px;background:#059669;color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-print"></i> Print</button>
      <button onclick="tcDownloadPDF()" style="padding:8px 16px;background:#1e40af;color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-file-pdf"></i> PDF</button>
    </div>
  </div>
  <div id="tc-preview" style="border:1px solid var(--gray-200);border-radius:8px;padding:40px;font-family:'Times New Roman',serif;font-size:14px;line-height:1.7;min-height:400px;position:relative;overflow:hidden;background:white;max-width:800px;margin:0 auto;"></div>
</div>

<!-- Edit Details Modal -->
<div id="tc-form-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
  <div style="background:white;border-radius:12px;width:460px;max-width:95vw;padding:24px;max-height:90vh;overflow-y:auto;">
    <div style="display:flex;justify-content:space-between;margin-bottom:16px;">
      <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-edit" style="color:var(--primary-color);"></i> Edit Details</h3>
      <button onclick="tcCloseForm()" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Title</label>
        <select id="tc-title" onchange="tcUpdatePreview()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
          <option value="Mr">Mr</option><option value="Mrs">Mrs</option><option value="Ms">Ms</option>
        </select>
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Date of Birth</label>
        <input id="tc-dob" type="date" onchange="tcUpdatePreview()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Issued Date</label>
        <input id="tc-issued" type="date" onchange="tcUpdatePreview()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Physician</label>
        <input id="tc-physician" type="text" readonly style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:var(--gray-50);">
      </div>
    </div>
    <button onclick="tcCloseForm()" style="width:100%;margin-top:16px;padding:12px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px;"><i class="fas fa-check"></i> Done</button>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
let tcHospSettings = {};
let tcSelectedTests = []; // [{description, result}]
let tcCurrentRef = null;

async function tcInit(){
  const today = new Date().toISOString().split('T')[0];
  document.getElementById('tc-issued').value = today;
  try{
    const r = await fetch(_mdBase + '/api.php?endpoint=settings/hospital');
    const j = await r.json();
    if(j.success) tcHospSettings = j.data || {};
  }catch(e){}
  if(window._loggedUserName) document.getElementById('tc-physician').value = window._loggedUserName;
  tcCurrentRef = await getNextDocRef('test_certificate');
}

// ---- Patient Search ----
let tcSearchTimer;
async function tcSearchPatient(){
  clearTimeout(tcSearchTimer);
  tcSearchTimer = setTimeout(async()=>{
    const q = document.getElementById('tc-patient-search').value.trim();
    const dd = document.getElementById('tc-patient-dropdown');
    if(q.length < 2){ dd.style.display='none'; return; }
    try{
      const r = await fetch(_mdBase + '/api.php?endpoint=patients/patients&search='+encodeURIComponent(q)+'&limit=8');
      const j = await r.json();
      const patients = j.data?.patients || [];
      if(!patients.length){ dd.innerHTML='<div style="padding:8px 12px;color:var(--gray-400);font-size:12px;">No patients found</div>'; dd.style.display='block'; return; }
      dd.innerHTML = patients.map(p=>`<div onclick="tcSelectPatient(${p.id},'${(p.first_name+' '+p.last_name).replace(/'/g,"\\'")}','${p.date_of_birth||''}','${p.gender||''}')" style="padding:8px 12px;cursor:pointer;font-size:12px;border-bottom:1px solid var(--gray-100);" onmouseover="this.style.background='var(--gray-50)'" onmouseout="this.style.background=''"><strong>${p.first_name} ${p.last_name}</strong> <span style="color:var(--gray-500);">${p.patient_no}</span></div>`).join('');
      dd.style.display = 'block';
    }catch(e){ dd.style.display='none'; }
  }, 300);
}

async function tcSelectPatient(id, name, dob, gender){
  document.getElementById('tc-patient-search').value = name;
  document.getElementById('tc-patient').value = name;
  document.getElementById('tc-patient-id').value = id;
  if(dob) document.getElementById('tc-dob').value = dob;
  if(gender) document.getElementById('tc-title').value = gender==='female'?'Mrs':'Mr';
  document.getElementById('tc-patient-dropdown').style.display = 'none';
  // Load visits for this patient
  await tcLoadVisits(id);
}

async function tcLoadVisits(patientId){
  const sel = document.getElementById('tc-visit');
  sel.innerHTML = '<option value="">Loading...</option>';
  sel.disabled = true;
  try{
    const r = await fetch(_mdBase + '/api.php?endpoint=patients/visits&action=patient_visits&patient_id='+patientId);
    const j = await r.json();
    const visits = j.data?.visits || [];
    if(!visits.length){ sel.innerHTML='<option value="">No visits found</option>'; return; }
    sel.innerHTML = '<option value="">— select visit —</option>' +
      visits.map(v=>`<option value="${v.id}">${v.visit_no} — ${v.visit_date} (${v.status})</option>`).join('');
    sel.disabled = false;
  }catch(e){
    sel.innerHTML = '<option value="">Error loading visits</option>';
  }
}

async function tcLoadLabTests(){
  const visitId = document.getElementById('tc-visit').value;
  const section  = document.getElementById('tc-tests-section');
  const listEl   = document.getElementById('tc-tests-list');
  const buildBtn = document.getElementById('tc-build-btn');
  if(!visitId){ section.style.display='none'; return; }

  section.style.display = 'block';
  listEl.innerHTML = '<div style="padding:20px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Loading lab tests...</div>';
  buildBtn.style.display = 'none';

  try{
    const r = await fetch(_mdBase + '/api.php?endpoint=patients/visits&action=medical_orders&visit_id='+visitId+'&order_type=lab');
    const j = await r.json();
    const orders = j.data?.orders || [];
    if(!orders.length){
      listEl.innerHTML = '<div style="padding:20px;text-align:center;color:var(--gray-400);">No lab tests found for this visit.</div>';
      return;
    }
    listEl.innerHTML = `<table style="width:100%;border-collapse:collapse;font-size:13px;">
      <thead><tr style="background:var(--gray-50);">
        <th style="padding:8px 12px;text-align:center;width:40px;"><input type="checkbox" onchange="tcToggleAll(this)" title="Select all"></th>
        <th style="padding:8px 12px;text-align:left;">Test Name</th>
        <th style="padding:8px 12px;text-align:left;">Result</th>
        <th style="padding:8px 12px;text-align:center;">Status</th>
      </tr></thead>
      <tbody>
        ${orders.map(o=>`<tr style="border-bottom:1px solid var(--gray-100);">
          <td style="padding:8px 12px;text-align:center;"><input type="checkbox" class="tc-test-cb" data-desc="${o.description.replace(/"/g,'&quot;')}" data-result="${(o.result||'').replace(/"/g,'&quot;')}" onchange="tcCheckBuildBtn()"></td>
          <td style="padding:8px 12px;font-weight:500;">${o.description}</td>
          <td style="padding:8px 12px;color:${o.result?'#059669':'var(--gray-400)'};">${o.result||'<em>Pending</em>'}</td>
          <td style="padding:8px 12px;text-align:center;"><span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;background:${o.status==='completed'?'#d1fae5':'#fef3c7'};color:${o.status==='completed'?'#065f46':'#92400e'};">${o.status}</span></td>
        </tr>`).join('')}
      </tbody>
    </table>`;
    buildBtn.style.display = 'inline-block';
  }catch(e){
    listEl.innerHTML = '<div style="padding:20px;text-align:center;color:#dc2626;">Error: '+e.message+'</div>';
  }
}

function tcToggleAll(masterCb){
  document.querySelectorAll('.tc-test-cb').forEach(cb => cb.checked = masterCb.checked);
  tcCheckBuildBtn();
}

function tcCheckBuildBtn(){
  const any = [...document.querySelectorAll('.tc-test-cb')].some(cb=>cb.checked);
  document.getElementById('tc-build-btn').style.display = any ? 'inline-block' : 'none';
}

function tcBuildFromSelected(){
  tcSelectedTests = [...document.querySelectorAll('.tc-test-cb:checked')].map(cb=>({
    description: cb.dataset.desc,
    result: cb.dataset.result || 'Pending'
  }));
  if(!tcSelectedTests.length){ alert('Please select at least one test.'); return; }
  document.getElementById('tc-preview-card').style.display = 'block';
  document.getElementById('tc-preview-card').scrollIntoView({behavior:'smooth', block:'start'});
  tcUpdatePreview();
}

function tcOpenForm(){ document.getElementById('tc-form-modal').style.display='flex'; }
function tcCloseForm(){ document.getElementById('tc-form-modal').style.display='none'; }

function tcFormatDate(dateStr){
  if(!dateStr) return '____/____/______';
  const d = new Date(dateStr);
  return String(d.getDate()).padStart(2,'0')+'/'+String(d.getMonth()+1).padStart(2,'0')+'/'+d.getFullYear();
}

function tcUpdatePreview(){
  const hs = tcHospSettings;
  const physician  = document.getElementById('tc-physician').value || '..............................';
  const title      = document.getElementById('tc-title').value;
  const patient    = document.getElementById('tc-patient').value || document.getElementById('tc-patient-search').value || '..............................';
  const dob        = document.getElementById('tc-dob').value;
  const issuedDate = document.getElementById('tc-issued').value;
  const hospName   = hs.hospital_name || 'Hospital';

  const docLogo    = hs.doc_logo || hs.logo || '';
  const hLineColor = hs.header_line_color || '#059669';
  const hLineSize  = hs.header_line_size  || '2';
  const hTextColor = hs.header_text_color || '#1f2937';
  const hFontSize  = hs.header_font_size  || '11';
  const hLeftText  = hs.header_left  || hospName;
  const refNo      = tcCurrentRef || '';
  const hRightText = (hs.header_right||'Date: {date}').replace('{date}', tcFormatDate(issuedDate)).replace('{ref}', refNo || 'TST');
  const fLineColor = hs.footer_line_color || '#059669';
  const fLineSize  = hs.footer_line_size  || '2';
  const fTextColor = hs.footer_text_color || '#1f2937';
  const fFontSize  = hs.footer_font_size  || '9';
  const fLeftText  = hs.footer_left  || (hs.address||'Kigali, Rwanda');
  const fRightText = hs.footer_right || (hs.phone?'Tel: '+hs.phone+' | ':'')+'Email: '+(hs.email||'');

  const wmEnabled  = hs.watermark_enabled === '1';
  const wmOpacity  = (parseInt(hs.watermark_opacity)||15)/100;
  const wmRotation = hs.watermark_rotation || '-30';
  let wmHtml = '';
  if(wmEnabled){
    if(hs.watermark_type==='image' && hs.watermark_image){
      wmHtml = `<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(${wmRotation}deg);opacity:${wmOpacity};pointer-events:none;z-index:0;"><img src="${hs.watermark_image}" style="width:150px;height:auto;"></div>`;
    } else {
      wmHtml = `<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(${wmRotation}deg);opacity:${wmOpacity};pointer-events:none;z-index:0;font-size:${hs.watermark_size||40}px;color:${hs.watermark_color||'#d1d5db'};font-weight:700;white-space:nowrap;">${hs.watermark_text||hospName}</div>`;
    }
  }

  // Build tests rows from selected — result can be plain text or JSON array of parameters
  function tcRenderResult(description, result){
    // Try to parse as JSON
    let parsed = null;
    if(result && result.trim().startsWith('[')){
      try{ parsed = JSON.parse(result); }catch(e){}
    }
    if(parsed && Array.isArray(parsed) && parsed.length){
      // Structured: render a sub-table with parameters
      const paramRows = parsed.map(p => {
        const val = parseFloat(p.value);
        const min = parseFloat(p.normal_min);
        const max = parseFloat(p.normal_max);
        const isHigh = !isNaN(val) && !isNaN(max) && val > max;
        const isLow  = !isNaN(val) && !isNaN(min) && val < min;
        const flag   = isHigh ? ' <span style="color:#dc2626;font-weight:700;">H</span>' : isLow ? ' <span style="color:#1e40af;font-weight:700;">L</span>' : '';
        return `<tr>
          <td style="padding:3px 8px;border:1px solid #ddd;">${p.test_name}</td>
          <td style="padding:3px 8px;border:1px solid #ddd;font-weight:600;">${p.value}${flag}</td>
          <td style="padding:3px 8px;border:1px solid #ddd;color:#666;">${p.unit||''}</td>
          <td style="padding:3px 8px;border:1px solid #ddd;color:#666;">${p.normal_min||''} – ${p.normal_max||''}</td>
        </tr>`;
      }).join('');
      return `<td style="padding:6px 10px;" colspan="1">
        <table style="width:100%;border-collapse:collapse;font-size:11px;">
          <thead><tr style="background:#f5f5f5;">
            <th style="padding:3px 8px;border:1px solid #ddd;text-align:left;">Parameter</th>
            <th style="padding:3px 8px;border:1px solid #ddd;text-align:left;">Value</th>
            <th style="padding:3px 8px;border:1px solid #ddd;text-align:left;">Unit</th>
            <th style="padding:3px 8px;border:1px solid #ddd;text-align:left;">Normal Range</th>
          </tr></thead>
          <tbody>${paramRows}</tbody>
        </table>
      </td>`;
    }
    // Plain text result
    const color = result ? '#059669' : '#999';
    return `<td style="padding:6px 10px;font-weight:700;color:${color};">${result||'Pending'}</td>`;
  }

  const testsRows = tcSelectedTests.length
    ? tcSelectedTests.map(t=>`<tr style="border-bottom:1px solid #eee;vertical-align:top;">
        <td style="padding:6px 10px;font-weight:500;white-space:nowrap;">${t.description}</td>
        ${tcRenderResult(t.description, t.result)}
      </tr>`).join('')
    : `<tr><td colspan="2" style="padding:8px;color:#999;">No tests selected</td></tr>`;

  document.getElementById('tc-preview').innerHTML = `
    ${wmHtml}
    <div style="position:relative;z-index:1;">
      <img src="assets/letterhead.png" style="display:block;width:100%;height:auto;">
      <div style="display:flex;justify-content:flex-end;margin:4px 0 20px;">
        <span style="font-size:${hFontSize}px;color:${hTextColor};">${hRightText}</span>
      </div>
      <div style="text-align:center;margin-bottom:20px;">
        <h2 style="font-size:16px;font-weight:700;text-decoration:underline;margin:0;">TEST CERTIFICATE</h2>
        ${refNo ? `<div style="font-size:11px;color:#64748b;margin-top:4px;">Ref: <strong>${refNo}</strong></div>` : ''}
      </div>
      <p style="text-align:justify;margin-bottom:20px;">I, the undersigned, Dr. <strong>${stripDr(physician)}</strong>, a physician at <strong>${hospName}</strong>, hereby certify that ${title}. <strong>${patient}</strong>, born on <strong>${tcFormatDate(dob)}</strong>, underwent the following lab test(s).</p>
      <p style="margin-bottom:8px;font-weight:700;">Tests Performed &amp; Results:</p>
      <table style="width:100%;border-collapse:collapse;font-size:13px;margin-bottom:20px;border:1px solid #ccc;">
        <thead><tr style="background:#f0f0f0;">
          <th style="padding:7px 10px;text-align:left;border:1px solid #ccc;">Test Name</th>
          <th style="padding:7px 10px;text-align:left;border:1px solid #ccc;">Result</th>
        </tr></thead>
        <tbody>${testsRows}</tbody>
      </table>
      <p style="text-align:justify;margin-bottom:25px;">This certificate is issued at the request of the patient for all lawful purposes.</p>
      <p style="margin-bottom:5px;"><strong>Issued on:</strong> ${tcFormatDate(issuedDate)}</p>
      <p style="margin-bottom:30px;"><strong>At:</strong> ${hospName}</p>
      <div style="margin-top:20px;display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:12px;">
        <div>
          <p style="margin-bottom:3px;"><strong>Physician's Name &amp; Signature:</strong></p>
          <p style="margin-bottom:20px;">Dr. ${stripDr(physician)}</p>
          <p style="margin-bottom:3px;"><strong>Stamp:</strong></p>
          <div style="width:120px;height:40px;border-bottom:1px solid #333;"></div>
          <p style="font-size:9px;color:#666;margin-top:2px;">Official stamp</p>
        </div>
        <div style="display:flex;align-items:center;gap:8px;background:#f8fafc;border:1px solid #cbd5e1;border-radius:6px;padding:6px 10px;">
          <div id="tc-qr-code" style="width:68px;height:68px;background:white;padding:2px;border:1px solid #e2e8f0;border-radius:4px;display:flex;align-items:center;justify-content:center;"></div>
          <div>
            <div style="font-size:9px;font-weight:700;color:#059669;text-transform:uppercase;letter-spacing:0.4px;">✓ Verified Document</div>
            <div style="font-size:11px;font-weight:700;color:#0f172a;margin-top:1px;">${refNo}</div>
            <div style="font-size:9px;color:#64748b;margin-top:2px;">Scan with camera<br>to verify authenticity</div>
          </div>
        </div>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;padding-top:6px;border-top:${fLineSize}px solid ${fLineColor};margin-top:30px;">
        <span style="font-size:${fFontSize}px;color:${fTextColor};">${fLeftText}</span>
        <span style="font-size:${fFontSize}px;color:${fTextColor};">${fRightText}</span>
      </div>
    </div>`;
  renderDocQRCode('tc-qr-code', refNo, 68);
}

async function tcPrint(){
  const patient  = document.getElementById('tc-patient').value || '';
  const physician = document.getElementById('tc-physician').value || '';
  if(!patient){ alert('Please select a patient first.'); return; }
  const refToSave = tcCurrentRef;
  await saveDocument('test_certificate', patient, physician, {
    title: document.getElementById('tc-title').value, patient, physician,
    dob: document.getElementById('tc-dob').value,
    issued: document.getElementById('tc-issued').value,
    tests: tcSelectedTests,
    reference_no: refToSave
  }, refToSave);
  const content = document.getElementById('tc-preview').innerHTML;
  const win = window.open('','_blank','width=800,height=600');
  win.document.write(`<html><head><title>Test Certificate</title><style>body{font-family:'Times New Roman',serif;padding:40px;color:#000;line-height:1.7;font-size:14px;}img{max-width:100%;}@media print{body{padding:20px;}}</style></head><body><div style="position:relative;overflow:hidden;">${content}</div></body></html>`);
  win.document.close(); win.focus();
  setTimeout(()=>{ win.print(); }, 500);
  tcCurrentRef = await getNextDocRef('test_certificate');
  tcUpdatePreview();
}

async function tcDownloadPDF(){
  const patient = document.getElementById('tc-patient').value || 'Patient';
  const physician = document.getElementById('tc-physician').value || '';
  if(!patient || patient==='Patient'){ alert('Please select a patient first.'); return; }
  const refToSave = tcCurrentRef;
  await saveDocument('test_certificate', patient, physician, {
    title: document.getElementById('tc-title').value, patient, physician,
    dob: document.getElementById('tc-dob').value,
    issued: document.getElementById('tc-issued').value,
    tests: tcSelectedTests,
    reference_no: refToSave
  }, refToSave);
  html2pdf().set({
    margin: 10,
    filename: (refToSave||'TST')+'_'+patient.replace(/\s+/g,'_')+'.pdf',
    image: {type:'jpeg',quality:0.98},
    html2canvas: {scale:2, useCORS:true},
    jsPDF: {unit:'mm',format:'a4',orientation:'portrait'}
  }).from(document.getElementById('tc-preview')).save().then(async ()=>{
    tcCurrentRef = await getNextDocRef('test_certificate');
    tcUpdatePreview();
  });
}

tcInit();
</script>
<?php endif; ?>

<?php if ($subModule == 'dietary-certificate'): ?>
<!-- ====== DIETARY RESTRICTION CERTIFICATE ====== -->

<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-utensils" style="color:var(--primary-color);"></i> Dietary Restriction Certificate</h3>
    <div style="display:flex;gap:8px;">
      <button onclick="dcOpenForm()" style="padding:8px 16px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-edit"></i> Fill Details</button>
      <button onclick="dcPrint()" style="padding:8px 16px;background:#059669;color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-print"></i> Print</button>
      <button onclick="dcDownloadPDF()" style="padding:8px 16px;background:#1e40af;color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-file-pdf"></i> PDF</button>
    </div>
  </div>
  <div id="dc-preview" style="border:1px solid var(--gray-200);border-radius:8px;padding:40px;font-family:'Times New Roman',serif;font-size:14px;line-height:1.7;min-height:500px;position:relative;overflow:hidden;background:white;max-width:800px;margin:0 auto;">
  </div>
</div>

<!-- Form Modal -->
<div id="dc-form-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
  <div style="background:white;border-radius:12px;width:520px;max-width:95vw;padding:24px;max-height:90vh;overflow-y:auto;">
    <div style="display:flex;justify-content:space-between;margin-bottom:16px;">
      <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-edit" style="color:var(--primary-color);"></i> Fill Document Details</h3>
      <button onclick="dcCloseForm()" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
      <div style="grid-column:1/-1;">
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Physician's Name *</label>
        <input id="dc-physician" type="text" readonly style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:var(--gray-50);cursor:not-allowed;">
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Title (Mr/Mrs)</label>
        <select id="dc-title" onchange="dcUpdatePreview()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
          <option value="Mr">Mr</option>
          <option value="Mrs">Mrs</option>
          <option value="Ms">Ms</option>
        </select>
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Patient * <small style="color:var(--gray-400);">(search)</small></label>
        <div style="position:relative;">
          <input id="dc-patient-search" type="text" placeholder="Type to search patient..." oninput="dcSearchPatient()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
          <input id="dc-patient" type="hidden">
          <div id="dc-patient-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:white;border:1px solid var(--gray-200);border-radius:6px;max-height:150px;overflow-y:auto;z-index:100;box-shadow:0 4px 12px rgba(0,0,0,0.1);margin-top:2px;"></div>
        </div>
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Date of Birth *</label>
        <input id="dc-dob" type="date" onchange="dcUpdatePreview()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Issued Date</label>
        <input id="dc-issued" type="date" onchange="dcUpdatePreview()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div style="grid-column:1/-1;">
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Restricted Foods/Beverages * <small style="color:var(--gray-400);">(one per line)</small></label>
        <textarea id="dc-items" rows="8" placeholder="e.g.&#10;Dairy products&#10;Spicy foods&#10;Alcohol&#10;Caffeine&#10;Red meat" oninput="dcUpdatePreview()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;resize:vertical;"></textarea>
      </div>
    </div>
    <button onclick="dcCloseForm()" style="width:100%;margin-top:16px;padding:12px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px;"><i class="fas fa-check"></i> Done</button>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
let dcHospSettings = {};
let dcCurrentRef = null;

async function dcInit(){
  const today = new Date().toISOString().split('T')[0];
  document.getElementById('dc-issued').value = today;
  try{
    const r = await fetch(_mdBase + '/api.php?endpoint=settings/hospital');
    const j = await r.json();
    if(j.success) dcHospSettings = j.data || {};
  }catch(e){}
  // Pre-fill physician
  if(window._loggedUserName) document.getElementById('dc-physician').value = window._loggedUserName;
  dcCurrentRef = await getNextDocRef('dietary_restriction');
  dcUpdatePreview();
}

let dcSearchTimer;
async function dcSearchPatient(){
  clearTimeout(dcSearchTimer);
  dcSearchTimer = setTimeout(async()=>{
    const q=document.getElementById('dc-patient-search').value.trim();
    const dd=document.getElementById('dc-patient-dropdown');
    if(q.length<2){dd.style.display='none';return;}
    try{
      const r=await fetch(_mdBase + '/api.php?endpoint=patients/patients&search='+encodeURIComponent(q)+'&limit=8');
      const j=await r.json();
      const patients=j.data?.patients||[];
      if(!patients.length){dd.innerHTML='<div style="padding:8px 12px;color:var(--gray-400);font-size:12px;">No patients found</div>';dd.style.display='block';return;}
      dd.innerHTML=patients.map(p=>`<div onclick="dcSelectPatient('${(p.first_name+' '+p.last_name).replace(/'/g,"\\'")}','${p.date_of_birth||''}','${p.gender||''}')" style="padding:8px 12px;cursor:pointer;font-size:12px;border-bottom:1px solid var(--gray-100);" onmouseover="this.style.background='var(--gray-50)'" onmouseout="this.style.background=''"><strong>${p.first_name} ${p.last_name}</strong> <span style="color:var(--gray-500);">${p.patient_no}</span></div>`).join('');
      dd.style.display='block';
    }catch(e){dd.style.display='none';}
  },300);
}

function dcSelectPatient(name, dob, gender){
  document.getElementById('dc-patient-search').value=name;
  document.getElementById('dc-patient').value=name;
  if(dob) document.getElementById('dc-dob').value=dob;
  if(gender){
    const titleSel=document.getElementById('dc-title');
    titleSel.value = gender==='female'?'Mrs':'Mr';
  }
  document.getElementById('dc-patient-dropdown').style.display='none';
  dcUpdatePreview();
}

function dcOpenForm(){ document.getElementById('dc-form-modal').style.display = 'flex'; }
function dcCloseForm(){ document.getElementById('dc-form-modal').style.display = 'none'; }

function dcFormatDate(dateStr){
  if(!dateStr) return '____/____/______';
  const d = new Date(dateStr);
  const dd = String(d.getDate()).padStart(2,'0');
  const mm = String(d.getMonth()+1).padStart(2,'0');
  const yyyy = d.getFullYear();
  return dd+'/'+mm+'/'+yyyy;
}

function dcUpdatePreview(){
  const hs = dcHospSettings;
  const physician = document.getElementById('dc-physician').value || '..............................';
  const title = document.getElementById('dc-title').value;
  const patient = document.getElementById('dc-patient').value || document.getElementById('dc-patient-search').value || '..............................';
  const dob = document.getElementById('dc-dob').value;
  const issuedDate = document.getElementById('dc-issued').value;
  const items = document.getElementById('dc-items').value.split('\n').filter(s=>s.trim());
  const hospName = hs.hospital_name || 'Hospital';
  
  // Document settings
  const docLogo = hs.doc_logo || hs.logo || '';
  const hLineColor = hs.header_line_color || '#059669';
  const hLineSize = hs.header_line_size || '2';
  const hTextColor = hs.header_text_color || '#1f2937';
  const hFontSize = hs.header_font_size || '11';
  const hLeftText = hs.header_left || hospName;
  const refNo = dcCurrentRef || '';
  const hRightText = (hs.header_right || 'Date: {date}').replace('{date}', dcFormatDate(issuedDate)).replace('{ref}', refNo || 'DRC');
  const fLineColor = hs.footer_line_color || '#059669';
  const fLineSize = hs.footer_line_size || '2';
  const fTextColor = hs.footer_text_color || '#1f2937';
  const fFontSize = hs.footer_font_size || '9';
  const fLeftText = hs.footer_left || (hs.address||'Kigali, Rwanda');
  const fRightText = hs.footer_right || (hs.phone?'Tel: '+hs.phone+' | ':'')+'Email: '+(hs.email||'');
  
  // Watermark
  const wmEnabled = hs.watermark_enabled === '1';
  const wmType = hs.watermark_type || 'text';
  const wmText = hs.watermark_text || hospName;
  const wmColor = hs.watermark_color || '#d1d5db';
  const wmSize = hs.watermark_size || '40';
  const wmOpacity = (parseInt(hs.watermark_opacity)||15)/100;
  const wmRotation = hs.watermark_rotation || '-30';
  const wmImage = hs.watermark_image || '';
  
  let wmHtml = '';
  if(wmEnabled){
    if(wmType==='image' && wmImage){
      wmHtml = `<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(${wmRotation}deg);opacity:${wmOpacity};pointer-events:none;z-index:0;"><img src="${wmImage}" style="width:150px;height:auto;"></div>`;
    } else {
      wmHtml = `<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(${wmRotation}deg);opacity:${wmOpacity};pointer-events:none;z-index:0;font-size:${wmSize}px;color:${wmColor};font-weight:700;white-space:nowrap;">${wmText}</div>`;
    }
  }

  // Build items list
  let itemsHtml = '';
  if(items.length > 0){
    itemsHtml = items.map(item => `<li style="margin-bottom:4px;">${item.trim()}</li>`).join('');
  } else {
    // Show empty lines as in template
    itemsHtml = Array(6).fill('<li style="margin-bottom:4px;color:#999;">.................................................</li>').join('');
  }

  const html = `
    ${wmHtml}
    <div style="position:relative;z-index:1;">
      <!-- Header -->
      <img src="assets/letterhead.png" style="display:block;width:100%;height:auto;">
      <div style="display:flex;justify-content:flex-end;margin:4px 0 20px;">
        <span style="font-size:${hFontSize}px;color:${hTextColor};">${hRightText}</span>
      </div>
      
      <!-- Title -->
      <div style="text-align:center;margin-bottom:20px;">
        <h2 style="font-size:16px;font-weight:700;text-decoration:underline;margin:0;">DIETARY RESTRICTION CERTIFICATE</h2>
        ${refNo ? `<div style="font-size:11px;color:#64748b;margin-top:4px;">Ref: <strong>${refNo}</strong></div>` : ''}
      </div>
      
      <!-- Body -->
      <p style="text-align:justify;margin-bottom:15px;">I, the undersigned, Dr. <strong>${stripDr(physician)}</strong>, a physician at <strong>${hospName}</strong>, hereby certify that ${title}. <strong>${patient}</strong>, born on <strong>${dcFormatDate(dob)}</strong>, has been examined and treated at <strong>${hospName}</strong>.</p>
      
      <p style="text-align:justify;margin-bottom:15px;">Due to the patient's medical condition, they are advised to avoid consuming the following foods and/or beverages:</p>
      
      <ul style="margin-left:20px;margin-bottom:20px;">
        ${itemsHtml}
      </ul>
      
      <p style="text-align:justify;margin-bottom:15px;">These dietary restrictions should be followed as medically recommended to support the patient's recovery and overall health.</p>
      
      <p style="text-align:justify;margin-bottom:25px;">This certificate is issued at the request of the patient for all lawful purposes.</p>
      
      <!-- Issued -->
      <p style="margin-bottom:5px;"><strong>Issued on:</strong> ${dcFormatDate(issuedDate)}</p>
      <p style="margin-bottom:20px;"><strong>At:</strong> ${hospName}</p>
      
      <!-- Signature & QR Code Block -->
      <div style="margin-top:30px;display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:12px;">
        <div>
          <p style="margin-bottom:3px;"><strong>Physician's Name & Signature:</strong></p>
          <p style="margin-bottom:20px;">Dr. ${stripDr(physician)}</p>
          <p style="margin-bottom:3px;"><strong>Stamp:</strong></p>
          <div style="width:120px;height:40px;border-bottom:1px solid #333;"></div>
          <p style="font-size:9px;color:#666;margin-top:2px;">Official stamp</p>
        </div>
        <div style="display:flex;align-items:center;gap:8px;background:#f8fafc;border:1px solid #cbd5e1;border-radius:6px;padding:6px 10px;">
          <div id="dc-qr-code" style="width:68px;height:68px;background:white;padding:2px;border:1px solid #e2e8f0;border-radius:4px;display:flex;align-items:center;justify-content:center;"></div>
          <div>
            <div style="font-size:9px;font-weight:700;color:#059669;text-transform:uppercase;letter-spacing:0.4px;">✓ Verified Document</div>
            <div style="font-size:11px;font-weight:700;color:#0f172a;margin-top:1px;">${refNo}</div>
            <div style="font-size:9px;color:#64748b;margin-top:2px;">Scan with camera<br>to verify authenticity</div>
          </div>
        </div>
      </div>
      
      <!-- Footer -->
      <div style="display:flex;justify-content:space-between;align-items:center;padding-top:6px;border-top:${fLineSize}px solid ${fLineColor};margin-top:30px;">
        <span style="font-size:${fFontSize}px;color:${fTextColor};">${fLeftText}</span>
        <span style="font-size:${fFontSize}px;color:${fTextColor};">${fRightText}</span>
      </div>
    </div>
  `;
  
  document.getElementById('dc-preview').innerHTML = html;
  renderDocQRCode('dc-qr-code', refNo, 68);
}

async function dcPrint(){
  const patient=document.getElementById('dc-patient').value||'';
  const physician=document.getElementById('dc-physician').value||'';
  if(!patient){alert('Please fill in the patient name.');return;}
  const refToSave = dcCurrentRef;
  await saveDocument('dietary_restriction',patient,physician,{
    title:document.getElementById('dc-title').value,patient,physician,
    dob:document.getElementById('dc-dob').value,issued:document.getElementById('dc-issued').value,
    items:document.getElementById('dc-items').value,
    reference_no: refToSave
  }, refToSave);
  const content = document.getElementById('dc-preview').innerHTML;
  const win = window.open('','_blank','width=800,height=600');
  win.document.write(`<html><head><title>Dietary Restriction Certificate</title><style>
    body{font-family:'Times New Roman',serif;padding:40px;color:#000;line-height:1.7;font-size:14px;}
    img{max-width:100%;}
    @media print{body{padding:20px;}}
  </style></head><body><div style="position:relative;overflow:hidden;">${content}</div></body></html>`);
  win.document.close();
  win.focus();
  setTimeout(()=>{win.print();},500);
  dcCurrentRef = await getNextDocRef('dietary_restriction');
  dcUpdatePreview();
}

async function dcDownloadPDF(){
  const patient=document.getElementById('dc-patient').value||'Patient';
  const physician=document.getElementById('dc-physician').value||'';
  if(!patient||patient==='Patient'){alert('Please fill in the patient name.');return;}
  const refToSave = dcCurrentRef;
  await saveDocument('dietary_restriction',patient,physician,{
    title:document.getElementById('dc-title').value,patient,physician,
    dob:document.getElementById('dc-dob').value,issued:document.getElementById('dc-issued').value,
    items:document.getElementById('dc-items').value,
    reference_no: refToSave
  }, refToSave);
  const element = document.getElementById('dc-preview');
  const opt = {
    margin: 10,
    filename: (refToSave||'DRC')+'_'+patient.replace(/\s+/g,'_')+'.pdf',
    image: {type:'jpeg',quality:0.98},
    html2canvas: {scale:2, useCORS:true},
    jsPDF: {unit:'mm',format:'a4',orientation:'portrait'}
  };
  html2pdf().set(opt).from(element).save().then(async ()=>{
    dcCurrentRef = await getNextDocRef('dietary_restriction');
    dcUpdatePreview();
  });
}

dcInit();
</script>
<?php endif; ?>

<?php if (!in_array($subModule, ['generated', 'medical-leave', 'leave-certificate', 'test-certificate', 'dietary-certificate', 'external-transfer'], true)): ?>
<!-- ====== TEMPLATE DOCUMENTS (js/doc-templates.js) ====== -->
<div id="dt-root"></div>
<script>dtMount(<?= json_encode($subModule) ?>);</script>
<?php endif; ?>

<?php if ($subModule == 'external-transfer'): ?>
<?php include __DIR__ . '/includes/doc-transfer.php'; ?>
<script src="js/doc-transfer.js?v=<?= filemtime(__DIR__ . '/js/doc-transfer.js') ?>"></script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
