<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Backups\CreateBackup;
use Illuminate\Console\Command;
use Throwable;

final class BackupCommand extends Command
{
    protected $signature = 'assestme:backup {--output= : Absolute path for the .tar.gz archive}';

    protected $description = 'Create and verify a consistent AssestMe application backup';

    public function handle(CreateBackup $createBackup): int
    {
        $output = $this->option('output');

        try {
            $path = $createBackup->handle(is_string($output) && $output !== '' ? $output : null);
            $this->components->info("Backup created: {$path}");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
