<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Attributes\ShouldBeEncrypted;
use Spatie\LaravelSettings\Settings;

final class FattureInCloudSettings extends Settings
{
    public ?string $client_id;

    #[ShouldBeEncrypted]
    public ?string $encrypted_client_secret;

    #[ShouldBeEncrypted]
    public ?string $encrypted_access_token;

    public ?string $access_token_expires_at;

    #[ShouldBeEncrypted]
    public ?string $encrypted_refresh_token;

    public ?string $company_id;

    public ?string $company_name;

    public ?string $default_vat_type_id;

    public ?string $default_vat_type_label;

    public ?int $scope_version;

    public static function group(): string
    {
        return 'fatture_in_cloud';
    }
}
