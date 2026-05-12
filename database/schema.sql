-- ============================================================================
-- TuNotes — Schema database lengkap
-- ----------------------------------------------------------------------------
-- Cara pakai:
--   1. Buka phpMyAdmin di http://localhost/phpmyadmin
--   2. Jalankan file ini (Import → pilih schema.sql), atau via CLI:
--        mysql -u root -p < database/schema.sql
--   3. Database `webpro_tunotes` akan dibuat & seluruh tabel terisi.
--
-- Konvensi:
--   - Semua tabel pakai InnoDB + utf8mb4 (mendukung emoji & karakter Unicode).
--   - Soft delete dipakai untuk `notes` & `comments` (status='deleted')
--     supaya admin bisa moderasi tanpa hilangkan data permanen.
--   - Hard delete dipakai untuk `favorites`, `ratings`, `reading_progress`
--     (data turunan, gak masalah hilang).
--   - Foreign key pakai ON DELETE CASCADE untuk tabel relasi murni
--     (favorites, ratings, dst.) supaya bersih saat user/note di-hard-delete.
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `webpro_tunotes`
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE `webpro_tunotes`;

-- ----------------------------------------------------------------------------
-- 1. users — akun & profil pengguna
-- ----------------------------------------------------------------------------
-- `username` ditambahkan selain `email` supaya konsisten dengan UI login lama
-- (form `Email or Username`). Login bisa pakai salah satu.
-- `notes_count`, `last_active_at` di-denormalisasi supaya tampilan admin cepat.
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`            VARCHAR(100) NOT NULL,
    `username`        VARCHAR(50)  NOT NULL,
    `email`           VARCHAR(150) NOT NULL,
    `password_hash`   VARCHAR(255) NOT NULL,
    `role`            ENUM('user','admin','super_admin') NOT NULL DEFAULT 'user',
    `status`          ENUM('active','suspended','inactive') NOT NULL DEFAULT 'active',
    `avatar_url`      VARCHAR(255) DEFAULT NULL,
    `notes_count`     INT UNSIGNED NOT NULL DEFAULT 0,
    `last_active_at`  DATETIME DEFAULT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_users_email`    (`email`),
    UNIQUE KEY `uniq_users_username` (`username`),
    KEY `idx_users_status_role` (`status`, `role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 2. semesters — pengelompokan mata kuliah per semester (Study List)
-- ----------------------------------------------------------------------------
-- Tiap user punya daftar semester sendiri. `is_active` menandai semester yang
-- sedang berjalan (dropdown "ganti semester" di halaman Study List).
DROP TABLE IF EXISTS `semesters`;
CREATE TABLE `semesters` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `name`       VARCHAR(60)  NOT NULL,        -- mis. "2024 Fall", "2025 Spring"
    `is_active`  TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_semesters_user` (`user_id`, `is_active`),
    CONSTRAINT `fk_semesters_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 3. courses — mata kuliah (Detail List / Study List)
-- ----------------------------------------------------------------------------
-- Setiap course milik user tertentu. `semester_id` nullable supaya course
-- bisa berdiri sendiri sebelum dimasukkan ke semester.
DROP TABLE IF EXISTS `courses`;
CREATE TABLE `courses` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`      INT UNSIGNED NOT NULL,
    `semester_id`  INT UNSIGNED DEFAULT NULL,
    `code`         VARCHAR(30)  DEFAULT NULL,  -- mis. "CS302"
    `name`         VARCHAR(120) NOT NULL,      -- mis. "Pemrograman Web"
    `instructor`   VARCHAR(120) DEFAULT NULL,
    `location`     VARCHAR(120) DEFAULT NULL,
    `start_date`   DATE         DEFAULT NULL,
    `link`         VARCHAR(255) DEFAULT NULL,
    `status`       ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_courses_user`     (`user_id`, `status`),
    KEY `idx_courses_semester` (`semester_id`),
    CONSTRAINT `fk_courses_user`     FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_courses_semester` FOREIGN KEY (`semester_id`)
        REFERENCES `semesters` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 4. notes — catatan yang di-upload user
-- ----------------------------------------------------------------------------
-- `tags` pakai JSON column (sesuai keputusan desain awal — simpel, gak butuh JOIN).
-- `course_id` nullable: note bisa berdiri sendiri sebelum dilink ke course.
-- `course_name` tetap disimpan sebagai text (denorm) supaya note publik dari
-- user lain tetap tampil nama mata kuliah walau user A tidak akses course user B.
-- `avg_rating` & `rating_count` di-cache di sini supaya tampilan card cepat
-- (di-update via trigger atau dihitung ulang di endpoint POST /api/ratings).
DROP TABLE IF EXISTS `notes`;
CREATE TABLE `notes` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`         INT UNSIGNED NOT NULL,
    `course_id`       INT UNSIGNED DEFAULT NULL,
    `title`           VARCHAR(255) DEFAULT NULL,  -- nullable: fallback ke file_name kalau kosong
    `course_name`     VARCHAR(120) NOT NULL,
    `lecturer_name`   VARCHAR(120) DEFAULT NULL,
    `category`        VARCHAR(50)  DEFAULT NULL,
    `tags`            JSON         DEFAULT NULL,
    `description`     TEXT         DEFAULT NULL,
    `file_path`       VARCHAR(255) NOT NULL,      -- path relatif di /uploads/
    `file_name`       VARCHAR(255) NOT NULL,      -- nama asli saat upload
    `file_size`       INT UNSIGNED NOT NULL,      -- bytes
    `file_type`       VARCHAR(10)  NOT NULL,      -- pdf/doc/docx/txt/ppt/pptx
    `page_count`      INT UNSIGNED DEFAULT NULL,  -- jumlah halaman (untuk card Favorit)
    `is_public`       TINYINT(1)   NOT NULL DEFAULT 1,
    `downloads_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `view_count`      INT UNSIGNED NOT NULL DEFAULT 0,
    `avg_rating`      DECIMAL(2,1) NOT NULL DEFAULT 0.0,  -- 0.0 - 5.0
    `rating_count`    INT UNSIGNED NOT NULL DEFAULT 0,
    `status`          ENUM('active','deleted') NOT NULL DEFAULT 'active',
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notes_user_status`     (`user_id`, `status`),
    KEY `idx_notes_public_created`  (`is_public`, `status`, `created_at`),
    KEY `idx_notes_course`          (`course_id`),
    CONSTRAINT `fk_notes_user`   FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`)   ON DELETE CASCADE,
    CONSTRAINT `fk_notes_course` FOREIGN KEY (`course_id`)
        REFERENCES `courses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 5. ratings — rating bintang per-user per-note
-- ----------------------------------------------------------------------------
-- UNIQUE(user_id, note_id) memastikan satu user cuma bisa kasih 1 rating per note.
-- Update rating = UPDATE row yang ada (bukan insert baru).
DROP TABLE IF EXISTS `ratings`;
CREATE TABLE `ratings` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `note_id`    INT UNSIGNED NOT NULL,
    `score`      TINYINT UNSIGNED NOT NULL,    -- 1..5; di-validasi di endpoint
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_ratings_user_note` (`user_id`, `note_id`),
    KEY `idx_ratings_note` (`note_id`),
    CONSTRAINT `fk_ratings_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ratings_note` FOREIGN KEY (`note_id`)
        REFERENCES `notes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 6. favorites — catatan yang di-like user (heart icon)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `favorites`;
CREATE TABLE `favorites` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `note_id`    INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_favorites_user_note` (`user_id`, `note_id`),
    KEY `idx_favorites_note` (`note_id`),
    CONSTRAINT `fk_favorites_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_favorites_note` FOREIGN KEY (`note_id`)
        REFERENCES `notes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 7. comments — komentar threaded di Detail Catatan
-- ----------------------------------------------------------------------------
-- `parent_id` self-FK untuk threading (NULL = top-level).
-- `status='deleted'` dipakai admin untuk hide komentar tanpa hapus permanent.
DROP TABLE IF EXISTS `comments`;
CREATE TABLE `comments` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `note_id`    INT UNSIGNED NOT NULL,
    `user_id`    INT UNSIGNED NOT NULL,
    `parent_id`  INT UNSIGNED DEFAULT NULL,
    `content`    TEXT NOT NULL,
    `status`     ENUM('active','deleted') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_comments_note_status` (`note_id`, `status`, `created_at`),
    KEY `idx_comments_parent`      (`parent_id`),
    KEY `idx_comments_user`        (`user_id`),
    CONSTRAINT `fk_comments_note`   FOREIGN KEY (`note_id`)
        REFERENCES `notes` (`id`)   ON DELETE CASCADE,
    CONSTRAINT `fk_comments_user`   FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`)   ON DELETE CASCADE,
    CONSTRAINT `fk_comments_parent` FOREIGN KEY (`parent_id`)
        REFERENCES `comments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 8. reading_progress — papan Kanban Notes Read (3 kolom)
