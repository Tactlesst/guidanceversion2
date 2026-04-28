<?php
class User {
    private $conn;
    private $table_name = "users";

    public $id, $password, $email, $role, $first_name, $middle_name, $last_name;
    public $position, $student_id, $grade_level_applying, $examinee_type, $is_active;

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

    public function register() {
        if (!empty($this->email)) {
            $check = $this->conn->prepare("SELECT id FROM {$this->table_name} WHERE email = ?");
            $check->execute([$this->email]);
            if ($check->rowCount() > 0) throw new Exception("Email already exists.");
        }
        if ($this->role !== "student" && empty($this->email)) throw new Exception("Email is required for role: " . $this->role);

        $query = "INSERT INTO {$this->table_name} 
                  SET password=:password, email=:email, role=:role, first_name=:first_name, 
                      middle_name=:middle_name, last_name=:last_name, position=:position,
                      grade_level_applying=:grade_level_applying, examinee_type=:examinee_type";
        $stmt = $this->conn->prepare($query);

        $this->password = password_hash($this->password, PASSWORD_DEFAULT);
        $this->email = empty($this->email) ? null : htmlspecialchars(strip_tags($this->email));
        $this->role = htmlspecialchars(strip_tags($this->role));
        $this->first_name = htmlspecialchars(strip_tags($this->first_name));
        $this->middle_name = empty($this->middle_name) ? null : htmlspecialchars(strip_tags($this->middle_name));
        $this->last_name = htmlspecialchars(strip_tags($this->last_name));
        $this->position = empty($this->position) ? null : htmlspecialchars(strip_tags($this->position));

        $stmt->bindParam(":password", $this->password);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":role", $this->role);
        $stmt->bindParam(":first_name", $this->first_name);
        $stmt->bindParam(":middle_name", $this->middle_name);
        $stmt->bindParam(":last_name", $this->last_name);
        $stmt->bindParam(":position", $this->position);
        $stmt->bindParam(":grade_level_applying", $this->grade_level_applying);
        $stmt->bindParam(":examinee_type", $this->examinee_type);

