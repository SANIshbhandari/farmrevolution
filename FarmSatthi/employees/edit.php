<?php
$pageTitle = 'Edit Employee - FarmSaathi';
$currentModule = 'employees';
require_once __DIR__ . '/../includes/header.php';

requirePermission('manager');

$conn = getDBConnection();
$errors = [];
$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    setFlashMessage("Invalid employee ID.", 'error');
    redirect('index.php');
}

// Verify record ownership
verifyRecordOwnership($conn, 'employees', $id, 'index.php');

$stmt = $conn->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    setFlashMessage("Employee not found.", 'error');
    redirect('index.php');
}

$employee = $result->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name'] ?? '');
    $role = sanitizeInput($_POST['role'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $salary = sanitizeInput($_POST['salary'] ?? '');
    $hire_date = sanitizeInput($_POST['hire_date'] ?? '');
    $status = sanitizeInput($_POST['status'] ?? 'active');
    $notes = sanitizeInput($_POST['notes'] ?? '');
    
    if ($error = validateRequired($name, 'Name')) $errors[] = $error;
    if ($error = validateRequired($role, 'Role')) $errors[] = $error;
    if ($error = validateRequired($phone, 'Phone')) $errors[] = $error;
    if (!empty($email) && ($error = validateEmail($email))) $errors[] = $error;
    if ($error = validatePositive($salary, 'Salary')) $errors[] = $error;
    if ($error = validateDate($hire_date, 'Hire date')) $errors[] = $error;
    
    if (empty($errors)) {
        $email = !empty($email) ? $email : null;
        
        $stmt = $conn->prepare("
            UPDATE employees 
            SET name = ?, role = ?, phone = ?, email = ?, salary = ?, hire_date = ?, status = ?, notes = ?
            WHERE id = ?
        ");
        $stmt->bind_param("ssssdsssi", $name, $role, $phone, $email, $salary, $hire_date, $status, $notes, $id);
        
        if ($stmt->execute()) {
            $stmt->close();
            setFlashMessage("Employee updated successfully!", 'success');
            redirect('index.php');
        } else {
            $errors[] = "Failed to update employee. Please try again.";
        }
        $stmt->close();
    }
} else {
    $name = $employee['name'] ?? '';
    $role = $employee['role'] ?? '';
    $phone = $employee['phone'] ?? '';
    $email = $employee['email'] ?? '';
    $salary = $employee['salary'] ?? 0;
    $hire_date = $employee['hire_date'] ?? date('Y-m-d');
    $status = $employee['status'] ?? 'active';
    $notes = $employee['notes'] ?? '';
}
?>

<div class="form-container">
    <div class="form-header">
        <h2>Edit Employee</h2>
        <a href="index.php" class="btn btn-outline">← Back to Employees</a>
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
                <label for="name">Full Name *</label>
                <input 
                    type="text" 
                    id="name" 
                    name="name" 
                    class="form-control" 
                    value="<?php echo htmlspecialchars($name); ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="role">Role *</label>
                <input 
                    type="text" 
                    id="role" 
                    name="role" 
                    class="form-control" 
                    value="<?php echo htmlspecialchars($role); ?>"
                    required
                >
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="phone">Phone *</label>
                <input 
                    type="tel" 
                    id="phone" 
                    name="phone" 
                    class="form-control" 
                    value="<?php echo htmlspecialchars($phone); ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    class="form-control" 
                    value="<?php echo htmlspecialchars($email ?? ''); ?>"
                >
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="salary">Salary ($) *</label>
                <input 
                    type="number" 
                    id="salary" 
                    name="salary" 
                    class="form-control" 
                    value="<?php echo htmlspecialchars($salary); ?>"
                    step="0.01"
                    min="0"
                    required
                >
            </div>

            <div class="form-group">
                <label for="hire_date">Hire Date *</label>
                <input 
                    type="date" 
                    id="hire_date" 
                    name="hire_date" 
                    class="form-control" 
                    value="<?php echo htmlspecialchars($hire_date); ?>"
                    required
                >
            </div>
        </div>

        <div class="form-group">
            <label for="status">Status *</label>
            <select id="status" name="status" class="form-control" required>
                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                <option value="terminated" <?php echo $status === 'terminated' ? 'selected' : ''; ?>>Terminated</option>
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
            <button type="submit" class="btn btn-primary">Update Employee</button>
            <a href="index.php" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
