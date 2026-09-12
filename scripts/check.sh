#!/usr/bin/env bash
set -Eeuo pipefail

project_dir="${1:-$PWD}"
cd "$project_dir"

source scripts/isolated-environment.sh

export ASSESTME_TEST_DB_DRIVER=sqlite
assestme_begin_isolated_environment check
trap assestme_end_isolated_environment EXIT

composer validate --strict
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
php artisan test --testsuite=Unit
