// Hospital Settings JavaScript
const hsAPI = (typeof _apiBase !== 'undefined' ? _apiBase : 'api.php') + '?endpoint=settings/hospital';

function hsPreviewLogo(input){
  if(input.files && input.files[0]){
    const reader = new FileReader();
    reader.onload = function(e){
      window._hsLogoSrc = e.target.result;
      document.getElementById('hs-logo-preview').innerHTML = '<img src="'+e.target.result+'" style="width:100%;height:100%;object-fit:contain;">';
      hsUpdatePreview();
    };
    reader.readAsDataURL(input.files[0]);
  }
}

function hsPreviewWmImage(input){
  if(input.files && input.files[0]){
    const reader = new FileReader();
    reader.onload = function(e){
      document.getElementById('hs-wm-image-preview').innerHTML = '<img src="'+e.target.result+'" style="width:100%;height:100%;object-fit:contain;">';
      hsUpdatePreview();
    };
    reader.readAsDataURL(input.files[0]);
  }
}

function hsPreviewDocLogo(input){
  if(input.files && input.files[0]){
    const reader = new FileReader();
    reader.onload = function(e){
      window._hsDocLogoSrc = e.target.result;
      document.getElementById('hs-doc-logo-preview').innerHTML = '<img src="'+e.target.result+'" style="width:100%;height:100%;object-fit:contain;">';
      hsUpdatePreview();
    };
    reader.readAsDataURL(input.files[0]);
  }
}

function hsToggleWatermarkType(){
  const type = document.getElementById('hs-watermark-type').value;
  const textField = document.getElementById('hs-wm-text-field');
  const colorField = document.getElementById('hs-wm-color-field');
  const imageField = document.getElementById('hs-wm-image-field');
  const fontSizeField = document.getElementById('hs-wm-fontsize-field');
  if(type === 'image'){
    textField.style.display = 'none';
    colorField.style.display = 'none';
    fontSizeField.style.display = 'none';
    imageField.style.display = 'block';
  } else {
    textField.style.display = 'block';
    colorField.style.display = 'block';
    fontSizeField.style.display = 'block';
    imageField.style.display = 'none';
  }
  hsUpdatePreview();
}

async function hsSaveInfo(){
  const data = {
    section: 'hospital_info',
    hospital_name: document.getElementById('hs-name').value.trim(),
    email: document.getElementById('hs-email').value.trim(),
    phone: document.getElementById('hs-phone').value.trim(),
    po_box: document.getElementById('hs-pobox').value.trim(),
    address: document.getElementById('hs-address').value.trim(),
    facility_category: document.getElementById('hs-facility-category').value,
    website_url: document.getElementById('hs-website-url').value.trim()
  };
  if(!data.hospital_name) return alert('Hospital name is required.');
  if(!data.facility_category) return alert('Please select a facility category.');

  // Handle logo upload
  const logoInput = document.getElementById('hs-logo');
  if(logoInput.files && logoInput.files[0]){
    const reader = new FileReader();
    reader.onload = async function(e){
      data.logo = e.target.result;
      await hsSaveData(data);
    };
    reader.readAsDataURL(logoInput.files[0]);
  } else {
    await hsSaveData(data);
  }
}

async function hsSaveDoc(){
  const data = {
    section: 'document_settings',
    header_left: document.getElementById('hs-header-left').value.trim(),
    header_right: document.getElementById('hs-header-right').value.trim(),
    header_line_color: document.getElementById('hs-header-line-color').value,
    header_line_size: document.getElementById('hs-header-line-size').value,
    header_text_color: document.getElementById('hs-header-text-color').value,
    header_font_size: document.getElementById('hs-header-font-size').value,
    footer_left: document.getElementById('hs-footer-left').value.trim(),
    footer_right: document.getElementById('hs-footer-right').value.trim(),
    footer_line_color: document.getElementById('hs-footer-line-color').value,
    footer_line_size: document.getElementById('hs-footer-line-size').value,
    footer_text_color: document.getElementById('hs-footer-text-color').value,
    footer_font_size: document.getElementById('hs-footer-font-size').value,
    watermark_type: document.getElementById('hs-watermark-type').value,
    watermark_text: document.getElementById('hs-watermark-text').value.trim(),
    watermark_color: document.getElementById('hs-watermark-color').value,
    watermark_size: document.getElementById('hs-watermark-size').value,
    watermark_opacity: document.getElementById('hs-watermark-opacity').value,
    watermark_rotation: document.getElementById('hs-watermark-rotation').value,
    watermark_enabled: document.getElementById('hs-watermark-enabled').value
  };

  // Handle watermark image
  const wmImageInput = document.getElementById('hs-watermark-image');
  const docLogoInput = document.getElementById('hs-doc-logo');
  
  let pending = 0;
  let ready = () => { pending--; if(pending<=0){ hsSaveData(data); hsUpdatePreview(); } };
  
  if(docLogoInput.files && docLogoInput.files[0]){
    pending++;
    const r = new FileReader();
    r.onload = function(e){ data.doc_logo = e.target.result; window._hsDocLogoSrc = e.target.result; ready(); };
    r.readAsDataURL(docLogoInput.files[0]);
  }
  if(wmImageInput.files && wmImageInput.files[0]){
    pending++;
    const r = new FileReader();
    r.onload = function(e){ data.watermark_image = e.target.result; ready(); };
    r.readAsDataURL(wmImageInput.files[0]);
  }
  if(pending === 0){
    await hsSaveData(data);
    hsUpdatePreview();
  }
}

