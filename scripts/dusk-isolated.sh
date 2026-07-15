#!/usr/bin/env bash
set -Eeuo pipefail

project_dir="${1:-$PWD}"
if [[ $# -gt 0 ]]; then
    shift
fi
cd "$project_dir"

source scripts/isolated-environment.sh
assestme_begin_isolated_environment dusk

server_pid=""
cleanup() {
    if [[ -n "$server_pid" ]]; then
        kill "$server_pid" >/dev/null 2>&1 || true
        wait "$server_pid" >/dev/null 2>&1 || true
    fi
    assestme_end_isolated_environment
}
trap cleanup EXIT INT TERM

php artisan migrate:fresh --seed --force

if [[ -z "${DUSK_PORT:-}" ]]; then
    DUSK_PORT="$(php -r '
        $socket = stream_socket_server("tcp://127.0.0.1:0", $errorCode, $errorMessage);
        if ($socket === false) { fwrite(STDERR, $errorMessage); exit(1); }
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        echo substr((string) $address, strrpos((string) $address, ":") + 1);
    ')"
fi

export APP_URL="http://127.0.0.1:${DUSK_PORT}"
(
    cd public
    exec php -d variables_order=EGPCS -S "127.0.0.1:${DUSK_PORT}" \
        ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
) >"$ASSESTME_TEST_ROOT/storage/logs/dusk-server.log" 2>&1 &
server_pid=$!

for attempt in {1..40}; do
    if curl --fail --silent "$APP_URL/admin/login" >/dev/null; then
        php artisan dusk --without-tty "$@"
        exit $?
    fi
    sleep 0.25
done

cat "$ASSESTME_TEST_ROOT/storage/logs/dusk-server.log" >&2
printf 'ERROR: The isolated Dusk server did not become ready.\n' >&2
exit 1
