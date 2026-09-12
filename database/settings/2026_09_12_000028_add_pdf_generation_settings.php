<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->addIfMissing('report.pdf_image_dpi', 150);
        $this->addIfMissing('report.pdf_jpeg_quality', 85);
        $this->addIfMissing('report.pdf_optimize_images', true);
    }

    private function addIfMissing(string $property, bool|int|string|null $value): void
    {
        if (! $this->migrator->exists($property)) {
            $this->migrator->add($property, $value);
        }
    }
};
