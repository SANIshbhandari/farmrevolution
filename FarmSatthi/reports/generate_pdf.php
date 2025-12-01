<?php
/**
 * PDF Report Generator using TCPDF
 * Download TCPDF from: https://github.com/tecnickcom/TCPDF/releases
 * Extract to: FarmSatthi/reports/lib/tcpdf/
 */

require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if TCPDF is available - try multiple possible locations
$tcpdfPaths = [
    __DIR__ . '/TCPDF-main/tcpdf.php',           // Downloaded from GitHub
    __DIR__ . '/tcpdf/tcpdf.php',                // Renamed folder
    __DIR__ . '/lib/tcpdf/tcpdf.php',            // In lib subfolder
    __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php'  // Composer installation
];

$tcpdfPath = null;
foreach ($tcpdfPaths as $path) {
    if (file_exists($path)) {
        $tcpdfPath = $path;
        break;
    }
}

if (!$tcpdfPath) {
    die('<div style="font-family: Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 30px; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
         <h2 style="color: #dc3545;">📄 TCPDF Library Not Found</h2>
         <p><strong>Installation Instructions:</strong></p>
         <ol style="line-height: 1.8;">
            <li><strong>Download TCPDF:</strong>
                <ul style="margin-top: 10px;">
                    <li><strong>Option 1 (Composer):</strong> Run <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px;">composer require tecnickcom/tcpdf</code></li>
                    <li><strong>Option 2 (Manual):</strong> 
                        <ol type="a">
                            <li>Visit: <a href="https://github.com/tecnickcom/TCPDF" target="_blank" style="color: #2d7a3e;">https://github.com/tecnickcom/TCPDF</a></li>
                            <li>Click the green <strong>"Code"</strong> button</li>
                            <li>Select <strong>"Download ZIP"</strong></li>
                        </ol>
                    </li>
                </ul>
            </li>
            <li>Extract the downloaded ZIP file (you\'ll get a folder like <code>TCPDF-main</code>)</li>
            <li>Rename the folder to <code>tcpdf</code></li>
            <li>Copy the <code>tcpdf</code> folder to: <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px;">FarmSatthi/reports/tcpdf/</code></li>
            <li>Verify the file exists: <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px;">FarmSatthi/reports/tcpdf/tcpdf.php</code></li>
            <li>Refresh this page</li>
         </ol>
         <div style="background: #d1ecf1; padding: 15px; border-left: 4px solid #17a2b8; margin: 20px 0; border-radius: 4px;">
            <strong>💡 Recommended Alternative:</strong><br>
            Use the browser\'s print function instead! It\'s easier and produces better-looking PDFs with charts.<br>
            <ol style="margin: 10px 0 0 20px;">
                <li>Go back to the Reports Dashboard</li>
                <li>Click the <strong>"🖨️ Print/PDF"</strong> button</li>
                <li>Select "Save as PDF" in the print dialog</li>
            </ol>
         </div>
         <div style="display: flex; gap: 10px; margin-top: 20px;">
            <a href="dashboard.php" style="display: inline-block; padding: 10px 20px; background: #2d7a3e; color: white; text-decoration: none; border-radius: 4px;">← Back to Reports</a>
            <a href="https://github.com/tecnickcom/TCPDF/archive/refs/heads/main.zip" style="display: inline-block; padding: 10px 20px; background: #17a2b8; color: white; text-decoration: none; border-radius: 4px;" download>⬇️ Download TCPDF</a>
         </div>
         </div>');
}

require_once $tcpdfPath;

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die('Error: You must be logged in to generate reports. <a href="../auth/login.php">Login</a>');
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get parameters
$reportType = $_GET['type'] ?? 'dashboard';
$date_from = $_GET['date_from'] ?? date('Y-01-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Data isolation
$isolationWhere = "created_by = $user_id";

// Create new PDF document
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('FarmSaathi');
$pdf->SetAuthor('FarmSaathi Farm Management System');
$pdf->SetTitle('Farm Report - ' . ucfirst($reportType));
$pdf->SetSubject('Farm Management Report');

// Set header and footer
$pdf->SetHeaderData('', 0, 'FarmSaathi Farm Management', 'Generated: ' . date('Y-m-d H:i:s'));
$pdf->setHeaderFont(Array('helvetica', '', 10));
$pdf->setFooterFont(Array('helvetica', '', 8));

// Set margins
$pdf->SetMargins(15, 27, 15);
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(10);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 25);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 10);

// Generate content based on report type
if ($reportType === 'dashboard') {
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
    $financialSummary = $result->fetch_assoc();
    $stmt->close();
    
    $total_income = $financialSummary['total_income'] ?? 0;
    $total_expense = $financialSummary['total_expense'] ?? 0;
    $netProfit = $total_income - $total_expense;
    $profitMargin = $total_income > 0 ? ($netProfit / $total_income) * 100 : 0;
    
    // Title
    $pdf->SetFont('helvetica', 'B', 18);
    $pdf->Cell(0, 10, 'Dashboard Summary Report', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, 'Period: ' . date('M d, Y', strtotime($date_from)) . ' to ' . date('M d, Y', strtotime($date_to)), 0, 1, 'C');
    $pdf->Ln(8);
    
    // Financial Summary
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'Financial Summary', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Ln(2);
    
    $html = '<table border="1" cellpadding="6" style="border-collapse: collapse;">
        <tr style="background-color:#2d7a3e;color:#ffffff;font-weight:bold;">
            <th width="60%">Metric</th>
            <th width="40%">Amount</th>
        </tr>
        <tr>
            <td>Total Income</td>
            <td style="color:#28a745;font-weight:bold;">रू ' . number_format($total_income, 2) . '</td>
        </tr>
        <tr>
            <td>Total Expenses</td>
            <td style="color:#dc3545;font-weight:bold;">रू ' . number_format($total_expense, 2) . '</td>
        </tr>
        <tr style="background-color:#f8f9fa;">
            <td><strong>Net Profit/Loss</strong></td>
            <td style="color:' . ($netProfit >= 0 ? '#28a745' : '#dc3545') . ';font-weight:bold;">रू ' . number_format($netProfit, 2) . '</td>
        </tr>
        <tr>
            <td>Profit Margin</td>
            <td>' . number_format($profitMargin, 1) . '%</td>
        </tr>
    </table>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Ln(10);
    
    // Crop Performance
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'Crop Performance', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Ln(2);
    
    $stmt = $conn->prepare("
        SELECT crop_name, crop_type, area_hectares, expected_yield, actual_yield, status
        FROM crops 
        WHERE created_by = ?
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $crops = $stmt->get_result();
    
    $html = '<table border="1" cellpadding="5" style="border-collapse: collapse;font-size:9px;">
        <tr style="background-color:#2d7a3e;color:#ffffff;font-weight:bold;">
            <th width="25%">Crop Name</th>
            <th width="20%">Type</th>
            <th width="15%">Area (ha)</th>
            <th width="15%">Expected</th>
            <th width="15%">Actual</th>
            <th width="10%">Status</th>
        </tr>';
    
    while ($crop = $crops->fetch_assoc()) {
        $html .= '<tr>
            <td>' . htmlspecialchars($crop['crop_name'] ?? '') . '</td>
            <td>' . htmlspecialchars($crop['crop_type'] ?? '') . '</td>
            <td>' . number_format($crop['area_hectares'], 2) . '</td>
            <td>' . ($crop['expected_yield'] ? number_format($crop['expected_yield'], 0) : 'N/A') . '</td>
            <td>' . ($crop['actual_yield'] ? number_format($crop['actual_yield'], 0) : 'Pending') . '</td>
            <td>' . ucfirst($crop['status']) . '</td>
        </tr>';
    }
    
    $html .= '</table>';
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Top Expenses
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'Top Expense Categories', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Ln(2);
    
    $stmt = $conn->prepare("
        SELECT category, SUM(amount) as total, COUNT(*) as count
        FROM finance 
        WHERE type = 'expense'
        AND transaction_date BETWEEN ? AND ?
        AND created_by = ?
        GROUP BY category
        ORDER BY total DESC
        LIMIT 10
    ");
    $stmt->bind_param("ssi", $date_from, $date_to, $user_id);
    $stmt->execute();
    $expenses = $stmt->get_result();
    
    $html = '<table border="1" cellpadding="5" style="border-collapse: collapse;">
        <tr style="background-color:#2d7a3e;color:#ffffff;font-weight:bold;">
            <th width="50%">Category</th>
            <th width="20%">Transactions</th>
            <th width="30%">Amount</th>
        </tr>';
    
    while ($expense = $expenses->fetch_assoc()) {
        $html .= '<tr>
            <td>' . htmlspecialchars($expense['category'] ?? '') . '</td>
            <td align="center">' . $expense['count'] . '</td>
            <td>रू ' . number_format($expense['total'], 2) . '</td>
        </tr>';
    }
    
    $html .= '</table>';
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Ln(10);
    
    // Livestock Summary
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'Livestock Summary', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Ln(2);
    
    $stmt = $conn->prepare("
        SELECT animal_type, breed, SUM(quantity) as total_count, status, 
               COUNT(*) as records
        FROM livestock 
        WHERE created_by = ?
        GROUP BY animal_type, breed, status
        ORDER BY animal_type, total_count DESC
        LIMIT 20
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $livestock = $stmt->get_result();
    
    if ($livestock->num_rows > 0) {
        $html = '<table border="1" cellpadding="5" style="border-collapse: collapse;font-size:9px;">
            <tr style="background-color:#2d7a3e;color:#ffffff;font-weight:bold;">
                <th width="25%">Animal Type</th>
                <th width="25%">Breed</th>
                <th width="15%">Quantity</th>
                <th width="15%">Records</th>
                <th width="20%">Status</th>
            </tr>';
        
        while ($animal = $livestock->fetch_assoc()) {
            $statusColor = $animal['status'] === 'active' ? '#28a745' : ($animal['status'] === 'sold' ? '#ffc107' : '#dc3545');
            $html .= '<tr>
                <td>' . htmlspecialchars($animal['animal_type'] ?? '') . '</td>
                <td>' . htmlspecialchars($animal['breed'] ?? '') . '</td>
                <td align="center"><strong>' . $animal['total_count'] . '</strong></td>
                <td align="center">' . $animal['records'] . '</td>
                <td style="color:' . $statusColor . ';"><strong>' . ucfirst($animal['status']) . '</strong></td>
            </tr>';
        }
        
        $html .= '</table>';
        $pdf->writeHTML($html, true, false, true, false, '');
    } else {
        $pdf->Cell(0, 10, 'No livestock data available', 0, 1);
    }
    $stmt->close();
    $pdf->Ln(10);
    
    // Inventory Status
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'Inventory Status', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Ln(2);
    
    $stmt = $conn->prepare("
        SELECT item_name, item_type, category, quantity, unit, reorder_level, status
        FROM inventory 
        WHERE created_by = ?
        AND item_type IN ('supply', 'equipment')
        ORDER BY 
            CASE 
                WHEN quantity <= reorder_level THEN 1
                WHEN quantity <= reorder_level * 1.5 THEN 2
                ELSE 3
            END,
            quantity ASC
        LIMIT 15
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $inventory = $stmt->get_result();
    
    if ($inventory->num_rows > 0) {
        $html = '<table border="1" cellpadding="5" style="border-collapse: collapse;font-size:9px;">
            <tr style="background-color:#2d7a3e;color:#ffffff;font-weight:bold;">
                <th width="30%">Item Name</th>
                <th width="15%">Type</th>
                <th width="15%">Quantity</th>
                <th width="15%">Reorder At</th>
                <th width="25%">Alert</th>
            </tr>';
        
        while ($item = $inventory->fetch_assoc()) {
            $stockLevel = ($item['reorder_level'] !== null && $item['reorder_level'] > 0) ? 
                ($item['quantity'] / $item['reorder_level']) : 2;
            
            $alertText = '';
            $alertColor = '#28a745';
            if ($stockLevel <= 1) {
                $alertText = '🔴 CRITICAL - Reorder Now!';
                $alertColor = '#dc3545';
            } elseif ($stockLevel <= 1.5) {
                $alertText = '🟡 LOW - Reorder Soon';
                $alertColor = '#ffc107';
            } else {
                $alertText = '🟢 OK';
            }
            
            $html .= '<tr>
                <td>' . htmlspecialchars($item['item_name'] ?? '') . '</td>
                <td>' . ucfirst($item['item_type']) . '</td>
                <td align="center"><strong>' . number_format($item['quantity'], 2) . ' ' . $item['unit'] . '</strong></td>
                <td align="center">' . ($item['reorder_level'] ? number_format($item['reorder_level'], 2) . ' ' . $item['unit'] : 'Not set') . '</td>
                <td style="color:' . $alertColor . ';"><strong>' . $alertText . '</strong></td>
            </tr>';
        }
        
        $html .= '</table>';
        $pdf->writeHTML($html, true, false, true, false, '');
    } else {
        $pdf->Cell(0, 10, 'No inventory data available', 0, 1);
    }
    $stmt->close();
    $pdf->Ln(10);
    
    // Monthly Trend Chart (Text-based representation)
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'Monthly Financial Trend (Last 6 Months)', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Ln(2);
    
    $stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(transaction_date, '%Y-%m') as month,
            DATE_FORMAT(transaction_date, '%b %Y') as month_label,
            SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense
        FROM finance
        WHERE transaction_date BETWEEN DATE_SUB(?, INTERVAL 6 MONTH) AND ?
        AND created_by = ?
        GROUP BY month, month_label
        ORDER BY month ASC
    ");
    $stmt->bind_param("ssi", $date_to, $date_to, $user_id);
    $stmt->execute();
    $monthlyTrend = $stmt->get_result();
    
    if ($monthlyTrend->num_rows > 0) {
        $html = '<table border="1" cellpadding="5" style="border-collapse: collapse;">
            <tr style="background-color:#2d7a3e;color:#ffffff;font-weight:bold;">
                <th width="25%">Month</th>
                <th width="25%">Income</th>
                <th width="25%">Expenses</th>
                <th width="25%">Net Profit</th>
            </tr>';
        
        $maxAmount = 0;
        $trendData = [];
        while ($month = $monthlyTrend->fetch_assoc()) {
            $trendData[] = $month;
            $maxAmount = max($maxAmount, $month['income'], $month['expense']);
        }
        
        foreach ($trendData as $month) {
            $profit = $month['income'] - $month['expense'];
            $profitColor = $profit >= 0 ? '#28a745' : '#dc3545';
            
            $html .= '<tr>
                <td>' . htmlspecialchars($month['month_label']) . '</td>
                <td style="color:#28a745;"><strong>रू ' . number_format($month['income'], 2) . '</strong></td>
                <td style="color:#dc3545;"><strong>रू ' . number_format($month['expense'], 2) . '</strong></td>
                <td style="color:' . $profitColor . ';"><strong>रू ' . number_format($profit, 2) . '</strong></td>
            </tr>';
        }
        
        $html .= '</table>';
        $pdf->writeHTML($html, true, false, true, false, '');
    } else {
        $pdf->Cell(0, 10, 'No monthly trend data available', 0, 1);
    }
    $stmt->close();
    $pdf->Ln(10);
    
    // Smart Recommendations
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->SetTextColor(45, 122, 62);
    $pdf->Cell(0, 10, '💡 Smart Recommendations for Your Farm', 0, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Ln(5);
    
    $recommendations = [];
    
    // Financial recommendations
    if ($profitMargin < 20 && $profitMargin >= 0) {
        $recommendations[] = [
            'title' => '📊 Improve Profit Margin',
            'desc' => 'Your profit margin is ' . number_format($profitMargin, 1) . '%. Consider reducing operational costs or increasing crop yields to improve profitability. Target: 20%+ profit margin.'
        ];
    } elseif ($profitMargin < 0) {
        $recommendations[] = [
            'title' => '⚠️ Address Losses Urgently',
            'desc' => 'Your farm is operating at a loss. Review all expenses immediately, focus on high-value crops, and consider diversifying income sources.'
        ];
    } elseif ($profitMargin > 40) {
        $recommendations[] = [
            'title' => '🎉 Excellent Performance!',
            'desc' => 'Outstanding profit margin of ' . number_format($profitMargin, 1) . '%! Consider reinvesting in farm expansion, modern equipment, or new crop varieties.'
        ];
    }
    
    // Expense analysis
    $expenses->data_seek(0);
    if ($expenses->num_rows > 0) {
        $topExpense = $expenses->fetch_assoc();
        $expensePercent = ($total_expense > 0) ? ($topExpense['total'] / $total_expense) * 100 : 0;
        if ($expensePercent > 30) {
            $recommendations[] = [
                'title' => '💰 High Expense Category',
                'desc' => 'Your "' . $topExpense['category'] . '" expenses account for ' . number_format($expensePercent, 1) . '% of total costs. Look for bulk purchasing options or alternative suppliers to reduce costs.'
            ];
        }
    }
    
    // Inventory recommendations
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM inventory WHERE item_type = 'supply' AND quantity <= reorder_level AND created_by = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $lowStockCount = $result->fetch_assoc()['count'];
    $stmt->close();
    
    if ($lowStockCount > 0) {
        $recommendations[] = [
            'title' => '📦 Inventory Alert',
            'desc' => 'You have ' . $lowStockCount . ' item(s) running low on stock. Reorder soon to avoid operational delays and potential production losses.'
        ];
    }
    
    // Crop diversification
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT crop_type) as count FROM crops WHERE created_by = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $cropTypes = $result->fetch_assoc()['count'];
    $stmt->close();
    
    if ($cropTypes < 3) {
        $recommendations[] = [
            'title' => '🌱 Diversify Your Crops',
            'desc' => 'You are growing ' . $cropTypes . ' crop type(s). Consider diversifying to 3-5 different crops to reduce risk, improve soil health, and increase overall income stability.'
        ];
    }
    
    // Livestock recommendations
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM livestock WHERE status = 'active' AND created_by = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $activeLivestock = $result->fetch_assoc()['count'];
    $stmt->close();
    
    if ($activeLivestock > 0) {
        $recommendations[] = [
            'title' => '🐄 Livestock Health Monitoring',
            'desc' => 'Maintain regular health check-ups for your ' . $activeLivestock . ' active livestock record(s). Schedule vaccinations and keep detailed production records for better management.'
        ];
    }
    
    // General recommendations
    $recommendations[] = [
        'title' => '📱 Use Technology',
        'desc' => 'Continue using FarmSaathi to track all farm activities. Regular data entry helps identify trends and make informed decisions.'
    ];
    
    $recommendations[] = [
        'title' => '📅 Plan Ahead',
        'desc' => 'Review this report monthly. Set goals for the next period and track your progress. Small improvements compound over time.'
    ];
    
    // Display recommendations
    $recNum = 1;
    foreach ($recommendations as $rec) {
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetFillColor(240, 248, 255);
        $pdf->Cell(0, 8, $recNum . '. ' . $rec['title'], 0, 1, 'L', true);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->MultiCell(0, 5, $rec['desc'], 0, 'L');
        $pdf->Ln(3);
        $recNum++;
    }
    
    // Summary Footer
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->MultiCell(0, 5, 'This report was generated by FarmSaathi Farm Management System. For best results, review this report monthly and take action on the recommendations. Keep your data updated for accurate insights.', 0, 'C');
}

// Close database connection
$conn->close();

// Output PDF
$filename = 'FarmSaathi_' . ucfirst($reportType) . '_Report_' . date('Y-m-d') . '.pdf';
$pdf->Output($filename, 'D'); // D = download
exit;
?>
