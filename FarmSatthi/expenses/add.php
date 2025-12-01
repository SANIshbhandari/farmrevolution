<?php
$pageTitle = 'Add Transaction - FarmSaathi';
$currentModule = 'finance';
require_once __DIR__ . '/../includes/header.php';

// Get transaction type from URL (income or expense)
$type = sanitizeInput($_GET['type'] ?? 'expense');
$type = in_array($type, ['income', 'expense']) ? $type : 'expense';
$pageTitle = 'Add ' . ucfirst($type) . ' - FarmSaathi';

requirePermission('manager');

$conn = getDBConnection();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = sanitizeInput($_POST['type'] ?? 'expense');
    $category = sanitizeInput($_POST['category'] ?? '');
    $amount = sanitizeInput($_POST['amount'] ?? '');
    $transaction_date = sanitizeInput($_POST['transaction_date'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $payment_method = sanitizeInput($_POST['payment_method'] ?? 'cash');
    
    if ($error = validateRequired($category, 'Category')) $errors[] = $error;
    if ($error = validatePositive($amount, 'Amount')) $errors[] = $error;
    if ($error = validateDate($transaction_date, 'Transaction date')) $errors[] = $error;
    
    if (empty($errors)) {
        $createdBy = getCreatedByUserId();
        $stmt = $conn->prepare("
            INSERT INTO finance (created_by, type, category, amount, transaction_date, description, payment_method)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("issdsss", $createdBy, $type, $category, $amount, $transaction_date, $description, $payment_method);
        
        if ($stmt->execute()) {
            $stmt->close();
            setFlashMessage(ucfirst($type) . " added successfully!", 'success');
            redirect('index.php');
        } else {
            $errors[] = "Failed to add " . $type . ". Please try again.";
        }
        $stmt->close();
    }
}
?>

<div class="form-container">
    <div class="form-header">
        <h2><?php echo $type === 'income' ? '💰 Add Income' : '💸 Add Expense'; ?></h2>
        <a href="index.php" class="btn btn-outline">← Back to Finance</a>
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

    <form method="POST" action="add.php?type=<?php echo $type; ?>" class="data-form">
        <input type="hidden" name="type" value="<?php echo $type; ?>">
        
        <div class="form-row">
            <div class="form-group">
                <label for="category">Category *</label>
                <select id="category" name="category" class="form-control" required>
                    <option value="">-- Select Category --</option>
                    <?php if ($type === 'income'): ?>
                        <optgroup label="Income Categories">
                            <option value="Crop Sales" <?php echo ($category ?? '') === 'Crop Sales' ? 'selected' : ''; ?>>Crop Sales</option>
                            <option value="Livestock Sales" <?php echo ($category ?? '') === 'Livestock Sales' ? 'selected' : ''; ?>>Livestock Sales</option>
                            <option value="Dairy Products" <?php echo ($category ?? '') === 'Dairy Products' ? 'selected' : ''; ?>>Dairy Products</option>
                            <option value="Poultry Products" <?php echo ($category ?? '') === 'Poultry Products' ? 'selected' : ''; ?>>Poultry Products</option>
                            <option value="Equipment Rental" <?php echo ($category ?? '') === 'Equipment Rental' ? 'selected' : ''; ?>>Equipment Rental</option>
                            <option value="Services" <?php echo ($category ?? '') === 'Services' ? 'selected' : ''; ?>>Services</option>
                            <option value="Government Subsidies" <?php echo ($category ?? '') === 'Government Subsidies' ? 'selected' : ''; ?>>Government Subsidies</option>
                            <option value="Other Income" <?php echo ($category ?? '') === 'Other Income' ? 'selected' : ''; ?>>Other Income</option>
                        </optgroup>
                    <?php else: ?>
                        <optgroup label="Expense Categories">
                            <option value="Seeds & Planting" <?php echo ($category ?? '') === 'Seeds & Planting' ? 'selected' : ''; ?>>Seeds & Planting</option>
                            <option value="Fertilizers & Pesticides" <?php echo ($category ?? '') === 'Fertilizers & Pesticides' ? 'selected' : ''; ?>>Fertilizers & Pesticides</option>
                            <option value="Animal Feed" <?php echo ($category ?? '') === 'Animal Feed' ? 'selected' : ''; ?>>Animal Feed</option>
                            <option value="Veterinary Services" <?php echo ($category ?? '') === 'Veterinary Services' ? 'selected' : ''; ?>>Veterinary Services</option>
                            <option value="Labor & Wages" <?php echo ($category ?? '') === 'Labor & Wages' ? 'selected' : ''; ?>>Labor & Wages</option>
                            <option value="Equipment & Machinery" <?php echo ($category ?? '') === 'Equipment & Machinery' ? 'selected' : ''; ?>>Equipment & Machinery</option>
                            <option value="Fuel & Transportation" <?php echo ($category ?? '') === 'Fuel & Transportation' ? 'selected' : ''; ?>>Fuel & Transportation</option>
                            <option value="Utilities" <?php echo ($category ?? '') === 'Utilities' ? 'selected' : ''; ?>>Utilities (Water, Electricity)</option>
                            <option value="Rent & Lease" <?php echo ($category ?? '') === 'Rent & Lease' ? 'selected' : ''; ?>>Rent & Lease</option>
                            <option value="Insurance" <?php echo ($category ?? '') === 'Insurance' ? 'selected' : ''; ?>>Insurance</option>
                            <option value="Taxes & Fees" <?php echo ($category ?? '') === 'Taxes & Fees' ? 'selected' : ''; ?>>Taxes & Fees</option>
                            <option value="Maintenance & Repairs" <?php echo ($category ?? '') === 'Maintenance & Repairs' ? 'selected' : ''; ?>>Maintenance & Repairs</option>
                            <option value="Marketing & Sales" <?php echo ($category ?? '') === 'Marketing & Sales' ? 'selected' : ''; ?>>Marketing & Sales</option>
                            <option value="Other Expenses" <?php echo ($category ?? '') === 'Other Expenses' ? 'selected' : ''; ?>>Other Expenses</option>
                        </optgroup>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="amount">Amount (रू) *</label>
                <input 
                    type="number" 
                    id="amount" 
                    name="amount" 
                    class="form-control" 
                    value="<?php echo htmlspecialchars($amount ?? ''); ?>"
                    step="0.01"
                    min="0"
                    placeholder="0.00"
                    required
                >
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="transaction_date">Date *</label>
                <input 
                    type="date" 
                    id="transaction_date" 
                    name="transaction_date" 
                    class="form-control" 
                    value="<?php echo htmlspecialchars($transaction_date ?? date('Y-m-d')); ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="payment_method">Payment Method *</label>
                <select id="payment_method" name="payment_method" class="form-control" required>
                    <option value="cash" <?php echo ($payment_method ?? 'cash') === 'cash' ? 'selected' : ''; ?>>Cash</option>
                    <option value="bank" <?php echo ($payment_method ?? '') === 'bank' ? 'selected' : ''; ?>>Bank</option>
                    <option value="other" <?php echo ($payment_method ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="description">Description *</label>
            <textarea 
                id="description" 
                name="description" 
                class="form-control" 
                rows="4"
                required
            ><?php echo htmlspecialchars($description ?? ''); ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn <?php echo $type === 'income' ? 'btn-success' : 'btn-primary'; ?>">
                <?php echo $type === 'income' ? '💰 Add Income' : '💸 Add Expense'; ?>
            </button>
            <a href="index.php" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
