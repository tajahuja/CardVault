<?php
/**
 * Companies List & Search Page
 */

$pageTitle = 'Companies';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/csrf.php';
$pdo = require __DIR__ . '/includes/db.php';

$userId = $_SESSION['user_id'];

// Retrieve search, filter and pagination parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$industryFilter = isset($_GET['industry']) ? trim($_GET['industry']) : '';
$locationFilter = isset($_GET['location']) ? trim($_GET['location']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

$limit = 10;
$offset = ($page - 1) * $limit;

$allIndustries = [];
$allLocations = [];
$companies = [];
$totalCount = 0;

try {
    // Retrieve distinct industries for filtering
    $indStmt = $pdo->prepare("SELECT DISTINCT industry FROM companies WHERE user_id = :user_id AND industry IS NOT NULL AND industry != '' ORDER BY industry ASC");
    $indStmt->execute(['user_id' => $userId]);
    $allIndustries = $indStmt->fetchAll(PDO::FETCH_COLUMN);

    // Retrieve distinct locations for filtering
    $locStmt = $pdo->prepare("SELECT DISTINCT location FROM companies WHERE user_id = :user_id AND location IS NOT NULL AND location != '' ORDER BY location ASC");
    $locStmt->execute(['user_id' => $userId]);
    $allLocations = $locStmt->fetchAll(PDO::FETCH_COLUMN);

    // Build query conditions
    $where = "WHERE user_id = :user_id";
    $params = ['user_id' => $userId];

    if ($search !== '') {
        $where .= " AND (name LIKE :search_name OR industry LIKE :search_ind OR location LIKE :search_loc)";
        $params['search_name'] = '%' . $search . '%';
        $params['search_ind'] = '%' . $search . '%';
        $params['search_loc'] = '%' . $search . '%';
    }

    if ($industryFilter !== '') {
        $where .= " AND industry = :industry";
        $params['industry'] = $industryFilter;
    }

    if ($locationFilter !== '') {
        $where .= " AND location = :location";
        $params['location'] = $locationFilter;
    }

    // Count query
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM companies {$where}");
    $countStmt->execute($params);
    $totalCount = $countStmt->fetchColumn();

    // Select query with contacts count and last activity timeline log
    $sql = "
        SELECT c.*, 
               (SELECT COUNT(*) FROM contacts WHERE company_id = c.id) as contact_count,
               (SELECT MAX(created_at) FROM interactions WHERE contact_id IN (SELECT id FROM contacts WHERE company_id = c.id)) as last_interaction
        FROM companies c 
        {$where} 
        ORDER BY c.name ASC 
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $pdo->prepare($sql);
    
    // Bind limit & offset as integers explicitly for MySQL compatibility
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $key => $val) {
        $stmt->bindValue(':' . $key, $val);
    }
    $stmt->execute();
    $companies = $stmt->fetchAll();

} catch (\PDOException $e) {
    error_log("Database Error in Companies List: " . $e->getMessage());
    $dbError = "Unable to fetch companies.";
}

$totalPages = ceil($totalCount / $limit);
?>

<div class="page-header">
    <div class="page-title">
        <h1>B2B Companies</h1>
        <p>Manage organizations and associate your contacts into target clients</p>
    </div>
    <div class="header-actions">
        <button type="button" class="btn btn-primary" id="open-add-company-btn">🏢 Add Company</button>
    </div>
</div>

<?php if (isset($dbError)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($dbError); ?></div>
<?php endif; ?>

<!-- Filter & Search Controls -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-body">
        <form method="GET" action="companies.php" style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 1rem; align-items: end;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="search" class="form-label">Search Companies</label>
                <input type="text" name="search" id="search" class="form-control" placeholder="Search by name, industry, or location..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
                <label for="industry" class="form-label">Industry</label>
                <select name="industry" id="industry" class="form-control">
                    <option value="">All Industries</option>
                    <?php foreach ($allIndustries as $ind): ?>
                        <option value="<?php echo htmlspecialchars($ind); ?>" <?php echo $industryFilter === $ind ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ind); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label for="location" class="form-label">Location</label>
                <select name="location" id="location" class="form-control">
                    <option value="">All Locations</option>
                    <?php foreach ($allLocations as $loc): ?>
                        <option value="<?php echo htmlspecialchars($loc); ?>" <?php echo $locationFilter === $loc ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($loc); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="companies.php" class="btn btn-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- Companies Table Grid -->
