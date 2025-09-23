<?php
$page_title = 'Shared Documents';
$user = $auth->getCurrentUser();

require_once __DIR__ . '/../models/Document.php';
$documentModel = new Document();

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'with_me';

if ($tab === 'by_me') {
    $documents = $documentModel->getSharedByUser($user['id']);
} else {
    $documents = $documentModel->getSharedWithUser($user['id']);
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-share-alt"></i> Shared Documents</h2>
</div>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?php echo $tab === 'with_me' ? 'active' : ''; ?>" href="index.php?page=shared_documents&tab=with_me">Shared with Me</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $tab === 'by_me' ? 'active' : ''; ?>" href="index.php?page=shared_documents&tab=by_me">Shared by Me</a>
    </li>
</ul>

<?php if (empty($documents)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-folder-open fa-4x text-muted mb-4"></i>
            <h4>No shared documents found</h4>
            <p class="text-muted">
                <?php if ($tab === 'with_me'): ?>
                    When another user shares a document with you, it will appear here.
                <?php else: ?>
                    You haven't shared any documents yet. You can share a document from the "My Documents" page.
                <?php endif; ?>
            </p>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>File Name</th>
                            <th>Size</th>
                            <th>Type</th>
                            <th>Shared By/With</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $doc): ?>
                            <tr>
                                <td><i class="fas fa-file-alt me-2"></i><?php echo htmlspecialchars($doc['file_name']); ?></td>
                                <td><?php echo formatBytes($doc['file_size']); ?></td>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($doc['file_type']); ?></span></td>
                                <td>
                                    <?php if ($tab === 'with_me'): ?>
                                        <strong><?php echo htmlspecialchars($doc['sharer_username']); ?></strong>
                                    <?php else: ?>
                                        <strong><?php echo htmlspecialchars($doc['recipient_username'] ?? 'All Users'); ?></strong>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" class="btn btn-sm btn-outline-primary" download>
                                        <i class="fas fa-download"></i>
                                    </a>
                                    <?php if ($tab === 'by_me'): ?>
                                        <button class="btn btn-sm btn-outline-warning"
                                                onclick="unshareDocument(<?php echo $doc['id']; ?>, <?php echo isset($doc['recipient_id']) ? $doc['recipient_id'] : 'null'; ?>)">
                                            <i class="fas fa-user-times"></i>
                                        </button>
                                    <?php else: ?>
                                        <?php if ($documentModel->hasUserSavedSharedDocument($doc['id'], $user['id'])): ?>
                                            <button class="btn btn-sm btn-outline-secondary" disabled>
                                                <i class="fas fa-check"></i> Saved
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-success"
                                                    onclick="saveSharedDocument(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars($doc['file_name'], ENT_QUOTES); ?>')">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include 'layout.php';
?>