<?php
/**
 * API - Check Unique Profile Slug Availability
 */

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/includes/auth.php';
$pdo = require dirname(__DIR__) . '/includes/db.php';

// Assert user is logged in
require_login();

$userId = $_SESSION['user_id'];
$slug = isset($_GET['slug']) ? trim(strtolower($_GET['slug'])) : '';

// Validate slug characters (only alphanumeric, dashes, and underscores)
if (empty($slug) || !preg_match('/^[a-z0-9_-]+$/', $slug)) {
    echo json_encode(['available' => false, 'message' => 'Invalid characters.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_profiles WHERE slug = :slug AND user_id != :user_id");
    $stmt->execute([
        'slug' => $slug,
        'user_id' => $userId
    ]);
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        echo json_encode(['available' => false, 'message' => 'Slug is already taken.']);
    } else {
        echo json_encode(['available' => true, 'message' => 'Slug is available!']);
    }
    exit;
} catch (PDOException $e) {
    echo json_encode(['available' => false, 'message' => 'Database error.']);
    exit;
}
