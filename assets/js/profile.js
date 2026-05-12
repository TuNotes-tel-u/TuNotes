// profile.js — halaman Profil: populate dari /api/auth.php?view=profile +
// submit edit name/email/password via POST ?action=update_profile.
// Bio: skip (kolom belum ada di schema; flag minor — tidak break).

(async function () {
  const user = await requireAuth();
  if (!user) return;

  const res = await api.get('../api/auth.php?view=profile');
  if (!res.data || !res.data.success) {
    await showAlertModal({ type: 'error', message: 'Gagal memuat profil.' });
    return;
  }
  const u = res.data.data.user;
  const stats = res.data.data.stats || {};

  // Populate form
  const nameEl = document.getElementById('profileName');
  if (nameEl) nameEl.value = u.name || '';
  const usernameEl = document.getElementById('profileUsername');
  if (usernameEl) { usernameEl.value = u.username || ''; usernameEl.readOnly = true; }
  const emailEl = document.getElementById('profileEmail');
  if (emailEl) emailEl.value = u.email || '';
  const bioEl = document.getElementById('profileBio');
  if (bioEl) {
    bioEl.value = '';
    bioEl.placeholder = 'Fitur bio belum tersedia (schema users belum punya kolom bio).';
    bioEl.disabled = true;
  }

  // Avatar di kartu kiri
  setProfileAvatar(u);

  // Stat cards
  setText('statTotalNotes',  stats.total_notes  != null ? stats.total_notes  : 0);
  setText('statFavorites',   stats.favorites    != null ? stats.favorites    : 0);
  setText('statDownloads',   stats.downloads    != null ? stats.downloads    : 0);
  setText('statMemberSince', stats.member_since || '—');

  // Tombol Save Changes
  const form = document.querySelector('.profile-form');
  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const payload = {
        name:  (document.getElementById('profileName') || {}).value || '',
        email: (document.getElementById('profileEmail') || {}).value || '',
      };
      const newPw = (document.getElementById('profilePassword') || {}).value || '';
      if (newPw !== '') {
        const cur = await showFormModal({
          title: 'Konfirmasi Ganti Password',
          message: 'Untuk keamanan, masukkan password saat ini.',
          fields: [
            { name: 'current_password', label: 'Password saat ini', type: 'text', required: true },
          ],
          submitLabel: 'Lanjutkan',
        });
        if (!cur) return;
        payload.current_password = cur.current_password;
        payload.new_password = newPw;
      }

      const btn = form.querySelector('.save-btn');
      if (btn) { btn.disabled = true; btn.textContent = 'Menyimpan…'; }
      const r = await api.post('../api/auth.php?action=update_profile', payload);
      if (btn) { btn.disabled = false; btn.innerHTML = '<i class="ai-save"></i> Save Changes'; }

      if (r.data && r.data.success) {
        await showAlertModal({ type: 'success', title: 'Tersimpan', message: 'Profil berhasil diperbarui.' });
        // Refresh stats + form (kalau ada perubahan email/name)
        location.reload();
      } else {
        let msg = (r.data && r.data.message) || 'Gagal menyimpan.';
        if (r.data && r.data.details) {
          msg += '\n' + Object.values(r.data.details).join('\n');
        }
        await showAlertModal({ type: 'error', title: 'Gagal', message: msg });
      }
    });
  }

  // Change Photo — defer
  const changePhotoLink = document.querySelector('.change-photo-link');
  if (changePhotoLink) {
    changePhotoLink.addEventListener('click', async (e) => {
      e.preventDefault();
      await showAlertModal({
        type: 'info',
        title: 'Belum Tersedia',
        message: 'Fitur upload foto profil belum tersedia. Coming soon!',
      });
    });
  }
})();

function setProfileAvatar(user) {
  const wrap = document.querySelector('.avatar-container');
  if (!wrap) return;
  const img = wrap.querySelector('img');
  if (!img) return;
  if (user.avatar_url) {
    img.src = user.avatar_url;
    img.alt = user.name || '';
    return;
  }
  // Generate inisial circle besar
  const name = user.name || user.username || '?';
  const initial = name.trim().charAt(0).toUpperCase();
  const colors = ['#ef4444','#f59e0b','#10b981','#3b82f6','#8b5cf6','#ec4899','#06b6d4','#0891b2'];
  const seed = user.username || name;
  let h = 0;
  for (let i = 0; i < seed.length; i++) h = ((h << 5) - h + seed.charCodeAt(i)) | 0;
  const color = colors[Math.abs(h) % colors.length];

  const div = document.createElement('div');
  div.style.cssText = 'width:120px;height:120px;border-radius:50%;background:' + color
    + ';color:#fff;display:flex;align-items:center;justify-content:center;'
    + 'font-size:3rem;font-weight:700;';
  div.textContent = initial;
  img.replaceWith(div);
}

function setText(id, v) {
  const el = document.getElementById(id);
  if (el) el.textContent = String(v);
}
