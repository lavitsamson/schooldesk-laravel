# ADR 0011: Performance measurement and hardening

- **Status:** Accepted
- **Date:** 2026-10-26
- **Deciders:** Lavit Samson

## Context

The spec sets targets on a large dataset (20 schools, 10,000 students, two years of attendance):
student list under 200 ms, attendance grid under 150 ms, a class's report cards under 60 s. It
also asks for a security review, an accessibility pass and a complete Playwright suite.

What makes this harder:

- Development runs in Sail on a Windows bind mount, where every PHP request spends about 85 ms
  just reading the framework's files. Timings taken there say more about the file system than
  about the application.
- Optimising without numbers tends to add caches and complexity where nothing was slow.
- A strict Content Security Policy has to fit what Vue, Inertia, Ziggy, Chart.js and Horizon
  actually do in the browser.
- Accessibility and CSP regressions only show in a real browser, page by page.

## Decision

1. **Measure first, on a production-like copy.** `PerformanceSeeder` builds the spec's dataset
   (10,080 students, 4.3 million attendance rows, 1.5 million marks, 40,000 invoices).
   `scripts/benchmark.sh` copies the app to the container's own file system, prepares it like
   a deployment (optimised autoloader, cached config/routes/views/events, opcache, no debug),
   serves it, and `scripts/benchmark.mjs` times each page the way the app navigates (Inertia
   JSON requests) and the report-card batch, against the targets.
2. **Optimise what the numbers show.** All three targets were met before any change (p95: 55 ms,
   49 ms, 3.6 s), thanks to the composite indexes that start with `school_id` and eager loading
   from earlier milestones. Two pages without targets were slow: the monthly attendance report
   (196 ms) and class results (384 ms). Both spent their time hydrating Eloquent models and
   casting the same dates, enums and decimals again and again. They now read plain rows
   (`toBase()`, which still applies the school scope) and prepare per-assessment data once:
   64 ms and 137 ms.
3. **Cache what is read everywhere and changes rarely, per school.** Grade scales are cached
   forever, tagged `school:{id}`, and dropped after the transaction that changes them commits
   (`DB::afterCommit`), so no request can cache the old scale in between. Tags need Redis (or the
   array store in tests); the CI end-to-end job uses Redis like local and production.
   School settings are **not** cached separately: the school row arrives with the membership
   check that `SetCurrentSchool` runs on every request (one indexed join). Caching it would mean
   trusting a cached membership, so removing a member would no longer take effect at once.
4. **A nonce-based CSP on every response** (`SecurityHeaders`). Scripts need our origin or the
   request's nonce; Vite, Ziggy (`@routes(nonce: …)`) and Horizon add it. Styles allow
   `'unsafe-inline'` (Vue style bindings, Chart.js); that permits CSS injection at most, never
   script. Horizon's dashboard compiles templates in the browser and gets `'unsafe-eval'` on
   its own path only. The Vite dev server's origin is allowed while `public/hot` exists. Fonts
   are bundled, so the policy names no third-party host.
5. **Typed files for anything that repeats user input.** Spreadsheet exports write text cells as
   text; the import error report became XLSX, because CSV has no cell types and Excel evaluates
   a cell starting with `=`, even in a "fixed" CSV re-opened later.
6. **Browser checks per page in CI.** `pages.spec.ts` walks every main page per role in both
   colour schemes with axe-core (WCAG 2.1 AA) and fails on CSP violations in the console.
   `journey.spec.ts` runs a new school from registration to a paid invoice, following the
   verification and invitation emails through Mailpit's API.

## Consequences

- The README's numbers can be reproduced with two commands, and a regression shows up as a
  missed target in the benchmark's output.
- Hot read paths in two actions use arrays instead of models. The type of each row is
  documented in a PHPStan array shape, so the trade is visible.
- A new page that needs an inline script, a third-party host or `eval` fails the page walk
  instead of breaking in production; the fix is a nonce or a deliberate CSP change.
- Every new page needs to pass axe in light and dark mode before it can be merged.
- The CI end-to-end job needs Redis and Mailpit services.

## Alternatives considered

- **Laravel Telescope or Debugbar for profiling.** Both measure inside a single request on the
  slow mount; the benchmark needs repeatable percentiles over many requests on a realistic
  setup. A small script was enough.
- **Caching the student list or report pages.** The targets were met without it, and per-school
  page caches need careful invalidation on every write (students, enrolments, marks).
- **Denormalising `term_id` onto marks** to speed up class results' marks query (16 ms of the
  page). Not worth a second source of truth for this gain.
- **A CSP package (spatie/laravel-csp).** One small middleware covers the policy and its
  exceptions, with no new dependency.
- **Prefixing risky CSV cells with an apostrophe** (OWASP's CSV-injection advice) instead of
  switching the error report to XLSX. It would change values such as phone numbers (`+49 …`)
  on the way back in, and the report is made to be fixed and uploaded again.
- **Lighthouse or pa11y** for accessibility. axe-core runs inside the existing Playwright suite,
  with the same sign-in and pages, at no extra tooling cost.
