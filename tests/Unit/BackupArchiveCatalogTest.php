<?php

declare(strict_types=1);

use App\Actions\Backups\DeleteBackup;
use App\Services\Backups\BackupArchiveCatalog;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->catalogRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'assestme-catalog-'.Str::uuid();
    config()->set('assestme.backup.root', $this->catalogRoot);
});

afterEach(function (): void {
    if (is_link($this->catalogRoot)) {
        unlink($this->catalogRoot);
    } else {
        File::deleteDirectory($this->catalogRoot);
    }
});

it('returns no archives for a missing or empty backup directory', function (): void {
    expect(app(BackupArchiveCatalog::class)->all())->toBe([]);

    File::ensureDirectoryExists($this->catalogRoot);

    expect(app(BackupArchiveCatalog::class)->all())->toBe([]);
});

it('lists ordinary and safety archives while ignoring unknown files and subdirectories', function (): void {
    File::ensureDirectoryExists($this->catalogRoot.DIRECTORY_SEPARATOR.'nested');
    File::put($this->catalogRoot.DIRECTORY_SEPARATOR.'assestme-20260725-023000.tar.gz', 'ordinary');
    File::put($this->catalogRoot.DIRECTORY_SEPARATOR.'assestme-safety-20260725-024000.tar.gz', 'safety');
    File::put($this->catalogRoot.DIRECTORY_SEPARATOR.'manual.tar.gz', 'unknown');
    File::put(
        $this->catalogRoot.DIRECTORY_SEPARATOR.'nested'.DIRECTORY_SEPARATOR.'assestme-20260725-025000.tar.gz',
        'nested',
    );

    $archives = app(BackupArchiveCatalog::class)->all();

    expect($archives)->toHaveCount(2)
        ->and(collect($archives)->pluck('kind')->sort()->values()->all())->toBe(['backup', 'safety'])
        ->and(collect($archives)->pluck('name')->all())->not->toContain('manual.tar.gz');
});

it('sorts managed archives by descending filesystem modification time', function (): void {
    File::ensureDirectoryExists($this->catalogRoot);
    $older = $this->catalogRoot.DIRECTORY_SEPARATOR.'assestme-20260725-030000.tar.gz';
    $newer = $this->catalogRoot.DIRECTORY_SEPARATOR.'assestme-20260724-030000.tar.gz';
    File::put($older, 'older');
    File::put($newer, 'newer');
    touch($older, 100);
    touch($newer, 200);

    expect(collect(app(BackupArchiveCatalog::class)->all())->pluck('name')->all())->toBe([
        basename($newer),
        basename($older),
    ]);
});

it('rejects unsafe and unmanaged archive names', function (string $name): void {
    File::ensureDirectoryExists($this->catalogRoot);

    expect(fn () => app(BackupArchiveCatalog::class)->resolveManagedArchive($name))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'empty' => '',
    'slash' => '../assestme-20260725-023000.tar.gz',
    'backslash' => '..\\assestme-20260725-023000.tar.gz',
    'dot' => '.',
    'dot dot' => '..',
    'nul' => "assestme-20260725-023000.tar.gz\0",
    'similar name' => 'assestme-20260725-023000.tar.gz.tmp',
]);

it('rejects missing files symlinks and external targets', function (): void {
    File::ensureDirectoryExists($this->catalogRoot);
    $name = 'assestme-20260725-023000.tar.gz';

    expect(fn () => app(BackupArchiveCatalog::class)->resolveManagedArchive($name))
        ->toThrow(RuntimeException::class);

    $external = sys_get_temp_dir().DIRECTORY_SEPARATOR.'assestme-external-'.Str::uuid().'.tar.gz';
    File::put($external, 'external');
    symlink($external, $this->catalogRoot.DIRECTORY_SEPARATOR.$name);

    try {
        expect(app(BackupArchiveCatalog::class)->all())->toBe([])
            ->and(fn () => app(BackupArchiveCatalog::class)->resolveManagedArchive($name))
            ->toThrow(RuntimeException::class)
            ->and(fn () => app(BackupArchiveCatalog::class)->resolveManagedArchive($external))
            ->toThrow(InvalidArgumentException::class);
    } finally {
        unlink($this->catalogRoot.DIRECTORY_SEPARATOR.$name);
        File::delete($external);
    }
});

it('requires an absolute configured backup root', function (): void {
    config()->set('assestme.backup.root', 'relative/backups');

    expect(fn () => app(BackupArchiveCatalog::class)->all())
        ->toThrow(InvalidArgumentException::class);
});

it('never deletes unknown external or symlinked files', function (): void {
    File::ensureDirectoryExists($this->catalogRoot);
    $unknown = $this->catalogRoot.DIRECTORY_SEPARATOR.'manual.tar.gz';
    $external = sys_get_temp_dir().DIRECTORY_SEPARATOR.'assestme-external-'.Str::uuid().'.tar.gz';
    $linkName = 'assestme-20260725-023000.tar.gz';
    $link = $this->catalogRoot.DIRECTORY_SEPARATOR.$linkName;
    File::put($unknown, 'unknown');
    File::put($external, 'external');
    symlink($external, $link);

    try {
        expect(fn () => app(DeleteBackup::class)('manual.tar.gz'))->toThrow(InvalidArgumentException::class)
            ->and(fn () => app(DeleteBackup::class)($external))->toThrow(InvalidArgumentException::class)
            ->and(fn () => app(DeleteBackup::class)($linkName))->toThrow(RuntimeException::class)
            ->and(File::exists($unknown))->toBeTrue()
            ->and(File::exists($external))->toBeTrue();
    } finally {
        unlink($link);
        File::delete($unknown);
        File::delete($external);
    }
});
