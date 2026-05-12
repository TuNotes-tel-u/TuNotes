# TuNotes — Smarter Lecture Notes

Platform catatan akademik sosial untuk mahasiswa Indonesia. Gabungan **note management** (upload PDF/DOC/PPT) dengan **social interaction** (like, komentar, rating, sharing) dan **AI assistant** untuk ringkasan/agenda/analisis catatan. Mahasiswa juga bisa kelola **mata kuliah per semester** (Study List), **homework**, dan **Reading Board** Kanban untuk progres baca.

## Tech Stack

- **Backend:** PHP 8 **prosedural murni** (no framework). Akses DB via `mysqli` dengan **prepared statements** + `password_hash(PASSWORD_BCRYPT)` + session cookie httpOnly.
- **Database:** MySQL (utf8mb4 / InnoDB). Schema lengkap: 11 tabel.
- **Frontend user:** HTML5 + CSS3 + JavaScript vanilla + `fetch` API. Tidak pakai framework JS.
- **Frontend admin:** PHP **SSR monolithic** (HTML + PHP di satu file) dengan CSRF token.
- **AI Provider:** Google Gemini via REST (`gemini-1.5-flash`). Fallback ke **mock mode** otomatis kalau API key kosong — flow UI tetap kerja.
- **Server lokal:** XAMPP (Apache + MySQL + PHP 8) atau PHP built-in dev server.

## Arsitektur — Dual Approach

| Sisi | Pendekatan | Folder |
|---|---|---|
| **User** | API-driven (FE konsumsi JSON dari 6 endpoint) | `pages/`, `api/`, `assets/` |
| **Admin** | Monolithic SSR (PHP & HTML campur, CSRF, PRG pattern) | `admin/` |

## Setup (XAMPP / Laragon)

1. **Clone / copy** folder project ke `htdocs/` atau lokasi mana pun (project ini di-set fleksibel pakai `$_SERVER['SCRIPT_NAME']` auto-detect base URL).
2. **Import schema:** buka phpMyAdmin atau `mysql -u root` lalu jalankan `database/schema.sql`. DB bernama `webpro_tunotes` akan dibuat lengkap dengan seed admin.
3. **Optional — Gemini AI:** edit `config/ai_config.php` dan ganti `'api_key' => ''` jadi key kamu (atau set env var `GEMINI_API_KEY`). File ini sudah masuk `.gitignore`. **Kalau dilewat, AI tetap kerja via mock mode** (template response).
4. **Start server.** Pilih satu:
   - **XAMPP Apache:** mulai service → akses `http://localhost/Projectweb-TuNotes/`.
   - **PHP dev server:** `php -S 127.0.0.1:8080 -t .` → akses `http://127.0.0.1:8080/`.
5. **Login.** Akun seed super admin: `admin` / `admin123`. Atau register user biasa via UI.

`index.php` di root otomatis route: guest → Landing, user → Dashboard, admin/super_admin → Admin Dashboard.

## API Endpoints (6 endpoint, user side)

Semua endpoint balikin JSON dengan struktur konsisten `{success, message, data?, meta?, error_code?}`.

| Endpoint | Method | Fungsi |
|---|---|---|
| `/api/auth.php` | POST `?action=register/login` · GET (sesi atau `?view=profile`) · DELETE (logout) · POST `?action=update_profile` | Auth + profil user |
| `/api/notes.php` | GET (detail/list scope=public/mine/course_id + sort + pagination) · POST (upload multipart) · POST `?action=update` · DELETE · POST/DELETE `?action=rate` | CRUD catatan + rating |
| `/api/favorites.php` | GET (list user) · POST (toggle on) · DELETE (toggle off) | Manajemen like/favorit |
| `/api/comments.php` | GET (`?note_id=X`, tree 1-level) · POST (root/reply) · DELETE (soft jika ada reply / hard jika tidak) | Komentar threaded |
| `/api/study.php` | GET `?view=study_list/reading_board/course_detail` · POST `?action=add_*/update_*` · DELETE `?action=delete_*` | Semester, course, homework, reading board |
| `/api/ai.php` | POST (call AI + simpan) · GET (history + detail) · DELETE | Notes AI Assistant (rate-limited 30/jam) |

## Struktur Folder

