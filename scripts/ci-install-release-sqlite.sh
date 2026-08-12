#!/usr/bin/env bash

set -euo pipefail

project_path="${1:-}"
port="${2:-8123}"
release_label="${3:-release}"

if [[ "$project_path" != /* || ! -f "$project_path/artisan" || ! "$port" =~ ^[0-9]+$ || ! "$release_label" =~ ^[0-9A-Za-z._-]+$ ]]; then
    echo "Usage: scripts/ci-install-release-sqlite.sh <absolute-release-path> [port] [release-label]" >&2
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
trace_root="$(mktemp -d)"
trace_prefix="$trace_root/process"
administrator_password="CI!$(php -r 'echo bin2hex(random_bytes(14));')aA1"
tracer_pid=''
supervisor_pid=''
listener_pid=''
listener_parent_pid=''
diagnostics_reported=no

process_status() {
    local pid="${1:-}"
    local process_stat

    if [[ -z "$pid" ]]; then
        echo absent

        return
    fi

    process_stat="$(ps -o stat= -p "$pid" 2>/dev/null | awk '{$1=$1; print}' || true)"

    if [[ -z "$process_stat" ]]; then
        echo dead
    elif [[ "$process_stat" == Z* ]]; then
        echo zombie
    else
        echo alive
    fi
}

port_listener_pid() {
    local detected_pid=''

    if command -v ss >/dev/null 2>&1; then
        detected_pid="$({ ss -H -ltnp "sport = :$port" 2>/dev/null || true; } \
            | sed -n 's/.*pid=\([0-9][0-9]*\).*/\1/p' \
            | head -n 1)"
    fi

    if [[ -z "$detected_pid" ]] && command -v lsof >/dev/null 2>&1; then
        detected_pid="$(lsof -nP -t -iTCP:"$port" -sTCP:LISTEN 2>/dev/null | head -n 1 || true)"
    fi

    printf '%s' "$detected_pid"
}

port_status() {
    if command -v ss >/dev/null 2>&1 && ss -H -ltn "sport = :$port" 2>/dev/null | grep -q .; then
        echo yes
    elif command -v lsof >/dev/null 2>&1 && lsof -nP -iTCP:"$port" -sTCP:LISTEN >/dev/null 2>&1; then
        echo yes
    else
        echo no
    fi
}

process_tree() {
    local parent
    local child
    local process_ids="${supervisor_pid:-}"

    if [[ -n "$listener_pid" && "$listener_pid" != "$supervisor_pid" ]]; then
        process_ids+="${process_ids:+,}$listener_pid"
    fi

    for parent in "$supervisor_pid" "$listener_pid"; do
        if [[ -z "$parent" ]]; then
            continue
        fi

        while read -r child; do
            if [[ -n "$child" && ",$process_ids," != *",$child,"* ]]; then
                process_ids+="${process_ids:+,}$child"
            fi
        done < <(pgrep -P "$parent" 2>/dev/null || true)
    done

    echo 'PID PPID STAT COMMAND ARGS'

    if [[ -n "$process_ids" ]]; then
        ps -o pid=,ppid=,stat=,comm=,args= -p "$process_ids" 2>/dev/null || true
    fi
}

trace_exit_record() {
    local role="$1"
    local pid="${2:-}"
    local trace_file="$trace_prefix.$pid"
    local exit_line=''
    local exit_code='unavailable'
    local signal='none'
    local signal_name='none'

    if [[ -n "$pid" && -f "$trace_file" ]]; then
        exit_line="$(grep -E '\+\+\+ (exited with [0-9]+|killed by SIG[A-Z0-9]+[^+]*) \+\+\+' "$trace_file" | tail -n 1 || true)"
    fi

    if [[ "$exit_line" =~ \+\+\+\ exited\ with\ ([0-9]+)\ \+\+\+ ]]; then
        exit_code="${BASH_REMATCH[1]}"
    elif [[ "$exit_line" =~ \+\+\+\ killed\ by\ (SIG[A-Z0-9]+) ]]; then
        signal_name="${BASH_REMATCH[1]}"
        signal="$(kill -l "$signal_name" 2>/dev/null || true)"

        if [[ "$signal" =~ ^[0-9]+$ ]]; then
            exit_code="$((128 + signal))"
        else
            signal=unknown
        fi
    elif [[ "$(process_status "$pid")" = alive ]]; then
        exit_code=still-running
    fi

    echo "RELEASE_${role}_EXIT_CODE=$exit_code" >&2
    echo "RELEASE_${role}_SIGNAL=$signal" >&2
    echo "RELEASE_${role}_SIGNAL_NAME=$signal_name" >&2
    echo "RELEASE_${role}_EXIT_SOURCE=strace" >&2
}

diagnose_server_snapshot() {
    local phase="$1"
    local last_finalize_marker='none'
    local observed_listener

    observed_listener="$(port_listener_pid)"
    echo "RELEASE_DIAGNOSTIC_PHASE=$phase" >&2
    echo "RELEASE_SERVER_SUPERVISOR_PID=${supervisor_pid:-unavailable}" >&2
    echo "RELEASE_SERVER_LISTENER_PID=${listener_pid:-unavailable}" >&2
    echo "RELEASE_SERVER_SUPERVISOR_STATUS=$(process_status "$supervisor_pid")" >&2
    echo "RELEASE_SERVER_LISTENER_STATUS=$(process_status "$listener_pid")" >&2
    echo "RELEASE_SERVER_PORT_${port}_LISTEN=$(port_status)" >&2
    echo "RELEASE_SERVER_PORT_${port}_OWNER_PID=${observed_listener:-none}" >&2
    echo 'RELEASE_SERVER_PROCESS_TREE_BEGIN' >&2
    process_tree >&2
    echo 'RELEASE_SERVER_PROCESS_TREE_END' >&2

    if [[ -f "$project_path/storage/logs/laravel.log" ]]; then
        last_finalize_marker="$(
            grep -o 'installer\.finalize\.[a-z_.]*' "$project_path/storage/logs/laravel.log" \
                | tail -n 1 \
                || true
        )"
    fi

    echo "RELEASE_LAST_FINALIZE_MARKER=${last_finalize_marker:-none}" >&2

    for state_path in \
        .env \
        .env.pending \
        storage/app/private/installed.lock \
        storage/framework/installer/state.enc; do
        local state_label
        state_label="$(printf '%s' "$state_path" | tr '/.' '__' | tr '[:lower:]' '[:upper:]')"

        if [[ -e "$project_path/$state_path" ]]; then
            echo "RELEASE_${state_label}=present" >&2
        else
            echo "RELEASE_${state_label}=absent" >&2
        fi
    done
}

