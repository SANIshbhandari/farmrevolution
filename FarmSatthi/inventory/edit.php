<?php
$pageTitle = 'Edit Inventory Item - FarmSaathi';
$currentModule = 'inventory';
require_once __DIR__ . '/../includes/header.php';

requirePermission('manager');

$conn = getDBConnection();
$errors = [];
$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    setFlashMessage("Invalid inventory ID.", 'error');
    redirect('index.php');
}

// Verify record ownership
verifyRecordOwnership($conn, 'inventory', $id, 'index.php');

$stmt = $conn->prepare("SELECT * FROM inventory WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    setFlashMessage("Inventory item not found.", 'error');
    redirect('index.php');
}

$item = $result->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_type = $item['item_type']; // Keep original type
    $item_name = sanitizeInput($_POST['item_name'] ?? '');
    $category = sanitizeInput($_POST['category'] ?? '');
    $quantity = sanitizeInput($_POST['quantity'] ?? '');
    $unit = sanitizeInput($_POST['unit'] ?? '');
    $reorder_level = sanitizeInput($_POST['reorder_level'] ?? '');
    $purchase_date = sanitizeInput($_POST['purchase_date'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $salary = sanitizeInput($_POST['salary'] ?? '');
    $hire_date = sanitizeInput($_POST['hire_date'] ?? '');
    $status = sanitizeInput($_POST['status'] ?? 'active');
    
    if ($error = validateRequired($item_name, 'Item name')) $errors[] = $error;
    if ($error = validateRequired($category, 'Category')) $errors[] = $error;
    
    if (empty($errors)) {
        // Convert empty strings to null
        $quantity_val = $quantity !== '' ? $quantity : null;
        $unit_val = $unit !== '' ? $unit : null;
        $reorder_level_val = $reorder_level !== '' ? $reorder_level : null;
        $purchase_date_val = $purchase_date !== '' ? $purchase_date : null;
        $phone_val = $phone !== '' ? $phone : null;
        $salary_val = $salary !== '' ? $salary : null;
        $hire_date_val = $hire_date !== '' ? $hire_date : null;
        
        $stmt = $conn->prepare("
            UPDATE inventory 
            SET item_name = ?, category = ?, quantity = ?, unit = ?, reorder_level = ?,
                purchase_date = ?, phone = ?, salary = ?, hire_date = ?, status = ?
            WHERE id = ?
        ");
        $stmt->bind_param("ssdsdsdsdsi", 
            $item_name, $category, $quantity_val, $unit_val, $reorder_level_val,
            $purchase_date_val, $phone_val, $salary_val, $hire_date_val, $status, $id
        );
        
        if ($stmt->execute()) {
            $stmt->close();
            setFlashMessage("Item updated successfully!", 'success');
            redirect('index.php?item_type=' . $item_type);
        } else {
            $errors[] = "Failed to update item. Please try again.";
        }
        $stmt->close();
    }
} else {
    $item_name = $item['item_name'] ?? '';
    $category = $item['category'] ?? '';
    $quantity = $item['quantity'] ?? '';
    $unit = $item['unit'] ?? '';
    $reorder_level = $item['reorder_level'] ?? '';
    $purchase_date = $item['purchase_date'] ?? '';
    $phone = $item['phone'] ?? '';
    $salary = $item['salary'] ?? '';
    $hire_date = $item['hire_date'] ?? '';
    $status = $item['status'] ?? 'active';
}
?>

<div class="form-container">
    <div class="form-header">
        <h2>Edit <?php echo ucfirst($item['item_type']); ?></h2>
        <a href="index.php?item_type=<?php echo $item['item_type']; ?>" class="btn btn-outline">← Back to <?php echo ucfirst($item['item_type']); ?>s</a>
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
                <label for="item_name"><?php echo $item['item_type'] === 'employee' ? 'Employee Name' : 'Item Name'; ?> *</label>
                <input 
                    type="text" 
                    id="item_name" 
                    name="item_name" 
                    class="form-control" 
                    value="<?php echo htmlspecialchars($item_name); ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="category"><?php echo $item['item_type'] === 'employee' ? 'Role/Position' : 'Category'; ?> *</label>
                <input 
                    type="text" 
                    id="category" 
                    name="category" 
                    class="form-control" 
                    value="<?php echo htmlspecialchars($category); ?>"
                    required
                >
            </div>
        </div>

        <?php if ($item['item_type'] === 'supply'): ?>
        <div class="form-row">
            <div class="form-group">
                <label for="quantity">Quantity</label>
                <input 
                    type="number" 
                    id="quantity" 
                    name="quantity" 
                    class="form-control" 
                    value="<?php echo htmlspecialchars($quantity); ?>"
                    step="0.01"
                    min="0"
                >
            </div>

            <div class="form-group">
                <label for="unit">Unit</label>
                <input 
                    type="text" 
                    id="unit" 
                    name="unit" 
                    class="form-control" 
                    value="<?php echo htmlspecialchars($unit); ?>"
                    placeholder="e.g., kg, liters, pieces"
                >
            </div>
        </div>

        <div class="form-group">
            <label for="reorder_level">Reorder Level</label>
            <input 
                type="number" 
                id="reorder_level" 
                name="reorder_level" 
                class="form-control" 
                value="<?php echo htmlspecialchars($reorder_level); ?>"
                step="0.01"
                min="0"
            >
            <small class="form-text">Alert will be shown when quantity falls below this level</small>
        </div>
        <?php elseif ($item['item_type'] === 'equipment'): ?>
        <div class="form-group">
            <label for="purchase_date">Purchase Date</label>
            <input 
                type="date" 
                id="purchase_date" 
                name="purchase_date" 
                class="form-control" 
                value="<?php echo htmlspecialchars($purchase_date); ?>"
            >
        </div>
        <?php elseif ($item['item_type'] === 'employee'): ?>
        <div class="form-row">
            <div class="form-group">
                <label for="phone">Phone</label>
                <input 
                    type="text" 
                    id="phone" 
                    name="phone" 
                    class="form-control" 
                    value="<?php echo htmlspecialchars($phone); ?>"
                    placeholder="Contact number"
                >
            </div>

            <div class="form-group">
                <label for="salary">Salary</label>
                <input 
                    type="number" 
                    id="salary" 
                    name="salary" 
                    class="form-control" 
                    value="<?php echo htmlspecialchars($salary); ?>"
                    step="0.01"
                    min="0"
                >
            </div>
        </div>

        <div class="form-group">
            <label for="hire_date">Hire Date</label>
            <input 
                type="date" 
                id="hire_date" 
                name="hire_date" 
                class="form-control" 
                value="<?php echo htmlspecialchars($hire_date); ?>"
            >
        </div>
        <?php endif; ?>

        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status" class="form-control">
                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Update</button>
            <a href="index.php?item_type=<?php echo $item['item_type']; ?>" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
