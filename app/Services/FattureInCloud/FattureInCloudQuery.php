<?php

declare(strict_types=1);

namespace App\Services\FattureInCloud;

final class FattureInCloudQuery
{
    public static function fiscalIdentifier(string $field, string $identifier): string
    {
        if (! in_array($field, ['vat_number', 'tax_code'], true)) {
            throw new \InvalidArgumentException('Unsupported Fatture in Cloud client filter field.');
        }

        return $field.' = '.self::literal($identifier);
    }

    public static function productSearch(string $search): string
    {
        $value = self::literal($search);

        return "(name contains {$value} or code contains {$value} or description contains {$value})";
    }

    public static function documentSubject(string $subject): string
    {
        return 'any_subject contains '.self::literal($subject);
    }

    private static function literal(string $value): string
    {
        // Fatture in Cloud documents string values with single-quoted SQL-like
        // literals. Doubling quotes keeps user-entered search text inside it.
        return "'".str_replace("'", "''", $value)."'";
    }
}
