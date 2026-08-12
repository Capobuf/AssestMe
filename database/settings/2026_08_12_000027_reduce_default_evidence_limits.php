<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->update(
            'general.max_evidence_file_mb',
            static fn (int $value): int => $value === 25 ? 5 : $value,
        );
        $this->migrator->update(
            'general.max_assessment_evidence_mb',
            static fn (int $value): int => $value === 250 ? 100 : $value,
        );
    }
};
