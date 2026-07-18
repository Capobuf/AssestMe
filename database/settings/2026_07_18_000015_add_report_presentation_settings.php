<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('report.cover_title_mode', 'separate');
        $this->migrator->add('report.show_priority_descriptions', true);
    }
};
