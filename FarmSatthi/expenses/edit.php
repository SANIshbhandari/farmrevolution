<?php
$pageTitle = 'Edit Expense - FarmSaathi';
$currentModule = 'expenses';
require_once __DIR__ . '/../includes/header.php';

requirePermission('manager');

$conn = getDBConnection();
$errors = [];
$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    setFlashMessage("Invalid expense ID.", 'error');
    redirect('index.php');
}

// Verify record ownership
verifyRecordOwnership($conn, 'finance', $id, 'index.php');

$stmt = $conn->prepare("SELECT * FROM finance WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    setFlashMessage("Expense not found.", 'error');
    redirect('index.php');
}

$transaction = $result->fetch_assoc();
$stmt->close();

$transactionType = $transaction['type']; // income or expense
$pageTitle = 'Edit ' . ucfirst($transactionType) . ' - FarmSaathi';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = sanitizeInput($_POST['type'] ?? $transactionType);
    $category = sanitizeInput($_POST['category'] ?? '');
    $amount = sanitizeInput($_POST['amount'] ?? '');
    $transaction_date = sanitizeInput($_POST['transaction_date'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $payment_method = sanitizeInput($_POST['payment_method'] ?? 'cash');
    
    if ($error = validateRequired($category, 'Category')) $errors[] = $error;
    if ($error = validatePositive($amount, 'Amount')) $errors[] = $error;
    if ($error = validateDate($transaction_date, 'Transaction date')) $errors[] = $error;
    
    if (empty($errors)) {
        $stmt = $conn->prepare("
            UPDATE finance 
            SET type = ?, category = ?, amount = ?, transaction_date = ?, description = ?, payment_method = ?
            WHERE id = ?
        ");
        $stmt->bind_param("ssdsssi", $type, $category, $amount, $transaction_date, $description, $payment_method, $id);
        
        if ($stmt->execute()) {
            $stmt->close();
            setFlashMessage(ucfirst($type) . " updated successfully!", 'success');
            redirect('index.php');
        } else {
            $errors[] = "Failed to update transaction. Please try again.";
        }
        $stmt->close();
    }
} else {
    $type = $transaction['type'] ?? 'expense';
    $category = $transaction['category'] ?? '';
    $amount = $transaction['amount'] ?? 0;
    $transaction_date = $transaction['transaction_date'] ?? date('Y-m-d');
    $description = $transaction['description'] ?? '';
    $payment_method = $transaction['payment_method'] ?? '';
}
?>

<div class="form-container">
    <div class="form-header">
        <h2><?php echo $type === 'income' ? '💰 Edit Income' : '💸 Edit Expense'; ?></h2>
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

    <form method="POST" action="edit.php?id=<?php echo $id; ?>" class="data-form">
        <input type="hidden" name="type" value="<?php echo $type; ?>">
        
        <div class="form-row">
            <div class="form-group">
                <label for="type">Type *</label>
                <select id="type" name="type" class="form-control" required>
                    <option value="income" <?php echo $type === 'income' ? 'selected' : ''; ?>>Income</option>
                    <option value="expense" <?php echo $type === 'expense' ? 'selected' : ''; ?>>Expense</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="category">Category *</label>
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

        <div class="form-row">
            <div class="form-group">
                <label for="amount">Amount (₹) *</label>
                <input 
                    type="number" 
                    id="amount" 
                    name="amount" 
                    class="form-control" 
                    value="<?php echo htmlspecialchars($amount); ?>"
                    step="0.01"
                    min="0"
                    required
                >
            </div>

            <div class="form-group">
                <label for="transaction_date">Date *</label>
                <input 
                    type="date" 
                    id="transaction_date" 
                    name="transaction_date" 
                    class="form-control" 
                    value="<?php echo htmlspecialchars($transaction_date); ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="payment_method">Payment Method *</label>
                <select id="payment_method" name="payment_method" class="form-control" required>
                    <option value="cash" <?php echo $payment_method === 'cash' ? 'selected' : ''; ?>>Cash</option>
                    <option value="check" <?php echo $payment_method === 'check' ? 'selected' : ''; ?>>Check</option>
                    <option value="bank_transfer" <?php echo $payment_method === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                    <option value="credit_card" <?php echo $payment_method === 'credit_card' ? 'selected' : ''; ?>>Credit Card</option>
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
            ><?php echo htmlspecialchars($description); ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Update Expense</button>
            <a href="index.php" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
