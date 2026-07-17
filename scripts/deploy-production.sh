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
release_root="${ASSESTME_RELEASE_ROOT:-}"
release_id="$(date -u +%Y%m%dT%H%M%SZ)"
release_dir="${release_root}/releases/${release_id}"
shared_dir="${release_root}/shared"
current_link="${release_root}/current"
previous_target=""
backup_path=""
backup_root="${ASSESTME_BACKUP_ROOT:-}"
runtime_group="${ASSESTME_RUNTIME_GROUP:-}"
fpm_service="${ASSESTME_FPM_SERVICE:-}"
switched=0
maintenance_enabled=0

[[ "$source_dir" == /* ]] || source_dir="$(realpath "$source_dir")"
[[ -n "$release_root" ]] || fail "ASSESTME_RELEASE_ROOT is required."
[[ "$release_root" == /* ]] || fail "ASSESTME_RELEASE_ROOT must be an absolute path."
[[ -n "$backup_root" ]] || fail "ASSESTME_BACKUP_ROOT is required."
[[ "$backup_root" == /* ]] || fail "ASSESTME_BACKUP_ROOT must be an absolute path."
[[ -n "$runtime_group" ]] || fail "ASSESTME_RUNTIME_GROUP is required."
[[ "$runtime_group" =~ ^[A-Za-z_][A-Za-z0-9_.-]*$ ]] || fail "ASSESTME_RUNTIME_GROUP is invalid."
[[ -n "$fpm_service" ]] || fail "ASSESTME_FPM_SERVICE is required."
[[ "$fpm_service" =~ ^[A-Za-z0-9_.@-]+$ ]] || fail "ASSESTME_FPM_SERVICE is invalid."
[[ -f "$source_dir/artisan" && -f "$source_dir/composer.lock" ]] || \
    fail "The source directory must contain artisan and composer.lock."
[[ -n "${ASSESTME_HOSTNAME:-}" ]] || fail "ASSESTME_HOSTNAME is required."
[[ "$ASSESTME_HOSTNAME" =~ ^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$ && "$ASSESTME_HOSTNAME" == *.* ]] || \
    fail "ASSESTME_HOSTNAME must be a lowercase fully qualified domain name."
[[ -f "$shared_dir/.env" ]] || fail "Missing production environment file: $shared_dir/.env"
[[ -f "$shared_dir/database/database.sqlite" ]] || fail "Missing shared SQLite database."
[[ -d "$shared_dir/storage" ]] || fail "Missing shared storage directory."
[[ ! -e "$release_dir" ]] || fail "Release already exists: $release_dir"

for command_name in composer curl git php rsync systemctl; do
    command -v "$command_name" >/dev/null 2>&1 || fail "Required command is missing: $command_name"
done

git -C "$source_dir" rev-parse --is-inside-work-tree >/dev/null 2>&1 || \
    fail "The release source must be a Git worktree."
git -C "$source_dir" ls-files --error-unmatch composer.lock >/dev/null 2>&1 || \
    fail "composer.lock must be tracked by Git."
[[ -z "$(git -C "$source_dir" status --porcelain --untracked-files=all)" ]] || \
    fail "The release source must be clean, including untracked files."
composer validate --strict --working-dir="$source_dir" >/dev/null

if [[ -L "$current_link" ]]; then
    previous_target="$(readlink -f "$current_link")"
fi

secure_permissions() {
    chgrp -R "$runtime_group" "$release_dir" "$shared_dir/.env" "$shared_dir/database" "$shared_dir/storage"
    find "$release_dir" -type d -exec chmod 0750 {} +
    find "$release_dir" -type f -exec chmod 0640 {} +
    find "$release_dir/scripts" -type f -name '*.sh' -exec chmod 0750 {} +
    find "$shared_dir/database" "$shared_dir/storage" -type d -exec chmod 0770 {} +
    find "$shared_dir/database" "$shared_dir/storage" -type f -exec chmod 0660 {} +
    chmod 0770 "$release_dir/bootstrap/cache"
    chmod 0640 "$shared_dir/.env"
    chmod 0660 "$shared_dir/database/database.sqlite"
}

cleanup_failed_release() {
    local exit_code=$?
    if [[ $exit_code -ne 0 ]]; then
        printf 'ERROR: Deployment failed.\n' >&2
        if [[ -n "$backup_path" && -d "$release_dir" && -f "$release_dir/artisan" ]]; then
            if ! php "$release_dir/artisan" assestme:restore-backup "$backup_path" --no-interaction; then
                printf 'ERROR: Automatic pre-deployment backup restoration also failed.\n' >&2
            fi
        fi
        if [[ "$switched" == "1" ]]; then
            if [[ -n "$previous_target" ]]; then
                ln -sfn "$previous_target" "${current_link}.rollback"
                mv -Tf "${current_link}.rollback" "$current_link"
            else
                rm -f "$current_link"
            fi
            if ! systemctl reload "$fpm_service"; then
                printf 'ERROR: PHP-FPM reload failed during deployment recovery.\n' >&2
            fi
        fi
        if [[ "$maintenance_enabled" == "1" ]]; then
            if [[ -f "$release_dir/artisan" ]]; then
                if ! php "$release_dir/artisan" up; then
                    printf 'ERROR: The failed release could not leave maintenance mode.\n' >&2
                fi
            elif [[ -n "$previous_target" && -f "$previous_target/artisan" ]]; then
                if ! php "$previous_target/artisan" up; then
                    printf 'ERROR: The previous release could not leave maintenance mode.\n' >&2
                fi
            fi
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

secure_permissions

cd "$release_dir"
ln -sfn "$shared_dir/.env" .env
rm -f database/database.sqlite
rm -rf storage
ln -s "$shared_dir/database/database.sqlite" database/database.sqlite
ln -s "$shared_dir/storage" storage

composer install --no-dev --no-interaction --prefer-dist --classmap-authoritative
secure_permissions
php artisan optimize:clear
php artisan down --retry=60
maintenance_enabled=1

mkdir -p "$backup_root"
backup_path="${backup_root}/assestme-$(date -u +%Y%m%d-%H%M%S).tar.gz"
php artisan assestme:backup --output="$backup_path" --no-interaction
[[ -f "$backup_path" ]] || fail "The pre-deployment backup archive was not created: $backup_path"
php artisan migrate --force --isolated
php artisan filament:assets
php artisan optimize
secure_permissions
php artisan assestme:diagnose

ln -sfn "$release_dir" "${current_link}.next"
mv -Tf "${current_link}.next" "$current_link"
switched=1

systemctl reload "$fpm_service"
php artisan up
maintenance_enabled=0
curl --fail --silent --show-error --retry 5 --retry-delay 2 "https://${ASSESTME_HOSTNAME}/admin" >/dev/null

trap - EXIT
info "Deployment completed: ${release_dir}"
if [[ -n "$previous_target" ]]; then
    info "Previous release: ${previous_target}"
fi
