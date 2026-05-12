// favorites.js — halaman MyFavorites: list catatan favorit + unfavorite + sort + pagination.
(function () {
  let currentSort = 'date_added';
  let currentPage = 1;

  (async function () {
    const user = await requireAuth();
    if (!user) return;

    const nameSpan = document.querySelector('.user-profile span');
    if (nameSpan) nameSpan.textContent = user.name || user.username;

    const logout = document.querySelector('.nav-logout');
    if (logout) {
      logout.addEventListener('click', async (e) => {
        e.preventDefault();
        await api.delete('../api/auth.php', { autoRedirect: false });
        window.location.href = 'LandingPage.html';
      });
    }

    // Replace dropdown options dengan value yang match API.
    const sortSelect = document.getElementById('sort-select');
    if (sortSelect) {
      sortSelect.innerHTML =
        '<option value="date_added">Date Added</option>' +
        '<option value="rating">Rating</option>' +
        '<option value="pages">Pages</option>';
      sortSelect.value = currentSort;
      sortSelect.addEventListener('change', () => {
        currentSort = sortSelect.value;
        currentPage = 1;
        load();
      });
    }

    // Event delegation: klik heart icon di card → unfavorite.
    const list = document.querySelector('.favorite-list');
    if (list) {
      list.addEventListener('click', async (e) => {
        const btn = e.target.closest('.btn-unfavorite');
        if (!btn) return;
        e.preventDefault();
        const id = btn.dataset.id;
        const card = btn.closest('.favorite-card');
        if (card) {
          card.style.transition = 'opacity 0.25s';
          card.style.opacity = '0.4';
        }
        const { data } = await api.delete('../api/favorites.php?note_id=' + id);
        if (data && data.success) {
          if (card) card.style.opacity = '0';
          setTimeout(load, 250);
        } else {
          if (card) card.style.opacity = '1';
          alert((data && data.message) || 'Gagal menghapus dari favorit.');
        }
      });
    }

    await load();
  })();

  async function load() {
    const list = document.querySelector('.favorite-list');
    if (!list) return;
    list.innerHTML = '<p style="color:#666;padding:1rem;">Memuat favorit…</p>';

    const url = '../api/favorites.php?sort=' + encodeURIComponent(currentSort) +
                '&page=' + currentPage + '&limit=10';
    const { data } = await api.get(url);

    if (!data || !data.success) {
      list.innerHTML = '<p style="color:#b91c1c;padding:1rem;">Gagal memuat favorit.</p>';
      return;
    }
    if (!data.data.length) {
      list.innerHTML = '<p style="color:#666;padding:1rem;">Belum ada catatan favorit. <a href="Dashboard.html">Cari catatan publik →</a></p>';
      const info = document.querySelector('.pagination-info');
      if (info) info.textContent = 'Showing 0 results';
      const ctrls = document.querySelector('.pagination-controls');
      if (ctrls) ctrls.innerHTML = '';
      return;
    }

    list.innerHTML = data.data.map(renderCard).join('');

    const m = data.meta;
    const start = (m.page - 1) * m.limit + 1;
    const end = Math.min(m.page * m.limit, m.total);
    const info = document.querySelector('.pagination-info');
    if (info) info.textContent = 'Showing ' + start + ' to ' + end + ' of ' + m.total + ' results';
    renderPagination(m);
  }

  function renderCard(n) {
    const title = escapeHtml(n.title || n.course_name);
    const lecturer = escapeHtml(n.lecturer_name || '-');
    const category = escapeHtml(n.category || '');
    const description = escapeHtml(n.description || '—');
    const rating = (n.avg_rating || 0).toFixed(1);
    const pages = n.page_count != null ? n.page_count : '-';
    const added = formatDate(n.favorited_at);
    return ''
      + '<div class="favorite-card" data-id="' + n.id + '">'
      +   '<div class="card__main-content">'
      +     '<div class="card__details">'
      +       '<div class="card__title-group">'
      +         '<div class="card__icon-wrapper"><i class="fas fa-file"></i></div>'
      +         '<h3 class="card__title">' + title + '</h3>'
      +       '</div>'
      +       '<p class="card__description">' + description + '</p>'
      +     '</div>'
      +     '<div class="card__metadata-actions">'
      +       '<div class="card__metadata">'
      +         '<span class="metadata-item">' + (category ? category + ' • ' : '') + lecturer + '</span>'
      +         '<div class="rating-group">'
      +           '<span class="rating-stars">' + renderStars(n.avg_rating || 0) + '</span>'
      +           '<span class="rating-value">' + rating + '</span>'
      +         '</div>'
      +         '<span class="metadata-item">• Added ' + added + ' • ' + pages + ' pages</span>'
      +       '</div>'
      +       '<div class="card__actions" style="display:flex;gap:0.75rem;align-items:center;">'
      +         '<button class="btn-unfavorite" data-id="' + n.id + '" aria-label="Hapus dari favorit" '
      +                'style="background:transparent;border:none;cursor:pointer;color:#dc2626;font-size:1.25rem;">'
      +           '<i class="fas fa-heart"></i>'
      +         '</button>'
      +         '<a href="DetailCatatan.html?id=' + n.id + '" class="btn btn--view-details">View Details</a>'
      +       '</div>'
      +     '</div>'
      +   '</div>'
      + '</div>';
  }

  function renderStars(score) {
    const rounded = Math.round((score || 0) * 2) / 2;
    const full = Math.floor(rounded);
    const half = rounded - full === 0.5;
    let html = '';
    for (let i = 0; i < full; i++) html += '<i class="fas fa-star"></i>';
    if (half) html += '<i class="fas fa-star-half-alt"></i>';
    for (let i = full + (half ? 1 : 0); i < 5; i++) html += '<i class="far fa-star"></i>';
    return html;
  }

  function renderPagination(m) {
    const ctrls = document.querySelector('.pagination-controls');
    if (!ctrls) return;
    if (m.total_pages <= 1) { ctrls.innerHTML = ''; return; }

    let html = '<button class="btn btn--page" data-page="' + (m.page - 1) + '"'
             + (m.page <= 1 ? ' disabled' : '') + '><i class="fas fa-chevron-left"></i></button>';
    for (let i = 1; i <= m.total_pages; i++) {
      html += '<button class="btn btn--page' + (i === m.page ? ' active' : '')
            + '" data-page="' + i + '">' + i + '</button>';
    }
    html += '<button class="btn btn--page" data-page="' + (m.page + 1) + '"'
          + (m.page >= m.total_pages ? ' disabled' : '') + '><i class="fas fa-chevron-right"></i></button>';
    ctrls.innerHTML = html;

    ctrls.querySelectorAll('[data-page]').forEach(btn => {
      btn.addEventListener('click', () => {
        if (btn.disabled) return;
        const p = parseInt(btn.dataset.page, 10);
        if (p >= 1 && p <= m.total_pages && p !== currentPage) {
          currentPage = p;
          load();
        }
      });
    });
  }
})();
