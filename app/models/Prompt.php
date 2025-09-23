<?php
class Prompt {
    private $db;
    private $table_name = "prompts";

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function create($title, $content, $description, $category_id, $user_id, $tags = '') {
        try {
            $query = "INSERT INTO " . $this->table_name . " 
                     (title, content, description, category_id, user_id, tags) 
                     VALUES (:title, :content, :description, :category_id, :user_id, :tags)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':content', $content);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':category_id', $category_id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':tags', $tags);

            if ($stmt->execute()) {
                return $this->db->lastInsertId();
            }
            return false;
        } catch(PDOException $exception) {
            error_log("Create prompt error: " . $exception->getMessage());
            return false;
        }
    }

    public function getByUserId($user_id, $limit = 20, $offset = 0) {
        try {
            $query = "SELECT p.*, c.name as category_name 
                     FROM " . $this->table_name . " p 
                     LEFT JOIN categories c ON p.category_id = c.id 
                     WHERE p.user_id = :user_id 
                     ORDER BY p.created_at DESC 
                     LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch(PDOException $exception) {
            error_log("Get prompts error: " . $exception->getMessage());
            return [];
        }
    }

    public function getByCategory($user_id, $category_id, $limit = 20, $offset = 0) {
        try {
            $query = "SELECT p.*, c.name as category_name 
                     FROM " . $this->table_name . " p 
                     LEFT JOIN categories c ON p.category_id = c.id 
                     WHERE p.user_id = :user_id AND p.category_id = :category_id 
                     ORDER BY p.created_at DESC 
                     LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':category_id', $category_id);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch(PDOException $exception) {
            error_log("Get prompts by category error: " . $exception->getMessage());
            return [];
        }
    }

    public function getById($id, $user_id) {
        try {
            $query = "SELECT p.*, c.name as category_name 
                     FROM " . $this->table_name . " p 
                     LEFT JOIN categories c ON p.category_id = c.id 
                     WHERE p.id = :id AND p.user_id = :user_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();

            return $stmt->fetch();
        } catch(PDOException $exception) {
            error_log("Get prompt error: " . $exception->getMessage());
            return null;
        }
    }

    public function getByIdForViewing($id, $user_id) {
        try {
            // First try to get as owner
            $prompt = $this->getById($id, $user_id);
            
            if ($prompt) {
                return $prompt;
            }
            
            // If not found as owner, check if it's shared with the user
            $query = "SELECT p.*, c.name as category_name, s.sharer_id, u.username as sharer_username
                     FROM " . $this->table_name . " p 
                     LEFT JOIN categories c ON p.category_id = c.id
                     JOIN shared_prompts s ON p.id = s.prompt_id
                     JOIN users u ON s.sharer_id = u.id
                     WHERE p.id = :id 
                     AND (s.recipient_id = :user_id OR s.shared_with_all = TRUE)
                     AND s.sharer_id != :user_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();

            return $stmt->fetch();
        } catch(PDOException $exception) {
            error_log("Get prompt for viewing error: " . $exception->getMessage());
            return null;
        }
    }

    public function update($id, $user_id, $title, $content, $description, $category_id, $tags = '') {
        try {
            $query = "UPDATE " . $this->table_name . " 
                     SET title = :title, content = :content, description = :description, 
                         category_id = :category_id, tags = :tags, updated_at = CURRENT_TIMESTAMP 
                     WHERE id = :id AND user_id = :user_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':content', $content);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':category_id', $category_id);
            $stmt->bindParam(':tags', $tags);

            return $stmt->execute();
        } catch(PDOException $exception) {
            error_log("Update prompt error: " . $exception->getMessage());
            return false;
        }
    }

    public function delete($id, $user_id) {
        try {
            $query = "DELETE FROM " . $this->table_name . " WHERE id = :id AND user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':user_id', $user_id);
            return $stmt->execute();
        } catch(PDOException $exception) {
            error_log("Delete prompt error: " . $exception->getMessage());
            return false;
        }
    }

    public function search($user_id, $search, $category_id = null, $limit = 20, $offset = 0) {
        try {
            $whereClause = "p.user_id = :user_id AND MATCH(p.title, p.content, p.description, p.tags) AGAINST(:search IN NATURAL LANGUAGE MODE)";
            $params = [':user_id' => $user_id, ':search' => $search];

            if ($category_id) {
                $whereClause .= " AND p.category_id = :category_id";
                $params[':category_id'] = $category_id;
            }

            $query = "SELECT p.*, c.name as category_name 
                     FROM " . $this->table_name . " p 
                     LEFT JOIN categories c ON p.category_id = c.id 
                     WHERE " . $whereClause . " 
                     ORDER BY p.created_at DESC 
                     LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch(PDOException $exception) {
            error_log("Search prompts error: " . $exception->getMessage());
            return [];
        }
    }

    public function toggleFavorite($id, $user_id) {
        try {
            $query = "UPDATE " . $this->table_name . " 
                     SET is_favorite = NOT is_favorite 
                     WHERE id = :id AND user_id = :user_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':user_id', $user_id);
            return $stmt->execute();
        } catch(PDOException $exception) {
            error_log("Toggle favorite error: " . $exception->getMessage());
            return false;
        }
    }

    public function incrementUsage($id, $user_id) {
        try {
            $query = "UPDATE " . $this->table_name . " 
                     SET usage_count = usage_count + 1 
                     WHERE id = :id AND user_id = :user_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':user_id', $user_id);
            return $stmt->execute();
        } catch(PDOException $exception) {
            error_log("Increment usage error: " . $exception->getMessage());
            return false;
        }
    }

    public function getCountByUserId($user_id) {
        try {
            $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " WHERE user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result['count'];
        } catch(PDOException $exception) {
            error_log("Get prompt count error: " . $exception->getMessage());
            return 0;
        }
    }
    
    // Chrome Extension Support Methods
    
    public function getByUserIdWithPagination($user_id, $limit = 20, $offset = 0, $category_id = null, $search = '') {
        try {
            $whereClause = "p.user_id = :user_id";
            $params = [':user_id' => $user_id];
            
            if ($category_id) {
                $whereClause .= " AND p.category_id = :category_id";
                $params[':category_id'] = $category_id;
            }
            
            if (!empty($search)) {
                $whereClause .= " AND (p.title LIKE :search OR p.content LIKE :search OR p.description LIKE :search OR p.tags LIKE :search)";
                $params[':search'] = '%' . $search . '%';
            }
            
            $query = "SELECT p.*, c.name as category_name 
                     FROM " . $this->table_name . " p 
                     LEFT JOIN categories c ON p.category_id = c.id 
                     WHERE " . $whereClause . " 
                     ORDER BY p.created_at DESC 
                     LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch(PDOException $exception) {
            error_log("Get prompts with pagination error: " . $exception->getMessage());
            return [];
        }
    }
    
    public function countByUserId($user_id, $category_id = null, $search = '') {
        try {
            $whereClause = "user_id = :user_id";
            $params = [':user_id' => $user_id];
            
            if ($category_id) {
                $whereClause .= " AND category_id = :category_id";
                $params[':category_id'] = $category_id;
            }
            
            if (!empty($search)) {
                $whereClause .= " AND (title LIKE :search OR content LIKE :search OR description LIKE :search OR tags LIKE :search)";
                $params[':search'] = '%' . $search . '%';
            }
            
            $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " WHERE " . $whereClause;
            
            $stmt = $this->db->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            
            $result = $stmt->fetch();
            return $result['count'];
        } catch(PDOException $exception) {
            error_log("Count prompts error: " . $exception->getMessage());
            return 0;
        }
    }
    
    public function searchByUser($user_id, $query, $category_id = null, $limit = 20) {
        try {
            $whereClause = "p.user_id = :user_id";
            $params = [':user_id' => $user_id];
            
            if ($category_id) {
                $whereClause .= " AND p.category_id = :category_id";
                $params[':category_id'] = $category_id;
            }
            
            // Use both MATCH AGAINST and LIKE for better search coverage
            $searchClause = "(";
            if (strlen($query) >= 3) {
                $searchClause .= "MATCH(p.title, p.content, p.description, p.tags) AGAINST(:search_match IN NATURAL LANGUAGE MODE) OR ";
                $params[':search_match'] = $query;
            }
            $searchClause .= "p.title LIKE :search_like OR p.content LIKE :search_like OR p.description LIKE :search_like OR p.tags LIKE :search_like)";
            $params[':search_like'] = '%' . $query . '%';
            
            $fullQuery = "SELECT p.*, c.name as category_name 
                         FROM " . $this->table_name . " p 
                         LEFT JOIN categories c ON p.category_id = c.id 
                         WHERE " . $whereClause . " AND " . $searchClause . " 
                         ORDER BY p.created_at DESC 
                         LIMIT :limit";
            
            $stmt = $this->db->prepare($fullQuery);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch(PDOException $exception) {
            error_log("Search prompts by user error: " . $exception->getMessage());
            return [];
        }
    }
    
    public function getFavoritesByUserId($user_id, $limit = 20, $offset = 0) {
        try {
            $query = "SELECT p.*, c.name as category_name 
                     FROM " . $this->table_name . " p 
                     LEFT JOIN categories c ON p.category_id = c.id 
                     WHERE p.user_id = :user_id AND p.is_favorite = 1 
                     ORDER BY p.created_at DESC 
                     LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch(PDOException $exception) {
            error_log("Get favorites error: " . $exception->getMessage());
            return [];
        }
    }
    
    public function share($prompt_id, $sharer_id, $recipient_id = null, $shared_with_all = false) {
        try {
            $query = "INSERT INTO shared_prompts (prompt_id, sharer_id, recipient_id, shared_with_all) 
                     VALUES (:prompt_id, :sharer_id, :recipient_id, :shared_with_all)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':prompt_id', $prompt_id);
            $stmt->bindParam(':sharer_id', $sharer_id);
            $stmt->bindParam(':recipient_id', $recipient_id);
            $stmt->bindParam(':shared_with_all', $shared_with_all, PDO::PARAM_BOOL);

            $result = $stmt->execute();
            
            // If sharing was successful, add 'shared' tag to prompt
            if ($result) {
                $this->addSharedTag($prompt_id, $sharer_id);
            }
            
            return $result;
        } catch(PDOException $exception) {
            error_log("Share prompt error: " . $exception->getMessage());
            return false;
        }
    }

    public function getSharedWithUser($user_id, $limit = 20, $offset = 0) {
        try {
            $query = "SELECT p.*, c.name as category_name, s.sharer_id, u.username as sharer_username
                     FROM prompts p
                     JOIN shared_prompts s ON p.id = s.prompt_id
                     JOIN users u ON s.sharer_id = u.id
                     LEFT JOIN categories c ON p.category_id = c.id
                     WHERE (s.recipient_id = :user_id OR s.shared_with_all = TRUE) 
                     AND s.sharer_id != :user_id
                     ORDER BY s.created_at DESC
                     LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch(PDOException $exception) {
            error_log("Get shared prompts error: " . $exception->getMessage());
            return [];
        }
    }
    
    public function getSharedByUser($user_id, $limit = 20, $offset = 0) {
        try {
            $query = "SELECT p.*, c.name as category_name, s.id as share_id, s.recipient_id, u.username as recipient_username
                     FROM prompts p
                     JOIN shared_prompts s ON p.id = s.prompt_id
                     LEFT JOIN users u ON s.recipient_id = u.id
                     LEFT JOIN categories c ON p.category_id = c.id
                     WHERE s.sharer_id = :user_id
                     ORDER BY s.created_at DESC
                     LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch(PDOException $exception) {
            error_log("Get shared by user error: " . $exception->getMessage());
            return [];
        }
    }
    
    public function unshare($prompt_id, $sharer_id, $recipient_id = null) {
        try {
            if ($recipient_id) {
                // Unshare from specific recipient
                $query = "DELETE FROM shared_prompts 
                         WHERE prompt_id = :prompt_id AND sharer_id = :sharer_id AND recipient_id = :recipient_id";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':prompt_id', $prompt_id);
                $stmt->bindParam(':sharer_id', $sharer_id);
                $stmt->bindParam(':recipient_id', $recipient_id);
            } else {
                // Unshare from all (remove all sharing records for this prompt by this sharer)
                $query = "DELETE FROM shared_prompts 
                         WHERE prompt_id = :prompt_id AND sharer_id = :sharer_id";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':prompt_id', $prompt_id);
                $stmt->bindParam(':sharer_id', $sharer_id);
            }
            
            $result = $stmt->execute();
            
            // If unsharing was successful, remove 'shared' tag from prompt
            if ($result) {
                $this->removeSharedTag($prompt_id, $sharer_id);
            }
            
            return $result;
        } catch(PDOException $exception) {
            error_log("Unshare prompt error: " . $exception->getMessage());
            return false;
        }
    }
    
    public function saveSharedPrompt($original_prompt_id, $new_owner_id) {
        try {
            // First get the original prompt data with category name
            $query = "SELECT p.title, p.content, p.description, p.category_id, p.tags, c.name as category_name
                     FROM prompts p 
                     LEFT JOIN categories c ON p.category_id = c.id
                     WHERE p.id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $original_prompt_id);
            $stmt->execute();
            $original = $stmt->fetch();
            
            if (!$original) {
                return false;
            }
            
            // Check if user already has a prompt with the same title and content
            $check_query = "SELECT id FROM prompts 
                           WHERE user_id = :user_id AND title = :title AND content = :content";
            $check_stmt = $this->db->prepare($check_query);
            $check_stmt->bindParam(':user_id', $new_owner_id);
            $check_stmt->bindParam(':title', $original['title']);
            $check_stmt->bindParam(':content', $original['content']);
            $check_stmt->execute();
            
            if ($check_stmt->fetch()) {
                // User already has this prompt
                return false;
            }
            
            // Handle category creation/mapping
            $new_category_id = null;
            if ($original['category_name']) {
                $new_category_id = $this->createOrGetCategory($original['category_name'], $new_owner_id);
            }
            
            // Process tags - remove 'shared' tag and clean up
            $processed_tags = $this->processTagsForCopy($original['tags']);
            
            // Create a new prompt for the user with the shared content
            $insert_query = "INSERT INTO prompts (title, content, description, category_id, user_id, tags) 
                           VALUES (:title, :content, :description, :category_id, :user_id, :tags)";
            $insert_stmt = $this->db->prepare($insert_query);
            $insert_stmt->bindParam(':title', $original['title']);
            $insert_stmt->bindParam(':content', $original['content']);
            $insert_stmt->bindParam(':description', $original['description']);
            $insert_stmt->bindParam(':category_id', $new_category_id);
            $insert_stmt->bindParam(':user_id', $new_owner_id);
            $insert_stmt->bindParam(':tags', $processed_tags);
            
            if ($insert_stmt->execute()) {
                return $this->db->lastInsertId();
            }
            return false;
        } catch(PDOException $exception) {
            error_log("Save shared prompt error: " . $exception->getMessage());
            return false;
        }
    }
    
    public function hasUserSavedSharedPrompt($original_prompt_id, $user_id) {
        try {
            // Get the original prompt data
            $query = "SELECT title, content FROM prompts WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $original_prompt_id);
            $stmt->execute();
            $original = $stmt->fetch();
            
            if (!$original) {
                return false;
            }
            
            // Check if user has a prompt with the same title and content
            $check_query = "SELECT id FROM prompts 
                           WHERE user_id = :user_id AND title = :title AND content = :content";
            $check_stmt = $this->db->prepare($check_query);
            $check_stmt->bindParam(':user_id', $user_id);
            $check_stmt->bindParam(':title', $original['title']);
            $check_stmt->bindParam(':content', $original['content']);
            $check_stmt->execute();
            
            return $check_stmt->fetch() !== false;
        } catch(PDOException $exception) {
            error_log("Check saved shared prompt error: " . $exception->getMessage());
            return false;
        }
    }
    
    public function hasUserSharedPrompt($prompt_id, $user_id) {
        try {
            $query = "SELECT COUNT(*) as count FROM shared_prompts 
                     WHERE prompt_id = :prompt_id AND sharer_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':prompt_id', $prompt_id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $result = $stmt->fetch();
            
            return $result['count'] > 0;
        } catch(PDOException $exception) {
            error_log("Check user shared prompt error: " . $exception->getMessage());
            return false;
        }
    }
    
    private function addSharedTag($prompt_id, $user_id) {
        try {
            // Get current tags
            $query = "SELECT tags FROM prompts WHERE id = :id AND user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $prompt_id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $result = $stmt->fetch();
            
            if ($result) {
                $current_tags = $result['tags'] ? explode(',', $result['tags']) : [];
                $current_tags = array_map('trim', $current_tags);
                
                // Add 'shared' tag if not already present
                if (!in_array('shared', $current_tags)) {
                    $current_tags[] = 'shared';
                    $new_tags = implode(', ', $current_tags);
                    
                    // Update prompt with new tags
                    $update_query = "UPDATE prompts SET tags = :tags WHERE id = :id AND user_id = :user_id";
                    $update_stmt = $this->db->prepare($update_query);
                    $update_stmt->bindParam(':tags', $new_tags);
                    $update_stmt->bindParam(':id', $prompt_id);
                    $update_stmt->bindParam(':user_id', $user_id);
                    $update_stmt->execute();
                }
            }
        } catch(PDOException $exception) {
            error_log("Add shared tag error: " . $exception->getMessage());
        }
    }
    
    private function removeSharedTag($prompt_id, $user_id) {
        try {
            // Check if there are any remaining shares for this prompt
            $check_query = "SELECT COUNT(*) as count FROM shared_prompts WHERE prompt_id = :prompt_id AND sharer_id = :user_id";
            $check_stmt = $this->db->prepare($check_query);
            $check_stmt->bindParam(':prompt_id', $prompt_id);
            $check_stmt->bindParam(':user_id', $user_id);
            $check_stmt->execute();
            $check_result = $check_stmt->fetch();
            
            // Only remove 'shared' tag if no more shares exist
            if ($check_result['count'] == 0) {
                $query = "SELECT tags FROM prompts WHERE id = :id AND user_id = :user_id";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':id', $prompt_id);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->execute();
                $result = $stmt->fetch();
                
                if ($result && $result['tags']) {
                    $current_tags = explode(',', $result['tags']);
                    $current_tags = array_map('trim', $current_tags);
                    
                    // Remove 'shared' tag
                    $current_tags = array_filter($current_tags, function($tag) {
                        return $tag !== 'shared';
                    });
                    
                    $new_tags = implode(', ', $current_tags);
                    
                    // Update prompt with new tags
                    $update_query = "UPDATE prompts SET tags = :tags WHERE id = :id AND user_id = :user_id";
                    $update_stmt = $this->db->prepare($update_query);
                    $update_stmt->bindParam(':tags', $new_tags);
                    $update_stmt->bindParam(':id', $prompt_id);
                    $update_stmt->bindParam(':user_id', $user_id);
                    $update_stmt->execute();
                }
            }
        } catch(PDOException $exception) {
            error_log("Remove shared tag error: " . $exception->getMessage());
        }
    }
    
    private function createOrGetCategory($category_name, $user_id) {
        try {
            // Check if category already exists for this user
            $check_query = "SELECT id FROM categories WHERE name = :name AND user_id = :user_id";
            $check_stmt = $this->db->prepare($check_query);
            $check_stmt->bindParam(':name', $category_name);
            $check_stmt->bindParam(':user_id', $user_id);
            $check_stmt->execute();
            $existing = $check_stmt->fetch();
            
            if ($existing) {
                return $existing['id'];
            }
            
            // Create new category
            $create_query = "INSERT INTO categories (name, user_id) VALUES (:name, :user_id)";
            $create_stmt = $this->db->prepare($create_query);
            $create_stmt->bindParam(':name', $category_name);
            $create_stmt->bindParam(':user_id', $user_id);
            
            if ($create_stmt->execute()) {
                return $this->db->lastInsertId();
            }
            
            return null;
        } catch(PDOException $exception) {
            error_log("Create or get category error: " . $exception->getMessage());
            return null;
        }
    }
    
    private function processTagsForCopy($original_tags) {
        if (!$original_tags) {
            return '';
        }
        
        $tags = explode(',', $original_tags);
        $tags = array_map('trim', $tags);
        
        // Remove 'shared' tag from copied prompt
        $tags = array_filter($tags, function($tag) {
            return strtolower($tag) !== 'shared';
        });
        
        return implode(', ', $tags);
    }
    
    /**
     * Get backup statistics for a user
     */
    public function getBackupStats($user_id) {
        try {
            $stats = [];
            
            // Total prompts
            $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " WHERE user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['total_prompts'] = $result['count'];
            
            // Favorite prompts
            $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " WHERE user_id = :user_id AND is_favorite = 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['favorite_prompts'] = $result['count'];
            
            // Categories
            $query = "SELECT COUNT(*) as count FROM categories WHERE user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['categories'] = $result['count'];
            
            // Most recent prompt date
            $query = "SELECT MAX(created_at) as latest FROM " . $this->table_name . " WHERE user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['latest_prompt'] = $result['latest'];
            
            return $stats;
        } catch(PDOException $exception) {
            error_log("Get backup stats error: " . $exception->getMessage());
            return ['total_prompts' => 0, 'favorite_prompts' => 0, 'categories' => 0, 'latest_prompt' => null];
        }
    }
    
    /**
     * Batch create prompts from backup data
     */
    public function batchCreate($prompts, $user_id, $category_mapping = []) {
        try {
            $this->db->beginTransaction();
            
            $created_count = 0;
            $errors = [];
            
            foreach ($prompts as $prompt_data) {
                // Map category ID if exists
                $category_id = null;
                if (isset($prompt_data['category_id']) && isset($category_mapping[$prompt_data['category_id']])) {
                    $category_id = $category_mapping[$prompt_data['category_id']];
                }
                
                // Check for duplicates
                $check_query = "SELECT id FROM " . $this->table_name . " WHERE title = :title AND content = :content AND user_id = :user_id";
                $check_stmt = $this->db->prepare($check_query);
                $check_stmt->bindParam(':title', $prompt_data['title']);
                $check_stmt->bindParam(':content', $prompt_data['content']);
                $check_stmt->bindParam(':user_id', $user_id);
                $check_stmt->execute();
                
                if ($check_stmt->rowCount() === 0) {
                    $result = $this->create(
                        $prompt_data['title'],
                        $prompt_data['content'],
                        $prompt_data['description'] ?? '',
                        $category_id,
                        $user_id,
                        $prompt_data['tags'] ?? ''
                    );
                    
                    if ($result) {
                        $created_count++;
                        
                        // Set favorite status if needed
                        if (!empty($prompt_data['is_favorite'])) {
                            $this->toggleFavorite($result, $user_id);
                        }
                    } else {
                        $errors[] = "Failed to create prompt: " . $prompt_data['title'];
                    }
                } else {
                    $errors[] = "Duplicate prompt skipped: " . $prompt_data['title'];
                }
            }
            
            $this->db->commit();
            return ['created' => $created_count, 'errors' => $errors];
            
        } catch(Exception $exception) {
            $this->db->rollback();
            error_log("Batch create prompts error: " . $exception->getMessage());
            return ['created' => 0, 'errors' => ['Database error: ' . $exception->getMessage()]];
        }
    }
}
?>