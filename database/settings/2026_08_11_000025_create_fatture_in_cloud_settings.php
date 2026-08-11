<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->addIfMissing('fatture_in_cloud.client_id');
        $this->addEncryptedIfMissing('fatture_in_cloud.encrypted_client_secret');
        $this->addEncryptedIfMissing('fatture_in_cloud.encrypted_access_token');
        $this->addIfMissing('fatture_in_cloud.access_token_expires_at');
        $this->addEncryptedIfMissing('fatture_in_cloud.encrypted_refresh_token');
        $this->addIfMissing('fatture_in_cloud.company_id');
        $this->addIfMissing('fatture_in_cloud.company_name');
        $this->addIfMissing('fatture_in_cloud.default_vat_type_id');
        $this->addIfMissing('fatture_in_cloud.default_vat_type_label');
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
