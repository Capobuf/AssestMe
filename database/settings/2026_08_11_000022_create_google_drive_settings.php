<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->addIfMissing('google_drive.client_id', null);
        $this->addEncryptedIfMissing('google_drive.encrypted_client_secret');
        $this->addIfMissing('google_drive.sync_enabled', true);
        $this->addIfMissing('google_drive.google_account_email', null);
        $this->addEncryptedIfMissing('google_drive.encrypted_refresh_token');
        $this->addIfMissing('google_drive.root_folder_id', null);
        $this->addIfMissing('google_drive.root_folder_name', null);
    }

    private function addIfMissing(string $property, bool|string|null $value): void
    {
        if (! $this->migrator->exists($property)) {
            $this->migrator->add($property, $value);
        }
    }

    private function addEncryptedIfMissing(string $property): void
    {
        if (! $this->migrator->exists($property)) {
            $this->migrator->addEncrypted($property, null);
        }
    }
};
