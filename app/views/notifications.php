<?php
$page_title = 'Notifications';
$user = $auth->getCurrentUser();

require_once __DIR__ . '/../../helpers/NotificationService.php';
$notificationService = new NotificationService();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $response = ['success' => false, 'message' => ''];
        
        switch ($_POST['action']) {
            case 'mark_read':
                $notification_id = (int)($_POST['notification_id'] ?? 0);
                if ($notification_id) {
                    $result = $notificationService->markAsRead($notification_id, $user['id']);
                    $response = [
                        'success' => $result,
                        'message' => $result ? 'Notification marked as read' : 'Failed to mark as read'
                    ];
                }
                break;
                
            case 'mark_all_read':
                $count = $notificationService->markAllAsRead($user['id']);
                $response = [
                    'success' => true,
                    'message' => "Marked {$count} notifications as read"
                ];
                break;
                
            case 'delete':
                $notification_id = (int)($_POST['notification_id'] ?? 0);
                if ($notification_id) {
                    $result = $notificationService->deleteNotification($notification_id, $user['id']);
                    $response = [
                        'success' => $result,
                        'message' => $result ? 'Notification deleted' : 'Failed to delete notification'
                    ];
                }
                break;
        }
        
        // Return JSON response for AJAX requests
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }
        
        // Redirect for regular form submissions
        $message = urlencode($response['message']);
        $status = $response['success'] ? 'success' : 'error';
        header("Location: index.php?page=notifications&{$status}={$message}");
        exit();
    }
}

// Get pagination parameters
$page_num = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$limit = 20;
$offset = ($page_num - 1) * $limit;

// Get filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$unread_only = ($filter === 'unread');

// Get notifications
$notifications = $notificationService->getUserNotifications($user['id'], $limit, $offset, $unread_only);
$unread_count = $notificationService->getUnreadCount($user['id']);

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-bell"></i> Notifications
        <?php if ($unread_count > 0): ?>
            <span class="badge bg-danger ms-2"><?php echo $unread_count; ?> unread</span>
        <?php endif; ?>
    </h1>
    
    <div class="d-flex gap-2">
        <?php if ($unread_count > 0): ?>
            <button type="button" class="btn btn-outline-primary" onclick="markAllAsRead()">
                <i class="fas fa-check-double"></i> Mark All Read
            </button>
        <?php endif; ?>
        <button type="button" class="btn btn-outline-secondary" onclick="window.location.reload()">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
    </div>
</div>

<!-- Display success/error messages -->
<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars(urldecode($_GET['success'])); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars(urldecode($_GET['error'])); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Filter Tabs -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <ul class="nav nav-pills">
                <li class="nav-item">
                    <a class="nav-link <?php echo $filter === '' ? 'active' : ''; ?>" 
                       href="index.php?page=notifications">
                        All Notifications
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $filter === 'unread' ? 'active' : ''; ?>" 
                       href="index.php?page=notifications&filter=unread">
                        Unread <?php echo $unread_count > 0 ? "({$unread_count})" : ''; ?>
                    </a>
                </li>
            </ul>
            
            <div class="text-muted small">
                <i class="fas fa-info-circle"></i> 
                Notifications are kept for 30 days
            </div>
        </div>
    </div>
</div>

