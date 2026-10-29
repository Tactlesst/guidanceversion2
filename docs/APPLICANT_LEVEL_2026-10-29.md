# Examinee applicant level — 2026-10-29

Examinees can choose or correct the grade/year they are applying for in **My Profile → Applicant information** before booking an exam. If their account has a blank applicant level, the exam booking form offers the same choices and saves their selection together with the reservation.

## Cause

Two active examinee accounts had a blank `users.grade_level_applying`. The booking service required this value, while Profile offered no editor and booking offered no input. Current registration already saves the applicant level; this change supports existing incomplete accounts without guessing their intended level.

## Changes

- Added an examinee-only applicant information panel to Profile, with a required dropdown and clear distinction from official academic placement.
- Added a required dropdown during booking only when the saved applicant level is blank. Existing saved levels are displayed with a Profile correction link.
- Choices use active academic grade levels and active departments. Registered New Student entry-level rules remain in force; Transferee choices retain the existing broader choices.
- Profile changes use the authenticated account ID and existing CSRF protection. Server validation rejects inactive accounts, student roles, invalid choices and non-string values.
- Active, rescheduled, awaiting-results, completed or waiting-for-reschedule exam applications lock self-service applicant-level changes. Guidance handles corrections to those records.
- Changes and reservations use the existing booking lock and transactions. If booking or its notification fails, the new applicant-level selection is rolled back as well.
- Student IDs, official departments/programs/strands and academic assignments are untouched.
- Updated Profile stylesheet caching and appended public patch notes version 1.2.5.
- Added private CLI QA `tools/qa/applicant_grade.php` and included it in the existing QA runner.

## Validation

221 automated checks passed using temporary database records:

| Suite | Checks |
| --- | ---: |
| Applicant-level validation, permissions, booking history and rollback | 27 |
| Exact exam sessions and capacity | 30 |
| Automatic rescheduling | 26 |
| Booking and conversion regression | 40 |
| Notifications and email queue | 48 |
| Account verification and recovery | 32 |
| Rendered Profile and booking forms | 11 |
| Required choice validation and submitted form fields | 7 |

Desktop and 390px mobile previews were inspected. The applicant-level dropdown remains usable without horizontal overflow. PHP syntax checks and installed-file hash comparisons passed. Public release merging was checked for manual-version conflicts.

No real applicant grade, examination application, official academic record or email recipient was changed for testing. No external email was sent. No database schema change was needed.

## Files

- `classes/examinations/ExamBooking.php`
- `dashboard/profile.php`
- `dashboard/pages/examinee/book_exam.php`
- `assets/css/profile.css`
- `dashboard/partials/page_assets.php`
- `includes/public/builtin_releases.php`
- `tools/qa/applicant_grade.php`
- `tools/qa/run_checks.ps1`

Backups and staging: `work/applicant-grade-fix` in this Codex workspace. Verification records and screenshots: workspace `outputs/Applicant-Grade-*.json` and `outputs/applicant-level-profile*.jpg`.