-- ----------------------------------------------------------------------------
-- Satu entri = satu note yang sedang/sudah/akan dibaca user.
-- `status` cocok dengan label Kanban di UI.
DROP TABLE IF EXISTS `reading_progress`;
CREATE TABLE `reading_progress` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`          INT UNSIGNED NOT NULL,
    `note_id`          INT UNSIGNED NOT NULL,
    `status`           ENUM('belum_dibaca','sedang_dipelajari','selesai')
                       NOT NULL DEFAULT 'belum_dibaca',
    `progress_percent` TINYINT UNSIGNED NOT NULL DEFAULT 0,    -- 0..100
    `target_date`      DATE DEFAULT NULL,                       -- "Target Minggu Ini"
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_reading_user_note` (`user_id`, `note_id`),
    KEY `idx_reading_user_status` (`user_id`, `status`),
    CONSTRAINT `fk_reading_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reading_note` FOREIGN KEY (`note_id`)
        REFERENCES `notes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 9. homework — tugas per mata kuliah (tab Homework di Detail List)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `homework`;
CREATE TABLE `homework` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `course_id`   INT UNSIGNED NOT NULL,
    `user_id`     INT UNSIGNED NOT NULL,
    `title`       VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `due_date`    DATETIME DEFAULT NULL,
    `is_done`     TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_homework_course` (`course_id`, `is_done`),
    KEY `idx_homework_user`   (`user_id`, `due_date`),
    CONSTRAINT `fk_homework_course` FOREIGN KEY (`course_id`)
        REFERENCES `courses` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_homework_user`   FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 10. ai_results — riwayat hasil Notes AI Assistant
-- ----------------------------------------------------------------------------
-- `provider` & `tokens_used` disiapkan untuk integrasi OpenAI/Gemini di Tahap 5.
-- `source_note_ids` = JSON array berisi id catatan yang dijadikan konteks AI.
DROP TABLE IF EXISTS `ai_results`;
CREATE TABLE `ai_results` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`         INT UNSIGNED NOT NULL,
    `prompt`          TEXT NOT NULL,
    `mode`            VARCHAR(30) DEFAULT NULL,    -- 'otomatis' | 'penelitian' | 'semua_sumber'
    `response`        LONGTEXT DEFAULT NULL,
    `source_note_ids` JSON DEFAULT NULL,
    `can_edit`        TINYINT(1) NOT NULL DEFAULT 1,
    `provider`        VARCHAR(20) DEFAULT NULL,    -- 'openai' | 'gemini' | 'mock'
    `tokens_used`     INT UNSIGNED DEFAULT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ai_results_user_created` (`user_id`, `created_at`),
    CONSTRAINT `fk_ai_results_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 11. notifications — push notification massal (Manajemen Notifikasi admin)
