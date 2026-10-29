# Operation and hosting

## Local configuration

Start Apache and MySQL in XAMPP and open http://localhost/guidanceversion2/. Existing accounts keep their credentials. The earlier `guidance` folder remains separate.

`config/app.php` selects `guidanceversion2_test`. Database connection overrides are `GUIDANCE_DB_HOST`, `GUIDANCE_DB_PORT`, `GUIDANCE_DB_USER` and `GUIDANCE_DB_PASSWORD`.

Set the public website address in the counselor/admin's System Settings before moving online. It is stored in private `config/app.local.php`, or managed by `GUIDANCE_BASE_URL` when supplied by hosting. Include the subfolder if applicable. Email links use this configured address, not an arbitrary incoming Host header.

Email settings are also in System Settings and saved in private `config/email.local.php`. Preserve `config/security.local.php` securely when moving existing encrypted queued verification messages. Do not publish SMTP passwords or encryption keys.

## Background jobs

The existing Windows tasks are `SRCB Guidance v2 Appointment Checks` and `SRCB Guidance v2 Email Queue`. Both were enabled and last returned success during QA, running approximately once per minute.

```powershell
& 'C:/xampp/php/php.exe' 'C:/xampp/htdocs/guidanceversion2/cron/appointment_status_heartbeat.php' --dry-run
```

That command only reports due appointments. `cron/send_email_outbox.php` actually sends pending business emails; do not run it as an extra email test. Installation scripts under `cron/` calculate their paths from the project folder.

The Windows tasks currently use the interactive account. For unattended hosting, configure a persistent scheduler/service account and equivalent worker commands. Keep the server timezone at Asia/Manila. Do not rely on opening a page to mark missed appointments.

## Before public hosting

The shared XAMPP runtime is PHP 8.0.30, outside supported releases. Upgrade a separate staging installation to a supported PHP release and rerun QA before replacing shared XAMPP. See the [official PHP support table](https://www.php.net/supported-versions.php).

The local database login is root with an empty password. Configure a dedicated password-protected application user before hosting. Only the optional development concurrency tests need CREATE/DROP DATABASE privileges; the website account should not have them.

Use HTTPS, the correct public base URL, production error logging with displayed errors disabled, and equivalent private-directory access rules on the host. Apache rules are included in `.htaccess`; Nginx requires explicit equivalents. Confirm private storage/configuration/tools/dependencies return 403 or 404.

GD is absent in the CLI runtime. Sampled report logos/charts render successfully; verify image-format/conversion support on the target host.

Private mail settings and the encryption-key file were already tracked in the local Git repository before this pass. New ignore rules do not remove tracked content or previous history. No repository publishing was performed. Review private tracked files before sharing a repository; rotate credentials if it was previously published. Do not casually regenerate the encryption key while queued encrypted payloads still depend on it.

## Backup and restore

SQL backups are in protected `storage/backups/`; download through the superadmin controls. Keep an additional private off-machine backup. Rehearse restores against a separate disposable database and validate accounts, PDS history and attachments before a real restore. A destructive live restore was not performed during QA.