<!-- Notifications List -->
<?php if (empty($notifications)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-bell-slash fa-4x text-muted mb-4"></i>
            <h4>No notifications found</h4>
            <p class="text-muted">
                <?php if ($filter === 'unread'): ?>
                    You have no unread notifications. Great job staying on top of things!
                <?php else: ?>
                    You don't have any notifications yet. When you receive notifications about your account activity, they'll appear here.
                <?php endif; ?>
            </p>
        </div>
    </div>
<?php else: ?>
    <div class="notifications-list">
        <?php foreach ($notifications as $notification): ?>
            <div class="card mb-3 notification-card <?php echo $notification['read_at'] ? '' : 'unread-notification'; ?>" 
                 id="notification-<?php echo $notification['id']; ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center mb-2">
                                <h5 class="mb-0 <?php echo $notification['read_at'] ? '' : 'fw-bold'; ?>">
                                    <?php echo htmlspecialchars($notification['subject']); ?>
                                </h5>
                                <?php if (!$notification['read_at']): ?>
                                    <span class="badge bg-primary ms-2">New</span>
                                <?php endif; ?>
                                
                                <!-- Notification Type Icon -->
                                <?php
                                $type_icons = [
                                    'welcome' => 'fas fa-hand-wave text-success',
                                    'subscription_confirmation' => 'fas fa-crown text-warning',
                                    'subscription_expiry' => 'fas fa-exclamation-triangle text-warning',
                                    'payment_reminder' => 'fas fa-credit-card text-info',
                                    'prompt_shared' => 'fas fa-share-alt text-primary',
                                    'usage_warning' => 'fas fa-chart-line text-warning',
                                    'limit_reached' => 'fas fa-ban text-danger'
                                ];
                                $icon_class = $type_icons[$notification['type']] ?? 'fas fa-bell text-secondary';
                                ?>
                                <i class="<?php echo $icon_class; ?> ms-2"></i>
                            </div>
                            
                            <p class="mb-2 text-muted">
                                <?php echo htmlspecialchars($notification['message']); ?>
                            </p>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="fas fa-clock"></i>
                                    <?php 
                                    $created_time = new DateTime($notification['created_at']);
                                    $now = new DateTime();
                                    $diff = $now->diff($created_time);
                                    
                                    if ($diff->days > 0) {
                                        echo $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ' ago';
                                    } elseif ($diff->h > 0) {
                                        echo $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
                                    } elseif ($diff->i > 0) {
                                        echo $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
                                    } else {
                                        echo 'Just now';
                                    }
                                    ?>
                                </small>
                                
                                <?php if ($notification['read_at']): ?>
                                    <small class="text-muted">
                                        <i class="fas fa-check"></i> 
                                        Read <?php echo date('M j, Y g:i A', strtotime($notification['read_at'])); ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="ms-3">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                        type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <?php if (!$notification['read_at']): ?>
                                        <li>
                                            <button class="dropdown-item" 
                                                    onclick="markAsRead(<?php echo $notification['id']; ?>)">
                                                <i class="fas fa-check"></i> Mark as Read
                                            </button>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php if ($notification['action_url']): ?>
                                        <li>
                                            <a class="dropdown-item" 
                                               href="<?php echo htmlspecialchars($notification['action_url']); ?>">
                                                <i class="fas fa-external-link-alt"></i> View Details
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <button class="dropdown-item text-danger" 
                                                onclick="deleteNotification(<?php echo $notification['id']; ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Pagination -->
    <?php if (count($notifications) >= $limit): ?>
        <nav aria-label="Notifications pagination">
            <ul class="pagination justify-content-center">
                <?php if ($page_num > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="index.php?page=notifications&filter=<?php echo $filter; ?>&p=<?php echo $page_num - 1; ?>">
                            Previous
                        </a>
                    </li>
                <?php endif; ?>
                
                <li class="page-item active">
                    <span class="page-link">Page <?php echo $page_num; ?></span>
                </li>
                
                <li class="page-item">
                    <a class="page-link" href="index.php?page=notifications&filter=<?php echo $filter; ?>&p=<?php echo $page_num + 1; ?>">
                        Next
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<style>
.unread-notification {
    border-left: 4px solid #0d6efd;
    background: rgba(13, 110, 253, 0.02);
}

.notification-card {
    transition: all 0.3s ease;
}

.notification-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.notifications-list .card {
    border-radius: 10px;
}
</style>

<script>
// Mark notification as read
function markAsRead(notificationId) {
    fetch('index.php?page=api&action=mark_notification_read', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `notification_id=${notificationId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove unread styling
            const notificationCard = document.getElementById(`notification-${notificationId}`);
            notificationCard.classList.remove('unread-notification');
            
            // Update badge/subject
            const badge = notificationCard.querySelector('.badge.bg-primary');
            if (badge) badge.remove();
            
            const subject = notificationCard.querySelector('h5');
            if (subject) subject.classList.remove('fw-bold');
            
            showToast('Notification marked as read', 'success');
            
            // Refresh page to update counts
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Failed to mark as read', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('A network error occurred', 'error');
    });
}

// Mark all notifications as read
function markAllAsRead() {
    if (!confirm('Mark all notifications as read?')) {
        return;
    }
    
    fetch('index.php?page=api&action=mark_all_notifications_read', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('All notifications marked as read', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Failed to mark all as read', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('A network error occurred', 'error');
    });
}

// Delete notification
function deleteNotification(notificationId) {
    if (!confirm('Delete this notification?')) {
        return;
    }
    
    fetch('index.php?page=api&action=delete_notification', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `notification_id=${notificationId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove the notification card
            const notificationCard = document.getElementById(`notification-${notificationId}`);
            notificationCard.style.transition = 'opacity 0.3s ease';
            notificationCard.style.opacity = '0';
            
            setTimeout(() => {
                notificationCard.remove();
                showToast('Notification deleted', 'success');
                
                // Check if no notifications left
                const remainingCards = document.querySelectorAll('.notification-card');
                if (remainingCards.length === 0) {
                    location.reload();
                }
            }, 300);
        } else {
            showToast(data.message || 'Failed to delete notification', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('A network error occurred', 'error');
    });
}

// Toast notification function
function showToast(message, type = 'info') {
    const toastContainer = document.getElementById('toast-container') || createToastContainer();
    
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} border-0`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
    const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
    bsToast.show();
    
    toast.addEventListener('hidden.bs.toast', () => {
        toast.remove();
    });
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
    return container;
}
</script>

<?php
$content = ob_get_clean();
include 'layout.php';
?>