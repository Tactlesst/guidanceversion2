<?php
/** Run once from the command line after backing up the database. Safe to rerun. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../../config/database.php';
require_once __DIR__.'/../../includes/pds_editor_store.php';
$db=(new Database)->getConnection();
foreach(explode(';',file_get_contents(__DIR__.'/20261028_pds_history.sql')) as $statement)if(trim($statement)!=='')$db->exec($statement);
$store=new PDSEditorStore($db);$history=new PDSHistory($db);$count=0;
foreach($db->query('SELECT user_id FROM pds ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) as $uid){$db->beginTransaction();try{$state=$store->load((int)$uid,true);if(!$history->latest((int)$uid)){$history->baseline((int)$uid,$state);$count++;}$db->commit();}catch(Throwable $e){$db->rollBack();throw $e;}}
echo 'PDS history ready. Imported baselines: '.$count.PHP_EOL;
