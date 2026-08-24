<?php
/**
 * User Settings & Account Deletion Page
 */

$pageTitle = 'Settings';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/csrf.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>User Settings</h1>
        <p>Update your account security configurations</p>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
    <!-- Change Password Card -->
    <div class="card">
        <div class="card-header"><h3 class="card-title">🔐 Change Password</h3></div>
        <div class="card-body">
            <div id="pw-error-alert" class="alert alert-danger hidden"></div>
            <div id="pw-success-alert" class="alert alert-success hidden"></div>
            
            <form id="password-form" action="api/settings.php" method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required placeholder="••••••••">
                </div>
                
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required placeholder="Min. 8 characters" minlength="8">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required placeholder="Min. 8 characters" minlength="8">
                </div>
                
                <button type="submit" class="btn btn-primary btn-block" id="pw-submit-btn">Change Password</button>
            </form>
        </div>
    </div>

    <!-- Account Privacy and Deletion Card -->
    <div class="card">
        <div class="card-header"><h3 class="card-title" style="color: var(--danger-color);">⚠️ Account Privacy & Deletion</h3></div>
        <div class="card-body">
            <p style="font-size: 0.9rem; color: var(--text-color); margin-bottom: 1.5rem; line-height: 1.6;">
                In accordance with global privacy policies, you may completely delete your CardVault account and all associated data at any time. 
                This action will instantly and permanently erase all your saved contacts, call notes, custom tags, and uploaded card images from our servers. 
                <strong>This action is irreversible.</strong>
            </p>
            
            <button type="button" class="btn btn-danger btn-block" id="delete-trigger-btn">Delete My Account</button>
        </div>
    </div>
</div>

<!-- Account Deletion Confirmation Modal -->
<div id="delete-modal" class="modal-overlay hidden">
    <div class="modal-card">
        <div class="modal-header">
            <h3 style="color: var(--danger-color);">⚠️ Confirm Account Deletion</h3>
        </div>
        <div class="modal-body">
            <p style="margin-bottom: 1rem;">This will permanently delete your CardVault profile, along with all scanned card images, notes, tags, and contacts. You cannot undo this action.</p>
            
            <form id="delete-account-form" action="api/settings.php" method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="delete_account">
                
                <div class="form-group">
                    <label for="del_confirm_password">Please enter your password to authorize deletion</label>
                    <input type="password" id="del_confirm_password" name="confirm_password" required placeholder="Enter password to confirm">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="delete-cancel-btn">Cancel</button>
            <button type="button" class="btn btn-danger" id="delete-confirm-btn">Delete Permanently</button>
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
    // Handle Change Password Form Submit
    document.getElementById('password-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const form = this;
        const errAlert = document.getElementById('pw-error-alert');
        const succAlert = document.getElementById('pw-success-alert');
        const submitBtn = document.getElementById('pw-submit-btn');
        
        // Reset state
        errAlert.classList.add('hidden');
        succAlert.classList.add('hidden');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Updating...';
        
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
                    throw new Error(data.message || 'Unable to update password.');
                }
                return data;
            });
        })
        .then(data => {
            succAlert.textContent = data.message;
            succAlert.classList.remove('hidden');
            form.reset();
            submitBtn.disabled = false;
            submitBtn.textContent = 'Change Password';
        })
        .catch(error => {
            errAlert.textContent = error.message;
            errAlert.classList.remove('hidden');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Change Password';
        });
    });

    // Account Deletion Flow
    const deleteModal = document.getElementById('delete-modal');
    const deleteTrigger = document.getElementById('delete-trigger-btn');
    const deleteCancel = document.getElementById('delete-cancel-btn');
    const deleteConfirm = document.getElementById('delete-confirm-btn');
    const deleteForm = document.getElementById('delete-account-form');

    deleteTrigger.addEventListener('click', () => {
        deleteModal.classList.remove('hidden');
        document.getElementById('del_confirm_password').focus();
    });

    deleteCancel.addEventListener('click', () => {
        deleteModal.classList.add('hidden');
        deleteForm.reset();
    });

    deleteConfirm.addEventListener('click', () => {
        const passwordInput = document.getElementById('del_confirm_password');
        if (!passwordInput.value) {
            alert('Please enter your password to confirm account deletion.');
            passwordInput.focus();
            return;
        }
        
        deleteConfirm.disabled = true;
        deleteConfirm.textContent = 'Deleting Account...';
        
        const formData = new FormData(deleteForm);
        
        fetch(deleteForm.action, {
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
                    throw new Error(data.message || 'Unable to delete account.');
                }
                return data;
            });
        })
        .then(data => {
            alert('Your account and all associated data have been permanently deleted.');
            if (data.redirect) {
                window.location.href = data.redirect;
            } else {
                window.location.href = 'login.php';
            }
        })
        .catch(error => {
            alert(error.message);
            deleteConfirm.disabled = false;
            deleteConfirm.textContent = 'Delete Permanently';
        });
    });
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
