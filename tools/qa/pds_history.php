<?php
declare(strict_types=1);

require_once __DIR__.'/bootstrap.php';
require QA_ROOT.'/config/database.php';
require QA_ROOT.'/includes/pds/editor_store.php';
require QA_ROOT.'/includes/pds/signature_access.php';
require QA_ROOT.'/includes/pds/digital_view.php';
require __DIR__.'/support/pds_tables.php';
$db=(new Database)->getConnection();$checks=[];
foreach(['pds','pds_siblings','pds_organizations','pds_test_results','users','student_profiles','pds_versions','pds_annual_snapshots','notifications','email_outbox','system_settings','system_logs'] as $table)pdsWizardTempTable($db,$table);
$db->exec('ALTER TABLE pds_versions ADD UNIQUE KEY fixture_version(user_id,version_no)');
$db->exec('ALTER TABLE pds_annual_snapshots ADD UNIQUE KEY fixture_annual(user_id,school_year)');
$db->exec("INSERT INTO system_settings(setting_key,setting_value) VALUES('pds_enabled','1')");
foreach([91001=>'student',91002=>'student',91003=>'admin',91004=>'guidance_advocate',91005=>'super_admin',91006=>'examinee'] as $id=>$role)$db->prepare('INSERT INTO users(id,role,is_active,archived,first_name,last_name,email) VALUES(?,?,1,0,?,?,NULL)')->execute([$id,$role,'Synthetic','Fixture']);
$db->exec("INSERT INTO student_profiles(user_id,student_id,department,grade_level,program) VALUES(91001,'TEST-ONLY','Higher Education','1st Year College','Fixture course')");
$uid=91001;$store=new PDSEditorStore($db,QA_CASE_DIR.'/fixture-uploads');$history=new PDSHistory($db);
function check(string $name,bool $ok):void{global $checks;if(!$ok)throw new RuntimeException('FAILED: '.$name);$checks[]=$name;}
function saveFixture(array $post):array{global $uid,$store;return $store->save($uid,'highered',$post,[],PDSEditorStore::revision($store->load($uid)));}
function rejectFixture(string $name,callable $action):void{global $store,$uid,$history;$before=PDSEditorStore::revision($store->load($uid));$count=$history->versions($uid)['total'];try{$action();}catch(InvalidArgumentException|RuntimeException $e){check($name,$before===PDSEditorStore::revision($store->load($uid))&&$count===$history->versions($uid)['total']);return;}throw new RuntimeException('Unexpected acceptance: '.$name);}
function currentCount():int{global $history,$uid;return $history->versions($uid)['total'];}

