<?php
/**
 * Contact Details Profile Page
 */

$pageTitle = 'Contact Details';
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
        echo '<div class="alert alert-danger" style="margin-top: 2rem;">Contact not found or you are not authorized to view it. <a href="contacts.php">Back to contacts list</a></div>';
        require_once __DIR__ . '/includes/footer.php';
        exit;
    }
    
    // Auto-initials
    $initials = '';
    if (!empty($contact['first_name'])) $initials .= substr($contact['first_name'], 0, 1);
    if (!empty($contact['last_name'])) $initials .= substr($contact['last_name'], 0, 1);
    if (empty($initials)) $initials = substr($contact['full_name'] ?? 'C', 0, 1);
    $initials = strtoupper($initials);
    
    $displayName = !empty($contact['full_name']) ? $contact['full_name'] : ($contact['first_name'] . ' ' . $contact['last_name']);
    $statusClass = strtolower(str_replace(' ', '', $contact['status']));
    
    // Fetch chronological interactions timeline
    $stmtInt = $pdo->prepare("
        SELECT type, description, created_at 
        FROM interactions 
        WHERE contact_id = :contact_id AND user_id = :user_id 
        ORDER BY created_at DESC
    ");
    $stmtInt->execute(['contact_id' => $contactId, 'user_id' => $userId]);
    $interactions = $stmtInt->fetchAll();

    // Fetch active pending follow-up
    $stmtFu = $pdo->prepare("
        SELECT * FROM follow_ups 
        WHERE contact_id = :contact_id AND user_id = :user_id AND status = 'Pending' 
        ORDER BY follow_up_date ASC 
        LIMIT 1
    ");
    $stmtFu->execute(['contact_id' => $contactId, 'user_id' => $userId]);
    $activeFollowUp = $stmtFu->fetch();

} catch (\PDOException $e) {
    error_log("Contact detail load DB error: " . $e->getMessage());
    echo '<div class="alert alert-danger">An error occurred while loading contact details.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}
?>

<div class="page-header">
    <div class="page-title">
        <h1><?php echo e($displayName); ?></h1>
        <p><?php echo $contact['job_title'] ? e($contact['job_title']) : 'No Job Title'; ?> <?php echo $contact['company'] ? 'at ' . e($contact['company']) : ''; ?></p>
    </div>
    <div class="header-actions">
        <a href="dashboard.php" class="btn btn-secondary">🏠 Back to Dashboard</a>
    </div>
</div>

<?php
$whatsAppUrl = get_whatsapp_url($contact['phone']);
$websiteUrl = clean_url($contact['website']);
?>

<!-- Quick Actions Toolbar -->
<div class="card" style="margin-bottom: 1.5rem; background: var(--surface-color); border: 1px solid var(--border-color);">
    <div class="card-body" style="padding: 1rem; display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center;">
        <span style="font-weight: 600; color: var(--text-muted); margin-right: 0.5rem; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em;">Actions:</span>
        
        <!-- Edit -->
        <a href="edit-contact.php?id=<?php echo $contact['id']; ?>" class="btn btn-secondary" style="font-size: 0.85rem; padding: 0.5rem 0.85rem; display: flex; align-items: center; gap: 0.35rem;">
            <span>✏️</span> Edit Profile
        </a>

        <!-- Call -->
        <?php if ($contact['phone']): ?>
            <a href="tel:<?php echo e($contact['phone']); ?>" class="btn btn-secondary" style="font-size: 0.85rem; padding: 0.5rem 0.85rem; display: flex; align-items: center; gap: 0.35rem; color: var(--primary-color);">
                <span>📞</span> Call Mobile
            </a>
        <?php else: ?>
            <button class="btn btn-secondary" disabled style="font-size: 0.85rem; padding: 0.5rem 0.85rem; opacity: 0.5; cursor: not-allowed; display: flex; align-items: center; gap: 0.35rem;">
                <span>📞</span> Call Mobile
            </button>
        <?php endif; ?>

        <!-- WhatsApp -->
        <?php if ($whatsAppUrl): ?>
            <button type="button" class="btn btn-secondary" onclick="triggerWhatsAppComposer(<?php echo $contact['id']; ?>, '<?php echo e(addslashes($contact['full_name'])); ?>', '<?php echo e(addslashes($contact['phone'])); ?>', '<?php echo e(addslashes($contact['company'])); ?>', '<?php echo e(addslashes($contact['place_met'])); ?>')" style="font-size: 0.85rem; padding: 0.5rem 0.85rem; display: flex; align-items: center; gap: 0.35rem; background-color: #25d366; color: white; border-color: #20ba5a;">
                <span style="font-size: 1rem;">💬</span> WhatsApp
            </button>
        <?php else: ?>
            <button class="btn btn-secondary" disabled style="font-size: 0.85rem; padding: 0.5rem 0.85rem; opacity: 0.5; cursor: not-allowed; display: flex; align-items: center; gap: 0.35rem;">
                <span>💬</span> WhatsApp
            </button>
        <?php endif; ?>

        <!-- Email -->
        <?php if ($contact['email']): ?>
            <button type="button" class="btn btn-secondary" onclick="triggerEmailComposer(<?php echo $contact['id']; ?>, '<?php echo e(addslashes($contact['full_name'])); ?>', '<?php echo e(addslashes($contact['email'])); ?>', '<?php echo e(addslashes($contact['company'])); ?>', '<?php echo e(addslashes($contact['place_met'])); ?>')" style="font-size: 0.85rem; padding: 0.5rem 0.85rem; display: flex; align-items: center; gap: 0.35rem;">
                <span>✉️</span> Send Email
            </button>
        <?php else: ?>
            <button class="btn btn-secondary" disabled style="font-size: 0.85rem; padding: 0.5rem 0.85rem; opacity: 0.5; cursor: not-allowed; display: flex; align-items: center; gap: 0.35rem;">
                <span>✉️</span> Send Email
            </button>
        <?php endif; ?>

        <!-- Website -->
        <?php if ($websiteUrl): ?>
            <a href="<?php echo $websiteUrl; ?>" target="_blank" class="btn btn-secondary" style="font-size: 0.85rem; padding: 0.5rem 0.85rem; display: flex; align-items: center; gap: 0.35rem;">
                <span>🌐</span> Website ↗
            </a>
        <?php else: ?>
            <button class="btn btn-secondary" disabled style="font-size: 0.85rem; padding: 0.5rem 0.85rem; opacity: 0.5; cursor: not-allowed; display: flex; align-items: center; gap: 0.35rem;">
                <span>🌐</span> Website
            </button>
        <?php endif; ?>

        <!-- Delete (with form block) -->
        <form id="delete-contact-form" action="api/delete_contact.php" method="POST" style="display: inline-block; margin: 0; margin-left: auto;">
            <?php csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo $contact['id']; ?>">
            <button type="submit" class="btn btn-danger" style="font-size: 0.85rem; padding: 0.5rem 0.85rem; display: flex; align-items: center; gap: 0.35rem;" onclick="return confirm('Are you sure you want to delete this contact? This action cannot be undone.');">
                <span>🗑️</span> Delete
            </button>
        </form>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Main Column: Details -->
    <div>
        <!-- Profile Block Card -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-body" style="display: flex; gap: 1.5rem; align-items: center;">
                <div class="contact-avatar" style="width: 5rem; height: 5rem; font-size: 2rem; border-radius: 50%; background-color: var(--primary-light); color: var(--primary-color);">
                    <?php echo e($initials); ?>
                </div>
                <div>
                    <h2 style="font-size: 1.5rem; margin-bottom: 0.25rem; color: var(--secondary-color);"><?php echo e($displayName); ?></h2>
                    <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 0.5rem;"><?php echo e($contact['job_title'] ?: 'No Job Title'); ?> — <strong style="color: var(--secondary-color);"><?php echo e($contact['company'] ?: 'No Company'); ?></strong></p>
                    <span class="badge badge-<?php echo $statusClass; ?>"><?php echo e($contact['status']); ?></span>
                </div>
            </div>
        </div>

        <!-- Contact Detail Cards Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
            <!-- Contact Channels -->
            <div class="card">
                <div class="card-header"><h3 class="card-title">📞 Contact Information</h3></div>
                <div class="card-body" style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-muted);">Phone Number</div>
                        <?php if ($contact['phone']): ?>
                            <a href="tel:<?php echo e($contact['phone']); ?>" style="color: var(--primary-color); font-weight: 500; text-decoration: none;"><?php echo e($contact['phone']); ?></a>
                        <?php else: ?>
                            <div style="color: var(--text-muted); font-style: italic;">Not provided</div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-muted);">Alternate Phone</div>
                        <?php if ($contact['alternate_phone']): ?>
                            <a href="tel:<?php echo e($contact['alternate_phone']); ?>" style="color: var(--text-color); text-decoration: none;"><?php echo e($contact['alternate_phone']); ?></a>
                        <?php else: ?>
                            <div style="color: var(--text-muted); font-style: italic;">Not provided</div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-muted);">Email Address</div>
                        <?php if ($contact['email']): ?>
                            <a href="mailto:<?php echo e($contact['email']); ?>" style="color: var(--primary-color); font-weight: 500; text-decoration: none;"><?php echo e($contact['email']); ?></a>
                        <?php else: ?>
                            <div style="color: var(--text-muted); font-style: italic;">Not provided</div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-muted);">Alternate Email</div>
                        <?php if ($contact['alternate_email']): ?>
                            <a href="mailto:<?php echo e($contact['alternate_email']); ?>" style="color: var(--text-color); text-decoration: none;"><?php echo e($contact['alternate_email']); ?></a>
                        <?php else: ?>
                            <div style="color: var(--text-muted); font-style: italic;">Not provided</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Locations -->
            <div class="card">
                <div class="card-header"><h3 class="card-title">📍 Location Details</h3></div>
                <div class="card-body" style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-muted);">Street Address</div>
                        <div style="font-weight: 500; white-space: pre-line;"><?php echo e($contact['address'] ?: 'Not provided'); ?></div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                        <div>
                            <div style="font-size: 0.8rem; color: var(--text-muted);">City</div>
                            <div style="font-weight: 500;"><?php echo e($contact['city'] ?: '-'); ?></div>
                        </div>
                        <div>
                            <div style="font-size: 0.8rem; color: var(--text-muted);">State</div>
                            <div style="font-weight: 500;"><?php echo e($contact['state'] ?: '-'); ?></div>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                        <div>
                            <div style="font-size: 0.8rem; color: var(--text-muted);">Country</div>
                            <div style="font-weight: 500;"><?php echo e($contact['country'] ?: '-'); ?></div>
                        </div>
                        <div>
                            <div style="font-size: 0.8rem; color: var(--text-muted);">Postal Code</div>
                            <div style="font-weight: 500;"><?php echo e($contact['postal_code'] ?: '-'); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Online / Socials -->
            <div class="card">
                <div class="card-header"><h3 class="card-title">🌐 Online Profiles</h3></div>
                <div class="card-body" style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-muted);">Website</div>
                        <?php if ($contact['website']): ?>
                            <a href="<?php echo clean_url($contact['website']); ?>" target="_blank" style="color: var(--primary-color); font-weight: 500; text-decoration: none; display: flex; align-items: center; gap: 0.25rem;">🔗 Visit Website ↗</a>
                        <?php else: ?>
                            <div style="color: var(--text-muted); font-style: italic;">Not provided</div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-muted);">LinkedIn Profile</div>
                        <?php if ($contact['linkedin_url']): ?>
                            <a href="<?php echo clean_url($contact['linkedin_url']); ?>" target="_blank" style="color: var(--primary-color); font-weight: 500; text-decoration: none; display: flex; align-items: center; gap: 0.25rem;">💼 View LinkedIn ↗</a>
                        <?php else: ?>
                            <div style="color: var(--text-muted); font-style: italic;">Not provided</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Relationship Context -->
            <div class="card">
                <div class="card-header"><h3 class="card-title">🤝 Relationship Details</h3></div>
                <div class="card-body" style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-muted);">Date Met</div>
                        <div style="font-weight: 500;"><?php echo $contact['date_met'] ? format_date_user($contact['date_met']) : 'Not specified'; ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-muted);">Place Met</div>
                        <div style="font-weight: 500;"><?php echo e($contact['place_met'] ?: 'Not recorded'); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-muted);">Industry / Category</div>
                        <div style="font-weight: 500;"><?php echo e($contact['industry'] ?: 'Not specified'); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-muted);">Lead Source</div>
                        <div style="font-weight: 500;"><?php echo e($contact['lead_source'] ?: ($contact['source'] ?: 'Manual Entry')); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Phase 9: Notes Section -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">📝 Conversation Notes</h3>
            </div>
            <div class="card-body">
                <!-- Add Note Form -->
                <form id="add-note-form" action="api/notes.php" method="POST" style="margin-bottom: 1.5rem; display: flex; gap: 0.5rem; align-items: flex-start;">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="contact_id" value="<?php echo $contact['id']; ?>">
                    <input type="hidden" name="action" value="add">
                    <div style="flex-grow: 1;">
                        <textarea name="note" required placeholder="Type a new update or conversation note..." style="min-height: 60px; padding: 0.5rem; font-size: 0.9rem; margin-bottom: 0;"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1rem; align-self: flex-end;">Add Note</button>
                </form>

                <!-- Notes List -->
                <div id="notes-container" style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <!-- Will be loaded dynamically in Phase 9. For now, empty state or backend load -->
                    <div style="color: var(--text-muted); font-size: 0.9rem; text-align: center; padding: 1rem;">
                        No notes yet. Add one above.
                    </div>
                </div>
            </div>
        </div>

        <!-- Relationship Timeline -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">⏳ Relationship Timeline</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem 1.25rem;">
                <?php if (empty($interactions)): ?>
                    <div style="color: var(--text-muted); font-size: 0.9rem; text-align: center; padding: 1rem;">
                        No recorded interactions yet. Initial scan or manual setup will populate this list.
                    </div>
                <?php else: ?>
                    <div class="timeline-container" style="position: relative; border-left: 2px solid var(--border-color); padding-left: 1.5rem; margin-left: 0.75rem; display: flex; flex-direction: column; gap: 1.5rem;">
                        <?php foreach ($interactions as $int): 
                            $icon = '📝';
                            $badgeClass = 'badge-secondary';
                            switch ($int['type']) {
                                case 'Scan':
                                    $icon = '📸';
                                    $badgeClass = 'badge-success';
                                    break;
                                case 'Note':
                                    $icon = '📝';
                                    $badgeClass = 'badge-info';
                                    break;
                                case 'Call':
                                    $icon = '📞';
                                    $badgeClass = 'badge-primary';
                                    break;
                                case 'WhatsApp':
                                    $icon = '💬';
                                    $badgeClass = 'badge-success';
                                    break;
                                case 'Email':
                                    $icon = '✉️';
                                    $badgeClass = 'badge-info';
                                    break;
                                case 'Meeting':
                                    $icon = '🤝';
                                    $badgeClass = 'badge-primary';
                                    break;
                                case 'Follow-up':
                                    $icon = '⏰';
                                    $badgeClass = 'badge-warning';
                                    break;
                                case 'Status Change':
                                    $icon = '🔄';
                                    $badgeClass = 'badge-secondary';
                                    break;
                            }
                        ?>
                            <div class="timeline-item" style="position: relative;">
                                <!-- Icon dot -->
                                <div class="timeline-icon" style="position: absolute; left: -2.25rem; top: 0.15rem; background: var(--surface-color); border: 2px solid var(--border-color); border-radius: 50%; width: 1.5rem; height: 1.5rem; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; box-shadow: var(--shadow-sm); z-index: 2;">
                                    <?php echo $icon; ?>
                                </div>
                                <div class="timeline-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                                    <span class="badge <?php echo $badgeClass; ?>" style="font-size: 0.7rem; font-weight: bold; text-transform: uppercase;"><?php echo e($int['type']); ?></span>
                                    <span style="font-size: 0.8rem; color: var(--text-muted);"><?php echo date('d M Y, h:i A', strtotime($int['created_at'])); ?></span>
                                </div>
                                <div class="timeline-desc" style="font-size: 0.9rem; color: var(--text-color); line-height: 1.5; font-weight: 500;">
                                    <?php echo e($int['description']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Side Column: Business Card & Tags -->
    <div>
        <!-- Follow-up Schedule Card -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header"><h3 class="card-title">⏰ Follow-up Schedule</h3></div>
            <div class="card-body">
                <?php if ($activeFollowUp): 
                    $overdue = ($activeFollowUp['follow_up_date'] < $today);
                    $isToday = ($activeFollowUp['follow_up_date'] === $today);
                    $dueDateStyle = 'color: var(--success-color);';
                    if ($overdue) {
                        $dueDateStyle = 'color: var(--danger-color);';
                    } elseif ($isToday) {
                        $dueDateStyle = 'color: var(--warning-color);';
                    }
                ?>
                    <div style="margin-bottom: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem;">
                        <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.15rem;">Next Touchpoint</div>
                        <div style="font-weight: 600; font-size: 1.1rem; <?php echo $dueDateStyle; ?>">
                            <?php echo $overdue ? '⚠️ Overdue (' . format_date_user($activeFollowUp['follow_up_date']) . ')' : ($isToday ? '📅 Today' : format_date_user($activeFollowUp['follow_up_date'])); ?>
                        </div>
                        <div style="margin-top: 0.35rem; display: flex; gap: 0.25rem;">
                            <span class="badge badge-<?php echo strtolower($activeFollowUp['priority']); ?>"><?php echo $activeFollowUp['priority']; ?> Priority</span>
                            <span class="badge badge-secondary">Pending</span>
                        </div>
                        <?php if ($activeFollowUp['notes']): ?>
                            <div style="font-size: 0.8rem; font-style: italic; color: var(--text-muted); margin-top: 0.5rem; line-height: 1.4; border-left: 2px solid var(--border-color); padding-left: 0.5rem;">
                                <?php echo e($activeFollowUp['notes']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <button type="button" class="btn btn-primary" style="font-size: 0.85rem; padding: 0.45rem;" onclick="openProfileCompleteModal(<?php echo $activeFollowUp['id']; ?>)">✅ Complete Follow-up</button>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                            <button type="button" class="btn btn-secondary" style="font-size: 0.85rem; padding: 0.45rem;" onclick="openProfileSnoozeModal(<?php echo $activeFollowUp['id']; ?>)">⏰ Snooze</button>
                            <button type="button" class="btn btn-secondary" style="font-size: 0.85rem; padding: 0.45rem;" onclick="openProfileEditModal(<?php echo $activeFollowUp['id']; ?>, '<?php echo $activeFollowUp['follow_up_date']; ?>', '<?php echo $activeFollowUp['priority']; ?>', '<?php echo e(addslashes($activeFollowUp['notes'])); ?>')">✏️ Edit</button>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="color: var(--text-muted); font-size: 0.85rem; font-style: italic; margin-bottom: 1rem; text-align: center;">
                        No pending follow-up scheduled.
                    </div>
                    
                    <!-- Form to create follow-up -->
                    <form id="profile-followup-create-form" action="api/followup.php" method="POST">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="contact_id" value="<?php echo $contact['id']; ?>">
                        
                        <div class="form-group" style="margin-bottom: 0.75rem;">
                            <label for="new_fu_date" style="font-size: 0.8rem; margin-bottom: 0.15rem;">Select Date</label>
                            <input type="date" id="new_fu_date" name="follow_up_date" required style="padding: 0.4rem; font-size: 0.85rem; margin-bottom: 0;">
                        </div>
                        <div class="form-group" style="margin-bottom: 0.75rem;">
                            <label for="new_fu_priority" style="font-size: 0.8rem; margin-bottom: 0.15rem;">Priority</label>
                            <select id="new_fu_priority" name="priority" style="padding: 0.4rem; font-size: 0.85rem; margin-bottom: 0;">
                                <option value="Low">Low</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="High">High</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0.75rem;">
                            <label for="new_fu_notes" style="font-size: 0.8rem; margin-bottom: 0.15rem;">Goal / Note</label>
                            <textarea id="new_fu_notes" name="notes" placeholder="Discuss demo setup..." style="min-height: 50px; font-size: 0.85rem; padding: 0.4rem; margin-bottom: 0;"></textarea>
                        </div>
                        <button type="submit" class="btn btn-secondary btn-block" style="font-size: 0.85rem; padding: 0.45rem;">Schedule Follow-up</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tags Card -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header"><h3 class="card-title">🏷️ Contact Tags</h3></div>
            <div class="card-body">
                <!-- Tags Chips List -->
                <div id="tags-container" style="display: flex; flex-wrap: wrap; gap: 0.35rem; margin-bottom: 1rem;">
                    <!-- Will load dynamically in Phase 9 -->
                    <span style="color: var(--text-muted); font-size: 0.85rem; font-style: italic;">No tags.</span>
                </div>
                
                <!-- Add Tag Form -->
                <form id="add-tag-form" action="api/tags.php" method="POST" style="display: flex; gap: 0.25rem;">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="contact_id" value="<?php echo $contact['id']; ?>">
                    <input type="hidden" name="action" value="attach">
                    <input type="text" name="tag_name" required placeholder="Add tag..." style="padding: 0.4rem; font-size: 0.85rem; flex-grow: 1;">
                    <button type="submit" class="btn btn-secondary" style="padding: 0.4rem 0.75rem; font-size: 0.85rem;">+</button>
                </form>
            </div>
        </div>

        <!-- Business Card Image Card -->
        <div class="card">
            <div class="card-header"><h3 class="card-title">📸 Scanned Card Image</h3></div>
            <div class="card-body" style="padding: 0.5rem; text-align: center;">
                <?php if ($contact['original_card_image']): ?>
                    <!-- Private Auth Image Serving Endpoint -->
                    <div style="background-color: #f1f5f9; border-radius: var(--radius-sm); overflow: hidden; border: 1px solid var(--border-color); position: relative;">
                        <img src="api/view_card.php?id=<?php echo $contact['id']; ?>" alt="Business Card Scanned Image" style="width: 100%; height: auto; display: block; object-fit: contain;">
                    </div>
                <?php else: ?>
                    <div style="padding: 2rem 1rem; color: var(--text-muted); font-size: 0.9rem; font-style: italic;">
                        No card image scanned for this contact.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.tag-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background-color: var(--primary-light);
    color: var(--primary-color);
    padding: 0.25rem 0.625rem;
    font-size: 0.8rem;
    font-weight: 500;
    border-radius: 50px;
}
.tag-chip button {
    background: none;
    border: none;
    color: var(--primary-color);
    cursor: pointer;
    font-weight: 700;
    font-size: 0.9rem;
    padding: 0;
    line-height: 1;
}
.tag-chip button:hover {
    color: var(--danger-color);
}
.note-item {
    padding: 0.75rem 1rem;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    background-color: var(--background-color);
}
.note-meta {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-bottom: 0.25rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.note-delete-btn {
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    font-size: 0.8rem;
}
.note-delete-btn:hover {
    color: var(--danger-color);
}
.note-text {
    font-size: 0.9rem;
    white-space: pre-wrap;
    color: var(--text-color);
}
</style>

<script>
    const contactId = <?php echo $contact['id']; ?>;

    // Helper to escape HTML dynamically
    function escapeHTML(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;')
                  .replace(/</g, '&lt;')
                  .replace(/>/g, '&gt;')
                  .replace(/"/g, '&quot;')
                  .replace(/'/g, '&#039;');
    }

    // 1. NOTES LOAD & SUBMIT SYSTEM
    function loadNotes() {
        const notesContainer = document.getElementById('notes-container');
        
        fetch(`api/notes.php?contact_id=${contactId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.notes.length === 0) {
                    notesContainer.innerHTML = `
                        <div style="color: var(--text-muted); font-size: 0.9rem; text-align: center; padding: 1rem;">
                            No notes yet. Add one above.
                        </div>`;
                    return;
                }
                
                notesContainer.innerHTML = data.notes.map(note => `
                    <div class="note-item" id="note-card-${note.id}">
                        <div class="note-meta">
                            <span>📅 ${escapeHTML(note.created_at)}</span>
                            <button type="button" class="note-delete-btn" onclick="deleteNote(${note.id})">🗑️ Delete</button>
                        </div>
                        <div class="note-text">${escapeHTML(note.note)}</div>
                    </div>
                `).join('');
            }
        })
        .catch(err => console.error("Notes error:", err));
    }

    document.getElementById('add-note-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = this;
        const formData = new FormData(form);
        
        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json',
                'X-CSRF-Token': formData.get('csrf_token')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                form.querySelector('textarea').value = '';
                showToast('Note added successfully.', 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showToast(data.message || 'Failed to add note.', 'error');
            }
        })
        .catch(err => showToast('Error: ' + err.message, 'error'));
    });

    window.deleteNote = function(noteId) {
        if (!confirm('Are you sure you want to delete this note?')) return;
        
        const csrfToken = document.querySelector('input[name="csrf_token"]').value;
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('note_id', noteId);
        formData.append('csrf_token', csrfToken);
        
        fetch('api/notes.php', {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json',
                'X-CSRF-Token': csrfToken
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const noteCard = document.getElementById(`note-card-${noteId}`);
                if (noteCard) noteCard.remove();
                showToast('Note deleted successfully.', 'success');
                loadNotes();
            } else {
                showToast(data.message || 'Failed to delete note.', 'error');
            }
        })
        .catch(err => showToast('Error: ' + err.message, 'error'));
    };

    // 2. TAGS LOAD & SUBMIT SYSTEM
    function loadTags() {
        const tagsContainer = document.getElementById('tags-container');
        
        fetch(`api/tags.php?contact_id=${contactId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.tags.length === 0) {
                    tagsContainer.innerHTML = '<span style="color: var(--text-muted); font-size: 0.85rem; font-style: italic;">No tags.</span>';
                    return;
                }
                
                tagsContainer.innerHTML = data.tags.map(tag => `
                    <span class="tag-chip">
                        🏷️ ${escapeHTML(tag.name)}
                        <button type="button" onclick="detachTag(${tag.id})" title="Remove tag">&times;</button>
                    </span>
                `).join('');
            }
        })
        .catch(err => console.error("Tags error:", err));
    }

    document.getElementById('add-tag-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = this;
        const formData = new FormData(form);
        
        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json',
                'X-CSRF-Token': formData.get('csrf_token')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                form.querySelector('input[name="tag_name"]').value = '';
                showToast('Tag added successfully.', 'success');
                loadTags();
            } else {
                showToast(data.message || 'Failed to add tag.', 'error');
            }
        })
        .catch(err => showToast('Error: ' + err.message, 'error'));
    });

    window.detachTag = function(tagId) {
        const csrfToken = document.querySelector('input[name="csrf_token"]').value;
        const formData = new FormData();
        formData.append('action', 'detach');
        formData.append('contact_id', contactId);
        formData.append('tag_id', tagId);
        formData.append('csrf_token', csrfToken);
        
        fetch('api/tags.php', {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json',
                'X-CSRF-Token': csrfToken
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Tag removed.', 'info');
                loadTags();
            } else {
                showToast(data.message || 'Failed to remove tag.', 'error');
            }
        })
        .catch(err => showToast('Error: ' + err.message, 'error'));
    };

    // 3. CONTACT CRITICAL ACTIONS
    document.getElementById('delete-contact-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = this;
        const formData = new FormData(form);
        
        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json',
                'X-CSRF-Token': formData.get('csrf_token')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.redirect) {
                showToast('Contact deleted successfully.', 'success');
                setTimeout(() => window.location.href = data.redirect, 1000);
            } else {
                showToast(data.message || 'An error occurred while deleting the contact.', 'error');
            }
        })
        .catch(error => {
            showToast('Failed to delete contact: ' + error.message, 'error');
        });
    });

    // 4. AUTOMATIC ACTION LOGGING SYSTEM
    const callBtn = document.querySelector('a[href^="tel:"]');
    
    function logAction(type, description) {
        const formData = new FormData();
        formData.append('contact_id', contactId);
        formData.append('type', type);
        formData.append('description', description);
        formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
        
        fetch('api/log_interaction.php', {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json',
                'X-CSRF-Token': document.querySelector('input[name="csrf_token"]').value
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log(`${type} action logged to timeline.`);
            } else {
                console.error('Failed to log action: ' + data.message);
            }
        })
        .catch(err => console.error('Error logging action:', err));
    }

    if (callBtn) {
        callBtn.addEventListener('click', () => {
            logAction('Call', 'Initiated phone call to mobile.');
        });
    }

    // Run on startup
    loadNotes();
    loadTags();
