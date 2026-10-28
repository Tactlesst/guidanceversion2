<?php
// Configure the public website URL once for web requests and background email jobs.
// Environment override wins; app.local.php supports hosting without environment settings.
$localPath = __DIR__ . '/app.local.php';
$local = is_file($localPath) ? require $localPath : [];
$baseUrl = rtrim(trim((string)(getenv('GUIDANCE_BASE_URL') ?: ($local['base_url'] ?? 'http://localhost/guidanceversion2'))), '/');
require_once __DIR__ . '/../classes/WebsiteConfiguration.php';
$baseUrl = WebsiteConfiguration::normalize($baseUrl);
return [
    'database'=>['host'=>getenv('GUIDANCE_DB_HOST')?:'localhost','port'=>(int)(getenv('GUIDANCE_DB_PORT')?:3306),'name'=>'guidanceversion2_test','username'=>getenv('GUIDANCE_DB_USER')?:'root','password'=>getenv('GUIDANCE_DB_PASSWORD')?:''],
    'base_url'=>$baseUrl,
    'timezone'=>'Asia/Manila','school_name'=>"St. Rita's College of Balingasag",
];
