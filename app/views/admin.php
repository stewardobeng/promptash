<?php
$page_title = 'Admin Panel';
$auth->requireAdmin();

// Initialize models
$userModel = new User();
$promptModel = new Prompt();
$categoryModel = new Category(); // Initialize Category model

// Initialize membership models
require_once __DIR__ . '/../models/MembershipTier.php';
require_once __DIR__ . '/../models/UsageTracker.php';
$membershipModel = new MembershipTier();
$usageTracker = new UsageTracker();

// Get membership statistics
$membershipStats = $userModel->getMembershipStats();
$tierStats = $membershipModel->getTierStatistics();

// Get usage analytics
$currentMonthUsage = $usageTracker->getSystemUsageStats();
$lastMonthUsage = $usageTracker->getSystemUsageStats(date('Y-m-01', strtotime('-1 month')));
$usersApproachingLimits = $usageTracker->getUsersApproachingLimits(75);

// Calculate usage trends
$usageTrends = [];
foreach (['prompt_creation', 'ai_generation', 'category_creation', 'bookmark_creation'] as $type) {
    $current = isset($currentMonthUsage['stats'][$type]) ? $currentMonthUsage['stats'][$type]['total_usage'] : 0;
    $last = isset($lastMonthUsage['stats'][$type]) ? $lastMonthUsage['stats'][$type]['total_usage'] : 0;
    
    $trend = 0;
    if ($last > 0) {
        $trend = round((($current - $last) / $last) * 100, 1);
    } elseif ($current > 0) {
        $trend = 100;
    }
    
    $usageTrends[$type] = [
        'current' => $current,
        'last' => $last,
        'trend' => $trend,
        'trend_direction' => $trend > 0 ? 'up' : ($trend < 0 ? 'down' : 'same')
    ];
}

// Get statistics
$totalUsers = $userModel->getUserCount();
$totalPrompts = 0;
$activeUsers = 0;

// Get recent users
$recentUsers = $userModel->getAllUsers(3, 0);

