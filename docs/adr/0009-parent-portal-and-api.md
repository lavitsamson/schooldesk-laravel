# ADR 0009: Parent portal and a token API scoped to one school

- **Status:** Accepted
- **Date:** 2026-10-25
- **Deciders:** Lavit Samson

## Context

Parents need to see their children's attendance, results, report cards and invoices, pay
online and read what the school has told them. They do this in the browser (the Inertia
portal) and, later, in a mobile app (a JSON API). Both must respect the tenancy rules of
ADR 0001: the school a request acts for is never taken from request input, and another school's
records are a 404.

What makes this harder:

- A parent can have children at two schools: one user account, two memberships.
- The web app picks the school from the session (the school switcher). An API client has no
  session; a bearer token has to carry the school.
- Notifications (absences, report cards, invoices) are stored per user by Laravel's database
  channel, not per school. A parent at two schools would see both schools' notifications
  everywhere.
- The portal and the API show the same data. Two implementations would drift.

## Decision

1. **One token per device and school.** `personal_access_tokens` has a `school_id`.
   `POST /api/v1/auth/token` takes email, password and a device name. A parent with one active
   membership gets a token for that school; with several, they choose with `school_id`, and the
   membership is checked. Tokens go to users with the parent role in that school only (403
   otherwise). They expire after 90 days (`API_TOKEN_DAYS`) and are pruned daily. Sanctum
   stores only the SHA-256 hash; the `sd_` prefix makes leaked tokens recognisable to secret
   scanners.

2. **SetCurrentSchool reads the school from the token.** The same middleware serves both
   worlds: the session's selected membership for web requests, the token's `school_id` for API
   requests. Either way the membership must still be active on every request, so removing a
   parent locks the app out at once (403, "Your membership of this token's school has ended").
   The API accepts bearer tokens only (`sanctum.guard = []`): a portal session cookie does not
   authenticate API calls, so the API needs no CSRF protection.

3. **Notifications remember their school.** `notifications.school_id` is filled by
   `SchoolDatabaseChannel`, bound in place of Laravel's `DatabaseChannel`, from the current
   school. Notifications are sent from queued jobs that already run inside their school
   (`CurrentSchool::run`). The portal, the badge and the API read the current school's
   notifications only.

4. **Shared actions, Form Requests and resources.** `ListChildren`, `BuildAttendanceMonth`,
   `ListPublishedReportCards`, `ListNotifications`, `ListFamilyInvoices` and
   `CalculateReportCard` serve both the portal controllers and the API controllers. So do the
   Form Requests (`ChildRequest` authorises "one of my children" via
   `StudentPolicy::viewAsGuardian`) and the JSON resources. The API controllers are thin:
   `/children/{student}/attendance` and the portal's attendance calendar are the same code.

5. **Children are found through guardian records only.** `Student::childrenOf($user)` is the
   single definition of "my children", used by policies, lists and invoices. Route binding
   applies the school scope first, so another school's student is a 404. Another family's
   child in the same school is a 403: the record exists in the parent's school, and saying so
   reveals nothing a school directory would not.

6. **Parents see published report cards only.** `ReportCardPolicy::download` allows the class
   teacher and admins (any status) and guardians once the card is published. The portal links
   to the PDF with a signed, expiring URL; the API serves it to the bearer token.

7. **One error shape, cursor pagination.** Every API error is
   `{ "message", "code", "errors"? }` (`ApiExceptionRenderer`). `code` is stable for clients
   (`not_found`, `validation_failed`, `too_many_requests`, …), 404s never name the model, and
   unexpected errors say nothing outside debug mode. Lists use cursor pagination
   (`?limit=1..100&cursor=`), which stays correct while new notifications arrive and needs no
   `COUNT(*)`. Bounded documents (a month of attendance, a term's grades) are not paginated.
   Rate limits: 60 requests a minute per token, 5 sign-in attempts a minute per email and IP.

8. **Docs from the code, as static files.** Scribe reads routes, Form Request rules and the
   `@response` examples in the controllers' docblocks and writes `public/docs` (HTML, OpenAPI,
   Postman), served at `/docs`. It never calls the API to get examples. Scribe is a dev
   dependency, and docblocks (not PHP attributes) keep production free of its classes.

## Consequences

- A parent at two schools signs in twice in the app, once per school. That matches the web
  app, where they switch schools, and keeps every token's reach obvious.
- The portal's data and the API's cannot drift. Changing a resource changes both, so API
  changes need care: the resources are the v1 contract.
- Notifications sent outside a school context get no `school_id` and appear nowhere. All
  current senders run in a school; a test pins the channel's behaviour.
- The parents' dashboard is the family overview, at the same URL as the staff dashboard. A
  user who is both staff and parent keeps the staff dashboard and reaches their children
  through the navigation.
- Generated docs are committed. Regenerate them (`php artisan scribe:generate`) when an
  endpoint changes.

## Alternatives considered

- **School id in the URL (`/api/v1/schools/{school}/children`)** with a membership check on
  each request. It works, but it reintroduces the school as request input, which ADR 0001 rules
  out, and every endpoint would have to remember the check.
- **Sanctum's SPA (cookie) mode for the API.** It suits a first-party SPA, not a mobile app,
  and it would mix session and token authentication on the same routes.
- **Laravel Passport / OAuth.** Too much for one first-party app: no third-party clients, no
  scopes beyond "parent".
- **Filtering notifications by the children in their data** instead of a `school_id` column.
  It is fragile (every type stores different keys) and needs JSON queries that differ between
  MySQL and PostgreSQL.
