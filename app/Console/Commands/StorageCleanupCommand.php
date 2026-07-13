<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Storage\CleanupDeletionOperations;
use Illuminate\Console\Command;
use Throwable;

final class StorageCleanupCommand extends Command
{
    protected $signature = 'assestme:storage:cleanup {--limit=100 : Maximum operations to retry}';

    protected $description = 'Retry committed AssestMe deletion cleanup operations';

    public function handle(CleanupDeletionOperations $cleanup): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);

        if (! is_int($limit)) {
            $this->components->error(__('assestme.storage.cleanup.invalid_limit'));

            return self::FAILURE;
        }

        try {
            $result = $cleanup->handle($limit);
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(__('assestme.storage.cleanup.result', [
            'processed' => $result->processed,
            'cleaned' => $result->cleaned,
            'failed' => $result->failed,
        ]));

        return $result->failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