// Calculate total prompts and active users
foreach ($userModel->getAllUsers(1000, 0) as $user) {
    $userPrompts = $promptModel->getCountByUserId($user['id']);
    $totalPrompts += $userPrompts;
    if ($user['is_active']) {
        $activeUsers++;
    }
}

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-cog"></i> Admin Panel</h2>
    <div>
        <a href="index.php?page=users" class="btn btn-primary">
            <i class="fas fa-users"></i> Manage Users
        </a>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card text-center border-primary">
            <div class="card-body">
                <i class="fas fa-users fa-3x text-primary mb-3"></i>
                <h3 class="card-title"><?php echo $totalUsers; ?></h3>
                <p class="card-text text-muted">Total Users</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card text-center border-success">
            <div class="card-body">
                <i class="fas fa-user-check fa-3x text-success mb-3"></i>
                <h3 class="card-title"><?php echo $activeUsers; ?></h3>
                <p class="card-text text-muted">Active Users</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card text-center border-warning">
            <div class="card-body">
                <i class="fas fa-crown fa-3x text-warning mb-3"></i>
                <?php 
                // Get premium users count from users_by_tier for consistency
                $premiumUsersCount = 0;
                if (isset($membershipStats['users_by_tier'])) {
                    foreach ($membershipStats['users_by_tier'] as $tierData) {
                        if ($tierData['name'] === 'premium') {
                            $premiumUsersCount = $tierData['user_count'];
                            break;
                        }
                    }
                }
                ?>
                <h3 class="card-title"><?php echo $premiumUsersCount; ?></h3>
                <p class="card-text text-muted">Premium Users</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card text-center border-info">
            <div class="card-body">
                <i class="fas fa-chart-line fa-3x text-info mb-3"></i>
                <?php 
                    require_once __DIR__ . '/../models/AppSettings.php';
                    $appSettings = new AppSettings();
                    $revenue = isset($membershipStats['monthly_revenue']) ? $membershipStats['monthly_revenue'] : 0;
                ?>
                <h3 class="card-title"><?php echo $appSettings->formatPrice($revenue); ?></h3>
                <p class="card-text text-muted">Monthly Revenue</p>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-chart-pie"></i> Membership Overview</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php if (!empty($membershipStats['users_by_tier'])): ?>
                        <?php foreach ($membershipStats['users_by_tier'] as $tierData): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card border-0 bg-light">
                                    <div class="card-body text-center">
                                        <?php if ($tierData['name'] === 'premium'): ?>
                                            <i class="fas fa-crown fa-2x text-warning mb-2"></i>
                                        <?php else: ?>
                                            <i class="fas fa-user fa-2x text-secondary mb-2"></i>
                                        <?php endif; ?>
                                        <h5><?php echo htmlspecialchars($tierData['display_name']); ?></h5>
                                        <h3 class="text-primary"><?php echo number_format($tierData['user_count']); ?></h3>
                                        <small class="text-muted">users</small>
                                        <?php if ($totalUsers > 0): ?>
                                            <div class="progress mt-2" style="height: 6px;">
                                                <div class="progress-bar <?php echo $tierData['name'] === 'premium' ? 'bg-warning' : 'bg-secondary'; ?>" 
                                                     style="width: <?php echo round(($tierData['user_count'] / $totalUsers) * 100, 1); ?>%"></div>
                                            </div>
                                            <small class="text-muted"><?php echo round(($tierData['user_count'] / $totalUsers) * 100, 1); ?>% of users</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <?php if (isset($membershipStats['expiring_subscriptions']) && $membershipStats['expiring_subscriptions'] > 0): ?>
                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Attention:</strong> <?php echo $membershipStats['expiring_subscriptions']; ?> subscription(s) expire in the next 30 days.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-chart-line"></i> Usage Analytics</h5>
                <small class="text-muted">System-wide usage statistics and trends</small>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card border-primary h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-file-text fa-2x text-primary mb-3"></i>
                                <h4 class="text-primary"><?php echo number_format($usageTrends['prompt_creation']['current']); ?></h4>
                                <p class="card-text text-muted mb-2">Prompts Created This Month</p>
                                <?php if ($usageTrends['prompt_creation']['trend'] != 0): ?>
                                    <small class="badge <?php echo $usageTrends['prompt_creation']['trend_direction'] === 'up' ? 'bg-success' : 'bg-danger'; ?>">
                                        <i class="fas fa-arrow-<?php echo $usageTrends['prompt_creation']['trend_direction']; ?>"></i>
                                        <?php echo abs($usageTrends['prompt_creation']['trend']); ?>% vs last month
                                    </small>
                                <?php else: ?>
                                    <small class="badge bg-secondary">No change</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card border-success h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-robot fa-2x text-success mb-3"></i>
                                <h4 class="text-success"><?php echo number_format($usageTrends['ai_generation']['current']); ?></h4>
                                <p class="card-text text-muted mb-2">AI Generations This Month</p>
                                <?php if ($usageTrends['ai_generation']['trend'] != 0): ?>
                                    <small class="badge <?php echo $usageTrends['ai_generation']['trend_direction'] === 'up' ? 'bg-success' : 'bg-danger'; ?>">
                                        <i class="fas fa-arrow-<?php echo $usageTrends['ai_generation']['trend_direction']; ?>"></i>
                                        <?php echo abs($usageTrends['ai_generation']['trend']); ?>% vs last month
                                    </small>
                                <?php else: ?>
                                    <small class="badge bg-secondary">No change</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card border-warning h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-tags fa-2x text-warning mb-3"></i>
                                <h4 class="text-warning"><?php echo number_format($usageTrends['category_creation']['current']); ?></h4>
                                <p class="card-text text-muted mb-2">Categories Created This Month</p>
                                <?php if ($usageTrends['category_creation']['trend'] != 0): ?>
                                    <small class="badge <?php echo $usageTrends['category_creation']['trend_direction'] === 'up' ? 'bg-success' : 'bg-danger'; ?>">
                                        <i class="fas fa-arrow-<?php echo $usageTrends['category_creation']['trend_direction']; ?>"></i>
                                        <?php echo abs($usageTrends['category_creation']['trend']); ?>% vs last month
                                    </small>
                                <?php else: ?>
                                    <small class="badge bg-secondary">No change</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card border-info h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-bookmark fa-2x text-info mb-3"></i>
                                <h4 class="text-info"><?php echo number_format($usageTrends['bookmark_creation']['current']); ?></h4>
                                <p class="card-text text-muted mb-2">Bookmarks Created This Month</p>
                                <?php if ($usageTrends['bookmark_creation']['trend'] != 0): ?>
                                    <small class="badge <?php echo $usageTrends['bookmark_creation']['trend_direction'] === 'up' ? 'bg-success' : 'bg-danger'; ?>">
                                        <i class="fas fa-arrow-<?php echo $usageTrends['bookmark_creation']['trend_direction']; ?>"></i>
                                        <?php echo abs($usageTrends['bookmark_creation']['trend']); ?>% vs last month
                                    </small>
                                <?php else: ?>
                                    <small class="badge bg-secondary">No change</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fas fa-users"></i> Active Users by Usage Type</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Usage Type</th>
                                        <th>Active Users</th>
                                        <th>Avg per User</th>
                                        <th>Peak Usage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $usage_labels = [
                                        'prompt_creation' => 'Prompt Creation',
                                        'ai_generation' => 'AI Generation', 
                                        'category_creation' => 'Category Creation',
                                        'bookmark_creation' => 'Bookmark Creation'
                                    ];
                                    
                                    // Safe array access with fallback data
                                    $statsData = [];
                                    if (isset($currentMonthUsage['stats']) && is_array($currentMonthUsage['stats'])) {
                                        $statsData = $currentMonthUsage['stats'];
                                    } else {
                                        // Provide fallback data when stats are not available
                                        $statsData = [
                                            'prompt_creation' => ['active_users' => 0, 'avg_usage_per_user' => 0, 'max_usage' => 0],
                                            'ai_generation' => ['active_users' => 0, 'avg_usage_per_user' => 0, 'max_usage' => 0],
                                            'category_creation' => ['active_users' => 0, 'avg_usage_per_user' => 0, 'max_usage' => 0],
                                            'bookmark_creation' => ['active_users' => 0, 'avg_usage_per_user' => 0, 'max_usage' => 0]
                                        ];
                                    }
                                    
                                    foreach ($statsData as $type => $stats): ?>
                                        <tr>
                                            <td><?php echo $usage_labels[$type] ?? ucwords(str_replace('_', ' ', $type)); ?></td>
                                            <td><span class="badge bg-primary"><?php echo number_format($stats['active_users'] ?? 0); ?></span></td>
                                            <td><?php echo number_format($stats['avg_usage_per_user'] ?? 0, 1); ?></td>
                                            <td><?php echo number_format($stats['max_usage'] ?? 0); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-exclamation-triangle"></i> Users Approaching Limits</h6>
                        <?php if (!empty($usersApproachingLimits)): ?>
                            <div class="list-group" style="max-height: 200px; overflow-y: auto;">
                                <?php foreach (array_slice($usersApproachingLimits, 0, 5) as $user): ?>
                                    <div class="list-group-item list-group-item-action py-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <small class="fw-bold"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></small>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                            </div>
                                            <div class="text-end">
                                                <small class="badge bg-warning">
                                                    <?php echo number_format($user['usage_count']); ?>/<?php echo number_format($user['usage_limit']); ?>
                                                </small>
                                                <br><small class="text-muted"><?php echo ucwords(str_replace('_', ' ', $user['usage_type'])); ?></small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (count($usersApproachingLimits) > 5): ?>
                                    <div class="list-group-item text-center py-2">
                                        <small class="text-muted">+ <?php echo count($usersApproachingLimits) - 5; ?> more users</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-3">
                                <i class="fas fa-check-circle fa-2x mb-2"></i>
                                <p class="mb-0">All users are within their usage limits</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-chart-bar"></i> Revenue Analytics</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3 mb-3">
                        <h6 class="text-muted">This Month</h6>
                        <h4 class="text-success"><?php echo $appSettings->formatPrice($membershipStats['monthly_revenue'] ?? 0); ?></h4>
                    </div>
                    <div class="col-md-3 mb-3">
                        <h6 class="text-muted">Active Subscriptions</h6>
                        <h4 class="text-primary"><?php echo number_format($membershipStats['active_subscriptions'] ?? 0); ?></h4>
                        <small class="text-muted">Subscription records</small>
                    </div>
                    <div class="col-md-3 mb-3">
                        <h6 class="text-muted">Conversion Rate</h6>
                        <?php 
                        $conversionRate = 0;
                        if ($totalUsers > 0 && $premiumUsersCount > 0) {
                            $conversionRate = ($premiumUsersCount / $totalUsers) * 100;
                        }
                        ?>
                        <h4 class="text-info"><?php echo number_format($conversionRate, 1); ?>%</h4>
                        <small class="text-muted">Users to Premium</small>
                    </div>
                    <div class="col-md-3 mb-3">
                        <h6 class="text-muted">Expiring Soon</h6>
                        <h4 class="text-warning"><?php echo number_format($membershipStats['expiring_subscriptions'] ?? 0); ?></h4>
                    </div>
                </div>
                
                <?php if (isset($membershipStats['expiring_subscriptions']) && $membershipStats['expiring_subscriptions'] > 0): ?>
                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Action Required:</strong> <?php echo $membershipStats['expiring_subscriptions']; ?> subscription(s) expire in the next 30 days. 
                        Consider sending renewal reminders.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-download"></i> Export Reports</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">Generate detailed reports for analysis</p>
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-primary btn-sm" onclick="exportUsageReport()">
                        <i class="fas fa-chart-line"></i> Usage Report
                    </button>
                    <button class="btn btn-outline-success btn-sm" onclick="exportRevenueReport()">
                        <i class="fas fa-chart-line"></i> Revenue Report
                    </button>
                    <button class="btn btn-outline-info btn-sm" onclick="exportUserReport()">
                        <i class="fas fa-users"></i> User Report
                    </button>
                    <button class="btn btn-outline-warning btn-sm" onclick="exportSubscriptionReport()">
                        <i class="fas fa-crown"></i> Subscription Report
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="fas fa-user-plus"></i> Recent Users</h5>
                <a href="index.php?page=users" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($recentUsers)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-users fa-3x mb-3"></i>
                        <p>No users registered yet.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recentUsers as $user): ?>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1">
                                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                            <?php if ($user['role'] === 'admin'): ?>
                                                <span class="badge bg-danger">Admin</span>
                                            <?php endif; ?>
                                            <?php if (!$user['is_active']): ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </h6>
                                        <p class="mb-1 text-muted small">
                                            @<?php echo htmlspecialchars($user['username']); ?> • 
                                            <?php echo htmlspecialchars($user['email']); ?>
                                        </p>
                                        <small class="text-muted">
                                            Joined <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-info-circle"></i> System Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6">
                        <strong>Application:</strong><br>
                        <span class="text-muted">Promptash v1.0.0</span>
                    </div>
                    <div class="col-6">
                        <strong>PHP Version:</strong><br>
                        <span class="text-muted"><?php echo PHP_VERSION; ?></span>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-6">
                        <strong>Database:</strong><br>
                        <span class="text-muted">MySQL</span>
                    </div>
                    <div class="col-6">
                        <strong>Server:</strong><br>
                        <span class="text-muted"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></span>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-12">
                        <strong>Installation Date:</strong><br>
                        <span class="text-muted">
                            <?php 
                            $configFile = '../config/database.php';
                            if (file_exists($configFile)) {
                                echo date('F j, Y \a\t g:i A', filemtime($configFile));
                            } else {
                                echo 'Unknown';
                            }
                            ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-bolt"></i> Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <a href="index.php?page=users" class="btn btn-outline-primary w-100">
                            <i class="fas fa-users"></i> Manage Users
                        </a>
                    </div>
                    <div class="col-md-4 mb-2">
                        <a href="index.php?page=settings#membershipTiers" class="btn btn-outline-success w-100">
                            <i class="fas fa-crown"></i> Membership Plans
                        </a>
                    </div>
                    <div class="col-md-4 mb-2">
                        <a href="index.php?page=admin_backup" class="btn btn-outline-warning w-100">
                            <i class="fas fa-database"></i> Backup & Restore
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include 'layout.php';
?>

