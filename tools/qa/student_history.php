<?php
declare(strict_types=1);

require_once __DIR__.'/bootstrap.php';
require QA_ROOT.'/config/database.php';require QA_ROOT.'/includes/pds/editor_store.php';require QA_ROOT.'/includes/students/record_support.php';require QA_ROOT.'/classes/reporting/ReportApproval.php';require __DIR__.'/support/pds_tables.php';
$db=(new Database)->getConnection();$checks=[];
foreach(['users','student_profiles','pds','pds_siblings','pds_organizations','pds_test_results','pds_versions','pds_annual_snapshots','notifications','email_outbox','system_settings','system_logs','report_approvals','academic_departments','academic_grade_levels','academic_strands','academic_programs'] as $table)pdsWizardTempTable($db,$table);
$db->exec('ALTER TABLE pds_versions ADD UNIQUE KEY fixture_version(user_id,version_no)');$db->exec('ALTER TABLE pds_annual_snapshots ADD UNIQUE KEY fixture_year(user_id,school_year)');$db->exec('ALTER TABLE notifications ADD UNIQUE KEY event_owner(user_id,dedupe_key)');$db->exec('ALTER TABLE email_outbox ADD UNIQUE KEY event_key(dedupe_key)');
foreach([93001=>'student',93002=>'admin',93003=>'guidance_advocate',93004=>'student',93005=>'super_admin'] as $id=>$role)$db->prepare('INSERT INTO users(id,role,is_active,archived,first_name,last_name,email) VALUES(?,?,1,0,?,?,NULL)')->execute([$id,$role,'Synthetic','Fixture']);
$db->exec("INSERT INTO student_profiles(user_id,student_id,department,grade_level,program) VALUES(93001,'TEST-ONLY','Higher Education','1st Year College','BSIT')");$db->exec("INSERT INTO system_settings(setting_key,setting_value) VALUES('pds_enabled',1)");
function check($name,$ok){global $checks;if(!$ok)throw new RuntimeException('FAILED: '.$name);$checks[]=$name;}
function rejects($name,$fn){$rejected=false;try{$fn();}catch(RuntimeException|InvalidArgumentException $e){$rejected=true;}check($name,$rejected);}
$store=new PDSEditorStore($db,QA_CASE_DIR.'/fixture-uploads');$history=new PDSHistory($db);$uid=93001;
$state=$store->save($uid,'highered',['school_year'=>'2025-2026','home_address'=>'Original synthetic address'],[],PDSEditorStore::revision($store->load($uid)));$old=$history->latest($uid);
$history->renew($uid,'2026-2027',PDSEditorStore::revision($state));$state=$store->load($uid);$store->save($uid,'highered',['home_address'=>'New synthetic address'],[],PDSEditorStore::revision($state));
$record=studentRecordSummary($db,$uid,true);check('Student summary includes every saved school year',count($record['pds_years'])===2&&array_column($record['pds_years'],'school_year')===['2026-2027','2025-2026']);
check('Current PDS remains available beside history',$record['pds']['school_year']==='2026-2027'&&$record['can_view_pds']);
check('All revisions counted independently of annual records',$record['pds_version_count']===3&&$record['can_view_pds_history']);
check('Summary does not expose snapshot answers',!str_contains(json_encode($record),'synthetic address')&&!array_key_exists('snapshot_json',$record['pds_years'][0]));
$previous=$history->version($uid,(int)$old['id']);check('Previous school year retains original address',$previous['state']['pds']['home_address']==='Original synthetic address');
check('Past school year remains closed',!empty($record['pds_years'][1]['closed_at']));
foreach([93002,93003,93005] as $staff){check('Staff may read PDS history '.$staff,(int)PDSHistory::authorize($db,$staff,$uid)['id']===$staff);}
check('Student can still view own history',(int)PDSHistory::authorize($db,$uid,$uid)['id']===$uid);rejects('Other student cannot view history',fn()=>PDSHistory::authorize($db,93004,$uid));
$db->exec('UPDATE users SET is_active=0 WHERE id=93003');rejects('Inactive advocate denied history',fn()=>PDSHistory::authorize($db,93003,$uid));$db->exec('UPDATE users SET is_active=1 WHERE id=93003');
$adv=OfficePolicy::actor($db,93003);OfficePolicy::enforce($db,$adv,'pds_history',[]);check('Advocate may enter history route',true);OfficePolicy::enforce($db,$adv,'pds_history',['history_action'=>'print']);check('Advocate print route proceeds to protected approval check',true);rejects('Advocate cannot renew or change student annual PDS',fn()=>OfficePolicy::enforce($db,$adv,'pds_history',['history_action'=>'renew']));
OfficePolicy::enforce($db,$adv,'student_records',['update_student_profile'=>1]);check('Advocate academic assignment update restored',true);OfficePolicy::enforce($db,$adv,'release_results',['total_score'=>1]);check('Advocate result update restored',true);rejects('Booking limit controls remain denied',fn()=>OfficePolicy::enforce($db,$adv,'schedules',['set_daily_limit'=>1]));
$approval=new ReportApproval($db);$scope=['user_id'=>$uid,'version'=>(int)$old['id'],'paper'=>'long'];$request=$approval->request(93003,'pds_version','print',$scope);
rejects('Pending historic print approval cannot be used',fn()=>$approval->consume(93003,(int)$request['id'],'pds_version','print',$scope));$approval->decide(93002,(int)$request['id'],'approved');
rejects('Approval cannot print another version',fn()=>$approval->consume(93003,(int)$request['id'],'pds_version','print',array_replace($scope,['version'=>(int)$old['id']+1])));
rejects('Approval cannot print another student',fn()=>$approval->consume(93003,(int)$request['id'],'pds_version','print',array_replace($scope,['user_id'=>93004])));
rejects('Approval cannot change paper size',fn()=>$approval->consume(93003,(int)$request['id'],'pds_version','print',array_replace($scope,['paper'=>'a4'])));
$approval->consume(93003,(int)$request['id'],'pds_version','print',$scope);check('Matching saved-version permission consumed once',$approval->find((int)$request['id'],93003)['status']==='used');rejects('Historic permission cannot be reused',fn()=>$approval->consume(93003,(int)$request['id'],'pds_version','print',$scope));
$db->exec("INSERT INTO academic_departments(id,name,is_active) VALUES(1,'Higher Education',1)");$db->exec("INSERT INTO academic_grade_levels(name,department_id,is_active) VALUES('1st Year College',1,1),('2nd Year College',1,1)");$db->exec("INSERT INTO academic_programs(name,department_id,is_active) VALUES('BSIT',1,1)");
saveStudentAcademicAssignment($db,['user_id'=>$uid,'department'=>'Higher Education','grade_level'=>'2nd Year College','program'=>'BSIT']);check('Academic assignment saves valid values',$db->query('SELECT grade_level FROM student_profiles WHERE user_id=93001')->fetchColumn()==='2nd Year College');
check('Academic updates preserve historical academic context',$history->version($uid,(int)$old['id'])['state']['context']['profile_grade']==='1st Year College');check('Viewing history and assigning academics does not rewrite PDS history',$history->versions($uid)['total']===3);
file_put_contents(QA_RESULTS.'/Student-PDS-History-and-Advocate-Checks.json',json_encode($checks,JSON_PRETTY_PRINT));file_put_contents(QA_CASE_DIR.'/summary-fixture.json',json_encode($record));
echo count($checks)." isolated PDS history, frozen-data, Advocate access, print approval and academic-update checks passed. No production data or emails changed.\n";
