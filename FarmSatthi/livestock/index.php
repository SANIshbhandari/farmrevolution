<?php
$pageTitle = 'Livestock Management - FarmSaathi';
$currentModule = 'livestock';
require_once __DIR__ . '/../includes/header.php';

$conn = getDBConnection();
$isolationWhere = getDataIsolationWhere();

// Get summary statistics
$stats = $conn->query("
    SELECT 
        COUNT(*) as total_animals,
        SUM(quantity) as total_count,
        SUM(CASE WHEN status = 'active' THEN quantity ELSE 0 END) as active_count,
        SUM(CASE WHEN status = 'sold' THEN quantity ELSE 0 END) as sold_count,
        SUM(CASE WHEN status = 'deceased' THEN quantity ELSE 0 END) as deceased_count
    FROM livestock 
    WHERE $isolationWhere
")->fetch_assoc();

// Get births this month
$birthsThisMonth = $conn->query("
    SELECT COUNT(*) as count
    FROM livestock 
    WHERE acquisition_type = 'birth' 
    AND MONTH(date_of_birth) = MONTH(CURRENT_DATE())
    AND YEAR(date_of_birth) = YEAR(CURRENT_DATE())
    AND $isolationWhere
")->fetch_assoc()['count'];

// Get deaths this month (using created_at as proxy since we don't track death date separately)
$deathsThisMonth = $conn->query("
    SELECT SUM(quantity) as count
    FROM livestock 
    WHERE status = 'deceased'
    AND $isolationWhere
")->fetch_assoc()['count'] ?? 0;

// Get animals sold this month (check sales JSON for recent sales)
$soldThisMonth = 0;
$salesCheck = $conn->query("SELECT sales FROM livestock WHERE status = 'sold' AND $isolationWhere");
while ($row = $salesCheck->fetch_assoc()) {
    $salesData = json_decode($row['sales'] ?? '[]', true);
    if (is_array($salesData)) {
        foreach ($salesData as $sale) {
            $saleDate = $sale['sale_date'] ?? '';
            if ($saleDate && date('Y-m', strtotime($saleDate)) === date('Y-m')) {
                $soldThisMonth++;
            }
        }
    }
}

// Get upcoming vaccinations (from health_records JSON)
$upcomingVaccinations = [];
$healthCheck = $conn->query("SELECT id, animal_tag, animal_type, breed, health_records FROM livestock WHERE $isolationWhere AND status = 'active'");
while ($animal = $healthCheck->fetch_assoc()) {
    $healthRecords = json_decode($animal['health_records'] ?? '[]', true);
    if (is_array($healthRecords)) {
        foreach ($healthRecords as $record) {
            if (isset($record['next_due_date']) && $record['next_due_date'] >= date('Y-m-d') && $record['next_due_date'] <= date('Y-m-d', strtotime('+30 days'))) {
                $upcomingVaccinations[] = [
                    'animal_tag' => $animal['animal_tag'],
                    'animal_type' => $animal['animal_type'],
                    'breed' => $animal['breed'],
                    'due_date' => $record['next_due_date'],
                    'description' => $record['description']
                ];
            }
        }
    }
}

// Get filters
$search = sanitizeInput($_GET['search'] ?? '');
$animal_type = sanitizeInput($_GET['animal_type'] ?? '');
$status = sanitizeInput($_GET['status'] ?? '');

// Build query
$whereConditions = [$isolationWhere];
$params = [];
$types = '';

if (!empty($search)) {
    $whereConditions[] = "(animal_tag LIKE ? OR animal_type LIKE ? OR breed LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'sss';
}

if (!empty($animal_type)) {
    $whereConditions[] = "animal_type = ?";
    $params[] = $animal_type;
    $types .= 's';
}

if (!empty($status)) {
    $whereConditions[] = "status = ?";
    $params[] = $status;
    $types .= 's';
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
$query = "SELECT * FROM livestock $whereClause ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<style>
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px; }
.stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; }
.stat-card h3 { margin: 0 0 10px 0; font-size: 0.9rem; color: #666; }
.stat-card .value { font-size: 2rem; font-weight: bold; color: #2d7a3e; }
.alert-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-bottom: 20px; border-radius: 4px; }
.alert-box h4 { margin: 0 0 10px 0; color: #856404; }
.alert-item { padding: 8px; border-bottom: 1px solid #f0f0f0; }
.alert-item:last-child { border-bottom: none; }
</style>

<div class="module-header">
    <h2>🐄 Livestock Management</h2>
    <div style="display: flex; gap: 10px;">
        <?php if (canModify()): ?>
        <a href="add.php?type=purchase" class="btn btn-primary">+ Purchase Livestock</a>
        <a href="add.php?type=birth" class="btn btn-success">+ Record Birth</a>
        <?php endif; ?>
    </div>
</div>

<!-- Summary Statistics -->
<div class="stats-grid">
    <div class="stat-card">
        <h3>Total Animals</h3>
        <div class="value"><?php echo number_format($stats['total_count'] ?? 0); ?></div>
        <small><?php echo $stats['total_animals'] ?? 0; ?> records</small>
    </div>
    <div class="stat-card">
        <h3>Active Animals</h3>
        <div class="value" style="color: #28a745;"><?php echo number_format($stats['active_count'] ?? 0); ?></div>
    </div>
    <div class="stat-card">
        <h3>Births This Month</h3>
        <div class="value" style="color: #17a2b8;"><?php echo $birthsThisMonth; ?></div>
    </div>
    <div class="stat-card">
        <h3>Deaths This Month</h3>
        <div class="value" style="color: #dc3545;"><?php echo $deathsThisMonth; ?></div>
    </div>
    <div class="stat-card">
        <h3>Sold This Month</h3>
        <div class="value" style="color: #ffc107;"><?php echo $soldThisMonth; ?></div>
    </div>
</div>

<!-- Upcoming Vaccinations Alert -->
<?php if (count($upcomingVaccinations) > 0): ?>
<div class="alert-box">
    <h4>⚠️ Upcoming Vaccinations (Next 30 Days)</h4>
    <?php foreach ($upcomingVaccinations as $vac): ?>
    <div class="alert-item">
        <strong><?php echo htmlspecialchars($vac['animal_tag']); ?></strong> - 
        <?php echo htmlspecialchars($vac['animal_type']); ?> (<?php echo htmlspecialchars($vac['breed']); ?>) - 
        <?php echo htmlspecialchars($vac['description']); ?> - 
        Due: <?php echo formatDate($vac['due_date']); ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="filters-section">
    <form method="GET" action="index.php" class="filters-form">
        <div class="filter-group">
            <input type="text" name="search" placeholder="Search by tag, type, breed..." 
                value="<?php echo htmlspecialchars($search); ?>" class="form-control">
        </div>
        <div class="filter-group">
            <select name="animal_type" class="form-control">
                <option value="">All Types</option>
                <option value="cow" <?php echo $animal_type === 'cow' ? 'selected' : ''; ?>>Cow</option>
                <option value="buffalo" <?php echo $animal_type === 'buffalo' ? 'selected' : ''; ?>>Buffalo</option>
                <option value="goat" <?php echo $animal_type === 'goat' ? 'selected' : ''; ?>>Goat</option>
                <option value="sheep" <?php echo $animal_type === 'sheep' ? 'selected' : ''; ?>>Sheep</option>
                <option value="chicken" <?php echo $animal_type === 'chicken' ? 'selected' : ''; ?>>Chicken</option>
                <option value="duck" <?php echo $animal_type === 'duck' ? 'selected' : ''; ?>>Duck</option>
            </select>
        </div>
        <div class="filter-group">
            <select name="status" class="form-control">
                <option value="">All Status</option>
                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="sold" <?php echo $status === 'sold' ? 'selected' : ''; ?>>Sold</option>
                <option value="deceased" <?php echo $status === 'deceased' ? 'selected' : ''; ?>>Deceased</option>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary">🔍 Search</button>
        <a href="index.php" class="btn btn-outline">Clear</a>
    </form>
</div>

<!-- Livestock Table -->
<?php if ($result->num_rows > 0): ?>
<div class="table-responsive">
    <table class="data-table">
        <thead>
            <tr>
                <th>Tag ID</th>
                <th>Type</th>
                <th>Breed</th>
                <th>Gender</th>
                <th>Age</th>
                <th>Quantity</th>
                <th>Acquisition</th>
                <th>Status</th>
                <?php if (canModify()): ?>
                <th>Actions</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): 
                $age = '';
                if ($row['date_of_birth']) {
                    $dob = new DateTime($row['date_of_birth']);
                    $now = new DateTime();
                    $diff = $now->diff($dob);
                    if ($diff->y > 0) {
                        $age = $diff->y . ' year' . ($diff->y > 1 ? 's' : '');
                    } elseif ($diff->m > 0) {
                        $age = $diff->m . ' month' . ($diff->m > 1 ? 's' : '');
                    } else {
                        $age = $diff->d . ' day' . ($diff->d > 1 ? 's' : '');
                    }
                }
            ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($row['animal_tag'] ?? ''); ?></strong></td>
                <td><?php echo ucfirst($row['animal_type'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['breed'] ?? ''); ?></td>
                <td><?php echo $row['gender'] ? ucfirst($row['gender']) : 'N/A'; ?></td>
                <td><?php echo $age ?: 'N/A'; ?></td>
                <td><?php echo $row['quantity']; ?></td>
                <td>
                    <?php if ($row['acquisition_type'] === 'birth'): ?>
                        <span class="badge badge-success">🐣 Birth</span>
                    <?php else: ?>
                        <span class="badge badge-info">💰 Purchase</span>
                    <?php endif; ?>
                </td>
                <td><span class="badge badge-<?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                <?php if (canModify()): ?>
                <td class="actions">
                    <a href="view.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">👁️ View</a>
                    <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-edit">Edit</a>
                    <?php if ($row['status'] === 'active'): ?>
                    <a href="record_sale.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-success">💰 Sell</a>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
<div class="no-results">
    <p>No livestock found.</p>
    <?php if (canModify()): ?>
    <a href="add.php?type=purchase" class="btn btn-primary">Purchase Your First Livestock</a>
    <a href="add.php?type=birth" class="btn btn-success">Record a Birth</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php
$stmt->close();
require_once __DIR__ . '/../includes/footer.php';
?>
