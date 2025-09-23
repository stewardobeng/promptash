// Custom JavaScript for Promptash

document.addEventListener('DOMContentLoaded', function() {
    // Remove any theme toggle buttons that might exist
    removeThemeToggles();
    
    // Initialize application
    initializeApp();

    // Initialize dropdowns with custom config to fix table overflow issue
    initializeDropdowns();

    // Initialize sidebar menu with persistent state
    initializeSidebarMenu();

    // Add fade-in animation to cards
    animateCards();

    // Initialize tooltips
    initializeTooltips();

    // Initialize copy functionality
    initializeCopyButtons();

    // Initialize search functionality
    initializeSearch();

    // Enhance dropdowns
    enhanceDropdowns();

    // Initialize modal functionality
    initializeModals();

    // Initialize admin user management modals (if admin)
    if (document.getElementById('createUserModal')) {
        initializeAdminUserModals();
    }
    
    // Initialize profile validation
    initializeProfileValidation();

    // Initialize password visibility toggles
    initializePasswordToggles();

    // Initialize note drag and drop
    initializeNoteDragAndDrop();

    // ** NEW ** Initialize Passkey buttons if they exist
    const registerBtn = document.getElementById('registerPasskeyBtn');
    if (registerBtn) {
        registerBtn.addEventListener('click', registerPasskey);
    }

    const loginBtn = document.getElementById('loginWithPasskeyBtn');
    if (loginBtn) {
        loginBtn.addEventListener('click', loginWithPasskey);
    }

    initializePasskeyManagement();
});  

// ** NEW ** Passkey JavaScript Functions

// Helper function to convert base64url to ArrayBuffer
function bufferDecode(value) {
    return Uint8Array.from(atob(value.replace(/_/g, '/').replace(/-/g, '+')), c => c.charCodeAt(0));
}

// Helper function to convert ArrayBuffer to base64url
function bufferEncode(value) {
    return btoa(String.fromCharCode.apply(null, new Uint8Array(value)))
        .replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}

// --- PASSKEY REGISTRATION ---
async function registerPasskey() {
    try {
        const resp = await fetch('index.php?page=api&action=passkey_register_options');
        if (!resp.ok) {
            const errorBody = await resp.text();
            throw new Error(`Passkey options request failed (${resp.status}): ${errorBody || resp.statusText}`);
        }

        let options = await resp.json();
        if (options && options.publicKey) {
            options = options.publicKey;
        }
        if (!options || !options.challenge || !options.user) {
            throw new Error('Passkey options were incomplete.');
        }

        // 2. Turn base64url fields into ArrayBuffers
        options.challenge = bufferDecode(options.challenge);
        options.user.id = bufferDecode(options.user.id);

        if (Array.isArray(options.excludeCredentials)) {
            options.excludeCredentials = options.excludeCredentials.map((cred) => ({
                ...cred,
                id: typeof cred.id === 'string' ? bufferDecode(cred.id) : cred.id,
            }));
        }

        // 3. Call the browser's WebAuthn API to create the credential
        const cred = await navigator.credentials.create({
            publicKey: options
        });

        // 4. Encode the credential's ArrayBuffer fields into base64url
        const attestationResponse = {
            id: cred.id,
            rawId: bufferEncode(cred.rawId),
            type: cred.type,
            response: {
                attestationObject: bufferEncode(cred.response.attestationObject),
                clientDataJSON: bufferEncode(cred.response.clientDataJSON),
        },
        };

        // 5. Send the new credential to the server for verification
        const verificationResp = await fetch('index.php?page=api&action=passkey_register_verify', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(attestationResponse),
        });
        if (!verificationResp.ok) {
            const errorBody = await verificationResp.text();
            throw new Error(`Passkey verification failed (${verificationResp.status}): ${errorBody || verificationResp.statusText}`);
        }
        const verificationJSON = await verificationResp.json();

        // 6. Show success or error to the user
        if (verificationJSON && verificationJSON.success) {
            const passkeyName = verificationJSON.passkey?.display_name || 'Passkey';
            showToast(`${passkeyName} registered successfully!`, 'success');

            if (Array.isArray(verificationJSON.passkeys)) {
                renderPasskeyList(verificationJSON.passkeys);
            } else {
                setTimeout(() => window.location.reload(), 1500);
            }
        } else {
            showToast(verificationJSON.message || 'Failed to register passkey.', 'error');
        }
    } catch (err) {
        console.error("Passkey registration error:", err);
        const fallbackMessage = 'Could not create passkey. Make sure you are on a secure (HTTPS) connection.';
        showToast(err?.message || fallbackMessage, 'error');
    }
}


function renderPasskeyList(passkeys) {
    const statusEl = document.getElementById('passkeyStatus');
    const listEl = document.getElementById('passkeyList');

    if (!statusEl || !listEl) {
        return;
    }

    const hasPasskeys = Array.isArray(passkeys) && passkeys.length > 0;

    statusEl.classList.remove('alert-success', 'alert-secondary');
    statusEl.classList.add(hasPasskeys ? 'alert-success' : 'alert-secondary');

    if (hasPasskeys) {
        statusEl.innerHTML = '<i class="fas fa-check-circle"></i> Passkeys are <strong>enabled</strong> for your account.';
        listEl.classList.remove('d-none');
        listEl.innerHTML = '';

        passkeys.forEach((passkey) => {
            const name = (passkey?.display_name || 'Passkey').toString();
            const added = passkey?.added_on_formatted || '';
            const id = passkey?.id || '';

            const item = document.createElement('li');
            item.className = 'list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2';
            item.dataset.passkeyId = id;
            item.dataset.passkeyName = name;

            const labelWrapper = document.createElement('div');
            labelWrapper.className = 'd-flex align-items-center';

            const icon = document.createElement('i');
            icon.className = 'fas fa-fingerprint me-2';
            labelWrapper.appendChild(icon);

            const nameSpan = document.createElement('span');
            nameSpan.className = 'passkey-name';
            nameSpan.textContent = name;
            labelWrapper.appendChild(nameSpan);

            const actionWrapper = document.createElement('div');
            actionWrapper.className = 'd-flex align-items-center gap-2';

            if (added) {
                const addedSpan = document.createElement('span');
                addedSpan.className = 'text-muted small';
                addedSpan.textContent = added;
                actionWrapper.appendChild(addedSpan);
            }

            const renameBtn = document.createElement('button');
            renameBtn.type = 'button';
            renameBtn.className = 'btn btn-sm btn-outline-secondary';
            renameBtn.dataset.passkeyAction = 'rename';
            renameBtn.dataset.passkeyId = id;
            renameBtn.dataset.passkeyName = name;
            renameBtn.innerHTML = '<i class="fas fa-edit"></i> Rename';
            actionWrapper.appendChild(renameBtn);

            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'btn btn-sm btn-outline-danger';
            deleteBtn.dataset.passkeyAction = 'delete';
            deleteBtn.dataset.passkeyId = id;
            deleteBtn.dataset.passkeyName = name;
            deleteBtn.innerHTML = '<i class="fas fa-trash-alt"></i> Delete';
            actionWrapper.appendChild(deleteBtn);

            item.appendChild(labelWrapper);
            item.appendChild(actionWrapper);
            listEl.appendChild(item);
        });
    } else {
        statusEl.innerHTML = '<i class="fas fa-info-circle"></i> No passkeys are registered yet.';
        listEl.classList.add('d-none');
        listEl.innerHTML = '';
    }
}


