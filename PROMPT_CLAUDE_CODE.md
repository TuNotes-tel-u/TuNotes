# PROMPT UTAMA — PROYEK TuNotes (Untuk Claude Code di VS Code)

> Salin seluruh isi file ini ke chat Claude Code pada VS Code sebagai pesan pertama. Prompt ini sudah dioptimalkan untuk memberi Claude konteks penuh: tujuan produk, fitur, arsitektur, ketentuan teknis, distribusi endpoint, dan standar kualitas kode.

---

## 1. PERAN & TUJUAN KAMU (CLAUDE)

Halo Claude. Kamu akan berperan sebagai **Senior Full-Stack Engineer + Software Architect** yang membantu saya membangun aplikasi **TuNotes** dari nol hingga selesai. Kamu wajib:

1. **Memahami konteks dulu sebelum menulis kode** — baca seluruh prompt ini sampai habis, lalu konfirmasi pemahaman kamu dalam bentuk ringkasan singkat (maksimal 1 paragraf) sebelum kita mulai coding.
2. **Tidak menulis kode dummy/placeholder** kecuali saya minta. Setiap fungsi harus benar-benar bekerja dan terhubung dengan database.
3. **Mengikuti konvensi penamaan, struktur folder, dan arsitektur** yang sudah saya tetapkan di bawah — JANGAN improvisasi struktur tanpa diskusi.
4. **Bertanya dulu** jika ada ambiguitas — lebih baik klarifikasi 10 detik daripada salah arah 1 jam.
5. **Memberi penjelasan berbobot** di setiap perubahan besar: kenapa pilih pendekatan ini, apa trade-off-nya, dan apa dampaknya ke fitur lain.

---

## 2. APA ITU TuNotes?

**TuNotes** adalah platform catatan akademik berbasis sosial untuk mahasiswa Indonesia. Konsepnya menggabungkan **note management** (seperti Notion) dengan **social interaction** (seperti media sosial):

- Setiap pengguna bisa **mengunggah catatan kuliah** (PDF, DOC, DOCX, TXT, PPT, PPTX) lengkap dengan nama mata kuliah, dosen, tag, kategori, dan deskripsi.
- Catatan yang ditandai **public** bisa **dilihat, di-like (favorit), diunduh, dan dikomentari** oleh user lain.
- Ada fitur **AI Assistant** untuk merangkum, menganalisis, dan brainstorming dari catatan yang ada.
- Ada fitur **Study List** untuk mengelompokkan mata kuliah per semester.
- Ada **Reading Board** (Kanban-style) untuk melacak progress baca catatan: Belum Dibaca → Sedang Dipelajari → Selesai.
- Ada **Dashboard Admin** untuk moderasi konten, manajemen user, dan analitik.

Target user: mahasiswa Indonesia yang ingin **belajar lebih cerdas, kolaboratif, dan efisien**.

---

## 3. DAFTAR LENGKAP FITUR (12 HALAMAN USER + 5 MODUL ADMIN)

### A. HALAMAN ROLE USER (12 fitur)

