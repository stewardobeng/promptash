<?php
class BackupHelper {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    /**
     * Export user's prompts and categories as JSON
     */
    public function exportUserData($user_id, $format = 'json') {
        try {
            // Get user info (excluding sensitive data)
            $userQuery = "SELECT username, email, first_name, last_name FROM users WHERE id = :user_id";
            $userStmt = $this->db->prepare($userQuery);
            $userStmt->bindParam(':user_id', $user_id);
            $userStmt->execute();
            $user = $userStmt->fetch();

            // Get user's categories
            $categoriesQuery = "SELECT id, name, description, created_at FROM categories WHERE user_id = :user_id ORDER BY name";
            $categoriesStmt = $this->db->prepare($categoriesQuery);
            $categoriesStmt->bindParam(':user_id', $user_id);
            $categoriesStmt->execute();
            $categories = $categoriesStmt->fetchAll();

            // Get user's prompts
            $promptsQuery = "SELECT p.*, c.name as category_name 
                           FROM prompts p 
                           LEFT JOIN categories c ON p.category_id = c.id 
                           WHERE p.user_id = :user_id 
                           ORDER BY p.created_at DESC";
            $promptsStmt = $this->db->prepare($promptsQuery);
            $promptsStmt->bindParam(':user_id', $user_id);
            $promptsStmt->execute();
            $prompts = $promptsStmt->fetchAll();
            
            // Get user's bookmarks
            $bookmarksQuery = "SELECT * FROM bookmarks WHERE user_id = :user_id ORDER BY created_at DESC";
            $bookmarksStmt = $this->db->prepare($bookmarksQuery);
            $bookmarksStmt->bindParam(':user_id', $user_id);
            $bookmarksStmt->execute();
            $bookmarks = $bookmarksStmt->fetchAll();

            // ** NEW ** Get user's notes, documents, and videos
            $notes = $this->getAllTableDataForUser('notes', $user_id);
            $documents = $this->getAllTableDataForUser('documents', $user_id);
            $videos = $this->getAllTableDataForUser('videos', $user_id);

            $data = [
                'export_info' => [
                    'version' => '1.2', // Updated version
                    'export_date' => date('Y-m-d H:i:s'),
                    'type' => 'user_backup',
                    'user' => $user
                ],
                'categories' => $categories,
                'prompts' => $prompts,
                'bookmarks' => $bookmarks,
                'notes' => $notes,
                'documents' => $documents,
                'videos' => $videos
            ];

            if ($format === 'json') {
                return json_encode($data, JSON_PRETTY_PRINT);
            } elseif ($format === 'txt') {
                return $this->convertToText($data);
            }

            return false;
        } catch(PDOException $exception) {
            error_log("Export user data error: " . $exception->getMessage());
            return false;
        }
    }

