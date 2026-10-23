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

- [ ] Schools, memberships (`school_user`)
- [ ] `BelongsToSchool` trait, `CurrentSchool`, `SetCurrentSchool` middleware
- [ ] spatie/laravel-permission in teams mode
- [ ] Policy skeletons
- [ ] Invitations (signed, expiring links)
- [ ] Setup wizard
- [ ] Tenant isolation test harness

## 3. Structure & students

- [ ] Academic years, terms, classes, subjects, teacher assignments
- [ ] Students and guardians CRUD, enrollments
- [ ] Promotion to next academic year

## 4. Bulk import

- [ ] Queued, chunked import with progress and error report
- [ ] Horizon

## 5. Attendance

- [ ] Attendance grid, absence notifications, monthly report and export

## 6. Assessments & grades

- [ ] Grade entry grid, grade scales, `CalculateReportCard` action

## 7. Report cards & AI

- [ ] Batch generation, PDF, approval flow, AI drafts with data minimisation

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
