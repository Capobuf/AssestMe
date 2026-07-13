#!/usr/bin/env bash
set -Eeuo pipefail

fail() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

info() {
    printf 'INFO: %s\n' "$*"
}

project_dir="${1:-$PWD}"
cd "$project_dir"

[[ -f artisan && -f composer.json ]] || fail "Run this script from the AssestMe Laravel project root."
[[ -f composer.lock ]] || fail "composer.lock is required."
[[ -f database/database.sqlite ]] || fail "SQLite database is missing."

composer validate --strict
composer audit --locked --no-interaction
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
php artisan test

if php artisan list --raw | grep -q '^canary:check'; then
    php artisan canary:check --strict
else
    fail "Canary command canary:check is unavailable."
fi

php artisan migrate:status
php artisan assestme:diagnose
php artisan assestme:benchmark --findings=50
php artisan route:list --except-vendor
php artisan about

if [[ "${RUN_DUSK:-0}" == "1" ]]; then
    php artisan dusk
else
    info "Dusk was not run. Set RUN_DUSK=1 after Chrome/ChromeDriver are available."
fi

info "All requested verification checks passed."
