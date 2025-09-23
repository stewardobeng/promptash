<?php
$page_title = 'Shared Notes';
$user = $auth->getCurrentUser();

require_once __DIR__ . '/../models/Note.php';
$noteModel = new Note();

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'with_me';

if ($tab === 'by_me') {
    $notes = $noteModel->getSharedByUser($user['id']);
} else {
    $notes = $noteModel->getSharedWithUser($user['id']);
}

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-share-alt"></i> Shared Notes</h2>
</div>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?php echo $tab === 'with_me' ? 'active' : ''; ?>" href="index.php?page=shared_notes&tab=with_me">Shared with Me</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $tab === 'by_me' ? 'active' : ''; ?>" href="index.php?page=shared_notes&tab=by_me">Shared by Me</a>
    </li>
</ul>

<?php if (empty($notes)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-sticky-note fa-4x text-muted mb-4"></i>
            <h4>No shared notes found</h4>
            <p class="text-muted">
                <?php if ($tab === 'with_me'): ?>
                    When another user shares a note with you, it will appear here.
                <?php else: ?>
                    You haven't shared any notes yet. You can share a note from the "My Notes" page.
                <?php endif; ?>
            </p>
        </div>
    </div>
<?php else: ?>
    <div class="row">
        <?php foreach ($notes as $note): ?>
            <div class="col-md-4 col-lg-3 mb-4">
                <div class="card h-100 note-card note-<?php echo htmlspecialchars($note['color']); ?>">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title">
                                <a href="index.php?page=note&id=<?php echo $note['id']; ?>" class="text-decoration-none stretched-link text-dark">
                                    <?php echo htmlspecialchars($note['title']); ?>
                                </a>
                            </h5>
                            <div class="dropdown" style="z-index: 2;">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <?php if ($tab === 'by_me'): ?>
                                        <li>
                                            <button class="dropdown-item text-warning"
                                                    onclick="event.stopPropagation(); unshareNote(<?php echo $note['id']; ?>, <?php echo isset($note['recipient_id']) ? $note['recipient_id'] : 'null'; ?>)">
                                                <i class="fas fa-user-times fa-fw me-2"></i>Unshare
                                            </button>
                                        </li>
                                    <?php else: ?>
                                        <?php if ($noteModel->hasUserSavedSharedNote($note['id'], $user['id'])): ?>
                                            <li>
                                                <span class="dropdown-item-text text-muted">
                                                    <i class="fas fa-check fa-fw me-2"></i>In your collection
                                                </span>
                                            </li>
                                        <?php else: ?>
                                            <li>
                                                <button class="dropdown-item text-success"
                                                        onclick="event.stopPropagation(); saveSharedNote(<?php echo $note['id']; ?>, '<?php echo htmlspecialchars($note['title'], ENT_QUOTES); ?>')">
                                                    <i class="fas fa-copy fa-fw me-2"></i>Make a Copy
                                                </button>
                                            </li>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                        <p class="card-text flex-grow-1"><?php echo nl2br(htmlspecialchars(substr($note['content'], 0, 150))); ?><?php if (strlen($note['content']) > 150) echo '...'; ?></p>
                        <p class="card-text text-muted small mt-auto">
                            <?php if ($tab === 'with_me'): ?>
                                Shared by: <strong><?php echo htmlspecialchars($note['sharer_username']); ?></strong>
                            <?php else: ?>
                                Shared with: <strong><?php echo htmlspecialchars($note['recipient_username'] ?? 'All Users'); ?></strong>
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