// detail-list.js — halaman DetailList: header course + tab Notes + tab Homework.
// Pakai modal.js untuk semua aksi input/konfirmasi.
let courseData = null;
let homeworkList = [];

(async function () {
  const user = await requireAuth();
  if (!user) return;

  const params = new URLSearchParams(window.location.search);
  const courseId = parseInt(params.get('course_id'), 10);
  if (!courseId) {
    await showAlertModal({ type: 'error', message: 'course_id tidak ada di URL.' });
    window.location.href = 'StudyList.html';
    return;
  }

  // Tab switch
  document.querySelectorAll('.tab-button').forEach((btn, idx) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.tab-button').forEach(b => b.classList.remove('tab-button--active'));
      btn.classList.add('tab-button--active');
      const which = btn.dataset.tab || (idx === 0 ? 'notes' : 'homework');
      const notesSec = document.getElementById('notesSection');
      const hwSec = document.getElementById('homeworkSection');
      if (notesSec) notesSec.style.display = which === 'notes' ? '' : 'none';
      if (hwSec) hwSec.style.display = which === 'homework' ? '' : 'none';
    });
  });

  // Add Note → redirect
  const addNoteBtn = document.getElementById('btnAddNote');
  if (addNoteBtn) {
    addNoteBtn.addEventListener('click', () => {
      window.location.href = 'UploadNotes.html?course_id=' + courseId;
    });
  }

  // Edit Course Info (SEMUA field di 1 modal)
  const editBtn = document.getElementById('btnEditCourse');
  if (editBtn) {
    editBtn.addEventListener('click', async () => {
      if (!courseData) return;
      const data = await showFormModal({
        title: 'Edit Informasi Mata Kuliah',
        fields: [
          { name: 'name', label: 'Nama Mata Kuliah', type: 'text', required: true, value: courseData.name || '' },
          { name: 'code', label: 'Kode', type: 'text', value: courseData.code || '', placeholder: 'CS302' },
          { name: 'instructor', label: 'Instructor', type: 'text', value: courseData.instructor || '' },
          { name: 'location', label: 'Lokasi', type: 'text', value: courseData.location || '' },
          { name: 'link', label: 'Link Materi', type: 'url', value: courseData.link || '', placeholder: 'https://…' },
          { name: 'start_date', label: 'Tanggal Mulai', type: 'date', value: (courseData.start_date || '').substring(0, 10) },
          { name: 'status', label: 'Status', type: 'select', value: courseData.status || 'active',
            options: [
              { value: 'active', label: 'Active' },
              { value: 'inactive', label: 'Inactive' },
            ],
          },
        ],
        submitLabel: 'Simpan Perubahan',
      });
      if (!data) return;
      const { data: resp } = await api.post('../api/study.php?action=update_course&id=' + courseId, data);
      if (resp && resp.success) await load();
      else await showAlertModal({ type: 'error', message: (resp && resp.message) || 'Gagal update.' });
    });
  }

  // Add Homework
  const addHwBtn = document.getElementById('btnAddHomework');
  if (addHwBtn) {
    addHwBtn.addEventListener('click', async () => {
      const data = await showFormModal({
        title: 'Tambah Homework',
        fields: [
          { name: 'title', label: 'Judul Tugas', type: 'text', required: true,
            placeholder: 'contoh: Tugas Knapsack 0/1' },
          { name: 'description', label: 'Deskripsi (opsional)', type: 'textarea' },
          { name: 'due_date', label: 'Deadline (opsional)', type: 'date' },
        ],
        submitLabel: 'Tambah Homework',
      });
      if (!data) return;
      const { data: resp } = await api.post('../api/study.php?action=add_homework', {
        course_id: courseId,
        title: data.title,
        description: data.description,
        due_date: data.due_date,
        status: 'pending',
      });
      if (resp && resp.success) await load();
      else await showAlertModal({ type: 'error', message: (resp && resp.message) || 'Gagal menambah homework.' });
    });
  }

  // Delete Course
  const delCourseBtn = document.getElementById('btnDeleteCourse');
  if (delCourseBtn) {
    delCourseBtn.addEventListener('click', async () => {
      const ok = await showConfirmModal({
        title: 'Hapus Mata Kuliah?',
        message: 'Homework di mata kuliah ini akan ikut terhapus. Catatan tetap ada (course-link di-reset).',
        confirmLabel: 'Hapus Mata Kuliah',
        danger: true,
      });
      if (!ok) return;
      const { data } = await api.delete('../api/study.php?action=delete_course&id=' + courseId);
      if (data && data.success) {
        window.location.href = 'StudyList.html';
      } else {
        await showAlertModal({ type: 'error', message: (data && data.message) || 'Gagal hapus.' });
      }
    });
  }

  // Event delegation untuk homework
  const hwListEl = document.getElementById('homeworkList');
  if (hwListEl) {
    hwListEl.addEventListener('click', async (e) => {
      const del = e.target.closest('[data-action="delete-hw"]');
      if (del) {
        e.preventDefault();
        const ok = await showConfirmModal({
          title: 'Hapus Homework?',
          message: 'Aksi tidak bisa dibatalkan.',
          confirmLabel: 'Hapus',
          danger: true,
        });
        if (!ok) return;
        const { data } = await api.delete('../api/study.php?action=delete_homework&id=' + del.dataset.id);
        if (data && data.success) await load();
        else await showAlertModal({ type: 'error', message: (data && data.message) || 'Gagal hapus.' });
        return;
      }
      const edit = e.target.closest('[data-action="edit-hw"]');
      if (edit) {
        e.preventDefault();
        const id = parseInt(edit.dataset.id, 10);
        const hw = homeworkList.find(h => h.id === id);
        if (!hw) return;
        const data = await showFormModal({
          title: 'Edit Homework',
          fields: [
            { name: 'title', label: 'Judul Tugas', type: 'text', required: true, value: hw.title },
            { name: 'description', label: 'Deskripsi', type: 'textarea', value: hw.description || '' },
            { name: 'due_date', label: 'Deadline', type: 'date', value: (hw.due_date || '').substring(0, 10) },
            { name: 'status', label: 'Status', type: 'select', value: hw.status,
              options: [
                { value: 'pending', label: 'Pending' },
                { value: 'done', label: 'Done' },
              ],
            },
          ],
          submitLabel: 'Simpan',
        });
        if (!data) return;
        const { data: resp } = await api.post('../api/study.php?action=update_homework&id=' + id, data);
        if (resp && resp.success) await load();
        else await showAlertModal({ type: 'error', message: (resp && resp.message) || 'Gagal edit.' });
      }
    });
    hwListEl.addEventListener('change', async (e) => {
      const tgl = e.target.closest('[data-action="toggle-hw"]');
      if (!tgl) return;
      const newStatus = tgl.checked ? 'done' : 'pending';
      const { data } = await api.post('../api/study.php?action=update_homework&id=' + tgl.dataset.id, { status: newStatus });
      if (!data || !data.success) {
        tgl.checked = !tgl.checked;
        await showAlertModal({ type: 'error', message: (data && data.message) || 'Gagal update.' });
      } else {
        await load();
      }
    });
  }

  await load();

  async function load() {
    const res = await api.get('../api/study.php?view=course_detail&course_id=' + courseId);
    if (!res.data || !res.data.success) {
      await showAlertModal({ type: 'error', message: (res.data && res.data.message) || 'Gagal memuat course.' });
      window.location.href = 'StudyList.html';
      return;
    }
    courseData = res.data.data.course;
    homeworkList = res.data.data.homework || [];
    renderHeader(courseData);
    renderNotes(res.data.data.notes || []);
    renderHomework(homeworkList);
  }

  function renderHeader(c) {
    setText('courseName', c.name);
    setText('courseCodeSemester',
      (c.code ? c.code + ' • ' : '') + (c.semester_name || '—')
    );
    setText('courseInstructor', c.instructor || '—');
    setText('courseLocation', c.location || '—');
    setText('courseStartDate', c.start_date ? formatDate(c.start_date) : '—');

    const linkEl = document.getElementById('courseLink');
    if (linkEl) {
      if (c.link) {
        linkEl.href = c.link;
        linkEl.textContent = c.link;
        linkEl.style.display = '';
      } else {
        linkEl.style.display = 'none';
      }
    }
    const chip = document.getElementById('statusChip');
    if (chip) {
      chip.textContent = c.status === 'active' ? '● Active' : '○ Inactive';
      chip.style.color = c.status === 'active' ? '#10b981' : '#9ca3af';
    }
  }

  function renderNotes(notes) {
    const list = document.getElementById('notesList');
    if (!list) return;
    if (!notes.length) {
      list.innerHTML = '<p style="color:#666;padding:0.5rem;">Belum ada catatan di mata kuliah ini. '
        + '<a href="UploadNotes.html?course_id=' + courseId + '">Upload pertama →</a></p>';
      return;
    }
    list.innerHTML = notes.map(n => ''
      + '<div class="note-card card" data-id="' + n.id + '">'
      +   '<div class="note-card-header">'
      +     '<div class="note-date-info">'
      +       '<div class="icon-date-wrapper"><i class="fa-solid fa-calendar"></i></div>'
      +       '<span class="note-date">' + formatDate(n.created_at) + '</span>'
      +     '</div>'
      +     '<div class="note-actions">'
      +       '<a href="UpdateNotes.html?id=' + n.id + '" title="Edit" '
      +         'style="text-decoration:none;color:#3b82f6;margin-right:0.5rem;">'
      +         '<i class="fa-solid fa-pen"></i></a>'
      +       '<a href="' + escapeHtml(n.file_url) + '" download="' + escapeHtml(n.file_name) + '" title="Download" '
      +         'style="text-decoration:none;color:#10b981;">'
      +         '<i class="fa-solid fa-download"></i></a>'
      +     '</div>'
      +   '</div>'
      +   '<h3 class="note-title">'
      +     '<a href="DetailCatatan.html?id=' + n.id + '" style="text-decoration:none;color:inherit;">'
      +       escapeHtml(n.title || n.course_name) + '</a></h3>'
      +   '<p class="note-preview">' + escapeHtml(n.description || '—') + '</p>'
      + '</div>'
    ).join('');
  }

  function renderHomework(hw) {
    const list = document.getElementById('homeworkList');
    if (!list) return;
    if (!hw.length) {
      list.innerHTML = '<p style="color:#666;padding:0.5rem;">Belum ada homework. Klik "Add Homework" di atas.</p>';
      return;
    }
    list.innerHTML = hw.map(h => {
      const due = h.due_date ? formatDate(h.due_date) : '—';
      const checked = h.status === 'done' ? 'checked' : '';
      const lineThrough = h.status === 'done' ? 'text-decoration:line-through;color:#9ca3af;' : '';
      return ''
        + '<div class="homework-item" data-id="' + h.id + '" '
        +   'style="display:flex;gap:0.75rem;padding:0.75rem;border:1px solid #e5e7eb;'
        +   'border-radius:8px;margin-bottom:0.5rem;align-items:flex-start;background:#fff;">'
        +   '<input type="checkbox" ' + checked + ' data-action="toggle-hw" data-id="' + h.id + '" '
        +     'style="margin-top:0.25rem;cursor:pointer;width:1.1rem;height:1.1rem;">'
        +   '<div style="flex:1;' + lineThrough + '">'
        +     '<div style="font-weight:600;">' + escapeHtml(h.title) + '</div>'
        +     (h.description
              ? '<div style="font-size:0.85rem;color:#666;margin-top:0.25rem;">' + escapeHtml(h.description) + '</div>'
              : '')
        +     '<div style="font-size:0.8rem;color:#9ca3af;margin-top:0.25rem;">'
        +       '<i class="fa-solid fa-calendar"></i> Deadline: ' + due + '</div>'
        +   '</div>'
        +   '<div style="display:flex;gap:0.5rem;">'
        +     '<button data-action="edit-hw" data-id="' + h.id + '" '
        +       'style="background:none;border:none;color:#3b82f6;cursor:pointer;">'
        +       '<i class="fa-solid fa-pen"></i> Edit</button>'
        +     '<button data-action="delete-hw" data-id="' + h.id + '" '
        +       'style="background:none;border:none;color:#ef4444;cursor:pointer;">'
        +       '<i class="fa-solid fa-trash"></i> Hapus</button>'
        +   '</div>'
        + '</div>';
    }).join('');
  }

  function setText(id, v) {
    const el = document.getElementById(id);
    if (el) el.textContent = v;
  }
})();