// A fixture image is used only in connection-local temporary tables, never a real user's signature.
$oldSig='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j7gAAAABJRU5ErkJggg==';
$fixture=['user_id'=>$uid,'education_level'=>'highered','school_year'=>'2025-2026','first_name'=>'Synthetic','last_name'=>'Fixture','birth_date'=>'2000-01-02','home_address'=>'Old fixture address','father_status'=>'Employed','mother_status'=>'Retired','privacy_agreement'=>1,'privacy_agreement_date'=>'2025-06-02 08:00:00','student_signature'=>$oldSig,'parent_signature'=>$oldSig,'student_signature_name'=>'Synthetic fixture only','parent_signature_name'=>'Synthetic parent only','student_date_signed'=>'2025-06-02','parent_date_signed'=>'2025-06-02','family_income'=>'P 10,001 - P13,000','student_photo'=>$oldSig,'special_talents'=>'<script>fixture</script>'];
$fields=implode(',',array_keys($fixture));$db->prepare('INSERT INTO pds('.$fields.') VALUES('.implode(',',array_fill(0,count($fixture),'?')).')')->execute(array_values($fixture));
foreach(PDSEditorSchema::RELATED as $group=>$def){$name=match($group){'siblings'=>'sibling_name','organizations'=>'organization_name','tests'=>'test_name'};$db->prepare('INSERT INTO '.$def['table'].'(user_id,'.$name.') VALUES(?,?)')->execute([$uid,'Original fixture '.$group]);}
$oldState=$store->load($uid);$history->baseline($uid,$oldState);$v1=$history->latest($uid);$saved=$history->version($uid,(int)$v1['id']);
check('Baseline captures every PDS column without altering it',$saved['state']['pds']===$oldState['pds']);
foreach(PDSEditorSchema::RELATED as $group=>$_)check('Baseline preserves '.$group,$saved['state'][$group]===$oldState[$group]);
check('Baseline freezes school/account context',$saved['state']['context']['profile_program']==='Fixture course');
$history->baseline($uid,$oldState);check('Baseline import is idempotent',currentCount()===1);
rejectFixture('Invalid nonconsecutive year rejected atomically',fn()=>$history->renew($uid,'2026-2028',PDSEditorStore::revision($oldState)));
rejectFixture('Earlier year rejected atomically',fn()=>$history->renew($uid,'2024-2025',PDSEditorStore::revision($oldState)));
rejectFixture('Renewal requires latest revision',fn()=>$history->renew($uid,'2026-2027',str_repeat('0',64)));
$history->renew($uid,'2026-2027',PDSEditorStore::revision($oldState));$newState=$store->load($uid);
check('New year is created explicitly',$newState['pds']['school_year']==='2026-2027'&&currentCount()===2);
$cleared=['student_signature','parent_signature','student_signature_name','parent_signature_name','student_date_signed','parent_date_signed','privacy_agreement_date'];
foreach($cleared as $field)check('Renewal clears '.$field,$newState['pds'][$field]===null);
check('Renewal clears privacy agreement',(int)$newState['pds']['privacy_agreement']===0);
$preserved=$oldState['pds'];foreach(array_merge($cleared,['school_year','privacy_agreement','updated_at']) as $field)unset($preserved[$field]);$copied=$newState['pds'];foreach(array_merge($cleared,['school_year','privacy_agreement','updated_at']) as $field)unset($copied[$field]);
check('Every other PDS field is copied exactly',$preserved===$copied);
foreach(PDSEditorSchema::RELATED as $group=>$_)check('Renewal copies all '.$group,$newState[$group]===$oldState[$group]);
$annual=array_column($history->annual($uid),null,'school_year');check('Previous year is closed at original snapshot',(int)$annual['2025-2026']['version_id']===(int)$v1['id']&&!empty($annual['2025-2026']['closed_at']));
check('New year begins unreviewed',$annual['2026-2027']['reviewed_at']===null&&$annual['2026-2027']['closed_at']===null);
rejectFixture('Closed annual year rejects new writes',fn()=>$history->record($uid,$oldState,'updated',$uid,true));
$oldKey=hash('sha256',$oldSig);check('Owner can still read previous-year signature',PDSSignatureAccess::read($db,$uid,$oldKey)['mime']==='image/png');check('Admin can read authorized historical signature',PDSSignatureAccess::read($db,91003,$oldKey)['mime']==='image/png');
foreach([91002,91004,91005] as $actor){try{PDSSignatureAccess::read($db,$actor,$oldKey);check('Historical signature denied for role '.$actor,false);}catch(RuntimeException $e){check('Historical signature denied for role '.$actor,true);}}
$state=saveFixture(['home_address'=>'Updated fixture address','father_status'=>'Deceased','siblings'=>[['id'=>$oldState['siblings'][0]['id'],'sibling_name'=>'Updated fixture sibling']],'organizations'=>[['organization_name'=>'New fixture organization']],'tests'=>[['test_name'=>'New fixture test','test_date'=>'2026-10-28']]]);
$latest=$history->version($uid,(int)$history->latest($uid)['id']);
check('Address change is tracked',$latest['changes']['pds.home_address']['before']==='Old fixture address'&&$latest['changes']['pds.home_address']['after']==='Updated fixture address');
check('Deceased family status is tracked',$latest['changes']['pds.father_status']['before']==='Employed'&&$latest['changes']['pds.father_status']['after']==='Deceased');
check('Related row changes and additions are versioned',count($latest['state']['organizations'])===2&&count($latest['state']['tests'])===2&&count($latest['changes'])>=5);
$prior=$history->version($uid,(int)$v1['id']);check('Earlier year retains old address and family status',$prior['state']['pds']['home_address']==='Old fixture address'&&$prior['state']['pds']['father_status']==='Employed');
check('Earlier related rows remain original',$prior['state']['siblings'][0]['sibling_name']==='Original fixture siblings'&&count($prior['state']['organizations'])===1);
$annual=array_column($history->annual($uid),null,'school_year');check('Current save never advances closed annual pointer',(int)$annual['2025-2026']['version_id']===(int)$v1['id']);
$beforeCount=currentCount();saveFixture(['home_address'=>'Updated fixture address','father_status'=>'Deceased']);check('Repeated no-op save creates no extra version',currentCount()===$beforeCount);
rejectFixture('School year cannot overwrite old year through normal save',fn()=>saveFixture(['school_year'=>'2027-2028']));
rejectFixture('Malformed school year rejected',fn()=>saveFixture(['school_year'=>['2026-2027']]));
rejectFixture('Invalid related date rolls back field writes and journal',fn()=>saveFixture(['home_address'=>'Must rollback','tests'=>[['test_name'=>'Bad date','test_date'=>'2026-02-30']]]));
rejectFixture('Unsigned annual review rejected atomically',fn()=>saveFixture(['privacy_agreement'=>'1','_pds_finalize'=>'1']));
rejectFixture('Stale normal save rejected',fn()=>$store->save($uid,'highered',['home_address'=>'Stale'],[],PDSEditorStore::revision($oldState)));

