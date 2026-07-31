<?php

declare(strict_types=1);

namespace App\Data\Installation;

final readonly class ApplicationConfigurationData
{
    public function __construct(
        public string $name,
        public string $url,
        public string $timezone,
        public string $locale,
        public string $backupRoot,
        public string $weasyPrintBinary,
        public string $phpBinary,
    ) {}

    /** @return array{name: string, url: string, timezone: string, locale: string, backup_root: string, weasyprint_binary: string, php_binary: string} */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'url' => $this->url,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'backup_root' => $this->backupRoot,
            'weasyprint_binary' => $this->weasyPrintBinary,
            'php_binary' => $this->phpBinary,
        ];
    }

    /** @param array{name: string, url: string, timezone: string, locale: string, backup_root: string, weasyprint_binary: string, php_binary: string} $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            url: $data['url'],
            timezone: $data['timezone'],
            locale: $data['locale'],
            backupRoot: $data['backup_root'],
            weasyPrintBinary: $data['weasyprint_binary'],
            phpBinary: $data['php_binary'],
        );
    }
}
