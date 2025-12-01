<?php
$pageTitle = 'Stock Movement - FarmSaathi';
$currentModule = 'inventory';
require_once __DIR__ . '/../includes/header.php';

requirePermission('manager');

$conn = getDBConnection();
$errors = [];

// Get inventory item ID
$inventory_id = intval($_GET['id'] ?? 0);

// Fetch inventory item
$stmt = $conn->prepare("SELECT * FROM inventory WHERE id = ? AND " . getDataIsolationWhere());
$stmt->bind_param("i", $inventory_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$item) {
    setFlashMessage("Inventory item not found.", 'error');
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $movement_type = sanitizeInput($_POST['movement_type'] ?? '');
    $quantity = floatval($_POST['quantity'] ?? 0);
    $reason = sanitizeInput($_POST['reason'] ?? '');
    $reference_number = sanitizeInput($_POST['reference_number'] ?? '');
    $notes = sanitizeInput($_POST['notes'] ?? '');
    $movement_date = sanitizeInput($_POST['movement_date'] ?? date('Y-m-d'));
    
    // Validation
    if (empty($movement_type) || !in_array($movement_type, ['in', 'out'])) {
        $errors[] = "Please select movement type.";
    }
    if ($quantity <= 0) {
        $errors[] = "Quantity must be greater than 0.";
    }
    if ($movement_type === 'out' && $quantity > $item['quantity']) {
        $errors[] = "Cannot remove more than available stock (" . $item['quantity'] . " " . $item['unit'] . ").";
    }
    if (empty($reason)) {
        $errors[] = "Please provide a reason for this movement.";
    }
    
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            // Record stock movement
            $stmt = $conn->prepare("
                INSERT INTO stock_movements (inventory_id, movement_type, quantity, reason, reference_number, notes, movement_date, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $created_by = getCurrentUserId();
            $stmt->bind_param("isdssssi", $inventory_id, $movement_type, $quantity, $reason, $reference_number, $notes, $movement_date, $created_by);
            $stmt->execute();
            $stmt->close();
            
            // Update inventory quantity
            $new_quantity = $movement_type === 'in' ? 
                $item['quantity'] + $quantity : 
                $item['quantity'] - $quantity;
            
            $stmt = $conn->prepare("UPDATE inventory SET quantity = ? WHERE id = ?");
            $stmt->bind_param("di", $new_quantity, $inventory_id);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            
            setFlashMessage("Stock movement recorded successfully! New quantity: " . number_format($new_quantity, 2) . " " . $item['unit'], 'success');
            redirect('index.php');
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Failed to record stock movement: " . $e->getMessage();
        }
    }
}
?>

