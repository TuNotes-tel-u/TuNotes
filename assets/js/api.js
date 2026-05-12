// api.js — wrapper fetch ringan untuk semua endpoint JSON.
// Auto-handle credentials (cookie session), auto-redirect ke login kalau 401.
// Cara pakai dari halaman /pages/*.html:
//   const { data } = await api.get('../api/notes.php?scope=public');
//   await api.postForm('../api/notes.php', formData);
//   await api.delete('../api/notes.php?id=' + id);

(function (global) {
  'use strict';

  // Lokasi halaman login dipakai untuk redirect 401.
  // Diasumsikan dipanggil dari halaman di /pages/, jadi pakai path relatif.
  const LOGIN_URL = 'login.html';

  async function call(method, url, options) {
    options = options || {};
    const init = {
      method,
      credentials: 'include',
      headers: Object.assign({}, options.headers || {}),
    };

    if (options.body !== undefined) {
      if (options.body instanceof FormData) {
        // JANGAN set Content-Type — browser perlu bikin boundary multipart sendiri.
        init.body = options.body;
      } else if (typeof options.body === 'string') {
        init.headers['Content-Type'] = init.headers['Content-Type'] || 'application/json';
        init.body = options.body;
      } else {
        init.headers['Content-Type'] = 'application/json';
        init.body = JSON.stringify(options.body);
      }
    }

    let res;
    try {
      res = await fetch(url, init);
    } catch (err) {
      return { ok: false, status: 0, data: null, networkError: err };
    }

    let data = null;
    try { data = await res.json(); } catch (_) { /* response bukan JSON */ }

    if (res.status === 401 && options.autoRedirect !== false) {
      const back = encodeURIComponent(window.location.pathname + window.location.search);
      window.location.href = LOGIN_URL + '?next=' + back;
      return { ok: false, status: 401, data };
    }

    return { ok: res.ok, status: res.status, data };
  }

  global.api = {
    get:      function (url, opts)        { return call('GET',    url, opts); },
    post:     function (url, body, opts)  { return call('POST',   url, Object.assign({}, opts || {}, { body: body })); },
    postForm: function (url, fd,   opts)  { return call('POST',   url, Object.assign({}, opts || {}, { body: fd })); },
    delete:   function (url, opts)        { return call('DELETE', url, opts); },
  };

  // Helper escape HTML untuk render aman.
  global.escapeHtml = function (str) {
    if (str == null) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  };

  // Format bytes → human readable (untuk file size).
  global.formatBytes = function (bytes) {
    if (!bytes) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return (bytes / Math.pow(k, i)).toFixed(i === 0 ? 0 : 1) + ' ' + sizes[i];
  };

  // Format tanggal ISO/DB → "Mar 15, 2024".
  global.formatDate = function (dbDate) {
    if (!dbDate) return '';
    const d = new Date(dbDate.replace ? dbDate.replace(' ', 'T') : dbDate);
    if (isNaN(d.getTime())) return '';
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();
  };

  // Cek sesi via GET /api/auth.php. Pakai untuk halaman wajib login.
  global.requireAuth = async function (options) {
    options = options || {};
    const { data, status } = await call('GET', '../api/auth.php', { autoRedirect: false });
    if (status === 200 && data && data.success) {
      return data.data; // user object
    }
    if (options.silent) return null;
    const back = encodeURIComponent(window.location.pathname + window.location.search);
    window.location.href = LOGIN_URL + '?next=' + back;
    return null;
  };
})(window);
