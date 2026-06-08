// notes-read.js — halaman NotesRead: Kanban 3 kolom Belum Dibaca / Sedang Dipelajari / Selesai.
// Pakai modal.js untuk add/delete (bukan prompt/confirm).
let boardData = { unread: [], learning: [], done: [] };
let activeCourseFilter = '';
let activeSearch = '';

(async function () {
  const user = await requireAuth();
  if (!user) return;

  const nameSpan = document.querySelector('.user-profile span');
  if (nameSpan) nameSpan.textContent = user.name || user.username;
  const logout = document.querySelector('.btn--logout');
  if (logout) {
    logout.addEventListener('click', async (e) => {
      e.preventDefault();
      await api.delete('../api/auth.php', { autoRedirect: false });
      window.location.href = 'LandingPage.html';
    });
  }

  const searchInput = document.querySelector('.search-box input');
  if (searchInput) {
    searchInput.addEventListener('input', () => {
      activeSearch = searchInput.value.toLowerCase().trim();
      renderBoard();
    });
  }

  // Convert dropdown ke select asli
  const courseDropdown = document.querySelector('.dropdown');
  if (courseDropdown) {
    courseDropdown.outerHTML = '<select id="courseFilter" style="padding:0.5rem 0.9rem;border-radius:6px;border:1px solid #d1d5db;font:inherit;background:#fff;"><option value="">Semua Mata Kuliah</option></select>';
    document.getElementById('courseFilter').addEventListener('change', (e) => {
      activeCourseFilter = e.target.value;
      renderBoard();
    });
  }

  // Add reading button — replace anchor wrapper
  const addBtn = document.querySelector('.btn-add');
  if (addBtn) {
    addBtn.outerHTML = '<button class="btn-add" id="btnAddReading" style="padding:0.6rem 1.2rem;background:#3b82f6;color:#fff;border:none;border-radius:6px;cursor:pointer;font-weight:500;"><i class="fa-solid fa-plus"></i> Add reading notes</button>';
    document.getElementById('btnAddReading').addEventListener('click', addReadingFlow);
  }

  document.querySelectorAll('.column-content').forEach(col => {
    col.addEventListener('click', handleCardClick);
    col.addEventListener('change', handleCardChange);
  });

  // AI banner "Rekomendasi AI" — wire ke /api/ai.php (Tahap 5).
  const aiBtn = document.querySelector('.btn-ai-analyze');
  if (aiBtn) {
    aiBtn.addEventListener('click', analyzeWithAI);
  }

  // Tombol "Atur" target mingguan — single source of truth via localStorage.weeklyTarget.
  // Dashboard widget "Progress Belajar" juga baca key yang sama, jadi otomatis sync.
  const editTargetLink = document.getElementById('editTargetLink');
  if (editTargetLink) {
    editTargetLink.addEventListener('click', async (e) => {
      e.preventDefault();
      const current = parseInt(localStorage.getItem('weeklyTarget') || '20', 10) || 20;
      const data = await showFormModal({
        title: 'Atur Target Mingguan',
        message: 'Berapa catatan yang ingin kamu selesaikan minggu ini? Tersimpan di browser (localStorage). Dipakai juga di widget Progress Belajar di Dashboard.',
        fields: [
          { name: 'target', label: 'Target (1–100 catatan)', type: 'number', required: true, value: current },
        ],
        submitLabel: 'Simpan',
      });
      if (!data) return;
      let v = parseInt(data.target, 10);
      if (isNaN(v) || v < 1) v = 1;
      if (v > 100) v = 100;
      localStorage.setItem('weeklyTarget', String(v));
      await load();
    });
  }

  await load();
})();

async function analyzeWithAI() {
  const aiBtn = document.querySelector('.btn-ai-analyze');
  const bannerText = document.querySelector('.ai-banner .ai-text p');
  if (!aiBtn || !bannerText) return;

  const titles = []
    .concat(boardData.learning.map(n => n.title || n.course_name))
    .concat(boardData.unread.map(n => n.title || n.course_name))
    .slice(0, 8);

  if (!titles.length) {
    await showAlertModal({
      type: 'info',
      title: 'Reading Board Kosong',
      message: 'Tambah catatan dulu lewat tombol "+ Add reading notes" supaya AI bisa kasih rekomendasi.',
    });
    return;
  }

  aiBtn.disabled = true;
  const oldLabel = aiBtn.textContent;
  aiBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menganalisis…';

  const prompt = 'Berdasarkan reading board user, rekomendasikan 1 catatan prioritas '
              + 'untuk minggu ini. Jawab singkat dalam 1 kalimat (maks 200 karakter). '
              + 'Catatan yang ada di board: ' + titles.join('; ') + '.';

  const { data, status } = await api.post('../api/ai.php', { prompt, mode: 'auto' });

  aiBtn.disabled = false;
  aiBtn.textContent = oldLabel || 'Analisis dengan AI';

  if (data && data.success) {
    const text = (data.data.response || '').trim();
    bannerText.textContent = text.length > 240 ? text.substring(0, 240) + '…' : text;
  } else if (status === 429) {
    await showAlertModal({ type: 'error', title: 'Rate Limit', message: (data && data.message) || '' });
  } else {
    await showAlertModal({ type: 'error', message: (data && data.message) || 'Gagal panggil AI.' });
  }
}