diagnose_server_failure() {
    diagnostics_reported=yes
    diagnose_server_snapshot AFTER_FAILURE
    sleep 2
    diagnose_server_snapshot AFTER_FAILURE_DELAY
    trace_exit_record LISTENER "$listener_pid"
    trace_exit_record SUPERVISOR "$supervisor_pid"

    if [[ -f "$project_path/storage/logs/laravel.log" ]]; then
        echo 'RELEASE_LARAVEL_LOG_TAIL_BEGIN' >&2
        tail -n 120 "$project_path/storage/logs/laravel.log" >&2
        echo 'RELEASE_LARAVEL_LOG_TAIL_END' >&2
    else
        echo 'RELEASE_LARAVEL_LOG=absent' >&2
    fi

    echo 'RELEASE_SERVER_LOG_TAIL_BEGIN' >&2
    tail -n 120 "$server_log" >&2
    echo 'RELEASE_SERVER_LOG_TAIL_END' >&2
}

cleanup() {
    local script_exit_code="$?"
    local tracer_children=''
    set +e

    if [[ "$script_exit_code" -ne 0 && -n "$tracer_pid" && "$diagnostics_reported" = no ]]; then
        diagnose_server_failure
    fi

    if [[ -n "$supervisor_pid" ]]; then
        kill "$supervisor_pid" >/dev/null 2>&1 || true
    elif [[ -n "$tracer_pid" ]]; then
        tracer_children="$(pgrep -P "$tracer_pid" 2>/dev/null || true)"

        if [[ -n "$tracer_children" ]]; then
            kill $tracer_children >/dev/null 2>&1 || true
        fi
    fi

    if [[ -n "$listener_pid" \
        && "$(process_status "$listener_pid")" = alive \
        && "$(ps -o ppid= -p "$listener_pid" 2>/dev/null | awk '{$1=$1; print}')" = "$supervisor_pid" ]]; then
        kill "$listener_pid" >/dev/null 2>&1 || true
    fi

    if [[ -n "$tracer_pid" ]]; then
        kill "$tracer_pid" >/dev/null 2>&1 || true
        wait "$tracer_pid" >/dev/null 2>&1 || true
    fi

    rm -f -- "$cookie_jar" "$response_file" "$server_log"
    find "$trace_root" -depth -delete
}
trap cleanup EXIT

