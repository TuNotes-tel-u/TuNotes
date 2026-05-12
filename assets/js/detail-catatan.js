// detail-catatan.js — halaman detail catatan: render note + comments + like + rating (Tahap 3).
let currentUser = null;
let noteId = null;
let noteData = null;
let isFavorited = false;
let myRating = 0;

(async function () {
  const params = new URLSearchParams(window.location.search);
  noteId = parseInt(params.get('id'), 10);
  if (!noteId) {
    alert('ID catatan tidak ada di URL.');
    window.location.href = 'Dashboard.html';
    return;
  }

  // requireAuth dengan silent supaya guest tetap bisa lihat catatan public.
  currentUser = await requireAuth({ silent: true });

  await loadNote();
  await loadComments();
  if (currentUser) {
    await loadFavoriteState();
    setupRatingWidget();
  }
  wireLikeButton();
  wireCommentForm();
})();

// =============================================================================
// Note detail (sama seperti Tahap 2, plus simpan noteData ke state global)
// =============================================================================
async function loadNote() {
  const res = await api.get('../api/notes.php?id=' + noteId, { autoRedirect: false });
  if (!res.data || !res.data.success) {
    if (res.status === 403)      alert('Catatan ini privat.');
    else if (res.status === 404) alert('Catatan tidak ditemukan.');
    else                          alert((res.data && res.data.message) || 'Gagal memuat.');
    window.location.href = 'Dashboard.html';
    return;
  }
  noteData = res.data.data;

  const titleEl = document.querySelector('.page-title');
  if (titleEl) titleEl.textContent = noteData.title || noteData.course_name;

  const dlBtn = document.querySelector('a.btn.btn-primary');
  if (dlBtn) {
    dlBtn.href = noteData.file_url;
    dlBtn.setAttribute('download', noteData.file_name || 'catatan');
  }

  const fileNameEl = document.querySelector('.file-name');
  const fileSizeEl = document.querySelector('.file-size');
  if (fileNameEl) fileNameEl.textContent = noteData.file_name;
  if (fileSizeEl) {
    let s = formatBytes(noteData.file_size);
    if (noteData.page_count) s += ' • ' + noteData.page_count + ' pages';
    fileSizeEl.textContent = s;
  }

  const courseTitle = document.querySelector('.course-info .card-title');
  if (courseTitle) courseTitle.textContent = noteData.course_name;
  const courseMeta = document.querySelector('.course-info .course-meta p');
  if (courseMeta) {
    const parts = [];
    if (noteData.lecturer_name) parts.push(noteData.lecturer_name);
    if (noteData.category) parts.push(noteData.category);
    courseMeta.textContent = parts.join(' • ') || '—';
  }

  const tagEl = document.querySelector('.course-info .tag');
  if (tagEl) tagEl.textContent = (noteData.tags && noteData.tags[0]) || (noteData.category || 'Catatan');

  const dateEl = document.querySelector('.course-info .date');
  if (dateEl) dateEl.textContent = formatDate(noteData.created_at);

  if (noteData.uploader) {
    const uploaderName = document.querySelector('.uploader-name');
    if (uploaderName) uploaderName.textContent = noteData.uploader.name;
    const authorName = document.querySelector('.author-name');
    if (authorName) authorName.textContent = noteData.uploader.name;
  }

  const authorBio = document.querySelector('.author-bio');
  if (authorBio) authorBio.textContent = noteData.description || 'Tidak ada deskripsi.';
}

// =============================================================================
// Comments
// =============================================================================
async function loadComments() {
  const list = document.getElementById('commentList');
  if (!list) return;
  list.innerHTML = '<p style="color:#666;padding:0.5rem;">Memuat komentar…</p>';

  const res = await api.get('../api/comments.php?note_id=' + noteId, { autoRedirect: false });
  if (!res.data || !res.data.success) {
    list.innerHTML = '<p style="color:#b91c1c;padding:0.5rem;">Gagal memuat komentar.</p>';
    return;
  }

  const total = (res.data.meta && res.data.meta.total) || 0;
  const counterEl = document.querySelector('.comments-header .card-title');
  if (counterEl) counterEl.textContent = 'Comments (' + total + ')';

  const comments = res.data.data;
  if (!comments.length) {
    list.innerHTML = '<p style="color:#666;padding:0.5rem;">Belum ada komentar.</p>';
    return;
  }

  list.innerHTML = comments.map(c => renderComment(c, false)).join('');
}

