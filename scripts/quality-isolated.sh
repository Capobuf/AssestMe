#!/usr/bin/env bash
set -Eeuo pipefail

project_dir="${1:-$PWD}"
cd "$project_dir"

source scripts/isolated-environment.sh
source scripts/gate-receipts.sh

assestme_clear_gate_receipt quality "$project_dir"
quality_fingerprint="$(assestme_gate_fingerprint quality "$project_dir")"

assestme_begin_isolated_environment quality

cleanup_quality_environment() {
    assestme_cleanup_isolated_environment_file
    assestme_end_isolated_environment
}

trap cleanup_quality_environment EXIT
assestme_prepare_isolated_environment_file "$PWD"

vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
php artisan migrate:fresh --seed --force
php artisan test

# RefreshDatabase deliberately leaves the shared test database at its clean
# schema baseline. Rebuild a representative installed state before Canary
# sweeps pages whose forms depend on seeded domain configuration.
php artisan migrate:fresh --seed --force
export DEV_ADMIN_PASSWORD="$(php -r 'echo "Verify!".bin2hex(random_bytes(16))."aA1";')"
php artisan assestme:create-admin --from-env >/dev/null
unset DEV_ADMIN_PASSWORD
php artisan assestme:installation:lock --force >/dev/null

php artisan canary:check --strict
composer audit --locked --no-interaction

if [[ "$(assestme_gate_fingerprint quality "$project_dir")" != "$quality_fingerprint" ]]; then
    printf 'ERROR: Repository or runtime inputs changed while the quality gate was running.\n' >&2
    exit 1
fi

assestme_write_gate_receipt quality "$project_dir" "$quality_fingerprint"
