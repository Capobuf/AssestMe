<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Backups\VerifyBackup;
use Illuminate\Console\Command;
use Throwable;

final class VerifyBackupCommand extends Command
{
    protected $signature = 'assestme:backup:verify {archive : Path to the backup archive}';

    protected $description = 'Verify an AssestMe backup manifest and every file hash';

    public function handle(VerifyBackup $verifyBackup): int
    {
        $archive = (string) $this->argument('archive');

        try {
            $manifest = $verifyBackup->handle($archive);
            $this->components->info(
                sprintf('Backup verified: %d files, created %s', count($manifest->files()), $manifest->createdAt),
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
