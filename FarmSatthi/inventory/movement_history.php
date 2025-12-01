<?php
$pageTitle = 'Stock Movement History - FarmSaathi';
$currentModule = 'inventory';
require_once __DIR__ . '/../includes/header.php';

$conn = getDBConnection();

// Get inventory item ID (optional - if provided, show history for specific item)
$inventory_id = intval($_GET['id'] ?? 0);
$item = null;

if ($inventory_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM inventory WHERE id = ? AND " . getDataIsolationWhere());
    $stmt->bind_param("i", $inventory_id);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Filters
$movement_type = sanitizeInput($_GET['type'] ?? '');
$date_from = sanitizeInput($_GET['date_from'] ?? '');
$date_to = sanitizeInput($_GET['date_to'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$recordsPerPage = 50;

// Build query
$whereConditions = [];
$params = [];
$types = '';

// Data isolation
$isolationWhere = getDataIsolationWhere();

if ($inventory_id > 0) {
    $whereConditions[] = "sm.inventory_id = ?";
    $params[] = $inventory_id;
    $types .= 'i';
}

if (!empty($movement_type)) {
    $whereConditions[] = "sm.movement_type = ?";
    $params[] = $movement_type;
    $types .= 's';
}

if (!empty($date_from)) {
    $whereConditions[] = "sm.movement_date >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $whereConditions[] = "sm.movement_date <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$whereClause = !empty($whereConditions) ? 'AND ' . implode(' AND ', $whereConditions) : '';

// Get total count
$countQuery = "
    SELECT COUNT(*) as total 
    FROM stock_movements sm
    INNER JOIN inventory i ON sm.inventory_id = i.id
    WHERE $isolationWhere $whereClause
";
$stmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$totalRecords = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$pagination = getPagination($totalRecords, $page, $recordsPerPage);

// Get movements
$query = "
    SELECT sm.*, i.item_name, i.item_type, i.unit, u.username
    FROM stock_movements sm
    INNER JOIN inventory i ON sm.inventory_id = i.id
    LEFT JOIN users u ON sm.created_by = u.id
    WHERE $isolationWhere $whereClause
    ORDER BY sm.movement_date DESC, sm.created_at DESC
    LIMIT ? OFFSET ?
";
$params[] = $recordsPerPage;
$params[] = $pagination['offset'];
$types .= 'ii';

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="module-header">
    <h2>📊 Stock Movement History</h2>
    <a href="index.php" class="btn btn-outline">← Back to Inventory</a>
</div>

<?php if ($item): ?>
<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #2d7a3e;">
    <h3 style="margin: 0; color: #2d7a3e;">📦 <?php echo htmlspecialchars($item['item_name']); ?></h3>
    <p style="margin: 5px 0 0 0;">Current Stock: <strong><?php echo number_format($item['quantity'], 2); ?> <?php echo $item['unit']; ?></strong></p>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="filters-section">
    <form method="GET" class="filters-form">
        <?php if ($inventory_id > 0): ?>
        <input type="hidden" name="id" value="<?php echo $inventory_id; ?>">
        <?php endif; ?>
        
        <div class="filter-group">
            <select name="type" class="form-control">
                <option value="">All Movements</option>
                <option value="in" <?php echo $movement_type === 'in' ? 'selected' : ''; ?>>📥 Stock IN</option>
                <option value="out" <?php echo $movement_type === 'out' ? 'selected' : ''; ?>>📤 Stock OUT</option>
            </select>
        </div>
        
        <div class="filter-group">
            <input 
                type="date" 
                name="date_from" 
                class="form-control" 
                value="<?php echo htmlspecialchars($date_from); ?>"
                placeholder="From Date"
            >
        </div>
        
        <div class="filter-group">
            <input 
                type="date" 
                name="date_to" 
                class="form-control" 
                value="<?php echo htmlspecialchars($date_to); ?>"
                placeholder="To Date"
            >
        </div>
        
        <button type="submit" class="btn btn-secondary">🔍 Search</button>
        <a href="movement_history.php<?php echo $inventory_id > 0 ? '?id=' . $inventory_id : ''; ?>" class="btn btn-outline">Clear</a>
    </form>
</div>

<!-- Summary Cards -->
<?php
$summaryQuery = "
    SELECT 
        SUM(CASE WHEN movement_type = 'in' THEN quantity ELSE 0 END) as total_in,
        SUM(CASE WHEN movement_type = 'out' THEN quantity ELSE 0 END) as total_out,
        COUNT(*) as total_movements
    FROM stock_movements sm
    INNER JOIN inventory i ON sm.inventory_id = i.id
    WHERE $isolationWhere $whereClause
";
$stmt = $conn->prepare($summaryQuery);
if (!empty($params)) {
    // Remove the LIMIT and OFFSET params for summary
    array_pop($params);
    array_pop($params);
    $summaryTypes = substr($types, 0, -2);
    if (!empty($summaryTypes)) {
        $stmt->bind_param($summaryTypes, ...$params);
    }
}
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
    <div style="background: #e8f5e9; padding: 1rem; border-radius: 8px;">
        <h4 style="margin: 0 0 0.5rem 0; color: #2e7d32;">📥 Total Stock IN</h4>
        <p style="font-size: 1.5rem; font-weight: bold; margin: 0; color: #1b5e20;"><?php echo number_format($summary['total_in'], 2); ?></p>
    </div>
    <div style="background: #ffebee; padding: 1rem; border-radius: 8px;">
        <h4 style="margin: 0 0 0.5rem 0; color: #c62828;">📤 Total Stock OUT</h4>
        <p style="font-size: 1.5rem; font-weight: bold; margin: 0; color: #b71c1c;"><?php echo number_format($summary['total_out'], 2); ?></p>
    </div>
    <div style="background: #e3f2fd; padding: 1rem; border-radius: 8px;">
        <h4 style="margin: 0 0 0.5rem 0; color: #1565c0;">📊 Total Movements</h4>
        <p style="font-size: 1.5rem; font-weight: bold; margin: 0; color: #0d47a1;"><?php echo number_format($summary['total_movements']); ?></p>
    </div>
</div>

<!-- Movement History Table -->
<?php if ($result->num_rows > 0): ?>
<div class="table-responsive">
    <table class="data-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Item</th>
                <th>Type</th>
                <th>Quantity</th>
                <th>Reason</th>
                <th>Reference</th>
                <th>By</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo formatDate($row['movement_date']); ?></td>
                <td>
                    <strong><?php echo htmlspecialchars($row['item_name']); ?></strong><br>
                    <small><?php echo ucfirst($row['item_type']); ?></small>
                </td>
                <td>
                    <?php if ($row['movement_type'] === 'in'): ?>
                        <span class="badge badge-success">📥 IN</span>
                    <?php else: ?>
                        <span class="badge badge-warning">📤 OUT</span>
                    <?php endif; ?>
                </td>
                <td style="font-weight: bold; color: <?php echo $row['movement_type'] === 'in' ? '#28a745' : '#dc3545'; ?>;">
                    <?php echo $row['movement_type'] === 'in' ? '+' : '-'; ?><?php echo number_format($row['quantity'], 2); ?> <?php echo $row['unit']; ?>
                </td>
                <td><?php echo htmlspecialchars($row['reason']); ?></td>
                <td><?php echo htmlspecialchars($row['reference_number'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($row['username'] ?? 'Unknown'); ?></td>
                <td><?php echo htmlspecialchars(substr($row['notes'] ?? '', 0, 50)) . (strlen($row['notes'] ?? '') > 50 ? '...' : ''); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php if ($pagination['total_pages'] > 1): ?>
<div class="pagination">
    <?php if ($pagination['has_previous']): ?>
    <a href="?page=<?php echo $page - 1; ?><?php echo $inventory_id > 0 ? '&id=' . $inventory_id : ''; ?>&type=<?php echo urlencode($movement_type); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" class="btn btn-sm">Previous</a>
    <?php endif; ?>
    
    <span class="pagination-info">
        Page <?php echo $pagination['current_page']; ?> of <?php echo $pagination['total_pages']; ?>
        (<?php echo $totalRecords; ?> total movements)
    </span>
    
    <?php if ($pagination['has_next']): ?>
    <a href="?page=<?php echo $page + 1; ?><?php echo $inventory_id > 0 ? '&id=' . $inventory_id : ''; ?>&type=<?php echo urlencode($movement_type); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" class="btn btn-sm">Next</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php else: ?>
<div class="no-results">
    <p>No stock movements found.</p>
</div>
<?php endif; ?>

<?php
$stmt->close();
require_once __DIR__ . '/../includes/footer.php';
?>
