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

environment_created=0
if [[ ! -f .env ]]; then
    cp .env.example .env
    environment_created=1
    info "Created .env from .env.example."
fi

mkdir -p \
    backups \
    bootstrap/cache \
    database \
    storage/app/generated \
    storage/app/private \
    storage/app/qa-artifacts \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs
[[ -f database/database.sqlite ]] || install -m 660 /dev/null database/database.sqlite

composer_lock_hash="$(sha256sum composer.lock | cut -d ' ' -f 1)"
composer_marker="vendor/.assestme-composer-lock.sha256"
installed_lock_hash=""
if [[ -f "$composer_marker" ]]; then
    installed_lock_hash="$(tr -d '\r\n' < "$composer_marker")"
fi

if [[ ! -f vendor/autoload.php || "$installed_lock_hash" != "$composer_lock_hash" ]]; then
    composer install --no-interaction --prefer-dist
    printf '%s\n' "$composer_lock_hash" > "$composer_marker"
else
    info "Composer dependencies already match composer.lock."
fi

if ! grep -Eq '^APP_KEY=base64:.+' .env; then
    php artisan key:generate --force
fi

php artisan optimize:clear
php artisan migrate --force
php artisan db:seed --force
php artisan filament:assets

administrator_created=0
if ! php -r '
require "vendor/autoload.php";
$application = require "bootstrap/app.php";
$application->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
exit(App\Models\User::query()->exists() ? 0 : 1);
'; then
    if php artisan list --raw | grep -q '^assestme:create-admin'; then
        php artisan assestme:create-admin \
            --from-env \
            --no-interaction
        administrator_created=1
    else
        fail "The assestme:create-admin command is not implemented yet."
    fi
fi

php artisan assestme:diagnose

info "AssestMe application bootstrap completed."
if [[ "$administrator_created" == "0" && "$environment_created" == "1" ]]; then
    info "The existing singleton administrator was preserved."
fi
