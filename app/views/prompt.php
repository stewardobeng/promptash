<?php
$user = $auth->getCurrentUser();
$action = isset($_GET['action']) ? $_GET['action'] : 'view';
$prompt_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Initialize models
$promptModel = new Prompt();
$categoryModel = new Category();

$prompt = null;
$error = '';
$success = '';

// Get categories for the current user
$categories = $categoryModel->getByUserId($user['id']);

// Get prompt data for view/edit
if ($prompt_id) {
    if ($action === 'edit') {
        // For editing, user must be the owner
        $prompt = $promptModel->getById($prompt_id, $user['id']);
    } else {
        // For viewing, user can be owner or have shared access
        $prompt = $promptModel->getByIdForViewing($prompt_id, $user['id']);
    }
    
    if (!$prompt) {
        // If no prompt is found, redirect to the prompts list
        header('Location: index.php?page=prompts');
        exit();
    }
}

// Set page title
$page_title = $prompt ? htmlspecialchars($prompt['title']) : 'View Prompt';

ob_start();
?>

<?php if ($action === 'view' && $prompt): ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><?php echo htmlspecialchars($prompt['title']); ?></h2>
            <?php if ($prompt['description']): ?>
                <p class="text-muted"><?php echo htmlspecialchars($prompt['description']); ?></p>
            <?php endif; ?>
        </div>
        <div>
            <?php if ($prompt['user_id'] == $user['id']): ?>
                <button type="button" class="btn btn-outline-primary" onclick="showEditPromptModal(<?php echo $prompt['id']; ?>)">
                    <i class="fas fa-edit"></i> Edit
                </button>
            <?php endif; ?>
            <a href="index.php?page=prompts" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-file-text"></i> Prompt Content</h5>
                </div>
                <div class="card-body">
                    <div class="prompt-content">
                        <?php echo nl2br(htmlspecialchars(trim($prompt['content']))); ?>
                    </div>
                    <div class="mt-3">
                        <button class="btn btn-outline-primary" onclick="copyToClipboard(document.querySelector('.prompt-content').textContent.trim(), this, <?php echo $prompt['id']; ?>)">
                            <i class="fas fa-copy"></i> Copy to Clipboard
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-info-circle"></i> Details</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Category:</strong><br>
                        <?php if ($prompt['category_name']): ?>
                            <span class="badge bg-secondary"><?php echo htmlspecialchars($prompt['category_name']); ?></span>
                        <?php else: ?>
                            <span class="text-muted">Uncategorized</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($prompt['tags']): ?>
                        <div class="mb-3">
                            <strong>Tags:</strong><br>
                            <?php foreach (explode(',', $prompt['tags']) as $tag): ?>
                                <span class="badge bg-light text-dark border"><?php echo htmlspecialchars(trim($tag)); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($prompt['user_id'] == $user['id']): ?>
                        <div class="mb-3">
                            <strong>Favorite:</strong><br>
                            <?php if ($prompt['is_favorite']): ?>
                                <span class="badge bg-warning"><i class="fas fa-star"></i> Yes</span>
                            <?php else: ?>
                                <span class="text-muted">No</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Usage Count:</strong><br>
                            <span class="badge bg-info"><?php echo $prompt['usage_count']; ?> times</span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <strong>Created:</strong><br>
                        <small class="text-muted"><?php echo date('F j, Y \a\t g:i A', strtotime($prompt['created_at'])); ?></small>
                    </div>
                    
                    <?php if (strtotime($prompt['updated_at']) > strtotime($prompt['created_at'])): ?>
                        <div class="mb-3">
                            <strong>Last Updated:</strong><br>
                            <small class="text-muted"><?php echo date('F j, Y \a\t g:i A', strtotime($prompt['updated_at'])); ?></small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include 'layout.php';
?>