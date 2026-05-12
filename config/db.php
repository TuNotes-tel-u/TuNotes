<?php
/**
 * Koneksi database mysqli untuk semua endpoint API dan halaman admin SSR.
 * Sengaja pakai mysqli prosedural (bukan PDO) supaya konsisten dengan ketentuan proyek.
 *
 * Cara pakai: require_once __DIR__ . '/../config/db.php';
 * Variabel $conn (mysqli) tersedia setelah require.
 */

require_once __DIR__ . '/constants.php';

// Matikan reporting mysqli built-in (PHP 8.1+ defaultnya melempar exception).
// Kita pilih cek manual + balas JSON rapi supaya error tidak bocor sebagai HTML.
mysqli_report(MYSQLI_REPORT_OFF);

$conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    // Jangan bocorkan detail koneksi ke client. Cukup log + balas JSON 500.
    error_log('[TuNotes] Koneksi DB gagal: ' . mysqli_connect_error());

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'success'    => false,
        'message'    => 'Server bermasalah, koneksi database gagal.',
        'error_code' => 'DB_CONNECTION_FAILED',
    ]);
    exit;
}

// utf8mb4 supaya emoji & karakter non-Latin (judul mata kuliah dalam bahasa lain, dll) aman.
mysqli_set_charset($conn, 'utf8mb4');
