#!/usr/bin/env bash
set -Eeuo pipefail

project_dir="${1:-$PWD}"
if [[ $# -gt 0 ]]; then
    shift
fi
cd "$project_dir"

source scripts/isolated-environment.sh

if [[ $# -eq 0 ]]; then
    printf 'ERROR: Pass the browser test files to scripts/dusk-isolated.sh explicitly.\n' >&2
    exit 1
fi

assestme_begin_isolated_environment dusk

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
    assestme_end_isolated_environment
}
trap cleanup EXIT INT TERM
assestme_prepare_isolated_environment_file "$PWD"

php artisan migrate:fresh --seed --force
php artisan assestme:installation:lock --force --no-interaction

if [[ -z "${DUSK_PORT:-}" ]]; then
    DUSK_PORT="$(php -r '
        $socket = stream_socket_server("tcp://127.0.0.1:0", $errorCode, $errorMessage);
        if ($socket === false) { fwrite(STDERR, $errorMessage); exit(1); }
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        echo substr((string) $address, strrpos((string) $address, ":") + 1);
    ')"
fi

DUSK_SERVER_BIND="${DUSK_SERVER_BIND:-127.0.0.1}"
DUSK_BROWSER_HOST="${DUSK_BROWSER_HOST:-127.0.0.1}"
DUSK_READY_HOST="${DUSK_READY_HOST:-127.0.0.1}"

export DUSK_BROWSER_HOST DUSK_PORT DUSK_READY_HOST DUSK_SERVER_BIND
export APP_URL="http://${DUSK_BROWSER_HOST}:${DUSK_PORT}"
export ASSESTME_FIC_DUSK_FAKE="${ASSESTME_FIC_DUSK_FAKE:-1}"

if [[ -n "${DUSK_DRIVER_URL:-}" ]]; then
    driver_ready=0
    for attempt in $(seq 1 "${DUSK_DRIVER_READY_ATTEMPTS:-60}"); do
        if curl --fail --silent "${DUSK_DRIVER_URL%/}/status" | grep -Eq '"ready"[[:space:]]*:[[:space:]]*true'; then
            driver_ready=1
            break
        fi
        sleep 1
    done

    if [[ "$driver_ready" != "1" ]]; then
        printf 'ERROR: The configured remote Dusk driver did not become ready: %s\n' "$DUSK_DRIVER_URL" >&2
        exit 1
    fi
fi

(
    cd public
    exec php -d variables_order=EGPCS -S "${DUSK_SERVER_BIND}:${DUSK_PORT}" \
        ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
) >"$ASSESTME_TEST_ROOT/storage/logs/dusk-server.log" 2>&1 &
server_pid=$!

for attempt in {1..40}; do
    if curl --fail --silent "http://${DUSK_READY_HOST}:${DUSK_PORT}/admin/login" >/dev/null; then
        php artisan dusk --without-tty "$@"
        exit 0
    fi
    sleep 0.25
done

cat "$ASSESTME_TEST_ROOT/storage/logs/dusk-server.log" >&2
printf 'ERROR: The isolated Dusk server did not become ready.\n' >&2
exit 1
