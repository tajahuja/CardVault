<?php
/**
 * Global Header - Included in all protected pages
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/functions.php';

// Assert user is logged in
require_login();

// Helper to determine active link
$currentPage = basename($_SERVER['PHP_SELF']);
function is_active_nav($page, $current) {
    return $page === $current ? 'active' : '';
}

// Get user initials for avatar
$userInitial = 'U';
if (!empty($_SESSION['user_name'])) {
    $userInitial = strtoupper(substr(trim($_SESSION['user_name']), 0, 1));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? e($pageTitle) . ' - CardVault' : 'CardVault CRM'; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php echo $additionalHead ?? ''; ?>
</head>
<body>
    <div class="app-wrapper">
        <!-- Sidebar for Desktop -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="dashboard.php" class="logo">
                    <span class="logo-icon">🗂️</span>
                    <span class="logo-text">Card<span>Vault</span></span>
                </a>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-link <?php echo is_active_nav('dashboard.php', $currentPage); ?>">
                    <span class="nav-icon">📊</span> Dashboard
                </a>
                <a href="contacts.php" class="nav-link <?php echo is_active_nav('contacts.php', $currentPage); ?>">
                    <span class="nav-icon">👥</span> Contacts
                </a>
                <a href="scan.php" class="nav-link <?php echo is_active_nav('scan.php', $currentPage); ?>">
                    <span class="nav-icon">📸</span> Scan Card
                </a>
                <a href="settings.php" class="nav-link <?php echo is_active_nav('settings.php', $currentPage); ?>">
                    <span class="nav-icon">⚙️</span> Settings
                </a>
            </nav>
            <div class="sidebar-footer">
                <div class="user-badge">
                    <div class="user-avatar"><?php echo e($userInitial); ?></div>
                    <div class="user-info">
                        <div class="user-name" title="<?php echo e($_SESSION['user_name']); ?>"><?php echo e($_SESSION['user_name']); ?></div>
                        <a href="logout.php" style="color: #f87171; font-size: 0.8rem; text-decoration: none; display: block; margin-top: 0.15rem;">Sign Out</a>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Mobile Navigation (Bottom Bar) -->
        <nav class="mobile-nav">
            <div class="mobile-nav-inner">
                <a href="dashboard.php" class="mobile-link <?php echo is_active_nav('dashboard.php', $currentPage); ?>">
                    <span class="mobile-link-icon">📊</span>
                    <span>Dashboard</span>
                </a>
                <a href="contacts.php" class="mobile-link <?php echo is_active_nav('contacts.php', $currentPage); ?>">
                    <span class="mobile-link-icon">👥</span>
                    <span>Contacts</span>
                </a>
                <a href="scan.php" class="mobile-link <?php echo is_active_nav('scan.php', $currentPage); ?>">
                    <span class="mobile-link-icon">📸</span>
                    <span>Scan</span>
                </a>
                <a href="settings.php" class="mobile-link <?php echo is_active_nav('settings.php', $currentPage); ?>">
                    <span class="mobile-link-icon">⚙️</span>
                    <span>Settings</span>
                </a>
                <a href="logout.php" class="mobile-link">
                    <span class="mobile-link-icon">🚪</span>
                    <span>Out</span>
                </a>
            </div>
        </nav>

        <!-- Main Workspace Content -->
        <main class="main-content">
