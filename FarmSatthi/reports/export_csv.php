<?php
/**
 * CSV Export for Reports
 */

require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die('Error: You must be logged in to export reports.');
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get parameters
$exportType = $_GET['type'] ?? 'financial';
$date_from = $_GET['date_from'] ?? date('Y-01-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=FarmSaathi_' . ucfirst($exportType) . '_' . date('Y-m-d') . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 support
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

switch ($exportType) {
    case 'financial':
        // Financial transactions export
        fputcsv($output, ['Date', 'Type', 'Category', 'Description', 'Amount', 'Payment Method']);
        
        $stmt = $conn->prepare("
            SELECT transaction_date, type, category, description, amount, payment_method
            FROM finance 
            WHERE transaction_date BETWEEN ? AND ?
            AND created_by = ?
            ORDER BY transaction_date DESC
        ");
        $stmt->bind_param("ssi", $date_from, $date_to, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['transaction_date'],
                ucfirst($row['type']),
                $row['category'],
                $row['description'],
                $row['amount'],
                $row['payment_method']
            ]);
        }
        break;
        
    case 'crops':
        // Crops export
        fputcsv($output, ['Crop Name', 'Type', 'Variety', 'Area (ha)', 'Planting Date', 'Harvest Date', 'Expected Yield', 'Actual Yield', 'Status']);
        
        $stmt = $conn->prepare("
            SELECT crop_name, crop_type, variety, area_hectares, planting_date, harvest_date, 
                   expected_yield, actual_yield, status
            FROM crops 
            WHERE created_by = ?
            ORDER BY planting_date DESC
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['crop_name'],
                $row['crop_type'],
                $row['variety'],
                $row['area_hectares'],
                $row['planting_date'],
                $row['harvest_date'] ?? 'Not set',
                $row['expected_yield'] ?? 'N/A',
                $row['actual_yield'] ?? 'Pending',
                ucfirst($row['status'])
            ]);
        }
        break;
        
    case 'livestock':
        // Livestock export
        fputcsv($output, ['Animal Type', 'Breed', 'Tag Number', 'Gender', 'Date of Birth', 'Quantity', 'Status', 'Acquisition Date']);
        
        $stmt = $conn->prepare("
            SELECT animal_type, breed, animal_tag, gender, date_of_birth, quantity, status, acquisition_date
            FROM livestock 
            WHERE created_by = ?
            ORDER BY acquisition_date DESC
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['animal_type'],
                $row['breed'],
                $row['animal_tag'],
                $row['gender'],
                $row['date_of_birth'] ?? 'N/A',
                $row['quantity'],
                ucfirst($row['status']),
                $row['acquisition_date']
            ]);
        }
        break;
        
    case 'inventory':
        // Inventory export
        fputcsv($output, ['Item Name', 'Type', 'Category', 'Quantity', 'Unit', 'Reorder Level', 'Status', 'Purchase Date']);
        
        $stmt = $conn->prepare("
            SELECT item_name, item_type, category, quantity, unit, reorder_level, status, purchase_date
            FROM inventory 
            WHERE created_by = ?
            ORDER BY item_name ASC
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['item_name'],
                ucfirst($row['item_type']),
                $row['category'],
                $row['quantity'] ?? 'N/A',
                $row['unit'] ?? 'N/A',
                $row['reorder_level'] ?? 'Not set',
                ucfirst($row['status']),
                $row['purchase_date'] ?? 'N/A'
            ]);
        }
        break;
        
    case 'summary':
        // Summary export
        fputcsv($output, ['Report Summary - ' . date('M d, Y', strtotime($date_from)) . ' to ' . date('M d, Y', strtotime($date_to))]);
        fputcsv($output, []);
        
        // Financial Summary
        $stmt = $conn->prepare("
            SELECT 
                SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
                SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense
            FROM finance 
            WHERE transaction_date BETWEEN ? AND ?
            AND created_by = ?
        ");
        $stmt->bind_param("ssi", $date_from, $date_to, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $financial = $result->fetch_assoc();
        
        fputcsv($output, ['Financial Summary']);
        fputcsv($output, ['Total Income', 'रू ' . number_format($financial['total_income'], 2)]);
        fputcsv($output, ['Total Expenses', 'रू ' . number_format($financial['total_expense'], 2)]);
        fputcsv($output, ['Net Profit', 'रू ' . number_format($financial['total_income'] - $financial['total_expense'], 2)]);
        fputcsv($output, []);
        
        // Crop Count
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM crops WHERE created_by = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $cropCount = $stmt->get_result()->fetch_assoc()['count'];
        
        fputcsv($output, ['Total Crops', $cropCount]);
        
        // Livestock Count
        $stmt = $conn->prepare("SELECT SUM(quantity) as count FROM livestock WHERE status = 'active' AND created_by = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $livestockCount = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
        
        fputcsv($output, ['Active Livestock', $livestockCount]);
        break;
        
    default:
        fputcsv($output, ['Error: Invalid export type']);
}

$conn->close();
fclose($output);
exit;
?>
