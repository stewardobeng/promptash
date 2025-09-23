<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo htmlspecialchars(isset($appSettings) && $appSettings !== null ? $appSettings->getAppName() : 'Promptash'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Crect width='16' height='16' rx='2' ry='2' fill='%230066ab'/%3E%3Ctext x='8' y='11' font-family='Arial' font-size='10' text-anchor='middle' fill='white'%3EP%3C/text%3E%3C/svg%3E">
    <link href="assets/css/style.css?v=<?php echo time() . '_no_theme'; ?>" rel="stylesheet">
    

    <script>
        localStorage.removeItem('theme');
        // Remove any existing theme toggle buttons on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Remove theme toggle buttons by various selectors
            const themeToggles = document.querySelectorAll('#themeToggle, .theme-toggle, [title*="Toggle"], [title*="Dark Mode"], [title*="Light Mode"]');
            themeToggles.forEach(toggle => toggle.remove());
            
            // Also remove any buttons with moon or sun icons in navbar
            const navButtons = document.querySelectorAll('.top-navbar button');
            navButtons.forEach(btn => {
                const icon = btn.querySelector('i');
                if (icon && (icon.classList.contains('fa-moon') || icon.classList.contains('fa-sun'))) {
                    btn.remove();
                }
            });
            
            // Use MutationObserver to catch dynamically added theme toggles
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) { // Element node
                            // Check if the added node is a theme toggle
                            if (node.id === 'themeToggle' || node.classList?.contains('theme-toggle')) {
                                node.remove();
                            }
                            // Check if it contains theme toggle children
                            const childToggles = node.querySelectorAll?.('#themeToggle, .theme-toggle');
                            childToggles?.forEach(toggle => toggle.remove());
                        }
                    });
                });
            });
            observer.observe(document.body, { childList: true, subtree: true });
        });
    </script>
