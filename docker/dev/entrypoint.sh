#!/usr/bin/env bash
set -Eeuo pipefail

fail() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

[[ -f /workspace/artisan && -f /workspace/composer.json && -f /workspace/composer.lock ]] || \
    fail "The AssestMe repository is not mounted at /workspace."

if [[ "$(id -u)" == "0" ]]; then
    repository_uid="$(stat -c '%u' /workspace)"
    repository_gid="$(stat -c '%g' /workspace)"
    runtime_uid="${ASSESTME_UID:-$repository_uid}"
    runtime_gid="${ASSESTME_GID:-$repository_gid}"

    [[ "$runtime_uid" =~ ^[0-9]+$ ]] || fail "ASSESTME_UID must be numeric when provided."
    [[ "$runtime_gid" =~ ^[0-9]+$ ]] || fail "ASSESTME_GID must be numeric when provided."

    if [[ "$runtime_uid" != "0" || "$runtime_gid" != "0" ]]; then
        exec setpriv --reuid="$runtime_uid" --regid="$runtime_gid" --clear-groups "$0" "$@"
    fi
fi

umask 0002
runtime_root="${TMPDIR:-/tmp}/assestme-runtime-$(id -u)"
mkdir -p "$runtime_root/composer-home" "$runtime_root/composer-cache"
chmod 0700 "$runtime_root" "$runtime_root/composer-home" "$runtime_root/composer-cache"

export COMPOSER_HOME="$runtime_root/composer-home"
export COMPOSER_CACHE_DIR="$runtime_root/composer-cache"

cd /workspace
scripts/bootstrap-local.sh /workspace

exec "$@"
