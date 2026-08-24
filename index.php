<?php
/**
 * CardVault Entrance Gateway
 */

require_once __DIR__ . '/includes/auth.php';

// Redirect based on session authentication state
if (is_logged_in()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
