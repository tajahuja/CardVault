<?php
/**
 * Contacts List & Search Page
 */

$pageTitle = 'Contacts';
require_once __DIR__ . '/includes/header.php';
$pdo = require_once __DIR__ . '/includes/db.php';

$userId = $_SESSION['user_id'];

// Retrieve search, filter, sorting and page inputs
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$tagFilter = isset($_GET['tag']) ? trim($_GET['tag']) : '';
$industryFilter = isset($_GET['industry']) ? trim($_GET['industry']) : '';
$sourceFilter = isset($_GET['lead_source']) ? trim($_GET['lead_source']) : '';
$sortBy = isset($_GET['sort']) ? trim($_GET['sort']) : 'name_asc';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

$limit = 10;
$offset = ($page - 1) * $limit;

$allTags = [];
$allIndustries = [];
$allSources = [];

try {
    // Fetch all user's tags for the filter dropdown
    $tagsStmt = $pdo->prepare("SELECT DISTINCT name FROM tags WHERE user_id = :user_id ORDER BY name ASC");
    $tagsStmt->execute(['user_id' => $userId]);
    $allTags = $tagsStmt->fetchAll(PDO::FETCH_COLUMN);

    // Fetch distinct industries for the filter dropdown
    $indStmt = $pdo->prepare("SELECT DISTINCT industry FROM contacts WHERE user_id = :user_id AND industry IS NOT NULL AND industry != '' ORDER BY industry ASC");
    $indStmt->execute(['user_id' => $userId]);
    $allIndustries = $indStmt->fetchAll(PDO::FETCH_COLUMN);

    // Fetch distinct lead sources for the filter dropdown
    $srcStmt = $pdo->prepare("SELECT DISTINCT lead_source FROM contacts WHERE user_id = :user_id AND lead_source IS NOT NULL AND lead_source != '' ORDER BY lead_source ASC");
    $srcStmt->execute(['user_id' => $userId]);
    $allSources = $srcStmt->fetchAll(PDO::FETCH_COLUMN);

    // 1. Build Query Constraints
    $where = "WHERE user_id = :user_id";
    $params = ['user_id' => $userId];
    
    if ($search !== '') {
        $where .= " AND (full_name LIKE :search 
                         OR company LIKE :search 
                         OR job_title LIKE :search 
                         OR phone LIKE :search 
                         OR email LIKE :search
                         OR website LIKE :search
                         OR linkedin_url LIKE :search
                         OR address LIKE :search
                         OR city LIKE :search
                         OR state LIKE :search
                         OR country LIKE :search
                         OR postal_code LIKE :search
                         OR EXISTS (
                             SELECT 1 FROM contact_tags ct 
                             JOIN tags t ON ct.tag_id = t.id 
                             WHERE ct.contact_id = contacts.id AND t.name LIKE :search
                         )
                         OR EXISTS (
                             SELECT 1 FROM notes n 
                             WHERE n.contact_id = contacts.id AND n.note LIKE :search
                         ))";
        $params['search'] = '%' . $search . '%';
    }
    
    if ($statusFilter !== '') {
        $where .= " AND status = :status";
        $params['status'] = $statusFilter;
    }

    if ($tagFilter !== '') {
        $where .= " AND EXISTS (
            SELECT 1 FROM contact_tags ct 
            JOIN tags t ON ct.tag_id = t.id 
            WHERE ct.contact_id = contacts.id AND t.name = :tag_name
        )";
        $params['tag_name'] = $tagFilter;
    }

    if ($industryFilter !== '') {
        $where .= " AND industry = :industry";
        $params['industry'] = $industryFilter;
    }

    if ($sourceFilter !== '') {
        $where .= " AND lead_source = :lead_source";
        $params['lead_source'] = $sourceFilter;
    }
    
    // 2. Define Sorting Order
    $orderBy = "ORDER BY full_name ASC";
    switch ($sortBy) {
        case 'name_desc':
            $orderBy = "ORDER BY full_name DESC";
            break;
        case 'created_desc':
            $orderBy = "ORDER BY created_at DESC";
            break;
        case 'created_asc':
            $orderBy = "ORDER BY created_at ASC";
            break;
        case 'met_desc':
            $orderBy = "ORDER BY CASE WHEN date_met IS NULL THEN 1 ELSE 0 END, date_met DESC, created_at DESC";
            break;
        case 'followup_asc':
            $orderBy = "ORDER BY CASE WHEN follow_up_date IS NULL THEN 1 ELSE 0 END, follow_up_date ASC";
            break;
    }
    
    // 3. Count Total Matching Rows
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM contacts $where");
    foreach ($params as $key => $val) {
        $countStmt->bindValue(':' . $key, $val);
    }
    $countStmt->execute();
    $totalRows = $countStmt->fetchColumn();
    $totalPages = max(1, ceil($totalRows / $limit));
    
    // 4. Fetch Paginated Records
    $sql = "SELECT id, first_name, last_name, full_name, company, job_title, phone, email, status, date_met, industry, lead_source 
            FROM contacts 
            $where 
            $orderBy 
            LIMIT :limit OFFSET :offset";
            
    $stmt = $pdo->prepare($sql);
    
    // Bind all parameter values securely
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    if ($search !== '') {
        $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
    }
    if ($statusFilter !== '') {
        $stmt->bindValue(':status', $statusFilter, PDO::PARAM_STR);
    }
    if ($tagFilter !== '') {
        $stmt->bindValue(':tag_name', $tagFilter, PDO::PARAM_STR);
    }
    if ($industryFilter !== '') {
        $stmt->bindValue(':industry', $industryFilter, PDO::PARAM_STR);
    }
    if ($sourceFilter !== '') {
        $stmt->bindValue(':lead_source', $sourceFilter, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $contacts = $stmt->fetchAll();
    
} catch (\PDOException $e) {
    error_log("Contacts list load DB error: " . $e->getMessage());
    $dbError = true;
}
?>

<div class="page-header">
    <div class="page-title">
        <h1>Contacts Directory</h1>
        <p>Browse and search your contact database</p>
    </div>
    <div class="header-actions">
        <a href="api/contacts.php?export=csv" class="btn btn-secondary" target="_blank">📥 Export to CSV</a>
        <a href="scan.php" class="btn btn-primary">📸 Scan Card</a>
    </div>
</div>

<?php if (isset($dbError)): ?>
    <div class="alert alert-danger">An error occurred while loading contacts.</div>
<?php endif; ?>

<!-- Search, Filter & Sort Form -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-body" style="padding: 1rem;">
        <form method="GET" action="contacts.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)) auto; gap: 0.75rem; align-items: end;">
            <div class="form-group" style="margin-bottom: 0; min-width: 220px;">
                <label for="search" style="font-size: 0.8rem; font-weight: 600;">Search contacts</label>
                <input type="text" id="search" name="search" placeholder="Search by name, company, notes, tags..." value="<?php echo e($search); ?>">
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
                <label for="status" style="font-size: 0.8rem; font-weight: 600;">Status</label>
                <select id="status" name="status">
                    <option value="">All Statuses</option>
                    <?php
                    $statuses = ['New', 'Contacted', 'Follow-up', 'Converted', 'Not Interested', 'Archived'];
                    foreach ($statuses as $stat) {
                        $selected = ($statusFilter === $stat) ? 'selected' : '';
                        echo '<option value="' . $stat . '" ' . $selected . '>' . $stat . '</option>';
                    }
                    ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label for="tag" style="font-size: 0.8rem; font-weight: 600;">Tag</label>
                <select id="tag" name="tag">
                    <option value="">All Tags</option>
                    <?php
                    foreach ($allTags as $tName) {
                        $selected = ($tagFilter === $tName) ? 'selected' : '';
                        echo '<option value="' . e($tName) . '" ' . $selected . '>' . e($tName) . '</option>';
                    }
                    ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label for="industry" style="font-size: 0.8rem; font-weight: 600;">Industry</label>
                <select id="industry" name="industry">
                    <option value="">All Industries</option>
                    <?php
                    foreach ($allIndustries as $ind) {
                        $selected = ($industryFilter === $ind) ? 'selected' : '';
                        echo '<option value="' . e($ind) . '" ' . $selected . '>' . e($ind) . '</option>';
                    }
                    ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label for="lead_source" style="font-size: 0.8rem; font-weight: 600;">Lead Source</label>
                <select id="lead_source" name="lead_source">
                    <option value="">All Sources</option>
                    <?php
                    foreach ($allSources as $src) {
                        $selected = ($sourceFilter === $src) ? 'selected' : '';
                        echo '<option value="' . e($src) . '" ' . $selected . '>' . e($src) . '</option>';
                    }
                    ?>
                </select>
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
                <label for="sort" style="font-size: 0.8rem; font-weight: 600;">Sort By</label>
                <select id="sort" name="sort">
                    <option value="name_asc" <?php echo $sortBy === 'name_asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
                    <option value="name_desc" <?php echo $sortBy === 'name_desc' ? 'selected' : ''; ?>>Name (Z-A)</option>
                    <option value="created_desc" <?php echo $sortBy === 'created_desc' ? 'selected' : ''; ?>>Date Added (Newest)</option>
                    <option value="created_asc" <?php echo $sortBy === 'created_asc' ? 'selected' : ''; ?>>Date Added (Oldest)</option>
                    <option value="met_desc" <?php echo $sortBy === 'met_desc' ? 'selected' : ''; ?>>Date Met (Recent)</option>
                    <option value="followup_asc" <?php echo $sortBy === 'followup_asc' ? 'selected' : ''; ?>>Follow-up Date (Earliest)</option>
                </select>
            </div>
            
            <div style="display: flex; gap: 0.5rem; margin-bottom: 0.25rem;">
                <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.25rem;">Filter</button>
                <a href="contacts.php" class="btn btn-secondary" style="padding: 0.65rem 1rem; text-decoration: none; text-align: center;">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Contacts Table List -->
<div class="card">
    <div class="card-body" style="padding: 0;">
        <?php if (empty($contacts)): ?>
            <div class="empty-state">
                <div class="empty-icon">👥</div>
                <p>No contacts found matching your query.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Contact Name</th>
                            <th>Company / Job</th>
                            <th>Email Address</th>
                            <th>Phone Number</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contacts as $contact): 
                            $initials = '';
                            if (!empty($contact['first_name'])) $initials .= substr($contact['first_name'], 0, 1);
                            if (!empty($contact['last_name'])) $initials .= substr($contact['last_name'], 0, 1);
                            if (empty($initials)) $initials = substr($contact['full_name'] ?? 'C', 0, 1);
                            $initials = strtoupper($initials);
                            
                            $displayName = !empty($contact['full_name']) ? $contact['full_name'] : ($contact['first_name'] . ' ' . $contact['last_name']);
                            $statusClass = strtolower(str_replace(' ', '', $contact['status']));
                        ?>
                            <tr>
                                <td>
                                    <div class="contact-cell">
                                        <div class="contact-avatar"><?php echo e($initials); ?></div>
                                        <div class="contact-info-cell">
                                            <a href="contact.php?id=<?php echo $contact['id']; ?>" class="contact-name"><?php echo e($displayName); ?></a>
                                            <div style="font-size: 0.75rem; color: var(--text-muted);">Met: <?php echo $contact['date_met'] ? format_date_user($contact['date_met']) : 'Unknown'; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 500; color: var(--secondary-color);"><?php echo e($contact['company'] ?: '-'); ?></div>
                                    <div class="contact-info-cell"><div class="contact-company"><?php echo e($contact['job_title'] ?: '-'); ?></div></div>
                                </td>
                                <td>
                                    <?php if (!empty($contact['email'])): ?>
                                        <a href="mailto:<?php echo e($contact['email']); ?>" style="color: var(--primary-color); text-decoration: none;"><?php echo e($contact['email']); ?></a>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($contact['phone'])): ?>
                                        <a href="tel:<?php echo e($contact['phone']); ?>" style="color: var(--text-color); text-decoration: none;"><?php echo e($contact['phone']); ?></a>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $statusClass; ?>">
                                        <?php echo e($contact['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination Section -->
            <?php if ($totalPages > 1): ?>
                <div style="padding: 1.25rem 1.5rem; display: flex; align-items: center; justify-content: space-between; border-top: 1px solid var(--border-color);">
                    <div style="font-size: 0.85rem; color: var(--text-muted);">
                        Showing page <strong><?php echo $page; ?></strong> of <strong><?php echo $totalPages; ?></strong> pages (Total contacts: <?php echo $totalRows; ?>)
                    </div>
                    <div style="display: flex; gap: 0.35rem;">
                        <?php if ($page > 1): ?>
                            <a href="contacts.php?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="btn btn-secondary" style="font-size: 0.85rem; padding: 0.4rem 0.75rem;">Previous</a>
                        <?php endif; ?>
                        
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        for ($i = $start; $i <= $end; $i++) {
                            $activeClass = ($i === $page) ? 'btn-primary' : 'btn-secondary';
                            echo '<a href="contacts.php?' . http_build_query(array_merge($_GET, ['page' => $i])) . '" class="btn ' . $activeClass . '" style="font-size: 0.85rem; padding: 0.4rem 0.75rem;">' . $i . '</a>';
                        }
                        ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="contacts.php?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="btn btn-secondary" style="font-size: 0.85rem; padding: 0.4rem 0.75rem;">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
