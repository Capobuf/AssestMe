<?php

declare(strict_types=1);

namespace App\Services\Database;

use RuntimeException;
use SensitiveParameter;
use Throwable;

final class DatabaseClientOptionFile
{
    public function create(
        string $directory,
        string $host,
        int $port,
        string $username,
        #[SensitiveParameter]
        string $password,
        string $socket,
        string $driver,
        ?string $sslCa,
    ): string {
        $path = $directory.DIRECTORY_SEPARATOR.'.assestme-db-credentials-'.bin2hex(random_bytes(12));
        $handle = @fopen($path, 'xb');
        $failed = false;

        if ($handle === false) {
            throw new RuntimeException('Temporary database credentials could not be created.');
        }

        try {
            if (! @chmod($path, 0600)) {
                throw new RuntimeException('Temporary database credential permissions could not be restricted.');
            }

            clearstatcache(true, $path);
            $permissions = fileperms($path);

            if ($permissions === false || ($permissions & 0777) !== 0600) {
                throw new RuntimeException('Temporary database credential permissions are invalid.');
            }

            $lines = [
                '[client]',
                'user='.$this->value($username),
                'password='.$this->value($password),
                'port='.$port,
            ];

            if ($socket !== '') {
                $lines[] = 'socket='.$this->value($socket);
            } else {
                $lines[] = 'host='.$this->value($host);
            }

            if ($sslCa !== null) {
                $lines[] = 'ssl-ca='.$this->value($sslCa);
            } elseif ($driver === 'mariadb') {
                // MariaDB 11.4+ verifies certificates by default. Without a configured CA,
                // retain encrypted client transport without claiming server authentication.
                $lines[] = 'ssl-verify-server-cert=0';
            }

            $this->writeAll($handle, implode(PHP_EOL, $lines).PHP_EOL);

            if (! fflush($handle)) {
                throw new RuntimeException('Temporary database credentials could not be persisted.');
            }
        } catch (Throwable) {
            $failed = true;
        } finally {
            if (! fclose($handle)) {
                $failed = true;
            }
        }

        if ($failed) {
            $this->delete($path);

            throw new RuntimeException('Temporary database credentials could not be created securely.');
        }

        return $path;
    }

    public function delete(string $path): bool
    {
        if (! file_exists($path) && ! is_link($path)) {
            return true;
        }

        return @unlink($path)
            && ! file_exists($path)
            && ! is_link($path);
    }

    private function value(string $value): string
    {
        if (preg_match('/[\x00-\x07\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1) {
            throw new RuntimeException('A database credential contains an unsupported control character.');
        }

        return '"'.strtr($value, [
            '\\' => '\\\\',
            '"' => '\\"',
            "\x08" => '\\b',
            "\t" => '\\t',
            "\n" => '\\n',
            "\r" => '\\r',
        ]).'"';
    }

    /** @param resource $handle */
    private function writeAll($handle, string $contents): void
    {
        while ($contents !== '') {
            $written = fwrite($handle, $contents);

            if ($written === false || $written === 0) {
                throw new RuntimeException('Protected database credentials could not be written.');
            }

            $contents = substr($contents, $written);
        }
    }
}
