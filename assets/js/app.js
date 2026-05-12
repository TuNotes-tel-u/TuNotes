/* TuNotes Admin — Shared App Logic (sidebar, header, helpers, modal, toast, icons) */

const ICONS = {
  home:          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12l9-9 9 9"/><path d="M5 10v10h14V10"/></svg>',
  users:         '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
  fileText:      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg>',
  message:       '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
  barChart:      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6"  y1="20" x2="6"  y2="14"/></svg>',
  bell:          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
  logout:        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
  search:        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
  menu:          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6"  x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>',
  close:         '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
  eye:           '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>',
  edit:          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
  trash:         '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/></svg>',
  plus:          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
  check:         '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
  clock:         '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
  alertTriangle: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
  star:          '<svg viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
  download:      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
  flag:          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>',
  bot:           '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="10" rx="2"/><circle cx="12" cy="5" r="2"/><path d="M12 7v4"/><line x1="8" y1="16" x2="8" y2="16"/><line x1="16" y1="16" x2="16" y2="16"/></svg>',
  activity:      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>',
  trendingUp:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>',
  send:          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>',
  shield:        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
};

/* ---------- Helpers ---------- */
function escapeHtml(str) {
  return String(str ?? '').replace(/[&<>"']/g, m => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
  }[m]));
}
function initials(name) {
  return String(name || '?').trim().split(/\s+/).slice(0,2).map(s => s[0].toUpperCase()).join('');
}
function avatarClass(name) {
  const colors = ['avatar-blue','avatar-green','avatar-purple','avatar-red'];
  let h = 0; for (const ch of String(name||'')) h = (h * 31 + ch.charCodeAt(0)) >>> 0;
  return colors[h % colors.length];
}

/* ---------- Sidebar & Header ---------- */
const NAV = [
  { key: 'dashboard',     href: 'Dashboard-admin.html',         label: 'Dashboard',        icon: 'home',    section: 'Utama' },
  { key: 'users',         href: 'users.html',         label: 'Pengguna',         icon: 'users',   section: 'Manajemen' },
  { key: 'content',       href: 'content.html',       label: 'Konten',           icon: 'fileText',section: 'Manajemen' },
  { key: 'comments',      href: 'comments.html',      label: 'Komentar',         icon: 'message', section: 'Manajemen' },
  { key: 'analytics',     href: 'analytics.html',     label: 'Analitik',         icon: 'barChart',section: 'Wawasan' },
  { key: 'notifications', href: 'notifications.html', label: 'Notifikasi',       icon: 'bell',    section: 'Wawasan' },
];

function renderSidebar() {
  const sb = document.getElementById('sidebar');
  if (!sb) return;
  const active = document.body.dataset.page || 'dashboard';
  const groups = {};
  NAV.forEach(n => { (groups[n.section] = groups[n.section] || []).push(n); });
  let html = `
    <div class="sidebar-header">
      <div class="sidebar-logo">${ICONS.fileText}</div>
      <div>
        <div class="sidebar-title">TuNotes</div>
        <span class="sidebar-tag">ADMIN</span>
      </div>
      <button class="sidebar-close btn-icon" id="sbClose" aria-label="Tutup menu">${ICONS.close}</button>
    </div>
    <nav class="sidebar-nav">`;
  for (const sec of Object.keys(groups)) {
    html += `<div class="nav-section-label">${sec}</div>`;
    for (const n of groups[sec]) {
      html += `<a href="${n.href}" class="nav-item ${n.key===active?'active':''}">${ICONS[n.icon]}<span>${n.label}</span></a>`;
    }
  }
  html += `</nav>
    <div class="sidebar-footer">
      <a href="#" class="nav-item logout" onclick="event.preventDefault();showToast('Logout (demo)')">${ICONS.logout}<span>Keluar</span></a>
    </div>`;
  sb.innerHTML = html;
}

function renderHeader() {
  const h = document.getElementById('header');
  if (!h) return;
  h.innerHTML = `
    <button class="hamburger btn-icon" id="hamburger" aria-label="Buka menu">${ICONS.menu}</button>
    <div class="search-box">
      ${ICONS.search}
      <input type="search" placeholder="Cari di TuNotes Admin..." aria-label="Pencarian global" />
    </div>
    <div class="header-right">
      <button class="header-bell" aria-label="Notifikasi">${ICONS.bell}<span class="dot"></span></button>
      <button class="header-profile" aria-label="Profil admin">
        <div class="header-avatar">LP</div>
        <div class="header-profile-info">
          <div class="header-profile-name">Lani Pratiwi</div>
          <div class="header-profile-role">Super Admin</div>
        </div>
      </button>
    </div>`;
}

/* ---------- Modal ---------- */
function openModal({ title, body, footer }) {
  closeModal();
  const overlay = document.createElement('div');
  overlay.className = 'modal-overlay show';
  overlay.id = '__modal';
  overlay.innerHTML = `
    <div class="modal" role="dialog" aria-modal="true">
      <div class="modal-header">
        <div class="modal-title">${title}</div>
        <button class="btn-icon" onclick="closeModal()" aria-label="Tutup">${ICONS.close}</button>
      </div>
      <div class="modal-body">${body}</div>
      ${footer ? `<div class="modal-footer">${footer}</div>` : ''}
    </div>`;
  overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(); });
  document.body.appendChild(overlay);
}
function closeModal() {
  const m = document.getElementById('__modal');
  if (m) m.remove();
}

/* ---------- Toast ---------- */
let _toastTimer;
function showToast(msg) {
  let t = document.getElementById('__toast');
  if (!t) {
    t = document.createElement('div');
    t.className = 'toast';
    t.id = '__toast';
    document.body.appendChild(t);
  }
  t.textContent = msg;
  requestAnimationFrame(() => t.classList.add('show'));
  clearTimeout(_toastTimer);
  _toastTimer = setTimeout(() => t.classList.remove('show'), 2400);
}

/* ---------- Boot ---------- */
document.addEventListener('DOMContentLoaded', () => {
  renderSidebar();
  renderHeader();

  // mobile drawer
  const overlay = document.createElement('div');
  overlay.className = 'sidebar-overlay';
  document.body.appendChild(overlay);
  const sb = document.getElementById('sidebar');
  document.addEventListener('click', e => {
    if (e.target.closest('#hamburger')) { sb.classList.add('open'); overlay.classList.add('show'); }
    if (e.target.closest('#sbClose') || e.target === overlay) { sb.classList.remove('open'); overlay.classList.remove('show'); }
  });

  // ESC closes modal
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
});
