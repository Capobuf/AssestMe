<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Attributes\ShouldBeEncrypted;
use Spatie\LaravelSettings\Settings;

final class GoogleDriveSettings extends Settings
{
    public ?string $client_id;

    #[ShouldBeEncrypted]
    public ?string $encrypted_client_secret;

    public bool $sync_enabled;

    public ?string $google_account_email;

    #[ShouldBeEncrypted]
    public ?string $encrypted_refresh_token;

    public ?string $root_folder_id;

    public ?string $root_folder_name;

    public static function group(): string
    {
        return 'google_drive';
    }
}
