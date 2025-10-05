<?php
session_start();

// Define install mode for error reporting
define('INSTALL_MODE', true);

$configFile = __DIR__ . '/../config/config.php';
$configTemplateFile = __DIR__ . '/../config/config.php.example';
$sqlFile = __DIR__ . '/../database_complete.sql'; // Adjusted path based on your file structure
$lockFile = __DIR__ . '/install.lock';


// Check if already installed (either by lock file or the final config file)
if (file_exists($lockFile) || file_exists($configFile)) {
    header('Location: ../index.php');
    exit();
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

// Include required files
require_once '../helpers/Database.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step == 1) {
        // Database configuration step
        $host = trim($_POST['host']);
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $database = trim($_POST['database']);

        if (empty($host) || empty($username) || empty($database)) {
            $error = 'Please fill in all required fields.';
        } else {
            $db = new Database();
            
            // Test connection without database first
            if ($db->testConnection($host, $username, $password)) {
                // Try to create database
                if ($db->createDatabase($host, $username, $password, $database)) {
                    // Test connection with database
                    if ($db->testConnection($host, $username, $password, $database)) {
                        // Store database config in session
                        $_SESSION['db_config'] = [
                            'host' => $host,
                            'username' => $username,
                            'password' => $password,
                            'database' => $database
                        ];
                        header('Location: index.php?step=2');
                        exit();
                    } else {
                        $error = 'Could not connect to the database. Please check your credentials.';
                    }
                } else {
                    $error = 'Could not create database. Please check your permissions.';
                }
            } else {
                $error = 'Could not connect to MySQL server. Please check your credentials.';
            }
        }
    } elseif ($step == 2) {
        // Admin account and site configuration step
        $app_domain = trim($_POST['app_domain']); // <-- ADDED
        $admin_username = trim($_POST['admin_username']);
        $admin_email = trim($_POST['admin_email']);
        $admin_password = $_POST['admin_password'];
        $admin_confirm_password = $_POST['admin_confirm_password'];
        $admin_first_name = trim($_POST['admin_first_name']);
        $admin_last_name = trim($_POST['admin_last_name']);

        if (empty($app_domain) || empty($admin_username) || empty($admin_email) || empty($admin_password) || empty($admin_first_name) || empty($admin_last_name)) {
            $error = 'Please fill in all required fields.';
        } elseif (strpos($app_domain, 'http') !== false || strpos($app_domain, '/') !== false) {
            $error = 'The Application Domain should not include "http", "https", or slashes. Example: <strong>promptash.com</strong> or <strong>localhost</strong>.';
        } elseif ($admin_password !== $admin_confirm_password) {
            $error = 'Passwords do not match.';
        } elseif (strlen($admin_password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Store site and admin config in session
            $_SESSION['site_config'] = ['app_domain' => $app_domain]; // <-- ADDED
            $_SESSION['admin_config'] = [
                'username' => $admin_username,
                'email' => $admin_email,
                'password' => $admin_password,
                'first_name' => $admin_first_name,
                'last_name' => $admin_last_name
            ];
            header('Location: index.php?step=3');
            exit();
        }
    } elseif ($step == 3) {
        // Final installation step
        if (isset($_SESSION['db_config']) && isset($_SESSION['site_config']) && isset($_SESSION['admin_config'])) {
            $db_config = $_SESSION['db_config'];
            $site_config = $_SESSION['site_config']; // <-- ADDED
            $admin_config = $_SESSION['admin_config'];

            try {
                // --- 1. Create the config.php file from template ---
                if (!file_exists($configTemplateFile)) {
                    throw new Exception("Configuration template '{$configTemplateFile}' not found.");
                }
                $configContent = file_get_contents($configTemplateFile);
                $secretKey = bin2hex(random_bytes(32));

                $replacements = [
                    '{{APP_DOMAIN}}' => $site_config['app_domain'],
                    '{{DB_HOST}}'    => $db_config['host'],
                    '{{DB_NAME}}'    => $db_config['database'],
                    '{{DB_USER}}'    => $db_config['username'],
                    '{{DB_PASS}}'    => $db_config['password'],
                    '{{SECRET_KEY}}' => $secretKey,
                ];
                
                $configContent = str_replace(array_keys($replacements), array_values($replacements), $configContent);
                if (file_put_contents($configFile, $configContent) === false) {
                    throw new Exception("Could not write to '{$configFile}'. Please check directory permissions.");
                }

                // --- 2. Execute SQL file to create tables ---
                $db = new Database(); // Re-instantiate to use the new config file
                $conn = $db->getConnection();
                if (!$db->executeSqlFile($sqlFile, $conn)) {
                    throw new Exception("Could not execute SQL file '{$sqlFile}'. Make sure the file exists and is readable.");
                }

                // --- 3. Create admin user ---
                $hashed_password = password_hash($admin_config['password'], PASSWORD_DEFAULT);
                $query = "INSERT INTO users (username, email, password, first_name, last_name, role) VALUES (:username, :email, :password, :first_name, :last_name, 'admin')";
                
                $stmt = $conn->prepare($query);
                if (!$stmt->execute([
                    ':username'   => $admin_config['username'],
                    ':email'      => $admin_config['email'],
                    ':password'   => $hashed_password,
                    ':first_name' => $admin_config['first_name'],
                    ':last_name'  => $admin_config['last_name']
                ])) {
                     throw new Exception('Could not create the admin user.');
                }

                // --- 4. Create the lock file ---
                file_put_contents($lockFile, date('Y-m-d H:i:s'));
                
                session_destroy();
                
                header('Location: index.php?step=4');
                exit();

            } catch (Exception $e) {
                $error = 'Installation failed: ' . $e->getMessage();
                // Clean up the config file if it was created on error
                if (file_exists($configFile)) {
                    unlink($configFile);
                }
            }
        } else {
            $error = 'Installation data is missing. Please start over.';
            $step = 1;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promptash - Installation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #0066ab 0%, #004d85 100%); min-height: 100vh; }
        .install-container { max-width: 600px; margin: 50px auto; }
        .install-card { background: white; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); overflow: hidden; }
        .install-header { background: linear-gradient(135deg, #0066ab 0%, #004d85 100%); color: white; padding: 30px; text-align: center; }
        .step-indicator { display: flex; justify-content: center; margin: 20px 0; }
        .step { width: 40px; height: 40px; border-radius: 50%; background: #e9ecef; display: flex; align-items: center; justify-content: center; margin: 0 10px; font-weight: bold; }
        .step.active { background: #0066ab; color: white; }
        .step.completed { background: #28a745; color: white; }
        .form-text { font-size: 0.875em; }
    </style>
</head>
<body>
    <div class="container">
        <div class="install-container">
            <div class="install-card">
                <div class="install-header">
                    <h1><i class="fas fa-magic"></i> Promptash</h1>
                    <p class="mb-0">Installation Wizard</p>
                </div>
                
                <div class="step-indicator">
                    <div class="step <?php echo $step >= 1 ? ($step > 1 ? 'completed' : 'active') : ''; ?>">1</div>
                    <div class="step <?php echo $step >= 2 ? ($step > 2 ? 'completed' : 'active') : ''; ?>">2</div>
                    <div class="step <?php echo $step >= 3 ? ($step > 3 ? 'completed' : 'active') : ''; ?>">3</div>
                    <div class="step <?php echo $step >= 4 ? 'completed' : ''; ?>">4</div>
                </div>

                <div class="p-4">
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($step == 1): ?>
                        <h3>Step 1: Database Configuration</h3>
                        <p class="text-muted">Please provide your MySQL database connection details.</p>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="host" class="form-label">Database Host</label>
                                <input type="text" class="form-control" id="host" name="host" value="localhost" required>
                            </div>
                            <div class="mb-3">
                                <label for="username" class="form-label">Database Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Database Password</label>
                                <input type="password" class="form-control" id="password" name="password">
                            </div>
                            <div class="mb-3">
                                <label for="database" class="form-label">Database Name</label>
                                <input type="text" class="form-control" id="database" name="database" required>
                                <div class="form-text">The database will be created if it doesn't exist.</div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-arrow-right"></i> Next Step</button>
                        </form>

                    <?php elseif ($step == 2): ?>
                        <h3>Step 2: Site & Admin Account</h3>
                        <p class="text-muted">Set your site's domain and create the administrator account.</p>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="app_domain" class="form-label fw-bold">Application Domain</label>
                                <input type="text" class="form-control" id="app_domain" name="app_domain" placeholder="e.g., myapp.com or localhost" required>
                                <div class="form-text text-danger">This is critical for Passkeys to work correctly. Do not include <code>http://</code> or <code>https://</code>.</div>
                            </div>
                            <hr class="my-4">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="admin_first_name" class="form-label">First Name</label>
                                        <input type="text" class="form-control" id="admin_first_name" name="admin_first_name" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="admin_last_name" class="form-label">Last Name</label>
                                        <input type="text" class="form-control" id="admin_last_name" name="admin_last_name" required>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="admin_username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="admin_username" name="admin_username" required>
                            </div>
                            <div class="mb-3">
                                <label for="admin_email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="admin_email" name="admin_email" required>
                            </div>
                            <div class="mb-3">
                                <label for="admin_password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="admin_password" name="admin_password" required>
                                <div class="form-text">Password must be at least 8 characters long.</div>
                            </div>
                            <div class="mb-3">
                                <label for="admin_confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="admin_confirm_password" name="admin_confirm_password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-arrow-right"></i> Next Step</button>
                        </form>

                    <?php elseif ($step == 3): ?>
                        <h3>Step 3: Installation</h3>
                        <p class="text-muted">Ready to install Promptash with your configuration.</p>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            Click the button below to complete the installation process.
                        </div>
                        
                        <form method="POST">
                            <button type="submit" class="btn btn-success btn-lg w-100">
                                <i class="fas fa-download"></i> Install Promptash
                            </button>
                        </form>

                    <?php elseif ($step == 4): ?>
                        <h3><i class="fas fa-check-circle text-success"></i> Installation Complete!</h3>
                        <div class="alert alert-success">Promptash has been successfully installed!</div>
                        <p class="fw-bold text-danger">For security, please delete the entire <code>/install</code> directory from your server immediately.</p>
                        
                        <a href="../index.php?page=login" class="btn btn-primary btn-lg w-100 mt-3">
                            <i class="fas fa-sign-in-alt"></i> Go to Login Page
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
