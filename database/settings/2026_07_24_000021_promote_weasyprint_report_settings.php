<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('report.show_resolution', true);
        $this->migrator->delete('report.repeated_header_footer');
        $this->migrator->delete('report.header_text');
        $this->migrator->delete('report.footer_text');
        $this->migrator->delete('report.new_page_per_finding');
    }
};
