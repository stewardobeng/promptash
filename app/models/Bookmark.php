<?php
class Bookmark {
    private $db;
    private $table_name = "bookmarks";

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function create($user_id, $url, $title, $description, $image, $tags) {
        try {
            $query = "INSERT INTO " . $this->table_name . "
                     (user_id, url, title, description, image, tags)
                     VALUES (:user_id, :url, :title, :description, :image, :tags)";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':url', $url);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':image', $image);
            $stmt->bindParam(':tags', $tags);

            if ($stmt->execute()) {
                return $this->db->lastInsertId();
            }
            return false;
        } catch(PDOException $exception) {
            error_log("Create bookmark error: " . $exception->getMessage());
            return false;
        }
    }

    public function getByUserId($user_id, $limit = 20, $offset = 0) {
        try {
            $query = "SELECT * FROM " . $this->table_name . "
                     WHERE user_id = :user_id
                     ORDER BY created_at DESC
                     LIMIT :limit OFFSET :offset";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch(PDOException $exception) {
            error_log("Get bookmarks error: " . $exception->getMessage());
            return [];
        }
    }

    public function getById($id, $user_id) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id AND user_id = :user_id";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();

            return $stmt->fetch();
        } catch(PDOException $exception) {
            error_log("Get bookmark error: " . $exception->getMessage());
            return null;
        }
    }

    public function update($id, $user_id, $title, $description, $image, $tags) {
        try {
            $query = "UPDATE " . $this->table_name . "
                     SET title = :title, description = :description, image = :image, tags = :tags, updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id AND user_id = :user_id";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':image', $image);
            $stmt->bindParam(':tags', $tags);

            return $stmt->execute();
        } catch(PDOException $exception) {
            error_log("Update bookmark error: " . $exception->getMessage());
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
            error_log("Delete bookmark error: " . $exception->getMessage());
            return false;
        }
    }
    
    public function search($user_id, $search, $limit = 20, $offset = 0) {
        try {
            $query = "SELECT * FROM " . $this->table_name . "
                     WHERE user_id = :user_id AND MATCH(title, description, tags) AGAINST(:search IN NATURAL LANGUAGE MODE)
                     ORDER BY created_at DESC
                     LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':search', $search);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch(PDOException $exception) {
            error_log("Search bookmarks error: " . $exception->getMessage());
            return [];
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
            error_log("Get bookmark count error: " . $exception->getMessage());
            return 0;
        }
    }

    public function urlExistsForUser($user_id, $url) {
        try {
            $query = "SELECT id FROM " . $this->table_name . " WHERE user_id = :user_id AND url = :url";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':url', $url);
            $stmt->execute();
            return $stmt->fetch() !== false;
        } catch (PDOException $exception) {
            error_log("URL exists check error: " . $exception->getMessage());
            return true; // Fail safe to prevent duplicates on error
        }
    }

    public function share($bookmark_id, $sharer_id, $recipient_id = null, $shared_with_all = false) {
        try {
            $query = "INSERT INTO shared_bookmarks (bookmark_id, sharer_id, recipient_id, shared_with_all)
                     VALUES (:bookmark_id, :sharer_id, :recipient_id, :shared_with_all)";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':bookmark_id', $bookmark_id);
            $stmt->bindParam(':sharer_id', $sharer_id);
            $stmt->bindParam(':recipient_id', $recipient_id);
            $stmt->bindParam(':shared_with_all', $shared_with_all, PDO::PARAM_BOOL);

            return $stmt->execute();
        } catch(PDOException $exception) {
            error_log("Share bookmark error: " . $exception->getMessage());
            return false;
        }
    }

    public function getSharedWithUser($user_id, $limit = 20, $offset = 0) {
        try {
            $query = "SELECT b.*, s.sharer_id, u.username as sharer_username
                     FROM bookmarks b
                     JOIN shared_bookmarks s ON b.id = s.bookmark_id
                     JOIN users u ON s.sharer_id = u.id
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
            error_log("Get shared bookmarks error: " . $exception->getMessage());
            return [];
        }
    }

    public function getSharedByUser($user_id, $limit = 20, $offset = 0) {
        try {
            $query = "SELECT b.*, s.id as share_id, s.recipient_id, u.username as recipient_username
                     FROM bookmarks b
                     JOIN shared_bookmarks s ON b.id = s.bookmark_id
                     LEFT JOIN users u ON s.recipient_id = u.id
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
    
    public function hasUserSharedBookmark($bookmark_id, $user_id) {
        try {
            $query = "SELECT COUNT(*) as count FROM shared_bookmarks
                     WHERE bookmark_id = :bookmark_id AND sharer_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':bookmark_id', $bookmark_id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $result = $stmt->fetch();
            
            return $result['count'] > 0;
        } catch(PDOException $exception) {
            error_log("Check user shared bookmark error: " . $exception->getMessage());
            return false;
        }
    }

    public function unshare($bookmark_id, $sharer_id, $recipient_id = null) {
        try {
            if ($recipient_id) {
                // Unshare from specific recipient
                $query = "DELETE FROM shared_bookmarks
                         WHERE bookmark_id = :bookmark_id AND sharer_id = :sharer_id AND recipient_id = :recipient_id";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':bookmark_id', $bookmark_id);
                $stmt->bindParam(':sharer_id', $sharer_id);
                $stmt->bindParam(':recipient_id', $recipient_id);
            } else {
                // Unshare from all (remove all sharing records for this bookmark by this sharer)
                $query = "DELETE FROM shared_bookmarks
                         WHERE bookmark_id = :bookmark_id AND sharer_id = :sharer_id";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':bookmark_id', $bookmark_id);
                $stmt->bindParam(':sharer_id', $sharer_id);
            }
            
            return $stmt->execute();
        } catch(PDOException $exception) {
            error_log("Unshare bookmark error: " . $exception->getMessage());
            return false;
        }
    }

    public function saveSharedBookmark($original_bookmark_id, $new_owner_id) {
        try {
            $query = "SELECT * FROM bookmarks WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $original_bookmark_id);
            $stmt->execute();
            $original = $stmt->fetch();
            
            if (!$original) {
                return false;
            }

            return $this->create(
                $new_owner_id,
                $original['url'],
                $original['title'],
                $original['description'],
                $original['image'],
                $original['tags']
            );
        } catch(PDOException $exception) {
            error_log("Save shared bookmark error: " . $exception->getMessage());
            return false;
        }
    }

    public function hasUserSavedSharedBookmark($original_bookmark_id, $user_id) {
        try {
            $query = "SELECT url FROM bookmarks WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $original_bookmark_id);
            $stmt->execute();
            $original = $stmt->fetch();
            
            if (!$original) {
                return false;
            }
            
            $check_query = "SELECT id FROM bookmarks WHERE user_id = :user_id AND url = :url";
            $check_stmt = $this->db->prepare($check_query);
            $check_stmt->bindParam(':user_id', $user_id);
            $check_stmt->bindParam(':url', $original['url']);
            $check_stmt->execute();
            
            return $check_stmt->fetch() !== false;
        } catch(PDOException $exception) {
            error_log("Check saved shared bookmark error: " . $exception->getMessage());
            return false;
        }
    }
    
    // Chrome Extension Support Methods
    
    public function getByUserIdWithPagination($user_id, $limit = 20, $offset = 0, $search = '') {
        try {
            $whereClause = "user_id = :user_id";
            $params = [':user_id' => $user_id];
            
            if (!empty($search)) {
                $whereClause .= " AND (title LIKE :search OR description LIKE :search OR tags LIKE :search OR url LIKE :search)";
                $params[':search'] = '%' . $search . '%';
            }
            
            $query = "SELECT * FROM " . $this->table_name . " 
                     WHERE " . $whereClause . " 
                     ORDER BY created_at DESC 
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
            error_log("Get bookmarks with pagination error: " . $exception->getMessage());
            return [];
        }
    }
    
    public function countByUserId($user_id, $search = '') {
        try {
            $whereClause = "user_id = :user_id";
            $params = [':user_id' => $user_id];
            
            if (!empty($search)) {
                $whereClause .= " AND (title LIKE :search OR description LIKE :search OR tags LIKE :search OR url LIKE :search)";
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
            error_log("Count bookmarks error: " . $exception->getMessage());
            return 0;
        }
    }
    
    public function searchByUser($user_id, $query, $limit = 20) {
        try {
            // Use both MATCH AGAINST and LIKE for better search coverage
            $searchClause = "(";
            $params = [':user_id' => $user_id];
            
            if (strlen($query) >= 3) {
                $searchClause .= "MATCH(title, description, tags) AGAINST(:search_match IN NATURAL LANGUAGE MODE) OR ";
                $params[':search_match'] = $query;
            }
            $searchClause .= "title LIKE :search_like OR description LIKE :search_like OR tags LIKE :search_like OR url LIKE :search_like)";
            $params[':search_like'] = '%' . $query . '%';
            
            $fullQuery = "SELECT * FROM " . $this->table_name . " 
                         WHERE user_id = :user_id AND " . $searchClause . " 
                         ORDER BY created_at DESC 
                         LIMIT :limit";
            
            $stmt = $this->db->prepare($fullQuery);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch(PDOException $exception) {
            error_log("Search bookmarks by user error: " . $exception->getMessage());
            return [];
        }
    }
}
?>