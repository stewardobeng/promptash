<?php
// Include configuration
require_once 'config/config.php';
require_once 'helpers/Auth.php';
require_once 'helpers/Security.php';
require_once 'helpers/TOTP.php';
require_once 'app/models/User.php';
require_once 'app/models/Prompt.php';
require_once 'app/models/Category.php';
require_once 'app/models/Bookmark.php';
require_once 'app/models/Note.php';
require_once 'app/models/Document.php';
require_once 'app/models/Video.php';


// Initialize authentication
$auth = new Auth();
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// --- MODIFICATION START: Improved Maintenance Mode Logic ---
if (isset($appSettings) && $appSettings !== null) {
    // Check for maintenance mode
    if ($appSettings->isMaintenanceMode()) {
        // Allow access for already logged-in admins
        if (!$auth->isAdmin()) {
            // For all other users (non-admins or not logged in),
            // only allow access to the login, register, and API pages. Redirect everything else to the login page.
            $allowed_pages_in_maintenance = ['login', 'register', 'api'];
            if (!in_array($page, $allowed_pages_in_maintenance)) {
                // Redirect to the login page with a maintenance notice
                header('Location: index.php?page=login');
                exit();
            }
        }
    }
    
    // Check if registration is allowed (this check remains the same)
    if ($page === 'register' && !$appSettings->isRegistrationAllowed()) {
        header('Location: index.php?page=login&registration=disabled');
        exit();
    }
}
// --- MODIFICATION END ---

// Handle logout
if ($page === 'logout') {
    $auth->logout();
    header('Location: index.php?page=login');
    exit();
}

// Public pages (no authentication required)
$public_pages = ['login', 'register', 'api'];

// Check authentication for protected pages
if (!in_array($page, $public_pages) && !$auth->isLoggedIn()) {
    header('Location: index.php?page=login');
    exit();
}

// Admin-only pages
$admin_pages = ['admin', 'users', 'admin_backup', 'security_logs'];
if (in_array($page, $admin_pages)) {
    $auth->requireAdmin();
}

// Include the appropriate page
switch ($page) {
    case 'login':
        include 'app/views/login.php';
        break;
    case 'register':
        include 'app/views/register.php';
        break;
    case 'dashboard':
        include 'app/views/dashboard.php';
        break;
    case 'prompts':
        include 'app/views/prompts.php';
        break;
    case 'bookmarks':
        include 'app/views/bookmarks.php';
        break;
    case 'notes':
        include 'app/views/notes.php';
        break;
    case 'note': // This is the new case for viewing a single note
        include 'app/views/note.php';
        break;
    case 'documents':
        include 'app/views/documents.php';
        break;
    case 'videos':
        include 'app/views/videos.php';
        break;
    case 'prompt_generator':
        require_once 'app/models/UserSettings.php';
        include 'app/views/prompt_generator.php';
        break;
    case 'shared_prompts':
        include 'app/views/shared_prompts.php';
        break;
    case 'shared_bookmarks':
        include 'app/views/shared_bookmarks.php';
        break;
    case 'shared_notes':
        include 'app/views/shared_notes.php';
        break;
    case 'shared_documents':
        include 'app/views/shared_documents.php';
        break;
    case 'shared_videos':
        include 'app/views/shared_videos.php';
        break;
    case 'prompt':
        include 'app/views/prompt.php';
        break;
    case 'categories':
        include 'app/views/categories.php';
        break;
    case 'profile':
        include 'app/views/profile.php';
        break;
    case 'admin':
        include 'app/views/admin.php';
        break;
    case 'users':
        include 'app/views/users.php';
        break;
    case 'backup':
        include 'app/views/backup.php';
        break;
    case 'admin_backup':
        include 'app/views/admin_backup.php';
        break;
    case 'security_logs':
        include 'app/views/security_logs.php';
        break;
    case 'two_factor':
        include 'app/views/two_factor.php';
        break;
    case 'settings':
        require_once 'helpers/AIHelper.php';
        require_once 'app/models/UserSettings.php';
        include 'app/views/settings.php';
        break;
    case 'notifications':
        include 'app/views/notifications.php';
        break;
    case 'upgrade':
        include 'app/views/upgrade.php';
        break;
    case 'payment_callback':
        include 'app/views/payment_callback.php';
        break;
    case 'api':
        include 'app/views/api.php';
        break;
    default:
        include 'app/views/dashboard.php';
        break;
}
?>
