<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\GoogleDrive\GoogleDriveSyncService;
use Illuminate\Console\Command;

final class GoogleDriveSyncCommand extends Command
{
    protected $signature = 'assestme:google-drive-sync {--force : Ignora il confronto hash per gli assessment configurati}';

    protected $description = 'Sincronizza la copia leggibile degli assessment su Google Drive';

    public function handle(GoogleDriveSyncService $sync): int
    {
        $result = $sync->syncAll((bool) $this->option('force'));

        if ($result->lockUnavailable) {
            $this->warn(__('assestme.google_drive.command.locked'));
        }

        $this->line(__('assestme.google_drive.command.result', [
            'evaluated' => $result->evaluated,
            'skipped' => $result->skipped,
            'synchronized' => $result->synchronized,
            'failed' => $result->failed,
        ]));

        return $result->exitCode();
    }
}
