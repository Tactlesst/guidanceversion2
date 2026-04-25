<?php

class Notification {
    private $conn;
    private $table_name = "notifications";

    public $id;
    public $user_id;
    public $title;
    public $message;
    public $type;
    public $is_read;
    public $related_table;
    public $related_id;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  (user_id, title, message, type, related_table, related_id, created_at) 
                  VALUES 
                  (:user_id, :title, :message, :type, :related_table, :related_id, NOW())";

        $stmt = $this->conn->prepare($query);

        $title = htmlspecialchars(strip_tags($this->title));
        $message = htmlspecialchars(strip_tags($this->message));
        $type = htmlspecialchars(strip_tags($this->type));
        $related_table = htmlspecialchars(strip_tags($this->related_table));

        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":message", $message);
        $stmt->bindParam(":type", $type);
        $stmt->bindParam(":related_table", $related_table);
        $stmt->bindParam(":related_id", $this->related_id);

        try {
            if($stmt->execute()) {
                return true;
            }
            return false;
        } catch (Exception $e) {
            error_log("Notification creation error: " . $e->getMessage());
            return false;
        }
    }

    public function getUnreadCount($user_id) {
        $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " 
                  WHERE user_id = ? AND is_read = 0";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['count'];
    }

    public function getByUserId($user_id, $limit = 10) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE user_id = ? 
                  ORDER BY created_at DESC 
                  LIMIT ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->bindParam(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }
}
