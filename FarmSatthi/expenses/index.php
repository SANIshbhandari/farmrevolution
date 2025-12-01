<?php
$pageTitle = 'Finance - FarmSaathi';
$currentModule = 'finance';
require_once __DIR__ . '/../includes/header.php';

$conn = getDBConnection();

$search = sanitizeInput($_GET['search'] ?? '');
$type = sanitizeInput($_GET['type'] ?? ''); // income or expense
$category = sanitizeInput($_GET['category'] ?? '');
$date_from = sanitizeInput($_GET['date_from'] ?? '');
$date_to = sanitizeInput($_GET['date_to'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$recordsPerPage = 20;

$whereConditions = [];
$params = [];
$types = '';

// Add data isolation
$isolationWhere = getDataIsolationWhere();
$whereConditions[] = $isolationWhere;

// Filter by type if specified
if (!empty($type)) {
    $whereConditions[] = "type = ?";
    $params[] = $type;
    $types .= 's';
}

if (!empty($search)) {
    $whereConditions[] = "(category LIKE ? OR description LIKE ?)";
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

if (!empty($date_from)) {
    $whereConditions[] = "transaction_date >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $whereConditions[] = "transaction_date <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

$countQuery = "SELECT COUNT(*) as total FROM finance $whereClause";
$stmt = $conn->prepare($countQuery);
if (!$stmt) {
    die("Query error: " . $conn->error . "<br>Query: " . $countQuery);
}
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$totalRecords = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$pagination = getPagination($totalRecords, $page, $recordsPerPage);

$query = "SELECT * FROM finance $whereClause ORDER BY transaction_date DESC LIMIT ? OFFSET ?";
$params[] = $recordsPerPage;
$params[] = $pagination['offset'];
$types .= 'ii';

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Query error: " . $conn->error . "<br>Query: " . $query);
}
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="module-header">
    <h2>💰 Finance Management</h2>
    <?php if (canModify()): ?>
    <div style="display: flex; gap: 10px;">
        <a href="add.php?type=income" class="btn btn-success">+ Add Income</a>
        <a href="add.php?type=expense" class="btn btn-primary">+ Add Expense</a>
    </div>
    <?php endif; ?>
</div>

<!-- Quick Date Filters -->
<div style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px;">
    <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
        <strong style="color: #666;">Quick Filters:</strong>
        <a href="?date_from=<?php echo date('Y-m-d'); ?>&date_to=<?php echo date('Y-m-d'); ?>" class="btn btn-sm btn-outline">Today</a>
        <a href="?date_from=<?php echo date('Y-m-d', strtotime('-1 day')); ?>&date_to=<?php echo date('Y-m-d', strtotime('-1 day')); ?>" class="btn btn-sm btn-outline">Yesterday</a>
        <a href="?date_from=<?php echo date('Y-m-d', strtotime('monday this week')); ?>&date_to=<?php echo date('Y-m-d'); ?>" class="btn btn-sm btn-outline">This Week</a>
        <a href="?date_from=<?php echo date('Y-m-01'); ?>&date_to=<?php echo date('Y-m-d'); ?>" class="btn btn-sm btn-outline">This Month</a>
        <a href="?date_from=<?php echo date('Y-m-01', strtotime('-1 month')); ?>&date_to=<?php echo date('Y-m-t', strtotime('-1 month')); ?>" class="btn btn-sm btn-outline">Last Month</a>
        <a href="?date_from=<?php echo date('Y-01-01'); ?>&date_to=<?php echo date('Y-m-d'); ?>" class="btn btn-sm btn-outline">This Year</a>
        <a href="index.php" class="btn btn-sm btn-secondary">Clear All</a>
    </div>
</div>

<div class="filters-section">
    <form method="GET" action="index.php" class="filters-form">
        <div class="filter-group">
            <select name="type" class="form-control">
                <option value="">All Transactions</option>
                <option value="income" <?php echo $type === 'income' ? 'selected' : ''; ?>>Income Only</option>
                <option value="expense" <?php echo $type === 'expense' ? 'selected' : ''; ?>>Expenses Only</option>
            </select>
        </div>
        <div class="filter-group">
            <input 
                type="text" 
                name="search" 
                placeholder="Search transactions..." 
                value="<?php echo htmlspecialchars($search); ?>"
                class="form-control"
            >
        </div>
        <div class="filter-group">
            <input 
                type="date" 
                name="date_from" 
                placeholder="From Date" 
                value="<?php echo htmlspecialchars($date_from); ?>"
                class="form-control"
            >
        </div>
        <div class="filter-group">
            <input 
                type="date" 
                name="date_to" 
                placeholder="To Date" 
                value="<?php echo htmlspecialchars($date_to); ?>"
                class="form-control"
            >
        </div>
        <button type="submit" class="btn btn-secondary">🔍 Search</button>
        <a href="index.php" class="btn btn-outline">Clear</a>
    </form>
</div>

<?php 
// Calculate totals
$totalIncome = 0;
$totalExpense = 0;
$conn->query("SET @total_income = 0, @total_expense = 0");
$summaryResult = $conn->query("
    SELECT 
        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
        SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense
    FROM finance 
    WHERE $isolationWhere
");
if ($summaryResult && $summaryRow = $summaryResult->fetch_assoc()) {
    $totalIncome = $summaryRow['total_income'];
    $totalExpense = $summaryRow['total_expense'];
    $profit = $totalIncome - $totalExpense;
}
?>

<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
    <div class="stat-card" style="background: #e8f5e9; padding: 1rem; border-radius: 8px;">
        <h4 style="margin: 0 0 0.5rem 0; color: #2e7d32;">Total Income</h4>
        <p style="font-size: 1.5rem; font-weight: bold; margin: 0; color: #1b5e20;"><?php echo formatCurrency($totalIncome); ?></p>
    </div>
    <div class="stat-card" style="background: #ffebee; padding: 1rem; border-radius: 8px;">
        <h4 style="margin: 0 0 0.5rem 0; color: #c62828;">Total Expenses</h4>
        <p style="font-size: 1.5rem; font-weight: bold; margin: 0; color: #b71c1c;"><?php echo formatCurrency($totalExpense); ?></p>
    </div>
    <div class="stat-card" style="background: <?php echo $profit >= 0 ? '#e3f2fd' : '#fff3e0'; ?>; padding: 1rem; border-radius: 8px;">
        <h4 style="margin: 0 0 0.5rem 0; color: <?php echo $profit >= 0 ? '#1565c0' : '#e65100'; ?>;">Profit/Loss</h4>
        <p style="font-size: 1.5rem; font-weight: bold; margin: 0; color: <?php echo $profit >= 0 ? '#0d47a1' : '#bf360c'; ?>;"><?php echo formatCurrency($profit); ?></p>
    </div>
</div>

<?php if ($result->num_rows > 0): ?>
<div class="table-responsive">
    <table class="data-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Category</th>
                <th>Description</th>
                <th>Amount</th>
                <th>Payment Method</th>
                <?php if (canModify()): ?>
                <th>Actions</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo formatDate($row['transaction_date']); ?></td>
                <td><span class="badge badge-<?php echo $row['type'] === 'income' ? 'success' : 'warning'; ?>"><?php echo ucfirst($row['type']); ?></span></td>
                <td><?php echo htmlspecialchars($row['category'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars(substr($row['description'] ?? '', 0, 50)) . (strlen($row['description'] ?? '') > 50 ? '...' : ''); ?></td>
                <td class="<?php echo $row['type'] === 'income' ? 'text-success' : 'text-danger'; ?>"><?php echo formatCurrency($row['amount']); ?></td>
                <td><?php echo ucfirst(str_replace('_', ' ', $row['payment_method'])); ?></td>
                <?php if (canModify()): ?>
                <td class="actions">
                    <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-edit">Edit</a>
                    <a href="delete.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-delete" onclick="return confirm('Are you sure you want to delete this expense?');">Delete</a>
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
    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" class="btn btn-sm">Previous</a>
    <?php endif; ?>
    
    <span class="pagination-info">
        Page <?php echo $pagination['current_page']; ?> of <?php echo $pagination['total_pages']; ?>
        (<?php echo $totalRecords; ?> total records)
    </span>
    
    <?php if ($pagination['has_next']): ?>
    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" class="btn btn-sm">Next</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php else: ?>
<div class="no-results">
    <p>No expenses found.</p>
    <?php if (canModify()): ?>
    <a href="add.php" class="btn btn-primary">Add Your First Expense</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php
$stmt->close();
require_once __DIR__ . '/../includes/footer.php';
?>
