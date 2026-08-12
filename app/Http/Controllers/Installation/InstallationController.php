<?php

declare(strict_types=1);

namespace App\Http\Controllers\Installation;

use App\Actions\Installation\FinalizeInstallation;
use App\Data\Installation\DatabaseConfigurationData;
use App\Data\Installation\InstallationDatabaseStatus;
use App\Data\Installation\InstallationProgressData;
use App\Enums\SupportedDatabaseDriver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Installation\AdministratorRequest;
use App\Http\Requests\Installation\ApplicationConfigurationRequest;
use App\Http\Requests\Installation\DatabaseConfigurationRequest;
use App\Services\Installation\DatabaseCapabilityProbe;
use App\Services\Installation\DatabaseCapabilityProbeException;
use App\Services\Installation\InstallationDatabaseClassificationException;
use App\Services\Installation\InstallationDatabaseClassifier;
use App\Services\Installation\InstallationRuntimeInspector;
use App\Services\Installation\InstallationState;
use App\Services\Installation\SchedulerHeartbeat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

final class InstallationController extends Controller
{
    public function __construct(
        private readonly InstallationState $installationState,
        private readonly InstallationRuntimeInspector $runtimeInspector,
        private readonly DatabaseCapabilityProbe $databaseCapabilityProbe,
        private readonly InstallationDatabaseClassifier $databaseClassifier,
        private readonly SchedulerHeartbeat $schedulerHeartbeat,
    ) {}

    public function welcome(Request $request): View|RedirectResponse
    {
        $progress = $this->installationState->progress();

        if ($progress->step !== 'welcome') {
            return redirect()->route($this->routeForStep($progress->step));
        }

        return view('installation.welcome', [
            'domain' => $request->getHost(),
            'detectedUrl' => $this->detectedUrl($request),
        ]);
    }

    public function continueFromWelcome(): RedirectResponse
    {
        $progress = $this->installationState->progress();
        $this->installationState->save(new InstallationProgressData(
            installationId: $progress->installationId,
            step: 'runtime',
            application: $progress->application,
            database: $progress->database,
        ));

        return redirect()->route('installation.runtime');
    }

    public function runtime(): View
    {
        $inspection = $this->runtimeInspector->inspect(base_path());

        return view('installation.runtime', compact('inspection'));
    }

    public function continueFromRuntime(Request $request): RedirectResponse
    {
        $progress = $this->installationState->progress();
        $inspection = $this->runtimeInspector->inspect(base_path());

        if ($request->boolean('refresh')) {
            return redirect()->route('installation.runtime');
        }

        if (! $inspection->passed()) {
            return back()->with('installation_error', 'I requisiti runtime non sono ancora soddisfatti.');
        }

        $this->installationState->save(new InstallationProgressData(
            installationId: $progress->installationId,
            step: 'configuration',
            application: $progress->application,
            database: $progress->database,
        ));

        return redirect()->route('installation.configuration');
    }

    public function configuration(Request $request): View
    {
        $progress = $this->installationState->progress();

        return view('installation.configuration', [
            'application' => $progress->application,
            'database' => $progress->database,
            'detectedUrl' => $this->detectedUrl($request),
        ]);
    }

    public function storeConfiguration(ApplicationConfigurationRequest $request): RedirectResponse
    {
        $progress = $this->installationState->progress();
        $inspection = $this->runtimeInspector->inspect(base_path());

        if (! $inspection->passed()
            || $inspection->phpBinary === null
            || $inspection->weasyPrintBinary === null) {
            return back()->withInput()->with('installation_error', 'PHP CLI, WeasyPrint o filesystem non superano il controllo reale.');
        }

        $application = $request->toData($inspection->weasyPrintBinary, $inspection->phpBinary);

        try {
            $this->assertBackupRoot($application->backupRoot);
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('installation_error', $exception->getMessage());
        }

        $requestedDriver = SupportedDatabaseDriver::from((string) $request->validated('database_driver'));
        $currentDatabase = $progress->database;
        $sameDatabaseFamily = $currentDatabase !== null
            && (
                ($requestedDriver === SupportedDatabaseDriver::Sqlite
                    && $currentDatabase->driver === SupportedDatabaseDriver::Sqlite)
                || ($requestedDriver === SupportedDatabaseDriver::MySql
                    && $currentDatabase->driver !== SupportedDatabaseDriver::Sqlite)
            );
        $database = $sameDatabaseFamily
            ? $currentDatabase
            : $this->defaultDatabaseConfiguration($requestedDriver);

        $this->installationState->save(new InstallationProgressData(
            installationId: $progress->installationId,
            step: 'database',
            application: $application,
            database: $database,
        ));

        return redirect()->route('installation.database');
    }

    public function database(): View|RedirectResponse
    {
        $progress = $this->installationState->progress();

        if ($progress->application === null || $progress->database === null) {
            return redirect()->route('installation.configuration');
        }

        return view('installation.database', ['database' => $progress->database]);
    }

