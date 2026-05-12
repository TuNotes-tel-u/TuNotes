// user-header.js — populate avatar + name di header semua halaman user.
// Pakai cara include: <script src="../assets/js/user-header.js"></script>
// SETELAH api.js (butuh api.get).
//
// HTML markup convention (tambahkan attribute ke element existing):
//   <img class="avatar" data-user-avatar>              ← avatar img, JS akan set src atau replace
//   <span data-user-name>...</span>                    ← nama user
//   <span class="badge" data-notif-badge>3</span>      ← notif badge, akan di-hide
//   <span class="badge" data-message-badge>2</span>    ← message badge, akan di-hide
//
// Kalau user belum login (401), function return diam — tidak redirect.
// Setiap halaman yang butuh redirect masih di-handle oleh requireAuth di page-specific JS.

(async function () {
  if (typeof api === 'undefined') return;
  let userResp;
  try {
    userResp = await api.get('../api/auth.php', { autoRedirect: false });
  } catch (_) { return; }
  if (!userResp || !userResp.data || !userResp.data.success) return;

  const user = userResp.data.data;
  const name = user.name || user.username || '';
  const username = user.username || '';

  // Update nama
  document.querySelectorAll('[data-user-name]').forEach(el => { el.textContent = name; });

  // Update avatar — kalau ada avatar_url pakai img, kalau tidak generate initials.
  document.querySelectorAll('[data-user-avatar]').forEach(el => {
    if (user.avatar_url) {
      if (el.tagName === 'IMG') {
        el.src = user.avatar_url;
        el.alt = name;
      } else {
        el.innerHTML = '<img src="' + escapeAttr(user.avatar_url) + '" alt="' + escapeAttr(name)
          + '" style="width:100%;height:100%;border-radius:50%;object-fit:cover;display:block;">';
      }
      return;
    }
    // Generate initial circle
    const initial = (name.trim().charAt(0) || '?').toUpperCase();
    const color = pickColor(username || name);
    const initialDiv = document.createElement('span');
    initialDiv.setAttribute('data-user-avatar', '');
    initialDiv.style.cssText = 'background:' + color + ';color:#fff;display:inline-flex;'
      + 'align-items:center;justify-content:center;font-weight:600;border-radius:50%;'
      + 'flex-shrink:0;text-transform:uppercase;letter-spacing:0.02em;';
    initialDiv.textContent = initial;
    // Copy size & className dari element existing
    if (el.tagName === 'IMG') {
      // Pakai computed style untuk dapat ukuran natural img-nya kalau CSS yang nentuin.
      const rect = el.getBoundingClientRect();
      const w = rect.width > 0 ? rect.width : 36;
      const h = rect.height > 0 ? rect.height : 36;
      initialDiv.style.width = w + 'px';
      initialDiv.style.height = h + 'px';
      initialDiv.style.fontSize = Math.max(11, Math.round(w * 0.42)) + 'px';
      if (el.className) initialDiv.className = el.className;
      el.replaceWith(initialDiv);
    } else {
      // Element wrapper (span/div) — set initial sebagai child, pertahankan ukuran CSS-nya.
      initialDiv.style.width = '100%';
      initialDiv.style.height = '100%';
      initialDiv.style.fontSize = '0.9rem';
      el.innerHTML = '';
      el.appendChild(initialDiv);
    }
  });

  // Hide notif & message badges (belum ada sistem notif real-time).
  document.querySelectorAll('[data-notif-badge], [data-message-badge]').forEach(el => {
    el.style.display = 'none';
  });

  function escapeAttr(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/"/g, '&quot;')
      .replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }
  function pickColor(seed) {
    const colors = ['#ef4444','#f59e0b','#10b981','#3b82f6','#8b5cf6','#ec4899','#06b6d4','#0891b2'];
    let h = 0;
    for (let i = 0; i < seed.length; i++) h = ((h << 5) - h + seed.charCodeAt(i)) | 0;
    return colors[Math.abs(h) % colors.length];
  }
})();
