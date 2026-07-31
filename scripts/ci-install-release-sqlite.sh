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

cleanup() {
    if [[ -n "$server_pid" ]]; then
        kill "$server_pid" >/dev/null 2>&1 || true
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
    APP_ENV=testing APP_URL="$base_url" "$php_binary" artisan serve --host=127.0.0.1 --port="$port"
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
post /install/configuration \
    --data-urlencode 'application_name=AssestMe' \
    --data-urlencode "application_url=$configured_url" \
    --data-urlencode 'timezone=Europe/Rome' \
    --data-urlencode 'locale=it' \
    --data-urlencode "backup_root=$project_path/storage/backups" \
    --data-urlencode "weasyprint_binary=$weasyprint_binary" \
    --data-urlencode "php_binary=$php_binary" \
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

(
    cd "$project_path"
    "$php_binary" artisan schedule:run >/dev/null
    "$php_binary" artisan assestme:diagnose --json >/dev/null
    test "$("$php_binary" artisan tinker --execute='echo App\Models\User::query()->count();')" = '1'
)

echo "Extracted release SQLite installation passed."
