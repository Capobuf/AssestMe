#!/usr/bin/env bash
set -Eeuo pipefail

fail() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

project_dir="${1:-$PWD}"
cd "$project_dir"

source scripts/isolated-environment.sh

[[ "${ASSESTME_TEST_DB_DRIVER:-}" == "mariadb" ]] || fail \
    "The canonical application suite requires ASSESTME_TEST_DB_DRIVER=mariadb."

trap assestme_cleanup_isolated_environment_file EXIT
assestme_prepare_isolated_environment_file "$PWD"

php artisan test --testsuite=Feature
php artisan test tests/Unit/ServerDatabaseBackupRestoreTest.php
scripts/dusk-isolated.sh "$project_dir" \
    tests/Browser/ApplicationSmokeTest.php \
    tests/Browser/WorkspaceLocalDraftTest.php \
    tests/Browser/RiskMatrixFieldTest.php \
    tests/Browser/ReportSettingsPreviewTest.php
