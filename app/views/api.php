<?php
// API endpoints for modal functionality
// Prevent any output before JSON
ob_start();

// Set error reporting to prevent HTML error output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');

// Include configuration and models
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/Database.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/TOTP.php';
require_once __DIR__ . '/../../helpers/AIHelper.php';
require_once __DIR__ . '/../../helpers/PasskeyHelper.php'; // ** NEW **
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Prompt.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../models/Bookmark.php';
require_once __DIR__ . '/../models/Note.php';
require_once __DIR__ . '/../models/Document.php';
require_once __DIR__ . '/../models/Video.php';
require_once __DIR__ . '/../models/UserSettings.php';
require_once __DIR__ . '/../models/UsageTracker.php';
require_once __DIR__ . '/../models/AppSettings.php'; // Include AppSettings

// Initialize authentication
$auth = new Auth();

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

// Public endpoints that do not require login
$public_endpoints = ['login', 'ping', 'server_info', 'get_server_info', 'passkey_login_options', 'passkey_login_verify'];

if (!in_array($action, $public_endpoints) && !$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user = $auth->getCurrentUser();

// Initialize models
$userModel = new User();
$promptModel = new Prompt();
$categoryModel = new Category();
$bookmarkModel = new Bookmark();
$noteModel = new Note();
$documentModel = new Document();
$videoModel = new Video();
$settingsModel = new UserSettings();
$usageTracker = new UsageTracker();
$appSettings = new AppSettings();

try {
    // Debug logging
    error_log("API called with action: " . $action);
    error_log("POST data: " . print_r($_POST, true));
    
    switch ($action) {
        
        // ** NEW ** Passkey Endpoints
        case 'passkey_register_options':
            // The main login check at the top of this file already protects this endpoint,
            // so the `$user` variable is safely available for us to use.
            try {
                $passkeyHelper = new PasskeyHelper();

                // Use the existing $user variable to get the required info
                $args = $passkeyHelper->getRegistrationArgs($user['id'], $user['username'], $user['first_name'] . ' ' . $user['last_name']);

                // Save the challenge to the session to verify the user's response later
                $_SESSION['passkey_challenge'] = $passkeyHelper->challengeToString($args->challenge);

                // This is the main fix: We wrap the response in a 'publicKey' object,
                // which is what the JavaScript in app.js is expecting.
                echo json_encode(['publicKey' => $args]);

            } catch (Exception $e) {
                // This will catch any other errors and send a clean error message back.
                echo json_encode(['success' => false, 'message' => 'Error generating passkey options: ' . $e->getMessage()]);
            }
            break;

        case 'passkey_register_verify':
            if (!isset($_SESSION['passkey_challenge'])) {
                throw new Exception('No challenge found in session.');
            }
            $challenge = $_SESSION['passkey_challenge'];
            $clientData = file_get_contents('php://input');

            $passkeyHelper = new PasskeyHelper();
            $passkeyData = $passkeyHelper->processRegistration($user['id'], $clientData, $challenge);

            if ($passkeyData) {
                unset($_SESSION['passkey_challenge']);
                $passkeys = $passkeyHelper->getPasskeysForUser($user['id']);

                echo json_encode([
                    'success' => true,
                    'message' => 'Passkey registered successfully!',
                    'passkey' => $passkeyData,
                    'passkeys' => $passkeys,
                ]);
            } else {
                throw new Exception('Failed to register passkey.');
            }
            break;

        case 'passkey_rename':
            $payload = json_decode(file_get_contents('php://input'), true);
            if (!is_array($payload)) {
                throw new Exception('Invalid request payload.');
            }

            $passkeyId = isset($payload['id']) ? (int)$payload['id'] : 0;
            $newName = $payload['name'] ?? '';

            if ($passkeyId <= 0) {
                throw new Exception('Invalid passkey identifier.');
            }

            $passkeyHelper = new PasskeyHelper();
            $updatedPasskey = $passkeyHelper->renamePasskey($user['id'], $passkeyId, $newName);
            $allPasskeys = $passkeyHelper->getPasskeysForUser($user['id']);

            echo json_encode([
                'success' => true,
                'message' => 'Passkey renamed successfully.',
                'passkey' => $updatedPasskey,
                'passkeys' => $allPasskeys,
            ]);
            break;

        case 'passkey_delete':
            $payload = json_decode(file_get_contents('php://input'), true);
            if (!is_array($payload)) {
                throw new Exception('Invalid request payload.');
            }

            $passkeyId = isset($payload['id']) ? (int)$payload['id'] : 0;

            if ($passkeyId <= 0) {
                throw new Exception('Invalid passkey identifier.');
            }

            $passkeyHelper = new PasskeyHelper();
            $passkeyHelper->deletePasskey($user['id'], $passkeyId);
            $remainingPasskeys = $passkeyHelper->getPasskeysForUser($user['id']);

            echo json_encode([
                'success' => true,
                'message' => 'Passkey deleted successfully.',
                'passkeys' => $remainingPasskeys,
            ]);
            break;

        case 'passkey_login_options':
            $passkeyHelper = new PasskeyHelper();
            $args = $passkeyHelper->getAuthenticationArgs();
            
            $_SESSION['passkey_challenge'] = $passkeyHelper->challengeToString($args->challenge);

            echo json_encode(['publicKey' => $args]);
            break;

        case 'passkey_login_verify':
            if (!isset($_SESSION['passkey_challenge'])) {
                throw new Exception('No challenge found in session.');
            }
            $challenge = $_SESSION['passkey_challenge'];
            $clientData = file_get_contents('php://input');

            $passkeyHelper = new PasskeyHelper();
            $userId = $passkeyHelper->processAuthentication($clientData, $challenge);
            
            if ($userId && $auth->loginWithPasskey($userId)) {
                unset($_SESSION['passkey_challenge']);
                echo json_encode(['success' => true, 'message' => 'Login successful!']);
            } else {
                throw new Exception('Passkey authentication failed.');
            }
            break;
        // ** END ** Passkey Endpoints

        // ** Authentication endpoint for Chrome extension
        case 'login':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            
            if (empty($username) || empty($password)) {
                throw new Exception('Username and password are required');
            }
            
            // Attempt to authenticate the user
            $loginResult = $auth->login($username, $password);
            
            if ($loginResult) {
                // Get user information
                $user = $auth->getCurrentUser();
                
                // Return success with user data
                echo json_encode([
                    'success' => true,
                    'message' => 'Login successful',
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'email' => $user['email'] ?? '',
                        'first_name' => $user['first_name'] ?? '',
                        'last_name' => $user['last_name'] ?? '',
                        'role' => $user['role'] ?? 'user'
                    ]
                ]);
            } else {
                // Login failed
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid username or password'
                ]);
            }
            break;
            
        case 'logout':
            // Logout endpoint for Chrome extension
            $auth->logout();
            echo json_encode([
                'success' => true,
                'message' => 'Logged out successfully'
            ]);
            break;
            
        case 'check_auth':
            // Check authentication status
            if ($auth->isLoggedIn()) {
                $user = $auth->getCurrentUser();
                echo json_encode([
                    'success' => true,
                    'authenticated' => true,
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username']
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'authenticated' => false
                ]);
            }
            break;
        case 'get_categories':
            $categories = $categoryModel->getByUserId($user['id']);
            echo json_encode([
                'success' => true,
                'categories' => $categories
            ]);
            break;
            
        case 'create_prompt':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            // Check usage limits first
            if (!$usageTracker->canPerformAction($user['id'], 'prompt_creation')) {
                $usageSummary = $usageTracker->getUserUsageSummary($user['id']);
                $limit = $usageSummary['usage']['prompt_creation']['limit'];
                throw new Exception("You have reached your monthly limit of {$limit} prompts. <a href='index.php?page=upgrade' class='alert-link'>Upgrade to Premium</a> for unlimited prompts.");
            }
            
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
            $tags = trim($_POST['tags'] ?? '');
            
            if (empty($title) || empty($content)) {
                throw new Exception('Title and content are required');
            }
            
            $result = $promptModel->create($title, $content, $description, $category_id, $user['id'], $tags);
            
            if ($result) {
                // Track the usage
                $usageTracker->trackUsage($user['id'], 'prompt_creation');
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Prompt created successfully',
                    'id' => $result
                ]);
            } else {
                throw new Exception('Failed to create prompt');
            }
            break;
            
        case 'create_category':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            // Check usage limits first
            if (!$usageTracker->canPerformAction($user['id'], 'category_creation')) {
                $usageSummary = $usageTracker->getUserUsageSummary($user['id']);
                $limit = $usageSummary['usage']['category_creation']['limit'];
                throw new Exception("You have reached your monthly limit of {$limit} categories. <a href='index.php?page=upgrade' class='alert-link'>Upgrade to Premium</a> for unlimited categories.");
            }
            
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            
            if (empty($name)) {
                throw new Exception('Category name is required');
            }
            
            $result = $categoryModel->create($name, $description, $user['id']);
            
            if ($result) {
                // Track the usage
                $usageTracker->trackUsage($user['id'], 'category_creation');
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Category created successfully',
                    'id' => $result
                ]);
            } else {
                throw new Exception('Failed to create category');
            }
            break;
            
        case 'get_prompt':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if (!$id) {
                throw new Exception('Prompt ID is required');
            }
            
            // First try to get as owner, then as shared
            $prompt = $promptModel->getByIdForViewing($id, $user['id']);
            
            if ($prompt) {
                echo json_encode([
                    'success' => true,
                    'prompt' => $prompt
                ]);
            } else {
                throw new Exception('Prompt not found');
            }
            break;
            
        case 'edit_prompt':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
            $tags = trim($_POST['tags'] ?? '');
            
            if (!$id || empty($title) || empty($content)) {
                throw new Exception('ID, title and content are required');
            }
            
            $result = $promptModel->update($id, $user['id'], $title, $content, $description, $category_id, $tags);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Prompt updated successfully'
                ]);
            } else {
                throw new Exception('Failed to update prompt');
            }
            break;
            
        case 'get_category':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if (!$id) {
                throw new Exception('Category ID is required');
            }
            
            $category = $categoryModel->getById($id, $user['id']);
            
            if ($category) {
                echo json_encode([
                    'success' => true,
                    'category' => $category
                ]);
            } else {
                throw new Exception('Category not found');
            }
            break;
            
        case 'edit_category':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            
            if (!$id || empty($name)) {
                throw new Exception('ID and name are required');
            }
            
            $result = $categoryModel->update($id, $user['id'], $name, $description);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Category updated successfully'
                ]);
            } else {
                throw new Exception('Failed to update category');
            }
            break;
            
        case 'delete_prompt':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            if (!$id) {
                throw new Exception('Prompt ID is required');
            }
            
            $result = $promptModel->delete($id, $user['id']);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Prompt deleted successfully'
                ]);
            } else {
                throw new Exception('Failed to delete prompt');
            }
            break;
            
        case 'delete_category':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            if (!$id) {
                throw new Exception('Category ID is required');
            }
            
            $result = $categoryModel->delete($id, $user['id']);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Category deleted successfully'
                ]);
            } else {
                throw new Exception('Failed to delete category');
            }
            break;
            
        // ** NEW ** Bookmark endpoints
        case 'fetch_url_metadata':
            $url = trim($_POST['url'] ?? '');
            if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                throw new Exception('Invalid URL provided');
            }
            
            $html = @file_get_contents($url);
            if ($html === false) {
                throw new Exception('Could not fetch URL content');
            }
            
            $doc = new DOMDocument();
            @$doc->loadHTML($html);
            
            $title = '';
            $description = '';
            $image = '';
            
            $titleNode = $doc->getElementsByTagName('title');
            if ($titleNode->length > 0) {
                $title = $titleNode->item(0)->nodeValue;
            }
            
            $metas = $doc->getElementsByTagName('meta');
            for ($i = 0; $i < $metas->length; $i++) {
                $meta = $metas->item($i);
                if ($meta->getAttribute('name') == 'description') {
                    $description = $meta->getAttribute('content');
                }
                if ($meta->getAttribute('property') == 'og:image') {
                    $image = $meta->getAttribute('content');
                }
            }
            
            echo json_encode([
                'success' => true,
                'metadata' => [
                    'title' => $title,
                    'description' => $description,
                    'image' => $image
                ]
            ]);
            break;
            
        case 'create_bookmark':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            // Check usage limits first
            if (!$usageTracker->canPerformAction($user['id'], 'bookmark_creation')) {
                $usageSummary = $usageTracker->getUserUsageSummary($user['id']);
                $limit = $usageSummary['usage']['bookmark_creation']['limit'];
                throw new Exception("You have reached your limit of {$limit} bookmarks. <a href='index.php?page=upgrade' class='alert-link'>Upgrade to Premium</a> for more bookmarks.");
            }
            
            $url = trim($_POST['url'] ?? '');
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $image = trim($_POST['image'] ?? '');
            $tags = trim($_POST['tags'] ?? '');

            if (empty($url) || empty($title)) {
                throw new Exception('URL and title are required');
            }

            if ($bookmarkModel->urlExistsForUser($user['id'], $url)) {
                throw new Exception('This bookmark has already been added.');
            }
            
            $result = $bookmarkModel->create($user['id'], $url, $title, $description, $image, $tags);
            
            if ($result) {
                // Track the usage
                $usageTracker->trackUsage($user['id'], 'bookmark_creation');
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Bookmark created successfully'
                ]);
            } else {
                throw new Exception('Failed to create bookmark');
            }
            break;

        case 'get_bookmark':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if (!$id) {
                throw new Exception('Bookmark ID is required');
            }
            
            $bookmark = $bookmarkModel->getById($id, $user['id']);
            
            if ($bookmark) {
                echo json_encode([
                    'success' => true,
                    'bookmark' => $bookmark
                ]);
            } else {
                throw new Exception('Bookmark not found');
            }
            break;

        case 'edit_bookmark':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $image = trim($_POST['image'] ?? '');
            $tags = trim($_POST['tags'] ?? '');
            
            if (!$id || empty($title)) {
                throw new Exception('ID and title are required');
            }
            
            $result = $bookmarkModel->update($id, $user['id'], $title, $description, $image, $tags);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Bookmark updated successfully'
                ]);
            } else {
                throw new Exception('Failed to update bookmark');
            }
            break;

        case 'delete_bookmark':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            if (!$id) {
                throw new Exception('Bookmark ID is required');
            }
            
            $result = $bookmarkModel->delete($id, $user['id']);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Bookmark deleted successfully'
                ]);
            } else {
                throw new Exception('Failed to delete bookmark');
            }
            break;
            
        case 'share_bookmark':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }

            $bookmark_id = isset($_POST['bookmark_id']) ? (int)$_POST['bookmark_id'] : 0;
            $emails = trim($_POST['emails'] ?? '');
            $share_with_all = isset($_POST['share_with_all']);

            if (!$bookmark_id) {
                throw new Exception('Bookmark ID is required');
            }
            
            if ($share_with_all) {
                $result = $bookmarkModel->share($bookmark_id, $user['id'], null, true);
            } else {
                $email_array = array_map('trim', explode(',', $emails));
                $shared_count = 0;
                
                foreach ($email_array as $email) {
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $recipient = $userModel->findByEmail($email);
                        if ($recipient) {
                            $share_result = $bookmarkModel->share($bookmark_id, $user['id'], $recipient['id']);
                            if ($share_result) {
                                $shared_count++;
                            }
                        }
                    }
                }
                
                if ($shared_count === 0) {
                    throw new Exception('No valid recipients found for sharing');
                }
            }

            echo json_encode([
                'success' => true,
                'message' => 'Bookmark shared successfully'
            ]);
            break;

        case 'unshare_bookmark':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }

            $bookmark_id = isset($_POST['bookmark_id']) ? (int)$_POST['bookmark_id'] : 0;
            $recipient_id = isset($_POST['recipient_id']) ? (int)$_POST['recipient_id'] : null;

            if (!$bookmark_id) {
                throw new Exception('Bookmark ID is required');
            }

            $result = $bookmarkModel->unshare($bookmark_id, $user['id'], $recipient_id);

            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => $recipient_id ? 'Bookmark unshared from specific user' : 'Bookmark unshared from all users'
                ]);
            } else {
                throw new Exception('Failed to unshare bookmark');
            }
            break;

        case 'save_shared_bookmark':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }

            $bookmark_id = isset($_POST['bookmark_id']) ? (int)$_POST['bookmark_id'] : 0;

            if (!$bookmark_id) {
                throw new Exception('Bookmark ID is required');
            }
            
            if ($bookmarkModel->hasUserSavedSharedBookmark($bookmark_id, $user['id'])) {
                throw new Exception('You have already saved this bookmark.');
            }

            $result = $bookmarkModel->saveSharedBookmark($bookmark_id, $user['id']);

            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Bookmark copied to your collection'
                ]);
            } else {
                throw new Exception('Failed to save shared bookmark');
            }
            break;
        
        case 'share_prompt':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }

            $prompt_id = isset($_POST['prompt_id']) ? (int)$_POST['prompt_id'] : 0;
            $emails = trim($_POST['emails'] ?? '');
            $share_with_all = isset($_POST['share_with_all']);

            if (!$prompt_id) {
                throw new Exception('Prompt ID is required');
            }

            // Get the prompt details for notifications
            $prompt = $promptModel->getById($prompt_id, $user['id']);
            if (!$prompt) {
                throw new Exception('Prompt not found or access denied');
            }

            // Initialize notification service
            require_once __DIR__ . '/../../helpers/NotificationService.php';
            $notificationService = new NotificationService();

            if ($share_with_all) {
                $result = $promptModel->share($prompt_id, $user['id'], null, true);
                // Note: For share_with_all, we don't send individual notifications to avoid spam
            } else {
                $email_array = array_map('trim', explode(',', $emails));
                $shared_count = 0;
                
                foreach ($email_array as $email) {
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        error_log("DEBUG: Looking for user with email: {$email}");
                        $recipient = $userModel->findByEmail($email);
                        if ($recipient) {
                            error_log("DEBUG: Found recipient: " . print_r($recipient, true));
                            $share_result = $promptModel->share($prompt_id, $user['id'], $recipient['id']);
                            if ($share_result) {
                                error_log("DEBUG: Successfully shared prompt, now sending notification");
                                // Send notification to recipient
                                try {
                                    $notification_result = $notificationService->sendPromptSharedNotification(
                                        $recipient,
                                        $user,
                                        $prompt['title'],
                                        $prompt_id
                                    );
                                    $shared_count++;
                                    
                                    if ($notification_result) {
                                        error_log("SUCCESS: Prompt '{$prompt['title']}' shared with {$recipient['email']} - notification sent");
                                    } else {
                                        error_log("WARNING: Prompt '{$prompt['title']}' shared with {$recipient['email']} - notification failed to send");
                                    }
                                } catch (Exception $notif_e) {
                                    error_log("ERROR: Notification failed for prompt share to {$recipient['email']}: " . $notif_e->getMessage());
                                }
                            } else {
                                error_log("ERROR: Failed to share prompt with {$recipient['email']}");
                            }
                        } else {
                            error_log("WARNING: No user found with email: {$email}");
                        }
                    } else {
                        error_log("WARNING: Invalid email format: {$email}");
                    }
                }
                
                if ($shared_count === 0) {
                    throw new Exception('No valid recipients found for sharing');
                }
            }

            echo json_encode([
                'success' => true,
                'message' => 'Prompt shared successfully'
            ]);
            break;
            
        case 'unshare_prompt':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }

            $prompt_id = isset($_POST['prompt_id']) ? (int)$_POST['prompt_id'] : 0;
            $recipient_id = isset($_POST['recipient_id']) ? (int)$_POST['recipient_id'] : null;

            if (!$prompt_id) {
                throw new Exception('Prompt ID is required');
            }

            $result = $promptModel->unshare($prompt_id, $user['id'], $recipient_id);

            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => $recipient_id ? 'Prompt unshared from specific user' : 'Prompt unshared from all users'
                ]);
            } else {
                throw new Exception('Failed to unshare prompt');
            }
            break;
            
        case 'save_shared_prompt':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }

            $prompt_id = isset($_POST['prompt_id']) ? (int)$_POST['prompt_id'] : 0;

            if (!$prompt_id) {
                throw new Exception('Prompt ID is required');
            }

            // Verify user has access to this shared prompt
            $shared_prompt = $promptModel->getByIdForViewing($prompt_id, $user['id']);
            if (!$shared_prompt || !isset($shared_prompt['sharer_id'])) {
                throw new Exception('Shared prompt not found or access denied');
            }

            $result = $promptModel->saveSharedPrompt($prompt_id, $user['id']);

            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Prompt copied to your collection',
                    'new_prompt_id' => $result
                ]);
            } else {
                // Check if it failed because user already has this prompt
                if ($promptModel->hasUserSavedSharedPrompt($prompt_id, $user['id'])) {
                    throw new Exception('You have already saved this prompt to your collection');
                } else {
                    throw new Exception('Failed to save shared prompt');
                }
            }
            break;

        // ** NEW ** Note endpoints
        case 'create_note':
            if (!$usageTracker->canPerformAction($user['id'], 'note_creation')) {
                $usageSummary = $usageTracker->getUserUsageSummary($user['id']);
                $limit = $usageSummary['usage']['note_creation']['limit'];
                throw new Exception("You have reached your limit of {$limit} notes. <a href='index.php?page=upgrade' class='alert-link'>Upgrade to Premium</a> for more notes.");
            }
            $title = trim($_POST['title'] ?? 'New Note');
            $content = trim($_POST['content'] ?? '');
            $color = trim($_POST['color'] ?? 'yellow');
            if ($noteModel->create($user['id'], $title, $content, $color)) {
                $usageTracker->trackUsage($user['id'], 'note_creation');
                echo json_encode(['success' => true, 'message' => 'Note created successfully']);
            } else {
                throw new Exception('Failed to create note');
            }
            break;

        case 'get_note':
            $id = (int)($_GET['id'] ?? 0);
            $note = $noteModel->getById($id, $user['id']);
            if ($note) {
                echo json_encode(['success' => true, 'note' => $note]);
            } else {
                throw new Exception('Note not found');
            }
            break;

        case 'edit_note':
            $id = (int)($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? 'New Note');
            $content = trim($_POST['content'] ?? '');
            $color = trim($_POST['color'] ?? 'yellow');
            if ($noteModel->update($id, $user['id'], $title, $content, $color)) {
                echo json_encode(['success' => true, 'message' => 'Note updated successfully']);
            } else {
                throw new Exception('Failed to update note');
            }
            break;

        case 'delete_note':
            $id = (int)($_POST['id'] ?? 0);
            if ($noteModel->delete($id, $user['id'])) {
                echo json_encode(['success' => true, 'message' => 'Note deleted successfully']);
            } else {
                throw new Exception('Failed to delete note');
            }
            break;

        case 'toggle_pin_note':
            $id = (int)($_POST['id'] ?? 0);
            if ($noteModel->togglePin($id, $user['id'])) {
                echo json_encode(['success' => true, 'message' => 'Note pin toggled']);
            } else {
                throw new Exception('Failed to toggle note pin');
            }
            break;
            
        case 'share_note':
            $note_id = (int)($_POST['note_id'] ?? 0);
            $emails = trim($_POST['emails'] ?? '');
            $share_with_all = isset($_POST['share_with_all']);
            if (!$note_id) throw new Exception('Note ID required');
            
            $sharer = $user;
            if ($share_with_all) {
                $noteModel->share($note_id, $sharer['id'], null, true);
            } else {
                $email_array = array_map('trim', explode(',', $emails));
                foreach ($email_array as $email) {
                    $recipient = $userModel->findByEmail($email);
                    if ($recipient) {
                        $noteModel->share($note_id, $sharer['id'], $recipient['id'], false);
                    }
                }
            }
            echo json_encode(['success' => true, 'message' => 'Note shared successfully']);
            break;

        case 'unshare_note':
            $note_id = (int)($_POST['note_id'] ?? 0);
            $recipient_id = isset($_POST['recipient_id']) ? (int)$_POST['recipient_id'] : null;
            if (!$note_id) throw new Exception('Note ID required');
            $noteModel->unshare($note_id, $user['id'], $recipient_id);
            echo json_encode(['success' => true, 'message' => 'Note unshared']);
            break;

        case 'save_shared_note':
            $note_id = (int)($_POST['note_id'] ?? 0);
            if (!$note_id) throw new Exception('Note ID required');
            $new_note_id = $noteModel->saveSharedNote($note_id, $user['id']);
            if ($new_note_id) {
                echo json_encode(['success' => true, 'message' => 'Note copied to your collection']);
            } else {
                throw new Exception('Failed to copy note');
            }
            break;

        // ** NEW ** Document endpoints
        case 'upload_document':
            if (!$usageTracker->canPerformAction($user['id'], 'document_creation')) {
                $usageSummary = $usageTracker->getUserUsageSummary($user['id']);
                $limit = $usageSummary['usage']['document_creation']['limit'];
                throw new Exception("You have reached your limit of {$limit} documents. <a href='index.php?page=upgrade' class='alert-link'>Upgrade to Premium</a> for more documents.");
            }
            if (isset($_FILES['document'])) {
                $file = $_FILES['document'];
                $file_name = basename($file['name']);
                $file_path = $documentModel->upload_dir . $user['id'] . '_' . time() . '_' . $file_name;
                
                if (move_uploaded_file($file['tmp_name'], $file_path)) {
                    if ($documentModel->create($user['id'], $file_name, $file_path, $file['size'], $file['type'])) {
                        $usageTracker->trackUsage($user['id'], 'document_creation');
                        echo json_encode(['success' => true, 'message' => 'Document uploaded successfully']);
                    } else {
                        unlink($file_path); // Clean up uploaded file
                        throw new Exception('Failed to save document record');
                    }
                } else {
                    throw new Exception('Failed to upload document');
                }
            } else {
                throw new Exception('No document file provided');
            }
            break;

        case 'delete_document':
            $id = (int)($_POST['id'] ?? 0);
            if ($documentModel->delete($id, $user['id'])) {
                echo json_encode(['success' => true, 'message' => 'Document deleted successfully']);
            } else {
                throw new Exception('Failed to delete document');
            }
            break;

        case 'share_document':
            $document_id = (int)($_POST['document_id'] ?? 0);
            $emails = trim($_POST['emails'] ?? '');
            $share_with_all = isset($_POST['share_with_all']);
            if (!$document_id) throw new Exception('Document ID required');

            $sharer = $user;
            if ($share_with_all) {
                $documentModel->share($document_id, $sharer['id'], null, true);
            } else {
                $email_array = array_map('trim', explode(',', $emails));
                foreach ($email_array as $email) {
                    $recipient = $userModel->findByEmail($email);
                    if ($recipient) {
                        $documentModel->share($document_id, $sharer['id'], $recipient['id'], false);
                    }
                }
            }
            echo json_encode(['success' => true, 'message' => 'Document shared successfully']);
            break;

        case 'unshare_document':
            $document_id = (int)($_POST['document_id'] ?? 0);
            $recipient_id = isset($_POST['recipient_id']) ? (int)$_POST['recipient_id'] : null;
            if (!$document_id) throw new Exception('Document ID required');
            $documentModel->unshare($document_id, $user['id'], $recipient_id);
            echo json_encode(['success' => true, 'message' => 'Document unshared']);
            break;

        case 'save_shared_document':
            $document_id = (int)($_POST['document_id'] ?? 0);
            if (!$document_id) throw new Exception('Document ID required');
            $new_doc_id = $documentModel->saveSharedDocument($document_id, $user['id']);
            if ($new_doc_id) {
                echo json_encode(['success' => true, 'message' => 'Document copied to your collection']);
            } else {
                throw new Exception('Failed to copy document');
            }
            break;

        // ** NEW ** Video endpoints
        case 'add_video':
             if (!$usageTracker->canPerformAction($user['id'], 'video_creation')) {
                $usageSummary = $usageTracker->getUserUsageSummary($user['id']);
                $limit = $usageSummary['usage']['video_creation']['limit'];
                throw new Exception("You have reached your limit of {$limit} videos. <a href='index.php?page=upgrade' class='alert-link'>Upgrade to Premium</a> for more videos.");
            }
            $url = trim($_POST['url'] ?? '');
            
            // --- START MODIFICATION ---

            // Validate YouTube URL and extract Video ID
            if (!preg_match('/(youtube.com|youtu.be)\/(watch\?v=)?([a-zA-Z0-9_-]{11})/', $url, $matches)) {
                throw new Exception('Invalid or unsupported YouTube URL');
            }
            
            $video_id = $matches[3];
            
            // Fetch video details from YouTube's oEmbed endpoint
            $oembed_url = "https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v={$video_id}&format=json";
            $video_data_json = @file_get_contents($oembed_url);
            
            if ($video_data_json === false) {
                throw new Exception('Could not fetch video details. Please check the URL.');
            }
            
            $video_data = json_decode($video_data_json, true);
            
            // Extract details from the response
            $title = $video_data['title'] ?? 'YouTube Video';
            $description = "A video from the channel: " . ($video_data['author_name'] ?? 'Unknown');
            $thumbnail_url = $video_data['thumbnail_url'] ?? "https://img.youtube.com/vi/{$video_id}/0.jpg";
            $channel_title = $video_data['author_name'] ?? 'YouTube Channel';
            $duration = "N/A"; // oEmbed doesn't provide duration

            if ($videoModel->create($user['id'], $url, $title, $description, $thumbnail_url, $channel_title, $duration)) {
                $usageTracker->trackUsage($user['id'], 'video_creation');
                echo json_encode(['success' => true, 'message' => 'Video added successfully']);
            } else {
                throw new Exception('Failed to add video to your library.');
            }
            
            // --- END MODIFICATION ---
            break;
        
        case 'delete_video':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) {
                throw new Exception('Video ID is required');
            }
            if ($videoModel->delete($id, $user['id'])) {
                echo json_encode(['success' => true, 'message' => 'Video deleted successfully']);
            } else {
                throw new Exception('Failed to delete video');
            }
            break;
            
        case 'share_video':
            $video_id = (int)($_POST['video_id'] ?? 0);
            $emails = trim($_POST['emails'] ?? '');
            $share_with_all = isset($_POST['share_with_all']);
            if (!$video_id) throw new Exception('Video ID required');

            $sharer = $user;
            if ($share_with_all) {
                $videoModel->share($video_id, $sharer['id'], null, true);
            } else {
                $email_array = array_map('trim', explode(',', $emails));
                foreach ($email_array as $email) {
                    $recipient = $userModel->findByEmail($email);
                    if ($recipient) {
                        $videoModel->share($video_id, $sharer['id'], $recipient['id'], false);
                    }
                }
            }
            echo json_encode(['success' => true, 'message' => 'Video shared successfully']);
            break;

        case 'unshare_video':
            $video_id = (int)($_POST['video_id'] ?? 0);
            $recipient_id = isset($_POST['recipient_id']) ? (int)$_POST['recipient_id'] : null;
            if (!$video_id) throw new Exception('Video ID required');
            $videoModel->unshare($video_id, $user['id'], $recipient_id);
            echo json_encode(['success' => true, 'message' => 'Video unshared']);
            break;

        case 'save_shared_video':
            $video_id = (int)($_POST['video_id'] ?? 0);
            if (!$video_id) throw new Exception('Video ID required');
            $new_video_id = $videoModel->saveSharedVideo($video_id, $user['id']);
            if ($new_video_id) {
                echo json_encode(['success' => true, 'message' => 'Video copied to your collection']);
            } else {
                throw new Exception('Failed to copy video');
            }
            break;
            
        // Admin User Management Actions
        case 'create_user':
            if (!$auth->isAdmin()) {
                throw new Exception('Admin access required');
            }
            
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $role = $_POST['role'] ?? 'user';
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($username) || empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
                throw new Exception('All required fields must be filled');
            }
            
            if (strlen($password) < 8) {
                throw new Exception('Password must be at least 8 characters long');
            }
            
            $result = $userModel->createUser($username, $email, $password, $first_name, $last_name, $role, $is_active);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'User created successfully',
                    'id' => $result
                ]);
            } else {
                throw new Exception('Failed to create user. Username or email may already exist.');
            }
            break;
            
        case 'get_user':
            if (!$auth->isAdmin()) {
                throw new Exception('Admin access required');
            }
            
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if (!$id) {
                throw new Exception('User ID is required');
            }
            
            $userInfo = $userModel->getUserById($id);
            
            if ($userInfo) {
                echo json_encode([
                    'success' => true,
                    'user' => $userInfo
                ]);
            } else {
                throw new Exception('User not found');
            }
            break;
            
        case 'edit_user':
            if (!$auth->isAdmin()) {
                throw new Exception('Admin access required');
            }
            
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $role = $_POST['role'] ?? 'user';
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (!$id || empty($username) || empty($email) || empty($first_name) || empty($last_name)) {
                throw new Exception('ID and all required fields must be provided');
            }
            
            // Check if username/email exists for other users
            if ($userModel->usernameExists($username, $id)) {
                throw new Exception('Username already exists for another user');
            }
            
            if ($userModel->emailExists($email, $id)) {
                throw new Exception('Email already exists for another user');
            }
            
            $data = [
                'username' => $username,
                'email' => $email,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'role' => $role,
                'is_active' => $is_active
            ];
            
            $result = $userModel->updateUser($id, $data);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'User updated successfully'
                ]);
            } else {
                throw new Exception('Failed to update user');
            }
            break;
            
        case 'delete_user':
            if (!$auth->isAdmin()) {
                throw new Exception('Admin access required');
            }
            
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            if (!$id) {
                throw new Exception('User ID is required');
            }
            
            // Don't allow deleting the current admin
            if ($id == $user['id']) {
                throw new Exception('You cannot delete your own account');
            }
            
            $result = $userModel->deleteUser($id);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'User deleted successfully'
                ]);
            } else {
                throw new Exception('Failed to delete user');
            }
            break;
            
        case 'reset_password':
            if (!$auth->isAdmin()) {
                throw new Exception('Admin access required');
            }
            
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
            $new_password = trim($_POST['password'] ?? '');
            
            if (!$user_id) {
                throw new Exception('User ID is required');
            }
            
            // If no password provided, generate one
            if (empty($new_password)) {
                $new_password = $userModel->generateRandomPassword();
            }
            
            if (strlen($new_password) < 8) {
                throw new Exception('Password must be at least 8 characters long');
            }
            
            $result = $userModel->resetPassword($user_id, $new_password);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Password reset successfully',
                    'new_password' => $new_password
                ]);
            } else {
                throw new Exception('Failed to reset password');
            }
            break;
            
        // Two-Factor Authentication API endpoints
        case 'enable_2fa':
            if (!isset($_POST['secret']) || !isset($_POST['verification_code'])) {
                throw new Exception('Secret and verification code are required');
            }
            
            $secret = $_POST['secret'];
            $code = $_POST['verification_code'];
            
            if (!TOTP::verifyCode($secret, $code)) {
                throw new Exception('Invalid verification code');
            }
            
            $recovery_codes = TOTP::generateRecoveryCodes();
            if ($userModel->enable2FA($user['id'], $secret, $recovery_codes)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Two-factor authentication enabled successfully',
                    'recovery_codes' => $recovery_codes
                ]);
            } else {
                throw new Exception('Failed to enable two-factor authentication');
            }
            break;
            
        case 'disable_2fa':
            if (!isset($_POST['current_password'])) {
                throw new Exception('Current password is required');
            }
            
            if (!$auth->verifyPassword($user['username'], $_POST['current_password'])) {
                throw new Exception('Invalid password');
            }
            
            if ($userModel->disable2FA($user['id'])) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Two-factor authentication disabled successfully'
                ]);
            } else {
                throw new Exception('Failed to disable two-factor authentication');
            }
            break;
            
        case 'regenerate_recovery_codes':
            if (!isset($_POST['current_password'])) {
                throw new Exception('Current password is required');
            }
            
            if (!$auth->verifyPassword($user['username'], $_POST['current_password'])) {
                throw new Exception('Invalid password');
            }
            
            $new_recovery_codes = TOTP::generateRecoveryCodes();
            if ($userModel->updateRecoveryCodes($user['id'], $new_recovery_codes)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Recovery codes regenerated successfully',
                    'recovery_codes' => $new_recovery_codes
                ]);
            } else {
                throw new Exception('Failed to regenerate recovery codes');
            }
            break;
            
        case 'get_2fa_status':
            $twoFactorData = $userModel->get2FAData($user['id']);
            echo json_encode([
                'success' => true,
                'enabled' => (bool)$twoFactorData['two_factor_enabled'],
                'has_recovery_codes' => !empty($twoFactorData['two_factor_recovery_codes'])
            ]);
            break;
            
        case 'increment_usage':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            $prompt_id = (int)($_POST['prompt_id'] ?? 0);
            
            if ($prompt_id <= 0) {
                throw new Exception('Invalid prompt ID');
            }
            
            $result = $promptModel->incrementUsage($prompt_id, $user['id']);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Usage count updated'
                ]);
            } else {
                throw new Exception('Failed to update usage count');
            }
            break;
            
        case 'generate_prompt':
        case 'ai_generate_prompt':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            // Check usage limits first
            if (!$usageTracker->canPerformAction($user['id'], 'ai_generation')) {
                $usageSummary = $usageTracker->getUserUsageSummary($user['id']);
                $limit = $usageSummary['usage']['ai_generation']['limit'];
                throw new Exception("You have reached your monthly limit of {$limit} AI generations. <a href='index.php?page=upgrade' class='alert-link'>Upgrade to Premium</a> for 500 AI generations per month.");
            }
            
            $description = trim($_POST['description'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $style = trim($_POST['style'] ?? 'professional');
            $target_audience = trim($_POST['target_audience'] ?? 'general');
            
            if (empty($description)) {
                throw new Exception('Description is required');
            }
            
            $aiHelper = AIHelper::fromAdminSettings();
            if (!$aiHelper->isAiAvailable()) {
                throw new Exception('AI service is not available. Please contact your administrator.');
            }
            
            $generated_prompt = $aiHelper->generatePromptFromDescription($description, $category, $style, $target_audience);
            
            // Track the usage
            $usageTracker->trackUsage($user['id'], 'ai_generation');
            
            echo json_encode([
                'success' => true,
                'prompt' => $generated_prompt
            ]);
            break;
            
        case 'enhance_prompt':
        case 'ai_enhance_prompt':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            // Check usage limits first
            if (!$usageTracker->canPerformAction($user['id'], 'ai_generation')) {
                $usageSummary = $usageTracker->getUserUsageSummary($user['id']);
                $limit = $usageSummary['usage']['ai_generation']['limit'];
                throw new Exception("You have reached your monthly limit of {$limit} AI generations. <a href='index.php?page=upgrade' class='alert-link'>Upgrade to Premium</a> for 500 AI generations per month.");
            }
            
            $prompt = trim($_POST['prompt'] ?? '');
            $enhancement_type = trim($_POST['enhancement_type'] ?? 'clarity');
            
            if (empty($prompt)) {
                throw new Exception('Prompt content is required');
            }
            
            $aiHelper = AIHelper::fromAdminSettings();
            if (!$aiHelper->isAiAvailable()) {
                throw new Exception('AI service is not available. Please contact your administrator.');
            }
            
            $enhanced_prompt = $aiHelper->enhancePrompt($prompt, $enhancement_type);
            
            // Track the usage
            $usageTracker->trackUsage($user['id'], 'ai_generation');
            
            echo json_encode([
                'success' => true,
                'enhanced_prompt' => $enhanced_prompt
            ]);
            break;
            
        case 'generate_tags':
        case 'ai_generate_tags':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            if (!$usageTracker->canPerformAction($user['id'], 'ai_generation')) {
                $usageSummary = $usageTracker->getUserUsageSummary($user['id']);
                $limit = $usageSummary['usage']['ai_generation']['limit'];
                throw new Exception("You have reached your monthly limit of {$limit} AI generations. <a href='index.php?page=upgrade' class='alert-link'>Upgrade to Premium</a> for 500 AI generations per month.");
            }
            
            $prompt = trim($_POST['prompt'] ?? '');
            $max_tags = (int)($_POST['max_tags'] ?? 8);
            
            if (empty($prompt)) {
                throw new Exception('Prompt content is required');
            }
            
            $aiHelper = AIHelper::fromAdminSettings();
            if (!$aiHelper->isAiAvailable()) {
                throw new Exception('AI service is not available. Please contact your administrator.');
            }
            
            $tags_string = $aiHelper->generateTags($prompt, $max_tags);
            
            // FIX: Convert the comma-separated string from the AI into a proper array
            $tags_array = array_filter(array_map('trim', explode(',', $tags_string)));
            
            $usageTracker->trackUsage($user['id'], 'ai_generation');
            
            echo json_encode([
                'success' => true,
                'tags' => $tags_array // Return the array
            ]);
            break;
            
        case 'generate_title':
        case 'ai_generate_title':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            // Check usage limits first
            if (!$usageTracker->canPerformAction($user['id'], 'ai_generation')) {
                $usageSummary = $usageTracker->getUserUsageSummary($user['id']);
                $limit = $usageSummary['usage']['ai_generation']['limit'];
                throw new Exception("You have reached your monthly limit of {$limit} AI generations. <a href='index.php?page=upgrade' class='alert-link'>Upgrade to Premium</a> for 500 AI generations per month.");
            }
            
            $prompt = trim($_POST['prompt'] ?? '');
            
            if (empty($prompt)) {
                throw new Exception('Prompt content is required');
            }
            
            $aiHelper = AIHelper::fromAdminSettings();
            if (!$aiHelper->isAiAvailable()) {
                throw new Exception('AI service is not available. Please contact your administrator.');
            }
            
            $title = $aiHelper->generateTitle($prompt);
            
            // Track the usage
            $usageTracker->trackUsage($user['id'], 'ai_generation');
            
            echo json_encode([
                'success' => true,
                'title' => $title
            ]);
            break;
            
        case 'test_ai_connection':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            $aiHelper = AIHelper::fromAdminSettings();
            $result = $aiHelper->testConnection();
            
            echo json_encode($result);
            break;
            
        case 'test_paystack_connection':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            $auth->requireAdmin();
            
            require_once __DIR__ . '/../../helpers/PaymentProcessor.php';
            
            try {
                $paymentProcessor = new PaymentProcessor();
                $result = $paymentProcessor->testConnection();
                echo json_encode($result);
            } catch (Throwable $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Test failed: ' . $e->getMessage()
                ]);
            }
            break;
            
        case 'save_settings':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            $settings = $_POST['settings'] ?? [];
            $success_count = 0;
            
            foreach ($settings as $key => $value) {
                if ($settingsModel->setSetting($user['id'], $key, $value)) {
                    $success_count++;
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => "Saved {$success_count} settings successfully"
            ]);
            break;
            
        case 'get_settings':
            $settings = $settingsModel->getAllSettings($user['id']);
            echo json_encode([
                'success' => true,
                'settings' => $settings
            ]);
            break;
            
        case 'get_usage_summary':
            $usageSummary = $usageTracker->getUserUsageSummary($user['id']);
            echo json_encode([
                'success' => true,
                'usage' => $usageSummary
            ]);
            break;
            
        case 'check_usage_limit':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            $usage_type = trim($_POST['usage_type'] ?? '');
            $count = (int)($_POST['count'] ?? 1);
            
            if (empty($usage_type)) {
                throw new Exception('Usage type is required');
            }
            
            $canPerform = $usageTracker->canPerformAction($user['id'], $usage_type, $count);
            $usageSummary = $usageTracker->getUserUsageSummary($user['id']);
            
            echo json_encode([
                'success' => true,
                'can_perform' => $canPerform,
                'usage_summary' => $usageSummary['usage'][$usage_type] ?? null
            ]);
            break;
            
        // Analytics Export Endpoints (Admin Only)
        case 'export_usage_report':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            $auth->requireAdmin();
            
            // Get usage data for the last 6 months
            $months = [];
            for ($i = 5; $i >= 0; $i--) {
                $months[] = date('Y-m-01', strtotime("-{$i} months"));
            }
            
            $csvData = [];
            $csvData[] = ['Month', 'Prompts Created', 'AI Generations', 'Categories Created', 'Active Users'];
            
            foreach ($months as $month) {
                $stats = $usageTracker->getSystemUsageStats($month);
                $csvData[] = [
                    date('F Y', strtotime($month)),
                    isset($stats['stats']['prompt_creation']) ? $stats['stats']['prompt_creation']['total_usage'] : 0,
                    isset($stats['stats']['ai_generation']) ? $stats['stats']['ai_generation']['total_usage'] : 0,
                    isset($stats['stats']['category_creation']) ? $stats['stats']['category_creation']['total_usage'] : 0,
                    isset($stats['stats']['prompt_creation']) ? $stats['stats']['prompt_creation']['active_users'] : 0
                ];
            }
            
            // Output CSV
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="usage_report_' . date('Y-m-d') . '.csv"');
            
            $output = fopen('php://output', 'w');
            foreach ($csvData as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
            exit();
            
        case 'export_revenue_report':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            $auth->requireAdmin();
            
            // Get revenue data
            $database = new Database();
            $db = $database->getConnection();
            
            $query = "SELECT 
                        DATE_FORMAT(processed_at, '%Y-%m') as month,
                        COUNT(*) as transactions,
                        SUM(amount) as revenue,
                        transaction_type,
                        AVG(amount) as avg_amount
                      FROM payment_transactions 
                      WHERE status = 'success' 
                      AND processed_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                      GROUP BY month, transaction_type
                      ORDER BY month DESC, transaction_type";
            
            $stmt = $db->prepare($query);
            $stmt->execute();
            $results = $stmt->fetchAll();
            
            $csvData = [];
            $csvData[] = ['Month', 'Transaction Type', 'Count', 'Total Revenue', 'Average Amount'];
            
            foreach ($results as $row) {
                $csvData[] = [
                    date('F Y', strtotime($row['month'] . '-01')),
                    ucwords(str_replace('_', ' ', $row['transaction_type'])),
                    $row['transactions'],
                    $appSettings->formatPrice($row['revenue']),
                    $appSettings->formatPrice($row['avg_amount'])
                ];
            }
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="revenue_report_' . date('Y-m-d') . '.csv"');
            
            $output = fopen('php://output', 'w');
            foreach ($csvData as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
            exit();
            
        case 'export_user_report':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            $auth->requireAdmin();
            
            // Get user data with membership info
            $database = new Database();
            $db = $database->getConnection();
            
            $query = "SELECT 
                        u.id, u.username, u.email, u.first_name, u.last_name,
                        u.created_at, u.is_active, u.role,
                        mt.display_name as membership_tier,
                        us.status as subscription_status,
                        us.started_at as subscription_start,
                        us.expires_at as subscription_expires,
                        (
                            SELECT COUNT(*) FROM prompts p WHERE p.user_id = u.id
                        ) as total_prompts
                      FROM users u
                      LEFT JOIN membership_tiers mt ON mt.id = u.current_tier_id
                      LEFT JOIN user_subscriptions us ON us.user_id = u.id AND us.status = 'active'
                      ORDER BY u.created_at DESC";
            
            $stmt = $db->prepare($query);
            $stmt->execute();
            $results = $stmt->fetchAll();
            
            $csvData = [];
            $csvData[] = [
                'User ID', 'Username', 'Email', 'Full Name', 'Membership Tier',
                'Status', 'Role', 'Registration Date', 'Subscription Status',
                'Subscription Start', 'Subscription Expires', 'Total Prompts'
            ];
            
            foreach ($results as $row) {
                $csvData[] = [
                    $row['id'],
                    $row['username'],
                    $row['email'],
                    $row['first_name'] . ' ' . $row['last_name'],
                    $row['membership_tier'] ?: 'None',
                    $row['is_active'] ? 'Active' : 'Inactive',
                    ucfirst($row['role']),
                    date('Y-m-d', strtotime($row['created_at'])),
                    $row['subscription_status'] ? ucfirst($row['subscription_status']) : 'None',
                    $row['subscription_start'] ? date('Y-m-d', strtotime($row['subscription_start'])) : '',
                    $row['subscription_expires'] ? date('Y-m-d', strtotime($row['subscription_expires'])) : '',
                    $row['total_prompts']
                ];
            }
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="user_report_' . date('Y-m-d') . '.csv"');
            
            $output = fopen('php://output', 'w');
            foreach ($csvData as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
            exit();
            
        case 'export_subscription_report':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            $auth->requireAdmin();
            
            // Get subscription data
            $database = new Database();
            $db = $database->getConnection();
            
            $query = "SELECT 
                        us.id as subscription_id,
                        u.username, u.email, u.first_name, u.last_name,
                        mt.display_name as tier_name,
                        mt.price_annual,
                        us.status,
                        us.billing_cycle,
                        us.started_at,
                        us.expires_at,
                        us.cancelled_at,
                        us.auto_renew,
                        (
                            SELECT SUM(amount) FROM payment_transactions pt 
                            WHERE pt.subscription_id = us.id AND pt.status = 'success'
                        ) as total_paid
                      FROM user_subscriptions us
                      JOIN users u ON u.id = us.user_id
                      JOIN membership_tiers mt ON mt.id = us.tier_id
                      ORDER BY us.created_at DESC";
            
            $stmt = $db->prepare($query);
            $stmt->execute();
            $results = $stmt->fetchAll();
            
            $csvData = [];
            $csvData[] = [
                'Subscription ID', 'Username', 'Email', 'Full Name', 'Tier',
                'Annual Price', 'Status', 'Billing Cycle', 'Started', 'Expires',
                'Cancelled', 'Auto Renew', 'Total Paid'
            ];
            
            foreach ($results as $row) {
                $csvData[] = [
                    $row['subscription_id'],
                    $row['username'],
                    $row['email'],
                    $row['first_name'] . ' ' . $row['last_name'],
                    $row['tier_name'],
                    '$' . number_format($row['price_annual'], 2),
                    ucfirst($row['status']),
                    ucfirst($row['billing_cycle']),
                    date('Y-m-d', strtotime($row['started_at'])),
                    $row['expires_at'] ? date('Y-m-d', strtotime($row['expires_at'])) : 'Never',
                    $row['cancelled_at'] ? date('Y-m-d', strtotime($row['cancelled_at'])) : '',
                    $row['auto_renew'] ? 'Yes' : 'No',
                    '$' . number_format($row['total_paid'] ?: 0, 2)
                ];
            }
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="subscription_report_' . date('Y-m-d') . '.csv"');
            
            $output = fopen('php://output', 'w');
            foreach ($csvData as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
            exit();
            
        // Notification System Endpoints
        case 'get_notifications':
            require_once __DIR__ . '/../../helpers/NotificationService.php';
            $notificationService = new NotificationService();
            
            $limit = (int)($_GET['limit'] ?? 10);
            $offset = (int)($_GET['offset'] ?? 0);
            $unread_only = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
            
            $notifications = $notificationService->getUserNotifications($user['id'], $limit, $offset, $unread_only);
            $unread_count = $notificationService->getUnreadCount($user['id']);
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'unread_count' => $unread_count
            ]);
            break;
            
        case 'mark_notification_read':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            require_once __DIR__ . '/../../helpers/NotificationService.php';
            $notificationService = new NotificationService();
            
            $notification_id = (int)($_POST['notification_id'] ?? 0);
            if (!$notification_id) {
                throw new Exception('Notification ID is required');
            }
            
            $result = $notificationService->markAsRead($notification_id, $user['id']);
            
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Notification marked as read' : 'Failed to mark notification as read'
            ]);
            break;
            
        case 'mark_all_notifications_read':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            require_once __DIR__ . '/../../helpers/NotificationService.php';
            $notificationService = new NotificationService();
            
            $result = $notificationService->markAllAsRead($user['id']);
            
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'All notifications marked as read' : 'Failed to mark notifications as read'
            ]);
            break;
            
        case 'check_usage_notifications':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            require_once __DIR__ . '/../../helpers/NotificationService.php';
            $notificationService = new NotificationService();
            
            $notifications_sent = $notificationService->checkAndSendUsageNotifications($user['id']);
            
            echo json_encode([
                'success' => true,
                'notifications_sent' => $notifications_sent,
                'message' => "Checked usage limits and sent {$notifications_sent} notifications"
            ]);
            break;
            
        case 'delete_notification':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            require_once __DIR__ . '/../../helpers/NotificationService.php';
            $notificationService = new NotificationService();
            
            $notification_id = (int)($_POST['notification_id'] ?? 0);
            if (!$notification_id) {
                throw new Exception('Notification ID is required');
            }
            
            $result = $notificationService->deleteNotification($notification_id, $user['id']);
            
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Notification deleted successfully' : 'Failed to delete notification'
            ]);
            break;
            
        // ** NEW ** Chrome Extension Support Endpoints
        
        case 'get_prompts':
            // Bulk prompt retrieval for Chrome extension
            $page = (int)($_GET['page'] ?? 1);
            $limit = min((int)($_GET['limit'] ?? 20), 100); // Max 100 items per request
            $category_id = !empty($_GET['category']) ? (int)$_GET['category'] : null;
            $search = trim($_GET['search'] ?? '');
            
            $offset = ($page - 1) * $limit;
            
            // Assume the Prompt model has a method to get user prompts with pagination
            try {
                // We'll need to implement this method in the Prompt model
                $prompts = $promptModel->getByUserIdWithPagination($user['id'], $limit, $offset, $category_id, $search);
                $total = $promptModel->countByUserId($user['id'], $category_id, $search);
                
                echo json_encode([
                    'success' => true,
                    'prompts' => $prompts,
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'total_pages' => ceil($total / $limit)
                ]);
            } catch (Throwable $e) {
                // Fallback: Return a simplified response if the method doesn't exist
                echo json_encode([
                    'success' => true,
                    'prompts' => [],
                    'total' => 0,
                    'message' => 'Prompt listing requires model implementation'
                ]);
            }
            break;
            
        case 'get_bookmarks':
            // Bulk bookmark retrieval for Chrome extension
            $page = (int)($_GET['page'] ?? 1);
            $limit = min((int)($_GET['limit'] ?? 20), 100); // Max 100 items per request
            $search = trim($_GET['search'] ?? '');
            
            $offset = ($page - 1) * $limit;
            
            try {
                // We'll need to implement this method in the Bookmark model
                $bookmarks = $bookmarkModel->getByUserIdWithPagination($user['id'], $limit, $offset, $search);
                $total = $bookmarkModel->countByUserId($user['id'], $search);
                
                echo json_encode([
                    'success' => true,
                    'bookmarks' => $bookmarks,
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'total_pages' => ceil($total / $limit)
                ]);
            } catch (Throwable $e) {
                // Fallback: Return a simplified response if the method doesn't exist
                echo json_encode([
                    'success' => true,
                    'bookmarks' => [],
                    'total' => 0,
                    'message' => 'Bookmark listing requires model implementation'
                ]);
            }
            break;
            
        case 'search_prompts':
            // Search prompts for Chrome extension
            $query = trim($_GET['query'] ?? $_POST['query'] ?? '');
            $category_id = !empty($_GET['category']) ? (int)$_GET['category'] : null;
            $limit = min((int)($_GET['limit'] ?? 20), 100);
            
            if (empty($query)) {
                throw new Exception('Search query is required');
            }
            
            try {
                // We'll need to implement this method in the Prompt model
                $prompts = $promptModel->searchByUser($user['id'], $query, $category_id, $limit);
                
                echo json_encode([
                    'success' => true,
                    'prompts' => $prompts,
                    'query' => $query,
                    'total' => count($prompts)
                ]);
            } catch (Throwable $e) {
                // Fallback: Return a simplified response if the method doesn't exist
                echo json_encode([
                    'success' => true,
                    'prompts' => [],
                    'message' => 'Prompt search requires model implementation'
                ]);
            }
            break;
            
        case 'search_bookmarks':
            // Search bookmarks for Chrome extension
            $query = trim($_GET['query'] ?? $_POST['query'] ?? '');
            $limit = min((int)($_GET['limit'] ?? 20), 100);
            
            if (empty($query)) {
                throw new Exception('Search query is required');
            }
            
            try {
                // We'll need to implement this method in the Bookmark model
                $bookmarks = $bookmarkModel->searchByUser($user['id'], $query, $limit);
                
                echo json_encode([
                    'success' => true,
                    'bookmarks' => $bookmarks,
                    'query' => $query,
                    'total' => count($bookmarks)
                ]);
            } catch (Throwable $e) {
                // Fallback: Return a simplified response if the method doesn't exist
                echo json_encode([
                    'success' => true,
                    'bookmarks' => [],
                    'message' => 'Bookmark search requires model implementation'
                ]);
            }
            break;
            
        case 'get_user_profile':
            // Get user profile information for Chrome extension
            try {
                // Get basic user info
                $userInfo = [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'first_name' => $user['first_name'] ?? '',
                    'last_name' => $user['last_name'] ?? '',
                    'role' => $user['role'] ?? 'user',
                    'created_at' => $user['created_at'] ?? null
                ];
                
                // Get usage statistics
                $usageSummary = $usageTracker->getUserUsageSummary($user['id']);
                
                // Get counts
                $promptCount = $promptModel->countByUserId($user['id']);
                $categoryCount = $categoryModel->countByUserId($user['id']);
                
                try {
                    $bookmarkCount = $bookmarkModel->countByUserId($user['id']);
                } catch (Throwable $e) {
                    $bookmarkCount = 0;
                }
                
                echo json_encode([
                    'success' => true,
                    'user' => $userInfo,
                    'stats' => [
                        'prompts' => $promptCount,
                        'categories' => $categoryCount,
                        'bookmarks' => $bookmarkCount
                    ],
                    'usage' => $usageSummary
                ]);
            } catch (Throwable $e) {
                echo json_encode([
                    'success' => true,
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username']
                    ],
                    'message' => 'Limited profile data available'
                ]);
            }
            break;
            
        case 'update_user_profile':
            // Update user profile for Chrome extension
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            $updates = [];
            
            // Only allow updating certain fields
            $allowedFields = ['first_name', 'last_name', 'email'];
            
            foreach ($allowedFields as $field) {
                if (isset($_POST[$field])) {
                    $updates[$field] = trim($_POST[$field]);
                }
            }
            
            if (empty($updates)) {
                throw new Exception('No valid fields to update');
            }
            
            // Validate email if provided
            if (isset($updates['email']) && !filter_var($updates['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email address');
            }
            
            try {
                $result = $userModel->updateUser($user['id'], $updates);
                
                if ($result) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Profile updated successfully'
                    ]);
                } else {
                    throw new Exception('Failed to update profile');
                }
            } catch (Throwable $e) {
                throw new Exception('Profile update requires model implementation');
            }
            break;
            
        case 'get_favorites':
        case 'get_favorite_prompts':
            // Get favorite prompts for Chrome extension
            try {
                // This would require a favorites system to be implemented
                $favorites = $promptModel->getFavoritesByUserId($user['id']);
                
                echo json_encode([
                    'success' => true,
                    'prompts' => $favorites
                ]);
            } catch (Throwable $e) {
                // Fallback: Return empty favorites
                echo json_encode([
                    'success' => true,
                    'prompts' => [],
                    'message' => 'Favorites system requires model implementation'
                ]);
            }
            break;
            
        case 'toggle_favorite':
        case 'toggle_prompt_favorite':
            // Toggle prompt favorite status
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            $prompt_id = (int)($_POST['id'] ?? $_POST['prompt_id'] ?? 0);
            if (!$prompt_id) {
                throw new Exception('Prompt ID is required');
            }
            
            try {
                // This would require a favorites system to be implemented
                $result = $promptModel->toggleFavorite($prompt_id, $user['id']);
                
                echo json_encode([
                    'success' => true,
                    'is_favorite' => $result,
                    'message' => $result ? 'Added to favorites' : 'Removed from favorites'
                ]);
            } catch (Throwable $e) {
                throw new Exception('Favorites system requires model implementation');
            }
            break;
            
        case 'ping':
            // Simple ping endpoint for testing
            echo json_encode([
                'success' => true,
                'message' => 'pong',
                'timestamp' => time(),
                'user_id' => $user['id']
            ]);
            break;
            
        case 'get_server_info':
        case 'server_info':
            // Get server/API information
            echo json_encode([
                'success' => true,
                'server' => [
                    'version' => '2.0-enhanced',
                    'timestamp' => time(),
                    'timezone' => date_default_timezone_get(),
                    'php_version' => PHP_VERSION
                ],
                'api' => [
                    'endpoints' => [
                        'prompts' => ['get_prompts', 'create_prompt', 'edit_prompt', 'delete_prompt', 'search_prompts'],
                        'bookmarks' => ['get_bookmarks', 'create_bookmark', 'edit_bookmark', 'delete_bookmark', 'search_bookmarks'],
                        'categories' => ['get_categories', 'create_category', 'edit_category', 'delete_category'],
                        'user' => ['get_user_profile', 'update_user_profile'],
                        'utilities' => ['ping', 'server_info', 'fetch_url_metadata']
                    ]
                ]
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Throwable $e) {
    // Clear any buffered output that might contain HTML/errors
    ob_clean();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// It's a good idea to have a helper function for your JSON responses
function json_response($success, $message, $data = []) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
}

// Ensure clean output
ob_end_flush();
?>



