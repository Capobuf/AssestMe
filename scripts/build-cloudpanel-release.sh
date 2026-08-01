#!/usr/bin/env bash

set -euo pipefail

project_path="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
release_version="${1:-}"
output_directory="${2:-$project_path/releases}"

if [[ ! "$release_version" =~ ^[0-9A-Za-z][0-9A-Za-z._-]*$ ]]; then
    echo "Usage: scripts/build-cloudpanel-release.sh <version> [absolute-output-directory]" >&2
    exit 64
fi

if [[ "$output_directory" != /* ]]; then
    echo "The output directory must be absolute." >&2
    exit 65
fi

for executable in composer rsync sha256sum php; do
    if ! command -v "$executable" >/dev/null 2>&1; then
        echo "Required build executable is missing: $executable" >&2
        exit 69
    fi
done

if ! php -r 'exit(class_exists("ZipArchive") ? 0 : 1);'; then
    echo "The PHP zip extension is required to build the release archive." >&2
    exit 69
fi

staging_parent="$(mktemp -d "${TMPDIR:-/tmp}/assestme-release.XXXXXXXX")"
staging_path="$staging_parent/assestme"

cleanup() {
    if [[ -n "${staging_parent:-}" && "$staging_parent" == "${TMPDIR:-/tmp}"/assestme-release.* && -d "$staging_parent" ]]; then
        find "$staging_parent" -depth -delete
    fi
}
trap cleanup EXIT

mkdir -p "$staging_path" "$output_directory"

rsync --archive --delete \
    --exclude='.git/' \
    --exclude='.github/' \
    --exclude='.env' \
    --exclude='.env.backup' \
    --exclude='.env.pending' \
    --exclude='auth.json' \
    --exclude='backups/' \
    --exclude='docker/' \
    --exclude='node_modules/' \
    --exclude='releases/' \
    --exclude='tests/' \
    --exclude='vendor/' \
    --exclude='database/*.sqlite*' \
    --exclude='public/hot' \
    --exclude='storage/app/database/*' \
    --exclude='storage/app/generated/*' \
    --exclude='storage/app/private/*' \
    --exclude='storage/backups/*' \
    --exclude='storage/framework/cache/data/*' \
    --exclude='storage/framework/installer/*' \
    --exclude='storage/framework/sessions/*' \
    --exclude='storage/framework/views/*' \
    --exclude='storage/logs/*' \
    "$project_path/" "$staging_path/"

printf '%s\n' "$release_version" > "$staging_path/VERSION"

(
    cd "$staging_path"
    APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' composer install \
        --no-dev \
        --prefer-dist \
        --no-interaction \
        --no-progress \
        --classmap-authoritative
    APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan filament:assets
    composer check-platform-reqs --no-dev
)

if [[ -e "$staging_path/.env" || ! -f "$staging_path/vendor/autoload.php" ]]; then
    echo "Release staging validation failed." >&2
    exit 70
fi

find "$staging_path/storage" -type f ! -name '.gitignore' -delete
find "$staging_path/bootstrap/cache" -type f ! -name '.gitignore' -delete

(
    cd "$staging_path"
    find . -type f ! -name 'RELEASE-MANIFEST.sha256' -print0 \
        | LC_ALL=C sort -z \
        | xargs -0 sha256sum > RELEASE-MANIFEST.sha256
)

archive_path="$output_directory/assestme-${release_version}.zip"

php -r '
$source = $argv[1];
$destination = $argv[2];
$zip = new ZipArchive();
if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Unable to create the release archive.\n");
    exit(1);
}
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST,
);
foreach ($iterator as $item) {
    $path = $item->getPathname();
    $relative = "assestme/".substr($path, strlen($source) + 1);
    if ($item->isDir()) {
        $zip->addEmptyDir($relative);
    } else {
        $zip->addFile($path, $relative);
    }
}
if (! $zip->close()) {
    fwrite(STDERR, "Unable to finalize the release archive.\n");
    exit(1);
}
' "$staging_path" "$archive_path"

echo "$archive_path"
