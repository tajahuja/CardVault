<?php
/**
 * Sales Kanban Pipeline Dashboard
 */

$pageTitle = 'Pipeline';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/csrf.php';
$pdo = require __DIR__ . '/includes/db.php';

$userId = $_SESSION['user_id'];

$opportunities = [];
$contacts = [];

// Pipeline metrics
$totalValue = 0;
$weightedValue = 0;
$openOppsCount = 0;
$wonThisMonth = 0;
$lostThisMonth = 0;

try {
    // 1. Fetch opportunities with contact details
    $stmt = $pdo->prepare("
        SELECT o.*, c.full_name as contact_name, c.company as contact_company 
        FROM opportunities o 
        JOIN contacts c ON o.contact_id = c.id 
        WHERE o.user_id = :user_id
        ORDER BY o.expected_close_date ASC
    ");
    $stmt->execute(['user_id' => $userId]);
    $opportunities = $stmt->fetchAll();

    // 2. Fetch all contacts for creation dropdown
    $stmtContacts = $pdo->prepare("SELECT id, full_name, company FROM contacts WHERE user_id = :user_id ORDER BY full_name ASC");
    $stmtContacts->execute(['user_id' => $userId]);
    $contacts = $stmtContacts->fetchAll();

    // 3. Compute stats
    $startOfMonth = date('Y-m-01');
    foreach ($opportunities as $opp) {
        $val = floatval($opp['value']);
        $prob = intval($opp['probability']);
        $stage = $opp['stage'];
        
        $totalValue += $val;
        
        if ($stage !== 'Won' && $stage !== 'Lost') {
            $openOppsCount++;
            $weightedValue += ($val * ($prob / 100));
        } elseif ($stage === 'Won') {
            // Won check
            if (date('Y-m-d', strtotime($opp['updated_at'])) >= $startOfMonth) {
                $wonThisMonth++;
            }
        } elseif ($stage === 'Lost') {
            // Lost check
            if (date('Y-m-d', strtotime($opp['updated_at'])) >= $startOfMonth) {
                $lostThisMonth++;
            }
        }
    }

} catch (\PDOException $e) {
    error_log("Pipeline DB Error: " . $e->getMessage());
    $dbError = "Failed to load opportunities.";
}

// Group opportunities by stage
$stages = ['New Lead', 'Contacted', 'Qualified', 'Proposal', 'Negotiation', 'Won', 'Lost'];
$groupedOpps = [];
foreach ($stages as $st) {
    $groupedOpps[$st] = [];
}
foreach ($opportunities as $opp) {
    $groupedOpps[$opp['stage']][] = $opp;
}
?>

<div class="page-header" style="margin-bottom: 1.5rem;">
    <div class="page-title">
        <h1>Sales Kanban Pipeline</h1>
        <p>Track business opportunities, deals value, and close conversions</p>
    </div>
    <div class="header-actions">
        <button type="button" class="btn btn-primary" id="open-add-opp-btn">📈 New Opportunity</button>
    </div>
</div>

<?php if (isset($dbError)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($dbError); ?></div>
<?php endif; ?>

<!-- Summary Metrics Panel -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
    <div class="card" style="border-left: 4px solid var(--primary-color);">
        <div class="card-body" style="padding: 1.25rem;">
            <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Pipeline Value</div>
            <div style="font-size: 1.75rem; font-weight: 700; margin-top: 0.25rem; color: var(--secondary-color);">₹<?php echo number_format($totalValue, 2); ?></div>
        </div>
    </div>
    <div class="card" style="border-left: 4px solid #10b981;">
        <div class="card-body" style="padding: 1.25rem;">
            <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Weighted Open Value</div>
            <div style="font-size: 1.75rem; font-weight: 700; margin-top: 0.25rem; color: var(--secondary-color);">₹<?php echo number_format($weightedValue, 2); ?></div>
        </div>
    </div>
    <div class="card" style="border-left: 4px solid #3b82f6;">
        <div class="card-body" style="padding: 1.25rem;">
            <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Open Opportunities</div>
            <div style="font-size: 1.75rem; font-weight: 700; margin-top: 0.25rem; color: var(--secondary-color);"><?php echo $openOppsCount; ?></div>
        </div>
    </div>
    <div class="card" style="border-left: 4px solid #059669;">
        <div class="card-body" style="padding: 1.25rem;">
            <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Won This Month</div>
            <div style="font-size: 1.75rem; font-weight: 700; margin-top: 0.25rem; color: var(--secondary-color);"><?php echo $wonThisMonth; ?></div>
        </div>
    </div>
    <div class="card" style="border-left: 4px solid #ef4444;">
        <div class="card-body" style="padding: 1.25rem;">
            <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Lost This Month</div>
            <div style="font-size: 1.75rem; font-weight: 700; margin-top: 0.25rem; color: var(--secondary-color);"><?php echo $lostThisMonth; ?></div>
        </div>
    </div>
</div>

<!-- Kanban Columns container -->
<div class="kanban-container" style="display: flex; gap: 1rem; overflow-x: auto; padding-bottom: 1.5rem; align-items: flex-start; height: 75vh;">
    <?php foreach ($stages as $stage): 
        $stageClass = '';
        if ($stage === 'Won') $stageClass = 'stage-won';
        elseif ($stage === 'Lost') $stageClass = 'stage-lost';
        ?>
        <div class="kanban-column" data-stage="<?php echo htmlspecialchars($stage); ?>" style="flex: 0 0 280px; background-color: #f1f5f9; border-radius: var(--radius-md); max-height: 100%; display: flex; flex-direction: column; border: 1px solid var(--border-color);">
            <div class="kanban-column-header" style="padding: 1rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--border-color); background-color: var(--surface-color); border-top-left-radius: var(--radius-md); border-top-right-radius: var(--radius-md);">
                <h3 style="font-size: 0.95rem; font-weight: 700; color: var(--secondary-color); margin: 0;"><?php echo htmlspecialchars($stage); ?></h3>
                <span class="badge" style="background-color: var(--border-color); color: var(--text-color); font-size: 0.75rem; padding: 0.15rem 0.4rem; border-radius: 10px; font-weight: 600;">
                    <?php echo count($groupedOpps[$stage]); ?>
                </span>
            </div>
            
            <div class="kanban-cards-wrapper" style="padding: 0.75rem; overflow-y: auto; flex: 1; min-height: 150px;" ondragover="event.preventDefault();" ondrop="dropOpportunity(event, '<?php echo htmlspecialchars($stage); ?>')">
                <?php if (empty($groupedOpps[$stage])): ?>
                    <div style="padding: 2rem; text-align: center; color: var(--text-muted); font-size: 0.8rem; border: 2px dashed rgba(0,0,0,0.05); border-radius: 4px;">
                        No deals here
                    </div>
                <?php else: ?>
                    <?php foreach ($groupedOpps[$stage] as $opp): ?>
                        <div class="kanban-card" draggable="true" ondragstart="dragOpportunity(event, '<?php echo $opp['id']; ?>')" style="background-color: var(--surface-color); border-radius: var(--radius-sm); border: 1px solid var(--border-color); padding: 0.875rem; margin-bottom: 0.75rem; box-shadow: var(--shadow-sm); cursor: grab;"
                             data-id="<?php echo $opp['id']; ?>"
                             data-name="<?php echo htmlspecialchars($opp['name'], ENT_QUOTES); ?>"
                             data-contact-id="<?php echo $opp['contact_id']; ?>"
                             data-value="<?php echo $opp['value']; ?>"
                             data-probability="<?php echo $opp['probability']; ?>"
                             data-close-date="<?php echo htmlspecialchars($opp['expected_close_date'] ?? ''); ?>"
                             data-stage="<?php echo htmlspecialchars($opp['stage']); ?>">
                            <div style="font-weight: 600; font-size: 0.9rem; color: var(--secondary-color); margin-bottom: 0.25rem; word-break: break-word;">
                                <?php echo htmlspecialchars($opp['name']); ?>
                            </div>
                            
                            <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.5rem;">
                                👤 <?php echo htmlspecialchars($opp['contact_name']); ?>
                                <?php if (!empty($opp['contact_company'])): ?>
                                    <span style="display: block; font-size: 0.75rem; font-style: italic;">(<?php echo htmlspecialchars($opp['contact_company']); ?>)</span>
                                <?php endif; ?>
                            </div>
                            
                            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); padding-top: 0.5rem; margin-top: 0.5rem;">
                                <span style="font-weight: 700; font-size: 0.85rem; color: var(--primary-color);">
                                    ₹<?php echo number_format($opp['value'], 0); ?>
                                </span>
                                <span style="font-size: 0.75rem; background-color: rgba(79, 70, 229, 0.08); color: var(--primary-color); padding: 0.1rem 0.35rem; border-radius: 4px; font-weight: 600;">
                                    <?php echo intval($opp['probability']); ?>%
                                </span>
                            </div>
                            
                            <!-- Quick actions / details button -->
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem; font-size: 0.75rem;">
                                <span style="color: var(--text-muted);">
                                    📅 <?php echo $opp['expected_close_date'] ? date('M d', strtotime($opp['expected_close_date'])) : 'No date'; ?>
                                </span>
                                <div style="display: flex; gap: 0.2rem;">
                                    <button type="button" class="btn btn-secondary btn-xs edit-opp-trigger" style="padding: 0.1rem 0.3rem;" onclick="openEditOppModal(this.parentNode.parentNode.parentNode)">✏️</button>
                                    
                                    <!-- Keyboard accessible move triggers -->
                                    <select class="quick-move-select" style="font-size: 0.7rem; border-radius: 3px; border: 1px solid var(--border-color); background: var(--surface-color); padding: 0;" onchange="quickMoveOpportunity(<?php echo $opp['id']; ?>, this.value)">
                                        <option value="">Move...</option>
                                        <?php foreach ($stages as $stOption): ?>
                                            <option value="<?php echo htmlspecialchars($stOption); ?>" <?php echo $stOption === $stage ? 'disabled' : ''; ?>>
                                                <?php echo htmlspecialchars($stOption); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Add / Edit Opportunity Modal -->
<div id="opp-modal" class="modal-overlay hidden">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="opp-modal-title">📈 New Sales Opportunity</h3>
        </div>
        <form id="opp-form" action="api/pipeline.php" method="POST">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" id="opp-form-action" value="create">
            <input type="hidden" name="id" id="opp-id" value="">
            
            <div class="modal-body">
                <div class="form-group">
                    <label for="opp-name" class="form-label">Deal / Opportunity Name *</label>
                    <input type="text" name="name" id="opp-name" class="form-control" required placeholder="e.g. Acme Enterprise Subscription License">
                </div>
                
                <div class="form-group" id="opp-contact-group">
                    <label for="opp-contact-id" class="form-label">Primary Contact *</label>
                    <select name="contact_id" id="opp-contact-id" class="form-control" required>
                        <option value="">-- Select Contact --</option>
                        <?php foreach ($contacts as $c): ?>
                            <option value="<?php echo $c['id']; ?>">
                                <?php echo htmlspecialchars($c['full_name']); ?> <?php echo !empty($c['company']) ? '(' . htmlspecialchars($c['company']) . ')' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="opp-stage" class="form-label">Pipeline Stage</label>
                    <select name="stage" id="opp-stage" class="form-control">
                        <?php foreach ($stages as $st): ?>
                            <option value="<?php echo htmlspecialchars($st); ?>"><?php echo htmlspecialchars($st); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="opp-value" class="form-label">Deal Value (₹)</label>
                        <input type="number" name="value" id="opp-value" class="form-control" step="0.01" min="0" value="0.00">
                    </div>
                    <div class="form-group">
                        <label for="opp-probability" class="form-label">Probability (%)</label>
                        <input type="number" name="probability" id="opp-probability" class="form-control" min="0" max="100" value="10">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="opp-close-date" class="form-label">Expected Close Date</label>
                    <input type="date" name="expected_close_date" id="opp-close-date" class="form-control">
                </div>
            </div>
            
            <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                <button type="button" class="btn btn-danger btn-sm hidden" id="delete-opp-btn">Delete Deal</button>
                <div style="display: flex; gap: 0.5rem; margin-left: auto;">
                    <button type="button" class="btn btn-secondary" id="close-opp-modal-btn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="save-opp-btn">Save Deal</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Drag and Drop Logic
function dragOpportunity(event, id) {
    event.dataTransfer.setData("text/plain", id);
    event.dataTransfer.effectAllowed = "move";
}

function dropOpportunity(event, targetStage) {
    event.preventDefault();
    const id = event.dataTransfer.getData("text/plain");
    if (id) {
        moveOpportunityAPI(id, targetStage);
    }
}

function quickMoveOpportunity(id, targetStage) {
    if (id && targetStage) {
        moveOpportunityAPI(id, targetStage);
    }
}

function moveOpportunityAPI(id, targetStage) {
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    const formData = new FormData();
    formData.append('action', 'move');
    formData.append('id', id);
    formData.append('stage', targetStage);
    formData.append('csrf_token', csrfToken);
    
    fetch('api/pipeline.php', {
        method: 'POST',
        body: formData,
        headers: {
            'Accept': 'application/json',
            'X-CSRF-Token': csrfToken
        }
    })
    .then(res => res.json().then(data => {
        if (!res.ok) throw new Error(data.message || 'Moving failed.');
        return data;
    }))
    .then(data => {
        window.location.reload();
    })
    .catch(err => {
        alert(err.message);
    });
}

function openEditOppModal(cardElement) {
    const modal = document.getElementById('opp-modal');
    const form = document.getElementById('opp-form');
    const title = document.getElementById('opp-modal-title');
    const contactGroup = document.getElementById('opp-contact-group');
    const deleteBtn = document.getElementById('delete-opp-btn');
    
    document.getElementById('opp-form-action').value = 'update';
    document.getElementById('opp-id').value = cardElement.getAttribute('data-id');
    document.getElementById('opp-name').value = cardElement.getAttribute('data-name');
    document.getElementById('opp-stage').value = cardElement.getAttribute('data-stage');
    document.getElementById('opp-value').value = cardElement.getAttribute('data-value');
    document.getElementById('opp-probability').value = cardElement.getAttribute('data-probability');
    document.getElementById('opp-close-date').value = cardElement.getAttribute('data-close-date');
    
    // Hide contact picker during update (Contact cannot be reassigned)
    contactGroup.classList.add('hidden');
    document.getElementById('opp-contact-id').required = false;
    
    title.textContent = '✏️ Edit Deal Details';
    deleteBtn.classList.remove('hidden');
    modal.classList.remove('hidden');
}

document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('opp-modal');
    const form = document.getElementById('opp-form');
    const openAddBtn = document.getElementById('open-add-opp-btn');
    const closeBtn = document.getElementById('close-opp-modal-btn');
    const deleteBtn = document.getElementById('delete-opp-btn');
    
    if (openAddBtn) {
        openAddBtn.addEventListener('click', () => {
            document.getElementById('opp-form-action').value = 'create';
            document.getElementById('opp-id').value = '';
            document.getElementById('opp-contact-group').classList.remove('hidden');
            document.getElementById('opp-contact-id').required = true;
            document.getElementById('opp-modal-title').textContent = '📈 New Sales Opportunity';
            deleteBtn.classList.add('hidden');
            form.reset();
            modal.classList.remove('hidden');
        });
    }
    
    if (closeBtn) closeBtn.addEventListener('click', () => modal.classList.add('hidden'));
    
    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const submitBtn = document.getElementById('save-opp-btn');
            submitBtn.disabled = true;
            
            const formData = new FormData(form);
            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-Token': formData.get('csrf_token')
                }
            })
            .then(res => res.json().then(data => {
                if (!res.ok) throw new Error(data.message || 'Operation failed.');
                return data;
            }))
            .then(data => {
                modal.classList.add('hidden');
                alert(data.message);
                window.location.reload();
            })
            .catch(err => {
                alert(err.message);
                submitBtn.disabled = false;
            });
        });
    }
    
    if (deleteBtn) {
        deleteBtn.addEventListener('click', () => {
            if (confirm('Are you sure you want to delete this opportunity?')) {
                const oppId = document.getElementById('opp-id').value;
                const csrfToken = document.querySelector('input[name="csrf_token"]').value;
                
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', oppId);
                formData.append('csrf_token', csrfToken);
                
                fetch('api/pipeline.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-Token': csrfToken
                    }
                })
                .then(res => res.json().then(data => {
                    if (!res.ok) throw new Error(data.message || 'Delete failed.');
                    return data;
                }))
                .then(data => {
                    modal.classList.add('hidden');
                    alert(data.message);
                    window.location.reload();
                })
                .catch(err => {
                    alert(err.message);
                });
            }
        });
    }
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
