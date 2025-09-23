<?php
$page_title = 'Profile';
$user = $auth->getCurrentUser();

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $userModel = new User();
        
        // Check if email is already taken by another user
        if ($email !== $user['email'] && $userModel->emailExists($email, $user['id'])) {
            $error = 'This email address is already in use by another account.';
        } else {
            $updateData = [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email
            ];

            // Handle password change
            if (!empty($new_password)) {
                if (empty($current_password)) {
                    $error = 'Current password is required to change password.';
                } elseif ($new_password !== $confirm_password) {
                    $error = 'New passwords do not match.';
                } elseif (strlen($new_password) < 8) {
                    $error = 'New password must be at least 8 characters long.';
                } else {
                    // Verify current password
                    $database = new Database();
                    $db = $database->getConnection();
                    $query = "SELECT password FROM users WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':id', $user['id']);
                    $stmt->execute();
                    $userData = $stmt->fetch();

                    if (!password_verify($current_password, $userData['password'])) {
                        $error = 'Current password is incorrect.';
                    } else {
                        $updateData['password'] = password_hash($new_password, PASSWORD_DEFAULT);
                    }
                }
            }

            if (empty($error)) {
                // Use the new updateProfile method that excludes username
                if ($userModel->updateProfile($user['id'], $updateData)) {
                    // Update session data with new values
                    $_SESSION['first_name'] = $first_name;
                    $_SESSION['last_name'] = $last_name;
                    $_SESSION['email'] = $email;
                    
                    // Get fresh user data to ensure form shows updated values
                    $user = $auth->getCurrentUser();
                    
                    $success = 'Profile updated successfully.';
                    
                    // If password was changed, show additional confirmation
                    if (!empty($new_password)) {
                        $success .= ' Your password has been changed.';
                    }
                } else {
                    $error = 'Failed to update profile. Please try again.';
                }
            }
        }
    }
}

// Get user statistics
$promptModel = new Prompt();
$categoryModel = new Category();
$totalPrompts = $promptModel->getCountByUserId($user['id']);
$totalCategories = count($categoryModel->getByUserId($user['id']));

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-user"></i> Profile</h2>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <!-- Hidden field to indicate successful update for JavaScript -->
    <script>
        // Set updated data for immediate form field updates
        window.profileUpdateSuccess = {
            first_name: '<?php echo htmlspecialchars(addslashes($first_name ?? $user['first_name'])); ?>',
            last_name: '<?php echo htmlspecialchars(addslashes($last_name ?? $user['last_name'])); ?>',
            email: '<?php echo htmlspecialchars(addslashes($email ?? $user['email'])); ?>'
        };
        
        // Trigger immediate update when page loads
        document.addEventListener('DOMContentLoaded', function() {
            if (window.profileUpdateSuccess) {
                // Small delay to ensure form elements are loaded
                setTimeout(function() {
                    // Update form fields immediately
                    const firstNameField = document.getElementById('first_name');
                    const lastNameField = document.getElementById('last_name');
                    const emailField = document.getElementById('email');
                    
                    if (firstNameField) {
                        firstNameField.value = window.profileUpdateSuccess.first_name;
                    }
                    if (lastNameField) {
                        lastNameField.value = window.profileUpdateSuccess.last_name;
                    }
                    if (emailField) {
                        emailField.value = window.profileUpdateSuccess.email;
                    }
                    
                    // Update the profile display in the stats card
                    const profileNameDisplay = document.querySelector('.card-body h5');
                    if (profileNameDisplay) {
                        profileNameDisplay.textContent = window.profileUpdateSuccess.first_name + ' ' + window.profileUpdateSuccess.last_name;
                    }
                    
                    // Clear password fields if they exist
                    const passwordFields = ['current_password', 'new_password', 'confirm_password'];
                    passwordFields.forEach(function(fieldId) {
                        const field = document.getElementById(fieldId);
                        if (field) {
                            field.value = '';
                            field.classList.remove('is-valid', 'is-invalid');
                            
                            // Remove validation feedback
                            const feedback = field.parentNode.querySelectorAll('.valid-feedback, .invalid-feedback');
                            feedback.forEach(function(fb) {
                                fb.remove();
                            });
                        }
                    });
                    
                    // Hide current password field
                    const currentPasswordField = document.getElementById('current_password');
                    if (currentPasswordField) {
                        const currentPasswordGroup = currentPasswordField.closest('.mb-3');
                        if (currentPasswordGroup) {
                            currentPasswordGroup.style.display = 'none';
                        }
                        currentPasswordField.required = false;
                    }
                    
                    console.log('Profile form updated with new values:', window.profileUpdateSuccess);
                }, 100);
            }
        });
    </script>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Profile Information -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-edit"></i> Edit Profile</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="first_name" class="form-label">First Name *</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="last_name" class="form-label">Last Name *</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" 
                               value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                        <div class="form-text">Username cannot be changed.</div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="email" class="form-label">Email Address *</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    
                    <hr>
                    
                    <h6 class="mb-3">Change Password (Optional)</h6>
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password">
                        <div class="form-text">Required only if changing password.</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password">
                                <div class="form-text">Minimum 8 characters.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Profile Statistics -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-chart-bar"></i> Your Statistics</h5>
            </div>
            <div class="card-body">
                <div class="text-center mb-4">
                    <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" 
                         style="width: 80px; height: 80px;">
                        <i class="fas fa-user fa-2x"></i>
                    </div>
                    <h5 class="mt-3 mb-1"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h5>
                    <p class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></p>
                </div>
                
                <div class="row text-center">
                    <div class="col-6">
                        <div class="border-end">
                            <h4 class="text-primary"><?php echo $totalPrompts; ?></h4>
                            <small class="text-muted">Prompts</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <h4 class="text-success"><?php echo $totalCategories; ?></h4>
                        <small class="text-muted">Categories</small>
                    </div>
                </div>
                
                <hr>
                
                <div class="small">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Role:</span>
                        <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                            <?php echo ucfirst($user['role']); ?>
                        </span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Member since:</span>
                        <span class="text-muted">
                            <?php 
                            $database = new Database();
                            $db = $database->getConnection();
                            $query = "SELECT created_at FROM users WHERE id = :id";
                            $stmt = $db->prepare($query);
                            $stmt->bindParam(':id', $user['id']);
                            $stmt->execute();
                            $userData = $stmt->fetch();
                            echo date('M Y', strtotime($userData['created_at']));
                            ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-bolt"></i> Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createPromptModal">
                        <i class="fas fa-plus"></i> Create Prompt
                    </button>
                    <a href="index.php?page=prompts" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-list"></i> View My Prompts
                    </a>
                    <a href="index.php?page=categories" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-tags"></i> Manage Categories
                    </a>
                    <a href="index.php?page=two_factor" class="btn btn-outline-warning btn-sm">
                        <i class="fas fa-shield-alt"></i> Two-Factor Authentication
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include 'layout.php';
?>

