<?php

declare(strict_types=1);

use App\Actions\Storage\CleanupDeletionOperations;
use App\Actions\Storage\DeleteEntityAccordingToPolicy;
use App\Enums\DeletionOperationStatus;
use App\Enums\DeletionPolicy;
use App\Models\Client;
use App\Models\DeletionOperation;
use App\Models\Site;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->privateRoot = storage_path('framework/testing/deletion-'.bin2hex(random_bytes(6)));
    File::ensureDirectoryExists($this->privateRoot);
    config()->set('assestme.backup.private_storage_path', $this->privateRoot);
    config()->set('assestme.deletion.trash_root', $this->privateRoot.DIRECTORY_SEPARATOR.'.trash');
});

afterEach(function (): void {
    File::deleteDirectory($this->privateRoot);
});

it('archives an entity without moving or deleting its private files', function (): void {
    $client = Client::factory()->create();
    $relative = "clients/{$client->id}/logo.png";
    $absolute = $this->privateRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    File::ensureDirectoryExists(dirname($absolute));
    File::put($absolute, 'logo');

    $operation = app(DeleteEntityAccordingToPolicy::class)->handle(
        $client,
        DeletionPolicy::Archive,
        [$relative],
    );

    expect($operation)->toBeNull()
        ->and(Client::withTrashed()->find($client->id)?->trashed())->toBeTrue()
        ->and(File::exists($absolute))->toBeTrue()
        ->and(DeletionOperation::query()->count())->toBe(0);
});

it('stages files, permanently deletes the entity, and records completed cleanup', function (): void {
    $client = Client::factory()->create();
    $relative = "clients/{$client->id}/logo.png";
    $absolute = $this->privateRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    File::ensureDirectoryExists(dirname($absolute));
    File::put($absolute, 'permanent-logo');

    $operation = app(DeleteEntityAccordingToPolicy::class)->handle(
        $client,
        DeletionPolicy::Permanent,
        [$relative],
    );

    expect($operation)->not->toBeNull()
        ->and($operation?->status)->toBe(DeletionOperationStatus::Cleaned)
        ->and($operation?->manifest[0]['sha256'])->toBe(hash('sha256', 'permanent-logo'))
        ->and(Client::withTrashed()->find($client->id))->toBeNull()
        ->and(File::exists($absolute))->toBeFalse()
        ->and(File::isDirectory($this->privateRoot.DIRECTORY_SEPARATOR.$operation?->trash_path))->toBeFalse();
});

it('restores staged files when the database deletion is rejected', function (): void {
    $client = Client::factory()->create();
    Site::factory()->for($client)->create();
    $relative = "clients/{$client->id}/logo.png";
    $absolute = $this->privateRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    File::ensureDirectoryExists(dirname($absolute));
    File::put($absolute, 'recover-me');

    expect(fn () => app(DeleteEntityAccordingToPolicy::class)->handle(
        $client,
        DeletionPolicy::Permanent,
        [$relative],
    ))->toThrow(QueryException::class);

    $operation = DeletionOperation::query()->sole();

    expect($operation->status)->toBe(DeletionOperationStatus::Restored)
        ->and($operation->error_text)->not->toBeNull()
        ->and(Client::query()->find($client->id))->not->toBeNull()
        ->and(File::get($absolute))->toBe('recover-me');
});

it('rejects path traversal and missing referenced files before changing persistent state', function (string $path, string $exception): void {
    $client = Client::factory()->create();

    expect(fn () => app(DeleteEntityAccordingToPolicy::class)->handle(
        $client,
        DeletionPolicy::Permanent,
        [$path],
    ))->toThrow($exception);

    expect(Client::query()->find($client->id))->not->toBeNull()
        ->and(DeletionOperation::query()->count())->toBe(0);
})->with([
    'path traversal' => ['../database/database.sqlite', InvalidArgumentException::class],
    'missing file' => ['clients/missing/logo.png', RuntimeException::class],
]);

it('retries a committed cleanup and removes only its validated trash directory', function (): void {
    $uuid = fake()->uuid();
    $relativeTrash = ".trash/{$uuid}";
    $absoluteTrash = $this->privateRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativeTrash);
    File::ensureDirectoryExists($absoluteTrash);
    File::put($absoluteTrash.DIRECTORY_SEPARATOR.'staged.bin', 'staged');
    $operation = DeletionOperation::query()->create([
        'uuid' => $uuid,
        'entity_type' => Client::class,
        'entity_id' => 404,
        'status' => DeletionOperationStatus::Committed,
        'trash_path' => $relativeTrash,
        'manifest' => [],
    ]);

    $result = app(CleanupDeletionOperations::class)->handle();

    expect($result->processed)->toBe(1)
        ->and($result->cleaned)->toBe(1)
        ->and($result->failed)->toBe(0)
        ->and($operation->fresh()?->status)->toBe(DeletionOperationStatus::Cleaned)
        ->and(File::isDirectory($absoluteTrash))->toBeFalse();
});

it('keeps a cleanup failure visible and exits the command non-zero', function (): void {
    $uuid = fake()->uuid();
    $relativeTrash = ".trash/{$uuid}";
    $absoluteTrash = $this->privateRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativeTrash);
    $operation = DeletionOperation::query()->create([
        'uuid' => $uuid,
        'entity_type' => Client::class,
        'entity_id' => 405,
        'status' => DeletionOperationStatus::CleanupFailed,
        'trash_path' => $relativeTrash,
        'manifest' => [],
        'error_text' => 'Initial cleanup failure.',
    ]);

    $files = Mockery::mock(Filesystem::class);
    $files->shouldReceive('isDirectory')->once()->with($absoluteTrash)->andReturnTrue();
    $files->shouldReceive('deleteDirectory')->once()->with($absoluteTrash)->andReturnFalse();
    app()->instance(Filesystem::class, $files);

    $this->artisan('assestme:storage:cleanup')
        ->expectsOutputToContain('non riuscite: 1')
        ->assertFailed();

    expect($operation->fresh()?->status)->toBe(DeletionOperationStatus::CleanupFailed)
        ->and($operation->fresh()?->error_text)->toContain('could not be removed');
});

it('refuses a cleanup operation whose persisted path does not match its UUID', function (): void {
    $operation = DeletionOperation::query()->create([
        'uuid' => fake()->uuid(),
        'entity_type' => Client::class,
        'entity_id' => 406,
        'status' => DeletionOperationStatus::Committed,
        'trash_path' => '.trash/another-operation',
        'manifest' => [],
    ]);

    $result = app(CleanupDeletionOperations::class)->handle();

    expect($result->failed)->toBe(1)
        ->and($operation->fresh()?->status)->toBe(DeletionOperationStatus::CleanupFailed)
        ->and($operation->fresh()?->error_text)->toContain('invalid trash path');
});