function renderComment(c, isReply) {
  const author   = (c.commenter && c.commenter.name) || '?';
  const content  = c.deleted ? '<em style="color:#9ca3af;">[Komentar dihapus]</em>' : escapeHtml(c.content);
  const time     = formatDate(c.created_at);
  const isMine   = currentUser && c.commenter && c.commenter.id === currentUser.id;
  const isAdmin  = currentUser && (currentUser.role === 'admin' || currentUser.role === 'super_admin');
  const canDelete = !c.deleted && (isMine || isAdmin);
  const indent   = isReply ? 'margin-left:2.5rem;border-left:2px solid #e5e7eb;padding-left:0.75rem;' : '';

  // Avatar fallback: pakai gambar default berdasarkan id komentar.
  const avatarIdx = (c.id % 3) + 1;
  const avatar = '../assets/images/user' + avatarIdx + '.png';

  let html = ''
    + '<article class="comment" data-id="' + c.id + '" style="' + indent + '">'
    +   '<img src="' + avatar + '" alt="" class="avatar-small">'
    +   '<div class="comment-body">'
    +     '<div class="comment-author"><strong>' + escapeHtml(author) + '</strong> '
    +       '<span class="comment-time">' + time + '</span></div>'
    +     '<p>' + content + '</p>'
    +     '<div class="comment-actions">';
  if (!c.deleted && currentUser && !isReply) {
    html += '<button class="btn-reply" data-action="reply" data-id="' + c.id + '">Reply</button>';
  }
  if (canDelete) {
    html += '<button data-action="delete-comment" data-id="' + c.id + '" '
         +  'style="background:none;border:none;color:#dc2626;cursor:pointer;margin-left:0.5rem;">Hapus</button>';
  }
  html += '    </div>'
       +  '    <div class="reply-form-container"></div>'
       +  '  </div>'
       +  '</article>';

  if (c.replies && c.replies.length) {
    html += c.replies.map(r => renderComment(r, true)).join('');
  }
  return html;
}

function wireCommentForm() {
  const header = document.querySelector('.comments-header');
  if (!header) return;
  const existingBtn = header.querySelector('.btn-secondary');

  if (!currentUser) {
    // Guest: ubah tombol jadi link ke login.
    if (existingBtn) {
      existingBtn.textContent = 'Login untuk komentar';
      existingBtn.addEventListener('click', () => { window.location.href = 'login.html'; });
    }
    return;
  }

  if (existingBtn) existingBtn.remove();

  const wrap = document.createElement('div');
  wrap.style.cssText = 'margin:0.75rem 0;';
  wrap.innerHTML = ''
    + '<form id="commentForm" style="display:flex;gap:0.5rem;align-items:flex-start;">'
    +   '<textarea required maxlength="1000" rows="2" placeholder="Tulis komentar…" '
    +            'style="flex:1;padding:0.5rem;border:1px solid #d1d5db;border-radius:6px;font:inherit;resize:vertical;"></textarea>'
    +   '<button type="submit" class="btn btn-secondary" style="align-self:flex-end;">Post</button>'
    + '</form>';
  header.parentNode.insertBefore(wrap, header.nextSibling);

  // Submit & event delegation untuk reply/delete di list.
  wrap.querySelector('form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const textarea = wrap.querySelector('textarea');
    const content = textarea.value.trim();
    if (!content) return;
    const { data } = await api.post('../api/comments.php', { note_id: noteId, content, parent_id: null });
    if (data && data.success) {
      textarea.value = '';
      await loadComments();
    } else {
      alert((data && data.message) || 'Gagal post komentar.');
    }
  });

  const list = document.getElementById('commentList');
  if (list) {
    list.addEventListener('click', async (e) => {
      const btn = e.target.closest('[data-action]');
      if (!btn) return;
      e.preventDefault();
      const action = btn.dataset.action;
      const id = btn.dataset.id;

      if (action === 'reply') {
        showReplyForm(btn.closest('.comment'), parseInt(id, 10));
      } else if (action === 'delete-comment') {
        if (!confirm('Hapus komentar ini?')) return;
        const { data } = await api.delete('../api/comments.php?id=' + id);
        if (data && data.success) {
          await loadComments();
        } else {
          alert((data && data.message) || 'Gagal menghapus.');
        }
      }
    });
  }
}

function showReplyForm(commentEl, parentId) {
  const container = commentEl.querySelector('.reply-form-container');
  if (!container) return;
  if (container.querySelector('form')) {
    container.innerHTML = '';
    return;
  }
  container.innerHTML = ''
    + '<form class="reply-form" style="margin-top:0.5rem;display:flex;gap:0.5rem;">'
    +   '<textarea required maxlength="1000" rows="2" '
    +            'style="flex:1;padding:0.4rem;border:1px solid #d1d5db;border-radius:4px;font:inherit;resize:vertical;"></textarea>'
    +   '<button type="submit" class="btn btn-secondary" style="align-self:flex-end;">Reply</button>'
    + '</form>';
  const form = container.querySelector('form');
  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const ta = form.querySelector('textarea');
    const content = ta.value.trim();
    if (!content) return;
    const { data } = await api.post('../api/comments.php', { note_id: noteId, content, parent_id: parentId });
    if (data && data.success) {
      await loadComments();
    } else {
      alert((data && data.message) || 'Gagal post reply.');
    }
  });
}

// =============================================================================
// Like / Favorite
// =============================================================================
async function loadFavoriteState() {
  const { data } = await api.get('../api/favorites.php?limit=100', { autoRedirect: false });
  if (data && data.success && Array.isArray(data.data)) {
    isFavorited = data.data.some(n => n.id === noteId);
    updateLikeButton();
  }
}

