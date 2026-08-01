<?php

declare(strict_types=1);

namespace App\Services\Database\Snapshot;

use App\Services\Database\DatabaseClientOptionFile;
use App\Services\Database\DatabaseClientProcessEnvironment;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

trait StreamsSecureDatabaseDump
{
    private string $validatedDumpBinary = '';

    public function setValidatedDumpBinary(string $path): static
    {
        $this->validatedDumpBinary = $path;

        return $this;
    }

    public function dumpToFile(string $dumpFile): void
    {
        if ($this->validatedDumpBinary === ''
            || $this->dbName === ''
            || $this->userName === ''
            || ($this->host === '' && $this->socket === '')
            || $this->timeout <= 0) {
            throw new RuntimeException('Database dump configuration is incomplete.');
        }

        $optionFile = new DatabaseClientOptionFile;
        $credentialsPath = $optionFile->create(
            dirname($dumpFile),
            $this->host,
            $this->port,
            $this->userName,
            $this->password,
            $this->socket,
        );
        $outputHandle = false;
        $outputCreated = false;
        $dumpFailed = false;
        $failureDetail = null;
        $failurePhase = 'preparing_output';
        $exitCode = null;

        try {
            $outputHandle = fopen($dumpFile, 'xb');

            if ($outputHandle === false) {
                throw new RuntimeException('Database dump output could not be created.');
            }

            $outputCreated = true;

            if (! chmod($dumpFile, 0600)) {
                throw new RuntimeException('Database dump output permissions could not be restricted.');
            }

            $failurePhase = 'starting_process';
            $process = new Process(
                $this->dumpCommand($credentialsPath),
                env: (new DatabaseClientProcessEnvironment)->forDriver(
                    $this->databaseClientDriver(),
                    $credentialsPath,
                ),
                timeout: $this->timeout,
            );
            $process->start();

            $failurePhase = 'streaming_output';
            $processErrorOutput = '';
            foreach ($process as $type => $contents) {
                if ($type === Process::OUT) {
                    $this->writeAll($outputHandle, $contents);
                } elseif (strlen($processErrorOutput) < 4096) {
                    $processErrorOutput .= substr($contents, 0, 4096 - strlen($processErrorOutput));
                }
            }

            $exitCode = $process->getExitCode();

            if (! $process->isSuccessful()) {
                $failurePhase = 'process_exit';
                $failureDetail = trim($processErrorOutput);

                if ($failureDetail === '') {
                    $failureDetail = 'Database dump process failed.';
                }

                throw new RuntimeException($failureDetail);
            }

            if (! fflush($outputHandle)) {
                $failurePhase = 'flushing_output';

                throw new RuntimeException('Database dump output could not be flushed.');
            }
        } catch (Throwable $exception) {
            $dumpFailed = true;
            $failureDetail ??= $exception->getMessage();
        } finally {
            if (is_resource($outputHandle) && ! fclose($outputHandle)) {
                $dumpFailed = true;
                $failurePhase = 'closing_output';
                $failureDetail ??= 'Database dump output could not be closed.';
            }
        }

        $credentialsCleanupFailed = ! $optionFile->delete($credentialsPath);
        clearstatcache(true, $dumpFile);

        if ($dumpFailed
            || $credentialsCleanupFailed
            || ! is_file($dumpFile)
            || filesize($dumpFile) === 0) {
            if ($outputCreated && is_file($dumpFile)) {
                unlink($dumpFile);
            }

            $failureDetail ??= $credentialsCleanupFailed
                ? 'Temporary database credentials could not be removed securely.'
                : 'Database dump could not be created securely.';
            $failureDetail = $this->sanitizeFailureDetail($failureDetail, $credentialsPath);

            Log::warning('Database dump failed.', [
                'driver' => $this->databaseClientDriver(),
                'client_type' => $this->databaseClientDriver(),
                'exit_code' => $exitCode,
                'phase' => $failurePhase,
                'message' => $failureDetail,
            ]);

            $product = $this->databaseClientDriver() === 'mariadb' ? 'MariaDB' : 'MySQL';

            throw new RuntimeException("Backup {$product} non riuscito: {$failureDetail}");
        }
    }

    /** @return list<string> */
    private function dumpCommand(string $credentialsPath): array
    {
        if ($this->compressor !== null
            || $this->appendMode
            || $this->skipAutoIncrement
            || $this->extraOptions !== []
            || $this->extraOptionsAfterDbName !== []) {
            throw new RuntimeException('Unsupported database dump options were configured.');
        }

        $command = [
            $this->validatedDumpBinary,
            ...$this->credentialsFileArguments($credentialsPath),
        ];

        if (! $this->createTables) {
            $command[] = '--no-create-info';
        }

        if (! $this->includeData) {
            $command[] = '--no-data';
        }

        if ($this->skipComments) {
            $command[] = '--skip-comments';
        }

        $command[] = $this->useExtendedInserts ? '--extended-insert' : '--skip-extended-insert';

        if ($this->useSingleTransaction) {
            $command[] = '--single-transaction';
        }

        if ($this->skipLockTables) {
            $command[] = '--skip-lock-tables';
        }

        $command[] = '--skip-add-locks';

        if ($this->doNotUseColumnStatistics) {
            $command[] = '--column-statistics=0';
        }

        if ($this->useQuick) {
            $command[] = '--quick';
        }

        if ($this->includeRoutines) {
            $command[] = '--routines';
        }

        foreach ($this->excludeTables as $tableName) {
            $command[] = "--ignore-table={$this->dbName}.{$tableName}";
        }

        foreach ($this->excludeTablesData as $tableName) {
            $command[] = "--ignore-table-data={$this->dbName}.{$tableName}";
        }

        if ($this->databaseClientDriver() === 'mysql') {
            $command[] = '--no-tablespaces';
        }

        if ($this->defaultCharacterSet !== '') {
            $command[] = "--default-character-set={$this->defaultCharacterSet}";
        }

        if ($this->setGtidPurged !== 'AUTO') {
            $command[] = "--set-gtid-purged={$this->setGtidPurged}";
        }

        $command[] = $this->safeArgument($this->dbName);

        if ($this->includeTables !== []) {
            $command[] = '--tables';

            foreach ($this->includeTables as $tableName) {
                $command[] = $this->safeArgument($tableName);
            }
        }

        return $command;
    }

    private function safeArgument(string $value): string
    {
        if ($value === ''
            || str_starts_with($value, '-')
            || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new RuntimeException('A database dump argument is invalid.');
        }

        return $value;
    }

    /** @param resource $handle */
    private function writeAll($handle, string $contents): void
    {
        while ($contents !== '') {
            $written = fwrite($handle, $contents);

            if ($written === false || $written === 0) {
                throw new RuntimeException('A protected database backup file could not be written.');
            }

            $contents = substr($contents, $written);
        }
    }

    private function sanitizeFailureDetail(string $failureDetail, string $credentialsPath): string
    {
        $sanitized = str_replace($credentialsPath, '[temporary credentials file]', $failureDetail);

        if ($this->password !== '') {
            $sanitized = str_replace($this->password, '[redacted]', $sanitized);
        }

        $sanitized = preg_replace('/--defaults-file=\S+/', '--defaults-file=[temporary credentials file]', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/(--password(?:=|\s+)|\bpassword\s*[:=]\s*)\S+/i', '$1[redacted]', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/\s+/', ' ', $sanitized) ?? $sanitized;

        return mb_substr(trim($sanitized), 0, 500);
    }

    /** @return list<string> */
    abstract protected function credentialsFileArguments(string $credentialsPath): array;

    abstract protected function databaseClientDriver(): string;
}
