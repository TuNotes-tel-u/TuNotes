<?php
/**
 * Konstanta global proyek TuNotes.
 * Dipisah dari db.php supaya bisa di-require oleh file lain (mis. upload helper)
 * tanpa harus memicu koneksi DB.
 */

// --- Database ---
defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');
defined('DB_NAME') || define('DB_NAME', 'webpro_tunotes');

// --- Aplikasi ---
defined('APP_NAME') || define('APP_NAME', 'TuNotes');

// APP_BASE_URL di-deteksi otomatis dari SCRIPT_NAME supaya project ini bisa
// dijalankan baik via:
//   - XAMPP htdocs subfolder (mis. /Projectweb-TuNotes/api/notes.php → base '/Projectweb-TuNotes')
//   - PHP dev server di project root (mis. /api/notes.php → base '')
// Override manual dengan define('APP_BASE_URL', '/custom') sebelum require constants.php
// kalau perlu.
if (!defined('APP_BASE_URL')) {
    $__script = $_SERVER['SCRIPT_NAME'] ?? '';
    $__base   = preg_replace('#/(api|admin|pages|helpers|config)/[^/]+\.php$#', '', $__script);
    define('APP_BASE_URL', is_string($__base) ? $__base : '');
    unset($__script, $__base);
}

// --- Upload catatan ---
// Path fisik untuk simpan file (di luar folder publik akses langsung tidak diblokir di XAMPP,
// tapi nama file kita acak supaya tidak bisa ditebak).
defined('UPLOAD_DIR')   || define('UPLOAD_DIR', __DIR__ . '/../uploads');
defined('UPLOAD_URL')   || define('UPLOAD_URL', APP_BASE_URL . '/uploads');
defined('MAX_UPLOAD_BYTES') || define('MAX_UPLOAD_BYTES', 10 * 1024 * 1024); // 10 MB
defined('ALLOWED_EXTENSIONS') || define('ALLOWED_EXTENSIONS', 'pdf,doc,docx,txt,ppt,pptx');
defined('ALLOWED_MIME_TYPES') || define('ALLOWED_MIME_TYPES',
    'application/pdf,'
    . 'application/msword,'
    . 'application/vnd.openxmlformats-officedocument.wordprocessingml.document,'
    . 'text/plain,'
    . 'application/vnd.ms-powerpoint,'
    . 'application/vnd.openxmlformats-officedocument.presentationml.presentation'
);

// --- Session ---
// Nama cookie diganti supaya tidak bentrok kalau XAMPP melayani project PHP lain di host yang sama.
defined('SESSION_NAME') || define('SESSION_NAME', 'TUNOTES_SID');

// --- Avatar default (untuk user yang belum unggah foto profil) ---
defined('DEFAULT_AVATAR') || define('DEFAULT_AVATAR', APP_BASE_URL . '/assets/images/user1.png');
