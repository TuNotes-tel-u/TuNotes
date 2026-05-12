// my-notes.js — grid catatan milik user yang login.
// Pakai modal.js untuk konfirmasi delete (bukan confirm() browser).
(async function () {
  const user = await requireAuth();
  if (!user) return;

  // Tampilkan nama user di header
  const nameSpan = document.querySelector('.user-profile span');
  if (nameSpan) nameSpan.textContent = user.name || user.username;

  // Logout
  const logout = document.querySelector('.btn-logout');
  if (logout) {
    logout.addEventListener('click', async (e) => {
      e.preventDefault();
      await api.delete('../api/auth.php', { autoRedirect: false });
      window.location.href = 'LandingPage.html';
    });
  }

  // Inject CSS hover + icon flex-shrink satu kali (inline tidak bisa :hover / child selector).
  if (!document.getElementById('my-notes-card-styles')) {
    const s = document.createElement('style');
    s.id = 'my-notes-card-styles';
    s.textContent = ''
      + '.note-action{transition:filter 0.15s;}'
      + '.note-action:hover{filter:brightness(0.92);}'
      + '.note-action i{flex-shrink:0;}';
    document.head.appendChild(s);
  }

  const grid = document.getElementById('notesGrid');
  if (grid) {
    // Pastikan layout grid auto-fit walaupun CSS belum punya rule khusus.
    grid.style.display = 'grid';
    grid.style.gridTemplateColumns = 'repeat(auto-fill, minmax(280px, 1fr))';
    grid.style.gap = '1.25rem';
    grid.innerHTML = '<p style="grid-column:1/-1;color:#666;padding:1rem;">Memuat…</p>';
  }

  const { data } = await api.get('../api/notes.php?scope=mine&limit=48');
  if (!data || !data.success) {
    if (grid) grid.innerHTML = '<p style="grid-column:1/-1;color:#b91c1c;padding:1rem;">Gagal memuat catatan.</p>';
    return;
  }
  if (!data.data.length) {
    if (grid) grid.innerHTML = '<p style="grid-column:1/-1;color:#666;padding:1rem;">Belum ada catatan. <a href="UploadNotes.html">Upload pertama →</a></p>';
    return;
  }

  // Variasi gambar fallback per card (assets sudah ada)
  const fallbacks = ['Mynotes1.jpg','Mynotes2.jpg','Mynotes3.jpg','Mynotes4.jpg',
                     'Mynotes5.jpg','Mynotes6.jpg','Mynotes7.jpg','Mynotes8.jpg'];

  // -- Style constants (inline supaya tidak tergantung CSS eksternal) --
  const cardStyle    = 'background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.08);'
                     + 'overflow:hidden;display:flex;flex-direction:column;';
  const imgStyle     = 'width:100%;aspect-ratio:16/10;object-fit:cover;background:#e5e7eb;display:block;';
  const bodyStyle    = 'padding:1rem;flex:1;display:flex;flex-direction:column;gap:0.25rem;';
  const titleStyle   = 'margin:0;font-weight:600;font-size:1rem;line-height:1.3;'
                     + 'white-space:nowrap;overflow:hidden;text-overflow:ellipsis;'
                     + 'text-decoration:none;color:#111827;display:block;';
  const metaStyle    = 'margin:0;color:#6b7280;font-size:0.85rem;';
  const actionsStyle = 'display:flex;gap:0.4rem;padding:0 1rem 1rem 1rem;';
  const actionBase   = 'flex:1;padding:0.45rem 0.5rem;border-radius:6px;font-size:0.82rem;'
                     + 'display:flex;align-items:center;justify-content:center;gap:0.3rem;'
                     + 'text-decoration:none;border:none;cursor:pointer;font-weight:500;'
                     + 'font-family:inherit;line-height:1.2;'
                     + 'white-space:nowrap;overflow:hidden;text-overflow:ellipsis;'
                     + 'min-width:0;';
  const actionEdit     = 'background:#dbeafe;color:#1e40af;';
  const actionDelete   = 'background:#fee2e2;color:#991b1b;';
  const actionDownload = 'background:#dcfce7;color:#166534;';

  grid.innerHTML = data.data.map((n, i) => {
    const titleText = escapeHtml(n.title || n.course_name);
    const titleAttr = escapeHtml(n.title || n.course_name || '');
    return ''
      + '<div class="note-card" data-id="' + n.id + '" style="' + cardStyle + '">'
      +   '<img src="../assets/images/' + fallbacks[i % fallbacks.length] + '" '
      +     'alt="' + escapeHtml(n.course_name) + '" class="note-card__image" style="' + imgStyle + '">'
      +   '<div class="note-card__body" style="' + bodyStyle + '">'
      +     '<a class="note-card__title" href="DetailCatatan.html?id=' + n.id + '" '
      +       'title="' + titleAttr + '" style="' + titleStyle + '">' + titleText + '</a>'
      +     '<p class="note-card__meta" style="' + metaStyle + '">' + escapeHtml(n.lecturer_name || '-') + '</p>'
      +     '<p class="note-card__date" style="' + metaStyle + '">' + formatDate(n.created_at) + '</p>'
      +   '</div>'
      +   '<div class="note-card__actions" style="' + actionsStyle + '">'
      +     '<a class="note-action note-action--edit" href="UpdateNotes.html?id=' + n.id + '" '
      +       'style="' + actionBase + actionEdit + '">'
      +       '<i class="fa-solid fa-pen"></i> Update</a>'
      +     '<button class="note-action note-action--delete" data-action="delete" data-id="' + n.id + '" '
      +       'style="' + actionBase + actionDelete + '">'
      +       '<i class="fa-solid fa-trash"></i> Delete</button>'
      +     '<a class="note-action note-action--download" href="' + escapeHtml(n.file_url) + '" '
      +       'download="' + escapeHtml(n.file_name) + '" '
      +       'style="' + actionBase + actionDownload + '">'
      +       '<i class="fa-solid fa-download"></i> Unduh</a>'
      +   '</div>'
      + '</div>';
  }).join('');

  // Delegasi event delete (modal konfirmasi).
  grid.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-action="delete"]');
    if (!btn) return;
    e.preventDefault();
    const ok = await showConfirmModal({
      title: 'Hapus Catatan?',
      message: 'Yakin hapus catatan ini? File akan terhapus dari server dan aksi tidak bisa dibatalkan.',
      confirmLabel: 'Hapus Catatan',
      danger: true,
    });
    if (!ok) return;

    const id = btn.dataset.id;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menghapus…';
    const { data: resp } = await api.delete('../api/notes.php?id=' + id);
    if (resp && resp.success) {
      const card = btn.closest('.note-card');
      if (card) {
        card.style.transition = 'opacity 0.25s, transform 0.25s';
        card.style.opacity = '0';
        card.style.transform = 'scale(0.95)';
        setTimeout(() => card.remove(), 250);
      }
    } else {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa-solid fa-trash"></i> Delete';
      await showAlertModal({
        type: 'error',
        title: 'Gagal Menghapus',
        message: (resp && resp.message) || 'Tidak bisa menghapus catatan. Coba lagi.',
      });
    }
  });
})();
