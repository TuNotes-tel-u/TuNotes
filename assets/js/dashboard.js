// dashboard.js — catatan publik (Terbaru/Populer/Rekomendasi AI) + heart toggle (Tahap 3).
let myFavoriteIds = new Set();

(async function () {
  const user = await requireAuth();
  if (!user) return;

  const greetEl = document.getElementById('greeting');
  if (greetEl) greetEl.textContent = 'Halo, ' + (user.name || user.username).toUpperCase() + ' !';
  const nameEl = document.getElementById('userMenuName');
  if (nameEl) nameEl.textContent = user.name || user.username;

  // Fetch favorit user + progress belajar paralel (gak saling tergantung).
  // Target mingguan dibaca dari localStorage; editor target dipindah ke NotesRead.html.
  await Promise.all([loadMyFavoriteIds(), loadProgressBelajar()]);

  // Tab handler
  const tabs = document.querySelectorAll('.tab-btn');
  tabs.forEach(btn => {
    btn.addEventListener('click', () => {
      tabs.forEach(t => t.classList.remove('active'));
      btn.classList.add('active');
      loadNotes(btn.dataset.sort || 'newest');
    });
  });

  // Logout
  const logout = document.getElementById('logoutLink');
  if (logout) {
    logout.addEventListener('click', async (e) => {
      e.preventDefault();
      await api.delete('../api/auth.php', { autoRedirect: false });
      window.location.href = 'LandingPage.html';
    });
  }

  // Event delegation: heart click di grid → toggle favorit.
  const grid = document.getElementById('notesGrid');
  if (grid) {
    grid.addEventListener('click', async (e) => {
      const btn = e.target.closest('.like-btn');
      if (!btn) return;
      e.preventDefault();
      const id = parseInt(btn.dataset.id, 10);
      if (!id) return;
      const liked = btn.classList.contains('active');
      btn.disabled = true;
      if (liked) {
        const { data } = await api.delete('../api/favorites.php?note_id=' + id);
        if (data && data.success) {
          btn.classList.remove('active');
          myFavoriteIds.delete(id);
        }
      } else {
        const { data } = await api.post('../api/favorites.php', { note_id: id });
        if (data && data.success) {
          btn.classList.add('active');
          myFavoriteIds.add(id);
        }
      }
      btn.disabled = false;
    });
  }

  await loadNotes('newest');
})();

async function loadMyFavoriteIds() {
  myFavoriteIds = new Set();
  const { data } = await api.get('../api/favorites.php?limit=100', { autoRedirect: false });
  if (data && data.success && Array.isArray(data.data)) {
    data.data.forEach(n => myFavoriteIds.add(n.id));
  }
}

async function loadProgressBelajar() {
  const numEl  = document.getElementById('progressNumber');
  const fillEl = document.getElementById('progressFill');
  const msgEl  = document.getElementById('progressMessage');
  if (!numEl || !fillEl || !msgEl) return;

  const target = parseInt(localStorage.getItem('weeklyTarget') || '20', 10) || 20;
  const { data } = await api.get('../api/read-notes.php?view=reading_board', { autoRedirect: false });
  const done = (data && data.success && data.meta && typeof data.meta.done_count === 'number')
               ? data.meta.done_count : 0;

  const pct = Math.min(100, Math.round((done / target) * 100));
  numEl.textContent = done + '/' + target;
  fillEl.style.width = pct + '%';

  if (done >= target) {
    msgEl.textContent = '🎉 Target minggu ini tercapai! Mantap!';
    msgEl.style.color = '#10b981';
    msgEl.style.fontWeight = '600';
  } else {
    const remaining = target - done;
    msgEl.textContent = remaining + ' catatan lagi untuk mencapai target mingguan!';
    msgEl.style.color = '';
    msgEl.style.fontWeight = '';
  }
}

const badgeMap = { pdf: 'badge-pdf', doc: 'badge-doc', docx: 'badge-doc', ppt: 'badge-ppt', pptx: 'badge-ppt', txt: 'badge-doc' };

async function loadNotes(sort) {
  const grid = document.getElementById('notesGrid');
  if (!grid) return;
  grid.innerHTML = '<p style="grid-column:1/-1;color:#666;padding:1rem;">Memuat catatan…</p>';

  const url = '../api/notes.php?scope=public&sort=' + encodeURIComponent(sort) + '&limit=12';
  const { data } = await api.get(url, { autoRedirect: false });

  if (!data || !data.success) {
    grid.innerHTML = '<p style="grid-column:1/-1;color:#b91c1c;padding:1rem;">Gagal memuat catatan.</p>';
    return;
  }
  if (!data.data.length) {
    grid.innerHTML = '<p style="grid-column:1/-1;color:#666;padding:1rem;">Belum ada catatan publik. <a href="UploadNotes.html">Upload pertama →</a></p>';
    return;
  }
  grid.innerHTML = data.data.map(renderCard).join('');
}

function renderCard(n) {
  const liked = myFavoriteIds.has(n.id);
  const title = escapeHtml(n.title || n.course_name);
  const lecturer = escapeHtml(n.lecturer_name || '-');
  const rating = (n.avg_rating || 0).toFixed(1);
  const downloads = n.downloads_count || 0;
  const date = formatDate(n.created_at);
  const ext = (n.file_type || '').toLowerCase();
  const badgeClass = badgeMap[ext] || 'badge-pdf';

  // akar-icons CDN sering gagal render — pakai Unicode ♥ supaya heart selalu kelihatan.
  // Warna mengikuti CSS .like-btn / .like-btn.active di Dashboard.css (abu / merah).
  return ''
    + '<article class="note-card">'
    +   '<div class="card-header">'
    +     '<div class="card-icon"><i class="ai-file"></i></div>'
    +     '<button class="like-btn' + (liked ? ' active' : '') + '" aria-label="Sukai" data-id="' + n.id + '">'
    +       '<span aria-hidden="true" style="font-size:1.5rem;line-height:1;">♥</span>'
    +     '</button>'
    +   '</div>'
    +   '<div class="card-body">'
    +     '<a href="DetailCatatan.html?id=' + n.id + '"><h3>' + title + '</h3></a>'
    +     '<p>' + lecturer + '</p>'
    +   '</div>'
    +   '<div class="card-footer">'
    +     '<div class="meta-item"><i class="ai-star-fill"></i> ' + rating + '</div>'
    +     '<div class="meta-item"><i class="ai-person"></i> ' + downloads + '</div>'
    +     '<span class="meta-date">' + date + '</span>'
    +     '<span class="' + badgeClass + '">' + ext.toUpperCase() + '</span>'
    +   '</div>'
    + '</article>';
}
