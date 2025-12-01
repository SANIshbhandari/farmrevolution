<?php
$pageTitle = 'Edit User - FarmSaathi';
$currentModule = 'users';
require_once __DIR__ . '/../../includes/header.php';

// Only admin can access
requirePermission('admin');

$conn = getDBConnection();
$errors = [];
$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    setFlashMessage("Invalid user ID.", 'error');
    redirect('index.php');
}

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    setFlashMessage("User not found.", 'error');
    redirect('index.php');
}

$user = $result->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $role = sanitizeInput($_POST['role'] ?? 'manager');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate inputs
    if ($error = validateRequired($username, 'Username')) $errors[] = $error;
    if ($error = validateEmail($email)) $errors[] = $error;
    
    // Check if username already exists (excluding current user)
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->bind_param("si", $username, $id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = "Username already exists.";
        }
        $stmt->close();
    }
    
    // Check if email already exists (excluding current user)
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = "Email already exists.";
        }
        $stmt->close();
    }
    
    // Validate password if provided
    if (!empty($password)) {
        if (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters long.";
        }
        if ($password !== $confirm_password) {
            $errors[] = "Passwords do not match.";
        }
    }
    
    // Update user if no errors
    if (empty($errors)) {
        if (!empty($password)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ?, password = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $username, $email, $role, $hashedPassword, $id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
            $stmt->bind_param("sssi", $username, $email, $role, $id);
        }
        
        if ($stmt->execute()) {
            $stmt->close();
            setFlashMessage("User updated successfully!", 'success');
            redirect('index.php');
        } else {
            $errors[] = "Failed to update user. Please try again.";
            $stmt->close();
        }
    }
} else {
    $username = $user['username'] ?? '';
    $email = $user['email'] ?? '';
    $role = $user['role'] ?? 'viewer';
}
?>

<div class="form-container">
    <div class="form-header">
        <h2>Edit User</h2>
        <a href="index.php" class="btn btn-outline">← Back to Users</a>
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
                <label for="username">Username *</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    class="form-control" 
                    value="<?php echo htmlspecialchars($username); ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="email">Email *</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    class="form-control" 
                    value="<?php echo htmlspecialchars($email); ?>"
                    required
                >
            </div>
        </div>

        <div class="form-group">
            <label for="role">Role *</label>
            <select id="role" name="role" class="form-control" required>
                <option value="manager" <?php echo $role === 'manager' ? 'selected' : ''; ?>>Manager (Farm Operations)</option>
                <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Admin (User Management)</option>
            </select>
        </div>

        <hr>
        <h3>Change Password (Optional)</h3>
        <p class="text-muted">Leave blank to keep current password</p>

        <div class="form-row">
            <div class="form-group">
                <label for="password">New Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="form-control"
                >
                <small class="form-text">Minimum 6 characters</small>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input 
                    type="password" 
                    id="confirm_password" 
                    name="confirm_password" 
                    class="form-control"
                >
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Update User</button>
            <a href="index.php" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
