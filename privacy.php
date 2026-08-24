<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - CardVault</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .privacy-container {
            max-width: 800px;
            margin: 4rem auto;
            padding: 0 1.5rem;
        }
        .privacy-card {
            background-color: var(--surface-color);
            padding: 3rem 2.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
        }
        .privacy-card h1 {
            color: var(--secondary-color);
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .privacy-card h2 {
            color: var(--secondary-color);
            font-size: 1.25rem;
            font-weight: 600;
            margin-top: 2rem;
            margin-bottom: 0.75rem;
            border-left: 3px solid var(--primary-color);
            padding-left: 0.5rem;
        }
        .privacy-card p, .privacy-card li {
            color: var(--text-color);
            font-size: 0.95rem;
            line-height: 1.7;
            margin-bottom: 1rem;
        }
        .privacy-card ul {
            margin-left: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .back-link {
            display: inline-block;
            margin-top: 2rem;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body style="background: linear-gradient(135deg, #e0e7ff 0%, #f8fafc 100%); min-height: 100vh;">
    <div class="privacy-container">
        <div class="privacy-card">
            <div class="logo" style="margin-bottom: 2rem;">
                <span class="logo-icon">🗂️</span>
                <span class="logo-text">Card<span>Vault</span></span>
            </div>
            
            <h1>Privacy Policy</h1>
            <p style="color: var(--text-muted); font-size: 0.85rem;">Last Updated: 19 August 2026</p>
            
            <p>Welcome to CardVault. We are committed to protecting the personal information and contacts data you manage within our application. This Privacy Policy details how we handle, store, and safeguard your data.</p>
            
            <h2>1. Data Collection & Isolation</h2>
            <p>CardVault is structured as a private multi-user CRM. All information you create, import, or upload is exclusively associated with your unique account identifier. Other users of the system have no technical access to browse, search, or view your records under any circumstances.</p>
            
            <h2>2. Personal Contact Information</h2>
            <p>We store the contact files you scan or manually enter, including names, company, email addresses, phone numbers, location data, relationship status, custom tags, and personal call notes. This data is strictly stored for your private networking CRM purposes.</p>
            
            <h2>3. Secure Image Storage</h2>
            <p>When you scan a business card, the image file is uploaded to a secure directory on our servers.
            To safeguard your documents:
            <ul>
                <li>The original file names are discarded and replaced with cryptographically secure, unpredictable filenames.</li>
                <li>Direct web execution of scripts within the uploads folder is strictly blocked via directory access controls.</li>
                <li>Card images are served exclusively via an authorized media gateway that verifies session ownership before outputting image content.</li>
            </ul>
            </p>
            
            <h2>4. Browser-Based Processing</h2>
            <p>OCR text extraction is executed completely in your local web browser using Tesseract.js. No external OCR servers or cloud-based machine learning endpoints process your images, meaning your card data never leaves the secure flow between your browser and your private database.</p>
            
            <h2>5. Your Rights: Right to be Forgotten</h2>
            <p>You retain full ownership of your data:
            <ul>
                <li>You may modify or delete individual contacts, notes, and tags at any time.</li>
                <li>You have the right to completely purge your account. Deleting your account in Settings will instantly erase all your profile data, CRM records, and business card files from the storage disks.</li>
            </ul>
            </p>

            <h2>6. Security Measures</h2>
            <p>We employ standard enterprise security practices to defend your data, including SQL injection mitigation via PDO prepared statements, Cross-Site Scripting (XSS) escaping, session timeouts, and Cross-Site Request Forgery (CSRF) tokens on all mutating operations.</p>
            
            <a href="login.php" class="back-link">← Back to Sign In</a>
        </div>
    </div>
</body>
</html>
