#!/usr/bin/env bash
set -Eeuo pipefail

project_dir="${1:-$PWD}"
cd "$project_dir"

source scripts/isolated-environment.sh
assestme_begin_isolated_environment quality
trap assestme_end_isolated_environment EXIT

vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
php artisan migrate:fresh --seed --force
php artisan test
php artisan canary:check --strict
composer audit --locked --no-interaction
