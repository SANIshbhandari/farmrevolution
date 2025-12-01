<?php
/**
 * Core Utility Functions
 */

/**
 * Sanitize user input
 * @param string $data Input data to sanitize
 * @return string Sanitized data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validate required field
 * @param mixed $value Field value
 * @param string $fieldName Field name for error message
 * @return string|null Error message or null if valid
 */
function validateRequired($value, $fieldName) {
    if (empty($value) && $value !== '0') {
        return $fieldName . " is required.";
    }
    return null;
}

/**
 * Validate email format
 * @param string $email Email address
 * @return string|null Error message or null if valid
 */
function validateEmail($email) {
    if (empty($email)) {
        return "Email is required.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return "Invalid email format.";
    }
    return null;
}

/**
 * Validate date format (YYYY-MM-DD)
 * @param string $date Date string
 * @param string $fieldName Field name for error message
 * @return string|null Error message or null if valid
 */
function validateDate($date, $fieldName = "Date") {
    if (empty($date)) {
        return $fieldName . " is required.";
    }
    $d = DateTime::createFromFormat('Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date) {
        return $fieldName . " must be in YYYY-MM-DD format.";
    }
    return null;
}

/**
 * Validate numeric value
 * @param mixed $value Value to validate
 * @param string $fieldName Field name for error message
 * @return string|null Error message or null if valid
 */
function validateNumeric($value, $fieldName) {
    if (empty($value) && $value !== '0' && $value !== 0) {
        return $fieldName . " is required.";
    }
    if (!is_numeric($value)) {
        return $fieldName . " must be a number.";
    }
    return null;
}

/**
 * Validate positive number
 * @param mixed $value Value to validate
 * @param string $fieldName Field name for error message
 * @return string|null Error message or null if valid
 */
function validatePositive($value, $fieldName) {
    $numericError = validateNumeric($value, $fieldName);
    if ($numericError) {
        return $numericError;
    }
    if ($value < 0) {
        return $fieldName . " must be a positive number.";
    }
    return null;
}

/**
 * Set flash message in session
 * @param string $message Message text
 * @param string $type Message type (success, error, warning, info)
 */
function setFlashMessage($message, $type = 'info') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

/**
 * Get and clear flash message from session
 * @return array|null Array with 'message' and 'type' keys, or null if no message
 */
function getFlashMessage() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['flash_message'])) {
        $message = [
            'message' => $_SESSION['flash_message'],
            'type' => $_SESSION['flash_type'] ?? 'info'
        ];
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        return $message;
    }
    
    return null;
}

/**
 * Display flash message HTML
 */
function displayFlashMessage() {
    $flash = getFlashMessage();
    if ($flash) {
        $alertClass = 'alert-' . $flash['type'];
        echo '<div class="alert ' . $alertClass . '" id="flash-message">';
        echo htmlspecialchars($flash['message']);
        echo '<span class="close-alert" onclick="this.parentElement.style.display=\'none\';">&times;</span>';
        echo '</div>';
    }
}

/**
 * Redirect to a page
 * @param string $url URL to redirect to
 */
function redirect($url) {
    header("Location: " . $url);
    exit();
}

/**
 * Format currency in Nepali Rupees
 * @param float $amount Amount to format
 * @return string Formatted currency string
 */
function formatCurrency($amount) {
    return 'रू ' . number_format($amount ?? 0, 2);
}

/**
 * Format date for display (Nepali format)
 * @param string $date Date string
 * @return string Formatted Nepali date
 */
function formatDate($date) {
    if (empty($date)) return '';
    require_once __DIR__ . '/nepali_date.php';
    return toNepaliDate($date, 'long');
}

/**
 * Format date in English (for forms and inputs)
 * @param string $date Date string
 * @return string Formatted English date
 */
function formatDateEnglish($date) {
    if (empty($date)) return '';
    return date('M d, Y', strtotime($date));
}

/**
 * Get pagination data
 * @param int $totalRecords Total number of records
 * @param int $currentPage Current page number
 * @param int $recordsPerPage Records per page
 * @return array Pagination data
 */
