<?php
$pageTitle = 'Edit Equipment - FarmSaathi';
$currentModule = 'equipment';
require_once __DIR__ . '/../includes/header.php';

requirePermission('manager');

$conn = getDBConnection();
$errors = [];
$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    setFlashMessage("Invalid equipment ID.", 'error');
    redirect('index.php');
}

// Verify record ownership
verifyRecordOwnership($conn, 'equipment', $id, 'index.php');

$stmt = $conn->prepare("SELECT * FROM equipment WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    setFlashMessage("Equipment not found.", 'error');
    redirect('index.php');
}

$equipment = $result->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $equipment_name = sanitizeInput($_POST['equipment_name'] ?? '');
    $type = sanitizeInput($_POST['type'] ?? '');
    $purchase_date = sanitizeInput($_POST['purchase_date'] ?? '');
    $last_maintenance = sanitizeInput($_POST['last_maintenance'] ?? '');
    $next_maintenance = sanitizeInput($_POST['next_maintenance'] ?? '');
    $condition = sanitizeInput($_POST['condition'] ?? 'good');
    $value = sanitizeInput($_POST['value'] ?? '');
    $notes = sanitizeInput($_POST['notes'] ?? '');
    
    if ($error = validateRequired($equipment_name, 'Equipment name')) $errors[] = $error;
    if ($error = validateRequired($type, 'Type')) $errors[] = $error;
    if ($error = validateDate($purchase_date, 'Purchase date')) $errors[] = $error;
    if (!empty($last_maintenance) && ($error = validateDate($last_maintenance, 'Last maintenance'))) $errors[] = $error;
    if (!empty($next_maintenance) && ($error = validateDate($next_maintenance, 'Next maintenance'))) $errors[] = $error;
    if ($error = validatePositive($value, 'Value')) $errors[] = $error;
    
    if (empty($errors)) {
        $last_maintenance = !empty($last_maintenance) ? $last_maintenance : null;
        $next_maintenance = !empty($next_maintenance) ? $next_maintenance : null;
        
        $stmt = $conn->prepare("
            UPDATE equipment 
            SET equipment_name = ?, type = ?, purchase_date = ?, last_maintenance = ?, 
                next_maintenance = ?, `condition` = ?, value = ?, notes = ?
            WHERE id = ?
        ");
        $stmt->bind_param("ssssssdsi", $equipment_name, $type, $purchase_date, $last_maintenance, $next_maintenance, $condition, $value, $notes, $id);
        
        if ($stmt->execute()) {
            $stmt->close();
            setFlashMessage("Equipment updated successfully!", 'success');
            redirect('index.php');
        } else {
            $errors[] = "Failed to update equipment. Please try again.";
        }
        $stmt->close();
    }
} else {
    $equipment_name = $equipment['equipment_name'] ?? '';
    $type = $equipment['type'] ?? '';
    $purchase_date = $equipment['purchase_date'] ?? date('Y-m-d');
    $last_maintenance = $equipment['last_maintenance'] ?? '';
    $next_maintenance = $equipment['next_maintenance'] ?? '';
    $condition = $equipment['condition'] ?? 'good';
    $value = $equipment['value'] ?? 0;
    $notes = $equipment['notes'] ?? '';
}
?>

<div class="form-container">
    <div class="form-header">
        <h2>Edit Equipment</h2>
        <a href="index.php" class="btn btn-outline">← Back to Equipment</a>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST" action="edit.php?id=<?php echo $id; ?>" class="data-form">
        <div class="form-row">
            <div class="form-group">
                <label for="equipment_name">Equipment Name *</label>
                <input 
                    type="text" 
                    id="equipment_name" 
                    name="equipment_name" 
                    class="form-control" 
                    value="<?php echo htmlspecialchars($equipment_name); ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="type">Type *</label>
                <input 
                    type="text" 
                    id="type" 
                    name="type" 
                    class="form-control" 
                    value="<?php echo htmlspecialchars($type); ?>"
                    required
                >
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="purchase_date">Purchase Date *</label>
                <input 
                    type="date" 
                    id="purchase_date" 
                    name="purchase_date" 
                    class="form-control" 
                    value="<?php echo htmlspecialchars($purchase_date); ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="value">Value ($) *</label>
                <input 
                    type="number" 
                    id="value" 
                    name="value" 
                    class="form-control" 
                    value="<?php echo htmlspecialchars($value); ?>"
                    step="0.01"
                    min="0"
                    required
                >
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="last_maintenance">Last Maintenance Date</label>
                <input 
                    type="date" 
                    id="last_maintenance" 
                    name="last_maintenance" 
                    class="form-control" 
                    value="<?php echo htmlspecialchars($last_maintenance ?? ''); ?>"
                >
            </div>

            <div class="form-group">
                <label for="next_maintenance">Next Maintenance Date</label>
                <input 
                    type="date" 
                    id="next_maintenance" 
                    name="next_maintenance" 
                    class="form-control" 
                    value="<?php echo htmlspecialchars($next_maintenance ?? ''); ?>"
                >
            </div>
        </div>

        <div class="form-group">
            <label for="condition">Condition *</label>
            <select id="condition" name="condition" class="form-control" required>
                <option value="excellent" <?php echo $condition === 'excellent' ? 'selected' : ''; ?>>Excellent</option>
                <option value="good" <?php echo $condition === 'good' ? 'selected' : ''; ?>>Good</option>
                <option value="fair" <?php echo $condition === 'fair' ? 'selected' : ''; ?>>Fair</option>
                <option value="poor" <?php echo $condition === 'poor' ? 'selected' : ''; ?>>Poor</option>
                <option value="needs_repair" <?php echo $condition === 'needs_repair' ? 'selected' : ''; ?>>Needs Repair</option>
            </select>
        </div>

        <div class="form-group">
            <label for="notes">Notes</label>
            <textarea 
                id="notes" 
                name="notes" 
                class="form-control" 
                rows="4"
            ><?php echo htmlspecialchars($notes ?? ''); ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Update Equipment</button>
            <a href="index.php" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
