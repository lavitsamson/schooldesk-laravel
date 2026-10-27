# ADR 0012: Production deployment with Docker Compose, and a self-resetting public demo

- **Status:** Accepted
- **Date:** 2026-10-27
- **Deciders:** Lavit Samson

## Context

The release milestone asks for a production Docker setup (PHP-FPM, Nginx, Horizon, scheduler),
documented deployment to a VPS with Docker, and a public demo whose data resets every night.

Constraints:

- One small VPS, run by one person: no Kubernetes, no managed services required.
- HTTPS with certificates that renew themselves.
- The app has three runtime roles (web requests, queue workers, scheduler) that must run the
  same code and share uploaded and generated files (photos, imports, PDFs, exports).
- Nothing from development may end up in production: dev dependencies (Scribe, Faker, Pest,
  Sail), tests, `node_modules`, `.env`.
- A public demo invites strangers to type into it, so it must forget everything daily and must
  not become a way to send email to arbitrary addresses.

## Decision

1. **One multi-stage Dockerfile, two images.** `docker/Dockerfile` builds Composer dependencies
   without dev packages, the Vite assets in a Node stage, and two targets:
   - `app`: PHP-FPM 8.4 (Alpine) with exactly the extensions the app uses (bcmath, intl, gd,
     zip, pcntl, exif, opcache, MySQL and PostgreSQL drivers, phpredis), production `php.ini`
     (opcache without timestamp checks: the code never changes inside a container), running
     as `www-data`. The same image runs PHP-FPM, `artisan horizon` and `artisan schedule:work`.
   - `web`: Nginx with only `public/` (built assets, fonts, the static API docs). It serves
     files itself and passes everything else to PHP-FPM.

   Configuration is read from the environment at start (`artisan optimize` in the entrypoint),
   so one image runs anywhere; nothing secret is baked in.
2. **Caddy at the edge, Nginx behind it.** Caddy obtains and renews Let's Encrypt certificates
   with no cron job or first manual step, redirects HTTP to HTTPS and compresses responses.
   Nginx remains the FastCGI front for PHP-FPM, as the spec asks, which keeps the PHP side a
   conventional, well-documented setup. Caddy discards any `X-Forwarded-For` a client sends and
   sets its own; Laravel trusts `X-Forwarded-*` only from the private Docker network
   (`TRUSTED_PROXIES`), so rate limits see the real client address and `Secure` cookies and
   HSTS see HTTPS.
3. **Docker Compose on one host** (`compose.prod.yaml`): Caddy, Nginx, PHP-FPM, Horizon,
   scheduler, MySQL 8.4 and Redis, on an internal network where only Caddy publishes ports.
   `storage/app` is a named volume shared by PHP-FPM and Horizon. Logs go to the containers'
   output (`LOG_CHANNEL=stderr`). Health checks: PHP-FPM's ping page, Nginx, `horizon:status`.
4. **Deploys are a script, not a pipeline** (`scripts/deploy.sh` on the server): pull, build
   images tagged with the commit, run migrations with the new image while the old containers
   still serve, restart, then `scripts/smoke-test.sh`. Earlier images stay tagged for a
   rollback. Migrations must therefore keep the running release working (expand, then
   contract in a later release).
5. **CI builds and runs the production stack** on every push: both images, `up --wait`, migrate
   and seed, the same smoke test, `horizon:status`, and a demo reset followed by the smoke test
   again.
6. **Demo mode** (`DEMO_MODE=true`): a banner on every page, the demo accounts on the sign-in
   page, and `demo:reset` scheduled nightly. The reset puts the app into maintenance mode
   (stored in Redis, so every container sees it), clears the queues, deletes stored files, runs
   `migrate:fresh --seed`, and empties the sessions' own Redis database. Sessions must go: the
   seed gives demo users the same ids every night, but new sign-ups get the ids of yesterday's,
   and a surviving session would open someone else's new school. The command refuses to run
   without `DEMO_MODE`. On the demo, mail goes to the log, so sign-up and invitation forms
   cannot be used to send email; visitors use the demo accounts.

## Consequences

- One `docker compose` command brings up the whole system, locally (for a trial) or on a VPS,
  and CI proves on every push that the images build, boot, migrate, seed and serve.
- Two web servers (Caddy and Nginx) are one hop more than strictly necessary. In return each
  does one job, and TLS needs no manual steps.
- One host is a single point of failure, and MySQL runs in a container rather than as a managed
  database. Backups (database dump plus the storage volume, `docs/DEPLOYMENT.md`) are the
  operator's job. Moving to a managed database, S3 storage or several app hosts needs only
  environment changes (`DB_HOST`, `FILESYSTEM_DISK`) and an external load balancer.
- A deploy restarts the containers: a few seconds in which requests may fail. Zero-downtime
  deploys (two app containers, rolling restarts) are left for when real schools depend on it.
- The demo forgets everything at night, including schools visitors registered, and their
  verification emails are never delivered. That is the point: the demo accounts show every
  role in seconds.

## Alternatives considered

- **Nginx with certbot:** one server fewer, but certificates need a first manual issue and a
  renewal job with Nginx reloads.
- **Caddy with PHP-FPM directly (no Nginx):** simpler, but departs from the specified stack,
  and Nginx's static file and FastCGI handling is what most PHP teams already know.
- **FrankenPHP / Laravel Octane:** faster, but long-lived workers change how state and memory
  behave; not worth it at this scale, and the app was not written with Octane in mind.
- **Laravel Forge or a PaaS:** less to maintain, but hides the operations this milestone is
  meant to show, and adds a paid dependency.
- **Images pushed to a registry from CI:** cleaner for several servers. On one server, building
  there avoids registry credentials; CI still builds the same Dockerfile on every push.
