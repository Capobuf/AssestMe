#!/usr/bin/env bash

set -euo pipefail

project_path="${1:-}"
port="${2:-8123}"

if [[ "$project_path" != /* || ! -f "$project_path/artisan" || ! "$port" =~ ^[0-9]+$ ]]; then
    echo "Usage: scripts/ci-install-release-sqlite.sh <absolute-release-path> [port]" >&2
    exit 64
fi

if [[ -e "$project_path/.env" || ! -f "$project_path/vendor/autoload.php" ]]; then
    echo "The extracted release is not in a clean installable state." >&2
    exit 65
fi

php_binary="$(php -r 'echo realpath(PHP_BINARY);')"
weasyprint_binary="$(command -v weasyprint || true)"
base_url="http://127.0.0.1:${port}"
configured_url="https://127.0.0.1:${port}"
cookie_jar="$(mktemp)"
response_file="$(mktemp)"
server_log="$(mktemp)"
administrator_password="CI!$(php -r 'echo bin2hex(random_bytes(14));')aA1"
server_pid=''

diagnose_server_failure() {
    echo "RELEASE_HTTP_SERVER_PID=$server_pid" >&2

    if kill -0 "$server_pid" >/dev/null 2>&1; then
        echo "RELEASE_HTTP_SERVER_PID_STATUS=alive" >&2
    else
        echo "RELEASE_HTTP_SERVER_PID_STATUS=dead" >&2
        set +e
        wait "$server_pid"
        local server_exit_code="$?"
        set -e
        echo "RELEASE_HTTP_SERVER_EXIT_CODE=$server_exit_code" >&2

        if [[ "$server_exit_code" -gt 128 ]]; then
            local server_signal="$((server_exit_code - 128))"
            local server_signal_name
            server_signal_name="$(kill -l "$server_signal" 2>/dev/null || true)"
            echo "RELEASE_HTTP_SERVER_SIGNAL=$server_signal" >&2
            echo "RELEASE_HTTP_SERVER_SIGNAL_NAME=${server_signal_name:-unknown}" >&2
        fi
    fi

    if (exec 3<>"/dev/tcp/127.0.0.1/$port") 2>/dev/null; then
        echo "RELEASE_HTTP_SERVER_PORT_LISTEN=yes" >&2
    else
        echo "RELEASE_HTTP_SERVER_PORT_LISTEN=no" >&2
    fi

    for state_path in \
        .env \
        .env.pending \
        storage/app/private/installed.lock \
        storage/framework/installer/state.enc; do
        local state_label
        state_label="$(printf '%s' "$state_path" | tr '/.' '__' | tr '[:lower:]' '[:upper:]')"

        if [[ -e "$project_path/$state_path" ]]; then
            echo "RELEASE_HTTP_${state_label}=present" >&2
        else
            echo "RELEASE_HTTP_${state_label}=absent" >&2
        fi
    done

    if [[ -f "$project_path/storage/logs/laravel.log" ]]; then
        local last_finalize_marker
        last_finalize_marker="$(
            grep -o 'installer\.finalize\.[a-z_.]*' "$project_path/storage/logs/laravel.log" \
                | tail -n 1 \
                || true
        )"
        echo "RELEASE_HTTP_LAST_FINALIZE_MARKER=${last_finalize_marker:-none}" >&2
        tail -n 120 "$project_path/storage/logs/laravel.log" >&2
    else
        echo "RELEASE_HTTP_LAST_FINALIZE_MARKER=none" >&2
    fi

    tail -n 120 "$server_log" >&2
}

cleanup() {
    local script_exit_code="$?"
    local server_children=''
    set +e

    if [[ "$script_exit_code" -ne 0 && -n "$server_pid" ]]; then
        diagnose_server_failure
    fi

    if [[ -n "$server_pid" ]]; then
        server_children="$(pgrep -P "$server_pid" 2>/dev/null || true)"
        kill "$server_pid" >/dev/null 2>&1 || true

        if [[ -n "$server_children" ]]; then
            kill $server_children >/dev/null 2>&1 || true
        fi

        wait "$server_pid" >/dev/null 2>&1 || true
    fi
    rm -f -- "$cookie_jar" "$response_file" "$server_log"
}
trap cleanup EXIT

if [[ "$php_binary" != /* || "$weasyprint_binary" != /* ]]; then
    echo "PHP CLI and WeasyPrint must resolve to absolute paths." >&2
    exit 69
fi

mkdir -p "$project_path/storage/backups" "$project_path/storage/app/database"

(
    cd "$project_path"
    exec env \
        -u DB_CONNECTION \
        -u DB_DATABASE \
        -u DB_HOST \
        -u DB_PORT \
        -u DB_USERNAME \
        -u DB_PASSWORD \
        -u DB_SOCKET \
        -u ASSESTME_BACKUP_ROOT \
        APP_ENV=testing \
        APP_URL="$base_url" \
        "$php_binary" artisan serve --no-reload --host=127.0.0.1 --port="$port"
) >"$server_log" 2>&1 &
server_pid="$!"

for attempt in $(seq 1 30); do
    if curl --silent --output /dev/null --max-time 2 "$base_url/install"; then
        break
    fi
    sleep 1
done

curl --fail --silent --show-error --cookie-jar "$cookie_jar" "$base_url/install" > "$response_file"
csrf_token="$(sed -n 's/.*name="_token" value="\([^"]*\)".*/\1/p' "$response_file" | head -n 1)"
if [[ -z "$csrf_token" ]]; then
    echo "The installer CSRF token was not rendered." >&2
    exit 70
fi

post() {
    local path="$1"
    shift
    curl --fail --silent --show-error \
        --location \
        --cookie "$cookie_jar" \
        --cookie-jar "$cookie_jar" \
        --output "$response_file" \
        --data-urlencode "_token=$csrf_token" \
        "$@" \
        "$base_url$path"
}

post /install/welcome
grep -q 'Requisiti runtime' "$response_file" || { echo 'Welcome step failed.' >&2; exit 70; }
post /install/requirements
grep -q 'Impostazioni di produzione' "$response_file" || { echo 'Runtime step failed.' >&2; exit 70; }
for removed_field in application_name weasyprint_binary php_binary dump_binary restore_binary; do
    if grep -q "name=\"$removed_field\"" "$response_file"; then
        echo "Removed installer field is still present: $removed_field" >&2
        exit 70
    fi
done
post /install/configuration \
    --data-urlencode 'application_name=Ignored value' \
    --data-urlencode "application_url=$configured_url" \
    --data-urlencode 'timezone=Europe/Rome' \
    --data-urlencode 'locale=it' \
    --data-urlencode "backup_root=$project_path/storage/backups" \
    --data-urlencode 'database_driver=sqlite'
grep -q 'Configura SQLite' "$response_file" || { echo 'Application configuration step failed.' >&2; exit 70; }
post /install/database \
    --data-urlencode 'database_driver=sqlite' \
    --data-urlencode "sqlite_path=$project_path/storage/app/database/database.sqlite"
grep -q 'Ultimo passaggio' "$response_file" || {
    echo 'SQLite database step failed.' >&2
    sed -n '/installer-alert-error/,/<\/div>/p' "$response_file" >&2
    exit 70
}
post /install/finalize \
    --data-urlencode 'name=CI Administrator' \
    --data-urlencode 'email=admin@assestme.invalid' \
    --data-urlencode "password=$administrator_password" \
    --data-urlencode "password_confirmation=$administrator_password"

if ! grep -q 'AssestMe è pronto' "$response_file"; then
    echo "The installer did not reach its final page." >&2
    sed -n '/installer-alert-error/,/<\/div>/p' "$response_file" >&2
    tail -n 80 "$server_log" >&2
    exit 70
fi

install_status="$(curl --silent --output /dev/null --write-out '%{http_code}' "$base_url/install")"
login_status="$(curl --silent --output /dev/null --write-out '%{http_code}' "$base_url/admin/login")"
if [[ "$install_status" != '404' || "$login_status" != '200' ]]; then
    echo "The installer lock or administrator login endpoint is invalid." >&2
    exit 70
fi

grep -q '^APP_NAME="AssestMe"$' "$project_path/.env" || {
    echo 'The installer did not preserve the fixed application name.' >&2
    exit 70
}

(
    cd "$project_path"
    env \
        -u APP_ENV \
        -u APP_KEY \
        -u APP_URL \
        -u DB_CONNECTION \
        -u DB_DATABASE \
        -u DB_HOST \
        -u DB_PORT \
        -u DB_USERNAME \
        -u DB_PASSWORD \
        -u DB_SOCKET \
        -u ASSESTME_BACKUP_ROOT \
        "$php_binary" artisan schedule:run >/dev/null
    env \
        -u APP_ENV \
        -u APP_KEY \
        -u APP_URL \
        -u DB_CONNECTION \
        -u DB_DATABASE \
        -u DB_HOST \
        -u DB_PORT \
        -u DB_USERNAME \
        -u DB_PASSWORD \
        -u DB_SOCKET \
        -u ASSESTME_BACKUP_ROOT \
        "$php_binary" artisan assestme:diagnose --json >/dev/null
    test "$(
        env \
            -u APP_ENV \
            -u APP_KEY \
            -u APP_URL \
            -u DB_CONNECTION \
            -u DB_DATABASE \
            -u DB_HOST \
            -u DB_PORT \
            -u DB_USERNAME \
            -u DB_PASSWORD \
            -u DB_SOCKET \
            -u ASSESTME_BACKUP_ROOT \
            "$php_binary" artisan tinker --execute='echo App\Models\User::query()->count();'
    )" = '1'
)

echo "Extracted release SQLite installation passed."
