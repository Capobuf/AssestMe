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
use RuntimeException;
use SensitiveParameter;

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

        $this->databaseCapabilityProbe->probe($database);
        $classification = $this->databaseClassifier->classify($database, $progress->installationId);

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
        $this->runtimeConfigurator->apply($application, $database);
        $this->runArtisanCommand('migrate', ['--force' => true, '--isolated' => true]);
        $this->runArtisanCommand('db:seed', ['--force' => true]);
        $this->persistAdministrator($administrator);
        $this->databaseClassifier->markComplete($database, $progress->installationId);

        $result = $this->finalCheck->run($application, $database);

        if (! $result->passed()) {
            throw new RuntimeException('One or more final installation checks failed.');
        }

        $this->environmentWriter->activatePending($application, $database, $applicationKey);
        $this->runArtisanCommand('optimize:clear');

        if (app()->environment('production')) {
            $this->runArtisanCommand('optimize');
        }

        $this->installationState->createInstalledLock($database->driver);
        $this->installationState->removeProgress();
        BootstrapInstallationKey::removeAfterCompletion(
            base_path(),
            $applicationKey,
            $this->configuredAbsolutePath('assestme.installation.environment_path'),
            $this->configuredAbsolutePath('assestme.installation.lock_path'),
            $this->configuredAbsolutePath('assestme.installation.bootstrap_key_path'),
        );

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
        if (Artisan::call($command, $parameters) !== 0) {
            throw new RuntimeException("The {$command} command did not complete successfully.");
        }
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
