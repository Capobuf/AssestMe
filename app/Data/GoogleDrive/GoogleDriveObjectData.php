<?php

declare(strict_types=1);

namespace App\Data\GoogleDrive;

final readonly class GoogleDriveObjectData
{
    public function __construct(
        public string $id,
        public string $name,
        public string $mimeType,
        public ?string $webViewLink,
    ) {}
}
