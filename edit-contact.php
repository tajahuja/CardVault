<?php
/**
 * Edit Contact Page
 */

$pageTitle = 'Edit Contact';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/csrf.php';
$pdo = require_once __DIR__ . '/includes/db.php';

$userId = $_SESSION['user_id'];
$contactId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($contactId <= 0) {
    header('Location: contacts.php');
    exit;
}

try {
    // Assert ownership at the database query level
    $stmt = $pdo->prepare("SELECT * FROM contacts WHERE id = :id AND user_id = :user_id LIMIT 1");
    $stmt->execute([
        'id' => $contactId,
        'user_id' => $userId
    ]);
    $contact = $stmt->fetch();
    
    if (!$contact) {
        // Contact not found or not owned
        echo '<div class="alert alert-danger" style="margin-top: 2rem;">Contact not found or you are not authorized to edit it. <a href="contacts.php">Back to contacts</a></div>';
        require_once __DIR__ . '/includes/footer.php';
        exit;
    }
} catch (\PDOException $e) {
    error_log("Edit contact load DB error: " . $e->getMessage());
    echo '<div class="alert alert-danger">An error occurred while loading contact details.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}
?>

<div class="page-header">
    <div class="page-title">
        <h1>Edit Contact: <?php echo e($contact['full_name']); ?></h1>
        <p>Modify contact profile details</p>
    </div>
    <div class="header-actions">
        <a href="contact.php?id=<?php echo $contact['id']; ?>" class="btn btn-secondary">Cancel</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form id="contact-form" action="api/update_contact.php" method="POST">
            <?php csrf_field(); ?>
            <input type="hidden" id="contact_id" name="id" value="<?php echo $contact['id']; ?>">
            <input type="hidden" id="ignore_duplicate" name="ignore_duplicate" value="0">

            <!-- Section: Personal Information -->
            <div style="margin-bottom: 2rem;">
                <h3 style="border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem; margin-bottom: 1rem; color: var(--primary-color);">Personal Information</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" value="<?php echo e($contact['full_name']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" value="<?php echo e($contact['first_name']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" value="<?php echo e($contact['last_name']); ?>">
                    </div>
                </div>
            </div>

            <!-- Section: Professional Information -->
            <div style="margin-bottom: 2rem;">
                <h3 style="border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem; margin-bottom: 1rem; color: var(--primary-color);">Professional Information</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
                    <div class="form-group">
                        <label for="job_title">Job Title</label>
                        <input type="text" id="job_title" name="job_title" value="<?php echo e($contact['job_title']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="company">Company</label>
                        <input type="text" id="company" name="company" value="<?php echo e($contact['company']); ?>">
                    </div>
                </div>
                <div class="form-group" style="margin-top: 1rem;">
                    <label for="industry">Industry / Category</label>
                    <select id="industry" name="industry">
                        <?php $indVal = $contact['industry'] ?? ''; ?>
                        <option value="" <?php echo $indVal === '' ? 'selected' : ''; ?>>Select Industry</option>
                        <option value="Hospitality" <?php echo $indVal === 'Hospitality' ? 'selected' : ''; ?>>Hospitality (Hotel/Resort)</option>
                        <option value="Travel" <?php echo $indVal === 'Travel' ? 'selected' : ''; ?>>Travel & Tourism</option>
                        <option value="Finance" <?php echo $indVal === 'Finance' ? 'selected' : ''; ?>>Finance & Banking</option>
                        <option value="Real Estate" <?php echo $indVal === 'Real Estate' ? 'selected' : ''; ?>>Real Estate</option>
                        <option value="Technology" <?php echo $indVal === 'Technology' ? 'selected' : ''; ?>>Technology & IT</option>
                        <option value="Consulting" <?php echo $indVal === 'Consulting' ? 'selected' : ''; ?>>Consulting</option>
                        <option value="Education" <?php echo $indVal === 'Education' ? 'selected' : ''; ?>>Education</option>
                        <option value="Government" <?php echo $indVal === 'Government' ? 'selected' : ''; ?>>Government</option>
                        <option value="Other" <?php echo $indVal === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
            </div>

            <!-- Section: Contact Information -->
            <div style="margin-bottom: 2rem;">
                <h3 style="border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem; margin-bottom: 1rem; color: var(--primary-color);">Contact Information</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="text" id="phone" name="phone" value="<?php echo e($contact['phone']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="alternate_phone">Alternate Phone</label>
                        <input type="text" id="alternate_phone" name="alternate_phone" value="<?php echo e($contact['alternate_phone']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" value="<?php echo e($contact['email']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="alternate_email">Alternate Email</label>
                        <input type="email" id="alternate_email" name="alternate_email" value="<?php echo e($contact['alternate_email']); ?>">
                    </div>
                </div>
            </div>

            <!-- Section: Online Profiles -->
            <div style="margin-bottom: 2rem;">
                <h3 style="border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem; margin-bottom: 1rem; color: var(--primary-color);">Online Profiles</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
                    <div class="form-group">
                        <label for="website">Website URL</label>
                        <input type="url" id="website" name="website" value="<?php echo e($contact['website']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="linkedin_url">LinkedIn Profile URL</label>
                        <input type="url" id="linkedin_url" name="linkedin_url" value="<?php echo e($contact['linkedin_url']); ?>">
                    </div>
                </div>
            </div>

            <!-- Section: Location -->
            <div style="margin-bottom: 2rem;">
                <h3 style="border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem; margin-bottom: 1rem; color: var(--primary-color);">Location Details</h3>
                <div style="margin-bottom: 1rem;">
                    <div class="form-group">
                        <label for="address">Street Address</label>
                        <textarea id="address" name="address" style="min-height: 80px;"><?php echo e($contact['address']); ?></textarea>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem;">
                    <div class="form-group">
                        <label for="city">City</label>
                        <input type="text" id="city" name="city" value="<?php echo e($contact['city']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="state">State / Province</label>
                        <input type="text" id="state" name="state" value="<?php echo e($contact['state']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="country">Country</label>
                        <input type="text" id="country" name="country" value="<?php echo e($contact['country']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="postal_code">PIN / Postal Code</label>
                        <input type="text" id="postal_code" name="postal_code" value="<?php echo e($contact['postal_code']); ?>">
                    </div>
                </div>
            </div>

            <!-- Section: Relationship Info -->
            <div style="margin-bottom: 2rem;">
                <h3 style="border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem; margin-bottom: 1rem; color: var(--primary-color);">Relationship Details</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div class="form-group">
                        <label for="date_met">Date Met</label>
                        <input type="date" id="date_met" name="date_met" value="<?php echo $contact['date_met']; ?>">
                    </div>
                    <div class="form-group">
                        <label for="place_met">Place Met</label>
                        <input type="text" id="place_met" name="place_met" value="<?php echo e($contact['place_met']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="follow_up_date">Follow-up Date</label>
                        <input type="date" id="follow_up_date" name="follow_up_date" value="<?php echo $contact['follow_up_date']; ?>">
                    </div>
                    <div class="form-group">
                        <label for="status">Relationship Status</label>
                        <select id="status" name="status">
                            <?php
                            $statuses = ['New', 'Contacted', 'Follow-up', 'Converted', 'Not Interested', 'Archived'];
                            foreach ($statuses as $stat) {
                                $selected = ($contact['status'] === $stat) ? 'selected' : '';
                                echo '<option value="' . $stat . '" ' . $selected . '>' . $stat . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="form-group" style="margin-top: 1rem;">
                    <label for="lead_source">Lead Source</label>
                    <select id="lead_source" name="lead_source">
                        <?php $srcVal = $contact['lead_source'] ?? ''; ?>
                        <option value="Manual Entry" <?php echo $srcVal === 'Manual Entry' ? 'selected' : ''; ?>>Manual Entry</option>
                        <option value="Business Card" <?php echo $srcVal === 'Business Card' ? 'selected' : ''; ?>>Business Card</option>
                        <option value="Conference" <?php echo $srcVal === 'Conference' ? 'selected' : ''; ?>>Conference / Event</option>
                        <option value="Exhibition" <?php echo $srcVal === 'Exhibition' ? 'selected' : ''; ?>>Exhibition / Trade Show</option>
                        <option value="Referral" <?php echo $srcVal === 'Referral' ? 'selected' : ''; ?>>Referral</option>
                        <option value="Cold Contact" <?php echo $srcVal === 'Cold Contact' ? 'selected' : ''; ?>>Cold Contact</option>
                        <option value="Other" <?php echo $srcVal === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button type="submit" class="btn btn-primary" id="save-btn">Update Contact</button>
                <a href="contact.php?id=<?php echo $contact['id']; ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<!-- Duplicate Modal System -->
<div id="duplicate-modal" class="modal-overlay hidden">
    <div class="modal-card">
        <div class="modal-header">
            <h3>⚠️ Possible Duplicate Contact Found</h3>
        </div>
        <div class="modal-body">
            <p id="duplicate-warning-text"></p>
            <p>Would you like to save these changes anyway?</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" id="dup-create-btn">Update Anyway</button>
            <button type="button" class="btn btn-danger" id="dup-cancel-btn">Cancel</button>
        </div>
    </div>
</div>

<style>
/* Modal CSS */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 999;
}
.modal-card {
    background-color: var(--surface-color);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg);
    width: 90%;
    max-width: 500px;
    padding: 1.75rem;
    border: 1px solid var(--border-color);
}
.modal-header h3 {
    color: var(--secondary-color);
    margin-bottom: 1rem;
    font-size: 1.25rem;
}
.modal-body {
    color: var(--text-color);
    font-size: 0.95rem;
    line-height: 1.6;
    margin-bottom: 1.5rem;
}
.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}
</style>

<script>
    document.getElementById('contact-form').addEventListener('submit', function(e) {
        e.preventDefault();
        updateContact();
    });

    function updateContact() {
        const form = document.getElementById('contact-form');
        const saveBtn = document.getElementById('save-btn');
        const originalText = saveBtn.textContent;
        
        saveBtn.disabled = true;
        saveBtn.textContent = 'Updating...';
        
        const formData = new FormData(form);
        
        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json',
                'X-CSRF-Token': formData.get('csrf_token')
            }
        })
        .then(response => {
            return response.json().then(data => {
                if (!response.ok) {
                    throw new Error(data.message || 'An error occurred while updating the contact.');
                }
                return data;
            });
        })
        .then(data => {
            if (data.duplicate) {
                // Duplicate detected
                document.getElementById('duplicate-warning-text').textContent = data.reason;
                document.getElementById('duplicate-modal').classList.remove('hidden');
                
                // Reset save button state
                saveBtn.disabled = false;
                saveBtn.textContent = originalText;
            } else if (data.success && data.redirect) {
                window.location.href = data.redirect;
            } else {
                throw new Error('Unexpected response format.');
            }
        })
        .catch(error => {
            alert(error.message);
            saveBtn.disabled = false;
            saveBtn.textContent = originalText;
        });
    }

    // Modal Interaction
    document.getElementById('dup-cancel-btn').addEventListener('click', function() {
        document.getElementById('duplicate-modal').classList.add('hidden');
    });

    document.getElementById('dup-create-btn').addEventListener('click', function() {
        document.getElementById('ignore_duplicate').value = "1";
        document.getElementById('duplicate-modal').classList.add('hidden');
        updateContact();
    });
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
