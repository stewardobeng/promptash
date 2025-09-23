<?php
$page_title = 'Admin Backup & Restore';
$auth->requireAdmin();

// Initialize models
require_once 'helpers/BackupHelper.php';
$userModel = new User();
$backupHelper = new BackupHelper();

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'export_full') {
        $exportData = $backupHelper->exportApplicationData('json');
        
        if ($exportData) {
            $filename = $backupHelper->generateBackupFilename(0, 'full', 'json');
            
            // Set headers for file download
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($exportData));
            
            echo $exportData;
            exit();
        } else {
            $message = 'Full backup export failed. Please try again.';
            $message_type = 'danger';
        }
    } elseif ($action === 'import_full') {
        if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
            $uploadedFile = $_FILES['backup_file'];
            $fileContent = file_get_contents($uploadedFile['tmp_name']);
            
            // Validate file
            $validation = $backupHelper->validateBackupFile($fileContent);
            
            if ($validation['valid'] && $validation['type'] === 'full_backup') {
                // Confirm action
                if (isset($_POST['confirm_restore']) && $_POST['confirm_restore'] === 'yes') {
                    $result = $backupHelper->importApplicationData($fileContent);
                    
                    if ($result['success']) {
                        $stats = $result['stats'];
                        $message = "Full restore successful! Restored: ";
                        $items = [];
                        foreach ($stats as $table => $count) {
                            $items[] = "$count " . ucfirst($table);
                        }
                        $message .= implode(', ', $items);
                        $message_type = 'success';
                        
                        // Log out the user since data was replaced
                        $message .= " Please log in again with your restored admin account.";
                    } else {
                        $message = 'Full restore failed: ' . $result['message'];
                        $message_type = 'danger';
                    }
                } else {
                    $message = 'Please confirm that you want to restore the full backup. This will replace ALL existing data.';
                    $message_type = 'warning';
                    $pending_restore = true;
                    $pending_file = base64_encode($fileContent);
                }
            } else {
                $message = 'Invalid full backup file: ' . $validation['message'];
                $message_type = 'danger';
            }
        } else {
            $message = 'Please select a valid backup file.';
            $message_type = 'danger';
        }
    } elseif ($action === 'confirm_restore') {
        $fileContent = base64_decode($_POST['backup_data']);
        $result = $backupHelper->importApplicationData($fileContent);
        
        if ($result['success']) {
            $stats = $result['stats'];
            $message = "Full restore successful! Restored: ";
            $items = [];
            foreach ($stats as $table => $count) {
                $items[] = "$count " . ucfirst($table);
            }
            $message .= implode(', ', $items);
            $message .= " You will be logged out in 5 seconds.";
            $message_type = 'success';
            
            // Auto logout after restore
            echo "<script>setTimeout(function(){ window.location.href = 'index.php?page=logout'; }, 5000);</script>";
        } else {
            $message = 'Full restore failed: ' . $result['message'];
            $message_type = 'danger';
        }
    }
}

// Get application statistics
$appStats = $userModel->getApplicationStats();

ob_start();
?>

