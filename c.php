<?php
/**
 * Public Digital Business Card Landing Page (Guest Access)
 */

// Load core files without require_login()
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
$pdo = require __DIR__ . '/includes/db.php';

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

if (empty($slug)) {
    // If no slug, try parsing from request URI (fallback for URL rewrite c/username)
    $requestUri = $_SERVER['REQUEST_URI'];
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $basePath = dirname($scriptName);
    
    // Simple path extraction
    $path = trim(str_replace($basePath, '', $requestUri), '/');
    if (preg_match('/^c\/([a-zA-Z0-9_-]+)$/', $path, $matches)) {
        $slug = $matches[1];
    }
}

if (empty($slug)) {
    http_response_code(404);
    include __DIR__ . '/404.php'; // Fallback to 404 page if exists, or show simple message
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM user_profiles WHERE slug = :slug LIMIT 1");
    $stmt->execute(['slug' => $slug]);
    $profile = $stmt->fetch();

    if (!$profile) {
        http_response_code(404);
        echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'><title>Profile Not Found</title>";
        echo "<link rel='stylesheet' href='assets/css/style.css'><style>body { display: flex; align-items: center; justify-content: center; height: 100vh; background-color: #f5f7fa; font-family: 'Inter', sans-serif; }</style></head>";
        echo "<body><div style='text-align: center; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); max-width: 400px;'><h1 style='color: #ff3b30; font-size: 24px; margin-bottom: 10px;'>Profile Not Found</h1><p style='color: #666; margin-bottom: 20px;'>The digital business card you are looking for does not exist or has been removed.</p><a href='login.php' style='display: inline-block; background: #007aff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px;'>Go to CardVault</a></div></body></html>";
        exit;
    }

    $publicFields = json_decode($profile['public_fields_json'] ?? '{}', true);

    // Filter displayed fields based on public toggles
    $showName = isset($publicFields['full_name']) && $publicFields['full_name'] ? $profile['full_name'] : '';
    $showDesignation = isset($publicFields['designation']) && $publicFields['designation'] ? $profile['designation'] : '';
    $showCompany = isset($publicFields['company']) && $publicFields['company'] ? $profile['company'] : '';
    $showPhone = isset($publicFields['phone']) && $publicFields['phone'] ? $profile['phone'] : '';
    $showEmail = isset($publicFields['email']) && $publicFields['email'] ? $profile['email'] : '';
    $showWebsite = isset($publicFields['website']) && $publicFields['website'] ? $profile['website'] : '';
    $showLinkedin = isset($publicFields['linkedin_url']) && $publicFields['linkedin_url'] ? $profile['linkedin_url'] : '';
    $showBio = isset($publicFields['bio']) && $publicFields['bio'] ? $profile['bio'] : '';

    $userInitial = !empty($showName) ? strtoupper(substr($showName, 0, 1)) : 'U';

} catch (PDOException $e) {
    http_response_code(500);
    echo "Database connection error.";
    exit;
}

// Generate guest CSRF token for the contact exchange form
$csrfToken = get_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($showName ?: 'Digital Business Card'); ?> - CardVault</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #007aff;
            --primary-hover: #0056b3;
            --text-color: #1d1d1f;
            --secondary-text: #86868b;
            --bg-color: #f5f5f7;
            --card-bg: #ffffff;
            --border-color: #e5e5ea;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            line-height: 1.5;
            padding: 20px 10px;
        }

        .container {
            max-width: 480px;
            margin: 0 auto;
        }

        .card {
            background-color: var(--card-bg);
            border-radius: 18px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
        }

        .card-header {
            background: linear-gradient(135deg, #007aff, #00c6ff);
            height: 120px;
            position: relative;
        }

        .profile-photo-wrapper {
            position: absolute;
            bottom: -50px;
            left: 50%;
            transform: translateX(-50%);
        }

        .profile-photo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 4px solid var(--card-bg);
            background-color: #e5e5ea;
            object-fit: cover;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: 600;
            color: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .card-body {
            padding: 65px 24px 30px 24px;
            text-align: center;
        }

        .profile-name {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 4px;
            color: var(--text-color);
        }

        .profile-title {
            font-size: 14px;
            font-weight: 500;
            color: var(--secondary-text);
            margin-bottom: 2px;
        }

        .profile-company {
            font-size: 14px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 16px;
        }

        .profile-bio {
            font-size: 14px;
            color: #48484a;
            margin-bottom: 24px;
            text-align: left;
            background-color: #fafafa;
            padding: 12px 16px;
            border-radius: 10px;
            border: 1px dashed var(--border-color);
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }

        .action-button {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: var(--text-color);
            font-size: 11px;
            font-weight: 500;
        }

        .action-icon {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background-color: #f2f2f7;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            margin-bottom: 6px;
            transition: background-color 0.2s;
        }

        .action-button:hover .action-icon {
            background-color: #e5e5ea;
        }

        .save-btn {
            display: block;
            width: 100%;
            background-color: var(--primary-color);
            color: white;
            text-align: center;
            padding: 14px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 15px;
            text-decoration: none;
            transition: background-color 0.2s;
            box-shadow: 0 4px 12px rgba(0, 122, 255, 0.2);
        }

        .save-btn:hover {
            background-color: var(--primary-hover);
        }

        /* Exchange Form */
        .exchange-card {
            background-color: var(--card-bg);
            border-radius: 18px;
            border: 1px solid var(--border-color);
            padding: 24px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.05);
        }

        .exchange-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .exchange-subtitle {
            font-size: 13px;
            color: var(--secondary-text);
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 16px;
            text-align: left;
        }

        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #48484a;
            margin-bottom: 6px;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            background-color: #fafafa;
            transition: border-color 0.2s, background-color 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            background-color: #ffffff;
        }

        .submit-btn {
            width: 100%;
            background-color: #34c759;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: background-color 0.2s;
            box-shadow: 0 4px 12px rgba(52, 199, 89, 0.2);
        }

        .submit-btn:hover {
            background-color: #28a745;
        }

        /* Success screen overlay */
        .toast-container {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1000;
            width: 90%;
            max-width: 400px;
        }

        .toast-alert {
            background-color: #30d158;
            color: white;
            padding: 12px 16px;
            border-radius: 10px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            font-size: 14px;
            font-weight: 500;
            text-align: center;
            margin-bottom: 10px;
            opacity: 0;
            transform: translateY(-20px);
            transition: opacity 0.3s, transform 0.3s;
        }

        .toast-alert.show {
            opacity: 1;
            transform: translateY(0);
        }
        
        .toast-alert.error {
            background-color: #ff453a;
        }
    </style>