function initializePasskeyManagement() {
    const listEl = document.getElementById('passkeyList');
    if (!listEl) {
        return;
    }

    listEl.addEventListener('click', async (event) => {
        const target = event.target.closest('[data-passkey-action]');
        if (!target) {
            return;
        }

        event.preventDefault();

        const action = target.dataset.passkeyAction;
        const passkeyId = target.dataset.passkeyId;
        const currentName = target.dataset.passkeyName || target.closest('li')?.dataset.passkeyName || 'Passkey';

        if (!passkeyId) {
            return;
        }

        try {
            if (action === 'rename') {
                const proposed = prompt('Enter a new name for this passkey:', currentName);
                if (proposed === null) {
                    return;
                }

                const trimmed = proposed.trim();
                if (!trimmed) {
                    showToast('Passkey name cannot be blank.', 'error');
                    return;
                }

                await updatePasskeyName(passkeyId, trimmed, currentName);
            } else if (action === 'delete') {
                const confirmed = confirm(`Remove "${currentName}"? You will no longer be able to sign in with this passkey.`);
                if (!confirmed) {
                    return;
                }

                await removePasskey(passkeyId, currentName);
            }
        } catch (err) {
            console.error('Passkey management error:', err);
            showToast(err?.message || 'Passkey action failed.', 'error');
        }
    });
}

async function updatePasskeyName(passkeyId, newName, previousName = 'Passkey') {
    const resp = await fetch('index.php?page=api&action=passkey_rename', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: passkeyId, name: newName }),
    });

    let data;
    try {
        data = await resp.json();
    } catch (err) {
        throw new Error('Unable to rename passkey.');
    }

    if (!resp.ok || !data.success) {
        throw new Error(data?.message || 'Unable to rename passkey.');
    }

    renderPasskeyList(data.passkeys);
    const finalName = data.passkey?.display_name || newName || previousName || 'Passkey';
    showToast(data.message || `Passkey renamed to "${finalName}".`, 'success');
}

async function removePasskey(passkeyId, previousName = 'Passkey') {
    const resp = await fetch('index.php?page=api&action=passkey_delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: passkeyId }),
    });

    let data;
    try {
        data = await resp.json();
    } catch (err) {
        throw new Error('Unable to delete passkey.');
    }

    if (!resp.ok || !data.success) {
        throw new Error(data?.message || 'Unable to delete passkey.');
    }

    renderPasskeyList(data.passkeys);
    const removedName = data.passkey?.display_name || previousName || 'Passkey';
    showToast(data.message || `Passkey "${removedName}" deleted.`, 'success');
}

// --- PASSKEY LOGIN ---
async function loginWithPasskey() {
    try {
        // 1. Get authentication options from the server
        const resp = await fetch('index.php?page=api&action=passkey_login_options');
        let options = await resp.json();
        if (options && options.publicKey) {
            options = options.publicKey;
        }
        if (!options || !options.challenge) {
            throw new Error('Passkey options were incomplete.');
        }

        // 2. Turn base64url fields into ArrayBuffers
        options.challenge = bufferDecode(options.challenge);

        if (Array.isArray(options.allowCredentials)) {
            options.allowCredentials = options.allowCredentials.map((cred) => ({
                ...cred,
                id: typeof cred.id === 'string' ? bufferDecode(cred.id) : cred.id,
            }));
        }

        // 3. Call the browser\'s WebAuthn API to get the assertion
        const assertion = await navigator.credentials.get({
            publicKey: options
        });

        // 4. Encode the assertion\'s ArrayBuffer fields into base64url
        const authResponse = {
            id: assertion.id,
            rawId: bufferEncode(assertion.rawId),
            type: assertion.type,
            response: {
                authenticatorData: bufferEncode(assertion.response.authenticatorData),
                clientDataJSON: bufferEncode(assertion.response.clientDataJSON),
                signature: bufferEncode(assertion.response.signature),
                userHandle: bufferEncode(assertion.response.userHandle),
            },
        };

        // 5. Send the assertion to the server for verification
        const verificationResp = await fetch('index.php?page=api&action=passkey_login_verify', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(authResponse),
        });
        const verificationJSON = await verificationResp.json();

        // 6. If successful, redirect to the dashboard
        if (verificationJSON && verificationJSON.success) {
            showToast('Login successful!', 'success');
            window.location.href = 'index.php?page=dashboard';
        } else {
            showToast(verificationJSON?.message || 'Passkey login failed.', 'error');
        }
    } catch (err) {
        console.error('Passkey login error:', err);
        showToast(err?.message || 'Could not authenticate with passkey.', 'error');
    }
}

// --- EXISTING APPLICATION FUNCTIONS ---

// Function to remove any theme toggle buttons
function removeThemeToggles() {
    const selectors = [
        '#themeToggle',
        '.theme-toggle',
        '[title*="Toggle"]',
        '[title*="Dark Mode"]',
        '[title*="Light Mode"]'
    ];
    
    selectors.forEach(selector => {
        document.querySelectorAll(selector).forEach(element => {
            element.remove();
        });
    });
    
    // Remove buttons with moon/sun icons
    document.querySelectorAll('.top-navbar button').forEach(btn => {
        const icon = btn.querySelector('i');
        if (icon && (icon.classList.contains('fa-moon') || icon.classList.contains('fa-sun'))) {
            btn.remove();
        }
    });
}

// Periodically check for and remove theme toggles
setInterval(removeThemeToggles, 1000);

// Initialize application
function initializeApp() {
    // Add global fetch error handler for session expiration (only if not on auth pages)
    const currentPage = new URLSearchParams(window.location.search).get('page');
    const authPages = ['login', 'register', 'forgot_password', 'reset_password'];
    if (!authPages.includes(currentPage)) {
        const originalFetch = window.fetch;
        window.fetch = function(...args) {
            return originalFetch.apply(this, args)
                .then(response => {
                    // Check if we got a login page instead of expected JSON
                    if (response.url && response.url.includes('page=login') && !args[0].includes('page=login')) {
                        // Session expired, redirect to login
                        console.log('Session expired, redirecting to login');
                        window.location.href = 'index.php?page=login&message=' + encodeURIComponent('Your session has expired. Please log in again.');
                        return;
                }
                    return response;
            })
                .catch(error => {
                    console.error('Fetch error:', error);
                    throw error;
            });
        };
    }

    // Add loading animation to buttons on AJAX forms
    document.querySelectorAll('form[id]').forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
                submitBtn.disabled = true;

                // Re-enable after 5 seconds as fallback
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
            }, 5000);
        }
        });
    });

    // Add smooth scrolling
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
            });
        }
        });
    });
}

// Animate cards on page load
function animateCards() {
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';

        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
}

