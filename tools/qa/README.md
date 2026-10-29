# Private QA toolkit

Run locally in PowerShell:

```powershell
& 'C:/xampp/htdocs/guidanceversion2/tools/qa/run_checks.ps1'
& 'C:/xampp/htdocs/guidanceversion2/tools/qa/run_checks.ps1' -Concurrency
```

Use `-Php` to select another PHP executable. The driver opts into guarded CLI tests with `GUIDANCE_RUN_QA=1`; web execution is denied. The database must be running. Covered cases require PDO MySQL, OpenSSL, mbstring, fileinfo and Zip.

Mutable cases use synthetic records in connection-local temporary tables, including users, notifications and email outboxes. Mail delivery is fake. The optional concurrency case uses a uniquely named disposable database and requires development CREATE/DROP DATABASE privileges. Do not grant those privileges to the website's public hosting account.

Run one toolkit instance at a time. Shared fixture helpers are under `support/`; generated evidence and synthetic media are under protected `results/`. Results are test fixtures, not production reports. The runner stops on failure or a PHP warning. Review the read-only database audit too; it never deletes or repairs real records automatically.

The toolkit covers PDS/yearly history, surveys, verification, notifications/queue delivery, advocate approvals, account import/edit/reset, academic controllers, schedules/closures, report generation, idle sessions, audit filters and optional simultaneous bookings. It does not send real SMTP mail, restore the live database, or operate a browser/physical printer. Separate live HTTP, DOM and PDF visual checks are documented in the QA report.

Do not substitute historical diagnostic or authorized-email-test scripts for this reviewed runner; some were designed for real setup operations.

Exam-session checks (`exam_sessions.php` and `exam_automation.php`) cover published-session visibility, exact scheduled hours, unlimited default capacity, optional limits, independent counseling, retained original attempts, automatic replacement links, waiting/retry, result and attendance protection, notification deduplication and rollback. They use temporary tables and do not send real mail.
