#!/usr/bin/env bash

set -euo pipefail

project_path="${1:-}"
domain="${2:-}"
php_binary="${3:-}"

if [[ -z "$project_path" || -z "$domain" ]]; then
    echo "Usage: scripts/cloudpanel-smoke.sh <absolute-project-path> <domain> [absolute-php-cli]" >&2
    exit 64
fi

if [[ "$project_path" != /* || ! -d "$project_path" || ! -f "$project_path/artisan" ]]; then
    echo "The project path must be absolute and contain artisan." >&2
    exit 65
fi

if [[ ! "$domain" =~ ^[A-Za-z0-9.-]+$ ]]; then
    echo "The domain is invalid." >&2
    exit 65
fi

if [[ -z "$php_binary" ]]; then
    php_binary="$(command -v php || true)"
fi

if [[ "$php_binary" != /* || ! -f "$php_binary" || ! -x "$php_binary" ]]; then
    echo "A regular executable PHP CLI path is required." >&2
    exit 69
fi

if [[ ! -f "$project_path/vendor/autoload.php" ]]; then
    echo "Production dependencies are missing." >&2
    exit 69
fi

if [[ ! -f "$project_path/storage/app/private/installed.lock" ]]; then
    echo "The definitive installation lock is missing." >&2
    exit 70
fi

cd "$project_path"

"$php_binary" --version | head -n 1
"$php_binary" artisan assestme:diagnose --json >/dev/null

health_payload="$(curl --fail --silent --show-error --max-time 15 "https://${domain}/up")"
if [[ "$health_payload" != *'"status":"healthy"'* && "$health_payload" != *'"status": "healthy"'* ]]; then
    echo "The application health endpoint is not healthy." >&2
    exit 70
fi

login_status="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' --max-time 15 "https://${domain}/admin/login")"
if [[ "$login_status" != "200" ]]; then
    echo "The administrator login endpoint returned HTTP ${login_status}." >&2
    exit 70
fi

heartbeat_path="$project_path/storage/app/private/operational-status/scheduler-heartbeat.json"
if [[ ! -s "$heartbeat_path" || -L "$heartbeat_path" ]]; then
    echo "The scheduler heartbeat is missing or unsafe." >&2
    exit 70
fi

backup_directory="$project_path/storage/backups"
mkdir -p "$backup_directory"
backup_path="$backup_directory/cloudpanel-smoke-$(date -u +%Y%m%dT%H%M%SZ).tar.gz"
"$php_binary" artisan assestme:backup --output="$backup_path" >/dev/null
"$php_binary" artisan assestme:backup:verify "$backup_path" >/dev/null

echo "CloudPanel smoke checks passed."
echo "Health, login, lock, scheduler heartbeat, WeasyPrint diagnostics, backup and verification are operational."
echo "Verified backup: $backup_path"
