<?php
/**
 * API - Secure Business Card Image Upload
 */

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Assert user is logged in
require_login();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method not allowed.', [], 405);
}

// Validate CSRF token
validate_csrf();

// Check if file was uploaded
if (!isset($_FILES['card_image'])) {
    json_response(false, 'No file was uploaded.', [], 400);
}

$file = $_FILES['card_image'];

// Check upload errors
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errMessage = 'File upload failed.';
    switch ($file['error']) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $errMessage = 'The uploaded file exceeds the maximum allowed size (5MB).';
            break;
        case UPLOAD_ERR_PARTIAL:
            $errMessage = 'The file was only partially uploaded.';
            break;
        case UPLOAD_ERR_NO_FILE:
            $errMessage = 'No file was uploaded.';
            break;
    }
    json_response(false, $errMessage, [], 400);
}

// Validate file size (Limit to 5MB)
$maxSize = 5 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    json_response(false, 'File is too large. Maximum size allowed is 5MB.', [], 400);
}

// Validate MIME Type
$allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
$tempPath = $file['tmp_name'];

if (!file_exists($tempPath)) {
    json_response(false, 'Uploaded file not found on server.', [], 500);
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $tempPath);
finfo_close($finfo);

if (!in_array($mimeType, $allowedMimes)) {
    json_response(false, 'Invalid image format. Only JPG, PNG, and WEBP formats are allowed.', [], 400);
}

// Validate Extension
$originalName = $file['name'];
$pathParts = pathinfo($originalName);
$ext = isset($pathParts['extension']) ? strtolower($pathParts['extension']) : '';
$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

if (!in_array($ext, $allowedExtensions)) {
    json_response(false, 'Invalid file extension. Only .jpg, .jpeg, .png, and .webp extensions are allowed.', [], 400);
}

// Additional integrity check: ensure it is a valid image dimensions
$imgSize = getimagesize($tempPath);
if ($imgSize === false) {
    json_response(false, 'Corrupt image file. Upload rejected.', [], 400);
}

// Create destination path
$uploadDir = dirname(__DIR__) . '/uploads/business_cards/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique random filename
try {
    $randomName = bin2hex(random_bytes(16)) . '.' . $ext;
} catch (\Exception $e) {
    $randomName = md5(uniqid(mt_rand(), true)) . '.' . $ext;
}

$destPath = $uploadDir . $randomName;

// Move file to uploads directory
if (move_uploaded_file($tempPath, $destPath)) {
    // Record in user session to authorize immediate view access
    if (!isset($_SESSION['uploaded_cards'])) {
        $_SESSION['uploaded_cards'] = [];
    }
    $_SESSION['uploaded_cards'][] = $randomName;
    
    json_response(true, 'Image uploaded successfully.', [
        'filename' => $randomName
    ]);
} else {
    error_log("Failed to move uploaded file to: " . $destPath);
    json_response(false, 'Failed to save the image on the server.', [], 500);
}