<script>
// Export functionality for analytics reports
function exportUsageReport() {
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
    button.disabled = true;
    
    fetch('index.php?page=api&action=export_usage_report', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        }
    })
    .then(response => {
        if (response.ok) {
            return response.blob();
        }
        throw new Error('Export failed');
    })
    .then(blob => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'usage_report_' + new Date().toISOString().split('T')[0] + '.csv';
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        showAlert('success', 'Usage report exported successfully!');
    })
    .catch(error => {
        showAlert('danger', 'Failed to export usage report: ' + error.message);
    })
    .finally(() => {
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

function exportRevenueReport() {
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
    button.disabled = true;
    
    fetch('index.php?page=api&action=export_revenue_report', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        }
    })
    .then(response => {
        if (response.ok) {
            return response.blob();
        }
        throw new Error('Export failed');
    })
    .then(blob => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'revenue_report_' + new Date().toISOString().split('T')[0] + '.csv';
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        showAlert('success', 'Revenue report exported successfully!');
    })
    .catch(error => {
        showAlert('danger', 'Failed to export revenue report: ' + error.message);
    })
    .finally(() => {
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

function exportUserReport() {
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
    button.disabled = true;
    
    fetch('index.php?page=api&action=export_user_report', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        }
    })
    .then(response => {
        if (response.ok) {
            return response.blob();
        }
        throw new Error('Export failed');
    })
    .then(blob => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'user_report_' + new Date().toISOString().split('T')[0] + '.csv';
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        showAlert('success', 'User report exported successfully!');
    })
    .catch(error => {
        showAlert('danger', 'Failed to export user report: ' + error.message);
    })
    .finally(() => {
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

function exportSubscriptionReport() {
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
    button.disabled = true;
    
    fetch('index.php?page=api&action=export_subscription_report', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        }
    })
    .then(response => {
        if (response.ok) {
            return response.blob();
        }
        throw new Error('Export failed');
    })
    .then(blob => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'subscription_report_' + new Date().toISOString().split('T')[0] + '.csv';
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        showAlert('success', 'Subscription report exported successfully!');
    })
    .catch(error => {
        showAlert('danger', 'Failed to export subscription report: ' + error.message);
    })
    .finally(() => {
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

// Helper function to show alerts
function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const container = document.querySelector('.d-flex.justify-content-between');
    container.parentNode.insertBefore(alertDiv, container.nextSibling);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}
</script>