<?php
class Video {
    private $db;
    private $table_name = "videos";

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    /**
     * Create a new video entry
     */
    public function create($user_id, $url, $title, $description, $thumbnail_url, $channel_title, $duration) {
        try {
            $query = "INSERT INTO " . $this->table_name . " (user_id, url, title, description, thumbnail_url, channel_title, duration) VALUES (:user_id, :url, :title, :description, :thumbnail_url, :channel_title, :duration)";
            $stmt = $this->db->prepare($query);

            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':url', $url);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':thumbnail_url', $thumbnail_url);
            $stmt->bindParam(':channel_title', $channel_title);
            $stmt->bindParam(':duration', $duration);

            if ($stmt->execute()) {
                return $this->db->lastInsertId();
            }
            return false;
        } catch(PDOException $exception) {
            error_log("Create video error: " . $exception->getMessage());
            return false;
        }
    }

    /**
     * Get all videos for a user
     */
    public function getByUserId($user_id) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE user_id = :user_id ORDER BY created_at DESC";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch(PDOException $exception) {
            error_log("Get videos by user ID error: " . $exception->getMessage());
            return [];
        }
    }

    /**
     * Get a single video by its ID
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
            error_log("Get video by ID error: " . $exception->getMessage());
            return null;
        }
    }

     public function getByIdForViewing($id, $user_id) {
        try {
            $video = $this->getById($id, $user_id);
            if ($video) {
                return $video;
            }

            $query = "SELECT v.*, s.sharer_id, u.username as sharer_username
                     FROM " . $this->table_name . " v
                     JOIN shared_videos s ON v.id = s.video_id
                     JOIN users u ON s.sharer_id = u.id
                     WHERE v.id = :id 
                     AND (s.recipient_id = :user_id OR s.shared_with_all = TRUE)
                     AND s.sharer_id != :user_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            return $stmt->fetch();
        } catch(PDOException $exception) {
            error_log("Get video for viewing error: " . $exception->getMessage());
            return null;
        }
    }

    /**
     * Delete a video entry
     */
    public function delete($id, $user_id) {
        try {
            $query = "DELETE FROM " . $this->table_name . " WHERE id = :id AND user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':user_id', $user_id);
            return $stmt->execute();
        } catch(PDOException $exception) {
            error_log("Delete video error: " . $exception->getMessage());
            return false;
        }
    }

    /**
     * Get the total count of videos for a user
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
            error_log("Get video count error: " . $exception->getMessage());
            return 0;
        }
    }

    // Sharing Methods
    public function share($video_id, $sharer_id, $recipient_id = null, $shared_with_all = false) {
        try {
            $query = "INSERT INTO shared_videos (video_id, sharer_id, recipient_id, shared_with_all)
                     VALUES (:video_id, :sharer_id, :recipient_id, :shared_with_all)";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':video_id', $video_id);
            $stmt->bindParam(':sharer_id', $sharer_id);
            $stmt->bindParam(':recipient_id', $recipient_id);
            $stmt->bindParam(':shared_with_all', $shared_with_all, PDO::PARAM_BOOL);
            return $stmt->execute();
        } catch (PDOException $exception) {
            error_log("Share video error: " . $exception->getMessage());
            return false;
        }
    }

    public function unshare($video_id, $sharer_id, $recipient_id = null) {
        try {
            $query = "DELETE FROM shared_videos WHERE video_id = :video_id AND sharer_id = :sharer_id";
            if ($recipient_id) {
                $query .= " AND recipient_id = :recipient_id";
            } else {
                $query .= " AND recipient_id IS NULL";
            }
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':video_id', $video_id);
            $stmt->bindParam(':sharer_id', $sharer_id);
            if ($recipient_id) {
                $stmt->bindParam(':recipient_id', $recipient_id);
            }
            return $stmt->execute();
        } catch (PDOException $exception) {
            error_log("Unshare video error: " . $exception->getMessage());
            return false;
        }
    }

    public function getSharedWithUser($user_id) {
        try {
            $query = "SELECT v.*, s.sharer_id, u.username as sharer_username
                      FROM videos v
                      JOIN shared_videos s ON v.id = s.video_id
                      JOIN users u ON s.sharer_id = u.id
                      WHERE (s.recipient_id = :user_id OR s.shared_with_all = TRUE)
                      AND s.sharer_id != :user_id
                      ORDER BY s.created_at DESC";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $exception) {
            error_log("Get shared videos with user error: " . $exception->getMessage());
            return [];
        }
    }

    public function getSharedByUser($user_id) {
        try {
            $query = "SELECT v.*, s.recipient_id, u.username as recipient_username
                      FROM videos v
                      JOIN shared_videos s ON v.id = s.video_id
                      LEFT JOIN users u ON s.recipient_id = u.id
                      WHERE s.sharer_id = :user_id
                      ORDER BY s.created_at DESC";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $exception) {
            error_log("Get videos shared by user error: " . $exception->getMessage());
            return [];
        }
    }

    public function saveSharedVideo($original_video_id, $new_owner_id) {
        try {
             $original_video = $this->getById($original_video_id);
             if(!$original_video) {
                // Check shared notes table if not owner
                $query = "SELECT v.* FROM videos v JOIN shared_videos sv ON v.id = sv.video_id WHERE v.id = :id";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':id', $original_video_id);
                $stmt->execute();
                $original_video = $stmt->fetch();
            }

            if (!$original_video) return false;

            return $this->create(
                $new_owner_id,
                $original_video['url'],
                $original_video['title'] . " (copy)",
                $original_video['description'],
                $original_video['thumbnail_url'],
                $original_video['channel_title'],
                $original_video['duration']
            );
        } catch (PDOException $exception) {
            error_log("Save shared video error: " . $exception->getMessage());
            return false;
        }
    }

    public function hasUserSharedVideo($video_id, $user_id) {
        try {
            $query = "SELECT COUNT(*) as count FROM shared_videos
                     WHERE video_id = :video_id AND sharer_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':video_id', $video_id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $result = $stmt->fetch();
            
            return $result['count'] > 0;
        } catch(PDOException $exception) {
            error_log("Check user shared video error: " . $exception->getMessage());
            return false;
        }
    }

    public function hasUserSavedSharedVideo($original_video_id, $user_id) {
        try {
            $query = "SELECT url FROM videos WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $original_video_id);
            $stmt->execute();
            $original = $stmt->fetch();
            
            if (!$original) {
                return false;
            }
            
            $check_query = "SELECT id FROM videos WHERE user_id = :user_id AND url = :url";
            $check_stmt = $this->db->prepare($check_query);
            $check_stmt->bindParam(':user_id', $user_id);
            $check_stmt->bindParam(':url', $original['url']);
            $check_stmt->execute();
            
            return $check_stmt->fetch() !== false;
        } catch(PDOException $exception) {
            error_log("Check saved shared video error: " . $exception->getMessage());
            return false;
        }
    }
}
?>