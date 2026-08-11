<?php

declare(strict_types=1);

namespace App\Actions\FattureInCloud;

final class FattureInCloudFiscalIdentifier
{
    public static function vat(?string $value): ?string
    {
        $normalized = self::compact($value);
        if ($normalized !== null && str_starts_with($normalized, 'IT')) {
            $normalized = substr($normalized, 2);
        }

        return $normalized === '' ? null : $normalized;
    }

    public static function taxCode(?string $value): ?string
    {
        return self::compact($value);
    }

    private static function compact(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return mb_strtoupper((string) preg_replace('/\s+/u', '', trim($value)));
    }
}
