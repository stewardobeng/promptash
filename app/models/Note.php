<?php
class Note {
    private $db;
    private $table_name = "notes";

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    /**
     * Create a new note
     */
    public function create($user_id, $title, $content, $color = 'yellow') {
        try {
            $query = "INSERT INTO " . $this->table_name . " (user_id, title, content, color) VALUES (:user_id, :title, :content, :color)";
            $stmt = $this->db->prepare($query);

            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':content', $content);
            $stmt->bindParam(':color', $color);

            if ($stmt->execute()) {
                return $this->db->lastInsertId();
            }
            return false;
        } catch(PDOException $exception) {
            error_log("Create note error: " . $exception->getMessage());
            return false;
        }
    }

    /**
     * Get all notes for a user
     */
    public function getByUserId($user_id) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE user_id = :user_id ORDER BY is_pinned DESC, updated_at DESC";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch(PDOException $exception) {
            error_log("Get notes by user ID error: " . $exception->getMessage());
            return [];
        }
    }

    /**
     * Get a single note by its ID
     */
    public function getById($id, $user_id) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id AND user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            return $stmt->fetch();
        } catch(PDOException $exception) {
            error_log("Get note by ID error: " . $exception->getMessage());
            return null;
        }
    }

    public function getByIdForViewing($id, $user_id) {
        try {
            $note = $this->getById($id, $user_id);
            if ($note) {
                return $note;
            }

            $query = "SELECT n.*, s.sharer_id, u.username as sharer_username
                     FROM " . $this->table_name . " n 
                     JOIN shared_notes s ON n.id = s.note_id
                     JOIN users u ON s.sharer_id = u.id
                     WHERE n.id = :id 
                     AND (s.recipient_id = :user_id OR s.shared_with_all = TRUE)
                     AND s.sharer_id != :user_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            return $stmt->fetch();
        } catch(PDOException $exception) {
            error_log("Get note for viewing error: " . $exception->getMessage());
            return null;
        }
    }

    /**
     * Update a note
     */
    public function update($id, $user_id, $title, $content, $color) {
        try {
            $query = "UPDATE " . $this->table_name . " SET title = :title, content = :content, color = :color, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND user_id = :user_id";
            $stmt = $this->db->prepare($query);

            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':content', $content);
            $stmt->bindParam(':color', $color);

            return $stmt->execute();
        } catch(PDOException $exception) {
            error_log("Update note error: " . $exception->getMessage());
            return false;
        }
    }

    /**
     * Delete a note
     */
    public function delete($id, $user_id) {
        try {
            $query = "DELETE FROM " . $this->table_name . " WHERE id = :id AND user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':user_id', $user_id);
            return $stmt->execute();
        } catch(PDOException $exception) {
            error_log("Delete note error: " . $exception->getMessage());
            return false;
        }
    }

    /**
     * Toggle the pinned status of a note
     */
    public function togglePin($id, $user_id) {
        try {
            $query = "UPDATE " . $this->table_name . " SET is_pinned = NOT is_pinned, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':user_id', $user_id);
            return $stmt->execute();
        } catch(PDOException $exception) {
            error_log("Toggle note pin error: " . $exception->getMessage());
            return false;
        }
    }
    
    /**
     * Get the total count of notes for a user
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
            error_log("Get note count error: " . $exception->getMessage());
            return 0;
        }
    }

    // Sharing Methods
    public function share($note_id, $sharer_id, $recipient_id = null, $shared_with_all = false) {
        try {
            $query = "INSERT INTO shared_notes (note_id, sharer_id, recipient_id, shared_with_all)
                     VALUES (:note_id, :sharer_id, :recipient_id, :shared_with_all)";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':note_id', $note_id);
            $stmt->bindParam(':sharer_id', $sharer_id);
            $stmt->bindParam(':recipient_id', $recipient_id);
            $stmt->bindParam(':shared_with_all', $shared_with_all, PDO::PARAM_BOOL);
            return $stmt->execute();
        } catch (PDOException $exception) {
            error_log("Share note error: " . $exception->getMessage());
            return false;
        }
    }

    public function unshare($note_id, $sharer_id, $recipient_id = null) {
        try {
            $query = "DELETE FROM shared_notes WHERE note_id = :note_id AND sharer_id = :sharer_id";
            if ($recipient_id) {
                $query .= " AND recipient_id = :recipient_id";
            } else {
                $query .= " AND recipient_id IS NULL";
            }
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':note_id', $note_id);
            $stmt->bindParam(':sharer_id', $sharer_id);
            if ($recipient_id) {
                $stmt->bindParam(':recipient_id', $recipient_id);
            }
            return $stmt->execute();
        } catch (PDOException $exception) {
            error_log("Unshare note error: " . $exception->getMessage());
            return false;
        }
    }

    public function getSharedWithUser($user_id) {
        try {
            $query = "SELECT n.*, s.sharer_id, u.username as sharer_username
                      FROM notes n
                      JOIN shared_notes s ON n.id = s.note_id
                      JOIN users u ON s.sharer_id = u.id
                      WHERE (s.recipient_id = :user_id OR s.shared_with_all = TRUE)
                      AND s.sharer_id != :user_id
                      ORDER BY s.created_at DESC";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $exception) {
            error_log("Get shared notes with user error: " . $exception->getMessage());
            return [];
        }
    }

    public function getSharedByUser($user_id) {
        try {
            $query = "SELECT n.*, s.recipient_id, u.username as recipient_username
                      FROM notes n
                      JOIN shared_notes s ON n.id = s.note_id
                      LEFT JOIN users u ON s.recipient_id = u.id
                      WHERE s.sharer_id = :user_id
                      ORDER BY s.created_at DESC";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $exception) {
            error_log("Get notes shared by user error: " . $exception->getMessage());
            return [];
        }
    }

    public function saveSharedNote($original_note_id, $new_owner_id) {
        try {
            $original_note = $this->getById($original_note_id, null); // Get note without owner check
             if(!$original_note) {
                // Check shared notes table if not owner
                $query = "SELECT n.* FROM notes n JOIN shared_notes sn ON n.id = sn.note_id WHERE n.id = :id";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':id', $original_note_id);
                $stmt->execute();
                $original_note = $stmt->fetch();
            }
            
            if (!$original_note) return false;

            return $this->create(
                $new_owner_id,
                $original_note['title'] . " (copy)",
                $original_note['content'],
                $original_note['color']
            );
        } catch (PDOException $exception) {
            error_log("Save shared note error: " . $exception->getMessage());
            return false;
        }
    }

    public function hasUserSharedNote($note_id, $user_id) {
        try {
            $query = "SELECT COUNT(*) as count FROM shared_notes
                     WHERE note_id = :note_id AND sharer_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':note_id', $note_id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $result = $stmt->fetch();
            
            return $result['count'] > 0;
        } catch(PDOException $exception) {
            error_log("Check user shared note error: " . $exception->getMessage());
            return false;
        }
    }

    public function hasUserSavedSharedNote($original_note_id, $user_id) {
        try {
            $query = "SELECT title, content FROM notes WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $original_note_id);
            $stmt->execute();
            $original = $stmt->fetch();
            
            if (!$original) {
                return false;
            }
            
            $check_query = "SELECT id FROM notes WHERE user_id = :user_id AND title = :title AND content = :content";
            $check_stmt = $this->db->prepare($check_query);
            $check_stmt->bindParam(':user_id', $user_id);
            $check_stmt->bindValue(':title', $original['title'] . " (copy)");
            $check_stmt->bindParam(':content', $original['content']);
            $check_stmt->execute();
            
            return $check_stmt->fetch() !== false;
        } catch(PDOException $exception) {
            error_log("Check saved shared note error: " . $exception->getMessage());
            return false;
        }
    }
}
?>