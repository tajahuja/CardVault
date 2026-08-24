<?php
/**
 * Add Contact Page
 */

$pageTitle = 'Add Contact';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/csrf.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Add New Contact</h1>
        <p>Manually create a new CRM contact record</p>
    </div>
    <div class="header-actions">
        <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form id="contact-form" action="api/save_contact.php" method="POST">
            <?php csrf_field(); ?>
            <input type="hidden" id="ignore_duplicate" name="ignore_duplicate" value="0">
            <input type="hidden" name="source" value="Manual Entry">

            <!-- Section: Personal Information -->
            <div style="margin-bottom: 2rem;">
                <h3 style="border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem; margin-bottom: 1rem; color: var(--primary-color);">Personal Information</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" placeholder="John Doe">
                    </div>
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" placeholder="John">
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" placeholder="Doe">
                    </div>
                </div>
            </div>

            <!-- Section: Professional Information -->
            <div style="margin-bottom: 2rem;">
                <h3 style="border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem; margin-bottom: 1rem; color: var(--primary-color);">Professional Information</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
                    <div class="form-group">
                        <label for="job_title">Job Title</label>
                        <input type="text" id="job_title" name="job_title" placeholder="Software Architect">
                    </div>
                    <div class="form-group">
                        <label for="company">Company</label>
                        <input type="text" id="company" name="company" placeholder="Acme Corporation">
                    </div>
                </div>
            </div>

            <!-- Section: Contact Information -->
            <div style="margin-bottom: 2rem;">
                <h3 style="border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem; margin-bottom: 1rem; color: var(--primary-color);">Contact Information</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="text" id="phone" name="phone" placeholder="+91 98765 43210">
                    </div>
                    <div class="form-group">
                        <label for="alternate_phone">Alternate Phone</label>
                        <input type="text" id="alternate_phone" name="alternate_phone" placeholder="+1 555-0199">
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" placeholder="john.doe@acme.com">
                    </div>
                    <div class="form-group">
                        <label for="alternate_email">Alternate Email</label>
                        <input type="email" id="alternate_email" name="alternate_email" placeholder="john.personal@gmail.com">
                    </div>
                </div>
            </div>

            <!-- Section: Online Profiles -->
            <div style="margin-bottom: 2rem;">
                <h3 style="border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem; margin-bottom: 1rem; color: var(--primary-color);">Online Profiles</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
                    <div class="form-group">
                        <label for="website">Website URL</label>
                        <input type="url" id="website" name="website" placeholder="https://www.acme.com">
                    </div>
                    <div class="form-group">
                        <label for="linkedin_url">LinkedIn Profile URL</label>
                        <input type="url" id="linkedin_url" name="linkedin_url" placeholder="https://linkedin.com/in/johndoe">
                    </div>
                </div>
            </div>

            <!-- Section: Location -->
            <div style="margin-bottom: 2rem;">
                <h3 style="border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem; margin-bottom: 1rem; color: var(--primary-color);">Location Details</h3>
                <div style="margin-bottom: 1rem;">
                    <div class="form-group">
                        <label for="address">Street Address</label>
                        <textarea id="address" name="address" placeholder="123 Corporate Way, Suite 400" style="min-height: 80px;"></textarea>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem;">
                    <div class="form-group">
                        <label for="city">City</label>
                        <input type="text" id="city" name="city" placeholder="Mumbai">
                    </div>
                    <div class="form-group">
                        <label for="state">State / Province</label>
                        <input type="text" id="state" name="state" placeholder="Maharashtra">
                    </div>
                    <div class="form-group">
                        <label for="country">Country</label>
                        <input type="text" id="country" name="country" placeholder="India">
                    </div>
                    <div class="form-group">
                        <label for="postal_code">PIN / Postal Code</label>
                        <input type="text" id="postal_code" name="postal_code" placeholder="400001">
                    </div>
                </div>
            </div>

            <!-- Section: Relationship Info -->
            <div style="margin-bottom: 2rem;">
                <h3 style="border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem; margin-bottom: 1rem; color: var(--primary-color);">Relationship Details</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div class="form-group">
                        <label for="date_met">Date Met</label>
                        <input type="date" id="date_met" name="date_met" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="place_met">Place Met</label>
                        <input type="text" id="place_met" name="place_met" placeholder="Annual Expo or Online Meet">
                    </div>
                    <div class="form-group">
                        <label for="follow_up_date">Follow-up Date</label>
                        <input type="date" id="follow_up_date" name="follow_up_date">
                    </div>
                    <div class="form-group">
                        <label for="status">Relationship Status</label>
                        <select id="status" name="status">
                            <option value="New" selected>New</option>
                            <option value="Contacted">Contacted</option>
                            <option value="Follow-up">Follow-up</option>
                            <option value="Converted">Converted</option>
                            <option value="Not Interested">Not Interested</option>
                            <option value="Archived">Archived</option>
                        </select>
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button type="submit" class="btn btn-primary" id="save-btn">Save Contact</button>
                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
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
            <p>What would you like to do?</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" id="dup-update-btn">Update Existing</button>
            <button type="button" class="btn btn-secondary" id="dup-create-btn">Create New Anyway</button>
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
    display: flex;
    align-items: center;
    gap: 0.5rem;
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
    flex-wrap: wrap;
}
</style>

<script>
    document.getElementById('contact-form').addEventListener('submit', function(e) {
        e.preventDefault();
        saveContact();
    });

    let existingIdForUpdate = null;

    function saveContact() {
        const form = document.getElementById('contact-form');
        const saveBtn = document.getElementById('save-btn');
        const originalText = saveBtn.textContent;
        
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';
        
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
                    throw new Error(data.message || 'An error occurred while saving the contact.');
                }
                return data;
            });
        })
        .then(data => {
            if (data.duplicate) {
                // Duplicate detected
                document.getElementById('duplicate-warning-text').textContent = data.reason;
                existingIdForUpdate = data.existing_id;
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
        // Retry saving, ignoring duplicate warning
        document.getElementById('ignore_duplicate').value = "1";
        document.getElementById('duplicate-modal').classList.add('hidden');
        saveContact();
    });

    document.getElementById('dup-update-btn').addEventListener('click', function() {
        if (existingIdForUpdate) {
            // Transform form submission into an update of the existing contact
            const form = document.getElementById('contact-form');
            form.action = 'api/update_contact.php';
            
            // Append or modify inputs
            let idInput = document.getElementById('existing-id-input');
            if (!idInput) {
                idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.id = 'existing-id-input';
                idInput.name = 'id';
                form.appendChild(idInput);
            }
            idInput.value = existingIdForUpdate;
            
            document.getElementById('ignore_duplicate').value = "1";
            document.getElementById('duplicate-modal').classList.add('hidden');
            saveContact();
        }
    });
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