<div class="form-container">
    <div class="form-header">
        <h2>📊 Record Stock Movement</h2>
        <a href="index.php" class="btn btn-outline">← Back to Inventory</a>
    </div>

    <!-- Item Info Card -->
    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #2d7a3e;">
        <h3 style="margin: 0 0 10px 0; color: #2d7a3e;">📦 <?php echo htmlspecialchars($item['item_name']); ?></h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <div>
                <strong>Type:</strong> <?php echo ucfirst($item['item_type']); ?>
            </div>
            <div>
                <strong>Category:</strong> <?php echo htmlspecialchars($item['category']); ?>
            </div>
            <div>
                <strong>Current Stock:</strong> 
                <span style="font-size: 1.2em; font-weight: bold; color: #2d7a3e;">
                    <?php echo number_format($item['quantity'], 2); ?> <?php echo $item['unit']; ?>
                </span>
            </div>
            <?php if ($item['reorder_level']): ?>
            <div>
                <strong>Reorder Level:</strong> <?php echo number_format($item['reorder_level'], 2); ?> <?php echo $item['unit']; ?>
            </div>
            <?php endif; ?>
        </div>
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
                <label for="movement_type">Movement Type *</label>
                <select id="movement_type" name="movement_type" class="form-control" required onchange="updateReasonOptions()">
                    <option value="">-- Select Type --</option>
                    <option value="in" <?php echo ($movement_type ?? '') === 'in' ? 'selected' : ''; ?>>📥 Stock IN (Add)</option>
                    <option value="out" <?php echo ($movement_type ?? '') === 'out' ? 'selected' : ''; ?>>📤 Stock OUT (Remove)</option>
                </select>
            </div>

            <div class="form-group">
                <label for="quantity">Quantity * (<?php echo $item['unit']; ?>)</label>
                <input 
                    type="number" 
                    id="quantity" 
                    name="quantity" 
                    class="form-control" 
                    value="<?php echo htmlspecialchars($quantity ?? ''); ?>"
                    step="0.01"
                    min="0.01"
                    required
                >
                <small class="form-text">Available: <?php echo number_format($item['quantity'], 2); ?> <?php echo $item['unit']; ?></small>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="reason">Reason *</label>
                <select id="reason" name="reason" class="form-control" required>
                    <option value="">-- Select Reason --</option>
                    <optgroup label="Stock IN Reasons" id="in-reasons" style="display: none;">
                        <option value="Purchase">Purchase</option>
                        <option value="Return">Return from Use</option>
                        <option value="Donation">Donation/Gift</option>
                        <option value="Production">Farm Production</option>
                        <option value="Adjustment">Stock Adjustment (Increase)</option>
                        <option value="Other">Other</option>
                    </optgroup>
                    <optgroup label="Stock OUT Reasons" id="out-reasons" style="display: none;">
                        <option value="Usage">Normal Usage</option>
                        <option value="Sale">Sale</option>
                        <option value="Wastage">Wastage/Spoilage</option>
                        <option value="Damaged">Damaged</option>
                        <option value="Expired">Expired</option>
                        <option value="Theft">Theft/Loss</option>
                        <option value="Donation">Donation/Gift</option>
                        <option value="Adjustment">Stock Adjustment (Decrease)</option>
                        <option value="Other">Other</option>
                    </optgroup>
                </select>
            </div>

            <div class="form-group">
                <label for="movement_date">Date *</label>
                <input 
                    type="date" 
                    id="movement_date" 
                    name="movement_date" 
                    class="form-control" 
                    value="<?php echo htmlspecialchars($movement_date ?? date('Y-m-d')); ?>"
                    max="<?php echo date('Y-m-d'); ?>"
                    required
                >
            </div>
        </div>

        <div class="form-group">
            <label for="reference_number">Reference Number</label>
            <input 
                type="text" 
                id="reference_number" 
                name="reference_number" 
                class="form-control" 
                value="<?php echo htmlspecialchars($reference_number ?? ''); ?>"
                placeholder="e.g., Invoice #, PO #, Receipt #"
            >
            <small class="form-text">Optional: Invoice number, receipt number, etc.</small>
        </div>

        <div class="form-group">
            <label for="notes">Notes</label>
            <textarea 
                id="notes" 
                name="notes" 
                class="form-control" 
                rows="3"
                placeholder="Additional details about this stock movement..."
            ><?php echo htmlspecialchars($notes ?? ''); ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">✅ Record Movement</button>
            <a href="index.php" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<script>
function updateReasonOptions() {
    const movementType = document.getElementById('movement_type').value;
    const inReasons = document.getElementById('in-reasons');
    const outReasons = document.getElementById('out-reasons');
    const reasonSelect = document.getElementById('reason');
    
    // Reset selection
    reasonSelect.value = '';
    
    // Show/hide appropriate reason groups
    if (movementType === 'in') {
        inReasons.style.display = 'block';
        outReasons.style.display = 'none';
    } else if (movementType === 'out') {
        inReasons.style.display = 'none';
        outReasons.style.display = 'block';
    } else {
        inReasons.style.display = 'none';
        outReasons.style.display = 'none';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateReasonOptions();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
