<?php

declare(strict_types=1);

use App\Enums\GeneratedReportFormat;
use App\Services\GoogleDrive\GoogleDriveName;

it('keeps stable padded local IDs while sanitizing readable Drive names', function (): void {
    $names = new GoogleDriveName;

    expect($names->client(1, "  Labor/\u{0000}vetro  "))->toBe('C-000001 - Labor vetro')
        ->and($names->assessment(12, '2026-08-11', 'Assessment: IT / rete'))->toBe('A-000012 - 2026-08-11 - Assessment IT rete')
        ->and($names->spreadsheet(12))->toBe('Findings - A-000012')
        ->and($names->evidence(101, 221, 'Configurazione / backup', 'immagine.jpg'))
        ->toBe('F-000101 - E-000221 - Configurazione backup.jpg')
        ->and($names->generatedReport(41, GeneratedReportFormat::Pdf, 1))->toBe('R-000041 - Report v1.pdf')
        ->and($names->generatedReport(42, GeneratedReportFormat::Xlsx, 2))->toBe('R-000042 - Esportazione v2.xlsx');
});

it('uses readable fallbacks without losing stable identity', function (): void {
    $names = new GoogleDriveName;

    expect($names->client(8, '///'))->toBe('C-000008 - Azienda')
        ->and($names->assessment(9, '2026-08-11', '***'))->toBe('A-000009 - 2026-08-11 - Assessment')
        ->and($names->evidence(2, 3, '', 'no-extension'))->toBe('F-000002 - E-000003 - Evidenza');
});
