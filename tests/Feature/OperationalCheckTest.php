<?php

declare(strict_types=1);

use App\Actions\Operations\RecordOperationalCheck;
use App\Enums\OperationalCheckStatus;
use App\Enums\OperationalCheckType;
use App\Services\Operations\OperationalCheckStore;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    $this->operationalStatusRoot = storage_path('framework/testing/operational-status-'.bin2hex(random_bytes(6)));
    File::ensureDirectoryExists($this->operationalStatusRoot);
    config()->set('assestme.backup.private_storage_path', $this->operationalStatusRoot);
});

afterEach(function (): void {
    File::deleteDirectory($this->operationalStatusRoot);
});

it('persists a successful SQLite integrity check', function (): void {
    expect(Artisan::call('assestme:integrity-check'))->toBe(0)
        ->and(Artisan::output())->toContain(__('assestme.operations.integrity_passed'));

    $check = app(OperationalCheckStore::class)->find(OperationalCheckType::DatabaseIntegrity);

    expect($check)->not->toBeNull()
        ->and($check?->status)->toBe(OperationalCheckStatus::Succeeded)
        ->and($check?->lastSucceededAt)->not->toBeNull()
        ->and($check?->lastFailedAt)->toBeNull()
        ->and($check?->errorText)->toBeNull();
});

it('persists and logs a failed SQLite integrity check', function (): void {
    Log::spy();
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')
        ->twice()
        ->andReturn('sqlite');
    $connection->shouldReceive('select')
        ->once()
        ->with('PRAGMA integrity_check')
        ->andReturn([(object) ['integrity_check' => 'database disk image is malformed']]);
    DB::partialMock()
        ->shouldReceive('connection')
        ->once()
        ->andReturn($connection);
    expect(Artisan::call('assestme:integrity-check'))->toBe(1)
        ->and(Artisan::output())->toContain('Controllo di integrità del database fallito');

    $check = app(OperationalCheckStore::class)->find(OperationalCheckType::DatabaseIntegrity);

    expect($check)->not->toBeNull()
        ->and($check?->status)->toBe(OperationalCheckStatus::Failed)
        ->and($check?->lastFailedAt)->not->toBeNull()
        ->and($check?->lastSucceededAt)->toBeNull()
        ->and($check?->errorText)->toContain('database disk image is malformed');

    Log::shouldHaveReceived('error')
        ->once()
        ->with('Scheduled database integrity check failed.', Mockery::type('array'));
});

it('keeps the last success timestamp while replacing a failure warning after recovery', function (): void {
    $record = app(RecordOperationalCheck::class);
    $successAt = CarbonImmutable::parse('2026-07-17 06:00:00', 'UTC');
    $failureAt = $successAt->addHour();
    $recoveryAt = $failureAt->addHour();

    $record(
        OperationalCheckType::Backup,
        OperationalCheckStatus::Succeeded,
        attemptedAt: $successAt,
    );
    $failed = $record(
        OperationalCheckType::Backup,
        OperationalCheckStatus::Failed,
        'disk full',
        $failureAt,
    );

    expect($failed->lastSucceededAt?->equalTo($successAt))->toBeTrue()
        ->and($failed->lastFailedAt?->equalTo($failureAt))->toBeTrue()
        ->and($failed->errorText)->toBe('disk full');

    $recovered = $record(
        OperationalCheckType::Backup,
        OperationalCheckStatus::Succeeded,
        attemptedAt: $recoveryAt,
    );

    expect($recovered->status)->toBe(OperationalCheckStatus::Succeeded)
        ->and($recovered->lastSucceededAt?->equalTo($recoveryAt))->toBeTrue()
        ->and($recovered->lastFailedAt?->equalTo($failureAt))->toBeTrue()
        ->and($recovered->errorText)->toBeNull();
});
