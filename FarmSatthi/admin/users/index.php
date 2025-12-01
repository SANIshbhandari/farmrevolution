<?php
$pageTitle = 'User Management - FarmSaathi';
$currentModule = 'users';
require_once __DIR__ . '/../../includes/header.php';

// Only admin can access
requirePermission('admin');

$conn = getDBConnection();

$search = sanitizeInput($_GET['search'] ?? '');
$role_filter = sanitizeInput($_GET['role'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$recordsPerPage = 20;

$whereConditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $whereConditions[] = "(username LIKE ? OR email LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'ss';
}

if (!empty($role_filter)) {
    $whereConditions[] = "role = ?";
    $params[] = $role_filter;
    $types .= 's';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$countQuery = "SELECT COUNT(*) as total FROM users $whereClause";
$stmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$totalRecords = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$pagination = getPagination($totalRecords, $page, $recordsPerPage);

$query = "SELECT * FROM users $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $recordsPerPage;
$params[] = $pagination['offset'];
$types .= 'ii';

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="module-header">
    <h2>User Management</h2>
    <a href="add.php" class="btn btn-primary">+ Create New User</a>
</div>

<div class="filters-section">
    <form method="GET" action="index.php" class="filters-form">
        <div class="filter-group">
            <input 
                type="text" 
                name="search" 
                placeholder="Search users..." 
                value="<?php echo htmlspecialchars($search); ?>"
                class="form-control"
            >
        </div>
        <div class="filter-group">
            <select name="role" class="form-control">
                <option value="">All Roles</option>
                <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                <option value="manager" <?php echo $role_filter === 'manager' ? 'selected' : ''; ?>>Manager</option>
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
                <th>ID</th>
                <th>Username</th>
                <th>Role</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['username']); ?></td>
                <td><span class="badge badge-<?php echo $row['role']; ?>"><?php echo ucfirst($row['role']); ?></span></td>
                <td><?php echo formatDate($row['created_at']); ?></td>
                <td class="actions">
                    <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-edit">Edit</a>
                    <?php if ($row['id'] != getCurrentUserId()): ?>
                    <a href="delete.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-delete" onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php if ($pagination['total_pages'] > 1): ?>
<div class="pagination">
    <?php if ($pagination['has_previous']): ?>
    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>" class="btn btn-sm">Previous</a>
    <?php endif; ?>
    
    <span class="pagination-info">
        Page <?php echo $pagination['current_page']; ?> of <?php echo $pagination['total_pages']; ?>
        (<?php echo $totalRecords; ?> total users)
    </span>
    
    <?php if ($pagination['has_next']): ?>
    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>" class="btn btn-sm">Next</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php else: ?>
<div class="no-results">
    <p>No users found.</p>
    <a href="add.php" class="btn btn-primary">Create First User</a>
</div>
<?php endif; ?>

<?php
$stmt->close();
require_once __DIR__ . '/../../includes/footer.php';
?>
