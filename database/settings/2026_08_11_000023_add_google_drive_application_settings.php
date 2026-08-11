<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->addIfMissing('google_drive.client_id');
        $this->addEncryptedIfMissing('google_drive.encrypted_client_secret');
    }

    private function addIfMissing(string $property): void
    {
        if (! $this->migrator->exists($property)) {
            $this->migrator->add($property, null);
        }
    }

    private function addEncryptedIfMissing(string $property): void
    {
        if (! $this->migrator->exists($property)) {
            $this->migrator->addEncrypted($property, null);
        }
    }
};