-- ----------------------------------------------------------------------------
-- Untuk MVP, notifikasi disimpan global (tidak per-user). Field `target_role`
-- menentukan siapa yang melihat.
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`        VARCHAR(255) NOT NULL,
    `message`      TEXT NOT NULL,
    `type`         VARCHAR(30) DEFAULT NULL,   -- 'info' | 'maintenance' | 'academic'
    `target_role`  ENUM('all','user','admin') NOT NULL DEFAULT 'all',
    `scheduled_at` DATETIME DEFAULT NULL,
    `sent_at`      DATETIME DEFAULT NULL,
    `created_by`   INT UNSIGNED NOT NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notifications_target` (`target_role`, `scheduled_at`),
    KEY `idx_notifications_creator` (`created_by`),
    CONSTRAINT `fk_notifications_creator` FOREIGN KEY (`created_by`)
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- Data seed minimal — user super_admin default supaya bisa uji login admin
-- segera setelah import. Login pakai: username "admin" / password "admin123".
-- Hash di bawah dihasilkan dari:
--   password_hash('admin123', PASSWORD_BCRYPT, ['cost'=>12])
-- GANTI password ini segera setelah login pertama di Tahap 6.
-- ============================================================================
INSERT INTO `users` (`name`, `username`, `email`, `password_hash`, `role`, `status`)
VALUES (
    'Super Admin',
    'admin',
    'admin@tunotes.local',
    '$2y$12$sDL6e4JjPZZX3qE8w.IbAeTm7UnCk75eMULY2eRgwlbNuckix4/y2',
    'super_admin',
    'active'
);
