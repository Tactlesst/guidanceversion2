<?php
require_once __DIR__.'/bootstrap.php';

require QA_ROOT.'/config/database.php';
require __DIR__.'/support/pds_tables.php';
require QA_ROOT.'/classes/reporting/SchoolReportService.php';
$db=(new Database)->getConnection();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
foreach(['users','student_profiles','pds','pds_highschool','pds_seniorhigh','pds_college','ses_predictions','counseling_appointments','entrance_exam_appointments'] as $table)pdsWizardTempTable($db,$table);
$checks=[];function check($label,$ok){global $checks;if(!$ok)throw new RuntimeException($label);$checks[]=$label;}
function put($table,$row){global $db;$stmt=$db->prepare('INSERT INTO '.$table.' (`'.implode('`,`',array_keys($row)).'`) VALUES ('.implode(',',array_fill(0,count($row),'?')).')');$stmt->execute(array_values($row));}
$actor=['role'=>'admin','first_name'=>'Fixture','last_name'=>'Reviewer'];
foreach(['kinder','elementary','highschool','seniorhigh','highered'] as $index=>$education){$id=$index+1;put('users',['id'=>$id,'role'=>'student','first_name'=>'Fixture','last_name'=>(string)$id]);put('student_profiles',['user_id'=>$id,'student_id'=>'F'.$id,'grade_level'=>'Fixture grade']);put('pds',['user_id'=>$id,'education_level'=>$education,'school_year'=>'2026-2027','grade_level'=>'Fixture grade','family_income'=>$id===2?null:'P 10,001 - P13,000','created_at'=>'2026-06-01 08:00:00','updated_at'=>'2026-10-10 08:00:00']);}
// An old estimate must not stay current after the PDS was edited.
put('ses_predictions',['user_id'=>1,'predicted_ses'=>'High','confidence_score'=>.8,'model_used'=>'Legacy test method','prediction_date'=>'2026-09-01 08:00:00','is_latest'=>1]);
$before=(int)$db->query('SELECT COUNT(*) FROM ses_predictions')->fetchColumn();
$report=(new SchoolReportService($db))->build('ses',['include_records'=>1],$actor);
check('All five education levels included automatically',$report['record_count']===5);
check('Stale estimates are not counted as current classifications',$report['metrics']['High SES']===0&&$report['metrics']['Unclassified']===5);
check('Blank income is not guessed',$report['metrics']['Income recorded']===4);
check('Record coverage is distinguished from completed surveys',$report['metrics']['PDS records']===5&&$report['metrics']['Records included']===5&&$report['metrics']['Income not recorded']===1&&!array_key_exists('Students surveyed',$report['metrics']));
check('No new predictions are written by reporting',(int)$db->query('SELECT COUNT(*) FROM ses_predictions')->fetchColumn()===$before);
check('CSV/Excel records include saved income and its classification source',count($report['export_rows'])===5&&in_array('Family monthly income',$report['export_columns'],true)&&in_array('Classification source',$report['export_columns'],true));
check('Printed income distribution uses actual PDS brackets',count(array_filter($report['tables'],static fn($table)=>$table['title']==='Family monthly income from PDS'&&count($table['rows'])===2))===1);
check('Recorded income is presented before classification charts',$report['charts'][0]['title']==='Family monthly income from PDS');
check('Unclassified records do not generate empty confidence charts',!in_array('Prediction confidence',array_column($report['charts'],'title'),true));
check('Detailed saved PDS income appears first',$report['tables'][0]['title']==='PDS income and classification source');
$db->exec("UPDATE pds SET family_income='Above P30,000' WHERE user_id=3");
$next=(new SchoolReportService($db))->build('ses',['include_records'=>1],$actor);check('A PDS edit updates the report without a manual prediction step',in_array('Above PHP 30,000',array_column($next['export_rows'],8),true));
$filter=(new SchoolReportService($db))->build('ses',['category'=>'Unclassified','school_year'=>'2026-2027','grade'=>'Fixture grade'], $actor);check('Academic and category filters combine for PDS summaries',$filter['record_count']===5);
$historic=(new SchoolReportService($db))->build('ses',['date_to'=>'2026-09-30','include_records'=>1],$actor);check('Historic estimate is preserved but current income is not backdated',$historic['record_count']===1&&$historic['metrics']['High SES']===1&&$historic['export_rows'][0][8]==='Not recorded');
$db->exec("UPDATE pds SET updated_at='2026-06-01 08:00:00' WHERE user_id=1");
$unchanged=(new SchoolReportService($db))->build('ses',['include_records'=>1],$actor);check('An unchanged legacy estimate remains identified as an unverified estimate',$unchanged['metrics']['High SES']===1&&count(array_filter($unchanged['export_rows'],static fn($row)=>$row[9]==='Recorded estimate; rules not verified'))===1);
$db->exec("UPDATE pds SET family_income=NULL WHERE user_id=4");$blank=(new SchoolReportService($db))->build('ses',[],$actor);check('Removing income updates coverage without assigning a low income',$blank['metrics']['Income recorded']===3);
foreach([
 ['completed','F','A'],['completed',null,null],['completed','F',null],['pending','F','A'],['completed','N/A','A'],['completed',null,'A']
] as $index=>$test){put('entrance_exam_appointments',['id'=>101+$index,'user_id'=>1,'preferred_date'=>'2026-10-11','exam_date'=>'2026-10-11','status'=>$test[0],'olsat_level'=>$test[1],'olsat_form'=>$test[2],'total_score'=>25,'total_items'=>50,'stanine_score'=>5,'school_year'=>'2026-2027','grade_level_applying'=>'Fixture grade']);}
$olsat=(new SchoolReportService($db))->build('olsat',['include_records'=>1],$actor);
check('Only completed explicitly identified OLSAT assessments included',$olsat['record_count']===1);
check('OLSAT raw percentage is calculated from recorded scores',$olsat['metrics']['Average raw score (%)']===50.0);
check('Unprovided SAI and percentile remain unavailable',$olsat['metrics']['Recorded SAI values']===0&&$olsat['export_rows'][0][9]===null&&$olsat['export_rows'][0][10]===null);
check('Generic entrance examinations remain in the separate examinee report',(new SchoolReportService($db))->build('examinees',[],$actor)['record_count']===6);
check('Exam names identify OLSAT explicitly',str_starts_with($olsat['export_rows'][0][13],'OLSAT assessment'));
try{(new SchoolReportService($db))->build('ses',[],['role'=>'student']);check('Student cannot read staff SES report',false);}catch(RuntimeException $e){check('Student cannot read staff SES report',true);}
require QA_ROOT.'/classes/reporting/SchoolReportRenderer.php';
$svg=SchoolReportRenderer::svg(['title'=>'All income groups','kind'=>'bar','labels'=>array_map(static fn($n)=>'Income band '.$n,range(1,10)),'values'=>array_fill(0,10,1)]);
check('Print charts include all ten income groups',substr_count($svg,'<rect ')===10&&str_contains($svg,'Income band 10'));
$csv=SchoolReportRenderer::csv($report);check('CSV contains automatic income summaries',str_contains($csv,'Family monthly income from PDS')&&str_contains($csv,'Unclassified'));
$xlsx=SchoolReportRenderer::xlsx($report);check('Excel export remains a real workbook',str_starts_with($xlsx,'PK'));
$pdf=SchoolReportRenderer::pdf($report);check('PDF export contains the updated report',str_starts_with($pdf,'%PDF-')&&strlen($pdf)>10000);
file_put_contents(QA_CASE_DIR.'/ses-fixture.pdf',$pdf);file_put_contents(QA_CASE_DIR.'/ses-fixture.xlsx',$xlsx);
file_put_contents(QA_RESULTS.'/Report-Automation-Checks.json',json_encode($checks,JSON_PRETTY_PRINT));echo count($checks)." automatic PDS income, historical consistency, OLSAT separation, export and authorization checks passed using temporary tables.\n";