// Initialize tooltips
function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// Get action name from icon class
function getActionFromIcon(iconClass) {
    const iconMap = {
        'fa-edit': 'Edit',
        'fa-trash': 'Delete',
        'fa-eye': 'View',
        'fa-star': 'Favorite',
        'fa-copy': 'Copy',
        'fa-download': 'Download',
        'fa-upload': 'Upload',
        'fa-share': 'Share',
        'fa-print': 'Print',
        'fa-search': 'Search',
        'fa-filter': 'Filter',
        'fa-sort': 'Sort',
        'fa-refresh': 'Refresh'
    };

    for (const [icon, action] of Object.entries(iconMap)) {
        if (iconClass.includes(icon)) {
            return action;
        }
    }
    return null;
}

// ** NEW/MODIFIED ** Initialize dropdowns with a fix for clipping issues
function initializeDropdowns() {
    var dropdownElementList = [].slice.call(document.querySelectorAll('[data-bs-toggle="dropdown"]'));
    dropdownElementList.map(function (dropdownToggleEl) {
        // Check if the dropdown is inside a table-responsive container
        if (dropdownToggleEl.closest('.table-responsive')) {
            // If so, initialize with a 'fixed' strategy to prevent clipping
            return new bootstrap.Dropdown(dropdownToggleEl, {
                popperConfig: {
                    strategy: 'fixed',
            },
        });
        } else {
            // Otherwise, initialize with default settings
            return new bootstrap.Dropdown(dropdownToggleEl);
        }
    });
}

// Initialize copy functionality
function initializeCopyButtons() {
    window.copyToClipboard = function(text, button, promptId = null) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => {
                showCopySuccess(button);
                if (promptId) {
                    trackPromptUsage(promptId);
            }
        }).catch(() => {
                fallbackCopyToClipboard(text, button, promptId);
        });
        } else {
            fallbackCopyToClipboard(text, button, promptId);
        }
    };

    function fallbackCopyToClipboard(text, button, promptId = null) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
            document.execCommand('copy');
            showCopySuccess(button);
            if (promptId) {
                trackPromptUsage(promptId);
        }
        } catch (err) {
            showCopyError(button);
        }
        document.body.removeChild(textArea);
    }
    
    function trackPromptUsage(promptId) {
        // Track usage when prompt is copied
        fetch('index.php?page=api&action=increment_usage', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
        },
            body: 'prompt_id=' + encodeURIComponent(promptId)
        })
        .then(response => {
            // Check if response is actually JSON
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return response.json();
        } else {
                // If not JSON, might be a redirect to login - silently ignore
                return { success: false, error: 'Not JSON response' };
        }
        })
        .then(data => {
            // Silently handle the result - don't show errors to user for usage tracking
            if (data && data.success) {
                console.log('Usage tracked for prompt:', promptId);
        }
        })
        .catch(error => {
            // Silently handle errors for usage tracking
            console.log('Usage tracking failed (non-critical):', error);
        });
    }

    function showCopySuccess(button) {
        if (button) {
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check"></i> Copied!';
            button.classList.replace('btn-outline-primary', 'btn-success');
            setTimeout(() => {
                button.innerHTML = originalText;
                button.classList.replace('btn-success', 'btn-outline-primary');
        }, 2000);
        }
        showToast('Copied to clipboard!', 'success');
    }

    function showCopyError(button) {
        if (button) {
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-times"></i> Failed';
            button.classList.replace('btn-outline-primary', 'btn-danger');
            setTimeout(() => {
                button.innerHTML = originalText;
                button.classList.replace('btn-danger', 'btn-outline-primary');
        }, 2000);
        }
        showToast('Copy failed. Please try again.', 'error');
    }
}

// Initialize search functionality
function initializeSearch() {
    const searchInputs = document.querySelectorAll('input[type="search"], input[name="search"]');
    searchInputs.forEach(input => {
        const searchIcon = input.parentElement.querySelector('.fa-search');
        if (searchIcon) {
            input.addEventListener('focus', () => {
                searchIcon.style.transform = 'scale(1.2)';
                searchIcon.style.color = '#667eea';
        });
            input.addEventListener('blur', () => {
                searchIcon.style.transform = 'scale(1)';
                searchIcon.style.color = '';
        });
        }
    });
}

// Initialize password visibility toggles
function initializePasswordToggles() {
    const togglePassword = (toggleEl, passwordEl) => {
        if (toggleEl && passwordEl) {
            toggleEl.addEventListener('click', function () {
                const type = passwordEl.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordEl.setAttribute('type', type);
                this.classList.toggle('fa-eye-slash');
        });
        }
    };

    togglePassword(document.querySelector('#togglePassword'), document.querySelector('#password'));
    togglePassword(document.querySelector('#toggleConfirmPassword'), document.querySelector('#confirm_password'));
}

// Show toast notification
function showToast(message, type = 'info', duration = 3000) {
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        toastContainer.style.zIndex = '9999';
        document.body.appendChild(toastContainer);
    }

    const toastId = 'toast-' + Date.now();
    const bgClass = type === 'error' ? 'danger' : type;
    const iconClass = type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle';

    const toastHtml = `
        <div id="${toastId}" class="toast align-items-center text-white bg-${bgClass} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-${iconClass} me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>`;

    toastContainer.insertAdjacentHTML('beforeend', toastHtml);

    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, { delay: duration });
    toast.show();
    toastElement.addEventListener('hidden.bs.toast', () => toastElement.remove());
}

// Sidebar toggle for mobile
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
}


// --- MODIFICATION START: Sidebar State Management ---

// Submenu toggle functionality that saves state to localStorage
function toggleSubmenu(menuId) {
    const submenu = document.getElementById(menuId);
    const header = document.querySelector(`[data-menu="${menuId}"]`);
    
    if (submenu.classList.contains('expanded')) {
        submenu.classList.remove('expanded');
        header.classList.remove('expanded');
        localStorage.setItem(menuId + '-state', 'collapsed');
    } else {
        submenu.classList.add('expanded');
        header.classList.add('expanded');
        localStorage.setItem(menuId + '-state', 'expanded');
    }
}

// Initialize sidebar menu state based on localStorage and current page
function initializeSidebarMenu() {
    const currentPage = new URLSearchParams(window.location.search).get('page') || 'dashboard';
    
    // Define page to menu mapping
    const pageMenuMap = {
        'prompt_generator': 'ai-tools-menu',
        'prompts': 'my-content-menu',
        'bookmarks': 'my-content-menu',
        'notes': 'my-content-menu',
        'documents': 'my-content-menu',
        'videos': 'my-content-menu',
        'categories': 'my-content-menu',
        'shared_prompts': 'shared-content-menu',
        'shared_bookmarks': 'shared-content-menu',
        'shared_notes': 'shared-content-menu',
        'shared_documents': 'shared-content-menu',
        'shared_videos': 'shared-content-menu',
        'profile': 'account-menu',
        'settings': 'account-menu',
        'two_factor': 'account-menu',
        'notifications': 'account-menu',
        'backup': 'account-menu',
        'upgrade': 'account-menu',
        'admin': 'admin-menu',
        'users': 'admin-menu',
        'admin_backup': 'admin-menu',
        'security_logs': 'admin-menu'
    };
    
    document.querySelectorAll('.menu-section-header').forEach(header => {
        const menuId = header.getAttribute('data-menu');
        const submenu = document.getElementById(menuId);
        let state = localStorage.getItem(menuId + '-state');

        // If the current page is in a menu, that menu should be expanded,
        // overriding a stored 'collapsed' state for better user experience.
        if (pageMenuMap[currentPage] === menuId) {
            state = 'expanded';
        }

        // Apply the stored or determined state. Default to collapsed if no state is stored and not on a child page.
        if (state === 'expanded') {
            submenu.classList.add('expanded');
            header.classList.add('expanded');
        } else {
            submenu.classList.remove('expanded');
            header.classList.remove('expanded');
        }
    });
    
    // Add click handlers for menu sections
    initializeMenuHandlers();
}

