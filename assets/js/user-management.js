// User Management Modal Functions
// This file contains standalone functions for user management modals

// Show create user modal
function showCreateUserModal() {
    console.log('showCreateUserModal called');
    var modalElement = document.getElementById('createUserModal');
    if (!modalElement) {
        alert('Create user modal not found. Please check if you are logged in as admin.');
        return;
    }
    
    // Reset form
    var form = document.getElementById('createUserForm');
    if (form) {
        form.reset();
        var activeCheckbox = document.getElementById('userActive');
        if (activeCheckbox) {
            activeCheckbox.checked = true;
        }
    }
    
    var modal = new bootstrap.Modal(modalElement);
    modal.show();
}

// Show edit user modal
function showEditUserModal(userId) {
    console.log('showEditUserModal called with userId:', userId);
    if (!userId) {
        alert('User ID is required');
        return;
    }
    
    // Fetch user data
    fetch('index.php?page=api&action=get_user&id=' + userId, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(function(response) {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.status);
        }
        
        // Check if response is actually JSON
        var contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            // If not JSON, get text to see what we received
            return response.text().then(function(text) {
                console.error('Expected JSON but received:', text.substring(0, 500));
                throw new Error('Server returned HTML instead of JSON. Check server logs.');
            });
        }
        
        return response.json();
    })
    .then(function(data) {
        if (data.success && data.user) {
            var user = data.user;
            
            // Populate form fields
            var fields = {
                'editUserId': user.id,
                'editUserFirstName': user.first_name || '',
                'editUserLastName': user.last_name || '',
                'editUserUsername': user.username || '',
                'editUserEmail': user.email || '',
                'editUserRole': user.role || 'user'
            };
            
            for (var fieldId in fields) {
                var element = document.getElementById(fieldId);
                if (element) {
                    element.value = fields[fieldId];
                }
            }
            
            var activeCheckbox = document.getElementById('editUserActive');
            if (activeCheckbox) {
                activeCheckbox.checked = user.is_active == 1;
            }
            
            // Show modal
            var modalElement = document.getElementById('editUserModal');
            if (modalElement) {
                var modal = new bootstrap.Modal(modalElement);
                modal.show();
            }
        } else {
            alert(data.message || 'Failed to load user data');
        }
    })
    .catch(function(error) {
        console.error('Error loading user:', error);
        alert('An error occurred while loading the user');
    });
}

// Show reset password modal
function showResetPasswordModal(userId, userName) {
    console.log('showResetPasswordModal called with:', userId, userName);
    if (!userId || !userName) {
        alert('User ID and name are required');
        return;
    }
    
    var userIdField = document.getElementById('resetPasswordUserId');
    var userNameField = document.getElementById('resetPasswordUserName');
    var passwordField = document.getElementById('newPassword');
    
    if (userIdField) userIdField.value = userId;
    if (userNameField) userNameField.textContent = userName;
    if (passwordField) passwordField.value = '';
    
    var modalElement = document.getElementById('resetPasswordModal');
    if (modalElement) {
        var modal = new bootstrap.Modal(modalElement);
        modal.show();
    } else {
        console.error('Reset password modal not found');
    }
}

// Export functions to global scope
window.showCreateUserModal = showCreateUserModal;
window.showEditUserModal = showEditUserModal;
window.showResetPasswordModal = showResetPasswordModal;

// Log that functions are loaded
console.log('User management functions loaded successfully');
