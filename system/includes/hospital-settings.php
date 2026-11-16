<!-- Hospital Settings - Hospital Info -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);margin-bottom:20px;">
  <h3 style="font-size:16px;font-weight:600;margin-bottom:16px;"><i class="fas fa-hospital-alt" style="color:var(--primary-color);"></i> Hospital Information</h3>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
    <div>
      <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Hospital Name *</label>
      <input id="hs-name" type="text" placeholder="e.g. Iramiro Clinic" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
    </div>
    <div>
      <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Email *</label>
      <input id="hs-email" type="email" placeholder="info@hospital.rw" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
    </div>
    <div>
      <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Phone Number *</label>
      <input id="hs-phone" type="text" placeholder="+250 788 000 000" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
    </div>
    <div>
      <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">P.O Box</label>
      <input id="hs-pobox" type="text" placeholder="P.O Box 1861 Kigali, Rwanda" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
    </div>
    <div>
      <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Address *</label>
      <input id="hs-address" type="text" placeholder="Kigali, Rwanda" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
    </div>
    <div>
      <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Facility Category *</label>
      <select id="hs-facility-category" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;">
        <option value="">— Select Category —</option>
        <option value="primary">Primary Health Facility</option>
        <option value="secondary">Secondary Health Facility</option>
        <option value="tertiary">Tertiary Health Facility</option>
      </select>
    </div>
    <div style="grid-column:1/-1;">
      <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Website / System URL <small style="font-weight:400;color:var(--gray-400);">(used in the QR code on documents)</small></label>
      <input id="hs-website-url" type="url" placeholder="https://yourhospital.rw/system" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      <div style="font-size:11px;color:var(--gray-500);margin-top:4px;">Address where this system is reachable from the internet, up to the folder that holds verify-document.php. Scanning a QR code opens <strong>this URL</strong>/verify-document.php?ref=… Leave empty to use the address you are browsing from (does not work for phones when it is localhost).</div>
    </div>
    <div style="grid-column:1/-1;">
      <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Hospital Logo</label>
      <div style="display:flex;align-items:center;gap:12px;">
        <div id="hs-logo-preview" style="width:80px;height:80px;border:2px dashed var(--gray-200);border-radius:8px;display:flex;align-items:center;justify-content:center;overflow:hidden;">
          <i class="fas fa-image" style="font-size:24px;color:var(--gray-300);"></i>
        </div>
        <div>
          <input id="hs-logo" type="file" accept="image/*" onchange="hsPreviewLogo(this)" style="font-size:12px;">
          <p style="font-size:11px;color:var(--gray-400);margin-top:4px;">Recommended: 200x200px, PNG or JPG</p>
        </div>
      </div>
    </div>
  </div>
  <button onclick="hsSaveInfo()" style="margin-top:16px;padding:10px 24px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;"><i class="fas fa-save"></i> Save Hospital Info</button>
</div>