</head>
<body>

    <div class="toast-container" id="toast-container"></div>

    <div class="container">
        <!-- Digital Card -->
        <div class="card">
            <div class="card-header">
                <div class="profile-photo-wrapper">
                    <?php if (!empty($profile['profile_photo']) && file_exists(__DIR__ . '/' . $profile['profile_photo'])): ?>
                        <img class="profile-photo" src="<?php echo e($profile['profile_photo']); ?>" alt="Profile">
                    <?php else: ?>
                        <div class="profile-photo" style="background-color: #007aff;"><?php echo e($userInitial); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card-body">
                <h1 class="profile-name"><?php echo e($showName ?: 'Digital Card'); ?></h1>
                
                <?php if (!empty($showDesignation)): ?>
                    <p class="profile-title"><?php echo e($showDesignation); ?></p>
                <?php endif; ?>
                
                <?php if (!empty($showCompany)): ?>
                    <p class="profile-company"><?php echo e($showCompany); ?></p>
                <?php endif; ?>

                <?php if (!empty($showBio)): ?>
                    <div class="profile-bio">
                        <?php echo nl2br(e($showBio)); ?>
                    </div>
                <?php endif; ?>

                <!-- Action Button Grids -->
                <div class="action-grid">
                    <?php if (!empty($showPhone)): ?>
                        <a href="tel:<?php echo e($showPhone); ?>" class="action-button">
                            <span class="action-icon">📞</span>
                            Call
                        </a>
                        <a href="https://wa.me/<?php echo e(preg_replace('/[^0-9]/', '', $showPhone)); ?>" class="action-button" target="_blank">
                            <span class="action-icon">💬</span>
                            WhatsApp
                        </a>
                    <?php endif; ?>

                    <?php if (!empty($showEmail)): ?>
                        <a href="mailto:<?php echo e($showEmail); ?>" class="action-button">
                            <span class="action-icon">✉️</span>
                            Email
                        </a>
                    <?php endif; ?>

                    <?php if (!empty($showLinkedin)): ?>
                        <a href="<?php echo e($showLinkedin); ?>" class="action-button" target="_blank">
                            <span class="action-icon">🔗</span>
                            LinkedIn
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Save to Contacts (vCard Download) -->
                <a href="api/vcard.php?slug=<?php echo e($slug); ?>" class="save-btn">📥 Save Contact</a>
            </div>
        </div>

        <!-- Guest Contact Exchange Form -->
        <div class="exchange-card">
            <h2 class="exchange-title">Share Your Contact Details</h2>
            <p class="exchange-subtitle">Let <?php echo e(e(explode(' ', $showName)[0])); ?> remember you. Share your info to exchange connections.</p>
            
            <form id="exchangeForm">
                <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                <input type="hidden" name="slug" value="<?php echo e($slug); ?>">

                <div class="form-group">
                    <label class="form-label" for="full_name">Full Name *</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" required placeholder="e.g. John Doe">
                </div>

                <div class="form-group">
                    <label class="form-label" for="company">Company</label>
                    <input type="text" class="form-control" id="company" name="company" placeholder="e.g. Acme Corp">
                </div>

                <div class="form-group">
                    <label class="form-label" for="designation">Designation</label>
                    <input type="text" class="form-control" id="designation" name="designation" placeholder="e.g. Project Manager">
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">Phone Number *</label>
                    <input type="tel" class="form-control" id="phone" name="phone" required placeholder="e.g. +91 98230 12345">
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email Address *</label>
                    <input type="email" class="form-control" id="email" name="email" required placeholder="e.g. john@example.com">
                </div>

                <div class="form-group">
                    <label class="form-label" for="notes">Quick Note</label>
                    <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="e.g. Met at the conference, let's connect!"></textarea>
                </div>

                <button type="submit" class="submit-btn">🤝 Share Contact</button>
            </form>
        </div>
    </div>

    <script>
        function showToast(message, isError = false) {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast-alert ${isError ? 'error' : ''}`;
            toast.textContent = message;
            container.appendChild(toast);
            
            // Trigger animation
            setTimeout(() => toast.classList.add('show'), 50);
            
            // Remove after 4 seconds
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }

        document.getElementById('exchangeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('.submit-btn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Sharing...';

            fetch('api/exchange.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('🎉 Contact shared successfully!');
                    document.getElementById('exchangeForm').reset();
                } else {
                    showToast(data.message || 'An error occurred.', true);
                }
            })
            .catch(err => {
                showToast('Network error, please try again.', true);
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = '🤝 Share Contact';
            });
        });
    </script>
</body>
</html>
