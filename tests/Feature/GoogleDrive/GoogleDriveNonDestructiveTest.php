<?php

declare(strict_types=1);

use App\Data\GoogleDrive\GoogleDriveObjectData;
use App\Services\GoogleDrive\GoogleWorkspaceClient;
use Google\Service\Sheets\BatchUpdateValuesResponse;
use Revolution\Google\Sheets\SheetsClient;

it('rejects duplicate managed objects within the same parent', function (): void {
    $client = new class extends GoogleWorkspaceClient
    {
        public function __construct() {}

        /** @return list<GoogleDriveObjectData> */
        protected function managedChildren(string $parentId, string $managedPrefix, string $mimeType): array
        {
            return [
                new GoogleDriveObjectData('one', 'C-000001 - Primo', self::FOLDER_MIME_TYPE, null),
                new GoogleDriveObjectData('two', 'C-000001 - Secondo', self::FOLDER_MIME_TYPE, null),
            ];
        }
    };

    expect(fn () => $client->ensureFolder('root', 'C-000001', 'C-000001 - Azienda'))
        ->toThrow(RuntimeException::class, 'duplicati');
});

it('does not expose any remote delete operation', function (): void {
    expect(method_exists(GoogleWorkspaceClient::class, 'delete'))
        ->toBeFalse()
        ->and(method_exists(GoogleWorkspaceClient::class, 'deleteFolder'))->toBeFalse()
        ->and(method_exists(GoogleWorkspaceClient::class, 'deleteFile'))->toBeFalse();
});

it('rewrites only managed tabs and preserves a user-added tab', function (): void {
    $sheets = Mockery::mock(SheetsClient::class);
    $sheets->shouldReceive('spreadsheet')->once()->with('sheet-id')->andReturnSelf();
    $sheets->shouldReceive('sheetList')->twice()->andReturn([
        1 => 'Assessment',
        2 => 'Findings',
        3 => 'Soluzioni',
        4 => 'Evidenze',
        99 => 'Note manuali',
    ]);
    $sheets->shouldNotReceive('addSheet', 'deleteSheet');
    $sheets->shouldReceive('sheet')->times(8)->andReturnSelf();
    $sheets->shouldReceive('range')->with('A:Z')->times(4)->andReturnSelf();
    $sheets->shouldReceive('range')->with('A1')->times(4)->andReturnSelf();
    $sheets->shouldReceive('clear')->times(4);
    $updates = [];
    $sheets->shouldReceive('update')->times(4)->andReturnUsing(
        static function (array $rows) use (&$updates): BatchUpdateValuesResponse {
            $updates[] = $rows;

            return new BatchUpdateValuesResponse;
        },
    );

    $client = new class($sheets) extends GoogleWorkspaceClient
    {
        /** @var array<string, int|string> */
        public array $frozenSheetIds = [];

        public function __construct(private readonly SheetsClient $fakeSheets) {}

        protected function sheets(): SheetsClient
        {
            return $this->fakeSheets;
        }

        /** @param array<string, int|string> $sheetIds */
        protected function freezeTableHeaders(SheetsClient $sheets, string $spreadsheetId, array $sheetIds): void
        {
            $this->frozenSheetIds = $sheetIds;
        }
    };

    $rows = [['Prima', 'Seconda', 'Terza'], [0 => 'Valore', 1 => null, 2 => 'Finale']];
    $client->replaceSpreadsheet('sheet-id', $rows, $rows, $rows, $rows);

    expect($client->frozenSheetIds)->toHaveKey('Note manuali', 99)
        ->and($updates[0][1])->toBe(['Valore', '', 'Finale']);
});
