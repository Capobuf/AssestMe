<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

it('includes SQLite integrity verification in application diagnostics', function (): void {
    $this->artisan('assestme:diagnose')
        ->expectsOutputToContain('"sqlite_integrity_check": true')
        ->assertSuccessful();
});

it('preserves the legacy generic-host nginx artifact without Docker development settings', function (): void {
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

    expect($configuration)->not->toContain('xdebug');
});

it('defines the authoritative minimal Docker development topology semantically', function (): void {
    $composePath = base_path('docker/compose.dev.yml');
    $dockerfilePath = base_path('docker/dev/Dockerfile');

    expect($composePath)->toBeFile()
        ->and($dockerfilePath)->toBeFile()
        ->and(base_path('docker/dev/php.ini'))->toBeFile()
        ->and(base_path('docker/dev/entrypoint.sh'))->toBeFile();

    foreach (['compose.yaml', 'compose.yml', 'docker-compose.yaml', 'docker-compose.yml'] as $rootComposeFile) {
        expect(base_path($rootComposeFile))->not->toBeFile();
    }

    /** @var array{services: array<string, array<string, mixed>>, volumes?: mixed} $compose */
    $compose = Yaml::parseFile($composePath);
    expect(array_keys($compose['services']))->toBe(['app', 'selenium'])
        ->and($compose)->not->toHaveKey('volumes');

    $app = $compose['services']['app'];
    $selenium = $compose['services']['selenium'];

    expect($app['build'])->toBe([
        'context' => 'dev',
        'dockerfile' => 'Dockerfile',
    ])->and($app['working_dir'])->toBe('/workspace')
        ->and($app['init'])->toBeTrue()
        ->and($app['command'])->toBe([
            'php',
            'artisan',
            'serve',
            '--host=0.0.0.0',
            '--port=8000',
            '--no-reload',
        ])->and($app['ports'])->toBe(['0.0.0.0:8000:8000'])
        ->and($app['volumes'])->toContain('../:/workspace')
        ->and($app['environment']['DB_DATABASE'])->toBe('/workspace/database/database.sqlite')
        ->and($app['environment']['LARAVEL_PDF_WEASYPRINT_BINARY'])->toBe('/usr/bin/weasyprint')
        ->and($app['environment']['ASSESTME_BACKUP_ROOT'])->toBe('/workspace/backups')
        ->and($app['environment']['ASSESTME_UID'])->toBe('${UID:-}')
        ->and($app['environment']['ASSESTME_GID'])->toBe('${GID:-}')
        ->and($app['environment']['DUSK_BROWSER_HOST'])->toBe('assestme-app')
        ->and($app['networks']['default']['aliases'])->toBe(['assestme-app'])
        ->and($app['environment']['XDEBUG_MODE'])->toBe('${XDEBUG_MODE:-off}')
        ->and($app['extra_hosts'])->toContain('host.docker.internal:host-gateway')
        ->and($app)->not->toHaveKeys(['container_name', 'privileged', 'depends_on', 'cap_add']);

    expect($selenium['profiles'])->toBe(['browser'])
        ->and($selenium['image'])->toBe('selenium/standalone-chromium:4.46.0-20260707')
        ->and($selenium['shm_size'])->toBe('2gb')
        ->and($selenium)->not->toHaveKey('ports')
        ->and($selenium)->not->toHaveKeys(['container_name', 'privileged', 'cap_add']);

    $serializedCompose = strtolower((string) file_get_contents($composePath));
    expect($serializedCompose)
        ->not->toContain('/var/run/docker.sock')
        ->not->toMatch('/\b(?:nginx|php-fpm|node(?:js)?|npm|pnpm|yarn|vite|redis|mysql|postgres(?:ql)?|mariadb|supervisor|systemd|horizon|queue worker)\b/');

    $dockerfile = strtolower((string) file_get_contents($dockerfilePath));
    expect($dockerfile)
        ->toContain('from php:8.3.32-cli-bookworm')
        ->toContain('from composer:2.10.2')
        ->toContain('arg xdebug_version=3.5.3')
        ->toContain('docker-php-ext-configure')
        ->toContain('docker-php-ext-install')
        ->toContain('docker-php-ext-enable')
        ->toContain('poppler-utils')
        ->toContain('weasyprint')
        ->toContain('sockets')
        ->not->toContain('copy . /workspace')
        ->not->toMatch('/\b(?:alpine|nginx|php-fpm|node(?:js)?|npm|pnpm|yarn|vite|redis|mysql|postgres(?:ql)?|mariadb|supervisor|systemd|horizon|chromedriver|chromium)\b/');
});