</script>

<!-- MODAL: COMPLETE FOLLOW-UP -->
<div id="profile-complete-modal" class="modal-overlay hidden">
    <div class="modal-card">
        <div class="modal-header">
            <h3>✅ Complete Follow-up</h3>
        </div>
        <form id="profile-complete-form">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="complete">
            <input type="hidden" name="id" id="profile-complete-id">
            <div class="modal-body" style="padding: 1.25rem;">
                <p>Add follow-up outcome notes:</p>
                <div class="form-group">
                    <label for="profile_completion_notes">Follow-up Notes / Outcomes</label>
                    <textarea id="profile_completion_notes" name="completion_notes" required placeholder="Discussed software specs, scheduled demo session..." style="min-height: 100px; margin-bottom: 0;"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeProfileModal('profile-complete-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save & Mark Completed</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: SNOOZE FOLLOW-UP -->
<div id="profile-snooze-modal" class="modal-overlay hidden">
    <div class="modal-card">
        <div class="modal-header">
            <h3>⏰ Snooze Scheduled Follow-up</h3>
        </div>
        <form id="profile-snooze-form">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="snooze">
            <input type="hidden" name="id" id="profile-snooze-id">
            <div class="modal-body" style="padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem;">
                <p>Select snooze duration:</p>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem;">
                    <button type="button" class="btn btn-secondary" style="font-size: 0.85rem;" onclick="submitProfileSnoozeDays(1)">Tomorrow</button>
                    <button type="button" class="btn btn-secondary" style="font-size: 0.85rem;" onclick="submitProfileSnoozeDays(3)">3 Days</button>
                    <button type="button" class="btn btn-secondary" style="font-size: 0.85rem;" onclick="submitProfileSnoozeDays(7)">1 Week</button>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="profile_custom_snooze_date">Or select custom date:</label>
                    <input type="date" id="profile_custom_snooze_date" name="custom_date" style="margin-bottom: 0;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeProfileModal('profile-snooze-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Reschedule Date</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: EDIT FOLLOW-UP -->
<div id="profile-edit-modal" class="modal-overlay hidden">
    <div class="modal-card">
        <div class="modal-header">
            <h3>✏️ Edit Follow-up Details</h3>
        </div>
        <form id="profile-edit-form">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="profile-edit-id">
            <div class="modal-body" style="padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem;">
                <div class="form-group">
                    <label for="profile_edit_date">Due Date</label>
                    <input type="date" id="profile_edit_date" name="follow_up_date" required>
                </div>
                <div class="form-group">
                    <label for="profile_edit_priority">Priority</label>
                    <select id="profile_edit_priority" name="priority">
                        <option value="Low">Low</option>
                        <option value="Medium">Medium</option>
                        <option value="High">High</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="profile_edit_notes">Follow-up Goal / Notes</label>
                    <textarea id="profile_edit_notes" name="notes" placeholder="Goal of this scheduled conversation..." style="min-height: 80px; margin-bottom: 0;"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeProfileModal('profile-edit-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Profile Follow-up Modal Helpers
    window.openProfileModal = function(id) {
        document.getElementById(id).classList.remove('hidden');
    }
    window.closeProfileModal = function(id) {
        document.getElementById(id).classList.add('hidden');
    }
    window.openProfileCompleteModal = function(id) {
        document.getElementById('profile-complete-id').value = id;
        document.getElementById('profile_completion_notes').value = '';
        openProfileModal('profile-complete-modal');
    }
    window.openProfileSnoozeModal = function(id) {
        document.getElementById('profile-snooze-id').value = id;
        document.getElementById('profile_custom_snooze_date').value = '';
        openProfileModal('profile-snooze-modal');
    }
    window.submitProfileSnoozeDays = function(days) {
        const id = document.getElementById('profile-snooze-id').value;
        const csrfToken = document.querySelector('#profile-snooze-form input[name="csrf_token"]').value;
        const formData = new FormData();
        formData.append('action', 'snooze');
        formData.append('id', id);
        formData.append('days', days);
        formData.append('csrf_token', csrfToken);
        submitFollowUpForm(formData);
    }
    window.openProfileEditModal = function(id, date, priority, notes) {
        document.getElementById('profile-edit-id').value = id;
        document.getElementById('profile_edit_date').value = date;
        document.getElementById('profile_edit_priority').value = priority;
        document.getElementById('profile_edit_notes').value = notes;
        openProfileModal('profile-edit-modal');
    }

    function submitFollowUpForm(formData) {
        fetch('api/followup.php', {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(data.message || 'Operation succeeded.', 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showToast(data.message || 'Operation failed.', 'error');
            }
        })
        .catch(err => showToast('Error: ' + err.message, 'error'));
    }

    // Bind profile forms
    document.getElementById('profile-complete-form').addEventListener('submit', function(e) {
        e.preventDefault();
        submitFollowUpForm(new FormData(this));
    });
    document.getElementById('profile-snooze-form').addEventListener('submit', function(e) {
        e.preventDefault();
        submitFollowUpForm(new FormData(this));
    });
    document.getElementById('profile-edit-form').addEventListener('submit', function(e) {
        e.preventDefault();
        submitFollowUpForm(new FormData(this));
    });

    const createFuForm = document.getElementById('profile-followup-create-form');
    if (createFuForm) {
        createFuForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitFollowUpForm(new FormData(this));
        });
    }
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
