<?php
/**
 * Event Workspace Details Page
 */

$pageTitle = 'Event Details';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/csrf.php';
$pdo = require __DIR__ . '/includes/db.php';

$userId = $_SESSION['user_id'];
$eventId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($eventId <= 0) {
    echo "<div class='alert alert-danger'>Invalid Event ID.</div>";
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

try {
    // 1. Fetch Event details
    $stmtEvent = $pdo->prepare("SELECT * FROM events WHERE id = :id AND user_id = :user_id");
    $stmtEvent->execute(['id' => $eventId, 'user_id' => $userId]);
    $event = $stmtEvent->fetch();

    if (!$event) {
        echo "<div class='alert alert-danger'>Event not found or unauthorized access.</div>";
        require_once __DIR__ . '/includes/footer.php';
        exit;
    }

    // 2. Fetch contacts met at this event
    $stmtContacts = $pdo->prepare("
        SELECT c.* 
        FROM contacts c 
        JOIN event_contacts ec ON c.id = ec.contact_id 
        WHERE ec.event_id = :event_id AND c.user_id = :user_id 
        ORDER BY c.full_name ASC
    ");
    $stmtContacts->execute(['event_id' => $eventId, 'user_id' => $userId]);
    $contacts = $stmtContacts->fetchAll();

    // 3. Fetch all other unassociated user contacts to allow linking them manually
    $stmtUnassociated = $pdo->prepare("
        SELECT id, full_name, company 
        FROM contacts 
        WHERE user_id = :user_id 
          AND id NOT IN (SELECT contact_id FROM event_contacts WHERE event_id = :event_id) 
        ORDER BY full_name ASC
    ");
    $stmtUnassociated->execute(['user_id' => $userId, 'event_id' => $eventId]);
    $unassociatedContacts = $stmtUnassociated->fetchAll();

    // 4. Fetch follow-ups generated from these contacts
    $followUps = [];
    if (!empty($contacts)) {
        $contactIds = array_map(function($c) { return $c['id']; }, $contacts);
        $inQuery = implode(',', array_fill(0, count($contactIds), '?'));
        $stmtFU = $pdo->prepare("
            SELECT f.*, c.full_name as contact_name 
            FROM follow_ups f 
            JOIN contacts c ON f.contact_id = c.id 
            WHERE f.contact_id IN ($inQuery) AND f.user_id = ? 
            ORDER BY f.follow_up_date ASC
        ");
        $stmtFU->execute(array_merge($contactIds, [$userId]));
        $followUps = $stmtFU->fetchAll();
    }

    // 5. Aggregate unique companies represented
    $companiesRepresented = [];
    foreach ($contacts as $c) {
        if (!empty($c['company'])) {
            $companiesRepresented[] = $c['company'];
        }
    }
    $companiesRepresented = array_unique($companiesRepresented);

} catch (\PDOException $e) {
    error_log("Fetch Event Details Error: " . $e->getMessage());
    echo "<div class='alert alert-danger'>A database error occurred.</div>";
    require_once __DIR__ . '/includes/footer.php';
    exit;
}
?>

<div class="page-header" style="margin-bottom: 2rem;">
    <div class="page-title">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <a href="events.php" style="font-size: 1.5rem; text-decoration: none;">⬅️</a>
            <h1>📅 <?php echo htmlspecialchars($event['name']); ?></h1>
        </div>
        <p style="margin-left: 2.25rem;">Event Networking Summary Profile</p>
    </div>
    <div class="header-actions" style="display: flex; gap: 0.5rem;">
        <a href="scan.php?event_id=<?php echo $eventId; ?>" class="btn btn-primary">📸 Scan Card Here</a>
        <button type="button" class="btn btn-secondary" id="edit-event-btn">✏️ Edit</button>
        <button type="button" class="btn btn-danger" id="delete-event-btn">🗑️ Delete</button>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem; align-items: start;">
    <!-- LEFT SIDEBAR: Event details card -->
    <div>
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header"><h3 class="card-title">Event Overview</h3></div>
            <div class="card-body">
                <div style="margin-bottom: 1rem;">
                    <strong class="form-label" style="display: block; color: var(--text-muted);">Event Type</strong>
                    <span class="badge" style="background-color: #f1f5f9; color: var(--secondary-color); border: 1px solid var(--border-color); font-weight: 500; font-size: 0.8rem; padding: 0.15rem 0.5rem; border-radius: 4px;">
                        <?php echo htmlspecialchars($event['type']); ?>
                    </span>
                </div>
                <div style="margin-bottom: 1rem;">
                    <strong class="form-label" style="display: block; color: var(--text-muted);">Date</strong>
                    <span><?php echo date('F d, Y', strtotime($event['date'])); ?></span>
                </div>
                <div style="margin-bottom: 1rem;">
                    <strong class="form-label" style="display: block; color: var(--text-muted);">Location Venue</strong>
                    <span><?php echo htmlspecialchars($event['location'] ?? 'N/A'); ?></span>
                </div>
                <div style="margin-bottom: 0;">
                    <strong class="form-label" style="display: block; color: var(--text-muted);">Description / Notes</strong>
                    <p style="margin: 0.25rem 0 0 0; font-size: 0.95rem; line-height: 1.5; color: var(--text-muted);">
                        <?php echo nl2br(htmlspecialchars($event['description'] ?? 'No description notes saved.')); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Companies represented -->
        <div class="card">
            <div class="card-header"><h3 class="card-title">Companies represented (<?php echo count($companiesRepresented); ?>)</h3></div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($companiesRepresented)): ?>
                    <p style="padding: 1.5rem; text-align: center; color: var(--text-muted);">No corporate organizations represented.</p>
                <?php else: ?>
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        <?php foreach ($companiesRepresented as $comp): ?>
                            <li style="padding: 0.75rem 1rem; border-bottom: 1px solid var(--border-color); font-weight: 500;">
                                🏢 <?php echo htmlspecialchars($comp); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- RIGHT MAIN PANELS: Contacts met & follow-ups -->
    <div>
        <!-- Contacts Met -->
        <div class="card" style="margin-bottom: 2rem;">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                <h3 class="card-title">Contacts Met (<?php echo count($contacts); ?>)</h3>
                <!-- Add contact manual link form -->
                <form id="link-contact-form" action="api/events.php" method="POST" style="display: flex; gap: 0.5rem; max-width: 350px; flex: 1;">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="add_contact">
                    <input type="hidden" name="event_id" value="<?php echo $eventId; ?>">
                    <select name="contact_id" class="form-control" style="font-size: 0.85rem;" required>
                        <option value="">-- Associate Contact Met --</option>
                        <?php foreach ($unassociatedContacts as $uc): ?>
                            <option value="<?php echo $uc['id']; ?>">
                                <?php echo htmlspecialchars($uc['full_name']); ?> <?php echo !empty($uc['company']) ? '(' . htmlspecialchars($uc['company']) . ')' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">Add</button>
                </form>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($contacts)): ?>
                    <div style="padding: 3rem; text-align: center; color: var(--text-muted);">
                        <p>No contacts logged for this event.</p>
                        <p style="font-size: 0.9rem; margin-top: 0.5rem;">Use the <strong>📸 Scan Card Here</strong> button to scan cards live at the event!</p>
                    </div>
                <?php else: ?>
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        <?php foreach ($contacts as $c): ?>
                            <li style="padding: 1rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <a href="contact.php?id=<?php echo $c['id']; ?>" class="text-primary" style="text-decoration: none; font-weight: 600; display: block;">
                                        👤 <?php echo htmlspecialchars($c['full_name']); ?>
                                    </a>
                                    <span style="font-size: 0.85rem; color: var(--text-muted);">
                                        <?php echo htmlspecialchars($c['job_title'] ?? 'No Title'); ?> <?php echo !empty($c['company']) ? 'at ' . htmlspecialchars($c['company']) : ''; ?>
                                    </span>
                                </div>
                                <button type="button" class="btn btn-secondary btn-xs unlink-contact-btn" data-id="<?php echo $c['id']; ?>">Unlink</button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Follow-ups generated -->
        <div class="card">
            <div class="card-header"><h3 class="card-title">Follow-ups Generated (<?php echo count($followUps); ?>)</h3></div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($followUps)): ?>
                    <p style="padding: 2rem; text-align: center; color: var(--text-muted);">No follow-up schedules created for event contacts.</p>
                <?php else: ?>
                    <table class="table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="text-align: left; background-color: var(--background-color); border-bottom: 1px solid var(--border-color);">
                                <th style="padding: 0.75rem 1rem;">Contact</th>
                                <th style="padding: 0.75rem 1rem;">Due Date</th>
                                <th style="padding: 0.75rem 1rem;">Priority</th>
                                <th style="padding: 0.75rem 1rem;">Status</th>
                                <th style="padding: 0.75rem 1rem;">Action notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($followUps as $fu): ?>
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td style="padding: 0.75rem 1rem; font-weight: 500;">
                                        <a href="contact.php?id=<?php echo $fu['contact_id']; ?>" class="text-primary" style="text-decoration: none;">
                                            <?php echo htmlspecialchars($fu['contact_name']); ?>
                                        </a>
                                    </td>
                                    <td style="padding: 0.75rem 1rem; font-size: 0.9rem;">
                                        <?php echo date('M d, Y', strtotime($fu['follow_up_date'])); ?>
                                    </td>
                                    <td style="padding: 0.75rem 1rem;">
                                        <span class="badge" style="background-color: <?php 
                                            if ($fu['priority'] === 'High') echo '#fee2e2; color: #991b1b;';
                                            elseif ($fu['priority'] === 'Medium') echo '#fef3c7; color: #92400e;';
                                            else echo '#f3f4f6; color: #374151;';
                                        ?> font-weight: 600; font-size: 0.75rem; padding: 0.15rem 0.4rem; border-radius: 4px;">
                                            <?php echo $fu['priority']; ?>
                                        </span>
                                    </td>
                                    <td style="padding: 0.75rem 1rem;">
                                        <span class="badge" style="background-color: <?php echo $fu['status'] === 'Completed' ? '#d1fae5; color: #065f46;' : '#f3f4f6; color: #374151;'; ?> font-size: 0.75rem; padding: 0.15rem 0.4rem; border-radius: 4px;">
                                            <?php echo $fu['status']; ?>
                                        </span>
                                    </td>
                                    <td style="padding: 0.75rem 1rem; font-size: 0.85rem; color: var(--text-muted);">
                                        <?php echo htmlspecialchars($fu['notes'] ?? 'N/A'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Edit Event Modal -->
<div id="edit-event-modal" class="modal-overlay hidden">
    <div class="modal-card">
        <div class="modal-header"><h3>✏️ Edit Event Details</h3></div>
        <form id="edit-event-form" action="api/events.php" method="POST">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo $eventId; ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label for="edit-name" class="form-label">Event Name *</label>
                    <input type="text" name="name" id="edit-name" class="form-control" required value="<?php echo htmlspecialchars($event['name']); ?>">
                </div>
                <div class="form-group">
                    <label for="edit-type" class="form-label">Event Type *</label>
                    <select name="type" id="edit-type" class="form-control" required>
                        <?php 
                        $types = ['Trade Show', 'Conference', 'Meeting', 'Networking Event', 'Exhibition', 'Travel', 'Client Visit', 'Other'];
                        foreach ($types as $t): ?>
                            <option value="<?php echo htmlspecialchars($t); ?>" <?php echo $event['type'] === $t ? 'selected' : ''; ?>><?php echo htmlspecialchars($t); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit-date" class="form-label">Date *</label>
                    <input type="date" name="date" id="edit-date" class="form-control" required value="<?php echo $event['date']; ?>">
                </div>
                <div class="form-group">
                    <label for="edit-location" class="form-label">Location</label>
                    <input type="text" name="location" id="edit-location" class="form-control" value="<?php echo htmlspecialchars($event['location'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="edit-desc" class="form-label">Description / Notes</label>
                    <textarea name="description" id="edit-desc" class="form-control" rows="3"><?php echo htmlspecialchars($event['description'] ?? ''); ?></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="close-edit-btn">Cancel</button>
                <button type="submit" class="btn btn-primary" id="save-edit-btn">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const editModal = document.getElementById('edit-event-modal');
    const editBtn = document.getElementById('edit-event-btn');
    const closeEditBtn = document.getElementById('close-edit-btn');
    const editForm = document.getElementById('edit-event-form');
    
    if (editBtn) editBtn.addEventListener('click', () => editModal.classList.remove('hidden'));
    if (closeEditBtn) closeEditBtn.addEventListener('click', () => editModal.classList.add('hidden'));

    if (editForm) {
        editForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const submitBtn = document.getElementById('save-edit-btn');
            submitBtn.disabled = true;
            
            const formData = new FormData(editForm);
            fetch(editForm.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-Token': formData.get('csrf_token')
                }
            })
            .then(res => res.json().then(data => {
                if (!res.ok) throw new Error(data.message || 'Update failed.');
                return data;
            }))
            .then(data => {
                editModal.classList.add('hidden');
                alert(data.message);
                window.location.reload();
            })
            .catch(err => {
                alert(err.message);
                submitBtn.disabled = false;
            });
        });
    }

    // Delete Event Action
    const deleteBtn = document.getElementById('delete-event-btn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', () => {
            if (confirm('Are you absolutely sure you want to delete this event? Scanned contacts will remain in the database, but their association with this event will be cleared.')) {
                const csrfToken = document.querySelector('input[name="csrf_token"]').value;
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', '<?php echo $eventId; ?>');
                formData.append('csrf_token', csrfToken);
                
                fetch('api/events.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-Token': csrfToken
                    }
                })
                .then(res => res.json().then(data => {
                    if (!res.ok) throw new Error(data.message || 'Deletion failed.');
                    return data;
                }))
                .then(data => {
                    alert(data.message);
                    window.location.href = 'events.php';
                })
                .catch(err => {
                    alert(err.message);
                });
            }
        });
    }

    // Unlink contact
    document.querySelectorAll('.unlink-contact-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            if (confirm('Unlink this contact from the event?')) {
                const contactId = btn.getAttribute('data-id');
                const csrfToken = document.querySelector('input[name="csrf_token"]').value;
                
                const formData = new FormData();
                formData.append('action', 'remove_contact');
                formData.append('contact_id', contactId);
                formData.append('event_id', '<?php echo $eventId; ?>');
                formData.append('csrf_token', csrfToken);
                
                fetch('api/events.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-Token': csrfToken
                    }
                })
                .then(res => res.json().then(data => {
                    if (!res.ok) throw new Error(data.message || 'Failed to unlink.');
                    return data;
                }))
                .then(data => {
                    alert(data.message);
                    window.location.reload();
                })
                .catch(err => {
                    alert(err.message);
                });
            }
        });
    });

    // Link contact submit via AJAX
    const linkForm = document.getElementById('link-contact-form');
    if (linkForm) {
        linkForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(linkForm);
            fetch(linkForm.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-Token': formData.get('csrf_token')
                }
            })
            .then(res => res.json().then(data => {
                if (!res.ok) throw new Error(data.message || 'Failed to link.');
                return data;
            }))
            .then(data => {
                alert(data.message);
                window.location.reload();
            })
            .catch(err => {
                alert(err.message);
            });
        });
    }
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
