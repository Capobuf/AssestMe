#!/usr/bin/env bash
set -Eeuo pipefail

fail() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

info() {
    printf 'INFO: %s\n' "$*"
}

project_dir="${1:-$PWD}"
cd "$project_dir"

[[ -f artisan && -f composer.json ]] || fail "Run this script from an AssestMe Laravel project root."
[[ -f .env.example ]] || fail ".env.example is missing."

"$(dirname "$0")/preflight.sh" "$project_dir"

if [[ ! -f .env ]]; then
    cp .env.example .env
    info "Created .env from .env.example."
fi

mkdir -p database storage/app/private storage/app/generated storage/app/qa-artifacts
[[ -f database/database.sqlite ]] || install -m 660 /dev/null database/database.sqlite

absolute_database="$(realpath database/database.sqlite)"
absolute_root="$(realpath .)"
php -r '
$databasePath = $argv[1];
$rootPath = $argv[2];
$file = $argv[3];
$contents = file_get_contents($file);
if ($contents === false) { fwrite(STDERR, "Unable to read .env\n"); exit(1); }
$replacements = [
    "DB_DATABASE" => $databasePath,
    "LARAVEL_PDF_DOMPDF_CHROOT" => $rootPath,
];
foreach ($replacements as $key => $value) {
    $pattern = "/^".preg_quote($key, "/")."=.*$/m";
    if (preg_match($pattern, $contents)) {
        $contents = preg_replace($pattern, $key."=".$value, $contents, 1);
    } else {
        $contents .= "\n".$key."=".$value."\n";
    }
}
file_put_contents($file, $contents);
' "$absolute_database" "$absolute_root" .env

composer install --no-interaction --prefer-dist

if ! grep -Eq '^APP_KEY=base64:.+' .env; then
    php artisan key:generate --force
fi

php artisan migrate --force
php artisan filament:assets
php artisan storage:unlink >/dev/null 2>&1 || true

if [[ -z "${DEV_ADMIN_PASSWORD:-}" ]]; then
    DEV_ADMIN_PASSWORD="$(php -r 'echo bin2hex(random_bytes(12))."!Aa1";')"
fi

export DEV_ADMIN_NAME="${DEV_ADMIN_NAME:-Administrator}"
export DEV_ADMIN_EMAIL="${DEV_ADMIN_EMAIL:-admin@assestme.local}"
export DEV_ADMIN_PASSWORD

if php artisan list --raw | grep -q '^assestme:create-admin'; then
    php artisan assestme:create-admin \
        --name="$DEV_ADMIN_NAME" \
        --email="$DEV_ADMIN_EMAIL" \
        --password="$DEV_ADMIN_PASSWORD" \
        --no-interaction
else
    fail "The assestme:create-admin command is not implemented yet."
fi

php artisan optimize:clear

cat <<EOF

AssestMe local environment is ready.
URL: http://127.0.0.1:8000/admin
Email: ${DEV_ADMIN_EMAIL}
Password: ${DEV_ADMIN_PASSWORD}
Start: php artisan serve --host=127.0.0.1 --port=8000
EOF
