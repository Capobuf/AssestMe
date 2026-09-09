<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

it('includes driver-aware database integrity verification in application diagnostics', function (): void {
    User::factory()->create();

    $this->artisan('assestme:diagnose', ['--json' => true])
        ->expectsOutputToContain('"database_integrity": {')
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
    expect(array_keys($compose['services']))->toBe(['app', 'selenium', 'mysql-test', 'mariadb-test'])
        ->and($compose)->not->toHaveKey('volumes');

    $app = $compose['services']['app'];
    $selenium = $compose['services']['selenium'];
    $mysql = $compose['services']['mysql-test'];
    $mariaDb = $compose['services']['mariadb-test'];

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
        ->and($app['environment']['ASSESTME_VERIFY_RUNTIME'])->toBe('docker-compose-dev')
        ->and($app['environment']['ASSESTME_UID'])->toBe('${UID:-}')
        ->and($app['environment']['ASSESTME_GID'])->toBe('${GID:-}')
        ->and($app['environment']['DUSK_BROWSER_HOST'])->toBe('assestme-app')
        ->and($app['environment']['GIT_CONFIG_COUNT'])->toBe('1')
        ->and($app['environment']['GIT_CONFIG_KEY_0'])->toBe('safe.directory')
        ->and($app['environment']['GIT_CONFIG_VALUE_0'])->toBe('/workspace')
        ->and($app['networks']['default']['aliases'])->toBe(['assestme-app'])
        ->and($app['environment']['XDEBUG_MODE'])->toBe('${XDEBUG_MODE:-off}')
        ->and($app['extra_hosts'])->toContain('host.docker.internal:host-gateway')
        ->and($app)->not->toHaveKeys(['container_name', 'privileged', 'depends_on', 'cap_add']);

    expect($selenium['profiles'])->toBe(['browser'])
        ->and($selenium['image'])->toBe('selenium/standalone-chromium:4.46.0-20260707')
        ->and($selenium['shm_size'])->toBe('2gb')
        ->and($selenium)->not->toHaveKey('ports')
        ->and($selenium)->not->toHaveKeys(['container_name', 'privileged', 'cap_add']);

    expect($mysql['profiles'])->toBe(['database'])
        ->and($mysql['image'])->toBe('${ASSESTME_MYSQL_TEST_IMAGE:-mysql:8.4.11}')
        ->and($mariaDb['profiles'])->toBe(['database'])
        ->and($mariaDb['image'])->toBe('${ASSESTME_MARIADB_TEST_IMAGE:-mariadb:12.3.2}');

    $serializedCompose = strtolower((string) file_get_contents($composePath));
    expect($serializedCompose)
        ->not->toContain('/var/run/docker.sock')
        ->not->toMatch('/\b(?:nginx|php-fpm|node(?:js)?|npm|pnpm|yarn|vite|redis|postgres(?:ql)?|supervisor|systemd|horizon|queue worker)\b/');

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
        ->toContain('pdo_mysql')
        ->toContain('default-mysql-client')
        ->not->toContain('copy . /workspace')
        ->not->toMatch('/\b(?:alpine|nginx|php-fpm|node(?:js)?|npm|pnpm|yarn|vite|redis|postgres(?:ql)?|supervisor|systemd|horizon|chromedriver|chromium)\b/');
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
        ->toContain('base64_encode(random_bytes(32))')
        ->toContain('grep -Fqx "APP_KEY=${application_key}" .env')
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

it('records the superseded installation history and approved Docker and hosted production profiles', function (): void {
    $plan = (string) file_get_contents(base_path('plan.md'));

    expect($plan)
        ->toContain('# AssestMe documentation entry point')
        ->toContain('docs/index.md')
        ->toContain('contains no duplicated product or architecture requirements')
        ->not->toContain('| D-007');

    $runtimeAdr = (string) file_get_contents(
        base_path('docs/adr/0002-runtime-and-dependencies.md'),
    );
    $databaseAdr = (string) file_get_contents(
        base_path('docs/adr/0003-domain-data-and-import.md'),
    );
    $backupAdr = (string) file_get_contents(
        base_path('docs/adr/0006-storage-deletion-and-backup.md'),
    );
    $securityAdr = (string) file_get_contents(
        base_path('docs/adr/0007-security-and-installation-state.md'),
    );
    $verificationAdr = (string) file_get_contents(
        base_path('docs/adr/0008-testing-and-verification.md'),
    );
    $deploymentAdr = (string) file_get_contents(
        base_path('docs/adr/0009-deployment-and-installer.md'),
    );

    expect($runtimeAdr)
        ->toContain('| D-007 (v2) | SUPERSEDED |')
        ->toContain('| D-007 | APPROVED | The application requires no Node.js frontend build; Docker Compose is the maintained and authoritative local development environment |')
        ->toContain('| D-042 (v1) | SUPERSEDED | The Docker development profile published the development HTTP port only on host loopback; superseded by D-042 on 2026-07-18 |')
        ->toContain('| D-042 | APPROVED | The Docker development profile is defined by `docker/compose.dev.yml`, a project-owned PHP 8.3 development image, bind-mounted source code, persistent default SQLite state, optional isolated Selenium browser testing, optional real MySQL/MariaDB compatibility-test services, and HTTP publication on all host IPv4 interfaces |')
        ->toContain('docker/compose.dev.yml');

    expect($deploymentAdr)
        ->toContain('| D-019 (v1) | SUPERSEDED |')
        ->toContain('| D-019 | SUPERSEDED | Docker Compose development and a future, not-yet-implemented CloudPanel production profile were approved; superseded by D-064 on 2026-07-31 |')
        ->toContain('| D-064 | APPROVED |')
        ->toContain('| D-068 | APPROVED |')
        ->toContain('| D-069 | APPROVED |')
        ->toContain('| D-070 | APPROVED |')
        ->toContain('traditional PHP hosting');

    expect($databaseAdr)->toContain('| D-063 | APPROVED |');
    expect($securityAdr)->toContain('| D-065 | APPROVED |');
    expect($backupAdr)->toContain('| D-066 | APPROVED |');
    expect($verificationAdr)
        ->toContain('| D-067 | APPROVED |')
        ->toContain('| D-074 | APPROVED |');

    $requiredDocumentation = [
        'docs/index.md',
        'docs/adr/README.md',
        'docs/adr/0002-runtime-and-dependencies.md',
        'docs/adr/0003-domain-data-and-import.md',
        'docs/adr/0006-storage-deletion-and-backup.md',
        'docs/adr/0007-security-and-installation-state.md',
        'docs/adr/0008-testing-and-verification.md',
        'docs/adr/0009-deployment-and-installer.md',
        'docs/how-to/development-and-verification.md',
        'docs/how-to/hosting-installation.md',
        'docs/how-to/cpanel-installation.md',
        'docs/how-to/cloudpanel-installation.md',
        'docs/how-to/cloudpanel-acceptance-checklist.md',
    ];

    foreach ($requiredDocumentation as $documentationPath) {
        expect(base_path($documentationPath))->toBeFile();
    }

    expect(base_path('scripts/cloudpanel'))->not->toBeDirectory();
});

it('normalizes blank optional Google credentials from a clean Compose checkout', function (): void {
    $services = (string) file_get_contents(base_path('config/services.php'));

    expect($services)
        ->toContain("'client_id' => env('GOOGLE_CLIENT_ID') ?: null")
        ->toContain("'client_secret' => env('GOOGLE_CLIENT_SECRET') ?: null");
});

it('rejects both verification gates before work begins outside the marked Compose runtime', function (): void {
    foreach (['scripts/verify-core.sh', 'scripts/verify.sh'] as $script) {
        $process = new Process([
            'env',
            '-u',
            'ASSESTME_VERIFY_RUNTIME',
            'bash',
            base_path($script),
        ], base_path());
        $process->run();

        expect($process->isSuccessful())->toBeFalse()
            ->and($process->getOutput())->toBe('')
            ->and($process->getErrorOutput())
            ->toContain('must run inside the app service from docker/compose.dev.yml');
    }
});

it('uses capability-based preflight and rejects an incomplete project root', function (): void {
    $script = (string) file_get_contents(base_path('scripts/preflight.sh'));

    expect($script)
        ->toContain('PHP >= 8.3.0 is required')
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

it('uses a private disposable environment placeholder in clean quality checkouts', function (): void {
    $testRoot = sys_get_temp_dir().'/assestme-environment-placeholder-'.bin2hex(random_bytes(8));
    File::ensureDirectoryExists($testRoot);

    try {
        $createCommand = <<<'BASH'
source scripts/isolated-environment.sh
assestme_prepare_isolated_environment_file "$1"
printf '%s|%s|%s|' "$ASSESTME_ISOLATED_ENV_OWNS_FILE" "$(stat -c '%a' "$1/.env")" "$(stat -c '%s' "$1/.env")"
assestme_cleanup_isolated_environment_file
[[ ! -e "$1/.env" ]]
BASH;
        $createProcess = new Process(['bash', '-c', $createCommand, 'bash', $testRoot], base_path());
        $createProcess->run();

        expect($createProcess->isSuccessful())->toBeTrue($createProcess->getErrorOutput())
            ->and($createProcess->getOutput())->toBe('1|600|0|');

        File::put($testRoot.'/.env', "APP_NAME=Existing\n");

        $preserveCommand = <<<'BASH'
source scripts/isolated-environment.sh
assestme_prepare_isolated_environment_file "$1"
printf '%s|' "$ASSESTME_ISOLATED_ENV_OWNS_FILE"
assestme_cleanup_isolated_environment_file
cat "$1/.env"
BASH;
        $preserveProcess = new Process(['bash', '-c', $preserveCommand, 'bash', $testRoot], base_path());
        $preserveProcess->run();

        expect($preserveProcess->isSuccessful())->toBeTrue($preserveProcess->getErrorOutput())
            ->and($preserveProcess->getOutput())->toBe("0|APP_NAME=Existing\n");

        File::delete($testRoot.'/.env');
        File::ensureDirectoryExists($testRoot.'/.env');

        $invalidProcess = new Process([
            'bash',
            '-c',
            'source scripts/isolated-environment.sh; assestme_prepare_isolated_environment_file "$1"',
            'bash',
            $testRoot,
        ], base_path());
        $invalidProcess->run();

        expect($invalidProcess->isSuccessful())->toBeFalse()
            ->and($invalidProcess->getErrorOutput())
            ->toContain('The existing isolated environment file must be a readable file');
    } finally {
        File::deleteDirectory($testRoot);
    }
});

it('runs each expensive gate once and reuses only exact fingerprint receipts', function (): void {
    $quality = (string) file_get_contents(base_path('scripts/quality-isolated.sh'));
    $browser = (string) file_get_contents(base_path('scripts/dusk-isolated.sh'));
    $core = (string) file_get_contents(base_path('scripts/verify-core.sh'));
    $verify = (string) file_get_contents(base_path('scripts/verify.sh'));
    $qualityWorkflow = (string) file_get_contents(base_path('.github/workflows/quality.yml'));
    $acceptanceWorkflow = (string) file_get_contents(base_path('.github/workflows/acceptance.yml'));
    $testPosition = strpos($quality, 'php artisan test');
    $canarySetupPosition = strrpos($quality, 'php artisan migrate:fresh --seed --force');
    $canaryPosition = strpos($quality, 'php artisan canary:check --strict');

    expect($quality)
        ->toContain('vendor/bin/pint --test')
        ->toContain('vendor/bin/phpstan analyse --memory-limit=1G')
        ->toContain('assestme_prepare_isolated_environment_file "$PWD"')
        ->toContain('assestme_cleanup_isolated_environment_file')
        ->toContain('php artisan test')
        ->toContain('php artisan canary:check --strict')
        ->toContain('php artisan assestme:create-admin --from-env >/dev/null')
        ->toContain('php artisan assestme:installation:lock --force >/dev/null')
        ->toContain('composer audit --locked --no-interaction')
        ->toContain('assestme_write_gate_receipt quality')
        ->and(substr_count($quality, 'php artisan migrate:fresh --seed --force'))->toBe(2)
        ->and($testPosition)->toBeInt()
        ->and($canarySetupPosition)->toBeInt()->toBeGreaterThan($testPosition)
        ->and($canaryPosition)->toBeInt()->toBeGreaterThan($canarySetupPosition)
        ->and($core)
        ->toContain('[[ -f /.dockerenv && "${ASSESTME_VERIFY_RUNTIME:-}" == "docker-compose-dev" ]]')
        ->toContain('bash scripts/preflight.sh "$project_dir"')
        ->toContain('assestme_gate_receipt_matches quality')
        ->toContain('php artisan assestme:diagnose')
        ->toContain('php artisan assestme:benchmark --findings=50')
        ->toContain('php artisan assestme:storage:audit')
        ->not->toContain('dusk')
        ->not->toContain('selenium')
        ->and($verify)
        ->toContain('bash scripts/verify-core.sh "$project_dir"')
        ->toContain('assestme_gate_receipt_matches browser')
        ->not->toContain('vendor/bin/pint --test')
        ->not->toContain('vendor/bin/phpstan analyse')
        ->not->toContain('php artisan test')
        ->not->toContain('php artisan canary:check')
        ->not->toContain('composer audit')
        ->and($browser)
        ->toContain('if [[ $# -eq 0 ]]')
        ->toContain('assestme_write_gate_receipt browser')
        ->and($qualityWorkflow)
        ->toContain('app scripts/verify-core.sh')
        ->not->toContain('--profile browser')
        ->not->toContain('selenium')
        ->not->toContain('composer validate --strict')
        ->not->toContain('composer audit --locked')
        ->and($acceptanceWorkflow)
        ->toContain('app scripts/verify.sh')
        ->not->toContain('RUN_DUSK=0');

    expect(substr_count($verify, 'scripts/dusk-isolated.sh'))->toBe(1)
        ->and(substr_count($acceptanceWorkflow, 'app scripts/verify.sh'))->toBe(1);

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

it('keeps normal PR quality browser-free and publishes only after complete acceptance', function (): void {
    $qualityPath = base_path('.github/workflows/quality.yml');
    $workflowPath = base_path('.github/workflows/acceptance.yml');
    $qualitySource = (string) file_get_contents($qualityPath);
    $workflowSource = (string) file_get_contents($workflowPath);
    $legacyArchiveSuffix = '-cloudpanel'.'.zip';
    $externalChecksumSuffix = '.zip'.'.sha256';

    /** @var array{permissions: array{contents: string}, jobs: array<string, array<string, mixed>>} $quality */
    $quality = Yaml::parseFile($qualityPath);
    /** @var array{permissions: array{contents: string}, jobs: array<string, array<string, mixed>>} $workflow */
    $workflow = Yaml::parseFile($workflowPath);
    $publish = $workflow['jobs']['publish-develop-release'];
    $release = $workflow['jobs']['release-acceptance'];
    $releaseDiagnostics = collect($release['steps'])
        ->firstWhere('name', 'Upload release installer diagnostics');

    expect(array_keys($quality['jobs']))->toBe(['quality', 'database-compatibility'])
        ->and($quality['concurrency'])->toBe([
            'group' => 'quality-${{ github.workflow }}-${{ github.event.pull_request.number || github.ref }}',
            'cancel-in-progress' => true,
        ])
        ->and($qualitySource)
        ->toContain('docker compose -f docker/compose.dev.yml up --build --detach --wait app')
        ->toContain('app scripts/verify-core.sh')
        ->not->toContain('--profile browser')
        ->not->toContain('selenium')
        ->not->toContain('dusk-isolated.sh')
        ->and($workflow['permissions'])->toBe(['contents' => 'read'])
        ->and($publish['if'])->toContain("github.event_name == 'push'")
        ->and($publish['if'])->toContain("github.ref == 'refs/heads/develop'")
        ->and($publish['needs'])->toBe([
            'complete-validation',
            'release-acceptance',
            'clean-checkout-bootstrap',
        ])
        ->and($publish['permissions'])->toBe(['contents' => 'write'])
        ->and($publish['concurrency'])->toBe([
            'group' => 'assestme-develop-release',
            'cancel-in-progress' => true,
        ])
        ->and($workflowSource)->toContain('name: assestme-ci-release')
        ->toContain('path: ${{ runner.temp }}/release/assestme-ci.zip')
        ->toContain('uses: actions/checkout@v5')
        ->toContain('uses: actions/download-artifact@v7')
        ->toContain('uses: actions/upload-artifact@v6')
        ->toContain('scripts/build-release.sh ci "${RUNNER_TEMP}/release"')
        ->toContain('scripts/release-installer-acceptance.sh "${RUNNER_TEMP}/validated-release/assestme"')
        ->toContain('if [[ "$acceptance_status" -ne 139 ]]')
        ->toContain('validated-release-retry')
        ->toContain('release-installer-retry')
        ->not->toContain('uses: actions/checkout@v4')
        ->not->toContain('uses: actions/download-artifact@v4')
        ->not->toContain('uses: actions/upload-artifact@v4')
        ->not->toContain('continue-on-error')
        ->not->toContain('KNOWN_GOOD_COMMIT')
        ->not->toContain('d02539')
        ->not->toContain('strace')
        ->not->toContain('current-http-repeat')
        ->not->toContain('ASSESTME_FIC_DUSK_FAKE=1')
        ->toContain('assestme-develop.zip')
        ->toContain("release_tag='develop-latest'")
        ->toContain("release_title='AssestMe develop — ultima build valida'")
        ->toContain('--prerelease')
        ->toContain('git rev-parse origin/develop')
        ->not->toContain($legacyArchiveSuffix)
        ->not->toContain($externalChecksumSuffix)
        ->and($releaseDiagnostics)->toBeArray()
        ->and($releaseDiagnostics['if'])->toBe('failure()')
        ->and($releaseDiagnostics['uses'])->toBe('actions/upload-artifact@v6')
        ->and($releaseDiagnostics['with']['name'])->toBe('release-installer-diagnostics')
        ->and($releaseDiagnostics['with']['path'])
        ->toContain('${{ runner.temp }}/release-installer/storage/logs/release-server.log')
        ->toContain('${{ runner.temp }}/validated-release/assestme/storage/logs/laravel.log')
        ->toContain('tests/Browser/screenshots')
        ->toContain('tests/Browser/source')
        ->toContain('tests/Browser/console')
        ->not->toContain('.env')
        ->not->toContain('bootstrap-key')
        ->not->toContain('state.enc')
        ->and($release['steps'])->toContain([
            'name' => 'Upload validated installable release',
            'uses' => 'actions/upload-artifact@v6',
            'with' => [
                'name' => 'assestme-ci-release',
                'path' => '${{ runner.temp }}/release/assestme-ci.zip',
                'if-no-files-found' => 'error',
                'retention-days' => 7,
            ],
        ]);
});

it('deploys CloudPanel only after the successful terminal develop job with verified SSH', function (): void {
    $workflowPath = base_path('.github/workflows/acceptance.yml');
    $workflowSource = (string) file_get_contents($workflowPath);

    /** @var array{jobs: array<string, array<string, mixed>>} $workflow */
    $workflow = Yaml::parseFile($workflowPath);
    $deploy = $workflow['jobs']['deploy_cloudpanel'];

    expect($deploy['name'])->toBe('Deploy CloudPanel CI')
        ->and($deploy['needs'])->toBe(['publish-develop-release'])
        ->and($deploy['if'])->toContain("github.event_name == 'push'")
        ->toContain("github.ref == 'refs/heads/develop'")
        ->toContain('success()')
        ->and($deploy['runs-on'])->toBe('ubuntu-latest')
        ->and($deploy['timeout-minutes'])->toBe(20)
        ->and($deploy['permissions'])->toBe(['contents' => 'read'])
        ->and($deploy['concurrency'])->toBe([
            'group' => 'assestme-cloudpanel-ci',
            'cancel-in-progress' => false,
        ])
        ->and($workflow['jobs']['publish-develop-release']['needs'])->toContain('complete-validation')
        ->toContain('release-acceptance')
        ->toContain('clean-checkout-bootstrap')
        ->and($workflowSource)->toContain('CLOUDPANEL_SSH_PRIVATE_KEY')
        ->toContain('CLOUDPANEL_KNOWN_HOSTS')
        ->toContain('CLOUDPANEL_HOST')
        ->toContain('CLOUDPANEL_PORT')
        ->toContain('CLOUDPANEL_USER')
        ->toContain('-o BatchMode=yes')
        ->toContain('-o IdentitiesOnly=yes')
        ->toContain('-o StrictHostKeyChecking=yes')
        ->toContain('-o ConnectTimeout=20')
        ->not->toContain('StrictHostKeyChecking=no')
        ->not->toContain('ssh-action');
});

it('defines a locked forced-command CloudPanel deploy script with explicit failure paths', function (): void {
    $scriptPath = base_path('deploy/cloudpanel/deploy-assestme');
    $script = (string) file_get_contents($scriptPath);
    $syntax = new Process(['bash', '-n', $scriptPath], base_path());
    $syntax->run();

    expect($scriptPath)->toBeFile()
        ->and(is_executable($scriptPath))->toBeTrue()
        ->and($syntax->isSuccessful())->toBeTrue($syntax->getErrorOutput())
        ->and($script)
        ->toContain('set -Eeuo pipefail')
        ->toContain('umask 027')
        ->toContain('DEPLOY_ROOT=/home/bydot-assestme/htdocs/assestme.bydot.it')
        ->toContain('LOCK_FILE="$HOME/.dploy/github-actions-deploy.lock"')
        ->toContain('if ! /usr/bin/flock -n 9; then')
        ->toContain('Another AssestMe deployment is already running.')
        ->toContain('exit 75')
        ->toContain('/usr/local/bin/dploy deploy develop')
        ->toContain('test -f "$CURRENT_RELEASE/artisan"')
        ->toContain('test -f "$CURRENT_RELEASE/public/index.php"')
        ->toContain('/usr/bin/php8.3')
        ->toContain('about')
        ->toContain('Deployed release: %s')
        ->not->toContain('/usr/bin/git')
        ->not->toContain('Deployed commit: %s')
        ->not->toContain('sudo')
        ->not->toContain('|| true');
});
