<?php
/**
 * Digital Business Card Editor Dashboard (My Card)
 */

$pageTitle = 'My Digital Card';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/csrf.php';
$pdo = require __DIR__ . '/includes/db.php';

$userId = $_SESSION['user_id'];
$notice = '';
$noticeClass = 'info';

// 1. Fetch current profile
try {
    $stmt = $pdo->prepare("SELECT * FROM user_profiles WHERE user_id = :user_id LIMIT 1");
    $stmt->execute(['user_id' => $userId]);
    $profile = $stmt->fetch();
} catch (PDOException $e) {
    $profile = false;
    $notice = 'Error loading profile details.';
    $noticeClass = 'danger';
}

// 2. Handle POST Request (Save Profile Changes)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    $slug = trim(strtolower($_POST['slug'] ?? ''));
    $fullName = trim($_POST['full_name'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $linkedinUrl = trim($_POST['linkedin_url'] ?? '');
    $bio = trim($_POST['bio'] ?? '');

    // Rebuild public fields toggles JSON
    $publicFields = [
        'full_name' => isset($_POST['show_full_name']) && $_POST['show_full_name'] === '1',
        'designation' => isset($_POST['show_designation']) && $_POST['show_designation'] === '1',
        'company' => isset($_POST['show_company']) && $_POST['show_company'] === '1',
        'phone' => isset($_POST['show_phone']) && $_POST['show_phone'] === '1',
        'email' => isset($_POST['show_email']) && $_POST['show_email'] === '1',
        'website' => isset($_POST['show_website']) && $_POST['show_website'] === '1',
        'linkedin_url' => isset($_POST['show_linkedin_url']) && $_POST['show_linkedin_url'] === '1',
        'bio' => isset($_POST['show_bio']) && $_POST['show_bio'] === '1'
    ];
    $publicFieldsJson = json_encode($publicFields);

    // Validation
    if (empty($slug) || !preg_match('/^[a-z0-9_-]+$/', $slug)) {
        $notice = 'Slug is required and must contain only lowercase letters, numbers, dashes, or underscores.';
        $noticeClass = 'danger';
    } elseif (empty($fullName)) {
        $notice = 'Full Name is required.';
        $noticeClass = 'danger';
    } else {
        try {
            // Check slug uniqueness
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM user_profiles WHERE slug = :slug AND user_id != :user_id");
            $stmtCheck->execute(['slug' => $slug, 'user_id' => $userId]);
            $exists = $stmtCheck->fetchColumn();

            if ($exists > 0) {
                $notice = 'The slug is already taken by another profile. Please choose a different one.';
                $noticeClass = 'danger';
            } else {
                // Handle Profile Photo Upload
                $profilePhotoPath = $profile['profile_photo'] ?? null;
                
                if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                    $fileTmpPath = $_FILES['profile_photo']['tmp_name'];
                    $fileName = $_FILES['profile_photo']['name'];
                    $fileSize = $_FILES['profile_photo']['size'];
                    $fileType = $_FILES['profile_photo']['type'];
                    $fileNameCmps = explode(".", $fileName);
                    $fileExtension = strtolower(end($fileNameCmps));

                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (in_array($fileExtension, $allowedExtensions)) {
                        if ($fileSize <= 2 * 1024 * 1024) { // 2MB limit
                            $uploadDir = __DIR__ . '/uploads/profiles/';
                            if (!is_dir($uploadDir)) {
                                mkdir($uploadDir, 0777, true);
                            }
                            
                            $newFileName = 'profile_' . $userId . '_' . time() . '.' . $fileExtension;
                            $destPath = $uploadDir . $newFileName;
                            
                            if (move_uploaded_file($fileTmpPath, $destPath)) {
                                // Delete old photo file if it exists
                                if ($profilePhotoPath && file_exists(__DIR__ . '/' . $profilePhotoPath)) {
                                    @unlink(__DIR__ . '/' . $profilePhotoPath);
                                }
                                $profilePhotoPath = 'uploads/profiles/' . $newFileName;
                            } else {
                                $notice = 'Error moving uploaded profile photo.';
                                $noticeClass = 'danger';
                            }
                        } else {
                            $notice = 'Profile photo file size must be less than 2MB.';
                            $noticeClass = 'danger';
                        }
                    } else {
                        $notice = 'Allowed file extensions for photo: JPG, JPEG, PNG, GIF.';
                        $noticeClass = 'danger';
                    }
                }

                if ($noticeClass !== 'danger') {
                    if ($profile) {
                        // Update
                        $sql = "UPDATE user_profiles 
                                SET slug = :slug, full_name = :full_name, designation = :designation, company = :company, 
                                    phone = :phone, email = :email, website = :website, linkedin_url = :linkedin_url, 
                                    bio = :bio, profile_photo = :profile_photo, public_fields_json = :public_fields_json
                                WHERE user_id = :user_id";
                    } else {
                        // Insert
                        $sql = "INSERT INTO user_profiles (
                                    user_id, slug, full_name, designation, company, phone, email, website, 
                                    linkedin_url, bio, profile_photo, public_fields_json
                                ) VALUES (
                                    :user_id, :slug, :full_name, :designation, :company, :phone, :email, :website, 
                                    :linkedin_url, :bio, :profile_photo, :public_fields_json
                                )";
                    }

                    $stmtSave = $pdo->prepare($sql);
                    $stmtSave->execute([
                        'user_id' => $userId,
                        'slug' => $slug,
                        'full_name' => $fullName,
                        'designation' => $designation !== '' ? $designation : null,
                        'company' => $company !== '' ? $company : null,
                        'phone' => $phone !== '' ? $phone : null,
                        'email' => $email !== '' ? $email : null,
                        'website' => $website !== '' ? $website : null,
                        'linkedin_url' => $linkedinUrl !== '' ? $linkedinUrl : null,
                        'bio' => $bio !== '' ? $bio : null,
                        'profile_photo' => $profilePhotoPath,
                        'public_fields_json' => $publicFieldsJson
                    ]);

                    $notice = 'Digital Business Card profile updated successfully!';
                    $noticeClass = 'success';

                    // Refresh profile details in memory
                    $stmt->execute(['user_id' => $userId]);
                    $profile = $stmt->fetch();
                }
            }
        } catch (PDOException $e) {
            $notice = 'Database error: ' . $e->getMessage();
            $noticeClass = 'danger';
        }
    }
}