// Initialize click handlers for menu sections
function initializeMenuHandlers() {
    document.querySelectorAll('.menu-section-header').forEach(header => {
        header.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const menuId = this.getAttribute('data-menu');
            if (menuId) {
                toggleSubmenu(menuId);
        }
        });
    });
}

// --- MODIFICATION END ---

document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    
    // Handle regular sidebar mobile toggle
    if (sidebar && !sidebar.contains(event.target) && !event.target.closest('[onclick="toggleSidebar()"]')) {
        sidebar.classList.remove('show');
    }
});


// Enhanced dropdown functionality
function enhanceDropdowns() {
    // When a dropdown is shown, find its parent card and elevate it
    document.addEventListener('shown.bs.dropdown', function(event) {
        const dropdownToggle = event.target;
        const card = dropdownToggle.closest('.card');
        
        if (card) {
            // First, reset any other cards that might be open
            document.querySelectorAll('.card.dropdown-open').forEach(openCard => {
                openCard.classList.remove('dropdown-open');
        });
            // Then, elevate the current card
            card.classList.add('dropdown-open');
        }
    });

    // When a dropdown is hidden, remove the elevation class from its card
    document.addEventListener('hidden.bs.dropdown', function(event) {
        const dropdownToggle = event.target;
        const card = dropdownToggle.closest('.card');
        if (card) {
            card.classList.remove('dropdown-open');
        }
    });
}

// Modal Management System
function initializeModals() {
    loadCategoriesForModals();
    initializeFormModal('createPromptForm', 'create_prompt', 'Prompt created successfully!');
    initializeFormModal('createCategoryForm', 'create_category', 'Category created successfully!', loadCategoriesForModals);
    initializeFormModal('editPromptForm', 'edit_prompt', 'Prompt updated successfully!');
    initializeFormModal('editCategoryForm', 'edit_category', 'Category updated successfully!', loadCategoriesForModals);
    initializeFormModal('sharePromptForm', 'share_prompt', 'Prompt shared successfully!');
    initializeFormModal('createBookmarkForm', 'create_bookmark', 'Bookmark saved successfully!');
    initializeFormModal('editBookmarkForm', 'edit_bookmark', 'Bookmark updated successfully!');
    initializeFormModal('shareBookmarkForm', 'share_bookmark', 'Bookmark shared successfully!');
    
    // ** NEW ** Initialize new feature modals
    initializeFormModal('createNoteForm', 'create_note', 'Note created successfully!');
    initializeFormModal('editNoteForm', 'edit_note', 'Note updated successfully!');
    initializeFormModal('uploadDocumentForm', 'upload_document', 'Document uploaded successfully!');
    initializeFormModal('addVideoForm', 'add_video', 'Video added successfully!');
    initializeFormModal('shareNoteForm', 'share_note', 'Note shared successfully!');
    initializeFormModal('shareDocumentForm', 'share_document', 'Document shared successfully!');
    initializeFormModal('shareVideoForm', 'share_video', 'Video shared successfully!');


    initializeDeleteConfirmModal();
    initializeBookmarkUrlFetching();

    // Close all open dropdowns when a modal opens
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.addEventListener('show.bs.modal', function () {
            // Find all open dropdowns and close them
            const openDropdowns = document.querySelectorAll('.dropdown-menu.show');
            openDropdowns.forEach(dropdown => {
                // Find the dropdown toggle button
                const dropdownToggle = dropdown.previousElementSibling;
                if (dropdownToggle && dropdownToggle.hasAttribute('data-bs-toggle')) {
                    const dropdownInstance = bootstrap.Dropdown.getInstance(dropdownToggle);
                    if (dropdownInstance) {
                        dropdownInstance.hide();
                }
            }
        });
            
            // Also remove dropdown-open class from any cards
            document.querySelectorAll('.card.dropdown-open').forEach(card => {
                card.classList.remove('dropdown-open');
        });
        });
    });
}

function loadCategoriesForModals() {
    fetch('index.php?page=api&action=get_categories')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const selects = document.querySelectorAll('#promptCategory, #editPromptCategory');
                selects.forEach(select => {
                    select.innerHTML = '<option value="">Select a category</option>';
                    data.categories.forEach(category => {
                        select.innerHTML += `<option value="${category.id}">${category.name}</option>`;
                });
            });
        }
        });
}

function initializeFormModal(formId, action, successMessage, callback) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const modalEl = this.closest('.modal');
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
        submitBtn.disabled = true;

        fetch(`index.php?page=api&action=${action}`, {
            method: 'POST',
            body: new FormData(form)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(modalEl).hide();
                form.reset();
                showToast(successMessage, 'success');
                if (callback) callback(data);
                setTimeout(() => window.location.reload(), 1000);
        } else {
                showToast(data.message || 'An error occurred.', 'error');
        }
        })
        .catch(() => showToast('A network error occurred.', 'error'))
        .finally(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    });
}


function initializeDeleteConfirmModal() {
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    if (!confirmBtn) return;

    confirmBtn.addEventListener('click', function() {
        const { action, id, type } = this.dataset;
        if (!action || !id) return;

        this.disabled = true;

        fetch(`index.php?page=api&action=${action}`, {
            method: 'POST',
            body: new URLSearchParams({ id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(this.closest('.modal')).hide();
                showToast(`${type} deleted successfully!`, 'success');
                setTimeout(() => window.location.reload(), 1000);
        } else {
                showToast(data.message || `Failed to delete ${type}.`, 'error');
        }
        })
        .catch(() => showToast('A network error occurred.', 'error'))
        .finally(() => { this.disabled = false; });
    });
}

// ** NEW ** Bookmark URL fetching logic
function initializeBookmarkUrlFetching() {
    const urlInput = document.getElementById('bookmarkUrl');
    if (!urlInput) return;

    let timeout = null;
    urlInput.addEventListener('input', () => {
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            fetchUrlMetadata(urlInput.value);
        }, 1000); // 1-second delay
    });
}

