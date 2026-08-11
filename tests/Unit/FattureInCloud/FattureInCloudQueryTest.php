<?php

declare(strict_types=1);

use App\Services\FattureInCloud\FattureInCloudQuery;

it('builds provider filters without treating search text as query syntax', function (): void {
    expect(FattureInCloudQuery::fiscalIdentifier('vat_number', '01234567890'))
        ->toBe("vat_number = '01234567890'")
        ->and(FattureInCloudQuery::fiscalIdentifier('tax_code', 'RSSMRA80A01H501U'))
        ->toBe("tax_code = 'RSSMRA80A01H501U'")
        ->and(FattureInCloudQuery::documentSubject("[ASSESTME client's quote]"))
        ->toBe("any_subject contains '[ASSESTME client''s quote]'")
        ->and(FattureInCloudQuery::productSearch("l'offerta"))
        ->toBe("(name contains 'l''offerta' or code contains 'l''offerta' or description contains 'l''offerta')");
});

it('rejects unsupported client filter fields', function (): void {
    expect(fn (): string => FattureInCloudQuery::fiscalIdentifier('name', 'Demo'))
        ->toThrow(InvalidArgumentException::class);
});
