<?php
$page_title = 'Categories';
$user = $auth->getCurrentUser();

// Initialize model
$categoryModel = new Category();

$error = '';
$success = '';

// Get all categories for list view
$categories = $categoryModel->getByUserId($user['id']);

// Handle success messages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'created':
            $success = 'Category created successfully.';
            break;
        case 'updated':
            $success = 'Category updated successfully.';
            break;
        case 'deleted':
            $success = 'Category deleted successfully.';
            break;
    }
}

ob_start();
?>

    <!-- List View -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-tags"></i> Categories</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCategoryModal">
            <i class="fas fa-plus"></i> New Category
        </button>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($categories)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-tags fa-4x text-muted mb-4"></i>
                <h4>No categories yet</h4>
                <p class="text-muted">Create categories to organize your prompts better.</p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCategoryModal">
                    <i class="fas fa-plus"></i> Create Category
                </button>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($categories as $category): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title"><?php echo htmlspecialchars($category['name']); ?></h5>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" 
                                            data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <a class="dropdown-item" href="#" onclick="showEditCategoryModal(<?php echo $category['id']; ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <button class="dropdown-item text-danger" 
                                                    onclick="showDeleteConfirmModal('Category', <?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name'], ENT_QUOTES); ?>', 'delete_category')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            
                            <?php if ($category['description']): ?>
                                <p class="card-text text-muted">
                                    <?php echo htmlspecialchars($category['description']); ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="mt-auto">
                                <small class="text-muted">
                                    <i class="fas fa-file-text"></i> <?php echo $category['prompt_count']; ?> prompts
                                </small>
                                <br>
                                <small class="text-muted">
                                    <i class="fas fa-clock"></i> Created <?php echo date('M j, Y', strtotime($category['created_at'])); ?>
                                </small>
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
