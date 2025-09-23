<?php
$page_title = 'My Bookmarks';
$user = $auth->getCurrentUser();

// Initialize model
require_once __DIR__ . '/../models/Bookmark.php';
$bookmarkModel = new Bookmark();

// Get parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page_num = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$limit = 20;
$offset = ($page_num - 1) * $limit;

// Get bookmarks based on search
if ($search) {
    $bookmarks = $bookmarkModel->search($user['id'], $search, $limit, $offset);
} else {
    $bookmarks = $bookmarkModel->getByUserId($user['id'], $limit, $offset);
}

// Helper function to extract domain from URL
function getDomainFromUrl($url) {
    $host = parse_url($url, PHP_URL_HOST);
    // Remove 'www.' if it exists
    if (substr($host, 0, 4) == 'www.') {
        $host = substr($host, 4);
    }
    return $host;
}

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-bookmark"></i> My Bookmarks</h2>
    <div class="d-flex align-items-center">
        
        <div class="btn-group me-2 d-none d-md-inline-flex" role="group" aria-label="View switch">
        <button type="button" class="btn btn-outline-primary active" id="grid-view-btn" title="Grid View">
                <i class="fas fa-th"></i>
            </button>
            <button type="button" class="btn btn-outline-primary" id="list-view-btn" title="List View">
                <i class="fas fa-list"></i>
            </button>
        </div>
        <button type="button" class="btn btn-primary" onclick="showCreateBookmarkModal()">
            <i class="fas fa-plus"></i> New Bookmark
        </button>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="page" value="bookmarks">
            <div class="col-md-10">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control" name="search" placeholder="Search bookmarks..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100">Search</button>
            </div>
        </form>
    </div>
</div>

