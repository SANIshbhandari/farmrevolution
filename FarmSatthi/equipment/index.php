<?php
$pageTitle = 'Equipment - FarmSaathi';
$currentModule = 'equipment';
require_once __DIR__ . '/../includes/header.php';

$conn = getDBConnection();

$search = sanitizeInput($_GET['search'] ?? '');
$condition = sanitizeInput($_GET['condition'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$recordsPerPage = 20;

$whereConditions = [];
$params = [];
$types = '';

// Add data isolation
$isolationWhere = getDataIsolationWhere();
$whereConditions[] = $isolationWhere;

if (!empty($search)) {
    $whereConditions[] = "(equipment_name LIKE ? OR type LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'ss';
}

if (!empty($condition)) {
    $whereConditions[] = "condition = ?";
    $params[] = $condition;
    $types .= 's';
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

$countQuery = "SELECT COUNT(*) as total FROM equipment $whereClause";
$stmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$totalRecords = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$pagination = getPagination($totalRecords, $page, $recordsPerPage);

$query = "SELECT * FROM equipment $whereClause ORDER BY next_maintenance ASC, equipment_name ASC LIMIT ? OFFSET ?";
$params[] = $recordsPerPage;
$params[] = $pagination['offset'];
$types .= 'ii';

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="module-header">
    <h2>Equipment Management</h2>
    <?php if (canModify()): ?>
    <a href="add.php" class="btn btn-primary">+ Add New Equipment</a>
    <?php endif; ?>
</div>

<div class="filters-section">
    <form method="GET" action="index.php" class="filters-form">
        <div class="filter-group">
            <input 
                type="text" 
                name="search" 
                placeholder="Search equipment..." 
                value="<?php echo htmlspecialchars($search); ?>"
                class="form-control"
            >
        </div>
        <div class="filter-group">
            <select name="condition" class="form-control">
                <option value="">All Conditions</option>
                <option value="excellent" <?php echo $condition === 'excellent' ? 'selected' : ''; ?>>Excellent</option>
                <option value="good" <?php echo $condition === 'good' ? 'selected' : ''; ?>>Good</option>
                <option value="fair" <?php echo $condition === 'fair' ? 'selected' : ''; ?>>Fair</option>
                <option value="poor" <?php echo $condition === 'poor' ? 'selected' : ''; ?>>Poor</option>
                <option value="needs_repair" <?php echo $condition === 'needs_repair' ? 'selected' : ''; ?>>Needs Repair</option>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary">🔍 Search</button>
        <a href="index.php" class="btn btn-outline">Clear</a>
    </form>
</div>

<?php if ($result->num_rows > 0): ?>
<div class="table-responsive">
    <table class="data-table">
        <thead>
            <tr>
                <th>Equipment Name</th>
                <th>Type</th>
                <th>Purchase Date</th>
                <th>Last Maintenance</th>
                <th>Next Maintenance</th>
                <th>Condition</th>
                <th>Value</th>
                <?php if (canModify()): ?>
                <th>Actions</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['equipment_name'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['type'] ?? ''); ?></td>
                <td><?php echo formatDate($row['purchase_date']); ?></td>
                <td><?php echo formatDate($row['last_maintenance']); ?></td>
                <td><?php echo formatDate($row['next_maintenance']); ?></td>
                <td><span class="badge badge-<?php echo $row['condition']; ?>"><?php echo ucfirst(str_replace('_', ' ', $row['condition'])); ?></span></td>
                <td><?php echo formatCurrency($row['value']); ?></td>
                <?php if (canModify()): ?>
                <td class="actions">
                    <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-edit">Edit</a>
                    <a href="delete.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-delete" onclick="return confirm('Are you sure you want to delete this equipment?');">Delete</a>
                </td>
                <?php endif; ?>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php if ($pagination['total_pages'] > 1): ?>
<div class="pagination">
    <?php if ($pagination['has_previous']): ?>
    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&condition=<?php echo urlencode($condition); ?>" class="btn btn-sm">Previous</a>
    <?php endif; ?>
    
    <span class="pagination-info">
        Page <?php echo $pagination['current_page']; ?> of <?php echo $pagination['total_pages']; ?>
        (<?php echo $totalRecords; ?> total records)
    </span>
    
    <?php if ($pagination['has_next']): ?>
    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&condition=<?php echo urlencode($condition); ?>" class="btn btn-sm">Next</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php else: ?>
<div class="no-results">
    <p>No equipment found.</p>
    <?php if (canModify()): ?>
    <a href="add.php" class="btn btn-primary">Add Your First Equipment</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php
$stmt->close();
require_once __DIR__ . '/../includes/footer.php';
?>
