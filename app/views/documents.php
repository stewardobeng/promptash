<?php
$page_title = 'My Documents';
$user = $auth->getCurrentUser();

// Initialize the Document model
require_once __DIR__ . '/../models/Document.php';
$documentModel = new Document();
$documents = $documentModel->getByUserId($user['id']);

// Function to format file size
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
    <h2><i class="fas fa-folder-open"></i> My Documents</h2>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal">
        <i class="fas fa-upload"></i> Upload Document
    </button>
</div>

<?php if (empty($documents)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-folder-open fa-4x text-muted mb-4"></i>
            <h4>No documents uploaded.</h4>
            <p class="text-muted">Upload your documents to keep them organized and accessible.</p>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>File Name</th>
                            <th>File Size</th>
                            <th>File Type</th>
                            <th>Uploaded On</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $doc): ?>
                            <tr>
                                <td>
                                    <i class="fas fa-file-alt me-2"></i><?php echo htmlspecialchars($doc['file_name']); ?>
                                    <?php if ($documentModel->hasUserSharedDocument($doc['id'], $user['id'])): ?>
                                        <span class="badge bg-info ms-2"><i class="fas fa-share-alt"></i> Shared</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatBytes($doc['file_size']); ?></td>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($doc['file_type']); ?></span></td>
                                <td><?php echo date('M j, Y', strtotime($doc['created_at'])); ?></td>
                                <td class="text-center">
                                    <div class="btn-group" role="group" aria-label="Document Actions">
                                        <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" class="btn btn-sm btn-outline-primary" title="Download" download>
                                            <i class="fas fa-download"></i>
                                        </a>
                                        
                                        <?php if ($documentModel->hasUserSharedDocument($doc['id'], $user['id'])): ?>
                                            <button type="button" class="btn btn-sm btn-outline-warning" title="Unshare" onclick="unshareDocument(<?php echo $doc['id']; ?>)">
                                                <i class="fas fa-user-times"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-outline-info" title="Share" onclick="showShareDocumentModal(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars($doc['file_name'], ENT_QUOTES); ?>')">
                                                <i class="fas fa-share-alt"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Delete" onclick="showDeleteConfirmModal('Document', <?php echo $doc['id']; ?>, '<?php echo htmlspecialchars($doc['file_name'], ENT_QUOTES); ?>', 'delete_document')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
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