#!/usr/bin/env bash
set -Eeuo pipefail

project_dir="${1:-$PWD}"
cd "$project_dir"

source scripts/isolated-environment.sh

export ASSESTME_TEST_DB_DRIVER=sqlite
assestme_begin_isolated_environment check

cleanup_check_environment() {
    assestme_cleanup_isolated_environment_file
    assestme_end_isolated_environment
}

trap cleanup_check_environment EXIT
assestme_prepare_isolated_environment_file "$PWD"

composer validate --strict
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
php artisan test --testsuite=Unit
