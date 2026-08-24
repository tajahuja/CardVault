<?php
/**
 * User Logout Page
 */

require_once __DIR__ . '/includes/auth.php';

// Execute logout
logout_user();

// Redirect to login page with notice
header('Location: login.php?logged_out=1');
exit;
