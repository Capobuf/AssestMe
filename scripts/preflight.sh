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
project_dir="${1:-$PWD}"
database_driver="${2:-${DB_CONNECTION:-sqlite}}"
[[ -d "$project_dir" ]] || fail "Project directory does not exist: ${project_dir}."
[[ -f "$project_dir/artisan" && -f "$project_dir/composer.json" && -f "$project_dir/composer.lock" ]] || \
    fail "Project directory must contain artisan, composer.json, and composer.lock."
[[ -w "$project_dir" ]] || fail "Project directory is not writable: ${project_dir}."
[[ "$database_driver" == "sqlite" || "$database_driver" == "mysql" || "$database_driver" == "mariadb" ]] || \
    fail "Database driver must be sqlite, mysql, or mariadb."

php -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' || \
    fail "PHP >= 8.3.0 is required; detected $(php -r 'echo PHP_VERSION;')."

required_extensions=(
    bcmath ctype curl dom fileinfo filter gd iconv intl libxml mbstring openssl
    pdo phar session simplexml tokenizer xml xmlreader xmlwriter zip zlib
)

for extension in "${required_extensions[@]}"; do
    php -r "exit(extension_loaded('${extension}') ? 0 : 1);" || \
        fail "Missing required PHP extension: ${extension}."
done

if [[ "$database_driver" == "sqlite" ]]; then
    php -r "exit(extension_loaded('pdo_sqlite') ? 0 : 1);" || fail "Missing required PHP extension: pdo_sqlite."
else
    php -r "exit(extension_loaded('pdo_mysql') ? 0 : 1);" || fail "Missing required PHP extension: pdo_mysql."
fi

if [[ ! -f "$project_dir/vendor/autoload.php" ]]; then
    command -v composer >/dev/null 2>&1 || fail "vendor/autoload.php is missing and Composer is unavailable. Use the prebuilt release archive."
    composer_major="$(composer --version --no-ansi | sed -E 's/.*version ([0-9]+).*/\1/')"
    [[ "$composer_major" == "2" ]] || fail "Composer 2 is required to prepare a source checkout."
    info "Composer: $(composer --version --no-ansi)"
else
    info "Locked production dependencies: present"
fi

weasyprint_binary="${LARAVEL_PDF_WEASYPRINT_BINARY:-}"
if [[ -z "$weasyprint_binary" ]] && command -v weasyprint >/dev/null 2>&1; then
    weasyprint_binary="$(command -v weasyprint)"
fi
[[ "$weasyprint_binary" == /* && -f "$weasyprint_binary" && -x "$weasyprint_binary" ]] || \
    fail "An absolute executable WeasyPrint path is required."

pdf_probe="$(mktemp "${TMPDIR:-/tmp}/assestme-preflight.XXXXXXXX.pdf")"
html_probe="$(mktemp "${TMPDIR:-/tmp}/assestme-preflight.XXXXXXXX.html")"
cleanup() {
    rm -f -- "$pdf_probe" "$html_probe"
}
trap cleanup EXIT
printf '%s\n' '<!doctype html><html><body>AssestMe</body></html>' > "$html_probe"
"$weasyprint_binary" "$html_probe" "$pdf_probe" >/dev/null 2>&1 || fail "WeasyPrint could not generate a PDF."
[[ "$(head -c 5 "$pdf_probe")" == '%PDF-' ]] || fail "WeasyPrint output is not a PDF."

info "PHP: $(php -r 'echo PHP_VERSION;')"
info "Database driver prerequisites: ${database_driver}"
info "WeasyPrint: ${weasyprint_binary}"
info "All preflight checks passed."
