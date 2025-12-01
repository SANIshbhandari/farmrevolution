<?php
$pageTitle = 'Add Livestock - FarmSaathi';
$currentModule = 'livestock';
require_once __DIR__ . '/../includes/header.php';

requirePermission('manager');

$conn = getDBConnection();
$errors = [];

$type = sanitizeInput($_GET['type'] ?? 'purchase'); // 'purchase' or 'birth'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $animal_tag = sanitizeInput($_POST['animal_tag'] ?? '');
    $animal_type = sanitizeInput($_POST['animal_type'] ?? '');
    $breed = sanitizeInput($_POST['breed'] ?? '');
    $gender = sanitizeInput($_POST['gender'] ?? '');
    $quantity = intval($_POST['quantity'] ?? 1);
    $date_of_birth = sanitizeInput($_POST['date_of_birth'] ?? '');
    $acquisition_type = sanitizeInput($_POST['acquisition_type'] ?? 'purchase');
    $purchase_date = sanitizeInput($_POST['purchase_date'] ?? '');
    $purchase_cost = floatval($_POST['purchase_cost'] ?? 0);
    $mother_tag = sanitizeInput($_POST['mother_tag'] ?? '');
    $current_location = sanitizeInput($_POST['current_location'] ?? '');
    $notes = sanitizeInput($_POST['notes'] ?? '');
    
    // Validation
    if (empty($animal_tag)) $errors[] = "Animal tag is required.";
    if (empty($animal_type)) $errors[] = "Animal type is required.";
    if (empty($breed)) $errors[] = "Breed is required.";
    if (empty($gender)) $errors[] = "Gender is required.";
    if ($quantity <= 0) $errors[] = "Quantity must be greater than 0.";
    
    if ($acquisition_type === 'purchase') {
        if (empty($purchase_date)) $errors[] = "Purchase date is required.";
        if ($purchase_cost <= 0) $errors[] = "Purchase cost is required.";
    } else {
        if (empty($date_of_birth)) $errors[] = "Date of birth is required.";
    }
    
    // Check if tag already exists
    if (!empty($animal_tag)) {
        $checkTag = $conn->prepare("SELECT id FROM livestock WHERE animal_tag = ?");
        $checkTag->bind_param("s", $animal_tag);
        $checkTag->execute();
        if ($checkTag->get_result()->num_rows > 0) {
            $errors[] = "Animal tag already exists. Please use a unique tag.";
        }
        $checkTag->close();
    }
    
    if (empty($errors)) {
        $createdBy = getCreatedByUserId();
        $status = 'active';
        
        // Initialize JSON fields
        $health_records = '[]';
        $breeding_records = '[]';
        $production = '[]';
        $expenses = '[]';
        
        $stmt = $conn->prepare("
            INSERT INTO livestock 
            (created_by, animal_tag, animal_type, breed, gender, quantity, date_of_birth, 
             acquisition_type, purchase_date, purchase_cost, mother_tag, current_location, 
             health_records, breeding_records, production, expenses, notes, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param("issssississssssss", 
            $createdBy, $animal_tag, $animal_type, $breed, $gender, $quantity, 
            $date_of_birth, $acquisition_type, $purchase_date, $purchase_cost, 
            $mother_tag, $current_location, $health_records, $breeding_records, 
            $production, $expenses, $notes, $status
        );
        
        if ($stmt->execute()) {
            $stmt->close();
            
            // Log activity
            $action = $acquisition_type === 'birth' ? 'recorded birth of' : 'purchased';
            logActivity('create', 'livestock', "Successfully $action $quantity $animal_type ($animal_tag)");
            
            setFlashMessage("Livestock added successfully!", 'success');
            redirect('index.php');
        } else {
            $errors[] = "Failed to add livestock: " . $stmt->error;
            $stmt->close();
        }
    }
}

// Get list of female animals for mother selection (for births)
$mothers = $conn->query("
    SELECT animal_tag, animal_type, breed 
    FROM livestock 
    WHERE gender = 'female' AND status = 'active'
    ORDER BY animal_tag
");
?>

<div class="form-container">
    <div class="form-header">
        <h2><?php echo $type === 'birth' ? '🐣 Record Birth' : '💰 Purchase Livestock'; ?></h2>
        <a href="index.php" class="btn btn-outline">← Back to Livestock</a>
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
        <input type="hidden" name="acquisition_type" value="<?php echo $type; ?>">
        
        <div class="form-row">
            <div class="form-group">
                <label for="animal_tag">Animal Tag/ID *</label>
                <input type="text" id="animal_tag" name="animal_tag" class="form-control" 
                    value="<?php echo htmlspecialchars($_POST['animal_tag'] ?? ''); ?>" 
                    placeholder="e.g., COW-001, GOAT-025" required>
                <small>Unique identifier for this animal</small>
            </div>
            
            <div class="form-group">
                <label for="animal_type">Animal Type *</label>
                <select id="animal_type" name="animal_type" class="form-control" required>
                    <option value="">Select Type</option>
                    <option value="cow" <?php echo ($_POST['animal_type'] ?? '') === 'cow' ? 'selected' : ''; ?>>Cow</option>
                    <option value="buffalo" <?php echo ($_POST['animal_type'] ?? '') === 'buffalo' ? 'selected' : ''; ?>>Buffalo</option>
                    <option value="goat" <?php echo ($_POST['animal_type'] ?? '') === 'goat' ? 'selected' : ''; ?>>Goat</option>
                    <option value="sheep" <?php echo ($_POST['animal_type'] ?? '') === 'sheep' ? 'selected' : ''; ?>>Sheep</option>
                    <option value="chicken" <?php echo ($_POST['animal_type'] ?? '') === 'chicken' ? 'selected' : ''; ?>>Chicken</option>
                    <option value="duck" <?php echo ($_POST['animal_type'] ?? '') === 'duck' ? 'selected' : ''; ?>>Duck</option>
                    <option value="pig" <?php echo ($_POST['animal_type'] ?? '') === 'pig' ? 'selected' : ''; ?>>Pig</option>
                    <option value="other" <?php echo ($_POST['animal_type'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="breed">Breed *</label>
                <input type="text" id="breed" name="breed" class="form-control" 
                    value="<?php echo htmlspecialchars($_POST['breed'] ?? ''); ?>" 
                    placeholder="e.g., Holstein, Jersey, Murrah" required>
            </div>
            
            <div class="form-group">
                <label for="gender">Gender *</label>
                <select id="gender" name="gender" class="form-control" required>
                    <option value="">Select Gender</option>
                    <option value="male" <?php echo ($_POST['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                    <option value="female" <?php echo ($_POST['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="quantity">Quantity *</label>
                <input type="number" id="quantity" name="quantity" class="form-control" 
                    value="<?php echo htmlspecialchars($_POST['quantity'] ?? '1'); ?>" min="1" required>
                <small>Number of animals</small>
            </div>
            
            <div class="form-group">
                <label for="current_location">Current Location</label>
                <input type="text" id="current_location" name="current_location" class="form-control" 
                    value="<?php echo htmlspecialchars($_POST['current_location'] ?? ''); ?>" 
                    placeholder="e.g., Barn A, Shed 2">
            </div>
        </div>

        <?php if ($type === 'birth'): ?>
        <!-- Birth-specific fields -->
        <div class="form-row">
            <div class="form-group">
                <label for="date_of_birth">Date of Birth *</label>
                <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" 
                    value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? date('Y-m-d')); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="mother_tag">Mother's Tag</label>
                <select id="mother_tag" name="mother_tag" class="form-control">
                    <option value="">Select Mother (Optional)</option>
                    <?php while ($mother = $mothers->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($mother['animal_tag'] ?? ''); ?>"
                        <?php echo ($_POST['mother_tag'] ?? '') === $mother['animal_tag'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($mother['animal_tag'] ?? ''); ?> - 
                        <?php echo htmlspecialchars($mother['animal_type'] ?? ''); ?> 
                        (<?php echo htmlspecialchars($mother['breed'] ?? ''); ?>)
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Purchase-specific fields -->
        <div class="form-row">
            <div class="form-group">
                <label for="purchase_date">Purchase Date *</label>
                <input type="date" id="purchase_date" name="purchase_date" class="form-control" 
                    value="<?php echo htmlspecialchars($_POST['purchase_date'] ?? date('Y-m-d')); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="purchase_cost">Purchase Cost (Total) *</label>
                <input type="number" step="0.01" id="purchase_cost" name="purchase_cost" class="form-control" 
                    value="<?php echo htmlspecialchars($_POST['purchase_cost'] ?? ''); ?>" required>
                <small>Total cost for all animals</small>
            </div>
        </div>
        
        <div class="form-group">
            <label for="date_of_birth">Date of Birth (if known)</label>
            <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" 
                value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>">
        </div>
        <?php endif; ?>

        <div class="form-group">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" class="form-control" rows="3" 
                placeholder="Additional information about this animal..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <?php echo $type === 'birth' ? 'Record Birth' : 'Add Livestock'; ?>
            </button>
            <a href="index.php" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
