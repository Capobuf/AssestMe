#!/usr/bin/env bash
set -Eeuo pipefail

fail() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

info() {
    printf 'INFO: %s\n' "$*"
}

command -v lsb_release >/dev/null 2>&1 || fail "lsb_release is required. Install the lsb-release package."
command -v php >/dev/null 2>&1 || fail "PHP is not installed."
command -v composer >/dev/null 2>&1 || fail "Composer is not installed."
command -v git >/dev/null 2>&1 || fail "Git is not installed."
command -v sqlite3 >/dev/null 2>&1 || fail "sqlite3 is not installed."

os_id="$(. /etc/os-release && printf '%s' "$ID")"
os_version="$(. /etc/os-release && printf '%s' "$VERSION_ID")"
[[ "$os_id" == "ubuntu" && "$os_version" == "24.04" ]] || \
    fail "Ubuntu 24.04 is required; detected ${os_id} ${os_version}."

architecture="$(uname -m)"
case "$architecture" in
    x86_64|aarch64) ;;
    *) fail "Unsupported architecture: ${architecture}. Supported: x86_64, aarch64." ;;
esac

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

project_dir="${1:-$PWD}"
[[ -d "$project_dir" ]] || fail "Project directory does not exist: ${project_dir}."
[[ -w "$project_dir" ]] || fail "Project directory is not writable: ${project_dir}."

info "Operating system: Ubuntu ${os_version}"
info "Architecture: ${architecture}"
info "PHP: $(php -r 'echo PHP_VERSION;')"
info "Composer: $(composer --version --no-ansi)"
info "Git: $(git --version)"
info "SQLite: $(sqlite3 --version)"
info "All preflight checks passed."
