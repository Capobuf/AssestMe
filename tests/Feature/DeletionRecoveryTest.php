<?php

declare(strict_types=1);

use App\Actions\Storage\DeleteEntityAccordingToPolicy;
use App\Enums\DeletionOperationStatus;
use App\Enums\DeletionPolicy;
use App\Models\Client;
use App\Models\DeletionOperation;
use App\Models\Site;
use Illuminate\Database\QueryException;
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
