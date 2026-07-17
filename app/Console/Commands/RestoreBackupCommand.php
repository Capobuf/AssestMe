<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Backups\RestoreBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

final class RestoreBackupCommand extends Command
{
    protected $signature = 'assestme:restore-backup {archive : Path to the backup archive}';

    protected $description = 'Restore the AssestMe database and private storage from a verified backup';

    public function handle(RestoreBackup $restoreBackup): int
    {
        if (! app()->isDownForMaintenance()) {
            $this->components->error('Enable maintenance mode with `php artisan down` before restoring a backup.');

            return self::FAILURE;
        }

        try {
            $safetyBackup = $restoreBackup((string) $this->argument('archive'));
            $diagnosticExit = Artisan::call('assestme:diagnose');
            $this->output->write(Artisan::output());

            if ($diagnosticExit !== self::SUCCESS) {
                $this->components->error("Restore completed, but diagnostics failed. Safety backup: {$safetyBackup}");

                return self::FAILURE;
            }

            $this->components->info("Backup restored. Safety backup: {$safetyBackup}");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
