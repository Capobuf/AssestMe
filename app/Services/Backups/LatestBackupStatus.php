<?php

declare(strict_types=1);

namespace App\Services\Backups;

use App\Data\Backups\BackupArchiveData;
use Carbon\CarbonImmutable;

final readonly class LatestBackupStatus
{
    public function __construct(private BackupArchiveCatalog $catalog) {}

    public function latestSuccessfulAt(): ?CarbonImmutable
    {
        $latest = collect($this->catalog->all())
            ->first(static fn (BackupArchiveData $archive): bool => $archive->kind === 'backup');

        return $latest?->modifiedAt;
    }
}
