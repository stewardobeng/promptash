<?php
// Modal definitions for the prompt management system
?>
<div class="modal fade" id="createPromptModal" tabindex="-1" aria-labelledby="createPromptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createPromptModalLabel">
                    <i class="fas fa-plus"></i> Create New Prompt
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="createPromptForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="promptTitle" class="form-label">Title *</label>
                        <input type="text" class="form-control" id="promptTitle" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="promptDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="promptDescription" name="description" rows="2" placeholder="Brief description of this prompt"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="promptContent" class="form-label">Content *</label>
                        <textarea class="form-control" id="promptContent" name="content" rows="8" required placeholder="Enter your prompt content here..."></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="promptCategory" class="form-label">Category</label>
                                <select class="form-select" id="promptCategory" name="category_id">
                                    <option value="">Select a category</option>
                                    </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="promptTags" class="form-label">Tags</label>
                                <input type="text" class="form-control" id="promptTags" name="tags" placeholder="tag1, tag2, tag3">
                                <small class="form-text text-muted">Separate tags with commas</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create Prompt
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="createCategoryModal" tabindex="-1" aria-labelledby="createCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createCategoryModalLabel">
                    <i class="fas fa-tag"></i> Create New Category
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="createCategoryForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="categoryName" class="form-label">Name *</label>
                        <input type="text" class="form-control" id="categoryName" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="categoryDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="categoryDescription" name="description" rows="3" placeholder="Optional description for this category"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editPromptModal" tabindex="-1" aria-labelledby="editPromptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editPromptModalLabel">
                    <i class="fas fa-edit"></i> Edit Prompt
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editPromptForm">
                <input type="hidden" id="editPromptId" name="id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editPromptTitle" class="form-label">Title *</label>
                        <input type="text" class="form-control" id="editPromptTitle" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editPromptDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="editPromptDescription" name="description" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editPromptContent" class="form-label">Content *</label>
                        <textarea class="form-control" id="editPromptContent" name="content" rows="8" required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editPromptCategory" class="form-label">Category</label>
                                <select class="form-select" id="editPromptCategory" name="category_id">
                                    <option value="">Select a category</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editPromptTags" class="form-label">Tags</label>
                                <input type="text" class="form-control" id="editPromptTags" name="tags">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Prompt
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCategoryModalLabel">
                    <i class="fas fa-edit"></i> Edit Category
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editCategoryForm">
                <input type="hidden" id="editCategoryId" name="id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editCategoryName" class="form-label">Name *</label>
                        <input type="text" class="form-control" id="editCategoryName" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editCategoryDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="editCategoryDescription" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteConfirmModalLabel">
                    <i class="fas fa-exclamation-triangle text-warning"></i> Confirm Delete
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="deleteConfirmMessage">Are you sure you want to delete this item? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="sharePromptModal" tabindex="-1" aria-labelledby="sharePromptModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sharePromptModalLabel">
                    <i class="fas fa-share-alt"></i> Share Prompt
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="sharePromptForm">
                <input type="hidden" id="sharePromptId" name="prompt_id">
                <div class="modal-body">
                    <p>Share "<strong><span id="sharePromptTitle"></span></strong>" with other users.</p>
                    <div class="mb-3">
                        <label for="shareEmails" class="form-label">User Emails</label>
                        <textarea class="form-control" id="shareEmails" name="emails" rows="3" placeholder="Enter user emails, separated by commas..."></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="shareWithAll" name="share_with_all">
                        <label class="form-check-label" for="shareWithAll">
                            Share with all users
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-share"></i> Share Prompt
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="createBookmarkModal" tabindex="-1" aria-labelledby="createBookmarkModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createBookmarkModalLabel">
                    <i class="fas fa-plus"></i> Add New Bookmark
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="createBookmarkForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="bookmarkUrl" class="form-label">URL *</label>
                        <input type="url" class="form-control" id="bookmarkUrl" name="url" placeholder="https://example.com" required>
                        <div class="form-text">Paste a link here to automatically fetch its details.</div>
                    </div>
                    <div id="metadata-loader" class="text-center my-3" style="display: none;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p>Fetching link details...</p>
                    </div>
                    <div id="metadata-fields" style="display: none;">
                        <div class="mb-3">
                            <label for="bookmarkTitle" class="form-label">Title *</label>
                            <input type="text" class="form-control" id="bookmarkTitle" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="bookmarkDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="bookmarkDescription" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="bookmarkImage" class="form-label">Image URL</label>
                            <input type="url" class="form-control" id="bookmarkImage" name="image">
                        </div>
                        <div class="mb-3">
                            <label for="bookmarkTags" class="form-label">Tags</label>
                            <input type="text" class="form-control" id="bookmarkTags" name="tags" placeholder="tag1, tag2, tag3">
                            <small class="form-text text-muted">Separate tags with commas</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="saveBookmarkBtn" disabled>
                        <i class="fas fa-save"></i> Save Bookmark
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editBookmarkModal" tabindex="-1" aria-labelledby="editBookmarkModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editBookmarkModalLabel">
                    <i class="fas fa-edit"></i> Edit Bookmark
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editBookmarkForm">
                <input type="hidden" id="editBookmarkId" name="id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editBookmarkTitle" class="form-label">Title *</label>
                        <input type="text" class="form-control" id="editBookmarkTitle" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="editBookmarkDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="editBookmarkDescription" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="editBookmarkImage" class="form-label">Image URL</label>
                        <input type="url" class="form-control" id="editBookmarkImage" name="image">
                    </div>
                    <div class="mb-3">
                        <label for="editBookmarkTags" class="form-label">Tags</label>
                        <input type="text" class="form-control" id="editBookmarkTags" name="tags">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Bookmark
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="shareBookmarkModal" tabindex="-1" aria-labelledby="shareBookmarkModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="shareBookmarkModalLabel">
                    <i class="fas fa-share-alt"></i> Share Bookmark
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="shareBookmarkForm">
                <input type="hidden" id="shareBookmarkId" name="bookmark_id">
                <div class="modal-body">
                    <p>Share "<strong><span id="shareBookmarkTitle"></span></strong>" with other users.</p>
                    <div class="mb-3">
                        <label for="shareBookmarkEmails" class="form-label">User Emails</label>
                        <textarea class="form-control" id="shareBookmarkEmails" name="emails" rows="3" placeholder="Enter user emails, separated by commas..."></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="shareBookmarkWithAll" name="share_with_all">
                        <label class="form-check-label" for="shareBookmarkWithAll">
                            Share with all users
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-share"></i> Share Bookmark
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($auth->isAdmin()): ?>
<div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createUserModalLabel">
                    <i class="fas fa-user-plus"></i> Create New User
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="createUserForm" action="javascript:void(0)">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="userFirstName" class="form-label">First Name *</label>
                                <input type="text" class="form-control" id="userFirstName" name="first_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="userLastName" class="form-label">Last Name *</label>
                                <input type="text" class="form-control" id="userLastName" name="last_name" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="userUsername" class="form-label">Username *</label>
                                <input type="text" class="form-control" id="userUsername" name="username" required>
                                <div class="form-text">Must be unique</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="userEmail" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="userEmail" name="email" required>
                                <div class="form-text">Must be unique</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="userPassword" class="form-label">Password *</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="userPassword" name="password" required>
                                    <button type="button" class="btn btn-outline-secondary" id="toggleUserPasswordVisibility">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" id="generatePassword">
                                        <i class="fas fa-random"></i> Generate
                                    </button>
                                </div>
                                <div class="form-text">Minimum 8 characters</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="userRole" class="form-label">Role *</label>
                                <select class="form-select" id="userRole" name="role" required>
                                    <option value="user">User</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="userActive" name="is_active" checked>
                            <label class="form-check-label" for="userActive">
                                Account is active
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUserModalLabel">
                    <i class="fas fa-user-edit"></i> Edit User
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editUserForm">
                <input type="hidden" id="editUserId" name="id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editUserFirstName" class="form-label">First Name *</label>
                                <input type="text" class="form-control" id="editUserFirstName" name="first_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editUserLastName" class="form-label">Last Name *</label>
                                <input type="text" class="form-control" id="editUserLastName" name="last_name" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editUserUsername" class="form-label">Username *</label>
                                <input type="text" class="form-control" id="editUserUsername" name="username" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editUserEmail" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="editUserEmail" name="email" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editUserRole" class="form-label">Role *</label>
                                <select class="form-select" id="editUserRole" name="role" required>
                                    <option value="user">User</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="editUserActive" name="is_active">
                                    <label class="form-check-label" for="editUserActive">
                                        Account is active
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="resetPasswordModalLabel">
                    <i class="fas fa-key"></i> Reset Password
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="resetPasswordForm">
                <input type="hidden" id="resetPasswordUserId" name="user_id">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        Resetting password for: <strong id="resetPasswordUserName"></strong>
                    </div>
                    
                    <div class="mb-3">
                        <label for="newPassword" class="form-label">New Password *</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="newPassword" name="password" required>
                            <button type="button" class="btn btn-outline-secondary" id="generateNewPassword">
                                <i class="fas fa-random"></i> Generate
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="togglePasswordVisibility">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">Minimum 8 characters. User will need to change this on first login.</div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> 
                        <strong>Important:</strong> Make sure to securely communicate the new password to the user.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-key"></i> Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ** NEW ** Modals for Notes, Documents, and Videos -->

