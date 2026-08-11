<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->deleteIfExists('google_drive.redirect_uri');
        $this->migrator->deleteIfExists('google_drive.encrypted_picker_api_key');
        $this->migrator->deleteIfExists('google_drive.project_number');
    }
};
