<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SupportedDatabaseDriver;
use App\Models\User;
use App\Services\Installation\InstallationState;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class MarkInstallationCompleteCommand extends Command
{
    protected $signature = 'assestme:installation:lock {--force : Confirm creation non-interactively}';

    protected $description = 'Create the installed lock for an initialized local or testing instance';

    public function handle(InstallationState $installationState): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('This command is restricted to local and testing environments.');

            return self::FAILURE;
        }

        if ($installationState->isInstalled()) {
            $this->info('The AssestMe installation lock already exists.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && (! $this->input->isInteractive() || ! $this->confirm('Create the irreversible local installation lock?'))) {
            $this->error('Explicit confirmation is required.');

            return self::FAILURE;
        }

        $connection = DB::connection();
        $driver = SupportedDatabaseDriver::tryFrom($connection->getDriverName());
        $schema = Schema::connection($connection->getName());

        $administratorCountIsValid = app()->environment('testing')
            ? User::query()->count() <= 1
            : User::query()->count() === 1;

        if ($driver === null || ! $schema->hasTable('migrations') || ! $schema->hasTable('settings') || ! $administratorCountIsValid) {
            $this->error('The local database is not a complete AssestMe installation.');

            return self::FAILURE;
        }

        $installationState->createInstalledLock($driver);
        $this->info('The AssestMe installation lock was created.');

        return self::SUCCESS;
    }
}
