<?php
$page_title = 'Security Logs';

// Admin-only page
if (!$auth->isAdmin()) {
    header('Location: index.php?page=dashboard');
    exit();
}

// Read security logs from error log
$securityLogs = [];
$logFile = ini_get('error_log');

// Try different possible log file locations
if (empty($logFile) || !file_exists($logFile)) {
    $possiblePaths = [
        '/tmp/php_errors.log',
        '../logs/error.log',
        'logs/error.log',
        '/var/log/php_errors.log',
        dirname($_SERVER['DOCUMENT_ROOT']) . '/logs/error.log'
    ];
    
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $logFile = $path;
            break;
        }
    }
}

$logFileExists = false;
if ($logFile && file_exists($logFile)) {
    $logFileExists = true;
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $lines = array_reverse($lines); // Most recent first
    
    foreach ($lines as $line) {
        if (strpos($line, 'SECURITY:') !== false) {
            $securityLogs[] = $line;
        }
    }
    
    // Limit to last 100 entries
    $securityLogs = array_slice($securityLogs, 0, 100);
}

ob_start();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="fas fa-shield-alt"></i> Security Logs</h1>
                <div>
                    <button class="btn btn-outline-secondary" onclick="location.reload()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Recent Security Events</h5>
                    <small class="text-muted">Last 100 security-related events</small>
                </div>
                <div class="card-body">
                    <?php if (!$logFileExists): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <strong>Log File Not Found</strong><br>
                            No error log file could be located. Security events will appear here once the application starts logging.
                            <br><small>Searched locations: error_log setting, /tmp/php_errors.log, logs/error.log</small>
                        </div>
                    <?php elseif (empty($securityLogs)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            <strong>No Security Events Yet</strong><br>
                            Security events will appear here as users interact with the application (login attempts, registrations, etc.).
                            <br><small>Log file found: <?php echo htmlspecialchars($logFile); ?></small>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>Event Type</th>
                                        <th>IP Address</th>
                                        <th>Details</th>
                                        <th>Severity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($securityLogs as $log): ?>
                                        <?php
                                        // Parse log entry
                                        $logData = null;
                                        if (preg_match('/SECURITY: (.+)$/', $log, $matches)) {
                                            $logData = json_decode($matches[1], true);
                                        }
                                        
                                        if (!$logData) continue;
                                        
                                        // Determine severity and icon
                                        $severity = 'info';
                                        $icon = 'fas fa-info-circle';
                                        $badgeClass = 'bg-primary';
                                        
                                        switch ($logData['event']) {
                                            case 'login_failed':
                                            case 'rate_limit_exceeded':
                                            case 'csrf_token_invalid':
                                                $severity = 'warning';
                                                $icon = 'fas fa-exclamation-triangle';
                                                $badgeClass = 'bg-warning';
                                                break;
                                            case 'registration_failed':
                                                $severity = 'danger';
                                                $icon = 'fas fa-times-circle';
                                                $badgeClass = 'bg-danger';
                                                break;
                                            case 'login_success':
                                            case 'user_registered':
                                                $severity = 'success';
                                                $icon = 'fas fa-check-circle';
                                                $badgeClass = 'bg-success';
                                                break;
                                        }
                                        ?>
                                        <tr>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($logData['timestamp']); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <i class="<?php echo $icon; ?> text-<?php echo $severity; ?>"></i>
                                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $logData['event']))); ?>
                                            </td>
                                            <td>
                                                <code><?php echo htmlspecialchars($logData['ip']); ?></code>
                                            </td>
                                            <td>
                                                <?php if (isset($logData['details']) && is_array($logData['details'])): ?>
                                                    <div class="small">
                                                        <?php foreach ($logData['details'] as $key => $value): ?>
                                                            <div>
                                                                <strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $key))); ?>:</strong>
                                                                <?php echo htmlspecialchars($value); ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $badgeClass; ?>">
                                                    <?php echo ucfirst($severity); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Security Statistics -->
    <div class="row mt-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Total Events</h6>
                            <h3><?php echo count($securityLogs); ?></h3>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-list fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php
        $eventCounts = [];
        foreach ($securityLogs as $log) {
            if (preg_match('/SECURITY: (.+)$/', $log, $matches)) {
                $logData = json_decode($matches[1], true);
                if ($logData && isset($logData['event'])) {
                    $eventCounts[$logData['event']] = ($eventCounts[$logData['event']] ?? 0) + 1;
                }
            }
        }
        ?>
        
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Successful Logins</h6>
                            <h3><?php echo $eventCounts['login_success'] ?? 0; ?></h3>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-sign-in-alt fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Failed Logins</h6>
                            <h3><?php echo $eventCounts['login_failed'] ?? 0; ?></h3>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-exclamation-triangle fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Registrations</h6>
                            <h3><?php echo $eventCounts['user_registered'] ?? 0; ?></h3>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-user-plus fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.opacity-75 {
    opacity: 0.75;
}

.table-responsive {
    max-height: 600px;
    overflow-y: auto;
}

.card-body h3 {
    margin-bottom: 0;
}
</style>

<?php
$content = ob_get_clean();
include 'layout.php';
?>
