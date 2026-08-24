<?php
/**
 * User Registration Page
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

// If already logged in, redirect to dashboard
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - CardVault</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="auth-body">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="logo">
                    <span class="logo-icon">🗂️</span>
                    <span class="logo-text">Card<span>Vault</span></span>
                </div>
                <p class="auth-subtitle">Create your CardVault account</p>
            </div>

            <div id="error-alert" class="alert alert-danger hidden"></div>

            <form id="register-form" method="POST" action="api/register.php" class="auth-form">
                <?php csrf_field(); ?>
                
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" required placeholder="John Doe" autocomplete="name" minlength="2">
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required placeholder="john@company.com" autocomplete="email">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required placeholder="Min. 8 characters" autocomplete="new-password" minlength="8">
                </div>

                <button type="submit" class="btn btn-primary btn-block" id="submit-btn">
                    <span class="btn-text">Register Account</span>
                    <span class="spinner hidden"></span>
                </button>
            </form>

            <div class="auth-footer">
                <p>Already have an account? <a href="login.php">Sign In here</a></p>
                <p class="privacy-link"><a href="privacy.php">Privacy Policy</a></p>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('register-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const form = this;
            const errorAlert = document.getElementById('error-alert');
            const submitBtn = document.getElementById('submit-btn');
            const btnText = submitBtn.querySelector('.btn-text');
            const spinner = submitBtn.querySelector('.spinner');

            // Reset UI state
            errorAlert.classList.add('hidden');
            errorAlert.textContent = '';
            submitBtn.disabled = true;
            btnText.textContent = 'Registering...';
            spinner.classList.remove('hidden');

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
                        throw new Error(data.message || 'Registration failed. Please try again.');
                    }
                    return data;
                });
            })
            .then(data => {
                if (data.success && data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    throw new Error('An unexpected response was received.');
                }
            })
            .catch(error => {
                errorAlert.textContent = error.message;
                errorAlert.classList.remove('hidden');
                
                // Restore button state
                submitBtn.disabled = false;
                btnText.textContent = 'Register Account';
                spinner.classList.add('hidden');
            });
        });
    </script>
</body>
</html>
