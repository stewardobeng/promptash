<?php
$page_title = 'Manage Users';
$auth->requireAdmin();

// Initialize model
$userModel = new User();
require_once __DIR__ . '/../models/MembershipTier.php';
$membershipModel = new MembershipTier();
require_once __DIR__ . '/../../helpers/Database.php';

function adminAssignPlan($userId, $tierName, $billingCycle, $membershipModel, $userModel, $username) {
    try {
        $tier = $membershipModel->getTierByName($tierName);
        if (!$tier) {
            return ['success' => false, 'message' => 'Selected plan is not available.'];
        }

        $database = new Database();
        $db = $database->getConnection();
        if (!$db) {
            return ['success' => false, 'message' => 'Database connection failed.'];
        }

        $db->beginTransaction();

        if (!$userModel->updateMembershipTier($userId, $tier['id'])) {
            throw new Exception('Unable to update user membership tier.');
        }

        $expireStmt = $db->prepare("UPDATE user_subscriptions SET status = 'expired', auto_renew = 0, cancelled_at = NOW() WHERE user_id = :user_id AND status IN ('active','trial')");
        $expireStmt->execute([':user_id' => $userId]);

        $now = new DateTime();
        if ($billingCycle === 'trial') {
            $expiresAt = (clone $now)->modify('+7 days')->format('Y-m-d H:i:s');
            $insert = $db->prepare("INSERT INTO user_subscriptions (user_id, tier_id, status, billing_cycle, started_at, expires_at, auto_renew, metadata) VALUES (:user_id, :tier_id, 'trial', 'trial', :started_at, :expires_at, 0, :metadata)");
            $insert->execute([
                ':user_id' => $userId,
                ':tier_id' => $tier['id'],
                ':started_at' => $now->format('Y-m-d H:i:s'),
                ':expires_at' => $expiresAt,
                ':metadata' => json_encode([
                    'assigned_by' => 'admin',
                    'source' => 'user_management',
                    'trial_days' => 7
                ])
            ]);
            $summary = $tier['display_name'] . ' trial (7 days)';
        } else {
            $expiresAt = (clone $now)->modify($billingCycle === 'annual' ? '+1 year' : '+1 month')->format('Y-m-d H:i:s');
            $insert = $db->prepare("INSERT INTO user_subscriptions (user_id, tier_id, status, billing_cycle, started_at, expires_at, auto_renew, metadata, last_payment_at, next_payment_at) VALUES (:user_id, :tier_id, 'active', :billing_cycle, :started_at, :expires_at, 1, :metadata, :last_payment_at, :next_payment_at)");
            $insert->execute([
                ':user_id' => $userId,
                ':tier_id' => $tier['id'],
                ':billing_cycle' => $billingCycle,
                ':started_at' => $now->format('Y-m-d H:i:s'),
                ':expires_at' => $expiresAt,
                ':metadata' => json_encode([
                    'assigned_by' => 'admin',
                    'source' => 'user_management',
                    'billing_cycle' => $billingCycle
                ]),
                ':last_payment_at' => $now->format('Y-m-d H:i:s'),
                ':next_payment_at' => $expiresAt
            ]);
            $summary = $tier['display_name'] . ' (' . ucfirst($billingCycle) . ' billing)';
        }

        $db->commit();

        return ['success' => true, 'message' => 'User "' . $username . '" has been assigned to ' . $summary . '.'];
    } catch (Exception $e) {
        if (isset($db) && $db instanceof PDO) {
            $db->rollBack();
        }
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

$error = '';
$success = '';

// Handle promotion/demotion requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['user_id'])) {
        $action = $_POST['action'];
        $user_id = (int)$_POST['user_id'];
        
        // Verify the user exists and is not the current admin
        $targetUser = $userModel->getById($user_id);
        if (!$targetUser) {
            $error = 'User not found.';
        } elseif ($user_id == $auth->getCurrentUser()['id']) {
            $error = 'You cannot modify your own membership tier.';
        } else {
            switch ($action) {
                case 'set_plan_personal_trial':
                    $result = adminAssignPlan($user_id, 'personal', 'trial', $membershipModel, $userModel, $targetUser['username']);
                    break;
                case 'set_plan_personal_monthly':
                    $result = adminAssignPlan($user_id, 'personal', 'monthly', $membershipModel, $userModel, $targetUser['username']);
                    break;
                case 'set_plan_personal_annual':
                    $result = adminAssignPlan($user_id, 'personal', 'annual', $membershipModel, $userModel, $targetUser['username']);
                    break;
                case 'set_plan_premium_monthly':
                    $result = adminAssignPlan($user_id, 'premium', 'monthly', $membershipModel, $userModel, $targetUser['username']);
                    break;
                case 'set_plan_premium_annual':
                    $result = adminAssignPlan($user_id, 'premium', 'annual', $membershipModel, $userModel, $targetUser['username']);
                    break;
                case 'promote_premium':
                    $result = adminAssignPlan($user_id, 'premium', 'annual', $membershipModel, $userModel, $targetUser['username']);
                    break;
                case 'demote_personal':
                    $result = adminAssignPlan($user_id, 'personal', 'monthly', $membershipModel, $userModel, $targetUser['username']);
                    break;
                default:
                    $result = ['success' => false, 'message' => 'Invalid action specified.'];
            }

            if (!empty($result['success'])) {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Get parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$page_num = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$limit = 20;
$offset = ($page_num - 1) * $limit;

// Get users based on search and filter
if ($search) {
    $users = $userModel->searchUsers($search, $limit, $offset);
} else {
    $users = $userModel->getAllUsers($limit, $offset);
}

// Apply filter
if ($filter === 'inactive') {
    $users = array_filter($users, function($user) {
        return !$user['is_active'];
    });
} elseif ($filter === 'admin') {
    $users = array_filter($users, function($user) {
        return $user['role'] === 'admin';
    });
}

ob_start();
?>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">User Management</h1>
                <button type="button" class="btn btn-primary" onclick="showCreateUserModal()">
                    <i class="fas fa-plus me-2"></i>Create User
                </button>
            </div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="page" value="users">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control" name="search" placeholder="Search users..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="filter">
                    <option value="">All Users</option>
                    <option value="admin" <?php echo $filter === 'admin' ? 'selected' : ''; ?>>Admins Only</option>
                    <option value="inactive" <?php echo $filter === 'inactive' ? 'selected' : ''; ?>>Inactive Users</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<?php if (empty($users)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-users fa-4x text-muted mb-4"></i>
            <h4>No users found</h4>
            <p class="text-muted">
                <?php if ($search || $filter): ?>
                    Try adjusting your search criteria or <a href="index.php?page=users">view all users</a>.
                <?php else: ?>
                    No users have registered yet.
                <?php endif; ?>
            </p>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Membership</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                        <br>
                                        <small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <?php if ($user['role'] === 'admin'): ?>
                                        <span class="badge bg-danger">Admin</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary">User</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $activeSubscription = $userModel->getActiveSubscription($user['id']);
                                    $planName = strtolower(trim($activeSubscription['tier_name'] ?? ($user['tier_name'] ?? 'personal')));
                                    $planDisplay = $activeSubscription['tier_display_name'] ?? ($user['tier_name'] ?? 'Personal Plan');
                                    $isTrial = $activeSubscription && $activeSubscription['status'] === 'trial';
                                    $badgeClass = ($planName === 'premium') ? 'bg-warning text-dark' : 'bg-secondary';
                                    $trialDaysRemaining = null;
                                    if ($isTrial && !empty($activeSubscription['expires_at'])) {
                                        try {
                                            $exp = new DateTime($activeSubscription['expires_at']);
                                            $now = new DateTime();
                                            if ($exp > $now) {
                                                $trialDaysRemaining = (int)$now->diff($exp)->format('%a');
                                            } else {
                                                $trialDaysRemaining = 0;
                                            }
                                        } catch (Exception $e) {
                                            $trialDaysRemaining = null;
                                        }
                                    }
                                    ?>
                                    <div class="d-flex flex-column">
                                        <span class="badge <?php echo $badgeClass; ?>">
                                            <?php if ($planName === 'premium'): ?>
                                                <i class="fas fa-crown"></i> Premium
                                            <?php else: ?>
                                                <i class="fas fa-user"></i> Personal
                                            <?php endif; ?>
                                            <?php if ($isTrial): ?> (Trial)<?php endif; ?>
                                        </span>
                                        <?php if ($isTrial): ?>
                                            <small class="text-info">
                                                <?php if ($trialDaysRemaining === null): ?>Trial active<?php else: ?>
                                                    <?php echo $trialDaysRemaining; ?> day<?php echo $trialDaysRemaining === 1 ? '' : 's'; ?> remaining
                                                <?php endif; ?>
                                            </small>
                                        <?php elseif ($activeSubscription): ?>
                                            <small class="text-muted">
                                                <?php echo ucfirst($activeSubscription['billing_cycle'] ?? 'monthly'); ?> billing
                                            </small>
                                        <?php else: ?>
                                            <small class="text-muted">No active subscription</small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($user['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?php echo date('M j, Y', strtotime($user['created_at'])); ?></small>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <?php if ($user['id'] != $auth->getCurrentUser()['id']): ?>
                                            <a href="<?php echo $auth->generateLoginAsLink($user['id']); ?>" class="btn btn-sm btn-outline-secondary" title="Log in as <?php echo htmlspecialchars($user['username']); ?>">
                                                <i class="fas fa-sign-in-alt"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($user['role'] !== 'admin'): ?>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-success dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="fas fa-layer-group"></i> Set Plan
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <?php if (!($activeSubscription && $activeSubscription['status'] === 'trial')): ?>
                                                        <li class="dropdown-item text-info fw-semibold">Personal Trial</li>
                                                        <li><a class="dropdown-item" href="#" onclick="setUserPlan('set_plan_personal_trial', <?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>')"><i class="fas fa-clock me-2"></i>Start 7-day Trial</a></li>
                                                        <li><hr class="dropdown-divider"></li>
                                                    <?php endif; ?>
                                                    <li class="dropdown-item text-muted fw-semibold">Personal Plan</li>
                                                    <li><a class="dropdown-item" href="#" onclick="setUserPlan('set_plan_personal_monthly', <?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>')"><i class="fas fa-calendar-alt me-2"></i>Monthly Billing</a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="setUserPlan('set_plan_personal_annual', <?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>')"><i class="fas fa-calendar me-2"></i>Annual Billing</a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li class="dropdown-item text-muted fw-semibold">Premium Plan</li>
                                                    <li><a class="dropdown-item" href="#" onclick="setUserPlan('set_plan_premium_monthly', <?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>')"><i class="fas fa-crown me-2 text-warning"></i>Monthly Billing</a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="setUserPlan('set_plan_premium_annual', <?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>')"><i class="fas fa-crown me-2 text-warning"></i>Annual Billing</a></li>
                                                </ul>
                                            </div>
                                        <?php endif; ?>

                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="showEditUserModal(<?php echo $user['id']; ?>)" title="Edit User">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-info" onclick="showResetPasswordModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" title="Reset Password">
                                            <i class="fas fa-key"></i>
                                        </button>
                                        <?php if ($user['id'] != $auth->getCurrentUser()['id']): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="showDeleteConfirmModal('user', <?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', 'delete_user')" title="Delete User">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<form id="membershipActionForm" method="POST" style="display: none;">
    <input type="hidden" name="action" id="membershipAction">
    <input type="hidden" name="user_id" id="membershipUserId">
</form>

<script>
function setUserPlan(action, userId, username) {
    const messages = {
        set_plan_personal_trial: `Start a 7-day Personal trial for "${username}"?\n\nThe trial will unlock immediately and expire automatically after 7 days.`,
        set_plan_personal_monthly: `Move "${username}" to the Personal plan with monthly billing?`,
        set_plan_personal_annual: `Move "${username}" to the Personal plan with annual billing?`,
        set_plan_premium_monthly: `Upgrade "${username}" to the Premium plan with monthly billing?`,
        set_plan_premium_annual: `Upgrade "${username}" to the Premium plan with annual billing?`
    };

    const confirmMessage = messages[action] || `Apply the selected plan to "${username}"?`;
    if (!confirm(confirmMessage)) {
        return;
    }

    document.getElementById('membershipAction').value = action;
    document.getElementById('membershipUserId').value = userId;
    document.getElementById('membershipActionForm').submit();
}
</script>

<?php
$content = ob_get_clean();
include 'layout.php';
?>
