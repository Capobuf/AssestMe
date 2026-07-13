<?php

declare(strict_types=1);

namespace App\Actions\Backups;

use Carbon\CarbonImmutable;
use Illuminate\Filesystem\Filesystem;
use SplFileInfo;

final readonly class PruneBackups
{
    public function __construct(private Filesystem $files) {}

    public function handle(string $directory): int
    {
        if (! $this->files->isDirectory($directory)) {
            return 0;
        }

        $backups = collect($this->files->files($directory))
            ->filter(static fn (SplFileInfo $file): bool => preg_match(
                '/^assestme-(\d{8})-(\d{6})(?:-\d+)?\.tar\.gz$/',
                $file->getFilename(),
            ) === 1)
            ->sortByDesc(static fn (SplFileInfo $file): string => $file->getFilename())
            ->values();

        $keep = [];
        $daily = [];
        $weekly = [];
        $monthly = [];
        $dailyLimit = (int) config('assestme.backup.retention.daily', 7);
        $weeklyLimit = (int) config('assestme.backup.retention.weekly', 4);
        $monthlyLimit = (int) config('assestme.backup.retention.monthly', 6);

        foreach ($backups as $backup) {
            $date = $this->dateFromFilename($backup->getFilename());

            if ($date === null) {
                continue;
            }

            $dailyKey = $date->format('Y-m-d');
            $weeklyKey = $date->format('o-W');
            $monthlyKey = $date->format('Y-m');

            if (count($daily) < $dailyLimit && ! isset($daily[$dailyKey])) {
                $daily[$dailyKey] = true;
                $keep[$backup->getPathname()] = true;
            }

            if (count($weekly) < $weeklyLimit && ! isset($weekly[$weeklyKey])) {
                $weekly[$weeklyKey] = true;
                $keep[$backup->getPathname()] = true;
            }

            if (count($monthly) < $monthlyLimit && ! isset($monthly[$monthlyKey])) {
                $monthly[$monthlyKey] = true;
                $keep[$backup->getPathname()] = true;
            }
        }

        $deleted = 0;

        foreach ($backups as $backup) {
            if (! isset($keep[$backup->getPathname()]) && $this->files->delete($backup->getPathname())) {
                $deleted++;
            }
        }

        return $deleted;
    }

    private function dateFromFilename(string $filename): ?CarbonImmutable
    {
        if (preg_match('/^assestme-(\d{8})-(\d{6})/', $filename, $matches) !== 1) {
            return null;
        }

        return CarbonImmutable::createFromFormat('!Ymd-His', $matches[1].'-'.$matches[2]) ?: null;
    }
}
