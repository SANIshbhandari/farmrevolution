<?php
$pageTitle = 'Edit Livestock - FarmSaathi';
$currentModule = 'livestock';
require_once __DIR__ . '/../includes/header.php';

requirePermission('manager');

$conn = getDBConnection();
$errors = [];
$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    setFlashMessage("Invalid livestock ID.", 'error');
    redirect('index.php');
}

// Verify and get animal
verifyRecordOwnership($conn, 'livestock', $id, 'index.php');
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $breed = sanitizeInput($_POST['breed'] ?? '');
    $quantity = intval($_POST['quantity'] ?? 1);
    $current_location = sanitizeInput($_POST['current_location'] ?? '');
    $status = sanitizeInput($_POST['status'] ?? 'active');
    $notes = sanitizeInput($_POST['notes'] ?? '');
    
    if (empty($breed)) $errors[] = "Breed is required.";
    if ($quantity <= 0) $errors[] = "Quantity must be greater than 0.";
    
    if (empty($errors)) {
        $stmt = $conn->prepare("
            UPDATE livestock 
            SET breed = ?, quantity = ?, current_location = ?, status = ?, notes = ?
            WHERE id = ?
        ");
        $stmt->bind_param("sisssi", $breed, $quantity, $current_location, $status, $notes, $id);
        
        if ($stmt->execute()) {
            $stmt->close();
            logActivity('update', 'livestock', "Updated {$animal['animal_tag']}");
            setFlashMessage("Livestock updated successfully!", 'success');
            redirect("view.php?id=$id");
        } else {
            $errors[] = "Failed to update livestock.";
            $stmt->close();
        }
    }
} else {
    // Pre-fill form
    $breed = $animal['breed'] ?? '';
    $quantity = $animal['quantity'] ?? 1;
    $current_location = $animal['current_location'] ?? '';
    $status = $animal['status'] ?? 'active';
    $notes = $animal['notes'] ?? '';
}
?>

<div class="form-container">
    <div class="form-header">
        <h2>Edit Livestock - <?php echo htmlspecialchars($animal['animal_tag']); ?></h2>
        <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline">← Back</a>
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

    <form method="POST" class="data-form">
        <div class="form-row">
            <div class="form-group">
                <label for="breed">Breed *</label>
                <input type="text" id="breed" name="breed" class="form-control" 
                    value="<?php echo htmlspecialchars($breed); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="quantity">Quantity *</label>
                <input type="number" id="quantity" name="quantity" class="form-control" 
                    value="<?php echo $quantity; ?>" min="1" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="current_location">Current Location</label>
                <input type="text" id="current_location" name="current_location" class="form-control" 
                    value="<?php echo htmlspecialchars($current_location ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="status">Status *</label>
                <select id="status" name="status" class="form-control" required>
                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="sold" <?php echo $status === 'sold' ? 'selected' : ''; ?>>Sold</option>
                    <option value="deceased" <?php echo $status === 'deceased' ? 'selected' : ''; ?>>Deceased</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" class="form-control" rows="3"><?php echo htmlspecialchars($notes ?? ''); ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Update Livestock</button>
            <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
