<?php
$page_title = 'Shared Videos';
$user = $auth->getCurrentUser();

require_once __DIR__ . '/../models/Video.php';
$videoModel = new Video();

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'with_me';

if ($tab === 'by_me') {
    $videos = $videoModel->getSharedByUser($user['id']);
} else {
    $videos = $videoModel->getSharedWithUser($user['id']);
}

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-share-alt"></i> Shared Videos</h2>
</div>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?php echo $tab === 'with_me' ? 'active' : ''; ?>" href="index.php?page=shared_videos&tab=with_me">Shared with Me</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $tab === 'by_me' ? 'active' : ''; ?>" href="index.php?page=shared_videos&tab=by_me">Shared by Me</a>
    </li>
</ul>

<?php if (empty($videos)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-video fa-4x text-muted mb-4"></i>
            <h4>No shared videos found</h4>
            <p class="text-muted">
                <?php if ($tab === 'with_me'): ?>
                    When another user shares a video with you, it will appear here.
                <?php else: ?>
                    You haven't shared any videos yet. You can share a video from the "My Videos" page.
                <?php endif; ?>
            </p>
        </div>
    </div>
<?php else: ?>
    <div class="row">
        <?php foreach ($videos as $video): ?>
            <div class="col-md-4 col-lg-3 mb-4">
                <div class="card h-100 video-card">
                     <div class="position-relative">
                        <img src="<?php echo htmlspecialchars($video['thumbnail_url']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($video['title']); ?>" onclick="playVideo('<?php echo htmlspecialchars($video['url']); ?>', '<?php echo htmlspecialchars($video['title']); ?>')">
                        <div class="dropdown position-absolute" style="top: 8px; right: 8px; z-index: 2;">
                            <button class="btn btn-sm btn-dark dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" onclick="event.stopPropagation();">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <?php if ($tab === 'by_me'): ?>
                                    <li>
                                        <button class="dropdown-item text-warning" 
                                                onclick="event.stopPropagation(); unshareVideo(<?php echo $video['id']; ?>, <?php echo isset($video['recipient_id']) ? $video['recipient_id'] : 'null'; ?>)">
                                            <i class="fas fa-user-times fa-fw me-2"></i>Unshare
                                        </button>
                                    </li>
                                <?php else: ?>
                                    <?php if ($videoModel->hasUserSavedSharedVideo($video['id'], $user['id'])): ?>
                                        <li>
                                            <span class="dropdown-item-text text-muted">
                                                <i class="fas fa-check fa-fw me-2"></i>In your collection
                                            </span>
                                        </li>
                                    <?php else: ?>
                                        <li>
                                            <button class="dropdown-item text-success"
                                                    onclick="event.stopPropagation(); saveSharedVideo(<?php echo $video['id']; ?>, '<?php echo htmlspecialchars($video['title'], ENT_QUOTES); ?>')">
                                                <i class="fas fa-copy fa-fw me-2"></i>Make a Copy
                                            </button>
                                        </li>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body" onclick="playVideo('<?php echo htmlspecialchars($video['url']); ?>', '<?php echo htmlspecialchars($video['title']); ?>')">
                        <h6 class="card-title"><?php echo htmlspecialchars($video['title']); ?></h6>
                        <p class="card-text text-muted small"><?php echo htmlspecialchars($video['channel_title']); ?></p>
                         <p class="card-text text-muted small mt-auto">
                            <?php if ($tab === 'with_me'): ?>
                                Shared by: <strong><?php echo htmlspecialchars($video['sharer_username']); ?></strong>
                            <?php else: ?>
                                Shared with: <strong><?php echo htmlspecialchars($video['recipient_username'] ?? 'All Users'); ?></strong>
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