<?php
$pageTitle = 'Employees - FarmSaathi';
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
$whereConditions[] = "item_type = 'employee'";

if (!empty($search)) {
    $whereConditions[] = "(item_name LIKE ? OR category LIKE ? OR phone LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'sss';
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
    <h2>Employees Management</h2>
    <?php if (canModify()): ?>
    <a href="add.php?type=employee" class="btn btn-primary">+ Add New Employee</a>
    <?php endif; ?>
</div>

<div class="filters-section">
    <form method="GET" action="employees.php" class="filters-form">
        <div class="filter-group">
            <input 
                type="text" 
                name="search" 
                placeholder="Search employees..." 
                value="<?php echo htmlspecialchars($search); ?>"
                class="form-control"
            >
        </div>
        <div class="filter-group">
            <select name="status" class="form-control">
                <option value="">All Status</option>
                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary">🔍 Search</button>
        <a href="employees.php" class="btn btn-outline">Clear</a>
    </form>
</div>

<?php if ($result->num_rows > 0): ?>
<div class="table-responsive">
    <table class="data-table">
        <thead>
            <tr>
                <th>Employee Name</th>
                <th>Role/Position</th>
                <th>Phone</th>
                <th>Salary</th>
                <th>Hire Date</th>
                <th>Status</th>
                <?php if (canModify()): ?>
                <th>Actions</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                <td><?php echo htmlspecialchars($row['category']); ?></td>
                <td><?php echo htmlspecialchars($row['phone'] ?? 'N/A'); ?></td>
                <td><?php echo $row['salary'] ? '₹' . number_format($row['salary'], 2) : 'N/A'; ?></td>
                <td><?php echo $row['hire_date'] ? formatDate($row['hire_date']) : 'N/A'; ?></td>
                <td><span class="badge badge-<?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                <?php if (canModify()): ?>
                <td class="actions">
                    <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-edit">Edit</a>
                    <a href="delete.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-delete" onclick="return confirm('Are you sure you want to delete this employee?');">Delete</a>
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
    <p>No employees found.</p>
    <?php if (canModify()): ?>
    <a href="add.php?type=employee" class="btn btn-primary">Add Your First Employee</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php
$stmt->close();
require_once __DIR__ . '/../includes/footer.php';
?>
