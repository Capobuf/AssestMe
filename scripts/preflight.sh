#!/usr/bin/env bash
set -Eeuo pipefail

fail() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

info() {
    printf 'INFO: %s\n' "$*"
}

command -v php >/dev/null 2>&1 || fail "PHP is not installed."
command -v composer >/dev/null 2>&1 || fail "Composer is not installed."

project_dir="${1:-$PWD}"
[[ -d "$project_dir" ]] || fail "Project directory does not exist: ${project_dir}."
[[ -f "$project_dir/artisan" && -f "$project_dir/composer.json" && -f "$project_dir/composer.lock" ]] || \
    fail "Project directory must contain artisan, composer.json, and composer.lock."
[[ -w "$project_dir" ]] || fail "Project directory is not writable: ${project_dir}."

php_version="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
[[ "$php_version" == "8.3" ]] || fail "PHP 8.3.x is required; detected $(php -r 'echo PHP_VERSION;')."

composer_major="$(composer --version --no-ansi | sed -E 's/.*version ([0-9]+).*/\1/')"
[[ "$composer_major" == "2" ]] || fail "Composer 2 is required."

required_extensions=(
    bcmath ctype curl dom fileinfo filter gd iconv intl libxml mbstring openssl
    pdo pdo_sqlite session simplexml tokenizer xml xmlreader xmlwriter zip zlib
)

for extension in "${required_extensions[@]}"; do
    php -r "exit(extension_loaded('${extension}') ? 0 : 1);" || \
        fail "Missing required PHP extension: ${extension}."
done

info "PHP: $(php -r 'echo PHP_VERSION;')"
info "Composer: $(composer --version --no-ansi)"
info "All preflight checks passed."