<?php if (empty($bookmarks)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-bookmark fa-4x text-muted mb-4"></i>
            <h4>No bookmarks found</h4>
            <p class="text-muted">
                <?php if ($search): ?>
                    Your search returned no results. Try adjusting your search terms.
                <?php else: ?>
                    Save and organize your important links here.
                <?php endif; ?>
            </p>
            <button type="button" class="btn btn-primary" onclick="showCreateBookmarkModal()">
                <i class="fas fa-plus"></i> Add Your First Bookmark
            </button>
        </div>
    </div>
<?php else: ?>
    <div id="bookmarks-grid-view" class="row">
        <?php foreach ($bookmarks as $bookmark): ?>
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="card h-100 bookmark-card">
                    <?php if (!empty($bookmark['image'])): ?>
                        <a href="<?php echo htmlspecialchars($bookmark['url']); ?>" target="_blank" rel="noopener noreferrer">
                            <img src="<?php echo htmlspecialchars($bookmark['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($bookmark['title']); ?>" style="height: 180px; object-fit: cover;">
                        </a>
                    <?php endif; ?>
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">
                            <a href="<?php echo htmlspecialchars($bookmark['url']); ?>" target="_blank" rel="noopener noreferrer" class="text-decoration-none">
                                <?php echo htmlspecialchars($bookmark['title']); ?>
                            </a>
                        </h5>
                        <p class="card-text text-muted small flex-grow-1">
                            <?php echo htmlspecialchars(substr($bookmark['description'], 0, 150)); ?>
                            <?php if (strlen($bookmark['description']) > 150): ?>...<?php endif; ?>
                        </p>
                        <div class="mb-2">
                            <a href="<?php echo htmlspecialchars($bookmark['url']); ?>" target="_blank" rel="noopener noreferrer" class="text-muted small text-decoration-none bookmark-url">
                                <i class="fas fa-link me-1"></i><?php echo htmlspecialchars(getDomainFromUrl($bookmark['url'])); ?>
                            </a>
                        </div>
                        <?php if ($bookmark['tags'] || $bookmarkModel->hasUserSharedBookmark($bookmark['id'], $user['id'])): ?>
                            <div class="mb-2">
                                <?php if ($bookmark['tags']): ?>
                                    <?php foreach (explode(',', $bookmark['tags']) as $tag): ?>
                                        <span class="badge bg-light text-dark border"><?php echo htmlspecialchars(trim($tag)); ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <?php if ($bookmarkModel->hasUserSharedBookmark($bookmark['id'], $user['id'])): ?>
                                    <span class="badge bg-info"><i class="fas fa-share-alt"></i> Shared</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between align-items-center mt-auto">
                            <small class="text-muted"><i class="fas fa-clock"></i> <?php echo date('M j, Y', strtotime($bookmark['created_at'])); ?></small>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" onclick="showEditBookmarkModal(<?php echo $bookmark['id']; ?>)"><i class="fas fa-edit fa-fw me-2"></i>Edit</a></li>
                                    <li>
                                        <a class="dropdown-item" href="#" onclick="event.stopPropagation(); showShareBookmarkModal(<?php echo $bookmark['id']; ?>, '<?php echo htmlspecialchars($bookmark['title'], ENT_QUOTES); ?>')">
                                            <i class="fas fa-share-alt fa-fw me-2"></i>Share
                                        </a>
                                    </li>
                                    <?php if ($bookmarkModel->hasUserSharedBookmark($bookmark['id'], $user['id'])): ?>
                                    <li>
                                        <button class="dropdown-item text-warning"
                                                onclick="event.stopPropagation(); unshareBookmark(<?php echo $bookmark['id']; ?>)">
                                            <i class="fas fa-user-times fa-fw me-2"></i>Unshare from All
                                        </button>
                                    </li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <button class="dropdown-item text-danger"
                                                onclick="showDeleteConfirmModal('Bookmark', <?php echo $bookmark['id']; ?>, '<?php echo htmlspecialchars($bookmark['title'], ENT_QUOTES); ?>', 'delete_bookmark')">
                                            <i class="fas fa-trash fa-fw me-2"></i>Delete
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div id="bookmarks-list-view" style="display: none;">
        <div class="list-group">
            <?php foreach ($bookmarks as $bookmark): ?>
                <div class="list-group-item list-group-item-action">
                    <div class="d-flex w-100">
                        <?php if (!empty($bookmark['image'])): ?>
                            <a href="<?php echo htmlspecialchars($bookmark['url']); ?>" target="_blank" rel="noopener noreferrer">
                                <img src="<?php echo htmlspecialchars($bookmark['image']); ?>" class="bookmark-image me-3" alt="<?php echo htmlspecialchars($bookmark['title']); ?>">
                            </a>
                        <?php endif; ?>
                        <div class="flex-grow-1">
                            <div class="d-flex w-100 justify-content-between">
                                <h5 class="mb-1">
                                    <a href="<?php echo htmlspecialchars($bookmark['url']); ?>" target="_blank" rel="noopener noreferrer" class="text-decoration-none">
                                        <?php echo htmlspecialchars($bookmark['title']); ?>
                                    </a>
                                </h5>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="#" onclick="showEditBookmarkModal(<?php echo $bookmark['id']; ?>)"><i class="fas fa-edit fa-fw me-2"></i>Edit</a></li>
                                        <li>
                                            <a class="dropdown-item" href="#" onclick="event.stopPropagation(); showShareBookmarkModal(<?php echo $bookmark['id']; ?>, '<?php echo htmlspecialchars($bookmark['title'], ENT_QUOTES); ?>')">
                                                <i class="fas fa-share-alt fa-fw me-2"></i>Share
                                            </a>
                                        </li>
                                        <?php if ($bookmarkModel->hasUserSharedBookmark($bookmark['id'], $user['id'])): ?>
                                        <li>
                                            <button class="dropdown-item text-warning"
                                                    onclick="event.stopPropagation(); unshareBookmark(<?php echo $bookmark['id']; ?>)">
                                                <i class="fas fa-user-times fa-fw me-2"></i>Unshare from All
                                            </button>
                                        </li>
                                        <?php endif; ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <button class="dropdown-item text-danger"
                                                    onclick="showDeleteConfirmModal('Bookmark', <?php echo $bookmark['id']; ?>, '<?php echo htmlspecialchars($bookmark['title'], ENT_QUOTES); ?>', 'delete_bookmark')">
                                                <i class="fas fa-trash fa-fw me-2"></i>Delete
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <p class="mb-1 text-muted small"><?php echo htmlspecialchars($bookmark['description']); ?></p>
                            <?php if ($bookmark['tags'] || $bookmarkModel->hasUserSharedBookmark($bookmark['id'], $user['id'])): ?>
                                <div class="mb-2">
                                    <?php if ($bookmark['tags']): ?>
                                        <?php foreach (explode(',', $bookmark['tags']) as $tag): ?>
                                            <span class="badge bg-light text-dark border"><?php echo htmlspecialchars(trim($tag)); ?></span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <?php if ($bookmarkModel->hasUserSharedBookmark($bookmark['id'], $user['id'])): ?>
                                        <span class="badge bg-info"><i class="fas fa-share-alt"></i> Shared</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div class="d-flex align-items-center">
                                <small class="text-muted"><i class="fas fa-clock"></i> <?php echo date('M j, Y', strtotime($bookmark['created_at'])); ?></small>
                                <span class="text-muted mx-2">|</span>
                                <a href="<?php echo htmlspecialchars($bookmark['url']); ?>" target="_blank" rel="noopener noreferrer" class="text-muted small text-decoration-none bookmark-url">
                                    <i class="fas fa-link me-1"></i><?php echo htmlspecialchars(getDomainFromUrl($bookmark['url'])); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<style>
    /* Custom styles for the list view image */
    .bookmark-image {
        width: 120px;
        height: 90px;
        object-fit: cover;
        border-radius: 8px;
        flex-shrink: 0;
    }
    /* Style for the URL link to make it subtle */
    .bookmark-url {
        transition: color 0.2s ease-in-out;
    }
    .bookmark-url:hover {
        color: var(--primary-color) !important;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const gridViewBtn = document.getElementById('grid-view-btn');
    const listViewBtn = document.getElementById('list-view-btn');
    const gridView = document.getElementById('bookmarks-grid-view');
    const listView = document.getElementById('bookmarks-list-view');

    function setView(view) {
        if (view === 'list') {
            if (gridView) gridView.style.display = 'none';
            if (listView) listView.style.display = 'block';
            if (listViewBtn) listViewBtn.classList.add('active');
            if (gridViewBtn) gridViewBtn.classList.remove('active');
            localStorage.setItem('bookmarkView', 'list');
        } else {
            if (gridView) gridView.style.display = 'flex'; // Use flex for row behavior
            if (listView) listView.style.display = 'none';
            if (gridViewBtn) gridViewBtn.classList.add('active');
            if (listViewBtn) listViewBtn.classList.remove('active');
            localStorage.setItem('bookmarkView', 'grid');
        }
    }

    // Event Listeners
    if (gridViewBtn) {
        gridViewBtn.addEventListener('click', function () {
            setView('grid');
        });
    }

    if (listViewBtn) {
        listViewBtn.addEventListener('click', function () {
            setView('list');
        });
    }

    // On page load, check for saved preference
    const savedView = localStorage.getItem('bookmarkView');
    if (savedView && (gridView || listView)) {
        setView(savedView);
    } else if (gridView || listView) {
        setView('grid'); // Default view
    }
});
</script>

<?php
$content = ob_get_clean();
include 'layout.php';
?>