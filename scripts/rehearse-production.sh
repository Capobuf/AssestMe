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
[[ "$project_dir" == /* ]] || project_dir="$(realpath "$project_dir")"
[[ -f "$project_dir/artisan" && -f "$project_dir/composer.lock" ]] || \
    fail "The project directory must contain artisan and composer.lock."

for command_name in composer git php rsync; do
    command -v "$command_name" >/dev/null 2>&1 || fail "Required command is missing: $command_name"
done

rehearsal_root="$(mktemp -d "${TMPDIR:-/tmp}/assestme-production-like.XXXXXX")"
release_root="$rehearsal_root/application"
backup_root="$rehearsal_root/backups"
source_dir="$rehearsal_root/source"
shared_dir="$release_root/shared"
database_path="$shared_dir/database/database.sqlite"
private_path="$shared_dir/storage/app/private"
stub_dir="$rehearsal_root/host-stubs"
stub_log="$rehearsal_root/host-stubs.log"
hostname="rehearsal.assestme.test"
fpm_service="assestme-rehearsal.service"
runtime_group="$(id -gn)"

cleanup() {
    local exit_code=$?
    rm -rf -- "$rehearsal_root"
    exit "$exit_code"
}
trap cleanup EXIT

mkdir -p \
    "$backup_root" \
    "$shared_dir/database" \
    "$private_path" \
    "$shared_dir/storage/framework/cache/data" \
    "$shared_dir/storage/framework/sessions" \
    "$shared_dir/storage/framework/views" \
    "$shared_dir/storage/logs" \
    "$stub_dir" \
    "$rehearsal_root/bootstrap-cache"
install -m 0660 /dev/null "$database_path"

git clone --local --no-hardlinks --quiet "$project_dir" "$source_dir"
git -C "$project_dir" diff --binary --no-ext-diff | git -C "$source_dir" apply --whitespace=nowarn -
git -C "$source_dir" add --all
git -C "$source_dir" \
    -c user.name='AssestMe Rehearsal' \
    -c user.email='rehearsal@assestme.test' \
    commit --allow-empty --quiet --message='Build production rehearsal source'
[[ -z "$(git -C "$source_dir" status --porcelain --untracked-files=all)" ]] || \
    fail "The temporary release source is not clean."

cat >"$shared_dir/.env" <<EOF
APP_NAME=AssestMe
APP_ENV=production
APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=
APP_DEBUG=false
APP_URL=https://${hostname}
APP_LOCALE=it
APP_FALLBACK_LOCALE=it
APP_TIMEZONE=Europe/Rome
APP_MAINTENANCE_DRIVER=file
LOG_CHANNEL=stderr
LOG_LEVEL=warning
DB_CONNECTION=sqlite
DB_DATABASE=${database_path}
DB_FOREIGN_KEYS=true
DB_BUSY_TIMEOUT=5000
DB_JOURNAL_MODE=WAL
DB_SYNCHRONOUS=NORMAL
DB_TRANSACTION_MODE=IMMEDIATE
SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
CACHE_STORE=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
LARAVEL_PDF_DRIVER=dompdf
LARAVEL_PDF_DOMPDF_REMOTE_ENABLED=false
LARAVEL_PDF_DOMPDF_CHROOT=${release_root}
ASSESTME_HOSTNAME=${hostname}
ASSESTME_RELEASE_ROOT=${release_root}
ASSESTME_BACKUP_ROOT=${backup_root}
ASSESTME_RUNTIME_GROUP=${runtime_group}
ASSESTME_FPM_SERVICE=${fpm_service}
ASSESTME_VERSION=production-rehearsal
EOF
chmod 0640 "$shared_dir/.env"

for command_name in systemctl curl; do
    cat >"$stub_dir/$command_name" <<'EOF'
#!/usr/bin/env bash
set -Eeuo pipefail
printf '%s %s\n' "$(basename "$0")" "$*" >>"$ASSESTME_REHEARSAL_STUB_LOG"
EOF
    chmod 0750 "$stub_dir/$command_name"
done

export APP_CONFIG_CACHE="$rehearsal_root/bootstrap-cache/config.php"
export APP_DEBUG=false
export APP_ENV=production
export APP_FALLBACK_LOCALE=it
export APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA='
export APP_LOCALE=it
export APP_TIMEZONE=Europe/Rome
export ASSESTME_BACKUP_ROOT="$backup_root"
export ASSESTME_FPM_SERVICE="$fpm_service"
export ASSESTME_HOSTNAME="$hostname"
export ASSESTME_RELEASE_ROOT="$release_root"
export ASSESTME_REHEARSAL_STUB_LOG="$stub_log"
export ASSESTME_RUNTIME_GROUP="$runtime_group"
export ASSESTME_VERSION=production-rehearsal
export CACHE_STORE=file
export COMPOSER_ALLOW_SUPERUSER=1
export DB_BUSY_TIMEOUT=5000
export DB_CONNECTION=sqlite
export DB_DATABASE="$database_path"
export DB_FOREIGN_KEYS=true
export DB_JOURNAL_MODE=WAL
export DB_SYNCHRONOUS=NORMAL
export DB_TRANSACTION_MODE=IMMEDIATE
export FILESYSTEM_DISK=local
export LARAVEL_PDF_DOMPDF_CHROOT="$release_root"
export LARAVEL_PDF_DOMPDF_REMOTE_ENABLED=false
export LARAVEL_PDF_DRIVER=dompdf
export LARAVEL_STORAGE_PATH="$shared_dir/storage"
export PATH="$stub_dir:$PATH"
export QUEUE_CONNECTION=sync
export SESSION_DRIVER=file
export SESSION_ENCRYPT=true
export SESSION_SECURE_COOKIE=true

php "$project_dir/artisan" migrate:fresh --seed --force --no-interaction >/dev/null
php -r '
$database = new PDO("sqlite:".$argv[1], null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database->exec("CREATE TABLE production_rehearsal_marker (value TEXT NOT NULL)");
$statement = $database->prepare("INSERT INTO production_rehearsal_marker (value) VALUES (?)");
$statement->execute(["initial"]);
' "$database_path"
printf 'initial\n' >"$private_path/production-rehearsal.txt"

"$project_dir/scripts/deploy-production.sh" "$source_dir"
first_release="$(readlink -f "$release_root/current")"
[[ "$first_release" == "$release_root/releases/"* ]] || fail "The first release was not activated."

php -r '
$database = new PDO("sqlite:".$argv[1], null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$statement = $database->prepare("UPDATE production_rehearsal_marker SET value = ?");
$statement->execute(["before-second-deploy"]);
' "$database_path"
printf 'before-second-deploy\n' >"$private_path/production-rehearsal.txt"

sleep 1
"$project_dir/scripts/deploy-production.sh" "$source_dir"
second_release="$(readlink -f "$release_root/current")"
[[ "$second_release" != "$first_release" ]] || fail "The second deployment did not create a distinct release."

rollback_backup="$(find "$backup_root" -maxdepth 1 -type f -name 'assestme-20*.tar.gz' -printf '%p\n' | LC_ALL=C sort | tail -n 1)"
[[ -n "$rollback_backup" && -f "$rollback_backup" ]] || fail "The second pre-deployment backup is missing."
php "$second_release/artisan" assestme:backup:verify "$rollback_backup" --no-interaction >/dev/null

if php "$second_release/artisan" assestme:backup:verify "$rehearsal_root/missing.tar.gz" --no-interaction >/dev/null 2>&1; then
    fail "Backup verification unexpectedly accepted a missing archive."
fi
if php "$second_release/artisan" assestme:restore-backup "$rollback_backup" --no-interaction >/dev/null 2>&1; then
    fail "Backup restore unexpectedly ran outside maintenance mode."
fi

php -r '
$database = new PDO("sqlite:".$argv[1], null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$statement = $database->prepare("UPDATE production_rehearsal_marker SET value = ?");
$statement->execute(["after-second-deploy"]);
' "$database_path"
printf 'after-second-deploy\n' >"$private_path/production-rehearsal.txt"

"$second_release/scripts/rollback-production.sh" \
    --release="$first_release" \
    --backup="$rollback_backup" \
    --force

current_release="$(readlink -f "$release_root/current")"
[[ "$current_release" == "$first_release" ]] || fail "Rollback did not reactivate the first release."

database_marker="$(php -r '
$database = new PDO("sqlite:".$argv[1], null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
echo $database->query("SELECT value FROM production_rehearsal_marker")->fetchColumn();
' "$database_path")"
storage_marker="$(tr -d '\r\n' <"$private_path/production-rehearsal.txt")"
[[ "$database_marker" == 'before-second-deploy' ]] || fail "Rollback restored the wrong database state."
[[ "$storage_marker" == 'before-second-deploy' ]] || fail "Rollback restored the wrong private-storage state."

php "$current_release/artisan" assestme:diagnose >/dev/null
grep -Fq "systemctl reload $fpm_service" "$stub_log" || fail "The service reload handoff was not invoked."
grep -Fq "curl --fail --silent --show-error --retry 5 --retry-delay 2 https://$hostname/admin" "$stub_log" || \
    fail "The HTTPS reachability handoff was not invoked."

backup_count="$(find "$backup_root" -maxdepth 1 -type f -name '*.tar.gz' | wc -l | tr -d ' ')"
safety_count="$(find "$backup_root" -maxdepth 1 -type f -name 'assestme-safety-*.tar.gz' | wc -l | tr -d ' ')"
pre_rollback_count="$(find "$backup_root" -maxdepth 1 -type f -name 'assestme-pre-rollback-*.tar.gz' | wc -l | tr -d ' ')"
[[ "$backup_count" -ge 3 ]] || fail "The rehearsal did not retain the rollback, pre-rollback, and safety archives."
[[ "$safety_count" -ge 1 ]] || fail "Restore did not create its safety backup."
[[ "$pre_rollback_count" -ge 1 ]] || fail "Rollback did not create its pre-rollback backup."

info "Production-like deployment and rollback rehearsal passed."
info "Validated two immutable releases, two pre-deployment backups, backup verification, maintenance-only restore, a restore safety backup, a pre-rollback backup, diagnostics, and database/private-storage rollback state."
info "Failure paths rejected a missing archive and restore outside maintenance mode."
info "Host service reload and public HTTPS reachability were simulated and are not certified by this rehearsal."