    /**
     * Import user data from JSON
     */
    public function importUserData($user_id, $jsonData) {
        try {
            $data = json_decode($jsonData, true);
            
            if (!$data || !isset($data['export_info']) || $data['export_info']['type'] !== 'user_backup') {
                return ['success' => false, 'message' => 'Invalid backup file format'];
            }

            $this->db->beginTransaction();

            $stats = [
                'categories_imported' => 0, 
                'prompts_imported' => 0, 
                'bookmarks_imported' => 0,
                'notes_imported' => 0,
                'documents_imported' => 0,
                'videos_imported' => 0,
                'errors' => []
            ];
            $categoryMapping = []; // Old ID to new ID mapping

            // Import categories
            if (isset($data['categories']) && is_array($data['categories'])) {
                foreach ($data['categories'] as $category) {
                    $oldCategoryId = $category['id'];
                    
                    // Check if category already exists
                    $existsQuery = "SELECT id FROM categories WHERE name = :name AND user_id = :user_id";
                    $existsStmt = $this->db->prepare($existsQuery);
                    $existsStmt->bindParam(':name', $category['name']);
                    $existsStmt->bindParam(':user_id', $user_id);
                    $existsStmt->execute();
                    
                    if ($existsStmt->rowCount() > 0) {
                        $existingCategory = $existsStmt->fetch();
                        $categoryMapping[$oldCategoryId] = $existingCategory['id'];
                    } else {
                        // Create new category
                        $insertQuery = "INSERT INTO categories (name, description, user_id) VALUES (:name, :description, :user_id)";
                        $insertStmt = $this->db->prepare($insertQuery);
                        $insertStmt->bindParam(':name', $category['name']);
                        $insertStmt->bindParam(':description', $category['description']);
                        $insertStmt->bindParam(':user_id', $user_id);
                        
                        if ($insertStmt->execute()) {
                            $categoryMapping[$oldCategoryId] = $this->db->lastInsertId();
                            $stats['categories_imported']++;
                        }
                    }
                }
            }

            // Import prompts
            if (isset($data['prompts']) && is_array($data['prompts'])) {
                foreach ($data['prompts'] as $prompt) {
                    // Map category ID if exists
                    $categoryId = null;
                    if ($prompt['category_id'] && isset($categoryMapping[$prompt['category_id']])) {
                        $categoryId = $categoryMapping[$prompt['category_id']];
                    }

                    // Check if prompt already exists (by title and content)
                    $existsQuery = "SELECT id FROM prompts WHERE title = :title AND content = :content AND user_id = :user_id";
                    $existsStmt = $this->db->prepare($existsQuery);
                    $existsStmt->bindParam(':title', $prompt['title']);
                    $existsStmt->bindParam(':content', $prompt['content']);
                    $existsStmt->bindParam(':user_id', $user_id);
                    $existsStmt->execute();
                    
                    if ($existsStmt->rowCount() === 0) {
                        // Create new prompt
                        $insertQuery = "INSERT INTO prompts (title, content, description, category_id, user_id, tags, is_favorite) 
                                      VALUES (:title, :content, :description, :category_id, :user_id, :tags, :is_favorite)";
                        $insertStmt = $this->db->prepare($insertQuery);
                        $insertStmt->bindParam(':title', $prompt['title']);
                        $insertStmt->bindParam(':content', $prompt['content']);
                        $insertStmt->bindParam(':description', $prompt['description']);
                        $insertStmt->bindParam(':category_id', $categoryId);
                        $insertStmt->bindParam(':user_id', $user_id);
                        $insertStmt->bindParam(':tags', $prompt['tags']);
                        $insertStmt->bindParam(':is_favorite', $prompt['is_favorite'], PDO::PARAM_BOOL);
                        
                        if ($insertStmt->execute()) {
                            $stats['prompts_imported']++;
                        }
                    }
                }
            }
            
            // Import bookmarks
            if (isset($data['bookmarks']) && is_array($data['bookmarks'])) {
                foreach ($data['bookmarks'] as $bookmark) {
                    // Check if bookmark already exists (by URL)
                    $existsQuery = "SELECT id FROM bookmarks WHERE url = :url AND user_id = :user_id";
                    $existsStmt = $this->db->prepare($existsQuery);
                    $existsStmt->bindParam(':url', $bookmark['url']);
                    $existsStmt->bindParam(':user_id', $user_id);
                    $existsStmt->execute();
                    
                    if ($existsStmt->rowCount() === 0) {
                        // Create new bookmark
                        $insertQuery = "INSERT INTO bookmarks (user_id, url, title, description, image, tags) 
                                      VALUES (:user_id, :url, :title, :description, :image, :tags)";
                        $insertStmt = $this->db->prepare($insertQuery);
                        $insertStmt->bindParam(':user_id', $user_id);
                        $insertStmt->bindParam(':url', $bookmark['url']);
                        $insertStmt->bindParam(':title', $bookmark['title']);
                        $insertStmt->bindParam(':description', $bookmark['description']);
                        $insertStmt->bindParam(':image', $bookmark['image']);
                        $insertStmt->bindParam(':tags', $bookmark['tags']);
                        
                        if ($insertStmt->execute()) {
                            $stats['bookmarks_imported']++;
                        }
                    }
                }
            }

            // ** NEW ** Import notes, documents, and videos
            $this->importTableData('notes', $data, $user_id, $stats);
            $this->importTableData('documents', $data, $user_id, $stats);
            $this->importTableData('videos', $data, $user_id, $stats);

            $this->db->commit();
            return ['success' => true, 'stats' => $stats];

        } catch(Exception $exception) {
            $this->db->rollback();
            error_log("Import user data error: " . $exception->getMessage());
            return ['success' => false, 'message' => 'Import failed: ' . $exception->getMessage()];
        }
    }

