<?php

declare(strict_types=1);

namespace App\Data\FattureInCloud;

final readonly class FattureInCloudVatTypeData
{
    public function __construct(
        public string $id,
        public ?float $value,
        public string $description,
        public bool $disabled,
        public bool $default,
    ) {}

    public function label(): string
    {
        if ($this->value === null) {
            return $this->description !== ''
                ? $this->description
                : __('assestme.fatture_in_cloud.fields.vat_without_percentage', ['id' => $this->id]);
        }

        $percentage = rtrim(rtrim(number_format($this->value, 2, ',', ''), '0'), ',');

        return $this->description === ''
            ? $percentage.'%'
            : $percentage.'% — '.$this->description;
    }
}
