# ADR 0002 — Roles and permissions: spatie teams tied to the school context

- **Status:** Accepted
- **Date:** 2026-10-24
- **Deciders:** Lavit Samson

## Context

A user can belong to several schools with different roles: a teacher at one school can be a parent
at another. Roles must therefore be granted **per school**, and a role check must always run
against the school the request acts for (see [ADR 0001](0001-multi-tenancy.md)).

## Decision

1. **spatie/laravel-permission in teams mode**, with `school_id` as the team key. Roles and
   permissions are global rows (`school_id = null`). Only the *assignment* (`model_has_roles`)
   carries a `school_id`.

2. **The team id comes from `CurrentSchool`.** spatie normally keeps its own team id, set with
   `setPermissionsTeamId()`. That would be a second source of truth, which could drift from the
   global scope. A custom `SchoolTeamResolver` reads the team id from `CurrentSchool`, and setting
   a different id directly throws. With no school context, the user has no roles, so checks fail
   closed.

3. **Roles and permissions are code.** The `Role` and `Permission` enums define them, and
   `Role::permissions()` is the permission matrix from the spec. An idempotent
   `SyncRolesAndPermissions` action writes them to the database. A **migration** calls it, not a
   seeder, so a fresh deployment cannot run without its roles. A later change to the matrix adds
   a new migration that runs the sync again.

4. **Policies check permissions, not role names** (`$user->hasPermissionTo(Permission::ManageMembers)`).
   Changing what a role may do is a change to the enum, not to every policy.

5. **Platform admin is a flag** (`users.is_platform_admin`), not a team role. It does not grant
   access to school data: platform admins manage schools, they do not read students' records.

## Alternatives considered

- **Role column on `school_user`.** Simple, but limited to one role per school, and every check
  has to be hand-written. spatie's teams mode gives several roles per school, and its caching
  and Gate integration come for free.
- **Seeder for roles.** It is easy to forget on a new environment, and registration then fails
  with an obscure error. A migration runs on every deploy anyway.
- **`Gate::before` for platform admins.** Rejected: it would quietly give platform staff access
  to every school's personal data, which conflicts with GDPR data minimisation.

## Consequences

- Code that grants roles for a school other than the current one goes through `GrantRole`. It
  switches the context temporarily with `CurrentSchool::run()`.
- Long-lived user instances (tests, Octane, queue workers) can hold a `roles` relation loaded
  for another school. `SetCurrentSchool` and `GrantRole` unset it after switching.
- Pending invitations already carry their role, but grant nothing until accepted, because
  `SetCurrentSchool` only accepts active memberships.