<body>
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h4><i class="fas fa-magic"></i> <span><?php echo htmlspecialchars(isset($appSettings) && $appSettings !== null ? $appSettings->getAppName() : 'Promptash'); ?></span></h4>
        </div>
        
        <div class="sidebar-menu">
            <a href="index.php?page=dashboard" class="<?php echo $page === 'dashboard' ? 'active' : ''; ?>" data-tooltip="Dashboard">
                <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
            </a>
            
            <div class="menu-divider"></div>

            <div class="menu-section">
                <div class="menu-section-header" data-menu="ai-tools-menu">
                    <span><i class="fas fa-robot"></i> AI Tools</span>
                    <i class="fas fa-chevron-right toggle-icon" id="ai-tools-menu-icon"></i>
                </div>
                <div class="submenu" id="ai-tools-menu">
                    <a href="index.php?page=prompt_generator" class="<?php echo $page === 'prompt_generator' ? 'active' : ''; ?>" data-tooltip="AI Prompt Generator">
                        <i class="fas fa-magic"></i> <span>Prompt Generator</span>
                    </a>
                </div>
            </div>

            <div class="menu-section">
                <div class="menu-section-header" data-menu="my-content-menu">
                    <span><i class="fas fa-folder-user"></i> My Content</span>
                    <i class="fas fa-chevron-right toggle-icon" id="my-content-menu-icon"></i>
                </div>
                <div class="submenu" id="my-content-menu">
                    <a href="index.php?page=prompts" class="<?php echo $page === 'prompts' ? 'active' : ''; ?>" data-tooltip="My Prompts">
                        <i class="fas fa-file-alt"></i> <span>My Prompts</span>
                    </a>
                    <a href="index.php?page=bookmarks" class="<?php echo $page === 'bookmarks' ? 'active' : ''; ?>" data-tooltip="My Bookmarks">
                        <i class="fas fa-bookmark"></i> <span>My Bookmarks</span>
                    </a>
                    <a href="index.php?page=notes" class="<?php echo $page === 'notes' ? 'active' : ''; ?>" data-tooltip="My Notes">
                        <i class="fas fa-sticky-note"></i> <span>My Notes</span>
                    </a>
                    <a href="index.php?page=documents" class="<?php echo $page === 'documents' ? 'active' : ''; ?>" data-tooltip="My Documents">
                        <i class="fas fa-folder-open"></i> <span>My Documents</span>
                    </a>
                    <a href="index.php?page=videos" class="<?php echo $page === 'videos' ? 'active' : ''; ?>" data-tooltip="My Videos">
                        <i class="fas fa-video"></i> <span>My Videos</span>
                    </a>
                    <a href="index.php?page=categories" class="<?php echo $page === 'categories' ? 'active' : ''; ?>" data-tooltip="Categories">
                        <i class="fas fa-tags"></i> <span>Categories</span>
                    </a>
                </div>
            </div>

            <div class="menu-section">
                <div class="menu-section-header" data-menu="shared-content-menu">
                    <span><i class="fas fa-users"></i> Shared Content</span>
                    <i class="fas fa-chevron-right toggle-icon" id="shared-content-menu-icon"></i>
                </div>
                <div class="submenu" id="shared-content-menu">
                    <a href="index.php?page=shared_prompts" class="<?php echo $page === 'shared_prompts' ? 'active' : ''; ?>" data-tooltip="Shared Prompts">
                        <i class="fas fa-file-alt"></i> <span>Shared Prompts</span>
                    </a>
                    <a href="index.php?page=shared_bookmarks" class="<?php echo $page === 'shared_bookmarks' ? 'active' : ''; ?>" data-tooltip="Shared Bookmarks">
                        <i class="fas fa-bookmark"></i> <span>Shared Bookmarks</span>
                    </a>
                    <a href="index.php?page=shared_notes" class="<?php echo $page === 'shared_notes' ? 'active' : ''; ?>" data-tooltip="Shared Notes">
                        <i class="fas fa-sticky-note"></i> <span>Shared Notes</span>
                    </a>
                    <a href="index.php?page=shared_documents" class="<?php echo $page === 'shared_documents' ? 'active' : ''; ?>" data-tooltip="Shared Documents">
                        <i class="fas fa-folder-open"></i> <span>Shared Documents</span>
                    </a>
                    <a href="index.php?page=shared_videos" class="<?php echo $page === 'shared_videos' ? 'active' : ''; ?>" data-tooltip="Shared Videos">
                        <i class="fas fa-video"></i> <span>Shared Videos</span>
                    </a>
                    </div>
            </div>
            
            <div class="menu-divider"></div>

            <div class="menu-section">
                <div class="menu-section-header" data-menu="account-menu">
                    <span><i class="fas fa-user-cog"></i> Account & Data</span>
                    <i class="fas fa-chevron-right toggle-icon" id="account-menu-icon"></i>
                </div>
                <div class="submenu" id="account-menu">
                    <a href="index.php?page=profile" class="<?php echo $page === 'profile' ? 'active' : ''; ?>" data-tooltip="Profile">
                        <i class="fas fa-user-circle"></i> <span>Profile</span>
                    </a>
                    <a href="index.php?page=settings" class="<?php echo $page === 'settings' ? 'active' : ''; ?>" data-tooltip="Settings">
                        <i class="fas fa-cog"></i> <span>Settings & Membership</span>
                    </a>
                    <a href="index.php?page=two_factor" class="<?php echo $page === 'two_factor' ? 'active' : ''; ?>" data-tooltip="Two-Factor Auth">
                        <i class="fas fa-shield-alt"></i> <span>Security</span>
                    </a>
                    <a href="index.php?page=notifications" class="<?php echo $page === 'notifications' ? 'active' : ''; ?>" data-tooltip="Notifications">
                        <i class="fas fa-bell"></i> <span>Notifications</span>
                        <?php
                        // Show notification badge in sidebar
                        require_once __DIR__ . '/../../helpers/NotificationService.php';
                        $notificationService = new NotificationService();
                        $unread_count = $notificationService->getUnreadCount($auth->getCurrentUser()['id']);
                        if ($unread_count > 0) {
                            echo '<span class="badge bg-danger rounded-pill ms-auto">' . ($unread_count > 9 ? '9+' : $unread_count) . '</span>';
                        }
                        ?>
                    </a>
                    <a href="index.php?page=backup" class="<?php echo $page === 'backup' ? 'active' : ''; ?>" data-tooltip="Backup & Restore">
                        <i class="fas fa-download"></i> <span>Backup & Restore</span>
                    </a>
                     <?php
                    // Show upgrade link for free users
                    $current_user = $auth->getCurrentUser();
                    if (isset($current_user['current_tier_id'])) {
                        require_once __DIR__ . '/../models/MembershipTier.php';
                        $membershipModel = new MembershipTier();
                        $userTier = $membershipModel->getTierById($current_user['current_tier_id']);
                        if ($userTier && $userTier['name'] === 'free') {
                            echo '<a href="index.php?page=upgrade" class="' . ($page === 'upgrade' ? 'active' : '') . '" data-tooltip="Upgrade to Premium">';
                            echo '<i class="fas fa-crown text-warning"></i> <span>Upgrade to Premium</span>';
                            echo '</a>';
                        }
                    }
                    ?>
                </div>
            </div>
            
            <?php if ($auth->isAdmin()): ?>
            <div class="menu-divider"></div>
            
            <div class="menu-section">
                <div class="menu-section-header" data-menu="admin-menu">
                    <span><i class="fas fa-user-shield"></i> Administration</span>
                    <i class="fas fa-chevron-right toggle-icon" id="admin-menu-icon"></i>
                </div>
                <div class="submenu" id="admin-menu">
                    <a href="index.php?page=admin" class="<?php echo $page === 'admin' ? 'active' : ''; ?>" data-tooltip="Admin Panel">
                        <i class="fas fa-chart-line"></i> <span>Admin Panel</span>
                    </a>
                    <a href="index.php?page=users" class="<?php echo $page === 'users' ? 'active' : ''; ?>" data-tooltip="Manage Users">
                        <i class="fas fa-users-cog"></i> <span>Manage Users</span>
                    </a>
                    <a href="index.php?page=security_logs" class="<?php echo $page === 'security_logs' ? 'active' : ''; ?>" data-tooltip="Security Logs">
                        <i class="fas fa-shield-halved"></i> <span>Security Logs</span>
                    </a>
                    <a href="index.php?page=admin_backup" class="<?php echo $page === 'admin_backup' ? 'active' : ''; ?>" data-tooltip="System Backup">
                        <i class="fas fa-server"></i> <span>System Backup</span>
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="menu-divider"></div>
            
            <a href="index.php?page=logout" data-tooltip="Logout">
                <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
            </a>
        </div>
    </div>
    
    <div class="main-content" id="main-content">
        <div class="top-navbar d-flex justify-content-between align-items-center">
            <div>
                <button class="btn btn-outline-secondary d-md-none" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <h5 class="mb-0 d-none d-md-inline"><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></h5>
            </div>
            <div class="d-flex align-items-center">
                <?php
                // Get user membership info for nav display
                $current_user = $auth->getCurrentUser();
                if (isset($current_user['current_tier_id'])) {
                    require_once __DIR__ . '/../models/MembershipTier.php';
                    $membershipModel = new MembershipTier();
                    $userTier = $membershipModel->getTierById($current_user['current_tier_id']);
                    if ($userTier) {
                        if ($userTier['name'] === 'premium') {
                            echo '<span class="badge bg-warning text-dark me-3"><i class="fas fa-crown"></i> Premium</span>';
                        } else {
                            echo '<span class="badge bg-secondary me-3 d-none d-md-inline-block"><i class="fas fa-user"></i> Free</span>';
                        }
                    }
                }
                ?>
                <span class="me-3 d-none d-md-inline-block">Welcome, <?php echo htmlspecialchars($auth->getCurrentUser()['first_name']); ?>!</span>
                
                <div class="dropdown me-3">
                    <button class="btn btn-outline-secondary position-relative" type="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-bell"></i>
                        <?php
                        // Show initial notification count on page load
                        require_once __DIR__ . '/../../helpers/NotificationService.php';
                        $notificationService = new NotificationService();
                        $initial_unread_count = $notificationService->getUnreadCount($auth->getCurrentUser()['id']);
                        ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger <?php echo $initial_unread_count > 0 ? '' : 'd-none'; ?>" id="notificationBadge">
                            <?php echo $initial_unread_count > 9 ? '9+' : $initial_unread_count; ?>
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" style="width: 350px; max-height: 400px; overflow-y: auto;" id="notificationList">
                        <li><div class="dropdown-header d-flex justify-content-between align-items-center">
                            <span>Notifications</span>
                            <button class="btn btn-sm btn-outline-primary" onclick="markAllNotificationsRead()" id="markAllReadBtn">
                                <i class="fas fa-check-double"></i> Mark All Read
                            </button>
                        </div></li>
                        <li><hr class="dropdown-divider"></li>
                        <li id="notificationLoading">
                            <div class="text-center py-3">
                                <i class="fas fa-spinner fa-spin"></i> Loading...
                            </div>
                        </li>
                        <li id="noNotifications" class="d-none">
                            <div class="text-center py-3 text-muted">
                                <i class="fas fa-bell-slash"></i><br>
                                No notifications
                            </div>
                        </li>
                    </ul>
                </div>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user"></i>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="index.php?page=profile"><i class="fas fa-user"></i> Profile</a></li>
                        <li><a class="dropdown-item" href="index.php?page=settings"><i class="fas fa-cog"></i> Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="index.php?page=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="content-area">
            <?php echo $content; ?>
        </div>
    </div>
    
    <?php include 'modals.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <script src="assets/js/app.js?v=<?php echo time() . '_no_theme'; ?>"></script>
    <?php if ($auth->isAdmin()): ?>
    <script src="assets/js/user-management.js"></script>
    <?php endif; ?>

</body>
</html>
