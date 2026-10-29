# SRCB Guidance Management System

Open http://localhost/guidanceversion2/ with Apache and MySQL running in XAMPP. The root opens the public homepage; sign-in opens the workspace allowed for the account's role.

- [Folder structure](docs/PROJECT_STRUCTURE.md)
- [Operation and hosting](docs/OPERATIONS.md)
- [QA report](docs/QA_REPORT_2026-10-29.md)
- [Repeatable QA toolkit](tools/qa/README.md)

The existing `guidanceversion2_test` database is configured centrally through `config/app.php` and `config/database.php`. Keep private configuration, uploads and backups out of public downloads and shared repositories. Public version notes are displayed at `patch_notes.php`. Superadmins append releases in the Patch notes portal tab; manual releases are stored centrally in `release_notes`. Bundled release history is maintained in `includes/public/builtin_releases.php`; `includes/public/releases.php` merges both sources.
