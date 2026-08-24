# CardVault - Secure Business Card Scanner & Personal CRM

CardVault is a lightweight, mobile-first, production-ready web application designed to help professionals photograph or upload business cards, parse them locally in the browser using Tesseract.js OCR, and organize contacts in a secure personal CRM. 

The application is specifically engineered for **cPanel Shared Hosting environments (such as HostingRaja Premium SME)**. It uses a traditional PHP 8+ and MySQL/PDO architecture, eliminating any requirements for persistent Node.js servers, Docker containers, or command-line compilation.

---

## Key Features

1. **Mobile-First Card Scanner**: Integrated with browser-native camera inputs (`capture="environment"`). Works out of the box on mobile browsers without requiring a native app.
2. **Local Browser OCR**: Powered by Tesseract.js loaded from a CDN. The OCR processing happens entirely in the user's browser, preventing server overhead.
3. **Structured Heuristics Parser**: A regex-based parsing engine extracting Names, Job Titles, Companies, Emails, Phone Numbers, Websites, Social Profile Links, and Locations from raw scanned text.
4. **Interactive Single-Screen Review**: Review, correct, and edit parsed values immediately on the same page.
5. **Private Secure Storage**: Discards original filenames. Renames card images to secure random hashes, stores them inside blocked directories, and serves them via an authenticated PHP gateway that asserts file ownership.
6. **Multi-User Security Enforced**: Built with a database-level multi-tenant layout. Every read, write, update, delete, note, and tag operation validates contact ownership.
7. **CRM Capabilities**: Track follow-up dates, relationship statuses, tags, notes, and search/sort contacts. Includes full contacts directory export to CSV.

---

## Technology Stack

- **Backend**: PHP 8.0+
- **Database**: MySQL 5.7+ (or MariaDB 10.3+)
- **Database Driver**: PDO (PHP Data Objects)
- **Frontend**: HTML5, CSS3 (CSS Variables, Flexbox/Grid), Vanilla JavaScript
- **OCR Engine**: Tesseract.js (Client-Side)
- **Icons & Fonts**: Font-Free layout using native Emoji symbols and Inter typography via Google Fonts.

---

## Folder Structure

```
/cardvault
    /assets
        /css
            style.css           # Global CRM stylesheet (Indigo Slate Corporate theme)
        /js
            camera.js           # Handles camera/file selection and drag & drop UI
            ocr.js              # Runs Tesseract.js OCR & uploads image in parallel
            parser.js           # Deterministic regex heuristics parser
    /config
        database.php            # Active database configuration (gitignored)
        database.example.php    # Template database configuration
    /includes
        auth.php                # Secure session settings, logins & timeouts
        csrf.php                # Cryptographic CSRF token builder & validation
        db.php                  # Database connection builder (returns PDO)
        functions.php           # Global helper functions (escaping, JSON utility)
        header.php              # Global sidebar & layout wrapper (enforces auth)
        footer.php              # Global footer closer
    /api
        login.php               # REST authentication: Sign in
        register.php            # REST authentication: Sign up
        contacts.php            # Fetch contacts / generate CSV download
        save_contact.php        # Inserts contact. Performs Phase 8 duplicate checks.
        update_contact.php      # Edits contact. Enforces SQL ownership.
        delete_contact.php      # Deletes contact. Enforces SQL ownership.
        notes.php               # Read/insert/delete notes for a contact
        tags.php                # Attach/detach tags for a contact
        settings.php            # Change password & delete account endpoints
        view_card.php           # Authenticated image gateway serving private images
        upload_card.php         # Secure file validator (MIME, ext, size checking)
    /uploads
        /business_cards         # Storage for uploaded cards
        .htaccess               # Blocks execution of scripts in uploads
    /database
        schema.sql              # MySQL tables definition script
    /docs
        ARCHITECTURE.md         # Architecture blueprint
        DATABASE.md             # Normalized database specifications
        SECURITY.md             # Threat mitigation model
        DEPLOYMENT.md           # Deployment overview
    .htaccess                   # Disables directory indexing globally
    index.php                   # Entry router
    login.php                   # Sign-in UI
    register.php                # Account registration UI
    dashboard.php               # Professional CRM summary dashboard
    scan.php                    # Scanning UI & Review Edit Form
    add-contact.php             # Manual contact addition UI
    edit-contact.php            # Edit contact profile UI
    contact.php                 # Details contact view (Notes, Tags, Card Image)
    settings.php                # Security config & Account deletion UI
    privacy.php                 # General privacy policy agreement
    logout.php                  # Destroys session
```

---

## Database Installation

1. Create a MySQL database and user using your hosting panel (cPanel).
2. Assign the user to the database with **ALL PRIVILEGES**.
3. Open **phpMyAdmin**, select the database, click the **Import** tab, upload `database/schema.sql`, and click **Go** to generate the tables:
   - `users` (credentials)
   - `contacts` (main contact details, source and ocr raw text logs)
   - `notes` (client interactions)
   - `tags` (custom labels)
   - `contact_tags` (associative bridge)

---

## cPanel Deployment Steps

Follow these exact steps to launch CardVault on your cPanel shared hosting:

### STEP 1: Create MySQL Database
Log in to cPanel, search for **MySQL Database Wizard**, and create a database named `yourdomain_cardvault`.