<div class="card">
    <div class="card-body" style="padding: 0;">
        <?php if (empty($companies)): ?>
            <div style="padding: 3rem; text-align: center; color: var(--text-muted);">
                <p style="font-size: 1.2rem; margin-bottom: 1rem;">No companies found.</p>
                <p>Add a new company profile or adjust your filters above.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="text-align: left; border-bottom: 1px solid var(--border-color); background-color: var(--background-color);">
                            <th style="padding: 1rem;">Company Name</th>
                            <th style="padding: 1rem;">Industry</th>
                            <th style="padding: 1rem;">Website</th>
                            <th style="padding: 1rem;">Location</th>
                            <th style="padding: 1rem; text-align: center;">Contacts</th>
                            <th style="padding: 1rem;">Last Activity</th>
                            <th style="padding: 1rem; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($companies as $comp): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 1rem; font-weight: 500;">
                                    <a href="company.php?id=<?php echo $comp['id']; ?>" class="text-primary" style="text-decoration: none; font-weight: 600;">
                                        🏢 <?php echo htmlspecialchars($comp['name']); ?>
                                    </a>
                                </td>
                                <td style="padding: 1rem;">
                                    <?php echo htmlspecialchars($comp['industry'] ?? 'N/A'); ?>
                                </td>
                                <td style="padding: 1rem;">
                                    <?php if (!empty($comp['website'])): ?>
                                        <a href="<?php echo htmlspecialchars($comp['website']); ?>" target="_blank" class="text-primary" style="text-decoration: none;">
                                            <?php echo htmlspecialchars(parse_url($comp['website'], PHP_URL_HOST) ?: $comp['website']); ?> 🌐
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 1rem;">
                                    <?php echo htmlspecialchars($comp['location'] ?? 'N/A'); ?>
                                </td>
                                <td style="padding: 1rem; text-align: center;">
                                    <span class="badge" style="background-color: var(--primary-color); color: #fff; border-radius: 20px; padding: 0.25rem 0.6rem;">
                                        <?php echo intval($comp['contact_count']); ?>
                                    </span>
                                </td>
                                <td style="padding: 1rem; font-size: 0.9rem;">
                                    <?php echo $comp['last_interaction'] ? date('M d, Y', strtotime($comp['last_interaction'])) : '<span class="text-muted">No interactions</span>'; ?>
                                </td>
                                <td style="padding: 1rem; text-align: right; display: flex; gap: 0.5rem; justify-content: flex-end;">
                                    <a href="company.php?id=<?php echo $comp['id']; ?>" class="btn btn-secondary btn-sm" title="View Profile">👁️ View</a>
                                    <button type="button" class="btn btn-secondary btn-sm edit-company-trigger-btn" 
                                            data-id="<?php echo $comp['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($comp['name'], ENT_QUOTES); ?>"
                                            data-industry="<?php echo htmlspecialchars($comp['industry'] ?? '', ENT_QUOTES); ?>"
                                            data-website="<?php echo htmlspecialchars($comp['website'] ?? '', ENT_QUOTES); ?>"
                                            data-location="<?php echo htmlspecialchars($comp['location'] ?? '', ENT_QUOTES); ?>"
                                            data-source="<?php echo htmlspecialchars($comp['lead_source'] ?? '', ENT_QUOTES); ?>"
                                            title="Edit Company">✏️ Edit</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination Footer -->
            <?php if ($totalPages > 1): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; border-top: 1px solid var(--border-color);">
                    <div style="color: var(--text-muted); font-size: 0.9rem;">
                        Showing page <?php echo $page; ?> of <?php echo $totalPages; ?> (Total: <?php echo $totalCount; ?> companies)
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <?php if ($page > 1): ?>
                            <a href="companies.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&industry=<?php echo urlencode($industryFilter); ?>&location=<?php echo urlencode($locationFilter); ?>" class="btn btn-secondary btn-sm">Previous</a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="companies.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&industry=<?php echo urlencode($industryFilter); ?>&location=<?php echo urlencode($locationFilter); ?>" class="btn btn-secondary btn-sm">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add / Edit Company Modal -->
