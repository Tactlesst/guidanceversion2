# Project structure

Updated October 29, 2026. This reorganization applies to `guidanceversion2`; the earlier `guidance` application was not reorganized.

| Folder | Purpose |
| --- | --- |
| `auth/` | Sign-in, examinee registration, recovery and sign-out |
| `dashboard/` | Workspace shell and protected approval/export/download endpoints |
| `dashboard/pages/admin/` | Staff and superadmin pages |
| `dashboard/pages/examinee/` | Applicant overview, exam calendar, application and results |
| `dashboard/pages/reports/` | Report page entry points |
| `dashboard/partials/` | Header, sidebar, overviews and page assets |
| `classes/identity/` | Accounts, verification, login limits, imports and enrollment conversion |
| `classes/records/` | Personal data sheet model |
| `classes/counseling/` | Counseling appointments and remarks |
| `classes/examinations/` | Exam bookings, results and examinee data |
| `classes/scheduling/` | Calendars, holidays, closures, limits and deadline checks |
| `classes/notifications/` | Inbox, notifications, templates and queued mail |
| `classes/reporting/` | Report calculations, renderers and one-use approvals |
| `classes/announcements/` | Publication, audiences, pinning and expiry |
| `classes/system/` | Settings, website address and audit activity |
| `includes/` | Shared renderers/helpers, grouped by the same business areas |
| `assets/css/`, `assets/js/`, `assets/images/` | Public styles, interactions and artwork |
| `assets/vendor/` | Existing browser libraries |
| `vendor/phpmailer/`, `vendor/reporting/` | Protected server dependencies |
| `storage/uploads/` | Private PDS images/signatures and temporary CSV imports |
| `storage/backups/` | Private SQL backups |
| `database/migrations/`, `database/schema/` | Migrations and reference schema |
| `config/` | Central configuration and private local settings |
| `cron/` | CLI workers and Windows installation scripts |
| `tools/diagnostics/` | CLI-only diagnostic helpers |
| `tools/dependencies/reporting/` | Composer manifest/lockfile |
| `tools/qa/` | Guarded CLI tests, with generated evidence in `results/` |
| `tools/legacy/` | Retired source copies ending in `.php.txt` |
| `docs/` | Maintainer guides, QA report and previews |

## Stable routes and private storage

Public routes remain stable: the homepage, `auth/`, `dashboard/layout.php?page=...`, `pds/`, `surveys/`, `counseling/`, `entrance_exam/` and `profile/` keep their entry points. Old `admin/` and `ajax/` routes contain protected endpoints or retirement adapters. Unused older write controllers redirect to the current workspace or reject writes.

Flat model/include files were grouped by business purpose. Their imports were updated, and old copies removed only after checking replacements. Dependencies were moved without editing their source bytes.

Direct web access is denied for configuration, models, includes, database files, workers, diagnostics, documentation, dependencies, storage, page includes and partials. PDS media and backup downloads use authenticated routes.

Existing database values such as `uploads/pds_photos/...` remain logical references to protected `storage/uploads/` files. Historical snapshot JSON and hashes were preserved. Do not rewrite past snapshots just to rename a physical directory.

[FILE_MOVES.json](FILE_MOVES.json) maps 431 original paths to their current locations. A complete pre-move snapshot is kept privately in this chat's workspace, outside the web application. It contains private settings and records and must not be published.
