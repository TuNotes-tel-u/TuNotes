<?php
/**
 * /api/auth.php — endpoint autentikasi.
 *
 *   POST ?action=register   { name, username, email, password }     → 201 + auto-login
 *   POST ?action=login      { identifier, password }                → 200 + set session
 *   GET                                                             → 200 (data user) / 401
 *   DELETE                                                          → 200 (logout)
 *
 * Response: { success, message, data?, meta?, error_code?, details? }
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

only_methods('GET', 'POST', 'DELETE');
start_session_safe();

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? (string) $_GET['action'] : '';

// -----------------------------------------------------------------------------
// GET — cek sesi aktif, atau ?view=profile untuk profile + stats
// -----------------------------------------------------------------------------
if ($method === 'GET') {
    $user = current_user($conn);
    if ($user === null) {
        json_error('Belum login.', 'AUTH_REQUIRED', 401);
    }

    if (($_GET['view'] ?? '') === 'profile') {
        $uid = (int) $user['id'];

        // Total catatan aktif user
        $sql  = "SELECT COUNT(*) AS c FROM notes WHERE user_id = ? AND status = 'active'";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $uid);
        mysqli_stmt_execute($stmt);
        $total_notes = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];
        mysqli_stmt_close($stmt);

        // Total favorit user (catatan yang dia like)
        $sql  = "SELECT COUNT(*) AS c FROM favorites WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $uid);
        mysqli_stmt_execute($stmt);
        $favorites = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];
        mysqli_stmt_close($stmt);

        // Total downloads kumulatif dari catatan yg dia upload
        $sql  = "SELECT IFNULL(SUM(downloads_count), 0) AS s FROM notes WHERE user_id = ? AND status = 'active'";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $uid);
        mysqli_stmt_execute($stmt);
        $downloads = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['s'];
        mysqli_stmt_close($stmt);

        $member_since = '';
        if (!empty($user['created_at'])) {
            $ts = strtotime((string) $user['created_at']);
            if ($ts) $member_since = date('M Y', $ts);
        }

        json_success('OK', [
            'user'  => $user,
            'stats' => [
                'total_notes'  => $total_notes,
                'favorites'    => $favorites,
                'downloads'    => $downloads,
                'member_since' => $member_since,
            ],
        ]);
    }

    json_success('Sesi aktif.', $user);
}

// -----------------------------------------------------------------------------
// DELETE — logout
// -----------------------------------------------------------------------------
if ($method === 'DELETE') {
    logout_user();
    json_success('Logout berhasil.');
}

// -----------------------------------------------------------------------------
// POST — register atau login (dipisah via ?action=)
// -----------------------------------------------------------------------------
$input = read_json_input();

if ($action === 'register') {
    $name     = isset($input['name'])     ? trim((string) $input['name'])     : '';
    $username = isset($input['username']) ? trim((string) $input['username']) : '';
    $email    = isset($input['email'])    ? trim((string) $input['email'])    : '';
    $password = isset($input['password']) ? (string) $input['password']       : '';

    // Validasi field. Kumpulkan semua error sekaligus supaya UI bisa highlight banyak field.
    $errors = [];
    if ($name === '' || mb_strlen($name) > 100) {
        $errors['name'] = 'Nama wajib diisi (maksimal 100 karakter).';
    }
    if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
        $errors['username'] = 'Username 3–50 karakter, hanya huruf, angka, dan underscore.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
        $errors['email'] = 'Format email tidak valid.';
    }
    if (strlen($password) < 8) {
        $errors['password'] = 'Password minimal 8 karakter.';
    }
    if (!empty($errors)) {
        json_error('Validasi gagal.', 'VALIDATION_FAILED', 400, $errors);
    }

    // Cek konflik email atau username (kasus-insensitif untuk email).
    $sql  = 'SELECT id, email, username FROM users WHERE email = ? OR username = ? LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ss', $email, $username);
    mysqli_stmt_execute($stmt);
    $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($existing) {
        $which = strcasecmp($existing['email'], $email) === 0 ? 'Email' : 'Username';
        json_error("$which sudah terdaftar.", 'CONFLICT', 409);
    }

    // Insert user baru.
    $hash   = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $role   = 'user';
    $status = 'active';
    $sql    = 'INSERT INTO users (name, username, email, password_hash, role, status, last_active_at)
               VALUES (?, ?, ?, ?, ?, ?, NOW())';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ssssss', $name, $username, $email, $hash, $role, $status);
    if (!mysqli_stmt_execute($stmt)) {
        error_log('[TuNotes] auth.register insert gagal: ' . mysqli_stmt_error($stmt));
        json_error('Gagal menyimpan user.', 'INTERNAL_ERROR', 500);
    }
    $new_id = (int) mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    // Auto-login: session_regenerate_id sudah di dalam login_user().
    login_user($new_id, $role, $username, $name);

    $user = current_user($conn);
    json_success('Registrasi berhasil.', $user, null, 201);
}

if ($action === 'login') {
    $identifier = isset($input['identifier']) ? trim((string) $input['identifier']) : '';
    $password   = isset($input['password'])   ? (string) $input['password']         : '';

    if ($identifier === '' || $password === '') {
        json_error('Identifier dan password wajib diisi.', 'VALIDATION_FAILED', 400);
    }

    $sql  = 'SELECT id, name, username, email, password_hash, role, status
             FROM users WHERE email = ? OR username = ? LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ss', $identifier, $identifier);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    // Pesan generik supaya tidak bocor "akun mana yang terdaftar".
    if (!$row || !password_verify($password, $row['password_hash'])) {
        json_error('Email/username atau password salah.', 'INVALID_CREDENTIALS', 401);
    }
    if ($row['status'] !== 'active') {
        json_error('Akun tidak aktif. Hubungi admin.', 'ACCOUNT_NOT_ACTIVE', 403);
    }

    // Update last_active_at — sengaja sebelum login_user supaya kalau update gagal,
    // sesi belum keburu dibuat.
    $uid  = (int) $row['id'];
    $sql  = 'UPDATE users SET last_active_at = NOW() WHERE id = ?';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $uid);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    login_user($uid, (string) $row['role'], (string) $row['username'], (string) $row['name']);

    $user = current_user($conn);
    json_success('Login berhasil.', $user);
}

if ($action === 'update_profile') {
    $uid = require_login();

    $name             = trim((string) ($input['name'] ?? ''));
    $email            = trim((string) ($input['email'] ?? ''));
    $current_password = (string) ($input['current_password'] ?? '');
    $new_password     = (string) ($input['new_password'] ?? '');

    $errors = [];
    if ($name === '' || mb_strlen($name) > 100) {
        $errors['name'] = 'Nama wajib (maksimal 100 karakter).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
        $errors['email'] = 'Format email tidak valid.';
    }

    // Password change flow (opsional)
    $new_hash = null;
    if ($new_password !== '' || $current_password !== '') {
        if ($current_password === '') {
            $errors['current_password'] = 'Wajib isi password saat ini untuk mengganti.';
        }
        if ($new_password !== '' && strlen($new_password) < 8) {
            $errors['new_password'] = 'Password baru minimal 8 karakter.';
        }
        if ($new_password === '') {
            $errors['new_password'] = 'Wajib isi password baru.';
        }
        if (empty($errors)) {
            $sql  = "SELECT password_hash FROM users WHERE id = ? LIMIT 1";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'i', $uid);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if (!$row || !password_verify($current_password, $row['password_hash'])) {
                $errors['current_password'] = 'Password saat ini salah.';
            } else {
                $new_hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
            }
        }
    }

    if (!empty($errors)) {
        json_error('Validasi gagal.', 'VALIDATION_FAILED', 400, $errors);
    }

    // Email harus unik (selain dirinya sendiri)
    $sql  = "SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'si', $email, $uid);
    mysqli_stmt_execute($stmt);
    $dupe = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($dupe) {
        json_error('Email sudah dipakai user lain.', 'CONFLICT', 409);
    }

    if ($new_hash !== null) {
        $sql  = "UPDATE users SET name = ?, email = ?, password_hash = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'sssi', $name, $email, $new_hash, $uid);
    } else {
        $sql  = "UPDATE users SET name = ?, email = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ssi', $name, $email, $uid);
    }
    if (!mysqli_stmt_execute($stmt)) {
        error_log('[TuNotes] update_profile gagal: ' . mysqli_stmt_error($stmt));
        json_error('Gagal menyimpan perubahan profil.', 'INTERNAL_ERROR', 500);
    }
    mysqli_stmt_close($stmt);

    $user = current_user($conn);
    json_success('Profil diperbarui.', $user);
}

json_error('Action tidak dikenal. Gunakan ?action=register/login/update_profile.', 'INVALID_ACTION', 400);