async function load() {
  const { data } = await api.get('../api/read-notes.php?view=reading_board');
  if (!data || !data.success) {
    await showAlertModal({ type: 'error', message: (data && data.message) || 'Gagal memuat reading board.' });
    return;
  }
  boardData = data.data;
  const meta = data.meta || {};
  setText('statTotal', meta.total != null ? meta.total : 0);
  setText('statDone', meta.done_count != null ? meta.done_count : 0);
  // Target mingguan: source of truth = localStorage.weeklyTarget (dipakai juga di Dashboard).
  // meta.week_target tetap di-return backend tapi tidak ditampilkan di sini (siapa tahu dipakai fitur lain).
  const target = parseInt(localStorage.getItem('weeklyTarget') || '20', 10) || 20;
  setText('statTarget', target);

  populateCourseFilter();
  renderBoard();
}

function populateCourseFilter() {
  const sel = document.getElementById('courseFilter');
  if (!sel) return;
  const set = new Set();
  ['unread','learning','done'].forEach(s => {
    boardData[s].forEach(n => { if (n.course_name) set.add(n.course_name); });
  });
  const opts = ['<option value="">Semua Mata Kuliah</option>']
    .concat([...set].sort().map(c => '<option value="' + escapeHtml(c) + '">' + escapeHtml(c) + '</option>'));
  sel.innerHTML = opts.join('');
}

function renderBoard() {
  ['unread','learning','done'].forEach(status => {
    const col = document.querySelector('[data-column="' + status + '"]');
    if (!col) return;
    const content = col.querySelector('.column-content');
    const badge = col.querySelector('.count-badge');

    const filtered = boardData[status].filter(n => {
      if (activeCourseFilter && n.course_name !== activeCourseFilter) return false;
      if (activeSearch) {
        const hay = (n.title || '') + ' ' + (n.course_name || '') + ' ' + (n.lecturer_name || '');
        if (!hay.toLowerCase().includes(activeSearch)) return false;
      }
      return true;
    });

    if (badge) badge.textContent = filtered.length;

    if (!filtered.length) {
      content.innerHTML = '<p style="color:#9ca3af;padding:0.5rem;font-style:italic;font-size:0.85rem;">Kosong</p>';
      return;
    }
    content.innerHTML = filtered.map(n => renderCard(n, status)).join('');
  });
}

function renderCard(n, status) {
  const tag = (n.tags && n.tags[0]) || n.category || 'Catatan';
  const tagColor = status === 'unread' ? 'blue' : (status === 'learning' ? 'purple' : 'green');
  const rating = (n.avg_rating || 0).toFixed(1);
  const progress = n.reading.progress;
  const readingId = n.reading.id;
  const progressFillColor = status === 'learning' ? 'orange' : (status === 'done' ? 'green' : '');

  const progressBlock = status === 'done'
    ? '<div class="card-status-done"><span style="color:#10b981;"><i class="fa-solid fa-circle-check"></i> Selesai dipelajari</span></div>'
    : '<div class="card-progress' + (progress === 0 ? ' empty' : '') + '">'
      +   '<div class="progress-info"><span>Progress: ' + progress + '%</span></div>'
      +   '<div class="progress-bar-bg"><div class="progress-bar-fill ' + progressFillColor + '" style="width:' + progress + '%"></div></div>'
      + '</div>';

  const statusOptions = [
    { val: 'unread', label: 'Belum Dibaca' },
    { val: 'learning', label: 'Sedang Dipelajari' },
    { val: 'done', label: 'Selesai' },
  ].map(o => '<option value="' + o.val + '"' + (o.val === status ? ' selected' : '') + '>' + o.label + '</option>').join('');

  const progressInput = status === 'learning'
    ? '<input type="number" min="0" max="100" value="' + progress + '" data-action="set-progress" data-reading-id="' + readingId + '" '
      + 'style="width:60px;padding:0.2rem;border:1px solid #d1d5db;border-radius:4px;font-size:0.8rem;" title="Progress %">'
    : '';

  return ''
    + '<div class="note-card task-card" data-reading-id="' + readingId + '" data-note-id="' + n.id + '">'
    +   '<div class="card-top">'
    +     '<h4>' + escapeHtml(n.title || n.course_name) + '</h4>'
    +     '<p class="author">' + escapeHtml(n.lecturer_name || '-') + '</p>'
    +   '</div>'
    +   '<div class="card-tags">'
    +     '<span class="tag ' + tagColor + '">' + escapeHtml(tag) + '</span>'
    +     '<span class="rating"><i class="fa-solid fa-star" style="color:#f59e0b;"></i> ' + rating + '</span>'
    +   '</div>'
    +   progressBlock
    +   '<div class="card-actions" style="display:flex;gap:0.4rem;align-items:center;margin-top:0.5rem;flex-wrap:wrap;">'
    +     '<a href="DetailCatatan.html?id=' + n.id + '" class="btn-detail" style="padding:0.3rem 0.6rem;background:#3b82f6;color:#fff;border-radius:4px;text-decoration:none;font-size:0.8rem;">Detail</a>'
    +     '<select data-action="change-status" data-reading-id="' + readingId + '" style="padding:0.3rem;border-radius:4px;border:1px solid #d1d5db;font-size:0.8rem;">' + statusOptions + '</select>'
    +     progressInput
    +     '<button class="btn-delete" data-action="delete-reading" data-reading-id="' + readingId + '" '
    +       'style="padding:0.3rem 0.6rem;background:#ef4444;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:0.8rem;" title="Hapus">'
    +       '<i class="fa-solid fa-trash"></i></button>'
    +   '</div>'
    + '</div>';
}