<div class="row">
    <div class="col-md-10 mx-auto">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-server"></i> Application Backup & Restore</h2>
            <a href="index.php?page=admin" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Admin Panel
            </a>
        </div>

        <!-- Application Statistics -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-chart-bar"></i> Application Data Summary</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-2">
                        <div class="d-flex flex-column align-items-center">
                            <i class="fas fa-users fa-2x text-primary mb-2"></i>
                            <h4 class="mb-0"><?php echo $appStats['total_users'] ?? 0; ?></h4>
                            <small class="text-muted">Total Users</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="d-flex flex-column align-items-center">
                            <i class="fas fa-user-check fa-2x text-success mb-2"></i>
                            <h4 class="mb-0"><?php echo $appStats['active_users'] ?? 0; ?></h4>
                            <small class="text-muted">Active Users</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="d-flex flex-column align-items-center">
                            <i class="fas fa-user-shield fa-2x text-danger mb-2"></i>
                            <h4 class="mb-0"><?php echo $appStats['admin_users'] ?? 0; ?></h4>
                            <small class="text-muted">Admins</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="d-flex flex-column align-items-center">
                            <i class="fas fa-file-text fa-2x text-info mb-2"></i>
                            <h4 class="mb-0"><?php echo $appStats['total_prompts'] ?? 0; ?></h4>
                            <small class="text-muted">Prompts</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="d-flex flex-column align-items-center">
                            <i class="fas fa-tags fa-2x text-warning mb-2"></i>
                            <h4 class="mb-0"><?php echo $appStats['total_categories'] ?? 0; ?></h4>
                            <small class="text-muted">Categories</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="d-flex flex-column align-items-center">
                            <i class="fas fa-share fa-2x text-secondary mb-2"></i>
                            <h4 class="mb-0"><?php echo $appStats['total_shared_prompts'] ?? 0; ?></h4>
                            <small class="text-muted">Shared</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Export Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-download"></i> Export Full Application Backup</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    Export complete application data including all users, prompts, categories, and settings. 
                    This creates a full backup that can be used to restore the entire application on a new server.
                </p>
                
                <form method="POST">
                    <input type="hidden" name="action" value="export_full">
                    
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-download"></i> Download Full Backup
                    </button>
                </form>
                
                <div class="mt-3">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>What's included in the full backup:</strong>
                        <ul class="mb-0 mt-2">
                            <li>All user accounts (passwords excluded for security)</li>
                            <li>All prompts and categories from all users</li>
                            <li>Shared prompts and relationships</li>
                            <li>Application settings and configuration</li>
                            <li>Metadata and timestamps</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Import Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-upload"></i> Restore Full Application Backup</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    Restore complete application data from a full backup file. This will <strong>replace ALL existing data</strong>.
                </p>
                
                <?php if (isset($pending_restore) && $pending_restore): ?>
                    <!-- Confirmation step -->
                    <div class="alert alert-danger">
                        <h6><i class="fas fa-exclamation-triangle"></i> Confirm Full Restore</h6>
                        <p>You are about to restore a full backup. This will:</p>
                        <ul>
                            <li><strong>DELETE ALL EXISTING DATA</strong></li>
                            <li>Replace all users, prompts, categories, and settings</li>
                            <li>Log out all current users</li>
                            <li>Cannot be undone</li>
                        </ul>
                        <p class="mb-0">Are you absolutely sure you want to proceed?</p>
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="confirm_restore">
                        <input type="hidden" name="backup_data" value="<?php echo htmlspecialchars($pending_file); ?>">
                        
                        <div class="d-flex gap-3">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-exclamation-triangle"></i> Yes, Replace All Data
                            </button>
                            <a href="index.php?page=admin_backup" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                <?php else: ?>
                    <!-- Upload form -->
                    <form method="POST" enctype="multipart/form-data" id="restoreForm">
                        <input type="hidden" name="action" value="import_full">
                        
                        <div class="mb-3">
                            <label for="backup_file" class="form-label">Full Backup File</label>
                            <input type="file" class="form-control" id="backup_file" name="backup_file" 
                                   accept=".json" required>
                            <div class="form-text">Select a JSON full backup file exported from this application.</div>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="understand_risk" required>
                            <label class="form-check-label text-danger" for="understand_risk">
                                <strong>I understand this will replace ALL existing data and cannot be undone</strong>
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-upload"></i> Restore Full Backup
                        </button>
                    </form>
                <?php endif; ?>
                
                <div class="mt-3">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>DANGER - READ CAREFULLY:</strong>
                        <ul class="mb-0 mt-2">
                            <li>This operation will DELETE ALL existing data</li>
                            <li>All current users will be logged out</li>
                            <li>You must log in with credentials from the backup</li>
                            <li>Make sure you have admin access in the backup file</li>
                            <li>This operation cannot be undone</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Help Section -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-question-circle"></i> Admin Backup Guide</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fas fa-server text-primary"></i> Server Migration</h6>
                        <ol class="small text-muted">
                            <li>Export full backup from old server</li>
                            <li>Install fresh application on new server</li>
                            <li>Complete installation wizard</li>
                            <li>Use admin backup restore feature</li>
                            <li>Test functionality thoroughly</li>
                        </ol>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-shield-alt text-success"></i> Backup Strategy</h6>
                        <ul class="small text-muted">
                            <li>Schedule regular automated backups</li>
                            <li>Store backups in multiple locations</li>
                            <li>Test restore process periodically</li>
                            <li>Keep backup files secure</li>
                            <li>Document recovery procedures</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('restoreForm')?.addEventListener('submit', function(e) {
    const fileInput = document.getElementById('backup_file');
    const checkbox = document.getElementById('understand_risk');
    
    if (!checkbox.checked) {
        e.preventDefault();
        alert('Please confirm that you understand the risks.');
        return false;
    }
    
    if (fileInput.files.length > 0) {
        const file = fileInput.files[0];
        if (!file.name.toLowerCase().endsWith('.json')) {
            e.preventDefault();
            alert('Please select a JSON file.');
            return false;
        }
        
        if (!confirm('This will DELETE ALL existing data. Are you absolutely sure?')) {
            e.preventDefault();
            return false;
        }
        
        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        submitBtn.disabled = true;
        
        // Re-enable button after a delay in case of error
        setTimeout(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }, 60000);
    }
});
</script>

<?php
$content = ob_get_clean();
include 'layout.php';
?>