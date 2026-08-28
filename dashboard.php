<?php
/**
 * User CRM Dashboard Page
 */

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
$pdo = require __DIR__ . '/includes/db.php';

$userId = $_SESSION['user_id'];

// Initialize statistics
$totalContacts = 0;
$contactsThisMonth = 0;
$followupsDue = 0;
$recentContacts = [];
$upcomingFollowups = [];
$overdueActions = [];
$highPriorityActions = [];
$inactiveContacts = [];

try {
    // 1. Total Contacts
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM contacts WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $userId]);
    $totalContacts = $stmt->fetchColumn();

    // 2. Contacts added this month
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM contacts 
        WHERE user_id = :user_id 
          AND created_at >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')
    ");
    $stmt->execute(['user_id' => $userId]);
    $contactsThisMonth = $stmt->fetchColumn();

    // 3. Follow-ups due (Pending status in follow_ups table where date <= today)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM follow_ups 
        WHERE user_id = :user_id 
          AND follow_up_date <= CURRENT_DATE() 
          AND status = 'Pending'
    ");
    $stmt->execute(['user_id' => $userId]);
    $followupsDue = $stmt->fetchColumn();

    // 4. Recently Added Contacts
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, full_name, company, job_title, status, phone, email, created_at 
        FROM contacts 
        WHERE user_id = :user_id 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute(['user_id' => $userId]);
    $recentContacts = $stmt->fetchAll();

    // 5. Upcoming Follow-ups (Pending status in follow_ups table)
    $stmt = $pdo->prepare("
        SELECT f.id, c.id as contact_id, c.full_name, c.company, f.follow_up_date 
        FROM follow_ups f
        JOIN contacts c ON f.contact_id = c.id
        WHERE f.user_id = :user_id 
          AND f.follow_up_date >= CURRENT_DATE() 
          AND f.status = 'Pending' 
        ORDER BY f.follow_up_date ASC 
        LIMIT 5
    ");
    $stmt->execute(['user_id' => $userId]);
    $upcomingFollowups = $stmt->fetchAll();

    // 6. Overdue actions list
    $stmtOverdue = $pdo->prepare("
        SELECT f.id, c.id as contact_id, c.full_name, c.company, f.follow_up_date, f.priority 
        FROM follow_ups f
        JOIN contacts c ON f.contact_id = c.id
        WHERE f.user_id = :user_id AND f.status = 'Pending' AND f.follow_up_date < CURRENT_DATE()
        ORDER BY f.follow_up_date ASC, f.priority DESC
        LIMIT 3
    ");
    $stmtOverdue->execute(['user_id' => $userId]);
    $overdueActions = $stmtOverdue->fetchAll();

    // 7. High-priority actions list
    $stmtHigh = $pdo->prepare("
        SELECT f.id, c.id as contact_id, c.full_name, c.company, f.follow_up_date, f.priority 
        FROM follow_ups f
        JOIN contacts c ON f.contact_id = c.id
        WHERE f.user_id = :user_id AND f.status = 'Pending' AND f.priority = 'High'
        ORDER BY f.follow_up_date ASC
        LIMIT 3
    ");
    $stmtHigh->execute(['user_id' => $userId]);
    $highPriorityActions = $stmtHigh->fetchAll();

    // 8. Inactive relationships (>14 days since last contact event)
    $stmtInactive = $pdo->prepare("
        SELECT id, full_name, company, date_met,
               (SELECT MAX(created_at) FROM interactions WHERE contact_id = contacts.id) as last_activity
        FROM contacts 
        WHERE user_id = :user_id 
          AND (
              (SELECT MAX(created_at) FROM interactions WHERE contact_id = contacts.id) < DATE_SUB(CURRENT_DATE(), INTERVAL 14 DAY)
              OR 
              ((SELECT MAX(created_at) FROM interactions WHERE contact_id = contacts.id) IS NULL AND (date_met IS NULL OR date_met < DATE_SUB(CURRENT_DATE(), INTERVAL 14 DAY)))
          )
        ORDER BY last_activity ASC, id ASC
        LIMIT 3
    ");
    $stmtInactive->execute(['user_id' => $userId]);
    $inactiveContacts = $stmtInactive->fetchAll();

} catch (\PDOException $e) {
    error_log("Dashboard DB Error: " . $e->getMessage());
    $dbError = true;
}
?>

<div class="page-header">
    <div class="page-title">
        <h1>Welcome, <?php echo e($_SESSION['user_name']); ?></h1>
        <p>Manage your business contacts and professional follow-ups</p>
    </div>
    <div class="header-actions">
        <a href="scan.php" class="btn btn-primary">📸 Scan Business Card</a>
        <a href="add-contact.php" class="btn btn-secondary">➕ Add Contact</a>
    </div>
</div>

<?php if (isset($dbError)): ?>
    <div class="alert alert-danger">
        Unable to load live dashboard statistics. Please ensure database connection is configured correctly.
    </div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stats-card primary">
        <div class="stats-info">
            <h3>Total Contacts</h3>
            <div class="stats-number"><?php echo $totalContacts; ?></div>
        </div>
        <div class="stats-icon">👥</div>
    </div>
    
    <div class="stats-card success">
        <div class="stats-info">
            <h3>New This Month</h3>
            <div class="stats-number"><?php echo $contactsThisMonth; ?></div>
        </div>
        <div class="stats-icon">✨</div>
    </div>
    
    <div class="stats-card warning">
        <div class="stats-info">
            <h3>Follow-ups Due</h3>
            <div class="stats-number"><?php echo $followupsDue; ?></div>
        </div>
        <div class="stats-icon">⏰</div>
    </div>
</div>

<!-- TODAY'S ACTIONS & START MY DAY -->
<div class="card" style="margin-bottom: 1.5rem; background: var(--surface-color); border: 1px solid var(--border-color); border-radius: var(--radius-md); box-shadow: var(--shadow-sm);">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding: 1rem 1.25rem;">
        <h2 class="card-title" style="font-size: 1.2rem; display: flex; align-items: center; gap: 0.5rem; margin: 0;">⚡ Today's Priority Actions</h2>
        <a href="follow-ups.php?filter=today" class="btn btn-primary" style="font-size: 0.85rem; padding: 0.45rem 1rem; display: flex; align-items: center; gap: 0.25rem; background-color: var(--primary-color);">🚀 Start My Day</a>
    </div>
    <div class="card-body" style="padding: 1.25rem;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem;">
            <!-- Overdue follow-ups list -->
            <div>
                <h4 style="font-size: 0.95rem; margin-bottom: 0.75rem; color: var(--danger-color); display: flex; align-items: center; gap: 0.25rem; font-weight: 600;">⚠️ Overdue Follow-ups</h4>
                <?php if (empty($overdueActions)): ?>
                    <p style="font-size: 0.85rem; color: var(--text-muted); font-style: italic;">No overdue follow-ups. Great job!</p>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <?php foreach ($overdueActions as $action): ?>
                            <div style="font-size: 0.85rem; display: flex; justify-content: space-between; align-items: center; padding: 0.5rem; background: var(--primary-light); border-radius: var(--radius-sm);">
                                <span><a href="contact.php?id=<?php echo $action['contact_id']; ?>" style="text-decoration: none; color: var(--secondary-color); font-weight: 600;"><?php echo e($action['full_name']); ?></a> (<?php echo format_date_user($action['follow_up_date']); ?>)</span>
                                <span class="badge badge-<?php echo strtolower($action['priority']); ?>"><?php echo $action['priority']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- High priority follow-ups -->
            <div>
                <h4 style="font-size: 0.95rem; margin-bottom: 0.75rem; color: var(--warning-color); display: flex; align-items: center; gap: 0.25rem; font-weight: 600;">🔴 High-Priority Contacts</h4>
                <?php if (empty($highPriorityActions)): ?>
                    <p style="font-size: 0.85rem; color: var(--text-muted); font-style: italic;">No high-priority tasks pending.</p>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <?php foreach ($highPriorityActions as $action): ?>
                            <div style="font-size: 0.85rem; display: flex; justify-content: space-between; align-items: center; padding: 0.5rem; background: var(--primary-light); border-radius: var(--radius-sm);">
                                <span><a href="contact.php?id=<?php echo $action['contact_id']; ?>" style="text-decoration: none; color: var(--secondary-color); font-weight: 600;"><?php echo e($action['full_name']); ?></a> (Due: <?php echo format_date_user($action['follow_up_date']); ?>)</span>
                                <span class="badge badge-danger">High</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Inactive contacts list -->
            <div>
                <h4 style="font-size: 0.95rem; margin-bottom: 0.75rem; color: var(--secondary-color); display: flex; align-items: center; gap: 0.25rem; font-weight: 600;">💤 Slipping Connections</h4>
                <?php if (empty($inactiveContacts)): ?>
                    <p style="font-size: 0.85rem; color: var(--text-muted); font-style: italic;">All connections have active touchpoints!</p>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <?php foreach ($inactiveContacts as $c): 
                            $lastAct = $c['last_activity'] ? 'Last contact: ' . date('d M', strtotime($c['last_activity'])) : 'No recorded activity';
                        ?>
                            <div style="font-size: 0.85rem; display: flex; justify-content: space-between; align-items: center; padding: 0.5rem; background: var(--primary-light); border-radius: var(--radius-sm);">
                                <span><a href="contact.php?id=<?php echo $c['id']; ?>" style="text-decoration: none; color: var(--secondary-color); font-weight: 600;"><?php echo e($c['full_name']); ?></a></span>
                                <span style="font-size: 0.75rem; color: var(--text-muted); font-style: italic;"><?php echo $lastAct; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Dashboard Grid (2 columns: main and side) -->
<div class="dashboard-grid">
    <!-- Left Column: Recent Contacts -->
    <div class="dashboard-main">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Recently Added Contacts</h2>
                <a href="contacts.php" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.35rem 0.75rem;">View All</a>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($recentContacts)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📇</div>
                        <p>No contacts found. Get started by scanning a card!</p>
                        <a href="scan.php" class="btn btn-primary" style="margin-top: 1rem;">Scan Business Card</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 5%; text-align: center;">Sr. No.</th>
                                    <th>Person Name</th>
                                    <th>Organization</th>
                                    <th>Designation</th>
                                    <th>Mobile</th>
                                    <th>Email</th>
                                    <th>WhatsApp</th>
                                    <th>Date Added</th>
                                    <th style="text-align: center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $srNo = 1;
                                foreach ($recentContacts as $contact): 
                                    $initials = '';
                                    if (!empty($contact['first_name'])) $initials .= substr($contact['first_name'], 0, 1);
                                    if (!empty($contact['last_name'])) $initials .= substr($contact['last_name'], 0, 1);
                                    if (empty($initials)) $initials = substr($contact['full_name'] ?? 'C', 0, 1);
                                    $initials = strtoupper($initials);
                                    $displayName = !empty($contact['full_name']) ? $contact['full_name'] : ($contact['first_name'] . ' ' . $contact['last_name']);
                                    $whatsAppUrl = get_whatsapp_url($contact['phone']);
                                    $dateAdded = !empty($contact['created_at']) ? date('d M', strtotime($contact['created_at'])) : '-';
                                ?>
                                    <tr>
                                        <td style="text-align: center; font-weight: 600; color: var(--text-muted);"><?php echo $srNo++; ?></td>
                                        <td>
                                            <div class="contact-cell">
                                                <div class="contact-avatar"><?php echo e($initials); ?></div>
                                                <div class="contact-info-cell">
                                                    <a href="contact.php?id=<?php echo $contact['id']; ?>" class="contact-name" style="font-weight: 600;"><?php echo e($displayName); ?></a>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo e($contact['company'] ?: '-'); ?></td>
                                        <td><?php echo e($contact['job_title'] ?: '-'); ?></td>
                                        <td>
                                            <?php if ($contact['phone']): ?>
                                                <a href="tel:<?php echo e($contact['phone']); ?>" style="color: var(--primary-color); text-decoration: none; font-weight: 500;">📞 <?php echo e($contact['phone']); ?></a>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted); font-style: italic;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($contact['email']): ?>
                                                <a href="mailto:<?php echo e($contact['email']); ?>" title="<?php echo e($contact['email']); ?>" style="text-decoration: none; font-size: 1.1rem;">✉️</a>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted); font-style: italic;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($whatsAppUrl): ?>
                                                <a href="<?php echo $whatsAppUrl; ?>" target="_blank" class="badge badge-success" style="background-color: #25d366; color: white; padding: 0.25rem 0.5rem; text-decoration: none; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: bold;">WhatsApp</a>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted); font-style: italic;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="color: var(--text-muted); font-size: 0.85rem;"><?php echo e($dateAdded); ?></td>
                                        <td style="text-align: center;">
                                            <a href="contact.php?id=<?php echo $contact['id']; ?>" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Upcoming Follow-ups -->
    <div class="dashboard-side">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Upcoming Follow-ups</h2>
            </div>
            <div class="card-body">
                <?php if (empty($upcomingFollowups)): ?>
                    <div class="empty-state" style="padding: 1.5rem 1rem;">
                        <div class="empty-icon">📅</div>
                        <p style="font-size: 0.9rem;">No upcoming follow-ups scheduled.</p>
                    </div>
                <?php else: ?>
                    <div class="followup-list">
                        <?php foreach ($upcomingFollowups as $followup): ?>
                            <div class="followup-item">
                                <div class="followup-details">
                                    <a href="contact.php?id=<?php echo $followup['id']; ?>" class="followup-name"><?php echo e($followup['full_name']); ?></a>
                                    <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo e($followup['company'] ?: 'No Company'); ?></div>
                                </div>
                                <div class="followup-date">
                                    <span>📅</span> <?php echo format_date_user($followup['follow_up_date']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/includes/footer.php';
?>
