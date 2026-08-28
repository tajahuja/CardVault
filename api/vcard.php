<?php
/**
 * API - Export Profile as vCard (VCF)
 */

header('Content-Type: text/plain; charset=utf-8'); // Initial default, overridden for file download

$pdo = require dirname(__DIR__) . '/includes/db.php';

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

if (empty($slug)) {
    http_response_code(400);
    echo "Bad Request: Slug parameter is required.";
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM user_profiles WHERE slug = :slug LIMIT 1");
    $stmt->execute(['slug' => $slug]);
    $profile = $stmt->fetch();

    if (!$profile) {
        http_response_code(404);
        echo "Not Found: Profile does not exist.";
        exit;
    }

    $publicFields = json_decode($profile['public_fields_json'] ?? '{}', true);

    // Helper to sanitize/escape vCard values
    function escape_vcard($val) {
        if ($val === null) return '';
        $val = str_replace('\\', '\\\\', $val);
        $val = str_replace(';', '\\;', $val);
        $val = str_replace(',', '\\,', $val);
        $val = str_replace("\n", '\\n', $val);
        return trim($val);
    }

    $fullName = isset($publicFields['full_name']) && $publicFields['full_name'] ? $profile['full_name'] : '';
    $designation = isset($publicFields['designation']) && $publicFields['designation'] ? $profile['designation'] : '';
    $company = isset($publicFields['company']) && $publicFields['company'] ? $profile['company'] : '';
    $phone = isset($publicFields['phone']) && $publicFields['phone'] ? $profile['phone'] : '';
    $email = isset($publicFields['email']) && $publicFields['email'] ? $profile['email'] : '';
    $website = isset($publicFields['website']) && $publicFields['website'] ? $profile['website'] : '';
    $linkedinUrl = isset($publicFields['linkedin_url']) && $publicFields['linkedin_url'] ? $profile['linkedin_url'] : '';

    if (empty($fullName)) {
        $fullName = "CardVault Contact";
    }

    // Build vCard content
    $vcard = "BEGIN:VCARD\r\n";
    $vcard .= "VERSION:3.0\r\n";
    $vcard .= "FN:" . escape_vcard($fullName) . "\r\n";
    
    if (!empty($company)) {
        $vcard .= "ORG:" . escape_vcard($company) . "\r\n";
    }
    
    if (!empty($designation)) {
        $vcard .= "TITLE:" . escape_vcard($designation) . "\r\n";
    }
    
    if (!empty($phone)) {
        $vcard .= "TEL;TYPE=CELL,VOICE:" . escape_vcard($phone) . "\r\n";
    }
    
    if (!empty($email)) {
        $vcard .= "EMAIL;TYPE=PREF,INTERNET:" . escape_vcard($email) . "\r\n";
    }
    
    if (!empty($website)) {
        $vcard .= "URL:" . escape_vcard($website) . "\r\n";
    } elseif (!empty($linkedinUrl)) {
        $vcard .= "URL:" . escape_vcard($linkedinUrl) . "\r\n";
    }
    
    $vcard .= "NOTE:Exchanged via CardVault Personal CRM\r\n";
    $vcard .= "END:VCARD\r\n";

    // Stream download headers
    header('Content-Type: text/vcard; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $slug . '.vcf"');
    header('Content-Length: ' . strlen($vcard));
    echo $vcard;
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo "Database Error: " . $e->getMessage();
    exit;
}
