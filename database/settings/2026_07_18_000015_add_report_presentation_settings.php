<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->addIfMissing('report.cover_title_mode', 'separate');
        $this->addIfMissing('report.show_priority_descriptions', true);
    }

    private function addIfMissing(string $property, bool|int|string|null $value): void
    {
        if (! $this->migrator->exists($property)) {
            $this->migrator->add($property, $value);
        }
    }
};
