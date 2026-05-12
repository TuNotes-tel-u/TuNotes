// notes-ai.js — halaman Notes AI: form submit, quick actions, riwayat, edit/save/share/delete.
// Pakai modal.js (jangan native alert/confirm) + Font Awesome icons.

const QUICK_TEMPLATES = {
  agenda:   "Buatkan agenda belajar mingguan untuk semester ini. Tampilkan tabel hari × jam dengan saran mata kuliah dan topik. Format markdown.",
  analyze:  "Bantu saya menganalisis dokumen akademik. Sebutkan langkah-langkah: identifikasi tesis utama, argumen pendukung, dan kesimpulan. Berikan tips praktis.",
  tracker:  "Buat sistem pelacak tugas akademik sederhana. Sebutkan kolom-kolom relevan (judul, deadline, prioritas, status, mata kuliah) plus tips manajemen prioritas.",
  whatsnew: "Apa fitur baru yang berguna di TuNotes AI? Jelaskan singkat 3-5 fitur utama yang membantu mahasiswa belajar lebih efektif."
};

(async function () {
  const user = await requireAuth();
  if (!user) return;

  // Header user
  const nameSpan = document.querySelector('.user-profile span');
  if (nameSpan) nameSpan.textContent = user.name || user.username;

  // Logout
  const logout = document.querySelector('.btn_logout');
  if (logout) {
    logout.addEventListener('click', async (e) => {
      e.preventDefault();
      await api.delete('../api/auth.php', { autoRedirect: false });
      window.location.href = 'LandingPage.html';
    });
  }

  const form        = document.getElementById('aiForm');
  const promptEl    = document.getElementById('aiPrompt');
  const sendBtn     = document.getElementById('aiSendBtn');
  const allowEditEl = document.getElementById('allowEdit');
  const resultsList = document.getElementById('resultsList');
  const emptyState  = document.getElementById('emptyState');

  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      await submitPrompt(promptEl.value);
    });
  }

  // Quick action cards: klik → auto-fill + submit.
  document.querySelectorAll('[data-template]').forEach(card => {
    card.addEventListener('click', () => {
      const key = card.dataset.template;
      const tpl = QUICK_TEMPLATES[key];
      if (!tpl) return;
      if (promptEl) promptEl.value = tpl;
      submitPrompt(tpl);
    });
  });

  // Delegasi event di results list (edit/save/share/delete).
  if (resultsList) {
    resultsList.addEventListener('click', handleResultAction);
  }

  await loadHistory();

  // ---------- Submit prompt ----------
  async function submitPrompt(rawPrompt) {
    const prompt = (rawPrompt || '').trim();
    if (prompt.length < 3) {
      await showAlertModal({ type: 'error', title: 'Prompt Terlalu Pendek', message: 'Minimal 3 karakter.' });
      return;
    }
    if (prompt.length > 4000) {
      await showAlertModal({ type: 'error', title: 'Prompt Terlalu Panjang', message: 'Maksimal 4000 karakter.' });
      return;
    }

    const modeRadio = document.querySelector('input[name="mode"]:checked');
    const mode = modeRadio ? modeRadio.value : 'auto';
    const allow_edit = !!(allowEditEl && allowEditEl.checked);

    setLoading(true);
    const { data, status } = await api.post('../api/ai.php', {
      prompt, mode, allow_edit,
    });
    setLoading(false);

    if (data && data.success) {
      promptEl.value = '';
      await loadHistory();
      // Scroll ke results
      setTimeout(() => {
        const first = resultsList.querySelector('.result-card');
        if (first) first.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }, 50);
    } else if (status === 429) {
      await showAlertModal({
        type: 'error',
        title: 'Rate Limit Tercapai',
        message: (data && data.message) || 'Tunggu beberapa menit sebelum coba lagi.',
      });
    } else {
      await showAlertModal({
        type: 'error',
        title: 'Gagal',
        message: (data && data.message) || 'Tidak bisa kirim ke AI.',
      });
    }
  }

  function setLoading(loading) {
    if (!sendBtn) return;
    sendBtn.disabled = loading;
    sendBtn.innerHTML = loading
      ? '<i class="fa-solid fa-spinner fa-spin"></i>'
      : '<i class="fa-solid fa-paper-plane"></i>';
  }

  // ---------- Load history ----------
  async function loadHistory() {
    if (!resultsList) return;
    const { data } = await api.get('../api/ai.php?limit=20');
    if (!data || !data.success) {
      resultsList.innerHTML = '<p style="color:#b91c1c;padding:1rem;">Gagal memuat riwayat.</p>';
      if (emptyState) emptyState.style.display = 'none';
      return;
    }
    const items = data.data || [];
    if (!items.length) {
      resultsList.innerHTML = '';
      if (emptyState) emptyState.style.display = '';
      return;
    }
    if (emptyState) emptyState.style.display = 'none';
    resultsList.innerHTML = items.map(renderEntry).join('');
  }

  function renderEntry(e) {
    const ago     = formatTimeAgo(e.created_at);
    const title   = e.prompt.length > 70 ? e.prompt.substring(0, 70) + '…' : e.prompt;
    const titleEsc    = escapeHtml(title);
    const responseEsc = escapeHtml(e.response).replace(/\n/g, '<br>');
    const isMock  = e.provider === 'mock';

    const editBtn = e.allow_edit
      ? '<button class="btn-text" data-action="edit" data-id="' + e.id + '" '
        + 'style="background:none;border:none;cursor:pointer;color:#3b82f6;font-size:0.85rem;">'
        + '<i class="fa-solid fa-pen"></i> Edit</button>'
      : '';

    return ''
      + '<div class="result-card" data-id="' + e.id + '" '
      +   'style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:1.25rem;margin-bottom:1rem;">'
      +   '<div class="result-header" style="display:flex;align-items:flex-start;gap:0.75rem;margin-bottom:0.75rem;flex-wrap:wrap;">'
      +     '<div style="background:#ede9fe;color:#7c3aed;width:40px;height:40px;border-radius:8px;'
      +       'display:flex;align-items:center;justify-content:center;flex-shrink:0;">'
      +       '<i class="fa-solid fa-robot"></i></div>'
      +     '<div style="flex:1;min-width:0;">'
      +       '<h4 style="margin:0;font-size:0.95rem;font-weight:600;color:#111827;'
      +         'white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + titleEsc + '</h4>'
      +       '<span style="font-size:0.8rem;color:#6b7280;">'
      +         ago + ' · mode: ' + escapeHtml(e.mode)
      +         (isMock ? ' · <em style="color:#f59e0b;">MOCK</em>' : '')
      +       '</span>'
      +     '</div>'
      +     '<div style="display:flex;gap:0.4rem;flex-shrink:0;flex-wrap:wrap;">'
      +       editBtn
      +       '<button class="btn-text" data-action="save" data-id="' + e.id + '" '
      +         'style="background:none;border:none;cursor:pointer;color:#10b981;font-size:0.85rem;">'
      +         '<i class="fa-solid fa-download"></i> Simpan</button>'
      +       '<button class="btn-text" data-action="share" data-id="' + e.id + '" '
      +         'style="background:none;border:none;cursor:pointer;color:#6b7280;font-size:0.85rem;">'
      +         '<i class="fa-solid fa-share"></i> Bagikan</button>'
      +       '<button class="btn-text" data-action="delete" data-id="' + e.id + '" '
      +         'style="background:none;border:none;cursor:pointer;color:#ef4444;font-size:0.85rem;" title="Hapus">'
      +         '<i class="fa-solid fa-trash"></i></button>'
      +     '</div>'
      +   '</div>'
      +   '<div class="result-body" style="line-height:1.6;color:#374151;font-size:0.95rem;">'
      +     responseEsc
      +   '</div>'
      + '</div>';
  }

  function formatTimeAgo(dbDate) {
    if (!dbDate) return '';
    const d = new Date(String(dbDate).replace(' ', 'T'));
    if (isNaN(d.getTime())) return '';
    const diff = Date.now() - d.getTime();
    if (diff < 0) return 'baru saja';
    const sec = Math.floor(diff / 1000);
    if (sec < 60) return 'beberapa detik lalu';
    const min = Math.floor(sec / 60);
    if (min < 60) return min + ' menit lalu';
    const hr = Math.floor(min / 60);
    if (hr < 24) return hr + ' jam lalu';
    const day = Math.floor(hr / 24);
    if (day < 7) return day + ' hari lalu';
    return formatDate(dbDate);
  }

  // ---------- Result actions ----------
  async function handleResultAction(e) {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    e.preventDefault();
    const action = btn.dataset.action;
    const id     = btn.dataset.id;

    if (action === 'delete') {
      const ok = await showConfirmModal({
        title: 'Hapus Entry AI?',
        message: 'Entry ini akan dihapus permanen dari riwayat AI kamu. Aksi tidak bisa dibatalkan.',
        confirmLabel: 'Hapus',
        danger: true,
      });
      if (!ok) return;
      const { data } = await api.delete('../api/ai.php?id=' + id);
      if (data && data.success) await loadHistory();
      else await showAlertModal({ type: 'error', message: (data && data.message) || 'Gagal hapus.' });
      return;
    }

    if (action === 'save') {
      const entry = await fetchEntry(id);
      if (entry) downloadMd(entry);
      return;
    }

    if (action === 'share') {
      const entry = await fetchEntry(id);
      if (!entry) return;
      try {
        await navigator.clipboard.writeText(entry.response);
        await showAlertModal({
          type: 'success',
          title: 'Tersalin ke Clipboard',
          message: 'Hasil AI sudah siap di-paste di mana pun.',
        });
      } catch (err) {
        await showAlertModal({
          type: 'error',
          title: 'Clipboard Ditolak',
          message: 'Browser kamu menolak akses clipboard. Coba copy manual lewat menu.',
        });
      }
      return;
    }

    if (action === 'edit') {
      const entry = await fetchEntry(id);
      if (!entry) return;
      const data = await showFormModal({
        title: 'Edit Hasil AI',
        message: 'Edit teks di bawah. Klik "Salin ke Clipboard" untuk copy versi yang sudah kamu edit. Versi original di DB tidak berubah.',
        fields: [
          { name: 'response', label: 'Hasil AI (editable)', type: 'textarea',
            value: entry.response, required: true, rows: 12 },
        ],
        submitLabel: 'Salin ke Clipboard',
        cancelLabel: 'Tutup',
      });
      if (!data) return;
      try {
        await navigator.clipboard.writeText(data.response);
        await showAlertModal({
          type: 'success',
          title: 'Tersalin',
          message: 'Versi hasil edit sudah ada di clipboard.',
        });
      } catch (err) {
        await showAlertModal({ type: 'error', message: 'Browser menolak akses clipboard.' });
      }
    }
  }

  async function fetchEntry(id) {
    const r = await api.get('../api/ai.php?id=' + id);
    if (!r.data || !r.data.success) {
      await showAlertModal({ type: 'error', message: (r.data && r.data.message) || 'Gagal ambil entry.' });
      return null;
    }
    return r.data.data;
  }

  function downloadMd(entry) {
    const md = '# Hasil AI — TuNotes\n\n'
      + '**Prompt:** ' + entry.prompt + '\n\n'
      + '**Mode:** ' + entry.mode + '\n'
      + '**Tanggal:** ' + entry.created_at + '\n'
      + (entry.provider ? '**Provider:** ' + entry.provider + '\n' : '')
      + '\n---\n\n'
      + entry.response + '\n';
    const blob = new Blob([md], { type: 'text/markdown;charset=utf-8' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = 'tunotes-ai-' + entry.id + '.md';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
  }
})();