<!-- Document Settings: Header & Footer -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);margin-bottom:20px;">
  <h3 style="font-size:16px;font-weight:600;margin-bottom:16px;"><i class="fas fa-file-alt" style="color:var(--primary-color);"></i> Document Settings (Header & Footer)</h3>
  
  <!-- Header Settings -->
  <div style="border:1px solid var(--gray-200);border-radius:8px;padding:16px;margin-bottom:16px;">
    <h4 style="font-size:13px;font-weight:600;margin-bottom:12px;color:#059669;"><i class="fas fa-arrow-up"></i> Header</h4>
    <p style="font-size:11px;color:var(--gray-500);margin-bottom:10px;">Left side: Logo + Hospital name | Right side: Date & Document Reference</p>
    
    <!-- Document Logo -->
    <div style="margin-bottom:12px;padding:10px;background:var(--gray-50);border-radius:6px;">
      <label style="display:block;font-size:11px;font-weight:600;margin-bottom:6px;">Document Logo (appears on header left)</label>
      <div style="display:flex;align-items:center;gap:12px;">
        <div id="hs-doc-logo-preview" style="width:70px;height:50px;border:2px dashed var(--gray-200);border-radius:6px;display:flex;align-items:center;justify-content:center;overflow:hidden;background:white;">
          <i class="fas fa-image" style="font-size:18px;color:var(--gray-300);"></i>
        </div>
        <div>
          <input id="hs-doc-logo" type="file" accept="image/*" onchange="hsPreviewDocLogo(this)" style="font-size:11px;">
          <p style="font-size:10px;color:var(--gray-400);margin-top:3px;">This logo will appear on all printed documents</p>
        </div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div>
        <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px;">Header Left Text</label>
        <input id="hs-header-left" type="text" placeholder="Hospital Name" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
      </div>
      <div>
        <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px;">Header Right Text</label>
        <input id="hs-header-right" type="text" placeholder="Date: {date} | Ref: {ref}" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
      </div>
      <div>
        <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px;">Header Line Color</label>
        <div style="display:flex;gap:6px;align-items:center;">
          <input id="hs-header-line-color" type="color" value="#059669" style="width:40px;height:32px;border:1px solid var(--gray-200);border-radius:4px;cursor:pointer;">
          <span style="font-size:11px;color:var(--gray-500);">Default: Green</span>
        </div>
      </div>
      <div>
        <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px;">Header Line Size (px)</label>
        <input id="hs-header-line-size" type="number" value="2" min="1" max="10" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
      </div>
      <div>
        <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px;">Header Text Color</label>
        <input id="hs-header-text-color" type="color" value="#1f2937" style="width:40px;height:32px;border:1px solid var(--gray-200);border-radius:4px;cursor:pointer;">
      </div>
      <div>
        <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px;">Header Font Size (px)</label>
        <input id="hs-header-font-size" type="number" value="12" min="8" max="24" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
      </div>
    </div>
  </div>

  <!-- Footer Settings -->
  <div style="border:1px solid var(--gray-200);border-radius:8px;padding:16px;margin-bottom:16px;">
    <h4 style="font-size:13px;font-weight:600;margin-bottom:12px;color:#059669;"><i class="fas fa-arrow-down"></i> Footer</h4>
    <p style="font-size:11px;color:var(--gray-500);margin-bottom:10px;">Left side: Address | Right side: Contact & Email</p>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div>
        <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px;">Footer Left Text (Address)</label>
        <input id="hs-footer-left" type="text" placeholder="Kigali, Rwanda | P.O Box 1861" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
      </div>
      <div>
        <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px;">Footer Right Text (Contact)</label>
        <input id="hs-footer-right" type="text" placeholder="Tel: +250... | Email: info@..." style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
      </div>
      <div>
        <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px;">Footer Line Color</label>
        <div style="display:flex;gap:6px;align-items:center;">
          <input id="hs-footer-line-color" type="color" value="#059669" style="width:40px;height:32px;border:1px solid var(--gray-200);border-radius:4px;cursor:pointer;">
          <span style="font-size:11px;color:var(--gray-500);">Default: Green</span>
        </div>
      </div>
      <div>
        <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px;">Footer Line Size (px)</label>
        <input id="hs-footer-line-size" type="number" value="2" min="1" max="10" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
      </div>
      <div>
        <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px;">Footer Text Color</label>
        <input id="hs-footer-text-color" type="color" value="#1f2937" style="width:40px;height:32px;border:1px solid var(--gray-200);border-radius:4px;cursor:pointer;">
      </div>
      <div>
        <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px;">Footer Font Size (px)</label>
        <input id="hs-footer-font-size" type="number" value="10" min="8" max="24" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
      </div>
    </div>
  </div>

  <!-- Watermark Settings -->
  <div style="border:1px solid var(--gray-200);border-radius:8px;padding:16px;margin-bottom:16px;">
    <h4 style="font-size:13px;font-weight:600;margin-bottom:12px;color:#7c3aed;"><i class="fas fa-tint"></i> Watermark</h4>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
      <div>
        <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px;">Watermark Type</label>
        <select id="hs-watermark-type" onchange="hsToggleWatermarkType()" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
          <option value="text">Text</option>
          <option value="image">Image</option>
        </select>
      </div>
      <div id="hs-wm-text-field">
        <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px;">Watermark Text</label>
        <input id="hs-watermark-text" type="text" placeholder="e.g. IRAMIRO" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
      </div>
      <div id="hs-wm-color-field">
        <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px;">Watermark Color</label>
        <input id="hs-watermark-color" type="color" value="#d1d5db" style="width:40px;height:32px;border:1px solid var(--gray-200);border-radius:4px;cursor:pointer;">
      </div>
      <div id="hs-wm-image-field" style="display:none;grid-column:2/-1;">
        <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px;">Watermark Image</label>
        <div style="display:flex;align-items:center;gap:8px;">
          <div id="hs-wm-image-preview" style="width:60px;height:60px;border:1px dashed var(--gray-300);border-radius:6px;display:flex;align-items:center;justify-content:center;overflow:hidden;">
            <i class="fas fa-image" style="color:var(--gray-300);"></i>
          </div>
          <input id="hs-watermark-image" type="file" accept="image/*" onchange="hsPreviewWmImage(this)" style="font-size:11px;">
        </div>
        <p style="font-size:10px;color:var(--gray-400);margin-top:3px;">PNG with transparency recommended</p>
      </div>
      <div id="hs-wm-fontsize-field">
        <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px;">Font Size (px)</label>
        <input id="hs-watermark-size" type="number" value="60" min="20" max="150" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
      </div>
      <div>
        <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px;">Opacity (%)</label>
        <input id="hs-watermark-opacity" type="number" value="15" min="5" max="50" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
      </div>
      <div>
        <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px;">Rotation (degrees)</label>
        <input id="hs-watermark-rotation" type="number" value="-30" min="-90" max="90" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
      </div>
      <div>
        <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px;">Enable Watermark</label>
        <select id="hs-watermark-enabled" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
          <option value="1">Yes</option>
          <option value="0">No</option>
        </select>
      </div>
    </div>
  </div>

  <button onclick="hsSaveDoc()" style="padding:10px 24px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;"><i class="fas fa-save"></i> Save Document Settings</button>
