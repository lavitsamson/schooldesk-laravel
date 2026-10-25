# Progress

## 1. Foundation

- [x] Laravel 13 (PHP 8.4) + Breeze (Inertia, Vue 3, TypeScript, dark mode)
- [x] Laravel Sail (MySQL 8, Redis, Mailpit)
- [x] Pint (Laravel preset + `declare(strict_types=1)` everywhere)
- [x] Larastan level 8
- [x] Pest (feature, unit, architecture tests)
- [x] ESLint + Prettier + vue-tsc for the frontend
- [x] Playwright smoke test
- [x] GitHub Actions CI (Pint, PHPStan, ESLint, vue-tsc, Pest on MySQL and PostgreSQL, Vite build, Playwright)
- [x] README skeleton
- [x] ADR 0001: multi-tenancy

## 2. Tenancy & roles

- [x] Schools, memberships (`school_user`), school switcher
- [x] `BelongsToSchool` trait (fail-closed), `CurrentSchool`, `SetCurrentSchool` + `EnsureSchoolMembership` middleware
- [x] spatie/laravel-permission in teams mode, team id read from `CurrentSchool` (ADR 0002)
- [x] Policy skeletons (`SchoolPolicy`, `MembershipPolicy`)
- [x] Invitations (signed, expiring links; re-sending invalidates older links)
- [x] Setup wizard (school details → academic year & terms → classes → subjects → grade scale)
- [x] Tenant isolation test harness (cross-school 404 cases + a guard test for uncovered routes)
- [x] Demo seed: Riverside International School and Gymnasium am Main with demo logins

## 3. Structure & students

- [x] Academic years (terms kept by id, copy classes, make current), classes, subjects, subject-teacher assignments
- [x] Students CRUD: search, filters, sorting, server-side pagination, private photos, soft delete
- [x] Bulk actions: move to class, mark as left (export selected followed in milestone 4)
- [x] Guardians: new or shared between siblings, primary contact, parent portal invitation
- [x] Enrolments per academic year (ADR 0003)
- [x] Promotion to next academic year with preview, exclusions and graduation
- [x] Demo seed: teachers with assignments, ~500 students with families and guardians

## 4. Bulk import

- [x] Queued, chunked student import (CSV/XLSX, 200 rows per chunk) with row-level validation, progress polling and a downloadable error report (ADR 0004)
- [x] Idempotent re-upload (admission number is the key), German Excel files (semicolons, Windows-1252, DD.MM.YYYY), downloadable template
- [x] Bulk action "export selected" in the import format (export → edit → import round trip)
- [x] Uploaded files deleted after the import; batches and error reports pruned after 30 days
- [x] Horizon with a separate supervisor for the `imports` queue (dashboard for platform admins)

## 5. Attendance

- [x] Attendance grid per class and day (everyone starts as present), for own classes (class or subject teacher) or all classes (admins), in the school's timezone (ADR 0005)
- [x] Absence notifications to the primary guardian (mail, plus the database notification for portal users): queued with a correction delay, at most once per student and day
- [x] Monthly report per class (day grid, totals, rates, Chart.js chart) and XLSX export
- [x] Demo seed: up to three months of attendance up to yesterday

## 6. Assessments & grades

- [x] Assessments (test, exam, assignment) per class-subject and term, with max marks and weight, for the subject teacher (or admins)
- [x] Spreadsheet-like mark entry grid: autosave of changed rows, keyboard navigation, validation against max marks, decimal comma
- [x] Gradebook per class-subject with each student's weighted result so far
- [x] `CalculateReportCard` action: weighted percentage per subject, grade from the school's scale, overall result, attendance, as a snapshot (ADR 0006)
- [x] Class results across subjects and a per-student result for class teachers and admins
- [x] Grade scale editor (presets or adjusted bands, checked for gaps and overlaps)
- [x] Demo seed: four assessments per class-subject in the current term, marks for those already held

## 7. Report cards & AI

- [x] Report cards as stored snapshots: draft → approved (frozen, PDF rendered) → published; reopen until published (ADR 0007)
- [x] Class batch generation on the `reports` queue (Laravel job batch, one job per student) with polled progress
- [x] Comment editor with previous/next student, recalculation of drafts, publish one or all approved (guardians notified)
- [x] PDF with dompdf on the private disk, signed and authorised downloads, watermarked draft preview
- [x] AI comment drafts behind `LlmClient` (Anthropic via the HTTP client, `FakeLlmClient` without a key): off per school by default, minimised payload (tested), drafts beside the comment, token usage stored, 100/hour/school
- [x] Demo seed: Grade 7 A report cards at every stage, AI drafts on for Riverside
- [x] Fix: per-school rate limits now see the current school (middleware priority)

## 8. Fees & payments

- [ ] Fee structures, scheduled invoices, PDFs, Stripe Checkout, webhooks, receipts, reminders, dashboard

## 9. Parent portal & API

- [ ] Portal pages, Sanctum API, Scribe docs

## 10. GDPR, i18n, audit

- [ ] Export, anonymisation, German translation, activity log UI

## 11. Performance & hardening

- [ ] Performance seeder and targets, security review, `docs/SECURITY.md`, accessibility pass, Playwright suite

## 12. Release

- [ ] Production Docker setup, `docs/DEPLOYMENT.md`, deploy, screenshots, README polish
