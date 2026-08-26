# Digital Business Card - Technical Design

This document details the architectural design, specifications, database models, and security protections for CardVault's **Digital Business Card** system.

---

## 1. System Overview & Workflow

The Digital Business Card serves as a networking utility. It enables users to display their details publicly and exchange contacts with visitors directly from a mobile device using a QR code scan.

```
[Owner Device] 
  └─► Public QR ──► [Visitor Device scans QR] 
                      └─► Opens public URL (/c/unique-slug) 
                            ├─► Tap "Save Contact" ──► Downloads valid vCard (.vcf)
                            └─► Fill Exchange Form ──► Creates Contact for Owner (source: DBC)
```

---

## 2. Component Specifications

### 1. Digital Business Card Profile Editor (`my-card.php`)
*   Provides the owner with a dashboard editor to set publicly displayed fields.
*   Enforces toggles for: Full Name, Job Title, Company, Mobile Phone, Alternate Phone, Email, LinkedIn, Bio, Profile Image, and Logo.
*   Generates a custom `slug` (e.g. `c/john-doe`), validating uniqueness in the database.

### 2. Visitor Public Profile Page (`c/unique-slug`)
*   Fully optimized for mobile layouts with zero dependency on login sessions.
*   Displays allowed fields dynamically. Includes click tracking logs for CTA events (WhatsApp initiated, Call clicks, vCard downloads).
*   Enforces strict sanitization (escaping output via `e()`) to prevent XSS injection attacks from user-entered profile bio data.

### 3. QR Code Generator
*   Uses a client-side QR renderer (e.g. `QRCode.js` or pre-generated API paths) to render a high-contrast QR.
*   Encodes the **Public URL** (e.g. `https://business.dmcgoa.in/c/john-doe`) rather than encoding raw vCard texts. This ensures owners can update details without reprinting physical QR materials.

### 4. Valid vCard (VCF) Generation Engine (`api/vcard.php`)
*   Triggered when a visitor taps **SAVE CONTACT**.
*   Generates and streams a raw `.vcf` file download:
    *   **Content-Type**: `text/vcard; charset=utf-8`
    *   **Content-Disposition**: `attachment; filename="contact.vcf"`
*   Encodes attributes properly, handling carriage returns and escaped commas/semicolons:
    ```vcard
    BEGIN:VCARD
    VERSION:3.0
    FN:Rahul Sharma
    ORG:Goa Tourism Solutions
    TITLE:Director of Product
    TEL;TYPE=CELL,VOICE:+919823045678
    EMAIL;TYPE=PREF,INTERNET:rahul@goatourism.in
    URL:https://www.goatourism.in
    ADR;TYPE=WORK:;;404 Ocean View Heights;Panaji;Goa;403001;India
    NOTE:Generated via CardVault CRM
    END:VCARD
    ```

### 5. Guest-to-Owner Contact Exchange System
*   Visitor fills in: Name, Phone, Email, Company, and Designation.
*   Taps **SHARE CONTACT** to submit details to a secure visitor api (`api/exchange.php`).
*   The endpoint validates inputs, assigns the contact to the CardVault owner (`user_id` mapped via profile slug), sets `lead_source` to `'Digital Business Card'`, and logs a `📸 Scan` equivalent timeline interaction labeled `'Digital Contact Exchange'`.

---

## 3. Database Schema Requirements

```sql
CREATE TABLE IF NOT EXISTS `user_profiles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `slug` VARCHAR(100) NOT NULL,
    `full_name` VARCHAR(255) NOT NULL,
    `designation` VARCHAR(150) DEFAULT NULL,
    `company` VARCHAR(150) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `website` VARCHAR(255) DEFAULT NULL,
    `linkedin_url` VARCHAR(255) DEFAULT NULL,
    `bio` TEXT DEFAULT NULL,
    `profile_photo` VARCHAR(255) DEFAULT NULL,
    `public_fields_json` TEXT DEFAULT NULL, -- Controls displayed toggles
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    UNIQUE KEY `uq_profile_slug` (`slug`),
    UNIQUE KEY `uq_user_profile` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 4. Abuse & Spam Protections

1.  **Rate Limiting**: Set IP rate-limit blocks on the Guest Contact Exchange endpoint (`api/exchange.php`) to prevent automated spam scripts from loading fake contact records into a user's CRM database.
2.  **XSS Filtration**: Sanitize guest inputs (strip HTML/script tags) before saving them to the database.
3.  **Data Isolation Safeguard**: Verify that public profiles only query and display fields flagged as public in the `public_fields_json` configurations. Private databases or general CRM records must never be exposed via guest profiles.