    /**
     * Export complete application data (admin only)
     */
    public function exportApplicationData($format = 'json') {
        try {
            // Get all tables data
            $data = [
                'export_info' => [
                    'version' => '1.2',
                    'export_date' => date('Y-m-d H:i:s'),
                    'type' => 'full_backup',
                    'app_version' => APP_VERSION
                ],
                'users' => $this->getAllTableData('users', ['password']), // Exclude passwords
                'categories' => $this->getAllTableData('categories'),
                'prompts' => $this->getAllTableData('prompts'),
                'bookmarks' => $this->getAllTableData('bookmarks'),
                'notes' => $this->getAllTableData('notes'),
                'documents' => $this->getAllTableData('documents'),
                'videos' => $this->getAllTableData('videos'),
                'shared_prompts' => $this->getAllTableData('shared_prompts'),
                'app_settings' => $this->getAllTableData('app_settings')
            ];

            if ($format === 'json') {
                return json_encode($data, JSON_PRETTY_PRINT);
            }

            return false;
        } catch(PDOException $exception) {
            error_log("Export application data error: " . $exception->getMessage());
            return false;
        }
    }

    /**
     * Import complete application data (admin only)
     */
    public function importApplicationData($jsonData) {
        try {
            $data = json_decode($jsonData, true);
            
            if (!$data || !isset($data['export_info']) || $data['export_info']['type'] !== 'full_backup') {
                return ['success' => false, 'message' => 'Invalid full backup file format'];
            }

            $this->db->beginTransaction();

            // Clear existing data (WARNING: This is destructive)
            $tables = ['shared_prompts', 'prompts', 'categories', 'bookmarks', 'notes', 'documents', 'videos', 'users', 'app_settings'];
            foreach ($tables as $table) {
                $this->db->exec("DELETE FROM $table");
                $this->db->exec("ALTER TABLE $table AUTO_INCREMENT = 1");
            }

            $stats = [];

            // Import data in correct order (respecting foreign keys)
            $importOrder = ['users', 'categories', 'prompts', 'bookmarks', 'notes', 'documents', 'videos', 'shared_prompts', 'app_settings'];
            
            foreach ($importOrder as $table) {
                if (isset($data[$table]) && is_array($data[$table])) {
                    $count = 0;
                    foreach ($data[$table] as $row) {
                        if ($this->insertTableRow($table, $row)) {
                            $count++;
                        }
                    }
                    $stats[$table] = $count;
                }
            }

            $this->db->commit();
            return ['success' => true, 'stats' => $stats];

        } catch(Exception $exception) {
            $this->db->rollback();
            error_log("Import application data error: " . $exception->getMessage());
            return ['success' => false, 'message' => 'Import failed: ' . $exception->getMessage()];
        }
    }

    /**
     * Get all data from a table
     */
    private function getAllTableData($table, $excludeColumns = []) {
        try {
            $query = "SELECT * FROM $table";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $data = $stmt->fetchAll();

            // Remove excluded columns
            if (!empty($excludeColumns)) {
                foreach ($data as &$row) {
                    foreach ($excludeColumns as $column) {
                        unset($row[$column]);
                    }
                }
            }

            return $data;
        } catch(PDOException $exception) {
            error_log("Get table data error for $table: " . $exception->getMessage());
            return [];
        }
    }

    /**
     * Insert a row into a table
     */
    private function insertTableRow($table, $data) {
        try {
            $columns = array_keys($data);
            $placeholders = ':' . implode(', :', $columns);
            
            $query = "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES ($placeholders)";
            $stmt = $this->db->prepare($query);
            
            foreach ($data as $column => $value) {
                $stmt->bindValue(":$column", $value);
            }
            
            return $stmt->execute();
        } catch(PDOException $exception) {
            error_log("Insert table row error for $table: " . $exception->getMessage());
            return false;
        }
    }

