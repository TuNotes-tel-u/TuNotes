// update-notes.js — form edit catatan, populate dari API lalu submit lewat multipart.
(async function () {
  const user = await requireAuth();
  if (!user) return;

  const params = new URLSearchParams(window.location.search);
  const id = parseInt(params.get('id'), 10);
  if (!id) {
    alert('ID catatan tidak ada di URL.');
    window.location.href = 'MyNotes.html';
    return;
  }

  const form        = document.getElementById('updateForm');
  const fileInput   = document.getElementById('fileInputUpdate');
  const chooseBtn   = document.getElementById('chooseNewFile');
  const fileNameEl  = document.getElementById('currentFileName');
  const fileSizeEl  = document.getElementById('currentFileSize');
  const cancelBtn   = form.querySelector('.btn-cancel');
  const saveBtn     = form.querySelector('.btn-save');

  // Ambil data sekarang
  const res = await api.get('../api/notes.php?id=' + id);
  if (!res.data || !res.data.success) {
    alert((res.data && res.data.message) || 'Gagal memuat catatan.');
    window.location.href = 'MyNotes.html';
    return;
  }
  const note = res.data.data;
  if (note.user_id !== user.id) {
    alert('Hanya pemilik yang boleh mengubah catatan ini.');
    window.location.href = 'MyNotes.html';
    return;
  }

  // Populate field by ID (HTML existing pakai id, bukan name)
  document.getElementById('courseName').value   = note.course_name || '';
  document.getElementById('lecturerName').value = note.lecturer_name || '';
  document.getElementById('description').value  = note.description || '';
  const catEl  = document.getElementById('category');
  if (catEl) catEl.value = note.category || '';
  const tagsEl = document.getElementById('tagsInput');
  if (tagsEl) tagsEl.value = (note.tags || []).join(', ');

  if (fileNameEl) fileNameEl.textContent = note.file_name || '';
  if (fileSizeEl) fileSizeEl.textContent = formatBytes(note.file_size);

  // Pilih file baru (opsional)
  if (chooseBtn && fileInput) {
    chooseBtn.addEventListener('click', (e) => { e.preventDefault(); fileInput.click(); });
    fileInput.addEventListener('change', () => {
      const f = fileInput.files[0];
      if (!f) return;
      if (fileNameEl) fileNameEl.textContent = f.name + ' (akan diganti)';
      if (fileSizeEl) fileSizeEl.textContent = formatBytes(f.size);
    });
  }

  if (cancelBtn) {
    cancelBtn.addEventListener('click', (e) => {
      e.preventDefault();
      window.location.href = 'MyNotes.html';
    });
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData();
    fd.append('course_name',   document.getElementById('courseName').value.trim());
    fd.append('lecturer_name', document.getElementById('lecturerName').value.trim());
    if (catEl)  fd.append('category', catEl.value.trim());
    fd.append('description',   document.getElementById('description').value.trim());
    if (tagsEl) fd.append('tags', tagsEl.value.trim());
    if (fileInput && fileInput.files && fileInput.files[0]) {
      fd.append('file', fileInput.files[0]);
    }

    saveBtn.disabled = true;
    const originalHtml = saveBtn.innerHTML;
    saveBtn.textContent = 'Menyimpan…';

    const { data } = await api.postForm('../api/notes.php?action=update&id=' + id, fd);

    saveBtn.disabled = false;
    saveBtn.innerHTML = originalHtml;

    if (data && data.success) {
      alert('Perubahan disimpan.');
      window.location.href = 'MyNotes.html';
    } else {
      alert((data && data.message) || 'Gagal menyimpan perubahan.');
    }
  });
})();
