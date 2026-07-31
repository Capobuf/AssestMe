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

source scripts/isolated-environment.sh
source scripts/gate-receipts.sh

composer validate --strict

if assestme_gate_receipt_matches quality "$project_dir"; then
    info "Reusing the successful quality gate for the exact current repository and runtime fingerprint."
else
    info "No matching quality receipt was found; running the isolated quality gate once."
    bash scripts/quality-isolated.sh "$project_dir"
fi

assestme_begin_isolated_environment verify
trap assestme_end_isolated_environment EXIT

php artisan migrate:fresh --seed --force

export DEV_ADMIN_PASSWORD="$(php -r 'echo "Verify!".bin2hex(random_bytes(16))."aA1";')"
php artisan assestme:create-admin --from-env >/dev/null
unset DEV_ADMIN_PASSWORD
php artisan assestme:installation:lock --force >/dev/null

php artisan migrate:status
php artisan assestme:diagnose
php artisan assestme:benchmark --findings=50
php artisan assestme:storage:audit
php artisan route:list --except-vendor
php artisan about

if [[ "${RUN_DUSK:-1}" == "1" ]]; then
    if assestme_gate_receipt_matches browser "$project_dir"; then
        info "Reusing the successful browser gate for the exact current repository and runtime fingerprint."
    else
        info "No matching browser receipt was found; running the isolated browser gate once."
        scripts/dusk-isolated.sh "$project_dir"
    fi
else
    info "Dusk was explicitly disabled with RUN_DUSK=${RUN_DUSK}."
fi

info "All requested verification checks passed once or were proven by exact-fingerprint receipts."
