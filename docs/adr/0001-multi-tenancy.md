# ADR 0001 — Multi-tenancy: shared database with a school-scoped global scope

- **Status:** Accepted
- **Date:** 2026-10-23
- **Deciders:** Lavit Samson

## Context

SchoolDesk is a SaaS used by many schools. Each school (the tenant) owns its students, guardians,
grades, attendance, invoices and report cards. Data from one school must never be visible to
another, and a single user (for example a teacher who works at two schools, or a parent with
children at different schools) can belong to more than one school.

Operational constraints:

- One small team runs the product, on a single VPS at first, so operational simplicity matters.
- Reporting across the platform (for platform admins) should be possible without fanning out
  over hundreds of databases.
- The codebase has to run on both MySQL 8 and PostgreSQL, so vendor-specific isolation features
  cannot be the only line of defence.
- Reviewers should be able to see isolation enforced in the code and proven by tests.

## Options considered

### 1. Shared database, shared schema, `school_id` column + Eloquent global scope (chosen)

Every school-owned table has an indexed `school_id` foreign key, which is the first column of
composite indexes. A `BelongsToSchool` model trait:

- adds a global scope that filters every query by `CurrentSchool::id()`;
- fills `school_id` automatically when a model is created;
- throws if a query runs inside a web or API request with no school context set.

The `SetCurrentSchool` middleware sets the context from the user's selected membership (session)
or from the Sanctum token's school. **Never from request input.** Queued jobs carry `schoolId`
explicitly and set the context in `handle()`. Roles use spatie/laravel-permission in teams mode
with `school_id` as the team id.

- ➕ One database, one migration run, simple backups and deploys.
- ➕ Works the same on MySQL and PostgreSQL.
- ➕ Cross-school platform reporting is a normal query (with the scope removed on purpose).
- ➕ Cheap to onboard a school: insert one row.
- ➖ Isolation depends on application code; a forgotten scope or a raw query could leak data.
- ➖ A noisy school shares resources with everyone else.
- ➖ Per-school restore is harder (a filtered export instead of restoring a database).

### 2. Database per tenant (e.g. `stancl/tenancy`)

- ➕ Strong physical isolation; per-school backup/restore and data residency are easy.
- ➖ N databases to migrate, monitor and back up; migrations become a fan-out job.
- ➖ Connection management and queue workers become tenant-aware everywhere.
- ➖ Cross-school reporting needs aggregation across databases.
- ➖ Too much operational weight for a product that is starting on one VPS.

### 3. Schema per tenant (PostgreSQL schemas)

- ➕ Better isolation than a shared schema, one database server.
- ➖ PostgreSQL-only; the MySQL requirement rules it out.
- ➖ Same migration fan-out problem as option 2.

### 4. Shared schema + database row-level security (PostgreSQL RLS)

- ➕ Isolation is enforced by the database even for raw SQL.
- ➖ PostgreSQL-only; MySQL has no equivalent.
- ➖ Needs a per-connection session variable, which is awkward with connection pooling and
  queue workers.

## Decision

Use **option 1**: a single shared database with `school_id` on every school-owned table, enforced
by the `BelongsToSchool` global scope, the `SetCurrentSchool` middleware, policies, and
spatie/laravel-permission teams.

We reduce the main risk (isolation living in application code) with:

1. **The trait fails closed.** With no school context inside a request, queries throw instead of
   returning every school's rows.
2. **Tenant isolation test suite.** For every route, a user from School A gets **404** (not 403,
   so record existence is not leaked) for School B's records.
3. **Pest architecture tests** check that every school-owned model uses `BelongsToSchool`.
4. **Code review rule.** Raw queries and `withoutGlobalScope` calls must take `school_id`
   explicitly and are kept to a few audited places.

## Consequences

- Every new school-owned table needs `school_id`, a composite index that starts with it, the
  trait on its model, and isolation tests. The milestone checklist and the Definition of Done
  enforce this.
- If a customer later needs physical isolation or data residency, we can move to option 2 for
  that customer. Because tenant resolution is centralised in `CurrentSchool`, the change stays
  contained.
