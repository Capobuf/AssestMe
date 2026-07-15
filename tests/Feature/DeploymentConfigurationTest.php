<?php

declare(strict_types=1);

it('includes SQLite integrity verification in application diagnostics', function (): void {
    $this->artisan('assestme:diagnose')
        ->expectsOutputToContain('"sqlite_integrity_check": true')
        ->assertSuccessful();
});

it('ships a hardened nginx front controller with ACME-safe HTTPS redirection', function (): void {
    $configuration = (string) file_get_contents(base_path('stubs/nginx/assestme.conf'));

    expect($configuration)
        ->toContain('server_name __ASSESTME_HOSTNAME__;')
        ->toContain('root /var/www/assestme/current/public;')
        ->toContain('client_max_body_size 25m;')
        ->toContain('location ^~ /.well-known/acme-challenge/')
        ->toContain('if ($scheme = http)')
        ->toContain('return 301 https://$host$request_uri;')
        ->toContain('try_files $uri $uri/ /index.php?$query_string;')
        ->toContain('location = /index.php')
        ->toContain('fastcgi_pass unix:/run/php/php8.3-fpm.sock;')
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

it('requires a clean locked release and reapplies production permissions', function (): void {
    $script = (string) file_get_contents(base_path('scripts/deploy-production.sh'));

    expect($script)
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
        ->not->toContain('|| true');
});
