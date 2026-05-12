<?php
/**
 * Helper sesi & autentikasi.
 * Pastikan session_start() dipanggil sekali per request (idempotent).
 *
 * Aturan main yang dipakai di seluruh API user:
 *   - Cookie session bernama TUNOTES_SID, HttpOnly, SameSite=Lax.
 *   - Setelah login berhasil, session_regenerate_id(true) untuk cegah session fixation.
 *   - require_login() di awal endpoint yang butuh user terautentikasi.
 *   - require_role('admin', 'super_admin') di halaman SSR admin.
 */

require_once __DIR__ . '/response.php';
require_once __DIR__ . '/../config/constants.php';

if (!function_exists('start_session_safe')) {
    function start_session_safe(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_set_cookie_params([
                'lifetime' => 0,                          // expire saat browser ditutup
                'path'     => '/',
                'domain'   => '',
                // secure=true otomatis aktif di production (HTTPS). Di XAMPP lokal tetap false.
                'secure'   => !empty($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }
}

if (!function_exists('current_user_id')) {
    function current_user_id(): ?int
    {
        start_session_safe();
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }
}

if (!function_exists('current_user_role')) {
    function current_user_role(): ?string
    {
        start_session_safe();
        return $_SESSION['role'] ?? null;
    }
}

if (!function_exists('require_login')) {
    /**
     * Pakai di awal endpoint API yang butuh autentikasi.
     * Kalau belum login → balas 401 JSON dan stop. Return user_id kalau OK.
     */
    function require_login(): int
    {
        $uid = current_user_id();
        if ($uid === null) {
            json_error('Sesi tidak ditemukan, silakan login.', 'AUTH_REQUIRED', 401);
        }
        return $uid;
    }
}

if (!function_exists('require_role')) {
    /**
     * Pakai di halaman admin SSR. Tolak kalau role tidak cocok.
     * Contoh: require_role('admin', 'super_admin');
     */
    function require_role(string ...$roles): void
    {
        require_login();
        $role = current_user_role();
        if (!in_array($role, $roles, true)) {
            // Untuk halaman SSR admin, balas HTML 403 sederhana (bukan JSON) supaya UX masuk akal.
            if (!headers_sent()) {
                http_response_code(403);
                header('Content-Type: text/html; charset=utf-8');
            }
            echo '<h1>403 Forbidden</h1><p>Akses ditolak untuk peran ini.</p>';
            exit;
        }
    }
}

if (!function_exists('login_user')) {
    /**
     * Tandai user sebagai login: regenerate session id + simpan info ke $_SESSION.
     * Dipanggil setelah password_verify berhasil di /api/auth.php.
     */
    function login_user(int $user_id, string $role, string $username, string $name): void
    {
        start_session_safe();
        session_regenerate_id(true);
        $_SESSION['user_id']      = $user_id;
        $_SESSION['role']         = $role;
        $_SESSION['username']     = $username;
        $_SESSION['name']         = $name;
        $_SESSION['logged_in_at'] = time();
    }
}

if (!function_exists('logout_user')) {
    function logout_user(): void
    {
        start_session_safe();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Token CSRF untuk form Admin (SSR) di Tahap 6.
     * Generate sekali per sesi, pakai hash_equals saat verifikasi.
     */
    function csrf_token(): string
    {
        start_session_safe();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf(?string $token): bool
    {
        start_session_safe();
        return !empty($_SESSION['csrf_token'])
            && is_string($token)
            && hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool
    {
        return current_user_id() !== null;
    }
}

if (!function_exists('current_user')) {
    /**
     * Ambil profil user yang sedang login langsung dari DB (bukan cache session).
     * Mengembalikan null kalau belum login atau status user bukan 'active'
     * (user yang di-suspend setelah login otomatis dianggap tidak terautentikasi).
     */
    function current_user(mysqli $conn): ?array
    {
        $uid = current_user_id();
        if ($uid === null) {
            return null;
        }

        $sql = 'SELECT id, name, username, email, role, status, avatar_url,
                       notes_count, last_active_at, created_at
                FROM users WHERE id = ? AND status = ? LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        $active = 'active';
        mysqli_stmt_bind_param($stmt, 'is', $uid, $active);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result) ?: null;
        mysqli_stmt_close($stmt);

        return $user;
    }
}