// Drawn/uploaded signatures still need the existing explicit signer confirmation.
$chunk=static function($type,$data){return pack('N',strlen($data)).$type.$data.pack('N',crc32($type.$data));};$pixels='';for($y=0;$y<50;$y++)$pixels.="\0".str_repeat($y===25?"\x10\x10\x10":"\xff\xff\xff",100);$png="\x89PNG\r\n\x1a\n".$chunk('IHDR',pack('NNCCCCC',100,50,8,2,0,0,0)).$chunk('IDAT',gzcompress($pixels)).$chunk('IEND','');$newSig='data:image/png;base64,'.base64_encode($png);
rejectFixture('Drawing without signer authorization rejected',fn()=>saveFixture(['student_signature_drawn'=>$newSig]));
$state=saveFixture(['student_signature_drawn'=>$newSig,'student_signature_confirm'=>'1','student_signature_name'=>'Synthetic fixture only','privacy_agreement'=>'1','_pds_finalize'=>'1']);
$annual=array_column($history->annual($uid),null,'school_year');check('Signed adult annual review completes',!empty($annual['2026-2027']['reviewed_at']));check('Authorized signing dates automatically',$state['pds']['student_date_signed']===date('Y-m-d'));
check('Full snapshot includes current private signature reference',$history->version($uid,(int)$history->latest($uid)['id'])['state']['pds']['student_signature']===$state['pds']['student_signature']);
$diff=json_decode($history->latest($uid)['changes_json'],true);check('Diff does not expose signature path or payload',str_starts_with($diff['pds.student_signature']['after'],'Recorded image (')&&!str_contains($diff['pds.student_signature']['after'],'uploads/'));
saveFixture(['home_address'=>'Another reviewed-year update']);$annual=array_column($history->annual($uid),null,'school_year');check('Changing signed year requires reviewing again',$annual['2026-2027']['reviewed_at']===null);
rejectFixture('Minor needs parent signature',fn()=>saveFixture(['birth_date'=>'2015-01-02','_pds_finalize'=>'1']));
rejectFixture('Future birth date cannot bypass annual checks',fn()=>saveFixture(['birth_date'=>'2050-01-02','_pds_finalize'=>'1']));
rejectFixture('Annual review requires consent',fn()=>saveFixture(['privacy_agreement'=>'0','_pds_finalize'=>'1']));
$state=saveFixture(['birth_date'=>'2015-01-02','parent_signature_drawn'=>$newSig,'parent_signature_confirm'=>'1','parent_signature_name'=>'Synthetic parent only','_pds_finalize'=>'1']);check('Minor completes review with authorized parent signature',!empty($state['pds']['parent_signature']));

$db->exec("UPDATE users SET first_name='New profile name' WHERE id=91001");$db->exec("UPDATE student_profiles SET program='New profile course' WHERE user_id=91001");saveFixture(['special_talents'=>'New fixture talent']);
$prior=$history->version($uid,(int)$v1['id']);check('Old year retains original profile identity and academics',$prior['state']['context']['account_first_name']==='Synthetic'&&$prior['state']['context']['profile_program']==='Fixture course');
check('New versions track academic context changes',isset(json_decode($history->latest($uid)['changes_json'],true)['school_record.profile_program']));
for($i=0;$i<12;$i++)saveFixture(['special_talents'=>'Revision fixture '.$i]);$page=$history->versions($uid,'2026-2027',1);$page2=$history->versions($uid,'2026-2027',2);check('History paginates 10 versions per page',count($page['rows'])===10&&count($page2['rows'])>0);check('Year filter keeps baseline separate',$history->versions($uid,'2025-2026')['total']===1);check('Empty year scope handled',$history->versions($uid,'2030-2031')['total']===0);
foreach([$uid,91003] as $actor)check('Authorized history access '.$actor,PDSHistory::authorize($db,$actor,$uid)['id']==$actor);
foreach([91002,91004,91005] as $actor){try{PDSHistory::authorize($db,$actor,$uid);check('History denied for other role '.$actor,false);}catch(RuntimeException $e){check('History denied for other role '.$actor,true);}}
try{$history->version(91002,(int)$v1['id']);check('Version lookup scoped by owner',false);}catch(RuntimeException $e){check('Version lookup scoped by owner',true);}
$db->prepare('UPDATE pds_versions SET content_hash=? WHERE id=?')->execute([str_repeat('0',64),$v1['id']]);try{$history->version($uid,(int)$v1['id']);check('Tampered snapshot rejected',false);}catch(RuntimeException $e){check('Tampered snapshot rejected',true);}$db->prepare('UPDATE pds_versions SET content_hash=? WHERE id=?')->execute([$v1['content_hash'],$v1['id']]);

