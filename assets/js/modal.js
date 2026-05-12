// modal.js — sistem modal reusable: showFormModal, showConfirmModal, showAlertModal.
// Pakai dari mana saja setelah <script src="../assets/js/modal.js"></script>.
//
//   const data = await showFormModal({
//     title: 'Tambah Semester',
//     fields: [
//       { name: 'name', label: 'Nama', type: 'text', required: true, value: '' },
//       { name: 'is_active', label: 'Aktif sekarang', type: 'checkbox', value: false },
//     ],
//     submitLabel: 'Simpan',
//   });
//   if (!data) return; // user cancel / esc / backdrop click
//
//   const ok = await showConfirmModal({ title:'Hapus?', message:'...', danger:true });
//   if (!ok) return;
//
//   await showAlertModal({ type:'error', title:'Gagal', message:'...' });
//
// Fitur: ESC tutup, klik backdrop tutup, body scroll-lock, animasi fade 150ms.

(function (global) {
  'use strict';

  function injectCss() {
    if (document.getElementById('tunotes-modal-styles')) return;
    const style = document.createElement('style');
    style.id = 'tunotes-modal-styles';
    style.textContent = ''
      + '.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,0.4);'
      + 'backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);z-index:9999;'
      + 'display:flex;align-items:center;justify-content:center;padding:1rem;'
      + 'opacity:0;transition:opacity 0.15s ease-out;}'
      + '.modal-backdrop.show{opacity:1;}'
      + '.modal-card{background:#fff;border-radius:12px;padding:2rem;max-width:520px;width:100%;'
      + 'box-shadow:0 20px 60px rgba(0,0,0,0.2);max-height:90vh;overflow-y:auto;'
      + 'transform:translateY(10px);transition:transform 0.15s ease-out;}'
      + '.modal-backdrop.show .modal-card{transform:translateY(0);}'
      + '.modal-title{font-size:1.4rem;font-weight:700;margin:0 0 1.25rem 0;color:#111827;}'
      + '.modal-message{color:#4b5563;margin:0 0 1.5rem 0;line-height:1.5;}'
      + '.modal-field{margin-bottom:1rem;}'
      + '.modal-field label{display:block;font-weight:600;margin-bottom:0.4rem;color:#374151;font-size:0.9rem;}'
      + '.modal-field input[type=text],.modal-field input[type=date],.modal-field input[type=number],'
      + '.modal-field input[type=url],.modal-field input[type=email],'
      + '.modal-field textarea,.modal-field select{width:100%;padding:0.6rem 0.9rem;border:1px solid #d1d5db;'
      + 'border-radius:6px;font-family:inherit;font-size:0.95rem;box-sizing:border-box;background:#fff;}'
      + '.modal-field textarea{resize:vertical;min-height:80px;}'
      + '.modal-field input:focus,.modal-field textarea:focus,.modal-field select:focus{'
      + 'outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,0.15);}'
      + '.modal-field-check{display:flex;align-items:center;gap:0.5rem;}'
      + '.modal-field-check input{width:auto;cursor:pointer;}'
      + '.modal-field-check label{margin:0;cursor:pointer;}'
      + '.modal-actions{display:flex;gap:0.75rem;justify-content:flex-end;margin-top:1.5rem;'
      + 'flex-wrap:wrap;}'
      + '.modal-btn{padding:0.6rem 1.4rem;border-radius:6px;border:none;font-weight:500;'
      + 'cursor:pointer;font-size:0.95rem;font-family:inherit;transition:filter 0.15s,background 0.15s;}'
      + '.modal-btn:hover{filter:brightness(0.93);}'
      + '.modal-btn:disabled{opacity:0.6;cursor:not-allowed;}'
      + '.modal-btn--primary{background:#3b82f6;color:#fff;}'
      + '.modal-btn--danger{background:#ef4444;color:#fff;}'
      + '.modal-btn--cancel{background:#f3f4f6;color:#374151;}'
      + '.modal-icon-row{font-size:2.5rem;text-align:center;margin-bottom:0.5rem;}'
      + '.modal-icon-row .success{color:#10b981;}'
      + '.modal-icon-row .error{color:#ef4444;}'
      + '.modal-icon-row .info{color:#3b82f6;}';
    document.head.appendChild(style);
  }

  function lockBody(lock) {
    if (lock) {
      document.body.dataset.modalLockCount = (parseInt(document.body.dataset.modalLockCount || '0', 10) + 1).toString();
      document.body.style.overflow = 'hidden';
    } else {
      const c = Math.max(0, parseInt(document.body.dataset.modalLockCount || '0', 10) - 1);
      document.body.dataset.modalLockCount = c.toString();
      if (c === 0) document.body.style.overflow = '';
    }
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/"/g, '&quot;')
      .replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  let _fieldIdCounter = 0;

  function renderField(field) {
    const id = 'mf_' + (++_fieldIdCounter);
    const labelHtml = '<label for="' + id + '">' + esc(field.label || field.name) + (field.required ? ' *' : '') + '</label>';
    const placeholder = field.placeholder ? ' placeholder="' + esc(field.placeholder) + '"' : '';
    const required = field.required ? ' required' : '';
    const value = field.value == null ? '' : String(field.value);

    if (field.type === 'textarea') {
      return '<div class="modal-field">' + labelHtml
        + '<textarea id="' + id + '" name="' + esc(field.name) + '"' + required + placeholder
        + ' rows="' + (field.rows || 3) + '">' + esc(value) + '</textarea></div>';
    }
    if (field.type === 'select') {
      const opts = (field.options || []).map(function (o) {
        const v = (typeof o === 'object') ? o.value : o;
        const t = (typeof o === 'object') ? o.label : o;
        return '<option value="' + esc(v) + '"' + (String(v) === value ? ' selected' : '') + '>' + esc(t) + '</option>';
      }).join('');
      return '<div class="modal-field">' + labelHtml
        + '<select id="' + id + '" name="' + esc(field.name) + '"' + required + '>' + opts + '</select></div>';
    }
    if (field.type === 'checkbox') {
      return '<div class="modal-field modal-field-check">'
        + '<input type="checkbox" id="' + id + '" name="' + esc(field.name) + '"' + (field.value ? ' checked' : '') + '>'
        + '<label for="' + id + '">' + esc(field.label || field.name) + '</label>'
        + '</div>';
    }
    const inputType = field.type || 'text';
    return '<div class="modal-field">' + labelHtml
      + '<input type="' + inputType + '" id="' + id + '" name="' + esc(field.name) + '"'
      + ' value="' + esc(value) + '"' + required + placeholder + '></div>';
  }

  function openBackdrop(cardHtml) {
    injectCss();
    lockBody(true);
    const bd = document.createElement('div');
    bd.className = 'modal-backdrop';
    bd.innerHTML = '<div class="modal-card" role="dialog" aria-modal="true">' + cardHtml + '</div>';
    document.body.appendChild(bd);
    requestAnimationFrame(function () { bd.classList.add('show'); });
    return bd;
  }

  function closeBackdrop(bd) {
    bd.classList.remove('show');
    lockBody(false);
    setTimeout(function () { bd.remove(); }, 150);
  }

  function bindCommonClose(bd, resolveValue, resolve) {
    function onKey(e) {
      if (e.key === 'Escape') { e.preventDefault(); cleanup(); resolve(resolveValue); }
    }
    function onBackdropClick(e) {
      if (e.target === bd) { cleanup(); resolve(resolveValue); }
    }
    function cleanup() {
      document.removeEventListener('keydown', onKey);
      bd.removeEventListener('click', onBackdropClick);
      closeBackdrop(bd);
    }
    document.addEventListener('keydown', onKey);
    bd.addEventListener('click', onBackdropClick);
    return cleanup;
  }

  // -------- showFormModal --------
  global.showFormModal = function (opts) {
    return new Promise(function (resolve) {
      const fields = opts.fields || [];
      const html = '<h3 class="modal-title">' + esc(opts.title || 'Form') + '</h3>'
        + (opts.message ? '<p class="modal-message">' + esc(opts.message) + '</p>' : '')
        + '<form class="modal-form" novalidate>'
        + fields.map(renderField).join('')
        + '<div class="modal-actions">'
        +   '<button type="button" class="modal-btn modal-btn--cancel" data-act="cancel">'
        +     esc(opts.cancelLabel || 'Batal') + '</button>'
        +   '<button type="submit" class="modal-btn modal-btn--primary">'
        +     esc(opts.submitLabel || 'Simpan') + '</button>'
        + '</div></form>';
      const bd = openBackdrop(html);
      const form = bd.querySelector('form.modal-form');

      const cleanup = bindCommonClose(bd, null, resolve);

      bd.querySelector('[data-act="cancel"]').addEventListener('click', function (e) {
        e.preventDefault();
        cleanup();
        resolve(null);
      });

      form.addEventListener('submit', function (e) {
        e.preventDefault();
        const data = {};
        for (let i = 0; i < fields.length; i++) {
          const f = fields[i];
          const el = form.elements[f.name];
          if (!el) continue;
          if (f.type === 'checkbox') {
            data[f.name] = el.checked;
          } else if (f.type === 'number') {
            data[f.name] = el.value === '' ? null : Number(el.value);
          } else {
            data[f.name] = (el.value || '').trim();
          }
        }
        cleanup();
        resolve(data);
      });

      // Focus first input
      const first = form.querySelector('input:not([type=checkbox]), textarea, select');
      if (first) setTimeout(function () { first.focus(); }, 50);
    });
  };

  // -------- showConfirmModal --------
  global.showConfirmModal = function (opts) {
    return new Promise(function (resolve) {
      const danger = !!opts.danger;
      const btnClass = danger ? 'modal-btn--danger' : 'modal-btn--primary';
      const confirmLabel = opts.confirmLabel || (danger ? 'Hapus' : 'OK');
      const cancelLabel = opts.cancelLabel || 'Batal';
      const html = '<h3 class="modal-title">' + esc(opts.title || 'Konfirmasi') + '</h3>'
        + '<p class="modal-message">' + esc(opts.message || '') + '</p>'
        + '<div class="modal-actions">'
        +   '<button type="button" class="modal-btn modal-btn--cancel" data-act="cancel">'
        +     esc(cancelLabel) + '</button>'
        +   '<button type="button" class="modal-btn ' + btnClass + '" data-act="confirm">'
        +     esc(confirmLabel) + '</button>'
        + '</div>';
      const bd = openBackdrop(html);
      const cleanup = bindCommonClose(bd, false, resolve);
      bd.querySelector('[data-act="confirm"]').addEventListener('click', function () { cleanup(); resolve(true); });
      bd.querySelector('[data-act="cancel"]').addEventListener('click', function () { cleanup(); resolve(false); });
      setTimeout(function () { bd.querySelector('[data-act="cancel"]').focus(); }, 50);
    });
  };

  // -------- showAlertModal --------
  global.showAlertModal = function (opts) {
    return new Promise(function (resolve) {
      const type = opts.type || 'info';
      const iconClass = type === 'success' ? 'fa-circle-check' : (type === 'error' ? 'fa-circle-xmark' : 'fa-circle-info');
      const defaultTitle = type === 'error' ? 'Error' : (type === 'success' ? 'Berhasil' : 'Info');
      const html = '<div class="modal-icon-row"><i class="fa-solid ' + iconClass + ' ' + type + '"></i></div>'
        + '<h3 class="modal-title" style="text-align:center;">' + esc(opts.title || defaultTitle) + '</h3>'
        + '<p class="modal-message" style="text-align:center;">' + esc(opts.message || '') + '</p>'
        + '<div class="modal-actions" style="justify-content:center;">'
        +   '<button type="button" class="modal-btn modal-btn--primary" data-act="ok">OK</button>'
        + '</div>';
      const bd = openBackdrop(html);
      const cleanup = bindCommonClose(bd, undefined, resolve);
      bd.querySelector('[data-act="ok"]').addEventListener('click', function () { cleanup(); resolve(); });
      setTimeout(function () { bd.querySelector('[data-act="ok"]').focus(); }, 50);
    });
  };
})(window);