        return $stmt->execute();
    }

    public function getUserById($id) {
        $query = "SELECT u.*, sp.student_id, sp.department, sp.program, sp.strand, sp.grade_level 
                  FROM {$this->table_name} u
                  LEFT JOIN student_profiles sp ON u.id = sp.user_id WHERE u.id = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0 ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    }

    public function getAllUsers($include_archived = false) {
        $where = $include_archived ? "" : " WHERE (u.archived = 0 OR u.archived IS NULL)";
        $query = "SELECT u.*, sp.student_id FROM {$this->table_name} u 
                  LEFT JOIN student_profiles sp ON u.id = sp.user_id 
                  {$where} ORDER BY u.archived ASC, u.created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function getUsersByRole($role, $include_archived = false) {
        $where = $include_archived 
            ? "WHERE u.role = ? ORDER BY u.archived ASC, u.first_name, u.last_name"
            : "WHERE u.role = ? AND (u.archived = 0 OR u.archived IS NULL) ORDER BY u.first_name, u.last_name";
        $query = "SELECT u.*, sp.student_id FROM {$this->table_name} u 
                  LEFT JOIN student_profiles sp ON u.id = sp.user_id {$where}";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $role);
        $stmt->execute();
        return $stmt;
    }

    public function updateUserComplete() {
        if (!empty($this->email)) {
            $check = $this->conn->prepare("SELECT id FROM {$this->table_name} WHERE email = ? AND id != ?");
            $check->execute([$this->email, $this->id]);
            if ($check->rowCount() > 0) throw new Exception("Email already exists.");
        }
        if (!empty($this->student_id)) {
            $check = $this->conn->prepare("SELECT id FROM student_profiles WHERE student_id = ? AND user_id != ?");
            $check->execute([$this->student_id, $this->id]);
            if ($check->rowCount() > 0) throw new Exception("Student ID already exists.");
        }

        $query = "UPDATE {$this->table_name} SET first_name=:first_name, last_name=:last_name, email=:email, role=:role, is_active=:is_active WHERE id=:id";
        $stmt = $this->conn->prepare($query);
        $this->first_name = htmlspecialchars(strip_tags($this->first_name));
        $this->last_name = htmlspecialchars(strip_tags($this->last_name));
        $this->email = empty($this->email) ? null : htmlspecialchars(strip_tags($this->email));
        $this->role = htmlspecialchars(strip_tags($this->role));

        $stmt->bindParam(":first_name", $this->first_name);
        $stmt->bindParam(":last_name", $this->last_name);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":role", $this->role);
        $stmt->bindParam(":is_active", $this->is_active);
        $stmt->bindParam(":id", $this->id);

        if ($stmt->execute()) {
            if ($this->role === 'student' && !empty($this->student_id)) {
                $this->student_id = htmlspecialchars(strip_tags($this->student_id));
                $profile_check = $this->conn->prepare("SELECT id FROM student_profiles WHERE user_id = ?");
                $profile_check->execute([$this->id]);
                if ($profile_check->rowCount() > 0) {
                    $upd = $this->conn->prepare("UPDATE student_profiles SET student_id = ? WHERE user_id = ?");
                    $upd->execute([$this->student_id, $this->id]);
                } else {
                    $ins = $this->conn->prepare("INSERT INTO student_profiles (user_id, student_id) VALUES (?, ?)");
                    $ins->execute([$this->id, $this->student_id]);
                }
            }
            return true;
        }
        return false;
    }

    public function resetPassword($id, $new_password) {
        $stmt = $this->conn->prepare("UPDATE {$this->table_name} SET password = ? WHERE id = ?");
        return $stmt->execute([password_hash($new_password, PASSWORD_DEFAULT), $id]);
    }

    public function archiveUser($id) {
        $check = $this->conn->prepare("SELECT role FROM {$this->table_name} WHERE id = ?");
        $check->execute([$id]);
        if ($check->rowCount() > 0 && $check->fetch(PDO::FETCH_ASSOC)['role'] == 'super_admin')
            throw new Exception("Cannot archive super admin account.");
        $stmt = $this->conn->prepare("UPDATE {$this->table_name} SET archived = 1, is_active = 0 WHERE id = ? AND role != 'super_admin'");
        return $stmt->execute([$id]);
    }

    public function unarchiveUser($id) {
        $stmt = $this->conn->prepare("UPDATE {$this->table_name} SET archived = 0, is_active = 1 WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function toggleUserStatus($id) {
        $stmt = $this->conn->prepare("UPDATE {$this->table_name} SET is_active = NOT is_active WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function searchUsers($search_term, $role_filter = null, $include_archived = false) {
        $query = "SELECT u.*, sp.student_id FROM {$this->table_name} u 
                  LEFT JOIN student_profiles sp ON u.id = sp.user_id 
                  WHERE (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR sp.student_id LIKE ?)";
        $params = ["%$search_term%", "%$search_term%", "%$search_term%", "%$search_term%"];

        if (!$include_archived) { $query .= " AND (u.archived = 0 OR u.archived IS NULL)"; }
        if ($role_filter && $role_filter != 'all') { $query .= " AND u.role = ?"; $params[] = $role_filter; }
        $query .= " ORDER BY u.archived ASC, u.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt;
    }

    public function getUserStats() {
        $stmt = $this->conn->prepare("SELECT role, COUNT(*) as count, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_count 
                  FROM {$this->table_name} WHERE (archived = 0 OR archived IS NULL) GROUP BY role");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function bulkUpdateStatus($user_ids, $status) {
        if (empty($user_ids)) return false;
        $placeholders = str_repeat('?,', count($user_ids) - 1) . '?';
        $stmt = $this->conn->prepare("UPDATE {$this->table_name} SET is_active = ? WHERE id IN ($placeholders) AND role != 'super_admin'");
        return $stmt->execute(array_merge([$status], $user_ids));
    }

    public function getRecentUsers($limit = 10) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table_name} ORDER BY created_at DESC LIMIT ?");
        $stmt->bindParam(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }

    public function updateStudentProfile($user_id, $data) {
        $check = $this->conn->prepare("SELECT id FROM student_profiles WHERE user_id = ?");
        $check->execute([$user_id]);
        if ($check->rowCount() > 0) {
            $stmt = $this->conn->prepare("UPDATE student_profiles SET department=?, program=?, strand=?, grade_level=?, updated_at=NOW() WHERE user_id=?");
            return $stmt->execute([$data['department'] ?? null, $data['program'] ?? null, $data['strand'] ?? null, $data['grade_level'] ?? null, $user_id]);
        } else {
            $stmt = $this->conn->prepare("INSERT INTO student_profiles (user_id, student_id, department, program, strand, grade_level) VALUES (?, ?, ?, ?, ?, ?)");
            return $stmt->execute([$user_id, 'TEMP-' . $user_id, $data['department'] ?? null, $data['program'] ?? null, $data['strand'] ?? null, $data['grade_level'] ?? null]);
        }
    }
}
?>
