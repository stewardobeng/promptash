<?php
$page_title = 'My Prompts';
$user = $auth->getCurrentUser();

// Initialize models
$promptModel = new Prompt();
$categoryModel = new Category();

// Check for usage notifications
require_once __DIR__ . '/../../helpers/NotificationService.php';
$notificationService = new NotificationService();
$notificationService->checkAndSendUsageNotifications($user['id']);

// Get parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : null;
$page_num = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$limit = 20;
$offset = ($page_num - 1) * $limit;

// Get prompts
if ($search) {
    $prompts = $promptModel->search($user['id'], $search, $category_filter, $limit, $offset);
} elseif ($category_filter) {
    $prompts = $promptModel->getByCategory($user['id'], $category_filter, $limit, $offset);
} else {
    $prompts = $promptModel->getByUserId($user['id'], $limit, $offset);
}

// Get categories for filter
$categories = $categoryModel->getByUserId($user['id']);

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-file-text"></i> My Prompts</h2>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPromptModal">
        <i class="fas fa-plus"></i> New Prompt
    </button>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="page" value="prompts">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control" name="search" placeholder="Search prompts..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select class="form-select" name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" 
                                <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<?php if (empty($prompts)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-file-text fa-4x text-muted mb-4"></i>
            <h4>No prompts found</h4>
            <p class="text-muted">
                <?php if ($search || $category_filter): ?>
                    Try adjusting your search criteria or <a href="index.php?page=prompts">view all prompts</a>.
                <?php else: ?>
                    Get started by creating your first prompt!
                <?php endif; ?>
            </p>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPromptModal">
                <i class="fas fa-plus"></i> Create Prompt
            </button>
        </div>
    </div>
<?php else: ?>
    <div class="row">
        <?php foreach ($prompts as $prompt): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100 prompt-card" data-id="<?php echo $prompt['id']; ?>">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title mb-0">
                                <a href="index.php?page=prompt&action=view&id=<?php echo $prompt['id']; ?>" 
                                   class="text-decoration-none stretched-link">
                                    <?php echo htmlspecialchars($prompt['title']); ?>
                                </a>
                            </h5>
                            <div class="dropdown" style="z-index: 2;">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="index.php?page=prompt&action=view&id=<?php echo $prompt['id']; ?>"><i class="fas fa-eye fa-fw me-2"></i>View</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="event.stopPropagation(); showEditPromptModal(<?php echo $prompt['id']; ?>)"><i class="fas fa-edit fa-fw me-2"></i>Edit</a></li>
                                    <li>
                                        <a class="dropdown-item" href="#" onclick="event.stopPropagation(); showSharePromptModal(<?php echo $prompt['id']; ?>, '<?php echo htmlspecialchars($prompt['title'], ENT_QUOTES); ?>')">
                                            <i class="fas fa-share-alt fa-fw me-2"></i>Share
                                        </a>
                                    </li>
                                    <?php if ($promptModel->hasUserSharedPrompt($prompt['id'], $user['id'])): ?>
                                    <li>
                                        <button class="dropdown-item text-warning" 
                                                onclick="event.stopPropagation(); unsharePrompt(<?php echo $prompt['id']; ?>)">
                                            <i class="fas fa-user-times fa-fw me-2"></i>Unshare from All
                                        </button>
                                    </li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <button class="dropdown-item text-danger" 
                                                onclick="event.stopPropagation(); showDeleteConfirmModal('Prompt', <?php echo $prompt['id']; ?>, '<?php echo htmlspecialchars($prompt['title'], ENT_QUOTES); ?>', 'delete_prompt')">
                                            <i class="fas fa-trash fa-fw me-2"></i>Delete
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        
                        <?php if ($prompt['description']): ?>
                            <p class="card-text text-muted small">
                                <?php echo htmlspecialchars(substr($prompt['description'], 0, 100)); ?>
                                <?php if (strlen($prompt['description']) > 100): ?>...<?php endif; ?>
                            </p>
                        <?php endif; ?>
                        
                        <div class="mt-auto">
                            <div class="mb-2">
                                <?php if ($prompt['category_name']): ?>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($prompt['category_name']); ?></span>
                                <?php endif; ?>
                                <?php if ($prompt['is_favorite']): ?>
                                    <span class="badge bg-warning"><i class="fas fa-star"></i> Favorite</span>
                                <?php endif; ?>
                                <?php if ($promptModel->hasUserSharedPrompt($prompt['id'], $user['id'])): ?>
                                    <span class="badge bg-info"><i class="fas fa-share-alt"></i> Shared</span>
                                <?php endif; ?>
                            </div>
                            
                            <small class="text-muted">
                                <i class="fas fa-clock"></i> <?php echo date('M j, Y', strtotime($prompt['created_at'])); ?>
                                <?php if ($prompt['usage_count'] > 0): ?>
                                    <span class="ms-2"><i class="fas fa-eye"></i> <?php echo $prompt['usage_count']; ?> uses</span>
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <?php endif; ?>

<?php
$content = ob_get_clean();
include 'layout.php';
?>