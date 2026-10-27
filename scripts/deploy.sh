#!/usr/bin/env bash
# Deploys the checked-out branch on the server (docs/DEPLOYMENT.md). Run from the checkout:
#
#   scripts/deploy.sh            # pull, build, migrate, restart, smoke test
#   scripts/deploy.sh --seed     # first deploy: also load the demo seed
#
# Downtime is the few seconds the containers take to restart. Migrations run against the
# new image before the old containers stop, so they must keep the running code working
# (add columns first, remove them in a later release).
set -euo pipefail

cd "$(dirname "$0")/.."

env_file=.env.production
if [[ ! -f $env_file ]]; then
    echo "$env_file is missing: copy .env.production.example and fill it in." >&2
    exit 1
fi

compose=(docker compose -f compose.prod.yaml --env-file "$env_file")
setting() { grep -E "^$1=" "$env_file" | tail -1 | cut -d= -f2- | tr -d '"'; }

git pull --ff-only
export IMAGE_TAG
IMAGE_TAG=$(git rev-parse --short HEAD)

echo "Building $IMAGE_TAG ..."
"${compose[@]}" build

echo 'Migrating ...'
"${compose[@]}" up -d --wait mysql redis
if [[ ${1:-} == --seed ]]; then
    "${compose[@]}" run --rm app php artisan migrate --force --seed
else
    "${compose[@]}" run --rm app php artisan migrate --force
fi

echo 'Starting ...'
# Horizon finishes its running jobs before its container is replaced (stop_grace_period).
"${compose[@]}" up -d --wait --remove-orphans

# Dangling build layers only: the images of earlier releases stay tagged for a rollback.
docker image prune -f > /dev/null

url=$(setting APP_URL)
if [[ $(setting DEMO_MODE) == true ]]; then
    scripts/smoke-test.sh "$url" admin@riverside.demo 'DemoPass123!'
else
    scripts/smoke-test.sh "$url"
fi
