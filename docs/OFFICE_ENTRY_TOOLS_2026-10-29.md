# Patch notes, paper PDS entry and mail guide — release 1.2.4

Implemented in `C:\xampp\htdocs\guidanceversion2`, using `guidanceversion2_test`.

**Superadmin → Patch notes**

The superadmin can append a release by entering a newer version, release date, title, and labeled changes, then choosing **Publish version**. New releases appear on the public Patch notes page. Earlier versions stay available; duplicate or older version numbers are rejected. The editor suggests the next patch number, supports adding/removing change rows, and checks permissions and form tokens on the server.

Manual releases are stored in the new `release_notes` table. This is storage for the new publishing feature; existing records were not duplicated. Bundled history remains in `includes/public/builtin_releases.php`, with `includes/public/releases.php` providing the merged public history. Publication is audited. Source-only deployment to another database should run the guarded migration `database/migrations/20261029_release_notes.php` once.

**Admin / Guidance Advocate → Students & records → Enter paper PDS**

Search an existing active student by name or ID. The picker excludes students with a digital PDS or PDS history. A link is also available on the Personal data sheets page. The student's department and grade/year must be assigned first through Student records.

The existing six-section PDS wizard is reused, including academic, personal, address, family, siblings, education, organizations, health and test fields. Staff can upload the student photo and photographs/scans of both student and guardian signatures. Supported images remain JPG, PNG or WebP, up to 2 MB, with server-side file-content checks and protected storage.

Staff confirm that they are copying the authorized paper form. Staff cannot draw a replacement signature or impersonate the student's online annual-review action. Signing dates are entered from the paper instead of being changed to today's date. Missing dates remain blank. Paper consent can be recorded with its actual date; a date-only consent value is stored with a midnight time component. Future signing/consent dates are rejected.

Staff may choose **Mark the paper PDS complete for this school year** when the paper records consent and all required signature photos, names and dates are present. Existing annual validation also checks age and guardian signatures for students below 18. Otherwise it is saved for student review. School year and student name are required for staff entry.

Creation is checked again when saving, under a student-row lock. Existing PDS records cannot be overwritten by this create-only route. Versions and staff activity identify the actual admin/advocate who entered the record. The student receives a short account notification and queued email telling them Guidance entered their paper PDS. The existing digital/paper views, annual snapshots, private media access and advocate print-approval rules are preserved.

**Admin → System settings → Email & notifications → Mail server settings → Setup guide**

The guide button appears only inside Mail server settings. Its modal explains every existing field, sender-account changes, preserving/replacing the credential, matched port/encryption choices, delivery status checks and deployment jobs. It includes links to Google's official [app-password instructions](https://support.google.com/accounts/answer/185833?hl=en) and [SMTP configuration](https://support.google.com/mail/answer/7104828?hl=en). Opening the guide does not change the saved mail configuration. Keyboard focus enters the modal and returns to the guide button on closing; Escape also closes it.

| Verification | Passed checks |
| --- | ---: |
| New release publishing, staff permissions, PDS history, consent and activity | 33 |
| Real multipart photo/signature uploads with temporary records | 15 |
| Rendering, full-field wizard, identity preservation and modal content | 15 |
| Release-row and PDS-wizard interactions | 10 |
| Existing annual PDS / private media regression | 75 |
| Existing history, advocate access and print approval | 27 |
| Existing notifications and email queue | 48 |
| Existing report approvals | 40 |
| Existing activity filters, privacy and pagination | 79 |
| **Total targeted checks** | **342** |

Browser verification covered the real superadmin release editor, the admin paper-entry picker and the real mail-guide modal. The guide and release editor were also checked at a 390-pixel mobile width without horizontal overflow. A separate clearly labeled synthetic-student preview verified the paper wizard's signature-upload section, editable paper dates, annual-paper completion control and mobile layout. No real student's PDS was inserted or changed, no fictional release was published to the live database, and no real test email was sent. SMTP credentials and configuration were not changed.

Repeatable backend checks are included in the private QA runner as `tools/qa/office_tools.php`. Screenshots are saved beside this report as `superadmin-patch-notes.jpg`, `mail-setup-guide.jpg`, and `paper-pds-signature-entry-test.jpg` (synthetic student).