</div>

<!-- Live Preview -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <h3 style="font-size:16px;font-weight:600;margin-bottom:16px;"><i class="fas fa-eye" style="color:var(--primary-color);"></i> Document Preview</h3>
  <div id="hs-doc-preview" style="border:1px solid var(--gray-200);border-radius:8px;padding:30px;min-height:400px;position:relative;overflow:hidden;background:white;">
    <!-- Watermark -->
    <div id="hs-prev-watermark" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(-30deg);font-size:60px;color:rgba(209,213,219,0.15);font-weight:700;white-space:nowrap;pointer-events:none;z-index:0;">IRAMIRO</div>
    <!-- Header -->
    <div style="position:relative;z-index:1;">
      <div id="hs-prev-header" style="display:flex;justify-content:space-between;align-items:center;padding-bottom:8px;border-bottom:2px solid #059669;margin-bottom:20px;">
        <div style="display:flex;align-items:center;gap:10px;">
          <img id="hs-prev-logo" src="" style="height:40px;width:auto;display:none;">
          <span id="hs-prev-header-left" style="font-size:12px;font-weight:600;color:#1f2937;">Hospital Name</span>
        </div>
        <span id="hs-prev-header-right" style="font-size:12px;color:#1f2937;">Date: 2026-07-10 | Ref: DOC-001</span>
      </div>
      <!-- Body placeholder -->
      <div style="min-height:250px;display:flex;align-items:center;justify-content:center;color:var(--gray-300);font-size:14px;">
        <i class="fas fa-file-alt" style="font-size:48px;"></i>
      </div>
      <!-- Footer -->
      <div id="hs-prev-footer" style="display:flex;justify-content:space-between;align-items:center;padding-top:8px;border-top:2px solid #059669;margin-top:20px;">
        <span id="hs-prev-footer-left" style="font-size:10px;color:#1f2937;">Kigali, Rwanda | P.O Box 1861</span>
        <span id="hs-prev-footer-right" style="font-size:10px;color:#1f2937;">Tel: +250... | Email: info@...</span>
      </div>
    </div>
  </div>
  <button onclick="hsUpdatePreview()" style="margin-top:12px;padding:8px 16px;background:var(--gray-100);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-sync-alt"></i> Refresh Preview</button>
</div>

<script src="js/hospital-settings.js"></script>
