<?php
/**
 * Relationship Networking Events Workspace
 */

$pageTitle = 'Events';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/csrf.php';
$pdo = require __DIR__ . '/includes/db.php';

$userId = $_SESSION['user_id'];

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$typeFilter = isset($_GET['type']) ? trim($_GET['type']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

$limit = 10;
$offset = ($page - 1) * $limit;

$events = [];
$totalCount = 0;

try {
    $where = "WHERE user_id = :user_id";
    $params = ['user_id' => $userId];
    
    if ($search !== '') {
        $where .= " AND (name LIKE :search_name OR location LIKE :search_loc OR description LIKE :search_desc)";
        $term = '%' . $search . '%';
        $params['search_name'] = $term;
        $params['search_loc'] = $term;
        $params['search_desc'] = $term;
    }
    
    if ($typeFilter !== '') {
        $where .= " AND type = :type";
        $params['type'] = $typeFilter;
    }
    
    // Count query
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM events {$where}");
    $countStmt->execute($params);
    $totalCount = $countStmt->fetchColumn();
    
    // Fetch events with count of contacts met
    $sql = "
        SELECT e.*, 
               (SELECT COUNT(*) FROM event_contacts WHERE event_id = e.id) as contact_count,
               (SELECT COUNT(DISTINCT c.company_id) FROM event_contacts ec JOIN contacts c ON ec.contact_id = c.id WHERE ec.event_id = e.id AND c.company_id IS NOT NULL) as company_count
        FROM events e 
        {$where} 
        ORDER BY e.date DESC 
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $key => $val) {
        $stmt->bindValue(':' . $key, $val);
    }
    $stmt->execute();
    $events = $stmt->fetchAll();
    
} catch (\PDOException $e) {
    error_log("Events List DB Error: " . $e->getMessage());
    $dbError = "Unable to fetch events list.";
}

$totalPages = ceil($totalCount / $limit);
$eventTypes = ['Trade Show', 'Conference', 'Meeting', 'Networking Event', 'Exhibition', 'Travel', 'Client Visit', 'Other'];
?>

<div class="page-header" style="margin-bottom: 1.5rem;">
    <div class="page-title">
        <h1>Relationship Events Workspace</h1>
        <p>Log networking events, conventions, and travel exhibitions to structure contact channels</p>
    </div>
    <div class="header-actions">
        <button type="button" class="btn btn-primary" id="open-add-event-btn">📅 New Event</button>
    </div>
</div>

