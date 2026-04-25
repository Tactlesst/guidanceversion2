<?php
class User {
    private $conn;
    private $table_name = "users";
    public $id, $email, $role, $first_name, $last_name;

    public function __construct($db) { $this->conn = $db; }

    public function login($identifier, $password) {
        $query = "SELECT u.id, u.password, u.email, u.role, u.first_name, u.last_name, u.is_active, sp.student_id
                  FROM {$this->table_name} u
                  LEFT JOIN student_profiles sp ON u.id = sp.user_id
                  WHERE (u.email = ? OR sp.student_id = ?) AND u.is_active = 1 AND (u.archived = 0 OR u.archived IS NULL)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$identifier, $identifier]);

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (password_verify($password, $row['password'])) {
                $this->id = $row['id']; $this->email = $row['email'];
                $this->role = $row['role']; $this->first_name = $row['first_name'];
                $this->last_name = $row['last_name'];
                return true;
            }
        }
        return false;
    }

    public function getUserById($id) {
        $query = "SELECT u.*, sp.student_id FROM {$this->table_name} u
                  LEFT JOIN student_profiles sp ON u.id = sp.user_id WHERE u.id = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0 ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    }

    public function getUsersByRole($role) {
        $query = "SELECT u.*, sp.student_id FROM {$this->table_name} u 
                  LEFT JOIN student_profiles sp ON u.id = sp.user_id 
                  WHERE u.role = ? AND u.is_active = 1 AND (u.archived = 0 OR u.archived IS NULL)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$role]);
        return $stmt;
    }
}
?>
