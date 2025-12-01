<?php
$pageTitle = 'Reports Dashboard - FarmSaathi';
$currentModule = 'reports';
require_once __DIR__ . '/../includes/header.php';

$conn = getDBConnection();
$isolationWhere = getDataIsolationWhere();

// Date range filter
$date_from = sanitizeInput($_GET['date_from'] ?? date('Y-m-01'));
$date_to = sanitizeInput($_GET['date_to'] ?? date('Y-m-d'));

// Get all report data
// 1. Crop Performance
$cropPerformance = $conn->query("
    SELECT crop_name, crop_type, area_hectares, expected_yield, actual_yield, status,
           planting_date, harvest_date
    FROM crops 
    WHERE $isolationWhere
    ORDER BY actual_yield DESC
    LIMIT 10
");

// 2. Livestock Production
$livestockData = $conn->query("
    SELECT animal_type, breed, quantity, status, production
    FROM livestock 
    WHERE $isolationWhere
    ORDER BY quantity DESC
");

// 3. Financial Summary
$financialSummary = $conn->query("
    SELECT 
        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
        SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense,
        SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END) as net_profit
    FROM finance 
    WHERE transaction_date BETWEEN '$date_from' AND '$date_to'
    AND $isolationWhere
")->fetch_assoc();

// 4. Monthly Revenue vs Expense
$monthlyData = $conn->query("
    SELECT 
        DATE_FORMAT(transaction_date, '%Y-%m') as month,
        DATE_FORMAT(transaction_date, '%b %Y') as month_label,
        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
        SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense
    FROM finance
    WHERE transaction_date BETWEEN DATE_SUB('$date_to', INTERVAL 6 MONTH) AND '$date_to'
    AND $isolationWhere
    GROUP BY month, month_label
    ORDER BY month ASC
");

// 5. Top Expense Categories
$expenseCategories = $conn->query("
    SELECT category, SUM(amount) as total
    FROM finance 
    WHERE type = 'expense'
    AND transaction_date BETWEEN '$date_from' AND '$date_to'
    AND $isolationWhere
    GROUP BY category
    ORDER BY total DESC
    LIMIT 5
");

// 6. Inventory Stock Status
$inventoryStatus = $conn->query("
    SELECT item_name, category, quantity, unit, reorder_level, status
    FROM inventory 
    WHERE item_type = 'supply'
    AND $isolationWhere
    ORDER BY 
        CASE 
            WHEN quantity <= reorder_level THEN 1
            WHEN quantity <= reorder_level * 1.5 THEN 2
            ELSE 3
        END,
        quantity ASC
    LIMIT 10
");

// 7. Employee Productivity (from inventory)
$employees = $conn->query("
    SELECT item_name as name, category as position, hire_date, salary, status
    FROM inventory 
    WHERE item_type = 'employee'
    AND $isolationWhere
    ORDER BY hire_date DESC
");

// 8. Alerts & Upcoming Events
$alerts = [];

// Upcoming harvests
$upcomingHarvests = $conn->query("
    SELECT crop_name, harvest_date
    FROM crops 
    WHERE harvest_date BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 14 DAY)
    AND status = 'active'
    AND $isolationWhere
    ORDER BY harvest_date ASC
");
while ($row = $upcomingHarvests->fetch_assoc()) {
    $alerts[] = [
        'type' => 'info',
        'icon' => '📅',
        'message' => "Harvest due: {$row['crop_name']} on " . date('M d', strtotime($row['harvest_date']))
    ];
}

// Low stock items
$lowStock = $conn->query("
    SELECT item_name, quantity, unit, reorder_level
    FROM inventory 
    WHERE item_type = 'supply' 
    AND quantity <= reorder_level
    AND $isolationWhere
    LIMIT 5
");
while ($row = $lowStock->fetch_assoc()) {
    $alerts[] = [
        'type' => 'warning',
        'icon' => '⚠️',
        'message' => "Low stock: {$row['item_name']} ({$row['quantity']} {$row['unit']})"
    ];
}

// 9. Recent Activities
$recentActivities = $conn->query("
    SELECT username, action, module, description, created_at
    FROM activity_log
    WHERE user_id = " . getCurrentUserId() . "
    ORDER BY created_at DESC
    LIMIT 10
");
?>

<style>
.reports-dashboard { padding: 20px; background: #f5f7fa; min-height: 100vh; }
.report-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
.report-header h2 { margin: 0; color: #2d7a3e; font-size: 1.8rem; }
.date-filter { display: flex; gap: 10px; align-items: center; }
.date-filter input { border: 2px solid #e0e0e0; border-radius: 8px; padding: 8px 12px; }
.date-filter input:focus { border-color: #2d7a3e; outline: none; }

/* Stats Grid */
.report-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 30px; }
.report-card { 
    background: white; 
    padding: 25px; 
    border-radius: 12px; 
    box-shadow: 0 4px 12px rgba(0,0,0,0.08); 
    transition: transform 0.3s, box-shadow 0.3s;
    position: relative;
    overflow: hidden;
}
.report-card:hover { 
    transform: translateY(-5px); 
    box-shadow: 0 8px 20px rgba(0,0,0,0.12); 
}
.report-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, #2d7a3e, #4caf50);
}
.report-card h3 { 
    margin: 0 0 15px 0; 
    color: #555; 
    font-size: 0.95rem; 
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.stat-large { 
    font-size: 2.5rem; 
    font-weight: bold; 
    margin: 10px 0; 
    background: linear-gradient(135deg, #2d7a3e, #4caf50);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.stat-positive { color: #28a745; }
.stat-negative { color: #dc3545; }

/* Progress Bars */
.progress-bar { 
    background: #e9ecef; 
    height: 24px; 
    border-radius: 12px; 
    overflow: hidden; 
    margin: 10px 0;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
}
.progress-fill { 
    background: linear-gradient(90deg, #28a745, #4caf50); 
    height: 100%; 
    transition: width 0.6s ease;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding-right: 10px;
    color: white;
    font-size: 0.85rem;
    font-weight: 600;
}

/* Alerts */
.alert-item { 
    padding: 12px 15px; 
    margin: 8px 0; 
    border-left: 4px solid; 
    border-radius: 6px;
    transition: all 0.3s;
    cursor: pointer;
}
.alert-item:hover { transform: translateX(5px); }
.alert-warning { background: #fff3cd; border-color: #ffc107; }
.alert-info { background: #d1ecf1; border-color: #17a2b8; }
.alert-critical { background: #f8d7da; border-color: #dc3545; }

/* Activity Feed */
.activity-item { 
    padding: 12px; 
    border-bottom: 1px solid #eee;
    transition: background 0.3s;
}
.activity-item:hover { background: #f8f9fa; }
.activity-item:last-child { border-bottom: none; }

/* Tables */
.data-table { 
    width: 100%; 
    border-collapse: separate;
    border-spacing: 0;
    border-radius: 8px;
    overflow: hidden;
}
.data-table thead { background: linear-gradient(135deg, #2d7a3e, #4caf50); }
.data-table th { 
    color: white; 
    padding: 15px; 
    font-weight: 600;
    text-align: left;
}
.data-table td { 
    padding: 12px 15px; 
    border-bottom: 1px solid #f0f0f0;
}
.data-table tbody tr { transition: background 0.3s; }
.data-table tbody tr:hover { background: #f8f9fa; }

/* Badges */
.badge { 
    padding: 6px 12px; 
    border-radius: 20px; 
    font-size: 0.85rem;
    font-weight: 600;
    display: inline-block;
}
.badge-success { background: #d4edda; color: #155724; }
.badge-warning { background: #fff3cd; color: #856404; }
.badge-info { background: #d1ecf1; color: #0c5460; }
.badge-active { background: #d4edda; color: #155724; }
.badge-harvested { background: #cce5ff; color: #004085; }

/* Chart Container */
.chart-container { 
    background: white; 
    padding: 25px; 
    border-radius: 12px; 
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    margin-bottom: 20px;
}
.chart-placeholder { 
    height: 250px; 
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    border-radius: 8px; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    color: #6c757d;
    font-size: 1.1rem;
}

/* Buttons */
.btn { 
    border-radius: 8px; 
    padding: 10px 20px;
    font-weight: 600;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
}
.btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.btn-success { background: linear-gradient(135deg, #28a745, #4caf50); color: white; }
.btn-secondary { background: linear-gradient(135deg, #6c757d, #868e96); color: white; }

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
.report-card { animation: fadeIn 0.5s ease-out; }
.report-card:nth-child(1) { animation-delay: 0.1s; }
.report-card:nth-child(2) { animation-delay: 0.2s; }
.report-card:nth-child(3) { animation-delay: 0.3s; }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="reports-dashboard">
    <div class="report-header">
        <h2>📊 Reports Dashboard</h2>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <!-- Quick Date Filters -->
            <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                <a href="?date_from=<?php echo date('Y-m-d'); ?>&date_to=<?php echo date('Y-m-d'); ?>" class="btn btn-sm btn-outline" title="Today">Today</a>
                <a href="?date_from=<?php echo date('Y-m-d', strtotime('monday this week')); ?>&date_to=<?php echo date('Y-m-d'); ?>" class="btn btn-sm btn-outline" title="This Week">This Week</a>
                <a href="?date_from=<?php echo date('Y-m-01'); ?>&date_to=<?php echo date('Y-m-d'); ?>" class="btn btn-sm btn-outline" title="This Month">This Month</a>
                <a href="?date_from=<?php echo date('Y-01-01'); ?>&date_to=<?php echo date('Y-m-d'); ?>" class="btn btn-sm btn-outline" title="This Year">This Year</a>
            </div>
            
            <!-- Custom Date Filter -->
            <form method="GET" class="date-filter">
                <input type="date" name="date_from" value="<?php echo $date_from; ?>" class="form-control">
                <span>to</span>
                <input type="date" name="date_to" value="<?php echo $date_to; ?>" class="form-control">
                <button type="submit" class="btn btn-secondary">Apply</button>
            </form>
            
            <!-- Export Options -->
            <div class="dropdown" style="position: relative; display: inline-block;">
                <button onclick="toggleDropdown()" class="btn btn-success" title="Export Options">📥 Export ▼</button>
                <div id="exportDropdown" style="display: none; position: absolute; background: white; box-shadow: 0 4px 8px rgba(0,0,0,0.2); border-radius: 4px; min-width: 200px; z-index: 1000; margin-top: 5px;">
                    <a href="#" onclick="window.print(); return false;" style="display: block; padding: 10px 15px; text-decoration: none; color: #333; border-bottom: 1px solid #eee;">🖨️ Print/Save as PDF</a>
                    <a href="generate_pdf.php?type=dashboard&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" style="display: block; padding: 10px 15px; text-decoration: none; color: #333; border-bottom: 1px solid #eee;">📄 Download PDF</a>
                    <a href="export_csv.php?type=financial&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" style="display: block; padding: 10px 15px; text-decoration: none; color: #333; border-bottom: 1px solid #eee;">📊 Export Financial (CSV)</a>
                    <a href="export_csv.php?type=crops&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" style="display: block; padding: 10px 15px; text-decoration: none; color: #333; border-bottom: 1px solid #eee;">🌾 Export Crops (CSV)</a>
                    <a href="export_csv.php?type=livestock&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" style="display: block; padding: 10px 15px; text-decoration: none; color: #333; border-bottom: 1px solid #eee;">🐄 Export Livestock (CSV)</a>
                    <a href="export_csv.php?type=inventory&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" style="display: block; padding: 10px 15px; text-decoration: none; color: #333; border-bottom: 1px solid #eee;">📦 Export Inventory (CSV)</a>
                    <a href="export_csv.php?type=summary&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" style="display: block; padding: 10px 15px; text-decoration: none; color: #333;">📋 Export Summary (CSV)</a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function toggleDropdown() {
        const dropdown = document.getElementById('exportDropdown');
        dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
    }
    
    // Close dropdown when clicking outside
    window.onclick = function(event) {
        if (!event.target.matches('.btn')) {
            const dropdown = document.getElementById('exportDropdown');
            if (dropdown && dropdown.style.display === 'block') {
                dropdown.style.display = 'none';
            }
        }
    }
    </script>

    <!-- Financial Summary Cards -->
    <div class="report-grid">
        <div class="report-card">
            <h3>💰 Total Income</h3>
            <div class="stat-large stat-positive">₹<?php echo number_format($financialSummary['total_income'] ?? 0, 2); ?></div>
            <small>Period: <?php echo date('M d', strtotime($date_from)); ?> - <?php echo date('M d', strtotime($date_to)); ?></small>
        </div>
        <div class="report-card">
            <h3>💸 Total Expenses</h3>
            <div class="stat-large stat-negative">₹<?php echo number_format($financialSummary['total_expense'] ?? 0, 2); ?></div>
            <small>Period: <?php echo date('M d', strtotime($date_from)); ?> - <?php echo date('M d', strtotime($date_to)); ?></small>
        </div>
        <div class="report-card">
            <h3>📈 Net Profit</h3>
            <div class="stat-large <?php echo ($financialSummary['net_profit'] ?? 0) >= 0 ? 'stat-positive' : 'stat-negative'; ?>">
                ₹<?php echo number_format($financialSummary['net_profit'] ?? 0, 2); ?>
            </div>
            <small>Profit Margin: <?php echo ($financialSummary['total_income'] ?? 0) > 0 ? number_format((($financialSummary['net_profit'] ?? 0) / $financialSummary['total_income']) * 100, 1) : 0; ?>%</small>
        </div>
    </div>

    <!-- Continue in next part... -->

    <!-- 1. Crop Performance Report -->
    <div class="report-card" style="grid-column: 1 / -1;">
        <h3>🌾 Crop Performance Report</h3>
        <?php if ($cropPerformance && $cropPerformance->num_rows > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Crop Name</th>
                    <th>Type</th>
                    <th>Area (ha)</th>
                    <th>Expected Yield</th>
                    <th>Actual Yield</th>
                    <th>Performance</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($crop = $cropPerformance->fetch_assoc()): 
                    $performance = $crop['expected_yield'] > 0 ? ($crop['actual_yield'] / $crop['expected_yield']) * 100 : 0;
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($crop['crop_name'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($crop['crop_type'] ?? ''); ?></td>
                    <td><?php echo number_format($crop['area_hectares'], 2); ?></td>
                    <td><?php echo $crop['expected_yield'] ? number_format($crop['expected_yield'], 2) : 'N/A'; ?></td>
                    <td><?php echo $crop['actual_yield'] ? number_format($crop['actual_yield'], 2) : 'N/A'; ?></td>
                    <td>
                        <?php if ($performance > 0): ?>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo min($performance, 100); ?>%; background: <?php echo $performance >= 100 ? '#28a745' : ($performance >= 80 ? '#ffc107' : '#dc3545'); ?>;"></div>
                        </div>
                        <small><?php echo number_format($performance, 1); ?>%</small>
                        <?php else: ?>
                        <span>Pending</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge badge-<?php echo $crop['status']; ?>"><?php echo ucfirst($crop['status']); ?></span></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="color: #6c757d;">No crop data available</p>
        <?php endif; ?>
    </div>

    <!-- 2. Livestock Health & Production -->
    <div class="report-card" style="grid-column: 1 / -1;">
        <h3>🐄 Livestock Health & Production Report</h3>
        <?php if ($livestockData && $livestockData->num_rows > 0): ?>
        <div class="report-grid">
            <?php while ($livestock = $livestockData->fetch_assoc()): 
                $production = json_decode($livestock['production'] ?? '[]', true);
                $productionCount = is_array($production) ? count($production) : 0;
            ?>
            <div style="border: 1px solid #dee2e6; padding: 15px; border-radius: 4px;">
                <h4 style="margin: 0 0 10px 0;"><?php echo htmlspecialchars($livestock['animal_type'] ?? ''); ?></h4>
                <p><strong>Breed:</strong> <?php echo htmlspecialchars($livestock['breed'] ?? ''); ?></p>
                <p><strong>Quantity:</strong> <?php echo $livestock['quantity']; ?> heads</p>
                <p><strong>Production Records:</strong> <?php echo $productionCount; ?></p>
                <p><strong>Status:</strong> <span class="badge badge-<?php echo $livestock['status']; ?>"><?php echo ucfirst($livestock['status']); ?></span></p>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <p style="color: #6c757d;">No livestock data available</p>
        <?php endif; ?>
    </div>

    <!-- 3. Monthly Revenue vs Expense Chart -->
    <div class="chart-container" style="grid-column: 1 / -1;">
        <h3 style="color: #2d7a3e; margin-bottom: 20px;">📊 Monthly Revenue vs Expense Trend</h3>
        <?php if ($monthlyData && $monthlyData->num_rows > 0): ?>
        <canvas id="revenueChart" height="80"></canvas>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('revenueChart');
            if (!ctx) {
                console.error('Canvas element not found');
                return;
            }
            
            try {
                const revenueChart = new Chart(ctx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: [
                            <?php 
                            $monthlyData->data_seek(0);
                            $labels = [];
                            $incomeData = [];
                            $expenseData = [];
                            while ($month = $monthlyData->fetch_assoc()) {
                                $labels[] = "'" . addslashes($month['month_label']) . "'";
                                $incomeData[] = floatval($month['income']);
                                $expenseData[] = floatval($month['expense']);
                            }
                            echo implode(',', $labels);
                            ?>
                        ],
                        datasets: [{
                            label: 'Income',
                            data: [<?php echo implode(',', $incomeData); ?>],
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            borderWidth: 3,
                            tension: 0.4,
                            fill: true,
                            pointRadius: 5,
                            pointHoverRadius: 7
                        }, {
                            label: 'Expenses',
                            data: [<?php echo implode(',', $expenseData); ?>],
                            borderColor: '#dc3545',
                            backgroundColor: 'rgba(220, 53, 69, 0.1)',
                            borderWidth: 3,
                            tension: 0.4,
                            fill: true,
                            pointRadius: 5,
                            pointHoverRadius: 7
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                                labels: {
                                    font: {
                                        size: 14,
                                        weight: 'bold'
                                    },
                                    padding: 15
                                }
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                backgroundColor: 'rgba(0,0,0,0.8)',
                                padding: 12,
                                titleFont: {
                                    size: 14
                                },
                                bodyFont: {
                                    size: 13
                                },
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': रू ' + context.parsed.y.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return 'रू ' + value.toLocaleString('en-IN');
                                    },
                                    font: {
                                        size: 12
                                    }
                                },
                                grid: {
                                    color: 'rgba(0,0,0,0.05)'
                                }
                            },
                            x: {
                                ticks: {
                                    font: {
                                        size: 12
                                    }
                                },
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
                console.log('Chart created successfully');
            } catch (error) {
                console.error('Error creating chart:', error);
            }
        });
        </script>
        
        <table class="data-table" style="margin-top: 20px;">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Income</th>
                    <th>Expenses</th>
                    <th>Net Profit</th>
                    <th>Trend</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $monthlyData->data_seek(0); // Reset pointer to beginning
                while ($month = $monthlyData->fetch_assoc()): 
                    $profit = $month['income'] - $month['expense'];
                    $maxAmount = max($month['income'], $month['expense']);
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($month['month_label']); ?></td>
                    <td class="stat-positive">₹<?php echo number_format($month['income'], 2); ?></td>
                    <td class="stat-negative">₹<?php echo number_format($month['expense'], 2); ?></td>
                    <td class="<?php echo $profit >= 0 ? 'stat-positive' : 'stat-negative'; ?>">₹<?php echo number_format($profit, 2); ?></td>
                    <td>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $maxAmount > 0 ? ($month['income'] / $maxAmount) * 100 : 0; ?>%; background: #28a745;"></div>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div style="text-align: center; padding: 40px; background: #f8f9fa; border-radius: 8px;">
            <p style="color: #6c757d; font-size: 1.1rem; margin-bottom: 10px;">📊 No financial data available for the selected period</p>
            <p style="color: #999; font-size: 0.9rem;">Add income and expense transactions to see the revenue vs expense chart</p>
            <a href="<?php echo url('expenses/add.php'); ?>" class="btn btn-success" style="margin-top: 15px;">+ Add Transaction</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- 4. Top Expense Categories -->
    <div class="report-card">
        <h3>💳 Top Expense Categories</h3>
        <?php if ($expenseCategories && $expenseCategories->num_rows > 0): 
            $expenses = [];
            $maxExpense = 0;
            $expenseColors = ['#dc3545', '#ffc107', '#17a2b8', '#6c757d', '#28a745'];
            while ($row = $expenseCategories->fetch_assoc()) {
                $expenses[] = $row;
                $maxExpense = max($maxExpense, $row['total']);
            }
        ?>
        <div style="margin-top: 20px;">
        <?php foreach ($expenses as $i => $expense): ?>
        <div style="margin-bottom: 15px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                <strong><span style="display: inline-block; width: 12px; height: 12px; background: <?php echo $expenseColors[$i]; ?>; border-radius: 50%; margin-right: 8px;"></span><?php echo htmlspecialchars($expense['category'] ?? ''); ?></strong>
                <span>₹<?php echo number_format($expense['total'], 2); ?></span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo ($expense['total'] / $maxExpense) * 100; ?>%; background: <?php echo $expenseColors[$i]; ?>;">
                    <?php echo number_format(($expense['total'] / $maxExpense) * 100, 0); ?>%
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p style="color: #6c757d; text-align: center; padding: 40px;">No expense data available</p>
        <?php endif; ?>
    </div>

    <!-- 5. Profit Margin Report -->
    <div class="report-card">
        <h3>📈 Profit Margin Analysis</h3>
        <?php
        $profitMargin = $financialSummary['total_income'] > 0 ? 
            ($financialSummary['net_profit'] / $financialSummary['total_income']) * 100 : 0;
        
        // Get highest profit crop
        $topCrop = $conn->query("
            SELECT crop_name, actual_yield, production_cost
            FROM crops 
            WHERE actual_yield IS NOT NULL AND production_cost > 0
            AND $isolationWhere
            ORDER BY (actual_yield - production_cost) DESC
            LIMIT 1
        ")->fetch_assoc();
        ?>
        <div style="text-align: center; padding: 20px;">
            <div class="stat-large <?php echo $profitMargin >= 0 ? 'stat-positive' : 'stat-negative'; ?>">
                <?php echo number_format($profitMargin, 1); ?>%
            </div>
            <p>Overall Profit Margin</p>
        </div>
        <?php if ($topCrop): ?>
        <div style="background: #d4edda; padding: 15px; border-radius: 4px; margin-top: 10px;">
            <strong>🏆 Top Performing Crop:</strong><br>
            <?php echo htmlspecialchars($topCrop['crop_name'] ?? ''); ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- 6. Inventory Stock Status -->
    <div class="report-card">
        <h3>📦 Inventory Stock Status</h3>
        <?php if ($inventoryStatus && $inventoryStatus->num_rows > 0): ?>
        <?php while ($item = $inventoryStatus->fetch_assoc()): 
            $stockLevel = ($item['reorder_level'] !== null && $item['reorder_level'] > 0) ? ($item['quantity'] / $item['reorder_level']) : 1;
            $statusColor = $stockLevel <= 1 ? '🔴' : ($stockLevel <= 1.5 ? '🟡' : '🟢');
        ?>
        <div style="padding: 10px; border-bottom: 1px solid #eee;">
            <div style="display: flex; justify-content: space-between;">
                <span><?php echo $statusColor; ?> <?php echo htmlspecialchars($item['item_name'] ?? ''); ?></span>
                <span><strong><?php echo number_format($item['quantity'], 2); ?> <?php echo $item['unit']; ?></strong></span>
            </div>
            <small style="color: #6c757d;">Reorder at: <?php echo $item['reorder_level'] !== null ? number_format($item['reorder_level'], 2) : 'Not set'; ?> <?php echo $item['unit']; ?></small>
        </div>
        <?php endwhile; ?>
        <?php else: ?>
        <p style="color: #6c757d;">No inventory items</p>
        <?php endif; ?>
    </div>

    <!-- 7. Employee Productivity -->
    <div class="report-card">
        <h3>🧑‍🌾 Employee Overview</h3>
        <?php if ($employees && $employees->num_rows > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Position</th>
                    <th>Hire Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($emp = $employees->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($emp['name'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($emp['position'] ?? ''); ?></td>
                    <td><?php echo $emp['hire_date'] ? date('M Y', strtotime($emp['hire_date'])) : 'N/A'; ?></td>
                    <td><span class="badge badge-<?php echo $emp['status']; ?>"><?php echo ucfirst($emp['status']); ?></span></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="color: #6c757d;">No employees registered</p>
        <?php endif; ?>
    </div>

    <!-- 8. Alerts & Upcoming Events -->
    <div class="report-card" style="grid-column: 1 / -1;">
        <h3>🔔 Alerts & Upcoming Events</h3>
        <?php if (count($alerts) > 0): ?>
        <div class="report-grid">
            <?php foreach ($alerts as $alert): ?>
            <div class="alert-item alert-<?php echo $alert['type']; ?>">
                <span style="font-size: 1.2rem;"><?php echo $alert['icon']; ?></span>
                <?php echo $alert['message']; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p style="color: #6c757d;">✅ No alerts at this time</p>
        <?php endif; ?>
    </div>

    <!-- 9. Recent Activities -->
    <div class="report-card" style="grid-column: 1 / -1;">
        <h3>🚜 Recent Activities</h3>
        <?php if ($recentActivities && $recentActivities->num_rows > 0): ?>
        <?php while ($activity = $recentActivities->fetch_assoc()): ?>
        <div class="activity-item">
            <div style="display: flex; justify-content: space-between;">
                <span><strong><?php echo htmlspecialchars($activity['username'] ?? ''); ?></strong> - <?php echo htmlspecialchars($activity['action'] ?? ''); ?> in <?php echo htmlspecialchars($activity['module'] ?? ''); ?></span>
                <small style="color: #6c757d;"><?php echo date('M d, H:i', strtotime($activity['created_at'])); ?></small>
            </div>
            <?php if ($activity['description']): ?>
            <small style="color: #6c757d;"><?php echo htmlspecialchars($activity['description'] ?? ''); ?></small>
            <?php endif; ?>
        </div>
        <?php endwhile; ?>
        <?php else: ?>
        <p style="color: #6c757d;">No recent activities</p>
        <?php endif; ?>
    </div>

    <!-- 10. Recommendations -->
    <div class="report-card" style="grid-column: 1 / -1; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
        <h3 style="color: white;">💡 Smart Recommendations</h3>
        <div class="report-grid">
            <?php
            $recommendations = [];
            
            // Cost reduction tips
            if ($expenseCategories && $expenseCategories->num_rows > 0) {
                $expenseCategories->data_seek(0);
                $topExpense = $expenseCategories->fetch_assoc();
                if ($topExpense['total'] > $financialSummary['total_expense'] * 0.3) {
                    $recommendations[] = "💰 Your '{$topExpense['category']}' expenses are high (" . number_format(($topExpense['total'] / $financialSummary['total_expense']) * 100, 1) . "%). Consider bulk purchasing or alternative suppliers.";
                }
            }
            
            // Profit margin advice
            if ($profitMargin < 20) {
                $recommendations[] = "📊 Your profit margin is " . number_format($profitMargin, 1) . "%. Focus on reducing costs or increasing crop yields to improve profitability.";
            } elseif ($profitMargin > 40) {
                $recommendations[] = "🎉 Excellent profit margin of " . number_format($profitMargin, 1) . "%! Consider reinvesting in farm expansion or equipment upgrades.";
            }
            
            // Inventory management
            $lowStockCount = $conn->query("SELECT COUNT(*) as count FROM inventory WHERE item_type = 'supply' AND quantity <= reorder_level AND $isolationWhere")->fetch_assoc()['count'];
            if ($lowStockCount > 0) {
                $recommendations[] = "📦 You have $lowStockCount items running low. Restock soon to avoid operational delays.";
            }
            
            // Crop diversification
            $cropTypes = $conn->query("SELECT COUNT(DISTINCT crop_type) as count FROM crops WHERE $isolationWhere")->fetch_assoc()['count'];
            if ($cropTypes < 3) {
                $recommendations[] = "🌱 Consider diversifying your crops. Growing multiple crop types reduces risk and can increase overall income.";
            }
            
            if (empty($recommendations)) {
                $recommendations[] = "✅ Your farm operations are running smoothly! Keep up the good work.";
            }
            ?>
            <?php foreach ($recommendations as $rec): ?>
            <div style="background: rgba(255,255,255,0.2); padding: 15px; border-radius: 4px;">
                <?php echo $rec; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
// Print styles for PDF
window.onbeforeprint = function() {
    document.body.style.background = 'white';
    document.title = 'FarmSaathi_Report_' + new Date().toISOString().split('T')[0];
};

window.onafterprint = function() {
    document.body.style.background = '';
};
</script>

<style media="print">
@page { 
    margin: 1.5cm; 
    size: A4;
}

body { 
    background: white !important; 
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

.main-header, 
.main-nav, 
.btn, 
button, 
.date-filter,
.main-footer { 
    display: none !important; 
}

.report-header {
    box-shadow: none !important;
    border-bottom: 2px solid #2d7a3e;
    margin-bottom: 20px !important;
}

.report-header h2 {
    font-size: 24px !important;
}

.report-card { 
    page-break-inside: avoid; 
    box-shadow: none !important;
    border: 1px solid #ddd;
    margin-bottom: 15px !important;
}

.reports-dashboard { 
    padding: 0 !important; 
    background: white !important;
}

.chart-container {
    page-break-inside: avoid;
    box-shadow: none !important;
    border: 1px solid #ddd;
}

canvas {
    max-height: 300px !important;
}

.stat-large {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

.progress-fill {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

.badge {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
    border: 1px solid #ddd;
}

/* Add page header */
@page {
    @top-center {
        content: "FarmSaathi Farm Management Report";
    }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