### STEP 2: Create MySQL User
In the wizard, create a new database user (e.g. `yourdomain_cvuser`) and generate a secure password.

### STEP 3: Assign User to Database
Add the user to the database and check **ALL PRIVILEGES** to save database permissions.

### STEP 4: Open phpMyAdmin
Go to the cPanel main dashboard and open **phpMyAdmin** in the Database section.

### STEP 5: Import database/schema.sql
Select your database from the left side panel. Click **Import** at the top menu, choose `database/schema.sql` from your computer, and submit to initialize tables.

### STEP 6: Upload Application Files
Use cPanel **File Manager** or an FTP client (like FileZilla) to upload all CardVault files into the server directory (e.g., `public_html/cardvault/`).

### STEP 7: Configure database.php
In File Manager, navigate to `config/`. Rename `database.example.php` to `database.php`. Edit it and enter your database credentials:
```php
return [
    'host'      => 'localhost',
    'dbname'    => 'yourdomain_cardvault',
    'username'  => 'yourdomain_cvuser',
    'password'  => 'YOUR_SECURE_PASSWORD',
    'charset'   => 'utf8mb4'
];
```

### STEP 8: Set Correct Permissions
Verify file and folder permissions in cPanel:
- Standard directories: `755`
- Uploads directory (`uploads/business_cards/`): `755`
- PHP scripts: `644`

### STEP 9: Protect Uploads Directory
Confirm that `uploads/business_cards/.htaccess` is present. It disables PHP processing engines and blocks execution of uploaded scripts.

### STEP 10: Configure .htaccess
Verify that the root `.htaccess` is present to disable directory indexing (`Options -Indexes`).

### STEP 11: Open the Website
Navigate to `https://yourdomain.com/cardvault/` in your web browser. You will be redirected to the secure login page.

### STEP 12: Create First Account
Click **Register** to create a new user profile. CardVault passwords are saved using PHP's cryptographically secure `password_hash()` algorithm.

### STEP 13: Test Scanning
Navigate to **Scan Card**, allow camera access (if on mobile), capture or choose a business card image, and wait for the browser-based OCR to finish.

### STEP 14: Test Saving Contact
Review the extracted fields, correct any parsing errors on the review form, and click **Save Contact**.

### STEP 15: Test Editing Contact
Go to the contact profile details page, click **Edit Contact**, modify a value, and save.

### STEP 16: Test Search
Return to the dashboard, type a query in the search bar (e.g. searching a company or job title), and confirm that results are filtered.

### STEP 17: Test Follow-up
Open a contact profile, schedule a follow-up date, and set the status to `Follow-up`. Confirm that the contact appears under "Upcoming Follow-ups" on the main dashboard.

### STEP 18: Test Export
Go to **Contacts** and click **Export to CSV**. Verify the downloaded spreadsheet has all columns and contains your active contacts.

---

## Security Highlights

- **SQL Injection Prevention**: Prepared statements are used for all database interactions.
- **XSS Protection**: Outputs are escaped via the `e()` helper (`htmlspecialchars`) to block script injection.
- **CSRF Token Validation**: Tokens are required for all mutating forms/API requests.
- **Direct Image Block**: Uploaded cards are given randomized names. The uploads folder blocks script execution. Card images can only be fetched via `api/view_card.php` which validates contact ownership.
- **Session Protections**: Enforces HttpOnly, Lax SameSite, and strict 30-minute session expiry timeouts.
- **Privacy Assurance**: Settings provides an account delete command that deletes the profile and files permanently.

---

## OCR Setup & Self-Hosting

CardVault loads Tesseract.js from a public CDN by default:
```javascript
const OCR_CONFIG = {
    workerUrl: 'https://cdn.jsdelivr.net/npm/tesseract.js@5.0.3/dist/worker.min.js',
    coreUrl: 'https://cdn.jsdelivr.net/npm/tesseract.js-core@5.0.2/tesseract-core.wasm.js',
    langPath: 'https://tessdata.projectnaptha.com/4.0.0_fast'
};
```
If you need to run CardVault in an offline or local intranet environment, simply download the Tesseract scripts and language files and edit these URLs in `scan.php` to point to local web directory paths.

---

## Troubleshooting

- **Camera Permission Errors**: Ensure the site runs over `HTTPS`. Modern browsers restrict camera access (`capture="environment"`) to secure origins.
- **OCR Progress Sticking**: Ensure your browser can reach the CDN URLs. If blocked, configure CardVault to use self-hosted local Tesseract scripts.
- **Upload File Size Failures**: If you get a file upload error on large cards, increase the `upload_max_filesize` and `post_max_size` in the cPanel **Select PHP Version -> Options** menu.
- **Database Connection Issues**: Verify that your cPanel MySQL port/socket corresponds to `localhost` (default). Double check credentials in `config/database.php`.

---

## Backup Procedure

1. **Database Backup**:
   - Open cPanel -> **phpMyAdmin**.
   - Select the database, click **Export**, choose **Quick** format, and click **Go** to download the SQL database backup.
2. **Files Backup**:
   - Go to cPanel -> **File Manager**.
   - Right-click the `cardvault` folder, select **Compress**, choose **Zip Archive**, compress, and download the zip file. This retains all uploaded business card files.
