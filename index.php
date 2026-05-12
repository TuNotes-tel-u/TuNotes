<?php
/**
 * Entry point project — http://host/Projectweb-TuNotes/ atau http://127.0.0.1:8080/.
 * Logged in admin/super_admin → admin/Dashboard-admin.php
 * Logged in user biasa        → pages/Dashboard.html
 * Guest                       → pages/LandingPage.html
 */

require_once __DIR__ . '/helpers/auth_helper.php';

start_session_safe();

if (is_logged_in()) {
    $role = current_user_role();
    if ($role === 'admin' || $role === 'super_admin') {
        header('Location: admin/Dashboard-admin.php');
        exit;
    }
    header('Location: pages/Dashboard.html');
    exit;
}

header('Location: pages/LandingPage.html');
exit;