function updateLikeButton() {
  const btn = document.getElementById('likeBtn') || document.querySelector('button[aria-label="Sukai"]');
  if (!btn) return;
  // Override inline color (yang di-set di HTML default) supaya state ke-flip jelas.
  btn.style.color = isFavorited ? '#dc2626' : '#9ca3af';
  btn.classList.toggle('active', isFavorited);
}

function wireLikeButton() {
  const btn = document.getElementById('likeBtn') || document.querySelector('button[aria-label="Sukai"]');
  if (!btn) return;
  btn.addEventListener('click', async (e) => {
    e.preventDefault();
    if (!currentUser) {
      window.location.href = 'login.html';
      return;
    }
    btn.disabled = true;
    if (isFavorited) {
      const { data } = await api.delete('../api/favorites.php?note_id=' + noteId);
      if (data && data.success) { isFavorited = false; updateLikeButton(); }
    } else {
      const { data } = await api.post('../api/favorites.php', { note_id: noteId });
      if (data && data.success) { isFavorited = true; updateLikeButton(); }
    }
    btn.disabled = false;
  });
}

// =============================================================================
// Rating widget — 5-star di sidebar.
// Tampil untuk SEMUA viewer (termasuk owner), tapi click di-disable + pesan
// "Pemilik tidak bisa rate" kalau viewer == uploader. Widget di-inject SEBELUM
// .ai-summary supaya cepat kelihatan tanpa scroll jauh.
// =============================================================================
function setupRatingWidget() {
  if (!currentUser || !noteData) return;
  const sidebar = document.querySelector('aside.sidebar');
  if (!sidebar) return;

  const isOwner = noteData.user_id === currentUser.id;
  const avg     = (noteData.avg_rating || 0);
  const count   = noteData.rating_count || 0;
  // Pre-fill bintang sesuai rata-rata supaya owner langsung lihat skor.
  const initialPaint = isOwner ? Math.round(avg) : 0;

  const widget = document.createElement('div');
  widget.className = 'card';
  widget.id = 'ratingWidget';
  widget.style.cssText = 'padding:1rem;border:1px solid #e5e7eb;border-radius:8px;margin-top:1rem;background:#fff;';
  widget.innerHTML = ''
    + '<div class="card-header" style="margin-bottom:0.5rem;">'
    +   '<h2 class="card-title">' + (isOwner ? 'Rating Catatan Ini' : 'Beri Rating') + '</h2>'
    + '</div>'
    + '<div id="ratingStars" style="font-size:1.75rem;display:flex;gap:0.25rem;'
    +    (isOwner ? 'cursor:not-allowed;opacity:0.85;' : 'cursor:pointer;') + 'user-select:none;line-height:1;">'
    +   [1, 2, 3, 4, 5].map(i =>
        '<span class="rating-star" data-value="' + i + '" style="color:'
        + (i <= initialPaint ? '#f59e0b' : '#d1d5db') + ';transition:color 0.15s;">★</span>'
      ).join('')
    + '</div>'
    + '<p id="ratingStatus" style="margin:0.5rem 0 0;font-size:0.85rem;color:#666;">'
    +   'Rata-rata: <strong>' + avg.toFixed(1) + '</strong> (' + count + ' rating)'
    +   (isOwner ? '<br><em style="color:#9ca3af;">Pemilik tidak bisa rate catatan sendiri.</em>' : '')
    + '</p>';

  // Posisi: sebelum AI Summary supaya gak perlu scroll panjang.
  const aiSummary = sidebar.querySelector('.ai-summary');
  if (aiSummary) {
    sidebar.insertBefore(widget, aiSummary);
  } else {
    sidebar.appendChild(widget);
  }

  if (isOwner) return; // owner: hentikan di sini, gak pasang click handler

  const stars = widget.querySelectorAll('.rating-star');
  function paintStars(value) {
    stars.forEach((s, i) => {
      s.style.color = i < value ? '#f59e0b' : '#d1d5db';
    });
  }

  stars.forEach(s => {
    s.addEventListener('mouseenter', () => paintStars(parseInt(s.dataset.value, 10)));
    s.addEventListener('mouseleave', () => paintStars(myRating));
    s.addEventListener('click', async () => {
      const value = parseInt(s.dataset.value, 10);
      const { data, status } = await api.post('../api/notes.php?action=rate&id=' + noteId, { rating: value });
      if (data && data.success) {
        myRating = value;
        paintStars(myRating);
        const statusEl = document.getElementById('ratingStatus');
        if (statusEl) {
          statusEl.innerHTML = 'Rata-rata: <strong>' + data.data.avg_rating.toFixed(1)
            + '</strong> (' + data.data.rating_count + ' rating). Rating kamu: <strong>' + value + '★</strong>';
        }
      } else {
        alert((data && data.message) || ('Gagal beri rating (HTTP ' + status + ').'));
      }
    });
  });
}
