<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        if (! $this->migrator->exists('report.show_resolution')) {
            $this->migrator->add('report.show_resolution', true);
        }

        $this->migrator->deleteIfExists('report.repeated_header_footer');
        $this->migrator->deleteIfExists('report.header_text');
        $this->migrator->deleteIfExists('report.footer_text');
        $this->migrator->deleteIfExists('report.new_page_per_finding');
    }
};
