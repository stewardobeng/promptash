<?php
class Category {
    private $db;
    private $table_name = "categories";

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function create($name, $description, $user_id) {
        try {
            $query = "INSERT INTO " . $this->table_name . " (name, description, user_id) 
                     VALUES (:name, :description, :user_id)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':user_id', $user_id);

            if ($stmt->execute()) {
                return $this->db->lastInsertId();
            }
            return false;
        } catch(PDOException $exception) {
            error_log("Create category error: " . $exception->getMessage());
            return false;
        }
    }

    public function getByUserId($user_id) {
        try {
            $query = "SELECT c.*, COUNT(p.id) as prompt_count 
                     FROM " . $this->table_name . " c 
                     LEFT JOIN prompts p ON c.id = p.category_id 
                     WHERE c.user_id = :user_id 
                     GROUP BY c.id 
                     ORDER BY c.name ASC";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch(PDOException $exception) {
            error_log("Get categories error: " . $exception->getMessage());
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
            error_log("Get category count error: " . $exception->getMessage());
            return 0;
        }
    }
    
    // Alias for Chrome Extension compatibility
    public function countByUserId($user_id) {
        return $this->getCountByUserId($user_id);
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
            error_log("Get category error: " . $exception->getMessage());
            return null;
        }
    }

    public function update($id, $user_id, $name, $description) {
        try {
            $query = "UPDATE " . $this->table_name . " 
                     SET name = :name, description = :description, updated_at = CURRENT_TIMESTAMP 
                     WHERE id = :id AND user_id = :user_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':description', $description);

            return $stmt->execute();
        } catch(PDOException $exception) {
            error_log("Update category error: " . $exception->getMessage());
            return false;
        }
    }

    public function delete($id, $user_id) {
        try {
            // First, update prompts to remove category reference
            $updateQuery = "UPDATE prompts SET category_id = NULL WHERE category_id = :id AND user_id = :user_id";
            $updateStmt = $this->db->prepare($updateQuery);
            $updateStmt->bindParam(':id', $id);
            $updateStmt->bindParam(':user_id', $user_id);
            $updateStmt->execute();

            // Then delete the category
            $query = "DELETE FROM " . $this->table_name . " WHERE id = :id AND user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':user_id', $user_id);
            return $stmt->execute();
        } catch(PDOException $exception) {
            error_log("Delete category error: " . $exception->getMessage());
            return false;
        }
    }
}
?>