| No | Halaman | Fungsi Inti |
|----|---------|-------------|
| 1 | **Landing Page** | Halaman pengenalan publik — hero "Lecture Notes, Smarter", section Why TuNotes, About, Testimonials, Footer. Tombol Login/Register. |
| 2 | **Dashboard / Home** | Halaman utama setelah login. Greeting user, progress belajar mingguan, daftar catatan publik (Terbaru, Populer, Rekomendasi AI), dengan card berisi mata kuliah, dosen, rating, jumlah download, tanggal, format file. |
| 3 | **Detail Catatan** | Halaman preview satu catatan: preview PDF, AI Summary, info uploader, tanggal, kolom komentar dengan threading, tombol download/like/share. |
| 4 | **My Notes** | Daftar grid catatan **milik user yang login**, dikelompokkan per mata kuliah. Filter status & tanggal, search bar. |
| 5 | **Detail My Notes** | Tabel semua dokumen di dalam SATU mata kuliah. Kolom: Document Title, Lecturer, Upload Date, Actions (Update, Delete, Unduh). Ada pagination. |
| 6 | **Update Notes** | Form edit catatan: Course Name, Lecturer Name, Tags (chip multi-input), Category dropdown, file replacement (drag & drop), Description textarea, tombol Batalkan / Simpan Perubahan. |
| 7 | **Favorit** | Daftar catatan yang di-like user. Toggle view (grid/list), sort by Date Added/Rating/Pages, pagination. Card berisi judul, mata kuliah, dosen, rating, jumlah halaman, ringkasan singkat. |
| 8 | **Upload Notes** | Form upload catatan baru: Course Name, Lecturer Name, Tags, Category, file drop zone (PDF/DOC/DOCX/TXT/PPT/PPTX, max 10MB), description, tombol Upload Notes. |
| 9 | **Notes AI** | Asisten Belajar Cerdas. Text area prompt, mode AI (Otomatis/Penelitian/Semua sumber), checkbox izin pengeditan hasil AI, 4 quick actions (Tulis Agenda Kuliah, Analisis PDF, Buat Pelacak Tugas, What's New), riwayat Hasil AI dengan tombol Edit/Simpan/Bagikan. |
| 10 | **Notes Read** | **Kanban Board** dengan 3 kolom: Belum Dibaca, Sedang Dipelajari, Selesai. Setiap card menampilkan judul, dosen, tag, rating, progress bar %. Ada Search, filter Mata Kuliah, statistik (Total/Selesai/Target Minggu Ini), tombol Add Reading Notes, dan banner Rekomendasi AI. |
| 11 | **Study List** | Halaman semester. Setiap semester (mis. 2024 Fall, 2024 Spring) berisi card mata kuliah dengan jumlah catatan tersedia + tombol Lihat Catatan. Ada ringkasan progress (Total Mata Kuliah, Total Catatan, Semester Aktif), dropdown ganti semester, tombol Tambah Semester Baru. |
| 12 | **Detail List (Detail Mata Kuliah)** | Header mata kuliah (kode, semester, instruktur, lokasi, start date, link, status Active). 2 tab: **Notes** (daftar catatan dengan tanggal & deskripsi) dan **Homework** (daftar tugas). Tombol Add Note dan Edit Course Info. |

### B. DASHBOARD ADMIN (5 modul)

| No | Modul | Operasi CRUD | Deskripsi |
|----|-------|--------------|-----------|
| 1 | **Manajemen Pengguna** | C, R, U, D | Kelola data profil, role (User / Admin / Super Admin), status akun (Aktif / Ditangguhkan / Tidak Aktif), jumlah catatan, last active. UI: tabel + tombol Tambah Pengguna + filter status (Semua/Aktif/Ditangguhkan/Tidak Aktif). |
| 2 | **Moderasi Konten Publik** | R, D | Pantau catatan publik, jumlah unduhan, rating bintang. Admin bisa kurasi materi populer dan menghapus catatan yang melanggar aturan. |
| 3 | **Manajemen Komentar** | R, D | Pantau kolom diskusi untuk cegah spam/pelecehan. Admin baca semua komentar dan hapus yang tidak sesuai standar. |
| 4 | **Analitik Keberhasilan** | R (Read-only) | Visualisasi grafik: pengguna aktif harian/bulanan, total catatan tersimpan, top categories, growth chart. |
| 5 | **Manajemen Notifikasi** | C, R, U, D | Atur pengiriman push notification massal terkait pemeliharaan sistem atau info akademik. |

---

## 4. STACK TEKNOLOGI (WAJIB DIPATUHI)

- **Bahasa Backend:** PHP **prosedural murni** (TIDAK pakai framework seperti Laravel/CodeIgniter, TIDAK pakai OOP berat — fungsi-fungsi prosedural saja).
- **Database:** MySQL (akses via `mysqli` atau `PDO` — pilih SATU dan konsisten; saya pilih **`mysqli` dengan prepared statements** untuk keamanan).
- **Frontend User:** HTML5, CSS3 (boleh Tailwind CDN atau custom), JavaScript Vanilla / fetch API untuk konsumsi JSON. **TIDAK pakai React/Vue/framework JS berat**.
- **Frontend Admin:** HTML + PHP server-side rendering murni (satu file campur HTML & PHP).
- **Server lokal:** XAMPP / Laragon (Apache + MySQL + PHP 8+).
- **Format komunikasi API:** `application/json` (request & response).
- **Autentikasi:** PHP Sessions + password hashing dengan `password_hash()` & `password_verify()`. Untuk endpoint API, tambahkan token sederhana (session token disimpan di cookie httpOnly).

---

## 5. ARSITEKTUR — DUAL APPROACH

Aplikasi ini memakai **dua pendekatan arsitektur yang dipisah berdasarkan role**:

### 5.1 ARSITEKTUR ADMIN — Monolithic (Server-Side Rendering Murni)
- **Pendekatan:** Full PHP. HTML dan logika PHP ditulis di satu file.
- **Front-end & Back-end TIDAK dipisah.**
- **Contoh struktur file:**
  ```
  /admin/users.php       ← satu file: query DB + render tabel HTML
  /admin/content.php
  /admin/comments.php
  /admin/analytics.php
  /admin/notifications.php
  ```
- **Alasan dipilih:** Admin panel butuh cepat dibangun, jarang diakses (hanya admin), dan SEO tidak relevan. SSR murni lebih sederhana untuk operasi CRUD internal.

### 5.2 ARSITEKTUR USER — API-Driven (Pemisahan Front-end & Back-end)
- **Pendekatan:** Front-end (HTML/CSS/JS) **mengonsumsi data JSON** dari endpoint backend PHP.
- **Struktur:**
  ```
  /api/             ← endpoint PHP yang return JSON
    auth.php
    notes.php
    favorites.php
    comments.php
    study.php
    ai.php
  /public/          ← halaman HTML untuk user
    index.html              (Landing)
    dashboard.html
    detail.html
    my-notes.html
    favorit.html
    upload.html
    notes-ai.html
    notes-read.html
    study-list.html
    detail-list.html
  /assets/
    css/, js/, img/
  ```
- **Alasan dipilih:** Sisi user butuh UX dinamis (filter, search, infinite scroll, real-time AI chat). Pemisahan FE/BE memudahkan testing endpoint dan suatu saat bisa diganti ke mobile app tanpa rewrite backend.

---

## 6. ANALISIS 6 ENDPOINT API (SISI USER)

Karena ketentuan proyek mensyaratkan **6 endpoint mandiri, masing-masing minimal 2 method (dari GET/POST/PUT/DELETE)**, berikut rekomendasi yang **menutup 12 fitur user** dengan efisien:

### Endpoint #1 — `/api/auth.php` (Autentikasi & Sesi)
| Method | Fungsi |
|--------|--------|
| `POST` | Register & Login (cek email/password, buat session). |
| `DELETE` | Logout (destroy session). |
| `GET` | Cek status sesi aktif & ambil data user yang login. |

**Mendukung halaman:** Landing Page (login/register), semua halaman butuh session check.

### Endpoint #2 — `/api/notes.php` (CRUD Catatan Inti)
| Method | Fungsi |
|--------|--------|
| `GET` | Ambil daftar catatan (semua publik untuk Dashboard, atau filter `?user_id=me` untuk My Notes, atau `?id=123` untuk Detail Catatan). |
| `POST` | Upload catatan baru (multipart untuk file + JSON metadata). |
| `PUT` | Update catatan (Course Name, Lecturer, Tags, Category, Description, ganti file). |
| `DELETE` | Hapus catatan milik sendiri. |

**Mendukung halaman:** Dashboard, Detail Catatan, My Notes, Detail My Notes, Update Notes, Upload Notes.

### Endpoint #3 — `/api/favorites.php` (Manajemen Like / Favorit)
| Method | Fungsi |
|--------|--------|
| `GET` | Ambil semua catatan favorit milik user yang login (untuk halaman Favorit). |
| `POST` | Tambah catatan ke favorit (toggle on). |
| `DELETE` | Hapus dari favorit (toggle off). |

**Mendukung halaman:** Favorit, dan tombol heart di Dashboard/Detail Catatan.

### Endpoint #4 — `/api/comments.php` (Komentar pada Catatan)
| Method | Fungsi |
|--------|--------|
| `GET` | Ambil semua komentar untuk satu catatan (`?note_id=...`). |
| `POST` | Tambah komentar baru atau reply. |
| `DELETE` | Hapus komentar milik sendiri. |

**Mendukung halaman:** Detail Catatan.

### Endpoint #5 — `/api/study.php` (Study List, Reading Board, Detail Mata Kuliah)
| Method | Fungsi |
|--------|--------|
| `GET` | Ambil daftar semester + mata kuliah + Kanban status reading (`?view=study_list` / `?view=reading_board` / `?course_id=...`). |
| `POST` | Tambah semester baru, mata kuliah baru, atau tambahkan catatan ke reading board. |
| `PUT` | Update progress reading (Belum Dibaca → Sedang Dipelajari → Selesai), update info mata kuliah. |
| `DELETE` | Hapus mata kuliah / hapus catatan dari reading board. |

**Mendukung halaman:** Study List, Detail List, Notes Read.

### Endpoint #6 — `/api/ai.php` (Notes AI Assistant)
| Method | Fungsi |
|--------|--------|
| `POST` | Kirim prompt user + konteks catatan, simpan hasil AI ke DB. (Untuk versi awal bisa pakai mock response atau integrasi Anthropic/OpenAI API). |
| `GET` | Ambil riwayat hasil AI milik user (Hasil AI section). |
| `DELETE` | Hapus entri hasil AI. |

**Mendukung halaman:** Notes AI.

> **Catatan strategis:** Dengan 6 endpoint ini, **semua 12 halaman user tercover** dan tidak ada redundansi. Setiap endpoint mengikuti prinsip **resource-oriented** (satu endpoint = satu domain data), bukan action-oriented (anti-pattern: `/api/getNotes.php`, `/api/deleteNote.php`, dll).

---

## 7. ANALISIS CRUD MANAJEMEN PENGGUNA (ADMIN)

Karena Admin pakai arsitektur Monolithic, CRUD ini **tidak via API JSON**, melainkan form submit klasik:

| Operasi | File | Detail |
|---------|------|--------|
| **CREATE** | `/admin/users_create.php` | Form input: nama, email, password (di-hash), role (user/admin/super_admin), status awal. Submit → INSERT INTO users. |
| **READ (list)** | `/admin/users.php` | Tabel dengan kolom: Pengguna, Email, Role, Status, Catatan, Terakhir Aktif, Aksi. Search by name/email. Filter: Semua / Aktif / Ditangguhkan / Tidak Aktif. Statistik card di atas. |
| **READ (detail)** | `/admin/users_view.php?id=X` | Detail satu user + log aktivitas. |
| **UPDATE** | `/admin/users_edit.php?id=X` | Form edit role, status akun, reset password. Submit → UPDATE users WHERE id=X. |
| **DELETE** | `/admin/users_delete.php?id=X` | Konfirmasi modal → DELETE FROM users WHERE id=X. Soft-delete recommended (status='deleted'). |

**Skema tabel `users` yang disarankan:**
```sql
id INT PK AUTO_INCREMENT
name VARCHAR(100)
email VARCHAR(150) UNIQUE
password_hash VARCHAR(255)
role ENUM('user','admin','super_admin') DEFAULT 'user'
status ENUM('active','suspended','inactive') DEFAULT 'active'
avatar_url VARCHAR(255) NULL
notes_count INT DEFAULT 0
last_active_at DATETIME NULL
created_at DATETIME DEFAULT CURRENT_TIMESTAMP
updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP
```

---

## 8. STRUKTUR FOLDER LENGKAP YANG SAYA INGINKAN

```
Projectweb-TuNotes/
├── admin/                          ← Monolithic SSR
│   ├── includes/
│   │   ├── header.php
│   │   ├── sidebar.php
│   │   ├── footer.php
│   │   └── auth_check.php          ← guard untuk role admin
│   ├── dashboard.php
│   ├── users.php                   ← list
│   ├── users_create.php
│   ├── users_edit.php
│   ├── users_delete.php
│   ├── content.php                 ← moderasi
│   ├── comments.php
│   ├── analytics.php
│   └── notifications.php
│
├── api/                            ← 6 endpoint JSON
│   ├── auth.php
│   ├── notes.php
│   ├── favorites.php
│   ├── comments.php
│   ├── study.php
│   └── ai.php
│
├── public/                         ← halaman user (HTML + JS fetch)
│   ├── index.html                  (Landing)
│   ├── login.html
│   ├── register.html
│   ├── dashboard.html
│   ├── detail.html
│   ├── my-notes.html
│   ├── detail-my-notes.html
│   ├── update-notes.html
│   ├── favorit.html
│   ├── upload.html
│   ├── notes-ai.html
│   ├── notes-read.html
│   ├── study-list.html
│   └── detail-list.html
│
├── assets/
│   ├── css/
│   ├── js/
│   │   ├── api.js                  ← wrapper fetch()
│   │   ├── auth.js
│   │   └── (per-page).js
│   └── img/
│
├── config/
│   ├── db.php                      ← koneksi mysqli
│   └── constants.php
│
├── helpers/
│   ├── response.php                ← function jsonResponse($data, $code)
│   ├── auth_helper.php             ← session helpers
│   ├── validation.php
│   └── upload_helper.php
│
├── uploads/                        ← file catatan user (gitignore)
│
├── database/
│   └── schema.sql                  ← seluruh CREATE TABLE
│
├── .htaccess
├── index.php                       ← redirect ke /public/index.html atau dashboard
└── README.md
```

---

## 9. KETENTUAN KODE & STANDAR KUALITAS

1. **Keamanan WAJIB:**
   - Semua query DB pakai **prepared statements** (`mysqli_prepare` + `bind_param`). Tidak boleh concat string query.
   - Password pakai `password_hash(PASSWORD_BCRYPT)` dan `password_verify`.
   - Validasi & sanitasi semua input (`filter_var`, `htmlspecialchars` saat output).
   - File upload: cek MIME type, ekstensi, ukuran (max 10MB), rename ke nama random (`bin2hex(random_bytes(16))`).
   - CSRF token untuk form Admin.
   - Session regenerate setelah login (`session_regenerate_id(true)`).

2. **API Response Format konsisten:**
   ```json
   {
     "success": true,
     "message": "Notes fetched successfully",
     "data": [...],
     "meta": { "page": 1, "total": 24 }
   }
   ```
   Jika error:
   ```json
   {
     "success": false,
     "message": "Unauthorized",
     "error_code": "AUTH_REQUIRED"
   }
   ```

3. **Naming convention:**
   - File PHP: `snake_case.php`
   - Variabel & function PHP: `snake_case`
   - Class CSS: `kebab-case`
   - Variabel JS: `camelCase`
   - Tabel DB: plural `users`, `notes`, `favorites`, `comments`
   - Kolom DB: `snake_case`

4. **Komentar di kode:** Tulis komentar **dalam Bahasa Indonesia** di bagian yang non-obvious (kenapa, bukan apa). Skip komentar untuk kode yang sudah jelas.

5. **Error handling:** Jangan biarkan PHP warning bocor ke output JSON. Pakai `try/catch` (untuk PDO) atau cek return value (untuk mysqli) lalu return JSON error yang rapi.

---

## 10. RENCANA EKSEKUSI BERTAHAP (URUTAN MEMBANGUN)

Saya minta kamu **eksekusi proyek ini bertahap, JANGAN langsung semua sekaligus**. Urutannya:

1. **Tahap 0 — Setup**
   - Buat struktur folder
   - Buat `config/db.php`
   - Buat `database/schema.sql` (semua tabel)
   - Buat `helpers/response.php`, `helpers/auth_helper.php`

2. **Tahap 1 — Autentikasi**
   - `/api/auth.php` (register, login, logout, session check)
   - Halaman `login.html`, `register.html`
   - Tunjukkan saya dulu, baru lanjut

3. **Tahap 2 — Notes CRUD (jantung aplikasi)**
   - `/api/notes.php` lengkap (GET/POST/PUT/DELETE)
   - Halaman: Dashboard, My Notes, Detail My Notes, Upload, Update, Detail Catatan
   - Tunjukkan, review bareng

4. **Tahap 3 — Favorit & Komentar**
   - `/api/favorites.php`, `/api/comments.php`
   - Wire ke Dashboard & Detail Catatan
   - Halaman Favorit

5. **Tahap 4 — Study List & Notes Read**
   - `/api/study.php`
   - Halaman Study List, Detail List, Notes Read (Kanban)

6. **Tahap 5 — Notes AI**
   - `/api/ai.php` (boleh mock dulu, real integration belakangan)
   - Halaman Notes AI

7. **Tahap 6 — Admin Panel (Monolithic)**
   - Auth guard admin
   - Manajemen Pengguna (full CRUD)
   - Moderasi Konten, Komentar, Analitik, Notifikasi

8. **Tahap 7 — Polishing**
   - Landing Page
   - Validasi end-to-end
   - Testing flow

---

## 11. ATURAN INTERAKSI KITA

- Setiap kali kamu **menyelesaikan satu tahap**, **berhenti** dan tanya saya: "Tahap X selesai, file yang dibuat: A, B, C. Mau lanjut Tahap X+1, atau ada revisi?"
- Jika kamu **menemukan ambiguitas** (mis. saya belum jelaskan field DB tertentu), **tanya dulu**, jangan asumsikan.
- Jika ada **trade-off teknis** (mis. simpan tag sebagai JSON column vs tabel terpisah), **jelaskan opsinya** lalu kasih rekomendasi kamu, biar saya yang putuskan.
- Jangan tulis kode **lebih dari yang diminta di tahap saat ini**. Fokus.
- Saat menulis SQL, gabungkan ke file `database/schema.sql` agar saya bisa import sekali jalan.

---

## 12. INSTRUKSI PERTAMA UNTUK KAMU

Setelah kamu baca seluruh prompt ini:

1. **Ringkas pemahaman kamu** dalam 1 paragraf — apa yang akan kita bangun, stack-nya apa, arsitekturnya bagaimana.
2. **Sebutkan 3 pertanyaan klarifikasi paling penting** sebelum kita mulai Tahap 0 (mis. soal field DB, soal integrasi AI, soal hosting upload file, dll).
3. **Tunggu konfirmasi saya** sebelum menulis kode apa pun.

Setelah saya jawab, kita mulai **Tahap 0 — Setup**.

---

**Catatan akhir:** Tone kerja saya — saya suka kerja iteratif, tidak suka over-engineering. Lebih baik kita rilis Tahap 1 yang jalan sempurna daripada Tahap 1-5 yang setengah jadi. Mari mulai. Terima kasih, Claude.
