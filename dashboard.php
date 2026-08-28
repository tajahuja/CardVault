<?php
/**
 * User CRM Dashboard Page
 */

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
$pdo = require __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';

$userId = $_SESSION['user_id'];

// Initialize statistics
$totalContacts = 0;
$totalCompanies = 0;
$openOpps = 0;
$todayFollowups = 0;
$overdueFollowups = 0;
$weeklyInteractions = 0;

$todaysActionsList = [];
$recentContacts = [];
$upcomingFollowups = [];
$recentEvents = [];
$recentActivityTimeline = [];

// Pipeline Snapshot metrics
$pipelineValue = 0;
$pipelineWeighted = 0;
$wonThisMonth = 0;
$lostThisMonth = 0;

try {
    // 1. CRM Metrics
    $totalContacts = $pdo->query("SELECT COUNT(*) FROM contacts WHERE user_id = $userId")->fetchColumn();
    $totalCompanies = $pdo->query("SELECT COUNT(*) FROM companies WHERE user_id = $userId")->fetchColumn();
    $openOpps = $pdo->query("SELECT COUNT(*) FROM opportunities WHERE user_id = $userId AND stage NOT IN ('Won', 'Lost')")->fetchColumn();
    
    // Today's Follow-ups
    $todayFollowups = $pdo->query("SELECT COUNT(*) FROM follow_ups WHERE user_id = $userId AND follow_up_date = CURRENT_DATE() AND status = 'Pending'")->fetchColumn();
    
    // Overdue Follow-ups
    $overdueFollowups = $pdo->query("SELECT COUNT(*) FROM follow_ups WHERE user_id = $userId AND follow_up_date < CURRENT_DATE() AND status = 'Pending'")->fetchColumn();
    
    // Weekly Interactions
    $weeklyInteractions = $pdo->query("SELECT COUNT(*) FROM interactions WHERE user_id = $userId AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

    // 2. Today's Actions (Start My Day Experience!)
    $stmtT = $pdo->prepare("
        SELECT f.*, c.full_name as contact_name, c.company as contact_company, c.phone as contact_phone, c.email as contact_email 
        FROM follow_ups f 
        JOIN contacts c ON f.contact_id = c.id 
        WHERE f.user_id = :user_id AND f.follow_up_date = CURRENT_DATE() AND f.status = 'Pending' 
        ORDER BY f.priority DESC, f.id ASC
    ");
    $stmtT->execute(['user_id' => $userId]);
    $todaysActionsList = $stmtT->fetchAll();

    // 3. Recently Added Contacts (last 5)
    $stmtC = $pdo->prepare("
        SELECT id, full_name, company, job_title, created_at, phone, email 
        FROM contacts 
        WHERE user_id = :user_id 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmtC->execute(['user_id' => $userId]);
    $recentContacts = $stmtC->fetchAll();

    // 4. Upcoming Follow-ups (next 5, after today)
    $stmtUp = $pdo->prepare("
        SELECT f.*, c.full_name as contact_name, c.company as contact_company 
        FROM follow_ups f 
        JOIN contacts c ON f.contact_id = c.id 
        WHERE f.user_id = :user_id AND f.follow_up_date > CURRENT_DATE() AND f.status = 'Pending' 
        ORDER BY f.follow_up_date ASC 
        LIMIT 5
    ");
    $stmtUp->execute(['user_id' => $userId]);
    $upcomingFollowups = $stmtUp->fetchAll();

    // 5. Recent Events (last 3)
    $stmtEv = $pdo->prepare("
        SELECT id, name, type, date, location 
        FROM events 
        WHERE user_id = :user_id 
        ORDER BY date DESC 
        LIMIT 3
    ");
    $stmtEv->execute(['user_id' => $userId]);
    $recentEvents = $stmtEv->fetchAll();

    // 6. Recent Activity Timeline (last 5 interactions)
    $stmtAct = $pdo->prepare("
        SELECT i.*, c.full_name as contact_name 
        FROM interactions i 
        JOIN contacts c ON i.contact_id = c.id 
        WHERE i.user_id = :user_id 
        ORDER BY i.created_at DESC 
        LIMIT 5
    ");
    $stmtAct->execute(['user_id' => $userId]);
    $recentActivityTimeline = $stmtAct->fetchAll();

    // 7. Pipeline Snapshot Value Calculations
    $stmtOppVal = $pdo->prepare("SELECT value, probability, stage, updated_at FROM opportunities WHERE user_id = :user_id");
    $stmtOppVal->execute(['user_id' => $userId]);
    $allOpps = $stmtOppVal->fetchAll();
    
    $startOfMonth = date('Y-m-01');
    foreach ($allOpps as $opp) {
        $val = floatval($opp['value']);
        $prob = intval($opp['probability']);
        $stage = $opp['stage'];
        
        if ($stage !== 'Won' && $stage !== 'Lost') {
            $pipelineValue += $val;
            $pipelineWeighted += ($val * ($prob / 100));
        } elseif ($stage === 'Won') {
            if (date('Y-m-d', strtotime($opp['updated_at'])) >= $startOfMonth) {
                $wonThisMonth += $val;
            }
        } elseif ($stage === 'Lost') {
            if (date('Y-m-d', strtotime($opp['updated_at'])) >= $startOfMonth) {
                $lostThisMonth += $val;
            }
        }
    }

} catch (\PDOException $e) {
    error_log("Dashboard Load Error: " . $e->getMessage());
    $dbError = "Failed to load dashboard metrics.";
}
?>

<div class="page-header" style="margin-bottom: 2rem;">
    <div class="page-title">
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></h1>
        <p>Relationship Management Platform & Command Center</p>
    </div>
</div>

<?php if (isset($dbError)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($dbError); ?></div>
<?php endif; ?>

<!-- Quick Actions Panel -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-header"><h3 class="card-title">🚀 Quick Actions</h3></div>
    <div class="card-body" style="display: flex; gap: 1rem; flex-wrap: wrap; justify-content: start; padding: 1rem 1.25rem;">
        <a href="scan.php" class="btn btn-primary">📸 Scan Card</a>
        <a href="add-contact.php" class="btn btn-secondary">👤 Add Contact</a>
        <a href="companies.php" class="btn btn-secondary">🏢 Add Company</a>
        <a href="pipeline.php" class="btn btn-secondary">📈 New Opportunity</a>
        <a href="follow-ups.php" class="btn btn-secondary">⏰ Schedule Follow-up</a>
        <a href="events.php" class="btn btn-secondary">📅 Register Event</a>
    </div>
</div>

<!-- Stats Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
    <div class="card" style="border-left: 4px solid var(--primary-color);">
        <div class="card-body" style="padding: 1.25rem;">
            <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Contacts</div>
            <div style="font-size: 1.75rem; font-weight: 700; margin-top: 0.25rem; color: var(--secondary-color);"><?php echo $totalContacts; ?></div>
        </div>
    </div>
    <div class="card" style="border-left: 4px solid #10b981;">
        <div class="card-body" style="padding: 1.25rem;">
            <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">B2B Companies</div>
            <div style="font-size: 1.75rem; font-weight: 700; margin-top: 0.25rem; color: var(--secondary-color);"><?php echo $totalCompanies; ?></div>
        </div>
    </div>
    <div class="card" style="border-left: 4px solid #3b82f6;">
        <div class="card-body" style="padding: 1.25rem;">
            <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Open Deals</div>
            <div style="font-size: 1.75rem; font-weight: 700; margin-top: 0.25rem; color: var(--secondary-color);"><?php echo $openOpps; ?></div>
        </div>
    </div>
    <div class="card" style="border-left: 4px solid #f59e0b;">
        <div class="card-body" style="padding: 1.25rem;">
            <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Today's Tasks</div>
            <div style="font-size: 1.75rem; font-weight: 700; margin-top: 0.25rem; color: var(--secondary-color);"><?php echo $todayFollowups; ?></div>
        </div>
    </div>
    <div class="card" style="border-left: 4px solid #ef4444;">
        <div class="card-body" style="padding: 1.25rem;">
            <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Overdue Tasks</div>
            <div style="font-size: 1.75rem; font-weight: 700; margin-top: 0.25rem; color: var(--secondary-color);"><?php echo $overdueFollowups; ?></div>
        </div>
    </div>
    <div class="card" style="border-left: 4px solid #8b5cf6;">
        <div class="card-body" style="padding: 1.25rem;">
            <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Interactions 7d</div>
            <div style="font-size: 1.75rem; font-weight: 700; margin-top: 0.25rem; color: var(--secondary-color);"><?php echo $weeklyInteractions; ?></div>
        </div>
    </div>
</div>

<!-- TODAY'S ACTIONS / START MY DAY EXPERIENCE -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding: 1rem 1.25rem;">
        <h2 class="card-title" style="margin: 0; font-size: 1.15rem;">⚡ Today's Follow-up Actions ("Start My Day")</h2>
        <a href="follow-ups.php" class="btn btn-primary btn-sm">⏰ Go to Follow-up Planner</a>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($todaysActionsList)): ?>
            <p style="padding: 2.5rem; text-align: center; color: var(--text-muted); font-style: italic;">No follow-ups due today. You are all caught up!</p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="text-align: left; background-color: var(--background-color); border-bottom: 1px solid var(--border-color);">
                            <th style="padding: 0.75rem 1rem;">Contact</th>
                            <th style="padding: 0.75rem 1rem;">Company</th>
                            <th style="padding: 0.75rem 1rem;">Priority</th>
                            <th style="padding: 0.75rem 1rem;">Follow-up Notes</th>
                            <th style="padding: 0.75rem 1rem; text-align: right;">Action Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($todaysActionsList as $action): 
                            $whatsAppUrl = get_whatsapp_url($action['contact_phone']);
                            ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 0.75rem 1rem; font-weight: 600;">
                                    <a href="contact.php?id=<?php echo $action['contact_id']; ?>" class="text-primary" style="text-decoration: none;">
                                        👤 <?php echo htmlspecialchars($action['contact_name']); ?>
                                    </a>
                                </td>
                                <td style="padding: 0.75rem 1rem;">
                                    <?php echo htmlspecialchars($action['contact_company'] ?? 'N/A'); ?>
                                </td>
                                <td style="padding: 0.75rem 1rem;">
                                    <span class="badge" style="background-color: <?php 
                                        if ($action['priority'] === 'High') echo '#fee2e2; color: #991b1b;';
                                        elseif ($action['priority'] === 'Medium') echo '#fef3c7; color: #92400e;';
                                        else echo '#f3f4f6; color: #374151;';
                                    ?> font-weight: 600; font-size: 0.75rem; padding: 0.15rem 0.4rem; border-radius: 4px;">
                                        <?php echo $action['priority']; ?>
                                    </span>
                                </td>
                                <td style="padding: 0.75rem 1rem; font-size: 0.85rem; color: var(--text-muted);">
                                    <?php echo htmlspecialchars($action['notes'] ?? 'N/A'); ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; text-align: right; display: flex; gap: 0.5rem; justify-content: flex-end; align-items: center;">
                                    <?php if ($action['contact_phone']): ?>
                                        <a href="tel:<?php echo htmlspecialchars($action['contact_phone']); ?>" class="btn btn-secondary btn-xs" style="padding: 0.25rem 0.4rem;" title="Call">📞 Call</a>
                                        <?php if ($whatsAppUrl): ?>
                                            <a href="<?php echo $whatsAppUrl; ?>" target="_blank" class="btn btn-secondary btn-xs" style="padding: 0.25rem 0.4rem; background-color: #25d366; color: white; border-color: #25d366;" title="WhatsApp">💬 WA</a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if ($action['contact_email']): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($action['contact_email']); ?>" class="btn btn-secondary btn-xs" style="padding: 0.25rem 0.4rem;" title="Email">✉️ Mail</a>
                                    <?php endif; ?>
                                    
                                    <button type="button" class="btn btn-primary btn-xs quick-complete-btn" style="padding: 0.25rem 0.4rem; background-color: #059669; border-color: #059669;" data-id="<?php echo $action['id']; ?>">✓ Complete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Main Split Layout -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; align-items: start;">
    
    <!-- LEFT COLUMN: Recent Contacts & Pipeline Snapshot -->
    <div>
        <!-- Pipeline Snapshot -->
        <div class="card" style="margin-bottom: 2rem;">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h3 class="card-title">📈 Sales Pipeline Snapshot</h3>
                <a href="pipeline.php" class="btn btn-secondary btn-sm">Full Pipeline Board</a>
            </div>
            <div class="card-body" style="padding: 1.25rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem; text-align: center; margin-bottom: 1rem;">
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 500;">Active Pipeline Value</div>
                        <div style="font-size: 1.3rem; font-weight: 700; color: var(--secondary-color); margin-top: 0.25rem;">₹<?php echo number_format($pipelineValue, 2); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 500;">Weighted Open Value</div>
                        <div style="font-size: 1.3rem; font-weight: 700; color: #10b981; margin-top: 0.25rem;">₹<?php echo number_format($pipelineWeighted, 2); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 500;">Closed Won (This Month)</div>
                        <div style="font-size: 1.3rem; font-weight: 700; color: #059669; margin-top: 0.25rem;">₹<?php echo number_format($wonThisMonth, 2); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Contacts -->
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h3 class="card-title">👤 Recently Scanned Contacts</h3>
                <a href="contacts.php" class="btn btn-secondary btn-sm">View All Contacts</a>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($recentContacts)): ?>
                    <p style="padding: 2rem; text-align: center; color: var(--text-muted);">No contacts found. Use the scanner to add contacts!</p>
                <?php else: ?>
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        <?php foreach ($recentContacts as $c): ?>
                            <li style="padding: 1rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <a href="contact.php?id=<?php echo $c['id']; ?>" class="text-primary" style="text-decoration: none; font-weight: 600;">
                                        <?php echo htmlspecialchars($c['full_name']); ?>
                                    </a>
                                    <span style="font-size: 0.85rem; color: var(--text-muted); display: block;">
                                        <?php echo htmlspecialchars($c['job_title'] ?? 'No designation'); ?> <?php echo !empty($c['company']) ? 'at ' . htmlspecialchars($c['company']) : ''; ?>
                                    </span>
                                </div>
                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <?php if ($c['phone']): ?>
                                        <a href="tel:<?php echo htmlspecialchars($c['phone']); ?>" class="btn btn-secondary btn-xs" style="padding: 0.2rem 0.35rem;">📞</a>
                                    <?php endif; ?>
                                    <a href="contact.php?id=<?php echo $c['id']; ?>" class="btn btn-secondary btn-xs" style="padding: 0.2rem 0.4rem;">👁️ View</a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- RIGHT COLUMN: Upcoming Follow-ups, Recent Events, and Recent Activity -->
    <div>
        <!-- Upcoming follow-ups -->
        <div class="card" style="margin-bottom: 2rem;">
            <div class="card-header"><h3 class="card-title">📅 Upcoming Follow-ups</h3></div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($upcomingFollowups)): ?>
                    <p style="padding: 1.5rem; text-align: center; color: var(--text-muted);">No upcoming schedules.</p>
                <?php else: ?>
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        <?php foreach ($upcomingFollowups as $up): ?>
                            <li style="padding: 0.75rem 1rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; font-size: 0.9rem;">
                                <div>
                                    <a href="contact.php?id=<?php echo $up['contact_id']; ?>" class="text-primary" style="text-decoration: none; font-weight: 500;">
                                        <?php echo htmlspecialchars($up['contact_name']); ?>
                                    </a>
                                </div>
                                <div style="font-size: 0.8rem; color: var(--text-muted);">
                                    📅 <?php echo date('M d', strtotime($up['follow_up_date'])); ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Events -->
        <div class="card" style="margin-bottom: 2rem;">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h3 class="card-title">📅 Recent Events</h3>
                <a href="events.php" class="btn btn-secondary btn-sm" style="font-size: 0.75rem;">All Events</a>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($recentEvents)): ?>
                    <p style="padding: 1.5rem; text-align: center; color: var(--text-muted);">No events registered.</p>
                <?php else: ?>
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        <?php foreach ($recentEvents as $ev): ?>
                            <li style="padding: 0.75rem 1rem; border-bottom: 1px solid var(--border-color); font-size: 0.9rem;">
                                <a href="event.php?id=<?php echo $ev['id']; ?>" class="text-primary" style="text-decoration: none; font-weight: 500; display: block;">
                                    Thailand <?php echo htmlspecialchars($ev['name']); ?>
                                </a>
                                <span style="font-size: 0.8rem; color: var(--text-muted);">
                                    <?php echo date('M d, Y', strtotime($ev['date'])); ?> · <?php echo htmlspecialchars($ev['location'] ?? 'Online'); ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Activity Logs Timeline -->
        <div class="card">
            <div class="card-header"><h3 class="card-title">⏳ Recent Timeline Activity</h3></div>
            <div class="card-body" style="padding: 1.25rem 1rem;">
                <?php if (empty($recentActivityTimeline)): ?>
                    <p style="text-align: center; color: var(--text-muted); font-style: italic; font-size: 0.9rem;">No recorded activity timeline logs.</p>
                <?php else: ?>
                    <div style="position: relative; border-left: 2px solid var(--border-color); margin-left: 0.5rem; padding-left: 1.25rem;">
                        <?php foreach ($recentActivityTimeline as $act): ?>
                            <div style="margin-bottom: 1.25rem; position: relative;">
                                <span style="position: absolute; left: -1.75rem; top: 0.15rem; width: 0.8rem; height: 0.8rem; background: var(--surface-color); border: 2px solid var(--primary-color); border-radius: 50%;"></span>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.15rem; font-size: 0.85rem;">
                                    <span style="font-weight: 600; color: var(--secondary-color);"><?php echo htmlspecialchars($act['type']); ?></span>
                                    <span style="color: var(--text-muted); font-size: 0.75rem;"><?php echo date('M d h:i A', strtotime($act['created_at'])); ?></span>
                                </div>
                                <div style="font-size: 0.85rem; color: var(--text-muted);">
                                    <?php echo htmlspecialchars($act['description']); ?>
                                </div>
                                <div style="font-size: 0.75rem; margin-top: 0.15rem;">
                                    Contact: <a href="contact.php?id=<?php echo $act['contact_id']; ?>" class="text-primary" style="text-decoration: none;"><?php echo htmlspecialchars($act['contact_name']); ?></a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- CSRF Field for Complete AJAX action -->
<input type="hidden" id="dashboard-csrf" value="<?php echo get_csrf_token(); ?>">

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Start My Day Touchpoint - Quick Complete Action
    document.querySelectorAll('.quick-complete-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const fuId = btn.getAttribute('data-id');
            const csrfToken = document.getElementById('dashboard-csrf').value;
            
            const formData = new FormData();
            formData.append('action', 'complete');
            formData.append('id', fuId);
            formData.append('completion_notes', 'Follow-up marked complete from Dashboard Start My Day panel.');
            formData.append('csrf_token', csrfToken);
            
            btn.disabled = true;
            btn.textContent = 'Completing...';
            
            fetch('api/followup.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken
                }
            })
            .then(res => res.json().then(data => {
                if (!res.ok) throw new Error(data.message || 'Operation failed.');
                return data;
            }))
            .then(data => {
                alert('Follow-up marked completed successfully!');
                window.location.reload();
            })
            .catch(err => {
                alert(err.message);
                btn.disabled = false;
                btn.textContent = '✓ Complete';
            });
        });
    });
});
</script>

<?php 
require_once __DIR__ . '/includes/footer.php';
?>
