// study-list.js — halaman StudyList: list semester + course user, plus aksi tambah.
// Pakai modal.js untuk semua input (bukan prompt/confirm browser).
(async function () {
  const user = await requireAuth();
  if (!user) return;

  const logout = document.querySelector('.nav-logout');
  if (logout) {
    logout.addEventListener('click', async (e) => {
      e.preventDefault();
      await api.delete('../api/auth.php', { autoRedirect: false });
      window.location.href = 'LandingPage.html';
    });
  }

  // -- Tambah Semester --
  const addSemBtn = document.getElementById('btnAddSemester');
  if (addSemBtn) {
    addSemBtn.addEventListener('click', async () => {
      const data = await showFormModal({
        title: 'Tambah Semester Baru',
        fields: [
          { name: 'name', label: 'Nama Semester', type: 'text', required: true,
            placeholder: 'contoh: 2025 Ganjil atau 2025 Fall' },
          { name: 'is_active', label: 'Jadikan semester aktif sekarang', type: 'checkbox', value: false },
        ],
        submitLabel: 'Tambah Semester',
      });
      if (!data) return;
      const { data: resp } = await api.post('../api/study.php?action=add_semester', {
        name: data.name,
        is_active: data.is_active,
      });
      if (resp && resp.success) {
        await load();
      } else {
        await showAlertModal({ type: 'error', title: 'Gagal', message: (resp && resp.message) || 'Tidak bisa menyimpan semester.' });
      }
    });
  }

  // -- Event delegation di container semester --
  const container = document.getElementById('semestersContainer');
  if (container) {
    container.addEventListener('click', async (e) => {
      const addCourseCard = e.target.closest('[data-action="add-course"]');
      if (addCourseCard) {
        e.preventDefault();
        const semesterId = addCourseCard.dataset.semesterId;
        const data = await showFormModal({
          title: 'Tambah Mata Kuliah',
          fields: [
            { name: 'name', label: 'Nama Mata Kuliah', type: 'text', required: true,
              placeholder: 'contoh: Algoritma & Struktur Data' },
            { name: 'code', label: 'Kode (opsional)', type: 'text', placeholder: 'CS302' },
            { name: 'instructor', label: 'Dosen Pengajar (opsional)', type: 'text' },
          ],
          submitLabel: 'Tambah',
        });
        if (!data) return;
        const { data: resp } = await api.post('../api/study.php?action=add_course', {
          semester_id: semesterId ? parseInt(semesterId, 10) : null,
          name: data.name,
          code: data.code,
          instructor: data.instructor,
        });
        if (resp && resp.success) await load();
        else await showAlertModal({ type: 'error', message: (resp && resp.message) || 'Gagal menambah mata kuliah.' });
        return;
      }

      const delSemBtn = e.target.closest('[data-action="delete-semester"]');
      if (delSemBtn) {
        e.preventDefault();
        const id = delSemBtn.dataset.semesterId;
        const ok = await showConfirmModal({
          title: 'Hapus Semester?',
          message: 'Semua mata kuliah & homework di dalam semester ini ikut terhapus. Catatan tetap ada (course-link di-reset).',
          confirmLabel: 'Hapus',
          danger: true,
        });
        if (!ok) return;
        const { data } = await api.delete('../api/study.php?action=delete_semester&id=' + id);
        if (data && data.success) await load();
        else await showAlertModal({ type: 'error', message: (data && data.message) || 'Gagal hapus semester.' });
      }
    });
  }

  await load();

  async function load() {
    if (!container) return;
    container.innerHTML = '<p style="color:#666;padding:1rem;">Memuat…</p>';

    const { data } = await api.get('../api/study.php?view=study_list');
    if (!data || !data.success) {
      container.innerHTML = '<p style="color:#b91c1c;padding:1rem;">Gagal memuat data.</p>';
      return;
    }

    const semesters = data.data;
    const meta = data.meta || {};
    setText('statTotalCourses', meta.total_courses != null ? meta.total_courses : 0);
    setText('statTotalNotes', meta.total_notes != null ? meta.total_notes : 0);
    setText('statActiveSemesters', meta.active_semesters != null ? meta.active_semesters : 0);

    if (!semesters.length) {
      container.innerHTML = '<p style="color:#666;padding:1rem;">Belum ada semester. Klik <strong>Tambah Semester Baru</strong> di atas untuk mulai.</p>';
      return;
    }
    container.innerHTML = semesters.map(renderSemesterSection).join('');
  }

  function renderSemesterSection(s) {
    const courseCards = (s.courses || []).map(renderCourseCard).join('');
    const addCard = s.id !== null
      ? '<div class="course-card course-card--add" data-action="add-course" data-semester-id="' + s.id + '" style="cursor:pointer;">'
        +   '<div class="course-add-content">'
        +     '<div class="add-icon"><i class="fa-solid fa-plus"></i></div>'
        +     '<h3 class="course-title course-title--muted">Tambah Mata Kuliah</h3>'
        +     '<p class="course-notes-count course-notes-count--muted">Buat mata kuliah baru</p>'
        +   '</div>'
        + '</div>'
      : '';
    const activeBadge = s.is_active
      ? ' <span style="color:#10b981;font-size:0.75rem;font-weight:500;">● Aktif</span>'
      : '';
    const deleteBtn = s.id !== null
      ? '<button data-action="delete-semester" data-semester-id="' + s.id + '" '
        + 'style="background:none;border:none;color:#ef4444;cursor:pointer;font-size:0.85rem;margin-left:0.5rem;">'
        + '<i class="fa-solid fa-trash"></i> Hapus</button>'
      : '';
    return ''
      + '<section class="semester-section">'
      +   '<div class="semester-header">'
      +     '<h2>' + escapeHtml(s.name) + activeBadge + '</h2>'
      +     '<span class="semester-count">' + (s.courses || []).length + ' mata kuliah' + deleteBtn + '</span>'
      +   '</div>'
      +   '<div class="course-grid">' + courseCards + addCard + '</div>'
      + '</section>';
  }

  function renderCourseCard(c) {
    const colors = ['blue','purple','green','orange','red-light','blue-dark'];
    const color = colors[c.id % colors.length];
    const codeText = c.code ? escapeHtml(c.code) + ' • ' : '';
    return ''
      + '<div class="course-card" data-course-id="' + c.id + '">'
      +   '<div class="course-icon course-icon--' + color + '">'
      +     '<i class="fa-solid fa-book"></i>'
      +   '</div>'
      +   '<h3 class="course-title">' + escapeHtml(c.name) + '</h3>'
      +   '<p class="course-notes-count">' + codeText + c.notes_count + ' catatan tersedia</p>'
      +   '<a href="DetailList.html?course_id=' + c.id + '" class="btn btn--outline">Lihat Catatan</a>'
      + '</div>';
  }

  function setText(id, v) {
    const el = document.getElementById(id);
    if (el) el.textContent = String(v);
  }
})();