it('configures the Docker PHP runtime and idempotent bootstrap contract', function (): void {
    $configuration = (string) file_get_contents(base_path('docker/dev/php.ini'));
    $entrypoint = (string) file_get_contents(base_path('docker/dev/entrypoint.sh'));
    $bootstrap = (string) file_get_contents(base_path('scripts/bootstrap-local.sh'));
    $applicationConfiguration = (string) file_get_contents(base_path('config/assestme.php'));

    foreach ([
        'memory_limit = 512M',
        'upload_max_filesize = 25M',
        'post_max_size = 30M',
        'max_file_uploads = 20',
        'max_execution_time = 120',
        'display_errors = On',
        'display_startup_errors = On',
        'error_reporting = E_ALL',
        'log_errors = On',
        'opcache.enable = 1',
        'opcache.validate_timestamps = 1',
        'opcache.revalidate_freq = 0',
        'xdebug.mode = off',
        'xdebug.client_host = host.docker.internal',
        'xdebug.client_port = 9003',
    ] as $setting) {
        expect($configuration)->toContain($setting);
    }

    expect($entrypoint)
        ->toContain('set -Eeuo pipefail')
        ->toContain("repository_uid=\"\$(stat -c '%u' /workspace)\"")
        ->toContain('setpriv --reuid="$runtime_uid" --regid="$runtime_gid" --clear-groups')
        ->toContain('scripts/bootstrap-local.sh /workspace')
        ->toContain('exec "$@"')
        ->not->toContain('chmod -R 777');

    expect($bootstrap)
        ->toContain('[[ ! -f vendor/autoload.php || "$installed_lock_hash" != "$composer_lock_hash" ]]')
        ->toContain('[[ -f database/database.sqlite ]] || install -m 660 /dev/null database/database.sqlite')
        ->toContain('App\\Models\\User::query()->exists()')
        ->toContain('php artisan assestme:create-admin \\')
        ->toContain('--from-env')
        ->toContain('php artisan migrate --force')
        ->toContain('php artisan db:seed --force')
        ->toContain('php artisan filament:assets')
        ->toContain('php artisan assestme:diagnose')
        ->not->toContain('migrate:fresh')
        ->not->toContain('export DEV_ADMIN_')
        ->not->toContain('chmod -R 777')
        ->not->toContain('Start: php artisan serve');

    expect($applicationConfiguration)
        ->toContain("env('DEV_ADMIN_NAME', 'Administrator')")
        ->toContain("env('DEV_ADMIN_EMAIL', 'admin@assestme.local')")
        ->toContain("env('DEV_ADMIN_PASSWORD')");
});