function fetchUrlMetadata(url) {
    const metadataFields = document.getElementById('metadata-fields');
    const loader = document.getElementById('metadata-loader');
    const saveBtn = document.getElementById('saveBookmarkBtn');
    
    if (!url || !url.startsWith('http')) {
        metadataFields.style.display = 'none';
        saveBtn.disabled = true;
        return;
    }

    loader.style.display = 'block';
    metadataFields.style.display = 'none';
    saveBtn.disabled = true;

    fetch('index.php?page=api&action=fetch_url_metadata', {
        method: 'POST',
        body: new URLSearchParams({ url })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.metadata) {
            document.getElementById('bookmarkTitle').value = data.metadata.title;
            document.getElementById('bookmarkDescription').value = data.metadata.description;
            document.getElementById('bookmarkImage').value = data.metadata.image;
            metadataFields.style.display = 'block';
            saveBtn.disabled = false;
        } else {
            showToast(data.message || 'Could not fetch metadata.', 'error');
            // Show fields anyway for manual entry
            metadataFields.style.display = 'block';
            saveBtn.disabled = false;
        }
    })
    .catch(() => {
        showToast('Error fetching URL metadata.', 'error');
        metadataFields.style.display = 'block';
        saveBtn.disabled = false;
    })
    .finally(() => {
        loader.style.display = 'none';
    });
}


// Global functions to trigger modals
window.showCreateBookmarkModal = function() {
    const form = document.getElementById('createBookmarkForm');
    form.reset();
    document.getElementById('metadata-fields').style.display = 'none';
    document.getElementById('saveBookmarkBtn').disabled = true;
    new bootstrap.Modal(document.getElementById('createBookmarkModal')).show();
};

window.showEditBookmarkModal = function(bookmarkId) {
    fetch(`index.php?page=api&action=get_bookmark&id=${bookmarkId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const bookmark = data.bookmark;
                document.getElementById('editBookmarkId').value = bookmark.id;
                document.getElementById('editBookmarkTitle').value = bookmark.title;
                document.getElementById('editBookmarkDescription').value = bookmark.description || '';
                document.getElementById('editBookmarkImage').value = bookmark.image || '';
                document.getElementById('editBookmarkTags').value = bookmark.tags || '';
                new bootstrap.Modal(document.getElementById('editBookmarkModal')).show();
        } else {
                showToast('Failed to load bookmark data.', 'error');
        }
        });
};

window.showShareBookmarkModal = function(bookmarkId, bookmarkTitle) {
    document.getElementById('shareBookmarkId').value = bookmarkId;
    document.getElementById('shareBookmarkTitle').textContent = bookmarkTitle;
    new bootstrap.Modal(document.getElementById('shareBookmarkModal')).show();
};

window.showEditPromptModal = function(promptId) {
    fetch(`index.php?page=api&action=get_prompt&id=${promptId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const prompt = data.prompt;
                document.getElementById('editPromptId').value = prompt.id;
                document.getElementById('editPromptTitle').value = prompt.title;
                document.getElementById('editPromptDescription').value = prompt.description || '';
                document.getElementById('editPromptContent').value = prompt.content;
                document.getElementById('editPromptCategory').value = prompt.category_id || '';
                document.getElementById('editPromptTags').value = prompt.tags || '';
                new bootstrap.Modal(document.getElementById('editPromptModal')).show();
        } else {
                showToast('Failed to load prompt data.', 'error');
        }
        });
};

window.showEditCategoryModal = function(categoryId) {
    fetch(`index.php?page=api&action=get_category&id=${categoryId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const category = data.category;
                document.getElementById('editCategoryId').value = category.id;
                document.getElementById('editCategoryName').value = category.name;
                document.getElementById('editCategoryDescription').value = category.description || '';
                new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
        } else {
                showToast('Failed to load category data.', 'error');
        }
        });
};

window.showDeleteConfirmModal = function(type, id, name, action) {
    const modalEl = document.getElementById('deleteConfirmModal');
    modalEl.querySelector('#deleteConfirmMessage').textContent = `Are you sure you want to delete the ${type.toLowerCase()} "${name}"? This action cannot be undone.`;
    const confirmBtn = modalEl.querySelector('#confirmDeleteBtn');
    confirmBtn.dataset.action = action;
    confirmBtn.dataset.id = id;
    confirmBtn.dataset.type = type;
    new bootstrap.Modal(modalEl).show();
};

window.showSharePromptModal = function(promptId, promptTitle) {
    document.getElementById('sharePromptId').value = promptId;
    document.getElementById('sharePromptTitle').textContent = promptTitle;
    new bootstrap.Modal(document.getElementById('sharePromptModal')).show();
};

window.unsharePrompt = function(promptId, recipientId = null) {
    unshareItem('prompt', promptId, recipientId);
};

window.saveSharedPrompt = function(promptId, promptTitle) {
    saveSharedItem('prompt', promptId, promptTitle);
};

window.unshareBookmark = function(bookmarkId, recipientId = null) {
    unshareItem('bookmark', bookmarkId, recipientId);
};

window.saveSharedBookmark = function(bookmarkId, bookmarkTitle) {
    saveSharedItem('bookmark', bookmarkId, bookmarkTitle);
};

// ** NEW ** Functions for Notes, Documents, and Videos
window.showEditNoteModal = function(noteId) {
    fetch(`index.php?page=api&action=get_note&id=${noteId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const note = data.note;
                document.getElementById('editNoteId').value = note.id;
                document.getElementById('editNoteTitle').value = note.title;
                document.getElementById('editNoteContent').value = note.content;
                document.getElementById(`edit-color-${note.color}`).checked = true;
                new bootstrap.Modal(document.getElementById('editNoteModal')).show();
        } else {
                showToast('Failed to load note data.', 'error');
        }
        });
};

window.showShareNoteModal = function(noteId, noteTitle) {
    document.getElementById('shareNoteId').value = noteId;
    document.getElementById('shareNoteTitle').textContent = noteTitle;
    new bootstrap.Modal(document.getElementById('shareNoteModal')).show();
};

window.unshareNote = function(noteId, recipientId = null) {
    unshareItem('note', noteId, recipientId);
};

window.saveSharedNote = function(noteId, noteTitle) {
    saveSharedItem('note', noteId, noteTitle);
};