<?php if (isset($dbError)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($dbError); ?></div>
<?php endif; ?>

<!-- Filters -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-body">
        <form method="GET" action="events.php" style="display: grid; grid-template-columns: 2fr 1fr auto; gap: 1rem; align-items: end;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="search" class="form-label">Search Events</label>
                <input type="text" name="search" id="search" class="form-control" placeholder="Search by name, location, details..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
                <label for="type" class="form-label">Event Type</label>
                <select name="type" id="type" class="form-control">
                    <option value="">All Types</option>
                    <?php foreach ($eventTypes as $t): ?>
                        <option value="<?php echo htmlspecialchars($t); ?>" <?php echo $typeFilter === $t ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($t); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="events.php" class="btn btn-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- Events List Table -->
<div class="card">
    <div class="card-body" style="padding: 0;">
        <?php if (empty($events)): ?>
            <div style="padding: 3rem; text-align: center; color: var(--text-muted);">
                <p style="font-size: 1.2rem; margin-bottom: 1rem;">No events logged.</p>
                <p>Register a trade show or networking meeting to get started.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="text-align: left; border-bottom: 1px solid var(--border-color); background-color: var(--background-color);">
                            <th style="padding: 1rem;">Event Name</th>
                            <th style="padding: 1rem;">Type</th>
                            <th style="padding: 1rem;">Date</th>
                            <th style="padding: 1rem;">Location</th>
                            <th style="padding: 1rem; text-align: center;">Contacts Met</th>
                            <th style="padding: 1rem; text-align: center;">Companies represented</th>
                            <th style="padding: 1rem; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $ev): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 1rem; font-weight: 500;">
                                    <a href="event.php?id=<?php echo $ev['id']; ?>" class="text-primary" style="text-decoration: none; font-weight: 600;">
                                        📅 <?php echo htmlspecialchars($ev['name']); ?>
                                    </a>
                                </td>
                                <td style="padding: 1rem;">
                                    <span class="badge" style="background-color: #f1f5f9; color: var(--secondary-color); border: 1px solid var(--border-color); font-weight: 500; font-size: 0.8rem; padding: 0.15rem 0.5rem; border-radius: 4px;">
                                        <?php echo htmlspecialchars($ev['type']); ?>
                                    </span>
                                </td>
                                <td style="padding: 1rem;">
                                    <?php echo date('M d, Y', strtotime($ev['date'])); ?>
                                </td>
                                <td style="padding: 1rem;">
                                    <?php echo htmlspecialchars($ev['location'] ?? 'N/A'); ?>
                                </td>
                                <td style="padding: 1rem; text-align: center;">
                                    <span class="badge" style="background-color: var(--primary-color); color: #fff; border-radius: 20px; padding: 0.25rem 0.6rem;">
                                        <?php echo intval($ev['contact_count']); ?>
                                    </span>
                                </td>
                                <td style="padding: 1rem; text-align: center;">
                                    <span class="badge" style="background-color: #10b981; color: #fff; border-radius: 20px; padding: 0.25rem 0.6rem;">
                                        <?php echo intval($ev['company_count']); ?>
                                    </span>
                                </td>
                                <td style="padding: 1rem; text-align: right; display: flex; gap: 0.5rem; justify-content: flex-end;">
                                    <a href="scan.php?event_id=<?php echo $ev['id']; ?>" class="btn btn-secondary btn-sm" title="Scan card at this event">📸 Scan Card</a>
                                    <a href="event.php?id=<?php echo $ev['id']; ?>" class="btn btn-secondary btn-sm" title="View details">👁️ View</a>
                                    <button type="button" class="btn btn-secondary btn-sm edit-event-trigger-btn"
                                            data-id="<?php echo $ev['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($ev['name'], ENT_QUOTES); ?>"
                                            data-type="<?php echo htmlspecialchars($ev['type'], ENT_QUOTES); ?>"
                                            data-date="<?php echo $ev['date']; ?>"
                                            data-location="<?php echo htmlspecialchars($ev['location'] ?? '', ENT_QUOTES); ?>"
                                            data-desc="<?php echo htmlspecialchars($ev['description'] ?? '', ENT_QUOTES); ?>"
                                            title="Edit details">✏️ Edit</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; border-top: 1px solid var(--border-color);">
                    <div style="color: var(--text-muted); font-size: 0.9rem;">
                        Showing page <?php echo $page; ?> of <?php echo $totalPages; ?> (Total: <?php echo $totalCount; ?> events)
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <?php if ($page > 1): ?>
                            <a href="events.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($typeFilter); ?>" class="btn btn-secondary btn-sm">Previous</a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="events.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($typeFilter); ?>" class="btn btn-secondary btn-sm">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal -->
<div id="event-modal" class="modal-overlay hidden">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="modal-title">📅 Register New Event</h3>
        </div>
        <form id="event-form" action="api/events.php" method="POST">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" id="form-action" value="create">
            <input type="hidden" name="id" id="event-id" value="">
            
            <div class="modal-body">
                <div class="form-group">
                    <label for="event-name" class="form-label">Event Name *</label>
                    <input type="text" name="name" id="event-name" class="form-control" required placeholder="e.g. Thailand Tourism Expo 2026">
                </div>
                <div class="form-group">
                    <label for="event-type" class="form-label">Event Type *</label>
                    <select name="type" id="event-type" class="form-control" required>
                        <?php foreach ($eventTypes as $t): ?>
                            <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="event-date" class="form-label">Date *</label>
                    <input type="date" name="date" id="event-date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label for="event-location" class="form-label">Location (Venue/City)</label>
                    <input type="text" name="location" id="event-location" class="form-control" placeholder="e.g. Queen Sirikit Center, Bangkok">
                </div>
                <div class="form-group">
                    <label for="event-description" class="form-label">Description / Notes</label>
                    <textarea name="description" id="event-description" class="form-control" rows="3" placeholder="e.g. Exhibiting at Stall B4. Focused on travel operators and hoteliers."></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="close-modal-btn">Cancel</button>
                <button type="submit" class="btn btn-primary" id="save-event-btn">Save Event</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('event-modal');
    const form = document.getElementById('event-form');
    const openAddBtn = document.getElementById('open-add-event-btn');
    const closeModalBtn = document.getElementById('close-modal-btn');
    const modalTitle = document.getElementById('modal-title');
    
    const eventIdInput = document.getElementById('event-id');
    const actionInput = document.getElementById('form-action');
    const nameInput = document.getElementById('event-name');
    const typeInput = document.getElementById('event-type');
    const dateInput = document.getElementById('event-date');
    const locationInput = document.getElementById('event-location');
    const descInput = document.getElementById('event-description');

    function openModal(mode = 'create', data = {}) {
        actionInput.value = mode;
        if (mode === 'create') {
            modalTitle.textContent = '📅 Register New Event';
            eventIdInput.value = '';
            form.reset();
            dateInput.value = new Date().toISOString().substring(0, 10);
        } else {
            modalTitle.textContent = '✏️ Edit Event Details';
            eventIdInput.value = data.id;
            nameInput.value = data.name || '';
            typeInput.value = data.type || '';
            dateInput.value = data.date || '';
            locationInput.value = data.location || '';
            descInput.value = data.desc || '';
        }
        modal.classList.remove('hidden');
    }

    if (openAddBtn) openAddBtn.addEventListener('click', () => openModal('create'));
    if (closeModalBtn) closeModalBtn.addEventListener('click', () => modal.classList.add('hidden'));

    // Handle Edit clicks
    document.querySelectorAll('.edit-event-trigger-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const data = {
                id: btn.getAttribute('data-id'),
                name: btn.getAttribute('data-name'),
                type: btn.getAttribute('data-type'),
                date: btn.getAttribute('data-date'),
                location: btn.getAttribute('data-location'),
                desc: btn.getAttribute('data-desc')
            };
            openModal('update', data);
        });
    });

    // Handle Form Submit via AJAX
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const submitBtn = document.getElementById('save-event-btn');
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
        .then(response => response.json().then(data => {
            if (!response.ok) {
                throw new Error(data.message || 'Saving event failed.');
            }
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
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
