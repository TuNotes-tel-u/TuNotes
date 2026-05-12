// detail-my-notes.js — tabel dokumen dalam 1 mata kuliah (atau semua, kalau course_id kosong).
(async function () {
  const user = await requireAuth();
  if (!user) return;

  const params = new URLSearchParams(window.location.search);
  const courseId = params.get('course_id');

  const tbody = document.getElementById('notesTableBody');
  const titleEl = document.getElementById('courseTitle');
  const showingEl = document.getElementById('showingText');

  let url, label;
  if (courseId) {
    url = '../api/notes.php?course_id=' + encodeURIComponent(courseId) + '&limit=100';
    label = 'Course #' + escapeHtml(courseId);
  } else {
    // Fallback: semua catatan user (course feature menyusul di Tahap 4).
    url = '../api/notes.php?scope=mine&limit=100';
    label = 'All My Documents';
  }
  if (titleEl) titleEl.textContent = label;
  tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:2rem;color:#666;">Memuat…</td></tr>';

  const { data } = await api.get(url);
  if (!data || !data.success) {
    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:2rem;color:#b91c1c;">Gagal memuat data.</td></tr>';
    return;
  }
  if (!data.data.length) {
    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:2rem;color:#666;">Belum ada dokumen.</td></tr>';
    if (showingEl) showingEl.textContent = 'Showing 0 results';
    return;
  }
  if (showingEl) showingEl.textContent = 'Showing 1 to ' + data.data.length + ' of ' + data.meta.total + ' results';

  const fileIconClass = (ext) => {
    ext = (ext || '').toLowerCase();
    if (ext === 'pdf') return 'icon-pdf';
    if (ext === 'doc' || ext === 'docx') return 'icon-word';
    if (ext === 'ppt' || ext === 'pptx') return 'icon-ppt';
    return 'icon-pdf';
  };

  tbody.innerHTML = data.data.map(n => ''
    + '<tr data-id="' + n.id + '">'
    +   '<td>'
    +     '<div class="doc-info">'
    +       '<div class="file-icon ' + fileIconClass(n.file_type) + '">'
    +         '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>'
    +       '</div>'
    +       '<div class="doc-text">'
    +         '<div class="doc-name">' + escapeHtml(n.title || n.course_name) + '</div>'
    +         '<div class="doc-file">' + escapeHtml(n.file_name) + '</div>'
    +       '</div>'
    +     '</div>'
    +   '</td>'
    +   '<td><div class="lecturer-info"><span class="lecturer-name">' + escapeHtml(n.lecturer_name || '-') + '</span></div></td>'
    +   '<td class="text-date">' + formatDate(n.created_at) + '</td>'
    +   '<td>'
    +     '<div class="action-buttons">'
    +       '<a href="UpdateNotes.html?id=' + n.id + '" class="btn-action btn-update">Update</a>'
    +       '<a href="#" class="btn-action btn-delete" data-action="delete" data-id="' + n.id + '">Delete</a>'
    +       '<a href="' + escapeHtml(n.file_url) + '" class="btn-action btn-download" download="' + escapeHtml(n.file_name) + '">Unduh</a>'
    +     '</div>'
    +   '</td>'
    + '</tr>'
  ).join('');

  // Delegasi event delete
  tbody.addEventListener('click', async function (e) {
    const btn = e.target.closest('[data-action="delete"]');
    if (!btn) return;
    e.preventDefault();
    if (!confirm('Yakin hapus catatan ini? Aksi tidak bisa dibatalkan.')) return;
    const id = btn.dataset.id;
    const { data } = await api.delete('../api/notes.php?id=' + id);
    if (data && data.success) {
      const row = btn.closest('tr');
      if (row) row.remove();
    } else {
      alert(data && data.message ? data.message : 'Gagal menghapus.');
    }
  });
})();
