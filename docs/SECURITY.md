# Security

The threats SchoolDesk was designed against, how each is mitigated, and where the tests are. The data is about children, so the default answer to "should this be reachable?" is no.

Reporting a vulnerability: please email the author (see the README) rather than opening a public issue.

## Threats and mitigations

### Cross-school data leakage

One database holds every school (ADR 0001), so a missing filter would show one school's students to another.

- **Fail-closed scoping.** School-owned models use `BelongsToSchool`: a global scope filters by `CurrentSchool`, and a query without a school context throws `MissingSchoolContextException` instead of returning every school's rows. Creating a record fills `school_id`; writing a record of another school throws `CrossSchoolWriteException`.
- **The school never comes from input.** `SetCurrentSchool` takes it from the membership selected in the session (web) or from the Sanctum token's `school_id` (API), and checks on every request that the membership is still active.
- **Route model binding sees the school.** `SetCurrentSchool` runs before `SubstituteBindings`, so another school's id resolves to a **404**, which does not reveal that the record exists.
- **Validation.** Laravel's `exists`/`unique` rules ignore global scopes; school-owned lookups use `App\Support\SchoolRule`.
- **Queued jobs** carry the school id and set the context in `handle()`; `CurrentSchool` is a scoped singleton, reset between jobs.
- **Raw queries** are few, use bindings only, and name `school_id` explicitly.
- Tests: `tests/Feature/Tenancy` (web and API isolation suites: every route with another school's record answers 404, plus a guard test that fails when a new route is not covered), `SchoolOwnedModelsTest` (every school-owned model uses the trait).

### Insecure direct object references (IDOR) within a school

- A **policy per model** (`StudentPolicy`, `AttendancePolicy`, `GradePolicy`, `InvoicePolicy`, …), checked in Form Requests (`authorize()`) or controllers. Teachers only reach their own classes and class-subjects; parents only their own children's records, found through their guardian records.
- Permissions are spatie/laravel-permission roles in **teams mode** with the school as team (ADR 0002), so a role at one school grants nothing at another.
- Mass assignment: every model lists `#[Fillable]` attributes; nothing is unguarded.
- Tests: every controller has authorised, unauthorised (403) and cross-school (404) cases.

### Invitation token abuse

- Invitation links are **signed, expiring URLs** (`INVITATION_EXPIRE_DAYS`, 7). The link carries the invitation's `invited_at`; re-sending changes it, so older links stop working.
- Accepting sets a password only for an invitee without one; an invitee with an account must be signed in as that account (`AcceptInvitationRequest::authorize()`). Following the link proves the email address, so it is marked verified.
- Sending is limited to 50 invitations per school and hour; accepting to 6 attempts per minute.

### Webhook spoofing (Stripe)

- `POST /webhooks/stripe` verifies the `Stripe-Signature` header with the webhook secret and a 5-minute timestamp tolerance; anything else gets 400 and a log line.
- Each event id is stored in `stripe_webhook_events` in the same transaction as its effect, so a replayed or duplicated event changes nothing.
- Payment amounts come from Stripe's event, never from the browser. Checkout is off without keys. Rate limited to 120 requests per minute.
- Tests: valid, invalid and missing signatures, duplicate events, partial payments, refunds.

### File access

- Every file (photos, report-card and invoice PDFs, receipts, imports, error reports, GDPR exports) lives on the **private** disk (`storage/app/private`). Laravel's built-in `/storage` route for that disk is **switched off** (`serve => false`): it would serve any file on a valid signature without checking a policy.
- Every download goes through a controller that checks the policy. PDFs and exports also need a **temporary signed URL** (30 minutes for PDFs; 24 hours for GDPR exports, which also need a signed-in data protection admin) and are sent with `Cache-Control: private, no-store`; photos are `private` (never kept by shared caches).
- Uploads: photos must be JPEG, PNG or WebP images (no SVG), at most 2 MB and 4000 × 4000 px; imports CSV or XLSX, by extension and content type. Stored under generated names.
- PDFs are rendered by dompdf with **remote resources and PHP off**, so a crafted value cannot make the server fetch a URL (SSRF) or run code.

### Prompt injection via remarks (AI comment drafts)

- **Data minimisation doubles as injection defence** (ADR 0007): the model receives only the first name, grades per subject, overall grade, attendance percentage and trend as JSON. Free-text remarks, which a user could fill with instructions, are never sent.
- The system prompt says the JSON is data, never instructions, and asks for the comment only.
- A draft is shown **beside** the comment field; nothing AI-written reaches parents until a teacher has edited and approved the report card. The feature is off per school by default and limited to 100 drafts per school and hour.
- Residual risk: a first name or subject name could still carry text aimed at the model. The output is plain text that a teacher reviews, so the worst case is a strange draft.

### Cross-site scripting (XSS)

- Vue escapes all interpolated values; the only `v-html` renders the privacy notice from our own Markdown files with raw HTML stripped and unsafe links disabled.
- **Content Security Policy** on every response (`SecurityHeaders` middleware): scripts only from our origin or with the request's nonce (Vite, Ziggy and Horizon add it), no `eval`, no plugins, no framing, forms and requests to our origin only. Styles allow `'unsafe-inline'` because Vue's style bindings and Chart.js set inline styles; that allows CSS injection at most, not script execution. The Horizon dashboard (platform admins only) compiles Vue templates in the browser and gets `'unsafe-eval'` on its path only.
- Fonts are served from our own origin, so the policy names no third-party host (and no font CDN sees visitors' IP addresses).
- The Playwright page walk (`tests/e2e/pages.spec.ts`) fails on any CSP violation in the browser console, so a page the policy would break fails in CI, not in production.

### Spreadsheet formula injection

A student called `=HYPERLINK("https://evil.example","Click")` would become a live formula in an exported spreadsheet.

- All exports are XLSX files whose text cells are written as text (`StringValueBinder`); numbers stay numbers.
- The import error report repeats uploaded values. It used to be a CSV, which has no cell types and lets Excel evaluate anything starting with `=`, `+`, `-` or `@`; since milestone 11 it is an XLSX file.
- Tests: `AttendanceReportTest`, `StudentImportTest` assert that such a value comes back as a text cell.

### Authentication attacks

- Passwords: at least 12 characters (NIST SP 800-63B favours length over composition rules); in production also checked against known breaches through Have I Been Pwned's range API, which only ever sees 5 characters of a SHA-1 hash. Hashed with bcrypt.
- Sign-in: 5 attempts per minute per email address and IP address (web and API). Registration and both password reset steps: 6 requests per minute per IP address.
- **No account enumeration**: the forgot-password form answers the same for unknown addresses and throttled requests; the API token endpoint answers wrong password, unknown user and no password with one message.
- Sessions are regenerated on sign-in and invalidated on sign-out. Cookies are `HttpOnly`, `SameSite=Lax`, and `Secure` in production by default.
- CSRF: Laravel's token on every web form and Inertia request. The parent API uses bearer tokens only (`sanctum.guard = []`), never cookies, so it needs no CSRF protection, and its CORS default (any origin, no credentials) cannot be used to ride a session.

### API tokens

- One Sanctum token per device and school (ADR 0009), stored hashed, expiring after `API_TOKEN_DAYS` (90), revoked on sign-out. A token stops working the moment the membership ends.
- 60 requests per minute per token; 5 sign-in attempts per minute per email and IP address.
- Parents only: staff accounts get no API token.

### Other headers

`X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` (camera, microphone, geolocation, payment, USB off), and on HTTPS requests `Strict-Transport-Security` and `Cross-Origin-Opener-Policy: same-origin`.

### Dependencies and secrets

- Versions are pinned by `composer.lock` and `package-lock.json`; nothing published in the 14 days before it was added. CI runs `composer audit` and `npm audit` on every push.
- No secrets in the repository: configuration comes from the environment (`.env.example` documents every variable). `APP_DEBUG` must be false in production; with it on, server error pages are sent without a CSP so the debug page works.

### Abuse of expensive operations

Per-school or per-user rate limits on everything that sends mail, calls an external API or does heavy work: invitations (50/h), imports (30/h), GDPR exports (20/h), AI drafts (100/h), Stripe Checkout (10/min per user).

### Deployment (milestone 12)

- **Spoofed client addresses.** Rate limits and the audit log need the real client IP. Caddy discards any `X-Forwarded-For` a client sends and sets its own; Laravel trusts `X-Forwarded-*` only from `TRUSTED_PROXIES` (the private Docker network in production, nobody by default). Tests: `tests/Feature/Security/TrustedProxiesTest.php`.
- **Small attack surface.** Only Caddy publishes ports (80, 443); MySQL and Redis (with a password) are on the internal network. PHP-FPM runs as `www-data`; the images hold no dev dependencies, tests, `.env` or `.git`. Nginx serves no PHP file other than the front controller, and no dotfiles. Containers get their configuration from `.env.production` (mode 600) at start; nothing secret is in an image.
- **HTTPS everywhere.** Caddy redirects HTTP and renews certificates; cookies are `Secure` and HSTS is sent once Laravel sees HTTPS through the trusted proxy. Sessions are encrypted (`SESSION_ENCRYPT=true`).
- **The public demo** (`DEMO_MODE`) sends no email (`MAIL_MAILER=log`), so its forms cannot be used to mail strangers, and asks visitors not to enter real personal data. Everything they enter is deleted every night, and every session ends (sessions have their own Redis database, which the reset empties): otherwise a new sign-up could reuse the id of yesterday's user and inherit their session. `demo:reset` refuses to run without `DEMO_MODE`. Tests: `tests/Feature/Demo/DemoModeTest.php`; CI runs a reset on the production stack.
- Details: `docs/DEPLOYMENT.md`, ADR 0012.

## Audit trail

Changes to students, marks, invoices, payments, report-card approvals and GDPR actions are logged per school with the user, the changed fields and old and new values (spatie/laravel-activitylog, ADR 0010). School admins can filter the log; anonymising a student also scrubs their personal values from it (`docs/GDPR.md`).

## Security review, milestone 11

A review against this document found and fixed:

| Finding | Fix |
|---|---|
| No Content Security Policy or security headers | `SecurityHeaders` middleware with a nonce-based CSP, tested per page in the browser |
| Fonts loaded from a third-party CDN (IP addresses to a third party; CSP would need its host) | Figtree bundled and served from our origin |
| The private disk had Laravel's signed `/storage` route on, which bypasses policies | `serve => false` |
| Attendance export wrote names with the default value binder: `=…` became a formula | Text cells; test |
| Import error report was a CSV repeating uploaded values | XLSX with text cells; test |
| Forgot-password revealed whether an address had an account | One answer for all cases; test |
| Laravel's default minimum password length (8) | 12, plus breach check in production |
| Registration and password reset had no per-IP limit | 6 per minute; tests |
| Session cookies not `Secure` unless configured | `Secure` by default in production |
| Dependency vulnerabilities only checked by hand | `composer audit` and `npm audit` in CI |

Not in scope for v1: two-factor authentication, SSO, a web application firewall, malware scanning of uploads (only images and spreadsheets are accepted, and neither is ever executed or served inline as HTML).
