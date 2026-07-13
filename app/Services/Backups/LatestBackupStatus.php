<?php

declare(strict_types=1);

namespace App\Services\Backups;

use Carbon\CarbonImmutable;
use Illuminate\Filesystem\Filesystem;

final readonly class LatestBackupStatus
{
    public function __construct(private Filesystem $files) {}

    public function latestSuccessfulAt(): ?CarbonImmutable
    {
        $root = (string) config('assestme.backup.root');

        if (! $this->files->isDirectory($root)) {
            return null;
        }

        $latestTimestamp = null;

        foreach ($this->files->files($root) as $file) {
            if (preg_match('/^assestme-\d{8}-\d{6}(?:-\d+)?\.tar\.gz$/', $file->getFilename()) !== 1) {
                continue;
            }

            $timestamp = $file->getMTime();
            $latestTimestamp = $latestTimestamp === null ? $timestamp : max($latestTimestamp, $timestamp);
        }

        return $latestTimestamp === null
            ? null
            : CarbonImmutable::createFromTimestampUTC($latestTimestamp);
    }
}
