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

- [x] Money as integer minor units in a `Money` value object (parsing "1.200,50" and "1,200.50", per-locale formatting) (ADR 0008)
- [x] Fee structures per academic year and class: every term, once a year, or one-off for new students
- [x] `fees:generate-term-invoices` (daily, terms starting within 14 days): idempotent, locked per school and term, gapless invoice numbers per school and year; "Generate now" for admins
- [x] Invoice list (search, class and status filters incl. overdue), invoice page, invoice PDF on signed links, void without payments
- [x] Cash and bank payments (partial payments, row-locked against overpaying), receipt PDF in a queued job
- [x] Stripe Checkout (test mode) for parents; webhooks with signature check, event de-duplication in the same transaction, payments, partial payments and refunds; off without keys
- [x] Notifications to the primary guardian: invoice issued, payment received, overdue reminders after 3 and 10 days (each at most once)
- [x] Fees dashboard per term: invoiced, collected, outstanding and overdue per class, with a chart
- [x] Parents see and pay their own children's invoices (the portal in milestone 9 builds on it)
- [x] Demo seed: Riverside and Gymnasium fees, autumn invoices in mixed states; E2E with a signed, simulated Stripe webhook

## 9. Parent portal & API

- [x] Parent portal: family overview as the parents' dashboard, per child an attendance calendar, results so far and published report cards (signed PDFs), sibling switcher; invoices and online payment from milestone 8
- [x] Notifications page with unread badge; notifications store their school (`SchoolDatabaseChannel`), so a parent at two schools sees each school's only there
- [x] Parent API `/api/v1` with Sanctum: one token per device and school, `SetCurrentSchool` reads the school from the token and checks the membership on every request (ADR 0009)
- [x] Endpoints: token issue/revoke, `/me`, children, attendance by month, grades by term, report cards and PDFs, invoices and Stripe Checkout sessions, notifications and mark as read
- [x] API Resources shared with the portal, cursor pagination, one error JSON shape (`message`, `code`, `errors`), 60 requests/min per token, 5 sign-in attempts/min
- [x] Scribe docs at `/docs` (static HTML, OpenAPI, Postman), generated from routes, Form Requests and `@response` examples
- [x] Isolation: API cross-school 404 suite; fix: the `school` guard now runs before route binding, so a user without a school gets the school picker instead of an error
- [x] Demo seed: notifications for the demo parent; E2E for the parent portal

## 10. GDPR, i18n, audit

- [x] English, German and Hindi everywhere: UI, validation, mails, notifications, exports and PDFs (ADR 0010); language per user, else school, else browser; profile language picker
- [x] Queued work and scheduled commands run in the school's language; dates and numbers per language (British English); notification titles built in the reader's language
- [x] Hindi PDFs with a bundled Noto Sans Devanagari font (dompdf's shaping limits documented); Hindi is machine quality, noted in the README
- [x] `npm run i18n:check` in CI (missing and unused keys) and a Pest test for placeholders
- [x] Activity log (spatie/laravel-activitylog) for students, marks, invoices, payments, report-card approvals and GDPR actions, per school; audit log page with filters and a "History" link per student
- [x] GDPR: student data export (JSON + PDFs in a ZIP, queued, emailed signed link, expires after 24 h), anonymisation (personal fields, files, audit log values), attendance-note retention per school, privacy notice template in en/de/hi (`docs/GDPR.md`)

## 11. Performance & hardening

- [x] `PerformanceSeeder` (20 schools, 10,080 students, 4.3M attendance records, 1.5M marks, 40,000 invoices) and `scripts/benchmark.sh` on a production-like copy; all targets met, before/after in the README (ADR 0011)
- [x] Monthly attendance report and class results 3× faster (plain rows instead of models); grade scales cached per school (tagged, dropped after commit)
- [x] Security review (`docs/SECURITY.md`): nonce-based CSP and security headers, self-hosted fonts, private disk not served by the storage route, spreadsheet formula injection fixed, 12-character passwords (breach check in production), no account enumeration, auth rate limits, `Secure` cookies in production, dependency audit in CI
- [x] Accessibility pass (`docs/ACCESSIBILITY.md`): axe (WCAG 2.1 AA) on every main page per role in light and dark mode in CI, contrast and link-name fixes, skip link, a SchoolDesk landing page and logo
- [x] Playwright suite: the full journey (registration → setup wizard → invitations → import → invoices → attendance and marks → report card → parent reads and pays), following emails through Mailpit

## 12. Release

- [x] Production images (multi-stage Dockerfile: PHP-FPM 8.4 `app`, Nginx `web`) and `compose.prod.yaml` with Caddy (automatic HTTPS), Horizon, the scheduler, MySQL and Redis (ADR 0012)
- [x] Forwarded headers trusted only from configured proxies (`TRUSTED_PROXIES`)
- [x] `scripts/deploy.sh` (build, migrate with the new image, restart, smoke test) and `scripts/smoke-test.sh`; `docs/DEPLOYMENT.md` (VPS setup, updates, rollbacks, backups, operations)
- [x] CI job that builds, starts, seeds and smoke-tests the production stack, and runs a demo reset
- [x] Demo mode: banner, demo logins on the sign-in page, nightly `demo:reset` in maintenance mode that also ends every session
- [x] `docs/ARCHITECTURE.md`, screenshots from the demo seed (`tests/e2e/screenshots.spec.ts`), README polish
- [x] Fixes found on the production stack: Nginx kept a recreated app container's old address (502), the school admin's navigation overflowed (now a School menu)
- [ ] Deploy the public demo to a VPS and link it in the README
