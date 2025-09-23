<?php
$page_title = 'Dashboard';
$user = $auth->getCurrentUser();

// Initialize models
$promptModel = new Prompt();
$categoryModel = new Category();
$bookmarkModel = new Bookmark(); 
$noteModel = new Note();
$documentModel = new Document();
$videoModel = new Video();

// Initialize membership models
require_once __DIR__ . '/../models/MembershipTier.php';
require_once __DIR__ . '/../models/UsageTracker.php';
require_once __DIR__ . '/../../helpers/NotificationService.php';
$membershipModel = new MembershipTier();
$usageTracker = new UsageTracker();
$notificationService = new NotificationService();

// Check for usage notifications when user visits dashboard
$notificationService->checkAndSendUsageNotifications($user['id']);

// Get membership and usage information
$usageSummary = $usageTracker->getUserUsageSummary($user['id']);

// Provide fallback data if usage summary is empty
if (empty($usageSummary) || !isset($usageSummary['tier']) || !isset($usageSummary['usage'])) {
    // Get basic tier information as fallback
    $freeTier = $membershipModel->getTierByName('free');
    $usageSummary = [
        'tier' => $freeTier ?: [
            'id' => 1,
            'name' => 'free',
            'display_name' => 'Free',
            'description' => 'Basic plan'
        ],
        'usage' => [
            'prompt_creation' => [
                'used' => 0,
                'limit' => 50,
                'percentage' => 0,
                'is_unlimited' => false,
                'is_at_limit' => false,
                'is_near_limit' => false
            ],
            'ai_generation' => [
                'used' => 0,
                'limit' => 50,
                'percentage' => 0,
                'is_unlimited' => false,
                'is_at_limit' => false,
                'is_near_limit' => false
            ],
            'category_creation' => [
                'used' => 0,
                'limit' => 10,
                'percentage' => 0,
                'is_unlimited' => false,
                'is_at_limit' => false,
                'is_near_limit' => false
            ],
            'note_creation' => [
                'used' => 0,
                'limit' => 50,
                'percentage' => 0,
                'is_unlimited' => false,
                'is_at_limit' => false,
                'is_near_limit' => false
            ],
            'document_creation' => [
                'used' => 0,
                'limit' => 50,
                'percentage' => 0,
                'is_unlimited' => false,
                'is_at_limit' => false,
                'is_near_limit' => false
            ],
            'video_creation' => [
                'used' => 0,
                'limit' => 50,
                'percentage' => 0,
                'is_unlimited' => false,
                'is_at_limit' => false,
                'is_near_limit' => false
            ]
        ]
    ];
}

// Get statistics
$totalPrompts = $promptModel->getCountByUserId($user['id']);
$totalBookmarks = $bookmarkModel->getCountByUserId($user['id']); 
$totalNotes = $noteModel->getCountByUserId($user['id']);
$totalDocuments = $documentModel->getCountByUserId($user['id']);
$totalVideos = $videoModel->getCountByUserId($user['id']);
$recentPrompts = $promptModel->getByUserId($user['id'], 3, 0);
$categories = $categoryModel->getByUserId($user['id']);

ob_start();
?>

