// auth.js — handle login & register via /api/auth.php
// Dipakai di pages/login.html dan pages/register.html.

(function () {
  'use strict';

  const AUTH_ENDPOINT = '../api/auth.php';

  async function postJson(url, body) {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(body),
    });
    let data = null;
    try { data = await res.json(); } catch (_) { /* response bukan JSON valid */ }
    return { ok: res.ok, status: res.status, data };
  }

  function ensureErrorBox(form) {
    let box = form.querySelector('.form-error');
    if (!box) {
      box = document.createElement('div');
      box.className = 'form-error';
      box.setAttribute('role', 'alert');
      box.style.cssText =
        'color:#b91c1c;background:#fef2f2;border:1px solid #fecaca;' +
        'padding:0.6rem 0.9rem;border-radius:6px;margin:0.75rem 0;' +
        'font-size:0.9rem;line-height:1.4;display:none;';
      form.insertBefore(box, form.firstChild);
    }
    return box;
  }

  function showError(form, message) {
    const box = ensureErrorBox(form);
    box.textContent = message;
    box.style.display = 'block';
  }

  function clearError(form) {
    const box = form.querySelector('.form-error');
    if (box) {
      box.style.display = 'none';
      box.textContent = '';
    }
  }

  function setLoading(form, loading, fallbackLabel) {
    const btn = form.querySelector('button[type="submit"]');
    if (!btn) return;
    if (loading) {
      btn.dataset.originalText = btn.textContent;
      btn.disabled = true;
      btn.textContent = 'Memproses…';
    } else {
      btn.disabled = false;
      btn.textContent = btn.dataset.originalText || fallbackLabel || 'Submit';
    }
  }

  function redirectAfterAuth(user) {
    const role = user && user.role;
    if (role === 'admin' || role === 'super_admin') {
      window.location.href = '../admin/Dashboard-admin.php';
    } else {
      window.location.href = 'Dashboard.html';
    }
  }

  // ---------------------------------------------------------------------------
  // LOGIN
  // ---------------------------------------------------------------------------
  const loginForm = document.querySelector('#loginForm, form.login-form');
  if (loginForm) {
    loginForm.addEventListener('submit', async function (e) {
      e.preventDefault();
      clearError(loginForm);

      const fd = new FormData(loginForm);
      const identifier = String(fd.get('identifier') || fd.get('email') || '').trim();
      const password = String(fd.get('password') || '');

      if (!identifier || !password) {
        showError(loginForm, 'Email/Username dan password wajib diisi.');
        return;
      }

      setLoading(loginForm, true);
      const { data } = await postJson(AUTH_ENDPOINT + '?action=login', { identifier, password });

      if (data && data.success) {
        redirectAfterAuth(data.data);
      } else {
        showError(loginForm, (data && data.message) || 'Login gagal. Coba lagi.');
        setLoading(loginForm, false, 'Sign in');
      }
    });
  }

  // ---------------------------------------------------------------------------
  // REGISTER
  // ---------------------------------------------------------------------------
  const registerForm = document.querySelector('#registerForm, form.auth-form');
  if (registerForm) {
    registerForm.addEventListener('submit', async function (e) {
      e.preventDefault();
      clearError(registerForm);

      const fd = new FormData(registerForm);
      const name = String(fd.get('name') || fd.get('nama_lengkap') || '').trim();
      const username = String(fd.get('username') || '').trim();
      const email = String(fd.get('email') || '').trim();
      const password = String(fd.get('password') || '');

      if (!name || !username || !email || !password) {
        showError(registerForm, 'Semua field wajib diisi.');
        return;
      }
      if (password.length < 8) {
        showError(registerForm, 'Password minimal 8 karakter.');
        return;
      }

      setLoading(registerForm, true);
      const { data } = await postJson(AUTH_ENDPOINT + '?action=register', {
        name, username, email, password,
      });

      if (data && data.success) {
        redirectAfterAuth(data.data);
      } else {
        let msg = (data && data.message) || 'Registrasi gagal.';
        // Tampilkan detail validasi per field kalau ada.
        if (data && data.details && typeof data.details === 'object') {
          const lines = Object.values(data.details).filter(Boolean);
          if (lines.length) msg += '\n' + lines.join('\n');
        }
        showError(registerForm, msg);
        setLoading(registerForm, false, 'Register Now');
      }
    });
  }
})();
