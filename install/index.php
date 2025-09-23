<?php
session_start();

// Define install mode for error reporting
define('INSTALL_MODE', true);

// Check if already installed
if (file_exists('../config/database.php')) {
    header('Location: ../index.php');
    exit();
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 0;
$error = '';
$success = '';
$dependencyOutput = '';

// Include required files
require_once __DIR__ . '/../helpers/InstallerHelper.php';
require_once '../helpers/Database.php';

$dependencyStatus = InstallerHelper::getDependencyStatus();

if ($dependencyStatus['ready'] && $step === 0) {
    header('Location: index.php?step=1');
    exit();
}

if (!$dependencyStatus['ready'] && $step > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?step=0');
    exit();
}

if (isset($_SESSION['dependency_success'])) {
    $success = $_SESSION['dependency_success'];
    unset($_SESSION['dependency_success']);
}

if (isset($_SESSION['dependency_output'])) {
    $dependencyOutput = $_SESSION['dependency_output'];
    unset($_SESSION['dependency_output']);
}

if ($step === 0 && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'install_dependencies') {
    $result = InstallerHelper::ensureDependencies();
    $dependencyOutput = $result['output'];
    $dependencyStatus = InstallerHelper::getDependencyStatus();

    if ($result['success']) {
        $_SESSION['dependency_success'] = $result['message'];
        if (!empty($dependencyOutput)) {
            $_SESSION['dependency_output'] = $dependencyOutput;
        }
        header('Location: index.php?step=1');
        exit();
    } else {
        $error = $result['message'];
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step > 0) {
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
        // Admin account creation step
        $admin_username = trim($_POST['admin_username']);
        $admin_email = trim($_POST['admin_email']);
        $admin_password = $_POST['admin_password'];
        $admin_confirm_password = $_POST['admin_confirm_password'];
        $admin_first_name = trim($_POST['admin_first_name']);
        $admin_last_name = trim($_POST['admin_last_name']);

        if (empty($admin_username) || empty($admin_email) || empty($admin_password) || 
            empty($admin_first_name) || empty($admin_last_name)) {
            $error = 'Please fill in all required fields.';
        } elseif ($admin_password !== $admin_confirm_password) {
            $error = 'Passwords do not match.';
        } elseif (strlen($admin_password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Store admin config in session
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
        if (isset($_SESSION['db_config']) && isset($_SESSION['admin_config'])) {
            $db_config = $_SESSION['db_config'];
            $admin_config = $_SESSION['admin_config'];

            // Create database configuration file
            $config_content = "<?php\n";
            $config_content .= "// Database configuration\n";
            $config_content .= "define('DB_HOST', '" . addslashes($db_config['host']) . "');\n";
            $config_content .= "define('DB_NAME', '" . addslashes($db_config['database']) . "');\n";
            $config_content .= "define('DB_USER', '" . addslashes($db_config['username']) . "');\n";
            $config_content .= "define('DB_PASS', '" . addslashes($db_config['password']) . "');\n";
            $config_content .= "?>";

            if (file_put_contents('../config/database.php', $config_content)) {
                // Execute SQL file to create tables
                $database = new Database();
                echo "<!-- Attempting to execute database_complete.sql -->";
                if ($database->executeSqlFile('../database_complete.sql')) {
                    echo "<!-- Database schema executed successfully -->";
                    // Create admin user
                    $conn = $database->getConnection();
                    $hashed_password = password_hash($admin_config['password'], PASSWORD_DEFAULT);
                    
                    $query = "INSERT INTO users (username, email, password, first_name, last_name, role) 
                             VALUES (:username, :email, :password, :first_name, :last_name, 'admin')";
                    
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':username', $admin_config['username']);
                    $stmt->bindParam(':email', $admin_config['email']);
                    $stmt->bindParam(':password', $hashed_password);
                    $stmt->bindParam(':first_name', $admin_config['first_name']);
                    $stmt->bindParam(':last_name', $admin_config['last_name']);

                    if ($stmt->execute()) {
                        // Clear session data
                        unset($_SESSION['db_config']);
                        unset($_SESSION['admin_config']);
                        
                        header('Location: index.php?step=4');
                        exit();
                    } else {
                        $error = 'Could not create admin user.';
                    }
                } else {
                    $error = 'Could not create database tables.';
                }
            } else {
                $error = 'Could not create configuration file. Please check file permissions.';
            }
        } else {
            $error = 'Installation data is missing. Please start over.';
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
        body {
            background: linear-gradient(135deg, #0066ab 0%, #004d85 100%);
            min-height: 100vh;
        }
        .install-container {
            max-width: 600px;
            margin: 50px auto;
        }
        .install-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .install-header {
            background: linear-gradient(135deg, #0066ab 0%, #004d85 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin: 20px 0;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
        }
        .step.active {
            background: #0066ab;
            color: white;
        }
        .step.completed {
            background: #28a745;
            color: white;
        }
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
                    <div class="step <?php echo $step === 0 ? 'active' : 'completed'; ?>" title="Dependencies">D</div>
                    <div class="step <?php echo $step >= 1 ? ($step > 1 ? 'completed' : 'active') : ''; ?>">1</div>
                    <div class="step <?php echo $step >= 2 ? ($step > 2 ? 'completed' : 'active') : ''; ?>">2</div>
                    <div class="step <?php echo $step >= 3 ? ($step > 3 ? 'completed' : 'active') : ''; ?>">3</div>
                    <div class="step <?php echo $step >= 4 ? 'completed' : ''; ?>">4</div>
                </div>

                <div class="p-4">
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($step === 0): ?>
                        <h3>Step 0: Install Dependencies</h3>
                        <p class="text-muted">Promptash bundles a browser-based Composer runner for shared hosting. Install dependencies before continuing.</p>

                        <?php if (!$dependencyStatus['ready']): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> Composer will install required PHP libraries into the <code>vendor/</code> directory. This can take a minute on shared hosting.
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> Dependencies are already installed. You can continue to the next step.
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($dependencyStatus['missing'])): ?>
                            <div class="mb-3">
                                <strong>Missing items detected:</strong>
                                <ul class="list-group list-group-flush border rounded">
                                    <?php foreach ($dependencyStatus['missing'] as $missingItem): ?>
                                        <li class="list-group-item small"><?php echo htmlspecialchars($missingItem); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (!$dependencyStatus['ready']): ?>
                            <form method="POST" class="mb-3">
                                <input type="hidden" name="action" value="install_dependencies">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-cogs"></i> Run Composer Install
                                </button>
                            </form>
                        <?php endif; ?>

                        <p class="small text-muted">If your host blocks PHP process functions, edit <code>composer-install.php</code> to set a secret key and open it in your browser (e.g. <code>/composer-install.php?key=YOUR_SECRET</code>) to install dependencies manually. Delete that file afterwards.</p>

                        <?php if (!empty($dependencyOutput)): ?>
                            <h5 class="mt-4">Composer Output</h5>
                            <pre class="bg-light border rounded p-3 small"><?php echo htmlspecialchars($dependencyOutput); ?></pre>
                        <?php endif; ?>

                        <?php if ($dependencyStatus['ready']): ?>
                            <a class="btn btn-primary" href="index.php?step=1">
                                <i class="fas fa-arrow-right"></i> Continue to Step 1
                            </a>
                        <?php endif; ?>

                    <?php elseif ($step == 1): ?>
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
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-arrow-right"></i> Next Step
                            </button>
                        </form>

                    <?php elseif ($step == 2): ?>
                        <h3>Step 2: Admin Account</h3>
                        <p class="text-muted">Create your administrator account.</p>
                        
                        <form method="POST">
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
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-arrow-right"></i> Next Step
                            </button>
                        </form>

                    <?php elseif ($step == 3): ?>
                        <h3>Step 3: Installation</h3>
                        <p class="text-muted">Ready to install Promptash with your configuration.</p>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            Click the button below to complete the installation process.
                        </div>
                        
                        <form method="POST">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-download"></i> Install Promptash
                            </button>
                        </form>

                    <?php elseif ($step == 4): ?>
                        <h3>Installation Complete!</h3>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> 
                            Promptash has been successfully installed!
                        </div>
                        
                        <p>Your application is now ready to use. You can:</p>
                        <ul>
                            <li>Login with your admin account</li>
                            <li>Start creating and managing prompts</li>
                            <li>Manage users from the admin panel</li>
                        </ul>
                        
                        <a href="../index.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-home"></i> Go to Application
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

