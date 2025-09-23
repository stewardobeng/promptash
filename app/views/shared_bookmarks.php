<?php
$page_title = 'Shared Bookmarks';
$user = $auth->getCurrentUser();

require_once __DIR__ . '/../models/Bookmark.php';
$bookmarkModel = new Bookmark();

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'with_me';

if ($tab === 'by_me') {
    $bookmarks = $bookmarkModel->getSharedByUser($user['id']);
} else {
    $bookmarks = $bookmarkModel->getSharedWithUser($user['id']);
}

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-share-square"></i> Shared Bookmarks</h2>
</div>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?php echo $tab === 'with_me' ? 'active' : ''; ?>" href="index.php?page=shared_bookmarks&tab=with_me">Shared with Me</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $tab === 'by_me' ? 'active' : ''; ?>" href="index.php?page=shared_bookmarks&tab=by_me">Shared by Me</a>
    </li>
</ul>


<?php if (empty($bookmarks)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-share-square fa-4x text-muted mb-4"></i>
            <h4>No shared bookmarks found</h4>
            <p class="text-muted">
                <?php if ($tab === 'with_me'): ?>
                    When another user shares a bookmark with you, it will appear here.
                <?php else: ?>
                    You haven't shared any bookmarks yet. You can share a bookmark from the "My Bookmarks" page.
                <?php endif; ?>
            </p>
        </div>
    </div>
<?php else: ?>
    <div class="row">
        <?php foreach ($bookmarks as $bookmark): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100 bookmark-card">
                    <?php if (!empty($bookmark['image'])): ?>
                        <a href="<?php echo htmlspecialchars($bookmark['url']); ?>" target="_blank" rel="noopener noreferrer">
                            <img src="<?php echo htmlspecialchars($bookmark['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($bookmark['title']); ?>" style="height: 180px; object-fit: cover;">
                        </a>
                    <?php endif; ?>
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                             <h5 class="card-title">
                                <a href="<?php echo htmlspecialchars($bookmark['url']); ?>" target="_blank" rel="noopener noreferrer" class="text-decoration-none">
                                    <?php echo htmlspecialchars($bookmark['title']); ?>
                                </a>
                            </h5>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <?php if ($tab === 'by_me'): ?>
                                        <li>
                                            <button class="dropdown-item text-warning" 
                                                    onclick="event.stopPropagation(); unshareBookmark(<?php echo $bookmark['id']; ?>, <?php echo isset($bookmark['recipient_id']) ? $bookmark['recipient_id'] : 'null'; ?>)">
                                                <i class="fas fa-user-times fa-fw me-2"></i>Unshare
                                            </button>
                                        </li>
                                    <?php else: ?>
                                        <?php if ($bookmarkModel->hasUserSavedSharedBookmark($bookmark['id'], $user['id'])): ?>
                                            <li>
                                                <span class="dropdown-item-text text-muted">
                                                    <i class="fas fa-check fa-fw me-2"></i>In your collection
                                                </span>
                                            </li>
                                        <?php else: ?>
                                            <li>
                                                <button class="dropdown-item text-success" 
                                                        onclick="event.stopPropagation(); saveSharedBookmark(<?php echo $bookmark['id']; ?>, '<?php echo htmlspecialchars($bookmark['title'], ENT_QUOTES); ?>')">
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
                                Shared by: <strong><?php echo htmlspecialchars($bookmark['sharer_username']); ?></strong>
                            <?php else: ?>
                                Shared with: <strong><?php echo htmlspecialchars($bookmark['recipient_username'] ?? 'All Users'); ?></strong>
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