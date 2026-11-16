// js/doc-templates.js
// Form-driven documents built from the clinic's Word templates (docs/*.docx).
// Each template = fields (form inputs) + body(h) (the document wording).
// One engine renders the form, live preview, print, PDF and save for all of them.
// Depends on globals from medical-documents.php: _mdBase, stripDr, getNextDocRef, saveDocument, renderDocQRCode.

const DT_INPUT = 'width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;';
const DT_TEETH = [[18,17,16,15,14,13,12,11,21,22,23,24,25,26,27,28],[48,47,46,45,44,43,42,41,31,32,33,34,35,36,37,38]];

function dtEsc(s){ return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function dtDate(s){
  s = String(s || '');
  const [y,m,d] = s.slice(0,10).split('-');
  if(!d) return s;
  return d+'/'+m+'/'+y + (s.length > 10 ? ' '+s.slice(11,16) : '');
}
function dtAge(dob){
  if(!dob) return '';
  const b = new Date(dob), n = new Date();
  let a = n.getFullYear() - b.getFullYear();
  if(n < new Date(n.getFullYear(), b.getMonth(), b.getDate())) a--;
  return a >= 0 ? String(a) : '';
}
function dtNow(){ const d = new Date(); return new Date(d - d.getTimezoneOffset()*60000).toISOString().slice(0,16); }
function dtDots(n){ return '<span style="color:#444;word-break:break-all;">' + '.'.repeat(n) + '</span>'; }
function dtP(html){ return '<p style="text-align:justify;margin:0 0 9px;">' + html + '</p>'; }

// Helpers handed to body()/sign(): h.v(key) prints the value in bold, or a dotted blank like the paper form
function dtHelpers(V, tpl){
  const types = {};
  tpl.fields.forEach(f => types[f.key] = f.type || 'text');
  const raw = k => String(V[k] ?? '').trim();
  const sex = raw('sex');
  return {
    V, raw,
    has: k => raw(k) !== '',
    v: (k, dots = 20) => {
      const x = raw(k);
      if(!x) return dtDots(dots);
      return '<strong>' + dtEsc(/date/.test(types[k]) ? dtDate(x) : x).replace(/\n/g,'<br>') + '</strong>';
    },
    he:  sex === 'F' ? 'she' : sex === 'M' ? 'he'  : 'he/she',
    He:  sex === 'F' ? 'She' : sex === 'M' ? 'He'  : 'He / She',
    his: sex === 'F' ? 'her' : sex === 'M' ? 'his' : 'his/her',
    him: sex === 'F' ? 'her' : sex === 'M' ? 'him' : 'him/her',
  };
}

// ---------- shared field sets ----------
const DT_PHYS = {key:'physician', label:"Physician's Name", type:'physician'};
const DT_SEX  = {key:'sex', label:'Sex', type:'select', options:['M','F']};
const DT_ADDR = ['village','cell','sector','district','province'].map(k => ({key:k, label:k[0].toUpperCase()+k.slice(1)}));

function dtBmi(V){
  const w = parseFloat(V.weight), h = parseFloat(V.height) / 100;
  V.bmi = (w > 0 && h > 0) ? (w / (h*h)).toFixed(1) : '';
}

const DT_CERT_FIELDS = [
  {key:'name', label:'Names *', type:'patient'},
  {key:'dob', label:'Born on', type:'date'},
  DT_SEX,
  ...DT_ADDR,
  {key:'weight', label:'Weight (Kg)', type:'number'},
  {key:'height', label:'Height (Cm)', type:'number'},
  {key:'bmi', label:'BMI (auto)', calc:true},
  {key:'bp', label:'BP (mmHg) e.g. 120/80'},
  {key:'pulse', label:'Pulse (/min)', type:'number'},
  DT_PHYS,
  {key:'issued', label:'Done at Kigali on', type:'date'},
];

function dtCertBody(h, bio){
  const {v} = h;
  return dtP(`This certificate is delivered to: Names: ${v('name',40)} Born on ${v('dob',14)}`)
    + dtP(`Resident of ${v('village')} Village, ${v('cell')} Cell, ${v('sector')} Sector, ${v('district')} District, ${v('province')} Province`)
    + dtP(`Weight (Kg): ${v('weight',6)} Height (Cm): ${v('height',6)} BMI: ${v('bmi',6)} BP: ${v('bp',10)} mmHg Pul: ${v('pulse',6)} /min`)
    + (bio
      ? dtP(`I, Dr ${v('physician',30)}, Medical Doctor at Iramiro Clinic, hereby confirm that, after physical and clinical examination of the above named, ${h.his} biological and clinical status doesn't reveal any danger neither for ${h.him} nor for others. Thus, this certificate is delivered to confirm that ${h.he} is physically fit and without any other risk for the community.`)
        + dtP('<strong>Laboratory tests conducted are as follow:</strong>')
        + dtP(v('labs', 250))
      : dtP(`I, Dr ${v('physician',30)}, Medical Doctor at Iramiro Clinic, hereby confirm that, after physical and clinical examination of the above named, ${h.his} clinical status does not reveal any danger neither for ${h.him} nor for others. Thus, this certificate is delivered to confirm that ${h.he} is physically fit.`))
    + dtP(`Done at Kigali on ${v('issued',14)}`);
}
const dtCertSign = h => `<p style="margin:0;">Dr ${h.v('physician',30)}, Medical Doctor at Iramiro Clinic</p>
  <p style="margin:6px 0 0;"><strong>Signature &amp; Stamp:</strong></p><div style="width:140px;height:36px;border-bottom:1px solid #333;"></div>`;

const dtDirectorSign = () => `<p style="margin:0;"><strong>Habimana Cyprien, MPH</strong></p>
  <p style="margin:0;">Managing Director, Iramiro Clinic</p>`;

const DT_CELL = 'border:1px solid #333;padding:6px 8px;vertical-align:top;';

// ---------- templates (key = document_type) ----------
const DT_TEMPLATES = {

  fitness_certificate: {
    sub:'fitness-certificate', label:'Medical Fitness',
    title:'MEDICAL CERTIFICATE FOR PHYSICAL FITNESS',
    fields: DT_CERT_FIELDS,
    calc: dtBmi,
    body: h => dtCertBody(h, false),
    sign: dtCertSign,
  },

  biological_certificate: {
    sub:'biological-certificate', label:'Biological Tests Certificate',
    title:'MEDICAL CERTIFICATE',
    fields: [...DT_CERT_FIELDS.slice(0,-2), {key:'labs', label:'Laboratory tests conducted', type:'textarea', rows:4}, ...DT_CERT_FIELDS.slice(-2)],
    calc: dtBmi,
    body: h => dtCertBody(h, true),
    sign: dtCertSign,
  },

  hospitalization_certificate: {
    sub:'hospitalization-certificate', label:'Hospitalization Certificate',
    title:'CERTIFICATE OF HOSPITALIZATION',
    fields: [
      DT_PHYS,
      {key:'title', label:'Mme / Mr', type:'select', options:['Mme','Mr']},
      {key:'name', label:'Patient *', type:'patient'},
      DT_SEX,
      {key:'hf', label:'Code HF'},
      {key:'from', label:'Hospitalized from', type:'date'},
      {key:'to', label:'To', type:'date'},
      {key:'outcome', label:'Authorized to leave for', type:'select', options:['clinical status improvement','transfer','death']},
      {key:'diagnosis', label:'Diagnosis', type:'textarea'},
      {key:'treatment', label:'Treatment received', type:'textarea', rows:4},
      {key:'measures', label:'Further measures', type:'textarea'},
      {key:'issued', label:'Date', type:'date'},
    ],
    body: h => {
      const {v} = h;
      return dtP(`I, Dr ${v('physician',30)} Medical Doctor at Iramiro Clinic, hereby confirm that ${h.raw('title') || 'Mme, Mr'} ${v('name',40)} Code HF ${v('hf',10)} has been treated and hospitalized in our premises from ${v('from',14)} to ${v('to',14)}. ${h.He} is authorized to leave the Clinic for ${h.has('outcome') ? v('outcome') : 'clinical status improvement / transfer / death'}.`)
        + dtP(`<strong>Diagnosis:</strong> ${v('diagnosis',90)}`)
        + dtP(`<strong>Treatment received:</strong> ${v('treatment',250)}`)
        + dtP(`<strong>Further measures:</strong> ${v('measures',160)}`);
    },
    sign: h => `<p style="margin:0;">Dr ${h.v('physician',40)}</p><p style="margin:6px 0 0;"><strong>Signature &amp; Stamp:</strong></p><div style="width:140px;height:36px;border-bottom:1px solid #333;"></div>`,
  },

  referral_form: {
    sub:'referral-form', label:'Referral / Transfer Form',
    title:'REFERRAL/TRANSFER FORM', size:12,
    fields: [
      {key:'name', label:'Patient Name *', type:'patient'},
      {key:'age', label:'Age', type:'number'},
      {key:'sex', label:'Sexe', type:'select', options:['M','F']},
      {key:'district', label:'District'}, {key:'sector', label:'Sector'},
      {key:'cell', label:'Cell'}, {key:'village', label:'Village'},
      {key:'admitted', label:'Date/time of Admission', type:'datetime-local'},
      {key:'transferred', label:'Date/Time of Transfer', type:'datetime-local'},
      {key:'calling_time', label:'Calling Time', type:'time'},
      {key:'day_night', label:'Night or Day', type:'select', options:['Day','Night']},
      {key:'contact_staff', label:'Referral Contacted Staff'},
      {key:'contact_phone', label:'Phone'},
      {key:'facility', label:'Receiving Facility'},
      {key:'department', label:'Receiving Department'},
      {key:'reason', label:'Reason for Transfer', type:'textarea', rows:2},
      {key:'diag1', label:'Diagnosis ①'}, {key:'diag2', label:'Diagnosis ②'},
      {key:'diag3', label:'Diagnosis ③'}, {key:'diag4', label:'Diagnosis ④'},
      {key:'procedures', label:'Procedures and Treatments', type:'textarea', rows:2},
      {key:'medications', label:'Current medications', type:'textarea', rows:2},
      {type:'section', label:'Significant Findings'},
      {key:'lab', label:'Laboratory', type:'textarea', rows:2},
      {key:'others', label:'Others', type:'textarea', rows:2},
      {type:'section', label:'Current Patient Condition — Vital Signs'},
      {key:'spo2', label:'Pulse Ox (%)'}, {key:'rr', label:'RR (/min)'},
      {key:'bp', label:'BP (mmHg)'}, {key:'pulse', label:'Pulse (/min)'},
      {key:'temp', label:'T° (°C)'},
      {key:'presentation', label:'Clinical Presentation', type:'textarea', rows:2},
      {key:'transport', label:'Type of Transportation', type:'select', options:['Ambulatory','Ambulance']},
      {...DT_PHYS, label:'Referring Clinician'},
      {key:'referred_at', label:'Referring Date/time', type:'datetime-local', def: dtNow},
      {key:'issued', label:'Document Date', type:'date'},
    ],
    body: h => {
      const {v, V} = h;
      const box = o => V.transport === o ? '☒' : '☐';
      return dtP(`Patient Name: ${v('name',40)} Age: ${v('age',6)} Sexe: ${v('sex',4)}`)
        + dtP(`District: ${v('district',14)} Sector: ${v('sector',14)} Cell: ${v('cell',14)} Village: ${v('village',14)}`)
        + dtP(`Date/time of Admission: ${v('admitted')} Date/Time of Transfer: ${v('transferred')}`)
        + dtP(`Calling Time: ${v('calling_time',8)} Referral Contacted Staff: ${v('contact_staff')} Phone: ${v('contact_phone',12)} Night or Day: ${v('day_night',6)}`)
        + dtP(`Receiving Facility: ${v('facility',30)} Receiving Department: ${v('department',25)}`)
        + dtP(`Reason for Transfer: ${v('reason',80)}`)
        + dtP(`Diagnosis: ① ${v('diag1',30)} ② ${v('diag2',30)} ③ ${v('diag3',30)} ④ ${v('diag4',30)}`)
        + dtP(`Procedures and Treatments: ${v('procedures',80)}`)
        + dtP(`Current medications: ${v('medications',80)}`)
        + dtP(`<strong>Significant Findings:</strong><br>i. Laboratory: ${v('lab',100)}<br>ii. Others: ${v('others',90)}`)
        + dtP(`<strong>Current Patient Condition:</strong><br>i. Vital Signs: Pulse Ox: ${v('spo2',6)} RR: ${v('rr',6)} BP: ${v('bp',10)} Pulse: ${v('pulse',6)} T°: ${v('temp',6)}<br>ii. Clinical Presentation: ${v('presentation',100)}<br>iii. Monitoring of patient during transportation to other Facility is mandatory to ensure that any transferred patient is well monitored, therefore the transferring Nurse has to monitor: All Vital Signs: BP, T°, Pulse Oxy, RR, Blood Sugar, PR and assess the ABCs of the transferred Patient at least every 30 min and record the results and any intervention done in the “during transfer monitoring register”.`)
        + dtP(`<strong>Type of Transportation:</strong> &nbsp; Ambulatory ${box('Ambulatory')} &nbsp;&nbsp; Ambulance ${box('Ambulance')}`)
        + dtP(`<strong>Referring Clinician:</strong> Name and signature: Dr ${v('physician',30)} &nbsp; Date/time: ${v('referred_at')} &nbsp; <strong>STAMP</strong>`)
        + `<div style="border-top:2px dashed #333;margin:12px 0 8px;"></div>
           <p style="text-align:center;font-weight:700;margin:0 0 8px;">PATIENT REPORT BACK/COUNTER-REFER</p>`
        + dtP(`Patient name ${dtDots(40)} Sex ${dtDots(8)} Age ${dtDots(6)}`)
        + dtP(`Date of Admission: ${dtDots(25)} Date of Discharge: ${dtDots(25)}`)
        + dtP(`Final Diagnosis: ${dtDots(90)}`)
        + dtP(`Treatments ${dtDots(180)}`)
        + dtP(`Recommendations: ${dtDots(180)}`)
        + dtP(`Attending Doctor: ${dtDots(60)}`);
    },
  },

  echography_report: {
    sub:'echography-report', label:'Echography Report',
    title:'RAPPORT D’EXAMEN ÉCHOGRAPHIQUE',
    fields: [
      {key:'name', label:'Noms *', type:'patient'},
      {key:'age', label:'Âge', type:'number'},
      {key:'sex', label:'Sexe', type:'select', options:['M','F']},
      {key:'insurance', label:'Insurance'},
      {key:'insurance_id', label:'Insurance ID'},
      {key:'hf', label:'HF reg. Code'},
      {key:'marital', label:'Statut matrimonial', type:'select', options:['Célibataire','Marié(e)','Divorcé(e)','Veuf / Veuve']},
      {key:'spouse', label:'Noms du conjoint'},
      {key:'profession', label:'Profession'},
      {key:'district', label:'District'}, {key:'sector', label:'Sector'},
      {key:'cell', label:'Cell'}, {key:'village', label:'Village'},
      {key:'clinical', label:'Renseignements cliniques', type:'textarea', rows:2},
      {key:'echo_type', label:'Type d’échographie'},
      {key:'findings', label:'Aspect échographique', type:'textarea', rows:5},
      {key:'conclusion', label:'Conclusion', type:'textarea', rows:2},
      {key:'decision', label:'Décision', type:'textarea', rows:2},
      {key:'issued', label:'Date', type:'date'},
      {...DT_PHYS, label:'Nom du Clinicien'},
    ],
    body: h => {
      const {v} = h;
      const row = (label, k, dots) => `<tr><td style="${DT_CELL}width:30%;font-weight:700;">${label}</td><td style="${DT_CELL}">${v(k, dots)}</td></tr>`;
      return dtP(`<strong>Identification:</strong> Noms: ${v('name',40)} Âge: ${v('age',6)} Sexe: ${v('sex',4)}`)
        + dtP(`Insurance: ${v('insurance')} Insurance ID: ${v('insurance_id')} HF reg. Code: ${v('hf',10)}`)
        + dtP(`Statut matrimonial: ${v('marital',15)} Noms du conjoint: ${v('spouse',25)} Profession: ${v('profession',15)}`)
        + dtP(`District: ${v('district',14)} Sector: ${v('sector',14)} Cell: ${v('cell',14)} Village: ${v('village',14)}`)
        + `<table style="width:100%;border-collapse:collapse;margin:10px 0;">
            ${row('Renseignements cliniques:', 'clinical', 60)}
            ${row('Type d’échographie:', 'echo_type', 40)}
            ${row('Aspect échographique:', 'findings', 200)}
            ${row('Conclusion:', 'conclusion', 80)}
            ${row('Décision:', 'decision', 60)}
            ${row('Date:', 'issued', 14)}
          </table>`;
    },
    sign: h => `<p style="margin:0;"><strong>Nom et Signature du Clinicien:</strong> ${h.v('physician',30)}</p><div style="width:140px;height:36px;border-bottom:1px solid #333;"></div>`,
  },

  followup_sheet: {
    sub:'followup-sheet', label:'Fiche de Suivi',
    title:'FICHE DE SUIVI DU MALADE SOUS SURVEILLANCE HOSPITALISÉ', size:12,
    fields: [
      {key:'name', label:'Names *', type:'patient'},
      {key:'age', label:'Age', type:'number'},
      DT_SEX,
      {key:'hf', label:'Reg No'},
      {key:'insurance', label:'Assurance'},
      {key:'reason', label:'Motif d’hospitalisation', type:'textarea', rows:2},
      {key:'rows', label:'Suivi (one entry per observation)', type:'rows',
        newRow: () => ({at: dtNow(), staff: stripDr(window._loggedUserName || '')}),
        cols: [
          {key:'at', label:'Date & Heure', type:'datetime-local'},
          {key:'vitals', label:'Vitaux Signs'},
          {key:'presentation', label:'Clinical presentations', type:'textarea'},
          {key:'treatment', label:'Treatment & consum', type:'textarea'},
          {key:'staff', label:'Clinical staff'},
        ]},
      {key:'discharge', label:'Date de sortie', type:'date'},
      {key:'discharge_state', label:'État à la sortie'},
      {key:'recommendations', label:'Recommandations', type:'textarea', rows:2},
      {key:'issued', label:'Document Date', type:'date'},
    ],
    body: h => {
      const {v, V} = h;
      const th = `${DT_CELL}background:#f1f5f9;text-align:left;`;
      const rows = (V.rows || []).filter(r => Object.values(r).some(x => String(x ?? '').trim()));
      const cell = x => `<td style="${DT_CELL}">${dtEsc(x).replace(/\n/g,'<br>')}</td>`;
      const body = rows.length
        ? rows.map(r => `<tr>${cell(dtDate(r.at))}${cell(r.vitals)}${cell(r.presentation)}${cell(r.treatment)}${cell(r.staff)}</tr>`).join('')
        : `<tr>${'<td style="'+DT_CELL+'height:28px;"></td>'.repeat(5)}</tr>`.repeat(3);
      return dtP(`Names: ${v('name',40)} Age: ${v('age',6)} Sex: ${v('sex',4)} Reg No: ${v('hf',12)} Assurance: ${v('insurance',14)}`)
        + dtP(`Motif d’hospitalisation: ${v('reason',100)}`)
        + `<table style="width:100%;border-collapse:collapse;margin:8px 0 12px;">
            <thead><tr><th style="${th}width:15%;">Date &amp; Heure</th><th style="${th}width:15%;">Vitaux Signs</th><th style="${th}">Clinical presentations</th><th style="${th}">Treatment &amp; consum</th><th style="${th}width:15%;">Clinical staff</th></tr></thead>
            <tbody>${body}</tbody>
          </table>`
        + dtP(`<strong>Date de sortie:</strong> ${v('discharge',14)}`)
        + dtP(`<strong>État à la sortie:</strong> ${v('discharge_state',60)}`)
        + dtP(`<strong>Recommandations:</strong> ${v('recommendations',120)}`);
    },
  },

  dental_lab: {
    sub:'dental-lab', label:'Dental Lab Authorization',
    title:'DENTAL LABORATORY WORK AUTHORIZATION',
    fields: [
      {key:'try_in', label:'Desired Completion/Try-in Date', type:'date'},
      {key:'name', label:'Patient Name *', type:'patient'},
      {key:'age', label:'Age', type:'number'},
      {key:'sex', label:'Gender', type:'select', options:['M','F']},
      {key:'hf', label:'HF CODE/ID'},
      {key:'case_type', label:'Case Type', type:'select', options:['Maxillary (Upper)','Mandibular (Lower)','Maxillary & Mandibular']},
      {key:'kennedy', label:'Kennedy Class', type:'select', options:['I','II','III','IV']},
      {key:'modification', label:'Modification'},
      {key:'teeth_shade', label:'Artificial Teeth Shade'},
      {key:'gum_shade', label:'Gum Shade'},
      {key:'teeth', label:'Teeth (click to mark)', type:'teeth'},
      {type:'section', label:'Clinician Details'},
      {...DT_PHYS, label:'Dentist Name'},
      {key:'clinic_address', label:'Clinic/Address'},
      {key:'clinic_phone', label:'Phone'},
      {key:'license', label:'License No'},
      {type:'section', label:'Dental Laboratory Details'},
      {key:'lab_name', label:'Laboratory Name'},
      {key:'lab_address', label:'Address'},
      {key:'lab_phone', label:'Phone'},
      {key:'lab_email', label:'Email'},
      {key:'issued', label:'Date', type:'date'},
    ],
    init: (V, hs) => {
      V.clinic_address = 'Iramiro Clinic' + (hs.address ? ', ' + hs.address : '');
      V.clinic_phone = hs.phone || '';
    },
    body: h => {
      const {v, V} = h;
      return '<p style="text-align:center;font-weight:700;margin:0 0 10px;">RPD WORK AUTHORIZATION FORM SPECIFICATION</p>'
        + dtP(`Desired Completion/Try-in Date: ${v('try_in',14)}`)
        + dtP(`Patient Name: ${v('name',35)} Age: ${v('age',5)} Gender: ${v('sex',4)} HF CODE/ID: ${v('hf',10)}`)
        + dtP(`Case Type: ${h.has('case_type') ? v('case_type') : 'Maxillary (Upper) / Mandibular (Lower)'}`)
        + dtP(`Classification: Kennedy Class ${v('kennedy',6)} &nbsp; Modification ${v('modification',10)}`)
        + `<table style="width:100%;border-collapse:collapse;margin:8px 0;"><tr>
            <td style="${DT_CELL}width:50%;"><strong>ARTIFICIAL TEETH SHADE:</strong> ${v('teeth_shade',12)}</td>
            <td style="${DT_CELL}"><strong>GUM SHADE:</strong> ${v('gum_shade',12)}</td></tr></table>`
        + `<div style="margin:10px 0;">${dtTeethHtml(V.teeth || [], false)}</div>`
        + `<table style="width:100%;border-collapse:collapse;margin-top:8px;"><tr>
            <td style="${DT_CELL}width:50%;"><strong>Clinician Details</strong><br>Dentist Name: Dr ${v('physician')}<br>Clinic/Address: ${v('clinic_address')}<br>Phone: ${v('clinic_phone')}<br>License No: ${v('license')}</td>
            <td style="${DT_CELL}"><strong>Dental Laboratory Details</strong><br>Laboratory Name: ${v('lab_name')}<br>Address: ${v('lab_address')}<br>Phone: ${v('lab_phone')}<br>Email: ${v('lab_email')}</td>
          </tr></table>`;
    },
  },

  attestation_service: {
    sub:'attestation-service', label:'Attestation de Service', hr:true,
    title:'ATTESTATION DE SERVICE',
    fields: [
      {key:'title', label:'Mme / Mlle / Mr', type:'select', options:['Mme','Mlle','Mr']},
      {key:'name', label:'Employé(e) *', type:'employee'},
      {key:'position', label:'Poste'},
      {key:'start', label:'Depuis', type:'date'},
      {key:'salary', label:'Salaire mensuel net (FRW) — optionnel', type:'number'},
      {key:'place', label:'Fait à', type:'select', options:['Kigali','Kabuga'], def:'Kigali'},
      {key:'issued', label:'Date', type:'date'},
    ],
    body: h => {
      const {v} = h;
      const salary = h.has('salary') ? ` Son salaire mensuel net est de <strong>${Number(h.raw('salary')).toLocaleString('fr-FR')}</strong> francs rwandais.` : '';
      return dtP(`Je soussigné, <strong>Habimana Cyprien, MPH</strong>, Managing Director Iramiro Clinic, atteste par la présente que ${h.raw('title') || 'Mme, Mlle, Mr'} ${v('name',40)} travaille au poste de ${v('position',30)} dans l’institution dont la responsabilité m’a été confiée, depuis ${v('start',14)} jusqu’à nos jours.${salary} La qualité de son travail, sa relation sociale et interpersonnelle est incontestable.`)
        + dtP('Cette attestation lui est délivrée pour servir et valoir ce que de droit.')
        + dtP(`Fait à ${v('place',12)} le ${v('issued',14)}`);
    },
    sign: dtDirectorSign,
  },

  attestation_service_rendu: {
    sub:'attestation-service-rendu', label:'Attestation de Service Rendu', hr:true,
    title:'ATTESTATION DE SERVICE RENDU',
    fields: [
      {key:'title', label:'Mr / Mme / Mlle', type:'select', options:['Mr','Mme','Mlle']},
      {key:'name', label:'Employé(e) *', type:'employee'},
      {key:'position', label:'En qualité de'},
      {key:'start', label:'Période du', type:'date'},
      {key:'end', label:'Au', type:'date'},
      {key:'reason', label:'Fin de service', type:'select', full:true, options:['après expiration du contrat','résilié de commun accord sans préjudice comme prévu dans ledit contrat','pour ses raisons personnelles']},
      {key:'quality', label:'La qualité de son travail…', type:'select', options:['ont été','n’ont pas été']},
      {key:'place', label:'Fait à', type:'select', options:['Kigali','Kabuga'], def:'Kigali'},
      {key:'issued', label:'Date', type:'date'},
    ],
    body: h => {
      const {v} = h;
      return dtP(`Je soussigné, <strong>Habimana Cyprien, MPH</strong>, Managing Director Iramiro Clinic, atteste par la présente que ${h.raw('title') || 'Mr/Mme/Mlle'} ${v('name',40)} a travaillé en qualité de ${v('position',30)} pour une période allant du ${v('start',14)} au ${v('end',14)} dans l’institution dont la responsabilité m’a été confiée ${h.has('reason') ? v('reason') : 'après expiration du contrat / résilié de commun accord sans préjudice comme prévu dans ledit contrat / pour ses raisons personnelles'}. La qualité de son travail, son organisation du service, sa relation sociale et interpersonnelle ${h.has('quality') ? v('quality') : 'ont été / n’ont pas été'} incontestables.`)
        + dtP('Cette attestation lui est délivrée pour servir et valoir ce que de droit.')
        + dtP(`Fait à ${v('place',12)} le ${v('issued',14)}`);
    },
    sign: dtDirectorSign,
  },

  mission_order: {
    sub:'mission-order', label:'Mission Order', hr:true,
    title:'MISSION ORDER',
    fields: [
      {key:'name', label:'Delivery to *', type:'employee'},
      {key:'position', label:'Job Title'},
      {key:'department', label:'Department'},
      {key:'subject', label:'Mission subject', type:'textarea', rows:2},
      {key:'destination', label:'Destination'},
      {key:'transport', label:'Means of Transport'},
      {key:'transport_fees', label:'Transport Fees (Rwf)', type:'number'},
      {key:'duration', label:'Duration of Mission (number of days)', type:'number'},
      {key:'departure', label:'Departure day', type:'date'},
      {key:'rate1', label:'Daily cost (Rwf) — days without night', type:'number'},
      {key:'days1', label:'Number of days without night', type:'number'},
      {key:'rate2', label:'Daily cost (Rwf) — days with night', type:'number'},
      {key:'days2', label:'Number of days with night', type:'number'},
      {key:'total', label:'Total Mission (Rwf, auto)', calc:true},
      {key:'issued', label:'Delivered at Kigali on', type:'date'},
      {key:'admin', label:'Administration (Name, Position)', def:'Habimana Cyprien, Managing Director'},
    ],
    // ponytail: total = transport fees + daily costs; drop transport_fees here if the clinic counts it separately
    calc: V => {
      const n = x => parseFloat(x) || 0;
      const t = n(V.transport_fees) + n(V.rate1)*n(V.days1) + n(V.rate2)*n(V.days2);
      V.total = t ? t.toLocaleString('en-US') : '';
    },
    body: h => {
      const {v} = h;
      const row = (label, val) => `<tr><td style="${DT_CELL}width:35%;font-weight:700;">${label}</td><td style="${DT_CELL}">${val}</td></tr>`;
      return `<table style="width:100%;border-collapse:collapse;margin-bottom:14px;">
          ${row('Delivery to:', v('name',40))}
          ${row('Job Title:', v('position',30))}
          ${row('Department:', v('department',30))}
          ${row('Mission subject:', v('subject',80))}
          ${row('Destination:', v('destination',30))}
          ${row('Means of Transport:', v('transport',30))}
          ${row('Transport Fees:', v('transport_fees',12) + ' Rwf')}
          ${row('Duration of Mission (number of days):', v('duration',6))}
          ${row('Departure day:', v('departure',14))}
          ${row('Daily cost:', `${v('rate1',10)} Rwf For ${v('days1',4)} Days without night<br>${v('rate2',10)} Rwf For ${v('days2',4)} Days with night`)}
          ${row('Total Mission:', v('total',14) + ' Rwf')}
          ${row('Delivered at Kigali on:', v('issued',14))}
          ${row('Administration:<br><span style="font-weight:400;font-size:12px;">Name, Position, Signature and Stamp</span>', v('admin',40) + '<div style="height:40px;"></div>')}
        </table>`
        + dtP('<strong>Destination Visa:</strong>')
        + dtP(`Arrival date: ${dtDots(30)}`)
        + dtP(`Departure date: ${dtDots(30)}`);
    },
  },
};

// Tooth chart (FDI numbering). clickable = form version
function dtTeethHtml(sel, clickable){
  return DT_TEETH.map(row => '<div style="display:flex;gap:3px;justify-content:center;margin:3px 0;">' + row.map((n, i) =>
    (i === 8 ? '<span style="width:8px;border-left:2px solid #333;"></span>' : '')
    + `<span ${clickable ? `onclick="dtToggleTooth(${n})"` : ''} style="width:26px;height:26px;display:inline-flex;align-items:center;justify-content:center;border:1px solid #333;border-radius:50%;font-size:11px;cursor:${clickable ? 'pointer' : 'default'};${sel.includes(n) ? 'background:#0f172a;color:#fff;font-weight:700;' : ''}">${n}</span>`
  ).join('') + '</div>').join('');
}

// ---------- engine ----------
let dtT = null, dtTypeKey = '', dtV = {}, dtHs = {}, dtRef = null, dtList = [], dtTimer;

async function dtMount(sub){
  dtTypeKey = Object.keys(DT_TEMPLATES).find(k => DT_TEMPLATES[k].sub === sub);
  if(!dtTypeKey) return;
  dtT = DT_TEMPLATES[dtTypeKey];
  const btn = (fn, bg, icon, text) => `<button onclick="${fn}()" style="padding:8px 16px;background:${bg};color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas ${icon}"></i> ${text}</button>`;
  document.getElementById('dt-root').innerHTML = `
    <div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
        <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-file-alt" style="color:var(--primary-color);"></i> ${dtEsc(dtT.label)}</h3>
        <div style="display:flex;gap:8px;">
          ${btn('dtOpenForm', 'var(--primary-color)', 'fa-edit', 'Fill Details')}
          ${btn('dtPrint', '#059669', 'fa-print', 'Print')}
          ${btn('dtPDF', '#1e40af', 'fa-file-pdf', 'PDF')}
        </div>
      </div>
      <div id="dt-preview" style="border:1px solid var(--gray-200);border-radius:8px;padding:40px;font-family:'Times New Roman',serif;font-size:${dtT.size || 14}px;line-height:1.6;min-height:600px;position:relative;overflow:hidden;background:white;max-width:800px;margin:0 auto;"></div>
    </div>
    <div id="dt-form-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
      <div style="background:white;border-radius:12px;width:640px;max-width:95vw;padding:24px;max-height:90vh;overflow-y:auto;">
        <div style="display:flex;justify-content:space-between;margin-bottom:16px;">
          <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-edit" style="color:var(--primary-color);"></i> Fill Document Details</h3>
          <button onclick="dtCloseForm()" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button>
        </div>
        <div id="dt-form" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;"></div>
        <button onclick="dtCloseForm()" style="width:100%;margin-top:16px;padding:12px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px;"><i class="fas fa-check"></i> Done</button>
      </div>
    </div>`;

  try{
    const j = await (await fetch(_mdBase + '/api.php?endpoint=settings/hospital')).json();
    if(j.success) dtHs = j.data || {};
  }catch(e){}

  const today = new Date().toISOString().split('T')[0];
  dtT.fields.forEach(f => {
    if(f.type === 'physician') dtV[f.key] = stripDr(window._loggedUserName || '');
    else if(f.type === 'rows') dtV[f.key] = [f.newRow()];
    else if(f.type === 'teeth') dtV[f.key] = [];
    else if(f.def != null) dtV[f.key] = typeof f.def === 'function' ? f.def() : f.def;
    else if(f.key === 'issued') dtV[f.key] = today;
  });
  dtT.init?.(dtV, dtHs);
  dtRenderForm();
  dtRef = await getNextDocRef(dtTypeKey);
  dtPreview();
}

function dtOpenForm(){ document.getElementById('dt-form-modal').style.display = 'flex'; }
function dtCloseForm(){ document.getElementById('dt-form-modal').style.display = 'none'; }

function dtFieldHtml(f){
  if(f.type === 'section') return `<div style="grid-column:1/-1;font-size:12px;font-weight:700;color:var(--primary-color);text-transform:uppercase;border-bottom:1px solid var(--gray-200);padding-bottom:4px;margin-top:4px;">${dtEsc(f.label)}</div>`;
  const id = 'dt-' + f.key, val = dtEsc(dtV[f.key] ?? '');
  const wide = f.full || ['textarea','rows','teeth'].includes(f.type);
  let input;
  switch(f.type){
    case 'select':
      input = `<select id="${id}" onchange="dtSet('${f.key}',this.value)" style="${DT_INPUT}"><option value="">—</option>${f.options.map(o => `<option ${dtV[f.key] === o ? 'selected' : ''}>${dtEsc(o)}</option>`).join('')}</select>`;
      break;
    case 'textarea':
      input = `<textarea id="${id}" rows="${f.rows || 3}" oninput="dtSet('${f.key}',this.value)" style="${DT_INPUT}resize:vertical;">${val}</textarea>`;
      break;
    case 'patient': case 'employee':
      input = `<div style="position:relative;"><input id="${id}" value="${val}" autocomplete="off" placeholder="Type to search ${f.type}..." oninput="dtSet('${f.key}',this.value);dtSearch('${f.key}','${f.type}')" style="${DT_INPUT}">
        <div id="${id}-dd" style="display:none;position:absolute;top:100%;left:0;right:0;background:white;border:1px solid var(--gray-200);border-radius:6px;max-height:180px;overflow-y:auto;z-index:100;box-shadow:0 4px 12px rgba(0,0,0,0.1);margin-top:2px;"></div></div>`;
      break;
    case 'physician':
      input = `<input id="${id}" value="${val}" readonly style="${DT_INPUT}background:var(--gray-50);cursor:not-allowed;">`;
      break;
    case 'rows': case 'teeth':
      input = `<div id="${id}"></div>`;
      break;
    default:
      input = `<input id="${id}" type="${f.type || 'text'}" value="${val}" ${f.calc ? 'readonly' : ''} oninput="dtSet('${f.key}',this.value)" style="${DT_INPUT}${f.calc ? 'background:var(--gray-50);' : ''}">`;
  }
  return `<div${wide ? ' style="grid-column:1/-1;"' : ''}><label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">${dtEsc(f.label)}</label>${input}</div>`;
}

function dtRenderForm(){
  document.getElementById('dt-form').innerHTML = dtT.fields.map(dtFieldHtml).join('');
  dtT.fields.forEach(f => {
    if(f.type === 'rows') dtRenderRows(f);
    if(f.type === 'teeth') document.getElementById('dt-' + f.key).innerHTML = dtTeethHtml(dtV[f.key], true);
  });
}

function dtSet(k, val){
  dtV[k] = val;
  if(dtT.calc){
    dtT.calc(dtV);
    dtT.fields.filter(f => f.calc).forEach(f => { const el = document.getElementById('dt-' + f.key); if(el) el.value = dtV[f.key] ?? ''; });
  }
  dtPreview();
}

// Repeatable entries (Fiche de suivi), separated by a dashed line in the form
function dtRenderRows(f){
  const rows = dtV[f.key];
  document.getElementById('dt-' + f.key).innerHTML = rows.map((r, i) => `
    ${i ? '<div style="border-top:2px dashed var(--gray-400,#9ca3af);margin:14px 0;"></div>' : ''}
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
      <strong style="font-size:12px;">Entry ${i + 1}</strong>
      ${rows.length > 1 ? `<button type="button" onclick="dtRowDel('${f.key}',${i})" style="border:none;background:none;color:#dc2626;cursor:pointer;font-size:12px;"><i class="fas fa-trash"></i> Remove</button>` : ''}
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">${f.cols.map(c => {
      const on = `oninput="dtRowSet('${f.key}',${i},'${c.key}',this.value)"`;
      const v = dtEsc(r[c.key] ?? '');
      const inp = c.type === 'textarea'
        ? `<textarea rows="2" ${on} style="${DT_INPUT}resize:vertical;">${v}</textarea>`
        : `<input type="${c.type || 'text'}" value="${v}" ${on} style="${DT_INPUT}">`;
      return `<div${c.type === 'textarea' ? ' style="grid-column:1/-1;"' : ''}><label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px;">${dtEsc(c.label)}</label>${inp}</div>`;
    }).join('')}</div>`).join('')
    + `<button type="button" onclick="dtRowAdd('${f.key}')" style="margin-top:12px;padding:8px 14px;background:var(--gray-50);border:1px dashed var(--gray-300,#d1d5db);border-radius:6px;cursor:pointer;font-size:12px;width:100%;"><i class="fas fa-plus"></i> Add entry</button>`;
}
function dtRowsField(k){ return dtT.fields.find(f => f.key === k); }
function dtRowSet(k, i, c, val){ dtV[k][i][c] = val; dtPreview(); }
function dtRowAdd(k){ const f = dtRowsField(k); dtV[k].push(f.newRow()); dtRenderRows(f); dtPreview(); }
function dtRowDel(k, i){ dtV[k].splice(i, 1); dtRenderRows(dtRowsField(k)); dtPreview(); }

function dtToggleTooth(n){
  const t = dtV.teeth, i = t.indexOf(n);
  i >= 0 ? t.splice(i, 1) : t.push(n);
  document.getElementById('dt-teeth').innerHTML = dtTeethHtml(t, true);
  dtPreview();
}

// Patient / employee search → fills every matching field the template has
function dtSearch(k, who){
  clearTimeout(dtTimer);
  dtTimer = setTimeout(async () => {
    const q = document.getElementById('dt-' + k).value.trim();
    const dd = document.getElementById('dt-' + k + '-dd');
    if(q.length < 2){ dd.style.display = 'none'; return; }
    try{
      const ep = who === 'employee' ? 'hr/employees' : 'patients/patients';
      const j = await (await fetch(_mdBase + '/api.php?endpoint=' + ep + '&search=' + encodeURIComponent(q) + '&limit=8')).json();
      dtList = j.data?.[who === 'employee' ? 'employees' : 'patients'] || [];
      dd.innerHTML = dtList.length
        ? dtList.map((p, i) => `<div onclick="dtPick('${k}','${who}',${i})" style="padding:8px 12px;cursor:pointer;font-size:12px;border-bottom:1px solid var(--gray-100);" onmouseover="this.style.background='var(--gray-50)'" onmouseout="this.style.background=''"><strong>${dtEsc(p.first_name + ' ' + p.last_name)}</strong> <span style="color:var(--gray-500);">${dtEsc(p.patient_no || p.employee_no || '')}</span></div>`).join('')
        : `<div style="padding:8px 12px;color:var(--gray-400);font-size:12px;">No ${who}s found</div>`;
      dd.style.display = 'block';
    }catch(e){ dd.style.display = 'none'; }
  }, 300);
}

async function dtPick(k, who, i){
  let p = dtList[i];
  document.getElementById('dt-' + k + '-dd').style.display = 'none';
  if(who === 'patient'){
    // full record has location names + insurance
    try{ const j = await (await fetch(_mdBase + '/api.php?endpoint=patients/patients&id=' + p.id)).json(); if(j.success) p = j.data; }catch(e){}
  }
  const fem = p.gender === 'female';
  const ins = (p.insurance || [])[0] || {};
  const map = who === 'employee'
    ? {position: p.position, department: p.department_name, start: p.date_joined}
    : {dob: p.date_of_birth, age: dtAge(p.date_of_birth), sex: fem ? 'F' : 'M', hf: p.patient_no,
       province: p.province_name, district: p.district_name, sector: p.sector_name, cell: p.cell_name, village: p.village_name,
       insurance: ins.company_name || p.insurance_company, insurance_id: ins.member_number || ins.policy_number};
  map[k] = (p.first_name + ' ' + p.last_name).trim();
  map.title = fem ? 'Mme' : 'Mr';
  dtT.fields.forEach(f => {
    if(map[f.key] == null || map[f.key] === '') return;
    dtV[f.key] = map[f.key];
    const el = document.getElementById('dt-' + f.key);
    if(el) el.value = map[f.key];
  });
  dtSet(k, dtV[k]);
}

function dtFrame(title, body, sign, issued){
  const hs = dtHs, ref = dtRef || '';
  const hospName = hs.hospital_name || 'Iramiro Clinic';
  const hRight = (hs.header_right || 'Date: {date}').replace('{date}', dtDate(issued)).replace('{ref}', ref);
  const fLeft = hs.footer_left || (hs.address || 'Kigali, Rwanda');
  const fRight = hs.footer_right || (hs.phone ? 'Tel: ' + hs.phone + ' | ' : '') + 'Email: ' + (hs.email || '');
  const fStyle = `font-size:${hs.footer_font_size || 9}px;color:${hs.footer_text_color || '#1f2937'};`;
  let wm = '';
  if(hs.watermark_enabled === '1'){
    const pos = `position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(${hs.watermark_rotation || -30}deg);opacity:${(parseInt(hs.watermark_opacity) || 15) / 100};pointer-events:none;z-index:0;`;
    wm = hs.watermark_type === 'image' && hs.watermark_image
      ? `<div style="${pos}"><img src="${hs.watermark_image}" style="width:150px;height:auto;"></div>`
      : `<div style="${pos}font-size:${hs.watermark_size || 40}px;color:${hs.watermark_color || '#d1d5db'};font-weight:700;white-space:nowrap;">${hs.watermark_text || hospName}</div>`;
  }
  return `${wm}<div style="position:relative;z-index:1;">
    <img src="assets/letterhead.png" style="display:block;width:100%;height:auto;">
    <div style="display:flex;justify-content:flex-end;margin:4px 0 10px;"><span style="font-size:${hs.header_font_size || 11}px;color:${hs.header_text_color || '#1f2937'};">${hRight}</span></div>
    <div style="text-align:center;margin-bottom:12px;">
      <h2 style="font-size:15px;font-weight:700;text-decoration:underline;margin:0;">${title}</h2>
      ${ref ? `<div style="font-size:11px;color:#64748b;margin-top:4px;">Ref: <strong>${ref}</strong></div>` : ''}
    </div>
    ${body}
    <div style="margin-top:16px;display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:12px;">
      <div>${sign}</div>
      <div style="display:flex;align-items:center;gap:8px;background:#f8fafc;border:1px solid #cbd5e1;border-radius:6px;padding:6px 10px;">
        <div id="dt-qr-code" style="width:68px;height:68px;background:white;padding:2px;border:1px solid #e2e8f0;border-radius:4px;display:flex;align-items:center;justify-content:center;"></div>
        <div>
          <div style="font-size:9px;font-weight:700;color:#059669;text-transform:uppercase;letter-spacing:0.4px;">✓ Verified Document</div>
          <div style="font-size:11px;font-weight:700;color:#0f172a;margin-top:1px;">${ref}</div>
          <div style="font-size:9px;color:#64748b;margin-top:2px;">Scan with camera<br>to verify authenticity</div>
        </div>
      </div>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;padding-top:6px;border-top:${hs.footer_line_size || 2}px solid ${hs.footer_line_color || '#059669'};margin-top:16px;">
      <span style="${fStyle}">${fLeft}</span><span style="${fStyle}">${fRight}</span>
    </div>
  </div>`;
}

function dtPreview(){
  const h = dtHelpers(dtV, dtT);
  document.getElementById('dt-preview').innerHTML = dtFrame(dtT.title, dtT.body(h), dtT.sign ? dtT.sign(h) : '', dtV.issued || new Date().toISOString().split('T')[0]);
  renderDocQRCode('dt-qr-code', dtRef, 68);
}

// Label/value pairs shown on verify-document.php
function dtSummary(){
  const out = [];
  dtT.fields.forEach(f => {
    const val = dtV[f.key];
    if(f.type === 'section' || f.type === 'physician') return;
    if(f.type === 'rows'){
      const n = (val || []).filter(r => Object.values(r).some(x => String(x ?? '').trim())).length;
      if(n) out.push([f.label.replace(/\s*\(.*\)$/, ''), n + (n > 1 ? ' entries' : ' entry')]);
      return;
    }
    if(f.type === 'teeth'){ if(val?.length) out.push(['Teeth', val.join(', ')]); return; }
    const s = String(val ?? '').trim();
    if(s) out.push([f.label.replace(/\s*\*$/, ''), /date/.test(f.type || '') ? dtDate(s) : s]);
  });
  return out;
}

async function dtSave(){
  const name = String(dtV.name || '').trim();
  if(!name){ alert('Please fill in the name.'); return null; }
  const ref = dtRef;
  const signer = dtT.hr ? (window._loggedUserName || '') : (dtV.physician || window._loggedUserName || '');
  await saveDocument(dtTypeKey, name, signer, {...dtV, _tpl: 1, _summary: dtSummary(), reference_no: ref}, ref);
  return ref || 'DOC';
}

async function dtPrint(){
  if(!await dtSave()) return;
  const content = document.getElementById('dt-preview').innerHTML;
  const win = window.open('', '_blank', 'width=800,height=600');
  win.document.write(`<html><head><title>${dtEsc(dtT.title)}</title><style>
    @page{size:A4;margin:10mm;}
    body{font-family:'Times New Roman',serif;padding:10px;color:#000;line-height:1.6;font-size:${dtT.size || 14}px;}
    img{max-width:100%;}
  </style></head><body><div style="position:relative;overflow:hidden;">${content}</div></body></html>`);
  win.document.close();
  win.focus();
  setTimeout(() => { win.print(); }, 500);
  dtRef = await getNextDocRef(dtTypeKey);
  dtPreview();
}

async function dtPDF(){
  const ref = await dtSave();
  if(!ref) return;
  html2pdf().set({
    margin: 10,
    filename: ref + '_' + String(dtV.name).trim().replace(/\s+/g, '_') + '.pdf',
    image: {type:'jpeg', quality:0.98},
    html2canvas: {scale:2, useCORS:true},
    jsPDF: {unit:'mm', format:'a4', orientation:'portrait'}
  }).from(document.getElementById('dt-preview')).save().then(async () => {
    dtRef = await getNextDocRef(dtTypeKey);
    dtPreview();
  });
}
