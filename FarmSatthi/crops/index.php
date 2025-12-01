<?php
$pageTitle = 'Crops - FarmSaathi';
$currentModule = 'crops';
require_once __DIR__ . '/../includes/header.php';

$conn = getDBConnection();

// Get search and filter parameters
$search = sanitizeInput($_GET['search'] ?? '');
$status = sanitizeInput($_GET['status'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$recordsPerPage = 20;

// Build query with data isolation
$whereConditions = [];
$params = [];
$types = '';

// Add data isolation - managers see only their own data
$isolationWhere = getDataIsolationWhere();
$whereConditions[] = $isolationWhere;

if (!empty($search)) {
    $whereConditions[] = "(crop_name LIKE ? OR crop_type LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'ss';
}

if (!empty($status)) {
    $whereConditions[] = "status = ?";
    $params[] = $status;
    $types .= 's';
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM crops $whereClause";
$stmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$totalRecords = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Get pagination data
$pagination = getPagination($totalRecords, $page, $recordsPerPage);

// Get crops
$query = "SELECT * FROM crops $whereClause ORDER BY planting_date DESC LIMIT ? OFFSET ?";
$params[] = $recordsPerPage;
$params[] = $pagination['offset'];
$types .= 'ii';

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="module-header">
    <h2>Crops Management</h2>
    <?php if (canModify()): ?>
    <a href="add.php" class="btn btn-primary">+ Add New Crop</a>
    <?php endif; ?>
</div>

<div class="filters-section">
    <form method="GET" action="index.php" class="filters-form">
        <div class="filter-group">
            <input 
                type="text" 
                name="search" 
                placeholder="Search crops..." 
                value="<?php echo htmlspecialchars($search); ?>"
                class="form-control"
            >
        </div>
        <div class="filter-group">
            <select name="status" class="form-control">
                <option value="">All Status</option>
                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="harvested" <?php echo $status === 'harvested' ? 'selected' : ''; ?>>Harvested</option>
                <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Failed</option>
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
                <th>Crop Name</th>
                <th>Type</th>
                <th>Planting Date</th>
                <th>Harvest Date</th>
                <th>Area (ha)</th>
                <th>Status</th>
                <?php if (canModify()): ?>
                <th>Actions</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['crop_name'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['crop_type'] ?? ''); ?></td>
                <td><?php echo formatDate($row['planting_date']); ?></td>
                <td><?php echo $row['harvest_date'] ? formatDate($row['harvest_date']) : 'Not set'; ?></td>
                <td><?php echo number_format($row['area_hectares'], 2); ?></td>
                <td><span class="badge badge-<?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                <?php if (canModify()): ?>
                <td class="actions">
                    <a href="record_sale.php?crop_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-success">💰 Record Sale</a>
                    <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-edit">Edit</a>
                    <a href="delete.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-delete" onclick="return confirm('Are you sure you want to delete this crop?');">Delete</a>
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
    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>" class="btn btn-sm">Previous</a>
    <?php endif; ?>
    
    <span class="pagination-info">
        Page <?php echo $pagination['current_page']; ?> of <?php echo $pagination['total_pages']; ?>
        (<?php echo $totalRecords; ?> total records)
    </span>
    
    <?php if ($pagination['has_next']): ?>
    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>" class="btn btn-sm">Next</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php else: ?>
<div class="no-results">
    <p>No crops found.</p>
    <?php if (canModify()): ?>
    <a href="add.php" class="btn btn-primary">Add Your First Crop</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php
$stmt->close();
require_once __DIR__ . '/../includes/footer.php';
?>
