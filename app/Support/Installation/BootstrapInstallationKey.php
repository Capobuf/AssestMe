<?php

declare(strict_types=1);

namespace App\Support\Installation;

use RuntimeException;

final class BootstrapInstallationKey
{
    private const KEY_BYTES = 32;

    public static function apply(string $basePath): void
    {
        $basePath = rtrim($basePath, DIRECTORY_SEPARATOR);

        if (self::processEnvironmentKey() !== null) {
            return;
        }

        $fileKey = self::environmentFileKey($basePath.DIRECTORY_SEPARATOR.'.env');

        if ($fileKey !== null) {
            self::setProcessEnvironmentKey($fileKey);

            return;
        }

        $storagePath = self::storagePath($basePath);

        if (is_file($storagePath.'/app/private/installed.lock')) {
            throw new RuntimeException('APP_KEY is missing from an installed AssestMe instance.');
        }

        $directory = $storagePath.'/framework/installer';
        self::ensurePrivateDirectory($directory);

        $lockPath = $directory.'/bootstrap-key.lock';
        $lock = fopen($lockPath, 'c+b');

        if ($lock === false) {
            throw new RuntimeException('Installer bootstrap key lock could not be opened.');
        }

        try {
            @chmod($lockPath, 0600);

            if (! flock($lock, LOCK_EX)) {
                throw new RuntimeException('Installer bootstrap key lock could not be acquired.');
            }

            $path = $directory.'/bootstrap-key';

            if (is_link($path)) {
                throw new RuntimeException('Installer bootstrap key path must not be a symbolic link.');
            }

            if (! is_file($path)) {
                $key = 'base64:'.base64_encode(random_bytes(self::KEY_BYTES));
                $handle = @fopen($path, 'x+b');

                if ($handle === false) {
                    throw new RuntimeException('Installer bootstrap key could not be created.');
                }

                try {
                    if (! flock($handle, LOCK_EX) || fwrite($handle, $key) !== strlen($key) || ! fflush($handle)) {
                        throw new RuntimeException('Installer bootstrap key could not be written.');
                    }
                } finally {
                    fclose($handle);
                }

                if (! @chmod($path, 0600)) {
                    throw new RuntimeException('Installer bootstrap key permissions could not be secured.');
                }
            }

            $key = trim((string) @file_get_contents($path));

            if (! self::isValidKey($key)) {
                throw new RuntimeException('Installer bootstrap key is invalid.');
            }

            self::setProcessEnvironmentKey($key);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public static function isValidKey(string $key): bool
    {
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            return is_string($decoded) && strlen($decoded) === self::KEY_BYTES;
        }

        return strlen($key) === self::KEY_BYTES;
    }

    public static function removeAfterCompletion(
        string $basePath,
        string $expectedKey,
        ?string $environmentPath = null,
        ?string $installedLockPath = null,
        ?string $bootstrapKeyPath = null,
    ): void {
        $basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
        $storagePath = self::storagePath($basePath);
        $environmentPath ??= $basePath.'/.env';
        $installedLockPath ??= $storagePath.'/app/private/installed.lock';
        $bootstrapKeyPath ??= $storagePath.'/framework/installer/bootstrap-key';

        if (! is_file($installedLockPath) || is_link($installedLockPath)) {
            throw new RuntimeException('The installer bootstrap key cannot be removed before the installation lock exists.');
        }

        $environmentKey = self::environmentFileKey($environmentPath);

        if ($environmentKey === null || ! hash_equals($expectedKey, $environmentKey)) {
            throw new RuntimeException('The definitive environment key does not match the installer bootstrap key.');
        }

        foreach ([$bootstrapKeyPath, $bootstrapKeyPath.'.lock'] as $path) {
            if (is_link($path)) {
                throw new RuntimeException('An installer bootstrap key path is an unexpected symbolic link.');
            }

            if (is_file($path) && ! @unlink($path)) {
                throw new RuntimeException('The installer bootstrap key could not be removed after completion.');
            }
        }
    }

    private static function processEnvironmentKey(): ?string
    {
        $key = getenv('APP_KEY');

        return is_string($key) && self::isValidKey($key) ? $key : null;
    }

    private static function environmentFileKey(string $path): ?string
    {
        if (! is_file($path) || is_link($path)) {
            return null;
        }

        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('AssestMe environment file is not readable.');
        }

        try {
            while (($line = fgets($handle)) !== false) {
                if (! preg_match('/^\s*APP_KEY\s*=\s*(.*)\s*$/', rtrim($line, "\r\n"), $matches)) {
                    continue;
                }

                $key = trim($matches[1]);

                if (strlen($key) >= 2 && (($key[0] === '"' && str_ends_with($key, '"')) || ($key[0] === "'" && str_ends_with($key, "'")))) {
                    $key = substr($key, 1, -1);
                }

                return self::isValidKey($key) ? $key : null;
            }
        } finally {
            fclose($handle);
        }

        return null;
    }

    private static function ensurePrivateDirectory(string $directory): void
    {
        if (is_link($directory)) {
            throw new RuntimeException('Installer runtime directory must not be a symbolic link.');
        }

        if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Installer runtime directory is not writable.');
        }

        if (! is_writable($directory) || ! @chmod($directory, 0700)) {
            throw new RuntimeException('Installer runtime directory permissions could not be secured.');
        }
    }

    private static function setProcessEnvironmentKey(string $key): void
    {
        putenv('APP_KEY='.$key);
        $_ENV['APP_KEY'] = $key;
        $_SERVER['APP_KEY'] = $key;
    }

    private static function storagePath(string $basePath): string
    {
        $configured = getenv('LARAVEL_STORAGE_PATH');

        if (is_string($configured) && str_starts_with($configured, DIRECTORY_SEPARATOR)) {
            return rtrim($configured, DIRECTORY_SEPARATOR);
        }

        return $basePath.'/storage';
    }
}
