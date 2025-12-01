<?php
$pageTitle = 'View Livestock - FarmSaathi';
$currentModule = 'livestock';
require_once __DIR__ . '/../includes/header.php';

$conn = getDBConnection();
$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    setFlashMessage("Invalid livestock ID.", 'error');
    redirect('index.php');
}

// Verify record ownership
verifyRecordOwnership($conn, 'livestock', $id, 'index.php');

// Get livestock data
$isolationWhere = getDataIsolationWhere();
$stmt = $conn->prepare("SELECT * FROM livestock WHERE id = ? AND $isolationWhere");
$stmt->bind_param("i", $id);
$stmt->execute();
$animal = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$animal) {
    setFlashMessage("Livestock not found.", 'error');
    redirect('index.php');
}

// Decode JSON fields
$healthRecords = json_decode($animal['health_records'] ?? '[]', true) ?: [];
$breedingRecords = json_decode($animal['breeding_records'] ?? '[]', true) ?: [];
$productionRecords = json_decode($animal['production'] ?? '[]', true) ?: [];
$expenses = json_decode($animal['expenses'] ?? '[]', true) ?: [];

// Calculate age
$age = '';
if ($animal['date_of_birth']) {
    $dob = new DateTime($animal['date_of_birth']);
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

<style>
.animal-profile { background: white; padding: 25px; border-radius: 8px; margin-bottom: 20px; }
.profile-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px; border-bottom: 2px solid #e9ecef; padding-bottom: 15px; }
.profile-info { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 20px; }
.info-item { padding: 10px; background: #f8f9fa; border-radius: 4px; }
.info-item label { font-weight: 600; color: #666; font-size: 0.9rem; display: block; margin-bottom: 5px; }
.info-item value { font-size: 1.1rem; color: #333; }
.section-title { font-size: 1.2rem; font-weight: 600; color: #2d7a3e; margin: 25px 0 15px 0; border-bottom: 2px solid #e9ecef; padding-bottom: 8px; }
.record-card { background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 10px; border-left: 4px solid #2d7a3e; }
.record-card h4 { margin: 0 0 10px 0; color: #2d7a3e; }
.no-records { text-align: center; padding: 30px; color: #666; background: #f8f9fa; border-radius: 6px; }
</style>

<div class="form-container">
    <div class="profile-header">
        <div>
            <h2>🐄 <?php echo htmlspecialchars($animal['animal_tag']); ?></h2>
            <p style="color: #666; margin: 5px 0 0 0;">
                <?php echo ucfirst($animal['animal_type']); ?> - <?php echo htmlspecialchars($animal['breed']); ?>
            </p>
        </div>
        <div style="display: flex; gap: 10px;">
            <?php if (canModify() && $animal['status'] === 'active'): ?>
            <a href="add_health.php?id=<?php echo $id; ?>" class="btn btn-success">+ Health Record</a>
            <a href="add_production.php?id=<?php echo $id; ?>" class="btn btn-info">+ Production</a>
            <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-primary">Edit</a>
            <?php endif; ?>
            <a href="index.php" class="btn btn-outline">← Back</a>
        </div>
    </div>

    <div class="animal-profile">
        <h3>Basic Information</h3>
        <div class="profile-info">
            <div class="info-item">
                <label>Animal Tag</label>
                <value><?php echo htmlspecialchars($animal['animal_tag'] ?? ''); ?></value>
            </div>
            <div class="info-item">
                <label>Type</label>
                <value><?php echo ucfirst($animal['animal_type'] ?? ''); ?></value>
            </div>
            <div class="info-item">
                <label>Breed</label>
                <value><?php echo htmlspecialchars($animal['breed'] ?? ''); ?></value>
            </div>
            <div class="info-item">
                <label>Gender</label>
                <value><?php echo $animal['gender'] ? ucfirst($animal['gender']) : 'N/A'; ?></value>
            </div>
            <div class="info-item">
                <label>Age</label>
                <value><?php echo $age ?: 'Unknown'; ?></value>
            </div>
            <div class="info-item">
                <label>Quantity</label>
                <value><?php echo $animal['quantity']; ?></value>
            </div>
            <div class="info-item">
                <label>Acquisition</label>
                <value>
                    <?php if ($animal['acquisition_type'] === 'birth'): ?>
                        🐣 Born on Farm
                    <?php else: ?>
                        💰 Purchased
                    <?php endif; ?>
                </value>
            </div>
            <div class="info-item">
                <label>Status</label>
                <value><span class="badge badge-<?php echo $animal['status']; ?>"><?php echo ucfirst($animal['status']); ?></span></value>
            </div>
            <?php if ($animal['current_location']): ?>
            <div class="info-item">
                <label>Location</label>
                <value><?php echo htmlspecialchars($animal['current_location'] ?? ''); ?></value>
            </div>
            <?php endif; ?>
            <?php if ($animal['mother_tag']): ?>
            <div class="info-item">
                <label>Mother</label>
                <value><?php echo htmlspecialchars($animal['mother_tag'] ?? ''); ?></value>
            </div>
            <?php endif; ?>
            <?php if ($animal['acquisition_type'] === 'purchase'): ?>
            <div class="info-item">
                <label>Purchase Date</label>
                <value><?php echo formatDate($animal['purchase_date']); ?></value>
            </div>
            <div class="info-item">
                <label>Purchase Cost</label>
                <value><?php echo formatCurrency($animal['purchase_cost']); ?></value>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if ($animal['notes']): ?>
        <div class="info-item" style="margin-top: 15px;">
            <label>Notes</label>
            <value><?php echo nl2br(htmlspecialchars($animal['notes'] ?? '')); ?></value>
        </div>
        <?php endif; ?>
    </div>

    <!-- Health Records -->
    <div class="section-title">💉 Health Records</div>
    <?php if (count($healthRecords) > 0): ?>
        <?php foreach ($healthRecords as $record): ?>
        <div class="record-card">
            <h4><?php echo htmlspecialchars($record['description'] ?? ''); ?></h4>
            <p><strong>Type:</strong> <?php echo ucfirst($record['type'] ?? ''); ?></p>
            <p><strong>Date:</strong> <?php echo formatDate($record['date']); ?></p>
            <?php if (!empty($record['veterinarian'])): ?>
            <p><strong>Veterinarian:</strong> <?php echo htmlspecialchars($record['veterinarian'] ?? ''); ?></p>
            <?php endif; ?>
            <?php if (!empty($record['cost'])): ?>
            <p><strong>Cost:</strong> <?php echo formatCurrency($record['cost']); ?></p>
            <?php endif; ?>
            <?php if (!empty($record['next_due_date'])): ?>
            <p><strong>Next Due:</strong> <?php echo formatDate($record['next_due_date']); ?></p>
            <?php endif; ?>
            <?php if (!empty($record['notes'])): ?>
            <p><strong>Notes:</strong> <?php echo htmlspecialchars($record['notes'] ?? ''); ?></p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="no-records">No health records yet</div>
    <?php endif; ?>

    <!-- Production Records -->
    <?php if (in_array($animal['animal_type'], ['cow', 'buffalo', 'goat', 'chicken', 'duck'])): ?>
    <div class="section-title">📊 Production Records</div>
    <?php if (count($productionRecords) > 0): ?>
        <?php 
        $totalProduction = 0;
        foreach ($productionRecords as $record): 
            $totalProduction += $record['quantity'];
        ?>
        <div class="record-card">
            <h4><?php echo formatDate($record['date']); ?></h4>
            <p><strong>Type:</strong> <?php echo ucfirst($record['type']); ?></p>
            <p><strong>Quantity:</strong> <?php echo number_format($record['quantity'], 2); ?> <?php echo $record['unit']; ?></p>
            <?php if (!empty($record['morning'])): ?>
            <p><strong>Morning:</strong> <?php echo number_format($record['morning'], 2); ?> <?php echo $record['unit']; ?></p>
            <?php endif; ?>
            <?php if (!empty($record['evening'])): ?>
            <p><strong>Evening:</strong> <?php echo number_format($record['evening'], 2); ?> <?php echo $record['unit']; ?></p>
            <?php endif; ?>
            <?php if (!empty($record['notes'])): ?>
            <p><strong>Notes:</strong> <?php echo htmlspecialchars($record['notes'] ?? ''); ?></p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <div class="info-item" style="margin-top: 15px;">
            <label>Total Production</label>
            <value><?php echo number_format($totalProduction, 2); ?> <?php echo $productionRecords[0]['unit'] ?? ''; ?></value>
        </div>
    <?php else: ?>
        <div class="no-records">No production records yet</div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Breeding Records -->
    <?php if ($animal['gender'] === 'female'): ?>
    <div class="section-title">🐣 Breeding Records</div>
    <?php if (count($breedingRecords) > 0): ?>
        <?php foreach ($breedingRecords as $record): ?>
        <div class="record-card">
            <h4>Breeding on <?php echo formatDate($record['date']); ?></h4>
            <?php if (!empty($record['father_tag'])): ?>
            <p><strong>Father:</strong> <?php echo htmlspecialchars($record['father_tag'] ?? ''); ?></p>
            <?php endif; ?>
            <?php if (!empty($record['expected_delivery'])): ?>
            <p><strong>Expected Delivery:</strong> <?php echo formatDate($record['expected_delivery']); ?></p>
            <?php endif; ?>
            <?php if (!empty($record['actual_delivery'])): ?>
            <p><strong>Actual Delivery:</strong> <?php echo formatDate($record['actual_delivery']); ?></p>
            <p><strong>Offspring:</strong> <?php echo $record['offspring_count']; ?></p>
            <?php endif; ?>
            <?php if (!empty($record['notes'])): ?>
            <p><strong>Notes:</strong> <?php echo htmlspecialchars($record['notes'] ?? ''); ?></p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="no-records">No breeding records yet</div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Expenses -->
    <div class="section-title">💰 Related Expenses</div>
    <?php if (count($expenses) > 0): ?>
        <?php 
        $totalExpenses = 0;
        foreach ($expenses as $expense): 
            $totalExpenses += $expense['amount'];
        ?>
        <div class="record-card">
            <h4><?php echo htmlspecialchars($expense['description'] ?? ''); ?></h4>
            <p><strong>Category:</strong> <?php echo ucfirst($expense['category'] ?? ''); ?></p>
            <p><strong>Date:</strong> <?php echo formatDate($expense['date']); ?></p>
            <p><strong>Amount:</strong> <?php echo formatCurrency($expense['amount']); ?></p>
            <?php if (!empty($expense['notes'])): ?>
            <p><strong>Notes:</strong> <?php echo htmlspecialchars($expense['notes'] ?? ''); ?></p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <div class="info-item" style="margin-top: 15px;">
            <label>Total Expenses</label>
            <value><?php echo formatCurrency($totalExpenses); ?></value>
        </div>
    <?php else: ?>
        <div class="no-records">No expenses recorded yet</div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
