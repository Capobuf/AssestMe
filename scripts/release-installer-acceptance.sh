#!/usr/bin/env bash
set -Eeuo pipefail

fail() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

source_project="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
release_path="${1:-}"

[[ "$release_path" == /* && -d "$release_path" && -f "$release_path/artisan" ]] || \
    fail "Usage: scripts/release-installer-acceptance.sh <absolute-extracted-release-path>"
[[ ! -e "$release_path/.env" && -f "$release_path/vendor/autoload.php" ]] || \
    fail "The extracted release is not in a clean installable state."

source "$source_project/scripts/isolated-environment.sh"

test_root="${ASSESTME_TEST_ROOT:-}"
owns_test_root=0
if [[ -z "$test_root" ]]; then
    test_root="$(mktemp -d "${TMPDIR:-/tmp}/assestme-release-acceptance.XXXXXXXX")"
    owns_test_root=1
fi
[[ "$test_root" == /* ]] || fail "ASSESTME_TEST_ROOT must be absolute."

mkdir -p "$test_root/storage/logs"
touch "$test_root/.assestme-test-root"

server_pid=""
cleanup() {
    if [[ -n "$server_pid" ]]; then
        if kill "$server_pid" >/dev/null 2>&1; then
            :
        fi
        if wait "$server_pid" >/dev/null 2>&1; then
            :
        fi
    fi

    assestme_cleanup_isolated_environment_file

    if [[ "$owns_test_root" == "1" && -f "$test_root/.assestme-test-root" ]]; then
        rm -rf -- "$test_root"
    fi
}
trap cleanup EXIT INT TERM
assestme_prepare_isolated_environment_file "$source_project"

port="${ASSESTME_RELEASE_ACCEPTANCE_PORT:-}"
if [[ -z "$port" ]]; then
    port="$(php -r '
        $socket = stream_socket_server("tcp://127.0.0.1:0", $errorCode, $errorMessage);
        if ($socket === false) { fwrite(STDERR, $errorMessage); exit(1); }
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        echo substr((string) $address, strrpos((string) $address, ":") + 1);
    ')"
fi
[[ "$port" =~ ^[0-9]+$ ]] || fail "ASSESTME_RELEASE_ACCEPTANCE_PORT must be numeric."
server_bind="${DUSK_SERVER_BIND:-127.0.0.1}"
browser_host="${DUSK_BROWSER_HOST:-127.0.0.1}"
ready_host="${DUSK_READY_HOST:-127.0.0.1}"
application_url="http://${browser_host}:${port}"
ready_url="http://${ready_host}:${port}"
configured_application_url="https://${browser_host}:${port}"
administrator_password="CI!$(php -r 'echo bin2hex(random_bytes(14));')aA1"
database_host="${ASSESTME_DUSK_DATABASE_HOST:-}"
database_port="${ASSESTME_DUSK_DATABASE_PORT:-}"
database_name="${ASSESTME_DUSK_DATABASE_NAME:-}"
database_username="${ASSESTME_DUSK_DATABASE_USERNAME:-}"
database_password="${ASSESTME_DUSK_DATABASE_PASSWORD:-}"

[[ -n "$database_host" ]] || fail "ASSESTME_DUSK_DATABASE_HOST is required."
[[ "$database_port" =~ ^[0-9]+$ ]] || fail "ASSESTME_DUSK_DATABASE_PORT must be numeric."
[[ -n "$database_name" ]] || fail "ASSESTME_DUSK_DATABASE_NAME is required."
[[ -n "$database_username" ]] || fail "ASSESTME_DUSK_DATABASE_USERNAME is required."
[[ -n "$database_password" ]] || fail "ASSESTME_DUSK_DATABASE_PASSWORD is required."

(
    cd "$release_path/public"
    exec php -d variables_order=EGPCS -S "${server_bind}:${port}" \
        ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
) >"$test_root/storage/logs/release-server.log" 2>&1 &
server_pid="$!"

ready=0
for attempt in {1..40}; do
    if curl --fail --silent "${ready_url}/install" >/dev/null; then
        ready=1
        break
    fi
    sleep 0.25
done
[[ "$ready" == "1" ]] || fail "The extracted release HTTP server did not become ready."

cd "$source_project"
APP_URL="$application_url" \
ASSESTME_DUSK_ADMIN_PASSWORD="$administrator_password" \
ASSESTME_DUSK_APPLICATION_URL="$configured_application_url" \
ASSESTME_DUSK_DATABASE_HOST="$database_host" \
ASSESTME_DUSK_DATABASE_PORT="$database_port" \
ASSESTME_DUSK_DATABASE_NAME="$database_name" \
ASSESTME_DUSK_DATABASE_USERNAME="$database_username" \
ASSESTME_DUSK_DATABASE_PASSWORD="$database_password" \
ASSESTME_DUSK_INSTALLER=1 \
ASSESTME_RELEASE_PATH="$release_path" \
ASSESTME_TEST_ISOLATED=1 \
ASSESTME_TEST_ROOT="$test_root" \
php artisan dusk --without-tty tests/Browser/ReleaseInstallationTest.php

[[ -s "$release_path/.env" ]] || fail "The installed release .env is missing or empty."
[[ -f "$release_path/storage/app/private/installed.lock" ]] || fail "The installation lock is missing."
[[ ! -e "$release_path/.env.pending" ]] || fail "The pending environment file was not removed."
[[ ! -e "$release_path/storage/framework/installer/state.enc" ]] || fail "Installer state was not removed."

cd "$release_path"
env -u APP_ENV -u APP_KEY -u APP_URL -u DB_CONNECTION -u DB_DATABASE -u ASSESTME_BACKUP_ROOT \
    php artisan schedule:run
diagnostic_payload="$(env -u APP_ENV -u APP_KEY -u APP_URL -u DB_CONNECTION -u DB_DATABASE -u ASSESTME_BACKUP_ROOT \
    php artisan assestme:diagnose --json)"
printf '%s' "$diagnostic_payload" | php -r '
    $report = json_decode(stream_get_contents(STDIN), true, 64, JSON_THROW_ON_ERROR);
    exit(($report["database"]["product"] ?? null) === "MariaDB" ? 0 : 1);
'

health_payload="$(curl --fail --silent --show-error --max-time 15 "${ready_url}/up")"
[[ "$health_payload" == *'"status":"healthy"'* || "$health_payload" == *'"status": "healthy"'* ]] || \
    fail "The installed application health endpoint is not healthy."
[[ "$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' --max-time 15 "${ready_url}/admin/login")" == "200" ]] || \
    fail "The installed administrator login endpoint is unavailable."

printf 'Release installer acceptance passed.\n'
