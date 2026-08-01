<?php

declare(strict_types=1);

use App\Data\Installation\ApplicationConfigurationData;
use App\Data\Installation\DatabaseConfigurationData;
use App\Data\Installation\InstallationFinalCheckResult;
use App\Data\Installation\InstallationProgressData;
use App\Enums\SupportedDatabaseDriver;
use App\Models\User;
use App\Services\Database\DatabaseClientBinaryResolver;
use App\Services\Database\DatabaseDumpBinaryValidator;
use App\Services\Database\DatabaseRestoreBinaryValidator;
use App\Services\Installation\InstallationFinalCheck;
use App\Services\Installation\InstallationRuntimeInspector;
use App\Services\Installation\InstallationState;
use App\Services\Installation\SchedulerHeartbeat;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;

beforeEach(function (): void {
    $root = storage_path('framework/testing/installer-wizard-'.Str::uuid());
    (new Filesystem)->ensureDirectoryExists($root, 0700, true);

    Config::set([
        'assestme.installation.state_path' => $root.'/state.enc',
        'assestme.installation.lock_path' => $root.'/installed.lock',
        'assestme.installation.finalization_lock_path' => $root.'/finalize.lock',
        'assestme.installation.environment_path' => $root.'/.env',
        'assestme.installation.bootstrap_key_path' => $root.'/bootstrap-key',
        'assestme.scheduler.heartbeat_path' => $root.'/scheduler.json',
    ]);
});

afterEach(function (): void {
    $statePath = (string) config('assestme.installation.state_path');
    (new Filesystem)->deleteDirectory(dirname($statePath));
});

it('exposes only the protected installer flow before the definitive lock', function (): void {
    $this->get('/install')
        ->assertOk()
        ->assertSee('Basic Authentication')
        ->assertSee('SQLite')
        ->assertSee('MySQL / MariaDB')
        ->assertDontSee('scelte separate');

    $this->get('/')->assertRedirect('/install');
    $this->get('/admin')->assertRedirect('/install');
    $this->get('/up')->assertOk()->assertJson([
        'status' => 'not_installed',
        'bootable' => true,
        'scheduler' => 'pending',
    ]);
    $this->get('/js/assestme-installer.js')->assertNotFound();
});

it('does not advance while a real runtime requirement is missing', function (): void {
    Config::set([
        'assestme.installation.php_binary' => null,
        'laravel-pdf.weasyprint.binary' => null,
    ]);
    app()->instance(InstallationRuntimeInspector::class, new InstallationRuntimeInspector(
        phpCandidatePaths: [],
        weasyPrintCandidatePaths: [],
    ));
    $state = app(InstallationState::class);
    $progress = $state->progress();
    $state->save(new InstallationProgressData(
        installationId: $progress->installationId,
        step: 'runtime',
        application: new ApplicationConfigurationData(
            name: 'AssestMe',
            url: 'https://localhost',
            timezone: 'Europe/Rome',
            locale: 'it',
            backupRoot: storage_path('backups'),
            weasyPrintBinary: '/missing/weasyprint',
            phpBinary: '/missing/php8.3',
        ),
    ));

    $this->get('/install/requirements')
        ->assertOk()
        ->assertSee('Non superato');

    $this->post('/install/requirements')
        ->assertRedirect()
        ->assertSessionHas('installation_error');

    expect($state->progress()->step)->toBe('runtime');
});

it('shows operational WeasyPrint installation instructions when automatic detection fails', function (): void {
    $inspection = (new InstallationRuntimeInspector(
        phpCandidatePaths: [PHP_BINARY],
        weasyPrintCandidatePaths: [],
    ))->inspect(base_path(), configuredWeasyPrintBinary: '/missing/weasyprint');

    $this->view('installation.runtime', [
        'inspection' => $inspection,
        'errors' => new ViewErrorBag,
    ])
        ->assertSee('WeasyPrint è obbligatorio')
        ->assertSee('sudo apt update')
        ->assertSee('sudo apt install -y weasyprint')
        ->assertSee('weasyprint --version')
        ->assertSee('Hosting condiviso, cPanel o Plesk')
        ->assertSee('LARAVEL_PDF_WEASYPRINT_BINARY')
        ->assertSee('Verifica nuovamente');
});

