<?php
class Document {
    private $db;
    private $table_name = "documents";
    public $upload_dir = "uploads/documents/";

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        // Ensure the upload directory exists
        if (!is_dir($this->upload_dir)) {
            mkdir($this->upload_dir, 0755, true);
        }
    }

    /**
     * Create a new document record
     */
    public function create($user_id, $file_name, $file_path, $file_size, $file_type) {
        try {
            $query = "INSERT INTO " . $this->table_name . " (user_id, file_name, file_path, file_size, file_type) VALUES (:user_id, :file_name, :file_path, :file_size, :file_type)";
            $stmt = $this->db->prepare($query);

            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':file_name', $file_name);
            $stmt->bindParam(':file_path', $file_path);
            $stmt->bindParam(':file_size', $file_size);
            $stmt->bindParam(':file_type', $file_type);

            if ($stmt->execute()) {
                return $this->db->lastInsertId();
            }
            return false;
        } catch(PDOException $exception) {
            error_log("Create document error: " . $exception->getMessage());
            return false;
        }
    }

    /**
     * Get all documents for a user
     */
    public function getByUserId($user_id) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE user_id = :user_id ORDER BY created_at DESC";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch(PDOException $exception) {
            error_log("Get documents by user ID error: " . $exception->getMessage());
            return [];
        }
    }

    /**
     * Get a single document by its ID
     */
    public function getById($id, $user_id = null) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id";
            if ($user_id) {
                $query .= " AND user_id = :user_id";
            }
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            if ($user_id) {
                $stmt->bindParam(':user_id', $user_id);
            }
            $stmt->execute();
            return $stmt->fetch();
        } catch(PDOException $exception) {
            error_log("Get document by ID error: " . $exception->getMessage());
            return null;
        }
    }

    public function getByIdForViewing($id, $user_id) {
        try {
            $doc = $this->getById($id, $user_id);
            if ($doc) {
                return $doc;
            }

            $query = "SELECT d.*, s.sharer_id, u.username as sharer_username
                     FROM " . $this->table_name . " d 
                     JOIN shared_documents s ON d.id = s.document_id
                     JOIN users u ON s.sharer_id = u.id
                     WHERE d.id = :id 
                     AND (s.recipient_id = :user_id OR s.shared_with_all = TRUE)
                     AND s.sharer_id != :user_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            return $stmt->fetch();
        } catch(PDOException $exception) {
            error_log("Get document for viewing error: " . $exception->getMessage());
            return null;
        }
    }


    /**
     * Delete a document from the filesystem and database
     */
    public function delete($id, $user_id) {
        try {
            // First, get the document path to delete the file
            $doc = $this->getById($id, $user_id);
            if ($doc && file_exists($doc['file_path'])) {
                unlink($doc['file_path']);
            }

            // Then, delete the record from the database
            $query = "DELETE FROM " . $this->table_name . " WHERE id = :id AND user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':user_id', $user_id);
            return $stmt->execute();
        } catch(PDOException $exception) {
            error_log("Delete document error: " . $exception->getMessage());
            return false;
        }
    }
    
    /**
     * Get the total count of documents for a user
     */
    public function getCountByUserId($user_id) {
        try {
            $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " WHERE user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return (int)$result['count'];
        } catch(PDOException $exception) {
            error_log("Get document count error: " . $exception->getMessage());
            return 0;
        }
    }

    // Sharing Methods
    public function share($document_id, $sharer_id, $recipient_id = null, $shared_with_all = false) {
        try {
            $query = "INSERT INTO shared_documents (document_id, sharer_id, recipient_id, shared_with_all)
                     VALUES (:document_id, :sharer_id, :recipient_id, :shared_with_all)";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':document_id', $document_id);
            $stmt->bindParam(':sharer_id', $sharer_id);
            $stmt->bindParam(':recipient_id', $recipient_id);
            $stmt->bindParam(':shared_with_all', $shared_with_all, PDO::PARAM_BOOL);
            return $stmt->execute();
        } catch (PDOException $exception) {
            error_log("Share document error: " . $exception->getMessage());
            return false;
        }
    }

    public function unshare($document_id, $sharer_id, $recipient_id = null) {
        try {
            $query = "DELETE FROM shared_documents WHERE document_id = :document_id AND sharer_id = :sharer_id";
            if ($recipient_id) {
                $query .= " AND recipient_id = :recipient_id";
            } else {
                // If unsharing from a specific person is not specified, this implies unsharing from 'All Users'
                // which means deleting the record where recipient_id is NULL.
                $query .= " AND recipient_id IS NULL";
            }
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':document_id', $document_id);
            $stmt->bindParam(':sharer_id', $sharer_id);
            if ($recipient_id) {
                $stmt->bindParam(':recipient_id', $recipient_id);
            }
            return $stmt->execute();
        } catch (PDOException $exception) {
            error_log("Unshare document error: " . $exception->getMessage());
            return false;
        }
    }

    public function getSharedWithUser($user_id) {
        try {
            $query = "SELECT d.*, s.sharer_id, u.username as sharer_username
                      FROM documents d
                      JOIN shared_documents s ON d.id = s.document_id
                      JOIN users u ON s.sharer_id = u.id
                      WHERE (s.recipient_id = :user_id OR s.shared_with_all = TRUE)
                      AND s.sharer_id != :user_id
                      ORDER BY s.created_at DESC";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $exception) {
            error_log("Get shared documents with user error: " . $exception->getMessage());
            return [];
        }
    }

    public function getSharedByUser($user_id) {
        try {
            $query = "SELECT d.*, s.recipient_id, u.username as recipient_username
                      FROM documents d
                      JOIN shared_documents s ON d.id = s.document_id
                      LEFT JOIN users u ON s.recipient_id = u.id
                      WHERE s.sharer_id = :user_id
                      ORDER BY s.created_at DESC";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $exception) {
            error_log("Get documents shared by user error: " . $exception->getMessage());
            return [];
        }
    }

    public function saveSharedDocument($original_document_id, $new_owner_id) {
        try {
            // Get the original document without checking for ownership
            $original_doc = $this->getById($original_document_id, null);
            if (!$original_doc) return false;
            
            // Generate a new unique file path for the copy
            $new_file_name = $new_owner_id . '_' . time() . '_' . $original_doc['file_name'];
            $new_file_path = $this->upload_dir . $new_file_name;

            // Copy the actual file on the server
            if (!copy($original_doc['file_path'], $new_file_path)) {
                 error_log("Failed to copy document file from {$original_doc['file_path']} to {$new_file_path}");
                return false;
            }

            // Create a new document record for the new owner
            return $this->create(
                $new_owner_id,
                $original_doc['file_name'],
                $new_file_path,
                $original_doc['file_size'],
                $original_doc['file_type']
            );
        } catch (Exception $exception) {
            error_log("Save shared document error: " . $exception->getMessage());
            return false;
        }
    }

    public function hasUserSharedDocument($document_id, $user_id) {
        try {
            $query = "SELECT COUNT(*) as count FROM shared_documents
                     WHERE document_id = :document_id AND sharer_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':document_id', $document_id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $result = $stmt->fetch();
            
            return $result['count'] > 0;
        } catch(PDOException $exception) {
            error_log("Check user shared document error: " . $exception->getMessage());
            return false;
        }
    }

    public function hasUserSavedSharedDocument($original_document_id, $user_id) {
        try {
            $query = "SELECT file_path FROM documents WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $original_document_id);
            $stmt->execute();
            $original = $stmt->fetch();
            
            if (!$original) {
                return false;
            }
            
            $check_query = "SELECT id FROM documents WHERE user_id = :user_id AND file_path LIKE :file_path";
            $check_stmt = $this->db->prepare($check_query);
            $check_stmt->bindParam(':user_id', $user_id);
            $check_stmt->bindValue(':file_path', '%' . basename($original['file_path']));
            $check_stmt->execute();
            
            return $check_stmt->fetch() !== false;
        } catch(PDOException $exception) {
            error_log("Check saved shared document error: " . $exception->getMessage());
            return false;
        }
    }
}
?>