<!-- Create Note Modal -->
<div class="modal fade" id="createNoteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus"></i> New Note</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createNoteForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="noteTitle" class="form-label">Title</label>
                        <input type="text" class="form-control" id="noteTitle" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="noteContent" class="form-label">Content</label>
                        <textarea class="form-control" id="noteContent" name="content" rows="5"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Color</label>
                        <div>
                            <input type="radio" class="btn-check" name="color" id="color-yellow" value="yellow" checked>
                            <label class="btn btn-outline-warning" for="color-yellow">Yellow</label>
                            <input type="radio" class="btn-check" name="color" id="color-blue" value="blue">
                            <label class="btn btn-outline-primary" for="color-blue">Blue</label>
                            <input type="radio" class="btn-check" name="color" id="color-green" value="green">
                            <label class="btn btn-outline-success" for="color-green">Green</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Note</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Note Modal -->
<div class="modal fade" id="editNoteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Note</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editNoteForm">
                <input type="hidden" id="editNoteId" name="id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editNoteTitle" class="form-label">Title</label>
                        <input type="text" class="form-control" id="editNoteTitle" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="editNoteContent" class="form-label">Content</label>
                        <textarea class="form-control" id="editNoteContent" name="content" rows="5"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Color</label>
                        <div>
                            <input type="radio" class="btn-check" name="color" id="edit-color-yellow" value="yellow">
                            <label class="btn btn-outline-warning" for="edit-color-yellow">Yellow</label>
                            <input type="radio" class="btn-check" name="color" id="edit-color-blue" value="blue">
                            <label class="btn btn-outline-primary" for="edit-color-blue">Blue</label>
                            <input type="radio" class="btn-check" name="color" id="edit-color-green" value="green">
                            <label class="btn btn-outline-success" for="edit-color-green">Green</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Share Note Modal -->
