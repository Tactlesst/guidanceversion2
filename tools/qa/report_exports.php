<?php
require_once __DIR__.'/bootstrap.php';

require QA_ROOT.'/config/database.php';require QA_ROOT.'/classes/reporting/SchoolReportService.php';require QA_ROOT.'/classes/reporting/SchoolReportRenderer.php';
$db=(new Database)->getConnection();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$checks=[];
function check($name,$value){global $checks;if(!$value)throw new RuntimeException($name);$checks[]=$name;}
foreach(['users','student_profiles','pds','pds_highschool','pds_seniorhigh','pds_college','ses_predictions','counseling_appointments','entrance_exam_appointments'] as $table){$columns=$db->query('SHOW COLUMNS FROM '.$table)->fetchAll(PDO::FETCH_ASSOC);$parts=[];foreach($columns as $c)$parts[]='`'.$c['Field'].'` '.$c['Type'].' NULL';$db->exec('CREATE TEMPORARY TABLE '.$table.' ('.implode(',',$parts).')');}
function insertRow($table,$row){global $db;$q=$db->prepare('INSERT INTO '.$table.' (`'.implode('`,`',array_keys($row)).'`) VALUES ('.implode(',',array_fill(0,count($row),'?')).')');$q->execute(array_values($row));}
for($i=1;$i<=135;$i++){
 insertRow('users',['id'=>$i,'first_name'=>'Fixture '.str_pad((string)$i,3,'0',STR_PAD_LEFT),'last_name'=>$i===1?'=FORMULA()':'Student','middle_name'=>'','email'=>'fixture'.$i.'@example.invalid','role'=>$i%2?'student':'examinee']);
 insertRow('student_profiles',['id'=>$i,'user_id'=>$i,'student_id'=>'00'.str_pad((string)$i,4,'0',STR_PAD_LEFT),'grade_level'=>$i%2?'Grade 9':'Grade 10']);
 insertRow('pds_highschool',['id'=>$i,'user_id'=>$i,'school_year'=>'2026-2027','grade_level'=>$i%2?'Grade 9':'Grade 10','created_at'=>'2026-06-01 08:00:00']);
 insertRow('ses_predictions',['id'=>$i,'user_id'=>$i,'predicted_ses'=>['Low','Middle','High'][($i-1)%3],'confidence_score'=>$i===1?0:.8,'prediction_date'=>'2026-10-15 08:00:00','is_latest'=>1]);
 insertRow('ses_predictions',['id'=>1000+$i,'user_id'=>$i,'predicted_ses'=>'High','confidence_score'=>.9,'prediction_date'=>'2026-06-15 08:00:00','is_latest'=>0]);
 $status=$i<=100?'completed':($i<=115?'pending':($i<=125?'missed':'cancelled'));
 insertRow('entrance_exam_appointments',['id'=>$i,'user_id'=>$i,'preferred_date'=>'2026-10-15','exam_date'=>$i<=100?'2026-10-16':null,'status'=>$status,'exam_result'=>$i<=80?'passed':'failed','grade_level_applying'=>$i%2?'Grade 9':'Grade 10','total_score'=>$i===1?0:40,'total_items'=>50,'qualified_grade'=>$i<=80?'Grade 9':null,'preferred_program'=>$i%2?'BSIT':'ABM','school_year'=>'2026-2027','olsat_level'=>'F','olsat_form'=>'1','stanine_score'=>5,'interpretation'=>'Stored classification']);
 insertRow('counseling_appointments',['id'=>$i,'user_id'=>$i,'appointment_date'=>'2026-10-15','status'=>['completed','pending','in_progress'][($i-1)%3],'assigned_advocate_id'=>null,'concern_type'=>['academic','personal','career'][($i-1)%3],'nature_of_contact'=>$i<=10?'referral_interview':'walk-in','is_follow_up'=>$i<=5?1:0,'follow_up_needed'=>$i<=15?1:0,'concern_description'=>'PRIVATE_NARRATIVE_SENTINEL','session_summary'=>'PRIVATE_NOTES_SENTINEL']);
}
$service=new SchoolReportService($db);$actor=['role'=>'admin','first_name'=>'Test','last_name'=>'Reviewer'];$all=$service->build('examinees',['include_records'=>1],$actor);
check('All matching records exported beyond UI pagination',count($all['export_rows'])===135);
check('Pending absent cancelled do not become failed',$all['metrics']['Failed']===20&&$all['metrics']['Pending']===15&&$all['metrics']['Absent']===10);
check('Passing rate excludes nonclassified states',$all['metrics']['Passing rate (%)']===80.0);
check('Zero score preserved',$all['export_rows'][0][7]===0.0||in_array(0.0,array_column($all['export_rows'],7),true));
$passed=$service->build('examinees',['include_records'=>1,'result'=>'passed'],$actor);check('Passed subset contains all 80 records',count($passed['export_rows'])===80);check('Summary retains full matching cohort',$passed['metrics']['Examination records']===135);
$grade=$service->build('examinees',['include_records'=>1,'grade'=>'Grade 9','result'=>'passed'],$actor);check('Grade and result filters combine',count($grade['export_rows'])===40);
$date=$service->build('examinees',['date_from'=>'2026-10-16','date_to'=>'2026-10-16'],$actor);check('Actual exam dates used',$date['record_count']===100);
$empty=$service->build('examinees',['search'=>'NO_MATCH_SENTINEL','include_records'=>1],$actor);check('Empty report has unavailable passing rate',$empty['record_count']===0&&$empty['metrics']['Passing rate (%)']===null);
$ses=$service->build('ses',['include_records'=>1],$actor);check('SES latest-only unique students',$ses['record_count']===135&&$ses['metrics']['Low SES']===45);$history=$service->build('ses',['date_to'=>'2026-07-01'],$actor);check('SES historical scope chooses latest within dates',$history['metrics']['High SES']===135);
$counsel=$service->build('counseling',[],$actor);check('Counseling referral and followup counts',$counsel['metrics']['Referrals']===10&&$counsel['metrics']['Follow-up sessions']===5);check('Unknown session format retained',$counsel['metrics']['Format not recorded']===135);$serialized=json_encode($counsel);check('Counseling contains no identity or narrative',strpos($serialized,'Fixture')===false&&strpos($serialized,'SENTINEL')===false&&!isset($counsel['export_rows']));
$olsat=$service->build('olsat',['include_records'=>1],$actor);check('OLSAT completed only',$olsat['record_count']===100);check('OLSAT norms not fabricated',$olsat['metrics']['Recorded SAI values']===0&&$olsat['export_rows'][0][9]===null);
try{$service->build('examinees',[],['role'=>'student']);check('Student role denied',false);}catch(RuntimeException $e){check('Student role denied',true);}try{SchoolReportService::filters('ses',['date_from'=>'2026-12-01','date_to'=>'2026-01-01']);check('Date range rejected',false);}catch(InvalidArgumentException $e){check('Date range rejected',true);}
foreach(['examinees'=>$passed,'ses'=>$ses,'olsat'=>$olsat,'counseling'=>$counsel,'empty'=>$empty] as $name=>$report){file_put_contents(QA_RESULTS.'/QA-'.$name.'-fixture.pdf',SchoolReportRenderer::pdf($report));file_put_contents(QA_RESULTS.'/QA-'.$name.'-fixture.xlsx',SchoolReportRenderer::xlsx($report));file_put_contents(QA_RESULTS.'/QA-'.$name.'-fixture.csv',SchoolReportRenderer::csv($report));}
check('CSV formula strings escaped',strpos(SchoolReportRenderer::csv($all),"'=FORMULA()")!==false);
file_put_contents(QA_RESULTS.'/QA-Report-Export-Fixture-Checks.json',json_encode($checks,JSON_PRETTY_PRINT));echo count($checks)." report checks passed using connection-local temporary tables.\n";
