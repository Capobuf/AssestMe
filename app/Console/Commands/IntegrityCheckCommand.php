<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Operations\CheckDatabaseIntegrity;
use App\Actions\Operations\RecordOperationalCheck;
use App\Enums\OperationalCheckStatus;
use App\Enums\OperationalCheckType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

final class IntegrityCheckCommand extends Command
{
    protected $signature = 'assestme:integrity-check';

    public function __construct()
    {
        parent::__construct();

        $this->setDescription(__('assestme.operations.integrity_command_description'));
    }

    public function handle(
        CheckDatabaseIntegrity $checkDatabaseIntegrity,
        RecordOperationalCheck $recordOperationalCheck,
    ): int {
        try {
            $checkDatabaseIntegrity();
            $recordOperationalCheck(
                OperationalCheckType::DatabaseIntegrity,
                OperationalCheckStatus::Succeeded,
            );
            $this->components->info(__('assestme.operations.integrity_passed'));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->recordFailure($recordOperationalCheck, $exception);
            $this->components->error(__('assestme.operations.integrity_failed', [
                'reason' => $exception->getMessage(),
            ]));

            return self::FAILURE;
        }
    }

    private function recordFailure(RecordOperationalCheck $recordOperationalCheck, Throwable $exception): void
    {
        Log::error('Scheduled SQLite integrity check failed.', [
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);

        try {
            $recordOperationalCheck(
                OperationalCheckType::DatabaseIntegrity,
                OperationalCheckStatus::Failed,
                $exception->getMessage(),
            );
        } catch (Throwable $recordingException) {
            Log::critical('SQLite integrity failure status could not be persisted.', [
                'exception' => $recordingException::class,
                'message' => $recordingException->getMessage(),
                'integrity_exception' => $exception::class,
            ]);
        }
    }
}
