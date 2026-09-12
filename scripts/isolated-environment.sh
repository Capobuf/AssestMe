#!/usr/bin/env bash

assestme_begin_isolated_environment() {
    local prefix="${1:-verify}"
    local database_driver="${ASSESTME_TEST_DB_DRIVER:-sqlite}"

    if [[ "$database_driver" != "sqlite" && "$database_driver" != "mysql" && "$database_driver" != "mariadb" ]]; then
        printf 'ERROR: ASSESTME_TEST_DB_DRIVER must be sqlite, mysql, or mariadb.\n' >&2
        return 1
    fi

    ASSESTME_ISOLATED_ENV_OWNS_ROOT=0
    if [[ -z "${ASSESTME_TEST_ROOT:-}" ]]; then
        ASSESTME_TEST_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/assestme-${prefix}.XXXXXX")"
        ASSESTME_ISOLATED_ENV_OWNS_ROOT=1
    elif [[ "${ASSESTME_TEST_ISOLATED:-0}" != "1" || ! -f "$ASSESTME_TEST_ROOT/.assestme-test-root" ]]; then
        printf 'ERROR: A reused ASSESTME_TEST_ROOT must already be marked as an isolated AssestMe test root.\n' >&2
        return 1
    fi

    [[ "$ASSESTME_TEST_ROOT" == /* ]] || {
        printf 'ERROR: ASSESTME_TEST_ROOT must be absolute.\n' >&2
        return 1
    }
    [[ "$ASSESTME_TEST_ROOT" != "$PWD" \
        && "$ASSESTME_TEST_ROOT" != "$PWD/"* \
        && "$PWD" != "$ASSESTME_TEST_ROOT/"* ]] || {
        printf 'ERROR: ASSESTME_TEST_ROOT must be separate from the project directory.\n' >&2
        return 1
    }

    mkdir -p \
        "$ASSESTME_TEST_ROOT/storage/app/private" \
        "$ASSESTME_TEST_ROOT/storage/framework/cache/data" \
        "$ASSESTME_TEST_ROOT/storage/framework/sessions" \
        "$ASSESTME_TEST_ROOT/storage/framework/views" \
        "$ASSESTME_TEST_ROOT/storage/logs" \
        "$ASSESTME_TEST_ROOT/storage/backups"
    touch "$ASSESTME_TEST_ROOT/.assestme-test-root"
    touch "$ASSESTME_TEST_ROOT/database.sqlite"

    export APP_ENV=testing
    export APP_DEBUG=false
    export APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=
    export APP_FALLBACK_LOCALE=it
    export APP_LOCALE=it
    export APP_TIMEZONE=Europe/Rome
    export ASSESTME_BACKUP_ROOT="$ASSESTME_TEST_ROOT/storage/backups"
    export ASSESTME_TEST_ISOLATED=1
    export ASSESTME_TEST_ROOT
    export CACHE_STORE=file
    export DB_CONNECTION="$database_driver"
    if [[ "$database_driver" == "sqlite" ]]; then
        export DB_DATABASE="$ASSESTME_TEST_ROOT/database.sqlite"
        export DB_HOST=""
        export DB_PASSWORD=""
        export DB_PORT=""
        export DB_USERNAME=""
    else
        export DB_DATABASE="${ASSESTME_TEST_DB_DATABASE:-assestme_test}"
        export DB_HOST="${ASSESTME_TEST_DB_HOST:-${database_driver}-test}"
        export DB_PASSWORD="${ASSESTME_TEST_DB_PASSWORD:-}"
        export DB_PORT="${ASSESTME_TEST_DB_PORT:-3306}"
        export DB_USERNAME="${ASSESTME_TEST_DB_USERNAME:-root}"
    fi
    export DB_FOREIGN_KEYS=true
    export DB_BUSY_TIMEOUT=5000
    export DB_JOURNAL_MODE=WAL
    export DB_SYNCHRONOUS=NORMAL
    export DB_TRANSACTION_MODE=IMMEDIATE
    export FILESYSTEM_DISK=local
    export LARAVEL_PDF_DRIVER=weasyprint
    export LARAVEL_PDF_WEASYPRINT_BINARY=/usr/bin/weasyprint
    export LARAVEL_STORAGE_PATH="$ASSESTME_TEST_ROOT/storage"
    export LOG_CHANNEL=single
    export QUEUE_CONNECTION=sync
    export SESSION_DRIVER=file
}

assestme_prepare_isolated_environment_file() {
    local project_dir="${1:-$PWD}"
    local environment_file="$project_dir/.env"

    ASSESTME_ISOLATED_ENV_FILE="$environment_file"
    ASSESTME_ISOLATED_ENV_OWNS_FILE=0

    if [[ -e "$environment_file" || -L "$environment_file" ]]; then
        if [[ ! -f "$environment_file" || ! -r "$environment_file" ]]; then
            printf 'ERROR: The existing isolated environment file must be a readable file: %s\n' "$environment_file" >&2
            return 1
        fi

        return 0
    fi

    if ! (umask 077; set -o noclobber; : > "$environment_file") 2>/dev/null; then
        printf 'ERROR: The isolated environment placeholder could not be created: %s\n' "$environment_file" >&2
        return 1
    fi

    ASSESTME_ISOLATED_ENV_OWNS_FILE=1
}

assestme_cleanup_isolated_environment_file() {
    if [[ "${ASSESTME_ISOLATED_ENV_OWNS_FILE:-0}" == "1" \
        && -n "${ASSESTME_ISOLATED_ENV_FILE:-}" \
        && -f "$ASSESTME_ISOLATED_ENV_FILE" \
        && ! -L "$ASSESTME_ISOLATED_ENV_FILE" ]]; then
        if [[ ! -s "$ASSESTME_ISOLATED_ENV_FILE" ]]; then
            rm -f -- "$ASSESTME_ISOLATED_ENV_FILE"
        else
            printf 'WARNING: The isolated environment placeholder changed during verification and was preserved: %s\n' "$ASSESTME_ISOLATED_ENV_FILE" >&2
        fi
    fi

    unset ASSESTME_ISOLATED_ENV_FILE ASSESTME_ISOLATED_ENV_OWNS_FILE
}

assestme_end_isolated_environment() {
    if [[ "${ASSESTME_KEEP_TEST_ROOT:-0}" != "1" \
        && "${ASSESTME_ISOLATED_ENV_OWNS_ROOT:-0}" == "1" \
        && -n "${ASSESTME_TEST_ROOT:-}" \
        && -f "$ASSESTME_TEST_ROOT/.assestme-test-root" ]]; then
        rm -rf -- "$ASSESTME_TEST_ROOT"
    fi
}
