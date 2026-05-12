<?php
/**
 * Helper untuk validasi & penyimpanan file upload catatan.
 *
 * Pakai: validate_uploaded_file($_FILES['file']) → array bersih (ext, mime, size, name)
 *        store_uploaded_file($info) → ['stored_name', 'file_path']
 *        delete_stored_file($stored_name)
 *
 * Semua fungsi memanggil json_error + exit jika input invalid (gaya fail-fast).
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/response.php';

if (!function_exists('parse_allowed_extensions')) {
    function parse_allowed_extensions(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', ALLOWED_EXTENSIONS))));
    }
}

if (!function_exists('parse_allowed_mimes')) {
    function parse_allowed_mimes(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', ALLOWED_MIME_TYPES))));
    }
}

if (!function_exists('validate_uploaded_file')) {
    function validate_uploaded_file(array $f): array
    {
        if (!isset($f['error']) || is_array($f['error'])) {
            json_error('File upload tidak valid.', 'UPLOAD_INVALID', 400);
        }
        if ($f['error'] === UPLOAD_ERR_NO_FILE) {
            json_error('File wajib diisi.', 'FILE_REQUIRED', 400);
        }
        if ($f['error'] !== UPLOAD_ERR_OK) {
            // Mapping kode error PHP supaya pesan ke client lebih jelas.
            $messages = [
                UPLOAD_ERR_INI_SIZE   => 'File melebihi batas upload server (php.ini).',
                UPLOAD_ERR_FORM_SIZE  => 'File melebihi batas form.',
                UPLOAD_ERR_PARTIAL    => 'File ter-upload sebagian, ulangi.',
                UPLOAD_ERR_NO_TMP_DIR => 'Server tidak punya tmp dir.',
                UPLOAD_ERR_CANT_WRITE => 'Server gagal menulis file.',
                UPLOAD_ERR_EXTENSION  => 'Upload dihentikan ekstensi PHP.',
            ];
            $msg = $messages[$f['error']] ?? ('Upload gagal (kode ' . $f['error'] . ').');
            json_error($msg, 'UPLOAD_FAILED', 400);
        }

        $size = (int) ($f['size'] ?? 0);
        if ($size <= 0 || $size > MAX_UPLOAD_BYTES) {
            json_error('Ukuran file harus > 0 dan ≤ 10 MB.', 'FILE_TOO_LARGE', 400);
        }

        $name = (string) ($f['name'] ?? '');
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed_exts = parse_allowed_extensions();
        if ($ext === '' || !in_array($ext, $allowed_exts, true)) {
            json_error('Ekstensi tidak diizinkan. Hanya: ' . ALLOWED_EXTENSIONS . '.', 'EXT_NOT_ALLOWED', 400);
        }

        // MIME pakai finfo (lebih reliable daripada $_FILES['type'] yang dari client).
        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime  = (string) finfo_file($finfo, $f['tmp_name']);
                finfo_close($finfo);
            }
        }
        if ($mime === '') {
            $mime = (string) ($f['type'] ?? '');
        }
        $allowed_mimes = parse_allowed_mimes();
        if (!in_array($mime, $allowed_mimes, true)) {
            json_error("MIME type tidak diizinkan: $mime", 'MIME_NOT_ALLOWED', 400);
        }

        return [
            'name' => $name,
            'ext'  => $ext,
            'mime' => $mime,
            'size' => $size,
            'tmp'  => $f['tmp_name'],
        ];
    }
}

if (!function_exists('store_uploaded_file')) {
    function store_uploaded_file(array $info): array
    {
        if (!is_dir(UPLOAD_DIR)) {
            if (!mkdir(UPLOAD_DIR, 0775, true) && !is_dir(UPLOAD_DIR)) {
                json_error('Server gagal menyiapkan folder upload.', 'INTERNAL_ERROR', 500);
            }
        }
        // Nama acak supaya tidak bisa ditebak & tidak collide.
        $stored = bin2hex(random_bytes(16)) . '.' . $info['ext'];
        $dest   = UPLOAD_DIR . DIRECTORY_SEPARATOR . $stored;
        if (!move_uploaded_file($info['tmp'], $dest)) {
            json_error('Server gagal menyimpan file.', 'INTERNAL_ERROR', 500);
        }
        return [
            'stored_name' => $stored,
            'file_path'   => $stored, // disimpan relatif terhadap UPLOAD_DIR
        ];
    }
}

if (!function_exists('delete_stored_file')) {
    function delete_stored_file(?string $stored_name): void
    {
        if (!$stored_name) {
            return;
        }
        // basename() defensif: cegah path traversal kalau ada bug yang lolos.
        $path = UPLOAD_DIR . DIRECTORY_SEPARATOR . basename($stored_name);
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

if (!function_exists('parse_tags_input')) {
    /**
     * Terima tags sebagai JSON array atau comma-separated, normalisasi ke JSON string.
     * Maksimal 10 tag, masing-masing maks 30 karakter.
     * Return null kalau kosong (akan disimpan sebagai NULL di DB).
     */
    function parse_tags_input($raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $arr = null;
        $maybe = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : null);
        if (is_array($maybe)) {
            $arr = $maybe;
        } else {
            $arr = explode(',', (string) $raw);
        }
        $arr = array_values(array_filter(array_map(
            function ($t) { return trim((string) $t); },
            $arr
        ), 'strlen'));
        if (empty($arr)) {
            return null;
        }
        $arr = array_slice($arr, 0, 10);
        $arr = array_map(function ($t) { return mb_substr($t, 0, 30); }, $arr);
        return json_encode($arr, JSON_UNESCAPED_UNICODE);
    }
}
