param([string]$Php='C:\xampp\php\php.exe',[switch]$Concurrency)
$ErrorActionPreference='Stop'
if(-not(Test-Path -LiteralPath $Php -PathType Leaf)){throw 'Choose the installed PHP executable with -Php.'}
$previous=$env:GUIDANCE_RUN_QA;$env:GUIDANCE_RUN_QA='1'
$cases=@('pds_history','survey_annual','student_history','report_income','account_codes','booking_flow','exam_sessions','exam_automation','examinee_mail','notifications','approvals','private_media_schedules','accounts_import','report_exports','authentication','academics','staff_activity','office_tools','applicant_grade')
$results=@()
try{
 foreach($name in $cases){$text=(& $Php -d display_errors=1 (Join-Path $PSScriptRoot ($name+'.php')) 2>&1|Out-String);if($LASTEXITCODE-ne 0-or $text-match 'Warning:|Fatal error:|Parse error:'){throw ('QA failed in '+$name+': '+$text)};$results+=[pscustomobject]@{suite=$name;passed=$true;output=$text.Trim()};Write-Output $text.Trim()}
 foreach($mode in @('passive','active','expired')){$text=(& $Php (Join-Path $PSScriptRoot 'session.php') $mode 2>&1|Out-String);if($LASTEXITCODE-ne 0){throw $text};$results+=[pscustomobject]@{suite='session-'+$mode;passed=$true;output=$text.Trim()};Write-Output $text.Trim()}
 if($Concurrency){$text=(& $Php (Join-Path $PSScriptRoot 'concurrency.php') 2>&1|Out-String);if($LASTEXITCODE-ne 0){throw $text};$results+=[pscustomobject]@{suite='concurrency';passed=$true;output=$text.Trim()};Write-Output $text.Trim()}
 & $Php (Join-Path $PSScriptRoot 'database_audit.php');if($LASTEXITCODE-ne 0){throw 'Database integrity audit failed.'}
 $results|ConvertTo-Json -Depth 4|Set-Content -LiteralPath (Join-Path $PSScriptRoot 'results/run-summary.json') -Encoding utf8
}finally{$env:GUIDANCE_RUN_QA=$previous}
