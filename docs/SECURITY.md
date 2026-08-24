# Security Architecture

CardVault implements a multi-layered security protocol designed to protect personal contact information and maintain integrity on shared hosting.

## Core Protections

### 1. SQL Injection Prevention
- **Mechanism**: PDO prepared statements with parameter binding are utilized exclusively.
- **Rule**: No SQL query is ever concatenated with variable input. All inputs are passed as parameters to prepared queries.
- **Example**:
  ```php
  $stmt = $pdo->prepare('SELECT * FROM contacts WHERE id = :id AND user_id = :user_id');
  $stmt->execute([
      'id' => $contactId,
      'user_id' => $_SESSION['user_id']
  ]);
  ```

### 2. Cross-Site Scripting (XSS) Prevention
- **Rule**: All output printed inside HTML pages is escaped using `htmlspecialchars($str, ENT_QUOTES, 'UTF-8')`.
- **JSON Encoding**: API endpoints use `json_encode()` with correct Content-Type headers (`application/json`), ensuring scripts cannot run.
- **Helper Function**: A global helper `e()` is defined in `includes/functions.php`.

### 3. Cross-Site Request Forgery (CSRF) Prevention
- **Mechanism**: A unique cryptographic token is generated per session (`$_SESSION['csrf_token']`).
- **Validation**:
  - Every POST/PUT/DELETE request must transmit this token via a `POST` parameter or standard request header (`X-CSRF-Token`).
  - The token is verified server-side inside `includes/csrf.php` before processing the action.
  - Failures result in immediate termination with a `403 Forbidden` response.

### 4. File-Upload Security
Card images contain potential pathways for shell upload or arbitrary execution. We defend against this strictly:
- **Location**: Uploads are placed in `uploads/business_cards/`.
- **Validation**:
  - MIME type is checked server-side using standard PHP `mime_content_type()` or `finfo` (e.g., must match `image/jpeg`, `image/png`, or `image/webp`).
  - File extension is verified against a strict whitelist: `jpg`, `jpeg`, `png`, `webp`.
  - Content check: Images are verified to ensure they do not contain hidden php tags or scripts.
- **Filename Sanitization**: The original filename is discarded completely. A random string (e.g., `bin2hex(random_bytes(16))`) is generated for storage on disk.
- **Execution Prevention**: The `uploads/business_cards/.htaccess` disables file execution and directory indexing:
  ```htaccess
  Options -Indexes
  RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .phps
  RemoveType .php .phtml .php3 .php4 .php5 .php7 .php8 .phps
  php_flag engine off
  ```
- **Access Control**: Users cannot directly browse the files. Images are loaded via an authorized media controller `api/view_card.php?id=<contact_id>` which asserts contact ownership before serving the content.

### 5. Session & Authentication Security
- **Hashing**: Passwords are saved using standard PHP `password_hash()` with `PASSWORD_DEFAULT` (bcrypt).
- **Session Configuration**:
  - `session.use_only_cookies = 1`
  - `session.cookie_httponly = 1`
  - `session.cookie_secure = 1` (on HTTPS)
  - `session.cookie_samesite = "Lax"`
- **Timeout**: User sessions expire after 30 minutes of inactivity. Session recreation takes place on login (`session_regenerate_id(true)`) to prevent session fixation.
