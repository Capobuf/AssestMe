<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Backups\VerifyBackup;
use App\Services\Backups\BackupArchiveCatalog;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

final class DownloadBackupController
{
    public function __invoke(
        string $archive,
        BackupArchiveCatalog $catalog,
        VerifyBackup $verifyBackup,
    ): BinaryFileResponse|Response {
        try {
            $path = $catalog->resolveManagedArchive($archive);
        } catch (InvalidArgumentException|RuntimeException) {
            abort(404, __('assestme.backups.errors.not_found'));
        }

        try {
            $verifyBackup->handle($path);
        } catch (Throwable $exception) {
            Log::warning('Backup download verification failed.', [
                'archive' => $archive,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return response(
                __('assestme.backups.errors.corrupt'),
                409,
                ['Content-Type' => 'text/plain; charset=UTF-8'],
            );
        }

        $response = response()->download($path, $archive, [
            'Content-Type' => 'application/gzip',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
