#!/bin/sh
# Entrypoint of the app image: PHP-FPM, Horizon and the scheduler all start here.
# Configuration comes from the environment at run time, so the caches are built on start,
# not at build time. Migrations are a separate step of the deploy (scripts/deploy.sh), so
# several containers starting at once never race to migrate.
set -eu

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is not set. Generate one with: echo \"base64:\$(openssl rand -base64 32)\"" >&2
    exit 1
fi

php artisan optimize --no-interaction > /dev/null

exec "$@"