it('omits binary and application name fields and ignores a submitted application name', function (): void {
    $state = app(InstallationState::class);
    $progress = $state->progress();
    $state->save(new InstallationProgressData(
        installationId: $progress->installationId,
        step: 'configuration',
    ));

    $this->get('/install/configuration')
        ->assertOk()
        ->assertDontSee('name="application_name"', false)
        ->assertDontSee('name="weasyprint_binary"', false)
        ->assertDontSee('name="php_binary"', false)
        ->assertSee('value="sqlite"', false)
        ->assertSee('value="mysql"', false)
        ->assertDontSee('value="mariadb"', false)
        ->assertSee('SQLite')
        ->assertSee('MySQL / MariaDB')
        ->assertSee('href="'.route('installation.runtime').'"', false);

    $response = $this->post('/install/configuration', [
        'application_name' => 'Nome manipolato',
        'application_url' => rtrim(url('/'), '/'),
        'timezone' => 'Europe/Rome',
        'locale' => 'it',
        'backup_root' => storage_path('backups'),
        'database_driver' => 'mysql',
    ]);
    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toBe(url('/install/database'));

    $saved = $state->progress();
    expect($saved->application?->name)->toBe('AssestMe')
        ->and($saved->application?->phpBinary)->toBeString()->toStartWith(DIRECTORY_SEPARATOR)
        ->and($saved->application?->weasyPrintBinary)->toBeString()->toStartWith(DIRECTORY_SEPARATOR);

    $this->get('/install/database')
        ->assertOk()
        ->assertDontSee('name="dump_binary"', false)
        ->assertDontSee('name="restore_binary"', false)
        ->assertSee('href="'.route('installation.configuration').'"', false);
});

it('keeps a detected MariaDB server configuration when returning to application settings', function (): void {
    $state = app(InstallationState::class);
    $progress = $state->progress();
    $application = new ApplicationConfigurationData(
        name: 'AssestMe',
        url: rtrim(url('/'), '/'),
        timezone: 'Europe/Rome',
        locale: 'it',
        backupRoot: storage_path('backups'),
        weasyPrintBinary: '/usr/bin/weasyprint',
        phpBinary: PHP_BINARY,
    );
    $database = new DatabaseConfigurationData(
        driver: SupportedDatabaseDriver::MariaDb,
        database: 'assestme_mariadb',
        host: 'database.example.test',
        port: 3307,
        username: 'assestme_user',
        password: 'Encrypted state password',
    );
    $state->save(new InstallationProgressData(
        installationId: $progress->installationId,
        step: 'configuration',
        application: $application,
        database: $database,
    ));

    $this->get('/install/configuration')
        ->assertOk()
        ->assertSee('value="mysql" checked', false)
        ->assertDontSee('value="mariadb"', false)
        ->assertSee($application->url)
        ->assertSee($application->timezone)
        ->assertSee($application->backupRoot);

    $this->post('/install/configuration', [
        'application_url' => $application->url,
        'timezone' => $application->timezone,
        'locale' => $application->locale,
        'backup_root' => $application->backupRoot,
        'database_driver' => 'mysql',
    ])->assertRedirect('/install/database');

    $saved = $state->progress()->database;
    expect($saved?->driver)->toBe(SupportedDatabaseDriver::MariaDb)
        ->and($saved?->host)->toBe($database->host)
        ->and($saved?->port)->toBe($database->port)
        ->and($saved?->database)->toBe($database->database)
        ->and($saved?->username)->toBe($database->username);
});

it('renders the generic server database page and administrator back link', function (): void {
    $state = app(InstallationState::class);
    $progress = $state->progress();
    $application = new ApplicationConfigurationData(
        name: 'AssestMe',
        url: rtrim(url('/'), '/'),
        timezone: 'Europe/Rome',
        locale: 'it',
        backupRoot: storage_path('backups'),
        weasyPrintBinary: '/usr/bin/weasyprint',
        phpBinary: PHP_BINARY,
    );
    $database = new DatabaseConfigurationData(
        driver: SupportedDatabaseDriver::MariaDb,
        database: 'assestme_mariadb',
        host: 'database.example.test',
        port: 3307,
        username: 'assestme_user',
        password: 'Encrypted state password',
    );
    $state->save(new InstallationProgressData(
        installationId: $progress->installationId,
        step: 'database',
        application: $application,
        database: $database,
    ));

    $this->get('/install/database')
        ->assertOk()
        ->assertSee('Configura MySQL / MariaDB')
        ->assertSee('value="mysql"', false)
        ->assertDontSee('value="mariadb"', false)
        ->assertSee($database->host)
        ->assertSee((string) $database->port)
        ->assertSee($database->database)
        ->assertSee($database->username)
        ->assertDontSee($database->password)
        ->assertSee('href="'.route('installation.configuration').'"', false);

    $state->save(new InstallationProgressData(
        installationId: $progress->installationId,
        step: 'administrator',
        application: $application,
        database: $database,
    ));

    $this->get('/install/administrator')
        ->assertOk()
        ->assertSee('href="'.route('installation.database').'"', false);
});

