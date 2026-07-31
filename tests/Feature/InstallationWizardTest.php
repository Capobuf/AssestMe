<?php

declare(strict_types=1);

use App\Data\Installation\ApplicationConfigurationData;
use App\Data\Installation\DatabaseConfigurationData;
use App\Data\Installation\InstallationProgressData;
use App\Enums\SupportedDatabaseDriver;
use App\Models\User;
use App\Services\Installation\InstallationState;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

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
        ->assertSee('MySQL')
        ->assertSee('MariaDB');

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
