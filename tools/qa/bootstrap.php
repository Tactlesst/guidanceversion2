<?php
if(PHP_SAPI!=='cli'||getenv('GUIDANCE_RUN_QA')!=='1'){http_response_code(403);exit('Run this private test toolkit with run_checks.ps1.');}
define('QA_ROOT',str_replace('\\','/',dirname(__DIR__,2)));
define('QA_RESULTS',__DIR__.'/results');
// Child concurrency processes share their parent's private fixture directory.
$case=basename($_SERVER['SCRIPT_FILENAME']??'test','.php');define('QA_CASE_DIR',QA_RESULTS.'/'.$case);
if(!is_dir(QA_CASE_DIR))mkdir(QA_CASE_DIR,0700,true);
$_SERVER['SCRIPT_NAME']='/guidanceversion2/dashboard/layout.php';
