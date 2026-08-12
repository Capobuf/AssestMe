<?php

declare(strict_types=1);

namespace App\Actions\Installation;

use App\Data\Installation\AdministratorData;
use App\Data\Installation\InstallationDatabaseStatus;
use App\Data\Installation\InstallationFinalCheckResult;
use App\Models\User;
use App\Services\Installation\DatabaseCapabilityProbe;
use App\Services\Installation\InstallationDatabaseClassifier;
use App\Services\Installation\InstallationEnvironmentWriter;
use App\Services\Installation\InstallationFinalCheck;
use App\Services\Installation\InstallationRuntimeConfigurator;
use App\Services\Installation\InstallationState;
use App\Support\Installation\BootstrapInstallationKey;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use SensitiveParameter;
use Throwable;

final readonly class FinalizeInstallation
{
    public function __construct(
        private InstallationState $installationState,
        private DatabaseCapabilityProbe $databaseCapabilityProbe,
        private InstallationDatabaseClassifier $databaseClassifier,
        private InstallationEnvironmentWriter $environmentWriter,
        private InstallationRuntimeConfigurator $runtimeConfigurator,
        private CreateSingletonAdministrator $createAdministrator,
        private InstallationFinalCheck $finalCheck,
    ) {}

    public function __invoke(
        #[SensitiveParameter] AdministratorData $administrator,
    ): InstallationFinalCheckResult {
        Log::info('installer.finalize.begin');

        $lockPath = config('assestme.installation.finalization_lock_path');

        if (! is_string($lockPath) || ! str_starts_with($lockPath, DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('The installer finalization lock path must be absolute.');
        }

        $this->ensurePrivateDirectory(dirname($lockPath));

        if (is_link($lockPath)) {
            throw new RuntimeException('The installer finalization lock must not be a symbolic link.');
        }

        $lock = @fopen($lockPath, 'c+b');

        if ($lock === false || ! @chmod($lockPath, 0600)) {
            throw new RuntimeException('The installer finalization lock could not be opened securely.');
        }

        try {
            if (! flock($lock, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('Another installation finalization is already running.');
            }

            return $this->finalize($administrator);
        } finally {
            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    private function finalize(
        #[SensitiveParameter] AdministratorData $administrator,
    ): InstallationFinalCheckResult {
        if ($this->installationState->isInstalled()) {
            throw new RuntimeException('AssestMe is already installed.');
        }

        $progress = $this->installationState->progress();
        $application = $progress->application;
        $database = $progress->database;

        if ($application === null || $database === null || $progress->step !== 'administrator') {
            throw new RuntimeException('The installer progress state is incomplete.');
        }

        $applicationKey = config('app.key');

        if (! is_string($applicationKey) || ! BootstrapInstallationKey::isValidKey($applicationKey)) {
            throw new RuntimeException('The installer application key is invalid.');
        }

        Log::info('installer.finalize.database_probe.begin');
        $this->databaseCapabilityProbe->probe($database);
        Log::info('installer.finalize.database_probe.complete');

        $classification = $this->databaseClassifier->classify($database, $progress->installationId);
        Log::info('installer.finalize.classify.complete');

        if ($classification->status === InstallationDatabaseStatus::Foreign) {
            $tables = implode(', ', $classification->tables);

            throw new RuntimeException(
                'The selected database contains foreign application tables'.($tables !== '' ? ": {$tables}" : '').'.',
            );
        }

        if ($classification->status === InstallationDatabaseStatus::Empty) {
            $this->databaseClassifier->markPartial($database, $progress->installationId);
        }

        $this->environmentWriter->writePending($application, $database, $applicationKey);
        Log::info('installer.finalize.environment_pending.complete');

        $this->runtimeConfigurator->apply($application, $database);
        Log::info('installer.finalize.runtime_config.complete');

        Log::info('installer.finalize.migrate.begin');
        $this->runArtisanCommand('migrate', ['--force' => true, '--isolated' => true]);
        Log::info('installer.finalize.migrate.complete');

        Log::info('installer.finalize.seed.begin');
        $this->runArtisanCommand('db:seed', ['--force' => true]);
        Log::info('installer.finalize.seed.complete');

        $this->persistAdministrator($administrator);
        Log::info('installer.finalize.administrator.complete');

        $this->databaseClassifier->markComplete($database, $progress->installationId);
        Log::info('installer.finalize.database_mark_complete.complete');

        Log::info('installer.finalize.final_checks.begin');
        $result = $this->finalCheck->run($application, $database);
        Log::info('installer.finalize.final_checks.complete');

        if (! $result->passed()) {
            $failedLabels = array_map(
                static fn (array $check): string => $check['label'],
                array_filter(
                    $result->checks,
                    static fn (array $check): bool => $check['status'] === 'failed',
                ),
            );

            throw new RuntimeException('Controlli finali non superati: '.implode('; ', $failedLabels).'.');
        }

        Log::info('installer.finalize.environment_activate.begin');
        $this->environmentWriter->activatePending($application, $database, $applicationKey);
        Log::info('installer.finalize.environment_activate.complete');

        Log::info('installer.finalize.optimize_clear.begin');
        $this->runArtisanCommand('optimize:clear');
        Log::info('installer.finalize.optimize_clear.complete');

        if (app()->environment('production')) {
            $this->runArtisanCommand('optimize');
        }

        $this->installationState->createInstalledLock($database->driver);
        Log::info('installer.finalize.installed_lock.complete');

        $this->installationState->removeProgress();
        Log::info('installer.finalize.progress_removed');

        BootstrapInstallationKey::removeAfterCompletion(
            base_path(),
            $applicationKey,
            $this->configuredAbsolutePath('assestme.installation.environment_path'),
            $this->configuredAbsolutePath('assestme.installation.lock_path'),
            $this->configuredAbsolutePath('assestme.installation.bootstrap_key_path'),
        );
        Log::info('installer.finalize.bootstrap_key_removed');
        Log::info('installer.finalize.complete');

        return $result;
    }

    private function persistAdministrator(
        #[SensitiveParameter] AdministratorData $administrator,
    ): void {
        $existing = User::query()->orderBy('id')->get();

        if ($existing->count() > 1) {
            throw new RuntimeException('AssestMe supports exactly one administrator.');
        }

        $user = $existing->first();

        if ($user instanceof User) {
            $this->createAdministrator->replace($user, $administrator);

            return;
        }

        ($this->createAdministrator)($administrator);
    }

    /** @param array<string, bool|int|string> $parameters */
    private function runArtisanCommand(string $command, array $parameters = []): void
    {
        Log::info('installer.finalize.artisan.begin', ['command' => $command]);

        try {
            $exitCode = Artisan::call($command, $parameters);
        } catch (Throwable $exception) {
            Log::error('installer.finalize.artisan.failure', [
                'command' => $command,
                'exception' => $exception::class,
            ]);

            throw $exception;
        }

        if ($exitCode !== 0) {
            Log::error('installer.finalize.artisan.failure', [
                'command' => $command,
                'exit_code' => $exitCode,
            ]);

            throw new RuntimeException("The {$command} command did not complete successfully.");
        }

        Log::info('installer.finalize.artisan.complete', [
            'command' => $command,
            'exit_code' => $exitCode,
        ]);
    }

    private function ensurePrivateDirectory(string $directory): void
    {
        if (is_link($directory)) {
            throw new RuntimeException('The installer lock directory must not be a symbolic link.');
        }

        if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('The installer lock directory could not be created.');
        }

        if (! is_writable($directory) || ! @chmod($directory, 0700)) {
            throw new RuntimeException('The installer lock directory is not writable securely.');
        }
    }

    private function configuredAbsolutePath(string $key): string
    {
        $path = config($key);

        if (! is_string($path) || ! str_starts_with($path, DIRECTORY_SEPARATOR)) {
            throw new RuntimeException("{$key} must be an absolute path.");
        }

        return $path;
    }
}
