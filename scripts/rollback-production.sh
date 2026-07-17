#!/usr/bin/env bash
set -Eeuo pipefail

fail() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

info() {
    printf 'INFO: %s\n' "$*"
}

usage() {
    cat <<'EOF'
Usage: rollback-production.sh --release=<release-id-or-path> --backup=<absolute-archive.tar.gz> [--force]

The selected backup replaces the shared database and private storage. Writes made
after that backup are discarded. Interactive runs require typing ROLLBACK; automated
runs must pass --force explicitly.
EOF
}

release_input=""
backup_path=""
force=0

for argument in "$@"; do
    case "$argument" in
        --release=*) release_input="${argument#*=}" ;;
        --backup=*) backup_path="${argument#*=}" ;;
        --force) force=1 ;;
        --help|-h)
            usage
            exit 0
            ;;
        *) fail "Unknown argument: $argument" ;;
    esac
done

release_root="${ASSESTME_RELEASE_ROOT:-}"
backup_root="${ASSESTME_BACKUP_ROOT:-}"
current_link="${release_root}/current"
releases_dir="${release_root}/releases"
hostname="${ASSESTME_HOSTNAME:-}"
fpm_service="${ASSESTME_FPM_SERVICE:-}"

[[ -n "$release_root" ]] || fail "ASSESTME_RELEASE_ROOT is required."
[[ "$release_root" == /* ]] || fail "ASSESTME_RELEASE_ROOT must be an absolute path."
[[ -n "$backup_root" ]] || fail "ASSESTME_BACKUP_ROOT is required."
[[ "$backup_root" == /* ]] || fail "ASSESTME_BACKUP_ROOT must be an absolute path."
[[ -n "$fpm_service" ]] || fail "ASSESTME_FPM_SERVICE is required."
[[ "$fpm_service" =~ ^[A-Za-z0-9_.@-]+$ ]] || fail "ASSESTME_FPM_SERVICE is invalid."
[[ -n "$release_input" ]] || fail "--release is required."
[[ "$backup_path" == /* ]] || fail "--backup must be an absolute path."
[[ -f "$backup_path" ]] || fail "Backup archive does not exist: $backup_path"
[[ -L "$current_link" ]] || fail "Current release link is missing: $current_link"
[[ -d "$releases_dir" ]] || fail "Release directory is missing: $releases_dir"
[[ "$hostname" =~ ^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$ && "$hostname" == *.* ]] || \
    fail "ASSESTME_HOSTNAME must be a lowercase fully qualified domain name."

for command_name in curl php realpath systemctl; do
    command -v "$command_name" >/dev/null 2>&1 || fail "Required command is missing: $command_name"
done

releases_dir="$(realpath "$releases_dir")"
if [[ "$release_input" == /* ]]; then
    target_candidate="$release_input"
else
    target_candidate="${releases_dir}/${release_input}"
fi

target_release="$(realpath "$target_candidate")"
current_target="$(readlink -f "$current_link")"
[[ "$target_release" == "$releases_dir/"* ]] || fail "Target release escapes the release directory."
[[ -f "$target_release/artisan" && -f "$target_release/composer.lock" ]] || \
    fail "Target release is incomplete: $target_release"
[[ -f "$current_target/artisan" ]] || fail "Current release is incomplete: $current_target"
[[ "$target_release" != "$current_target" ]] || fail "The target release is already current."

php "$current_target/artisan" assestme:backup:verify "$backup_path" --no-interaction

printf 'WARNING: rollback to %s will restore %s.\n' "$target_release" "$backup_path" >&2
printf 'WARNING: every database and private-storage write newer than that backup will be discarded.\n' >&2

if [[ "$force" != "1" ]]; then
    [[ -t 0 ]] || fail "Non-interactive rollback requires --force."
    read -r -p 'Type ROLLBACK to continue: ' confirmation
    [[ "$confirmation" == "ROLLBACK" ]] || fail "Rollback cancelled."
fi

mkdir -p "$backup_root"
pre_rollback_backup="${backup_root}/assestme-pre-rollback-$(date -u +%Y%m%d-%H%M%S).tar.gz"
switched=0
maintenance_enabled=0
finished=0

recover_failed_rollback() {
    local exit_code=$?
    if [[ $exit_code -ne 0 && "$finished" != "1" ]]; then
        printf 'ERROR: Rollback failed; attempting to restore the pre-rollback state.\n' >&2

        if [[ "$maintenance_enabled" != "1" ]]; then
            if php "$current_target/artisan" down --retry=60; then
                maintenance_enabled=1
            else
                printf 'ERROR: Maintenance mode could not be enabled during rollback recovery.\n' >&2
            fi
        fi

        if [[ "$switched" == "1" ]]; then
            ln -sfn "$current_target" "${current_link}.recovery"
            mv -Tf "${current_link}.recovery" "$current_link"
        fi

        if [[ -f "$pre_rollback_backup" && "$maintenance_enabled" == "1" ]]; then
            if ! php "$current_target/artisan" assestme:restore-backup "$pre_rollback_backup" --no-interaction; then
                printf 'ERROR: Pre-rollback database/storage restoration also failed.\n' >&2
            fi
        fi

        if ! systemctl reload "$fpm_service"; then
            printf 'ERROR: PHP-FPM reload failed during rollback recovery.\n' >&2
        fi
        if [[ "$maintenance_enabled" == "1" ]] && ! php "$current_target/artisan" up; then
            printf 'ERROR: The original release could not leave maintenance mode.\n' >&2
        fi
    fi

    exit "$exit_code"
}
trap recover_failed_rollback EXIT

php "$current_target/artisan" down --retry=60
maintenance_enabled=1
php "$current_target/artisan" assestme:backup --output="$pre_rollback_backup" --no-interaction
php "$current_target/artisan" assestme:restore-backup "$backup_path" --no-interaction

ln -sfn "$target_release" "${current_link}.rollback"
mv -Tf "${current_link}.rollback" "$current_link"
switched=1

systemctl reload "$fpm_service"
php "$target_release/artisan" assestme:diagnose
php "$target_release/artisan" up
maintenance_enabled=0
curl --fail --silent --show-error --retry 5 --retry-delay 2 "https://${hostname}/admin" >/dev/null

finished=1
trap - EXIT
info "Rollback completed: ${target_release}"
info "Pre-rollback safety backup: ${pre_rollback_backup}"
