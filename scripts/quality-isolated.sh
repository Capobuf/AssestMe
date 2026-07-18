#!/usr/bin/env bash
set -Eeuo pipefail

project_dir="${1:-$PWD}"
cd "$project_dir"

source scripts/isolated-environment.sh
source scripts/gate-receipts.sh

assestme_clear_gate_receipt quality "$project_dir"
quality_fingerprint="$(assestme_gate_fingerprint quality "$project_dir")"

assestme_begin_isolated_environment quality
trap assestme_end_isolated_environment EXIT

vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
php artisan migrate:fresh --seed --force
php artisan test
php artisan canary:check --strict
composer audit --locked --no-interaction

if [[ "$(assestme_gate_fingerprint quality "$project_dir")" != "$quality_fingerprint" ]]; then
    printf 'ERROR: Repository or runtime inputs changed while the quality gate was running.\n' >&2
    exit 1
fi

assestme_write_gate_receipt quality "$project_dir" "$quality_fingerprint"
