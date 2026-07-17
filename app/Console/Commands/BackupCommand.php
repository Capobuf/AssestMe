<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Backups\CreateBackup;
use App\Actions\Operations\RecordOperationalCheck;
use App\Enums\OperationalCheckStatus;
use App\Enums\OperationalCheckType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

final class BackupCommand extends Command
{
    protected $signature = 'assestme:backup {--output= : Absolute path for the .tar.gz archive}';

    protected $description = 'Create and verify a consistent AssestMe application backup';

    public function handle(
        CreateBackup $createBackup,
        RecordOperationalCheck $recordOperationalCheck,
    ): int {
        $output = $this->option('output');

        try {
            $path = $createBackup->handle(is_string($output) && $output !== '' ? $output : null);
            $recordOperationalCheck(
                OperationalCheckType::Backup,
                OperationalCheckStatus::Succeeded,
            );
            $this->components->info("Backup created: {$path}");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::error('Application backup failed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            try {
                $recordOperationalCheck(
                    OperationalCheckType::Backup,
                    OperationalCheckStatus::Failed,
                    $exception->getMessage(),
                );
            } catch (Throwable $recordingException) {
                Log::critical('Backup failure status could not be persisted.', [
                    'exception' => $recordingException::class,
                    'message' => $recordingException->getMessage(),
                    'backup_exception' => $exception::class,
                ]);
            }

            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
