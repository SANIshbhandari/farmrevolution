<?php
$pageTitle = 'Supplies - FarmSaathi';
$currentModule = 'inventory';
require_once __DIR__ . '/../includes/header.php';

$conn = getDBConnection();

$search = sanitizeInput($_GET['search'] ?? '');
$category = sanitizeInput($_GET['category'] ?? '');
$status = sanitizeInput($_GET['status'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$recordsPerPage = 20;

$whereConditions = [];
$params = [];
$types = '';

// Add data isolation
$isolationWhere = getDataIsolationWhere();
$whereConditions[] = $isolationWhere;
$whereConditions[] = "item_type = 'supply'";

if (!empty($search)) {
    $whereConditions[] = "(item_name LIKE ? OR category LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'ss';
}

if (!empty($category)) {
    $whereConditions[] = "category = ?";
    $params[] = $category;
    $types .= 's';
}

if (!empty($status)) {
    $whereConditions[] = "status = ?";
    $params[] = $status;
    $types .= 's';
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

$countQuery = "SELECT COUNT(*) as total FROM inventory $whereClause";
$stmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$totalRecords = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$pagination = getPagination($totalRecords, $page, $recordsPerPage);

$query = "SELECT * FROM inventory $whereClause ORDER BY item_name ASC LIMIT ? OFFSET ?";
$params[] = $recordsPerPage;
$params[] = $pagination['offset'];
$types .= 'ii';

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="module-header">
    <h2>Supplies Management</h2>
    <?php if (canModify()): ?>
    <a href="add.php?type=supply" class="btn btn-primary">+ Add New Supply</a>
    <?php endif; ?>
</div>

<div class="filters-section">
    <form method="GET" action="supplies.php" class="filters-form">
        <div class="filter-group">
            <input 
                type="text" 
                name="search" 
                placeholder="Search supplies..." 
                value="<?php echo htmlspecialchars($search); ?>"
                class="form-control"
            >
        </div>
        <div class="filter-group">
            <select name="status" class="form-control">
                <option value="">All Status</option>
                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                <option value="low_stock" <?php echo $status === 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary">🔍 Search</button>
        <a href="supplies.php" class="btn btn-outline">Clear</a>
    </form>
</div>

<?php if ($result->num_rows > 0): ?>
<div class="table-responsive">
    <table class="data-table">
        <thead>
            <tr>
                <th>Item Name</th>
                <th>Category</th>
                <th>Quantity</th>
                <th>Unit</th>
                <th>Reorder Level</th>
                <th>Status</th>
                <th>Last Updated</th>
                <?php if (canModify()): ?>
                <th>Actions</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): 
                $isLowStock = $row['quantity'] > 0 && $row['reorder_level'] !== null && $row['reorder_level'] >= 0 && $row['quantity'] <= $row['reorder_level'];
            ?>
            <tr class="<?php echo $isLowStock ? 'low-stock-row' : ''; ?>">
                <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                <td><?php echo htmlspecialchars($row['category']); ?></td>
                <td><?php echo $row['quantity'] ? number_format($row['quantity'], 2) : 'N/A'; ?></td>
                <td><?php echo htmlspecialchars($row['unit'] ?? 'N/A'); ?></td>
                <td><?php echo $row['reorder_level'] !== null ? number_format($row['reorder_level'], 2) : 'N/A'; ?></td>
                <td>
                    <?php if ($isLowStock): ?>
                        <span class="badge badge-warning">⚠️ Low Stock</span>
                    <?php else: ?>
                        <span class="badge badge-<?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></span>
                    <?php endif; ?>
                </td>
                <td><?php echo isset($row['created_at']) ? formatDate($row['created_at']) : 'N/A'; ?></td>
                <?php if (canModify()): ?>
                <td class="actions">
                    <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-edit">Edit</a>
                    <a href="delete.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-delete" onclick="return confirm('Are you sure you want to delete this item?');">Delete</a>
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
    <p>No supplies found.</p>
    <?php if (canModify()): ?>
    <a href="add.php?type=supply" class="btn btn-primary">Add Your First Supply</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php
$stmt->close();
require_once __DIR__ . '/../includes/footer.php';
?>
