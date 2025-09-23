<?php
$page_title = 'Backup & Restore';
$user = $auth->getCurrentUser();

// Initialize models
require_once 'helpers/BackupHelper.php';
$promptModel = new Prompt();
$backupHelper = new BackupHelper();

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'export') {
        $format = $_POST['format'] ?? 'json';
        
        $exportData = $backupHelper->exportUserData($user['id'], $format);
        
        if ($exportData) {
            $filename = $backupHelper->generateBackupFilename($user['id'], 'user', $format);
            
            // Set headers for file download
            if ($format === 'json') {
                header('Content-Type: application/json');
            } else {
                header('Content-Type: text/plain');
            }
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($exportData));
            
            echo $exportData;
            exit();
        } else {
            $message = 'Export failed. Please try again.';
            $message_type = 'danger';
        }
    } elseif ($action === 'import') {
        if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
            $uploadedFile = $_FILES['backup_file'];
            $fileContent = file_get_contents($uploadedFile['tmp_name']);
            
            // Validate file
            $validation = $backupHelper->validateBackupFile($fileContent);
            
            if ($validation['valid'] && $validation['type'] === 'user_backup') {
                $result = $backupHelper->importUserData($user['id'], $fileContent);
                
                if ($result['success']) {
                    $stats = $result['stats'];
                    $message = "Import successful! Imported {$stats['categories_imported']} categories and {$stats['prompts_imported']} prompts.";
                    if (!empty($stats['errors'])) {
                        $message .= " Some items were skipped: " . implode(', ', array_slice($stats['errors'], 0, 3));
                        if (count($stats['errors']) > 3) {
                            $message .= " and " . (count($stats['errors']) - 3) . " more.";
                        }
                    }
                    $message_type = 'success';
                } else {
                    $message = 'Import failed: ' . $result['message'];
                    $message_type = 'danger';
                }
            } else {
                $message = 'Invalid backup file: ' . $validation['message'];
                $message_type = 'danger';
            }
        } else {
            $message = 'Please select a valid backup file.';
            $message_type = 'danger';
        }
    }
}

// Get backup statistics
$stats = $promptModel->getBackupStats($user['id']);

ob_start();
?>

<div class="row">
    <div class="col-md-9 mx-auto">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Backup Statistics -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-chart-bar"></i> Your Data Summary</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="d-flex flex-column align-items-center">
                            <i class="fas fa-file-text fa-2x text-primary mb-2"></i>
                            <h4 class="mb-0"><?php echo $stats['total_prompts']; ?></h4>
                            <small class="text-muted">Total Prompts</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex flex-column align-items-center">
                            <i class="fas fa-star fa-2x text-warning mb-2"></i>
                            <h4 class="mb-0"><?php echo $stats['favorite_prompts']; ?></h4>
                            <small class="text-muted">Favorites</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex flex-column align-items-center">
                            <i class="fas fa-tags fa-2x text-success mb-2"></i>
                            <h4 class="mb-0"><?php echo $stats['categories']; ?></h4>
                            <small class="text-muted">Categories</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex flex-column align-items-center">
                            <i class="fas fa-clock fa-2x text-info mb-2"></i>
                            <h4 class="mb-0">
                                <?php 
                                if ($stats['latest_prompt']) {
                                    echo date('M j', strtotime($stats['latest_prompt']));
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </h4>
                            <small class="text-muted">Latest Prompt</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Export Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-download"></i> Export Your Data</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    Export all your prompts and categories to back them up or transfer to another system.
                </p>
                
                <form method="POST" class="d-flex align-items-end gap-3">
                    <input type="hidden" name="action" value="export">
                    
                    <div class="flex-grow-1">
                        <label for="format" class="form-label">Export Format</label>
                        <select name="format" id="format" class="form-select">
                            <option value="json">JSON (recommended for re-import)</option>
                            <option value="txt">Text (human-readable)</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-download"></i> Export Data
                    </button>
                </form>
                
                <div class="mt-3">
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i>
                        <strong>JSON format:</strong> Use this for backing up data that you plan to import later. Contains all metadata and preserves categories.<br>
                        <strong>Text format:</strong> Human-readable format for viewing or printing your prompts.
                    </small>
                </div>
            </div>
        </div>

        <!-- Import Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-upload"></i> Import Data</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    Import prompts and categories from a previously exported JSON backup file.
                </p>
                
                <form method="POST" enctype="multipart/form-data" id="importForm">
                    <input type="hidden" name="action" value="import">
                    
                    <div class="mb-3">
                        <label for="backup_file" class="form-label">Backup File</label>
                        <input type="file" class="form-control" id="backup_file" name="backup_file" 
                               accept=".json" required>
                        <div class="form-text">Select a JSON backup file exported from this application.</div>
                    </div>
                    
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-upload"></i> Import Data
                    </button>
                </form>
                
                <div class="mt-3">
                    <div class="alert alert-info">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Import Notes:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Only JSON files exported from this application are supported</li>
                            <li>Duplicate prompts (same title and content) will be skipped</li>
                            <li>Categories with the same name will be merged</li>
                            <li>Your existing data will not be deleted or modified</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Help Section -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-question-circle"></i> Backup Help</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fas fa-shield-alt text-success"></i> Why Backup?</h6>
                        <ul class="small text-muted">
                            <li>Protect against data loss</li>
                            <li>Transfer data between accounts</li>
                            <li>Archive old prompts</li>
                            <li>Share prompt collections</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-lightbulb text-warning"></i> Best Practices</h6>
                        <ul class="small text-muted">
                            <li>Export regularly (weekly/monthly)</li>
                            <li>Store backups in multiple locations</li>
                            <li>Use JSON format for complete backups</li>
                            <li>Test restore occasionally</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('importForm').addEventListener('submit', function(e) {
    const fileInput = document.getElementById('backup_file');
    if (fileInput.files.length > 0) {
        const file = fileInput.files[0];
        if (!file.name.toLowerCase().endsWith('.json')) {
            e.preventDefault();
            alert('Please select a JSON file.');
            return false;
        }
        
        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importing...';
        submitBtn.disabled = true;
        
        // Re-enable button after a delay in case of error
        setTimeout(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }, 30000);
    }
});
</script>

<?php
$content = ob_get_clean();
include 'layout.php';
?>