window.deleteNote = function(noteId) {
    if (confirm('Are you sure you want to delete this note?')) {
        fetch('index.php?page=api&action=delete_note', {
            method: 'POST',
            body: new URLSearchParams({ id: noteId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Note deleted successfully.', 'success');
                setTimeout(() => window.location.reload(), 1000);
        } else {
                showToast('Failed to delete note.', 'error');
        }
        });
    }
};

window.togglePin = function(noteId) {
    fetch('index.php?page=api&action=toggle_pin_note', {
        method: 'POST',
        body: new URLSearchParams({ id: noteId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            showToast('Failed to pin note.', 'error');
        }
    });
};

window.showShareDocumentModal = function(docId, docName) {
    document.getElementById('shareDocumentId').value = docId;
    document.getElementById('shareDocumentTitle').textContent = docName;
    new bootstrap.Modal(document.getElementById('shareDocumentModal')).show();
};

window.unshareDocument = function(docId, recipientId = null) {
    unshareItem('document', docId, recipientId);
};

window.saveSharedDocument = function(docId, docName) {
    saveSharedItem('document', docId, docName);
};

window.deleteDocument = function(docId) {
    if (confirm('Are you sure you want to delete this document?')) {
        fetch('index.php?page=api&action=delete_document', {
            method: 'POST',
            body: new URLSearchParams({ id: docId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Document deleted successfully.', 'success');
                setTimeout(() => window.location.reload(), 1000);
        } else {
                showToast('Failed to delete document.', 'error');
        }
        });
    }
};

window.showShareVideoModal = function(videoId, videoTitle) {
    document.getElementById('shareVideoId').value = videoId;
    document.getElementById('shareVideoTitle').textContent = videoTitle;
    new bootstrap.Modal(document.getElementById('shareVideoModal')).show();
};

window.unshareVideo = function(videoId, recipientId = null) {
    unshareItem('video', videoId, recipientId);
};

window.saveSharedVideo = function(videoId, videoTitle) {
    saveSharedItem('video', videoId, videoTitle);
};

window.playVideo = function(url, title) {
    const playerContainer = document.getElementById('inPageVideoPlayerContainer');
    const playerTitle = document.getElementById('inPageVideoTitle');
    const playerFrame = document.getElementById('inPageVideoPlayerFrame');

    if(!playerContainer || !playerTitle || !playerFrame) return;

    // Extract YouTube video ID
    const videoIdMatch = url.match(/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
    const videoId = videoIdMatch ? videoIdMatch[1] : null;
    
    if (videoId) {
        const embedUrl = `https://www.youtube.com/embed/${videoId}?autoplay=1`;
        playerTitle.textContent = title;
        playerFrame.src = embedUrl;
        playerContainer.style.display = 'block';
        playerContainer.scrollIntoView({ behavior: 'smooth' });
    } else {
        showToast('Invalid YouTube URL.', 'error');
    }
};

window.closeVideoPlayer = function() {
    const playerContainer = document.getElementById('inPageVideoPlayerContainer');
    const playerFrame = document.getElementById('inPageVideoPlayerFrame');
    if (playerContainer && playerFrame) {
        playerFrame.src = ''; // Stop the video
        playerContainer.style.display = 'none';
    }
};

// Generic sharing functions
function unshareItem(type, id, recipientId = null) {
    const confirmMessage = recipientId ? 
        `Are you sure you want to unshare this ${type} from the specific user?` : 
        `Are you sure you want to unshare this ${type} from all users?`;
    
    if (!confirm(confirmMessage)) return;
    
    const formData = new FormData();
    formData.append(`${type}_id`, id);
    if (recipientId) {
        formData.append('recipient_id', recipientId);
    }
    
    fetch(`index.php?page=api&action=unshare_${type}`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast(data.message || `Failed to unshare ${type}.`, 'error');
        }
    })
    .catch(() => showToast('A network error occurred.', 'error'));
}

function saveSharedItem(type, id, title) {
    const confirmMessage = `Are you sure you want to make a copy of "${title}"?`;
    
    if (!confirm(confirmMessage)) return;
    
    const formData = new FormData();
    formData.append(`${type}_id`, id);
    
    fetch(`index.php?page=api&action=save_shared_${type}`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
        } else {
            showToast(data.message || `Failed to save shared ${type}.`, 'error');
        }
    })
    .catch(() => showToast('A network error occurred.', 'error'));
}


// Admin User Management System
function initializeAdminUserModals() {
    initializeFormModal('createUserForm', 'create_user', 'User created successfully!');
    initializeFormModal('editUserForm', 'edit_user', 'User updated successfully!');
    initializeFormModal('resetPasswordForm', 'reset_password', 'Password reset successfully!', (data) => {
        showToast(`New password: ${data.new_password}`, 'info', 10000);
    });
    initializePasswordGenerators();
}

function initializePasswordGenerators() {
    const setupGenerator = (btnId, inputId) => {
        const btn = document.getElementById(btnId);
        if (btn) {
            btn.addEventListener('click', () => {
                const password = generateRandomPassword();
                document.getElementById(inputId).value = password;
                showToast('Password generated!', 'info');
        });
        }
    };
    setupGenerator('generatePassword', 'userPassword');
    setupGenerator('generateNewPassword', 'newPassword');

    // Toggle visibility for Create User password field
    const toggleUserPasswordBtn = document.getElementById('toggleUserPasswordVisibility');
    if (toggleUserPasswordBtn) {
        toggleUserPasswordBtn.addEventListener('click', function() {
            const passwordField = document.getElementById('userPassword');
            const icon = this.querySelector('i');
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.className = 'fas fa-eye-slash';
        } else {
                passwordField.type = 'password';
                icon.className = 'fas fa-eye';
        }
        });
    }

    // Toggle visibility for Reset Password field
    const toggleBtn = document.getElementById('togglePasswordVisibility');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            const passwordField = document.getElementById('newPassword');
            const icon = this.querySelector('i');
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.className = 'fas fa-eye-slash';
        } else {
                passwordField.type = 'password';
                icon.className = 'fas fa-eye';
        }
        });
    }
}

function generateRandomPassword(length = 12) {
    const charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    let password = '';
    for (let i = 0; i < length; i++) {
        password += charset.charAt(Math.floor(Math.random() * charset.length));
    }
    return password;
}

// User management functions continue here...

// AI Prompt Generator Functions
function showLoading() {
    document.getElementById('loadingOverlay').style.display = 'flex';
}

function hideLoading() {
    document.getElementById('loadingOverlay').style.display = 'none';
}

function generatePrompt() {
    const description = document.getElementById('promptDescription').value.trim();
    const category = document.getElementById('promptCategory').value;
    const style = document.getElementById('promptStyle').value;
    
    if (!description) {
        alert('Please enter a description first.');
        return;
    }
    
    showLoading();
    
    fetch('index.php?page=api', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'ai_generate_prompt',
            description: description,
            category: category,
            style: style
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('generatedPromptText').value = data.prompt;
            document.getElementById('generatedContent').style.display = 'block';
            enableActionButtons();
            showToast('Prompt generated successfully!', 'success');
        } else {
            showToast('Error generating prompt: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showToast('Network error occurred.', 'error');
        console.error('Error:', error);
    })
    .finally(() => {
        hideLoading();
    });
}

function generateTitle() {
    const promptText = document.getElementById('generatedPromptText').value.trim();
    const description = document.getElementById('promptDescription').value.trim();
    
    if (!promptText && !description) {
        alert('Please generate a prompt first or provide a description.');
        return;
    }
    
    showLoading();
    
    fetch('index.php?page=api', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'ai_generate_title',
            prompt: promptText || description
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('generatedTitle').value = data.title;
            showToast('Title generated successfully!', 'success');
        } else {
            showToast('Error generating title: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showToast('Network error occurred.', 'error');
        console.error('Error:', error);
    })
    .finally(() => {
        hideLoading();
    });
}

