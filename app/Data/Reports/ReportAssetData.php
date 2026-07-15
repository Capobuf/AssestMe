<?php

declare(strict_types=1);

namespace App\Data\Reports;

final readonly class ReportAssetData
{
    public function __construct(
        public int $id,
        public ?string $name,
        public string $type,
        public ?string $site,
        public ?string $manufacturer,
        public ?string $model,
        public ?string $hostname,
        public ?string $ipAddress,
        public string $displayLabel,
    ) {}

    /** @return array{id: int, name: string|null, type: string, site: string|null, manufacturer: string|null, model: string|null, hostname: string|null, ip_address: string|null, display_label: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'site' => $this->site,
            'manufacturer' => $this->manufacturer,
            'model' => $this->model,
            'hostname' => $this->hostname,
            'ip_address' => $this->ipAddress,
            'display_label' => $this->displayLabel,
        ];
    }
}
