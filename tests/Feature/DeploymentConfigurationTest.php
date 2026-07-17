<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

it('includes SQLite integrity verification in application diagnostics', function (): void {
    $this->artisan('assestme:diagnose')
        ->expectsOutputToContain('"sqlite_integrity_check": true')
        ->assertSuccessful();
});

it('ships a hardened nginx front controller with ACME-safe HTTPS redirection', function (): void {
    $configuration = (string) file_get_contents(base_path('stubs/nginx/assestme.conf'));

    expect($configuration)
        ->toContain('server_name __ASSESTME_HOSTNAME__;')
        ->toContain('root __ASSESTME_PUBLIC_ROOT__;')
        ->toContain('client_max_body_size 25m;')
        ->toContain('location ^~ /.well-known/acme-challenge/')
        ->toContain('if ($scheme = http)')
        ->toContain('return 301 https://$host$request_uri;')
        ->toContain('try_files $uri $uri/ /index.php?$query_string;')
        ->toContain('location = /index.php')
        ->toContain('include fastcgi_params;')
        ->toContain('fastcgi_pass __ASSESTME_PHP_FPM_ENDPOINT__;')
        ->not->toContain('snippets/fastcgi-php.conf')
        ->toContain('location ~ \.php$')
        ->toContain('return 404;')
        ->toContain('location ^~ /database/')
        ->toContain('location ^~ /storage/')
        ->toContain('location ^~ /vendor/')
        ->toContain('location ^~ /tests/')
        ->toContain('location ^~ /bootstrap/')
        ->toContain('Content-Security-Policy')
        ->toContain('Strict-Transport-Security')
        ->toContain('X-Content-Type-Options')
        ->toContain('X-Frame-Options');

    expect(substr_count($configuration, 'fastcgi_pass '))->toBe(1);
});

it('uses capability-based preflight and rejects an incomplete project root', function (): void {
    $script = (string) file_get_contents(base_path('scripts/preflight.sh'));

    expect($script)
        ->toContain('PHP 8.3.x is required')
        ->toContain('Missing required PHP extension')
        ->toContain('artisan, composer.json, and composer.lock')
        ->not->toContain('lsb_release')
        ->not->toContain('/etc/os-release')
        ->not->toContain('uname -m')
        ->not->toContain('command -v sqlite3');

    $valid = new Process(['bash', base_path('scripts/preflight.sh'), base_path()], base_path());
    $valid->run();
    expect($valid->isSuccessful())->toBeTrue($valid->getErrorOutput());

    $invalidRoot = storage_path('framework/testing/preflight-'.bin2hex(random_bytes(6)));
    File::ensureDirectoryExists($invalidRoot);

    try {
        $invalid = new Process(['bash', base_path('scripts/preflight.sh'), $invalidRoot], base_path());
        $invalid->run();

        expect($invalid->isSuccessful())->toBeFalse()
            ->and($invalid->getErrorOutput())->toContain('must contain artisan, composer.json, and composer.lock');
    } finally {
        File::deleteDirectory($invalidRoot);
    }
});

it('forces the isolated verifier to the Italian application contract', function (): void {
    $command = <<<'BASH'
export APP_FALLBACK_LOCALE=en
export APP_LOCALE=en
export APP_TIMEZONE=UTC
unset ASSESTME_ISOLATED_ENV_OWNS_ROOT ASSESTME_TEST_ISOLATED ASSESTME_TEST_ROOT
source scripts/isolated-environment.sh
assestme_begin_isolated_environment locale-contract
printf '%s|%s|%s' "$APP_LOCALE" "$APP_FALLBACK_LOCALE" "$APP_TIMEZONE"
assestme_end_isolated_environment
BASH;
    $process = new Process(['bash', '-c', $command], base_path());
    $process->run();

    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput())
        ->and($process->getOutput())->toBe('it|it|Europe/Rome');
});

it('requires a clean locked release and reapplies production permissions', function (): void {
    $script = (string) file_get_contents(base_path('scripts/deploy-production.sh'));

    expect($script)
        ->toContain('ASSESTME_RELEASE_ROOT is required.')
        ->toContain('ASSESTME_BACKUP_ROOT is required.')
        ->toContain('ASSESTME_RUNTIME_GROUP is required.')
        ->toContain('ASSESTME_RUNTIME_GROUP is invalid.')
        ->toContain('ASSESTME_FPM_SERVICE is required.')
        ->toContain('ASSESTME_FPM_SERVICE is invalid.')
        ->toContain('chgrp -R "$runtime_group"')
        ->toContain('systemctl reload "$fpm_service"')
        ->toContain('git -C "$source_dir" ls-files --error-unmatch composer.lock')
        ->toContain('git -C "$source_dir" status --porcelain --untracked-files=all')
        ->toContain('composer validate --strict --working-dir="$source_dir"')
        ->toContain('composer install --no-dev --no-interaction --prefer-dist --classmap-authoritative')
        ->toContain('find "$release_dir" -type d -exec chmod 0750 {} +')
        ->toContain('find "$release_dir" -type f -exec chmod 0640 {} +')
        ->toContain('find "$shared_dir/database" "$shared_dir/storage" -type d -exec chmod 0770 {} +')
        ->toContain('chmod 0660 "$shared_dir/database/database.sqlite"')
        ->toContain('php artisan migrate --force --isolated')
        ->toContain('php artisan filament:assets')
        ->toContain('php artisan assestme:diagnose')
        ->toContain('curl --fail --silent --show-error --retry 5')
        ->not->toContain('|| true');

    expect(substr_count($script, 'secure_permissions'))->toBeGreaterThanOrEqual(4);
});

it('ships a confirmed compensating production rollback', function (): void {
    $script = (string) file_get_contents(base_path('scripts/rollback-production.sh'));

    expect($script)
        ->toContain('ASSESTME_RELEASE_ROOT is required.')
        ->toContain('ASSESTME_BACKUP_ROOT is required.')
        ->toContain('ASSESTME_FPM_SERVICE is required.')
        ->toContain('ASSESTME_FPM_SERVICE is invalid.')
        ->toContain('Non-interactive rollback requires --force.')
        ->toContain("read -r -p 'Type ROLLBACK to continue: '")
        ->toContain('every database and private-storage write newer than that backup will be discarded')
        ->toContain('assestme:backup:verify "$backup_path"')
        ->toContain('assestme:backup --output="$pre_rollback_backup"')
        ->toContain('assestme:restore-backup "$backup_path"')
        ->toContain('assestme:restore-backup "$pre_rollback_backup"')
        ->toContain('mv -Tf "${current_link}.rollback" "$current_link"')
        ->toContain('php "$target_release/artisan" assestme:diagnose')
        ->toContain('curl --fail --silent --show-error --retry 5')
        ->toContain('systemctl reload "$fpm_service"')
        ->not->toContain('|| true');
});