it('records the superseded installation history and approved Docker and CloudPanel profiles only', function (): void {
    $plan = (string) file_get_contents(base_path('plan.md'));

    expect($plan)
        ->toContain('| D-007 (v2) | SUPERSEDED |')
        ->toContain('| D-007 | APPROVED | The application requires no Node.js frontend build; Docker Compose is the maintained and authoritative local development environment |')
        ->toContain('| D-019 (v1) | SUPERSEDED |')
        ->toContain('| D-019 | APPROVED | The only supported installation profiles are Docker Compose for development and CloudPanel for production |')
        ->toContain('| D-042 (v1) | SUPERSEDED | The Docker development profile published the development HTTP port only on host loopback; superseded by D-042 on 2026-07-18 |')
        ->toContain('| D-042 | APPROVED | The Docker development profile is defined by `docker/compose.dev.yml`, a project-owned PHP 8.3 development image, bind-mounted source code, persistent SQLite state, optional isolated Selenium browser testing, and HTTP publication on all host IPv4 interfaces |')
        ->toContain('### D-042 — Docker development profile')
        ->toContain('CloudPanel production configuration is not implemented by this decision')
        ->toContain('legacy/deprecated generic-host production artifacts')
        ->toContain('docker/compose.dev.yml');

    $markdownFiles = collect(File::allFiles(base_path()))
        ->reject(fn (SplFileInfo $file): bool => str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR))
        ->filter(fn (SplFileInfo $file): bool => strtolower($file->getExtension()) === 'md')
        ->map(fn (SplFileInfo $file): string => $file->getRelativePathname())
        ->sort()
        ->values()
        ->all();

    expect($markdownFiles)->toBe(['AGENTS.md', 'plan.md']);
    expect(base_path('scripts/cloudpanel'))->not->toBeDirectory();
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

it('runs each expensive gate once and reuses only exact fingerprint receipts', function (): void {
    $quality = (string) file_get_contents(base_path('scripts/quality-isolated.sh'));
    $browser = (string) file_get_contents(base_path('scripts/dusk-isolated.sh'));
    $verify = (string) file_get_contents(base_path('scripts/verify.sh'));
    $workflow = (string) file_get_contents(base_path('.github/workflows/quality.yml'));

    expect($quality)
        ->toContain('vendor/bin/pint --test')
        ->toContain('vendor/bin/phpstan analyse --memory-limit=1G')
        ->toContain('php artisan test')
        ->toContain('php artisan canary:check --strict')
        ->toContain('composer audit --locked --no-interaction')
        ->toContain('assestme_write_gate_receipt quality')
        ->and($verify)
        ->toContain('assestme_gate_receipt_matches quality')
        ->toContain('assestme_gate_receipt_matches browser')
        ->not->toContain('vendor/bin/pint --test')
        ->not->toContain('vendor/bin/phpstan analyse')
        ->not->toContain('php artisan test')
        ->not->toContain('php artisan canary:check')
        ->not->toContain('composer audit')
        ->and($browser)
        ->toContain('if [[ $# -eq 0 ]]')
        ->toContain('assestme_write_gate_receipt browser')
        ->and($workflow)
        ->toContain('run: RUN_DUSK=0 scripts/verify.sh')
        ->not->toContain('composer validate --strict')
        ->not->toContain('composer audit --locked');

    $repository = storage_path('framework/testing/gate-receipt-'.bin2hex(random_bytes(6)));
    File::ensureDirectoryExists($repository);
    File::put($repository.'/tracked.txt', "initial\n");
    File::put($repository.'/plan.md', "# Specification\n\n## 22. Progress\n\nInitial evidence.\n");

    try {
        foreach ([
            ['git', 'init'],
            ['git', 'config', 'user.email', 'gate-test@assestme.local'],
            ['git', 'config', 'user.name', 'Gate Test'],
            ['git', 'add', 'tracked.txt', 'plan.md'],
            ['git', 'commit', '-m', 'Initial'],
        ] as $command) {
            $process = new Process($command, $repository);
            $process->run();
            expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
        }

        $writeAndMatch = new Process(
            ['bash', '-c', <<<'BASH'
source "$GATE_RECEIPTS_HELPER"
fingerprint="$(assestme_gate_fingerprint quality "$GATE_TEST_ROOT")"
assestme_write_gate_receipt quality "$GATE_TEST_ROOT" "$fingerprint"
assestme_gate_receipt_matches quality "$GATE_TEST_ROOT"
BASH],
            base_path(),
            [
                'GATE_RECEIPTS_HELPER' => base_path('scripts/gate-receipts.sh'),
                'GATE_TEST_ROOT' => $repository,
            ],
        );
        $writeAndMatch->run();
        expect($writeAndMatch->isSuccessful())->toBeTrue($writeAndMatch->getErrorOutput());

        File::append($repository.'/plan.md', "Additional factual evidence.\n");
        $progressOnlyChange = new Process(
            ['bash', '-c', <<<'BASH'
source "$GATE_RECEIPTS_HELPER"
assestme_gate_receipt_matches quality "$GATE_TEST_ROOT"
BASH],
            base_path(),
            [
                'GATE_RECEIPTS_HELPER' => base_path('scripts/gate-receipts.sh'),
                'GATE_TEST_ROOT' => $repository,
            ],
        );
        $progressOnlyChange->run();
        expect($progressOnlyChange->isSuccessful())->toBeTrue($progressOnlyChange->getErrorOutput());

        File::put($repository.'/tracked.txt', "changed\n");
        $staleReceipt = new Process(
            ['bash', '-c', <<<'BASH'
source "$GATE_RECEIPTS_HELPER"
assestme_gate_receipt_matches quality "$GATE_TEST_ROOT"
BASH],
            base_path(),
            [
                'GATE_RECEIPTS_HELPER' => base_path('scripts/gate-receipts.sh'),
                'GATE_TEST_ROOT' => $repository,
            ],
        );
        $staleReceipt->run();
        expect($staleReceipt->isSuccessful())->toBeFalse();
    } finally {
        File::deleteDirectory($repository);
    }
});

it('preserves the legacy generic-host deployment artifact for internal regression coverage', function (): void {
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

it('preserves the legacy generic-host rollback artifact for internal regression coverage', function (): void {
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

it('preserves the legacy generic-host rehearsal as a non-normative internal check', function (): void {
    $script = (string) file_get_contents(base_path('scripts/rehearse-production.sh'));

    expect($script)
        ->toContain('mktemp -d')
        ->toContain('git -C "$project_dir" diff --binary --no-ext-diff')
        ->toContain('migrate:fresh --seed --force --no-interaction')
        ->toContain('scripts/deploy-production.sh')
        ->toContain('assestme:backup:verify "$rollback_backup"')
        ->toContain('assestme:restore-backup "$rollback_backup"')
        ->toContain('scripts/rollback-production.sh')
        ->toContain("database_marker\" == 'before-second-deploy'")
        ->toContain("storage_marker\" == 'before-second-deploy'")
        ->toContain('assestme:diagnose')
        ->toContain('Host service reload and public HTTPS reachability were simulated and are not certified')
        ->not->toContain('|| true');
});

it('keeps production configuration serializable', function (): void {
    $cache = storage_path('framework/testing/config-cache-'.bin2hex(random_bytes(6)).'.php');
    $process = new Process(
        ['php', 'artisan', 'config:cache'],
        base_path(),
        ['APP_CONFIG_CACHE' => $cache, 'APP_ENV' => 'production'],
    );

    try {
        $process->run();

        expect($process->isSuccessful())->toBeTrue($process->getErrorOutput())
            ->and($cache)->toBeFile();
    } finally {
        File::delete($cache);
    }
});

it('refuses browser tests outside the marked disposable environment', function (): void {
    $testCase = (string) file_get_contents(base_path('tests/DuskTestCase.php'));

    expect($testCase)
        ->toContain("getenv('ASSESTME_TEST_ISOLATED') !== '1'")
        ->toContain("getenv('ASSESTME_TEST_ROOT')")
        ->toContain("\$isolatedRoot.'/.assestme-test-root'")
        ->toContain('Dusk requires the marked disposable environment created by scripts/dusk-isolated.sh.');
});

it('uses a configured remote Dusk driver without starting local ChromeDriver', function (): void {
    $testCase = (string) file_get_contents(base_path('tests/DuskTestCase.php'));
    $runner = (string) file_get_contents(base_path('scripts/dusk-isolated.sh'));

    expect($testCase)
        ->toContain('if (static::configuredDriverUrl() === null)')
        ->toContain('static::startChromeDriver')
        ->toContain("\$_ENV['DUSK_DRIVER_URL'] ?? getenv('DUSK_DRIVER_URL')")
        ->not->toMatch("/(?<!get)env\\('DUSK_DRIVER_URL'\\)/");

    expect($runner)
        ->toContain('DUSK_SERVER_BIND="${DUSK_SERVER_BIND:-127.0.0.1}"')
        ->toContain('DUSK_BROWSER_HOST="${DUSK_BROWSER_HOST:-127.0.0.1}"')
        ->toContain('DUSK_READY_HOST="${DUSK_READY_HOST:-127.0.0.1}"')
        ->toContain('export APP_URL="http://${DUSK_BROWSER_HOST}:${DUSK_PORT}"')
        ->toContain('"${DUSK_DRIVER_URL%/}/status"')
        ->toContain('exec php -d variables_order=EGPCS -S "${DUSK_SERVER_BIND}:${DUSK_PORT}"')
        ->toContain('http://${DUSK_READY_HOST}:${DUSK_PORT}/admin/login')
        ->toContain('assestme_end_isolated_environment');
});
