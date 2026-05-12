<?php
/**
 * CSRF helper untuk form admin.
 * Pakai di tiap form POST:
 *
 *   <form method="POST">
 *     <?= csrf_field() ?>
 *     ...
 *   </form>
 *
 * Di handler POST: verify_csrf($_POST['csrf_token'] ?? '') → bool.
 * Fungsi csrf_token() & verify_csrf() ada di helpers/auth_helper.php.
 */

require_once __DIR__ . '/../../helpers/auth_helper.php';

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        $token = csrf_token();
        return '<input type="hidden" name="csrf_token" value="'
             . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}
