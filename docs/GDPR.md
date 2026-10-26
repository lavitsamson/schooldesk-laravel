# GDPR

How SchoolDesk handles personal data, and what a school admin can do to answer requests under the GDPR. Each school is the **controller** of its data; the SchoolDesk operator is its **processor** (Art. 28).

## Personal data held

| Data | Where | Notes |
|---|---|---|
| Students: name, admission number, date of birth, gender (optional), address, photo (optional) | `students`, private disk | Photos only through an authorised route |
| Guardians: name, email, phone, relationship | `guardians` | A portal account (`users`) once invited |
| Attendance and notes on absences | `attendance_records` | Notes often give health reasons (Art. 9): see retention below |
| Marks and remarks | `grades` | |
| Report cards: results snapshot, teacher comment, PDF | `report_cards`, private disk | |
| AI comment drafts | `ai_comment_drafts` | Data minimisation, below |
| Invoices, payments, receipts | `invoices`, `payments`, private disk | Kept for tax law |
| Audit log | `activity_log` | Changed fields, old and new values |
| In-app notifications | `notifications` | First names in the message data |
| Imports | `import_batches`, `import_rows`, private disk | Pruned after `IMPORT_KEEP_DAYS` (30) |

All files are on the private `local` disk and only reachable through signed, authorised routes.

## Access and portability (Art. 15, 20): data export

**Student page → Data protection → Export data** (school admins with `data_protection.manage`).

1. `RequestStudentExport` creates a `student_exports` row, logs `exported` in the audit log (area *Data protection*) and queues `PrepareStudentExport`.
2. The job runs in the school's language and context. `BuildStudentExport` writes a ZIP:
   - `student.json` (`CollectStudentData`): details, guardians, enrolments, attendance with notes, marks with remarks, report cards with their results snapshot, invoices with items and payments, and the student's audit history. Keys are English, dates ISO 8601, amounts in minor units.
   - `photo.*`, `report-cards/*.pdf` (approved and published only), `invoices/*.pdf`, `receipts/*.pdf`.
3. The admin who asked gets an email (`StudentExportReady`) with a **temporary signed link**. The link and the file expire after `GDPR_EXPORT_LINK_HOURS` (24). Downloading also needs a signed-in data protection admin of the school; other schools' exports return 404.
4. `model:prune` runs hourly for `StudentExport` and deletes expired exports and their files, and exports whose job never finished within a day.

Exports are rate-limited to 20 per hour per school (each one renders every PDF of the student).

## Right to erasure (Art. 17): anonymisation

**Student page → Data protection → Erase personal data.** The admin confirms by typing the admission number. `AnonymiseStudent` runs in one transaction and cannot be undone:

| Removed or replaced | Kept (legal retention, school records) |
|---|---|
| Name → "Anonymised Student" (shown as "Anonymised student" in the reader's language) | Marks |
| Admission number → `ANON-{id}` | Attendance statuses |
| Date of birth → `1900-01-01` placeholder (the column is required); never shown | Report-card results (snapshot renamed) |
| Gender, address, photo file | Invoices, items and payments |
| Mark remarks, attendance notes, report-card comments | Enrolments (class and year) |
| AI comment drafts | |
| Stored report-card PDFs, payment receipts, data exports | |
| Guardian links; guardians with no other child at the school are deleted | |
| In-app notifications about the student or their invoices | |
| Import rows with the student's admission number | |
| Personal fields (name, admission number, date of birth, gender, address, remarks) in the student's audit log entries | |

Report cards and receipts are rendered again, with the anonymised name, if ever downloaded. The erasure itself is logged (`anonymised`, by whom and when). An anonymised student can no longer be edited, linked to guardians or exported.

Not covered:

- **Guardians' user accounts** are left alone: they may belong to other schools, and a parent can delete their own account on the profile page.
- **Import error reports** (CSV files of failed rows) are not searched; they are deleted with their batch after 30 days.
- **Backups and sent emails** are outside the application. The school's retention policy for backups applies.

## Retention

- **Attendance notes**: each school chooses 6, 12, 24 or 36 months, or to keep them (**Data protection** page). `gdpr:prune-attendance-notes` runs nightly at 03:00 and clears older notes; the attendance records stay, as report cards need them.
- **Imports**: pruned after `IMPORT_KEEP_DAYS` days.
- **Data exports**: 24 hours.
- **Parent API tokens**: deleted a day after they expire.

## Data minimisation in the AI feature

AI comment drafts are off unless a school admin turns them on. `BuildCommentDraftRequest` sends only the first name, subject grades, attendance rate and trend: no surname, date of birth, address, remarks or guardian data. Tests assert on the request payload. Teachers must review every draft; nothing AI-generated is published without approval (ADR 0007).

## Privacy notice

`/privacy` shows a privacy notice template in English, German or Hindi (the visitor's language), linked from every page's footer. The text lives in `resources/privacy/{en,de,hi}.md`; a school completes the bracketed parts and has it checked before going live. It is a template, not legal advice.

## Tests

`tests/Feature/Gdpr/`: export contents, signed and expiring links, permissions, pruning; anonymisation of every field and file listed above, what is kept, guardians, notifications, audit log; retention setting and nightly pruning; privacy notice languages. Cross-school requests for the new routes return 404 (`tests/Support/CrossSchoolRoutes.php`).