function generateTags() {
    const promptText = document.getElementById('generatedPromptText').value.trim();
    const title = document.getElementById('generatedTitle').value.trim();
    
    if (!promptText && !title) {
        alert('Please generate a prompt or title first.');
        return;
    }
    
    showLoading();
    
    fetch('index.php?page=api', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'ai_generate_tags',
            prompt: promptText || title,
            max_tags: 8
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('generatedTags').value = data.tags.join(', ');
            showToast('Tags generated successfully!', 'success');
        } else {
            showToast('Error generating tags: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showToast('Network error occurred.', 'error');
        console.error('Error:', error);
    })
    .finally(() => {
        hideLoading();
    });
}

function enhancePrompt(enhancementType) {
    const promptText = document.getElementById('generatedPromptText').value.trim();
    
    if (!promptText) {
        alert('Please generate a prompt first.');
        return;
    }
    
    showLoading();
    
    fetch('index.php?page=api', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'ai_enhance_prompt',
            prompt: promptText,
            enhancement_type: enhancementType
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('generatedPromptText').value = data.enhanced_prompt;
            showToast(`Prompt enhanced for ${enhancementType}!`, 'success');
        } else {
            showToast('Error enhancing prompt: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showToast('Network error occurred.', 'error');
        console.error('Error:', error);
    })
    .finally(() => {
        hideLoading();
    });
}

function enableActionButtons() {
    document.getElementById('generateTitleBtn').disabled = false;
    document.getElementById('generateTagsBtn').disabled = false;
}

function clearAll() {
    document.getElementById('promptDescription').value = '';
    document.getElementById('promptCategory').value = '';
    document.getElementById('promptStyle').value = 'professional';
    document.getElementById('generatedTitle').value = '';
    document.getElementById('generatedPromptText').value = '';
    document.getElementById('generatedTags').value = '';
    document.getElementById('generatedContent').style.display = 'none';
    document.getElementById('generateTitleBtn').disabled = true;
    document.getElementById('generateTagsBtn').disabled = true;
}

function copyToClipboard() {
    const title = document.getElementById('generatedTitle').value;
    const prompt = document.getElementById('generatedPromptText').value;
    const tags = document.getElementById('generatedTags').value;
    
    const textToCopy = `Title: ${title}\n\nPrompt:\n${prompt}\n\nTags: ${tags}`;
    
    navigator.clipboard.writeText(textToCopy).then(() => {
        showToast('Copied to clipboard!', 'success');
    }).catch(() => {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = textToCopy;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showToast('Copied to clipboard!', 'success');
    });
}

function savePrompt() {
    const title = document.getElementById('generatedTitle').value.trim();
    const prompt = document.getElementById('generatedPromptText').value.trim();
    const tags = document.getElementById('generatedTags').value.trim();
    const description = document.getElementById('promptDescription').value.trim();
    
    if (!title || !prompt) {
        alert('Please generate both a title and prompt before saving.');
        return;
    }
    
    showLoading();
    
    fetch('index.php?page=api', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'create_prompt',
            title: title,
            description: description,
            content: prompt,
            tags: tags,
            category_id: ''
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Prompt saved successfully!', 'success');
            // Optionally redirect to prompts page
            setTimeout(() => {
                window.location.href = 'index.php?page=prompts';
        }, 2000);
        } else {
            showToast('Error saving prompt: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showToast('Network error occurred.', 'error');
        console.error('Error:', error);
    })
    .finally(() => {
        hideLoading();
    });
}

function getCategoryId(categoryName) {
    const select = document.getElementById('promptCategory');
    const options = select.options;
    
    for (let i = 0; i < options.length; i++) {
        if (options[i].text === categoryName) {
            return options[i].value;
        }
    }
    return select.value; // Return current selected value as fallback
}

function clearAll() {
    if (confirm('Are you sure you want to clear all fields?')) {
        document.getElementById('promptDescription').value = '';
        document.getElementById('promptCategory').value = '';
        document.getElementById('promptStyle').value = 'professional';
        document.getElementById('targetAudience').value = 'general';
        document.getElementById('generatedTitle').value = '';
        document.getElementById('generatedPromptText').value = '';
        document.getElementById('generatedTags').value = '';
        document.getElementById('generatedContent').style.display = 'none';
        document.getElementById('generateTitleBtn').disabled = true;
        document.getElementById('generateTagsBtn').disabled = true;
    }
}

