<?php
/**
 * B2B Company Detail Profile
 */

$pageTitle = 'Company Profile';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/csrf.php';
$pdo = require __DIR__ . '/includes/db.php';

$userId = $_SESSION['user_id'];
$companyId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($companyId <= 0) {
    echo "<div class='alert alert-danger'>Invalid Company ID.</div>";
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

try {
    // 1. Fetch Company details
    $stmtCompany = $pdo->prepare("SELECT * FROM companies WHERE id = :id AND user_id = :user_id");
    $stmtCompany->execute(['id' => $companyId, 'user_id' => $userId]);
    $company = $stmtCompany->fetch();

    if (!$company) {
        echo "<div class='alert alert-danger'>Company not found or unauthorized access.</div>";
        require_once __DIR__ . '/includes/footer.php';
        exit;
    }

    // 2. Fetch associated contacts
    $stmtContacts = $pdo->prepare("SELECT * FROM contacts WHERE company_id = :company_id AND user_id = :user_id ORDER BY full_name ASC");
    $stmtContacts->execute(['company_id' => $companyId, 'user_id' => $userId]);
    $contacts = $stmtContacts->fetchAll();

    // 3. Fetch all other unassociated user contacts to allow linking them
    $stmtUnassociated = $pdo->prepare("SELECT id, full_name, company FROM contacts WHERE user_id = :user_id AND (company_id != :company_id OR company_id IS NULL) ORDER BY full_name ASC");
    $stmtUnassociated->execute(['user_id' => $userId, 'company_id' => $companyId]);
    $unassociatedContacts = $stmtUnassociated->fetchAll();

    // 4. Fetch opportunities associated with the company
    $stmtOpps = $pdo->prepare("
        SELECT o.*, c.full_name as contact_name 
        FROM opportunities o 
        JOIN contacts c ON o.contact_id = c.id 
        WHERE o.company_id = :company_id AND o.user_id = :user_id 
        ORDER BY o.expected_close_date ASC
    ");
    $stmtOpps->execute(['company_id' => $companyId, 'user_id' => $userId]);
    $opportunities = $stmtOpps->fetchAll();

    // 5. Fetch notes associated with all contacts of this company
    $notes = [];
    if (!empty($contacts)) {
        $contactIds = array_map(function($c) { return $c['id']; }, $contacts);
        $inQuery = implode(',', array_fill(0, count($contactIds), '?'));
        $stmtNotes = $pdo->prepare("
            SELECT n.*, c.full_name as contact_name 
            FROM notes n 
            JOIN contacts c ON n.contact_id = c.id 
            WHERE n.contact_id IN ($inQuery) AND n.user_id = ? 
            ORDER BY n.created_at DESC
        ");
        $stmtNotes->execute(array_merge($contactIds, [$userId]));
        $notes = $stmtNotes->fetchAll();
    }

    // 6. Fetch chronological interactions timeline for all contacts of this company
    $interactions = [];
    if (!empty($contacts)) {
        $contactIds = array_map(function($c) { return $c['id']; }, $contacts);
        $inQuery = implode(',', array_fill(0, count($contactIds), '?'));
        $stmtInt = $pdo->prepare("
            SELECT i.*, c.full_name as contact_name 
            FROM interactions i 
            JOIN contacts c ON i.contact_id = c.id 
            WHERE i.contact_id IN ($inQuery) AND i.user_id = ? 
            ORDER BY i.created_at DESC
        ");
        $stmtInt->execute(array_merge($contactIds, [$userId]));
        $interactions = $stmtInt->fetchAll();
    }

} catch (\PDOException $e) {
    error_log("Fetch Company Details Error: " . $e->getMessage());
    echo "<div class='alert alert-danger'>A database error occurred.</div>";
    require_once __DIR__ . '/includes/footer.php';
    exit;
}
?>

<div class="page-header" style="margin-bottom: 2rem;">
    <div class="page-title">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <a href="companies.php" style="font-size: 1.5rem; text-decoration: none;">⬅️</a>
            <h1>🏢 <?php echo htmlspecialchars($company['name']); ?></h1>
        </div>
        <p style="margin-left: 2.25rem;">B2B Corporate Account Workspace</p>
    </div>
    <div class="header-actions" style="display: flex; gap: 0.5rem;">
        <button type="button" class="btn btn-secondary" id="edit-company-btn">✏️ Edit Details</button>
        <button type="button" class="btn btn-danger" id="delete-company-btn">🗑️ Delete Company</button>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem; align-items: start;">
    <!-- LEFT SIDEBAR: Company Info & Linked Contacts -->
    <div>
        <!-- Profile info card -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header"><h3 class="card-title">Corporate Info</h3></div>
            <div class="card-body">
                <div style="margin-bottom: 1rem;">
                    <strong class="form-label" style="display: block; color: var(--text-muted);">Industry</strong>
                    <span><?php echo htmlspecialchars($company['industry'] ?? 'N/A'); ?></span>
                </div>
                <div style="margin-bottom: 1rem;">
                    <strong class="form-label" style="display: block; color: var(--text-muted);">Website</strong>
                    <?php if (!empty($company['website'])): ?>
                        <a href="<?php echo htmlspecialchars($company['website']); ?>" target="_blank" class="text-primary" style="text-decoration: none;">
                            <?php echo htmlspecialchars($company['website']); ?> 🌐
                        </a>
                    <?php else: ?>
                        <span>N/A</span>
                    <?php endif; ?>
                </div>
                <div style="margin-bottom: 1rem;">
                    <strong class="form-label" style="display: block; color: var(--text-muted);">Location</strong>
                    <span><?php echo htmlspecialchars($company['location'] ?? 'N/A'); ?></span>
                </div>
                <div style="margin-bottom: 0;">
                    <strong class="form-label" style="display: block; color: var(--text-muted);">Lead Source</strong>
                    <span><?php echo htmlspecialchars($company['lead_source'] ?? 'N/A'); ?></span>
                </div>
            </div>
        </div>

        <!-- Associated Contacts list card -->
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h3 class="card-title">Linked Contacts (<?php echo count($contacts); ?>)</h3>
            </div>
            <div class="card-body" style="padding: 0;">
                <!-- Link Contact select form -->
                <div style="padding: 1rem; border-bottom: 1px solid var(--border-color); background-color: var(--background-color);">
                    <form id="link-contact-form" action="api/companies.php" method="POST" style="display: flex; gap: 0.5rem;">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="assign_contact">
                        <input type="hidden" name="company_id" value="<?php echo $companyId; ?>">
                        <select name="contact_id" class="form-control" style="flex: 1;" required>
                            <option value="">-- Choose Contact to Associate --</option>
                            <?php foreach ($unassociatedContacts as $uc): ?>
                                <option value="<?php echo $uc['id']; ?>">
                                    <?php echo htmlspecialchars($uc['full_name']); ?> <?php echo !empty($uc['company']) ? '(' . htmlspecialchars($uc['company']) . ')' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm">Link</button>
                    </form>
                </div>

                <?php if (empty($contacts)): ?>
                    <p style="padding: 2rem; text-align: center; color: var(--text-muted);">No contacts associated with this company yet.</p>
                <?php else: ?>
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        <?php foreach ($contacts as $c): ?>
                            <li style="padding: 1rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <a href="contact.php?id=<?php echo $c['id']; ?>" class="text-primary" style="text-decoration: none; font-weight: 500; display: block;">
                                        👤 <?php echo htmlspecialchars($c['full_name']); ?>
                                    </a>
                                    <span style="font-size: 0.85rem; color: var(--text-muted);"><?php echo htmlspecialchars($c['job_title'] ?? 'No Title'); ?></span>
                                </div>
                                <button type="button" class="btn btn-secondary btn-xs unlink-contact-btn" data-id="<?php echo $c['id']; ?>" style="padding: 0.15rem 0.4rem; font-size: 0.75rem;">Unlink</button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- RIGHT SIDEBAR: Opportunities & Activity Logs Timeline -->
    <div>
        <!-- Opportunities Board -->
        <div class="card" style="margin-bottom: 2rem;">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h3 class="card-title">Active Opportunities (<?php echo count($opportunities); ?>)</h3>
                <a href="pipeline.php" class="btn btn-secondary btn-sm">📈 Pipeline</a>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($opportunities)): ?>
                    <p style="padding: 2rem; text-align: center; color: var(--text-muted);">No opportunities registered for this company.</p>
                <?php else: ?>
                    <table class="table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="text-align: left; background-color: var(--background-color); border-bottom: 1px solid var(--border-color);">
                                <th style="padding: 0.75rem 1rem;">Deal Name</th>
                                <th style="padding: 0.75rem 1rem;">Contact</th>
                                <th style="padding: 0.75rem 1rem;">Stage</th>
                                <th style="padding: 0.75rem 1rem; text-align: right;">Value</th>
                                <th style="padding: 0.75rem 1rem; text-align: center;">Close Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($opportunities as $opp): ?>
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td style="padding: 0.75rem 1rem; font-weight: 500;">
                                        <?php echo htmlspecialchars($opp['name']); ?>
                                    </td>
                                    <td style="padding: 0.75rem 1rem; font-size: 0.9rem;">
                                        <a href="contact.php?id=<?php echo $opp['contact_id']; ?>" class="text-primary" style="text-decoration: none;">
                                            <?php echo htmlspecialchars($opp['contact_name']); ?>
                                        </a>
                                    </td>
                                    <td style="padding: 0.75rem 1rem; font-size: 0.9rem;">
                                        <span class="badge" style="background-color: #f1f5f9; color: var(--secondary-color); border: 1px solid var(--border-color); font-weight: 500; font-size: 0.8rem; padding: 0.15rem 0.5rem; border-radius: 4px;">
                                            <?php echo htmlspecialchars($opp['stage']); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 0.75rem 1rem; text-align: right; font-weight: 600;">
                                        ₹<?php echo number_format($opp['value'], 2); ?>
                                    </td>
                                    <td style="padding: 0.75rem 1rem; text-align: center; font-size: 0.85rem; color: var(--text-muted);">
                                        <?php echo $opp['expected_close_date'] ? date('M d, Y', strtotime($opp['expected_close_date'])) : 'N/A'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Activity Timeline -->
        <div class="card">
            <div class="card-header"><h3 class="card-title">Activity Timeline</h3></div>
            <div class="card-body" style="padding: 1.5rem 1rem;">
                <?php if (empty($interactions)): ?>
                    <p style="text-align: center; color: var(--text-muted); padding: 1.5rem;">No recorded activity logs for linked contacts.</p>
                <?php else: ?>
                    <div style="position: relative; border-left: 2px solid var(--border-color); margin-left: 1rem; padding-left: 1.5rem;">
                        <?php foreach ($interactions as $int): ?>
                            <div style="margin-bottom: 1.5rem; position: relative;">
                                <!-- Timeline icon node -->
                                <span style="position: absolute; left: -2.05rem; top: 0.1rem; width: 1.1rem; height: 1.1rem; background: var(--surface-color); border: 2.5px solid var(--primary-color); border-radius: 50%;"></span>
                                
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                                    <span style="font-weight: 600; font-size: 0.95rem; color: var(--secondary-color);">
                                        <?php echo htmlspecialchars($int['type']); ?> interaction
                                    </span>
                                    <span style="font-size: 0.85rem; color: var(--text-muted);">
                                        <?php echo date('M d, Y h:i A', strtotime($int['created_at'])); ?>
                                    </span>
                                </div>
                                <div style="font-size: 0.9rem; color: var(--text-muted); line-height: 1.5;">
                                    <?php echo nl2br(htmlspecialchars($int['description'])); ?>
                                </div>
                                <div style="font-size: 0.8rem; margin-top: 0.25rem; font-weight: 500;">
                                    Contact: <a href="contact.php?id=<?php echo $int['contact_id']; ?>" class="text-primary" style="text-decoration: none;">
                                        <?php echo htmlspecialchars($int['contact_name']); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="edit-modal" class="modal-overlay hidden">
    <div class="modal-card">
        <div class="modal-header"><h3>✏️ Edit Company Details</h3></div>
        <form id="edit-form" action="api/companies.php" method="POST">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo $companyId; ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label for="edit-name" class="form-label">Company Name *</label>
                    <input type="text" name="name" id="edit-name" class="form-control" required value="<?php echo htmlspecialchars($company['name']); ?>">
                </div>
                <div class="form-group">
                    <label for="edit-industry" class="form-label">Industry</label>
                    <input type="text" name="industry" id="edit-industry" class="form-control" value="<?php echo htmlspecialchars($company['industry'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="edit-website" class="form-label">Website URL</label>
                    <input type="url" name="website" id="edit-website" class="form-control" value="<?php echo htmlspecialchars($company['website'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="edit-location" class="form-label">Location (City/Country)</label>
                    <input type="text" name="location" id="edit-location" class="form-control" value="<?php echo htmlspecialchars($company['location'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="edit-source" class="form-label">Lead Source</label>
                    <input type="text" name="lead_source" id="edit-source" class="form-control" value="<?php echo htmlspecialchars($company['lead_source'] ?? ''); ?>">
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
    // Edit Modal toggling
    const editModal = document.getElementById('edit-modal');
    const editBtn = document.getElementById('edit-company-btn');
    const closeEditBtn = document.getElementById('close-edit-btn');
    const editForm = document.getElementById('edit-form');
    
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

    // Delete Company Action
    const deleteBtn = document.getElementById('delete-company-btn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', () => {
            if (confirm('Are you absolutely sure you want to delete this company? Contacts linked to this company will not be deleted, but their association will be cleared.')) {
                const csrfToken = document.querySelector('input[name="csrf_token"]').value;
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', '<?php echo $companyId; ?>');
                formData.append('csrf_token', csrfToken);
                
                fetch('api/companies.php', {
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
                    window.location.href = 'companies.php';
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
            if (confirm('Unlink this contact from the company?')) {
                const contactId = btn.getAttribute('data-id');
                const csrfToken = document.querySelector('input[name="csrf_token"]').value;
                
                const formData = new FormData();
                formData.append('action', 'assign_contact');
                formData.append('contact_id', contactId);
                formData.append('company_id', '0'); // 0 dissociates
                formData.append('csrf_token', csrfToken);
                
                fetch('api/companies.php', {
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
