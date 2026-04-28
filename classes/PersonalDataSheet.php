<?php
class PersonalDataSheet {
    private $conn;
    private $table_name = "personal_data_sheets";

    public $id, $user_id, $middle_name, $birth_date, $birth_place, $gender, $civil_status;
    public $nationality, $religion, $contact_number, $address;
    public $father_name, $father_occupation, $father_contact;
    public $mother_name, $mother_occupation, $mother_contact;
    public $guardian_name, $guardian_relationship, $guardian_contact;
    public $elementary_school, $elementary_year_graduated;
    public $high_school, $high_school_year_graduated;
    public $college, $college_course, $college_year_graduated;
    public $emergency_contact_name, $emergency_contact_relationship, $emergency_contact_number;
    public $medical_conditions, $medications, $allergies, $is_completed;

    public function __construct($db) { $this->conn = $db; }

    public function getByUserId($user_id) {
        $table = $this->determineTableByUserId($user_id);
        $stmt = $this->conn->prepare("SELECT * FROM {$table} WHERE user_id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        return $stmt->rowCount() > 0 ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    }

    public function exists($user_id) {
        foreach (['pds_college', 'pds_seniorhigh', 'pds_highschool'] as $table) {
            if (!$this->tableExists($table)) continue;
            $stmt = $this->conn->prepare("SELECT id FROM {$table} WHERE user_id = ?");
            $stmt->execute([$user_id]);
            if ($stmt->rowCount() > 0) return true;
        }
        return false;
    }

    public function getAllPDS() {
        $parts = [];
        if ($this->tableExists('pds_highschool')) {
            $parts[] = "SELECT p.id, p.user_id, p.first_name, p.last_name, p.middle_name, p.student_photo, 
                       p.created_at, p.updated_at, p.grade_level, NULL as strand, NULL as course, NULL as year_level,
                       p.father_given_name, p.father_surname, p.mother_given_name, p.mother_surname,
                       p.guardian_name, p.nickname, p.age, p.gender, p.birth_date, NULL as civil_status,
                       p.contact_number, p.address, p.nationality, NULL as email,
                       u.first_name as user_first_name, u.last_name as user_last_name, 
                       sp.student_id, 'highschool' as pds_type
                       FROM pds_highschool p JOIN users u ON p.user_id = u.id LEFT JOIN student_profiles sp ON u.id = sp.user_id";
        }
        if ($this->tableExists('pds_seniorhigh')) {
            $parts[] = "SELECT p.id, p.user_id, p.first_name, p.last_name, p.middle_name, p.student_photo,
                       p.created_at, p.updated_at, p.grade_level_shs as grade_level, p.strand_shs as strand, 
                       NULL as course, NULL as year_level,
                       p.father_given_name, p.father_surname, p.mother_given_name, p.mother_surname,
                       p.guardian_name, p.nickname, p.age, p.gender, p.birth_date, NULL as civil_status,
                       p.contact_number, p.address, p.nationality, NULL as email,
                       u.first_name as user_first_name, u.last_name as user_last_name, 
                       sp.student_id, 'seniorhigh' as pds_type
                       FROM pds_seniorhigh p JOIN users u ON p.user_id = u.id LEFT JOIN student_profiles sp ON u.id = sp.user_id";
        }
        if ($this->tableExists('pds_college')) {
            $parts[] = "SELECT p.id, p.user_id, p.first_name_he as first_name, p.last_name_he as last_name, 
                       p.middle_name_he as middle_name, p.student_photo,
                       NULL as created_at, NULL as updated_at, NULL as grade_level, NULL as strand, 
                       p.course, p.year_level, p.father_given_name, p.father_surname, 
                       p.mother_given_name, p.mother_surname, p.guardian_name, p.nickname, 
                       p.age_he as age, p.sex_he as gender, p.date_of_birth_he as birth_date, 
                       p.civil_status_he as civil_status, p.mobile_number as contact_number, 
                       p.home_address_he as address, p.nationality, p.email_he as email,
                       u.first_name as user_first_name, u.last_name as user_last_name, 
                       sp.student_id, 'college' as pds_type
                       FROM pds_college p JOIN users u ON p.user_id = u.id LEFT JOIN student_profiles sp ON u.id = sp.user_id";
        }

        if (empty($parts)) return $this->conn->prepare("SELECT NULL WHERE 1=0")->execute();
        $query = implode(" UNION ALL ", $parts) . " ORDER BY COALESCE(updated_at, created_at) DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function getPDSById($id) {
        $tables = [
            'pds_college' => "SELECT p.*, u.first_name as user_first_name, u.last_name as user_last_name, sp.student_id, u.email, u.role, 'Higher Education' as education_type FROM pds_college p JOIN users u ON p.user_id = u.id LEFT JOIN student_profiles sp ON u.id = sp.user_id WHERE p.id = ?",
            'pds_seniorhigh' => "SELECT p.*, u.first_name as user_first_name, u.last_name as user_last_name, sp.student_id, u.email, u.role, 'Senior High School' as education_type FROM pds_seniorhigh p JOIN users u ON p.user_id = u.id LEFT JOIN student_profiles sp ON u.id = sp.user_id WHERE p.id = ?",
            'pds_highschool' => "SELECT p.*, u.first_name as user_first_name, u.last_name as user_last_name, sp.student_id, u.email, u.role, CASE WHEN p.education_level = 'elementary' AND p.grade_level_elem = 'Kindergarten' THEN 'Kindergarten' WHEN p.education_level = 'elementary' THEN 'Elementary' ELSE 'High School' END as education_type FROM pds_highschool p JOIN users u ON p.user_id = u.id LEFT JOIN student_profiles sp ON u.id = sp.user_id WHERE p.id = ?",
        ];
        foreach ($tables as $table => $query) {
            if (!$this->tableExists($table)) continue;
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$id]);
            if ($stmt->rowCount() > 0) return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return false;
    }

    public function deletePDS($id) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table_name} WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getPDSStats() {
        try {
            $check = $this->conn->prepare("SHOW COLUMNS FROM {$this->table_name} LIKE 'status'");
            $check->execute();
            $has_status = $check->rowCount() > 0;
        } catch (Exception $e) { $has_status = false; }

        if ($has_status) {
            $stmt = $this->conn->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN (status IS NULL OR status = 'pending') THEN 1 ELSE 0 END) as pending, SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved, SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected FROM {$this->table_name}");
        } else {
            $stmt = $this->conn->prepare("SELECT COUNT(*) as total, COUNT(*) as pending, 0 as approved, 0 as rejected FROM {$this->table_name}");
        }
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function determineTableByUserId($user_id) {
        foreach (['pds_college', 'pds_seniorhigh', 'pds_highschool'] as $table) {
            if (!$this->tableExists($table)) continue;
            $stmt = $this->conn->prepare("SELECT 1 FROM {$table} WHERE user_id = ? LIMIT 1");
            $stmt->execute([$user_id]);
            if ($stmt->rowCount() > 0) return $table;
        }
        return 'pds_highschool';
    }

    private function tableExists($table) {
        try {
            $stmt = $this->conn->prepare("SHOW TABLES LIKE :table");
            $stmt->bindValue(':table', $table, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (Exception $e) { return false; }
    }
}
?>
