// upload-notes.js — form upload catatan baru via multipart.
(async function () {
  const user = await requireAuth();
  if (!user) return;

  const nameSpan = document.querySelector('.user-menu span');
  if (nameSpan) nameSpan.textContent = user.name || user.username;

  // Pre-fill course_name kalau URL bawa ?course_id=X (dari Detail Mata Kuliah).
  // Sekaligus simpan course_id di hidden input supaya ikut ke-submit.
  const params = new URLSearchParams(window.location.search);
  const courseIdFromUrl = parseInt(params.get('course_id'), 10);
  let hiddenCourseId = null;
  if (courseIdFromUrl) {
    const r = await api.get('../api/study.php?view=course_detail&course_id=' + courseIdFromUrl);
    if (r.data && r.data.success) {
      const course = r.data.data.course;
      const courseInput = document.getElementById('courseName');
      const lecturerInput = document.getElementById('lecturerName');
      if (courseInput) {
        courseInput.value = course.name || '';
        courseInput.readOnly = true;
        courseInput.style.background = '#f3f4f6';
      }
      if (lecturerInput && course.instructor && !lecturerInput.value) {
        lecturerInput.value = course.instructor;
      }
      hiddenCourseId = course.id;
    }
  }

  // Logout
  const logout = document.querySelector('.nav-logout');
  if (logout) {
    logout.addEventListener('click', async (e) => {
      e.preventDefault();
      await api.delete('../api/auth.php', { autoRedirect: false });
      window.location.href = 'LandingPage.html';
    });
  }

  const form       = document.querySelector('.notes-details-section');
  const fileInput  = document.getElementById('fileInput');
  const fileStatus = document.getElementById('fileStatus');
  const submitBtn  = document.getElementById('submitButton');
  const dropzone   = document.getElementById('dropzoneArea');

  function updateFileStatus() {
    if (!fileInput.files || fileInput.files.length === 0) {
      fileStatus.textContent = 'Belum ada file dipilih.';
      submitBtn.disabled = true;
      return;
    }
    const f = fileInput.files[0];
    fileStatus.textContent = f.name + ' (' + formatBytes(f.size) + ')';
    submitBtn.disabled = false;
  }

  fileInput.addEventListener('change', updateFileStatus);

  // Klik di dropzone → pilih file
  dropzone.addEventListener('click', (e) => {
    // Hindari double-trigger kalau klik tepat di input.
    if (e.target === fileInput) return;
    fileInput.click();
  });

  // Drag & drop
  dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('drag-over'); });
  dropzone.addEventListener('dragleave', () => dropzone.classList.remove('drag-over'));
  dropzone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropzone.classList.remove('drag-over');
    if (e.dataTransfer && e.dataTransfer.files.length) {
      // Note: assigning ke fileInput.files cuma jalan di browser modern via DataTransfer.
      fileInput.files = e.dataTransfer.files;
      updateFileStatus();
    }
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const courseName = document.getElementById('courseName').value.trim();
    if (!courseName) { alert('Course Name wajib diisi.'); return; }
    if (!fileInput.files || !fileInput.files.length) { alert('Pilih file dulu.'); return; }

    const fd = new FormData();
    fd.append('course_name',   courseName);
    fd.append('lecturer_name', document.getElementById('lecturerName').value.trim());
    fd.append('category',      document.getElementById('category').value.trim());
    fd.append('tags',          document.getElementById('tags').value.trim());
    fd.append('description',   document.getElementById('description').value.trim());
    if (hiddenCourseId) fd.append('course_id', hiddenCourseId);
    fd.append('file',          fileInput.files[0]);

    const label = submitBtn.querySelector('span');
    submitBtn.disabled = true;
    if (label) label.textContent = 'Mengupload…';

    const { data } = await api.postForm('../api/notes.php', fd);

    if (data && data.success) {
      alert('Upload berhasil!');
      window.location.href = 'MyNotes.html';
      return;
    }

    submitBtn.disabled = false;
    if (label) label.textContent = 'Upload Notes';
    let msg = (data && data.message) || 'Gagal upload.';
    if (data && data.details && typeof data.details === 'object') {
      msg += '\n' + Object.values(data.details).join('\n');
    }
    alert(msg);
  });
})();
