<?php
$page_title = 'My Videos';
$user = $auth->getCurrentUser();

// Initialize the Video model
require_once __DIR__ . '/../models/Video.php';
$videoModel = new Video();
$videos = $videoModel->getByUserId($user['id']);

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-video"></i> My Videos</h2>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVideoModal">
        <i class="fas fa-plus"></i> Add Video
    </button>
</div>

<div id="inPageVideoPlayerContainer" class="card mb-4" style="display: none;">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0" id="inPageVideoTitle"></h5>
        <button type="button" class="btn-close" onclick="closeVideoPlayer()"></button>
    </div>
    <div class="card-body p-0">
        <div class="ratio ratio-16x9">
            <iframe id="inPageVideoPlayerFrame" src="" allowfullscreen></iframe>
        </div>
    </div>
</div>

<?php if (empty($videos)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-video fa-4x text-muted mb-4"></i>
            <h4>No videos added yet.</h4>
            <p class="text-muted">Add YouTube or other video links to create your personal video library.</p>
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
                                <?php if ($videoModel->hasUserSharedVideo($video['id'], $user['id'])): ?>
                                    <li>
                                        <button class="dropdown-item text-warning" onclick="event.stopPropagation(); unshareVideo(<?php echo $video['id']; ?>)">
                                            <i class="fas fa-user-times fa-fw me-2"></i>Unshare
                                        </button>
                                    </li>
                                <?php else: ?>
                                    <li>
                                        <button class="dropdown-item" 
                                                onclick="event.stopPropagation(); showShareVideoModal(<?php echo $video['id']; ?>, '<?php echo htmlspecialchars($video['title'], ENT_QUOTES); ?>')">
                                            <i class="fas fa-share-alt fa-fw me-2"></i>Share
                                        </button>
                                    </li>
                                <?php endif; ?>
                                <li>
                                    <button class="dropdown-item text-danger" 
                                            onclick="event.stopPropagation(); showDeleteConfirmModal('Video', <?php echo $video['id']; ?>, '<?php echo htmlspecialchars($video['title'], ENT_QUOTES); ?>', 'delete_video')">
                                        <i class="fas fa-trash fa-fw me-2"></i>Delete
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body" onclick="playVideo('<?php echo htmlspecialchars($video['url']); ?>', '<?php echo htmlspecialchars($video['title']); ?>')">
                        <h6 class="card-title"><?php echo htmlspecialchars($video['title']); ?></h6>
                        <p class="card-text text-muted small"><?php echo htmlspecialchars($video['channel_title']); ?></p>
                        <?php if ($videoModel->hasUserSharedVideo($video['id'], $user['id'])): ?>
                            <span class="badge bg-info"><i class="fas fa-share-alt"></i> Shared</span>
                        <?php endif; ?>
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