async function handleCardClick(e) {
  const del = e.target.closest('[data-action="delete-reading"]');
  if (del) {
    e.preventDefault();
    const ok = await showConfirmModal({
      title: 'Hapus dari Reading Board?',
      message: 'Catatan tetap ada di sistem, hanya dikeluarkan dari board.',
      confirmLabel: 'Hapus',
      danger: true,
    });
    if (!ok) return;
    const { data } = await api.delete('../api/read-notes.php?action=delete_reading&id=' + del.dataset.readingId);
    if (data && data.success) await load();
    else await showAlertModal({ type: 'error', message: (data && data.message) || 'Gagal hapus.' });
  }
}

async function handleCardChange(e) {
  const sel = e.target.closest('[data-action="change-status"]');
  if (sel) {
    const { data } = await api.post('../api/read-notes.php?action=update_reading&id=' + sel.dataset.readingId, { status: sel.value });
    if (data && data.success) await load();
    else await showAlertModal({ type: 'error', message: (data && data.message) || 'Gagal pindah kolom.' });
    return;
  }
  const prog = e.target.closest('[data-action="set-progress"]');
  if (prog) {
    let v = parseInt(prog.value, 10);
    if (isNaN(v)) v = 0;
    const { data } = await api.post('../api/read-notes.php?action=update_reading&id=' + prog.dataset.readingId, { progress: v });
    if (data && data.success) await load();
    else await showAlertModal({ type: 'error', message: (data && data.message) || 'Gagal update progress.' });
  }
}

async function addReadingFlow() {
  const res = await api.get('../api/notes.php?scope=mine&limit=100');
  if (!res.data || !res.data.success) {
    await showAlertModal({ type: 'error', message: 'Gagal memuat daftar catatan.' });
    return;
  }
  const notes = res.data.data;
  if (!notes.length) {
    await showAlertModal({ type: 'info', message: 'Belum ada catatan. Upload dulu di Upload Notes.' });
    return;
  }
  const options = notes.map(n => ({
    value: String(n.id),
    label: (n.course_name || '—') + ' — ' + (n.title || n.file_name),
  }));
  const data = await showFormModal({
    title: 'Tambah Catatan ke Reading Board',
    fields: [
      { name: 'note_id', label: 'Pilih Catatan', type: 'select', required: true, options },
      { name: 'status', label: 'Status Awal', type: 'select', value: 'unread',
        options: [
          { value: 'unread', label: 'Belum Dibaca' },
          { value: 'learning', label: 'Sedang Dipelajari' },
          { value: 'done', label: 'Selesai' },
        ],
      },
    ],
    submitLabel: 'Tambahkan ke Board',
  });
  if (!data) return;
  const { data: resp } = await api.post('../api/read-notes.php?action=add_to_reading', {
    note_id: parseInt(data.note_id, 10),
    status: data.status,
  });
  if (resp && resp.success) await load();
  else await showAlertModal({ type: 'error', message: (resp && resp.message) || 'Gagal menambah.' });
}

function setText(id, v) {
  const el = document.getElementById(id);
  if (el) el.textContent = String(v);
}
