<?php
$page_title = 'Shared Prompts';
$user = $auth->getCurrentUser();

$promptModel = new Prompt();

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'with_me';

if ($tab === 'by_me') {
    $prompts = $promptModel->getSharedByUser($user['id']);
} else {
    $prompts = $promptModel->getSharedWithUser($user['id']);
}

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-share-alt"></i> Shared Prompts</h2>
</div>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?php echo $tab === 'with_me' ? 'active' : ''; ?>" href="index.php?page=shared_prompts&tab=with_me">Shared with Me</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $tab === 'by_me' ? 'active' : ''; ?>" href="index.php?page=shared_prompts&tab=by_me">Shared by Me</a>
    </li>
</ul>

<?php if (empty($prompts)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-share-alt fa-4x text-muted mb-4"></i>
            <h4>No shared prompts found</h4>
            <p class="text-muted">
                <?php if ($tab === 'with_me'): ?>
                    When another user shares a prompt with you, it will appear here.
                <?php else: ?>
                    You haven't shared any prompts yet. You can share a prompt from the "My Prompts" page.
                <?php endif; ?>
            </p>
        </div>
    </div>
<?php else: ?>
    <div class="row">
        <?php foreach ($prompts as $prompt): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100 prompt-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title">
                                <a href="index.php?page=prompt&action=view&id=<?php echo $prompt['id']; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($prompt['title']); ?>
                                </a>
                            </h5>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" 
                                        data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li>
                                        <a class="dropdown-item" href="index.php?page=prompt&action=view&id=<?php echo $prompt['id']; ?>">
                                            <i class="fas fa-eye fa-fw me-2"></i>View
                                        </a>
                                    </li>
                                    <?php if ($tab === 'by_me'): ?>
                                        <li>
                                            <a class="dropdown-item" href="#" onclick="event.stopPropagation(); showEditPromptModal(<?php echo $prompt['id']; ?>)">
                                                <i class="fas fa-edit fa-fw me-2"></i>Edit
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <button class="dropdown-item text-warning" 
                                                    onclick="event.stopPropagation(); unsharePrompt(<?php echo $prompt['id']; ?>, <?php echo isset($prompt['recipient_id']) ? $prompt['recipient_id'] : 'null'; ?>)">
                                                <i class="fas fa-user-times fa-fw me-2"></i>Unshare
                                            </button>
                                        </li>
                                    <?php else: ?>
                                        <?php
                                        // Check if user has already saved this shared prompt
                                        $userHasSaved = $promptModel->hasUserSavedSharedPrompt($prompt['id'], $user['id']);
                                        ?>
                                        <?php if ($userHasSaved): ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <span class="dropdown-item-text text-muted">
                                                    <i class="fas fa-check fa-fw me-2"></i>Already in your collection
                                                </span>
                                            </li>
                                        <?php else: ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <button class="dropdown-item text-success" 
                                                        onclick="event.stopPropagation(); saveSharedPrompt(<?php echo $prompt['id']; ?>, '<?php echo htmlspecialchars($prompt['title'], ENT_QUOTES); ?>')">
                                                    <i class="fas fa-copy fa-fw me-2"></i>Make a Copy
                                                </button>
                                            </li>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                        <p class="card-text text-muted small">
                            <?php if ($tab === 'with_me'): ?>
                                Shared by: <strong><?php echo htmlspecialchars($prompt['sharer_username']); ?></strong>
                            <?php else: ?>
                                Shared with: <strong><?php echo htmlspecialchars($prompt['recipient_username'] ?? 'All Users'); ?></strong>
                            <?php endif; ?>
                        </p>
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