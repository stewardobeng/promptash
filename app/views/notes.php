<?php
$page_title = 'My Notes';
$user = $auth->getCurrentUser();

// Initialize the Note model
require_once __DIR__ . '/../models/Note.php';
$noteModel = new Note();
$notes = $noteModel->getByUserId($user['id']);

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-sticky-note"></i> My Notes</h2>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createNoteModal">
        <i class="fas fa-plus"></i> New Note
    </button>
</div>

<?php if (empty($notes)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-sticky-note fa-4x text-muted mb-4"></i>
            <h4>You have no notes yet.</h4>
            <p class="text-muted">Click the button above to create your first note.</p>
        </div>
    </div>
<?php else: ?>
    <div class="row">
        <?php foreach ($notes as $note): ?>
            <div class="col-md-4 col-lg-3 mb-4">
                <div class="card h-100 note-card note-<?php echo htmlspecialchars($note['color']); ?> <?php echo $note['is_pinned'] ? 'note-pinned' : ''; ?>">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title">
                                <a href="index.php?page=note&id=<?php echo $note['id']; ?>" class="text-decoration-none stretched-link text-dark">
                                    <?php if ($note['is_pinned']): ?>
                                        <i class="fas fa-thumbtack text-muted me-2"></i>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($note['title']); ?>
                                </a>
                            </h5>
                            <div class="dropdown" style="z-index: 2;">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" onclick="event.stopPropagation();">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" onclick="event.stopPropagation(); togglePin(<?php echo $note['id']; ?>)"><i class="fas fa-thumbtack fa-fw me-2"></i><?php echo $note['is_pinned'] ? 'Unpin' : 'Pin'; ?></a></li>
                                    <li><a class="dropdown-item" href="#" onclick="event.stopPropagation(); showEditNoteModal(<?php echo $note['id']; ?>)"><i class="fas fa-edit fa-fw me-2"></i>Edit</a></li>
                                    <?php if ($noteModel->hasUserSharedNote($note['id'], $user['id'])): ?>
                                        <li>
                                            <button class="dropdown-item text-warning" onclick="event.stopPropagation(); unshareNote(<?php echo $note['id']; ?>)">
                                                <i class="fas fa-user-times fa-fw me-2"></i>Unshare
                                            </button>
                                        </li>
                                    <?php else: ?>
                                        <li>
                                            <a class="dropdown-item" href="#" onclick="event.stopPropagation(); showShareNoteModal(<?php echo $note['id']; ?>, '<?php echo htmlspecialchars($note['title'], ENT_QUOTES); ?>')">
                                                <i class="fas fa-share-alt fa-fw me-2"></i>Share
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <button class="dropdown-item text-danger" onclick="event.stopPropagation(); showDeleteConfirmModal('Note', <?php echo $note['id']; ?>, '<?php echo htmlspecialchars($note['title'], ENT_QUOTES); ?>', 'delete_note')">
                                            <i class="fas fa-trash fa-fw me-2"></i>Delete
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <p class="card-text flex-grow-1"><?php echo nl2br(htmlspecialchars($note['content'])); ?></p>
                        <div class="mt-auto">
                            <?php if ($noteModel->hasUserSharedNote($note['id'], $user['id'])): ?>
                                <span class="badge bg-info"><i class="fas fa-share-alt"></i> Shared</span>
                            <?php endif; ?>
                            <small class="text-muted d-block">Updated: <?php echo date('M j, Y', strtotime($note['updated_at'])); ?></small>
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