<div class="modal fade" id="shareNoteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-share-alt"></i> Share Note</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="shareNoteForm">
                <input type="hidden" id="shareNoteId" name="note_id">
                <div class="modal-body">
                     <p>Share "<strong><span id="shareNoteTitle"></span></strong>" with other users.</p>
                    <div class="mb-3">
                        <label for="shareNoteEmails" class="form-label">User Emails</label>
                        <textarea class="form-control" id="shareNoteEmails" name="emails" rows="3" placeholder="Enter user emails, separated by commas..."></textarea>
                    </div>
                     <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="shareNoteWithAll" name="share_with_all">
                        <label class="form-check-label" for="shareNoteWithAll">Share with all users</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Share Note</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Upload Document Modal -->
<div class="modal fade" id="uploadDocumentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-upload"></i> Upload Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="uploadDocumentForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="documentFile" class="form-label">Select file</label>
                        <input class="form-control" type="file" id="documentFile" name="document" required>
                        <div class="form-text">Max file size: 5MB.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Share Document Modal -->
<div class="modal fade" id="shareDocumentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-share-alt"></i> Share Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="shareDocumentForm">
                <input type="hidden" id="shareDocumentId" name="document_id">
                 <div class="modal-body">
                     <p>Share "<strong><span id="shareDocumentTitle"></span></strong>" with other users.</p>
                    <div class="mb-3">
                        <label for="shareDocumentEmails" class="form-label">User Emails</label>
                        <textarea class="form-control" id="shareDocumentEmails" name="emails" rows="3" placeholder="Enter user emails, separated by commas..."></textarea>
                    </div>
                     <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="shareDocumentWithAll" name="share_with_all">
                        <label class="form-check-label" for="shareDocumentWithAll">Share with all users</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Share Document</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Video Modal -->
<div class="modal fade" id="addVideoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Add Video</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addVideoForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="videoUrl" class="form-label">YouTube Video URL</label>
                        <input type="url" class="form-control" id="videoUrl" name="url" required placeholder="https://www.youtube.com/watch?v=...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Video</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Share Video Modal -->
<div class="modal fade" id="shareVideoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-share-alt"></i> Share Video</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="shareVideoForm">
                <input type="hidden" id="shareVideoId" name="video_id">
                 <div class="modal-body">
                     <p>Share "<strong><span id="shareVideoTitle"></span></strong>" with other users.</p>
                    <div class="mb-3">
                        <label for="shareVideoEmails" class="form-label">User Emails</label>
                        <textarea class="form-control" id="shareVideoEmails" name="emails" rows="3" placeholder="Enter user emails, separated by commas..."></textarea>
                    </div>
                     <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="shareVideoWithAll" name="share_with_all">
                        <label class="form-check-label" for="shareVideoWithAll">Share with all users</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Share Video</button>
                </div>
            </form>
        </div>
    </div>
</div>
