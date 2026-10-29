<?php
require_once __DIR__.'/bootstrap.php';

$root=QA_CASE_DIR.'/sessions';if(!is_dir($root))mkdir($root,0700,true);ini_set('session.save_path',$root);ini_set('session.use_cookies','0');session_name('GUIDANCEV2SESSID');session_id('qa'.bin2hex(random_bytes(10)));session_start();$case=$argv[1]??'';$before=time()-($case==='expired'?1801:600);$_SESSION=['LAST_ACTIVITY'=>$before,'qa_marker'=>'present'];$id=session_id();session_write_close();$_COOKIE['GUIDANCEV2SESSID']=$id;$_SERVER['REQUEST_METHOD']='GET';$_SERVER['SCRIPT_NAME']=$case==='active'?'/guidanceversion2/dashboard/layout.php':'/guidanceversion2/dashboard/approval_status.php';
require QA_ROOT.'/includes/core/session.php';
$valid=$case==='expired'?!isset($_SESSION['qa_marker']):($case==='active'?($_SESSION['LAST_ACTIVITY']>$before):($_SESSION['LAST_ACTIVITY']===$before));session_destroy();if(!$valid)throw new RuntimeException('Session test failed: '.$case);echo $case." session check passed.\n";
