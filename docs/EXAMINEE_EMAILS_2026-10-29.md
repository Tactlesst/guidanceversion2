# Examinee email updates — release 1.2.3

Implemented in `C:\xampp\htdocs\guidanceversion2` on 29 October 2026.

- New examinee accounts receive a welcome email linking to exam booking. Account creation and its notification save together; failures roll back registration.
- Exam application confirmation emails include the actual scheduled start and end in Philippine time.
- Automatic rescheduling emails explain the unfinished attempt and show the previous and new schedules. Staff-initiated changes use neutral wording rather than inventing an unfinished-exam reason.
- Results emails read the saved, released decision. Passed examinees are congratulated and asked to visit St. Rita's College of Balingasag to verify enrollment, confirm required documents and receive the school's next instructions. Enrollment is finalized through school verification.
- Failed-result emails direct the examinee to review the result and contact Guidance. Missing or unreleased decisions cannot produce a Passed email.
- Result buttons open the recipient's authorized results page after sign-in. Links contain an allowlisted topic rather than a student ID, score or other confidential record data.
- Repeated identical result saves do not duplicate emails. Actual saved result corrections generate a new notice.
- Existing branding, website-address settings and background email delivery are reused. Account notifications appear immediately, with emails queued for the worker.
- Updated System Settings help to describe current exam rescheduling behavior, and added homepage patch notes for version 1.2.3.

Validation: 35 new email checks, 48 existing notification/worker checks, 26 automatic rescheduling checks, 40 booking/history/conversion checks, and 30 scheduled-session checks passed — **179 checks**. Tests used temporary records and a fake email transport; no real test email, fictional result, or live application was created. No old messages were mass-resent.

The configured mail service reports ready, and the existing email worker checked at 10:46:38 AM Philippine time. Future account, application, rescheduling and result-release actions now use these messages. Delivery is handled by the existing SMTP queue and its retry mechanism.

The private QA runner now includes `tools/qa/examinee_mail.php`. Preview HTML for synthetic Passed and Failed messages is generated under that test's protected results folder.
