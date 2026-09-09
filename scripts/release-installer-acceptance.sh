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

    if [[ "$owns_test_root" == "1" && -f "$test_root/.assestme-test-root" ]]; then
        rm -rf -- "$test_root"
    fi
}
trap cleanup EXIT INT TERM

port="${ASSESTME_RELEASE_ACCEPTANCE_PORT:-8123}"
[[ "$port" =~ ^[0-9]+$ ]] || fail "ASSESTME_RELEASE_ACCEPTANCE_PORT must be numeric."
application_url="http://127.0.0.1:${port}"
configured_application_url="https://127.0.0.1:${port}"
administrator_password="CI!$(php -r 'echo bin2hex(random_bytes(14));')aA1"

(
    cd "$release_path/public"
    exec php -d variables_order=EGPCS -S "127.0.0.1:${port}" \
        ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
) >"$test_root/storage/logs/release-server.log" 2>&1 &
server_pid="$!"

ready=0
for attempt in {1..40}; do
    if curl --fail --silent "${application_url}/install" >/dev/null; then
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
ASSESTME_DUSK_INSTALLER=1 \
ASSESTME_FIC_DUSK_FAKE=1 \
ASSESTME_RELEASE_PATH="$release_path" \
ASSESTME_TEST_ISOLATED=1 \
ASSESTME_TEST_ROOT="$test_root" \
php artisan dusk --without-tty tests/Browser/ReleaseInstallationTest.php || dusk_status="$?"

dusk_status="${dusk_status:-0}"
if [[ "$dusk_status" -ne 0 ]]; then
    server_status=0
    if ! kill -0 "$server_pid" >/dev/null 2>&1; then
        wait "$server_pid" || server_status="$?"
        server_pid=""
    fi

    if [[ "$server_status" -eq 139 ]]; then
        printf 'Release PHP server terminated with SIGSEGV (exit 139).\n' >&2
        exit 139
    fi

    exit "$dusk_status"
fi

[[ -s "$release_path/.env" ]] || fail "The installed release .env is missing or empty."
[[ -f "$release_path/storage/app/private/installed.lock" ]] || fail "The installation lock is missing."
[[ ! -e "$release_path/.env.pending" ]] || fail "The pending environment file was not removed."
[[ ! -e "$release_path/storage/framework/installer/state.enc" ]] || fail "Installer state was not removed."

cd "$release_path"
env -u APP_ENV -u APP_KEY -u APP_URL -u DB_CONNECTION -u DB_DATABASE -u ASSESTME_BACKUP_ROOT \
    php artisan schedule:run
env -u APP_ENV -u APP_KEY -u APP_URL -u DB_CONNECTION -u DB_DATABASE -u ASSESTME_BACKUP_ROOT \
    php artisan assestme:diagnose --json >/dev/null

health_payload="$(curl --fail --silent --show-error --max-time 15 "${application_url}/up")"
[[ "$health_payload" == *'"status":"healthy"'* || "$health_payload" == *'"status": "healthy"'* ]] || \
    fail "The installed application health endpoint is not healthy."
[[ "$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' --max-time 15 "${application_url}/admin/login")" == "200" ]] || \
    fail "The installed administrator login endpoint is unavailable."

printf 'Release installer acceptance passed.\n'