// 3. Resolve public paths if profile exists
$publicUrl = '';
$qrCodeUrl = '';
$qrDownloadUrl = '';
$profileFields = [];

if ($profile) {
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
    $host = $_SERVER['HTTP_HOST'];
    $path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    $publicUrl = "$scheme://$host$path/c.php?slug=" . urlencode($profile['slug']);
    
    $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=" . urlencode($publicUrl);
    $qrDownloadUrl = "https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=" . urlencode($publicUrl);
    $profileFields = json_decode($profile['public_fields_json'] ?? '{}', true);
} else {
    // Fill default inputs from session user details
    $profile = [
        'slug' => '',
        'full_name' => $_SESSION['user_name'] ?? '',
        'designation' => '',
        'company' => '',
        'phone' => '',
        'email' => $_SESSION['user_email'] ?? '',
        'website' => '',
        'linkedin_url' => '',
        'bio' => '',
        'profile_photo' => ''
    ];
    $profileFields = [
        'full_name' => true,
        'designation' => true,
        'company' => true,
        'phone' => true,
        'email' => true,
        'website' => true,
        'linkedin_url' => true,
        'bio' => true
    ];
}

$csrfToken = generate_csrf_token();
?>

<div class="main-content">
    <div class="content-header">
        <h1>My Digital Business Card</h1>
        <p class="subtitle">Customize your public card, manage shared fields, and view your visitor QR code.</p>
    </div>

    <?php if ($notice): ?>
        <div class="alert alert-<?php echo $noticeClass; ?> alert-dismissible" role="alert">
            <?php echo e($notice); ?>
        </div>
    <?php endif; ?>

    <div class="dashboard-grid" style="grid-template-columns: 2fr 1fr; gap: 24px;">
        <!-- Profile Config Form -->
        <div class="card-widget">
            <h2 class="section-title">Profile Editor</h2>
            <form action="my-card.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label class="form-label" for="slug">Public URL Slug *</label>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="color: #666; font-size: 14px;">/c/</span>
                            <input type="text" class="form-control" id="slug" name="slug" required value="<?php echo e($profile['slug']); ?>" placeholder="john-doe" style="flex-grow: 1;">
                        </div>
                        <small id="slug-feedback" style="display: block; font-size: 11px; margin-top: 4px; font-weight: 600;"></small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="profile_photo">Profile Photo</label>
                        <input type="file" class="form-control" id="profile_photo" name="profile_photo" accept="image/*">
                        <small style="color: #666; font-size: 11px;">Max size 2MB (JPG, PNG, GIF)</small>
                    </div>
                </div>

                <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">

                <!-- Profile Attributes with Visibility Switches -->
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <!-- Row 1 -->
                    <div style="display: grid; grid-template-columns: 3fr 1fr; gap: 16px; align-items: flex-end;">
                        <div class="form-group">
                            <label class="form-label" for="full_name">Full Name *</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required value="<?php echo e($profile['full_name']); ?>" placeholder="e.g. John Doe">
                        </div>
                        <div style="margin-bottom: 12px;">
                            <label class="checkbox-label" style="display: flex; align-items: center; gap: 6px; font-size: 12px; cursor: pointer;">
                                <input type="checkbox" name="show_full_name" value="1" <?php echo ($profileFields['full_name'] ?? true) ? 'checked' : ''; ?>> Public
                            </label>
                        </div>
                    </div>

                    <!-- Row 2 -->
                    <div style="display: grid; grid-template-columns: 3fr 1fr; gap: 16px; align-items: flex-end;">
                        <div class="form-group">
                            <label class="form-label" for="designation">Designation / Title</label>
                            <input type="text" class="form-control" id="designation" name="designation" value="<?php echo e($profile['designation']); ?>" placeholder="e.g. Project Manager">
                        </div>
                        <div style="margin-bottom: 12px;">
                            <label class="checkbox-label" style="display: flex; align-items: center; gap: 6px; font-size: 12px; cursor: pointer;">
                                <input type="checkbox" name="show_designation" value="1" <?php echo ($profileFields['designation'] ?? true) ? 'checked' : ''; ?>> Public
                            </label>
                        </div>
                    </div>

                    <!-- Row 3 -->
                    <div style="display: grid; grid-template-columns: 3fr 1fr; gap: 16px; align-items: flex-end;">
                        <div class="form-group">
                            <label class="form-label" for="company">Company Name</label>
                            <input type="text" class="form-control" id="company" name="company" value="<?php echo e($profile['company']); ?>" placeholder="e.g. Acme Corp">
                        </div>
                        <div style="margin-bottom: 12px;">
                            <label class="checkbox-label" style="display: flex; align-items: center; gap: 6px; font-size: 12px; cursor: pointer;">
                                <input type="checkbox" name="show_company" value="1" <?php echo ($profileFields['company'] ?? true) ? 'checked' : ''; ?>> Public
                            </label>
                        </div>
                    </div>

                    <!-- Row 4 -->
                    <div style="display: grid; grid-template-columns: 3fr 1fr; gap: 16px; align-items: flex-end;">
                        <div class="form-group">
                            <label class="form-label" for="phone">Phone Number</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="<?php echo e($profile['phone']); ?>" placeholder="e.g. +91 98230 12345">
                        </div>
                        <div style="margin-bottom: 12px;">
                            <label class="checkbox-label" style="display: flex; align-items: center; gap: 6px; font-size: 12px; cursor: pointer;">
                                <input type="checkbox" name="show_phone" value="1" <?php echo ($profileFields['phone'] ?? true) ? 'checked' : ''; ?>> Public
                            </label>
                        </div>
                    </div>

                    <!-- Row 5 -->
                    <div style="display: grid; grid-template-columns: 3fr 1fr; gap: 16px; align-items: flex-end;">
                        <div class="form-group">
                            <label class="form-label" for="email">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo e($profile['email']); ?>" placeholder="e.g. john@example.com">
                        </div>
                        <div style="margin-bottom: 12px;">
                            <label class="checkbox-label" style="display: flex; align-items: center; gap: 6px; font-size: 12px; cursor: pointer;">
                                <input type="checkbox" name="show_email" value="1" <?php echo ($profileFields['email'] ?? true) ? 'checked' : ''; ?>> Public
                            </label>
                        </div>
                    </div>

                    <!-- Row 6 -->
                    <div style="display: grid; grid-template-columns: 3fr 1fr; gap: 16px; align-items: flex-end;">
                        <div class="form-group">
                            <label class="form-label" for="website">Website Link</label>
                            <input type="url" class="form-control" id="website" name="website" value="<?php echo e($profile['website']); ?>" placeholder="e.g. https://www.example.com">
                        </div>
                        <div style="margin-bottom: 12px;">
                            <label class="checkbox-label" style="display: flex; align-items: center; gap: 6px; font-size: 12px; cursor: pointer;">
                                <input type="checkbox" name="show_website" value="1" <?php echo ($profileFields['website'] ?? true) ? 'checked' : ''; ?>> Public
                            </label>
                        </div>
                    </div>

                    <!-- Row 7 -->
                    <div style="display: grid; grid-template-columns: 3fr 1fr; gap: 16px; align-items: flex-end;">
                        <div class="form-group">
                            <label class="form-label" for="linkedin_url">LinkedIn URL</label>
                            <input type="url" class="form-control" id="linkedin_url" name="linkedin_url" value="<?php echo e($profile['linkedin_url']); ?>" placeholder="e.g. https://linkedin.com/in/username">
                        </div>
                        <div style="margin-bottom: 12px;">
                            <label class="checkbox-label" style="display: flex; align-items: center; gap: 6px; font-size: 12px; cursor: pointer;">
                                <input type="checkbox" name="show_linkedin_url" value="1" <?php echo ($profileFields['linkedin_url'] ?? true) ? 'checked' : ''; ?>> Public
                            </label>
                        </div>
                    </div>

                    <!-- Row 8 -->
                    <div style="display: grid; grid-template-columns: 3fr 1fr; gap: 16px; align-items: flex-end;">
                        <div class="form-group">
                            <label class="form-label" for="bio">Bio / About Me</label>
                            <textarea class="form-control" id="bio" name="bio" rows="3" placeholder="Write a short pitch or description..."><?php echo e($profile['bio']); ?></textarea>
                        </div>
                        <div style="margin-bottom: 12px;">
                            <label class="checkbox-label" style="display: flex; align-items: center; gap: 6px; font-size: 12px; cursor: pointer;">
                                <input type="checkbox" name="show_bio" value="1" <?php echo ($profileFields['bio'] ?? true) ? 'checked' : ''; ?>> Public
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-actions" style="margin-top: 30px; display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary" style="padding: 12px 24px; border-radius: 8px; font-weight: 600;">Save Profile Updates</button>
                </div>
            </form>
        </div>

        <!-- Visitor QR & Sharing Widget -->
        <div style="display: flex; flex-direction: column; gap: 24px;">
            <div class="card-widget" style="text-align: center; padding: 30px;">
                <h2 class="section-title" style="margin-bottom: 20px;">DBC QR Code</h2>
                
                <?php if ($profile && !empty($profile['slug'])): ?>
                    <div style="background-color: #fafafa; padding: 20px; border-radius: 12px; border: 1px solid #eee; display: inline-block; margin-bottom: 20px;">
                        <img src="<?php echo $qrCodeUrl; ?>" alt="DBC QR Code" style="display: block; margin: 0 auto; width: 180px; height: 180px;">
                    </div>
                    
                    <p style="font-size: 13px; color: #666; margin-bottom: 20px; word-break: break-all;">
                        Public Link:<br>
                        <a href="<?php echo $publicUrl; ?>" target="_blank" style="font-weight: 600; color: #007aff; text-decoration: none;"><?php echo e($publicUrl); ?></a>
                    </p>

                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <a href="<?php echo $publicUrl; ?>" target="_blank" class="btn btn-secondary" style="width: 100%; border-radius: 8px; text-decoration: none; display: inline-block; text-align: center; padding: 10px;">👁️ View Live Card</a>
                        <a href="<?php echo $qrDownloadUrl; ?>" download="cardvault_qr.png" target="_blank" class="btn btn-success" style="width: 100%; border-radius: 8px; text-decoration: none; display: inline-block; text-align: center; padding: 10px; background-color: #34c759; color: white;">📥 Download QR</a>
                    </div>
                <?php else: ?>
                    <div style="color: #666; padding: 30px 10px; font-size: 13px;">
                        ⚠️ You need to set a custom Public URL Slug and click "Save Profile Updates" to generate your printable QR code.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Profile Photo preview -->
            <?php if (!empty($profile['profile_photo'])): ?>
                <div class="card-widget" style="text-align: center; padding: 20px;">
                    <h3 style="font-size: 14px; font-weight: 600; margin-bottom: 12px; color: #666;">Active Profile Photo</h3>
                    <img src="<?php echo e($profile['profile_photo']); ?>" alt="Profile Preview" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid #007aff;">
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Live slug uniqueness checker
    const slugInput = document.getElementById('slug');
    const feedback = document.getElementById('slug-feedback');

    slugInput.addEventListener('input', function() {
        const slug = this.value.trim().toLowerCase();
        
        // Remove invalid characters as user types
        this.value = slug.replace(/[^a-z0-9_-]/g, '');
        
        if (this.value.length < 3) {
            feedback.textContent = 'Too short';
            feedback.style.color = '#ff3b30';
            return;
        }

        fetch(`api/check_slug.php?slug=${encodeURIComponent(this.value)}`)
            .then(res => res.json())
            .then(data => {
                if (data.available) {
                    feedback.textContent = '✓ Slug is available';
                    feedback.style.color = '#34c759';
                } else {
                    feedback.textContent = '✗ ' + (data.message || 'Already taken');
                    feedback.style.color = '#ff3b30';
                }
            })
            .catch(() => {
                feedback.textContent = '';
            });
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
