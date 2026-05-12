<?php
/**
 * Auth guard untuk SEMUA halaman admin.
 * Include di paling atas tiap file admin/*.php (sebelum render apa pun).
 *
 *   require_once __DIR__ . '/includes/auth_check.php';
 *
 * Setelah include, variabel global tersedia:
 *   $admin_user  → array data user current (id, name, username, email, role, status, avatar_url)
 *   $is_super    → bool, true kalau role super_admin
 *
 * Kalau belum login atau role bukan admin/super_admin, langsung redirect.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/auth_helper.php';

start_session_safe();

$uid = current_user_id();
if ($uid === null) {
    // Belum login → ke halaman login user.
    header('Location: ../pages/login.html');
    exit;
}

$role = current_user_role();
if (!in_array($role, ['admin', 'super_admin'], true)) {
    // User biasa coba akses admin → balikkan ke Dashboard user dengan flash.
    $_SESSION['flash'] = [
        'type'    => 'error',
        'message' => 'Akses ditolak — area khusus admin.',
    ];
    header('Location: ../pages/Dashboard.html');
    exit;
}

// Ambil data user lengkap untuk dipakai di sidebar/header.
$sql  = "SELECT id, name, username, email, role, status, avatar_url
         FROM users WHERE id = ? AND status = 'active' LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $uid);
mysqli_stmt_execute($stmt);
$admin_user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$admin_user) {
    // User di-suspend / dihapus saat sesi masih aktif → paksa logout.
    logout_user();
    header('Location: ../pages/login.html');
    exit;
}

$is_super = ($admin_user['role'] === 'super_admin');

// Flash message support (set di handler POST, baca di GET berikutnya).
$flash = null;
if (isset($_SESSION['admin_flash'])) {
    $flash = $_SESSION['admin_flash'];
    unset($_SESSION['admin_flash']);
}

/**
 * Helper: set flash message + redirect (POST-Redirect-GET pattern).
 */
if (!function_exists('admin_flash_redirect')) {
    function admin_flash_redirect(string $url, string $type, string $message): void
    {
        $_SESSION['admin_flash'] = ['type' => $type, 'message' => $message];
        header('Location: ' . $url);
        exit;
    }
}

/**
 * Helper: escape output ke HTML.
 */
if (!function_exists('h')) {
    function h($v): string
    {
        return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
    }
}