if [[ "$php_binary" != /* || "$weasyprint_binary" != /* ]]; then
    echo "PHP CLI and WeasyPrint must resolve to absolute paths." >&2
    exit 69
fi

for diagnostic_binary in ss strace; do
    if ! command -v "$diagnostic_binary" >/dev/null 2>&1; then
        echo "Required diagnostic executable is missing: $diagnostic_binary" >&2
        exit 69
    fi
done

echo "RELEASE_LABEL=$release_label"
echo 'RELEASE_RUNTIME_PHP_VERSION_BEGIN'
php --version
echo 'RELEASE_RUNTIME_PHP_VERSION_END'
echo 'RELEASE_RUNTIME_PHP_INI_BEGIN'
php --ini
echo 'RELEASE_RUNTIME_PHP_INI_END'
echo 'RELEASE_RUNTIME_PHP_MODULES_BEGIN'
php -m
echo 'RELEASE_RUNTIME_PHP_MODULES_END'
php -r '
echo "RELEASE_PHP_VERSION=", PHP_VERSION, PHP_EOL;
echo "RELEASE_PHP_BINARY=", PHP_BINARY, PHP_EOL;
echo "RELEASE_PDO_DRIVERS=", json_encode(PDO::getAvailableDrivers(), JSON_THROW_ON_ERROR), PHP_EOL;
$pdo = new PDO("sqlite::memory:");
echo "RELEASE_SQLITE_RUNTIME=", $pdo->query("select sqlite_version()")?->fetchColumn(), PHP_EOL;
'
php -r '
$lock = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
foreach ($lock["packages"] as $package) {
    if ($package["name"] === "laravel/framework") {
        echo "RELEASE_LARAVEL_FRAMEWORK=", $package["version"], PHP_EOL;
        exit(0);
    }
}
exit(1);
' "$project_path/composer.lock"

mkdir -p "$project_path/storage/backups" "$project_path/storage/app/database"

(
    cd "$project_path"
    exec strace -ff -e trace=process -o "$trace_prefix" env \
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
tracer_pid="$!"

for attempt in $(seq 1 30); do
    if curl --silent --output /dev/null --max-time 2 "$base_url/install"; then
        break
    fi
    sleep 1
done

listener_pid="$(port_listener_pid)"

if [[ -z "$listener_pid" ]]; then
    echo "The HTTP listener PID could not be resolved from port $port." >&2
    exit 71
fi

supervisor_pid="$(ps -o ppid= -p "$listener_pid" | awk '{$1=$1; print}')"
listener_parent_pid="$supervisor_pid"

if [[ -z "$supervisor_pid" || "$supervisor_pid" = "$tracer_pid" ]]; then
    echo 'The artisan serve supervisor PID could not be resolved from the listener process.' >&2
    exit 71
fi

supervisor_args="$(ps -o args= -p "$supervisor_pid" 2>/dev/null || true)"

if [[ "$supervisor_args" != *"artisan serve"* ]]; then
    echo "The resolved listener parent is not the artisan serve supervisor: $supervisor_args" >&2
    exit 71
fi

echo "RELEASE_SERVER_TRACER_PID=$tracer_pid"
echo "RELEASE_SERVER_SUPERVISOR_PID=$supervisor_pid"
echo "RELEASE_SERVER_LISTENER_PID=$listener_pid"
echo "RELEASE_SERVER_LISTENER_PPID=$listener_parent_pid"

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
diagnose_server_snapshot BEFORE_FINALIZE
post /install/finalize \
    --data-urlencode 'name=CI Administrator' \
    --data-urlencode 'email=admin@assestme.invalid' \
    --data-urlencode "password=$administrator_password" \
    --data-urlencode "password_confirmation=$administrator_password"

diagnose_server_snapshot AFTER_FINALIZE
trace_exit_record LISTENER "$listener_pid"
trace_exit_record SUPERVISOR "$supervisor_pid"

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