<div class="row">
    <div class="col-12 mb-4">
        <div class="card <?php echo (isset($usageSummary['tier']['name']) && $usageSummary['tier']['name'] === 'premium') ? 'border-warning' : 'border-secondary'; ?>">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <?php if (isset($usageSummary['tier']['name']) && $usageSummary['tier']['name'] === 'premium'): ?>
                                <div class="me-3">
                                    <i class="fas fa-crown fa-2x text-warning"></i>
                                </div>
                            <?php else: ?>
                                <div class="me-3">
                                    <i class="fas fa-user fa-2x text-secondary"></i>
                                </div>
                            <?php endif; ?>
                            <div>
                                <h5 class="mb-1">
                                    <?php echo htmlspecialchars($usageSummary['tier']['display_name'] ?? 'Free'); ?> Member
                                    <?php if (isset($usageSummary['tier']['name']) && $usageSummary['tier']['name'] === 'premium'): ?>
                                        <span class="badge bg-warning text-dark ms-2">Premium</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary ms-2">Free</span>
                                    <?php endif; ?>
                                </h5>
                                <?php if (!isset($usageSummary['tier']['name']) || $usageSummary['tier']['name'] === 'free'): ?>
                                    <p class="text-muted mb-0">Upgrade to unlock unlimited prompts and advanced features</p>
                                <?php else: ?>
                                    <p class="text-muted mb-0">Thank you for being a Premium member!</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="border-end">
                                    <h6 class="mb-0"><?php echo number_format($usageSummary['usage']['prompt_creation']['used'] ?? 0); ?></h6>
                                    <small class="text-muted">Prompts Used</small>
                                    <?php if (!($usageSummary['usage']['prompt_creation']['is_unlimited'] ?? false)): ?>
                                        <div class="progress mt-1" style="height: 3px;">
                                            <div class="progress-bar bg-primary" style="width: <?php echo ($usageSummary['usage']['prompt_creation']['percentage'] ?? 0); ?>%"></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border-end">
                                    <h6 class="mb-0"><?php echo number_format($usageSummary['usage']['ai_generation']['used'] ?? 0); ?></h6>
                                    <small class="text-muted">AI Generations</small>
                                    <?php if (!($usageSummary['usage']['ai_generation']['is_unlimited'] ?? false)): ?>
                                        <div class="progress mt-1" style="height: 3px;">
                                            <div class="progress-bar bg-success" style="width: <?php echo ($usageSummary['usage']['ai_generation']['percentage'] ?? 0); ?>%"></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-4">
                                <?php if (!isset($usageSummary['tier']['name']) || $usageSummary['tier']['name'] === 'free'): ?>
                                    <a href="index.php?page=upgrade" class="btn btn-primary btn-sm">
                                        <i class="fas fa-arrow-up"></i> Upgrade
                                    </a>
                                <?php else: ?>
                                    <div>
                                        <h6 class="mb-0 text-success"><i class="fas fa-check"></i></h6>
                                        <small class="text-muted">Premium Active</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (!isset($usageSummary['tier']['name']) || $usageSummary['tier']['name'] === 'free'): ?>
                    <?php
                    $showWarning = false;
                    $warningMessage = '';
                    
                    if ($usageSummary['usage']['prompt_creation']['is_near_limit'] ?? false) {
                        $showWarning = true;
                        $warningMessage = "You're approaching your prompt limit. Upgrade to Premium for unlimited prompts!";
                    } elseif ($usageSummary['usage']['ai_generation']['is_near_limit'] ?? false) {
                        $showWarning = true;
                        $warningMessage = "You're approaching your AI generation limit. Upgrade to Premium for 300 AI generations per month!";
                    }
                    ?>
                    
                    <?php if ($showWarning): ?>
                        <div class="alert alert-warning mt-3 mb-0">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Usage Alert:</strong> <?php echo $warningMessage; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-3 mb-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-file-text fa-3x text-primary mb-3"></i>
                <h3 class="card-title"><?php echo $totalPrompts; ?></h3>
                <p class="card-text text-muted">Total Prompts</p>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-bookmark fa-3x text-info mb-3"></i>
                <h3 class="card-title"><?php echo $totalBookmarks; ?></h3>
                <p class="card-text text-muted">Total Bookmarks</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-sticky-note fa-3x text-success mb-3"></i>
                <h3 class="card-title"><?php echo $totalNotes; ?></h3>
                <p class="card-text text-muted">Total Notes</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-folder-open fa-3x text-warning mb-3"></i>
                <h3 class="card-title"><?php echo $totalDocuments; ?></h3>
                <p class="card-text text-muted">Total Documents</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-bolt"></i> Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPromptModal">
                        <i class="fas fa-plus"></i> Create New Prompt
                    </button>
                    <button type="button" class="btn btn-info" onclick="showCreateBookmarkModal()">
                        <i class="fas fa-bookmark"></i> Add Bookmark
                    </button>
                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#createCategoryModal">
                        <i class="fas fa-tag"></i> Add Category
                    </button>
                    <a href="index.php?page=prompts" class="btn btn-outline-secondary">
                        <i class="fas fa-list"></i> View All Prompts
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="fas fa-clock"></i> Recent Prompts</h5>
                <a href="index.php?page=prompts" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($recentPrompts)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-file-text fa-3x mb-3"></i>
                        <p>No prompts yet. <a href="#" data-bs-toggle="modal" data-bs-target="#createPromptModal">Create your first prompt</a>!</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recentPrompts as $prompt): ?>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1">
                                            <a href="index.php?page=prompt&action=view&id=<?php echo $prompt['id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($prompt['title']); ?>
                                            </a>
                                            <?php if ($prompt['is_favorite']): ?>
                                                <i class="fas fa-star text-warning"></i>
                                            <?php endif; ?>
                                        </h6>
                                        <p class="mb-1 text-muted small">
                                            <?php echo htmlspecialchars(substr($prompt['description'] ?: $prompt['content'], 0, 100)); ?>
                                            <?php if (strlen($prompt['description'] ?: $prompt['content']) > 100): ?>...<?php endif; ?>
                                        </p>
                                        <small class="text-muted">
                                            <?php if ($prompt['category_name']): ?>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($prompt['category_name']); ?></span>
                                            <?php endif; ?>
                                            <?php echo date('M j, Y', strtotime($prompt['created_at'])); ?>
                                        </small>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" 
                                                data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="dropdown-item" href="#" onclick="showEditPromptModal(<?php echo $prompt['id']; ?>)">
                                                    <i class="fas fa-edit fa-fw me-2"></i>Edit
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="index.php?page=prompt&action=view&id=<?php echo $prompt['id']; ?>">
                                                    <i class="fas fa-eye fa-fw me-2"></i>View
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($categories)): ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="fas fa-tags"></i> Categories</h5>
                <a href="index.php?page=categories" class="btn btn-sm btn-outline-primary">Manage</a>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($categories as $category): ?>
                        <div class="col-md-4 mb-3">
                            <div class="card border-start border-primary border-4">
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo htmlspecialchars($category['name']); ?></h6>
                                    <p class="card-text text-muted small">
                                        <?php echo htmlspecialchars($category['description'] ?? ''); ?>
                                    </p>
                                    <small class="text-muted">
                                        <i class="fas fa-file-text"></i> <?php echo $category['prompt_count']; ?> prompts
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include 'layout.php';
?>