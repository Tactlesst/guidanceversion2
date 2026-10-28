<?php
function studentRecordSummary(PDO $db,int $id,bool $canViewPds): ?array {
    $q=$db->prepare("SELECT u.id,u.first_name,u.middle_name,u.last_name,u.email,u.role,u.is_active,u.created_at,u.updated_at,sp.student_id,sp.department,sp.strand,sp.program,sp.grade_level,sp.updated_at AS academic_updated_at FROM users u LEFT JOIN student_profiles sp ON sp.user_id=u.id WHERE u.id=? AND u.role='student' AND COALESCE(u.archived,0)=0 LIMIT 1");$q->execute([$id]);$record=$q->fetch(PDO::FETCH_ASSOC);if(!$record)return null;
    $q=$db->prepare('SELECT id,education_level,school_year,created_at,updated_at FROM pds WHERE user_id=? ORDER BY updated_at DESC,id DESC LIMIT 1');$q->execute([$id]);$pds=$q->fetch(PDO::FETCH_ASSOC);
    $record['pds_years']=[];$record['pds_version_count']=0;
    if($canViewPds){require_once __DIR__.'/pds_history.php';$record['pds_years']=(new PDSHistory($db))->annual($id);$count=$db->prepare('SELECT COUNT(*) FROM pds_versions WHERE user_id=?');$count->execute([$id]);$record['pds_version_count']=(int)$count->fetchColumn();}
    $record['can_view_pds_history']=$canViewPds&&$record['pds_version_count']>0;
    $record['has_pds']=(bool)$pds;$record['pds']=$pds?:null;$record['can_view_pds']=$canViewPds&&(bool)$pds;return $record;
}
function saveStudentAcademicAssignment(PDO $db,array $input): void {
    $id=filter_var($input['user_id']??null,FILTER_VALIDATE_INT);if(!$id||$id<1)throw new InvalidArgumentException('Choose a valid student.');
    $record=studentRecordSummary($db,$id,false);if(!$record)throw new InvalidArgumentException('This student record is unavailable.');
    $values=[];foreach(['department','strand','program','grade_level'] as $field){$value=$input[$field]??'';if(!is_string($value)||mb_strlen($value)>150)throw new InvalidArgumentException('Choose valid academic values.');$values[$field]=trim($value);}
    $q=$db->prepare('SELECT id,name FROM academic_departments WHERE name=? AND is_active=1 LIMIT 1');$q->execute([$values['department']]);$department=$q->fetch(PDO::FETCH_ASSOC);
    if(!$department)throw new InvalidArgumentException('Choose an active department.');
    $lookup=static function($table)use($db,$department){$q=$db->prepare("SELECT name FROM $table WHERE department_id=? AND is_active=1 ORDER BY sort_order,name");$q->execute([$department['id']]);return $q->fetchAll(PDO::FETCH_COLUMN);};
    $grades=$lookup('academic_grade_levels');$strands=$lookup('academic_strands');$programs=$lookup('academic_programs');
    if($grades&&!in_array($values['grade_level'],$grades,true))throw new InvalidArgumentException('Choose a grade or year level for this department.');
    if(!$grades)$values['grade_level']='';
    if($strands){if(!in_array($values['strand'],$strands,true))throw new InvalidArgumentException('Choose a strand for this department.');$values['program']='';}
    elseif($programs){if(!in_array($values['program'],$programs,true))throw new InvalidArgumentException('Choose a program for this department.');$values['strand']='';}
    else{$values['strand']='';$values['program']='';}
    $q=$db->prepare('SELECT id FROM student_profiles WHERE user_id=? LIMIT 1');$q->execute([$id]);$exists=$q->fetchColumn();
    if($exists){$q=$db->prepare('UPDATE student_profiles SET department=?,strand=?,program=?,grade_level=?,updated_at=CURRENT_TIMESTAMP WHERE user_id=?');}
    else{$q=$db->prepare('INSERT INTO student_profiles (department,strand,program,grade_level,user_id) VALUES (?,?,?,?,?)');}
    $q->execute([$values['department'],$values['strand']?:null,$values['program']?:null,$values['grade_level']?:null,$id]);
}