async function hsSaveData(data){
  try{
    const r = await fetch(hsAPI, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data)});
    const j = await r.json();
    if(j.success) alert('✓ ' + j.message);
    else alert('Error: ' + j.message);
  }catch(e){
    alert('Failed: ' + e.message);
  }
}

async function hsLoadSettings(){
  try{
    const r = await fetch(hsAPI);
    const j = await r.json();
    if(!j.success) return;
    const d = j.data || {};

    // Hospital Info
    if(d.hospital_name) document.getElementById('hs-name').value = d.hospital_name;
    if(d.email) document.getElementById('hs-email').value = d.email;
    if(d.phone) document.getElementById('hs-phone').value = d.phone;
    if(d.po_box) document.getElementById('hs-pobox').value = d.po_box;
    if(d.address) document.getElementById('hs-address').value = d.address;
    if(d.facility_category) document.getElementById('hs-facility-category').value = d.facility_category;
    if(d.website_url) document.getElementById('hs-website-url').value = d.website_url;
    if(d.logo) {
      window._hsLogoSrc = d.logo;
      document.getElementById('hs-logo-preview').innerHTML = '<img src="'+d.logo+'" style="width:100%;height:100%;object-fit:contain;">';
    }

    // Document Settings
    if(d.header_left) document.getElementById('hs-header-left').value = d.header_left;
    if(d.header_right) document.getElementById('hs-header-right').value = d.header_right;
    if(d.doc_logo){
      window._hsDocLogoSrc = d.doc_logo;
      document.getElementById('hs-doc-logo-preview').innerHTML = '<img src="'+d.doc_logo+'" style="width:100%;height:100%;object-fit:contain;">';
    }
    if(d.header_right) document.getElementById('hs-header-right').value = d.header_right;
    if(d.header_line_color) document.getElementById('hs-header-line-color').value = d.header_line_color;
    if(d.header_line_size) document.getElementById('hs-header-line-size').value = d.header_line_size;
    if(d.header_text_color) document.getElementById('hs-header-text-color').value = d.header_text_color;
    if(d.header_font_size) document.getElementById('hs-header-font-size').value = d.header_font_size;
    if(d.footer_left) document.getElementById('hs-footer-left').value = d.footer_left;
    if(d.footer_right) document.getElementById('hs-footer-right').value = d.footer_right;
    if(d.footer_line_color) document.getElementById('hs-footer-line-color').value = d.footer_line_color;
    if(d.footer_line_size) document.getElementById('hs-footer-line-size').value = d.footer_line_size;
    if(d.footer_text_color) document.getElementById('hs-footer-text-color').value = d.footer_text_color;
    if(d.footer_font_size) document.getElementById('hs-footer-font-size').value = d.footer_font_size;
    if(d.watermark_text) document.getElementById('hs-watermark-text').value = d.watermark_text;
    if(d.watermark_color) document.getElementById('hs-watermark-color').value = d.watermark_color;
    if(d.watermark_size) document.getElementById('hs-watermark-size').value = d.watermark_size;
    if(d.watermark_opacity) document.getElementById('hs-watermark-opacity').value = d.watermark_opacity;
    if(d.watermark_rotation) document.getElementById('hs-watermark-rotation').value = d.watermark_rotation;
    if(d.watermark_enabled !== undefined) document.getElementById('hs-watermark-enabled').value = d.watermark_enabled;
    if(d.watermark_type) document.getElementById('hs-watermark-type').value = d.watermark_type;
    if(d.watermark_image) document.getElementById('hs-wm-image-preview').innerHTML = '<img src="'+d.watermark_image+'" style="width:100%;height:100%;object-fit:contain;">';
    hsToggleWatermarkType();

    // Delay preview update to ensure all DOM elements (logo img) are ready
    setTimeout(hsUpdatePreview, 100);
  }catch(e){ console.error('Failed to load settings:', e); }
}