    /**
     * Convert data to text format
     */
    private function convertToText($data) {
        $text = "PROMPTASH BACKUP\n";
        $text .= "=====================\n\n";
        $text .= "Export Date: " . $data['export_info']['export_date'] . "\n";
        $text .= "User: " . $data['export_info']['user']['first_name'] . " " . $data['export_info']['user']['last_name'] . "\n\n";

        if (!empty($data['categories'])) {
            $text .= "CATEGORIES\n";
            $text .= "----------\n";
            foreach ($data['categories'] as $category) {
                $text .= "- " . $category['name'];
                if ($category['description']) {
                    $text .= ": " . $category['description'];
                }
                $text .= "\n";
            }
            $text .= "\n";
        }

        if (!empty($data['prompts'])) {
            $text .= "PROMPTS\n";
            $text .= "-------\n\n";
            foreach ($data['prompts'] as $i => $prompt) {
                $text .= ($i + 1) . ". " . $prompt['title'] . "\n";
                if ($prompt['category_name']) {
                    $text .= "   Category: " . $prompt['category_name'] . "\n";
                }
                if ($prompt['description']) {
                    $text .= "   Description: " . $prompt['description'] . "\n";
                }
                if ($prompt['tags']) {
                    $text .= "   Tags: " . $prompt['tags'] . "\n";
                }
                $text .= "   Content:\n";
                $text .= "   " . str_replace("\n", "\n   ", $prompt['content']) . "\n\n";
                $text .= "   ---\n\n";
            }
        }
        
        if (!empty($data['bookmarks'])) {
            $text .= "BOOKMARKS\n";
            $text .= "---------\n\n";
            foreach ($data['bookmarks'] as $i => $bookmark) {
                $text .= ($i + 1) . ". " . $bookmark['title'] . "\n";
                $text .= "   URL: " . $bookmark['url'] . "\n";
                if ($bookmark['description']) {
                    $text .= "   Description: " . $bookmark['description'] . "\n";
                }
                if ($bookmark['tags']) {
                    $text .= "   Tags: " . $bookmark['tags'] . "\n";
                }
                $text .= "   ---\n\n";
            }
        }

        // ** NEW ** Add new sections to text export
        if (!empty($data['notes'])) {
            $text .= "NOTES\n";
            $text .= "-----\n\n";
            foreach($data['notes'] as $note) {
                $text .= "Title: " . $note['title'] . "\n";
                $text .= "Content: " . $note['content'] . "\n\n";
            }
        }

        if (!empty($data['videos'])) {
            $text .= "VIDEOS\n";
            $text .= "------\n\n";
            foreach($data['videos'] as $video) {
                $text .= "Title: " . $video['title'] . "\n";
                $text .= "URL: " . $video['url'] . "\n\n";
            }
        }

        return $text;
    }

    /**
     * Generate filename for backup
     */
    public function generateBackupFilename($user_id, $type = 'user', $format = 'json') {
        $timestamp = date('Y-m-d_H-i-s');
        if ($type === 'user') {
            return "promptash_backup_user_{$user_id}_{$timestamp}.$format";
        } else {
            return "promptash_backup_full_{$timestamp}.$format";
        }
    }

    /**
     * Validate backup file
     */
    public function validateBackupFile($jsonData) {
        $data = json_decode($jsonData, true);
        
        if (!$data) {
            return ['valid' => false, 'message' => 'Invalid JSON format'];
        }

        if (!isset($data['export_info'])) {
            return ['valid' => false, 'message' => 'Missing export information'];
        }

        $type = $data['export_info']['type'] ?? '';
        if (!in_array($type, ['user_backup', 'full_backup'])) {
            return ['valid' => false, 'message' => 'Unknown backup type'];
        }

        return ['valid' => true, 'type' => $type, 'info' => $data['export_info']];
    }
    
    // ** NEW ** Helper functions for backup/restore
    private function getAllTableDataForUser($table, $user_id) {
        try {
            $query = "SELECT * FROM $table WHERE user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching data for table $table and user $user_id: " . $e->getMessage());
            return [];
        }
    }

    private function importTableData($tableName, $data, $user_id, &$stats) {
        if (isset($data[$tableName]) && is_array($data[$tableName])) {
            foreach ($data[$tableName] as $row) {
                unset($row['id']); // Remove old ID
                $row['user_id'] = $user_id; // Set new user ID

                $columns = array_keys($row);
                $placeholders = ':' . implode(', :', $columns);
                
                $query = "INSERT INTO $tableName (" . implode(', ', $columns) . ") VALUES ($placeholders)";
                $stmt = $this->db->prepare($query);
                
                if ($stmt->execute($row)) {
                    $stats[$tableName . '_imported']++;
                } else {
                    $stats['errors'][] = "Failed to import a row into $tableName.";
                }
            }
        }
    }
}
?>
