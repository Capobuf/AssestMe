<?php

declare(strict_types=1);

namespace App\Actions\Backups;

use App\Services\Backups\BackupArchiveCatalog;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

final readonly class DeleteBackup
{
    public function __construct(
        private BackupArchiveCatalog $catalog,
        private Filesystem $files,
    ) {}

    public function __invoke(string $name): void
    {
        $path = $this->catalog->resolveManagedArchive($name);

        if (! $this->files->delete($path) || $this->files->exists($path)) {
            throw new RuntimeException('The managed backup archive could not be deleted.');
        }
    }
}
