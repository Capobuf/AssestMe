<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Storage\AuditPrivateStorage;
use Illuminate\Console\Command;

final class StorageAuditCommand extends Command
{
    protected $signature = 'assestme:storage:audit';

    protected $description = 'Report orphaned, missing, corrupt, or incompletely deleted private files';

    public function handle(AuditPrivateStorage $audit): int
    {
        $result = $audit->handle();
        $this->line(__('assestme.storage.audit.result', [
            'orphans' => count($result->orphanFiles),
            'missing' => count($result->missingFiles),
            'mismatches' => count($result->hashMismatches),
            'operations' => count($result->unfinishedOperations),
        ]));

        foreach ([...$result->orphanFiles, ...$result->missingFiles, ...$result->hashMismatches, ...$result->unfinishedOperations] as $issue) {
            $this->warn($issue);
        }

        return $result->hasIssues() ? self::FAILURE : self::SUCCESS;
    }
}
