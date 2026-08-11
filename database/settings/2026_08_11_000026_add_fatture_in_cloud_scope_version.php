<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        if (! $this->migrator->exists('fatture_in_cloud.scope_version')) {
            $this->migrator->add('fatture_in_cloud.scope_version', null);
        }
    }
};
