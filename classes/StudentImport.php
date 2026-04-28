<?php
class StudentImport {
    private $conn;
    private $table_name = "student_imports";

    public $id, $filename, $file_type, $imported_by, $total_records, $successful_imports, $failed_imports, $import_status, $error_log;

    public function __construct($db) { $this->conn = $db; }

    public function create() {
        $stmt = $this->conn->prepare("INSERT INTO {$this->table_name} SET filename=:filename, file_type=:file_type, imported_by=:imported_by, total_records=:total_records, successful_imports=:successful_imports, failed_imports=:failed_imports, import_status=:import_status, error_log=:error_log");
        $this->filename = htmlspecialchars(strip_tags($this->filename));
        $this->file_type = htmlspecialchars(strip_tags($this->file_type));
        $this->error_log = htmlspecialchars(strip_tags($this->error_log ?? ''));
        $stmt->bindParam(":filename", $this->filename);
        $stmt->bindParam(":file_type", $this->file_type);
        $stmt->bindParam(":imported_by", $this->imported_by);
        $stmt->bindParam(":total_records", $this->total_records);
        $stmt->bindParam(":successful_imports", $this->successful_imports);
        $stmt->bindParam(":failed_imports", $this->failed_imports);
        $stmt->bindParam(":import_status", $this->import_status);
        $stmt->bindParam(":error_log", $this->error_log);
        return $stmt->execute() ? $this->conn->lastInsertId() : false;
    }

    public function processCSV($file_path, $imported_by) {
        $this->imported_by = $imported_by;
        $this->file_type = 'csv';
        $this->filename = basename($file_path);
        $this->import_status = 'processing';
        $errors = []; $successful = 0; $failed = 0; $total = 0;

        try {
            if (($handle = fopen($file_path, "r")) !== FALSE) {
                $header = fgetcsv($handle, 1000, ",");
                $expected = ['password', 'email', 'first_name', 'middle_name', 'last_name', 'student_id', 'role'];
                if (!$this->validateHeaders($header, $expected)) {
                    $errors[] = "Invalid CSV format. Expected: " . implode(', ', $expected);
                    $this->error_log = implode("\n", $errors);
                    $this->import_status = 'failed';
                    return $this->create();
                }
                $user = new User($this->conn);
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $total++;
                    try {
                        if (empty(array_filter($data))) continue;
                        if (empty($data[0]) || empty($data[2]) || empty($data[4])) { $errors[] = "Row $total: Missing required fields"; $failed++; continue; }
                        $role = !empty($data[6]) ? trim($data[6]) : "student";
                        if ($role !== "student" && empty($data[1])) { $errors[] = "Row $total: Email required for $role"; $failed++; continue; }

                        $user = new User($this->conn);
                        $user->password = trim($data[0]);
                        $user->email = !empty($data[1]) ? trim($data[1]) : null;
                        $user->first_name = trim($data[2]);
                        $user->middle_name = !empty($data[3]) ? trim($data[3]) : null;
                        $user->last_name = trim($data[4]);
                        $user->student_id = !empty($data[5]) ? trim($data[5]) : null;
                        $user->role = in_array($role, ['student', 'guidance_advocate', 'admin', 'examinee']) ? $role : 'student';

                        if ($user->register()) {
                            $user_id = $this->conn->lastInsertId();
                            if ($user->role === 'student' && !empty($user->student_id)) {
                                $check = $this->conn->prepare("SELECT id FROM student_profiles WHERE student_id = ?");
                                $check->execute([$user->student_id]);
                                if ($check->rowCount() > 0) { $errors[] = "Row $total: Student ID '{$user->student_id}' exists"; $failed++; continue; }
                                $profile = $this->conn->prepare("INSERT INTO student_profiles (user_id, student_id) VALUES (?, ?)");
                                $profile->execute([$user_id, $user->student_id]);
                            }
                            $successful++;
                        } else { $errors[] = "Row $total: Failed to create user"; $failed++; }
                    } catch (Exception $e) { $errors[] = "Row $total: " . $e->getMessage(); $failed++; }
                }
                fclose($handle);
            } else { $errors[] = "Could not open CSV file"; $this->import_status = 'failed'; }
        } catch (Exception $e) { $errors[] = "CSV Error: " . $e->getMessage(); $this->import_status = 'failed'; }

        $this->total_records = $total; $this->successful_imports = $successful; $this->failed_imports = $failed;
        $this->error_log = implode("\n", $errors); $this->import_status = 'completed';
        return $this->create();
    }

    public function getImportHistory($limit = 20) {
        $stmt = $this->conn->prepare("SELECT i.*, u.first_name, u.last_name FROM {$this->table_name} i JOIN users u ON i.imported_by = u.id ORDER BY i.created_at DESC LIMIT ?");
        $stmt->bindParam(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }

    public function generateSampleCSV() {
        $sample = [
            ['password', 'email', 'first_name', 'middle_name', 'last_name', 'student_id', 'role'],
            ['password123', 'john.doe@srcb.edu.ph', 'John', 'Michael', 'Doe', 'SRCB-2024-001', 'student'],
            ['password123', 'mary.smith@srcb.edu.ph', 'Mary', 'Jane', 'Smith', 'SRCB-2024-002', 'student'],
        ];
        if (!file_exists('../uploads/')) mkdir('../uploads/', 0777, true);
        $file = fopen('../uploads/sample_user_import.csv', 'w');
        foreach ($sample as $row) fputcsv($file, $row);
        fclose($file);
        return 'sample_user_import.csv';
    }

    private function validateHeaders($actual, $expected) {
        if (count($actual) < count($expected)) return false;
        for ($i = 0; $i < count($expected); $i++) {
            if (strtolower(trim($actual[$i])) !== strtolower($expected[$i])) return false;
        }
        return true;
    }
}
?>
