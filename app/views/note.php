<?php
$user = $auth->getCurrentUser();
$note_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Initialize Note model
$noteModel = new Note();
$note = null;

if ($note_id) {
    $note = $noteModel->getById($note_id, $user['id']);
}

if (!$note) {
    // If no note is found, redirect to the notes list
    header('Location: index.php?page=notes');
    exit();
}

$page_title = htmlspecialchars($note['title']);
ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>
        <?php if ($note['is_pinned']): ?>
            <i class="fas fa-thumbtack text-muted me-2" title="Pinned"></i>
        <?php endif; ?>
        <?php echo htmlspecialchars($note['title']); ?>
    </h2>
    <div>
        <button type="button" class="btn btn-outline-primary" onclick="showEditNoteModal(<?php echo $note['id']; ?>)">
            <i class="fas fa-edit"></i> Edit
        </button>
        <a href="index.php?page=notes" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back to Notes
        </a>
    </div>
</div>

<div class="card note-<?php echo htmlspecialchars($note['color']); ?>">
    <div class="card-body" style="min-height: 300px;">
        <p class="card-text fs-5">
            <?php echo nl2br(htmlspecialchars($note['content'])); ?>
        </p>
    </div>
    <div class="card-footer text-muted">
        Last Updated: <?php echo date('F j, Y \a\t g:i A', strtotime($note['updated_at'])); ?>
    </div>
</div>

<?php
$content = ob_get_clean();
include 'layout.php';
?>