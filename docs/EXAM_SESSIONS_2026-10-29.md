# Exam sessions and automatic rescheduling — 29 October 2026

Installed release **1.2.2** in `C:\xampp\htdocs\guidanceversion2`, using the existing `guidanceversion2_test` database.

The examinee calendar now displays published exam titles and their scheduled hours independently of booking availability. Examinees join the session set by Guidance; the old generated hourly appointments and fixed two-hour duration have been removed for new bookings. The first future scheduled exam in the displayed month is selected initially.

Exams have **no attendee limit by default**. Guidance can keep a configured daily limit, change it, or remove it through Schedules → Daily bookings & limits. Full dates stay visible. Counseling and exams use separate capacity and can run together. Advance booking, Sundays, holidays, school closures, and other office-event restrictions remain enforced.

After a qualifying unfinished exam reaches its stored scheduled end, the appointment checker chooses the next published, open future session with available capacity. It preserves the original attempt and creates one linked replacement. If no session is available, the application waits and is retried by the existing background checker. The examinee receives an account notification and queued email update; replacement schedules also notify staff through the existing notification service.

Automation applies to pending/confirmed attempts without recorded attendance or scores/results. Recorded attendance, awaiting-results cases, completed exams and Passed/Failed results remain protected. An explicit Failed result does not trigger this rescheduling. Historical deadlines before activation are not automatically rebooked.

Three fields were added to the existing `entrance_exam_appointments` table: `rescheduled_from_id`, `rescheduled_to_id`, and `reschedule_pending`. A unique replacement link prevents duplicate automatic attempts. No new data table was created. The guarded migration is `database/migrations/20261029_exam_sessions.php`; its activation timestamp is stored in the existing settings row's `updated_at` field. Re-running activation preserves the original timestamp.

The original and replacement save together with their notifications and audit record. A notification failure rolls back the change. The shared exam-booking lock prevents concurrent reservations and automatic replacements exceeding a configured limit. Account ownership and examinee eligibility remain checked on the server.

Live read-only verification confirmed **Exam College — 30 October 2026, 8:00 AM–5:00 PM** is visible and bookable with unlimited default capacity. Automatic rescheduling is enabled. The background appointment and email-queue tasks are ready, with last result 0. A live dry run returned no due changes and no errors.

| Verification | Passed checks |
| --- | ---: |
| Actual-session timing, visibility, optional capacity and counseling overlap | 30 |
| Automatic replacement, history, waiting/retry, protected results, notices and rollback | 26 |
| Existing booking, heartbeat, cancellation and conversion regression checks | 40 |
| Existing notifications and queued email checks | 48 |
| Existing private media, publication and schedule checks | 41 |
| Real simultaneous booking tests in a disposable database | 6 |
| Calendar rendering and role checks | 7 |
| Calendar interactions, native form validation and external session-radio submission | 13 |
| **Total targeted checks** | **211** |

Browser checks used clearly labeled synthetic schedules. Desktop and 390-pixel mobile layouts showed no horizontal overflow; actual session selection was verified without submitting a live reservation. Screenshots are saved beside this report as `exam-calendar-desktop-test.jpg` and `exam-calendar-mobile-test.jpg`.

Tests used connection-local temporary tables or a disposable test database. They created no live examinee booking and sent no real test email. Existing appointments, scores, PDS records, and the old `guidance` project were preserved. The existing private QA runner now includes `exam_sessions.php` and `exam_automation.php` for repeatable checks.
