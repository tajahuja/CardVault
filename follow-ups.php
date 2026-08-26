<?php
/**
 * Follow-up Center Dashboard
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
$pdo = require_once __DIR__ . '/includes/db.php';

// Assert login
require_login();

$userId = $_SESSION['user_id'];
$currentPage = 'follow-ups.php';
$pageTitle = 'Follow-up Center';

$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$endOfWeek = date('Y-m-d', strtotime('sunday this week'));

// --- Retrieve Summary Counts (Pending Only) ---
$numOverdue = 0; $numToday = 0; $numTomorrow = 0; $numWeek = 0; $numUpcoming = 0;
try {
    $cOverdue = $pdo->prepare("SELECT COUNT(*) FROM follow_ups WHERE user_id = :uid AND status = 'Pending' AND follow_up_date < :today");
    $cOverdue->execute(['uid' => $userId, 'today' => $today]);
    $numOverdue = $cOverdue->fetchColumn();

    $cToday = $pdo->prepare("SELECT COUNT(*) FROM follow_ups WHERE user_id = :uid AND status = 'Pending' AND follow_up_date = :today");
    $cToday->execute(['uid' => $userId, 'today' => $today]);
    $numToday = $cToday->fetchColumn();

    $cTomorrow = $pdo->prepare("SELECT COUNT(*) FROM follow_ups WHERE user_id = :uid AND status = 'Pending' AND follow_up_date = :tomorrow");
    $cTomorrow->execute(['uid' => $userId, 'tomorrow' => $tomorrow]);
    $numTomorrow = $cTomorrow->fetchColumn();

    $cWeek = $pdo->prepare("SELECT COUNT(*) FROM follow_ups WHERE user_id = :uid AND status = 'Pending' AND follow_up_date >= :today AND follow_up_date <= :end_week");
    $cWeek->execute(['uid' => $userId, 'today' => $today, 'end_week' => $endOfWeek]);
    $numWeek = $cWeek->fetchColumn();

    $cUpcoming = $pdo->prepare("SELECT COUNT(*) FROM follow_ups WHERE user_id = :uid AND status = 'Pending' AND follow_up_date > :tomorrow");
    $cUpcoming->execute(['uid' => $userId, 'tomorrow' => $tomorrow]);
    $numUpcoming = $cUpcoming->fetchColumn();
} catch (\PDOException $e) {
    error_log("Summary counters query failed: " . $e->getMessage());
}

// --- Retrieve Filtered List ---
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : 'all';
$where = "WHERE f.user_id = :uid";
$params = ['uid' => $userId];

switch ($filter) {
    case 'overdue':
        $where .= " AND f.status = 'Pending' AND f.follow_up_date < :today";
        $params['today'] = $today;
        break;
    case 'today':
        $where .= " AND f.status = 'Pending' AND f.follow_up_date = :today";
        $params['today'] = $today;
        break;
    case 'tomorrow':
        $where .= " AND f.status = 'Pending' AND f.follow_up_date = :tomorrow";
        $params['tomorrow'] = $tomorrow;
        break;
    case 'this_week':
        $where .= " AND f.status = 'Pending' AND f.follow_up_date >= :today AND f.follow_up_date <= :end_week";
        $params['today'] = $today;
        $params['end_week'] = $endOfWeek;
        break;
    case 'high_priority':
        $where .= " AND f.status = 'Pending' AND f.priority = 'High'";
        break;
    case 'completed':
        $where .= " AND f.status = 'Completed'";
        break;
    default:
        $where .= " AND f.status = 'Pending'";
        break;
}

$followUps = [];
try {
    $sql = "
        SELECT f.*, c.full_name, c.company, c.job_title, c.phone, c.email, c.website,
               (SELECT CONCAT(type, '|', description, '|', created_at) 
                FROM interactions 
                WHERE contact_id = f.contact_id AND user_id = f.user_id 
                ORDER BY created_at DESC LIMIT 1) as last_interaction
        FROM follow_ups f
        JOIN contacts c ON f.contact_id = c.id
        $where
        ORDER BY f.follow_up_date ASC, f.priority DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $followUps = $stmt->fetchAll();
} catch (\PDOException $e) {
    error_log("Follow-ups list query failed: " . $e->getMessage());
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Premium UI Styles inside page scope for CRM quality rendering -->
<style>
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    .summary-card {
        background: var(--surface-color);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        padding: 1.25rem 1rem;
        text-align: center;
        box-shadow: var(--shadow-sm);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .summary-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }
    .summary-count {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.25rem;
    }
    .summary-label {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        color: var(--text-muted);
    }
    .filter-pills {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid var(--border-color);
    }
    .filter-pill {
        display: inline-block;
        padding: 0.45rem 1rem;
        font-size: 0.85rem;
        font-weight: 500;
        background: var(--surface-color);
        border: 1px solid var(--border-color);
        border-radius: 20px;
        color: var(--text-color);
        text-decoration: none;
        transition: all 0.2s;
    }
    .filter-pill:hover {
        border-color: var(--primary-color);
        color: var(--primary-color);
    }
    .filter-pill.active {
        background: var(--primary-color);
        border-color: var(--primary-color);
        color: white;
    }
    .followup-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1.25rem;
    }
    .followup-card {
        background: var(--surface-color);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-shadow: var(--shadow-sm);
        overflow: hidden;
        position: relative;
    }
    .priority-indicator {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
    }
    .priority-high { background-color: var(--danger-color); }
    .priority-medium { background-color: var(--warning-color); }
    .priority-low { background-color: var(--success-color); }

    .card-body-content {
        padding: 1.25rem;
    }
    .card-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.75rem;
    }
    .due-date-badge {
        font-size: 0.75rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }
    .due-overdue { color: var(--danger-color); }
    .due-today { color: var(--warning-color); }
    .due-future { color: var(--success-color); }

    .card-header-info h4 {
        font-size: 1.1rem;
        margin-bottom: 0.15rem;
        color: var(--secondary-color);
    }
    .card-header-info p {
        font-size: 0.85rem;
        color: var(--text-muted);
        margin-bottom: 0.75rem;
    }
    .interaction-excerpt {
        font-size: 0.8rem;
        background: var(--primary-light);
        color: var(--primary-color);
        padding: 0.5rem 0.75rem;
        border-radius: var(--radius-sm);
        margin-bottom: 0.75rem;
    }
    .followup-notes {
        font-size: 0.85rem;
        line-height: 1.45;
        border-left: 2px solid var(--border-color);
        padding-left: 0.5rem;
        margin-bottom: 1rem;
        color: var(--text-color);
    }
    .card-footer-actions {
        background: var(--primary-light);
        border-top: 1px solid var(--border-color);
        padding: 0.75rem 1.25rem;
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .btn-action-sm {
        padding: 0.35rem 0.65rem;
        font-size: 0.75rem;
        border-radius: var(--radius-sm);
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }
</style>

<div class="page-header">
    <div class="page-title">
        <h1>Follow-up Center</h1>
        <p>Premium workspace to manage relationship touchpoints</p>
    </div>
</div>

<!-- Summary Status Bar -->
<div class="summary-grid">
    <div class="summary-card">
        <div class="summary-count <?php echo $numOverdue > 0 ? 'text-danger' : ''; ?>" style="color: <?php echo $numOverdue > 0 ? 'var(--danger-color)' : 'var(--text-color)'; ?>;"><?php echo $numOverdue; ?></div>
        <div class="summary-label">Overdue</div>
    </div>
    <div class="summary-card">
        <div class="summary-count" style="color: <?php echo $numToday > 0 ? 'var(--warning-color)' : 'var(--text-color)'; ?>;"><?php echo $numToday; ?></div>
        <div class="summary-label">Today</div>
    </div>
    <div class="summary-card">
        <div class="summary-count"><?php echo $numTomorrow; ?></div>
        <div class="summary-label">Tomorrow</div>
    </div>
    <div class="summary-card">
        <div class="summary-count"><?php echo $numWeek; ?></div>
        <div class="summary-label">This Week</div>
    </div>
    <div class="summary-card">
        <div class="summary-count"><?php echo $numUpcoming; ?></div>
        <div class="summary-label">Upcoming</div>
    </div>
</div>

<!-- Filter Toggles -->
<div class="filter-pills">
    <a href="follow-ups.php?filter=all" class="filter-pill <?php echo $filter === 'all' ? 'active' : ''; ?>">All Pending</a>
    <a href="follow-ups.php?filter=overdue" class="filter-pill <?php echo $filter === 'overdue' ? 'active' : ''; ?>">⚠️ Overdue (<?php echo $numOverdue; ?>)</a>
    <a href="follow-ups.php?filter=today" class="filter-pill <?php echo $filter === 'today' ? 'active' : ''; ?>">📅 Today (<?php echo $numToday; ?>)</a>
    <a href="follow-ups.php?filter=tomorrow" class="filter-pill <?php echo $filter === 'tomorrow' ? 'active' : ''; ?>">Tomorrow</a>
    <a href="follow-ups.php?filter=this_week" class="filter-pill <?php echo $filter === 'this_week' ? 'active' : ''; ?>">This Week</a>
    <a href="follow-ups.php?filter=high_priority" class="filter-pill <?php echo $filter === 'high_priority' ? 'active' : ''; ?>">🔴 High Priority</a>
    <a href="follow-ups.php?filter=completed" class="filter-pill <?php echo $filter === 'completed' ? 'active' : ''; ?>">✅ Completed</a>
</div>

<!-- Follow-ups Content -->
<?php if (empty($followUps)): ?>
    <div class="card" style="text-align: center; padding: 3rem 1.5rem;">
        <div style="font-size: 3rem; margin-bottom: 1rem;">🗓️</div>
        <h3>No follow-ups found</h3>
        <p style="color: var(--text-muted); margin-bottom: 1.5rem;">No touchpoints match your selected filter right now.</p>
        <div style="display: flex; gap: 0.5rem; justify-content: center;">
            <a href="contacts.php" class="btn btn-primary">Browse Contacts</a>
            <a href="scan.php" class="btn btn-secondary">Scan Card</a>
        </div>
    </div>
<?php else: ?>
    <div class="followup-grid">
        <?php foreach ($followUps as $fu): 
            $overdue = ($fu['status'] === 'Pending' && $fu['follow_up_date'] < $today);
            $isToday = ($fu['status'] === 'Pending' && $fu['follow_up_date'] === $today);
            
            $dueClass = 'due-future';
            $dueLabel = format_date_user($fu['follow_up_date']);
            if ($overdue) {
                $dueClass = 'due-overdue';
                $dueLabel = '⚠️ Overdue (' . format_date_user($fu['follow_up_date']) . ')';
            } elseif ($isToday) {
                $dueClass = 'due-today';
                $dueLabel = '📅 Today';
            }
            
            // Format Priority indicators
            $indicatorClass = 'priority-medium';
            if ($fu['priority'] === 'High') $indicatorClass = 'priority-high';
            if ($fu['priority'] === 'Low') $indicatorClass = 'priority-low';
            
            // Format Last interaction excerpt
            $lastIntText = 'No recorded interactions.';
            if ($fu['last_interaction']) {
                $parts = explode('|', $fu['last_interaction']);
                if (count($parts) >= 2) {
                    $lastIntText = "Last: " . $parts[0] . " - " . (strlen($parts[1]) > 50 ? substr($parts[1], 0, 50) . '...' : $parts[1]);
                }
            }
        ?>
            <div class="followup-card" id="followup-card-<?php echo $fu['id']; ?>">
                <div class="priority-indicator <?php echo $indicatorClass; ?>"></div>
                <div class="card-body-content">
                    <div class="card-meta">
                        <span class="due-date-badge <?php echo $dueClass; ?>"><?php echo $dueLabel; ?></span>
                        <span class="badge badge-<?php echo strtolower($fu['priority']); ?>"><?php echo $fu['priority']; ?> Priority</span>
                    </div>
                    
                    <div class="card-header-info">
                        <h4><a href="contact.php?id=<?php echo $fu['contact_id']; ?>" style="text-decoration: none; color: inherit; font-weight: 600;"><?php echo e($fu['full_name']); ?></a></h4>
                        <p><?php echo e($fu['job_title'] ?: 'Designation'); ?> — <strong><?php echo e($fu['company'] ?: 'Company'); ?></strong></p>
                    </div>

                    <?php if ($fu['last_interaction']): ?>
                        <div class="interaction-excerpt" title="Interaction details">
                            <?php echo e($lastIntText); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($fu['notes']): ?>
                        <div class="followup-notes">
                            <?php echo e($fu['notes']); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card-footer-actions">
                    <?php if ($fu['status'] === 'Pending'): ?>
                        <button type="button" class="btn btn-primary btn-action-sm" onclick="openCompleteModal(<?php echo $fu['id']; ?>, '<?php echo e(addslashes($fu['full_name'])); ?>')">✅ Complete</button>
                        <button type="button" class="btn btn-secondary btn-action-sm" onclick="openSnoozeModal(<?php echo $fu['id']; ?>)">⏰ Snooze</button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-secondary btn-action-sm" onclick="openEditModal(<?php echo $fu['id']; ?>, '<?php echo $fu['follow_up_date']; ?>', '<?php echo $fu['priority']; ?>', '<?php echo e(addslashes($fu['notes'])); ?>')">✏️ Edit</button>
                    
                    <?php if ($fu['phone']): ?>
                        <a href="tel:<?php echo e($fu['phone']); ?>" class="btn btn-secondary btn-action-sm" onclick="logQuickAction(<?php echo $fu['contact_id']; ?>, 'Call', 'Called contact mobile.')">📞 Call</a>
                        <a href="https://wa.me/<?php echo preg_replace('/\D/', '', $fu['phone']); ?>" target="_blank" class="btn btn-secondary btn-action-sm" style="background-color: #25d366; color: white; border-color: #20ba5a;" onclick="logQuickAction(<?php echo $fu['contact_id']; ?>, 'WhatsApp', 'Initiated WhatsApp follow-up.')">💬 WhatsApp</a>
                    <?php endif; ?>
                    
                    <?php if ($fu['email']): ?>
                        <a href="mailto:<?php echo e($fu['email']); ?>" class="btn btn-secondary btn-action-sm" onclick="logQuickAction(<?php echo $fu['contact_id']; ?>, 'Email', 'Initiated outbound email follow-up.')">✉️ Email</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- MODAL: COMPLETE FOLLOW-UP -->
<div id="complete-modal" class="modal-overlay hidden">
    <div class="modal-card">
        <div class="modal-header">
            <h3>✅ Complete Follow-up</h3>
        </div>
        <form id="complete-form">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="complete">
            <input type="hidden" name="id" id="complete-id">
            <div class="modal-body" style="padding: 1.25rem;">
                <p>Complete follow-up with <strong id="complete-name-label"></strong>. Add touchpoint notes below:</p>
                <div class="form-group">
                    <label for="completion_notes">Follow-up Notes / Outcomes</label>
                    <textarea id="completion_notes" name="completion_notes" required placeholder="Discussed software specs, scheduled demo session..." style="min-height: 100px; margin-bottom: 0;"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('complete-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save & Mark Completed</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: SNOOZE FOLLOW-UP -->
<div id="snooze-modal" class="modal-overlay hidden">
    <div class="modal-card">
        <div class="modal-header">
            <h3>⏰ Snooze Scheduled Follow-up</h3>
        </div>
        <form id="snooze-form">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="snooze">
            <input type="hidden" name="id" id="snooze-id">
            <div class="modal-body" style="padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem;">
                <p>Select snooze duration:</p>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem;">
                    <button type="button" class="btn btn-secondary" style="font-size: 0.85rem;" onclick="submitSnoozeDays(1)">Tomorrow</button>
                    <button type="button" class="btn btn-secondary" style="font-size: 0.85rem;" onclick="submitSnoozeDays(3)">3 Days</button>
                    <button type="button" class="btn btn-secondary" style="font-size: 0.85rem;" onclick="submitSnoozeDays(7)">1 Week</button>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="custom_snooze_date">Or select custom date:</label>
                    <input type="date" id="custom_snooze_date" name="custom_date" style="margin-bottom: 0;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('snooze-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Reschedule Date</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: EDIT FOLLOW-UP -->
<div id="edit-modal" class="modal-overlay hidden">
    <div class="modal-card">
        <div class="modal-header">
            <h3>✏️ Edit Follow-up Details</h3>
        </div>
        <form id="edit-form">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit-id">
            <div class="modal-body" style="padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem;">
                <div class="form-group">
                    <label for="edit_date">Due Date</label>
                    <input type="date" id="edit_date" name="follow_up_date" required>
                </div>
                <div class="form-group">
                    <label for="edit_priority">Priority</label>
                    <select id="edit_priority" name="priority">
                        <option value="Low">Low</option>
                        <option value="Medium">Medium</option>
                        <option value="High">High</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="edit_notes">Follow-up Goal / Notes</label>
                    <textarea id="edit_notes" name="notes" placeholder="Goal of this scheduled conversation..." style="min-height: 80px; margin-bottom: 0;"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('edit-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Modal Helpers
    function openModal(id) {
        document.getElementById(id).classList.remove('hidden');
    }
    function closeModal(id) {
        document.getElementById(id).classList.add('hidden');
    }

    // Complete Modal
    function openCompleteModal(id, name) {
        document.getElementById('complete-id').value = id;
        document.getElementById('complete-name-label').textContent = name;
        document.getElementById('completion_notes').value = '';
        openModal('complete-modal');
    }

    // Snooze Modal
    function openSnoozeModal(id) {
        document.getElementById('snooze-id').value = id;
        document.getElementById('custom_snooze_date').value = '';
        openModal('snooze-modal');
    }

    function submitSnoozeDays(days) {
        const id = document.getElementById('snooze-id').value;
        const csrfToken = document.querySelector('#snooze-form input[name="csrf_token"]').value;
        
        const formData = new FormData();
        formData.append('action', 'snooze');
        formData.append('id', id);
        formData.append('days', days);
        formData.append('csrf_token', csrfToken);
        
        sendFollowUpAction(formData);
    }

    // Edit Modal
    function openEditModal(id, date, priority, notes) {
        document.getElementById('edit-id').value = id;
        document.getElementById('edit_date').value = date;
        document.getElementById('edit_priority').value = priority;
        document.getElementById('edit_notes').value = notes;
        openModal('edit-modal');
    }

    // AJAX action submission
    function sendFollowUpAction(formData) {
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
                // Show a brief success toast before reloading
                showToast(data.message || 'Operation succeeded.', 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showToast(data.message || 'Operation failed.', 'error');
            }
        })
        .catch(err => showToast('Error: ' + err.message, 'error'));
    }

    // Quick action log hook
    function logQuickAction(contactId, type, description) {
        const csrfToken = document.querySelector('input[name="csrf_token"]').value;
        const formData = new FormData();
        formData.append('contact_id', contactId);
        formData.append('type', type);
        formData.append('description', description);
        formData.append('csrf_token', csrfToken);

        fetch('api/log_interaction.php', {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(`${type} interaction logged to timeline.`, 'success');
            }
        })
        .catch(err => showToast('Failed to log interaction: ' + err.message, 'error'));
    }

    // Form Submits
    document.getElementById('complete-form').addEventListener('submit', function(e) {
        e.preventDefault();
        sendFollowUpAction(new FormData(this));
    });

    document.getElementById('snooze-form').addEventListener('submit', function(e) {
        e.preventDefault();
        sendFollowUpAction(new FormData(this));
    });

    document.getElementById('edit-form').addEventListener('submit', function(e) {
        e.preventDefault();
        sendFollowUpAction(new FormData(this));
    });
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
