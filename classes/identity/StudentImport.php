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
        require_once __DIR__.'/User.php';
        $this->imported_by=$imported_by;$this->file_type='csv';$this->filename=basename($file_path);
        $this->total_records=0;$this->successful_imports=0;$this->failed_imports=0;$this->import_status='completed';$errors=[];
        $handle=fopen($file_path,'r');
        if(!$handle){$this->import_status='failed';$this->error_log='Could not read CSV file.';return $this->create();}
        try {
            $header=fgetcsv($handle,0,',');if(is_array($header)&&isset($header[0]))$header[0]=preg_replace('/^\xEF\xBB\xBF/','',$header[0]);
            $expected=['password','email','first_name','middle_name','last_name','student_id','role'];
            if(!$this->validateHeaders($header,$expected)){$this->import_status='failed';$this->error_log='Invalid CSV format. Expected: '.implode(', ',$expected);return $this->create();}
            while(($row=fgetcsv($handle,0,','))!==false){
                if(!array_filter($row,static fn($v)=>$v!==null&&trim((string)$v)!==''))continue;
                $this->total_records++;$number=$this->total_records;
                $owns=!$this->conn->inTransaction();$rowStarted=false;
                try {
                    if(count($row)<7||strlen(trim((string)$row[0]))<8||trim((string)$row[2])===''||trim((string)$row[4])==='')throw new InvalidArgumentException('Check the required fields and use a password of at least 8 characters.');
                    $role=trim((string)$row[6])?:'student';if(!in_array($role,['student','guidance_advocate','admin','examinee'],true))throw new InvalidArgumentException('Choose a supported role.');
                    $sid=trim((string)$row[5]);
                    if($owns)$this->conn->beginTransaction();else $this->conn->exec('SAVEPOINT import_row');$rowStarted=true;
                    if($role==='student'&&$sid!==''){$q=$this->conn->prepare('SELECT id FROM student_profiles WHERE student_id=?');$q->execute([$sid]);if($q->fetchColumn())throw new InvalidArgumentException('Student ID already exists.');}
                    $user=new User($this->conn);$user->password=trim((string)$row[0]);$user->email=trim((string)$row[1]);$user->first_name=trim((string)$row[2]);$user->middle_name=trim((string)$row[3])?:null;$user->last_name=trim((string)$row[4]);$user->role=$role;
                    if(!$user->register())throw new RuntimeException('Could not create this account.');$uid=$this->conn->lastInsertId();
                    if($role==='student'&&$sid!=='')$this->conn->prepare('INSERT INTO student_profiles(user_id,student_id) VALUES(?,?)')->execute([$uid,$sid]);
                    if($owns)$this->conn->commit();else $this->conn->exec('RELEASE SAVEPOINT import_row');$this->successful_imports++;
                }catch(Throwable $e){if($rowStarted&&$owns&&$this->conn->inTransaction())$this->conn->rollBack();elseif($rowStarted&&!$owns&&$this->conn->inTransaction())$this->conn->exec('ROLLBACK TO SAVEPOINT import_row');$this->failed_imports++;$errors[]='Row '.$number.': '.($e instanceof PDOException?'The account could not be saved. Check for duplicate account details.':$e->getMessage());}
            }
        }finally{fclose($handle);}
        $this->error_log=implode("\n",$errors);return $this->create();
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
        $sampleFolder=dirname(__DIR__,2).'/storage/uploads';if(!is_dir($sampleFolder))mkdir($sampleFolder,0775,true);
        $file = fopen($sampleFolder.'/sample_user_import.csv','w');
        foreach ($sample as $row) fputcsv($file, $row);
        fclose($file);
        return 'sample_user_import.csv';
    }

    private function validateHeaders($actual, $expected) {
        if (!is_array($actual) || count($actual) < count($expected)) return false;
        for ($i = 0; $i < count($expected); $i++) {
            if (strtolower(trim($actual[$i])) !== strtolower($expected[$i])) return false;
        }
        return true;
    }
}
?>
