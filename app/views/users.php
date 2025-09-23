<?php
$page_title = 'Manage Users';
$auth->requireAdmin();

// Initialize model
$userModel = new User();
require_once __DIR__ . '/../models/MembershipTier.php';
$membershipModel = new MembershipTier();

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
                case 'promote_premium':
                    $premiumTier = $membershipModel->getTierByName('premium');
                    if ($premiumTier && $userModel->updateMembershipTier($user_id, $premiumTier['id'])) {
                        $success = 'User "' . htmlspecialchars($targetUser['username']) . '" has been promoted to Premium membership!';
                    } else {
                        $error = 'Failed to promote user to Premium membership.';
                    }
                    break;
                    
                case 'demote_free':
                    $freeTier = $membershipModel->getTierByName('free');
                    if ($freeTier && $userModel->updateMembershipTier($user_id, $freeTier['id'])) {
                        $success = 'User "' . htmlspecialchars($targetUser['username']) . '" has been demoted to Free membership.';
                    } else {
                        $error = 'Failed to demote user to Free membership.';
                    }
                    break;
                    
                default:
                    $error = 'Invalid action specified.';
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
                                    // Check tier name (handle both 'name' and 'display_name' fields)
                                    $isPremium = false;
                                    if (isset($user['tier_name'])) {
                                        $tierName = strtolower(trim($user['tier_name']));
                                        // Check for premium in both name and display_name
                                        $isPremium = ($tierName === 'premium' || strpos($tierName, 'premium') !== false);
                                    }
                                    
                                    // Debug: Uncomment next line to see tier data
                                    echo "";
                                    ?>
                                    <?php if ($isPremium): ?>
                                        <span class="badge bg-warning text-dark"><i class="fas fa-crown"></i> Premium</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Free</span>
                                    <?php endif; ?>
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
                                            <?php 
                                            // Use the same premium check logic as the display
                                            $isPremiumUser = false;
                                            if (isset($user['tier_name'])) {
                                                $tierName = strtolower(trim($user['tier_name']));
                                                // Check for premium in both name and display_name
                                                $isPremiumUser = ($tierName === 'premium' || strpos($tierName, 'premium') !== false);
                                            }
                                            ?>
                                            <?php if (!$isPremiumUser): ?>
                                                <button type="button" class="btn btn-sm btn-outline-success" 
                                                        onclick="promoteToPremium(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" 
                                                        title="Promote to Premium">
                                                    <i class="fas fa-crown"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-outline-warning" 
                                                        onclick="demoteToFree(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" 
                                                        title="Demote to Free">
                                                    <i class="fas fa-user-minus"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                onclick="showEditUserModal(<?php echo $user['id']; ?>)" 
                                                title="Edit User">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-info" 
                                                onclick="showResetPasswordModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" 
                                                title="Reset Password">
                                            <i class="fas fa-key"></i>
                                        </button>
                                        <?php if ($user['id'] != $auth->getCurrentUser()['id']): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    onclick="showDeleteConfirmModal('user', <?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', 'delete_user')" 
                                                    title="Delete User">
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
function promoteToPremium(userId, username) {
    if (confirm(`Are you sure you want to promote "${username}" to Premium membership?\n\nThis will give them unlimited prompts and advanced features.`)) {
        document.getElementById('membershipAction').value = 'promote_premium';
        document.getElementById('membershipUserId').value = userId;
        document.getElementById('membershipActionForm').submit();
    }
}

function demoteToFree(userId, username) {
    if (confirm(`Are you sure you want to demote "${username}" to Free membership?\n\nThis will limit their access to free tier features only.\n\nWarning: This action will immediately restrict their access.`)) {
        document.getElementById('membershipAction').value = 'demote_free';
        document.getElementById('membershipUserId').value = userId;
        document.getElementById('membershipActionForm').submit();
    }
}
</script>

<?php
$content = ob_get_clean();
include 'layout.php';
?>