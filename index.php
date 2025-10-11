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
require_once 'app/models/MembershipTier.php';


// Initialize authentication
$auth = new Auth();
$page = $_GET['page'] ?? ($auth->isLoggedIn() ? 'dashboard' : 'landing');

// --- MODIFICATION START: Improved Maintenance Mode Logic ---
if (isset($appSettings) && $appSettings !== null) {
    // Check for maintenance mode
    if ($appSettings->isMaintenanceMode()) {
        // Allow access for already logged-in admins, even if they are in 'login as' mode
        if (!$auth->isOriginallyAdmin()) {
            // For all other users, only allow access to essential pages during maintenance
            $allowed_pages_in_maintenance = ['login', 'register', 'landing', 'checkout', 'api', 'revert_login_as'];
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
$public_pages = ['landing', 'checkout', 'login', 'register', 'api', 'login_as'];

// Check authentication for protected pages
if (!in_array($page, $public_pages) && !$auth->isLoggedIn()) {
    header('Location: index.php?page=login');
    exit();
}

// Enforce membership or trial status for logged-in users
$auth->enforceMembershipAccess($page);

// Admin-only pages
$admin_pages = ['admin', 'users', 'admin_backup', 'security_logs'];
if (in_array($page, $admin_pages)) {
    // Use isOriginallyAdmin to protect admin pages from being accessed by non-admins
    if (!$auth->isOriginallyAdmin()) {
        header('Location: index.php?page=dashboard');
        exit();
    }
}

// Include the appropriate page
switch ($page) {
    case 'login':
        include 'app/views/login.php';
        break;
    case 'login_as':
        // Only an admin who is not already in "login as" mode can initiate this
        if ($auth->isAdmin() && !$auth->isLoginAs() && isset($_GET['token'])) {
            $userModel = new User();
            $user = $userModel->validateLoginToken($_GET['token']);
            if ($user) {
                $auth->loginAs($user['id']);
                header('Location: index.php?page=dashboard');
                exit();
            }
        }
        // If token is invalid or user is not admin, redirect to login
        header('Location: index.php?page=login');
        exit();
        break;
    // --- MODIFICATION START: Added revert login case ---
    case 'revert_login_as':
        // Check if the user is in a "login as" session
        if ($auth->isLoginAs()) {
            // Revert to the original admin session
            $auth->revertLoginAs();
        }
        // Redirect to the admin user management page after reverting
        header('Location: index.php?page=users');
        exit();
        break;
    // --- MODIFICATION END ---
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
    case 'checkout':
        require_once 'helpers/CheckoutHelper.php';
        $membershipModel = new MembershipTier();
        $publicTiers = $membershipModel->getAllTiers(true);
        include 'app/views/checkout.php';
        break;
    case 'landing':
        $membershipModel = new MembershipTier();
        $publicTiers = $membershipModel->getAllTiers(true);
        include 'app/views/landing.php';
        break;
    default:
        include 'app/views/dashboard.php';
        break;
}
?>