```
Projectweb-TuNotes/
├── index.php                  ← router entry point
├── README.md
├── .gitignore
├── api/                       ← 6 endpoint JSON (sisi user)
│   ├── auth.php
│   ├── notes.php
│   ├── favorites.php
│   ├── comments.php
│   ├── study.php
│   └── ai.php
├── admin/                     ← Monolithic SSR (sisi admin)
│   ├── Dashboard-admin.php
│   ├── users.php · content.php · comments.php · analytics.php · notifications.php
│   ├── logout.php · index.php
│   └── includes/              ← auth_check.php · header.php · sidebar.php · footer.php · csrf.php
├── pages/                     ← 16 halaman HTML user
│   ├── LandingPage.html · login.html · register.html
│   ├── Dashboard.html · MyNotes.html · DetailMyNotes.html · UpdateNotes.html
│   ├── MyFavorites.html · UploadNotes.html · NotesAI.html
│   ├── NotesRead.html · StudyList.html · DetailList.html
│   ├── DetailCatatan.html · Profil.html
│   └── tambahan.html
├── assets/
│   ├── css/                   ← per-page stylesheet
│   ├── js/                    ← api.js · modal.js · user-header.js + 1 JS per halaman
│   └── images/                ← logo, ikon, gambar testimonial
├── config/
│   ├── db.php
│   ├── constants.php          ← auto-detect APP_BASE_URL
│   └── ai_config.php          ← GEMINI_API_KEY (gitignored)
├── helpers/                   ← response.php · auth_helper.php · upload_helper.php · note_formatter.php · admin_view.php · ai_client.php
├── database/
│   └── schema.sql             ← 11 tabel + seed super admin
├── uploads/                   ← file PDF/DOC user (gitignored)
└── _legacy/                   ← arsip backend lama dari sebelum Tahap 0
```

## Halaman User (12 fitur)

| Halaman | Fungsi |
|---|---|
| Landing Page | Hero, fitur, about, testimonial. Nav otomatis switch antara guest dan user yang sudah login. |
| Dashboard | Catatan publik (tab Terbaru/Populer/Rekomendasi AI), heart toggle, widget Progress Belajar (dari `localStorage.weeklyTarget`). |
| Detail Catatan | Preview, info uploader, like, komentar threaded + reply, rating bintang. |
| My Notes | Grid catatan milik user dengan Update/Delete/Unduh action. |
| Detail My Notes | Tabel dokumen dalam 1 mata kuliah. |
| Update / Upload Notes | Form CRUD catatan dengan dropzone. Upload mendukung pre-fill course_id dari Detail List. |
| Favorit | Grid catatan favorit, sort/pagination, unfavorite langsung dari card. |
| Notes AI | Form prompt + 4 quick action template, history dengan Edit (clipboard) / Simpan (.md) / Bagikan / Delete. |
| Notes Read | Kanban 3 kolom (Belum Dibaca / Sedang Dipelajari / Selesai), drag via dropdown, progress %, banner rekomendasi AI. Editor target mingguan di sini (single source of truth). |
| Study List | Daftar semester + course + summary stats. Modal Tambah Semester/Mata Kuliah. |
| Detail List | Header course + tab Notes & Homework + CRUD homework. |
| Profil | Form edit name/email/password + 4 stat card (notes/favorites/downloads/member since). |

## Modul Admin (5)

| Modul | CRUD | Highlight |
|---|---|---|
| Manajemen Pengguna | C/R/U/D | Tabel + filter status + search. Restriction self-delete + super_admin guard. |
| Moderasi Konten | R/D | List card per catatan, hard delete + unlink file. |
| Manajemen Komentar | R/D | Soft delete (content jadi `[Komentar dihapus oleh moderator]`). |
| Analitik | R | 4 stat (DAU/MAU/Notes/Retention) + 3 Chart.js (DAU/Trend Notes/MAU) + tabel Top Mata Kuliah. |
| Manajemen Notifikasi | C/R/U/D | Split 2 kolom (form / history). Tipe info/maintenance/academic/warning/success. |

Plus **Dashboard admin** dengan 4 quick stat + recent users + recent notes + quick links.

## Schema (`database/schema.sql`)

11 tabel: `users`, `semesters`, `courses`, `notes`, `ratings`, `favorites`, `comments`, `reading_progress`, `homework`, `ai_results`, `notifications`. Pakai FK dengan kombinasi `CASCADE` (relasi turunan) dan `SET NULL` (mis. `notes.course_id` saat course dihapus).

Seed admin di akhir file: `INSERT INTO users (username='admin', password=admin123 hashed)`.

## Security

- Semua query DB pakai `mysqli_prepare` + `bind_param` — **tidak ada concat string**.
- Password user pakai `password_hash(PASSWORD_BCRYPT, cost=12)`. Verifikasi `password_verify`.
- Session cookie `httpOnly` + `SameSite=Lax`, auto-`secure` di HTTPS, di-regenerate setelah login.
- Admin POST handler **wajib CSRF token** (`verify_csrf` + `hash_equals`).
- Upload file: validasi ekstensi + MIME via `finfo`, max 10 MB, nama random `bin2hex(random_bytes(16))`.
- AI request rate-limited 30/jam per user.
- API key Gemini tidak pernah di-log atau di-output ke client.

## Catatan

- `config/ai_config.php` sudah ter-gitignore. **Jangan commit API key.**
- `uploads/*` ter-gitignore (kecuali `.gitkeep` kalau ditambah).
- `_legacy/` arsip dari migrasi awal (backend lama yang punya SQL injection); jangan dipakai sebagai pola.
- Project di-bangun bertahap dalam 7 tahap (0–7), dimulai dari setup → auth → notes → favorit/komentar → study/reading → AI → admin → polishing.

— TuNotes 2026, dibangun untuk mata kuliah Pemrograman Web Semester 2.