$data=array_merge($prior['state']['pds'],$prior['state']['context']);$related=[];foreach(PDSEditorSchema::RELATED as $group=>$def)$related[$def['table']]=$prior['state'][$group];$html=PDSDigitalView::render($data,$related,'/guidanceversion2/','','paper',true);
check('Archived digital view uses original values',str_contains($html,'Old fixture address')&&str_contains($html,'Employed')&&!str_contains($html,'New profile name'));
check('Archived view has no edit actions',!str_contains($html,'Update information')&&!str_contains($html,'Edit this section'));
check('Stored answers escaped in archived HTML',str_contains($html,'&lt;script&gt;fixture&lt;/script&gt;')&&!str_contains($html,'<script>fixture</script>'));
$paper=PDSPaperDocument::render($data,$prior['state']['siblings'],$prior['state']['organizations'],$prior['state']['tests'],'/guidanceversion2/');check('Archived printable PDS uses old address and rows',str_contains($paper,'Old fixture address')&&str_contains($paper,'Original fixture siblings')&&!str_contains($paper,'Updated fixture address'));

define('IN_LAYOUT',true);$user_info=['id'=>$uid,'role'=>'student'];$role='student';$_SESSION=['pds_history_csrf'=>'fixture-token'];$_SERVER['REQUEST_METHOD']='GET';$_SERVER['SCRIPT_NAME']='/guidanceversion2/dashboard/layout.php';$_GET=['version'=>$v1['id']];
ob_start();include QA_ROOT.'/pds/history.php';$historyHtml=ob_get_clean();check('History page offers renewal and original preview',str_contains($historyHtml,'confirm_renewal')&&str_contains($historyHtml,'Old fixture address'));file_put_contents(QA_CASE_DIR.'/history-fixture.html',$historyHtml);
$_GET=['version'=>$v1['id'],'format'=>'paper'];ob_start();include QA_ROOT.'/pds/history.php';$historyPaper=ob_get_clean();check('Historical paper route includes same-tab print control',str_contains($historyPaper,'id="phPrint"')&&str_contains($historyPaper,'data-pds-document'));file_put_contents(QA_CASE_DIR.'/history-paper-fixture.html',$historyPaper);
$_GET=['user_id'=>91002];http_response_code(200);ob_start();include QA_ROOT.'/pds/history.php';ob_end_clean();check('History controller denies other-owner access',http_response_code()===403);
$_GET=['pds_id'=>$oldState['pds']['id']];http_response_code(200);ob_start();include QA_ROOT.'/pds/history.php';ob_end_clean();check('Student cannot resolve staff pds_id entry',http_response_code()===403);
$_GET=[];$_SERVER['REQUEST_METHOD']='POST';$_POST=['history_action'=>'renew','confirm_renewal'=>'1','school_year'=>'2027-2028','revision'=>PDSEditorStore::revision($store->load($uid)),'csrf'=>'incorrect'];$beforeCount=currentCount();ob_start();include QA_ROOT.'/pds/history.php';$invalidPost=ob_get_clean();check('Renewal controller requires CSRF token',currentCount()===$beforeCount&&str_contains($invalidPost,'Reload the page before'));
$_SERVER['REQUEST_METHOD']='GET';$_POST=[];$_GET=['pds_id'=>$oldState['pds']['id']];$role='admin';$user_info=['id'=>91003,'role'=>'admin'];ob_start();include QA_ROOT.'/pds/history.php';$staffHtml=ob_get_clean();check('Admin entry resolves owner and hides renewal',str_contains($staffHtml,'Read-only')===false&&str_contains($staffHtml,'2025-2026')&&!str_contains($staffHtml,'confirm_renewal'));

// Legacy unlabelled records remain visible as an honest baseline.
$uid=91006;$store->save($uid,'highered',['home_address'=>'Legacy fixture'],[],PDSEditorStore::revision($store->load($uid)));$history->renew($uid,'2026-2027',PDSEditorStore::revision($store->load($uid)));$annual=array_column($history->annual($uid),null,'school_year');check('Unlabelled legacy baseline survives first yearly renewal',isset($annual[''])&&!empty($annual['']['closed_at'])&&$history->version($uid,(int)$annual['']['version_id'])['state']['pds']['home_address']==='Legacy fixture');
file_put_contents(QA_RESULTS.'/PDS-History-Checks.json',json_encode($checks,JSON_PRETTY_PRINT));echo count($checks)." isolated PDS history checks passed. Real records were not modified.\n";
