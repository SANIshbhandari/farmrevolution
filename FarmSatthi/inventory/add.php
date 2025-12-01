<?php
$pageTitle = 'Add Inventory Item - FarmSaathi';
$currentModule = 'inventory';
require_once __DIR__ . '/../includes/header.php';

requirePermission('manager');

$conn = getDBConnection();
$errors = [];

// Get type from URL parameter or POST
$default_type = sanitizeInput($_GET['type'] ?? 'supply');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_type = sanitizeInput($_POST['item_type'] ?? $default_type); // supply, equipment, employee
    $item_name = sanitizeInput($_POST['item_name'] ?? '');
    $category = sanitizeInput($_POST['category'] ?? '');
    $quantity = sanitizeInput($_POST['quantity'] ?? '');
    $unit = sanitizeInput($_POST['unit'] ?? '');
    $reorder_level = sanitizeInput($_POST['reorder_level'] ?? '');
    $purchase_date = sanitizeInput($_POST['purchase_date'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $salary = sanitizeInput($_POST['salary'] ?? '');
    $hire_date = sanitizeInput($_POST['hire_date'] ?? '');
    
    if ($error = validateRequired($item_name, 'Item name')) $errors[] = $error;
    if ($error = validateRequired($category, 'Category')) $errors[] = $error;
    
    if (empty($errors)) {
        $createdBy = getCreatedByUserId();
        $status = 'active';
        
        // Convert empty strings to null for proper database storage
        $quantity_val = $quantity !== '' ? $quantity : null;
        $unit_val = $unit !== '' ? $unit : null;
        $reorder_level_val = $reorder_level !== '' ? $reorder_level : null;
        $purchase_date_val = $purchase_date !== '' ? $purchase_date : null;
        $phone_val = $phone !== '' ? $phone : null;
        $salary_val = $salary !== '' ? $salary : null;
        $hire_date_val = $hire_date !== '' ? $hire_date : null;
        
        // Check if table uses created_by or user_id
        $columnCheck = $conn->query("SHOW COLUMNS FROM inventory LIKE 'created_by'");
        $useCreatedBy = $columnCheck && $columnCheck->num_rows > 0;
        $userColumn = $useCreatedBy ? 'created_by' : 'user_id';
        
        $stmt = $conn->prepare("
            INSERT INTO inventory ($userColumn, item_type, item_name, category, quantity, unit, reorder_level, purchase_date, phone, salary, hire_date, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isssdsdssdss", 
            $createdBy, $item_type, $item_name, $category, 
            $quantity_val, $unit_val, $reorder_level_val, $purchase_date_val, 
            $phone_val, $salary_val, $hire_date_val, $status
        );
        
        if ($stmt->execute()) {
            $stmt->close();
            setFlashMessage("Inventory item added successfully!", 'success');
            redirect('index.php');
        } else {
            $errors[] = "Failed to add inventory item: " . $conn->error;
            $stmt->close();
        }
    }
}
?>

<div class="form-container">
    <div class="form-header">
        <h2>Add New Inventory Item</h2>
        <a href="index.php" class="btn btn-outline">← Back to Inventory</a>
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

    <form method="POST" action="add.php?type=<?php echo htmlspecialchars($default_type); ?>" class="data-form">
        <div class="form-row">
            <div class="form-group">
                <label for="item_type">Item Type *</label>
                <select id="item_type" name="item_type" class="form-control" required onchange="toggleFields()">
                    <option value="supply" <?php echo $default_type === 'supply' ? 'selected' : ''; ?>>Supply (Seeds, Fertilizer, etc.)</option>
                    <option value="equipment" <?php echo $default_type === 'equipment' ? 'selected' : ''; ?>>Equipment (Tractor, Tools, etc.)</option>
                    <option value="employee" <?php echo $default_type === 'employee' ? 'selected' : ''; ?>>Employee</option>
                </select>
            </div>

            <div class="form-group">
                <label for="item_name">Name *</label>
                <input 
                    type="text" 
                    id="item_name" 
                    name="item_name" 
                    class="form-control" 
                    value="<?php echo htmlspecialchars($item_name ?? ''); ?>"
                    placeholder="Item/Employee name"
                    required
                >
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="category">Category/Role *</label>
                <input 
                    type="text" 
                    id="category" 
                    name="category" 
                    class="form-control" 
                    value="<?php echo htmlspecialchars($category ?? ''); ?>"
                    placeholder="e.g., Seeds, Fertilizer, Farm Worker"
                    required
                >
            </div>
        </div>

        <div id="supply_fields" style="display:<?php echo $default_type === 'supply' ? 'block' : 'none'; ?>;">
            <div class="form-row">
                <div class="form-group">
                    <label for="quantity">Quantity</label>
                    <input 
                        type="number" 
                        id="quantity" 
                        name="quantity" 
                        class="form-control" 
                        value="<?php echo htmlspecialchars($quantity ?? ''); ?>"
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
                        value="<?php echo htmlspecialchars($unit ?? ''); ?>"
                        placeholder="e.g., kg, liters, pieces"
                    >
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="reorder_level">Reorder Level (Alert Threshold)</label>
                    <input 
                        type="number" 
                        id="reorder_level" 
                        name="reorder_level" 
                        class="form-control" 
                        value="<?php echo htmlspecialchars($reorder_level ?? ''); ?>"
                        step="0.01"
                        min="0"
                        placeholder="Alert when quantity falls below this"
                    >
                    <small class="form-text">You'll get an alert when stock falls below this level</small>
                </div>
            </div>
        </div>

        <div id="equipment_fields" style="display:<?php echo $default_type === 'equipment' ? 'block' : 'none'; ?>;">
            <div class="form-row">
                <div class="form-group">
                    <label for="purchase_date">Purchase Date</label>
                    <input type="date" id="purchase_date" name="purchase_date" class="form-control">
                </div>
            </div>
        </div>

        <div id="employee_fields" style="display:<?php echo $default_type === 'employee' ? 'block' : 'none'; ?>;">
            <div class="form-row">
                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="text" id="phone" name="phone" class="form-control" placeholder="Contact number">
                </div>
                <div class="form-group">
                    <label for="salary">Salary</label>
                    <input type="number" id="salary" name="salary" class="form-control" step="0.01" min="0">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="hire_date">Hire Date</label>
                    <input type="date" id="hire_date" name="hire_date" class="form-control">
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Add Item</button>
            <a href="index.php" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<script>
function toggleFields() {
    const type = document.getElementById('item_type').value;
    document.getElementById('supply_fields').style.display = type === 'supply' ? 'block' : 'none';
    document.getElementById('equipment_fields').style.display = type === 'equipment' ? 'block' : 'none';
    document.getElementById('employee_fields').style.display = type === 'employee' ? 'block' : 'none';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