it('renders panel-neutral scheduler instructions for CloudPanel cPanel Plesk and shell', function (): void {
    $php = (string) realpath(PHP_BINARY);
    $artisan = base_path('artisan');
    $command = $php.' '.$artisan.' schedule:run';

    $this->view('installation.complete', [
        'checks' => [],
        'scheduler' => app(SchedulerHeartbeat::class)->status(),
        'cronCommand' => $command,
        'errors' => new ViewErrorBag,
    ])
        ->assertSee('CloudPanel')
        ->assertSee('Sites → dominio → Cron Jobs → Add Cron Job')
        ->assertSee('cPanel')
        ->assertSee('Advanced → Cron Jobs → Add New Cron Job')
        ->assertSee('Plesk')
        ->assertSee('Websites &amp; Domains → Scheduled Tasks → Add Task', false)
        ->assertSee('Run a PHP script')
        ->assertSee('* * * * *')
        ->assertSee($command)
        ->assertSee('crontab -e')
        ->assertSee('* * * * * '.$command);
});

it('serves the generic hosting documentation through the installer middleware', function (): void {
    $this->get('/install/documentation/hosting')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');

    expect(file_get_contents(base_path('docs/hosting-installation.md')))
        ->toContain('Installazione AssestMe su hosting PHP')
        ->toContain('Shared hosting con document root fissa');
});

it('keeps the real sanitized final-check detail instead of replacing it with a generic message', function (): void {
    $method = new ReflectionMethod(InstallationFinalCheck::class, 'check');
    $check = $method->invoke(
        app(InstallationFinalCheck::class),
        'storage',
        'Storage privato',
        static fn (): never => throw new RuntimeException(
            'dump failed at --defaults-file=/tmp/.assestme-db-credentials-test password=do-not-expose',
        ),
    );

    expect($check['status'])->toBe('failed')
        ->and($check['detail'])->toContain('dump failed')
        ->and($check['detail'])->toContain('[temporary credentials file]')
        ->and($check['detail'])->toContain('password=[redacted]')
        ->and($check['detail'])->not->toContain('do-not-expose');
});

it('keeps final server backup checks pending when no matching dump client is available', function (SupportedDatabaseDriver $driver, string $message): void {
    $originalResolver = app(DatabaseClientBinaryResolver::class);
    $resolver = new DatabaseClientBinaryResolver(
        app(DatabaseDumpBinaryValidator::class),
        app(DatabaseRestoreBinaryValidator::class),
        [],
    );
    config()->set('assestme.backup.dump_binary');
    app()->instance(DatabaseClientBinaryResolver::class, $resolver);
    app()->forgetInstance(InstallationFinalCheck::class);

    try {
        $method = new ReflectionMethod(InstallationFinalCheck::class, 'backupChecks');
        $checks = $method->invoke(
            app(InstallationFinalCheck::class),
            new DatabaseConfigurationData(driver: $driver, database: 'assestme'),
        );

        expect($checks)->toHaveCount(2)
            ->and($checks[0]['status'])->toBe('pending')
            ->and($checks[1]['status'])->toBe('pending')
            ->and($checks[0]['detail'])->toBe($message)
            ->and((new InstallationFinalCheckResult($checks))->passed())->toBeTrue();
    } finally {
        app()->instance(DatabaseClientBinaryResolver::class, $originalResolver);
        app()->forgetInstance(InstallationFinalCheck::class);
    }
})->with([
    'MySQL' => [
        SupportedDatabaseDriver::MySql,
        'Backup database non ancora disponibile. Installare un client MySQL che fornisca mysqldump; AssestMe lo rileverà automaticamente.',
    ],
    'MariaDB' => [
        SupportedDatabaseDriver::MariaDb,
        'Backup database non ancora disponibile. Installare il pacchetto mariadb-client; AssestMe rileverà automaticamente mariadb-dump.',
    ],
]);

