$ErrorActionPreference = 'Stop'
$projectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$xamppDirectory = Split-Path (Split-Path $projectRoot -Parent) -Parent
$phpExecutable = Join-Path $xamppDirectory 'php\php-win.exe'
$workerFile = Join-Path $projectRoot 'cron\appointment_status_heartbeat.php'
if (-not (Test-Path -LiteralPath $phpExecutable)) { throw 'The XAMPP background PHP runtime could not be found.' }
$taskName = 'SRCB Guidance v2 Appointment Checks'
$existing = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
if ($existing -and $existing.Actions[0].Arguments -notlike ('*' + $workerFile + '*')) { throw 'A different job already uses this task name.' }
$action = New-ScheduledTaskAction -Execute $phpExecutable -Argument ('-f "' + $workerFile + '"') -WorkingDirectory $projectRoot
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1)
$settings = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 3) -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
$principal = New-ScheduledTaskPrincipal -UserId ([System.Security.Principal.WindowsIdentity]::GetCurrent().Name) -LogonType Interactive -RunLevel Limited
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Settings $settings -Principal $principal -Description 'Check missed counseling and entrance-exam appointments using Philippine time.' -Force | Out-Null
Get-ScheduledTask -TaskName $taskName | Select-Object TaskName,State
