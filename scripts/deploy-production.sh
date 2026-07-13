#!/usr/bin/env bash
set -Eeuo pipefail

fail() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

info() {
    printf 'INFO: %s\n' "$*"
}

source_dir="${1:-$PWD}"
release_root="${ASSESTME_RELEASE_ROOT:-/var/www/assestme}"
release_id="$(date -u +%Y%m%dT%H%M%SZ)"
release_dir="${release_root}/releases/${release_id}"
shared_dir="${release_root}/shared"
current_link="${release_root}/current"
previous_target=""
backup_path=""
backup_root="${ASSESTME_BACKUP_ROOT:-/var/backups/assestme}"
switched=0

[[ -f "$source_dir/artisan" && -f "$source_dir/composer.lock" ]] || \
    fail "The source directory must contain artisan and composer.lock."
[[ -n "${ASSESTME_HOSTNAME:-}" ]] || fail "ASSESTME_HOSTNAME is required."
[[ -f "$shared_dir/.env" ]] || fail "Missing production environment file: $shared_dir/.env"
[[ -f "$shared_dir/database/database.sqlite" ]] || fail "Missing shared SQLite database."
[[ -d "$shared_dir/storage" ]] || fail "Missing shared storage directory."

if [[ -L "$current_link" ]]; then
    previous_target="$(readlink -f "$current_link")"
fi

cleanup_failed_release() {
    local exit_code=$?
    if [[ $exit_code -ne 0 ]]; then
        printf 'ERROR: Deployment failed.\n' >&2
        if [[ -n "$backup_path" && -d "$release_dir" && -f "$release_dir/artisan" ]]; then
            php "$release_dir/artisan" assestme:restore-backup "$backup_path" --no-interaction || true
        fi
        if [[ "$switched" == "1" ]]; then
            if [[ -n "$previous_target" ]]; then
                ln -sfn "$previous_target" "${current_link}.rollback"
                mv -Tf "${current_link}.rollback" "$current_link"
            else
                rm -f "$current_link"
            fi
            systemctl reload php8.3-fpm || true
        fi
        if [[ -f "$release_dir/artisan" ]]; then
            php "$release_dir/artisan" up >/dev/null 2>&1 || true
        elif [[ -n "$previous_target" && -f "$previous_target/artisan" ]]; then
            php "$previous_target/artisan" up >/dev/null 2>&1 || true
        fi
        rm -rf "$release_dir"
    fi
    exit "$exit_code"
}
trap cleanup_failed_release EXIT

mkdir -p "$release_root/releases" "$release_dir"
rsync -a --delete \
    --exclude='.git' \
    --exclude='.env' \
    --exclude='database/database.sqlite*' \
    --exclude='storage' \
    "$source_dir/" "$release_dir/"

cd "$release_dir"
ln -sfn "$shared_dir/.env" .env
rm -f database/database.sqlite
rm -rf storage
ln -s "$shared_dir/database/database.sqlite" database/database.sqlite
ln -s "$shared_dir/storage" storage

composer install --no-dev --no-interaction --prefer-dist --classmap-authoritative
php artisan optimize:clear
php artisan down --retry=60

mkdir -p "$backup_root"
backup_path="${backup_root}/assestme-$(date -u +%Y%m%d-%H%M%S).tar.gz"
php artisan assestme:backup --output="$backup_path" --no-interaction
[[ -f "$backup_path" ]] || fail "The pre-deployment backup archive was not created: $backup_path"
php artisan migrate --force --isolated
php artisan filament:assets
php artisan optimize
php artisan assestme:diagnose

ln -sfn "$release_dir" "${current_link}.next"
mv -Tf "${current_link}.next" "$current_link"
switched=1

systemctl reload php8.3-fpm
php artisan up
curl --fail --silent --show-error --retry 5 --retry-delay 2 "https://${ASSESTME_HOSTNAME}/admin" >/dev/null

trap - EXIT
info "Deployment completed: ${release_dir}"
if [[ -n "$previous_target" ]]; then
    info "Previous release: ${previous_target}"
fi
