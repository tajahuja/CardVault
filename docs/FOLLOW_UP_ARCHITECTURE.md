# Follow-up Center - Architecture & Technical Design

This document details the database schemas, API specs, and synchronization design for CardVault's **Follow-up Center** scheduling systems.

---

## 1. Relational Database Schema

Follow-ups are stored inside a dedicated table. This allows scheduling multiple subsequent touchpoints for a single contact and tracking chronological completion history.

```sql
CREATE TABLE IF NOT EXISTS `follow_ups` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `contact_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `follow_up_date` DATE NOT NULL,
    `priority` ENUM('Low', 'Medium', 'High') DEFAULT 'Medium',
    `status` ENUM('Pending', 'Completed', 'Snoozed') DEFAULT 'Pending',
    `notes` TEXT DEFAULT NULL,
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    INDEX `idx_user_follow_ups` (`user_id`, `follow_up_date`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 2. Syncing Pending Follow-ups to Contacts

To maintain backward compatibility with contact directory listings and legacy sorting models, the column `contacts.follow_up_date` is dynamically updated by a trigger-like helper function `syncContactFollowUpDate()` on every follow-up state change.

```
[Create/Complete/Snooze Follow-up event]
  │
  ▼
[Fetch closest 'Pending' follow_up_date for contact]
  │
  ▼
[Update contacts table SET follow_up_date = next_date OR NULL]
```

### Sync Query Implementation
```php
function syncContactFollowUpDate($pdo, $contactId, $userId) {
    // 1. Fetch closest pending date
    $stmt = $pdo->prepare("
        SELECT follow_up_date FROM follow_ups 
        WHERE contact_id = :contact_id AND user_id = :user_id AND status = 'Pending' 
        ORDER BY follow_up_date ASC LIMIT 1
    ");
    $stmt->execute(['contact_id' => $contactId, 'user_id' => $userId]);
    $nextDate = $stmt->fetchColumn();

    // 2. Set contact header target
    $updateStmt = $pdo->prepare("
        UPDATE contacts SET follow_up_date = :next_date 
        WHERE id = :contact_id AND user_id = :user_id
    ");
    $updateStmt->execute([
        'next_date' => $nextDate ?: null,
        'contact_id' => $contactId,
        'user_id' => $userId
    ]);
}
```

---

## 3. State Transition Model

```
       [Schedule Follow-up]
                 │
                 ▼
         ┌───────────────┐
         │    Pending    │◄───────────────┐
         └──────┬────────┘                │
                │                         │
      ┌─────────┴─────────┐               │ (Reset to Pending)
      ▼                   ▼               │
 [Complete Action]  [Snooze Action] ──────┘
      │                   │
      ▼                   ▼
┌───────────┐       ┌───────────┐
│ Completed │       │  Snoozed  │
└───────────┘       └───────────┘
```

---

## 4. API Endpoint Interface (`api/followup.php`)

All requests must use `POST` method, provide valid authenticated session user IDs, and supply a CSRF validation token header field.

### Actions Specification
1.  **`action = create`**
    *   *Parameters*: `contact_id`, `follow_up_date`, `priority` (Low/Medium/High), `notes` (optional).
    *   *Operation*: Inserts a new Pending follow-up; triggers chronological log entry on the contact's vertical timeline.
2.  **`action = complete`**
    *   *Parameters*: `id` (follow-up ID), `completion_notes`.
    *   *Operation*: Marks status as `'Completed'`, stamps `completed_at`, appends outcome notes, and registers completion on timeline.
3.  **`action = snooze`**
    *   *Parameters*: `id`, `days` (relative interval) OR `custom_date`.
    *   *Operation*: Computes the future date, updates the record target, resets status to `'Pending'`, and writes snooze note to timeline.
4.  **`action = edit`**
    *   *Parameters*: `id`, `follow_up_date`, `priority`, `notes`.
    *   *Operation*: Modifies pending fields and updates contact sync state.

---

## 5. Security & Isolation Controls
*   **Segmented User Ownership**: Every SQL command inside the API combines `AND user_id = :user_id` to block direct object reference (IDOR) hacking attempts.
*   **CSRF Safeguards**: The endpoint requires `validate_csrf()` execution before processing any state-changing logic.
