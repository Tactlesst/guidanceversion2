<?php
/** Read-only choices. Academic labels remain owned by Academic Settings. */
final class PDSEditorOptions {
    public static function schoolYears(?int $year=null): array {
        $year=$year??(int)date('Y');$years=[];
        for($start=$year+2;$start>=$year-60;$start--)$years[]=$start.'-'.($start+1);
        return $years;
    }
    public static function load(PDO $db,array $profile): array {
        $options=[
            'school_year'=>self::schoolYears(),
            'grade_level'=>[],'course'=>[],'strand'=>[],'year_level'=>[],
            'semester'=>['1st Semester','2nd Semester','Summer / Midyear'],
            'suffix'=>['Jr.','Sr.','II','III','IV','V'],
            'civil_status'=>['Single','Married','Widowed','Separated','Annulled'],
            'citizenship'=>['Filipino','Others'],
            'religion'=>['Roman Catholic','Islam','Iglesia ni Cristo','Aglipayan','Protestant / Christian','Evangelical Christian','Baptist','Seventh-day Adventist','Jehovah’s Witnesses','Buddhism','Hinduism','None','Others']
        ];
        try {
            $q=$db->prepare('SELECT id FROM academic_departments WHERE name=? AND is_active=1 LIMIT 1');
            $q->execute([$profile['department']??'']);$department=$q->fetchColumn();
            if($department){
                foreach(['grade_level'=>'academic_grade_levels','course'=>'academic_programs','strand'=>'academic_strands'] as $field=>$table){
                    // Unassigned programs/strands in the existing settings are shared choices.
                    $scope=$field==='grade_level'?'department_id=?':'(department_id=? OR department_id IS NULL)';
                    $q=$db->prepare('SELECT name FROM '.$table.' WHERE is_active=1 AND '.$scope.' ORDER BY sort_order,name');
                    $q->execute([$department]);$options[$field]=array_values(array_unique(array_map('strval',$q->fetchAll(PDO::FETCH_COLUMN))));
                }
                $options['year_level']=$options['grade_level'];
            }
        }catch(PDOException $e){
            // Keep saved answers accessible if the academic catalog is unavailable.
            error_log('PDS academic choices could not be loaded.');
        }
        return $options;
    }
}
