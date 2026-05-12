<?php
// Logout admin → destroy session, redirect ke landing/login.
require_once __DIR__ . '/../helpers/auth_helper.php';
logout_user();
header('Location: ../pages/login.html');
exit;
