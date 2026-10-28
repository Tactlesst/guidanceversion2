$ErrorActionPreference = 'Stop'
$projectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$phpDirectory = Split-Path (Split-Path $projectRoot -Parent) -Parent
$phpExecutable = Join-Path $phpDirectory 'php\php-win.exe'
if (-not (Test-Path -LiteralPath $phpExecutable)) { throw 'The XAMPP background PHP runtime could not be found.' }
$workerFile = Join-Path $projectRoot 'cron\send_email_outbox.php'
$taskName = 'SRCB Guidance v2 Email Queue'
$action = New-ScheduledTaskAction -Execute $phpExecutable -Argument ('-f "' + $workerFile + '" -- --limit=10') -WorkingDirectory $projectRoot
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1)
$settings = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 8) -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
$principal = New-ScheduledTaskPrincipal -UserId ([System.Security.Principal.WindowsIdentity]::GetCurrent().Name) -LogonType Interactive -RunLevel Limited
$existing = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
if ($existing -and $existing.Actions[0].Arguments -notlike ('*' + $workerFile + '*')) { throw 'A different job already uses the requested task name.' }
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Settings $settings -Principal $principal -Description 'Send queued guidanceversion2 email updates without delaying website submissions.' -Force | Out-Null
Get-ScheduledTask -TaskName $taskName | Select-Object TaskName, State
