<?php
$pageTitle = 'Dashboard - FarmSaathi';
$currentModule = 'dashboard';
require_once __DIR__ . '/../includes/header.php';

$conn = getDBConnection();
$userRole = getCurrentUserRole();

// Get statistics based on role
$stats = [];
$alerts = [];

if ($userRole === 'admin') {
    // Admin Dashboard - User Management Statistics
    
    // Total users
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    $stats['total_users'] = $result->fetch_assoc()['count'];
    
    // Admin users
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
    $stats['admin_users'] = $result->fetch_assoc()['count'];
    
    // Manager users
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'manager'");
    $stats['manager_users'] = $result->fetch_assoc()['count'];
    
    // Recent users (last 30 days)
    $result = $conn->query("
        SELECT COUNT(*) as count FROM users 
        WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
    ");
    $stats['recent_users'] = $result->fetch_assoc()['count'];
    
    // Get recent user activity
    $result = $conn->query("
        SELECT username, role, created_at 
        FROM users 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $recentUsers = [];
    while ($row = $result->fetch_assoc()) {
        $recentUsers[] = $row;
    }
    
} else {
    // Manager Dashboard - Farm Operations Statistics (User-specific data)
    $isolationWhere = getDataIsolationWhere();
    
    // Total active crops - both count and area
    $result = $conn->query("SELECT COUNT(*) as count, COALESCE(SUM(area_hectares), 0) as total_area FROM crops WHERE status = 'active' AND $isolationWhere");
    $cropsData = $result->fetch_assoc();
    $stats['total_crops'] = $cropsData['count'];
    $stats['total_crop_area'] = $cropsData['total_area'];
    
    // Total livestock - both count and types
    $result = $conn->query("SELECT COALESCE(SUM(quantity), 0) as total_count, COUNT(*) as types FROM livestock WHERE status = 'active' AND $isolationWhere");
    $livestockData = $result->fetch_assoc();
    $stats['total_livestock'] = $livestockData['total_count'];
    $stats['livestock_types'] = $livestockData['types'];
    
    // Active employees (from inventory table)
    $result = $conn->query("SELECT COUNT(*) as count FROM inventory WHERE item_type = 'employee' AND status = 'active' AND $isolationWhere");
    $stats['active_employees'] = $result->fetch_assoc()['count'];
    
    // Current month expenses (from finance table)
    $result = $conn->query("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM finance 
        WHERE type = 'expense'
        AND MONTH(transaction_date) = MONTH(CURRENT_DATE()) 
        AND YEAR(transaction_date) = YEAR(CURRENT_DATE())
        AND $isolationWhere
    ");
    $stats['monthly_expenses'] = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['total'] : 0;
    
    // Low inventory alerts (supplies only - based on reorder_level)
    $result = $conn->query("
        SELECT * FROM inventory 
        WHERE item_type = 'supply' 
        AND quantity IS NOT NULL 
        AND reorder_level IS NOT NULL 
        AND quantity <= reorder_level 
        AND $isolationWhere 
        ORDER BY (quantity / NULLIF(reorder_level, 0)) ASC 
        LIMIT 10
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => '📦',
                'message' => "Low stock: " . $row['item_name'] . " (" . $row['quantity'] . " " . $row['unit'] . " remaining, reorder at " . $row['reorder_level'] . ")",
                'link' => url('inventory/index.php')
            ];
        }
    }
    
    // Upcoming equipment maintenance (from inventory table)
    $result = $conn->query("
        SELECT * FROM inventory 
        WHERE item_type = 'equipment'
        AND maintenance_date IS NOT NULL 
        AND maintenance_date <= DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY)
        AND maintenance_date >= CURRENT_DATE()
        AND $isolationWhere
        ORDER BY maintenance_date
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $daysUntil = floor((strtotime($row['maintenance_date']) - time()) / 86400);
            $alerts[] = [
                'type' => 'info',
                'icon' => '🔧',
                'message' => "Maintenance due: " . $row['item_name'] . " in " . $daysUntil . " day(s)",
                'link' => url('inventory/index.php')
            ];
        }
    }
    
    // Approaching harvest dates
    $result = $conn->query("
        SELECT * FROM crops 
        WHERE harvest_date IS NOT NULL
        AND harvest_date <= DATE_ADD(CURRENT_DATE(), INTERVAL 14 DAY)
        AND harvest_date >= CURRENT_DATE()
        AND status = 'active'
        AND $isolationWhere
        ORDER BY harvest_date
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $daysUntil = floor((strtotime($row['harvest_date']) - time()) / 86400);
            $alerts[] = [
                'type' => 'success',
                'icon' => '🌾',
                'message' => "Harvest approaching: " . $row['crop_name'] . " in " . $daysUntil . " day(s)",
                'link' => url('crops/index.php')
            ];
        }
    }
    
    // Financial data for charts (using finance table)
    $totalIncome = 0;
    $totalExpenses = 0;
    $monthlyData = [];
    $expenseCategories = [];
    
    // Get total income from finance table
    $incomeResult = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM finance WHERE type = 'income' AND $isolationWhere");
    if ($incomeResult) {
        $totalIncome = $incomeResult->fetch_assoc()['total'];
    }
    
    // Get total expenses from finance table
    $expenseResult = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM finance WHERE type = 'expense' AND $isolationWhere");
    if ($expenseResult) {
        $totalExpenses = $expenseResult->fetch_assoc()['total'];
    }
    
    // Get monthly financial data for last 6 months
    $monthlyResult = $conn->query("
        SELECT 
            DATE_FORMAT(transaction_date, '%Y-%m') as month,
            DATE_FORMAT(transaction_date, '%b %Y') as month_label,
            SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expenses
        FROM finance
        WHERE transaction_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
        AND $isolationWhere
        GROUP BY month, month_label
        ORDER BY month ASC
    ");
    
    if ($monthlyResult) {
        while ($row = $monthlyResult->fetch_assoc()) {
            $monthlyData[] = $row;
        }
    }
    
    // Get expense breakdown by category
    $categoryResult = $conn->query("
        SELECT 
            category,
            SUM(amount) as total
        FROM finance 
        WHERE type = 'expense'
        AND $isolationWhere
        GROUP BY category
        ORDER BY total DESC
        LIMIT 5
    ");
    
    if ($categoryResult) {
        while ($row = $categoryResult->fetch_assoc()) {
            $expenseCategories[] = $row;
        }
    }
}
?>

<div class="dashboard">
    <h2>Dashboard</h2>
    <p class="dashboard-subtitle">Welcome, <?php echo htmlspecialchars(getCurrentUsername()); ?> (<?php echo ucfirst($userRole); ?>)</p>
    
    <?php if ($userRole === 'admin'): ?>
    <!-- Admin Dashboard -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-content">
                <h3>Total Users</h3>
                <p class="stat-number"><?php echo number_format($stats['total_users']); ?></p>
                <a href="<?php echo url('admin/users/index.php'); ?>" class="stat-link">Manage Users →</a>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">🔑</div>
            <div class="stat-content">
                <h3>Admin Users</h3>
                <p class="stat-number"><?php echo number_format($stats['admin_users']); ?></p>
                <a href="<?php echo url('admin/users/index.php'); ?>" class="stat-link">View Admins →</a>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">👨‍💼</div>
            <div class="stat-content">
                <h3>Manager Users</h3>
                <p class="stat-number"><?php echo number_format($stats['manager_users']); ?></p>
                <a href="<?php echo url('admin/users/index.php'); ?>" class="stat-link">View Managers →</a>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">📅</div>
            <div class="stat-content">
                <h3>New Users (30 days)</h3>
                <p class="stat-number"><?php echo number_format($stats['recent_users']); ?></p>
                <a href="<?php echo url('admin/activity/index.php'); ?>" class="stat-link">View Activity →</a>
            </div>
        </div>
    </div>

    <div class="recent-activity-section">
        <h3>Recent User Registrations</h3>
        <?php if (!empty($recentUsers)): ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Registered</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentUsers as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['username'] ?? ''); ?></td>
                        <td><span class="badge badge-<?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span></td>
                        <td><?php echo formatDate($user['created_at']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="text-muted">No recent user registrations.</p>
        <?php endif; ?>
    </div>

    <div class="quick-actions">
        <h3>Quick Actions</h3>
        <div class="action-buttons">
            <a href="<?php echo url('admin/users/add.php'); ?>" class="btn btn-primary">+ Create User Account</a>
            <a href="<?php echo url('admin/users/index.php'); ?>" class="btn btn-secondary">👥 Manage Users</a>
            <a href="<?php echo url('admin/activity/index.php'); ?>" class="btn btn-secondary">📊 View User Activity</a>
        </div>
    </div>

    <?php else: ?>
    <!-- Manager Dashboard -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">🌱</div>
            <div class="stat-content">
                <h3>Active Crops</h3>
                <p class="stat-number"><?php echo number_format($stats['total_crops']); ?></p>
                <p style="font-size: 0.9em; color: #666; margin-top: 5px;">
                    <?php echo number_format($stats['total_crop_area'], 2); ?> hectares
                </p>
                <a href="<?php echo url('crops/index.php'); ?>" class="stat-link">View Crops →</a>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">🐄</div>
            <div class="stat-content">
                <h3>Total Animals</h3>
                <p class="stat-number"><?php echo number_format($stats['total_livestock']); ?></p>
                <p style="font-size: 0.9em; color: #666; margin-top: 5px;">
                    <?php echo number_format($stats['livestock_types']); ?> livestock type<?php echo $stats['livestock_types'] != 1 ? 's' : ''; ?>
                </p>
                <a href="<?php echo url('livestock/index.php'); ?>" class="stat-link">View Livestock →</a>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-content">
                <h3>Active Employees</h3>
                <p class="stat-number"><?php echo number_format($stats['active_employees']); ?></p>
                <a href="<?php echo url('employees/index.php'); ?>" class="stat-link">View Employees →</a>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">💰</div>
            <div class="stat-content">
                <h3>Monthly Expenses</h3>
                <p class="stat-number"><?php echo formatCurrency($stats['monthly_expenses']); ?></p>
                <a href="<?php echo url('expenses/index.php'); ?>" class="stat-link">View Expenses →</a>
            </div>
        </div>
    </div>

    <!-- Financial Insights Section -->
    <div class="insights-section" style="margin: 30px 0;">
        <h3>💡 Financial Insights & Recommendations</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <!-- Financial Summary -->
            <div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h4 style="margin-top: 0; color: #2c3e50;">📊 Financial Summary</h4>
                <div style="margin: 15px 0;">
                    <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee;">
                        <span style="font-weight: 600;">Total Income:</span>
                        <span style="color: #28a745; font-weight: bold;"><?php echo formatCurrency($totalIncome); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee;">
                        <span style="font-weight: 600;">Total Expenses:</span>
                        <span style="color: #dc3545; font-weight: bold;"><?php echo formatCurrency($totalExpenses); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: 10px 0; margin-top: 10px;">
                        <span style="font-weight: 600; font-size: 1.1em;">Net Profit:</span>
                        <span style="color: <?php echo ($totalIncome - $totalExpenses) >= 0 ? '#28a745' : '#dc3545'; ?>; font-weight: bold; font-size: 1.1em;">
                            <?php echo formatCurrency($totalIncome - $totalExpenses); ?>
                        </span>
                    </div>
                </div>
                <?php 
                $profitMargin = $totalIncome > 0 ? (($totalIncome - $totalExpenses) / $totalIncome) * 100 : 0;
                ?>
                <div style="background: <?php echo $profitMargin >= 20 ? '#d4edda' : ($profitMargin >= 0 ? '#fff3cd' : '#f8d7da'); ?>; padding: 15px; border-radius: 5px; margin-top: 15px;">
                    <strong>Profit Margin: <?php echo number_format($profitMargin, 1); ?>%</strong>
                    <p style="margin: 5px 0 0 0; font-size: 0.9em;">
                        <?php if ($profitMargin >= 20): ?>
                            ✅ Excellent! Your farm is highly profitable.
                        <?php elseif ($profitMargin >= 10): ?>
                            👍 Good profit margin. Keep monitoring expenses.
                        <?php elseif ($profitMargin >= 0): ?>
                            ⚠️ Low profit margin. Consider reducing costs.
                        <?php else: ?>
                            ❌ Operating at a loss. Review expenses urgently.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            
            <!-- Top Expense Categories -->
            <div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h4 style="margin-top: 0; color: #2c3e50;">💰 Top Expense Categories</h4>
                <?php if (!empty($expenseCategories)): ?>
                    <?php foreach ($expenseCategories as $index => $category): 
                        $percentage = $totalExpenses > 0 ? ($category['total'] / $totalExpenses) * 100 : 0;
                    ?>
                    <div style="margin: 12px 0;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span style="font-weight: 600;"><?php echo htmlspecialchars($category['category'] ?? ''); ?></span>
                            <span style="color: #666;"><?php echo formatCurrency($category['total']); ?> (<?php echo number_format($percentage, 1); ?>%)</span>
                        </div>
                        <div style="background: #e9ecef; height: 8px; border-radius: 4px; overflow: hidden;">
                            <div style="background: <?php echo ['#dc3545', '#fd7e14', '#ffc107', '#28a745', '#17a2b8'][$index % 5]; ?>; height: 100%; width: <?php echo $percentage; ?>%;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #666; text-align: center; padding: 20px;">No expense data available yet.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Actionable Recommendations -->
        <div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h4 style="margin-top: 0; color: #2c3e50;">🎯 Recommended Actions for Your Farm</h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                <?php
                $recommendations = [];
                
                // Generate smart recommendations based on data
                if ($profitMargin < 10) {
                    $recommendations[] = ['icon' => '💡', 'title' => 'Reduce Operating Costs', 'desc' => 'Review your top expense categories and identify areas to cut costs without affecting productivity.'];
                }
                
                if ($stats['total_crops'] < 3) {
                    $recommendations[] = ['icon' => '🌱', 'title' => 'Diversify Crops', 'desc' => 'Consider planting more crop varieties to spread risk and increase income opportunities.'];
                }
                
                if (!empty($expenseCategories) && $expenseCategories[0]['total'] > ($totalExpenses * 0.4)) {
                    $recommendations[] = ['icon' => '⚖️', 'title' => 'Balance Expenses', 'desc' => 'Your top expense category is consuming over 40% of costs. Look for alternatives or bulk discounts.'];
                }
                
                $result = $conn->query("SELECT COUNT(*) as count FROM crops WHERE status = 'active' AND harvest_date IS NOT NULL AND harvest_date <= DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY) AND $isolationWhere");
                if ($result) {
                    $upcomingHarvests = $result->fetch_assoc()['count'];
                    if ($upcomingHarvests > 0) {
                        $recommendations[] = ['icon' => '🚜', 'title' => 'Prepare for Harvest', 'desc' => "You have $upcomingHarvests crop(s) ready for harvest soon. Arrange labor and storage in advance."];
                    }
                }
                
                $result = $conn->query("SELECT COUNT(*) as count FROM inventory WHERE item_type = 'supply' AND quantity IS NOT NULL AND reorder_level IS NOT NULL AND quantity <= reorder_level AND $isolationWhere");
                if ($result) {
                    $lowStock = $result->fetch_assoc()['count'];
                    if ($lowStock > 0) {
                        $recommendations[] = ['icon' => '📦', 'title' => 'Restock Inventory', 'desc' => "$lowStock item(s) are running low. Order supplies now to avoid delays in farm operations."];
                    }
                }
                
                if ($totalIncome > 0 && $totalExpenses > 0) {
                    $recommendations[] = ['icon' => '📈', 'title' => 'Track Monthly Trends', 'desc' => 'Review your income and expenses monthly to identify seasonal patterns and plan better.'];
                }
                
                // Default recommendations if none generated
                if (empty($recommendations)) {
                    $recommendations = [
                        ['icon' => '📊', 'title' => 'Monitor Daily', 'desc' => 'Check your dashboard daily to stay updated on farm operations and alerts.'],
                        ['icon' => '💰', 'title' => 'Record All Transactions', 'desc' => 'Keep accurate records of all income and expenses for better financial planning.'],
                        ['icon' => '🌾', 'title' => 'Plan Ahead', 'desc' => 'Use the expected harvest dates to plan your sales and cash flow in advance.']
                    ];
                }
                
                foreach ($recommendations as $rec): ?>
                <div style="border-left: 4px solid #667eea; padding: 15px; background: #f8f9fa; border-radius: 4px;">
                    <div style="font-size: 2em; margin-bottom: 8px;"><?php echo $rec['icon']; ?></div>
                    <strong style="display: block; margin-bottom: 5px; color: #2c3e50;"><?php echo $rec['title']; ?></strong>
                    <p style="margin: 0; font-size: 0.9em; color: #666;"><?php echo $rec['desc']; ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($alerts)): ?>
    <div class="alerts-section">
        <h3>Alerts & Notifications</h3>
        <div class="alerts-list">
            <?php foreach ($alerts as $alert): ?>
            <div class="alert-item alert-<?php echo $alert['type']; ?>">
                <span class="alert-icon"><?php echo $alert['icon']; ?></span>
                <span class="alert-message"><?php echo htmlspecialchars($alert['message'] ?? ''); ?></span>
                <a href="<?php echo $alert['link']; ?>" class="alert-action">View →</a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="alerts-section">
        <h3>Alerts & Notifications</h3>
        <p class="text-muted">No alerts at this time. Everything looks good! ✓</p>
    </div>
    <?php endif; ?>

    <div class="quick-actions">
        <h3>Quick Actions</h3>
        <div class="action-buttons">
            <a href="<?php echo url('crops/add.php'); ?>" class="btn btn-success">+ Add Crop</a>
            <a href="<?php echo url('livestock/add.php'); ?>" class="btn btn-success">+ Add Livestock</a>
            <a href="<?php echo url('expenses/add.php'); ?>" class="btn btn-success">+ Add Expense</a>
            <a href="<?php echo url('inventory/add.php'); ?>" class="btn btn-success">+ Add Inventory</a>
            <a href="<?php echo url('reports/index.php'); ?>" class="btn btn-primary">📊 View Reports</a>
        </div>
    </div>

    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