function getPagination($totalRecords, $currentPage = 1, $recordsPerPage = 20) {
    $totalPages = ceil($totalRecords / $recordsPerPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $recordsPerPage;
    
    return [
        'total_records' => $totalRecords,
        'total_pages' => $totalPages,
        'current_page' => $currentPage,
        'records_per_page' => $recordsPerPage,
        'offset' => $offset,
        'has_previous' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages
    ];
}

/**
 * Generate CSRF token
 * @return string CSRF token
 */
function generateCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token Token to verify
 * @return bool True if valid, false otherwise
 */
function verifyCSRFToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Log user activity
 * @param string $action Action performed (create, update, delete, view)
 * @param string $module Module name (crops, livestock, equipment, etc.)
 * @param string $description Description of the action
 */
function logActivity($action, $module, $description = '') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Only log if user is logged in
    if (!isset($_SESSION['user_id'])) {
        return;
    }
    
    try {
        $conn = getDBConnection();
        $user_id = $_SESSION['user_id'] ?? null;
        $username = $_SESSION['username'] ?? 'System';
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        $stmt = $conn->prepare("INSERT INTO activity_log (user_id, username, action, module, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
        
        if (!$stmt) {
            error_log("Activity log prepare failed: " . $conn->error);
            return; // Silently fail
        }
        
        $stmt->bind_param("isssss", $user_id, $username, $action, $module, $description, $ip_address);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        // Silently fail - don't break the application if logging fails
        error_log("Activity logging failed: " . $e->getMessage());
    }
}

/**
 * ============================================================================
 * DATA ISOLATION FUNCTIONS
 * These functions implement user-based data isolation for managers
 * ============================================================================
 */

/**
 * Get WHERE clause for data isolation
 * Admins see all data, managers see only their own data
 * @param string $tableAlias Optional table alias (e.g., 'c' for crops)
 * @return string WHERE clause condition (e.g., "created_by = 5" or "1=1")
 */
function getDataIsolationWhere($tableAlias = '') {
    $role = getCurrentUserRole();
    $userId = getCurrentUserId();
    
    // Admins see all data
    if ($role === 'admin') {
        return '1=1';
    }
    
    // Managers see only their own data
    $column = $tableAlias ? "$tableAlias.created_by" : 'created_by';
    return "$column = $userId";
}

/**
 * Check if current user can access a specific record
 * @param int $recordUserId The user_id who created the record
 * @return bool True if user can access, false otherwise
 */
function canAccessRecord($recordUserId) {
    $role = getCurrentUserRole();
    $userId = getCurrentUserId();
    
    // Admins can access all records
    if ($role === 'admin') {
        return true;
    }
    
    // Managers can only access their own records
    return $userId == $recordUserId;
}

/**
 * Get current user ID for INSERT operations
 * Returns the logged-in user's ID to be used as created_by value
 * @return int User ID
 */
function getCreatedByUserId() {
    return getCurrentUserId();
}

/**
 * Verify record ownership before UPDATE/DELETE operations
 * Redirects with error message if user doesn't have access
 * @param mysqli $conn Database connection
 * @param string $table Table name
 * @param int $recordId Record ID to check
 * @param string $redirectUrl URL to redirect to if access denied
 */
function verifyRecordOwnership($conn, $table, $recordId, $redirectUrl = 'index.php') {
    // Check which column exists (created_by or user_id)
    $columnCheck = $conn->query("SHOW COLUMNS FROM $table LIKE 'created_by'");
    $userColumn = ($columnCheck && $columnCheck->num_rows > 0) ? 'created_by' : 'user_id';
    
    $stmt = $conn->prepare("SELECT $userColumn as owner_id FROM $table WHERE id = ?");
    
    if (!$stmt) {
        error_log("verifyRecordOwnership prepare failed for table $table: " . $conn->error);
        setFlashMessage("Database error.", 'error');
        redirect($redirectUrl);
        return;
    }
    
    $stmt->bind_param("i", $recordId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        setFlashMessage("Record not found.", 'error');
        redirect($redirectUrl);
    }
    
    $record = $result->fetch_assoc();
    $stmt->close();
    
    if (!canAccessRecord($record['owner_id'])) {
        setFlashMessage("You don't have permission to access this record.", 'error');
        redirect($redirectUrl);
    }
}

/**
 * Get user-specific statistics for dashboard
 * @param mysqli $conn Database connection
 * @return array Statistics array with counts
 */
function getUserStatistics($conn) {
    $isolationWhere = getDataIsolationWhere();
    
    $stats = [];
    
    // Count crops
    $result = $conn->query("SELECT COUNT(*) as count FROM crops WHERE $isolationWhere");
    $stats['crops'] = $result->fetch_assoc()['count'];
    
    // Count livestock
    $result = $conn->query("SELECT COUNT(*) as count FROM livestock WHERE $isolationWhere");
    $stats['livestock'] = $result->fetch_assoc()['count'];
    
    // Count equipment
    $result = $conn->query("SELECT COUNT(*) as count FROM equipment WHERE $isolationWhere");
    $stats['equipment'] = $result->fetch_assoc()['count'];
    
    // Count employees
    $result = $conn->query("SELECT COUNT(*) as count FROM employees WHERE $isolationWhere");
    $stats['employees'] = $result->fetch_assoc()['count'];
    
    // Count expenses
    $result = $conn->query("SELECT COUNT(*) as count FROM expenses WHERE $isolationWhere");
    $stats['expenses'] = $result->fetch_assoc()['count'];
    
    // Count inventory items
    $result = $conn->query("SELECT COUNT(*) as count FROM inventory WHERE $isolationWhere");
    $stats['inventory'] = $result->fetch_assoc()['count'];
    
    return $stats;
}
?>
