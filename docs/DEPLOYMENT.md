# Deployment Documentation

This guide describes how to deploy CardVault to standard cPanel Shared Hosting (such as HostingRaja Premium SME).

## System Requirements
- PHP 8.0 or higher
- PDO MySQL extension
- MySQL 5.7+ or MariaDB 10.3+
- Web browser with camera and JavaScript support

---

## Deployment Steps

### STEP 1: Create MySQL Database
1. Log in to your cPanel dashboard.
2. Search for and open the **MySQL Database Wizard** or **MySQL Databases**.
3. Create a new database named `yourdomain_cardvault`. Note down the full name.

### STEP 2: Create MySQL User
1. In the same databases area, scroll to **MySQL Users -> Add New User**.
2. Generate a strong password and save it securely.
3. Create the user (e.g. `yourdomain_cvuser`).

### STEP 3: Assign User to Database
1. In cPanel, scroll to **Add User To Database**.
2. Select your newly created user and database, then click **Add**.
3. Check the box for **ALL PRIVILEGES** and click **Make Changes**.

### STEP 4: Open phpMyAdmin
1. Go back to the cPanel main page.
2. Locate and open **phpMyAdmin** in the Database section.

### STEP 5: Import Database Schema
1. Select your database (`yourdomain_cardvault`) from the left sidebar in phpMyAdmin.
2. Click the **Import** tab at the top.
3. Click **Choose File** and locate `database/schema.sql` from your local files.
4. Click **Go** (or Import) at the bottom to execute the table creation.

### STEP 6: Upload Application Files
1. Go back to cPanel and open **File Manager**.
2. Navigate to your website's root folder (usually `public_html/` or a subdirectory).
3. Click **Upload** and upload the CardVault files.
4. If uploading as a `.zip` file, extract the archive inside the destination folder.

### STEP 7: Configure database.php
1. In File Manager, navigate to `config/`.
2. Rename or copy `database.example.php` to `database.php`.
3. Right-click `database.php` and select **Edit**.
4. Replace the database host, name, username, and password with the credentials from Steps 1 & 2.
5. Save changes.

### STEP 8: Set Correct File Permissions
Ensure the following permissions:
- Directories: `755`
- PHP Files: `644`
- Uploads Directory (`uploads/business_cards/`): `755` (or `777` if required by some shared hosts, but `755` is preferred for safety).

### STEP 9: Protect Uploads Directory
1. Verify that `uploads/business_cards/.htaccess` is present.
2. Verify that directory browsing is blocked by the main `.htaccess` in the root folder.

### STEP 10: Configure root .htaccess
Ensure that the root `.htaccess` is present to deny direct directory listing:
```htaccess
Options -Indexes
```

### STEP 11: Open the Website
1. Open your browser and navigate to your website URL (e.g. `https://yourdomain.com/cardvault/`).
2. You will be redirected to the login page.

---

## Verification & Testing Steps

### STEP 12: Create First Account
1. On the login page, click **Register** (or go to `register.php`).
2. Input a name, email address, and a secure password. Click **Register**.
3. You should be automatically logged in and redirected to the dashboard.

### STEP 13: Test Scanning
1. On the dashboard, click **Scan Card**.
2. Grant camera permissions if prompted (on mobile, it will launch the camera; on desktop, it will prompt for image selection).
3. Upload/photograph a business card.

### STEP 14: Test Saving Contact
1. Once the image processes, confirm the parsed details.
2. Make manual corrections on the review screen.
3. Click **Save Contact**. You will be redirected to the contact details view.

### STEP 15: Test Editing Contact
1. On the contact profile view, click **Edit Contact**.
2. Change a field and save. Verify the update is recorded.

### STEP 16: Test Search
1. Return to the dashboard.
2. Input a company name or keyword into the search bar. Verify results filter dynamically.

### STEP 17: Test Follow-up
1. Open a contact profile.
2. Set a follow-up date and change status to `Follow-up`.
3. Return to the dashboard. Confirm that this contact appears in the "Follow-ups Due" list.

### STEP 18: Test Export
1. Navigate to the contacts list page.
2. Click **Export to CSV**. Verify the downloaded file contains the correct contact rows.
