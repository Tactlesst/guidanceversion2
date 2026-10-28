<?php
// Back up the three survey tables before running this command-line-only migration.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../../config/database.php';$db=(new Database)->getConnection();
foreach(['multiple_intelligence_survey','learning_style_inventory','learning_style_inventory_intermediate'] as $table){
    $columns=array_column($db->query('SHOW COLUMNS FROM '.$table)->fetchAll(PDO::FETCH_ASSOC),'Field');
    foreach(['school_year'=>"VARCHAR(9) NOT NULL DEFAULT ''",'profile_snapshot'=>'LONGTEXT NULL'] as $column=>$ddl)if(!in_array($column,$columns,true))$db->exec("ALTER TABLE $table ADD COLUMN $column $ddl");
    if($table==='multiple_intelligence_survey'&&!in_array('user_id',$columns,true))$db->exec("ALTER TABLE $table ADD COLUMN user_id INT NULL");
    if($table==='learning_style_inventory'&&!in_array('answers_json',$columns,true))$db->exec("ALTER TABLE $table ADD COLUMN answers_json TEXT NULL");
    $indexes=array_column($db->query('SHOW INDEX FROM '.$table)->fetchAll(PDO::FETCH_ASSOC),'Key_name');
    if(!in_array('annual_owner_survey',$indexes,true))$db->exec("ALTER TABLE $table ADD UNIQUE KEY annual_owner_survey(user_id,school_year)");
    if(!in_array('survey_school_year',$indexes,true))$db->exec("ALTER TABLE $table ADD KEY survey_school_year(school_year,completed_at)");
    if($table==='multiple_intelligence_survey'&&!in_array('annual_student_survey',$indexes,true))$db->exec("ALTER TABLE $table ADD UNIQUE KEY annual_student_survey(student_id,school_year)");
    $oldKey=$table==='multiple_intelligence_survey'?'unique_student_survey':'unique_user_survey';if(in_array($oldKey,$indexes,true))$db->exec("ALTER TABLE $table DROP INDEX $oldKey");
}
// Resolve existing ownership only where the stored student ID has exactly one owner.
// Preserve original timestamps; the actual historical school year remains unknown.
$db->exec('UPDATE multiple_intelligence_survey a JOIN (SELECT student_id,MIN(user_id) AS user_id FROM student_profiles GROUP BY student_id HAVING COUNT(*)=1) sp ON sp.student_id=a.student_id SET a.user_id=sp.user_id,a.updated_at=a.updated_at WHERE a.user_id IS NULL');
echo "Annual survey storage ready; existing scores and answers retained. Historical years left unassigned.\n";
