<?php

declare(strict_types=1);

namespace App\Services\Installation;

use App\Data\Installation\ApplicationConfigurationData;
use App\Data\Installation\DatabaseConfigurationData;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

final class InstallationRuntimeConfigurator
{
    public function apply(
        ApplicationConfigurationData $application,
        DatabaseConfigurationData $database,
    ): void {
        $previousConnection = (string) Config::get('database.default', 'sqlite');

        Config::set([
            'app.name' => $application->name,
            'app.url' => $application->url,
            'app.timezone' => $application->timezone,
            'app.locale' => $application->locale,
            'app.fallback_locale' => 'it',
            'database.default' => $database->driver->value,
            'database.connections.'.$database->driver->value => $database->toLaravelConfig(),
            'cache.default' => 'file',
            'session.driver' => 'file',
            'session.encrypt' => true,
            'queue.default' => 'sync',
            'filesystems.default' => 'local',
            'laravel-pdf.driver' => 'weasyprint',
            'laravel-pdf.weasyprint.binary' => $application->weasyPrintBinary,
            'assestme.installation.php_binary' => $application->phpBinary,
            'assestme.backup.root' => $application->backupRoot,
        ]);

        app()->setLocale($application->locale);
        date_default_timezone_set($application->timezone);
        DB::purge($previousConnection);
        DB::purge($database->driver->value);
        DB::setDefaultConnection($database->driver->value);
        DB::connection()->getPdo();
    }
}