<div id="company-modal" class="modal-overlay hidden">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="modal-title">🏢 Add New Company</h3>
        </div>
        <form id="company-form" action="api/companies.php" method="POST">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" id="form-action" value="create">
            <input type="hidden" name="id" id="company-id" value="">
            
            <div class="modal-body">
                <div class="form-group">
                    <label for="comp-name" class="form-label">Company Name *</label>
                    <input type="text" name="name" id="comp-name" class="form-control" required placeholder="e.g. ABC Corporate Solutions">
                </div>
                <div class="form-group">
                    <label for="comp-industry" class="form-label">Industry</label>
                    <input type="text" name="industry" id="comp-industry" class="form-control" placeholder="e.g. Information Technology">
                </div>
                <div class="form-group">
                    <label for="comp-website" class="form-label">Website URL</label>
                    <input type="url" name="website" id="comp-website" class="form-control" placeholder="e.g. https://www.abccorp.com">
                </div>
                <div class="form-group">
                    <label for="comp-location" class="form-label">Location (City/Country)</label>
                    <input type="text" name="location" id="comp-location" class="form-control" placeholder="e.g. Mumbai, India">
                </div>
                <div class="form-group">
                    <label for="comp-source" class="form-label">Lead Source</label>
                    <input type="text" name="lead_source" id="comp-source" class="form-control" placeholder="e.g. Trade Show, Referrals">
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="close-modal-btn">Cancel</button>
                <button type="submit" class="btn btn-primary" id="save-company-btn">Save Company</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('company-modal');
    const form = document.getElementById('company-form');
    const openAddBtn = document.getElementById('open-add-company-btn');
    const closeModalBtn = document.getElementById('close-modal-btn');
    const modalTitle = document.getElementById('modal-title');
    
    const companyIdInput = document.getElementById('company-id');
    const actionInput = document.getElementById('form-action');
    const nameInput = document.getElementById('comp-name');
    const industryInput = document.getElementById('comp-industry');
    const websiteInput = document.getElementById('comp-website');
    const locationInput = document.getElementById('comp-location');
    const sourceInput = document.getElementById('comp-source');

    function openModal(mode = 'create', data = {}) {
        actionInput.value = mode;
        if (mode === 'create') {
            modalTitle.textContent = '🏢 Add New Company';
            companyIdInput.value = '';
            form.reset();
        } else {
            modalTitle.textContent = '✏️ Edit Company Details';
            companyIdInput.value = data.id;
            nameInput.value = data.name || '';
            industryInput.value = data.industry || '';
            websiteInput.value = data.website || '';
            locationInput.value = data.location || '';
            sourceInput.value = data.source || '';
        }
        modal.classList.remove('hidden');
    }

    if (openAddBtn) openAddBtn.addEventListener('click', () => openModal('create'));
    if (closeModalBtn) closeModalBtn.addEventListener('click', () => modal.classList.add('hidden'));

    // Handle Edit clicks
    document.querySelectorAll('.edit-company-trigger-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const data = {
                id: btn.getAttribute('data-id'),
                name: btn.getAttribute('data-name'),
                industry: btn.getAttribute('data-industry'),
                website: btn.getAttribute('data-website'),
                location: btn.getAttribute('data-location'),
                source: btn.getAttribute('data-source')
            };
            openModal('update', data);
        });
    });

    // Handle Form Submit via AJAX to handle validation notices safely
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const submitBtn = document.getElementById('save-company-btn');
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
                throw new Error(data.message || 'Saving company failed.');
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