it('keeps a failed server backup pending but retains a failed SQLite final backup', function (): void {
    $root = dirname((string) config('assestme.installation.state_path'));
    $binaryDirectory = $root.'/client-bin';
    $binary = $binaryDirectory.'/mysqldump';
    $originalResolver = app(DatabaseClientBinaryResolver::class);
    $originalBackupRoot = config('assestme.backup.root');
    File::ensureDirectoryExists($binaryDirectory, 0700, true);
    File::put($binary, "#!/bin/sh\nprintf '%s\\n' 'mysqldump Ver 8.0.36 MySQL Community Server'\n");
    chmod($binary, 0700);
    config()->set('assestme.backup.root', public_path('installer-backups'));
    app()->instance(DatabaseClientBinaryResolver::class, new DatabaseClientBinaryResolver(
        app(DatabaseDumpBinaryValidator::class),
        app(DatabaseRestoreBinaryValidator::class),
        [$binaryDirectory],
    ));
    app()->forgetInstance(InstallationFinalCheck::class);

    try {
        $method = new ReflectionMethod(InstallationFinalCheck::class, 'backupChecks');
        $serverChecks = $method->invoke(
            app(InstallationFinalCheck::class),
            new DatabaseConfigurationData(driver: SupportedDatabaseDriver::MySql, database: 'assestme'),
        );
        $sqliteChecks = $method->invoke(
            app(InstallationFinalCheck::class),
            new DatabaseConfigurationData(driver: SupportedDatabaseDriver::Sqlite, database: database_path('database.sqlite')),
        );

        expect($serverChecks[0]['status'])->toBe('pending')
            ->and($serverChecks[0]['detail'])->toContain('Backup output must be outside the public directory, database, and private storage paths.')
            ->and((new InstallationFinalCheckResult($serverChecks))->passed())->toBeTrue()
            ->and($sqliteChecks[0]['status'])->toBe('failed')
            ->and((new InstallationFinalCheckResult($sqliteChecks))->passed())->toBeFalse();
    } finally {
        config()->set('assestme.backup.root', $originalBackupRoot);
        app()->instance(DatabaseClientBinaryResolver::class, $originalResolver);
        app()->forgetInstance(InstallationFinalCheck::class);
    }
});

it('rejects an SQLite path below public through the HTTP database step', function (): void {
    $state = app(InstallationState::class);
    $progress = $state->progress();
    $state->save(new InstallationProgressData(
        installationId: $progress->installationId,
        step: 'database',
        application: new ApplicationConfigurationData(
            name: 'AssestMe',
            url: 'https://localhost',
            timezone: 'Europe/Rome',
            locale: 'it',
            backupRoot: storage_path('backups'),
            weasyPrintBinary: '/usr/bin/weasyprint',
            phpBinary: '/usr/bin/php8.3',
        ),
        database: new DatabaseConfigurationData(
            driver: SupportedDatabaseDriver::Sqlite,
            database: storage_path('app/database/database.sqlite'),
        ),
    ));

    $this->post('/install/database', [
        'database_driver' => 'sqlite',
        'sqlite_path' => public_path('database.sqlite'),
    ])
        ->assertRedirect()
        ->assertSessionHas('installation_error', 'The SQLite database must not be stored under the public directory.');

    expect($state->progress()->step)->toBe('database');
});

it('makes every installer route return 404 after the definitive lock', function (): void {
    app(InstallationState::class)->createInstalledLock(SupportedDatabaseDriver::Sqlite);

    $this->get('/install')->assertNotFound();
    $this->get('/install/requirements')->assertNotFound();
    $this->post('/install/welcome')->assertNotFound();
    $this->post('/install/finalize')->assertNotFound();
    $this->get('/')->assertRedirect('/admin');
});

it('blocks a completed application schema with an administrator but no lock as anomalous', function (): void {
    $environmentPath = (string) config('assestme.installation.environment_path');
    (new Filesystem)->put($environmentPath, 'APP_ENV=production');
    User::factory()->create();

    $this->get('/install')
        ->assertStatus(409)
        ->assertSee('reinstallazione web è bloccata');
});
