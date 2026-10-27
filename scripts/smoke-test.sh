#!/usr/bin/env bash
# Smoke test of a running deployment (CI's production-stack job, and after every deploy):
#
#   scripts/smoke-test.sh https://demo.schooldesk.example [email password]
#
# Checks health, a page with its security headers, static assets and blocked paths. Given an
# account (e.g. admin@riverside.demo DemoPass123! on the demo), it also signs in and opens
# the dashboard and the student list. Needs bash and curl only.
set -euo pipefail

base=${1:?usage: scripts/smoke-test.sh <base-url> [email password]}
base=${base%/}
email=${2:-}
password=${3:-}

jar=$(mktemp)
trap 'rm -f "$jar"' EXIT
failures=0

check() {
    local name=$1 expected=$2 actual=$3
    if [[ $actual == "$expected" ]]; then
        echo "ok    $name"
    else
        echo "FAIL  $name: expected $expected, got $actual"
        failures=$((failures + 1))
    fi
}

status() { curl -s -o /dev/null -w '%{http_code}' "$@"; }
header() { curl -s -D - -o /dev/null "${@:2}" | tr -d '\r' | grep -i "^$1:" | head -1 | cut -d' ' -f2- || true; }

check 'health check /up' 200 "$(status "$base/up")"
check 'landing page' 200 "$(status "$base/")"
check 'sign-in page' 200 "$(status "$base/login")"
check 'API docs' 200 "$(status "$base/docs/")"
check 'no .env' 403 "$(status "$base/.env")"
check 'no direct PHP files' 404 "$(status "$base/index.php")"
check 'Horizon closed to guests' 403 "$(status "$base/horizon")"

csp=$(header content-security-policy "$base/login")
check 'nonce-based CSP' yes "$([[ $csp == *"'nonce-"* ]] && echo yes || echo "${csp:-none}")"
check 'one X-Content-Type-Options header' 1 "$(curl -s -D - -o /dev/null "$base/login" | grep -ci '^x-content-type-options:' || true)"

asset=$(curl -s "$base/login" | grep -o '/build/assets/app-[^"]*\.js' | head -1 || true)
check 'Vite asset linked' yes "$([[ -n $asset ]] && echo yes || echo no)"
if [[ -n $asset ]]; then
    check 'asset served' 200 "$(status "$base$asset")"
    check 'asset cached for a year' 'public, max-age=31536000, immutable' "$(header cache-control "$base$asset")"
fi

if [[ -n $email ]]; then
    # Sign in like the browser does: the XSRF-TOKEN cookie goes back in the X-XSRF-TOKEN header.
    curl -s -c "$jar" -b "$jar" -o /dev/null "$base/login"
    xsrf=$(awk '$6 == "XSRF-TOKEN" { print $7 }' "$jar")
    xsrf=$(printf '%b' "${xsrf//%/\\x}")
    signed_in=$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -c "$jar" -b "$jar" -X POST "$base/login" \
        -H "X-XSRF-TOKEN: $xsrf" --data-urlencode "email=$email" --data-urlencode "password=$password")
    # A failed sign-in redirects too, but back to the form.
    check 'sign in' yes "$([[ $signed_in == 302\ */dashboard ]] && echo yes || echo "$signed_in")"
    check 'dashboard after sign-in' 200 "$(status -b "$jar" "$base/dashboard")"
    check 'student list' 200 "$(status -b "$jar" "$base/students")"
fi

if ((failures > 0)); then
    echo "$failures check(s) failed."
    exit 1
fi
echo 'All checks passed.'