    public function storeDatabase(DatabaseConfigurationRequest $request): RedirectResponse
    {
        $progress = $this->installationState->progress();
        $database = $request->toData();
        $reinitialized = false;

        $sameDatabaseFamily = $progress->database !== null
            && (
                ($progress->database->driver === SupportedDatabaseDriver::Sqlite
                    && $database->driver === SupportedDatabaseDriver::Sqlite)
                || ($progress->database->driver !== SupportedDatabaseDriver::Sqlite
                    && $database->driver !== SupportedDatabaseDriver::Sqlite)
            );

        if ($database->password === ''
            && $sameDatabaseFamily
            && $progress->database->database === $database->database
            && $progress->database->host === $database->host
            && $progress->database->username === $database->username) {
            $database = new DatabaseConfigurationData(
                driver: $database->driver,
                database: $database->database,
                host: $database->host,
                port: $database->port,
                username: $database->username,
                password: $progress->database->password,
                socket: $database->socket,
                charset: $database->charset,
                collation: $database->collation,
            );
        }

        try {
            $probe = $this->databaseCapabilityProbe->probe($database);

            if ($database->driver !== SupportedDatabaseDriver::Sqlite
                && $database->driver !== $probe->driver) {
                $database = new DatabaseConfigurationData(
                    driver: $probe->driver,
                    database: $database->database,
                    host: $database->host,
                    port: $database->port,
                    username: $database->username,
                    password: $database->password,
                    socket: $database->socket,
                    charset: $database->charset,
                    collation: $database->collation,
                );
            }

            $classification = $this->databaseClassifier->classify($database, $progress->installationId);

            if ($classification->status === InstallationDatabaseStatus::Foreign) {
                if (! $request->confirmsDatabaseReinitialization()) {
                    return back()
                        ->withInput($request->except('database_password'))
                        ->with('installation_error', 'Il database contiene tabelle applicative esistenti: '.implode(', ', $classification->tables).'.')
                        ->with('installation_database_reset_tables', $classification->tables);
                }

                $this->databaseClassifier->reinitialize($database);
                $reinitialized = true;
                $probe = $this->databaseCapabilityProbe->probe($database);
                $classification = $this->databaseClassifier->classify($database, $progress->installationId);

                if ($classification->status !== InstallationDatabaseStatus::Empty) {
                    throw new InstallationDatabaseClassificationException(
                        'Il database non risulta vuoto dopo la re-inizializzazione.',
                    );
                }
            }
        } catch (DatabaseCapabilityProbeException|InstallationDatabaseClassificationException|RuntimeException $exception) {
            report($exception);

            return back()
                ->withInput($request->except('database_password'))
                ->with('installation_error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput($request->except('database_password'))
                ->with('installation_error', 'La verifica reale del database non è stata superata.');
        }

        $this->installationState->save(new InstallationProgressData(
            installationId: $progress->installationId,
            step: 'administrator',
            application: $progress->application,
            database: $database,
        ));
        $request->session()->forget('installation_database_reset_tables');

        return redirect()->route('installation.administrator')
            ->with(
                'installation_success',
                "{$probe->product} {$probe->serverVersion} verificato."
                .($reinitialized ? ' Database re-inizializzato.' : ''),
            );
    }

    public function administrator(): View|RedirectResponse
    {
        $progress = $this->installationState->progress();

        if ($progress->application === null || $progress->database === null) {
            return redirect()->route('installation.configuration');
        }

        return view('installation.administrator');
    }

    public function finalize(
        AdministratorRequest $request,
        FinalizeInstallation $finalizeInstallation,
    ): View|RedirectResponse {
        try {
            $progress = $this->installationState->progress();
            $result = $finalizeInstallation($request->toData());

            if ($progress->application === null) {
                throw new RuntimeException('The final installation configuration is incomplete.');
            }

            $scheduler = $this->schedulerHeartbeat->status();
            $cronCommand = $this->shellArgument($progress->application->phpBinary)
                .' '.$this->shellArgument(base_path('artisan')).' schedule:run';

            return view('installation.complete', [
                'checks' => $result->checks,
                'scheduler' => $scheduler,
                'cronCommand' => $cronCommand,
            ]);
        } catch (DatabaseCapabilityProbeException|InstallationDatabaseClassificationException|RuntimeException $exception) {
            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->with('installation_error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->with('installation_error', 'La finalizzazione non è riuscita. Puoi riprenderla in sicurezza senza cancellare il database.');
        }
    }

    public function documentation(): BinaryFileResponse
    {
        $path = base_path('docs/how-to/hosting-installation.md');
        abort_unless(is_file($path), 404);

        return response()->file($path, ['Content-Type' => 'text/markdown; charset=UTF-8']);
    }

    private function detectedUrl(Request $request): string
    {
        return $request->getScheme().'://'.$request->getHttpHost();
    }

    private function routeForStep(string $step): string
    {
        return match ($step) {
            'runtime' => 'installation.runtime',
            'configuration' => 'installation.configuration',
            'database' => 'installation.database',
            'administrator' => 'installation.administrator',
            default => 'installation.welcome',
        };
    }

    private function defaultDatabaseConfiguration(SupportedDatabaseDriver $driver): DatabaseConfigurationData
    {
        if ($driver === SupportedDatabaseDriver::Sqlite) {
            return new DatabaseConfigurationData(
                driver: $driver,
                database: storage_path('app/database/database.sqlite'),
            );
        }

        return new DatabaseConfigurationData(
            driver: $driver,
            database: 'assestme',
        );
    }

    private function assertBackupRoot(string $path): void
    {
        if (is_link($path) || is_link(dirname($path))) {
            throw new RuntimeException('La directory backup non può essere un collegamento simbolico.');
        }

        if (! is_dir($path) && ! @mkdir($path, 0750, true) && ! is_dir($path)) {
            throw new RuntimeException('La directory backup non può essere creata.');
        }

        $realPath = realpath($path);
        $publicPath = realpath(public_path());

        if (! is_string($realPath)
            || ! is_writable($realPath)
            || (is_string($publicPath) && ($realPath === $publicPath || str_starts_with($realPath, $publicPath.DIRECTORY_SEPARATOR)))) {
            throw new RuntimeException('La directory backup deve essere scrivibile e fuori da public.');
        }
    }

    private function shellArgument(string $argument): string
    {
        return preg_match('/^[A-Za-z0-9_\/.:-]+$/', $argument) === 1
            ? $argument
            : "'".str_replace("'", "'\\''", $argument)."'";
    }
}