function hsUpdatePreview(){
  const hLineColor = document.getElementById('hs-header-line-color').value;
  const hLineSize = document.getElementById('hs-header-line-size').value;
  const hTextColor = document.getElementById('hs-header-text-color').value;
  const hFontSize = document.getElementById('hs-header-font-size').value;
  const fLineColor = document.getElementById('hs-footer-line-color').value;
  const fLineSize = document.getElementById('hs-footer-line-size').value;
  const fTextColor = document.getElementById('hs-footer-text-color').value;
  const fFontSize = document.getElementById('hs-footer-font-size').value;

  // Header
  const hdr = document.getElementById('hs-prev-header');
  hdr.style.borderBottom = hLineSize+'px solid '+hLineColor;
  const hLeft = document.getElementById('hs-prev-header-left');
  hLeft.textContent = document.getElementById('hs-header-left').value || document.getElementById('hs-name').value || 'Hospital Name';
  hLeft.style.color = hTextColor;
  hLeft.style.fontSize = hFontSize+'px';
  const hRight = document.getElementById('hs-prev-header-right');
  hRight.textContent = document.getElementById('hs-header-right').value || 'Date: '+new Date().toISOString().split('T')[0]+' | Ref: DOC-001';
  hRight.style.color = hTextColor;
  hRight.style.fontSize = hFontSize+'px';

  // Logo in header preview
  const logoPreview = document.getElementById('hs-prev-logo');
  if(window._hsDocLogoSrc){
    logoPreview.src = window._hsDocLogoSrc;
    logoPreview.style.display = 'block';
  } else if(window._hsLogoSrc){
    logoPreview.src = window._hsLogoSrc;
    logoPreview.style.display = 'block';
  } else {
    logoPreview.style.display = 'none';
  }

  // Footer
  const ftr = document.getElementById('hs-prev-footer');
  ftr.style.borderTop = fLineSize+'px solid '+fLineColor;
  const fLeft = document.getElementById('hs-prev-footer-left');
  fLeft.textContent = document.getElementById('hs-footer-left').value || 'Address';
  fLeft.style.color = fTextColor;
  fLeft.style.fontSize = fFontSize+'px';
  const fRight = document.getElementById('hs-prev-footer-right');
  fRight.textContent = document.getElementById('hs-footer-right').value || 'Contact & Email';
  fRight.style.color = fTextColor;
  fRight.style.fontSize = fFontSize+'px';

  // Watermark
  const wm = document.getElementById('hs-prev-watermark');
  const wmEnabled = document.getElementById('hs-watermark-enabled').value === '1';
  const wmType = document.getElementById('hs-watermark-type').value;
  wm.style.display = wmEnabled ? 'block' : 'none';
  const wmOpacity = (parseInt(document.getElementById('hs-watermark-opacity').value)||15) / 100;
  const rotation = document.getElementById('hs-watermark-rotation').value || -30;
  wm.style.opacity = wmOpacity;
  wm.style.transform = 'translate(-50%,-50%) rotate('+rotation+'deg)';

  if(wmType === 'image'){
    // Show image watermark
    const imgEl = document.querySelector('#hs-wm-image-preview img');
    if(imgEl){
      wm.innerHTML = '<img src="'+imgEl.src+'" style="width:200px;height:auto;">';
    } else {
      wm.innerHTML = '<i class="fas fa-image" style="font-size:80px;color:#d1d5db;"></i>';
    }
    wm.style.fontSize = '';
    wm.style.color = '';
  } else {
    // Show text watermark
    wm.innerHTML = '';
    wm.textContent = document.getElementById('hs-watermark-text').value || 'WATERMARK';
    const wmColor = document.getElementById('hs-watermark-color').value;
    wm.style.color = wmColor;
    wm.style.fontSize = (document.getElementById('hs-watermark-size').value||60)+'px';
  }
}

// Live preview on input change
document.addEventListener('input', function(e){
  const id = e.target.id || '';
  if(id.startsWith('hs-header-') || id.startsWith('hs-footer-') || id.startsWith('hs-watermark-')){
    hsUpdatePreview();
  }
});
document.addEventListener('change', function(e){
  const id = e.target.id || '';
  if(id.startsWith('hs-header-') || id.startsWith('hs-footer-') || id.startsWith('hs-watermark-')){
    hsUpdatePreview();
  }
});

hsLoadSettings();
