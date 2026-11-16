<?php /* includes/ui_prompt.php — window.uiPrompt() (in-page prompt). Included by requests.php / procurement.php; harmless if already defined. */ ?>
<script>
/* In-page replacement for window.prompt(): native prompts block the page and cannot be
   used by automated UI tests. Returns a Promise that resolves to the text, or null on cancel. */
(function () {
    if (window.uiPrompt) return;
    var backdrop = null, input = null, titleEl = null, msgEl = null, errEl = null, okBtn = null, cancelBtn = null;

    function build() {
        if (backdrop) return;
        backdrop = document.createElement('div');
        backdrop.id = 'ui-prompt-backdrop';
        backdrop.style.cssText = 'display:none;position:fixed;inset:0;z-index:100000;background:rgba(15,23,42,.45);align-items:center;justify-content:center;padding:16px;';
        backdrop.innerHTML =
            '<div style="background:#fff;border-radius:12px;padding:22px;width:440px;max-width:100%;box-shadow:0 20px 50px rgba(0,0,0,.25);box-sizing:border-box;">' +
              '<h3 id="ui-prompt-title" style="font-size:15px;font-weight:600;margin:0 0 8px;color:#111827;"></h3>' +
              '<p id="ui-prompt-msg" style="font-size:13px;color:#4b5563;white-space:pre-wrap;margin:0 0 12px;"></p>' +
              '<textarea id="ui-prompt-input" rows="3" style="width:100%;padding:9px 10px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;font-family:inherit;resize:vertical;box-sizing:border-box;"></textarea>' +
              '<div id="ui-prompt-err" style="display:none;color:#dc2626;font-size:12px;margin-top:6px;"></div>' +
              '<div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px;">' +
                '<button type="button" id="ui-prompt-cancel" style="padding:8px 14px;border:1px solid #d1d5db;background:#fff;border-radius:8px;font-size:13px;cursor:pointer;">Cancel</button>' +
                '<button type="button" id="ui-prompt-ok" style="padding:8px 14px;border:none;background:var(--primary-color,#2563eb);color:#fff;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">OK</button>' +
              '</div>' +
            '</div>';
        document.body.appendChild(backdrop);
        titleEl = document.getElementById('ui-prompt-title');
        msgEl = document.getElementById('ui-prompt-msg');
        input = document.getElementById('ui-prompt-input');
        errEl = document.getElementById('ui-prompt-err');
        okBtn = document.getElementById('ui-prompt-ok');
        cancelBtn = document.getElementById('ui-prompt-cancel');
    }

    window.uiPrompt = function (message, defaultValue, opts) {
        opts = opts || {};
        build();
        return new Promise(function (resolve) {
            function done(value) {
                backdrop.style.display = 'none';
                resolve(value);
            }
            titleEl.textContent = opts.title || 'Please confirm';
            msgEl.textContent = message || '';
            msgEl.style.display = message ? '' : 'none';
            input.value = defaultValue == null ? '' : String(defaultValue);
            input.placeholder = opts.placeholder || '';
            errEl.style.display = 'none';
            okBtn.textContent = opts.okLabel || 'OK';
            backdrop.style.display = 'flex';
            setTimeout(function () { try { input.focus(); input.select(); } catch (e) {} }, 30);
            okBtn.onclick = function () {
                var v = input.value;
                if (opts.required && !v.trim()) {
                    errEl.textContent = opts.requiredMessage || 'This field is required.';
                    errEl.style.display = 'block';
                    input.focus();
                    return;
                }
                done(v);
            };
            cancelBtn.onclick = function () { done(null); };
            input.onkeydown = function (e) {
                if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); okBtn.click(); }
                if (e.key === 'Escape') { e.preventDefault(); cancelBtn.click(); }
            };
        });
    };
})();
</script>