// Profile Update Validation and Enhancement
function initializeProfileValidation() {
    const profileForm = document.querySelector('form[method="POST"]');
    if (!profileForm || !document.getElementById('first_name')) {
        return; // Not on profile page
    }
    
    // Check if there was a successful update and update form fields
    if (window.profileUpdateSuccess) {
        updateProfileFormFields(window.profileUpdateSuccess);
        clearPasswordFields();
        updateProfileDisplay(window.profileUpdateSuccess);
        // Clean up the global variable
        delete window.profileUpdateSuccess;
    }

    // Add real-time validation
    const firstNameInput = document.getElementById('first_name');
    const lastNameInput = document.getElementById('last_name');
    const emailInput = document.getElementById('email');
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const currentPasswordInput = document.getElementById('current_password');

    // Real-time validation for names
    [firstNameInput, lastNameInput].forEach(input => {
        if (input) {
            input.addEventListener('input', function() {
                validateName(this);
        });
        }
    });

    // Real-time email validation
    if (emailInput) {
        emailInput.addEventListener('input', function() {
            validateEmail(this);
        });
    }

    // Password strength validation
    if (newPasswordInput) {
        newPasswordInput.addEventListener('input', function() {
            validatePasswordStrength(this);
            if (confirmPasswordInput.value) {
                validatePasswordMatch();
        }
        });
    }

    // Password confirmation validation
    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', function() {
            validatePasswordMatch();
        });
    }

    // Show/hide current password requirement
    if (newPasswordInput && currentPasswordInput) {
        newPasswordInput.addEventListener('input', function() {
            const currentPasswordGroup = currentPasswordInput.closest('.mb-3');
            if (this.value.length > 0) {
                currentPasswordGroup.style.display = 'block';
                currentPasswordInput.required = true;
        } else {
                currentPasswordGroup.style.display = 'none';
                currentPasswordInput.required = false;
                currentPasswordInput.value = '';
                removeValidationFeedback(currentPasswordInput);
        }
        });
    }

    // Form submission validation
    profileForm.addEventListener('submit', function(e) {
        if (!validateProfileForm()) {
            e.preventDefault();
            return;
        }
        
        // Add loading state to submit button
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Updating...';
        submitBtn.disabled = true;
        
        // Store form data for potential updates
        const formData = new FormData(this);
        const updatedData = {
            first_name: formData.get('first_name'),
            last_name: formData.get('last_name'),
            email: formData.get('email')
        };
        
        // Set a timeout to re-enable the button and update form if successful
        setTimeout(() => {
            // Check if there's a success message (indicating successful update)
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                // Update form fields with the new values
                updateProfileFormFields(updatedData);
                
                // Clear password fields
                clearPasswordFields();
                
                // Update profile display in sidebar if exists
                updateProfileDisplay(updatedData);
        }
            
            // Reset button
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }, 1000);
    });

    function validateName(input) {
        const value = input.value.trim();
        const isValid = value.length >= 2 && /^[a-zA-Z\s-']+$/.test(value);
        
        if (value.length === 0) {
            showValidationFeedback(input, '', 'neutral');
        } else if (isValid) {
            showValidationFeedback(input, 'Looks good!', 'valid');
        } else {
            showValidationFeedback(input, 'Name must be at least 2 characters and contain only letters, spaces, hyphens, and apostrophes.', 'invalid');
        }
        
        return isValid;
    }

    function validateEmail(input) {
        const value = input.value.trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        const isValid = emailRegex.test(value);
        
        if (value.length === 0) {
            showValidationFeedback(input, '', 'neutral');
        } else if (isValid) {
            showValidationFeedback(input, 'Valid email address.', 'valid');
        } else {
            showValidationFeedback(input, 'Please enter a valid email address.', 'invalid');
        }
        
        return isValid;
    }

    function validatePasswordStrength(input) {
        const value = input.value;
        const minLength = 8;
        const hasUpper = /[A-Z]/.test(value);
        const hasLower = /[a-z]/.test(value);
        const hasNumber = /\d/.test(value);
        const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(value);
        
        let strength = 0;
        let feedback = [];
        
        if (value.length === 0) {
            showValidationFeedback(input, '', 'neutral');
            return true; // Password is optional
        }
        
        if (value.length >= minLength) strength++;
        else feedback.push(`At least ${minLength} characters`);
        
        if (hasUpper) strength++;
        else feedback.push('One uppercase letter');
        
        if (hasLower) strength++;
        else feedback.push('One lowercase letter');
        
        if (hasNumber) strength++;
        else feedback.push('One number');
        
        if (hasSpecial) strength++;
        else feedback.push('One special character');
        
        const strengthLevels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
        const strengthColors = ['danger', 'warning', 'info', 'primary', 'success'];
        
        let message;
        let type;
        
        if (strength >= 4) {
            message = `Password strength: ${strengthLevels[strength]} ðŸ’ª`;
            type = 'valid';
        } else {
            message = `Password needs: ${feedback.join(', ')}`;
            type = 'invalid';
        }
        
        showValidationFeedback(input, message, type);
        return strength >= 3; // Require at least "Fair" strength
    }

    function validatePasswordMatch() {
        if (!newPasswordInput || !confirmPasswordInput) return true;
        
        const newPassword = newPasswordInput.value;
        const confirmPassword = confirmPasswordInput.value;
        
        if (confirmPassword.length === 0) {
            showValidationFeedback(confirmPasswordInput, '', 'neutral');
            return true;
        }
        
        if (newPassword === confirmPassword) {
            showValidationFeedback(confirmPasswordInput, 'Passwords match! âœ“', 'valid');
            return true;
        } else {
            showValidationFeedback(confirmPasswordInput, 'Passwords do not match.', 'invalid');
            return false;
        }
    }

    function validateProfileForm() {
        let isValid = true;
        
        // Validate required fields
        if (firstNameInput && !validateName(firstNameInput)) {
            isValid = false;
        }
        
        if (lastNameInput && !validateName(lastNameInput)) {
            isValid = false;
        }
        
        if (emailInput && !validateEmail(emailInput)) {
            isValid = false;
        }
        
        // Validate password if provided
        if (newPasswordInput && newPasswordInput.value) {
            if (!validatePasswordStrength(newPasswordInput)) {
                isValid = false;
        }
            
            if (!validatePasswordMatch()) {
                isValid = false;
        }
            
            if (currentPasswordInput && !currentPasswordInput.value) {
                showValidationFeedback(currentPasswordInput, 'Current password is required when changing password.', 'invalid');
                isValid = false;
        }
        }
        
        return isValid;
    }

    function showValidationFeedback(input, message, type) {
        removeValidationFeedback(input);
        
        if (type === 'neutral' || !message) {
            input.classList.remove('is-valid', 'is-invalid');
            return;
        }
        
        const feedbackDiv = document.createElement('div');
        feedbackDiv.className = type === 'valid' ? 'valid-feedback' : 'invalid-feedback';
        feedbackDiv.textContent = message;
        
        input.classList.remove('is-valid', 'is-invalid');
        input.classList.add(type === 'valid' ? 'is-valid' : 'is-invalid');
        
        input.parentNode.appendChild(feedbackDiv);
    }

    function removeValidationFeedback(input) {
        const existingFeedback = input.parentNode.querySelectorAll('.valid-feedback, .invalid-feedback');
        existingFeedback.forEach(feedback => feedback.remove());
    }
    
    function updateProfileFormFields(data) {
        // Update form fields with new values
        if (firstNameInput) firstNameInput.value = data.first_name;
        if (lastNameInput) lastNameInput.value = data.last_name;
        if (emailInput) emailInput.value = data.email;
        
        // Clear any validation states on updated fields
        [firstNameInput, lastNameInput, emailInput].forEach(input => {
            if (input) {
                input.classList.remove('is-valid', 'is-invalid');
                removeValidationFeedback(input);
        }
        });
    }
    
    function clearPasswordFields() {
        // Clear all password fields
        if (currentPasswordInput) {
            currentPasswordInput.value = '';
            currentPasswordInput.classList.remove('is-valid', 'is-invalid');
            removeValidationFeedback(currentPasswordInput);
        }
        if (newPasswordInput) {
            newPasswordInput.value = '';
            newPasswordInput.classList.remove('is-valid', 'is-invalid');
            removeValidationFeedback(newPasswordInput);
        }
        if (confirmPasswordInput) {
            confirmPasswordInput.value = '';
            confirmPasswordInput.classList.remove('is-valid', 'is-invalid');
            removeValidationFeedback(confirmPasswordInput);
        }
        
        // Hide current password field if new password is cleared
        if (currentPasswordInput) {
            const currentPasswordGroup = currentPasswordInput.closest('.mb-3');
            if (currentPasswordGroup) {
                currentPasswordGroup.style.display = 'none';
        }
            currentPasswordInput.required = false;
        }
    }
    
    function updateProfileDisplay(data) {
        // Update profile name display in the statistics card
        const profileNameDisplay = document.querySelector('.card-body h5');
        if (profileNameDisplay && data.first_name && data.last_name) {
            profileNameDisplay.textContent = `${data.first_name} ${data.last_name}`;
        }
        
        // Update any other profile displays on the page
        const fullNameElements = document.querySelectorAll('[data-profile-fullname]');
        fullNameElements.forEach(element => {
            element.textContent = `${data.first_name} ${data.last_name}`;
        });
        
        // Update dropdown menu profile name if it exists
        const dropdownProfileName = document.querySelector('.dropdown-menu .dropdown-header');
        if (dropdownProfileName) {
            dropdownProfileName.textContent = `${data.first_name} ${data.last_name}`;
        }
    }
}

function initializeNoteDragAndDrop() {
    const notesContainer = document.querySelector('.row'); // Assuming notes are in a div with class="row"
    if (notesContainer && document.querySelector('.note-card')) { // Only run on pages with notes
        new Sortable(notesContainer, {
            animation: 150,
            filter: '.note-pinned', // This is the key: prevents pinned notes from being dragged
            ghostClass: 'note-ghost', // Class for the drop placeholder
        });
    }
}


