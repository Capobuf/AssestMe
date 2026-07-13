<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class DiagnoseCommand extends Command
{
    protected $signature = 'assestme:diagnose';

    protected $description = 'Verify the AssestMe runtime and SQLite invariants';

    public function handle(): int
    {
        $requiredExtensions = [
            'bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'filter', 'gd', 'iconv',
            'intl', 'libxml', 'mbstring', 'openssl', 'pdo', 'pdo_sqlite', 'session',
            'simplexml', 'tokenizer', 'xml', 'xmlreader', 'xmlwriter', 'zip', 'zlib',
        ];
        $missingExtensions = array_values(array_filter(
            $requiredExtensions,
            static fn (string $extension): bool => ! extension_loaded($extension),
        ));

        $foreignKeys = (int) DB::scalar('PRAGMA foreign_keys');
        $journalMode = strtolower((string) DB::scalar('PRAGMA journal_mode'));
        $busyTimeout = (int) DB::scalar('PRAGMA busy_timeout');
        $synchronous = (int) DB::scalar('PRAGMA synchronous');
        $transactionMode = strtoupper((string) config('database.connections.sqlite.transaction_mode'));

        $checks = [
            'php_8_3_or_newer' => version_compare(PHP_VERSION, '8.3.0', '>='),
            'memory_limit_512mb' => ini_parse_quantity((string) ini_get('memory_limit')) >= 512 * 1024 * 1024,
            'required_extensions' => $missingExtensions === [],
            'database_sqlite' => DB::getDriverName() === 'sqlite',
            'sqlite_foreign_keys' => $foreignKeys === 1,
            'sqlite_journal_mode_wal' => $journalMode === 'wal',
            'sqlite_busy_timeout_5000' => $busyTimeout === 5000,
            'sqlite_synchronous_normal' => $synchronous === 1,
            'sqlite_transaction_immediate' => $transactionMode === 'IMMEDIATE',
            'pdf_driver_dompdf' => config('laravel-pdf.driver') === 'dompdf',
            'single_administrator' => User::query()->count() <= 1,
            'storage_writable' => is_writable(storage_path()),
            'cache_writable' => is_writable(base_path('bootstrap/cache')),
        ];

        $result = [
            'ok' => ! in_array(false, $checks, true),
            'checks' => $checks,
            'details' => [
                'php' => PHP_VERSION,
                'memory_limit' => (string) ini_get('memory_limit'),
                'missing_extensions' => $missingExtensions,
                'journal_mode' => $journalMode,
                'busy_timeout' => $busyTimeout,
                'synchronous' => $synchronous,
                'transaction_mode' => $transactionMode,
            ],
        ];

        $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
